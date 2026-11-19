/**
 * Ação ElizaOS: Dossiê 360° KYB + Veredito Determinístico para IA (/api/v1/dossier)
 */

import { Grid402Client } from '../client.js';

export const getDossier360Action = {
  name: 'GET_DOSSIER_360',
  similes: [
    'KYB_DOSSIER_360',
    'DOSSIE_360_CNPJ',
    'VEREDITO_KYB_CNPJ',
    'CHECK_COMPANY_DOSSIER',
    'GRID402_GET_DOSSIER_360'
  ],
  description: 'Obtém o Dossiê 360° KYB de um CNPJ no Grid402 (Receita Federal + Simples/MEI + Sanções CGU CEIS/CNEP) com score de integridade (0-100) e veredito determinístico (APPROVED, FLAGGED, REJECTED).',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });

      const text = message?.content?.text || '';
      const digitsMatch = text.replace(/\D/g, '').match(/\d{14}/);
      const cnpj = digitsMatch ? digitsMatch[0] : '00000000000191';

      const res: any = await client.request('/api/v1/dossier', { cnpj });
      const verdict = res.verdict || {};
      const company = res.company || {};

      const outputText = `🛡️ Dossiê 360° KYB (Grid402):\n` +
        `• CNPJ: ${res.cnpj || cnpj} (${company.razao_social || 'N/A'})\n` +
        `• Veredito IA: ${verdict.decision || 'N/A'} (Score: ${res.integrity_score ?? 'N/A'}/100 | Risco: ${verdict.risk_level || 'N/A'})\n` +
        `• Resumo: ${verdict.summary_for_ai || 'N/A'}`;

      if (callback) {
        callback({
          text: outputText,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha na consulta do Dossiê 360° no Grid402: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'Gere o dossiê 360 KYB e veredito para o CNPJ 00.000.000/0001-91' } },
      { user: '{{agentName}}', content: { text: 'Gerando Dossiê 360° KYB com veredito determinístico via Grid402...', action: 'GET_DOSSIER_360' } }
    ]
  ]
};
