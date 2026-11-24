/**
 * Grid402 MCP Server - Módulo de Liquidação Automática x402 Multi-Chain
 *
 * Suporta pagamentos automáticos em USDC (6 casas decimais) em:
 * - Base Mainnet (Chain ID 8453 / eip155:8453) — On-Chain ERC-20 e Gasless EIP-3009
 * - Polygon PoS (Chain ID 137 / eip155:137) — On-Chain ERC-20 e Gasless EIP-3009
 * - Arbitrum One (Chain ID 42161 / eip155:42161) — On-Chain ERC-20 e Gasless EIP-3009
 * - Optimism (Chain ID 10 / eip155:10) — On-Chain ERC-20
 * - Ethereum Mainnet (Chain ID 1 / eip155:1) — On-Chain ERC-20
 * - Solana Mainnet (solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp) — On-Chain SPL USDC e Gasless PayAI SVM
 */

import crypto from 'node:crypto';
import { ethers } from 'ethers';

export const BASE_RPC_URLS = [
  'https://base-rpc.publicnode.com',
  'https://1rpc.io/base',
  'https://mainnet.base.org',
];

export const SOLANA_RPC_URLS = [
  'https://solana-rpc.publicnode.com',
  'https://api.mainnet-beta.solana.com',
];

export const USDC_CONTRACT_BASE = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'.toLowerCase();
export const USDC_MINT_SOLANA = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';
export const GRID402_RECIPIENT_WALLET = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'.toLowerCase();
export const GRID402_SOLANA_RECIPIENT_WALLET = 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C';
export const PAYAI_SOLANA_FEE_PAYER = 'CjNFTjvBhbJJd2B5ePPMHRLx1ELZpa8dwQgGL727eKww';
export const SOLANA_SPL_TOKEN_PROGRAM_ID = 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA';
export const REQUIRED_RAW_UNITS = 5000n; // 0.0050 USDC (6 casas decimais)

export const SUPPORTED_X402_NETWORKS = {
  base: {
    slug: 'base',
    name: 'Base',
    kind: 'evm',
    chainId: 8453,
    caip2: 'eip155:8453',
    usdcContract: USDC_CONTRACT_BASE,
    recipient: GRID402_RECIPIENT_WALLET,
    rpcs: BASE_RPC_URLS,
    eip3009: { name: 'USD Coin', version: '2' },
  },
  polygon: {
    slug: 'polygon',
    name: 'Polygon',
    kind: 'evm',
    chainId: 137,
    caip2: 'eip155:137',
    usdcContract: '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
    recipient: GRID402_RECIPIENT_WALLET,
    rpcs: [
      'https://polygon-bor-rpc.publicnode.com',
      'https://1rpc.io/matic',
    ],
    eip3009: { name: 'USD Coin', version: '2' },
  },
  arbitrum: {
    slug: 'arbitrum',
    name: 'Arbitrum',
    kind: 'evm',
    chainId: 42161,
    caip2: 'eip155:42161',
    usdcContract: '0xaf88d065e77c8cc2239327c5edb3a432268e5831',
    recipient: GRID402_RECIPIENT_WALLET,
    rpcs: [
      'https://arbitrum-one-rpc.publicnode.com',
      'https://arb1.arbitrum.io/rpc',
      'https://1rpc.io/arb',
    ],
    eip3009: { name: 'USD Coin', version: '2' },
  },
  optimism: {
    slug: 'optimism',
    name: 'Optimism',
    kind: 'evm',
    chainId: 10,
    caip2: 'eip155:10',
    usdcContract: '0x0b2c639c533813f4aa9d7837caf62653d097ff85',
    recipient: GRID402_RECIPIENT_WALLET,
    rpcs: [
      'https://optimism-rpc.publicnode.com',
      'https://mainnet.optimism.io',
      'https://1rpc.io/op',
    ],
    eip3009: null,
  },
  ethereum: {
    slug: 'ethereum',
    name: 'Ethereum',
    kind: 'evm',
    chainId: 1,
    caip2: 'eip155:1',
    usdcContract: '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
    recipient: GRID402_RECIPIENT_WALLET,
    rpcs: [
      'https://ethereum-rpc.publicnode.com',
      'https://1rpc.io/eth',
    ],
    eip3009: null,
  },
  solana: {
    slug: 'solana',
    name: 'Solana',
    kind: 'svm',
    chainId: null,
    caip2: 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
    usdcContract: USDC_MINT_SOLANA,
    recipient: GRID402_SOLANA_RECIPIENT_WALLET,
    feePayer: PAYAI_SOLANA_FEE_PAYER,
    rpcs: SOLANA_RPC_URLS,
  },
};

