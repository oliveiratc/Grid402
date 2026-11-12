<?php
// pass.php - Pagina dedicada de compra de Session Pass (Grid402)
// Linkavel diretamente na resposta HTTP 402 para humanos inspecionando o desafio.
$domain = defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Comprar Session Pass (pass_g402_...)</title>
    <style>
        :root { --bg:#0a0e17; --surface:#111827; --border:#1f2937; --text:#e5e7eb; --muted:#8fa2b9; --cyan:#00f0ff; --blue:#0052ff; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
        .wrap { max-width:960px; margin:0 auto; padding:2rem 1.25rem; }
        h1 { font-size:1.9rem; letter-spacing:-0.5px; margin:0 0 .5rem; }
        h2 { font-size:1.3rem; margin:2rem 0 .75rem; color:var(--cyan); }
        p { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:1.25rem; margin:1.5rem 0; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; display:flex; flex-direction:column; justify-content:space-between; }
        .card.featured { border-color:var(--blue); box-shadow:0 8px 30px rgba(0,82,255,.25); }
        .tag { font-size:.8rem; text-transform:uppercase; font-weight:700; color:var(--muted); letter-spacing:1px; margin-bottom:.4rem; }
        .price { font-size:1.6rem; font-weight:800; color:#fff; margin:.5rem 0; }
        .credits { color:var(--cyan); font-weight:700; }
        .desc { font-size:.9rem; color:var(--muted); margin-bottom:1rem; }
        .btn { display:inline-block; text-align:center; padding:.7rem 1rem; border-radius:8px; font-weight:700; text-decoration:none; margin-top:.75rem; }
        .btn-primary { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#fff; }
        .btn-outline { border:1px solid var(--cyan); color:var(--cyan); }
        code { background:rgba(0,240,255,.08); color:var(--cyan); padding:2px 6px; border-radius:4px; font-family:'JetBrains Mono',monospace; font-size:.9rem; }
        pre { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1rem; overflow-x:auto; color:#cbd5e1; font-size:.85rem; }
        .note { background:rgba(0,82,255,.08); border:1px solid rgba(0,82,255,.35); border-radius:8px; padding:1rem; margin:1.5rem 0; font-size:.9rem; }
        a { color:var(--cyan); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Comprar Session Pass <code>pass_g402_...</code></h1>
    <p>Pague uma vez em USDC na rede Base Mainnet e receba na hora um token com saldo de milhares de requisicoes de alta velocidade (&lt;15ms), sem assinar transacao a cada GET. Ideal para agentes de IA e esteiras de automacao (n8n, Make, Zapier).</p>

    <div class="note">
        <strong>Nao tem USDC/carteira?</strong> Em breve: checkout em Pix (BRL) e cartao. Enquanto isso, use o <a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/#topup">abastecimento Web3</a> (MetaMask, Coinbase Smart Wallet com Passkey, Rainbow) ou o modo Sandbox gratuito: <code>X-Payment: demo</code> (3 req/hora por IP).
    </div>

    <h2>Pacotes</h2>
    <div class="grid">
        <div class="card">
            <div>
                <div class="tag">Starter Pass</div>
                <div class="price">2 USDC</div>
                <div class="credits">4.000 requisicoes</div>
                <p class="desc">Ideal para desenvolvedores individuais, testes rapidos e bots pontuais. Validade 30 dias.</p>
            </div>
            <a class="btn btn-outline" href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/#topup">Comprar Starter</a>
        </div>
        <div class="card featured">
            <div>
                <div class="tag">Pro / Agent Pass</div>
                <div class="price">5 USDC</div>
                <div class="credits">10.000 requisicoes</div>
                <p class="desc">Para agentes autonomos de IA e esteiras de automacao continua. Mais escolhido. Validade 30 dias.</p>
            </div>
            <a class="btn btn-primary" href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/#topup">Comprar Pro</a>
        </div>
        <div class="card">
            <div>
                <div class="tag">Fleet / Enterprise</div>
                <div class="price">20 USDC</div>
                <div class="credits">50.000 + 10.000 bonus</div>
                <p class="desc">Frotas de agentes, sistemas ERP corporativos e alta demanda. Validade 60 dias.</p>
            </div>
            <a class="btn btn-outline" href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/#topup">Comprar Fleet</a>
        </div>
    </div>

    <h2>Como usar o token</h2>
    <pre>curl -s -H "X-Payment: pass_g402_SEU_TOKEN_AQUI" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"</pre>
    <p>Ou defina a variavel de ambiente <code>GRID402_SESSION_PASS=pass_g402_...</code> no servidor MCP (<code>grid402-mcp</code>) para uso automatico.</p>

    <h2>Endpoints disponiveis</h2>
    <pre>GET /api/v1/forex        0.0005 USDC
GET /api/v1/crypto       0.0005 USDC
GET /api/v1/dns          0.0005 USDC
GET /api/v1/ssl          0.0005 USDC
GET /api/v1/asn          0.0005 USDC
GET /api/v1/cep          0.0005 USDC
GET /api/v1/cnpj         0.0020 USDC
GET /api/v1/compliance   0.0020 USDC (premium)</pre>

    <p style="margin-top:2rem;"><a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/">&larr; Voltar ao portal</a> &nbsp;|&nbsp; <a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/llms.txt">llms.txt</a> &nbsp;|&nbsp; <a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/openapi.json">OpenAPI</a></p>
</div>
</body>
</html>
