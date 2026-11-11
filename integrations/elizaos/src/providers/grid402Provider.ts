/**
 * Provider de Contexto do Grid402 para o Runtime do ElizaOS
 */

import { DEFAULT_GRID402_BASE_URL } from '../client.js';

export const grid402Provider = {
  get: async (runtime: any, _message: any, _state?: any) => {
    const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
    const hasPass = Boolean(passToken && passToken.startsWith('pass_g402_'));

    return `[Grid402 Network Intelligence Context]
Hub: Grid402 (M2M Open Data & Network Utilities Oracle)
Endpoint Base: ${DEFAULT_GRID402_BASE_URL}
Network: Base Mainnet (Chain ID 8453)
Settlement Protocol: HTTP x402 in USDC (0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913)
Session Pass Status: ${hasPass ? 'ACTIVE (Fast-track <15ms latency)' : 'NOT_CONFIGURED (Requires x402 on-chain or Sandbox mode)'}
Active Endpoints Available: Forex (USD/EUR/BRL), Crypto Tickers, Global Markets (ECB), OFAC SDN Sanctions, GLEIF LEI Worldwide, CNPJ Receita Federal, CEP, DNS, SSL, BGP/ASN, Workdays, Indicators (Selic/CDI/IPCA).`;
  }
};

