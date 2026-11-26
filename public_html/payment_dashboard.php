<?php

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/pass-reengagement-banner.php';
require_once __DIR__ . '/includes/pass-queue-helper.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/growth.php';
require_once __DIR__ . '/services/pass.php';

$userIdleStats = grid402_get_user_idle_pass_stats();

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' && !empty($_COOKIE['g402_pass_token'])) {
    $cookieTok = trim((string) $_COOKIE['g402_pass_token']);
    if (preg_match('/^pass_g402_[a-z0-9_]{8,24}_[a-f0-9]{16}$/i', $cookieTok) === 1) {
        $token = $cookieTok;
    }
}
$filterEndpoint = trim((string) ($_GET['endpoint'] ?? ''));
$filterOutcome = trim((string) ($_GET['outcome'] ?? ''));

$pass = ($token !== '') ? grid402_get_session_pass_status($token) : null;
$statement = ($pass && !empty($pass['ok']))
    ? grid402_get_session_pass_statement($token, ['endpoint' => $filterEndpoint, 'outcome' => $filterOutcome])
    : null;

$metrics = grid402_get_metrics();
$passesStats = grid402_get_passes_stats();
$utilityServices = grid402_get_utility_services();

$baseQuery = [
    'token' => $token,
];
if ($filterEndpoint !== '') {
    $baseQuery['endpoint'] = $filterEndpoint;
}
if ($filterOutcome !== '') {
    $baseQuery['outcome'] = $filterOutcome;
}
$csvUrl = '/api/v1/pass/statement?' . http_build_query(array_merge($baseQuery, ['format' => 'csv']));
$jsonUrl = '/api/v1/pass/statement?' . http_build_query(array_merge($baseQuery, ['format' => 'json']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=2">
    <title>Grid402 Payment Dashboard &amp; Session Pass Statement</title>
    <meta name="description" content="Track x402 Pay-per-Request volume and audit Session Pass balance, Spend Controls, and Zero-PII consumption statements in CSV or JSON.">
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --ok:#10B981; --warn:#F59E0B; --danger:#EF4444; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.55; }
        main { max-width:1040px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        a { color:var(--cyan); }
        input, select { background:#111C38; border:1px solid var(--border); border-radius:6px; color:#fff; padding:.55rem .7rem; width:100%; font-size:.92rem; }
        label { display:block; font-size:.86rem; color:var(--muted); margin-bottom:.3rem; }
        button, .btn-link { display:inline-block; margin-top:.6rem; padding:.55rem 1rem; border:0; border-radius:7px; background:var(--ok); color:#04121A; font-weight:700; cursor:pointer; text-decoration:none; font-size:.9rem; }
        .btn-outline { background:transparent; border:1px solid var(--border); color:var(--text); }
        .btn-outline:hover { border-color:var(--cyan); color:var(--cyan); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1rem; margin-top:1rem; }
        .card { border:1px solid var(--border); background:var(--surface); border-radius:10px; padding:1.1rem; }
        .filters-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.85rem; align-items:end; margin-top:1rem; }
        .actions-row { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; margin-top:.85rem; }
        .notice { background:rgba(0,240,255,.06); border:1px solid rgba(0,240,255,.25); border-radius:8px; padding:.8rem 1rem; margin:1rem 0; font-size:.88rem; color:#CBD5E1; }
        .table-wrap { overflow-x:auto; margin-top:1rem; border:1px solid var(--border); border-radius:10px; background:var(--surface); }
        table { width:100%; border-collapse:collapse; font-size:.86rem; }
        th, td { text-align:left; padding:.68rem .85rem; border-bottom:1px solid var(--border); white-space:nowrap; }
        th { color:var(--muted); font-weight:600; background:#091024; }
        tr:last-child td { border-bottom:none; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.76rem; font-weight:700; }
        .badge-ok { background:rgba(16,185,129,.15); color:#34D399; border:1px solid rgba(16,185,129,.35); }
        .badge-warn { background:rgba(245,158,11,.15); color:#FBBF24; border:1px solid rgba(245,158,11,.35); }
        .badge-info { background:rgba(0,240,255,.12); color:#67E8F9; border:1px solid rgba(0,240,255,.35); }
        code { color:var(--cyan); font-family:'JetBrains Mono',monospace; font-size:.84rem; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '', 'show_lang' => true]); ?>
<main>
    <?php echo grid402_pass_reengagement_banner([
        'idle_passes' => $userIdleStats['idle_passes'],
        'idle_usdc'   => $userIdleStats['idle_usdc'],
        'expires_in'  => $userIdleStats['expires_min_days'],
        'lang'        => 'en',
    ]); ?>

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

    <h2 style="margin-top:2rem;" data-i18n="paydash.status.title">Pass Status and Usage Statement</h2>
    <form method="get" action="/payment-dashboard">
        <label for="token" data-i18n="paydash.status.label">Token pass_g402... (optional)</label>
        <input id="token" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" placeholder="pass_g402_...">
        <button type="submit" data-i18n="paydash.status.btn">Check</button>
    </form>

    <?php if ($pass && !empty($pass['ok'])): ?>
        <?php
            $remaining = (int) ($pass['credits_remaining'] ?? 0);
            $growthCfg = function_exists('grid402_growth_read') ? (grid402_growth_read()['config'] ?? []) : [];
            $lowBalanceThreshold = (int) ($growthCfg['low_balance_threshold'] ?? 10);
            $isLow = $remaining <= $lowBalanceThreshold;
        ?>
        <?php if ($isLow): ?>
            <div class="card" style="margin-top:1rem;border-color:#F59E0B;background:#241A06;">
                <h3 style="color:#FCD34D;margin-top:0;" data-i18n="paydash.low.title">Low balance</h3>
                <p style="color:#FDE68A;"><strong><?php echo $remaining; ?> credit(s)</strong> remaining on your Session Pass (warning threshold: <?php echo $lowBalanceThreshold; ?>).</p>
                <p><a class="btn-link" href="/pass" style="background:#F59E0B;color:#1F2937;" data-i18n="paydash.low.btn">Top up Session Pass →</a></p>
            </div>
        <?php endif; ?>

        <div class="grid" style="margin-top:1rem;">
            <div class="card">
                <h3 style="margin-top:0;" data-i18n="paydash.active.title">Active Pass</h3>
                <p><strong data-i18n="paydash.active.remaining">Remaining credits:</strong> <?php echo (int) ($pass['credits_remaining'] ?? 0); ?> / <?php echo (int) ($pass['credits_total'] ?? 0); ?></p>
                <p><strong data-i18n="paydash.active.expires">Expires:</strong> <?php echo htmlspecialchars((string) ($pass['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Tier:</strong> <?php echo htmlspecialchars((string) ($pass['tier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string) ($pass['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</p>
            </div>
            <?php if ($statement && !empty($statement['ok'])): ?>
            <div class="card">
                <h3 style="margin-top:0;" data-i18n="paydash.stmt.summary_title">Filtered Statement Summary</h3>
                <p><strong data-i18n="paydash.stmt.sum_entries">Events shown:</strong> <?php echo (int) ($statement['summary']['returned_entries'] ?? 0); ?> / <?php echo (int) ($statement['retention']['stored_entries'] ?? 0); ?></p>
                <p><strong data-i18n="paydash.stmt.sum_credits">Credits debited:</strong> <?php echo (int) ($statement['summary']['total_credits_debited'] ?? 0); ?> (<?php echo htmlspecialchars((string) ($statement['summary']['total_usdc_equivalent'] ?? '0.0000'), ENT_QUOTES, 'UTF-8'); ?> USDC)</p>
                <p><strong data-i18n="paydash.stmt.sum_blocked">Blocked (403/429):</strong> <?php echo (int) ($statement['summary']['blocked_count'] ?? 0); ?> | <strong data-i18n="paydash.stmt.sum_replays">Replays:</strong> <?php echo (int) ($statement['summary']['replay_count'] ?? 0); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($statement && !empty($statement['ok'])): ?>
            <section style="margin-top:1.75rem;" aria-labelledby="stmt-heading">
                <h2 id="stmt-heading" data-i18n="paydash.stmt.title">Session Pass Consumption Statement</h2>
                <div class="notice" data-i18n-html="paydash.stmt.retention_notice">
                    Retains up to <strong>200 most recent Zero-PII events</strong> per pass (query arguments and IP addresses are never stored). Need extended archival? Contact <a href="mailto:contato@grid402.online">contato@grid402.online</a>.
                </div>

                <form method="get" action="/payment-dashboard" class="filters-row">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                        <label for="stmt-endpoint" data-i18n="paydash.stmt.filter_endpoint">Endpoint filter</label>
                        <select id="stmt-endpoint" name="endpoint">
                            <option value="" data-i18n="paydash.stmt.filter_all">All</option>
                            <?php foreach (array_keys($utilityServices) as $svcKey): ?>
                                <option value="<?php echo htmlspecialchars($svcKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterEndpoint === $svcKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($svcKey, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="stmt-outcome" data-i18n="paydash.stmt.filter_outcome">Outcome filter</label>
                        <select id="stmt-outcome" name="outcome">
                            <option value="" data-i18n="paydash.stmt.filter_all">All</option>
                            <option value="consumed" <?php echo $filterOutcome === 'consumed' ? 'selected' : ''; ?>>consumed (200)</option>
                            <option value="refunded" <?php echo $filterOutcome === 'refunded' ? 'selected' : ''; ?>>refunded (auto-refund)</option>
                            <option value="endpoint_restricted" <?php echo $filterOutcome === 'endpoint_restricted' ? 'selected' : ''; ?>>endpoint_restricted (403)</option>
                            <option value="daily_limit_exceeded" <?php echo $filterOutcome === 'daily_limit_exceeded' ? 'selected' : ''; ?>>daily_limit_exceeded (429)</option>
                            <option value="idempotent_replay" <?php echo $filterOutcome === 'idempotent_replay' ? 'selected' : ''; ?>>idempotent_replay (200)</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="width:100%;" data-i18n="paydash.stmt.filter_btn">Apply Filters</button>
                    </div>
                </form>

                <div class="actions-row">
                    <a class="btn-link" href="<?php echo htmlspecialchars($csvUrl, ENT_QUOTES, 'UTF-8'); ?>" data-i18n="paydash.stmt.export_csv">Export CSV</a>
                    <a class="btn-link btn-outline" href="<?php echo htmlspecialchars($jsonUrl, ENT_QUOTES, 'UTF-8'); ?>" data-i18n="paydash.stmt.export_json">Export JSON</a>
                </div>

                <?php $entries = $statement['entries'] ?? []; ?>
                <?php if (empty($entries)): ?>
                    <div class="card" style="margin-top:1rem;">
                        <p style="margin:0;color:var(--muted);" data-i18n="paydash.stmt.empty">No statement events match the selected filters yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col" data-i18n="paydash.stmt.col_ts">Timestamp (UTC)</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_endpoint">Endpoint</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_outcome">Outcome</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_status">HTTP</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_credits">Credits</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_after">Balance After</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_usdc">USDC Eq.</th>
                                    <th scope="col" data-i18n="paydash.stmt.col_reqid">Request ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $row): ?>
                                    <?php
                                        $outcomeVal = (string) ($row['outcome'] ?? 'consumed');
                                        $badgeClass = $outcomeVal === 'consumed'
                                            ? 'badge-ok'
                                            : (($outcomeVal === 'idempotent_replay' || $outcomeVal === 'refunded') ? 'badge-info' : 'badge-warn');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($row['ts'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><code>/api/v1/<?php echo htmlspecialchars((string) ($row['endpoint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($outcomeVal, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo (int) ($row['http_status'] ?? 200); ?></td>
                                        <td><?php echo (int) ($row['credits_debited'] ?? 0); ?></td>
                                        <td><?php echo (int) ($row['credits_after'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['usdc_equivalent'] ?? '0.0000'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><code><?php echo htmlspecialchars((string) ($row['request_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php elseif ($token !== ''): ?>
        <p style="color:#F59E0B;margin-top:1rem;" data-i18n="paydash.invalid">Invalid token or pass not found.</p>
    <?php endif; ?>

    <?php
        $paidApiCalls = (int) ($metrics['total_paid_requests'] ?? 0);
        $sandboxApiCalls = (int) ($metrics['total_sandbox_requests'] ?? 0);
        $totalPageViews = (int) ($metrics['total_page_views'] ?? 0);
        $serviceCountsSum = is_array($metrics['service_counts'] ?? null)
            ? array_sum(array_map('intval', $metrics['service_counts']))
            : 0;
        $apiCallsRecorded = $serviceCountsSum > 0 ? $serviceCountsSum : (int) ($metrics['total_requests'] ?? 0);
        $paidPassesCount = isset($passesStats['paid_passes'])
            ? (int) $passesStats['paid_passes']
            : (int) ($passesStats['total_issued'] ?? 0);
        $activePaidPasses = isset($passesStats['active_paid_passes'])
            ? (int) $passesStats['active_paid_passes']
            : (int) ($passesStats['active_passes'] ?? 0);
    ?>
    <h2 style="margin-top:2.25rem;" data-i18n="paydash.summary.title">Hub summary</h2>
    <p><strong data-i18n="paydash.summary.calls">API calls recorded (excl. HTML page views):</strong> <?php echo $apiCallsRecorded; ?> (<strong><?php echo $paidApiCalls; ?></strong> paid • <?php echo $sandboxApiCalls; ?> sandbox)</p>
    <p><strong data-i18n="paydash.summary.page_views">HTML page views:</strong> <?php echo $totalPageViews; ?></p>
    <p><strong>Revenue:</strong> <?php echo number_format((float) ($metrics['total_revenue_usdc'] ?? 0), 4); ?> USDC</p>
    <p><strong data-i18n="paydash.summary.passes">Paid Session Passes issued:</strong> <?php echo $paidPassesCount; ?> (<?php echo $activePaidPasses; ?> active)</p>
</main>
<script src="/assets/js/pass_storage.js?v=3.5.0"></script>
<script>
(function () {
    var params = new URLSearchParams(window.location.search);
    var urlToken = (params.get('token') || '').trim();
    if (window.Grid402PassStorage) {
        if (urlToken && window.Grid402PassStorage.isValidToken(urlToken)) {
            window.Grid402PassStorage.save(urlToken);
        } else if (!urlToken && !<?php echo $token !== '' ? 'true' : 'false'; ?>) {
            var stored = window.Grid402PassStorage.load();
            if (stored) {
                params.set('token', stored);
                window.location.replace(window.location.pathname + '?' + params.toString());
            }
        }
    }
})();
</script>
<script src="/assets/js/i18n.js?v=3.5.0"></script>
</body>
</html>
