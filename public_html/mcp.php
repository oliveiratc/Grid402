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
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/growth.php';
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
require_once __DIR__ . '/services/token_safety.php';
require_once __DIR__ . '/services/prediction_markets.php';
require_once __DIR__ . '/services/sec_company.php';
require_once __DIR__ . '/services/vuln_audit.php';
require_once __DIR__ . '/services/chain_telemetry.php';
require_once __DIR__ . '/services/batch.php';
require_once __DIR__ . '/services/pass.php';
require_once __DIR__ . '/services/oracle_signer.php';

const MCP_PROTOCOL_VERSION = '2024-11-05';
const MCP_SUPPORTED_PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];
const MCP_SERVER_NAME = 'grid402-mcp';
const MCP_SERVER_VERSION = '2.2.0';
const MCP_AUTO_TRIAL_DAILY_LIMIT = 1;
const MCP_AUTO_TRIAL_WINDOW_SECONDS = 86400;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment, x-payment, X-Payment-Network, x-payment-network, X-Session-Pass, x-session-pass, PAYMENT-SIGNATURE, payment-signature, X-Payment-Signature, x-payment-signature, Payment, payment, Mcp-Session-Id, mcp-session-id, MCP-Protocol-Version, mcp-protocol-version, Idempotency-Key, X-Request-Id');
header('Access-Control-Expose-Headers: PAYMENT-REQUIRED, PAYMENT-RESPONSE, X-PAYMENT-RESPONSE, X-Payment-Status, X-Payment-Amount, X-Payment-Network, Mcp-Session-Id, X-Request-Id');
grid402_emit_request_id_header();

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
        $requestedVersion = is_array($params) && isset($params['protocolVersion']) ? trim((string) $params['protocolVersion']) : '';
        $negotiatedVersion = MCP_PROTOCOL_VERSION;
        if ($requestedVersion !== '') {
            $negotiatedVersion = in_array($requestedVersion, MCP_SUPPORTED_PROTOCOL_VERSIONS, true)
                ? $requestedVersion
                : '2025-03-26';
        }
        mcp_result($responseId, [
            'protocolVersion' => $negotiatedVersion,
            'capabilities' => ['tools' => (object) []],
            'serverInfo' => ['name' => MCP_SERVER_NAME, 'version' => MCP_SERVER_VERSION],
            'instructions' => 'Grid402 hosted MCP (30 tools). Free tools: grid402_summary, grid402_verify, grid402_pass_balance. First paid data tool call per IP/day gets 1 free auto-trial (max 2 items on grid402_batch); unlock 50 FREE credits (no wallet needed) with promo code MCPTRY50 at https://grid402.online/pass?promo=MCPTRY50, or pay via x402 v2 (Base/Polygon/Arbitrum/Solana) / Session Pass / RapidAPI USD.',
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

function mcp_content_text(array $payload, array $meta = [], bool $isError = false): array {
    $res = [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]],
    ];
    if ($isError) {
        $res['isError'] = true;
    }
    if (!empty($meta)) {
        $res['_meta'] = $meta;
    }
    return $res;
}