const ERC20_ABI = [
  'function transfer(address to, uint256 amount) returns (bool)',
  'function balanceOf(address account) view returns (uint256)',
];

const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
const BASE58_MAP = new Map([...BASE58_ALPHABET].map((ch, idx) => [ch, BigInt(idx)]));
const ED25519_PKCS8_PREFIX = Buffer.from('302e020100300506032b657004220420', 'hex');

/**
 * Codifica um Buffer/Uint8Array em Base58 (padrão Bitcoin/Solana).
 */
export function encodeBase58(bytes) {
  const buf = Buffer.from(bytes);
  if (buf.length === 0) return '';
  let num = BigInt('0x' + buf.toString('hex'));
  let out = '';
  while (num > 0n) {
    const rem = Number(num % 58n);
    out = BASE58_ALPHABET[rem] + out;
    num /= 58n;
  }
  for (let i = 0; i < buf.length && buf[i] === 0; i++) {
    out = '1' + out;
  }
  return out;
}

/**
 * Decodifica uma string Base58 para Buffer.
 */
export function decodeBase58(str) {
  if (typeof str !== 'string' || str.trim() === '') {
    throw new Error('String Base58 vazia ou inválida.');
  }
  const clean = str.trim();
  let num = 0n;
  for (const ch of clean) {
    const val = BASE58_MAP.get(ch);
    if (val === undefined) {
      throw new Error(`Caractere inválido em Base58: "${ch}"`);
    }
    num = num * 58n + val;
  }
  let hex = num === 0n ? '' : num.toString(16);
  if (hex.length % 2 !== 0) hex = '0' + hex;
  const rawBytes = hex ? Buffer.from(hex, 'hex') : Buffer.alloc(0);
  let leadingZeros = 0;
  for (let i = 0; i < clean.length && clean[i] === '1'; i++) {
    leadingZeros++;
  }
  return Buffer.concat([Buffer.alloc(leadingZeros, 0), rawBytes]);
}

/**
 * Normaliza o nome/CAIP-2/ChainID de uma rede para o slug canônico suportado.
 */
export function normalizePaymentNetwork(networkRaw, privateKeyHint = null) {
  const val = String(networkRaw || '').trim().toLowerCase();
  if (val) {
    if (['base', 'base-mainnet', 'eip155:8453', '8453'].includes(val)) return 'base';
    if (['polygon', 'matic', 'pol', 'polygon-pos', 'eip155:137', '137'].includes(val)) return 'polygon';
    if (['arbitrum', 'arb', 'arbitrum-one', 'arb1', 'eip155:42161', '42161'].includes(val)) return 'arbitrum';
    if (['optimism', 'op', 'op-mainnet', 'eip155:10', '10'].includes(val)) return 'optimism';
    if (['ethereum', 'eth', 'mainnet', 'eth-mainnet', 'eip155:1', '1'].includes(val)) return 'ethereum';
    if (['solana', 'sol', 'solana-mainnet', 'solana:5eykt4usfv8p8njdtrepy1vzqkqzkvdp'].includes(val)) return 'solana';
    throw new Error(`Rede de pagamento não suportada: "${networkRaw}". Redes suportadas: base, polygon, arbitrum, optimism, ethereum, solana.`);
  }

  // Auto-detecção pela chave privada quando a rede não é explícita
  if (typeof privateKeyHint === 'string') {
    const trimmed = privateKeyHint.trim();
    if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
      return 'solana';
    }
    if (!trimmed.startsWith('0x') && /^[1-9A-HJ-NP-Za-km-z]{43,90}$/.test(trimmed)) {
      return 'solana';
    }
  }
  return 'base';
}

/**
 * Faz o parse de uma chave privada Solana Ed25519 (Base58 64-byte keypair, Base58 32-byte seed,
 * JSON array de 32/64 bytes ou seed hexadecimal de 64 chars) usando node:crypto nativo.
 */
