<?php
/**
 * Grid402 - Motor de Insights Executivos, Saúde Operacional e Alertas (/admin e collector)
 *
 * Responsável por:
 * - Comparativo estruturado Hoje vs. Ontem (fuso America/Sao_Paulo) com variação percentual segura.
 * - Agregação consolidada de Saúde Operacional (25/25 UP, uptime 30d, p50/p95, integrações).
 * - Avaliação determinística das 7 regras de Pontos de Atenção (severidades high, medium e low).
 * - Despacho de notificações no Telegram exclusivo para severidade high com cooldown Zero-DB (flock).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../metrics.php';

/**
 * Calcula delta absoluto e percentual com proteção contra divisão por zero.
 *
 * @return array{today: int|float, yesterday: int|float, total: int|float, delta: int|float, delta_pct: ?float}
 */
function grid402_admin_delta_entry(int|float $todayVal, int|float $yesterdayVal, int|float $totalVal, int $precision = 0): array {
    $delta = $precision > 0
        ? round((float) $todayVal - (float) $yesterdayVal, $precision)
        : ((int) $todayVal - (int) $yesterdayVal);

    $deltaPct = null;
    if ((float) $yesterdayVal > 0.0) {
        $deltaPct = round((((float) $todayVal - (float) $yesterdayVal) / (float) $yesterdayVal) * 100.0, 1);
    }

    return [
        'today' => $precision > 0 ? round((float) $todayVal, $precision) : (int) $todayVal,
        'yesterday' => $precision > 0 ? round((float) $yesterdayVal, $precision) : (int) $yesterdayVal,
        'total' => $precision > 0 ? round((float) $totalVal, $precision) : (int) $totalVal,
        'delta' => $delta,
        'delta_pct' => $deltaPct,
    ];
}

/**
 * Compõe o quadro comparativo Hoje vs. Ontem (fuso America/Sao_Paulo).
 */
function grid402_admin_today_vs_yesterday(array $metrics, array $growthMetrics = [], float $usdRate = 5.50): array {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $todayDt = new DateTimeImmutable('now', $tz);
    $yesterdayDt = $todayDt->modify('-1 day');

    $todayKey = $todayDt->format('Y-m-d');
    $yesterdayKey = $yesterdayDt->format('Y-m-d');

    $daily = is_array($metrics['daily'] ?? null) ? $metrics['daily'] : [];
    $todayRow = is_array($daily[$todayKey] ?? null) ? $daily[$todayKey] : [];
    $yesterdayRow = is_array($daily[$yesterdayKey] ?? null) ? $daily[$yesterdayKey] : [];

    $todayReqs = (int) ($todayRow['requests'] ?? 0);
    $yesterdayReqs = (int) ($yesterdayRow['requests'] ?? 0);
    $totalReqs = (int) ($metrics['total_requests'] ?? 0);

    $todayPv = (int) ($todayRow['page_views'] ?? 0);
    $yesterdayPv = (int) ($yesterdayRow['page_views'] ?? 0);
    $totalPv = (int) ($metrics['total_page_views'] ?? 0);

    $todayBots = (int) ($todayRow['bot_views'] ?? 0);
    $yesterdayBots = (int) ($yesterdayRow['bot_views'] ?? 0);
    $totalBots = (int) ($metrics['total_bot_views'] ?? 0);

    $todayPaid = (int) ($todayRow['paid'] ?? 0);
    $yesterdayPaid = (int) ($yesterdayRow['paid'] ?? 0);
    $totalPaid = (int) ($metrics['total_paid_requests'] ?? 0);

    $todayUsdc = (float) ($todayRow['usdc'] ?? 0.0);
    $yesterdayUsdc = (float) ($yesterdayRow['usdc'] ?? 0.0);
    $totalUsdc = (float) ($metrics['total_revenue_usdc'] ?? 0.0);

    $todayBrl = round($todayUsdc * $usdRate, 2);
    $yesterdayBrl = round($yesterdayUsdc * $usdRate, 2);
    $totalBrl = round($totalUsdc * $usdRate, 2);

    $newByDay = is_array($growthMetrics['new_wallets_by_day'] ?? null) ? $growthMetrics['new_wallets_by_day'] : [];
    $todayWallets = (int) ($newByDay[$todayKey] ?? 0);
    $yesterdayWallets = (int) ($newByDay[$yesterdayKey] ?? 0);
    $totalWallets = (int) ($growthMetrics['wallets_connected'] ?? 0);

    return [
        'today_date' => $todayKey,
        'today_label' => $todayDt->format('d/m'),
        'yesterday_date' => $yesterdayKey,
        'yesterday_label' => $yesterdayDt->format('d/m'),
        'metrics' => [
            'requests' => grid402_admin_delta_entry($todayReqs, $yesterdayReqs, $totalReqs, 0),
            'page_views' => grid402_admin_delta_entry($todayPv, $yesterdayPv, $totalPv, 0),
            'bot_views' => grid402_admin_delta_entry($todayBots, $yesterdayBots, $totalBots, 0),
            'paid' => grid402_admin_delta_entry($todayPaid, $yesterdayPaid, $totalPaid, 0),
            'revenue_usdc' => grid402_admin_delta_entry($todayUsdc, $yesterdayUsdc, $totalUsdc, 4),
            'revenue_brl' => grid402_admin_delta_entry($todayBrl, $yesterdayBrl, $totalBrl, 2),
            'new_wallets' => grid402_admin_delta_entry($todayWallets, $yesterdayWallets, $totalWallets, 0),
        ],
    ];
}

