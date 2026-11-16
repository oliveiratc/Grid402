<?php
/**
 * Grid402 // Suíte de Testes: Extrato Zero-PII de Session Pass e PassStorageInterface (Fase 2)
 * Cobre todos os cenários da change OpenSpec: add-pass-statement-and-storage-interface
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpDir = t_bootstrap('statement');
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/logger.php';
require_once __DIR__ . '/../public_html/x402.php';

echo "=================================================================\n";
echo "  GRID402 - EXTRATO ZERO-PII DE SESSION PASS & STORAGE INTERFACE\n";
echo "=================================================================\n\n";

$projectRoot = dirname(__DIR__);
$phpBin = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';

/**
 * Executa requisição HTTP GET retornando status, headers brutos e body.
 */
function stmt_http_get(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
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
}

try {
    // -------------------------------------------------------------------------
    // [1/6] PassStorageInterface e JsonFlockPassRepository sem SQL
    // -------------------------------------------------------------------------
    echo "[1/6] Validando PassStorageInterface e JsonFlockPassRepository:\n";
    $repo = grid402_pass_repository();
    t_assert('grid402_pass_repository() implementa PassStorageInterface', $repo instanceof PassStorageInterface);
    t_assert('Implementacao padrao eh JsonFlockPassRepository', $repo instanceof JsonFlockPassRepository);

    // -------------------------------------------------------------------------
    // [2/6] Proteção Anti CSV Injection e Serialização UTF-8 com BOM
    // -------------------------------------------------------------------------
    echo "\n[2/6] Validando sanitizacao anti Formula/CSV Injection e BOM UTF-8:\n";
    t_assert('Prefixa = com apostrofo', grid402_csv_safe_cell('=cmd|calc') === "'=cmd|calc");
    t_assert('Prefixa + com apostrofo', grid402_csv_safe_cell('+1+1') === "'+1+1");
    t_assert('Prefixa - com apostrofo', grid402_csv_safe_cell('-danger') === "'-danger");
    t_assert('Prefixa @ com apostrofo', grid402_csv_safe_cell('@SUM(A1:A2)') === "'@SUM(A1:A2)");
    t_assert('Prefixa TAB com apostrofo', grid402_csv_safe_cell("\tEVIL") === "'\tEVIL");
    t_assert('Prefixa CR com apostrofo', grid402_csv_safe_cell("\rEVIL") === "'\rEVIL");
    t_assert('Preserva valores normais sem apostrofo', grid402_csv_safe_cell('g402_1234') === 'g402_1234');

    $csvSample = grid402_format_statement_csv([
        [
            'ts' => '2026-11-16T09:00:00Z',
            'request_id' => '=HYPERLINK("http://evil")',
            'endpoint' => 'forex',
            'outcome' => 'consumed',
            'http_status' => 200,
            'credits_debited' => 1,
            'credits_after' => 399,
            'usdc_equivalent' => '0.0050',
        ],
    ]);
    t_assert('CSV inicia com BOM UTF-8', str_starts_with($csvSample, "\xEF\xBB\xBF"));
    t_assert('CSV neutraliza formula maliciosa na coluna request_id', str_contains($csvSample, "'=HYPERLINK("));

    // -------------------------------------------------------------------------
    // [3/6] Ring Buffer estrito (max 200 eventos/passe) e isolamento de session_passes.json
    // -------------------------------------------------------------------------
    echo "\n[3/6] Validando ring buffer (max 200 eventos/passe) e separacao de storage:\n";
    $txBulk = '0x' . bin2hex(random_bytes(32));
    $bulkPass = grid402_issue_session_pass($txBulk, 2000000, false); // 400 creditos
    $bulkToken = (string) $bulkPass['pass_token'];

    for ($i = 1; $i <= 215; $i++) {
        $ok = grid402_record_pass_statement_event($bulkToken, [
            'ts_epoch' => 1763280000 + $i,
            'request_id' => 'ring_req_' . $i,
            'endpoint' => ($i % 2 === 0) ? 'forex' : 'workdays',
            'credits_debited' => 1,
            'credits_after' => 400 - $i,
            'http_status' => 200,
            'outcome' => 'consumed',
        ]);
        if (!$ok) {
            throw new RuntimeException("Falha ao gravar evento {$i} no ring buffer");
        }
    }

    $bulkStmt = grid402_get_session_pass_statement($bulkToken);
    t_assert('Ring buffer retem exatamente 200 lancamentos', (int) ($bulkStmt['retention']['stored_entries'] ?? 0) === 200);
    t_assert('Flag truncated === true quando atinge 200', !empty($bulkStmt['retention']['truncated']));
    t_assert('Evento mais recente (primeiro da lista decrescente) eh ring_req_215', ($bulkStmt['entries'][0]['request_id'] ?? '') === 'ring_req_215');
    t_assert('Evento mais antigo retido eh ring_req_16 (1..15 descartados)', ($bulkStmt['entries'][199]['request_id'] ?? '') === 'ring_req_16');

    $rawPassesFile = (string) file_get_contents(GRID402_FILE_PASSES);
    t_assert('session_passes.json nao contem eventos de extrato embutidos', !str_contains($rawPassesFile, 'ring_req_215'));

    // -------------------------------------------------------------------------
    // [4/6] Expurgo de extrato ao limpar passe expirado (> 7 dias)
    // -------------------------------------------------------------------------
    echo "\n[4/6] Validando limpeza de extratos de passes expirados:\n";
    $expiredPassId = 'live' . bin2hex(random_bytes(6));
    $expiredToken = 'pass_g402_' . $expiredPassId . '_' . grid402_pass_sign_id($expiredPassId);
    $passesData = json_decode((string) file_get_contents(GRID402_FILE_PASSES), true) ?: [];
    $passesData[$expiredPassId] = [
        'pass_id' => $expiredPassId,
        'pass_token' => $expiredToken,
        'tier' => 'starter',
        'mode' => 'production',
        'tx_hash' => '0x' . bin2hex(random_bytes(32)),
        'usdc_deposited' => '2.0000',
        'credit_scale' => 'v2_5000',
        'credits_total' => 400,
        'credits_remaining' => 10,
        'credits_used_today' => 0,
        'usage_day_utc' => gmdate('Y-m-d'),
        'daily_credit_limit' => null,
        'allowed_endpoints' => [],
        'issued_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 900000),
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 700000),
        'expires_epoch' => time() - 700000,
        'usage_count' => 1,
    ];
    grid402_atomic_write(GRID402_FILE_PASSES, $passesData);
    grid402_record_pass_statement_event($expiredToken, ['request_id' => 'orphan_evt_1', 'endpoint' => 'forex', 'credits_debited' => 1, 'credits_after' => 9, 'http_status' => 200, 'outcome' => 'consumed']);
    // Emitir um novo passe dispara a limpeza de expirados > 7 dias
    grid402_issue_session_pass('0x' . bin2hex(random_bytes(32)), 2000000, false);
    $stmtRawAfterPurge = (string) file_get_contents(GRID402_FILE_PASS_STATEMENTS);
    t_assert('Extrato de passe expirado ha > 7 dias foi expurgado', !str_contains($stmtRawAfterPurge, $expiredPassId));

    // -------------------------------------------------------------------------
    // [5/6] Fluxo HTTP E2E: 200 consumed, idempotent_replay, 403, 429, Zero-PII, API Statement & Dashboard
    // -------------------------------------------------------------------------
    echo "\n[5/6] Testando fluxo HTTP ponta a ponta (/api/v1/pass/statement + /payment-dashboard):\n";
    $server = null;
    try {
        $server = grid402_start_server([
            'docroot' => $projectRoot . '/public_html',
            'router' => $projectRoot . '/public_html/router.php',
            'probe' => '/status',
            'env' => [
                'GRID402_STORAGE_DIR' => $tmpDir,
                'GRID402_FILE_PASS_STATEMENTS' => $tmpDir . '/pass_statements.json',
                'GRID402_FILE_REQUEST_LOGS' => $tmpDir . '/requests.jsonl',
            ],
        ]);
        $baseUrl = $server['base_url'];

        // Emite pass Starter (400 creditos) e configura Spend Control (forex permitido, 1 credito/dia)
        $txLive = '0x' . bin2hex(random_bytes(32));
        $livePass = grid402_issue_session_pass($txLive, 2000000, false);
        $liveToken = (string) $livePass['pass_token'];
        grid402_update_session_pass_limits($liveToken, 1, ['forex']);

        // a) Chamada valida em /api/v1/forex com Idempotency-Key e X-Request-Id iniciado por '-' (testar CSV injection)
        t_assert('Notificacao Telegram recusa token Session Pass (evita vazar pass_g402_ ou link BaseScan falso)',
            grid402_notify_telegram_purchase('forex', 0.0050, $liveToken) === false
        );
        $rConsumed = stmt_http_get("{$baseUrl}/api/v1/forex?base=BRL", [
            "X-Payment: {$liveToken}",
            'Idempotency-Key: stmt-idem-001',
            'X-Request-Id: -cmd_risk_001',
            'Accept: application/json',
        ]);
        t_assert('Chamada forex valida retorna HTTP 200', $rConsumed['code'] === 200, "Code={$rConsumed['code']}");

        // b) Replay idempotente com mesma Idempotency-Key
        $rReplay = stmt_http_get("{$baseUrl}/api/v1/forex?base=BRL", [
            "X-Payment: {$liveToken}",
            'Idempotency-Key: stmt-idem-001',
            'X-Request-Id: stmt-replay-002',
            'Accept: application/json',
        ]);
        t_assert('Replay idempotente retorna HTTP 200 com X-Idempotent-Replay: true', $rReplay['code'] === 200 && stripos($rReplay['headers'], 'X-Idempotent-Replay: true') !== false);

        // c) Endpoint restrito (cnpj com CNPJ sensivel nos query params -> deve bloquear 403 e NAO vazar CNPJ no extrato)
        $rBlocked403 = stmt_http_get("{$baseUrl}/api/v1/cnpj?cnpj=00000000000191", [
            "X-Payment: {$liveToken}",
            'X-Request-Id: stmt-block-403',
            'Accept: application/json',
        ]);
        t_assert('Chamada fora da whitelist retorna HTTP 403 PassEndpointRestricted', $rBlocked403['code'] === 403 && str_contains($rBlocked403['body'], 'PassEndpointRestricted'));

        // d) Nova chamada em forex sem Idempotency-Key excede daily_credit_limit=1 -> 429
        $rLimit429 = stmt_http_get("{$baseUrl}/api/v1/forex", [
            "X-Payment: {$liveToken}",
            'X-Request-Id: stmt-limit-429',
            'Accept: application/json',
        ]);
        t_assert('Excesso de limite diário retorna HTTP 429 PassDailyLimitExceeded', $rLimit429['code'] === 429 && str_contains($rLimit429['body'], 'PassDailyLimitExceeded'));

        // e) Erros 400 e 404 do endpoint gratuito /api/v1/pass/statement
        $rBad400 = stmt_http_get("{$baseUrl}/api/v1/pass/statement?token=pass_g402_invalid_0000000000000000");
        t_assert('Token HMAC invalido retorna HTTP 400 InvalidPassTokenFormat', $rBad400['code'] === 400 && str_contains($rBad400['body'], 'InvalidPassTokenFormat'));

        $missingPid = 'live' . bin2hex(random_bytes(6));
        $missingToken = 'pass_g402_' . $missingPid . '_' . grid402_pass_sign_id($missingPid);
        $rMissing404 = stmt_http_get("{$baseUrl}/api/v1/pass/statement?token={$missingToken}");
        t_assert('Passe inexistente retorna HTTP 404 PassNotFound', $rMissing404['code'] === 404 && str_contains($rMissing404['body'], 'PassNotFound'));

        // f) Consulta completa JSON autenticada por Authorization: Bearer
        $rStmtJson = stmt_http_get("{$baseUrl}/api/v1/pass/statement", [
            "Authorization: Bearer {$liveToken}",
            'Accept: application/json',
        ]);
        $stmtJson = json_decode($rStmtJson['body'], true);
        t_assert('GET /api/v1/pass/statement retorna HTTP 200 e X-Request-Id', $rStmtJson['code'] === 200 && stripos($rStmtJson['headers'], 'X-Request-Id:') !== false);
        t_assert('Extrato contem todos os 4 desfechos esperados', is_array($stmtJson['entries'] ?? null) && count($stmtJson['entries']) === 4);

        $outcomes = array_column($stmtJson['entries'] ?? [], 'outcome');
        t_assert('Contem consumed, idempotent_replay, endpoint_restricted e daily_limit_exceeded',
            in_array('consumed', $outcomes, true) &&
            in_array('idempotent_replay', $outcomes, true) &&
            in_array('endpoint_restricted', $outcomes, true) &&
            in_array('daily_limit_exceeded', $outcomes, true)
        );
        t_assert('Zero-PII: CNPJ 00000000000191 nao aparece no JSON nem no pass_statements.json',
            !str_contains($rStmtJson['body'], '00000000000191') &&
            !str_contains((string) file_get_contents($tmpDir . '/pass_statements.json'), '00000000000191')
        );
        t_assert('Consulta gratuita ao extrato nao altera saldo (permanece 399)', (int) ($stmtJson['pass']['credits_remaining'] ?? -1) === 399);
        t_assert('Resumo soma 1 credito debitado, 0.0050 USDC, 2 bloqueios e 1 replay',
            ($stmtJson['summary']['total_credits_debited'] ?? -1) === 1 &&
            ($stmtJson['summary']['total_usdc_equivalent'] ?? '') === '0.0050' &&
            ($stmtJson['summary']['blocked_count'] ?? -1) === 2 &&
            ($stmtJson['summary']['replay_count'] ?? -1) === 1
        );

        // g) Filtros ?endpoint=forex&outcome=consumed via header X-Payment
        $rFiltered = stmt_http_get("{$baseUrl}/api/v1/pass/statement?endpoint=forex&outcome=consumed", [
            "X-Payment: {$liveToken}",
        ]);
        $filteredJson = json_decode($rFiltered['body'], true);
        t_assert('Filtro endpoint=forex + outcome=consumed retorna exatamente 1 evento',
            $rFiltered['code'] === 200 &&
            count($filteredJson['entries'] ?? []) === 1 &&
            ($filteredJson['entries'][0]['request_id'] ?? '') === '-cmd_risk_001'
        );

        // h) Exportacao CSV via HTTP com BOM UTF-8, header attachment e neutralizacao '-cmd_risk_001
        $rCsv = stmt_http_get("{$baseUrl}/api/v1/pass/statement?token=" . urlencode($liveToken) . '&format=csv');
        t_assert('GET /api/v1/pass/statement?format=csv retorna HTTP 200 text/csv attachment',
            $rCsv['code'] === 200 &&
            stripos($rCsv['headers'], 'text/csv') !== false &&
            stripos($rCsv['headers'], 'attachment; filename="grid402_statement_') !== false
        );
        t_assert('CSV HTTP comeca com BOM UTF-8 e prefixa -cmd_risk_001 com apostrofo',
            str_starts_with($rCsv['body'], "\xEF\xBB\xBF") &&
            str_contains($rCsv['body'], "'-cmd_risk_001") &&
            !str_contains($rCsv['body'], '00000000000191')
        );

        // i) Interface HTML /payment-dashboard em Ingles padrao + estado vazio de filtro
        $rDash = stmt_http_get("{$baseUrl}/payment-dashboard?token=" . urlencode($liveToken));
        t_assert('GET /payment-dashboard?token=... retorna 200 com extrato em ingles e botoes CSV/JSON',
            $rDash['code'] === 200 &&
            str_contains($rDash['body'], 'Session Pass Consumption Statement') &&
            str_contains($rDash['body'], 'format=csv') &&
            str_contains($rDash['body'], 'format=json') &&
            str_contains($rDash['body'], 'contato@grid402.online') &&
            str_contains($rDash['body'], 'endpoint_restricted')
        );

        $rDashEmpty = stmt_http_get("{$baseUrl}/payment-dashboard?token=" . urlencode($liveToken) . '&endpoint=notarize');
        t_assert('Filtro sem resultados renderiza estado vazio amigavel no /payment-dashboard',
            $rDashEmpty['code'] === 200 &&
            str_contains($rDashEmpty['body'], 'No statement events match the selected filters yet.')
        );
    } finally {
        if ($server !== null) {
            grid402_stop_server($server);
        }
    }

    // -------------------------------------------------------------------------
    // [6/6] Concorrência Multi-Processo: 10 workers em 5 créditos + consistência no extrato
    // -------------------------------------------------------------------------
    echo "\n[6/6] Validando disputa concorrente multi-processo com gravacao de extrato (10 workers em 5 creditos):\n";
    $txConc = '0x' . bin2hex(random_bytes(32));
    $concPass = grid402_issue_session_pass($txConc, 25000, false); // 5 creditos exatos
    $concToken = (string) $concPass['pass_token'];

    $workerFile = $tmpDir . '/stmt_conc_worker.php';
    $workerCode = <<<'PHP'
