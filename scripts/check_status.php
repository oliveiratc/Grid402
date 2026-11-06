<?php
/**
 * Grid402 - CLI Diagnostic Tool
 * 
 * Permite auditar rapidamente a saúde e latência dos alvos em tempo real
 * diretamente pelo terminal com saída formatada.
 * 
 * Uso:
 *   php scripts/check_status.php
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';

echo "\n\033[1;36m=================================================================================\033[0m\n";
echo "\033[1;32m   GRID402 // DIAGNÓSTICO EM TEMPO REAL DOS ALVOS CRÍTICOS (TELEMETRIA M2M)      \033[0m\n";
echo "\033[1;36m=================================================================================\033[0m\n\n";

$targets = grid402_get_targets();
$mh = curl_multi_init();
$handles = [];

echo "Disparando " . count($targets) . " sondagens paralelas via cURL Multi...\n";
$start = microtime(true);

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
        CURLOPT_NOBODY => true,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$target['id']] = ['handle' => $ch, 'target' => $target];
}

do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
        if (curl_multi_select($mh, 0.05) === -1) {
            usleep(10000);
        }
    }
} while ($active && $status === CURLM_OK);

$duration = round((microtime(true) - $start) * 1000, 2);

printf(
    "%-18s | %-16s | %-8s | %-10s | %-8s | %-12s\n",
    "CATEGORIA", "NOME DO ALVO", "STATUS", "LATÊNCIA", "HTTP", "SCORE"
);
echo str_repeat("-", 85) . "\n";

$upCount = 0;
foreach ($handles as $item) {
    $ch = $item['handle'];
    $target = $item['target'];

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $timeMs = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1);
    $errno = curl_errno($ch);

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);

    $isExpected = in_array($httpCode, $target['expected_codes'], true) || ($httpCode >= 200 && $httpCode < 400);
    $statusStr = ($errno === 0 && $isExpected) ? "\033[32mUP\033[0m" : (($errno === 0) ? "\033[33mDEGRADED\033[0m" : "\033[31mDOWN\033[0m");

    $score = ($errno === 0 && $isExpected) ? ($timeMs < 300 ? 98 : ($timeMs < 800 ? 90 : 75)) : 0;
    if ($errno === 0 && $isExpected) {
        $upCount++;
    }

    printf(
        "%-18s | %-16s | %-17s | %8.1f ms | %-8d | %d/100\n",
        substr($target['category'], 0, 18),
        substr($target['name'], 0, 16),
        $statusStr,
        $timeMs,
        $httpCode,
        $score
    );
}
curl_multi_close($mh);

echo str_repeat("=", 85) . "\n";
echo "Tempo de resposta do lote: {$duration} ms | Alvos operantes: {$upCount}/" . count($targets) . "\n\n";

