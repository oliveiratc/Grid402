<?php
/**
 * Grid402 - Módulo de Telemetria Interna e Métricas em Tempo Real (Zero-DB)
 * 
 * Gravação atômica via flock(LOCK_EX) com buffer circular de eventos recentes
 * e anonimização de IPs em conformidade com a LGPD.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';

/**
 * Data do dia no fuso de Brasilia (America/Sao_Paulo) para os buckets
 * diarios ('daily') das metricas do Grid402. O dia brasileiro zera as
 * 00:00 BRT (= 03:00 UTC), entao os contadores "hoje" zeram nesse
 * instante, alinhados com o relogio local do mantenedor.
 */
function grid402_metrics_today(): string {
    static $today = null;
    if ($today === null) {
        $tz = new DateTimeZone('America/Sao_Paulo');
        $today = (new DateTime('now', $tz))->format('Y-m-d');
    }
    return $today;
}

/**
 * Data (BRT) de um timestamp arbitrario gravado em UTC
 * (created_at, first_seen_at, last_seen_at).
 */
function grid402_metrics_day_of(string $ts): string {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $dt = DateTime::createFromFormat(DateTime::ATOM, $ts);
    if ($dt === false) {
        $ts = trim($ts) !== '' ? $ts : 'now';
        $dt = new DateTime($ts, $tz);
    }
    $dt->setTimezone($tz);
    return $dt->format('Y-m-d');
}

/**
 * Mascara o IP do cliente para conformidade com a LGPD
 * IPv4: 189.40.122.50 -> 189.40.xxx.xxx
 * IPv6: 2001:0db8:85a3:0000:0000:8a2e:0370:7334 -> 2001:0db8:xxxx:xxxx
 */
function grid402_mask_ip(?string $ip): string {
    if (!$ip || trim($ip) === '' || $ip === '::1' || $ip === '127.0.0.1') {
        return '127.0.xxx.xxx';
    }
    $ip = trim($ip);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        if (count($parts) >= 2) {
            return $parts[0] . ':' . $parts[1] . ':xxxx:xxxx';
        }
    }

    return 'xxx.xxx.xxx.xxx';
}

/**
 * Retorna a estrutura inicial padrão de métricas
 */
function grid402_default_metrics(): array {
    return [
        'version' => '1.0.0',
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'total_requests' => 0,
        'total_paid_requests' => 0,
        'total_sandbox_requests' => 0,
        'total_page_views' => 0,
        'total_revenue_usdc' => 0.0,
        'status_counts' => [
            '200' => 0,
            '402' => 0,
            '403' => 0,
        ],
        'service_counts' => [
            'forex' => 0,
            'crypto' => 0,
            'dns' => 0,
            'ssl' => 0,
            'asn' => 0,
            'cep' => 0,
            'cnpj' => 0,
            'indicators' => 0,
            'holidays' => 0,
            'workdays' => 0,
            'sanctions' => 0,
            'simples' => 0,
            'compliance' => 0,
            'lei' => 0,
            'ofac' => 0,
            'notarize' => 0,
            'global-markets' => 0,
            'pass' => 0,
            'dossier' => 0,
            'kyb-audit' => 0,
            'domain-audit' => 0,
            'market-snapshot' => 0,
            'extract' => 0,
            'email-verify' => 0,
            'summary' => 0,
        ],
        'payment_sources' => [
            'tx_hash' => 0,
            'pass' => 0,
            'facilitator' => 0,
            'facilitator_verify_only' => 0,
            'mercadopago' => 0,
            'rapidapi' => 0,
            'sandbox' => 0,
            'unknown' => 0,
        ],
        'failure_categories' => [
            'invalid_proof' => 0,
            'payment_rejected' => 0,
            'authorization_expired' => 0,
            'replay' => 0,
            'facilitator_unavailable' => 0,
            'facilitator_disabled' => 0,
            'sandbox_rate_limited' => 0,
            'unknown' => 0,
        ],
        'daily' => [],
        'recent_events' => [],
        'latency_samples_ms' => [],
    ];
}

/**
 * Normaliza métricas legadas/parciais para o schema atual
 */
