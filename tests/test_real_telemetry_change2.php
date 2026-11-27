<?php
/**
 * Suite de Testes E2E e Unitários — OpenSpec Change 2: real-telemetry-admin-and-discovery-hardening (RF12-RF18)
 */

declare(strict_types=1);

$tempStorage = sys_get_temp_dir() . '/grid402_change2_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
mkdir($tempStorage, 0775, true);
putenv("GRID402_STORAGE_DIR={$tempStorage}");
$_ENV['GRID402_STORAGE_DIR'] = $tempStorage;
putenv('GRID402_RAPIDAPI_PROXY_SECRET=test-rapid-secret-change2-1234');
$_ENV['GRID402_RAPIDAPI_PROXY_SECRET'] = 'test-rapid-secret-change2-1234';
putenv('GRID402_DISABLE_TELEGRAM=1');
$_ENV['GRID402_DISABLE_TELEGRAM'] = '1';

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/metrics.php';
require_once __DIR__ . '/../public_html/logger.php';
require_once __DIR__ . '/../public_html/growth.php';
require_once __DIR__ . '/../public_html/x402.php';
require_once __DIR__ . '/../public_html/services/admin_insights.php';
require_once __DIR__ . '/helpers_server.php';

echo "=== TESTES CHANGE 2: TELEMETRIA REAL, ADMIN & DESCOBERTA (RF12-RF18) ===\n";

$passed = 0;
function check2(bool $cond, string $label): void {
    global $passed;
    if (!$cond) {
        fwrite(STDERR, "  [FAIL] {$label}\n");
        exit(1);
    }
    $passed++;
    echo "  [PASS] {$label}\n";
}

