<?php
/**
 * Testes do Growth Engine
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/growth.php';

$pass = 0; $fail = 0;
function ok(bool $condition, string $msg = ''): void {
    global $pass, $fail;
    if ($condition) { $pass++; } else { $fail++; echo "FAIL: $msg\n"; }
}

// Testes de registro de builder
$result = grid402_growth_register_builder(['name' => 'Test Builder', 'email' => 'test@example.com']);
ok(isset($result['builders']), 'Builder registrado');

// Registro de referral
$result = grid402_growth_create_referral(['wallet' => '0x0000000000000000000000000000000000000001']);
ok(isset($result['referrals']), 'Referral criado');

// Métricas não-zeradas
$metrics = grid402_growth_metrics();
ok(is_array($metrics) && isset($metrics['wallets_connected']), 'Métricas retornadas');

// Escala v2_5000 oficial (400 / 1200 / 6000) e migracao automatica de defaults legados
$defs = grid402_growth_defaults();
ok(($defs['recharge_starter_credits'] ?? 0) === 400, 'Default starter deve ser 400 creditos');
ok(($defs['recharge_pro_credits'] ?? 0) === 1200, 'Default pro deve ser 1200 creditos');
ok(($defs['recharge_fleet_credits'] ?? 0) === 6000, 'Default fleet deve ser 6000 creditos');
ok(($defs['starter_promo_requires_first_paid'] ?? false) === true, 'Politica anti-abuso deve exigir 1a compra');
ok(($defs['promo_auto_issue'] ?? true) === false, 'Emissao automatica deve permanecer desabilitada');

$migrated = grid402_growth_normalize([
    'config' => [
        'recharge_starter_credits' => 4000,
        'recharge_pro_credits' => 10000,
        'recharge_fleet_credits' => 50000,
    ],
]);
ok(($migrated['config']['recharge_starter_credits'] ?? 0) === 400, 'Migracao legado starter 4000 -> 400');
ok(($migrated['config']['recharge_pro_credits'] ?? 0) === 1200, 'Migracao legado pro 10000 -> 1200');
ok(($migrated['config']['recharge_fleet_credits'] ?? 0) === 6000, 'Migracao legado fleet 50000 -> 6000');

echo "\nResultado: $pass passaram, $fail falharam\n";
exit($fail > 0 ? 1 : 0);