function grid402_normalize_metrics(array $metrics): array {
    $metrics['version'] = $metrics['version'] ?? '1.0.0';
    $metrics['updated_at'] = $metrics['updated_at'] ?? gmdate('Y-m-d\TH:i:s\Z');
    $metrics['total_requests'] = (int)($metrics['total_requests'] ?? 0);
    $metrics['total_paid_requests'] = (int)($metrics['total_paid_requests'] ?? 0);
    $metrics['total_sandbox_requests'] = (int)($metrics['total_sandbox_requests'] ?? 0);
    $metrics['total_page_views'] = (int)($metrics['total_page_views'] ?? 0);
    $metrics['total_revenue_usdc'] = (float)($metrics['total_revenue_usdc'] ?? 0.0);

    if (!isset($metrics['status_counts']) || !is_array($metrics['status_counts'])) {
        $metrics['status_counts'] = [];
    }
    if (!isset($metrics['service_counts']) || !is_array($metrics['service_counts'])) {
        $metrics['service_counts'] = [];
    }
    if (!isset($metrics['payment_sources']) || !is_array($metrics['payment_sources'])) {
        $metrics['payment_sources'] = [];
    }
    if (!isset($metrics['failure_categories']) || !is_array($metrics['failure_categories'])) {
        $metrics['failure_categories'] = [];
    }
    if (!isset($metrics['recent_events']) || !is_array($metrics['recent_events'])) {
        $metrics['recent_events'] = [];
    }
    if (!isset($metrics['latency_samples_ms']) || !is_array($metrics['latency_samples_ms'])) {
        $metrics['latency_samples_ms'] = [];
    }
    if (!isset($metrics['daily']) || !is_array($metrics['daily'])) {
        $metrics['daily'] = [];
    }

    foreach ($metrics['daily'] as $day => $stats) {
        if (!is_array($stats)) {
            $stats = [];
        }
        $stats = array_merge([
            'requests' => 0,
            'page_views' => 0,
            'paid' => 0,
            'usdc' => 0.0,
        ], $stats);
        $stats['requests'] = (int)$stats['requests'];
        $stats['page_views'] = (int)$stats['page_views'];
        $stats['paid'] = (int)$stats['paid'];
        $stats['usdc'] = (float)$stats['usdc'];
        $metrics['daily'][$day] = $stats;
    }

    return $metrics;
}

/**
 * Carrega o snapshot atual de métricas
 */
function grid402_get_metrics(): array {
    $file = GRID402_FILE_METRICS;
    if (!file_exists($file)) {
        return grid402_default_metrics();
    }
    $content = @file_get_contents($file);
    if (!$content) {
        return grid402_default_metrics();
    }
    $data = @json_decode($content, true);
    return is_array($data) ? grid402_normalize_metrics($data) : grid402_default_metrics();
}

/**
 * Grava atomicamente um evento de telemetria em metrics.json
 */
