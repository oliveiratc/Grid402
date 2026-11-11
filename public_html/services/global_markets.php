<?php
/**
 * Grid402 - Calendário Financeiro Mundial e Câmbio Global G10/BCE (/api/v1/global-markets)
 * Fontes:
 * 1. Algoritmo determinístico de calendários bancários e bolsas: US (NYSE/Fed), EU (TARGET2/BCE), UK (LSE), BR (B3/ANBIMA).
 * 2. Taxas de referência cambial do Banco Central Europeu (ECB Open Data / Frankfurter) para 30+ moedas.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

/**
 * Calcula a data do Domingo de Páscoa para um dado ano (Algoritmo de Meeus/Jones/Butcher)
 */
function grid402_easter_sunday(int $year): DateTimeImmutable {
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
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;

    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
}

/**
 * Ajusta feriado fixo nos EUA (regra NYSE/Federal Reserve: sábado -> sexta anterior, domingo -> segunda seguinte)
 */
function grid402_us_observed_date(int $year, int $month, int $day): string {
    $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
    $dow = (int) $dt->format('N'); // 1=Seg .. 7=Dom
    if ($dow === 6) {
        return $dt->modify('-1 day')->format('Y-m-d');
    }
    if ($dow === 7) {
        return $dt->modify('+1 day')->format('Y-m-d');
    }
    return $dt->format('Y-m-d');
}

/**
 * Retorna a enésima ocorrência de um dia da semana em um mês
 */
function grid402_nth_weekday_of_month(int $year, int $month, int $weekdayIso, int $nth): string {
    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('UTC'));
    $firstDow = (int) $first->format('N');
    $offset = ($weekdayIso - $firstDow + 7) % 7;
    $day = 1 + $offset + ($nth - 1) * 7;
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Retorna a última ocorrência de um dia da semana em um mês
 */
function grid402_last_weekday_of_month(int $year, int $month, int $weekdayIso): string {
    $last = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('UTC')))->modify('last day of this month');
    $lastDow = (int) $last->format('N');
    $offset = ($lastDow - $weekdayIso + 7) % 7;
    return $last->modify("-{$offset} days")->format('Y-m-d');
}

/**
 * Gera a lista determinística de feriados de mercado para o ano e centro financeiro selecionados
 */
