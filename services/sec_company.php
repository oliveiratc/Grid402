<?php
/**
 * Grid402 - Micro-Servico Corporativo SEC EDGAR (/api/v1/sec-company)
 *
 * Fontes 100% publicas sem API key paga (SEC EDGAR):
 * - Mapa ticker->CIK: https://www.sec.gov/files/company_tickers.json (cache 24h)
 * - Perfil e filings: https://data.sec.gov/submissions/CIK{cik10}.json (cache 3600s)
 * - Fatos XBRL opcionais: https://data.sec.gov/api/xbrl/companyfacts/CIK{cik10}.json (teto rigido 8 MB)
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Valida e normaliza parametros de consulta de /api/v1/sec-company.
 *
 * @return array<string, mixed>
 */
function grid402_sec_company_validate_input(
    string $ticker = '',
    string $cik = '',
    int $limit = 10,
    string $forms = '',
    mixed $includeFacts = false
): array {
    $cleanTicker = strtoupper(trim($ticker));
    $cleanCikRaw = trim($cik);

    if ($cleanTicker === '' && $cleanCikRaw === '') {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'MissingSecIdentifier',
            'message' => 'Informe o parametro "ticker" (ex.: AAPL) ou "cik" (ex.: 0000320193).',
        ];
    }

    if ($cleanTicker !== '' && !preg_match('/^[A-Z0-9.\-]{1,10}$/', $cleanTicker)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidTickerParameter',
            'message' => 'Parametro "ticker" invalido. Use apenas 1 a 10 caracteres alfanumericos, ponto ou hifen.',
        ];
    }

    $cikNormalized = '';
    if ($cleanCikRaw !== '') {
        $digitsOnly = ltrim($cleanCikRaw, " \t\n\r\0\x0B");
        if (!preg_match('/^[0-9]{1,10}$/', $digitsOnly)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'InvalidCikParameter',
                'message' => 'Parametro "cik" invalido. Informe de 1 a 10 digitos numericos.',
            ];
        }
        $cikNormalized = str_pad($digitsOnly, 10, '0', STR_PAD_LEFT);
    }

    $clampedLimit = max(1, min(25, $limit > 0 ? $limit : 10));

    $formsList = [];
    if (trim($forms) !== '') {
        foreach (explode(',', $forms) as $rawForm) {
            $f = strtoupper(trim($rawForm));
            if ($f === '') {
                continue;
            }
            if (!preg_match('/^[A-Z0-9\-\/]{1,16}$/', $f)) {
                return [
                    'ok' => false,
                    'code' => 400,
                    'error' => 'InvalidFormsParameter',
                    'message' => 'Parametro "forms" contem formato de formulario SEC invalido.',
                ];
            }
            $formsList[] = $f;
        }
        $formsList = array_values(array_unique($formsList));
    }

    $factsBool = false;
    if (is_bool($includeFacts)) {
        $factsBool = $includeFacts;
    } elseif (is_numeric($includeFacts)) {
        $factsBool = ((int) $includeFacts) === 1;
    } elseif (is_string($includeFacts)) {
        $factsBool = in_array(strtolower(trim($includeFacts)), ['1', 'true', 'yes', 'on'], true);
    }

    return [
        'ok' => true,
        'ticker' => $cleanTicker,
        'cik' => $cikNormalized,
        'limit' => $clampedLimit,
        'forms' => $formsList,
        'include_facts' => $factsBool,
    ];
}

/**
 * Executa GET HTTPS para SEC EDGAR respeitando User-Agent institucional e teto de bytes.
 * Suporta mock em $GLOBALS['grid402_sec_company_http_override'].
 *
 * @return array{ok: bool, status: int, data: mixed, error: ?string, size_exceeded?: bool}
 */
