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

/**
 * Consulta os saldos on-chain em USDC e ETH da carteira oficial na Base Mainnet com cache de 60s
 */
function grid402_get_oracle_wallet_balances(): array {
    $cacheFile = __DIR__ . '/private_data/wallet_balance_cache.json';
    $now = time();
    $cacheTtl = 60; // 60 segundos de cache

    if (file_exists($cacheFile)) {
        $cacheRaw = @file_get_contents($cacheFile);
        $cached = $cacheRaw ? @json_decode($cacheRaw, true) : null;
        if (is_array($cached) && isset($cached['cached_at']) && ($now - (int)$cached['cached_at']) < $cacheTtl) {
            return $cached;
        }
    }

    $oracleAddress = defined('GRID402_WALLET_ADDRESS') ? GRID402_WALLET_ADDRESS : '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
    $usdcContract = defined('GRID402_USDC_CONTRACT') ? GRID402_USDC_CONTRACT : '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';
    $baseRpcUrl = defined('GRID402_BASE_RPC_URL') ? GRID402_BASE_RPC_URL : 'https://mainnet.base.org';

    $rpcUrls = [
        $baseRpcUrl,
        'https://base.llamarpc.com',
        'https://1rpc.io/base',
    ];

    $usdcBalance = 0.0;
    $ethBalance = 0.0;
    $rpcOk = false;

    // Helper interno para executar chamadas JSON-RPC
    $execRpc = function (string $method, array $params) use ($rpcUrls): mixed {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);

        foreach ($rpcUrls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT => 'Grid402-Admin-Probe/2.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && is_string($response)) {
                $json = @json_decode($response, true);
                if (isset($json['result'])) {
                    return $json['result'];
                }
            }
        }

        return null;
    };

    try {
        // 1. Saldo USDC (ERC-20 balanceOf)
        $cleanAddr = str_starts_with($oracleAddress, '0x') ? substr($oracleAddress, 2) : $oracleAddress;
        $callData = '0x70a08231' . str_pad($cleanAddr, 64, '0', STR_PAD_LEFT);
        $resUsdc = $execRpc('eth_call', [
            [
                'to' => $usdcContract,
                'data' => $callData,
            ],
            'latest'
        ]);

        if (is_string($resUsdc) && str_starts_with($resUsdc, '0x')) {
            $hexVal = substr($resUsdc, 2);
            $rawInt = hexdec($hexVal);
            $usdcBalance = round($rawInt / 1000000, 4); // USDC tem 6 decimais
            $rpcOk = true;
        }

        // 2. Saldo ETH (eth_getBalance para reserva de gas)
        $resEth = $execRpc('eth_getBalance', [
            $oracleAddress,
            'latest'
        ]);

        if (is_string($resEth) && str_starts_with($resEth, '0x')) {
            $hexEth = substr($resEth, 2);
            $rawEth = hexdec($hexEth);
            $ethBalance = round($rawEth / 1e18, 5); // ETH tem 18 decimais
            $rpcOk = true;
        }
    } catch (\Throwable $e) {
        if (file_exists($cacheFile)) {
            $cacheRaw = @file_get_contents($cacheFile);
            $cached = $cacheRaw ? @json_decode($cacheRaw, true) : null;
            if (is_array($cached)) {
                return $cached;
            }
        }
    }

    $result = [
        'oracle_address' => $oracleAddress,
        'usdc_balance' => $usdcBalance,
        'eth_balance' => $ethBalance,
        'network' => 'Base Mainnet',
        'chain_id' => 8453,
        'cached_at' => $now,
        'rpc_operational' => $rpcOk,
    ];

    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($cacheFile, (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $result;
}

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

if ($action === 'reset') {
    if (function_exists('grid402_is_default_secret') and grid402_is_default_secret()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'InsecureResetBlocked',
            'message' => 'O reset administrativo foi bloqueado. Defina uma chave forte para GRID402_CRON_SECRET nas variaveis de ambiente antes de usar esta operacao.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $providedSecret = (string)($_GET['secret'] ?? '');
    $isConfirmed = (isset($_GET['confirm']) and $_GET['confirm'] === 'true');

    if ($providedSecret !== '' and hash_equals(GRID402_CRON_SECRET, $providedSecret) and $isConfirmed) {
        if (file_exists($authFile)) {
            @unlink($authFile);
        }
        $_SESSION = [];
        session_destroy();
        header('Location: /admin?reset=done');
        exit;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Forbidden',
        'message' => 'Segredo de reset incorreto ou confirmacao ausente (exija confirm=true).'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 2.1. Controle Anti-CSRF
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['admin_csrf_token'];

function grid402_validate_csrf(?string $token): bool {
    $sessionToken = (string)($_SESSION['admin_csrf_token'] ?? '');
    if ($sessionToken === '' or empty($token)) {
        return false;
    }
    return hash_equals($sessionToken, (string)$token);
}

/**
 * Controle de Forca Bruta de Login (Zero-DB)
 * Trava o IP por 15 minutos (900s) apos 5 falhas consecutivas
 */
function grid402_get_login_rate_file(): string {
    $dir = defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR : (__DIR__ . '/private_data');
    return $dir . '/admin_login_rate.json';
}

function grid402_check_login_rate(string $ip): array {
    $file = grid402_get_login_rate_file();
    $maxAttempts = 5;
    $lockoutSeconds = 900;
    $now = time();

    if (!file_exists($file)) {
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }

    $raw = @file_get_contents($file);
    $data = $raw ? (@json_decode($raw, true) ?: []) : [];
    $entry = $data[$ip] ?? null;

    if (!$entry) {
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }

    $firstFail = (int)($entry['first_fail'] ?? $now);
    $attempts = (int)($entry['attempts'] ?? 0);
    $elapsed = $now - $firstFail;

    if ($elapsed >= $lockoutSeconds) {
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }

    if ($attempts >= $maxAttempts) {
        $retryAfter = max(1, $lockoutSeconds - $elapsed);
        return [
            'allowed' => false,
            'remaining' => 0,
            'retry_after' => $retryAfter,
            'attempts' => $attempts,
        ];
    }

    return [
        'allowed' => true,
        'remaining' => max(0, $maxAttempts - $attempts),
        'retry_after' => 0,
        'attempts' => $attempts,
    ];
}

function grid402_record_login_failure(string $ip): void {
    $file = grid402_get_login_rate_file();
    $lockoutSeconds = 900;
    $now = time();

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($file);
        $content = ($filesize > 0) ? (string) fread($fp, $filesize) : '';
        $data = $content !== '' ? (@json_decode($content, true) ?: []) : [];

        $cleaned = [];
        foreach ($data as $kIp => $v) {
            $t = (int)($v['first_fail'] ?? 0);
            if (($now - $t) < $lockoutSeconds) {
                $cleaned[$kIp] = $v;
            }
        }

        $entry = $cleaned[$ip] ?? ['attempts' => 0, 'first_fail' => $now];
        if (($now - (int)$entry['first_fail']) >= $lockoutSeconds) {
            $entry = ['attempts' => 0, 'first_fail' => $now];
        }

        $entry['attempts'] = ((int)$entry['attempts']) + 1;
        $entry['last_fail'] = $now;
        $cleaned[$ip] = $entry;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function grid402_reset_login_rate(string $ip): void {
    $file = grid402_get_login_rate_file();
    if (!file_exists($file)) {
        return;
    }
    $fp = fopen($file, 'c+');
    if (!$fp) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($file);
        $content = ($filesize > 0) ? (string) fread($fp, $filesize) : '';
        $data = $content !== '' ? (@json_decode($content, true) ?: []) : [];
        if (isset($data[$ip])) {
            unset($data[$ip]);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// 3. Processamento de POST (Setup, Login ou Alteracao de Senha)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    // 3.0. Alteracao de Senha Autenticada
    if ($action === 'change_password') {
        $isJsonReq = ((isset($_GET['format']) and $_GET['format'] === 'json') or
                      (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')));

        if (!$isLoggedIn) {
            http_response_code(401);
            if ($isJsonReq) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Sessao administrativa expirada ou nao autenticada.']);
            } else {
                header('Location: /admin');
            }
            exit;
        }

        if (!grid402_validate_csrf($submittedCsrf)) {
            http_response_code(403);
            if ($isJsonReq) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Token CSRF de seguranca ausente ou invalido. Recarregue a pagina.']);
            } else {
                $errorMsg = 'Token CSRF de seguranca invalido ou expirado.';
            }
            exit;
        }

        $currPass = (string) ($_POST['current_password'] ?? '');
        $newPass = (string) ($_POST['new_password'] ?? '');
        $newPassConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        $authContent = @file_get_contents($authFile);
        $authData = $authContent ? @json_decode($authContent, true) : null;

        $pErr = '';
        if (!$authData or !password_verify($currPass, (string)($authData['password_hash'] ?? ''))) {
            $pErr = 'A senha atual informada esta incorreta.';
        } elseif (strlen($newPass) < 8) {
            $pErr = 'A nova senha deve ter no minimo 8 caracteres.';
        } elseif ($newPass !== $newPassConfirm) {
            $pErr = 'A nova senha e a confirmacao nao coincidem.';
        }

        if ($pErr !== '') {
            if ($isJsonReq) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $pErr]);
                exit;
            } else {
                $errorMsg = $pErr;
            }
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 11]);
            $authData['password_hash'] = $newHash;
            $authData['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
            grid402_atomic_write($authFile, $authData);

            if ($isJsonReq) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);
                exit;
            } else {
                $successMsg = 'Senha alterada com sucesso!';
            }
        }
    } elseif (!$hasSetup) {
        if (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de seguranca invalido ou expirado. Recarregue a pagina.';
        } else {
            // 3.1. Setup de Primeiro Acesso
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

                if (!$saved or !file_exists($authFile)) {
                    $errorMsg = 'Erro crítico: não foi possível gravar as credenciais no disco. Verifique permissões do diretório de armazenamento.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user'] = $user;
                    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    header('Location: /admin?setup=success');
                    exit;
                }
            }
        }
    } else {
        // 3.2. Login Normal com Protecao Anti-Brute-Force
        $clientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $rateCheck = grid402_check_login_rate($clientIp);

        if (!$rateCheck['allowed']) {
            http_response_code(429);
            $waitMinutes = max(1, (int) ceil(($rateCheck['retry_after'] ?? 900) / 60));
            $errorMsg = "Muitas tentativas incorretas. Acesso bloqueado por seguranca por {$waitMinutes} minuto(s).";
        } elseif (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de seguranca invalido ou expirado. Recarregue a pagina.';
        } else {
            $user = trim((string) ($_POST['username'] ?? ''));
            $pass = (string) ($_POST['password'] ?? '');

            $authContent = @file_get_contents($authFile);
            $authData = $authContent ? @json_decode($authContent, true) : null;

            if ($authData and $user === ($authData['username'] ?? '') and password_verify($pass, (string)($authData['password_hash'] ?? ''))) {
                grid402_reset_login_rate($clientIp);
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $authData['username'];
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));

                $authData['last_login'] = gmdate('Y-m-d\TH:i:s\Z');
                $authData['login_count'] = ((int)($authData['login_count'] ?? 0)) + 1;
                grid402_atomic_write($authFile, $authData);

                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                header('Location: /admin');
                exit;
            } else {
                grid402_record_login_failure($clientIp);
                usleep(350000); // Delay defensivo anti-bruteforce
                $rem = max(0, ($rateCheck['remaining'] ?? 5) - 1);
                $errorMsg = ($rem > 0)
                    ? "Usuário ou senha incorretos. ({$rem} tentativa(s) restante(s) antes do bloqueio)."
                    : "Usuário ou senha incorretos. Acesso bloqueado por segurança por 15 minutos.";
            }
        }
    }
}

