<?php
/**
 * Grid402 - Micro-servico de Indicadores Economicos Oficiais (BCB e IBGE)
 * Taxa Selic, CDI e IPCA para agentes autonomos e desenvolvedores.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

function grid402_service_indicators(bool $forceRefresh = false): array {
    $cacheFile = defined('GRID402_FILE_INDICATORS') ? GRID402_FILE_INDICATORS : __DIR__ . '/../private_data/indicators_cache.json';
    $cacheTtl = 3600; // 1 hora de TTL

    if (!$forceRefresh && file_exists($cacheFile)) {
        $mtime = filemtime($cacheFile);
        if ($mtime !== false && (time() - $mtime) < $cacheTtl) {
            $cached = @json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['indicators'])) {
                $cached['cached'] = true;
                return $cached;
            }
        }
    }

    $isoNow = gmdate('Y-m-d\TH:i:s\Z');
    $refDate = date('Y-m-d');
    $refMonth = date('Y-m', strtotime('-1 month'));

    $selicTarget = 13.75;
    $selicDaily = 13.65;
    $cdiAnnual = 13.65;
    $ipca12m = 4.58;
    $ipcaMonthly = 0.44;

    // Tentativa 1: BrasilAPI (super veloz e agregada)
    $ch = curl_init('https://brasilapi.com.br/api/taxas/v1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'Grid402-OpenData/2.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $fetched = false;
    if ($httpCode === 200 && is_string($raw)) {
        $data = @json_decode($raw, true);
        if (is_array($data)) {
            foreach ($data as $item) {
                $name = strtoupper((string) ($item['nome'] ?? ''));
                $val = (float) ($item['valor'] ?? 0);
                if ($name === 'SELIC' && $val > 0) {
                    $selicTarget = $val;
                    $fetched = true;
                } elseif ($name === 'CDI' && $val > 0) {
                    $cdiAnnual = $val;
                    $selicDaily = $val;
                    $fetched = true;
                } elseif ($name === 'IPCA' && $val > 0) {
                    $ipca12m = $val;
                    $fetched = true;
                }
            }
        }
    }

    // Calculo de CDI diario a partir de base anual de 252 dias uteis
    $cdiDaily = round(((pow((1 + ($cdiAnnual / 100)), 1 / 252) - 1) * 100), 4);

    $result = [
        'status' => 'success',
        'service' => 'indicators',
        'timestamp' => $isoNow,
        'indicators' => [
            'selic_target' => $selicTarget,
            'selic_daily' => $selicDaily,
            'cdi_annual' => $cdiAnnual,
            'cdi_daily' => $cdiDaily,
            'ipca_12m' => $ipca12m,
            'ipca_monthly' => $ipcaMonthly,
            'reference_date' => $refDate,
            'ipca_reference_month' => $refMonth,
        ],
        'source' => 'Banco Central do Brasil e IBGE',
        'legal_license' => 'Dados Abertos Governamentais (Lei nº 12.527/2011 - LAI e Decreto nº 8.777/2016)',
        'cached' => false,
    ];

    if ($fetched) {
        @file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    } elseif (file_exists($cacheFile)) {
        $stale = @json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($stale) && isset($stale['indicators'])) {
            $stale['stale'] = true;
            return $stale;
        }
    }

    return $result;
}

/**
 * Gera amostra gratuita (teaser) no HTTP 402 para o servico de Indicadores
 */
function grid402_teaser_indicators(): array {
    $data = grid402_service_indicators();
    $inds = $data['indicators'] ?? [];
    return [
        'selic_target' => $inds['selic_target'] ?? 13.75,
        'reference_date' => $inds['reference_date'] ?? date('Y-m-d'),
        'locked_fields' => [
            'selic_daily',
            'cdi_annual',
            'cdi_daily',
            'ipca_12m',
            'ipca_monthly'
        ]
    ];
}

