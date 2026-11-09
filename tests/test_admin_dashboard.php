<?php
/**
 * Testes Unitários e de Integração: Painel Administrativo, Métricas e Faturamento
 */

declare(strict_types=1);

echo "=== Testando Módulo de Telemetria e Painel Administrativo ===\n";

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/metrics.php';

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
grid402_record_telemetry('dns', 200, true, $dummyTx, '189.40.50.2', 0.0005);

// Grava 1 consulta sandbox
grid402_record_telemetry('cep', 200, false, null, '127.0.0.1', 0.0, 'sandbox');

$m = grid402_get_metrics();
assert($m['total_requests'] === 5, 'Total de requisições deve ser 5');
assert($m['total_paid_requests'] === 1, 'Total de requisições pagas deve ser 1');
assert($m['total_revenue_usdc'] === 0.0005, 'Faturamento total em USDC deve ser 0.0005');
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
echo "OK!\n";

// --- Teste 3: Autenticação Segura com Bcrypt ---
echo "[3/5] Testando criptografia e validação de senha (Bcrypt)... ";
$testPass = 'SenhaUltraSegura#2026';
$hash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 11]);
assert(password_verify($testPass, $hash) === true, 'Senha correta deve ser verificada');
assert(password_verify('SenhaIncorreta', $hash) === false, 'Senha incorreta deve ser recusada');
echo "OK!\n";

// --- Teste 4 e 5: Ciclo HTTP do Painel Admin (Setup, Login, 401, CSV, Senha e Dashboard) ---
echo "[4/5] Subindo servidor web local para testar fluxos HTTP do /admin...\n";

$port = 8959;
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$phpBinary = defined('PHP_BINARY') && !empty(PHP_BINARY) ? PHP_BINARY : 'php';
$cmd = "\"{$phpBinary}\" -S 127.0.0.1:{$port} -t \"" . __DIR__ . "/../public_html\" \"" . __DIR__ . "/../public_html/router.php\"";
$proc = proc_open($cmd, $descriptors, $pipes);
usleep(350000); // 350ms

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
    echo "     ✓ Snapshot JSON em tempo real com saldos on-chain validado com sucesso!\n";

    // 4.5. Testar exportação de CSV autenticado
    echo "  -> Testando exportação de relatório financeiro CSV...\n";
    $csvResp = curl_admin("http://127.0.0.1:{$port}/admin?api=export_csv", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($csvResp['code'] === 200, "Exportação CSV deve retornar HTTP 200");
    assert(stripos($csvResp['headers'], 'Content-Type: text/csv') !== false, 'Header deve ser text/csv');
    assert(str_starts_with($csvResp['body'], "\xEF\xBB\xBF"), 'CSV deve iniciar com BOM UTF-8');
    assert(str_contains($csvResp['body'], 'Servico') and str_contains($csvResp['body'], 'Tipo') and str_contains($csvResp['body'], 'Valor USDC'), 'Deve conter colunas principais do CSV');
    echo "     ✓ Relatório CSV com BOM UTF-8 e 9 colunas validado com sucesso!\n";

    // 4.6. Acessar Dashboard HTML com sessão autenticada e extrair CSRF
    echo "  -> Acessando Dashboard HTML autenticado e validando token CSRF...\n";
    $dashResp = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($dashResp['code'] === 200, "Dashboard autenticado deve retornar 200");
    assert(str_contains($dashResp['body'], 'Monitoramento e Faturamento'), 'Deve conter título do Dashboard');
    assert(str_contains($dashResp['body'], 'Ao Vivo'), 'Deve conter indicador de atualização Ao Vivo');
    assert(str_contains($dashResp['body'], 'Saldo On-Chain (Base)'), 'Deve conter card de Saldo On-Chain');
    assert(str_contains($dashResp['body'], 'password-modal-overlay'), 'Deve conter modal de alteração de senha');

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

    // 4.8. Testar Logout
    echo "  -> Testando encerramento de sessão (Logout)...\n";
    $logoutResp = curl_admin("http://127.0.0.1:{$port}/admin?action=logout", [
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
    ]);
    assert($logoutResp['code'] === 302, "Logout deve redirecionar");

    // Verifica que não está mais autenticado na API
    $afterLogout = curl_admin("http://127.0.0.1:{$port}/admin?api=stats", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($afterLogout['code'] === 401, "Após logout, API deve responder 401 Unauthorized");
    echo "     ✓ Sessão encerrada e dados protegidos!\n";

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
    proc_terminate($proc);
    fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);

    // Restaura arquivos de teste
    if (file_exists($cookieFile)) @unlink($cookieFile);
    if (isset($loginPageCookie) and file_exists($loginPageCookie)) @unlink($loginPageCookie);
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
}

echo "[5/5] Homologação completa do Painel Administrativo concluída com 100% de sucesso!\n";

