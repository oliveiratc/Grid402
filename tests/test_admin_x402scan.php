<?php
/**
 * Suite de Testes: Painel Público x402scan no Cockpit /admin
 * Cobre cache Zero-DB (300s), conversão raw->USDC, dual-wallet (EVM+Solana),
 * fallback stale_cache, degradação unavailable, /admin?api=stats (&refresh=1) e SSR HTML.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpDir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/services/x402scan.php';

$cleanupRecursive = static function (string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
};

$httpCall = static function (string $method, string $url, array $headers = [], ?string $body = null, ?string $cookieFile = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cookieFile !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = @json_decode($raw, true);
    return ['code' => $code, 'body' => $raw, 'json' => is_array($json) ? $json : null];
};

echo "=== TESTES X402SCAN NO PAINEL ADMIN ===\n\n";

try {
    // -------------------------------------------------------------------------
    // [1] Unitários: consulta tRPC, cache 300s, forceRefresh, stale_cache, unavailable
    // -------------------------------------------------------------------------
    echo "[1/2] Validando serviço Zero-DB services/x402scan.php:\n";

    $httpCallCount = 0;
    $capturedUrls = [];
    $GLOBALS['grid402_x402scan_http_override'] = static function (string $url, int $timeoutSec) use (&$httpCallCount, &$capturedUrls): array {
        $httpCallCount++;
        $capturedUrls[] = $url;
        if (str_contains($url, 'public.origins.get')) {
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    'result' => [
                        'data' => [
                            'json' => [
                                'id' => GRID402_X402SCAN_ORIGIN_ID,
                                'title' => 'Grid402 - Global Open-Data &amp; Network Utilities M2M Hub',
                                'hasX402V2Resource' => true,
                                'updatedAt' => '2026-11-28T01:33:02.628Z',
                            ],
                        ],
                    ],
                ],
            ];
        }
        $decodedUrl = rawurldecode($url);
        if (str_contains($decodedUrl, '"timeframe":1')) {
            return [
                'ok' => true, 'status' => 200, 'error' => null,
                'data' => ['result' => ['data' => ['json' => [
                    'total_transactions' => 31, 'total_amount' => 355000, 'unique_buyers' => 1, 'latest_block_timestamp' => '2026-11-28T01:36:42.000Z',
                ]]]],
            ];
        }
        if (str_contains($decodedUrl, '"timeframe":7')) {
            return [
                'ok' => true, 'status' => 200, 'error' => null,
                'data' => ['result' => ['data' => ['json' => [
                    'total_transactions' => 41, 'total_amount' => 480000, 'unique_buyers' => 2, 'latest_block_timestamp' => '2026-11-28T01:36:42.000Z',
                ]]]],
            ];
        }
        if (str_contains($decodedUrl, '"timeframe":30')) {
            return [
                'ok' => true, 'status' => 200, 'error' => null,
                'data' => ['result' => ['data' => ['json' => [
                    'total_transactions' => 68, 'total_amount' => 4730000, 'unique_buyers' => 4, 'latest_block_timestamp' => '2026-11-27T13:43:53.000Z',
                ]]]],
            ];
        }
        return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'UnhandledMock'];
    };

    $live = grid402_get_x402scan_stats(false);
    $allUrlsDecoded = rawurldecode(implode("\n", $capturedUrls));
    t_assert('Cache miss consulta origins.get + 3 janelas (1d/7d/30d) com carteiras EVM e Solana',
        $httpCallCount === 4
        && ($live['status'] ?? '') === 'live'
        && ($live['stale'] ?? true) === false
        && ($live['windows']['d1']['transactions'] ?? -1) === 31
        && abs((float) ($live['windows']['d1']['volume_usdc'] ?? 0) - 0.3550) < 0.0001
        && ($live['windows']['d7']['transactions'] ?? -1) === 41
        && ($live['windows']['d30']['transactions'] ?? -1) === 68
        && abs((float) ($live['windows']['d30']['volume_usdc'] ?? 0) - 4.7300) < 0.0001
        && ($live['windows']['d30']['unique_buyers'] ?? -1) === 4
        && !empty($live['origin']['has_x402_v2'])
        && str_contains($allUrlsDecoded, strtolower(GRID402_WALLET_ADDRESS))
        && str_contains($allUrlsDecoded, GRID402_SOLANA_WALLET_ADDRESS)
    );

    $cacheFile = $tmpDir . DIRECTORY_SEPARATOR . 'x402scan_cache.json';
    t_assert('Persistiu cache atômico em x402scan_cache.json', is_file($cacheFile));

    $cached = grid402_get_x402scan_stats(false);
    t_assert('Segunda chamada dentro de 300s retorna status=cached com 0 chamadas HTTP extras',
        $httpCallCount === 4
        && ($cached['status'] ?? '') === 'cached'
        && ($cached['windows']['d30']['transactions'] ?? -1) === 68
    );

    $forced = grid402_get_x402scan_stats(true);
    t_assert('forceRefresh=true ignora cache e executa novas chamadas HTTP (status=live)',
        $httpCallCount === 8
        && ($forced['status'] ?? '') === 'live'
    );

    // Expirar cache e simular falha upstream -> stale_cache
    $expiredPayload = $forced;
    $expiredPayload['cached_at'] = time() - 900;
    grid402_atomic_write($cacheFile, $expiredPayload);

    $GLOBALS['grid402_x402scan_http_override'] = static fn(): array => ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'SimulatedTimeout'];

    $staleRes = grid402_get_x402scan_stats(false);
    t_assert('Falha upstream com cache expirado preserva dados anteriores com status=stale_cache e stale=true',
        ($staleRes['status'] ?? '') === 'stale_cache'
        && ($staleRes['stale'] ?? false) === true
        && ($staleRes['windows']['d30']['transactions'] ?? -1) === 68
    );

    // Remover cache e simular falha upstream -> unavailable
    @unlink($cacheFile);
    $unavailRes = grid402_get_x402scan_stats(false);
    t_assert('Falha upstream sem cache prévio retorna status=unavailable com valores nulos seguros',
        ($unavailRes['status'] ?? '') === 'unavailable'
        && ($unavailRes['stale'] ?? true) === false
        && array_key_exists('transactions', $unavailRes['windows']['d30'] ?? [])
        && $unavailRes['windows']['d30']['transactions'] === null
    );

    unset($GLOBALS['grid402_x402scan_http_override']);

    // -------------------------------------------------------------------------
    // [2] HTTP E2E: /admin?api=stats, ?refresh=1 e SSR HTML do cockpit
    // -------------------------------------------------------------------------
    echo "\n[2/2] Validando HTTP E2E de /admin?api=stats e cockpit SSR:\n";

    $adminUser = 'admin_x402scan';
    $adminPass = 'ScanAdmin#2026';
    grid402_atomic_write(GRID402_FILE_ADMIN_AUTH, [
        'username' => $adminUser,
        'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]),
        'two_factor_telegram' => false,
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ]);

    $wrapperRouter = $tmpDir . DIRECTORY_SEPARATOR . 'admin_x402scan_router.php';
    $projectRouter = str_replace('\\', '/', realpath(__DIR__ . '/../public_html/router.php'));
    $originConstExport = var_export(GRID402_X402SCAN_ORIGIN_ID, true);
    $projectRouterExport = var_export($projectRouter, true);

    file_put_contents($wrapperRouter, <<<PHP
<?php
\$GLOBALS['grid402_x402scan_http_override'] = static function (string \$url, int \$timeoutSec): array {
    if (str_contains(\$url, 'public.origins.get')) {
        return [
            'ok' => true, 'status' => 200, 'error' => null,
            'data' => ['result' => ['data' => ['json' => [
                'id' => {$originConstExport},
                'title' => 'Grid402 - Global Open-Data Hub',
                'hasX402V2Resource' => true,
                'updatedAt' => '2026-11-28T01:33:02.628Z',
            ]]]],
        ];
    }
    \$dec = rawurldecode(\$url);
    \$map = [
        '"timeframe":1'  => ['total_transactions' => 31, 'total_amount' => 355000,  'unique_buyers' => 1, 'latest_block_timestamp' => '2026-11-28T01:36:42.000Z'],
        '"timeframe":7'  => ['total_transactions' => 41, 'total_amount' => 480000,  'unique_buyers' => 2, 'latest_block_timestamp' => '2026-11-28T01:36:42.000Z'],
        '"timeframe":30' => ['total_transactions' => 68, 'total_amount' => 4730000, 'unique_buyers' => 4, 'latest_block_timestamp' => '2026-11-27T13:43:53.000Z'],
    ];
    foreach (\$map as \$needle => \$row) {
        if (str_contains(\$dec, \$needle)) {
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['result' => ['data' => ['json' => \$row]]]];
        }
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'MockMiss'];
};
return require {$projectRouterExport};
PHP);

    $server = grid402_start_server([
        'router' => $wrapperRouter,
        'probe' => '/status',
        'env' => [
            'GRID402_STORAGE_DIR' => $tmpDir,
            'GRID402_DISABLE_TELEGRAM' => '1',
        ],
    ]);

    try {
        $baseUrl = $server['base_url'];

        $rUnauth = $httpCall('GET', "{$baseUrl}/admin?api=stats");
        t_assert('GET /admin?api=stats sem sessão retorna HTTP 401', $rUnauth['code'] === 401);

        $cookieFile = $tmpDir . DIRECTORY_SEPARATOR . 'admin_cookies.txt';
        $rLoginGet = $httpCall('GET', "{$baseUrl}/admin", [], null, $cookieFile);
        preg_match('/name="csrf_token"\s+value="([^"]+)"/', $rLoginGet['body'], $mCsrf);
        $csrf = $mCsrf[1] ?? '';
        $rLoginPost = $httpCall('POST', "{$baseUrl}/admin", [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'username' => $adminUser,
            'password' => $adminPass,
            'csrf_token' => $csrf,
        ]), $cookieFile);
        t_assert('Login /admin efetuado com sucesso (HTTP 302)', $rLoginPost['code'] === 302);

        $rDash = $httpCall('GET', "{$baseUrl}/admin", [], null, $cookieFile);
        t_assert('/admin HTML renderiza painel x402scan em Receita e Passes e card resumo no Resumo Executivo',
            $rDash['code'] === 200
            && str_contains($rDash['body'], 'id="x402scan-panel"')
            && str_contains($rDash['body'], 'id="x402scan-summary-card"')
            && str_contains($rDash['body'], 'x402scan — Índice Público On-Chain')
            && str_contains($rDash['body'], 'Últimas 24h (1d)')
            && str_contains($rDash['body'], 'US$ 4.7300')
            && str_contains($rDash['body'], 'https://www.x402scan.com/server/' . GRID402_X402SCAN_ORIGIN_ID)
        );

        $rStatsCached = $httpCall('GET', "{$baseUrl}/admin?api=stats", [], null, $cookieFile);
        t_assert('/admin?api=stats retorna objeto x402scan com janelas d1/d7/d30 e status=cached (SSR já aqueceu cache)',
            $rStatsCached['code'] === 200
            && ($rStatsCached['json']['x402scan']['status'] ?? '') === 'cached'
            && ($rStatsCached['json']['x402scan']['windows']['d30']['transactions'] ?? -1) === 68
            && abs((float) ($rStatsCached['json']['x402scan']['windows']['d30']['volume_usdc'] ?? 0) - 4.7300) < 0.0001
        );

        $rStatsRefresh = $httpCall('GET', "{$baseUrl}/admin?api=stats&refresh=1", [], null, $cookieFile);
        t_assert('/admin?api=stats&refresh=1 força recarga ao vivo retornando status=live',
            $rStatsRefresh['code'] === 200
            && ($rStatsRefresh['json']['x402scan']['status'] ?? '') === 'live'
            && ($rStatsRefresh['json']['x402scan']['origin']['has_x402_v2'] ?? false) === true
        );
    } finally {
        grid402_stop_server($server);
    }
} finally {
    $cleanupRecursive($tmpDir);
}

t_finish('TESTES X402SCAN NO PAINEL ADMIN');
