<?php
/**
 * Testes Unitarios da Assinatura Criptografica de Oraculo (Proof of Data)
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/oracle_signer.php';

echo "=== Testes da Assinatura Criptografica de Oraculo Grid402 ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "[PASS] {$name}\n";
        $testsPassed++;
    } else {
        echo "[FAIL] {$name} - {$details}\n";
        $testsFailed++;
    }
}

// 1. Determinismo do Hash Canonico
$payloadA = [
    'status' => 'success',
    'service' => 'forex',
    'rates' => [
        'USD' => ['bid' => '5.60', 'ask' => '5.62'],
        'EUR' => ['bid' => '6.10', 'ask' => '6.12']
    ]
];

$payloadB = [
    'service' => 'forex',
    'rates' => [
        'EUR' => ['ask' => '6.12', 'bid' => '6.10'],
        'USD' => ['ask' => '5.62', 'bid' => '5.60']
    ],
    'status' => 'success'
];

$hashA = grid402_compute_data_hash($payloadA);
$hashB = grid402_compute_data_hash($payloadB);

assert_test(
    'Hash canonico e deterministico independente da ordem das chaves',
    $hashA === $hashB && str_starts_with($hashA, '0x'),
    "HashA: {$hashA} != HashB: {$hashB}"
);

// 2. Geracao da Prova Criptografica em Producao
$dataProd = [
    'status' => 'success',
    'service' => 'workdays',
    'workdays' => 5,
    'start_date' => '2026-11-06',
    'target_date' => '2026-11-13'
];

grid402_sign_data($dataProd, false);

assert_test(
    'Assinatura em producao anexa _oracle_proof estruturado',
    isset($dataProd['_oracle_proof']) &&
    is_array($dataProd['_oracle_proof']) &&
    !empty($dataProd['_oracle_proof']['signature']) &&
    $dataProd['_oracle_proof']['sandbox'] === false &&
    $dataProd['_oracle_proof']['algorithm'] === 'secp256k1-sha256',
    json_encode($dataProd['_oracle_proof'] ?? [])
);

// 3. Verificacao Positiva da Prova
$verifyProd = grid402_verify_oracle_proof($dataProd);
assert_test(
    'Verificacao positiva da assinatura valida com sucesso',
    !empty($verifyProd['valid']) && $verifyProd['valid'] === true,
    json_encode($verifyProd)
);

// 4. Detecao de Adulteracao de Dados (Data Tampering)
$dataTampered = $dataProd;
$dataTampered['workdays'] = 6; // Adulterado de 5 para 6

$verifyTampered = grid402_verify_oracle_proof($dataTampered);
assert_test(
    'Rejeita payload adulterado acusando DataHashMismatch',
    empty($verifyTampered['valid']) && ($verifyTampered['error'] ?? '') === 'DataHashMismatch',
    json_encode($verifyTampered)
);

// 5. Detecao de Assinatura Forjada
$dataForged = $dataProd;
$dataForged['_oracle_proof']['signature'] = '0x1234567890abcdef';

$verifyForged = grid402_verify_oracle_proof($dataForged);
assert_test(
    'Rejeita assinatura corrompida ou forjada',
    empty($verifyForged['valid']),
    json_encode($verifyForged)
);

// 6. Prova em Modo Sandbox
$dataSandbox = [
    'status' => 'success',
    'service' => 'cnpj',
    'cnpj' => '00000000000191',
    'razao_social' => 'BANCO DO BRASIL SA'
];

grid402_sign_data($dataSandbox, true);

assert_test(
    'Modo Sandbox emite assinatura marcando sandbox=true',
    isset($dataSandbox['_oracle_proof']['sandbox']) &&
    $dataSandbox['_oracle_proof']['sandbox'] === true,
    json_encode($dataSandbox['_oracle_proof'] ?? [])
);

$verifySandbox = grid402_verify_oracle_proof($dataSandbox);
assert_test(
    'Verificacao de prova no modo Sandbox e valida',
    !empty($verifySandbox['valid']) && $verifySandbox['valid'] === true,
    json_encode($verifySandbox)
);

echo "\nResultado: {$testsPassed} passaram | {$testsFailed} falharam.\n";
if ($testsFailed > 0) {
    exit(1);
}
echo "Todos os testes da Assinatura Criptografica foram APROVADOS com 100% de sucesso!\n";