// 4. Endpoints de API JSON e Exportacao CSV
// Mapas canonicos de origem de pagamento e categoria de falha (usados no CSV, no JSON e no painel)
$paymentSourceLabels = [
    'tx_hash' => 'TX hash (legado)',
    'pass' => 'Session pass',
    'facilitator' => 'Facilitador (settle)',
    'facilitator_verify_only' => 'Facilitador (verify only)',
    'sandbox' => 'Sandbox',
    'unknown' => 'Não identificada',
];
$failureCategoryLabels = [
    'invalid_proof' => 'Prova inválida',
    'payment_rejected' => 'Pagamento rejeitado',
    'authorization_expired' => 'Autorização expirada',
    'replay' => 'Replay (já usado)',
    'facilitator_unavailable' => 'Facilitador indisponível',
    'facilitator_disabled' => 'Facilitador desativado',
    'unknown' => 'Outros',
];
$paymentSourceKeys = array_keys($paymentSourceLabels);
$failureCategoryKeys = array_keys($failureCategoryLabels);

// 4.1. Endpoint de Exportacao Financeira CSV (/admin?api=export_csv)
if ($apiParam === 'export_csv') {
    if (!$isLoggedIn) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Sessão administrativa expirada ou inválida.'], JSON_PRETTY_PRINT);
        exit;
    }

    $metrics = grid402_get_metrics();
    $forex = grid402_service_forex();
    $usdRate = (float)($forex['rates']['USD']['bid'] ?? 5.50);
    $events = $metrics['recent_events'] ?? [];

    $filename = 'grid402_telemetria_' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    // UTF-8 BOM para abertura correta no Microsoft Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, [
            'Horario UTC',
            'Servico',
            'Status HTTP',
            'Tipo',
            'Valor USDC',
            'Cotacao PTAX BRL',
            'Valor BRL Estimado',
            'Hash BaseScan',
            'Origem do Pagamento',
            'Falha',
            'Hash Liquidacao',
            'IP Mascarado'
        ], ';');

        foreach ($events as $ev) {
            $priceUsdc = (float)($ev['price_usdc'] ?? 0.0);
            $priceBrl = round($priceUsdc * $usdRate, 4);
            $evType = $ev['type'] ?? ($ev['is_paid'] ? 'paid' : 'free');

            fputcsv($output, [
                $ev['timestamp'] ?? '',
                $ev['service'] ?? '',
                $ev['status'] ?? 200,
                $evType,
                number_format($priceUsdc, 4, '.', ''),
                number_format($usdRate, 4, '.', ''),
                number_format($priceBrl, 2, ',', '.'),
                $ev['tx_hash'] ?? '',
                $ev['payment_source'] ?? '',
                $ev['failure_category'] ?? '',
                $ev['settlement_tx'] ?? '',
                $ev['ip_masked'] ?? ''
            ], ';');
        }

        fclose($output);
    }
    exit;
}

