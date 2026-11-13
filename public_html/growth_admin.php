<?php
/**
 * Grid402 - Growth Administration (protegido)
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/growth.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
session_name('GRID402_ADMIN_SESS');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}

$action = (string)($_POST['action'] ?? ($_GET['action'] ?? ''));
$msg = '';
$err = '';

$data = grid402_growth_read();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_config') {
        $payload = [
            'free_trial_limit' => $_POST['free_trial_limit'] ?? 0,
            'recurring_service_threshold' => $_POST['recurring_service_threshold'] ?? 3,
            'recurring_24h_threshold' => $_POST['recurring_24h_threshold'] ?? 5,
            'low_balance_threshold' => $_POST['low_balance_threshold'] ?? 10,
            'starter_promo_credits' => $_POST['starter_promo_credits'] ?? 10,
            'referral_reward_credits' => $_POST['referral_reward_credits'] ?? 25,
            'builder_reward_credits' => $_POST['builder_reward_credits'] ?? 100,
            'promo_auto_issue' => isset($_POST['promo_auto_issue']),
            'rewards_pending_validation' => !isset($_POST['rewards_pending_validation']) || $_POST['rewards_pending_validation'],
            'max_rewards_per_day' => $_POST['max_rewards_per_day'] ?? 25,
        ];
        $result = grid402_growth_update_config($payload);
        $msg = isset($result['error']) ? $result['error'] : 'Configuração atualizada.';
        $data = grid402_growth_read();
    } elseif ($action === 'issue_reward') {
        $result = grid402_growth_issue_pending_reward((string)($_POST['reward_id'] ?? ''));
        $msg = isset($result['error']) ? $result['error'] : 'Créditos emitidos.';
        $data = grid402_growth_read();
    } elseif ($action === 'toggle_builder') {
        $id = (string)($_POST['builder_id'] ?? '');
        $toggle = (string)($_POST['status'] ?? 'active');
        grid402_growth_update(function (array $data) use ($id, $toggle) {
            foreach ($data['builders'] as &$b) {
                if (($b['builder_id'] ?? '') === $id) {
                    $b['status'] = $toggle;
                    $b['validated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    break;
                }
            }
            return $data;
        });
        $msg = 'Builder atualizado.';
        $data = grid402_growth_read();
    }
}

$metrics = grid402_growth_metrics();
$cfg = $data['config'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Growth Administration</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --ok:#10B981; --warn:#F59E0B; --danger:#EF4444; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.55; }
        .container { max-width:1180px; margin:0 auto; padding:1rem 1.25rem 3rem; }
        a { color:var(--cyan); }
        .panel { border:1px solid var(--border); background:var(--surface); border-radius:10px; padding:1rem; margin-bottom:1rem; }
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.6rem; }
        .kpi { background:#111C38; border:1px solid var(--border); border-radius:8px; padding:.75rem; }
        .value { font-size:1.4rem; font-weight:700; }
        table { width:100%; border-collapse:collapse; font-size:.82rem; }
        th,td { border-bottom:1px solid var(--border); padding:.5rem; text-align:left; }
        input, select { background:#111C38; border:1px solid var(--border); color:#fff; padding:.4rem; border-radius:6px; width:100%; }
        button.primary { background:var(--cyan); color:#04121A; border:0; border-radius:6px; padding:.45rem .8rem; font-weight:700; }
        button.danger { background:var(--danger); color:#fff; border:0; border-radius:6px; padding:.45rem .8rem; }
        .ok { color:var(--ok); } .warn { color:var(--warn); } .muted { color:var(--muted); }
    </style>
</head>
<body>
<div class="container">
    <p><a href="/admin">← Voltar ao Admin</a></p>
    <h1>Growth Administration</h1>
    <?php if ($msg): ?><div class="panel ok"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <section class="panel">
        <h2>KPIs</h2>
        <div class="kpi-grid">
            <div class="kpi"><div class="value"><?php echo (int)$metrics['wallets_connected']; ?></div><div class="muted">Wallets conectadas</div></div>
            <div class="kpi"><div class="value"><?php echo (int)$metrics['wallets_paying']; ?></div><div class="muted">Pagantes</div></div>
            <div class="kpi"><div class="value"><?php echo number_format((float)$metrics['conversion_first_payment'],1); ?>%</div><div class="muted">Conversão 1ª compra</div></div>
            <div class="kpi"><div class="value"><?php echo number_format((float)$metrics['paid_revenue_usdc'],4); ?></div><div class="muted">USDC arrecadados</div></div>
            <div class="kpi"><div class="value"><?php echo number_format((float)$metrics['wallet_retention'],1); ?>%</div><div class="muted">Retenção</div></div>
            <div class="kpi"><div class="value"><?php echo (int)$metrics['paid_transactions']; ?></div><div class="muted">Transações pagas</div></div>
        </div>
    </section>

    <section class="panel">
        <h2>Configuração do Engine</h2>
        <form method="post" class="grid" action="/growth_admin">
            <input type="hidden" name="action" value="update_config">
            <label>Free trial (consultas)<input type="number" min="0" name="free_trial_limit" value="<?php echo (int)$cfg['free_trial_limit']; ?>"></label>
            <label>Threshold recurring service<input type="number" min="0" name="recurring_service_threshold" value="<?php echo (int)$cfg['recurring_service_threshold']; ?>"></label>
            <label>Threshold 24h<input type="number" min="0" name="recurring_24h_threshold" value="<?php echo (int)$cfg['recurring_24h_threshold']; ?>"></label>
            <label>Low balance<input type="number" min="0" name="low_balance_threshold" value="<?php echo (int)$cfg['low_balance_threshold']; ?>"></label>
            <label>Starter credits<input type="number" min="0" name="starter_promo_credits" value="<?php echo (int)$cfg['starter_promo_credits']; ?>"></label>
            <label>Referral reward<input type="number" min="0" name="referral_reward_credits" value="<?php echo (int)$cfg['referral_reward_credits']; ?>"></label>
            <label>Builder reward<input type="number" min="0" name="builder_reward_credits" value="<?php echo (int)$cfg['builder_reward_credits']; ?>"></label>
            <label>Max rewards/day<input type="number" min="0" name="max_rewards_per_day" value="<?php echo (int)$cfg['max_rewards_per_day']; ?>"></label>
            <label><input type="checkbox" name="promo_auto_issue" value="1" <?php echo $cfg['promo_auto_issue'] ? 'checked' : ''; ?>> Auto-issue promo (admin)</label>
            <label><input type="checkbox" name="rewards_pending_validation" value="1" <?php echo $cfg['rewards_pending_validation'] ? 'checked' : ''; ?>> Rewards pendentes de validação</label>
            <button type="submit" class="primary">Salvar</button>
        </form>
    </section>

    <section class="panel">
        <h2>Recompensas pendentes</h2>
        <table>
            <thead><tr><th>ID</th><th>Tipo</th><th>Créditos</th><th>Motivo</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($data['rewards'] ?? []) as $r): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string)($r['reward_id'] ?? '')); ?></code></td>
                    <td><?php echo htmlspecialchars((string)($r['type'] ?? '')); ?></td>
                    <td><?php echo (int)($r['credits'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['reason'] ?? '')); ?></td>
                    <td class="<?php echo ($r['status'] ?? '') === 'issued' ? 'ok' : 'warn'; ?>"><?php echo htmlspecialchars((string)($r['status'] ?? 'pending')); ?></td>
                    <td>
                        <?php if (($r['status'] ?? '') === 'pending'): ?>
                            <form method="post" action="/growth_admin" style="display:inline"><input type="hidden" name="action" value="issue_reward"><input type="hidden" name="reward_id" value="<?php echo htmlspecialchars((string)$r['reward_id']); ?>"><button class="primary" type="submit">Aprovar</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Builders cadastrados</h2>
        <table>
            <thead><tr><th>Slug</th><th>Nome</th><th>Status</th><th>Última atualização</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($data['builders'] ?? []) as $b): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($b['slug'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($b['name'] ?? '')); ?></td>
                    <td class="<?php echo ($b['status'] ?? '') === 'active' ? 'ok' : 'warn'; ?>"><?php echo htmlspecialchars((string)($b['status'] ?? 'pending')); ?></td>
                    <td><?php echo htmlspecialchars((string)($b['validated_at'] ?? 'pendente')); ?></td>
                    <td>
                        <form method="post" action="/growth_admin" style="display:inline"><input type="hidden" name="action" value="toggle_builder"><input type="hidden" name="builder_id" value="<?php echo htmlspecialchars((string)$b['builder_id']); ?>"><input type="hidden" name="status" value="active"><button class="primary" type="submit">Validar</button></form>
                        <form method="post" action="/growth_admin" style="display:inline"><input type="hidden" name="action" value="toggle_builder"><input type="hidden" name="builder_id" value="<?php echo htmlspecialchars((string)$b['builder_id']); ?>"><input type="hidden" name="status" value="pending"><button class="danger" type="submit">Rejeitar</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
