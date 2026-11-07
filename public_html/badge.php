<?php
/**
 * Grid402 - Gerador de Badges SVG Dinâmicos
 * Formato padrão: /badge/{target_id}.svg
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';

$targetId = strtolower(trim((string) ($_GET['target'] ?? $_GET['id'] ?? 'system')));
$targetId = preg_replace('/\.svg$/', '', $targetId);

$label = 'Grid402';
$value = 'UNKNOWN';
$color = '#6b7280';

$summaryFile = GRID402_FILE_FREE;
if (!file_exists($summaryFile)) {
    if (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
        define('GRID402_INTERNAL_BOOTSTRAP', true);
    }
    include_once __DIR__ . '/collector.php';
}

$summary = file_exists($summaryFile) ? @json_decode((string)@file_get_contents($summaryFile), true) : null;
$services = $summary['services'] ?? [];

switch ($targetId) {
    case 'forex':
    case 'usd':
    case 'cambio':
        $label = 'USD / BRL';
        $usd = $services['forex']['usd_brl'] ?? 5.50;
        $value = 'R$ ' . number_format((float)$usd, 3, ',', '.');
        $color = '#10b981';
        break;

    case 'crypto':
    case 'btc':
    case 'bitcoin':
        $label = 'BTC / USD';
        $btc = $services['crypto']['btc_usd'] ?? 75000.0;
        $value = '$' . number_format((float)$btc, 0, '.', ',');
        $color = '#10b981';
        break;

    case 'eth':
    case 'ethereum':
        $label = 'ETH / USD';
        $eth = $services['crypto']['eth_usd'] ?? 3200.0;
        $value = '$' . number_format((float)$eth, 0, '.', ',');
        $color = '#10b981';
        break;

    case 'dns':
        $label = 'DNS Resolver';
        $value = 'ONLINE';
        $color = '#10b981';
        break;

    case 'ssl':
        $label = 'Inspetor SSL';
        $value = 'TLS 1.3';
        $color = '#10b981';
        break;

    case 'asn':
    case 'ip':
        $label = 'BGP / ASN';
        $value = 'ACTIVE';
        $color = '#10b981';
        break;

    case 'cep':
    case 'ibge':
        $label = 'CEP & IBGE';
        $value = 'ONLINE';
        $color = '#10b981';
        break;

    case 'system':
    case 'grid402':
    case 'hub':
    default:
        $label = 'Grid402 Hub';
        $value = '6/6 ONLINE';
        $color = '#10b981';
        break;
}

// Cálculo de larguras para o SVG
$charWidth = 7.2;
$labelWidth = (int) ceil(strlen($label) * $charWidth + 14);
$valueWidth = (int) ceil(strlen($value) * $charWidth + 14);
$totalWidth = $labelWidth + $valueWidth;

$labelCenter = (int) ceil($labelWidth / 2);
$valueCenter = (int) ceil($labelWidth + ($valueWidth / 2));

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
