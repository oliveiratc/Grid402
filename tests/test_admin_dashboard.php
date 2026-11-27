<?php
/**
 * Testes Unitários e de Integração: Painel Administrativo, Métricas e Faturamento
 */

declare(strict_types=1);

echo "=== Testando Módulo de Telemetria e Painel Administrativo ===\n";

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/metrics.php';
require_once __DIR__ . '/helpers_server.php';

// --- Teste 1: Anonimização de IP (LGPD) ---
echo "[1/5] Testando anonimização de IP (grid402_mask_ip)... ";
assert(grid402_mask_ip('189.40.122.50') === '189.40.xxx.xxx', 'IPv4 deve ter últimos dois octetos mascarados');
assert(grid402_mask_ip('10.0.0.1') === '10.0.xxx.xxx', 'IPv4 privado deve ser mascarado');
assert(str_starts_with(grid402_mask_ip('2001:0db8:85a3:0000:0000:8a2e:0370:7334'), '2001:0db8:'), 'IPv6 deve ser mascarado');
assert(grid402_mask_ip('127.0.0.1') === '127.0.xxx.xxx', 'Localhost deve ser mascarado');
echo "OK!\n";

// --- Teste 2: Gravação Atômica de Telemetria e Buffer Circular (100 eventos e Tipos de Tráfego) ---
echo "[2/5] Testando gravação atômica de telemetria, classificação de tipo e buffer circular de 100 eventos... ";
// Usa um arquivo de métricas temporário para testes
$backupMetricsFile = GRID402_FILE_METRICS . '.bak_test';
if (file_exists(GRID402_FILE_METRICS)) {
    copy(GRID402_FILE_METRICS, $backupMetricsFile);
}

// Reseta métricas para teste
grid402_atomic_write(GRID402_FILE_METRICS, grid402_default_metrics());

// Grava 1 consulta gratuita de câmbio
grid402_record_telemetry('forex', 200, false, null, '200.150.10.5');

// Grava 1 desafio 402 de DNS
grid402_record_telemetry('dns', 402, false, null, '189.40.50.2');

// Grava 1 bloqueio 403 de SSL (rate limit)
grid402_record_telemetry('ssl', 403, false, null, '189.40.50.2');

// Grava 1 consulta paga de DNS com tx_hash
$dummyTx = '0x' . bin2hex(random_bytes(32));
grid402_record_telemetry('dns', 200, true, $dummyTx, '189.40.50.2', 0.0050);

// Grava 1 consulta sandbox
grid402_record_telemetry('cep', 200, false, null, '127.0.0.1', 0.0, 'sandbox');

$m = grid402_get_metrics();
assert($m['total_requests'] === 5, 'Total de requisições deve ser 5');
assert($m['total_paid_requests'] === 1, 'Total de requisições pagas deve ser 1');
assert($m['total_revenue_usdc'] === 0.0050, 'Faturamento total em USDC deve ser 0.0050');
assert($m['service_counts']['forex'] === 1, 'Consultas de forex deve ser 1');
assert($m['service_counts']['dns'] === 2, 'Consultas de dns deve ser 2');
assert($m['service_counts']['ssl'] === 1, 'Consultas de ssl deve ser 1');
assert($m['service_counts']['cep'] === 1, 'Consultas de cep deve ser 1');
assert(count($m['service_counts']) >= 11, 'Deve conter catalogo de pelo menos 11 microservicos');
assert($m['status_counts']['200'] === 3, 'Status 200 deve ser 3');
assert($m['status_counts']['402'] === 1, 'Status 402 deve ser 1');
assert($m['status_counts']['403'] === 1, 'Status 403 deve ser 1');
assert(count($m['recent_events']) === 5, 'Deve haver 5 eventos recentes');

// Verificação de tipos de tráfego
assert($m['recent_events'][0]['type'] === 'sandbox', 'Primeiro evento deve ser classificado como sandbox');
assert($m['recent_events'][1]['type'] === 'paid', 'Segundo evento deve ser classificado como paid');
assert($m['recent_events'][1]['tx_hash'] === strtolower($dummyTx), 'Evento pago deve conter o hash da transação');
assert($m['recent_events'][2]['type'] === 'blocked_403', 'Terceiro evento deve ser classificado como blocked_403');
assert($m['recent_events'][3]['type'] === 'challenge_402', 'Quarto evento deve ser classificado como challenge_402');
assert($m['recent_events'][4]['type'] === 'free', 'Quinto evento deve ser classificado como free');
assert($m['recent_events'][1]['ip_masked'] === '189.40.xxx.xxx', 'IP deve estar anonimizado no evento');

// Teste de Buffer Circular (adicionar 110 eventos e garantir que no máximo 100 permanecem)
for ($i = 0; $i < 110; $i++) {
    grid402_record_telemetry('crypto', 200, false);
}
$mAfter = grid402_get_metrics();
assert(count($mAfter['recent_events']) === 100, 'Buffer circular deve reter exatamente 100 eventos recentes no maximo');

// --- Teste 2.1: Origem do pagamento e categoria de falha na telemetria ---
// Pagamento liquidado via facilitator (conta faturamento)
$settlementHash = '0x' . bin2hex(random_bytes(32));
grid402_record_telemetry('dns', 200, true, null, '189.40.50.9', 0.0050, 'paid', [
    'payment_source' => 'facilitator',
    'settlement_tx' => $settlementHash,
]);
// Pagamento em modo verify_only (nao fatura)
grid402_record_telemetry('cep', 200, true, null, '189.40.50.9', 0.0050, 'paid', [
    'payment_source' => 'facilitator_verify_only',
]);
// Falha de pagamento (registra apenas categoria de falha)
grid402_record_telemetry('dns', 402, false, null, '189.40.50.9', null, 'challenge_402', [
    'failure_category' => 'payment_rejected',
]);

$mMeta = grid402_get_metrics();
assert(($mMeta['payment_sources']['tx_hash'] ?? 0) === 1, 'Origem tx_hash deve contabilizar 1 evento');
assert(($mMeta['payment_sources']['sandbox'] ?? 0) === 1, 'Origem sandbox deve contabilizar 1 evento');
assert(($mMeta['payment_sources']['facilitator'] ?? 0) === 1, 'Origem facilitator deve contabilizar 1 evento');
assert(($mMeta['payment_sources']['facilitator_verify_only'] ?? 0) === 1, 'Origem facilitator_verify_only deve contabilizar 1 evento');
assert(($mMeta['failure_categories']['payment_rejected'] ?? 0) === 1, 'Falha payment_rejected deve contabilizar 1 evento');
assert($mMeta['total_paid_requests'] === 2, 'Somente pagamentos liquidados devem contar como pagos (tx_hash + facilitator)');
assert(abs($mMeta['total_revenue_usdc'] - 0.010) < 0.0000001, 'Receita deve somar apenas pagamentos liquidaveis (0.010 USDC)');

// Evento mais recente deve carregar a categoria de falha e nao uma origem de pagamento
assert($mMeta['recent_events'][0]['failure_category'] === 'payment_rejected', 'Evento de falha deve carregar failure_category');
assert($mMeta['recent_events'][0]['payment_source'] === null, 'Evento de falha nao deve carregar payment_source');
assert($mMeta['recent_events'][2]['payment_source'] === 'facilitator', 'Evento liquidado deve carregar payment_source facilitator');
assert($mMeta['recent_events'][2]['settlement_tx'] === strtolower($settlementHash), 'Evento liquidado deve carregar settlement_tx');
assert($mMeta['recent_events'][2]['is_paid'] === true, 'Evento liquidado deve ser is_paid true');
assert($mMeta['recent_events'][1]['is_paid'] === false, 'Evento verify_only nao deve ser is_paid');

// --- Teste 2.2: Indicadores Analíticos Avançados (grid402_compute_admin_analytics) ---
$analytics = grid402_compute_admin_analytics($mMeta, 5.50);
assert(isset($analytics['arpu_usdc']), 'Analytics deve conter arpu_usdc');
assert(abs($analytics['arpu_usdc'] - 0.0050) < 0.000001, 'ARPU com 0.001 USDC em 2 pagamentos deve ser 0.0050');
assert(isset($analytics['technical_success_rate']), 'Analytics deve conter technical_success_rate');
assert($analytics['technical_success_rate'] === 100.0, 'SLA sem erros 5xx deve ser 100.0 mesmo havendo desafios 402');
assert(($analytics['error_5xx_count'] ?? -1) === 0, 'Sem 5xx registrados, error_5xx_count deve ser 0');
assert(isset($analytics['http_200_rate']) && $analytics['http_200_rate'] < 100.0, 'http_200_rate deve refletir proporcao de 200 separados do SLA');
// Simula erro 503 para validar queda proporcional no SLA sem 5xx
$mWith5xx = $mMeta;
$mWith5xx['total_requests'] = 100;
$mWith5xx['status_counts']['503'] = 2;
$analyticsWith5xx = grid402_compute_admin_analytics($mWith5xx, 5.50);
assert($analyticsWith5xx['error_5xx_count'] === 2 && $analyticsWith5xx['technical_success_rate'] === 98.0, 'SLA deve refletir exatamente 98.0% quando ha 2 erros 5xx em 100 requests');

