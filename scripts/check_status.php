<?php
/**
 * Grid402 - CLI Diagnostic Tool (Produção /status 25/25 UP + Handshake HTTP 402 Real)
 *
 * Verifica ao vivo a saúde do hub Grid402 em produção (`https://grid402.online`):
 *  1. GET /status — valida HTTP 200, JSON operacional, 25/25 serviços UP, preços preenchidos
 *     e ausência de vazamento de cotações ao vivo (V-14).
 *  2. Handshake HTTP 402 real (cURL Multi paralelo) nos endpoints pagos de produção, validando:
 *     - HTTP 402 estrito (paywall ativo, sem vazamento HTTP 200 nem 5xx);
 *     - Header PAYMENT-REQUIRED (Base64 → JSON x402 v2 com accepts[] e carteira canônica);
 *     - Latência real de resposta.
 *
 * Uso:
 *   php scripts/check_status.php
 *   php scripts/check_status.php --base-url=https://grid402.online
 *   php scripts/check_status.php --targets   (inclui sondagem dos alvos externos legados)
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';

$baseUrl = 'https://grid402.online';
$includeLegacyTargets = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = rtrim(substr($arg, strlen('--base-url=')), '/');
    } elseif ($arg === '--targets') {
        $includeLegacyTargets = true;
    }
}

$expectedWallet = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
$expectedServicesCount = count(grid402_get_utility_services());
$hasFailures = false;

echo "\n\033[1;36m=========================================================================================\033[0m\n";
echo "\033[1;32m   GRID402 // AUDITORIA LIVE DE PRODUÇÃO (/status {$expectedServicesCount}/{$expectedServicesCount} UP + HANDSHAKE x402 v2)     \033[0m\n";
echo "\033[1;36m=========================================================================================\033[0m\n";
echo "Alvo: {$baseUrl} | Carteira canônica: {$expectedWallet}\n\n";

// =========================================================================
// 1. Auditoria de /status (25/25 UP, price_usdc completo, sem vazamento V-14)
// =========================================================================
echo "\033[1;34m[1/2] Verificando {$baseUrl}/status ...\033[0m\n";
$statusStart = microtime(true);
$chStatus = curl_init($baseUrl . '/status');
curl_setopt_array($chStatus, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_USERAGENT => 'Grid402-Status-Check/2.0',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$statusBody = curl_exec($chStatus);
$statusHttp = (int) curl_getinfo($chStatus, CURLINFO_HTTP_CODE);
$statusErr = curl_error($chStatus);
curl_close($chStatus);
$statusMs = round((microtime(true) - $statusStart) * 1000, 1);

if ($statusHttp !== 200 || !is_string($statusBody)) {
    echo "  \033[31m[FALHA]\033[0m /status retornou HTTP {$statusHttp} ({$statusErr}) em {$statusMs} ms\n\n";
    $hasFailures = true;
} else {
    $statusJson = @json_decode($statusBody, true);
    if (!is_array($statusJson) || !is_array($statusJson['services'] ?? null)) {
        echo "  \033[31m[FALHA]\033[0m /status retornou JSON inválido em {$statusMs} ms\n\n";
        $hasFailures = true;
    } else {
        $services = $statusJson['services'];
        $totalSvcs = count($services);
        $upSvcs = 0;
        $missingPrice = [];
        foreach ($services as $svcId => $svcInfo) {
            if (is_array($svcInfo) && strtoupper((string) ($svcInfo['status'] ?? '')) === 'UP') {
                $upSvcs++;
            }
            if (!is_array($svcInfo) || !isset($svcInfo['price_usdc']) || trim((string) $svcInfo['price_usdc']) === '') {
                $missingPrice[] = (string) $svcId;
            }
        }
        $hubStatus = strtoupper((string) ($statusJson['status'] ?? 'UNKNOWN'));
        $walletInStatus = strtolower((string) ($statusJson['recipient_wallet'] ?? ''));
        $tsStr = (string) ($statusJson['timestamp'] ?? '');
        $tsEpoch = $tsStr !== '' ? strtotime($tsStr) : false;
        $ageSec = ($tsEpoch !== false) ? max(0, time() - (int) $tsEpoch) : null;
        $uptime30d = (float) ($statusJson['uptime_30d']['overall_uptime_pct'] ?? 0.0);
        $leaksQuotes = isset($services['forex']['usd_brl']) || isset($services['crypto']['btc_usd']);

        $allUpOk = ($upSvcs === $expectedServicesCount && $totalSvcs === $expectedServicesCount && $hubStatus === 'OPERATIONAL');
        $walletOk = ($walletInStatus === $expectedWallet);
        $priceOk = empty($missingPrice);

        echo sprintf(
            "  HTTP 200 (%0.1f ms) | Status: %s | Serviços: %s%d/%d UP\033[0m | Uptime 30d: %0.2f%% | Idade: %s\n",
            $statusMs,
            $hubStatus,
            $allUpOk ? "\033[32m" : "\033[31m",
            $upSvcs,
            $totalSvcs,
            $uptime30d,
            $ageSec !== null ? ($ageSec . 's') : 'n/a'
        );
        echo "  Carteira /status: " . ($walletOk ? "\033[32mOK\033[0m ({$walletInStatus})" : "\033[31mDIVERGENTE\033[0m ({$walletInStatus})") . "\n";
        echo "  Preços price_usdc: " . ($priceOk ? "\033[32mOK ({$totalSvcs}/{$totalSvcs})\033[0m" : "\033[33mFALTANDO\033[0m (" . implode(', ', $missingPrice) . ")") . "\n";
        echo "  Proteção V-14 (/status sem cotações pagas): " . (!$leaksQuotes ? "\033[32mOK\033[0m" : "\033[33mAVISO (cotações ao vivo expostas)\033[0m") . "\n\n";

        if (!$allUpOk || !$walletOk) {
            $hasFailures = true;
        }
    }
}

// =========================================================================
// 2. Handshake HTTP 402 Real nos endpoints pagos de produção
// =========================================================================
$probes = [
    ['id' => 'forex',           'path' => '/api/v1/forex',                               'expected_raw' => 5000],
    ['id' => 'crypto',          'path' => '/api/v1/crypto',                              'expected_raw' => 5000],
    ['id' => 'cep',             'path' => '/api/v1/cep?code=01310100',                   'expected_raw' => 5000],
    ['id' => 'cnpj',            'path' => '/api/v1/cnpj?cnpj=00000000000191',            'expected_raw' => 10000],
    ['id' => 'lei',             'path' => '/api/v1/lei?lei=5493001KJTIIGC8Y1R12',        'expected_raw' => 10000],
    ['id' => 'sanctions',       'path' => '/api/v1/sanctions?cnpj=00000000000191',       'expected_raw' => 10000],
    ['id' => 'kyb-audit',       'path' => '/api/v1/kyb-audit?cnpj=00000000000191',       'expected_raw' => 25000],
    ['id' => 'compliance',      'path' => '/api/v1/compliance?cnpj=00000000000191',      'expected_raw' => 30000],
    ['id' => 'dossier',         'path' => '/api/v1/dossier?cnpj=00000000000191',         'expected_raw' => 50000],
    ['id' => 'extract',         'path' => '/api/v1/extract?url=https://example.com',     'expected_raw' => 5000],
    ['id' => 'email-verify',    'path' => '/api/v1/email-verify?email=contato@grid402.online', 'expected_raw' => 5000],
    ['id' => 'pass',            'path' => '/api/v1/pass',                                'expected_raw' => 2000000],
];

echo "\033[1;34m[2/2] Sondando handshake HTTP 402 real em " . count($probes) . " endpoints pagos (paralelo)...\033[0m\n";
$mh = curl_multi_init();
$handles = [];
$batchStart = microtime(true);

foreach ($probes as $probe) {
    $ch = curl_init($baseUrl . $probe['path']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Grid402-Status-Check/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$probe['id']] = ['handle' => $ch, 'probe' => $probe];
}

do {
    $mrc = curl_multi_exec($mh, $active);
    if ($active) {
        if (curl_multi_select($mh, 0.05) === -1) {
            usleep(10000);
        }
    }
} while ($active && $mrc === CURLM_OK);

$batchDurationMs = round((microtime(true) - $batchStart) * 1000, 1);

printf(
    "%-16s | %-6s | %-10s | %-10s | %-10s | %-18s\n",
    'ENDPOINT', 'HTTP', 'x402 HDR', 'ACCEPTS', 'LATÊNCIA', 'RESULTADO'
);
echo str_repeat('-', 85) . "\n";

$okProbes = 0;
foreach ($handles as $item) {
    $ch = $item['handle'];
    $probe = $item['probe'];
    $rawResp = (string) curl_multi_getcontent($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $timeMs = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1);
    $errno = curl_errno($ch);

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);

    $rawHeaders = substr($rawResp, 0, $headerSize);
    $payReqB64 = '';
    foreach (preg_split('/\r?\n/', $rawHeaders) ?: [] as $hLine) {
        if (stripos($hLine, 'PAYMENT-REQUIRED:') === 0) {
            $payReqB64 = trim(substr($hLine, strlen('PAYMENT-REQUIRED:')));
            break;
        }
    }

    $hdrOk = false;
    $acceptsCount = 0;
    $payToValid = false;
    $amountMatch = false;

    if ($payReqB64 !== '') {
        $decodedRaw = base64_decode($payReqB64, true);
        $decoded = is_string($decodedRaw) ? @json_decode($decodedRaw, true) : null;
        if (is_array($decoded) && is_array($decoded['accepts'] ?? null) && count($decoded['accepts']) > 0) {
            $hdrOk = true;
            $acceptsCount = count($decoded['accepts']);
            foreach ($decoded['accepts'] as $acc) {
                $net = (string) ($acc['network'] ?? '');
                $payTo = strtolower((string) ($acc['payTo'] ?? ''));
                $amt = (int) ($acc['amount'] ?? ($acc['maxAmountRequired'] ?? 0));
                if (str_starts_with($net, 'eip155:')) {
                    if ($payTo === $expectedWallet) {
                        $payToValid = true;
                    }
                    if ($amt === (int) $probe['expected_raw']) {
                        $amountMatch = true;
                    }
                }
            }
        }
    }

    $probePassed = ($errno === 0 && $httpCode === 402 && $hdrOk && $payToValid && $amountMatch);
    if ($probePassed) {
        $okProbes++;
        $resLabel = "\033[32mOK (402 x402v2)\033[0m";
    } elseif ($httpCode === 402 && $hdrOk && $payToValid && !$amountMatch) {
        $hasFailures = true;
        $resLabel = "\033[33mPREÇO DIVERGENTE\033[0m";
    } else {
        $hasFailures = true;
        $resLabel = "\033[31mFALHA (HTTP {$httpCode})\033[0m";
    }

    printf(
        "%-16s | %-6d | %-10s | %-10s | %7.1f ms | %s\n",
        $probe['id'],
        $httpCode,
        $hdrOk ? 'OK' : 'AUSENTE',
        $acceptsCount . ' chain(s)',
        $timeMs,
        $resLabel
    );
}
curl_multi_close($mh);

echo str_repeat('=', 85) . "\n";
echo "Lote 402 concluído em {$batchDurationMs} ms | Handshakes válidos: {$okProbes}/" . count($probes) . "\n\n";

// =========================================================================
// Opcional (--targets): Sondagem dos alvos externos legados (SEFAZ/etc.)
// =========================================================================
if ($includeLegacyTargets) {
    $targets = grid402_get_targets();
    $mhT = curl_multi_init();
    $tHandles = [];
    echo "\033[1;34m[Extra --targets] Sondando " . count($targets) . " alvos externos...\033[0m\n";
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
        curl_multi_add_handle($mhT, $ch);
        $tHandles[$target['id']] = ['handle' => $ch, 'target' => $target];
    }
    do {
        $st = curl_multi_exec($mhT, $act);
        if ($act) {
            if (curl_multi_select($mhT, 0.05) === -1) {
                usleep(10000);
            }
        }
    } while ($act && $st === CURLM_OK);

    $upTargets = 0;
    foreach ($tHandles as $item) {
        $ch = $item['handle'];
        $target = $item['target'];
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $timeMs = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1);
        $errno = curl_errno($ch);
        curl_multi_remove_handle($mhT, $ch);
        curl_close($ch);
        $isExpected = in_array($httpCode, $target['expected_codes'], true) || ($httpCode >= 200 && $httpCode < 400);
        if ($errno === 0 && $isExpected) {
            $upTargets++;
        }
        printf("  %-18s | HTTP %-4d | %7.1f ms | %s\n", substr($target['name'], 0, 18), $httpCode, $timeMs, ($errno === 0 && $isExpected) ? 'UP' : 'DOWN');
    }
    curl_multi_close($mhT);
    echo "Alvos externos operantes: {$upTargets}/" . count($targets) . "\n\n";
}

exit($hasFailures ? 1 : 0);
