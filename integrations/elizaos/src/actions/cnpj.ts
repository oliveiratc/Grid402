/**
 * Ação ElizaOS: Consulta Cadastral de CNPJ da Receita Federal
 */

import { Grid402Client } from '../client.js';

export const lookupCnpjAction = {
  name: 'LOOKUP_CNPJ',
  similes: [
    'CHECK_CNPJ',
    'CONSULTAR_CNPJ',
    'DADOS_EMPRESA_BR',
    'RECEITA_FEDERAL_CNPJ',
    'RAZAO_SOCIAL'
  ],
  description: 'Obtém dados cadastrais completos de CNPJs na Receita Federal do Brasil: razão social, situação cadastral, CNAE, data de abertura, município e sócios.',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });

      const text = message?.content?.text || '';
      // Extrai dígitos de CNPJ (14 dígitos)
      const digitsMatch = text.replace(/\D/g, '').match(/\d{14}/);
      const cnpj = digitsMatch ? digitsMatch[0] : '00000000000191'; // Fallback Banco do Brasil

      const res = await client.request('/api/v1/cnpj', { cnpj });

      const company = res.company || res;
      const outputText = `🏛️ Consulta CNPJ Receita Federal (Grid402):\n` +
        `• CNPJ: ${company.cnpj || cnpj}\n` +
        `• Razão Social: ${company.razao_social || company.nome || 'N/A'}\n` +
        `• Situação: ${company.situacao_cadastral || company.status || 'ATIVA'}\n` +
        `• Atividade Principal: ${company.cnae_fiscal_descricao || company.cnae || 'N/A'}\n` +
        `• Localização: ${company.municipio || ''}/${company.uf || ''}`;

      if (callback) {
        callback({
          text: outputText,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha na consulta do CNPJ no Grid402: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'Consulte os dados do CNPJ 00.000.000/0001-91' } },
      { user: '{{agentName}}', content: { text: 'Consultando cadastro na Receita Federal via Grid402...', action: 'LOOKUP_CNPJ' } }
    ]
  ]
};

