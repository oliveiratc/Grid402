<?php
/**
 * Grid402 - GRID402 AGENT GROWTH PROGRAM
 * Programa para agentes autônomos, builders e integrações leves.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/growth.php';

$feedback = null;
$feedbackType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'register_builder') {
        $input = [
            'name' => (string) ($_POST['name'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
            'url' => (string) ($_POST['url'] ?? ''),
            'proof_id' => hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'grid402-proof'),
        ];
        $result = grid402_growth_register_builder($input);
        if (!empty($result['error'])) {
            $feedback = 'Nome ou e-mail inválido. Verifique e tente novamente.';
            $feedbackType = 'error';
        } else {
            $feedback = 'Builder cadastrado! Em breve você receberá um convite para validação e ativação.';
            $feedbackType = 'ok';
        }
    } elseif ($action === 'create_referral') {
        $wallet = trim((string) ($_POST['wallet'] ?? ''));
        $result = grid402_growth_create_referral(['wallet' => $wallet]);
        if (!empty($result['error'])) {
            $feedback = 'Carteira inválida. Use endereço base0x... com 40 hex.';
            $feedbackType = 'error';
        } else {
            $data = grid402_growth_read();
            $match = null;
            foreach (array_reverse($data['referrals'] ?? []) as $r) {
                if (($r['wallet_last4'] ?? '') === strtolower(substr($wallet, -4)) && empty($r['used']) && ($r['status'] ?? '') === 'active') {
                    $match = $r['code'] ?? null;
                    break;
                }
            }
            $feedback = $match ? 'Seu código de indicação: ' . $match : 'Código criado. Consulte no admin para ver a lista completa.';
            $feedbackType = 'ok';
        }
    }
}

?>
$clientIp = grid402_get_trusted_client_ip();
$ipHash = hash_hmac('sha256', $clientIp, GRID402_CRON_SECRET);
$msg = '';
$err = '';
$action = (string)($_POST['action'] ?? ($_GET['action'] ?? ''));

// Simples rate limit para formulários públicos
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

// Processamento de formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');
    $expectedCsrf = hash_hmac('sha256', 'grid402-growth-v1:' . GRID402_CRON_SECRET, GRID402_CRON_SECRET);

    if (!hash_equals($expectedCsrf, $submittedCsrf)) {
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
            $msg = isset($result['error']) ? '' : 'Cadastro de builder criado. Aguarda validação administrativa.';
            $err = isset($result['error']) ? 'Não foi possível validar os dados do builder.' : '';
        } elseif ($action === 'create_referral') {
            $result = grid402_growth_create_referral(['wallet' => (string)($_POST['wallet'] ?? '')]);
            $code = null;
            if (($result['referrals'] ?? null) !== null) {
                foreach ((array)$result['referrals'] as $r) {
                    if (($r['wallet_last4'] ?? '') === substr(strtolower((string)($_POST['wallet'] ?? '')), -4) && ($r['created_at'] ?? '') === gmdate('Y-m-d\TH:i:s\Z')) {
                        $code = $r['code'];
                        break;
                    }
                }
            }
            $msg = isset($result['error']) ? '' : 'Código de indicação criado: ' . ($code ?: '');
            $err = isset($result['error']) ? 'Carteira inválida. Use um endereço Ethereum 0x de 20 bytes.' : '';
        }
    }
}

$csrf = hash_hmac('sha256', 'grid402-growth-v1:' . GRID402_CRON_SECRET, GRID402_CRON_SECRET);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRID402 AGENT GROWTH PROGRAM</title>
    <meta name="description" content="Programa de crescimento do Grid402: Starter, Builder Rewards, Agent Referrals e On-Chain Reputation para agentes autônomos.">
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
<main>
    <p><a href="/">← Grid402</a></p>
    <h1>GRID402 AGENT GROWTH PROGRAM</h1>
    <p>Programa de aquisição, ativação, recompra e retenção para agentes autônomos que usam o Grid402 sobre USDC + x402 na Base.</p>

    <?php if (!empty($feedback)): ?>
        <div style="background:<?php echo ($feedbackType==='ok'?'#0E3A22':'#3A1313'); ?>; border:1px solid <?php echo ($feedbackType==='ok'?'#10B981':'#EF4444'); ?>; padding:.7rem .9rem; border-radius:8px; color:<?php echo ($feedbackType==='ok'?'#B6F6D8':'#FECACA'); ?>; margin:1rem 0;"><?php echo htmlspecialchars((string)$feedback); ?></div>
    <?php endif; ?>

    <?php if ($msg): ?><div class="okmsg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="errmsg"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="grid">
        <div class="card"><div class="value">Starter</div><p><strong>10 créditos bônus</strong> para agentes que fizerem a primeira consulta validada.</p><p>Elegibilidade: primeira transação liquidada na carteira indicada; 1 promoção por carteira.</p></div>
        <div class="card"><div class="value">Builders</div><p><strong>100 créditos</strong> por integração ativa que passe por 5 chamadas pagas.</p><p>Registro, validação administrativa e controle antifraude obrigatórios.</p></div>
        <div class="card"><div class="value">Referrals</div><p><strong>25 créditos</strong> por integração onboard validada por 10 chamadas.</p><p>Links de indicação com HMAC; sem autorreferência.</p></div>
    </div>

    <h2>5.1 Agent Starter</h2>
    <div class="card">
        <ol>
            <li>Gere uma carteira Base e conecte-a no <a href="/playground">playground</a> ou configure um payer x402 no SDK/MCP.</li>
            <li>Faça uma chamada paga (ex.: <code>$ curl -H "X-Payment: $PAYTOKEN" https://grid402.online/api/v1/crypto</code>).</li>
            <li>Use o crédito promocional para testar outros endpoints.</li>
        </ol>
        <p>As solicitações ficam pendentes de validação administrativa antes de emissão.</p>
    </div>

    <h2>5.2 Builder Rewards</h2>
    <div class="card">
        <form method="post" action="/growth">
            <label for="name">Nome do builder / time</label>
            <input id="name" name="name" required minlength="2" maxlength="80" placeholder="Ex.: FiscalAgents Labs">
            <label for="email">E-mail ou contato</label>
            <input id="email" name="email" type="email" required placeholder="you@example.com">
            <label for="url">Repositório/URL da integração</label>
            <input id="url" name="url" type="url" placeholder="https://github.com/your-org/agent-integration">
            <input type="hidden" name="action" value="register_builder">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <button type="submit">Cadastrar como builder</button>
        </form>
    </div>

    <h2>5.3 Agent Referrals</h2>
    <div class="card">
        <form method="post" action="/growth">
            <label for="wallet">Carteira Base (0x...)</label>
            <input id="wallet" name="wallet" required pattern="^0x[a-fA-F0-9]{40}$" placeholder="0x0000000000000000000000000000000000000000">
            <input type="hidden" name="action" value="create_referral">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <button type="submit">Gerar referral code</button>
        </form>
    </div>

    <h2>Conformidade</h2>
    <p>O Grid402 oferece infraestrutura e dados públicos verificáveis; não decide crédito, cadastro ou atribuição de risco. O builder configurador dos agentes assume responsabilidade pelas decisões do próprio sistema.</p>
</main>
</body>
</html>