function grid402_sec_company_http_get(string $url, int $timeoutSec = 5, int $maxBytes = 8388608): array {
    $override = $GLOBALS['grid402_sec_company_http_override'] ?? null;
    if (is_callable($override)) {
        return $override($url, $timeoutSec, $maxBytes);
    }

    $ch = curl_init($url);
    $buffer = '';
    $sizeExceeded = false;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSec),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Grid402/3.8 (contato@grid402.online)',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$buffer, &$sizeExceeded, $maxBytes): int {
            $chunkLen = strlen($chunk);
            if ($maxBytes > 0 && (strlen($buffer) + $chunkLen) > $maxBytes) {
                $sizeExceeded = true;
                return 0;
            }
            $buffer .= $chunk;
            return $chunkLen;
        },
    ]);

    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($sizeExceeded) {
        return [
            'ok' => false,
            'status' => 413,
            'data' => null,
            'error' => 'PayloadSizeLimitExceeded',
            'size_exceeded' => true,
        ];
    }

    if ($status !== 200 || $buffer === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $err !== '' ? $err : ('HTTP ' . $status),
        ];
    }

    $decoded = @json_decode($buffer, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => 'InvalidJSON',
        ];
    }

    return ['ok' => true, 'status' => 200, 'data' => $decoded, 'error' => null];
}

/**
 * Resolve ticker -> CIK (10 digitos) usando cache Zero-DB compacto de 24h.
 *
 * @return array{ok: bool, cik?: string, title?: string, status?: int, error?: string}
 */
