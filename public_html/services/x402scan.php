<?php
/**
 * Grid402 // Telemetria Pública x402scan (Zero-API-Key & Zero-DB)
 *
 * Consulta os endpoints tRPC públicos do x402scan (`public.origins.get` e `public.stats.overall`)
 * para as janelas materializadas `1d` (24h), `7d` e `30d` nas carteiras oficiais EVM + Solana.
 * Mantém cache atômico em `private_data/x402scan_cache.json` por 300s com fallback `stale_cache`.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

if (!defined('GRID402_X402SCAN_ORIGIN_ID')) {
    $envOrigin = getenv('GRID402_X402SCAN_ORIGIN_ID');
    define(
        'GRID402_X402SCAN_ORIGIN_ID',
        ($envOrigin !== false && trim($envOrigin) !== '')
            ? trim($envOrigin)
            : '00294038-51ff-4073-ab29-c59fca221a50'
    );
}
if (!defined('GRID402_X402SCAN_SERVER_URL')) {
    define('GRID402_X402SCAN_SERVER_URL', 'https://www.x402scan.com/server/' . GRID402_X402SCAN_ORIGIN_ID);
}
if (!defined('GRID402_X402SCAN_CACHE_TTL_SEC')) {
    define('GRID402_X402SCAN_CACHE_TTL_SEC', 300);
}

/**
 * Executa HTTP GET para a API pública tRPC do x402scan.
 * Suporta override em `$GLOBALS['grid402_x402scan_http_override']` para testes offline.
 *
 * @return array{ok: bool, status: int, data: mixed, error: ?string}
 */
function grid402_x402scan_http_get(string $url, int $timeoutSec = 4): array {
    $override = $GLOBALS['grid402_x402scan_http_override'] ?? null;
    if (is_callable($override)) {
        return $override($url, $timeoutSec);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'CurlInitFailed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(2, max(1, $timeoutSec)),
        CURLOPT_TIMEOUT => max(1, $timeoutSec),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Grid402-Admin/1.0 (+https://grid402.online)',
        ],
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
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'InvalidJSON'];
    }

    return ['ok' => true, 'status' => 200, 'data' => $decoded, 'error' => null];
}

/**
 * Extrai e normaliza uma janela (`1d`, `7d` ou `30d`) retornada por `public.stats.overall`.
 *
 * @param array<string, mixed> $resp
 * @return array<string, mixed>|null
 */
function grid402_x402scan_parse_window(array $resp): ?array {
    if (empty($resp['ok']) || !is_array($resp['data'])) {
        return null;
    }

    $json = $resp['data']['result']['data']['json'] ?? null;
    if (!is_array($json)) {
        return null;
    }

    $txs = isset($json['total_transactions']) ? (int) $json['total_transactions'] : 0;
    $amountRaw = isset($json['total_amount']) ? (int) $json['total_amount'] : 0;
    $buyers = isset($json['unique_buyers']) ? (int) $json['unique_buyers'] : 0;
    $latestTs = isset($json['latest_block_timestamp']) && is_string($json['latest_block_timestamp'])
        ? trim($json['latest_block_timestamp'])
        : null;

    return [
        'transactions' => max(0, $txs),
        'amount_raw' => max(0, $amountRaw),
        'volume_usdc' => round(max(0, $amountRaw) / 1000000, 4),
        'unique_buyers' => max(0, $buyers),
        'latest_tx_at' => ($latestTs !== null && $latestTs !== '') ? $latestTs : null,
    ];
}

/**
 * Retorna snapshot público do x402scan (cache 300s em disco, fallback `stale_cache` ou `unavailable`).
 *
 * @return array<string, mixed>
 */
