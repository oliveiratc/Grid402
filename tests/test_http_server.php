<?php
declare(strict_types=1);

echo "[TEST] Iniciando servidor web de teste na porta 8942...\n";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$phpBinary = 'C:\Users\thiag\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe';
$cmd = "\"{$phpBinary}\" -S 127.0.0.1:8942 -t \"" . __DIR__ . "/../public_html\"";

$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    die("Falha ao iniciar servidor de testes PHP.\n");
}

// Aguarda o servidor subir
usleep(300000); // 300ms

function http_get(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 3,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$body, 'content_type' => $contentType];
}

try {
    // 1. Teste de Acesso de Navegador (HTML Landing Page)
    echo "1. Testando requisição de navegador (Accept: text/html)...\n";
    $respHtml = http_get('http://127.0.0.1:8942/', ['Accept: text/html']);
    assert($respHtml['code'] === 200, "Código deve ser 200 (Recebido: {$respHtml['code']})");
    assert(str_contains($respHtml['body'], '<!DOCTYPE html>'), 'Deve retornar documento HTML');
    assert(str_contains($respHtml['body'], 'Grid402'), 'Deve conter nome da aplicação Grid402');
    echo "✓ Landing Page dark-mode renderizada com sucesso (HTTP 200)!\n";

    // 2. Teste de Resumo Gratuito (JSON)
    echo "2. Testando endpoint de resumo gratuito (format=json)...\n";
    $respJson = http_get('http://127.0.0.1:8942/?format=json', ['Accept: application/json']);
    assert($respJson['code'] === 200, "Código deve ser 200");
    $dataFree = json_decode($respJson['body'], true);
    assert(is_array($dataFree), "Deve ser um JSON válido");
    assert($dataFree['protocol'] === 'x402', "Protocolo deve ser x402");
    assert(isset($dataFree['categories']), "Deve conter categorias monitoradas");
    echo "✓ Resumo gratuito entregue com sucesso (HTTP 200)!\n";

    // 3. Teste de Desafio x402 (Telemetria detalhada sem pagamento)
    echo "3. Testando desafio x402 ao solicitar telemetria detalhada...\n";
    $resp402 = http_get('http://127.0.0.1:8942/?detail=true', ['Accept: application/json']);
    assert($resp402['code'] === 402, "Código deve ser 402 Payment Required (Recebido: {$resp402['code']})");
    $data402 = json_decode($resp402['body'], true);
    assert($data402['error'] === 'PaymentRequired', 'Erro deve ser PaymentRequired');
    assert($data402['network'] === 'Base', 'Rede deve ser Base');
    assert($data402['price_usdc'] === '0.0005', 'Preço deve ser 0.0005 USDC');
    assert(isset($data402['recipient_wallet']), 'Carteira recebedora deve estar presente');
    echo "✓ Desafio HTTP 402 emitido em conformidade com o protocolo!\n";

    // 4. Teste de Replay Attack via HTTP
    echo "4. Testando envio de transação reutilizada via cabeçalho X-Payment...\n";
    $usedTx = '0x' . str_repeat('d', 64);
    require_once __DIR__ . '/../public_html/x402.php';
    grid402_mark_transaction_used($usedTx);

    $respReplay = http_get('http://127.0.0.1:8942/?detail=true', [
        'Accept: application/json',
        "X-Payment: {$usedTx}",
    ]);
    assert($respReplay['code'] === 403, "Código deve ser 403 Forbidden para Replay Attack (Recebido: {$respReplay['code']})");
    $dataReplay = json_decode($respReplay['body'], true);
    assert($dataReplay['error'] === 'ReplayAttackDetected', 'Deve indicar ReplayAttackDetected');
    echo "✓ Replay attack bloqueado com sucesso via API HTTP (HTTP 403)!\n";

    echo "✓ Todos os fluxos HTTP foram validados com 100% de sucesso!\n";
} finally {
    // Encerra o processo do servidor
    proc_terminate($process);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}

