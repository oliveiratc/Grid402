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
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/growth.php';
require_once __DIR__ . '/facilitator.php';
require_once __DIR__ . '/x402.php';
require_once __DIR__ . '/services/forex.php';
require_once __DIR__ . '/services/admin_insights.php';
require_once __DIR__ . '/includes/admin_auth_guard.php';

/**
 * Retorna snapshot seguro (sem segredos) do estado do facilitador x402, coletor e Telegram.
 */
function grid402_admin_runtime_status(): array {
    $facMode = defined('GRID402_FACILITATOR_MODE') ? (string) GRID402_FACILITATOR_MODE : 'settle';
    $facUrl = defined('GRID402_FACILITATOR_URL') ? (string) GRID402_FACILITATOR_URL : '';
    $facHost = $facUrl !== '' ? (string) (parse_url($facUrl, PHP_URL_HOST) ?: '') : '';
    $authMode = function_exists('grid402_facilitator_auth_mode') ? grid402_facilitator_auth_mode() : 'public';
    $x402Network = defined('GRID402_X402_NETWORK') ? (string) GRID402_X402_NETWORK : 'base';

    $summaryFile = defined('GRID402_FILE_FREE') ? GRID402_FILE_FREE : '';
    $summaryExists = ($summaryFile !== '' && file_exists($summaryFile));
    $summaryMtime = $summaryExists ? (int) @filemtime($summaryFile) : 0;
    $summaryAgeSec = ($summaryExists && $summaryMtime > 0) ? max(0, time() - $summaryMtime) : null;

    $tg = function_exists('grid402_telegram_runtime_status')
        ? grid402_telegram_runtime_status()
        : ['configured' => false, 'disabled' => false, 'token_configured' => false, 'chat_id_configured' => false, 'last_status' => null];

    $rapid = function_exists('grid402_rapidapi_runtime_status')
        ? grid402_rapidapi_runtime_status()
        : [
            'configured' => defined('GRID402_RAPIDAPI_PROXY_SECRET') && strlen((string) GRID402_RAPIDAPI_PROXY_SECRET) >= 16,
            'source' => 'env',
        ];
    $multiChainCount = function_exists('grid402_build_accepts')
        ? count(grid402_build_accepts(defined('GRID402_USDC_REQUIRED_RAW') ? (int) GRID402_USDC_REQUIRED_RAW : 5000))
        : 1;

    return [
        'facilitator' => [
            'mode' => $facMode,
            'auth_mode' => $authMode,
            'host' => $facHost,
            'network' => $x402Network,
            'multichain_accepts' => $multiChainCount,
        ],
        'collector' => [
            'summary_exists' => $summaryExists,
            'summary_age_sec' => $summaryAgeSec,
            'summary_stale' => ($summaryAgeSec === null || $summaryAgeSec > 600),
        ],
        'telegram' => $tg,
        'rapidapi' => $rapid,
    ];
}

/**
 * Formata um timestamp ISO-8601 UTC exibindo o horario de Brasilia (BRT, America/Sao_Paulo)
 * como linha principal e o horario UTC original como referencia secundaria.
 *
 * @return array{brt: string, utc: string}
 */
function grid402_admin_format_brt_utc(string $isoUtc, bool $timeOnly = false): array {
    $trimmed = trim($isoUtc);
    if ($trimmed === '') {
        return ['brt' => '-', 'utc' => ''];
    }
    try {
        $dtUtc = new DateTimeImmutable($trimmed, new DateTimeZone('UTC'));
        $dtBrt = $dtUtc->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        if ($timeOnly) {
            return [
                'brt' => $dtBrt->format('H:i:s') . ' BRT',
                'utc' => $dtUtc->format('H:i:s') . ' UTC',
            ];
        }
        return [
            'brt' => $dtBrt->format('d/m H:i:s') . ' BRT',
            'utc' => $trimmed,
        ];
    } catch (Throwable $e) {
        return [
            'brt' => $timeOnly ? substr($trimmed, 11, 8) : $trimmed,
            'utc' => '',
        ];
    }
}

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

    $oracleAddress = defined('GRID402_WALLET_ADDRESS') ? GRID402_WALLET_ADDRESS : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
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

// 1. Configuração estrita de sessão segura (HttpOnly, SameSite=Lax, Fingerprint IP/UA e Idle Timeout)
grid402_admin_session_start();

// 1.1. Cabeçalhos de segurança (anti-clickjacking, MIME sniffing, referrer)
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

$authFile = GRID402_FILE_ADMIN_AUTH;
$hasSetup = file_exists($authFile);
$action = (string) ($_GET['action'] ?? '');
$apiParam = (string) ($_GET['api'] ?? '');
$errorMsg = '';
$successMsg = '';

$isBackgroundPoll = ($apiParam === 'stats' || $apiParam === 'growth');
$touchSessionActivity = !$isBackgroundPoll || ((string) ($_GET['touch'] ?? '') === '1');
$sessionVerification = grid402_verify_active_admin_session($touchSessionActivity);
$isLoggedIn = (bool) $sessionVerification['valid'];

if (!$isLoggedIn && ($sessionVerification['reason'] === 'idle_timeout' || (string) ($_GET['expired'] ?? '') === 'idle_timeout')) {
    $errorMsg = 'Sua sessão expirou por inatividade. Faça login novamente.';
} elseif (!$isLoggedIn && ($sessionVerification['reason'] === 'fingerprint_mismatch' || (string) ($_GET['expired'] ?? '') === 'fingerprint_mismatch')) {
    $errorMsg = 'Sessão encerrada por mudança de rede ou navegador. Autentique-se novamente.';
}

if ($action === 'cancel_2fa') {
    unset($_SESSION['admin_2fa_pending']);
    header('Location: /admin');
    exit;
}

if ($action === 'cancel_recovery') {
    unset($_SESSION['admin_recovery_pending']);
    header('Location: /admin');
    exit;
}

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
        $data = grid402_read_locked_json($fp, []);

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
        $data = grid402_read_locked_json($fp, []);
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

/**
 * Controle de Força Bruta para Recuperação de Senha via Telegram (Zero-DB)
 * Limite de 3 envios por janela de 10 minutos (600s) por IP.
 */
