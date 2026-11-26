<?php
/**
 * Grid402 // Bateria de Regressão: Auditoria de Bloqueios de Faturamento
 * Cobre:
 *  1. CORS preflight OPTIONS (HTTP 204 + PAYMENT-SIGNATURE) em /api/v1/* e /mcp
 *  2. HTTP 402 (e nao 400) ao sondar /api/v1/cnpj, sanctions, simples, compliance, kyb-audit sem params
 *  3. Validacao previa de parametros (400 sem debitar Session Pass)
 *  4. Precos unificados 0.0150 USDC (15000 raw) em domain-audit e market-snapshot
 *  5. Previews ricos no 402 para forex, crypto, dns, ssl, asn, domain-audit, kyb-audit, market-snapshot
 *  6. Nao-duplicacao de receita no consumo de Session Pass (total_revenue_usdc = 0 no consumo)
 *  7. /mcp: mcp_accepts_unpaid com precos corretos + execucao com Session Pass e _oracle_proof sem TypeError
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_server.php';

$tmpDir = sys_get_temp_dir() . '/grid402_rev_audit_' . getmypid() . '_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0775, true);

putenv('GRID402_STORAGE_DIR=' . $tmpDir);
define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/metrics.php';
require_once __DIR__ . '/../public_html/services/pass.php';

$passed = 0;
$failed = 0;
function rev_check(string $label, bool $ok, string $detail = ''): void {
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

function rev_http(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$respHeaders) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $rawBody = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'headers' => $respHeaders, 'body' => $rawBody];
}

// Emite um Session Pass de teste (Starter: 400 creditos)
$txTest = '0x' . bin2hex(random_bytes(32));
$passIssued = grid402_issue_session_pass($txTest, 2000000, false);
$passToken = (string) ($passIssued['pass_token'] ?? '');

$server = grid402_start_server([
    'docroot' => __DIR__ . '/../public_html',
    'probe' => '/',
    'env' => ['GRID402_STORAGE_DIR' => $tmpDir],
]);
$baseUrl = $server['base_url'];

try {
    echo "=== [1] CORS Preflight OPTIONS (204 + PAYMENT-SIGNATURE) ===\n";
    $optApi = rev_http('OPTIONS', "{$baseUrl}/?api=forex", [
        'Origin: https://example.com',
        'Access-Control-Request-Method: GET',
        'Access-Control-Request-Headers: PAYMENT-SIGNATURE, X-Payment',
    ]);
    rev_check('OPTIONS /?api=forex retorna HTTP 204', $optApi['code'] === 204, 'code=' . $optApi['code']);
    $allowHdrs = strtolower($optApi['headers']['access-control-allow-headers'] ?? '');
    rev_check('CORS permite PAYMENT-SIGNATURE e X-Payment', str_contains($allowHdrs, 'payment-signature') && str_contains($allowHdrs, 'x-payment'));

    $optMcp = rev_http('OPTIONS', "{$baseUrl}/mcp.php", [
        'Origin: https://example.com',
        'Access-Control-Request-Method: POST',
    ]);
    rev_check('OPTIONS /mcp.php retorna HTTP 204', $optMcp['code'] === 204, 'code=' . $optMcp['code']);

    echo "\n=== [2] Discovery 402 sem parametros em CNPJ / Sanctions / Simples / Compliance / KYB ===\n";
    foreach (['cnpj', 'sanctions', 'simples', 'compliance', 'kyb-audit'] as $ep) {
        $r402 = rev_http('GET', "{$baseUrl}/?api={$ep}", ['Accept: application/json']);
        $j402 = json_decode($r402['body'], true) ?: [];
        rev_check("GET /?api={$ep} sem params retorna HTTP 402 (nao 400)", $r402['code'] === 402 && !empty($j402['preview']), 'code=' . $r402['code']);
    }

    echo "\n=== [3] Preco 0.0150 USDC e previews ricos nos 8 servicos ===\n";
    foreach (['domain-audit', 'market-snapshot'] as $ep3) {
        $r3 = rev_http('GET', "{$baseUrl}/?api={$ep3}", ['Accept: application/json']);
        $j3 = json_decode($r3['body'], true) ?: [];
        $amt = (string) ($j3['accepts'][0]['amount'] ?? $j3['accepts'][0]['maxAmountRequired'] ?? '');
        rev_check("{$ep3} retorna 402 com price_usdc = 0.0150 (15000 raw)", $r3['code'] === 402 && ($j3['price_usdc'] ?? '') === '0.0150' && $amt === '15000', 'price=' . ($j3['price_usdc'] ?? '') . ', amt=' . $amt);
    }
    foreach (['forex', 'crypto', 'dns', 'ssl', 'asn', 'domain-audit', 'kyb-audit', 'market-snapshot'] as $epPrev) {
        $rp = rev_http('GET', "{$baseUrl}/?api={$epPrev}", ['Accept: application/json']);
        $jp = json_decode($rp['body'], true) ?: [];
        rev_check("{$epPrev} possui preview estruturado e locked_fields", !empty($jp['preview']) && !empty($jp['locked_fields']));
    }

    echo "\n=== [4] Validacao previa sem debitar Session Pass + Nao-duplicacao de receita ===\n";
    $badCnpj = rev_http('GET', "{$baseUrl}/?api=cnpj&cnpj=123", ['Accept: application/json', "X-Payment: {$passToken}"]);
    rev_check('CNPJ invalido com Session Pass retorna 400', $badCnpj['code'] === 400, 'code=' . $badCnpj['code']);
    $statusAfterBad = grid402_get_session_pass_status($passToken);
    rev_check('CNPJ invalido NAO debitou creditos do Session Pass (permanece 400)', ($statusAfterBad['credits_remaining'] ?? -1) === 400);

    $goodForex = rev_http('GET', "{$baseUrl}/?api=forex", ['Accept: application/json', "X-Payment: {$passToken}"]);
    rev_check('GET /?api=forex com Session Pass retorna HTTP 200', $goodForex['code'] === 200, 'code=' . $goodForex['code']);
    $statusAfterForex = grid402_get_session_pass_status($passToken);
    rev_check('Forex debitou 1 credito (restam 399)', ($statusAfterForex['credits_remaining'] ?? -1) === 399);

    $metricsAfterPass = grid402_get_metrics();
    rev_check('Consumo via Session Pass conta em total_paid_requests sem duplicar total_revenue_usdc',
        ($metricsAfterPass['total_paid_requests'] ?? 0) === 1 && abs((float)($metricsAfterPass['total_revenue_usdc'] ?? 0.0)) < 0.0001
    );

    echo "\n=== [5] Hosted MCP (/mcp.php): precos corretos, auto-trial 1/dia e execucao com Session Pass sem TypeError ===\n";
    // 1a chamada sem credencial recebe auto-trial 1/dia (mcp_sandbox) com _trial_notice
    $mcpAutoTrial = rev_http('POST', "{$baseUrl}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_domain_audit', 'arguments' => ['domain' => 'base.org']],
    ]));
    $mcpTrialJson = json_decode($mcpAutoTrial['body'], true) ?: [];
    $mcpTrialPayload = json_decode((string) ($mcpTrialJson['result']['content'][0]['text'] ?? ''), true) ?: [];
    rev_check(
        'MCP 1a chamada sem pagamento concede auto-trial 1/dia (mcp_sandbox) com _trial_notice MCPTRY50',
        empty($mcpTrialJson['result']['isError']) &&
        !empty($mcpTrialPayload['sandbox']) &&
        ($mcpTrialPayload['_trial_notice']['mode'] ?? '') === 'mcp_sandbox',
        'body=' . substr($mcpAutoTrial['body'], 0, 220)
    );

    // 2a chamada sem pagamento no mesmo dia retorna formato @x402/mcp com 0.0150 USDC (15000)
    $mcpUnpaid = rev_http('POST', "{$baseUrl}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_domain_audit', 'arguments' => ['domain' => 'base.org']],
    ]));
    $mcpUnpaidJson = json_decode($mcpUnpaid['body'], true) ?: [];
    $mcpUnpaidErr = $mcpUnpaidJson['result']['_meta']['x402/error'] ?? [];
    rev_check(
        'MCP domain_audit 2a chamada sem pagamento retorna formato @x402/mcp (isError + _meta[x402/error] + PAYMENT-REQUIRED) com 0.0150 USDC (15000)',
        ($mcpUnpaidJson['result']['isError'] ?? false) === true &&
        ($mcpUnpaidErr['price_usdc'] ?? '') === '0.0150' &&
        ($mcpUnpaidErr['price_raw_units'] ?? 0) === 15000 &&
        !empty($mcpUnpaid['headers']['payment-required']),
        'body=' . substr($mcpUnpaid['body'], 0, 220)
    );

    $mcpPassUnpaid = rev_http('POST', "{$baseUrl}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_pass', 'arguments' => []],
    ]));
    $mcpPassJson = json_decode($mcpPassUnpaid['body'], true) ?: [];
    $mcpPassErr = $mcpPassJson['result']['_meta']['x402/error'] ?? [];
    rev_check(
        'MCP grid402_pass sem pagamento retorna formato @x402/mcp com 2.0000 USDC (2000000)',
        ($mcpPassJson['result']['isError'] ?? false) === true &&
        ($mcpPassErr['price_usdc'] ?? '') === '2.0000' &&
        ($mcpPassErr['price_raw_units'] ?? 0) === 2000000 &&
        !empty($mcpPassUnpaid['headers']['payment-required']),
        'body=' . substr($mcpPassUnpaid['body'], 0, 220)
    );

    $mcpPaid = rev_http('POST', "{$baseUrl}/mcp.php", [
        'Content-Type: application/json',
        "X-Payment: {$passToken}",
    ], json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_forex', 'arguments' => []],
    ]));
    $mcpPaidJson = json_decode($mcpPassUnpaid['body'], true);
    $mcpPaidDecoded = json_decode($mcpPaid['body'], true) ?: [];
    $mcpToolText = (string) ($mcpPaidDecoded['result']['content'][0]['text'] ?? '');
    $mcpToolPayload = json_decode($mcpToolText, true) ?: [];
    rev_check('MCP grid402_forex via X-Payment Session Pass retorna HTTP 200 sem TypeError e com _oracle_proof',
        $mcpPaid['code'] === 200 && isset($mcpToolPayload['_meta'], $mcpToolPayload['_oracle_proof'])
    );

    echo "\n=== [6] Web3 1-Click JS valores corretos na UI ===\n";
    $indexHtml = (string) file_get_contents(__DIR__ . '/../public_html/index.php');
    rev_check('index.php nao contem amountRaw = 500000 para pass', !str_contains($indexHtml, 'amountRaw = 500000'));
    rev_check('index.php contem atalho Pix R$ 12 no Playground Unlock Card', str_contains($indexHtml, 'btn-playground-pix-pay'));
} finally {
    grid402_stop_server($server);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($tmpDir);
}

echo "\n=================================================================\n";
echo "  RESULTADO: {$passed} aprovados, {$failed} falhas\n";
echo "=================================================================\n";
exit($failed > 0 ? 1 : 0);
