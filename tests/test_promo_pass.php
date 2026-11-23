<?php
/**
 * Testes automatizados para o motor de cupons promocionais de Session Pass (MCPTRY50).
 */

declare(strict_types=1);

$tmpDir = sys_get_temp_dir() . '/grid402_promo_test_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0775, true);

putenv('GRID402_PRIVATE_DIR=' . $tmpDir);
putenv('GRID402_FILE_PROMO_CLAIMS=' . $tmpDir . '/promo_claims.json');
putenv('GRID402_PROMO_MCPTRY50_MAX_CLAIMS=2');
define('GRID402_TEST_MODE', true);
define('GRID402_EXEC', true);

require_once __DIR__ . '/../public_html/services/promo_pass.php';

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] {$msg}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$msg}\n";
        $failed++;
    }
}

echo "=== Testando Cupons Promocionais de Session Pass (MCPTRY50) ===\n";

$now = strtotime('2026-11-30T15:00:00Z');

// 1. Status da campanha
$st = grid402_promo_get_status('MCPTRY50', $now);
assertTrue(!empty($st['ok']) && ($st['code'] ?? 0) === 200, 'GET promo-status MCPTRY50 retorna 200');
assertTrue(($st['campaign']['credits'] ?? 0) === 50, 'Campanha MCPTRY50 concede exatamente 50 creditos');
assertTrue(($st['campaign']['validity_days'] ?? 0) === 30, 'Validade configurada para 30 dias');
assertTrue(($st['campaign']['daily_credit_limit'] ?? 0) === 25, 'Limite diario configurado para 25 cr/dia');
assertTrue(($st['campaign']['remaining_claims'] ?? 0) === 2, 'Respeita max_claims via env (2)');
assertTrue(!empty($st['campaign']['active']), 'Campanha ativa na Cyber Monday (30/nov)');

// 2. Codigo invalido
$bad = grid402_promo_get_status('INVALID_XYZ', $now);
assertTrue(empty($bad['ok']) && ($bad['code'] ?? 0) === 404, 'Codigo inexistente retorna 404 PromoNotFound');

// 3. Resgate valido (case-insensitive)
$claim1 = grid402_promo_claim_pass('mcptry50', '203.0.113.10', $now);
assertTrue(!empty($claim1['ok']) && ($claim1['code'] ?? 0) === 200, 'Resgate 1 (case-insensitive) retorna 200');
$tok1 = (string) ($claim1['pass_token'] ?? '');
assertTrue(str_starts_with($tok1, 'pass_g402_live'), 'Token emitido segue formato oficial pass_g402_live...');
assertTrue(($claim1['credits'] ?? 0) === 50, 'Resgate retorna 50 creditos');
assertTrue(($claim1['ttl_days'] ?? 0) === 30, 'Resgate retorna validade de 30 dias');
assertTrue(($claim1['daily_credit_limit'] ?? 0) === 25, 'Resgate retorna daily_credit_limit=25');
assertTrue(($claim1['remaining_claims'] ?? -1) === 1, 'Resta 1 vaga apos primeiro resgate');

// 4. Verifica pass real no repositorio + consumo de 1 credito
$repo = grid402_pass_repository();
$passInfo = $repo->getPassStatus($tok1);
assertTrue(($passInfo['status'] ?? '') === 'ACTIVE', 'Session Pass promocional esta ACTIVE no repositorio');
assertTrue((int) ($passInfo['credits_remaining'] ?? 0) === 50, 'Saldo inicial no repositorio e 50 creditos');
assertTrue((int) ($passInfo['daily_credit_limit'] ?? 0) === 25, 'Spend Control daily_credit_limit=25 ativo no repositorio');
$ttlDiff = (int) ($passInfo['expires_epoch'] ?? 0) - $now;
assertTrue($ttlDiff >= 29 * 86400 && $ttlDiff <= 31 * 86400, 'Expiracao gravada para exatamente 30 dias');

$consume = $repo->consumePass($tok1, 5000, 'extract');
assertTrue(!empty($consume['valid']) && (int) ($consume['credits_remaining'] ?? 0) === 49, 'Consumo de 1 credito em /extract debita corretamente (49 restantes)');

// 5. Bloqueio de duplicidade pelo mesmo IP (409)
$dup = grid402_promo_claim_pass('MCPTRY50', '203.0.113.10', $now);
assertTrue(empty($dup['ok']) && ($dup['code'] ?? 0) === 409 && ($dup['error'] ?? '') === 'PromoAlreadyClaimed', 'Segundo resgate pelo mesmo IP retorna 409 PromoAlreadyClaimed');

// 6. Zero-PII: IP em texto claro jamais salvo em disco
$rawClaims = (string) file_get_contents($tmpDir . '/promo_claims.json');
assertTrue(!str_contains($rawClaims, '203.0.113.10'), 'promo_claims.json nao armazena IP em texto claro (Zero-PII)');

// 7. Segundo IP esgota a cota global (max=2), terceiro IP recebe 410 PromoExhausted
$claim2 = grid402_promo_claim_pass('MCPTRY50', '203.0.113.11', $now);
assertTrue(!empty($claim2['ok']) && ($claim2['remaining_claims'] ?? -1) === 0, 'Segundo IP distinto resgata ultima vaga (remaining=0)');

$claim3 = grid402_promo_claim_pass('MCPTRY50', '203.0.113.12', $now);
assertTrue(empty($claim3['ok']) && ($claim3['code'] ?? 0) === 410 && ($claim3['error'] ?? '') === 'PromoExhausted', 'Terceiro IP apos esgotar cota retorna 410 PromoExhausted');

// 8. Campanha expirada apos 08/dez retorna 410 PromoExpired
$expired = grid402_promo_claim_pass('MCPTRY50', '203.0.113.99', strtotime('2026-12-09T00:00:00Z'));
assertTrue(empty($expired['ok']) && ($expired['code'] ?? 0) === 410 && ($expired['error'] ?? '') === 'PromoExpired', 'Tentativa apos termino da campanha retorna 410 PromoExpired');

// Limpeza
foreach (glob($tmpDir . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmpDir);

echo "\nResultado: {$passed} aprovados, {$failed} reprovados.\n";
exit($failed > 0 ? 1 : 0);
