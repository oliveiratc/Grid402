/**
 * Ação ElizaOS: Resolução de Identificador Global de Entidade (GLEIF LEI)
 */

import { Grid402Client } from '../client.js';

export const lookupLeiAction = {
  name: 'LOOKUP_LEI',
  similes: [
    'CHECK_LEI',
    'CONSULTAR_LEI',
    'GLEIF_LOOKUP',
    'LEGAL_ENTITY_IDENTIFIER',
    'EMPRESA_GLOBAL'
  ],
  description: 'Resolve códigos LEI (ISO 17442) da Global Legal Entity Identifier Foundation (GLEIF) para mais de 2,6 milhões de empresas em 200+ países.',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });

      const text = message?.content?.text || '';
      // Extrai código LEI de 20 caracteres alfanuméricos
      const leiMatch = text.match(/[A-Z0-9]{20}/);
      const params: Record<string, string> = {};
      if (leiMatch) {
        params.lei = leiMatch[0];
      } else {
        const queryMatch = text.match(/(?:lei|gleif|empresa)\s+(?:de\s+|para\s+)?([a-zA-Z0-9\s]{3,40})/i);
        if (queryMatch) params.q = queryMatch[1].trim();
        else params.lei = '5493006MHB84DD0ZWV18'; // Fallback Alphabet Inc.
      }

      const res = await client.request('/api/v1/lei', params);

      const entity = res.entity || {};
      const outputText = `🌐 Registro Global GLEIF LEI (Grid402):\n` +
        `• Razão Social: ${entity.legal_name || res.legal_name || 'N/A'}\n` +
        `• Código LEI: ${res.lei || params.lei}\n` +
        `• Jurisdição: ${entity.jurisdiction || res.jurisdiction || 'N/A'}\n` +
        `• Status: ${entity.entity_status || res.status || 'ACTIVE'}\n` +
        `• Licença de Dados: CC0 1.0 Universal (Domínio Público)`;

      if (callback) {
        callback({
          text: outputText,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha na consulta LEI no Grid402: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'Consulte o LEI 5493006MHB84DD0ZWV18 da Alphabet' } },
      { user: '{{agentName}}', content: { text: 'Buscando registro na base da GLEIF via Grid402...', action: 'LOOKUP_LEI' } }
    ]
  ]
};

