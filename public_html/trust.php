<?php
/**
 * Grid402 - Trust Center
 * SLA, disponibilidade histórica, metodologia e política de incidentes.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');

$catalogServices = grid402_get_utility_services();
$summaryRaw = @file_get_contents(GRID402_FILE_FREE);
$summary = $summaryRaw ? (@json_decode($summaryRaw, true) ?: []) : [];
$services = is_array($summary['services'] ?? null) ? $summary['services'] : [];
$servicesTotal = count($catalogServices);
$servicesUp = 0;
foreach ($catalogServices as $svcId => $def) {
    $st = is_array($services[$svcId] ?? null) ? ($services[$svcId]['status'] ?? 'UP') : 'UP';
    if ($st === 'UP') {
        $servicesUp++;
    }
}

$uptime30d = grid402_get_uptime_history_30d();
$uptimePct30d = (float)($uptime30d['overall_uptime_pct'] ?? 100.0);
$uptimeDays = is_array($uptime30d['days'] ?? null) ? $uptime30d['days'] : [];

$metrics = grid402_get_metrics();
$trustAnalytics = grid402_compute_admin_analytics($metrics);
$latencyP95Ms = (float) ($trustAnalytics['latency_p95_ms'] ?? 0.0);
$latencySamplesCount = (int) ($trustAnalytics['latency_samples_count'] ?? 0);
$statusCounts = is_array($metrics['status_counts'] ?? null) ? $metrics['status_counts'] : [];
$total = (int)($metrics['total_requests'] ?? 0);
$ok = (int)($statusCounts['200'] ?? 0);
$error5xx = (int)($statusCounts['500'] ?? 0) + (int)($statusCounts['502'] ?? 0) + (int)($statusCounts['503'] ?? 0);
$availabilityHttp = $total > 0 ? round((($total - $error5xx) / $total) * 100, 2) : 100.0;
$error4xx = (int)($statusCounts['402'] ?? 0) + (int)($statusCounts['403'] ?? 0);
$checkedAt = $summary['timestamp'] ?? gmdate('Y-m-d\TH:i:s\Z');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=2">
    <meta name="description" content="Grid402 Trust Center: Institutional identity, technical SLA, 30-day availability history, public source provenance, and M2M governance.">
    <title>Grid402 // Trust Center, Governance &amp; Provenance</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --ok:#10B981; --primary:#00F0FF; --warn:#F59E0B; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.6; }
        .container { max-width:1060px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        h1 { font-size:2rem; letter-spacing:-.04em; margin-bottom:.25rem; }
        h2 { color:var(--primary); font-size:1.25rem; margin-top:2.2rem; }
        p { color:var(--muted); }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin:1.5rem 0; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:1rem; }
        .value { font-size:1.6rem; font-weight:700; color:var(--text); }
        .ok { color:var(--ok); }
        code { color:var(--primary); font-family:'JetBrains Mono',monospace; font-size:.88rem; }
        a { color:var(--primary); }
        li { color:#CBD5E1; margin:.25rem 0; }
        .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:10px; margin:1rem 0; }
        table { width:100%; border-collapse:collapse; min-width:720px; }
        th, td { text-align:left; padding:.65rem .75rem; border-bottom:1px solid var(--border); vertical-align:top; font-size:.9rem; }
        th { color:#CBD5E1; background:#111C38; font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; }
        tr:last-child td { border-bottom:0; }
        .uptime-strip { display:grid; grid-template-columns:repeat(30,1fr); gap:4px; margin:.75rem 0; }
        .uptime-bar { height:28px; border-radius:4px; background:var(--ok); opacity:.9; }
        .uptime-bar.degraded { background:var(--warn); }
        .notice-box { background:rgba(0,240,255,.06); border:1px solid rgba(0,240,255,.28); border-radius:10px; padding:1rem 1.2rem; margin:1.2rem 0; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/trust', 'show_lang' => true]); ?>
<div class="container">
    <h1 data-i18n="trust.title">Trust Center, Governance &amp; Provenance</h1>
    <p data-i18n-html="trust.subtitle">Institutional transparency, verifiable historical availability (30 days), matrix of official public sources, scope boundaries, and security policy of the <strong>Grid402</strong> M2M oracle (<code>grid402.online</code>).</p>

    <div class="cards">
        <div class="card"><div class="value ok"><?php echo number_format($uptimePct30d, 2); ?>%</div><div data-i18n="trust.kpi1">Historical Uptime (30 days) · Best-Effort Target: 99.5%</div></div>
        <div class="card"><div class="value ok"><?php echo $servicesUp; ?>/<?php echo $servicesTotal; ?></div><div data-i18n="trust.kpi2">Operational Services (UP) in Current Snapshot</div></div>
        <div class="card"><div class="value ok"><?php echo number_format($availabilityHttp, 2); ?>%</div><div><span data-i18n="trust.kpi3">Success Rate Without 5xx Errors</span> (<?php echo $ok; ?> HTTP 200) · <span data-i18n="trust.kpi3_p95">p95 Latency:</span> <strong><?php echo number_format($latencyP95Ms, 1); ?> ms</strong></div></div>
        <div class="card"><div class="value"><?php echo $error4xx; ?></div><div data-i18n="trust.kpi4">HTTP 402 Challenges Issued + Replays Blocked</div></div>
    </div>

    <div class="notice-box" data-i18n-html="trust.notice">
        <strong>Brand Disambiguation Notice (<code>grid402.online</code>):</strong><br>
        <strong>Grid402</strong> (operated exclusively at the canonical domain <code>https://grid402.online</code>) is an independent <strong>Public Data Oracle and M2M Network Utility infrastructure settled via HTTP 402 on the Base blockchain (Chain ID 8453)</strong>. It has no corporate, technical, or operational affiliation with homonymous electrical power distribution projects (<em>smart-grid / physical power grid</em>) or third-party academic initiatives.
    </div>

    <h2 data-i18n="trust.s1.title">1. Institutional Identity and Operator Governance</h2>
    <div class="card">
        <ul data-i18n-html="trust.s1.list">
            <li><strong>Operator and Maintainer:</strong> Grid402 Network Intelligence (Technical Lead: Thiago Campos — São Paulo, SP, Brazil).</li>
            <li><strong>Official Oracle and Recipient Wallet (Base Mainnet - Chain ID 8453):</strong> <code>0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90</code> (<a href="https://basescan.org/address/0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90" target="_blank" rel="noopener noreferrer">Audit on BaseScan</a>).</li>
            <li><strong>Availability Policy (SLA):</strong> Operated on a <em>Best-Effort</em> basis with a technical availability target of <strong>99.5%</strong> across oracle routes and resilient local caching to protect against upstream government source fluctuations.</li>
            <li><strong>Official Repository and Open-Source SDKs/MCP:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a> · Official packages: <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npm: grid402</a>, <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npm: grid402-mcp</a>, <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">PyPI: grid402</a>.</li>
            <li><strong>Support Contact, DPO (LGPD/GDPR), and Vulnerability Disclosure:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a> · Security manifest: <a href="/.well-known/security.txt">/.well-known/security.txt</a>.</li>
        </ul>
    </div>

    <h2 data-i18n="trust.s2.title">2. Availability History (Last 30 Days)</h2>
    <p><span data-i18n="trust.s2.desc">Continuous monitoring of all 23 microservices.</span> (<code><?php echo htmlspecialchars((string)$checkedAt); ?></code> · <a href="/status">/status</a>)</p>
    <div class="uptime-strip" role="img" aria-label="30-day availability history">
        <?php foreach ($uptimeDays as $day):
            $dayStatus = (string)($day['status'] ?? 'UP');
            $dayDate = (string)($day['date'] ?? '');
            $dayPct = number_format((float)($day['uptime_pct'] ?? 100.0), 1);
            $dayUp = (int)($day['services_up'] ?? $servicesTotal);
            $dayTot = (int)($day['services_total'] ?? $servicesTotal);
        ?>
            <div class="uptime-bar <?php echo $dayStatus !== 'UP' ? 'degraded' : ''; ?>" title="<?php echo htmlspecialchars("{$dayDate}: {$dayPct}% ({$dayUp}/{$dayTot} UP)"); ?>"></div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.82rem;color:var(--muted);">
        <span><span data-i18n="trust.s2.ago">30 days ago</span> (<?php echo htmlspecialchars((string)($uptimeDays[0]['date'] ?? '')); ?>)</span>
        <span><span data-i18n="trust.s2.avg">30d Average:</span> <strong class="ok"><?php echo number_format($uptimePct30d, 2); ?>%</strong></span>
        <span><span data-i18n="trust.s2.today">Today</span> (<?php echo htmlspecialchars((string)($uptimeDays[count($uptimeDays)-1]['date'] ?? gmdate('Y-m-d'))); ?>)</span>
    </div>

    <h2 data-i18n="trust.s3.title">3. Transparent Scope: Official Public Data vs. Private Credit Bureaus</h2>
    <p data-i18n="trust.s3.desc">Grid402 maintains absolute transparency regarding what it delivers and what is outside its scope:</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th data-i18n="trust.s3.th1">Dimension</th>
                    <th data-i18n="trust.s3.th2">What Grid402 Delivers (Public M2M Oracle)</th>
                    <th data-i18n="trust.s3.th3">What Requires a Private Credit Bureau (Out of Scope)</th>
                </tr>
            </thead>
            <tbody data-i18n-html="trust.s3.tbody">
                <tr>
                    <td><strong>Data Sources</strong></td>
                    <td>Open government registries (Receita Federal CNPJ/Simples, CGU CEIS/CNEP, BCB PTAX/SGS, IBGE, GLEIF LEI CC0, US Treasury OFAC SDN, ECB, DNS/TLS/BGP).</td>
                    <td>Private commercial default databases, private notary protests, bounced checks, and closed banking history (e.g., Serasa, SPC, Boa Vista, Quod).</td>
                </tr>
                <tr>
                    <td><strong>Guarantee and Proof</strong></td>
                    <td>Deterministic JSON normalization + <code>_meta</code> provenance block + offline-auditable <strong>ECDSA secp256k1</strong> cryptographic signature (<code>_oracle_proof</code>).</td>
                    <td>Closed proprietary credit score without public cryptographic oracle signature.</td>
                </tr>
                <tr>
                    <td><strong>Privacy (LGPD/GDPR)</strong></td>
                    <td>Strictly <strong>Zero-PII</strong> architecture: processes exclusively Legal Entities and macroeconomic/network facts, filtering individual tax IDs at the source.</td>
                    <td>Queries on natural person tax IDs (CPF), presumed individual income, and personal consumer history.</td>
                </tr>
                <tr>
                    <td><strong>Commercial Model</strong></td>
                    <td>Zero sign-up, zero monthly fee. Per-request settlement via HTTP 402 (<code>0.0050 to 0.0500 USDC</code> across 6 chains) or prepaid Session Pass (<code>1 credit = 0.0050 USDC</code>).</td>
                    <td>Annual contracts with minimum monthly commitment, manual onboarding approval, and postpaid billing.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 data-i18n="trust.s4.title">4. Provenance and Freshness (TTL) Matrix of All 23 Services</h2>
    <p data-i18n-html="trust.s4.desc">Every paid or sandbox HTTP 200 response includes the <code>_meta</code> object signed alongside <code>_oracle_proof</code>, attesting the exact upstream source and cache policy:</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th data-i18n="trust.s4.th1">Endpoint</th>
                    <th data-i18n="trust.s4.th2">Service</th>
                    <th data-i18n="trust.s4.th3">Upstream Official Source</th>
                    <th data-i18n="trust.s4.th4">Cache TTL</th>
                    <th data-i18n="trust.s4.th5">Fee (USDC / Pass)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catalogServices as $svcId => $svc):
                $price = (string)($svc['price_usdc'] ?? GRID402_PRICE_DISPLAY);
                $priceLabel = ($price === 'Gratuito') ? 'Free' : $price;
                $credits = ($price === 'Gratuito') ? '0 credits' : (($svcId === 'pass') ? '400 to 6,000 credits' : ((int)ceil(((float)$price * 1000000) / 5000) . ' credit(s)'));
            ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string)$svc['endpoint']); ?></code></td>
                    <td><strong data-i18n="svc.<?php echo htmlspecialchars((string)$svcId); ?>.name"><?php echo htmlspecialchars((string)$svc['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)($svc['upstream_source'] ?? 'Official Public Source')); ?></td>
                    <td><code><?php echo (int)($svc['cache_ttl_sec'] ?? 0); ?>s</code></td>
                    <td><code<?php echo $price === 'Gratuito' ? ' data-i18n="agents.free"' : ''; ?>><?php echo htmlspecialchars($priceLabel); ?></code> <span style="color:var(--muted);font-size:.82rem;">(<?php echo htmlspecialchars($credits); ?>)</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 data-i18n="trust.s5.title">5. Version and Pricing History (Public Changelog)</h2>
    <div class="card">
        <ul data-i18n-html="trust.s5.list">
            <li><strong>v3.3.0 (2026-11):</strong> Launch of premium <code>/api/v1/dossier</code> (<code>0.0500 USDC</code> / 10 credits, AI Verdict <code>APPROVED</code>/<code>FLAGGED</code>/<code>REJECTED</code>), 25th MCP tool (<code>grid402_get_dossier_360</code>), Multi-Chain USDC payments across 6 networks (Base, Arbitrum, Polygon, Optimism, Ethereum, Solana SPL USDC), and Fleet Pass bonus math clarification (<code>4,000 base + 2,000 bonus = 6,000 credits</code>).</li>
            <li><strong>v3.2.0 (2026-11):</strong> Structured JSONL Zero-PII observability (<code>requests.jsonl</code> + <code>X-Request-Id</code>), live <code>p95</code> latency tracking, MCP handshake <code>instructions</code>, explicit Dual-License (MIT SDKs + Proprietary Gateway), and parallel concurrency load test suite.</li>
            <li><strong>v3.1.0 (2026-11):</strong> Unified official Session Pass math (<code>1 credit = 0.0050 USDC</code>: Starter 2 USDC = 400 credits, Pro 5 USDC = 1,000 credits, Fleet 20 USDC = 6,000 credits), <code>Idempotency-Key</code> support (300s TTL), per-pass Spend Controls (<code>daily_credit_limit</code>, <code>allowed_endpoints</code>, and <code>GET /api/v1/pass/balance</code>), signed <code>_meta</code> block injection, and 30-day uptime history.</li>
            <li><strong>v3.0.0 (2026-11):</strong> Expansion to 22 services with composite endpoints (<code>/api/v1/domain-audit</code>, <code>/api/v1/kyb-audit</code>, <code>/api/v1/market-snapshot</code>), native x402 v2 facilitator integration (EIP-3009), and hybrid Pix/Card + USDC checkout.</li>
            <li><strong>v2.4.0 (2026-10):</strong> Launch of global GLEIF LEI (CC0), OFAC SDN + Web3 AML on Base, ECDSA secp256k1 Notarization, and ECB Global Exchange Calendar endpoints.</li>
        </ul>
    </div>

    <h2 data-i18n="trust.s6.title">6. Incident Policy, Key Security and Liability</h2>
    <ul data-i18n-html="trust.s6.list">
        <li><strong>S0 (Critical Outage):</strong> immediate response and automatic fallback to signed cached snapshot with explicit timestamp in <code>_meta.fetched_at</code>.</li>
        <li><strong>S1 (External Government Source Degradation):</strong> <em>fail-closed</em> behavior with structured error, zero synthetic data generation, and zero credit deduction on 5xx failures.</li>
        <li><strong>Non-Custodial M2M Key Management &amp; Spend Controls:</strong> Grid402 never stores or requests client private keys or seed phrases. Autonomous agents using <code>GRID402_PRIVATE_KEY</code> should use dedicated low-balance operational wallets or Session Passes (<code>pass_g402_...</code>) constrained by <code>daily_credit_limit</code> and <code>allowed_endpoints</code>; request logs (<code>requests.jsonl</code> + <code>X-Request-Id</code>) never record full payment tokens or raw IPs.</li>
        <li><strong>Credit Decision Disclaimer:</strong> Grid402 provides verifiable public technical data and does not grant credit, provide legal advice, or approve onboarding.</li>
    </ul>
</div>
<script src="/assets/js/i18n.js?v=3.3.0"></script>
</body>
</html>
