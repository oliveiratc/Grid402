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

echo "\nResultado: $pass passaram, $fail falharam\n";
exit($fail > 0 ? 1 : 0);