/**
 * Consolida a saúde operacional da plataforma (serviços UP, uptime 30d, p50/p95 e integrações).
 */
function grid402_admin_operational_health(
    array $metrics,
    array $analytics,
    array $runtimeStatus,
    array $walletBalances = []
): array {
    $catalogCount = function_exists('grid402_get_utility_services')
        ? count(grid402_get_utility_services())
        : 25;

    $servicesUp = 0;
    $servicesTotal = $catalogCount;
    $uptime30dPct = 100.0;
    $slaTargetPct = 99.5;

    $summaryFile = defined('GRID402_FILE_FREE') ? GRID402_FILE_FREE : '';
    if ($summaryFile !== '' && is_file($summaryFile) && is_readable($summaryFile)) {
        $raw = @file_get_contents($summaryFile);
        $decoded = $raw ? @json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $svcs = is_array($decoded['services'] ?? null) ? $decoded['services'] : [];
            if (!empty($svcs)) {
                $servicesTotal = count($svcs);
                $upCount = 0;
                foreach ($svcs as $svcInfo) {
                    if (is_array($svcInfo) && strtoupper((string) ($svcInfo['status'] ?? '')) === 'UP') {
                        $upCount++;
                    }
                }
                $servicesUp = $upCount;
            }
            if (is_array($decoded['uptime_30d'] ?? null)) {
                $uptime30dPct = (float) ($decoded['uptime_30d']['overall_uptime_pct'] ?? 100.0);
                $slaTargetPct = (float) ($decoded['uptime_30d']['sla_target_pct'] ?? 99.5);
            }
        }
    }

    if (function_exists('grid402_get_uptime_30d')) {
        $hist30d = grid402_get_uptime_30d();
        if (is_array($hist30d) && isset($hist30d['overall_uptime_pct'])) {
            $uptime30dPct = (float) $hist30d['overall_uptime_pct'];
            $slaTargetPct = (float) ($hist30d['sla_target_pct'] ?? $slaTargetPct);
        }
    }

    $recentEvents = is_array($metrics['recent_events'] ?? null) ? $metrics['recent_events'] : [];
    $burst429Recent = 0;
    foreach ($recentEvents as $ev) {
        if (is_array($ev) && (int) ($ev['status'] ?? 0) === 429) {
            $burst429Recent++;
        }
    }

    $fac = is_array($runtimeStatus['facilitator'] ?? null) ? $runtimeStatus['facilitator'] : [];
    $col = is_array($runtimeStatus['collector'] ?? null) ? $runtimeStatus['collector'] : [];
    $tg = is_array($runtimeStatus['telegram'] ?? null) ? $runtimeStatus['telegram'] : [];
    $rapid = is_array($runtimeStatus['rapidapi'] ?? null) ? $runtimeStatus['rapidapi'] : [];
    $mp = is_array($runtimeStatus['mercadopago'] ?? null) ? $runtimeStatus['mercadopago'] : null;
    $tgLast = is_array($tg['last_status'] ?? null) ? $tg['last_status'] : null;

    $health = [
        'services_up' => $servicesUp,
        'services_total' => $servicesTotal,
        'uptime_30d_pct' => round($uptime30dPct, 2),
        'sla_target_pct' => round($slaTargetPct, 2),
        'error_5xx_count' => (int) ($analytics['error_5xx_count'] ?? 0),
        'technical_success_rate' => round((float) ($analytics['technical_success_rate'] ?? 100.0), 1),
        'latency_p50_ms' => round((float) ($analytics['latency_p50_ms'] ?? 0.0), 2),
        'latency_p95_ms' => round((float) ($analytics['latency_p95_ms'] ?? 0.0), 2),
        'burst_429_recent' => $burst429Recent,
        'collector_age_sec' => $col['summary_age_sec'] ?? null,
        'collector_stale' => (bool) ($col['summary_stale'] ?? true),
        'rpc_operational' => (bool) ($walletBalances['rpc_operational'] ?? false),
        'facilitator_mode' => (string) ($fac['mode'] ?? 'unknown'),
        'facilitator_host' => (string) ($fac['host'] ?? ''),
        'multichain_accepts' => (int) ($fac['multichain_accepts'] ?? 1),
        'rapidapi_configured' => (bool) ($rapid['configured'] ?? false),
        'rapidapi_count' => (int) ($rapid['count'] ?? 0),
        'telegram_configured' => (bool) ($tg['configured'] ?? false),
        'telegram_last_ok' => $tgLast !== null ? (bool) ($tgLast['ok'] ?? false) : null,
        'telegram_last_timestamp' => $tgLast !== null ? (string) ($tgLast['timestamp'] ?? '') : '',
    ];
    if ($mp !== null) {
        $health['mercadopago_configured'] = (bool) ($mp['configured'] ?? false);
        $health['mercadopago_sandbox'] = (bool) ($mp['sandbox'] ?? false);
    }

    return $health;
}