// Teste unitario de grid402_get_passes_stats() com schema credits_total + expires_epoch + legado
$passesBackupFile = GRID402_FILE_PASSES . '.bak_admin_test';
$hadPassesFile = file_exists(GRID402_FILE_PASSES);
if ($hadPassesFile) {
    copy(GRID402_FILE_PASSES, $passesBackupFile);
}
try {
    $futureEpoch = time() + 86400;
    $pastEpoch = time() - 86400;
    grid402_atomic_write(GRID402_FILE_PASSES, [
        'pass_v2_ativo' => [
            'pass_id' => 'pass_v2_ativo',
            'credit_scale' => 'v2_5000',
            'credits_total' => 400,
            'credits_remaining' => 250,
            'expires_epoch' => $futureEpoch,
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $futureEpoch),
        ],
        'pass_expirado' => [
            'pass_id' => 'pass_expirado',
            'credit_scale' => 'v2_5000',
            'credits_total' => 1000,
            'credits_remaining' => 4,
            'expires_epoch' => $pastEpoch,
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $pastEpoch),
        ],
    ]);
    $pStats = grid402_get_passes_stats();
    assert($pStats['total_issued'] === 2, 'total_issued deve ser 2');
    assert($pStats['active_passes'] === 1, 'active_passes deve contabilizar 1 passe ativo via expires_epoch');
    assert($pStats['total_credits'] === 1400, 'total_credits deve somar credits_total (1400)');
    assert($pStats['remaining_credits'] === 254, 'remaining_credits deve somar todos os restantes (254)');
    assert($pStats['active_remaining_credits'] === 250, 'active_remaining_credits deve isolar apenas ativos (250)');
} finally {
    if ($hadPassesFile && file_exists($passesBackupFile)) {
        rename($passesBackupFile, GRID402_FILE_PASSES);
    } else {
        @unlink(GRID402_FILE_PASSES);
        if (file_exists($passesBackupFile)) @unlink($passesBackupFile);
    }
}
assert(isset($analytics['sandbox_rate']), 'Analytics deve conter sandbox_rate');
assert(count($analytics['daily_history_14d']) === 14, 'daily_history_14d deve conter exatamente 14 dias continuos');
assert(is_array($analytics['top_consumers']), 'top_consumers deve ser array');
assert(count($analytics['top_consumers']) >= 1, 'top_consumers deve conter ao menos 1 requisitante');
assert(str_contains($analytics['top_consumers'][0]['ip_masked'], 'xxx.xxx'), 'IP do top_consumers deve estar anonimizado');
assert(isset($analytics['top_consumers'][0]['profile']), 'Top consumidor deve possuir perfil heuristico');
assert(is_array($analytics['service_revenue']), 'service_revenue deve ser array');
assert(count($analytics['service_revenue']) >= 11, 'service_revenue deve cobrir o catálogo de micro-serviços');
echo "OK!\n";

// --- Teste 3: Autenticação Segura com Bcrypt ---
echo "[3/5] Testando criptografia e validação de senha (Bcrypt) e persistencia RapidAPI... ";
$testPass = 'SenhaUltraSegura#2026';
$hash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 11]);
assert(password_verify($testPass, $hash) === true, 'Senha correta deve ser verificada');
assert(password_verify('SenhaIncorreta', $hash) === false, 'Senha incorreta deve ser recusada');

