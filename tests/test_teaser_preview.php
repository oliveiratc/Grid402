<?php
/**
 * Testes Unitários e de Integração: Amostra Parcial (Teaser Data) no Desafio HTTP 402
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';
require_once __DIR__ . '/../public_html/services/cnpj.php';
require_once __DIR__ . '/../public_html/services/cep.php';
require_once __DIR__ . '/../public_html/services/indicators.php';
require_once __DIR__ . '/../public_html/services/holidays.php';

echo "=== INICIANDO TESTES DO TEASER PREVIEW NO PROTOCOLO x402 ===\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$detail}\n";
        $failed++;
    }
}

// 1. Testes de CNPJ Preview
echo "[1] Testes da Prévia de CNPJ:\n";

// 1.1 CNPJ Válido (Banco do Brasil)
$bbCnpj = '00000000000191';
$bbPreview = grid402_get_cnpj_preview($bbCnpj);

assert_test(
    "CNPJ válido retorna success=true",
    isset($bbPreview['success']) && $bbPreview['success'] === true,
    json_encode($bbPreview)
);

assert_test(
    "CNPJ válido contém chave preview com razão social e situação cadastral",
    isset($bbPreview['preview']['razao_social']) &&
    !empty($bbPreview['preview']['razao_social']) &&
    isset($bbPreview['preview']['situacao_cadastral']),
    json_encode($bbPreview['preview'] ?? [])
);

assert_test(
    "CNPJ válido contém lista de campos protegidos (locked_fields)",
    isset($bbPreview['locked_fields']) &&
    is_array($bbPreview['locked_fields']) &&
    in_array('capital_social', $bbPreview['locked_fields'], true) &&
    in_array('natureza_juridica', $bbPreview['locked_fields'], true),
    json_encode($bbPreview['locked_fields'] ?? [])
);

// 1.2 CNPJ Matemático Inválido (Rejeição sem 402)
$invalidCnpj = '11111111111111';
$invalidRes = grid402_get_cnpj_preview($invalidCnpj);
assert_test(
    "CNPJ inválido retorna erro 400 antes da emissão de cobrança",
    isset($invalidRes['success']) && $invalidRes['success'] === false &&
    isset($invalidRes['code']) && $invalidRes['code'] === 400,
    json_encode($invalidRes)
);

echo "\n[2] Testes da Prévia de CEP:\n";

// 2.1 CEP Válido (Av. Paulista)
$paulistaCep = '01310100';
$cepPreview = grid402_get_cep_preview($paulistaCep);

assert_test(
    "CEP válido retorna success=true",
    isset($cepPreview['success']) && $cepPreview['success'] === true,
    json_encode($cepPreview)
);

assert_test(
    "CEP válido contém chave preview com cidade e UF abertos",
    isset($cepPreview['preview']['cidade']) &&
    $cepPreview['preview']['cidade'] === 'São Paulo' &&
    isset($cepPreview['preview']['uf']) &&
    $cepPreview['preview']['uf'] === 'SP',
    json_encode($cepPreview['preview'] ?? [])
);

assert_test(
    "CEP válido contém lista de campos protegidos (locked_fields)",
    isset($cepPreview['locked_fields']) &&
    is_array($cepPreview['locked_fields']) &&
    in_array('logradouro', $cepPreview['locked_fields'], true) &&
    in_array('bairro', $cepPreview['locked_fields'], true),
    json_encode($cepPreview['locked_fields'] ?? [])
);

// 2.2 CEP com Formato Inválido (Rejeição 400)
$invalidCep = '123';
$invalidCepRes = grid402_get_cep_preview($invalidCep);
assert_test(
    "CEP de formato inválido retorna erro 400 antes da cobrança",
    isset($invalidCepRes['success']) && $invalidCepRes['success'] === false &&
    isset($invalidCepRes['code']) && $invalidCepRes['code'] === 400,
    json_encode($invalidCepRes)
);

// 3. Testes da Previa de Indicadores Economicos
echo "\n[3] Testes da Previa de Indicadores Economicos:\n";
$indPreview = grid402_teaser_indicators();
assert_test(
    "Indicadores contem selic_target aberta e data de referencia",
    isset($indPreview['selic_target']) && $indPreview['selic_target'] > 0 &&
    isset($indPreview['reference_date']),
    json_encode($indPreview)
);
assert_test(
    "Indicadores contem campos protegidos locked_fields",
    isset($indPreview['locked_fields']) && is_array($indPreview['locked_fields']) &&
    in_array('cdi_annual', $indPreview['locked_fields'], true) &&
    in_array('ipca_12m', $indPreview['locked_fields'], true),
    json_encode($indPreview['locked_fields'] ?? [])
);

// 4. Testes da Previa de Feriados Nacionais e Calendario Bancario
echo "\n[4] Testes da Previa de Feriados:\n";
$holPreview = grid402_teaser_holidays(2026);
assert_test(
    "Feriados contem proximo feriado e total do ano abertos",
    isset($holPreview['next_holiday']) && is_array($holPreview['next_holiday']) &&
    isset($holPreview['total_holidays_in_year']) && $holPreview['total_holidays_in_year'] >= 14,
    json_encode($holPreview)
);
assert_test(
    "Feriados contem campos protegidos locked_fields",
    isset($holPreview['locked_fields']) && is_array($holPreview['locked_fields']) &&
    in_array('full_year_schedule', $holPreview['locked_fields'], true),
    json_encode($holPreview['locked_fields'] ?? [])
);

echo "\n[5] Teste Estrutural do Payload x402:\n";

// Simula execução de montagem do payload
$samplePreview = ['item' => 'teste'];
$sampleLocked = ['campo_oculto'];
$reflector = new ReflectionFunction('grid402_respond_402');
$numParams = $reflector->getNumberOfParameters();

assert_test(
    "Função grid402_respond_402 aceita parâmetros de preview e lockedFields",
    $numParams >= 6,
    "Parâmetros encontrados: {$numParams}"
);

echo "\n=== RESULTADO FINAL: {$passed} PASSOU | {$failed} FALHOU ===\n";
exit($failed > 0 ? 1 : 0);