// 4.2. Endpoint de Polling JSON (/admin?api=stats)
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
    $wallet = grid402_get_oracle_wallet_balances();
    $oracleConfigured = defined('GRID402_ORACLE_PRIVATE_KEY') && !empty(GRID402_ORACLE_PRIVATE_KEY);
    $analytics = grid402_compute_admin_analytics($metrics, $usdRate);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $revenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
    $today = gmdate('Y-m-d');
    $todayUsdc = (float)($metrics['daily'][$today]['usdc'] ?? 0.0);

    $paymentSources = [];
    $rawSources = is_array($metrics['payment_sources'] ?? null) ? $metrics['payment_sources'] : [];
    foreach ($paymentSourceKeys as $key) {
        $paymentSources[$key] = (int)($rawSources[$key] ?? 0);
    }

    $failureCategories = [];
    $rawFailures = is_array($metrics['failure_categories'] ?? null) ? $metrics['failure_categories'] : [];
    foreach ($failureCategoryKeys as $key) {
        $failureCategories[$key] = (int)($rawFailures[$key] ?? 0);
    }

    echo json_encode([
        'status' => 'success',
        'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
        'total_requests' => (int)($metrics['total_requests'] ?? 0),
        'total_paid_requests' => (int)($metrics['total_paid_requests'] ?? 0),
        'total_sandbox_requests' => (int)($metrics['total_sandbox_requests'] ?? 0),
        'total_revenue_usdc' => $revenueUsdc,
        'total_revenue_brl' => round($revenueUsdc * $usdRate, 2),
        'today_revenue_usdc' => $todayUsdc,
        'today_revenue_brl' => round($todayUsdc * $usdRate, 2),
        'arpu_usdc' => $analytics['arpu_usdc'],
        'arpu_brl' => $analytics['arpu_brl'],
        'technical_success_rate' => $analytics['technical_success_rate'],
        'sandbox_rate' => $analytics['sandbox_rate'],
        'usd_rate' => $usdRate,
        'wallet_balances' => $wallet,
        'passes_stats' => $analytics['passes_stats'] ?? [],
        'oracle_configured' => $oracleConfigured,
        'status_counts' => $metrics['status_counts'] ?? [],
        'service_counts' => $metrics['service_counts'] ?? [],
        'payment_sources' => $paymentSources,
        'failure_categories' => $failureCategories,
        'payment_source_labels' => $paymentSourceLabels,
        'failure_category_labels' => $failureCategoryLabels,
        'daily_history_14d' => $analytics['daily_history_14d'] ?? [],
        'top_consumers' => $analytics['top_consumers'] ?? [],
        'service_revenue' => $analytics['service_revenue'] ?? [],
        'recent_events' => $metrics['recent_events'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4.3. Endpoint de Vendas e MRR em BRL (Alavanca 2)
if ($apiParam === 'sales') {
    if (!$isLoggedIn) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized'], JSON_PRETTY_PRINT);
        exit;
    }
    require_once __DIR__ . '/services/sales.php';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode([
        'status' => 'success',
        'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
        'mrr' => grid402_sales_mrr(),
        'plans' => grid402_plans(),
        'recent_sales' => grid402_sales_list(50),
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
        <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <title>Grid402 // Acesso Administrativo</title>
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg: #060B19;
                --surface: #0B132B;
                --border: #1E2C4F;
                --text-main: #F8FAFC;
                --text-muted: #94A3B8;
                --primary: #0052FF;
                --accent: #00F0FF;
                --danger: #EF4444;
                --font-heading: 'Space Grotesk', system-ui, sans-serif;
                --font-sans: 'Inter', system-ui, sans-serif;
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
                gap: 0.85rem;
                margin-bottom: 2rem;
            }
            .brand-badge {
                background: linear-gradient(135deg, #00F0FF, #0052FF);
                color: #FFFFFF;
                font-family: var(--font-mono);
                font-weight: 800;
                font-size: 0.8rem;
                padding: 0.35rem 0.65rem;
                border-radius: 6px;
            }
            .brand-name { font-family: var(--font-heading); font-size: 1.35rem; font-weight: 700; letter-spacing: -0.5px; }
            h1 { font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: -0.5px; }
            p.sub { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.75rem; }
            .form-group { margin-bottom: 1.25rem; }
            label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
            input[type="text"], input[type="password"] {
                width: 100%;
                background: #060B19;
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
                color: #FFFFFF;
                font-family: var(--font-heading);
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
                background: rgba(0, 240, 255, 0.12);
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
                <img src="/assets/logo-icon-transparent.png" alt="Grid402" width="36" height="36">
                <div>
                    <div class="brand-name">Grid<span style="color: var(--accent);">402</span></div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">Acesso Administrativo</div>
                </div>
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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
$wallet = grid402_get_oracle_wallet_balances();
$oracleConfigured = defined('GRID402_ORACLE_PRIVATE_KEY') && !empty(GRID402_ORACLE_PRIVATE_KEY);
$analytics = grid402_compute_admin_analytics($metrics, $usdRate);

$revenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
$revenueBrl = round($revenueUsdc * $usdRate, 2);

$today = gmdate('Y-m-d');
$todayUsdc = (float)($metrics['daily'][$today]['usdc'] ?? 0.0);
$todayBrl = round($todayUsdc * $usdRate, 2);

$arpuUsdc = $analytics['arpu_usdc'];
$arpuBrl = $analytics['arpu_brl'];
$technicalSuccessRate = $analytics['technical_success_rate'];
$sandboxRate = $analytics['sandbox_rate'];
$passesStats = $analytics['passes_stats'] ?? ['active_passes' => 0, 'remaining_credits' => 0];
$dailyHistory14d = $analytics['daily_history_14d'] ?? [];
$topConsumers = $analytics['top_consumers'] ?? [];
$serviceRevenue = $analytics['service_revenue'] ?? [];

$totalReqs = (int)($metrics['total_requests'] ?? 0);
$paidReqs = (int)($metrics['total_paid_requests'] ?? 0);
$sandboxReqs = (int)($metrics['total_sandbox_requests'] ?? 0);
$reqs402 = (int)($metrics['status_counts']['402'] ?? 0);
$conversionRate = ($reqs402 + $paidReqs > 0) ? round(($paidReqs / ($reqs402 + $paidReqs)) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Grid402 // Painel de Telemetria e Faturamento</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #060B19;
            --surface: #0B132B;
            --surface-hover: #111C38;
            --border: #1E2C4F;
            --border-glow: rgba(0, 240, 255, 0.25);
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --primary: #0052FF;
            --primary-glow: rgba(0, 82, 255, 0.25);
            --secondary: #00F0FF;
            --accent: #00F0FF;
            --accent-glow: rgba(0, 240, 255, 0.2);
            --warning: #F59E0B;
            --danger: #EF4444;
            --font-heading: 'Space Grotesk', system-ui, sans-serif;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-sans);
            padding: 0 1.5rem;
            min-height: 100vh;
            overflow-x: hidden;
        }
        h1, h2, h3, h4 { font-family: var(--font-heading); letter-spacing: -0.5px; }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 0 4rem;
            width: 100%;
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
            background: linear-gradient(135deg, #00F0FF, #0052FF);
            color: #FFFFFF;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }
        .logo-title { font-family: var(--font-heading); font-size: 1.4rem; font-weight: 700; letter-spacing: -0.5px; }
        .user-nav { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; flex-wrap: wrap; }
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(0, 240, 255, 0.08);
            border: 1px solid rgba(0, 240, 255, 0.3);
            color: var(--accent);
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
            background-color: var(--accent);
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
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-outline:hover { background: var(--surface); border-color: var(--accent); }

        /* KPI Cards Grid (5 colunas responsivas) */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* Gráfico Temporal SVG 14 Dias */
        .chart-wrap {
            position: relative;
            width: 100%;
            height: 220px;
            margin-top: 0.5rem;
        }
        .chart-svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }
        .chart-tooltip {
            position: absolute;
            display: none;
            background: #0B132B;
            border: 1px solid var(--accent);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            color: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
            pointer-events: none;
            z-index: 50;
            white-space: nowrap;
            transition: opacity 0.15s ease;
        }
        .chart-grid-line {
            stroke: rgba(255, 255, 255, 0.06);
            stroke-dasharray: 3,3;
        }
        .chart-axis-text {
            fill: var(--text-muted);
            font-size: 10px;
            font-family: var(--font-mono);
            text-anchor: middle;
        }
        .chart-bar-rect {
            fill: url(#chartGradient);
            transition: height 0.3s ease, y 0.3s ease, opacity 0.2s ease;
            cursor: pointer;
        }
        .chart-bar-rect:hover {
            opacity: 0.85;
            filter: brightness(1.2);
        }
        .chart-bar-paid-rect {
            fill: #10B981;
            transition: height 0.3s ease, y 0.3s ease;
        }
        .tag-profile {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.68rem;
            font-weight: 600;
            font-family: var(--font-mono);
        }
        .profile-paid {
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .profile-sandbox {
            background: rgba(168, 85, 247, 0.15);
            color: #C084FC;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }
        .profile-agent {
            background: rgba(56, 189, 248, 0.15);
            color: #38BDF8;
            border: 1px solid rgba(56, 189, 248, 0.3);
        }
        .profile-402 {
            background: rgba(245, 158, 11, 0.15);
            color: #FBBF24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        .rank-badge.top-1 { background: rgba(245, 158, 11, 0.2); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.4); }
        .rank-badge.top-2 { background: rgba(203, 213, 225, 0.2); color: #CBD5E1; border: 1px solid rgba(203, 213, 225, 0.4); }
        .rank-badge.top-3 { background: rgba(180, 83, 9, 0.2); color: #D97706; border: 1px solid rgba(180, 83, 9, 0.4); }

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
            margin-bottom: 0.65rem;
            font-size: 0.82rem;
        }
        .bar-label { width: 95px; font-family: var(--font-mono); color: var(--text-main); }
        .bar-label.wide { width: 158px; font-size: 0.74rem; line-height: 1.2; }
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

        /* Barra de Ferramentas e Filtros Reativos do Feed */
        .feed-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
            background: rgba(3, 7, 18, 0.6);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
        }
        .filter-group {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .filter-btn:hover {
            color: #fff;
            border-color: var(--accent);
        }
        .filter-btn.active {
            background: rgba(56, 189, 248, 0.15);
            border-color: var(--accent);
            color: #fff;
        }
        .search-wrap {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 240px;
            justify-content: flex-end;
        }
        .search-input {
            background: #030712;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: #fff;
            font-size: 0.8rem;
            padding: 0.4rem 0.65rem;
            font-family: var(--font-sans);
            width: 100%;
            max-width: 280px;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* Tabela */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #030712;
            width: 100%;
        }
        table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
            font-size: 0.82rem;
            text-align: left;
        }
        th, td {
            white-space: nowrap;
        }
        th {
            background: rgba(255,255,255,0.02);
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
        }
        td {
            padding: 0.75rem 1rem;
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
        .status-sandbox { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .tag-paid {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .tag-source {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 600;
            font-family: var(--font-mono);
            background: rgba(56, 189, 248, 0.12);
            color: #7DD3FC;
            white-space: nowrap;
        }
        .tag-failure {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 600;
            font-family: var(--font-mono);
            background: rgba(239, 68, 68, 0.14);
            color: #FCA5A5;
            white-space: nowrap;
        }
        .tx-link { color: var(--accent); text-decoration: none; }
        .tx-link:hover { text-decoration: underline; }

        /* Modal de Alteracao de Senha */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(6px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .modal-title { font-size: 1.1rem; font-weight: 700; color: #fff; }
        .modal-close {
            background: none; border: none; font-size: 1.25rem; color: var(--text-muted);
            cursor: pointer; padding: 0.25rem;
        }
        .modal-close:hover { color: #fff; }

        /* Regras de Responsividade Fluida para o Admin */
        @media (max-width: 860px) {
            header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
                padding-bottom: 1.5rem;
                margin-bottom: 1.75rem;
            }
            .logo-wrap {
                justify-content: space-between;
                width: 100%;
            }
            .user-nav {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .btn-outline {
                flex: 1 1 auto;
                justify-content: center;
                text-align: center;
                font-size: 0.75rem;
                padding: 0.45rem 0.65rem;
            }
            .analytics-row {
                grid-template-columns: 1fr;
                gap: 1.25rem;
                margin-bottom: 1.75rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0 0.85rem;
            }
            .container {
                padding: 1.5rem 0 3rem;
            }
            .kpi-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.85rem;
                margin-bottom: 1.75rem;
            }
            .feed-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            .filter-group {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
            .filter-btn {
                flex: 1 1 auto;
                text-align: center;
                padding: 0.4rem 0.5rem;
                font-size: 0.72rem;
            }
            .search-wrap {
                width: 100%;
                min-width: 0;
                max-width: 100%;
            }
            .search-input {
                width: 100%;
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 0 0.6rem;
            }
            .kpi-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .kpi-card {
                padding: 1rem 1.15rem;
            }
            .kpi-value {
                font-size: 1.55rem;
            }
            .panel {
                padding: 1rem;
            }
            .bar-row {
                font-size: 0.75rem;
            }
            .bar-label {
                width: 75px;
            }
            .bar-label.wide {
                width: 96px;
                font-size: 0.68rem;
                line-height: 1.15;
            }
            .bar-track {
                margin: 0 0.5rem;
                height: 6px;
            }
            .bar-count {
                width: 40px;
            }
            .modal-card {
                margin: 0.75rem;
                max-height: 90vh;
            }
            .modal-header {
                padding: 1rem;
            }
        }

        @media (max-width: 360px) {
            .logo-title {
                font-size: 1.15rem;
            }
            .filter-btn {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-wrap">
                <a href="/" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit;">
                    <img src="/assets/logo-icon-transparent.png" alt="Grid402" width="36" height="36" style="display: block;">
                    <span class="logo-title">Grid<span style="color: var(--accent);">402</span></span>
                </a>
                <span class="logo-badge">ADMIN</span>
                <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500; margin-left: 0.5rem;">Monitoramento e Faturamento</span>
            </div>
            <div class="user-nav">
                <div class="live-indicator">
                    <span class="pulse-dot"></span>
                    <span id="live-timer-text">Ao Vivo (5s)</span>
                </div>
                <a href="/admin?api=export_csv" class="btn-outline" title="Baixar histórico completo de consultas em CSV">📥 Exportar CSV</a>
                <button type="button" class="btn-outline" onclick="openPasswordModal()">🔑 Alterar Senha</button>
                <button type="button" class="btn-outline" onclick="fetchLiveStats(true)">🔄 Atualizar</button>
                <a href="/" target="_blank" class="btn-outline">🌐 Ver Portal</a>
                <a href="/admin?action=logout" class="btn-outline" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">Sair</a>
            </div>
        </header>

        <!-- KPI Cards Grid (8 Cards Executivos de Alta Fidelidade) -->
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
                <div class="kpi-title">Ticket Médio (ARPU)</div>
                <div class="kpi-value" id="kpi-arpu-usdc">US$ <?php echo number_format($arpuUsdc, 4); ?></div>
                <div class="kpi-sub" id="kpi-arpu-brl">≈ R$ <?php echo number_format($arpuBrl, 4, ',', '.'); ?> / consulta paga</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Consultas Totais</div>
                <div class="kpi-value" id="kpi-total-reqs"><?php echo number_format($totalReqs, 0, ',', '.'); ?></div>
                <div class="kpi-sub" id="kpi-paid-reqs"><?php echo number_format($paidReqs, 0, ',', '.'); ?> pagas • <?php echo number_format($sandboxReqs, 0, ',', '.'); ?> sandbox</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Taxa de Conversão</div>
                <div class="kpi-value" id="kpi-conversion"><?php echo $conversionRate; ?>%</div>
                <div class="kpi-sub">Pagos / Desafios 402 emitidos</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">SLA / Sucesso Técnico</div>
                <div class="kpi-value" id="kpi-sla-tech" style="color: #10B981;"><?php echo $technicalSuccessRate; ?>%</div>
                <div class="kpi-sub">HTTP 200 OK sobre total de chamadas</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Adoção Sandbox</div>
                <div class="kpi-value" id="kpi-sandbox-rate" style="color: #A855F7;"><?php echo $sandboxRate; ?>%</div>
                <div class="kpi-sub">Testes sem custo antes da conversão</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">
                    <span>Saldo On-Chain (Base)</span>
                    <span id="kpi-rpc-status" style="font-size: 0.68rem; font-family: var(--font-mono); color: var(--primary);">RPC ATIVO</span>
                </div>
                <div class="kpi-value" id="kpi-wallet-usdc">US$ <?php echo number_format((float)($wallet['usdc_balance'] ?? 0.0), 4); ?></div>
                <div class="kpi-sub" id="kpi-wallet-passes"><?php echo (int)($passesStats['active_passes'] ?? 0); ?> passes ativos • <?php echo number_format((int)($passesStats['remaining_credits'] ?? 0), 0, ',', '.'); ?> créditos</div>
            </div>
        </div>

        <!-- Linha Analítica 1: Gráfico Temporal Diário SVG (14 Dias) + Top Agentes / Consumidores -->
        <div class="analytics-row">
            <div class="panel">
                <div class="panel-title">
                    <span>Evolução Diária (Últimos 14 Dias)</span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Volume e Faturamento</span>
                </div>
                <div class="chart-wrap" id="chart-wrap-14d">
                    <div id="chart-tooltip" class="chart-tooltip"></div>
                    <svg id="chart-svg-14d" class="chart-svg" viewBox="0 0 680 200" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#00F0FF" stop-opacity="0.9"/>
                                <stop offset="100%" stop-color="#0052FF" stop-opacity="0.4"/>
                            </linearGradient>
                        </defs>
                        <!-- As barras e eixos são gerados e atualizados dinamicamente via JS -->
                    </svg>
                </div>
                <div style="display: flex; gap: 1.25rem; font-size: 0.72rem; color: var(--text-muted); margin-top: 0.75rem; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; background: linear-gradient(135deg, #00F0FF, #0052FF); border-radius: 2px;"></span>
                        <span>Consultas Totais</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; background: #10B981; border-radius: 2px;"></span>
                        <span>Consultas Pagas (x402)</span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title">
                    <span>Top Agentes / IPs Consumidores (LGPD)</span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Últimas 100 consultas</span>
                </div>
                <div class="table-wrap" style="max-height: 230px; overflow-y: auto;">
                    <table style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 32px;">#</th>
                                <th>IP Mascarado</th>
                                <th>Total</th>
                                <th>Perfil Estimado</th>
                                <th>Fav</th>
                            </tr>
                        </thead>
                        <tbody id="top-consumers-tbody">
                            <?php if (empty($topConsumers)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Aguardando eventos no buffer.</td></tr>
                            <?php else: ?>
                                <?php foreach ($topConsumers as $c): 
                                    $pCls = 'profile-agent';
                                    if ($c['paid'] > 0) $pCls = 'profile-paid';
                                    elseif ($c['sandbox'] > 0) $pCls = 'profile-sandbox';
                                    elseif ($c['challenges'] > 5) $pCls = 'profile-402';
                                    $rkCls = ($c['rank'] <= 3) ? 'top-' . $c['rank'] : '';
                                ?>
                                <tr>
                                    <td><span class="rank-badge <?php echo $rkCls; ?>"><?php echo $c['rank']; ?></span></td>
                                    <td><code style="font-family: var(--font-mono); font-size: 0.78rem;"><?php echo htmlspecialchars($c['ip_masked'], ENT_QUOTES); ?></code></td>
                                    <td><strong><?php echo $c['total']; ?></strong> <span style="font-size: 0.7rem; color: var(--text-muted);">(<?php echo $c['paid']; ?>p)</span></td>
                                    <td><span class="tag-profile <?php echo $pCls; ?>"><?php echo htmlspecialchars($c['profile'], ENT_QUOTES); ?></span></td>
                                    <td><code style="font-size: 0.72rem; color: var(--accent);"><?php echo htmlspecialchars($c['favorite_service'], ENT_QUOTES); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Linha Analítica 2: Volume por Micro-Serviço + Rentabilidade Estimada por Serviço -->
        <div class="analytics-row">
            <div class="panel">
                <div class="panel-title">Volume por Micro-Serviço (19 Nós)</div>
                <div id="service-bars-container">
                    <?php
                    $servicesList = ['lei', 'ofac', 'notarize', 'global-markets', 'pass', 'compliance', 'sanctions', 'simples', 'workdays', 'indicators', 'holidays', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'cep', 'cnpj', 'summary'];
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
                <div class="panel-title">
                    <span>Rentabilidade Estimada por Serviço (USDC)</span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Volume × Preço Oficial</span>
                </div>
                <div id="service-revenue-bars-container">
                    <?php
                    $maxRev = 0.0001;
                    foreach ($serviceRevenue as $sr) {
                        if ((float)$sr['estimated_usdc'] > $maxRev) $maxRev = (float)$sr['estimated_usdc'];
                    }
                    foreach (array_slice($serviceRevenue, 0, 10) as $sr):
                        $revVal = (float)$sr['estimated_usdc'];
                        $revPct = round(($revVal / $maxRev) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label wide"><?php echo htmlspecialchars($sr['service'], ENT_QUOTES); ?> <small style="color: var(--text-muted);">($<?php echo $sr['unit_price']; ?>)</small></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $revPct; ?>%; background: linear-gradient(90deg, #10B981, #00F0FF);"></div></div>
                        <span class="bar-count" style="width: 72px;">$<?php echo number_format($revVal, 4); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

            <div class="panel">
                <div class="panel-title">Distribuição de Status HTTP</div>
                <div id="status-bars-container">
                    <?php
                    $statusCounts = $metrics['status_counts'] ?? [];
                    $totalRequests = (int)($metrics['total_requests'] ?? 0);
                    $statusSum = array_sum(array_map('intval', $statusCounts));
                    // Garante que a soma das barras de status corresponda ao total de consultas
                    $residual = $totalRequests - $statusSum;
                    if ($residual > 0) {
                        $statusCounts['outros'] = (int)(($statusCounts['outros'] ?? 0) + $residual);
                    }
                    $totalStatus = array_sum($statusCounts) ?: 1;
                    $statuses = [
                        '200' => ['label' => '200 OK', 'color' => '#10B981'],
                        '402' => ['label' => '402 Desafio', 'color' => '#F59E0B'],
                        '403' => ['label' => '403 Replay', 'color' => '#EF4444'],
                        '400' => ['label' => '400 Prova inválida', 'color' => '#F97316'],
                        '429' => ['label' => '429 Limite sandbox', 'color' => '#A855F7'],
                    ];
                    // Inclui qualquer outro status gravado, para nenhum evento ficar fora das barras
                    foreach (array_keys($statusCounts) as $extraCode) {
                        if (!isset($statuses[(string)$extraCode])) {
                            $statuses[(string)$extraCode] = [
                                'label' => ($extraCode === 'outros' ? 'Outros' : $extraCode . ' Outros'),
                                'color' => '#64748B',
                            ];
                        }
                    }
                    foreach ($statuses as $code => $info):
                        if (!isset($statusCounts[$code]) && !in_array((string)$code, ['200', '402', '403'], true)) {
                            continue;
                        }
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

        <!-- 2 Columns: Origem dos Pagamentos e Categorias de Falha -->
        <div class="analytics-row">
            <div class="panel">
                <div class="panel-title">Origem dos Pagamentos</div>
                <div id="payment-source-bars-container">
                    <?php
                    $sourceCounts = $metrics['payment_sources'] ?? [];
                    $maxSource = max(array_values(array_map('intval', $sourceCounts)) ?: [0]);
                    if ($maxSource <= 0) $maxSource = 1;
                    $totalSource = array_sum(array_map('intval', $sourceCounts));

                    foreach ($paymentSourceLabels as $srcKey => $srcLabel):
                        $cnt = (int)($sourceCounts[$srcKey] ?? 0);
                        if ($cnt === 0 && $srcKey === 'unknown') continue;
                        $widthPct = round(($cnt / $maxSource) * 100);
                        $sharePct = $totalSource > 0 ? round(($cnt / $totalSource) * 100) : 0;
                    ?>
                    <div class="bar-row" data-source-key="<?php echo htmlspecialchars($srcKey, ENT_QUOTES); ?>">
                        <span class="bar-label wide"><?php echo htmlspecialchars($srcLabel, ENT_QUOTES); ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $widthPct; ?>%;"></div></div>
                        <span class="bar-count"><?php echo $cnt; ?> (<?php echo $sharePct; ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title">Falhas de Pagamento por Categoria</div>
                <div id="failure-bars-container">
                    <?php
                    $failureCounts = $metrics['failure_categories'] ?? [];
                    $maxFailure = max(array_values(array_map('intval', $failureCounts)) ?: [0]);
                    if ($maxFailure <= 0) $maxFailure = 1;
                    $totalFailure = array_sum(array_map('intval', $failureCounts));

                    foreach ($failureCategoryLabels as $failKey => $failLabel):
                        $cnt = (int)($failureCounts[$failKey] ?? 0);
                        if ($cnt === 0 && $failKey === 'unknown') continue;
                        $widthPct = round(($cnt / $maxFailure) * 100);
                        $sharePct = $totalFailure > 0 ? round(($cnt / $totalFailure) * 100) : 0;
                    ?>
                    <div class="bar-row" data-failure-key="<?php echo htmlspecialchars($failKey, ENT_QUOTES); ?>">
                        <span class="bar-label wide"><?php echo htmlspecialchars($failLabel, ENT_QUOTES); ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $widthPct; ?>%; background: linear-gradient(90deg, #EF4444, #F59E0B);"></div></div>
                        <span class="bar-count"><?php echo $cnt; ?> (<?php echo $sharePct; ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Feed de Consultas em Tempo Real (Buffer de 100 Eventos) -->
        <div class="panel">
            <div class="panel-title" style="flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <span>Feed de Consultas Recentes (Buffer de 100 Eventos)</span>
                    <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted); margin-left: 0.5rem;" id="last-updated-text">Atualizado há instantes</span>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <a href="/admin?api=export_csv" class="btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;">📥 Exportar CSV</a>
                </div>
            </div>

            <!-- Barra de Filtros Reativos e Busca Textual -->
            <div class="feed-toolbar">
                <div class="filter-group">
                    <button type="button" class="filter-btn active" id="filter-btn-all" onclick="setFeedFilter('all')">Todos (<span id="count-all">0</span>)</button>
                    <button type="button" class="filter-btn" id="filter-btn-paid" onclick="setFeedFilter('paid')">🟢 Pagos (<span id="count-paid">0</span>)</button>
                    <button type="button" class="filter-btn" id="filter-btn-sandbox" onclick="setFeedFilter('sandbox')">🧪 Sandbox (<span id="count-sandbox">0</span>)</button>
                    <button type="button" class="filter-btn" id="filter-btn-challenge" onclick="setFeedFilter('challenge_402')">🟡 Desafios 402 (<span id="count-challenge">0</span>)</button>
                    <button type="button" class="filter-btn" id="filter-btn-blocked" onclick="setFeedFilter('blocked_403')">🔴 Bloqueados 403 (<span id="count-blocked">0</span>)</button>
                </div>
                <div class="search-wrap">
                    <input type="text" id="feed-search-input" class="search-input" placeholder="Buscar por serviço, IP ou hash..." oninput="applyFeedSearch()">
                    <span id="feed-visible-count" style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;">Mostrando 0 de 0</span>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Horário (UTC)</th>
                            <th>Serviço</th>
                            <th>Status</th>
                            <th>Tipo</th>
                            <th>Origem / Falha</th>
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
                        <tr id="no-events-row">
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma consulta registrada até o momento.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev):
                                $evType = $ev['type'] ?? ($ev['is_paid'] ? 'paid' : 'free');
                                $st = (int)($ev['status'] ?? 200);
                            ?>
                            <tr class="event-row" data-type="<?php echo htmlspecialchars($evType, ENT_QUOTES); ?>">
                                <td><?php echo substr($ev['timestamp'] ?? '', 11, 8); ?></td>
                                <td><strong><?php echo htmlspecialchars((string)($ev['service'] ?? ''), ENT_QUOTES); ?></strong></td>
                                <td>
                                    <span class="tag-status status-<?php echo $st; ?>">HTTP <?php echo $st; ?></span>
                                </td>
                                <td>
                                    <?php if ($evType === 'paid'): ?>
                                        <span class="tag-paid">PAGO x402</span>
                                    <?php elseif ($evType === 'sandbox'): ?>
                                        <span class="tag-status status-sandbox">SANDBOX (demo)</span>
                                    <?php elseif ($evType === 'challenge_402'): ?>
                                        <span class="tag-status status-402">DESAFIO 402</span>
                                    <?php elseif ($evType === 'blocked_403'): ?>
                                        <span class="tag-status status-403">BLOQUEADO 403</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">GRATUITO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $evFailure = (string)($ev['failure_category'] ?? '');
                                    $evSource = (string)($ev['payment_source'] ?? '');
                                    if ($evFailure !== '' && $evFailure !== 'unknown') {
                                        $failLbl = $failureCategoryLabels[$evFailure] ?? $evFailure;
                                        echo '<span class="tag-failure">' . htmlspecialchars($failLbl, ENT_QUOTES) . '</span>';
                                    } elseif ($evSource !== '' && $evSource !== 'unknown') {
                                        $srcLbl = $paymentSourceLabels[$evSource] ?? $evSource;
                                        echo '<span class="tag-source">' . htmlspecialchars($srcLbl, ENT_QUOTES) . '</span>';
                                    } else {
                                        echo '<span style="color: var(--text-muted);">-</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo !empty($ev['is_paid']) ? '$' . $ev['price_usdc'] : '-'; ?></td>
                                <td>
                                    <?php
                                    $evTx = (string)($ev['settlement_tx'] ?? '');
                                    if ($evTx === '') {
                                        $evTx = (string)($ev['tx_hash'] ?? '');
                                    }
                                    ?>
                                    <?php if ($evTx !== ''): ?>
                                        <a href="https://basescan.org/tx/<?php echo htmlspecialchars($evTx, ENT_QUOTES); ?>" target="_blank" class="tx-link">
                                            <?php echo substr($evTx, 0, 8) . '...' . substr($evTx, -6); ?> ↗
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

    <!-- Modal de Alteracao de Senha do Operador -->
    <div id="password-modal-overlay" class="modal-overlay" onclick="handlePasswordOverlayClick(event)">
        <div class="modal-card">
            <div class="modal-header">
                <span class="modal-title">🔑 Alterar Senha do Operador</span>
                <button type="button" class="modal-close" onclick="closePasswordModal()">✕</button>
            </div>
            <form id="change-password-form" onsubmit="submitPasswordChange(event)" style="padding: 1.5rem;">
                <div id="password-modal-alert" style="display: none; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-bottom: 1rem;"></div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="modal-curr-pass" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Senha Atual</label>
                    <input type="password" id="modal-curr-pass" required class="search-input" style="max-width: 100%; width: 100%;" autocomplete="current-password">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="modal-new-pass" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Nova Senha (min. 8 caracteres)</label>
                    <input type="password" id="modal-new-pass" required minlength="8" class="search-input" style="max-width: 100%; width: 100%;" autocomplete="new-password">
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="modal-confirm-pass" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Confirmar Nova Senha</label>
                    <input type="password" id="modal-confirm-pass" required minlength="8" class="search-input" style="max-width: 100%; width: 100%;" autocomplete="new-password">
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn-outline" onclick="closePasswordModal()">Cancelar</button>
                    <button type="submit" id="btn-submit-password" class="btn" style="width: auto; padding: 0.5rem 1.25rem; font-size: 0.85rem; margin-top: 0;">Salvar Nova Senha</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentFeedFilter = 'all';
        let currentFeedSearchText = '';
        let cachedEvents = [];

        function setFeedFilter(filter) {
            currentFeedFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById('filter-btn-' + (filter === 'challenge_402' ? 'challenge' : (filter === 'blocked_403' ? 'blocked' : filter)));
            if (activeBtn) activeBtn.classList.add('active');
            applyFeedFilterAndSearch();
        }

        function applyFeedSearch() {
            const input = document.getElementById('feed-search-input');
            currentFeedSearchText = input ? input.value.trim().toLowerCase() : '';
            applyFeedFilterAndSearch();
        }

        function applyFeedFilterAndSearch() {
            const rows = document.querySelectorAll('.event-row');
            let visibleCount = 0;
            let totalCount = rows.length;

            let countPaid = 0;
            let countSandbox = 0;
            let countChallenge = 0;
            let countBlocked = 0;

            rows.forEach(r => {
                const type = r.getAttribute('data-type') || 'free';
                if (type === 'paid') countPaid++;
                if (type === 'sandbox') countSandbox++;
                if (type === 'challenge_402') countChallenge++;
                if (type === 'blocked_403') countBlocked++;

                const matchesFilter = (currentFeedFilter === 'all' || type === currentFeedFilter);
                const rowText = r.textContent.toLowerCase();
                const matchesSearch = (!currentFeedSearchText || rowText.includes(currentFeedSearchText));

                if (matchesFilter && matchesSearch) {
                    r.style.display = '';
                    visibleCount++;
                } else {
                    r.style.display = 'none';
                }
            });

            const countAllEl = document.getElementById('count-all');
            const countPaidEl = document.getElementById('count-paid');
            const countSandboxEl = document.getElementById('count-sandbox');
            const countChallengeEl = document.getElementById('count-challenge');
            const countBlockedEl = document.getElementById('count-blocked');

            if (countAllEl) countAllEl.textContent = totalCount;
            if (countPaidEl) countPaidEl.textContent = countPaid;
            if (countSandboxEl) countSandboxEl.textContent = countSandbox;
            if (countChallengeEl) countChallengeEl.textContent = countChallenge;
            if (countBlockedEl) countBlockedEl.textContent = countBlocked;

            const visCountEl = document.getElementById('feed-visible-count');
            if (visCountEl) visCountEl.textContent = 'Mostrando ' + visibleCount + ' de ' + totalCount;
        }

        // Utilitario de escape seguro contra DOM XSS
        function safeHtmlText(val) {
            if (val === null || val === undefined) return '';
            const span = document.createElement('span');
            span.textContent = String(val);
            return span.innerHTML;
        }

        // Renderiza barras de origem de pagamento / categoria de falha
        function renderBreakdownBars(containerId, keyAttr, counts, labels, gradient) {
            const el = document.getElementById(containerId);
            if (!el || !labels) return;
            counts = counts || {};
            const keys = Object.keys(labels);
            if (keys.length === 0) return;

            const total = keys.reduce((acc, k) => acc + Number(counts[k] || 0), 0);
            const maxVal = Math.max(1, ...keys.map(k => Number(counts[k] || 0)));
            const fillExtra = gradient ? '; background: linear-gradient(90deg, #EF4444, #F59E0B);' : '';
            let html = '';
            keys.forEach(k => {
                const c = Number(counts[k] || 0);
                if (c === 0 && k === 'unknown') return;
                const widthPct = Math.round((c / maxVal) * 100);
                const sharePct = total > 0 ? Math.round((c / total) * 100) : 0;
                html += `
                <div class="bar-row" ${keyAttr}="${safeHtmlText(k)}">
                    <span class="bar-label wide">${safeHtmlText(labels[k] || k)}</span>
                    <div class="bar-track"><div class="bar-fill" style="width: ${widthPct}%${fillExtra}"></div></div>
                    <span class="bar-count">${c} (${sharePct}%)</span>
                </div>`;
            });
            el.innerHTML = html;
        }

        // Renderiza o Gráfico Temporal SVG Nativo dos Últimos 14 Dias
        function renderSvgChart(history) {
            const svg = document.getElementById('chart-svg-14d');
            if (!svg || !history || history.length === 0) return;

            const maxReqs = Math.max(10, ...history.map(d => Number(d.requests || 0)));
            const width = 680;
            const height = 200;
            const padL = 35;
            const padR = 15;
            const padT = 20;
            const padB = 30;
            const chartW = width - padL - padR;
            const chartH = height - padT - padB;
            const n = history.length;
            const slotW = chartW / n;
            const barW = Math.max(12, slotW * 0.55);

            let svgContent = `
            <defs>
                <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#00F0FF" stop-opacity="0.9"/>
                    <stop offset="100%" stop-color="#0052FF" stop-opacity="0.4"/>
                </linearGradient>
            </defs>`;

            // Linhas de Grade e Eixo Y
            const gridSteps = [0, 0.5, 1];
            gridSteps.forEach(step => {
                const y = padT + chartH - (step * chartH);
                const val = Math.round(step * maxReqs);
                svgContent += `
                <line x1="${padL}" y1="${y}" x2="${width - padR}" y2="${y}" class="chart-grid-line" />
                <text x="${padL - 6}" y="${y + 3}" class="chart-axis-text" style="text-anchor: end;">${val}</text>`;
            });

            // Barras e Eixo X
            history.forEach((d, i) => {
                const reqs = Number(d.requests || 0);
                const paid = Number(d.paid || 0);
                const usdc = Number(d.usdc || 0);
                const barH = reqs > 0 ? Math.max(4, Math.round((reqs / maxReqs) * chartH)) : 0;
                const paidH = paid > 0 ? Math.max(4, Math.round((paid / maxReqs) * chartH)) : 0;
                const slotX = padL + (i * slotW);
                const barX = slotX + ((slotW - barW) / 2);
                const barY = padT + chartH - barH;
                const paidY = padT + chartH - paidH;
                const labelX = slotX + (slotW / 2);

                if (barH > 0) {
                    svgContent += `<rect x="${barX}" y="${barY}" width="${barW}" height="${barH}" rx="3" class="chart-bar-rect" />`;
                }

                if (paidH > 0) {
                    svgContent += `<rect x="${barX}" y="${paidY}" width="${barW}" height="${paidH}" rx="3" class="chart-bar-paid-rect" />`;
                }

                svgContent += `<text x="${labelX}" y="${height - 10}" class="chart-axis-text">${safeHtmlText(d.label || '')}</text>`;

                svgContent += `
                <rect x="${slotX}" y="${padT}" width="${slotW}" height="${chartH}" fill="transparent" style="cursor: pointer;"
                    data-date="${safeHtmlText(d.date || '')}"
                    data-requests="${reqs}"
                    data-paid="${paid}"
                    data-usdc="${usdc.toFixed(4)}"
                    onmouseenter="handleBarHover(event, this)"
                    onmouseleave="handleBarLeave()"
                    ontouchstart="handleBarHover(event, this)" />`;
            });

            svg.innerHTML = svgContent;
        }

        function handleBarHover(e, el) {
            const tooltip = document.getElementById('chart-tooltip');
            const wrap = document.getElementById('chart-wrap-14d');
            if (!tooltip || !wrap) return;

            const date = el.getAttribute('data-date');
            const reqs = el.getAttribute('data-requests');
            const paid = el.getAttribute('data-paid');
            const usdc = el.getAttribute('data-usdc');

            tooltip.innerHTML = `
                <div style="font-weight: 700; color: var(--accent); margin-bottom: 2px;">📅 ${date}</div>
                <div>📊 Total: <strong>${reqs}</strong> requisições</div>
                <div>🟢 Pagas (x402): <strong>${paid}</strong></div>
                <div>💰 Faturamento: <strong>US$ ${usdc}</strong></div>
            `;
            tooltip.style.display = 'block';

            const wrapRect = wrap.getBoundingClientRect();
            const clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : wrapRect.left + wrapRect.width / 2);
            let leftPos = clientX - wrapRect.left - 60;
            if (leftPos < 10) leftPos = 10;
            if (leftPos > wrapRect.width - 160) leftPos = wrapRect.width - 160;

            tooltip.style.left = leftPos + 'px';
            tooltip.style.top = '10px';
        }

        function handleBarLeave() {
            const tooltip = document.getElementById('chart-tooltip');
            if (tooltip) tooltip.style.display = 'none';
        }

        // Renderiza a lista de Top Consumidores
        function renderTopConsumers(consumers) {
            const tbody = document.getElementById('top-consumers-tbody');
            if (!tbody) return;
            if (!consumers || consumers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Aguardando eventos no buffer.</td></tr>';
                return;
            }
            let html = '';
            consumers.forEach(c => {
                let pCls = 'profile-agent';
                if (c.paid > 0) pCls = 'profile-paid';
                else if (c.sandbox > 0) pCls = 'profile-sandbox';
                else if (c.challenges > 5) pCls = 'profile-402';
                const rkCls = (c.rank <= 3) ? 'top-' + c.rank : '';
                html += `
                <tr>
                    <td><span class="rank-badge ${rkCls}">${c.rank}</span></td>
                    <td><code style="font-family: var(--font-mono); font-size: 0.78rem;">${safeHtmlText(c.ip_masked)}</code></td>
                    <td><strong>${c.total}</strong> <span style="font-size: 0.7rem; color: var(--text-muted);">(${c.paid}p)</span></td>
                    <td><span class="tag-profile ${pCls}">${safeHtmlText(c.profile)}</span></td>
                    <td><code style="font-size: 0.72rem; color: var(--accent);">${safeHtmlText(c.favorite_service)}</code></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        // Renderiza a lista de Rentabilidade por Servico
        function renderServiceRevenue(services) {
            const el = document.getElementById('service-revenue-bars-container');
            if (!el || !services) return;
            const top10 = services.slice(0, 10);
            const maxVal = Math.max(0.0001, ...top10.map(s => Number(s.estimated_usdc || 0)));
            let html = '';
            top10.forEach(s => {
                const rev = Number(s.estimated_usdc || 0);
                const p = Math.round((rev / maxVal) * 100);
                html += `
                <div class="bar-row">
                    <span class="bar-label wide">${safeHtmlText(s.service)} <small style="color: var(--text-muted);">($${s.unit_price})</small></span>
                    <div class="bar-track"><div class="bar-fill" style="width: ${p}%; background: linear-gradient(90deg, #10B981, #00F0FF);"></div></div>
                    <span class="bar-count" style="width: 72px;">$${rev.toFixed(4)}</span>
                </div>`;
            });
            el.innerHTML = html;
        }

        async function fetchLiveStats(isManual = false) {
            try {
                const res = await fetch('/admin?api=stats', { cache: 'no-store' });
                if (res.status === 401) {
                    window.location.reload();
                    return;
                }
                const data = await res.json();
                if (data.status !== 'success') return;

                // 1. Atualizar KPIs Financeiros e Operacionais
                const totalUsdcEl = document.getElementById('kpi-total-usdc');
                const totalBrlEl = document.getElementById('kpi-total-brl');
                const todayUsdcEl = document.getElementById('kpi-today-usdc');
                const todayBrlEl = document.getElementById('kpi-today-brl');
                const totalReqsEl = document.getElementById('kpi-total-reqs');
                const paidReqsEl = document.getElementById('kpi-paid-reqs');
                const convEl = document.getElementById('kpi-conversion');
                const arpuUsdcEl = document.getElementById('kpi-arpu-usdc');
                const arpuBrlEl = document.getElementById('kpi-arpu-brl');
                const slaTechEl = document.getElementById('kpi-sla-tech');
                const sandboxRateEl = document.getElementById('kpi-sandbox-rate');
                const passesEl = document.getElementById('kpi-wallet-passes');

                if (totalUsdcEl) totalUsdcEl.textContent = 'US$ ' + Number(data.total_revenue_usdc || 0).toFixed(4);
                if (totalBrlEl) totalBrlEl.textContent = '≈ R$ ' + Number(data.total_revenue_brl || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' (Base Mainnet)';
                if (todayUsdcEl) todayUsdcEl.textContent = 'US$ ' + Number(data.today_revenue_usdc || 0).toFixed(4);
                if (todayBrlEl) todayBrlEl.textContent = '≈ R$ ' + Number(data.today_revenue_brl || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' hoje';
                if (totalReqsEl) totalReqsEl.textContent = Number(data.total_requests || 0).toLocaleString('pt-BR');
                if (paidReqsEl) paidReqsEl.textContent = Number(data.total_paid_requests || 0).toLocaleString('pt-BR') + ' pagas • ' + Number(data.total_sandbox_requests || 0).toLocaleString('pt-BR') + ' sandbox';

                if (arpuUsdcEl) arpuUsdcEl.textContent = 'US$ ' + Number(data.arpu_usdc ?? 0).toFixed(4);
                if (arpuBrlEl) arpuBrlEl.textContent = '≈ R$ ' + Number(data.arpu_brl || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 }) + ' / consulta paga';
                if (slaTechEl) slaTechEl.textContent = Number(data.technical_success_rate || 100).toFixed(1) + '%';
                if (sandboxRateEl) sandboxRateEl.textContent = Number(data.sandbox_rate || 0).toFixed(1) + '%';

                if (passesEl && data.passes_stats) {
                    passesEl.textContent = Number(data.passes_stats.active_passes || 0) + ' passes ativos • ' + Number(data.passes_stats.remaining_credits || 0).toLocaleString('pt-BR') + ' créditos';
                }

                const reqs402 = (data.status_counts ? Number(data.status_counts['402'] || 0) : 0);
                const paid = Number(data.total_paid_requests || 0);
                const conv = (reqs402 + paid > 0) ? ((paid / (reqs402 + paid)) * 100).toFixed(1) : 0;
                if (convEl) convEl.textContent = conv + '%';

                // 2. Atualizar Card On-Chain (Base Mainnet)
                if (data.wallet_balances) {
                    const w = data.wallet_balances;
                    const usdcEl = document.getElementById('kpi-wallet-usdc');
                    const rpcEl = document.getElementById('kpi-rpc-status');
                    if (usdcEl) usdcEl.textContent = 'US$ ' + Number(w.usdc_balance || 0).toFixed(4);
                    if (rpcEl) {
                        rpcEl.textContent = w.rpc_operational ? 'RPC ATIVO' : 'CACHE OFFLINE';
                        rpcEl.style.color = w.rpc_operational ? 'var(--primary)' : 'var(--warning)';
                    }
                }

                // 2b. Atualizar Gráfico Temporal 14 Dias
                if (data.daily_history_14d) {
                    renderSvgChart(data.daily_history_14d);
                }

                // 2c. Atualizar Top Agentes / Consumidores
                if (data.top_consumers) {
                    renderTopConsumers(data.top_consumers);
                }

                // 2d. Atualizar Rentabilidade Estimada por Serviço
                if (data.service_revenue) {
                    renderServiceRevenue(data.service_revenue);
                }

                // 3. Atualizar Barras de Servicos (19 Nós)
                const serviceBarsEl = document.getElementById('service-bars-container');
                if (serviceBarsEl) {
                    if (data.service_counts) {
                        const servicesList = ['lei', 'ofac', 'notarize', 'global-markets', 'pass', 'compliance', 'sanctions', 'simples', 'workdays', 'indicators', 'holidays', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'cep', 'cnpj', 'summary'];
                        const sCounts = data.service_counts || {};
                        const maxS = Math.max(...Object.values(sCounts).concat([1]));
                        let sHtml = '';
                        servicesList.forEach(s => {
                            const c = Number(sCounts[s] || 0);
                            const p = Math.round((c / maxS) * 100);
                            sHtml += `
                            <div class="bar-row">
                                <span class="bar-label">${safeHtmlText(s)}</span>
                                <div class="bar-track"><div class="bar-fill" style="width: ${p}%;"></div></div>
                                <span class="bar-count">${c}</span>
                            </div>`;
                        });
                        serviceBarsEl.innerHTML = sHtml;
                    }
                }

                // 3b. Atualizar Barras de Origem de Pagamento e Categorias de Falha
                renderBreakdownBars(
                    'payment-source-bars-container',
                    'data-source-key',
                    data.payment_sources,
                    data.payment_source_labels,
                    false
                );
                renderBreakdownBars(
                    'failure-bars-container',
                    'data-failure-key',
                    data.failure_categories,
                    data.failure_category_labels,
                    true
                );

                const sourceLabels = data.payment_source_labels || {};
                const failureLabels = data.failure_category_labels || {};

                // 4. Atualizar Tabela de Eventos (100 Eventos Sanitizados)
                const tbody = document.getElementById('recent-events-tbody');
                const events = data.recent_events || [];
                cachedEvents = events;

                if (tbody) {
                    if (events.length === 0) {
                        tbody.innerHTML = '<tr id="no-events-row"><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma consulta registrada até o momento.</td></tr>';
                    } else {
                        let rowsHtml = '';
                        events.forEach(ev => {
                            const time = safeHtmlText((ev.timestamp || '').substr(11, 8));
                            const st = Number(ev.status || 200);
                            const rawType = ev.type || (ev.is_paid ? 'paid' : 'free');
                            const safeType = safeHtmlText(rawType);

                            let typeBadge = '<span style="color: var(--text-muted); font-size: 0.75rem;">GRATUITO</span>';
                            if (rawType === 'paid') {
                                typeBadge = '<span class="tag-paid">PAGO x402</span>';
                            } else if (rawType === 'sandbox') {
                                typeBadge = '<span class="tag-status status-sandbox">SANDBOX (demo)</span>';
                            } else if (rawType === 'challenge_402') {
                                typeBadge = '<span class="tag-status status-402">DESAFIO 402</span>';
                            } else if (rawType === 'blocked_403') {
                                typeBadge = '<span class="tag-status status-403">BLOQUEADO 403</span>';
                            }

                            const val = ev.is_paid ? '$' + safeHtmlText(ev.price_usdc) : '-';

                            const failKey = String(ev.failure_category || '');
                            const srcKey = String(ev.payment_source || '');
                            let originBadge = '<span style="color: var(--text-muted);">-</span>';
                            if (failKey && failKey !== 'unknown') {
                                originBadge = `<span class="tag-failure">${safeHtmlText(failureLabels[failKey] || failKey)}</span>`;
                            } else if (srcKey && srcKey !== 'unknown') {
                                originBadge = `<span class="tag-source">${safeHtmlText(sourceLabels[srcKey] || srcKey)}</span>`;
                            }

                            const rawTx = String(ev.settlement_tx || ev.tx_hash || '');
                            const isHexTx = /^0x[a-f0-9]{64}$/i.test(rawTx);
                            const tx = isHexTx
                                ? `<a href="https://basescan.org/tx/${encodeURIComponent(rawTx)}" target="_blank" class="tx-link">${safeHtmlText(rawTx.substr(0, 8))}...${safeHtmlText(rawTx.substr(-6))} ↗</a>`
                                : '<span style="color: var(--text-muted);">-</span>';

                            const cleanService = safeHtmlText(ev.service || '');
                            const cleanIp = safeHtmlText(ev.ip_masked || 'xxx.xxx.xxx.xxx');

                            rowsHtml += `
                            <tr class="event-row" data-type="${safeType}">
                                <td>${time}</td>
                                <td><strong>${cleanService}</strong></td>
                                <td><span class="tag-status status-${st}">HTTP ${st}</span></td>
                                <td>${typeBadge}</td>
                                <td>${originBadge}</td>
                                <td>${val}</td>
                                <td>${tx}</td>
                                <td style="color: var(--text-muted);">${cleanIp}</td>
                            </tr>`;
                        });
                        tbody.innerHTML = rowsHtml;
                    }
                }

                const lastUpdEl = document.getElementById('last-updated-text');
                if (lastUpdEl) {
                    lastUpdEl.textContent = 'Última atualização: ' + new Date().toLocaleTimeString('pt-BR');
                }

                applyFeedFilterAndSearch();
            } catch (e) {
                console.error('Erro ao buscar stats em tempo real:', e);
            }
        }

        // Modal de Alteracao de Senha
        function openPasswordModal() {
            const overlay = document.getElementById('password-modal-overlay');
            const alertBox = document.getElementById('password-modal-alert');
            if (alertBox) alertBox.style.display = 'none';
            if (overlay) overlay.classList.add('active');
            const currInp = document.getElementById('modal-curr-pass');
            if (currInp) currInp.focus();
        }

        function closePasswordModal() {
            const overlay = document.getElementById('password-modal-overlay');
            if (overlay) overlay.classList.remove('active');
            const form = document.getElementById('change-password-form');
            if (form) form.reset();
        }

        function handlePasswordOverlayClick(e) {
            if (e.target.id === 'password-modal-overlay') closePasswordModal();
        }

        async function submitPasswordChange(e) {
            e.preventDefault();
            const currPass = document.getElementById('modal-curr-pass').value;
            const newPass = document.getElementById('modal-new-pass').value;
            const confirmPass = document.getElementById('modal-confirm-pass').value;
            const alertBox = document.getElementById('password-modal-alert');
            const btn = document.getElementById('btn-submit-password');

            if (newPass !== confirmPass) {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
                alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'A nova senha e a confirmacao nao coincidem.';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Salvando...';

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfVal = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const formData = new FormData();
                formData.append('current_password', currPass);
                formData.append('new_password', newPass);
                formData.append('new_password_confirm', confirmPass);
                formData.append('csrf_token', csrfVal);

                const res = await fetch('/admin?action=change_password', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfVal
                    },
                    body: formData
                });
                const data = await res.json();

                alertBox.style.display = 'block';
                if (data.success) {
                    alertBox.style.background = 'rgba(16, 185, 129, 0.15)';
                    alertBox.style.color = '#34D399';
                    alertBox.textContent = data.message || 'Senha alterada com sucesso!';
                    setTimeout(() => {
                        closePasswordModal();
                    }, 1500);
                } else {
                    alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
                    alertBox.style.color = '#FCA5A5';
                    alertBox.textContent = data.error || 'Nao foi possivel alterar a senha.';
                }
            } catch (err) {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
                alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'Erro ao conectar ao servidor: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.textContent = 'Salvar Nova Senha';
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePasswordModal();
        });

        // Inicializacao dos contadores e polling
        const initialDaily = <?php echo json_encode($dailyHistory14d, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        renderSvgChart(initialDaily);
        applyFeedFilterAndSearch();
        fetchLiveStats();
        setInterval(fetchLiveStats, 5000);
    </script>
    <footer style="max-width: 1280px; margin: 2.5rem auto 1.5rem; padding: 1.25rem 1.5rem 0; border-top: 1px solid rgba(255,255,255,0.08); text-align: center; font-size: 0.78rem; color: #64748B;">
        © <?php echo date('Y'); ?> Grid402 Network Intelligence. Todos os direitos reservados • Software e Arquitetura Protegidos (Lei nº 9.609/1998 e Lei nº 9.610/1998).
    </footer>
</body>
</html>