// Teste isolado de fallback rapidapi_config.json quando env GRID402_RAPIDAPI_PROXY_SECRET esta invalido/ausente
$rapidIsoDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_rapidapi_iso_' . bin2hex(random_bytes(4));
@mkdir($rapidIsoDir, 0775, true);
try {
    $isoScript = $rapidIsoDir . DIRECTORY_SEPARATOR . 'check.php';
    file_put_contents($isoScript, <<<'PHP'
<?php
define('GRID402_EXEC', true);
require_once $argv[1] . '/public_html/config.php';
$secret = 'rapidapi_isolated_secret_9876543210';
$secret2 = 'rapidapi_isolated_secret_alt_1234567';
assert(grid402_rapidapi_runtime_status()['configured'] === false, 'Sem env e sem arquivo deve ficar inativo');
assert(grid402_rapidapi_runtime_status()['count'] === 0, 'Count inicial deve ser 0');
assert(grid402_save_rapidapi_config($secret . ',' . $secret2) === true, 'Save multiplo rapidapi_config.json deve suceder');
$st = grid402_rapidapi_runtime_status();
assert($st['configured'] === true && $st['source'] === 'admin_file' && $st['count'] === 2, 'Status deve apontar source=admin_file e count=2');
$_SERVER['HTTP_X_RAPIDAPI_PROXY_SECRET'] = $secret;
assert(grid402_is_valid_rapidapi_proxy_request() === true, 'Primeiro segredo valido deve passar via admin_file');
$_SERVER['HTTP_X_RAPIDAPI_PROXY_SECRET'] = $secret2;
assert(grid402_is_valid_rapidapi_proxy_request() === true, 'Segundo segredo valido deve passar via admin_file');
$_SERVER['HTTP_X_RAPIDAPI_PROXY_SECRET'] = 'wrong_secret_1234567890';
assert(grid402_is_valid_rapidapi_proxy_request() === false, 'Terceiro segredo errado deve falhar');
assert(grid402_save_rapidapi_config('') === true, 'Clear deve remover rapidapi_config.json');
assert(grid402_rapidapi_runtime_status()['configured'] === false && grid402_rapidapi_runtime_status()['count'] === 0, 'Apos clear deve voltar para inativo e count=0');
echo "ISO_OK";
PHP);
    $isoEnv = $_ENV;
    foreach ($_SERVER as $k => $v) {
        if (is_string($k) && is_string($v) && !isset($isoEnv[$k])) {
            $isoEnv[$k] = $v;
        }
    }
    $isoEnv['GRID402_STORAGE_DIR'] = $rapidIsoDir;
    $isoEnv['GRID402_RAPIDAPI_PROXY_SECRET'] = 'disabled_short';
    $isoProc = proc_open([PHP_BINARY, $isoScript, dirname(__DIR__)], [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $isoPipes, dirname(__DIR__), $isoEnv);
    assert(is_resource($isoProc), 'Subprocesso PHP isolado de RapidAPI deve iniciar');
    $isoOut = stream_get_contents($isoPipes[1]);
    $isoErr = stream_get_contents($isoPipes[2]);
    fclose($isoPipes[1]);
    fclose($isoPipes[2]);
    $isoCode = proc_close($isoProc);
    assert($isoCode === 0 && trim((string) $isoOut) === 'ISO_OK', "Teste isolado RapidAPI falhou (code={$isoCode}): {$isoOut} {$isoErr}");
} finally {
    foreach (glob($rapidIsoDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($rapidIsoDir);
}

// Teste unitario de leitura de falhas recentes (Zero-PII + exclui PaymentRequired)
require_once __DIR__ . '/../public_html/logger.php';
$tmpLogFailures = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_failures_' . bin2hex(random_bytes(4)) . '.jsonl';
$tmpDedicatedFailures = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_payfail_' . bin2hex(random_bytes(4)) . '.json';
$prevLogEnv = getenv('GRID402_FILE_REQUEST_LOGS');
$prevDedEnv = getenv('GRID402_FILE_PAYMENT_FAILURES');
try {
    putenv('GRID402_FILE_REQUEST_LOGS=' . $tmpLogFailures);
    $_ENV['GRID402_FILE_REQUEST_LOGS'] = $tmpLogFailures;
    putenv('GRID402_FILE_PAYMENT_FAILURES=' . $tmpDedicatedFailures);
    $_ENV['GRID402_FILE_PAYMENT_FAILURES'] = $tmpDedicatedFailures;
    file_put_contents($tmpLogFailures, implode("\n", [
        json_encode(['ts' => '2026-11-21T10:00:00Z', 'request_id' => 'g402_1', 'service' => 'dns', 'status' => 402, 'payment_source' => 'none', 'error' => 'PaymentRequired', 'ip_hmac' => 'aaa']),
        json_encode(['ts' => '2026-11-21T10:01:00Z', 'request_id' => 'g402_2', 'service' => 'cnpj', 'status' => 402, 'payment_source' => 'facilitator', 'error' => 'PaymentFailed: invalid_exact_evm_insufficient_balance token pass_g402_secret123', 'ip_hmac' => 'bbb']),
        json_encode(['ts' => '2026-11-21T10:02:00Z', 'request_id' => 'g402_3', 'service' => 'forex', 'status' => 200, 'payment_source' => 'pass', 'error' => null, 'ip_hmac' => 'ccc']),
    ]) . "\n");
    $readFail = grid402_read_recent_payment_failures(10);
    assert(count($readFail) === 1, 'Deve retornar exatamente 1 falha real filtrando PaymentRequired e 200');
    assert(($readFail[0]['service'] ?? '') === 'cnpj' && str_contains((string) $readFail[0]['error'], 'pass_g402_[REDACTED]'), 'Deve sanitizar token Zero-PII');
} finally {
    if ($prevLogEnv !== false && $prevLogEnv !== '') {
        putenv('GRID402_FILE_REQUEST_LOGS=' . $prevLogEnv);
        $_ENV['GRID402_FILE_REQUEST_LOGS'] = $prevLogEnv;
    } else {
        putenv('GRID402_FILE_REQUEST_LOGS');
        unset($_ENV['GRID402_FILE_REQUEST_LOGS']);
    }
    if ($prevDedEnv !== false && $prevDedEnv !== '') {
        putenv('GRID402_FILE_PAYMENT_FAILURES=' . $prevDedEnv);
        $_ENV['GRID402_FILE_PAYMENT_FAILURES'] = $prevDedEnv;
    } else {
        putenv('GRID402_FILE_PAYMENT_FAILURES');
        unset($_ENV['GRID402_FILE_PAYMENT_FAILURES']);
    }
    @unlink($tmpLogFailures);
    @unlink($tmpDedicatedFailures);
}
echo "OK!\n";

// --- Teste 4 e 5: Ciclo HTTP do Painel Admin (Setup, Login, 401, CSV, Senha e Dashboard) ---
echo "[4/5] Subindo servidor web local para testar fluxos HTTP do /admin...\n";

// Porta livre, comando em formato array e teardown por arvore de processos:
// tudo centralizado em tests/helpers_server.php.
$server = grid402_start_server([
    'docroot' => __DIR__ . '/../public_html',
    'router' => __DIR__ . '/../public_html/router.php',
    'probe' => '/admin',
    'env' => [
        'GRID402_DISABLE_TELEGRAM' => '1',
        'GRID402_TEST_OTP_CAPTURE' => '1',
    ],
]);
$port = $server['port'];
$serverLog = $server['log_file'];

// Pre-aquece o cache de saldos on-chain: consultas RPC reais (3 URLs x 3s) estouram
// o CURLOPT_TIMEOUT de 4s do cliente em um servidor embutido single-thread.
$walletCacheFile = GRID402_PRIVATE_DIR . '/wallet_balance_cache.json';
$walletCacheBackup = $walletCacheFile . '.bak_test';
$hadWalletCache = file_exists($walletCacheFile);
if ($hadWalletCache) {
    copy($walletCacheFile, $walletCacheBackup);
}
if (!is_dir(GRID402_PRIVATE_DIR)) {
    @mkdir(GRID402_PRIVATE_DIR, 0755, true);
}
@file_put_contents($walletCacheFile, (string) json_encode([
    'oracle_address' => defined('GRID402_WALLET_ADDRESS') ? GRID402_WALLET_ADDRESS : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
    'usdc_balance' => 0.0,
    'eth_balance' => 0.0,
    'network' => 'Base Mainnet',
    'chain_id' => 8453,
    'cached_at' => time(),
    'rpc_operational' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

function curl_admin(string $url, array $options = []): array {
    $ch = curl_init($url);
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HEADER => true,
    ];
    curl_setopt_array($ch, $options + $defaults);
    $response = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);
    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

$testAuthBackup = GRID402_FILE_ADMIN_AUTH . '.bak_test';
if (file_exists(GRID402_FILE_ADMIN_AUTH)) {
    copy(GRID402_FILE_ADMIN_AUTH, $testAuthBackup);
    unlink(GRID402_FILE_ADMIN_AUTH);
}

try {
    // 4.1. Sem arquivo de auth -> deve exibir tela de setup inicial
    echo "  -> Testando tela de setup no primeiro acesso sem credenciais...\n";
    $cookieFile = sys_get_temp_dir() . '/grid402_admin_cookie.txt';
    if (file_exists($cookieFile)) @unlink($cookieFile);

    $setupResp = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($setupResp['code'] === 200, "Setup deve responder HTTP 200 (Recebeu: {$setupResp['code']})");
    assert(str_contains($setupResp['body'], 'Configurar Acesso'), 'Deve conter tela de Configurar Acesso');
    assert(str_contains($setupResp['body'], 'name="csrf_token"'), 'Deve conter campo de token CSRF no formulario');

    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $setupResp['body'], $csrfMatches);
    $setupCsrf = $csrfMatches[1] ?? '';
    assert(strlen($setupCsrf) === 64, 'Token CSRF de 64 caracteres hexadecimais deve estar presente');
    echo "     ✓ Setup inicial e token CSRF detectados com sucesso!\n";

    // 4.2. Requisição à API /admin?api=stats sem autenticação -> deve retornar HTTP 401
    echo "  -> Testando bloqueio da API /admin?api=stats sem sessão...\n";
    $unauthStats = curl_admin("http://127.0.0.1:{$port}/admin?api=stats");
    assert($unauthStats['code'] === 401, "API stats desautenticada deve retornar 401 (Recebeu: {$unauthStats['code']})");
    assert(str_contains($unauthStats['body'], 'Unauthorized'), 'Deve conter mensagem Unauthorized');
    echo "     ✓ Bloqueio HTTP 401 para requisição sem sessão validado!\n";

    // 4.2.1. Testar rejeição de setup sem CSRF token
    echo "  -> Testando rejeição de submissão de setup sem token CSRF...\n";
    $noCsrfPost = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => 'admin_test',
            'password' => 'Segredo#Forte2026',
            'password_confirm' => 'Segredo#Forte2026',
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert(str_contains($noCsrfPost['body'], 'CSRF'), 'Submissão sem CSRF deve ser recusada com alerta');
    echo "     ✓ Proteção contra requisições sem CSRF validada!\n";

    // 4.3. Submeter formulário de setup inicial COM CSRF token válido
    echo "  -> Cadastrando credenciais administrativas iniciais com token CSRF...\n";
    $postSetup = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => 'admin_test',
            'password' => 'Segredo#Forte2026',
            'password_confirm' => 'Segredo#Forte2026',
            'csrf_token' => $setupCsrf,
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($postSetup['code'] === 302 || $postSetup['code'] === 200, "Setup deve redirecionar após cadastro");
    assert(file_exists(GRID402_FILE_ADMIN_AUTH), "Arquivo admin_auth.json deve ter sido criado");
    echo "     ✓ Credenciais criadas e sessão iniciada com CSRF válido!\n";

    // 4.4. Acessar /admin?api=stats COM o cookie de sessão autenticado
    echo "  -> Consultando /admin?api=stats com sessão autenticada...\n";
    $authStats = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($authStats['code'] === 200, "API stats autenticada deve retornar 200");
    $statsJson = json_decode($authStats['body'], true);
    assert($statsJson['status'] === 'success', 'Status deve ser success');
    assert(isset($statsJson['total_revenue_usdc']), 'Deve conter total_revenue_usdc');
    assert(isset($statsJson['service_counts']), 'Deve conter service_counts');
    assert(count($statsJson['service_counts']) >= 11, 'Deve conter os 11 microservicos no snapshot');
    assert(isset($statsJson['recent_events']), 'Deve conter recent_events');
    assert(isset($statsJson['wallet_balances']), 'Deve conter wallet_balances');
    assert(isset($statsJson['oracle_configured']), 'Deve conter oracle_configured');
    assert(isset($statsJson['wallet_balances']['usdc_balance']), 'Deve conter usdc_balance');
    assert(isset($statsJson['wallet_balances']['eth_balance']), 'Deve conter eth_balance');
    assert(isset($statsJson['payment_sources']) && is_array($statsJson['payment_sources']), 'Deve conter payment_sources');
    assert(isset($statsJson['failure_categories']) && is_array($statsJson['failure_categories']), 'Deve conter failure_categories');
    assert(isset($statsJson['payment_source_labels']['tx_hash']), 'Deve conter rotulos de origem do pagamento');
    assert(isset($statsJson['failure_category_labels']['payment_rejected']), 'Deve conter rotulos de categoria de falha');
    assert(isset($statsJson['payment_sources']['facilitator']), 'payment_sources deve expor todas as chaves canonicas');
    assert(isset($statsJson['failure_categories']['payment_rejected']), 'failure_categories deve expor todas as chaves canonicas');
    assert((int) array_sum($statsJson['status_counts']) === (int) $statsJson['total_requests'], 'Soma dos status deve igualar total_requests');
    // Indicadores executivos e analíticos expandidos
    assert(isset($statsJson['arpu_usdc']), 'API deve conter arpu_usdc');
    assert(isset($statsJson['technical_success_rate']), 'API deve conter technical_success_rate');
    assert(isset($statsJson['sandbox_rate']), 'API deve conter sandbox_rate');
    assert(isset($statsJson['daily_history_14d']) && count($statsJson['daily_history_14d']) === 14, 'API deve conter daily_history_14d com 14 dias');
    assert(isset($statsJson['top_consumers']) && is_array($statsJson['top_consumers']), 'API deve conter top_consumers');
    assert(isset($statsJson['service_revenue']) && is_array($statsJson['service_revenue']), 'API deve conter service_revenue');
    assert(isset($statsJson['passes_stats']), 'API deve conter passes_stats');
    assert(isset($statsJson['facilitator_status']['mode'], $statsJson['facilitator_status']['auth_mode'], $statsJson['facilitator_status']['host']), 'API deve conter facilitator_status sem segredos');
    if (defined('GRID402_X402_NETWORK') && GRID402_X402_NETWORK === 'base') {
        $expectedChains = (function_exists('grid402_is_solana_x402_enabled') && grid402_is_solana_x402_enabled()) ? 4 : 3;
        assert((int) ($statsJson['facilitator_status']['multichain_accepts'] ?? 0) === $expectedChains, "multichain_accepts em mainnet deve ser {$expectedChains} (Recebeu: " . ($statsJson['facilitator_status']['multichain_accepts'] ?? 'null') . ')');
        // Valida também em subprocesso isolado que com GRID402_SOLANA_X402_ENABLED=true o facilitador anuncia 4 chains
        $sol4Cmd = sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg("putenv('GRID402_SOLANA_X402_ENABLED=true'); define('GRID402_EXEC', true); require '" . str_replace('\\', '/', __DIR__ . '/../public_html/config.php') . "'; require '" . str_replace('\\', '/', __DIR__ . '/../public_html/x402.php') . "'; echo count(grid402_build_accepts(5000));")
        );
        assert(trim((string) shell_exec($sol4Cmd)) === '4', 'Com GRID402_SOLANA_X402_ENABLED=true, multichain_accepts em mainnet deve ser 4');
    }
    assert(isset($statsJson['rapidapi_status']['configured'], $statsJson['rapidapi_status']['source']), 'API deve conter rapidapi_status sem segredos');
    assert(defined('GRID402_RAPIDAPI_PROXY_SECRET') && (GRID402_RAPIDAPI_PROXY_SECRET === '' || !str_contains($authStats['body'], GRID402_RAPIDAPI_PROXY_SECRET)), 'API stats nunca deve vazar GRID402_RAPIDAPI_PROXY_SECRET');
    assert(isset($statsJson['collector_status']['summary_exists'], $statsJson['collector_status']['summary_stale']), 'API deve conter collector_status');
    assert(isset($statsJson['recent_payment_failures']) && is_array($statsJson['recent_payment_failures']), 'API deve conter recent_payment_failures array');
    assert(isset($statsJson['telegram_status']['configured'], $statsJson['telegram_status']['token_configured'], $statsJson['telegram_status']['chat_id_configured']), 'API deve conter telegram_status sem segredos');
    assert(defined('GRID402_TELEGRAM_BOT_TOKEN') && (GRID402_TELEGRAM_BOT_TOKEN === '' || !str_contains($authStats['body'], GRID402_TELEGRAM_BOT_TOKEN)), 'API stats nunca deve vazar GRID402_TELEGRAM_BOT_TOKEN');
    assert(isset($statsJson['error_5xx_count']), 'API deve conter error_5xx_count');
    assert(isset($statsJson['today_vs_yesterday']['metrics']['requests'], $statsJson['today_vs_yesterday']['metrics']['page_views'], $statsJson['today_vs_yesterday']['metrics']['new_wallets']), 'API deve conter today_vs_yesterday estruturado');
    assert(isset($statsJson['operational_health']['services_up'], $statsJson['operational_health']['services_total'], $statsJson['operational_health']['uptime_30d_pct']), 'API deve conter operational_health consolidado');
    assert(isset($statsJson['alerts']) && is_array($statsJson['alerts']), 'API deve conter lista de alerts');
    assert(isset($statsJson['alerts_summary']['high'], $statsJson['alerts_summary']['medium'], $statsJson['alerts_summary']['low']), 'API deve conter alerts_summary');
    echo "     ✓ Snapshot JSON em tempo real com saldos on-chain, ARPU, SLA, gráfico 14D e top consumidores validado!\n";

    // 4.5. Testar exportação de CSV autenticado
    echo "  -> Testando exportação de relatório financeiro CSV...\n";
    $csvResp = curl_admin("http://127.0.0.1:{$port}/admin?api=export_csv", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($csvResp['code'] === 200, "Exportação CSV deve retornar HTTP 200");
    assert(stripos($csvResp['headers'], 'Content-Type: text/csv') !== false, 'Header deve ser text/csv');
    assert(str_starts_with($csvResp['body'], "\xEF\xBB\xBF"), 'CSV deve iniciar com BOM UTF-8');
    assert(str_contains($csvResp['body'], 'Servico') and str_contains($csvResp['body'], 'Tipo') and str_contains($csvResp['body'], 'Valor USDC'), 'Deve conter colunas principais do CSV');
    assert(str_contains($csvResp['body'], 'Origem do Pagamento'), 'CSV deve conter coluna Origem do Pagamento');
    assert(str_contains($csvResp['body'], 'Falha'), 'CSV deve conter coluna Falha');
    assert(str_contains($csvResp['body'], 'Hash Liquidacao'), 'CSV deve conter coluna Hash Liquidacao');

    // Valida a contagem exata de colunas do cabecalho (12 colunas)
    $csvLines = preg_split('/\r?\n/', ltrim($csvResp['body'], "\xEF\xBB\xBF"));
    $csvHeader = str_getcsv($csvLines[0] ?? '', ';');
    assert(count($csvHeader) === 12, 'CSV deve conter exatamente 12 colunas (Recebeu: ' . count($csvHeader) . ')');
    echo "     ✓ Relatório CSV com BOM UTF-8 e 12 colunas validado com sucesso!\n";

    // 4.6. Acessar Dashboard HTML com sessão autenticada e extrair CSRF
    echo "  -> Acessando Dashboard HTML autenticado e validando token CSRF...\n";
    $dashResp = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($dashResp['code'] === 200, "Dashboard autenticado deve retornar 200");
    assert(str_contains($dashResp['body'], 'Monitoramento e Faturamento'), 'Deve conter título do Dashboard');
    assert(str_contains($dashResp['body'], 'Ao Vivo'), 'Deve conter indicador de atualização Ao Vivo');
    assert(str_contains($dashResp['body'], 'Facilitador x402'), 'Dashboard deve exibir status seguro do Facilitador x402');
    assert(str_contains($dashResp['body'], 'runtime-rapidapi-badge') && str_contains($dashResp['body'], 'btn-config-rapidapi') && str_contains($dashResp['body'], 'rapidapi-modal-overlay'), 'Dashboard deve exibir badge, botao e modal de configuracao do RapidAPI Bridge');
    if (defined('GRID402_X402_NETWORK') && GRID402_X402_NETWORK === 'base') {
        $expectedChainsLabel = (function_exists('grid402_is_solana_x402_enabled') && grid402_is_solana_x402_enabled()) ? '4 chains' : '3 chains';
        assert(str_contains($dashResp['body'], $expectedChainsLabel), "Dashboard em mainnet deve exibir {$expectedChainsLabel} no badge do facilitador");
    }
    assert(str_contains($dashResp['body'], 'runtime-telegram-badge') && str_contains($dashResp['body'], 'btn-test-telegram'), 'Dashboard deve exibir badge e botao de diagnostico do Telegram');
    assert(str_contains($dashResp['body'], 'payment-failures-panel') && str_contains($dashResp['body'], 'payment-failures-tbody'), 'Dashboard deve exibir painel de diagnostico real de falhas em requests.jsonl');
    assert(str_contains($dashResp['body'], 'Horário (BRT · UTC)'), 'Dashboard deve exibir cabecalho Horário (BRT · UTC) nas tabelas de telemetria e falhas');
    assert(str_contains($dashResp['body'], 'btn-config-telegram') && str_contains($dashResp['body'], 'telegram-modal-overlay'), 'Dashboard deve exibir botao e modal de configuracao do Telegram');
    assert(str_contains($dashResp['body'], 'Sem erros 5xx'), 'Dashboard deve exibir SLA sem erros 5xx');
    assert(str_contains($dashResp['body'], 'Saldo On-Chain (Base)'), 'Deve conter card de Saldo On-Chain');
    assert(str_contains($dashResp['body'], 'password-modal-overlay'), 'Deve conter modal de alteração de senha');
    assert(str_contains($dashResp['body'], 'Origem dos Pagamentos'), 'Deve conter painel de Origem dos Pagamentos');
    assert(str_contains($dashResp['body'], 'Falhas de Pagamento por Categoria'), 'Deve conter painel de Falhas de Pagamento');
    assert(str_contains($dashResp['body'], 'Origem / Falha'), 'Deve conter coluna Origem / Falha no feed');
    assert(str_contains($dashResp['body'], 'payment-source-bars-container'), 'Deve conter container das barras de origem');
    assert(str_contains($dashResp['body'], 'failure-bars-container'), 'Deve conter container das barras de falha');
    assert(str_contains($dashResp['body'], 'role="tablist"') && str_contains($dashResp['body'], 'id="tab-resumo"') && str_contains($dashResp['body'], 'id="tab-audiencia"') && str_contains($dashResp['body'], 'id="tab-receita"') && str_contains($dashResp['body'], 'id="tab-saude"') && str_contains($dashResp['body'], 'id="tab-eventos"'), 'Dashboard deve conter as 5 abas UX com role=tablist');
    assert(str_contains($dashResp['body'], 'chart.umd.min.js') && str_contains($dashResp['body'], 'chart-14d-canvas') && str_contains($dashResp['body'], 'chart-14d-fallback') && str_contains($dashResp['body'], 'daily-history-14d-tbody'), 'Dashboard deve conter Chart.js v4 com fallback e tabela 14 dias');
    assert(str_contains($dashResp['body'], 'settings-drawer') && str_contains($dashResp['body'], 'alerts-container') && str_contains($dashResp['body'], 'paid-vs-total-services-tbody'), 'Dashboard deve conter drawer de configuracoes, alertas e ranking de servicos pagos reais');
    assert(preg_match_all('/<h1\b/i', $dashResp['body']) === 1, 'Dashboard autenticado deve conter exatamente um unico h1 semantico');

    preg_match('/name="csrf-token"\s+content="([a-f0-9]{64})"/', $dashResp['body'], $authCsrfMatches);
    $authCsrf = $authCsrfMatches[1] ?? '';
    assert(strlen($authCsrf) === 64, 'Token CSRF autenticado deve estar presente no meta tag');
    echo "     ✓ Dashboard dark-mode com saldos, modais e token CSRF renderizado com sucesso!\n";

    // 4.7. Testar alteração de senha autenticada com proteção CSRF
    echo "  -> Testando alteração de senha (validação de token CSRF e credencial atual)...\n";
    // 4.7.1. Rejeição sem CSRF token
    $noCsrfChange = curl_admin("http://127.0.0.1:{$port}/admin?action=change_password", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_POSTFIELDS => http_build_query([
            'current_password' => 'Segredo#Forte2026',
            'new_password' => 'NovaSenha#Forte2026',
            'new_password_confirm' => 'NovaSenha#Forte2026',
        ]),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($noCsrfChange['code'] === 403, "Tentativa de alteração sem CSRF deve retornar HTTP 403 (Recebeu: {$noCsrfChange['code']})");

    // 4.7.1b. Rejeição de test_telegram sem autenticacao e sem CSRF
    $unauthTg = curl_admin("http://127.0.0.1:{$port}/admin?action=test_telegram", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    assert($unauthTg['code'] === 401, "test_telegram sem sessao deve retornar HTTP 401 (Recebeu: {$unauthTg['code']})");

    $noCsrfTg = curl_admin("http://127.0.0.1:{$port}/admin?action=test_telegram", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($noCsrfTg['code'] === 403, "test_telegram sem CSRF deve retornar HTTP 403 (Recebeu: {$noCsrfTg['code']})");

    // 4.7.1c. Validacao completa do endpoint save_telegram_config (401, 403, 400 e 200 com skip_test)
    $unauthSaveTg = curl_admin("http://127.0.0.1:{$port}/admin?action=save_telegram_config", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    assert($unauthSaveTg['code'] === 401, "save_telegram_config sem sessao deve retornar HTTP 401 (Recebeu: {$unauthSaveTg['code']})");

    $noCsrfSaveTg = curl_admin("http://127.0.0.1:{$port}/admin?action=save_telegram_config", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($noCsrfSaveTg['code'] === 403, "save_telegram_config sem CSRF deve retornar HTTP 403 (Recebeu: {$noCsrfSaveTg['code']})");

    $badFormatSaveTg = curl_admin("http://127.0.0.1:{$port}/admin?action=save_telegram_config", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
        CURLOPT_POSTFIELDS => http_build_query([
            'bot_token' => 'token_invalido',
            'chat_id' => '123456789',
            'csrf_token' => $authCsrf,
        ]),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($badFormatSaveTg['code'] === 400, "save_telegram_config com formato invalido deve retornar HTTP 400 (Recebeu: {$badFormatSaveTg['code']})");

    $tgCfgFile = GRID402_PRIVATE_DIR . '/telegram_config.json';
    $prevTgCfg = is_file($tgCfgFile) ? file_get_contents($tgCfgFile) : null;
    $goodSaveTg = curl_admin("http://127.0.0.1:{$port}/admin?action=save_telegram_config", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
        CURLOPT_POSTFIELDS => http_build_query([
            'bot_token' => '123456789:AAH_test_token_value_1234567890',
            'chat_id' => '987654321',
            'skip_test' => '1',
            'csrf_token' => $authCsrf,
        ]),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($goodSaveTg['code'] === 200, "save_telegram_config valido deve retornar HTTP 200 (Recebeu: {$goodSaveTg['code']})");
    $goodSaveTgJson = json_decode($goodSaveTg['body'], true);
    assert(($goodSaveTgJson['success'] ?? false) === true && is_file($tgCfgFile), 'save_telegram_config deve gravar telegram_config.json');
    if ($prevTgCfg !== null) {
        file_put_contents($tgCfgFile, $prevTgCfg, LOCK_EX);
    } elseif (is_file($tgCfgFile)) {
        @unlink($tgCfgFile);
    }

    // 4.7.1d. Validacao completa do endpoint save_rapidapi_config (401, 403, 400, 200 e clear=1)
    $unauthSaveRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['proxy_secret' => 'rapidapi_admin_secret_1234567890']),
    ]);
    assert($unauthSaveRapid['code'] === 401, "save_rapidapi_config sem sessao deve retornar HTTP 401 (Recebeu: {$unauthSaveRapid['code']})");

    $noCsrfSaveRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['proxy_secret' => 'rapidapi_admin_secret_1234567890']),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($noCsrfSaveRapid['code'] === 403, "save_rapidapi_config sem CSRF deve retornar HTTP 403 (Recebeu: {$noCsrfSaveRapid['code']})");

    $shortSaveRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
        CURLOPT_POSTFIELDS => http_build_query(['proxy_secret' => 'curto', 'csrf_token' => $authCsrf]),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($shortSaveRapid['code'] === 400, "save_rapidapi_config curto deve retornar HTTP 400 (Recebeu: {$shortSaveRapid['code']})");

    $rapidCfgFile = GRID402_PRIVATE_DIR . '/rapidapi_config.json';
    $prevRapidCfg = is_file($rapidCfgFile) ? file_get_contents($rapidCfgFile) : null;
    try {
        $testRapidSecret = 'rapidapi_admin_secret_' . bin2hex(random_bytes(8));
        $goodSaveRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
            CURLOPT_POSTFIELDS => http_build_query([
                'proxy_secret' => $testRapidSecret,
                'csrf_token' => $authCsrf,
            ]),
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        assert($goodSaveRapid['code'] === 200, "save_rapidapi_config valido deve retornar HTTP 200 (Recebeu: {$goodSaveRapid['code']})");
        $goodSaveRapidJson = json_decode($goodSaveRapid['body'], true);
        assert(($goodSaveRapidJson['success'] ?? false) === true, 'save_rapidapi_config deve retornar success=true');
        assert(($goodSaveRapidJson['rapidapi_status']['configured'] ?? false) === true, 'rapidapi_status.configured deve ser true');
        assert((int) ($goodSaveRapidJson['rapidapi_status']['count'] ?? 0) >= 1, 'rapidapi_status.count deve ser >= 1');
        assert(!str_contains($goodSaveRapid['body'], $testRapidSecret), 'save_rapidapi_config nunca deve ecoar o segredo na resposta');
        clearstatcache(true, $rapidCfgFile);
        assert(is_file($rapidCfgFile), 'save_rapidapi_config deve gravar rapidapi_config.json');

        $testRapidSecretB = 'rapidapi_admin_secret_b_' . bin2hex(random_bytes(8));
        $multiSaveRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
            CURLOPT_POSTFIELDS => http_build_query([
                'proxy_secret' => $testRapidSecret . ',' . $testRapidSecretB,
                'csrf_token' => $authCsrf,
            ]),
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        assert($multiSaveRapid['code'] === 200, "save_rapidapi_config multi-segredo deve retornar HTTP 200 (Recebeu: {$multiSaveRapid['code']})");
        $multiSaveJson = json_decode($multiSaveRapid['body'], true);
        assert(($multiSaveJson['success'] ?? false) === true, 'save_rapidapi_config multi-segredo deve retornar success=true');
        assert((int) ($multiSaveJson['rapidapi_status']['count'] ?? 0) >= 2, 'rapidapi_status.count deve ser >= 2 para dois segredos');
        assert(!str_contains($multiSaveRapid['body'], $testRapidSecret) && !str_contains($multiSaveRapid['body'], $testRapidSecretB), 'save_rapidapi_config nunca deve ecoar nenhum dos segredos');

        $statsRapidRes = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        assert($statsRapidRes['code'] === 200, 'GET /admin?api=stats deve retornar HTTP 200');
        $statsRapidJson = json_decode($statsRapidRes['body'], true);
        $statsRapidStatus = $statsRapidJson['runtime']['rapidapi'] ?? ($statsRapidJson['rapidapi_status'] ?? null);
        assert(is_array($statsRapidStatus) && isset($statsRapidStatus['count']), '/admin?api=stats deve incluir runtime rapidapi status com count');

        $badMultiRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
            CURLOPT_POSTFIELDS => http_build_query([
                'proxy_secret' => 'segredo_valido_123456,curto',
                'csrf_token' => $authCsrf,
            ]),
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        assert($badMultiRapid['code'] === 400, "save_rapidapi_config com item curto deve retornar HTTP 400 (Recebeu: {$badMultiRapid['code']})");

        $clearSaveRapid = curl_admin("http://127.0.0.1:{$port}/admin?action=save_rapidapi_config", [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $authCsrf],
            CURLOPT_POSTFIELDS => http_build_query([
                'clear' => '1',
                'csrf_token' => $authCsrf,
            ]),
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        assert($clearSaveRapid['code'] === 200, "save_rapidapi_config?clear=1 deve retornar HTTP 200 (Recebeu: {$clearSaveRapid['code']})");
        clearstatcache(true, $rapidCfgFile);
        assert(!is_file($rapidCfgFile), 'clear=1 deve remover rapidapi_config.json');
    } finally {
        if ($prevRapidCfg !== null) {
            file_put_contents($rapidCfgFile, $prevRapidCfg, LOCK_EX);
        } elseif (is_file($rapidCfgFile)) {
            @unlink($rapidCfgFile);
        }
    }

    // 4.7.2. Tentativa com senha atual errada via JSON e CSRF válido
    $badChange = curl_admin("http://127.0.0.1:{$port}/admin?action=change_password", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-CSRF-Token: ' . $authCsrf,
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'current_password' => 'SenhaErrada123',
            'new_password' => 'NovaSenha#2026',
            'new_password_confirm' => 'NovaSenha#2026',
            'csrf_token' => $authCsrf,
        ]),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($badChange['code'] === 400, "Alteração de senha com senha atual errada deve retornar 400");
    $badChangeJson = json_decode($badChange['body'], true);
    assert($badChangeJson['success'] === false, 'Deve retornar success false');
    assert(isset($badChangeJson['error']), 'Deve conter mensagem de erro');

    // 4.7.3. Tentativa com sucesso e CSRF válido
    $goodChange = curl_admin("http://127.0.0.1:{$port}/admin?action=change_password", [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-CSRF-Token: ' . $authCsrf,
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'current_password' => 'Segredo#Forte2026',
            'new_password' => 'NovaSenha#Forte2026',
            'new_password_confirm' => 'NovaSenha#Forte2026',
            'csrf_token' => $authCsrf,
        ]),
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($goodChange['code'] === 200, "Alteração de senha válida deve retornar 200");
    $goodChangeJson = json_decode($goodChange['body'], true);
    assert($goodChangeJson['success'] === true, 'Deve retornar success true');
    assert(isset($goodChangeJson['message']), 'Deve conter mensagem de sucesso');

    // Verifica se o hash no arquivo foi atualizado
    $authDataUpdated = json_decode((string) file_get_contents(GRID402_FILE_ADMIN_AUTH), true);
    assert(password_verify('NovaSenha#Forte2026', (string) $authDataUpdated['password_hash']) === true, 'Novo hash de senha deve ser validado');
    echo "     ✓ Fluxo seguro de alteração de senha protegido por CSRF homologado com sucesso!\n";

    // 4.7.4. Testar protecao contra sequestro de sessao (Fingerprint IP/User-Agent)
    echo "  -> Testando proteção contra sequestro de sessão (vínculo de User-Agent)...\n";
    $hijackResp = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Grid402-Hijacker-Agent/9.9',
    ]);
    assert($hijackResp['code'] === 401, "Tentativa de uso do cookie de sessão com outro User-Agent deve retornar HTTP 401 (Recebeu: {$hijackResp['code']})");
    echo "     ✓ Sequestro de sessão com User-Agent divergente bloqueado e sessão invalidada!\n";

    // 4.8. Testar fluxo completo de Login em Duas Etapas (2FA via Telegram) + Logout
    echo "  -> Testando fluxo completo de autenticação em duas etapas (2FA via Telegram)...\n";
    $otpCaptureFile = GRID402_PRIVATE_DIR . '/admin_test_otp.json';
    if (file_exists($otpCaptureFile)) @unlink($otpCaptureFile);

    $twoFaCookie = sys_get_temp_dir() . '/grid402_2fa_cookie.txt';
    if (file_exists($twoFaCookie)) @unlink($twoFaCookie);

    $loginGet = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEJAR => $twoFaCookie,
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $loginGet['body'], $twoFaCsrfMatch);
    $twoFaCsrf = $twoFaCsrfMatch[1] ?? '';
    assert(strlen($twoFaCsrf) === 64, 'Deve obter CSRF na tela de login para 2FA');

    // Etapa 1: Envia usuario e senha corretos -> deve exigir codigo 2FA e NAO liberar /admin?api=stats ainda
    $step1Resp = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => 'admin_test',
            'password' => 'NovaSenha#Forte2026',
            'csrf_token' => $twoFaCsrf,
        ]),
        CURLOPT_COOKIEJAR => $twoFaCookie,
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    assert($step1Resp['code'] === 200 && str_contains($step1Resp['body'], 'Verificação 2FA (Telegram)'), 'Login com 2FA ativo deve renderizar tela de desafio 2FA');
    assert(is_file($otpCaptureFile), 'Deve ter gerado e despachado codigo OTP de 6 digitos');

    $pre2faApi = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    assert($pre2faApi['code'] === 401, 'Antes de concluir o 2FA, /admin?api=stats deve retornar HTTP 401');

    $otpData = json_decode((string) file_get_contents($otpCaptureFile), true);
    $realOtp = (string) ($otpData['otp'] ?? '');
    assert(preg_match('/^\d{6}$/', $realOtp) === 1, 'OTP capturado deve ter 6 digitos numericos');
    $wrongOtp = ($realOtp === '000000') ? '999999' : '000000';

    // Etapa 2a: Envia codigo OTP incorreto -> deve recusar
    $badOtpResp = curl_admin("http://127.0.0.1:{$port}/admin?action=verify_2fa", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'otp_code' => $wrongOtp,
            'csrf_token' => $twoFaCsrf,
        ]),
        CURLOPT_COOKIEJAR => $twoFaCookie,
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    assert(str_contains($badOtpResp['body'], 'Código 2FA incorreto'), 'OTP incorreto deve ser recusado com contagem de tentativas');

    // Etapa 2b: Envia codigo OTP correto -> deve redirecionar 302 e liberar sessao autenticada
    $goodOtpResp = curl_admin("http://127.0.0.1:{$port}/admin?action=verify_2fa", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'otp_code' => $realOtp,
            'csrf_token' => $twoFaCsrf,
        ]),
        CURLOPT_COOKIEJAR => $twoFaCookie,
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    assert($goodOtpResp['code'] === 302, "OTP correto deve redirecionar HTTP 302 para /admin (Recebeu: {$goodOtpResp['code']})");

    $post2faApi = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    assert($post2faApi['code'] === 200, "Apos concluir 2FA, /admin?api=stats deve retornar HTTP 200 (Recebeu: {$post2faApi['code']})");
    echo "     ✓ Autenticação 2FA via Telegram (desafio, recusa de OTP inválido e aprovação de OTP válido) homologada!\n";

    echo "  -> Testando encerramento de sessão (Logout)...\n";
    $logoutResp = curl_admin("http://127.0.0.1:{$port}/admin?action=logout", [
        CURLOPT_COOKIEFILE => $twoFaCookie,
        CURLOPT_COOKIEJAR => $twoFaCookie,
    ]);
    assert($logoutResp['code'] === 302, "Logout deve redirecionar");

    // Verifica que não está mais autenticado na API
    $afterLogout = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $twoFaCookie,
    ]);
    assert($afterLogout['code'] === 401, "Após logout, API deve responder 401 Unauthorized");
    echo "     ✓ Sessão encerrada e dados protegidos!\n";

    // 4.8b. Testar fluxo completo de Recuperação de Senha via Telegram
    echo "  -> Testando fluxo completo de recuperação de senha via Telegram (Esqueci minha senha)...\n";
    $recoveryCookie = sys_get_temp_dir() . '/grid402_recovery_cookie.txt';
    if (file_exists($recoveryCookie)) @unlink($recoveryCookie);

    $loginPageRec = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEJAR => $recoveryCookie,
        CURLOPT_COOKIEFILE => $recoveryCookie,
    ]);
    assert(str_contains($loginPageRec['body'], 'Esqueci minha senha (recuperar via Telegram)'), 'Tela de login deve exibir opção de recuperação');

    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $loginPageRec['body'], $recCsrfMatches);
    $recCsrf = $recCsrfMatches[1] ?? '';
    assert(strlen($recCsrf) === 64, 'Token CSRF na tela de login deve ser válido');

    // 1. Dispara solicitação de recuperação via POST
    $triggerRec = curl_admin("http://127.0.0.1:{$port}/admin?action=forgot_password", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token' => $recCsrf,
        ]),
        CURLOPT_COOKIEJAR => $recoveryCookie,
        CURLOPT_COOKIEFILE => $recoveryCookie,
    ]);
    assert(str_contains($triggerRec['body'], 'Recuperar Acesso') && str_contains($triggerRec['body'], 'recovery_otp'), 'Deve renderizar tela de formulário com campo recovery_otp');

    // Lê o OTP capturado em modo de teste
    $recOtpFile = GRID402_PRIVATE_DIR . '/admin_test_recovery_otp.json';
    assert(file_exists($recOtpFile), 'Arquivo de captura de OTP de recuperação deve ter sido gerado');
    $recOtpData = json_decode((string) file_get_contents($recOtpFile), true);
    $validRecOtp = (string) ($recOtpData['otp'] ?? '');
    assert(preg_match('/^\d{6}$/', $validRecOtp) === 1, 'OTP de recuperação capturado deve ter 6 dígitos');

    // 2. Testa OTP incorreto -> deve recusar
    $badRecResp = curl_admin("http://127.0.0.1:{$port}/admin?action=confirm_recovery", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token' => $recCsrf,
            'recovery_otp' => '000000',
            'new_password' => 'NovaSenhaSegura@2026',
            'new_password_confirm' => 'NovaSenhaSegura@2026',
        ]),
        CURLOPT_COOKIEJAR => $recoveryCookie,
        CURLOPT_COOKIEFILE => $recoveryCookie,
    ]);
    assert(str_contains($badRecResp['body'], 'Código de recuperação incorreto'), 'OTP incorreto deve ser recusado');

    // 3. Testa senha curta (< 8 chars) -> deve recusar
    $shortPassResp = curl_admin("http://127.0.0.1:{$port}/admin?action=confirm_recovery", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token' => $recCsrf,
            'recovery_otp' => $validRecOtp,
            'new_password' => 'curta',
            'new_password_confirm' => 'curta',
        ]),
        CURLOPT_COOKIEJAR => $recoveryCookie,
        CURLOPT_COOKIEFILE => $recoveryCookie,
    ]);
    assert(str_contains($shortPassResp['body'], 'pelo menos 8 caracteres'), 'Senha curta deve ser rejeitada');

    // 4. Envia OTP correto e nova senha válida
    $goodRecResp = curl_admin("http://127.0.0.1:{$port}/admin?action=confirm_recovery", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token' => $recCsrf,
            'recovery_otp' => $validRecOtp,
            'new_password' => 'SuperSenhaNova402!',
            'new_password_confirm' => 'SuperSenhaNova402!',
        ]),
        CURLOPT_COOKIEJAR => $recoveryCookie,
        CURLOPT_COOKIEFILE => $recoveryCookie,
    ]);
    assert($goodRecResp['code'] === 302, "Redefinição com sucesso deve retornar HTTP 302 (Recebeu: {$goodRecResp['code']})");

    // Verifica que agora está autenticado e o painel responde 200
    $dashRec = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $recoveryCookie,
    ]);
    assert($dashRec['code'] === 200, "Após recuperação concluída, /admin?api=stats deve responder 200");
    echo "     ✓ Fluxo de recuperação de senha via Telegram (solicitação, recusa de OTP inválido e redefinição com sucesso) homologado!\n";

    if (file_exists($recoveryCookie)) @unlink($recoveryCookie);
    if (file_exists($recOtpFile)) @unlink($recOtpFile);

    // 4.9. Testar Bloqueio contra Força Bruta (Lockout de 15 minutos após 5 falhas consecutivas)
    echo "  -> Testando proteção contra força bruta no login (bloqueio 429 após 5 falhas)...\n";
    $loginPageCookie = sys_get_temp_dir() . '/grid402_login_cookie.txt';
    if (file_exists($loginPageCookie)) @unlink($loginPageCookie);

    $loginPage = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEJAR => $loginPageCookie,
        CURLOPT_COOKIEFILE => $loginPageCookie,
    ]);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $loginPage['body'], $loginCsrfMatches);
    $loginCsrf = $loginCsrfMatches[1] ?? '';
    assert(strlen($loginCsrf) === 64, 'Token CSRF do formulario de login deve ser valido');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        curl_admin("http://127.0.0.1:{$port}/admin", [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => 'admin_test',
                'password' => 'SenhaIncorreta#' . $attempt,
                'csrf_token' => $loginCsrf,
            ]),
            CURLOPT_COOKIEJAR => $loginPageCookie,
            CURLOPT_COOKIEFILE => $loginPageCookie,
        ]);
    }

    // A 6ª tentativa deve ser travada com HTTP 429 Too Many Requests
    $lockoutAttempt = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => 'admin_test',
            'password' => 'NovaSenha#Forte2026',
            'csrf_token' => $loginCsrf,
        ]),
        CURLOPT_COOKIEJAR => $loginPageCookie,
        CURLOPT_COOKIEFILE => $loginPageCookie,
    ]);
    assert($lockoutAttempt['code'] === 429, "Após 5 tentativas erradas, sistema deve responder HTTP 429 Lockout (Recebeu: {$lockoutAttempt['code']})");
    assert(str_contains($lockoutAttempt['body'], 'bloqueado por seguran'), 'Deve conter mensagem de bloqueio por seguranca');
    echo "     ✓ Bloqueio HTTP 429 contra força bruta validado com sucesso!\n";

} finally {
    if (isset($server)) {
        grid402_stop_server($server);
    }
    if (isset($serverLog) && is_file($serverLog)) @unlink($serverLog);

    // Restaura arquivos de teste
    if (isset($cookieFile) && file_exists($cookieFile)) @unlink($cookieFile);
    if (isset($twoFaCookie) && file_exists($twoFaCookie)) @unlink($twoFaCookie);
    if (isset($loginPageCookie) && file_exists($loginPageCookie)) @unlink($loginPageCookie);
    $otpTestFile = GRID402_PRIVATE_DIR . '/admin_test_otp.json';
    if (file_exists($otpTestFile)) @unlink($otpTestFile);
    $loginRateFile = GRID402_PRIVATE_DIR . '/admin_login_rate.json';
    if (file_exists($loginRateFile)) @unlink($loginRateFile);
    if (file_exists($testAuthBackup)) {
        rename($testAuthBackup, GRID402_FILE_ADMIN_AUTH);
    } else {
        @unlink(GRID402_FILE_ADMIN_AUTH);
    }
    if (file_exists($backupMetricsFile)) {
        rename($backupMetricsFile, GRID402_FILE_METRICS);
    }
    if ($hadWalletCache && file_exists($walletCacheBackup)) {
        rename($walletCacheBackup, $walletCacheFile);
    } else {
        @unlink($walletCacheFile);
    }
}

