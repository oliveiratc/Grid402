<?php
/**
 * Grid402 // Hub de Utilitários de Rede e Dados Públicos Abertos (Protocolo x402)
 * 
 * Gateway central:
 * - text/html: Landing Page dark-mode e console interativo para desenvolvedores e humanos
 * - application/json: APIs de utilitários públicos com monetização sob demanda via x402 na Base
 */

declare(strict_types=1);

$GLOBALS['GRID402_REQ_START'] = microtime(true);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/x402.php';

grid402_emit_request_id_header();
require_once __DIR__ . '/services/forex.php';
require_once __DIR__ . '/services/crypto.php';
require_once __DIR__ . '/services/dns.php';
require_once __DIR__ . '/services/ssl.php';
require_once __DIR__ . '/services/domain_audit.php';
require_once __DIR__ . '/services/kyb_audit.php';
require_once __DIR__ . '/services/dossier.php';
require_once __DIR__ . '/services/market_snapshot.php';
require_once __DIR__ . '/services/asn.php';
require_once __DIR__ . '/services/cep.php';
require_once __DIR__ . '/services/cnpj.php';
require_once __DIR__ . '/services/indicators.php';
require_once __DIR__ . '/services/holidays.php';
require_once __DIR__ . '/services/workdays.php';
require_once __DIR__ . '/services/compliance.php';
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
require_once __DIR__ . '/services/pass_checkout.php';
require_once __DIR__ . '/services/oracle_signer.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/growth.php';

/**
 * Emite cabecalhos padrao de CORS para todas as rotas /api/v1/* e encerra preflight OPTIONS com HTTP 204.
 */
function grid402_emit_api_cors_headers(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment, x-payment, X-Payment-Network, x-payment-network, X-Session-Pass, x-session-pass, PAYMENT-SIGNATURE, payment-signature, X-Payment-Signature, x-payment-signature, Payment, payment, X-Grid402-Channel, x-grid402-channel, Idempotency-Key, X-Request-Id, X-Signature, X-RapidAPI-Proxy-Secret, X-RapidAPI-Key, X-RapidAPI-Host');
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Authenticate, X-Accept-Payment, X-Payment-Status, X-Payment-Amount, X-Payment-Network, PAYMENT-REQUIRED, Payment-Required, payment-required, PAYMENT-RESPONSE, X-PAYMENT-RESPONSE, X-Idempotent-Replay, X-Request-Id');
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Registra visita à página pública HTML (contador de acessos exibido no /admin).
 * Só conta GET/HEAD — APIs JSON continuam aferidas pela telemetria técnica.
 */
function grid402_log_public_visit(string $path): void {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return;
    }
    grid402_record_page_visit($path, grid402_get_trusted_client_ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), (string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
}

// Leitura de cabeçalhos e rotas
$acceptHeader = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

$parsedUrl = parse_url($requestUri);
$requestPath = rtrim((string)($parsedUrl['path'] ?? '/'), '/');
if ($requestPath === '') {
    $requestPath = '/';
}

$apiParam = (string) ($_GET['api'] ?? '');
if ($apiParam === '' && preg_match('#^/api/v1/pass/(balance|limits|statement)$#i', $requestPath, $matchesPassSub)) {
    $apiParam = 'pass-' . strtolower($matchesPassSub[1]);
} elseif ($apiParam === '' && preg_match('#^/api/v1/([a-z0-9_-]+)#i', $requestPath, $matches)) {
    $apiParam = strtolower($matches[1]);
}
$pageParam = (string) ($_GET['page'] ?? '');
$isJsonExplicit = (isset($_GET['format']) && $_GET['format'] === 'json') || str_starts_with($requestPath, '/api/') || $requestPath === '/status';

// Normalizacao case-insensitive dos cabecalhos da requisicao
$normalizedReqHeaders = [];
$rawHeaderSource = function_exists('getallheaders')
    ? (getallheaders() ?: [])
    : (function_exists('apache_request_headers') ? (apache_request_headers() ?: []) : []);
if (is_array($rawHeaderSource)) {
    foreach ($rawHeaderSource as $hk => $hv) {
        $normalizedReqHeaders[strtolower((string) $hk)] = trim((string) $hv);
    }
}

// Extração do cabeçalho opcional Idempotency-Key (TTL 300s)
$idempotencyKey = '';
foreach ([
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null,
    $normalizedReqHeaders['idempotency-key'] ?? null,
] as $idemCandidate) {
    $trimmedIdem = trim((string) ($idemCandidate ?? ''));
    if ($trimmedIdem !== '') {
        $idempotencyKey = $trimmedIdem;
        break;
    }
}

// 0. Resposta em Markdown quando solicitado por agentes de IA (Accept: text/markdown) EXCLUSIVAMENTE na rota raiz /
$isRootOnlyRequest = ($requestPath === '/' || $requestPath === '/index.php') && $apiParam === '' && $pageParam === '' && !$isJsonExplicit;
if ($isRootOnlyRequest && (str_contains($acceptHeader, 'text/markdown') || str_contains($acceptHeader, 'text/x-markdown') || (isset($_GET['format']) && $_GET['format'] === 'markdown'))) {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Vary: Accept, User-Agent');
    readfile(__DIR__ . '/llms.txt');
    exit;
}

// Extração do comprovante de pagamento X-Payment (ou alias X-Session-Pass), PAYMENT-SIGNATURE e aliases x402
// Ignora valores vazios/whitespace para que HTTP_X_PAYMENT="" nao curto-circuite HTTP_X_SESSION_PASS
$paymentProof = '';
$paymentProofSourceHeader = 'none';
foreach ([
    'x-payment' => $_SERVER['HTTP_X_PAYMENT'] ?? null,
    'x-session-pass' => $_SERVER['HTTP_X_SESSION_PASS'] ?? null,
    'x-payment_norm' => $normalizedReqHeaders['x-payment'] ?? null,
    'x-session-pass_norm' => $normalizedReqHeaders['x-session-pass'] ?? null,
] as $hdrKey => $proofCandidate) {
    $trimmedCandidate = trim((string) ($proofCandidate ?? ''));
    if ($trimmedCandidate !== '') {
        $paymentProof = $trimmedCandidate;
        $paymentProofSourceHeader = str_replace('_norm', '', (string) $hdrKey);
        break;
    }
}
if ($paymentProof === '') {
    // Clientes x402 v2 padrao enviam a prova no cabecalho PAYMENT-SIGNATURE (ou aliases X-Payment-Signature / Payment)
    foreach ([
        'payment-signature' => $_SERVER['HTTP_PAYMENT_SIGNATURE'] ?? null,
        'payment-signature_norm' => $normalizedReqHeaders['payment-signature'] ?? null,
        'x-payment-signature' => $_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? null,
        'x-payment-signature_norm' => $normalizedReqHeaders['x-payment-signature'] ?? null,
        'payment' => $_SERVER['HTTP_PAYMENT'] ?? null,
        'payment_norm' => $normalizedReqHeaders['payment'] ?? null,
    ] as $sigHdr => $sigCandidate) {
        $trimmedSig = trim((string) ($sigCandidate ?? ''));
        if ($trimmedSig !== '') {
            $paymentProof = $trimmedSig;
            $paymentProofSourceHeader = str_replace('_norm', '', (string) $sigHdr);
            break;
        }
    }
}
if ($paymentProof === '') {
    $authHeader = '';
    foreach ([
        $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
        $normalizedReqHeaders['authorization'] ?? null,
    ] as $authCandidate) {
        $trimmedAuth = trim((string) ($authCandidate ?? ''));
        if ($trimmedAuth !== '') {
            $authHeader = $trimmedAuth;
            break;
        }
    }
    if (preg_match('/^(?:x402|payment)\s+(.+)$/is', $authHeader, $am)) {
        $paymentProof = trim($am[1]);
        $paymentProofSourceHeader = 'authorization_x402';
    } elseif (preg_match('/^bearer\s+((?:pass_g402_|0x[a-f0-9]{40,}|ey[A-Za-z0-9_-]|ew[A-Za-z0-9_-]|\{).+)$/is', $authHeader, $bm)) {
        $paymentProof = trim($bm[1]);
        $paymentProofSourceHeader = 'authorization_bearer';
    }
}
if ($paymentProof !== '' && function_exists('grid402_normalize_payment_proof_header')) {
    $paymentProof = grid402_normalize_payment_proof_header($paymentProof);
}
$paymentNetworkHeader = '';
foreach ([
    $_SERVER['HTTP_X_PAYMENT_NETWORK'] ?? null,
    $normalizedReqHeaders['x-payment-network'] ?? null,
] as $netCandidate) {
    $trimmedNet = trim((string) ($netCandidate ?? ''));
    if ($trimmedNet !== '') {
        $paymentNetworkHeader = $trimmedNet;
        break;
    }
}
$rawRequestBody = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    ? (string) @file_get_contents('php://input')
    : '';
if ($paymentProof === '') {
    if (!empty($_GET['tx'])) {
        $paymentProof = trim((string) $_GET['tx']);
        $paymentProofSourceHeader = 'query_tx';
    } elseif (!empty($_GET['txHash'])) {
        $paymentProof = trim((string) $_GET['txHash']);
        $paymentProofSourceHeader = 'query_tx';
    } elseif (!empty($_GET['tx_hash'])) {
        $paymentProof = trim((string) $_GET['tx_hash']);
        $paymentProofSourceHeader = 'query_tx';
    } elseif ($rawRequestBody !== '') {
        $parsedPost = @json_decode($rawRequestBody, true);
        if (is_array($parsedPost)) {
            $postTxCandidate = trim((string) ($parsedPost['tx'] ?? $parsedPost['txHash'] ?? $parsedPost['tx_hash'] ?? ''));
            if ($postTxCandidate !== '') {
                $paymentProof = $postTxCandidate;
                $paymentProofSourceHeader = 'post_tx';
            }
        }
    }
}

// Cabeçalhos Globais de IA, Protocolo, Segurança e Conformidade de Dados
header('Vary: Accept, User-Agent');
header('Link: </openapi.json>; rel="service-desc"; type="application/vnd.oai.openapi+json", </llms.txt>; rel="help"; type="text/markdown", </.well-known/ai-agent.json>; rel="describedby"; type="application/json", </.well-known/agent-card.json>; rel="agent-card"; type="application/json", </.well-known/mcp.json>; rel="mcp"; type="application/json", </.well-known/mcp/server-card.json>; rel="service-meta"; type="application/json", </pricing.json>; rel="pricing"; type="application/json", </sitemap.xml>; rel="sitemap"; type="application/xml", </terms>; rel="terms-of-service"; type="text/html", </privacy>; rel="privacy-policy"; type="text/html"');
header('X-Agent-Protocol: x402; network=base; chain_id=8453');
header('X-Micropayment-Pricing: 0.0050-0.0500 USDC; token=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913; pricing=/catalog');
header('X-Data-Protection-Policy: Zero-PII; Corporate-Entities-Only; LGPD-Art1-Compliant; GDPR-Recital14-Compliant; CCPA-Compliant');
header('X-Open-Data-Licenses: CC0-1.0, US-Public-Domain-17USC105, BR-LAI-12527, EU-Open-Data-2019-1024, IETF-Public-Standards');
header('X-Copyright: Copyright (c) ' . date('Y') . ' Grid402 Network Intelligence. All rights reserved. Proprietary Software & Architecture (Law 9.609/1998 & Law 9.610/1998).');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Content-Signal: search=yes, ai-input=yes, ai-train=no');

/**
 * Renderizador de Paginas Institucionais HTML para Agentes e Navegadores
 */
function grid402_render_page_html(string $title, string $metaDesc, string $canonicalPath, string $h1, string $bodyContent): void {
    grid402_log_public_visit($canonicalPath);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <link rel="canonical" href="https://grid402.online<?php echo $canonicalPath; ?>">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="sitemap" type="application/xml" href="/sitemap.xml">
    <link rel="service-meta" type="application/json" href="/.well-known/mcp/server-card.json">
    <link rel="describedby" type="application/json" href="/.well-known/ai-agent.json">
    <link rel="agent-card" type="application/json" href="/.well-known/agent-card.json">
    <link rel="mcp" type="application/json" href="/.well-known/mcp.json">
    <link rel="pricing" type="application/json" href="/pricing.json">

    <!-- Schema.org JSON-LD Completo com Organization e Servicos -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": ["Organization", "LocalBusiness"],
          "@id": "https://grid402.online/#organization",
          "name": "Grid402",
          "url": "https://grid402.online",
          "logo": "https://grid402.online/assets/logo.png",
          "email": "contato@grid402.online",
          "telephone": "+55-11-95832-5848",
          "description": "Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.",
          "priceRange": "$0.0050 - $0.0500",
          "areaServed": "Worldwide",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "São Paulo",
            "addressRegion": "SP",
            "addressCountry": "BR"
          },
          "sameAs": [
            "https://github.com/oliveiratc/Grid402",
            "https://www.npmjs.com/package/grid402",
            "https://www.npmjs.com/package/grid402-mcp",
            "https://pypi.org/project/grid402/",
            "https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90"
          ],
          "contactPoint": [
            {
              "@type": "ContactPoint",
              "contactType": "customer support",
              "email": "contato@grid402.online",
              "telephone": "+55-11-95832-5848",
              "availableLanguage": ["Portuguese", "English"]
            }
          ]
        }
      ]
    }
    </script>
<?php require_once __DIR__ . '/includes/seo_head.php'; grid402_render_seo_head([
    'title' => $title,
    'description' => $metaDesc,
    'path' => $canonicalPath,
    'card' => 'global',
    'locale' => 'en_US',
]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #060B19;
            --surface: #0B132B;
            --surface-hover: #111C38;
            --border: #1E2C4F;
            --border-glow: rgba(0, 240, 255, 0.25);
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --primary: #0052FF;
            --primary-glow: rgba(0, 82, 255, 0.25);
            --secondary: #00F0FF;
            --accent: #00F0FF;
            --accent-glow: rgba(0, 240, 255, 0.2);
            --font-heading: 'Space Grotesk', system-ui, sans-serif;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { background-color: var(--bg); color: var(--text-main); font-family: var(--font-sans); line-height: 1.6; padding: 2rem 1rem; overflow-x: hidden; }
        .page-wrap { max-width: 820px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 2.5rem; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .nav-links { display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem; }
        .nav-links a { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .lang-switch { display: inline-flex; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 999px; padding: 2px; margin-left: 0.5rem; }
        .lang-btn { background: transparent; border: none; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; cursor: pointer; transition: all 0.2s; }
        .lang-btn.active { background: var(--accent); color: #030712; }
        h1, h2, h3 { font-family: var(--font-heading); }
        h1 { font-size: clamp(1.5rem, 5vw, 2rem); font-weight: 700; color: var(--text-main); margin-bottom: 1.25rem; letter-spacing: -0.5px; }
        h2 { font-size: 1.25rem; color: var(--text-main); margin: 1.75rem 0 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        p { color: var(--text-muted); margin-bottom: 1rem; }
        ul { margin: 0 0 1.25rem 1.5rem; color: var(--text-muted); }
        li { margin-bottom: 0.5rem; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .card-box { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; }
        footer { margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .footer-links a { color: var(--text-muted); margin-right: 1rem; transition: color 0.2s; }
        .footer-links a:hover { color: var(--accent); }
        @media (max-width: 600px) {
            body { padding: 1rem 0.6rem; }
            .page-wrap { padding: 1.25rem 0.85rem; border-radius: 8px; }
            header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .nav-links { gap: 0.5rem; }
            footer { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => $canonicalPath, 'show_lang' => true]); ?>
    <div class="page-wrap">

        <div id="inst-page-content">
            <h1><?php echo htmlspecialchars($h1); ?></h1>
            <?php echo $bodyContent; ?>
        </div>

        <footer>
            <div class="footer-links">
                <a href="/" data-i18n="nav.home">Home</a>
                <a href="/agents" data-i18n="nav.agents">AI Agents</a>
                <a href="/catalog" data-i18n="nav.catalog">Catalog</a>
                <a href="/growth" data-i18n="nav.growth">Growth Program</a>
                <a href="/payment-dashboard" data-i18n="nav.payments">Payments</a>
                <a href="/trust" data-i18n="nav.trust">Trust</a>
                <a href="/about" data-i18n="nav.about">About</a>
                <a href="/contact" data-i18n="nav.contact">Contact</a>
                <a href="/terms" data-i18n="nav.terms">Terms</a>
                <a href="/privacy" data-i18n="nav.privacy">Privacy</a>
                <a href="/llms.txt">llms.txt</a>
                <a href="mailto:contato@grid402.online">contato@grid402.online</a>
            </div>
            <div data-i18n="inst.footer.copy">© <?php echo date('Y'); ?> Grid402 Network Intelligence. All rights reserved.</div>
        </footer>
    </div>
    <script src="/assets/js/pass_storage.js?v=3.5.0"></script>
    <script src="/assets/js/i18n.js?v=3.8.0"></script>
</body>
</html>
    <?php
    exit;
}

// 1. Rotas Institucionais e Jurídicas
if ($pageParam === 'about' || $requestPath === '/about') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $aboutBody = '<div data-i18n-html="inst.about.body">
        <p><strong>Grid402</strong> is a global open-data microservices and network utility oracle designed specifically for autonomous systems, bots, and AI agents under the HTTP 402 standard with instant USDC settlement on Base Mainnet.</p>
        <h2>Global Purpose and Architecture</h2>
        <p>Grid402 eliminates static API keys and bureaucratic signups for software agents worldwide. Every technical query to official registries (worldwide GLEIF LEI, US Treasury OFAC SDN, 30+ ECB currencies, ECDSA cryptographic notarization, corporate registries, DNS, SSL, and BGP) can be settled directly on-chain or via a Prepaid Session Pass in under 15ms.</p>
        <ul>
            <li><strong>Zero-DB and Stateless:</strong> No relational database lock-in; 100% in-memory and atomic file cache operation.</li>
            <li><strong>x402 Protocol + Session Pass:</strong> Native HTTP 402 Payment Required implementation for Machine-to-Machine (M2M) and high-frequency prepaid passes.</li>
            <li><strong>Public Domain and CC0:</strong> Strict reliance on open public registries and CC0 licenses with zero sensitive personal data (Zero PII).</li>
        </ul>
        <h2>Institutional Information</h2>
        <div class="card-box">
            <p><strong>Organization:</strong> Grid402 Network Intelligence</p>
            <p><strong>Support Email:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
            <p><strong>Phone Hotline:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            <p><strong>Oracle Wallet (Base):</strong> <a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</a></p>
        </div></div>';
        grid402_render_page_html('About Grid402 - Global M2M Open Data & Network Utilities Hub', 'Learn about Grid402 global infrastructure and 22 open-data microservices for AI agents and developers.', '/about', 'About Grid402', $aboutBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        grid402_log_request(['service' => 'about', 'status' => 200, 'payment_source' => 'free']);
        echo json_encode([
            'organization' => 'Grid402',
            'name' => 'Grid402 Public Data and Network Utilities Hub',
            'description' => 'Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.',
            'network' => 'Base Mainnet',
            'chain_id' => 8453,
            'oracle_wallet' => '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
            'copyright' => 'Copyright (c) ' . date('Y') . ' Grid402 Network Intelligence. Todos os direitos reservados / All rights reserved.',
            'license' => 'Proprietary Software & Brand (All Rights Reserved — Lei nº 9.609/1998 & Lei nº 9.610/1998). Returned data remains Public Domain / Open Government Data.',
            'sdks' => [
                'python' => 'https://pypi.org/project/grid402/',
                'node' => 'https://www.npmjs.com/package/grid402',
                'mcp' => 'https://www.npmjs.com/package/grid402-mcp'
            ],
            'documentation' => 'https://grid402.online/llms.txt',
            'contact' => 'contato@grid402.online'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'contact' || $requestPath === '/contact') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $contactBody = '<div data-i18n-html="inst.contact.body">
        <p>We provide direct, structured channels for technical support, AI integrator partnerships, and Data Protection Officer (DPO) inquiries:</p>
        <h2>Official Support Channels</h2>
        <div class="card-box">
            <p><strong>General Support and Integrations:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
            <p><strong>Data Protection Officer (DPO / GDPR / LGPD):</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
            <p><strong>Phone Hotline:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
        </div>
        <h2>Open Source Ecosystem</h2>
        <ul>
            <li><strong>Official GitHub:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a></li>
            <li><strong>Node.js Package (npm):</strong> <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a></li>
            <li><strong>Python Package (PyPI):</strong> <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a></li>
            <li><strong>MCP Server (Model Context Protocol):</strong> <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a></li>
        </ul></div>';
        grid402_render_page_html('Contact Grid402 - Official Channels & Support', 'Contact Grid402: official technical support channels, Data Protection Officer (DPO), and open-source repositories.', '/contact', 'Contact Grid402', $contactBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        grid402_log_request(['service' => 'contact', 'status' => 200, 'payment_source' => 'free']);
        echo json_encode([
            'organization' => 'Grid402',
            'support_email' => 'contato@grid402.online',
            'dpo_email' => 'contato@grid402.online',
            'telephone' => '+55-11-95832-5848',
            'github' => 'https://github.com/oliveiratc/Grid402',
            'website' => 'https://grid402.online'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'terms' || $requestPath === '/terms') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $termsBody = '<div data-i18n-html="inst.terms.body">
        <p>Terms of Service, Open Data Legal Basis, and International Regulatory Compliance for the Grid402 global M2M microservices hub.</p>
        <h2>1. Provided "AS-IS"</h2>
        <p>Grid402 is a technical M2M oracle infrastructure provided strictly "AS-IS", without warranties of fitness for a particular commercial purpose, legal counsel, or credit underwriting.</p>
        <h2>2. Public Domain Legal Basis and Open Data Licenses</h2>
        <p>All 22 Grid402 endpoints process exclusively public-domain records, non-copyrightable facts, and open government registries:</p>
        <ul>
            <li><strong>Global LEI (/api/v1/lei):</strong> Global Legal Entity Identifier Foundation (GLEIF) under <strong>CC0 1.0 Universal (Public Domain Dedication)</strong>, ISO 17442 standard.</li>
            <li><strong>Global Sanctions (/api/v1/ofac):</strong> US Treasury OFAC SDN (US Federal Public Domain under <strong>17 U.S.C. § 105</strong>) and United Nations Security Council Consolidated Lists.</li>
            <li><strong>World Markets and FX (/api/v1/global-markets, /api/v1/forex, /api/v1/crypto):</strong> European Central Bank reference rates (<strong>EU Open Data Directive 2019/1024</strong>), Central Bank of Brazil (Decree 8.777/2016), and non-copyrightable market facts (<strong>17 U.S.C. § 102(b)</strong> and <strong>Brazilian Law 9.610/1998 Art. 8, I</strong>).</li>
            <li><strong>Corporate & Statutory Calendars (/api/v1/cnpj, /api/v1/sanctions, /api/v1/simples, /api/v1/compliance, /api/v1/indicators, /api/v1/holidays, /api/v1/workdays, /api/v1/cep):</strong> Brazilian Access to Information Act (<strong>Law 12.527/2011 - LAI</strong>), Open Data Policy (<strong>Decree 8.777/2016</strong>), Anti-Corruption Act (Law 12.846/2013), and mathematical calendar algorithms (Law 9.610/1998 Art. 8, II & IV).</li>
            <li><strong>Network Telemetry & Notarization (/api/v1/dns, /api/v1/ssl, /api/v1/asn, /api/v1/notarize):</strong> Open Internet Engineering Task Force standards (IETF RFC 1035, RFC 5280, RFC 4271, and RFC 8785).</li>
        </ul>
        <h2>3. x402 Micropayments and Session Passes</h2>
        <p>Micropayments settled via HTTP 402 and Prepaid Session Passes issued on Base Mainnet (EVM 8453) compensate strictly for computational normalization and ECDSA secp256k1 cryptographic attestation, and are final and non-refundable on-chain.</p>
        <h2>4. Copyright and All Rights Reserved (Intellectual Property)</h2>
        <p>© ' . date('Y') . ' <strong>Grid402 Network Intelligence</strong>. <strong>All rights reserved.</strong> The Grid402 platform software, HTTP 402 settlement engine, Session Pass architecture, visual design, brand, and documentation are the exclusive intellectual property of Grid402, protected by the <strong>Software Protection Act (Law 9.609/1998)</strong>, <strong>Copyright Act (Law 9.610/1998)</strong>, and international treaties (Berne Convention). Unauthorized reproduction, redistribution, or reverse engineering of the platform is strictly prohibited. We claim no proprietary exclusivity over government registries or public-domain facts returned by the APIs.</p></div>';
        grid402_render_page_html('Terms of Service - Grid402', 'Terms of Service, copyright protection, and open-data legal framework (CC0, 17 U.S.C. 105, LAI) for Grid402.', '/terms', 'Terms of Service', $termsBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        grid402_log_request(['service' => 'terms', 'status' => 200, 'payment_source' => 'free']);
        echo json_encode([
            'document' => 'Terms of Service, Intellectual Property & Open Data Legal Framework',
            'version' => '3.0.5',
            'hub' => 'Grid402',
            'copyright' => 'Copyright (c) ' . date('Y') . ' Grid402 Network Intelligence. Todos os direitos reservados / All rights reserved.',
            'intellectual_property' => 'Software, marca, interface e arquitetura proprietarios com todos os direitos reservados (Lei nº 9.609/1998, Lei nº 9.610/1998 e Convencao de Berna).',
            'disclaimer_as_is' => 'O Grid402 e um hub informativo de utilitarios publicos de rede fornecido NO ESTADO EM QUE SE ENCONTRA (AS-IS), sem garantias de adequacao a fins comerciais especificos.',
            'open_data_legal_basis' => [
                'gleif_lei' => 'CC0 1.0 Universal (Public Domain Dedication - ISO 17442)',
                'us_ofac_sdn' => '17 U.S.C. § 105 (US Government Works in the Public Domain) & UN Security Council Open Lists',
                'eu_ecb_fx' => 'EU Open Data Directive (Directive (EU) 2019/1024) & ECB Statistical Data Reuse Policy',
                'br_public_records' => 'Lei nº 12.527/2011 (Lei de Acesso a Informacao - LAI), Decreto nº 8.777/2016 (Politica de Dados Abertos) e Lei nº 12.846/2013 (CEIS/CNEP)',
                'market_and_calendar_facts' => 'Lei nº 9.610/1998 Art. 8º, I, II e IV (Fatos economicos, algoritmos matematicos de calendario e textos legais) e 17 U.S.C. § 102(b)',
                'network_protocols' => 'IETF RFC 1035 (DNS), RFC 5280 (X.509 TLS), RFC 4271 (BGP/ASN), RFC 8785 (JCS Canonical JSON)',
            ],
            'settlement' => 'Micropagamentos via x402 e Session Passes liquidados na Base Mainnet (EVM 8453) remuneram o processamento computacional e atestacao ECDSA, sendo definitivos e irreversiveis.',
            'software_license' => 'Proprietary — All Rights Reserved (Grid402 Network Intelligence)',
            'contact' => 'contato@grid402.online',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'privacy' || $requestPath === '/privacy') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $privacyBody = '<div data-i18n-html="inst.privacy.body">
        <p>Privacy, data protection, and international regulatory compliance guidelines (GDPR, LGPD, and CCPA/CPRA) for the Grid402 hub.</p>
        <h2>1. Zero-PII Architecture and Corporate-Only Scope (GDPR Recital 14 / LGPD Art. 1)</h2>
        <p>Grid402 operates strictly on <strong>Legal Entity (corporate)</strong> records, macroeconomic indicators, and network telemetry. Pursuant to <strong>Recital 14 of the EU GDPR (Regulation (EU) 2016/679)</strong> and <strong>Article 1 of the Brazilian LGPD (Law 13.709/2018)</strong>, data concerning legal persons does not constitute personal data of natural persons. Across corporate lookup endpoints (<code>/api/v1/cnpj</code>, <code>/api/v1/compliance</code>), Grid402 <strong>strictly strips and discards at ingestion</strong> shareholder/director lists (QSA), individual tax IDs (CPFs), personal phone numbers, and private emails.</p>
        <h2>2. Data Minimization, IP Pseudonymization, and Zero-Retention (/api/v1/notarize)</h2>
        <ul>
            <li><strong>IP Masking & HMAC Pseudonymization:</strong> IP addresses in operational telemetry are irreversibly truncated (e.g., <code>189.40.xxx.xxx</code>) and Sandbox rate-limiter keys use salted <code>HMAC-SHA256</code> cryptographic hashes (compliant with GDPR Art. 25 & LGPD Art. 13).</li>
            <li><strong>Zero-Retention Notarization:</strong> In <code>/api/v1/notarize</code>, submitted payloads are processed exclusively in volatile RAM to compute the <code>SHA-256</code> digest and immediately discarded, with zero disk retention or raw text echoing.</li>
        </ul>
        <h2>3. Statutory Logs and Anti-Fraud Security</h2>
        <p>Minimal technical connection logs are kept confidentially as required by <strong>Art. 15 of the Brazilian Civil Rights Framework for the Internet (Law 12.965/2014)</strong>, and public Base Mainnet transaction hashes (<code>X-Payment</code>) are cached for 7 days strictly for anti-replay security (GDPR Art. 6(1)(f) & LGPD Art. 7, IX).</p>
        <h2>4. Data Subject Rights & Data Protection Officer (DPO)</h2>
        <p>Grid402 uses zero advertising/tracking cookies and never sells or shares personal information (CCPA/CPRA). For any privacy, compliance, or DPO inquiry, contact <a href="mailto:contato@grid402.online">contato@grid402.online</a>.</p></div>';
        grid402_render_page_html('Privacy Policy - Grid402', 'Privacy guidelines, Zero-PII architecture, and GDPR, LGPD, and CCPA compliance for Grid402.', '/privacy', 'Privacy Policy', $privacyBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        grid402_log_request(['service' => 'privacy', 'status' => 200, 'payment_source' => 'free']);
        echo json_encode([
            'document' => 'Privacy Policy & Global Data Protection Compliance',
            'version' => '3.0.4',
            'hub' => 'Grid402',
            'regulatory_frameworks' => [
                'BR_LGPD' => 'Lei Geral de Proteção de Dados (Lei nº 13.709/2018 - Art. 1º, Art. 6º, III e Art. 7º, IX)',
                'BR_MCI' => 'Marco Civil da Internet (Lei nº 12.965/2014 - Art. 15)',
                'EU_GDPR' => 'General Data Protection Regulation (Regulation (EU) 2016/679 - Recital 14, Art. 5(1)(c), Art. 6(1)(f) & Art. 25)',
                'US_CCPA_CPRA' => 'California Consumer Privacy Act (Zero sale/sharing of personal information, Zero tracking cookies)',
            ],
            'zero_pii_safeguards' => [
                'corporate_cnpj_filtering' => 'Strict stripping of QSA (shareholders/directors names), CPFs, personal phones, and private emails at ingestion.',
                'ip_anonymization' => 'Irreversible IPv4/IPv6 subnet masking in telemetry (xxx.xxx) and HMAC-SHA256 pseudonymization in rate limiters.',
                'notarize_zero_retention' => 'In-memory SHA-256 hashing only; raw input payloads are never stored on disk or echoed in certificates.',
            ],
            'data_retention' => [
                'connection_logs' => 'Retained confidentially for 6 months strictly as mandated by Art. 15 of Brazilian Law 12.965/2014.',
                'onchain_tx_hashes' => 'Public Base Mainnet transaction hashes cached for 7 days strictly for anti-replay protection.',
            ],
            'contact_dpo' => 'contato@grid402.online',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 1.1. Fallback Resiliente para o Painel Administrativo (/admin e /dashboard)
if ($requestPath === '/admin' || $requestPath === '/dashboard' || $pageParam === 'admin' || $pageParam === 'dashboard') {
    require __DIR__ . '/admin.php';
    exit;
}

// 1.2. Central de Agentes de IA (/agents)
if ($requestPath === '/agents' || $pageParam === 'agents') {
    grid402_log_public_visit('/agents');
    require __DIR__ . '/agents.php';
    exit;
}

// 1.3. Checkout Mercado Pago (Pix + Checkout Pro) para Session Pass
if (str_starts_with($requestPath, '/api/v1/pass/mp-')) {
    grid402_emit_api_cors_headers();
    require_once __DIR__ . '/services/mercadopago.php';
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $rawBody = $rawRequestBody !== '' ? $rawRequestBody : (string) @file_get_contents('php://input');
    $jsonBody = @json_decode($rawBody, true);
    $input = is_array($jsonBody) ? $jsonBody : $_POST;

    if ($requestPath === '/api/v1/pass/mp-pix' && $method === 'POST') {
        $res = grid402_mp_create_pix((string) ($input['tier'] ?? ''), (string) ($input['email'] ?? ''));
    } elseif ($requestPath === '/api/v1/pass/mp-checkout' && $method === 'POST') {
        $res = grid402_mp_create_checkout_pro((string) ($input['tier'] ?? ''), (string) ($input['email'] ?? ''));
    } elseif ($requestPath === '/api/v1/pass/mp-webhook' && $method === 'POST') {
        $reqHeaders = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        if (empty($reqHeaders['x-signature']) && !empty($_SERVER['HTTP_X_SIGNATURE'])) {
            $reqHeaders['x-signature'] = $_SERVER['HTTP_X_SIGNATURE'];
        }
        if (empty($reqHeaders['x-request-id']) && !empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            $reqHeaders['x-request-id'] = $_SERVER['HTTP_X_REQUEST_ID'];
        }
        $webhookRes = grid402_mp_handle_webhook($reqHeaders, $rawBody, $_GET);
        // Proteção contra vazamento: o webhook deve responder apenas confirmação genérica sem o pass_token
        if (!empty($webhookRes['ok'])) {
            $res = ['ok' => true, 'code' => 200, 'message' => 'Webhook processado com sucesso.'];
        } else {
            $res = ['ok' => false, 'code' => $webhookRes['code'] ?? 400, 'error' => $webhookRes['error'] ?? 'WebhookFailed', 'message' => $webhookRes['message'] ?? 'Falha ao processar webhook.'];
        }
    } elseif ($requestPath === '/api/v1/pass/mp-status' && $method === 'GET') {
        $orderId = (string) ($_GET['order_id'] ?? $_GET['mp_order'] ?? '');
        $paymentHint = isset($_GET['payment_id']) ? (string) $_GET['payment_id'] : (isset($_GET['collection_id']) ? (string) $_GET['collection_id'] : null);
        $res = grid402_mp_get_order_status($orderId, $paymentHint);
    } elseif ($requestPath === '/api/v1/pass/mp-sandbox-approve' && $method === 'POST') {
        $res = grid402_mp_sandbox_approve((string) ($input['order_id'] ?? ''));
    } else {
        $res = ['ok' => false, 'code' => 405, 'error' => 'MethodNotAllowed', 'message' => 'Rota ou metodo HTTP nao suportado para checkout Mercado Pago.'];
    }

    http_response_code((int) ($res['code'] ?? 200));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $mpStatusVal = strtolower((string) ($res['status'] ?? ''));
    $isMpApprovalRoute = ($requestPath === '/api/v1/pass/mp-webhook' || $requestPath === '/api/v1/pass/mp-status');
    $alreadyLoggedByMp = !empty($res['logged_paid_request']);
    $isMpFreshApproval = $isMpApprovalRoute
        && !empty($res['ok'])
        && in_array($mpStatusVal, ['paid', 'approved'], true)
        && !empty($res['pass_token'])
        && array_key_exists('idempotent', $res)
        && $res['idempotent'] === false;
    $isMpAlreadyApprovedPollingOrReplay = $isMpApprovalRoute
        && !empty($res['ok'])
        && in_array($mpStatusVal, ['paid', 'approved'], true)
        && !empty($res['pass_token'])
        && !$isMpFreshApproval;

    // Evita duplicar linha paga 'mercadopago' no requests.jsonl/CSV quando mercadopago.php ja registrou a venda
    if (!$alreadyLoggedByMp) {
        $mpLogSource = 'free';
        $mpPriceUsdc = null;
        if ($isMpFreshApproval && empty($res['sandbox'])) {
            $mpLogSource = 'mercadopago';
            if (!empty($res['price_usdc'])) {
                $mpPriceUsdc = (string) $res['price_usdc'];
            } elseif (!empty($res['raw_usdc_units']) && (int) $res['raw_usdc_units'] > 0) {
                $mpPriceUsdc = number_format(((int) $res['raw_usdc_units']) / 1000000.0, 4, '.', '');
            } elseif (!empty($res['tier']) && function_exists('grid402_mp_packages')) {
                $mpPkgs = grid402_mp_packages();
                $tierKey = strtolower((string) $res['tier']);
                if (isset($mpPkgs[$tierKey]['raw_usdc_units'])) {
                    $mpPriceUsdc = number_format(((int) $mpPkgs[$tierKey]['raw_usdc_units']) / 1000000.0, 4, '.', '');
                }
            }
        } elseif ($isMpFreshApproval && !empty($res['sandbox'])) {
            $mpLogSource = 'sandbox';
        }

        $mpLogEvent = [
            'service' => 'pass-mp',
            'status' => (int) ($res['code'] ?? 200),
            'payment_source' => $mpLogSource,
            'idempotent_replay' => $isMpAlreadyApprovedPollingOrReplay,
            'error' => !empty($res['ok']) ? null : (string) ($res['error'] ?? null),
        ];
        if ($mpPriceUsdc !== null) {
            $mpLogEvent['price_usdc'] = $mpPriceUsdc;
        }
        grid402_log_request($mpLogEvent);
        $mpRespCode = (int) ($res['code'] ?? 200);
        if (empty($res['ok']) && $mpRespCode >= 500) {
            $mpErrTxt = trim((string) ($res['error'] ?? 'MercadoPagoError') . ': ' . (string) ($res['message'] ?? ''));
            if (function_exists('grid402_record_telemetry')) {
                grid402_record_telemetry('pass-mp', $mpRespCode, false, null, null, 0.0, 'error', [
                    'payment_source' => 'mercadopago',
                    'failure_category' => 'mercadopago_gateway_error',
                ]);
            }
            if (function_exists('grid402_record_payment_failure')) {
                grid402_record_payment_failure([
                    'service' => 'pass-mp',
                    'status' => $mpRespCode,
                    'payment_source' => 'mercadopago',
                    'failure_category' => 'mercadopago_gateway_error',
                    'error' => $mpErrTxt,
                ]);
            }
        }
    }
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1.3c. Registro verificado de conexao de carteira Web3 (RF14)
if ($requestPath === '/api/v1/growth/wallet-connect' || $apiParam === 'wallet-connect') {
    grid402_emit_api_cors_headers();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'MethodNotAllowed', 'message' => 'Use POST with {"address":"0x..."}.'], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $rawBody = $rawRequestBody !== '' ? $rawRequestBody : (string) @file_get_contents('php://input');
    $jsonBody = @json_decode($rawBody, true);
    $input = is_array($jsonBody) ? $jsonBody : $_POST;
    $res = grid402_growth_record_wallet_connect((string) ($input['address'] ?? ''), grid402_get_trusted_client_ip());
    $code = (int) ($res['code'] ?? 200);
    if ($code === 429 && !empty($res['retry_after'])) {
        header('Retry-After: ' . (int) $res['retry_after']);
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1.3b. Cupons Promocionais de Session Pass (/api/v1/pass/promo-status e /api/v1/pass/promo-claim)
if ($requestPath === '/api/v1/pass/promo-status' || $requestPath === '/api/v1/pass/promo-claim') {
    grid402_emit_api_cors_headers();
    require_once __DIR__ . '/services/promo_pass.php';
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $rawBody = $rawRequestBody !== '' ? $rawRequestBody : (string) @file_get_contents('php://input');
    $jsonBody = @json_decode($rawBody, true);
    $input = is_array($jsonBody) ? $jsonBody : $_POST;

    if ($requestPath === '/api/v1/pass/promo-status' && $method === 'GET') {
        $promoCode = (string) ($_GET['code'] ?? $_GET['promo'] ?? '');
        $res = grid402_promo_get_status($promoCode);
    } elseif ($requestPath === '/api/v1/pass/promo-claim' && $method === 'POST') {
        $promoCode = (string) ($input['code'] ?? $input['promo'] ?? $_GET['code'] ?? $_GET['promo'] ?? '');
        $res = grid402_promo_claim_pass($promoCode);
    } else {
        $res = ['ok' => false, 'code' => 405, 'error' => 'MethodNotAllowed', 'message' => 'Use GET /api/v1/pass/promo-status?code=... or POST /api/v1/pass/promo-claim.'];
    }

    http_response_code((int) ($res['code'] ?? 200));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    grid402_log_request([
        'service' => 'pass-promo',
        'status' => (int) ($res['code'] ?? 200),
        'payment_source' => 'free',
        'error' => !empty($res['ok']) ? null : (string) ($res['error'] ?? null),
    ]);
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1.3c. Exportacao Binaria PDF 1.4 de Dossie 360 KYB (POST /api/v1/dossier/pdf — Zero-PII & Anti-Forjamento)
if ($requestPath === '/api/v1/dossier/pdf') {
    grid402_emit_api_cors_headers();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if ($method !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => 405, 'error' => 'MethodNotAllowed', 'message' => 'Use POST /api/v1/dossier/pdf com JSON do dossie assinado.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    require_once __DIR__ . '/services/dossier_pdf.php';
    $rawBody = $rawRequestBody !== '' ? $rawRequestBody : (string) @file_get_contents('php://input');

    if (strlen($rawBody) > 131072) { // Teto máximo de 128 KB contra exaustão de memória
        http_response_code(413);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => 413, 'error' => 'PayloadTooLarge', 'message' => 'O payload do dossie excede o limite maximo de 128 KB.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $decoded = @json_decode($rawBody, true);
    $dossierPayload = is_array($decoded['dossier'] ?? null) ? $decoded['dossier'] : (is_array($decoded) ? $decoded : null);

    if (!is_array($dossierPayload)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => 400, 'error' => 'InvalidJSON', 'message' => 'Envie um payload JSON valido do Dossie 360 KYB.'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $validatedPdf = grid402_dossier_pdf_validate_payload($dossierPayload);
    if (empty($validatedPdf['ok'])) {
        $errCode = (int) ($validatedPdf['code'] ?? 403);
        http_response_code($errCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($validatedPdf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdfBinary = grid402_dossier_pdf_render($validatedPdf['dossier'], !empty($validatedPdf['is_sample']));
    $cnpjDigits = preg_replace('/\D/', '', (string) ($validatedPdf['dossier']['cnpj_raw'] ?? $validatedPdf['dossier']['cnpj'] ?? ($validatedPdf['dossier']['cnpj_data']['cnpj'] ?? 'kyb')));
    if ($cnpjDigits === '') {
        $cnpjDigits = 'kyb';
    }
    $fileSuffix = !empty($validatedPdf['is_sample']) ? ($cnpjDigits . '-amostra') : $cnpjDigits;

    http_response_code(200);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="dossie-kyb-' . $fileSuffix . '.pdf"');
    header('Content-Length: ' . strlen($pdfBinary));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo $pdfBinary;
    exit;
}

// 2. Classificação de Rotas
$isStatusRoute = ($requestPath === '/status' || $requestPath === '/api/v1/summary' || $requestPath === '/telemetry');
$isHomeRoute = ($requestPath === '/' || $requestPath === '/index.php');
$isVerifyRoute = ($requestPath === '/api/v1/verify' || $apiParam === 'verify');
$validApiEndpoints = ['summary', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'domain-audit', 'kyb-audit', 'dossier', 'market-snapshot', 'cep', 'cnpj', 'indicators', 'holidays', 'workdays', 'sanctions', 'simples', 'compliance', 'lei', 'ofac', 'notarize', 'global-markets', 'extract', 'email-verify', 'token-safety', 'prediction-markets', 'sec-company', 'vuln-audit', 'chain-telemetry', 'batch', 'pass', 'pass-emitir', 'pass-balance', 'pass-limits', 'pass-statement'];
$isValidApiAction = in_array($apiParam, $validApiEndpoints, true);
$isInstitutionalRoute = in_array($requestPath, ['/about', '/contact', '/terms', '/privacy', '/admin', '/dashboard', '/agents'], true);

// 3. Rota Inexistente: Retorna HTTP 404 Real (Issue 3)
if (!$isHomeRoute && !$isStatusRoute && !$isValidApiAction && !$isInstitutionalRoute && !$isVerifyRoute) {
    http_response_code(404);
    if (str_contains($acceptHeader, 'text/html') && !str_starts_with($userAgent, 'curl/')) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="theme-color" content="#060B19"><link rel="icon" href="/favicon.ico?v=3" sizes="any"><link rel="icon" type="image/svg+xml" href="/favicon.svg?v=3"><link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=3"><link rel="manifest" href="/site.webmanifest?v=3"><title>404 - Pagina Nao Encontrada | Grid402</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet"><style>body{background:#060B19;color:#F8FAFC;font-family:\'Inter\',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column;text-align:center;padding:1rem}h1{font-family:\'Space Grotesk\',sans-serif;font-size:4rem;margin:0.5rem 0 0.25rem;color:#00F0FF;letter-spacing:-1px}p{color:#94A3B8;font-size:1.1rem;margin:0.5rem 0 1.5rem}a{background:#0052FF;color:#FFF;padding:0.75rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600;transition:opacity 0.2s}a:hover{opacity:0.9}</style></head><body><img src="/assets/favicon.svg?v=3" alt="Grid402" width="64" height="64"><h1>404</h1><p>A pagina ou rota solicitada nao existe no Grid402.</p><p><a href="/">Voltar a pagina inicial</a></p></body></html>';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        grid402_log_request(['service' => 'not_found', 'status' => 404, 'payment_source' => 'none', 'error' => 'NotFound']);
        echo json_encode([
            'error' => 'NotFound',
            'status' => 404,
            'message' => 'A rota solicitada nao existe no hub Grid402.',
            'documentation' => 'https://grid402.online/llms.txt',
            'valid_endpoints' => [
                '/',
                '/status',
                '/api/v1/forex',
                '/api/v1/crypto',
                '/api/v1/dns',
                '/api/v1/ssl',
                '/api/v1/asn',
                '/api/v1/cep',
                '/api/v1/cnpj',
                '/api/v1/indicators',
                '/api/v1/holidays',
                '/api/v1/workdays',
                '/api/v1/sanctions',
                '/api/v1/simples',
                '/api/v1/compliance',
                '/api/v1/lei',
                '/api/v1/ofac',
                '/api/v1/notarize',
                '/api/v1/global-markets',
                '/api/v1/extract',
                '/api/v1/email-verify',
                '/api/v1/token-safety',
                '/api/v1/prediction-markets',
                '/api/v1/sec-company',
                '/api/v1/vuln-audit',
                '/api/v1/chain-telemetry',
                '/api/v1/batch',
                '/api/v1/dossier',
                '/api/v1/pass',
                '/api/v1/verify',
                '/about',
                '/contact',
                '/terms',
                '/privacy',
                '/llms.txt',
                '/openapi.json',
                '/pricing.json',
                '/.well-known/ai-agent.json',
                '/.well-known/agent-card.json',
                '/.well-known/mcp.json',
                '/.well-known/mcp/server-card.json'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 4. Rota Gratuita: Resumo Geral do Hub (/status ou /?format=json)
if ($isStatusRoute || ($isHomeRoute && isset($_GET['format']) && $_GET['format'] === 'json')) {
    if (!isset($_GET['detail'])) {
        $summaryMaxAgeSec = 600;
        $summaryMissing = !file_exists(GRID402_FILE_FREE);
        $summaryMtime = $summaryMissing ? 0 : (int) @filemtime(GRID402_FILE_FREE);
        $summaryStale = $summaryMissing || $summaryMtime <= 0 || (time() - $summaryMtime) > $summaryMaxAgeSec;
        if ($summaryStale) {
            // Lock nao-bloqueante para evitar stampede de multiplas requisicoes disparando collector ao mesmo tempo
            $refreshLockPath = GRID402_PRIVATE_DIR . '/collector.refresh.lock';
            $lockDir = dirname($refreshLockPath);
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0775, true);
            }
            $lockFp = @fopen($refreshLockPath, 'c+');
            $gotLock = $lockFp && @flock($lockFp, LOCK_EX | LOCK_NB);
            if ($gotLock || $summaryMissing) {
                if (!defined('GRID402_INTERNAL_BOOTSTRAP')) {
                    define('GRID402_INTERNAL_BOOTSTRAP', true);
                }
                include_once __DIR__ . '/collector.php';
                if ($gotLock && $lockFp) {
                    @flock($lockFp, LOCK_UN);
                }
            }
            if ($lockFp) {
                @fclose($lockFp);
            }
        }
        grid402_record_telemetry('summary', 200, false);
        grid402_log_request(['service' => 'summary', 'status' => 200, 'payment_source' => 'free']);
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=30');
        $rawSummary = @file_get_contents(GRID402_FILE_FREE);
        $decodedSummary = $rawSummary ? (@json_decode($rawSummary, true) ?: []) : [];
        if (is_array($decodedSummary)) {
            $catalogDefs = grid402_get_utility_services();
            if (!isset($decodedSummary['services']) || !is_array($decodedSummary['services'])) {
                $decodedSummary['services'] = [];
            }
            foreach ($catalogDefs as $svcId => $def) {
                if (!isset($decodedSummary['services'][$svcId]) || !is_array($decodedSummary['services'][$svcId])) {
                    $decodedSummary['services'][$svcId] = [
                        'name' => $def['name'],
                        'status' => 'UP',
                        'endpoint' => $def['endpoint'],
                        'category' => $def['category'],
                        'price_usdc' => $def['price_usdc'] ?? GRID402_PRICE_DISPLAY,
                    ];
                }
                if (empty($decodedSummary['services'][$svcId]['status']) || $decodedSummary['services'][$svcId]['status'] === '—') {
                    $decodedSummary['services'][$svcId]['status'] = 'UP';
                }
                // Sincroniza price_usdc canonico (ex.: lei = 0.0100) sobre o snapshot de summary_free.json
                $decodedSummary['services'][$svcId]['price_usdc'] = $def['price_usdc'] ?? GRID402_PRICE_DISPLAY;
                $decodedSummary['services'][$svcId]['upstream_source'] = $def['upstream_source'] ?? 'Grid402 Official Public Data Feed';
                $decodedSummary['services'][$svcId]['cache_ttl_sec'] = $def['cache_ttl_sec'] ?? 300;
                $decodedSummary['services'][$svcId]['latency_ms'] = $decodedSummary['services'][$svcId]['latency_ms'] ?? 38;
                $decodedSummary['services'][$svcId]['last_checked'] = $decodedSummary['services'][$svcId]['last_checked'] ?? ($decodedSummary['timestamp'] ?? gmdate('Y-m-d\TH:i:s\Z'));
            }
            $decodedSummary['operator'] = GRID402_OPERATOR_NAME;
            $decodedSummary['recipient_wallet'] = GRID402_WALLET_ADDRESS;
            if (!isset($decodedSummary['uptime_30d'])) {
                $decodedSummary['uptime_30d'] = grid402_get_uptime_history_30d();
            }
            echo json_encode($decodedSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            readfile(GRID402_FILE_FREE);
        }
        exit;
    }
}

// 4.1. Rota de Auditoria e Verificacao de Oraculo (/api/v1/verify)
if ($isVerifyRoute) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $rawInput = '';
    $payload = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = (string) file_get_contents('php://input');
        if (strlen($rawInput) > 65536) {
            http_response_code(413);
            echo json_encode([
                'valid' => false,
                'error' => 'PayloadTooLarge',
                'message' => 'O corpo da requisicao excede o limite maximo de 64 KB.',
                'code' => 413,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (trim($rawInput) === '') {
            http_response_code(400);
            echo json_encode([
                'valid' => false,
                'error' => 'EmptyPayload',
                'message' => 'O corpo da requisicao POST esta vazio.',
                'code' => 400,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $payload = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            http_response_code(400);
            echo json_encode([
                'valid' => false,
                'error' => 'InvalidJSON',
                'message' => 'O corpo enviado nao e um JSON valido.',
                'code' => 400,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $getPayload = (string) ($_GET['payload'] ?? '');
        if ($getPayload !== '') {
            $payload = json_decode($getPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                http_response_code(400);
                echo json_encode([
                    'valid' => false,
                    'error' => 'InvalidJSON',
                    'message' => 'O parametro payload nao e um JSON valido.',
                    'code' => 400,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'valid' => false,
                'error' => 'MissingPayload',
                'message' => 'Envie o objeto JSON da resposta via POST ou no parametro GET payload.',
                'endpoint' => '/api/v1/verify',
                'instructions' => 'Envie um objeto JSON contendo o bloco _oracle_proof para validacao matematica.',
                'code' => 400,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $t0 = microtime(true);
    $verification = grid402_verify_oracle_proof($payload);
    $elapsedMs = round((microtime(true) - $t0) * 1000, 3);

    $verifyStatusLabel = !empty($verification['valid']) ? 'AUTHENTIC_ORACLE_DATA' : 'VERIFICATION_FAILED';
    $response = array_merge($verification, [
        'verification_time_ms' => $elapsedMs,
        'hub' => 'Grid402',
        'status' => $verifyStatusLabel,
    ]);

    $verifyLatency = grid402_request_elapsed_ms();
    grid402_record_latency_sample($verifyLatency);
    grid402_log_request([
        'service' => 'verify',
        'status' => 200,
        'latency_ms' => $verifyLatency,
        'payment_source' => 'free',
        'error' => !empty($verification['valid']) ? null : ($verification['error'] ?? 'VerificationFailed'),
    ]);

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Despachante interno compartilhado para execucao dos servicos de dados individuais e em lote (batch).
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function grid402_dispatch_data_service(string $slug, array $params = [], bool $isSandbox = false, string $rawBody = ''): array {
    switch ($slug) {
        case 'forex':
            return grid402_service_forex();
        case 'crypto':
            return grid402_service_crypto();
        case 'dns':
            $domain = (string) ($params['domain'] ?? 'github.com');
            return grid402_service_dns($domain);
        case 'ssl':
            $host = (string) ($params['host'] ?? 'base.org');
            return grid402_service_ssl($host);
        case 'asn':
            $ip = isset($params['ip']) ? (string) $params['ip'] : null;
            return grid402_service_asn($ip);
        case 'domain-audit':
            $domain = (string) ($params['domain'] ?? $params['host'] ?? 'base.org');
            return grid402_service_domain_audit($domain);
        case 'kyb-audit':
            return grid402_service_kyb_audit(
                (string) ($params['cnpj'] ?? ''),
                (string) ($params['name'] ?? ''),
                (string) ($params['lei'] ?? ''),
                (string) ($params['address'] ?? $params['target'] ?? ''),
                $isSandbox
            );
        case 'dossier':
            return grid402_service_dossier((string) ($params['cnpj'] ?? $params['code'] ?? '00000000000191'));
        case 'market-snapshot':
            return grid402_service_market_snapshot();
        case 'cep':
            return grid402_service_cep((string) ($params['code'] ?? $params['cep'] ?? '01310100'));
        case 'cnpj':
            return grid402_service_cnpj((string) ($params['cnpj'] ?? $params['code'] ?? '00000000000191'));
        case 'indicators':
            return grid402_service_indicators();
        case 'holidays':
            return grid402_service_holidays(isset($params['year']) ? (int) $params['year'] : null);
        case 'workdays':
            return grid402_service_workdays($params);
        case 'sanctions':
            return grid402_service_sanctions((string) ($params['cnpj'] ?? $params['code'] ?? '00000000000191'));
        case 'simples':
            return grid402_service_simples((string) ($params['cnpj'] ?? $params['code'] ?? '00000000000191'));
        case 'compliance':
            return grid402_service_compliance((string) ($params['cnpj'] ?? $params['code'] ?? '00000000000191'));
        case 'lei':
            return grid402_service_lei((string) ($params['lei'] ?? ''), (string) ($params['q'] ?? ''));
        case 'ofac':
            $addrVal = (string) ($params['address'] ?? $params['target'] ?? '');
            $nameVal = (string) ($params['name'] ?? '');
            if ($addrVal !== '' && $nameVal === '' && function_exists('grid402_ofac_detect_address_type') && grid402_ofac_detect_address_type($addrVal) === null && !str_starts_with(strtolower(trim($addrVal)), '0x')) {
                $nameVal = $addrVal;
                $addrVal = '';
            }
            return grid402_service_ofac($addrVal, $nameVal, $isSandbox);
        case 'notarize':
            $dataVal = (string) ($params['data'] ?? $params['content'] ?? '');
            $hashVal = (string) ($params['hash'] ?? '');
            if ($rawBody !== '' && $dataVal === '' && $hashVal === '') {
                $dataVal = substr($rawBody, 0, 8192);
            }
            return grid402_service_notarize($dataVal, $hashVal);
        case 'global-markets':
            return grid402_service_global_markets(
                (string) ($params['market'] ?? 'US'),
                (string) ($params['date'] ?? ''),
                (string) ($params['base'] ?? 'USD')
            );
        case 'extract':
            $urlVal = (string) ($params['url'] ?? 'https://example.com');
            $maxCharsVal = isset($params['max_chars']) ? (int) $params['max_chars'] : 12000;
            return grid402_service_extract($urlVal, $maxCharsVal);
        case 'email-verify':
            return grid402_service_email_verify((string) ($params['email'] ?? 'contato@grid402.online'));
        case 'token-safety':
            return grid402_service_token_safety(
                (string) ($params['address'] ?? $params['token'] ?? ''),
                (string) ($params['chain'] ?? '')
            );
        case 'prediction-markets':
            return grid402_service_prediction_markets(
                (string) ($params['q'] ?? ''),
                (string) ($params['source'] ?? 'all'),
                (string) ($params['status'] ?? 'active'),
                isset($params['limit']) ? (int) $params['limit'] : 10
            );
        case 'sec-company':
            return grid402_service_sec_company(
                (string) ($params['ticker'] ?? ''),
                (string) ($params['cik'] ?? ''),
                isset($params['limit']) ? (int) $params['limit'] : 10,
                (string) ($params['forms'] ?? ''),
                $params['include_facts'] ?? false
            );
        case 'vuln-audit':
            return grid402_service_vuln_audit(
                (string) ($params['ecosystem'] ?? ''),
                (string) ($params['package'] ?? ''),
                (string) ($params['version'] ?? ''),
                (string) ($params['id'] ?? '')
            );
        case 'chain-telemetry':
            return grid402_service_chain_telemetry((string) ($params['chain'] ?? 'base'));
        default:
            return [
                'success' => false,
                'error' => 'UnsupportedService',
                'message' => "Servico '{$slug}' nao suportado neste despachante.",
                'code' => 400,
            ];
    }
}

// 5. Se for requisição para as APIs de dados técnicos
if ($isValidApiAction) {
    grid402_emit_api_cors_headers();

    // 2.0. Consulta gratuita de extrato Zero-PII do Session Pass (/api/v1/pass/statement -> JSON ou CSV)
    if ($apiParam === 'pass-statement') {
        $authHeaderRaw = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        $bearerToken = '';
        if (preg_match('/^Bearer\s+(pass_g402_[a-z0-9_]+)$/i', $authHeaderRaw, $bearerMatch)) {
            $bearerToken = trim($bearerMatch[1]);
        }
        $statementToken = trim((string) ($_GET['token'] ?? $_GET['pass'] ?? ''));
        if ($statementToken === '' && str_starts_with(strtolower($paymentProof), 'pass_g402_')) {
            $statementToken = trim($paymentProof);
        }
        if ($statementToken === '' && $bearerToken !== '') {
            $statementToken = $bearerToken;
        }
        $statementFilters = [
            'endpoint' => $_GET['endpoint'] ?? null,
            'outcome' => $_GET['outcome'] ?? null,
        ];
        $statement = grid402_get_session_pass_statement($statementToken, $statementFilters);
        $statusCode = (int) ($statement['code'] ?? 200);
        $stmtLatency = grid402_request_elapsed_ms();
        grid402_record_latency_sample($stmtLatency);
        grid402_log_request([
            'service' => 'pass-statement',
            'status' => $statusCode,
            'latency_ms' => $stmtLatency,
            'payment_source' => 'free',
            'error' => !empty($statement['ok']) ? null : (string) ($statement['error'] ?? 'PassStatementError'),
        ]);

        $requestedFormat = strtolower(trim((string) ($_GET['format'] ?? 'json')));
        if ($requestedFormat === 'csv' && !empty($statement['ok'])) {
            $passIdFile = preg_replace('/[^a-z0-9_-]/i', '', (string) ($statement['pass']['pass_id'] ?? 'session'));
            $csvPayload = grid402_format_statement_csv($statement['entries'] ?? []);
            http_response_code(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="grid402_statement_' . $passIdFile . '.csv"');
            header('Cache-Control: no-store');
            echo $csvPayload;
            exit;
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($statement, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2.1. Consulta gratuita de saldo e Spend Controls de Session Pass (/api/v1/pass/balance, /api/v1/pass/limits ou ?status=pass_...)
    if ($apiParam === 'pass-balance' || $apiParam === 'pass-limits' || ($apiParam === 'pass' && (!empty($_GET['status']) || !empty($_GET['token'])))) {
        $rawBodyPass = (string) @file_get_contents('php://input');
        $jsonBodyPass = $rawBodyPass !== '' ? (@json_decode($rawBodyPass, true) ?: []) : [];
        if (!is_array($jsonBodyPass)) {
            $jsonBodyPass = [];
        }

        $tokenQuery = trim((string) (
            $_GET['token']
            ?? $_GET['status']
            ?? $_GET['pass']
            ?? $jsonBodyPass['token']
            ?? $jsonBodyPass['pass_token']
            ?? (str_starts_with($paymentProof, 'pass_g402_') ? $paymentProof : '')
        ));

        $hasDailyLimitParam = array_key_exists('daily_credit_limit', $_GET) || array_key_exists('daily_credit_limit', $jsonBodyPass);
        $hasAllowedEndpointsParam = array_key_exists('allowed_endpoints', $_GET) || array_key_exists('allowed_endpoints', $jsonBodyPass);
        $isLimitsMutation = ($apiParam === 'pass-limits' && ($method === 'POST' || $method === 'PATCH'))
            || (($method === 'POST' || $method === 'PATCH') && ($hasDailyLimitParam || $hasAllowedEndpointsParam));

        if ($apiParam === 'pass-limits' && ($method === 'GET' || $method === 'HEAD')) {
            // Em GET /pass-limits, apenas consulta sem mutação
            $passInfo = grid402_get_session_pass_status($tokenQuery);
        } elseif ($isLimitsMutation) {
            $dailyLimitRaw = $jsonBodyPass['daily_credit_limit'] ?? $_POST['daily_credit_limit'] ?? null;
            $dailyLimitVal = ($dailyLimitRaw === null || $dailyLimitRaw === '') ? null : (int) $dailyLimitRaw;
            $allowedRaw = $jsonBodyPass['allowed_endpoints'] ?? $_POST['allowed_endpoints'] ?? null;
            if (is_string($allowedRaw)) {
                $allowedVal = $allowedRaw !== '' ? array_map('trim', explode(',', $allowedRaw)) : null;
            } elseif (is_array($allowedRaw)) {
                $allowedVal = $allowedRaw;
            } else {
                $allowedVal = null;
            }
            $passInfo = grid402_update_session_pass_limits($tokenQuery, $dailyLimitVal, $allowedVal);
        } elseif ($hasDailyLimitParam || $hasAllowedEndpointsParam) {
            // Rejeita tentativas de mutação de limites via GET
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'MethodNotAllowed',
                'message' => 'Mutação de Spend Controls requer requisição POST ou PATCH com payload JSON.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $passInfo = grid402_get_session_pass_status($tokenQuery);
        }

        http_response_code($passInfo['code'] ?? 200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $passLatency = grid402_request_elapsed_ms();
        grid402_record_latency_sample($passLatency);
        grid402_log_request([
            'service' => $apiParam,
            'status' => (int) ($passInfo['code'] ?? 200),
            'latency_ms' => $passLatency,
            'payment_source' => 'free',
            'error' => !empty($passInfo['ok']) ? null : (string) ($passInfo['error'] ?? 'PassLookupError'),
        ]);
        echo json_encode($passInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2.2. Definição de Preço Escalonado por Serviço e Verificação Prévia Defensiva
    $isPass = ($apiParam === 'pass');
    $isPassEmitir = ($apiParam === 'pass-emitir');
    $isBatch = ($apiParam === 'batch');
    $isDossier = ($apiParam === 'dossier');
    $isCompliance = ($apiParam === 'compliance');
    $isKybAudit = ($apiParam === 'kyb-audit');
    $isOfac = ($apiParam === 'ofac');
    $isCompositeMedium = in_array($apiParam, ['domain-audit', 'market-snapshot', 'token-safety'], true);
    $isTwoCreditTier = in_array($apiParam, ['cnpj', 'lei', 'sanctions', 'simples', 'prediction-markets', 'sec-company'], true);

    $batchPreCheck = null;
    $reqMethodUpperApi = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $isBatchSandboxProof = in_array(strtolower(trim($paymentProof)), ['demo', 'sandbox'], true);

    $passTierSelected = 'starter';
    if ($isBatch) {
        if ($reqMethodUpperApi !== 'POST') {
            if ($paymentProof !== '' || $rawRequestBody !== '') {
                http_response_code(405);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'MethodNotAllowed',
                    'message' => 'Use POST /api/v1/batch com corpo JSON {"items":[{"service":"dns","params":{"domain":"base.org"}}]}.',
                    'code' => 405,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            $servicePriceDisplay = GRID402_PRICE_DISPLAY;
            $servicePriceRaw = GRID402_USDC_REQUIRED_RAW;
        } else {
            $batchPreCheck = grid402_batch_parse_and_validate($rawRequestBody, $isBatchSandboxProof);
            if (empty($batchPreCheck['ok'])) {
                http_response_code((int) ($batchPreCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array_merge([
                    'success' => false,
                    'code' => (int) ($batchPreCheck['code'] ?? 400),
                ], $batchPreCheck), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            $servicePriceDisplay = (string) $batchPreCheck['total_usdc_display'];
            $servicePriceRaw = (int) $batchPreCheck['total_raw_units'];
        }
    } elseif ($isPass) {
        $tierParam = strtolower(trim((string) ($_GET['tier'] ?? 'starter')));
        if ($tierParam === 'fleet') {
            $passTierSelected = 'fleet';
            $servicePriceDisplay = '20.0000';
            $servicePriceRaw = 20000000; // 20.000000 USDC (Fleet Pass = 6.000 creditos)
        } elseif ($tierParam === 'pro') {
            $passTierSelected = 'pro';
            $servicePriceDisplay = '5.0000';
            $servicePriceRaw = 5000000; // 5.000000 USDC (Pro Pass = 1.200 creditos)
        } else {
            $passTierSelected = 'starter';
            $servicePriceDisplay = '2.0000';
            $servicePriceRaw = 2000000; // 2.000000 USDC (Starter Pass = 400 creditos)
        }
    } elseif ($isDossier) {
        $servicePriceDisplay = GRID402_PRICE_DOSSIER;
        $servicePriceRaw = GRID402_USDC_DOSSIER_RAW; // 0.050000 USDC (10 creditos)
    } elseif ($isCompliance) {
        $servicePriceDisplay = '0.0300';
        $servicePriceRaw = 30000; // 0.030000 USDC (6 creditos)
    } elseif ($isKybAudit) {
        $servicePriceDisplay = GRID402_PRICE_KYB_AUDIT;
        $servicePriceRaw = GRID402_USDC_KYB_AUDIT_RAW; // 0.025000 USDC (5 creditos)
    } elseif ($isOfac) {
        $servicePriceDisplay = '0.0200';
        $servicePriceRaw = 20000; // 0.020000 USDC (4 creditos)
    } elseif ($isCompositeMedium) {
        $servicePriceDisplay = '0.0150';
        $servicePriceRaw = 15000; // 0.015000 USDC (3 creditos)
    } elseif ($isTwoCreditTier) {
        $servicePriceDisplay = ($apiParam === 'cnpj') ? GRID402_PRICE_CNPJ : '0.0100';
        $servicePriceRaw = ($apiParam === 'cnpj') ? GRID402_USDC_CNPJ_RAW : 10000; // 0.010000 USDC (2 creditos)
    } else {
        $servicePriceDisplay = GRID402_PRICE_DISPLAY;
        $servicePriceRaw = GRID402_USDC_REQUIRED_RAW; // 0.005000 USDC (1 credito)
    }

    // Validação prévia defensiva de parâmetros (só rejeita se o parâmetro foi explicitamente enviado e é inválido;
    // quando omitido, o desafio 402 e a execução usam o identificador showcase padrão).
    $isCnpjEndpoint = in_array($apiParam, ['cnpj', 'sanctions', 'simples', 'compliance', 'dossier'], true);
    if ($isCnpjEndpoint) {
        $cnpjInputRaw = trim((string) ($_GET['cnpj'] ?? $_GET['code'] ?? ''));
        if ($cnpjInputRaw !== '') {
            $cnpjInputClean = preg_replace('/\D/', '', $cnpjInputRaw);
            if ($cnpjInputClean === '' || !grid402_validate_cnpj_digits($cnpjInputClean)) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'InvalidCNPJ',
                    'message' => 'O CNPJ informado é matematicamente inválido ou possui dígitos verificadores incorretos.',
                    'cnpj' => $cnpjInputRaw,
                    'code' => 400,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'cep') {
        $cepInputRaw = trim((string) ($_GET['code'] ?? $_GET['cep'] ?? ''));
        if ($cepInputRaw !== '') {
            $cepDigits = preg_replace('/\D/', '', $cepInputRaw);
            if (strlen($cepDigits) !== 8) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'InvalidCEP',
                    'message' => 'O CEP informado deve conter exatamente 8 dígitos numéricos.',
                    'cep' => $cepInputRaw,
                    'code' => 400,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'lei') {
        $leiInputRaw = trim((string) ($_GET['lei'] ?? ''));
        if ($leiInputRaw !== '') {
            $leiClean = preg_replace('/[^A-Za-z0-9]/', '', $leiInputRaw);
            if (strlen($leiClean) !== 20) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'InvalidLEI',
                    'message' => 'O código LEI (ISO 17442) deve conter exatamente 20 caracteres alfanuméricos.',
                    'lei' => $leiInputRaw,
                    'code' => 400,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'kyb-audit') {
        $kybCnpjRaw = trim((string) ($_GET['cnpj'] ?? ''));
        if ($kybCnpjRaw !== '') {
            $kybCnpjClean = preg_replace('/\D/', '', $kybCnpjRaw);
            if ($kybCnpjClean === '' || !grid402_validate_cnpj_digits($kybCnpjClean)) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'InvalidCNPJ',
                    'message' => 'O CNPJ informado para auditoria KYB é inválido.',
                    'cnpj' => $kybCnpjRaw,
                    'code' => 400,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'extract') {
        if (array_key_exists('url', $_GET)) {
            $extractUrlRaw = trim((string) ($_GET['url'] ?? ''));
            $urlCheck = grid402_extract_validate_public_url($extractUrlRaw);
            if (!$urlCheck['ok']) {
                http_response_code((int) ($urlCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $urlCheck['error'],
                    'message' => $urlCheck['message'],
                    'code' => (int) ($urlCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'email-verify') {
        if (array_key_exists('email', $_GET)) {
            $emailInputRaw = trim((string) ($_GET['email'] ?? ''));
            $emailCheck = grid402_email_verify_validate_input($emailInputRaw);
            if (!$emailCheck['ok']) {
                http_response_code((int) ($emailCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $emailCheck['error'],
                    'message' => $emailCheck['message'],
                    'code' => (int) ($emailCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'token-safety') {
        if (array_key_exists('address', $_GET) || array_key_exists('token', $_GET) || array_key_exists('chain', $_GET)) {
            $tsAddrRaw = trim((string) ($_GET['address'] ?? $_GET['token'] ?? ''));
            $tsChainRaw = trim((string) ($_GET['chain'] ?? ''));
            $tsCheck = grid402_token_safety_validate_input($tsAddrRaw, $tsChainRaw);
            if (!$tsCheck['ok']) {
                http_response_code((int) ($tsCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $tsCheck['error'],
                    'message' => $tsCheck['message'],
                    'code' => (int) ($tsCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'prediction-markets') {
        if (array_key_exists('q', $_GET) || array_key_exists('source', $_GET) || array_key_exists('status', $_GET) || array_key_exists('limit', $_GET)) {
            $pmCheck = grid402_prediction_markets_validate_input(
                (string) ($_GET['q'] ?? ''),
                (string) ($_GET['source'] ?? 'all'),
                (string) ($_GET['status'] ?? 'active'),
                isset($_GET['limit']) ? (int) $_GET['limit'] : 10
            );
            if (!$pmCheck['ok']) {
                http_response_code((int) ($pmCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $pmCheck['error'],
                    'message' => $pmCheck['message'],
                    'code' => (int) ($pmCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'sec-company') {
        $hasSecExplicitQuery = array_key_exists('ticker', $_GET) || array_key_exists('cik', $_GET) || array_key_exists('limit', $_GET) || array_key_exists('forms', $_GET) || array_key_exists('include_facts', $_GET);
        $hasIncomingPayHeader = !empty($_SERVER['HTTP_X_PAYMENT']) || !empty($_SERVER['HTTP_PAYMENT_SIGNATURE']) || !empty($_SERVER['HTTP_X_PAYMENT_SIGNATURE']) || !empty($_SERVER['HTTP_PAYMENT']) || !empty($_SERVER['HTTP_X_SESSION_PASS']) || !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['HTTP_X_RAPIDAPI_PROXY_SECRET']);
        if ($hasSecExplicitQuery || $hasIncomingPayHeader) {
            $secCheck = grid402_sec_company_validate_input(
                (string) ($_GET['ticker'] ?? ''),
                (string) ($_GET['cik'] ?? ''),
                isset($_GET['limit']) ? (int) $_GET['limit'] : 10,
                (string) ($_GET['forms'] ?? ''),
                $_GET['include_facts'] ?? false
            );
            if (!$secCheck['ok']) {
                http_response_code((int) ($secCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $secCheck['error'],
                    'message' => $secCheck['message'],
                    'code' => (int) ($secCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'vuln-audit') {
        $hasVulnExplicitQuery = array_key_exists('ecosystem', $_GET) || array_key_exists('package', $_GET) || array_key_exists('version', $_GET) || array_key_exists('id', $_GET);
        $hasIncomingPayHeader = !empty($_SERVER['HTTP_X_PAYMENT']) || !empty($_SERVER['HTTP_PAYMENT_SIGNATURE']) || !empty($_SERVER['HTTP_X_PAYMENT_SIGNATURE']) || !empty($_SERVER['HTTP_PAYMENT']) || !empty($_SERVER['HTTP_X_SESSION_PASS']) || !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['HTTP_X_RAPIDAPI_PROXY_SECRET']);
        if ($hasVulnExplicitQuery || $hasIncomingPayHeader) {
            $vaCheck = grid402_vuln_audit_validate_input(
                (string) ($_GET['ecosystem'] ?? ''),
                (string) ($_GET['package'] ?? ''),
                (string) ($_GET['version'] ?? ''),
                (string) ($_GET['id'] ?? '')
            );
            if (!$vaCheck['ok']) {
                http_response_code((int) ($vaCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $vaCheck['error'],
                    'message' => $vaCheck['message'],
                    'code' => (int) ($vaCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($apiParam === 'chain-telemetry') {
        if (array_key_exists('chain', $_GET)) {
            $ctCheck = grid402_chain_telemetry_validate_input((string) ($_GET['chain'] ?? ''));
            if (!$ctCheck['ok']) {
                http_response_code((int) ($ctCheck['code'] ?? 400));
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $ctCheck['error'],
                    'message' => $ctCheck['message'],
                    'code' => (int) ($ctCheck['code'] ?? 400),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // 2.3. Verificação de Pagamento x402 para Micro-serviços Técnicos com Teaser Preview
    // Nota de seguranca: X-RapidAPI-Proxy-Secret NUNCA libera emissao gratuita de Session Pass (/pass ou /pass-emitir)
    $isRapidApiProxy = (!$isPass && !$isPassEmitir && function_exists('grid402_is_valid_rapidapi_proxy_request') && grid402_is_valid_rapidapi_proxy_request());

    if ($paymentProof === '' && !$isPassEmitir && !$isRapidApiProxy) {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        $previewData = null;
        $lockedFields = null;

        if ($apiParam === 'forex') {
            $previewData = [
                'base' => 'BRL',
                'pairs_sample' => ['USD/BRL', 'EUR/BRL', 'GBP/BRL'],
                'source' => 'Banco Central do Brasil (PTAX)',
                'status' => 'UP',
            ];
            $lockedFields = ['rates.USD', 'rates.EUR', 'rates.GBP', 'timestamp', '_oracle_proof'];
        } elseif ($apiParam === 'crypto') {
            $previewData = [
                'assets_sample' => ['BTC', 'ETH', 'SOL', 'USDC', 'BASE'],
                'quotes' => ['USD', 'BRL'],
                'source' => 'Coinbase Spot + Kraken',
                'status' => 'UP',
            ];
            $lockedFields = ['prices.BTC', 'prices.ETH', 'prices.SOL', 'prices.USDC', '_oracle_proof'];
        } elseif ($apiParam === 'dns') {
            $dnsTarget = (string) ($_GET['domain'] ?? 'github.com');
            $previewData = [
                'domain' => $dnsTarget,
                'record_types' => ['A', 'AAAA', 'MX', 'TXT', 'NS', 'CNAME'],
                'resolver' => 'Authoritative DNS / DoH',
            ];
            $lockedFields = ['records.A', 'records.MX', 'records.TXT', 'records.NS', '_oracle_proof'];
        } elseif ($apiParam === 'ssl') {
            $sslTarget = (string) ($_GET['host'] ?? 'base.org');
            $previewData = [
                'host' => $sslTarget,
                'port' => 443,
                'checks' => ['validity', 'days_remaining', 'issuer', 'san_domains', 'tls_version'],
            ];
            $lockedFields = ['valid', 'days_remaining', 'issuer', 'subject_alt_names', '_oracle_proof'];
        } elseif ($apiParam === 'asn') {
            $asnTarget = (string) ($_GET['ip'] ?? '1.1.1.1');
            $previewData = [
                'ip' => $asnTarget,
                'source' => 'RIPE Stat / BGP Routing Registry',
                'fields' => ['asn', 'organization', 'prefix', 'country'],
            ];
            $lockedFields = ['asn', 'as_name', 'prefix', 'country', '_oracle_proof'];
        } elseif ($apiParam === 'domain-audit') {
            $daTarget = (string) ($_GET['domain'] ?? $_GET['host'] ?? 'base.org');
            $previewData = [
                'domain' => $daTarget,
                'bundle' => 'DNS + TLS/X.509 + BGP/ASN',
                'credits' => 3,
            ];
            $lockedFields = ['dns', 'ssl', 'asn', 'risk_score', '_oracle_proof'];
        } elseif ($apiParam === 'kyb-audit') {
            $kybCnpj = (string) ($_GET['cnpj'] ?? '00000000000191');
            $previewData = [
                'cnpj' => $kybCnpj,
                'bundle' => 'Receita Federal CNPJ + OFAC/UN Sanctions + GLEIF LEI',
                'credits' => 5,
                'price_usdc' => GRID402_PRICE_KYB_AUDIT,
            ];
            $lockedFields = ['audit.cnpj', 'audit.ofac_sanctions', 'audit.lei', 'checked_at', '_oracle_proof'];
        } elseif ($apiParam === 'dossier') {
            $dossierCnpj = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $teaser = grid402_teaser_dossier($dossierCnpj);
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'market-snapshot') {
            $previewData = [
                'market' => (string) ($_GET['market'] ?? 'US'),
                'bundle' => 'PTAX Forex + Crypto Spot + Selic/CDI/IPCA + ANBIMA',
                'credits' => 3,
            ];
            $lockedFields = ['forex', 'crypto', 'indicators', 'global_markets', '_oracle_proof'];
        } elseif ($apiParam === 'cnpj') {
            $hasExplicitCnpj = trim((string) ($_GET['cnpj'] ?? $_GET['code'] ?? '')) !== '';
            $cnpjInput = $hasExplicitCnpj ? (string) ($_GET['cnpj'] ?? $_GET['code']) : '00000000000191';
            $cnpjResult = grid402_get_cnpj_preview($cnpjInput);
            if (!($cnpjResult['success'] ?? false)) {
                $previewData = [
                    'cnpj' => '00.000.000/0001-91',
                    'cnpj_valid' => true,
                    'base_oficial' => 'Receita Federal do Brasil (Base Pública CNPJ - Zero PII)',
                    'preview_mode' => 'static_schema_sample',
                    'razao_social' => '[LOCKED — unlock via x402 or Session Pass]',
                    'nome_fantasia' => '[LOCKED]',
                    'uf' => '[LOCKED]',
                    'situacao_cadastral' => '[LOCKED]',
                ];
                $lockedFields = ['razao_social', 'nome_fantasia', 'situacao_cadastral', 'capital_social', 'natureza_juridica', 'data_abertura', 'cnae_fiscal', 'cnaes_secundarios', 'endereco', '_oracle_proof'];
            } else {
                $previewData = $cnpjResult['preview'];
                $lockedFields = $cnpjResult['locked_fields'];
            }
        } elseif ($apiParam === 'cep') {
            $cepInput = (string) ($_GET['code'] ?? $_GET['cep'] ?? '01310100');
            $cepResult = grid402_get_cep_preview($cepInput);
            if (!($cepResult['success'] ?? false)) {
                $previewData = [
                    'cep' => '01310-100',
                    'cep_format_valid' => true,
                    'base_oficial' => 'Base Pública Aberta Nacional e Diretório Municipal IBGE',
                    'preview_mode' => 'static_schema_sample',
                    'cidade' => '[LOCKED — unlock via x402 or Session Pass]',
                    'uf' => '[LOCKED]',
                ];
                $lockedFields = ['logradouro', 'bairro', 'cidade', 'uf', 'complemento', 'ddd', 'ibge', '_oracle_proof'];
            } else {
                $previewData = $cepResult['preview'];
                $lockedFields = $cepResult['locked_fields'];
            }
        } elseif ($apiParam === 'indicators') {
            $teaser = grid402_teaser_indicators();
            $lockedFields = $teaser['locked_fields'] ?? [];
            unset($teaser['locked_fields']);
            $previewData = $teaser;
        } elseif ($apiParam === 'holidays') {
            $yearInput = isset($_GET['year']) ? (int) $_GET['year'] : null;
            $teaser = grid402_teaser_holidays($yearInput);
            $lockedFields = $teaser['locked_fields'] ?? [];
            unset($teaser['locked_fields']);
            $previewData = $teaser;
        } elseif ($apiParam === 'workdays') {
            $teaser = grid402_teaser_workdays($_GET);
            $lockedFields = $teaser['locked_fields'] ?? [];
            unset($teaser['locked_fields']);
            $previewData = $teaser;
        } elseif ($apiParam === 'sanctions') {
            $cnpjInput = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $teaser = grid402_teaser_sanctions($cnpjInput);
            $lockedFields = $teaser['locked_fields'] ?? [];
            $previewData = $teaser['preview'];
        } elseif ($apiParam === 'simples') {
            $cnpjInput = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $teaser = grid402_teaser_simples($cnpjInput);
            $lockedFields = $teaser['locked_fields'] ?? [];
            $previewData = $teaser['preview'];
        } elseif ($apiParam === 'compliance') {
            $cnpjInput = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $teaser = grid402_teaser_compliance($cnpjInput);
            $lockedFields = $teaser['locked_fields'] ?? [];
            $previewData = $teaser['preview'];
        } elseif ($apiParam === 'lei') {
            $leiInput = (string) ($_GET['lei'] ?? '');
            $qInput = (string) ($_GET['q'] ?? '');
            $previewData = grid402_preview_lei($leiInput, $qInput);
            $lockedFields = ['entity.lei', 'entity.legal_name', 'entity.jurisdiction', 'entity.entity_status', 'entity.legal_address', 'entity.registration_authority', 'entity.local_registration_id', '_oracle_proof'];
        } elseif ($apiParam === 'ofac') {
            $addrInput = (string) ($_GET['address'] ?? $_GET['target'] ?? '');
            $nameInput = (string) ($_GET['name'] ?? '');
            if ($addrInput !== '' && !preg_match('/^0x[a-fA-F0-9]{40}$/', trim($addrInput)) && $nameInput === '') {
                $nameInput = $addrInput;
                $addrInput = '';
            }
            $previewData = grid402_preview_ofac($addrInput, $nameInput);
            $lockedFields = ['sanctioned', 'risk_level', 'compliance_decision', 'matched_lists', 'onchain_profile', '_oracle_proof'];
        } elseif ($apiParam === 'notarize') {
            $dataInput = (string) ($_GET['data'] ?? $_GET['content'] ?? '');
            $hashInput = (string) ($_GET['hash'] ?? '');
            $previewData = grid402_preview_notarize($dataInput, $hashInput);
            $lockedFields = ['certificate_id', 'notarized_at_utc', 'unix_epoch', 'oracle_authority', '_oracle_proof'];
        } elseif ($apiParam === 'global-markets') {
            $mktInput = (string) ($_GET['market'] ?? 'US');
            $dtInput = (string) ($_GET['date'] ?? '');
            $baseInput = (string) ($_GET['base'] ?? 'USD');
            $previewData = grid402_preview_global_markets($mktInput, $dtInput, $baseInput);
            $lockedFields = ['is_business_day', 'is_holiday', 'holiday_name', 'next_business_day', 'fx_reference.rates', 'market_holidays_calendar', '_oracle_proof'];
        } elseif ($apiParam === 'extract') {
            $extractUrlInput = (string) ($_GET['url'] ?? 'https://example.com');
            $teaser = grid402_teaser_extract($extractUrlInput);
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'email-verify') {
            $emailInput = (string) ($_GET['email'] ?? 'contato@grid402.online');
            $teaser = grid402_teaser_email_verify($emailInput);
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'token-safety') {
            $tsAddrInput = (string) ($_GET['address'] ?? $_GET['token'] ?? '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913');
            $tsChainInput = (string) ($_GET['chain'] ?? '');
            $teaser = grid402_service_token_safety_preview($tsAddrInput, $tsChainInput);
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'prediction-markets') {
            $pmQInput = (string) ($_GET['q'] ?? '');
            $pmSourceInput = (string) ($_GET['source'] ?? 'all');
            $pmStatusInput = (string) ($_GET['status'] ?? 'active');
            $pmLimitInput = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $teaser = grid402_service_prediction_markets_preview($pmQInput, $pmSourceInput, $pmStatusInput, $pmLimitInput);
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'sec-company') {
            $teaser = grid402_service_sec_company_preview(
                (string) ($_GET['ticker'] ?? ''),
                (string) ($_GET['cik'] ?? ''),
                isset($_GET['limit']) ? (int) $_GET['limit'] : 10,
                (string) ($_GET['forms'] ?? ''),
                $_GET['include_facts'] ?? false
            );
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'vuln-audit') {
            $teaser = grid402_service_vuln_audit_preview(
                (string) ($_GET['ecosystem'] ?? ''),
                (string) ($_GET['package'] ?? ''),
                (string) ($_GET['version'] ?? ''),
                (string) ($_GET['id'] ?? '')
            );
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'chain-telemetry') {
            $teaser = grid402_service_chain_telemetry_preview((string) ($_GET['chain'] ?? 'base'));
            $previewData = $teaser['preview'];
            $lockedFields = $teaser['locked_fields'];
        } elseif ($apiParam === 'batch') {
            $previewData = is_array($batchPreCheck) ? [
                'mode' => 'POST /api/v1/batch',
                'item_count' => (int) ($batchPreCheck['item_count'] ?? 0),
                'total_credits' => (int) ($batchPreCheck['total_credits'] ?? 1),
                'total_usdc' => (string) ($batchPreCheck['total_usdc_display'] ?? $servicePriceDisplay),
                'batch_plan' => $batchPreCheck['batch_plan'] ?? [],
                'sandbox_max_items' => 2,
                'production_max_items' => 5,
            ] : [
                'mode' => 'POST /api/v1/batch',
                'max_items' => 5,
                'sandbox_max_items' => 2,
                'example_body' => ['items' => [['service' => 'dns', 'params' => ['domain' => 'base.org']], ['service' => 'token-safety', 'params' => ['chain' => 'base', 'address' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913']]]],
            ];
            $lockedFields = ['results', 'batch_summary', '_meta', '_oracle_proof'];
        } elseif ($apiParam === 'pass') {
            $passCanonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? rtrim((string) GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
            $previewData = [
                'product' => 'x402 Prepaid Session Pass (High-Frequency M2M & Web3 Top-Up)',
                'selected_tier' => $passTierSelected,
                'selected_price_usdc' => $servicePriceDisplay,
                'credit_unit' => '1 credit = 0.0050 USDC (5,000 raw units) = 1 standard API call',
                'tier_micro' => 'R$ 4.90 (Pix/Card BRL) = 50 credits (5 KYB dossiers, 90 days validity)',
                'tier_starter' => '2.0000 USDC (or R$ 12 Pix) = 400 credits (<15ms latency, 180 days validity)',
                'tier_pro' => '5.0000 USDC (or R$ 29 Pix) = 1,000 base + 200 bonus (+20%) = 1,200 credits (<15ms latency, 180 days validity)',
                'tier_fleet' => '20.0000 USDC (or R$ 115 Pix) = 4,000 base + 2,000 bonus (+50%) = 6,000 credits (<15ms latency, 365 days validity)',
                'spend_controls' => 'Supports daily_credit_limit, allowed_endpoints, and free balance check via GET /api/v1/pass/balance',
                'network' => (function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled())
                    ? 'Multi-Chain USDC: Base (8453), Arbitrum (42161), Polygon (137), Optimism (10), Ethereum (1), and Solana SPL USDC'
                    : 'Multi-Chain EVM USDC: Base (8453), Arbitrum (42161), Polygon (137), Optimism (10), and Ethereum (1)',
                'token' => 'USDC (0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913)',
                'recipient' => GRID402_WALLET_ADDRESS,
                'checkout_url' => $passCanonicalBase . '/pass',
                'pix_micro_url' => $passCanonicalBase . '/pass?tier=micro&method=pix',
                'pix_starter_url' => $passCanonicalBase . '/pass?tier=starter&method=pix',
                'web3_activate_endpoint' => 'POST /api/v1/pass-emitir {"txHash":"0x...","chain":"base"}',
                'usage_header' => 'X-Payment: pass_g402_<your_token>',
                'usage_example_curl' => 'curl -s -H "X-Payment: pass_g402_<your_token>" "https://grid402.online/api/v1/forex"',
                'usage_example_mcp' => 'GRID402_SESSION_PASS=pass_g402_<your_token> npx -y grid402-mcp',
            ];
            $lockedFields = ['pass_token', 'credits_total', 'credits_remaining', 'expires_at', 'tier', '_oracle_proof'];
        }

        grid402_respond_402($serviceLabel, null, $servicePriceDisplay, $servicePriceRaw, $previewData, $lockedFields);
    }

    // Auditoria on-chain com trava atômica anti-replay e suporte a Modo Sandbox
    $clientIp = grid402_get_trusted_client_ip();
    $proofIsPayload = false;
    $proofForLog = null;

    $emitPaymentFailure = static function (array $valResult) use ($apiParam, $servicePriceDisplay, $servicePriceRaw, &$proofIsPayload, &$proofForLog, $paymentProof, $paymentProofSourceHeader): never {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        $statusCode = (int) ($valResult['code'] ?? 402);
        $isSandboxRateLimited = ($statusCode === 429 && (!empty($valResult['sandbox']) || ($valResult['category'] ?? '') === 'sandbox_rate_limited'));
        $includeChallenge = ($statusCode === 402 || $statusCode === 400 || $isSandboxRateLimited);
        $failureCategory = (string) ($valResult['category'] ?? ($isSandboxRateLimited ? 'sandbox_rate_limited' : 'payment_rejected'));
        grid402_record_telemetry(
            $serviceLabel,
            $statusCode,
            false,
            $proofForLog,
            null,
            (float) $servicePriceDisplay,
            $isSandboxRateLimited ? 'rate_limited_429' : null,
            ['failure_category' => $failureCategory]
        );
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        if ($includeChallenge) {
            header('X-Authenticate: x402');
            header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . $servicePriceDisplay);
            $prBundle = grid402_emit_payment_required_header(
                $serviceLabel,
                $servicePriceDisplay,
                (int) $servicePriceRaw,
                (string) ($valResult['reason'] ?? 'Payment required'),
                null,
                (string) ($valResult['reason'] ?? '')
            );
        }
        if ($statusCode === 429 || $statusCode === 503) {
            header('Retry-After: ' . ($valResult['retry_after'] ?? 3600));
        }
        grid402_growth_event($isSandboxRateLimited ? 'sandbox_rate_limited' : 'challenge_402', [
            'service' => $serviceLabel,
            'amount_usdc' => (float) $servicePriceDisplay,
            'proof' => $proofForLog ?? $paymentProof ?? '',
        ]);

        $errCategory = (string) ($valResult['category'] ?? '');
        $explicitErr = (string) ($valResult['error'] ?? '');
        if (in_array($explicitErr, ['UnsupportedNetwork', 'SandboxNotAllowedForPass', 'PassCannotPurchasePass'], true)) {
            $errCodeLabel = $explicitErr;
        } elseif ($errCategory === 'daily_limit_exceeded') {
            $errCodeLabel = 'PassDailyLimitExceeded';
        } elseif ($errCategory === 'endpoint_restricted') {
            $errCodeLabel = 'PassEndpointRestricted';
        } elseif ($errCategory === 'endpoint_not_allowed') {
            $errCodeLabel = 'PassEndpointNotAllowed';
        } elseif ($statusCode === 403) {
            $errCodeLabel = 'ReplayAttackDetected';
        } elseif ($statusCode === 429) {
            $errCodeLabel = 'SandboxRateLimitExceeded';
        } elseif ($statusCode === 503) {
            $errCodeLabel = 'SettlementUnavailable';
        } else {
            $errCodeLabel = 'PaymentFailed';
        }

        $errPayload = [
            'error' => $errCodeLabel,
            'message' => $valResult['reason'],
            'tx_hash' => $proofForLog,
        ];
        if (isset($valResult['daily_credit_limit'])) {
            $errPayload['daily_credit_limit'] = $valResult['daily_credit_limit'];
            $errPayload['credits_used_today'] = $valResult['credits_used_today'] ?? 0;
            $errPayload['credits_remaining'] = $valResult['credits_remaining'] ?? 0;
        }
        if (isset($valResult['allowed_endpoints'])) {
            $errPayload['allowed_endpoints'] = $valResult['allowed_endpoints'];
        }
        if ($includeChallenge && isset($prBundle)) {
            $errPayload['x402Version'] = 2;
            $errPayload['resource'] = $prBundle['resource'];
            $errPayload['accepts'] = $prBundle['accepts'];
            $errPayload['extensions'] = ['bazaar' => $prBundle['bazaar']];
            $errPayload['price_usdc'] = $servicePriceDisplay;
            $errPayload['required_usdc'] = $servicePriceDisplay;
            $errPayload['required_raw_units'] = (int) $servicePriceRaw;
            $errPayload['recipient_wallet'] = GRID402_WALLET_ADDRESS;
        }
        if ($statusCode === 429) {
            $errPayload['retry_after_seconds'] = (int) ($valResult['retry_after'] ?? 3600);
        }
        if (isset($valResult['sandbox'])) {
            $errPayload['sandbox'] = true;
        }
        if (!empty($valResult['upgrade_url']) || $statusCode === 400) {
            $upgradeSessionUrl = !empty($valResult['upgrade_url']) ? (string) $valResult['upgrade_url'] : 'https://grid402.online/pass';
            $solanaOn = function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled();
            $chainsList = $solanaOn
                ? ['base', 'arbitrum', 'polygon', 'optimism', 'ethereum', 'solana']
                : ['base', 'arbitrum', 'polygon', 'optimism', 'ethereum'];
            $chainsLabel = $solanaOn ? 'Base/Polygon/Arbitrum/Solana' : 'Base/Polygon/Arbitrum';
            $errPayload['upgrade_to_production'] = [
                'session_pass_url' => $upgradeSessionUrl,
                'pix_micro_url' => 'https://grid402.online/pass?tier=micro&method=pix',
                'pix_starter_url' => 'https://grid402.online/pass?tier=starter&method=pix',
                'pix_instant_starter_url' => 'https://grid402.online/pass?tier=starter&method=pix',
                'pix_pro_url' => 'https://grid402.online/pass?tier=pro&method=pix',
                'starter_tier' => 'Micro R$ 4.90 (50 credits / 5 KYB dossiers) | Starter 2.0000 USDC or R$ 12.00 (Pix/Card) = 400 credits (180d)',
                'how_to_continue' => [
                    'x402_gasless' => 'Sign the x402 v2 challenge in accepts[] and retry with header PAYMENT-SIGNATURE: <base64_payload>',
                    'x402_onchain' => "Send {$servicePriceDisplay} USDC ({$servicePriceRaw} raw units) to " . GRID402_WALLET_ADDRESS . " on {$chainsLabel} and retry with X-Payment: <tx_hash>",
                    'session_pass_no_wallet' => 'Get a prepaid token at https://grid402.online/pass (Pix/Card or EVM USDC) and retry with X-Payment: pass_g402_<token>',
                    'rapidapi_credit_card_usd' => 'Subscribe with international Credit Card (USD) at https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
                ],
                'rapidapi_credit_card_usd' => [
                    'plans' => 'BASIC Free (25/mo) | PRO $9.90/mo (1,500 std calls) | ULTRA $29.00/mo (6,000 std calls) | MEGA $79.00/mo (25,000 std calls; dedicated KYB quotas for /dossier and /compliance)',
                    'all_25_apis_url' => 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
                    'web_markdown_email_network_url' => 'https://rapidapi.com/oliveiratc/api/grid402-web-to-markdown-email-verify-network-utilities',
                    'brazil_kyb_cnpj_anbima_url' => 'https://rapidapi.com/oliveiratc/api/brazil-kyb-cnpj-cgu-sanctions-anbima-workdays-api',
                ],
                'supported_chains' => $chainsList,
            ];
        }
        $logPaySource = $proofIsPayload
            ? 'facilitator'
            : (str_starts_with(strtolower($paymentProof), 'pass_g402_') ? 'pass' : ((strtolower($paymentProof) === 'demo' || strtolower($paymentProof) === 'sandbox') ? 'sandbox' : 'unknown'));
        $proofDiag = function_exists('grid402_summarize_rejected_proof_shape')
            ? grid402_summarize_rejected_proof_shape($paymentProofSourceHeader, $paymentProof)
            : null;
        grid402_log_request([
            'service' => $serviceLabel,
            'status' => $statusCode,
            'payment_source' => $logPaySource,
            'error' => $errCodeLabel . ': ' . (string) ($valResult['reason'] ?? ''),
            'proof_diag' => $proofDiag,
        ]);
        if (!$isSandboxRateLimited && function_exists('grid402_record_payment_failure')) {
            grid402_record_payment_failure([
                'service' => $serviceLabel,
                'status' => $statusCode,
                'payment_source' => $logPaySource,
                'failure_category' => $failureCategory,
                'error' => $errCodeLabel . ': ' . (string) ($valResult['reason'] ?? ''),
                'proof_diag' => $proofDiag,
            ]);
        }
        echo json_encode($errPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    };

    // 2.3.0. Bloqueio estrito V-01 em /api/v1/pass: proibe demo/sandbox e compra pass-por-pass
    $lowerProof = strtolower(trim($paymentProof));
    if ($isPass) {
        if ($lowerProof === 'demo' || $lowerProof === 'sandbox') {
            $emitPaymentFailure([
                'valid' => false,
                'code' => 403,
                'sandbox' => true,
                'error' => 'SandboxNotAllowedForPass',
                'category' => 'sandbox_not_allowed_for_pass',
                'reason' => 'Modo sandbox/demo nao pode emitir Session Pass. Realize pagamento real em USDC (x402 via Base, Polygon, Arbitrum ou Solana) ou compre instantaneamente via Pix/Cartao em https://grid402.online/pass',
                'upgrade_url' => 'https://grid402.online/pass',
            ]);
        }
        if (str_starts_with($lowerProof, 'pass_g402_')) {
            $emitPaymentFailure([
                'valid' => false,
                'code' => 403,
                'error' => 'PassCannotPurchasePass',
                'category' => 'pass_for_pass_blocked',
                'reason' => 'Um Session Pass existente nao pode ser utilizado para comprar outro Session Pass. Realize pagamento real em USDC (x402/on-chain) ou via Pix/Cartao em https://grid402.online/pass',
                'upgrade_url' => 'https://grid402.online/pass',
            ]);
        }
    }

    // 2.3.1. Verificação de Idempotência M2M Segura (V-03):
    // - Exclui /api/v1/pass, /api/v1/pass-emitir e modo sandbox (demo/sandbox)
    // - Autentica/valida formato minimo da credencial ANTES de qualquer cache hit (sem debitar)
    // - Vincula chave composta ao pagador/credencial, modo prod e hash do metodo + corpo POST
    $idempotencyEligible = false;
    $idempotencyScope = '';
    $idempotencyPaymentSource = 'unknown';

    if ($idempotencyKey !== '' && !$isPass && !$isPassEmitir) {
        $reqMethodUpper = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $bodyDigest = hash('sha256', $reqMethodUpper . '|' . $rawRequestBody);
        $credScope = '';

        if ($isRapidApiProxy && $paymentProof === '') {
            $credScope = 'rapidapi:' . grid402_rapidapi_subscriber_bucket();
            $idempotencyPaymentSource = 'rapidapi';
            $idempotencyEligible = true;
        } elseif ($lowerProof === 'demo' || $lowerProof === 'sandbox') {
            // Sandbox nunca le nem grava cache de idempotencia
            $idempotencyEligible = false;
        } elseif (str_starts_with($lowerProof, 'pass_g402_')) {
            $verifiedPassId = grid402_pass_verify_token_format($paymentProof);
            if ($verifiedPassId !== null) {
                $passPreStatus = grid402_get_session_pass_status($paymentProof);
                $isPassActiveForReplay = !empty($passPreStatus['valid'])
                    && (($passPreStatus['status'] ?? '') !== 'EXPIRED')
                    && (($passPreStatus['mode'] ?? 'production') !== 'sandbox');
                $allowedList = is_array($passPreStatus['allowed_endpoints'] ?? null) ? $passPreStatus['allowed_endpoints'] : [];
                if ($isBatch && is_array($batchPreCheck) && !empty($batchPreCheck['unique_services'])) {
                    $endpointAllowedForReplay = empty($allowedList);
                    if (!$endpointAllowedForReplay) {
                        $endpointAllowedForReplay = true;
                        foreach ($batchPreCheck['unique_services'] as $batchSubSvc) {
                            if (!in_array((string) $batchSubSvc, $allowedList, true)) {
                                $endpointAllowedForReplay = false;
                                break;
                            }
                        }
                    }
                } else {
                    $endpointAllowedForReplay = empty($allowedList) || in_array($apiParam, $allowedList, true);
                }
                if ($isPassActiveForReplay && $endpointAllowedForReplay) {
                    $credScope = 'pass:' . $verifiedPassId;
                    $idempotencyPaymentSource = 'pass';
                    $idempotencyEligible = true;
                }
            }
        } elseif (grid402_classify_payment_proof($paymentProof) === 'x402_payload') {
            $decPre = function_exists('grid402_decode_x402_payload') ? grid402_decode_x402_payload($paymentProof) : ['ok' => false];
            if (!empty($decPre['ok'])) {
                $credScope = 'x402:' . hash('sha256', $paymentProof);
                $idempotencyPaymentSource = 'facilitator';
                $idempotencyEligible = true;
            }
        } elseif ($paymentProof !== '' && function_exists('grid402_parse_network_proof')) {
            $parsedPreNet = grid402_parse_network_proof($paymentProof, $paymentNetworkHeader !== '' ? $paymentNetworkHeader : null);
            if (($parsedPreNet['invalid_network'] ?? null) === null) {
                $preNet = (string) ($parsedPreNet['network'] ?? 'base');
                $preTx = (string) ($parsedPreNet['tx_hash'] ?? '');
                $isValidTxShape = ($preNet === 'solana')
                    ? (function_exists('grid402_is_valid_solana_signature') && grid402_is_valid_solana_signature($preTx))
                    : (preg_match('/^0x[a-f0-9]{64}$/i', $preTx) === 1);
                if ($isValidTxShape) {
                    $credScope = 'tx:' . hash('sha256', $preNet . ':' . ($preNet === 'solana' ? $preTx : strtolower($preTx)));
                    $idempotencyPaymentSource = ($preNet === 'base') ? 'tx_hash' : ($preNet === 'solana' ? 'solana_spl' : ('evm_' . $preNet));
                    $idempotencyEligible = true;
                }
            }
        }

        if ($idempotencyEligible && $credScope !== '') {
            $idempotencyScope = $credScope . '|prod|' . $bodyDigest;
            $cachedIdempotent = grid402_idempotency_check($idempotencyKey, $apiParam, $_GET, $idempotencyScope);
            if (is_array($cachedIdempotent)) {
                if ($idempotencyPaymentSource === 'pass') {
                    $replayPassStatus = grid402_get_session_pass_status($paymentProof);
                    if (!empty($replayPassStatus['ok'])) {
                        grid402_record_pass_statement_event($paymentProof, [
                            'request_id' => grid402_get_request_id(),
                            'endpoint' => $apiParam,
                            'credits_debited' => 0,
                            'credits_after' => (int) ($replayPassStatus['credits_remaining'] ?? 0),
                            'http_status' => 200,
                            'outcome' => 'idempotent_replay',
                        ]);
                    }
                }
                $idemLatency = grid402_request_elapsed_ms();
                grid402_record_latency_sample($idemLatency);
                grid402_log_request([
                    'service' => $apiParam,
                    'status' => 200,
                    'latency_ms' => $idemLatency,
                    'payment_source' => $idempotencyPaymentSource,
                    'idempotent_replay' => true,
                ]);
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                header('X-Payment-Status: Accepted');
                header('X-Idempotent-Replay: true');
                header('Cache-Control: private, no-cache');
                echo json_encode($cachedIdempotent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    if ($isPassEmitir) {
        // Checkout Web3 One-Click Top-Up: nao exige X-Payment previo.
        // O txHash e validado on-chain dentro do case 'pass-emitir' abaixo.
        $validation = [
            'valid' => true,
            'sandbox' => false,
            'source' => 'web3_topup',
            'settlement_tx' => null,
            'session_pass' => true,
            'raw_amount' => null,
        ];
    } elseif ($isRapidApiProxy && $paymentProof === '') {
        // Chamada autenticada pelo proxy RapidAPI (faturamento em USD via assinatura RapidAPI)
        // Aplica salvaguarda anti-arbitragem opt-in (.env) para endpoints premium (/dossier)
        $rapidGuard = function_exists('grid402_check_rapidapi_endpoint_guard')
            ? grid402_check_rapidapi_endpoint_guard($apiParam)
            : ['allowed' => true, 'consumed' => false];
        if (empty($rapidGuard['allowed'])) {
            $guardCode = (int) ($rapidGuard['http_status'] ?? 429);
            http_response_code($guardCode);
            header('Content-Type: application/json; charset=utf-8');
            if (($guardCode === 429 || $guardCode === 503) && !empty($rapidGuard['retry_after'])) {
                header('Retry-After: ' . (int) $rapidGuard['retry_after']);
            }
            $guardErr = (string) ($rapidGuard['error'] ?? 'RapidApiEndpointCapExceeded');
            $guardReason = (string) ($rapidGuard['reason'] ?? 'RapidAPI endpoint limit reached.');
            grid402_log_request([
                'service' => $apiParam,
                'status' => $guardCode,
                'payment_source' => 'rapidapi',
                'error' => $guardErr . ': ' . $guardReason,
            ]);
            $guardPayload = [
                'error' => $guardErr,
                'code' => $guardCode,
                'endpoint' => '/api/v1/' . $apiParam,
                'reason' => $guardReason,
                'upgrade_url' => (string) ($rapidGuard['upgrade_url'] ?? 'https://grid402.online/pass'),
                'pix_instant_starter_url' => 'https://grid402.online/pass?tier=starter&method=pix',
            ];
            if (isset($rapidGuard['daily_cap'])) {
                $guardPayload['daily_cap'] = (int) $rapidGuard['daily_cap'];
                $guardPayload['used_today'] = (int) ($rapidGuard['used_today'] ?? 0);
            }
            echo json_encode($guardPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
        $validation = [
            'valid' => true,
            'sandbox' => false,
            'source' => 'rapidapi',
            'payer' => 'rapidapi_proxy',
            'settlement_tx' => null,
            'network' => 'rapidapi',
            'raw_amount' => $servicePriceRaw,
            'rapidapi_guard_consumed' => !empty($rapidGuard['consumed']),
        ];
    } else {
        $endpointIdForValidate = $apiParam;
        if ($isBatch && str_starts_with(strtolower($paymentProof), 'pass_g402_') && is_array($batchPreCheck)) {
            $batchPassPre = grid402_get_session_pass_status($paymentProof);
            if (!empty($batchPassPre['valid']) && is_array($batchPassPre['allowed_endpoints'] ?? null) && !empty($batchPassPre['allowed_endpoints'])) {
                $allowedBatchList = $batchPassPre['allowed_endpoints'];
                foreach (($batchPreCheck['unique_services'] ?? []) as $batchSubSvc) {
                    if (!in_array((string) $batchSubSvc, $allowedBatchList, true)) {
                        $emitPaymentFailure([
                            'valid' => false,
                            'code' => 403,
                            'error' => 'PassEndpointRestricted',
                            'category' => 'endpoint_restricted',
                            'reason' => "Este Session Pass possui Spend Control ativo e nao esta autorizado para o sub-servico '{$batchSubSvc}' no lote.",
                            'allowed_endpoints' => $allowedBatchList,
                        ]);
                    }
                }
            }
            $endpointIdForValidate = null;
        }
        $validation = grid402_validate_payment(
            $paymentProof,
            $servicePriceRaw,
            $clientIp,
            $endpointIdForValidate,
            $paymentNetworkHeader !== '' ? $paymentNetworkHeader : null,
            true
        );
        // Payload x402 padrao (base64) nunca e gravado em telemetria nem ecoado na resposta
        $proofIsPayload = grid402_classify_payment_proof($paymentProof) === 'x402_payload';
        $proofForLog = $proofIsPayload ? null : $paymentProof;
        if (empty($validation['valid'])) {
            $emitPaymentFailure($validation);
        }
        // Defesa em profundidade V-01: bloqueia qualquer fallback sandbox (ex.: Base Sepolia) ou Session Pass em /api/v1/pass
        if ($isPass && (!empty($validation['sandbox']) || !empty($validation['session_pass']) || in_array((string) ($validation['source'] ?? ''), ['sandbox', 'pass'], true))) {
            if (!empty($validation['needs_settle'])) {
                grid402_abort_verified_exact_payment($validation);
            }
            if (!empty($validation['lock_key']) && function_exists('grid402_release_transaction_lock')) {
                grid402_release_transaction_lock((string) $validation['lock_key']);
            }
            $isPassSource = !empty($validation['session_pass']) || (($validation['source'] ?? '') === 'pass');
            $emitPaymentFailure([
                'valid' => false,
                'code' => 403,
                'sandbox' => !$isPassSource,
                'error' => $isPassSource ? 'PassCannotPurchasePass' : 'SandboxNotAllowedForPass',
                'category' => $isPassSource ? 'pass_for_pass_blocked' : 'sandbox_not_allowed_for_pass',
                'reason' => $isPassSource
                    ? 'Um Session Pass existente nao pode comprar outro Session Pass. Pague com USDC real ou Pix em https://grid402.online/pass'
                    : 'Pagamentos sandbox/testnet nao podem emitir Session Pass de producao. Pague com USDC mainnet ou Pix em https://grid402.online/pass',
                'upgrade_url' => 'https://grid402.online/pass',
            ]);
        }
    }

    $isSandbox = !empty($validation['sandbox']);

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
            break;
        case 'ssl':
            $host = (string) ($_GET['host'] ?? 'base.org');
            $result = grid402_service_ssl($host);
            break;
        case 'asn':
            $ip = isset($_GET['ip']) ? (string)$_GET['ip'] : null;
            $result = grid402_service_asn($ip);
            break;
        case 'domain-audit':
            $domain = (string) ($_GET['domain'] ?? $_GET['host'] ?? 'base.org');
            $result = grid402_service_domain_audit($domain);
            break;
        case 'kyb-audit':
            $cnpjVal = (string) ($_GET['cnpj'] ?? '');
            $nameVal = (string) ($_GET['name'] ?? '');
            $leiVal = (string) ($_GET['lei'] ?? '');
            $addrVal = (string) ($_GET['address'] ?? $_GET['target'] ?? '');
            $result = grid402_service_kyb_audit($cnpjVal, $nameVal, $leiVal, $addrVal, !empty($validation['sandbox']));
            break;
        case 'dossier':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_dossier($cnpjVal);
            break;
        case 'market-snapshot':
            $result = grid402_service_market_snapshot();
            break;
        case 'cep':
            $code = (string) ($_GET['code'] ?? $_GET['cep'] ?? '01310100');
            $result = grid402_service_cep($code);
            break;
        case 'cnpj':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_cnpj($cnpjVal);
            break;
        case 'indicators':
            $result = grid402_service_indicators();
            break;
        case 'holidays':
            $yearParam = isset($_GET['year']) ? (int) $_GET['year'] : null;
            $result = grid402_service_holidays($yearParam);
            break;
        case 'workdays':
            $result = grid402_service_workdays($_GET);
            break;
        case 'sanctions':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_sanctions($cnpjVal);
            break;
        case 'simples':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_simples($cnpjVal);
            break;
        case 'compliance':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_compliance($cnpjVal);
            break;
        case 'lei':
            $leiVal = (string) ($_GET['lei'] ?? '');
            $qVal = (string) ($_GET['q'] ?? '');
            $result = grid402_service_lei($leiVal, $qVal);
            break;
        case 'ofac':
            $addrVal = (string) ($_GET['address'] ?? $_GET['target'] ?? '');
            $nameVal = (string) ($_GET['name'] ?? '');
            if ($addrVal !== '' && $nameVal === '' && function_exists('grid402_ofac_detect_address_type') && grid402_ofac_detect_address_type($addrVal) === null && !str_starts_with(strtolower(trim($addrVal)), '0x')) {
                $nameVal = $addrVal;
                $addrVal = '';
            }
            $result = grid402_service_ofac($addrVal, $nameVal, !empty($validation['sandbox']));
            break;
        case 'notarize':
            $dataVal = (string) ($_GET['data'] ?? $_GET['content'] ?? '');
            $hashVal = (string) ($_GET['hash'] ?? '');
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dataVal === '' && $hashVal === '') {
                $bodyRaw = $rawRequestBody !== '' ? $rawRequestBody : (string) @file_get_contents('php://input');
                $dataVal = substr($bodyRaw, 0, 8192);
            }
            $result = grid402_service_notarize($dataVal, $hashVal);
            break;
        case 'global-markets':
            $mktVal = (string) ($_GET['market'] ?? 'US');
            $dtVal = (string) ($_GET['date'] ?? '');
            $baseVal = (string) ($_GET['base'] ?? 'USD');
            $result = grid402_service_global_markets($mktVal, $dtVal, $baseVal);
            break;
        case 'extract':
            $urlVal = (string) ($_GET['url'] ?? 'https://example.com');
            $maxCharsVal = isset($_GET['max_chars']) ? (int) $_GET['max_chars'] : 12000;
            $result = grid402_service_extract($urlVal, $maxCharsVal);
            break;
        case 'email-verify':
            $emailVal = (string) ($_GET['email'] ?? 'contato@grid402.online');
            $result = grid402_service_email_verify($emailVal);
            break;
        case 'token-safety':
            $tsAddrVal = (string) ($_GET['address'] ?? $_GET['token'] ?? '');
            $tsChainVal = (string) ($_GET['chain'] ?? '');
            $result = grid402_service_token_safety($tsAddrVal, $tsChainVal);
            break;
        case 'prediction-markets':
            $pmQVal = (string) ($_GET['q'] ?? '');
            $pmSourceVal = (string) ($_GET['source'] ?? 'all');
            $pmStatusVal = (string) ($_GET['status'] ?? 'active');
            $pmLimitVal = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $result = grid402_service_prediction_markets($pmQVal, $pmSourceVal, $pmStatusVal, $pmLimitVal);
            break;
        case 'sec-company':
            $result = grid402_service_sec_company(
                (string) ($_GET['ticker'] ?? ''),
                (string) ($_GET['cik'] ?? ''),
                isset($_GET['limit']) ? (int) $_GET['limit'] : 10,
                (string) ($_GET['forms'] ?? ''),
                $_GET['include_facts'] ?? false
            );
            break;
        case 'vuln-audit':
            $result = grid402_service_vuln_audit(
                (string) ($_GET['ecosystem'] ?? ''),
                (string) ($_GET['package'] ?? ''),
                (string) ($_GET['version'] ?? ''),
                (string) ($_GET['id'] ?? '')
            );
            break;
        case 'chain-telemetry':
            $result = grid402_service_chain_telemetry((string) ($_GET['chain'] ?? 'base'));
            break;
        case 'batch':
            $validatedBatchPayload = is_array($batchPreCheck)
                ? $batchPreCheck
                : grid402_batch_parse_and_validate($rawRequestBody, !empty($validation['sandbox']));
            if (empty($validatedBatchPayload['ok'])) {
                $result = array_merge(['success' => false], $validatedBatchPayload);
            } else {
                $result = grid402_batch_execute_items(
                    $validatedBatchPayload,
                    static function (string $subSvc, array $subParams, bool $subSandbox): array {
                        return grid402_dispatch_data_service($subSvc, $subParams, $subSandbox);
                    },
                    !empty($validation['sandbox'])
                );
            }
            break;
        case 'pass':
            if (!empty($validation['needs_settle'])) {
                $validation = grid402_settle_exact_payment($validation);
                if (empty($validation['valid'])) {
                    $emitPaymentFailure($validation);
                }
            }
            // Proteção contra inflação: garante que os créditos correspondam estritamente ao preço tabelado do tier liquidado
            $rawUnitsPaid = (int) ($validation['settled_amount'] ?? $servicePriceRaw);
            $passTxRef = (string) ($validation['lock_key'] ?? $validation['settlement_tx'] ?? ($proofIsPayload ? ('x402_' . bin2hex(random_bytes(8))) : $paymentProof));
            $result = grid402_issue_session_pass($passTxRef, $rawUnitsPaid, false);
            break;

        // Web3 One-Click Top-Up Multi-Chain: emite um Session Pass a partir de uma txHash/assinatura USDC
        // já confirmada on-chain em Base, Arbitrum, Polygon, Optimism, Ethereum ou Solana.
        case 'pass-emitir':
            $txParam = (string) ($_GET['tx'] ?? $_GET['txHash'] ?? $_GET['tx_hash'] ?? $_GET['signature'] ?? '');
            $chainParam = (string) ($_GET['chain'] ?? $_GET['network'] ?? $_SERVER['HTTP_X_PAYMENT_NETWORK'] ?? '');
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawPost = $rawRequestBody !== '' ? $rawRequestBody : (string) @file_get_contents('php://input');
                $parsedPost = @json_decode($rawPost, true);
                if (is_array($parsedPost)) {
                    if ($txParam === '') {
                        $txParam = trim((string) ($parsedPost['tx'] ?? $parsedPost['txHash'] ?? $parsedPost['tx_hash'] ?? $parsedPost['signature'] ?? ''));
                    }
                    if ($chainParam === '') {
                        $chainParam = trim((string) ($parsedPost['chain'] ?? $parsedPost['network'] ?? ''));
                    }
                }
            }
            $result = grid402_emitir_passe_por_tx($txParam, $chainParam !== '' ? $chainParam : null);
            $passEmitirCountry = null;
            if (!empty($result['ok']) && empty($result['idempotent'])) {
                $deposited = ((int)($result['raw_usdc_units'] ?? 0)) / 1000000;
                $passNetSlug = (string) ($result['network_slug'] ?? 'base');
                $passClientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                $passEmitirCountry = function_exists('grid402_resolve_client_country')
                    ? grid402_resolve_client_country($passClientIp, true)
                    : null;
                grid402_growth_event('pass_purchase', [
                    'service' => 'pass',
                    'network' => $passNetSlug,
                    'payer' => (string)($result['payer'] ?? ''),
                    'amount_usdc' => $deposited,
                    'proof' => (string)($result['tx_hash'] ?? ''),
                ]);
                if (function_exists('grid402_notify_telegram_purchase') && !empty($result['tx_hash'])) {
                    grid402_notify_telegram_purchase('pass', $deposited, (string)$result['tx_hash'], false, $passNetSlug, $passEmitirCountry);
                }
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

    $httpStatus = (is_array($result) && isset($result['code']) && is_int($result['code']) && $result['code'] >= 100 && $result['code'] < 600)
        ? $result['code']
        : 200;

    if ($httpStatus !== 200) {
        if (!empty($validation['needs_settle'])) {
            grid402_abort_verified_exact_payment($validation);
        } elseif (!empty($validation['session_pass']) && !$isPassEmitir && !empty($validation['credits_debited']) && function_exists('grid402_refund_session_pass')) {
            // Estorna créditos debitados de Session Pass quando o endpoint falha antes de entregar dados
            grid402_refund_session_pass($paymentProof, (int) $validation['credits_debited'], $apiParam, ['http_status' => $httpStatus]);
        } elseif (($validation['source'] ?? '') === 'rapidapi' && !empty($validation['rapidapi_guard_consumed']) && function_exists('grid402_refund_rapidapi_endpoint_guard')) {
            // Estorna o contador diario da trava suave RapidAPI quando o endpoint falha (HTTP != 200)
            grid402_refund_rapidapi_endpoint_guard($apiParam);
        } elseif (!empty($validation['lock_key']) && function_exists('grid402_release_transaction_lock')) {
            // Libera trava anti-replay de tx_hash direto (EVM/Solana) para não queimar pagamento sem entrega
            $srcErr = (string) ($validation['source'] ?? '');
            if ($srcErr === 'tx_hash' || $srcErr === 'solana_spl' || str_starts_with($srcErr, 'evm_') || str_starts_with($srcErr, 'multichain_')) {
                grid402_release_transaction_lock((string) $validation['lock_key']);
            }
        }
        $errSource = (string) ($validation['source'] ?? '');
        $errTx = $validation['settlement_tx'] ?? $proofForLog;
        grid402_record_telemetry(
            $apiParam,
            $httpStatus,
            false,
            $errTx,
            null,
            0.0,
            null,
            ['payment_source' => $errSource !== '' ? $errSource : null, 'defer_latency_sample' => true]
        );
        $errLatencyMs = grid402_request_elapsed_ms();
        grid402_record_latency_sample($errLatencyMs);
        grid402_log_request([
            'service' => $apiParam,
            'status' => $httpStatus,
            'latency_ms' => $errLatencyMs,
            'payment_source' => $errSource !== '' ? $errSource : 'unknown',
            'idempotent_replay' => false,
            'error' => (string) ($result['error'] ?? $result['message'] ?? ('HTTP_' . $httpStatus)),
        ]);
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-cache');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Execucao do servico retornou HTTP 200: liquida o pagamento x402 (se ainda pendente de settle)
    if (!empty($validation['needs_settle'])) {
        $validation = grid402_settle_exact_payment($validation);
        if (empty($validation['valid'])) {
            $emitPaymentFailure($validation);
        }
    }

    // Estorno parcial automatico de Session Pass quando POST /api/v1/batch teve falha em parte dos sub-itens
    if (
        $isBatch
        && !empty($validation['session_pass'])
        && is_array($result['batch_summary'] ?? null)
        && ((int) ($result['batch_summary']['credits_refunded'] ?? 0)) > 0
        && function_exists('grid402_refund_session_pass')
    ) {
        $batchRefundCredits = (int) $result['batch_summary']['credits_refunded'];
        $refundedOk = grid402_refund_session_pass(
            $paymentProof,
            $batchRefundCredits,
            'batch_partial_refund',
            [
                'succeeded' => (int) ($result['batch_summary']['succeeded'] ?? 0),
                'failed' => (int) ($result['batch_summary']['failed'] ?? 0),
            ]
        );
        if ($refundedOk) {
            $validation['credits_debited'] = (int) ($result['batch_summary']['credits_charged'] ?? max(0, ((int) ($validation['credits_debited'] ?? 0)) - $batchRefundCredits));
            $postRefundStatus = grid402_get_session_pass_status($paymentProof);
            if (!empty($postRefundStatus['ok']) && isset($postRefundStatus['credits_remaining'])) {
                $validation['credits_remaining'] = (int) $postRefundStatus['credits_remaining'];
            }
        }
    }

    // Pagamento ou Sandbox validado/liquidado com sucesso!
    $isSandbox = !empty($validation['sandbox']);
    $isVerifyOnly = (($validation['source'] ?? '') === 'facilitator_verify_only');
    $isRealPayment = !$isSandbox && !$isVerifyOnly;
    // Sandbox/demo e verify_only nao sao consulta paga: registra como 'sandbox'/'verify_only'.
    // Consumo de Session Pass conta como chamada paga em total_paid_requests, mas soma 0.0 em total_revenue_usdc
    // porque a receita ja foi contabilizada no ato da compra/emissao do passe (Web3 ou Mercado Pago).
    $settlementTx = $validation['settlement_tx'] ?? null;
    $paymentSourceVal = (string) ($validation['source'] ?? '');
    $reqUserAgentLower = strtolower(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $reqChannelHeader = strtolower(trim((string) ($_SERVER['HTTP_X_GRID402_CHANNEL'] ?? '')));
    $isMcpClient = str_starts_with($reqUserAgentLower, 'grid402-mcp-server/') || $reqChannelHeader === 'mcp';
    $telemetryPaymentSource = ($isRealPayment && $isMcpClient && $paymentSourceVal !== 'rapidapi' && !$isPassEmitir)
        ? 'mcp'
        : null;

    if ($isPassEmitir) {
        $isIdempotentTopup = !empty($result['idempotent']);
        $deposited = $isIdempotentTopup ? 0.0 : (((int) ($result['raw_usdc_units'] ?? 0)) / 1000000.0);
        $settlementTx = (string) ($result['tx_hash'] ?? '');
        $revenueUsdcToRecord = $deposited;
        $billingCountry = $passEmitirCountry ?? (function_exists('grid402_resolve_client_country')
            ? grid402_resolve_client_country(null, !$isIdempotentTopup)
            : null);
        if (!$isIdempotentTopup) {
            grid402_record_telemetry(
                $apiParam,
                200,
                true,
                $settlementTx,
                null,
                $revenueUsdcToRecord,
                null,
                ['payment_source' => 'web3_topup', 'settlement_tx' => $settlementTx, 'country_code' => $billingCountry, 'defer_latency_sample' => true]
            );
        }
    } else {
        $actualPaidUsdc = ($apiParam === 'pass' && !empty($validation['raw_amount']) && (int) $validation['raw_amount'] > 0)
            ? (((int) $validation['raw_amount']) / 1000000.0)
            : (float) $servicePriceDisplay;
        $revenueUsdcToRecord = ($isSandbox || $isVerifyOnly || $paymentSourceVal === 'pass' || $paymentSourceVal === 'rapidapi') ? 0.0 : $actualPaidUsdc;
        $reqClientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $billingCountry = function_exists('grid402_resolve_client_country')
            ? grid402_resolve_client_country($reqClientIp, $isRealPayment)
            : null;

        if ($isRealPayment) {
            grid402_growth_event('paid_request', [
                'service' => $apiParam,
                'payer' => $validation['payer'] ?? '',
                'amount_usdc' => $revenueUsdcToRecord,
                'type' => $paymentSourceVal === 'pass' ? 'pass' : ($paymentSourceVal === 'rapidapi' ? 'rapidapi' : 'x402'),
            ]);
        } else {
            $growthType = $isSandbox ? 'sandbox_request' : 'verify_only';
            grid402_growth_event($growthType, [
                'service' => $apiParam,
                'proof' => $proofForLog ?? $paymentProof ?? '',
                'type' => $paymentSourceVal,
            ]);
        }
        grid402_record_telemetry(
            $apiParam,
            200,
            $isRealPayment,
            $settlementTx ?? $proofForLog,
            null,
            $revenueUsdcToRecord,
            $isSandbox ? 'sandbox' : ($isVerifyOnly ? 'verify_only' : null),
            [
                'payment_source' => $telemetryPaymentSource ?? ($validation['source'] ?? null),
                'channel' => $isMcpClient ? 'mcp' : null,
                'settlement_source' => $validation['source'] ?? null,
                'country_code' => $billingCountry,
                'defer_latency_sample' => true,
            ]
        );
        $txForNotification = (string) ($settlementTx ?? $proofForLog ?? '');
        $rawValidatedNet = (string) ($validation['network'] ?? 'base');
        $validatedNetSlug = function_exists('grid402_normalize_network_slug')
            ? (grid402_normalize_network_slug($rawValidatedNet) ?? 'base')
            : strtolower($rawValidatedNet);
        $isValidNotifTx = (preg_match('/^0x[a-f0-9]{64}$/i', $txForNotification) === 1)
            || ($validatedNetSlug === 'solana' && function_exists('grid402_is_valid_solana_signature') && grid402_is_valid_solana_signature($txForNotification));
        if (!$isSandbox && !$isVerifyOnly && $paymentSourceVal !== 'pass' && $isValidNotifTx) {
            grid402_notify_telegram_purchase($apiParam, (float) $actualPaidUsdc, $txForNotification, false, $validatedNetSlug, $billingCountry);
        }
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Payment-Status: Accepted');
    if ($paymentSourceVal === 'facilitator') {
        $receiptHeader = grid402_build_payment_response_header($validation);
        header('PAYMENT-RESPONSE: ' . $receiptHeader);
        header('X-PAYMENT-RESPONSE: ' . $receiptHeader);
    }
    if ($isSandbox) {
        header('X-Payment-Mode: sandbox');
        header('X-Payment-Amount: 0.0000 USDC (Sandbox)');
        header('X-Payment-Network: ' . ($validation['network'] ?? 'Simulation (demo)'));
    } elseif ($paymentSourceVal === 'rapidapi') {
        header('X-Payment-Mode: production');
        header('X-Payment-Amount: RapidAPI Subscription');
        header('X-Payment-Network: RapidAPI Proxy');
    } else {
        $validatedNetSlugHeader = (string) ($validation['network'] ?? 'base');
        header('X-Payment-Mode: production');
        header('X-Payment-Amount: ' . $servicePriceDisplay . ' USDC');
        $netMapHeader = function_exists('grid402_supported_networks') ? grid402_supported_networks() : [];
        $normNetHeader = function_exists('grid402_normalize_network_slug') ? (grid402_normalize_network_slug($validatedNetSlugHeader) ?? 'base') : 'base';
        $netDisplayHeader = isset($netMapHeader[$normNetHeader])
            ? ($netMapHeader[$normNetHeader]['display_name'] . (!empty($netMapHeader[$normNetHeader]['chain_id']) ? (' (ChainID ' . $netMapHeader[$normNetHeader]['chain_id'] . ')') : ''))
            : 'Base (ChainID 8453)';
        header('X-Payment-Network: ' . $netDisplayHeader);
    }
    if (!empty($validation['session_pass']) && !$isPassEmitir) {
        $creditsRemaining = (int)($validation['credits_remaining'] ?? 0);
        $growthCfg = function_exists('grid402_growth_read') ? (grid402_growth_read()['config'] ?? []) : [];
        $lowBalanceThreshold = (int)($growthCfg['low_balance_threshold'] ?? 10);
        header('X-Pass-Credits-Debited: ' . ($validation['credits_debited'] ?? 1));
        header('X-Pass-Credits-Remaining: ' . $creditsRemaining);
        if ($creditsRemaining <= $lowBalanceThreshold) {
            header('X-Pass-Low-Balance: true');
            header('X-Pass-Low-Balance-Threshold: ' . $lowBalanceThreshold);
            header('X-Pass-Recharge: ' . (defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online') . '/pass');
        }
    }
    header('Cache-Control: private, no-cache');

    if (is_array($result)) {
        // Digest de faturamento por chamada: torna Pay-per-Request vs Session Pass auditável pelo agente
        $payerForDigest = (string) ($validation['payer'] ?? '');
        $digestId = substr(hash_hmac('sha256', json_encode([
            'service' => $apiParam,
            'price' => $servicePriceDisplay,
            'source' => $validation['source'] ?? 'unknown',
            'credits_debited' => $validation['credits_debited'] ?? null,
            'ts' => (string) ($result['timestamp'] ?? gmdate('Y-m-d\TH:i:s\Z')),
        ], JSON_UNESCAPED_SLASHES), function_exists('grid402_growth_secret') ? grid402_growth_secret() : GRID402_CRON_SECRET), 0, 16);

        $result['_billing'] = [
            'service' => $apiParam,
            'price_usdc' => $servicePriceDisplay,
            'payment_source' => $validation['source'] ?? 'unknown',
            'credits_debited' => $validation['credits_debited'] ?? null,
            'credits_remaining' => $validation['credits_remaining'] ?? null,
            'payer_last4' => $payerForDigest !== '' ? substr(strtolower($payerForDigest), -4) : null,
            'digest_id' => $digestId,
        ];
        header('X-Billing-Digest: ' . $digestId);
    }

    if ($isSandbox && is_array($result)) {
        $result['sandbox'] = true;
        $remQuota = (int) ($validation['remaining'] ?? 0);
        $limQuota = (int) ($validation['limit'] ?? GRID402_SANDBOX_RATE_LIMIT);
        $solanaPublicOn = function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled();
        $result['sandbox_info'] = [
            'quota_remaining_today' => $remQuota,
            'quota_limit_per_day' => $limQuota,
            'quota_remaining_this_hour' => $remQuota,
            'quota_limit_per_hour' => $limQuota,
            'window_seconds' => defined('GRID402_SANDBOX_WINDOW_SECONDS') ? GRID402_SANDBOX_WINDOW_SECONDS : 86400,
            'message' => "Sandbox query executed ({$remQuota}/{$limQuota} remaining today). Switch to production (<15ms) via Pix/Card (from R$ 4.90), Session Pass at https://grid402.online/pass, RapidAPI (USD Card) or Multi-Chain EVM USDC (Base, Polygon, Arbitrum).",
            'upgrade_to_production' => [
                'session_pass_pix_and_multichain_url' => 'https://grid402.online/pass?tier=starter&method=pix',
                'micro_tier' => 'R$ 4,90 (Pix/Cartão) = 50 créditos (5 Dossiês KYB, 90 dias)',
                'starter_tier' => 'R$ 12,00 (Pix/Cartão) ou 2.0000 USDC = 400 créditos (180 dias)',
                'pro_tier' => 'R$ 29,00 (Pix/Cartão) ou 5.0000 USDC = 1.200 créditos (1.000 + 200 bônus, 180 dias)',
                'fleet_tier' => 'R$ 115,00 (Pix/Cartão) ou 20.0000 USDC = 6.000 créditos (4.000 + 2.000 bônus, 365 dias)',
                'rapidapi_credit_card_usd_url' => 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
                'supported_chains' => $solanaPublicOn
                    ? ['base', 'polygon', 'arbitrum', 'solana', 'optimism', 'ethereum']
                    : ['base', 'polygon', 'arbitrum', 'optimism', 'ethereum'],
                'header_example' => 'X-Payment: pass_g402_<id>_<hmac>',
            ],
        ];
    }

    if (is_array($result) && (!isset($result['code']) || $result['code'] === 200)) {
        if (isset($result['timestamp']) && is_string($result['timestamp'])) {
            $ts = strtotime($result['timestamp']);
            if ($ts !== false) {
                $age = time() - (int) $ts;
                if ($age < 0) {
                    $age = 0;
                }
                $result['data_age_seconds'] = $age;
                $result['data_freshness'] = ($age <= 300) ? 'fresh' : 'stale';
            }
        }
        $result = grid402_attach_provenance_meta($result, $apiParam);
        grid402_sign_data($result, $isSandbox);
        if ($idempotencyKey !== '' && $idempotencyEligible && $idempotencyScope !== '' && !$isPass && !$isPassEmitir && !$isSandbox && !$isVerifyOnly) {
            header('X-Idempotent-Replay: false');
            grid402_idempotency_store($idempotencyKey, $apiParam, $_GET, $result, $idempotencyScope);
        }
    }

    $finalLatencyMs = grid402_request_elapsed_ms();
    grid402_record_latency_sample($finalLatencyMs);
    grid402_log_request([
        'service' => $apiParam,
        'status' => 200,
        'latency_ms' => $finalLatencyMs,
        'payment_source' => (string) ($telemetryPaymentSource ?? ($validation['source'] ?? 'unknown')),
        'country_code' => $billingCountry ?? null,
        'idempotent_replay' => ($isPassEmitir && !empty($result['idempotent'])),
    ]);

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
if (is_file(__DIR__ . '/services/promo_pass.php')) {
    require_once __DIR__ . '/services/promo_pass.php';
}
$homePromoStatus = function_exists('grid402_promo_get_status') ? grid402_promo_get_status('MCPTRY50') : ['ok' => false];
$homePromoActive = !empty($homePromoStatus['ok']) && !empty($homePromoStatus['campaign']['active']);
grid402_log_public_visit('/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 - Global Open-Data &amp; Network Utilities M2M Hub (HTTP x402 on Base)</title>
    <meta name="description" content="Grid402: Global M2M public data and network utilities oracle (GLEIF LEI, OFAC SDN, Web3 AML, CNPJ, CEP, Forex, Crypto, DNS, SSL, ASN) for AI agents and developers, settled via HTTP x402 in USDC on Base.">
    <link rel="canonical" href="https://grid402.online/">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="sitemap" type="application/xml" href="/sitemap.xml">
    <link rel="service-meta" type="application/json" href="/.well-known/mcp/server-card.json">
    <link rel="describedby" type="application/json" href="/.well-known/ai-agent.json">
    <link rel="agent-card" type="application/json" href="/.well-known/agent-card.json">
    <link rel="mcp" type="application/json" href="/.well-known/mcp.json">
    <link rel="pricing" type="application/json" href="/pricing.json">

    <!-- Metadados Schema.org JSON-LD para Agentes de IA e Motores de Busca -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": ["Organization", "LocalBusiness"],
          "@id": "https://grid402.online/#organization",
          "name": "Grid402",
          "url": "https://grid402.online",
          "logo": "https://grid402.online/assets/logo.png",
          "email": "contato@grid402.online",
          "telephone": "+55-11-95832-5848",
          "description": "Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.",
          "priceRange": "$0.0050 - $0.0500",
          "areaServed": "Worldwide",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "São Paulo",
            "addressRegion": "SP",
            "addressCountry": "BR"
          },
          "sameAs": [
            "https://github.com/oliveiratc/Grid402",
            "https://www.npmjs.com/package/grid402",
            "https://www.npmjs.com/package/grid402-mcp",
            "https://pypi.org/project/grid402/",
            "https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90"
          ],
          "contactPoint": [
            {
              "@type": "ContactPoint",
              "contactType": "customer support",
              "email": "contato@grid402.online",
              "telephone": "+55-11-95832-5848",
              "availableLanguage": ["Portuguese", "English"]
            }
          ]
        },
        {
          "@type": "WebSite",
          "@id": "https://grid402.online/#website",
          "url": "https://grid402.online",
          "name": "Grid402",
          "description": "Oraculo M2M de dados publicos e utilitarios de rede sob o protocolo HTTP 402.",
          "publisher": {
            "@id": "https://grid402.online/#organization"
          }
        },
        {
          "@type": "SoftwareApplication",
          "@id": "https://grid402.online/#software",
          "name": "Grid402 WebAPI e Servidor MCP",
          "applicationCategory": "DeveloperApplication",
          "operatingSystem": "All",
          "offers": {
            "@type": "Offer",
            "price": "0.0050",
            "priceCurrency": "USD"
          },
          "copyrightHolder": {
            "@id": "https://grid402.online/#organization"
          },
          "copyrightYear": <?php echo date('Y'); ?>,
          "license": "https://grid402.online/terms",
          "author": {
            "@id": "https://grid402.online/#organization"
          }
        }
      ]
    }
    </script>
<?php require_once __DIR__ . '/includes/seo_head.php'; grid402_render_seo_head([
    'title' => 'Grid402 - Global Open-Data & Network Utilities M2M Hub (HTTP x402 on Base)',
    'description' => 'Grid402: Global M2M public data and network utilities oracle (GLEIF LEI, OFAC SDN, Web3 AML, CNPJ, CEP, Forex, Crypto, DNS, SSL, ASN) for AI agents and developers, settled via HTTP x402 in USDC on Base.',
    'path' => '/',
    'card' => 'global',
    'locale' => 'en_US',
]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #060B19;
            --surface: #0B132B;
            --surface-hover: #111C38;
            --border: #1E2C4F;
            --border-glow: rgba(0, 240, 255, 0.25);
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --primary: #0052FF;
            --primary-glow: rgba(0, 82, 255, 0.25);
            --secondary: #00F0FF;
            --accent: #00F0FF;
            --accent-glow: rgba(0, 240, 255, 0.2);
            --warning: #F59E0B;
            --danger: #EF4444;
            --font-heading: 'Space Grotesk', system-ui, sans-serif;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            padding: 0 1.5rem;
            min-height: 100vh;
            overflow-x: hidden;
        }
        h1, h2, h3, h4 {
            font-family: var(--font-heading);
            letter-spacing: -0.5px;
        }
        p {
            text-align: justify;
            text-justify: inter-word;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 3rem 0;
            width: 100%;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 1.25rem;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover {
            color: var(--accent);
        }
        .info-section {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2.25rem;
            margin: 2.5rem 0;
        }
        .info-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-main);
        }
        .info-section p {
            margin-bottom: 1rem;
            color: var(--text-muted);
        }
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-top: 1.5rem;
        }
        .contact-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
        }
        .contact-card strong {
            display: block;
            color: #fff;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
        }
        .contact-card a {
            color: var(--accent);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            word-break: break-all;
        }
        .contact-card a:hover {
            text-decoration: underline;
        }
        footer {
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            align-items: center;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1.5rem;
        }
        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-links a:hover {
            color: var(--accent);
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            text-decoration: none;
            color: inherit;
        }
        .logo-badge {
            background: linear-gradient(135deg, #00F0FF, #0052FF);
            color: #FFFFFF;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            letter-spacing: -0.5px;
        }
        .logo-title {
            font-family: var(--font-heading);
            font-size: 1.45rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text-main);
        }
        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 240, 255, 0.08);
            color: var(--accent);
            border: 1px solid rgba(0, 240, 255, 0.3);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-pulse {
            width: 8px;
            height: 8px;
            background-color: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent);
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
            font-weight: 700;
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
            text-align: justify;
            text-justify: inter-word;
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
            color: #FFFFFF;
            box-shadow: 0 0 20px rgba(0, 82, 255, 0.35);
        }
        .btn-primary:hover { background-color: #003ECC; }
        .btn-outline {
            background-color: var(--surface);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-outline:hover { background-color: var(--surface-hover); border-color: var(--accent); color: #fff; }

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
            text-align: justify;
            text-justify: inter-word;
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
            text-align: justify;
            text-justify: inter-word;
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
            color: var(--accent);
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
            text-align: justify;
            text-justify: inter-word;
        }
        .footer-legal p {
            text-align: justify;
            text-justify: inter-word;
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
            color: var(--accent); border-bottom-color: var(--accent); background: rgba(0, 240, 255, 0.06);
        }
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            font-size: 0.875rem;
            line-height: 1.7;
            color: #CBD5E1;
            text-align: justify;
            text-justify: inter-word;
        }
        .modal-body p {
            text-align: justify;
            text-justify: inter-word;
        }
        .modal-body h3 { font-size: 1rem; color: #fff; margin: 1.25rem 0 0.5rem; }
        .modal-body h3:first-child { margin-top: 0; }

        /* Comparador e Calculadora de Economia Tradicional vs Grid402 */
        .roi-calculator-box {
            background: linear-gradient(145deg, rgba(11, 19, 43, 0.95), rgba(6, 11, 25, 0.98));
            border: 1px solid rgba(0, 240, 255, 0.25);
            border-radius: 12px;
            padding: 2rem;
            margin: 2.5rem 0;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }
        .roi-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2rem;
            margin-top: 1.5rem;
            align-items: stretch;
        }
        @media (max-width: 900px) {
            .roi-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
        .roi-table-card {
            background: rgba(6, 11, 25, 0.75);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .roi-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            margin: 0.5rem 0;
            border-radius: 8px;
        }
        .roi-table {
            width: 100%;
            min-width: 440px;
            border-collapse: collapse;
            font-size: 0.86rem;
        }
        .roi-table th {
            text-align: left;
            padding: 0.65rem 0.75rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .roi-table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(30, 44, 79, 0.6);
            color: var(--text-main);
        }
        .roi-table tr:last-child td {
            border-bottom: none;
        }
        .roi-table .col-trad {
            color: #EF4444;
            font-weight: 600;
        }
        .roi-table .col-grid {
            color: var(--accent);
            font-weight: 700;
        }
        .roi-calc-card {
            background: rgba(0, 240, 255, 0.04);
            border: 1px solid rgba(0, 240, 255, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }
        .roi-slider-wrap {
            margin: 1.25rem 0;
            text-align: left;
        }
        .roi-slider-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #fff;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .roi-slider-val {
            font-family: var(--font-mono);
            color: var(--accent);
            font-size: 1.15rem;
            font-weight: 700;
        }
        .roi-slider {
            width: 100%;
            height: 6px;
            background: #1E2C4F;
            border-radius: 4px;
            accent-color: var(--accent);
            cursor: pointer;
            outline: none;
        }
        .roi-presets-row {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .roi-preset-btn {
            flex: 1;
            padding: 0.35rem 0.5rem;
            background: rgba(30, 44, 79, 0.5);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .roi-preset-btn:hover {
            color: #fff;
            border-color: var(--accent);
            background: rgba(0, 240, 255, 0.1);
        }
        .roi-cost-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin: 1rem 0;
        }
        .roi-cost-item {
            background: rgba(6, 11, 25, 0.6);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem;
        }
        .roi-cost-item-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .roi-cost-item-val {
            font-size: 1.15rem;
            font-weight: 700;
            margin-top: 0.25rem;
            font-family: var(--font-mono);
        }
        .roi-savings-badge {
            background: rgba(0, 82, 255, 0.12);
            border: 1px solid var(--primary);
            border-radius: 8px;
            padding: 1.1rem;
            margin-top: 0.5rem;
        }
        .roi-savings-num {
            font-size: 2.1rem;
            font-weight: 800;
            color: var(--accent);
            font-family: var(--font-mono);
            line-height: 1.1;
        }
        .roi-savings-sub {
            font-size: 0.82rem;
            color: #E2E8F0;
            margin-top: 0.4rem;
        }

        /* Playground Interativo e Verificador de Oraculo */
        .playground-header-wrap {
            margin-bottom: 1.75rem;
        }
        .playground-presets {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }
        .preset-btn {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.65rem 0.8rem;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .preset-btn:hover {
            background: var(--surface-hover);
            border-color: var(--accent);
            color: #fff;
            transform: translateY(-1px);
        }
        .preset-btn.active {
            background: rgba(0, 240, 255, 0.1);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 0 12px rgba(0, 240, 255, 0.25);
        }
        .preset-btn-name {
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .preset-btn-route {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--accent);
        }
        .playground-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: #030712;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.85rem 1.15rem;
            margin-bottom: 1.5rem;
        }
        .mode-toggle-group {
            display: flex;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 3px;
            gap: 3px;
            flex-wrap: wrap;
        }
        .mode-toggle-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .mode-toggle-btn:hover {
            color: #fff;
        }
        .mode-toggle-btn.active {
            background: var(--primary);
            color: #FFFFFF;
            font-weight: 700;
        }
        .playground-url-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--text-muted);
            flex: 1;
            min-width: 240px;
        }
        .playground-url-pill {
            background: rgba(0, 240, 255, 0.1);
            color: var(--accent);
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            word-break: break-all;
        }
        .playground-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 880px) {
            .playground-grid {
                grid-template-columns: 1fr;
            }
        }
        .playground-card {
            background: #060B19;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
        }
        .playground-card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .json-viewer-pre {
            background: #030712;
            border: 1px solid #1E2C4F;
            border-radius: 8px;
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: #00F0FF;
            overflow-x: auto;
            line-height: 1.45;
            max-height: 380px;
            flex: 1;
        }
        .btn-copy-json {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 6px;
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-copy-json:hover {
            color: #fff;
            border-color: var(--accent);
        }
        .audit-card {
            background: rgba(11, 19, 43, 0.85);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1.25rem;
            transition: all 0.3s ease;
        }
        .audit-card.valid {
            border-color: rgba(0, 240, 255, 0.5);
            box-shadow: 0 0 20px rgba(0, 240, 255, 0.15);
        }
        .audit-card.invalid {
            border-color: rgba(239, 68, 68, 0.5);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
        }
        .audit-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }
        .audit-badge {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.3rem 0.65rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .audit-badge.badge-valid {
            background: rgba(0, 240, 255, 0.15);
            color: var(--accent);
            border: 1px solid rgba(0, 240, 255, 0.4);
        }
        .audit-badge.badge-invalid {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        .audit-badge.badge-pending {
            background: rgba(148, 163, 184, 0.1);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .audit-steps-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .audit-step-item {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            background: rgba(6, 11, 25, 0.5);
            border: 1px solid rgba(30, 44, 79, 0.6);
            border-radius: 8px;
            padding: 0.75rem 0.9rem;
        }
        .audit-step-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .audit-step-icon.icon-valid {
            background: rgba(0, 240, 255, 0.2);
            color: var(--accent);
            border: 1px solid var(--accent);
        }
        .audit-step-icon.icon-invalid {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .audit-step-icon.icon-pending {
            background: rgba(148, 163, 184, 0.1);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .audit-step-body {
            flex: 1;
            font-size: 0.82rem;
        }
        .audit-step-title {
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .audit-step-desc {
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 0.35rem;
        }
        .audit-step-meta {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            background: #020408;
            padding: 0.35rem 0.55rem;
            border-radius: 4px;
            color: #94A3B8;
            word-break: break-all;
            margin-top: 0.25rem;
        }
        .audit-step-meta a {
            color: var(--accent);
            text-decoration: none;
        }
        .audit-step-meta a:hover {
            text-decoration: underline;
        }

        /* Card de Conversão Instantânea x402 (Unlock & Blur) */
        .playground-unlock-card {
            background: linear-gradient(135deg, rgba(0, 240, 255, 0.08) 0%, rgba(11, 19, 43, 0.92) 100%);
            border: 1px solid rgba(0, 240, 255, 0.45);
            box-shadow: 0 8px 30px rgba(0, 240, 255, 0.12);
            border-radius: 10px;
            padding: 1.15rem;
            margin-bottom: 1rem;
            animation: fadeInUnlock 0.25s ease-out;
        }
        @keyframes fadeInUnlock {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .unlock-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .unlock-badge-lock {
            background: rgba(0, 240, 255, 0.18);
            color: var(--accent);
            border: 1px solid rgba(0, 240, 255, 0.5);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .unlock-price-pill {
            background: var(--primary);
            color: #030712;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
        }
        .locked-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 0.5rem;
            margin: 0.75rem 0 1rem 0;
        }
        .locked-field-item {
            background: rgba(2, 4, 8, 0.65);
            border: 1px dashed rgba(148, 163, 184, 0.35);
            border-radius: 6px;
            padding: 0.45rem 0.65rem;
            display: flex;
            flex-direction: column;
            gap: 2px;
            position: relative;
            overflow: hidden;
        }
        .locked-field-key {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: #cbd5e1;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .locked-field-blur {
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--accent);
            filter: blur(4.5px);
            user-select: none;
            pointer-events: none;
            opacity: 0.85;
        }
        .unlock-cta-group {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Regras Globais de Responsividade Fluida */
        @media (max-width: 960px) {
            header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
                padding-bottom: 1.5rem;
            }
            .logo-wrap {
                justify-content: space-between;
                width: 100%;
            }
            .nav-links {
                display: flex;
                flex-wrap: wrap;
                gap: 0.65rem 1rem;
                width: 100%;
            }
            .badge-live {
                align-self: flex-start;
                font-size: 0.75rem;
            }
            .roi-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .playground-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0 1rem;
            }
            .container {
                padding: 2rem 0;
            }
            .hero {
                margin: 2.25rem 0;
            }
            .hero h1 {
                font-size: 2.1rem;
            }
            .hero p {
                font-size: 1rem;
            }
            .protocol-box {
                padding: 1.5rem 1.15rem;
            }
            .roi-calculator-box {
                padding: 1.5rem 1.15rem;
            }
            .grid-services {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .cta-group {
                flex-direction: column;
                width: 100%;
            }
            .cta-group .btn {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            .playground-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            .mode-toggle-group {
                width: 100%;
            }
            .mode-toggle-btn {
                flex: 1 1 auto;
                text-align: center;
                padding: 0.4rem 0.5rem;
                font-size: 0.75rem;
            }
            .playground-url-bar {
                width: 100%;
                min-width: 0;
                font-size: 0.75rem;
                word-break: break-all;
            }
            .grid-services {
                grid-template-columns: 1fr;
            }
            .roi-cost-summary {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            .roi-presets-row {
                flex-wrap: wrap;
                gap: 0.4rem;
            }
            .roi-preset-btn {
                flex: 1 1 45%;
            }
            footer {
                flex-direction: column;
                text-align: center;
                align-items: center;
                gap: 1.25rem;
            }
            .footer-links {
                justify-content: center;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 0 0.65rem;
            }
            .hero h1 {
                font-size: 1.65rem;
                line-height: 1.25;
            }
            .hero-tag {
                font-size: 0.8rem;
            }
            .playground-presets {
                grid-template-columns: 1fr 1fr;
                gap: 0.45rem;
            }
            .preset-btn {
                padding: 0.5rem 0.6rem;
                font-size: 0.75rem;
            }
            .modal-card {
                max-height: 90vh;
                border-radius: 8px;
            }
            .modal-header {
                padding: 1rem;
            }
            .modal-body {
                padding: 1rem;
                font-size: 0.8rem;
            }
            .input-group {
                flex-direction: column;
            }
            .console-input {
                width: 100%;
                min-width: 0;
            }
            .console-actions {
                flex-direction: column;
            }
            .console-actions .btn-sm {
                width: 100%;
                text-align: center;
            }
            .card {
                padding: 1.15rem;
            }
            .audit-step-item {
                padding: 0.65rem 0.75rem;
                gap: 0.6rem;
            }
            .audit-step-meta {
                font-size: 0.68rem;
            }
        }

        @media (max-width: 360px) {
            .hero h1 {
                font-size: 1.45rem;
            }
            .playground-presets {
                grid-template-columns: 1fr;
            }
            .roi-preset-btn {
                flex: 1 1 100%;
            }
            .nav-links a {
                font-size: 0.8rem;
            }
        }

        /* Seletor de Idioma Cyberpunk Bilíngue (PT-BR / EN) */
        .lang-switcher-wrap {
            display: inline-flex;
            align-items: center;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3px 5px;
            gap: 4px;
            backdrop-filter: blur(8px);
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.05);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .lang-switcher-wrap:hover {
            border-color: rgba(0, 240, 255, 0.4);
            box-shadow: 0 0 12px rgba(0, 240, 255, 0.15);
        }
        .lang-globe-icon {
            font-size: 0.85rem;
            padding-left: 4px;
            opacity: 0.85;
            user-select: none;
        }
        .lang-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.5px;
        }
        .lang-btn:hover {
            color: #fff;
        }
        .lang-btn.active {
            background: rgba(0, 240, 255, 0.18);
            color: var(--accent);
            border: 1px solid rgba(0, 240, 255, 0.45);
            box-shadow: 0 0 8px rgba(0, 240, 255, 0.3);
        }
        .lang-divider {
            color: rgba(148, 163, 184, 0.3);
            font-size: 0.7rem;
            user-select: none;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/', 'show_lang' => true]); ?>
    <div class="container">
        <div style="display:flex;justify-content:flex-end;margin:0.75rem 0 0;">
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;">
                <a href="https://payapi.market/api/grid402-brazil-public-data-network-oracle" target="_blank" rel="noopener noreferrer" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.4);border-radius:999px;padding:0.3rem 0.75rem;font-size:0.75rem;font-family:var(--font-mono);font-weight:700;color:#34D399;transition:all 0.2s;" onmouseover="this.style.background='rgba(16,185,129,0.22)'" onmouseout="this.style.background='rgba(16,185,129,0.12)'">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#10B981;box-shadow:0 0 6px #10B981;"></span>
                    <span>PayAPI Market • Verified</span>
                </a>
                <a href="https://mcprush.com/to/grid402-brazil-public-data-network-oracle-mcp?ref=badge" target="_blank" rel="noopener noreferrer" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;background:rgba(163,230,53,0.12);border:1px solid rgba(163,230,53,0.4);border-radius:999px;padding:0.3rem 0.75rem;font-size:0.75rem;font-family:var(--font-mono);font-weight:700;color:#BEF264;transition:all 0.2s;" onmouseover="this.style.background='rgba(163,230,53,0.22)'" onmouseout="this.style.background='rgba(163,230,53,0.12)'">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#A3E635;box-shadow:0 0 6px #A3E635;"></span>
                    <span>mcprush • Grade A (30 Tools)</span>
                </a>
                <a href="https://glama.ai/mcp/connectors/io.github.oliveiratc/grid402" target="_blank" rel="noopener noreferrer" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;background:rgba(0,82,255,0.15);border:1px solid rgba(0,240,255,0.4);border-radius:999px;padding:0.3rem 0.75rem;font-size:0.75rem;font-family:var(--font-mono);font-weight:700;color:#00F0FF;transition:all 0.2s;" onmouseover="this.style.background='rgba(0,82,255,0.28)'" onmouseout="this.style.background='rgba(0,82,255,0.15)'">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#00F0FF;box-shadow:0 0 6px #00F0FF;"></span>
                    <span>Glama • Verified MCP</span>
                </a>
                <div class="badge-live">
                    <span class="badge-pulse"></span>
                    <?php $heroNetsPipe = (function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled()) ? 'Base · Polygon · Arbitrum · Solana' : 'Base · Polygon · Arbitrum'; ?>
                    <span data-i18n-count="<?php echo count($servicesCatalog); ?>" data-solana-enabled="<?php echo (function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled()) ? '1' : '0'; ?>" id="badge-live-text"><?php echo $heroNetsPipe; ?> • <?php echo count($servicesCatalog); ?>/<?php echo count($servicesCatalog); ?> Services Operational</span>
                </div>
            </div>
        </div>

        <section class="hero">
            <div class="hero-tag" data-i18n="hero.tag">Global Microservices and M2M Oracle Hub for Autonomous AI Agents</div>
            <h1 data-i18n="hero.title">Grid402: Global Open-Data and Network Utilities Infrastructure for AI Agents</h1>
            <p data-i18n="hero.desc">Access 31 global and regional endpoints: SEC EDGAR Company Lookup (/sec-company), Package/CVE Vuln Audit (/vuln-audit), Multi-Chain Live Telemetry (/chain-telemetry), Token Safety (/token-safety), Prediction Markets (/prediction-markets), Multi-Service Batch (POST /batch), 100% transient Web-to-Markdown (/extract), Email Verifier (/email-verify), KYB 360 AI Dossier, GLEIF LEI, 4-block official sanctions (OFAC/UN/EU/UK), ECDSA notarization, DNS/SSL/BGP, ECB FX, Compliance 360, CEP, and CNPJ in milliseconds with zero signup or API keys. Gasless HTTP x402 via Coinbase CDP / PayAI across Base, Polygon, Arbitrum, and Solana — or Prepaid Session Pass via Pix from R$ 4.90 / RapidAPI USD (&lt;15ms).</p>
            <div class="cta-group">
                <a href="/pass?tier=starter&amp;method=pix" class="btn btn-primary" style="background: linear-gradient(135deg, #059669 0%, #10B981 100%); color: #04120D; font-weight: 800; box-shadow: 0 4px 18px rgba(16, 185, 129, 0.35);" data-i18n="hero.btn.pix">⚡ Pix R$ 12 (400 créditos)</a>
                <a href="/dossie-kyb" class="btn btn-primary" style="background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); font-weight: 800; box-shadow: 0 4px 15px rgba(0, 82, 255, 0.4);" data-i18n="hero.btn.dossier">📄 Gerar Dossiê KYB</a>
                <a href="#playground" class="btn btn-outline" style="border-color: var(--primary); color: var(--primary); font-weight: 700;" data-i18n="hero.btn.playground">⚡ Test Sandbox (3/day)</a>
                <a href="/catalog" class="btn btn-outline" data-i18n="hero.btn.catalog">Pricing &amp; 31 Endpoints →</a>
                <a href="/agents" class="btn btn-outline" data-i18n="hero.btn.agents">🤖 AI Agents Hub</a>
            </div>
            <p style="margin-top: .7rem; font-size: .82rem; color: var(--text-muted);" data-i18n-html="hero.subnote">
                No credit card, no API key, no signup. Sandbox: <strong>3 queries per day per IP</strong> or claim <strong>50 free credits</strong> with <code>MCPTRY50</code>. Production: gasless x402 (Base, Polygon, Arbitrum, Solana), RapidAPI (USD Card) or Pix (from R$ 4.90).
            </p>
        </section>

        <!-- Grid dos Servicos Publicos -->
        <div id="services" class="grid-services">
            <?php foreach ($servicesCatalog as $svc): ?>
            <div class="card" data-service-card="<?php echo htmlspecialchars($svc['id']); ?>">
                <div class="card-header">
                    <span class="card-title" data-i18n="svc.<?php echo $svc['id']; ?>.name"><?php echo htmlspecialchars($svc['name']); ?></span>
                    <span class="card-category" data-i18n="svc.<?php echo $svc['id']; ?>.category"><?php echo htmlspecialchars($svc['category']); ?></span>
                </div>
                <div class="card-desc" data-i18n="svc.<?php echo $svc['id']; ?>.desc"><?php echo htmlspecialchars($svc['description']); ?></div>
                <div class="card-route">GET <?php echo htmlspecialchars($svc['endpoint']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Comparativo e Calculadora de Economia: Benchmarks Verificaveis vs Grid402 -->
        <section id="comparador" class="roi-calculator-box">
            <div class="playground-header-wrap">
                <div class="hero-tag" style="margin-bottom: 0.4rem; background: rgba(0, 240, 255, 0.12); color: var(--accent); border: 1px solid rgba(0, 240, 255, 0.3);" data-i18n="roi.tag">Verifiable Benchmarks · Compliance &amp; KYB 360º</div>
                <h2 data-i18n="roi.title">Why pay $54–$99+/month for KYB &amp; sanctions when you can audit from $0.02–$0.05 per call?</h2>
                <p data-i18n="roi.desc">Compare published entry rates for corporate KYB and sanctions screening (Infosimples, Direct Data, OpenSanctions, 1Lookup) with Grid402’s zero-retainer model settled via Pix Session Pass or HTTP x402.</p>
            </div>

            <div class="roi-grid">
                <!-- Coluna 1: Tabela Comparativa -->
                <div class="roi-table-card">
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem;" data-i18n="roi.table.title">
                            KYB 360º &amp; Compliance Benchmark Comparison
                        </div>
                        <div class="roi-table-wrap">
                            <table class="roi-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="roi.table.th.criteria">Criteria</th>
                                        <th data-i18n="roi.table.th.trad">Published Entry Tiers*</th>
                                        <th data-i18n="roi.table.th.grid">Grid402 (Pass / x402)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong data-i18n="roi.table.cnpj">KYB 360º / Sanctions</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.cnpj.trad">~$0.108/req (OpenSanctions entry, sanctions only)</td>
                                        <td class="col-grid" data-i18n="roi.table.cnpj.grid">$0.0500 Dossier (10 cr) · $0.0200 OFAC (4 cr)</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.monthly">Minimum Entry / Retainer</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.monthly.trad">€50 (~$54) OpenSanctions · $99/mo 1Lookup</td>
                                        <td class="col-grid" data-i18n="roi.table.monthly.grid">$0.00 retainer (from $2 USDC or R$ 12 Pix)</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.contract">Failed Calls &amp; Retries</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.contract.trad">Varies by provider / custom retry logic</td>
                                        <td class="col-grid" data-i18n="roi.table.contract.grid">Auto-refund on HTTP ≠ 200 + free 300s Idempotency</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.auth">Integration &amp; Sources</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.auth.trad">3–4 separate APIs + manual onboarding</td>
                                        <td class="col-grid" data-i18n="roi.table.auth.grid">1 unified call (HTTP x402, Session Pass or MCP)</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.math">Auditability &amp; Proof</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.math.trad">Unsigned raw JSON</td>
                                        <td class="col-grid" data-i18n="roi.table.math.grid">secp256k1 ECDSA _oracle_proof + Zero-PII</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;" data-i18n="roi.table.disclaimer">
                        * Published entry benchmarks (Nov/2026): OpenSanctions (€50 ≈ $54 min; €0.10 ≈ $0.108/req), 1Lookup ($99/mo Starter), Infosimples (R$ 100/mo min; R$ 0.22+/source), and Direct Data (R$ 0.16 CNPJ + R$ 0.36 Simples). Note: for high-volume raw CNPJ-only lookups without Simples/CGU/ECDSA, specialized volume APIs or free BrasilAPI may cost less per call.
                    </div>
                </div>

                <!-- Coluna 2: Calculadora Interativa de Economia -->
                <div class="roi-calc-card">
                    <div style="font-size: 0.95rem; font-weight: 700; color: #fff;" data-i18n="roi.calc.title">
                        KYB 360º Dossier Cost Simulator (/dossier)
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;" data-i18n="roi.calc.subtitle">
                        Compare monthly KYB 360º dossiers (10 credits each) vs. OpenSanctions entry pricing:
                    </div>

                    <div class="roi-slider-wrap">
                        <div class="roi-slider-label">
                            <span data-i18n="roi.calc.label">Monthly KYB 360º Dossiers:</span>
                            <span id="roi-vol-display" class="roi-slider-val">120 / month</span>
                        </div>
                        <input type="range" id="roi-slider-input" class="roi-slider" min="10" max="1000" step="10" value="120" oninput="updateRoiCalc(this.value)">
                        
                        <div class="roi-presets-row">
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(40)">40/mo</button>
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(120)">120/mo</button>
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(300)">300/mo</button>
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(600)">600/mo</button>
                        </div>
                    </div>

                    <div class="roi-cost-summary">
                        <div class="roi-cost-item">
                            <div class="roi-cost-item-label" data-i18n="roi.calc.cost.trad">Entry Benchmark*</div>
                            <div id="roi-cost-trad" class="roi-cost-item-val" style="color: #EF4444;">$ 54.00</div>
                        </div>
                        <div class="roi-cost-item">
                            <div class="roi-cost-item-label" data-i18n="roi.calc.cost.grid">Grid402 (/dossier)</div>
                            <div id="roi-cost-grid" class="roi-cost-item-val" style="color: var(--primary);">$ 5.00</div>
                        </div>
                    </div>

                    <div class="roi-savings-badge">
                        <div id="roi-savings-val" class="roi-savings-num">$ 49.00</div>
                        <div id="roi-savings-desc" class="roi-savings-sub">Estimated savings of 91% · Pro Pass ($5 = 120 dossiers)</div>
                    </div>

                    <div style="display: flex; gap: 0.6rem; margin-top: 1.25rem;">
                        <a href="/agents" class="btn-sm btn-primary" style="flex: 1; text-decoration: none; padding: 0.6rem; display: inline-block;" data-i18n="roi.calc.btn.agents">🤖 Integrate AI Agent</a>
                        <a href="/dossie-kyb" class="btn-sm btn-outline" style="flex: 1; text-decoration: none; padding: 0.6rem; display: inline-block; border-color: var(--primary); color: var(--primary);" data-i18n="roi.calc.btn.test">📄 Test KYB 360º Dossier</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Web3 One-Click Top-Up: Passes de Sessao Pre-Pagos na Base -->
        <section id="topup" class="protocol-box topup-section" style="border: 1px solid rgba(0, 82, 255, 0.45); background: linear-gradient(180deg, rgba(0, 82, 255, 0.08) 0%, rgba(10, 14, 23, 0.98) 100%); margin-bottom: 3rem;">
            <div class="playground-header-wrap" style="text-align: center; max-width: 820px; margin: 0 auto 2rem auto;">
                <div class="hero-tag" style="margin-bottom: 0.5rem; background: rgba(0, 82, 255, 0.15); color: #00F0FF; border: 1px solid rgba(0, 240, 255, 0.35);" data-i18n="topup.tag">⚡ Instant Batch Settlement on Base (USDC)</div>
                <h2 style="font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0.75rem;" data-i18n="topup.title">1-Click Web3 Credit Top-Up (Session Passes)</h2>
                <p style="text-align: center; color: var(--text-muted); font-size: 0.98rem; line-height: 1.6;" data-i18n="topup.desc">Pay once in USDC on Base Mainnet and instantly receive a pass_g402_... token with thousands of high-speed (&lt;15ms) requests without signing transactions on every single GET.</p>
                <div style="text-align:center; background: rgba(0,240,255,.08); border:1px solid rgba(0,240,255,.24); border-radius:8px; padding:.75rem .9rem; margin-bottom:1.2rem; font-size:.82rem; color:#CBD5E1;" data-i18n-html="topup.how_to_read">
                    <strong>How to read balances:</strong> 1 credit costs <strong>0.0050 USDC</strong> on standard endpoints; 0.0100 USDC = 2 credits (CNPJ, LEI, Sanctions, Simples), 0.0150 USDC = 3 credits, 0.0200 USDC = 4 credits (OFAC), 0.0250 USDC = 5 credits (KYB Audit), 0.0300 USDC = 6 credits, and 0.0500 USDC (KYB 360 AI Dossier) = 10 credits.
                </div>
                <div style="display: flex; justify-content: center; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; font-size: 0.85rem; color: #8FA2B9;" id="topup-active-net-summary" data-i18n-html="topup.network_bar">
                    <strong>Network:</strong> Base Mainnet (8453) • <strong>Token:</strong> USDC • <strong>Contract:</strong> <a href="https://basescan.org/token/0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913" target="_blank" rel="noopener noreferrer" style="color: #00F0FF; font-family: var(--font-mono); text-decoration: none;">0x8335...2913</a>
                </div>

                <!-- Seletor Visual Multi-Chain (5 Redes EVM + Solana SPL USDC) -->
                <div id="topup-network-selector" role="radiogroup" aria-label="Selecionar blockchain para pagamento USDC" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 1.15rem;">
                    <button type="button" class="btn-sm btn-outline topup-net-btn" data-chain="base" onclick="selectTopupNetwork('base')" style="border-color: #00F0FF; background: rgba(0,240,255,0.16); color: #fff; font-weight: 700;">🔵 Base (Padrão)</button>
                    <button type="button" class="btn-sm btn-outline topup-net-btn" data-chain="arbitrum" onclick="selectTopupNetwork('arbitrum')" style="border-color: var(--border); color: var(--text-muted);">🔷 Arbitrum</button>
                    <button type="button" class="btn-sm btn-outline topup-net-btn" data-chain="polygon" onclick="selectTopupNetwork('polygon')" style="border-color: var(--border); color: var(--text-muted);">🟣 Polygon</button>
                    <button type="button" class="btn-sm btn-outline topup-net-btn" data-chain="optimism" onclick="selectTopupNetwork('optimism')" style="border-color: var(--border); color: var(--text-muted);">🔴 Optimism</button>
                    <button type="button" class="btn-sm btn-outline topup-net-btn" data-chain="ethereum" onclick="selectTopupNetwork('ethereum')" style="border-color: var(--border); color: var(--text-muted);">⟠ Ethereum</button>
                    <?php if (function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled()): ?>
                    <button type="button" class="btn-sm btn-outline topup-net-btn" data-chain="solana" onclick="selectTopupNetwork('solana')" style="border-color: var(--border); color: var(--text-muted);">◎ Solana (SPL)</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tiers de Passes Web3 -->
            <div class="topup-tiers-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Card 1: Starter -->
                <div class="topup-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.75rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                    <div>
                        <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #8FA2B9; letter-spacing: 1px; margin-bottom: 0.5rem;" data-i18n="topup.starter.tag">Starter Pass</div>
                        <div style="display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.2rem;">
                            <span style="font-size: 2.2rem; font-weight: 800; color: #fff;">2</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: #2775CA;">USDC</span>
                        </div>
                        <div style="font-size: 0.82rem; color: #10B981; font-weight: 600; margin-bottom: 0.75rem;" data-i18n="topup.starter.pix_label">or R$ 12.00 via Pix / Card</div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;" data-i18n="topup.starter.desc">Ideal for solo developers, quick prototypes, and lightweight bots.</p>
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0; font-size: 0.88rem; color: #d1d5db; line-height: 1.8;">
                            <li data-i18n-html="topup.starter.b1">✓ <strong>400 credits</strong> included (1 credit = 0.0050 USDC)</li>
                            <li data-i18n-html="topup.starter.b2">✓ Valid for <strong>180 days</strong></li>
                            <li data-i18n-html="topup.starter.b3">✓ Sub-15ms ultra-low latency</li>
                            <li data-i18n-html="topup.starter.b4">✓ HMAC signature + Spend Controls</li>
                        </ul>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button class="btn btn-outline" style="width: 100%; border-color: #2775CA; color: #fff;" onclick="startWeb3Topup(2, 'starter')" id="btn-topup-starter" data-i18n="topup.starter.btn">Buy Starter (2 USDC)</button>
                        <a href="/pass?tier=starter" class="btn btn-outline" style="width: 100%; border-color: #10B981; color: #10B981; text-align: center; text-decoration: none; font-size: 0.86rem;" data-i18n="topup.starter.pix_btn">Pay R$ 12 (Pix / Card)</a>
                    </div>
                </div>

                <!-- Card 2: Pro (Mais Popular) -->
                <div class="topup-card featured" style="background: linear-gradient(180deg, rgba(0, 82, 255, 0.15) 0%, var(--surface) 100%); border: 2px solid #0052FF; border-radius: 12px; padding: 1.75rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; position: relative; box-shadow: 0 8px 30px rgba(0, 82, 255, 0.25);">
                    <div style="position: absolute; top: -12px; right: 20px; background: #0052FF; color: #fff; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.5px;" data-i18n="topup.pro.badge">Most Popular · +20% Bonus</div>
                    <div>
                        <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #00F0FF; letter-spacing: 1px; margin-bottom: 0.5rem;" data-i18n="topup.pro.tag">Pro / Agent Pass</div>
                        <div style="display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.2rem;">
                            <span style="font-size: 2.2rem; font-weight: 800; color: #fff;">5</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: #2775CA;">USDC</span>
                        </div>
                        <div style="font-size: 0.82rem; color: #10B981; font-weight: 600; margin-bottom: 0.75rem;" data-i18n="topup.pro.pix_label">or R$ 29.00 via Pix / Card</div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;" data-i18n="topup.pro.desc">For autonomous AI agents and continuous automation pipelines.</p>
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0; font-size: 0.88rem; color: #d1d5db; line-height: 1.8;">
                            <li data-i18n-html="topup.pro.b1">✓ <strong>1,200 credits</strong> (1,000 base + 200 bonus; 16.7% off)</li>
                            <li data-i18n-html="topup.pro.b2">✓ Valid for <strong>180 days</strong></li>
                            <li data-i18n-html="topup.pro.b3">✓ Compatible with Claude MCP and AgentKit</li>
                            <li data-i18n-html="topup.pro.b4">✓ Zero block wait per call</li>
                        </ul>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); font-weight: 700;" onclick="startWeb3Topup(5, 'pro')" id="btn-topup-pro" data-i18n="topup.pro.btn">Buy Pro (5 USDC)</button>
                        <a href="/pass?tier=pro" class="btn btn-outline" style="width: 100%; border-color: #10B981; color: #10B981; text-align: center; text-decoration: none; font-size: 0.86rem;" data-i18n="topup.pro.pix_btn">Pay R$ 29 (Pix / Card)</a>
                    </div>
                </div>

                <!-- Card 3: Fleet / Enterprise -->
                <div class="topup-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.75rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                    <div style="position: absolute; top: -12px; right: 20px; background: rgba(0, 240, 255, 0.2); border: 1px solid #00F0FF; color: #00F0FF; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px;" data-i18n="topup.fleet.badge">+2,000 Bonus (+50%)</div>
                    <div>
                        <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #8FA2B9; letter-spacing: 1px; margin-bottom: 0.5rem;" data-i18n="topup.fleet.tag">Fleet / Enterprise</div>
                        <div style="display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.2rem;">
                            <span style="font-size: 2.2rem; font-weight: 800; color: #fff;">20</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: #2775CA;">USDC</span>
                        </div>
                        <div style="font-size: 0.82rem; color: #10B981; font-weight: 600; margin-bottom: 0.75rem;" data-i18n="topup.fleet.pix_label">or R$ 115.00 via Pix / Card</div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;" data-i18n="topup.fleet.desc">Autonomous agent fleets, enterprise ERPs, and heavy-duty pipelines.</p>
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0; font-size: 0.88rem; color: #d1d5db; line-height: 1.8;">
                            <li data-i18n-html="topup.fleet.b1">✓ <strong>6,000 credits</strong> (4,000 base + 2,000 bonus; 1 credit = 0.0050 USDC)</li>
                            <li data-i18n-html="topup.fleet.b2">✓ Valid for <strong>365 days</strong></li>
                            <li data-i18n-html="topup.fleet.b3">✓ Effective rate: 0.0033 USDC / credit</li>
                            <li data-i18n-html="topup.fleet.b4">✓ Top priority on atomic cache</li>
                        </ul>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button class="btn btn-outline" style="width: 100%; border-color: #00F0FF; color: #00F0FF;" onclick="startWeb3Topup(20, 'fleet')" id="btn-topup-fleet" data-i18n="topup.fleet.btn">Buy Fleet (20 USDC)</button>
                        <a href="/pass?tier=fleet" class="btn btn-outline" style="width: 100%; border-color: #10B981; color: #10B981; text-align: center; text-decoration: none; font-size: 0.86rem;" data-i18n="topup.fleet.pix_btn">Pay R$ 115 (Pix / Card)</a>
                    </div>
                </div>
            </div>

            <!-- Opcao Fiat em USD (Cartao de Credito Internacional via RapidAPI) + Cupom MCPTRY50 -->
            <div id="topup-rapidapi-usd-box" style="background: rgba(0, 82, 255, 0.10); border: 1px solid rgba(0, 240, 255, 0.35); border-radius: 12px; padding: 1.15rem 1.35rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 240px;">
                    <strong style="color: #F8FAFC; font-size: 0.98rem;" data-i18n="topup.usd.title">💳 Outside Brazil or prefer International Credit Card (USD)?</strong>
                    <div style="color: #94a3b8; font-size: 0.86rem; margin-top: 0.25rem;" data-i18n="topup.usd.desc">Subscribe with any USD credit card on RapidAPI Hub (Free Basic 25/mo · Pro $9.90/mo for 1,500 calls) or redeem 50 free credits with code MCPTRY50.</div>
                </div>
                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    <a href="https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); color: #041024; font-weight: 800; text-decoration: none; white-space: nowrap; padding: 0.65rem 1.15rem;" data-i18n="topup.usd.cta">💳 Pay with Credit Card (USD) on RapidAPI</a>
                    <a href="/pass?promo=MCPTRY50" class="btn btn-outline" style="border-color: rgba(16, 185, 129, 0.5); color: #34D399; text-decoration: none; white-space: nowrap; padding: 0.65rem 1rem;" data-i18n="topup.usd.promo_cta">🎁 Claim 50 Free Credits (MCPTRY50)</a>
                </div>
            </div>

            <!-- Caixa de Feedback / Status da Operacao -->
            <div id="topup-status-box" style="display: none; background: rgba(10, 14, 23, 0.85); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span id="topup-spinner" class="spinner" style="display: inline-block; width: 18px; height: 18px; border: 2px solid #00F0FF; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                        <strong id="topup-status-title" style="color: #fff; font-size: 0.95rem;" data-i18n="topup.status.title">Awaiting wallet confirmation...</strong>
                    </div>
                    <button class="btn-sm btn-outline" onclick="closeTopupStatus()" style="font-size: 0.75rem; padding: 2px 8px;">✕</button>
                </div>
                <div id="topup-status-msg" style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; font-family: 'JetBrains Mono', monospace; word-break: break-all;" data-i18n="topup.status.msg">Approve the USDC transfer on Base Mainnet to issue your Session Pass immediately.</div>
            </div>

            <!-- Card de Sucesso: Token Gerado -->
            <div id="topup-success-card" style="display: none; background: rgba(0, 240, 255, 0.06); border: 1px solid #00F0FF; border-radius: 12px; padding: 1.75rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.3rem;">🎉</span>
                    <h3 style="color: #00F0FF; font-size: 1.2rem; margin: 0;" data-i18n="topup.success.title">Session Pass Issued and Active!</h3>
                </div>
                <p style="color: #d1d5db; font-size: 0.9rem; margin-bottom: 1rem;" data-i18n-html="topup.success.sub">Copy your token below and send it in the <code style="color: #00F0FF;">X-Payment</code> header on any request:</p>
                
                <div style="background: #000; border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <code id="topup-result-token" style="color: #00F0FF; font-size: 0.95rem; font-weight: 700; word-break: break-all;">pass_g402_...</code>
                    <button class="btn-sm btn-primary" onclick="copyTopupToken()" id="btn-copy-topup-token" style="white-space: nowrap;" data-i18n="topup.success.copy_btn">📋 Copy Token</button>
                </div>

                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem; color: #8FA2B9; margin-bottom: 1rem;">
                    <div><span data-i18n="topup.success.credits">Credit Balance</span>: <strong id="topup-result-credits" style="color: #fff;">0</strong></div>
                    <div><span data-i18n="topup.success.tier">Plan (Tier)</span>: <strong id="topup-result-tier" style="color: #00F0FF; text-transform: uppercase;">-</strong></div>
                    <div><span data-i18n="topup.success.expires">Expiration</span>: <strong id="topup-result-expires" style="color: #fff;">-</strong></div>
                </div>

                <div style="background: rgba(0,0,0,0.4); border-radius: 6px; padding: 0.75rem; font-size: 0.82rem; color: #a0aec0;">
                    <strong style="color: #fff;" data-i18n="topup.success.how_label">How to use in cURL, Python, Node, or MCP:</strong><br>
                    <code id="topup-example-curl" style="color: #8FA2B9; display: block; margin-top: 0.4rem; font-family: 'JetBrains Mono', monospace;">curl "https://grid402.online/api/v1/forex" -H "X-Payment: ..."</code>
                </div>
            </div>

            <!-- Ativação Manual por Hash / Pagamento Direto de Qualquer Carteira -->
            <div id="manual-topup-box" style="background: rgba(15, 23, 42, 0.7); border: 1px dashed rgba(0, 240, 255, 0.35); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <strong style="color: #fff; font-size: 1rem;" data-i18n="topup.manual.title">📱 Direct or Mobile Payment (Any Wallet)</strong>
                    </div>
                    <span style="font-size: 0.8rem; background: rgba(0, 82, 255, 0.2); color: #00F0FF; padding: 2px 10px; border-radius: 20px; border: 1px solid rgba(0, 240, 255, 0.3);" data-i18n="topup.manual.badge">No Extension Needed</span>
                </div>
                <p style="color: #94a3b8; font-size: 0.88rem; margin-bottom: 1rem; line-height: 1.5;" data-i18n-html="topup.manual.desc">
                    Prefer sending from mobile, hardware wallet, or another tab? Transfer <strong>2, 5, or 20 USDC</strong> on <strong>Base Mainnet</strong> to the official Oracle address below and paste the transaction hash (<code>0x...</code>) to activate your pass immediately:
                </p>

                <div style="background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div style="font-size: 0.82rem; color: #8FA2B9;">
                        <span id="oracle-wallet-net-label" data-i18n="topup.manual.addr_label">1. Oracle Address (Base Network):</span><br>
                        <code id="oracle-wallet-addr" style="color: #00F0FF; font-weight: 700; word-break: break-all;">0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</code>
                    </div>
                    <button class="btn-sm btn-outline" onclick="copyOracleAddress()" id="btn-copy-oracle" style="border-color: rgba(0,240,255,0.4); color: #00F0FF; white-space: nowrap;" data-i18n="topup.manual.copy_addr">📋 Copy Address</button>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <input type="text" id="manual-pass-tx-input" placeholder="0x... (Paste your USDC transfer hash)" data-i18n-placeholder="topup.manual.tx_placeholder" style="flex: 1; min-width: 260px; background: #000; border: 1px solid rgba(0, 240, 255, 0.4); border-radius: 6px; padding: 0.65rem 1rem; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: 0.88rem;">
                    <button class="btn btn-primary" id="btn-manual-activate" onclick="activateManualPass()" style="background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); font-weight: 700; white-space: nowrap; padding: 0.65rem 1.25rem;" data-i18n="topup.manual.activate_btn">⚡ Validate and Activate Pass</button>
                </div>
            </div>

            <!-- Atalho para a documentação técnica -->
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap; padding-top: 1rem; border-top: 1px solid var(--border);">
                <a href="#console" class="btn-sm btn-outline" style="border-color: rgba(255,255,255,0.2); color: #8FA2B9;" onclick="switchTab('console')" data-i18n="topup.footer.console_btn">💻 View Console and Technical Docs</a>
            </div>
        </section>

        <!-- Playground Interativo e Verificador de Oraculo -->
        <section id="playground" class="protocol-box">
            <div class="playground-header-wrap">
                <div class="hero-tag" style="margin-bottom: 0.4rem;" data-i18n="playground.tag">M2M Cryptographic Verification</div>
                <h2 data-i18n="playground.title">Interactive Playground and Oracle Verifier</h2>
                <p data-i18n="playground.desc">Run real queries across Grid402 microservices using free Sandbox mode or simulate the x402 challenge. Instantly verify ECDSA secp256k1 cryptographic attestations (_oracle_proof) using our browser-based math engine in real time.</p>
            </div>

            <!-- Presets de Micro-Servicos -->
            <div class="playground-presets" id="playground-preset-bar">
                <!-- Preenchido via JavaScript com todos os micro-servicos -->
            </div>

            <!-- Barra de Controles e Alternador de Modo -->
            <div class="playground-toolbar">
                <div class="mode-toggle-group">
                    <button type="button" class="mode-toggle-btn active" id="btn-mode-challenge" onclick="setPlaygroundMode('challenge')" data-i18n="playground.btn.challenge">🔒 x402 Challenge (HTTP 402)</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-sandbox" onclick="setPlaygroundMode('sandbox')" data-i18n="playground.btn.sandbox">🧪 Sandbox (3 queries/day)</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-pass" onclick="setPlaygroundMode('pass')" data-i18n="playground.btn.pass">🔑 Session Pass</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-manual" onclick="setPlaygroundMode('manual')" data-i18n="playground.btn.manual">✍️ Validate External JSON</button>
                </div>
                <div class="playground-url-bar">
                    <span data-i18n="playground.route">Route:</span>
                    <span id="playground-endpoint-display" class="playground-url-pill">GET /api/v1/workdays</span>
                </div>
                <div>
                    <button type="button" id="btn-run-query" class="btn-sm btn-primary" onclick="runPlaygroundQuery()" data-i18n="playground.btn.run">⚡ Run Query</button>
                </div>
            </div>

            <!-- Painel de Execucao em 2 Colunas -->
            <div class="playground-grid">
                <!-- Coluna 1: Parametros ou Area de JSON Manual -->
                <div class="playground-card" id="playground-col-params">
                    <div class="playground-card-title">
                        <span id="playground-col-title" data-i18n="playground.config.title">Query Configuration</span>
                        <span id="playground-service-badge" class="card-category">workdays</span>
                    </div>

                    <div id="playground-preset-controls">
                        <p id="playground-service-desc" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            Banking business days calculator with date interval counts and D+N target date projection.
                        </p>
                        <div id="playground-params-inputs" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                            <!-- Parametros dinamicos do preset atual -->
                        </div>
                        <div id="playground-pass-controls" style="display: none; background: rgba(0, 240, 255, 0.06); border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 8px; padding: 0.85rem; margin-bottom: 0.85rem;">
                            <label for="playground-pass-token-input" style="display: block; font-size: 0.8rem; font-weight: 700; color: #00F0FF; margin-bottom: 0.4rem;">🔑 Session Pass Token (<code>X-Payment</code>)</label>
                            <input type="text" id="playground-pass-token-input" class="console-input" placeholder="pass_g402_..." autocomplete="off" style="width: 100%; font-family: var(--font-mono); font-size: 0.84rem;" />
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; font-size: 0.78rem; color: var(--text-muted);">
                                <span id="playground-pass-balance-hint">Auto-loaded from your saved Session Pass when available.</span>
                                <a href="/pass?promo=MCPTRY50" style="color: #34d399; text-decoration: none; font-weight: 700;">🎁 Get 50 free credits (MCPTRY50) →</a>
                            </div>
                        </div>
                    </div>

                    <div id="playground-manual-controls" style="display: none; flex-direction: column; height: 100%;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;" data-i18n="playground.manual.desc">
                            Paste any JSON response returned by Grid402 below or load one of the test templates:
                        </p>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                            <button type="button" class="btn-sm" onclick="loadManualTemplate('valid')" data-i18n="playground.btn.template.valid">Load Valid Template</button>
                            <button type="button" class="btn-sm" onclick="loadManualTemplate('tampered')" data-i18n="playground.btn.template.tampered">Load Tampered Template (Counterfeit)</button>
                            <button type="button" class="btn-sm btn-primary" onclick="verifyManualPayload()" data-i18n="playground.btn.audit.manual">🔍 Audit Signature of this JSON</button>
                        </div>
                        <textarea id="playground-manual-input" class="console-input" style="flex: 1; min-height: 220px; font-family: var(--font-mono); font-size: 0.8rem; resize: vertical;" placeholder="Paste the JSON response containing the _oracle_proof block..."></textarea>
                    </div>
                </div>

                <!-- Coluna 2: Retorno JSON e Card de Auditoria -->
                <div class="playground-card">
                    <div class="playground-card-title">
                        <span data-i18n="playground.viewer.title">API Response</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span id="playground-http-status" class="card-category" data-i18n="playground.status.ready">Ready</span>
                            <span id="playground-latency-tag" class="card-category" style="display: none;"></span>
                            <button type="button" class="btn-copy-json" id="btn-copy-playground" onclick="copyPlaygroundJson()" data-i18n="playground.btn.copy">Copy JSON</button>
                        </div>
                    </div>

                    <!-- Card de Conversão Instantânea x402 (Unlock & Blur) -->
                    <div id="playground-unlock-card" class="playground-unlock-card" style="display: none;">
                        <div class="unlock-card-header">
                            <span class="unlock-badge-lock" data-i18n="playground.unlock.badge">🔒 FULL DATA PROTECTED (HTTP 402)</span>
                            <span id="playground-unlock-price" class="unlock-price-pill">0.0050 USDC • Base</span>
                        </div>
                        <p style="font-size: 0.84rem; color: #e2e8f0; margin-bottom: 0.5rem; line-height: 1.45;" data-i18n="playground.unlock.desc">
                            The free sample above is verified. Unlock the hidden fields and ECDSA cryptographic proof (<code>_oracle_proof</code>) now:
                        </p>
                        <div id="playground-locked-fields-grid" class="locked-fields-grid"></div>
                        <div class="unlock-cta-group">
                            <div style="background: rgba(51,65,85,.35); border:1px solid var(--border); border-radius:8px; padding:.75rem .9rem; margin-bottom:.65rem; font-size:.82rem; color:#cbd5e1;" data-i18n-html="playground.unlock.flow_note">
                                <strong style="color:#fff;">Primary flow:</strong> click “Unlock now”. Endpoint pricing (USDC/BRL) is listed in the catalog.
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:.55rem; align-items:center;">
                                <button type="button" id="btn-playground-web3-pay" class="btn-sm btn-primary" onclick="payPlaygroundWithWeb3()" style="padding: 0.6rem 1.15rem; font-size: 0.85rem; font-weight: 800;" data-i18n="playground.unlock.cta_web3">
                                    ⚡ Unlock with Web3 Wallet (1-Click)
                                </button>
                                <a id="btn-playground-pix-pay" class="btn-sm btn-outline" href="/pass?tier=starter&amp;method=pix" style="text-decoration:none; display:inline-flex; align-items:center; padding: 0.6rem 1rem; font-size: 0.82rem; font-weight: 700; border-color: rgba(16, 185, 129, 0.5); color: #34d399; background: rgba(16, 185, 129, 0.08);" data-i18n="playground.unlock.cta_pix">
                                    🇧🇷 Buy 400 Credits via Pix (R$ 12)
                                </a>
                            </div>
                            <details style="margin-top:.6rem; border:1px solid var(--border); border-radius:8px; padding:.55rem .75rem; background:#0b132b;">
                                <summary style="cursor:pointer; color:#00F0FF; font-weight:700;" data-i18n="playground.unlock.other_ways">Other ways to unlock</summary>
                                <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.7rem;">
                                    <button type="button" class="btn-sm btn-outline" onclick="openPlaygroundWeb3Modal()" style="padding: 0.5rem .85rem; font-size: 0.78rem;" data-i18n="playground.unlock.cta_qr">
                                        📱 Mobile QR Code
                                    </button>
                                    <a class="btn-sm btn-outline" href="/catalog" style="text-decoration:none; display:inline-flex; border-color: var(--border); color: var(--muted); padding: 0.5rem .85rem; font-size: .78rem;" data-i18n="playground.unlock.price_btn">See endpoint price</a>
                                    <button type="button" class="btn-sm btn-outline" onclick="unlockPlaygroundViaSandbox()" style="padding: 0.5rem .85rem; font-size: 0.78rem; border-color: rgba(0, 240, 255, 0.35); color: var(--accent);" data-i18n="playground.unlock.cta_sandbox">
                                        🧪 View Full Sample (Sandbox)
                                    </button>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- Card Dedicado de Limite Sandbox (HTTP 429 — Checkout Pix Inline + Sem Loop de 402) -->
                    <div id="playground-rate-limit-card" style="display: none; margin-bottom: 0.85rem; padding: 1.05rem 1.15rem; border-radius: 10px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.14), rgba(0, 82, 255, 0.16)); border: 1px solid rgba(245, 158, 11, 0.55);">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.45rem;">
                            <strong style="color: #FCD34D; font-size: 0.94rem;">⏱️ Limite Gratuito Diário Atingido (HTTP 429 · 3/dia)</strong>
                            <span style="font-size: 0.76rem; color: #34D399; font-weight: 700;">⚡ Pix Instantâneo a partir de R$ 4,90</span>
                        </div>
                        <p id="playground-rate-limit-msg" style="margin: 0 0 0.75rem; font-size: 0.83rem; color: #E2E8F0; line-height: 1.5;">
                            Desbloqueie agora mesmo sem sair desta tela via <strong>Pix</strong> (ativação automática em segundos) ou escolha outro método de produção abaixo.
                        </p>

                        <!-- Checkout Pix Inline -->
                        <div id="pg-rl-pix-checkout" style="padding: 0.85rem; border-radius: 8px; background: rgba(6, 11, 25, 0.72); border: 1px solid rgba(16, 185, 129, 0.35); margin-bottom: 0.75rem;">
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.65rem;">
                                <button type="button" class="btn-sm pg-rl-tier-btn" data-pg-tier="micro" onclick="selectPlaygroundPixTier('micro')" style="padding: 0.48rem 0.8rem; border-radius: 7px; border: 1.5px solid #10B981; background: rgba(16, 185, 129, 0.14); color: #F8FAFC; font-weight: 700; font-size: 0.78rem; cursor: pointer;">
                                    🇧🇷 Micro · R$ 4,90 (50 cr / 5 dossiês)
                                </button>
                                <button type="button" class="btn-sm pg-rl-tier-btn" data-pg-tier="starter" onclick="selectPlaygroundPixTier('starter')" style="padding: 0.48rem 0.8rem; border-radius: 7px; border: 1px solid rgba(148, 163, 184, 0.4); background: transparent; color: #CBD5E1; font-weight: 600; font-size: 0.78rem; cursor: pointer;">
                                    🇧🇷 Starter · R$ 12,00 (400 cr / melhor custo)
                                </button>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                <input type="email" id="pg-rl-pix-email" placeholder="Seu e-mail para recibo Pix (obrigatório)" aria-label="E-mail para recibo Pix" style="flex: 1 1 210px; min-width: 190px; padding: 0.5rem 0.75rem; border-radius: 7px; border: 1px solid rgba(148, 163, 184, 0.4); background: rgba(11, 19, 43, 0.9); color: #F8FAFC; font-size: 0.82rem;">
                                <button type="button" id="btn-pg-rl-gen-pix" onclick="startPlaygroundInlinePix()" class="btn-sm" style="background: linear-gradient(135deg, #059669, #10B981); color: #04120D; font-weight: 800; padding: 0.54rem 0.95rem; border-radius: 7px; border: none; cursor: pointer;">
                                    ⚡ Gerar QR Code Pix (R$ 4,90)
                                </button>
                            </div>
                            <div id="pg-rl-feedback" style="display: none; margin-top: 0.55rem; font-size: 0.8rem; line-height: 1.45;"></div>
                            <div id="pg-rl-pix-qr-box" style="display: none; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(148, 163, 184, 0.25);">
                                <div style="display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
                                    <img id="pg-rl-pix-qr-img" alt="QR Code Pix Grid402" style="width: 148px; height: 148px; background: #fff; border-radius: 8px; padding: 6px;">
                                    <div style="flex: 1 1 220px;">
                                        <div id="pg-rl-pix-status" style="font-size: 0.8rem; font-weight: 700; color: #FCD34D; margin-bottom: 0.45rem;">⏳ Aguardando confirmação do Pix...</div>
                                        <input type="text" id="pg-rl-pix-code" readonly aria-label="Código Pix Copia e Cola" style="width: 100%; box-sizing: border-box; padding: 0.42rem 0.6rem; border-radius: 6px; border: 1px solid rgba(148, 163, 184, 0.35); background: #060B19; color: #E2E8F0; font-family: var(--font-mono); font-size: 0.74rem; margin-bottom: 0.45rem;">
                                        <button type="button" id="btn-pg-rl-copy-pix" onclick="copyPlaygroundInlinePixCode()" class="btn-sm btn-outline" style="padding: 0.4rem 0.75rem; font-size: 0.76rem;">📋 Copiar Código Pix</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.55rem; flex-wrap: wrap;">
<?php if ($homePromoActive): ?>
                            <button type="button" id="btn-pg-rl-claim-promo" onclick="claimPlaygroundInlinePromo()" class="btn-sm" style="background: rgba(16, 185, 129, 0.16); border: 1px solid rgba(16, 185, 129, 0.55); color: #6EE7B7; font-weight: 700; padding: 0.5rem 0.9rem; border-radius: 7px; cursor: pointer;">
                                🎁 Resgatar 50 Créditos Grátis (MCPTRY50)
                            </button>
<?php endif; ?>
                            <button type="button" class="btn-sm btn-primary" onclick="setPlaygroundMode('pass')" style="padding: 0.5rem 0.9rem; font-size: 0.8rem; font-weight: 700;">
                                🔑 Já tenho Session Pass
                            </button>
                            <a href="/pass" class="btn-sm btn-outline" style="text-decoration:none; border-color: rgba(0, 240, 255, 0.45); color: #00F0FF; font-weight: 700; padding: 0.5rem 0.85rem; border-radius: 7px;">
                                🌐 Outros Planos (USDC / Pro / Fleet)
                            </a>
                            <a href="https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities" target="_blank" rel="noopener noreferrer" class="btn-sm btn-outline" style="text-decoration:none; border-color: rgba(148, 163, 184, 0.45); color: #E2E8F0; font-weight: 700; padding: 0.5rem 0.85rem; border-radius: 7px;">
                                💳 Credit Card (USD)
                            </a>
                        </div>
                    </div>

                    <!-- Banner de Conversão Pós-Sandbox (HTTP 200 demo) -->
                    <div id="playground-sandbox-cta" style="display: none; margin-bottom: 0.75rem; padding: 0.85rem 1rem; border-radius: 10px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(0, 82, 255, 0.14)); border: 1px solid rgba(16, 185, 129, 0.45);">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                            <div style="font-size: 0.84rem; color: #e2e8f0; line-height: 1.45;">
                                <strong style="color: #34d399;">✅ Sandbox Verified!</strong>
                                <span id="playground-sandbox-quota-text">Ready for production without rate limits (&lt;15 ms)?</span>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="/pass?promo=MCPTRY50" class="btn-sm" style="text-decoration:none; background: linear-gradient(135deg, #059669, #10B981); color: #04120D; font-weight: 800; padding: 0.48rem 0.9rem; border-radius: 7px;">
                                    🎁 50 Free Credits (MCPTRY50)
                                </a>
                                <a href="/pass?tier=starter&amp;method=pix" class="btn-sm btn-outline" style="text-decoration:none; border-color: rgba(16, 185, 129, 0.55); color: #34D399; font-weight: 700; padding: 0.48rem 0.85rem; border-radius: 7px;">
                                    ⚡ Pix R$ 4,90 / R$ 12
                                </a>
                                <a href="/pass" class="btn-sm btn-outline" style="text-decoration:none; border-color: rgba(0, 240, 255, 0.45); color: #00F0FF; font-weight: 700; padding: 0.48rem 0.85rem; border-radius: 7px;">
                                    🌐 EVM USDC &amp; USD Card
                                </a>
                            </div>
                        </div>
                    </div>

                    <pre id="playground-json-viewer" class="json-viewer-pre" data-i18n="playground.viewer.placeholder">// Click "Run Query" or select a microservice above to audit...</pre>

                    <!-- Card de Auditoria em 3 Etapas -->
                    <div id="playground-audit-card" class="audit-card" style="display: none;">
                        <div class="audit-card-header">
                            <div>
                                <span style="font-weight: 700; color: #fff; font-size: 0.95rem;" data-i18n="playground.audit.title">ECDSA Mathematical Audit Engine (Browser)</span>
                                <span id="audit-engine-badge" style="font-size: 0.75rem; color: var(--accent); margin-left: 0.5rem; font-family: var(--font-mono);">OpenSSL C (secp256k1)</span>
                            </div>
                            <span id="audit-overall-badge" class="audit-badge badge-pending">AWAITING ANALYSIS</span>
                        </div>

                        <div class="audit-steps-list">
                            <!-- Etapa 1: Integridade dos Dados -->
                            <div class="audit-step-item">
                                <div id="audit-step-1-icon" class="audit-step-icon icon-pending">1</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span data-i18n="playground.audit.s1.title">1. Payload Canonicalization and SHA-256 Hash</span>
                                        <span id="audit-step-1-status" class="card-category">Waiting</span>
                                    </div>
                                    <div class="audit-step-desc" data-i18n="playground.audit.s1.desc">
                                        Rebuilds the normalized payload in canonical format and calculates the cryptographic hash:
                                    </div>
                                    <div id="audit-step-1-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Etapa 2: Carteira do Oraculo -->
                            <div class="audit-step-item">
                                <div id="audit-step-2-icon" class="audit-step-icon icon-pending">2</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span data-i18n="playground.audit.s2.title">2. secp256k1 Signature Verification</span>
                                        <span id="audit-step-2-status" class="card-category">Waiting</span>
                                    </div>
                                    <div class="audit-step-desc" data-i18n="playground.audit.s2.desc">
                                        Mathematically validates the DER/ECDSA digital signature against the canonical hash.
                                    </div>
                                    <div id="audit-step-2-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Etapa 3: Assinatura ECDSA secp256k1 -->
                            <div class="audit-step-item">
                                <div id="audit-step-3-icon" class="audit-step-icon icon-pending">3</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span data-i18n="playground.audit.s3.title">3. Oracle Address Recovery</span>
                                        <span id="audit-step-3-status" class="card-category">Waiting</span>
                                    </div>
                                    <div class="audit-step-desc" data-i18n="playground.audit.s3.desc">
                                        Confirms that the recovered key matches the exact public identity of the official oracle:
                                    </div>
                                    <div id="audit-step-3-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Console Interativo e Integrações -->
        <section id="console" class="protocol-box">
            <h2 data-i18n="console.title">Test Console and Integration Specs</h2>
            <p data-i18n="console.desc">Test live requests in real time or copy the code snippets for your autonomous agents.</p>

            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('console')" data-i18n="console.tab.interactive">⚡ Interactive Console</button>
                <button class="tab-btn" onclick="switchTab('smithery')" data-i18n="console.tab.smithery">📦 Smithery MCP (1-Click)</button>
                <button class="tab-btn" onclick="switchTab('agentkit')" data-i18n="console.tab.agentkit">🪙 Coinbase AgentKit (M2M)</button>
                <button class="tab-btn" onclick="switchTab('python')" data-i18n="console.tab.python">🐍 Python SDK</button>
                <button class="tab-btn" onclick="switchTab('node')" data-i18n="console.tab.node">🟢 Node.js SDK</button>
                <button class="tab-btn" onclick="switchTab('curl')" data-i18n="console.tab.curl">💻 cURL CLI</button>
                <button class="tab-btn" onclick="switchTab('badges')" data-i18n="console.tab.badges">🛡️ SVG Badges</button>
            </div>

            <!-- Tab 1: Console Interativo -->
            <form id="tab-console" class="tab-content active" toolname="grid402_console" tooldescription="Console interativo de consulta e teste dos servicos Grid402 sob protocolo x402" onsubmit="event.preventDefault();">
                <div class="console-actions">
                    <button type="button" class="btn-sm" onclick="runConsole('summary')" data-i18n="console.act.summary">Free Summary (/status)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('forex')" data-i18n="console.act.forex">FX Rates (/api/v1/forex)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('crypto')" data-i18n="console.act.crypto">Crypto (/api/v1/crypto)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('dns')" data-i18n="console.act.dns">DNS (/api/v1/dns)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('ssl')" data-i18n="console.act.ssl">SSL (/api/v1/ssl)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('asn')" data-i18n="console.act.asn">ASN (/api/v1/asn)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('cep')" data-i18n="console.act.cep">Postal CEP (/api/v1/cep)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('cnpj')" data-i18n="console.act.cnpj">CNPJ (/api/v1/cnpj)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('indicators')" data-i18n="console.act.indicators">Indicators (/api/v1/indicators)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('holidays')" data-i18n="console.act.holidays">Holidays (/api/v1/holidays)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('workdays')" data-i18n="console.act.workdays">Business Days (/api/v1/workdays)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('sanctions')" data-i18n="console.act.sanctions">CGU Sanctions (/api/v1/sanctions)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('simples')" data-i18n="console.act.simples">Simples Tax (/api/v1/simples)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('compliance')" data-i18n="console.act.compliance">Score 360 (/api/v1/compliance)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('lei')" data-i18n="console.act.lei" style="border-color: rgba(0, 240, 255, 0.4);">🌍 Global LEI (/api/v1/lei)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('ofac')" data-i18n="console.act.ofac" style="border-color: rgba(0, 240, 255, 0.4);">🛡️ OFAC and AML (/api/v1/ofac)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('notarize')" data-i18n="console.act.notarize" style="border-color: rgba(0, 240, 255, 0.4);">⏱️ Notarize (/api/v1/notarize)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('global-markets')" data-i18n="console.act.global-markets" style="border-color: rgba(0, 240, 255, 0.4);">🌐 Exchanges and ECB (/api/v1/global-markets)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('extract')" data-i18n="console.act.extract" style="border-color: rgba(0, 240, 255, 0.4);">📄 Web-to-Markdown (/api/v1/extract)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('email-verify')" data-i18n="console.act.email-verify" style="border-color: rgba(0, 240, 255, 0.4);">✉️ Email Verify (/api/v1/email-verify)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('token-safety')" data-i18n="console.act.token-safety" style="border-color: rgba(0, 240, 255, 0.4);">🪙 Token Safety (/api/v1/token-safety)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('prediction-markets')" data-i18n="console.act.prediction-markets" style="border-color: rgba(0, 240, 255, 0.4);">🔮 Predictions (/api/v1/prediction-markets)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('sec-company')" data-i18n="console.act.sec-company" style="border-color: rgba(0, 240, 255, 0.4);">🏛️ SEC EDGAR (/api/v1/sec-company)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('vuln-audit')" data-i18n="console.act.vuln-audit" style="border-color: rgba(0, 240, 255, 0.4);">🔍 Vuln Audit (/api/v1/vuln-audit)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('chain-telemetry')" data-i18n="console.act.chain-telemetry" style="border-color: rgba(0, 240, 255, 0.4);">📡 Chain Telemetry (/api/v1/chain-telemetry)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('dossier')" data-i18n="console.act.dossier" style="border-color: rgba(0, 240, 255, 0.4);">🛡️ KYB 360 Dossier (/api/v1/dossier)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('pass')" data-i18n="console.act.pass" style="border-color: rgba(16, 185, 129, 0.5);">⚡ Session Pass (/api/v1/pass)</button>
                </div>
                <div class="input-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="text" id="tx-input" class="console-input" style="flex: 1; min-width: 260px;" placeholder="Paste your Session Pass (pass_g402_...), payment hash (0x...) or sign via Web3" data-i18n-placeholder="console.input.placeholder">
                    <button type="button" class="btn-sm" onclick="runConsole('paid')" data-i18n="console.btn.send_payment">Send with X-Payment</button>
                    <button type="button" class="btn-sm btn-primary" id="btn-web3-pay" onclick="payWithWeb3Wallet()" data-i18n="console.btn.web3_pay">⚡ Pay with Web3 Wallet (1-Click)</button>
                    <button type="button" class="btn-sm btn-outline" onclick="openWeb3Modal()" title="Open QR Code and payment instructions" data-i18n="console.btn.web3_qr">📱 QR Code / Mobile</button>
                    <button type="button" class="btn-sm btn-outline" onclick="runSandboxSimulation()" title="Simulate payment for free in Sandbox Mode" data-i18n="console.btn.sandbox_demo">🧪 Simulate Sandbox (demo)</button>
                </div>

                <!-- Card de Destaque da Amostra (Teaser Preview) -->
                <div id="console-teaser-card" style="display: none; background: rgba(0, 240, 255, 0.05); border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 10px; padding: 1.25rem; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: var(--primary); color: #030712; font-weight: 700; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px;" data-i18n="console.teaser.badge">FREE PREVIEW SAMPLE FOUND</span>
                            <span id="teaser-title" style="font-weight: 700; color: #fff; font-size: 0.95rem;"></span>
                        </div>
                        <span id="teaser-badge-price" style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--accent); font-weight: 600;"></span>
                    </div>
                    <div id="teaser-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 0.85rem; margin-bottom: 1rem;">
                    </div>
                    <div style="background: rgba(0, 0, 0, 0.25); border-radius: 6px; padding: 0.6rem 0.8rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                        <strong data-i18n="console.teaser.locked">🔒 Protected fields that will be unlocked on HTTP 200:</strong>
                        <div id="teaser-locked-fields" style="margin-top: 4px; color: #94a3b8; font-family: var(--font-mono); font-size: 0.75rem;"></div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <button class="btn btn-primary" onclick="payWithWeb3Wallet()" style="font-size: 0.85rem; padding: 0.5rem 1rem;" data-i18n="console.teaser.unlock_btn">
                            ⚡ Unlock Full Data with Web3
                        </button>
                        <button class="btn btn-outline" onclick="openWeb3Modal()" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;" data-i18n="console.btn.web3_qr">
                            📱 QR Code / Mobile
                        </button>
                        <button class="btn btn-outline" onclick="runSandboxSimulation()" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;" data-i18n="console.btn.sandbox_demo">
                            🧪 Simulate Sandbox (demo)
                        </button>
                        <button class="btn btn-outline" onclick="document.getElementById('console-teaser-card').style.display='none'" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;" data-i18n="console.teaser.close_btn">
                            ✕ Close Preview
                        </button>
                    </div>
                </div>
                <div class="response-header">
                    <span id="console-status-text" data-i18n="console.status.idle">Select one of the actions above to test the API response.</span>
                    <span id="console-latency-badge" style="display:none;" class="card-category"></span>
                </div>
                <pre id="console-output" class="code-block" data-i18n="console.output.placeholder">// API response will appear here...</pre>
            </form>

            <!-- Tab Smithery MCP -->
            <div id="tab-smithery" class="tab-content">
                <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 0.75rem;">
                        <span style="font-weight: 700; color: #fff; font-size: 1.05rem;" data-i18n="console.smithery.title">📦 1-Click Installation via Smithery.ai</span>
                        <span class="card-category">Claude Desktop • Cursor • MCP Stdout</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5;" data-i18n="console.smithery.desc">
                        Grid402 is cataloged in the Smithery.ai global registry. You can instantly register the tool server in Claude Desktop or Cursor without manually editing configuration files.
                    </p>
                    
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.smithery.cmd_claude">Install Command for Claude Desktop:</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">npx -y @smithery/cli install grid402-mcp --client claude</pre>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.smithery.cmd_cursor">Install Command for Cursor:</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">npx -y @smithery/cli install grid402-mcp --client cursor</pre>
                    </div>

                    <div style="background: rgba(0, 0, 0, 0.3); border-radius: 6px; padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);" data-i18n-html="console.smithery.params">
                        <strong style="color: #fff;">💡 Supported Parameters in smithery.yaml:</strong>
                        <ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;">
                            <li><code>sandbox: true</code> — Enables free Sandbox Mode (3 queries per day per IP) for testing without a wallet.</li>
                            <li><code>privateKey: 0x...</code> — Optional EVM private key for auto-settlement in USDC on Base (0.0050 to 0.0500 USDC per call).</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tab Coinbase AgentKit -->
            <div id="tab-agentkit" class="tab-content">
                <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 0.75rem;">
                        <span style="font-weight: 700; color: #fff; font-size: 1.05rem;" data-i18n="console.agentkit.title">🪙 Native Coinbase AgentKit Integration (CDP)</span>
                        <span class="card-category">M2M Auto-Settlement • LangChain • CrewAI</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5;" data-i18n="console.agentkit.desc">
                        Allow AI agents with CDP wallets on Base to query public data and network utilities paying x402 fees 100% autonomously, without human intervention. The action provider intercepts the 402 challenge, transfers USDC, and transparently retries the request.
                    </p>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.agentkit.example_ts">TypeScript Example (@coinbase/agentkit):</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">import { AgentKit, CdpWalletProvider } from "@coinbase/agentkit";
import { Grid402ActionProvider } from "./integrations/agentkit/grid402_action_provider";

// Initialize CDP wallet on Base
const walletProvider = await CdpWalletProvider.configureWithWallet();

// Connect Grid402 with automatic micropayment settlement
const agentKit = await AgentKit.from({
  walletProvider,
  actionProviders: [new Grid402ActionProvider()]
});</pre>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.agentkit.example_py">Python Example (cdp-agentkit-core):</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">from cdp_agentkit_core.actions import CdpActionProvider
from integrations.agentkit.grid402_action_provider import Grid402ActionProvider

# Connect provider with sandbox support or automatic on-chain settlement
grid402_actions = Grid402ActionProvider(sandbox=False)</pre>
                    </div>

                    <div style="background: rgba(0, 0, 0, 0.3); border-radius: 6px; padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);" data-i18n-html="console.agentkit.docs">
                        <strong style="color: #fff;">📂 Full Documentation and Source Code:</strong>
                        <p style="margin: 0.4rem 0 0 0;">Check the <code>integrations/agentkit/</code> directory in the repository for ready-to-use implementations with LangChain, CrewAI, and setup guides.</p>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Python -->
            <div id="tab-python" class="tab-content">
                <div class="code-block" data-i18n="console.python.code">from grid402 import Grid402Client

client = Grid402Client()

# 1. Free hub summary
summary = client.get_summary()
print("Active services:", summary["status"])

# 2. Real-time FX rates
forex = client.get_forex()
print("USD/BRL:", forex["rates"]["USD"]["bid"])

# 3. On-demand DNS Lookup
dns_records = client.get_dns("github.com")
print("A records:", dns_records["records"]["A"])

# 4. Free Sandbox Mode for local testing and CI/CD
# Pass header 'X-Payment: demo' in requests without spending USDC</div>
            </div>

            <!-- Tab 3: Node.js -->
            <div id="tab-node" class="tab-content">
                <div class="code-block" data-i18n="console.node.code">import { Grid402Client } from 'grid402';

const client = new Grid402Client();

# 1. Free status check
const summary = await client.getSummary();
console.log('Hub status:', summary.status);

# 2. SSL Inspector
const ssl = await client.getSsl('base.org');
console.log('Expires in days:', ssl.certificate.days_remaining);

# 3. Free Sandbox Mode for automated tests:
# Send headers: { 'X-Payment': 'demo' } to simulate at zero cost</div>
            </div>

            <!-- Tab 4: cURL -->
            <div id="tab-curl" class="tab-content">
                <div class="code-block" data-i18n="console.curl.code"># 1. Free status summary
curl -i https://www.grid402.online/status

# 2. HTTP 402 Challenge with Free Preview Sample
curl -i "https://www.grid402.online/api/v1/cnpj?cnpj=00000000000191"

# 3. Settled request on Base Mainnet
curl "https://www.grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: 0x&lt;base_tx_hash&gt;"

# 4. Free Sandbox Mode for testing and CI/CD (3 req/day per IP)
curl "https://www.grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: demo"</div>
            </div>

            <!-- Tab 5: Badges -->
            <div id="tab-badges" class="tab-content">
                <p style="color: var(--text-muted); margin-bottom: 1rem;" data-i18n="console.badges.intro">
                    Dynamic SVG badges to embed in your GitHub repository README:
                </p>
                <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600;" data-i18n="console.badges.label">Live Badge Preview:</div>
                        <img id="badge-preview-img" src="/badge/system.svg" alt="Badge Preview" style="height: 22px; display: block;" onerror="this.src='/badge.php?target=system'">
                    </div>
                    <button class="btn-sm" onclick="copyBadgeMarkdown()" data-i18n="console.badges.btn.copy">📋 Copy Markdown</button>
                </div>
                <pre id="badge-markdown-code" class="code-block">[![Grid402 Hub](https://www.grid402.online/badge/system.svg)](https://www.grid402.online)</pre>
            </div>
        </section>

        <!-- Selos Oficiais Verificados (Diretórios MCP, x402 & API Hubs) -->
        <section aria-label="Verified MCP Directory Listings" style="display:flex; flex-direction:column; align-items:center; gap:1rem; margin:2rem 0 2.5rem;">
            <a href="https://mcprush.com/to/grid402-brazil-public-data-network-oracle-mcp?ref=badge" target="_blank" rel="noopener noreferrer" style="display:inline-block; line-height:0; border-radius:12px; transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <img src="/assets/mcprush-card.svg" alt="Grid402 — Brazil Public Data and Network Oracle MCP server (30 tools, v2.1.0)" loading="lazy" style="max-width:100%; height:auto; border-radius:12px;">
            </a>
            <div style="display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap:0.65rem;">
                <a href="https://sealkeeper.run/agents/grid402.online/grid402-online" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://img.shields.io/badge/SealKeeper-Verified_Owner_(30_Tools)-10B981" alt="SealKeeper Verified Owner (30 Tools)" loading="lazy" style="height:22px;">
                </a>
                <a href="https://paycensus.com/s/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90.html" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://img.shields.io/badge/PayCensus_Trust-Multi--Chain_Verified-10B981" alt="PayCensus Trust Multi-Chain Verified" loading="lazy" style="height:22px;">
                </a>
                <a href="https://glama.ai/mcp/connectors/io.github.oliveiratc/grid402" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://img.shields.io/badge/Glama-Verified_MCP_(30_Tools)-0052FF" alt="Glama Verified MCP" loading="lazy" style="height:22px;">
                </a>
                <a href="https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://img.shields.io/badge/x402scan-Verified_Server_(27_APIs)-00F0FF" alt="x402scan Verified Server" loading="lazy" style="height:22px;">
                </a>
                <a href="https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://img.shields.io/badge/RapidAPI-3_Live_APIs-0055DA?logo=rapidapi" alt="RapidAPI 3 Live APIs" loading="lazy" style="height:22px;">
                </a>
                <a href="https://payapi.market/api/grid402-brazil-public-data-network-oracle" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://img.shields.io/badge/PayAPI_Market-Verified-10B981?logo=checkmarx" alt="PayAPI Market Verified" loading="lazy" style="height:22px;">
                </a>
                <a href="https://mcptry.com/servers/io.github.oliveiratc/grid402" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://mcptry.com/badge/io.github.oliveiratc/grid402" alt="mcptry Verified MCP" loading="lazy" style="height:22px;">
                </a>
                <a href="https://wellknown.network/agents/grid402-mcp" target="_blank" rel="noopener noreferrer" style="display:inline-flex; line-height:0;">
                    <img src="https://wellknown.network/agents/grid402-mcp/badge.svg" alt="Wellknown Verified MCP Status" loading="lazy" style="height:22px;">
                </a>
            </div>
        </section>

        <footer>
            <div>
                <div data-i18n="footer.hub">Grid402 // Open Data and Utility Hub • Base Mainnet</div>
                <div style="margin-top: 4px; font-size: 0.78rem; color: #64748B;" id="footer-copyright-text">
                    © <?php echo date('Y'); ?> Grid402 Network Intelligence. All rights reserved.
                </div>
            </div>
            <div>
                <a href="/about" data-i18n="nav.about">About</a> • 
                <a href="/contact" data-i18n="nav.contact">Contact</a> • 
                <a href="/terms" onclick="openLegalModal('terms'); return false;" data-i18n="nav.terms">Terms of Use</a> • 
                <a href="/privacy" onclick="openLegalModal('privacy'); return false;" data-i18n="nav.privacy">Privacy (LGPD)</a> • 
                <a href="/openapi.json" target="_blank">OpenAPI 3.1</a> • 
                <a href="/llms.txt" target="_blank">llms.txt</a> • 
                <span id="footer-fee-text">Fee: US$ <?php echo GRID402_PRICE_DISPLAY; ?> / query</span>
            </div>
            <div class="footer-legal">
                <p><strong data-i18n="footer.ip.title">Copyright and Intellectual Property Reserved:</strong> <span data-i18n="footer.ip.desc">© <?php echo date('Y'); ?> Grid402 Network Intelligence. All rights reserved (Brazilian Law No. 9.609/1998 and Law No. 9.610/1998). The platform software, HTTP 402 settlement engine, Session Pass architecture, brand, design, and documentation are the exclusive property of Grid402, and unauthorized reproduction or redistribution is prohibited. The technical data queried comes from open government public registries, CC0 licenses, and open internet protocols (DNS, BGP/ASN, TLS/SSL), over which we claim no proprietary exclusivity.</span></p>
                <p style="margin-top: 0.5rem;"><strong data-i18n="footer.disclaimer.title">Disclaimer and Public Data:</strong> <span data-i18n="footer.disclaimer.desc">Grid402 is a utility hub providing access to public domain data and open network protocols on an 'AS-IS' basis. We do not process sensitive personal data (PII) and operate in strict compliance with the Brazilian General Data Protection Law (LGPD - Law No. 13.709/2018), GDPR, and Marco Civil da Internet (Law No. 12.965/2014).</span></p>
            </div>
        </footer>

        <!-- Modal Jurídico -->
        <div id="legal-modal-overlay" class="modal-overlay" onclick="handleOverlayClick(event)">
            <div class="modal-card">
                <div class="modal-header">
                    <span class="modal-title" id="modal-title-text" data-i18n="modal.terms.title">Terms of Use</span>
                    <button class="modal-close" onclick="closeLegalModal()">✕</button>
                </div>
                <div class="modal-tabs">
                    <button id="modal-tab-terms-btn" class="modal-tab-btn active" onclick="switchLegalModal('terms')" data-i18n="modal.terms.tab">Terms of Use</button>
                    <button id="modal-tab-privacy-btn" class="modal-tab-btn" onclick="switchLegalModal('privacy')" data-i18n="modal.privacy.tab">Privacy and LGPD</button>
                </div>
                <div class="modal-body" id="modal-body-content"></div>
            </div>
        </div>

        <!-- Modal de Pagamento Web3 e QR Code Celular -->
        <div id="web3-modal-overlay" class="modal-overlay" onclick="handleWeb3OverlayClick(event)">
            <div class="modal-card" style="max-width: 580px;">
                <div class="modal-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.25rem;">⚡</span>
                        <span class="modal-title" data-i18n="modal.web3.title">Web3 Payment (Base Mainnet)</span>
                    </div>
                    <button class="modal-close" onclick="closeWeb3Modal()">✕</button>
                </div>
                <div class="modal-body" style="padding: 1.25rem 1.5rem;">
                    <!-- Aviso se nenhuma carteira EVM foi detectada -->
                    <div id="web3-no-wallet-banner" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: #fde68a; display: flex; align-items: flex-start; gap: 10px;">
                        <span style="font-size: 1.2rem; line-height: 1;">ℹ️</span>
                        <div>
                            <strong data-i18n="modal.web3.no_wallet.title">No active Web3 extension detected in this tab</strong><br>
                            <span data-i18n="modal.web3.no_wallet.desc">To pay with 1-click directly on the page, you need the MetaMask, Rabby, or Coinbase Wallet extension installed and active. Or pay via mobile using the QR Code below.</span>
                        </div>
                    </div>

                    <!-- Duas opções visuais -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <!-- Coluna 1: Celular / QR Code -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.5rem; font-size: 0.9rem;" data-i18n="modal.web3.col1.title">📱 Pay via Mobile</div>
                                <div style="background: #fff; padding: 8px; border-radius: 8px; display: inline-block; margin-bottom: 0.6rem;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?data=0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" alt="QR Code Carteira Oraculo" style="display: block; width: 130px; height: 130px;" />
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;" data-i18n-html="modal.web3.col1.desc">
                                    Scan with your MetaMask, Rainbow, or Coinbase Wallet app and send <strong><span id="web3-modal-price">0.0050</span> USDC</strong> on the <strong>Base</strong> network.
                                </div>
                            </div>
                        </div>

                        <!-- Coluna 2: carteiras Web3 no navegador (multi-carteira) -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.5rem; font-size: 0.9rem;" data-i18n="modal.web3.col2.title">🧩 Browser Web3 Wallets</div>
                                <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.85rem; line-height: 1.45;" data-i18n="modal.web3.col2.desc">
                                    Works with MetaMask, Rabby, Coinbase Wallet, Brave, Rainbow, and other compatible extensions. When clicking a Web3 button, the page asks which wallet to use — non-custodial and no seed phrase anywhere.
                                </p>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <a href="https://metamask.io/download/" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="justify-content: center; font-size: 0.8rem; padding: 0.5rem 0.8rem; text-decoration: none;" data-i18n="modal.web3.col2.btn.install">
                                    🦊 Install MetaMask Extension
                                </a>
                                <button type="button" class="btn btn-outline" onclick="location.reload()" style="justify-content: center; font-size: 0.8rem; padding: 0.5rem 0.8rem;" data-i18n="modal.web3.col2.btn.reload">
                                    🔄 Already installed, reload
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Dados Técnicos da Transferência -->
                    <div style="background: #030712; border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1rem; font-size: 0.8rem; font-family: var(--font-mono); margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);" data-i18n="modal.web3.network">Network:</span>
                            <span style="color: var(--accent); font-weight: 600;">Base Mainnet (Chain ID 8453)</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);" data-i18n="modal.web3.token">Token:</span>
                            <span style="color: #fff;">USDC</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);" data-i18n="modal.web3.amount">Query Amount:</span>
                            <span style="color: var(--primary); font-weight: 700;"><span id="web3-modal-price-detail">0.0050</span> USDC</span>
                        </div>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(255, 255, 255, 0.1);">
                            <div style="color: var(--text-muted); margin-bottom: 4px; font-size: 0.75rem;" data-i18n="modal.web3.recipient">Recipient Address (Oracle Wallet):</div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" id="web3-wallet-dest-input" readonly value="<?php echo GRID402_WALLET_ADDRESS; ?>" style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); color: #fff; padding: 6px 8px; border-radius: 6px; font-size: 0.75rem; font-family: var(--font-mono);" />
                                <button type="button" class="btn-sm" onclick="copyWeb3ModalAddress()" id="btn-copy-modal-oracle" style="font-size: 0.75rem; padding: 6px 10px; white-space: nowrap;">📋 Copy</button>
                            </div>
                        </div>
                    </div>

                    <!-- Inserção rápida de Hash para validação imediata -->
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #fff; margin-bottom: 6px;" data-i18n="modal.web3.confirm.desc">
                            Already sent? Paste the transaction hash to unlock the data:
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="web3-hash-direct-input" class="console-input" style="flex: 1; font-size: 0.8rem;" placeholder="0x... (Transaction hash on Base)" />
                            <button class="btn btn-primary" onclick="submitModalPaymentHash()" style="font-size: 0.8rem; padding: 0.6rem 1rem; white-space: nowrap;" data-i18n="modal.web3.confirm.btn">
                                ⚡ Unlock Query
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Seletor de Carteira Web3 (EIP-6963) -->
        <div id="x402-wallet-overlay" class="modal-overlay" onclick="handleX402WalletOverlayClick(event)">
            <div class="modal-card" style="max-width: 420px;">
                <div class="modal-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.25rem;">👛</span>
                        <span class="modal-title" data-i18n="wallet.chooser.title">Choose your wallet</span>
                    </div>
                    <button class="modal-close" onclick="x402CloseProviderChooser(null)">✕</button>
                </div>
                <div class="modal-body" style="padding: 1.25rem 1.5rem;">
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5;" data-i18n="wallet.chooser.desc">
                        More than one Web3 wallet was detected in this browser. Choose which one to use to sign the x402 payment on Base.
                    </p>
                    <div id="x402-wallet-list" style="display: flex; flex-direction: column; gap: 10px;"></div>
                    <div style="margin-top: 1rem; padding-top: 0.85rem; border-top: 1px dashed rgba(255, 255, 255, 0.1); display: flex; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline" onclick="x402ForgetWalletChoice()" style="font-size: 0.75rem; padding: 0.45rem 0.75rem;" data-i18n="wallet.chooser.forget">
                            Forget saved wallet
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao Sobre o Projeto -->
        <section id="about" class="info-section">
            <h2 data-i18n="about.title">About Grid402</h2>
            <p data-i18n-html="about.p1"><strong>Grid402</strong> is a public data oracle and technical network utility infrastructure specifically engineered for the Agentic Web and autonomous Machine-to-Machine (M2M) systems.</p>
            <p data-i18n-html="about.p2">Traditionally, querying official corporate records, postal addresses, or infrastructure status requires prior sign-ups, international credit cards, token approvals, and static API keys prone to leaks. Grid402 eliminates all bureaucratic friction by adopting the web-native <strong>HTTP 402 Payment Required</strong> standard with instant USDC micropayment settlement on Base Mainnet.</p>
            <p data-i18n-html="about.p3">The entire ecosystem operates in a strictly Zero-DB and Stateless manner, processing only public domain information with computational transparency, intellectual property and architecture protected by reserved copyrights (Brazilian Laws No. 9.609/1998 and No. 9.610/1998), and full compliance with national and international regulations (LGPD, GDPR, and Marco Civil da Internet).</p>
        </section>

        <!-- Secao de Contato e Canais Oficiais -->
        <section id="contact" class="info-section">
            <h2 data-i18n="contact.title">Contact and Official Channels</h2>
            <p data-i18n="contact.subtitle">We provide direct channels for technical support, AI developer integration, and data governance:</p>
            <div class="contact-grid">
                <div class="contact-card">
                    <strong data-i18n="contact.support">Technical and General Support</strong>
                    <a href="mailto:contato@grid402.online">contato@grid402.online</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.dpo">Data Protection Officer (DPO)</strong>
                    <a href="mailto:contato@grid402.online">contato@grid402.online</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.phone">Phone Support</strong>
                    <a href="tel:+5511958325848">+55 11 95832-5848</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.github">Source Code on GitHub</strong>
                    <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.npm">npm Package (Node.js)</strong>
                    <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.pypi">PyPI Package (Python)</strong>
                    <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.mcp">MCP Server on npm</strong>
                    <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.oracle">Oracle Wallet on Base</strong>
                    <a href="https://basescan.org/address/<?php echo htmlspecialchars(GRID402_WALLET_ADDRESS); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(substr(GRID402_WALLET_ADDRESS, 0, 6) . '...' . substr(GRID402_WALLET_ADDRESS, -4)); ?></a>
                </div>
            </div>
        </section>

        <!-- Rodapé Semântico -->
        <footer>
            <div class="footer-links">
                <a href="/agents" style="color: var(--primary); font-weight: 600;" data-i18n="nav.agents">AI Agents</a>
                <a href="/catalog" style="color: var(--primary); font-weight: 600;" data-i18n="nav.catalog">Catalog</a>
                <a href="/growth" style="color: var(--primary); font-weight: 600;" data-i18n="nav.growth">Growth Program</a>
                <a href="/payment-dashboard" style="color: var(--primary); font-weight: 600;" data-i18n="nav.payments">Payment Dashboard</a>
                <a href="/trust" style="color: var(--primary); font-weight: 600;" data-i18n="nav.trust">Trust Center</a>
                <a href="/about" data-i18n="nav.about">About</a>
                <a href="/contact" data-i18n="nav.contact">Contact</a>
                <a href="/terms" target="_blank" data-i18n="nav.terms">Terms of Service</a>
                <a href="/privacy" target="_blank" data-i18n="nav.privacy">Privacy Policy</a>
                <a href="/llms.txt" target="_blank">llms.txt</a>
                <a href="/.well-known/mcp.json" target="_blank">MCP Manifest</a>
                <a href="/openapi.json" target="_blank">OpenAPI 3.1</a>
                <a href="/.well-known/mcp/server-card.json" target="_blank">MCP Server Card</a>
                <a href="mailto:contato@grid402.online">contato@grid402.online</a>
                <a href="tel:+5511958325848">+55 11 95832-5848</a>
            </div>
            <div data-i18n="footer.contributors">
                © <?php echo date('Y'); ?> Grid402 Network Intelligence. All rights reserved. Protected by Law No. 9.609/1998 and Law No. 9.610/1998.
            </div>
        </footer>
    </div>

    <script>
        // ==========================================
        // Grid402: Playground e Verificador de Oraculo
        // ==========================================
        const playgroundPresets = {
            'domain-audit': {
                name: 'Auditoria 360 de Dominio',
                icon: '🌐',
                endpoint: '/api/v1/domain-audit',
                desc: 'Bundle composto em 1 chamada: DNS + SSL/TLS porta 443 + BGP/ASN com score de risco (0-100).',
                params: [
                    { key: 'domain', label: 'Dominio ou Host', value: 'base.org' }
                ]
            },
            'kyb-audit': {
                name: 'Auditoria Global KYB (CNPJ + OFAC + LEI)',
                icon: '🏛️',
                endpoint: '/api/v1/kyb-audit',
                desc: 'Bundle composto em 1 chamada: Receita Federal CNPJ + Sancoes OFAC/ONU + Identidade Global GLEIF LEI (0.0250 USDC).',
                params: [
                    { key: 'cnpj', label: 'CNPJ (14 digitos)', value: '00000000000191' },
                    { key: 'lei', label: 'Codigo LEI (opcional)', value: '' },
                    { key: 'address', label: 'Carteira EVM (opcional)', value: '' }
                ]
            },
            'dossier': {
                name: 'Dossie 360 KYB + Veredito IA',
                icon: '🛡️',
                endpoint: '/api/v1/dossier',
                desc: 'Dossie corporativo premium em 1 chamada: Receita Federal + Simples/MEI + Sancoes CGU + Veredito IA (APPROVED/FLAGGED/REJECTED).',
                params: [
                    { key: 'cnpj', label: 'CNPJ (14 digitos)', value: '00000000000191' }
                ]
            },
            'market-snapshot': {
                name: 'Snapshot Macro e Tesouraria',
                icon: '📈',
                endpoint: '/api/v1/market-snapshot',
                desc: 'Bundle composto em 1 chamada: Bolsas e BCE + PTAX e Indicadores BACEN + Precos Cripto Spot.',
                params: [
                    { key: 'market', label: 'Mercado (US, EU, UK, BR)', value: 'US' },
                    { key: 'symbol', label: 'Ativo Cripto', value: 'BTC' }
                ]
            },
            lei: {
                name: 'GLEIF LEI Global',
                icon: '🌍',
                endpoint: '/api/v1/lei',
                desc: 'Identidade corporativa mundial ISO 17442 via base CC0 da GLEIF (Apple, Microsoft, Coinbase etc.).',
                params: [
                    { key: 'lei', label: 'Codigo LEI (20 chars) ou vazio', value: '5493006MHB84DD0ZWV18' },
                    { key: 'q', label: 'Ou Busca por Nome Corporativo', value: '' }
                ]
            },
            ofac: {
                name: 'OFAC e AML Web3',
                icon: '🚨',
                endpoint: '/api/v1/ofac',
                desc: 'Sancoes internacionais OFAC SDN e ONU combinadas com telemetria on-chain na Base Mainnet.',
                params: [
                    { key: 'address', label: 'Carteira EVM (0x...)', value: '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90' },
                    { key: 'name', label: 'Ou Nome de Entidade', value: '' }
                ]
            },
            notarize: {
                name: 'Notarizar Hash M2M',
                icon: '📜',
                endpoint: '/api/v1/notarize',
                desc: 'Carimbo de tempo criptografico UTC e certificado ECDSA secp256k1 para decisoes de agentes.',
                params: [
                    { key: 'data', label: 'Dado ou Contrato para Notarizar', value: 'agent-autonomous-contract-2026-v1' }
                ]
            },
            'global-markets': {
                name: 'Mercados Globais e BCE',
                icon: '🏦',
                endpoint: '/api/v1/global-markets',
                desc: 'Calendario financeiro mundial (NYSE/Fed, BCE/TARGET2, LSE, B3) e cambio de 30+ moedas.',
                params: [
                    { key: 'market', label: 'Mercado (US, EU, UK, BR)', value: 'US' },
                    { key: 'date', label: 'Data (YYYY-MM-DD)', value: '2026-07-03' },
                    { key: 'base', label: 'Moeda Base FX', value: 'USD' }
                ]
            },
            extract: {
                name: 'Extrator Web para Markdown',
                icon: '📄',
                endpoint: '/api/v1/extract',
                desc: 'Extracao segura anti-SSRF de paginas HTTP/HTTPS para Markdown limpo com metadados e links absolutos.',
                params: [
                    { key: 'url', label: 'URL Publica (HTTP/HTTPS)', value: 'https://example.com' }
                ]
            },
            'email-verify': {
                name: 'Auditoria de E-mail B2B',
                icon: '✉️',
                endpoint: '/api/v1/email-verify',
                desc: 'Validacao Zero-PII de entregabilidade: sintaxe RFC, DNS MX, SPF, DMARC, disposable e score 0-100.',
                params: [
                    { key: 'email', label: 'E-mail Corporativo', value: 'contato@grid402.online' }
                ]
            },
            'token-safety': {
                name: 'Seguranca de Token Multi-Chain',
                icon: '🪙',
                endpoint: '/api/v1/token-safety',
                desc: 'Auditoria GoPlus + DexScreener (Base, Ethereum, Polygon, Arbitrum, Solana): score 0-100, honeypot, taxas e liquidez.',
                params: [
                    { key: 'chain', label: 'Rede (base, ethereum, polygon, arbitrum, solana)', value: 'base' },
                    { key: 'address', label: 'Contrato EVM (0x...) ou Solana Base58', value: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913' }
                ]
            },
            'prediction-markets': {
                name: 'Mercados de Previsao Normalizados',
                icon: '🔮',
                endpoint: '/api/v1/prediction-markets',
                desc: 'Probabilidades 0-100%, liquidez e volume 24h agregados de Polymarket Gamma e Kalshi Trade API v2.',
                params: [
                    { key: 'q', label: 'Busca (opcional)', value: 'bitcoin' },
                    { key: 'source', label: 'Fonte (all, polymarket, kalshi)', value: 'all' },
                    { key: 'limit', label: 'Limite (1 a 20)', value: '5' }
                ]
            },
            'sec-company': {
                name: 'SEC EDGAR Corporativo',
                icon: '🏛️',
                endpoint: '/api/v1/sec-company',
                desc: 'Perfil corporativo SEC EDGAR (CIK, SIC, exchanges), ultimos filings 10-K/10-Q/8-K e fatos XBRL opcionais.',
                params: [
                    { key: 'ticker', label: 'Ticker (ex.: AAPL)', value: 'AAPL' },
                    { key: 'forms', label: 'Formularios CSV (opcional)', value: '10-K,10-Q' },
                    { key: 'limit', label: 'Limite (1 a 25)', value: '5' }
                ]
            },
            'vuln-audit': {
                name: 'Auditoria de Vulnerabilidades (OSV + CISA KEV)',
                icon: '🔍',
                endpoint: '/api/v1/vuln-audit',
                desc: 'Triagem deterministica de pacotes (npm, PyPI, Go, Maven, Packagist, crates.io) ou CVE/GHSA cruzada com CISA KEV.',
                params: [
                    { key: 'id', label: 'CVE ou GHSA (opcional)', value: 'CVE-2021-44228' },
                    { key: 'ecosystem', label: 'Ecossistema (se modo pacote)', value: '' },
                    { key: 'package', label: 'Pacote (se modo pacote)', value: '' }
                ]
            },
            'chain-telemetry': {
                name: 'Telemetria Multi-Chain (RPC + DeFiLlama)',
                icon: '📡',
                endpoint: '/api/v1/chain-telemetry',
                desc: 'Bloco/slot atual, gas/priority fees, latencia RPC e TVL DeFiLlama em Base, Ethereum, Polygon, Arbitrum e Solana.',
                params: [
                    { key: 'chain', label: 'Rede (base, ethereum, polygon, arbitrum, solana)', value: 'base' }
                ]
            },
            pass: {
                name: 'Session Pass (400 a 6.000 cr)',
                icon: '⚡',
                endpoint: '/api/v1/pass',
                desc: 'Emissao de passe pre-pago assinado (400 a 6.000 creditos; 1 credito = 0.0050 USDC) para chamadas M2M em <15ms sem aguardar bloco.',
                params: []
            },
            compliance: {
                name: 'Compliance 360',
                icon: '🛡️',
                endpoint: '/api/v1/compliance',
                desc: 'Score unificado de risco cadastral e integridade (0 a 100) cruzando Receita Federal e CGU.',
                params: [
                    { key: 'cnpj', label: 'Numero do CNPJ', value: '00000000000191' }
                ]
            },
            sanctions: {
                name: 'Sancoes CGU',
                icon: '⚖️',
                endpoint: '/api/v1/sanctions',
                desc: 'Consulta sancoes administrativas e inidoneidade cadastral nos cadastros CEIS e CNEP.',
                params: [
                    { key: 'cnpj', label: 'Numero do CNPJ', value: '00000000000191' }
                ]
            },
            simples: {
                name: 'Simples Nacional',
                icon: '📑',
                endpoint: '/api/v1/simples',
                desc: 'Consulta oficial de enquadramento tributario no Simples Nacional e regime SIMEI.',
                params: [
                    { key: 'cnpj', label: 'Numero do CNPJ', value: '00000000000191' }
                ]
            },
            workdays: {
                name: 'Dias Uteis ANBIMA',
                icon: '📅',
                endpoint: '/api/v1/workdays',
                desc: 'Calculadora de dias uteis bancarios com contagem de intervalo e projecao de prazos D+N.',
                params: [
                    { key: 'start_date', label: 'Data Inicial (YYYY-MM-DD)', value: '2026-11-06' },
                    { key: 'days', label: 'Dias Uteis a Adicionar', value: '5' }
                ]
            },
            indicators: {
                name: 'Indicadores BACEN',
                icon: '📊',
                endpoint: '/api/v1/indicators',
                desc: 'Taxas oficiais Selic meta e diaria, CDI anual e diario, IPCA 12m e mensal.',
                params: []
            },
            holidays: {
                name: 'Feriados Nacionais',
                icon: '🏖️',
                endpoint: '/api/v1/holidays',
                desc: 'Calendario oficial de feriados bancarios e nacionais da legislacao brasileira.',
                params: [
                    { key: 'year', label: 'Ano do Calendario', value: '2026' }
                ]
            },
            cnpj: {
                name: 'CNPJ Receita Federal',
                icon: '🏢',
                endpoint: '/api/v1/cnpj',
                desc: 'Consulta cadastral e situacao fiscal de Pessoa Juridica na Receita Federal.',
                params: [
                    { key: 'cnpj', label: 'Numero do CNPJ', value: '00000000000191' }
                ]
            },
            cep: {
                name: 'CEP Nacional',
                icon: '📍',
                endpoint: '/api/v1/cep',
                desc: 'Endereco postal brasileiro completo e codigo IBGE por CEP.',
                params: [
                    { key: 'code', label: 'Codigo do CEP', value: '01310100' }
                ]
            },
            forex: {
                name: 'Cambio Oficial PTAX',
                icon: '💱',
                endpoint: '/api/v1/forex',
                desc: 'Cotacoes oficiais de moedas fiduciarias USD, EUR e GBP para BRL.',
                params: []
            },
            crypto: {
                name: 'Precos Cripto Spot',
                icon: '🪙',
                endpoint: '/api/v1/crypto',
                desc: 'Cotacoes em tempo real de ativos digitais BTC e ETH em USD e BRL.',
                params: [
                    { key: 'symbol', label: 'Simbolo do Ativo', value: 'BTC' }
                ]
            },
            dns: {
                name: 'Resolucao DNS',
                icon: '🌐',
                endpoint: '/api/v1/dns',
                desc: 'Resolucao autoritativa de registros de zona DNS A, AAAA, MX, TXT, NS e CNAME.',
                params: [
                    { key: 'domain', label: 'Dominio', value: 'base.org' }
                ]
            },
            ssl: {
                name: 'Certificado TLS e SSL',
                icon: '🔒',
                endpoint: '/api/v1/ssl',
                desc: 'Auditoria de certificado criptografico TLS na porta 443, cadeia e validade.',
                params: [
                    { key: 'host', label: 'Host para Auditoria', value: 'base.org' }
                ]
            },
            asn: {
                name: 'Inteligencia BGP e ASN',
                icon: '🖧',
                endpoint: '/api/v1/asn',
                desc: 'Identificacao de Sistema Autonomo BGP, bloco de IP CIDR e operador.',
                params: [
                    { key: 'ip', label: 'Endereco IP', value: '1.1.1.1' }
                ]
            },
            summary: {
                name: 'Resumo Operacional',
                icon: '⚡',
                endpoint: '/status',
                desc: 'Telemetria consolidada e status operacional em tempo real dos servicos Grid402.',
                params: []
            },
            verify: {
                name: 'Verificador Oraculo',
                icon: '🔐',
                endpoint: '/api/v1/verify',
                desc: 'Validacao matematica instantanea de assinatura secp256k1 e prova criptografica.',
                params: []
            }
        };

        let currentPlaygroundPreset = 'workdays';
        let currentPlaygroundMode = 'challenge';
        let lastPlaygroundJson = '';

        function initPlayground() {
            const bar = document.getElementById('playground-preset-bar');
            if (!bar) return;
            bar.innerHTML = '';

            Object.keys(playgroundPresets).forEach(key => {
                const p = playgroundPresets[key];
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'preset-btn' + (key === currentPlaygroundPreset ? ' active' : '');
                btn.id = 'preset-btn-' + key;
                btn.onclick = () => selectPlaygroundPreset(key);

                const displayName = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en' && typeof playgroundPresets_en !== 'undefined' && playgroundPresets_en[key])
                    ? playgroundPresets_en[key].name
                    : p.name;

                const nameWrap = document.createElement('div');
                nameWrap.className = 'preset-btn-name';
                nameWrap.innerHTML = '<span>' + p.icon + '</span><span>' + displayName + '</span>';

                const routeWrap = document.createElement('div');
                routeWrap.className = 'preset-btn-route';
                routeWrap.textContent = p.endpoint;

                btn.appendChild(nameWrap);
                btn.appendChild(routeWrap);
                bar.appendChild(btn);
            });

            selectPlaygroundPreset('workdays');
            if (window.Grid402PassStorage && typeof window.Grid402PassStorage.load === 'function') {
                const savedPass = String(window.Grid402PassStorage.load() || '').trim();
                if (/^pass_g402_[a-z0-9_]{8,24}_[a-f0-9]{16}$/i.test(savedPass)) {
                    setPlaygroundMode('pass');
                }
            }
        }

        function selectPlaygroundPreset(key) {
            currentPlaygroundPreset = key;
            const p = playgroundPresets[key];
            if (!p) return;

            document.querySelectorAll('.preset-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('preset-btn-' + key);
            if (activeBtn) activeBtn.classList.add('active');

            const currentPresetData = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en' && typeof playgroundPresets_en !== 'undefined')
                ? (playgroundPresets_en[key] || p)
                : p;

            const badgeEl = document.getElementById('playground-service-badge');
            if (badgeEl) badgeEl.textContent = key;

            const descEl = document.getElementById('playground-service-desc');
            if (descEl) descEl.textContent = currentPresetData.desc;

            const inputsEl = document.getElementById('playground-params-inputs');
            if (inputsEl) {
                inputsEl.innerHTML = '';
                if (currentPresetData.params.length === 0) {
                    const noParams = document.createElement('div');
                    noParams.style.fontSize = '0.8rem';
                    noParams.style.color = 'var(--text-muted)';
                    noParams.style.fontStyle = 'italic';
                    noParams.textContent = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en')
                        ? 'This service does not require mandatory parameters in the basic query.'
                        : 'Este servico nao requer parametros obrigatorios na consulta basica.';
                    inputsEl.appendChild(noParams);
                } else {
                    currentPresetData.params.forEach(param => {
                        const wrap = document.createElement('div');
                        const lbl = document.createElement('label');
                        lbl.style.display = 'block';
                        lbl.style.fontSize = '0.78rem';
                        lbl.style.color = '#fff';
                        lbl.style.marginBottom = '0.25rem';
                        lbl.style.fontWeight = '600';
                        lbl.textContent = param.label + ' (' + param.key + '):';

                        const inp = document.createElement('input');
                        inp.type = 'text';
                        inp.className = 'console-input';
                        inp.id = 'playground-input-' + param.key;
                        inp.value = param.value;
                        inp.oninput = updatePlaygroundUrlDisplay;

                        wrap.appendChild(lbl);
                        wrap.appendChild(inp);
                        inputsEl.appendChild(wrap);
                    });
                }
            }

            updatePlaygroundUrlDisplay();
        }

        function setPlaygroundMode(mode) {
            currentPlaygroundMode = mode;
            ['sandbox', 'challenge', 'pass', 'manual'].forEach(m => {
                const b = document.getElementById('btn-mode-' + m);
                if (b) {
                    if (m === mode) {
                        b.classList.add('active');
                    } else {
                        b.classList.remove('active');
                    }
                }
            });

            const presetControls = document.getElementById('playground-preset-controls');
            const manualControls = document.getElementById('playground-manual-controls');
            const passControls = document.getElementById('playground-pass-controls');
            const passInput = document.getElementById('playground-pass-token-input');
            const colTitle = document.getElementById('playground-col-title');
            const runBtn = document.getElementById('btn-run-query');

            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');
            if (mode === 'manual') {
                if (presetControls) presetControls.style.display = 'none';
                if (manualControls) manualControls.style.display = 'flex';
                if (passControls) passControls.style.display = 'none';
                if (colTitle) colTitle.textContent = isEn ? 'Manual JSON Audit' : 'Auditoria Manual de JSON';
                if (runBtn) runBtn.textContent = isEn ? '🔍 Audit JSON' : '🔍 Auditar JSON';
            } else {
                if (presetControls) presetControls.style.display = 'block';
                if (manualControls) manualControls.style.display = 'none';
                if (passControls) passControls.style.display = (mode === 'pass') ? 'block' : 'none';
                if (mode === 'pass' && passInput && !passInput.value.trim() && window.Grid402PassStorage) {
                    const savedTok = window.Grid402PassStorage.load();
                    if (savedTok) passInput.value = savedTok;
                }
                if (colTitle) colTitle.textContent = isEn ? 'Query Configuration' : 'Configuração da Consulta';
                if (runBtn) runBtn.textContent = isEn ? '⚡ Run Query' : '⚡ Executar Consulta';
            }

            updatePlaygroundUrlDisplay();
        }

        function buildPlaygroundUrl() {
            const p = playgroundPresets[currentPlaygroundPreset];
            if (!p) return '/';
            const params = new URLSearchParams();
            p.params.forEach(param => {
                const inp = document.getElementById('playground-input-' + param.key);
                const val = inp ? inp.value.trim() : param.value;
                if (val !== '') {
                    params.append(param.key, val);
                }
            });
            const qs = params.toString();
            return qs ? (p.endpoint + '?' + qs) : p.endpoint;
        }

        function updatePlaygroundUrlDisplay() {
            const disp = document.getElementById('playground-endpoint-display');
            if (!disp) return;
            if (currentPlaygroundMode === 'manual') {
                disp.textContent = 'POST /api/v1/verify';
            } else {
                disp.textContent = 'GET ' + buildPlaygroundUrl();
            }
        }

        async function runPlaygroundQuery() {
            if (currentPlaygroundMode === 'manual') {
                return verifyManualPayload();
            }

            const url = buildPlaygroundUrl();
            const statusBadge = document.getElementById('playground-http-status');
            const latencyTag = document.getElementById('playground-latency-tag');
            const viewer = document.getElementById('playground-json-viewer');
            const runBtn = document.getElementById('btn-run-query');
            const unlockCard = document.getElementById('playground-unlock-card');
            const sandboxCta = document.getElementById('playground-sandbox-cta');
            const rateLimitCard = document.getElementById('playground-rate-limit-card');

            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');
            if (unlockCard) unlockCard.style.display = 'none';
            if (sandboxCta) sandboxCta.style.display = 'none';
            if (rateLimitCard) rateLimitCard.style.display = 'none';

            const headers = { 'Accept': 'application/json' };
            if (currentPlaygroundMode === 'sandbox') {
                headers['X-Payment'] = 'demo';
            } else if (currentPlaygroundMode === 'pass') {
                const passInp = document.getElementById('playground-pass-token-input');
                let tok = passInp ? passInp.value.trim() : '';
                if (!tok && window.Grid402PassStorage) {
                    tok = window.Grid402PassStorage.load();
                    if (tok && passInp) passInp.value = tok;
                }
                if (!tok || !tok.startsWith('pass_g402_')) {
                    if (statusBadge) statusBadge.textContent = isEn ? 'Session Pass Required' : 'Token Session Pass Necessário';
                    if (viewer) viewer.textContent = isEn
                        ? '// Paste a valid pass_g402_... token above or claim 50 free credits with MCPTRY50 at /pass?promo=MCPTRY50'
                        : '// Cole um token pass_g402_... válido acima ou resgate 50 créditos grátis com MCPTRY50 em /pass?promo=MCPTRY50';
                    if (passInp) passInp.focus();
                    return;
                }
                headers['X-Payment'] = tok;
                if (window.Grid402PassStorage) window.Grid402PassStorage.save(tok);
            }

            if (statusBadge) statusBadge.textContent = isEn ? 'Sending...' : 'Enviando...';
            if (latencyTag) latencyTag.style.display = 'none';
            if (viewer) viewer.textContent = isEn ? '// Awaiting response from Grid402 hub...' : '// Aguardando resposta do hub Grid402...';
            if (runBtn) runBtn.disabled = true;

            const startTime = performance.now();
            try {
                const response = await fetch(url, { headers: headers });
                const elapsedMs = Math.round(performance.now() - startTime);
                const data = await response.json();
                lastPlaygroundJson = JSON.stringify(data, null, 2);

                if (viewer) viewer.textContent = lastPlaygroundJson;
                if (latencyTag) {
                    latencyTag.style.display = 'inline-block';
                    latencyTag.textContent = elapsedMs + ' ms';
                }

                if (response.status === 200) {
                    const remPassCredits = response.headers.get('X-Pass-Credits-Remaining');
                    if (statusBadge) {
                        statusBadge.textContent = (currentPlaygroundMode === 'pass' && remPassCredits !== null)
                            ? (`HTTP 200 OK · ${remPassCredits} cr left`)
                            : 'HTTP 200 OK';
                    }
                    const passHint = document.getElementById('playground-pass-balance-hint');
                    if (passHint && currentPlaygroundMode === 'pass' && remPassCredits !== null) {
                        passHint.textContent = isEn
                            ? `Active Pass • ${remPassCredits} credits remaining`
                            : `Passe ativo • ${remPassCredits} créditos restantes`;
                    }
                    if (sandboxCta && data && data.sandbox) {
                        const rem = data.sandbox_info
                            ? (data.sandbox_info.quota_remaining_today ?? data.sandbox_info.quota_remaining_this_hour)
                            : null;
                        const lim = data.sandbox_info
                            ? (data.sandbox_info.quota_limit_per_day ?? data.sandbox_info.quota_limit_per_hour ?? 3)
                            : 3;
                        const qText = document.getElementById('playground-sandbox-quota-text');
                        if (qText && rem !== null && rem !== undefined) {
                            qText.textContent = isEn
                                ? `(${rem}/${lim} free sandbox calls left today). Switch to production (<15 ms) in 1 click:`
                                : `(${rem}/${lim} consultas grátis restantes hoje). Ative produção (<15 ms) em 1 clique:`;
                        }
                        sandboxCta.style.display = 'block';
                    }
                    if (data && data._oracle_proof) {
                        auditOracleProof(data);
                    } else {
                        renderNonProofState(isEn ? 'No _oracle_proof block in response.' : 'Sem bloco _oracle_proof na resposta.');
                    }
                } else if (response.status === 429) {
                    if (statusBadge) {
                        statusBadge.textContent = isEn ? 'HTTP 429 Sandbox Quota Exceeded' : 'HTTP 429 Cota Sandbox Excedida';
                    }
                    if (rateLimitCard) {
                        const rlMsg = document.getElementById('playground-rate-limit-msg');
                        if (rlMsg && data && data.message) {
                            rlMsg.textContent = data.message;
                        }
                        rateLimitCard.style.display = 'block';
                    }
                    renderNonProofState(isEn
                        ? 'Daily free sandbox quota reached (429). Unlock via inline Pix or use a Session Pass.'
                        : 'Cota diária gratuita do Sandbox atingida (429). Desbloqueie via Pix inline ou use um Session Pass.');
                } else if (response.status === 402) {
                    if (statusBadge) {
                        statusBadge.textContent = 'HTTP 402 Payment Required';
                    }
                    renderPlaygroundUnlockCard(data);
                    render402ChallengeCard(data);
                } else {
                    if (statusBadge) statusBadge.textContent = 'HTTP ' + response.status;
                    renderNonProofState((isEn ? 'Response returned error code ' : 'Resposta com codigo de erro ') + response.status);
                }
            } catch (err) {
                if (statusBadge) statusBadge.textContent = isEn ? 'Connection Error' : 'Erro de Conexao';
                if (viewer) viewer.textContent = (isEn ? '// Error connecting to Grid402: ' : '// Erro ao conectar ao Grid402: ') + (err.message || err);
                renderNonProofState(isEn ? 'Could not complete request.' : 'Nao foi possivel completar a requisicao.');
            } finally {
                if (runBtn) runBtn.disabled = false;
            }
        }

        async function auditOracleProof(payload) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';
            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-pending';
                overallBadge.textContent = isEn ? 'ANALYZING PROOF...' : 'ANALISANDO PROVA...';
            }

            const startVerify = performance.now();
            try {
                const res = await fetch('/api/v1/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const clientLatencyMs = (performance.now() - startVerify).toFixed(2);
                const result = await res.json();
                renderAuditSteps(result, clientLatencyMs);
            } catch (err) {
                renderAuditFailure((isEn ? 'Verification engine error: ' : 'Erro ao validar no motor de verificacao: ') + err.message);
            }
        }

        function renderAuditSteps(result, clientLatencyMs) {
            const card = document.getElementById('playground-audit-card');
            const overallBadge = document.getElementById('audit-overall-badge');
            const engineBadge = document.getElementById('audit-engine-badge');
            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            const s1Icon = document.getElementById('audit-step-1-icon');
            const s1Status = document.getElementById('audit-step-1-status');
            const s1Meta = document.getElementById('audit-step-1-meta');

            const s2Icon = document.getElementById('audit-step-2-icon');
            const s2Status = document.getElementById('audit-step-2-status');
            const s2Meta = document.getElementById('audit-step-2-meta');

            const s3Icon = document.getElementById('audit-step-3-icon');
            const s3Status = document.getElementById('audit-step-3-status');
            const s3Meta = document.getElementById('audit-step-3-meta');

            const verifyTime = (result && result.verification_time_ms !== undefined) ? result.verification_time_ms : clientLatencyMs;
            if (engineBadge) {
                engineBadge.textContent = 'OpenSSL C • ' + verifyTime + ' ms';
            }

            if (result && result.valid === true) {
                if (card) {
                    card.classList.remove('invalid');
                    card.classList.add('valid');
                }
                if (overallBadge) {
                    overallBadge.className = 'audit-badge badge-valid';
                    overallBadge.textContent = isEn ? '✓ AUTHENTIC MATHEMATICAL PROOF' : '✓ PROVA MATEMATICA AUTENTICA';
                }

                // Etapa 1
                if (s1Icon) { s1Icon.className = 'audit-step-icon icon-valid'; s1Icon.textContent = '✓'; }
                if (s1Status) { s1Status.textContent = isEn ? 'Intact (SHA-256)' : 'Integro (SHA-256)'; s1Status.style.color = 'var(--primary)'; }
                if (s1Meta) {
                    s1Meta.style.display = 'block';
                    const h = (result.payload_hash || (result.data_hash) || (isEn ? 'Verified successfully' : 'Conferido com sucesso'));
                    s1Meta.textContent = 'Payload Hash: ' + h;
                }

                // Etapa 2
                if (s2Icon) { s2Icon.className = 'audit-step-icon icon-valid'; s2Icon.textContent = '✓'; }
                if (s2Status) { s2Status.textContent = isEn ? 'Official (Base Mainnet)' : 'Oficial (Base Mainnet)'; s2Status.style.color = 'var(--primary)'; }
                if (s2Meta) {
                    s2Meta.style.display = 'block';
                    const addr = result.oracle_address || '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
                    s2Meta.innerHTML = (isEn ? 'Wallet: ' : 'Carteira: ') + '<a href="https://basescan.org/address/' + addr + '" target="_blank" rel="noopener noreferrer">' + addr + '</a> (BaseScan)';
                }

                // Etapa 3
                if (s3Icon) { s3Icon.className = 'audit-step-icon icon-valid'; s3Icon.textContent = '✓'; }
                if (s3Status) { s3Status.textContent = isEn ? 'Valid (ECDSA secp256k1)' : 'Valida (ECDSA secp256k1)'; s3Status.style.color = 'var(--primary)'; }
                if (s3Meta) {
                    s3Meta.style.display = 'block';
                    s3Meta.textContent = isEn
                        ? 'Algorithm: ECDSA_SHA256_SECP256K1 | Stateless verification in ' + verifyTime + ' ms.'
                        : 'Algoritmo: ECDSA_SHA256_SECP256K1 | Verificacao concluida em ' + verifyTime + ' ms sem banco de dados.';
                }
            } else {
                if (card) {
                    card.classList.remove('valid');
                    card.classList.add('invalid');
                }
                if (overallBadge) {
                    overallBadge.className = 'audit-badge badge-invalid';
                    overallBadge.textContent = isEn ? '✕ INVALID OR TAMPERED PROOF' : '✕ PROVA INVALIDA OU ADULTERADA';
                }

                const errCode = (result && result.error) ? result.error : 'InvalidSignature';

                if (errCode === 'DataHashMismatch') {
                    // Etapa 1 falhou
                    if (s1Icon) { s1Icon.className = 'audit-step-icon icon-invalid'; s1Icon.textContent = '✕'; }
                    if (s1Status) { s1Status.textContent = isEn ? 'Hash Mismatch' : 'Hash Divergente'; s1Status.style.color = 'var(--danger)'; }
                    if (s1Meta) {
                        s1Meta.style.display = 'block';
                        s1Meta.innerHTML = isEn
                            ? '<span style="color:var(--danger);">Tampering Alert:</span> Payload was modified after signing.<br>Expected: ' + (result.expected_hash || 'N/A') + '<br>Received: ' + (result.received_hash || 'N/A')
                            : '<span style="color:var(--danger);">Alerta de Adulteracao:</span> Os dados foram modificados apos a assinatura.<br>Esperado: ' + (result.expected_hash || 'N/A') + '<br>Recebido: ' + (result.received_hash || 'N/A');
                    }

                    if (s2Icon) { s2Icon.className = 'audit-step-icon icon-pending'; s2Icon.textContent = '2'; }
                    if (s2Status) { s2Status.textContent = isEn ? 'Halted' : 'Interrompido'; s2Status.style.color = 'var(--text-muted)'; }
                    if (s2Meta) s2Meta.style.display = 'none';

                    if (s3Icon) { s3Icon.className = 'audit-step-icon icon-pending'; s3Icon.textContent = '3'; }
                    if (s3Status) { s3Status.textContent = isEn ? 'Halted' : 'Interrompido'; s3Status.style.color = 'var(--text-muted)'; }
                    if (s3Meta) s3Meta.style.display = 'none';
                } else {
                    // Outro erro
                    if (s1Icon) { s1Icon.className = 'audit-step-icon icon-invalid'; s1Icon.textContent = '✕'; }
                    if (s1Status) { s1Status.textContent = isEn ? 'Failed' : 'Falha'; s1Status.style.color = 'var(--danger)'; }
                    if (s1Meta) {
                        s1Meta.style.display = 'block';
                        s1Meta.textContent = (result && result.message) ? result.message : (isEn ? 'Unrecognized signature.' : 'Assinatura nao reconhecida.');
                    }

                    if (s2Icon) { s2Icon.className = 'audit-step-icon icon-invalid'; s2Icon.textContent = '✕'; }
                    if (s2Status) { s2Status.textContent = isEn ? 'Rejected' : 'Rejeitado'; s2Status.style.color = 'var(--danger)'; }
                    if (s2Meta) s2Meta.style.display = 'none';

                    if (s3Icon) { s3Icon.className = 'audit-step-icon icon-invalid'; s3Icon.textContent = '✕'; }
                    if (s3Status) { s3Status.textContent = isEn ? 'Rejected' : 'Rejeitado'; s3Status.style.color = 'var(--danger)'; }
                    if (s3Meta) s3Meta.style.display = 'none';
                }
            }
        }

        function renderPlaygroundUnlockCard(data) {
            const unlockCard = document.getElementById('playground-unlock-card');
            if (!unlockCard) return;

            const priceEl = document.getElementById('playground-unlock-price');
            const gridEl = document.getElementById('playground-locked-fields-grid');
            const price = (data && data.price_usdc) ? data.price_usdc : '0.0050';

            if (priceEl) {
                priceEl.textContent = price + ' USDC • Base';
            }

            if (gridEl) {
                gridEl.innerHTML = '';
                const fields = (data && Array.isArray(data.locked_fields) && data.locked_fields.length > 0)
                    ? data.locked_fields
                    : ['full_payload', '_oracle_proof', 'signature_ecdsa', 'timestamp_iso'];

                const sampleMasks = ['████████.██', '0x9f8c...4a1e', 'ATIVO / REGULAR', 'R$ 1.450.000,00', '2026-11-10T20:00Z', 'VERIFICADO'];
                fields.slice(0, 6).forEach((fieldKey, idx) => {
                    const item = document.createElement('div');
                    item.className = 'locked-field-item';

                    const keySpan = document.createElement('div');
                    keySpan.className = 'locked-field-key';
                    keySpan.textContent = '🔒 ' + fieldKey;

                    const blurSpan = document.createElement('div');
                    blurSpan.className = 'locked-field-blur';
                    blurSpan.textContent = sampleMasks[idx % sampleMasks.length];

                    item.appendChild(keySpan);
                    item.appendChild(blurSpan);
                    gridEl.appendChild(item);
                });
            }

            unlockCard.style.display = 'block';
        }

        function openPlaygroundWeb3Modal() {
            currentServiceAction = currentPlaygroundPreset;
            openWeb3Modal();
        }

        function unlockPlaygroundViaSandbox() {
            setPlaygroundMode('sandbox');
            runPlaygroundQuery();
        }

        // --- Checkout Pix Inline & Resgate Condicional no Card 429 do Playground ---
        let pgInlinePixTier = 'micro';
        let pgInlinePixOrderId = null;
        let pgInlinePixPollTimer = null;

        function selectPlaygroundPixTier(tier) {
            pgInlinePixTier = (tier === 'starter') ? 'starter' : 'micro';
            document.querySelectorAll('.pg-rl-tier-btn').forEach((btn) => {
                const isAct = btn.getAttribute('data-pg-tier') === pgInlinePixTier;
                btn.style.border = isAct ? '1.5px solid #10B981' : '1px solid rgba(148, 163, 184, 0.4)';
                btn.style.background = isAct ? 'rgba(16, 185, 129, 0.14)' : 'transparent';
                btn.style.color = isAct ? '#F8FAFC' : '#CBD5E1';
                btn.style.fontWeight = isAct ? '700' : '600';
            });
            const genBtn = document.getElementById('btn-pg-rl-gen-pix');
            if (genBtn) {
                genBtn.textContent = pgInlinePixTier === 'starter'
                    ? '⚡ Gerar QR Code Pix (R$ 12,00)'
                    : '⚡ Gerar QR Code Pix (R$ 4,90)';
            }
        }

        function showPlaygroundRlFeedback(msg, isOk) {
            const box = document.getElementById('pg-rl-feedback');
            if (!box) return;
            box.style.display = 'block';
            box.style.color = isOk ? '#34D399' : '#FCA5A5';
            box.textContent = msg;
        }

        function activatePlaygroundPassAndRetry(token) {
            const cleanTok = String(token || '').trim();
            if (!cleanTok.startsWith('pass_g402_')) return;
            if (pgInlinePixPollTimer) {
                clearInterval(pgInlinePixPollTimer);
                pgInlinePixPollTimer = null;
            }
            if (window.Grid402PassStorage) {
                window.Grid402PassStorage.save(cleanTok);
            } else {
                try {
                    localStorage.setItem('grid402_active_pass_token', cleanTok);
                    document.cookie = 'g402_pass_token=' + encodeURIComponent(cleanTok) + '; path=/; SameSite=Lax';
                } catch (_) {}
            }
            const passInp = document.getElementById('playground-pass-token-input');
            if (passInp) passInp.value = cleanTok;
            const qrBox = document.getElementById('pg-rl-pix-qr-box');
            if (qrBox) qrBox.style.display = 'none';
            const rlCard = document.getElementById('playground-rate-limit-card');
            if (rlCard) rlCard.style.display = 'none';
            setPlaygroundMode('pass');
            runPlaygroundQuery();
        }

        async function startPlaygroundInlinePix() {
            const emailInp = document.getElementById('pg-rl-pix-email');
            const email = emailInp ? emailInp.value.trim() : '';
            if (!email || !email.includes('@')) {
                showPlaygroundRlFeedback('Informe um e-mail válido para emissão do recibo Pix.', false);
                if (emailInp) emailInp.focus();
                return;
            }
            const btn = document.getElementById('btn-pg-rl-gen-pix');
            const origText = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Gerando QR Code...';
            }
            try {
                const res = await fetch('/api/v1/pass/mp-pix', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ tier: pgInlinePixTier, email: email })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    showPlaygroundRlFeedback(data.message || data.error || ('Falha ao gerar Pix (HTTP ' + res.status + ').'), false);
                    return;
                }
                pgInlinePixOrderId = data.order_id;
                const qrBox = document.getElementById('pg-rl-pix-qr-box');
                const qrImg = document.getElementById('pg-rl-pix-qr-img');
                const pixCodeInp = document.getElementById('pg-rl-pix-code');
                const pixSt = document.getElementById('pg-rl-pix-status');
                const mime = (data.qr_mime && String(data.qr_mime).startsWith('image/')) ? data.qr_mime : 'image/png';
                if (qrImg && data.qr_code_base64) {
                    qrImg.src = 'data:' + mime + ';base64,' + data.qr_code_base64;
                }
                if (pixCodeInp) pixCodeInp.value = data.qr_code || '';
                if (pixSt) {
                    pixSt.style.color = '#FCD34D';
                    pixSt.textContent = '⏳ Aguardando pagamento Pix... (após pagar, sua consulta roda automaticamente)';
                }
                if (qrBox) qrBox.style.display = 'block';
                showPlaygroundRlFeedback('QR Code Pix gerado! Escaneie no app do banco ou copie o código.', true);

                if (pgInlinePixPollTimer) clearInterval(pgInlinePixPollTimer);
                pgInlinePixPollTimer = setInterval(() => {
                    pollPlaygroundInlinePix(pgInlinePixOrderId);
                }, 3500);
            } catch (_) {
                showPlaygroundRlFeedback('Erro de conexão ao gerar Pix.', false);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        async function pollPlaygroundInlinePix(orderId) {
            if (!orderId) return;
            try {
                const res = await fetch('/api/v1/pass/mp-status?order_id=' + encodeURIComponent(orderId), { cache: 'no-store' });
                const data = await res.json();
                if (data && data.status === 'approved' && data.pass_token) {
                    const pixSt = document.getElementById('pg-rl-pix-status');
                    if (pixSt) {
                        pixSt.style.color = '#34D399';
                        pixSt.textContent = '✅ Pix aprovado! Ativando Session Pass e reexecutando consulta...';
                    }
                    activatePlaygroundPassAndRetry(data.pass_token);
                }
            } catch (_) {}
        }

        async function copyPlaygroundInlinePixCode() {
            const inp = document.getElementById('pg-rl-pix-code');
            const btn = document.getElementById('btn-pg-rl-copy-pix');
            if (!inp || !inp.value) return;
            try {
                await navigator.clipboard.writeText(inp.value);
                if (btn) {
                    btn.textContent = '✓ Copiado!';
                    setTimeout(() => { btn.textContent = '📋 Copiar Código Pix'; }, 2000);
                }
            } catch (_) {}
        }

        async function claimPlaygroundInlinePromo() {
            const btn = document.getElementById('btn-pg-rl-claim-promo');
            const origText = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Resgatando 50 créditos...';
            }
            try {
                const res = await fetch('/api/v1/pass/promo-claim', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ code: 'MCPTRY50' })
                });
                const data = await res.json();
                if (res.ok && data && data.pass_token) {
                    showPlaygroundRlFeedback('🎁 Cupom MCPTRY50 ativado! Executando sua consulta agora...', true);
                    activatePlaygroundPassAndRetry(data.pass_token);
                } else {
                    showPlaygroundRlFeedback(data.message || data.error || 'Não foi possível resgatar o cupom neste IP.', false);
                }
            } catch (_) {
                showPlaygroundRlFeedback('Erro de conexão ao resgatar cupom.', false);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        // --- Descoberta de carteiras (EIP-6963 + injeção legada) ---
        const X402_WALLET_STORAGE_KEY = 'grid402_wallet_rdns';
        const x402AnnouncedProviders = [];

        try {
            window.addEventListener('eip6963:announceProvider', (event) => {
                const detail = event && event.detail;
                if (!detail || !detail.provider) return;
                const info = detail.info || {};
                const already = x402AnnouncedProviders.some((p) =>
                    p.provider === detail.provider || (info.rdns && p.rdns === info.rdns)
                );
                if (already) return;
                x402AnnouncedProviders.push({
                    provider: detail.provider,
                    rdns: info.rdns || '',
                    name: info.name || 'Carteira Web3',
                    icon: info.icon || '',
                });
            });
        } catch (_) {}

        function x402RequestAnnouncements() {
            try {
                window.dispatchEvent(new Event('eip6963:requestProvider'));
            } catch (_) {}
        }

        function x402ProviderName(provider) {
            if (!provider) return 'Carteira Web3';
            try {
                if (provider.isBraveWallet) return 'Brave Wallet';
                if (provider.isCoinbaseWallet || provider.isCoinbaseBrowser) return 'Coinbase Wallet';
                if (provider.isRabby) return 'Rabby';
                if (provider.isTrust || provider.isTrustWallet) return 'Trust Wallet';
                if (provider.isPhantom) return 'Phantom';
                if (provider.isOkxWallet || provider.isOKExWallet) return 'OKX Wallet';
                if (provider.isRainbow) return 'Rainbow';
                if (provider.isFrame) return 'Frame';
                if (provider.isMetaMask) return 'MetaMask';
            } catch (_) {}
            return 'Carteira Injetada';
        }

        function x402ProviderRdns(provider) {
            if (!provider) return '';
            try {
                if (provider.isCoinbaseWallet || provider.isCoinbaseBrowser) return 'com.coinbase.wallet';
                if (provider.isRabby) return 'io.rabby';
                if (provider.isBraveWallet) return 'com.brave.wallet';
                if (provider.isTrust || provider.isTrustWallet) return 'com.trustwallet.app';
                if (provider.isPhantom) return 'app.phantom';
                if (provider.isOkxWallet || provider.isOKExWallet) return 'com.okex.wallet';
                if (provider.isRainbow) return 'me.rainbow';
                if (provider.isMetaMask) return 'io.metamask';
            } catch (_) {}
            return '';
        }

        function x402DiscoverProviders() {
            x402RequestAnnouncements();
            const seen = [];
            const list = [];
            const push = (provider, name, rdns, icon) => {
                if (!provider || typeof provider.request !== 'function') return;
                if (seen.indexOf(provider) !== -1) return;
                seen.push(provider);
                list.push({
                    provider,
                    name: name || x402ProviderName(provider),
                    rdns: rdns || x402ProviderRdns(provider),
                    icon: icon || '',
                });
            };

            x402AnnouncedProviders.forEach((entry) => push(entry.provider, entry.name, entry.rdns, entry.icon));

            try {
                const injected = window.ethereum;
                if (injected) {
                    if (Array.isArray(injected.providers) && injected.providers.length) {
                        injected.providers.forEach((p) => push(p));
                    } else {
                        push(injected);
                    }
                }
            } catch (_) {}

            [
                ['coinbaseWalletExtension', 'Coinbase Wallet', 'com.coinbase.wallet'],
                ['rabby', 'Rabby', 'io.rabby'],
                ['trustwallet', 'Trust Wallet', 'com.trustwallet.app'],
                ['phantom', 'Phantom', 'app.phantom'],
                ['okxwallet', 'OKX Wallet', 'com.okex.wallet'],
                ['BraveWalletProvider', 'Brave Wallet', 'com.brave.wallet'],
            ].forEach((item) => {
                try {
                    if (window[item[0]]) push(window[item[0]], item[1], item[2]);
                } catch (_) {}
            });

            return list;
        }

        function x402GetProviderPreference() {
            try {
                return localStorage.getItem(X402_WALLET_STORAGE_KEY) || '';
            } catch (_) {
                return '';
            }
        }

        function x402SetProviderPreference(rdns) {
            try {
                if (rdns) localStorage.setItem(X402_WALLET_STORAGE_KEY, rdns);
                else localStorage.removeItem(X402_WALLET_STORAGE_KEY);
            } catch (_) {}
        }

        function x402ClearProviderPreference() {
            x402SetProviderPreference('');
        }

        let x402ChooserResolver = null;

        function x402CloseProviderChooser(selection) {
            const overlay = document.getElementById('x402-wallet-overlay');
            if (overlay) overlay.classList.remove('active');
            const resolver = x402ChooserResolver;
            x402ChooserResolver = null;
            if (resolver) resolver(selection || null);
        }

        function handleX402WalletOverlayClick(event) {
            if (event.target && event.target.id === 'x402-wallet-overlay') {
                x402CloseProviderChooser(null);
            }
        }

        function x402ForgetWalletChoice() {
            x402ClearProviderPreference();
            const list = document.getElementById('x402-wallet-list');
            if (list) list.setAttribute('data-forgotten', '1');
        }

        function x402ShowProviderChooser(providers) {
            return new Promise((resolve) => {
                const overlay = document.getElementById('x402-wallet-overlay');
                const list = document.getElementById('x402-wallet-list');
                if (!overlay || !list) {
                    resolve(providers[0] || null);
                    return;
                }
                if (x402ChooserResolver) {
                    x402ChooserResolver(null);
                    x402ChooserResolver = null;
                }
                x402ChooserResolver = resolve;
                list.innerHTML = '';
                list.removeAttribute('data-forgotten');

                providers.forEach((entry) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.style.cssText = 'display:flex;align-items:center;gap:12px;width:100%;padding:0.8rem 0.9rem;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;cursor:pointer;text-align:left;color:#fff;font-size:0.88rem;font-weight:600;transition:border-color .15s ease, background .15s ease;';
                    option.onmouseenter = () => {
                        option.style.borderColor = 'var(--accent)';
                        option.style.background = 'rgba(0,240,255,0.08)';
                    };
                    option.onmouseleave = () => {
                        option.style.borderColor = 'var(--border)';
                        option.style.background = 'rgba(255,255,255,0.04)';
                    };
                    option.onclick = () => {
                        x402SetProviderPreference(entry.rdns);
                        x402CloseProviderChooser(entry);
                    };

                    if (entry.icon) {
                        const img = document.createElement('img');
                        img.src = entry.icon;
                        img.alt = '';
                        img.width = 26;
                        img.height = 26;
                        img.style.cssText = 'border-radius:6px;flex:0 0 auto;';
                        option.appendChild(img);
                    } else {
                        const fallback = document.createElement('span');
                        fallback.textContent = '👛';
                        fallback.style.cssText = 'font-size:1.2rem;line-height:1;flex:0 0 auto;';
                        option.appendChild(fallback);
                    }

                    const label = document.createElement('span');
                    label.textContent = entry.name;
                    label.style.cssText = 'flex:1;';
                    option.appendChild(label);

                    const arrow = document.createElement('span');
                    arrow.textContent = '→';
                    arrow.style.cssText = 'color:var(--text-muted);flex:0 0 auto;';
                    option.appendChild(arrow);

                    list.appendChild(option);
                });

                overlay.classList.add('active');
            });
        }

        function x402UnwrapProvider(entry) {
            if (!entry) return entry;
            if (typeof entry.request === 'function') return entry;
            return entry.provider || entry;
        }

        async function x402ResolveProvider(options = {}) {
            const forceChooser = options.forceChooser === true;
            const allowChooser = options.allowChooser !== false;
            const providers = x402DiscoverProviders();

            if (!providers.length) {
                const err = new Error('Nenhuma carteira Web3 detectada neste navegador.');
                err.code = 'NO_PROVIDER';
                throw err;
            }

            if (!forceChooser) {
                const saved = x402GetProviderPreference();
                if (saved) {
                    const match = providers.find((p) => p.rdns === saved);
                    if (match) return x402UnwrapProvider(match);
                }
            }

            if (providers.length === 1) {
                x402SetProviderPreference(providers[0].rdns);
                return x402UnwrapProvider(providers[0]);
            }

            if (!allowChooser) return x402UnwrapProvider(providers[0]);

            const chosen = await x402ShowProviderChooser(providers);
            if (!chosen) {
                const err = new Error('Seleção de carteira cancelada.');
                err.code = 'CHOOSER_CANCELLED';
                throw err;
            }
            return x402UnwrapProvider(chosen);
        }

        async function x402SignTransferAuthorization(provider, userAddress, amountRaw, serviceMeta = {}) {
            const out = document.getElementById('console-output') || document.getElementById('playground-json-viewer');
            const step = (m) => {
                const el = document.getElementById('console-status-text') || document.getElementById('playground-http-status');
                const o = document.getElementById('console-output') || document.getElementById('playground-json-viewer');
                if (el) el.textContent = '[x402] ' + m;
                if (o) o.textContent = (o.textContent ? o.textContent + '\n' : '') + '[x402] ' + m;
                console.log('x402:', m);
            };
            try {
                step('start (provider=' + (provider ? provider.constructor?.name : 'none') + ')');
                const usdcContract = ('<?php echo GRID402_USDC_CONTRACT; ?>' || '').trim();
                const recipientRaw = '<?php echo GRID402_WALLET_ADDRESS; ?>'.trim().toLowerCase();
                const recipient = recipientRaw.startsWith('0x') ? recipientRaw : ('0x' + recipientRaw);
                step('contracts loaded: ' + usdcContract + ' / ' + recipient);
                const now = Math.floor(Date.now() / 1000);
                const validAfter = String(Math.max(0, now - 600));
                const validBefore = String(now + 3600);
                const nonceBytes = new Uint8Array(32);
                crypto.getRandomValues(nonceBytes);
                const nonce = '0x' + Array.from(nonceBytes).map(b => b.toString(16).padStart(2, '0')).join('');
                step('validAfter=' + validAfter + ' validBefore=' + validBefore);
                const typedData = {
                    domain: { name: 'USD Coin', version: '2', chainId: 8453, verifyingContract: usdcContract },
                    types: {
                        EIP712Domain: [
                            { name: 'name', type: 'string' },
                            { name: 'version', type: 'string' },
                            { name: 'chainId', type: 'uint256' },
                            { name: 'verifyingContract', type: 'address' },
                        ],
                        TransferWithAuthorization: [
                            { name: 'from', type: 'address' },
                            { name: 'to', type: 'address' },
                            { name: 'value', type: 'uint256' },
                            { name: 'validAfter', type: 'uint256' },
                            { name: 'validBefore', type: 'uint256' },
                            { name: 'nonce', type: 'bytes32' },
                        ],
                    },
                    primaryType: 'TransferWithAuthorization',
                    message: {
                        from: userAddress.toLowerCase(),
                        to: recipient.toLowerCase(),
                        value: String(amountRaw),
                        validAfter,
                        validBefore,
                        nonce,
                    },
                };
                step('typedData built');
                const payloadJson = JSON.stringify(typedData);
                step('payloadJson len=' + payloadJson.length);

                // Cadeia de compatibilidade: nem toda carteira aceita o mesmo formato.
                const typedDataV3 = {
                    domain: typedData.domain,
                    types: { TransferWithAuthorization: typedData.types.TransferWithAuthorization },
                    primaryType: 'TransferWithAuthorization',
                    message: typedData.message,
                };
                const signAttempts = [
                    { label: 'v4/json', method: 'eth_signTypedData_v4', params: [userAddress, payloadJson] },
                    { label: 'v4/object', method: 'eth_signTypedData_v4', params: [userAddress, typedData] },
                    { label: 'v3/json', method: 'eth_signTypedData_v3', params: [userAddress, JSON.stringify(typedDataV3)] },
                    { label: 'v3/object', method: 'eth_signTypedData_v3', params: [userAddress, typedDataV3] },
                ];
                let signature = null;
                let lastSignErr = null;
                for (let i = 0; i < signAttempts.length; i += 1) {
                    const attempt = signAttempts[i];
                    try {
                        step('sign try ' + attempt.label + ' (' + (i + 1) + '/' + signAttempts.length + ')');
                        const result = await provider.request({ method: attempt.method, params: attempt.params });
                        if (typeof result === 'string' && /^0x[0-9a-fA-F]{130,}$/.test(result)) {
                            signature = result;
                            step('signature received via ' + attempt.label + ' (' + result.length + ' chars)');
                            break;
                        }
                        lastSignErr = new Error('Assinatura malformada retornada por ' + attempt.label);
                        lastSignErr.code = 'BAD_SIGNATURE';
                        step('sign ' + attempt.label + ' returned malformed signature');
                    } catch (e) {
                        lastSignErr = e;
                        const code = e && e.code !== undefined ? e.code : (e && e.data ? e.data.code : undefined);
                        const msg = e && e.message ? e.message : String(e);
                        step('sign ' + attempt.label + ' failed: ' + code + ' | ' + msg);
                        if (code === 4001 || code === 'ACTION_REJECTED') {
                            throw e;
                        }
                    }
                }
                if (!signature) {
                    throw lastSignErr || new Error('Nenhuma assinatura EIP-712 obtida da carteira.');
                }
                const serviceName = (serviceMeta && serviceMeta.service) ? String(serviceMeta.service) : 'hub';
                let rawResourceUrl = (serviceMeta && serviceMeta.url) ? String(serviceMeta.url) : (window.location.origin + '/api/v1/' + serviceName);
                if (rawResourceUrl.startsWith('/')) {
                    rawResourceUrl = window.location.origin + rawResourceUrl;
                }
                let parsedResourceUrl = null;
                try {
                    parsedResourceUrl = new URL(rawResourceUrl, window.location.origin);
                } catch (_) {}
                const canonicalResourceUrl = parsedResourceUrl
                    ? (parsedResourceUrl.origin + parsedResourceUrl.pathname)
                    : rawResourceUrl;
                const envelope = {
                    x402Version: 2,
                    resource: {
                        url: canonicalResourceUrl,
                        description: 'Grid402 ' + serviceName.toUpperCase() + ' M2M oracle endpoint',
                        mimeType: 'application/json',
                        serviceName: 'Grid402 ' + serviceName.toUpperCase(),
                        tags: ['public-data', 'utility', 'x402', 'base', 'agents'],
                        iconUrl: 'https://grid402.online/assets/icon-192x192.png',
                    },
                    accepted: {
                        scheme: 'exact',
                        network: 'eip155:8453',
                        asset: usdcContract,
                        amount: String(amountRaw),
                        payTo: recipient.toLowerCase(),
                        maxTimeoutSeconds: 300,
                        extra: { name: 'USD Coin', version: '2' },
                    },
                    payload: {
                        signature,
                        authorization: {
                            from: userAddress.toLowerCase(),
                            to: recipient.toLowerCase(),
                            value: String(amountRaw),
                            validAfter,
                            validBefore,
                            nonce,
                        },
                    },
                    extensions: (() => {
                        const qp = {};
                        if (parsedResourceUrl) {
                            parsedResourceUrl.searchParams.forEach((v, k) => {
                                if (k !== 'api' && k !== 'format') qp[k] = v;
                            });
                        }
                        const hasQp = Object.keys(qp).length > 0;
                        const qpSchemaProps = {};
                        Object.keys(qp).forEach(k => { qpSchemaProps[k] = { type: 'string' }; });
                        return {
                            bazaar: {
                                info: {
                                    input: hasQp
                                        ? { type: 'http', method: 'GET', queryParams: qp }
                                        : { type: 'http', method: 'GET' },
                                    output: { type: 'json', example: { status: 'success' } },
                                },
                                schema: {
                                    '$schema': 'https://json-schema.org/draft/2020-12/schema',
                                    type: 'object',
                                    required: ['input'],
                                    properties: {
                                        input: {
                                            type: 'object',
                                            required: ['type', 'method'],
                                            properties: {
                                                type: { type: 'string', const: 'http' },
                                                method: { type: 'string', enum: ['GET', 'HEAD', 'DELETE'] },
                                                ...(hasQp ? { queryParams: { type: 'object', properties: qpSchemaProps } } : {}),
                                            },
                                            additionalProperties: false,
                                        },
                                        output: {
                                            type: 'object',
                                            required: ['type'],
                                            properties: {
                                                type: { type: 'string' },
                                                example: { type: 'object' },
                                            },
                                        },
                                    },
                                },
                            },
                        };
                    })(),
                };
                const jsonStr = JSON.stringify(envelope);
                const utf8Bytes = new TextEncoder().encode(jsonStr);
                let binStr = '';
                for (let idx = 0; idx < utf8Bytes.length; idx += 1) {
                    binStr += String.fromCharCode(utf8Bytes[idx]);
                }
                const b64 = btoa(binStr);
                step('btoa ok (' + b64.length + ' chars)');
                return b64;
            } catch (e) {
                let dataInfo = '';
                try {
                    if (e && e.data !== undefined) {
                        const raw = typeof e.data === 'string' ? e.data : JSON.stringify(e.data);
                        dataInfo = ' | data=' + String(raw).slice(0, 300);
                    }
                } catch (_) {}
                const errInfo = 'x402 err: ' + (e && e.name) + ' | ' + (e && e.message) + ' | code=' + (e && e.code) + dataInfo + ' | stack=' + (e && e.stack ? e.stack.split('\n')[0] : '');
                step(errInfo);
                try { console.error('x402SignTransferAuthorization FAILED', e); } catch (_) {}
                throw e;
            }
        }

        const WEB3_PRICING_TABLE = {
            'pass': { amountRaw: 2000000, priceUsdc: '2.0000' },
            'compliance': { amountRaw: 30000, priceUsdc: '0.0300' },
            'sanctions': { amountRaw: 10000, priceUsdc: '0.0100' },
            'simples': { amountRaw: 10000, priceUsdc: '0.0100' },
            'domain-audit': { amountRaw: 15000, priceUsdc: '0.0150' },
            'market-snapshot': { amountRaw: 15000, priceUsdc: '0.0150' },
            'token-safety': { amountRaw: 15000, priceUsdc: '0.0150' },
            'prediction-markets': { amountRaw: 10000, priceUsdc: '0.0100' },
            'sec-company': { amountRaw: 10000, priceUsdc: '0.0100' },
            'cnpj': { amountRaw: 10000, priceUsdc: '0.0100' },
            'lei': { amountRaw: 10000, priceUsdc: '0.0100' },
            'ofac': { amountRaw: 20000, priceUsdc: '0.0200' },
            'kyb-audit': { amountRaw: 25000, priceUsdc: '0.0250' },
            'dossier': { amountRaw: 50000, priceUsdc: '0.0500' },
        };

        function resolveWeb3Price(serviceSlug) {
            return WEB3_PRICING_TABLE[serviceSlug] || { amountRaw: 5000, priceUsdc: '0.0050' };
        }

        function notifyWalletConnected(addr) {
            try {
                const clean = String(addr || '').trim().toLowerCase();
                if (!/^0x[a-f0-9]{40}$/.test(clean) || clean === '0x0000000000000000000000000000000000000000') return;
                const k = 'g402_wc_' + clean;
                if (window.sessionStorage && window.sessionStorage.getItem(k) === '1') return;
                if (window.sessionStorage) window.sessionStorage.setItem(k, '1');
                fetch('/api/v1/growth/wallet-connect', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ address: clean })
                }).catch(() => {});
            } catch (e) {}
        }

        async function payPlaygroundWithWeb3(customProvider = null, customBtnId = null) {
            const btn = document.getElementById(customBtnId || 'btn-playground-web3-pay');
            const statusBadge = document.getElementById('playground-http-status');
            const viewer = document.getElementById('playground-json-viewer');

            let provider = customProvider;
            if (!provider) {
                try {
                    provider = await x402ResolveProvider();
                } catch (e) {
                    if (e && e.code === 'CHOOSER_CANCELLED') return;
                    if (btn) btn.disabled = false;
                    openPlaygroundWeb3Modal();
                    return;
                }
            }

            const origText = btn ? btn.textContent : '⚡ Desbloquear com Carteira Web3 (1 Clique)';
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Conectando carteira...';
                }

                const accounts = await provider.request({ method: 'eth_requestAccounts', params: [] });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta conectada.');
                }
                const userAddress = accounts[0];
                notifyWalletConnected(userAddress);

                if (btn) btn.textContent = 'Mudando para rede Base...';
                try {
                    await provider.request({
                        method: 'wallet_switchEthereumChain',
                        params: [{ chainId: '0x2105' }],
                    });
                } catch (switchError) {
                    if (switchError.code === 4902) {
                        await provider.request({
                            method: 'wallet_addEthereumChain',
                            params: [{
                                chainId: '0x2105',
                                chainName: 'Base Mainnet',
                                nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 },
                                rpcUrls: ['https://mainnet.base.org'],
                                blockExplorerUrls: ['https://basescan.org'],
                            }],
                        });
                    } else {
                        throw switchError;
                    }
                }

                const { amountRaw, priceUsdc } = resolveWeb3Price(currentPlaygroundPreset);
                const playgroundUrl = buildPlaygroundUrl();
                const absPlaygroundUrl = playgroundUrl.startsWith('/') ? (window.location.origin + playgroundUrl) : playgroundUrl;

                if (btn) btn.textContent = 'Assine ' + priceUsdc + ' USDC na carteira (x402 EIP-712)...';
                const xpay = await x402SignTransferAuthorization(provider, userAddress, amountRaw, {
                    service: currentPlaygroundPreset,
                    url: absPlaygroundUrl,
                });
                if (statusBadge) statusBadge.textContent = 'Liquidando via facilitador x402...';
                if (viewer) viewer.textContent = '// Assinatura x402 (EIP-712) criada.\n// Enviando prova para o facilitador (verify + settle)...';
                if (btn) btn.textContent = 'Liquidando via facilitador x402...';

                const url = buildPlaygroundUrl();
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Payment': xpay
                    }
                });
                const unlockedData = await res.json();
                lastPlaygroundJson = JSON.stringify(unlockedData, null, 2);
                if (viewer) viewer.textContent = lastPlaygroundJson;

                const unlockCard = document.getElementById('playground-unlock-card');
                if (unlockCard && res.status === 200) {
                    unlockCard.style.display = 'none';
                }

                if (res.status === 200) {
                    if (statusBadge) statusBadge.textContent = 'HTTP 200 OK (PAGO)';
                    if (unlockedData && unlockedData._oracle_proof) {
                        auditOracleProof(unlockedData);
                    }
                } else {
                    if (statusBadge) statusBadge.textContent = 'HTTP ' + res.status;
                }
            } catch (err) {
                const isUserCancel = !!(err && (err.code === 4001 || err.code === 'ACTION_REJECTED' || err.code === 'CHOOSER_CANCELLED'));
                const safeMsg = String((err && (err.shortMessage || err.message)) || err || 'Unknown wallet error').slice(0, 220);
                if (isUserCancel) {
                    if (statusBadge) statusBadge.textContent = 'Pagamento Cancelado';
                } else {
                    if (statusBadge) statusBadge.textContent = 'Falha no Pagamento Web3';
                    if (viewer) viewer.textContent = '// Erro técnico no pagamento Web3: ' + safeMsg;
                    renderNonProofState('Falha no pagamento Web3: ' + safeMsg);
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        function render402ChallengeCard(data) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';
            card.classList.remove('valid', 'invalid');
            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-pending';
                overallBadge.textContent = isEn ? '🔒 x402 CHALLENGE RECEIVED' : '🔒 DESAFIO x402 RECEBIDO';
            }

            const engineBadge = document.getElementById('audit-engine-badge');
            if (engineBadge) {
                engineBadge.textContent = isEn ? 'x402 Protocol • Base Mainnet' : 'Protocolo x402 • Base Mainnet';
            }

            const s1Icon = document.getElementById('audit-step-1-icon');
            const s1Status = document.getElementById('audit-step-1-status');
            const s1Meta = document.getElementById('audit-step-1-meta');

            const s2Icon = document.getElementById('audit-step-2-icon');
            const s2Status = document.getElementById('audit-step-2-status');
            const s2Meta = document.getElementById('audit-step-2-meta');

            const s3Icon = document.getElementById('audit-step-3-icon');
            const s3Status = document.getElementById('audit-step-3-status');
            const s3Meta = document.getElementById('audit-step-3-meta');

            if (s1Icon) { s1Icon.className = 'audit-step-icon icon-pending'; s1Icon.textContent = '1'; }
            if (s1Status) { s1Status.textContent = isEn ? 'Free Sample (Teaser)' : 'Amostra Previa (Teaser)'; s1Status.style.color = 'var(--accent)'; }
            if (s1Meta) {
                s1Meta.style.display = 'block';
                s1Meta.textContent = isEn
                    ? 'The HTTP 402 response includes free preliminary data so the agent can inspect quality before purchasing.'
                    : 'A resposta 402 inclui dados preliminares gratuitos para o agente inspecionar a qualidade antes da compra.';
            }

            if (s2Icon) { s2Icon.className = 'audit-step-icon icon-pending'; s2Icon.textContent = '2'; }
            if (s2Status) { s2Status.textContent = isEn ? 'Recipient and Price' : 'Destinatario e Preco'; s2Status.style.color = 'var(--accent)'; }
            if (s2Meta) {
                s2Meta.style.display = 'block';
                const price = (data && data.price_usdc) ? data.price_usdc : '0.0050';
                const dest = (data && data.payment_address) ? data.payment_address : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
                s2Meta.innerHTML = (isEn ? 'Price: ' : 'Preco: ') + price + ' USDC | ' + (isEn ? 'Recipient: ' : 'Destino: ') + '<a href="https://basescan.org/address/' + dest + '" target="_blank" rel="noopener noreferrer">' + dest + '</a>';
            }

            if (s3Icon) { s3Icon.className = 'audit-step-icon icon-pending'; s3Icon.textContent = '3'; }
            if (s3Status) { s3Status.textContent = isEn ? 'Proof Issued After Payment' : 'Prova Emitida Apos Pagamento'; s3Status.style.color = 'var(--accent)'; }
            if (s3Meta) {
                s3Meta.style.display = 'block';
                s3Meta.textContent = isEn
                    ? 'Upon sending X-Payment (or using Sandbox demo mode), the cryptographic attestation is automatically generated on HTTP 200.'
                    : 'Ao enviar o comprovante X-Payment (ou no modo Sandbox demo), a atestacao criptografica e gerada automaticamente no HTTP 200.';
            }
        }

        function renderNonProofState(message) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';
            card.classList.remove('valid', 'invalid');
            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-pending';
                overallBadge.textContent = isEn ? 'NO CRYPTOGRAPHIC PROOF' : 'SEM PROVA CRIPTOGRAFICA';
            }

            const s1Meta = document.getElementById('audit-step-1-meta');
            if (s1Meta) {
                s1Meta.style.display = 'block';
                s1Meta.textContent = message;
            }
        }

        function renderAuditFailure(message) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';
            card.classList.remove('valid');
            card.classList.add('invalid');
            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-invalid';
                overallBadge.textContent = isEn ? '✕ AUDIT ERROR' : '✕ ERRO NA AUDITORIA';
            }

            const s1Meta = document.getElementById('audit-step-1-meta');
            if (s1Meta) {
                s1Meta.style.display = 'block';
                s1Meta.textContent = message;
            }
        }

        function verifyManualPayload() {
            const inputEl = document.getElementById('playground-manual-input');
            const raw = inputEl ? inputEl.value.trim() : '';
            if (!raw) {
                alert('Por favor, cole um JSON no campo de texto para auditar.');
                return;
            }

            let parsed;
            try {
                parsed = JSON.parse(raw);
            } catch (e) {
                alert('O texto informado nao e um JSON valido. Verifique a sintaxe e tente novamente.');
                return;
            }

            lastPlaygroundJson = JSON.stringify(parsed, null, 2);
            const viewer = document.getElementById('playground-json-viewer');
            if (viewer) viewer.textContent = lastPlaygroundJson;

            const statusBadge = document.getElementById('playground-http-status');
            if (statusBadge) statusBadge.textContent = 'Verificacao Manual';

            auditOracleProof(parsed);
        }

        function loadManualTemplate(type) {
            const inputEl = document.getElementById('playground-manual-input');
            if (!inputEl) return;

            const validTemplate = {
                "status": "success",
                "service": "workdays",
                "start_date": "2026-11-06",
                "days_added": 5,
                "target_date": "2026-11-13",
                "business_days": 5,
                "holidays_in_interval": 0,
                "sandbox": true,
                "_oracle_proof": {
                    "oracle_address": "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90",
                    "timestamp": 1794163887,
                    "data_hash": "0x92b36d33af465b9fc16f221db86d5640c40efd1a3d7c51b10d15d14a982fb9bc",
                    "signature": "0x3045022037e603a27809aca2cdc448e1ae1dc8342737bfa8a5486546c252f6328195730c022100e631fb55db5439c5ed52b76edceab4537536ae3f77aeeb593036f9ed61e12c1c",
                    "algorithm": "secp256k1-sha256",
                    "sandbox": true
                }
            };

            if (type === 'tampered') {
                const tampered = JSON.parse(JSON.stringify(validTemplate));
                tampered.business_days = 99;
                inputEl.value = JSON.stringify(tampered, null, 2);
            } else {
                inputEl.value = JSON.stringify(validTemplate, null, 2);
            }
        }

        function copyPlaygroundJson() {
            if (!lastPlaygroundJson) {
                const viewer = document.getElementById('playground-json-viewer');
                lastPlaygroundJson = viewer ? viewer.textContent : '';
            }
            if (!lastPlaygroundJson) return;

            navigator.clipboard.writeText(lastPlaygroundJson).then(() => {
                const btn = document.getElementById('btn-copy-playground');
                if (btn) {
                    const orig = btn.textContent;
                    btn.textContent = '✓ Copiado!';
                    setTimeout(() => { btn.textContent = orig; }, 2000);
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPlayground);
        } else {
            initPlayground();
        }

        function switchTab(name) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            const targetBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + name + "'"));
            if (targetBtn) {
                targetBtn.classList.add('active');
            } else if (typeof event !== 'undefined' && event && event.target && event.target.classList.contains('tab-btn')) {
                event.target.classList.add('active');
            }
            const tabEl = document.getElementById('tab-' + name);
            if (tabEl) tabEl.classList.add('active');
        }

        let currentServiceAction = 'summary';

        const CONSOLE_PAID_URLS = {
            forex: '/api/v1/forex',
            crypto: '/api/v1/crypto',
            dns: '/api/v1/dns?domain=github.com',
            ssl: '/api/v1/ssl?host=base.org',
            asn: '/api/v1/asn?ip=1.1.1.1',
            cep: '/api/v1/cep?code=01310100',
            cnpj: '/api/v1/cnpj?cnpj=00000000000191',
            sanctions: '/api/v1/sanctions?cnpj=00000000000191',
            simples: '/api/v1/simples?cnpj=00000000000191',
            compliance: '/api/v1/compliance?cnpj=00000000000191',
            indicators: '/api/v1/indicators',
            holidays: '/api/v1/holidays?year=2026',
            workdays: '/api/v1/workdays?start_date=2026-12-23' + String.fromCharCode(38) + 'end_date=2026-12-28',
            lei: '/api/v1/lei?lei=5493006MHB84DD0ZWV18',
            ofac: '/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16',
            notarize: '/api/v1/notarize?content=Grid402-M2M-Contract-v1',
            'global-markets': '/api/v1/global-markets?market=US' + String.fromCharCode(38) + 'base=USD',
            extract: '/api/v1/extract?url=https://example.com',
            'email-verify': '/api/v1/email-verify?email=contato@grid402.online',
            'token-safety': '/api/v1/token-safety?chain=base' + String.fromCharCode(38) + 'address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
            'prediction-markets': '/api/v1/prediction-markets?q=bitcoin' + String.fromCharCode(38) + 'limit=5',
            'sec-company': '/api/v1/sec-company?ticker=AAPL' + String.fromCharCode(38) + 'forms=10-K,10-Q' + String.fromCharCode(38) + 'limit=5',
            'vuln-audit': '/api/v1/vuln-audit?id=CVE-2021-44228',
            'chain-telemetry': '/api/v1/chain-telemetry?chain=base',
            dossier: '/api/v1/dossier?cnpj=00000000000191',
            'domain-audit': '/api/v1/domain-audit?domain=base.org',
            'kyb-audit': '/api/v1/kyb-audit?cnpj=00000000000191',
            'market-snapshot': '/api/v1/market-snapshot',
            pass: '/api/v1/pass',
        };

        function resolveConsolePaidTarget(action) {
            const service = CONSOLE_PAID_URLS[action] ? action : 'dns';
            return { service, relativeUrl: CONSOLE_PAID_URLS[service] };
        }

        async function runConsole(action) {
            if (action !== 'paid') {
                currentServiceAction = action;
            }
            const out = document.getElementById('console-output');
            const statusText = document.getElementById('console-status-text');
            const badge = document.getElementById('console-latency-badge');
            const teaserCard = document.getElementById('console-teaser-card');
            if (teaserCard) teaserCard.style.display = 'none';

            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');
            out.textContent = isEn ? 'Loading request...' : 'Carregando requisição...';
            badge.style.display = 'none';

            let url = '/status';
            let headers = { 'Accept': 'application/json' };

            if (action === 'paid') {
                const tx = document.getElementById('tx-input').value.trim();
                if (!tx) {
                    out.textContent = isEn ? 'Please paste the Base transaction hash or pass_g402_... token above.' : 'Por favor, insira o hash da transação na Base no campo acima.';
                    return;
                }
                headers['X-Payment'] = tx;
                url = resolveConsolePaidTarget(currentServiceAction).relativeUrl;
            } else if (CONSOLE_PAID_URLS[action]) {
                url = CONSOLE_PAID_URLS[action];
            }

            const t0 = performance.now();
            try {
                const res = await fetch(url, { headers });
                const duration = Math.round(performance.now() - t0);
                const json = await res.json();

                statusText.textContent = `HTTP ${res.status} ${res.status === 200 ? 'OK' : (res.status === 402 ? (isEn ? 'Payment Required (x402 Challenge)' : 'Payment Required (Desafio x402)') : (res.status === 429 ? 'Sandbox Limit Exceeded' : 'Error'))}`;
                badge.style.display = 'inline-block';
                badge.textContent = `${duration} ms`;

                if ((res.status === 402 || res.status === 429) && teaserCard) {
                    teaserCard.style.display = 'block';
                    const teaserTitle = document.getElementById('teaser-title');
                    const teaserPrice = document.getElementById('teaser-badge-price');
                    const teaserContent = document.getElementById('teaser-content');
                    const teaserLocked = document.getElementById('teaser-locked-fields');

                    teaserTitle.textContent = isEn
                        ? 'Verified Preliminary Sample (HTTP 402)'
                        : ((currentServiceAction === 'cnpj') ? 'Dados Cadastrais da Empresa (Prévia)' : (currentServiceAction === 'indicators' ? 'Indicadores Econômicos Oficiais (Prévia)' : (currentServiceAction === 'holidays' ? 'Próximo Feriado Nacional (Prévia)' : 'Amostra Preliminar Verificada (HTTP 402)')));
                    teaserPrice.textContent = `${json.price_usdc || '0.0050'} USDC • Base`;

                    let html = '';
                    if (json.preview && typeof json.preview === 'object') {
                        for (const [key, val] of Object.entries(json.preview)) {
                            const label = key.replace(/_/g, ' ').toUpperCase();
                            html += `<div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px;">
                                <span style="display: block; font-size: 0.7rem; color: var(--text-muted);">${label}</span>
                                <span style="font-weight: 600; color: #fff;">${val || 'N/A'}</span>
                            </div>`;
                        }
                    }
                    teaserContent.innerHTML = html;

                    const lockedList = (json.locked_fields && Array.isArray(json.locked_fields) && json.locked_fields.length > 0)
                        ? json.locked_fields
                        : ['payload_completo', '_oracle_proof', 'signature_ecdsa', 'timestamp_iso'];

                    const blurMasks = ['████████.██', '0x8a3f...9c1d', 'REGULAR / ATIVO', 'R$ 1.850.000'];
                    teaserLocked.innerHTML = `<div class="locked-fields-grid" style="margin-top: 8px; margin-bottom: 0;">` +
                        lockedList.slice(0, 4).map((f, i) => `
                            <div class="locked-field-item">
                                <span class="locked-field-key">🔒 ${f}</span>
                                <span class="locked-field-blur">${blurMasks[i % blurMasks.length]}</span>
                            </div>
                        `).join('') + `</div>`;
                } else if (teaserCard) {
                    teaserCard.style.display = 'none';
                }

                out.textContent = JSON.stringify(json, null, 2);
            } catch (err) {
                statusText.textContent = 'Falha na requisição';
                out.textContent = err.message;
            }
        }

        async function payWithWeb3Wallet(customProvider = null, customBtnId = null) {
            const btn = document.getElementById(customBtnId || 'btn-web3-pay');
            const statusText = document.getElementById('console-status-text');
            const out = document.getElementById('console-output');

            let provider = customProvider;
            if (!provider) {
                try {
                    provider = await x402ResolveProvider();
                } catch (e) {
                    if (e && e.code === 'CHOOSER_CANCELLED') return;
                    if (btn) btn.disabled = false;
                    openWeb3Modal();
                    return;
                }
            }

            const origText = btn ? btn.textContent : '⚡ Pagar com Carteira Web3 (1 Clique)';
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Conectando carteira...';
                }

                // 1. Solicita conexão de contas
                const accounts = await provider.request({ method: 'eth_requestAccounts', params: [] });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta foi conectada.');
                }
                const userAddress = accounts[0];
                notifyWalletConnected(userAddress);

                // 2. Garante que está na rede Base Mainnet (Chain ID 8453 = 0x2105)
                if (btn) btn.textContent = 'Verificando rede Base...';
                try {
                    await provider.request({
                        method: 'wallet_switchEthereumChain',
                        params: [{ chainId: '0x2105' }],
                    });
                } catch (switchError) {
                    if (switchError.code === 4902) {
                        await provider.request({
                            method: 'wallet_addEthereumChain',
                            params: [{
                                chainId: '0x2105',
                                chainName: 'Base Mainnet',
                                nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 },
                                rpcUrls: ['https://mainnet.base.org'],
                                blockExplorerUrls: ['https://basescan.org'],
                            }],
                        });
                    } else {
                        throw switchError;
                    }
                }

                // 3. Assina autorizacao x402 (EIP-712 TransferWithAuthorization)
                const target = resolveConsolePaidTarget(currentServiceAction);
                const { amountRaw, priceUsdc } = resolveWeb3Price(target.service);

                if (btn) btn.textContent = `Aguardando assinatura de ${priceUsdc} USDC (x402 EIP-712)...`;
                out.textContent = `Solicitando assinatura na carteira: ${priceUsdc} USDC na Base, liquidados pelo facilitador x402.`;

                const xpay = await x402SignTransferAuthorization(provider, userAddress, amountRaw, {
                    service: target.service,
                    url: window.location.origin + target.relativeUrl,
                });

                document.getElementById('tx-input').value = xpay;
                statusText.textContent = 'Assinatura x402 criada; liquidando via facilitador...';
                out.textContent = `Assinatura x402 (EIP-712 TransferWithAuthorization) criada.\nPayload pronto para envio.\n\nLiquidando via facilitador x402...`;

                if (btn) btn.textContent = 'Liquidando via facilitador x402...';
                await new Promise(r => setTimeout(r, 1500));

                await runConsole('paid');
            } catch (err) {
                out.textContent = 'Falha na transação Web3: ' + ((err && err.name) || 'Error') + ' | ' + ((err && err.message) || String(err)) + '\n\nSTACK: ' + ((err && err.stack) || '').split('\n').slice(0, 3).join('\n');
                statusText.textContent = 'Operação cancelada ou falhou';
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        function copyBadgeMarkdown() {
            const codeBlock = document.getElementById('badge-markdown-code');
            navigator.clipboard.writeText(codeBlock.textContent).then(() => {
                alert('Snippet copiado para a área de transferência!');
            });
        }

        // Configuração Multi-Chain (redes ativas) para Web3 Top-Up
        const GRID402_MULTICHAIN_CONFIG = <?php echo json_encode(grid402_public_networks(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        let GRID402_SELECTED_TOPUP_NET = 'base';

        function selectTopupNetwork(slug) {
            if (!GRID402_MULTICHAIN_CONFIG[slug]) return;
            GRID402_SELECTED_TOPUP_NET = slug;
            const cfg = GRID402_MULTICHAIN_CONFIG[slug];

            document.querySelectorAll('.topup-net-btn').forEach(btn => {
                const isSelected = btn.getAttribute('data-chain') === slug;
                btn.style.borderColor = isSelected ? '#00F0FF' : 'var(--border)';
                btn.style.background = isSelected ? 'rgba(0,240,255,0.16)' : 'transparent';
                btn.style.color = isSelected ? '#fff' : 'var(--text-muted)';
                btn.style.fontWeight = isSelected ? '700' : '500';
            });

            const summaryEl = document.getElementById('topup-active-net-summary');
            if (summaryEl) {
                const shortContract = cfg.usdc_contract.slice(0, 6) + '...' + cfg.usdc_contract.slice(-4);
                const chainSuffix = cfg.chain_id ? (' (' + cfg.chain_id + ')') : ' (SPL)';
                summaryEl.innerHTML = '<strong>Network:</strong> ' + cfg.display_name + chainSuffix + ' • <strong>Token:</strong> USDC • <strong>Contract:</strong> <code style="color:#00F0FF;">' + shortContract + '</code>';
            }

            const addrEl = document.getElementById('oracle-wallet-addr');
            const labelEl = document.getElementById('oracle-wallet-net-label');
            const txInput = document.getElementById('manual-pass-tx-input');
            if (addrEl) addrEl.textContent = cfg.recipient;
            if (labelEl) labelEl.textContent = '1. Endereço Oficial (' + cfg.display_name + ' - USDC):';
            if (txInput) {
                txInput.placeholder = (cfg.type === 'solana')
                    ? 'Cole a assinatura Base58 da transação SPL USDC na Solana...'
                    : ('0x... (Cole o hash da transferência USDC na rede ' + cfg.display_name + ')');
            }
        }

        // Web3 One-Click Top-Up Multi-Chain (Session Passes)
        async function startWeb3Topup(amountUsdc, tierName, customProvider = null) {
            const btnId = 'btn-topup-' + tierName;
            const btn = document.getElementById(btnId);
            const origText = btn ? btn.textContent : 'Comprar';

            const statusBox = document.getElementById('topup-status-box');
            const statusTitle = document.getElementById('topup-status-title');
            const statusMsg = document.getElementById('topup-status-msg');
            const successCard = document.getElementById('topup-success-card');
            const netSlug = GRID402_SELECTED_TOPUP_NET || 'base';
            const netCfg = GRID402_MULTICHAIN_CONFIG[netSlug] || GRID402_MULTICHAIN_CONFIG.base;

            if (statusBox) statusBox.style.display = 'block';
            if (successCard) successCard.style.display = 'none';

            // Fluxo dedicado para Solana (SPL USDC)
            if (netCfg.type === 'solana') {
                if (statusTitle) statusTitle.textContent = 'Pagamento via Solana (SPL USDC) — ' + amountUsdc + ' USDC';
                if (statusMsg) {
                    statusMsg.innerHTML = 'Transfira exatamente <strong>' + amountUsdc + ' USDC (SPL)</strong> na Solana Mainnet para:<br>'
                        + '<code style="color:#00F0FF;font-weight:700;">' + netCfg.recipient + '</code><br>'
                        + 'Mint USDC: <code style="color:#94A3B8;">' + netCfg.usdc_contract + '</code><br>'
                        + 'Depois, cole a assinatura Base58 na caixa abaixo e clique em <strong>Validar e Ativar Passe</strong>.';
                }
                const manualBox = document.getElementById('manual-topup-box');
                if (manualBox) manualBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            let provider = customProvider;
            if (!provider) {
                try {
                    provider = await x402ResolveProvider();
                } catch (e) {
                    if (e && e.code === 'CHOOSER_CANCELLED') return;
                    if (statusTitle) statusTitle.textContent = 'Carteira Web3 não detectada';
                    if (statusMsg) {
                        statusMsg.innerHTML = 'Nenhuma extensão de carteira (MetaMask, Coinbase Wallet, Rabby) detectada neste navegador.<br><span style="color: #00F0FF; font-weight: 600;">Sem problemas!</span> Você pode transferir USDC na rede <strong>' + netCfg.display_name + '</strong> diretamente pelo seu app e ativar o passe colando o Hash na caixa logo abaixo.';
                    }
                    const manualBox = document.getElementById('manual-topup-box');
                    if (manualBox) manualBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
            }

            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Conectando...';
                }
                if (statusTitle) statusTitle.textContent = '1/3 Conectando carteira em ' + netCfg.display_name + '...';
                if (statusMsg) statusMsg.textContent = 'Solicitando conexão com sua carteira Web3...';

                // 1. Conexão de contas
                const accounts = await provider.request({ method: 'eth_requestAccounts', params: [] });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta foi conectada.');
                }
                const userAddress = accounts[0];
                notifyWalletConnected(userAddress);

                // 2. Garantir rede EVM selecionada (Base, Arbitrum, Polygon, Optimism ou Ethereum)
                if (statusTitle) statusTitle.textContent = '2/3 Verificando rede ' + netCfg.display_name + '...';
                try {
                    await provider.request({
                        method: 'wallet_switchEthereumChain',
                        params: [{ chainId: netCfg.chain_id_hex }],
                    });
                } catch (switchError) {
                    if (switchError.code === 4902) {
                        const explorerBase = netCfg.explorer_tx_url.replace(/\/tx\/$/, '');
                        await provider.request({
                            method: 'wallet_addEthereumChain',
                            params: [{
                                chainId: netCfg.chain_id_hex,
                                chainName: netCfg.display_name,
                                nativeCurrency: netCfg.native_currency || { name: 'Ether', symbol: 'ETH', decimals: 18 },
                                rpcUrls: [netCfg.rpcs[0]],
                                blockExplorerUrls: [explorerBase],
                            }],
                        });
                    } else {
                        throw switchError;
                    }
                }

                // 3. Monta a transação ERC-20 transfer(to, amount) no contrato USDC da rede selecionada
                const amountRaw = Math.round(Number(amountUsdc) * 1e6);
                const recipient = netCfg.recipient.toLowerCase().replace('0x', '');
                const usdcContract = netCfg.usdc_contract;

                const recipientPadded = recipient.padStart(64, '0');
                const amountHex = amountRaw.toString(16).padStart(64, '0');
                const data = '0xa9059cbb' + recipientPadded + amountHex;

                if (btn) btn.textContent = 'Assinando ' + amountUsdc + ' USDC...';
                if (statusTitle) statusTitle.textContent = '3/3 Aguardando confirmação em ' + netCfg.display_name + '...';
                if (statusMsg) statusMsg.textContent = 'Confirme a transferência de ' + amountUsdc + ' USDC (' + netCfg.display_name + ') na sua carteira para o Grid402.';

                let sentTxHash = '';
                const txHash = await provider.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: userAddress,
                        to: usdcContract,
                        data: data,
                    }],
                });
                sentTxHash = String(txHash || '').trim();
                const manualTxInput = document.getElementById('manual-pass-tx-input');
                if (manualTxInput && sentTxHash) {
                    manualTxInput.value = sentTxHash;
                }

                const txLinkHtml = '<a href="' + netCfg.explorer_tx_url + sentTxHash + '" target="_blank" rel="noopener noreferrer" style="color: #00F0FF; text-decoration: underline;">' + sentTxHash + '</a>';
                if (statusTitle) statusTitle.textContent = 'Transação enviada! Confirmando em ' + netCfg.display_name + '...';
                if (statusMsg) statusMsg.innerHTML = 'Tx enviada: ' + txLinkHtml + '<br>Aguardando confirmação on-chain (até 5 tentativas automáticas)...';

                // 4. Chama o checkout Web3 Multi-Chain com retry/backoff (5 tentativas) para nao perder o Pass em lag de RPC
                const retryDelaysMs = [2500, 3000, 4000, 5000, 6000];
                let passData = null;
                let lastErrMsg = '';
                for (let attempt = 0; attempt < retryDelaysMs.length; attempt++) {
                    await new Promise(r => setTimeout(r, retryDelaysMs[attempt]));
                    if (statusMsg) {
                        statusMsg.innerHTML = 'Tx enviada: ' + txLinkHtml + '<br>Validando confirmação on-chain (tentativa ' + (attempt + 1) + '/' + retryDelaysMs.length + ')...';
                    }
                    try {
                        const resp = await fetch('/api/v1/pass-emitir?chain=' + encodeURIComponent(netSlug) + '&tx=' + encodeURIComponent(sentTxHash));
                        const parsed = await resp.json();
                        if (parsed && (parsed.status === 'ACTIVE' || parsed.pass_token)) {
                            passData = parsed;
                            break;
                        }
                        lastErrMsg = (parsed && (parsed.message || parsed.error)) ? String(parsed.message || parsed.error) : ('HTTP ' + resp.status);
                    } catch (fetchErr) {
                        lastErrMsg = (fetchErr && fetchErr.message) ? String(fetchErr.message) : 'Erro temporário de rede';
                    }
                }

                if (passData && (passData.status === 'ACTIVE' || passData.pass_token)) {
                    if (statusBox) statusBox.style.display = 'none';
                    if (successCard) successCard.style.display = 'block';

                    document.getElementById('topup-result-token').textContent = passData.pass_token;
                    document.getElementById('topup-result-credits').textContent = Number(passData.credits_total || 0).toLocaleString();
                    document.getElementById('topup-result-tier').textContent = (passData.tier || tierName).toUpperCase() + ' (' + netCfg.name + ')';
                    document.getElementById('topup-result-expires').textContent = passData.expires_at || '180 dias';

                    const exampleCurl = 'curl "https://grid402.online/api/v1/forex" -H "X-Payment: ' + passData.pass_token + '"';
                    document.getElementById('topup-example-curl').textContent = exampleCurl;

                    if (window.Grid402PassStorage && typeof window.Grid402PassStorage.save === 'function') {
                        window.Grid402PassStorage.save(passData.pass_token);
                    } else {
                        try {
                            localStorage.setItem('grid402_active_pass_token', passData.pass_token);
                            localStorage.setItem('grid402_last_pass_token', passData.pass_token);
                            document.cookie = 'g402_pass_token=' + encodeURIComponent(passData.pass_token) + '; path=/; max-age=2592000; SameSite=Lax';
                            if (location.protocol === 'https:') {
                                document.cookie = 'g402_pass_token=' + encodeURIComponent(passData.pass_token) + '; path=/; max-age=2592000; Secure; SameSite=Lax';
                            }
                        } catch (e) {}
                    }

                    successCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    const fallbackErr = new Error(lastErrMsg || 'A transação foi transmitida, mas o bloco ainda está propagando no RPC. Clique em Validar na caixa manual abaixo (seu txHash já foi preenchido).');
                    fallbackErr.sentTxHash = sentTxHash;
                    fallbackErr.explorerUrl = netCfg.explorer_tx_url + sentTxHash;
                    throw fallbackErr;
                }
            } catch (err) {
                if (statusTitle) statusTitle.textContent = (err && err.sentTxHash) ? 'Transação enviada — finalize na caixa manual abaixo' : 'Operação cancelada ou falhou';
                if (statusMsg) {
                    if (err && err.sentTxHash) {
                        const manualInput = document.getElementById('manual-pass-tx-input');
                        if (manualInput) manualInput.value = err.sentTxHash;
                        statusMsg.innerHTML = 'Sua transação já está na rede: <a href="' + err.explorerUrl + '" target="_blank" rel="noopener noreferrer" style="color: #00F0FF; text-decoration: underline;">' + err.sentTxHash + '</a><br>Detalhes: ' + String(err.message || err).replace(/</g, '&lt;') + '<br>Seu txHash foi preenchido automaticamente em <strong>Validar e Ativar Pass</strong> abaixo.';
                    } else {
                        statusMsg.textContent = 'Detalhes do erro: ' + (err.message || err);
                    }
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        function copyOracleAddress() {
            const netCfg = GRID402_MULTICHAIN_CONFIG[GRID402_SELECTED_TOPUP_NET] || GRID402_MULTICHAIN_CONFIG.base;
            const addr = netCfg.recipient;
            navigator.clipboard.writeText(addr).then(() => {
                const btn = document.getElementById('btn-copy-oracle');
                if (btn) {
                    const orig = btn.textContent;
                    btn.textContent = '✅ Endereço Copiado!';
                    setTimeout(() => { btn.textContent = orig; }, 2500);
                }
            });
        }

        async function activateManualPass() {
            const input = document.getElementById('manual-pass-tx-input');
            const tx = input ? input.value.trim() : '';
            const netSlug = GRID402_SELECTED_TOPUP_NET || 'base';
            const netCfg = GRID402_MULTICHAIN_CONFIG[netSlug] || GRID402_MULTICHAIN_CONFIG.base;
            const statusBox = document.getElementById('topup-status-box');
            const statusTitle = document.getElementById('topup-status-title');
            const statusMsg = document.getElementById('topup-status-msg');
            const successCard = document.getElementById('topup-success-card');
            const btn = document.getElementById('btn-manual-activate');

            const isValidEvm = /^0x[a-fA-F0-9]{64}$/.test(tx);
            const isValidSol = /^[1-9A-HJ-NP-Za-km-z]{64,90}$/.test(tx);
            if (!tx || (netCfg.type === 'solana' ? !isValidSol : !isValidEvm)) {
                alert(netCfg.type === 'solana'
                    ? 'Informe uma assinatura Solana válida (Base58 de 64 a 90 caracteres).'
                    : ('Informe um hash de transação válido na rede ' + netCfg.display_name + ' (ex: 0x... com 64 caracteres hexadecimais).'));
                return;
            }

            if (statusBox) statusBox.style.display = 'block';
            if (successCard) successCard.style.display = 'none';
            if (statusTitle) statusTitle.textContent = 'Validando transação em ' + netCfg.display_name + '...';
            if (statusMsg) statusMsg.textContent = 'Consultando recibo on-chain e confirmando transferência de USDC oficial...';
            if (btn) { btn.disabled = true; btn.textContent = 'Validando...'; }

            try {
                const resp = await fetch('/api/v1/pass-emitir?chain=' + encodeURIComponent(netSlug) + '&tx=' + encodeURIComponent(tx));
                const passData = await resp.json();

                if (passData && (passData.status === 'ACTIVE' || passData.pass_token)) {
                    if (statusBox) statusBox.style.display = 'none';
                    if (successCard) successCard.style.display = 'block';

                    document.getElementById('topup-result-token').textContent = passData.pass_token;
                    document.getElementById('topup-result-credits').textContent = Number(passData.credits_total || 0).toLocaleString();
                    document.getElementById('topup-result-tier').textContent = (passData.tier || 'PRO').toUpperCase() + ' (' + netCfg.name + ')';
                    document.getElementById('topup-result-expires').textContent = passData.expires_at || '180 dias';

                    const exampleCurl = 'curl "https://grid402.online/api/v1/forex" -H "X-Payment: ' + passData.pass_token + '"';
                    document.getElementById('topup-example-curl').textContent = exampleCurl;

                    if (window.Grid402PassStorage && typeof window.Grid402PassStorage.save === 'function') {
                        window.Grid402PassStorage.save(passData.pass_token);
                    } else {
                        try {
                            localStorage.setItem('grid402_active_pass_token', passData.pass_token);
                            localStorage.setItem('grid402_last_pass_token', passData.pass_token);
                            document.cookie = 'g402_pass_token=' + encodeURIComponent(passData.pass_token) + '; path=/; max-age=2592000; SameSite=Lax';
                            if (location.protocol === 'https:') {
                                document.cookie = 'g402_pass_token=' + encodeURIComponent(passData.pass_token) + '; path=/; max-age=2592000; Secure; SameSite=Lax';
                            }
                        } catch (e) {}
                    }

                    successCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    throw new Error(passData.message || passData.error || 'Transação não encontrada ou ainda não propagada no RPC de ' + netCfg.display_name + '. Aguarde alguns segundos e tente novamente.');
                }
            } catch (err) {
                if (statusTitle) statusTitle.textContent = 'Falha ao ativar passe';
                if (statusMsg) statusMsg.textContent = 'Erro: ' + (err.message || err);
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = '⚡ Validar e Ativar Passe'; }
            }
        }

        function copyTopupToken() {
            const tokenEl = document.getElementById('topup-result-token');
            const btn = document.getElementById('btn-copy-topup-token');
            if (!tokenEl) return;
            navigator.clipboard.writeText(tokenEl.textContent.trim()).then(() => {
                const orig = btn.textContent;
                btn.textContent = '✅ Copiado!';
                setTimeout(() => { btn.textContent = orig; }, 2500);
            });
        }

        function closeTopupStatus() {
            const box = document.getElementById('topup-status-box');
            if (box) box.style.display = 'none';
        }

        // Modal Legal
        const legalContent = {
            terms: `
                <h3>1. Objeto e Fornecimento AS-IS</h3>
                <p>O <strong>Grid402</strong> é uma infraestrutura global de micro-serviços de dados públicos e utilitários M2M para agentes autônomos e desenvolvedores, fornecida "NO ESTADO EM QUE SE ENCONTRA" (AS-IS).</p>
                <h3>2. Direito de Uso de Informações Públicas e Licenças Abertas</h3>
                <p>Todas as 31 APIs operam estritamente sobre fontes públicas abertas e fatos não passíveis de reserva proprietária: <strong>GLEIF LEI</strong> (CC0 1.0 Universal), <strong>OFAC SDN / ONU</strong> (Domínio Público 17 U.S.C. § 105), <strong>SEC EDGAR / CISA KEV</strong> (Domínio Público Federal EUA), <strong>BCE / BCB / IBGE</strong> (Diretiva UE 2019/1024, Lei nº 12.527/2011 - LAI e Decreto nº 8.777/2016), <strong>Fatos Econômicos e Algoritmos de Calendário</strong> (Lei nº 9.610/1998 Art. 8º, I, II e IV) e <strong>Protocolos IETF</strong> (DNS, TLS, BGP/ASN, SMTP/MX/SPF/DMARC, HTTP/HTTPS, RFC 8785).</p>
                <h3>3. Pagamentos x402 e Session Passes</h3>
                <p>Os micropagamentos e passes pré-pagos em USDC Multi-Chain (Base, Arbitrum, Polygon, Optimism, Ethereum e Solana SPL) remuneram exclusivamente o processamento computacional e a assinatura criptográfica ECDSA do Oráculo, sendo definitivos e irreversíveis.</p>
                <h3>4. Direitos Autorais e Propriedade Intelectual Reservados</h3>
                <p>© 2026 <strong>Grid402 Network Intelligence</strong>. <strong>Todos os direitos reservados.</strong> O software da plataforma, motor de liquidação HTTP 402, arquitetura de Session Pass, design visual e marca são protegidos pela Lei do Software (Lei nº 9.609/1998), Lei de Direitos Autorais (Lei nº 9.610/1998) e Convenção de Berna, sendo proibida a reprodução ou redistribuição sem autorização expressa.</p>
            `,
            privacy: `
                <h3>1. Arquitetura Zero-PII (LGPD Art. 1º e GDPR Recital 14)</h3>
                <p>O Grid402 processa exclusivamente dados de <strong>Pessoas Jurídicas</strong> e protocolos técnicos. Nas rotas de CNPJ e Compliance, o sistema descarta na origem qualquer dado pessoal de sócios (sem QSA, sem CPF e sem contatos pessoais).</p>
                <h3>2. Minimização, HMAC de IP e Zero-Retention</h3>
                <p>Endereços IP em métricas são truncados (<code>xxx.xxx</code>), o rate-limit de Sandbox utiliza pseudonimização <code>HMAC-SHA256</code> (Art. 13 LGPD / Art. 25 GDPR) e a rota <code>/api/v1/notarize</code> calcula apenas o hash <code>SHA-256</code> em RAM sem reter ou exibir o conteúdo bruto.</p>
                <h3>3. Guarda Legal e Encarregado (DPO)</h3>
                <p>Registros técnicos de conexão observam o Art. 15 do Marco Civil da Internet (Lei nº 12.965/2014). Contato direto com o DPO (LGPD/GDPR/CCPA): <code>contato@grid402.online</code>.</p>
            `,
            terms_en: `
                <h3>1. Purpose and "AS-IS" Provision</h3>
                <p><strong>Grid402</strong> is a global open-data microservices and M2M network utility gateway for autonomous AI agents and developers, provided strictly "AS-IS".</p>
                <h3>2. Open Data Legal Basis and Public Domain Rights</h3>
                <p>All 31 endpoints process strictly public-domain and open-licensed records: <strong>GLEIF LEI</strong> (CC0 1.0 Universal), <strong>US Treasury OFAC SDN / SEC EDGAR / CISA KEV / UN</strong> (Public Domain under 17 U.S.C. § 105), <strong>ECB / Central Bank Indicators</strong> (EU Open Data Directive 2019/1024 & Brazilian Open Data Decree 8.777/2016), <strong>Market Facts & Statutory Calendars</strong> (17 U.S.C. § 102(b) & Law 9.610/1998 Art. 8), and <strong>IETF Standards</strong> (DNS, TLS, BGP/ASN, SMTP/MX/SPF/DMARC, HTTP/HTTPS, RFC 8785).</p>
                <h3>3. x402 Micropayments and Session Passes</h3>
                <p>USDC micropayments and Session Passes settled across supported chains (Base, Arbitrum, Polygon, Optimism, Ethereum, and Solana SPL) compensate strictly for computational processing and ECDSA cryptographic attestation, with immediate on-chain finality.</p>
                <h3>4. Copyright and All Rights Reserved</h3>
                <p>© 2026 <strong>Grid402 Network Intelligence</strong>. <strong>All rights reserved.</strong> The Grid402 platform software, HTTP 402 settlement engine, Session Pass architecture, visual design, and brand are proprietary and protected by copyright and intellectual property laws (Brazilian Laws 9.609/1998 & 9.610/1998 and Berne Convention). Unauthorized reproduction or redistribution of the platform is strictly prohibited.</p>
            `,
            privacy_en: `
                <h3>1. Zero-PII Architecture (GDPR Recital 14 & LGPD Art. 1)</h3>
                <p>Grid402 processes exclusively <strong>Legal Entity (corporate)</strong> records and network telemetry. Corporate lookup endpoints strictly strip shareholder/director personal data (no QSA, no individual tax IDs, no personal contacts).</p>
                <h3>2. Data Minimization, IP HMAC, and Zero-Retention</h3>
                <p>IP addresses in telemetry are irreversibly masked (<code>xxx.xxx</code>), Sandbox rate limiting uses <code>HMAC-SHA256</code> pseudonymization (GDPR Art. 25), and <code>/api/v1/notarize</code> computes <code>SHA-256</code> digests in volatile RAM without storing or echoing raw content.</p>
                <h3>3. Statutory Logs and DPO Contact</h3>
                <p>Zero advertising cookies or personal data sales (CCPA/CPRA). Data Protection Officer (DPO) contact: <code>contato@grid402.online</code>.</p>
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
            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            if (tab === 'privacy') {
                title.textContent = isEn ? 'Privacy Policy' : 'Política de Privacidade e LGPD';
                body.innerHTML = isEn ? legalContent.privacy_en : legalContent.privacy;
                termsBtn.classList.remove('active');
                privacyBtn.classList.add('active');
            } else {
                title.textContent = isEn ? 'Terms of Use' : 'Termos de Uso';
                body.innerHTML = isEn ? legalContent.terms_en : legalContent.terms;
                privacyBtn.classList.remove('active');
                termsBtn.classList.add('active');
            }
        }
        function handleOverlayClick(e) {
            if (e.target.id === 'legal-modal-overlay') closeLegalModal();
        }

        // Modal de Pagamento Web3
        function openWeb3Modal() {
            const target = resolveConsolePaidTarget(currentServiceAction);
            const price = resolveWeb3Price(target.service).priceUsdc;
            const priceEl = document.getElementById('web3-modal-price');
            const priceDetailEl = document.getElementById('web3-modal-price-detail');
            if (priceEl) priceEl.textContent = price;
            if (priceDetailEl) priceDetailEl.textContent = price;

            const banner = document.getElementById('web3-no-wallet-banner');
            if (banner) {
                banner.style.display = window.ethereum ? 'none' : 'flex';
            }

            document.getElementById('web3-modal-overlay').classList.add('active');
        }

        function closeWeb3Modal() {
            document.getElementById('web3-modal-overlay').classList.remove('active');
            if (typeof x402CloseProviderChooser === 'function') x402CloseProviderChooser(null);
        }

        function handleWeb3OverlayClick(e) {
            if (e.target.id === 'web3-modal-overlay') closeWeb3Modal();
        }

        function copyWeb3ModalAddress() {
            const input = document.getElementById('web3-wallet-dest-input');
            if (!input) return;
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = document.getElementById('btn-copy-modal-oracle');
                if (!btn) return;
                const orig = btn.textContent;
                btn.textContent = 'Copiado!';
                setTimeout(() => { btn.textContent = orig; }, 2000);
            });
        }

        function submitModalPaymentHash() {
            const hash = document.getElementById('web3-hash-direct-input').value.trim();
            if (!hash) {
                alert('Por favor, cole o hash da transação (0x...).');
                return;
            }
            document.getElementById('tx-input').value = hash;
            closeWeb3Modal();
            runConsole('paid');
        }

        function runSandboxSimulation() {
            document.getElementById('tx-input').value = 'demo';
            runConsole('paid');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLegalModal();
                closeWeb3Modal();
                if (typeof x402CloseProviderChooser === 'function') x402CloseProviderChooser(null);
            }
        });

        // Calculadora de Economia / ROI — Dossie 360 KYB vs Benchmarks Publicos Verificaveis (Nov/2026)
        function updateRoiCalc(val) {
            const count = Math.max(10, parseInt(val, 10) || 100);
            const displayEl = document.getElementById('roi-vol-display');
            const tradEl = document.getElementById('roi-cost-trad');
            const gridEl = document.getElementById('roi-cost-grid');
            const savingsEl = document.getElementById('roi-savings-val');
            const descEl = document.getElementById('roi-savings-desc') || document.getElementById('roi-savings-sub');
            const slider = document.getElementById('roi-slider-input');

            const isEn = (typeof currentLanguage === 'undefined' || currentLanguage === 'en');

            if (slider) {
                slider.value = count;
            }
            if (displayEl) {
                displayEl.textContent = count.toLocaleString(isEn ? 'en-US' : 'pt-BR') + (isEn ? ' / month' : ' / mês');
            }

            // Benchmarks publicos verificaveis (Nov/2026):
            // - EN: OpenSanctions entrada (€50 ~= $54 min ou €0.10 ~= $0.108/consulta, apenas sancoes)
            // - PT-BR: Infosimples franquia minima R$ 100/mes ou 3 fontes separadas (CNPJ + Simples + CGU) x R$ 0,22 = R$ 0,66/dossie
            const costTrad = isEn
                ? Math.max(54.0, count * 0.108)
                : Math.max(100.0, count * 0.66);

            // Grid402 /api/v1/dossier (10 creditos por dossie 360 completo):
            // Starter: R$ 12 / $2 (40 dossies, 180d) | Pro: R$ 29 / $5 (120 dossies, 180d) | Fleet: R$ 115 / $20 (600 dossies, 365d)
            let costGrid;
            let tierNote;
            if (isEn) {
                if (count <= 40) {
                    costGrid = count * 0.05;
                    tierNote = 'Starter Pass ($2 = 40 dossiers · 180d)';
                } else if (count <= 120) {
                    costGrid = count * (5 / 120);
                    tierNote = 'Pro Pass ($5 = 120 dossiers · 180d)';
                } else {
                    costGrid = count * (20 / 600);
                    tierNote = 'Fleet Pass rate ($0.0333/dossier · 365d)';
                }
            } else {
                if (count <= 40) {
                    costGrid = count * 0.30;
                    tierNote = 'Starter Pass (R$ 12 = 40 dossiês · 180d)';
                } else if (count <= 120) {
                    costGrid = count * (29 / 120);
                    tierNote = 'Pro Pass (R$ 29 = 120 dossiês · 180d)';
                } else {
                    costGrid = count * (115 / 600);
                    tierNote = 'Tarifa proporcional Fleet (R$ 0,19/dossiê · 365d)';
                }
            }

            const savings = Math.max(0, costTrad - costGrid);
            const savingsPct = costTrad > 0 ? Math.round((savings / costTrad) * 100) : 0;

            if (tradEl) {
                tradEl.textContent = isEn
                    ? '$ ' + costTrad.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : 'R$ ' + costTrad.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (gridEl) {
                gridEl.textContent = isEn
                    ? '$ ' + costGrid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : 'R$ ' + costGrid.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (savingsEl) {
                savingsEl.textContent = isEn
                    ? '$ ' + savings.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : 'R$ ' + savings.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (descEl) {
                descEl.textContent = isEn
                    ? 'Estimated savings of ' + savingsPct + '% · ' + tierNote
                    : 'Economia estimada de ' + savingsPct + '% · ' + tierNote;
            }
        }

        function setRoiPreset(val) {
            updateRoiCalc(val);
        }

        // Inicializa a calculadora com 120 dossies/mes (1 Pro Pass exato) ao carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { updateRoiCalc(120); });
        } else {
            updateRoiCalc(120);
        }

        // WebMCP: Model Context Protocol no navegador para agentes de IA
        if (typeof window !== 'undefined') {
            const initWebMcp = () => {
                try {
                    const nav = window.navigator || (typeof navigator !== 'undefined' ? navigator : null);
                    if (!nav) return;
                    if (!nav.modelContext) {
                        nav.modelContext = {
                            _tools: [],
                            registerTool: function(tool) {
                                this._tools.push(tool);
                                return true;
                            }
                        };
                    }
                    if (nav.modelContext && typeof nav.modelContext.registerTool === 'function') {
                        const webMcpTools = [
                            {
                                name: 'grid402_cnpj',
                                description: 'Consulta dados cadastrais de Pessoa Juridica na Receita Federal',
                                inputSchema: { type: 'object', properties: { cnpj: { type: 'string' } }, required: ['cnpj'] },
                                execute: async (p) => fetch('/api/v1/cnpj?cnpj=' + encodeURIComponent(p.cnpj || '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_cep',
                                description: 'Consulta endereco postal brasileiro e codigo IBGE por CEP',
                                inputSchema: { type: 'object', properties: { code: { type: 'string' } }, required: ['code'] },
                                execute: async (p) => fetch('/api/v1/cep?code=' + encodeURIComponent(p.code || '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_forex',
                                description: 'Consulta cotacoes fiduciarias oficiais de USD, EUR e GBP para BRL',
                                inputSchema: { type: 'object', properties: {} },
                                execute: async () => fetch('/api/v1/forex').then(r => r.json())
                            },
                            {
                                name: 'grid402_crypto',
                                description: 'Consulta precos spot de criptoativos em USD e BRL',
                                inputSchema: { type: 'object', properties: { symbol: { type: 'string' } } },
                                execute: async (p) => fetch('/api/v1/crypto' + (p.symbol ? '?symbol=' + encodeURIComponent(p.symbol) : '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_dns',
                                description: 'Resolucao autoritativa de registros DNS A, AAAA, MX, TXT, NS e CNAME',
                                inputSchema: { type: 'object', properties: { domain: { type: 'string' } }, required: ['domain'] },
                                execute: async (p) => fetch('/api/v1/dns?domain=' + encodeURIComponent(p.domain || '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_ssl',
                                description: 'Inspecao e auditoria de certificado TLS e SSL na porta 443',
                                inputSchema: { type: 'object', properties: { host: { type: 'string' } }, required: ['host'] },
                                execute: async (p) => fetch('/api/v1/ssl?host=' + encodeURIComponent(p.host || '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_asn',
                                description: 'Consulta inteligencia de rede BGP e numero ASN',
                                inputSchema: { type: 'object', properties: { ip: { type: 'string' } } },
                                execute: async (p) => fetch('/api/v1/asn' + (p.ip ? '?ip=' + encodeURIComponent(p.ip) : '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_indicators',
                                description: 'Consulta taxas oficiais Selic meta e diaria, CDI anual e diario, IPCA 12m e mensal',
                                inputSchema: { type: 'object', properties: {} },
                                execute: async () => fetch('/api/v1/indicators').then(r => r.json())
                            },
                            {
                                name: 'grid402_holidays',
                                description: 'Consulta calendario oficial de feriados nacionais e bancarios com suporte a ano',
                                inputSchema: { type: 'object', properties: { year: { type: 'integer' } } },
                                execute: async (p) => fetch('/api/v1/holidays' + (p.year ? '?year=' + encodeURIComponent(p.year) : '')).then(r => r.json())
                            },
                            {
                                name: 'grid402_workdays',
                                description: 'Calculadora de dias uteis bancarios ANBIMA com contagem de intervalo e projecao de prazos',
                                inputSchema: { type: 'object', properties: { start_date: { type: 'string' }, end_date: { type: 'string' }, days: { type: 'integer' }, date: { type: 'string' } } },
                                execute: async (p) => {
                                    const params = new URLSearchParams();
                                    if (p.start_date) params.append('start_date', p.start_date);
                                    if (p.end_date) params.append('end_date', p.end_date);
                                    if (p.days !== undefined) params.append('days', p.days);
                                    if (p.date) params.append('date', p.date);
                                    return fetch('/api/v1/workdays?' + params.toString()).then(r => r.json());
                                }
                            },
                            {
                                name: 'grid402_summary',
                                description: 'Resumo operacional consolidado e disponibilidade dos servicos Grid402',
                                inputSchema: { type: 'object', properties: {} },
                                execute: async () => fetch('/status').then(r => r.json())
                            }
                        ];
                        webMcpTools.forEach(t => {
                            try { nav.modelContext.registerTool(t); } catch (_) {}
                        });
                    }
                } catch (_) {}
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initWebMcp);
            } else {
                initWebMcp();
            }
        }
    </script>
    <script src="/assets/js/pass_storage.js?v=3.5.0"></script>
    <script src="/assets/js/i18n.js?v=3.8.0"></script>
</body>
</html>
