<?php
/**
 * Grid402 - Micro-servico de Calculadora de Dias Uteis Financeiros ANBIMA
 * Calculo deterministico stateless de prazos bancarios, intervalo entre datas e projecao temporal.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/holidays.php';

/**
 * Obtem mapa indexado de feriados bancarios para um determinado ano com cache em memoria
 */
function grid402_get_banking_holidays_map(int $year): array {
    static $memoryCache = [];
    if (!isset($memoryCache[$year])) {
        $data = grid402_service_holidays($year);
        $map = [];
        if (isset($data['holidays']) && is_array($data['holidays'])) {
            foreach ($data['holidays'] as $h) {
                if (!empty($h['is_banking_holiday'])) {
                    $map[$h['date']] = $h['name'];
                }
            }
        }
        $memoryCache[$year] = $map;
    }
    return $memoryCache[$year];
}

/**
 * Verifica se uma data especifica (YYYY-MM-DD) e dia util bancario
 */
function grid402_is_workday(string $dateStr): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return [
            'valid' => false,
            'error' => 'InvalidDateFormat'
        ];
    }

    $parts = explode('-', $dateStr);
    $year = (int) $parts[0];
    $month = (int) $parts[1];
    $day = (int) $parts[2];

    if (!checkdate($month, $day, $year) || $year < 1900 || $year > 2100) {
        return [
            'valid' => false,
            'error' => 'DateOutOfBounds'
        ];
    }

    $ts = gmmktime(12, 0, 0, $month, $day, $year);
    $weekday = (int) gmdate('w', $ts);

    if ($weekday === 0) {
        return [
            'valid' => true,
            'is_workday' => false,
            'day_type' => 'weekend',
            'description' => 'Domingo',
            'holiday_name' => null
        ];
    }

    if ($weekday === 6) {
        return [
            'valid' => true,
            'is_workday' => false,
            'day_type' => 'weekend',
            'description' => 'Sabado',
            'holiday_name' => null
        ];
    }

    $holidays = grid402_get_banking_holidays_map($year);
    if (isset($holidays[$dateStr])) {
        return [
            'valid' => true,
            'is_workday' => false,
            'day_type' => 'holiday',
            'description' => $holidays[$dateStr],
            'holiday_name' => $holidays[$dateStr]
        ];
    }

    return [
        'valid' => true,
        'is_workday' => true,
        'day_type' => 'workday',
        'description' => 'Dia util bancario',
        'holiday_name' => null
    ];
}

/**
 * Encontra o proximo dia util bancario a partir de uma data de referencia
 */
function grid402_get_next_workday(string $dateStr): string {
    $ts = strtotime($dateStr . ' 12:00:00 UTC');
    for ($i = 1; $i <= 30; $i++) {
        $nextTs = $ts + ($i * 86400);
        $candidate = gmdate('Y-m-d', $nextTs);
        $check = grid402_is_workday($candidate);
        if (!empty($check['valid']) && !empty($check['is_workday'])) {
            return $candidate;
        }
    }
    return $dateStr;
}

/**
 * Servico principal da Calculadora de Dias Uteis Financeiros
 */