function grid402_sec_company_resolve_cik(string $ticker, string $privateDir, bool $useOverride): array {
    $tickerUpper = strtoupper(trim($ticker));
    $cacheFile = $privateDir . '/sec_tickers_cache.json';
    $ttl = 86400;

    $cachedMap = null;
    if (!$useOverride && file_exists($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = $raw ? @json_decode($raw, true) : null;
        if (is_array($decoded) && is_array($decoded['map'] ?? null)) {
            $cachedMap = $decoded['map'];
            if (isset($decoded['cached_at']) && (time() - (int) $decoded['cached_at']) < $ttl) {
                if (isset($cachedMap[$tickerUpper])) {
                    return [
                        'ok' => true,
                        'cik' => (string) $cachedMap[$tickerUpper]['cik'],
                        'title' => (string) ($cachedMap[$tickerUpper]['title'] ?? ''),
                    ];
                }
                return ['ok' => false, 'status' => 404, 'error' => 'TickerNotFound'];
            }
        }
    }

    $resp = grid402_sec_company_http_get('https://www.sec.gov/files/company_tickers.json', 6, 16777216);
    if (!empty($resp['ok']) && is_array($resp['data'])) {
        $map = [];
        foreach ($resp['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $t = strtoupper(trim((string) ($row['ticker'] ?? '')));
            $cikRaw = (string) ($row['cik_str'] ?? $row['cik'] ?? '');
            if ($t === '' || $cikRaw === '' || !preg_match('/^[0-9]+$/', $cikRaw)) {
                continue;
            }
            $map[$t] = [
                'cik' => str_pad($cikRaw, 10, '0', STR_PAD_LEFT),
                'title' => trim((string) ($row['title'] ?? '')),
            ];
        }

        if (!$useOverride && !empty($map) && function_exists('grid402_atomic_write')) {
            grid402_atomic_write($cacheFile, [
                'cached_at' => time(),
                'count' => count($map),
                'map' => $map,
            ]);
        }

        if (isset($map[$tickerUpper])) {
            return [
                'ok' => true,
                'cik' => (string) $map[$tickerUpper]['cik'],
                'title' => (string) ($map[$tickerUpper]['title'] ?? ''),
            ];
        }
        return ['ok' => false, 'status' => 404, 'error' => 'TickerNotFound'];
    }

    if (is_array($cachedMap) && isset($cachedMap[$tickerUpper])) {
        return [
            'ok' => true,
            'cik' => (string) $cachedMap[$tickerUpper]['cik'],
            'title' => (string) ($cachedMap[$tickerUpper]['title'] ?? ''),
        ];
    }

    return [
        'ok' => false,
        'status' => 503,
        'error' => (string) ($resp['error'] ?? 'SecTickersUnavailable'),
    ];
}

/**
 * Extrai metricas US-GAAP mais recentes do JSON companyfacts da SEC.
 *
 * @param array<string, mixed> $factsJson
 * @return array<string, mixed>
 */
function grid402_sec_company_extract_facts(array $factsJson): array {
    $usGaap = is_array($factsJson['facts']['us-gaap'] ?? null) ? $factsJson['facts']['us-gaap'] : [];
    if (empty($usGaap)) {
        return [];
    }

    $tagCandidates = [
        'revenue' => ['tags' => ['RevenueFromContractWithCustomerExcludingAssessedTax', 'Revenues', 'SalesRevenueNet'], 'units' => ['USD']],
        'net_income' => ['tags' => ['NetIncomeLoss'], 'units' => ['USD']],
        'assets' => ['tags' => ['Assets'], 'units' => ['USD']],
        'liabilities' => ['tags' => ['Liabilities'], 'units' => ['USD']],
        'cash_and_equivalents' => ['tags' => ['CashAndCashEquivalentsAtCarryingValue', 'CashCashEquivalentsRestrictedCashAndRestrictedCashEquivalents'], 'units' => ['USD']],
        'eps_diluted' => ['tags' => ['EarningsPerShareDiluted'], 'units' => ['USD/shares']],
    ];

    $summary = [];
    foreach ($tagCandidates as $metricKey => $spec) {
        foreach ($spec['tags'] as $tagName) {
            if (!isset($usGaap[$tagName]['units']) || !is_array($usGaap[$tagName]['units'])) {
                continue;
            }
            $unitsBlock = $usGaap[$tagName]['units'];
            foreach ($spec['units'] as $preferredUnit) {
                if (empty($unitsBlock[$preferredUnit]) || !is_array($unitsBlock[$preferredUnit])) {
                    continue;
                }
                $entries = $unitsBlock[$preferredUnit];
                usort($entries, static function (array $a, array $b): int {
                    $cmpEnd = strcmp((string) ($b['end'] ?? ''), (string) ($a['end'] ?? ''));
                    if ($cmpEnd !== 0) {
                        return $cmpEnd;
                    }
                    return strcmp((string) ($b['filed'] ?? ''), (string) ($a['filed'] ?? ''));
                });
                $latest = $entries[0] ?? null;
                if (is_array($latest) && isset($latest['val']) && is_numeric($latest['val'])) {
                    $summary[$metricKey] = [
                        'tag' => $tagName,
                        'value' => $latest['val'] + 0,
                        'unit' => $preferredUnit,
                        'end_date' => $latest['end'] ?? null,
                        'filed' => $latest['filed'] ?? null,
                        'form' => $latest['form'] ?? null,
                        'fy' => $latest['fy'] ?? null,
                        'fp' => $latest['fp'] ?? null,
                    ];
                    break 2;
                }
            }
        }
    }

    return $summary;
}

/**
 * Teaser gratuito para resposta HTTP 402.
 *
 * @return array<string, mixed>
 */
function grid402_service_sec_company_preview(
    string $ticker = 'AAPL',
    string $cik = '',
    int $limit = 10,
    string $forms = '',
    mixed $includeFacts = false
): array {
    $val = grid402_sec_company_validate_input($ticker !== '' || $cik !== '' ? $ticker : 'AAPL', $cik, $limit, $forms, $includeFacts);
    return [
        'preview' => [
            'ticker' => $val['ok'] && $val['ticker'] !== '' ? $val['ticker'] : null,
            'cik' => $val['ok'] && $val['cik'] !== '' ? $val['cik'] : null,
            'limit' => $val['ok'] ? $val['limit'] : 10,
            'forms_filter' => $val['ok'] ? $val['forms'] : [],
            'include_facts' => $val['ok'] ? $val['include_facts'] : false,
            'upstream' => 'US SEC EDGAR Public API (data.sec.gov)',
        ],
        'locked_fields' => ['cik', 'company_name', 'sic', 'exchanges', 'filings', 'facts', '_oracle_proof'],
    ];
}

/**
 * Consulta corporativa oficial na SEC EDGAR com cache Zero-DB (3600s).
 *
 * @return array<string, mixed>
 */
function grid402_service_sec_company(
    string $ticker = '',
    string $cik = '',
    int $limit = 10,
    string $forms = '',
    mixed $includeFacts = false
): array {
    $val = grid402_sec_company_validate_input($ticker, $cik, $limit, $forms, $includeFacts);
    if (empty($val['ok'])) {
        return [
            'success' => false,
            'error' => $val['error'],
            'message' => $val['message'],
            'code' => (int) ($val['code'] ?? 400),
        ];
    }

    $tickerClean = (string) $val['ticker'];
    $cikNorm = (string) $val['cik'];
    $limitVal = (int) $val['limit'];
    $formsFilter = $val['forms'];
    $wantFacts = (bool) $val['include_facts'];

    $privateDir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/../private_data');
    $useOverride = is_callable($GLOBALS['grid402_sec_company_http_override'] ?? null);

    $cacheKey = hash('sha256', $tickerClean . '|' . $cikNorm . '|' . $limitVal . '|' . implode(',', $formsFilter) . '|' . ($wantFacts ? '1' : '0'));
    $cacheFile = $privateDir . '/cache_sec_company_' . substr($cacheKey, 0, 24) . '.json';
    $cacheTtl = 3600;

    if (!$useOverride && file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = $rawCache ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < $cacheTtl) {
            $decodedCache['cached'] = true;
            return $decodedCache;
        }
    }

    if ($cikNorm === '' && $tickerClean !== '') {
        $resolved = grid402_sec_company_resolve_cik($tickerClean, $privateDir, $useOverride);
        if (empty($resolved['ok'])) {
            $errCode = (int) ($resolved['status'] ?? 503);
            if ($errCode === 404) {
                return [
                    'success' => false,
                    'error' => 'SecCompanyNotFound',
                    'message' => "Ticker '{$tickerClean}' nao encontrado no indice oficial da SEC EDGAR.",
                    'code' => 404,
                ];
            }
            return [
                'success' => false,
                'error' => 'UpstreamUnavailable',
                'message' => 'Nao foi possivel consultar o indice de tickers da SEC EDGAR no momento. Nenhum debito foi mantido.',
                'code' => 503,
            ];
        }
        $cikNorm = (string) $resolved['cik'];
    }

    $subUrl = 'https://data.sec.gov/submissions/CIK' . $cikNorm . '.json';
    $subResp = grid402_sec_company_http_get($subUrl, 5, 16777216);
    if (empty($subResp['ok']) || !is_array($subResp['data'])) {
        if ((int) ($subResp['status'] ?? 0) === 404) {
            return [
                'success' => false,
                'error' => 'SecCompanyNotFound',
                'message' => "CIK '{$cikNorm}' nao encontrado na SEC EDGAR.",
                'code' => 404,
            ];
        }
        if (!$useOverride && file_exists($cacheFile)) {
            $staleRaw = @file_get_contents($cacheFile);
            $stale = $staleRaw ? @json_decode($staleRaw, true) : null;
            if (is_array($stale)) {
                $stale['cached'] = true;
                $stale['stale_fallback'] = true;
                return $stale;
            }
        }
        return [
            'success' => false,
            'error' => 'UpstreamUnavailable',
            'message' => 'SEC EDGAR submissions indisponivel no momento (' . (string) ($subResp['error'] ?? 'error') . '). Nenhum debito foi mantido.',
            'code' => 503,
        ];
    }

    $sub = $subResp['data'];
    $recent = is_array($sub['filings']['recent'] ?? null) ? $sub['filings']['recent'] : [];
    $formsArr = is_array($recent['form'] ?? null) ? $recent['form'] : [];
    $filingDates = is_array($recent['filingDate'] ?? null) ? $recent['filingDate'] : [];
    $reportDates = is_array($recent['reportDate'] ?? null) ? $recent['reportDate'] : [];
    $accNums = is_array($recent['accessionNumber'] ?? null) ? $recent['accessionNumber'] : [];
    $primaryDocs = is_array($recent['primaryDocument'] ?? null) ? $recent['primaryDocument'] : [];
    $primaryDescs = is_array($recent['primaryDocDescription'] ?? null) ? $recent['primaryDocDescription'] : [];

    $intCik = ltrim($cikNorm, '0');
    if ($intCik === '') {
        $intCik = '0';
    }

    $filings = [];
    $totalRows = count($formsArr);
    for ($i = 0; $i < $totalRows; $i++) {
        $formCode = strtoupper(trim((string) ($formsArr[$i] ?? '')));
        if ($formCode === '') {
            continue;
        }
        if (!empty($formsFilter) && !in_array($formCode, $formsFilter, true)) {
            continue;
        }
        $acc = trim((string) ($accNums[$i] ?? ''));
        $doc = trim((string) ($primaryDocs[$i] ?? ''));
        $accNoDashes = str_replace('-', '', $acc);
        $edgarUrl = ($accNoDashes !== '' && $doc !== '')
            ? ('https://www.sec.gov/Archives/edgar/data/' . $intCik . '/' . $accNoDashes . '/' . $doc)
            : null;

        $filings[] = [
            'form' => $formCode,
            'filing_date' => isset($filingDates[$i]) ? (string) $filingDates[$i] : null,
            'report_date' => isset($reportDates[$i]) && $reportDates[$i] !== '' ? (string) $reportDates[$i] : null,
            'accession_number' => $acc !== '' ? $acc : null,
            'primary_document' => $doc !== '' ? $doc : null,
            'description' => isset($primaryDescs[$i]) && $primaryDescs[$i] !== '' ? (string) $primaryDescs[$i] : null,
            'edgar_url' => $edgarUrl,
        ];
        if (count($filings) >= $limitVal) {
            break;
        }
    }

    $factsStatus = 'not_requested';
    $factsData = null;
    if ($wantFacts) {
        $factsUrl = 'https://data.sec.gov/api/xbrl/companyfacts/CIK' . $cikNorm . '.json';
        $factsResp = grid402_sec_company_http_get($factsUrl, 6, 8388608);
        if (!empty($factsResp['size_exceeded']) || (int) ($factsResp['status'] ?? 0) === 413) {
            $factsStatus = 'skipped_size_limit';
        } elseif (!empty($factsResp['ok']) && is_array($factsResp['data'])) {
            $extracted = grid402_sec_company_extract_facts($factsResp['data']);
            $factsStatus = 'ok';
            $factsData = $extracted;
        } else {
            $factsStatus = 'unavailable';
        }
    }

    $tickersArr = is_array($sub['tickers'] ?? null) ? array_values(array_map('strval', $sub['tickers'])) : [];
    $exchangesArr = is_array($sub['exchanges'] ?? null) ? array_values(array_map('strval', $sub['exchanges'])) : [];
    $resolvedTicker = $tickerClean !== '' ? $tickerClean : ($tickersArr[0] ?? null);

    $payload = [
        'protocol' => 'x402',
        'service' => 'sec-company',
        'status' => 'UP',
        'cik' => $cikNorm,
        'ticker' => $resolvedTicker,
        'tickers' => $tickersArr,
        'company_name' => isset($sub['name']) ? (string) $sub['name'] : null,
        'sic' => isset($sub['sic']) ? (string) $sub['sic'] : null,
        'sic_description' => isset($sub['sicDescription']) ? (string) $sub['sicDescription'] : null,
        'exchanges' => $exchangesArr,
        'entity_type' => isset($sub['entityType']) ? (string) $sub['entityType'] : null,
        'state_of_incorporation' => isset($sub['stateOfIncorporation']) ? (string) $sub['stateOfIncorporation'] : null,
        'fiscal_year_end' => isset($sub['fiscalYearEnd']) ? (string) $sub['fiscalYearEnd'] : null,
        'filings_count' => count($filings),
        'forms_filter' => $formsFilter,
        'filings' => $filings,
        'facts_status' => $factsStatus,
        'facts' => $factsData,
        'legal_disclaimer' => 'Public-domain US SEC EDGAR filings and XBRL disclosures normalized by Grid402. Informational regulatory data only; not investment, legal, or financial advice.',
        'cached' => false,
        'cached_at' => time(),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if (!$useOverride && function_exists('grid402_atomic_write')) {
        grid402_atomic_write($cacheFile, $payload);
    }

    return $payload;
}