function rrmdir_change2(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $dir . '/' . $item;
        if (is_dir($p)) {
            rrmdir_change2($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}

register_shutdown_function(static function () use ($tempStorage): void {
    rrmdir_change2($tempStorage);
});

// ---------------------------------------------------------
// RF12: Bot UAs, Admin Cookie, Markdown, 429 vs Sandbox, Crawler split
// ---------------------------------------------------------
echo "\n[1] RF12: Filtros de bot, cookie admin, markdown, 429 e mcp_sandbox...\n";
$botUas = [
    'Mozilla/5.0 (compatible; AhrefsBot/7.0)',
    'Mozilla/5.0 AppleWebKit/537.36 (compatible; GPTBot/1.1)',
    'PerplexityBot/1.0',
    'Go-http-client/2.0',
    'undici',
    'node-fetch/1.0',
    'axios/1.6.8',
    'python-httpx/0.27.0',
    'Python/3.12 aiohttp/3.9.5',
    'Scrapy/2.11 (+https://scrapy.org)',
    'Java/21.0.1',
    'okhttp/4.12.0',
    'GuzzleHttp/7',
    'ChatGPT-User',
    'Claude-Web',
    'Meta-ExternalAgent',
    'Playwright/1.44.0',
    'Puppeteer',
];
foreach ($botUas as $bUa) {
    check2(grid402_is_bot_user_agent($bUa) === true, "Detecta bot/crawler UA: {$bUa}");
}
check2(grid402_is_bot_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0 Safari/537.36') === false, 'Permite Chrome humano');

$_COOKIE['g402_admin_auth'] = '1';
grid402_record_page_visit('/', '198.51.100.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0', 'text/html');
unset($_COOKIE['g402_admin_auth']);
$mAfterAdmin = grid402_get_metrics();
check2((int)($mAfterAdmin['total_page_views'] ?? 0) === 0, 'Cookie g402_admin_auth=1 não incrementa page views');

grid402_record_page_visit('/', '198.51.100.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0', 'text/markdown, text/html');
$mAfterMd = grid402_get_metrics();
check2((int)($mAfterMd['total_page_views'] ?? 0) === 0, 'Accept: text/markdown não incrementa page views');

grid402_record_page_visit('/', '198.51.100.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0', 'text/html');
$mAfterHuman = grid402_get_metrics();
check2((int)($mAfterHuman['total_page_views'] ?? 0) === 1, 'Browser humano sem cookie admin incrementa page_views para 1');

// 1 sandbox 200 (mcp_sandbox) + 3 bloqueios HTTP 429 + 1 crawler x402
grid402_record_telemetry('cep', 200, false, 'mcp_sandbox', '198.51.100.11', 0.005, 'sandbox', ['payment_source' => 'mcp_sandbox']);
for ($k429 = 0; $k429 < 3; $k429++) {
    grid402_record_telemetry('cep', 429, false, null, '198.51.100.10', 0.005, 'rate_limited_429', ['failure_category' => 'sandbox_rate_limited']);
}
grid402_record_telemetry('cep', 402, false, null, '198.51.100.12', 0.005, 'challenge_402', ['is_crawler' => true]);

$mTel = grid402_get_metrics();
check2((int)($mTel['status_counts']['429'] ?? 0) === 3, 'HTTP 429 registrado 3x em status_counts[429]');
check2((int)($mTel['failure_categories']['sandbox_rate_limited'] ?? 0) === 3, 'failure_categories[sandbox_rate_limited] === 3');
check2((int)($mTel['total_sandbox_requests'] ?? 0) === 1, 'total_sandbox_requests === 1 (429s não inflaram sandbox)');
check2((int)($mTel['payment_sources']['sandbox'] ?? 0) === 0, 'HTTP 429 não contou como payment_sources[sandbox]');
check2((int)($mTel['payment_sources']['mcp_sandbox'] ?? 0) === 1, 'Auto-trial MCP 200 contou em payment_sources[mcp_sandbox]');
check2((int)($mTel['total_crawler_requests'] ?? 0) === 1, 'Crawler x402 incrementou total_crawler_requests');
$an = grid402_compute_admin_analytics($mTel);
check2((int)($an['real_api_requests'] ?? 0) === ((int)$mTel['total_requests'] - 1), 'real_api_requests exclui crawlers de diretórios');

// ---------------------------------------------------------
// RF13: Growth Wallet Connect + Rate Limit + Perfis M2M vs Wallets
// ---------------------------------------------------------
echo "\n[2] RF13: Wallet connect dedicado e separação M2M vs Wallets...\n";
grid402_growth_event('sandbox_use', ['ip' => '203.0.113.77', 'proof' => 'm2m_probe_1', 'service' => 'cnpj', 'type' => 'sandbox']);
grid402_growth_event('challenge_402', ['ip' => '203.0.113.78', 'proof' => 'm2m_probe_2', 'service' => 'cep', 'type' => 'x402']);
$gmBeforeWallet = grid402_growth_metrics();
check2((int)($gmBeforeWallet['wallets_connected'] ?? -1) === 0, 'Eventos sandbox/429 sem carteira mantêm wallets_connected === 0');
check2((int)($gmBeforeWallet['identified_profiles'] ?? 0) === 2, 'Eventos M2M criam 2 identified_profiles');

$badWallet = grid402_growth_record_wallet_connect('not-a-wallet', '198.51.100.20', 'playground');
check2(($badWallet['ok'] ?? true) === false && ($badWallet['code'] ?? 0) === 400, 'Rejeita endereço de carteira inválido com 400');

$zeroWallet = grid402_growth_record_wallet_connect('0x0000000000000000000000000000000000000000', '198.51.100.20', 'playground');
check2(($zeroWallet['ok'] ?? true) === false && ($zeroWallet['code'] ?? 0) === 400, 'Rejeita zero-address 0x000...000');

$validAddr = '0xAb5801a7D398351b8bE11C439e05C5B3259aeC9B';
$w1 = grid402_growth_record_wallet_connect($validAddr, '198.51.100.20', 'playground');
$w2 = grid402_growth_record_wallet_connect(strtolower($validAddr), '198.51.100.21', 'pass');
check2(($w1['ok'] ?? false) === true && empty($w1['already_connected']), 'Registra primeira wallet conectada');
check2(($w2['ok'] ?? false) === true && !empty($w2['already_connected']), 'Deduplica mesma wallet em chamadas repetidas');

$gm = grid402_growth_metrics();
check2((int)($gm['wallets_connected'] ?? 0) === 1, 'wallets_connected conta apenas carteiras Web3 verificadas (1)');
check2((int)($gm['identified_profiles'] ?? 0) >= 3, 'identified_profiles inclui IPs M2M + wallet verificada');

$hitLimit = false;
for ($i = 0; $i < 12; $i++) {
    $resRl = grid402_growth_record_wallet_connect('0x11111111111111111111111111111111111111' . sprintf('%02d', $i), '198.51.100.30', 'playground');
    if (($resRl['code'] ?? 0) === 429) {
        $hitLimit = true;
        break;
    }
}
check2($hitLimit === true, 'Rate limit de 10/hora por IP bloqueia 11ª chamada em wallet-connect');

// ---------------------------------------------------------
// RF14: payment_failures.json ring buffer (>300KB requests.jsonl) + sanitização
// ---------------------------------------------------------
echo "\n[3] RF14: Ring buffer payment_failures.json (500) imune a floods 402 e redação...\n";
$redacted = grid402_sanitize_log_text('ERR APP_USR-1234567890123456-112621-abcdef1234567890 TEST-1234567890123456-112621-abcdef1234567890 Bearer secret.jwt.token dev@example.com pass_g402_abcd1234_0011223344556677 ' . str_repeat('a', 64));
check2(!str_contains($redacted, '1234567890123456') && str_contains($redacted, '[MP_TOKEN_REDACTED]'), 'Redige APP_USR- e TEST- do Mercado Pago');
check2(!str_contains($redacted, 'secret.jwt.token') && !str_contains($redacted, 'dev@example.com'), 'Redige Bearer tokens e emails');
check2(!str_contains($redacted, 'pass_g402_abcd1234'), 'Redige Session Pass tokens');

grid402_record_payment_failure(['service' => 'cep', 'status' => 402, 'failure_category' => 'challenge_402', 'error' => 'PaymentRequired']);
grid402_record_payment_failure(['service' => 'cep', 'status' => 429, 'failure_category' => 'sandbox_rate_limited', 'error' => 'RateLimit']);
check2(count(grid402_read_recent_payment_failures(25)) === 0, 'Ruído 402 vazio e 429 sandbox NÃO entra em payment_failures.json');

for ($fIdx = 1; $fIdx <= 505; $fIdx++) {
    grid402_record_payment_failure([
        'service' => 'cep',
        'status' => 400,
        'failure_category' => 'invalid_proof',
        'payment_source' => 'facilitator',
        'error' => "invalid_proof #{$fIdx}",
        'ip' => '192.0.2.50',
    ]);
}
$failuresRaw = json_decode((string) file_get_contents(grid402_get_payment_failures_path()), true);
check2(is_array($failuresRaw) && count($failuresRaw) === 500, 'Ring buffer payment_failures.json limitado a exatamente 500 eventos');

// Grava > 320 KB de desafios 402 em requests.jsonl e confirma que payment_failures.json continua visível
$floodLine = json_encode([
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'service' => 'cep',
    'status' => 402,
    'payment_source' => 'none',
    'error' => 'PaymentRequired ' . str_repeat('X', 200),
]) . "\n";
$floodLogPath = grid402_get_request_log_path();
file_put_contents($floodLogPath, str_repeat($floodLine, 1200));
clearstatcache();
check2(filesize($floodLogPath) > 300000, 'requests.jsonl preenchido com > 300 KB de desafios 402');

$recent25 = grid402_read_recent_payment_failures(25);
check2(count($recent25) === 25 && str_contains((string)$recent25[0]['error'], '#505'), 'Leitura retorna 25 falhas reais intactas mesmo após > 300 KB de 402 em requests.jsonl');

// ---------------------------------------------------------
// RF15: RapidAPI dossier cap default = 15 + RapidApiEndpointCapExceeded
// ---------------------------------------------------------
echo "\n[4] RF15: Cap diário RapidAPI para dossier (15/dia)...\n";
check2((int) GRID402_RAPIDAPI_DOSSIER_DAILY_CAP === 15, 'Default de GRID402_RAPIDAPI_DOSSIER_DAILY_CAP é 15');
$_SERVER['HTTP_X_RAPIDAPI_USER'] = 'rapid_user_change2';
for ($r = 1; $r <= 15; $r++) {
    $capRes = grid402_check_rapidapi_endpoint_guard('dossier');
    check2(($capRes['allowed'] ?? false) === true, "RapidAPI chamada #{$r}/15 permitida");
}
$cap16 = grid402_check_rapidapi_endpoint_guard('dossier');
unset($_SERVER['HTTP_X_RAPIDAPI_USER']);
check2(($cap16['allowed'] ?? true) === false && ($cap16['error'] ?? '') === 'RapidApiEndpointCapExceeded', '16ª chamada RapidAPI bloqueada com RapidApiEndpointCapExceeded');

// ---------------------------------------------------------
// RF17: Alertas Mercado Pago no Admin Insights
// ---------------------------------------------------------
echo "\n[5] RF17: Alertas de Mercado Pago (unconfigured & gateway 5xx)...\n";
$alertsUnconf = grid402_admin_evaluate_alerts(
    ['failure_categories' => [], 'service_paid_counts' => []],
    ['error_5xx_count' => 0, 'technical_success_rate' => 100.0, 'passes_stats' => []],
    ['error_5xx_count' => 0, 'collector_stale' => false, 'burst_429_recent' => 0, 'mercadopago_configured' => false, 'mercadopago_sandbox' => false],
    []
);
$idsUnconf = array_column($alertsUnconf['alerts'], 'id');
check2(in_array('mercadopago_unconfigured', $idsUnconf, true), 'Emite alerta mercadopago_unconfigured (medium) quando sem token e fora sandbox');

$alertsGw = grid402_admin_evaluate_alerts(
    ['failure_categories' => ['mercadopago_gateway_error' => 3], 'service_paid_counts' => []],
    ['error_5xx_count' => 0, 'technical_success_rate' => 100.0, 'passes_stats' => []],
    ['error_5xx_count' => 0, 'collector_stale' => false, 'burst_429_recent' => 0, 'mercadopago_configured' => true, 'mercadopago_sandbox' => false],
    []
);
$idsGw = array_column($alertsGw['alerts'], 'id');
check2(in_array('mercadopago_gateway_error', $idsGw, true), 'Emite alerta mercadopago_gateway_error (high) quando há falhas >= 500');

// ---------------------------------------------------------
// Testes HTTP E2E via servidor embutido
// ---------------------------------------------------------
echo "\n[6] Testes E2E HTTP (teaser_data, wallet-connect, RapidAPI HTTP 429, /admin SSR & stats, /llms.txt)...\n";
// Garante credenciais admin no storage isolado antes de iniciar servidor HTTP
file_put_contents($tempStorage . '/admin_auth.json', json_encode([
    'username' => 'admin_test',
    'password_hash' => password_hash('SecretChange2!Pass', PASSWORD_BCRYPT),
    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
]));

$server = grid402_start_server([
    'router' => __DIR__ . '/router.php',
    'env' => [
        'GRID402_STORAGE_DIR' => $tempStorage,
        'GRID402_RAPIDAPI_PROXY_SECRET' => 'test-rapid-secret-change2-1234',
        'GRID402_DISABLE_TELEGRAM' => '1',
        'GRID402_ORACLE_PRIVATE_KEY' => '0x0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    ],
]);
$baseUrl = $server['base_url'];

$httpCall = static function (string $url, array $headers = [], ?string $body = null, string $cookieFile = '') : array {
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$respHeaders): int {
            $trimmed = trim($line);
            if ($trimmed !== '' && str_contains($trimmed, ':')) {
                [$k, $v] = explode(':', $trimmed, 2);
                $respHeaders[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cookieFile !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'headers' => $respHeaders, 'body' => $raw];
};

try {
    // RF16: cnpj sem teaser_data, dossier com teaser_data
    $rCnpj = $httpCall("{$baseUrl}/api/v1/cnpj?cnpj=00000000000191");
    $jCnpj = json_decode($rCnpj['body'], true);
    check2($rCnpj['status'] === 402 && isset($jCnpj['preview']) && !array_key_exists('teaser_data', $jCnpj), 'RF16: GET /api/v1/cnpj 402 retorna preview sem teaser_data');

    $rDos = $httpCall("{$baseUrl}/api/v1/dossier?cnpj=00000000000191");
    $jDos = json_decode($rDos['body'], true);
    check2($rDos['status'] === 402 && isset($jDos['preview'], $jDos['teaser_data']), 'RF16: GET /api/v1/dossier 402 mantém preview E teaser_data');

    // RF15 HTTP: 16ª chamada RapidAPI para dossier (com 15 já gravadas via grid402_check_rapidapi_endpoint_cap acima para rapid_user_change2)
    $rRapid16 = $httpCall("{$baseUrl}/api/v1/dossier?cnpj=00000000000191", [
        'X-RapidAPI-Proxy-Secret: test-rapid-secret-change2-1234',
        'X-RapidAPI-User: rapid_user_change2',
    ]);
    $jRapid16 = json_decode($rRapid16['body'], true);
    check2($rRapid16['status'] === 429 && ($jRapid16['error'] ?? '') === 'RapidApiEndpointCapExceeded', 'RF15 HTTP: 16ª requisição RapidAPI /api/v1/dossier retorna HTTP 429 RapidApiEndpointCapExceeded');

    // RF13 HTTP: POST /api/v1/growth/wallet-connect + deduplicação + 400 + 429
    $rWc = $httpCall("{$baseUrl}/api/v1/growth/wallet-connect", ['Content-Type: application/json'], json_encode(['address' => '0x9999999999999999999999999999999999999999', 'source' => 'playground']));
    $jWc = json_decode($rWc['body'], true);
    check2($rWc['status'] === 200 && !empty($jWc['ok']), 'RF13 HTTP: POST /api/v1/growth/wallet-connect retorna 200 ok');

    $rWcDup = $httpCall("{$baseUrl}/api/v1/growth/wallet-connect", ['Content-Type: application/json'], json_encode(['address' => '0x9999999999999999999999999999999999999999', 'source' => 'pass']));
    $jWcDup = json_decode($rWcDup['body'], true);
    check2($rWcDup['status'] === 200 && !empty($jWcDup['already_connected']), 'RF13 HTTP: deduplica wallet repetida');

    $rWcBad = $httpCall("{$baseUrl}/api/v1/growth/wallet-connect", ['Content-Type: application/json'], json_encode(['address' => 'invalid']));
    check2($rWcBad['status'] === 400, 'RF13 HTTP: POST /api/v1/growth/wallet-connect rejeita wallet inválida com 400');

    // RF14 HTTP: falha real gravada no payment_failures.json via X-Payment inválido
    $rBadProof = $httpCall("{$baseUrl}/api/v1/cep?cep=01310100", ['X-Payment: fake_invalid_proof_token_999']);
    check2($rBadProof['status'] === 400 || $rBadProof['status'] === 402, 'Falha real com X-Payment inválido respondida');

    // RF18: GET /llms.txt
    $rLlms = $httpCall("{$baseUrl}/llms.txt");
    check2($rLlms['status'] === 200 && str_contains(strtolower($rLlms['headers']['content-type'] ?? ''), 'text/plain'), 'RF18: GET /llms.txt retorna HTTP 200 text/plain');
    $htaccessRaw = (string) @file_get_contents(__DIR__ . '/../public_html/.htaccess');
    check2(str_contains($htaccessRaw, 'Access-Control-Allow-Origin "*"'), 'RF18: .htaccess configura Access-Control-Allow-Origin "*" para descoberta estática');
    check2(!str_contains($rLlms['body'], chr(38)) && stripos($rLlms['body'], 'solana') === false, 'RF18: /llms.txt não contém & nem Solana');
    check2(str_contains($rLlms['body'], 'MCPTRY50') && str_contains($rLlms['body'], '/mcp') && str_contains($rLlms['body'], 'Micro') && str_contains($rLlms['body'], 'RapidAPI') && str_contains($rLlms['body'], '/dossie-kyb'), 'RF18: /llms.txt contém MCPTRY50, /mcp, Micro, RapidAPI e /dossie-kyb');

    // RF12 + RF17: Login admin seta cookie g402_admin_auth e /admin SSR + ?api=stats expõe novos campos
    $cookieFile = $tempStorage . '/admin_cookies.txt';
    $rAdminGet = $httpCall("{$baseUrl}/admin", [], null, $cookieFile);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $rAdminGet['body'], $csrfMatch);
    $adminCsrf = $csrfMatch[1] ?? '';
    check2(strlen($adminCsrf) === 64, 'Formulário de login /admin expõe csrf_token');
    $loginPost = http_build_query(['csrf_token' => $adminCsrf, 'username' => 'admin_test', 'password' => 'SecretChange2!Pass']);
    $rLogin = $httpCall("{$baseUrl}/admin", ['Content-Type: application/x-www-form-urlencoded'], $loginPost, $cookieFile);
    $rDashHtml = $httpCall("{$baseUrl}/admin", [], null, $cookieFile);
    check2($rDashHtml['status'] === 200 && str_contains($rDashHtml['body'], 'Chamadas API Reais') && str_contains($rDashHtml['body'], 'perfis M2M') && str_contains($rDashHtml['body'], 'payment_failures.json') && str_contains($rDashHtml['body'], 'RapidAPI Hub fatura em USD'), 'RF17 SSR: HTML /admin contém novos rótulos de clareza operacional');

    $rStats = $httpCall("{$baseUrl}/admin?api=stats", [], null, $cookieFile);
    $jStats = json_decode($rStats['body'], true);
    check2($rStats['status'] === 200 && ($jStats['status'] ?? '') === 'success', 'RF17: /admin?api=stats autenticado retorna 200 success');
    check2(array_key_exists('real_api_requests', $jStats) && array_key_exists('total_crawler_requests', $jStats) && array_key_exists('mercadopago_status', $jStats), 'RF17: /admin?api=stats inclui real_api_requests, total_crawler_requests e mercadopago_status');
    check2(isset($jStats['payment_source_labels']['mcp_sandbox'], $jStats['failure_category_labels']['mercadopago_gateway_error']), 'RF17: labels incluem mcp_sandbox e mercadopago_gateway_error');

    $viewsBeforeAdminGet = (int)($jStats['total_page_views'] ?? 0);
    $rHomeAsAdmin = $httpCall("{$baseUrl}/", ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0.0.0'], null, $cookieFile);
    check2($rHomeAsAdmin['status'] === 200, 'Home responde 200 com cookie admin');
    $rStats2 = $httpCall("{$baseUrl}/admin?api=stats", [], null, $cookieFile);
    $jStats2 = json_decode($rStats2['body'], true);
    check2((int)($jStats2['total_page_views'] ?? -1) === $viewsBeforeAdminGet, 'RF12 HTTP: Sessão com cookie g402_admin_auth não incrementa total_page_views na Home');

    $rHomeMd = $httpCall("{$baseUrl}/", ['User-Agent: Mozilla/5.0 Chrome/131.0.0.0', 'Accept: text/markdown']);
    check2($rHomeMd['status'] === 200, 'Home Accept: text/markdown responde 200');
    $rStats3 = $httpCall("{$baseUrl}/admin?api=stats", [], null, $cookieFile);
    $jStats3 = json_decode($rStats3['body'], true);
    check2((int)($jStats3['total_page_views'] ?? -1) === $viewsBeforeAdminGet, 'RF12 HTTP: Home Accept: text/markdown não incrementa total_page_views');
} finally {
    grid402_stop_server($server);
}

echo "\n=== TODOS OS {$passed} TESTES CHANGE 2 PASSARAM COM SUCESSO! ===\n";
exit(0);
