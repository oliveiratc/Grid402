<?php
declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';

echo "[TEST] Verificando constantes de configuração...\n";

assert(defined('GRID402_WALLET_ADDRESS'), 'GRID402_WALLET_ADDRESS deve estar definida');
assert(defined('GRID402_BASE_RPC_URL'), 'GRID402_BASE_RPC_URL deve estar definida');
assert(defined('GRID402_USDC_CONTRACT'), 'GRID402_USDC_CONTRACT deve estar definida');
assert(GRID402_CHAIN_ID === 8453, 'GRID402_CHAIN_ID deve ser 8453');
assert(GRID402_USDC_REQUIRED_RAW === 500, 'GRID402_USDC_REQUIRED_RAW deve ser 500');

$targets = grid402_get_targets();
assert(count($targets) >= 15 && count($targets) <= 20, 'Deve conter entre 15 e 20 alvos');

$categories = array_unique(array_column($targets, 'category'));
assert(in_array('SEFAZ', $categories, true), 'Deve conter categoria SEFAZ');
assert(in_array('Gateways', $categories, true), 'Deve conter categoria Gateways');
assert(in_array('Bancos & PIX', $categories, true), 'Deve conter categoria Bancos & PIX');
assert(in_array('Telecom & Infra', $categories, true), 'Deve conter categoria Telecom & Infra');

echo "✓ Todas as verificações de configuração passaram com sucesso! (" . count($targets) . " alvos validados)\n";

