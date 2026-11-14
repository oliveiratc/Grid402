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
require_once __DIR__ . '/services/lei.php';
require_once __DIR__ . '/services/ofac.php';
require_once __DIR__ . '/services/oracle_signer.php';

const MCP_PROTOCOL_VERSION = '2024-11-05';
const MCP_SERVER_NAME = 'grid402-mcp';
const MCP_SERVER_VERSION = '1.8.2';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, PAYMENT-SIGNATURE, x-payment');

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
    return [
        ['name' => 'grid402_summary', 'description' => 'Resumo consolidado de saude e disponibilidade dos servicos Grid402 (gratuito).', 'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []]],
        ['name' => 'grid402_forex', 'description' => 'Quote rates USD, EUR, GBP to BRL (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => ['type' => 'string', 'description' => 'Optional txHash on Base Mainnet proving payment.']], 'required' => []]],
        ['name' => 'grid402_crypto', 'description' => 'Spot prices BTC, ETH, SOL, USDC, BASE (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['payment_tx_hash' => ['type' => 'string']], 'required' => []]],
        ['name' => 'grid402_dns', 'description' => 'Authoritative DNS A, AAAA, MX, TXT, NS, CNAME lookup (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string']], 'required' => ['domain']]],
        ['name' => 'grid402_ssl', 'description' => 'Inspect TLS/SSL certificate on port 443 (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['host' => ['type' => 'string']], 'required' => ['host']]],
        ['name' => 'grid402_asn', 'description' => 'BGP routing intelligence: ASN, IP block, provider/org, country (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['ip' => ['type' => 'string']], 'required' => []]],
        ['name' => 'grid402_cep', 'description' => 'Brazilian CEP: street, neighborhood, municipality, UF, IBGE code (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string', 'description' => 'CEP, ex: 01310100']], 'required' => ['code']]],
        ['name' => 'grid402_cnpj', 'description' => 'Brazilian CNPJ situacao cadastral, razao social, CNAE, regime (0.0200 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string']], 'required' => ['cnpj']]],
        ['name' => 'grid402_indicators', 'description' => 'Selic Meta/Diaria, CDI, IPCA 12m (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []]],
        ['name' => 'grid402_holidays', 'description' => 'ANBIMA banking holidays / no-banking-business calendar for a year (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['year' => ['type' => 'integer']], 'required' => []]],
        ['name' => 'grid402_workdays', 'description' => 'ANBIMA business-day count and projection between dates (0.0050 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['start_date' => ['type' => 'string'], 'end_date' => ['type' => 'string'], 'days' => ['type' => 'integer']], 'required' => []]],
        ['name' => 'grid402_sanctions', 'description' => 'CEIS/CNEP sanctions lookup by CNPJ (CGU) (0.0100 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string']], 'required' => ['cnpj']]],
        ['name' => 'grid402_simples', 'description' => 'Simples Nacional / SIMEI (MEI) enquadramento (0.0100 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string']], 'required' => ['cnpj']]],
        ['name' => 'grid402_compliance', 'description' => 'Compliance 360 score (RF + CGU + corporate history), 0-100 (0.0300 USDC).', 'inputSchema' => ['type' => 'object', 'properties' => ['cnpj' => ['type' => 'string']], 'required' => ['cnpj']]],
        ['name' => 'grid402_verify', 'description' => 'Stateless verification of an _oracle_proof ECDSA signature (free).', 'inputSchema' => ['type' => 'object', 'properties' => ['payload' => ['type' => 'object', 'description' => 'JSON object with the _oracle_proof block to verify']], 'required' => ['payload']]],
    ];
}

