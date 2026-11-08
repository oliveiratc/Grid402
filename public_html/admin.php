<?php
/**
 * Grid402 // Painel Administrativo de Monitoramento e Faturamento em Tempo Real
 * 
 * Protegido por autenticação segura de sessão com Bcrypt e Zero-DB.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/services/forex.php';

// 1. Configuração estrita de sessão segura
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
session_name('GRID402_ADMIN_SESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authFile = GRID402_FILE_ADMIN_AUTH;
$hasSetup = file_exists($authFile);
$isLoggedIn = !empty($_SESSION['admin_logged_in']);
$action = (string) ($_GET['action'] ?? '');
$apiParam = (string) ($_GET['api'] ?? '');
$errorMsg = '';
$successMsg = '';

// 2. Ações de Logout e Reset de Segurança
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: /admin');
    exit;
}

if ($action === 'reset' && !empty($_GET['secret']) && hash_equals(GRID402_CRON_SECRET, (string)$_GET['secret'])) {
    if (file_exists($authFile)) {
        @unlink($authFile);
    }
    $_SESSION = [];
    session_destroy();
    header('Location: /admin?reset=done');
    exit;
}

// 3. Processamento de POST (Setup ou Login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3.1. Setup de Primeiro Acesso
    if (!$hasSetup) {
        $user = trim((string) ($_POST['username'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        $passConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($user) < 3) {
            $errorMsg = 'O nome de usuário deve ter pelo menos 3 caracteres.';
        } elseif (strlen($pass) < 8) {
            $errorMsg = 'A senha deve ter pelo menos 8 caracteres.';
        } elseif ($pass !== $passConfirm) {
            $errorMsg = 'As senhas informadas não coincidem.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 11]);
            $authData = [
                'username' => $user,
                'password_hash' => $hash,
                'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'last_login' => gmdate('Y-m-d\TH:i:s\Z'),
                'login_count' => 1,
            ];
            $saved = grid402_atomic_write($authFile, $authData);

            if (!$saved || !file_exists($authFile)) {
                $errorMsg = 'Erro crítico: não foi possível gravar as credenciais no disco. Verifique permissões do diretório de armazenamento.';
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $user;
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                header('Location: /admin?setup=success');
                exit;
            }
        }
    } else {
        // 3.2. Login Normal
        $user = trim((string) ($_POST['username'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');

        $authContent = @file_get_contents($authFile);
        $authData = $authContent ? @json_decode($authContent, true) : null;

        if ($authData && $user === ($authData['username'] ?? '') && password_verify($pass, (string)($authData['password_hash'] ?? ''))) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $authData['username'];

            $authData['last_login'] = gmdate('Y-m-d\TH:i:s\Z');
            $authData['login_count'] = ((int)($authData['login_count'] ?? 0)) + 1;
            grid402_atomic_write($authFile, $authData);

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            header('Location: /admin');
            exit;
        } else {
            usleep(350000); // Delay defensivo anti-bruteforce
            $errorMsg = 'Usuário ou senha incorretos.';
        }
    }
}

// 4. Endpoint de Polling JSON (/admin?api=stats)
if ($apiParam === 'stats') {
    if (!$isLoggedIn) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Sessão administrativa expirada ou inválida.'], JSON_PRETTY_PRINT);
        exit;
    }

    $metrics = grid402_get_metrics();
    $forex = grid402_service_forex();
    $usdRate = (float)($forex['rates']['USD']['bid'] ?? 5.50);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $revenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
    $today = gmdate('Y-m-d');
    $todayUsdc = (float)($metrics['daily'][$today]['usdc'] ?? 0.0);

    echo json_encode([
        'status' => 'success',
        'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
        'total_requests' => (int)($metrics['total_requests'] ?? 0),
        'total_paid_requests' => (int)($metrics['total_paid_requests'] ?? 0),
        'total_revenue_usdc' => $revenueUsdc,
        'total_revenue_brl' => round($revenueUsdc * $usdRate, 2),
        'today_revenue_usdc' => $todayUsdc,
        'today_revenue_brl' => round($todayUsdc * $usdRate, 2),
        'usd_rate' => $usdRate,
        'status_counts' => $metrics['status_counts'] ?? [],
        'service_counts' => $metrics['service_counts'] ?? [],
        'recent_events' => $metrics['recent_events'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Se não estiver logado, exibe tela de login ou setup
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Grid402 // Acesso Administrativo</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg: #090D14;
                --surface: #111726;
                --border: #1E293B;
                --text-main: #F1F5F9;
                --text-muted: #94A3B8;
                --primary: #10B981;
                --accent: #38BDF8;
                --danger: #EF4444;
                --font-sans: 'Plus Jakarta Sans', system-ui, sans-serif;
                --font-mono: 'JetBrains Mono', monospace;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                background-color: var(--bg);
                color: var(--text-main);
                font-family: var(--font-sans);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }
            .card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 12px;
                width: 100%;
                max-width: 420px;
                padding: 2.5rem;
                box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            }
            .brand {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 2rem;
            }
            .brand-badge {
                background: linear-gradient(135deg, #10B981, #06B6D4);
                color: #000;
                font-family: var(--font-mono);
                font-weight: 800;
                font-size: 0.85rem;
                padding: 0.35rem 0.65rem;
                border-radius: 6px;
            }
            .brand-name { font-size: 1.35rem; font-weight: 800; }
            h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
            p.sub { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.75rem; }
            .form-group { margin-bottom: 1.25rem; }
            label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
            input[type="text"], input[type="password"] {
                width: 100%;
                background: #030712;
                border: 1px solid var(--border);
                border-radius: 6px;
                padding: 0.75rem 1rem;
                color: var(--text-main);
                font-size: 0.95rem;
                font-family: var(--font-sans);
            }
            input:focus { outline: none; border-color: var(--accent); }
            .btn {
                width: 100%;
                background: var(--primary);
                color: #000;
                font-weight: 700;
                font-size: 0.95rem;
                padding: 0.75rem;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                transition: opacity 0.2s;
                margin-top: 0.5rem;
            }
            .btn:hover { opacity: 0.9; }
            .alert-error {
                background: rgba(239, 68, 68, 0.15);
                border: 1px solid var(--danger);
                color: #FCA5A5;
                padding: 0.75rem 1rem;
                border-radius: 6px;
                font-size: 0.85rem;
                margin-bottom: 1.5rem;
            }
            .alert-info {
                background: rgba(56, 189, 248, 0.15);
                border: 1px solid var(--accent);
                color: #BAE6FD;
                padding: 0.75rem 1rem;
                border-radius: 6px;
                font-size: 0.85rem;
                margin-bottom: 1.5rem;
            }
            .back-link {
                display: block;
                text-align: center;
                margin-top: 1.75rem;
                font-size: 0.85rem;
                color: var(--text-muted);
                text-decoration: none;
            }
            .back-link:hover { color: var(--accent); }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="brand">
                <span class="brand-badge">GRID // 402</span>
                <span class="brand-name">Painel Admin</span>
            </div>

            <?php if (!$hasSetup): ?>
                <h1>Configurar Acesso</h1>
                <p class="sub">Primeiro acesso detectado. Crie as credenciais mestras do operador.</p>
                <div class="alert-info">🔒 Estas credenciais serão salvas com criptografia forte e protegerão os dados financeiros e métricas de uso.</div>
            <?php else: ?>
                <h1>Autenticação Restrita</h1>
                <p class="sub">Informe suas credenciais para visualizar métricas e faturamento.</p>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert-error"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="username">Usuário Administrador</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="username" autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="<?php echo $hasSetup ? 'current-password' : 'new-password'; ?>" minlength="8">
                </div>
                <?php if (!$hasSetup): ?>
                    <div class="form-group">
                        <label for="password_confirm">Confirme a Senha</label>
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" minlength="8">
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn"><?php echo $hasSetup ? 'Entrar no Painel' : 'Salvar e Acessar'; ?></button>
            </form>

            <a href="/" class="back-link">← Voltar para o portal público</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Visualização do Dashboard Autenticado
$metrics = grid402_get_metrics();
$forex = grid402_service_forex();
$usdRate = (float)($forex['rates']['USD']['bid'] ?? 5.50);

$revenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
$revenueBrl = round($revenueUsdc * $usdRate, 2);

$today = gmdate('Y-m-d');
$todayUsdc = (float)($metrics['daily'][$today]['usdc'] ?? 0.0);
$todayBrl = round($todayUsdc * $usdRate, 2);

$totalReqs = (int)($metrics['total_requests'] ?? 0);
$paidReqs = (int)($metrics['total_paid_requests'] ?? 0);
$reqs402 = (int)($metrics['status_counts']['402'] ?? 0);
$conversionRate = ($reqs402 + $paidReqs > 0) ? round(($paidReqs / ($reqs402 + $paidReqs)) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Painel de Telemetria e Faturamento</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090D14;
            --surface: #111726;
            --surface-hover: #162035;
            --border: #1E293B;
            --text-main: #F1F5F9;
            --text-muted: #94A3B8;
            --primary: #10B981;
            --primary-glow: rgba(16, 185, 129, 0.15);
            --accent: #38BDF8;
            --warning: #F59E0B;
            --danger: #EF4444;
            --font-sans: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-sans);
            padding: 0 1.5rem;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 0 4rem;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .logo-wrap { display: flex; align-items: center; gap: 0.85rem; }
        .logo-badge {
            background: linear-gradient(135deg, #10B981, #06B6D4);
            color: #000;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }
        .logo-title { font-size: 1.4rem; font-weight: 800; }
        .user-nav { display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; }
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--primary);
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 600;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary);
            box-shadow: 0 0 8px var(--primary);
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-outline:hover { background: var(--surface); border-color: var(--accent); }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        .kpi-title {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
        }
        .kpi-value {
            font-size: 1.85rem;
            font-weight: 800;
            font-family: var(--font-mono);
            letter-spacing: -0.5px;
        }
        .kpi-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.4rem;
        }

        /* 2-Columns Layout */
        .analytics-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        @media (max-width: 860px) {
            .analytics-row { grid-template-columns: 1fr; }
        }
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        .panel-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .bar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.85rem;
            font-size: 0.85rem;
        }
        .bar-label { width: 90px; font-family: var(--font-mono); }
        .bar-track {
            flex: 1;
            height: 8px;
            background: #030712;
            border-radius: 4px;
            margin: 0 1rem;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #06B6D4, #10B981);
            border-radius: 4px;
            transition: width 0.4s ease;
        }
        .bar-count { width: 50px; text-align: right; font-family: var(--font-mono); color: var(--text-muted); }

        /* Table */
        .table-wrap {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #030712;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
        }
        th {
            background: rgba(255,255,255,0.02);
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            font-family: var(--font-mono);
        }
        tr:hover td { background: rgba(255,255,255,0.015); }
        .tag-status {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .status-200 { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .status-402 { background: rgba(245, 158, 11, 0.15); color: #FBBF24; }
        .status-403 { background: rgba(239, 68, 68, 0.15); color: #F87171; }
        .tag-paid {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .tx-link { color: var(--accent); text-decoration: none; }
        .tx-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-wrap">
                <span class="logo-badge">GRID // 402</span>
                <span class="logo-title">Monitoramento e Faturamento</span>
            </div>
            <div class="user-nav">
                <div class="live-indicator">
                    <span class="pulse-dot"></span>
                    <span id="live-timer-text">Ao Vivo (5s)</span>
                </div>
                <button class="btn-outline" onclick="fetchLiveStats(true)">🔄 Atualizar</button>
                <a href="/" target="_blank" class="btn-outline">🌐 Ver Portal</a>
                <a href="/admin?action=logout" class="btn-outline" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">Sair</a>
            </div>
        </header>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Faturamento Total</div>
                <div class="kpi-value" id="kpi-total-usdc">US$ <?php echo number_format($revenueUsdc, 4); ?></div>
                <div class="kpi-sub" id="kpi-total-brl">≈ R$ <?php echo number_format($revenueBrl, 2, ',', '.'); ?> (Base Mainnet)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Faturamento Hoje</div>
                <div class="kpi-value" id="kpi-today-usdc">US$ <?php echo number_format($todayUsdc, 4); ?></div>
                <div class="kpi-sub" id="kpi-today-brl">≈ R$ <?php echo number_format($todayBrl, 2, ',', '.'); ?> hoje</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Consultas Totais</div>
                <div class="kpi-value" id="kpi-total-reqs"><?php echo number_format($totalReqs, 0, ',', '.'); ?></div>
                <div class="kpi-sub" id="kpi-paid-reqs"><?php echo number_format($paidReqs, 0, ',', '.'); ?> consultas pagas liquidadas</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Taxa de Conversão</div>
                <div class="kpi-value" id="kpi-conversion"><?php echo $conversionRate; ?>%</div>
                <div class="kpi-sub">Pagos / Desafios 402 emitidos</div>
            </div>
        </div>

        <!-- 2 Columns: Tráfego por Serviço e Códigos de Status -->
        <div class="analytics-row">
            <div class="panel">
                <div class="panel-title">Volume por Micro-Serviço</div>
                <div id="service-bars-container">
                    <?php
                    $servicesList = ['forex', 'crypto', 'dns', 'ssl', 'asn', 'cep', 'summary'];
                    $serviceCounts = $metrics['service_counts'] ?? [];
                    $maxSvc = max(array_values($serviceCounts) ?: [1]);
                    if ($maxSvc <= 0) $maxSvc = 1;

                    foreach ($servicesList as $s):
                        $cnt = (int)($serviceCounts[$s] ?? 0);
                        $pct = round(($cnt / $maxSvc) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?php echo $s; ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                        <span class="bar-count"><?php echo $cnt; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title">Distribuição de Status HTTP</div>
                <div id="status-bars-container">
                    <?php
                    $statusCounts = $metrics['status_counts'] ?? [];
                    $totalStatus = array_sum($statusCounts) ?: 1;
                    $statuses = [
                        '200' => ['label' => '200 OK', 'color' => '#10B981'],
                        '402' => ['label' => '402 Desafio', 'color' => '#F59E0B'],
                        '403' => ['label' => '403 Replay', 'color' => '#EF4444'],
                    ];
                    foreach ($statuses as $code => $info):
                        $cnt = (int)($statusCounts[$code] ?? 0);
                        $pct = round(($cnt / $totalStatus) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?php echo $info['label']; ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $info['color']; ?>;"></div></div>
                        <span class="bar-count"><?php echo $cnt; ?> (<?php echo $pct; ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Feed de Consultas em Tempo Real -->
        <div class="panel">
            <div class="panel-title">
                <span>Feed de Consultas Recentes (Últimas 50)</span>
                <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);" id="last-updated-text">Atualizado há instantes</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Horário (UTC)</th>
                            <th>Serviço</th>
                            <th>Status</th>
                            <th>Tipo</th>
                            <th>Valor (USDC)</th>
                            <th>Transação On-Chain (Base)</th>
                            <th>IP Requisitante (LGPD)</th>
                        </tr>
                    </thead>
                    <tbody id="recent-events-tbody">
                        <?php
                        $events = $metrics['recent_events'] ?? [];
                        if (empty($events)):
                        ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma consulta registrada até o momento.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?php echo substr($ev['timestamp'] ?? '', 11, 8); ?></td>
                                <td><strong><?php echo htmlspecialchars((string)($ev['service'] ?? ''), ENT_QUOTES); ?></strong></td>
                                <td>
                                    <?php $st = (int)($ev['status'] ?? 200); ?>
                                    <span class="tag-status status-<?php echo $st; ?>">HTTP <?php echo $st; ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($ev['is_paid'])): ?>
                                        <span class="tag-paid">PAGO x402</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">GRATUITO</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($ev['is_paid']) ? '$' . $ev['price_usdc'] : '-'; ?></td>
                                <td>
                                    <?php if (!empty($ev['tx_hash'])): ?>
                                        <a href="https://basescan.org/tx/<?php echo htmlspecialchars($ev['tx_hash'], ENT_QUOTES); ?>" target="_blank" class="tx-link">
                                            <?php echo substr($ev['tx_hash'], 0, 8) . '...' . substr($ev['tx_hash'], -6); ?> ↗
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-muted);"><?php echo htmlspecialchars((string)($ev['ip_masked'] ?? 'xxx.xxx.xxx.xxx'), ENT_QUOTES); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function fetchLiveStats(isManual = false) {
            try {
                const res = await fetch('/admin?api=stats');
                if (res.status === 401) {
                    window.location.reload();
                    return;
                }
                const data = await res.json();
                if (data.status !== 'success') return;

                // 1. Atualizar KPIs
                document.getElementById('kpi-total-usdc').textContent = 'US$ ' + Number(data.total_revenue_usdc).toFixed(4);
                document.getElementById('kpi-total-brl').textContent = '≈ R$ ' + Number(data.total_revenue_brl).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                document.getElementById('kpi-today-usdc').textContent = 'US$ ' + Number(data.today_revenue_usdc).toFixed(4);
                document.getElementById('kpi-today-brl').textContent = '≈ R$ ' + Number(data.today_revenue_brl).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' hoje';
                document.getElementById('kpi-total-reqs').textContent = Number(data.total_requests).toLocaleString('pt-BR');
                document.getElementById('kpi-paid-reqs').textContent = Number(data.total_paid_requests).toLocaleString('pt-BR') + ' consultas pagas liquidadas';

                const reqs402 = Number(data.status_counts['402'] || 0);
                const paid = Number(data.total_paid_requests || 0);
                const conv = (reqs402 + paid > 0) ? ((paid / (reqs402 + paid)) * 100).toFixed(1) : 0;
                document.getElementById('kpi-conversion').textContent = conv + '%';

                // 2. Atualizar Barras de Serviços
                const servicesList = ['forex', 'crypto', 'dns', 'ssl', 'asn', 'cep', 'summary'];
                const sCounts = data.service_counts || {};
                const maxS = Math.max(...Object.values(sCounts).concat([1]));
                let sHtml = '';
                servicesList.forEach(s => {
                    const c = Number(sCounts[s] || 0);
                    const p = Math.round((c / maxS) * 100);
                    sHtml += `
                    <div class="bar-row">
                        <span class="bar-label">${s}</span>
                        <div class="bar-track"><div class="bar-fill" style="width: ${p}%;"></div></div>
                        <span class="bar-count">${c}</span>
                    </div>`;
                });
                document.getElementById('service-bars-container').innerHTML = sHtml;

                // 3. Atualizar Tabela de Eventos
                const tbody = document.getElementById('recent-events-tbody');
                const events = data.recent_events || [];
                if (events.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma consulta registrada até o momento.</td></tr>';
                } else {
                    let rowsHtml = '';
                    events.forEach(ev => {
                        const time = (ev.timestamp || '').substr(11, 8);
                        const st = ev.status || 200;
                        const paidTag = ev.is_paid ? '<span class="tag-paid">PAGO x402</span>' : '<span style="color: var(--text-muted); font-size: 0.75rem;">GRATUITO</span>';
                        const val = ev.is_paid ? '$' + ev.price_usdc : '-';
                        const tx = ev.tx_hash ? `<a href="https://basescan.org/tx/${ev.tx_hash}" target="_blank" class="tx-link">${ev.tx_hash.substr(0, 8)}...${ev.tx_hash.substr(-6)} ↗</a>` : '<span style="color: var(--text-muted);">-</span>';

                        rowsHtml += `
                        <tr>
                            <td>${time}</td>
                            <td><strong>${ev.service}</strong></td>
                            <td><span class="tag-status status-${st}">HTTP ${st}</span></td>
                            <td>${paidTag}</td>
                            <td>${val}</td>
                            <td>${tx}</td>
                            <td style="color: var(--text-muted);">${ev.ip_masked}</td>
                        </tr>`;
                    });
                    tbody.innerHTML = rowsHtml;
                }

                document.getElementById('last-updated-text').textContent = 'Última atualização: ' + new Date().toLocaleTimeString('pt-BR');
            } catch (e) {
                console.error('Erro ao buscar stats em tempo real:', e);
            }
        }

        // Inicia polling a cada 5 segundos
        setInterval(fetchLiveStats, 5000);
    </script>
</body>
</html>

