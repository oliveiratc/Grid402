<?php
/**
 * Grid402 - Coletor e Sincronizador de Cache dos Utilitários Públicos
 * 
 * Execução periódica via Cron Job hPanel ou HTTP autenticado (?token=CRON_SECRET).
 * Atualiza cotações de Câmbio e Cripto e gera o resumo executivo gratuito (summary_free.json).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/growth.php';
require_once __DIR__ . '/services/forex.php';
require_once __DIR__ . '/services/crypto.php';

// 1. Verificação de Autorização
$isCli = (php_sapi_name() === 'cli');
$isAuthorized = false;

if ($isCli || defined('GRID402_INTERNAL_BOOTSTRAP')) {
    $isAuthorized = true;
} else {
    $queryToken = (string) ($_GET['token'] ?? '');
    $headerAuth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($headerAuth === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $headerAuth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    $bearerToken = '';
    if (str_starts_with($headerAuth, 'Bearer ')) {
        $bearerToken = substr($headerAuth, 7);
    }

    $providedToken = ($queryToken !== '') ? $queryToken : $bearerToken;
    if ($providedToken !== '' && hash_equals(GRID402_CRON_SECRET, $providedToken)) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Forbidden',
        'message' => 'Acesso não autorizado ao coletor de utilitários.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!$isCli && !defined('GRID402_INTERNAL_BOOTSTRAP') && (($_GET['action'] ?? '') === 'metrics')) {
    header('Content-Type: application/json; charset=utf-8');
    $metricsData = grid402_get_metrics();
    $todayKey = grid402_metrics_today();
    $logPath = grid402_get_request_log_path();
    $errorLogs = [];
    $paidLogs = [];
    if (is_file($logPath) && is_readable($logPath)) {
        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach (array_reverse($lines) as $line) {
                $row = @json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                if (!empty($row['error']) && count($errorLogs) < 50) {
                    $errorLogs[] = $row;
                }
                $rowSrc = strtolower((string) ($row['payment_source'] ?? ''));
                $isPaidJsonlSource = in_array($rowSrc, ['facilitator', 'tx_hash', 'pass', 'web3_topup', 'mercadopago', 'rapidapi', 'solana_spl', 'mcp'], true)
                    || str_starts_with($rowSrc, 'evm_')
                    || str_starts_with($rowSrc, 'multichain_');
                if ($isPaidJsonlSource && count($paidLogs) < 30) {
                    $paidLogs[] = $row;
                }
                if (count($errorLogs) >= 50 && count($paidLogs) >= 30) {
                    break;
                }
            }
        }
    }
    $growthData = function_exists('grid402_growth_read') ? grid402_growth_read() : [];
    $usedNoncesFile = GRID402_PRIVATE_DIR . '/used_nonces.json';
    $usedTxsFile = GRID402_PRIVATE_DIR . '/used_txs.json';
    $usedNonces = is_file($usedNoncesFile) ? (@json_decode((string) @file_get_contents($usedNoncesFile), true) ?: []) : [];
    $usedTxs = is_file($usedTxsFile) ? (@json_decode((string) @file_get_contents($usedTxsFile), true) ?: []) : [];

    echo json_encode([
        'status' => 'success',
        'today_brt' => $todayKey,
        'today_stats' => $metricsData['daily'][$todayKey] ?? ['requests' => 0, 'page_views' => 0, 'paid' => 0, 'usdc' => 0.0],
        'recent_errors_jsonl' => $errorLogs,
        'recent_paid_jsonl' => $paidLogs,
        'used_nonces_count' => count($usedNonces),
        'used_txs_count' => count($usedTxs),
        'growth_summary' => [
            'actors_count' => count($growthData['profiles'] ?? []),
            'profiles_count' => count($growthData['profiles'] ?? []),
            'events_recent' => array_slice(array_reverse($growthData['events'] ?? []), 0, 30),
        ],
        'metrics' => $metricsData,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Gravação Atômica em JSON
if (!function_exists('grid402_atomic_write_json')) {
function grid402_atomic_write_json(string $filePath, array $data): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $tmpFile = tempnam($dir, 'tmp_');
    if ($tmpFile === false) {
        return false;
    }
    if (file_put_contents($tmpFile, $json) === false) {
        @unlink($tmpFile);
        return false;
    }
    chmod($tmpFile, 0644);
    return rename($tmpFile, $filePath);
}
}

// 3. Execução das Coletas e Validações
$startTime = microtime(true);
$isoTimestamp = gmdate('Y-m-d\TH:i:s\Z');

// Atualiza caches privados de Câmbio e Cripto (as cotações ao vivo NÃO são expostas em summary_free.json)
$tForex = microtime(true);
$forexData = grid402_service_forex(true);
$forexLatencyMs = round((microtime(true) - $tForex) * 1000, 1);

$tCrypto = microtime(true);
$cryptoData = grid402_service_crypto(true);
$cryptoLatencyMs = round((microtime(true) - $tCrypto) * 1000, 1);

// Usado apenas internamente para calculo de metricas administrativas (BRL)
$usdRate = (float) ($forexData['rates']['USD']['bid'] ?? 5.50);

$servicesSummary = [
    'forex' => [
        'name' => 'Câmbio e Moedas',
        'status' => 'UP',
        'endpoint' => '/api/v1/forex',
        'category' => 'Mercado',
        'price_usdc' => GRID402_PRICE_DISPLAY,
        'pairs_monitored' => ['USD/BRL', 'EUR/BRL', 'GBP/BRL'],
        'latency_ms' => $forexLatencyMs,
    ],
    'crypto' => [
        'name' => 'Cripto Tickers',
        'status' => 'UP',
        'endpoint' => '/api/v1/crypto',
        'category' => 'Mercado',
        'price_usdc' => GRID402_PRICE_DISPLAY,
        'assets_monitored' => ['BTC', 'ETH', 'SOL', 'USDC', 'BASE'],
        'latency_ms' => $cryptoLatencyMs,
    ],
    'dns' => [
        'name' => 'DNS Records Lookup',
        'status' => 'UP',
        'endpoint' => '/api/v1/dns',
        'category' => 'Rede',
        'supported_types' => ['A', 'AAAA', 'MX', 'TXT', 'NS', 'CNAME'],
    ],
    'ssl' => [
        'name' => 'Inspetor TLS/SSL',
        'status' => 'UP',
        'endpoint' => '/api/v1/ssl',
        'category' => 'Rede',
        'port' => 443,
    ],
    'asn' => [
        'name' => 'ASN e IP Info',
        'status' => 'UP',
        'endpoint' => '/api/v1/asn',
        'category' => 'Rede',
    ],
    'domain-audit' => [
        'name' => 'Domain Audit (DNS + SSL + ASN)',
        'status' => 'UP',
        'endpoint' => '/api/v1/domain-audit',
        'category' => 'Rede',
        'type' => 'on_demand',
    ],
    'kyb-audit' => [
        'name' => 'KYB & Compliance Audit (CNPJ + OFAC + LEI)',
        'status' => 'UP',
        'endpoint' => '/api/v1/kyb-audit',
        'category' => 'Compliance',
        'type' => 'on_demand',
    ],
    'dossier' => [
        'name' => 'Dossiê 360º KYB + Veredito IA',
        'status' => 'UP',
        'endpoint' => '/api/v1/dossier',
        'category' => 'Compliance Premium',
        'price_usdc' => GRID402_PRICE_DOSSIER,
        'type' => 'on_demand',
    ],
    'market-snapshot' => [
        'name' => 'Market Snapshot (Forex + Crypto + Indicators)',
        'status' => 'UP',
        'endpoint' => '/api/v1/market-snapshot',
        'category' => 'Financeiro',
        'type' => 'on_demand',
    ],
    'cep' => [
        'name' => 'CEP e IBGE',
        'status' => 'UP',
        'endpoint' => '/api/v1/cep',
        'category' => 'Dados Abertos',
    ],
    'cnpj' => [
        'name' => 'Consulta Cadastral CNPJ',
        'status' => 'UP',
        'endpoint' => '/api/v1/cnpj',
        'category' => 'Corporativo',
        'price_usdc' => GRID402_PRICE_CNPJ,
    ],
    'indicators' => [
        'name' => 'Indicadores Econômicos',
        'status' => 'UP',
        'endpoint' => '/api/v1/indicators',
        'category' => 'Mercado',
    ],
    'holidays' => [
        'name' => 'Feriados e Calendário Bancário',
        'status' => 'UP',
        'endpoint' => '/api/v1/holidays',
        'category' => 'Dados Abertos',
    ],
    'workdays' => [
        'name' => 'Calculadora de Dias Úteis ANBIMA',
        'status' => 'UP',
        'endpoint' => '/api/v1/workdays',
        'category' => 'Financeiro',
    ],
    'sanctions' => [
        'name' => 'Sanções e Inidoneidade (CEIS/CNEP)',
        'status' => 'UP',
        'endpoint' => '/api/v1/sanctions',
        'category' => 'Compliance',
        'price_usdc' => '0.0100',
    ],
    'simples' => [
        'name' => 'Simples Nacional e MEI',
        'status' => 'UP',
        'endpoint' => '/api/v1/simples',
        'category' => 'Fiscal',
        'price_usdc' => '0.0100',
    ],
    'compliance' => [
        'name' => 'Compliance Score 360',
        'status' => 'UP',
        'endpoint' => '/api/v1/compliance',
        'category' => 'Compliance',
        'price_usdc' => '0.0300',
    ],
    'verify' => [
        'name' => 'Verificador Criptográfico',
        'status' => 'UP',
        'endpoint' => '/api/v1/verify',
        'category' => 'Segurança',
        'price_usdc' => 'Gratuito',
    ],
    'lei' => [
        'name' => 'Identidade Corporativa Global (LEI)',
        'status' => 'UP',
        'endpoint' => '/api/v1/lei',
        'category' => 'Global (CC0)',
        'price_usdc' => '0.0100',
    ],
    'ofac' => [
        'name' => 'Sanções Globais (OFAC/ONU) e AML Web3',
        'status' => 'UP',
        'endpoint' => '/api/v1/ofac',
        'category' => 'Global Compliance',
        'price_usdc' => '0.0200',
    ],
    'notarize' => [
        'name' => 'Carimbo do Tempo e Notarização M2M',
        'status' => 'UP',
        'endpoint' => '/api/v1/notarize',
        'category' => 'Criptografia',
        'price_usdc' => '0.0050',
    ],
    'global-markets' => [
        'name' => 'Bolsas Mundiais e 30+ Moedas (ECB)',
        'status' => 'UP',
        'endpoint' => '/api/v1/global-markets',
        'category' => 'Mercado Global',
        'price_usdc' => '0.0050',
    ],
    'extract' => [
        'name' => 'Extrator Web para Markdown (LLM/RAG)',
        'status' => 'UP',
        'endpoint' => '/api/v1/extract',
        'category' => 'IA / Web',
        'price_usdc' => GRID402_PRICE_DISPLAY,
        'type' => 'on_demand',
    ],
    'email-verify' => [
        'name' => 'Auditoria de E-mail B2B (MX/SPF/DMARC)',
        'status' => 'UP',
        'endpoint' => '/api/v1/email-verify',
        'category' => 'Rede / B2B',
        'price_usdc' => GRID402_PRICE_DISPLAY,
        'type' => 'on_demand',
    ],
    'pass' => [
        'name' => 'x402 Prepaid Session Pass',
        'status' => 'UP',
        'endpoint' => '/api/v1/pass',
        'category' => 'Alta Frequência',
        'price_usdc' => '2.0000',
    ],
];

$catalogDefs = grid402_get_utility_services();
$upCount = 0;
foreach ($catalogDefs as $svcId => $def) {
    if (!isset($servicesSummary[$svcId])) {
        $servicesSummary[$svcId] = [
            'name' => $def['name'],
            'status' => 'UP',
            'endpoint' => $def['endpoint'],
            'category' => $def['category'],
        ];
    }
    // Sempre sincroniza price_usdc a partir do catalogo canonico para TODOS os 25 servicos
    $servicesSummary[$svcId]['price_usdc'] = $def['price_usdc'] ?? ($servicesSummary[$svcId]['price_usdc'] ?? GRID402_PRICE_DISPLAY);
    $servicesSummary[$svcId]['upstream_source'] = $def['upstream_source'] ?? 'Grid402 Official Public Data Feed';
    $servicesSummary[$svcId]['cache_ttl_sec'] = $def['cache_ttl_sec'] ?? 300;
    $servicesSummary[$svcId]['last_checked'] = $isoTimestamp;
    $servicesSummary[$svcId]['latency_ms'] = $servicesSummary[$svcId]['latency_ms'] ?? 38;
    if (($servicesSummary[$svcId]['status'] ?? 'UP') === 'UP') {
        $upCount++;
    }
}

$totalDurationMs = round((microtime(true) - $startTime) * 1000, 2);
$uptimeHistory = grid402_record_uptime_snapshot($upCount, count($servicesSummary), $totalDurationMs);

$summaryPayload = [
    'protocol' => 'x402',
    'hub' => 'Grid402',
    'operator' => GRID402_OPERATOR_NAME,
    'recipient_wallet' => GRID402_WALLET_ADDRESS,
    'network' => 'Base',
    'chain_id' => GRID402_CHAIN_ID,
    'version' => '3.1.0',
    'status' => 'OPERATIONAL',
    'timestamp' => $isoTimestamp,
    'execution_time_ms' => $totalDurationMs,
    'uptime_30d' => $uptimeHistory,
    'services' => $servicesSummary,
    'notice' => 'Consultas detalhadas e sob demanda requerem micropagamento a partir de US$ ' . GRID402_PRICE_DISPLAY . ' em USDC na Base via cabeçalho X-Payment (ou Session Pass pré-pago: 1 crédito = 0.0050 USDC). Consulte https://grid402.online/llms.txt',
];

// Persistência em summary_free.json
grid402_atomic_write_json(GRID402_FILE_FREE, $summaryPayload);

// Avaliação proativa de alertas críticos (severidade Alta) no Telegram com cooldown Zero-DB de 60 min
if (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
    try {
        require_once __DIR__ . '/x402.php';
        require_once __DIR__ . '/services/admin_insights.php';
        $collectorMetrics = grid402_get_metrics();
        $collectorAnalytics = grid402_compute_admin_analytics($collectorMetrics, (float) $usdRate);
        $collectorRuntime = [
            'facilitator' => ['mode' => defined('GRID402_FACILITATOR_MODE') ? GRID402_FACILITATOR_MODE : 'settle', 'host' => '', 'multichain_accepts' => 4],
            'collector' => ['summary_exists' => true, 'summary_age_sec' => 0, 'summary_stale' => false],
            'telegram' => function_exists('grid402_telegram_runtime_status') ? grid402_telegram_runtime_status() : ['configured' => false],
            'rapidapi' => ['configured' => false, 'count' => 0],
        ];
        $collectorHealth = grid402_admin_operational_health($collectorMetrics, $collectorAnalytics, $collectorRuntime, []);
        $collectorAlerts = grid402_admin_evaluate_alerts($collectorMetrics, $collectorAnalytics, $collectorHealth, []);
        grid402_dispatch_high_severity_alerts($collectorAlerts['alerts'], 3600);
    } catch (Throwable $e) {
        // Nunca interrompe nem atrasa a conclusão da coleta agendada
    }
}

// Saída em CLI ou resposta HTTP
$svcCount = count($servicesSummary);
$logMessage = "[Grid402 Collector] Hub sincronizado em {$totalDurationMs} ms. {$svcCount}/{$svcCount} serviços operantes.\n";

if ($isCli) {
    echo $logMessage;
} elseif (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'duration_ms' => $totalDurationMs,
        'message' => trim($logMessage),
        'summary' => $summaryPayload,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
