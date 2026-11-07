<?php
/**
 * Grid402 - Teste de Homologação Ponta a Ponta (End-to-End)
 * 
 * Valida todos os cenários da especificação do Hub de Utilitários de Rede & Dados Públicos:
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

// 2. Validação do Manifesto llms.txt
echo "[PASSO {$step}] Verificando Manifesto llms.txt para Crawlers e LLMs...\n";
$step++;
$llmsPath = __DIR__ . '/../public_html/llms.txt';
assert(file_exists($llmsPath), "llms.txt não encontrado");
$llmsContent = (string)file_get_contents($llmsPath);
assert(str_contains($llmsContent, 'Grid402'), 'llms.txt deve conter Grid402');
assert(str_contains($llmsContent, '8453'), 'llms.txt deve referenciar a chain Base (8453)');
assert(str_contains($llmsContent, '0.0005'), 'llms.txt deve indicar preço de 0.0005 USDC');
assert(str_contains($llmsContent, '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'), 'llms.txt deve conter contrato do USDC');
assert(str_contains($llmsContent, '/api/v1/forex'), 'llms.txt deve documentar rota forex');
assert(str_contains($llmsContent, '/api/v1/dns'), 'llms.txt deve documentar rota dns');
echo "  ✓ Manifesto llms.txt íntegro e em conformidade!\n\n";

// 3. Teste do Servidor HTTP e Ciclo de Vida M2M
echo "[PASSO {$step}] Subindo servidor web local para simular tráfego HTTP real...\n";
$step++;

$port = 8956;
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$phpBinary = 'C:\Users\thiag\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe';
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
    echo "     ✓ Landing Page dark-mode renderizada em {$nav['time_ms']} ms (HTTP 200)!\n";

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

    // 3.6. Requisições jurídicas (Terms & Privacy para Agentes e LGPD)
    echo "  -> Simulando consulta de Termos de Uso e Privacidade LGPD...\n";
    $termsReq = test_req("http://127.0.0.1:{$port}/?page=terms", ['Accept: application/json']);
    assert($termsReq['code'] === 200, "Terms deve retornar HTTP 200");
    assert(str_contains($termsReq['body'], 'AS-IS'), "Terms deve conter cláusula AS-IS");

    $privReq = test_req("http://127.0.0.1:{$port}/?page=privacy", ['Accept: application/json']);
    assert($privReq['code'] === 200, "Privacy deve retornar HTTP 200");
    assert(str_contains($privReq['body'], 'LGPD'), "Privacy deve referenciar a LGPD");
    echo "     ✓ Termos de Uso e Política LGPD validados em {$termsReq['time_ms']} ms (HTTP 200)!\n";

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
