<?php

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
$services = grid402_get_utility_services();
ksort($services);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID402 Agent Hub</title>
    <meta name="description" content="Integration hub for autonomous agents — HTTP x402, MCP, Coinbase AgentKit, Python SDK, TypeScript SDK.">
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --blue:#0052FF; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; }
        main { max-width:1100px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        a { color:var(--cyan); }
        h1 { letter-spacing:-.04em; }
        h2 { color:var(--cyan); margin-top:2rem; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem; }
        .card { border:1px solid var(--border); background:var(--surface); border-radius:10px; padding:1.1rem; }
        pre { background:#111C38; border:1px solid var(--border); border-radius:8px; padding:.9rem; overflow-x:auto; font-size:.8rem; }
        code { color:var(--cyan); }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '', 'show_lang' => true]); ?>
<main>
    <h1 data-i18n="agenthub.title">GRID402 AGENT HUB</h1>
    <p data-i18n="agenthub.subtitle">Integration hub for autonomous agents: documentation, examples, pricing, and compatibility.</p>

    <h2 data-i18n="agenthub.catalog.title">Service catalog</h2>
    <div class="grid">
        <?php foreach ($services as $id => $svc): ?>
            <div class="card">
                <h3 data-i18n="svc.<?php echo htmlspecialchars((string)$id); ?>.name"><?php echo htmlspecialchars((string)($svc['name'] ?? $id)); ?></h3>
                <p data-i18n="svc.<?php echo htmlspecialchars((string)$id); ?>.desc"><?php echo htmlspecialchars((string)($svc['description'] ?? '')); ?></p>
                <code><?php echo htmlspecialchars((string)($svc['endpoint'] ?? '/api/v1/' . $id)); ?></code>
                <p><a href="/catalog" data-i18n="agenthub.catalog.link">Prices and fees →</a></p>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 data-i18n="agenthub.examples.title">Integration examples</h2>

    <div class="card">
        <h3>HTTP x402 (cURL)</h3>
        <pre>curl -H "X-Payment: $PAYTOKEN" \
  https://grid402.online/api/v1/crypto</pre>
    </div>

    <div class="card">
        <h3>MCP</h3>
        <pre>{
  "method": "tools/call",
  "params": {
    "name": "grid402_crypto",
    "arguments": {}
  }
}</pre>
    </div>

    <div class="card">
        <h3>Budget-aware agent logic</h3>
        <pre>const budget = 1.00; // USDC
const spent = 0;
while (budget > spent) {
  const q = await grid402.query('crypto');
  spent += 0.0050;
}</pre>
        <p data-i18n="agenthub.budget.desc">Agents should implement their own budget controls; Grid402 does not authorize fund movement without explicit maintainer permission.</p>
    </div>

    <h2 data-i18n="agenthub.rules.title">Production rules</h2>
    <ul data-i18n-html="agenthub.rules.list">
        <li>Do not move funds without on-chain authorization compliant with your policies.</li>
        <li>Prefer <code>demo</code> for sandbox testing (5 queries/h/IP).</li>
        <li>Use a Session Pass to reduce latency and administrative cost.</li>
        <li>Validate <code>_oracle_proof</code> for sensitive responses.</li>
    </ul>
</main>
<script src="/assets/js/i18n.js?v=3.3.0"></script>
</body>
</html>