function grid402_get_recovery_rate_file(): string {
    $dir = defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR : (__DIR__ . '/private_data');
    return $dir . '/admin_recovery_rate.json';
}

function grid402_check_recovery_rate(string $ip): array {
    $file = grid402_get_recovery_rate_file();
    $maxAttempts = 3;
    $lockoutSeconds = 600;
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

    $firstFail = (int)($entry['first_attempt'] ?? $now);
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

function grid402_record_recovery_attempt(string $ip): void {
    $file = grid402_get_recovery_rate_file();
    $lockoutSeconds = 600;
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
        $data = grid402_read_locked_json($fp, []);

        $cleaned = [];
        foreach ($data as $kIp => $v) {
            $t = (int)($v['first_attempt'] ?? 0);
            if (($now - $t) < $lockoutSeconds) {
                $cleaned[$kIp] = $v;
            }
        }

        $entry = $cleaned[$ip] ?? ['attempts' => 0, 'first_attempt' => $now];
        if (($now - (int)$entry['first_attempt']) >= $lockoutSeconds) {
            $entry = ['attempts' => 0, 'first_attempt' => $now];
        }

        $entry['attempts'] = ((int)$entry['attempts']) + 1;
        $entry['last_attempt'] = $now;
        $cleaned[$ip] = $entry;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// 3. Processamento de POST (Setup, Login ou Alteracao de Senha)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    // 3.0a. Disparo de teste Telegram autenticado (diagnostico sem segredos)
    if ($action === 'test_telegram') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$isLoggedIn) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessao administrativa expirada ou nao autenticada.']);
            exit;
        }
        if (!grid402_validate_csrf($submittedCsrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token CSRF de seguranca ausente ou invalido.']);
            exit;
        }
        $sent = grid402_notify_telegram_purchase('cnpj', 0.0200, '0x' . str_repeat('a', 64), true);
        $tgStatus = grid402_telegram_runtime_status();
        $errDetail = (string) ($tgStatus['last_status']['error'] ?? '');
        echo json_encode([
            'success' => $sent,
            'telegram_status' => $tgStatus,
            'message' => $sent
                ? 'Alerta de teste enviado para o Telegram!'
                : ('Falha no envio' . ($errDetail !== '' ? ': ' . $errDetail : '. Verifique GRID402_TELEGRAM_BOT_TOKEN e GRID402_TELEGRAM_CHAT_ID no .env deste servidor.')),
        ]);
        exit;
    }

    // 3.0b. Configuracao de credenciais Telegram (salvo em private_data/telegram_config.json)
    if ($action === 'save_telegram_config') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$isLoggedIn) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessao administrativa expirada ou nao autenticada.']);
            exit;
        }
        if (!grid402_validate_csrf($submittedCsrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token CSRF de seguranca ausente ou invalido.']);
            exit;
        }

        $inputToken = trim((string)($_POST['bot_token'] ?? ''));
        $inputChat = trim((string)($_POST['chat_id'] ?? ''));
        $existingCreds = grid402_resolve_telegram_credentials();

        if ($inputToken === '' && $existingCreds['token'] !== '') {
            $inputToken = $existingCreds['token'];
        }
        if ($inputChat === '' && $existingCreds['chat_id'] !== '') {
            $inputChat = $existingCreds['chat_id'];
        }

        if (preg_match('/^\d{6,15}:[A-Za-z0-9_-]{20,80}$/', $inputToken) !== 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Formato de Bot Token invalido. Exemplo esperado: 123456789:AAH...']);
            exit;
        }
        if (preg_match('/^(-?\d{5,20}|@[A-Za-z0-9_]{4,64})$/', $inputChat) !== 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Formato de Chat ID invalido. Exemplo esperado: 123456789 ou -100123456789.']);
            exit;
        }

        if (!grid402_save_telegram_config($inputToken, $inputChat)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Nao foi possivel gravar private_data/telegram_config.json no servidor.']);
            exit;
        }

        $skipTest = ((string)($_POST['skip_test'] ?? '') === '1');
        $sent = $skipTest ? false : grid402_notify_telegram_purchase('cnpj', 0.0200, '0x' . str_repeat('a', 64), true);
        $tgStatus = grid402_telegram_runtime_status();
        $errDetail = (string) ($tgStatus['last_status']['error'] ?? '');

        echo json_encode([
            'success' => true,
            'test_sent' => $sent,
            'telegram_status' => $tgStatus,
            'message' => $sent
                ? 'Credenciais salvas e alerta de teste entregue no Telegram!'
                : ($skipTest ? 'Credenciais do Telegram salvas com sucesso!' : ('Credenciais salvas, mas o teste falhou' . ($errDetail !== '' ? ': ' . $errDetail : '.'))),
        ]);
        exit;
    }

    // 3.0c. Configuracao persistente do RapidAPI Proxy Secret (salvo em storage/rapidapi_config.json)
    if ($action === 'save_rapidapi_config') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$isLoggedIn) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessao administrativa expirada ou nao autenticada.']);
            exit;
        }
        if (!grid402_validate_csrf($submittedCsrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token CSRF de seguranca ausente ou invalido.']);
            exit;
        }

        $isClear = ((string) ($_POST['clear'] ?? '') === '1');
        if ($isClear) {
            if (!grid402_save_rapidapi_config('')) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Nao foi possivel remover rapidapi_config.json no servidor.']);
                exit;
            }
            $rapidStatus = grid402_rapidapi_runtime_status();
            $msg = ($rapidStatus['source'] === 'env')
                ? 'Arquivo rapidapi_config.json removido (observacao: GRID402_RAPIDAPI_PROXY_SECRET no .env continua ativo).'
                : 'Configuracao salva no painel removida. RapidAPI Bridge desativado.';
            echo json_encode([
                'success' => true,
                'rapidapi_status' => $rapidStatus,
                'message' => $msg,
            ]);
            exit;
        }

        $rawInput = trim((string) ($_POST['proxy_secret'] ?? ''));
        $tokens = preg_split('/[\r\n,;]+/', $rawInput, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || empty($tokens) || count($tokens) > 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Informe de 1 a 10 segredos X-RapidAPI-Proxy-Secret separados por virgula.']);
            exit;
        }
        $validList = [];
        foreach ($tokens as $tok) {
            $cleanTok = trim($tok);
            if (preg_match('/^\S{16,256}$/', $cleanTok) !== 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Formato invalido. Cada X-RapidAPI-Proxy-Secret deve ter de 16 a 256 caracteres sem espacos.']);
                exit;
            }
            $validList[] = $cleanTok;
        }
        $serializedSecrets = implode(',', array_values(array_unique($validList)));

        if (!grid402_save_rapidapi_config($serializedSecrets)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Nao foi possivel gravar rapidapi_config.json no servidor.']);
            exit;
        }

        $rapidStatus = grid402_rapidapi_runtime_status();
        $rapidCount = (int) ($rapidStatus['count'] ?? 0);
        if ($rapidStatus['source'] === 'mixed') {
            $msg = "RapidAPI Bridge ativo com {$rapidCount} segredos (.env + painel persistente)!";
        } elseif ($rapidStatus['source'] === 'env') {
            $msg = 'Segredos salvos em rapidapi_config.json em conjunto com GRID402_RAPIDAPI_PROXY_SECRET do .env.';
        } else {
            $msg = ($rapidCount > 1)
                ? "RapidAPI Bridge ativado com {$rapidCount} segredos (salvo em storage persistente)!"
                : 'RapidAPI Bridge ativado com sucesso (salvo em storage persistente)!';
        }
        echo json_encode([
            'success' => true,
            'rapidapi_status' => $rapidStatus,
            'message' => $msg,
        ]);
        exit;
    }

    // 3.0. Alteracao de Senha / Usuario / 2FA Autenticada
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
        $newUsername = trim((string) ($_POST['new_username'] ?? ''));
        $hasTwoFactorField = array_key_exists('two_factor_telegram', $_POST);
        $twoFactorEnabled = $hasTwoFactorField ? ((string) $_POST['two_factor_telegram'] === '1') : null;

        $authContent = @file_get_contents($authFile);
        $authData = $authContent ? @json_decode($authContent, true) : null;

        $pErr = '';
        if (!$authData or !password_verify($currPass, (string)($authData['password_hash'] ?? ''))) {
            $pErr = 'A senha atual informada esta incorreta.';
        } elseif ($newUsername !== '' and strlen($newUsername) < 3) {
            $pErr = 'O nome de usuario deve ter no minimo 3 caracteres.';
        } elseif ($newPass !== '' or $newPassConfirm !== '' or (!$hasTwoFactorField and $newUsername === '')) {
            if (strlen($newPass) < 8) {
                $pErr = 'A nova senha deve ter no minimo 8 caracteres.';
            } elseif ($newPass !== $newPassConfirm) {
                $pErr = 'A nova senha e a confirmacao nao coincidem.';
            }
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
            if ($newPass !== '') {
                $authData['password_hash'] = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 11]);
            }
            if ($newUsername !== '') {
                $authData['username'] = $newUsername;
                $_SESSION['admin_user'] = $newUsername;
            }
            if ($twoFactorEnabled !== null) {
                $authData['two_factor_telegram'] = $twoFactorEnabled;
            }
            $authData['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
            grid402_atomic_write($authFile, $authData);

            if ($isJsonReq) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Credenciais e configuracoes de seguranca atualizadas com sucesso!',
                    'username' => (string) ($authData['username'] ?? ''),
                    'two_factor_telegram' => (bool) ($authData['two_factor_telegram'] ?? true),
                ]);
                exit;
            } else {
                $successMsg = 'Senha alterada com sucesso!';
            }
        }
    } elseif ($action === 'verify_2fa' || isset($_POST['otp_code'])) {
        // 3.0c. Verificacao do Codigo 2FA enviado via Telegram
        $clientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $pending = $_SESSION['admin_2fa_pending'] ?? null;

        if (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de seguranca invalido ou expirado. Recarregue a pagina.';
        } elseif (!is_array($pending)) {
            $errorMsg = 'Nenhum desafio 2FA ativo na sessao. Faca login novamente.';
        } elseif (!hash_equals((string)($pending['fingerprint'] ?? ''), grid402_admin_session_fingerprint())) {
            unset($_SESSION['admin_2fa_pending']);
            $errorMsg = 'Desafio 2FA invalidado por mudanca de rede ou navegador. Faca login novamente.';
        } elseif (time() > (int)($pending['expires_at'] ?? 0)) {
            unset($_SESSION['admin_2fa_pending']);
            $errorMsg = 'O código 2FA expirou (validade de 5 minutos). Faça login novamente.';
        } else {
            $_SESSION['admin_2fa_pending']['attempts'] = ((int)($pending['attempts'] ?? 0)) + 1;
            $attempts = (int) $_SESSION['admin_2fa_pending']['attempts'];
            $otpInput = preg_replace('/\D/', '', (string)($_POST['otp_code'] ?? ''));
            $expectedHash = (string)($pending['code_hash'] ?? '');
            $actualHash = hash_hmac('sha256', (string) $otpInput, $csrfToken);

            if ($otpInput !== '' && strlen((string) $otpInput) === 6 && hash_equals($expectedHash, $actualHash)) {
                $authContent = @file_get_contents($authFile);
                $authData = $authContent ? @json_decode($authContent, true) : null;
                $verifiedUser = (string)($pending['username'] ?? ($authData['username'] ?? 'admin'));

                if (is_array($authData)) {
                    $authData['last_login'] = gmdate('Y-m-d\TH:i:s\Z');
                    $authData['login_count'] = ((int)($authData['login_count'] ?? 0)) + 1;
                    grid402_atomic_write($authFile, $authData);
                }

                grid402_reset_login_rate($clientIp);
                grid402_establish_admin_session($verifiedUser);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                header('Location: /admin');
                exit;
            } else {
                usleep(250000);
                if ($attempts >= 5) {
                    unset($_SESSION['admin_2fa_pending']);
                    grid402_record_login_failure($clientIp);
                    $errorMsg = 'Limite de 5 tentativas para o código 2FA excedido. Faça login novamente.';
                } else {
                    $rem2fa = 5 - $attempts;
                    $errorMsg = "Código 2FA incorreto. ({$rem2fa} tentativa(s) restante(s)).";
                }
            }
        }
    } elseif ($action === 'resend_2fa') {
        // 3.0d. Reenvio do Codigo 2FA via Telegram
        $clientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $pending = $_SESSION['admin_2fa_pending'] ?? null;

        if (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de seguranca invalido ou expirado.';
        } elseif (!is_array($pending)) {
            $errorMsg = 'Nenhum desafio 2FA ativo. Faca login novamente.';
        } elseif ((time() - (int)($pending['sent_at'] ?? 0)) < 15) {
            $waitSec = max(1, 15 - (time() - (int)($pending['sent_at'] ?? 0)));
            $errorMsg = "Aguarde {$waitSec}s antes de solicitar um novo código.";
        } else {
            $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $maskedIp = function_exists('grid402_mask_ip') ? grid402_mask_ip($clientIp) : $clientIp;
            if (grid402_send_telegram_admin_otp((string)($pending['username'] ?? 'admin'), $otpCode, $maskedIp)) {
                $_SESSION['admin_2fa_pending']['code_hash'] = hash_hmac('sha256', $otpCode, $csrfToken);
                $_SESSION['admin_2fa_pending']['expires_at'] = time() + 300;
                $_SESSION['admin_2fa_pending']['attempts'] = 0;
                $_SESSION['admin_2fa_pending']['sent_at'] = time();
                $successMsg = 'Novo código de 6 dígitos enviado para o seu Telegram.';
            } else {
                $errorMsg = 'Falha ao reenviar código pelo Telegram.';
            }
        }
    } elseif ($action === 'forgot_password') {
        // 3.0e. Solicitação de Envio de Código de Recuperação via Telegram
        $clientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $tgStatus = function_exists('grid402_telegram_runtime_status') ? grid402_telegram_runtime_status() : ['configured' => false];

        $isTestCapture = ((string) grid402_env('GRID402_TEST_OTP_CAPTURE', '') === '1' && in_array($clientIp, ['127.0.0.1', '::1'], true));

        if (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de segurança inválido ou expirado. Recarregue a página.';
        } elseif (!$hasSetup) {
            $errorMsg = 'Nenhuma credencial configurada no sistema. Crie as credenciais no primeiro acesso.';
        } elseif (empty($tgStatus['configured']) && !$isTestCapture) {
            $errorMsg = 'Recuperação via Telegram indisponível: o bot @GRIG402BOT não está configurado neste servidor.';
        } else {
            $lastSent = (int)($_SESSION['admin_recovery_pending']['sent_at'] ?? 0);
            if ($lastSent > 0 && (time() - $lastSent) < 30) {
                $waitSec = max(1, 30 - (time() - $lastSent));
                $errorMsg = "Um código de recuperação já foi enviado recentemente. Aguarde {$waitSec}s para novo envio.";
            } else {
                $rateCheck = grid402_check_recovery_rate($clientIp);
                if (!$rateCheck['allowed']) {
                    $waitMinutes = max(1, (int) ceil(($rateCheck['retry_after'] ?? 600) / 60));
                    $errorMsg = "Muitas tentativas de recuperação. Acesso temporariamente bloqueado por {$waitMinutes} minuto(s).";
                } else {
                    $authRaw = @file_get_contents($authFile);
                    $authData = $authRaw ? @json_decode($authRaw, true) : null;
                    $targetUser = (string)($authData['username'] ?? 'admin');
                    $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $maskedIp = function_exists('grid402_mask_ip') ? grid402_mask_ip($clientIp) : $clientIp;

                    if (grid402_send_telegram_admin_recovery_otp($targetUser, $otpCode, $maskedIp)) {
                        grid402_record_recovery_attempt($clientIp);
                        $_SESSION['admin_recovery_pending'] = [
                            'username' => $targetUser,
                            'code_hash' => hash_hmac('sha256', $otpCode, $csrfToken),
                            'expires_at' => time() + 600, // 10 minutos
                            'attempts' => 0,
                            'sent_at' => time(),
                            'fingerprint' => grid402_admin_session_fingerprint(),
                        ];
                        $successMsg = 'Código de segurança de 6 dígitos enviado para o seu Telegram (@GRIG402BOT)!';
                    } else {
                        $errorMsg = 'Falha ao despachar mensagem pelo Telegram. Verifique se o bot está ativo.';
                    }
                }
            }
        }
    } elseif ($action === 'resend_recovery') {
        // 3.0f. Reenvio de Código de Recuperação via Telegram
        $clientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $pending = $_SESSION['admin_recovery_pending'] ?? null;

        if (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de segurança inválido ou expirado. Recarregue a página.';
        } elseif (!is_array($pending)) {
            $errorMsg = 'Nenhuma solicitação de recuperação ativa. Clique em "Esqueci minha senha" novamente.';
        } elseif ((time() - (int)($pending['sent_at'] ?? 0)) < 30) {
            $waitSec = max(1, 30 - (time() - (int)($pending['sent_at'] ?? 0)));
            $errorMsg = "Aguarde {$waitSec}s antes de solicitar um novo código.";
        } else {
            $rateCheck = grid402_check_recovery_rate($clientIp);
            if (!$rateCheck['allowed']) {
                $waitMinutes = max(1, (int) ceil(($rateCheck['retry_after'] ?? 600) / 60));
                $errorMsg = "Limite de solicitações excedido. Aguarde {$waitMinutes} minuto(s).";
            } else {
                $targetUser = (string)($pending['username'] ?? 'admin');
                $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $maskedIp = function_exists('grid402_mask_ip') ? grid402_mask_ip($clientIp) : $clientIp;

                if (grid402_send_telegram_admin_recovery_otp($targetUser, $otpCode, $maskedIp)) {
                    grid402_record_recovery_attempt($clientIp);
                    $_SESSION['admin_recovery_pending']['code_hash'] = hash_hmac('sha256', $otpCode, $csrfToken);
                    $_SESSION['admin_recovery_pending']['expires_at'] = time() + 600;
                    $_SESSION['admin_recovery_pending']['attempts'] = 0;
                    $_SESSION['admin_recovery_pending']['sent_at'] = time();
                    $successMsg = 'Novo código de recuperação de 6 dígitos enviado para o seu Telegram.';
                } else {
                    $errorMsg = 'Falha ao reenviar código pelo Telegram.';
                }
            }
        }
    } elseif ($action === 'confirm_recovery' || isset($_POST['recovery_otp'])) {
        // 3.0g. Validação do Código e Redefinição de Senha
        $clientIp = function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $pending = $_SESSION['admin_recovery_pending'] ?? null;

        if (!grid402_validate_csrf($submittedCsrf)) {
            $errorMsg = 'Token CSRF de segurança inválido ou expirado. Recarregue a página.';
        } elseif (!is_array($pending)) {
            $errorMsg = 'Nenhuma solicitação de recuperação ativa. Inicie a recuperação novamente.';
        } elseif (!hash_equals((string)($pending['fingerprint'] ?? ''), grid402_admin_session_fingerprint())) {
            unset($_SESSION['admin_recovery_pending']);
            $errorMsg = 'Solicitação invalidada por mudança de rede ou navegador. Inicie novamente.';
        } elseif (time() > (int)($pending['expires_at'] ?? 0)) {
            unset($_SESSION['admin_recovery_pending']);
            $errorMsg = 'O código de recuperação expirou (validade de 10 minutos). Solicite um novo código.';
        } else {
            $_SESSION['admin_recovery_pending']['attempts'] = ((int)($pending['attempts'] ?? 0)) + 1;
            $attempts = (int) $_SESSION['admin_recovery_pending']['attempts'];
            $otpInput = preg_replace('/\D/', '', (string)($_POST['recovery_otp'] ?? ''));
            $expectedHash = (string)($pending['code_hash'] ?? '');
            $actualHash = hash_hmac('sha256', (string) $otpInput, $csrfToken);

            if ($otpInput !== '' && strlen((string) $otpInput) === 6 && hash_equals($expectedHash, $actualHash)) {
                $newPass = (string)($_POST['new_password'] ?? '');
                $newPassConfirm = (string)($_POST['new_password_confirm'] ?? '');

                if (strlen($newPass) < 8) {
                    $errorMsg = 'A nova senha deve ter pelo menos 8 caracteres.';
                } elseif ($newPass !== $newPassConfirm) {
                    $errorMsg = 'As novas senhas digitadas não coincidem.';
                } else {
                    $authContent = @file_get_contents($authFile);
                    $authData = $authContent ? @json_decode($authContent, true) : null;
                    if (!is_array($authData)) {
                        $authData = [
                            'username' => (string)($pending['username'] ?? 'admin'),
                            'two_factor_telegram' => true,
                            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                        ];
                    }
                    $authData['password_hash'] = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 11]);
                    $authData['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    $authData['last_login'] = gmdate('Y-m-d\TH:i:s\Z');
                    $authData['login_count'] = ((int)($authData['login_count'] ?? 0)) + 1;
                    grid402_atomic_write($authFile, $authData);

                    $targetUser = (string)($authData['username'] ?? ($pending['username'] ?? 'admin'));
                    $maskedIp = function_exists('grid402_mask_ip') ? grid402_mask_ip($clientIp) : $clientIp;
                    grid402_reset_login_rate($clientIp);
                    grid402_notify_telegram_admin_password_reset($targetUser, $maskedIp);

                    unset($_SESSION['admin_recovery_pending']);
                    grid402_establish_admin_session($targetUser);
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    header('Location: /admin?recovery=done');
                    exit;
                }
            } else {
                usleep(250000);
                if ($attempts >= 5) {
                    unset($_SESSION['admin_recovery_pending']);
                    grid402_record_login_failure($clientIp);
                    $errorMsg = 'Limite de 5 tentativas de código excedido. Solicite um novo código.';
                } else {
                    $rem = 5 - $attempts;
                    $errorMsg = "Código de recuperação incorreto. ({$rem} tentativa(s) restante(s)).";
                }
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
                    'two_factor_telegram' => true,
                    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                    'last_login' => gmdate('Y-m-d\TH:i:s\Z'),
                    'login_count' => 1,
                ];
                $saved = grid402_atomic_write($authFile, $authData);

                if (!$saved or !file_exists($authFile)) {
                    $errorMsg = 'Erro crítico: não foi possível gravar as credenciais no disco. Verifique permissões do diretório de armazenamento.';
                } else {
                    grid402_establish_admin_session($user);
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    header('Location: /admin?setup=success');
                    exit;
                }
            }
        }
    } else {
        // 3.2. Login Normal com Protecao Anti-Brute-Force + 2FA Telegram
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
                if (grid402_is_telegram_2fa_required($authData)) {
                    $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $maskedIp = function_exists('grid402_mask_ip') ? grid402_mask_ip($clientIp) : $clientIp;
                    $sentOtp = grid402_send_telegram_admin_otp((string) $authData['username'], $otpCode, $maskedIp);

                    if ($sentOtp) {
                        $_SESSION['admin_2fa_pending'] = [
                            'username' => (string) $authData['username'],
                            'code_hash' => hash_hmac('sha256', $otpCode, $csrfToken),
                            'expires_at' => time() + 300,
                            'attempts' => 0,
                            'sent_at' => time(),
                            'fingerprint' => grid402_admin_session_fingerprint(),
                        ];
                    } else {
                        $errorMsg = 'Não foi possível enviar o código 2FA para o Telegram. Verifique a conectividade do bot.';
                    }
                } else {
                    grid402_reset_login_rate($clientIp);
                    grid402_establish_admin_session((string) $authData['username']);

                    $authData['last_login'] = gmdate('Y-m-d\TH:i:s\Z');
                    $authData['login_count'] = ((int)($authData['login_count'] ?? 0)) + 1;
                    grid402_atomic_write($authFile, $authData);

                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    header('Location: /admin');
                    exit;
                }
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
    'facilitator' => 'Facilitador x402 (settle)',
    'facilitator_verify_only' => 'Facilitador (verify only)',
    'mercadopago' => 'Pix (Mercado Pago)',
    'rapidapi' => 'RapidAPI Hub',
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
    'sandbox_rate_limited' => 'Cota sandbox atingida (429)',
    'unknown' => 'Outros',
];
$paymentSourceKeys = array_keys($paymentSourceLabels);
$failureCategoryKeys = array_keys($failureCategoryLabels);

