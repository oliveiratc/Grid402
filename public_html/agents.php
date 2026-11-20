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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Agents and MCP Hub - Grid402</title>
    <meta name="description" content="Official Grid402 integration for autonomous AI agents, MCP servers (Claude Desktop and Cursor), and Coinbase AgentKit over HTTP 402 on Base.">
    <link rel="canonical" href="https://grid402.online/agents">
    <link rel="help" type="text/markdown" href="/llms.txt">
    <link rel="service-meta" type="application/json" href="/.well-known/mcp.json">
    <link rel="service-desc" type="application/vnd.oai.openapi+json" href="/openapi.json">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=2">
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
            flex-wrap: wrap;
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

        .lang-switch {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-dim);
            border-radius: 999px;
            padding: 2px;
        }

        .lang-btn {
            background: transparent;
            border: none;
            color: var(--text-dim);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .lang-btn.active {
            background: var(--cyan);
            color: #030712;
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
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/agents', 'show_lang' => true]); ?>
    <div class="container">

        <section class="hero">
            <div class="badge" data-i18n="agents.hero.badge">x402 Global Autonomous Agent Hub • 25 Services</div>
            <h1 data-i18n="agents.hero.title">Global Data and M2M Utility Infrastructure for AI Agents</h1>
            <p class="lead" data-i18n-html="agents.hero.lead">
                Grid402 empowers autonomous AI agents (Claude, Cursor, LangChain, CrewAI, and Coinbase AgentKit) to query 25 global and Brazilian public-domain endpoints via <strong>gasless HTTP x402 (Base, Polygon, Arbitrum, Solana)</strong> or a <strong>Prepaid Session Pass (&lt;15ms)</strong>, settling USDC micropayments with <strong>ECDSA cryptographic proof</strong>.
            </p>
        </section>

        <div class="card" style="margin: 0 0 1.5rem 0; border-color: rgba(0,240,255,.35);">
            <h2 style="margin-top:.2rem;" data-i18n="agents.nowallet.title">No-Wallet Onboarding — 3 minutes</h2>
            <p style="color:#94A3B8;" data-i18n-html="agents.nowallet.desc">You can test right now with <code>X-Payment: demo</code> (3 queries per day per IP). For production without managing transactions, install the SDK/MCP and configure the PayAI facilitator or a Session Pass; your agent simply calls the URL.</p>
            <div class="code-box" style="margin:.75rem 0;"><pre><code>curl -H "X-Payment: demo" https://grid402.online/api/v1/cnpj?cnpj=00000000000191</code></pre></div>
            <a href="/catalog" style="color:#00F0FF;font-weight:700;" data-i18n="agents.nowallet.btn.catalog">View prices, limits and examples →</a> &nbsp;·&nbsp;
            <a href="/pass" style="color:#00F0FF;font-weight:700;" data-i18n="agents.nowallet.btn.pass">Buy Session Pass →</a>
        </div>

        <div class="features-grid">
            <div class="card">
                <h2 data-i18n="agents.f1.title">Zero Human Sign-up</h2>
                <p data-i18n="agents.f1.desc">No static API keys, credit cards, or logins required. AI agents hire services directly via on-chain Web3 wallets (MetaMask, Rabby, Coinbase Wallet, and others).</p>
            </div>
            <div class="card">
                <h2 data-i18n="agents.f2.title">Cryptographic Oracle</h2>
                <p data-i18n="agents.f2.desc">Every response includes a deterministic ECDSA secp256k1 digital signature (_oracle_proof key), statelessly verifiable in under 1 ms.</p>
            </div>
            <div class="card">
                <h2 data-i18n="agents.f3.title">x402 Session Pass (&lt;15ms)</h2>
                <p data-i18n="agents.f3.desc">Purchase 400 to 6,000 credits in a single Base USDC transaction and execute high-frequency M2M calls in under 15ms without waiting for blocks.</p>
            </div>
        </div>

        <!-- 1. Model Context Protocol (MCP) -->
        <h2 class="section-title" data-i18n="agents.s1.title">1. Model Context Protocol (MCP) Integration</h2>
        <p class="section-sub" data-i18n="agents.s1.sub">Add all 27 MCP tools (25 endpoints + summary + pass balance) to Claude Desktop or Cursor with 1 click.</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-mcp', this)" data-i18n="agents.btn.copy_json">Copy JSON</button>
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

        <!-- 2. Framework ElizaOS Oficial -->
        <h2 class="section-title" data-i18n="agents.eliza.title">2. Official ElizaOS Plugin (grid402-plugin-elizaos)</h2>
        <p class="section-sub" data-i18n="agents.eliza.sub">Allow autonomous AI agents on ElizaOS to query FX rates, spot crypto, global markets, and OFAC/AML sanctions.</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-eliza', this)" data-i18n="agents.btn.copy_ts">Copy TypeScript</button>
            <pre id="code-eliza">import { grid402Plugin } from "grid402-plugin-elizaos";

export const defaultCharacter = {
  name: "GridMarketAgent",
  plugins: [grid402Plugin],
  settings: {
    secrets: {
      // Top up your balance at https://grid402.online/#topup
      GRID402_PASS_TOKEN: "pass_g402_live..."
    }
  }
};</pre>
        </div>

        <!-- 3. Codigo Python para Agentes Autonomos -->
        <h2 class="section-title" data-i18n="agents.s2.title">3. Python Code for Autonomous Agents</h2>
        <p class="section-sub" data-i18n="agents.s2.sub">Direct consumption handling the HTTP 402 challenge or using a Session Pass (`pass_g402_...`).</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-py', this)" data-i18n="agents.btn.copy_py">Copy Python</button>
            <pre id="code-py">import requests

BASE_URL = "https://grid402.online"

def query_oracle(endpoint, payment_proof=None):
    headers = {"Accept": "application/json"}
    if payment_proof:
        # Accepts Base TxHash (0x...), Session Pass (pass_g402_...), or "demo"
        headers["X-Payment"] = payment_proof
        
    res = requests.get(f"{BASE_URL}{endpoint}", headers=headers)
    
    if res.status_code == 402:
        body = res.json()
        print(f"[402 Challenge] Price: {body.get('price_usdc')} USDC on Base")
        print(f"[Free Preview]  {body.get('preview')}")
        return body
        
    data = res.json()
    print("[200 OK] Oracle Proof Verified:", "_oracle_proof" in data)
    return data

# Example: Global Corporate Identity (GLEIF LEI) or OFAC AML
query_oracle("/api/v1/lei?lei=5493006MHB84DD0ZWV18", payment_proof="demo")</pre>
        </div>

        <!-- 4. Terminal cURL -->
        <h2 class="section-title" data-i18n="agents.s3.title">4. Quick Terminal Test (cURL)</h2>
        <p class="section-sub" data-i18n="agents.s3.sub">Inspect x402 challenges, issue a Session Pass, or validate cryptographic proofs directly in the terminal.</p>

        <div class="code-box">
            <button class="copy-btn" onclick="copySnippet('code-curl', this)" data-i18n="agents.btn.copy_curl">Copy cURL</button>
            <pre id="code-curl"># 1. Inspect HTTP 402 Challenge + Free Teaser Preview
curl -i "https://grid402.online/api/v1/lei?lei=5493006MHB84DD0ZWV18"

# 2. Issue a Prepaid Session Pass (400 credits = 2.0000 USDC or "demo" in Sandbox)
curl -H "X-Payment: demo" "https://grid402.online/api/v1/pass"

# 3. Execute <15ms calls using your Session Pass or Base TxHash
curl -H "X-Payment: pass_g402_YOUR_TOKEN" \
  "https://grid402.online/api/v1/global-markets?market=US&base=USD"

# 4. Stateless ECDSA Oracle Proof verification (Free, <1ms)
curl -X POST "https://grid402.online/api/v1/verify" \
  -H "Content-Type: application/json" \
  -d '{"full_response_with_proof": {...}}'</pre>
        </div>

        <!-- Tabela de Endpoints -->
        <h2 class="section-title" data-i18n="agents.table.title">Catalog of All 25 Global Endpoints and Fees</h2>
        <p class="section-sub" data-i18n="agents.table.sub">Per-call pricing in USDC across 4 gasless PayAI x402 chains (Base, Polygon, Arbitrum, Solana) or in Session Pass credits.</p>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th data-i18n="agents.th.service">Service / Public Source</th>
                    <th data-i18n="agents.th.price">Fee (USDC / Pass)</th>
                    <th data-i18n="agents.th.proof">ECDSA Proof</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="endpoint-name">/api/v1/pass</span></td>
                    <td data-i18n="svc.pass.desc">Prepaid Session Pass (400 to 6,000 credits; 1 credit = 0.0050 USDC) for ultra-fast M2M calls (&lt;15ms)</td>
                    <td><span class="tag-price">2 / 5 / 20 USDC (400 / 1,000 / 6,000 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/dossier</span></td>
                    <td data-i18n="svc.dossier.desc">Premium KYB 360 Dossier with deterministic AI Verdict (APPROVED, FLAGGED, REJECTED), score 0-100, Receita Federal CNPJ, Simples/MEI and CGU CEIS/CNEP sanctions.</td>
                    <td><span class="tag-price">0.0500 USDC (10 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/domain-audit</span></td>
                    <td data-i18n="svc.domain-audit.desc">Composite 1-call bundle: authoritative DNS resolution + port 443 SSL/TLS certificate audit + BGP/ASN routing intelligence with consolidated risk score (0-100).</td>
                    <td><span class="tag-price">0.0150 USDC (3 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/kyb-audit</span></td>
                    <td data-i18n="svc.kyb-audit.desc">Composite 1-call global bundle: Receita Federal CNPJ + US Treasury OFAC/UN Sanctions + GLEIF Global LEI.</td>
                    <td><span class="tag-price">0.0200 USDC (4 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/market-snapshot</span></td>
                    <td data-i18n="svc.market-snapshot.desc">Composite 1-call bundle: Global Exchanges and ECB FX + Official PTAX and BACEN Indicators + Spot Crypto Prices for autonomous treasury agents.</td>
                    <td><span class="tag-price">0.0150 USDC (3 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/lei</span></td>
                    <td data-i18n="svc.lei.desc">Global Corporate Identity in 200+ countries via GLEIF LEI (ISO 17442, CC0)</td>
                    <td><span class="tag-price">0.0200 USDC (4 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/ofac</span></td>
                    <td data-i18n="svc.ofac.desc">International Sanctions (OFAC SDN / UN) + Web3 AML Inspector on Base</td>
                    <td><span class="tag-price">0.0200 USDC (4 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/notarize</span></td>
                    <td data-i18n="svc.notarize.desc">UTC Timestamp and M2M Cryptographic Notarization (SHA-256 + ECDSA)</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/global-markets</span></td>
                    <td data-i18n="svc.global-markets.desc">Global Exchange Calendar (US, EU, UK, BR) + 30+ ECB Currencies</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/forex</span></td>
                    <td data-i18n="svc.forex.desc">Official PTAX FX Rates (USD, EUR, GBP to BRL) with explicit timestamps</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/crypto</span></td>
                    <td data-i18n="svc.crypto.desc">Spot prices (BTC, ETH, SOL, USDC, BASE) in USD and BRL</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/dns</span></td>
                    <td data-i18n="svc.dns.desc">Authoritative DNS zone resolution (A, AAAA, MX, TXT, NS, CNAME)</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/ssl</span></td>
                    <td data-i18n="svc.ssl.desc">Port 443 TLS certificate and X.509 chain audit</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/asn</span></td>
                    <td data-i18n="svc.asn.desc">BGP routing, ASN number, CIDR block, and IP country lookup</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/cep</span></td>
                    <td data-i18n="svc.cep.desc">Brazilian postal address and official IBGE municipality code</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/cnpj</span></td>
                    <td data-i18n="svc.cnpj.desc">Official Brazilian corporate tax registry (Receita Federal)</td>
                    <td><span class="tag-price">0.0200 USDC (4 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/indicators</span></td>
                    <td data-i18n="svc.indicators.desc">Selic, CDI, and IPCA inflation indices (Central Bank and IBGE)</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/holidays</span></td>
                    <td data-i18n="svc.holidays.desc">Official ANBIMA and Febraban banking holiday calendar</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/workdays</span></td>
                    <td data-i18n="svc.workdays.desc">ANBIMA banking business days calculator and D+N projection</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/sanctions</span></td>
                    <td data-i18n="svc.sanctions.desc">Administrative sanctions and debarment registries (CGU CEIS/CNEP)</td>
                    <td><span class="tag-price">0.0100 USDC (2 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/simples</span></td>
                    <td data-i18n="svc.simples.desc">Simples Nacional and MEI tax regime status</td>
                    <td><span class="tag-price">0.0100 USDC (2 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/compliance</span></td>
                    <td data-i18n="svc.compliance.desc">Consolidated Compliance 360 corporate risk score (0 to 100)</td>
                    <td><span class="tag-price">0.0300 USDC (6 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/extract</span></td>
                    <td data-i18n="svc.extract.desc">SSRF-hardened webpage fetcher returning LLM-ready clean Markdown, metadata, and absolute links</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/email-verify</span></td>
                    <td data-i18n="svc.email-verify.desc">Zero-PII B2B email audit: RFC syntax, DNS MX, SPF, DMARC, disposable domain detection, and deliverability score 0–100</td>
                    <td><span class="tag-price">0.0050 USDC (1 cr)</span></td>
                    <td><span class="proof-yes" data-i18n="agents.yes">✓ Yes</span></td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/api/v1/verify</span></td>
                    <td data-i18n="svc.verify.desc">Stateless mathematical-cryptographic oracle verification (&lt;1ms)</td>
                    <td><span class="tag-free" data-i18n="agents.free">Free</span></td>
                    <td>Native</td>
                </tr>
                <tr>
                    <td><span class="endpoint-name">/status</span></td>
                    <td data-i18n="console.act.summary">Free Summary (/status)</td>
                    <td><span class="tag-free" data-i18n="agents.free">Free</span></td>
                    <td>-</td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- Manifestos e Arquivos de Descoberta -->
        <h2 class="section-title" data-i18n="agents.manifests.title">Manifests for Automated Discovery</h2>
        <p class="section-sub" data-i18n="agents.manifests.sub">Open standards for LLMs and autonomous agents to map Grid402.</p>

        <div class="manifest-links">
            <a href="/llms.txt" class="manifest-btn">llms.txt (LLM Summary)</a>
            <a href="/llms-full.txt" class="manifest-btn">llms-full.txt (Full Context)</a>
            <a href="/.well-known/mcp.json" class="manifest-btn">.well-known/mcp.json (MCP Tools)</a>
            <a href="/.well-known/ai-plugin.json" class="manifest-btn">ai-plugin.json (AI Plugin)</a>
            <a href="/openapi.json" class="manifest-btn">openapi.json (OpenAPI 3.1)</a>
        </div>

        <footer>
            <div>
                <div data-i18n="footer.hub">Grid402 // Open Data and Utility Hub • Base Mainnet</div>
                <div style="margin-top: 4px; font-size: 0.78rem; color: #64748B;" data-i18n="footer.contributors">
                    © <?php echo date('Y'); ?> Grid402 Network Intelligence. All rights reserved. Protected by Law No. 9.609/1998 and Law No. 9.610/1998.
                </div>
            </div>
            <div style="display:flex; gap:16px; align-items:center;">
                <a href="/terms" data-i18n="nav.terms">Terms</a>
                <a href="/privacy" data-i18n="nav.privacy">Privacy</a>
                <a href="/catalog" data-i18n="nav.catalog">Catalog</a>
                <a href="/trust" data-i18n="nav.trust">Trust</a>
                <a href="/growth" data-i18n="nav.growth">Growth</a>
                <a href="/payment-dashboard" data-i18n="nav.payments">Payments</a>
                <a href="/about" data-i18n="nav.about">About</a>
                <a href="/contact" data-i18n="nav.contact">Contact</a>
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
                btn.innerText = (typeof currentLanguage !== 'undefined' && currentLanguage === 'en') ? "Copied!" : "Copiado!";
                btn.classList.add("copied");
                setTimeout(function() {
                    btn.innerText = originalText;
                    btn.classList.remove("copied");
                }, 2000);
            }).catch(function(err) {
                console.error("Error copying snippet:", err);
            });
        }
    </script>
    <script src="/assets/js/i18n.js?v=3.3.0"></script>
</body>
</html>

