<?php
/**
 * Suite Offline + HTTP E2E da Fase 3A (Wave 3A):
 *  - GET /api/v1/sec-company (0.0100 USDC / 2 creditos)
 *  - GET /api/v1/vuln-audit (0.0050 USDC / 1 credito)
 *  - GET /api/v1/chain-telemetry (0.0050 USDC / 1 credito)
 *  - POST /api/v1/batch combinando endpoints Fase 3A
 *  - Paridade MCP v2.2.0 (33 tools), Bazaar x402 (30 pagos), catalogo (31)
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpPrivDir = t_bootstrap('settle');

require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/services/pass_repository.php';
require_once __DIR__ . '/../public_html/services/oracle_signer.php';
require_once __DIR__ . '/../public_html/services/sec_company.php';
require_once __DIR__ . '/../public_html/services/vuln_audit.php';
require_once __DIR__ . '/../public_html/services/chain_telemetry.php';
require_once __DIR__ . '/../public_html/services/batch.php';

echo "=== SUITE FASE 3A: SEC-COMPANY, VULN-AUDIT, CHAIN-TELEMETRY & ECOSSISTEMA (31/33) ===\n\n";

// Pre-popula cisa_kev_cache.json no storage temporario para testar fallback stale_cache sem rede
$kevCacheFile = $tmpPrivDir . '/cisa_kev_cache.json';
grid402_atomic_write($kevCacheFile, [
    'cached_at' => time() - 30000, // Expirado (> 21600s) para forcar fetch e fallback stale_cache se falhar
    'count' => 1,
    'map' => [
        'CVE-2021-44228' => [
            'date_added' => '2021-12-10',
            'due_date' => '2021-12-24',
            'known_ransomware' => 'Known',
            'vendor_project' => 'Apache',
            'product' => 'Log4j2',
        ],
    ],
]);

// Roteador temporario para injetar mocks determinísticos nos upstreams externos via header X-Test-Mock
$tempRouterFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_phase3a_router_' . getmypid() . '.php';
$repoRouterPath = str_replace('\\', '/', realpath(__DIR__ . '/router.php'));
$routerScript = <<<'PHP'
<?php
$mockProfile = (string) ($_SERVER['HTTP_X_TEST_MOCK'] ?? '');

$sampleSecTickers = [
    '0' => ['cik_str' => 320193, 'ticker' => 'AAPL', 'title' => 'Apple Inc.'],
    '1' => ['cik_str' => 789019, 'ticker' => 'MSFT', 'title' => 'MICROSOFT CORP'],
];

$sampleSecSubmissions = [
    'cik' => '0000320193',
    'entityType' => 'operating',
    'sic' => '3571',
    'sicDescription' => 'Electronic Computers',
    'name' => 'Apple Inc.',
    'tickers' => ['AAPL'],
    'exchanges' => ['Nasdaq'],
    'stateOfIncorporation' => 'CA',
    'fiscalYearEnd' => '0930',
    'filings' => [
        'recent' => [
            'form' => ['10-K', '8-K', '10-Q', '4'],
            'filingDate' => ['2026-10-30', '2026-10-15', '2026-08-01', '2026-07-15'],
            'reportDate' => ['2026-09-28', '2026-10-15', '2026-06-29', '2026-07-15'],
            'accessionNumber' => ['0000320193-26-000106', '0000320193-26-000099', '0000320193-26-000077', '0000320193-26-000050'],
            'primaryDocument' => ['aapl-20260928.htm', 'form8k.htm', 'aapl-20260629.htm', 'form4.xml'],
            'primaryDocDescription' => ['10-K', '8-K', '10-Q', '4'],
        ],
    ],
];

$sampleSecFacts = [
    'facts' => [
        'us-gaap' => [
            'RevenueFromContractWithCustomerExcludingAssessedTax' => [
                'units' => [
                    'USD' => [
                        ['end' => '2026-09-28', 'filed' => '2026-10-30', 'val' => 391035000000, 'form' => '10-K', 'fy' => 2026, 'fp' => 'FY'],
                    ],
                ],
            ],
            'NetIncomeLoss' => [
                'units' => [
                    'USD' => [
                        ['end' => '2026-09-28', 'filed' => '2026-10-30', 'val' => 93736000000, 'form' => '10-K', 'fy' => 2026, 'fp' => 'FY'],
                    ],
                ],
            ],
            'Assets' => [
                'units' => [
                    'USD' => [
                        ['end' => '2026-09-28', 'filed' => '2026-10-30', 'val' => 364980000000, 'form' => '10-K', 'fy' => 2026, 'fp' => 'FY'],
                    ],
                ],
            ],
        ],
    ],
];

if ($mockProfile !== '') {
    $GLOBALS['grid402_sec_company_http_override'] = static function (string $url, int $timeoutSec = 5, int $maxBytes = 8388608) use ($mockProfile, $sampleSecTickers, $sampleSecSubmissions, $sampleSecFacts): array {
        if ($mockProfile === 'sec_503') {
            return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'SecMockDown'];
        }
        if (str_contains($url, 'company_tickers.json')) {
            return ['ok' => true, 'status' => 200, 'data' => $sampleSecTickers, 'error' => null];
        }
        if ($mockProfile === 'sec_404') {
            return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'HTTP 404'];
        }
        if (str_contains($url, '/submissions/CIK')) {
            return ['ok' => true, 'status' => 200, 'data' => $sampleSecSubmissions, 'error' => null];
        }
        if (str_contains($url, '/companyfacts/CIK')) {
            if ($mockProfile === 'sec_facts_oversize') {
                return [
                    'ok' => false,
                    'status' => 413,
                    'data' => null,
                    'error' => 'PayloadSizeLimitExceeded',
                    'size_exceeded' => true,
                ];
            }
            return ['ok' => true, 'status' => 200, 'data' => $sampleSecFacts, 'error' => null];
        }
        return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'UnhandledMockSecUrl'];
    };

    $GLOBALS['grid402_vuln_audit_http_override'] = static function (string $method, string $url, ?array $jsonBody = null, int $timeoutSec = 5) use ($mockProfile): array {
        if (str_contains($url, 'cisa.gov')) {
            if ($mockProfile === 'vuln_kev_fail') {
                return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'CisaMockTimeout'];
            }
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    'vulnerabilities' => [
                        [
                            'cveID' => 'CVE-2021-44228',
                            'dateAdded' => '2021-12-10',
                            'dueDate' => '2021-12-24',
                            'knownRansomwareCampaignUse' => 'Known',
                            'vendorProject' => 'Apache',
                            'product' => 'Log4j2',
                        ],
                    ],
                ],
            ];
        }
        if (str_contains($url, 'api.osv.dev')) {
            if ($mockProfile === 'vuln_osv_fail') {
                return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'OsvMockDown'];
            }
            $log4jVuln = [
                'id' => 'GHSA-jfh8-c2jp-5v3q',
                'aliases' => ['CVE-2021-44228'],
                'summary' => 'Remote code execution in Apache Log4j2 JNDI lookup',
                'severity' => [['type' => 'CVSS_V3', 'score' => '10.0']],
                'database_specific' => ['severity' => 'CRITICAL'],
                'affected' => [['ranges' => [['events' => [['introduced' => '2.0-beta9'], ['fixed' => '2.15.0']]]]]],
                'published' => '2021-12-10T00:00:00Z',
                'references' => [['url' => 'https://nvd.nist.gov/vuln/detail/CVE-2021-44228']],
            ];
            if (str_ends_with($url, '/v1/vulns/CVE-2021-44228')) {
                return ['ok' => true, 'status' => 200, 'error' => null, 'data' => $log4jVuln];
            }
            if (str_ends_with($url, '/v1/vulns/CVE-9999-99999')) {
                return ['ok' => false, 'status' => 404, 'error' => 'HTTP 404', 'data' => null];
            }
            if (str_ends_with($url, '/v1/query')) {
                $pkgName = strtolower((string) ($jsonBody['package']['name'] ?? ''));
                if ($pkgName === 'log4j-core') {
                    return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['vulns' => [$log4jVuln]]];
                }
                if ($pkgName === 'safe-pkg') {
                    return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['vulns' => []]];
                }
                return [
                    'ok' => true,
                    'status' => 200,
                    'error' => null,
                    'data' => [
                        'vulns' => [
                            [
                                'id' => 'GHSA-35jh-r3h4-6jhm',
                                'aliases' => ['CVE-2021-23337'],
                                'summary' => 'Command injection in lodash template',
                                'severity' => [['type' => 'CVSS_V3', 'score' => '7.2']],
                                'database_specific' => ['severity' => 'HIGH'],
                                'affected' => [['ranges' => [['events' => [['introduced' => '0'], ['fixed' => '4.17.21']]]]]],
                            ],
                        ],
                    ],
                ];
            }
        }
        return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'UnhandledMockVulnUrl'];
    };

    $GLOBALS['grid402_chain_telemetry_http_override'] = static function (string $method, string $url, ?array $jsonBody = null, int $timeoutSec = 4) use ($mockProfile): array {
        if ($mockProfile === 'chain_all_fail') {
            return ['ok' => false, 'status' => 503, 'data' => null, 'latency_ms' => 15, 'error' => 'AllChainUpstreamsDown'];
        }
        if (str_contains($url, 'api.llama.fi')) {
            if ($mockProfile === 'chain_solana_no_llama') {
                return ['ok' => false, 'status' => 503, 'data' => null, 'latency_ms' => 19, 'error' => 'DeFiLlamaDown'];
            }
            return [
                'ok' => true,
                'status' => 200,
                'latency_ms' => 28,
                'error' => null,
                'data' => [
                    ['name' => 'Base', 'tvl' => 3450000000.12],
                    ['name' => 'Ethereum', 'tvl' => 55000000000.0],
                    ['name' => 'Polygon', 'tvl' => 980000000.5],
                    ['name' => 'Arbitrum', 'tvl' => 2900000000.75],
                    ['name' => 'Solana', 'tvl' => 8900000000.55],
                ],
            ];
        }
        if (str_contains($url, 'solana')) {
            return [
                'ok' => true,
                'status' => 200,
                'latency_ms' => 21,
                'error' => null,
                'data' => [
                    ['jsonrpc' => '2.0', 'id' => 1, 'result' => 312000456],
                    [
                        'jsonrpc' => '2.0',
                        'id' => 2,
                        'result' => [
                            ['slot' => 312000455, 'prioritizationFee' => 1500],
                            ['slot' => 312000456, 'prioritizationFee' => 3000],
                            ['slot' => 312000456, 'prioritizationFee' => 4500],
                        ],
                    ],
                ],
            ];
        }
        return [
            'ok' => true,
            'status' => 200,
            'latency_ms' => 17,
            'error' => null,
            'data' => [
                ['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x15f9000'], // 23040000
                ['jsonrpc' => '2.0', 'id' => 2, 'result' => '0x5f5e100'], // 0.1 Gwei
                ['jsonrpc' => '2.0', 'id' => 3, 'result' => '0xf4240'],   // 0.001 Gwei
            ],
        ];
    };
}

return require '__REPO_ROUTER__';
PHP;

$routerScript = str_replace('__REPO_ROUTER__', $repoRouterPath, $routerScript);
file_put_contents($tempRouterFile, $routerScript);

$httpReq = static function (string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$respHeaders): int {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = @json_decode($raw, true);
    return ['code' => $code, 'headers' => $respHeaders, 'body' => $raw, 'json' => is_array($json) ? $json : null];
};

$server = null;
try {
    // -------------------------------------------------------------------------
    // [1] Validacao Unitaria Offline + Cache + Cotacao Batch
    // -------------------------------------------------------------------------
    echo "[1/5] Validacao unitaria de inputs, cache local Zero-DB e cotacao batch:\n";

    $badSec = grid402_sec_company_validate_input('', '');
    t_assert('sec-company rejeita ticker e cik vazios com 400', empty($badSec['ok']) && ($badSec['code'] ?? 0) === 400);

    $badCik = grid402_sec_company_validate_input('', 'abc-not-cik');
    t_assert('sec-company rejeita cik nao numerico com 400', empty($badCik['ok']) && ($badCik['code'] ?? 0) === 400);

    $okSecVal = grid402_sec_company_validate_input('aapl', '', 5, '10-k, 8-k', 'true');
    t_assert('sec-company normaliza ticker, forms e include_facts=true',
        !empty($okSecVal['ok'])
        && ($okSecVal['ticker'] ?? '') === 'AAPL'
        && ($okSecVal['limit'] ?? 0) === 5
        && ($okSecVal['forms'] ?? []) === ['10-K', '8-K']
        && ($okSecVal['include_facts'] ?? false) === true
    );

    // Teste de cache hit real em disco para sec-company (sem override ativo)
    $secCacheKey = hash('sha256', 'AAPL||10||0');
    $secCachePath = $tmpPrivDir . '/cache_sec_company_' . substr($secCacheKey, 0, 24) . '.json';
    grid402_atomic_write($secCachePath, [
        'protocol' => 'x402',
        'service' => 'sec-company',
        'status' => 'UP',
        'cik' => '0000320193',
        'ticker' => 'AAPL',
        'company_name' => 'Apple Inc. (Cached)',
        'filings_count' => 1,
        'filings' => [],
        'facts_status' => 'not_requested',
        'facts' => null,
        'cached' => false,
        'cached_at' => time(),
    ]);
    $secCachedHit = grid402_service_sec_company('AAPL', '', 10, '', false);
    t_assert('sec-company serve cache hit Zero-DB (3600s) sem chamar rede externa',
        ($secCachedHit['status'] ?? '') === 'UP'
        && ($secCachedHit['cached'] ?? false) === true
        && ($secCachedHit['company_name'] ?? '') === 'Apple Inc. (Cached)'
    );

    $badVuln = grid402_vuln_audit_validate_input('unsupported_eco', 'pkg', '', '');
    t_assert('vuln-audit rejeita ecossistema desconhecido com 400', empty($badVuln['ok']) && ($badVuln['code'] ?? 0) === 400);

    $okVulnEco = grid402_vuln_audit_validate_input('pip', 'requests', '2.31.0', '');
    t_assert('vuln-audit normaliza alias pip -> PyPI',
        !empty($okVulnEco['ok']) && ($okVulnEco['ecosystem'] ?? '') === 'PyPI' && ($okVulnEco['mode'] ?? '') === 'package'
    );

    $badChain = grid402_chain_telemetry_validate_input('dogecoin');
    t_assert('chain-telemetry rejeita rede invalida com 400', empty($badChain['ok']) && ($badChain['code'] ?? 0) === 400);

    $okChainSol = grid402_chain_telemetry_validate_input('sol');
    t_assert('chain-telemetry normaliza alias sol -> solana', !empty($okChainSol['ok']) && ($okChainSol['chain'] ?? '') === 'solana');

    $batchWave3a = grid402_batch_parse_and_validate(json_encode([
        'items' => [
            ['service' => 'sec-company', 'params' => ['ticker' => 'AAPL', 'limit' => 2]],
            ['service' => 'chain-telemetry', 'params' => ['chain' => 'base']],
        ],
    ]), false);
    t_assert('Batch soma exatamente 10000 (sec-company) + 5000 (chain-telemetry) = 15000 raw (3 cr / 0.0150 USDC)',
        !empty($batchWave3a['ok'])
        && ($batchWave3a['total_raw_units'] ?? 0) === 15000
        && ($batchWave3a['total_credits'] ?? 0) === 3
        && ($batchWave3a['total_usdc_display'] ?? '') === '0.0150'
    );

    // -------------------------------------------------------------------------
    // [2] Servidor HTTP E2E: /api/v1/sec-company
    // -------------------------------------------------------------------------
    echo "\n[2/5] Testes HTTP E2E de GET /api/v1/sec-company:\n";

    $server = grid402_start_server([
        'router' => $tempRouterFile,
        'probe' => '/status',
        'env' => [
            'GRID402_STORAGE_DIR' => $tmpPrivDir,
            'GRID402_DISABLE_TELEGRAM' => '1',
        ],
    ]);
    $base = $server['base_url'];

    $passIssue = grid402_issue_session_pass('0x' . bin2hex(random_bytes(32)), 2000000, false);
    $passToken = (string) ($passIssue['pass_token'] ?? '');
    t_assert('Session Pass emitido para suite Fase 3A (400 creditos)', $passToken !== '' && ($passIssue['credits_remaining'] ?? 0) === 400);

    $rSec400 = $httpReq('GET', "{$base}/api/v1/sec-company");
    t_assert('GET /api/v1/sec-company sem ticker/cik retorna HTTP 400 pre-cobranca', $rSec400['code'] === 400);

    $rSec402 = $httpReq('GET', "{$base}/api/v1/sec-company?ticker=AAPL");
    t_assert('GET /api/v1/sec-company sem X-Payment retorna HTTP 402 com 10000 raw (0.0100 USDC) e preview',
        $rSec402['code'] === 402
        && ($rSec402['json']['price_usdc'] ?? '') === '0.0100'
        && (int) ($rSec402['json']['price_raw_units'] ?? 0) === 10000
        && !empty($rSec402['json']['preview'])
        && !empty($rSec402['json']['locked_fields'])
    );

    $balBeforeSec200 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rSecFacts = $httpReq('GET', "{$base}/api/v1/sec-company?ticker=AAPL&forms=10-K,10-Q&limit=2&include_facts=true", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: sec_ok',
    ]);
    $balAfterSec200 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    $secProofOk = is_array($rSecFacts['json']) && !empty(grid402_verify_oracle_proof($rSecFacts['json'])['valid']);
    t_assert('GET /api/v1/sec-company?ticker=AAPL&forms=10-K,10-Q&include_facts=true debita 2 cr e retorna filings filtrados, facts_status=ok e _oracle_proof valido',
        $rSecFacts['code'] === 200
        && ($balBeforeSec200 - $balAfterSec200) === 2
        && ($rSecFacts['json']['cik'] ?? '') === '0000320193'
        && ($rSecFacts['json']['filings_count'] ?? 0) === 2
        && ($rSecFacts['json']['filings'][0]['form'] ?? '') === '10-K'
        && ($rSecFacts['json']['filings'][1]['form'] ?? '') === '10-Q'
        && ($rSecFacts['json']['facts_status'] ?? '') === 'ok'
        && ($rSecFacts['json']['facts']['revenue']['value'] ?? 0) === 391035000000
        && isset($rSecFacts['json']['_meta'])
        && $secProofOk,
        "code={$rSecFacts['code']} diff=" . ($balBeforeSec200 - $balAfterSec200)
    );

    $rSecOver = $httpReq('GET', "{$base}/api/v1/sec-company?ticker=AAPL&include_facts=true", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: sec_facts_oversize',
    ]);
    t_assert('GET /api/v1/sec-company com companyfacts > 8 MB degrada com facts_status=skipped_size_limit e HTTP 200',
        $rSecOver['code'] === 200
        && ($rSecOver['json']['facts_status'] ?? '') === 'skipped_size_limit'
        && array_key_exists('facts', $rSecOver['json'] ?? [])
        && $rSecOver['json']['facts'] === null,
        "code={$rSecOver['code']} facts_status=" . ($rSecOver['json']['facts_status'] ?? '')
    );

    $balBeforeSec404 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rSec404 = $httpReq('GET', "{$base}/api/v1/sec-company?ticker=ZZZZZZ", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: sec_404',
    ]);
    $balAfterSec404 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('GET /api/v1/sec-company para ticker inexistente retorna HTTP 404 com estorno integral de 2 creditos',
        $rSec404['code'] === 404 && $balBeforeSec404 === $balAfterSec404,
        "code={$rSec404['code']} before={$balBeforeSec404} after={$balAfterSec404}"
    );

    // -------------------------------------------------------------------------
    // [3] Servidor HTTP E2E: /api/v1/vuln-audit
    // -------------------------------------------------------------------------
    echo "\n[3/5] Testes HTTP E2E de GET /api/v1/vuln-audit:\n";

    $rVuln400 = $httpReq('GET', "{$base}/api/v1/vuln-audit?ecosystem=npm");
    t_assert('GET /api/v1/vuln-audit com query incompleta retorna HTTP 400 pre-cobranca', $rVuln400['code'] === 400);

    $rVuln402 = $httpReq('GET', "{$base}/api/v1/vuln-audit?ecosystem=npm&package=lodash");
    t_assert('GET /api/v1/vuln-audit sem pagamento retorna HTTP 402 com 5000 raw (0.0050 USDC) e preview',
        $rVuln402['code'] === 402
        && ($rVuln402['json']['price_usdc'] ?? '') === '0.0050'
        && (int) ($rVuln402['json']['price_raw_units'] ?? 0) === 5000
        && !empty($rVuln402['json']['preview'])
    );

    $rVulnKev = $httpReq('GET', "{$base}/api/v1/vuln-audit?ecosystem=maven&package=log4j-core&version=2.14.1", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: vuln_ok',
    ]);
    $vulnProofOk = is_array($rVulnKev['json']) && !empty(grid402_verify_oracle_proof($rVulnKev['json'])['valid']);
    t_assert('GET /api/v1/vuln-audit em log4j-core cruza OSV + CISA KEV retornando CRITICAL_KEV e _oracle_proof valido',
        $rVulnKev['code'] === 200
        && ($rVulnKev['json']['verdict'] ?? '') === 'CRITICAL_KEV'
        && ($rVulnKev['json']['actively_exploited_kev'] ?? false) === true
        && (int) ($rVulnKev['json']['kev_match_count'] ?? 0) === 1
        && $vulnProofOk
    );

    $rVulnId = $httpReq('GET', "{$base}/api/v1/vuln-audit?id=CVE-2021-44228", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: vuln_ok',
    ]);
    t_assert('GET /api/v1/vuln-audit por ID direto (CVE-2021-44228) retorna modo id e fixed_in',
        $rVulnId['code'] === 200
        && ($rVulnId['json']['query_mode'] ?? '') === 'id'
        && ($rVulnId['json']['verdict'] ?? '') === 'CRITICAL_KEV'
        && in_array('2.15.0', $rVulnId['json']['vulnerabilities'][0]['fixed_in'] ?? [], true)
    );

    $rVulnClean = $httpReq('GET', "{$base}/api/v1/vuln-audit?ecosystem=npm&package=safe-pkg", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: vuln_ok',
    ]);
    t_assert('GET /api/v1/vuln-audit em pacote limpo retorna verdict=CLEAN e 0 vulnerabilidades',
        $rVulnClean['code'] === 200
        && ($rVulnClean['json']['verdict'] ?? '') === 'CLEAN'
        && (int) ($rVulnClean['json']['vulnerability_count'] ?? -1) === 0
    );

    // Forca TTL expirado em cisa_kev_cache.json antes de simular falha da CISA (pois chamadas anteriores com vuln_ok atualizaram cached_at)
    grid402_atomic_write($kevCacheFile, [
        'cached_at' => time() - 30000,
        'count' => 1,
        'map' => [
            'CVE-2021-44228' => [
                'date_added' => '2021-12-10',
                'due_date' => '2021-12-24',
                'known_ransomware' => 'Known',
                'vendor_project' => 'Apache',
                'product' => 'Log4j2',
            ],
        ],
    ]);

    $rVulnStaleKev = $httpReq('GET', "{$base}/api/v1/vuln-audit?id=CVE-2021-44228", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: vuln_kev_fail',
    ]);
    t_assert('Falha do feed CISA KEV faz fallback para cisa_kev_cache.json com kev_source_status=stale_cache',
        $rVulnStaleKev['code'] === 200
        && ($rVulnStaleKev['json']['kev_source_status'] ?? '') === 'stale_cache'
        && ($rVulnStaleKev['json']['verdict'] ?? '') === 'CRITICAL_KEV',
        "code={$rVulnStaleKev['code']} kev_status=" . ($rVulnStaleKev['json']['kev_source_status'] ?? '')
    );

    $balBeforeVuln404 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rVuln404 = $httpReq('GET', "{$base}/api/v1/vuln-audit?id=CVE-9999-99999", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: vuln_ok',
    ]);
    $balAfterVuln404 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('GET /api/v1/vuln-audit para CVE inexistente retorna HTTP 404 com estorno integral de 1 credito',
        $rVuln404['code'] === 404 && $balBeforeVuln404 === $balAfterVuln404
    );

    $balBeforeVuln503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rVuln503 = $httpReq('GET', "{$base}/api/v1/vuln-audit?ecosystem=npm&package=lodash", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: vuln_osv_fail',
    ]);
    $balAfterVuln503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('Falha do OSV.dev retorna HTTP 503 com estorno integral de 1 credito ao Session Pass',
        $rVuln503['code'] === 503 && $balBeforeVuln503 === $balAfterVuln503
    );

    // -------------------------------------------------------------------------
    // [4] Servidor HTTP E2E: /api/v1/chain-telemetry + /api/v1/batch
    // -------------------------------------------------------------------------
    echo "\n[4/5] Testes HTTP E2E de GET /api/v1/chain-telemetry e POST /api/v1/batch:\n";

    $rChain400 = $httpReq('GET', "{$base}/api/v1/chain-telemetry?chain=invalidnet");
    t_assert('GET /api/v1/chain-telemetry com rede invalida retorna HTTP 400 pre-cobranca', $rChain400['code'] === 400);

    $rChain402 = $httpReq('GET', "{$base}/api/v1/chain-telemetry?chain=base");
    t_assert('GET /api/v1/chain-telemetry sem pagamento retorna HTTP 402 com 5000 raw (0.0050 USDC) e preview',
        $rChain402['code'] === 402
        && ($rChain402['json']['price_usdc'] ?? '') === '0.0050'
        && (int) ($rChain402['json']['price_raw_units'] ?? 0) === 5000
    );

    $rChainBase = $httpReq('GET', "{$base}/api/v1/chain-telemetry?chain=base", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: chain_ok',
    ]);
    $chainProofOk = is_array($rChainBase['json']) && !empty(grid402_verify_oracle_proof($rChainBase['json'])['valid']);
    t_assert('GET /api/v1/chain-telemetry?chain=base retorna block_number, gas_gwei, tvl_usd, partial_data=false e _oracle_proof valido',
        $rChainBase['code'] === 200
        && (int) ($rChainBase['json']['block_number'] ?? 0) === 23040000
        && abs((float) ($rChainBase['json']['gas_gwei'] ?? 0) - 0.1) < 0.0001
        && ($rChainBase['json']['tvl_usd'] ?? 0) > 3000000000
        && ($rChainBase['json']['partial_data'] ?? true) === false
        && $chainProofOk
    );

    $rChainSolPartial = $httpReq('GET', "{$base}/api/v1/chain-telemetry?chain=solana", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: chain_solana_no_llama',
    ]);
    t_assert('GET /api/v1/chain-telemetry?chain=solana com falha na DeFiLlama degrada graciosamente com partial_data=true e HTTP 200',
        $rChainSolPartial['code'] === 200
        && ($rChainSolPartial['json']['chain_type'] ?? '') === 'solana'
        && (int) ($rChainSolPartial['json']['slot'] ?? 0) === 312000456
        && ($rChainSolPartial['json']['partial_data'] ?? false) === true
        && ($rChainSolPartial['json']['sources_status']['rpc'] ?? '') === 'ok'
        && str_starts_with((string) ($rChainSolPartial['json']['sources_status']['defillama'] ?? ''), 'unavailable')
    );

    $balBeforeChain503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rChain503 = $httpReq('GET', "{$base}/api/v1/chain-telemetry?chain=base", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: chain_all_fail',
    ]);
    $balAfterChain503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('Falha simultanea de RPC e DeFiLlama retorna HTTP 503 com estorno integral de 1 credito',
        $rChain503['code'] === 503 && $balBeforeChain503 === $balAfterChain503
    );

    // Batch combinando sec-company + chain-telemetry com sucesso completo e falha parcial
    $batchPayload = json_encode([
        'items' => [
            ['service' => 'sec-company', 'params' => ['ticker' => 'AAPL', 'limit' => 2]],
            ['service' => 'chain-telemetry', 'params' => ['chain' => 'base']],
        ],
    ]);

    $rBatch402 = $httpReq('POST', "{$base}/api/v1/batch", ['Content-Type: application/json'], $batchPayload);
    t_assert('POST /api/v1/batch (sec-company + chain-telemetry) retorna HTTP 402 com 15000 raw (0.0150 USDC)',
        $rBatch402['code'] === 402 && (int) ($rBatch402['json']['price_raw_units'] ?? 0) === 15000
    );

    $balBeforeBatchOk = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rBatchOk = $httpReq('POST', "{$base}/api/v1/batch", [
        'Content-Type: application/json',
        'X-Payment: ' . $passToken,
        'X-Test-Mock: chain_ok',
    ], $batchPayload);
    $balAfterBatchOk = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('POST /api/v1/batch (sec-company + chain-telemetry) retorna 200, succeeded=2 e debita 3 creditos',
        $rBatchOk['code'] === 200
        && ($rBatchOk['json']['batch_summary']['succeeded'] ?? -1) === 2
        && ($rBatchOk['json']['batch_summary']['failed'] ?? -1) === 0
        && ($balBeforeBatchOk - $balAfterBatchOk) === 3
    );

    $balBeforeBatchPart = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rBatchPartial = $httpReq('POST', "{$base}/api/v1/batch", [
        'Content-Type: application/json',
        'X-Payment: ' . $passToken,
        'X-Test-Mock: chain_all_fail',
    ], json_encode([
        'items' => [
            ['service' => 'sec-company', 'params' => ['ticker' => 'AAPL', 'limit' => 1]],
            ['service' => 'chain-telemetry', 'params' => ['chain' => 'base']],
        ],
    ]));
    $balAfterBatchPart = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('POST /api/v1/batch com sec-company OK (2 cr) e chain-telemetry 503 (1 cr estornado) debita 2 creditos liquidos',
        $rBatchPartial['code'] === 200
        && ($rBatchPartial['json']['batch_summary']['succeeded'] ?? -1) === 1
        && ($rBatchPartial['json']['batch_summary']['failed'] ?? -1) === 1
        && ($rBatchPartial['json']['batch_summary']['credits_charged'] ?? -1) === 2
        && ($rBatchPartial['json']['batch_summary']['credits_refunded'] ?? -1) === 1
        && ($balBeforeBatchPart - $balAfterBatchPart) === 2
    );

    // -------------------------------------------------------------------------
    // [5] Paridade Ecossistema (31 servicos, 30 x402, MCP v2.2.0 33 tools)
    // -------------------------------------------------------------------------
    echo "\n[5/5] Paridade de ecossistema (31 servicos, MCP v2.2.0 33 tools, 30 x402 resources):\n";

    $rMcpInit = $httpReq('POST', "{$base}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [],
    ]));
    t_assert('Hosted MCP /mcp retorna serverInfo.version = 2.2.0',
        $rMcpInit['code'] === 200 && ($rMcpInit['json']['result']['serverInfo']['version'] ?? '') === '2.2.0'
    );

    $rMcpTools = $httpReq('POST', "{$base}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => [],
    ]));
    $toolsList = $rMcpTools['json']['result']['tools'] ?? [];
    $toolNames = array_column($toolsList, 'name');
    t_assert('Hosted MCP tools/list expoe 33 ferramentas incluindo sec_company, vuln_audit e chain_telemetry',
        count($toolsList) === 33
        && in_array('grid402_sec_company', $toolNames, true)
        && in_array('grid402_vuln_audit', $toolNames, true)
        && in_array('grid402_chain_telemetry', $toolNames, true)
    );

    $rX402Manifest = $httpReq('GET', "{$base}/.well-known/x402.json");
    $xResources = $rX402Manifest['json']['resources'] ?? [];
    $xResUrls = array_column($xResources, 'resource');
    t_assert('/.well-known/x402.json lista 30 endpoints pagos incluindo sec-company, vuln-audit e chain-telemetry',
        count($xResources) === 30
        && in_array('https://grid402.online/api/v1/sec-company', $xResUrls, true)
        && in_array('https://grid402.online/api/v1/vuln-audit', $xResUrls, true)
        && in_array('https://grid402.online/api/v1/chain-telemetry', $xResUrls, true)
    );

    $llmsContent = (string) @file_get_contents(__DIR__ . '/../public_html/llms.txt');
    t_assert('public_html/llms.txt nao contem & nem a palavra Solana e documenta 31 servicos / 33 tools',
        !str_contains($llmsContent, '&')
        && stripos($llmsContent, 'solana') === false
        && str_contains($llmsContent, '31 Services')
        && str_contains($llmsContent, '33 tools')
    );
} finally {
    if ($server !== null) {
        grid402_stop_server($server);
    }
    if (is_file($tempRouterFile)) {
        @unlink($tempRouterFile);
    }
}

t_finish('SUITE FASE 3A');