export function parseSolanaKeypair(rawKey) {
  if (!rawKey || typeof rawKey !== 'string') {
    throw new Error('Chave privada Solana inválida. Forneça keypair Base58 (64 bytes), seed Base58 (32 bytes) ou JSON array.');
  }
  const trimmed = rawKey.trim();
  let keyBytes;

  if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
    let arr;
    try {
      arr = JSON.parse(trimmed);
    } catch {
      throw new Error('Chave privada Solana inválida: JSON array malformado.');
    }
    if (!Array.isArray(arr) || (arr.length !== 64 && arr.length !== 32)) {
      throw new Error('Chave privada Solana inválida: o JSON array deve conter 32 ou 64 bytes.');
    }
    keyBytes = Buffer.from(arr);
  } else if (/^(?:0x)?[0-9a-fA-F]{64}$/.test(trimmed)) {
    const cleanHex = trimmed.startsWith('0x') ? trimmed.slice(2) : trimmed;
    keyBytes = Buffer.from(cleanHex, 'hex');
  } else if (/^[1-9A-HJ-NP-Za-km-z]{43,90}$/.test(trimmed)) {
    keyBytes = decodeBase58(trimmed);
  } else {
    throw new Error('Chave privada inválida. Para EVM use 0x + 64 hex; para Solana use Base58 (32/64 bytes) ou JSON array.');
  }

  if (keyBytes.length !== 32 && keyBytes.length !== 64) {
    throw new Error(`Chave privada Solana com tamanho inválido (${keyBytes.length} bytes; esperado 32 ou 64 bytes).`);
  }

  const seed32 = keyBytes.subarray(0, 32);
  const privateKeyObj = crypto.createPrivateKey({
    key: Buffer.concat([ED25519_PKCS8_PREFIX, seed32]),
    format: 'der',
    type: 'pkcs8',
  });
  const spkiDer = crypto.createPublicKey(privateKeyObj).export({ format: 'der', type: 'spki' });
  const publicKeyBytes = Buffer.from(spkiDer.subarray(spkiDer.length - 32));

  if (keyBytes.length === 64) {
    const embeddedPub = keyBytes.subarray(32, 64);
    if (!embeddedPub.equals(publicKeyBytes)) {
      throw new Error('Chave privada Solana inválida: a chave pública embutida nos últimos 32 bytes não corresponde à seed Ed25519.');
    }
  }

  return {
    seed: seed32,
    publicKeyBytes,
    publicKeyBase58: encodeBase58(publicKeyBytes),
    privateKeyObject: privateKeyObj,
  };
}

/**
 * Codifica um inteiro no formato compact-u16 de transações Solana.
 */
function encodeCompactU16(value) {
  let rem = value;
  const bytes = [];
  while (true) {
    let elem = rem & 0x7f;
    rem >>= 7;
    if (rem === 0) {
      bytes.push(elem);
      break;
    }
    elem |= 0x80;
    bytes.push(elem);
  }
  return Buffer.from(bytes);
}

/**
 * Constrói e assina uma transação Solana Legacy de transferência SPL USDC (`TransferChecked`, opcode 12).
 *
 * @param {object} params
 * @param {string} params.privateKey Chave privada Solana (Base58 ou JSON array)
 * @param {string} params.sourceTokenAccount Conta SPL USDC de origem (Base58)
 * @param {string} params.destinationTokenAccount Conta SPL USDC de destino (Base58)
 * @param {string} params.recentBlockhash Blockhash recente da Solana Mainnet (Base58)
 * @param {bigint|number|string} [params.rawUnits] Quantidade bruta em 6 decimais (padrão 5000 = 0.0050 USDC)
 * @param {string} [params.mint] Mint SPL USDC (padrão EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v)
 * @param {string|null} [params.feePayer] Se informado e diferente da carteira pagadora, gera transação parcialmente assinada para o facilitador PayAI
 */
