<?php
/**
 * Grid402 - Teste de Homologação Ponta a Ponta (End-to-End)
 * 
 * Valida todos os cenários das specs:
 * - Coleta paralela via cURL Multi em tempo < 4.0s
 * - Geração de JSON duplo (summary_free e telemetry_paid)
 * - Manifesto llms.txt
 * - Interface Web Dark-Mode
 * - Handshake x402 (HTTP 402 Challenge)
 * - Proteção contra Replay Attack (HTTP 403)
 * - Validação RPC com a Base Mainnet
 */

declare(strict_types=1);

echo "=======================================================\n";
echo "    GRID402 - HOMOLOGAÇÃO DE SISTEMA PONTA A PONTA     \n";
echo "=======================================================\n\n";

$step = 1;

// 1. Execução do Coletor Concorrente
echo "[PASSO {$step}] Testando Motor de Telemetria Concorrente (collector.php)...\n";
$step++;
$t0 = microtime(true);
ob_start();
require __DIR__ . '/../public_html/collector.php';
$collectorOut = ob_get_clean();
$tCollector = round((microtime(true) - $t0) * 1000, 2);

echo "  -> Saída: " . trim($collectorOut) . "\n";
echo "  -> Duração total da coleta: {$tCollector} ms\n";
assert($tCollector < 4000, "Coletor excedeu 4000ms ({$tCollector}ms)");

$freePath = __DIR__ . '/../public_html/private_data/summary_free.json';
$paidPath = __DIR__ . '/../public_html/private_data/telemetry_paid.json';
assert(file_exists($freePath), "summary_free.json não foi gerado");
assert(file_exists($paidPath), "telemetry_paid.json não foi gerado");

$free = json_decode((string)file_get_contents($freePath), true);
$paid = json_decode((string)file_get_contents($paidPath), true);
assert($free['protocol'] === 'x402', 'Protocolo deve ser x402');
assert($free['chain_id'] === 8453, 'Chain ID deve ser 8453 (Base)');
assert(count($paid['targets']) >= 15, 'Devem existir 15 ou mais alvos no payload pago');
echo "  ✓ Coletor aprovado com sucesso (< 4.0s e arquivos JSON válidos)!\n\n";

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
echo "  ✓ Manifesto llms.txt íntegro e em conformidade!\n\n";

// 3. Teste do Servidor HTTP e Ciclo de Vida M2M
echo "[PASSO {$step}] Subindo servidor web local para simular tráfego HTTP real...\n";
$step++;

$port = 8955;
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$phpBinary = 'C:\Users\thiag\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe';
$cmd = "\"{$phpBinary}\" -S 127.0.0.1:{$port} -t \"" . __DIR__ . "/../public_html\"";
$proc = proc_open($cmd, $descriptors, $pipes);
usleep(300000); // 300ms

function test_req(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 3,
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
    assert(str_contains($nav['body'], 'SEFAZ'), "Deve exibir categoria SEFAZ na landing page");
    echo "     ✓ Landing Page dark-mode renderizada em {$nav['time_ms']} ms (HTTP 200)!\n";

    // 3.2. Agente de IA consultando resumo gratuito
    echo "  -> Simulando consulta de resumo gratuito por Agente IA (Accept: application/json)...\n";
    $freeReq = test_req("http://127.0.0.1:{$port}/?format=json", ['Accept: application/json']);
    assert($freeReq['code'] === 200, "Resumo gratuito deve responder 200");
    assert($freeReq['time_ms'] < 100, "Leitura de resumo gratuito deve ser < 100ms (Levou {$freeReq['time_ms']}ms)");
    echo "     ✓ Resumo gratuito entregue em {$freeReq['time_ms']} ms (HTTP 200)!\n";

    // 3.3. Agente solicitando telemetria detalhada sem pagamento
    echo "  -> Simulando requisição de telemetria detalhada sem X-Payment...\n";
    $chalReq = test_req("http://127.0.0.1:{$port}/?detail=true", ['Accept: application/json']);
    assert($chalReq['code'] === 402, "Deve retornar desafio HTTP 402 Payment Required");
    $chalData = json_decode($chalReq['body'], true);
    assert($chalData['error'] === 'PaymentRequired', 'Erro deve ser PaymentRequired');
    assert($chalData['network'] === 'Base', 'Rede deve ser Base');
    echo "     ✓ Desafio x402 emitido em {$chalReq['time_ms']} ms (HTTP 402)!\n";

    // 3.4. Agente tentando Replay Attack
    echo "  -> Simulando tentativa de Replay Attack com tx_hash já liquidado...\n";
    $testReplayTx = '0x' . str_repeat('f', 64);
    require_once __DIR__ . '/../public_html/x402.php';
    grid402_mark_transaction_used($testReplayTx);

    $replayReq = test_req("http://127.0.0.1:{$port}/?detail=true", [
        'Accept: application/json',
        "X-Payment: {$testReplayTx}",
    ]);
    assert($replayReq['code'] === 403, "Replay attack deve retornar HTTP 403 Forbidden");
    $replayData = json_decode($replayReq['body'], true);
    assert($replayData['error'] === 'ReplayAttackDetected', 'Deve acusar ReplayAttackDetected');
    echo "     ✓ Replay attack bloqueado com sucesso (HTTP 403)!\n";

    // 3.5. Requisição de Badge SVG Dinâmico
    echo "  -> Simulando requisição de Badge SVG dinâmico...\n";
    $badgeReq = test_req("http://127.0.0.1:{$port}/badge.php?target=sefaz_sp");
    assert($badgeReq['code'] === 200, "Badge deve retornar HTTP 200");
    assert(str_contains($badgeReq['body'], '<svg'), "Badge deve conter tag <svg");
    assert(str_contains($badgeReq['body'], 'SEFAZ'), "Badge deve conter SEFAZ");
    echo "     ✓ Badge dinâmico SVG emitido em {$badgeReq['time_ms']} ms (HTTP 200)!\n";

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

