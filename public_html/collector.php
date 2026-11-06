<?php
/**
 * Grid402 - Coletor Assíncrono Paralelo de Telemetria
 * 
 * Execução via CLI (Cron Job hPanel) ou HTTP autenticado (?token=CRON_SECRET).
 * Utiliza curl_multi_init para coletar de 15 a 20 alvos em < 4.0 segundos.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';

// 1. Verificação de Autorização
$isCli = (php_sapi_name() === 'cli');
$isAuthorized = false;

if ($isCli) {
    $isAuthorized = true;
} else {
    // Verificação via Query String ou Bearer Token
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
        'message' => 'Acesso não autorizado ao coletor de telemetria.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2. Função de Escrita Atômica
function grid402_atomic_write_json(string $filePath, array $data): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
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

// 3. Inicialização da Sondagem Paralela via cURL Multi
$targets = grid402_get_targets();
$mh = curl_multi_init();
$handles = [];
$startTime = microtime(true);
$isoTimestamp = gmdate('Y-m-d\TH:i:s\Z');

foreach ($targets as $target) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $target['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT_MS => GRID402_TIMEOUT_MS,
        CURLOPT_CONNECTTIMEOUT_MS => GRID402_CONNECT_TIMEOUT_MS,
        CURLOPT_USERAGENT => GRID402_USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => GRID402_SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => GRID402_SSL_VERIFY ? 2 : 0,
        CURLOPT_NOBODY => true, // Envia HEAD rápido para economia de banda
    ]);

    curl_multi_add_handle($mh, $ch);
    $handles[$target['id']] = [
        'handle' => $ch,
        'target' => $target,
    ];
}

// Execução assíncrona não-bloqueante
do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
        if (curl_multi_select($mh, 0.05) === -1) {
            usleep(10000);
        }
    }
} while ($active && $status === CURLM_OK);

$totalDurationMs = round((microtime(true) - $startTime) * 1000, 2);

// 4. Processamento dos Resultados Individuais
$detailedResults = [];
$categories = [];
$totalUp = 0;

foreach ($handles as $id => $item) {
    $ch = $item['handle'];
    $target = $item['target'];

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTimeMs = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 2);
    $connectTimeMs = round((float) curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000, 2);
    $dnsTimeMs = round((float) curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME) * 1000, 2);
    $primaryIp = (string) (curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: '0.0.0.0');
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);

    // Avaliação de Status
    $category = $target['category'];
    if (!isset($categories[$category])) {
        $categories[$category] = [
            'total' => 0,
            'up' => 0,
            'total_latency' => 0.0,
        ];
    }
    $categories[$category]['total']++;

    $statusStr = 'DOWN';
    $stabilityScore = 0;

    if ($curlErrno === 0) {
        $expected = $target['expected_codes'];
        if (in_array($httpCode, $expected, true) || ($httpCode >= 200 && $httpCode < 400)) {
            $statusStr = 'UP';
            $totalUp++;
            $categories[$category]['up']++;
            $categories[$category]['total_latency'] += $totalTimeMs;

            if ($totalTimeMs < 300) {
                $stabilityScore = 98;
            } elseif ($totalTimeMs < 800) {
                $stabilityScore = 90;
            } elseif ($totalTimeMs < 1500) {
                $stabilityScore = 75;
            } else {
                $stabilityScore = 60;
            }
        } elseif ($httpCode >= 500) {
            $statusStr = 'DOWN';
            $stabilityScore = 10;
        } else {
            $statusStr = 'DEGRADED';
            $categories[$category]['up']++;
            $categories[$category]['total_latency'] += $totalTimeMs;
            $stabilityScore = 50;
        }
    } else {
        $statusStr = ($curlErrno === CURLE_OPERATION_TIMEDOUT) ? 'TIMEOUT' : 'DOWN';
        $stabilityScore = 0;
    }

    $detailedResults[] = [
        'id' => $target['id'],
        'name' => $target['name'],
        'category' => $target['category'],
        'status' => $statusStr,
        'http_code' => $httpCode,
        'latency_ms' => $totalTimeMs,
        'connect_time_ms' => $connectTimeMs,
        'dns_time_ms' => $dnsTimeMs,
        'primary_ip' => $primaryIp,
        'stability_score' => $stabilityScore,
        'error_detail' => ($curlErrno !== 0) ? $curlError : null,
        'last_check' => $isoTimestamp,
    ];
}

curl_multi_close($mh);

// 5. Consolidação dos Resumos por Categoria
$summaryCategories = [];
foreach ($categories as $catName => $stats) {
    $catUp = $stats['up'];
    $catTotal = $stats['total'];
    $avgLat = ($catUp > 0) ? round($stats['total_latency'] / $catUp, 2) : 0.0;
    
    $catStatus = 'UP';
    if ($catUp === 0) {
        $catStatus = 'DOWN';
    } elseif ($catUp < $catTotal) {
        $catStatus = 'DEGRADED';
    }

    $summaryCategories[$catName] = [
        'status' => $catStatus,
        'avg_latency_ms' => $avgLat,
        'targets_up' => $catUp,
        'targets_total' => $catTotal,
    ];
}

$systemOverall = 'UP';
if ($totalUp === 0) {
    $systemOverall = 'DOWN';
} elseif ($totalUp < count($targets) * 0.75) {
    $systemOverall = 'DEGRADED';
}

// 6. Montagem dos Payloads
$freePayload = [
    'protocol' => 'x402',
    'network' => 'Base',
    'chain_id' => GRID402_CHAIN_ID,
    'version' => '1.0.0',
    'timestamp' => $isoTimestamp,
    'execution_time_ms' => $totalDurationMs,
    'total_targets' => count($targets),
    'healthy_targets' => $totalUp,
    'system_status' => $systemOverall,
    'categories' => $summaryCategories,
    'notice' => 'Para métricas técnicas individuais e diagnósticos milissegundo a milissegundo, envie requisição com cabeçalho X-Payment (Preço: US$ ' . GRID402_PRICE_DISPLAY . ' em USDC na Base). Consulte https://www.grid402.com.br/llms.txt',
];

$paidPayload = [
    'protocol' => 'x402',
    'license' => 'PAID_QUERY',
    'version' => '1.0.0',
    'timestamp' => $isoTimestamp,
    'execution_time_ms' => $totalDurationMs,
    'total_targets' => count($targets),
    'healthy_targets' => $totalUp,
    'system_status' => $systemOverall,
    'summary' => $freePayload,
    'targets' => $detailedResults,
];

// 7. Persistência Atômica
$writeFreeOk = grid402_atomic_write_json(GRID402_FILE_FREE, $freePayload);
$writePaidOk = grid402_atomic_write_json(GRID402_FILE_PAID, $paidPayload);

// 8. Auto-limpeza de used_txs.json (TTL de 7 dias = 604800 segundos)
if (file_exists(GRID402_FILE_USED_TXS)) {
    $rawTxs = file_get_contents(GRID402_FILE_USED_TXS);
    if ($rawTxs !== false) {
        $txs = json_decode($rawTxs, true);
        if (is_array($txs)) {
            $now = time();
            $pruned = [];
            $changed = false;
            foreach ($txs as $txHash => $ts) {
                if (($now - (int)$ts) <= 604800) {
                    $pruned[$txHash] = $ts;
                } else {
                    $changed = true;
                }
            }
            if ($changed) {
                grid402_atomic_write_json(GRID402_FILE_USED_TXS, $pruned);
            }
        }
    }
}

// 9. Saída da Execução
$responseInfo = [
    'success' => ($writeFreeOk && $writePaidOk),
    'duration_ms' => $totalDurationMs,
    'total_targets' => count($targets),
    'healthy_targets' => $totalUp,
    'timestamp' => $isoTimestamp,
];

if ($isCli) {
    echo sprintf(
        "[Grid402 Collector] Coleta concluída em %0.2f ms. Total: %d alvos | Saudáveis: %d | Status: %s\n",
        $totalDurationMs,
        count($targets),
        $totalUp,
        $systemOverall
    );
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($responseInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
