<?php
/**
 * Testes Unitarios e Funcionais da Calculadora de Dias Uteis Financeiros ANBIMA (Workdays)
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/holidays.php';
require_once __DIR__ . '/../public_html/services/workdays.php';

function run_test(string $name, callable $fn): void {
    try {
        $fn();
        echo "[PASS] {$name}\n";
    } catch (\Throwable $e) {
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        exit(1);
    }
}

echo "=== Testes da Calculadora de Dias Uteis Grid402 ===\n\n";

run_test("Verificacao de dia util, fins de semana e feriados", function() {
    // Sabado
    $sab = grid402_is_workday('2026-12-26');
    assert($sab['valid'] === true);
    assert($sab['is_workday'] === false);
    assert($sab['day_type'] === 'weekend');

    // Domingo
    $dom = grid402_is_workday('2026-12-27');
    assert($dom['valid'] === true);
    assert($dom['is_workday'] === false);
    assert($dom['day_type'] === 'weekend');

    // Natal (Feriado fixo)
    $natal = grid402_is_workday('2026-12-25');
    assert($natal['valid'] === true);
    assert($natal['is_workday'] === false);
    assert($natal['day_type'] === 'holiday');
    assert($natal['holiday_name'] === 'Natal');

    // Corpus Christi (Feriado movel bancario)
    $corpus = grid402_is_workday('2026-06-04');
    assert($corpus['valid'] === true);
    assert($corpus['is_workday'] === false);
    assert($corpus['day_type'] === 'holiday');

    // Dia util comum
    $util = grid402_is_workday('2026-11-06');
    assert($util['valid'] === true);
    assert($util['is_workday'] === true);
    assert($util['day_type'] === 'workday');
});

run_test("Calculo de intervalo no padrao ANBIMA (D+0 exclusivo e final inclusivo)", function() {
    // 2026-12-24 (quinta) ate 2026-12-28 (segunda)
    // 24 (D+0 exclusivo), 25 (Natal - feriado), 26 (sab), 27 (dom), 28 (seg, DU 1)
    $res = grid402_service_workdays([
        'start_date' => '2026-12-24',
        'end_date' => '2026-12-28'
    ]);

    assert($res['status'] === 'success');
    assert($res['mode'] === 'range');
    assert($res['workdays'] === 1, "Esperado 1 dia util entre 24/12 e 28/12/2026, obtido: " . $res['workdays']);
    assert($res['calendar_days'] === 4);
    assert(count($res['holidays_in_range']) >= 1);
});

run_test("Projecao de vencimento positiva (+5 dias uteis)", function() {
    // Sexta-feira 2026-11-06 + 5 dias uteis -> 2026-11-13 (sexta)
    $res = grid402_service_workdays([
        'start_date' => '2026-11-06',
        'days' => 5
    ]);

    assert($res['status'] === 'success');
    assert($res['mode'] === 'projection');
    assert($res['target_date'] === '2026-11-13', "Esperado 2026-11-13, obtido: " . $res['target_date']);
    assert($res['workdays'] === 5);
    assert($res['calendar_days'] === 7);
});

run_test("Projecao de vencimento negativa (-1 dia util)", function() {
    // Segunda-feira 2026-11-09 - 1 dia util -> 2026-11-06 (sexta)
    $res = grid402_service_workdays([
        'start_date' => '2026-11-09',
        'days' => -1
    ]);

    assert($res['status'] === 'success');
    assert($res['target_date'] === '2026-11-06');
    assert($res['workdays'] === 1);
});

run_test("Checagem pontual de dia e proximo dia util", function() {
    // Sexta de Natal 2026-12-25 -> proximo dia util e 2026-12-28
    $res = grid402_service_workdays([
        'date' => '2026-12-25'
    ]);

    assert($res['status'] === 'success');
    assert($res['mode'] === 'check');
    assert($res['is_workday'] === false);
    assert($res['next_workday'] === '2026-12-28');
});

run_test("Teaser gratuito no HTTP 402", function() {
    $teaser = grid402_teaser_workdays(['date' => '2026-12-25']);
    assert($teaser['is_workday'] === false);
    assert($teaser['holiday_name'] === 'Natal');
    assert(!empty($teaser['locked_fields']));
    assert(in_array('workdays', $teaser['locked_fields']));
});

run_test("Tratamento defensivo de limites e erros", function() {
    // Data invalida
    $errDate = grid402_service_workdays(['start_date' => 'data-invalida']);
    assert($errDate['success'] === false);
    assert($errDate['code'] === 400);

    // End date menor que start date
    $errRange = grid402_service_workdays([
        'start_date' => '2026-12-28',
        'end_date' => '2026-12-23'
    ]);
    assert($errRange['success'] === false);
    assert($errRange['code'] === 400);

    // Limite de 5000 dias excedido
    $errDays = grid402_service_workdays([
        'start_date' => '2026-01-01',
        'days' => 5001
    ]);
    assert($errDays['success'] === false);
    assert($errDays['code'] === 400);
});

echo "\nTodos os testes da Calculadora de Dias Uteis foram APROVADOS com 100% de sucesso!\n";