echo "[5/5] Homologação completa do Painel Administrativo concluída com 100% de sucesso!\n";

// Validação de filtragem de ruído de scanners gratuitos e 429 sandbox em grid402_read_recent_payment_failures()
require_once __DIR__ . '/../public_html/logger.php';
require_once __DIR__ . '/../public_html/growth.php';
$tmpLogFile = sys_get_temp_dir() . '/grid402_test_failures_' . bin2hex(random_bytes(4)) . '.jsonl';
$tmpPayFailFile = sys_get_temp_dir() . '/grid402_test_payfail_' . bin2hex(random_bytes(4)) . '.json';
putenv('GRID402_FILE_REQUEST_LOGS=' . $tmpLogFile);
$_ENV['GRID402_FILE_REQUEST_LOGS'] = $tmpLogFile;
putenv('GRID402_FILE_PAYMENT_FAILURES=' . $tmpPayFailFile);
$_ENV['GRID402_FILE_PAYMENT_FAILURES'] = $tmpPayFailFile;
try {
    grid402_log_request(['service' => 'pass-balance', 'status' => 400, 'payment_source' => 'free', 'error' => 'InvalidPassTokenFormat']);
    grid402_log_request(['service' => 'pass-statement', 'status' => 400, 'payment_source' => 'free', 'error' => 'InvalidPassTokenFormat']);
    grid402_log_request(['service' => 'pass-emitir', 'status' => 400, 'payment_source' => 'unknown', 'error' => 'InvalidTxHash']);
    grid402_log_request(['service' => 'dossier', 'status' => 429, 'payment_source' => 'sandbox', 'error' => 'SandboxRateLimitExceeded: Limite de 3 consultas gratuitas de sandbox por dia atingido']);
    grid402_log_request(['service' => 'dns', 'status' => 402, 'payment_source' => 'facilitator', 'error' => 'PaymentFailed: O facilitador rejeitou a autorizacao: invalid_exact_evm_insufficient_balance']);
    grid402_log_request(['service' => 'cnpj', 'status' => 429, 'payment_source' => 'pass', 'error' => 'PassDailyLimitExceeded: Limite diario do passe atingido']);
    $recentFailures = grid402_read_recent_payment_failures(25);
    assert(count($recentFailures) === 2, 'Deve filtrar probes gratuitos e 429 sandbox, mantendo falhas reais de facilitador e Session Pass');
    assert($recentFailures[0]['service'] === 'cnpj' && $recentFailures[0]['payment_source'] === 'pass', 'Deve preservar 429 real de Session Pass');
    assert($recentFailures[1]['service'] === 'dns' && $recentFailures[1]['payment_source'] === 'facilitator', 'Deve preservar falha real do facilitador em dns');
} finally {
    @unlink($tmpLogFile);
    @unlink($tmpLogFile . '.lock');
    @unlink($tmpPayFailFile);
    putenv('GRID402_FILE_REQUEST_LOGS');
    unset($_ENV['GRID402_FILE_REQUEST_LOGS']);
    putenv('GRID402_FILE_PAYMENT_FAILURES');
    unset($_ENV['GRID402_FILE_PAYMENT_FAILURES']);
}

