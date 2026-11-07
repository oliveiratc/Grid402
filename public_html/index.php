<?php
/**
 * Grid402 // Hub de Utilitários de Rede & Dados Públicos Abertos (Protocolo x402)
 * 
 * Gateway central:
 * - text/html: Landing Page dark-mode e console interativo para desenvolvedores e humanos
 * - application/json: APIs de utilitários públicos com monetização sob demanda via x402 na Base
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/x402.php';
require_once __DIR__ . '/services/forex.php';
require_once __DIR__ . '/services/crypto.php';
require_once __DIR__ . '/services/dns.php';
require_once __DIR__ . '/services/ssl.php';
require_once __DIR__ . '/services/asn.php';
require_once __DIR__ . '/services/cep.php';
require_once __DIR__ . '/metrics.php';

// Leitura de cabeçalhos e rotas
$acceptHeader = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$apiParam = (string) ($_GET['api'] ?? '');
if ($apiParam === '' && preg_match('#^/api/v1/([a-z0-9_-]+)#i', $requestUri, $matches)) {
    $apiParam = strtolower($matches[1]);
}
$pageParam = (string) ($_GET['page'] ?? '');
$isJsonExplicit = (isset($_GET['format']) && $_GET['format'] === 'json') || str_starts_with($requestUri, '/api/') || str_starts_with($requestUri, '/status');

// Extração do comprovante de pagamento X-Payment
$paymentProof = (string) ($_SERVER['HTTP_X_PAYMENT'] ?? '');
if ($paymentProof === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $paymentProof = (string) ($reqHeaders['X-Payment'] ?? $reqHeaders['x-payment'] ?? '');
}
if ($paymentProof === '') {
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (str_starts_with($authHeader, 'x402 ')) {
        $paymentProof = substr($authHeader, 5);
    }
}

// Cabeçalho de variação de cache para proxies e CDN
header('Vary: Accept, User-Agent');
header('Link: </openapi.json>; rel="service-desc"; type="application/vnd.oai.openapi+json", </llms.txt>; rel="help"; type="text/markdown"');
header('X-Agent-Protocol: x402; network=base; chain_id=8453');
header('X-Micropayment-Pricing: 0.0005 USDC; token=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913');

// 1. Rotas Jurídicas para Máquinas e APIs
if ($pageParam === 'terms' || str_contains($requestUri, '/terms')) {
    if (!$isJsonExplicit && str_contains($acceptHeader, 'text/html') && !str_starts_with($userAgent, 'curl/')) {
        // Renderiza no modal via HTML
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'document' => 'Terms of Service',
            'version' => '2.0.0',
            'hub' => 'Grid402',
            'disclaimer_as_is' => 'O Grid402 é um hub informativo de utilitários públicos de rede fornecido NO ESTADO EM QUE SE ENCONTRA (AS-IS), sem garantias de precisão infalível ou adequação a fins comerciais específicos.',
            'data_scope' => 'O serviço processa estritamente dados de domínio público e protocolos abertos de rede (DNS, SSL, BGP, Câmbio PTAX, Criptoativos e CEP nacional).',
            'settlement' => 'Micropagamentos via x402 liquidados na Base Mainnet (EVM 8453) são definitivos e irreversíveis.',
            'contact' => 'contato@grid402.com.br',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'privacy' || str_contains($requestUri, '/privacy')) {
    if (!$isJsonExplicit && str_contains($acceptHeader, 'text/html') && !str_starts_with($userAgent, 'curl/')) {
        // Renderiza no modal via HTML
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'document' => 'Privacy Policy (LGPD / Marco Civil)',
            'version' => '2.0.0',
            'hub' => 'Grid402',
            'compliance' => ['LGPD (Lei nº 13.709/2018)', 'Marco Civil da Internet (Lei nº 12.965/2014)'],
            'data_collected' => [
                'connection_logs' => 'Endereço IP, data e hora da conexão mantidos sob sigilo por 6 meses estritamente para cumprimento do Art. 15 da Lei nº 12.965/2014.',
                'onchain_hashes' => 'Hash da transação Base informado no cabeçalho X-Payment mantido em cache temporário por 7 dias para prevenção de replay attack (Art. 7º, IX da LGPD - Legítimo Interesse).',
            ],
            'infrastructure_scope' => 'Zero coleta de dados pessoais sensíveis ou PII de cidadãos.',
            'contact_dpo' => 'contato@grid402.com.br',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 2. Roteamento de APIs Técnicas e Dados M2M
$prefersHtml = str_contains($acceptHeader, 'text/html') && !$isJsonExplicit && !str_starts_with($userAgent, 'curl/') && !str_starts_with($userAgent, 'Wget/');

// Se for requisição de API / máquina
if (!$prefersHtml || $apiParam !== '') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment');
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Authenticate, X-Accept-Payment, X-Payment-Status, X-Payment-Amount, X-Payment-Network');

    // 2.1. Rota Gratuita: Resumo Geral do Hub
    if ($apiParam === 'summary' || $apiParam === '' || str_contains($requestUri, '/status')) {
        if (!isset($_GET['detail'])) {
            if (!file_exists(GRID402_FILE_FREE)) {
                if (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
                    define('GRID402_INTERNAL_BOOTSTRAP', true);
                }
                include_once __DIR__ . '/collector.php';
            }
            grid402_record_telemetry('summary', 200, false);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: public, max-age=30');
            readfile(GRID402_FILE_FREE);
            exit;
        }
    }

    // 2.2. Verificação de Pagamento x402 para Micro-serviços Técnicos
    if ($paymentProof === '') {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        grid402_respond_402($serviceLabel);
    }

    // Auditoria on-chain com trava atômica anti-replay
    $validation = grid402_validate_payment($paymentProof);
    if (!$validation['valid']) {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        grid402_record_telemetry($serviceLabel, $validation['code'], false, $paymentProof);
        http_response_code($validation['code']);
        header('Content-Type: application/json; charset=utf-8');
        if ($validation['code'] === 402) {
            header('X-Authenticate: x402');
            header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . GRID402_PRICE_DISPLAY);
        }
        echo json_encode([
            'error' => ($validation['code'] === 403) ? 'ReplayAttackDetected' : 'PaymentFailed',
            'message' => $validation['reason'],
            'tx_hash' => $paymentProof,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Pagamento validado com sucesso! Executa o micro-serviço requisitado
    grid402_record_telemetry($apiParam, 200, true, $paymentProof, null, (float)GRID402_PRICE_DISPLAY);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Payment-Status: Accepted');
    header('X-Payment-Amount: ' . GRID402_PRICE_DISPLAY . ' USDC');
    header('X-Payment-Network: Base (ChainID 8453)');
    header('Cache-Control: private, no-cache');

    $result = [];
    switch ($apiParam) {
        case 'forex':
            $result = grid402_service_forex();
            break;
        case 'crypto':
            $result = grid402_service_crypto();
            break;
        case 'dns':
            $domain = (string) ($_GET['domain'] ?? 'github.com');
            $result = grid402_service_dns($domain);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'ssl':
            $host = (string) ($_GET['host'] ?? 'base.org');
            $result = grid402_service_ssl($host);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'asn':
            $ip = isset($_GET['ip']) ? (string)$_GET['ip'] : null;
            $result = grid402_service_asn($ip);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'cep':
            $code = (string) ($_GET['code'] ?? '01310100');
            $result = grid402_service_cep($code);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        default:
            $result = [
                'protocol' => 'x402',
                'hub' => 'Grid402',
                'status' => 'PAID_QUERY_ACCEPTED',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'services' => grid402_get_utility_services(),
            ];
            break;
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Renderização da Landing Page Dark-Mode para Desenvolvedores Humanos
if (!file_exists(GRID402_FILE_FREE)) {
    if (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
        define('GRID402_INTERNAL_BOOTSTRAP', true);
    }
    include_once __DIR__ . '/collector.php';
}

$summaryData = @json_decode((string) @file_get_contents(GRID402_FILE_FREE), true) ?: [];
$services = $summaryData['services'] ?? [];
$servicesCatalog = grid402_get_utility_services();

$usdRate = $services['forex']['usd_brl'] ?? 5.50;
$btcPrice = $services['crypto']['btc_usd'] ?? 75000.0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Hub de Utilitários de Rede & Dados Públicos (x402)</title>
    <meta name="description" content="Portal e oráculo de micro-serviços públicos (Câmbio, Cripto, DNS, SSL, ASN, CEP) para agentes de IA e robôs M2M com micropagamentos x402 na Base.">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="sitemap" type="application/xml" href="/sitemap.xml">

    <!-- Metadados Schema.org para Agentes de IA e Motores de Busca -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebAPI",
      "name": "Grid402 Public Data & Network Utilities Hub",
      "url": "https://www.grid402.com.br",
      "description": "High-performance M2M public data and network utilities oracle (Forex, Crypto, DNS, SSL, ASN, CEP) for autonomous AI agents settled via the x402 protocol in USDC on Base Mainnet.",
      "documentation": "https://www.grid402.com.br/llms.txt",
      "termsOfService": "https://www.grid402.com.br/terms",
      "provider": {
        "@type": "Organization",
        "name": "Grid402",
        "url": "https://www.grid402.com.br"
      }
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090D14;
            --surface: #111726;
            --surface-hover: #162035;
            --border: #1E293B;
            --border-glow: rgba(16, 185, 129, 0.2);
            --text-main: #F1F5F9;
            --text-muted: #94A3B8;
            --primary: #10B981;
            --primary-glow: rgba(16, 185, 129, 0.15);
            --accent: #38BDF8;
            --warning: #F59E0B;
            --danger: #EF4444;
            --font-sans: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            padding: 0 1.5rem;
            min-height: 100vh;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 3rem 0;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .logo-badge {
            background: linear-gradient(135deg, #10B981, #06B6D4);
            color: #000;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            letter-spacing: -0.5px;
        }
        .logo-title {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-glow);
            color: var(--primary);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-pulse {
            width: 8px;
            height: 8px;
            background-color: var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--primary);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }
        .hero {
            margin: 3.5rem 0;
            text-align: center;
        }
        .hero-tag {
            color: var(--accent);
            font-family: var(--font-mono);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .hero h1 {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -1px;
            margin-bottom: 1.25rem;
            background: linear-gradient(180deg, #FFFFFF 0%, #CBD5E1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            color: var(--text-muted);
            font-size: 1.15rem;
            max-width: 680px;
            margin: 0 auto 2rem;
        }
        .cta-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background-color: var(--primary);
            color: #030712;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:hover { background-color: #059669; }
        .btn-outline {
            background-color: var(--surface);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-outline:hover { background-color: var(--surface-hover); border-color: var(--accent); }

        /* Grid de Serviços */
        .grid-services {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
            margin-bottom: 3.5rem;
        }
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }
        .card:hover {
            border-color: rgba(56, 189, 248, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .card-category {
            font-size: 0.75rem;
            font-family: var(--font-mono);
            background: rgba(56, 189, 248, 0.1);
            color: var(--accent);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        .card-desc {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            min-height: 42px;
        }
        .card-route {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            background: #030712;
            padding: 0.4rem 0.65rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            color: var(--primary);
            word-break: break-all;
        }

        /* Console e Abas */
        .protocol-box {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 3rem;
        }
        .protocol-box h2 {
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }
        .protocol-box p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        .tabs-header {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.65rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .tab-btn:hover { color: var(--text-main); }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .console-actions {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.5rem 0.85rem;
            font-size: 0.82rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-main);
            transition: all 0.2s ease;
        }
        .btn-sm:hover { background: var(--surface-hover); border-color: var(--accent); }
        .input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .console-input {
            flex: 1;
            min-width: 260px;
            background: #030712;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.55rem 0.85rem;
            color: var(--text-main);
            font-family: var(--font-mono);
            font-size: 0.85rem;
        }
        .console-input:focus { outline: none; border-color: var(--accent); }
        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .code-block {
            background-color: #030712;
            border: 1px solid #1E293B;
            border-radius: 8px;
            padding: 1.25rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: #38BDF8;
            overflow-x: auto;
            line-height: 1.5;
            max-height: 380px;
        }

        /* Footer e Modal Legal */
        footer {
            border-top: 1px solid var(--border);
            padding-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 1rem;
        }
        footer a { color: var(--accent); text-decoration: none; }
        footer a:hover { text-decoration: underline; }
        .footer-legal {
            width: 100%;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.75rem;
            line-height: 1.6;
            color: #64748B;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(6px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            max-width: 720px;
            width: 100%;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .modal-title { font-size: 1.15rem; font-weight: 700; color: #fff; }
        .modal-close {
            background: none; border: none; font-size: 1.25rem; color: var(--text-muted);
            cursor: pointer; padding: 0.25rem;
        }
        .modal-close:hover { color: #fff; }
        .modal-tabs { display: flex; border-bottom: 1px solid var(--border); }
        .modal-tab-btn {
            flex: 1; padding: 0.75rem; background: none; border: none;
            border-bottom: 2px solid transparent; color: var(--text-muted);
            font-weight: 600; cursor: pointer; text-align: center;
        }
        .modal-tab-btn.active {
            color: var(--primary); border-bottom-color: var(--primary); background: rgba(16, 185, 129, 0.05);
        }
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            font-size: 0.875rem;
            line-height: 1.7;
            color: #CBD5E1;
        }
        .modal-body h3 { font-size: 1rem; color: #fff; margin: 1.25rem 0 0.5rem; }
        .modal-body h3:first-child { margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-wrap">
                <span class="logo-badge">x402</span>
                <span class="logo-title">Grid402</span>
            </div>
            <div class="badge-live">
                <span class="badge-pulse"></span>
                <span>Base Mainnet (8453) • 6/6 Serviços Operantes</span>
            </div>
        </header>

        <section class="hero">
            <div class="hero-tag">Hub de Micro-Serviços para Robôs & Agentes de IA</div>
            <h1>Utilitários de Rede & Dados Públicos Abertos</h1>
            <p>Acesse cotações de câmbio, cripto, DNS, certificados SSL, BGP/ASN e dados de CEP em milissegundos sem cadastro ou chaves de API. Micropagamentos por chamada em USDC na Base.</p>
            <div class="cta-group">
                <a href="#console" class="btn btn-primary" onclick="switchTab('console')">⚡ Testar no Console</a>
                <a href="/llms.txt" class="btn btn-outline">Documentação llms.txt</a>
                <a href="/status" class="btn btn-outline">Resumo Gratuito (JSON)</a>
            </div>
        </section>

        <!-- Grid dos 6 Serviços -->
        <div class="grid-services">
            <?php foreach ($servicesCatalog as $svc): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><?php echo htmlspecialchars($svc['name']); ?></span>
                    <span class="card-category"><?php echo htmlspecialchars($svc['category']); ?></span>
                </div>
                <div class="card-desc"><?php echo htmlspecialchars($svc['description']); ?></div>
                <div class="card-route">GET <?php echo htmlspecialchars($svc['endpoint']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Console Interativo & Integrações -->
        <section id="console" class="protocol-box">
            <h2>Console de Teste & Especificações de Integração</h2>
            <p>Experimente as requisições em tempo real ou copie os snippets para seus agentes autônomos.</p>

            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('console')">⚡ Console Interativo</button>
                <button class="tab-btn" onclick="switchTab('python')">🐍 Python SDK</button>
                <button class="tab-btn" onclick="switchTab('node')">🟢 Node.js SDK</button>
                <button class="tab-btn" onclick="switchTab('curl')">💻 cURL CLI</button>
                <button class="tab-btn" onclick="switchTab('badges')">🛡️ Badges SVG</button>
            </div>

            <!-- Tab 1: Console Interativo -->
            <div id="tab-console" class="tab-content active">
                <div class="console-actions">
                    <button class="btn-sm" onclick="runConsole('summary')">Resumo Gratuito (/status)</button>
                    <button class="btn-sm" onclick="runConsole('forex')">Câmbio (/api/v1/forex)</button>
                    <button class="btn-sm" onclick="runConsole('crypto')">Cripto (/api/v1/crypto)</button>
                    <button class="btn-sm" onclick="runConsole('dns')">DNS (/api/v1/dns)</button>
                    <button class="btn-sm" onclick="runConsole('ssl')">SSL (/api/v1/ssl)</button>
                    <button class="btn-sm" onclick="runConsole('asn')">ASN (/api/v1/asn)</button>
                    <button class="btn-sm" onclick="runConsole('cep')">CEP (/api/v1/cep)</button>
                </div>
                <div class="input-group">
                    <input type="text" id="tx-input" class="console-input" placeholder="Cole o hash de pagamento na Base (0x...) para testar liberação com X-Payment">
                    <button class="btn-sm" onclick="runConsole('paid')">Enviar com X-Payment</button>
                </div>
                <div class="response-header">
                    <span id="console-status-text">Selecione uma das ações acima para testar o retorno da API.</span>
                    <span id="console-latency-badge" style="display:none;" class="card-category"></span>
                </div>
                <pre id="console-output" class="code-block">// Resposta da API aparecerá aqui...</pre>
            </div>

            <!-- Tab 2: Python -->
            <div id="tab-python" class="tab-content">
                <div class="code-block">from grid402 import Grid402Client

client = Grid402Client()

# 1. Resumo gratuito do hub
summary = client.get_summary()
print("Serviços operantes:", summary["status"])

# 2. Câmbio em tempo real
forex = client.get_forex()
print("USD/BRL:", forex["rates"]["USD"]["bid"])

# 3. DNS Lookup sob demanda
dns_records = client.get_dns("github.com")
print("Registros A:", dns_records["records"]["A"])</div>
            </div>

            <!-- Tab 3: Node.js -->
            <div id="tab-node" class="tab-content">
                <div class="code-block">import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// 1. Consulta gratuita
const summary = await client.getSummary();
console.log('Status do hub:', summary.status);

// 2. Inspetor SSL
const ssl = await client.getSsl('base.org');
console.log('Expira em dias:', ssl.certificate.days_remaining);</div>
            </div>

            <!-- Tab 4: cURL -->
            <div id="tab-curl" class="tab-content">
                <div class="code-block"># 1. Resumo gratuito
curl -i https://www.grid402.com.br/status

# 2. Desafio HTTP 402 em rota de DNS
curl -i "https://www.grid402.com.br/api/v1/dns?domain=github.com"

# 3. Requisição com comprovante na Base
curl "https://www.grid402.com.br/api/v1/dns?domain=github.com" \
  -H "X-Payment: 0x&lt;tx_hash_na_base&gt;"</div>
            </div>

            <!-- Tab 5: Badges -->
            <div id="tab-badges" class="tab-content">
                <p style="color: var(--text-muted); margin-bottom: 1rem;">
                    Badges em SVG dinâmicos para embeddar no README do seu repositório no GitHub:
                </p>
                <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600;">Exemplo de Badge em Tempo Real:</div>
                        <img id="badge-preview-img" src="/badge/system.svg" alt="Badge Preview" style="height: 22px; display: block;" onerror="this.src='/badge.php?target=system'">
                    </div>
                    <button class="btn-sm" onclick="copyBadgeMarkdown()">📋 Copiar Markdown</button>
                </div>
                <pre id="badge-markdown-code" class="code-block">[![Grid402 Hub](https://www.grid402.com.br/badge/system.svg)](https://www.grid402.com.br)</pre>
            </div>
        </section>

        <footer>
            <div>Grid402 // Open Data & Utility Hub • Base Mainnet</div>
            <div>
                <a href="/terms" onclick="openLegalModal('terms'); return false;">Termos de Uso</a> • 
                <a href="/privacy" onclick="openLegalModal('privacy'); return false;">Privacidade (LGPD)</a> • 
                <a href="/openapi.json" target="_blank">OpenAPI 3.1</a> • 
                <a href="/llms.txt" target="_blank">llms.txt</a> • 
                <a href="https://basescan.org/address/<?php echo GRID402_WALLET_ADDRESS; ?>" target="_blank" rel="noopener">BaseScan</a> •
                <span>Taxa: US$ <?php echo GRID402_PRICE_DISPLAY; ?> / consulta</span>
            </div>
            <div class="footer-legal">
                <p><strong>Isenção de Responsabilidade e Dados Públicos:</strong> O Grid402 é um hub utilitário de acesso a dados de domínio público e protocolos abertos de rede fornecido 'NO ESTADO EM QUE SE ENCONTRA' (AS-IS). Não tratamos dados pessoais sensíveis (PII) e operamos em estrita observância à Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e ao Marco Civil da Internet (Lei nº 12.965/2014).</p>
            </div>
        </footer>

        <!-- Modal Jurídico -->
        <div id="legal-modal-overlay" class="modal-overlay" onclick="handleOverlayClick(event)">
            <div class="modal-card">
                <div class="modal-header">
                    <span class="modal-title" id="modal-title-text">Termos de Uso</span>
                    <button class="modal-close" onclick="closeLegalModal()">✕</button>
                </div>
                <div class="modal-tabs">
                    <button id="modal-tab-terms-btn" class="modal-tab-btn active" onclick="switchLegalModal('terms')">Termos de Uso</button>
                    <button id="modal-tab-privacy-btn" class="modal-tab-btn" onclick="switchLegalModal('privacy')">Privacidade & LGPD</button>
                </div>
                <div class="modal-body" id="modal-body-content"></div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(name) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + name).classList.add('active');
        }

        let currentServiceAction = 'summary';

        async function runConsole(action) {
            currentServiceAction = action;
            const out = document.getElementById('console-output');
            const statusText = document.getElementById('console-status-text');
            const badge = document.getElementById('console-latency-badge');

            out.textContent = 'Carregando requisição...';
            badge.style.display = 'none';

            let url = '/status';
            let headers = { 'Accept': 'application/json' };

            switch (action) {
                case 'forex': url = '/api/v1/forex'; break;
                case 'crypto': url = '/api/v1/crypto'; break;
                case 'dns': url = '/api/v1/dns?domain=github.com'; break;
                case 'ssl': url = '/api/v1/ssl?host=base.org'; break;
                case 'asn': url = '/api/v1/asn?ip=1.1.1.1'; break;
                case 'cep': url = '/api/v1/cep?code=01310100'; break;
                case 'paid':
                    const tx = document.getElementById('tx-input').value.trim();
                    if (!tx) {
                        out.textContent = 'Por favor, insira o hash da transação na Base no campo acima.';
                        return;
                    }
                    headers['X-Payment'] = tx;
                    url = '/api/v1/dns?domain=github.com';
                    break;
                default: url = '/status'; break;
            }

            const t0 = performance.now();
            try {
                const res = await fetch(url, { headers });
                const duration = Math.round(performance.now() - t0);
                const json = await res.json();

                statusText.textContent = `HTTP ${res.status} ${res.status === 200 ? 'OK' : (res.status === 402 ? 'Payment Required (Desafio x402)' : 'Error')}`;
                badge.style.display = 'inline-block';
                badge.textContent = `${duration} ms`;

                out.textContent = JSON.stringify(json, null, 2);
            } catch (err) {
                statusText.textContent = 'Falha na requisição';
                out.textContent = err.message;
            }
        }

        function copyBadgeMarkdown() {
            const codeBlock = document.getElementById('badge-markdown-code');
            navigator.clipboard.writeText(codeBlock.textContent).then(() => {
                alert('Snippet copiado para a área de transferência!');
            });
        }

        // Modal Legal
        const legalContent = {
            terms: `
                <h3>1. Objeto e Natureza do Hub</h3>
                <p>O <strong>Grid402</strong> é um hub de micro-serviços públicos e utilitários técnicos de rede para sistemas autônomos e desenvolvedores. O serviço é fornecido 'NO ESTADO EM QUE SE ENCONTRA' (AS-IS), sem garantias de adequação a propósitos comerciais específicos.</p>
                <h3>2. Dados Públicos e Domínio Aberto</h3>
                <p>Todas as consultas utilizam estritamente dados de domínio público (cotações cambiais PTAX, tickers de mercado, base pública de CEP nacional e protocolos abertos de rede como DNS, TLS e BGP/ASN).</p>
                <h3>3. Pagamentos x402 e Irreversibilidade</h3>
                <p>Os micropagamentos são liquidados em USDC na Base Mainnet (8453) de forma definitiva e irreversível.</p>
            `,
            privacy: `
                <h3>1. Conformidade com a LGPD (Lei nº 13.709/2018)</h3>
                <p>O Grid402 não coleta, armazena ou trata dados pessoais identificáveis (PII) de cidadãos. As ferramentas realizam operações técnicas de rede e dados públicos abertos.</p>
                <h3>2. Guarda de Registros (Marco Civil da Internet)</h3>
                <p>Em observância ao Art. 15 da Lei nº 12.965/2014, os registros de conexão (endereço IP, data e hora) são mantidos por 6 meses sob sigilo.</p>
                <h3>3. Contato</h3>
                <p>Canal de comunicação sobre privacidade: <code>contato@grid402.com.br</code>.</p>
            `
        };

        function openLegalModal(tab) {
            switchLegalModal(tab);
            document.getElementById('legal-modal-overlay').classList.add('active');
        }
        function closeLegalModal() {
            document.getElementById('legal-modal-overlay').classList.remove('active');
        }
        function switchLegalModal(tab) {
            const title = document.getElementById('modal-title-text');
            const body = document.getElementById('modal-body-content');
            const termsBtn = document.getElementById('modal-tab-terms-btn');
            const privacyBtn = document.getElementById('modal-tab-privacy-btn');

            if (tab === 'privacy') {
                title.textContent = 'Política de Privacidade & LGPD';
                body.innerHTML = legalContent.privacy;
                termsBtn.classList.remove('active');
                privacyBtn.classList.add('active');
            } else {
                title.textContent = 'Termos de Uso';
                body.innerHTML = legalContent.terms;
                privacyBtn.classList.remove('active');
                termsBtn.classList.add('active');
            }
        }
        function handleOverlayClick(e) {
            if (e.target.id === 'legal-modal-overlay') closeLegalModal();
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeLegalModal();
        });
    </script>
</body>
</html>
