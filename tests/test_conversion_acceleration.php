<?php
/**
 * Testes Unitários: Aceleração de Faturamento e Otimização de Conversão
 */

declare(strict_types=1);

echo "=== Testando Aceleração de Faturamento e Conversão (OpenSpec) ===\n";

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/mercadopago.php';
require_once __DIR__ . '/../public_html/x402.php';

// --- Teste 1: Cadastro do pacote finance_pack ---
echo "[1/3] Testando catálogo de pacotes com finance_pack... ";
$packages = grid402_mp_packages();
assert(isset($packages['finance_pack']), 'O pacote finance_pack deve existir no catálogo');
assert($packages['finance_pack']['amount_brl'] === 29.90, 'Preço deve ser R$ 29,90');
assert($packages['finance_pack']['credits'] === 10000, 'Créditos devem ser 10.000');
assert($packages['finance_pack']['ttl_days'] === 365, 'Validade deve ser 365 dias');
echo "OK!\n";

// --- Teste 2: Payload de resgate Pix no erro 402 ---
echo "[2/3] Testando estrutura do corpo de resposta 402 com link Pix... ";
ob_start();
// Chama grid402_respond_402 em buffer capturado
try {
    grid402_respond_402('forex', 'Test 402 challenge');
} catch (\Throwable $e) {
    // pode interromper por exit se houver
}
$output = ob_get_clean();
$data = json_decode((string)$output, true);

assert(is_array($data), 'Resposta deve ser um JSON válido');
assert(isset($data['quick_pix_pass_url']), 'Deve conter a chave quick_pix_pass_url');
assert(str_contains($data['quick_pix_pass_url'], '/pass?tier=micro&method=pix'), 'URL deve apontar para compra do passe');
assert($data['quick_pix_price_brl'] === 4.90, 'Preço Pix deve ser 4.90');
echo "OK!\n";

// --- Teste 3: Polling de 2 segundos configurado ---
echo "[3/3] Verificando intervalo de polling rápido (2000ms)... ";
$passSource = file_get_contents(__DIR__ . '/../public_html/pass.php');
$dossieSource = file_get_contents(__DIR__ . '/../public_html/dossie_kyb.php');
assert(str_contains($passSource, 'setInterval(() => pollOrderStatus(orderId), 2000)'), 'pass.php deve ter polling a cada 2s');
assert(str_contains($dossieSource, 'setInterval(checkPixStatus, 2000)'), 'dossie_kyb.php deve ter polling a cada 2s');
echo "OK!\n";

echo "=== Todos os testes de conversão passaram com 100% de sucesso! ===\n";