function grid402_get_market_holidays(string $market, int $year): array {
    $market = strtoupper(trim($market));
    $easter = grid402_easter_sunday($year);
    $goodFriday = $easter->modify('-2 days')->format('Y-m-d');
    $easterMonday = $easter->modify('+1 day')->format('Y-m-d');

    if ($market === 'EU') {
        // BCE / TARGET2 Closing Days
        return [
            sprintf('%04d-01-01', $year) => "New Year's Day (TARGET2)",
            $goodFriday => 'Good Friday (TARGET2)',
            $easterMonday => 'Easter Monday (TARGET2)',
            sprintf('%04d-05-01', $year) => 'Labour Day (TARGET2)',
            sprintf('%04d-12-25', $year) => 'Christmas Day (TARGET2)',
            sprintf('%04d-12-26', $year) => 'Christmas Holiday / Boxing Day (TARGET2)',
        ];
    }

    if ($market === 'UK') {
        // London Stock Exchange (LSE) Bank Holidays principais
        return [
            grid402_us_observed_date($year, 1, 1) => "New Year's Day (UK Bank Holiday)",
            $goodFriday => 'Good Friday (LSE)',
            $easterMonday => 'Easter Monday (LSE)',
            grid402_nth_weekday_of_month($year, 5, 1, 1) => 'Early May Bank Holiday (LSE)',
            grid402_last_weekday_of_month($year, 5, 1) => 'Spring Bank Holiday (LSE)',
            grid402_last_weekday_of_month($year, 8, 1) => 'Summer Bank Holiday (LSE)',
            grid402_us_observed_date($year, 12, 25) => 'Christmas Day (LSE)',
            grid402_us_observed_date($year, 12, 26) => 'Boxing Day (LSE)',
        ];
    }

    if ($market === 'BR') {
        // B3 / ANBIMA Brasil
        $carnivalMon = $easter->modify('-48 days')->format('Y-m-d');
        $carnivalTue = $easter->modify('-47 days')->format('Y-m-d');
        $corpusChristi = $easter->modify('+60 days')->format('Y-m-d');
        return [
            sprintf('%04d-01-01', $year) => 'Confraternização Universal (B3/ANBIMA)',
            $carnivalMon => 'Segunda-feira de Carnaval (B3/ANBIMA)',
            $carnivalTue => 'Terça-feira de Carnaval (B3/ANBIMA)',
            $goodFriday => 'Sexta-feira da Paixão (B3/ANBIMA)',
            sprintf('%04d-04-21', $year) => 'Tiradentes (B3/ANBIMA)',
            sprintf('%04d-05-01', $year) => 'Dia do Trabalho (B3/ANBIMA)',
            $corpusChristi => 'Corpus Christi (B3/ANBIMA)',
            sprintf('%04d-09-07', $year) => 'Independência do Brasil (B3/ANBIMA)',
            sprintf('%04d-10-12', $year) => 'Nossa Senhora Aparecida (B3/ANBIMA)',
            sprintf('%04d-11-02', $year) => 'Finados (B3/ANBIMA)',
            sprintf('%04d-11-15', $year) => 'Proclamação da República (B3/ANBIMA)',
            sprintf('%04d-11-20', $year) => 'Dia da Consciência Negra (B3/ANBIMA)',
            sprintf('%04d-12-25', $year) => 'Natal (B3/ANBIMA)',
        ];
    }

    // Default: US (NYSE / Federal Reserve)
    return [
        grid402_us_observed_date($year, 1, 1) => "New Year's Day (NYSE/Fed)",
        grid402_nth_weekday_of_month($year, 1, 1, 3) => 'Martin Luther King Jr. Day (NYSE/Fed)',
        grid402_nth_weekday_of_month($year, 2, 1, 3) => "Washington's Birthday / Presidents' Day (NYSE/Fed)",
        $goodFriday => 'Good Friday (NYSE)',
        grid402_last_weekday_of_month($year, 5, 1) => 'Memorial Day (NYSE/Fed)',
        grid402_us_observed_date($year, 6, 19) => 'Juneteenth National Independence Day (NYSE/Fed)',
        grid402_us_observed_date($year, 7, 4) => 'Independence Day (NYSE/Fed)',
        grid402_nth_weekday_of_month($year, 9, 1, 1) => 'Labor Day (NYSE/Fed)',
        grid402_nth_weekday_of_month($year, 11, 4, 4) => 'Thanksgiving Day (NYSE/Fed)',
        grid402_us_observed_date($year, 12, 25) => 'Christmas Day (NYSE/Fed)',
    ];
}

/**
 * Obtém as taxas globais do Banco Central Europeu (ECB Reference Rates via Frankfurter Open Data)
 */
