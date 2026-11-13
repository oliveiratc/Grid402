<?php
// plans.php - Planos em BRL (Alavanca 2): assinatura mensal com créditos pré-pagos.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/sales.php';
$plans = grid402_plans();
$domain = defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Planos em BRL (Pix e Cartão)</title>
    <style>
        :root { --bg:#0a0e17; --surface:#111827; --border:#1f2937; --text:#e5e7eb; --muted:#8fa2b9; --cyan:#00f0ff; --blue:#0052ff; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
        .wrap { max-width:1080px; margin:0 auto; padding:2rem 1.25rem; }
        h1 { font-size:1.9rem; letter-spacing:-0.5px; margin:0 0 .5rem; }
        h2 { font-size:1.3rem; margin:2rem 0 .75rem; color:var(--cyan); }
        p { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1.25rem; margin:1.5rem 0; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; display:flex; flex-direction:column; justify-content:space-between; }
        .card.featured { border-color:var(--blue); box-shadow:0 8px 30px rgba(0,82,255,.25); }
        .tag { font-size:.8rem; text-transform:uppercase; font-weight:700; color:var(--muted); letter-spacing:1px; margin-bottom:.4rem; }
        .price { font-size:1.6rem; font-weight:800; color:#fff; margin:.5rem 0 .25rem; }
        .per { font-size:.85rem; color:var(--muted); }
        .credits { color:var(--cyan); font-weight:700; margin:.5rem 0; }
        .desc { font-size:.9rem; color:var(--muted); margin-bottom:1rem; }
        .btn { display:inline-block; text-align:center; padding:.7rem 1rem; border-radius:8px; font-weight:700; text-decoration:none; margin-top:.75rem; width:100%; }
        .btn-primary { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#fff; }
        .btn-outline { border:1px solid var(--cyan); color:var(--cyan); }
        code { background:rgba(0,240,255,.08); color:var(--cyan); padding:2px 6px; border-radius:4px; font-family:'JetBrains Mono',monospace; font-size:.9rem; }
        table { width:100%; border-collapse:collapse; margin:1rem 0; font-size:.9rem; }
        th, td { padding:.6rem .8rem; border-bottom:1px solid var(--border); text-align:left; }
        th { color:var(--cyan); }
        .muted { color:var(--muted); font-size:.9rem; }
        .note { background:rgba(0,82,255,.08); border:1px solid rgba(0,82,255,.35); border-radius:8px; padding:1rem; margin:1.5rem 0; font-size:.9rem; }
        a { color:var(--cyan); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Planos em BRL — Pix e Cartão</h1>
    <p>Assinatura mensal com créditos pré-pagos. Sem USDC, sem carteira, sem cadastro de API key. Pague via Pix ou cartão e receba um token <code>pass_g402_…</code> por e-mail e na tela.</p>

    <div class="note">
        <strong>Como funciona:</strong> 1 crédito = 1 consulta de 0,0005 USDC. Endpoints premium consomem mais créditos (CNPJ = 4, Compliance 360 = 6, Sanções/Simples = 2). A tabela abaixo mostra a <strong>equivalência real</strong> por plano — não "requisições" genéricas.
    </div>

    <h2>Planos</h2>
    <div class="grid">
        <?php foreach ($plans as $p): ?>
            <?php $featured = $p['id'] === 'pro'; ?>
            <div class="card <?php echo $featured ? 'featured' : ''; ?>">
                <div>
                    <div class="tag"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="price">R$ <?php echo number_format($p['price_brl'], 2, ',', '.'); ?></div>
                    <div class="per">por mês</div>
                    <div class="credits"><?php echo number_format($p['credits'], 0, ',', '.'); ?> créditos</div>
                    <p class="desc"><?php echo htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <ul style="font-size:.85rem;color:var(--muted);padding-left:1.2rem;margin:0 0 1rem;">
                        <li>≈ <?php echo number_format($p['approx_cnpj'], 0, ',', '.'); ?> consultas CNPJ</li>
                        <li>≈ <?php echo number_format($p['approx_compliance'], 0, ',', '.'); ?> dossiês Compliance 360</li>
                        <li>≈ <?php echo number_format($p['approx_sanctions'], 0, ',', '.'); ?> consultas Sanções/Simples</li>
                    </ul>
                </div>
                <?php if (!empty($p['mp_link'])): ?>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars($p['mp_link'], ENT_QUOTES, 'UTF-8'); ?>">Assinar via Pix</a>
                <?php elseif (!empty($p['stripe_price_id'])): ?>
                    <a class="btn btn-primary" href="https://checkout.stripe.com/c/pay/<?php echo htmlspecialchars($p['stripe_price_id'], ENT_QUOTES, 'UTF-8'); ?>">Assinar via Cartão</a>
                <?php else: ?>
                    <a class="btn btn-outline" href="mailto:contato@grid402.online?subject=Plano%20<?php echo urlencode($p['name']); ?>">Solicitar via e-mail</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Créditos por endpoint</h2>
    <table>
        <tr><th>Endpoint</th><th>Custo (USDC)</th><th>Créditos</th><th>Equivalência</th></tr>
        <tr><td>forex, crypto, dns, ssl, asn, cep, indicators, holidays, workdays, notarize, global-markets</td><td>0,0005</td><td>1</td><td>1 consulta</td></tr>
        <tr><td>sanctions, simples</td><td>0,0010</td><td>2</td><td>1 consulta</td></tr>
        <tr><td>cnpj, lei, ofac</td><td>0,0020</td><td>4</td><td>1 consulta</td></tr>
        <tr><td>compliance (Score 360)</td><td>0,0030</td><td>6</td><td>1 dossiê completo</td></tr>
    </table>

    <h2>Trial gratuito (200 créditos)</h2>
    <p class="muted">Sem cartão, sem USDC. Cadastre seu e-mail e receba um token <code>pass_g402_…</code> com 200 créditos para testar a API em produção.</p>
    <form method="post" action="/trial" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0;">
        <input type="email" name="email" placeholder="seu@email.com" required style="flex:1;min-width:220px;padding:.7rem;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
        <button type="submit" class="btn btn-primary" style="width:auto;">Receber trial</button>
    </form>

    <h2>Enterprise / White-label</h2>
    <p class="muted">SLA, volume contratado, fatura em BRL, integração dedicada, ou licenciamento da infraestrutura x402/Zero-DB para outros provedores de dados. <a href="mailto:contato@grid402.online?subject=Enterprise%20Grid402">contato@grid402.online</a></p>

    <p style="margin-top:2rem;"><a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/">&larr; Voltar ao portal</a> &nbsp;|&nbsp; <a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/pass">Session Pass (USDC)</a> &nbsp;|&nbsp; <a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/llms.txt">llms.txt</a></p>
</div>
</body>
</html>
