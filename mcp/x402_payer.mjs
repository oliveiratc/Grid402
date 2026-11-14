/**
 * Grid402 MCP Server - Módulo de Liquidação x402 On-Chain (Base Mainnet)
 * 
 * Permite pagamentos automáticos de US$ 0.0005 USDC via transferência ERC-20
 * quando uma chave privada é fornecida no ambiente do agente.
 */

import { ethers } from 'ethers';

export const BASE_RPC_URLS = [
  'https://mainnet.base.org',
  'https://1rpc.io/base',
  'https://base.llamarpc.com',
];

export const USDC_CONTRACT_BASE = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'.toLowerCase();
export const GRID402_RECIPIENT_WALLET = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'.toLowerCase();
export const REQUIRED_RAW_UNITS = 500n; // 0.000500 USDC (6 casas decimais)

const ERC20_ABI = [
  'function transfer(address to, uint256 amount) returns (bool)',
  'function balanceOf(address account) view returns (uint256)',
];

/**
 * Cria um provider com fallback resiliente para a Base Mainnet
 */
export async function getBaseProvider(preferredRpc = null) {
  const rpcs = preferredRpc ? [preferredRpc, ...BASE_RPC_URLS] : BASE_RPC_URLS;
  for (const rpc of rpcs) {
    try {
      const provider = new ethers.JsonRpcProvider(rpc, 8453, { staticNetwork: true });
      await provider.getBlockNumber();
      return provider;
    } catch {
      // Tenta próximo RPC
    }
  }
  throw new Error('Não foi possível conectar a nenhum nó RPC da Base Mainnet.');
}

/**
 * Executa a transferência de 0.0005 USDC na Base para liquidar o desafio x402
 * 
 * @param {object} challenge Desafio retornado pelo Grid402 (HTTP 402)
 * @param {string} privateKey Chave privada hexadecimal da carteira pagadora
 * @param {string} [preferredRpc] URL opcional de RPC da Base
 * @returns {Promise<{ txHash: string, blockNumber: number }>}
 */
export async function settleX402Payment(challenge, privateKey, preferredRpc = null) {
  if (!privateKey || typeof privateKey !== 'string' || !privateKey.startsWith('0x')) {
    throw new Error('Chave privada inválida. Deve iniciar com 0x e conter 64 caracteres hexadecimais.');
  }

  const recipient = (challenge?.recipient_wallet || GRID402_RECIPIENT_WALLET).toLowerCase();
  const tokenContract = (challenge?.token_contract || USDC_CONTRACT_BASE).toLowerCase();
  const rawUnits = BigInt(challenge?.price_raw_units || REQUIRED_RAW_UNITS);

  const provider = await getBaseProvider(preferredRpc);
  const wallet = new ethers.Wallet(privateKey, provider);

  // Instancia contrato ERC-20 do USDC na Base
  const usdc = new ethers.Contract(tokenContract, ERC20_ABI, wallet);

  // 1. Verifica saldo de USDC
  try {
    const balance = await usdc.balanceOf(wallet.address);
    if (balance < rawUnits) {
      throw new Error(`Saldo insuficiente de USDC na carteira ${wallet.address}. Requerido: 0.0005 USDC, Atual: ${ethers.formatUnits(balance, 6)} USDC.`);
    }
  } catch (err) {
    if (err.message.includes('Saldo insuficiente')) {
      throw err;
    }
    // Caso leitura falhe por RPC restrito, tenta envio direto
  }

  // 2. Envia transferência ERC-20
  const tx = await usdc.transfer(recipient, rawUnits);
  const receipt = await tx.wait(1);

  return {
    txHash: receipt.hash,
    blockNumber: receipt.blockNumber,
    from: wallet.address,
    to: recipient,
    amountUsdc: '0.0005',
  };
}