export function buildSolanaSplTransferTransaction({
  privateKey,
  sourceTokenAccount,
  destinationTokenAccount,
  recentBlockhash,
  rawUnits = REQUIRED_RAW_UNITS,
  mint = USDC_MINT_SOLANA,
  feePayer = null,
}) {
  const keypair = parseSolanaKeypair(privateKey);
  const ownerBytes = keypair.publicKeyBytes;
  const sourceBytes = decodeBase58(sourceTokenAccount);
  const destBytes = decodeBase58(destinationTokenAccount);
  const mintBytes = decodeBase58(mint);
  const tokenProgBytes = decodeBase58(SOLANA_SPL_TOKEN_PROGRAM_ID);
  const blockhashBytes = decodeBase58(recentBlockhash);

  for (const [label, buf] of [
    ['sourceTokenAccount', sourceBytes],
    ['destinationTokenAccount', destBytes],
    ['mint', mintBytes],
    ['recentBlockhash', blockhashBytes],
  ]) {
    if (buf.length !== 32) {
      throw new Error(`Parâmetro Solana "${label}" inválido (${buf.length} bytes; esperado 32 bytes).`);
    }
  }

  // Instruction data: TransferChecked (opcode 12) + uint64LE(amount) + uint8(decimals = 6)
  const ixData = Buffer.alloc(10);
  ixData.writeUInt8(12, 0);
  ixData.writeBigUInt64LE(BigInt(rawUnits), 1);
  ixData.writeUInt8(6, 9);

  const hasSeparateFeePayer = Boolean(
    feePayer && feePayer.trim() !== '' && feePayer.trim() !== keypair.publicKeyBase58
  );

  let header;
  let accountKeys;
  let programIdIndex;
  let accountIndices;

  if (hasSeparateFeePayer) {
    const feePayerBytes = decodeBase58(feePayer);
    if (feePayerBytes.length !== 32) {
      throw new Error('feePayer Solana inválido.');
    }
    // 2 signers (0: feePayer writable, 1: owner readonly), 2 unsigned writable (2: source, 3: dest), 2 unsigned readonly (4: mint, 5: tokenProgram)
    header = Buffer.from([2, 1, 2]);
    accountKeys = [feePayerBytes, ownerBytes, sourceBytes, destBytes, mintBytes, tokenProgBytes];
    programIdIndex = 5;
    accountIndices = Buffer.from([2, 4, 3, 1]);
  } else {
    // 1 signer (0: owner writable), 2 unsigned writable (1: source, 2: dest), 2 unsigned readonly (3: mint, 4: tokenProgram)
    header = Buffer.from([1, 0, 2]);
    accountKeys = [ownerBytes, sourceBytes, destBytes, mintBytes, tokenProgBytes];
    programIdIndex = 4;
    accountIndices = Buffer.from([1, 3, 2, 0]);
  }

  const messageBytes = Buffer.concat([
    header,
    encodeCompactU16(accountKeys.length),
    ...accountKeys,
    blockhashBytes,
    encodeCompactU16(1), // 1 instrução
    Buffer.from([programIdIndex]),
    encodeCompactU16(accountIndices.length),
    accountIndices,
    encodeCompactU16(ixData.length),
    ixData,
  ]);

  const ownerSig = crypto.sign(null, messageBytes, keypair.privateKeyObject);
  const signatures = hasSeparateFeePayer
    ? [Buffer.alloc(64, 0), ownerSig]
    : [ownerSig];

  const serializedTx = Buffer.concat([
    encodeCompactU16(signatures.length),
    ...signatures,
    messageBytes,
  ]);

  return {
    transactionBase64: serializedTx.toString('base64'),
    signatureBase58: encodeBase58(ownerSig),
    from: keypair.publicKeyBase58,
    feePayer: hasSeparateFeePayer ? feePayer.trim() : keypair.publicKeyBase58,
  };
}

/**
 * Constrói o payload gasless x402 v2 para Solana SVM (`PAYMENT-SIGNATURE` / `X-Payment` em base64).
 */
export function buildSolanaX402PaymentPayload({
  privateKey,
  sourceTokenAccount,
  destinationTokenAccount,
  recentBlockhash,
  rawUnits = REQUIRED_RAW_UNITS,
  recipient = GRID402_SOLANA_RECIPIENT_WALLET,
  mint = USDC_MINT_SOLANA,
  feePayer = PAYAI_SOLANA_FEE_PAYER,
}) {
  const built = buildSolanaSplTransferTransaction({
    privateKey,
    sourceTokenAccount,
    destinationTokenAccount,
    recentBlockhash,
    rawUnits,
    mint,
    feePayer,
  });

  const payloadObj = {
    x402Version: 2,
    scheme: 'exact',
    network: SUPPORTED_X402_NETWORKS.solana.caip2,
    accepted: {
      scheme: 'exact',
      network: SUPPORTED_X402_NETWORKS.solana.caip2,
      amount: String(BigInt(rawUnits)),
      asset: mint,
      payTo: recipient,
      maxTimeoutSeconds: 300,
      extra: { feePayer },
    },
    payload: {
      transaction: built.transactionBase64,
    },
  };

  return {
    payloadBase64: Buffer.from(JSON.stringify(payloadObj), 'utf8').toString('base64'),
    payload: payloadObj,
    from: built.from,
    to: recipient,
    signatureBase58: built.signatureBase58,
  };
}

/**
 * Executa uma chamada JSON-RPC na Solana Mainnet com failover entre nós públicos.
 */