function grid402_record_telemetry(
    string $service,
    int $statusCode,
    bool $isPaid = false,
    ?string $txHash = null,
    ?string $ip = null,
    ?float $priceUsdc = null,
    ?string $type = null,
    array $meta = []
): void {
    $file = GRID402_FILE_METRICS;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $metrics = grid402_read_locked_json($fp, grid402_default_metrics());
        $metrics = grid402_normalize_metrics($metrics);

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $today = grid402_metrics_today();
        $statusStr = (string)$statusCode;
        $svc = strtolower(trim($service));
        if ($svc === '') {
            $svc = 'summary';
        }

        $cleanTx = strtolower(trim((string)$txHash));

        // Determinacao da tipagem do evento de telemetria
        $eventTrafficType = $type;
        if ($eventTrafficType === null || trim($eventTrafficType) === '') {
            $reqProof = isset($_SERVER['HTTP_X_PAYMENT']) ? strtolower(trim((string)$_SERVER['HTTP_X_PAYMENT'])) : '';
            if ($isPaid) {
                $eventTrafficType = 'paid';
            } elseif ($cleanTx === 'demo' || $reqProof === 'demo') {
                $eventTrafficType = 'sandbox';
            } elseif ($statusCode === 402) {
                $eventTrafficType = 'challenge_402';
            } elseif ($statusCode === 403) {
                $eventTrafficType = 'blocked_403';
            } else {
                $eventTrafficType = 'free';
            }
        }

        // 0. Metadados de origem do pagamento e categoria de falha
        // (opcionais; eventos legados sao inferidos a partir da prova/status)
        $paymentSource = null;
        if (isset($meta['payment_source']) && is_string($meta['payment_source'])) {
            $candidate = strtolower(trim($meta['payment_source']));
            if ($candidate !== '') {
                $paymentSource = $candidate;
            }
        }
        $failureCategory = null;
        if (isset($meta['failure_category']) && is_string($meta['failure_category'])) {
            $candidate = strtolower(trim($meta['failure_category']));
            if ($candidate !== '') {
                $failureCategory = $candidate;
            }
        }
        $settlementTx = null;
        if (isset($meta['settlement_tx']) && is_string($meta['settlement_tx'])) {
            $candidate = strtolower(trim($meta['settlement_tx']));
            if ($candidate !== '') {
                $settlementTx = $candidate;
            }
        } elseif ($isPaid && str_starts_with($cleanTx, '0x')) {
            $settlementTx = $cleanTx;
        }

        // Inferencia para provas legadas que nao enviam metadados
        if ($paymentSource === null) {
            if ($eventTrafficType === 'sandbox' || $cleanTx === 'demo') {
                $paymentSource = 'sandbox';
            } elseif ($isPaid) {
                if (str_starts_with($cleanTx, 'pass_g402_')) {
                    $paymentSource = 'pass';
                } elseif (str_starts_with($cleanTx, '0x')) {
                    $paymentSource = 'tx_hash';
                } else {
                    $paymentSource = 'unknown';
                }
            }
        }

        $sourceDefaults = [
            'tx_hash' => 0,
            'pass' => 0,
            'facilitator' => 0,
            'facilitator_verify_only' => 0,
            'mercadopago' => 0,
            'rapidapi' => 0,
            'sandbox' => 0,
            'unknown' => 0,
        ];
        $failureDefaults = [
            'invalid_proof' => 0,
            'payment_rejected' => 0,
            'authorization_expired' => 0,
            'replay' => 0,
            'facilitator_unavailable' => 0,
            'facilitator_disabled' => 0,
            'sandbox_rate_limited' => 0,
            'unknown' => 0,
        ];
        if (!isset($metrics['payment_sources']) || !is_array($metrics['payment_sources'])) {
            $metrics['payment_sources'] = $sourceDefaults;
        }
        foreach ($sourceDefaults as $sourceKey => $unused) {
            if (!isset($metrics['payment_sources'][$sourceKey])) {
                $metrics['payment_sources'][$sourceKey] = 0;
            }
        }
        if (!isset($metrics['failure_categories']) || !is_array($metrics['failure_categories'])) {
            $metrics['failure_categories'] = $failureDefaults;
        }
        foreach ($failureDefaults as $failureKey => $unused) {
            if (!isset($metrics['failure_categories'][$failureKey])) {
                $metrics['failure_categories'][$failureKey] = 0;
            }
        }

        // Somente pagamentos liquidados faturam: sandbox e verify_only nao contam
        $nonBillableSources = ['sandbox', 'facilitator_verify_only'];
        $isBillablePaid = $isPaid && !in_array($paymentSource, $nonBillableSources, true);

        // 1. Contadores gerais
        $metrics['version'] = '1.0.0';
        $metrics['updated_at'] = $now;
        $metrics['total_requests'] = ((int)($metrics['total_requests'] ?? 0)) + 1;

        if (!isset($metrics['status_counts'])) {
            $metrics['status_counts'] = [];
        }
        $metrics['status_counts'][$statusStr] = ((int)($metrics['status_counts'][$statusStr] ?? 0)) + 1;

        if (!isset($metrics['service_counts'])) {
            $metrics['service_counts'] = [];
        }
        $metrics['service_counts'][$svc] = ((int)($metrics['service_counts'][$svc] ?? 0)) + 1;

        // 1.1 Contador separado de consultas sandbox/demo (nao geram receita)
        if ($eventTrafficType === 'sandbox') {
            $metrics['total_sandbox_requests'] = ((int)($metrics['total_sandbox_requests'] ?? 0)) + 1;
        }

        // 2. Faturamento apenas de pagamentos liquidados
        $effectivePrice = $priceUsdc ?? (float)GRID402_PRICE_DISPLAY;
        if ($isBillablePaid) {
            $metrics['total_paid_requests'] = ((int)($metrics['total_paid_requests'] ?? 0)) + 1;
            $metrics['total_revenue_usdc'] = round(((float)($metrics['total_revenue_usdc'] ?? 0)) + $effectivePrice, 4);
        }

        // 2.1 Contadores de origem do pagamento e de falhas por categoria
        if ($paymentSource !== null) {
            $metrics['payment_sources'][$paymentSource] = ((int)($metrics['payment_sources'][$paymentSource] ?? 0)) + 1;
        }
        if ($failureCategory !== null) {
            $metrics['failure_categories'][$failureCategory] = ((int)($metrics['failure_categories'][$failureCategory] ?? 0)) + 1;
        }

        // 3. Agrupamento diário
        if (!isset($metrics['daily'])) {
            $metrics['daily'] = [];
        }
        if (!isset($metrics['daily'][$today])) {
            $metrics['daily'][$today] = ['requests' => 0, 'page_views' => 0, 'paid' => 0, 'usdc' => 0.0];
        }
        $metrics['daily'][$today]['requests'] = ((int)$metrics['daily'][$today]['requests']) + 1;
        if ($isBillablePaid) {
            $metrics['daily'][$today]['paid'] = ((int)$metrics['daily'][$today]['paid']) + 1;
            $metrics['daily'][$today]['usdc'] = round(((float)$metrics['daily'][$today]['usdc']) + $effectivePrice, 4);
        }

        // Rotação determinística: manter apenas os 14 dias mais recentes no histórico
        ksort($metrics['daily']);
        if (count($metrics['daily']) > 14) {
            $metrics['daily'] = array_slice($metrics['daily'], -14, 14, true);
        }

        // 4. Buffer circular de eventos recentes (limite expandido para 100)
        if (!isset($metrics['recent_events']) || !is_array($metrics['recent_events'])) {
            $metrics['recent_events'] = [];
        }

        $maskedIp = grid402_mask_ip($ip ?? (string)($_SERVER['REMOTE_ADDR'] ?? ''));

        $event = [
            'timestamp' => $now,
            'service' => $svc,
            'status' => $statusCode,
            'is_paid' => $isBillablePaid,
            'type' => $eventTrafficType,
            'price_usdc' => $isBillablePaid ? (string)$effectivePrice : '0.0000',
            'tx_hash' => $txHash ? strtolower(trim($txHash)) : null,
            'settlement_tx' => $settlementTx,
            'payment_source' => $paymentSource,
            'failure_category' => $failureCategory,
            'ip_masked' => $maskedIp,
        ];

        array_unshift($metrics['recent_events'], $event);
        if (count($metrics['recent_events']) > 100) {
            $metrics['recent_events'] = array_slice($metrics['recent_events'], 0, 100);
        }

        // 5. Buffer circular de latencias (ultimas 200 amostras em ms para calculo de p50/p95)
        if (empty($meta['defer_latency_sample'])) {
            $sampleMs = null;
            if (isset($meta['latency_ms']) && is_numeric($meta['latency_ms'])) {
                $sampleMs = round((float) $meta['latency_ms'], 2);
            } elseif (function_exists('grid402_request_elapsed_ms')) {
                $sampleMs = grid402_request_elapsed_ms();
            }
            if ($sampleMs !== null && $sampleMs >= 0.0) {
                $metrics['latency_samples_ms'][] = $sampleMs;
                if (count($metrics['latency_samples_ms']) > 200) {
                    $metrics['latency_samples_ms'] = array_slice($metrics['latency_samples_ms'], -200);
                }
            }
        }

        // Gravação segura
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

/**
 * Registra atomicamente uma amostra de latencia (ms) no buffer circular de metrics.json.
 */
function grid402_record_latency_sample(float $latencyMs): void {
    if ($latencyMs < 0.0) {
        return;
    }
    $file = defined('GRID402_FILE_METRICS') ? GRID402_FILE_METRICS : (GRID402_PRIVATE_DIR . '/metrics.json');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $metrics = grid402_read_locked_json($fp, grid402_default_metrics());
        $metrics = grid402_normalize_metrics(is_array($metrics) ? $metrics : grid402_default_metrics());

        $metrics['latency_samples_ms'][] = round($latencyMs, 2);
        if (count($metrics['latency_samples_ms']) > 200) {
            $metrics['latency_samples_ms'] = array_slice($metrics['latency_samples_ms'], -200);
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

/**
 * Calcula percentil (ex: p50 ou p95) sobre um array de latencias em ms.
 */
function grid402_calculate_percentile(array $samples, float $percentile = 95.0): float {
    $vals = [];
    foreach ($samples as $v) {
        if (is_numeric($v)) {
            $vals[] = (float) $v;
        }
    }
    if (empty($vals)) {
        return 0.0;
    }
    sort($vals, SORT_NUMERIC);
    $n = count($vals);
    if ($n === 1) {
        return round($vals[0], 2);
    }
    $idx = (int) ceil(($percentile / 100.0) * $n) - 1;
    $idx = max(0, min($n - 1, $idx));
    return round($vals[$idx], 2);
}

/**
 * Registra uma visita à página pública (contador de acessos)
 *
 * Usado pelo canal HTML (home, institucionais, /agents, /pass).
 * Não substitui a telemetria técnica das APIs /api/v1/*.
 *
 * O IP nunca é armazenado em texto puro fora do buffer anonimizado (LGPD).
 */
/**
 * Ajusta o total de USDC faturado sem incrementar contador de requests.
 * Uso: compras de Session Pass (web3 top-up) cujo valor real depositado
 * difere do preço-tabela contabilizado no ponto de telemetria.
 */
function grid402_metrics_adjust_revenue(float $deltaUsdc): void {
    if ($deltaUsdc <= 0.0) {
        return;
    }
    $file = defined('GRID402_FILE_METRICS') ? GRID402_FILE_METRICS : (GRID402_PRIVATE_DIR . '/metrics.json');
    if (!file_exists($file)) {
        return;
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $metrics = grid402_read_locked_json($fp, []);
        if ($metrics === []) {
            $metrics = null;
        }
        if (is_array($metrics)) {
            $metrics['total_revenue_usdc'] = round((float)($metrics['total_revenue_usdc'] ?? 0.0) + $deltaUsdc, 4);
            $today = grid402_metrics_today();
            if (isset($metrics['daily'][$today]) && is_array($metrics['daily'][$today])) {
                $metrics['daily'][$today]['usdc'] = round((float)($metrics['daily'][$today]['usdc'] ?? 0.0) + $deltaUsdc, 4);
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function grid402_record_page_visit(?string $path = null, ?string $ip = null, ?string $ua = null): void {
    $file = GRID402_FILE_METRICS;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $metrics = grid402_read_locked_json($fp, grid402_default_metrics());
        if (!is_array($metrics)) {
            $metrics = grid402_default_metrics();
        }
        $metrics = grid402_normalize_metrics($metrics);

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $today = grid402_metrics_today();

        $metrics['updated_at'] = $now;
        $metrics['total_page_views'] = (int)($metrics['total_page_views'] ?? 0) + 1;
        $metrics['total_requests'] = (int)($metrics['total_requests'] ?? 0) + 1;

        if (!isset($metrics['daily'][$today])) {
            $metrics['daily'][$today] = ['requests' => 0, 'page_views' => 0, 'paid' => 0, 'usdc' => 0.0];
        }
        $metrics['daily'][$today]['requests'] = (int)($metrics['daily'][$today]['requests'] ?? 0) + 1;
        $metrics['daily'][$today]['page_views'] = (int)($metrics['daily'][$today]['page_views'] ?? 0) + 1;

        // Rotação determinística: manter apenas os 14 dias mais recentes
        ksort($metrics['daily']);
        if (count($metrics['daily']) > 14) {
            $metrics['daily'] = array_slice($metrics['daily'], -14, 14, true);
        }

        // Buffer leve de visitas (anonimizado, sem UA bruto em claro)
        if (!isset($metrics['recent_visits']) || !is_array($metrics['recent_visits'])) {
            $metrics['recent_visits'] = [];
        }
        $maxUa = $ua !== null ? trim($ua) : '';
        if ($maxUa !== '') {
            $maxUa = substr(preg_replace('/[\r\n\t]+/', ' ', $maxUa), 0, 180);
        }

        array_unshift($metrics['recent_visits'], [
            'timestamp' => $now,
            'path' => $path !== null ? substr(trim($path), 0, 180) : '/',
            'ip_masked' => grid402_mask_ip($ip ?? (string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'ua_masked' => $maxUa !== '' ? substr($maxUa, 0, 80) . '…' : '',
        ]);
        if (count($metrics['recent_visits']) > 50) {
            $metrics['recent_visits'] = array_slice($metrics['recent_visits'], 0, 50);
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

/**
 * Obtém estatísticas de Passes de Sessão Web3 (ativos, créditos emitidos e restantes)
 */
function grid402_get_passes_stats(): array {
    $file = defined('GRID402_FILE_PASSES') ? GRID402_FILE_PASSES : (defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR . '/session_passes.json' : __DIR__ . '/private_data/session_passes.json');
    if (!file_exists($file)) {
        return [
            'total_issued' => 0,
            'active_passes' => 0,
            'total_credits' => 0,
            'remaining_credits' => 0,
            'active_remaining_credits' => 0,
        ];
    }
    $raw = @file_get_contents($file);
    $passes = $raw ? (@json_decode($raw, true) ?: []) : [];
    $total = is_array($passes) ? count($passes) : 0;
    $active = 0;
    $totalCredits = 0;
    $remainingCredits = 0;
    $activeRemainingCredits = 0;
    $now = time();
    foreach ($passes as $p) {
        if (!is_array($p)) {
            continue;
        }
        if (function_exists('grid402_normalize_pass_entry')) {
            $p = grid402_normalize_pass_entry($p);
        } elseif (($p['credit_scale'] ?? '') !== 'v2_5000') {
            $rawTotal = (int) ($p['credits_total'] ?? $p['initial_credits'] ?? 0);
            $rawRem = (int) ($p['credits_remaining'] ?? 0);
            if ($rawTotal >= 4000) {
                $p['credits_total'] = max(1, intdiv($rawTotal, 100));
                $p['credits_remaining'] = max(0, intdiv($rawRem, 100));
            }
        }

        $exp = (int) ($p['expires_epoch'] ?? 0);
        if ($exp <= 0 && !empty($p['expires_at'])) {
            $parsedExp = is_numeric($p['expires_at'])
                ? (int) $p['expires_at']
                : @strtotime((string) $p['expires_at']);
            $exp = $parsedExp !== false ? (int) $parsedExp : 0;
        }

        $rem = max(0, (int) ($p['credits_remaining'] ?? 0));
        $init = max(0, (int) ($p['credits_total'] ?? $p['initial_credits'] ?? 0));
        $totalCredits += $init;
        $remainingCredits += $rem;
        if ($exp > $now && $rem > 0) {
            $active++;
            $activeRemainingCredits += $rem;
        }
    }
    return [
        'total_issued' => $total,
        'active_passes' => $active,
        'total_credits' => $totalCredits,
        'remaining_credits' => $remainingCredits,
        'active_remaining_credits' => $activeRemainingCredits,
    ];
}

/**
 * Computa indicadores analíticos avançados para o painel administrativo:
 * - ARPU (Ticket Médio em USDC e BRL)
 * - SLA Técnico (Disponibilidade HTTP sem erros 5xx %)
 * - Taxa de Adoção de Sandbox (%)
 * - Histórico Diário ordenado dos últimos 14 dias (com preenchimento contínuo)
 * - Top Agentes / IPs Consumidores (anonimizados conforme LGPD)
 * - Rentabilidade Estimada por Micro-Serviço com base nos preços de tabela USDC
 * - Estatísticas de Passes de Sessão Web3 Ativos
 */
function grid402_compute_admin_analytics(array $metrics, float $usdRate = 5.50): array {
    $totalRequests = (int)($metrics['total_requests'] ?? 0);
    $totalPaid = (int)($metrics['total_paid_requests'] ?? 0);
    $totalSandbox = (int)($metrics['total_sandbox_requests'] ?? 0);
    $totalRevenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
    $statusCounts = is_array($metrics['status_counts'] ?? null) ? $metrics['status_counts'] : [];
    $serviceCounts = is_array($metrics['service_counts'] ?? null) ? $metrics['service_counts'] : [];
    $recentEvents = is_array($metrics['recent_events'] ?? null) ? $metrics['recent_events'] : [];
    $rawDaily = is_array($metrics['daily'] ?? null) ? $metrics['daily'] : [];

    // 1. ARPU (Ticket Médio por consulta paga)
    $arpuUsdc = $totalPaid > 0 ? round($totalRevenueUsdc / $totalPaid, 6) : 0.0;
    $arpuBrl = round($arpuUsdc * $usdRate, 4);

    // 2. SLA Técnico sem erros 5xx (desafios 402 e page_views 200 HTML sao comportamento esperado)
    $ok200 = (int)($statusCounts['200'] ?? 0);
    $error5xx = (int)($statusCounts['500'] ?? 0)
        + (int)($statusCounts['502'] ?? 0)
        + (int)($statusCounts['503'] ?? 0)
        + (int)($statusCounts['504'] ?? 0);
    $technicalSuccessRate = $totalRequests > 0 ? round((max(0, $totalRequests - $error5xx) / $totalRequests) * 100, 1) : 100.0;
    $http200Rate = $totalRequests > 0 ? round(($ok200 / $totalRequests) * 100, 1) : 100.0;

    // 3. Taxa de Adoção Sandbox (%)
    $sandboxRate = $totalRequests > 0 ? round(($totalSandbox / $totalRequests) * 100, 1) : 0.0;

    // 4. Histórico dos últimos 14 dias contínuo (preenchendo eventuais dias sem tráfego)
    $dailyHistory14d = [];
    $todayTs = strtotime(grid402_metrics_today());
    for ($i = 13; $i >= 0; $i--) {
        $dayStr = gmdate('Y-m-d', $todayTs - ($i * 86400));
        $dayData = $rawDaily[$dayStr] ?? ['requests' => 0, 'page_views' => 0, 'paid' => 0, 'usdc' => 0.0];
        $dailyHistory14d[] = [
            'date' => $dayStr,
            'label' => gmdate('d/m', strtotime($dayStr)),
            'requests' => (int)($dayData['requests'] ?? 0),
            'page_views' => (int)($dayData['page_views'] ?? 0),
            'paid' => (int)($dayData['paid'] ?? 0),
            'usdc' => (float)($dayData['usdc'] ?? 0.0),
        ];
    }

    // 5. Top Agentes / IPs Consumidores (LGPD) a partir de recent_events
    $ipAggregates = [];
    foreach ($recentEvents as $ev) {
        $maskedIp = (string)($ev['ip_masked'] ?? 'xxx.xxx.xxx.xxx');
        if (!isset($ipAggregates[$maskedIp])) {
            $ipAggregates[$maskedIp] = [
                'ip_masked' => $maskedIp,
                'total' => 0,
                'paid' => 0,
                'sandbox' => 0,
                'challenges' => 0,
                'blocked' => 0,
                'services' => [],
                'last_seen' => (string)($ev['timestamp'] ?? ''),
            ];
        }
        $ipAggregates[$maskedIp]['total']++;
        $evType = (string)($ev['type'] ?? '');
        $status = (int)($ev['status'] ?? 200);
        if ($evType === 'paid' || !empty($ev['is_paid'])) {
            $ipAggregates[$maskedIp]['paid']++;
        } elseif ($evType === 'sandbox') {
            $ipAggregates[$maskedIp]['sandbox']++;
        } elseif ($status === 402 || $evType === 'challenge_402') {
            $ipAggregates[$maskedIp]['challenges']++;
        } elseif ($status === 403 || $evType === 'blocked_403') {
            $ipAggregates[$maskedIp]['blocked']++;
        }

        $svc = (string)($ev['service'] ?? '');
        if ($svc !== '') {
            $ipAggregates[$maskedIp]['services'][$svc] = ($ipAggregates[$maskedIp]['services'][$svc] ?? 0) + 1;
        }
    }

    // Ordena os IPs por maior total de requisições
    uasort($ipAggregates, fn($a, $b) => $b['total'] <=> $a['total']);
    $topConsumers = [];
    $rank = 1;
    foreach ($ipAggregates as $ip => $data) {
        // Encontra o serviço favorito deste IP
        arsort($data['services']);
        $favService = key($data['services']) ?: 'forex';
        
        // Perfil heurístico do consumidor
        $profile = 'Agente IA';
        if ($data['paid'] > 0) {
            $profile = 'Agente Pagador (x402)';
        } elseif ($data['sandbox'] > 0) {
            $profile = 'Dev em Teste (Sandbox)';
        } elseif ($data['challenges'] > 5 && $data['paid'] === 0) {
            $profile = 'Agente sem Wallet';
        }

        $topConsumers[] = [
            'rank' => $rank++,
            'ip_masked' => $data['ip_masked'],
            'total' => $data['total'],
            'paid' => $data['paid'],
            'sandbox' => $data['sandbox'],
            'challenges' => $data['challenges'],
            'favorite_service' => $favService,
            'profile' => $profile,
            'last_seen' => $data['last_seen'],
        ];
        if ($rank > 10) {
            break;
        }
    }

    // 6. Rentabilidade Estimada por Micro-Serviço
    $pricingTable = [
        'dossier' => 0.0500,
        'compliance' => 0.0300,
        'kyb-audit' => 0.0250,
        'ofac' => 0.0200,
        'lei' => 0.0100,
        'cnpj' => 0.0100,
        'domain-audit' => 0.0150,
        'market-snapshot' => 0.0150,
        'sanctions' => 0.0100,
        'simples' => 0.0100,
        'pass' => 2.0000,
        'extract' => 0.0050,
        'email-verify' => 0.0050,
        'notarize' => 0.0050,
        'forex' => 0.0050,
        'crypto' => 0.0050,
        'indicators' => 0.0050,
        'holidays' => 0.0050,
        'workdays' => 0.0050,
        'global-markets' => 0.0050,
        'dns' => 0.0050,
        'ssl' => 0.0050,
        'asn' => 0.0050,
        'cep' => 0.0050,
        'summary' => 0.0000,
    ];

    $serviceRevenue = [];
    foreach ($serviceCounts as $svcName => $count) {
        $price = $pricingTable[$svcName] ?? 0.0050;
        $serviceRevenue[$svcName] = [
            'service' => $svcName,
            'count' => (int)$count,
            'unit_price' => $price,
            'estimated_usdc' => round(((int)$count) * $price, 4),
        ];
    }
    // Ordena da maior receita estimada para a menor
    uasort($serviceRevenue, fn($a, $b) => $b['estimated_usdc'] <=> $a['estimated_usdc']);

    $latencySamples = is_array($metrics['latency_samples_ms'] ?? null) ? $metrics['latency_samples_ms'] : [];
    $latencyP50 = grid402_calculate_percentile($latencySamples, 50.0);
    $latencyP95 = grid402_calculate_percentile($latencySamples, 95.0);

    return [
        'arpu_usdc' => $arpuUsdc,
        'arpu_brl' => $arpuBrl,
        'technical_success_rate' => $technicalSuccessRate,
        'http_200_rate' => $http200Rate,
        'http_200_count' => $ok200,
        'error_5xx_count' => $error5xx,
        'sandbox_rate' => $sandboxRate,
        'latency_p50_ms' => $latencyP50,
        'latency_p95_ms' => $latencyP95,
        'latency_samples_count' => count($latencySamples),
        'daily_history_14d' => $dailyHistory14d,
        'top_consumers' => $topConsumers,
        'service_revenue' => array_values($serviceRevenue),
        'passes_stats' => grid402_get_passes_stats(),
    ];
}

