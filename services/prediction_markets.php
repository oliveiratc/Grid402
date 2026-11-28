<?php
/**
 * Grid402 - Micro-Servico de Mercados de Previsao Normalizados (/api/v1/prediction-markets)
 *
 * Fontes 100% publicas sem API key paga:
 * - Polymarket Gamma Public API (gamma-api.polymarket.com)
 * - Kalshi Public Trade API v2 (api.elections.kalshi.com)
 *
 * Cache Zero-DB atomico por 120s em GRID402_PRIVATE_DIR.
 * Degradacao graciosa se uma fonte falhar; HTTP 503 pre-cobranca apenas se ambas falharem.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Valida e normaliza parametros de consulta de /api/v1/prediction-markets.
 *
 * @return array<string, mixed>
 */
function grid402_prediction_markets_validate_input(string $q = '', string $source = 'all', string $status = 'active', int $limit = 10): array {
    $cleanQ = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $q));
    if (strlen($cleanQ) > 120) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'QueryTooLong',
            'message' => 'O parametro de busca "q" deve ter no maximo 120 caracteres.',
        ];
    }

    $normSource = strtolower(trim($source));
    if ($normSource === '') {
        $normSource = 'all';
    }
    if (!in_array($normSource, ['all', 'polymarket', 'kalshi'], true)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidMarketSource',
            'message' => 'Parametro "source" invalido. Valores aceitos: all, polymarket, kalshi.',
        ];
    }

    $normStatus = strtolower(trim($status));
    if ($normStatus === '') {
        $normStatus = 'active';
    }
    if (!in_array($normStatus, ['active', 'closed'], true)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidMarketStatus',
            'message' => 'Parametro "status" invalido. Valores aceitos: active, closed.',
        ];
    }

    $clampedLimit = max(1, min(20, $limit > 0 ? $limit : 10));

    return [
        'ok' => true,
        'q' => $cleanQ,
        'source' => $normSource,
        'status' => $normStatus,
        'limit' => $clampedLimit,
    ];
}

/**
 * Executa GET HTTPS curto para APIs de prediction markets (suporta mock em $GLOBALS['grid402_prediction_markets_http_override']).
 *
 * @return array{ok: bool, status: int, data: mixed, error: ?string}
 */
function grid402_prediction_markets_http_get(string $url, int $timeoutSec = 4): array {
    $override = $GLOBALS['grid402_prediction_markets_http_override'] ?? null;
    if (is_callable($override)) {
        return $override($url, $timeoutSec);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSec),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Grid402-PredictionMarkets/1.0 (+https://grid402.online)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($status !== 200 || !is_string($raw) || $raw === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $err !== '' ? $err : ('HTTP ' . $status),
        ];
    }

    $decoded = @json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => 'InvalidJSON',
        ];
    }

    return ['ok' => true, 'status' => 200, 'data' => $decoded, 'error' => null];
}

/**
 * Normaliza resposta da Polymarket Gamma API para schema canonico Grid402.
 *
 * @return array<int, array<string, mixed>>
 */
function grid402_normalize_polymarket_items(mixed $raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $list = isset($raw['markets']) && is_array($raw['markets']) ? $raw['markets'] : $raw;
    $out = [];
    foreach ($list as $m) {
        if (!is_array($m)) {
            continue;
        }
        $title = trim((string) ($m['question'] ?? $m['title'] ?? ''));
        $slug = trim((string) ($m['slug'] ?? $m['market_slug'] ?? ''));
        $id = (string) ($m['id'] ?? $m['conditionId'] ?? $slug);
        if ($title === '' || $id === '') {
            continue;
        }

        $rawOutcomes = $m['outcomes'] ?? [];
        if (is_string($rawOutcomes)) {
            $rawOutcomes = @json_decode($rawOutcomes, true) ?: [];
        }
        $rawPrices = $m['outcomePrices'] ?? [];
        if (is_string($rawPrices)) {
            $rawPrices = @json_decode($rawPrices, true) ?: [];
        }

        $outcomes = [];
        if (is_array($rawOutcomes) && is_array($rawPrices)) {
            foreach ($rawOutcomes as $idx => $name) {
                $probRaw = $rawPrices[$idx] ?? null;
                if (is_numeric($probRaw)) {
                    $p = (float) $probRaw;
                    if ($p <= 1.0) {
                        $p *= 100.0;
                    }
                    $outcomes[] = [
                        'name' => (string) $name,
                        'probability_pct' => round(max(0.0, min(100.0, $p)), 2),
                    ];
                }
            }
        }
        if (empty($outcomes) && isset($m['lastTradePrice']) && is_numeric($m['lastTradePrice'])) {
            $yesP = round(max(0.0, min(100.0, ((float) $m['lastTradePrice']) * 100.0)), 2);
            $outcomes = [
                ['name' => 'Yes', 'probability_pct' => $yesP],
                ['name' => 'No', 'probability_pct' => round(max(0.0, 100.0 - $yesP), 2)],
            ];
        }

        $vol24 = (float) ($m['volume24hr'] ?? $m['volumeNum'] ?? $m['volume'] ?? 0.0);
        $liq = (float) ($m['liquidityNum'] ?? $m['liquidity'] ?? 0.0);
        $activeFlag = isset($m['active']) ? (bool) $m['active'] : empty($m['closed']);

        $out[] = [
            'id' => $id,
            'source' => 'polymarket',
            'title' => $title,
            'slug' => $slug !== '' ? $slug : $id,
            'url' => $slug !== '' ? ('https://polymarket.com/event/' . rawurlencode($slug)) : 'https://polymarket.com',
            'active' => $activeFlag,
            'outcomes' => $outcomes,
            'volume_24h_usd' => round($vol24, 2),
            'liquidity_usd' => round($liq, 2),
            'end_date' => isset($m['endDate']) ? (string) $m['endDate'] : ($m['end_date_iso'] ?? null),
        ];
    }
    return $out;
}