// Validação de daily_history_14d com page_views, categoria sandbox_rate_limited e isolamento de paid_requests_per_service no Growth
$sampleMetrics = grid402_default_metrics();
assert(array_key_exists('sandbox_rate_limited', $sampleMetrics['failure_categories']), 'default_metrics deve conter sandbox_rate_limited');
$todayKey = grid402_metrics_today();
$sampleMetrics['daily'][$todayKey] = ['requests' => 42, 'page_views' => 17, 'paid' => 2, 'usdc' => 0.01];
$sampleAnalytics = grid402_compute_admin_analytics($sampleMetrics, 5.50);
$lastDay = $sampleAnalytics['daily_history_14d'][count($sampleAnalytics['daily_history_14d']) - 1] ?? [];
assert((int)($lastDay['page_views'] ?? -1) === 17, 'daily_history_14d deve expor page_views do dia');

$growthFile = grid402_growth_file();
$growthBackup = is_file($growthFile) ? file_get_contents($growthFile) : null;
try {
    @unlink($growthFile);
    grid402_growth_event('sandbox_rate_limited', ['service' => 'dossier', 'ip' => '8.209.10.10']);
    grid402_growth_event('challenge_402', ['service' => 'dossier', 'ip' => '8.209.10.10']);
    grid402_growth_event('paid_request', ['service' => 'dns', 'payer' => '0x1111111111111111111111111111111111111111', 'amount_usdc' => 0.005]);
    $gMetrics = grid402_growth_metrics();
    assert((int)($gMetrics['paid_requests_per_service']['dossier'] ?? 0) === 0, 'Rajadas sandbox_rate_limited/challenge_402 nao devem inflar paid_requests_per_service');
    assert((int)($gMetrics['paid_requests_per_service']['dns'] ?? 0) === 1, 'paid_requests_per_service deve contabilizar apenas consultas pagas');
} finally {
    if ($growthBackup !== null) {
        file_put_contents($growthFile, $growthBackup);
    } else {
        @unlink($growthFile);
    }
}

