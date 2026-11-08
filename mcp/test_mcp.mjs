/**
 * Grid402 MCP Server - Bateria de Testes Automatizados
 */

import assert from 'node:assert';
import { MCP_TOOLS_DEFINITIONS, executeGrid402Request } from './tools.mjs';
import { settleX402Payment, GRID402_RECIPIENT_WALLET } from './x402_payer.mjs';

async function runTests() {
  console.log('=== Testando Servidor MCP Grid402 ===\n');

  // 1. Validação de Schemas das Ferramentas
  console.log('[1/4] Validando registro e schemas das 7 ferramentas MCP...');
  assert.strictEqual(MCP_TOOLS_DEFINITIONS.length, 7, 'Deve conter exatamente 7 ferramentas registradas');

  const toolNames = MCP_TOOLS_DEFINITIONS.map(t => t.name);
  const expectedTools = [
    'grid402_summary',
    'grid402_forex',
    'grid402_crypto',
    'grid402_dns',
    'grid402_ssl',
    'grid402_asn',
    'grid402_cep',
  ];

  for (const expected of expectedTools) {
    assert.ok(toolNames.includes(expected), `Ferramenta ${expected} deve estar presente`);
  }
  console.log('  ✓ Todas as 7 ferramentas MCP validadas com sucesso!');

  // 2. Teste de Consulta Gratuita (summary)
  console.log('\n[2/4] Testando consulta à ferramenta gratuita grid402_summary...');
  const summary = await executeGrid402Request('summary');
  assert.strictEqual(summary.protocol, 'x402', 'Protocolo deve ser x402');
  assert.strictEqual(summary.hub, 'Grid402', 'Hub deve ser Grid402');
  assert.ok(summary.services, 'Deve conter catálogo de serviços');
  console.log('  ✓ Resumo de saúde recebido com sucesso (Hub operacional)');

  // 3. Teste de Captura de Desafio x402 sem Chave Privada
  console.log('\n[3/4] Testando resposta de desafio 402 para consulta detalhada (grid402_dns)...');
  const dnsChallenge = await executeGrid402Request('dns', { domain: 'github.com' });
  assert.strictEqual(dnsChallenge.payment_required, true, 'Deve indicar payment_required: true');
  assert.ok(dnsChallenge.challenge, 'Deve conter objeto challenge');
  assert.strictEqual(
    (dnsChallenge.challenge.recipient_wallet || '').toLowerCase(),
    GRID402_RECIPIENT_WALLET,
    'Carteira recebedora deve coincidir com a oficial'
  );
  console.log('  ✓ Desafio x402 capturado com instruções e carteira oficial!');

  // 4. Teste de Validação Defensiva de Chave Privada
  console.log('\n[4/4] Testando validação defensiva de formato de chave privada...');
  try {
    await settleX402Payment({}, 'chave_invalida');
    assert.fail('Deveria ter lançado erro para chave inválida');
  } catch (err) {
    assert.ok(err.message.includes('Chave privada inválida'), 'Deve rejeitar chave mal formatada');
    console.log('  ✓ Rejeição de chave privada inválida validada com sucesso!');
  }

  console.log('\n=== Todos os testes do MCP Server Grid402 foram APROVADOS com 100% de sucesso! ===');
}

runTests().catch(err => {
  console.error('\n❌ Falha no teste do MCP Server:', err);
  process.exit(1);
});

