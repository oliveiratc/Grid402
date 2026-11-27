<?php
/**
 * Grid402 // Landing Pages SEO de Alta Intenção em PT-BR (100% SSR)
 *
 * Rotas suportadas via .htaccess e router de testes:
 * - /api-cnpj
 * - /api-sancoes-cgu
 * - /api-dias-uteis-anbima
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
if ($slug === '') {
    $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    $slug = strtolower($path);
}

$domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';

$pages = [
    'api-cnpj' => [
        'path' => '/api-cnpj',
        'title' => 'API de Consulta CNPJ Receita Federal via Pix e Zero Mensalidade | Grid402',
        'description' => 'Consulte dados cadastrais de CNPJ na Receita Federal via API REST rápida (<15ms), Zero-PII (LGPD), sem mensalidade e sem contrato. Pague via Pix a partir de R$ 12 (400 créditos) ou x402 USDC.',
        'badge' => 'Receita Federal · Zero-PII (LGPD Art. 1º) · Assinatura ECDSA',
        'h1' => 'API de Consulta CNPJ da Receita Federal sem Mensalidade e com Pix',
        'lead' => 'Enriqueça cadastros B2B, validações de onboarding e fluxos no n8n com dados oficiais de Pessoa Jurídica (razão social, situação cadastral, CNAE, capital social e endereço) pagando apenas pelo uso — a partir de R$ 0,06 por consulta no Starter Pass Pix.',
        'endpoint' => '/api/v1/cnpj?cnpj=00000000000191',
        'price_usdc' => '0.0100 USDC',
        'credits' => '2 créditos (200 consultas por R$ 12 no Starter Pass)',
        'curl_example' => "curl -sL \"https://grid402.online/api/v1/cnpj?cnpj=00000000000191\" \\\n  -H \"X-Payment: pass_g402_SEU_TOKEN\"",
        'python_example' => "import requests\n\nresp = requests.get(\n    \"https://grid402.online/api/v1/cnpj\",\n    params={\"cnpj\": \"00000000000191\"},\n    headers={\"X-Payment\": \"pass_g402_SEU_TOKEN\"},\n    timeout=10,\n)\ndados = resp.json()\nprint(dados[\"razao_social\"], dados[\"situacao_cadastral\"], dados[\"cnae_fiscal\"])",
        'n8n_example' => "Node: HTTP Request (GET)\nURL: https://grid402.online/api/v1/cnpj?cnpj={{ \$json.cnpj }}\nHeader Name: X-Payment\nHeader Value: pass_g402_SEU_TOKEN\nRetorno: JSON estruturado + _oracle_proof pronto para Switch/IF",
        'traditional_cost' => 'R$ 300 a R$ 1.200/mês (franquia mínima + fidelidade 12 meses)',
        'grid_cost' => 'R$ 12,00 no Pix = 400 créditos (200 consultas CNPJ ou 40 Dossiês 360º)',
        'features' => [
            'Validação matemática prévia dos dígitos verificadores (rejeita CNPJ inválido com HTTP 400 sem cobrar créditos).',
            'Conformidade rigorosa LGPD Art. 1º (Zero-PII): descarta na origem CPF de sócios e contatos pessoais.',
            'Assinatura criptográfica ECDSA secp256k1 (_oracle_proof) auditável offline em POST /api/v1/verify.',
            'Compatível com Pix (Mercado Pago), Cartão, n8n, Python, Node.js, MCP e x402 USDC (Base, Polygon, Arbitrum).',
        ],
        'faq' => [
            [
                'q' => 'Quanto custa consultar um CNPJ na API Grid402?',
                'a' => 'Cada consulta em /api/v1/cnpj custa 2 créditos (0.0100 USDC). No Starter Pass de R$ 12 via Pix (400 créditos), você faz 200 consultas CNPJ (R$ 0,06 cada) sem mensalidade e sem expiração antecipada dentro de 180 dias.',
            ],
            [
                'q' => 'Preciso criar conta ou assinar contrato comercial?',
                'a' => 'Não. Basta gerar um Pix de R$ 12 em /pass?tier=starter&method=pix e receber na hora o token pass_g402_... para enviar no cabeçalho X-Payment.',
            ],
            [
                'q' => 'Posso testar gratuitamente antes de pagar?',
                'a' => 'Sim. Você pode usar o cabeçalho X-Payment: demo no Modo Sandbox (até 3 consultas por dia por IP) ou testar a prévia gratuita via HTTP 402 sem custo.',
            ],
        ],
    ],
    'api-sancoes-cgu' => [
        'path' => '/api-sancoes-cgu',
        'title' => 'API de Consulta Sanções CGU (CEIS e CNEP) em Tempo Real | Grid402',
        'description' => 'Consulte automaticamente se um CNPJ possui punições ativas nos cadastros CEIS e CNEP da Controladoria-Geral da União (CGU) via API REST. Sem mensalidade: Pix R$ 12 = 200 consultas.',
        'badge' => 'CGU · CEIS e CNEP · Lei Anticorrupção 12.846/2013',
        'h1' => 'API de Consulta de Sanções CGU (CEIS e CNEP) para Compliance e KYB',
        'lead' => 'Automatize a homologação de fornecedores, compras B2B e due diligence anticorrupção consultando CEIS (Empresas Inidôneas e Suspensas) e CNEP (Empresas Punidas) em milissegundos, pagando apenas R$ 0,06 por consulta no Pix.',
        'endpoint' => '/api/v1/sanctions?cnpj=00000000000191',
        'price_usdc' => '0.0100 USDC',
        'credits' => '2 créditos (200 consultas por R$ 12 no Starter Pass)',
        'curl_example' => "curl -sL \"https://grid402.online/api/v1/sanctions?cnpj=00000000000191\" \\\n  -H \"X-Payment: pass_g402_SEU_TOKEN\"",
        'python_example' => "import requests\n\nresp = requests.get(\n    \"https://grid402.online/api/v1/sanctions\",\n    params={\"cnpj\": \"00000000000191\"},\n    headers={\"X-Payment\": \"pass_g402_SEU_TOKEN\"},\n    timeout=10,\n)\naudit = resp.json()\nprint(\"Possui sanções?\", audit[\"has_sanctions\"], \"Total:\", audit[\"total_sanctions\"])",
        'n8n_example' => "Node: HTTP Request (GET)\nURL: https://grid402.online/api/v1/sanctions?cnpj={{ \$json.cnpj }}\nHeader Name: X-Payment\nHeader Value: pass_g402_SEU_TOKEN\nDica: Para CNPJ + Simples + CGU + Veredito IA em 1 chamada, use /api/v1/dossier",
        'traditional_cost' => 'R$ 500 a R$ 2.500/mês em plataformas de background check corporativo',
        'grid_cost' => 'R$ 12,00 no Pix = 400 créditos (200 consultas CEIS/CNEP ou 40 Dossiês 360º)',
        'features' => [
            'Consulta unificada aos cadastros federais CEIS e CNEP com normalização JSON pronta para decisão automatizada.',
            'Comprovante criptográfico ECDSA secp256k1 (_oracle_proof) ideal para trilha de auditoria interna e governança.',
            'Integração direta com o Dossiê 360º KYB (/api/v1/dossier e /dossie-kyb) quando precisar de relatório PDF completo.',
            'Suporte a Idempotency-Key (300s) e Spend Controls por token (limite diário e whitelist de rotas).',
        ],
        'faq' => [
            [
                'q' => 'Quais cadastros da CGU a rota /api/v1/sanctions consulta?',
                'a' => 'A API consulta CEIS (Cadastro Nacional de Empresas Inidôneas e Suspensas) e CNEP (Cadastro Nacional de Empresas Punidas) sob a Lei 12.846/2013.',
            ],
            [
                'q' => 'Qual é a diferença entre /api/v1/sanctions e /api/v1/dossier?',
                'a' => '/api/v1/sanctions custa 2 créditos (0.0100 USDC) e foca estritamente em CEIS/CNEP. Já /api/v1/dossier custa 10 créditos (0.0500 USDC) e agrega Receita Federal CNPJ, Simples/MEI, CGU CEIS/CNEP e Veredito determinístico (APPROVED/FLAGGED/REJECTED).',
            ],
            [
                'q' => 'Como funciona a prova de auditoria para compliance?',
                'a' => 'Toda resposta HTTP 200 inclui o bloco _meta (fonte oficial e timestamp UTC) e _oracle_proof (assinatura ECDSA secp256k1), permitindo provar quando e qual retorno foi obtido.',
            ],
        ],
    ],
    'api-dias-uteis-anbima' => [
        'path' => '/api-dias-uteis-anbima',
        'title' => 'API de Dias Úteis ANBIMA e Feriados Bancários (D+N) | Grid402',
        'description' => 'Calcule dias úteis bancários na convenção ANBIMA/Febraban, projeções D+N e vencimentos de boletos e renda fixa via API REST determinística (<15ms). Pix R$ 12 = 400 consultas.',
        'badge' => 'Convenção ANBIMA / Febraban · Cálculo Determinístico <15ms',
        'h1' => 'API de Dias Úteis ANBIMA e Calendário de Feriados Bancários',
        'lead' => 'Elimine tabelas manuais de feriados móveis (Carnaval, Páscoa, Corpus Christi) em sistemas financeiros, ERPs, emissão de boletos e precificação de renda fixa. Até 400 chamadas por R$ 12 via Pix (R$ 0,03 por cálculo).',
        'endpoint' => '/api/v1/workdays?start_date=2026-12-23&end_date=2026-12-28',
        'price_usdc' => '0.0050 USDC',
        'credits' => '1 crédito (400 cálculos por R$ 12 no Starter Pass)',
        'curl_example' => "curl -sL \"https://grid402.online/api/v1/workdays?start_date=2026-12-23&days=5\" \\\n  -H \"X-Payment: pass_g402_SEU_TOKEN\"",
        'python_example' => "import requests\n\nresp = requests.get(\n    \"https://grid402.online/api/v1/workdays\",\n    params={\"start_date\": \"2026-12-23\", \"days\": 5},\n    headers={\"X-Payment\": \"pass_g402_SEU_TOKEN\"},\n    timeout=10,\n)\ncalc = resp.json()\nprint(\"Vencimento D+5 ANBIMA:\", calc[\"target_date\"])",
        'n8n_example' => "Node: HTTP Request (GET)\nURL: https://grid402.online/api/v1/workdays?start_date={{ \$json.emissao }}&days=3\nHeader Name: X-Payment\nHeader Value: pass_g402_SEU_TOKEN\nRetorno: target_date, workdays, calendar_days, holidays_in_range",
        'traditional_cost' => 'Manutenção manual de planilhas ANBIMA ou APIs financeiras de R$ 200+/mês',
        'grid_cost' => 'R$ 12,00 no Pix = 400 créditos (400 cálculos de dias úteis ou feriados)',
        'features' => [
            'Suporta 3 modos em uma única rota: contagem entre datas (start_date + end_date), projeção D+N / D-N (start_date + days) e validação pontual (date).',
            'Algoritmo matemático determinístico de feriados móveis (Meeus) cobrindo 1900 a 2100 sem depender de scrapers externos.',
            'Convenção oficial ANBIMA (exclusivo no início, inclusivo no fim) usada em títulos públicos, CDI e liquidação bancária.',
            'Combina com /api/v1/indicators (Selic, CDI, IPCA) e /api/v1/holidays no mesmo Session Pass.',
        ],
        'faq' => [
            [
                'q' => 'A API considera Carnaval e Corpus Christi como feriados bancários?',
                'a' => 'Sim. A convenção ANBIMA/Febraban considera Segunda e Terça de Carnaval, Sexta-Feira Santa, Corpus Christi e todos os feriados nacionais bancários (incluindo Dia da Consciência Negra).',
            ],
            [
                'q' => 'Qual a latência média de resposta?',
                'a' => 'Com Session Pass (X-Payment: pass_g402_...), o cálculo é local e determinístico, respondendo em menos de 15ms sem chamadas externas sujeitas a indisponibilidade.',
            ],
            [
                'q' => 'Posso usar em automações no n8n, Make ou ERP?',
                'a' => 'Sim. Basta um nó HTTP Request GET enviando o cabeçalho X-Payment com seu Session Pass comprado via Pix em /pass.',
            ],
        ],
    ],
    'url-to-markdown-api' => [
        'lang' => 'en',
        'path' => '/url-to-markdown-api',
        'title' => 'SSRF-Safe URL to Clean Markdown API for LLMs & RAG ($0.005/page) | Grid402',
        'description' => 'Convert any public HTTP/HTTPS web page into clean, LLM-ready Markdown with metadata and absolute links. SSRF-hardened, zero headless browser maintenance. Pay via RapidAPI Credit Card ($9.90/mo) or Multi-Chain x402 USDC (Base, Polygon, Arbitrum).',
        'badge' => 'Web-to-Markdown · SSRF-Hardened · LLM & RAG Ready · ECDSA Signed',
        'h1' => 'SSRF-Safe URL to Clean Markdown API for AI Agents & RAG',
        'lead' => 'Turn public web pages into clean, token-efficient Markdown with title, description, word count, and absolute links — without managing Playwright, Puppeteer, or rotating proxies. $0.0050 per page via Multi-Chain x402 USDC or $9.90/mo via Credit Card on RapidAPI.',
        'endpoint' => '/api/v1/extract?url=https://grid402.online&max_chars=12000',
        'price_usdc' => '0.0050 USDC',
        'credits' => '1 credit (400 pages for $2 USDC / 2,500 pages for $9.90/mo on RapidAPI)',
        'rapidapi_url' => 'https://rapidapi.com/oliveiratc/api/grid402-web-to-markdown-email-verify-network-utilities',
        'curl_example' => "curl -sL \"https://grid402.online/api/v1/extract?url=https%3A%2F%2Fexample.com&max_chars=8000\" \\\n  -H \"X-Payment: pass_g402_YOUR_TOKEN\"",
        'python_example' => "from grid402 import Grid402Client\n\nclient = Grid402Client(session_pass=\"pass_g402_YOUR_TOKEN\")\nres = client.extract(\"https://example.com\", max_chars=8000)\nprint(res[\"title\"], res[\"word_count\"])\nprint(res[\"markdown\"][:500])",
        'n8n_example' => "MCP Server (Claude / Cursor / Windsurf):\nnpx -y grid402-mcp  (Tool: grid402_extract)\n\nRapidAPI Credit Card Header:\nX-RapidAPI-Key: YOUR_RAPIDAPI_KEY\nX-RapidAPI-Host: grid402-web-to-markdown-email-verify-network-utilities.p.rapidapi.com",
        'traditional_cost' => '$19 to $83/mo (Firecrawl / ScrapingBee / Browserless subscriptions)',
        'grid_cost' => '$0.0050/page via x402 USDC (Base, Polygon, Arbitrum) or $9.90/mo on RapidAPI',
        'features' => [
            'Strict SSRF defense: blocks localhost, RFC1918 private IPs, link-local cloud metadata (169.254.169.254), and non-HTTP schemes before billing.',
            'Strips scripts, navbars, styles, and boilerplate HTML to save up to 70% of LLM context tokens in RAG pipelines.',
            'Cryptographic ECDSA secp256k1 attestation (_oracle_proof) proving timestamp and exact extracted output.',
            'Zero-config MCP integration (`npx -y grid402-mcp` v2.0.2 with 3 free daily trial queries + Base/Polygon/Arbitrum auto-payer).',
        ],
        'faq' => [
            [
                'q' => 'How much does URL-to-Markdown extraction cost?',
                'a' => 'Each extraction costs $0.0050 USDC (1 credit). You can pay per call with gasless x402 v2 (Base, Polygon, Arbitrum), claim 50 free credits with MCPTRY50 at /pass?promo=MCPTRY50, buy a prepaid Session Pass (400 pages for $2 USDC), or subscribe with Credit Card on RapidAPI ($9.90/mo for 1,500 standard calls).',
            ],
            [
                'q' => 'Does Grid402 store the extracted web pages?',
                'a' => 'No. Processing is strictly stateless and Zero-Retention. Only public HTTP/HTTPS pages are fetched and converted on the fly.',
            ],
            [
                'q' => 'Can AI agents use this directly via Model Context Protocol (MCP)?',
                'a' => 'Yes. Install `npx -y grid402-mcp` and call the `grid402_extract` tool. New users get 3 free trial calls per day automatically.',
            ],
        ],
    ],
    'global-kyb-api' => [
        'lang' => 'en',
        'path' => '/global-kyb-api',
        'title' => 'Global KYB API: GLEIF LEI, OFAC Sanctions & Brazil CNPJ Verification | Grid402',
        'description' => 'Automate global and LatAm corporate KYB with public registries: GLEIF LEI (200+ jurisdictions, CC0 1.0), US OFAC SDN / UN Sanctions, and Brazil Receita Federal CNPJ + CGU Sanctions. GDPR Recital 14 Zero-PII compliant, ECDSA signed.',
        'badge' => 'Global KYB · GLEIF LEI (CC0) · OFAC SDN · Brazil CNPJ · Zero-PII',
        'h1' => 'Global & LatAm KYB API: GLEIF LEI, OFAC Sanctions & Brazil CNPJ',
        'lead' => 'Verify corporate entities across 200+ jurisdictions without $1,000/mo enterprise contracts. Combine official GLEIF LEI, US Treasury OFAC SDN / UN Sanctions, Web3 AML screening, and Brazil Receita Federal + CGU compliance in a single cryptographically signed JSON response.',
        'endpoint' => '/api/v1/kyb-audit?cnpj=00000000000191',
        'price_usdc' => '0.0250 USDC',
        'credits' => '5 credits ($0.0250 per KYB audit / $9.90/mo on RapidAPI)',
        'rapidapi_url' => 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
        'curl_example' => "curl -sL \"https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18\" \\\n  -H \"X-Payment: pass_g402_YOUR_TOKEN\"",
        'python_example' => "from grid402 import Grid402Client\n\nclient = Grid402Client(session_pass=\"pass_g402_YOUR_TOKEN\")\nlei = client.get_lei(\"5493006MHB84DD0ZWV18\")\nofac = client.get_ofac(\"0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90\")\nprint(lei[\"entity\"][\"legal_name\"], ofac[\"risk_level\"])",
        'n8n_example' => "MCP Server (Claude / Cursor / AgentKit):\nnpx -y grid402-mcp  (Tools: grid402_lei, grid402_ofac, grid402_kyb_audit, grid402_get_dossier_360)\n\nRapidAPI Credit Card Hub:\nhttps://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities",
        'traditional_cost' => '$0.50 to $2.00 per KYB check + $500+/mo minimum enterprise commitment',
        'grid_cost' => '$0.0100 per LEI · $0.0200 OFAC · $0.0250 KYB Audit ($0.0500 Dossier 360) via USDC or RapidAPI ($9.90/mo)',
        'features' => [
            '100% Public Corporate Registries & Open Licenses: GLEIF ISO 17442 (CC0 1.0), US OFAC SDN (17 U.S.C. § 105), UN Consolidated Lists, and Brazilian Public Registries (Law 12.527/2011).',
            'Strict GDPR Recital 14 & LGPD Art. 1 Zero-PII compliance: strips individual shareholder CPFs, personal phones, and private emails at ingestion.',
            'Cryptographic audit trail: every response carries _meta (source + UTC timestamp) and _oracle_proof (ECDSA secp256k1 signature verifiable offline).',
            'Multi-Chain x402 v2 EVM settlement (Base, Polygon, Arbitrum USDC) and RapidAPI Credit Card (USD).',
        ],
        'faq' => [
            [
                'q' => 'Are the KYB registries legally compliant for EU and US companies?',
                'a' => 'Yes. Grid402 processes exclusively public-domain legal entity records (corporate data, not natural persons under GDPR Recital 14) and official government sanctions lists, stripping any personal identifiers at ingestion.',
            ],
            [
                'q' => 'Can I pay with a corporate credit card in USD instead of crypto?',
                'a' => 'Yes. Subscribe directly on RapidAPI Hub (PRO $9.90/mo, ULTRA $29.90/mo, or MEGA $79.90/mo) with instant credit card billing.',
            ],
            [
                'q' => 'How can compliance teams prove what the API returned during an audit?',
                'a' => 'Every HTTP 200 payload includes an ECDSA secp256k1 signature (_oracle_proof) over the canonical JSON payload and UTC timestamp, verifiable anytime via POST /api/v1/verify.',
            ],
        ],
    ],
    'zero-pii-email-verification-api' => [
        'lang' => 'en',
        'path' => '/zero-pii-email-verification-api',
        'title' => 'GDPR-Safe Zero-PII B2B Email Verification API (MX, SPF, DMARC) | Grid402',
        'description' => 'Audit B2B email deliverability (RFC syntax, authoritative DNS MX, SPF, DMARC, disposable domain & role-based detection) with 100% Zero-PII and Zero-Retention. Raw emails are never stored on disk. $0.005/check via x402 USDC or RapidAPI.',
        'badge' => 'Zero-PII · Zero-Retention · MX / SPF / DMARC · GDPR Compliant',
        'h1' => 'GDPR-Safe Zero-PII B2B Email Verification API (MX, SPF & DMARC)',
        'lead' => 'Block disposable signups, detect broken mail servers, and score B2B lead deliverability (0–100) without sending raw customer emails to third-party data brokers. Processed strictly in volatile RAM with SHA-256 hashed output only.',
        'endpoint' => '/api/v1/email-verify?email=contact@company.com',
        'price_usdc' => '0.0050 USDC',
        'credits' => '1 credit ($0.0050 per check / 2,500 checks for $9.90/mo on RapidAPI)',
        'rapidapi_url' => 'https://rapidapi.com/oliveiratc/api/grid402-web-to-markdown-email-verify-network-utilities',
        'curl_example' => "curl -sL \"https://grid402.online/api/v1/email-verify?email=dev%40company.com\" \\\n  -H \"X-Payment: pass_g402_YOUR_TOKEN\"",
        'python_example' => "from grid402 import Grid402Client\n\nclient = Grid402Client(session_pass=\"pass_g402_YOUR_TOKEN\")\naudit = client.email_verify(\"dev@company.com\")\nprint(audit[\"verdict\"], audit[\"deliverability_score\"], audit[\"has_mx\"], audit[\"disposable\"])",
        'n8n_example' => "MCP Server (Claude / Cursor / n8n):\nnpx -y grid402-mcp  (Tool: grid402_email_verify)\nn8n Community Node: @oliveiratco1/n8n-nodes-grid402",
        'traditional_cost' => '$16 to $65/mo (ZeroBounce / NeverBounce) with server-side email logging',
        'grid_cost' => '$0.0050/check via x402 USDC (Base, Polygon, Arbitrum) or $9.90/mo on RapidAPI',
        'features' => [
            'True Zero-PII & Zero-Retention: raw email addresses are never written to disk, logs, or databases — responses return only `email_hash_sha256` and domain technical signals.',
            'Full DNS deliverability audit: RFC 5322 syntax, authoritative MX records, SPF policy, DMARC policy, free-provider flag, role-based flag, and disposable domain detection.',
            'Pre-billing validation: malformed email syntax returns HTTP 400 before charging any credit.',
            'Available via REST API, RapidAPI Credit Card ($9.90/mo), Multi-Chain x402 USDC, MCP (`grid402-mcp`), and n8n (`@oliveiratco1/n8n-nodes-grid402`).',
        ],
        'faq' => [
            [
                'q' => 'Does Grid402 log or store the email addresses I verify?',
                'a' => 'Never. The endpoint operates strictly in volatile RAM, never persists the input email in logs or cache, and returns only the irreversible SHA-256 hash alongside technical DNS records (MX, SPF, DMARC).',
            ],
            [
                'q' => 'How is pricing structured for high-volume B2B pipelines?',
                'a' => 'Pay $0.0050 USDC per verification on Base, Polygon, or Arbitrum, claim 50 free credits with MCPTRY50 at /pass?promo=MCPTRY50, buy a prepaid Session Pass (6,000 checks for $20 USDC), or subscribe on RapidAPI Hub starting at $9.90/mo.',
            ],
            [
                'q' => 'Does it detect throwaway/disposable email domains?',
                'a' => 'Yes. Known disposable domains (e.g., Mailinator, GuerrillaMail, 10MinuteMail, YOPmail) are flagged with `disposable: true` and `verdict: RISKY`.',
            ],
        ],
    ],
];

if (!isset($pages[$slug])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>404 | Grid402</title></head><body style="background:#060B19;color:#F8FAFC;font-family:sans-serif;text-align:center;padding:4rem;"><h1>404 - Página não encontrada</h1><p><a href="/" style="color:#00F0FF;">Voltar para Grid402</a></p></body></html>';
    exit;
}

$page = $pages[$slug];
$isEn = (($page['lang'] ?? 'pt-BR') === 'en');
$htmlLang = $isEn ? 'en' : 'pt-BR';
$rapidApiUrl = (string) ($page['rapidapi_url'] ?? 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities');

if (function_exists('grid402_record_page_visit')) {
    grid402_record_page_visit($page['path'], grid402_get_trusted_client_ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$jsonLdOffer = $isEn
    ? [
        '@type' => 'Offer',
        'name' => 'Grid402 RapidAPI PRO Plan & Multi-Chain x402 USDC',
        'price' => '9.90',
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
        'url' => $rapidApiUrl,
    ]
    : [
        '@type' => 'Offer',
        'name' => 'Grid402 Starter Session Pass (400 créditos via Pix)',
        'price' => '12.00',
        'priceCurrency' => 'BRL',
        'availability' => 'https://schema.org/InStock',
        'url' => $domain . '/pass?tier=starter&method=pix',
    ];

$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => $page['h1'],
            'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any (HTTP REST API)',
            'inLanguage' => $htmlLang,
            'url' => $domain . $page['path'],
            'description' => $page['description'],
            'offers' => $jsonLdOffer,
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn(array $item): array => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ], $page['faq']),
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($domain . $page['path'], ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <script type="application/ld+json"><?php echo json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?></script>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --blue:#0052FF; --green:#10B981; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Inter',system-ui,sans-serif; line-height:1.65; }
        .wrap { max-width:980px; margin:0 auto; padding:2.2rem 1.25rem 4rem; }
        .badge { display:inline-block; background:rgba(0,240,255,.1); border:1px solid rgba(0,240,255,.35); color:var(--cyan); border-radius:999px; padding:.28rem .85rem; font-size:.8rem; font-weight:700; margin-bottom:.9rem; }
        h1 { font-family:'Space Grotesk',sans-serif; font-size:clamp(1.8rem,4vw,2.5rem); line-height:1.15; letter-spacing:-.03em; margin:0 0 .85rem; }
        h2 { font-family:'Space Grotesk',sans-serif; font-size:1.35rem; color:var(--cyan); margin:2.2rem 0 .8rem; }
        p { color:var(--muted); }
        .cta-row { display:flex; flex-wrap:wrap; gap:.75rem; margin:1.4rem 0 1.8rem; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:.82rem 1.3rem; border-radius:9px; font-weight:800; text-decoration:none; font-size:.95rem; }
        .btn-pix { background:linear-gradient(135deg,#059669,#10B981); color:#04120D; }
        .btn-primary { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#041024; }
        .btn-outline { border:1px solid var(--border); color:var(--text); }
        .btn-outline:hover { border-color:var(--cyan); color:var(--cyan); }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.35rem; margin-bottom:1rem; }
        .code-grid { display:grid; grid-template-columns:1fr; gap:1rem; }
        pre { margin:.4rem 0 0; background:#050914; border:1px solid var(--border); border-radius:8px; padding:1rem; overflow-x:auto; color:#E2E8F0; font-family:'JetBrains Mono',monospace; font-size:.84rem; line-height:1.5; }
        table { width:100%; border-collapse:collapse; margin-top:.75rem; }
        th, td { text-align:left; padding:.75rem; border-bottom:1px solid var(--border); font-size:.93rem; }
        th { color:#CBD5E1; background:#111C38; }
        .ok { color:#34D399; font-weight:700; }
        ul.feat { padding-left:1.25rem; color:#CBD5E1; }
        ul.feat li { margin-bottom:.5rem; }
        .faq-item { border-bottom:1px solid var(--border); padding:.85rem 0; }
        .faq-item:last-child { border-bottom:0; }
        .faq-q { font-weight:700; color:#fff; margin-bottom:.3rem; }
        .crosslinks { margin-top:2.5rem; padding-top:1.25rem; border-top:1px solid var(--border); font-size:.9rem; }
        .crosslinks a { color:var(--cyan); text-decoration:none; margin-right:1.1rem; }
        .crosslinks a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => $page['path'], 'domain' => $domain, 'show_lang' => false]); ?>

<main class="wrap">
    <span class="badge"><?php echo htmlspecialchars($page['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
    <h1><?php echo htmlspecialchars($page['h1'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p style="font-size:1.05rem;max-width:800px;"><?php echo htmlspecialchars($page['lead'], ENT_QUOTES, 'UTF-8'); ?></p>

    <div class="cta-row">
        <?php if ($isEn): ?>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars($rapidApiUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">💳 Subscribe via Credit Card on RapidAPI ($9.90/mo)</a>
        <a class="btn btn-pix" href="/pass">⚡ Buy Prepaid USDC Pass (Base / Polygon / Arbitrum)</a>
        <a class="btn btn-outline" href="/catalog">Explore all 28 endpoints →</a>
        <?php else: ?>
        <a class="btn btn-pix" href="/pass?tier=starter&amp;method=pix">⚡ Comprar 400 Créditos via Pix (R$ 12)</a>
        <a class="btn btn-primary" href="/dossie-kyb">📄 Gerar Dossiê 360º KYB em PDF</a>
        <a class="btn btn-outline" href="/catalog">Ver todos os 28 endpoints →</a>
        <?php endif; ?>
    </div>

    <div class="card">
        <strong><?php echo $isEn ? 'Official Endpoint:' : 'Endpoint oficial:'; ?></strong> <code>GET https://grid402.online<?php echo htmlspecialchars($page['endpoint'], ENT_QUOTES, 'UTF-8'); ?></code><br>
        <strong><?php echo $isEn ? 'Pricing:' : 'Tarifa:'; ?></strong> <span class="ok"><?php echo htmlspecialchars($page['price_usdc'], ENT_QUOTES, 'UTF-8'); ?></span> · <?php echo htmlspecialchars($page['credits'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo $isEn ? 'Free Sandbox:' : 'Sandbox grátis:'; ?> <code>X-Payment: demo</code> (<?php echo $isEn ? '3 req/day per IP' : '3 req/dia por IP'; ?>).
    </div>

    <h2><?php echo $isEn ? 'Ready-to-Run Examples: cURL, Python & MCP / RapidAPI' : 'Exemplos prontos: cURL, Python e n8n'; ?></h2>
    <div class="code-grid">
        <div class="card">
            <strong>1. Terminal (cURL)</strong>
            <pre><?php echo htmlspecialchars($page['curl_example'], ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="card">
            <strong>2. Python</strong>
            <pre><?php echo htmlspecialchars($page['python_example'], ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="card">
            <strong>3. <?php echo $isEn ? 'MCP Server / RapidAPI Hub' : 'Automação n8n / Make / Zapier'; ?></strong>
            <pre><?php echo htmlspecialchars($page['n8n_example'], ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>

    <h2><?php echo $isEn ? 'Cost Comparison: Traditional Vendors vs. Grid402' : 'Comparativo de Custo: Bureaus Tradicionais vs. Grid402'; ?></h2>
    <div class="card" style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th><?php echo $isEn ? 'Criteria' : 'Critério'; ?></th>
                    <th><?php echo $isEn ? 'Traditional Vendors' : 'Fornecedores Tradicionais'; ?></th>
                    <th><?php echo $isEn ? 'Grid402 (RapidAPI / x402 USDC)' : 'Grid402 (Pix / x402)'; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo $isEn ? 'Entry Cost' : 'Custo de entrada'; ?></strong></td>
                    <td><?php echo htmlspecialchars($page['traditional_cost'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="ok"><?php echo htmlspecialchars($page['grid_cost'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo $isEn ? 'Minimum Commitment' : 'Mensalidade / Fidelidade'; ?></strong></td>
                    <td><?php echo $isEn ? 'Annual contract & fixed monthly minimums' : 'Contrato anual com franquia fixa'; ?></td>
                    <td class="ok"><?php echo $isEn ? '$0 minimum (Pay-per-call USDC or cancel-anytime RapidAPI)' : 'R$ 0,00 (Zero mensalidade, pague só o que usar)'; ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo $isEn ? 'Activation Time' : 'Ativação'; ?></strong></td>
                    <td><?php echo $isEn ? '2 to 5 business days sales review' : 'Análise comercial de 2 a 5 dias úteis'; ?></td>
                    <td class="ok"><?php echo $isEn ? 'Instant (x402, RapidAPI Credit Card, or npx -y grid402-mcp)' : 'Imediata via Pix (token pass_g402_... na hora)'; ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo $isEn ? 'Audit & Privacy' : 'Auditoria e Privacidade'; ?></strong></td>
                    <td><?php echo $isEn ? 'Unsigned JSON with server-side PII logging' : 'JSON simples sem prova criptográfica'; ?></td>
                    <td class="ok"><?php echo $isEn ? 'ECDSA secp256k1 _oracle_proof + Zero-PII (GDPR Recital 14)' : 'Assinatura ECDSA secp256k1 + Zero-PII (LGPD Art. 1º)'; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2><?php echo $isEn ? 'Technical Highlights' : 'Diferenciais Técnicos'; ?></h2>
    <div class="card">
        <ul class="feat">
            <?php foreach ($page['features'] as $feat): ?>
            <li><?php echo htmlspecialchars($feat, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h2><?php echo $isEn ? 'Frequently Asked Questions (FAQ)' : 'Perguntas Frequentes (FAQ)'; ?></h2>
    <div class="card">
        <?php foreach ($page['faq'] as $faq): ?>
        <div class="faq-item">
            <div class="faq-q"><?php echo htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div style="color:var(--muted);font-size:.94rem;"><?php echo htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card" style="text-align:center;border-color:rgba(16,185,129,.45);">
        <?php if ($isEn): ?>
        <h2 style="margin-top:0;">Start in 30 Seconds — Credit Card (USD) or Multi-Chain USDC</h2>
        <p style="margin-bottom:1rem;">Subscribe with Credit Card on RapidAPI Hub ($9.90/mo) or pay per call in USDC across Base, Polygon, and Arbitrum with <code>npx -y grid402-mcp</code>.</p>
        <div class="cta-row" style="justify-content:center;margin-bottom:0;">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars($rapidApiUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">💳 Open on RapidAPI Hub ($9.90/mo)</a>
            <a class="btn btn-pix" href="/pass">⚡ Buy Prepaid USDC Pass</a>
        </div>
        <?php else: ?>
        <h2 style="margin-top:0;">Comece em menos de 30 segundos sem criar conta</h2>
        <p style="margin-bottom:1rem;">Gere seu Starter Pass via Pix por R$ 12 (400 créditos) e use em todos os 28 endpoints da plataforma ou no Gerador de Dossiê KYB em PDF.</p>
        <div class="cta-row" style="justify-content:center;margin-bottom:0;">
            <a class="btn btn-pix" href="/pass?tier=starter&amp;method=pix">⚡ Gerar Pix R$ 12 Agora</a>
            <a class="btn btn-primary" href="/dossie-kyb">📄 Abrir Gerador de Dossiê KYB</a>
        </div>
        <?php endif; ?>
    </div>

    <nav class="crosslinks" aria-label="<?php echo $isEn ? 'Related pages' : 'Páginas relacionadas'; ?>">
        <strong><?php echo $isEn ? 'Explore more:' : 'Explore também:'; ?></strong>
        <a href="/url-to-markdown-api">URL-to-Markdown API</a>
        <a href="/global-kyb-api">Global KYB API</a>
        <a href="/zero-pii-email-verification-api">Zero-PII Email API</a>
        <a href="/dossie-kyb">Dossiê 360º KYB</a>
        <a href="/api-cnpj">API CNPJ</a>
        <a href="/api-sancoes-cgu">API Sanções CGU</a>
        <a href="/api-dias-uteis-anbima">API ANBIMA</a>
        <a href="/pass">Session Pass</a>
    </nav>
</main>
</body>
</html>
