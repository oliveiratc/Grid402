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
            'summary' => 0,
        ],
        'daily' => [],
        'recent_events' => [],
    ];
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
    return is_array($data) ? $data : grid402_default_metrics();
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
    ?string $type = null
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
        $filesize = filesize($file);
        $content = ($filesize > 0) ? (string) fread($fp, $filesize) : '';
        $metrics = ($content !== '') ? (@json_decode($content, true) ?: grid402_default_metrics()) : grid402_default_metrics();

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $today = gmdate('Y-m-d');
        $statusStr = (string)$statusCode;
        $svc = strtolower(trim($service));
        if ($svc === '') {
            $svc = 'summary';
        }

        // Determinacao da tipagem do evento de telemetria
        $eventTrafficType = $type;
        if ($eventTrafficType === null || trim($eventTrafficType) === '') {
            $cleanTx = strtolower(trim((string)$txHash));
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

        // 2. Faturamento se pago
        $effectivePrice = $priceUsdc ?? (float)GRID402_PRICE_DISPLAY;
        if ($isPaid) {
            $metrics['total_paid_requests'] = ((int)($metrics['total_paid_requests'] ?? 0)) + 1;
            $metrics['total_revenue_usdc'] = round(((float)($metrics['total_revenue_usdc'] ?? 0)) + $effectivePrice, 4);
        }

        // 3. Agrupamento diário
        if (!isset($metrics['daily'])) {
            $metrics['daily'] = [];
        }
        if (!isset($metrics['daily'][$today])) {
            $metrics['daily'][$today] = ['requests' => 0, 'paid' => 0, 'usdc' => 0.0];
        }
        $metrics['daily'][$today]['requests'] = ((int)$metrics['daily'][$today]['requests']) + 1;
        if ($isPaid) {
            $metrics['daily'][$today]['paid'] = ((int)$metrics['daily'][$today]['paid']) + 1;
            $metrics['daily'][$today]['usdc'] = round(((float)$metrics['daily'][$today]['usdc']) + $effectivePrice, 4);
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
            'is_paid' => $isPaid,
            'type' => $eventTrafficType,
            'price_usdc' => $isPaid ? (string)$effectivePrice : '0.0000',
            'tx_hash' => $txHash ? strtolower(trim($txHash)) : null,
            'ip_masked' => $maskedIp,
        ];

        array_unshift($metrics['recent_events'], $event);
        if (count($metrics['recent_events']) > 100) {
            $metrics['recent_events'] = array_slice($metrics['recent_events'], 0, 100);
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

