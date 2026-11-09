<?php
/**
 * Grid402 // Central de Agentes de IA e Clientes Model Context Protocol (MCP)
 * 
 * Portal dedicado para desenvolvedores de IA, orquestradores autonomos
 * e clientes MCP (Claude Desktop, Cursor, Coinbase AgentKit, LangChain).
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Link: </llms.txt>; rel="help"; type="text/markdown", </.well-known/mcp.json>; rel="service-meta"; type="application/json", </openapi.json>; rel="service-desc"; type="application/vnd.oai.openapi+json"');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Agentes de IA e MCP - Grid402</title>
    <meta name="description" content="Integracao oficial do Grid402 para agentes autonomos de IA, servidores MCP (Claude Desktop e Cursor) e Coinbase AgentKit sob protocolo HTTP 402 na Base.">
    <link rel="canonical" href="https://grid402.online/agents">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="service-meta" type="application/json" href="/.well-known/mcp.json">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #060B19;
            --bg-card: #0B132B;
            --bg-hover: #111C38;
            --border-dim: #1E2C4F;
            --border-glow: #00F0FF;
            --text-main: #F8FAFC;
            --text-dim: #94A3B8;
            --primary: #0052FF;
            --primary-bg: rgba(0, 82, 255, 0.15);
            --cyan: #00F0FF;
            --cyan-bg: rgba(0, 240, 255, 0.12);
            --emerald: #00F0FF;
            --emerald-bg: rgba(0, 240, 255, 0.12);
            --sky: #00F0FF;
            --sky-bg: rgba(0, 240, 255, 0.12);
            --amber: #F59E0B;
            --font-heading: 'Space Grotesk', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            padding: 0;
            margin: 0;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        h1, h2, h3, h4 {
            font-family: var(--font-heading);
            letter-spacing: -0.5px;
        }

        .container {
            max-width: 1040px;
            margin: 0 auto;
            padding: 32px 20px 80px 20px;
            width: 100%;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-dim);
            margin-bottom: 40px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-main);
        }

        .brand-logo {
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--emerald);
            background: var(--emerald-bg);
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .brand-text {
            font-size: 0.95rem;
            color: var(--text-dim);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        nav a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        nav a:hover, nav a.active {
            color: var(--emerald);
        }

        .hero {
            margin-bottom: 48px;
            text-align: left;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--emerald-bg);
            color: var(--emerald);
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 9999px;
            border: 1px solid rgba(16, 185, 129, 0.3);
            margin-bottom: 16px;
        }

        h1 {
            font-size: 2.3rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-main);
            margin-bottom: 14px;
        }

        .lead {
            font-size: 1.15rem;
            color: var(--text-dim);
            max-width: 800px;
            line-height: 1.6;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 48px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 24px;
            transition: transform 0.2s, border-color 0.2s;
        }

        .card:hover {
            border-color: var(--emerald);
            transform: translateY(-2px);
        }

        .card h2 {
            font-size: 1.25rem;
            color: var(--text-main);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card p {
            color: var(--text-dim);
            font-size: 0.92rem;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .section-sub {
            color: var(--text-dim);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        .code-box {
            position: relative;
            background: #04070D;
            border: 1px solid var(--border-dim);
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 28px;
            font-family: var(--font-mono);
            font-size: 0.88rem;
            overflow-x: auto;
        }

        .code-box pre {
            margin: 0;
            color: #E2E8F0;
        }

        .copy-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--bg-card);
            color: var(--text-dim);
            border: 1px solid var(--border-dim);
            border-radius: 6px;
            padding: 6px 12px;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            color: var(--emerald);
            border-color: var(--emerald);
        }

        .copy-btn.copied {
            background: var(--emerald);
            color: #000;
            border-color: var(--emerald);
        }

        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--border-dim);
            background: var(--bg-card);
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            min-width: 580px;
            border-collapse: collapse;
            background: transparent;
            border: none;
            margin-bottom: 0;
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-dim);
            font-size: 0.9rem;
            white-space: nowrap;
        }

        th {
            background: #090F1E;
            color: var(--text-dim);
            font-weight: 600;
            font-family: var(--font-mono);
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .endpoint-name {
            font-family: var(--font-mono);
            color: var(--sky);
            font-weight: 600;
        }

        .tag-price {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--emerald);
            background: var(--emerald-bg);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .tag-free {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--sky);
            background: var(--sky-bg);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        .manifest-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 48px;
        }

        .manifest-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border-dim);
            border-radius: 8px;
            padding: 10px 16px;
            font-family: var(--font-mono);
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .manifest-btn:hover {
            border-color: var(--emerald);
            color: var(--emerald);
            background: var(--bg-hover);
        }

        footer {
            margin-top: 60px;
            padding-top: 24px;
            border-top: 1px solid var(--border-dim);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 0.85rem;
            color: var(--text-dim);
        }

        footer a {
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.2s;
        }

        footer a:hover {
            color: var(--emerald);
        }

        @media (max-width: 768px) {
            .container { padding: 24px 16px 60px 16px; }
            h1 { font-size: 1.8rem; line-height: 1.25; }
            header { flex-direction: column; align-items: flex-start; gap: 16px; margin-bottom: 28px; }
            nav { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; }
            nav a { padding: 6px 10px; font-size: 0.85rem; }
            .features-grid { grid-template-columns: 1fr; gap: 14px; }
            .hero { margin-bottom: 32px; }
            .manifest-links { flex-direction: column; }
            .manifest-btn { width: 100%; justify-content: center; }
            footer { flex-direction: column; align-items: flex-start; gap: 12px; }
        }

        @media (max-width: 480px) {
            .container { padding: 16px 12px 40px 12px; }
            h1 { font-size: 1.45rem; }
            .lead { font-size: 0.92rem; }
            .badge { font-size: 0.72rem; }
            .card { padding: 16px; }
            .code-box { padding: 12px; font-size: 0.78rem; }
            .copy-btn { top: 8px; right: 8px; padding: 4px 8px; font-size: 0.72rem; }
            .tabs-header { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tab-btn { font-size: 0.8rem; padding: 6px 10px; }
            .brand-logo { font-size: 1.15rem; padding: 4px 8px; }
            .brand-text { font-size: 0.82rem; }
        }

        @media (max-width: 360px) {
            h1 { font-size: 1.3rem; }
            nav a { font-size: 0.78rem; padding: 5px 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="/" class="brand">
                <img src="/assets/logo-icon-transparent.png" alt="Grid402" width="38" height="38" style="display: block;">
                <div>
                    <div style="font-family: var(--font-heading); font-weight: 700; font-size: 1.35rem; line-height: 1.2; letter-spacing: -0.5px;">Grid<span style="color: var(--cyan);">402</span></div>
                    <div class="brand-text">Central de Agentes IA & MCP</div>
                </div>
            </a>
            <nav>
                <a href="/">Inicio</a>
                <a href="/agents" class="active">Agentes de IA</a>
                <a href="/llms.txt">llms.txt</a>
                <a href="/openapi.json">OpenAPI</a>
                <a href="/admin">Admin</a>
            </nav>
        </header>

        <section class="hero">
            <div class="badge">x402 Autonomic Agent Hub</div>
            <h1>Infraestrutura de Dados do Brasil para Agentes de IA</h1>
            <p class="lead">
                O Grid402 permite que agentes autonomos de IA (Claude, Cursor, LangChain, CrewAI e Coinbase AgentKit) consultem dados publicos brasileiros sob demanda via <strong>HTTP 402</strong>, liquidando micropagamentos em USDC na rede Base com <strong>prova criptografica de integridade</strong>.
            </p>
        </section>

        <div class="features-grid">
            <div class="card">
                <h2>Zero Cadastro Humano</h2>
                <p>Nao requer chaves estaticas de API, cartao de credito ou login. Agentes de IA contratam servicos diretamente via carteira Web3 on-chain.</p>
            </div>
            <div class="card">
                <h2>Oraculo Criptografico</h2>
                <p>Cada resposta inclui assinatura digital ECDSA secp256k1 deterministica (chave _oracle_proof), verificavel sem estado em menos de 1 ms.</p>
            </div>
            <div class="card">
                <h2>Latencia Ultrabaixa</h2>
                <p>Arquitetura Zero-DB em PHP nativo e cache atomico com respostas em menos de 20 ms, otimizadas para processamento em larga escala.</p>
            </div>
        </div>

        <!-- 1. Model Context Protocol (MCP) -->
        <h2 class="section-title">1. Integracao com Model Context Protocol (MCP)</h2>
        <p class="section-sub">Adicione todas as ferramentas de dados brasileiros ao Claude Desktop ou Cursor com 1 clique.</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-mcp', this)">Copiar JSON</button>
            <pre id="code-mcp">{
  "mcpServers": {
    "grid402": {
      "command": "npx",
      "args": ["-y", "grid402-mcp@latest"],
      "env": {
        "GRID402_BASE_URL": "https://grid402.online"
      }
    }
  }
}</pre>
        </div>

        <!-- 2. Python Agent Snippet -->
        <h2 class="section-title">2. Codigo Python para Agentes Autonomos</h2>
        <p class="section-sub">Consumo direto tratando o codigo de status 402 com requests ou bibliotecas padrao.</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-py', this)">Copiar Python</button>
            <pre id="code-py">import requests

BASE_URL = "https://grid402.online"

def consultar_dados(endpoint, payment_tx=None):
    headers = {}
    if payment_tx:
        headers["X-Payment"] = payment_tx
        
    res = requests.get(f"{BASE_URL}{endpoint}", headers=headers)
    
    # 402: Servico tarifado via x402
    if res.status_code == 402:
        payment = res.json().get("payment_details", {})
        print(f"[402] Efetue transferencia de {payment.get('amount')} USDC na Base")
        print(f"      Destinatario: {payment.get('recipient')}")
        return res.json()
        
    data = res.json()
    print("[200] Sucesso! Prova de oraculo presente:", "_oracle_proof" in data)
    return data

# Exemplo de chamada:
consultar_dados("/api/v1/cnpj?cnpj=00000000000191")</pre>
        </div>

        <!-- 3. Terminal cURL -->
        <h2 class="section-title">3. Teste Rapido no Terminal (cURL)</h2>
        <p class="section-sub">Inspecione os cabecalhos de pagamento x402 diretamente do seu terminal.</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-curl', this)">Copiar cURL</button>
            <pre id="code-curl"># 1. Obter desafio de pagamento HTTP 402
curl -i "https://grid402.online/api/v1/indicators"

# 2. Requisicao com comprovante on-chain na Base
curl -H "X-Payment: 0xSEU_TRANSACTION_HASH_ON_BASE" \
  "https://grid402.online/api/v1/indicators"

# 3. Validar prova criptografica de oraculo (sem custo)
curl -X POST "https://grid402.online/api/v1/verify" \
  -H "Content-Type: application/json" \
  -d '{"full_response_with_proof": {...}}'</pre>
        </div>

        <!-- Tabela de Endpoints -->
        <h2 class="section-title">Catalogo de Endpoints e Tarifas</h2>
        <p class="section-sub">Valores por chamada em USDC na rede Base Mainnet.</p>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Servico / Fonte</th>
                    <th>Tarifa (USDC)</th>
                    <th>Prova Oraculo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="endpoint-name">/api/v1/summary</span></td>
                    <td>Status e saude dos microservicos</td>
                    <td><span class="tag-free">Gratuito</span></td>
                    <td>Nao</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/forex</span></td>
                    <td>Cambio PTAX Oficial (USD, EUR, GBP para BRL)</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/crypto</span></td>
                    <td>Precos spot (BTC, ETH, SOL, USDC, BASE)</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/dns</span></td>
                    <td>Resolucao autoritativa de registros DNS</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/ssl</span></td>
                    <td>Auditoria de certificados TLS e SSL</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/asn</span></td>
                    <td>Roteamento BGP e informacoes ASN de IPs</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/cep</span></td>
                    <td>Endereco postal brasileiro e codigo IBGE</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/cnpj</span></td>
                    <td>Dados oficiais de PJ na Receita Federal</td>
                    <td><span class="tag-price">0.0020 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/indicators</span></td>
                    <td>Taxas Selic, CDI e indices de inflacao IPCA</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/holidays</span></td>
                    <td>Calendario de feriados bancarios ANBIMA</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/workdays</span></td>
                    <td>Calculadora de dias uteis financeiros</td>
                    <td><span class="tag-price">0.0005 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/sanctions</span></td>
                    <td>Sancoes administrativas e inidoneidade (CEIS/CNEP da CGU)</td>
                    <td><span class="tag-price">0.0010 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/simples</span></td>
                    <td>Enquadramento tributario no Simples Nacional e MEI</td>
                    <td><span class="tag-price">0.0010 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/compliance</span></td>
                    <td>Compliance Score 360 consolidado de risco cadastral</td>
                    <td><span class="tag-price">0.0030 USDC</span></td>
                    <td>Sim</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/verify</span></td>
                    <td>Validacao matematico-criptografica de oraculo</td>
                    <td><span class="tag-free">Gratuito</span></td>
                    <td>Nativa</td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- Manifestos e Arquivos de Descoberta -->
        <h2 class="section-title">Manifestos para Descoberta Automatica</h2>
        <p class="section-sub">Padroes abertos para que modelos de linguagem e agentes mapeiem o Grid402.</p>

        <div class="manifest-links">
            <a href="/llms.txt" class="manifest-btn">llms.txt (Contexto Sintetico)</a>
            <a href="/llms-full.txt" class="manifest-btn">llms-full.txt (Contexto Detalhado)</a>
            <a href="/.well-known/mcp.json" class="manifest-btn">.well-known/mcp.json (Tools MCP)</a>
            <a href="/.well-known/ai-plugin.json" class="manifest-btn">ai-plugin.json (Plugin IA)</a>
            <a href="/openapi.json" class="manifest-btn">openapi.json (OpenAPI 3.1)</a>
        </div>

        <footer>
            <div>Grid402 // Infraestrutura de Dados Abertos e Oraculo x402</div>
            <div style="display:flex; gap:16px;">
                <a href="/terms">Termos</a>
                <a href="/privacy">Privacidade</a>
                <a href="/about">Sobre</a>
                <a href="/contact">Contato</a>
                <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">GitHub</a>
            </div>
        </footer>
    </div>

    <script>
        function copySnippet(elementId, btn) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.innerText || el.textContent;
            navigator.clipboard.writeText(text).then(function() {
                const originalText = btn.innerText;
                btn.innerText = "Copiado!";
                btn.classList.add("copied");
                setTimeout(function() {
                    btn.innerText = originalText;
                    btn.classList.remove("copied");
                }, 2000);
            }).catch(function(err) {
                console.error("Erro ao copiar snippet:", err);
            });
        }
    </script>
</body>
</html>

