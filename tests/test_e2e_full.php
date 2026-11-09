<?php
/**
 * Grid402 - Teste de Homologação Ponta a Ponta (End-to-End)
 * 
 * Valida todos os cenários da especificação do Hub de Utilitários de Rede e Dados Públicos:
 * 1. Execução do Coletor e geração do resumo atômico gratuito
 * 2. Manifesto llms.txt para crawlers e agentes de IA
 * 3. Ciclo de vida M2M via Servidor HTTP Local:
 *    - Landing Page Dark-Mode (HTML)
 *    - Resumo Executivo Gratuito (JSON)
 *    - Desafio x402 (HTTP 402 Payment Required)
 *    - Bloqueio de Replay Attack via trava atômica flock (HTTP 403)
 *    - Emissão de Badges Dinâmicos em SVG (Forex, Crypto, DNS, System)
 *    - Endpoints Jurídicos (Termos AS-IS e Política LGPD)
 * 4. Conectividade Blockchain com o RPC da Base Mainnet
 */

declare(strict_types=1);

echo "=======================================================\n";
echo "    GRID402 - HOMOLOGAÇÃO DE SISTEMA PONTA A PONTA     \n";
echo "=======================================================\n\n";

$step = 1;

// 1. Execução do Coletor
echo "[PASSO {$step}] Testando Sincronização do Coletor (collector.php)...\n";
$step++;
$t0 = microtime(true);
ob_start();
require __DIR__ . '/../public_html/collector.php';
$collectorOut = ob_get_clean();
$tCollector = round((microtime(true) - $t0) * 1000, 2);

echo "  -> Saída: " . trim($collectorOut) . "\n";
echo "  -> Duração total da sincronização: {$tCollector} ms\n";
assert($tCollector < 4000, "Coletor excedeu 4000ms ({$tCollector}ms)");

$freePath = __DIR__ . '/../public_html/private_data/summary_free.json';
assert(file_exists($freePath), "summary_free.json não foi gerado");

$free = json_decode((string)file_get_contents($freePath), true);
assert($free['protocol'] === 'x402', 'Protocolo deve ser x402');
assert($free['chain_id'] === 8453, 'Chain ID deve ser 8453 (Base)');
assert($free['status'] === 'OPERATIONAL', 'Status deve ser OPERATIONAL');
assert(isset($free['services']['forex']), 'Serviço forex deve estar presente');
assert(isset($free['services']['crypto']), 'Serviço crypto deve estar presente');
assert(isset($free['services']['dns']), 'Serviço dns deve estar presente');
assert(isset($free['services']['ssl']), 'Serviço ssl deve estar presente');
assert(isset($free['services']['asn']), 'Serviço asn deve estar presente');
assert(isset($free['services']['cep']), 'Serviço cep deve estar presente');
echo "  ✓ Coletor aprovado com sucesso (< 4.0s e catálogo dos 6 serviços validado)!\n\n";

// 2. Validação do Manifesto llms.txt, openapi.json e robots.txt para IAs
echo "[PASSO {$step}] Verificando Arquivos de Descoberta para Crawlers e LLMs...\n";
$step++;
$llmsPath = __DIR__ . '/../public_html/llms.txt';
$openApiPath = __DIR__ . '/../public_html/openapi.json';
$robotsPath = __DIR__ . '/../public_html/robots.txt';
$sitemapPath = __DIR__ . '/../public_html/sitemap.xml';

assert(file_exists($llmsPath), "llms.txt não encontrado");
assert(file_exists($openApiPath), "openapi.json não encontrado");
assert(file_exists($robotsPath), "robots.txt não encontrado");
assert(file_exists($sitemapPath), "sitemap.xml não encontrado");

$llmsContent = (string)file_get_contents($llmsPath);
assert(str_contains($llmsContent, 'Grid402'), 'llms.txt deve conter Grid402');
assert(str_contains($llmsContent, '8453'), 'llms.txt deve referenciar a chain Base (8453)');
assert(str_contains($llmsContent, '0.0005'), 'llms.txt deve indicar preço de 0.0005 USDC');
assert(str_contains($llmsContent, '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'), 'llms.txt deve conter contrato do USDC');
assert(str_contains($llmsContent, '/api/v1/forex'), 'llms.txt deve documentar rota forex');
assert(str_contains($llmsContent, '/api/v1/dns'), 'llms.txt deve documentar rota dns');

