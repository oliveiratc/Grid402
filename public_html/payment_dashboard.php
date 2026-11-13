<?php

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/services/pass.php';
$token = (string)($_GET['token'] ?? '');
$pass = ($token !== '') ? grid402_get_session_pass_status($token) : null;
$metrics = grid402_get_metrics();
$passesStats = grid402_get_passes_stats();
?>
<!DOCTYPE html>
<html lang="pt-BR">
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
<main>
    <p><a href="/">← Grid402</a> &nbsp;·&nbsp; <a href="/catalog">Catálogo</a> &nbsp;·&nbsp; <a href="/growth">Growth Program</a></p>
    <h1>Payment Dashboard</h1>
    <p>Área de acompanhamento para pagamento x402 individual e Session Pass. Os dois produtos são distintos e aparecem aqui com regras separadas.</p>

    <div class="grid">
        <div class="card">
            <h2>Pay-per-Request</h2>
            <p>Cada chamada é paga diretamente via x402/USDC. Ideal para consumo baixo, sem pré-carga.</p>
            <p><a href="/catalog">Consultar preços →</a></p>
        </div>
        <div class="card">
            <h2>Agent Pass</h2>
            <p>Pré-carga de créditos. Ideal para consumo recorrente com latência &lt;15ms.</p>
            <p><a href="/pass">Comprar pass →</a></p>
        </div>
    </div>

    <h2>Status do Pass (opcional)</h2>
    <form method="get" action="/payment-dashboard">
        <label for="token">Token pass_g402... (opcional)</label>
        <input id="token" name="token" value="<?php echo htmlspecialchars($token); ?>" placeholder="pass_g402_...">
        <button type="submit">Consultar</button>
    </form>

    <?php if ($pass && !empty($pass['ok'])): ?>
        <div class="card" style="margin-top:1rem;">
            <h3>Pass ativo</h3>
            <p><strong>Créditos restantes:</strong> <?php echo (int)($pass['credits_remaining'] ?? 0); ?> / <?php echo (int)($pass['credits_total'] ?? 0); ?></p>
            <p><strong>Validade:</strong> <?php echo htmlspecialchars((string)($pass['expires_at'] ?? '')); ?></p>
            <p><strong>Tier:</strong> <?php echo htmlspecialchars((string)($pass['tier'] ?? '')); ?></p>
        </div>
    <?php elseif ($token !== ''): ?>
        <p style="color:#F59E0B;margin-top:1rem;">Token inválido ou pass não encontrado.</p>
    <?php endif; ?>

    <h2>Resumo do hub</h2>
    <p><strong>Chamadas registradas:</strong> <?php echo (int)($metrics['total_requests'] ?? 0); ?></p>
    <p><strong>Revenue:</strong> <?php echo number_format((float)($metrics['total_revenue_usdc'] ?? 0), 4); ?> USDC</p>
    <p><strong>Passes emitidos:</strong> <?php echo (int)($passesStats['total_issued'] ?? 0); ?></p>
</main>
</body>
</html>
