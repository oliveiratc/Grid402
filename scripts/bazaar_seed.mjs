#!/usr/bin/env node
/**
 * Grid402 // Bazaar Batch Seeder (Base Mainnet x402 v2)
 *
 * Descobre os endpoints pagos de `/.well-known/x402.json`, verifica quais ja estao
 * indexados no PayAI Bazaar e executa 1 liquidacao EIP-3009 real por rota faltante
 * para popular o catalogo publico.
 *
 * Seguranca:
 * - Por padrao roda em `--dry-run` (nao gasta nada). Passe `--execute` para assinar.
 * - A chave privada vem APENAS de `process.env.GRID402_BUYER_PRIVATE_KEY` em memoria.
 * - Pula `/api/v1/pass` (2.0000 USDC) por padrao; inclua `--include-pass` se desejar.
 *
 * Uso:
 *   node scripts/bazaar_seed.mjs
 *   node scripts/bazaar_seed.mjs --only=cnpj,workdays,forex
 *   $env:GRID402_BUYER_PRIVATE_KEY='0x...'; node scripts/bazaar_seed.mjs --execute
 */

import { ethers } from 'ethers';

const BASE_URL = (process.env.GRID402_BASE_URL || 'https://grid402.online').replace(/\/+$/, '');
const PAY_TO = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
const DISCOVERY_URL = `https://facilitator.payai.network/discovery/resources?payTo=${PAY_TO}`;
const BASE_RPC = 'https://mainnet.base.org';
const USDC_BASE = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

const QUERY_DEFAULTS = {
  'dns': { domain: 'base.org' },
  'domain-audit': { domain: 'base.org' },
  'ssl': { host: 'base.org' },
  'asn': { ip: '1.1.1.1' },
  'cep': { code: '01310100' },
  'cnpj': { cnpj: '00000000000191' },
  'sanctions': { cnpj: '00000000000191' },
  'simples': { cnpj: '00000000000191' },
  'compliance': { cnpj: '00000000000191' },
  'kyb-audit': { cnpj: '00000000000191' },
  'holidays': { year: '2026' },
  'workdays': { start_date: '2026-12-23', end_date: '2026-12-28' },
  'lei': { lei: '5493006MHB84DD0ZWV18' },
  'ofac': { target: '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90' },
  'notarize': { content: 'Grid402-M2M-Contract-v1' },
  'global-markets': { market: 'US', base: 'USD' },
};

function parseFlags(argv) {
  const flags = {
    execute: argv.includes('--execute'),
    includeExisting: argv.includes('--include-existing'),
    includePass: argv.includes('--include-pass'),
    only: null,
  };
  const onlyArg = argv.find((a) => a.startsWith('--only='));
  if (onlyArg) {
    flags.only = new Set(
      onlyArg.slice('--only='.length).split(',').map((s) => s.trim().toLowerCase()).filter(Boolean)
    );
  }
  return flags;
}

function buildTargetUrl(slug) {
  const params = QUERY_DEFAULTS[slug];
  const base = `${BASE_URL}/api/v1/${slug}`;
  if (!params) return base;
  const qs = new URLSearchParams(params).toString();
  return qs ? `${base}?${qs}` : base;
}

async function fetchJson(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  const text = await res.text();
  return { status: res.status, headers: res.headers, json: JSON.parse(text) };
}

async function readUsdcBalanceRaw(address) {
  const padded = address.toLowerCase().replace(/^0x/, '').padStart(64, '0');
  const res = await fetch(BASE_RPC, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      jsonrpc: '2.0',
      id: 1,
      method: 'eth_call',
      params: [{ to: USDC_BASE, data: `0x70a08231${padded}` }, 'latest'],
    }),
  });
  const data = await res.json();
  return BigInt(data?.result || '0x0');
}

async function signTransferAuthorization(wallet, accept, slug, challenge) {
  const amount = String(accept.amount);
  const nonce = '0x' + Buffer.from(ethers.randomBytes(32)).toString('hex');
  const validAfter = 0n;
  const validBefore = BigInt(Math.floor(Date.now() / 1000) + 300);

  const domain = {
    name: accept.extra?.name || 'USD Coin',
    version: accept.extra?.version || '2',
    chainId: 8453,
    verifyingContract: ethers.getAddress(accept.asset),
  };
  const types = {
    TransferWithAuthorization: [
      { name: 'from', type: 'address' },
      { name: 'to', type: 'address' },
      { name: 'value', type: 'uint256' },
      { name: 'validAfter', type: 'uint256' },
      { name: 'validBefore', type: 'uint256' },
      { name: 'nonce', type: 'bytes32' },
    ],
  };
  const value = {
    from: wallet.address,
    to: ethers.getAddress(accept.payTo),
    value: BigInt(amount),
    validAfter,
    validBefore,
    nonce,
  };
  const signature = await wallet.signTypedData(domain, types, value);

  const envelope = {
    x402Version: 2,
    resource: {
      ...(challenge.resource || {}),
      url: `${BASE_URL}/api/v1/${slug}`,
    },
    accepted: accept,
    payload: {
      signature,
      authorization: {
        from: wallet.address.toLowerCase(),
        to: ethers.getAddress(accept.payTo).toLowerCase(),
        value: amount,
        validAfter: validAfter.toString(),
        validBefore: validBefore.toString(),
        nonce,
      },
    },
  };
  if (challenge.extensions && typeof challenge.extensions === 'object') {
    envelope.extensions = challenge.extensions;
  }
  return Buffer.from(JSON.stringify(envelope)).toString('base64');
}