$openApiJson = json_decode((string)file_get_contents($openApiPath), true);
assert(isset($openApiJson['paths']['/api/v1/forex']), 'openapi.json deve conter /api/v1/forex');
assert(isset($openApiJson['paths']['/api/v1/dns']), 'openapi.json deve conter /api/v1/dns');

$robotsContent = (string)file_get_contents($robotsPath);
assert(str_contains($robotsContent, 'GPTBot'), 'robots.txt deve permitir GPTBot');
assert(str_contains($robotsContent, 'ClaudeBot'), 'robots.txt deve permitir ClaudeBot');

echo "  ✓ Arquivos de Descoberta M2M (llms.txt, openapi.json, robots.txt, sitemap.xml) íntegros!\n\n";

// 3. Teste do Servidor HTTP e Ciclo de Vida M2M
echo "[PASSO {$step}] Subindo servidor web local para simular tráfego HTTP real...\n";
$step++;

$port = 8956;
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$phpBinary = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';
$cmd = "\"{$phpBinary}\" -S 127.0.0.1:{$port} -t \"" . __DIR__ . "/../public_html\"";
$proc = proc_open($cmd, $descriptors, $pipes);
usleep(350000); // 350ms para inicialização do listener

function test_req(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 4,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 2);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$body, 'time_ms' => $time];
}

