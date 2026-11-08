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
require_once __DIR__ . '/services/asn.php';
require_once __DIR__ . '/services/cep.php';
require_once __DIR__ . '/services/cnpj.php';
require_once __DIR__ . '/services/indicators.php';
require_once __DIR__ . '/services/holidays.php';
require_once __DIR__ . '/services/workdays.php';
require_once __DIR__ . '/services/oracle_signer.php';
require_once __DIR__ . '/metrics.php';

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
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (str_starts_with($authHeader, 'x402 ')) {
        $paymentProof = substr($authHeader, 5);
    }
}

// Cabeçalhos Globais de IA, Protocolo e Descoberta
header('Vary: Accept, User-Agent');
header('Link: </openapi.json>; rel="service-desc"; type="application/vnd.oai.openapi+json", </llms.txt>; rel="help"; type="text/markdown", </.well-known/mcp/server-card.json>; rel="service-meta"; type="application/json", </sitemap.xml>; rel="sitemap"; type="application/xml"');
header('X-Agent-Protocol: x402; network=base; chain_id=8453');
header('X-Micropayment-Pricing: 0.0005 USDC; token=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913');
header('Content-Signal: search=yes, ai-train=no');

/**
 * Renderizador de Paginas Institucionais HTML para Agentes e Navegadores
 */
