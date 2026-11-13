<?php
/**
 * Grid402 // Hub de Utilitários de Rede e Dados Públicos Abertos (Protocolo x402)
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
require_once __DIR__ . '/services/domain_audit.php';
require_once __DIR__ . '/services/kyb_audit.php';
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
require_once __DIR__ . '/services/pass.php';
require_once __DIR__ . '/services/pass_checkout.php';
require_once __DIR__ . '/services/oracle_signer.php';
require_once __DIR__ . '/metrics.php';

/**
 * Registra visita à página pública HTML (contador de acessos exibido no /admin).
 * Só conta GET/HEAD — APIs JSON continuam aferidas pela telemetria técnica.
 */
function grid402_log_public_visit(string $path): void {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return;
    }
    grid402_record_page_visit($path, grid402_get_trusted_client_ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
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
if ($apiParam === '' && preg_match('#^/api/v1/([a-z0-9_-]+)#i', $requestPath, $matches)) {
    $apiParam = strtolower($matches[1]);
}
$pageParam = (string) ($_GET['page'] ?? '');
$isJsonExplicit = (isset($_GET['format']) && $_GET['format'] === 'json') || str_starts_with($requestPath, '/api/') || $requestPath === '/status';

// 0. Resposta em Markdown quando solicitado por agentes de IA (Accept: text/markdown)
if (str_contains($acceptHeader, 'text/markdown') || str_contains($acceptHeader, 'text/x-markdown') || (isset($_GET['format']) && $_GET['format'] === 'markdown')) {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Vary: Accept, User-Agent');
    grid402_log_public_visit('/llms.txt');
    readfile(__DIR__ . '/llms.txt');
    exit;
}

// Extração do comprovante de pagamento X-Payment
$paymentProof = (string) ($_SERVER['HTTP_X_PAYMENT'] ?? '');
if ($paymentProof === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $paymentProof = (string) ($reqHeaders['X-Payment'] ?? $reqHeaders['x-payment'] ?? '');
}
if ($paymentProof === '') {
    // Clientes x402 v2 padrao enviam a prova no cabecalho PAYMENT-SIGNATURE
    $paymentProof = trim((string) ($_SERVER['HTTP_PAYMENT_SIGNATURE'] ?? ''));
}
if ($paymentProof === '') {
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (str_starts_with($authHeader, 'x402 ')) {
        $paymentProof = substr($authHeader, 5);
    }
}
if ($paymentProof === '') {
    if (!empty($_GET['tx'])) {
        $paymentProof = trim((string) $_GET['tx']);
    } elseif (!empty($_GET['txHash'])) {
        $paymentProof = trim((string) $_GET['txHash']);
    } elseif (!empty($_GET['tx_hash'])) {
        $paymentProof = trim((string) $_GET['tx_hash']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawPost = (string) @file_get_contents('php://input');
        if ($rawPost !== '') {
            $parsedPost = @json_decode($rawPost, true);
            if (is_array($parsedPost)) {
                $paymentProof = trim((string) ($parsedPost['tx'] ?? $parsedPost['txHash'] ?? $parsedPost['tx_hash'] ?? ''));
            }
        }
    }
}

// Cabeçalhos Globais de IA, Protocolo, Segurança e Conformidade de Dados
header('Vary: Accept, User-Agent');
header('Link: </openapi.json>; rel="service-desc"; type="application/vnd.oai.openapi+json", </llms.txt>; rel="help"; type="text/markdown", </.well-known/mcp/server-card.json>; rel="service-meta"; type="application/json", </sitemap.xml>; rel="sitemap"; type="application/xml", </terms>; rel="terms-of-service"; type="text/html", </privacy>; rel="privacy-policy"; type="text/html"');
header('X-Agent-Protocol: x402; network=base; chain_id=8453');
header('X-Micropayment-Pricing: 0.0005-0.0030 USDC; token=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913; pricing=/catalog');
header('X-Data-Protection-Policy: Zero-PII; Corporate-Entities-Only; LGPD-Art1-Compliant; GDPR-Recital14-Compliant; CCPA-Compliant');
header('X-Open-Data-Licenses: CC0-1.0, US-Public-Domain-17USC105, BR-LAI-12527, EU-Open-Data-2019-1024, IETF-Public-Standards');
header('X-Copyright: Copyright (c) ' . date('Y') . ' Grid402 Network Intelligence. All rights reserved. Proprietary Software & Architecture (Law 9.609/1998 & Law 9.610/1998).');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Content-Signal: search=yes, ai-train=no');

/**
 * Renderizador de Paginas Institucionais HTML para Agentes e Navegadores
 */
function grid402_render_page_html(string $title, string $metaDesc, string $canonicalPath, string $h1, string $bodyContent): void {
    grid402_log_public_visit($canonicalPath);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
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
          "priceRange": "$0.0005 - $0.0030",
          "sameAs": [
            "https://github.com/oliveiratc/Grid402",
            "https://www.npmjs.com/package/grid402",
            "https://www.npmjs.com/package/grid402-mcp",
            "https://pypi.org/project/grid402/",
            "https://basescan.org/address/0xa31c31069c43ac1c0075a93c325b0dcd7744356c"
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
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
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
    <div class="page-wrap">
        <header>
            <div>
                <a href="/" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none;">
                    <img src="/assets/logo-icon-transparent.png" alt="Grid402" width="34" height="34" style="border-radius: 6px;">
                    <span style="font-family: var(--font-heading); font-weight: 700; font-size: 1.35rem; color: var(--text-main); letter-spacing: -0.5px;">Grid<span style="color: var(--accent);">402</span></span>
                </a>
            </div>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/agents" <?php echo $canonicalPath === '/agents' ? 'class="active"' : ''; ?> data-i18n="nav.agents">Agentes IA</a>
                <a href="/catalog" <?php echo $canonicalPath === '/catalog' ? 'class="active"' : ''; ?>>Catálogo</a>
                <a href="/trust" <?php echo $canonicalPath === '/trust' ? 'class="active"' : ''; ?>>Trust</a>
                <a href="/about" <?php echo $canonicalPath === '/about' ? 'class="active"' : ''; ?> data-i18n="nav.about">Sobre</a>
                <a href="/contact" <?php echo $canonicalPath === '/contact' ? 'class="active"' : ''; ?> data-i18n="nav.contact">Contato</a>
                <a href="/terms" <?php echo $canonicalPath === '/terms' ? 'class="active"' : ''; ?> data-i18n="nav.terms">Termos</a>
                <a href="/privacy" <?php echo $canonicalPath === '/privacy' ? 'class="active"' : ''; ?> data-i18n="nav.privacy">Privacidade</a>
                <div class="lang-switch" role="group" aria-label="Language selector">
                    <button type="button" id="lang-btn-pt" class="lang-btn active" onclick="setLanguage('pt')">PT</button>
                    <button type="button" id="lang-btn-en" class="lang-btn" onclick="setLanguage('en')">EN</button>
                </div>
            </nav>
        </header>

        <div id="inst-page-content">
            <h1><?php echo htmlspecialchars($h1); ?></h1>
            <?php echo $bodyContent; ?>
        </div>

        <footer>
            <div class="footer-links">
                <a href="/">Home</a>
                <a href="/agents" data-i18n="nav.agents">Agentes IA</a>
                <a href="/catalog">Catálogo</a>
                <a href="/trust">Trust</a>
                <a href="/about" data-i18n="nav.about">Sobre</a>
                <a href="/contact" data-i18n="nav.contact">Contato</a>
                <a href="/terms" data-i18n="nav.terms">Termos</a>
                <a href="/privacy" data-i18n="nav.privacy">Privacidade</a>
                <a href="/llms.txt">llms.txt</a>
                <a href="mailto:contato@grid402.online">contato@grid402.online</a>
            </div>
            <div data-i18n="inst.footer.copy">© <?php echo date('Y'); ?> Grid402 Network Intelligence. Todos os direitos reservados.</div>
        </footer>
    </div>
    <script src="/assets/js/i18n.js?v=3.0.5"></script>
</body>
</html>
    <?php
    exit;
}

// 1. Rotas Institucionais e Jurídicas
if ($pageParam === 'about' || $requestPath === '/about') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $aboutBody = '<div data-i18n-html="inst.about.body">
        <p>O <strong>Grid402</strong> é uma infraestrutura global de micro-serviços públicos e oráculo de utilitários de rede desenhada especificamente para sistemas autônomos, robôs e agentes de IA sob o padrão HTTP 402 com liquidação em USDC na rede Base Mainnet.</p>
        <h2>Propósito e Arquitetura Global</h2>
        <p>O projeto elimina chaves estáticas de API e burocracia de cadastros para agentes de software no mundo inteiro. Toda requisição técnica a dados oficiais (GLEIF LEI mundial, OFAC SDN, BCE 30+ moedas, notarização criptográfica ECDSA, Receita Federal, CEP, Banco Central, DNS, SSL e BGP) pode ser liquidada diretamente on-chain ou via Session Pass pré-pago em menos de 15ms.</p>
        <ul>
            <li><strong>Zero-DB e Stateless:</strong> Sem persistência de bancos de dados relacionais; operação 100% em memória e cache atômico.</li>
            <li><strong>Protocolo x402 + Session Pass:</strong> Implementação nativa do código HTTP 402 Payment Required para Machine-to-Machine (M2M) e passes de alta frequência.</li>
            <li><strong>Domínio Público e CC0:</strong> Uso estrito de fontes públicas abertas e licenças CC0 sem dados pessoais sensíveis (Zero PII).</li>
        </ul>
        <h2>Informações Institucionais</h2>
        <div class="card-box">
            <p><strong>Organização:</strong> Grid402 Network Intelligence</p>
            <p><strong>Email de Suporte:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
            <p><strong>Central Telefônica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            <p><strong>Carteira Oráculo (Base):</strong> <a href="https://basescan.org/address/0xa31c31069c43ac1c0075a93c325b0dcd7744356c" target="_blank" rel="noopener noreferrer">0xa31c31069c43ac1c0075a93c325b0dcd7744356c</a></p>
        </div></div>';
        grid402_render_page_html('About Grid402 - Global M2M Open Data & Network Utilities Hub', 'Saiba mais sobre a infraestrutura global e os 22 serviços públicos do Grid402 para agentes de IA e desenvolvedores.', '/about', 'About Grid402', $aboutBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'organization' => 'Grid402',
            'name' => 'Grid402 Public Data and Network Utilities Hub',
            'description' => 'Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.',
            'network' => 'Base Mainnet',
            'chain_id' => 8453,
            'oracle_wallet' => '0xa31c31069c43ac1c0075a93c325b0dcd7744356c',
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
        <p>Disponibilizamos canais diretos e estruturados para suporte técnico, parcerias para integradores de IA e contato com o Encarregado de Tratamento de Dados Pessoais (DPO):</p>
        <h2>Canais Oficiais de Atendimento</h2>
        <div class="card-box">
            <p><strong>Email de Suporte Geral e Integrações:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
            <p><strong>Encarregado de Dados (DPO / LGPD / GDPR):</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
            <p><strong>Central Telefônica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
        </div>
        <h2>Ecossistema e Código Aberto</h2>
        <ul>
            <li><strong>GitHub Oficial:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a></li>
            <li><strong>Pacote Node.js (npm):</strong> <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a></li>
            <li><strong>Pacote Python (PyPI):</strong> <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a></li>
            <li><strong>Servidor MCP (Model Context Protocol):</strong> <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a></li>
        </ul></div>';
        grid402_render_page_html('Contact Grid402 - Official Channels & Support', 'Entre em contato com o Grid402: canais oficiais de suporte técnico, encarregado de dados (DPO) e repositórios.', '/contact', 'Contact Grid402', $contactBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
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
        <p>Termos de serviço, licenciamento de dados públicos e conformidade regulatória internacional do hub global de micro-serviços e utilitários M2M Grid402.</p>
        <h2>1. Fornecimento no Estado em que se Encontra (AS-IS)</h2>
        <p>O Grid402 é uma infraestrutura técnica de oráculo M2M fornecida estritamente "NO ESTADO EM QUE SE ENCONTRA" (AS-IS), sem garantias de adequação a finalidades comerciais exclusivas, aconselhamento jurídico ou decisões de crédito.</p>
        <h2>2. Base Legal de Dados Públicos, Domínio Público e Licenças Abertas</h2>
        <p>Todas as 22 rotas do Grid402 processam exclusivamente informações de domínio público, fatos econômicos e registros governamentais abertos, livres de restrições proprietárias:</p>
        <ul>
            <li><strong>Global LEI (/api/v1/lei):</strong> Global Legal Entity Identifier Foundation (GLEIF) sob licença <strong>CC0 1.0 Universal (Public Domain Dedication)</strong>, padrão ISO 17442.</li>
            <li><strong>Sanções Internacionais (/api/v1/ofac):</strong> US Treasury OFAC SDN (domínio público federal sob <strong>17 U.S.C. § 105</strong>) e listas consolidadas públicas do Conselho de Segurança da ONU.</li>
            <li><strong>Mercados Globais e Câmbio (/api/v1/global-markets, /api/v1/forex, /api/v1/crypto):</strong> Taxas de referência do Banco Central Europeu (<strong>EU Open Data Directive 2019/1024</strong>), Banco Central do Brasil (Decreto nº 8.777/2016) e fatos econômicos de mercado não passíveis de copyright (<strong>Lei nº 9.610/1998 Art. 8º, I</strong> e <strong>17 U.S.C. § 102(b)</strong>).</li>
            <li><strong>Dados Corporativos e Feriados Nacionais (/api/v1/cnpj, /api/v1/sanctions, /api/v1/simples, /api/v1/compliance, /api/v1/indicators, /api/v1/holidays, /api/v1/workdays, /api/v1/cep):</strong> Lei de Acesso à Informação (<strong>Lei nº 12.527/2011 - LAI</strong>), Política de Dados Abertos (<strong>Decreto nº 8.777/2016</strong>), Lei Anticorrupção (Lei nº 12.846/2013) e algoritmos matemáticos de calendário civil (Lei nº 9.610/1998 Art. 8º, II e IV).</li>
            <li><strong>Telemetria de Rede e Notarização (/api/v1/dns, /api/v1/ssl, /api/v1/asn, /api/v1/notarize):</strong> Protocolos abertos da Internet (IETF RFC 1035, RFC 5280, RFC 4271 e RFC 8785).</li>
        </ul>
        <h2>3. Micropagamentos x402 e Session Passes</h2>
        <p>Pagamentos efetuados via protocolo HTTP 402 e emissões de Session Pass liquidados em USDC na Base Mainnet (EVM 8453) remuneram exclusivamente a infraestrutura computacional, normalização e assinatura criptográfica ECDSA secp256k1 do Oráculo, sendo definitivos e irreversíveis on-chain.</p>
        <h2>4. Direitos Autorais e Propriedade Intelectual Reservada</h2>
        <p>© ' . date('Y') . ' <strong>Grid402 Network Intelligence</strong>. <strong>Todos os direitos reservados.</strong> O software da plataforma Grid402, motor de liquidação x402, arquitetura de Session Pass, design visual, marca e documentações são de propriedade exclusiva do Grid402, protegidos pela <strong>Lei do Software (Lei nº 9.609/1998)</strong>, <strong>Lei de Direitos Autorais (Lei nº 9.610/1998)</strong> e tratados internacionais (Convenção de Berna). É vedada a reprodução, redistribuição ou engenharia reversa da plataforma sem autorização expressa. Os dados técnicos retornados pelas APIs provêm de registros públicos abertos e fatos de domínio público, sobre os quais não reivindicamos exclusividade proprietária.</p></div>';
        grid402_render_page_html('Terms of Service - Grid402', 'Termos de serviço, direitos autorais reservados (Leis 9.609/98 e 9.610/98) e base legal de dados públicos abertos (CC0, LAI, 17 U.S.C. 105) do Grid402.', '/terms', 'Terms of Service', $termsBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
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
        <p>Diretrizes de privacidade, proteção de dados e conformidade regulatória nacional e internacional (LGPD, GDPR e CCPA) do hub Grid402.</p>
        <h2>1. Arquitetura Zero-PII e Exclusão de Dados de Pessoas Físicas (LGPD Art. 1º / GDPR Recital 14)</h2>
        <p>O Grid402 opera estritamente com dados de <strong>Pessoas Jurídicas</strong>, indicadores macroeconômicos e telemetria de rede. Em conformidade com o <strong>Art. 1º da LGPD (Lei nº 13.709/2018)</strong> e o <strong>Considerando 14 (Recital 14) do GDPR Europeu (Regulamento UE 2016/679)</strong>, registros de pessoas jurídicas não constituem dados pessoais de pessoas naturais. Em consultas de CNPJ, o Grid402 <strong>filtra e descarta na origem</strong> o Quadro de Sócios e Administradores (QSA), nomes de sócios, CPFs, telefones pessoais e e-mails privados.</p>
        <h2>2. Minimização, Pseudonimização e Zero-Retention (/api/v1/notarize)</h2>
        <ul>
            <li><strong>Mascaramento e HMAC de IP:</strong> Endereços IP em métricas operacionais são truncados de forma irreversível (ex: <code>189.40.xxx.xxx</code>) e chaves de controle de taxa de Sandbox utilizam hash criptográfico <code>HMAC-SHA256</code> (pseudonimização conforme Art. 13 da LGPD e Art. 25 do GDPR).</li>
            <li><strong>Notarização Zero-Retention:</strong> No endpoint <code>/api/v1/notarize</code>, o conteúdo enviado é processado exclusivamente em memória RAM volátil para cálculo do digest <code>SHA-256</code> e descartado imediatamente, sem gravação ou exibição de texto bruto.</li>
        </ul>
        <h2>3. Guarda Legal de Registros e Prevenção a Fraudes</h2>
        <p>Registros técnicos estritos de conexão são mantidos sob sigilo pelo prazo legal em cumprimento ao <strong>Art. 15 do Marco Civil da Internet (Lei nº 12.965/2014)</strong>, e hashes públicos de transações na Base Mainnet (<code>X-Payment</code>) são retidos em cache temporário de 7 dias exclusivamente para prevenção de ataques de repetição (Replay Attack), com base no legítimo interesse e segurança cibernética (Art. 7º, IX da LGPD e Art. 6(1)(f) do GDPR).</p>
        <h2>4. Direitos do Titular e Encarregado de Dados (DPO)</h2>
        <p>O Grid402 não utiliza cookies de rastreamento publicitário e não vende ou compartilha informações pessoais (CCPA/CPRA). Para qualquer solicitação de conformidade, auditoria ou exercício de direitos perante o Encarregado de Dados (DPO), contate <a href="mailto:contato@grid402.online">contato@grid402.online</a>.</p></div>';
        grid402_render_page_html('Privacy Policy - Grid402', 'Diretrizes de privacidade, arquitetura Zero-PII e conformidade LGPD, GDPR e CCPA do Grid402.', '/privacy', 'Privacy Policy', $privacyBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
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

// 2. Classificação de Rotas
$isStatusRoute = ($requestPath === '/status' || $requestPath === '/api/v1/summary' || $requestPath === '/telemetry');
$isHomeRoute = ($requestPath === '/' || $requestPath === '/index.php');
$isVerifyRoute = ($requestPath === '/api/v1/verify' || $apiParam === 'verify');
$validApiEndpoints = ['summary', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'domain-audit', 'kyb-audit', 'market-snapshot', 'cep', 'cnpj', 'indicators', 'holidays', 'workdays', 'sanctions', 'simples', 'compliance', 'lei', 'ofac', 'notarize', 'global-markets', 'pass', 'pass-emitir'];
$isValidApiAction = in_array($apiParam, $validApiEndpoints, true);
$isInstitutionalRoute = in_array($requestPath, ['/about', '/contact', '/terms', '/privacy', '/admin', '/dashboard', '/agents'], true);

// 3. Rota Inexistente: Retorna HTTP 404 Real (Issue 3)
if (!$isHomeRoute && !$isStatusRoute && !$isValidApiAction && !$isInstitutionalRoute && !$isVerifyRoute) {
    http_response_code(404);
    if (str_contains($acceptHeader, 'text/html') && !str_starts_with($userAgent, 'curl/')) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="icon" type="image/x-icon" href="/favicon.ico"><title>404 - Pagina Nao Encontrada | Grid402</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet"><style>body{background:#060B19;color:#F8FAFC;font-family:\'Inter\',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column;text-align:center;padding:1rem}h1{font-family:\'Space Grotesk\',sans-serif;font-size:4rem;margin:0.5rem 0 0.25rem;color:#00F0FF;letter-spacing:-1px}p{color:#94A3B8;font-size:1.1rem;margin:0.5rem 0 1.5rem}a{background:#0052FF;color:#FFF;padding:0.75rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600;transition:opacity 0.2s}a:hover{opacity:0.9}</style></head><body><img src="/assets/logo-icon-transparent.png" alt="Grid402" width="64" height="64"><h1>404</h1><p>A pagina ou rota solicitada nao existe no Grid402.</p><p><a href="/">Voltar a pagina inicial</a></p></body></html>';
    } else {
        header('Content-Type: application/json; charset=utf-8');
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
                '/api/v1/pass',
                '/api/v1/verify',
                '/about',
                '/contact',
                '/terms',
                '/privacy',
                '/llms.txt',
                '/openapi.json',
                '/.well-known/mcp/server-card.json'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 4. Rota Gratuita: Resumo Geral do Hub (/status ou /?format=json)
if ($isStatusRoute || ($isHomeRoute && isset($_GET['format']) && $_GET['format'] === 'json')) {
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

    $response = array_merge($verification, [
        'verification_time_ms' => $elapsedMs,
        'hub' => 'Grid402',
        'status' => !empty($verification['valid']) ? 'AUTHENTIC_ORACLE_DATA' : 'VERIFICATION_FAILED',
    ]);

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Se for requisição para as APIs de dados técnicos
if ($isValidApiAction) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment');
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Authenticate, X-Accept-Payment, X-Payment-Status, X-Payment-Amount, X-Payment-Network, Payment-Required, payment-required');

    // 2.1. Consulta gratuita de saldo de Session Pass se passado ?status=pass_... ou ?token=pass_...
    if ($apiParam === 'pass' && (!empty($_GET['status']) || !empty($_GET['token']))) {
        $tokenQuery = (string) ($_GET['status'] ?? $_GET['token']);
        $passInfo = grid402_get_session_pass_status($tokenQuery);
        http_response_code($passInfo['code'] ?? 200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($passInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2.2. Definição de Preço Escalonado por Serviço e Verificação Prévia Defensiva
    $isPass = ($apiParam === 'pass');
    $isPassEmitir = ($apiParam === 'pass-emitir');
    $isCompliance = ($apiParam === 'compliance');
    $isSanctionsOrSimples = in_array($apiParam, ['sanctions', 'simples'], true);
    $isCorporateTier = in_array($apiParam, ['cnpj', 'lei', 'ofac'], true);

    if ($isPass) {
        $servicePriceDisplay = '0.0500';
        $servicePriceRaw = 5000; // Minimo de 0.0050 USDC (10 creditos) e recomendado 0.0500 USDC (100 creditos)
    } elseif ($isCompliance) {
        $servicePriceDisplay = '0.0030';
        $servicePriceRaw = 3000; // 0.003000 USDC
    } elseif ($isSanctionsOrSimples) {
        $servicePriceDisplay = '0.0010';
        $servicePriceRaw = 1000; // 0.001000 USDC
    } elseif ($isCorporateTier) {
        $servicePriceDisplay = GRID402_PRICE_CNPJ;
        $servicePriceRaw = GRID402_USDC_CNPJ_RAW;
    } else {
        $servicePriceDisplay = GRID402_PRICE_DISPLAY;
        $servicePriceRaw = GRID402_USDC_REQUIRED_RAW;
    }

    $isCnpjEndpoint = in_array($apiParam, ['cnpj', 'sanctions', 'simples', 'compliance'], true);
    if ($isCnpjEndpoint) {
        $cnpjInput = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '');
        $cnpjInputClean = preg_replace('/\D/', '', $cnpjInput);
        if ($cnpjInputClean === '' or !grid402_validate_cnpj_digits($cnpjInputClean)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'InvalidCNPJ',
                'message' => 'O CNPJ informado é matematicamente inválido ou possui dígitos verificadores incorretos.',
                'cnpj' => $cnpjInput,
                'code' => 400,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 2.3. Verificação de Pagamento x402 para Micro-serviços Técnicos com Teaser Preview
    if ($paymentProof === '') {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        $previewData = null;
        $lockedFields = null;

        if ($apiParam === 'cnpj') {
            $cnpjInput = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $cnpjResult = grid402_get_cnpj_preview($cnpjInput);
            if (!($cnpjResult['success'] ?? false)) {
                http_response_code($cnpjResult['code'] ?? 400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($cnpjResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            $previewData = $cnpjResult['preview'];
            $lockedFields = $cnpjResult['locked_fields'];
        } elseif ($apiParam === 'cep') {
            $cepInput = (string) ($_GET['code'] ?? $_GET['cep'] ?? '01310100');
            $cepResult = grid402_get_cep_preview($cepInput);
            if (!($cepResult['success'] ?? false)) {
                http_response_code($cepResult['code'] ?? 400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($cepResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            $previewData = $cepResult['preview'];
            $lockedFields = $cepResult['locked_fields'];
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
            $lockedFields = ['entity.legal_name', 'entity.legal_address', 'entity.registration_authority', 'entity.local_registration_id', '_oracle_proof'];
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
            $lockedFields = ['holiday_name', 'next_business_day', 'fx_reference.rates', 'market_holidays_calendar', '_oracle_proof'];
        } elseif ($apiParam === 'pass') {
            $previewData = [
                'product' => 'x402 Prepaid Session Pass (High-Frequency M2M & Web3 Top-Up)',
                'tier_starter' => '2.0000 USDC = 4,000 API calls (<15ms latency, 30 days validity)',
                'tier_pro' => '5.0000 USDC = 10,000 API calls (<15ms latency, 30 days validity)',
                'tier_fleet' => '20.0000 USDC = 50,000 API calls (<15ms latency, 60 days validity, prioritized)',
                'network' => 'Base Mainnet (Chain ID 8453)',
                'token' => 'USDC (0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913)',
                'recipient' => GRID402_WALLET_ADDRESS,
            ];
            $lockedFields = ['pass_token', 'credits_total', 'credits_remaining', 'expires_at', 'tier', '_oracle_proof'];
        }

        grid402_respond_402($serviceLabel, null, $servicePriceDisplay, $servicePriceRaw, $previewData, $lockedFields);
    }

    // Auditoria on-chain com trava atômica anti-replay e suporte a Modo Sandbox
    $clientIp = grid402_get_trusted_client_ip();

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
        $isSandbox = false;
        $isRealPayment = true;
        $proofIsPayload = false;
        $proofForLog = null;
    } else {
        $validation = grid402_validate_payment($paymentProof, $servicePriceRaw, $clientIp);
        // Payload x402 padrao (base64) nunca e gravado em telemetria nem ecoado na resposta
        $proofIsPayload = grid402_classify_payment_proof($paymentProof) === 'x402_payload';
        $proofForLog = $proofIsPayload ? null : $paymentProof;
        if (!$validation['valid']) {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        grid402_record_telemetry(
            $serviceLabel,
            $validation['code'],
            false,
            $proofForLog,
            null,
            (float)$servicePriceDisplay,
            null,
            ['failure_category' => $validation['category'] ?? 'payment_rejected']
        );
        http_response_code($validation['code']);
        header('Content-Type: application/json; charset=utf-8');
        if ($validation['code'] === 402) {
            header('X-Authenticate: x402');
            header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . $servicePriceDisplay);
        } elseif ($validation['code'] === 429 || $validation['code'] === 503) {
            header('Retry-After: ' . ($validation['retry_after'] ?? 3600));
        }

        $errPayload = [
            'error' => ($validation['code'] === 403) ? 'ReplayAttackDetected' : (($validation['code'] === 429) ? 'SandboxRateLimitExceeded' : (($validation['code'] === 503) ? 'SettlementUnavailable' : 'PaymentFailed')),
            'message' => $validation['reason'],
            'tx_hash' => $proofForLog,
        ];
        if ($validation['code'] === 402) {
            $errPayload['x402Version'] = 2;
            $errPayload['accepts'] = grid402_build_accepts((int) $servicePriceRaw);
            $errPayload['price_usdc'] = $servicePriceDisplay;
            $errPayload['recipient_wallet'] = GRID402_WALLET_ADDRESS;
        }
        if (isset($validation['sandbox'])) {
            $errPayload['sandbox'] = true;
        }
        echo json_encode($errPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    } // fecha else (pass-emitir pula a validacao x402 e segue direto ao switch abaixo)

    // Pagamento ou Sandbox validado com sucesso! Executa o micro-serviço requisitado
    $isSandbox = !empty($validation['sandbox']);
    $isVerifyOnly = (($validation['source'] ?? '') === 'facilitator_verify_only');
    $isRealPayment = !$isSandbox && !$isVerifyOnly;
    // Sandbox/demo e verify_only nao sao consulta paga: registra como 'sandbox'/'verify_only'
    $settlementTx = $validation['settlement_tx'] ?? null;
    grid402_record_telemetry(
        $apiParam,
        200,
        $isRealPayment,
        $settlementTx ?? $proofForLog,
        null,
        ($isSandbox || $isVerifyOnly) ? 0.0 : (float)$servicePriceDisplay,
        $isSandbox ? 'sandbox' : ($isVerifyOnly ? 'verify_only' : null),
        ['payment_source' => $validation['source'] ?? null]
    );
    if (!$isSandbox) {
        grid402_notify_telegram_purchase($apiParam, (float)$servicePriceDisplay, (string) ($settlementTx ?? $proofForLog ?? 'x402-exact'));
    }
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Payment-Status: Accepted');
    if (($validation['source'] ?? '') === 'facilitator') {
        $receiptHeader = grid402_build_payment_response_header($validation);
        header('PAYMENT-RESPONSE: ' . $receiptHeader);
        header('X-PAYMENT-RESPONSE: ' . $receiptHeader);
    }
    if ($isSandbox) {
        header('X-Payment-Mode: sandbox');
        header('X-Payment-Amount: 0.0000 USDC (Sandbox)');
        header('X-Payment-Network: ' . ($validation['network'] ?? 'Simulation (demo)'));
    } else {
        header('X-Payment-Mode: production');
        header('X-Payment-Amount: ' . $servicePriceDisplay . ' USDC');
        header('X-Payment-Network: Base (ChainID 8453)');
    }
    if (!empty($validation['session_pass'])) {
        header('X-Pass-Credits-Debited: ' . ($validation['credits_debited'] ?? 1));
        header('X-Pass-Credits-Remaining: ' . ($validation['credits_remaining'] ?? 0));
    }
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
        case 'domain-audit':
            $domain = (string) ($_GET['domain'] ?? $_GET['host'] ?? 'base.org');
            $result = grid402_service_domain_audit($domain);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'kyb-audit':
            $cnpjVal = (string) ($_GET['cnpj'] ?? '');
            $nameVal = (string) ($_GET['name'] ?? '');
            $leiVal = (string) ($_GET['lei'] ?? '');
            $result = grid402_service_kyb_audit($cnpjVal, $nameVal, $leiVal);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'market-snapshot':
            $result = grid402_service_market_snapshot();
            break;
        case 'cep':
            $code = (string) ($_GET['code'] ?? '01310100');
            $result = grid402_service_cep($code);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'cnpj':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_cnpj($cnpjVal);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'indicators':
            $result = grid402_service_indicators();
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'holidays':
            $yearParam = isset($_GET['year']) ? (int) $_GET['year'] : null;
            $result = grid402_service_holidays($yearParam);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'workdays':
            $result = grid402_service_workdays($_GET);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'sanctions':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_sanctions($cnpjVal);
            if (isset($result['code'])) {
                if ($result['code'] !== 200) {
                    http_response_code($result['code']);
                }
            }
            break;
        case 'simples':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_simples($cnpjVal);
            if (isset($result['code'])) {
                if ($result['code'] !== 200) {
                    http_response_code($result['code']);
                }
            }
            break;
        case 'compliance':
            $cnpjVal = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '00000000000191');
            $result = grid402_service_compliance($cnpjVal);
            if (isset($result['code'])) {
                if ($result['code'] !== 200) {
                    http_response_code($result['code']);
                }
            }
            break;
        case 'lei':
            $leiVal = (string) ($_GET['lei'] ?? '');
            $qVal = (string) ($_GET['q'] ?? '');
            $result = grid402_service_lei($leiVal, $qVal);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'ofac':
            $addrVal = (string) ($_GET['address'] ?? $_GET['target'] ?? '');
            $nameVal = (string) ($_GET['name'] ?? '');
            if ($addrVal !== '' && !preg_match('/^0x[a-fA-F0-9]{40}$/', trim($addrVal)) && $nameVal === '') {
                $nameVal = $addrVal;
                $addrVal = '';
            }
            $result = grid402_service_ofac($addrVal, $nameVal);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'notarize':
            $dataVal = (string) ($_GET['data'] ?? $_GET['content'] ?? '');
            $hashVal = (string) ($_GET['hash'] ?? '');
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dataVal === '' && $hashVal === '') {
                $bodyRaw = (string) file_get_contents('php://input');
                $dataVal = substr($bodyRaw, 0, 8192);
            }
            $result = grid402_service_notarize($dataVal, $hashVal);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'global-markets':
            $mktVal = (string) ($_GET['market'] ?? 'US');
            $dtVal = (string) ($_GET['date'] ?? '');
            $baseVal = (string) ($_GET['base'] ?? 'USD');
            $result = grid402_service_global_markets($mktVal, $dtVal, $baseVal);
            if (isset($result['code']) && $result['code'] !== 200) {
                http_response_code($result['code']);
            }
            break;
        case 'pass':
            $rawUnitsPaid = (int) ($validation['raw_amount'] ?? 50000);
            $result = grid402_issue_session_pass($paymentProof, $rawUnitsPaid, $isSandbox);
            break;

        // Web3 One-Click Top-Up: emite um Session Pass a partir de uma txHash USDC
        // já confirmada on-chain (Base Mainnet). Não exige X-Payment prévio.
        case 'pass-emitir':
            $txParam = (string) ($_GET['tx'] ?? $_GET['txHash'] ?? $_GET['tx_hash'] ?? '');
            if ($txParam === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawPost = (string) @file_get_contents('php://input');
                $parsedPost = @json_decode($rawPost, true);
                if (is_array($parsedPost)) {
                    $txParam = trim((string) ($parsedPost['tx'] ?? $parsedPost['txHash'] ?? $parsedPost['tx_hash'] ?? ''));
                }
            }
            $result = grid402_emitir_passe_por_tx($txParam);
            // Tratamento de códigos de erro do checkout
            if (!empty($result['code']) && $result['code'] !== 200) {
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

    if ($isSandbox && is_array($result)) {
        $result['sandbox'] = true;
        $result['sandbox_info'] = [
            'quota_remaining_this_hour' => $validation['remaining'] ?? 0,
            'quota_limit_per_hour' => $validation['limit'] ?? 20,
            'message' => 'Consulta executada no Modo Sandbox de testes. Para chamadas em produção, utilize a Base Mainnet.',
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
        grid402_sign_data($result, $isSandbox);
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
grid402_log_public_visit('/');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 - Hub de Utilitarios de Rede e Dados Publicos com Micropagamentos x402 na Base</title>
    <meta name="description" content="Grid402: Plataforma de micro-servicos publicos (CNPJ, CEP, Forex, Cripto, DNS, SSL, ASN) para agentes autonomos de IA e desenvolvedores, com micropagamentos x402 em USDC na rede Base.">
    <link rel="canonical" href="https://grid402.online/">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="sitemap" type="application/xml" href="/sitemap.xml">
    <link rel="service-meta" type="application/json" href="/.well-known/mcp/server-card.json">

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
          "priceRange": "$0.0005 - $0.0030",
          "sameAs": [
            "https://github.com/oliveiratc/Grid402",
            "https://www.npmjs.com/package/grid402",
            "https://www.npmjs.com/package/grid402-mcp",
            "https://pypi.org/project/grid402/",
            "https://basescan.org/address/0xa31c31069c43ac1c0075a93c325b0dcd7744356c"
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
            "price": "0.0005",
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
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
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
    <div class="container">
        <header>
            <a href="/" class="logo-wrap">
                <img src="/assets/logo-icon-transparent.png" alt="Grid402" width="38" height="38" style="display: block;">
                <span class="logo-title">Grid<span style="color: var(--accent);">402</span></span>
                <span class="logo-badge">x402</span>
            </a>
            <nav class="nav-links">
                <a href="#services" data-i18n="nav.services">Serviços</a>
                <a href="#topup" style="color: #00F0FF; font-weight: 700;" data-i18n="nav.topup">Abastecer Saldo</a>
                <a href="#comparador" style="color: var(--accent); font-weight: 600;" data-i18n="nav.savings">Economia 98%</a>
                <a href="#playground" data-i18n="nav.playground">Playground</a>
                <a href="#console" data-i18n="nav.console">Console</a>
                <a href="/agents" style="color: var(--accent); font-weight: 600;" data-i18n="nav.agents">Agentes IA</a>
                <a href="/catalog" style="color: var(--accent); font-weight: 600;">Catálogo</a>
                <a href="/trust" style="color: var(--accent); font-weight: 600;">Trust</a>
                <a href="/about" data-i18n="nav.about">About</a>
                <a href="/contact" data-i18n="nav.contact">Contact</a>
                <a href="/terms" data-i18n="nav.terms">Terms</a>
                <a href="/privacy" data-i18n="nav.privacy">Privacy</a>
            </nav>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <div class="lang-switcher-wrap" id="lang-switch-btn" title="Alternar idioma / Switch language">
                    <span class="lang-globe-icon">🌐</span>
                    <button type="button" class="lang-btn active" id="lang-btn-pt" onclick="setLanguage('pt')">PT</button>
                    <span class="lang-divider">|</span>
                    <button type="button" class="lang-btn" id="lang-btn-en" onclick="setLanguage('en')">EN</button>
                </div>
                <div class="badge-live">
                    <span class="badge-pulse"></span>
                    <span data-i18n-count="<?php echo count($servicesCatalog); ?>" id="badge-live-text">Base Mainnet (8453) • <?php echo count($servicesCatalog); ?>/<?php echo count($servicesCatalog); ?> Serviços Operantes</span>
                </div>
            </div>
        </header>

        <section class="hero">
            <div class="hero-tag" data-i18n="hero.tag">Hub de Micro-Serviços para Robôs e Agentes de IA</div>
            <h1 data-i18n="hero.title">Grid402: Hub de Utilitários de Rede e Dados Públicos para Agentes de IA</h1>
            <p data-i18n="hero.desc">Acesse cotações de câmbio, cripto, suíte de compliance e antifraude (score 360, sanções da CGU e Simples Nacional), indicadores econômicos oficiais (Selic, CDI e IPCA), feriados bancários ANBIMA, DNS, certificados SSL, BGP/ASN, CEP e CNPJ da Receita Federal em milissegundos sem cadastro ou chaves de API. Micropagamentos por chamada em USDC na Base.</p>
            <div class="cta-group">
                <a href="#playground" class="btn btn-primary" style="background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); font-weight: 800; box-shadow: 0 4px 15px rgba(0, 82, 255, 0.4);">⚡ Testar agora (Sandbox)</a>
                <a href="/catalog" class="btn btn-outline" style="border-color: var(--primary); color: var(--primary); font-weight: 700;">Ver preços, limites e exemplos →</a>
                <a href="/agents" class="btn btn-outline" data-i18n="hero.btn.agents">🤖 Integrar no seu agente</a>
            </div>
            <p style="margin-top: .7rem; font-size: .82rem; color: var(--text-muted);">
                Sem cartão, sem API key, sem cadastro. Sandbox: <strong>30 consultas por hora por IP</strong>. Produção: x402 ou Session Pass.
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

        <!-- Comparativo e Calculadora de Economia: Tradicional vs Grid402 -->
        <section id="comparador" class="roi-calculator-box">
            <div class="playground-header-wrap">
                <div class="hero-tag" style="margin-bottom: 0.4rem; background: rgba(0, 240, 255, 0.12); color: var(--accent); border: 1px solid rgba(0, 240, 255, 0.3);" data-i18n="roi.tag">Redução Real de Custos para Startups e Agentes</div>
                <h2 data-i18n="roi.title">Por que pagar R$ 500 por mês se o seu agente pode pagar R$ 0,01 por consulta?</h2>
                <p data-i18n="roi.desc">Compare o modelo arcaico dos birôs tradicionais (mensalidades obrigatórias, análise de crédito e fidelidade de 12 meses) com o modelo aberto M2M do Grid402 liquidado via protocolo x402 na Base.</p>
            </div>

            <div class="roi-grid">
                <!-- Coluna 1: Tabela Comparativa -->
                <div class="roi-table-card">
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem;" data-i18n="roi.table.title">
                            Comparativo Técnico e Comercial
                        </div>
                        <div class="roi-table-wrap">
                            <table class="roi-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="roi.table.th.criteria">Critério</th>
                                        <th data-i18n="roi.table.th.trad">Birôs Tradicionais</th>
                                        <th data-i18n="roi.table.th.grid">Grid402 (x402)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong data-i18n="roi.table.cnpj">Consulta CNPJ</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.cnpj.trad">R$ 0,50 a R$ 2,50</td>
                                        <td class="col-grid" data-i18n="roi.table.cnpj.grid">R$ 0,01 (0.0020 USDC)</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.monthly">Mensalidade Mínima</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.monthly.trad">R$ 500 a R$ 2.000 / mês</td>
                                        <td class="col-grid" data-i18n="roi.table.monthly.grid">R$ 0,00 (Zero mensalidade)</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.contract">Burocracia e Contrato</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.contract.trad">Fidelidade 12 meses</td>
                                        <td class="col-grid" data-i18n="roi.table.contract.grid">Zero contrato, sem cadastro</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.auth">Autenticação</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.auth.trad">API Keys que expiram</td>
                                        <td class="col-grid" data-i18n="roi.table.auth.grid">Protocolo x402 nativo</td>
                                    </tr>
                                    <tr>
                                        <td><strong data-i18n="roi.table.math">Garantia Matemática</strong></td>
                                        <td class="col-trad" data-i18n="roi.table.math.trad">Nenhuma (dados crus)</td>
                                        <td class="col-grid" data-i18n="roi.table.math.grid">Assinatura ECDSA secp256k1</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;" data-i18n="roi.table.disclaimer">
                        * Valores de mercado baseados em planos de entrada dos principais birôs de dados e compliance no Brasil.
                    </div>
                </div>

                <!-- Coluna 2: Calculadora Interativa de Economia -->
                <div class="roi-calc-card">
                    <div style="font-size: 0.95rem; font-weight: 700; color: #fff;" data-i18n="roi.calc.title">
                        Simulador de Economia em CNPJ e Dados
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;" data-i18n="roi.calc.subtitle">
                        Ajuste o volume mensal de consultas do seu robô ou sistema:
                    </div>

                    <div class="roi-slider-wrap">
                        <div class="roi-slider-label">
                            <span data-i18n="roi.calc.label">Volume Mensal de Consultas:</span>
                            <span id="roi-vol-display" class="roi-slider-val">1.000 / mes</span>
                        </div>
                        <input type="range" id="roi-slider-input" class="roi-slider" min="100" max="20000" step="100" value="1000" oninput="updateRoiCalc(this.value)">
                        
                        <div class="roi-presets-row">
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(500)">500/mes</button>
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(1000)">1.000/mes</button>
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(5000)">5.000/mes</button>
                            <button type="button" class="roi-preset-btn" onclick="setRoiPreset(15000)">15.000/mes</button>
                        </div>
                    </div>

                    <div class="roi-cost-summary">
                        <div class="roi-cost-item">
                            <div class="roi-cost-item-label" data-i18n="roi.calc.cost.trad">Custo Tradicional</div>
                            <div id="roi-cost-trad" class="roi-cost-item-val" style="color: #EF4444;">R$ 1.500,00</div>
                        </div>
                        <div class="roi-cost-item">
                            <div class="roi-cost-item-label" data-i18n="roi.calc.cost.grid">Custo no Grid402</div>
                            <div id="roi-cost-grid" class="roi-cost-item-val" style="color: var(--primary);">R$ 10,80</div>
                        </div>
                    </div>

                    <div class="roi-savings-badge">
                        <div id="roi-savings-val" class="roi-savings-num">R$ 1.489,20</div>
                        <div id="roi-savings-desc" class="roi-savings-sub">Economia estimada de 99% todos os meses!</div>
                    </div>

                    <div style="display: flex; gap: 0.6rem; margin-top: 1.25rem;">
                        <a href="/agents" class="btn-sm btn-primary" style="flex: 1; text-decoration: none; padding: 0.6rem; display: inline-block;" data-i18n="roi.calc.btn.agents">🤖 Integrar Agente IA</a>
                        <a href="#playground" class="btn-sm btn-outline" style="flex: 1; text-decoration: none; padding: 0.6rem; display: inline-block; border-color: var(--primary); color: var(--primary);" onclick="selectPlaygroundPreset('cnpj')" data-i18n="roi.calc.btn.test">⚡ Testar CNPJ</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Web3 One-Click Top-Up: Passes de Sessao Pre-Pagos na Base -->
        <section id="topup" class="protocol-box topup-section" style="border: 1px solid rgba(0, 82, 255, 0.45); background: linear-gradient(180deg, rgba(0, 82, 255, 0.08) 0%, rgba(10, 14, 23, 0.98) 100%); margin-bottom: 3rem;">
            <div class="playground-header-wrap" style="text-align: center; max-width: 820px; margin: 0 auto 2rem auto;">
                <div class="hero-tag" style="margin-bottom: 0.5rem; background: rgba(0, 82, 255, 0.15); color: #00F0FF; border: 1px solid rgba(0, 240, 255, 0.35);" data-i18n="topup.tag">⚡ Liquidacao Instantanea em Lote na Base (USDC)</div>
                <h2 style="font-size: 1.8rem; letter-spacing: -0.5px; margin-bottom: 0.75rem;" data-i18n="topup.title">Abasteca seu Saldo Web3 em 1 Clique (Session Passes)</h2>
                <p style="text-align: center; color: var(--text-muted); font-size: 0.98rem; line-height: 1.6;" data-i18n="topup.desc">Pague uma unica vez em USDC na rede Base Mainnet e receba na hora um token <code style="color: #00F0FF; background: rgba(0,240,255,0.1); padding: 2px 6px; border-radius: 4px;">pass_g402_...</code> com saldo de milhares de requisições de alta velocidade (&lt;15ms) sem assinar transações a cada GET.</p>
                <div style="text-align:center; background: rgba(0,240,255,.08); border:1px solid rgba(0,240,255,.24); border-radius:8px; padding:.75rem .9rem; margin-bottom:1.2rem; font-size:.82rem; color:#CBD5E1;">
                    <strong style="color:#fff;">Como ler o saldo:</strong> 1 crédito custa 0.0005 USDC no serviço padrão. Corporativo debita 4 créditos (CNPJ, LEI, OFAC) e 6 créditos (Compliance 360). <a href="/catalog" style="color:#00F0FF; font-weight:700;">Ver tabela oficial /catalog</a>
                </div>
                <div style="display: flex; justify-content: center; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                    <span style="font-size: 0.85rem; color: #8FA2B9;">Rede: <strong style="color: #fff;">Base Mainnet (8453)</strong></span>
                    <span style="color: var(--border);">•</span>
                    <span style="font-size: 0.85rem; color: #8FA2B9;">Moeda: <strong style="color: #2775CA;">USDC</strong></span>
                    <span style="color: var(--border);">•</span>
                    <span style="font-size: 0.85rem; color: #8FA2B9;">Contrato: <code style="color: #a0aec0; font-size: 0.78rem;">0x8335...2913</code></span>
                </div>
            </div>

            <!-- Tiers de Passes Web3 -->
            <div class="topup-tiers-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Card 1: Starter -->
                <div class="topup-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.75rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                    <div>
                        <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #8FA2B9; letter-spacing: 1px; margin-bottom: 0.5rem;" data-i18n="topup.starter.tag">Starter Pass</div>
                        <div style="display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.75rem;">
                            <span style="font-size: 2.2rem; font-weight: 800; color: #fff;">2</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: #2775CA;">USDC</span>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;" data-i18n="topup.starter.desc">Ideal para desenvolvedores individuais, testes rapidos e bots pontuais.</p>
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0; font-size: 0.88rem; color: #d1d5db; line-height: 1.8;">
                            <li>⚡ <strong style="color: #00F0FF;">4.000 créditos</strong> incluídos</li>
                            <li>⏱️ Validade de <strong style="color: #fff;">30 dias</strong></li>
                            <li>🚀 Latencia ultrarrapida &lt;15ms</li>
                            <li>🔒 Assinatura HMAC anti-fraude</li>
                        </ul>
                    </div>
                    <button class="btn btn-outline" style="width: 100%; border-color: #2775CA; color: #fff;" onclick="startWeb3Topup(2, 'starter')" id="btn-topup-starter" data-i18n="topup.starter.btn">Comprar Starter (2 USDC)</button>
                </div>

                <!-- Card 2: Pro (Mais Popular) -->
                <div class="topup-card featured" style="background: linear-gradient(180deg, rgba(0, 82, 255, 0.15) 0%, var(--surface) 100%); border: 2px solid #0052FF; border-radius: 12px; padding: 1.75rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; position: relative; box-shadow: 0 8px 30px rgba(0, 82, 255, 0.25);">
                    <div style="position: absolute; top: -12px; right: 20px; background: #0052FF; color: #fff; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.5px;" data-i18n="topup.pro.badge">Mais Escolhido</div>
                    <div>
                        <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #00F0FF; letter-spacing: 1px; margin-bottom: 0.5rem;" data-i18n="topup.pro.tag">Pro / Agent Pass</div>
                        <div style="display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.75rem;">
                            <span style="font-size: 2.2rem; font-weight: 800; color: #fff;">5</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: #2775CA;">USDC</span>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;" data-i18n="topup.pro.desc">Para agentes autonomos de IA e esteiras de automacao continua.</p>
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0; font-size: 0.88rem; color: #d1d5db; line-height: 1.8;">
                            <li>⚡ <strong style="color: #00F0FF;">10.000 créditos</strong> incluídos</li>
                            <li>⏱️ Validade de <strong style="color: #fff;">30 dias</strong></li>
                            <li>🤖 Perfeito para ElizaOS e AgentKit</li>
                            <li>📊 Consulta de saldo em tempo real</li>
                        </ul>
                    </div>
                    <button class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); font-weight: 700;" onclick="startWeb3Topup(5, 'pro')" id="btn-topup-pro" data-i18n="topup.pro.btn">Comprar Pro (5 USDC)</button>
                </div>

                <!-- Card 3: Fleet / Enterprise -->
                <div class="topup-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.75rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                    <div style="position: absolute; top: -12px; right: 20px; background: rgba(0, 240, 255, 0.2); border: 1px solid #00F0FF; color: #00F0FF; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px;" data-i18n="topup.fleet.badge">+10.000 Bonus</div>
                    <div>
                        <div style="font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #8FA2B9; letter-spacing: 1px; margin-bottom: 0.5rem;" data-i18n="topup.fleet.tag">Fleet / Enterprise</div>
                        <div style="display: flex; align-items: baseline; gap: 0.35rem; margin-bottom: 0.75rem;">
                            <span style="font-size: 2.2rem; font-weight: 800; color: #fff;">20</span>
                            <span style="font-size: 1.1rem; font-weight: 700; color: #2775CA;">USDC</span>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;" data-i18n="topup.fleet.desc">Frotas de agentes autonomos, sistemas ERP corporativos e alta demanda.</p>
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0; font-size: 0.88rem; color: #d1d5db; line-height: 1.8;">
                            <li>⚡ <strong style="color: #00F0FF;">50.000 créditos</strong> (+10k bônus)</li>
                            <li>⏱️ Validade estendida de <strong style="color: #fff;">60 dias</strong></li>
                            <li>🛡️ Rate-limit prioritario garantido</li>
                            <li>💼 Suporte direto para frotas de IA</li>
                        </ul>
                    </div>
                    <button class="btn btn-outline" style="width: 100%; border-color: #00F0FF; color: #00F0FF;" onclick="startWeb3Topup(20, 'fleet')" id="btn-topup-fleet" data-i18n="topup.fleet.btn">Comprar Fleet (20 USDC)</button>
                </div>
            </div>

            <!-- Caixa de Feedback / Status da Operacao -->
            <div id="topup-status-box" style="display: none; background: rgba(10, 14, 23, 0.85); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span id="topup-spinner" class="spinner" style="display: inline-block; width: 18px; height: 18px; border: 2px solid #00F0FF; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                        <strong id="topup-status-title" style="color: #fff; font-size: 0.95rem;">Processando abastecimento...</strong>
                    </div>
                    <button class="btn-sm btn-outline" onclick="closeTopupStatus()" style="font-size: 0.75rem; padding: 2px 8px;">✕ Fechar</button>
                </div>
                <div id="topup-status-msg" style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; font-family: 'JetBrains Mono', monospace; word-break: break-all;"></div>
            </div>

            <!-- Card de Sucesso: Token Gerado -->
            <div id="topup-success-card" style="display: none; background: rgba(0, 240, 255, 0.06); border: 1px solid #00F0FF; border-radius: 12px; padding: 1.75rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.3rem;">🎉</span>
                    <h3 style="color: #00F0FF; font-size: 1.2rem; margin: 0;">Session Pass Emitido com Sucesso!</h3>
                </div>
                <p style="color: #d1d5db; font-size: 0.9rem; margin-bottom: 1rem;">Seu passe pre-pago esta ativo na rede Base. Utilize o token abaixo no cabecalho <code style="color: #00F0FF;">X-Payment</code> em qualquer uma das 22 APIs do Grid402.</p>
                
                <div style="background: #000; border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 8px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <code id="topup-result-token" style="color: #00F0FF; font-size: 0.95rem; font-weight: 700; word-break: break-all;">pass_g402_...</code>
                    <button class="btn-sm btn-primary" onclick="copyTopupToken()" id="btn-copy-topup-token" style="white-space: nowrap;">📋 Copiar Token</button>
                </div>

                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem; color: #8FA2B9; margin-bottom: 1rem;">
                    <div>Creditos Disponiveis: <strong id="topup-result-credits" style="color: #fff;">0</strong></div>
                    <div>Plano: <strong id="topup-result-tier" style="color: #00F0FF; text-transform: uppercase;">-</strong></div>
                    <div>Expira em: <strong id="topup-result-expires" style="color: #fff;">-</strong></div>
                </div>

                <div style="background: rgba(0,0,0,0.4); border-radius: 6px; padding: 0.75rem; font-size: 0.82rem; color: #a0aec0;">
                    <strong style="color: #fff;">Exemplo de chamada com seu Passe:</strong><br>
                    <code id="topup-example-curl" style="color: #8FA2B9; display: block; margin-top: 0.4rem; font-family: 'JetBrains Mono', monospace;">curl "https://grid402.online/api/v1/forex" -H "X-Payment: ..."</code>
                </div>
            </div>

            <!-- Ativação Manual por Hash / Pagamento Direto de Qualquer Carteira -->
            <div id="manual-topup-box" style="background: rgba(15, 23, 42, 0.7); border: 1px dashed rgba(0, 240, 255, 0.35); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.25rem;">📱</span>
                        <strong style="color: #fff; font-size: 1rem;">Pagamento Direto ou Mobile (Qualquer Carteira)</strong>
                    </div>
                    <span style="font-size: 0.8rem; background: rgba(0, 82, 255, 0.2); color: #00F0FF; padding: 2px 10px; border-radius: 20px; border: 1px solid rgba(0, 240, 255, 0.3);">Sem Extensão Necessária</span>
                </div>
                <p style="color: #94a3b8; font-size: 0.88rem; margin-bottom: 1rem; line-height: 1.5;">
                    Se você está no celular ou usa <strong>Binance, Coinbase App, Phantom ou MetaMask Mobile</strong>, basta transferir <strong>2, 5 ou 20 USDC</strong> na rede <strong>Base</strong> para a Oracle da Grid402 e colar o hash da transação abaixo:
                </p>

                <div style="background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div style="font-size: 0.82rem; color: #8FA2B9;">
                        Endereço da Oracle (Rede Base):<br>
                        <code id="oracle-wallet-addr" style="color: #00F0FF; font-weight: 700; word-break: break-all;">0xa31c31069c43ac1c0075a93c325b0dcd7744356c</code>
                    </div>
                    <button class="btn-sm btn-outline" onclick="copyOracleAddress()" id="btn-copy-oracle" style="border-color: rgba(0,240,255,0.4); color: #00F0FF; white-space: nowrap;">📋 Copiar Endereço</button>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <input type="text" id="manual-pass-tx-input" placeholder="Cole o Hash da Transação na Base (0x...)" style="flex: 1; min-width: 260px; background: #000; border: 1px solid rgba(0, 240, 255, 0.4); border-radius: 6px; padding: 0.65rem 1rem; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: 0.88rem;">
                    <button class="btn btn-primary" id="btn-manual-activate" onclick="activateManualPass()" style="background: linear-gradient(135deg, #0052FF 0%, #00F0FF 100%); font-weight: 700; white-space: nowrap; padding: 0.65rem 1.25rem;">⚡ Validar e Ativar Passe</button>
                </div>
            </div>

            <!-- Alternativa Manual / Smart Wallet Passkey -->
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap; padding-top: 1rem; border-top: 1px solid var(--border);">
                <button class="btn-sm btn-outline" style="border-color: rgba(255,255,255,0.2); color: #fff;" onclick="startWeb3TopupWithSmartWallet(5, 'pro')" id="btn-topup-passkey">🔑 Pagar via Passkey / Biometria (Coinbase Smart Wallet)</button>
                <a href="#console" class="btn-sm btn-outline" style="border-color: rgba(255,255,255,0.2); color: #8FA2B9;" onclick="switchTab('console')">💻 Ver Console e Documentacao Tecnica</a>
            </div>
        </section>

        <!-- Playground Interativo e Verificador de Oraculo -->
        <section id="playground" class="protocol-box">
            <div class="playground-header-wrap">
                <div class="hero-tag" style="margin-bottom: 0.4rem;" data-i18n="playground.tag">Auditoria Criptográfica M2M</div>
                <h2 data-i18n="playground.title">Playground Interativo e Verificador de Oráculo</h2>
                <p data-i18n="playground.desc">Execute consultas reais nos <?php echo count($servicesCatalog); ?> micro-serviços do Grid402 em modo Sandbox gratuito ou simule o desafio x402. Valide instantaneamente a atestação criptográfica ECDSA secp256k1 (_oracle_proof) com o motor matemático em tempo real.</p>
            </div>

            <!-- Presets de Micro-Servicos -->
            <div class="playground-presets" id="playground-preset-bar">
                <!-- Preenchido via JavaScript com todos os micro-servicos -->
            </div>

            <!-- Barra de Controles e Alternador de Modo -->
            <div class="playground-toolbar">
                <div class="mode-toggle-group">
                    <button type="button" class="mode-toggle-btn active" id="btn-mode-challenge" onclick="setPlaygroundMode('challenge')" data-i18n="playground.btn.challenge">🔒 Desafio x402 (HTTP 402)</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-sandbox" onclick="setPlaygroundMode('sandbox')" data-i18n="playground.btn.sandbox">🧪 Sandbox (30 consultas/h)</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-manual" onclick="setPlaygroundMode('manual')" data-i18n="playground.btn.manual">✍️ Validar JSON Externo</button>
                </div>
                <div class="playground-url-bar">
                    <span data-i18n="playground.route">Rota:</span>
                    <span id="playground-endpoint-display" class="playground-url-pill">GET /api/v1/workdays</span>
                </div>
                <div>
                    <button type="button" id="btn-run-query" class="btn-sm btn-primary" onclick="runPlaygroundQuery()" data-i18n="playground.btn.run">⚡ Executar Consulta</button>
                </div>
            </div>

            <!-- Painel de Execucao em 2 Colunas -->
            <div class="playground-grid">
                <!-- Coluna 1: Parametros ou Area de JSON Manual -->
                <div class="playground-card" id="playground-col-params">
                    <div class="playground-card-title">
                        <span id="playground-col-title" data-i18n="playground.config.title">Configuração da Consulta</span>
                        <span id="playground-service-badge" class="card-category">workdays</span>
                    </div>

                    <div id="playground-preset-controls">
                        <p id="playground-service-desc" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            Cálculo de dias úteis bancários ANBIMA e projeção de prazos D+N.
                        </p>
                        <div id="playground-params-inputs" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                            <!-- Parametros dinamicos do preset atual -->
                        </div>
                    </div>

                    <div id="playground-manual-controls" style="display: none; flex-direction: column; height: 100%;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;" data-i18n="playground.manual.desc">
                            Cole abaixo qualquer resposta JSON retornada pelo Grid402 ou utilize um dos modelos de teste:
                        </p>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                            <button type="button" class="btn-sm" onclick="loadManualTemplate('valid')" data-i18n="playground.btn.template.valid">Carregar Modelo Válido</button>
                            <button type="button" class="btn-sm" onclick="loadManualTemplate('tampered')" data-i18n="playground.btn.template.tampered">Carregar Modelo Adulterado (Falso)</button>
                            <button type="button" class="btn-sm btn-primary" onclick="verifyManualPayload()" data-i18n="playground.btn.audit.manual">🔍 Auditar Assinatura deste JSON</button>
                        </div>
                        <textarea id="playground-manual-input" class="console-input" style="flex: 1; min-height: 220px; font-family: var(--font-mono); font-size: 0.8rem; resize: vertical;" placeholder="Cole o JSON da resposta contendo o bloco _oracle_proof..."></textarea>
                    </div>
                </div>

                <!-- Coluna 2: Retorno JSON e Card de Auditoria -->
                <div class="playground-card">
                    <div class="playground-card-title">
                        <span data-i18n="playground.viewer.title">Retorno da API</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span id="playground-http-status" class="card-category" data-i18n="playground.status.ready">Pronto</span>
                            <span id="playground-latency-tag" class="card-category" style="display: none;"></span>
                            <button type="button" class="btn-copy-json" id="btn-copy-playground" onclick="copyPlaygroundJson()" data-i18n="playground.btn.copy">Copiar JSON</button>
                        </div>
                    </div>

                    <!-- Card de Conversão Instantânea x402 (Unlock & Blur) -->
                    <div id="playground-unlock-card" class="playground-unlock-card" style="display: none;">
                        <div class="unlock-card-header">
                            <span class="unlock-badge-lock" data-i18n="playground.unlock.badge">🔒 DADOS COMPLETOS PROTEGIDOS (HTTP 402)</span>
                            <span id="playground-unlock-price" class="unlock-price-pill">0.0005 USDC • Base</span>
                        </div>
                        <p style="font-size: 0.84rem; color: #e2e8f0; margin-bottom: 0.5rem; line-height: 1.45;" data-i18n="playground.unlock.desc">
                            A amostra gratuita acima foi validada. Desbloqueie agora os campos ocultos e a prova criptográfica ECDSA (<code>_oracle_proof</code>):
                        </p>
                        <div id="playground-locked-fields-grid" class="locked-fields-grid"></div>
                        <div class="unlock-cta-group">
                            <div style="background: rgba(51,65,85,.35); border:1px solid var(--border); border-radius:8px; padding:.75rem .9rem; margin-bottom:.65rem; font-size:.82rem; color:#cbd5e1;">
                                <strong style="color:#fff;">Fluxo principal:</strong> clique em “Desbloquear agora”. Preço do endpoint (R$/USDC) está no catálogo.
                            </div>
                            <button type="button" id="btn-playground-web3-pay" class="btn-sm btn-primary" onclick="payPlaygroundWithWeb3()" style="padding: 0.6rem 1.15rem; font-size: 0.85rem; font-weight: 800;" data-i18n="playground.unlock.cta_web3">
                                ⚡ Desbloquear agora (1 clique / carteira Web3)
                            </button>
                            <details style="margin-top:.6rem; border:1px solid var(--border); border-radius:8px; padding:.55rem .75rem; background:#0b132b;">
                                <summary style="cursor:pointer; color:#00F0FF; font-weight:700;">Outras formas de desbloquear</summary>
                                <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.7rem;">
                                    <button type="button" id="btn-playground-smart-wallet" class="btn-sm btn-outline" onclick="payPlaygroundWithCoinbaseSmartWallet()" style="padding: 0.5rem .85rem; font-size: 0.78rem; border-color: #0052ff; background: rgba(0, 82, 255, 0.14); color: #fff; font-weight: 600;" data-i18n="playground.unlock.cta_smart_wallet">
                                        🔑 Passkey / Biometria
                                    </button>
                                    <button type="button" class="btn-sm btn-outline" onclick="openPlaygroundWeb3Modal()" style="padding: 0.5rem .85rem; font-size: 0.78rem;" data-i18n="playground.unlock.cta_qr">
                                        📱 QR / Celular
                                    </button>
                                    <a class="btn-sm btn-outline" href="/catalog" style="text-decoration:none; display:inline-flex; border-color: var(--border); color: var(--muted); padding: 0.5rem .85rem; font-size: .78rem;">Ver preço do endpoint</a>
                                    <button type="button" class="btn-sm btn-outline" onclick="unlockPlaygroundViaSandbox()" style="padding: 0.5rem .85rem; font-size: 0.78rem; border-color: rgba(0, 240, 255, 0.35); color: var(--accent);" data-i18n="playground.unlock.cta_sandbox">
                                        🧪 Ver amostra Sandbox
                                    </button>
                                </div>
                            </details>
                        </div>
                    </div>

                    <pre id="playground-json-viewer" class="json-viewer-pre" data-i18n="playground.viewer.placeholder">// O resultado da consulta ou da auditoria aparecera aqui...</pre>

                    <!-- Card de Auditoria em 3 Etapas -->
                    <div id="playground-audit-card" class="audit-card" style="display: none;">
                        <div class="audit-card-header">
                            <div>
                                <span style="font-weight: 700; color: #fff; font-size: 0.95rem;" data-i18n="playground.audit.title">Motor de Auditoria Matemática ECDSA (Browser)</span>
                                <span id="audit-engine-badge" style="font-size: 0.75rem; color: var(--accent); margin-left: 0.5rem; font-family: var(--font-mono);">OpenSSL C (secp256k1)</span>
                            </div>
                            <span id="audit-overall-badge" class="audit-badge badge-pending">AGUARDANDO ANALISE</span>
                        </div>

                        <div class="audit-steps-list">
                            <!-- Etapa 1: Integridade dos Dados -->
                            <div class="audit-step-item">
                                <div id="audit-step-1-icon" class="audit-step-icon icon-pending">1</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span data-i18n="playground.audit.s1.title">1. Canonicidade do Payload e Hash SHA-256</span>
                                        <span id="audit-step-1-status" class="card-category">Aguardando</span>
                                    </div>
                                    <div class="audit-step-desc" data-i18n="playground.audit.s1.desc">
                                        Reconstrói o payload normalizado em formato canônico e calcula o hash criptográfico:
                                    </div>
                                    <div id="audit-step-1-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Etapa 2: Carteira do Oraculo -->
                            <div class="audit-step-item">
                                <div id="audit-step-2-icon" class="audit-step-icon icon-pending">2</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span data-i18n="playground.audit.s2.title">2. Verificação da Assinatura secp256k1</span>
                                        <span id="audit-step-2-status" class="card-category">Aguardando</span>
                                    </div>
                                    <div class="audit-step-desc" data-i18n="playground.audit.s2.desc">
                                        Valida matematicamente a assinatura digital DER/ECDSA contra o hash canônico.
                                    </div>
                                    <div id="audit-step-2-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Etapa 3: Assinatura ECDSA secp256k1 -->
                            <div class="audit-step-item">
                                <div id="audit-step-3-icon" class="audit-step-icon icon-pending">3</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span data-i18n="playground.audit.s3.title">3. Recuperação de Endereço do Oráculo</span>
                                        <span id="audit-step-3-status" class="card-category">Aguardando</span>
                                    </div>
                                    <div class="audit-step-desc" data-i18n="playground.audit.s3.desc">
                                        Confirma que a chave recuperada corresponde exatamente à identidade pública do oráculo oficial:
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
            <h2 data-i18n="console.title">Console de Teste e Especificações de Integração</h2>
            <p data-i18n="console.desc">Experimente as requisições em tempo real ou copie os snippets para seus agentes autônomos.</p>

            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('console')" data-i18n="console.tab.interactive">⚡ Console Interativo</button>
                <button class="tab-btn" onclick="switchTab('smithery')" data-i18n="console.tab.smithery">📦 Smithery MCP (1 Clique)</button>
                <button class="tab-btn" onclick="switchTab('agentkit')" data-i18n="console.tab.agentkit">🪙 Coinbase AgentKit (M2M)</button>
                <button class="tab-btn" onclick="switchTab('python')" data-i18n="console.tab.python">🐍 Python SDK</button>
                <button class="tab-btn" onclick="switchTab('node')" data-i18n="console.tab.node">🟢 Node.js SDK</button>
                <button class="tab-btn" onclick="switchTab('curl')" data-i18n="console.tab.curl">💻 cURL CLI</button>
                <button class="tab-btn" onclick="switchTab('badges')" data-i18n="console.tab.badges">🛡️ Badges SVG</button>
            </div>

            <!-- Tab 1: Console Interativo -->
            <form id="tab-console" class="tab-content active" toolname="grid402_console" tooldescription="Console interativo de consulta e teste dos servicos Grid402 sob protocolo x402" onsubmit="event.preventDefault();">
                <div class="console-actions">
                    <button type="button" class="btn-sm" onclick="runConsole('summary')" data-i18n="console.act.summary">Resumo Gratuito (/status)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('forex')" data-i18n="console.act.forex">Câmbio (/api/v1/forex)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('crypto')" data-i18n="console.act.crypto">Cripto (/api/v1/crypto)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('dns')" data-i18n="console.act.dns">DNS (/api/v1/dns)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('ssl')" data-i18n="console.act.ssl">SSL (/api/v1/ssl)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('asn')" data-i18n="console.act.asn">ASN (/api/v1/asn)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('cep')" data-i18n="console.act.cep">CEP (/api/v1/cep)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('cnpj')" data-i18n="console.act.cnpj">CNPJ (/api/v1/cnpj)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('indicators')" data-i18n="console.act.indicators">Indicadores (/api/v1/indicators)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('holidays')" data-i18n="console.act.holidays">Feriados (/api/v1/holidays)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('workdays')" data-i18n="console.act.workdays">Dias Úteis (/api/v1/workdays)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('sanctions')" data-i18n="console.act.sanctions">Sanções CGU (/api/v1/sanctions)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('simples')" data-i18n="console.act.simples">Simples Nacional (/api/v1/simples)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('compliance')" data-i18n="console.act.compliance">Score 360 (/api/v1/compliance)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('lei')" data-i18n="console.act.lei" style="border-color: rgba(0, 240, 255, 0.4);">🌍 LEI Global (/api/v1/lei)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('ofac')" data-i18n="console.act.ofac" style="border-color: rgba(0, 240, 255, 0.4);">🛡️ OFAC e AML (/api/v1/ofac)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('notarize')" data-i18n="console.act.notarize" style="border-color: rgba(0, 240, 255, 0.4);">⏱️ Notarizar (/api/v1/notarize)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('global-markets')" data-i18n="console.act.global-markets" style="border-color: rgba(0, 240, 255, 0.4);">🌐 Bolsas e ECB (/api/v1/global-markets)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('pass')" data-i18n="console.act.pass" style="border-color: rgba(16, 185, 129, 0.5);">⚡ Session Pass (/api/v1/pass)</button>
                </div>
                <div class="input-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="text" id="tx-input" class="console-input" style="flex: 1; min-width: 260px;" placeholder="Cole seu Session Pass (pass_g402_...), hash de pagamento (0x...) ou assine via Web3" data-i18n-placeholder="console.input.placeholder">
                    <button type="button" class="btn-sm" onclick="runConsole('paid')" data-i18n="console.btn.send_payment">Enviar com X-Payment</button>
                    <button type="button" class="btn-sm btn-primary" id="btn-web3-pay" onclick="payWithWeb3Wallet()" data-i18n="console.btn.web3_pay">⚡ Pagar com Carteira Web3 (1 Clique)</button>
                    <button type="button" class="btn-sm btn-outline" id="btn-console-smart-wallet" onclick="payConsoleWithCoinbaseSmartWallet()" style="border-color: #0052ff; background: rgba(0, 82, 255, 0.14); color: #fff; font-weight: 600;" data-i18n="console.btn.smart_wallet">🔑 Passkey / Biometria (Smart Wallet)</button>
                    <button type="button" class="btn-sm btn-outline" onclick="openWeb3Modal()" title="Abrir QR Code e instruções de pagamento" data-i18n="console.btn.web3_qr">📱 QR Code / Celular</button>
                    <button type="button" class="btn-sm btn-outline" onclick="runSandboxSimulation()" title="Simular pagamento gratuitamente no Modo Sandbox" data-i18n="console.btn.sandbox_demo">🧪 Simular Sandbox (demo)</button>
                </div>

                <!-- Card de Destaque da Amostra (Teaser Preview) -->
                <div id="console-teaser-card" style="display: none; background: rgba(0, 240, 255, 0.05); border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 10px; padding: 1.25rem; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: var(--primary); color: #030712; font-weight: 700; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px;" data-i18n="console.teaser.badge">AMOSTRA GRATUITA ENCONTRADA</span>
                            <span id="teaser-title" style="font-weight: 700; color: #fff; font-size: 0.95rem;"></span>
                        </div>
                        <span id="teaser-badge-price" style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--accent); font-weight: 600;"></span>
                    </div>
                    <div id="teaser-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 0.85rem; margin-bottom: 1rem;">
                    </div>
                    <div style="background: rgba(0, 0, 0, 0.25); border-radius: 6px; padding: 0.6rem 0.8rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                        <strong data-i18n="console.teaser.locked">🔒 Campos protegidos que serão liberados no HTTP 200:</strong>
                        <div id="teaser-locked-fields" style="margin-top: 4px; color: #94a3b8; font-family: var(--font-mono); font-size: 0.75rem;"></div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <button class="btn btn-primary" onclick="payWithWeb3Wallet()" style="font-size: 0.85rem; padding: 0.5rem 1rem;" data-i18n="console.teaser.unlock_btn">
                            ⚡ Desbloquear Dados Completos com Web3
                        </button>
                        <button class="btn btn-outline" onclick="payConsoleWithCoinbaseSmartWallet()" style="font-size: 0.85rem; padding: 0.5rem 0.95rem; border-color: #0052ff; background: rgba(0, 82, 255, 0.14); color: #fff; font-weight: 600;" data-i18n="console.btn.smart_wallet">
                            🔑 Passkey / Biometria (Smart Wallet)
                        </button>
                        <button class="btn btn-outline" onclick="openWeb3Modal()" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;" data-i18n="console.btn.web3_qr">
                            📱 Pagar com QR Code / Celular
                        </button>
                        <button class="btn btn-outline" onclick="runSandboxSimulation()" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;" data-i18n="console.btn.sandbox_demo">
                            🧪 Testar via Sandbox (demo)
                        </button>
                        <button class="btn btn-outline" onclick="document.getElementById('console-teaser-card').style.display='none'" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;" data-i18n="console.teaser.close_btn">
                            ✕ Fechar Amostra
                        </button>
                    </div>
                </div>
                <div class="response-header">
                    <span id="console-status-text" data-i18n="console.status.idle">Selecione uma das ações acima para testar o retorno da API.</span>
                    <span id="console-latency-badge" style="display:none;" class="card-category"></span>
                </div>
                <pre id="console-output" class="code-block" data-i18n="console.output.placeholder">// Resposta da API aparecerá aqui...</pre>
            </form>

            <!-- Tab Smithery MCP -->
            <div id="tab-smithery" class="tab-content">
                <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 0.75rem;">
                        <span style="font-weight: 700; color: #fff; font-size: 1.05rem;" data-i18n="console.smithery.title">📦 Instalação em 1 Clique via Smithery.ai</span>
                        <span class="card-category">Claude Desktop • Cursor • MCP Stdout</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5;" data-i18n="console.smithery.desc">
                        O Grid402 está catalogado no registro global da Smithery.ai. Você pode registrar instantaneamente o servidor de ferramentas no seu Claude Desktop ou Cursor sem precisar editar arquivos de configuração manualmente.
                    </p>
                    
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.smithery.cmd_claude">Comando de Instalação no Claude Desktop:</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">npx -y @smithery/cli install grid402-mcp --client claude</pre>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.smithery.cmd_cursor">Comando de Instalação no Cursor:</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">npx -y @smithery/cli install grid402-mcp --client cursor</pre>
                    </div>

                    <div style="background: rgba(0, 0, 0, 0.3); border-radius: 6px; padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);" data-i18n-html="console.smithery.params">
                        <strong style="color: #fff;">💡 Parâmetros Suportados no smithery.yaml:</strong>
                        <ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;">
                            <li><code>sandbox: true</code> — Ativa o Modo Sandbox gratuito (30 consultas por hora por IP) para testes sem necessidade de carteira.</li>
                            <li><code>privateKey: 0x...</code> — Chave privada EVM opcional para auto-liquidação em USDC na Base (0.0005 a 0.0020 USDC por chamada).</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tab Coinbase AgentKit -->
            <div id="tab-agentkit" class="tab-content">
                <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 0.75rem;">
                        <span style="font-weight: 700; color: #fff; font-size: 1.05rem;" data-i18n="console.agentkit.title">🪙 Integração Nativa Coinbase AgentKit (CDP)</span>
                        <span class="card-category">M2M Auto-Settlement • LangChain • CrewAI</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5;" data-i18n="console.agentkit.desc">
                        Permita que agentes de IA com carteiras CDP na Base consultem dados públicos e utilitários de rede pagando tarifas x402 de forma 100% autônoma, sem interrupção humana. O provedor de ações intercepta o desafio 402, transfere o USDC e reenvia a requisição de forma transparente.
                    </p>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.agentkit.example_ts">Exemplo em TypeScript (@coinbase/agentkit):</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">import { AgentKit, CdpWalletProvider } from "@coinbase/agentkit";
import { Grid402ActionProvider } from "./integrations/agentkit/grid402_action_provider";

// Inicializa a carteira CDP na Base
const walletProvider = await CdpWalletProvider.configureWithWallet();

// Conecta o Grid402 com auto-liquidacao de micropagamentos
const agentKit = await AgentKit.from({
  walletProvider,
  actionProviders: [new Grid402ActionProvider()]
});</pre>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;" data-i18n="console.agentkit.example_py">Exemplo em Python (cdp-agentkit-core):</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">from cdp_agentkit_core.actions import CdpActionProvider
from integrations.agentkit.grid402_action_provider import Grid402ActionProvider

# Conecta o provedor com suporte a sandbox ou liquidacao on-chain automatica
grid402_actions = Grid402ActionProvider(sandbox=False)</pre>
                    </div>

                    <div style="background: rgba(0, 0, 0, 0.3); border-radius: 6px; padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);" data-i18n-html="console.agentkit.docs">
                        <strong style="color: #fff;">📂 Documentação Completa e Código-Fonte:</strong>
                        <p style="margin: 0.4rem 0 0 0;">Consulte o diretório <code>integrations/agentkit/</code> no repositório para implementações prontas com LangChain, CrewAI e guias de configuração.</p>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Python -->
            <div id="tab-python" class="tab-content">
                <div class="code-block" data-i18n="console.python.code">from grid402 import Grid402Client

client = Grid402Client()

# 1. Resumo gratuito do hub
summary = client.get_summary()
print("Serviços operantes:", summary["status"])

# 2. Câmbio em tempo real
forex = client.get_forex()
print("USD/BRL:", forex["rates"]["USD"]["bid"])

# 3. DNS Lookup sob demanda
dns_records = client.get_dns("github.com")
print("Registros A:", dns_records["records"]["A"])

# 4. Modo Sandbox gratuito para testes locais e CI/CD
# Passe o cabeçalho 'X-Payment: demo' nas requisições sem gastar USDC</div>
            </div>

            <!-- Tab 3: Node.js -->
            <div id="tab-node" class="tab-content">
                <div class="code-block" data-i18n="console.node.code">import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// 1. Consulta gratuita
const summary = await client.getSummary();
console.log('Status do hub:', summary.status);

// 2. Inspetor SSL
const ssl = await client.getSsl('base.org');
console.log('Expira em dias:', ssl.certificate.days_remaining);

// 3. Modo Sandbox gratuito para testes automatizados:
// Envie headers: { 'X-Payment': 'demo' } para simular sem custo</div>
            </div>

            <!-- Tab 4: cURL -->
            <div id="tab-curl" class="tab-content">
                <div class="code-block" data-i18n="console.curl.code"># 1. Resumo gratuito
curl -i https://www.grid402.online/status

# 2. Desafio HTTP 402 com Amostra Cadastral
curl -i "https://www.grid402.online/api/v1/cnpj?cnpj=00000000000191"

# 3. Requisição liquidada na Base Mainnet
curl "https://www.grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: 0x&lt;tx_hash_na_base&gt;"

# 4. Modo Sandbox gratuito para testes e CI/CD (30 req/hora por IP)
curl "https://www.grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: demo"</div>
            </div>

            <!-- Tab 5: Badges -->
            <div id="tab-badges" class="tab-content">
                <p style="color: var(--text-muted); margin-bottom: 1rem;" data-i18n="console.badges.intro">
                    Badges em SVG dinâmicos para embeddar no README do seu repositório no GitHub:
                </p>
                <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600;" data-i18n="console.badges.label">Exemplo de Badge em Tempo Real:</div>
                        <img id="badge-preview-img" src="/badge/system.svg" alt="Badge Preview" style="height: 22px; display: block;" onerror="this.src='/badge.php?target=system'">
                    </div>
                    <button class="btn-sm" onclick="copyBadgeMarkdown()" data-i18n="console.badges.btn.copy">📋 Copiar Markdown</button>
                </div>
                <pre id="badge-markdown-code" class="code-block">[![Grid402 Hub](https://www.grid402.online/badge/system.svg)](https://www.grid402.online)</pre>
            </div>
        </section>

        <footer>
            <div>
                <div data-i18n="footer.hub">Grid402 // Open Data e Utility Hub • Base Mainnet</div>
                <div style="margin-top: 4px; font-size: 0.78rem; color: #64748B;" id="footer-copyright-text">
                    © <?php echo date('Y'); ?> Grid402 Network Intelligence. Todos os direitos reservados.
                </div>
            </div>
            <div>
                <a href="/about" data-i18n="nav.about">About</a> • 
                <a href="/contact" data-i18n="nav.contact">Contact</a> • 
                <a href="/terms" onclick="openLegalModal('terms'); return false;" data-i18n="nav.terms">Terms of Use</a> • 
                <a href="/privacy" onclick="openLegalModal('privacy'); return false;" data-i18n="nav.privacy">Privacy (LGPD)</a> • 
                <a href="/openapi.json" target="_blank">OpenAPI 3.1</a> • 
                <a href="/llms.txt" target="_blank">llms.txt</a> • 
                <span id="footer-fee-text">Taxa: US$ <?php echo GRID402_PRICE_DISPLAY; ?> / consulta</span>
            </div>
            <div class="footer-legal">
                <p><strong data-i18n="footer.ip.title">Direitos Autorais e Propriedade Intelectual Reservados:</strong> <span data-i18n="footer.ip.desc">© <?php echo date('Y'); ?> Grid402 Network Intelligence. Todos os direitos reservados (Lei nº 9.609/1998 e Lei nº 9.610/1998). O software da plataforma, motor de liquidação HTTP 402, arquitetura de Session Pass, marca, design e documentações são de propriedade exclusiva do Grid402, sendo vedada a reprodução ou redistribuição não autorizada. Os dados técnicos consultados provêm de registros públicos governamentais abertos, licenças CC0 e protocolos livres da internet (DNS, BGP/ASN, TLS/SSL), sobre os quais não reivindicamos exclusividade proprietária.</span></p>
                <p style="margin-top: 0.5rem;"><strong data-i18n="footer.disclaimer.title">Isenção de Responsabilidade e Dados Públicos:</strong> <span data-i18n="footer.disclaimer.desc">O Grid402 é um hub utilitário de acesso a dados de domínio público e protocolos abertos de rede fornecido 'NO ESTADO EM QUE SE ENCONTRA' (AS-IS). Não tratamos dados pessoais sensíveis (PII) e operamos em estrita observância à Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e ao Marco Civil da Internet (Lei nº 12.965/2014).</span></p>
            </div>
        </footer>

        <!-- Modal Jurídico -->
        <div id="legal-modal-overlay" class="modal-overlay" onclick="handleOverlayClick(event)">
            <div class="modal-card">
                <div class="modal-header">
                    <span class="modal-title" id="modal-title-text" data-i18n="modal.terms.title">Termos de Uso</span>
                    <button class="modal-close" onclick="closeLegalModal()">✕</button>
                </div>
                <div class="modal-tabs">
                    <button id="modal-tab-terms-btn" class="modal-tab-btn active" onclick="switchLegalModal('terms')" data-i18n="modal.terms.tab">Termos de Uso</button>
                    <button id="modal-tab-privacy-btn" class="modal-tab-btn" onclick="switchLegalModal('privacy')" data-i18n="modal.privacy.tab">Privacidade e LGPD</button>
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
                        <span class="modal-title" data-i18n="modal.web3.title">Pagamento Web3 (Base Mainnet)</span>
                    </div>
                    <button class="modal-close" onclick="closeWeb3Modal()">✕</button>
                </div>
                <div class="modal-body" style="padding: 1.25rem 1.5rem;">
                    <!-- Aviso se nenhuma carteira EVM foi detectada -->
                    <div id="web3-no-wallet-banner" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: #fde68a; display: flex; align-items: flex-start; gap: 10px;">
                        <span style="font-size: 1.2rem; line-height: 1;">ℹ️</span>
                        <div>
                            <strong data-i18n="modal.web3.no_wallet.title">Nenhuma extensão Web3 ativa detectada nesta aba</strong><br>
                            <span data-i18n="modal.web3.no_wallet.desc">Para pagar com 1 clique direto na página, é necessário ter a extensão MetaMask, Rabby ou Coinbase Wallet instalada e ativa. Ou pague pelo celular via QR Code abaixo.</span>
                        </div>
                    </div>

                    <!-- Duas opções visuais -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <!-- Coluna 1: Celular / QR Code -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.5rem; font-size: 0.9rem;" data-i18n="modal.web3.col1.title">📱 Pagar pelo Celular</div>
                                <div style="background: #fff; padding: 8px; border-radius: 8px; display: inline-block; margin-bottom: 0.6rem;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?data=0xa31c31069c43ac1c0075a93c325b0dcd7744356c" alt="QR Code Carteira Oraculo" style="display: block; width: 130px; height: 130px;" />
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;" data-i18n-html="modal.web3.col1.desc">
                                    Escaneie pelo app da MetaMask, Rainbow ou Coinbase Wallet e envie <strong><span id="web3-modal-price">0.0005</span> USDC</strong> na rede <strong>Base</strong>.
                                </div>
                            </div>
                        </div>

                        <!-- Coluna 2: Coinbase Smart Wallet (Passkey) & Extensão no PC -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.5rem; font-size: 0.9rem;" data-i18n="modal.web3.col2.title">🔑 Sem Extensão? Use Passkey</div>
                                <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.85rem; line-height: 1.45;" data-i18n="modal.web3.col2.desc">
                                    Crie ou conecte uma <strong>Coinbase Smart Wallet</strong> em 5 segundos usando biometria/Passkey do celular ou computador, sem instalar extensão nem anotar seed phrase.
                                </p>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <button type="button" id="btn-modal-smart-wallet" class="btn btn-primary" onclick="payModalWithCoinbaseSmartWallet()" style="justify-content: center; font-size: 0.83rem; padding: 0.6rem 0.8rem; background: #0052ff; color: #fff; border: none;" data-i18n="modal.web3.col2.btn.smart_wallet">
                                    🔑 Conectar via Passkey (Smart Wallet)
                                </button>
                                <a href="https://metamask.io/download/" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="justify-content: center; font-size: 0.8rem; padding: 0.5rem 0.8rem; text-decoration: none;" data-i18n="modal.web3.col2.btn.install">
                                    🦊 Ou Instalar Extensão MetaMask
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Dados Técnicos da Transferência -->
                    <div style="background: #030712; border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1rem; font-size: 0.8rem; font-family: var(--font-mono); margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);" data-i18n="modal.web3.network">Rede:</span>
                            <span style="color: var(--accent); font-weight: 600;">Base Mainnet (Chain ID 8453)</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);" data-i18n="modal.web3.token">Token:</span>
                            <span style="color: #fff;">USDC</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);" data-i18n="modal.web3.amount">Valor da Consulta:</span>
                            <span style="color: var(--primary); font-weight: 700;"><span id="web3-modal-price-detail">0.0005</span> USDC</span>
                        </div>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(255, 255, 255, 0.1);">
                            <div style="color: var(--text-muted); margin-bottom: 4px; font-size: 0.75rem;" data-i18n="modal.web3.recipient">Endereço de Destino (Carteira do Oráculo):</div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" id="web3-wallet-dest-input" readonly value="<?php echo GRID402_WALLET_ADDRESS; ?>" style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); color: #fff; padding: 6px 8px; border-radius: 6px; font-size: 0.75rem; font-family: var(--font-mono);" />
                                <button type="button" class="btn-sm" onclick="copyOracleAddress()" id="btn-copy-oracle" style="font-size: 0.75rem; padding: 6px 10px; white-space: nowrap;">📋 Copiar</button>
                            </div>
                        </div>
                    </div>

                    <!-- Inserção rápida de Hash para validação imediata -->
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #fff; margin-bottom: 6px;" data-i18n="modal.web3.confirm.desc">
                            Já enviou? Cole o hash da transação para desbloquear os dados:
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="web3-hash-direct-input" class="console-input" style="flex: 1; font-size: 0.8rem;" placeholder="0x... (Hash da transação gerado na Base)" />
                            <button class="btn btn-primary" onclick="submitModalPaymentHash()" style="font-size: 0.8rem; padding: 0.6rem 1rem; white-space: nowrap;" data-i18n="modal.web3.confirm.btn">
                                ⚡ Desbloquear Consulta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao Sobre o Projeto -->
        <section id="about" class="info-section">
            <h2 data-i18n="about.title">Sobre o Grid402</h2>
            <p data-i18n-html="about.p1">O <strong>Grid402</strong> e uma infraestrutura pública de oraculo de dados e utilitarios tecnicos de rede desenhada especificamente para a Internet dos Agentes (Agentic Web) e sistemas autônomos Machine-to-Machine (M2M).</p>
            <p data-i18n-html="about.p2">Tradicionalmente, consultar dados oficiais de empresas, enderecamento postal ou status de infraestrutura exige cadastro previo, cartoes de credito internacionais, aprovacao de tokens e chaves estaticas de API com risco de vazamento. O Grid402 elimina toda a friccao burocratica adotando o padrao nativo da web <strong>HTTP 402 Payment Required</strong> com liquidacao instantanea de micropagamentos em USDC na rede Base Mainnet.</p>
            <p data-i18n-html="about.p3">Todo o ecossistema opera de maneira estritamente Zero-DB e Stateless, processando apenas informacoes de dominio publico com transparencia computacional, propriedade intelectual e arquitetura protegidas por direitos autorais reservados (Leis nº 9.609/1998 e nº 9.610/1998) e total conformidade com a legislacao nacional e internacional (LGPD, GDPR e Marco Civil da Internet).</p>
        </section>

        <!-- Secao de Contato e Canais Oficiais -->
        <section id="contact" class="info-section">
            <h2 data-i18n="contact.title">Contato e Canais Oficiais</h2>
            <p data-i18n="contact.subtitle">Disponibilizamos canais diretos de suporte tecnico, integracao para desenvolvedores de IA e governanca de dados:</p>
            <div class="contact-grid">
                <div class="contact-card">
                    <strong data-i18n="contact.support">Suporte Tecnico e Geral</strong>
                    <a href="mailto:contato@grid402.online">contato@grid402.online</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.dpo">Encarregado de Dados (DPO)</strong>
                    <a href="mailto:contato@grid402.online">contato@grid402.online</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.phone">Central Telefonica</strong>
                    <a href="tel:+5511958325848">+55 11 95832-5848</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.github">Codigo Fonte no GitHub</strong>
                    <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.npm">Pacote npm (Node.js)</strong>
                    <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.pypi">Pacote PyPI (Python)</strong>
                    <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.mcp">Servidor MCP no npm</strong>
                    <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a>
                </div>
                <div class="contact-card">
                    <strong data-i18n="contact.oracle">Carteira Oraculo na Base</strong>
                    <a href="https://basescan.org/address/0xa31c31069c43ac1c0075a93c325b0dcd7744356c" target="_blank" rel="noopener noreferrer">0xa31c...356c</a>
                </div>
            </div>
        </section>

        <!-- Rodapé Semântico -->
        <footer>
            <div class="footer-links">
                <a href="/agents" style="color: var(--primary); font-weight: 600;" data-i18n="nav.agents">Agentes IA</a>
                <a href="/catalog" style="color: var(--primary); font-weight: 600;">Catálogo</a>
                <a href="/trust" style="color: var(--primary); font-weight: 600;">Trust Center</a>
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
                © <?php echo date('Y'); ?> Grid402 Network Intelligence. Todos os direitos reservados. Protegido pela Lei nº 9.609/1998 e Lei nº 9.610/1998.
            </div>
        </footer>
    </div>

    <script>
        // ==========================================
        // Grid402: Playground e Verificador de Oraculo
        // ==========================================
        const playgroundPresets = {
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
                    { key: 'address', label: 'Carteira EVM (0x...)', value: '0xa31c31069c43ac1c0075a93c325b0dcd7744356c' },
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
            pass: {
                name: 'Session Pass (100 reqs)',
                icon: '⚡',
                endpoint: '/api/v1/pass',
                desc: 'Emissao de passe pre-pago assinado para chamadas M2M em <15ms sem aguardar bloco.',
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

                const nameWrap = document.createElement('div');
                nameWrap.className = 'preset-btn-name';
                nameWrap.innerHTML = '<span>' + p.icon + '</span><span>' + p.name + '</span>';

                const routeWrap = document.createElement('div');
                routeWrap.className = 'preset-btn-route';
                routeWrap.textContent = p.endpoint;

                btn.appendChild(nameWrap);
                btn.appendChild(routeWrap);
                bar.appendChild(btn);
            });

            selectPlaygroundPreset('workdays');
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
            ['sandbox', 'challenge', 'manual'].forEach(m => {
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
            const colTitle = document.getElementById('playground-col-title');
            const runBtn = document.getElementById('btn-run-query');

            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');
            if (mode === 'manual') {
                if (presetControls) presetControls.style.display = 'none';
                if (manualControls) manualControls.style.display = 'flex';
                if (colTitle) colTitle.textContent = isEn ? 'Manual JSON Audit' : 'Auditoria Manual de JSON';
                if (runBtn) runBtn.textContent = isEn ? '🔍 Audit JSON' : '🔍 Auditar JSON';
            } else {
                if (presetControls) presetControls.style.display = 'block';
                if (manualControls) manualControls.style.display = 'none';
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

            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');
            if (unlockCard) unlockCard.style.display = 'none';
            if (statusBadge) statusBadge.textContent = isEn ? 'Sending...' : 'Enviando...';
            if (latencyTag) latencyTag.style.display = 'none';
            if (viewer) viewer.textContent = isEn ? '// Awaiting response from Grid402 hub...' : '// Aguardando resposta do hub Grid402...';
            if (runBtn) runBtn.disabled = true;

            const headers = { 'Accept': 'application/json' };
            if (currentPlaygroundMode === 'sandbox') {
                headers['X-Payment'] = 'demo';
            }

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
                    if (statusBadge) statusBadge.textContent = 'HTTP 200 OK';
                    if (data && data._oracle_proof) {
                        auditOracleProof(data);
                    } else {
                        renderNonProofState(isEn ? 'No _oracle_proof block in response.' : 'Sem bloco _oracle_proof na resposta.');
                    }
                } else if (response.status === 402 || response.status === 429) {
                    if (statusBadge) {
                        statusBadge.textContent = response.status === 429
                            ? (isEn ? 'HTTP 429 Sandbox Quota Exceeded' : 'HTTP 429 Quota Sandbox Excedida')
                            : 'HTTP 402 Payment Required';
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
                    const addr = result.oracle_address || '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
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
            const price = (data && data.price_usdc) ? data.price_usdc : '0.0005';

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

        let coinbaseSmartWalletProvider = null;

        async function getCoinbaseSmartWalletProvider() {
            if (coinbaseSmartWalletProvider) {
                return coinbaseSmartWalletProvider;
            }
            if (typeof window !== 'undefined' && window.ethereum) {
                return window.ethereum;
            }
            throw new Error('Nenhuma carteira Web3 detectada. Instale uma extensão como MetaMask ou Coinbase Wallet.');
        }

        async function payPlaygroundWithWeb3(customProvider = null, customBtnId = null) {
            const btn = document.getElementById(customBtnId || 'btn-playground-web3-pay');
            const statusBadge = document.getElementById('playground-http-status');
            const viewer = document.getElementById('playground-json-viewer');

            let provider = customProvider || window.ethereum;
            if (!provider) {
                try {
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = 'Abrindo Passkey / Smart Wallet...';
                    }
                    provider = await getCoinbaseSmartWalletProvider();
                } catch (e) {
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

                const accounts = await provider.request({ method: 'eth_requestAccounts' });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta conectada.');
                }
                const userAddress = accounts[0];

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

                let amountRaw = 500;
                let priceUsdc = '0.0005';
                if (currentPlaygroundPreset === 'pass') {
                    amountRaw = 50000;
                    priceUsdc = '0.0500';
                } else if (currentPlaygroundPreset === 'compliance') {
                    amountRaw = 3000;
                    priceUsdc = '0.0030';
                } else if (currentPlaygroundPreset === 'sanctions' || currentPlaygroundPreset === 'simples') {
                    amountRaw = 1000;
                    priceUsdc = '0.0010';
                } else if (currentPlaygroundPreset === 'cnpj' || currentPlaygroundPreset === 'lei' || currentPlaygroundPreset === 'ofac') {
                    amountRaw = 2000;
                    priceUsdc = '0.0020';
                }

                const recipient = '<?php echo GRID402_WALLET_ADDRESS; ?>'.toLowerCase().replace('0x', '');
                const usdcContract = '<?php echo GRID402_USDC_CONTRACT; ?>';
                const recipientPadded = recipient.padStart(64, '0');
                const amountHex = amountRaw.toString(16).padStart(64, '0');
                const txData = '0xa9059cbb' + recipientPadded + amountHex;

                if (btn) btn.textContent = 'Assine ' + priceUsdc + ' USDC na carteira...';
                const txHash = await provider.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: userAddress,
                        to: usdcContract,
                        data: txData,
                    }],
                });

                if (statusBadge) statusBadge.textContent = 'Aguardando Bloco Base...';
                if (viewer) viewer.textContent = '// Transacao enviada na Base Mainnet!\n// TxHash: ' + txHash + '\n// Aguardando 3.5s para confirmacao e liberacao da prova criptografica...';
                if (btn) btn.textContent = 'Validando recibo on-chain...';

                await new Promise(r => setTimeout(r, 3500));

                const url = buildPlaygroundUrl();
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Payment': txHash
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
                if (statusBadge) statusBadge.textContent = 'Pagamento Cancelado';
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        async function payPlaygroundWithCoinbaseSmartWallet() {
            const btn = document.getElementById('btn-playground-smart-wallet');
            const origText = btn ? btn.textContent : '🔑 Passkey / Biometria (Coinbase Smart Wallet)';
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Carregando Passkey SDK...';
                }
                const smartProvider = await getCoinbaseSmartWalletProvider();
                await payPlaygroundWithWeb3(smartProvider, 'btn-playground-smart-wallet');
            } catch (err) {
                const statusBadge = document.getElementById('playground-http-status');
                if (statusBadge) statusBadge.textContent = 'Erro ao iniciar Smart Wallet';
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
                const price = (data && data.price_usdc) ? data.price_usdc : '0.0005';
                const dest = (data && data.payment_address) ? data.payment_address : '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
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
                    "oracle_address": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c",
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

            switch (action) {
                case 'forex': url = '/api/v1/forex'; break;
                case 'crypto': url = '/api/v1/crypto'; break;
                case 'dns': url = '/api/v1/dns?domain=github.com'; break;
                case 'ssl': url = '/api/v1/ssl?host=base.org'; break;
                case 'asn': url = '/api/v1/asn?ip=1.1.1.1'; break;
                case 'cep': url = '/api/v1/cep?code=01310100'; break;
                case 'cnpj': url = '/api/v1/cnpj?cnpj=00000000000191'; break;
                case 'sanctions': url = '/api/v1/sanctions?cnpj=00000000000191'; break;
                case 'simples': url = '/api/v1/simples?cnpj=00000000000191'; break;
                case 'compliance': url = '/api/v1/compliance?cnpj=00000000000191'; break;
                case 'indicators': url = '/api/v1/indicators'; break;
                case 'holidays': url = '/api/v1/holidays?year=2026'; break;
                case 'workdays': url = '/api/v1/workdays?start_date=2026-12-23' + String.fromCharCode(38) + 'end_date=2026-12-28'; break;
                case 'lei': url = '/api/v1/lei?lei=5493006MHB84DD0ZWV18'; break;
                case 'ofac': url = '/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16'; break;
                case 'notarize': url = '/api/v1/notarize?content=Grid402-M2M-Contract-v1'; break;
                case 'global-markets': url = '/api/v1/global-markets?market=US' + String.fromCharCode(38) + 'base=USD'; break;
                case 'pass': url = '/api/v1/pass'; break;
                case 'paid':
                    const tx = document.getElementById('tx-input').value.trim();
                    if (!tx) {
                        out.textContent = isEn ? 'Please paste the Base transaction hash or pass_g402_... token above.' : 'Por favor, insira o hash da transação na Base no campo acima.';
                        return;
                    }
                    headers['X-Payment'] = tx;
                    if (currentServiceAction === 'cnpj') url = '/api/v1/cnpj?cnpj=00000000000191';
                    else if (currentServiceAction === 'sanctions') url = '/api/v1/sanctions?cnpj=00000000000191';
                    else if (currentServiceAction === 'simples') url = '/api/v1/simples?cnpj=00000000000191';
                    else if (currentServiceAction === 'compliance') url = '/api/v1/compliance?cnpj=00000000000191';
                    else if (currentServiceAction === 'lei') url = '/api/v1/lei?lei=5493006MHB84DD0ZWV18';
                    else if (currentServiceAction === 'ofac') url = '/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16';
                    else if (currentServiceAction === 'notarize') url = '/api/v1/notarize?content=Grid402-M2M-Contract-v1';
                    else if (currentServiceAction === 'global-markets') url = '/api/v1/global-markets?market=US' + String.fromCharCode(38) + 'base=USD';
                    else if (currentServiceAction === 'pass') url = '/api/v1/pass';
                    else if (currentServiceAction === 'ssl') url = '/api/v1/ssl?host=base.org';
                    else if (currentServiceAction === 'asn') url = '/api/v1/asn?ip=1.1.1.1';
                    else if (currentServiceAction === 'cep') url = '/api/v1/cep?code=01310100';
                    else if (currentServiceAction === 'indicators') url = '/api/v1/indicators';
                    else if (currentServiceAction === 'holidays') url = '/api/v1/holidays?year=2026';
                    else if (currentServiceAction === 'workdays') url = '/api/v1/workdays?start_date=2026-12-23' + String.fromCharCode(38) + 'end_date=2026-12-28';
                    else if (currentServiceAction === 'forex') url = '/api/v1/forex';
                    else if (currentServiceAction === 'crypto') url = '/api/v1/crypto';
                    else url = '/api/v1/dns?domain=github.com';
                    break;
                default: url = '/status'; break;
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
                    teaserPrice.textContent = `${json.price_usdc || '0.0005'} USDC • Base`;

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

            let provider = customProvider || window.ethereum;
            if (!provider) {
                try {
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = 'Abrindo Passkey / Smart Wallet...';
                    }
                    provider = await getCoinbaseSmartWalletProvider();
                } catch (e) {
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
                const accounts = await provider.request({ method: 'eth_requestAccounts' });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta foi conectada.');
                }
                const userAddress = accounts[0];

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

                // 3. Monta a transação ERC-20 transfer(to, amount)
                let amountRaw = 500;
                let priceUsdc = '0.0005';
                if (currentServiceAction === 'pass') {
                    amountRaw = 50000;
                    priceUsdc = '0.0500';
                } else if (currentServiceAction === 'compliance') {
                    amountRaw = 3000;
                    priceUsdc = '0.0030';
                } else if (currentServiceAction === 'sanctions' || currentServiceAction === 'simples') {
                    amountRaw = 1000;
                    priceUsdc = '0.0010';
                } else if (currentServiceAction === 'cnpj' || currentServiceAction === 'lei' || currentServiceAction === 'ofac') {
                    amountRaw = 2000;
                    priceUsdc = '0.0020';
                }
                const recipient = '<?php echo GRID402_WALLET_ADDRESS; ?>'.toLowerCase().replace('0x', '');
                const usdcContract = '<?php echo GRID402_USDC_CONTRACT; ?>';

                // Function selector: transfer(address,uint256) -> 0xa9059cbb
                const recipientPadded = recipient.padStart(64, '0');
                const amountHex = amountRaw.toString(16).padStart(64, '0');
                const data = '0xa9059cbb' + recipientPadded + amountHex;

                if (btn) btn.textContent = `Aguardando assinatura de ${priceUsdc} USDC...`;
                out.textContent = `Solicitando aprovação na carteira para transferência de ${priceUsdc} USDC na Base para o oráculo...\nCarteira destino: 0x${recipient}`;

                const txHash = await provider.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: userAddress,
                        to: usdcContract,
                        data: data,
                    }],
                });

                document.getElementById('tx-input').value = txHash;
                statusText.textContent = `Transação enviada: ${txHash.substring(0, 10)}... Aguardando confirmação no bloco Base...`;
                out.textContent = `Transação enviada com sucesso na Base Mainnet!\nTx Hash: ${txHash}\nLink BaseScan: https://basescan.org/tx/${txHash}\n\nAguardando 3.5 segundos para propagação do recibo on-chain...`;

                if (btn) btn.textContent = 'Desbloqueando dados...';
                await new Promise(r => setTimeout(r, 3500));

                await runConsole('paid');
            } catch (err) {
                out.textContent = `Falha na transação Web3: ${err.message || err}`;
                statusText.textContent = 'Operação cancelada ou falhou';
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        async function payConsoleWithCoinbaseSmartWallet() {
            const btn = document.getElementById('btn-console-smart-wallet');
            const origText = btn ? btn.textContent : '🔑 Passkey / Biometria (Smart Wallet)';
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Carregando Passkey SDK...';
                }
                const smartProvider = await getCoinbaseSmartWalletProvider();
                await payWithWeb3Wallet(smartProvider, 'btn-console-smart-wallet');
            } catch (err) {
                const statusText = document.getElementById('console-status-text');
                if (statusText) statusText.textContent = 'Erro ao iniciar Smart Wallet';
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        async function payModalWithCoinbaseSmartWallet() {
            const btn = document.getElementById('btn-modal-smart-wallet');
            const origText = btn ? btn.textContent : '🔑 Conectar via Passkey (Smart Wallet)';
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Abrindo Passkey / Biometria...';
                }
                const smartProvider = await getCoinbaseSmartWalletProvider();
                closeWeb3Modal();
                await payPlaygroundWithWeb3(smartProvider, 'btn-playground-smart-wallet');
            } catch (err) {
                alert('Erro ao conectar Coinbase Smart Wallet: ' + (err.message || err));
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

        // Web3 One-Click Top-Up (Session Passes)
        async function startWeb3Topup(amountUsdc, tierName, customProvider = null) {
            const btnId = 'btn-topup-' + tierName;
            const btn = document.getElementById(btnId);
            const origText = btn ? btn.textContent : 'Comprar';

            const statusBox = document.getElementById('topup-status-box');
            const statusTitle = document.getElementById('topup-status-title');
            const statusMsg = document.getElementById('topup-status-msg');
            const successCard = document.getElementById('topup-success-card');

            if (statusBox) statusBox.style.display = 'block';
            if (successCard) successCard.style.display = 'none';

            let provider = customProvider || window.ethereum;
            if (!provider) {
                if (statusTitle) statusTitle.textContent = 'Carteira Web3 não detectada';
                if (statusMsg) {
                    statusMsg.innerHTML = 'Nenhuma extensão de carteira (MetaMask, Coinbase Wallet, Rabby) detectada neste navegador.<br><span style="color: #00F0FF; font-weight: 600;">Sem problemas!</span> Você pode transferir USDC diretamente pelo seu app (ou exchange) e ativar o passe colando o Hash na caixa logo abaixo.';
                }
                const manualBox = document.getElementById('manual-topup-box');
                if (manualBox) manualBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Conectando...';
                }
                if (statusTitle) statusTitle.textContent = '1/3 Conectando carteira na Base...';
                if (statusMsg) statusMsg.textContent = 'Solicitando conexão com sua carteira Web3...';

                // 1. Conexão de contas
                const accounts = await provider.request({ method: 'eth_requestAccounts' });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta foi conectada.');
                }
                const userAddress = accounts[0];

                // 2. Garantir rede Base Mainnet (0x2105 = 8453)
                if (statusTitle) statusTitle.textContent = '2/3 Verificando rede Base Mainnet...';
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

                // 3. Monta a transação ERC-20 transfer(to, amount)
                const amountRaw = Math.round(Number(amountUsdc) * 1e6);
                const recipient = '<?php echo GRID402_WALLET_ADDRESS; ?>'.toLowerCase().replace('0x', '');
                const usdcContract = '<?php echo GRID402_USDC_CONTRACT; ?>';

                const recipientPadded = recipient.padStart(64, '0');
                const amountHex = amountRaw.toString(16).padStart(64, '0');
                const data = '0xa9059cbb' + recipientPadded + amountHex;

                if (btn) btn.textContent = 'Assinando ' + amountUsdc + ' USDC...';
                if (statusTitle) statusTitle.textContent = '3/3 Aguardando confirmação...';
                if (statusMsg) statusMsg.textContent = 'Confirme a transferência de ' + amountUsdc + ' USDC na sua carteira para a infraestrutura do Grid402.';

                const txHash = await provider.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: userAddress,
                        to: usdcContract,
                        data: data,
                    }],
                });

                if (statusTitle) statusTitle.textContent = 'Transação enviada! Confirmando no bloco Base...';
                if (statusMsg) statusMsg.innerHTML = 'Tx enviada: <a href="https://basescan.org/tx/' + txHash + '" target="_blank" style="color: #00F0FF; text-decoration: underline;">' + txHash + '</a><br>Aguardando 3.5 segundos para emissão do seu Session Pass...';

                await new Promise(r => setTimeout(r, 3500));

                // 4. Chama o checkout Web3 para validar on-chain e emitir o Session Pass
                const resp = await fetch('/api/v1/pass-emitir?tx=' + encodeURIComponent(txHash));
                const passData = await resp.json();

                if (passData && (passData.status === 'ACTIVE' || passData.pass_token)) {
                    if (statusBox) statusBox.style.display = 'none';
                    if (successCard) successCard.style.display = 'block';

                    document.getElementById('topup-result-token').textContent = passData.pass_token;
                    document.getElementById('topup-result-credits').textContent = Number(passData.credits_total || 0).toLocaleString();
                    document.getElementById('topup-result-tier').textContent = (passData.tier || tierName).toUpperCase();
                    document.getElementById('topup-result-expires').textContent = passData.expires_at || '30 dias';

                    const exampleCurl = 'curl "https://grid402.online/api/v1/forex" -H "X-Payment: ' + passData.pass_token + '"';
                    document.getElementById('topup-example-curl').textContent = exampleCurl;

                    try {
                        localStorage.setItem('grid402_last_pass_token', passData.pass_token);
                    } catch (e) {}

                    successCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    throw new Error(passData.message || passData.error || 'A transação foi transmitida, mas o bloco ainda está propagando no RPC. Aguarde alguns segundos e consulte: /api/v1/pass?tx=' + txHash);
                }
            } catch (err) {
                if (statusTitle) statusTitle.textContent = 'Operação cancelada ou falhou';
                if (statusMsg) statusMsg.textContent = 'Detalhes do erro: ' + (err.message || err);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            }
        }

        async function startWeb3TopupWithSmartWallet(amountUsdc = 5, tierName = 'pro') {
            const btn = document.getElementById('btn-topup-passkey');
            const orig = btn ? btn.textContent : '';
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Conectando...';
                }
                const smartProvider = await getCoinbaseSmartWalletProvider();
                await startWeb3Topup(amountUsdc, tierName, smartProvider);
            } catch (err) {
                const statusBox = document.getElementById('topup-status-box');
                const statusTitle = document.getElementById('topup-status-title');
                const statusMsg = document.getElementById('topup-status-msg');
                if (statusBox) statusBox.style.display = 'block';
                if (statusTitle) statusTitle.textContent = 'Carteira Web3 não encontrada';
                if (statusMsg) {
                    statusMsg.innerHTML = 'Para 1 clique no navegador, ative uma extensão Web3 (MetaMask, Coinbase Wallet ou Rabby).<br><span style="color:#00F0FF; font-weight:600;">Ou pague agora pelo app:</span> envie os USDC para o endereço da Oracle abaixo e cole o Hash!';
                }
                const manualBox = document.getElementById('manual-topup-box');
                if (manualBox) manualBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = orig;
                }
            }
        }

        function copyOracleAddress() {
            const addr = '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
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
            const statusBox = document.getElementById('topup-status-box');
            const statusTitle = document.getElementById('topup-status-title');
            const statusMsg = document.getElementById('topup-status-msg');
            const successCard = document.getElementById('topup-success-card');
            const btn = document.getElementById('btn-manual-activate');

            if (!tx || !/^0x[a-fA-F0-9]{64}$/.test(tx)) {
                alert('Por favor, informe um hash de transação válido na rede Base (ex: 0x... com 64 caracteres hexadecimais).');
                return;
            }

            if (statusBox) statusBox.style.display = 'block';
            if (successCard) successCard.style.display = 'none';
            if (statusTitle) statusTitle.textContent = 'Validando transação na Base...';
            if (statusMsg) statusMsg.textContent = 'Consultando recibo on-chain e confirmando saldo de USDC emitido...';
            if (btn) { btn.disabled = true; btn.textContent = 'Validando...'; }

            try {
                const resp = await fetch('/api/v1/pass-emitir?tx=' + encodeURIComponent(tx));
                const passData = await resp.json();

                if (passData && (passData.status === 'ACTIVE' || passData.pass_token)) {
                    if (statusBox) statusBox.style.display = 'none';
                    if (successCard) successCard.style.display = 'block';

                    document.getElementById('topup-result-token').textContent = passData.pass_token;
                    document.getElementById('topup-result-credits').textContent = Number(passData.credits_total || 0).toLocaleString();
                    document.getElementById('topup-result-tier').textContent = (passData.tier || 'PRO').toUpperCase();
                    document.getElementById('topup-result-expires').textContent = passData.expires_at || '30 dias';

                    const exampleCurl = 'curl "https://grid402.online/api/v1/forex" -H "X-Payment: ' + passData.pass_token + '"';
                    document.getElementById('topup-example-curl').textContent = exampleCurl;

                    try {
                        localStorage.setItem('grid402_last_pass_token', passData.pass_token);
                    } catch (e) {}

                    successCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    throw new Error(passData.message || passData.error || 'Transação não encontrada ou ainda não propagada no RPC da Base. Aguarde alguns segundos e tente novamente.');
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
                <p>Todas as 22 APIs operam estritamente sobre fontes públicas abertas e fatos não passíveis de reserva proprietária: <strong>GLEIF LEI</strong> (CC0 1.0 Universal), <strong>OFAC SDN / ONU</strong> (Domínio Público 17 U.S.C. § 105), <strong>BCE / BCB / IBGE</strong> (Diretiva UE 2019/1024, Lei nº 12.527/2011 - LAI e Decreto nº 8.777/2016), <strong>Fatos Econômicos e Algoritmos de Calendário</strong> (Lei nº 9.610/1998 Art. 8º, I, II e IV) e <strong>Protocolos IETF</strong> (DNS, TLS, BGP/ASN, RFC 8785).</p>
                <h3>3. Pagamentos x402 e Session Passes</h3>
                <p>Os micropagamentos e passes pré-pagos em USDC na Base Mainnet (8453) remuneram exclusivamente o processamento computacional e a assinatura criptográfica ECDSA do Oráculo, sendo definitivos e irreversíveis.</p>
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
                <p>All 22 endpoints process strictly public-domain and open-licensed records: <strong>GLEIF LEI</strong> (CC0 1.0 Universal), <strong>US Treasury OFAC SDN / UN</strong> (Public Domain under 17 U.S.C. § 105), <strong>ECB / Central Bank Indicators</strong> (EU Open Data Directive 2019/1024 & Brazilian Open Data Decree 8.777/2016), <strong>Market Facts & Statutory Calendars</strong> (17 U.S.C. § 102(b) & Law 9.610/1998 Art. 8), and <strong>IETF Standards</strong> (DNS, TLS, BGP/ASN, RFC 8785).</p>
                <h3>3. x402 Micropayments and Session Passes</h3>
                <p>USDC micropayments and Session Passes settled on Base Mainnet (Chain ID 8453) compensate strictly for computational processing and ECDSA cryptographic attestation, with immediate on-chain finality.</p>
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
            let price = '0.0005';
            if (currentServiceAction === 'pass') price = '0.0500';
            else if (currentServiceAction === 'compliance') price = '0.0030';
            else if (currentServiceAction === 'sanctions' || currentServiceAction === 'simples') price = '0.0010';
            else if (currentServiceAction === 'cnpj' || currentServiceAction === 'lei' || currentServiceAction === 'ofac') price = '0.0020';
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
        }

        function handleWeb3OverlayClick(e) {
            if (e.target.id === 'web3-modal-overlay') closeWeb3Modal();
        }

        function copyOracleAddress() {
            const input = document.getElementById('web3-wallet-dest-input');
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = document.getElementById('btn-copy-oracle');
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
            }
        });

        // Calculadora de Economia / ROI (Tradicional vs Grid402)
        function updateRoiCalc(val) {
            const count = parseInt(val, 10) || 1000;
            const displayEl = document.getElementById('roi-vol-display');
            const tradEl = document.getElementById('roi-cost-trad');
            const gridEl = document.getElementById('roi-cost-grid');
            const savingsEl = document.getElementById('roi-savings-val');
            const descEl = document.getElementById('roi-savings-sub');
            const slider = document.getElementById('roi-slider-input');

            const isEn = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en');

            if (slider) {
                slider.value = count;
            }
            if (displayEl) {
                displayEl.textContent = count.toLocaleString(isEn ? 'en-US' : 'pt-BR') + (isEn ? ' / month' : ' / mes');
            }

            // Modelo Tradicional: R$ 500 (~$95) mensalidade base + R$ 1,00 ($0.19) por consulta media
            const costTrad = isEn ? (95 + (count * 0.19)) : (500 + (count * 1.00));

            // Grid402: 0.0020 USDC por consulta
            const costGrid = isEn ? (count * 0.0020) : (count * (0.0020 * 5.42));

            const savings = Math.max(0, costTrad - costGrid);
            const savingsPct = Math.round((savings / costTrad) * 100);

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
                    ? 'Estimated monthly savings of ' + savingsPct + '%!'
                    : 'Economia estimada de ' + savingsPct + '% todos os meses!';
            }
        }

        function setRoiPreset(val) {
            updateRoiCalc(val);
        }

        // Inicializa a calculadora com 1000 consultas ao carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { updateRoiCalc(1000); });
        } else {
            updateRoiCalc(1000);
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
    <script src="/assets/js/i18n.js?v=3.0.5"></script>
</body>
</html>
