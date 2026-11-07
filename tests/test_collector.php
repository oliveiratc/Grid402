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
assert(count($freeData['services']) === 6, "Deve conter 6 serviços catalogados");
assert(isset($freeData['services']['forex']['usd_brl']), "Deve conter cotação de USD");
assert(isset($freeData['services']['crypto']['btc_usd']), "Deve conter cotação de BTC");

echo "✓ Testes do coletor concluídos com 100% de sucesso!\n";
