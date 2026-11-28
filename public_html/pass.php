<?php
// pass.php - Pagina dedicada de compra de Session Pass (Grid402)
// Suporta pagamento em BRL via Mercado Pago (Pix transparente + Checkout Pro) e Web3 (USDC na Base).
if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/pass-reengagement-banner.php';
require_once __DIR__ . '/includes/pass-queue-helper.php';
require_once __DIR__ . '/services/mercadopago.php';
require_once __DIR__ . '/services/multichain.php';
require_once __DIR__ . '/services/promo_pass.php';
require_once __DIR__ . '/metrics.php';

$userIdleStats = grid402_get_user_idle_pass_stats();

grid402_record_page_visit('/pass');

$domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
$packages = grid402_mp_packages();
$isSandbox = grid402_mp_is_sandbox();
$mpConfigured = $isSandbox || (grid402_mp_access_token() !== '');
$solanaEnabled = function_exists('grid402_is_solana_public_enabled') && grid402_is_solana_public_enabled();
$supportedNets = function_exists('grid402_public_networks') ? grid402_public_networks() : grid402_supported_networks();
$rapidApiAllUrl = 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities';
$requestedPromo = strtoupper(trim((string) ($_GET['promo'] ?? $_GET['code'] ?? '')));
$promoStatus = $requestedPromo !== '' ? grid402_promo_get_status($requestedPromo) : null;
$promoCampaign = (is_array($promoStatus) && !empty($promoStatus['ok'])) ? $promoStatus['campaign'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Buy Session Pass (Pix, Card or USDC)</title>
<?php require_once __DIR__ . '/includes/seo_head.php'; grid402_render_seo_head([
    'title' => 'Grid402 // Buy Session Pass (Pix, Card or USDC)',
    'description' => 'Buy a prepaid Grid402 Session Pass via Pix (from R$ 4.90), Card, or USDC across Base, Polygon, Arbitrum, Optimism & Ethereum for sub-15ms gasless M2M API calls.',
    'path' => '/pass',
    'card' => 'global',
    'locale' => 'en_US',
    'emit_canonical' => true,
    'emit_description' => true,
]); ?>
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
    <?php if (($userIdleStats['idle_passes'] ?? 0) > 0): ?>
    <?php echo grid402_pass_reengagement_banner([
        'idle_passes' => $userIdleStats['idle_passes'],
        'idle_usdc'   => $userIdleStats['idle_usdc'],
        'expires_in'  => $userIdleStats['expires_min_days'],
        'lang'        => 'en',
    ]); ?>
    <?php endif; ?>

    <h1 data-i18n-html="pass.title">Buy Session Pass <code>pass_g402_...</code></h1>
    <p data-i18n-html="pass.subtitle">Pay once in <strong>BRL (Pix or Card via Mercado Pago)</strong> or in <strong>USDC on the Base network</strong> and instantly receive a token loaded with thousands of requests (&lt;15ms), without signing a transaction on every call. Ideal for n8n, Make, Zapier, Python, Node, and MCP.</p>

    <!-- Cyber Monday / Promo Code Claim Box -->
    <section id="promo-claim-section" aria-label="Promo Code Redemption" style="margin:1.25rem 0; background:linear-gradient(135deg, rgba(0,82,255,.16), rgba(0,240,255,.10)); border:1px solid <?php echo $promoCampaign ? 'var(--cyan)' : 'rgba(0,240,255,.35)'; ?>; border-radius:12px; padding:1.25rem; <?php echo $promoCampaign ? 'box-shadow:0 8px 28px rgba(0,240,255,.16);' : ''; ?>">
        <?php if ($promoCampaign): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; margin-bottom:.45rem;">
                <strong style="font-size:1.08rem; color:#fff;">⚡ <?php echo htmlspecialchars((string) $promoCampaign['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <span id="promo-remaining-badge" class="status-pill <?php echo !empty($promoCampaign['active']) ? 'ok' : ''; ?>">
                    <?php echo !empty($promoCampaign['active']) ? ((int) $promoCampaign['remaining_claims'] . ' / ' . (int) $promoCampaign['max_claims'] . ' remaining') : (!empty($promoCampaign['exhausted']) ? 'Fully claimed' : 'Campaign ended'); ?>
                </span>
            </div>
            <p style="margin:.25rem 0 .85rem; color:#E2E8F0; font-size:.93rem;">
                <?php echo htmlspecialchars((string) $promoCampaign['description'], ENT_QUOTES, 'UTF-8'); ?>
                <br><span style="color:var(--muted); font-size:.84rem;">Code: <code><?php echo htmlspecialchars((string) $promoCampaign['code'], ENT_QUOTES, 'UTF-8'); ?></code> • Valid <?php echo (int) $promoCampaign['validity_days']; ?> days • Daily cap: <?php echo (int) $promoCampaign['daily_credit_limit']; ?> credits/day • Limit: 1 pass per IP (Zero-PII).</span>
            </p>
            <div style="display:flex; gap:.65rem; flex-wrap:wrap; align-items:center;">
                <input type="hidden" id="promo-code-input" value="<?php echo htmlspecialchars((string) $promoCampaign['code'], ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="button" id="btn-promo-claim" class="btn btn-card" style="width:auto; padding:.72rem 1.35rem;" onclick="claimPromoPass(this)" <?php echo empty($promoCampaign['active']) ? 'disabled' : ''; ?>>
                    ⚡ Claim <?php echo (int) $promoCampaign['credits']; ?> Free Credits Now (No Wallet Needed)
                </button>
                <span id="promo-feedback" style="font-size:.86rem; color:var(--muted);"></span>
            </div>
        <?php else: ?>
            <div style="display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                <div style="font-size:.9rem; color:#E2E8F0;">
                    <strong>🎁 Have a promo code?</strong> <span style="color:var(--muted);">Redeem free starter credits with zero signup or wallet required.</span>
                </div>
                <div style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
                    <input type="text" id="promo-code-input" class="input" style="min-width:170px; max-width:210px; text-transform:uppercase;" placeholder="e.g. MCPTRY50" value="<?php echo htmlspecialchars($requestedPromo, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Promo code" />
                    <button type="button" id="btn-promo-claim" class="btn btn-card" style="width:auto; padding:.68rem 1.1rem;" onclick="claimPromoPass(this)">Redeem Code</button>
                </div>
            </div>
            <div id="promo-feedback" style="margin-top:.5rem; font-size:.86rem; color:<?php echo ($requestedPromo !== '' && !$promoCampaign) ? '#F59E0B' : 'var(--muted)'; ?>;">
                <?php if ($requestedPromo !== '' && !$promoCampaign): ?>
                    Promo code <code><?php echo htmlspecialchars($requestedPromo, ENT_QUOTES, 'UTF-8'); ?></code> was not found.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($isSandbox): ?>
    <div class="note" style="border-color:rgba(245,158,11,.5);background:rgba(245,158,11,.08);" data-i18n-html="pass.sandbox_note">
        <strong>Mercado Pago Sandbox Mode active:</strong> payments generated here are simulated for testing and do not perform real charges.
    </div>
    <?php elseif (!$mpConfigured): ?>
    <div id="mp-unavailable-banner" class="note" style="border-color:rgba(239,68,68,.55);background:rgba(239,68,68,.10);color:#FCA5A5;">
        <strong>Checkout BRL (Mercado Pago / Pix) temporariamente indisponível neste servidor.</strong> Utilize pagamento imediato via <a href="#multichain-web3" style="color:#00F0FF;">USDC Web3 (5 redes EVM)</a> ou <a href="<?php echo htmlspecialchars($rapidApiAllUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" style="color:#00F0FF;">Cartão de Crédito em USD via RapidAPI</a>.
    </div>
    <?php endif; ?>

    <!-- Saida Fiat em USD via RapidAPI para visitantes internacionais -->
    <section id="rapidapi-usd-section" aria-label="International Credit Card USD via RapidAPI" style="margin:1.25rem 0; background:rgba(0,82,255,.10); border:1px solid rgba(0,240,255,.35); border-radius:12px; padding:1.1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.85rem;">
        <div style="flex:1; min-width:240px;">
            <strong style="color:#fff; font-size:1rem;" data-i18n="pass.usd.title">💳 International Credit Card (USD) — No Crypto Wallet or Pix Required</strong>
            <div style="font-size:.87rem; color:var(--muted); margin-top:.2rem;" data-i18n="pass.usd.desc">Outside Brazil? Subscribe in USD with any credit card on RapidAPI Hub (Free Basic 25/mo • Pro $9.90/mo for 1,500 calls • Ultra $29/mo for 6,000 calls).</div>
        </div>
        <a id="btn-pass-rapidapi-usd" class="btn btn-card" style="width:auto; padding:.7rem 1.2rem; white-space:nowrap;" href="<?php echo htmlspecialchars($rapidApiAllUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-i18n="pass.usd.cta">💳 Pay with Credit Card (USD) on RapidAPI →</a>
    </section>

    <div class="email-row">
        <label for="mp-email" style="font-size:.9rem;color:var(--muted);" data-i18n="pass.email.label">Email for Pix receipt (required for Pix/Card BRL):</label>
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
            <div style="display:flex;gap:.65rem;margin-top:.75rem;flex-wrap:wrap;align-items:center;">
                <button type="button" class="btn btn-pix" style="width:auto;" onclick="copyToken(this)" data-i18n="pass.success.copy_btn">Copy Token</button>
                <button type="button" class="btn btn-outline" style="width:auto;padding:.68rem 1rem;" onclick="copyElementText('mp-token-snippet', this)">Copy cURL + MCP</button>
                <button type="button" id="btn-test-issued-pass" class="btn btn-card" style="width:auto;padding:.68rem 1.05rem;" onclick="testIssuedPassNow(this)">⚡ Test Pass Now (1 credit)</button>
                <span id="mp-token-meta" style="font-size:.88rem;color:var(--muted);"></span>
            </div>
            <div id="mp-test-result" role="status" aria-live="polite" style="display:none;margin-top:.85rem;background:#050914;border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;"></div>
            <pre id="mp-token-snippet" style="margin-top:1rem;"></pre>
            <div style="display:flex;gap:.75rem;margin-top:.9rem;flex-wrap:wrap;align-items:center;">
                <a class="btn btn-outline" style="width:auto;padding:.55rem 1rem;" href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">⭐ Star Grid402 on GitHub (MCP &amp; n8n recipes)</a>
                <a class="btn btn-outline" style="width:auto;padding:.55rem 1rem;" href="/catalog">Browse all 25 APIs</a>
                <a class="btn btn-outline" style="width:auto;padding:.55rem 1rem;" href="/payment-dashboard">Open Usage Statement</a>
            </div>
        </div>
    </div>

    <h2 data-i18n="pass.packages.title">Packages</h2>
    <div class="note" style="border-color:rgba(16,185,129,.45);background:rgba(16,185,129,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
        <div>
            <strong style="color:#10B981;" data-i18n="pass.pix_banner.title">⚡ Instant Activation via Pix BRL (No Signup / Zero-PII):</strong>
            <span style="color:var(--text);" data-i18n-html="pass.pix_banner.desc"> Start from <strong>R$ 4,90 (50 credits / 5 KYB dossiers)</strong> or pick <strong>Starter R$ 12 (400 credits = R$ 0,30/dossier, best value)</strong>. Fill in your email above and click <em>Pay with Pix</em>.</span>
        </div>
        <button type="button" class="btn btn-pix" style="width:auto;padding:.55rem 1rem;" onclick="startMpPix('starter', this)" <?php echo !$mpConfigured ? 'disabled' : ''; ?> data-i18n="pass.pix_banner.cta">⚡ Generate R$ 12 Pix QR Now (400 credits)</button>
    </div>
    <div class="grid">
        <?php foreach ($packages as $tier => $pkg):
            $isMicro = ($tier === 'micro');
            $usdcInt = (int) ($pkg['raw_usdc_units'] / 1000000);
            $dossiersCount = intdiv((int) $pkg['credits'], 10);
        ?>
        <div class="card <?php echo $tier === 'starter' ? 'featured' : ''; ?>" data-tier-card="<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>">
            <div>
                <div class="tag"><?php echo htmlspecialchars($pkg['label'], ENT_QUOTES, 'UTF-8'); ?><?php if ($tier === 'starter'): ?> · MELHOR CUSTO-BENEFÍCIO<?php endif; ?></div>
                <div class="price">R$ <?php echo number_format((float) $pkg['amount_brl'], 2, ',', '.'); ?></div>
                <div class="price-sub" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.sub">
                    <?php if ($isMicro): ?>
                        Pix / Card BRL • 5 KYB Dossiers (90d trial tier)
                    <?php else: ?>
                        or <?php echo $usdcInt; ?> USDC (<?php echo count($supportedNets); ?> EVM chains) • R$ <?php echo number_format(((float) $pkg['amount_brl']) / max(1, $dossiersCount), 2, ',', '.'); ?>/dossier
                    <?php endif; ?>
                </div>
                <div class="credits" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.credits"><?php echo number_format((int) $pkg['credits'], 0, '.', ','); ?> credits (<?php echo $dossiersCount; ?> KYB dossiers or <?php echo number_format((int) $pkg['credits'], 0, '.', ','); ?> std calls)</div>
                <p class="desc" data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.desc">
                    <?php if ($isMicro): ?>
                        Low-friction Pix entry pack to validate up to 5 KYB 360º Dossiers or 50 standard API calls. Valid for 90 days.
                    <?php elseif ($tier === 'starter'): ?>
                        Best entry value: 8x more credits than Micro for only R$ 12 (R$ 0,30 per KYB Dossier; 1 credit = 0.0050 USDC). Valid for 180 days.
                    <?php elseif ($tier === 'pro'): ?>
                        For autonomous AI agents and continuous automation pipelines (1,000 base + 200 bonus = 1,200 credits; 16.7% off). Valid for 180 days.
                    <?php else: ?>
                        Agent fleets, corporate ERP systems, and high demand (4,000 base + 2,000 bonus = 6,000 credits; 33.3% off). Valid for 365 days.
                    <?php endif; ?>
                </p>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-pix" onclick="startMpPix('<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>', this)" <?php echo !$mpConfigured ? 'disabled' : ''; ?> data-i18n="pass.pkg.<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>.btn_pix">Pay with Pix (R$ <?php echo number_format((float) $pkg['amount_brl'], 2, ',', '.'); ?>)</button>
                <button type="button" class="btn btn-card" onclick="startMpCheckoutPro('<?php echo htmlspecialchars($tier, ENT_QUOTES, 'UTF-8'); ?>', this)" <?php echo !$mpConfigured ? 'disabled' : ''; ?> data-i18n="pass.btn.card">Pay with Card (Mercado Pago)</button>
                <?php if (!$isMicro): ?>
                <a class="btn btn-outline" href="#multichain-web3" data-i18n="pass.btn.usdc">Pay with USDC (Web3)</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <section aria-label="Transparent Billing Guarantees" style="margin:1.5rem 0; background:rgba(16,185,129,.06); border:1px solid rgba(16,185,129,.3); border-radius:12px; padding:1.15rem 1.25rem;">
        <h2 style="margin:0 0 .85rem; font-size:1.05rem; color:#34D399;" data-i18n="guarantees.title">Transparent Billing Guarantees</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:.9rem;">
            <div>
                <strong style="display:block; font-size:.9rem; color:#F8FAFC; margin-bottom:.25rem;" data-i18n="guarantees.g1.title">Errors Never Bill (Auto-Refund)</strong>
                <span style="font-size:.83rem; color:var(--muted);" data-i18n-html="guarantees.g1.desc">If any call fails (HTTP ≠ 200), Session Pass credits are refunded automatically and x402 facilitator settlement is aborted.</span>
            </div>
            <div>
                <strong style="display:block; font-size:.9rem; color:#F8FAFC; margin-bottom:.25rem;" data-i18n="guarantees.g2.title">Free Safe Retries (300s)</strong>
                <span style="font-size:.83rem; color:var(--muted);" data-i18n-html="guarantees.g2.desc">Send an <code>Idempotency-Key</code> header to safely retry requests after network timeouts with zero duplicate billing for 5 minutes.</span>
            </div>
            <div>
                <strong style="display:block; font-size:.9rem; color:#F8FAFC; margin-bottom:.25rem;" data-i18n="guarantees.g3.title">Clear Credit Consumption</strong>
                <span style="font-size:.83rem; color:var(--muted);" data-i18n-html="guarantees.g3.desc"><code>1 credit = 0.0050 USDC</code> on standard routes; composite bundles consume 2 to 10 credits (e.g., 400 credits = 400 standard calls or 40 KYB 360º Dossiers).</span>
            </div>
        </div>
    </section>

    <section id="multichain-web3" style="margin-top:2rem; background:var(--surface); border:1px solid rgba(0,240,255,.35); border-radius:14px; padding:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem;">
            <h2 style="margin:0; font-size:1.25rem;" data-i18n="pass.web3.title">⚡ Multi-Chain Web3 Payment (USDC)</h2>
            <span style="font-size:.78rem; padding:.2rem .65rem; border-radius:999px; background:rgba(0,240,255,.12); color:var(--cyan); border:1px solid rgba(0,240,255,.3);" data-i18n="pass.web3.badge"><?php echo count($supportedNets); ?> Supported Chains</span>
        </div>
        <p style="margin-top:0; color:var(--muted); font-size:.92rem;" data-i18n-html="pass.web3.subtitle">
            Choose your preferred blockchain to issue a Session Pass (<strong>2 USDC</strong> Starter • <strong>5 USDC</strong> Pro • <strong>20 USDC</strong> Fleet) with zero bridge fees:
        </p>

        <div id="pass-network-selector" role="radiogroup" aria-label="Select Web3 network" style="display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.1rem;">
            <button type="button" class="btn btn-outline pass-net-btn" data-chain="base" onclick="selectPassNetwork('base')" style="width:auto; padding:.45rem .85rem; border-color:var(--cyan); background:rgba(0,240,255,.15); color:#fff;">🔵 Base</button>
            <button type="button" class="btn btn-outline pass-net-btn" data-chain="arbitrum" onclick="selectPassNetwork('arbitrum')" style="width:auto; padding:.45rem .85rem;">🔷 Arbitrum</button>
            <button type="button" class="btn btn-outline pass-net-btn" data-chain="polygon" onclick="selectPassNetwork('polygon')" style="width:auto; padding:.45rem .85rem;">🟣 Polygon</button>
            <button type="button" class="btn btn-outline pass-net-btn" data-chain="optimism" onclick="selectPassNetwork('optimism')" style="width:auto; padding:.45rem .85rem;">🔴 Optimism</button>
            <button type="button" class="btn btn-outline pass-net-btn" data-chain="ethereum" onclick="selectPassNetwork('ethereum')" style="width:auto; padding:.45rem .85rem;">⟠ Ethereum</button>
            <?php if ($solanaEnabled && isset($supportedNets['solana'])): ?>
            <button type="button" class="btn btn-outline pass-net-btn" data-chain="solana" onclick="selectPassNetwork('solana')" style="width:auto; padding:.45rem .85rem;">◎ Solana (SPL)</button>
            <?php endif; ?>
        </div>

        <!-- 1-Click EIP-1193 Wallet Checkout (MetaMask, Coinbase Wallet, Rabby) -->
        <div id="pass-web3-wallet-box" style="background:rgba(0,82,255,.10); border:1px solid rgba(0,82,255,.4); border-radius:10px; padding:1rem; margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.65rem; margin-bottom:.75rem;">
                <strong style="font-size:.94rem; color:#F8FAFC;">1-Click Wallet Checkout (MetaMask / EIP-1193)</strong>
                <div id="pass-web3-tier-selector" role="radiogroup" aria-label="Select USDC package" style="display:flex; gap:.4rem; flex-wrap:wrap;">
                    <button type="button" class="btn btn-outline pass-tier-btn" data-tier="starter" data-usdc="2" onclick="selectWeb3Tier('starter', 2)" style="width:auto; padding:.38rem .72rem; font-size:.82rem; border-color:var(--cyan); background:rgba(0,240,255,.15); color:#fff;">Starter — 2 USDC (400 cr)</button>
                    <button type="button" class="btn btn-outline pass-tier-btn" data-tier="pro" data-usdc="5" onclick="selectWeb3Tier('pro', 5)" style="width:auto; padding:.38rem .72rem; font-size:.82rem;">Pro — 5 USDC (1,200 cr)</button>
                    <button type="button" class="btn btn-outline pass-tier-btn" data-tier="fleet" data-usdc="20" onclick="selectWeb3Tier('fleet', 20)" style="width:auto; padding:.38rem .72rem; font-size:.82rem;">Fleet — 20 USDC (6,000 cr)</button>
                </div>
            </div>
            <div style="display:flex; gap:.65rem; flex-wrap:wrap; align-items:center;">
                <button type="button" id="btn-pass-wallet-pay" class="btn btn-card" style="width:auto; padding:.7rem 1.25rem;" onclick="connectAndPayWeb3Pass(this)">🦊 Connect Wallet &amp; Pay (MetaMask / EIP-1193)</button>
                <span id="pass-wallet-status" style="font-size:.84rem; color:#CBD5E1;">Sends official USDC transfer on the selected EVM chain and auto-issues your pass.</span>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:.85rem; margin-bottom:1rem;">
            <div style="background:#060B19; border:1px solid var(--border); border-radius:10px; padding:.85rem 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:.35rem;">
                    <span style="font-size:.78rem; color:var(--muted);">EVM Wallet (Base, Arbitrum, Polygon, Optimism, Ethereum):</span>
                    <button type="button" class="btn btn-outline" style="width:auto; padding:.22rem .55rem; font-size:.75rem;" onclick="copyElementText('pass-evm-addr', this)" aria-label="Copy EVM wallet address">Copy EVM</button>
                </div>
                <code id="pass-evm-addr" style="color:var(--cyan); font-size:.82rem; word-break:break-all;"><?php echo htmlspecialchars((string) $supportedNets['base']['recipient'], ENT_QUOTES, 'UTF-8'); ?></code>
            </div>
            <?php if ($solanaEnabled && isset($supportedNets['solana'])): ?>
            <div style="background:#060B19; border:1px solid var(--border); border-radius:10px; padding:.85rem 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:.35rem;">
                    <span style="font-size:.78rem; color:var(--muted);">Solana Wallet (SPL USDC):</span>
                    <button type="button" class="btn btn-outline" style="width:auto; padding:.22rem .55rem; font-size:.75rem;" onclick="copyElementText('pass-sol-addr', this)" aria-label="Copy Solana wallet address">Copy Solana</button>
                </div>
                <code id="pass-sol-addr" style="color:#10B981; font-size:.82rem; word-break:break-all;"><?php echo htmlspecialchars((string) $supportedNets['solana']['recipient'], ENT_QUOTES, 'UTF-8'); ?></code>
            </div>
            <?php endif; ?>
        </div>

        <div id="pass-selected-net-info" style="font-size:.85rem; color:#CBD5E1; margin-bottom:.85rem;">
            Active chain: <strong style="color:var(--cyan);">Base Mainnet</strong> • USDC Contract: <code><?php echo htmlspecialchars((string) $supportedNets['base']['usdc_contract'], ENT_QUOTES, 'UTF-8'); ?></code>
        </div>

        <div style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:center;">
            <input type="text" id="pass-mc-tx-input" placeholder="<?php echo $solanaEnabled ? 'Paste your USDC tx_hash (0x...) or Solana Base58 signature' : 'Paste your EVM USDC tx_hash (0x...)'; ?>" aria-label="USDC transaction hash" style="flex:1; min-width:240px; padding:.65rem .85rem; border-radius:8px; border:1px solid var(--border); background:#060B19; color:#fff; font-family:monospace; font-size:.86rem;">
            <button type="button" class="btn btn-pix" style="width:auto;" onclick="validateMultiChainPass(this)">⚡ Validate Transaction &amp; Issue Pass</button>
        </div>
        <div id="pass-mc-feedback" style="margin-top:.65rem; font-size:.86rem; color:var(--muted);"></div>
    </section>

    <h2 data-i18n="pass.howto.title">How to use the token</h2>
    <pre>curl -s -H "X-Payment: pass_g402_YOUR_TOKEN_HERE" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"</pre>
    <p data-i18n-html="pass.howto.desc">Or set <code>GRID402_SESSION_PASS=pass_g402_...</code> in the MCP server (<code>grid402-mcp</code>) or in your n8n credentials.</p>
    <p><a class="btn btn-outline" style="width:auto;" href="/payment-dashboard" data-i18n="paydash.stmt.title">Session Pass Consumption Statement</a></p>
</div>

<script>
const PASS_MULTICHAIN = <?php echo json_encode($supportedNets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
let selectedPassChain = 'base';
let selectedWeb3Tier = 'starter';
let selectedWeb3Usdc = 2;
let mpCurrentOrderId = null;
let mpPollTimer = null;
let lastIssuedPassToken = '';

function selectWeb3Tier(tier, usdc) {
    selectedWeb3Tier = tier;
    selectedWeb3Usdc = Number(usdc) || 2;
    document.querySelectorAll('.pass-tier-btn').forEach(btn => {
        const active = btn.getAttribute('data-tier') === tier;
        btn.style.borderColor = active ? 'var(--cyan)' : 'var(--border)';
        btn.style.background = active ? 'rgba(0,240,255,.15)' : 'transparent';
        btn.style.color = active ? '#fff' : 'var(--text)';
    });
}

function selectPassNetwork(slug) {
    if (!PASS_MULTICHAIN[slug]) return;
    selectedPassChain = slug;
    const cfg = PASS_MULTICHAIN[slug];
    document.querySelectorAll('.pass-net-btn').forEach(btn => {
        const active = btn.getAttribute('data-chain') === slug;
        btn.style.borderColor = active ? 'var(--cyan)' : 'var(--border)';
        btn.style.background = active ? 'rgba(0,240,255,.15)' : 'transparent';
        btn.style.color = active ? '#fff' : 'var(--text)';
    });
    const info = document.getElementById('pass-selected-net-info');
    if (info) {
        info.innerHTML = 'Active chain: <strong style="color:var(--cyan);">' + cfg.display_name + '</strong> • USDC Contract/Mint: <code>' + cfg.usdc_contract + '</code>';
    }
    const inp = document.getElementById('pass-mc-tx-input');
    if (inp) {
        inp.placeholder = cfg.type === 'solana'
            ? 'Paste the Base58 signature of your Solana SPL USDC transfer'
            : ('Paste the tx_hash (0x...) of your USDC transfer on ' + cfg.display_name);
    }
}

async function connectAndPayWeb3Pass(btn) {
    const statusEl = document.getElementById('pass-wallet-status');
    const fb = document.getElementById('pass-mc-feedback');
    const inp = document.getElementById('pass-mc-tx-input');
    const cfg = PASS_MULTICHAIN[selectedPassChain] || PASS_MULTICHAIN.base;

    if (cfg.type === 'solana') {
        if (statusEl) {
            statusEl.style.color = '#F59E0B';
            statusEl.textContent = 'Solana uses SPL wallets (Phantom/Solflare). Copy the Solana address below, transfer ' + selectedWeb3Usdc + ' USDC (SPL), then paste the Base58 signature to activate.';
        }
        return;
    }

    const provider = window.ethereum;
    if (!provider || typeof provider.request !== 'function') {
        if (statusEl) {
            statusEl.style.color = '#F59E0B';
            statusEl.textContent = 'No EIP-1193 wallet detected (MetaMask, Coinbase Wallet, Rabby). Copy the EVM address below to transfer USDC manually and paste the tx_hash.';
        }
        return;
    }

    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Connecting wallet...';
    if (statusEl) { statusEl.style.color = '#CBD5E1'; statusEl.textContent = '1/3 Requesting account access...'; }

    try {
        const accounts = await provider.request({ method: 'eth_requestAccounts', params: [] });
        if (!accounts || accounts.length === 0) {
            throw new Error('No wallet account was connected.');
        }
        const fromAddr = accounts[0];
        try {
            const wcClean = String(fromAddr || '').trim().toLowerCase();
            if (/^0x[a-f0-9]{40}$/.test(wcClean) && (!window.sessionStorage || window.sessionStorage.getItem('g402_wc_' + wcClean) !== '1')) {
                if (window.sessionStorage) window.sessionStorage.setItem('g402_wc_' + wcClean, '1');
                fetch('/api/v1/growth/wallet-connect', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ address: wcClean }) }).catch(() => {});
            }
        } catch (wcErr) {}

        if (statusEl) statusEl.textContent = '2/3 Switching network to ' + cfg.display_name + '...';
        try {
            await provider.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: cfg.chain_id_hex }],
            });
        } catch (switchErr) {
            if (switchErr && switchErr.code === 4902) {
                const explorerBase = String(cfg.explorer_tx_url || '').replace(/\/tx\/$/, '');
                await provider.request({
                    method: 'wallet_addEthereumChain',
                    params: [{
                        chainId: cfg.chain_id_hex,
                        chainName: cfg.display_name,
                        nativeCurrency: cfg.native_currency || { name: 'Ether', symbol: 'ETH', decimals: 18 },
                        rpcUrls: Array.isArray(cfg.rpcs) && cfg.rpcs.length ? [cfg.rpcs[0]] : [],
                        blockExplorerUrls: explorerBase ? [explorerBase] : [],
                    }],
                });
            } else {
                throw switchErr;
            }
        }

        const amountRaw = Math.round(selectedWeb3Usdc * 1e6);
        const recipientHex = String(cfg.recipient || '').toLowerCase().replace(/^0x/, '').padStart(64, '0');
        const amountHex = amountRaw.toString(16).padStart(64, '0');
        const callData = '0xa9059cbb' + recipientHex + amountHex;

        btn.textContent = 'Confirm ' + selectedWeb3Usdc + ' USDC in wallet...';
        if (statusEl) statusEl.textContent = '3/3 Approve the ' + selectedWeb3Usdc + ' USDC transfer on ' + cfg.display_name + ' in your wallet.';

        const txHash = await provider.request({
            method: 'eth_sendTransaction',
            params: [{
                from: fromAddr,
                to: cfg.usdc_contract,
                data: callData,
            }],
        });

        if (inp) inp.value = txHash;
        btn.textContent = 'Confirming on-chain...';
        if (statusEl) {
            statusEl.style.color = '#00F0FF';
            statusEl.textContent = 'Transaction broadcast (' + txHash.slice(0, 12) + '...). Waiting for RPC confirmation to issue your Session Pass...';
        }

        let issued = null;
        let lastErr = 'Waiting for block confirmation...';
        for (let attempt = 1; attempt <= 5; attempt++) {
            await new Promise(r => setTimeout(r, attempt === 1 ? 3500 : 3000));
            const res = await fetch('/api/v1/pass-emitir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ txHash, chain: selectedPassChain }),
            });
            const data = await res.json();
            if (data && data.ok && data.pass_token) {
                issued = data;
                break;
            }
            lastErr = (data && (data.message || data.error)) ? (data.message || data.error) : lastErr;
            if (res.status !== 404) {
                break;
            }
        }

        if (issued) {
            if (statusEl) { statusEl.style.color = '#10B981'; statusEl.textContent = '✓ Payment confirmed on ' + cfg.display_name + '! Your Session Pass is ready above.'; }
            if (fb) fb.textContent = '';
            showIssuedPass({
                pass_token: issued.pass_token,
                credits: issued.credits_total,
                ttl_days: issued.ttl_days || (issued.tier === 'fleet' ? 365 : 180),
                sandbox: false,
            });
            const panel = document.getElementById('mp-status-panel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            if (statusEl) {
                statusEl.style.color = '#F59E0B';
                statusEl.textContent = 'Tx sent (' + txHash.slice(0, 12) + '...) and prefilled below. Wait a few seconds for block propagation and click Validate Transaction.';
            }
            if (fb) fb.textContent = lastErr;
        }
    } catch (err) {
        if (statusEl) {
            statusEl.style.color = '#F59E0B';
            statusEl.textContent = 'Wallet checkout cancelled or failed: ' + (err && err.message ? err.message : String(err));
        }
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function validateMultiChainPass(btn) {
    const inp = document.getElementById('pass-mc-tx-input');
    const fb = document.getElementById('pass-mc-feedback');
    const txHash = inp ? inp.value.trim() : '';
    if (!txHash) {
        if (fb) fb.textContent = 'Please enter the USDC transaction hash or Solana signature.';
        return;
    }
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Validating on-chain...';
    if (fb) fb.textContent = 'Auditing transaction on ' + (PASS_MULTICHAIN[selectedPassChain]?.display_name || selectedPassChain) + '...';
    try {
        const res = await fetch('/api/v1/pass-emitir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ txHash, chain: selectedPassChain }),
        });
        const data = await res.json();
        if (data && data.ok && data.pass_token) {
            if (fb) fb.textContent = data.idempotent ? '✓ Existing Session Pass recovered idempotently.' : '';
            showIssuedPass({
                pass_token: data.pass_token,
                credits: data.credits_total,
                ttl_days: data.ttl_days || (data.tier === 'fleet' ? 365 : 180),
                sandbox: false,
            });
            const panel = document.getElementById('mp-status-panel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            if (fb) fb.textContent = 'Error: ' + (data.message || data.error || 'Transaction not confirmed yet.');
        }
    } catch (e) {
        if (fb) fb.textContent = 'Network error while querying /api/v1/pass-emitir.';
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

function showPanel(title, msg, isOk = false) {
    const panel = document.getElementById('mp-status-panel');
    const pill = document.getElementById('mp-status-pill');
    panel.classList.add('active');
    document.getElementById('mp-panel-title').textContent = title;
    document.getElementById('mp-panel-msg').textContent = msg;
    pill.textContent = isOk ? 'Active' : 'Awaiting payment...';
    pill.className = 'status-pill' + (isOk ? ' ok' : '');
}

function showIssuedPass(data) {
    if (mpPollTimer) { clearInterval(mpPollTimer); mpPollTimer = null; }
    lastIssuedPassToken = data.pass_token || '';
    const testResultEl = document.getElementById('mp-test-result');
    if (testResultEl) {
        testResultEl.style.display = 'none';
        testResultEl.innerHTML = '';
    }
    document.getElementById('mp-pix-area').style.display = 'none';
    document.getElementById('mp-success-area').style.display = 'block';
    document.getElementById('mp-token-value').textContent = data.pass_token;
    const extraCap = data.daily_credit_limit ? ` • Daily cap ${data.daily_credit_limit} cr/day` : '';
    document.getElementById('mp-token-meta').textContent = `${data.credits} credits • Valid ${data.ttl_days || 180} days${extraCap}` + (data.sandbox ? ' • SANDBOX' : '');
    const curlLine = data.curl_snippet || `curl -s -H "X-Payment: ${data.pass_token}" "${window.location.origin}/api/v1/forex"`;
    const mcpLine = data.mcp_snippet || `GRID402_SESSION_PASS=${data.pass_token} npx -y grid402-mcp`;
    document.getElementById('mp-token-snippet').textContent = `# cURL (sub-15ms):\n${curlLine}\n\n# MCP Server (Claude Desktop / Cursor / Windsurf):\n${mcpLine}\n\n# HTTP Header (n8n / Python / Node):\nX-Payment: ${data.pass_token}`;
    showPanel(
        data.promo_code ? `Promo ${data.promo_code} Activated!` : 'Payment Confirmed!',
        data.promo_code ? 'Your free promotional Session Pass is active and ready to use across all 25 APIs and 27 MCP tools.' : 'Your Session Pass is active and ready to use.',
        true
    );
    // Persiste o token na chave unificada (localStorage + cookie) para reconhecimento em todas as paginas
    if (window.Grid402PassStorage && typeof window.Grid402PassStorage.save === 'function') {
        window.Grid402PassStorage.save(data.pass_token);
    } else {
        try {
            localStorage.setItem('grid402_active_pass_token', data.pass_token);
            document.cookie = 'g402_pass_token=' + encodeURIComponent(data.pass_token) + '; path=/; max-age=2592000; SameSite=Lax';
            if (location.protocol === 'https:') {
                document.cookie = 'g402_pass_token=' + encodeURIComponent(data.pass_token) + '; path=/; max-age=2592000; Secure; SameSite=Lax';
            }
        } catch (_) {}
    }
}

async function testIssuedPassNow(btn) {
    const token = (lastIssuedPassToken || (document.getElementById('mp-token-value')?.textContent || '')).trim();
    const out = document.getElementById('mp-test-result');
    if (!token || !out) return;
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Testing /api/v1/forex...';
    out.style.display = 'block';
    out.style.borderColor = 'var(--border)';
    out.innerHTML = '<span style="color:var(--muted);font-size:.86rem;">Calling <code>/api/v1/forex</code> with <code>X-Payment</code>...</span>';
    try {
        const t0 = performance.now();
        const res = await fetch('/api/v1/forex', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Payment': token,
            },
        });
        const elapsedMs = Math.round(performance.now() - t0);
        const remaining = res.headers.get('X-Pass-Credits-Remaining');
        const rawText = await res.text();
        let parsed = null;
        try { parsed = rawText ? JSON.parse(rawText) : null; } catch (_) {}

        if (res.ok && parsed) {
            out.style.borderColor = 'var(--green)';
            const creditsInfo = remaining !== null ? ` • Credits remaining: <strong>${remaining}</strong>` : '';
            const previewJson = JSON.stringify(parsed, null, 2).split('\n').slice(0, 14).join('\n');
            out.innerHTML = `<div style="font-size:.85rem;color:var(--green);font-weight:700;margin-bottom:.45rem;">✓ Live call succeeded (HTTP ${res.status} in ${elapsedMs}ms${creditsInfo})</div><pre style="margin:0;max-height:220px;overflow:auto;">${previewJson.replace(/</g, '&lt;')}</pre>`;
        } else {
            out.style.borderColor = 'var(--warn)';
            const errMsg = (parsed && (parsed.message || parsed.error)) ? (parsed.message || parsed.error) : (`HTTP ${res.status}`);
            out.innerHTML = `<div style="font-size:.85rem;color:var(--warn);font-weight:700;">Test returned HTTP ${res.status}: ${String(errMsg).replace(/</g, '&lt;')}</div>`;
        }
    } catch (err) {
        out.style.borderColor = '#EF4444';
        out.innerHTML = `<div style="font-size:.85rem;color:#EF4444;font-weight:700;">Network error while testing pass: ${String(err && err.message ? err.message : err).replace(/</g, '&lt;')}</div>`;
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function claimPromoPass(btn) {
    const inp = document.getElementById('promo-code-input');
    const fb = document.getElementById('promo-feedback');
    const badge = document.getElementById('promo-remaining-badge');
    const code = (inp ? inp.value : '').trim().toUpperCase();
    if (!code) {
        if (fb) { fb.style.color = '#F59E0B'; fb.textContent = 'Enter a promo code first (e.g. MCPTRY50).'; }
        return;
    }
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Issuing Session Pass...';
    if (fb) { fb.style.color = 'var(--muted)'; fb.textContent = 'Validating promo code...'; }
    try {
        const res = await fetch('/api/v1/pass/promo-claim', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code })
        });
        const data = await res.json();
        if (res.ok && data.ok && data.pass_token) {
            if (fb) { fb.style.color = '#10B981'; fb.textContent = '✓ ' + data.credits + ' free credits issued! Copy your token below.'; }
            if (badge && typeof data.remaining_claims === 'number' && typeof data.max_claims === 'number') {
                badge.textContent = data.remaining_claims + ' / ' + data.max_claims + ' remaining';
            }
            showIssuedPass(data);
            const panel = document.getElementById('mp-status-panel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            if (fb) { fb.style.color = '#F59E0B'; fb.textContent = data.message || data.error || 'Unable to redeem promo code.'; }
        }
    } catch (e) {
        if (fb) { fb.style.color = '#EF4444'; fb.textContent = 'Network error while claiming promo code. Please try again.'; }
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function pollOrderStatus(orderId, paymentId = null) {
    let url = '/api/v1/pass/mp-status?order_id=' + encodeURIComponent(orderId);
    if (paymentId) url += '&payment_id=' + encodeURIComponent(paymentId);
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.status === 'approved' && data.pass_token) {
            showIssuedPass(data);
            return true;
        } else if (data.status === 'rejected' || data.status === 'cancelled' || data.status === 'expired') {
            if (mpPollTimer) { clearInterval(mpPollTimer); mpPollTimer = null; }
            showPanel('Payment not completed', 'Status: ' + data.status + '. Please generate a new payment if needed.');
            return true;
        }
    } catch (_) {}
    return false;
}

function startPolling(orderId) {
    if (mpPollTimer) clearInterval(mpPollTimer);
    mpPollTimer = setInterval(() => pollOrderStatus(orderId), 3000);
}

async function startMpPix(tier, btn) {
    const emailInput = document.getElementById('mp-email');
    const email = emailInput ? emailInput.value.trim() : '';
    const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    if (!isValidEmail || email.toLowerCase().endsWith('@grid402.online')) {
        showPanel('Informe um e-mail válido para o Pix', 'Digite seu e-mail no campo acima para emitir o QR Code Pix e receber o comprovante com segurança.');
        document.getElementById('mp-pix-area').style.display = 'none';
        if (emailInput) {
            emailInput.focus();
            emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating Pix...';
    document.getElementById('mp-success-area').style.display = 'none';
    try {
        const res = await fetch('/api/v1/pass/mp-pix', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tier, email })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'Unable to generate Pix charge.');
        }
        mpCurrentOrderId = data.order_id;
        showPanel(`Pix ${data.label} — R$ ${Number(data.amount_brl).toFixed(2).replace('.', ',')}`, 'Scan the QR Code or copy the Pix code below.');
        document.getElementById('mp-pix-area').style.display = 'flex';
        const mime = data.qr_mime || 'image/png';
        document.getElementById('mp-qr-img').src = data.qr_code_base64 ? `data:${mime};base64,${data.qr_code_base64}` : '';
        document.getElementById('mp-pix-code').value = data.qr_code || '';
        document.getElementById('mp-sandbox-actions').style.display = data.sandbox ? 'block' : 'none';
        startPolling(data.order_id);
        document.getElementById('mp-status-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
        showPanel('Unable to generate Pix', e.message || String(e));
        document.getElementById('mp-pix-area').style.display = 'none';
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function startMpCheckoutPro(tier, btn) {
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Opening Mercado Pago...';
    try {
        const email = document.getElementById('mp-email').value.trim();
        const res = await fetch('/api/v1/pass/mp-checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tier, email })
        });
        const data = await res.json();
        if (!res.ok || !data.ok || !data.checkout_url) {
            throw new Error(data.message || 'Unable to start Checkout Pro.');
        }
        window.location.href = data.checkout_url;
    } catch (e) {
        showPanel('Unable to open Checkout Pro', e.message || String(e));
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
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = old, 1800);
    });
}

