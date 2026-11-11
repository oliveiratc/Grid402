/**
 * Ação ElizaOS: Consulta de Câmbio Comercial e Moedas
 */

import { Grid402Client } from '../client.js';

export const getForexRateAction = {
  name: 'GET_FOREX_RATE',
  similes: [
    'QUERY_FOREX',
    'CHECK_EXCHANGE_RATE',
    'COTACAO_CAMBIO',
    'CONVERTER_MOEDA',
    'DOLAR_HOJE',
    'EURO_HOJE'
  ],
  description: 'Obtém cotações em tempo real de câmbio comercial para USD, EUR e GBP em relação ao BRL com dados oficiais e atestação criptográfica do Grid402.',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });
      const res = await client.request('/api/v1/forex');

      const text = `📊 Cotações de Câmbio Grid402:\n` +
        `• USD/BRL: R$ ${res.rates?.USD?.bid || res.rates?.USD || 'N/A'}\n` +
        `• EUR/BRL: R$ ${res.rates?.EUR?.bid || res.rates?.EUR || 'N/A'}\n` +
        `• GBP/BRL: R$ ${res.rates?.GBP?.bid || res.rates?.GBP || 'N/A'}\n` +
        `Horário UTC: ${res.timestamp || new Date().toISOString()}`;

      if (callback) {
        callback({
          text,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha ao consultar câmbio no Grid402: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'Qual a cotação do dólar e euro hoje?' } },
      { user: '{{agentName}}', content: { text: 'Consultando o oráculo Grid402 na Base...', action: 'GET_FOREX_RATE' } }
    ]
  ]
};

