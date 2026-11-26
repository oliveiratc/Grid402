<?php
/**
 * Testes automatizados das correcoes de vazamento de pagamento x402 e diagnostico Zero-PII:
 *  1. Base64 URL-encoded (%3D, %2B, %2F) em PAYMENT-SIGNATURE
 *  2. Alinhamento EIP-55, overpayment valido e maxTimeoutSeconds em paymentRequirements
 *  3. Rejeicao estrita preservada para underpayment, wrong asset e wrong payTo
 *  4. Dica especifica para endereco EVM de 40 hex no lugar de txHash (HTTP 400 + challenge)
 *  5. Diagnostico estrutural Zero-PII (proof_diag) em logger.php e grid402_read_recent_payment_failures
 *  6. Headers alternativos (X-Payment-Signature, Payment, Authorization: Bearer <base64>) via servidor HTTP local
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpDir = t_bootstrap('settle');
$tmpLogFile = $tmpDir . DIRECTORY_SEPARATOR . 'requests.jsonl';
putenv('GRID402_FILE_REQUEST_LOGS=' . $tmpLogFile);
require_once __DIR__ . '/../public_html/logger.php';

echo "=== TESTES DE CORRECAO DE VAZAMENTO x402 E DIAGNOSTICO ZERO-PII ===\n\n";

// -----------------------------------------------------------------------------
// 1. Base64 URL-encoded em grid402_decode_x402_payload
// -----------------------------------------------------------------------------
echo "[1] Decodificacao de Base64 URL-encoded:\n";
$b64Normal = t_payload();
$b64UrlEncoded = str_replace(['+', '/', '='], ['%2B', '%2F', '%3D'], $b64Normal);
$decUrlEnc = grid402_decode_x402_payload($b64UrlEncoded);
t_assert('Payload x402 URL-encoded (%2B/%2F/%3D) decodifica com sucesso', !empty($decUrlEnc['ok']) && ($decUrlEnc['version'] ?? 0) === 2);

// -----------------------------------------------------------------------------
// 2. Alinhamento EIP-55, overpayment valido e maxTimeoutSeconds em verify
// -----------------------------------------------------------------------------
echo "\n[2] Alinhamento de paymentRequirements (EIP-55, overpayment, timeout):\n";
$calls = [];
$handlers = [
    '/v2/x402/verify' => [
        'status' => 200,
        'body' => ['isValid' => true, 'payer' => '0x' . str_repeat('ab', 20)],
    ],
    '/v2/x402/settle' => [
        'status' => 200,
        'body' => [
            'success' => true,
            'transaction' => '0x' . str_repeat('cc', 32),
            'network' => 'eip155:8453',
        ],
    ],
];
t_install_transport($handlers, $calls);

$eip55Asset = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';
$eip55PayTo = '0xA592625adBceB8e025aC383F1a3FD8ebb6a63f90';
$overpayPayload = t_payload([
    'auth' => ['value' => '5000000', 'to' => $eip55PayTo],
    'accepted' => [
        'asset' => $eip55Asset,
        'payTo' => $eip55PayTo,
        'amount' => '5000000',
        'maxTimeoutSeconds' => 600,
    ],
]);

$resVerify = grid402_verify_exact_payment($overpayPayload, 2000000, 'https://grid402.online/api/v1/pass');
t_assert('Verify com overpayment (5M vs 2M) e EIP-55 retorna valid=true', !empty($resVerify['valid']));
$reqSent = $calls[0]['body']['paymentRequirements'] ?? [];
t_assert('paymentRequirements.amount alinha para 5000000 quando >= required e <= signed', ($reqSent['amount'] ?? '') === '5000000', json_encode($reqSent));
t_assert('paymentRequirements.asset preserva EIP-55 do cliente', ($reqSent['asset'] ?? '') === $eip55Asset, json_encode($reqSent));
t_assert('paymentRequirements.payTo preserva EIP-55 do cliente', ($reqSent['payTo'] ?? '') === $eip55PayTo, json_encode($reqSent));
t_assert('paymentRequirements.maxTimeoutSeconds alinha para 600', ($reqSent['maxTimeoutSeconds'] ?? 0) === 600, json_encode($reqSent));

// Seguranca preservada: underpayment e ativo/destinatario errados continuam bloqueados
$underpayPayload = t_payload(['auth' => ['value' => '1000'], 'accepted' => ['amount' => '1000']]);
$resUnder = grid402_verify_exact_payment($underpayPayload, 5000, 'https://grid402.online/api/v1/forex');
t_assert('Underpayment (1000 vs 5000) continua bloqueado com 402', empty($resUnder['valid']) && ($resUnder['code'] ?? 0) === 402);

$wrongAssetPayload = t_payload(['accepted' => ['asset' => '0x' . str_repeat('de', 20)]]);
$resWrongAsset = grid402_verify_exact_payment($wrongAssetPayload, 500, 'https://grid402.online/api/v1/forex');
t_assert('Ativo incorreto continua bloqueado com 402', empty($resWrongAsset['valid']) && ($resWrongAsset['code'] ?? 0) === 402);

// -----------------------------------------------------------------------------
// 3. Dica especifica para endereco EVM de 40 hex no lugar de txHash
// -----------------------------------------------------------------------------
echo "\n[3] Validacao de endereco EVM de 40 hex como prova:\n";
$addrVal = grid402_validate_payment('0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90');
t_assert('Endereco 40-hex retorna 400 invalid_proof', empty($addrVal['valid']) && ($addrVal['code'] ?? 0) === 400 && ($addrVal['category'] ?? '') === 'invalid_proof');
t_assert('Mensagem cita endereco EVM de 40 hex e formatos aceitos', str_contains((string) ($addrVal['reason'] ?? ''), '40-hex EVM wallet address') && str_contains((string) ($addrVal['reason'] ?? ''), 'PAYMENT-SIGNATURE') && str_contains((string) ($addrVal['reason'] ?? ''), 'demo'));

// -----------------------------------------------------------------------------
// 4. Diagnostico estrutural Zero-PII (proof_diag) e round-trip em requests.jsonl
// -----------------------------------------------------------------------------
echo "\n[4] Diagnostico estrutural Zero-PII (proof_diag):\n";
$secretPass = 'pass_g402_secretid1234_abcdef987654';
$secretTx = '0x' . str_repeat('ab', 32);
$secretWallet = '0x1234567890abcdef1234567890abcdef12345678';
$diagAddr = grid402_summarize_rejected_proof_shape('x-payment', $secretWallet, 'curl/8.4.0');
$diagPass = grid402_summarize_rejected_proof_shape('x-session-pass', $secretPass, 'python-httpx/0.27.0');
$diagJson = grid402_summarize_rejected_proof_shape('payment-signature', $b64Normal, 'node-fetch/3.0');
$diagJwt = grid402_summarize_rejected_proof_shape('authorization_bearer', 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0In0.sigpart123', 'MyAgent/1.0');

t_assert('proof_diag classifica evm_address_40hex sem vazar o endereco', str_contains($diagAddr, 'evm_address_40hex') && !str_contains($diagAddr, $secretWallet) && str_contains($diagAddr, 'curl/8.4.0'), $diagAddr);
t_assert('proof_diag classifica pass_token sem vazar o token', str_contains($diagPass, 'pass_token') && !str_contains($diagPass, 'secretid1234') && !str_contains($diagPass, 'abcdef987654'), $diagPass);
t_assert('proof_diag classifica x402_json com keys e rede sem vazar signature/from', str_contains($diagJson, 'x402_json[keys=') && str_contains($diagJson, 'net=eip155:8453') && !str_contains($diagJson, str_repeat('11', 16)), $diagJson);
t_assert('proof_diag classifica jwt_3parts', str_contains($diagJwt, 'jwt_3parts'), $diagJwt);

grid402_log_request([
    'service' => 'asn',
    'status' => 400,
    'payment_source' => 'unknown',
    'error' => 'PaymentFailed: Invalid payment proof: ' . $secretTx,
    'proof_diag' => $diagAddr,
]);
$readFailures = grid402_read_recent_payment_failures(5);
t_assert('grid402_read_recent_payment_failures preserva proof_diag e redige hex longo', !empty($readFailures) && ($readFailures[0]['proof_diag'] ?? '') === $diagAddr && str_contains((string) ($readFailures[0]['error'] ?? ''), '0x[REDACTED]'), json_encode($readFailures));

// -----------------------------------------------------------------------------
// 5. Servidor HTTP local: HTTP 400 com PAYMENT-REQUIRED + novos aliases de header
// -----------------------------------------------------------------------------
echo "\n[5] Servidor HTTP local (400 com challenge + aliases de header):\n";
$serverStorage = $tmpDir . DIRECTORY_SEPARATOR . 'http_storage';
mkdir($serverStorage, 0775, true);
$server = grid402_start_server([
    'docroot' => __DIR__ . '/../public_html',
    'router' => __DIR__ . '/router.php',
    'probe' => '/status',
    'env' => [
        'GRID402_STORAGE_DIR' => $serverStorage,
        'GRID402_FILE_REQUEST_LOGS' => $serverStorage . DIRECTORY_SEPARATOR . 'requests.jsonl',
        'GRID402_DISABLE_TELEGRAM' => '1',
    ],
]);

try {
    $httpCall = static function (string $url, array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 6,
        ]);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return [
            'code' => $code,
            'headers' => substr($raw, 0, $hdrSize),
            'body' => substr($raw, $hdrSize),
        ];
    };

    $r400 = $httpCall($server['base_url'] . '/api/v1/asn?ip=1.1.1.1', ['X-Payment: 0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90']);
    $json400 = json_decode($r400['body'], true);
    t_assert('HTTP 400 devolve header PAYMENT-REQUIRED para recuperacao do agente', $r400['code'] === 400 && stripos($r400['headers'], 'PAYMENT-REQUIRED:') !== false);
    t_assert('Corpo HTTP 400 inclui accepts[], x402Version=2 e upgrade_to_production', is_array($json400) && ($json400['x402Version'] ?? 0) === 2 && !empty($json400['accepts']) && !empty($json400['upgrade_to_production']));

    $rSigAlias = $httpCall($server['base_url'] . '/api/v1/asn?ip=1.1.1.1', ['X-Payment-Signature: demo']);
    t_assert('Alias X-Payment-Signature: demo autentica sandbox (HTTP 200)', $rSigAlias['code'] === 200);

    $rPayAlias = $httpCall($server['base_url'] . '/api/v1/forex', ['Payment: demo']);
    t_assert('Alias Payment: demo autentica sandbox (HTTP 200)', $rPayAlias['code'] === 200);

    $prettyB64 = base64_encode("{\n  \"x402Version\": 2\n}"); // comeca com "ew"
    $rBearerPretty = $httpCall($server['base_url'] . '/api/v1/forex', ['Authorization: Bearer ' . $prettyB64]);
    $jsonBearer = json_decode($rBearerPretty['body'], true);
    t_assert('Authorization: Bearer <base64 JSON ew...> entra no fluxo x402 (400 sem payload, nao 402 vazio)', $rBearerPretty['code'] === 400 && ($jsonBearer['error'] ?? '') === 'PaymentFailed');
} finally {
    grid402_stop_server($server);
    t_rmdir($serverStorage);
    t_rmdir($tmpDir);
}

t_finish('RESULTADO FINAL (correcoes de vazamento x402)');
