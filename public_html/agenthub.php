<?php

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
$services = grid402_get_utility_services();
ksort($services);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID402 Agent Hub</title>
    <meta name="description" content="Central de integração para agentes autônomos — HTTP x402, MCP, Coinbase AgentKit, Python SDK, TypeScript SDK.">
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
<main>
    <p><a href="/">← Grid402</a> &nbsp;·&nbsp; <a href="/agents">Agentes IA existente</a> &nbsp;·&nbsp; <a href="/growth">Growth Program</a></p>
    <h1>GRID402 AGENT HUB</h1>
    <p>Central de integração para agentes autônomos: documentação, exemplos, preços e compatibilidade.</p>

    <h2>Catálogo de serviços</h2>
    <div class="grid">
        <?php foreach ($services as $id => $svc): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars((string)($svc['name'] ?? $id)); ?></h3>
                <p><?php echo htmlspecialchars((string)($svc['description'] ?? '')); ?></p>
                <code><?php echo htmlspecialchars((string)($svc['endpoint'] ?? '/api/v1/' . $id)); ?></code>
                <p><a href="/catalog">Preços e tarifas →</a></p>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Exemplos de integração</h2>

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
  spent += 0.0005;
}</pre>
        <p>Agentes devem implementar orçamento próprio; Grid402 não autoriza movimentação de fundos sem permissão explícita do mantenedor.</p>
    </div>

    <h2>Regras de produção</h2>
    <ul>
        <li>Não movimente fundos sem autorização on-chain compatível com suas políticas.</li>
        <li>Prefira <code>demo</code> para testes sandbox (30 consultas/h/IP).</li>
        <li>Use Session Pass para reduzir latência e custo administrativo.</li>
        <li>Valide <code>_oracle_proof</code> para respostas sensíveis.</li>
    </ul>
</main>
</body>
</html>