function grid402_render_page_html(string $title, string $metaDesc, string $canonicalPath, string $h1, string $bodyContent): void {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <link rel="canonical" href="https://orangered-jackal-900744.hostingersite.com<?php echo $canonicalPath; ?>">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="sitemap" type="application/xml" href="/sitemap.xml">
    <link rel="service-meta" type="application/json" href="/.well-known/mcp/server-card.json">

    <!-- Schema.org JSON-LD Completo com Organization, LocalBusiness e Endereco Fisico -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": ["Organization", "LocalBusiness"],
          "@id": "https://orangered-jackal-900744.hostingersite.com/#organization",
          "name": "Grid402",
          "url": "https://orangered-jackal-900744.hostingersite.com",
          "logo": "https://orangered-jackal-900744.hostingersite.com/badge/summary.svg",
          "email": "contato@grid402.com.br",
          "telephone": "+55-11-95832-5848",
          "description": "Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "Avenida Paulista, 1000",
            "addressLocality": "Sao Paulo",
            "addressRegion": "SP",
            "postalCode": "01310-100",
            "addressCountry": "BR"
          },
          "priceRange": "$0.0005 - $0.0020",
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
              "email": "contato@grid402.com.br",
              "telephone": "+55-11-95832-5848",
              "availableLanguage": ["Portuguese", "English"]
            }
          ]
        }
      ]
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090D14;
            --surface: #111726;
            --border: #1E293B;
            --text-main: #F1F5F9;
            --text-muted: #94A3B8;
            --primary: #10B981;
            --accent: #38BDF8;
            --font-sans: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { background-color: var(--bg); color: var(--text-main); font-family: var(--font-sans); line-height: 1.6; padding: 2rem 1rem; }
        .page-wrap { max-width: 820px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 2.5rem; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .nav-links a { color: var(--text-muted); text-decoration: none; margin-left: 1.25rem; font-size: 0.9rem; font-weight: 500; }
        .nav-links a:hover, .nav-links a.active { color: var(--primary); }
        h1 { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 1.25rem; }
        h2 { font-size: 1.25rem; color: #fff; margin: 1.75rem 0 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        p { color: var(--text-muted); margin-bottom: 1rem; }
        ul { margin: 0 0 1.25rem 1.5rem; color: var(--text-muted); }
        li { margin-bottom: 0.5rem; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .card-box { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; }
        footer { margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .footer-links a { color: var(--text-muted); margin-right: 1rem; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <header>
            <div>
                <a href="/" style="font-weight: 800; font-size: 1.3rem; color: #fff; text-decoration: none;">Grid402</a>
            </div>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/about" <?php echo $canonicalPath === '/about' ? 'class="active"' : ''; ?>>About</a>
                <a href="/contact" <?php echo $canonicalPath === '/contact' ? 'class="active"' : ''; ?>>Contact</a>
                <a href="/terms" <?php echo $canonicalPath === '/terms' ? 'class="active"' : ''; ?>>Terms</a>
                <a href="/privacy" <?php echo $canonicalPath === '/privacy' ? 'class="active"' : ''; ?>>Privacy</a>
            </nav>
        </header>

        <h1><?php echo htmlspecialchars($h1); ?></h1>
        <?php echo $bodyContent; ?>

        <footer>
            <div class="footer-links">
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/contact">Contact</a>
                <a href="/terms">Terms</a>
                <a href="/privacy">Privacy</a>
                <a href="/llms.txt">llms.txt</a>
                <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a>
            </div>
            <div>Copyright (c) <?php echo date('Y'); ?> Grid402. Licenca MIT.</div>
        </footer>
    </div>
</body>
</html>
    <?php
    exit;
}

// 1. Rotas Institucionais e Jurídicas
if ($pageParam === 'about' || $requestPath === '/about') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $aboutBody = '
        <p>O <strong>Grid402</strong> e um hub de micro-servicos publicos e oraculo de utilitarios de rede desenhado especificamente para sistemas autonomos, robos e agentes de IA sob o padrao HTTP 402 com liquidacao em USDC na rede Base Mainnet.</p>
        <h2>Proposito e Arquitetura</h2>
        <p>O projeto elimina chaves estaticas de API e burocracia de cadastros cadastrais para agentes de software. Toda requisicao tecnica a dados oficiais (Receita Federal, CEP, Banco Central, DNS, SSL e BGP) pode ser liquidada diretamente on-chain com micropagamentos por chamada.</p>
        <ul>
            <li><strong>Zero-DB e Stateless:</strong> Sem persistencia de bancos de dados relacionais; operacao 100% em memoria e cache transiente.</li>
            <li><strong>Protocolo x402:</strong> Implementacao nativa do codigo HTTP 402 Payment Required para Machine-to-Machine (M2M).</li>
            <li><strong>Open Source:</strong> Codigo fonte livre sob Licenca MIT disponivel para auditoria publica.</li>
        </ul>
        <h2>Informacoes Institucionais e Endereco</h2>
        <div class="card-box">
            <p><strong>Organizacao:</strong> Grid402 Network Intelligence</p>
            <p><strong>Endereco Fisico:</strong> Avenida Paulista, 1000, Bela Vista, Sao Paulo - SP, CEP 01310-100, Brasil</p>
            <p><strong>Email de Suporte:</strong> <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a></p>
            <p><strong>Central Telefonica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            <p><strong>Carteira Oraculo:</strong> <a href="https://basescan.org/address/0xa31c31069c43ac1c0075a93c325b0dcd7744356c" target="_blank" rel="noopener noreferrer">0xa31c31069c43ac1c0075a93c325b0dcd7744356c</a></p>
        </div>';
        grid402_render_page_html('About Grid402 - Hub de Utilitarios de Rede e Dados Publicos', 'Saiba mais sobre a infraestrutura e os servicos publicos de rede do Grid402 para agentes de IA e desenvolvedores.', '/about', 'About Grid402', $aboutBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'organization' => 'Grid402',
            'name' => 'Grid402 Public Data and Network Utilities Hub',
            'description' => 'Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.',
            'network' => 'Base Mainnet',
            'chain_id' => 8453,
            'oracle_wallet' => '0xa31c31069c43ac1c0075a93c325b0dcd7744356c',
            'address' => [
                'street' => 'Avenida Paulista, 1000',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'postal_code' => '01310-100',
                'country' => 'Brasil'
            ],
            'license' => 'MIT',
            'sdks' => [
                'python' => 'https://pypi.org/project/grid402/',
                'node' => 'https://www.npmjs.com/package/grid402',
                'mcp' => 'https://www.npmjs.com/package/grid402-mcp'
            ],
            'documentation' => 'https://orangered-jackal-900744.hostingersite.com/llms.txt',
            'contact' => 'contato@grid402.com.br'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'contact' || $requestPath === '/contact') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $contactBody = '
        <p>Disponibilizamos canais diretos e estruturados para suporte tecnico, parcerias para integradores de IA e contato com o Encarregado de Tratamento de Dados Pessoais (DPO):</p>
        <h2>Canais Oficiais de Atendimento</h2>
        <div class="card-box">
            <p><strong>Email de Suporte Geral e Integracoes:</strong> <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a></p>
            <p><strong>Encarregado de Dados (DPO / LGPD):</strong> <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a></p>
            <p><strong>Central Telefonica:</strong> <a href="tel:+5511958325848">+55 11 95832-5848</a></p>
            <p><strong>Endereco Fisico:</strong> Avenida Paulista, 1000, Bela Vista, Sao Paulo - SP, CEP 01310-100, Brasil</p>
        </div>
        <h2>Ecossistema e Codigo Aberto</h2>
        <ul>
            <li><strong>GitHub Oficial:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a></li>
            <li><strong>Pacote Node.js (npm):</strong> <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a></li>
            <li><strong>Pacote Python (PyPI):</strong> <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a></li>
            <li><strong>Servidor MCP (Model Context Protocol):</strong> <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a></li>
        </ul>';
        grid402_render_page_html('Contact Grid402 - Canais Oficiais e Suporte', 'Entre em contato com o Grid402: canais oficiais de suporte tecnico, encarregado de dados (DPO) e repositorios.', '/contact', 'Contact Grid402', $contactBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'organization' => 'Grid402',
            'support_email' => 'contato@grid402.com.br',
            'dpo_email' => 'contato@grid402.com.br',
            'telephone' => '+55-11-95832-5848',
            'address' => [
                'street' => 'Avenida Paulista, 1000',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'postal_code' => '01310-100',
                'country' => 'Brasil'
            ],
            'github' => 'https://github.com/oliveiratc/Grid402',
            'website' => 'https://orangered-jackal-900744.hostingersite.com'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'terms' || $requestPath === '/terms') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $termsBody = '
        <p>Termos de servico do hub de micro-servicos e utilitarios de rede Grid402.</p>
        <h2>1. Fornecimento no Estado em que se Encontra (AS-IS)</h2>
        <p>O Grid402 e fornecido estritamente no estado em que se encontra, sem garantias de adequacao a finalidades comerciais exclusivas.</p>
        <h2>2. Natureza dos Dados Publicos</h2>
        <p>As consultas utilizam informacoes de dominio publico e registros abertos (Receita Federal, Banco Central, Registros Postais, DNS, TLS e BGP/ASN).</p>
        <h2>3. Micropagamentos Irreversiveis</h2>
        <p>Pagamentos efetuados via protocolo HTTP 402 liquidados na Base Mainnet (EVM 8453) sao irreversiveis e definitivos.</p>
        <h2>4. Licenciamento</h2>
        <p>O software e as bibliotecas clientes sao disponibilizados sob os termos da <strong>Licenca MIT</strong>.</p>';
        grid402_render_page_html('Terms of Service - Grid402', 'Termos de servico, condicoes de uso M2M e licenciamento MIT do Grid402.', '/terms', 'Terms of Service', $termsBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'document' => 'Terms of Service',
            'version' => '2.0.0',
            'hub' => 'Grid402',
            'disclaimer_as_is' => 'O Grid402 e um hub informativo de utilitarios publicos de rede fornecido NO ESTADO EM QUE SE ENCONTRA (AS-IS), sem garantias de precisao infalivel ou adequacao a fins comerciais especificos.',
            'data_scope' => 'O servico processa estritamente dados de dominio publico e protocolos abertos de rede (DNS, SSL, BGP, Cambio PTAX, Criptoativos e CEP nacional).',
            'settlement' => 'Micropagamentos via x402 liquidados na Base Mainnet (EVM 8453) sao definitivos e irreversiveis.',
            'contact' => 'contato@grid402.com.br',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pageParam === 'privacy' || $requestPath === '/privacy') {
    if (!$isJsonExplicit && !isset($_GET['format'])) {
        $privacyBody = '
        <p>Diretrizes de privacidade, conformidade e governanca do hub Grid402.</p>
        <h2>1. Conformidade com a LGPD (Lei nº 13.709/2018)</h2>
        <p>O Grid402 opera estritamente com dados de dominio publico e nao coleta, armazena ou comercializa dados pessoais sensiveis ou PII de cidadaos.</p>
        <h2>2. Marco Civil da Internet (Lei nº 12.965/2014)</h2>
        <p>Registros de conexao (IP, data e hora) sao mantidos em conformidade estrita com o Artigo 15 da legislacao brasileira por 6 meses para cumprimento legal.</p>
        <h2>3. Encarregado de Dados (DPO)</h2>
        <p>Para qualquer solicitacao de conformidade, entre em contato via <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a>.</p>';
        grid402_render_page_html('Privacy Policy - Grid402', 'Diretrizes de privacidade, conformidade LGPD e seguranca da informacao do Grid402.', '/privacy', 'Privacy Policy', $privacyBody);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'document' => 'Privacy Policy (LGPD / Marco Civil)',
            'version' => '2.0.0',
            'hub' => 'Grid402',
            'compliance' => ['LGPD (Lei nº 13.709/2018)', 'Marco Civil da Internet (Lei nº 12.965/2014)'],
            'data_collected' => [
                'connection_logs' => 'Endereco IP, data e hora da conexao mantidos sob sigilo por 6 meses estritamente para cumprimento do Art. 15 da Lei nº 12.965/2014.',
                'onchain_hashes' => 'Hash da transacao Base informado no cabecalho X-Payment mantido em cache temporario por 7 dias para prevencao de replay attack (Art. 7º, IX da LGPD - Legitimo Interesse).',
            ],
            'infrastructure_scope' => 'Zero coleta de dados pessoais sensiveis ou PII de cidadaos.',
            'contact_dpo' => 'contato@grid402.com.br',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 2. Classificação de Rotas
$isStatusRoute = ($requestPath === '/status' || $requestPath === '/api/v1/summary' || $requestPath === '/telemetry');
$isHomeRoute = ($requestPath === '/' || $requestPath === '/index.php');
$isVerifyRoute = ($requestPath === '/api/v1/verify' || $apiParam === 'verify');
$validApiEndpoints = ['summary', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'cep', 'cnpj', 'indicators', 'holidays', 'workdays'];
$isValidApiAction = in_array($apiParam, $validApiEndpoints, true);
$isInstitutionalRoute = in_array($requestPath, ['/about', '/contact', '/terms', '/privacy', '/admin', '/dashboard'], true);

// 3. Rota Inexistente: Retorna HTTP 404 Real (Issue 3)
if (!$isHomeRoute && !$isStatusRoute && !$isValidApiAction && !$isInstitutionalRoute && !$isVerifyRoute) {
    http_response_code(404);
    if (str_contains($acceptHeader, 'text/html') && !str_starts_with($userAgent, 'curl/')) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>404 - Pagina Nao Encontrada | Grid402</title><style>body{background:#090D14;color:#F1F5F9;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column}h1{font-size:3.5rem;margin:0;color:#10B981}p{color:#94A3B8;font-size:1.1rem}a{color:#38BDF8;text-decoration:none;font-weight:600}</style></head><body><h1>404</h1><p>A pagina ou rota solicitada nao existe no Grid402.</p><p><a href="/">Voltar a pagina inicial</a></p></body></html>';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'NotFound',
            'status' => 404,
            'message' => 'A rota solicitada nao existe no hub Grid402.',
            'documentation' => 'https://orangered-jackal-900744.hostingersite.com/llms.txt',
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
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Authenticate, X-Accept-Payment, X-Payment-Status, X-Payment-Amount, X-Payment-Network');

    // 2.2. Verificação prévia defensiva (CNPJ inválido retorna 400 antes de cobrar)
    $isCnpj = ($apiParam === 'cnpj');
    $servicePriceDisplay = $isCnpj ? GRID402_PRICE_CNPJ : GRID402_PRICE_DISPLAY;
    $servicePriceRaw = $isCnpj ? GRID402_USDC_CNPJ_RAW : GRID402_USDC_REQUIRED_RAW;

    if ($isCnpj) {
        $cnpjInput = (string) ($_GET['cnpj'] ?? $_GET['code'] ?? '');
        if ($cnpjInput === '' || !grid402_validate_cnpj_digits($cnpjInput)) {
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
        }

        grid402_respond_402($serviceLabel, null, $servicePriceDisplay, $servicePriceRaw, $previewData, $lockedFields);
    }

    // Auditoria on-chain com trava atômica anti-replay e suporte a Modo Sandbox
    $clientIp = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    if (str_contains($clientIp, ',')) {
        $clientIp = trim(explode(',', $clientIp)[0]);
    }

    $validation = grid402_validate_payment($paymentProof, $servicePriceRaw, $clientIp);
    if (!$validation['valid']) {
        $serviceLabel = $apiParam !== '' ? $apiParam : 'technical-utility';
        grid402_record_telemetry($serviceLabel, $validation['code'], false, $paymentProof, null, (float)$servicePriceDisplay);
        http_response_code($validation['code']);
        header('Content-Type: application/json; charset=utf-8');
        if ($validation['code'] === 402) {
            header('X-Authenticate: x402');
            header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . $servicePriceDisplay);
        } elseif ($validation['code'] === 429) {
            header('Retry-After: ' . ($validation['retry_after'] ?? 3600));
        }

        $errPayload = [
            'error' => ($validation['code'] === 403) ? 'ReplayAttackDetected' : (($validation['code'] === 429) ? 'SandboxRateLimitExceeded' : 'PaymentFailed'),
            'message' => $validation['reason'],
            'tx_hash' => $paymentProof,
        ];
        if (isset($validation['sandbox'])) {
            $errPayload['sandbox'] = true;
        }
        echo json_encode($errPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Pagamento ou Sandbox validado com sucesso! Executa o micro-serviço requisitado
    $isSandbox = !empty($validation['sandbox']);
    grid402_record_telemetry($apiParam, 200, true, $paymentProof, null, $isSandbox ? 0.0 : (float)$servicePriceDisplay);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Payment-Status: Accepted');
    if ($isSandbox) {
        header('X-Payment-Mode: sandbox');
        header('X-Payment-Amount: 0.0000 USDC (Sandbox)');
        header('X-Payment-Network: ' . ($validation['network'] ?? 'Simulation (demo)'));
    } else {
        header('X-Payment-Mode: production');
        header('X-Payment-Amount: ' . $servicePriceDisplay . ' USDC');
        header('X-Payment-Network: Base (ChainID 8453)');
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 - Hub de Utilitarios de Rede e Dados Publicos com Micropagamentos x402 na Base</title>
    <meta name="description" content="Grid402: Plataforma de micro-servicos publicos (CNPJ, CEP, Forex, Cripto, DNS, SSL, ASN) para agentes autonomos de IA e desenvolvedores, com micropagamentos x402 em USDC na rede Base.">
    <link rel="canonical" href="https://orangered-jackal-900744.hostingersite.com/">
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
          "@id": "https://orangered-jackal-900744.hostingersite.com/#organization",
          "name": "Grid402",
          "url": "https://orangered-jackal-900744.hostingersite.com",
          "logo": "https://orangered-jackal-900744.hostingersite.com/badge/summary.svg",
          "email": "contato@grid402.com.br",
          "telephone": "+55-11-95832-5848",
          "description": "Hub de micro-servicos publicos e oraculo de utilitarios de rede para agentes autonomos de IA sob o protocolo HTTP 402 com liquidacao em USDC na rede Base.",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "Avenida Paulista, 1000",
            "addressLocality": "Sao Paulo",
            "addressRegion": "SP",
            "postalCode": "01310-100",
            "addressCountry": "BR"
          },
          "priceRange": "$0.0005 - $0.0020",
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
              "email": "contato@grid402.com.br",
              "telephone": "+55-11-95832-5848",
              "availableLanguage": ["Portuguese", "English"]
            }
          ]
        },
        {
          "@type": "WebSite",
          "@id": "https://orangered-jackal-900744.hostingersite.com/#website",
          "url": "https://orangered-jackal-900744.hostingersite.com",
          "name": "Grid402",
          "description": "Oraculo M2M de dados publicos e utilitarios de rede sob o protocolo HTTP 402.",
          "publisher": {
            "@id": "https://orangered-jackal-900744.hostingersite.com/#organization"
          }
        },
        {
          "@type": "SoftwareApplication",
          "@id": "https://orangered-jackal-900744.hostingersite.com/#software",
          "name": "Grid402 WebAPI e Servidor MCP",
          "applicationCategory": "DeveloperApplication",
          "operatingSystem": "All",
          "offers": {
            "@type": "Offer",
            "price": "0.0005",
            "priceCurrency": "USD"
          },
          "license": "https://opensource.org/licenses/MIT",
          "author": {
            "@id": "https://orangered-jackal-900744.hostingersite.com/#organization"
          }
        }
      ]
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
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            padding: 0 1.5rem;
            min-height: 100vh;
        }
        p {
            text-align: justify;
            text-justify: inter-word;
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
            color: var(--primary);
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
            color: #fff;
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
            color: var(--primary);
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
            color: var(--primary); border-bottom-color: var(--primary); background: rgba(16, 185, 129, 0.05);
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
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.2);
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
            color: #030712;
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
            background: rgba(56, 189, 248, 0.1);
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
            background: #030712;
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
            background: #020408;
            border: 1px solid #1E293B;
            border-radius: 8px;
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: #38BDF8;
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
            background: rgba(17, 23, 38, 0.85);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1.25rem;
            transition: all 0.3s ease;
        }
        .audit-card.valid {
            border-color: rgba(16, 185, 129, 0.5);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.1);
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
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary);
            border: 1px solid rgba(16, 185, 129, 0.4);
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
            background: rgba(3, 7, 18, 0.5);
            border: 1px solid rgba(30, 41, 59, 0.6);
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
            background: rgba(16, 185, 129, 0.2);
            color: var(--primary);
            border: 1px solid var(--primary);
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
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-wrap">
                <span class="logo-badge">x402</span>
                <span class="logo-title">Grid402</span>
            </div>
            <nav class="nav-links">
                <a href="#services">Servicos</a>
                <a href="#playground">Playground</a>
                <a href="#console">Console</a>
                <a href="/about">About</a>
                <a href="/contact">Contact</a>
                <a href="/terms">Terms</a>
                <a href="/privacy">Privacy</a>
            </nav>
            <div class="badge-live">
                <span class="badge-pulse"></span>
                <span>Base Mainnet (8453) • 11/11 Servicos Operantes</span>
            </div>
        </header>

        <section class="hero">
            <div class="hero-tag">Hub de Micro-Servicos para Robos e Agentes de IA</div>
            <h1>Grid402: Hub de Utilitarios de Rede e Dados Publicos para Agentes de IA</h1>
            <p>Acesse cotacoes de cambio, cripto, indicadores economicos oficiais (Selic, CDI e IPCA), feriados nacionais e bancarios, DNS, certificados SSL, BGP/ASN, CEP e CNPJ da Receita Federal em milissegundos sem cadastro ou chaves de API. Micropagamentos por chamada em USDC na Base.</p>
            <div class="cta-group">
                <a href="#playground" class="btn btn-primary">⚡ Testar no Playground</a>
                <a href="#console" class="btn btn-outline" onclick="switchTab('console')">Console e SDKs</a>
                <a href="/about" class="btn btn-outline">About Us</a>
                <a href="/contact" class="btn btn-outline">Contact Us</a>
                <a href="/llms.txt" class="btn btn-outline">Documentacao llms.txt</a>
                <a href="/status" class="btn btn-outline">Resumo Gratuito (JSON)</a>
            </div>
        </section>

        <!-- Grid dos Servicos Publicos -->
        <div id="services" class="grid-services">
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

        <!-- Playground Interativo e Verificador de Oraculo -->
        <section id="playground" class="protocol-box">
            <div class="playground-header-wrap">
                <div class="hero-tag" style="margin-bottom: 0.4rem;">Auditoria Criptografica M2M</div>
                <h2>Playground Interativo e Verificador de Oraculo</h2>
                <p>Execute consultas reais nos 11 micro-servicos do Grid402 em modo Sandbox gratuito ou simule o desafio x402. Valide instantaneamente a atestacao criptografica ECDSA secp256k1 (_oracle_proof) com o motor matematico em tempo real.</p>
            </div>

            <!-- 11 Presets de Micro-Servicos -->
            <div class="playground-presets" id="playground-preset-bar">
                <!-- Preenchido via JavaScript com os 11 micro-servicos -->
            </div>

            <!-- Barra de Controles e Alternador de Modo -->
            <div class="playground-toolbar">
                <div class="mode-toggle-group">
                    <button type="button" class="mode-toggle-btn active" id="btn-mode-sandbox" onclick="setPlaygroundMode('sandbox')">🧪 Sandbox (demo)</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-challenge" onclick="setPlaygroundMode('challenge')">🔒 Desafio x402 (HTTP 402)</button>
                    <button type="button" class="mode-toggle-btn" id="btn-mode-manual" onclick="setPlaygroundMode('manual')">✍️ Validar JSON Externo</button>
                </div>
                <div class="playground-url-bar">
                    <span>Rota:</span>
                    <span id="playground-endpoint-display" class="playground-url-pill">GET /api/v1/workdays</span>
                </div>
                <div>
                    <button type="button" id="btn-run-query" class="btn-sm btn-primary" onclick="runPlaygroundQuery()">⚡ Executar Consulta</button>
                </div>
            </div>

            <!-- Painel de Execucao em 2 Colunas -->
            <div class="playground-grid">
                <!-- Coluna 1: Parametros ou Area de JSON Manual -->
                <div class="playground-card" id="playground-col-params">
                    <div class="playground-card-title">
                        <span id="playground-col-title">Configuracao da Consulta</span>
                        <span id="playground-service-badge" class="card-category">workdays</span>
                    </div>

                    <div id="playground-preset-controls">
                        <p id="playground-service-desc" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            Calculo de dias uteis bancarios ANBIMA e projecao de prazos D+N.
                        </p>
                        <div id="playground-params-inputs" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                            <!-- Parametros dinamicos do preset atual -->
                        </div>
                    </div>

                    <div id="playground-manual-controls" style="display: none; flex-direction: column; height: 100%;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                            Cole abaixo qualquer resposta JSON retornada pelo Grid402 ou utilize um dos modelos de teste:
                        </p>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                            <button type="button" class="btn-sm" onclick="loadManualTemplate('valid')">Carregar Exemplo Valido</button>
                            <button type="button" class="btn-sm" onclick="loadManualTemplate('tampered')">Carregar Exemplo Adulterado</button>
                            <button type="button" class="btn-sm btn-primary" onclick="verifyManualPayload()">Auditar JSON</button>
                        </div>
                        <textarea id="playground-manual-input" class="console-input" style="flex: 1; min-height: 220px; font-family: var(--font-mono); font-size: 0.8rem; resize: vertical;" placeholder="Cole o JSON da resposta contendo o bloco _oracle_proof..."></textarea>
                    </div>
                </div>

                <!-- Coluna 2: Retorno JSON e Card de Auditoria -->
                <div class="playground-card">
                    <div class="playground-card-title">
                        <span>Retorno da API</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span id="playground-http-status" class="card-category">Pronto</span>
                            <span id="playground-latency-tag" class="card-category" style="display: none;"></span>
                            <button type="button" class="btn-copy-json" id="btn-copy-playground" onclick="copyPlaygroundJson()">Copiar JSON</button>
                        </div>
                    </div>
                    <pre id="playground-json-viewer" class="json-viewer-pre">// O resultado da consulta ou da auditoria aparecera aqui...</pre>

                    <!-- Card de Auditoria em 3 Etapas -->
                    <div id="playground-audit-card" class="audit-card" style="display: none;">
                        <div class="audit-card-header">
                            <div>
                                <span style="font-weight: 700; color: #fff; font-size: 0.95rem;">Auditoria Criptografica</span>
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
                                        <span>1. Integridade dos Dados (Payload Hash)</span>
                                        <span id="audit-step-1-status" class="card-category">Aguardando</span>
                                    </div>
                                    <div class="audit-step-desc">
                                        Calcula o hash SHA-256 dos campos do payload e confere se nao houve modificacao apos a assinatura.
                                    </div>
                                    <div id="audit-step-1-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Etapa 2: Carteira do Oraculo -->
                            <div class="audit-step-item">
                                <div id="audit-step-2-icon" class="audit-step-icon icon-pending">2</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span>2. Carteira Oficial do Oraculo (BaseScan)</span>
                                        <span id="audit-step-2-status" class="card-category">Aguardando</span>
                                    </div>
                                    <div class="audit-step-desc">
                                        Valida se o endereco recuperado corresponde a carteira oficial autorizada na rede Base.
                                    </div>
                                    <div id="audit-step-2-meta" class="audit-step-meta" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Etapa 3: Assinatura ECDSA secp256k1 -->
                            <div class="audit-step-item">
                                <div id="audit-step-3-icon" class="audit-step-icon icon-pending">3</div>
                                <div class="audit-step-body">
                                    <div class="audit-step-title">
                                        <span>3. Assinatura Criptografica ECDSA</span>
                                        <span id="audit-step-3-status" class="card-category">Aguardando</span>
                                    </div>
                                    <div class="audit-step-desc">
                                        Comprova matematicamente a autoria do oraculo com a curva secp256k1 via OpenSSL.
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
            <h2>Console de Teste e Especificações de Integração</h2>
            <p>Experimente as requisições em tempo real ou copie os snippets para seus agentes autônomos.</p>

            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('console')">⚡ Console Interativo</button>
                <button class="tab-btn" onclick="switchTab('smithery')">📦 Smithery MCP (1 Clique)</button>
                <button class="tab-btn" onclick="switchTab('agentkit')">🪙 Coinbase AgentKit (M2M)</button>
                <button class="tab-btn" onclick="switchTab('python')">🐍 Python SDK</button>
                <button class="tab-btn" onclick="switchTab('node')">🟢 Node.js SDK</button>
                <button class="tab-btn" onclick="switchTab('curl')">💻 cURL CLI</button>
                <button class="tab-btn" onclick="switchTab('badges')">🛡️ Badges SVG</button>
            </div>

            <!-- Tab 1: Console Interativo -->
            <form id="tab-console" class="tab-content active" toolname="grid402_console" tooldescription="Console interativo de consulta e teste dos servicos Grid402 sob protocolo x402" onsubmit="event.preventDefault();">
                <div class="console-actions">
                    <button type="button" class="btn-sm" onclick="runConsole('summary')">Resumo Gratuito (/status)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('forex')">Câmbio (/api/v1/forex)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('crypto')">Cripto (/api/v1/crypto)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('dns')">DNS (/api/v1/dns)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('ssl')">SSL (/api/v1/ssl)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('asn')">ASN (/api/v1/asn)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('cep')">CEP (/api/v1/cep)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('cnpj')">CNPJ (/api/v1/cnpj)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('indicators')">Indicadores (/api/v1/indicators)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('holidays')">Feriados (/api/v1/holidays)</button>
                    <button type="button" class="btn-sm" onclick="runConsole('workdays')">Dias Úteis (/api/v1/workdays)</button>
                </div>
                <div class="input-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="text" id="tx-input" class="console-input" style="flex: 1; min-width: 260px;" placeholder="Cole o hash de pagamento na Base (0x...) ou use 'demo' para simulação">
                    <button type="button" class="btn-sm" onclick="runConsole('paid')">Enviar com X-Payment</button>
                    <button type="button" class="btn-sm btn-primary" id="btn-web3-pay" onclick="payWithWeb3Wallet()">⚡ Pagar com Carteira Web3 (1 Clique)</button>
                    <button type="button" class="btn-sm btn-outline" onclick="openWeb3Modal()" title="Abrir QR Code e instruções de pagamento">📱 QR Code / Celular</button>
                    <button type="button" class="btn-sm btn-outline" onclick="runSandboxSimulation()" title="Simular pagamento gratuitamente no Modo Sandbox">🧪 Simular Sandbox (demo)</button>
                </div>

                <!-- Card de Destaque da Amostra (Teaser Preview) -->
                <div id="console-teaser-card" style="display: none; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 1.25rem; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: var(--primary); color: #030712; font-weight: 700; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px;">AMOSTRA GRATUITA ENCONTRADA</span>
                            <span id="teaser-title" style="font-weight: 700; color: #fff; font-size: 0.95rem;"></span>
                        </div>
                        <span id="teaser-badge-price" style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--accent); font-weight: 600;"></span>
                    </div>
                    <div id="teaser-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 0.85rem; margin-bottom: 1rem;">
                    </div>
                    <div style="background: rgba(0, 0, 0, 0.25); border-radius: 6px; padding: 0.6rem 0.8rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                        <strong>🔒 Campos protegidos que serão liberados no HTTP 200:</strong>
                        <div id="teaser-locked-fields" style="margin-top: 4px; color: #94a3b8; font-family: var(--font-mono); font-size: 0.75rem;"></div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <button class="btn btn-primary" onclick="payWithWeb3Wallet()" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                            ⚡ Desbloquear Dados Completos com Web3
                        </button>
                        <button class="btn btn-outline" onclick="openWeb3Modal()" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;">
                            📱 Pagar com QR Code / Celular
                        </button>
                        <button class="btn btn-outline" onclick="runSandboxSimulation()" style="font-size: 0.85rem; padding: 0.5rem 0.9rem;">
                            🧪 Testar via Sandbox (demo)
                        </button>
                    </div>
                </div>
                <div class="response-header">
                    <span id="console-status-text">Selecione uma das ações acima para testar o retorno da API.</span>
                    <span id="console-latency-badge" style="display:none;" class="card-category"></span>
                </div>
                <pre id="console-output" class="code-block">// Resposta da API aparecerá aqui...</pre>
            </form>

            <!-- Tab Smithery MCP -->
            <div id="tab-smithery" class="tab-content">
                <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 0.75rem;">
                        <span style="font-weight: 700; color: #fff; font-size: 1.05rem;">📦 Instalação em 1 Clique via Smithery.ai</span>
                        <span class="card-category">Claude Desktop • Cursor • MCP Stdout</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5;">
                        O Grid402 está catalogado no registro global da Smithery.ai. Você pode registrar instantaneamente o servidor de ferramentas no seu Claude Desktop ou Cursor sem precisar editar arquivos de configuração manualmente.
                    </p>
                    
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;">Comando de Instalação no Claude Desktop:</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">npx -y @smithery/cli install grid402-mcp --client claude</pre>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;">Comando de Instalação no Cursor:</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">npx -y @smithery/cli install grid402-mcp --client cursor</pre>
                    </div>

                    <div style="background: rgba(0, 0, 0, 0.3); border-radius: 6px; padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                        <strong style="color: #fff;">💡 Parâmetros Suportados no smithery.yaml:</strong>
                        <ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;">
                            <li><code>sandbox: true</code> — Ativa o Modo Sandbox gratuito (20 consultas por hora por IP) para testes sem necessidade de carteira.</li>
                            <li><code>privateKey: 0x...</code> — Chave privada EVM opcional para auto-liquidação em USDC na Base (0.0005 a 0.0020 USDC por chamada).</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tab Coinbase AgentKit -->
            <div id="tab-agentkit" class="tab-content">
                <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 0.75rem;">
                        <span style="font-weight: 700; color: #fff; font-size: 1.05rem;">🪙 Integração Nativa Coinbase AgentKit (CDP)</span>
                        <span class="card-category">M2M Auto-Settlement • LangChain • CrewAI</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5;">
                        Permita que agentes de IA com carteiras CDP na Base consultem dados públicos e utilitários de rede pagando tarifas x402 de forma 100% autônoma, sem interrupção humana. O provedor de ações intercepta o desafio 402, transfere o USDC e reenvia a requisição de forma transparente.
                    </p>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;">Exemplo em TypeScript (@coinbase/agentkit):</div>
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
                        <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 0.35rem; font-weight: 600;">Exemplo em Python (cdp-agentkit-core):</div>
                        <pre class="code-block" style="margin-bottom: 0.75rem;">from cdp_agentkit_core.actions import CdpActionProvider
from integrations.agentkit.grid402_action_provider import Grid402ActionProvider

# Conecta o provedor com suporte a sandbox ou liquidacao on-chain automatica
grid402_actions = Grid402ActionProvider(sandbox=False)</pre>
                    </div>

                    <div style="background: rgba(0, 0, 0, 0.3); border-radius: 6px; padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                        <strong style="color: #fff;">📂 Documentação Completa e Código-Fonte:</strong>
                        <p style="margin: 0.4rem 0 0 0;">Consulte o diretório <code>integrations/agentkit/</code> no repositório para implementações prontas com LangChain, CrewAI e guias de configuração.</p>
                    </div>
                </div>
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
print("Registros A:", dns_records["records"]["A"])

# 4. Modo Sandbox gratuito para testes locais e CI/CD
# Passe o cabeçalho 'X-Payment: demo' nas requisições sem gastar USDC</div>
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
console.log('Expira em dias:', ssl.certificate.days_remaining);

// 3. Modo Sandbox gratuito para testes automatizados:
// Envie headers: { 'X-Payment': 'demo' } para simular sem custo</div>
            </div>

            <!-- Tab 4: cURL -->
            <div id="tab-curl" class="tab-content">
                <div class="code-block"># 1. Resumo gratuito
curl -i https://www.grid402.com.br/status

# 2. Desafio HTTP 402 com Amostra Cadastral
curl -i "https://www.grid402.com.br/api/v1/cnpj?cnpj=00000000000191"

# 3. Requisição liquidada na Base Mainnet
curl "https://www.grid402.com.br/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: 0x&lt;tx_hash_na_base&gt;"

# 4. Modo Sandbox gratuito para testes e CI/CD (20 req/hora por IP)
curl "https://www.grid402.com.br/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: demo"</div>
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
            <div>
                <div>Grid402 // Open Data e Utility Hub • Base Mainnet</div>
                <div style="margin-top: 4px; font-size: 0.78rem; color: #64748B;">
                    © <?php echo date('Y'); ?> Grid402. Todos os direitos reservados. Licenciado sob Licença MIT.
                </div>
            </div>
            <div>
                <a href="/about">About</a> • 
                <a href="/contact">Contact</a> • 
                <a href="/terms" onclick="openLegalModal('terms'); return false;">Terms of Use</a> • 
                <a href="/privacy" onclick="openLegalModal('privacy'); return false;">Privacy (LGPD)</a> • 
                <a href="/openapi.json" target="_blank">OpenAPI 3.1</a> • 
                <a href="/llms.txt" target="_blank">llms.txt</a> • 
                <span>Taxa: US$ <?php echo GRID402_PRICE_DISPLAY; ?> / consulta</span>
            </div>
            <div class="footer-legal">
                <p><strong>Direitos Autorais e Propriedade Intelectual:</strong> © <?php echo date('Y'); ?> Grid402. O software, arquitetura do servidor MCP e documentações são de titularidade do Grid402 e disponibilizados sob a Licença MIT. Os dados técnicos retornados provêm de registros públicos governamentais abertos, protocolos livres da internet (DNS, BGP/ASN, TLS/SSL) e fontes de domínio público, mantendo-se livres de qualquer exclusividade proprietária conforme a legislação vigente.</p>
                <p style="margin-top: 0.5rem;"><strong>Isenção de Responsabilidade e Dados Públicos:</strong> O Grid402 é um hub utilitário de acesso a dados de domínio público e protocolos abertos de rede fornecido 'NO ESTADO EM QUE SE ENCONTRA' (AS-IS). Não tratamos dados pessoais sensíveis (PII) e operamos em estrita observância à Lei Geral de Proteção de Dados (Lei nº 13.709/2018) e ao Marco Civil da Internet (Lei nº 12.965/2014).</p>
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
                    <button id="modal-tab-privacy-btn" class="modal-tab-btn" onclick="switchLegalModal('privacy')">Privacidade e LGPD</button>
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
                        <span class="modal-title">Pagamento Web3 (Base Mainnet)</span>
                    </div>
                    <button class="modal-close" onclick="closeWeb3Modal()">✕</button>
                </div>
                <div class="modal-body" style="padding: 1.25rem 1.5rem;">
                    <!-- Aviso se nenhuma carteira EVM foi detectada -->
                    <div id="web3-no-wallet-banner" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: #fde68a; display: flex; align-items: flex-start; gap: 10px;">
                        <span style="font-size: 1.2rem; line-height: 1;">ℹ️</span>
                        <div>
                            <strong>Nenhuma extensão Web3 ativa detectada nesta aba</strong><br>
                            Para pagar com 1 clique direto na página, é necessário ter a extensão MetaMask, Rabby ou Coinbase Wallet instalada e ativa. Ou pague pelo celular via QR Code abaixo.
                        </div>
                    </div>

                    <!-- Duas opções visuais -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <!-- Coluna 1: Celular / QR Code -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.5rem; font-size: 0.9rem;">📱 Pagar pelo Celular</div>
                                <div style="background: #fff; padding: 8px; border-radius: 8px; display: inline-block; margin-bottom: 0.6rem;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?data=0xa31c31069c43ac1c0075a93c325b0dcd7744356c" alt="QR Code Carteira Oraculo" style="display: block; width: 130px; height: 130px;" />
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                                    Escaneie pelo app da MetaMask, Rainbow ou Coinbase Wallet e envie <strong><span id="web3-modal-price">0.0005</span> USDC</strong> na rede <strong>Base</strong>.
                                </div>
                            </div>
                        </div>

                        <!-- Coluna 2: Instalação / Extensão no PC -->
                        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.5rem; font-size: 0.9rem;">💻 Extensão no Navegador</div>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5;">
                                    Instale a MetaMask para autorizar micropagamentos de USDC com apenas 1 clique em qualquer requisição.
                                </p>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <a href="https://metamask.io/download/" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="justify-content: center; font-size: 0.85rem; padding: 0.6rem 0.8rem; text-decoration: none;">
                                    🦊 Instalar MetaMask
                                </a>
                                <button class="btn btn-outline" onclick="location.reload()" style="justify-content: center; font-size: 0.8rem; padding: 0.5rem;">
                                    🔄 Já instalei, recarregar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Dados Técnicos da Transferência -->
                    <div style="background: #030712; border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1rem; font-size: 0.8rem; font-family: var(--font-mono); margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);">Rede:</span>
                            <span style="color: #38bdf8; font-weight: 600;">Base Mainnet (Chain ID 8453)</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);">Token:</span>
                            <span style="color: #fff;">USDC</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);">Valor da Consulta:</span>
                            <span style="color: var(--primary); font-weight: 700;"><span id="web3-modal-price-detail">0.0005</span> USDC</span>
                        </div>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(255, 255, 255, 0.1);">
                            <div style="color: var(--text-muted); margin-bottom: 4px; font-size: 0.75rem;">Endereço de Destino (Carteira do Oráculo):</div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" id="web3-wallet-dest-input" readonly value="<?php echo GRID402_WALLET_ADDRESS; ?>" style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); color: #fff; padding: 6px 8px; border-radius: 6px; font-size: 0.75rem; font-family: var(--font-mono);" />
                                <button type="button" class="btn-sm" onclick="copyOracleAddress()" id="btn-copy-oracle" style="font-size: 0.75rem; padding: 6px 10px; white-space: nowrap;">📋 Copiar</button>
                            </div>
                        </div>
                    </div>

                    <!-- Inserção rápida de Hash para validação imediata -->
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #fff; margin-bottom: 6px;">
                            Já enviou? Cole o hash da transação para desbloquear os dados:
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="web3-hash-direct-input" class="console-input" style="flex: 1; font-size: 0.8rem;" placeholder="0x... (Hash da transação gerado na Base)" />
                            <button class="btn btn-primary" onclick="submitModalPaymentHash()" style="font-size: 0.8rem; padding: 0.6rem 1rem; white-space: nowrap;">
                                ⚡ Desbloquear Consulta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao Sobre o Projeto -->
        <section id="about" class="info-section">
            <h2>Sobre o Grid402</h2>
            <p>O <strong>Grid402</strong> e uma infraestrutura pública de oraculo de dados e utilitarios tecnicos de rede desenhada especificamente para a Internet dos Agentes (Agentic Web) e sistemas autônomos Machine-to-Machine (M2M).</p>
            <p>Tradicionalmente, consultar dados oficiais de empresas, enderecamento postal ou status de infraestrutura exige cadastro previo, cartoes de credito internacionais, aprovacao de tokens e chaves estaticas de API com risco de vazamento. O Grid402 elimina toda a friccao burocratica adotando o padrao nativo da web <strong>HTTP 402 Payment Required</strong> com liquidacao instantanea de micropagamentos em USDC na rede Base Mainnet.</p>
            <p>Todo o ecossistema opera de maneira estritamente Zero-DB e Stateless, processando apenas informacoes de dominio publico com transparencia computacional, codigo aberto sob Licenca MIT e total conformidade com a legislacao brasileira (LGPD e Marco Civil da Internet).</p>
        </section>

        <!-- Secao de Contato e Canais Oficiais -->
        <section id="contact" class="info-section">
            <h2>Contato e Canais Oficiais</h2>
            <p>Disponibilizamos canais diretos de suporte tecnico, integracao para desenvolvedores de IA e governanca de dados:</p>
            <div class="contact-grid">
                <div class="contact-card">
                    <strong>Suporte Tecnico e Geral</strong>
                    <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a>
                </div>
                <div class="contact-card">
                    <strong>Encarregado de Dados (DPO)</strong>
                    <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a>
                </div>
                <div class="contact-card">
                    <strong>Central Telefonica</strong>
                    <a href="tel:+5511958325848">+55 11 95832-5848</a>
                </div>
                <div class="contact-card">
                    <strong>Codigo Fonte no GitHub</strong>
                    <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a>
                </div>
                <div class="contact-card">
                    <strong>Pacote npm (Node.js)</strong>
                    <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402</a>
                </div>
                <div class="contact-card">
                    <strong>Pacote PyPI (Python)</strong>
                    <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">pypi.org/project/grid402</a>
                </div>
                <div class="contact-card">
                    <strong>Servidor MCP no npm</strong>
                    <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npmjs.com/package/grid402-mcp</a>
                </div>
                <div class="contact-card">
                    <strong>Carteira Oraculo na Base</strong>
                    <a href="https://basescan.org/address/0xa31c31069c43ac1c0075a93c325b0dcd7744356c" target="_blank" rel="noopener noreferrer">0xa31c...356c</a>
                </div>
            </div>
        </section>

        <!-- Rodapé Semântico -->
        <footer>
            <div class="footer-links">
                <a href="/about">About</a>
                <a href="/contact">Contact</a>
                <a href="/terms" target="_blank">Terms of Service</a>
                <a href="/privacy" target="_blank">Privacy Policy</a>
                <a href="/llms.txt" target="_blank">llms.txt</a>
                <a href="/openapi.json" target="_blank">OpenAPI 3.1</a>
                <a href="/.well-known/mcp/server-card.json" target="_blank">MCP Server Card</a>
                <a href="mailto:contato@grid402.com.br">contato@grid402.com.br</a>
                <a href="tel:+5511958325848">+55 11 95832-5848</a>
            </div>
            <div>
                Copyright (c) 2026 Grid402 Contributors. Distribuido sob os termos da Licenca MIT.
            </div>
        </footer>
    </div>

    <script>
        // ==========================================
        // Grid402: Playground e Verificador de Oraculo
        // ==========================================
        const playgroundPresets = {
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
            }
        };

        let currentPlaygroundPreset = 'workdays';
        let currentPlaygroundMode = 'sandbox';
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

            const badgeEl = document.getElementById('playground-service-badge');
            if (badgeEl) badgeEl.textContent = key;

            const descEl = document.getElementById('playground-service-desc');
            if (descEl) descEl.textContent = p.desc;

            const inputsEl = document.getElementById('playground-params-inputs');
            if (inputsEl) {
                inputsEl.innerHTML = '';
                if (p.params.length === 0) {
                    const noParams = document.createElement('div');
                    noParams.style.fontSize = '0.8rem';
                    noParams.style.color = 'var(--text-muted)';
                    noParams.style.fontStyle = 'italic';
                    noParams.textContent = 'Este servico nao requer parametros obrigatorios na consulta basica.';
                    inputsEl.appendChild(noParams);
                } else {
                    p.params.forEach(param => {
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

            if (mode === 'manual') {
                if (presetControls) presetControls.style.display = 'none';
                if (manualControls) manualControls.style.display = 'flex';
                if (colTitle) colTitle.textContent = 'Auditoria Manual de JSON';
                if (runBtn) runBtn.textContent = '🔍 Auditar JSON';
            } else {
                if (presetControls) presetControls.style.display = 'block';
                if (manualControls) manualControls.style.display = 'none';
                if (colTitle) colTitle.textContent = 'Configuracao da Consulta';
                if (runBtn) runBtn.textContent = '⚡ Executar Consulta';
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

            if (statusBadge) statusBadge.textContent = 'Enviando...';
            if (latencyTag) latencyTag.style.display = 'none';
            if (viewer) viewer.textContent = '// Aguardando resposta do hub Grid402...';
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
                        renderNonProofState('Sem bloco _oracle_proof na resposta.');
                    }
                } else if (response.status === 402) {
                    if (statusBadge) statusBadge.textContent = 'HTTP 402 Payment Required';
                    render402ChallengeCard(data);
                } else {
                    if (statusBadge) statusBadge.textContent = 'HTTP ' + response.status;
                    renderNonProofState('Resposta com codigo de erro ' + response.status);
                }
            } catch (err) {
                if (statusBadge) statusBadge.textContent = 'Erro de Conexao';
                if (viewer) viewer.textContent = '// Erro ao conectar ao Grid402: ' + (err.message || err);
                renderNonProofState('Nao foi possivel completar a requisicao.');
            } finally {
                if (runBtn) runBtn.disabled = false;
            }
        }

        async function auditOracleProof(payload) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-pending';
                overallBadge.textContent = 'ANALISANDO PROVA...';
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
                renderAuditFailure('Erro ao validar no motor de verificacao: ' + err.message);
            }
        }

        function renderAuditSteps(result, clientLatencyMs) {
            const card = document.getElementById('playground-audit-card');
            const overallBadge = document.getElementById('audit-overall-badge');
            const engineBadge = document.getElementById('audit-engine-badge');

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
                    overallBadge.textContent = '✓ PROVA MATEMATICA AUTENTICA';
                }

                // Etapa 1
                if (s1Icon) { s1Icon.className = 'audit-step-icon icon-valid'; s1Icon.textContent = '✓'; }
                if (s1Status) { s1Status.textContent = 'Integro (SHA-256)'; s1Status.style.color = 'var(--primary)'; }
                if (s1Meta) {
                    s1Meta.style.display = 'block';
                    const h = (result.payload_hash || (payload && payload._oracle_proof && payload._oracle_proof.data_hash) || 'Conferido com sucesso');
                    s1Meta.textContent = 'Payload Hash: ' + h;
                }

                // Etapa 2
                if (s2Icon) { s2Icon.className = 'audit-step-icon icon-valid'; s2Icon.textContent = '✓'; }
                if (s2Status) { s2Status.textContent = 'Oficial (Base Mainnet)'; s2Status.style.color = 'var(--primary)'; }
                if (s2Meta) {
                    s2Meta.style.display = 'block';
                    const addr = result.oracle_address || '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
                    s2Meta.innerHTML = 'Carteira: <a href="https://basescan.org/address/' + addr + '" target="_blank" rel="noopener noreferrer">' + addr + '</a> (BaseScan)';
                }

                // Etapa 3
                if (s3Icon) { s3Icon.className = 'audit-step-icon icon-valid'; s3Icon.textContent = '✓'; }
                if (s3Status) { s3Status.textContent = 'Valida (ECDSA secp256k1)'; s3Status.style.color = 'var(--primary)'; }
                if (s3Meta) {
                    s3Meta.style.display = 'block';
                    s3Meta.textContent = 'Algoritmo: ECDSA_SHA256_SECP256K1 | Verificacao concluida em ' + verifyTime + ' ms sem banco de dados.';
                }
            } else {
                if (card) {
                    card.classList.remove('valid');
                    card.classList.add('invalid');
                }
                if (overallBadge) {
                    overallBadge.className = 'audit-badge badge-invalid';
                    overallBadge.textContent = '✕ PROVA INVALIDA OU ADULTERADA';
                }

                const errCode = (result && result.error) ? result.error : 'InvalidSignature';

                if (errCode === 'DataHashMismatch') {
                    // Etapa 1 falhou
                    if (s1Icon) { s1Icon.className = 'audit-step-icon icon-invalid'; s1Icon.textContent = '✕'; }
                    if (s1Status) { s1Status.textContent = 'Hash Divergente'; s1Status.style.color = 'var(--danger)'; }
                    if (s1Meta) {
                        s1Meta.style.display = 'block';
                        s1Meta.innerHTML = '<span style="color:var(--danger);">Alerta de Adulteracao:</span> Os dados foram modificados apos a assinatura.<br>Esperado: ' + (result.expected_hash || 'N/A') + '<br>Recebido: ' + (result.received_hash || 'N/A');
                    }

                    if (s2Icon) { s2Icon.className = 'audit-step-icon icon-pending'; s2Icon.textContent = '2'; }
                    if (s2Status) { s2Status.textContent = 'Interrompido'; s2Status.style.color = 'var(--text-muted)'; }
                    if (s2Meta) s2Meta.style.display = 'none';

                    if (s3Icon) { s3Icon.className = 'audit-step-icon icon-pending'; s3Icon.textContent = '3'; }
                    if (s3Status) { s3Status.textContent = 'Interrompido'; s3Status.style.color = 'var(--text-muted)'; }
                    if (s3Meta) s3Meta.style.display = 'none';
                } else {
                    // Outro erro
                    if (s1Icon) { s1Icon.className = 'audit-step-icon icon-invalid'; s1Icon.textContent = '✕'; }
                    if (s1Status) { s1Status.textContent = 'Falha'; s1Status.style.color = 'var(--danger)'; }
                    if (s1Meta) {
                        s1Meta.style.display = 'block';
                        s1Meta.textContent = (result && result.message) ? result.message : 'Assinatura nao reconhecida.';
                    }

                    if (s2Icon) { s2Icon.className = 'audit-step-icon icon-invalid'; s2Icon.textContent = '✕'; }
                    if (s2Status) { s2Status.textContent = 'Rejeitado'; s2Status.style.color = 'var(--danger)'; }
                    if (s2Meta) s2Meta.style.display = 'none';

                    if (s3Icon) { s3Icon.className = 'audit-step-icon icon-invalid'; s3Icon.textContent = '✕'; }
                    if (s3Status) { s3Status.textContent = 'Rejeitado'; s3Status.style.color = 'var(--danger)'; }
                    if (s3Meta) s3Meta.style.display = 'none';
                }
            }
        }

        function render402ChallengeCard(data) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';
            card.classList.remove('valid', 'invalid');

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-pending';
                overallBadge.textContent = '🔒 DESAFIO x402 RECEBIDO';
            }

            const engineBadge = document.getElementById('audit-engine-badge');
            if (engineBadge) {
                engineBadge.textContent = 'Protocolo x402 • Base Mainnet';
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
            if (s1Status) { s1Status.textContent = 'Amostra Previa (Teaser)'; s1Status.style.color = 'var(--accent)'; }
            if (s1Meta) {
                s1Meta.style.display = 'block';
                s1Meta.textContent = 'A resposta 402 inclui dados preliminares gratuitos para o agente inspecionar a qualidade antes da compra.';
            }

            if (s2Icon) { s2Icon.className = 'audit-step-icon icon-pending'; s2Icon.textContent = '2'; }
            if (s2Status) { s2Status.textContent = 'Destinatario e Preco'; s2Status.style.color = 'var(--accent)'; }
            if (s2Meta) {
                s2Meta.style.display = 'block';
                const price = (data && data.price_usdc) ? data.price_usdc : '0.0005';
                const dest = (data && data.payment_address) ? data.payment_address : '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
                s2Meta.innerHTML = 'Preco: ' + price + ' USDC | Destino: <a href="https://basescan.org/address/' + dest + '" target="_blank" rel="noopener noreferrer">' + dest + '</a>';
            }

            if (s3Icon) { s3Icon.className = 'audit-step-icon icon-pending'; s3Icon.textContent = '3'; }
            if (s3Status) { s3Status.textContent = 'Prova Emitida Apos Pagamento'; s3Status.style.color = 'var(--accent)'; }
            if (s3Meta) {
                s3Meta.style.display = 'block';
                s3Meta.textContent = 'Ao enviar o comprovante X-Payment (ou no modo Sandbox demo), a atestacao criptografica e gerada automaticamente no HTTP 200.';
            }
        }

        function renderNonProofState(message) {
            const card = document.getElementById('playground-audit-card');
            if (!card) return;
            card.style.display = 'block';
            card.classList.remove('valid', 'invalid');

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-pending';
                overallBadge.textContent = 'SEM PROVA CRIPTOGRAFICA';
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

            const overallBadge = document.getElementById('audit-overall-badge');
            if (overallBadge) {
                overallBadge.className = 'audit-badge badge-invalid';
                overallBadge.textContent = '✕ ERRO NA AUDITORIA';
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
            currentServiceAction = action;
            const out = document.getElementById('console-output');
            const statusText = document.getElementById('console-status-text');
            const badge = document.getElementById('console-latency-badge');
            const teaserCard = document.getElementById('console-teaser-card');
            if (teaserCard) teaserCard.style.display = 'none';

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
                case 'cnpj': url = '/api/v1/cnpj?cnpj=00000000000191'; break;
                case 'indicators': url = '/api/v1/indicators'; break;
                case 'holidays': url = '/api/v1/holidays?year=2026'; break;
                case 'workdays': url = '/api/v1/workdays?start_date=2026-12-23' + String.fromCharCode(38) + 'end_date=2026-12-28'; break;
                case 'paid':
                    const tx = document.getElementById('tx-input').value.trim();
                    if (!tx) {
                        out.textContent = 'Por favor, insira o hash da transação na Base no campo acima.';
                        return;
                    }
                    headers['X-Payment'] = tx;
                    if (currentServiceAction === 'cnpj') url = '/api/v1/cnpj?cnpj=00000000000191';
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

                statusText.textContent = `HTTP ${res.status} ${res.status === 200 ? 'OK' : (res.status === 402 ? 'Payment Required (Desafio x402)' : 'Error')}`;
                badge.style.display = 'inline-block';
                badge.textContent = `${duration} ms`;

                if (res.status === 402 && json.preview && teaserCard) {
                    teaserCard.style.display = 'block';
                    const teaserTitle = document.getElementById('teaser-title');
                    const teaserPrice = document.getElementById('teaser-badge-price');
                    const teaserContent = document.getElementById('teaser-content');
                    const teaserLocked = document.getElementById('teaser-locked-fields');

                    teaserTitle.textContent = (action === 'cnpj') ? 'Dados Cadastrais da Empresa (Prévia)' : (action === 'indicators' ? 'Indicadores Econômicos Oficiais (Prévia)' : (action === 'holidays' ? 'Próximo Feriado Nacional (Prévia)' : 'Localidade Postal (Prévia)'));
                    teaserPrice.textContent = `${json.price_usdc || '0.0005'} USDC na Base`;

                    let html = '';
                    for (const [key, val] of Object.entries(json.preview)) {
                        const label = key.replace(/_/g, ' ').toUpperCase();
                        html += `<div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px;">
                            <span style="display: block; font-size: 0.7rem; color: var(--text-muted);">${label}</span>
                            <span style="font-weight: 600; color: #fff;">${val || 'N/A'}</span>
                        </div>`;
                    }
                    teaserContent.innerHTML = html;

                    if (json.locked_fields && Array.isArray(json.locked_fields)) {
                        teaserLocked.textContent = json.locked_fields.join(', ');
                    } else {
                        teaserLocked.textContent = 'Dados completos desbloqueados mediante liquidação.';
                    }
                } else if (teaserCard) {
                    teaserCard.style.display = 'none';
                }

                out.textContent = JSON.stringify(json, null, 2);
            } catch (err) {
                statusText.textContent = 'Falha na requisição';
                out.textContent = err.message;
            }
        }

        async function payWithWeb3Wallet() {
            const btn = document.getElementById('btn-web3-pay');
            const statusText = document.getElementById('console-status-text');
            const out = document.getElementById('console-output');

            if (!window.ethereum) {
                openWeb3Modal();
                return;
            }

            try {
                btn.disabled = true;
                btn.textContent = 'Conectando carteira...';

                // 1. Solicita conexão de contas
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                if (!accounts || accounts.length === 0) {
                    throw new Error('Nenhuma conta foi conectada.');
                }
                const userAddress = accounts[0];

                // 2. Garante que está na rede Base Mainnet (Chain ID 8453 = 0x2105)
                btn.textContent = 'Verificando rede Base...';
                try {
                    await window.ethereum.request({
                        method: 'wallet_switchEthereumChain',
                        params: [{ chainId: '0x2105' }],
                    });
                } catch (switchError) {
                    if (switchError.code === 4902) {
                        await window.ethereum.request({
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
                const isCnpj = (currentServiceAction === 'cnpj');
                const amountRaw = isCnpj ? 2000 : 500; // 0.0020 USDC ou 0.0005 USDC
                const priceUsdc = isCnpj ? '0.0020' : '0.0005';
                const recipient = '<?php echo GRID402_WALLET_ADDRESS; ?>'.toLowerCase().replace('0x', '');
                const usdcContract = '<?php echo GRID402_USDC_CONTRACT; ?>';

                // Function selector: transfer(address,uint256) -> 0xa9059cbb
                const recipientPadded = recipient.padStart(64, '0');
                const amountHex = amountRaw.toString(16).padStart(64, '0');
                const data = '0xa9059cbb' + recipientPadded + amountHex;

                btn.textContent = `Aguardando assinatura de ${priceUsdc} USDC...`;
                out.textContent = `Solicitando aprovação na carteira para transferência de ${priceUsdc} USDC na Base para o oráculo...\nCarteira destino: 0x${recipient}`;

                const txHash = await window.ethereum.request({
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

                btn.textContent = 'Desbloqueando dados...';
                await new Promise(r => setTimeout(r, 3500));

                await runConsole('paid');
                btn.textContent = '⚡ Pagar com Carteira Web3 (1 Clique)';
                btn.disabled = false;
            } catch (err) {
                btn.disabled = false;
                btn.textContent = '⚡ Pagar com Carteira Web3 (1 Clique)';
                out.textContent = `Falha na transação Web3: ${err.message || err}`;
                statusText.textContent = 'Operação cancelada ou falhou';
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
                <h3>4. Direitos Autorais e Licenciamento</h3>
                <p>O software Grid402, suas bibliotecas de integração (TypeScript, Python, PHP) e especificações de ferramentas MCP são licenciados sob os termos da <strong>Licença MIT</strong>. As bases de dados consultadas pertencem ao domínio público ou a registros oficiais abertos (Receita Federal, Banco Central do Brasil, ICANN, Registro.br), inexistindo apropriação ou reserva proprietária sobre fatos e registros públicos.</p>
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
                title.textContent = 'Política de Privacidade e LGPD';
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

        // Modal de Pagamento Web3
        function openWeb3Modal() {
            const isCnpj = (currentServiceAction === 'cnpj');
            const price = isCnpj ? '0.0020' : '0.0005';
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
</body>
</html>