// Validação unitária do motor de insights executivos, 7 regras de alertas e cooldown Zero-DB no Telegram
require_once __DIR__ . '/../public_html/services/admin_insights.php';
$tzBrt = new DateTimeZone('America/Sao_Paulo');
$dayToday = (new DateTimeImmutable('now', $tzBrt))->format('Y-m-d');
$dayYesterday = (new DateTimeImmutable('now', $tzBrt))->modify('-1 day')->format('Y-m-d');

$mockMetrics = grid402_default_metrics();
$mockMetrics['total_requests'] = 500;
$mockMetrics['total_page_views'] = 120;
$mockMetrics['total_paid_requests'] = 10;
$mockMetrics['total_revenue_usdc'] = 1.5;
$mockMetrics['daily'][$dayToday] = ['requests' => 150, 'page_views' => 40, 'paid' => 5, 'usdc' => 0.5];
$mockMetrics['daily'][$dayYesterday] = ['requests' => 100, 'page_views' => 0, 'paid' => 2, 'usdc' => 0.25];
$mockGrowth = ['wallets_connected' => 12, 'new_wallets_by_day' => [$dayToday => 1, $dayYesterday => 2]];

$tvsy = grid402_admin_today_vs_yesterday($mockMetrics, $mockGrowth, 5.0);
assert($tvsy['metrics']['requests']['delta'] === 50 && $tvsy['metrics']['requests']['delta_pct'] === 50.0, 'Delta % de requests Hoje vs Ontem deve ser +50.0%');
assert($tvsy['metrics']['page_views']['delta_pct'] === null, 'Quando ontem for 0, delta_pct deve ser null sem divisao por zero');
assert($tvsy['metrics']['new_wallets']['delta'] === -1 && $tvsy['metrics']['new_wallets']['delta_pct'] === -50.0, 'Delta de novas wallets deve refletir -50.0%');

