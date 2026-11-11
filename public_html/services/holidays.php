<?php
/**
 * Grid402 - Micro-servico de Feriados Nacionais e Calendario Bancario (ANBIMA / Febraban)
 * Resolucao de feriados fixos e moveis com determinacao de dias uteis.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

function grid402_service_holidays(?int $year = null, bool $forceRefresh = false): array {
    $currentYear = (int) date('Y');
    if ($year === null) {
        $year = $currentYear;
    }

    if ($year < 1900 || $year > 2100) {
        return [
            'success' => false,
            'code' => 400,
            'error' => 'InvalidYearParameter',
            'message' => 'O ano informado deve ser um numero inteiro valido entre 1900 e 2100.',
            'year' => $year
        ];
    }

    $cacheDir = defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR : __DIR__ . '/../private_data';
    $cacheFile = $cacheDir . "/holidays_{$year}_cache.json";
    $cacheTtl = 86400; // 24 horas de TTL

    if (!$forceRefresh && file_exists($cacheFile)) {
        $mtime = filemtime($cacheFile);
        if ($mtime !== false && (time() - $mtime) < $cacheTtl) {
            $cached = @json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['holidays'])) {
                $cached['cached'] = true;
                return $cached;
            }
        }
    }

    // 1. Calculo matematico deterministico dos feriados moveis (Meeus / Gauss)
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $pascoaMes = intdiv($h + $l - 7 * $m + 114, 31);
    $pascoaDia = (($h + $l - 7 * $m + 114) % 31) + 1;
    $pascoaTs = gmmktime(0, 0, 0, $pascoaMes, $pascoaDia, $year);

    $carnavalSegundaTs = $pascoaTs - (48 * 86400);
    $carnavalTercaTs = $pascoaTs - (47 * 86400);
    $sextaFeiraSantaTs = $pascoaTs - (2 * 86400);
    $corpusChristiTs = $pascoaTs + (60 * 86400);

    $weekdayNames = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado'
    ];

    // Feriados fixos e moveis
    $rawHolidays = [
        ['date' => sprintf('%04d-01-01', $year), 'name' => 'Confraternizacao Universal', 'type' => 'national', 'banking' => true],
        ['date' => gmdate('Y-m-d', $carnavalSegundaTs), 'name' => 'Carnaval (Segunda-feira)', 'type' => 'banking', 'banking' => true],
        ['date' => gmdate('Y-m-d', $carnavalTercaTs), 'name' => 'Carnaval (Terca-feira)', 'type' => 'banking', 'banking' => true],
        ['date' => gmdate('Y-m-d', $sextaFeiraSantaTs), 'name' => 'Sexta-feira Santa (Paixao de Cristo)', 'type' => 'national', 'banking' => true],
        ['date' => gmdate('Y-m-d', $pascoaTs), 'name' => 'Pascoa', 'type' => 'religious', 'banking' => false],
        ['date' => sprintf('%04d-04-21', $year), 'name' => 'Tiradentes', 'type' => 'national', 'banking' => true],
        ['date' => sprintf('%04d-05-01', $year), 'name' => 'Dia Mundial do Trabalho', 'type' => 'national', 'banking' => true],
        ['date' => gmdate('Y-m-d', $corpusChristiTs), 'name' => 'Corpus Christi', 'type' => 'banking', 'banking' => true],
        ['date' => sprintf('%04d-09-07', $year), 'name' => 'Independencia do Brasil', 'type' => 'national', 'banking' => true],
        ['date' => sprintf('%04d-10-12', $year), 'name' => 'Nossa Senhora Aparecida', 'type' => 'national', 'banking' => true],
        ['date' => sprintf('%04d-11-02', $year), 'name' => 'Finados', 'type' => 'national', 'banking' => true],
        ['date' => sprintf('%04d-11-15', $year), 'name' => 'Proclamacao da Republica', 'type' => 'national', 'banking' => true],
        ['date' => sprintf('%04d-11-20', $year), 'name' => 'Dia Nacional de Zumbi e da Consciencia Negra', 'type' => 'national', 'banking' => true],
        ['date' => sprintf('%04d-12-25', $year), 'name' => 'Natal', 'type' => 'national', 'banking' => true],
    ];

    // Ultimo dia util do ano (sem expediente bancario ao publico)
    $lastDayTs = gmmktime(0, 0, 0, 12, 31, $year);
    $lastDayWeekday = (int) gmdate('w', $lastDayTs);
    if ($lastDayWeekday === 0) { // Domingo
        $lastBankingDayTs = $lastDayTs - (2 * 86400);
    } elseif ($lastDayWeekday === 6) { // Sabado
        $lastBankingDayTs = $lastDayTs - (1 * 86400);
    } else {
        $lastBankingDayTs = $lastDayTs;
    }
    $rawHolidays[] = [
        'date' => gmdate('Y-m-d', $lastBankingDayTs),
        'name' => 'Ultimo dia util do ano (Sem expediente bancario ao publico)',
        'type' => 'banking',
        'banking' => true
    ];

    // Ordenar cronologicamente
    usort($rawHolidays, fn($x, $y) => strcmp($x['date'], $y['date']));

    $holidaysList = [];
    foreach ($rawHolidays as $h) {
        $ts = strtotime($h['date'] . ' 12:00:00 UTC');
        $w = (int) gmdate('w', $ts);
        $holidaysList[] = [
            'date' => $h['date'],
            'name' => $h['name'],
            'type' => $h['type'],
            'day_of_week' => $weekdayNames[$w] ?? '',
            'is_banking_holiday' => $h['banking']
        ];
    }

    $result = [
        'status' => 'success',
        'service' => 'holidays',
        'year' => $year,
        'total_holidays' => count($holidaysList),
        'holidays' => $holidaysList,
        'source' => 'Calendario Nacional e ANBIMA / Febraban',
        'legal_license' => 'Domínio Público / Legislação Federal (Leis nº 662/1949, nº 6.802/1980, nº 14.759/2023 e Lei nº 9.610/1998 Art. 8º, IV)',
        'cached' => false
    ];

    @file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

    return $result;
}

/**
 * Gera amostra gratuita (teaser) no HTTP 402 para o servico de Feriados
 */
function grid402_teaser_holidays(?int $year = null): array {
    $data = grid402_service_holidays($year);
    $holidays = $data['holidays'] ?? [];
    $today = date('Y-m-d');

    $nextHoliday = null;
    foreach ($holidays as $h) {
        if ($h['date'] >= $today) {
            $nextHoliday = $h;
            break;
        }
    }
    if ($nextHoliday === null && !empty($holidays)) {
        $nextHoliday = $holidays[0];
    }

    return [
        'next_holiday' => $nextHoliday,
        'total_holidays_in_year' => count($holidays),
        'locked_fields' => [
            'full_year_schedule',
            'banking_days_count'
        ]
    ];
}

