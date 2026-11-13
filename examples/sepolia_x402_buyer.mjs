#!/usr/bin/env node
/**
 * Grid402 x402 Buyer — Homologação em Base Sepolia
 *
 * Assina uma autorizacao EIP-3009 (transferWithAuthorization) contra o USDC de teste
 * na Base Sepolia e troca pelo recurso protegido do Grid402 via facilitador publico.
 *
 * Segurança:
 * - A chave privada chega APENAS via variavel GRID402_TEST_PRIVATE_KEY (nunca do repo/CLI arg).
 * - Use uma carteira DESCARTAVEL de testnet. Nunca use a carteira oraculo nem chave de producao.
 * - Nao executa nenhuma outra operacao (sem swap, sem approve, sem envio além da autorizacao assinada).
 *
 * Pré-requisitos:
 *   1. Sepolia ETH de faucet (ex.: https://www.coinbase.com/faucets/base-ethereum-sepolia-faucet)
 *   2. Sepolia USDC de faucet (https://faucet.circle.com → Base Sepolia, contrato 0x036CbD53842c5426634e7929541eC2318f3dCF7e)
 *   3. Site rodando em homologação (ex.: php -S 127.0.0.1:8090 -t public_html tests/router.php
 *      com GRID402_X402_NETWORK=base-sepolia GRID402_FACILITATOR_MODE=verify_only|settle
 *      GRID402_FACILITATOR_URL=https://x402.org/facilitator
 *      GRID402_FACILITATOR_VERIFY_PATH=/verify GRID402_FACILITATOR_SETTLE_PATH=/settle)
 *
 * Uso:
 *   GRID402_TEST_PRIVATE_KEY=0x... GRID402_TARGET_URL=http://127.0.0.1:8090/api/v1/crypto node examples/sepolia_x402_buyer.mjs
 */

import { ethers } from 'ethers';

const TARGET = process.env.GRID402_TARGET_URL || 'http://127.0.0.1:8090/api/v1/crypto';
const pk = process.env.GRID402_TEST_PRIVATE_KEY || '';

if (!pk.startsWith('0x') || pk.length !== 66) {
  console.error('ERRO: defina GRID402_TEST_PRIVATE_KEY (0x + 64 hex) com uma carteira de TESTE Sepolia.');
  console.error('      Nunca use a carteira oraculo nem uma chave de producao.');
  process.exit(1);
}

const wallet = new ethers.Wallet(pk);
console.log('Carteira de teste:', wallet.address);

// 1) Desafio 402
console.log('\n[1] GET', TARGET, '(sem pagamento)');
const res1 = await fetch(TARGET, { headers: { Accept: 'application/json' } });
const info = await res1.json().catch(() => ({}));
console.log('    -> HTTP', res1.status);

if (res1.status !== 402) {
  console.error('Esperado HTTP 402, recebida', res1.status, '\n', JSON.stringify(info, null, 2).slice(0, 800));
  process.exit(1);
}

const accept = info?.accepts?.[0];
if (!accept) {
  console.error('402 sem accepts no payload. Corpo parcial:\n', JSON.stringify(info).slice(0, 800));
  process.exit(1);
}

const { network, asset, amount, payTo } = accept;
console.log('    -> accept:', { scheme: accept.scheme, network, asset, amount, payTo });
if (network !== 'eip155:84532') {
  console.error('ERRO: o alvo nao esta em Base Sepolia (eip155:84532). Nao execute em producao.');
  process.exit(1);
}

// 2) Assina EIP-3009 transferWithAuthorization (USDC testnet)
const chainId = 84532;
const nonce = '0x' + Buffer.from(ethers.randomBytes(32)).toString('hex');
const validAfter = 0n;
const validBefore = BigInt(Math.floor(Date.now() / 1000) + 300);

const domain = {
  name: accept.extra?.name ?? 'USD Coin',
  version: accept.extra?.version ?? '2',
  chainId,
  verifyingContract: ethers.getAddress(asset),
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
  to: ethers.getAddress(payTo),
  value: BigInt(amount),
  validAfter,
  validBefore,
  nonce,
};
const signature = await wallet.signTypedData(domain, types, value);
console.log('\n[2] Assinatura EIP-3009 gerada para', wallet.address);

// 3) Reenvio com PAYMENT-SIGNATURE
const authorization = {
  from: wallet.address.toLowerCase(),
  to: ethers.getAddress(payTo).toLowerCase(),
  value: String(amount),
  validAfter: validAfter.toString(),
  validBefore: validBefore.toString(),
  nonce,
};

// Bazaar: o payload liquidado precisa carregar a extensão em echo da que o 402 anunciou,
// caso o facilitador a suporte e tenha /discovery/resources (docs.x402.org/extensions/bazaar.md).
const echoExtensions = info?.extensions && typeof info.extensions === 'object' ? info.extensions : undefined;
const payload = {
  x402Version: 2,
  accepted: accept,
  payload: { signature, authorization },
  ...(echoExtensions ? { extensions: echoExtensions } : {}),
};
const b64 = Buffer.from(JSON.stringify(payload)).toString('base64');

console.log('\n[3] Reenviando com PAYMENT-SIGNATURE (payload v2, facilitador x402.org testnet)');
const res2 = await fetch(TARGET, {
  headers: {
    Accept: 'application/json',
    'PAYMENT-SIGNATURE': b64,
    'X-Payment': b64,
  },
});
const text = await res2.text();
console.log('    -> HTTP', res2.status);

const paymentResponse = res2.headers.get('PAYMENT-RESPONSE') ?? res2.headers.get('payment-response');
if (paymentResponse) {
  try {
    const receipt = JSON.parse(Buffer.from(paymentResponse, 'base64').toString('utf-8'));
    console.log('\n[4] Recibo do facilitador (PAYMENT-RESPONSE):', JSON.stringify(receipt, null, 2));
  } catch {
    console.log('\n[4] PAYMENT-RESPONSE (base64, decode falhou):', paymentResponse);
  }
}

const extensionResponses = res2.headers.get('EXTENSION-RESPONSES') ?? res2.headers.get('extension-responses');
if (extensionResponses) {
  try {
    const ext = JSON.parse(Buffer.from(extensionResponses, 'base64').toString('utf-8'));
    console.log('\n[4b] EXTENSION-RESPONSES (resultado de catalogação Bazaar):', JSON.stringify(ext, null, 2));
  } catch {
    console.log('\n[4b] EXTENSION-RESPONSES (base64, decode falhou):', extensionResponses);
  }
} else {
  console.log('\n[4b] EXTENSION-RESPONSES não presente no cabeçalho (ausência não é falha; o facilitador pode não enviar o header).');
}

console.log('\n[5] Corpo (ate 1500 bytes):\n', text.slice(0, 1500));
process.exit(res2.status === 200 ? 0 : 1);