$mockAnalytics = [
    'error_5xx_count' => 2,
    'technical_success_rate' => 98.5,
    'latency_p50_ms' => 120.0,
    'latency_p95_ms' => 1250.0,
    'passes_stats' => ['active_passes' => 2, 'total_credits' => 800, 'remaining_credits' => 800, 'active_remaining_credits' => 800],
];
$mockHealth = [
    'error_5xx_count' => 2,
    'technical_success_rate' => 98.5,
    'collector_stale' => true,
    'collector_age_sec' => 900,
    'burst_429_recent' => 25,
    'latency_p50_ms' => 120.0,
    'latency_p95_ms' => 1250.0,
];
$mockWallet = ['rpc_operational' => true, 'usdc_balance' => 4.29, 'eth_balance' => 0.0];
$evalAlerts = grid402_admin_evaluate_alerts($mockMetrics, $mockAnalytics, $mockHealth, $mockWallet);
assert($evalAlerts['summary']['high'] === 3, 'Deve detectar os 3 alertas de severidade high (5xx, collector_stale, burst_429)');
assert($evalAlerts['summary']['medium'] === 2, 'Deve detectar os 2 alertas de severidade medium (p95 > 1000ms, passes ociosos)');
assert($evalAlerts['summary']['low'] === 2, 'Deve detectar os 2 alertas de severidade low (0 ETH, baixa tracao)');
assert($evalAlerts['alerts'][0]['severity'] === 'high' && $evalAlerts['alerts'][count($evalAlerts['alerts']) - 1]['severity'] === 'low', 'Alertas devem estar ordenados high -> medium -> low');

