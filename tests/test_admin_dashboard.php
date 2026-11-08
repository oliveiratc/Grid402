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

// --- Teste 2: Gravação Atômica de Telemetria e Buffer Circular ---
echo "[2/5] Testando gravação atômica de telemetria e buffer circular... ";
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

// Grava 1 consulta paga de DNS com tx_hash
$dummyTx = '0x' . bin2hex(random_bytes(32));
grid402_record_telemetry('dns', 200, true, $dummyTx, '189.40.50.2', 0.0005);

$m = grid402_get_metrics();
assert($m['total_requests'] === 3, 'Total de requisições deve ser 3');
assert($m['total_paid_requests'] === 1, 'Total de requisições pagas deve ser 1');
assert($m['total_revenue_usdc'] === 0.0005, 'Faturamento total em USDC deve ser 0.0005');
assert($m['service_counts']['forex'] === 1, 'Consultas de forex deve ser 1');
assert($m['service_counts']['dns'] === 2, 'Consultas de dns deve ser 2');
assert($m['status_counts']['200'] === 2, 'Status 200 deve ser 2');
assert($m['status_counts']['402'] === 1, 'Status 402 deve ser 1');
assert(count($m['recent_events']) === 3, 'Deve haver 3 eventos recentes');
assert($m['recent_events'][0]['tx_hash'] === strtolower($dummyTx), 'Último evento deve conter o hash da transação');
assert($m['recent_events'][0]['ip_masked'] === '189.40.xxx.xxx', 'IP deve estar anonimizado no evento');

// Teste de Buffer Circular (adicionar 60 eventos e garantir que no máximo 50 permanecem)
for ($i = 0; $i < 60; $i++) {
    grid402_record_telemetry('crypto', 200, false);
}
$mAfter = grid402_get_metrics();
assert(count($mAfter['recent_events']) === 50, 'Buffer circular deve reter exatamente 50 eventos recentes no máximo');
echo "OK!\n";

// --- Teste 3: Autenticação Segura com Bcrypt ---
echo "[3/5] Testando criptografia e validação de senha (Bcrypt)... ";
$testPass = 'SenhaUltraSegura#2026';
$hash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 11]);
assert(password_verify($testPass, $hash) === true, 'Senha correta deve ser verificada');
assert(password_verify('SenhaIncorreta', $hash) === false, 'Senha incorreta deve ser recusada');
echo "OK!\n";

// --- Teste 4 & 5: Ciclo HTTP do Painel Admin (Setup, Login, 401 e Dashboard) ---
echo "[4/5] Subindo servidor web local para testar fluxos HTTP do /admin...\n";

$port = 8959;
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$phpBinary = 'C:\Users\thiag\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe';
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
    $setupResp = curl_admin("http://127.0.0.1:{$port}/admin");
    assert($setupResp['code'] === 200, "Setup deve responder HTTP 200 (Recebeu: {$setupResp['code']})");
    assert(str_contains($setupResp['body'], 'Configurar Acesso'), 'Deve conter tela de Configurar Acesso');
    echo "     ✓ Setup inicial detectado com sucesso!\n";

    // 4.2. Requisição à API /admin?api=stats sem autenticação -> deve retornar HTTP 401
    echo "  -> Testando bloqueio da API /admin?api=stats sem sessão...\n";
    $unauthStats = curl_admin("http://127.0.0.1:{$port}/admin?api=stats");
    assert($unauthStats['code'] === 401, "API stats desautenticada deve retornar 401 (Recebeu: {$unauthStats['code']})");
    assert(str_contains($unauthStats['body'], 'Unauthorized'), 'Deve conter mensagem Unauthorized');
    echo "     ✓ Bloqueio HTTP 401 para requisição sem sessão validado!\n";

    // 4.3. Submeter formulário de setup inicial
    echo "  -> Cadastrando credenciais administrativas iniciais...\n";
    $cookieFile = sys_get_temp_dir() . '/grid402_admin_cookie.txt';
    if (file_exists($cookieFile)) @unlink($cookieFile);

    $postSetup = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => 'admin_test',
            'password' => 'Segredo#Forte2026',
            'password_confirm' => 'Segredo#Forte2026',
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($postSetup['code'] === 302 || $postSetup['code'] === 200, "Setup deve redirecionar após cadastro");
    assert(file_exists(GRID402_FILE_ADMIN_AUTH), "Arquivo admin_auth.json deve ter sido criado");
    echo "     ✓ Credenciais criadas e sessão iniciada!\n";

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
    assert(isset($statsJson['recent_events']), 'Deve conter recent_events');
    echo "     ✓ Snapshot JSON em tempo real validado com sucesso!\n";

    // 4.5. Acessar Dashboard HTML com sessão autenticada
    echo "  -> Acessando Dashboard HTML autenticado...\n";
    $dashResp = curl_admin("http://127.0.0.1:{$port}/admin", [
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assert($dashResp['code'] === 200, "Dashboard autenticado deve retornar 200");
    assert(str_contains($dashResp['body'], 'Monitoramento e Faturamento'), 'Deve conter título do Dashboard');
    assert(str_contains($dashResp['body'], 'Ao Vivo'), 'Deve conter indicador de atualização Ao Vivo');
    echo "     ✓ Dashboard dark-mode renderizado com sucesso!\n";

    // 4.6. Testar Logout
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

} finally {
    proc_terminate($proc);
    fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);

    // Restaura arquivos de teste
    if (file_exists($cookieFile)) @unlink($cookieFile);
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

