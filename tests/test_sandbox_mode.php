<?php
/**
 * Testes Unitários e de Integração: Modo Sandbox e Rate Limit Defensivo
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';

echo "=== INICIANDO TESTES DO MODO SANDBOX E RATE LIMIT (GRID402) ===\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$detail}\n";
        $failed++;
    }
}

// 1. Constantes de Configuração da Base Sepolia
echo "[1] Testando Parâmetros da Base Sepolia e Sandbox:\n";
assert_test(
    "Chain ID da Base Sepolia é 84532",
    defined('GRID402_SEPOLIA_CHAIN_ID') && GRID402_SEPOLIA_CHAIN_ID === 84532
);

assert_test(
    "RPC público da Base Sepolia configurado",
    defined('GRID402_SEPOLIA_RPC_URL') && GRID402_SEPOLIA_RPC_URL === 'https://sepolia.base.org'
);

assert_test(
    "Contrato oficial USDC da Sepolia configurado",
    defined('GRID402_SEPOLIA_USDC_CONTRACT') && GRID402_SEPOLIA_USDC_CONTRACT === '0x036cbd53842c5426634e7929541ec2318f3dcf7e'
);

assert_test(
    "Cota horária defensiva definida em 3 consultas",
    defined('GRID402_SANDBOX_RATE_LIMIT') && GRID402_SANDBOX_RATE_LIMIT === 3
);

// 2. Autenticação via Cabeçalho 'demo' e 'sandbox'
echo "\n[2] Testando Validação do Cabeçalho de Simulação:\n";
$testIp = '192.168.100.' . rand(1, 254);

$valDemo = grid402_validate_payment('demo', null, $testIp);
assert_test(
    "Cabeçalho 'demo' é aceito com status 200",
    isset($valDemo['valid']) && $valDemo['valid'] === true &&
    isset($valDemo['code']) && $valDemo['code'] === 200,
    json_encode($valDemo)
);

assert_test(
    "Resposta de 'demo' carrega flag sandbox=true",
    isset($valDemo['sandbox']) && $valDemo['sandbox'] === true
);

$valSandbox = grid402_validate_payment('sandbox', null, $testIp);
assert_test(
    "Cabeçalho 'sandbox' é aceito com status 200",
    isset($valSandbox['valid']) && $valSandbox['valid'] === true &&
    isset($valSandbox['code']) && $valSandbox['code'] === 200,
    json_encode($valSandbox)
);

// 3. Teste de Rate Limit Defensivo por IP
echo "\n[3] Testando Bloqueio por Cota de Rate Limit (Max 3 req/hora):\n";
$spamIp = '10.200.50.' . rand(1, 254);

// Consome as 3 chamadas permitidas
$allowedCount = 0;
for ($i = 1; $i <= 3; $i++) {
    $res = grid402_check_sandbox_rate_limit($spamIp);
    if ($res['allowed']) {
        $allowedCount++;
    }
}

assert_test(
    "Permite exatamente 3 requisições consecutivas no intervalo de 1 hora",
    $allowedCount === 3,
    "Requisições autorizadas: {$allowedCount}"
);

// A 4ª requisição DEVE ser recusada
$resExceeded = grid402_check_sandbox_rate_limit($spamIp);
assert_test(
    "4ª requisição é rejeitada pelo rate limit (allowed=false)",
    $resExceeded['allowed'] === false && $resExceeded['remaining'] === 0
);

// Valida que o gateway responde com HTTP 429
$val429 = grid402_validate_payment('demo', null, $spamIp);
assert_test(
    "Requisição que excede o limite retorna HTTP 429 Too Many Requests",
    isset($val429['valid']) && $val429['valid'] === false &&
    isset($val429['code']) && $val429['code'] === 429,
    json_encode($val429)
);

echo "\n=== RESULTADO FINAL: {$passed} PASSOU | {$failed} FALHOU ===\n";
exit($failed > 0 ? 1 : 0);

