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

// 3. Fallback para o Gateway central (index.php)
require __DIR__ . '/index.php';