<?php
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
$res = grid402_consume_session_pass($token, 5000, 'workdays', ['request_id' => 'conc_req_' . $idx]);
echo json_encode(['worker' => $idx, 'valid' => !empty($res['valid']), 'code' => $res['code'] ?? 200]);
PHP;
    file_put_contents($workerFile, $workerCode);

    $goAt = sprintf('%.6f', microtime(true) + 0.35);
    $procs = [];
    $pipesList = [];
    for ($w = 0; $w < 10; $w++) {
        $cmd = [$phpBin, $workerFile, (string) $w, $goAt, $tmpDir, $projectRoot, $concToken];
        $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($proc)) {
            @fclose($pipes[0]);
            $procs[$w] = $proc;
            $pipesList[$w] = $pipes;
        }
    }

    $wins = 0;
    $losses402 = 0;
    foreach ($procs as $w => $proc) {
        $out = (string) stream_get_contents($pipesList[$w][1]);
        @fclose($pipesList[$w][1]);
        @fclose($pipesList[$w][2]);
        proc_close($proc);
        $j = json_decode(trim($out), true);
        if (is_array($j)) {
            if (($j['valid'] ?? false) === true) {
                $wins++;
            } elseif ((int) ($j['code'] ?? 0) === 402) {
                $losses402++;
            }
        }
    }

    $concStmt = grid402_get_session_pass_statement($concToken);
    t_assert('Exatamente 5 de 10 workers consumiram os 5 creditos (zero double-spend)', $wins === 5 && $losses402 === 5, "Wins={$wins}, 402={$losses402}");
    t_assert('Saldo final eh 0 e extrato contem estritamente os 5 consumos sem corrupcao JSON',
        ($concStmt['pass']['credits_remaining'] ?? -1) === 0 &&
        count($concStmt['entries'] ?? []) === 5 &&
        ($concStmt['summary']['total_credits_debited'] ?? -1) === 5
    );
} finally {
    t_rmdir($tmpDir);
}

t_finish('EXTRATO SESSION PASS E STORAGE INTERFACE');