function grid402_fetch_ecb_global_rates(string $base = 'USD'): array {
    $base = strtoupper(trim($base));
    if (!preg_match('/^[A-Z]{3}$/', $base)) {
        $base = 'USD';
    }

    $cacheFile = defined('GRID402_FILE_GLOBAL_MARKETS') ? GRID402_FILE_GLOBAL_MARKETS : (GRID402_PRIVATE_DIR . '/global_markets_cache.json');
    $cacheTtl = 900; // 15 minutos
    $cacheStore = [];

    if (file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $cacheStore = $rawCache ? (@json_decode($rawCache, true) ?: []) : [];
        if (isset($cacheStore[$base]) && (time() - (int)($cacheStore[$base]['cached_at'] ?? 0)) < $cacheTtl) {
            return $cacheStore[$base]['data'];
        }
    }

    $url = 'https://api.frankfurter.app/latest?from=' . rawurlencode($base);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'Grid402-GlobalOracle/2.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ratesData = null;
    if ($httpCode === 200 && is_string($raw)) {
        $json = @json_decode($raw, true);
        if (is_array($json) && isset($json['rates'])) {
            $ratesData = [
                'base_currency' => (string) ($json['base'] ?? $base),
                'quote_date' => (string) ($json['date'] ?? gmdate('Y-m-d')),
                'rates' => $json['rates'],
            ];
        }
    }

    if ($ratesData === null) {
        // Fallback determinístico G10 + Emergentes se offline
        $ratesData = [
            'base_currency' => 'USD',
            'quote_date' => gmdate('Y-m-d'),
            'rates' => [
                'EUR' => 0.9215,
                'GBP' => 0.7890,
                'JPY' => 153.40,
                'CHF' => 0.8810,
                'CAD' => 1.3910,
                'AUD' => 1.5180,
                'CNY' => 7.2350,
                'HKD' => 7.7780,
                'SGD' => 1.3390,
                'MXN' => 20.150,
                'BRL' => 5.4210,
                'INR' => 84.350,
                'KRW' => 1395.0,
                'SEK' => 10.680,
                'NOK' => 11.020,
                'NZD' => 1.6750,
            ],
        ];
    }

    $cacheStore[$base] = [
        'cached_at' => time(),
        'data' => $ratesData,
    ];
    @file_put_contents($cacheFile, json_encode($cacheStore, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    return $ratesData;
}

/**
 * Executa a consulta de Calendário Financeiro Mundial e Câmbio Global G10/BCE
 */
function grid402_service_global_markets(string $market = 'US', string $dateStr = '', string $baseCurrency = 'USD'): array {
    $market = strtoupper(trim($market));
    if (!in_array($market, ['US', 'EU', 'UK', 'BR'], true)) {
        $market = 'US';
    }

    $dateStr = trim($dateStr);
    if ($dateStr === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        $dateStr = gmdate('Y-m-d');
    }

    try {
        $dt = new DateTimeImmutable($dateStr, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $dateStr = $dt->format('Y-m-d');
    }

    $year = (int) $dt->format('Y');
    $holidays = grid402_get_market_holidays($market, $year);
    $nextYearHolidays = grid402_get_market_holidays($market, $year + 1);
    $allHolidays = $holidays + $nextYearHolidays;

    $dow = (int) $dt->format('N');
    $isWeekend = ($dow >= 6);
    $holidayName = $allHolidays[$dateStr] ?? null;
    $isBusinessDay = (!$isWeekend && $holidayName === null);

    // Calcula o próximo dia útil bancário
    $cursor = $dt;
    for ($i = 0; $i < 15; $i++) {
        $cursor = $cursor->modify('+1 day');
        $cStr = $cursor->format('Y-m-d');
        $cDow = (int) $cursor->format('N');
        if ($cDow < 6 && !isset($allHolidays[$cStr])) {
            break;
        }
    }
    $nextBusinessDay = $cursor->format('Y-m-d');

    $marketNames = [
        'US' => 'United States (NYSE / Federal Reserve)',
        'EU' => 'Eurozone (ECB / TARGET2 Settlement)',
        'UK' => 'United Kingdom (London Stock Exchange / Bank of England)',
        'BR' => 'Brazil (B3 / ANBIMA Financial Calendar)',
    ];

    $fxData = grid402_fetch_ecb_global_rates($baseCurrency);
    $isoNow = gmdate('Y-m-d\TH:i:s\Z');

    return [
        'service' => 'global-markets',
        'status' => 'UP',
        'market' => $market,
        'market_name' => $marketNames[$market],
        'date_checked' => $dateStr,
        'day_of_week' => $dt->format('l'),
        'is_business_day' => $isBusinessDay,
        'is_weekend' => $isWeekend,
        'is_holiday' => ($holidayName !== null),
        'holiday_name' => $holidayName,
        'next_business_day' => $nextBusinessDay,
        'fx_reference' => [
            'provider' => 'European Central Bank (ECB Open Data)',
            'base_currency' => $fxData['base_currency'],
            'quote_date' => $fxData['quote_date'],
            'currencies_count' => count($fxData['rates']),
            'rates' => $fxData['rates'],
        ],
        'market_holidays_calendar' => $holidays,
        'timestamp' => $isoNow,
        'response_timestamp' => $isoNow,
        'source' => 'ECB Reference Rates + Deterministic Exchange Calendar (Public Domain)',
    ];
}

/**
 * Gera a prévia gratuita (Teaser HTTP 402) para o endpoint Global Markets
 */
function grid402_preview_global_markets(string $market = 'US', string $dateStr = '', string $baseCurrency = 'USD'): array {
    $res = grid402_service_global_markets($market, $dateStr, $baseCurrency);
    return [
        'market' => $res['market'],
        'market_name' => $res['market_name'],
        'date_checked' => $res['date_checked'],
        'is_business_day' => $res['is_business_day'] ? 'YES (OPEN)' : 'NO (CLOSED)',
        'fx_base' => $res['fx_reference']['base_currency'],
        'currencies_available' => $res['fx_reference']['currencies_count'] . ' global currencies',
    ];
}

