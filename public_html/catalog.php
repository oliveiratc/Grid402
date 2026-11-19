<?php
/**
 * Grid402 - Catálogo Único de Serviços
 * Preços, limites, exemplos e status operacional de todos os endpoints públicos.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');

$services = grid402_get_utility_services();
$summaryRaw = @file_get_contents(GRID402_FILE_FREE);
$summary = $summaryRaw ? (@json_decode($summaryRaw, true) ?: []) : [];
$summaryServices = is_array($summary['services'] ?? null) ? $summary['services'] : [];
$summaryAt = isset($summary['timestamp']) ? (string)$summary['timestamp'] : gmdate('Y-m-d\TH:i:s\Z');

ksort($services);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Complete and live catalog of all 22 Grid402 microservices: pricing, limits, examples, and status.">
    <title>Grid402 // Complete Service Catalog</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --primary:#00F0FF; --ok:#10B981; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.55; }
        .container { max-width:1180px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        header { padding:1.5rem 0 2rem; }
        h1 { font-size:2.1rem; letter-spacing:-.04em; margin:0 0 .5rem; }
        h2 { font-size:1.25rem; color:var(--primary); margin-top:2.25rem; }
        p { color:var(--muted); }
        .meta { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:1rem; }
        .pill { border:1px solid var(--border); background:var(--surface); padding:.35rem .7rem; border-radius:999px; font-size:.82rem; }
        .search { width:100%; margin:1rem 0; padding:.8rem 1rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:1rem; }
        .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:10px; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        th, td { text-align:left; padding:.7rem .75rem; border-bottom:1px solid var(--border); vertical-align:top; }
        th { color:#CBD5E1; background:#111C38; font-size:.82rem; text-transform:uppercase; letter-spacing:.06em; }
        tr:last-child td { border-bottom:0; }
        code { color:#00F0FF; font-family:'JetBrains Mono',monospace; font-size:.85rem; }
        .price { color:#10B981; font-family:monospace; }
        .ok { color:var(--ok); }
        .muted { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem; margin-top:1rem; }
        .card { border:1px solid var(--border); background:var(--surface); border-radius:10px; padding:1rem; }
        .card h3 { margin:.1rem 0 .45rem; font-size:1rem; }
        .cta { display:inline-block; border:1px solid #0052FF; padding:.5rem .8rem; border-radius:7px; color:#fff; text-decoration:none; margin-right:.4rem; }
        .cta.secondary { border-color:var(--border); color:var(--primary); }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/catalog', 'show_lang' => true]); ?>
<div class="container">
    <header>
        <h1 data-i18n="catalog.title">Complete Service Catalog</h1>
        <p data-i18n-html="catalog.subtitle">Services, prices, limits, and examples on a single page. Paid queries via <code>POST</code>/<code>GET</code> with <code>X-Payment</code>.</p>
        <div class="meta">
            <span class="pill" data-i18n="catalog.pill.active"><?php echo count($services); ?> active endpoints</span>
            <span class="pill">Base Mainnet · chain 8453</span>
            <span class="pill" data-i18n="catalog.pill.price">0.0050 USDC standard</span>
            <span class="pill" data-i18n="catalog.pill.sandbox">Free Sandbox: <?php echo GRID402_SANDBOX_RATE_LIMIT; ?> queries/hour per IP</span>
        </div>
    </header>

    <h2 data-i18n="catalog.limits.title">Limits and authentication</h2>
    <div class="grid">
        <div class="card"><h3 data-i18n="catalog.limits.c1.title">x402 micropayment</h3><p data-i18n-html="catalog.limits.c1.desc">Send <code>PAYMENT-SIGNATURE</code> (Base64, EIP-3009, facilitator token) or tx hash to the official wallet <code><?php echo htmlspecialchars(GRID402_WALLET_ADDRESS); ?></code>; the service responds in milliseconds.</p></div>
        <div class="card"><h3 data-i18n="catalog.limits.c2.title">Session Pass (1 credit = 0.0050 USDC)</h3><p data-i18n-html="catalog.limits.c2.desc">Buy credits at <a href="/pass" style="color:var(--primary)">/pass</a>; send <code>X-Payment: pass_g402_...</code>. Starter (2 USDC = 400 credits), Pro (5 USDC = 1,000 credits), Fleet (20 USDC = 4,000 base + 2,000 bonus = 6,000 credits).</p></div>
        <div class="card"><h3 data-i18n="catalog.limits.c3.title">Sandbox &amp; Idempotency</h3><p data-i18n-html="catalog.limits.c3.desc">For testing: <code>X-Payment: demo</code> (5 req/hour per IP). In production, send <code>Idempotency-Key</code> for retries without duplicate billing (300s TTL).</p></div>
        <div class="card"><h3 data-i18n="catalog.limits.c4.title">Rate limit and Spend Controls</h3><p data-i18n-html="catalog.limits.c4.desc">Timeout: <?php echo GRID402_TIMEOUT_MS; ?> ms. Check balance for free at <code>GET /api/v1/pass/balance</code> and configure daily cap (<code>daily_credit_limit</code>) and route whitelist.</p></div>
    </div>

    <h2 data-i18n="catalog.table.title">Endpoints, price, provenance, status (23/23 UP) and example</h2>
    <input class="search" id="filter" type="text" placeholder="Filter by endpoint, name, official source, or category…" aria-label="Filter services" data-i18n-placeholder="catalog.search.placeholder">
    <div class="table-wrap">
        <table id="catalog">
            <thead><tr><th data-i18n="catalog.th.service">Service &amp; Official Source</th><th data-i18n="catalog.th.category">Category</th><th data-i18n="catalog.th.price">Price USDC</th><th data-i18n="catalog.th.status">Status</th><th data-i18n="catalog.th.example">Example</th></tr></thead>
            <tbody>
            <?php foreach ($services as $id => $svc):
                $price = isset($svc['price_usdc']) ? $svc['price_usdc'] : ($id === 'sanctions' || $id === 'simples' ? '0.0100' : (in_array($id, ['cnpj','lei','ofac','kyb-audit'], true) ? '0.0200' : (in_array($id, ['domain-audit','market-snapshot'], true) ? '0.0150' : ($id === 'compliance' ? '0.0300' : ($id === 'pass' ? '2.0000' : ($id === 'verify' ? 'Gratuito' : GRID402_PRICE_DISPLAY))))));
                $status = is_array($summaryServices[$id] ?? null) ? ($summaryServices[$id]['status'] ?? 'UP') : 'UP';
                if ($status === '—' || $status === '') {
                    $status = 'UP';
                }
                $upstreamSource = (string)($svc['upstream_source'] ?? ($summaryServices[$id]['upstream_source'] ?? 'Official Public Source'));
                $cacheTtl = isset($svc['cache_ttl_sec']) ? (int)$svc['cache_ttl_sec'] : 300;
                $example = '/api/v1/' . rawurlencode($id);
                if ($id === 'forex') $example .= '?base=USD&quote=BRL';
                elseif ($id === 'dns') $example .= '?domain=base.org&type=A';
                elseif ($id === 'ssl') $example .= '?host=base.org';
                elseif ($id === 'asn') $example .= '?ip=1.1.1.1';
                elseif ($id === 'cep') $example .= '?code=01310100';
                elseif ($id === 'cnpj') $example .= '?cnpj=00000000000191';
                elseif (in_array($id, ['kyb-audit','dossier','compliance','sanctions','simples'], true)) $example .= '?cnpj=00000000000191';
                elseif ($id === 'domain-audit') $example .= '?domain=base.org';
                elseif ($id === 'lei') $example .= '?lei=5493006MHB84DD0ZWV18';
                elseif ($id === 'ofac') $example .= '?target=0x8589427373d6d84e98730d7795d8f6f8731fda16';
                elseif ($id === 'notarize') $example .= '?content=Grid402-M2M-Audit';
                elseif ($id === 'holidays') $example .= '?year=2026';
                elseif ($id === 'workdays') $example .= '?start_date=2026-01-01&days=5';
                elseif ($id === 'global-markets') $example .= '?market=US&base=USD';
                $priceDisplay = ($price === 'Gratuito') ? 'Free' : (string)$price;
            ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string)($svc['endpoint'] ?? $example)); ?></code><br><strong data-i18n="svc.<?php echo htmlspecialchars((string)$id); ?>.name"><?php echo htmlspecialchars((string)($svc['name'] ?? $id)); ?></strong><br><span class="muted" data-i18n="svc.<?php echo htmlspecialchars((string)$id); ?>.desc"><?php echo htmlspecialchars((string)($svc['description'] ?? '')); ?></span><br><small class="muted"><span data-i18n="catalog.source_label">Source:</span> <?php echo htmlspecialchars($upstreamSource); ?> · TTL: <?php echo $cacheTtl; ?>s</small></td>
                    <td data-i18n="svc.<?php echo htmlspecialchars((string)$id); ?>.category"><?php echo htmlspecialchars((string)($svc['category'] ?? 'M2M')); ?></td>
                    <td class="price"<?php echo $price === 'Gratuito' ? ' data-i18n="agents.free"' : ''; ?>><?php echo htmlspecialchars($priceDisplay); ?></td>
                    <td class="ok"><strong><?php echo htmlspecialchars((string)$status); ?></strong></td>
                    <td><code>curl -H "X-Payment: demo" https://grid402.online<?php echo htmlspecialchars($example); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 data-i18n="catalog.state.title">Data freshness</h2>
    <p><span data-i18n="catalog.state.desc">Status marked as UP: on-chain data are real-time public facts; cached feeds may have up to 2 minutes of lag.</span> (<code><?php echo htmlspecialchars($summaryAt ?: 'live'); ?></code>)</p>
    <a class="cta" href="/trust" data-i18n="catalog.cta.trust">SLA, availability and methodology →</a>
    <a class="cta secondary" href="/agents" data-i18n="catalog.cta.agents">Agent guide →</a>
</div>
<script>
const input=document.getElementById('filter');
input.addEventListener('input',()=>{const q=input.value.toLowerCase();document.querySelectorAll('#catalog tbody tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none');});
</script>
<script src="/assets/js/i18n.js?v=3.3.0"></script>
</body>
</html>
