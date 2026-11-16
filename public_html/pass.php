<?php
// pass.php - Pagina dedicada de compra de Session Pass (Grid402)
// Suporta pagamento em BRL via Mercado Pago (Pix transparente + Checkout Pro) e Web3 (USDC na Base).
if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/mercadopago.php';
require_once __DIR__ . '/metrics.php';

grid402_record_page_visit('/pass');

$domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
$packages = grid402_mp_packages();
$isSandbox = grid402_mp_is_sandbox();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Buy Session Pass (Pix, Card or USDC)</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --blue:#0052FF; --green:#10B981; --warn:#F59E0B; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Inter','Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
        .wrap { max-width:980px; margin:0 auto; padding:2rem 1.25rem 3rem; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .brand { font-weight:800; font-size:1.15rem; color:#fff; text-decoration:none; }
        .brand span { color:var(--cyan); }
        h1 { font-size:1.95rem; letter-spacing:-0.5px; margin:0 0 .5rem; }
        h2 { font-size:1.25rem; margin:2rem 0 .75rem; color:var(--cyan); }
        p { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:1.25rem; margin:1.5rem 0; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; display:flex; flex-direction:column; justify-content:space-between; }
        .card.featured { border-color:var(--blue); box-shadow:0 8px 30px rgba(0,82,255,.25); }
        .tag { font-size:.78rem; text-transform:uppercase; font-weight:700; color:var(--muted); letter-spacing:1px; margin-bottom:.4rem; }
        .price { font-size:1.75rem; font-weight:800; color:#fff; margin:.35rem 0 0; }
        .price-sub { font-size:.85rem; color:var(--muted); margin-bottom:.5rem; }
        .credits { color:var(--cyan); font-weight:700; margin-bottom:.5rem; }
        .desc { font-size:.9rem; color:var(--muted); margin-bottom:1rem; }
        .actions { display:flex; flex-direction:column; gap:.55rem; margin-top:.75rem; }
        .btn { display:inline-block; width:100%; text-align:center; padding:.7rem 1rem; border-radius:8px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:.92rem; }
        .btn:disabled { opacity:.6; cursor:not-allowed; }
        .btn-pix { background:linear-gradient(135deg,#059669,#10B981); color:#04120D; }
        .btn-card { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#041024; }
        .btn-outline { border:1px solid var(--border); color:var(--text); background:transparent; }
        .btn-outline:hover { border-color:var(--cyan); color:var(--cyan); }
        code { background:rgba(0,240,255,.08); color:var(--cyan); padding:2px 6px; border-radius:4px; font-family:'JetBrains Mono',monospace; font-size:.88rem; }
        pre { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1rem; overflow-x:auto; color:#cbd5e1; font-size:.85rem; }
        .note { background:rgba(0,82,255,.08); border:1px solid rgba(0,82,255,.35); border-radius:8px; padding:1rem; margin:1.25rem 0; font-size:.9rem; }
        .email-row { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin:1rem 0 0; }
        .input { flex:1; min-width:240px; padding:.68rem .9rem; border-radius:8px; border:1px solid var(--border); background:#070D1F; color:#fff; font-size:.92rem; }
        .panel { background:var(--surface); border:1px solid var(--cyan); border-radius:12px; padding:1.5rem; margin:1.5rem 0; display:none; }
        .panel.active { display:block; }
        .pix-layout { display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap; margin-top:1rem; }
        .qr-box { background:#fff; padding:.6rem; border-radius:10px; width:220px; height:220px; display:flex; align-items:center; justify-content:center; }
        .qr-box img { max-width:100%; max-height:100%; display:block; }
        .pix-info { flex:1; min-width:260px; }
        .copy-row { display:flex; gap:.5rem; margin:.65rem 0; }
        .status-pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:700; background:rgba(245,158,11,.15); color:var(--warn); border:1px solid rgba(245,158,11,.35); }
        .status-pill.ok { background:rgba(16,185,129,.15); color:var(--green); border-color:rgba(16,185,129,.35); }
        .token-box { background:#050914; border:1px solid var(--green); border-radius:10px; padding:1rem; margin-top:.75rem; word-break:break-all; font-family:'JetBrains Mono',monospace; font-size:1rem; color:var(--cyan); }
        a { color:var(--cyan); }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/pass', 'domain' => $domain, 'show_lang' => true]); ?>
<div class="wrap">

    <h1 data-i18n-html="pass.title">Buy Session Pass <code>pass_g402_...</code></h1>
    <p data-i18n-html="pass.subtitle">Pay once in <strong>BRL (Pix or Card via Mercado Pago)</strong> or in <strong>USDC on the Base network</strong> and instantly receive a token loaded with thousands of requests (&lt;15ms), without signing a transaction on every call. Ideal for n8n, Make, Zapier, Python, Node, and MCP.</p>

    <?php if ($isSandbox): ?>
    <div class="note" style="border-color:rgba(245,158,11,.5);background:rgba(245,158,11,.08);" data-i18n-html="pass.sandbox_note">
        <strong>Mercado Pago Sandbox Mode active:</strong> payments generated here are simulated for testing and do not perform real charges.
    </div>
    <?php endif; ?>

    <div class="email-row">
        <label for="mp-email" style="font-size:.9rem;color:var(--muted);" data-i18n="pass.email.label">Email for receipt (optional):</label>
        <input type="email" id="mp-email" class="input" placeholder="dev@yourcompany.com" data-i18n-placeholder="pass.email.placeholder" autocomplete="email" />
    </div>

    <!-- Painel Pix / Checkout Status -->
    <div id="mp-status-panel" class="panel" role="region" aria-live="polite">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <strong id="mp-panel-title" style="font-size:1.1rem;" data-i18n="pass.panel.title">Mercado Pago Payment</strong>
            <span id="mp-status-pill" class="status-pill" data-i18n="pass.panel.waiting">Awaiting payment...</span>
        </div>
        <p id="mp-panel-msg" style="margin:.5rem 0 0;font-size:.9rem;"></p>

        <div id="mp-pix-area" class="pix-layout" style="display:none;">
            <div class="qr-box"><img id="mp-qr-img" src="" alt="Pix QR Code for Grid402 Session Pass payment" /></div>
            <div class="pix-info">
                <div style="font-size:.88rem;color:var(--muted);" data-i18n="pass.pix.code_label">Pix Copy and Paste Code:</div>
                <div class="copy-row">
                    <input type="text" id="mp-pix-code" class="input" readonly aria-label="Pix Copy and Paste Code" />
                    <button type="button" class="btn btn-pix" style="width:auto;padding:.68rem 1rem;" onclick="copyText('mp-pix-code', this)" data-i18n="pass.pix.copy_btn">Copy Pix</button>
                </div>
                <div style="font-size:.82rem;color:var(--muted);" data-i18n-html="pass.pix.instructions">After paying in your bank app, your <code>pass_g402_...</code> token will appear here automatically in a few seconds. Do not close this tab.</div>
                <div id="mp-sandbox-actions" style="display:none;margin-top:.85rem;">
                    <button type="button" id="btn-mp-sandbox-approve" class="btn btn-card" style="width:auto;" onclick="approveSandboxOrder()" data-i18n="pass.pix.sandbox_approve">Simulate Pix Approval (Sandbox)</button>
                </div>
            </div>
        </div>

        <div id="mp-success-area" style="display:none;margin-top:1rem;">
            <div style="font-size:.9rem;color:var(--green);font-weight:700;" data-i18n="pass.success.title">Session Pass issued successfully!</div>
            <div id="mp-token-value" class="token-box"></div>
            <div style="display:flex;gap:.75rem;margin-top:.75rem;flex-wrap:wrap;align-items:center;">
                <button type="button" class="btn btn-pix" style="width:auto;" onclick="copyToken(this)" data-i18n="pass.success.copy_btn">Copy Token</button>
                <span id="mp-token-meta" style="font-size:.88rem;color:var(--muted);"></span>
            </div>
            <pre id="mp-token-snippet" style="margin-top:1rem;"></pre>
        </div>
    </div>

    <h2 data-i18n="pass.packages.title">Packages</h2>
    <div class="grid">
        <?php foreach ($packages as $tier => $pkg): ?>
        <div class="card <?php echo $tier === 'pro' ? 'featured' : ''; ?>">
            <div>
                <div class="tag"><?php echo htmlspecialchars($pkg['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="price">R$ <?php echo number_format((float) $pkg['amount_brl'], 2, ',', '.'); ?></div>
                <div class="price-sub" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.sub">or <?php echo (int) ($pkg['raw_usdc_units'] / 1000000); ?> USDC on Base</div>
                <div class="credits" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.credits"><?php echo number_format((int) $pkg['credits'], 0, '.', ','); ?> credits (<?php echo number_format((int) $pkg['credits'], 0, '.', ','); ?> standard req)</div>
                <p class="desc" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.desc">
                    <?php if ($tier === 'starter'): ?>
                        Ideal for individual developers, quick tests, and spot bots (1 credit = 0.0050 USDC). Valid for 30 days.
                    <?php elseif ($tier === 'pro'): ?>
                        For autonomous AI agents and continuous automation pipelines (1 credit = 0.0050 USDC). Most popular. Valid for 30 days.
                    <?php else: ?>
                        Agent fleets, corporate ERP systems, and high demand (5,000 + 1,000 bonus = 6,000 credits). Valid for 60 days.
                    <?php endif; ?>
                </p>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-pix" onclick="startMpPix('<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>', this)" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.btn_pix">Pay with Pix (R$ <?php echo number_format((float) $pkg['amount_brl'], 0, ',', '.'); ?>)</button>
                <button type="button" class="btn btn-card" onclick="startMpCheckoutPro('<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>', this)" data-i18n="pass.btn.card">Pay with Card (Mercado Pago)</button>
                <a class="btn btn-outline" href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/#topup" data-i18n="pass.btn.usdc">Pay with USDC (Web3)</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h2 data-i18n="pass.howto.title">How to use the token</h2>
    <pre>curl -s -H "X-Payment: pass_g402_YOUR_TOKEN_HERE" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"</pre>
    <p data-i18n-html="pass.howto.desc">Or set <code>GRID402_SESSION_PASS=pass_g402_...</code> in the MCP server (<code>grid402-mcp</code>) or in your n8n credentials.</p>
    <p><a class="btn btn-outline" style="width:auto;" href="/payment-dashboard" data-i18n="paydash.stmt.title">Session Pass Consumption Statement</a></p>
</div>

<script>
let mpCurrentOrderId = null;
let mpPollTimer = null;

function showPanel(title, msg, isOk = false) {
    const panel = document.getElementById('mp-status-panel');
    const pill = document.getElementById('mp-status-pill');
    panel.classList.add('active');
    document.getElementById('mp-panel-title').textContent = title;
    document.getElementById('mp-panel-msg').textContent = msg;
    pill.textContent = isOk ? 'Aprovado' : 'Aguardando pagamento...';
    pill.className = 'status-pill' + (isOk ? ' ok' : '');
}

function showIssuedPass(data) {
    if (mpPollTimer) { clearInterval(mpPollTimer); mpPollTimer = null; }
    document.getElementById('mp-pix-area').style.display = 'none';
    document.getElementById('mp-success-area').style.display = 'block';
    document.getElementById('mp-token-value').textContent = data.pass_token;
    document.getElementById('mp-token-meta').textContent = `${data.credits} créditos • Validade ${data.ttl_days || 30} dias` + (data.sandbox ? ' • SANDBOX' : '');
    document.getElementById('mp-token-snippet').textContent =
        `curl -s -H "X-Payment: ${data.pass_token}" "${window.location.origin}/api/v1/cnpj?cnpj=00000000000191"`;
    showPanel('Pagamento confirmado!', 'Seu Session Pass já está ativo e pronto para uso.', true);
}

async function pollOrderStatus(orderId, paymentId = null) {
    let url = '/api/v1/pass/mp-status?order_id=' + encodeURIComponent(orderId);
    if (paymentId) url += '&payment_id=' + encodeURIComponent(paymentId);
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.status === 'approved' && data.pass_token) {
            showIssuedPass(data);
        } else if (data.status === 'rejected' || data.status === 'cancelled' || data.status === 'expired') {
            if (mpPollTimer) { clearInterval(mpPollTimer); mpPollTimer = null; }
            showPanel('Pagamento não concluído', 'Status: ' + data.status + '. Gere uma nova cobrança se necessário.');
        }
    } catch (_) {}
}

function startPolling(orderId) {
    if (mpPollTimer) clearInterval(mpPollTimer);
    mpPollTimer = setInterval(() => pollOrderStatus(orderId), 3000);
}

async function startMpPix(tier, btn) {
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Gerando Pix...';
    document.getElementById('mp-success-area').style.display = 'none';
    try {
        const email = document.getElementById('mp-email').value.trim();
        const res = await fetch('/api/v1/pass/mp-pix', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tier, email })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'Erro ao gerar cobrança Pix.');
        }
        mpCurrentOrderId = data.order_id;
        showPanel(`Pix ${data.label} — R$ ${Number(data.amount_brl).toFixed(2).replace('.', ',')}`, 'Escaneie o QR Code ou copie o código Pix abaixo.');
        document.getElementById('mp-pix-area').style.display = 'flex';
        const mime = data.qr_mime || 'image/png';
        document.getElementById('mp-qr-img').src = data.qr_code_base64 ? `data:${mime};base64,${data.qr_code_base64}` : '';
        document.getElementById('mp-pix-code').value = data.qr_code || '';
        document.getElementById('mp-sandbox-actions').style.display = data.sandbox ? 'block' : 'none';
        startPolling(data.order_id);
        document.getElementById('mp-status-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
        showPanel('Não foi possível gerar o Pix', e.message || String(e));
        document.getElementById('mp-pix-area').style.display = 'none';
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function startMpCheckoutPro(tier, btn) {
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Abrindo Mercado Pago...';
    try {
        const email = document.getElementById('mp-email').value.trim();
        const res = await fetch('/api/v1/pass/mp-checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tier, email })
        });
        const data = await res.json();
        if (!res.ok || !data.ok || !data.checkout_url) {
            throw new Error(data.message || 'Erro ao iniciar Checkout Pro.');
        }
        window.location.href = data.checkout_url;
    } catch (e) {
        showPanel('Não foi possível abrir o Checkout Pro', e.message || String(e));
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function approveSandboxOrder() {
    if (!mpCurrentOrderId) return;
    const res = await fetch('/api/v1/pass/mp-sandbox-approve', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: mpCurrentOrderId })
    });
    const data = await res.json();
    if (data.ok && data.pass_token) {
        showIssuedPass(data);
    }
}

function copyText(inputId, btn) {
    const el = document.getElementById(inputId);
    navigator.clipboard.writeText(el.value).then(() => {
        const old = btn.textContent;
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = old, 1800);
    });
}

function copyToken(btn) {
    const val = document.getElementById('mp-token-value').textContent;
    navigator.clipboard.writeText(val).then(() => {
        const old = btn.textContent;
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = old, 1800);
    });
}

(async function initFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const mpOrder = params.get('mp_order');
    const paymentId = params.get('payment_id') || params.get('collection_id');
    const sandboxApprove = params.get('sandbox_approve');
    if (!mpOrder) return;
    mpCurrentOrderId = mpOrder;
    showPanel('Verificando pagamento...', 'Consultando confirmação da ordem ' + mpOrder + '...');
    if (sandboxApprove === '1') {
        await approveSandboxOrder();
        return;
    }
    await pollOrderStatus(mpOrder, paymentId);
    startPolling(mpOrder);
})();
</script>
<script src="/assets/js/i18n.js?v=3.2.0"></script>
</body>
</html>