// Validação dos alertas da Fase 1 (Índice Oficial de Sanções 4 Blocos e Solana x402 sem ATA)
$alSanMissing = grid402_admin_evaluate_alerts($mockMetrics, $mockAnalytics, ['sanctions_available' => false, 'sanctions_freshness' => 'UNAVAILABLE'], []);
assert(in_array('sanctions_index_missing', array_column($alSanMissing['alerts'], 'id'), true), 'Deve emitir sanctions_index_missing (high) quando indice ausente');
$alSanStale = grid402_admin_evaluate_alerts($mockMetrics, $mockAnalytics, ['sanctions_available' => true, 'sanctions_freshness' => 'STALE', 'sanctions_age_hours' => 80.5], []);
assert(in_array('sanctions_index_stale', array_column($alSanStale['alerts'], 'id'), true), 'Deve emitir sanctions_index_stale (medium) quando indice STALE');
$alSolNoAta = grid402_admin_evaluate_alerts($mockMetrics, $mockAnalytics, ['sanctions_available' => true, 'sanctions_freshness' => 'FRESH', 'solana_enabled' => true], ['chains' => ['solana' => ['ata_initialized' => false]]]);
$solIds = array_column($alSolNoAta['alerts'], 'id');
assert(in_array('solana_enabled_without_ata', $solIds, true), 'Deve emitir solana_enabled_without_ata (high) quando Solana ativa sem ATA');
assert(!in_array('sanctions_index_missing', $solIds, true) && !in_array('sanctions_index_stale', $solIds, true), 'Indice FRESH nao deve emitir alertas de sancoes');

$tmpAlertsState = sys_get_temp_dir() . '/grid402_test_alerts_state_' . bin2hex(random_bytes(4)) . '.json';
try {
    $dispatchedIds = [];
    $fakeSender = static function (array $al) use (&$dispatchedIds): bool {
        $dispatchedIds[] = $al['id'];
        return true;
    };
    $t0 = 1795340000;
    $r1 = grid402_dispatch_high_severity_alerts($evalAlerts['alerts'], 3600, $fakeSender, $tmpAlertsState, $t0);
    assert(count($r1['sent']) === 3 && empty($r1['suppressed']), '1a execucao deve enviar exatamente os 3 alertas high (nunca medium/low)');
    $r2 = grid402_dispatch_high_severity_alerts($evalAlerts['alerts'], 3600, $fakeSender, $tmpAlertsState, $t0 + 600);
    assert(empty($r2['sent']) && count($r2['suppressed']) === 3, '2a execucao dentro do cooldown de 60min deve suprimir todos os 3 alertas');
    $r3 = grid402_dispatch_high_severity_alerts($evalAlerts['alerts'], 3600, $fakeSender, $tmpAlertsState, $t0 + 3605);
    assert(count($r3['sent']) === 3 && empty($r3['suppressed']), 'Apos expirar cooldown de 60min, alertas high ainda ativos devem ser reenviados');
    assert(count($dispatchedIds) === 6, 'Total de envios nas 3 rodadas deve ser 6 (3 + 0 + 3)');
} finally {
    @unlink($tmpAlertsState);
}
echo "[6/6] Motor de insights executivos, 7 regras de alertas e cooldown Telegram validados com sucesso!\n";