function grid402_service_workdays(array $params): array {
    $startDate = isset($params['start_date']) ? trim((string) $params['start_date']) : (isset($params['date']) ? trim((string) $params['date']) : null);
    $endDate = isset($params['end_date']) ? trim((string) $params['end_date']) : null;
    $hasDays = isset($params['days']) && is_numeric($params['days']);
    $days = $hasDays ? (int) $params['days'] : null;

    if ($startDate === null || $startDate === '') {
        $startDate = gmdate('Y-m-d');
    }

    $startCheck = grid402_is_workday($startDate);
    if (empty($startCheck['valid'])) {
        return [
            'success' => false,
            'code' => 400,
            'error' => 'InvalidDateParameter',
            'message' => 'O parametro de data inicial deve estar no formato ISO 8601 (YYYY-MM-DD) e situar-se entre 1900 e 2100.'
        ];
    }

    // Modo 1: Contagem entre duas datas (Range)
    if ($endDate !== null && $endDate !== '') {
        $endCheck = grid402_is_workday($endDate);
        if (empty($endCheck['valid'])) {
            return [
                'success' => false,
                'code' => 400,
                'error' => 'InvalidEndDateParameter',
                'message' => 'O parametro end_date deve estar no formato ISO 8601 (YYYY-MM-DD) e situar-se entre 1900 e 2100.'
            ];
        }

        $startTs = strtotime($startDate . ' 12:00:00 UTC');
        $endTs = strtotime($endDate . ' 12:00:00 UTC');

        if ($endTs < $startTs) {
            return [
                'success' => false,
                'code' => 400,
                'error' => 'InvalidDateRange',
                'message' => 'A data final (end_date) nao pode ser anterior a data inicial (start_date).'
            ];
        }

        $calendarDays = (int) round(($endTs - $startTs) / 86400);
        if ($calendarDays > (365 * 20)) {
            return [
                'success' => false,
                'code' => 400,
                'error' => 'RangeLimitExceeded',
                'message' => 'O intervalo maximo permitido para calculo de dias uteis e de 20 anos.'
            ];
        }

        $workdaysCount = 0;
        $holidaysInRange = [];

        // Convencao ANBIMA: D+0 exclusivo no inicio e inclusivo no final
        for ($i = 1; $i <= $calendarDays; $i++) {
            $currTs = $startTs + ($i * 86400);
            $currDate = gmdate('Y-m-d', $currTs);
            $cCheck = grid402_is_workday($currDate);
            if (!empty($cCheck['is_workday'])) {
                $workdaysCount++;
            } elseif ($cCheck['day_type'] === 'holiday') {
                $holidaysInRange[] = [
                    'date' => $currDate,
                    'name' => $cCheck['holiday_name']
                ];
            }
        }

        return [
            'status' => 'success',
            'service' => 'workdays',
            'mode' => 'range',
            'convention' => 'ANBIMA (exclusive start, inclusive end)',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'workdays' => $workdaysCount,
            'calendar_days' => $calendarDays,
            'start_is_workday' => $startCheck['is_workday'],
            'end_is_workday' => $endCheck['is_workday'],
            'holidays_in_range' => $holidaysInRange,
            'legal_license' => 'Domínio Público / Algoritmo Determinístico de Prazos Bancários (Lei nº 9.610/1998 Art. 8º, I e II)',
            'tariff_usdc' => '0.0050'
        ];
    }

    // Modo 2: Projecao de Prazo por Deslocamento (Projection)
    if ($hasDays) {
        if (abs($days) > 5000) {
            return [
                'success' => false,
                'code' => 400,
                'error' => 'DaysLimitExceeded',
                'message' => 'O deslocamento maximo permitido para projecao de dias uteis e de 5000 dias.'
            ];
        }

        $currTs = strtotime($startDate . ' 12:00:00 UTC');
        $step = ($days >= 0) ? 1 : -1;
        $needed = abs($days);
        $accumulated = 0;
        $calendarDays = 0;
        $holidaysInProjection = [];

        while ($accumulated < $needed) {
            $currTs += ($step * 86400);
            $calendarDays++;
            $currDate = gmdate('Y-m-d', $currTs);
            $cCheck = grid402_is_workday($currDate);
            if (!empty($cCheck['is_workday'])) {
                $accumulated++;
            } elseif ($cCheck['day_type'] === 'holiday') {
                $holidaysInProjection[] = [
                    'date' => $currDate,
                    'name' => $cCheck['holiday_name']
                ];
            }
        }

        $targetDate = gmdate('Y-m-d', $currTs);

        return [
            'status' => 'success',
            'service' => 'workdays',
            'mode' => 'projection',
            'convention' => 'ANBIMA',
            'start_date' => $startDate,
            'target_date' => $targetDate,
            'days_requested' => $days,
            'workdays' => abs($days),
            'calendar_days' => $calendarDays,
            'start_is_workday' => $startCheck['is_workday'],
            'target_is_workday' => true,
            'holidays_in_projection' => $holidaysInProjection,
            'legal_license' => 'Domínio Público / Algoritmo Determinístico de Prazos Bancários (Lei nº 9.610/1998 Art. 8º, I e II)',
            'tariff_usdc' => '0.0050'
        ];
    }

    // Modo 3: Checagem Pontual (Check)
    $nextWorkday = $startCheck['is_workday'] ? $startDate : grid402_get_next_workday($startDate);

    return [
        'status' => 'success',
        'service' => 'workdays',
        'mode' => 'check',
        'date' => $startDate,
        'is_workday' => $startCheck['is_workday'],
        'day_type' => $startCheck['day_type'],
        'description' => $startCheck['description'],
        'holiday_name' => $startCheck['holiday_name'],
        'next_workday' => $nextWorkday,
        'legal_license' => 'Domínio Público / Algoritmo Determinístico de Prazos Bancários (Lei nº 9.610/1998 Art. 8º, I e II)',
        'tariff_usdc' => '0.0050'
    ];
}

/**
 * Gera amostra gratuita (teaser) no HTTP 402 para o servico de Dias Uteis
 */
function grid402_teaser_workdays(array $params = []): array {
    $baseDate = isset($params['start_date']) ? trim((string) $params['start_date']) : (isset($params['date']) ? trim((string) $params['date']) : gmdate('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $baseDate)) {
        $baseDate = gmdate('Y-m-d');
    }

    $check = grid402_is_workday($baseDate);
    $year = (int) substr($baseDate, 0, 4);
    $hData = grid402_service_holidays($year);

    $nextHoliday = null;
    if (isset($hData['holidays']) && is_array($hData['holidays'])) {
        foreach ($hData['holidays'] as $h) {
            if ($h['date'] >= $baseDate && !empty($h['is_banking_holiday'])) {
                $nextHoliday = $h;
                break;
            }
        }
    }

    return [
        'base_date' => $baseDate,
        'is_workday' => $check['is_workday'] ?? false,
        'day_type' => $check['day_type'] ?? 'unknown',
        'holiday_name' => $check['holiday_name'] ?? null,
        'next_banking_holiday' => $nextHoliday,
        'locked_fields' => [
            'workdays',
            'calendar_days',
            'target_date',
            'holidays_in_range'
        ]
    ];
}

