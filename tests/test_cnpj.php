<?php
/**
 * Testes Unitários e de Integração: API de Consulta Cadastral de CNPJ
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/cnpj.php';
require_once __DIR__ . '/../public_html/x402.php';

echo "=== Testando Micro-Serviço de Consulta Cadastral de CNPJ ===\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest(bool $condition, string $message): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  ✓ {$message}\n";
    } else {
        echo "  ✗ FALHA: {$message}\n";
    }
}

// 1. Testes de Validação Matemática de Módulo 11
echo "[1/4] Testando validação matemática Módulo 11 (dígitos verificadores)...\n";
assertTest(grid402_validate_cnpj_digits('00.000.000/0001-91') === true, 'CNPJ formatado do Banco do Brasil (00.000.000/0001-91) é válido');
assertTest(grid402_validate_cnpj_digits('00000000000191') === true, 'CNPJ sem formatação (00000000000191) é válido');
assertTest(grid402_validate_cnpj_digits('33.000.167/0001-01') === true, 'CNPJ da Petrobras (33.000.167/0001-01) é válido');
assertTest(grid402_validate_cnpj_digits('11.111.111/1111-11') === false, 'Rejeição de dígitos repetidos (11.111.111/1111-11)');
assertTest(grid402_validate_cnpj_digits('00000000000000') === false, 'Rejeição de zeros repetidos (00000000000000)');
assertTest(grid402_validate_cnpj_digits('00.000.000/0001-92') === false, 'Rejeição de dígito verificador incorreto (final 92)');
assertTest(grid402_validate_cnpj_digits('123456789') === false, 'Rejeição de tamanho menor que 14 dígitos');

// 2. Testes de Consulta Cadastral e Conformidade LGPD
echo "\n[2/4] Testando consulta cadastral e conformidade estrita LGPD (sem QSA)...\n";
$bbData = grid402_service_cnpj('00000000000191');
assertTest($bbData['success'] === true, 'Consulta de CNPJ válida retornou success: true');
assertTest($bbData['cnpj'] === '00.000.000/0001-91', 'CNPJ formatado retornado corretamente');
assertTest(str_contains($bbData['razao_social'], 'BANCO DO BRASIL'), 'Razão social identificada corretamente');
assertTest($bbData['situacao_cadastral'] === 'ATIVA', 'Situação cadastral retornada como ATIVA');
assertTest(!empty($bbData['endereco']['municipio']), 'Município corporativo retornado');
assertTest(!isset($bbData['qsa']) && !isset($bbData['socios']), 'Proteção LGPD Risco Zero: QSA/Sócios ausentes do payload');

// 3. Teste de Cache Local Atômico (TTL 7 dias)
echo "\n[3/4] Testando persistência e recuperação do cache local...\n";
$cachedData = grid402_service_cnpj('00000000000191');
assertTest($cachedData['cached'] === true, 'Segunda consulta recuperada do cache local persistente');
assertTest(isset($cachedData['cache_age_seconds']) && $cachedData['cache_age_seconds'] >= 0, 'Idade do cache computada em segundos');

// 4. Teste de Validação de Preço e Desafio 402
echo "\n[4/4] Testando parâmetros de tarifação x402 para CNPJ ($0.0020 USDC)...\n";
assertTest(defined('GRID402_PRICE_CNPJ') && GRID402_PRICE_CNPJ === '0.0020', 'Constante GRID402_PRICE_CNPJ configurada como 0.0020');
assertTest(defined('GRID402_USDC_CNPJ_RAW') && GRID402_USDC_CNPJ_RAW === 2000, 'Constante GRID402_USDC_CNPJ_RAW configurada como 2000 unidades');

$catalog = grid402_get_utility_services();
assertTest(isset($catalog['cnpj']), 'Serviço cnpj registrado no catálogo oficial');
assertTest(($catalog['cnpj']['price_usdc'] ?? '') === '0.0020', 'Preço de 0.0020 registrado no catálogo de serviços');

echo "\n=== Resultado dos Testes: {$testsPassed}/{$totalTests} testes aprovados! ===\n";

if ($testsPassed === $totalTests) {
    echo "TODOS OS TESTES DA API DE CNPJ PASSARAM COM 100% DE SUCESSO!\n";
    exit(0);
} else {
    echo "ALGUNS TESTES FALHARAM.\n";
    exit(1);
}

