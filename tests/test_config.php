<?php
declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';

echo "[TEST] Verificando constantes de configuração do Hub de Utilitários...\n";

assert(defined('GRID402_WALLET_ADDRESS'), 'GRID402_WALLET_ADDRESS deve estar definida');
assert(defined('GRID402_BASE_RPC_URL'), 'GRID402_BASE_RPC_URL deve estar definida');
assert(defined('GRID402_USDC_CONTRACT'), 'GRID402_USDC_CONTRACT deve estar definida');
assert(GRID402_CHAIN_ID === 8453, 'GRID402_CHAIN_ID deve ser 8453');
assert(GRID402_USDC_REQUIRED_RAW === 500, 'GRID402_USDC_REQUIRED_RAW deve ser 500 (0.0005 USDC)');

$services = grid402_get_services();
assert(count($services) === 6, 'Deve conter exatamente 6 micro-serviços');

$keys = array_keys($services);
assert(in_array('forex', $keys, true), 'Deve conter serviço forex');
assert(in_array('crypto', $keys, true), 'Deve conter serviço crypto');
assert(in_array('dns', $keys, true), 'Deve conter serviço dns');
assert(in_array('ssl', $keys, true), 'Deve conter serviço ssl');
assert(in_array('asn', $keys, true), 'Deve conter serviço asn');
assert(in_array('cep', $keys, true), 'Deve conter serviço cep');

echo "✓ Todas as verificações de configuração passaram com sucesso! (" . count($services) . " utilitários validados)\n";