/**
 * Calcula indicadores operacionais recentes (Hoje vs. Ontem + janela de 1h/6h/24h nos recent_events).
 */
function grid402_admin_recent_pulse(array $metrics): array {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $todayDt = new DateTimeImmutable('now', $tz);
    $todayKey = $todayDt->format('Y-m-d');
    $yesterdayKey = $todayDt->modify('-1 day')->format('Y-m-d');

    $daily = is_array($metrics['daily'] ?? null) ? $metrics['daily'] : [];
    $todayRow = is_array($daily[$todayKey] ?? null) ? $daily[$todayKey] : [];
    $yesterdayRow = is_array($daily[$yesterdayKey] ?? null) ? $daily[$yesterdayKey] : [];

    $events = is_array($metrics['recent_events'] ?? null) ? $metrics['recent_events'] : [];
    $nowTs = time();
    $last1h = 0;
    $last6h = 0;
    $last24h = 0;
    $challengeClean = 0;
    $paymentFailures = 0;
    $sandboxCount = 0;
    $freeCount = 0;
    $paidCount = 0;
    $errorNon402Count = 0;
    $recentServices = [];

    foreach ($events as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $tsStr = (string) ($ev['timestamp'] ?? '');
        $evTs = $tsStr !== '' ? strtotime($tsStr) : false;
        if ($evTs !== false) {
            $age = $nowTs - (int) $evTs;
            if ($age <= 3600) {
                $last1h++;
            }
            if ($age <= 21600) {
                $last6h++;
            }
            if ($age <= 86400) {
                $last24h++;
            }
        }

        $status = (int) ($ev['status'] ?? 200);
        $type = (string) ($ev['type'] ?? '');
        $failureCat = $ev['failure_category'] ?? null;
        $svc = (string) ($ev['service'] ?? 'unknown');
        if ($svc !== '') {
            $recentServices[$svc] = ((int) ($recentServices[$svc] ?? 0)) + 1;
        }

        if ($type === 'paid' || !empty($ev['is_paid'])) {
            $paidCount++;
        } elseif ($type === 'sandbox') {
            $sandboxCount++;
        } elseif ($status === 402) {
            if (!empty($failureCat)) {
                $paymentFailures++;
            } else {
                $challengeClean++;
            }
        } elseif ($status >= 400) {
            $errorNon402Count++;
            if (!empty($failureCat)) {
                $paymentFailures++;
            }
        } else {
            $freeCount++;
        }
    }

    arsort($recentServices);
    $topRecentServices = [];
    foreach (array_slice($recentServices, 0, 6, true) as $svcName => $cnt) {
        $topRecentServices[] = ['service' => (string) $svcName, 'count' => (int) $cnt];
    }

    return [
        'today_date' => $todayDt->format('d/m'),
        'yesterday_date' => $todayDt->modify('-1 day')->format('d/m'),
        'today_requests' => (int) ($todayRow['requests'] ?? 0),
        'today_page_views' => (int) ($todayRow['page_views'] ?? 0),
        'today_paid' => (int) ($todayRow['paid'] ?? 0),
        'yesterday_requests' => (int) ($yesterdayRow['requests'] ?? 0),
        'yesterday_page_views' => (int) ($yesterdayRow['page_views'] ?? 0),
        'yesterday_paid' => (int) ($yesterdayRow['paid'] ?? 0),
        'buffer_total' => count($events),
        'last_1h_count' => $last1h,
        'last_6h_count' => $last6h,
        'last_24h_count' => $last24h,
        'challenge_402_clean' => $challengeClean,
        'payment_failures' => $paymentFailures,
        'sandbox_count' => $sandboxCount,
        'free_count' => $freeCount,
        'paid_count' => $paidCount,
        'error_non_402_count' => $errorNon402Count,
        'top_recent_services' => $topRecentServices,
    ];
}

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
    $csvScope = strtolower(trim((string) ($_GET['scope'] ?? 'recent')));
    $events = $metrics['recent_events'] ?? [];

    if ($csvScope === 'paid') {
        // Exportacao historica de todas as transacoes pagas a partir do requests.jsonl (nao afetada por rajadas no buffer de 100)
        $paidSourcesList = ['facilitator', 'tx_hash', 'pass', 'rapidapi', 'mercadopago', 'web3_topup', 'solana_spl'];
        $historicalPaid = [];
        $logPath = function_exists('grid402_get_request_log_path') ? grid402_get_request_log_path() : (GRID402_PRIVATE_DIR . '/requests.jsonl');
        $priceMap = [
            'dossier' => 0.0500, 'compliance' => 0.0300, 'kyb-audit' => 0.0250, 'ofac' => 0.0200, 'lei' => 0.0100,
            'cnpj' => 0.0100, 'domain-audit' => 0.0150, 'market-snapshot' => 0.0150, 'sanctions' => 0.0100,
            'simples' => 0.0100, 'pass' => 2.0000, 'pass-emitir' => 2.0000, 'extract' => 0.0050, 'email-verify' => 0.0050,
            'notarize' => 0.0050, 'forex' => 0.0050, 'crypto' => 0.0050, 'indicators' => 0.0050, 'holidays' => 0.0050,
            'workdays' => 0.0050, 'global-markets' => 0.0050, 'dns' => 0.0050, 'ssl' => 0.0050, 'asn' => 0.0050, 'cep' => 0.0050,
        ];
        if (is_file($logPath) && is_readable($logPath)) {
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach (array_reverse($lines) as $ln) {
                    $row = @json_decode($ln, true);
                    if (!is_array($row)) continue;
                    $src = strtolower((string) ($row['payment_source'] ?? ''));
                    if ((int) ($row['status'] ?? 0) !== 200) continue;
                    if (!in_array($src, $paidSourcesList, true) && !str_starts_with($src, 'evm_') && !str_starts_with($src, 'multichain_')) continue;
                    $svc = strtolower((string) ($row['service'] ?? ''));
                    $isZeroRec = in_array($src, ['pass', 'rapidapi'], true);
                    $rowPrice = isset($row['price_usdc']) && is_numeric($row['price_usdc'])
                        ? (float) $row['price_usdc']
                        : (float) ($priceMap[$svc] ?? 0.0050);
                    $historicalPaid[] = [
                        'timestamp' => (string) ($row['ts'] ?? ''),
                        'service' => $svc,
                        'status' => 200,
                        'type' => 'paid',
                        'is_paid' => true,
                        'price_usdc' => $isZeroRec ? 0.0 : $rowPrice,
                        'tx_hash' => (string) ($row['request_id'] ?? ''),
                        'payment_source' => $src,
                        'failure_category' => '',
                        'settlement_tx' => '',
                        'ip_masked' => (string) ($row['ip_hmac'] ?? ''),
                    ];
                }
            }
        }
        $events = $historicalPaid;
    }

    $filename = ($csvScope === 'paid' ? 'grid402_pagas_historico_' : 'grid402_telemetria_') . grid402_metrics_today() . '.csv';
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