export async function callSolanaRpc(method, params = [], preferredRpc = null) {
  const rpcs = preferredRpc ? [preferredRpc, ...SOLANA_RPC_URLS] : SOLANA_RPC_URLS;
  let lastError = null;

  for (const rpc of rpcs) {
    try {
      const res = await fetch(rpc, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }),
      });
      if (!res.ok) {
        lastError = new Error(`HTTP ${res.status} de ${rpc}`);
        continue;
      }
      const data = await res.json();
      if (data.error) {
        throw new Error(data.error.message || JSON.stringify(data.error));
      }
      return data.result;
    } catch (err) {
      lastError = err;
    }
  }
  throw new Error(`Falha ao comunicar com RPC da Solana Mainnet (${method}): ${lastError?.message || 'indisponível'}`);
}

/**
 * Aguarda confirmação de assinatura Solana via polling de `getSignatureStatuses`.
 */
export async function waitForSolanaConfirmation(signature, preferredRpc = null, maxAttempts = 20, intervalMs = 1500) {
  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    const statusRes = await callSolanaRpc(
      'getSignatureStatuses',
      [[signature], { searchTransactionHistory: true }],
      preferredRpc
    );
    const info = statusRes?.value?.[0];
    if (info) {
      if (info.err) {
        throw new Error(`Transação Solana falhou on-chain (${signature}): ${JSON.stringify(info.err)}`);
      }
      if (info.confirmationStatus === 'confirmed' || info.confirmationStatus === 'finalized') {
        return info;
      }
    }
    if (attempt < maxAttempts - 1) {
      await new Promise((resolve) => setTimeout(resolve, intervalMs));
    }
  }
  throw new Error(`Timeout aguardando confirmação da transação Solana ${signature} após ${Math.round((maxAttempts * intervalMs) / 1000)}s.`);
}

/**
 * Resolve o item `accepts[]` compatível com a rede desejada no desafio HTTP 402.
 */
function findChallengeAcceptForNetwork(challenge, networkSlug) {
  if (!Array.isArray(challenge?.accepts)) return null;
  for (const item of challenge.accepts) {
    if (!item || typeof item !== 'object') continue;
    try {
      if (normalizePaymentNetwork(item.network) === networkSlug) {
        return item;
      }
    } catch {
      // ignora rede desconhecida no challenge
    }
  }
  return null;
}

/**
 * Liquida um desafio x402 na Solana Mainnet (SPL USDC).
 * Suporta modo gasless x402 v2 (ativo por padrão quando o challenge inclui Solana `exact` e `GRID402_X402_GASLESS !== 'false'`)
 * ou envio direto on-chain (`solana:<signature>`) com espera de confirmação.
 */
