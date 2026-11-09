<?php
/**
 * Testes Automatizados do Endpoint de Verificacao /api/v1/verify
 */

declare(strict_types=1);

echo "=== Testes do Endpoint /api/v1/verify ===\n\n";

$port = 8959;
$phpBinary = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';
$cmd = "\"{$phpBinary}\" -S 127.0.0.1:{$port} -t \"" . __DIR__ . "/../public_html\"";
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($cmd, $descriptors, $pipes);
usleep(350000); // 350ms para inicializacao do servidor

$testsPassed = 0;
$testsFailed = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "[PASS] {$name}\n";
        $testsPassed++;
    } else {
        echo "[FAIL] {$name} - {$details}\n";
        $testsFailed++;
    }
}

function curl_request(string $url, string $method = 'GET', ?string $body = null, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $resBody = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => (string)$resBody];
}

try {
    // 1. Gerar payload assinado legitimo usando oracle_signer.php
    define('GRID402_EXEC', true);
    require_once __DIR__ . '/../public_html/config.php';
    require_once __DIR__ . '/../public_html/services/oracle_signer.php';

    $validData = [
        'status' => 'success',
        'service' => 'workdays',
        'workdays' => 5,
        'sandbox' => true,
    ];
    grid402_sign_data($validData, true);

    // 2. Teste POST com payload legitimo
    $postRes = curl_request(
        "http://127.0.0.1:{$port}/api/v1/verify",
        'POST',
        json_encode($validData),
        ['Content-Type: application/json', 'Accept: application/json']
    );
    $postJson = json_decode($postRes['body'], true);

    assert_test(
        'POST /api/v1/verify com dados legitimos retorna valid: true e status autentico',
        $postRes['code'] === 200 &&
        !empty($postJson['valid']) &&
        $postJson['valid'] === true &&
        $postJson['status'] === 'AUTHENTIC_ORACLE_DATA' &&
        isset($postJson['verification_time_ms']),
        $postRes['body']
    );

    // 3. Teste POST com payload adulterado (Data Tampering)
    $tamperedData = $validData;
    $tamperedData['workdays'] = 6; // Adulterado

    $tamperedRes = curl_request(
        "http://127.0.0.1:{$port}/api/v1/verify",
        'POST',
        json_encode($tamperedData),
        ['Content-Type: application/json']
    );
    $tamperedJson = json_decode($tamperedRes['body'], true);

    assert_test(
        'POST /api/v1/verify detecta adulteracao e retorna DataHashMismatch',
        $tamperedRes['code'] === 200 &&
        isset($tamperedJson['valid']) &&
        $tamperedJson['valid'] === false &&
        ($tamperedJson['error'] ?? '') === 'DataHashMismatch' &&
        $tamperedJson['status'] === 'VERIFICATION_FAILED',
        $tamperedRes['body']
    );

    // 4. Teste POST com assinatura forjada
    $forgedData = $validData;
    $forgedData['_oracle_proof']['signature'] = '0x1234567890abcdef';

    $forgedRes = curl_request(
        "http://127.0.0.1:{$port}/api/v1/verify",
        'POST',
        json_encode($forgedData),
        ['Content-Type: application/json']
    );
    $forgedJson = json_decode($forgedRes['body'], true);

    assert_test(
        'POST /api/v1/verify rejeita assinatura forjada',
        $forgedRes['code'] === 200 &&
        isset($forgedJson['valid']) &&
        $forgedJson['valid'] === false &&
        !empty($forgedJson['error']),
        $forgedRes['body']
    );

    // 5. Teste GET com parametro payload urlencoded
    $encodedPayload = urlencode(json_encode($validData));
    $getRes = curl_request(
        "http://127.0.0.1:{$port}/api/v1/verify?payload={$encodedPayload}",
        'GET',
        null,
        ['Accept: application/json']
    );
    $getJson = json_decode($getRes['body'], true);

    assert_test(
        'GET /api/v1/verify?payload=... aceita e valida payload com sucesso',
        $getRes['code'] === 200 &&
        !empty($getJson['valid']) &&
        $getJson['valid'] === true,
        $getRes['body']
    );

    // 6. Teste defensivo: Rejeicao de JSON malformado
    $malformedRes = curl_request(
        "http://127.0.0.1:{$port}/api/v1/verify",
        'POST',
        '{ invalid json content here',
        ['Content-Type: application/json']
    );
    assert_test(
        'POST /api/v1/verify rejeita JSON malformado com HTTP 400',
        $malformedRes['code'] === 400,
        "HTTP {$malformedRes['code']}: {$malformedRes['body']}"
    );

    // 7. Teste defensivo: Rejeicao de payload excessivo (> 64 KB)
    $hugePayload = json_encode(['data' => str_repeat('A', 70000)]);
    $hugeRes = curl_request(
        "http://127.0.0.1:{$port}/api/v1/verify",
        'POST',
        $hugePayload,
        ['Content-Type: application/json']
    );
    assert_test(
        'POST /api/v1/verify rejeita payload excessivo (> 64 KB) com HTTP 413',
        $hugeRes['code'] === 413,
        "HTTP {$hugeRes['code']}: {$hugeRes['body']}"
    );

} finally {
    proc_terminate($proc);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
}

echo "\nResultado: {$testsPassed} passaram | {$testsFailed} falharam.\n";
if ($testsFailed === 0) {
    echo "Todos os testes do endpoint /api/v1/verify foram APROVADOS com 100% de sucesso!\n";
    exit(0);
}
exit(1);

