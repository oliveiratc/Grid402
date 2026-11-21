<?php
/**
 * Grid402 // Hosted MCP (streamable HTTP) — /mcp
 *
 * Servidor MCP público e anônimo (streamable HTTP / JSON-RPC 2.0 stateless):
 *  - initialize / tools/list / ping: livres;
 *  - tools/call com grid402_summary e grid402_verify: livres;
 *  - demais ferramentas: retornam erro de pagamento com `x402 accepts`
 *    quando sem pagamento; com pagamento válido (txHash ou PAYMENT-SIGNATURE),
 *    verificam/liquidam via grid402_validate_payment / grid402_validate_exact_payment
 *    e executam a chamada.
 *
 * Objetivo: expor uma URL pública (`https://grid402.online/mcp`) listável
 * em diretórios de servidores MCP (xpaysh/awesome-x402, Recall-Kitchen,
 * Glama, mcp.so etc.), sem custo operacional.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/x402.php';
require_once __DIR__ . '/facilitator.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/services/forex.php';
require_once __DIR__ . '/services/crypto.php';
require_once __DIR__ . '/services/dns.php';
require_once __DIR__ . '/services/ssl.php';
require_once __DIR__ . '/services/asn.php';
require_once __DIR__ . '/services/cep.php';
require_once __DIR__ . '/services/cnpj.php';
require_once __DIR__ . '/services/indicators.php';
require_once __DIR__ . '/services/holidays.php';
require_once __DIR__ . '/services/workdays.php';
require_once __DIR__ . '/services/compliance.php';
require_once __DIR__ . '/services/domain_audit.php';
require_once __DIR__ . '/services/kyb_audit.php';
require_once __DIR__ . '/services/dossier.php';
require_once __DIR__ . '/services/market_snapshot.php';
require_once __DIR__ . '/services/lei.php';
require_once __DIR__ . '/services/ofac.php';
require_once __DIR__ . '/services/notarize.php';
require_once __DIR__ . '/services/global_markets.php';
require_once __DIR__ . '/services/extract.php';
require_once __DIR__ . '/services/email_verify.php';
require_once __DIR__ . '/services/pass.php';
require_once __DIR__ . '/services/oracle_signer.php';

const MCP_PROTOCOL_VERSION = '2024-11-05';
const MCP_SERVER_NAME = 'grid402-mcp';
const MCP_SERVER_VERSION = '2.0.0';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment, x-payment, X-Payment-Network, x-payment-network, X-Session-Pass, x-session-pass, PAYMENT-SIGNATURE, payment-signature, Idempotency-Key, X-Request-Id');
header('Access-Control-Expose-Headers: PAYMENT-REQUIRED, PAYMENT-RESPONSE, X-PAYMENT-RESPONSE, X-Payment-Status, X-Payment-Amount, X-Payment-Network, X-Request-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Manifest para descoberta (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    echo json_encode([
        'name' => MCP_SERVER_NAME,
        'version' => MCP_SERVER_VERSION,
        'description' => 'Grid402 hosted MCP (HTTP/streamable). Brazilian public data and network utilities for AI agents. x402 pay-per-call on Base; free tools: grid402_summary, grid402_verify.',
        'x402' => [
            'protocol' => 'x402',
            'network' => 'base-mainnet',
            'chain_id' => 8453,
            'token' => 'USDC',
            'payTo' => GRID402_ORACLE_WALLET,
        ],
        'docs' => 'https://grid402.online/llms.txt',
        'repository' => 'https://github.com/oliveiratc/Grid402',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'MethodNotAllowed', 'code' => 405]);
    exit;
}

$rawInput = (string) file_get_contents('php://input');
if (strlen($rawInput) > 256 * 1024) {
    mcp_error(null, -32600, 'PayloadTooLarge');
}
if (trim($rawInput) === '') {
    mcp_error(null, -32700, 'EmptyBody');
}
$req = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($req)) {
    mcp_error(null, -32700, 'InvalidJSON');
}

// Envelope JSON-RPC 2.0
$jsonrpc = $req['jsonrpc'] ?? '';
$id = $req['id'] ?? null;
$method = (string) ($req['method'] ?? '');
$params = $req['params'] ?? [];
if ($jsonrpc !== '2.0' || $method === '') {
    mcp_error($id, -32600, 'InvalidRequest');
}

$responseId = $id;

switch ($method) {
    case 'initialize':
        mcp_result($responseId, [
            'protocolVersion' => MCP_PROTOCOL_VERSION,
            'capabilities' => ['tools' => (object) []],
            'serverInfo' => ['name' => MCP_SERVER_NAME, 'version' => MCP_SERVER_VERSION],
        ]);
        break;

    case 'notifications/initialized':
    case 'initialized':
        http_response_code(202);
        exit;

    case 'ping':
        mcp_result($responseId, (object) []);
        break;

    case 'tools/list':
        mcp_result($responseId, ['tools' => mcp_tools_definitions()]);
        break;

    case 'tools/call':
        $tool = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        mcp_tool_call($responseId, $tool, $arguments, $params);
        break;

    default:
        mcp_error($responseId, -32601, 'MethodNotFound');
}

// ------------------------------------------------------------ utilities

function mcp_result($id, $result): void {
    http_response_code(200);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => $result,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function mcp_error($id, int $code, string $message, ?array $data = null): void {
    http_response_code(200);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => array_filter([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], fn($v) => $v !== null),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function mcp_content_text(array $payload): array {
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]],
    ];
}

function mcp_tools_definitions(): array {
    $payProp = ['type' => 'string', 'description' => 'Optional txHash on Base Mainnet, Session Pass token (pass_g402_...), EIP-3009 base64 payload, or demo for Sandbox.'];
    $roAnn = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];
    $localRoAnn = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];
    $writeAnn = ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];

    return [
        ['name' => 'grid402_summary', 'description' => 'Consolidated health and availability summary for all 25 Grid402 services (free).', 'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []], 'annotations' => $localRoAnn],
        ['name' => 'grid402_forex', 'description' => 'Official BCB PTAX fiat exchange rates USD, EUR, GBP to BRL (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_crypto', 'description' => 'Real-time spot prices for BTC, ETH, SOL, USDC, BASE in USD and BRL (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_dns', 'description' => 'Authoritative DNS A, AAAA, MX, TXT, NS, CNAME record lookup (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string', 'description' => 'Domain name to resolve (e.g. github.com).'], 'payment_tx_hash' => $payProp], 'required' => ['domain']], 'annotations' => $roAnn],
        ['name' => 'grid402_ssl', 'description' => 'Inspect port 443 TLS/SSL certificate validity, issuer, SANs and expiration (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['host' => ['type' => 'string', 'description' => 'Target hostname to inspect on port 443 (e.g. base.org).'], 'payment_tx_hash' => $payProp], 'required' => ['host']], 'annotations' => $roAnn],
        ['name' => 'grid402_asn', 'description' => 'BGP routing intelligence: ASN, IP prefix, organization and country (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['ip' => ['type' => 'string', 'description' => 'Optional IPv4/IPv6 address (defaults to 1.1.1.1).'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_domain_audit', 'description' => 'Consolidated Domain Audit bundle (DNS + TLS/SSL + BGP/ASN) with risk score (0.0150 USDC / 3 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string', 'description' => 'Domain name to audit (e.g. base.org).'], 'payment_tx_hash' => $payProp], 'required' => ['domain']], 'annotations' => $roAnn],
        ['name' => 'grid402_kyb_audit', 'description' => 'Consolidated Corporate KYB Dossier (CNPJ + OFAC/UN Sanctions + GLEIF LEI) (0.0200 USDC / 4 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number (e.g. 00000000000191).'], 'name' => ['type' => 'string', 'description' => 'Optional legal entity name for OFAC screening.'], 'lei' => ['type' => 'string', 'description' => 'Optional 20-char ISO 17442 LEI code.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_get_dossier_360', 'description' => 'Premium KYB 360 Dossier with deterministic AI Verdict (APPROVED, FLAGGED, REJECTED), score 0-100, Receita Federal CNPJ, Simples/MEI and CGU CEIS/CNEP sanctions (0.0500 USDC / 10 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number (with or without mask, e.g. 00000000000191).'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_market_snapshot', 'description' => 'Consolidated Financial and Macro Snapshot (PTAX Forex + Crypto Spot + Selic/CDI/IPCA + ANBIMA) (0.0150 USDC / 3 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_cep', 'description' => 'Brazilian CEP postal lookup: street, neighborhood, city, state and IBGE code (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string', 'description' => '8-digit Brazilian CEP postal code (e.g. 01310100).'], 'payment_tx_hash' => $payProp], 'required' => ['code']], 'annotations' => $roAnn],
        ['name' => 'grid402_cnpj', 'description' => 'Brazilian Receita Federal CNPJ lookup: registration status, legal name, CNAE and address (0.0200 USDC / 4 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number (e.g. 00000000000191).'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_indicators', 'description' => 'Official BCB/IBGE macro indicators: Selic target/daily, CDI and IPCA 12m (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_holidays', 'description' => 'ANBIMA Brazilian banking holidays calendar for a given year (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['year' => ['type' => 'integer', 'description' => '4-digit target calendar year (e.g. 2026).'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $localRoAnn],
        ['name' => 'grid402_workdays', 'description' => 'ANBIMA banking business-day count and D+N projection between dates (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['start_date' => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD format.'], 'end_date' => ['type' => 'string', 'description' => 'Optional end date in YYYY-MM-DD format.'], 'days' => ['type' => 'integer', 'description' => 'Optional number of business days to add to start_date.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $localRoAnn],
        ['name' => 'grid402_sanctions', 'description' => 'Brazilian CGU CEIS/CNEP administrative sanctions lookup by CNPJ (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number to check against CEIS/CNEP.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_simples', 'description' => 'Brazilian Simples Nacional and SIMEI (MEI) tax regime status by CNPJ (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_compliance', 'description' => 'Compliance 360 risk score (Receita Federal + CGU + corporate history), 0-100 (0.0300 USDC / 6 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number for 360 compliance scoring.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_lei', 'description' => 'Global Corporate Identity (ISO 17442) via GLEIF across 200+ jurisdictions (0.0200 USDC / 4 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['lei' => ['type' => 'string', 'description' => '20-character ISO 17442 LEI code (e.g. 5493006MHB84DD0ZWV18).'], 'payment_tx_hash' => $payProp], 'required' => ['lei']], 'annotations' => $roAnn],
        ['name' => 'grid402_ofac', 'description' => 'Global Sanctions (OFAC SDN / UN) and Base Mainnet EVM wallet AML inspection (0.0200 USDC / 4 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['target' => ['type' => 'string', 'description' => 'EVM address (0x...) or entity name to screen against OFAC/UN lists.'], 'payment_tx_hash' => $payProp], 'required' => ['target']], 'annotations' => $roAnn],
        ['name' => 'grid402_notarize', 'description' => 'M2M Cryptographic Timestamp and SHA-256 Proof-of-Existence signed with ECDSA secp256k1 (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['content' => ['type' => 'string', 'description' => 'Text or JSON payload to hash (SHA-256) and timestamp cryptographically.'], 'payment_tx_hash' => $payProp], 'required' => ['content']], 'annotations' => $localRoAnn],
        ['name' => 'grid402_global_markets', 'description' => 'World Exchange Settlement Calendar (US, EU, UK, BR) and 30+ ECB FX Reference Rates (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['market' => ['type' => 'string', 'description' => 'Market code: US, EU, UK or BR.'], 'base' => ['type' => 'string', 'description' => 'Base currency for ECB FX reference rates (e.g. USD, EUR, BRL).'], 'date' => ['type' => 'string', 'description' => 'Optional target date in YYYY-MM-DD format.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_extract', 'description' => 'SSRF-hardened Web URL to LLM-ready clean Markdown extractor with metadata and absolute links (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'Public HTTP or HTTPS URL to fetch and convert to clean Markdown (e.g. https://example.com).'], 'max_chars' => ['type' => 'integer', 'description' => 'Optional maximum output character length (500 to 25000, default 12000).'], 'payment_tx_hash' => $payProp], 'required' => ['url']], 'annotations' => $roAnn],
        ['name' => 'grid402_email_verify', 'description' => 'Zero-PII B2B email deliverability audit: RFC syntax, DNS MX, SPF, DMARC, disposable domain and role-based score (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string', 'description' => 'Email address to audit without persisting raw PII (e.g. contato@grid402.online).'], 'payment_tx_hash' => $payProp], 'required' => ['email']], 'annotations' => $roAnn],
        ['name' => 'grid402_pass', 'description' => 'x402 Prepaid Session Pass issuance (Starter 2 USDC = 400 credits, Pro 5 USDC = 1,000 credits, Fleet 20 USDC = 6,000 credits) or status check.', 'inputSchema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'description' => 'Optional existing pass_g402_... token to check status without issuing a new pass.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $writeAnn],
        ['name' => 'grid402_pass_balance', 'description' => 'Free Session Pass balance and Spend Controls telemetry (free).', 'inputSchema' => ['type' => 'object', 'properties' => ['token' => ['type' => 'string', 'description' => 'Session Pass token (pass_g402_...) to inspect.']], 'required' => ['token']], 'annotations' => $localRoAnn],
        ['name' => 'grid402_verify', 'description' => 'Stateless verification of an _oracle_proof ECDSA secp256k1 signature (free).', 'inputSchema' => ['type' => 'object', 'properties' => ['payload' => ['type' => 'object', 'description' => 'Full JSON response object containing the _oracle_proof block to verify.']], 'required' => ['payload']], 'annotations' => $localRoAnn],
    ];
}

function mcp_accepts_unpaid(string $service, ?int $requiredRawUnits = null): array {
    $price = match (true) {
        $service === 'dossier' => '0.0500',
        in_array($service, ['cnpj', 'lei', 'ofac', 'kyb-audit'], true) => '0.0200',
        in_array($service, ['domain-audit', 'market-snapshot'], true) => '0.0150',
        in_array($service, ['sanctions', 'simples'], true) => '0.0100',
        $service === 'compliance' => '0.0300',
        $service === 'pass' => '2.0000',
        default => '0.0050',
    };
    $rawUnits = (int) ($requiredRawUnits ?? (int) round(((float) $price) * 1e6));
    return [
        'error' => 'PaymentRequired',
        'payment_required' => true,
        'service' => $service,
        'price_usdc' => $price,
        'price_raw_units' => $rawUnits,
        'accepts' => grid402_build_accepts($rawUnits),
        'x402_version' => 2,
        'facilitator' => 'https://facilitator.payai.network',
        'payTo' => GRID402_ORACLE_WALLET,
        'network' => 'base-mainnet',
        'chain_id' => 8453,
        'token' => 'USDC',
        'token_contract' => GRID402_USDC_CONTRACT,
        'hint' => 'Call the API endpoint directly (e.g. https://grid402.online/api/v1/' . $service . ') or pass payment_tx_hash / X-Payment / PAYMENT-SIGNATURE.',
    ];
}

function mcp_tool_call($responseId, string $tool, array $arguments, array $params): void {
    // Free tools
    if ($tool === 'grid402_summary') {
        $data = @json_decode((string) @file_get_contents(GRID402_FILE_FREE), true);
        mcp_result($responseId, mcp_content_text(is_array($data) ? $data : ['status' => 'unavailable']));
    }
    if ($tool === 'grid402_verify') {
        $payload = $arguments['payload'] ?? null;
        if (!is_array($payload)) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['message' => 'payload deve ser um objeto JSON com _oracle_proof.']);
        }
        $t0 = microtime(true);
        $verification = grid402_verify_oracle_proof($payload);
        $elapsedMs = round((microtime(true) - $t0) * 1000, 3);
        mcp_result($responseId, mcp_content_text(array_merge($verification, ['verification_time_ms' => $elapsedMs, 'status' => !empty($verification['valid']) ? 'AUTHENTIC_ORACLE_DATA' : 'VERIFICATION_FAILED'])));
    }

    if ($tool === 'grid402_pass_balance' || ($tool === 'grid402_pass' && !empty($arguments['status']))) {
        $token = trim((string)($arguments['token'] ?? $arguments['status'] ?? ''));
        $statusRes = grid402_get_session_pass_status($token);
        mcp_result($responseId, mcp_content_text($statusRes));
    }

    $map = [
        'grid402_forex' => ['forex', null, 5000],
        'grid402_crypto' => ['crypto', null, 5000],
        'grid402_dns' => ['dns', 'domain', 5000],
        'grid402_ssl' => ['ssl', 'host', 5000],
        'grid402_asn' => ['asn', 'ip', 5000],
        'grid402_domain_audit' => ['domain-audit', 'domain', 15000],
        'grid402_kyb_audit' => ['kyb-audit', 'cnpj', 20000],
        'grid402_get_dossier_360' => ['dossier', 'cnpj', 50000],
        'grid402_market_snapshot' => ['market-snapshot', null, 15000],
        'grid402_cep' => ['cep', 'code', 5000],
        'grid402_cnpj' => ['cnpj', 'cnpj', 20000],
        'grid402_indicators' => ['indicators', null, 5000],
        'grid402_holidays' => ['holidays', 'year', 5000],
        'grid402_workdays' => ['workdays', null, 5000],
        'grid402_sanctions' => ['sanctions', 'cnpj', 10000],
        'grid402_simples' => ['simples', 'cnpj', 10000],
        'grid402_compliance' => ['compliance', 'cnpj', 30000],
        'grid402_lei' => ['lei', 'lei', 20000],
        'grid402_ofac' => ['ofac', 'target', 20000],
        'grid402_notarize' => ['notarize', 'content', 5000],
        'grid402_global_markets' => ['global-markets', null, 5000],
        'grid402_extract' => ['extract', 'url', 5000],
        'grid402_email_verify' => ['email-verify', 'email', 5000],
        'grid402_pass' => ['pass', null, 2000000],
    ];
    if (!isset($map[$tool])) {
        mcp_error($responseId, -32602, 'UnknownTool', ['tool' => $tool]);
    }
    [$service, $requiredArg, $rawCost] = $map[$tool];
    if ($requiredArg !== null && empty($arguments[$requiredArg]) && $requiredArg !== 'year' && $requiredArg !== 'ip') {
        mcp_error($responseId, -32602, 'InvalidArguments', ['message' => "Parametro '{$requiredArg}' e obrigatorio para {$tool}."]);
    }

    // Validação prévia de parâmetros antes de cobrar / liquidar
    if (in_array($service, ['cnpj', 'sanctions', 'simples', 'compliance', 'kyb-audit', 'dossier'], true) && !empty($arguments['cnpj'])) {
        $cnpjDigits = preg_replace('/\D/', '', (string)$arguments['cnpj']);
        if ($cnpjDigits === '' || !grid402_validate_cnpj_digits($cnpjDigits)) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => 'InvalidCNPJ', 'message' => 'O CNPJ informado e matematicamente invalido.']);
        }
    }
    if ($service === 'cep' && !empty($arguments['code'])) {
        $cepDigits = preg_replace('/\D/', '', (string)$arguments['code']);
        if (strlen($cepDigits) !== 8) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => 'InvalidCEP', 'message' => 'O CEP informado deve conter 8 digitos.']);
        }
    }
    if ($service === 'lei' && !empty($arguments['lei'])) {
        $cleanLeiArg = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string)$arguments['lei'])));
        if (strlen($cleanLeiArg) !== 20) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => 'InvalidLEI', 'message' => 'O codigo LEI deve conter exatamente 20 caracteres alfanumericos (ISO 17442).']);
        }
    }
    if ($service === 'extract' && !empty($arguments['url'])) {
        $urlCheck = grid402_extract_validate_public_url((string) $arguments['url']);
        if (!$urlCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $urlCheck['error'], 'message' => $urlCheck['message']]);
        }
    }
    if ($service === 'email-verify' && !empty($arguments['email'])) {
        $emailCheck = grid402_email_verify_validate_input((string) $arguments['email']);
        if (!$emailCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $emailCheck['error'], 'message' => $emailCheck['message']]);
        }
    }

    // Payment handling (IMPORTANTE: nunca aplicar strtolower() em Base64 de PAYMENT-SIGNATURE!)
    $mcpHdrMap = [];
    $mcpRawHdrs = function_exists('getallheaders')
        ? (getallheaders() ?: [])
        : (function_exists('apache_request_headers') ? (apache_request_headers() ?: []) : []);
    if (is_array($mcpRawHdrs)) {
        foreach ($mcpRawHdrs as $hk => $hv) {
            $mcpHdrMap[strtolower((string) $hk)] = trim((string) $hv);
        }
    }
    $txHash = isset($arguments['payment_tx_hash']) ? trim((string)$arguments['payment_tx_hash']) : '';
    $xPaymentHeader = trim((string) ($_SERVER['HTTP_X_PAYMENT'] ?? $_SERVER['HTTP_X_SESSION_PASS'] ?? $mcpHdrMap['x-payment'] ?? $mcpHdrMap['x-session-pass'] ?? ''));
    $meta = isset($params['_meta']) && is_array($params['_meta']) ? $params['_meta'] : [];
    $x402Payment = isset($meta['x402/payment']) ? trim((string)$meta['x402/payment']) : '';
    $payHeader = trim((string) ($_SERVER['HTTP_PAYMENT_SIGNATURE'] ?? $mcpHdrMap['payment-signature'] ?? ''));
    $mcpAuthHeader = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $mcpHdrMap['authorization'] ?? ''));
    $mcpAuthProof = '';
    if (preg_match('/^(?:x402|payment)\s+(.+)$/is', $mcpAuthHeader, $am)) {
        $mcpAuthProof = trim($am[1]);
    } elseif (preg_match('/^bearer\s+((?:pass_g402_|0x[a-f0-9]{40,}|eyJ|\{).+)$/is', $mcpAuthHeader, $bm)) {
        $mcpAuthProof = trim($bm[1]);
    }
    $mcpNetHeader = trim((string) ($_SERVER['HTTP_X_PAYMENT_NETWORK'] ?? $mcpHdrMap['x-payment-network'] ?? ''));

    $effectiveProof = $payHeader !== ''
        ? $payHeader
        : ($x402Payment !== ''
            ? $x402Payment
            : ($xPaymentHeader !== '' ? $xPaymentHeader : ($mcpAuthProof !== '' ? $mcpAuthProof : $txHash)));
    if ($effectiveProof !== '' && function_exists('grid402_normalize_payment_proof_header')) {
        $effectiveProof = grid402_normalize_payment_proof_header($effectiveProof);
    }

    $clientIp = grid402_get_trusted_client_ip();

    if ($effectiveProof === '') {
        grid402_record_telemetry($service, 402, false, null, $clientIp, (float) number_format($rawCost / 1e6, 4, '.', ''), 'challenge_402');
        mcp_error($responseId, -32001, 'PaymentRequired', mcp_accepts_unpaid($service, $rawCost));
    }

    $validation = grid402_validate_payment(
        $effectiveProof,
        $rawCost,
        $clientIp,
        $service,
        $mcpNetHeader !== '' ? $mcpNetHeader : null,
        true
    );
    $proofIsPayload = grid402_classify_payment_proof($effectiveProof) === 'x402_payload';
    $mcpFailPayment = static function (array $val) use ($responseId, $service, $rawCost, $proofIsPayload, $effectiveProof, $clientIp): never {
        grid402_record_telemetry(
            $service,
            (int)($val['code'] ?? 402),
            false,
            $proofIsPayload ? null : $effectiveProof,
            $clientIp,
            (float) number_format($rawCost / 1e6, 4, '.', ''),
            null,
            ['failure_category' => $val['category'] ?? 'payment_rejected']
        );
        $unpaidData = mcp_accepts_unpaid($service, $rawCost);
        $unpaidData['reason'] = $val['reason'] ?? 'Falha na validacao do pagamento.';
        mcp_error($responseId, -32001, 'PaymentRequired', $unpaidData);
    };
    if (empty($validation['valid'])) {
        $mcpFailPayment($validation);
    }

    // Para emissao de Session Pass, liquidar antes de emitir para obter settlement_tx/lock_key real
    if ($service === 'pass' && !empty($validation['needs_settle'])) {
        $validation = grid402_settle_exact_payment($validation);
        if (empty($validation['valid'])) {
            $mcpFailPayment($validation);
        }
    }

    $isSandbox = !empty($validation['sandbox']);

    // Execute
    $result = match ($service) {
        'forex' => grid402_service_forex(),
        'crypto' => grid402_service_crypto(),
        'dns' => grid402_service_dns((string)$arguments['domain']),
        'ssl' => grid402_service_ssl((string)$arguments['host']),
        'asn' => grid402_service_asn(empty($arguments['ip']) ? null : (string)$arguments['ip']),
        'cep' => grid402_service_cep((string)$arguments['code']),
        'cnpj' => grid402_service_cnpj((string)$arguments['cnpj']),
        'indicators' => grid402_service_indicators(),
        'holidays' => grid402_service_holidays(isset($arguments['year']) ? (int)$arguments['year'] : null),
        'workdays' => grid402_service_workdays($arguments),
        'sanctions' => grid402_service_sanctions((string)$arguments['cnpj']),
        'simples' => grid402_service_simples((string)$arguments['cnpj']),
        'compliance' => grid402_service_compliance((string)$arguments['cnpj']),
        'domain-audit' => grid402_service_domain_audit((string)$arguments['domain']),
        'kyb-audit' => grid402_service_kyb_audit(
            (string)($arguments['cnpj'] ?? ''),
            (string)($arguments['name'] ?? ''),
            (string)($arguments['lei'] ?? '')
        ),
        'dossier' => grid402_service_dossier((string)$arguments['cnpj']),
        'market-snapshot' => grid402_service_market_snapshot(),
        'lei' => grid402_service_lei((string)$arguments['lei']),
        'ofac' => (static function () use ($arguments): array {
            $t = trim((string)($arguments['target'] ?? $arguments['address'] ?? ''));
            $n = trim((string)($arguments['name'] ?? ''));
            if ($t !== '' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $t) && $n === '') {
                return grid402_service_ofac('', $t);
            }
            return grid402_service_ofac($t, $n);
        })(),
        'notarize' => grid402_service_notarize((string)$arguments['content']),
        'global-markets' => grid402_service_global_markets(
            isset($arguments['market']) ? (string)$arguments['market'] : 'US',
            isset($arguments['date']) ? (string)$arguments['date'] : '',
            isset($arguments['base']) ? (string)$arguments['base'] : 'USD'
        ),
        'extract' => grid402_service_extract(
            (string)($arguments['url'] ?? ''),
            isset($arguments['max_chars']) ? (int)$arguments['max_chars'] : 12000
        ),
        'email-verify' => grid402_service_email_verify((string)$arguments['email'] ?? ''),
        'pass' => (static function () use ($validation, $proofIsPayload, $effectiveProof, $rawCost, $isSandbox): array {
            $actualPaid = (int) ($validation['raw_amount'] ?? $rawCost);
            $passTxRef = (string) ($validation['lock_key'] ?? $validation['settlement_tx'] ?? ($proofIsPayload ? ('mcp_' . bin2hex(random_bytes(8))) : $effectiveProof));
            return grid402_issue_session_pass(
                $passTxRef,
                $actualPaid > 0 ? $actualPaid : (int)$rawCost,
                $isSandbox
            );
        })(),
        default => ['code' => 500, 'error' => 'ToolDispatchMissing'],
    };

    $execCode = (is_array($result) && isset($result['code']) && is_int($result['code']) && $result['code'] >= 100 && $result['code'] < 600)
        ? $result['code']
        : 200;
    if ($execCode !== 200) {
        if (!empty($validation['needs_settle'])) {
            grid402_abort_verified_exact_payment($validation);
        }
        $paymentSourceErr = (string) ($validation['source'] ?? '');
        $settlementTxErr = $validation['settlement_tx'] ?? ($proofIsPayload ? null : $effectiveProof);
        grid402_record_telemetry(
            $service,
            $execCode,
            false,
            $settlementTxErr,
            $clientIp,
            0.0,
            null,
            ['payment_source' => $paymentSourceErr !== '' ? $paymentSourceErr : null]
        );
        mcp_result($responseId, mcp_content_text(is_array($result) ? $result : ['result' => $result]));
    }

    if (!empty($validation['needs_settle'])) {
        $validation = grid402_settle_exact_payment($validation);
        if (empty($validation['valid'])) {
            $mcpFailPayment($validation);
        }
    }

    $isSandbox = !empty($validation['sandbox']);
    $isVerifyOnly = (($validation['source'] ?? '') === 'facilitator_verify_only');
    $isRealPayment = !$isSandbox && !$isVerifyOnly;
    $paymentSourceVal = (string)($validation['source'] ?? '');
    $settlementTx = $validation['settlement_tx'] ?? ($proofIsPayload ? null : $effectiveProof);

    // Evita dupla contagem de receita quando pagamento é consumo de crédito de Session Pass
    $actualRawPaid = (int)($validation['raw_amount'] ?? $rawCost);
    $revenueUsdc = ($isSandbox || $isVerifyOnly || $paymentSourceVal === 'pass')
        ? 0.0
        : (float) number_format(($service === 'pass' && $actualRawPaid > 0 ? $actualRawPaid : $rawCost) / 1e6, 4, '.', '');

    grid402_record_telemetry(
        $service,
        200,
        $isRealPayment,
        $settlementTx,
        $clientIp,
        $revenueUsdc,
        $isSandbox ? 'sandbox' : ($isVerifyOnly ? 'verify_only' : null),
        ['payment_source' => $paymentSourceVal !== '' ? $paymentSourceVal : null]
    );

    if ($paymentSourceVal === 'facilitator') {
        $receiptHeader = grid402_build_payment_response_header($validation);
        header('PAYMENT-RESPONSE: ' . $receiptHeader);
        header('X-PAYMENT-RESPONSE: ' . $receiptHeader);
    }

    if (is_array($result) && (!isset($result['code']) || $result['code'] === 200)) {
        $result = grid402_attach_provenance_meta($result, $service);
        grid402_sign_data($result, $isSandbox);
    }

    mcp_result($responseId, mcp_content_text(is_array($result) ? $result : ['result' => $result]));
}
