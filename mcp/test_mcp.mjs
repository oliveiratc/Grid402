/**
 * Grid402 MCP Server - Bateria de Testes Automatizados
 */

import assert from 'node:assert';
import { MCP_TOOLS_DEFINITIONS, executeGrid402Request } from './tools.mjs';
import { settleX402Payment, GRID402_RECIPIENT_WALLET } from './x402_payer.mjs';

async function runTests() {
  console.log('=== Testando Servidor MCP Grid402 ===\n');

  // 1. Validação de Schemas das Ferramentas
  console.log('[1/6] Validando registro e schemas das 15 ferramentas MCP...');
  assert.strictEqual(MCP_TOOLS_DEFINITIONS.length, 15, 'Deve conter exatamente 15 ferramentas registradas');

  const toolNames = MCP_TOOLS_DEFINITIONS.map(t => t.name);
  const expectedTools = [
    'grid402_summary',
    'grid402_forex',
    'grid402_crypto',
    'grid402_dns',
    'grid402_ssl',
    'grid402_asn',
    'grid402_cep',
    'grid402_cnpj',
    'grid402_indicators',
    'grid402_holidays',
    'grid402_workdays',
    'grid402_sanctions',
    'grid402_simples',
    'grid402_compliance',
    'grid402_verify',
  ];

  for (const expected of expectedTools) {
    assert.ok(toolNames.includes(expected), `Ferramenta ${expected} deve estar presente`);
  }
  console.log('  ✓ Todas as 15 ferramentas MCP validadas com sucesso!');

  // 2. Teste de Consulta Gratuita (summary)
  console.log('\n[2/5] Testando consulta à ferramenta gratuita grid402_summary...');
  const summary = await executeGrid402Request('summary');
  assert.strictEqual(summary.protocol, 'x402', 'Protocolo deve ser x402');
  assert.strictEqual(summary.hub, 'Grid402', 'Hub deve ser Grid402');
  assert.ok(summary.services, 'Deve conter catálogo de serviços');
  console.log('  ✓ Resumo de saúde recebido com sucesso (Hub operacional)');

  // 3. Teste de Captura de Desafio x402 sem Chave Privada
  console.log('\n[3/5] Testando resposta de desafio 402 para consulta detalhada (grid402_dns e grid402_cnpj)...');
  const dnsChallenge = await executeGrid402Request('dns', { domain: 'github.com' });
  assert.strictEqual(dnsChallenge.payment_required, true, 'Deve indicar payment_required: true');
  assert.ok(dnsChallenge.challenge, 'Deve conter objeto challenge');
  assert.strictEqual(
    (dnsChallenge.challenge.recipient_wallet || '').toLowerCase(),
    GRID402_RECIPIENT_WALLET,
    'Carteira recebedora deve coincidir com a oficial'
  );

  const cnpjChallenge = await executeGrid402Request('cnpj', { cnpj: '00000000000191' });
  assert.strictEqual(cnpjChallenge.payment_required, true, 'CNPJ deve indicar payment_required: true');
  assert.ok(cnpjChallenge.challenge, 'Deve conter desafio estruturado');

  const indChallenge = await executeGrid402Request('indicators');
  assert.strictEqual(indChallenge.payment_required, true, 'Indicators deve indicar payment_required: true');

  const holChallenge = await executeGrid402Request('holidays', { year: 2026 });
  assert.strictEqual(holChallenge.payment_required, true, 'Holidays deve indicar payment_required: true');

  const workdaysChallenge = await executeGrid402Request('workdays', { start_date: '2026-11-06', days: 5 });
  assert.strictEqual(workdaysChallenge.payment_required, true, 'Workdays deve indicar payment_required: true');
  assert.ok(workdaysChallenge.challenge, 'Workdays deve conter desafio');
  console.log('  ✓ Desafio x402 capturado com instruções e carteira oficial para DNS, CNPJ, Indicadores, Feriados e Workdays!');

  // 4. Teste de Validação Defensiva de Chave Privada
  console.log('\n[4/5] Testando validação defensiva de formato de chave privada...');
  try {
    await settleX402Payment({}, 'chave_invalida');
    assert.fail('Deveria ter lançado erro para chave inválida');
  } catch (err) {
    assert.ok(err.message.includes('Chave privada inválida'), 'Deve rejeitar chave mal formatada');
    console.log('  ✓ Rejeição de chave privada inválida validada com sucesso!');
  }

  // 5. Teste de Repasse Transparente de Prova Criptografica de Oraculo (_oracle_proof)
  console.log('\n[5/5] Testando presenca da prova de oraculo (_oracle_proof) em modo sandbox...');
  const originalSandbox = process.env.GRID402_SANDBOX;
  try {
    process.env.GRID402_SANDBOX = 'true';
    const sandboxWorkdays = await executeGrid402Request('workdays', { start_date: '2026-11-06', days: 5 });
    assert.strictEqual(sandboxWorkdays.status, 'success', 'Status da resposta deve ser success');
    assert.ok(sandboxWorkdays._oracle_proof, 'A resposta deve conter o bloco _oracle_proof');
    assert.ok(sandboxWorkdays._oracle_proof.signature, 'A prova deve conter assinatura digital');
    assert.strictEqual(sandboxWorkdays._oracle_proof.sandbox, true, 'A prova deve indicar modo sandbox: true');
    assert.strictEqual(sandboxWorkdays._oracle_proof.algorithm, 'secp256k1-sha256', 'Algoritmo deve ser secp256k1-sha256');
    console.log('  ✓ Prova de autenticidade criptografica (_oracle_proof) repassada com sucesso pelo MCP!');

    // 6. Teste de Verificacao da Prova de Oraculo via grid402_verify
    console.log('\n[6/6] Testando execucao da ferramenta grid402_verify...');
    const verificationResult = await executeGrid402Request('verify', { payload: sandboxWorkdays });
    assert.strictEqual(verificationResult.valid, true, 'Prova deve ser considerada valida pelo endpoint');
    assert.strictEqual(verificationResult.status, 'AUTHENTIC_ORACLE_DATA', 'Status de verificacao deve ser AUTHENTIC_ORACLE_DATA');
    assert.ok(verificationResult.oracle_address, 'Deve retornar o endereco do oraculo');
    console.log('  ✓ Ferramenta grid402_verify executada e validou assinatura com sucesso!');
  } finally {
    process.env.GRID402_SANDBOX = originalSandbox;
  }

  console.log('\n=== Todos os testes do MCP Server Grid402 foram APROVADOS com 100% de sucesso! ===');
}

runTests().catch(err => {
  console.error('\n❌ Falha no teste do MCP Server:', err);
  process.exit(1);
});

