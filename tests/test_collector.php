<?php
declare(strict_types=1);

echo "[TEST] 1. Executando collector.php via CLI...\n";
$start = microtime(true);

ob_start();
require __DIR__ . '/../public_html/collector.php';
$output = ob_get_clean();

$duration = microtime(true) - $start;
echo "Saída do coletor: " . trim($output) . "\n";
echo sprintf("Tempo total de execução local: %0.2f segundos\n", $duration);

// Validações de Tempo e Arquivos
assert($duration < 4.0, "O tempo de execução deve ser inferior a 4.0 segundos (Levou {$duration}s)");

$freeFile = __DIR__ . '/../public_html/private_data/summary_free.json';
assert(file_exists($freeFile), "summary_free.json deve existir");

$freeData = json_decode((string)file_get_contents($freeFile), true);
assert(is_array($freeData), "summary_free.json deve ser JSON válido");
assert($freeData['protocol'] === 'x402', "Protocolo deve ser x402");
assert($freeData['chain_id'] === 8453, "Chain ID deve ser 8453");
assert($freeData['status'] === 'OPERATIONAL', "Status deve ser OPERATIONAL");
assert(count($freeData['services']) === 28, "Deve conter 28 serviços catalogados");
assert(isset($freeData['services']['dossier']), "Deve conter serviço dossier catalogado");
assert(isset($freeData['services']['token-safety'], $freeData['services']['prediction-markets'], $freeData['services']['batch']), "Deve conter servicos da Fase 2 catalogados");
// V-14: /status gratuito não deve vazar cotações live de forex/crypto
assert(!isset($freeData['services']['forex']['usd_brl']), "V-14: /status não deve vazar cotação live usd_brl");
assert(!isset($freeData['services']['forex']['eur_brl']), "V-14: /status não deve vazar cotação live eur_brl");
assert(!isset($freeData['services']['crypto']['btc_usd']), "V-14: /status não deve vazar cotação live btc_usd");
assert(!isset($freeData['services']['crypto']['eth_usd']), "V-14: /status não deve vazar cotação live eth_usd");
assert(!isset($freeData['services']['crypto']['sol_usd']), "V-14: /status não deve vazar cotação live sol_usd");
assert(isset($freeData['services']['forex']['pairs_monitored']), "Forex deve manter metadado pairs_monitored");
assert(isset($freeData['services']['crypto']['assets_monitored']), "Crypto deve manter metadado assets_monitored");
// V-22: Todos os 28 serviços devem expor price_usdc canônico (incluindo lei=0.0100)
$withPrice = 0;
foreach ($freeData['services'] as $svcId => $svcMeta) {
    assert(
        isset($svcMeta['price_usdc']) && is_string($svcMeta['price_usdc']) && $svcMeta['price_usdc'] !== '',
        "Serviço {$svcId} deve expor price_usdc canônico em summary_free.json"
    );
    $withPrice++;
}
assert($withPrice === 28, "Todos os 28 serviços devem ter price_usdc populado (encontrado: {$withPrice})");
assert(($freeData['services']['lei']['price_usdc'] ?? '') === '0.0100', "V-22: lei deve ter price_usdc igual a 0.0100");

echo "✓ Testes do coletor concluídos com 100% de sucesso!\n";
