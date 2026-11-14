<?php

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/growth.php';
$data = grid402_growth_read();
$builders = array_values(is_array($data['builders'] ?? null) ? $data['builders'] : []);
// Ordena por solicitações e status para transparência pública
usort($builders, function ($a, $b) {
    $sa = ($a['status'] ?? '') === 'active' ? 1 : 0;
    $sb = ($b['status'] ?? '') === 'active' ? 1 : 0;
    return $sb <=> $sa;
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID402 Builder Reputation</title>
    <meta name="description" content="Reputação pública de builders Grid402 — baseada em consumo legítimo, não apenas volume financeiro.">
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --ok:#10B981; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.6; }
        main { max-width:900px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        a { color:var(--cyan); }
        table { width:100%; border-collapse:collapse; margin-top:1rem; }
        th,td { text-align:left; padding:.6rem .75rem; border-bottom:1px solid var(--border); }
        th { color:#CBD5E1; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; }
        .empty { color:var(--muted); padding:1.5rem; text-align:center; border:1px solid var(--border); border-radius:8px; }
        .ok { color: var(--ok); }
    </style>
</head>
<body>
<main>
    <p><a href="/growth">← Growth Program</a></p>
    <h1>GRID402 On-Chain Reputation</h1>
    <p>Ranking público de builders validados com base em consumo comprovado. Sem dados pessoais ou endereço completo de carteira.</p>
    <?php if (empty($builders)): ?>
        <div class="empty">Nenhum builder validado ainda. <a href="/growth">Cadastre-se no programa →</a></div>
    <?php else: ?>
        <table>
            <thead><tr><th>#</th><th>Builder</th><th>Status</th><th>Chamadas pagas</th></tr></thead>
            <tbody>
            <?php $rank = 0; foreach ($builders as $b): $rank++; ?>
                <tr>
                    <td><?php echo $rank; ?></td>
                    <td><?php echo htmlspecialchars((string)($b['slug'] ?? 'builder')); ?></td>
                    <td class="<?php echo ($b['status'] ?? '') === 'active' ? 'ok' : ''; ?>"><?php echo htmlspecialchars((string)($b['status'] ?? 'pending')); ?></td>
                    <td><?php echo (int)($b['paid_requests_tracked'] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>