function copyElementText(elementId, btn) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const text = (el.textContent || '').trim();
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const old = btn.textContent;
        btn.textContent = '✓ Copied!';
        setTimeout(() => btn.textContent = old, 1800);
    });
}

function copyToken(btn) {
    const val = document.getElementById('mp-token-value').textContent;
    navigator.clipboard.writeText(val).then(() => {
        const old = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = old, 1800);
    });
}

(async function initFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const mpOrder = params.get('mp_order');
    const paymentId = params.get('payment_id') || params.get('collection_id');
    const sandboxApprove = params.get('sandbox_approve');
    if (mpOrder) {
        mpCurrentOrderId = mpOrder;
        showPanel('Checking payment...', 'Querying confirmation for order ' + mpOrder + '...');
        if (sandboxApprove === '1') {
            await approveSandboxOrder();
            return;
        }
        const isTerminal = await pollOrderStatus(mpOrder, paymentId);
        if (!isTerminal) {
            startPolling(mpOrder);
        }
        return;
    }

    // Destaque visual do plano solicitado na URL sem disparar cobranca Pix automaticamente antes da leitura
    const reqTier = (params.get('tier') || '').toLowerCase();
    const reqMethod = (params.get('method') || '').toLowerCase();
    const autoPix = params.get('autopix') === '1' || params.get('autopix') === 'true';
    if (['micro', 'starter', 'pro', 'fleet'].includes(reqTier)) {
        const targetCard = document.querySelector(`.card[data-tier-card="${reqTier}"]`);
        if (targetCard) {
            targetCard.classList.add('featured');
            targetCard.style.outline = '2px solid var(--green)';
            if (reqMethod === 'pix' || autoPix) {
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
})();
</script>
<script src="/assets/js/pass_storage.js?v=3.5.0"></script>
<script src="/assets/js/i18n.js?v=3.5.0"></script>
</body>
</html>
