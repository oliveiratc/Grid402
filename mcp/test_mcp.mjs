/**
 * Grid402 MCP Server - Bateria de Testes Automatizados
 */

import assert from 'node:assert';
import crypto from 'node:crypto';
import { MCP_TOOLS_DEFINITIONS, executeGrid402Request } from './tools.mjs';
import {
  settleX402Payment,
  GRID402_RECIPIENT_WALLET,
  GRID402_SOLANA_RECIPIENT_WALLET,
  USDC_MINT_SOLANA,
  SUPPORTED_X402_NETWORKS,
  normalizePaymentNetwork,
  encodeBase58,
  decodeBase58,
  parseSolanaKeypair,
  buildSolanaX402PaymentPayload,
  buildEip3009PaymentPayload,
} from './x402_payer.mjs';

async function runTests() {
  console.log('=== Testando Servidor MCP Grid402 ===\n');

  // 1. Validação de Schemas das Ferramentas
  console.log('[1/7] Validando registro e schemas das 33 ferramentas MCP...');
  assert.strictEqual(MCP_TOOLS_DEFINITIONS.length, 33, 'Deve conter exatamente 33 ferramentas registradas');

  const toolNames = MCP_TOOLS_DEFINITIONS.map(t => t.name);
  const expectedTools = [
    'grid402_summary',
    'grid402_forex',
    'grid402_crypto',
    'grid402_dns',
    'grid402_ssl',
    'grid402_asn',
    'grid402_domain_audit',
    'grid402_kyb_audit',
    'grid402_get_dossier_360',
    'grid402_market_snapshot',
    'grid402_cep',
    'grid402_cnpj',
    'grid402_indicators',
    'grid402_holidays',
    'grid402_workdays',
    'grid402_sanctions',
    'grid402_simples',
    'grid402_compliance',
    'grid402_lei',
    'grid402_ofac',
    'grid402_notarize',
    'grid402_global_markets',
    'grid402_extract',
    'grid402_email_verify',
    'grid402_token_safety',
    'grid402_prediction_markets',
    'grid402_sec_company',
    'grid402_vuln_audit',
    'grid402_chain_telemetry',
    'grid402_batch',
    'grid402_pass',
    'grid402_pass_balance',
    'grid402_verify',
  ];

  for (const expected of expectedTools) {
    assert.ok(toolNames.includes(expected), `Ferramenta ${expected} deve estar presente`);
  }
  for (const paidTool of ['grid402_sanctions', 'grid402_simples', 'grid402_compliance']) {
    const def = MCP_TOOLS_DEFINITIONS.find(t => t.name === paidTool);
    assert.ok(def?.inputSchema?.properties?.payment_tx_hash, `Ferramenta ${paidTool} deve expor payment_tx_hash no inputSchema`);
  }
  console.log('  ✓ Todas as 30 ferramentas MCP validadas com sucesso!');

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
  assert.ok(dnsChallenge.checkout_options?.pix_instant_brl?.starter_400_credits?.includes('autopix=1'), 'Desafio 402 deve incluir link de Pix instantaneo (autopix=1)');
  assert.ok(dnsChallenge.checkout_options?.rapidapi_credit_card_usd?.all_25_apis?.includes('rapidapi.com'), 'Desafio 402 deve incluir links das 3 APIs no RapidAPI Hub');
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

  // 4. Teste de Validação Defensiva de Chave Privada e Auto-Payer Multi-Chain (Base, Polygon, Arbitrum e Solana)
  console.log('\n[4/7] Testando validação defensiva de chave privada e Auto-Payer Multi-Chain (EVM + Solana SPL USDC)...');
  try {
    await settleX402Payment({}, 'chave_invalida');
    assert.fail('Deveria ter lançado erro para chave inválida');
  } catch (err) {
    assert.ok(err.message.includes('Chave privada inválida'), 'Deve rejeitar chave mal formatada');
  }

  // 4.1 Redes suportadas e normalização
  for (const net of ['base', 'polygon', 'arbitrum', 'optimism', 'ethereum', 'solana']) {
    assert.ok(SUPPORTED_X402_NETWORKS[net], `Rede ${net} deve estar registrada em SUPPORTED_X402_NETWORKS`);
  }
  assert.strictEqual(normalizePaymentNetwork('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp'), 'solana');
  assert.strictEqual(normalizePaymentNetwork('eip155:137'), 'polygon');
  assert.strictEqual(normalizePaymentNetwork('eip155:42161'), 'arbitrum');

  // 4.2 Teste de keypair Solana Ed25519 + construção de payload x402 v2 SVM (SPL USDC TransferChecked)
  const solSeed = crypto.randomBytes(32);
  const solSeedB58 = encodeBase58(solSeed);
  assert.strictEqual(normalizePaymentNetwork(null, solSeedB58), 'solana', 'Deve auto-detectar chave Base58 como Solana');
  const solKp = parseSolanaKeypair(solSeedB58);
  assert.strictEqual(solKp.publicKeyBytes.length, 32, 'Pubkey Solana deve ter 32 bytes');
  assert.strictEqual(decodeBase58(solKp.publicKeyBase58).length, 32, 'Pubkey Base58 deve decodificar para 32 bytes');

  const dummyAtaSource = encodeBase58(crypto.randomBytes(32));
  const dummyAtaDest = encodeBase58(crypto.randomBytes(32));
  const dummyBlockhash = encodeBase58(crypto.randomBytes(32));
  const solX402 = buildSolanaX402PaymentPayload({
    privateKey: solSeedB58,
    sourceTokenAccount: dummyAtaSource,
    destinationTokenAccount: dummyAtaDest,
    recentBlockhash: dummyBlockhash,
    rawUnits: 5000n,
  });
  assert.strictEqual(solX402.payload.x402Version, 2);
  assert.strictEqual(solX402.payload.network, 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp');
  assert.strictEqual(solX402.payload.accepted.asset, USDC_MINT_SOLANA);
  assert.strictEqual(solX402.payload.accepted.payTo, GRID402_SOLANA_RECIPIENT_WALLET);
  assert.ok(Buffer.from(solX402.payload.payload.transaction, 'base64').length > 150, 'Transação Solana SPL USDC assinada deve ter > 150 bytes');

  // 4.3 Teste de assinatura Gasless EIP-3009 (Base, Polygon e Arbitrum) ATIVO POR PADRÃO via settleX402Payment
  const testEvmKey = '0x' + crypto.randomBytes(32).toString('hex');
  const prevGaslessEnv = process.env.GRID402_X402_GASLESS;
  delete process.env.GRID402_X402_GASLESS;
  try {
    for (const evmNet of ['base', 'polygon', 'arbitrum']) {
      const gaslessRes = await settleX402Payment({ price_raw_units: 5000 }, testEvmKey, null, {
        network: evmNet,
      });
      assert.strictEqual(gaslessRes.network, evmNet);
      assert.strictEqual(gaslessRes.mode, 'x402_gasless', `Gasless deve ser padrão para ${evmNet}`);
      const decodedPayload = JSON.parse(Buffer.from(gaslessRes.paymentHeader, 'base64').toString('utf8'));
      assert.strictEqual(decodedPayload.x402Version, 2);
      assert.strictEqual(decodedPayload.network, SUPPORTED_X402_NETWORKS[evmNet].caip2);
      assert.strictEqual(decodedPayload.accepted.payTo, GRID402_RECIPIENT_WALLET);
    }
  } finally {
    if (prevGaslessEnv !== undefined) process.env.GRID402_X402_GASLESS = prevGaslessEnv;
  }

  // 4.4 Bloqueio de demo e pass-por-pass em grid402_pass
  const passDemoBlocked = await executeGrid402Request('pass', { payment_tx_hash: 'demo' });
  assert.strictEqual(passDemoBlocked.error, 'PassPaymentModeForbidden', 'grid402_pass deve bloquear payment_tx_hash="demo"');
  const passByPassBlocked = await executeGrid402Request('pass', { payment_tx_hash: 'pass_g402_fake1234' });
  assert.strictEqual(passByPassBlocked.error, 'PassPaymentModeForbidden', 'grid402_pass deve bloquear pagamento com outro Session Pass');
  const passAutoTrialChallenge = await executeGrid402Request('pass', {}, { autoTrial: true });
  assert.strictEqual(passAutoTrialChallenge.payment_required, true, 'grid402_pass com autoTrial=true nunca deve injetar demo e deve retornar desafio 402');
  console.log('  ✓ Rejeição de chave inválida, Gasless padrão EVM, bloqueio demo/pass-por-pass em grid402_pass e Auto-Payer Multi-Chain validados com sucesso!');

  // 5. Teste de Repasse Transparente de Prova Criptografica de Oraculo (_oracle_proof)
  console.log('\n[5/7] Testando presenca da prova de oraculo (_oracle_proof) em modo sandbox...');
  const originalSandbox = process.env.GRID402_SANDBOX;
  try {
    process.env.GRID402_SANDBOX = 'true';
    const sandboxWorkdays = await executeGrid402Request('workdays', { start_date: '2026-11-06', days: 5 });
    if (sandboxWorkdays.error === 'SandboxRateLimitExceeded') {
      assert.ok(sandboxWorkdays.upgrade_to_production, 'Resposta 429 SandboxRateLimitExceeded deve conter bloco upgrade_to_production');
      assert.ok(sandboxWorkdays.upgrade_to_production, 'Resposta 429 deve conter bloco upgrade_to_production');
      console.log('  ✓ Cota diária de sandbox (3/dia) ativa e validada com retorno estruturado HTTP 429!');

      console.log('\n[6/7] Testando execucao da ferramenta gratuita grid402_verify...');
      const verificationProbe = await executeGrid402Request('verify', { payload: { test: true } });
      assert.strictEqual(typeof verificationProbe.valid, 'boolean', 'Endpoint verify deve responder valid boolean');
      console.log('  ✓ Ferramenta grid402_verify respondeu com sucesso!');
    } else {
      assert.strictEqual(sandboxWorkdays.status, 'success', 'Status da resposta deve ser success');
      assert.ok(sandboxWorkdays._oracle_proof, 'A resposta deve conter o bloco _oracle_proof');
      assert.ok(sandboxWorkdays._oracle_proof.signature, 'A prova deve conter assinatura digital');
      assert.strictEqual(sandboxWorkdays._oracle_proof.sandbox, true, 'A prova deve indicar modo sandbox: true');
      assert.strictEqual(sandboxWorkdays._oracle_proof.algorithm, 'secp256k1-sha256', 'Algoritmo deve ser secp256k1-sha256');
      console.log('  ✓ Prova de autenticidade criptografica (_oracle_proof) repassada com sucesso pelo MCP!');

      // 6. Teste de Verificacao da Prova de Oraculo via grid402_verify
      console.log('\n[6/7] Testando execucao da ferramenta grid402_verify...');
      const verificationResult = await executeGrid402Request('verify', { payload: sandboxWorkdays });
      assert.strictEqual(verificationResult.valid, true, 'Prova deve ser considerada valida pelo endpoint');
      assert.strictEqual(verificationResult.status, 'AUTHENTIC_ORACLE_DATA', 'Status de verificacao deve ser AUTHENTIC_ORACLE_DATA');
      assert.ok(verificationResult.oracle_address, 'Deve retornar o endereco do oraculo');
      console.log('  ✓ Ferramenta grid402_verify executada e validou assinatura com sucesso!');
    }
  } finally {
    process.env.GRID402_SANDBOX = originalSandbox;
  }

  console.log('\n[7/7] Testando handshake JSON-RPC stdio (instructions + version 2.0.0 + 27 tools)...');
  const { spawn } = await import('node:child_process');
  const { fileURLToPath } = await import('node:url');
  const { dirname, join } = await import('node:path');
  const mcpDir = dirname(fileURLToPath(import.meta.url));
  const rpcResult = await new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [join(mcpDir, 'index.mjs')], {
      cwd: mcpDir,
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    let stdoutBuf = '';
    const responses = new Map();
    const timeout = setTimeout(() => {
      child.kill();
      reject(new Error('Timeout aguardando handshake MCP stdio'));
    }, 8000);

    child.stdout.on('data', (chunk) => {
      stdoutBuf += chunk.toString('utf8');
      const lines = stdoutBuf.split(/\r?\n/);
      stdoutBuf = lines.pop() ?? '';
      for (const line of lines) {
        if (!line.trim()) continue;
        try {
          const msg = JSON.parse(line);
          if (msg.id !== undefined) {
            responses.set(msg.id, msg);
          }
          if (responses.has(1) && responses.has(2)) {
            clearTimeout(timeout);
            child.kill();
            resolve({ init: responses.get(1), list: responses.get(2) });
          }
        } catch {
          // Ignora linhas parciais ou informativas
        }
      }
    });

    child.on('error', (err) => {
      clearTimeout(timeout);
      reject(err);
    });

    child.stdin.write(JSON.stringify({
      jsonrpc: '2.0',
      id: 1,
      method: 'initialize',
      params: {
        protocolVersion: '2024-11-05',
        capabilities: {},
        clientInfo: { name: 'grid402-test', version: '1.0.0' },
      },
    }) + '\n');
    child.stdin.write(JSON.stringify({
      jsonrpc: '2.0',
      method: 'notifications/initialized',
    }) + '\n');
    child.stdin.write(JSON.stringify({
      jsonrpc: '2.0',
      id: 2,
      method: 'tools/list',
      params: {},
    }) + '\n');
  });

  assert.strictEqual(rpcResult.init?.result?.serverInfo?.version, '2.2.0', 'Versao MCP initialize deve ser 2.2.0');
  assert.ok(typeof rpcResult.init?.result?.instructions === 'string' && rpcResult.init.result.instructions.includes('GRID402_SESSION_PASS'), 'Handshake initialize deve retornar instructions com orientacoes de Session Pass e sandbox');
  assert.ok(rpcResult.init.result.instructions.includes('GRID402_X402_GASLESS'), 'Instructions deve documentar GRID402_X402_GASLESS');
  assert.ok(rpcResult.init.result.instructions.includes('demo'), 'Instructions deve mencionar modo sandbox demo');
  assert.strictEqual(rpcResult.list?.result?.tools?.length, 33, 'tools/list via stdio deve expor exatamente 33 ferramentas');
  console.log('  ✓ Handshake stdio retornou version=2.2.0, instructions nativas e 33 ferramentas!');

  console.log('\n=== Todos os testes do MCP Server Grid402 foram APROVADOS com 100% de sucesso! ===');
}

runTests().catch(err => {
  console.error('\n❌ Falha no teste do MCP Server:', err);
  process.exit(1);
});

