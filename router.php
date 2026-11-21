<?php
/**
 * Grid402 - Roteador para Servidor Web Embutido do PHP (CLI -S)
 * Permite testar rotas limpas (/api/v1/*, /badge/*, /status, /terms, /privacy) localmente.
 */

declare(strict_types=1);

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Handler dinamico para robots.txt (identico ao .htaccess da Hostinger)
if ($uri === '/robots.txt') {
    require __DIR__ . '/robots.php';
    exit;
}

// 1. Arquivos estáticos reais (llms.txt, arquivos em disco) são servidos diretamente pelo PHP
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// 2. Roteamento de Badges SVG
if (preg_match('#^/badge/([a-zA-Z0-9_\-]+)(\.svg)?$#', $uri, $matches)) {
    $_GET['target'] = $matches[1];
    require __DIR__ . '/badge.php';
    exit;
}

// 3. Roteamento do Painel Admin
if (preg_match('#^/(admin|dashboard)(/.*)?$#', $uri)) {
    require __DIR__ . '/admin.php';
    exit;
}

// 4. Painel Admin
if (preg_match('#^/(admin|dashboard)(/.*)?$#', $uri)) {
    require __DIR__ . '/admin.php';
    exit;
}

// 5. Pagina de compra de Session Pass e Payment Dashboard
if (preg_match('#^/pass(/.*)?$#', $uri)) {
    require __DIR__ . '/pass.php';
    exit;
}
if (preg_match('#^/payment-dashboard(/.*)?$#', $uri)) {
    require __DIR__ . '/payment_dashboard.php';
    exit;
}

// 6. Fallback para o Gateway central (index.php)
require __DIR__ . '/index.php';

