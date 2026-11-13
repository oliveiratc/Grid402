<?php
declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';

echo "[TEST] Verificando constantes de configuração do Hub de Utilitários...\n";

assert(defined('GRID402_WALLET_ADDRESS'), 'GRID402_WALLET_ADDRESS deve estar definida');
assert(defined('GRID402_BASE_RPC_URL'), 'GRID402_BASE_RPC_URL deve estar definida');
assert(defined('GRID402_USDC_CONTRACT'), 'GRID402_USDC_CONTRACT deve estar definida');
assert(GRID402_CHAIN_ID === 8453, 'GRID402_CHAIN_ID deve ser 8453');
assert(GRID402_USDC_REQUIRED_RAW === 5000, 'GRID402_USDC_REQUIRED_RAW deve ser 5000 (0.0050 USDC)');

assert(defined('GRID402_TELEGRAM_BOT_TOKEN'), 'GRID402_TELEGRAM_BOT_TOKEN deve estar definida');
assert(defined('GRID402_TELEGRAM_CHAT_ID'), 'GRID402_TELEGRAM_CHAT_ID deve estar definida');

$services = grid402_get_services();
assert(count($services) === 22, 'Deve conter exatamente 22 micro-servicos');

$keys = array_keys($services);
assert(in_array('forex', $keys, true), 'Deve conter servico forex');
assert(in_array('crypto', $keys, true), 'Deve conter servico crypto');
assert(in_array('dns', $keys, true), 'Deve conter servico dns');
assert(in_array('ssl', $keys, true), 'Deve conter servico ssl');
assert(in_array('asn', $keys, true), 'Deve conter servico asn');
assert(in_array('cep', $keys, true), 'Deve conter servico cep');
assert(in_array('cnpj', $keys, true), 'Deve conter servico cnpj');
assert(in_array('indicators', $keys, true), 'Deve conter servico indicators');
assert(in_array('holidays', $keys, true), 'Deve conter servico holidays');
assert(in_array('workdays', $keys, true), 'Deve conter servico workdays');
assert(in_array('sanctions', $keys, true), 'Deve conter servico sanctions');
assert(in_array('simples', $keys, true), 'Deve conter servico simples');
assert(in_array('compliance', $keys, true), 'Deve conter servico compliance');
assert(in_array('lei', $keys, true), 'Deve conter servico lei');
assert(in_array('ofac', $keys, true), 'Deve conter servico ofac');
assert(in_array('notarize', $keys, true), 'Deve conter servico notarize');
assert(in_array('global-markets', $keys, true), 'Deve conter servico global-markets');
assert(in_array('pass', $keys, true), 'Deve conter servico pass');
assert(in_array('verify', $keys, true), 'Deve conter servico verify');

echo "✓ Todas as verificações de configuração passaram com sucesso! (" . count($services) . " utilitários validados)\n";
