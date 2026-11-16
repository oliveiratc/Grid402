<?php
/**
 * Grid402 // Suite de Testes de Concorrencia, Trava Atomica, Observabilidade e Carga
 * Cobre todos os cenarios de concorrencia, Zero-PII JSONL, p50/p95 e X-Request-Id
 * da change OpenSpec: harden-reliability-mcp-and-observability
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpDir = t_bootstrap('concurrency_and_load');
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/x402.php';
require_once __DIR__ . '/../public_html/facilitator.php';
require_once __DIR__ . '/../public_html/logger.php';
require_once __DIR__ . '/../public_html/metrics.php';

echo "=================================================================\n";
echo "  GRID402 - CONCORRENCIA MULTI-PROCESSO, LOGS ZERO-PII E CARGA\n";
echo "=================================================================\n\n";

$phpBin = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';
$projectRoot = dirname(__DIR__);

/**
 * Executa N processos PHP filhos em paralelo sincronizados pelo mesmo timestamp-alvo.
 *
 * @return array<int, array{exit: int, stdout: string, stderr: string, json: ?array}>
 */
function run_parallel_php_workers(string $phpBin, string $workerFile, string $workerScript, int $count, array $commonArgs): array
{
    file_put_contents($workerFile, "<?php\n" . $workerScript . "\n");
    $goAt = sprintf('%.6f', microtime(true) + 0.35);
    $procs = [];
    $pipesList = [];

    for ($i = 0; $i < $count; $i++) {
        $cmd = array_merge([$phpBin, $workerFile, (string)$i, $goAt], $commonArgs);
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            @fclose($pipes[0]);
            $procs[$i] = $proc;
            $pipesList[$i] = $pipes;
        }
    }

    $results = [];
    foreach ($procs as $i => $proc) {
        $stdout = (string) stream_get_contents($pipesList[$i][1]);
        $stderr = (string) stream_get_contents($pipesList[$i][2]);
        @fclose($pipesList[$i][1]);
        @fclose($pipesList[$i][2]);
        $exit = proc_close($proc);
        $decoded = json_decode(trim($stdout), true);
        if (!is_array($decoded) && $stderr !== '') {
            echo "  [WARN] worker {$i} stderr: " . trim($stderr) . "\n";
        }
        $results[$i] = [
            'exit' => $exit,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    return $results;
}

try {
    // -------------------------------------------------------------------------
    // [1/5] Corrida Multi-Processo no Ultimo Credito do Session Pass
    // -------------------------------------------------------------------------
    echo "[1/5] Contencao multi-processo no ultimo credito do Session Pass (12 workers):\n";
    $txPass = '0x' . bin2hex(random_bytes(32));
    $issued = grid402_issue_session_pass($txPass, 5000, false); // 5000 raw = 1 credito exato
    $passToken = (string) ($issued['pass_token'] ?? '');
    t_assert('Session Pass com 1 credito exato emitido', $passToken !== '' && ($issued['credits_remaining'] ?? 0) === 1);

    $passWorker = <<<'PHP'
$idx = (int)$argv[1];
$goAt = (float)$argv[2];
$tmpDir = $argv[3];
$root = $argv[4];
$token = $argv[5];

require_once $root . '/tests/helpers_x402_exact.php';
t_bootstrap('worker', $tmpDir);
require_once $root . '/public_html/services/pass.php';

$waitUs = (int)(($goAt - microtime(true)) * 1000000);
if ($waitUs > 0) {
    usleep($waitUs);
}
$res = grid402_consume_session_pass($token, 5000, 'workdays');
echo json_encode(['worker' => $idx, 'valid' => !empty($res['valid']), 'code' => $res['code'] ?? 200, 'reason' => $res['reason'] ?? null]);
PHP;

    $passResults = run_parallel_php_workers($phpBin, $tmpDir . '/worker_pass.php', $passWorker, 12, [$tmpDir, $projectRoot, $passToken]);
    $passSuccess = 0;
    $passRejected402 = 0;
    foreach ($passResults as $r) {
        if (is_array($r['json']) && ($r['json']['valid'] ?? false) === true) {
            $passSuccess++;
        } elseif (is_array($r['json']) && (int)($r['json']['code'] ?? 0) === 402) {
            $passRejected402++;
        }
    }
    $afterPass = grid402_get_session_pass_status($passToken);
    t_assert('Exatamente 1 worker consumiu o ultimo credito (zero double-spend)', $passSuccess === 1, "Success={$passSuccess}");
    t_assert('Exatamente 11 workers receberam recusa HTTP 402', $passRejected402 === 11, "Rejected402={$passRejected402}");
    t_assert('Saldo final do Session Pass eh estritamente 0 e usage_count === 1', ($afterPass['credits_remaining'] ?? -1) === 0 && ($afterPass['usage_count'] ?? -1) === 1);

    // -------------------------------------------------------------------------
    // [2/5] Trava Atomica Anti-Replay Concorrente (tx_hash e payer:nonce)
    // -------------------------------------------------------------------------
    echo "\n[2/5] Contencao concorrente anti-replay em tx_hash e payer:nonce (10 workers cada):\n";
    $sameTx = '0x' . bin2hex(random_bytes(32));
    $txWorker = <<<'PHP'
$idx = (int)$argv[1];
$goAt = (float)$argv[2];
$tmpDir = $argv[3];
$root = $argv[4];
$txHash = $argv[5];

require_once $root . '/tests/helpers_x402_exact.php';
t_bootstrap('worker', $tmpDir);
require_once $root . '/public_html/x402.php';

$waitUs = (int)(($goAt - microtime(true)) * 1000000);
if ($waitUs > 0) {
    usleep($waitUs);
}
$claimed = grid402_claim_transaction_lock($txHash);
echo json_encode(['worker' => $idx, 'claimed' => $claimed]);
PHP;

    $txResults = run_parallel_php_workers($phpBin, $tmpDir . '/worker_tx.php', $txWorker, 10, [$tmpDir, $projectRoot, $sameTx]);
    $txWins = 0;
    $txLosses = 0;
    foreach ($txResults as $r) {
        if (is_array($r['json'])) {
            if (($r['json']['claimed'] ?? false) === true) {
                $txWins++;
            } else {
                $txLosses++;
            }
        }
    }
    t_assert('Exatamente 1 worker conquistou trava da tx_hash (9 bloqueados por anti-replay)', $txWins === 1 && $txLosses === 9, "Wins={$txWins}, Losses={$txLosses}");

    $sameNonceKey = '0x1111111111111111111111111111111111111111:0x' . bin2hex(random_bytes(32));
    $nonceWorker = <<<'PHP'
$idx = (int)$argv[1];
$goAt = (float)$argv[2];
$tmpDir = $argv[3];
$root = $argv[4];
$nonceKey = $argv[5];

require_once $root . '/tests/helpers_x402_exact.php';
t_bootstrap('worker', $tmpDir);
require_once $root . '/public_html/facilitator.php';

$waitUs = (int)(($goAt - microtime(true)) * 1000000);
if ($waitUs > 0) {
    usleep($waitUs);
}
$claimed = grid402_claim_nonce($nonceKey);
echo json_encode(['worker' => $idx, 'claimed' => $claimed]);
PHP;

    $nonceResults = run_parallel_php_workers($phpBin, $tmpDir . '/worker_nonce.php', $nonceWorker, 10, [$tmpDir, $projectRoot, $sameNonceKey]);
    $nonceWins = 0;
    $nonceLosses = 0;
    foreach ($nonceResults as $r) {
        if (is_array($r['json'])) {
            if (($r['json']['claimed'] ?? false) === true) {
                $nonceWins++;
            } else {
                $nonceLosses++;
            }
        }
    }
    t_assert('Exatamente 1 worker conquistou trava do payer:nonce (9 bloqueados)', $nonceWins === 1 && $nonceLosses === 9, "Wins={$nonceWins}, Losses={$nonceLosses}");

    // -------------------------------------------------------------------------
    // [3/5] Logger Estruturado Zero-PII + Rotacao Automatica (.1)
    // -------------------------------------------------------------------------
    echo "\n[3/5] Validando Logger JSONL Zero-PII e rotacao automatica de arquivo:\n";
    $logFile = $tmpDir . '/unit_requests.jsonl';
    putenv('GRID402_FILE_REQUEST_LOGS=' . $logFile);
    putenv('GRID402_LOG_MAX_BYTES=1400');

    $secretPass = 'pass_g402_live123456_abcdef0123456789';
    $secretTx = '0x' . str_repeat('ab', 32);
    $rawIp = '203.0.113.42';

    $logged = grid402_log_request([
        'method' => 'GET',
        'service' => 'cnpj',
        'status' => 403,
        'latency_ms' => 14.25,
        'payment_source' => 'pass',
        'ip' => $rawIp,
        'idempotent_replay' => false,
        'error' => "Token {$secretPass} falhou para tx {$secretTx}",
    ]);
    t_assert('grid402_log_request gravou primeira linha JSONL', $logged && is_file($logFile));
    $firstContent = (string) file_get_contents($logFile);
    $firstEntry = json_decode(trim($firstContent), true);
    t_assert('Entrada JSONL contem campos obrigatorios', is_array($firstEntry) && isset($firstEntry['ts'], $firstEntry['request_id'], $firstEntry['service'], $firstEntry['status'], $firstEntry['latency_ms'], $firstEntry['payment_source'], $firstEntry['ip_hmac']));
    t_assert('Token pass_g402_... redatado no log', !str_contains($firstContent, 'live123456') && str_contains($firstContent, 'pass_g402_[REDACTED]'));
    t_assert('Tx hash longa redatada no log', !str_contains($firstContent, 'ababababababababababab') && str_contains($firstContent, '0x[REDACTED]'));
    t_assert('IP literal nunca e gravado no log (somente ip_hmac de 12 hex)', !str_contains($firstContent, $rawIp) && preg_match('/^[a-f0-9]{12}$/', (string)($firstEntry['ip_hmac'] ?? '')) === 1);

    for ($n = 0; $n < 20; $n++) {
        grid402_log_request([
            'method' => 'GET',
            'service' => 'workdays',
            'status' => 200,
            'latency_ms' => 5.5 + ($n * 0.3),
            'payment_source' => 'sandbox',
            'ip' => '198.51.100.' . ($n + 1),
        ]);
    }
    t_assert('Rotacao automatica criou backup .1 ao ultrapassar limite de bytes', is_file($logFile . '.1') && filesize($logFile . '.1') >= 1400);
    t_assert('Arquivo ativo apos rotacao permanece compacto', is_file($logFile) && filesize($logFile) < 1600, 'Size=' . filesize($logFile));

    // Limpa overrides locais antes de testar servidor HTTP
    putenv('GRID402_FILE_REQUEST_LOGS');
    putenv('GRID402_LOG_MAX_BYTES');

    // -------------------------------------------------------------------------
    // [4/5] Percentis de Latencia (p50/p95) e Buffer Circular (max 200)
    // -------------------------------------------------------------------------
    echo "\n[4/5] Validando janela circular (max 200) e calculo p50/p95 em metrics.php:\n";
    for ($i = 1; $i <= 210; $i++) {
        grid402_record_latency_sample((float)$i);
    }
    $storedMetrics = grid402_get_metrics();
    $sampleCount = count($storedMetrics['latency_samples_ms'] ?? []);
    t_assert('Buffer circular mantem estritamente no maximo 200 amostras', $sampleCount === 200, "Count={$sampleCount}");
    t_assert('Amostras mais antigas (1..10) foram descartadas; primeira amostra eh 11', (float)($storedMetrics['latency_samples_ms'][0] ?? 0) === 11.0);

    $analytics = grid402_compute_admin_analytics($storedMetrics);
    t_assert('Analytics expoe latency_p50_ms, latency_p95_ms e latency_samples_count', isset($analytics['latency_p50_ms'], $analytics['latency_p95_ms'], $analytics['latency_samples_count']));
    t_assert('Percentis p50 e p95 refletem distribuicao esperada (11..210)', $analytics['latency_p50_ms'] >= 110.0 && $analytics['latency_p50_ms'] <= 111.0 && $analytics['latency_p95_ms'] >= 199.0 && $analytics['latency_p95_ms'] <= 201.0, "p50={$analytics['latency_p50_ms']} p95={$analytics['latency_p95_ms']}");

    // -------------------------------------------------------------------------
    // [5/5] Carga HTTP Concorrente via curl_multi (X-Request-Id + p50/p95/p99)
    // -------------------------------------------------------------------------
    echo "\n[5/5] Executando benchmark HTTP concorrente via curl_multi (30 requisicoes):\n";
    $metricsBackupPath = $projectRoot . '/public_html/private_data/metrics.json';
    $hadMetricsBackup = is_file($metricsBackupPath);
    $metricsBackupRaw = $hadMetricsBackup ? (string) file_get_contents($metricsBackupPath) : null;

    $httpLogFile = $tmpDir . '/http_requests.jsonl';
    $server = null;
    try {
        $server = grid402_start_server([
            'docroot' => $projectRoot . '/public_html',
            'router' => $projectRoot . '/public_html/index.php',
            'probe' => '/status',
            'env' => [
                'GRID402_STORAGE_DIR' => $tmpDir,
                'GRID402_FILE_REQUEST_LOGS' => $httpLogFile,
                'GRID402_LOG_MAX_BYTES' => '4096',
            ],
        ]);
        $baseUrl = $server['base_url'];

        // Prepara 30 requisicoes concorrentes em 3 rajadas de 10 (evita esgotamento de worker unico em php -S no Windows)
        $targets = [
            ['path' => '/status', 'expected' => 200, 'req_id' => 'bench-custom-001'],
            ['path' => '/about', 'expected' => 200, 'req_id' => null],
            ['path' => '/api/v1/workdays?year=2026&month=11&day=16', 'expected' => 402, 'req_id' => null],
        ];

        $latencies = [];
        $okCount = 0;
        $headerReqIdCount = 0;
        $customPreserved = false;
        $totalRequests = 30;
        $batchSize = 10;
        $benchStart = microtime(true);

        for ($offset = 0; $offset < $totalRequests; $offset += $batchSize) {
            $mh = curl_multi_init();
            $handles = [];
            for ($j = 0; $j < $batchSize; $j++) {
                $idx = $offset + $j;
                $spec = $targets[$idx % count($targets)];
                $ch = curl_init($baseUrl . $spec['path']);
                $headers = ['Accept: application/json'];
                if ($idx === 0 && $spec['req_id'] !== null) {
                    $headers[] = 'X-Request-Id: ' . $spec['req_id'];
                }
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$idx] = ['ch' => $ch, 'spec' => $spec];
            }

            $active = null;
            do {
                $mrc = curl_multi_exec($mh, $active);
                if ($active > 0) {
                    curl_multi_select($mh, 0.05);
                }
            } while ($active > 0 && ($mrc === CURLM_OK || $mrc === CURLM_CALL_MULTI_PERFORM));

            foreach ($handles as $idx => $item) {
                $ch = $item['ch'];
                $spec = $item['spec'];
                $raw = (string) curl_multi_getcontent($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $totalTimeMs = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000.0;
                $respHeaders = substr($raw, 0, $headerSize);

                if ($code === $spec['expected']) {
                    $okCount++;
                }
                if (preg_match('/^X-Request-Id:\s*([^\r\n]+)/im', $respHeaders, $hm)) {
                    $headerReqIdCount++;
                    $val = trim($hm[1]);
                    if ($idx === 0 && $val === 'bench-custom-001') {
                        $customPreserved = true;
                    }
                }
                $latencies[] = $totalTimeMs;
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }

        $benchTotalSec = max(0.001, microtime(true) - $benchStart);
        $throughput = round($totalRequests / $benchTotalSec, 2);
        $p50 = grid402_calculate_percentile($latencies, 50.0);
        $p95 = grid402_calculate_percentile($latencies, 95.0);
        $p99 = grid402_calculate_percentile($latencies, 99.0);

        echo "  Metricas da rajada ({$totalRequests} reqs): throughput={$throughput} req/s | p50={$p50}ms | p95={$p95}ms | p99={$p99}ms\n";
        t_assert('100% das 30 requisicoes responderam com status HTTP esperado (200/402, zero 500)', $okCount === $totalRequests, "OK={$okCount}/{$totalRequests}");
        t_assert('100% das respostas emitiram cabecalho X-Request-Id', $headerReqIdCount === $totalRequests, "WithReqId={$headerReqIdCount}/{$totalRequests}");
        t_assert('X-Request-Id fornecido pelo cliente foi propagado na resposta', $customPreserved);
        t_assert('Log JSONL HTTP gravado no caminho isolado', is_file($httpLogFile) && filesize($httpLogFile) > 0);
    } finally {
        if ($server !== null) {
            grid402_stop_server($server);
        }
        if ($hadMetricsBackup && $metricsBackupRaw !== null) {
            @file_put_contents($metricsBackupPath, $metricsBackupRaw);
        }
    }
} finally {
    t_rmdir($tmpDir);
}

t_finish('CONCORRENCIA, LOGS ZERO-PII E CARGA');