/**
 * Avalia deterministicamente as 7 regras de Pontos de Atenção (high, medium, low).
 *
 * @return array{alerts: array<int, array<string, string>>, summary: array{high: int, medium: int, low: int, total: int}}
 */
function grid402_admin_evaluate_alerts(
    array $metrics,
    array $analytics,
    array $operationalHealth,
    array $walletBalances = []
): array {
    $alerts = [];

    // 1. [HIGH] Erros 5xx
    $error5xx = (int) ($operationalHealth['error_5xx_count'] ?? ($analytics['error_5xx_count'] ?? 0));
    if ($error5xx > 0) {
        $alerts[] = [
            'id' => 'error_5xx',
            'severity' => 'high',
            'title' => 'Erros HTTP 5xx detectados na plataforma',
            'detail' => "{$error5xx} erro(s) 5xx acumulado(s) na telemetria (SLA técnico: " . number_format((float) ($operationalHealth['technical_success_rate'] ?? 100.0), 1, ',', '.') . '%).',
            'action' => 'Verifique imediatamente a aba Saúde e Alertas e o log estruturado requests.jsonl.',
        ];
    }

    // 2. [HIGH] Coletor atrasado (> 10 min ou ausente)
    $collectorStale = (bool) ($operationalHealth['collector_stale'] ?? false);
    if ($collectorStale) {
        $ageSec = $operationalHealth['collector_age_sec'] ?? null;
        $ageDetail = ($ageSec === null)
            ? 'Arquivo summary_free.json ausente no storage.'
            : 'Última atualização do coletor há ' . round(((int) $ageSec) / 60, 1) . ' min (limite: 10 min).';
        $alerts[] = [
            'id' => 'collector_stale',
            'severity' => 'high',
            'title' => 'Coletor de telemetria (/status) atrasado',
            'detail' => $ageDetail,
            'action' => 'Verifique a tarefa Cron de collector.php na Hostinger ou execute uma coleta manual.',
        ];
    }

    // 3. [HIGH] Rajada de HTTP 429 no buffer recente (>= 20 eventos)
    $burst429 = (int) ($operationalHealth['burst_429_recent'] ?? 0);
    if ($burst429 >= 20) {
        $alerts[] = [
            'id' => 'burst_429_sandbox',
            'severity' => 'high',
            'title' => 'Rajada de bloqueios HTTP 429 detectada',
            'detail' => "{$burst429} eventos HTTP 429 registrados nos últimos 100 eventos do buffer recente.",
            'action' => 'Inspecione o IP mascarado em Eventos ao Vivo; o rate limit protegeu o upstream, mas avalie bloqueio de borda se persistir.',
        ];
    }

    // 4. [MEDIUM] Latência p95 acima de 1000 ms
    $p95Ms = (float) ($operationalHealth['latency_p95_ms'] ?? ($analytics['latency_p95_ms'] ?? 0.0));
    if ($p95Ms > 1000.0) {
        $alerts[] = [
            'id' => 'high_latency_p95',
            'severity' => 'medium',
            'title' => 'Latência p95 acima de 1.000 ms',
            'detail' => 'p95 atual em ' . number_format($p95Ms, 1, ',', '.') . ' ms (p50: ' . number_format((float) ($operationalHealth['latency_p50_ms'] ?? 0.0), 1, ',', '.') . ' ms).',
            'action' => 'Verifique tempo de resposta das fontes upstream e a taxa de hit de cache em collector.php.',
        ];
    }

    // 5. [MEDIUM] Session Passes pagos de produção ativos 100% ociosos (exclui passes gratuitos promo_* e sandbox)
    $passesStats = is_array($analytics['passes_stats'] ?? null) ? $analytics['passes_stats'] : [];
    $hasPaidBreakdown = array_key_exists('active_paid_passes', $passesStats);
    $activePaidPasses = (int) ($hasPaidBreakdown ? $passesStats['active_paid_passes'] : ($passesStats['active_passes'] ?? 0));
    $paidTotalCredits = (int) ($hasPaidBreakdown ? ($passesStats['paid_total_credits'] ?? 0) : ($passesStats['total_credits'] ?? 0));
    $paidActiveRemCredits = (int) ($hasPaidBreakdown
        ? ($passesStats['paid_active_remaining_credits'] ?? ($passesStats['paid_remaining_credits'] ?? 0))
        : ($passesStats['active_remaining_credits'] ?? ($passesStats['remaining_credits'] ?? 0)));
    $passUses = (int) (($metrics['payment_sources']['pass'] ?? 0));
    if ($activePaidPasses > 0 && $paidActiveRemCredits > 0 && ($paidActiveRemCredits >= $paidTotalCredits || $passUses === 0)) {
        $alerts[] = [
            'id' => 'idle_session_passes',
            'severity' => 'medium',
            'title' => 'Session Passes pagos ativos sem consumo',
            'detail' => "{$activePaidPasses} passe(s) pago(s) ativo(s) com {$paidActiveRemCredits} créditos intactos (uso via pass: {$passUses}). Passes promocionais e sandbox são excluídos deste alerta.",
            'action' => 'Acompanhe o onboarding do header X-Payment e o fluxo de reengajamento de Session Pass.',
        ];
    }

    // 6. [LOW] Carteira oráculo com 0 ETH na Base Mainnet
    $rpcOk = (bool) ($walletBalances['rpc_operational'] ?? false);
    $ethBalance = (float) ($walletBalances['eth_balance'] ?? 0.0);
    if ($rpcOk && $ethBalance <= 0.0) {
        $usdcBal = number_format((float) ($walletBalances['usdc_balance'] ?? 0.0), 4, ',', '.');
        $alerts[] = [
            'id' => 'wallet_zero_eth',
            'severity' => 'low',
            'title' => 'Carteira oráculo com 0 ETH na Base',
            'detail' => "Saldo atual: {$usdcBal} USDC e 0 ETH. Recebimentos via PayAI são gasless, mas saques/transferências exigem ETH para gas.",
            'action' => 'Deposite uma pequena fração de ETH na Base quando planejar movimentar os USDC.',
        ];
    }

    // 7. [LOW] Serviços pagos do catálogo com baixa tração comercial (< 30 consultas PAGAS acumuladas; ignora 402/bots/sandbox)
    $servicePaidCounts = is_array($metrics['service_paid_counts'] ?? null)
        ? $metrics['service_paid_counts']
        : (is_array($analytics['service_paid_counts'] ?? null) ? $analytics['service_paid_counts'] : []);
    $catalog = function_exists('grid402_get_utility_services') ? grid402_get_utility_services() : [];
    $lowTraction = [];
    foreach ($catalog as $svcId => $svcDef) {
        $svcKey = strtolower((string) $svcId);
        if (in_array($svcKey, ['summary', 'verify'], true)) {
            continue;
        }
        $paidCnt = (int) ($servicePaidCounts[$svcKey] ?? 0);
        if ($paidCnt < 30) {
            $lowTraction[] = "{$svcKey} ({$paidCnt} pagas)";
        }
    }
    if (!empty($lowTraction)) {
        $preview = implode(', ', array_slice($lowTraction, 0, 6));
        $extraCount = count($lowTraction) - 6;
        $suffix = $extraCount > 0 ? " e +{$extraCount} serviço(s)" : '';
        $alerts[] = [
            'id' => 'low_traction_services',
            'severity' => 'low',
            'title' => count($lowTraction) . ' serviço(s) com baixa tração paga (< 30 pagas)',
            'detail' => "Serviços abaixo de 30 consultas pagas reais (402/sandbox/bots excluídos): {$preview}{$suffix}.",
            'action' => 'Acompanhe a indexação SEO, descoberta MCP e conversão na aba Audiência e Funil.',
        ];
    }

    // 8. [HIGH] Erros 5xx no gateway Mercado Pago
    $mpGatewayErrors = (int) ($metrics['failure_categories']['mercadopago_gateway_error'] ?? 0);
    if ($mpGatewayErrors > 0) {
        $alerts[] = [
            'id' => 'mercadopago_gateway_error',
            'severity' => 'high',
            'title' => 'Erros 5xx no gateway Mercado Pago',
            'detail' => "{$mpGatewayErrors} falha(s) de gateway registrada(s) no checkout Pix/Cartão.",
            'action' => 'Verifique MP_ACCESS_TOKEN, payment_failures.json e disponibilidade da API Mercado Pago.',
        ];
    }

    // 9. [MEDIUM] Mercado Pago sem token em produção
    if (
        array_key_exists('mercadopago_configured', $operationalHealth)
        && $operationalHealth['mercadopago_configured'] === false
        && empty($operationalHealth['mercadopago_sandbox'])
    ) {
        $alerts[] = [
            'id' => 'mercadopago_unconfigured',
            'severity' => 'medium',
            'title' => 'Mercado Pago sem MP_ACCESS_TOKEN configurado',
            'detail' => 'O checkout Pix/Cartão do Session Pass está sem credencial de produção ativa.',
            'action' => 'Configure MP_ACCESS_TOKEN (ou MP_SANDBOX_MODE=1 em staging) no .env da Hostinger.',
        ];
    }

    $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($alerts, static function (array $a, array $b) use ($severityOrder): int {
        return ($severityOrder[$a['severity']] ?? 9) <=> ($severityOrder[$b['severity']] ?? 9);
    });

    $summary = ['high' => 0, 'medium' => 0, 'low' => 0, 'total' => count($alerts)];
    foreach ($alerts as $al) {
        $sev = (string) ($al['severity'] ?? 'low');
        if (isset($summary[$sev])) {
            $summary[$sev]++;
        }
    }

    return [
        'alerts' => $alerts,
        'summary' => $summary,
    ];
}