// 4.2. Endpoint Growth Engine (/admin?api=growth)
if ($apiParam === 'growth') {
    if (!$isLoggedIn) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Sessão administrativa expirada ou inválida.'], JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode(grid402_growth_admin_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4.3. Endpoint de Polling JSON (/admin?api=stats)
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
    $runtimeStatus = grid402_admin_runtime_status();
    $growthMetrics = function_exists('grid402_growth_metrics') ? grid402_growth_metrics() : [];
    $todayVsYesterday = grid402_admin_today_vs_yesterday($metrics, $growthMetrics, $usdRate);
    $operationalHealth = grid402_admin_operational_health($metrics, $analytics, $runtimeStatus, $wallet);
    $alertsBundle = grid402_admin_evaluate_alerts($metrics, $analytics, $operationalHealth, $wallet);
    try {
        grid402_dispatch_high_severity_alerts($alertsBundle['alerts'], 3600);
    } catch (Throwable $e) {
        // Falha de rede no Telegram nunca interrompe o polling do painel
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $revenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
    $today = grid402_metrics_today();
    $todayUsdc = (float)($metrics['daily'][$today]['usdc'] ?? 0.0);
    $totalPageViews = (int)($metrics['total_page_views'] ?? 0);
    $todayPageViews = (int)($metrics['daily'][$today]['page_views'] ?? 0);

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
        'total_page_views' => $totalPageViews,
        'today_page_views' => $todayPageViews,
        'total_paid_requests' => (int)($metrics['total_paid_requests'] ?? 0),
        'total_sandbox_requests' => (int)($metrics['total_sandbox_requests'] ?? 0),
        'total_revenue_usdc' => $revenueUsdc,
        'total_revenue_brl' => round($revenueUsdc * $usdRate, 2),
        'today_revenue_usdc' => $todayUsdc,
        'today_revenue_brl' => round($todayUsdc * $usdRate, 2),
        'arpu_usdc' => $analytics['arpu_usdc'],
        'arpu_brl' => $analytics['arpu_brl'],
        'technical_success_rate' => $analytics['technical_success_rate'],
        'http_200_count' => $analytics['http_200_count'] ?? 0,
        'http_200_rate' => $analytics['http_200_rate'] ?? 100.0,
        'error_5xx_count' => $analytics['error_5xx_count'] ?? 0,
        'latency_p50_ms' => $analytics['latency_p50_ms'] ?? 0.0,
        'latency_p95_ms' => $analytics['latency_p95_ms'] ?? 0.0,
        'latency_samples_count' => $analytics['latency_samples_count'] ?? 0,
        'sandbox_rate' => $analytics['sandbox_rate'],
        'usd_rate' => $usdRate,
        'wallet_balances' => $wallet,
        'passes_stats' => $analytics['passes_stats'] ?? [],
        'facilitator_status' => $runtimeStatus['facilitator'],
        'collector_status' => $runtimeStatus['collector'],
        'telegram_status' => $runtimeStatus['telegram'],
        'rapidapi_status' => $runtimeStatus['rapidapi'],
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
        'recent_pulse' => grid402_admin_recent_pulse($metrics),
        'recent_events' => $metrics['recent_events'] ?? [],
        'recent_payment_failures' => function_exists('grid402_read_recent_payment_failures') ? grid402_read_recent_payment_failures(25) : [],
        'today_vs_yesterday' => $todayVsYesterday,
        'operational_health' => $operationalHealth,
        'alerts' => $alertsBundle['alerts'],
        'alerts_summary' => $alertsBundle['summary'],
        'paid_requests_per_service' => $growthMetrics['paid_requests_per_service'] ?? [],
        'growth_metrics' => $growthMetrics,
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
        <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=2">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=2">
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

            <?php
            $pendingRecovery = is_array($_SESSION['admin_recovery_pending'] ?? null) ? $_SESSION['admin_recovery_pending'] : null;
            $pending2fa = is_array($_SESSION['admin_2fa_pending'] ?? null) ? $_SESSION['admin_2fa_pending'] : null;
            $isForgotPasswordPrompt = (!$pendingRecovery && !$pending2fa && $hasSetup && $action === 'forgot_password');
            ?>
            <?php if ($pendingRecovery): ?>
                <h1>Recuperar Acesso</h1>
                <p class="sub">Enviamos um código de segurança de 6 dígitos para o seu Telegram (@GRIG402BOT). Digite o código e cadastre sua nova senha.</p>
            <?php elseif ($isForgotPasswordPrompt): ?>
                <h1>Recuperação via Telegram</h1>
                <p class="sub">Um código de autenticação único de 6 dígitos será despachado imediatamente para o bot oficial (@GRIG402BOT).</p>
            <?php elseif ($pending2fa): ?>
                <h1>Verificação 2FA (Telegram)</h1>
                <p class="sub">Enviamos um código de 6 dígitos para o seu Telegram oficial (válido por 5 minutos).</p>
            <?php elseif (!$hasSetup): ?>
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
            <?php if ($successMsg): ?>
                <div class="alert-info"><?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($pendingRecovery): ?>
                <form id="admin-recovery-form" method="POST" action="/admin?action=confirm_recovery">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label for="recovery_otp">Código de Recuperação (Telegram)</label>
                        <input type="text" id="recovery_otp" name="recovery_otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autocomplete="one-time-code" autofocus style="font-family: var(--font-mono); font-size: 1.35rem; letter-spacing: 0.35rem; text-align: center;">
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nova Senha</label>
                        <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="form-group">
                        <label for="new_password_confirm">Confirme a Nova Senha</label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password" minlength="8" placeholder="Repita a nova senha">
                    </div>
                    <button type="submit" class="btn">Redefinir Senha e Entrar</button>
                </form>
                <form method="POST" action="/admin?action=resend_recovery" style="margin-top: 0.75rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn" style="background: transparent; border: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem; padding: 0.55rem;">Reenviar código no Telegram</button>
                </form>
                <a href="/admin?action=cancel_recovery" class="back-link" style="margin-top: 1rem;">← Cancelar e voltar ao login</a>
            <?php elseif ($isForgotPasswordPrompt): ?>
                <form method="POST" action="/admin?action=forgot_password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn">Despachar Código para o Telegram</button>
                </form>
                <a href="/admin" class="back-link" style="margin-top: 1rem;">← Voltar para o login</a>
            <?php elseif ($pending2fa): ?>
                <form id="admin-2fa-form" method="POST" action="/admin?action=verify_2fa">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label for="otp_code">Código OTP de 6 dígitos</label>
                        <input type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autocomplete="one-time-code" autofocus style="font-family: var(--font-mono); font-size: 1.35rem; letter-spacing: 0.35rem; text-align: center;">
                    </div>
                    <button type="submit" class="btn">Verificar e Entrar</button>
                </form>
                <form method="POST" action="/admin?action=resend_2fa" style="margin-top: 0.75rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn" style="background: transparent; border: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem; padding: 0.55rem;">Reenviar código no Telegram</button>
                </form>
                <a href="/admin?action=cancel_2fa" class="back-link" style="margin-top: 1rem;">← Cancelar e voltar ao login</a>
            <?php else: ?>
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
                <?php if ($hasSetup): ?>
                    <form method="POST" action="/admin?action=forgot_password" style="margin-top: 1.25rem; text-align: center;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" style="background: none; border: none; color: var(--accent); font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; padding: 0.25rem 0.5rem; text-decoration: none;">
                            <span>🔑</span> Esqueci minha senha (recuperar via Telegram)
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <a href="/" class="back-link">← Voltar para o portal público</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Visualização do Dashboard Autenticado
$currentAuthRaw = @file_get_contents($authFile);
$currentAuthData = $currentAuthRaw ? (@json_decode($currentAuthRaw, true) ?: []) : [];
$currentAdminUser = (string)($_SESSION['admin_user'] ?? ($currentAuthData['username'] ?? 'admin'));
$twoFactorEnabled = array_key_exists('two_factor_telegram', $currentAuthData) ? (bool)$currentAuthData['two_factor_telegram'] : true;
$twoFactorActive = grid402_is_telegram_2fa_required($currentAuthData);

$metrics = grid402_get_metrics();
$forex = grid402_service_forex();
$usdRate = (float)($forex['rates']['USD']['bid'] ?? 5.50);
$wallet = grid402_get_oracle_wallet_balances();
$oracleConfigured = defined('GRID402_ORACLE_PRIVATE_KEY') && !empty(GRID402_ORACLE_PRIVATE_KEY);
$analytics = grid402_compute_admin_analytics($metrics, $usdRate);
$runtimeStatus = grid402_admin_runtime_status();
$facStatus = $runtimeStatus['facilitator'];
$colStatus = $runtimeStatus['collector'];
$tgStatus = $runtimeStatus['telegram'];
$rapidStatus = $runtimeStatus['rapidapi'];

$revenueUsdc = (float)($metrics['total_revenue_usdc'] ?? 0.0);
$revenueBrl = round($revenueUsdc * $usdRate, 2);

$today = grid402_metrics_today();
$todayUsdc = (float)($metrics['daily'][$today]['usdc'] ?? 0.0);
$todayBrl = round($todayUsdc * $usdRate, 2);

$arpuUsdc = $analytics['arpu_usdc'];
$arpuBrl = $analytics['arpu_brl'];
$technicalSuccessRate = $analytics['technical_success_rate'];
$latencyP95Ms = (float) ($analytics['latency_p95_ms'] ?? 0.0);
$sandboxRate = $analytics['sandbox_rate'];
$passesStats = $analytics['passes_stats'] ?? ['active_passes' => 0, 'remaining_credits' => 0];
$dailyHistory14d = $analytics['daily_history_14d'] ?? [];
$topConsumers = $analytics['top_consumers'] ?? [];
$serviceRevenue = $analytics['service_revenue'] ?? [];
$recentPulse = grid402_admin_recent_pulse($metrics);
$recentPaymentFailures = function_exists('grid402_read_recent_payment_failures') ? grid402_read_recent_payment_failures(25) : [];

$totalPageViews = (int)($metrics['total_page_views'] ?? 0);
$todayPageViews = (int)($metrics['daily'][$today]['page_views'] ?? 0);

$totalReqs = (int)($metrics['total_requests'] ?? 0);
$paidReqs = (int)($metrics['total_paid_requests'] ?? 0);
$sandboxReqs = (int)($metrics['total_sandbox_requests'] ?? 0);
$reqs402 = (int)($metrics['status_counts']['402'] ?? 0);
$conversionRate = ($reqs402 + $paidReqs > 0) ? round(($paidReqs / ($reqs402 + $paidReqs)) * 100, 1) : 0;
$growthMetrics = function_exists('grid402_growth_metrics') ? grid402_growth_metrics() : [];
$todayVsYesterday = grid402_admin_today_vs_yesterday($metrics, $growthMetrics, $usdRate);
$operationalHealth = grid402_admin_operational_health($metrics, $analytics, $runtimeStatus, $wallet);
$alertsBundle = grid402_admin_evaluate_alerts($metrics, $analytics, $operationalHealth, $wallet);

require __DIR__ . '/includes/admin_cockpit_view.php';