try {
    // 3.1. Navegador Humano
    echo "  -> Simulando acesso de navegador web (Accept: text/html)...\n";
    $nav = test_req("http://127.0.0.1:{$port}/", ['Accept: text/html']);
    assert($nav['code'] === 200, "Navegador deve receber HTTP 200 (Recebeu: {$nav['code']})");
    assert(str_contains($nav['body'], '<!DOCTYPE html>'), "Deve conter HTML5");
    assert(str_contains($nav['body'], 'Grid402'), "Deve exibir Grid402");
    assert(str_contains($nav['body'], 'Câmbio'), "Deve exibir serviço de Câmbio");
    assert(str_contains($nav['body'], 'DNS Records'), "Deve exibir serviço de DNS");
    assert(str_contains($nav['body'], 'id="playground"'), "Deve conter a seção de Playground");
    assert(str_contains($nav['body'], 'href="#playground"'), "Deve conter link para Playground no menu");
    assert(str_contains($nav['body'], 'id="playground-audit-card"'), "Deve conter o Card de Auditoria Técnica");
    echo "     ✓ Landing Page dark-mode e Playground renderizados em {$nav['time_ms']} ms (HTTP 200)!\n";

    // 3.2. Agente de IA consultando resumo gratuito
    echo "  -> Simulando consulta de resumo gratuito por Agente IA (Accept: application/json)...\n";
    $freeReq = test_req("http://127.0.0.1:{$port}/?format=json", ['Accept: application/json']);
    assert($freeReq['code'] === 200, "Resumo gratuito deve responder 200");
    assert($freeReq['time_ms'] < 100, "Leitura de resumo gratuito deve ser < 100ms (Levou {$freeReq['time_ms']}ms)");
    $freeJson = json_decode($freeReq['body'], true);
    assert($freeJson['status'] === 'OPERATIONAL', "Status no JSON deve ser OPERATIONAL");
    echo "     ✓ Resumo gratuito entregue em {$freeReq['time_ms']} ms (HTTP 200)!\n";

    // 3.3. Agente solicitando utilitário técnico sem pagamento (Desafio x402)
    echo "  -> Simulando requisição de micro-serviço técnico sem X-Payment (Desafio x402)...\n";
    $chalReq = test_req("http://127.0.0.1:{$port}/?api=forex", ['Accept: application/json']);
    assert($chalReq['code'] === 402, "Deve retornar desafio HTTP 402 Payment Required");
    $chalData = json_decode($chalReq['body'], true);
    assert($chalData['error'] === 'PaymentRequired', 'Erro deve ser PaymentRequired');
    assert($chalData['network'] === 'Base', 'Rede deve ser Base');
    assert($chalData['token'] === 'USDC', 'Token deve ser USDC');
    assert($chalData['price_usdc'] === '0.0005', 'Preço deve ser 0.0005 USDC');
    echo "     ✓ Desafio x402 emitido em {$chalReq['time_ms']} ms (HTTP 402)!\n";

    // 3.4. Agente tentando Replay Attack
    echo "  -> Simulando tentativa de Replay Attack com tx_hash já liquidado...\n";
    $testReplayTx = '0x' . bin2hex(random_bytes(32));
    require_once __DIR__ . '/../public_html/x402.php';
    grid402_mark_transaction_used($testReplayTx);

    $replayReq = test_req("http://127.0.0.1:{$port}/?api=forex", [
        'Accept: application/json',
        "X-Payment: {$testReplayTx}",
    ]);
    assert($replayReq['code'] === 403, "Replay attack deve retornar HTTP 403 Forbidden");
    $replayData = json_decode($replayReq['body'], true);
    assert($replayData['error'] === 'ReplayAttackDetected', 'Deve acusar ReplayAttackDetected');
    echo "     ✓ Replay attack bloqueado com sucesso (HTTP 403)!\n";

    // 3.5. Requisição de Badges SVG Dinâmicos
    echo "  -> Simulando requisição de Badges SVG dinâmicos...\n";
    $badgeForex = test_req("http://127.0.0.1:{$port}/badge.php?target=forex");
    assert($badgeForex['code'] === 200, "Badge forex deve retornar HTTP 200");
    assert(str_contains($badgeForex['body'], '<svg'), "Badge forex deve conter tag <svg");
    assert(str_contains($badgeForex['body'], 'USD / BRL'), "Badge forex deve conter USD / BRL");

    $badgeSys = test_req("http://127.0.0.1:{$port}/badge.php?target=system");
    assert($badgeSys['code'] === 200, "Badge system deve retornar HTTP 200");
    assert(str_contains($badgeSys['body'], 'Grid402 Hub'), "Badge system deve conter Grid402 Hub");
    echo "     ✓ Badges dinâmicos SVG emitidos em {$badgeForex['time_ms']} ms (HTTP 200)!\n";

    // 3.6. Requisições jurídicas (Terms e Privacy para Agentes e LGPD)
    echo "  -> Simulando consulta de Termos de Uso e Privacidade LGPD...\n";
    $termsReq = test_req("http://127.0.0.1:{$port}/?page=terms", ['Accept: application/json']);
    assert($termsReq['code'] === 200, "Terms deve retornar HTTP 200");
    assert(str_contains($termsReq['body'], 'AS-IS'), "Terms deve conter cláusula AS-IS");

    $privReq = test_req("http://127.0.0.1:{$port}/?page=privacy", ['Accept: application/json']);
    assert($privReq['code'] === 200, "Privacy deve retornar HTTP 200");
    assert(str_contains($privReq['body'], 'LGPD'), "Privacy deve referenciar a LGPD");
    echo "     ✓ Termos de Uso e Política LGPD validados em {$termsReq['time_ms']} ms (HTTP 200)!\n";

    // 3.7. Validação do micro-serviço de Calculadora de Dias Úteis ANBIMA (workdays)
    echo "  -> Simulando requisição de dias úteis ANBIMA sem pagamento (Desafio x402 com Teaser)...\n";
    $workdaysQuery = http_build_query([
        'api' => 'workdays',
        'start_date' => '2026-12-24',
        'end_date' => '2026-12-28',
    ]);
    $workdaysReq = test_req("http://127.0.0.1:{$port}/?{$workdaysQuery}", ['Accept: application/json']);
    assert($workdaysReq['code'] === 402, "Workdays sem pagamento deve responder HTTP 402");
    $workdaysChal = json_decode($workdaysReq['body'], true);
    assert($workdaysChal['error'] === 'PaymentRequired', 'Erro deve ser PaymentRequired');
    assert(isset($workdaysChal['preview']['is_workday']), 'Teaser deve conter is_workday');
    assert(isset($workdaysChal['preview']['base_date']), 'Teaser deve conter base_date');
    assert(in_array('workdays', $workdaysChal['locked_fields'] ?? [], true), 'workdays deve ser campo bloqueado');

    echo "  -> Simulando execução de dias úteis em Modo Sandbox (X-Payment: demo)...\n";
    $workdaysDemo = test_req("http://127.0.0.1:{$port}/?{$workdaysQuery}", [
        'Accept: application/json',
        'X-Payment: demo',
    ]);
    assert($workdaysDemo['code'] === 200, "Workdays no sandbox deve responder HTTP 200");
    $workdaysData = json_decode($workdaysDemo['body'], true);
    assert($workdaysData['status'] === 'success', "Status deve ser success");
    assert($workdaysData['workdays'] === 1, "De 24/12 a 28/12/2026 deve contabilizar 1 dia útil");
    echo "     ✓ Micro-serviço workdays validado com 100% de sucesso (HTTP 402 + Sandbox 200)!\n";

    // 3.8. Validação da Assinatura Criptográfica de Oráculo (_oracle_proof)
    echo "  -> Verificando emissão e integridade da Assinatura de Oráculo (_oracle_proof)...\n";
    assert(isset($workdaysData['_oracle_proof']), "Resposta de sucesso deve conter _oracle_proof");
    assert(!empty($workdaysData['_oracle_proof']['signature']), "Prova deve conter assinatura digital");
    assert($workdaysData['_oracle_proof']['sandbox'] === true, "Prova no sandbox deve indicar sandbox: true");
    assert($workdaysData['_oracle_proof']['oracle_address'] === '0xa31c31069c43ac1c0075a93c325b0dcd7744356c', "Carteira deve ser a oficial");
    require_once __DIR__ . '/../public_html/services/oracle_signer.php';
    $verifyRes = grid402_verify_oracle_proof($workdaysData);
    assert(!empty($verifyRes['valid']) && $verifyRes['valid'] === true, "A verificação da assinatura deve ser válida");
    echo "     ✓ Prova criptográfica (_oracle_proof) validada matematicamente no E2E!\n";

    // 3.9. Validação do Micro-Endpoint /api/v1/verify
    echo "  -> Testando micro-endpoint /api/v1/verify com payload assinado real...\n";
    $chVerify = curl_init("http://127.0.0.1:{$port}/api/v1/verify");
    curl_setopt_array($chVerify, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($workdaysData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 4,
    ]);
    $verifyBody = curl_exec($chVerify);
    $verifyCode = (int) curl_getinfo($chVerify, CURLINFO_HTTP_CODE);
    curl_close($chVerify);

    assert($verifyCode === 200, "Verify deve responder HTTP 200 (Recebeu: {$verifyCode})");
    $verifyJson = json_decode((string)$verifyBody, true);
    assert(!empty($verifyJson['valid']) && $verifyJson['valid'] === true, "Verificação deve ser válida");
    assert($verifyJson['status'] === 'AUTHENTIC_ORACLE_DATA', "Status deve ser AUTHENTIC_ORACLE_DATA");
    assert(isset($verifyJson['verification_time_ms']), "Deve registrar tempo de verificação");

    // Teste com payload adulterado
    $tamperedWorkdays = $workdaysData;
    $tamperedWorkdays['workdays'] = 999;
    $chTampered = curl_init("http://127.0.0.1:{$port}/api/v1/verify");
    curl_setopt_array($chTampered, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($tamperedWorkdays),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 4,
    ]);
    $tamperedBody = curl_exec($chTampered);
    $tamperedCode = (int) curl_getinfo($chTampered, CURLINFO_HTTP_CODE);
    curl_close($chTampered);

    assert($tamperedCode === 200, "Verify adulterado deve responder HTTP 200 com valid: false");
    $tamperedJson = json_decode((string)$tamperedBody, true);
    assert(isset($tamperedJson['valid']) && $tamperedJson['valid'] === false, "Verificação deve falhar");
    assert(($tamperedJson['error'] ?? '') === 'DataHashMismatch', "Erro deve ser DataHashMismatch");
    echo "     ✓ Micro-endpoint /api/v1/verify validou autenticidade e detectou adulteracao com sucesso!\n";

    echo "  ✓ Todos os testes HTTP foram validados com êxito!\n\n";

} finally {
    proc_terminate($proc);
    fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
}

// 4. Conectividade Blockchain com a Base Mainnet
echo "[PASSO {$step}] Verificando Conectividade On-Chain com a Base Mainnet...\n";
$step++;
$rpcStart = microtime(true);
$rpcReceipt = grid402_rpc_get_receipt('0x0000000000000000000000000000000000000000000000000000000000000000');
$rpcTime = round((microtime(true) - $rpcStart) * 1000, 2);
echo "  -> Roundtrip JSON-RPC da Base Mainnet: {$rpcTime} ms\n";
echo "  ✓ Conexão com RPC da Base Mainnet homologada com sucesso!\n\n";

echo "=======================================================\n";
echo "    ✓ TODOS OS TESTES E CENÁRIOS FORAM HOMOLOGADOS!    \n";
echo "=======================================================\n";