function mcp_accepts_unpaid(string $service, ?int $requiredRawUnits = null): array {
    $price = match (true) {
        in_array($service, ['cnpj', 'lei', 'ofac'], true) => '0.0200',
        in_array($service, ['sanctions', 'simples'], true) => '0.0100',
        $service === 'compliance' => '0.0300',
        $service === 'pass' => '0.5000',
        default => '0.0050',
    };
    return [
        'error' => 'PaymentRequired',
        'payment_required' => true,
        'service' => $service,
        'price_usdc' => $price,
        'price_raw_units' => (int) ($requiredRawUnits ?? (int) round(((float) $price) * 1e6)),
        'accepts' => grid402_build_accepts((int) round(((float) $price) * 1e6)),
        'x402_version' => 2,
        'facilitator' => 'https://facilitator.payai.network',
        'payTo' => GRID402_ORACLE_WALLET,
        'network' => 'base-mainnet',
        'chain_id' => 8453,
        'token' => 'USDC',
        'token_contract' => GRID402_USDC_CONTRACT,
        'hint' => 'Call the API endpoint directly (e.g. https://grid402.online/api/v1/' . $service . ') or pass payment_tx_hash / PAYMENT-SIGNATURE.',
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

    $map = [
        'grid402_forex' => ['forex', null, 5000],
        'grid402_crypto' => ['crypto', null, 5000],
        'grid402_dns' => ['dns', 'domain', 5000],
        'grid402_ssl' => ['ssl', 'host', 5000],
        'grid402_asn' => ['asn', 'ip', 5000],
        'grid402_cep' => ['cep', 'code', 5000],
        'grid402_cnpj' => ['cnpj', 'cnpj', 20000],
        'grid402_indicators' => ['indicators', null, 5000],
        'grid402_holidays' => ['holidays', 'year', 5000],
        'grid402_workdays' => ['workdays', null, 5000],
        'grid402_sanctions' => ['sanctions', 'cnpj', 10000],
        'grid402_simples' => ['simples', 'cnpj', 10000],
        'grid402_compliance' => ['compliance', 'cnpj', 30000],
    ];
    if (!isset($map[$tool])) {
        mcp_error($responseId, -32602, 'UnknownTool', ['tool' => $tool]);
    }
    [$service, $requiredArg, $rawCost] = $map[$tool];
    if ($requiredArg !== null && empty($arguments[$requiredArg]) && $requiredArg !== 'year') {
        mcp_error($responseId, -32602, 'InvalidArguments', ['message' => "Parametro '{$requiredArg}' e obrigatorio para {$tool}."]);
    }

    // Payment handling
    $txHash = isset($arguments['payment_tx_hash']) ? trim((string)$arguments['payment_tx_hash']) : '';
    $meta = isset($params['_meta']) && is_array($params['_meta']) ? $params['_meta'] : [];
    $x402Payment = isset($meta['x402/payment']) ? (string)$meta['x402/payment'] : '';
    $payHeader = isset($_SERVER['HTTP_PAYMENT_SIGNATURE']) ? strtolower(trim((string)$_SERVER['HTTP_PAYMENT_SIGNATURE'])) : '';

    $paid = false;
    $validation = null;
    if ($x402Payment !== '' || $payHeader !== '') {
        $rawProof = $payHeader !== '' ? $payHeader : $x402Payment;
        $validation = grid402_validate_exact_payment($rawProof, $rawCost, 'mcp://' . $tool);
        $paid = !empty($validation['valid']);
    } elseif ($txHash !== '') {
        $validation = grid402_validate_payment($txHash, $rawCost);
        $paid = !empty($validation['valid']);
    }

    if (!$paid) {
        mcp_error($responseId, -32001, 'PaymentRequired', mcp_accepts_unpaid($service, $rawCost));
    }

    // Record paid telemetry (1 USDC = 20.000 credits irrelevant here)
    grid402_record_telemetry($service, 200, true, (float) number_format($rawCost / 1e6, 4, '.', ''));

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
        default => ['code' => 500, 'error' => 'ToolDispatchMissing'],
    };
    mcp_result($responseId, mcp_content_text(is_array($result) ? $result : ['result' => $result]));
}
