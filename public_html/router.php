<?php
/**
 * Grid402 - Roteador para Servidor Web Embutido do PHP (CLI -S)
 * Permite testar rotas limpas (/api/v1/*, /badge/*, /status, /workdays, /catalog, /trust, /mcp, /agents, /dossie-kyb, /terms, /privacy) localmente.
 */

declare(strict_types=1);

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$normalizedUri = rtrim($uri, '/');
if ($normalizedUri === '') {
    $normalizedUri = '/';
}

// 0. Bloqueio estrito de acesso HTTP direto a diretorios e modulos internos (HTTP 403)
if (
    preg_match('#^/(services|includes|private_data)(/|$)#i', $uri) === 1
    || preg_match('#^/(config|x402|facilitator|metrics|logger)\.php$#i', $uri) === 1
    || preg_match('#^/\.(env|git)#i', $uri) === 1
    || str_contains($uri, '..')
) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Forbidden', 'message' => 'Direct HTTP access to internal resources is forbidden.']);
    exit;
}

// Handler dinamico para robots.txt (identico ao .htaccess da Hostinger)
if ($normalizedUri === '/robots.txt') {
    require __DIR__ . '/robots.php';
    exit;
}

// Alias /.well-known/x402 -> /.well-known/x402.json
if ($normalizedUri === '/.well-known/x402') {
    header('Content-Type: application/json; charset=utf-8');
    readfile(__DIR__ . '/.well-known/x402.json');
    exit;
}

$filePath = __DIR__ . $uri;

// 1. Arquivos estáticos reais (llms.txt, assets, manifestos públicos) são servidos diretamente pelo PHP
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// 2. Roteamento de Badges SVG
if (preg_match('#^/badge/([a-zA-Z0-9_\-]+)(\.svg)?$#', $uri, $matches)) {
    $_GET['target'] = $matches[1];
    require __DIR__ . '/badge.php';
    exit;
}

// 3. Roteamento do Painel Admin (regra unica)
if (preg_match('#^/(admin|dashboard)(/.*)?$#', $uri)) {
    require __DIR__ . '/admin.php';
    exit;
}

// 4. Atalho direto /workdays -> API workdays
if ($normalizedUri === '/workdays') {
    $_GET['api'] = 'workdays';
    require __DIR__ . '/index.php';
    exit;
}

// 5. Rotas dedicadas de paginas publicas e Hosted MCP
$directRoutes = [
    '/catalog' => 'catalog.php',
    '/docs' => 'catalog.php',
    '/trust' => 'trust.php',
    '/mcp' => 'mcp.php',
    '/agents' => 'agents.php',
    '/dossie-kyb' => 'dossie_kyb.php',
    '/growth' => 'growth_program.php',
    '/growth_admin' => 'growth_admin.php',
    '/agent-hub' => 'agenthub.php',
    '/builder' => 'builder_reputation.php',
    '/reputation' => 'builder_reputation.php',
    '/builder_reputation' => 'builder_reputation.php',
];
if (isset($directRoutes[$normalizedUri])) {
    require __DIR__ . '/' . $directRoutes[$normalizedUri];
    exit;
}

// 6. Paginas SEO landings
$seoLandings = [
    '/api-cnpj' => 'api-cnpj',
    '/api-sancoes-cgu' => 'api-sancoes-cgu',
    '/api-dias-uteis-anbima' => 'api-dias-uteis-anbima',
    '/url-to-markdown-api' => 'url-to-markdown-api',
    '/global-kyb-api' => 'global-kyb-api',
    '/zero-pii-email-verification-api' => 'zero-pii-email-verification-api',
];
if (isset($seoLandings[$normalizedUri])) {
    $_GET['slug'] = $seoLandings[$normalizedUri];
    require __DIR__ . '/seo_landing.php';
    exit;
}

// 7. Pagina de compra de Session Pass e Payment Dashboard
if (preg_match('#^/pass(/.*)?$#', $uri)) {
    require __DIR__ . '/pass.php';
    exit;
}
if (preg_match('#^/payment-dashboard(/.*)?$#', $uri)) {
    require __DIR__ . '/payment_dashboard.php';
    exit;
}

// 8. Fallback para o Gateway central (index.php)
require __DIR__ . '/index.php';

