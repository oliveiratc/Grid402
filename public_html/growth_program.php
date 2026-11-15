<?php
/**
 * Grid402 - GRID402 AGENT GROWTH PROGRAM
 * Programa para agentes autônomos, builders e integrações leves.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/growth.php';

$clientIp = grid402_get_trusted_client_ip();
$ipHash = hash_hmac('sha256', $clientIp, GRID402_CRON_SECRET);
$msg = '';
$err = '';
$action = (string)($_POST['action'] ?? ($_GET['action'] ?? ''));

// Token determinístico para formulários públicos + rate limit por IP
// (mesma família do controle usado em /pass e /admin).
$csrf = hash_hmac('sha256', 'grid402-growth-v1:' . GRID402_CRON_SECRET, GRID402_CRON_SECRET);

$rateFile = (defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR : __DIR__ . '/private_data') . '/growth_rate_limit.json';
function growth_rate_limit(string $file, string $key, int $max = 10): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $records = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $records = $raw ? (@json_decode($raw, true) ?: []) : [];
    }
    $now = time();
    foreach ($records as $k => $v) {
        if (($v['until'] ?? 0) < $now) unset($records[$k]);
    }
    $slot = $records[$key] ?? null;
    if ($slot) {
        $slot['count'] = (int)($slot['count'] ?? 0) + 1;
        if ($slot['count'] > $max) {
            return false;
        }
    } else {
        $slot = ['count' => 1, 'until' => $now + 3600];
    }
    $records[$key] = $slot;
    $fp = @fopen($file, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($records)); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    }
    return true;
}

// Processamento de formulários (único ponto de entrada)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');

    if ($submittedCsrf === '' || !hash_equals($csrf, $submittedCsrf)) {
        $err = 'Token inválido. Recarregue e tente novamente.';
    } elseif (!growth_rate_limit($rateFile, $ipHash, 10)) {
        $err = 'Limite de envios atingido. Tente novamente em 1 hora.';
    } else {
        if ($action === 'register_builder') {
            $result = grid402_growth_register_builder([
                'name' => (string)($_POST['name'] ?? ''),
                'email' => (string)($_POST['email'] ?? ''),
                'url' => (string)($_POST['url'] ?? ''),
                'proof_id' => $ipHash,
            ]);
            if (!empty($result['error'])) {
                $err = 'Não foi possível validar os dados do builder.';
            } else {
                $msg = 'Cadastro de builder criado. Aguarda validação administrativa.';
            }
        } elseif ($action === 'create_referral') {
            $result = grid402_growth_create_referral(['wallet' => (string)($_POST['wallet'] ?? '')]);
            $code = null;
            if (!empty($result['referrals']) && is_array($result['referrals'])) {
                foreach ($result['referrals'] as $r) {
                    if (($r['wallet_last4'] ?? '') === substr(strtolower((string)($_POST['wallet'] ?? '')), -4) && ($r['status'] ?? '') === 'active') {
                        $code = $r['code'] ?? null;
                        break;
                    }
                }
            }
            if (!empty($result['error'])) {
                $err = 'Carteira inválida. Use um endereço Ethereum 0x de 20 bytes.';
            } else {
                $msg = $code ? ('Código de indicação criado: ' . $code) : 'Código criado. Consulte o admin para a lista completa.';
            }
        } else {
            $err = 'Ação desconhecida.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID402 AGENT GROWTH PROGRAM</title>
    <meta name="description" content="Grid402 Growth Program: Starter, Builder Rewards, Agent Referrals, and On-Chain Reputation for autonomous agents.">
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --cyan:#00F0FF; --blue:#0052FF; --ok:#10B981; --warn:#F59E0B; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.6; }
        main { max-width:1100px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        a { color:var(--cyan); }
        h1 { font-size:2rem; letter-spacing:-.04em; margin-bottom:.25rem; }
        h2 { color:var(--cyan); margin-top:2.2rem; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1rem; margin:1rem 0; }
        .card { border:1px solid var(--border); background:var(--surface); border-radius:10px; padding:1.25rem; }
        .value { font-size:1.6rem; font-weight:700; color:var(--ok); }
        label { display:block; color:#CBD5E1; font-size:.85rem; margin:.65rem 0 .2rem; }
        input { width:100%; background:#111C38; border:1px solid var(--border); border-radius:6px; color:#fff; padding:.55rem; }
        button { margin-top:.8rem; padding:.6rem 1.15rem; border:0; border-radius:7px; background:var(--blue); color:#fff; font-weight:700; cursor:pointer; }
        .okmsg { background:rgba(16,185,129,.12); border:1px solid var(--ok); color:#B6F6D8; padding:.6rem .8rem; border-radius:8px; }
        .errmsg { background:rgba(239,68,68,.12); border:1px solid #EF4444; color:#FECACA; padding:.6rem .8rem; border-radius:8px; }
        code { color:var(--cyan); }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '', 'show_lang' => true]); ?>
<main>
    <h1 data-i18n="growth.title">GRID402 AGENT GROWTH PROGRAM</h1>
    <p data-i18n="growth.subtitle">Acquisition, activation, repeat purchase, and retention program for autonomous agents using Grid402 over USDC + x402 on Base.</p>

    <?php if ($msg !== ''): ?><div class="okmsg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="errmsg"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="grid">
        <div class="card"><div class="value">Starter</div><p data-i18n-html="growth.c1.p1"><strong>10 bonus credits</strong> for agents completing their first validated query.</p><p data-i18n="growth.c1.p2">Eligibility: first settled transaction on the specified wallet; 1 promotion per wallet.</p></div>
        <div class="card"><div class="value">Builders</div><p data-i18n-html="growth.c2.p1"><strong>100 credits</strong> per active integration completing 5 paid calls.</p><p data-i18n="growth.c2.p2">Registration, administrative validation, and anti-fraud control required.</p></div>
        <div class="card"><div class="value">Referrals</div><p data-i18n-html="growth.c3.p1"><strong>25 credits</strong> per onboarded integration validated by 10 calls.</p><p data-i18n="growth.c3.p2">HMAC-signed referral links; self-referral prohibited.</p></div>
    </div>

    <h2 data-i18n="growth.s1.title">5.1 Agent Starter</h2>
    <div class="card">
        <ol data-i18n-html="growth.s1.list">
            <li>Generate a Base wallet and connect it in the <a href="/#playground">playground</a> or configure an x402 payer in the SDK/MCP.</li>
            <li>Make a paid call (e.g.: <code>curl -H "X-Payment: $PAYTOKEN" https://grid402.online/api/v1/crypto</code>).</li>
            <li>Use the promotional credit to test other endpoints.</li>
        </ol>
        <p data-i18n="growth.s1.note">Requests remain pending administrative validation before issuance.</p>
    </div>

    <h2 data-i18n="growth.s2.title">5.2 Builder Rewards</h2>
    <div class="card">
        <form method="post" action="/growth">
            <label for="name" data-i18n="growth.s2.name">Builder / team name</label>
            <input id="name" name="name" required minlength="2" maxlength="80" placeholder="e.g., FiscalAgents Labs">
            <label for="email" data-i18n="growth.s2.email">Email or contact</label>
            <input id="email" name="email" type="email" required placeholder="you@example.com">
            <label for="url" data-i18n="growth.s2.url">Integration repository/URL</label>
            <input id="url" name="url" type="url" placeholder="https://github.com/your-org/agent-integration">
            <input type="hidden" name="action" value="register_builder">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <button type="submit" data-i18n="growth.s2.btn">Register as builder</button>
        </form>
    </div>

    <h2 data-i18n="growth.s3.title">5.3 Agent Referrals</h2>
    <div class="card">
        <form method="post" action="/growth">
            <label for="wallet" data-i18n="growth.s3.wallet">Base Wallet (0x...)</label>
            <input id="wallet" name="wallet" required pattern="^0x[a-fA-F0-9]{40}$" placeholder="0x0000000000000000000000000000000000000000">
            <input type="hidden" name="action" value="create_referral">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <button type="submit" data-i18n="growth.s3.btn">Generate referral code</button>
        </form>
    </div>

    <h2 data-i18n="growth.compliance.title">Compliance</h2>
    <p data-i18n="growth.compliance.desc">Grid402 provides infrastructure and verifiable public data; it does not decide credit, onboarding, or risk assignment. The builder configuring the agents assumes responsibility for their system's decisions.</p>
</main>
<script src="/assets/js/i18n.js?v=3.2.0"></script>
</body>
</html>