/**
 * Normaliza resposta da Kalshi Public Trade API v2 para schema canonico Grid402.
 *
 * @return array<int, array<string, mixed>>
 */
function grid402_normalize_kalshi_items(mixed $raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $list = isset($raw['markets']) && is_array($raw['markets']) ? $raw['markets'] : $raw;
    $out = [];
    foreach ($list as $m) {
        if (!is_array($m)) {
            continue;
        }
        $ticker = trim((string) ($m['ticker'] ?? $m['id'] ?? ''));
        $title = trim((string) ($m['title'] ?? $m['subtitle'] ?? ''));
        if ($ticker === '' || $title === '') {
            continue;
        }

        // Kalshi v2 retorna precos em cents (0-100) ou dolares decimais string (`yes_bid_dollars`)
        $yesProb = null;
        if (isset($m['last_price']) && is_numeric($m['last_price'])) {
            $yesProb = (float) $m['last_price'];
        } elseif (isset($m['yes_bid']) && is_numeric($m['yes_bid']) && isset($m['yes_ask']) && is_numeric($m['yes_ask'])) {
            $yesProb = (((float) $m['yes_bid']) + ((float) $m['yes_ask'])) / 2.0;
        } elseif (isset($m['last_price_dollars']) && is_numeric($m['last_price_dollars'])) {
            $yesProb = ((float) $m['last_price_dollars']) * 100.0;
        }
        if ($yesProb !== null && $yesProb <= 1.0 && !isset($m['last_price'])) {
            $yesProb *= 100.0;
        }
        $yesPct = $yesProb !== null ? round(max(0.0, min(100.0, $yesProb)), 2) : 50.0;
        $noPct = round(max(0.0, 100.0 - $yesPct), 2);

        $vol24 = (float) ($m['volume_24h'] ?? $m['volume'] ?? 0.0);
        $liq = (float) ($m['liquidity'] ?? ($m['open_interest'] ?? 0.0));
        $statusStr = strtolower((string) ($m['status'] ?? 'open'));
        $isActive = in_array($statusStr, ['open', 'active'], true);

        $out[] = [
            'id' => $ticker,
            'source' => 'kalshi',
            'title' => $title,
            'slug' => strtolower($ticker),
            'url' => 'https://kalshi.com/markets/' . rawurlencode(strtolower($ticker)),
            'active' => $isActive,
            'outcomes' => [
                ['name' => 'Yes', 'probability_pct' => $yesPct],
                ['name' => 'No', 'probability_pct' => $noPct],
            ],
            'volume_24h_usd' => round($vol24, 2),
            'liquidity_usd' => round($liq, 2),
            'end_date' => isset($m['expiration_time']) ? (string) $m['expiration_time'] : ($m['close_time'] ?? null),
        ];
    }
    return $out;
}

/**
 * Teaser gratuito para HTTP 402.
 *
 * @return array<string, mixed>
 */
function grid402_service_prediction_markets_preview(string $q = '', string $source = 'all', string $status = 'active', int $limit = 10): array {
    $val = grid402_prediction_markets_validate_input($q, $source, $status, $limit);
    return [
        'preview' => [
            'query' => $val['ok'] ? $val['q'] : '',
            'source' => $val['ok'] ? $val['source'] : 'all',
            'status_filter' => $val['ok'] ? $val['status'] : 'active',
            'limit' => $val['ok'] ? $val['limit'] : 10,
            'supported_sources' => ['polymarket', 'kalshi'],
        ],
        'locked_fields' => ['markets', 'count', 'sources_status', '_oracle_proof'],
    ];
}

/**
 * Consulta normalizada de mercados de previsao com cache Zero-DB (120s) e degradacao graciosa.
 *
 * @return array<string, mixed>
 */
