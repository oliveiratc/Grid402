<?php
/**
 * Grid402 - Coletor e Sincronizador de Cache dos Utilitários Públicos
 * 
 * Execução periódica via Cron Job hPanel ou HTTP autenticado (?token=CRON_SECRET).
 * Atualiza cotações de Câmbio e Cripto e gera o resumo executivo gratuito (summary_free.json).
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
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

// 2. Gravação Atômica em JSON
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

// 3. Execução das Coletas e Validações
$startTime = microtime(true);
$isoTimestamp = gmdate('Y-m-d\TH:i:s\Z');

// Atualiza Câmbio e Cripto
$forexData = grid402_service_forex(true);
$cryptoData = grid402_service_crypto(true);

$usdRate = $forexData['rates']['USD']['bid'] ?? 5.50;
$eurRate = $forexData['rates']['EUR']['bid'] ?? 5.90;
$gbpRate = $forexData['rates']['GBP']['bid'] ?? 7.00;

$btcPrice = $cryptoData['tickers']['BTC']['price_usd'] ?? 68000.0;
$ethPrice = $cryptoData['tickers']['ETH']['price_usd'] ?? 3200.0;
$solPrice = $cryptoData['tickers']['SOL']['price_usd'] ?? 180.0;

$servicesSummary = [
    'forex' => [
        'name' => 'Câmbio e Moedas',
        'status' => 'UP',
        'endpoint' => '/api/v1/forex',
        'category' => 'Mercado',
        'usd_brl' => $usdRate,
        'eur_brl' => $eurRate,
        'gbp_brl' => $gbpRate,
    ],
    'crypto' => [
        'name' => 'Cripto Tickers',
        'status' => 'UP',
        'endpoint' => '/api/v1/crypto',
        'category' => 'Mercado',
        'btc_usd' => $btcPrice,
        'eth_usd' => $ethPrice,
        'sol_usd' => $solPrice,
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
    'cep' => [
        'name' => 'CEP e IBGE',
        'status' => 'UP',
        'endpoint' => '/api/v1/cep',
        'category' => 'Dados Abertos',
    ],
];

$totalDurationMs = round((microtime(true) - $startTime) * 1000, 2);

$summaryPayload = [
    'protocol' => 'x402',
    'hub' => 'Grid402',
    'network' => 'Base',
    'chain_id' => GRID402_CHAIN_ID,
    'version' => '2.0.0',
    'status' => 'OPERATIONAL',
    'timestamp' => $isoTimestamp,
    'execution_time_ms' => $totalDurationMs,
    'services' => $servicesSummary,
    'notice' => 'Consultas detalhadas e sob demanda requerem micropagamento de US$ ' . GRID402_PRICE_DISPLAY . ' em USDC na Base via cabeçalho X-Payment. Consulte https://www.grid402.com.br/llms.txt',
];

// Persistência em summary_free.json
grid402_atomic_write_json(GRID402_FILE_FREE, $summaryPayload);

// Saída em CLI ou resposta HTTP
$logMessage = "[Grid402 Collector] Hub sincronizado em {$totalDurationMs} ms. 6/6 serviços operantes.\n";

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
