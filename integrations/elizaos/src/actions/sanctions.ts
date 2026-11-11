/**
 * Ação ElizaOS: Verificação de Sanções Internacionais OFAC SDN e AML Web3
 */

import { Grid402Client } from '../client.js';

export const checkSanctionsAction = {
  name: 'CHECK_SANCTIONS_OFAC',
  similes: [
    'CHECK_OFAC',
    'VERIFICAR_SANCOES',
    'AML_SCREENING',
    'WALLET_COMPLIANCE',
    'SANCTION_CHECK'
  ],
  description: 'Verifica se um endereço de carteira EVM ou nome de entidade consta em listas de sanções globais (OFAC SDN, ONU, SEEU) com decisão de conformidade e risco AML.',
  validate: async (_runtime: any, _message: any) => true,
  handler: async (runtime: any, message: any, _state?: any, _options?: any, callback?: any) => {
    try {
      const passToken = runtime?.getSetting?.('GRID402_PASS_TOKEN') || process.env.GRID402_PASS_TOKEN;
      const client = new Grid402Client({ passToken });

      const text = message?.content?.text || '';
      // Extrai endereço 0x... ou termo
      const ethMatch = text.match(/0x[a-fA-F0-9]{40}/);
      const params: Record<string, string> = {};
      if (ethMatch) {
        params.address = ethMatch[0];
      } else {
        const nameMatch = text.match(/(?:verificar|checar|sanções|ofac)\s+(?:de\s+|para\s+)?([a-zA-Z0-9\s]{3,40})/i);
        if (nameMatch) params.name = nameMatch[1].trim();
        else params.address = '0x8589427373D6D84E98730D7795D8f6f8731FDA16'; // Fallback Tornado Cash para teste
      }

      const res = await client.request('/api/v1/ofac', params);

      const statusEmoji = res.sanctioned ? '🚨 SANCIONADO / ALTO RISCO' : '✅ CONFORME / LIMPO';
      const outputText = `🛡️ Triagem de Sanções OFAC / AML (Grid402):\n` +
        `• Alvo: ${params.address || params.name}\n` +
        `• Status: ${statusEmoji}\n` +
        `• Nível de Risco: ${res.risk_level || 'LOW'}\n` +
        `• Decisão de Compliance: ${res.compliance_decision || 'APPROVE_TRANSACTION'}\n` +
        `• Listas Verificadas: ${Array.isArray(res.matched_lists) && res.matched_lists.length ? res.matched_lists.join(', ') : 'Nenhuma sanção encontrada'}`;

      if (callback) {
        callback({
          text: outputText,
          content: res,
        });
      }
      return true;
    } catch (err: any) {
      if (callback) {
        callback({ text: `Falha na verificação de sanções: ${err.message}` });
      }
      return false;
    }
  },
  examples: [
    [
      { user: '{{user1}}', content: { text: 'Essa carteira 0x8589427373D6D84E98730D7795D8f6f8731FDA16 está em listas de sanções da OFAC?' } },
      { user: '{{agentName}}', content: { text: 'Executando auditoria AML no oráculo Grid402...', action: 'CHECK_SANCTIONS_OFAC' } }
    ]
  ]
};

