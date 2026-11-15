<?php

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/growth.php';
require_once __DIR__ . '/services/pass.php';
$token = (string)($_GET['token'] ?? '');
$pass = ($token !== '') ? grid402_get_session_pass_status($token) : null;
$metrics = grid402_get_metrics();
$passesStats = grid402_get_passes_stats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 Payment Dashboard</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --ok:#10B981; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; }
        main { max-width:980px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        a { color:var(--cyan); }
        input { background:#111C38; border:1px solid var(--border); border-radius:6px; color:#fff; padding:.55rem; width:100%; }
        button { margin-top:.6rem; padding:.55rem 1rem; border:0; border-radius:7px; background:var(--ok); color:#04121A; font-weight:700; cursor:pointer; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem; margin-top:1rem; }
        .card { border:1px solid var(--border); background:var(--surface); border-radius:10px; padding:1.1rem; }
        code { color:var(--cyan); }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '', 'show_lang' => true]); ?>
<main>
    <h1 data-i18n="paydash.title">Payment Dashboard</h1>
    <p data-i18n="paydash.subtitle">Tracking hub for individual x402 payments and Session Pass. Both products are distinct and displayed here with separate rules.</p>

    <div class="grid">
        <div class="card">
            <h2 data-i18n="paydash.ppr.title">Pay-per-Request</h2>
            <p data-i18n="paydash.ppr.desc">Each call is paid directly via x402/USDC. Ideal for low volume without preloading.</p>
            <p><a href="/catalog" data-i18n="paydash.ppr.link">View pricing →</a></p>
        </div>
        <div class="card">
            <h2 data-i18n="paydash.pass.title">Agent Pass</h2>
            <p data-i18n="paydash.pass.desc">Preloaded credits. Ideal for recurring consumption with &lt;15ms latency.</p>
            <p><a href="/pass" data-i18n="paydash.pass.link">Buy pass →</a></p>
        </div>
    </div>

    <h2 data-i18n="paydash.status.title">Pass Status (optional)</h2>
    <form method="get" action="/payment-dashboard">
        <label for="token" data-i18n="paydash.status.label">Token pass_g402... (optional)</label>
        <input id="token" name="token" value="<?php echo htmlspecialchars($token); ?>" placeholder="pass_g402_...">
        <button type="submit" data-i18n="paydash.status.btn">Check</button>
    </form>

    <?php if ($pass && !empty($pass['ok'])): ?>
        <?php
            $remaining = (int)($pass['credits_remaining'] ?? 0);
            $growthCfg = function_exists('grid402_growth_read') ? (grid402_growth_read()['config'] ?? []) : [];
            $lowBalanceThreshold = (int)($growthCfg['low_balance_threshold'] ?? 10);
            $isLow = $remaining <= $lowBalanceThreshold;
        ?>
        <?php if ($isLow): ?>
            <div class="card" style="margin-top:1rem;border-color:#F59E0B;background:#241A06;">
                <h3 style="color:#FCD34D;margin-top:0;" data-i18n="paydash.low.title">Low balance</h3>
                <p style="color:#FDE68A;"><strong><?php echo $remaining; ?> credit(s)</strong> remaining on your Session Pass (warning threshold: <?php echo $lowBalanceThreshold; ?>).</p>
                <p><a class="btn" href="/pass" style="display:inline-block;background:#F59E0B;color:#1F2937;text-decoration:none;font-weight:700;padding:.5rem 1rem;border-radius:8px;" data-i18n="paydash.low.btn">Top up Session Pass →</a></p>
            </div>
        <?php endif; ?>
        <div class="card" style="margin-top:1rem;">
            <h3 data-i18n="paydash.active.title">Active Pass</h3>
            <p><strong data-i18n="paydash.active.remaining">Remaining credits:</strong> <?php echo (int)($pass['credits_remaining'] ?? 0); ?> / <?php echo (int)($pass['credits_total'] ?? 0); ?></p>
            <p><strong data-i18n="paydash.active.expires">Expires:</strong> <?php echo htmlspecialchars((string)($pass['expires_at'] ?? '')); ?></p>
            <p><strong>Tier:</strong> <?php echo htmlspecialchars((string)($pass['tier'] ?? '')); ?></p>
        </div>
    <?php elseif ($token !== ''): ?>
        <p style="color:#F59E0B;margin-top:1rem;" data-i18n="paydash.invalid">Invalid token or pass not found.</p>
    <?php endif; ?>

    <h2 data-i18n="paydash.summary.title">Hub summary</h2>
    <p><strong data-i18n="paydash.summary.calls">Recorded calls:</strong> <?php echo (int)($metrics['total_requests'] ?? 0); ?></p>
    <p><strong>Revenue:</strong> <?php echo number_format((float)($metrics['total_revenue_usdc'] ?? 0), 4); ?> USDC</p>
    <p><strong data-i18n="paydash.summary.passes">Issued passes:</strong> <?php echo (int)($passesStats['total_issued'] ?? 0); ?></p>
</main>
<script src="/assets/js/i18n.js?v=3.2.0"></script>
</body>
</html>
