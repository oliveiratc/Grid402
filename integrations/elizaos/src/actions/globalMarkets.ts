/**
 * Ação ElizaOS: Mercados Globais, Moedas BCE e Calendário de Bolsas
 */

import { Grid402Client } from '../client.js';

export const getGlobalMarketsAction = {
  name: 'GET_GLOBAL_MARKETS',
  similes: [
    'GLOBAL_MARKETS',
    'ECB_RATES',
    'FERIADOS_BOLSAS',
    'WORLD_EXCHANGES',
    'MERCADOS_INTERNACIONAIS'
  ],
  description: 'Obtém dados de mercados globais, taxas de referência do Banco Central Europeu (30+ moedas) e calendário de feriados de bolsas internacionais (NYSE, NASDAQ, LSE).',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });
      
      const contentText = message?.content?.text || '';
      let market = 'US';
      if (/europa|londres|lse|ftse/i.test(contentText)) market = 'GB';
      else if (/brasil|b3/i.test(contentText)) market = 'BR';

      const res = await client.request('/api/v1/global-markets', { market });

      const text = `🌐 Mercados Globais & Calendário Grid402:\n` +
        `• Mercado: ${res.market || market}\n` +
        `• Status do pregão: ${res.is_trading_day ? 'Aberto / Dia Útil' : 'Fechado / Feriado'}\n` +
        `• Moedas BCE de referência: ${Object.keys(res.ecb_reference_rates?.rates || {}).slice(0, 5).join(', ')}...\n` +
        `• Fonte de conformidade: Diretiva Aberta da UE 2019/1024`;

      if (callback) {
        callback({
          text,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha ao consultar mercados globais: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'A bolsa de Nova York abre hoje?' } },
      { user: '{{agentName}}', content: { text: 'Consultando calendário da NYSE no Grid402...', action: 'GET_GLOBAL_MARKETS' } }
    ]
  ]
};

