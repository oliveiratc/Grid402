<?php
// trial.php - Trial gratuito de 200 créditos (Alavanca 2, topo de funil).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/pass.php';

$domain = defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online';
$token = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } else {
        // Rate limit por IP: 1 trial a cada 24h
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $rlFile = GRID402_PRIVATE_DIR . '/trial_rates.json';
        $rl = [];
        if (file_exists($rlFile)) {
            $raw = @file_get_contents($rlFile);
            $rl = $raw ? (@json_decode($raw, true) ?: []) : [];
        }
        $now = time();
        $last = (int) ($rl[$ip] ?? 0);
        if ($last > 0 && ($now - $last) < 86400) {
            $error = 'Já existe um trial ativo para este IP nas últimas 24h.';
        } else {
            $ref = 'trial:' . substr(hash('sha256', $email), 0, 16);
            $result = grid402_issue_fiat_pass($ref, 200 * 500, 'trial', $email, 'trial');
            if (!empty($result['pass_token'])) {
                $token = $result['pass_token'];
                $rl[$ip] = $now;
                @file_put_contents($rlFile, json_encode($rl, JSON_PRETTY_PRINT), LOCK_EX);
            } else {
                $error = 'Não foi possível emitir o trial. Tente novamente.';
            }
        }
    }
}

// Se for GET ou após POST, renderizar página
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Trial gratuito (200 créditos)</title>
    <style>
        :root { --bg:#0a0e17; --surface:#111827; --border:#1f2937; --text:#e5e7eb; --muted:#8fa2b9; --cyan:#00f0ff; --blue:#0052ff; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
        .wrap { max-width:640px; margin:0 auto; padding:2rem 1.25rem; }
        h1 { font-size:1.7rem; margin:0 0 .5rem; }
        p { color:var(--muted); }
        .token { background:var(--surface); border:1px solid var(--cyan); border-radius:8px; padding:1rem; word-break:break-all; font-family:monospace; color:var(--cyan); margin:1rem 0; }
        .btn { display:inline-block; background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#fff; padding:.7rem 1.2rem; border-radius:8px; text-decoration:none; font-weight:700; border:none; cursor:pointer; }
        input { width:100%; padding:.7rem; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text); margin:.5rem 0; }
        .err { color:#ff6b6b; }
        pre { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1rem; overflow-x:auto; color:#cbd5e1; font-size:.85rem; }
        a { color:var(--cyan); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Trial gratuito — 200 créditos</h1>
    <?php if ($token): ?>
        <p>Seu trial foi emitido. Guarde este token (ele não será reexibido):</p>
        <div class="token"><?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?></div>
        <p>Use no cabeçalho <code>X-Payment</code>:</p>
        <pre>curl -s -H "X-Payment: <?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"</pre>
        <p><a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/plans">Ver planos em BRL</a></p>
    <?php else: ?>
        <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <p>Cadastre seu e-mail e receba 200 créditos para testar a API em produção (sem cartão, sem USDC).</p>
        <form method="post">
            <input type="email" name="email" placeholder="seu@email.com" required>
            <button type="submit" class="btn">Receber trial</button>
        </form>
        <p><a href="<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>/plans">Ver planos em BRL</a></p>
    <?php endif; ?>
</div>
</body>
</html>
