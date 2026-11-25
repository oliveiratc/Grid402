<?php
/**
 * Testes unitários e estruturais para:
 *  1) Atribuição de telemetria do canal MCP (`payment_sources['mcp']`)
 *  2) Resposta HTTP 402 do `/api/v1/pass` sem instrução contraditória de `X-Payment: demo`
 *  3) Onboarding pós-compra em `public_html/pass.php` e header `X-Grid402-Channel` em `mcp/tools.mjs`
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/metrics.php';
require_once __DIR__ . '/../public_html/x402.php';

$passed = 0;
$failed = 0;

function t_assert(string $label, bool $cond, string $detail = ''): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
    }
}

echo "=== Teste: Canal MCP + HTTP 402 do /api/v1/pass + Onboarding pass.php ===\n";

$backupMetricsFile = GRID402_FILE_METRICS . '.bak_mcp_test';
$hadMetrics = file_exists(GRID402_FILE_METRICS);
if ($hadMetrics) {
    copy(GRID402_FILE_METRICS, $backupMetricsFile);
}

$tmpDir = sys_get_temp_dir() . '/grid402_mcp_pass_test_' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0775, true);

try {
    grid402_atomic_write(GRID402_FILE_METRICS, grid402_default_metrics());

    // 1. Telemetria: chamada paga via MCP com Session Pass (price_usdc = 0.0) deve contar em mcp, total_paid_requests e service_paid_counts
    grid402_record_telemetry(
        'forex',
        200,
        true,
        'pass_g402_demo1234',
        '127.0.0.1',
        0.0,
        null,
        ['payment_source' => 'mcp', 'channel' => 'mcp', 'settlement_source' => 'pass', 'defer_latency_sample' => true]
    );

    // 2. Telemetria: chamada sandbox via canal MCP deve contar em sandbox e NÃO em mcp nem total_paid_requests
    grid402_record_telemetry(
        'forex',
        200,
        false,
        'demo',
        '127.0.0.1',
        0.0,
        'sandbox',
        ['payment_source' => 'sandbox', 'channel' => 'mcp', 'defer_latency_sample' => true]
    );

    $m = grid402_get_metrics();
    t_assert(
        'MCP pago com Session Pass incrementa payment_sources[mcp] para 1',
        ($m['payment_sources']['mcp'] ?? 0) === 1,
        json_encode($m['payment_sources'] ?? [])
    );
    t_assert(
        'MCP sandbox incrementa payment_sources[sandbox] sem contaminar mcp',
        ($m['payment_sources']['sandbox'] ?? 0) === 1 && ($m['payment_sources']['mcp'] ?? 0) === 1,
        json_encode($m['payment_sources'] ?? [])
    );
    t_assert(
        'MCP pago incrementa total_paid_requests e service_paid_counts[forex]',
        ($m['total_paid_requests'] ?? 0) === 1 && ($m['service_paid_counts']['forex'] ?? 0) === 1,
        json_encode(['paid' => $m['total_paid_requests'] ?? 0, 'forex' => $m['service_paid_counts']['forex'] ?? 0])
    );

    // 3. HTTP 402 de /api/v1/pass vs endpoint padrão (forex) via subprocesso PHP CLI
    $projectRoot = realpath(__DIR__ . '/..');
    $run402Probe = static function (string $service, string $price, int $raw) use ($projectRoot, $tmpDir): ?array {
        $probeScript = $tmpDir . '/probe_' . $service . '.php';
        $phpCode = "<?php\n"
            . "define('GRID402_EXEC', true);\n"
            . "require " . var_export($projectRoot . '/public_html/config.php', true) . ";\n"
            . "require " . var_export($projectRoot . '/public_html/x402.php', true) . ";\n"
            . "ob_start();\n"
            . "@grid402_respond_402(" . var_export($service, true) . ", null, " . var_export($price, true) . ", " . $raw . ");\n"
            . "\$out = ob_get_clean();\n"
            . "echo \$out;\n";
        file_put_contents($probeScript, $phpCode);
        $rawOut = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probeScript));
        @unlink($probeScript);
        $decoded = is_string($rawOut) ? @json_decode($rawOut, true) : null;
        return is_array($decoded) ? $decoded : null;
    };

    $pass402 = $run402Probe('pass', '2.0000', 2000000);
    t_assert(
        '/api/v1/pass 402 retorna sandbox_test.supported === false',
        is_array($pass402) && (($pass402['sandbox_test']['supported'] ?? true) === false),
        json_encode($pass402['sandbox_test'] ?? null)
    );
    t_assert(
        '/api/v1/pass 402 action_required não sugere X-Payment: demo e orienta /api/v1/pass-emitir',
        is_array($pass402)
            && !str_contains((string) ($pass402['action_required'] ?? ''), 'X-Payment: demo')
            && str_contains((string) ($pass402['action_required'] ?? ''), '/api/v1/pass-emitir'),
        (string) ($pass402['action_required'] ?? '')
    );

    $forex402 = $run402Probe('forex', '0.0050', 5000);
    t_assert(
        '/api/v1/forex 402 preserva sandbox_test.header === X-Payment: demo',
        is_array($forex402) && (($forex402['sandbox_test']['header'] ?? '') === 'X-Payment: demo'),
        json_encode($forex402['sandbox_test'] ?? null)
    );

    // 4. Verificação estrutural de pass.php, mcp.php, index.php e mcp/tools.mjs
    $passPhp = (string) file_get_contents($projectRoot . '/public_html/pass.php');
    $mcpPhp = (string) file_get_contents($projectRoot . '/public_html/mcp.php');
    $indexPhp = (string) file_get_contents($projectRoot . '/public_html/index.php');
    $toolsMjs = (string) file_get_contents($projectRoot . '/mcp/tools.mjs');

    t_assert(
        'pass.php contém onboarding pós-compra completo (testIssuedPassNow, mp-test-result, Copy cURL + MCP, GRID402_SESSION_PASS=)',
        str_contains($passPhp, 'testIssuedPassNow')
            && str_contains($passPhp, 'mp-test-result')
            && str_contains($passPhp, 'Copy cURL + MCP')
            && str_contains($passPhp, 'GRID402_SESSION_PASS='),
        'Elementos pós-compra ausentes em pass.php'
    );
    t_assert(
        'mcp/tools.mjs envia header X-Grid402-Channel: mcp',
        str_contains($toolsMjs, "'X-Grid402-Channel': 'mcp'")
    );
    t_assert(
        'mcp.php e index.php atribuem payment_source=mcp para chamadas pagas reais do canal MCP',
        str_contains($mcpPhp, "\$telemetryPaymentSource = \$isRealPayment ? 'mcp'")
            && str_contains($indexPhp, 'HTTP_X_GRID402_CHANNEL')
            && str_contains($indexPhp, "\$telemetryPaymentSource = (\$isRealPayment && \$isMcpClient")
    );
} finally {
    if ($hadMetrics && file_exists($backupMetricsFile)) {
        copy($backupMetricsFile, GRID402_FILE_METRICS);
        @unlink($backupMetricsFile);
    }
    foreach (glob($tmpDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpDir);
}

echo "\nResultado: {$passed} passou | {$failed} falhou\n";
exit($failed > 0 ? 1 : 0);