async function main() {
  const flags = parseFlags(process.argv.slice(2));
  const [{ json: manifest }, { json: discovery }] = await Promise.all([
    fetchJson(`${BASE_URL}/.well-known/x402.json`),
    fetchJson(DISCOVERY_URL),
  ]);

  const indexedUrls = new Set((discovery.items || []).map((it) => String(it.resource || '').replace(/\/+$/, '')));
  console.log(`PayAI Bazaar atual: ${indexedUrls.size} item(ns) para ${PAY_TO}`);

  const candidates = [];
  for (const entry of manifest.resources || []) {
    const cleanUrl = String(entry.resource || '').replace(/\/+$/, '');
    const m = cleanUrl.match(/\/api\/v1\/([a-z0-9-]+)$/i);
    if (!m) continue;
    const slug = m[1].toLowerCase();
    if (flags.only && !flags.only.has(slug)) continue;
    if (!flags.includePass && slug === 'pass') continue;
    if (!flags.includeExisting && indexedUrls.has(cleanUrl)) {
      console.log(`  [SKIP] ${slug.padEnd(16)} ja indexado (${cleanUrl})`);
      continue;
    }
    candidates.push(slug);
  }

  if (candidates.length === 0) {
    console.log('\nNenhum endpoint pendente para indexar.');
    return;
  }

  console.log(`\nProbing ${candidates.length} endpoint(s) em ${BASE_URL}...`);
  const ready = [];
  let totalRaw = 0n;

  for (const slug of candidates) {
    const url = buildTargetUrl(slug);
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const hdr = res.headers.get('payment-required');
    if (res.status !== 402 || !hdr) {
      console.log(`  [WARN] ${slug.padEnd(16)} retornou HTTP ${res.status} (esperado 402 + payment-required)`);
      continue;
    }
    const decoded = JSON.parse(Buffer.from(hdr, 'base64').toString('utf8'));
    const accept = (decoded.accepts || []).find((a) => a.network === 'eip155:8453' && a.scheme === 'exact');
    if (!accept) {
      console.log(`  [WARN] ${slug.padEnd(16)} sem oferta Base Mainnet exact`);
      continue;
    }
    const rawAmt = BigInt(accept.amount);
    totalRaw += rawAmt;
    ready.push({ slug, url, decoded, accept, rawAmt });
    console.log(`  [PLAN] ${slug.padEnd(16)} ${(Number(rawAmt) / 1e6).toFixed(4)} USDC -> ${url}`);
  }

  const totalUsdc = (Number(totalRaw) / 1e6).toFixed(4);
  console.log(`\nTotal estimado para ${ready.length} rota(s): ${totalUsdc} USDC (100% retorna para ${PAY_TO})`);

  if (!flags.execute) {
    console.log('\nModo --dry-run ativo. Para executar pagamentos reais na Base Mainnet:');
    console.log("  $env:GRID402_BUYER_PRIVATE_KEY='0xSUA_CHAVE'; node scripts/bazaar_seed.mjs --execute");
    return;
  }

  const pk = (process.env.GRID402_BUYER_PRIVATE_KEY || '').trim();
  if (!pk.startsWith('0x') || pk.length !== 66) {
    console.error('\nERRO: defina GRID402_BUYER_PRIVATE_KEY (0x + 64 hex) para executar com --execute.');
    process.exit(1);
  }

  const wallet = new ethers.Wallet(pk);
  const balanceRaw = await readUsdcBalanceRaw(wallet.address);
  console.log(`\nCarteira pagadora: ${wallet.address} | Saldo Base USDC: ${(Number(balanceRaw) / 1e6).toFixed(6)}`);
  if (balanceRaw < totalRaw) {
    console.error(`ERRO: saldo insuficiente (${(Number(balanceRaw) / 1e6).toFixed(6)} < ${totalUsdc} USDC).`);
    process.exit(1);
  }

  let okCount = 0;
  for (const item of ready) {
    const proof = await signTransferAuthorization(wallet, item.accept, item.slug, item.decoded);
    const res = await fetch(item.url, {
      headers: {
        Accept: 'application/json',
        'PAYMENT-SIGNATURE': proof,
      },
    });
    const text = await res.text();
    if (res.status === 200) {
      okCount++;
      console.log(`  [OK]   ${item.slug.padEnd(16)} HTTP 200 (${(Number(item.rawAmt) / 1e6).toFixed(4)} USDC)`);
    } else {
      console.log(`  [FAIL] ${item.slug.padEnd(16)} HTTP ${res.status}: ${text.slice(0, 180)}`);
    }
    await new Promise((r) => setTimeout(r, 800));
  }

  const { json: after } = await fetchJson(DISCOVERY_URL);
  console.log(`\nConcluido: ${okCount}/${ready.length} liquidados. PayAI Bazaar agora lista ${after.pagination?.total ?? (after.items || []).length} item(ns).`);
}

main().catch((err) => {
  console.error('Falha:', err?.message || err);
  process.exit(1);
});