function grid402_service_prediction_markets(string $q = '', string $source = 'all', string $status = 'active', int $limit = 10): array {
    $val = grid402_prediction_markets_validate_input($q, $source, $status, $limit);
    if (empty($val['ok'])) {
        return [
            'success' => false,
            'error' => $val['error'],
            'message' => $val['message'],
            'code' => (int) ($val['code'] ?? 400),
        ];
    }

    $qClean = (string) $val['q'];
    $srcFilter = (string) $val['source'];
    $statusFilter = (string) $val['status'];
    $limitVal = (int) $val['limit'];

    $privateDir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/../private_data');
    $cacheKey = hash('sha256', strtolower($qClean) . '|' . $srcFilter . '|' . $statusFilter . '|' . $limitVal);
    $cacheFile = $privateDir . '/prediction_markets_' . substr($cacheKey, 0, 24) . '.json';
    $cacheTtl = 120;
    $useOverride = is_callable($GLOBALS['grid402_prediction_markets_http_override'] ?? null);

    if (!$useOverride && file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = $rawCache ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < $cacheTtl) {
            $decodedCache['cached'] = true;
            return $decodedCache;
        }
    }

    $wantPoly = ($srcFilter === 'all' || $srcFilter === 'polymarket');
    $wantKalshi = ($srcFilter === 'all' || $srcFilter === 'kalshi');

    $sourcesStatus = [];
    $allMarkets = [];
    $okSources = 0;

    if ($wantPoly) {
        $polyUrl = 'https://gamma-api.polymarket.com/markets?active=' . ($statusFilter === 'active' ? 'true' : 'false')
            . '&closed=' . ($statusFilter === 'closed' ? 'true' : 'false')
            . '&limit=50';
        $polyResp = grid402_prediction_markets_http_get($polyUrl, 4);
        if (!empty($polyResp['ok'])) {
            $sourcesStatus['polymarket'] = 'ok';
            $okSources++;
            $allMarkets = array_merge($allMarkets, grid402_normalize_polymarket_items($polyResp['data']));
        } else {
            $sourcesStatus['polymarket'] = 'unavailable (' . (string) ($polyResp['error'] ?? 'error') . ')';
        }
    }

    if ($wantKalshi) {
        $kalshiStatus = ($statusFilter === 'closed') ? 'closed' : 'open';
        $kalshiUrl = 'https://api.elections.kalshi.com/trade-api/v2/markets?status=' . $kalshiStatus . '&limit=50';
        $kalshiResp = grid402_prediction_markets_http_get($kalshiUrl, 4);
        if (!empty($kalshiResp['ok'])) {
            $sourcesStatus['kalshi'] = 'ok';
            $okSources++;
            $allMarkets = array_merge($allMarkets, grid402_normalize_kalshi_items($kalshiResp['data']));
        } else {
            $sourcesStatus['kalshi'] = 'unavailable (' . (string) ($kalshiResp['error'] ?? 'error') . ')';
        }
    }

    if ($okSources === 0) {
        if (!$useOverride && file_exists($cacheFile)) {
            $staleRaw = @file_get_contents($cacheFile);
            $stale = $staleRaw ? @json_decode($staleRaw, true) : null;
            if (is_array($stale)) {
                $stale['cached'] = true;
                $stale['stale_fallback'] = true;
                return $stale;
            }
        }
        return [
            'success' => false,
            'error' => 'UpstreamUnavailable',
            'message' => 'Nao foi possivel obter dados de mercados de previsao no momento. Nenhum debito foi mantido.',
            'sources_status' => $sourcesStatus,
            'code' => 503,
        ];
    }

    if ($qClean !== '') {
        $needle = strtolower($qClean);
        $allMarkets = array_values(array_filter($allMarkets, static function (array $m) use ($needle): bool {
            $hay = strtolower((string) ($m['title'] ?? '') . ' ' . (string) ($m['slug'] ?? '') . ' ' . (string) ($m['id'] ?? ''));
            return str_contains($hay, $needle);
        }));
    }

    usort($allMarkets, static function (array $a, array $b): int {
        return ((float) ($b['volume_24h_usd'] ?? 0.0)) <=> ((float) ($a['volume_24h_usd'] ?? 0.0));
    });

    $sliced = array_slice($allMarkets, 0, $limitVal);

    $payload = [
        'protocol' => 'x402',
        'service' => 'prediction-markets',
        'status' => 'UP',
        'query' => $qClean !== '' ? $qClean : null,
        'source_filter' => $srcFilter,
        'status_filter' => $statusFilter,
        'count' => count($sliced),
        'total_matched' => count($allMarkets),
        'markets' => $sliced,
        'sources_status' => $sourcesStatus,
        'legal_disclaimer' => 'Normalized public prediction market snapshots from Polymarket and Kalshi. Informational probability data only; not financial or trading advice.',
        'cached' => false,
        'cached_at' => time(),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if (!$useOverride && function_exists('grid402_atomic_write')) {
        grid402_atomic_write($cacheFile, $payload);
    }

    return $payload;
}