function grid402_get_x402scan_stats(bool $forceRefresh = false): array {
    $privateDir = defined('GRID402_PRIVATE_DIR')
        ? rtrim((string) GRID402_PRIVATE_DIR, '/\\')
        : (__DIR__ . '/../private_data');
    $cacheFile = $privateDir . '/x402scan_cache.json';
    $ttl = defined('GRID402_X402SCAN_CACHE_TTL_SEC') ? (int) GRID402_X402SCAN_CACHE_TTL_SEC : 300;
    $originId = (string) GRID402_X402SCAN_ORIGIN_ID;
    $serverUrl = (string) GRID402_X402SCAN_SERVER_URL;

    $evmWallet = defined('GRID402_WALLET_ADDRESS') ? strtolower(trim((string) GRID402_WALLET_ADDRESS)) : '';
    $solWallet = defined('GRID402_SOLANA_WALLET_ADDRESS') ? trim((string) GRID402_SOLANA_WALLET_ADDRESS) : '';
    $recipients = [];
    if ($evmWallet !== '') {
        $recipients[] = $evmWallet;
    }
    if ($solWallet !== '') {
        $recipients[] = $solWallet;
    }

    $existingCache = null;
    if (file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = is_string($rawCache) ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && is_array($decodedCache['windows'] ?? null)) {
            $existingCache = $decodedCache;
            if (!$forceRefresh && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < $ttl) {
                $decodedCache['status'] = 'cached';
                $decodedCache['stale'] = false;
                return $decodedCache;
            }
        }
    }

    $emptyWindow = static fn(): array => [
        'transactions' => null,
        'amount_raw' => null,
        'volume_usdc' => null,
        'unique_buyers' => null,
        'latest_tx_at' => null,
    ];

    $trpcBase = 'https://www.x402scan.com/api/trpc/';
    $originInput = rawurlencode((string) json_encode(['json' => $originId], JSON_UNESCAPED_SLASHES));
    $originResp = grid402_x402scan_http_get($trpcBase . 'public.origins.get?input=' . $originInput, 4);

    $originNode = null;
    $originOk = false;
    if (!empty($originResp['ok']) && is_array($originResp['data']['result']['data']['json'] ?? null)) {
        $oj = $originResp['data']['result']['data']['json'];
        $originOk = true;
        $originNode = [
            'id' => (string) ($oj['id'] ?? $originId),
            'title' => html_entity_decode((string) ($oj['title'] ?? 'Grid402'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'has_x402_v2' => (bool) ($oj['hasX402V2Resource'] ?? false),
            'updated_at' => isset($oj['updatedAt']) && is_string($oj['updatedAt']) ? $oj['updatedAt'] : null,
        ];
    }

    $windows = [
        'd1' => $emptyWindow(),
        'd7' => $emptyWindow(),
        'd30' => $emptyWindow(),
    ];
    $windowsOkCount = 0;
    $errors = [];

    if (!$originOk) {
        $errors[] = 'origins.get: ' . (string) ($originResp['error'] ?? 'error');
    }

    foreach (['d1' => 1, 'd7' => 7, 'd30' => 30] as $winKey => $days) {
        $statsPayload = [
            'json' => [
                'recipients' => ['include' => $recipients],
                'timeframe' => $days,
            ],
        ];
        $statsInput = rawurlencode((string) json_encode($statsPayload, JSON_UNESCAPED_SLASHES));
        $statsResp = grid402_x402scan_http_get($trpcBase . 'public.stats.overall?input=' . $statsInput, 4);
        $parsedWin = grid402_x402scan_parse_window($statsResp);
        if (is_array($parsedWin)) {
            $windows[$winKey] = $parsedWin;
            $windowsOkCount++;
        } else {
            $errors[] = "stats({$winKey}): " . (string) ($statsResp['error'] ?? 'error');
        }
    }

    if ($windowsOkCount > 0 || $originOk) {
        if ($originNode === null && is_array($existingCache['origin'] ?? null)) {
            $originNode = $existingCache['origin'];
        }
        if ($originNode === null) {
            $originNode = [
                'id' => $originId,
                'title' => 'Grid402',
                'has_x402_v2' => true,
                'updated_at' => null,
            ];
        }

        $payload = [
            'status' => 'live',
            'stale' => false,
            'cached_at' => time(),
            'cache_ttl_sec' => $ttl,
            'server_url' => $serverUrl,
            'wallets' => [
                'evm' => $evmWallet !== '' ? $evmWallet : null,
                'solana' => $solWallet !== '' ? $solWallet : null,
            ],
            'origin' => $originNode,
            'windows' => $windows,
            'error' => !empty($errors) ? implode('; ', $errors) : null,
        ];

        if (function_exists('grid402_atomic_write')) {
            grid402_atomic_write($cacheFile, $payload);
        }

        return $payload;
    }

    if (is_array($existingCache)) {
        $existingCache['status'] = 'stale_cache';
        $existingCache['stale'] = true;
        $existingCache['error'] = !empty($errors) ? implode('; ', $errors) : 'x402scan upstream unavailable';
        return $existingCache;
    }

    return [
        'status' => 'unavailable',
        'stale' => false,
        'cached_at' => null,
        'cache_ttl_sec' => $ttl,
        'server_url' => $serverUrl,
        'wallets' => [
            'evm' => $evmWallet !== '' ? $evmWallet : null,
            'solana' => $solWallet !== '' ? $solWallet : null,
        ],
        'origin' => [
            'id' => $originId,
            'title' => 'Grid402',
            'has_x402_v2' => null,
            'updated_at' => null,
        ],
        'windows' => $windows,
        'error' => !empty($errors) ? implode('; ', $errors) : 'x402scan upstream unavailable',
    ];
}
