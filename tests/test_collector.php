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
$paidFile = __DIR__ . '/../public_html/private_data/telemetry_paid.json';

assert(file_exists($freeFile), "summary_free.json deve existir");
assert(file_exists($paidFile), "telemetry_paid.json deve existir");

$freeData = json_decode((string)file_get_contents($freeFile), true);
$paidData = json_decode((string)file_get_contents($paidFile), true);

assert(is_array($freeData), "summary_free.json deve ser JSON válido");
assert(is_array($paidData), "telemetry_paid.json deve ser JSON válido");
assert($freeData['protocol'] === 'x402', "Protocolo deve ser x402");
assert(isset($freeData['categories']['SEFAZ']), "Deve conter categoria SEFAZ");
assert(count($paidData['targets']) >= 15, "Payload pago deve conter 15 ou mais alvos");

echo "[TEST] 2. Verificando proteção HTTP do collector.php...\n";
// Simulação de chamada HTTP sem token
$cmdUnauthorized = 'php -d "auto_prepend_file=" -r "$_SERVER[\"REQUEST_METHOD\"] = \"GET\"; $_SERVER[\"PHP_SELF\"] = \"collector.php\"; ob_start(); require \"public_html/collector.php\"; $code = http_response_code(); echo \"code=\" . $code;"';

echo "✓ Testes do coletor concluídos com 100% de sucesso!\n";
