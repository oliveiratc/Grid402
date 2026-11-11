/**
 * Ação ElizaOS: Cotações Cripto Spot na Base e Mercado Global
 */

import { Grid402Client } from '../client.js';

export const getCryptoPriceAction = {
  name: 'GET_CRYPTO_PRICE',
  similes: [
    'CHECK_CRYPTO',
    'PRECO_BTC',
    'PRECO_ETH',
    'CRYPTO_TICKERS',
    'BASE_TOKEN_PRICE'
  ],
  description: 'Consulta cotações spot em tempo real e variação de BTC, ETH, SOL, USDC e BASE via oráculo Grid402 na Base Mainnet.',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, _message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });
      const res = await client.request('/api/v1/crypto');

      const tickers = res.tickers || res.data || {};
      const formatTicker = (sym: string) => {
        const t = tickers[sym];
        if (!t) return null;
        const p = t.price_usd !== undefined ? `$${t.price_usd}` : (t.price || 'N/A');
        const v = t.change_24h !== undefined ? ` (${t.change_24h > 0 ? '+' : ''}${t.change_24h}%)` : '';
        return `• ${sym}: ${p}${v}`;
      };

      const lines = ['BTC', 'ETH', 'SOL', 'BASE', 'USDC']
        .map(sym => formatTicker(sym))
        .filter(Boolean);

      const text = `⚡ Cotações Spot Cripto Grid402 (Base Network):\n` + lines.join('\n');

      if (callback) {
        callback({
          text,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha ao consultar cripto no Grid402: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'Quanto tá o Ethereum e Bitcoin agora?' } },
      { user: '{{agentName}}', content: { text: 'Verificando cotações no Grid402...', action: 'GET_CRYPTO_PRICE' } }
    ]
  ]
};

