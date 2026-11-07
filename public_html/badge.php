<?php
/**
 * Grid402 - Gerador de Badges SVG Dinâmicos
 * 
 * Permite embeddar status em tempo real em READMEs do GitHub, sites e documentações.
 * Formato padrão: /badge/{target_id}.svg
 * Exemplo: /badge/sefaz_sp.svg
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';

// Captura parâmetro de alvo
$targetId = strtolower(trim((string) ($_GET['target'] ?? $_GET['id'] ?? 'system')));
$targetId = preg_replace('/\.svg$/', '', $targetId);

// Inicializa dados padrão
$label = 'Grid402';
$value = 'UNKNOWN';
$color = '#6b7280'; // cinza

// Tenta ler targets.json ou summary_free.json
$summaryFile = GRID402_FILE_FREE;
$paidFile = GRID402_FILE_PAID;

// Se os arquivos de dados ainda não existirem (cold-start), executa coleta sob demanda
if (!file_exists($summaryFile) || !file_exists($paidFile)) {
    if (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
        define('GRID402_INTERNAL_BOOTSTRAP', true);
    }
    include_once __DIR__ . '/collector.php';
}

$targetData = null;
$categoryData = null;
$systemData = null;

if (file_exists($paidFile)) {
    $paidJson = @json_decode((string) @file_get_contents($paidFile), true);
    if (is_array($paidJson) && isset($paidJson['targets'])) {
        foreach ($paidJson['targets'] as $t) {
            if (strtolower($t['id']) === $targetId) {
                $targetData = $t;
                break;
            }
        }
    }
}

if (file_exists($summaryFile)) {
    $summaryJson = @json_decode((string) @file_get_contents($summaryFile), true);
    if (is_array($summaryJson)) {
        $systemData = $summaryJson;
        if (isset($summaryJson['categories'])) {
            foreach ($summaryJson['categories'] as $catName => $cat) {
                if (strtolower($catName) === $targetId || 
                    ($targetId === 'pix' && str_contains(strtolower($catName), 'pix')) ||
                    ($targetId === 'infra' && str_contains(strtolower($catName), 'infra')) ||
                    ($targetId === 'gateways' && str_contains(strtolower($catName), 'gateway')) ||
                    ($targetId === 'sefaz' && str_contains(strtolower($catName), 'sefaz'))) {
                    $categoryData = array_merge($cat, ['name' => $catName]);
                    break;
                }
            }
        }
    }
}

// Resolução de status e rótulo
if ($targetData !== null) {
    $label = $targetData['name'];
    $status = $targetData['status'];
    $latency = (int) round($targetData['latency_ms'] ?? 0);
    
    if ($status === 'UP') {
        $value = "UP {$latency}ms";
        $color = '#10b981'; // verde
    } elseif ($status === 'DEGRADED') {
        $value = "DEGRADED {$latency}ms";
        $color = '#f59e0b'; // amarelo/laranja
    } else {
        $value = 'DOWN';
        $color = '#ef4444'; // vermelho
    }
} elseif ($categoryData !== null) {
    $label = $categoryData['name'];
    $status = $categoryData['status'];
    $avgLatency = (int) round($categoryData['avg_latency_ms'] ?? 0);
    $upRatio = "{$categoryData['targets_up']}/{$categoryData['targets_total']}";

    if ($status === 'UP') {
        $value = "UP {$avgLatency}ms ({$upRatio})";
        $color = '#10b981';
    } elseif ($status === 'DEGRADED') {
        $value = "DEGRADED ({$upRatio})";
        $color = '#f59e0b';
    } else {
        $value = "DOWN ({$upRatio})";
        $color = '#ef4444';
    }
} elseif ($systemData !== null && in_array($targetId, ['system', 'grid402', 'all', ''])) {
    $label = 'Grid402';
    $status = $systemData['system_status'] ?? 'UP';
    $healthy = "{$systemData['healthy_targets']}/{$systemData['total_targets']}";

    if ($status === 'UP') {
        $value = "UP ({$healthy})";
        $color = '#10b981';
    } else {
        $value = "{$status} ({$healthy})";
        $color = ($status === 'DEGRADED') ? '#f59e0b' : '#ef4444';
    }
} else {
    $label = strtoupper(str_replace('_', ' ', $targetId));
    $value = 'NOT FOUND';
    $color = '#6b7280';
}

// Cálculo de larguras proporcionais para o SVG estilo Shields.io
$charWidth = 7.2;
$labelWidth = (int) ceil(strlen($label) * $charWidth + 14);
$valueWidth = (int) ceil(strlen($value) * $charWidth + 14);
$totalWidth = $labelWidth + $valueWidth;

$labelCenter = (int) ceil($labelWidth / 2);
$valueCenter = (int) ceil($labelWidth + ($valueWidth / 2));

// Cabeçalhos HTTP
if (!headers_sent()) {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=60, s-maxage=60');
    header('Access-Control-Allow-Origin: *');
    header('Vary: Accept-Encoding');
}

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$totalWidth}" height="20" role="img" aria-label="{$label}: {$value}">
  <title>{$label}: {$value}</title>
  <linearGradient id="g402-shine" x2="0" y2="100%">
    <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
    <stop offset="1" stop-opacity=".1"/>
  </linearGradient>
  <clipPath id="g402-clip">
    <rect width="{$totalWidth}" height="20" rx="3" fill="#fff"/>
  </clipPath>
  <g clip-path="url(#g402-clip)">
    <rect width="{$labelWidth}" height="20" fill="#2d3748"/>
    <rect x="{$labelWidth}" width="{$valueWidth}" height="20" fill="{$color}"/>
    <rect width="{$totalWidth}" height="20" fill="url(#g402-shine)"/>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" text-rendering="geometricPrecision" font-size="11">
    <text aria-hidden="true" x="{$labelCenter}" y="15" fill="#010101" fill-opacity=".3">{$label}</text>
    <text x="{$labelCenter}" y="14">{$label}</text>
    <text aria-hidden="true" x="{$valueCenter}" y="15" fill="#010101" fill-opacity=".3">{$value}</text>
    <text x="{$valueCenter}" y="14">{$value}</text>
  </g>
</svg>
SVG;
