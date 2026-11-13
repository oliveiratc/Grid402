<?php
/**
 * Grid402 - Roteador para Servidor Web Embutido do PHP (CLI -S)
 * Permite testar rotas limpas (/api/v1/*, /badge/*, /status, /terms, /privacy) localmente.
 */

declare(strict_types=1);

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$filePath = __DIR__ . $uri;

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

// 5. Pagina de compra de Session Pass
if (preg_match('#^/pass(/.*)?$#', $uri)) {
    require __DIR__ . '/pass.php';
    exit;
}

// 6. Pagina de planos BRL (Alavanca 2)
if (preg_match('#^/plans(/.*)?$#', $uri)) {
    require __DIR__ . '/plans.php';
    exit;
}

// 7. Trial gratuito (Alavanca 2)
if (preg_match('#^/trial(/.*)?$#', $uri)) {
    require __DIR__ . '/trial.php';
    exit;
}

// 8. Fallback para o Gateway central (index.php)
require __DIR__ . '/index.php';