export async function settleSolanaUsdcPayment(challenge, privateKey, preferredRpc = null, options = {}) {
  const keypair = parseSolanaKeypair(privateKey);
  const solAccept = findChallengeAcceptForNetwork(challenge, 'solana');
  const rawUnits = BigInt(solAccept?.amount || solAccept?.maxAmountRequired || challenge?.price_raw_units || REQUIRED_RAW_UNITS);
  const formattedUsdc = (Number(rawUnits) / 1e6).toFixed(4);
  const recipientOwner = solAccept?.payTo || challenge?.solana_recipient_wallet || GRID402_SOLANA_RECIPIENT_WALLET;
  const mint = solAccept?.asset || USDC_MINT_SOLANA;
  const dynamicFeePayer = solAccept?.extra?.feePayer || challenge?.solana_fee_payer || PAYAI_SOLANA_FEE_PAYER;

  // 1. Consulta conta SPL USDC do pagador e valida saldo
  const senderAccounts = await callSolanaRpc(
    'getTokenAccountsByOwner',
    [keypair.publicKeyBase58, { mint }, { encoding: 'jsonParsed' }],
    preferredRpc
  );
  const senderEntry = senderAccounts?.value?.[0];
  if (!senderEntry?.pubkey) {
    throw new Error(`Conta SPL USDC não encontrada na carteira Solana ${keypair.publicKeyBase58}.`);
  }
  const senderBalanceRaw = BigInt(senderEntry?.account?.data?.parsed?.info?.tokenAmount?.amount || '0');
  if (senderBalanceRaw < rawUnits) {
    const currentFormatted = (Number(senderBalanceRaw) / 1e6).toFixed(6);
    throw new Error(`Saldo insuficiente de SPL USDC na carteira Solana ${keypair.publicKeyBase58}. Requerido: ${formattedUsdc} USDC, Atual: ${currentFormatted} USDC.`);
  }

  // 2. Consulta conta SPL USDC oficial do recebedor Grid402 (tratamento gracioso caso ATA ainda não exista)
  let recipientTokenAccount = null;
  try {
    const recipientAccounts = await callSolanaRpc(
      'getTokenAccountsByOwner',
      [recipientOwner, { mint }, { encoding: 'jsonParsed' }],
      preferredRpc
    );
    recipientTokenAccount = recipientAccounts?.value?.[0]?.pubkey || null;
  } catch (ataErr) {
    throw new Error(
      `Falha ao consultar a conta SPL USDC (ATA) de destino ${recipientOwner} na Solana: ${ataErr.message}. ` +
      'Como alternativa imediata, use Base/Polygon/Arbitrum (GRID402_PAYMENT_NETWORK=base) ou um Session Pass em https://grid402.online/pass.'
    );
  }
  if (!recipientTokenAccount) {
    throw new Error(
      `A carteira recebedora Solana (${recipientOwner}) ainda não possui ATA SPL USDC inicializada on-chain. ` +
      'Use GRID402_PAYMENT_NETWORK=base (ou polygon/arbitrum) com USDC EVM, ou adquira um Session Pass em https://grid402.online/pass.'
    );
  }

  // 3. Obtém blockhash recente
  const latestBlockhashRes = await callSolanaRpc(
    'getLatestBlockhash',
    [{ commitment: 'confirmed' }],
    preferredRpc
  );
  const recentBlockhash = latestBlockhashRes?.value?.blockhash;
  if (!recentBlockhash) {
    throw new Error('Não foi possível obter recentBlockhash da Solana Mainnet.');
  }

  // 4. Modo Gasless via Facilitador PayAI x402 v2 (feePayer dinâmico de challenge.accepts[].extra.feePayer)
  const envGaslessDisabled = process.env.GRID402_X402_GASLESS === 'false' || process.env.GRID402_X402_GASLESS === '0';
  const challengeSupportsSolGasless = !Array.isArray(challenge?.accepts) || challenge.accepts.length === 0
    ? true
    : Boolean(solAccept && solAccept.scheme === 'exact');
  const useGasless = options.gasless !== undefined
    ? Boolean(options.gasless)
    : (!envGaslessDisabled && challengeSupportsSolGasless);

  if (useGasless) {
    const x402Built = buildSolanaX402PaymentPayload({
      privateKey,
      sourceTokenAccount: senderEntry.pubkey,
      destinationTokenAccount: recipientTokenAccount,
      recentBlockhash,
      rawUnits,
      recipient: recipientOwner,
      mint,
      feePayer: dynamicFeePayer,
    });
    return {
      txHash: x402Built.signatureBase58,
      paymentHeader: x402Built.payloadBase64,
      blockNumber: latestBlockhashRes?.context?.slot || null,
      network: 'solana',
      networkName: 'Solana',
      mode: 'x402_gasless',
      feePayer: dynamicFeePayer,
      from: keypair.publicKeyBase58,
      to: recipientOwner,
      amountUsdc: formattedUsdc,
    };
  }

  // 5. Modo On-Chain Direto (envia transação SPL USDC, aguarda confirmação e retorna solana:<signature>)
  const signedTx = buildSolanaSplTransferTransaction({
    privateKey,
    sourceTokenAccount: senderEntry.pubkey,
    destinationTokenAccount: recipientTokenAccount,
    recentBlockhash,
    rawUnits,
    mint,
    feePayer: null,
  });

  const txSignature = await callSolanaRpc(
    'sendTransaction',
    [signedTx.transactionBase64, { encoding: 'base64', preflightCommitment: 'confirmed' }],
    preferredRpc
  );

  const confirmedStatus = await waitForSolanaConfirmation(txSignature, preferredRpc);

  return {
    txHash: txSignature,
    paymentHeader: `solana:${txSignature}`,
    blockNumber: confirmedStatus?.slot || latestBlockhashRes?.context?.slot || null,
    network: 'solana',
    networkName: 'Solana',
    mode: 'onchain_transfer',
    from: keypair.publicKeyBase58,
    to: recipientOwner,
    amountUsdc: formattedUsdc,
  };
}

/**
 * Cria um provider EVM com fallback resiliente para qualquer rede EVM suportada (Base, Polygon, Arbitrum, Optimism, Ethereum).
 */