/**
 * Envia um alerta operacional de severidade Alta no Telegram (timeout curto, sem vazar segredos).
 */
function grid402_send_telegram_admin_alert(array $alert): bool {
    if ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1') {
        return false;
    }
    if (!function_exists('grid402_resolve_telegram_credentials')) {
        return false;
    }

    $creds = grid402_resolve_telegram_credentials();
    $token = (string) ($creds['token'] ?? '');
    $chatId = (string) ($creds['chat_id'] ?? '');
    if ($token === '' || $chatId === '') {
        return false;
    }

    $tz = new DateTimeZone('America/Sao_Paulo');
    $brtNow = (new DateTimeImmutable('now', $tz))->format('d/m/Y H:i:s') . ' BRT';
    $title = htmlspecialchars((string) ($alert['title'] ?? 'Alerta Crítico Grid402'), ENT_QUOTES, 'UTF-8');
    $detail = htmlspecialchars((string) ($alert['detail'] ?? ''), ENT_QUOTES, 'UTF-8');
    $action = htmlspecialchars((string) ($alert['action'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alertId = htmlspecialchars((string) ($alert['id'] ?? 'unknown'), ENT_QUOTES, 'UTF-8');

    $lines = [
        "🚨 <b>Alerta Crítico — Grid402 Admin</b>",
        "",
        "<b>{$title}</b>",
        "📌 <b>Detalhes:</b> {$detail}",
        "🛠️ <b>Ação sugerida:</b> {$action}",
        "🔖 <b>Código:</b> <code>{$alertId}</code>",
        "🕒 <b>Horário:</b> {$brtNow}",
        "🔗 <a href=\"https://grid402.online/admin#tab-saude\">Abrir Painel de Saúde</a>",
    ];

    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => implode("\n", $lines),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($payload)) {
        return false;
    }

    try {
        $ch = @curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        if (!$ch) {
            return false;
        }
        $curlOpts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Grid402-Admin-Alert/1.0',
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        @curl_setopt_array($ch, $curlOpts);
        @curl_exec($ch);
        $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = (string) @curl_error($ch);
        @curl_close($ch);

        if ($httpCode === 200) {
            if (function_exists('grid402_record_telegram_status')) {
                grid402_record_telegram_status(true, 200, '', 'admin_alert:' . (string) ($alert['id'] ?? 'high'), false);
            }
            return true;
        }

        if (function_exists('grid402_record_telegram_status')) {
            grid402_record_telegram_status(false, $httpCode, $curlErr !== '' ? $curlErr : ('HTTP ' . $httpCode), 'admin_alert:' . (string) ($alert['id'] ?? 'high'), false);
        }
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Despacha notificações Telegram apenas para alertas de severidade `high`,
 * com cooldown Zero-DB (padrão 60 min por `alert_id`) em `admin_alerts_state.json`.
 *
 * O lock exclusivo (`flock(LOCK_EX)`) é liberado antes de qualquer chamada de rede.
 *
 * @return array{evaluated: int, sent: array<int, string>, suppressed: array<int, string>, failed: array<int, string>}
 */
function grid402_dispatch_high_severity_alerts(
    array $alerts,
    int $cooldownSec = 3600,
    ?callable $sender = null,
    ?string $stateFile = null,
    ?int $nowEpoch = null
): array {
    $highAlerts = [];
    foreach ($alerts as $al) {
        if (is_array($al) && strtolower((string) ($al['severity'] ?? '')) === 'high' && !empty($al['id'])) {
            $highAlerts[] = $al;
        }
    }

    if (empty($highAlerts)) {
        return ['evaluated' => 0, 'sent' => [], 'suppressed' => [], 'failed' => []];
    }

    // Se usa o sender padrão e o Telegram não está configurado/habilitado, evita I/O desnecessário
    if ($sender === null) {
        if ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1') {
            return ['evaluated' => count($highAlerts), 'sent' => [], 'suppressed' => [], 'failed' => []];
        }
        if (function_exists('grid402_telegram_runtime_status')) {
            $tgStatus = grid402_telegram_runtime_status();
            if (empty($tgStatus['configured'])) {
                return ['evaluated' => count($highAlerts), 'sent' => [], 'suppressed' => [], 'failed' => []];
            }
        }
    }

    $path = $stateFile !== null && $stateFile !== ''
        ? $stateFile
        : (defined('GRID402_FILE_ADMIN_ALERTS_STATE')
            ? GRID402_FILE_ADMIN_ALERTS_STATE
            : (GRID402_PRIVATE_DIR . '/admin_alerts_state.json'));

    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $now = $nowEpoch ?? time();
    $cooldown = max(1, $cooldownSec);
    $toSend = [];
    $suppressed = [];
    $previousEpochs = [];

    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return ['evaluated' => count($highAlerts), 'sent' => [], 'suppressed' => [], 'failed' => []];
    }

    if (flock($fp, LOCK_EX)) {
        $state = grid402_read_locked_json($fp, ['alerts' => []]);
        if (!is_array($state)) {
            $state = ['alerts' => []];
        }
        if (!isset($state['alerts']) || !is_array($state['alerts'])) {
            $state['alerts'] = [];
        }

        foreach ($highAlerts as $al) {
            $id = (string) $al['id'];
            $entry = is_array($state['alerts'][$id] ?? null) ? $state['alerts'][$id] : [];
            $lastSent = (int) ($entry['last_sent_epoch'] ?? 0);
            if ($lastSent > 0 && ($now - $lastSent) < $cooldown) {
                $suppressed[] = $id;
                continue;
            }
            $previousEpochs[$id] = $lastSent;
            $state['alerts'][$id] = [
                'last_sent_epoch' => $now,
                'last_sent_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
                'send_count' => ((int) ($entry['send_count'] ?? 0)) + 1,
                'last_title' => (string) ($al['title'] ?? ''),
            ];
            $toSend[] = $al;
        }

        if (!empty($toSend)) {
            $state['updated_at'] = gmdate('Y-m-d\TH:i:s\Z', $now);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    $sent = [];
    $failed = [];
    $dispatchFn = $sender ?? 'grid402_send_telegram_admin_alert';

    foreach ($toSend as $al) {
        $id = (string) $al['id'];
        $ok = false;
        try {
            $ok = (bool) $dispatchFn($al);
        } catch (Throwable $e) {
            $ok = false;
        }

        if ($ok) {
            $sent[] = $id;
        } else {
            $failed[] = $id;
        }
    }

    // Reverte a reserva de cooldown apenas dos alertas cujo envio falhou
    if (!empty($failed)) {
        $fpRollback = @fopen($path, 'c+');
        if ($fpRollback) {
            if (flock($fpRollback, LOCK_EX)) {
                $state = grid402_read_locked_json($fpRollback, ['alerts' => []]);
                if (is_array($state) && isset($state['alerts']) && is_array($state['alerts'])) {
                    foreach ($failed as $failId) {
                        $prev = (int) ($previousEpochs[$failId] ?? 0);
                        if ($prev > 0) {
                            $state['alerts'][$failId]['last_sent_epoch'] = $prev;
                            $state['alerts'][$failId]['last_sent_at'] = gmdate('Y-m-d\TH:i:s\Z', $prev);
                            $state['alerts'][$failId]['send_count'] = max(0, ((int) ($state['alerts'][$failId]['send_count'] ?? 1)) - 1);
                        } else {
                            unset($state['alerts'][$failId]);
                        }
                    }
                    ftruncate($fpRollback, 0);
                    rewind($fpRollback);
                    fwrite($fpRollback, (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    fflush($fpRollback);
                }
                flock($fpRollback, LOCK_UN);
            }
            fclose($fpRollback);
        }
    }

    return [
        'evaluated' => count($highAlerts),
        'sent' => $sent,
        'suppressed' => $suppressed,
        'failed' => $failed,
    ];
}
