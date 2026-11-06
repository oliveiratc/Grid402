<?php
/**
 * Grid402 - Oráculo M2M de Telemetria e Status de Serviços Críticos (Protocolo x402)
 * 
 * Gateway principal:
 * - text/html: Landing Page dark-mode ultraleve para humanos
 * - application/json: Resumo gratuito ou telemetria detalhada via x402
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/x402.php';

// Leitura de cabeçalhos de requisição
$acceptHeader = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isJsonExplicit = isset($_GET['format']) && $_GET['format'] === 'json';
$isDetailRequested = isset($_GET['detail']) || str_contains($requestUri, 'telemetry');

// Extração do cabeçalho de pagamento X-Payment
$paymentProof = (string) ($_SERVER['HTTP_X_PAYMENT'] ?? '');
if ($paymentProof === '' && function_exists('apache_request_headers')) {
    $reqHeaders = apache_request_headers();
    $paymentProof = (string) ($reqHeaders['X-Payment'] ?? $reqHeaders['x-payment'] ?? '');
}
if ($paymentProof === '') {
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (str_starts_with($authHeader, 'x402 ')) {
        $paymentProof = substr($authHeader, 5);
    }
}

// 1. Decisão de Rota: Máquina (JSON) vs Humano (HTML)
$prefersHtml = str_contains($acceptHeader, 'text/html') && !$isJsonExplicit && !str_starts_with($userAgent, 'curl/') && !str_starts_with($userAgent, 'Wget/');

// Se for requisição de máquina (JSON ou rota de detalhe/telemetria)
if (!$prefersHtml || $isDetailRequested) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Payment');
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Payment-Status, X-Payment-Amount');

    // Se NÃO pediu detalhes: Entrega gratuita imediata
    if (!$isDetailRequested) {
        if (!file_exists(GRID402_FILE_FREE)) {
            // Se o coletor ainda não rodou, executa inline silenciosamente
            include_once __DIR__ . '/collector.php';
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=30');
        readfile(GRID402_FILE_FREE);
        exit;
    }

    // Se PEDIU detalhes: Fluxo x402
    if ($paymentProof === '') {
        grid402_respond_402();
    }

    // Validação on-chain do pagamento
    $validation = grid402_validate_payment($paymentProof);

    if (!$validation['valid']) {
        http_response_code($validation['code']);
        header('Content-Type: application/json; charset=utf-8');
        if ($validation['code'] === 402 && php_sapi_name() !== 'cli-server') {
            header('WWW-Authenticate: x402');
        }
        echo json_encode([
            'error' => ($validation['code'] === 403) ? 'ReplayAttackDetected' : 'PaymentFailed',
            'message' => $validation['reason'],
            'tx_hash' => $paymentProof,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Pagamento validado com sucesso!
    if (!file_exists(GRID402_FILE_PAID)) {
        include_once __DIR__ . '/collector.php';
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Payment-Status: Accepted');
    header('X-Payment-Amount: ' . GRID402_PRICE_DISPLAY . ' USDC');
    header('X-Payment-Network: Base (ChainID 8453)');
    header('Cache-Control: private, no-cache');
    readfile(GRID402_FILE_PAID);
    exit;
}

// 2. Renderização da Landing Page Dark-Mode para Navegadores Humanos
$freeData = file_exists(GRID402_FILE_FREE) ? json_decode((string)file_get_contents(GRID402_FILE_FREE), true) : null;
$systemStatus = $freeData['system_status'] ?? 'UP';
$healthyCount = $freeData['healthy_targets'] ?? 18;
$totalCount = $freeData['total_targets'] ?? 18;
$execDuration = $freeData['execution_time_ms'] ?? 420;
$categories = $freeData['categories'] ?? [];
$lastCheck = isset($freeData['timestamp']) ? date('d/m/Y H:i:s', strtotime($freeData['timestamp'])) : 'Atualizado agora';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid402 // Oráculo M2M de Telemetria de Serviços Críticos</title>
    <meta name="description" content="Oráculo de telemetria em tempo real para agentes de IA e sistemas M2M checarem disponibilidade de SEFAZ, PIX e Gateways. Protocolo HTTP x402 na Base.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090D14;
            --surface: #111726;
            --surface-hover: #162035;
            --border: #1E293B;
            --border-glow: rgba(16, 185, 129, 0.2);
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
            line-height: 1.6;
            padding: 0 1.5rem;
            min-height: 100vh;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 3rem 0;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid var(--border);
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .logo-badge {
            background: linear-gradient(135deg, #10B981, #06B6D4);
            color: #000;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            letter-spacing: -0.5px;
        }
        .logo-title {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-glow);
            color: var(--primary);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-pulse {
            width: 8px;
            height: 8px;
            background-color: var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--primary);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }
        .hero {
            margin: 3.5rem 0;
            text-align: center;
        }
        .hero-tag {
            color: var(--accent);
            font-family: var(--font-mono);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .hero h1 {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -1px;
            margin-bottom: 1.25rem;
            background: linear-gradient(180deg, #FFFFFF 0%, #CBD5E1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            color: var(--text-muted);
            font-size: 1.15rem;
            max-width: 680px;
            margin: 0 auto 2rem;
        }
        .cta-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background-color: var(--primary);
            color: #052e16;
        }
        .btn-primary:hover {
            background-color: #059669;
            transform: translateY(-1px);
        }
        .btn-outline {
            background-color: transparent;
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-outline:hover {
            background-color: var(--surface);
            border-color: var(--text-muted);
        }
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 3.5rem;
        }
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: border-color 0.2s ease;
        }
        .card:hover {
            border-color: #334155;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .card-status {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        .status-up { background: rgba(16, 185, 129, 0.15); color: var(--primary); }
        .status-deg { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .status-down { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .card-latency {
            font-family: var(--font-mono);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .card-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .protocol-box {
            background: linear-gradient(180deg, #111827 0%, #0F172A 100%);
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 2rem;
            margin-bottom: 3.5rem;
        }
        .protocol-box h2 {
            font-size: 1.35rem;
            margin-bottom: 0.5rem;
        }
        .protocol-box p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .code-block {
            background-color: #030712;
            border: 1px solid #1E293B;
            border-radius: 8px;
            padding: 1.25rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: #38BDF8;
            overflow-x: auto;
            line-height: 1.5;
        }
        footer {
            border-top: 1px solid var(--border);
            padding-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 1rem;
        }
        footer a { color: var(--accent); text-decoration: none; }
        footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-wrap">
                <span class="logo-badge">x402</span>
                <span class="logo-title">Grid402</span>
            </div>
            <div class="badge-live">
                <span class="badge-pulse"></span>
                <span>Base Mainnet (8453) • <?php echo htmlspecialchars($systemStatus); ?></span>
            </div>
        </header>

        <section class="hero">
            <div class="hero-tag">Infraestrutura M2M & Agentes de IA</div>
            <h1>Oráculo de Telemetria de Alta Frequência</h1>
            <p>Monitore a disponibilidade e latência exata de APIs críticas brasileiras (SEFAZ, PIX, Gateways e Bancos) antes de processar lotes de transações. Micropagamentos nativos em USDC na Base.</p>
            <div class="cta-group">
                <a href="/telemetry" class="btn btn-primary">Testar Endpoint x402</a>
                <a href="/llms.txt" class="btn btn-outline">Documentação llms.txt</a>
                <a href="/status?format=json" class="btn btn-outline">Resumo Gratuito (JSON)</a>
            </div>
        </section>

        <div class="grid-stats">
            <?php foreach ($categories as $catName => $stats): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><?php echo htmlspecialchars((string)$catName); ?></span>
                    <span class="card-status <?php echo ($stats['status'] === 'UP') ? 'status-up' : (($stats['status'] === 'DEGRADED') ? 'status-deg' : 'status-down'); ?>">
                        <?php echo htmlspecialchars((string)$stats['status']); ?>
                    </span>
                </div>
                <div class="card-latency"><?php echo number_format((float)$stats['avg_latency_ms'], 1); ?><span style="font-size: 1rem; color: var(--text-muted); font-weight: normal;"> ms</span></div>
                <div class="card-meta">
                    <?php echo (int)$stats['targets_up']; ?> / <?php echo (int)$stats['targets_total']; ?> endpoints operantes
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <section class="protocol-box">
            <h2>Como Consumir via Agentes Autônomos (HTTP x402)</h2>
            <p>Faça uma consulta para obter o desafio HTTP 402, liquide $0.0005 em USDC na Base e envie o hash da transação no cabeçalho <code>X-Payment</code>.</p>
            <div class="code-block">
# 1. Solicite a telemetria detalhada (Recebe HTTP 402 Payment Required)
curl -i https://www.grid402.com.br/telemetry \
  -H "Accept: application/json"

# Resposta: HTTP 402 Payment Required
# WWW-Authenticate: x402
# { "recipient_wallet": "<?php echo GRID402_WALLET_ADDRESS; ?>", "price_usdc": "0.0005", "chain_id": 8453 }

# 2. Reenvie o comprovante on-chain (Recebe HTTP 200 com payload detalhado)
curl https://www.grid402.com.br/telemetry \
  -H "Accept: application/json" \
  -H "X-Payment: 0x&lt;tx_hash_da_transferencia_na_base&gt;"
            </div>
        </section>

        <footer>
            <div>Grid402 Telemetry Oracle • Hospedado sobre Hostinger LiteSpeed</div>
            <div>
                <a href="/llms.txt">llms.txt</a> • 
                <a href="https://basescan.org/address/<?php echo GRID402_WALLET_ADDRESS; ?>" target="_blank" rel="noopener">BaseScan</a> •
                <span>Taxa: US$ <?php echo GRID402_PRICE_DISPLAY; ?> / consulta</span>
            </div>
        </footer>
    </div>
</body>
</html>
