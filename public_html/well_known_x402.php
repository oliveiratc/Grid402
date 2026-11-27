<?php
/**
 * Grid402 - Manifesto Dinamico /.well-known/x402.json e /.well-known/x402
 *
 * Reflete em tempo real GRID402_SOLANA_X402_ENABLED e o mapa de facilitadores
 * ativos (Coinbase CDP + PayAI), mantendo paridade estrita com config.php.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/facilitator.php';
require_once __DIR__ . '/x402.php';

/**
 * Constroi o payload canonico /.well-known/x402.json a partir do runtime atual.
 *
 * @return array<string, mixed>
 */
function grid402_build_well_known_x402_manifest(): array {
    $canonicalBase = defined('GRID402_CANONICAL_DOMAIN')
        ? rtrim((string) GRID402_CANONICAL_DOMAIN, '/')
        : 'https://grid402.online';

    $sampleAccepts = grid402_build_accepts(5000);
    $networks = [];
    foreach ($sampleAccepts as $acc) {
        if (!empty($acc['network'])) {
            $networks[] = (string) $acc['network'];
        }
    }
    $networks = array_values(array_unique($networks));

    $facilitatorsMap = grid402_facilitator_active_map();
    $primaryNet = (GRID402_X402_NETWORK === 'base-sepolia') ? 'eip155:84532' : 'eip155:8453';
    $primaryFacilitatorUrl = (string) ($facilitatorsMap[$primaryNet]['url'] ?? GRID402_FACILITATOR_URL);

    $services = grid402_get_utility_services();
    $resources = [];
    foreach ($services as $slug => $svc) {
        $priceStr = (string) ($svc['price_usdc'] ?? '0');
        if ($slug === 'verify' || strtolower($priceStr) === 'gratuito' || (float) $priceStr <= 0.0) {
            continue;
        }
        $priceRaw = (int) round(((float) $priceStr) * 1000000);
        $meta = grid402_bazaar_service_metadata($slug);
        $desc = grid402_sanitize_bazaar_description((string) ($meta['description'] ?? $svc['description'] ?? 'Grid402 verifiable public data API.'));
        // Higiene discovery: evita ampersand no manifesto JSON
        $desc = str_replace('&', 'and', $desc);

        $resources[] = [
            'resource' => $canonicalBase . '/api/v1/' . $slug,
            'type' => 'http',
            'method' => ($slug === 'batch' ? 'POST' : 'GET'),
            'description' => $desc,
            'accepts' => grid402_build_accepts($priceRaw),
        ];
    }

    $solanaEnabled = function_exists('grid402_is_solana_x402_enabled') && grid402_is_solana_x402_enabled();
    $descNetworks = $solanaEnabled
        ? 'Base, Polygon, Arbitrum and Solana USDC'
        : 'Base, Polygon and Arbitrum USDC';

    $manifest = [
        'x402Version' => 2,
        'name' => 'Grid402 Network Utilities',
        'alias' => 'grid402',
        'description' => "27 paid M2M open data, token safety, prediction markets, multi-service batch execution, web extraction, email verification, global sanctions screening, Brazilian KYB/compliance and network utility endpoints under HTTP 402 x402 v2 ({$descNetworks}) plus free cryptographic verification.",
        'website' => $canonicalBase,
        'network' => $primaryNet,
        'networks' => $networks,
        'asset' => GRID402_X402_USDC_CONTRACT,
        'payTo' => GRID402_WALLET_ADDRESS,
        'facilitator' => $primaryFacilitatorUrl,
        'facilitators' => $facilitatorsMap,
        'solana_enabled' => $solanaEnabled,
    ];

    if ($solanaEnabled) {
        $manifest['solanaPayTo'] = GRID402_SOLANA_WALLET_ADDRESS;
        $manifest['solanaAsset'] = GRID402_SOLANA_USDC_MINT;
    }

    $manifest['resources'] = $resources;
    $manifest['discoverable'] = true;
    $manifest['protocol'] = 'x402';
    $manifest['scheme'] = 'exact';
    $manifest['settlement'] = 'EIP-3009 + Permit2';
    $manifest['updated'] = gmdate('Y-m-d');

    return $manifest;
}

if (!defined('GRID402_X402_MANIFEST_NO_EMIT') && (defined('GRID402_X402_MANIFEST_FORCE_EMIT') || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment, PAYMENT-SIGNATURE, Payment, Idempotency-Key');
        exit;
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode(grid402_build_well_known_x402_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
