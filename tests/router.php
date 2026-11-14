<?php
/**
 * Roteador HTTP para testes locais com o servidor embutido do PHP
 */
declare(strict_types=1);

$uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/status') {
    $_GET['api'] = 'status';
    $_GET['format'] = 'json';
    require __DIR__ . '/../public_html/index.php';
    return true;
}

$localPages = [
    '/' => null,
    '/agents' => 'agents.php',
    '/catalog' => 'catalog.php',
    '/trust' => 'trust.php',
    '/growth' => 'growth_program.php',
    '/agenthub' => 'agenthub.php',
    '/payment-dashboard' => 'payment_dashboard.php',
    '/reputation' => 'builder_reputation.php',
    '/growth_admin' => 'growth_admin.php',
    '/mcp' => 'mcp.php',
];

if (isset($localPages[$uri])) {
    $file = $localPages[$uri];
    if ($file) {
        require __DIR__ . '/../public_html/' . $file;
        return true;
    }
}

if (preg_match('#^/api/v1/([a-z0-9_-]+)#', $uri, $matches)) {
    $_GET['api'] = $matches[1];
    require __DIR__ . '/../public_html/index.php';
    return true;
}

$staticFile = __DIR__ . '/../public_html' . $uri;
if (file_exists($staticFile) && !is_dir($staticFile)) {
    return false;
}

require __DIR__ . '/../public_html/index.php';
return true;