function mcp_tools_definitions(): array {
    $payProp = ['type' => 'string', 'description' => 'Optional txHash on Base/Polygon/Arbitrum/Solana, Session Pass token (pass_g402_...), x402 EIP-3009 base64 payload, or demo for Sandbox (3 req/day).'];
    $payPropPass = ['type' => 'string', 'description' => 'Required real USDC payment proof: txHash on Base/Polygon/Arbitrum/Solana or x402 EIP-3009 base64 payload (Starter 2.00 USDC = 400 credits). Sandbox/demo and existing Session Pass tokens are not accepted.'];
    $roAnn = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];
    $localRoAnn = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];
    $writeAnn = ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];

    return [
        ['name' => 'grid402_summary', 'description' => 'Consolidated health and availability summary for all 31 Grid402 services (free).', 'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []], 'annotations' => $localRoAnn],
        ['name' => 'grid402_forex', 'description' => 'Official BCB PTAX fiat exchange rates USD, EUR, GBP to BRL (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_crypto', 'description' => 'Real-time spot prices for BTC, ETH, SOL, USDC, BASE in USD and BRL (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_dns', 'description' => 'Authoritative DNS A, AAAA, MX, TXT, NS, CNAME record lookup (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string', 'description' => 'Domain name to resolve (e.g. github.com).'], 'payment_tx_hash' => $payProp], 'required' => ['domain']], 'annotations' => $roAnn],
        ['name' => 'grid402_ssl', 'description' => 'Inspect port 443 TLS/SSL certificate validity, issuer, SANs and expiration (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['host' => ['type' => 'string', 'description' => 'Target hostname to inspect on port 443 (e.g. base.org).'], 'payment_tx_hash' => $payProp], 'required' => ['host']], 'annotations' => $roAnn],
        ['name' => 'grid402_asn', 'description' => 'BGP routing intelligence: ASN, IP prefix, organization and country (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['ip' => ['type' => 'string', 'description' => 'Optional IPv4/IPv6 address (defaults to 1.1.1.1).'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_domain_audit', 'description' => 'Consolidated Domain Audit bundle (DNS + TLS/SSL + BGP/ASN) with risk score (0.0150 USDC / 3 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string', 'description' => 'Domain name to audit (e.g. base.org).'], 'payment_tx_hash' => $payProp], 'required' => ['domain']], 'annotations' => $roAnn],
        ['name' => 'grid402_kyb_audit', 'description' => 'Consolidated Corporate KYB Dossier (CNPJ + OFAC/UN Sanctions + GLEIF LEI) (0.0250 USDC / 5 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number (e.g. 00000000000191).'], 'name' => ['type' => 'string', 'description' => 'Optional legal entity name for OFAC screening.'], 'lei' => ['type' => 'string', 'description' => 'Optional 20-char ISO 17442 LEI code.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_get_dossier_360', 'description' => 'Premium KYB 360 Dossier with deterministic AI Verdict (APPROVED, FLAGGED, REJECTED), score 0-100, Receita Federal CNPJ, Simples/MEI and CGU CEIS/CNEP sanctions (0.0500 USDC / 10 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number (with or without mask, e.g. 00000000000191).'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_market_snapshot', 'description' => 'Consolidated Financial and Macro Snapshot (PTAX Forex + Crypto Spot + Selic/CDI/IPCA + ANBIMA) (0.0150 USDC / 3 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_cep', 'description' => 'Brazilian CEP postal lookup: street, neighborhood, city, state and IBGE code (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string', 'description' => '8-digit Brazilian CEP postal code (e.g. 01310100).'], 'payment_tx_hash' => $payProp], 'required' => ['code']], 'annotations' => $roAnn],
        ['name' => 'grid402_cnpj', 'description' => 'Brazilian Receita Federal CNPJ lookup: registration status, legal name, CNAE and address (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number (e.g. 00000000000191).'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_indicators', 'description' => 'Official BCB/IBGE macro indicators: Selic target/daily, CDI and IPCA 12m (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_holidays', 'description' => 'ANBIMA Brazilian banking holidays calendar for a given year (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['year' => ['type' => 'integer', 'description' => '4-digit target calendar year (e.g. 2026).'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $localRoAnn],
        ['name' => 'grid402_workdays', 'description' => 'ANBIMA banking business-day count and D+N projection between dates (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['start_date' => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD format.'], 'end_date' => ['type' => 'string', 'description' => 'Optional end date in YYYY-MM-DD format.'], 'days' => ['type' => 'integer', 'description' => 'Optional number of business days to add to start_date.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $localRoAnn],
        ['name' => 'grid402_sanctions', 'description' => 'Brazilian CGU CEIS/CNEP administrative sanctions lookup by CNPJ (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number to check against CEIS/CNEP.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_simples', 'description' => 'Brazilian Simples Nacional and SIMEI (MEI) tax regime status by CNPJ (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_compliance', 'description' => 'Compliance 360 risk score (Receita Federal + CGU + corporate history), 0-100 (0.0300 USDC / 6 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string', 'description' => '14-digit Brazilian CNPJ number for 360 compliance scoring.'], 'payment_tx_hash' => $payProp], 'required' => ['cnpj']], 'annotations' => $roAnn],
        ['name' => 'grid402_lei', 'description' => 'Global Corporate Identity (ISO 17442) via GLEIF across 200+ jurisdictions (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['lei' => ['type' => 'string', 'description' => '20-character ISO 17442 LEI code (e.g. 5493006MHB84DD0ZWV18).'], 'payment_tx_hash' => $payProp], 'required' => ['lei']], 'annotations' => $roAnn],
        ['name' => 'grid402_ofac', 'description' => 'Global Sanctions (OFAC SDN / UN) and Base Mainnet EVM wallet AML inspection (0.0200 USDC / 4 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['target' => ['type' => 'string', 'description' => 'EVM address (0x...) or entity name to screen against OFAC/UN lists.'], 'payment_tx_hash' => $payProp], 'required' => ['target']], 'annotations' => $roAnn],
        ['name' => 'grid402_notarize', 'description' => 'M2M Cryptographic Timestamp and SHA-256 Proof-of-Existence signed with ECDSA secp256k1 (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['content' => ['type' => 'string', 'description' => 'Text or JSON payload to hash (SHA-256) and timestamp cryptographically.'], 'payment_tx_hash' => $payProp], 'required' => ['content']], 'annotations' => $localRoAnn],
        ['name' => 'grid402_global_markets', 'description' => 'World Exchange Settlement Calendar (US, EU, UK, BR) and 30+ ECB FX Reference Rates (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['market' => ['type' => 'string', 'description' => 'Market code: US, EU, UK or BR.'], 'base' => ['type' => 'string', 'description' => 'Base currency for ECB FX reference rates (e.g. USD, EUR, BRL).'], 'date' => ['type' => 'string', 'description' => 'Optional target date in YYYY-MM-DD format.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_extract', 'description' => 'SSRF-hardened Web URL to LLM-ready clean Markdown extractor with metadata and absolute links (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'Public HTTP or HTTPS web page URL to fetch and convert to clean Markdown.'], 'max_chars' => ['type' => 'integer', 'description' => 'Optional maximum output character length (500 to 25000, default 12000).'], 'payment_tx_hash' => $payProp], 'required' => ['url']], 'annotations' => $roAnn],
        ['name' => 'grid402_email_verify', 'description' => 'Zero-PII B2B email deliverability audit: RFC syntax, DNS MX, SPF, DMARC, disposable domain and role-based score (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string', 'description' => 'Corporate or business email address to audit without persisting raw PII.'], 'payment_tx_hash' => $payProp], 'required' => ['email']], 'annotations' => $roAnn],
        ['name' => 'grid402_token_safety', 'description' => 'Multi-Chain token security & DEX liquidity audit across Base, Ethereum, Polygon, Arbitrum and Solana via public GoPlus Security + DexScreener APIs returning score (0-100), verdict and signed proof (0.0150 USDC / 3 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['address' => ['type' => 'string', 'description' => 'Token contract/mint address (EVM 0x... 40 hex chars or Solana Base58).'], 'chain' => ['type' => 'string', 'description' => 'Optional chain: base, ethereum, polygon, arbitrum or solana (auto-detected for Solana Base58).'], 'payment_tx_hash' => $payProp], 'required' => ['address']], 'annotations' => $roAnn],
        ['name' => 'grid402_prediction_markets', 'description' => 'Normalized public prediction market odds (0-100% probability), 24h volume and liquidity from Polymarket Gamma + Kalshi APIs with graceful degradation (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string', 'description' => 'Optional keyword search query (e.g. bitcoin, fed).'], 'source' => ['type' => 'string', 'description' => 'Filter: all (default), polymarket or kalshi.'], 'status' => ['type' => 'string', 'description' => 'Filter: active (default) or closed.'], 'limit' => ['type' => 'integer', 'description' => 'Max markets (1-20, default 10).'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_sec_company', 'description' => 'Query US SEC EDGAR company profile (CIK, SIC, exchanges), filtered 10-K/10-Q/8-K filings and optional bounded XBRL financial facts (0.0100 USDC / 2 credits).', 'inputSchema' => ['type' => 'object', 'properties' => ['ticker' => ['type' => 'string', 'description' => 'US stock ticker symbol (e.g. AAPL).'], 'cik' => ['type' => 'string', 'description' => 'SEC 1-10 digit CIK identifier.'], 'forms' => ['type' => 'string', 'description' => 'Optional CSV SEC form filter (e.g. 10-K,10-Q,8-K).'], 'limit' => ['type' => 'integer', 'description' => 'Max filings (1-25, default 10).'], 'include_facts' => ['type' => 'boolean', 'description' => 'Optional US-GAAP XBRL metrics (8 MB cap).'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_vuln_audit', 'description' => 'Deterministic package (npm, PyPI, Go, Maven, Packagist, crates.io) or CVE/GHSA vulnerability audit crossing OSV.dev and CISA KEV (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['ecosystem' => ['type' => 'string', 'description' => 'Package ecosystem for package mode.'], 'package' => ['type' => 'string', 'description' => 'Package name for package mode.'], 'version' => ['type' => 'string', 'description' => 'Optional package version.'], 'id' => ['type' => 'string', 'description' => 'Direct CVE or GHSA identifier.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_chain_telemetry', 'description' => 'Real-time blockchain RPC telemetry (block/slot, gas/priority fees, latency) and DeFiLlama TVL across Base, Ethereum, Polygon, Arbitrum and Solana (0.0050 USDC / 1 credit).', 'inputSchema' => ['type' => 'object', 'properties' => ['chain' => ['type' => 'string', 'description' => 'Network: base (default), ethereum, polygon, arbitrum or solana.'], 'payment_tx_hash' => $payProp], 'required' => []], 'annotations' => $roAnn],
        ['name' => 'grid402_batch', 'description' => 'Execute 1 to 5 paid Grid402 data calls in one POST request (max 2 items in sandbox), dynamically summed with automatic Session Pass partial refund on sub-item failure.', 'inputSchema' => ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'description' => '1 to 5 sub-requests [{"service":"dns","params":{"domain":"base.org"}}].', 'items' => ['type' => 'object', 'properties' => ['service' => ['type' => 'string'], 'params' => ['type' => 'object']], 'required' => ['service']]], 'payment_tx_hash' => $payProp], 'required' => ['items']], 'annotations' => $writeAnn],
        ['name' => 'grid402_pass', 'description' => 'x402 Prepaid Session Pass issuance (Starter 2 USDC = 400 credits/180d, Pro 5 USDC = 1,200 credits/180d, Fleet 20 USDC = 6,000 credits/365d; requires real USDC payment, demo/pass-for-pass not allowed) or status check.', 'inputSchema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'description' => 'Optional existing pass_g402_... token to check status without issuing a new pass.'], 'payment_tx_hash' => $payPropPass], 'required' => []], 'annotations' => $writeAnn],
        ['name' => 'grid402_pass_balance', 'description' => 'Free Session Pass balance and Spend Controls telemetry (free).', 'inputSchema' => ['type' => 'object', 'properties' => ['token' => ['type' => 'string', 'description' => 'Session Pass token (pass_g402_...) to inspect.']], 'required' => ['token']], 'annotations' => $localRoAnn],
        ['name' => 'grid402_verify', 'description' => 'Stateless verification of an _oracle_proof ECDSA secp256k1 signature (free).', 'inputSchema' => ['type' => 'object', 'properties' => ['payload' => ['type' => 'object', 'description' => 'Full JSON response object containing the _oracle_proof block to verify.']], 'required' => ['payload']], 'annotations' => $localRoAnn],
    ];
}

function mcp_canonical_domain(): string {
    return defined('GRID402_CANONICAL_DOMAIN') ? rtrim((string) GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
}

function mcp_accepts_unpaid(string $service, ?int $requiredRawUnits = null, ?string $reason = null): array {
    $price = match (true) {
        $service === 'dossier' => '0.0500',
        $service === 'kyb-audit' => '0.0250',
        $service === 'ofac' => '0.0200',
        in_array($service, ['domain-audit', 'market-snapshot', 'token-safety'], true) => '0.0150',
        in_array($service, ['cnpj', 'lei', 'sanctions', 'simples', 'prediction-markets', 'sec-company'], true) => '0.0100',
        $service === 'compliance' => '0.0300',
        $service === 'pass' => '2.0000',
        $service === 'batch' && $requiredRawUnits !== null && $requiredRawUnits > 0 => number_format($requiredRawUnits / 1e6, 4, '.', ''),
        default => '0.0050',
    };
    $rawUnits = (int) ($requiredRawUnits ?? (int) round(((float) $price) * 1e6));
    $domain = mcp_canonical_domain();
    $resourceUrl = $domain . '/api/v1/' . $service;
    $resource = [
        'url' => $resourceUrl,
        'description' => 'Grid402 x402 API service: /api/v1/' . $service . ' (' . $price . ' USDC)',
        'mimeType' => 'application/json',
    ];
    $payload = [
        'error' => 'PaymentRequired',
        'payment_required' => true,
        'service' => $service,
        'price_usdc' => $price,
        'price_raw_units' => $rawUnits,
        'x402Version' => 2,
        'resource' => $resource,
        'accepts' => grid402_build_accepts($rawUnits),
        'facilitator' => 'https://facilitator.payai.network',
        'payTo' => GRID402_ORACLE_WALLET,
        'network' => 'base-mainnet',
        'chain_id' => 8453,
        'token' => 'USDC',
        'token_contract' => GRID402_USDC_CONTRACT,
        'fiat_and_card_checkout' => [
            'free_promo' => [
                'code' => 'MCPTRY50',
                'credits' => 50,
                'redeem_url' => $domain . '/pass?promo=MCPTRY50',
                'description' => 'Claim 50 FREE credits (no wallet or signup needed) and send X-Payment: pass_g402_<token>.',
            ],
            'pix_brl' => $domain . '/pass?tier=starter&method=pix (Micro R$ 4.90 = 50 cr | Starter R$ 12 = 400 cr, <15ms)',
            'session_pass_url' => $domain . '/pass',
            'rapidapi_credit_card_usd' => [
                'all_25_apis' => 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
                'web_markdown_email_network' => 'https://rapidapi.com/oliveiratc/api/grid402-web-to-markdown-email-verify-network-utilities',
                'brazil_kyb_cnpj_anbima' => 'https://rapidapi.com/oliveiratc/api/brazil-kyb-cnpj-cgu-sanctions-anbima-workdays-api',
            ],
        ],
        'hint' => 'Claim 50 FREE credits with code MCPTRY50 at ' . $domain . '/pass?promo=MCPTRY50 (no wallet needed), send signed x402 v2 payment in params._meta["x402/payment"] or header PAYMENT-SIGNATURE, pass a prepaid Session Pass token (' . $domain . '/pass), or subscribe with Credit Card (USD) on RapidAPI.',
    ];
    if ($reason !== null && $reason !== '') {
        $payload['reason'] = $reason;
    }
    return $payload;
}

function mcp_payment_required_result($id, string $service, int $rawCost, ?string $reason = null): never {
    $unpaid = mcp_accepts_unpaid($service, $rawCost, $reason);
    $x402Error = [
        'x402Version' => 2,
        'error' => ($reason !== null && $reason !== '') ? $reason : 'Payment required',
        'resource' => $unpaid['resource'],
        'accepts' => $unpaid['accepts'],
        'service' => $service,
        'price_usdc' => $unpaid['price_usdc'],
        'price_raw_units' => $unpaid['price_raw_units'],
        'payTo' => $unpaid['payTo'],
        'fiat_and_card_checkout' => $unpaid['fiat_and_card_checkout'],
    ];

    if (!headers_sent()) {
        $paymentReqHeader = base64_encode((string) json_encode([
            'x402Version' => 2,
            'error' => $x402Error['error'],
            'resource' => $unpaid['resource'],
            'accepts' => $unpaid['accepts'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        header('PAYMENT-REQUIRED: ' . $paymentReqHeader);
    }

    $credits = max(1, intdiv($rawCost, 5000));
    $domain = mcp_canonical_domain();
    $reasonLine = ($reason !== null && $reason !== '') ? "Reason: {$reason}\n\n" : '';
    $sandboxLine = $service === 'pass'
        ? "Note: Sandbox/demo mode and existing Session Pass tokens are NOT accepted to issue a new pass. Use real USDC or Pix/Card checkout.\n"
        : "4) Free Sandbox Trial (3 req/day per IP): pass payment_tx_hash=\"demo\" for testing.\n";

    $solanaOn = function_exists('grid402_is_solana_x402_enabled') && grid402_is_solana_x402_enabled();
    $chainsText = $solanaOn ? 'Base, Polygon, Arbitrum, Solana' : 'Base, Polygon, Arbitrum';
    $humanMessage = "Payment Required for Grid402 tool '{$service}' ({$unpaid['price_usdc']} USDC / {$credits} credit(s)).\n"
        . $reasonLine
        . "How to unlock immediately:\n"
        . "1) FREE 50 Credits Promo (no wallet or signup required): redeem code MCPTRY50 at {$domain}/pass?promo=MCPTRY50 and pass your token via header `X-Payment: pass_g402_...` or argument `payment_tx_hash`.\n"
        . "2) Prepaid Session Pass via Pix/Card BRL (from R$ 4.90 = 50 cr | Starter R$ 12 = 400 cr, <15ms) or EVM USDC: {$domain}/pass (Pix plans: {$domain}/pass?tier=starter&method=pix)\n"
        . "3) RapidAPI Hub (International Credit Card in USD): https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities\n"
        . "4) Autonomous x402 v2 Gasless / Multi-Chain USDC ({$chainsText}): send signed x402 payload in `params._meta[\"x402/payment\"]` or header `PAYMENT-SIGNATURE`, or pay {$unpaid['price_usdc']} USDC ({$rawCost} raw units) to {$unpaid['payTo']} and pass the txHash.\n"
        . $sandboxLine
        . "\nMachine-readable x402 v2 challenge:\n"
        . json_encode($unpaid, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    mcp_result($id, [
        'isError' => true,
        'content' => [[
            'type' => 'text',
            'text' => $humanMessage,
        ]],
        '_meta' => [
            'x402/error' => $x402Error,
        ],
    ]);
    exit;
}

/**
 * Auto-trial diario gratuito do /mcp hospedado (1 chamada/dia por IP, Zero-PII sob flock).
 * Permite descoberta imediata sem header manual em ferramentas de dados (nunca em grid402_pass).
 */
function mcp_consume_auto_trial(string $clientIp): array {
    $dir = defined('GRID402_PRIVATE_DIR')
        ? rtrim((string) GRID402_PRIVATE_DIR, '/\\')
        : (function_exists('grid402_get_storage_dir') ? grid402_get_storage_dir() : (__DIR__ . '/private_data'));
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/mcp_trial_rates.json';
    $secret = defined('GRID402_CRON_SECRET') ? (string) GRID402_CRON_SECRET : 'grid402_mcp_trial';
    $ipKey = hash_hmac('sha256', trim($clientIp !== '' ? $clientIp : '127.0.0.1'), 'mcp_trial:' . $secret);
    $now = time();
    $window = MCP_AUTO_TRIAL_WINDOW_SECONDS;
    $limit = MCP_AUTO_TRIAL_DAILY_LIMIT;

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return ['allowed' => false, 'limit' => $limit, 'remaining' => 0, 'retry_after' => $window];
    }

    $allowed = false;
    $remaining = 0;
    $retryAfter = $window;
    if (flock($fp, LOCK_EX)) {
        $store = function_exists('grid402_read_locked_json') ? grid402_read_locked_json($fp, []) : [];
        if (!is_array($store)) {
            $store = [];
        }
        // Limpa entradas expiradas para evitar crescimento ilimitado
        foreach ($store as $k => $entry) {
            if (!is_array($entry) || (int) ($entry['window_start'] ?? 0) + $window <= $now) {
                unset($store[$k]);
            }
        }
        $cur = isset($store[$ipKey]) && is_array($store[$ipKey])
            ? $store[$ipKey]
            : ['window_start' => $now, 'count' => 0];
        $elapsed = $now - (int) ($cur['window_start'] ?? $now);
        if ($elapsed >= $window) {
            $cur = ['window_start' => $now, 'count' => 0];
            $elapsed = 0;
        }
        if ((int) $cur['count'] < $limit) {
            $cur['count'] = (int) $cur['count'] + 1;
            $store[$ipKey] = $cur;
            $allowed = true;
            $remaining = max(0, $limit - (int) $cur['count']);
            $retryAfter = max(1, $window - $elapsed);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($store, JSON_UNESCAPED_SLASHES));
            fflush($fp);
        } else {
            $retryAfter = max(1, $window - $elapsed);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return [
        'allowed' => $allowed,
        'limit' => $limit,
        'remaining' => $remaining,
        'retry_after' => $retryAfter,
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
        'grid402_kyb_audit' => ['kyb-audit', 'cnpj', 25000],
        'grid402_get_dossier_360' => ['dossier', 'cnpj', 50000],
        'grid402_market_snapshot' => ['market-snapshot', null, 15000],
        'grid402_cep' => ['cep', 'code', 5000],
        'grid402_cnpj' => ['cnpj', 'cnpj', 10000],
        'grid402_indicators' => ['indicators', null, 5000],
        'grid402_holidays' => ['holidays', 'year', 5000],
        'grid402_workdays' => ['workdays', null, 5000],
        'grid402_sanctions' => ['sanctions', 'cnpj', 10000],
        'grid402_simples' => ['simples', 'cnpj', 10000],
        'grid402_compliance' => ['compliance', 'cnpj', 30000],
        'grid402_lei' => ['lei', 'lei', 10000],
        'grid402_ofac' => ['ofac', 'target', 20000],
        'grid402_notarize' => ['notarize', 'content', 5000],
        'grid402_global_markets' => ['global-markets', null, 5000],
        'grid402_extract' => ['extract', 'url', 5000],
        'grid402_email_verify' => ['email-verify', 'email', 5000],
        'grid402_token_safety' => ['token-safety', 'address', 15000],
        'grid402_prediction_markets' => ['prediction-markets', null, 10000],
        'grid402_sec_company' => ['sec-company', null, 10000],
        'grid402_vuln_audit' => ['vuln-audit', null, 5000],
        'grid402_chain_telemetry' => ['chain-telemetry', null, 5000],
        'grid402_batch' => ['batch', 'items', 5000],
        'grid402_pass' => ['pass', null, 2000000],
    ];
    if (!isset($map[$tool])) {
        mcp_error($responseId, -32602, 'UnknownTool', ['tool' => $tool]);
    }
    [$service, $requiredArg, $rawCost] = $map[$tool];
    if ($requiredArg !== null && empty($arguments[$requiredArg]) && $requiredArg !== 'year' && $requiredArg !== 'ip') {
        mcp_error($responseId, -32602, 'InvalidArguments', ['message' => "Parametro '{$requiredArg}' e obrigatorio para {$tool}."]);
    }

    $batchPreCheck = null;
    if ($service === 'batch') {
        $batchEncoded = json_encode(['items' => $arguments['items'] ?? []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $batchPreCheck = grid402_batch_parse_and_validate(is_string($batchEncoded) ? $batchEncoded : '', false);
        if (empty($batchPreCheck['ok'])) {
            mcp_error($responseId, -32602, 'InvalidArguments', [
                'error' => $batchPreCheck['error'] ?? 'InvalidBatchPayload',
                'message' => $batchPreCheck['message'] ?? 'Lote invalido para grid402_batch.',
            ]);
        }
        $rawCost = (int) ($batchPreCheck['total_raw_units'] ?? 5000);
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
    if ($service === 'token-safety' && !empty($arguments['address'])) {
        $tsCheck = grid402_token_safety_validate_input((string) $arguments['address'], (string) ($arguments['chain'] ?? ''));
        if (!$tsCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $tsCheck['error'], 'message' => $tsCheck['message']]);
        }
    }
    if ($service === 'prediction-markets') {
        $pmCheck = grid402_prediction_markets_validate_input(
            (string) ($arguments['q'] ?? ''),
            (string) ($arguments['source'] ?? 'all'),
            (string) ($arguments['status'] ?? 'active'),
            isset($arguments['limit']) ? (int) $arguments['limit'] : 10
        );
        if (!$pmCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $pmCheck['error'], 'message' => $pmCheck['message']]);
        }
    }
    if ($service === 'sec-company') {
        $secCheck = grid402_sec_company_validate_input(
            (string) ($arguments['ticker'] ?? ''),
            (string) ($arguments['cik'] ?? ''),
            isset($arguments['limit']) ? (int) $arguments['limit'] : 10,
            (string) ($arguments['forms'] ?? ''),
            $arguments['include_facts'] ?? false
        );
        if (!$secCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $secCheck['error'], 'message' => $secCheck['message']]);
        }
    }
    if ($service === 'vuln-audit') {
        $vaCheck = grid402_vuln_audit_validate_input(
            (string) ($arguments['ecosystem'] ?? ''),
            (string) ($arguments['package'] ?? ''),
            (string) ($arguments['version'] ?? ''),
            (string) ($arguments['id'] ?? '')
        );
        if (!$vaCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $vaCheck['error'], 'message' => $vaCheck['message']]);
        }
    }
    if ($service === 'chain-telemetry') {
        $ctCheck = grid402_chain_telemetry_validate_input((string) ($arguments['chain'] ?? 'base'));
        if (!$ctCheck['ok']) {
            mcp_error($responseId, -32602, 'InvalidArguments', ['error' => $ctCheck['error'], 'message' => $ctCheck['message']]);
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
    $x402Payment = '';
    if (isset($meta['x402/payment'])) {
        if (is_array($meta['x402/payment'])) {
            $encodedMetaPay = json_encode($meta['x402/payment'], JSON_UNESCAPED_SLASHES);
            $x402Payment = is_string($encodedMetaPay) ? base64_encode($encodedMetaPay) : '';
        } elseif (is_string($meta['x402/payment'])) {
            $x402Payment = trim($meta['x402/payment']);
        }
    }
    $payHeader = trim((string) (
        $_SERVER['HTTP_PAYMENT_SIGNATURE']
        ?? $_SERVER['HTTP_X_PAYMENT_SIGNATURE']
        ?? $_SERVER['HTTP_PAYMENT']
        ?? $mcpHdrMap['payment-signature']
        ?? $mcpHdrMap['x-payment-signature']
        ?? $mcpHdrMap['payment']
        ?? ''
    ));
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
    $priceUsdcFloat = (float) number_format($rawCost / 1e6, 4, '.', '');
    $canonicalResourceUrl = mcp_canonical_domain() . '/api/v1/' . $service;

    $usedAutoTrial = false;
    $validation = null;
    if ($effectiveProof === '') {
        if ($service === 'batch' && is_array($batchPreCheck) && ((int) ($batchPreCheck['item_count'] ?? 0)) > 2) {
            mcp_payment_required_result($responseId, $service, $rawCost, 'Free MCP auto-trial allows max 2 items per batch. Supply x402 payment or Session Pass.');
        }
        if ($service !== 'pass') {
            $trialRes = mcp_consume_auto_trial($clientIp);
            if (!empty($trialRes['allowed'])) {
                $usedAutoTrial = true;
                $validation = [
                    'valid' => true,
                    'sandbox' => true,
                    'source' => 'mcp_sandbox',
                    'mcp_auto_trial' => true,
                    'remaining' => (int) ($trialRes['remaining'] ?? 0),
                    'limit' => (int) ($trialRes['limit'] ?? MCP_AUTO_TRIAL_DAILY_LIMIT),
                    'code' => 200,
                ];
            }
        }
        if (!$usedAutoTrial) {
            grid402_record_telemetry($service, 402, false, null, $clientIp, $priceUsdcFloat, 'challenge_402');
            if (function_exists('grid402_log_request')) {
                grid402_log_request([
                    'service' => $service,
                    'status' => 402,
                    'payment_source' => 'none',
                    'price_usdc' => $priceUsdcFloat,
                    'error' => 'PaymentRequired',
                    'method' => 'POST',
                    'ip' => $clientIp,
                ]);
            }
            if (function_exists('grid402_growth_event')) {
                grid402_growth_event('challenge_402', [
                    'service' => $service,
                    'ip' => $clientIp,
                    'type' => 'mcp',
                ]);
            }
            $trialExhaustedReason = ($service !== 'pass')
                ? 'Daily free MCP auto-trial (1/day per IP) already used. Claim 50 FREE credits (no wallet needed) with promo code MCPTRY50 at ' . mcp_canonical_domain() . '/pass?promo=MCPTRY50.'
                : null;
            mcp_payment_required_result($responseId, $service, $rawCost, $trialExhaustedReason);
        }
    }

    $proofIsPayload = !$usedAutoTrial && (grid402_classify_payment_proof($effectiveProof) === 'x402_payload');
    $mcpFailPayment = static function (array $val) use ($responseId, $service, $rawCost, $priceUsdcFloat, $proofIsPayload, $effectiveProof, $clientIp): never {
        $failCode = (int)($val['code'] ?? 402);
        $failCategory = (string)($val['category'] ?? 'payment_rejected');
        $failSource = (string)($val['source'] ?? ($proofIsPayload ? 'facilitator' : 'unknown'));
        $failReason = (string)($val['reason'] ?? 'payment_rejected');
        $failType = ($failCode === 429 || $failCategory === 'sandbox_rate_limited') ? 'rate_limited_429' : null;
        grid402_record_telemetry(
            $service,
            $failCode,
            false,
            $proofIsPayload ? null : $effectiveProof,
            $clientIp,
            $priceUsdcFloat,
            $failType,
            ['failure_category' => $failCategory]
        );
        if (function_exists('grid402_log_request')) {
            grid402_log_request([
                'service' => $service,
                'status' => $failCode,
                'payment_source' => $failSource !== '' ? $failSource : 'unknown',
                'price_usdc' => $priceUsdcFloat,
                'error' => $failCategory . ': ' . $failReason,
                'method' => 'POST',
                'ip' => $clientIp,
            ]);
        }
        if ($failType !== 'rate_limited_429' && function_exists('grid402_record_payment_failure')) {
            grid402_record_payment_failure([
                'service' => $service,
                'status' => $failCode,
                'failure_category' => $failCategory,
                'payment_source' => $failSource !== '' ? $failSource : 'unknown',
                'error' => $failCategory . ': ' . $failReason,
                'method' => 'POST',
                'ip' => $clientIp,
            ]);
        }
        if ($failCategory === 'sandbox_rate_limited' && function_exists('grid402_growth_event')) {
            grid402_growth_event('sandbox_rate_limited', [
                'service' => $service,
                'ip' => $clientIp,
                'type' => 'mcp',
            ]);
        }
        $reason = (string)($val['reason'] ?? 'Payment validation failed.');
        mcp_payment_required_result($responseId, $service, $rawCost, $reason);
    };

    if (!$usedAutoTrial) {
        // V-01: Bloqueia demo/sandbox e pagamento com outro Session Pass na emissão de grid402_pass
        if ($service === 'pass') {
            $proofLower = strtolower(trim($effectiveProof));
            if ($proofLower === 'demo' || $proofLower === 'sandbox') {
                $mcpFailPayment([
                    'valid' => false,
                    'code' => 403,
                    'source' => 'sandbox',
                    'category' => 'sandbox_pass_forbidden',
                    'reason' => 'Sandbox/demo mode cannot be used to issue a Session Pass. Pay with real USDC (2.00 USDC Starter) via x402 or purchase via Pix/Card at https://grid402.online/pass.',
                ]);
            }
            if (str_starts_with($proofLower, 'pass_g402_')) {
                $mcpFailPayment([
                    'valid' => false,
                    'code' => 403,
                    'source' => 'pass',
                    'category' => 'pass_for_pass_forbidden',
                    'reason' => 'An existing Session Pass cannot be used to issue a new Session Pass. Pay with real USDC via x402 or purchase via Pix/Card at https://grid402.online/pass.',
                ]);
            }
        }

        $mcpEndpointForValidate = $service;
        if ($service === 'batch' && str_starts_with(strtolower($effectiveProof), 'pass_g402_') && is_array($batchPreCheck)) {
            $mcpPassPre = grid402_get_session_pass_status($effectiveProof);
            if (!empty($mcpPassPre['valid']) && is_array($mcpPassPre['allowed_endpoints'] ?? null) && !empty($mcpPassPre['allowed_endpoints'])) {
                $allowedBatchList = $mcpPassPre['allowed_endpoints'];
                foreach (($batchPreCheck['unique_services'] ?? []) as $batchSubSvc) {
                    if (!in_array((string) $batchSubSvc, $allowedBatchList, true)) {
                        $mcpFailPayment([
                            'valid' => false,
                            'code' => 403,
                            'source' => 'pass',
                            'category' => 'endpoint_restricted',
                            'reason' => "Session Pass restricted: '{$batchSubSvc}' is not in allowed_endpoints.",
                        ]);
                    }
                }
            }
            $mcpEndpointForValidate = null;
        }
        if ($service === 'batch' && in_array(strtolower(trim($effectiveProof)), ['demo', 'sandbox'], true) && is_array($batchPreCheck) && ((int) ($batchPreCheck['item_count'] ?? 0)) > 2) {
            mcp_error($responseId, -32602, 'SandboxBatchLimitExceeded', ['message' => 'Modo sandbox/demo permite no maximo 2 itens por chamada em grid402_batch.']);
        }
        $validation = grid402_validate_payment(
            $effectiveProof,
            $rawCost,
            $clientIp,
            $mcpEndpointForValidate,
            $mcpNetHeader !== '' ? $mcpNetHeader : null,
            true,
            $canonicalResourceUrl
        );
        if (empty($validation['valid'])) {
            $mcpFailPayment($validation);
        }
    }

    // Defesa em profundidade V-01: nunca emitir pass em modo sandbox ou pago com outro pass
    if ($service === 'pass' && (!empty($validation['sandbox']) || ($validation['source'] ?? '') === 'pass')) {
        if (!empty($validation['session_pass']) && !empty($validation['credits_debited']) && function_exists('grid402_refund_session_pass')) {
            grid402_refund_session_pass($effectiveProof, (int) $validation['credits_debited'], $service, ['reason' => 'pass_for_pass_blocked', 'via' => 'mcp']);
        }
        $mcpFailPayment([
            'valid' => false,
            'code' => 403,
            'source' => (string)($validation['source'] ?? 'sandbox'),
            'category' => 'pass_issuance_forbidden',
            'reason' => 'Session Pass issuance requires real USDC payment or Pix/Card checkout at https://grid402.online/pass.',
        ]);
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
        'email-verify' => grid402_service_email_verify((string)($arguments['email'] ?? '')),
        'token-safety' => grid402_service_token_safety((string)($arguments['address'] ?? ''), (string)($arguments['chain'] ?? '')),
        'prediction-markets' => grid402_service_prediction_markets(
            (string)($arguments['q'] ?? ''),
            (string)($arguments['source'] ?? 'all'),
            (string)($arguments['status'] ?? 'active'),
            isset($arguments['limit']) ? (int)$arguments['limit'] : 10
        ),
        'sec-company' => grid402_service_sec_company(
            (string)($arguments['ticker'] ?? ''),
            (string)($arguments['cik'] ?? ''),
            isset($arguments['limit']) ? (int)$arguments['limit'] : 10,
            (string)($arguments['forms'] ?? ''),
            $arguments['include_facts'] ?? false
        ),
        'vuln-audit' => grid402_service_vuln_audit(
            (string)($arguments['ecosystem'] ?? ''),
            (string)($arguments['package'] ?? ''),
            (string)($arguments['version'] ?? ''),
            (string)($arguments['id'] ?? '')
        ),
        'chain-telemetry' => grid402_service_chain_telemetry((string)($arguments['chain'] ?? 'base')),
        'batch' => grid402_batch_execute_items(
            is_array($batchPreCheck) ? $batchPreCheck : ['ok' => true, 'items' => []],
            static function (string $subSvc, array $subParams, bool $subSandbox): array {
                return match ($subSvc) {
                    'forex' => grid402_service_forex(),
                    'crypto' => grid402_service_crypto(),
                    'dns' => grid402_service_dns((string)($subParams['domain'] ?? 'github.com')),
                    'ssl' => grid402_service_ssl((string)($subParams['host'] ?? 'base.org')),
                    'asn' => grid402_service_asn(isset($subParams['ip']) ? (string)$subParams['ip'] : null),
                    'cep' => grid402_service_cep((string)($subParams['code'] ?? $subParams['cep'] ?? '01310100')),
                    'cnpj' => grid402_service_cnpj((string)($subParams['cnpj'] ?? $subParams['code'] ?? '00000000000191')),
                    'indicators' => grid402_service_indicators(),
                    'holidays' => grid402_service_holidays(isset($subParams['year']) ? (int)$subParams['year'] : null),
                    'workdays' => grid402_service_workdays($subParams),
                    'sanctions' => grid402_service_sanctions((string)($subParams['cnpj'] ?? $subParams['code'] ?? '00000000000191')),
                    'simples' => grid402_service_simples((string)($subParams['cnpj'] ?? $subParams['code'] ?? '00000000000191')),
                    'compliance' => grid402_service_compliance((string)($subParams['cnpj'] ?? $subParams['code'] ?? '00000000000191')),
                    'domain-audit' => grid402_service_domain_audit((string)($subParams['domain'] ?? $subParams['host'] ?? 'base.org')),
                    'kyb-audit' => grid402_service_kyb_audit((string)($subParams['cnpj'] ?? ''), (string)($subParams['name'] ?? ''), (string)($subParams['lei'] ?? ''), (string)($subParams['address'] ?? $subParams['target'] ?? ''), $subSandbox),
                    'dossier' => grid402_service_dossier((string)($subParams['cnpj'] ?? $subParams['code'] ?? '00000000000191')),
                    'market-snapshot' => grid402_service_market_snapshot(),
                    'lei' => grid402_service_lei((string)($subParams['lei'] ?? ''), (string)($subParams['q'] ?? '')),
                    'ofac' => grid402_service_ofac((string)($subParams['address'] ?? $subParams['target'] ?? ''), (string)($subParams['name'] ?? ''), $subSandbox),
                    'notarize' => grid402_service_notarize((string)($subParams['data'] ?? $subParams['content'] ?? ''), (string)($subParams['hash'] ?? '')),
                    'global-markets' => grid402_service_global_markets((string)($subParams['market'] ?? 'US'), (string)($subParams['date'] ?? ''), (string)($subParams['base'] ?? 'USD')),
                    'extract' => grid402_service_extract((string)($subParams['url'] ?? 'https://example.com'), isset($subParams['max_chars']) ? (int)$subParams['max_chars'] : 12000),
                    'email-verify' => grid402_service_email_verify((string)($subParams['email'] ?? 'contato@grid402.online')),
                    'token-safety' => grid402_service_token_safety((string)($subParams['address'] ?? $subParams['token'] ?? ''), (string)($subParams['chain'] ?? '')),
                    'prediction-markets' => grid402_service_prediction_markets((string)($subParams['q'] ?? ''), (string)($subParams['source'] ?? 'all'), (string)($subParams['status'] ?? 'active'), isset($subParams['limit']) ? (int)$subParams['limit'] : 10),
                    'sec-company' => grid402_service_sec_company((string)($subParams['ticker'] ?? ''), (string)($subParams['cik'] ?? ''), isset($subParams['limit']) ? (int)$subParams['limit'] : 10, (string)($subParams['forms'] ?? ''), $subParams['include_facts'] ?? false),
                    'vuln-audit' => grid402_service_vuln_audit((string)($subParams['ecosystem'] ?? ''), (string)($subParams['package'] ?? ''), (string)($subParams['version'] ?? ''), (string)($subParams['id'] ?? '')),
                    'chain-telemetry' => grid402_service_chain_telemetry((string)($subParams['chain'] ?? 'base')),
                    default => ['code' => 400, 'error' => 'UnsupportedBatchService', 'message' => "Servico '{$subSvc}' nao suportado em lote."],
                };
            },
            $isSandbox
        ),
        'pass' => (static function () use ($validation, $proofIsPayload, $effectiveProof, $rawCost): array {
            $actualPaid = (int) ($validation['raw_amount'] ?? $rawCost);
            $passTxRef = (string) ($validation['lock_key'] ?? $validation['settlement_tx'] ?? ($proofIsPayload ? ('mcp_' . bin2hex(random_bytes(8))) : $effectiveProof));
            return grid402_issue_session_pass(
                $passTxRef,
                $actualPaid > 0 ? $actualPaid : (int)$rawCost,
                false
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
        } elseif (!empty($validation['session_pass']) && !empty($validation['credits_debited']) && function_exists('grid402_refund_session_pass')) {
            grid402_refund_session_pass($effectiveProof, (int) $validation['credits_debited'], $service, ['http_status' => $execCode, 'via' => 'mcp']);
        } elseif (!empty($validation['lock_key']) && function_exists('grid402_release_transaction_lock')) {
            $srcErr = (string) ($validation['source'] ?? '');
            if ($srcErr === 'tx_hash' || $srcErr === 'solana_spl' || str_starts_with($srcErr, 'evm_') || str_starts_with($srcErr, 'multichain_')) {
                grid402_release_transaction_lock((string) $validation['lock_key']);
            }
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
        if (function_exists('grid402_log_request')) {
            grid402_log_request([
                'service' => $service,
                'status' => $execCode,
                'payment_source' => $paymentSourceErr !== '' ? $paymentSourceErr : 'unknown',
                'price_usdc' => 0.0,
                'error' => (string) (is_array($result) ? ($result['error'] ?? $result['message'] ?? ('HTTP_' . $execCode)) : ('HTTP_' . $execCode)),
                'method' => 'POST',
                'ip' => $clientIp,
            ]);
        }
        mcp_result($responseId, mcp_content_text(is_array($result) ? $result : ['result' => $result], [], true));
    }

    if (!empty($validation['needs_settle'])) {
        $validation = grid402_settle_exact_payment($validation);
        if (empty($validation['valid'])) {
            $mcpFailPayment($validation);
        }
    }

    if (
        $service === 'batch'
        && !empty($validation['session_pass'])
        && is_array($result['batch_summary'] ?? null)
        && ((int) ($result['batch_summary']['credits_refunded'] ?? 0)) > 0
        && function_exists('grid402_refund_session_pass')
    ) {
        $mcpRefundCredits = (int) $result['batch_summary']['credits_refunded'];
        if (grid402_refund_session_pass($effectiveProof, $mcpRefundCredits, 'batch_partial_refund', ['via' => 'mcp', 'succeeded' => (int) ($result['batch_summary']['succeeded'] ?? 0), 'failed' => (int) ($result['batch_summary']['failed'] ?? 0)])) {
            $validation['credits_debited'] = (int) ($result['batch_summary']['credits_charged'] ?? max(0, ((int) ($validation['credits_debited'] ?? 0)) - $mcpRefundCredits));
            $postMcpStatus = grid402_get_session_pass_status($effectiveProof);
            if (!empty($postMcpStatus['ok']) && isset($postMcpStatus['credits_remaining'])) {
                $validation['credits_remaining'] = (int) $postMcpStatus['credits_remaining'];
            }
        }
    }

    $isSandbox = !empty($validation['sandbox']);
    $isVerifyOnly = (($validation['source'] ?? '') === 'facilitator_verify_only');
    $isRealPayment = !$isSandbox && !$isVerifyOnly;
    $paymentSourceVal = (string)($validation['source'] ?? '');
    $telemetryPaymentSource = $isRealPayment ? 'mcp' : ($paymentSourceVal !== '' ? $paymentSourceVal : null);
    $settlementTx = $validation['settlement_tx'] ?? ($proofIsPayload ? null : $effectiveProof);

    // Evita dupla contagem de receita quando pagamento é consumo de crédito de Session Pass
    $actualRawPaid = (int)($validation['raw_amount'] ?? $rawCost);
    $revenueUsdc = ($isSandbox || $isVerifyOnly || $paymentSourceVal === 'pass')
        ? 0.0
        : (float) number_format(($service === 'pass' && $actualRawPaid > 0 ? $actualRawPaid : $rawCost) / 1e6, 4, '.', '');
    $mcpCountry = function_exists('grid402_resolve_client_country')
        ? grid402_resolve_client_country($clientIp, $isRealPayment)
        : null;

    grid402_record_telemetry(
        $service,
        200,
        $isRealPayment,
        $settlementTx,
        $clientIp,
        $revenueUsdc,
        $isSandbox ? 'sandbox' : ($isVerifyOnly ? 'verify_only' : null),
        [
            'payment_source' => $telemetryPaymentSource,
            'channel' => 'mcp',
            'settlement_source' => $paymentSourceVal !== '' ? $paymentSourceVal : null,
            'country_code' => $mcpCountry,
        ]
    );

    if (function_exists('grid402_log_request')) {
        grid402_log_request([
            'service' => $service,
            'status' => 200,
            'payment_source' => $isRealPayment ? 'mcp' : ($paymentSourceVal !== '' ? $paymentSourceVal : 'unknown'),
            'price_usdc' => $revenueUsdc,
            'country_code' => $mcpCountry,
            'method' => 'POST',
            'ip' => $clientIp,
        ]);
    }

    if ($isRealPayment && $paymentSourceVal !== 'pass' && function_exists('grid402_notify_telegram_purchase')) {
        $mcpNotifTx = (string) ($settlementTx ?? '');
        $rawMcpNet = (string) ($validation['network'] ?? 'base');
        $mcpNetSlug = function_exists('grid402_normalize_network_slug')
            ? (grid402_normalize_network_slug($rawMcpNet) ?? 'base')
            : strtolower($rawMcpNet);
        $isValidMcpTx = (preg_match('/^0x[a-f0-9]{64}$/i', $mcpNotifTx) === 1)
            || ($mcpNetSlug === 'solana' && function_exists('grid402_is_valid_solana_signature') && grid402_is_valid_solana_signature($mcpNotifTx));
        if ($isValidMcpTx) {
            grid402_notify_telegram_purchase($service, (float) $revenueUsdc, $mcpNotifTx, false, $mcpNetSlug, $mcpCountry);
        }
    }

    if (function_exists('grid402_growth_event')) {
        if ($isRealPayment) {
            if ($service === 'pass') {
                grid402_growth_event('pass_purchase', [
                    'service' => 'pass',
                    'payer' => $validation['payer'] ?? '',
                    'proof' => $settlementTx ?? '',
                    'amount_usdc' => $revenueUsdc,
                    'ip' => $clientIp,
                    'type' => 'mcp',
                ]);
            } else {
                grid402_growth_event('paid_request', [
                    'service' => $service,
                    'payer' => $validation['payer'] ?? '',
                    'proof' => $proofIsPayload ? ($settlementTx ?? '') : $effectiveProof,
                    'amount_usdc' => $revenueUsdc,
                    'ip' => $clientIp,
                    'type' => $paymentSourceVal === 'pass' ? 'pass' : 'x402',
                ]);
            }
        } else {
            grid402_growth_event($isSandbox ? 'sandbox_request' : 'verify_only', [
                'service' => $service,
                'proof' => $proofIsPayload ? '' : $effectiveProof,
                'ip' => $clientIp,
                'type' => $paymentSourceVal !== '' ? $paymentSourceVal : 'mcp',
            ]);
        }
    }

    $responseMeta = [];
    if ($paymentSourceVal === 'facilitator') {
        $receiptHeader = grid402_build_payment_response_header($validation);
        header('PAYMENT-RESPONSE: ' . $receiptHeader);
        header('X-PAYMENT-RESPONSE: ' . $receiptHeader);
        $responseMeta['x402/payment-response'] = [
            'success' => true,
            'transaction' => $validation['settlement_tx'] ?? null,
            'network' => $validation['network'] ?? GRID402_X402_NETWORK_V2,
            'payer' => $validation['payer'] ?? null,
        ];
    } elseif ($isRealPayment && !empty($settlementTx)) {
        $responseMeta['x402/payment-response'] = [
            'success' => true,
            'transaction' => $settlementTx,
            'network' => $validation['network'] ?? GRID402_X402_NETWORK_V2,
            'payer' => $validation['payer'] ?? null,
            'source' => $paymentSourceVal,
        ];
    }

    if (is_array($result) && (!isset($result['code']) || $result['code'] === 200)) {
        if (!empty($validation['mcp_auto_trial'])) {
            $result['sandbox'] = true;
            $result['_trial_notice'] = [
                'mode' => 'mcp_sandbox',
                'quota_limit_per_day' => MCP_AUTO_TRIAL_DAILY_LIMIT,
                'quota_remaining_today' => (int) ($validation['remaining'] ?? 0),
                'promo_code' => 'MCPTRY50',
                'promo_url' => mcp_canonical_domain() . '/pass?promo=MCPTRY50',
                'message' => 'Free daily MCP auto-trial used (1/day per IP). Unlock 50 FREE credits (no wallet needed) with code MCPTRY50 at ' . mcp_canonical_domain() . '/pass?promo=MCPTRY50, or continue via Session Pass / x402 v2 / RapidAPI USD.',
            ];
        }
        $result = grid402_attach_provenance_meta($result, $service);
        grid402_sign_data($result, $isSandbox);
    }

    mcp_result($responseId, mcp_content_text(is_array($result) ? $result : ['result' => $result], $responseMeta));
}
