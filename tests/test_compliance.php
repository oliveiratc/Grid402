<?php
/**
 * Testes Unitários e de Integração: Micro-serviço de Compliance e Antifraude
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/compliance.php';

echo "=== Testando Micro-Serviço de Compliance e Antifraude Grid402 ===\n\n";

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

// 1. Testes de Consulta de Sanções (CEIS e CNEP)
echo "[1/4] Testando consulta de sanções administrativas da CGU...\n";
$cleanSanctions = grid402_service_sanctions('00000000000191');
assertTest($cleanSanctions['success'] === true, 'Consulta de sanções retornou success: true');
assertTest($cleanSanctions['has_sanctions'] === false, 'Entidade idônea sem sanções ativas (has_sanctions: false)');
assertTest($cleanSanctions['total_sanctions'] === 0, 'Total de sanções zerado para entidade limpa');
assertTest($cleanSanctions['cnpj_raw'] === '00000000000191', 'CNPJ sanitizado retornado corretamente');

$punishedSanctions = grid402_service_sanctions('11111111000191');
assertTest($punishedSanctions['success'] === true, 'Consulta de entidade sancionada retornou success: true');
assertTest($punishedSanctions['has_sanctions'] === true, 'Entidade sancionada detectada com sucesso (has_sanctions: true)');
assertTest($punishedSanctions['total_sanctions'] > 0, 'Total de sanções maior que zero');
assertTest(isset($punishedSanctions['sanctions'][0]['motivo']), 'Motivo da sanção informado no payload');

$invalidSanctions = grid402_service_sanctions('123456');
assertTest($invalidSanctions['success'] === false, 'CNPJ inválido rejeitado com success: false');
assertTest($invalidSanctions['code'] === 400, 'Código HTTP 400 retornado para CNPJ inválido');

// 2. Testes de Simples Nacional e MEI
echo "\n[2/4] Testando consulta ao Simples Nacional e MEI...\n";
$simplesResult = grid402_service_simples('00000000000191');
assertTest($simplesResult['success'] === true, 'Consulta ao Simples Nacional retornou success: true');
assertTest(isset($simplesResult['optante_simples']), 'Campo optante_simples presente');
assertTest(isset($simplesResult['optante_simei']), 'Campo optante_simei presente');
assertTest(in_array($simplesResult['situacao_atual'], ['NAO_OPTANTE', 'OPTANTE_SIMPLES_NACIONAL', 'OPTANTE_SIMEI_MEI'], true), 'Situação tributária classificada corretamente');

$invalidSimples = grid402_service_simples('999999');
assertTest($invalidSimples['success'] === false, 'CNPJ inválido no Simples rejeitado com sucesso');

// 3. Testes de Compliance Score 360
echo "\n[3/4] Testando cálculo consolidado do Compliance Score 360...\n";
$bbScore = grid402_service_compliance('00000000000191');
assertTest($bbScore['success'] === true, 'Score consolidado retornou success: true');
assertTest($bbScore['score'] >= 85, 'Score de entidade idônea ativa é superior a 85 pontos');
assertTest($bbScore['status'] === 'APROVADO', 'Veredito final classificado como APROVADO');
assertTest(isset($bbScore['summary']['situacao_cadastral']), 'Resumo cadastral consolidado presente');

$punishedScore = grid402_service_compliance('11111111000191');
assertTest($punishedScore['success'] === true, 'Score de entidade sancionada processado com sucesso');
assertTest($punishedScore['score'] < 50, 'Score de entidade sancionada penalizado abaixo de 50 pontos');
assertTest($punishedScore['status'] === 'REJEITADO', 'Veredito de entidade sancionada classificado como REJEITADO');
assertTest(count($punishedScore['flags']) > 0, 'Flags críticas de sanção adicionadas aos apontamentos');

// 4. Testes de Previsões e Teasers de Desafio 402
echo "\n[4/4] Testando geração de teasers e locked fields para o protocolo x402...\n";
$tSanctions = grid402_teaser_sanctions('00000000000191');
assertTest(isset($tSanctions['preview']['tipo_consulta']), 'Teaser de sanções gerado com metadados públicos');
assertTest(in_array('has_sanctions', $tSanctions['locked_fields'], true), 'Campo has_sanctions bloqueado no teaser');

$tSimples = grid402_teaser_simples('00000000000191');
assertTest(isset($tSimples['preview']['regimes_cobertos']), 'Teaser do Simples gerado com regimes cobertos');
assertTest(in_array('optante_simples', $tSimples['locked_fields'], true), 'Campo optante_simples bloqueado no teaser');

$tCompliance = grid402_teaser_compliance('00000000000191');
assertTest(isset($tCompliance['preview']['modelo_analise']), 'Teaser do Compliance Score gerado com sucesso');
assertTest(in_array('score', $tCompliance['locked_fields'], true), 'Campo score bloqueado para auto-liquidação x402');

echo "\n--------------------------------------------------\n";
echo "Resultado: {$testsPassed} de {$totalTests} testes aprovados com sucesso.\n";

if ($testsPassed === $totalTests) {
    echo "Status: SUCESSO TOTAL (100% dos testes aprovados)\n";
    exit(0);
} else {
    echo "Status: FALHA EM TESTES\n";
    exit(1);
}

