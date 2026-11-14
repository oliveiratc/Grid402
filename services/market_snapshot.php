<?php
/**
 * Grid402 - Micro-serviço Composto de Snapshot de Mercado
 * Consolida Forex + Crypto + Indicadores Econômicos em uma única chamada paga via x402.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/forex.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/indicators.php';

function grid402_service_market_snapshot(): array {
    return [
        'success' => true,
        'snapshot' => [
            'forex' => grid402_service_forex(),
            'crypto' => grid402_service_crypto(),
            'indicators' => grid402_service_indicators(),
        ],
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}
