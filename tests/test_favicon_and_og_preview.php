<?php
/**
 * Grid402 - Testes Automatizados de Favicons, Web Manifest e Pré-Visualização Open Graph / Twitter
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_server.php';

echo "=== TESTANDO FAVICONS, MANIFEST E PRÉ-VISUALIZAÇÃO OG/TWITTER ===\n";

$passed = 0;
function og_check(bool $cond, string $label): void {
    global $passed;
    if (!$cond) {
        fwrite(STDERR, "  [FAIL] {$label}\n");
        exit(1);
    }
    $passed++;
    echo "  [PASS] {$label}\n";
}

$pubDir = __DIR__ . '/../public_html';

// 1. Validação estrutural dos arquivos estáticos
echo "\n[1] Verificando integridade dos assets estáticos (SVG, ICO, PNG, webmanifest)...\n";
$svgRoot = (string) @file_get_contents($pubDir . '/favicon.svg');
$svgAssets = (string) @file_get_contents($pubDir . '/assets/favicon.svg');
og_check($svgRoot !== '' && str_contains($svgRoot, '<svg') && str_contains($svgRoot, 'viewBox="0 0 64 64"'), 'public_html/favicon.svg vetorial válido');
og_check($svgRoot === $svgAssets, 'public_html/assets/favicon.svg sincronizado com raiz');

foreach (['favicon.ico', 'assets/favicon.ico'] as $icoRel) {
    $icoPath = $pubDir . '/' . $icoRel;
    og_check(is_file($icoPath) && filesize($icoPath) > 500, "{$icoRel} presente e não vazio");
}

$pngDims = [
    'assets/favicon-16x16.png' => [16, 16],
    'assets/favicon-32x32.png' => [32, 32],
    'assets/apple-touch-icon.png' => [180, 180],
    'assets/icon-192x192.png' => [192, 192],
    'assets/icon-512x512.png' => [512, 512],
    'assets/og-card-global.png' => [1200, 630],
    'assets/og-card-dossie-kyb.png' => [1200, 630],
];
foreach ($pngDims as $rel => [$expW, $expH]) {
    $full = $pubDir . '/' . $rel;
    $info = @getimagesize($full);
    og_check(
        is_array($info) && ($info[0] ?? 0) === $expW && ($info[1] ?? 0) === $expH && ($info[2] ?? 0) === IMAGETYPE_PNG,
        "{$rel} é PNG exato {$expW}x{$expH}"
    );
}

$manifestRaw = (string) @file_get_contents($pubDir . '/site.webmanifest');
$manifestJson = json_decode($manifestRaw, true);
og_check(
    is_array($manifestJson)
    && ($manifestJson['short_name'] ?? '') === 'Grid402'
    && ($manifestJson['theme_color'] ?? '') === '#060B19'
    && count((array) ($manifestJson['icons'] ?? [])) >= 2,
    'public_html/site.webmanifest é JSON válido com ícones 192/512 e theme_color oficial'
);

// 2. Testes HTTP E2E de assets e meta tags em todas as rotas HTML
echo "\n[2] Subindo servidor local para validar HTTP 200 e meta tags OG/Twitter/Favicon...\n";
$tmpStorage = sys_get_temp_dir() . '/grid402_og_test_' . getmypid() . '_' . bin2hex(random_bytes(3));
mkdir($tmpStorage, 0775, true);
register_shutdown_function(static function () use ($tmpStorage): void {
    foreach (glob($tmpStorage . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpStorage);
});

$server = grid402_start_server([
    'router' => __DIR__ . '/router.php',
    'env' => [
        'GRID402_STORAGE_DIR' => $tmpStorage,
        'GRID402_DISABLE_TELEGRAM' => '1',
    ],
]);
$baseUrl = $server['base_url'];

$httpGet = static function (string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SocialPreviewCheck/1.0)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['code' => $code, 'ct' => $ct, 'body' => $body];
};

try {
    foreach ([
        '/favicon.svg',
        '/favicon.ico',
        '/site.webmanifest',
        '/assets/favicon-32x32.png',
        '/assets/favicon-16x16.png',
        '/assets/apple-touch-icon.png',
        '/assets/og-card-global.png',
        '/assets/og-card-dossie-kyb.png',
    ] as $assetRoute) {
        $resp = $httpGet($baseUrl . $assetRoute);
        og_check($resp['code'] === 200 && strlen($resp['body']) > 100, "Asset {$assetRoute} responde HTTP 200");
    }

    $publicRoutes = [
        ['path' => '/', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/'],
        ['path' => '/dossie-kyb', 'card' => 'og-card-dossie-kyb.png', 'locale' => 'pt_BR', 'url' => 'https://grid402.online/dossie-kyb'],
        ['path' => '/pass', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/pass'],
        ['path' => '/catalog', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/catalog'],
        ['path' => '/agents', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/agents'],
        ['path' => '/trust', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/trust'],
        ['path' => '/api-cnpj', 'card' => 'og-card-dossie-kyb.png', 'locale' => 'pt_BR', 'url' => 'https://grid402.online/api-cnpj'],
        ['path' => '/global-kyb-api', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/global-kyb-api'],
        ['path' => '/growth', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/growth'],
        ['path' => '/payment-dashboard', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/payment-dashboard'],
        ['path' => '/about', 'card' => 'og-card-global.png', 'locale' => 'en_US', 'url' => 'https://grid402.online/about'],
    ];

    foreach ($publicRoutes as $rt) {
        $r = $httpGet($baseUrl . $rt['path']);
        $b = $r['body'];
        og_check($r['code'] === 200, "{$rt['path']} responde HTTP 200");
        og_check(
            str_contains($b, '<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=3">')
            && str_contains($b, '<link rel="manifest" href="/site.webmanifest?v=3">')
            && str_contains($b, '<meta name="theme-color" content="#060B19">'),
            "{$rt['path']} inclui favicon.svg, site.webmanifest e theme-color"
        );
        og_check(
            str_contains($b, '<meta property="og:title"')
            && str_contains($b, '<meta property="og:description"')
            && str_contains($b, '<meta property="og:url" content="' . $rt['url'] . '">')
            && str_contains($b, '<meta property="og:locale" content="' . $rt['locale'] . '">')
            && str_contains($b, 'https://grid402.online/assets/' . $rt['card'] . '?v=3')
            && str_contains($b, '<meta property="og:image:width" content="1200">')
            && str_contains($b, '<meta property="og:image:height" content="630">')
            && str_contains($b, '<meta name="twitter:card" content="summary_large_image">')
            && str_contains($b, '<meta name="twitter:image" content="https://grid402.online/assets/' . $rt['card'] . '?v=3">'),
            "{$rt['path']} emite Open Graph + Twitter Card válidos ({$rt['card']} / {$rt['locale']})"
        );
    }

    $rAdmin = $httpGet($baseUrl . '/admin');
    og_check(
        $rAdmin['code'] === 200
        && str_contains($rAdmin['body'], '/favicon.svg?v=3')
        && str_contains($rAdmin['body'], '<meta name="robots" content="noindex, nofollow">')
        && !str_contains($rAdmin['body'], 'property="og:image"'),
        '/admin mantém favicons, emite noindex/nofollow e não expõe OG social público'
    );
} finally {
    grid402_stop_server($server);
}

echo "\n=== TODOS OS {$passed} TESTES DE FAVICON E OG PREVIEW PASSARAM! ===\n";
exit(0);