export async function getEvmProvider(networkSlug = 'base', preferredRpc = null) {
  const net = SUPPORTED_X402_NETWORKS[networkSlug] || SUPPORTED_X402_NETWORKS.base;
  const rpcs = preferredRpc ? [preferredRpc, ...net.rpcs] : net.rpcs;
  for (const rpc of rpcs) {
    try {
      const provider = new ethers.JsonRpcProvider(rpc, net.chainId, { staticNetwork: true });
      await provider.getBlockNumber();
      return provider;
    } catch {
      // Tenta próximo RPC
    }
  }
  throw new Error(`Não foi possível conectar a nenhum nó RPC da rede ${net.name} (Chain ID ${net.chainId}).`);
}

/**
 * Cria um provider com fallback resiliente para a Base Mainnet (retrocompatível).
 */
export async function getBaseProvider(preferredRpc = null) {
  return getEvmProvider('base', preferredRpc);
}

/**
 * Gera um payload gasless EIP-3009 (`TransferWithAuthorization`) assinado offline para Base, Polygon ou Arbitrum.
 */
export async function buildEip3009PaymentPayload(challenge, privateKey, networkSlug = 'base') {
  const slug = normalizePaymentNetwork(networkSlug, privateKey);
  const net = SUPPORTED_X402_NETWORKS[slug];
  if (!net || net.kind !== 'evm' || !net.eip3009) {
    throw new Error(`A rede "${networkSlug}" não suporta assinatura gasless EIP-3009. Use base, polygon ou arbitrum.`);
  }
  if (!privateKey || typeof privateKey !== 'string' || !/^0x[0-9a-fA-F]{64}$/.test(privateKey.trim())) {
    throw new Error('Chave privada EVM inválida. Deve iniciar com 0x e conter 64 caracteres hexadecimais.');
  }

  const wallet = new ethers.Wallet(privateKey.trim());
  const netAccept = findChallengeAcceptForNetwork(challenge, slug);
  const rawUnits = BigInt(netAccept?.amount || netAccept?.maxAmountRequired || challenge?.price_raw_units || REQUIRED_RAW_UNITS);
  const recipient = ethers.getAddress(netAccept?.payTo || challenge?.recipient_wallet || net.recipient);
  const verifyingContract = ethers.getAddress(netAccept?.asset || net.usdcContract);
  const eip3009Name = netAccept?.extra?.name || net.eip3009.name;
  const eip3009Version = netAccept?.extra?.version || net.eip3009.version;
  const nowSec = Math.floor(Date.now() / 1000);
  const validAfter = 0;
  const validBefore = nowSec + 300;
  const nonce = '0x' + crypto.randomBytes(32).toString('hex');

  const domain = {
    name: eip3009Name,
    version: eip3009Version,
    chainId: net.chainId,
    verifyingContract,
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

  const authorization = {
    from: wallet.address,
    to: recipient,
    value: rawUnits.toString(),
    validAfter: String(validAfter),
    validBefore: String(validBefore),
    nonce,
  };

  const signature = await wallet.signTypedData(domain, types, authorization);

  const payloadObj = {
    x402Version: 2,
    scheme: 'exact',
    network: net.caip2,
    accepted: {
      scheme: 'exact',
      network: net.caip2,
      amount: rawUnits.toString(),
      asset: verifyingContract.toLowerCase(),
      payTo: recipient.toLowerCase(),
      maxTimeoutSeconds: 300,
      extra: { name: eip3009Name, version: eip3009Version },
    },
    payload: {
      signature,
      authorization: {
        from: wallet.address.toLowerCase(),
        to: recipient.toLowerCase(),
        value: rawUnits.toString(),
        validAfter: String(validAfter),
        validBefore: String(validBefore),
        nonce,
      },
    },
  };

  return {
    payloadBase64: Buffer.from(JSON.stringify(payloadObj), 'utf8').toString('base64'),
    payload: payloadObj,
    signature,
    nonce,
    from: wallet.address,
    to: recipient.toLowerCase(),
    network: net.slug,
    networkName: net.name,
    amountUsdc: (Number(rawUnits) / 1e6).toFixed(4),
  };
}

/**
 * Executa a liquidação automática do desafio x402 em USDC Multi-Chain:
 * - Solana Mainnet (`solana`): SPL USDC on-chain (`solana:<signature>`) ou gasless PayAI SVM (`x402Version: 2`)
 * - Base, Polygon, Arbitrum, Optimism e Ethereum: transferência ERC-20 on-chain ou gasless EIP-3009
 *
 * @param {object} challenge Desafio retornado pelo Grid402 (HTTP 402)
 * @param {string} privateKey Chave privada EVM (0x + 64 hex) ou Solana (Base58 / JSON array)
 * @param {string|null} [preferredRpc] URL opcional de RPC customizado
 * @param {object} [options] Opções adicionais ({ network: 'base'|'polygon'|'arbitrum'|'optimism'|'ethereum'|'solana', gasless: boolean })
 * @returns {Promise<{ txHash: string, paymentHeader: string, blockNumber: number|null, network: string, networkName: string, from: string, to: string, amountUsdc: string }>}
 */
export async function settleX402Payment(challenge, privateKey, preferredRpc = null, options = {}) {
  if (!privateKey || typeof privateKey !== 'string') {
    throw new Error('Chave privada inválida. Deve iniciar com 0x (64 hex para EVM) ou ser uma chave Solana Base58/JSON válida.');
  }

  const requestedNetwork = options.network || process.env.GRID402_PAYMENT_NETWORK || null;
  const networkSlug = normalizePaymentNetwork(requestedNetwork, privateKey);
  const net = SUPPORTED_X402_NETWORKS[networkSlug];

  if (net.kind === 'svm') {
    return settleSolanaUsdcPayment(challenge, privateKey, preferredRpc, options);
  }

  const trimmedKey = privateKey.trim();
  if (!trimmedKey.startsWith('0x') || !/^0x[0-9a-fA-F]{64}$/.test(trimmedKey)) {
    throw new Error('Chave privada inválida. Deve iniciar com 0x e conter 64 caracteres hexadecimais.');
  }

  const rawUnits = BigInt(challenge?.price_raw_units || REQUIRED_RAW_UNITS);
  const formattedUsdc = (Number(rawUnits) / 1e6).toFixed(4);
  const envGaslessDisabled = process.env.GRID402_X402_GASLESS === 'false' || process.env.GRID402_X402_GASLESS === '0';
  const netAccept = findChallengeAcceptForNetwork(challenge, networkSlug);
  const challengeSupportsGasless = !Array.isArray(challenge?.accepts) || challenge.accepts.length === 0
    ? true
    : Boolean(netAccept && netAccept.scheme === 'exact');
  const useGasless = options.gasless !== undefined
    ? Boolean(options.gasless)
    : (!envGaslessDisabled && challengeSupportsGasless);

  if (useGasless && net.eip3009) {
    const eip3009 = await buildEip3009PaymentPayload(challenge, trimmedKey, networkSlug);
    return {
      txHash: eip3009.nonce,
      paymentHeader: eip3009.payloadBase64,
      blockNumber: null,
      network: net.slug,
      networkName: net.name,
      mode: 'x402_gasless',
      from: eip3009.from,
      to: eip3009.to,
      amountUsdc: formattedUsdc,
    };
  }

  const recipient = (challenge?.recipient_wallet || net.recipient).toLowerCase();
  const tokenContract = (
    networkSlug === 'base'
      ? (challenge?.token_contract || net.usdcContract)
      : net.usdcContract
  ).toLowerCase();

  const provider = await getEvmProvider(networkSlug, preferredRpc);
  const wallet = new ethers.Wallet(trimmedKey, provider);
  const usdc = new ethers.Contract(tokenContract, ERC20_ABI, wallet);

  // 1. Verifica saldo de USDC na rede selecionada
  try {
    const balance = await usdc.balanceOf(wallet.address);
    if (balance < rawUnits) {
      throw new Error(`Saldo insuficiente de USDC na rede ${net.name} (${wallet.address}). Requerido: ${formattedUsdc} USDC, Atual: ${ethers.formatUnits(balance, 6)} USDC.`);
    }
  } catch (err) {
    if (err.message.includes('Saldo insuficiente')) {
      throw err;
    }
  }

  // 2. Envia transferência ERC-20 e aguarda confirmação
  const tx = await usdc.transfer(recipient, rawUnits);
  const receipt = await tx.wait(1);
  const paymentHeader = networkSlug === 'base' ? receipt.hash : `${networkSlug}:${receipt.hash}`;

  return {
    txHash: receipt.hash,
    paymentHeader,
    blockNumber: receipt.blockNumber,
    network: net.slug,
    networkName: net.name,
    mode: 'onchain_transfer',
    from: wallet.address,
    to: recipient,
    amountUsdc: formattedUsdc,
  };
}
