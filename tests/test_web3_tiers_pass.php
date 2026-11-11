<?php
/**
 * Testes Unitários e de Integração: Tiers Web3 de Passes On-Chain (Starter, Pro e Fleet)
 */

declare(strict_types=1);

require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/pass.php';

echo "=== Iniciando Testes: Tiers Web3 de Session Passes ===\n\n";
$passed = 0;
$failed = 0;

function assert_test(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] {$msg}\n";
        $passed++;
    } else {
        echo "[FAIL] {$msg}\n";
        $failed++;
    }
}

// 1. Starter Tier (2 USDC -> 2.000.000 raw units)
$txStarter = '0x' . bin2hex(random_bytes(32));
$passStarter = grid402_issue_session_pass($txStarter, 2000000, false);
assert_test($passStarter['status'] === 'ACTIVE', 'Starter Pass emitido com status ACTIVE');
assert_test(($passStarter['tier'] ?? '') === 'starter', 'Starter Pass identificado com tier starter');
assert_test(($passStarter['credits_total'] ?? 0) === 4000, 'Starter Pass possui 4.000 créditos (2 USDC)');
assert_test(str_starts_with($passStarter['pass_token'] ?? '', 'pass_g402_live'), 'Starter Pass token com prefixo correto');

// 2. Pro Tier (5 USDC -> 5.000.000 raw units)
$txPro = '0x' . bin2hex(random_bytes(32));
$passPro = grid402_issue_session_pass($txPro, 5000000, false);
assert_test($passPro['status'] === 'ACTIVE', 'Pro Pass emitido com status ACTIVE');
assert_test(($passPro['tier'] ?? '') === 'pro', 'Pro Pass identificado com tier pro');
assert_test(($passPro['credits_total'] ?? 0) === 10000, 'Pro Pass possui 10.000 créditos (5 USDC)');

// 3. Fleet Tier (20 USDC -> 20.000.000 raw units)
$txFleet = '0x' . bin2hex(random_bytes(32));
$passFleet = grid402_issue_session_pass($txFleet, 20000000, false);
assert_test($passFleet['status'] === 'ACTIVE', 'Fleet Pass emitido com status ACTIVE');
assert_test(($passFleet['tier'] ?? '') === 'fleet', 'Fleet Pass identificado com tier fleet');
assert_test(($passFleet['credits_total'] ?? 0) === 50000, 'Fleet Pass possui 50.000 créditos (20 USDC + bônus)');

// Validade de 60 dias para o Fleet
$now = time();
$expEpoch = $passFleet['expires_epoch'] ?? 0;
$daysDiff = round(($expEpoch - $now) / 86400);
assert_test($daysDiff >= 59 && $daysDiff <= 61, "Fleet Pass possui validade de ~60 dias (calculado: {$daysDiff} dias)");

// 4. Idempotência / Deduplicação: chamar novamente com a mesma tx retorna o mesmo passe sem recriar
$passDup = grid402_issue_session_pass($txFleet, 20000000, false);
assert_test($passDup['pass_token'] === $passFleet['pass_token'], 'Rechamada com a mesma txHash retorna o mesmo token sem duplicar');

// 5. Consulta de status
$statusCheck = grid402_get_session_pass_status($passStarter['pass_token']);
assert_test($statusCheck['valid'] === true, 'Consulta de status do Starter Pass retorna valid=true');
assert_test($statusCheck['credits_remaining'] === 4000, 'Saldo intacto de 4.000 créditos confirmado');

echo "\n=== Resultado Final: {$passed} aprovados, {$failed} falhas ===\n";
if ($failed > 0) {
    exit(1);
}

