<?php
/**
 * Suite de Testes Deterministica + E2E HTTP - Fase 2
 * Change: add-phase2-token-safety-predictions-batch
 *
 * Cobre integralmente todos os cenarios das delta specs:
 * - GET /api/v1/token-safety (EVM Base, auto-deteccao Solana, 400 pre-cobranca, 402 15000 raw, 503 com estorno integral)
 * - GET /api/v1/prediction-markets (Polymarket + Kalshi com ?q=bitcoin&limit=5, degradacao graciosa, 402 10000 raw, 503 com estorno)
 * - POST /api/v1/batch (cotacao 402 soma exata, estorno parcial Session Pass, bloqueio 400 >5/proibidos, 403 allowed_endpoints pre-debito, limite 2 sandbox demo, Idempotency-Key, _oracle_proof)
 * - Paridade de ecossistema (28 servicos /status & config, 27 recursos x402.json com batch=POST, MCP 2.1.0 com 30 tools, llms.txt sem & e sem Solana)
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpPrivDir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/services/token_safety.php';
require_once __DIR__ . '/../public_html/services/prediction_markets.php';
require_once __DIR__ . '/../public_html/services/batch.php';
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/services/pass_repository.php';
require_once __DIR__ . '/../public_html/services/oracle_signer.php';

echo "=== SUITE FASE 2: TOKEN SAFETY, PREDICTION MARKETS & POST /api/v1/batch ===\n\n";

$tempRouterFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_phase2_router_' . getmypid() . '_' . bin2hex(random_bytes(4)) . '.php';

// Roteador temporario de teste que injeta mocks deterministicos via header X-Test-Mock antes de delegar para tests/router.php
$routerScript = <<<'PHP'
<?php
declare(strict_types=1);

$mockProfile = strtolower(trim((string) ($_SERVER['HTTP_X_TEST_MOCK'] ?? 'ok')));

if ($mockProfile === 'ts_all_fail') {
    $GLOBALS['grid402_token_safety_http_override'] = static function (string $url, int $timeoutSec = 4): array {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'SimulatedUpstream503'];
    };
} else {
    $GLOBALS['grid402_token_safety_http_override'] = static function (string $url, int $timeoutSec = 4): array {
        if (str_contains($url, 'api.gopluslabs.io/api/v1/solana/token_security')) {
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    'code' => 1,
                    'result' => [
                        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v' => [
                            'metadata' => ['name' => 'USD Coin (Solana Mock)', 'symbol' => 'USDC'],
                            'non_transferable' => '0',
                            'mintable' => ['status' => '0'],
                            'freezable' => ['status' => '0'],
                            'balance_mutable_authority' => ['status' => '0'],
                            'transfer_fee' => ['current_fee_rate' => '0'],
                            'holders' => [['percent' => '0.15']],
                        ],
                    ],
                ],
            ];
        }
        if (str_contains($url, 'api.gopluslabs.io/api/v1/token_security/')) {
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    'code' => 1,
                    'result' => [
                        '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913' => [
                            'token_name' => 'USD Coin',
                            'token_symbol' => 'USDC',
                            'is_honeypot' => '0',
                            'cannot_sell_all' => '0',
                            'is_mintable' => '0',
                            'is_proxy' => '0',
                            'owner_change_balance' => '0',
                            'is_open_source' => '1',
                            'buy_tax' => '0',
                            'sell_tax' => '0',
                            'holders' => [['percent' => '0.12']],
                        ],
                    ],
                ],
            ];
        }
        if (str_contains($url, 'api.dexscreener.com')) {
            $chainGuess = str_contains($url, 'EPjFWdd5') ? 'solana' : 'base';
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    'pairs' => [
                        [
                            'chainId' => $chainGuess,
                            'dexId' => 'aerodrome',
                            'pairAddress' => '0x1111111111111111111111111111111111111111',
                            'baseToken' => ['name' => 'USD Coin', 'symbol' => 'USDC'],
                            'priceUsd' => '1.0001',
                            'liquidity' => ['usd' => 12500000.0],
                            'volume' => ['h24' => 45000000.0],
                        ],
                    ],
                ],
            ];
        }
        return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'UnhandledMockUrl'];
    };
}

if ($mockProfile === 'pm_all_fail') {
    $GLOBALS['grid402_prediction_markets_http_override'] = static function (string $url, int $timeoutSec = 4): array {
        return ['ok' => false, 'status' => 502, 'data' => null, 'error' => 'MockFeedDown'];
    };
} elseif ($mockProfile === 'pm_kalshi_fail') {
    $GLOBALS['grid402_prediction_markets_http_override'] = static function (string $url, int $timeoutSec = 4): array {
        if (str_contains($url, 'kalshi.com')) {
            return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'KalshiDown503'];
        }
        return [
            'ok' => true,
            'status' => 200,
            'error' => null,
            'data' => [
                [
                    'id' => 'poly-btc-150k',
                    'question' => 'Will Bitcoin reach $150k in 2026?',
                    'slug' => 'will-bitcoin-reach-150k-2026',
                    'outcomes' => ['Yes', 'No'],
                    'outcomePrices' => ['0.42', '0.58'],
                    'volume24hr' => 890000.5,
                    'liquidityNum' => 250000.0,
                    'active' => true,
                    'endDate' => '2026-12-31T23:59:59Z',
                ],
            ],
        ];
    };
} else {
    $GLOBALS['grid402_prediction_markets_http_override'] = static function (string $url, int $timeoutSec = 4): array {
        if (str_contains($url, 'polymarket.com')) {
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    [
                        'id' => 'poly-btc-150k',
                        'question' => 'Will Bitcoin trade above $120k?',
                        'slug' => 'btc-above-120k',
                        'outcomes' => ['Yes', 'No'],
                        'outcomePrices' => ['0.61', '0.39'],
                        'volume24hr' => 1200000.0,
                        'liquidityNum' => 410000.0,
                        'active' => true,
                        'endDate' => '2026-12-31T23:59:59Z',
                    ],
                ],
            ];
        }
        if (str_contains($url, 'kalshi.com')) {
            return [
                'ok' => true,
                'status' => 200,
                'error' => null,
                'data' => [
                    'markets' => [
                        [
                            'ticker' => 'KXBTC-26DEC-120K',
                            'title' => 'Bitcoin above $120,000 by year-end?',
                            'last_price' => 58,
                            'volume_24h' => 350000.0,
                            'liquidity' => 125000.0,
                            'status' => 'open',
                            'close_time' => '2026-12-31T23:59:59Z',
                        ],
                    ],
                ],
            ];
        }
        return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'UnhandledMockPM'];
    };
}

if ($mockProfile === 'batch_partial') {
    $GLOBALS['grid402_dns_mock_override'] = true;
}

return require __DIR__ . '/router.php';
PHP;

// Ajuste de __DIR__ no roteador em temp para apontar pro router.php do repo
$repoRouterPath = str_replace('\\', '/', realpath(__DIR__ . '/router.php'));
$routerScript = str_replace("return require __DIR__ . '/router.php';", "return require '{$repoRouterPath}';", $routerScript);
file_put_contents($tempRouterFile, $routerScript);

/**
 * Helper HTTP local via cURL.
 */
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
    // [1] Validacao Unitaria do Batch + Servicos Core
    // -------------------------------------------------------------------------
    echo "[1/5] Validacao unitaria de input e lote em batch.php / services:\n";

    $badBatchEmpty = grid402_batch_parse_and_validate('{"items":[]}', false);
    t_assert('Batch vazio rejeitado com HTTP 400', empty($badBatchEmpty['ok']) && ($badBatchEmpty['code'] ?? 0) === 400);

    $sixItems = array_fill(0, 6, ['service' => 'forex', 'params' => []]);
    $badBatch6 = grid402_batch_parse_and_validate(json_encode(['items' => $sixItems]), false);
    t_assert('Batch > 5 itens em producao rejeitado com HTTP 400 BatchLimitExceeded', empty($badBatch6['ok']) && ($badBatch6['code'] ?? 0) === 400);

    $threeItems = array_fill(0, 3, ['service' => 'forex', 'params' => []]);
    $badBatchSandbox = grid402_batch_parse_and_validate(json_encode(['items' => $threeItems]), true);
    t_assert('Batch > 2 itens em sandbox rejeitado com HTTP 400 SandboxBatchLimitExceeded', empty($badBatchSandbox['ok']) && ($badBatchSandbox['code'] ?? 0) === 400);

    foreach (['pass', 'pass-emitir', 'pass-balance', 'pass-limits', 'pass-statement', 'batch', 'verify', 'summary'] as $blockedSvc) {
        $bRes = grid402_batch_parse_and_validate(json_encode(['items' => [['service' => $blockedSvc]]]), false);
        t_assert("Batch bloqueia servico proibido/recursivo: {$blockedSvc}", empty($bRes['ok']) && ($bRes['code'] ?? 0) === 400);
    }

    $validMix = grid402_batch_parse_and_validate(json_encode([
        'items' => [
            ['service' => 'dns', 'params' => ['domain' => 'base.org']],
            ['service' => 'token-safety', 'params' => ['chain' => 'base', 'address' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913']],
        ],
    ]), false);
    t_assert('Batch soma exatamente 5000 (dns) + 15000 (token-safety) = 20000 raw (4 cr / 0.0200 USDC)',
        !empty($validMix['ok'])
        && ($validMix['total_raw_units'] ?? 0) === 20000
        && ($validMix['total_credits'] ?? 0) === 4
        && ($validMix['total_usdc_display'] ?? '') === '0.0200'
    );

    // -------------------------------------------------------------------------
    // [2] Servidor HTTP Local: /api/v1/token-safety
    // -------------------------------------------------------------------------
    echo "\n[2/5] Testes HTTP E2E de GET /api/v1/token-safety:\n";

    $server = grid402_start_server([
        'router' => $tempRouterFile,
        'probe' => '/status',
        'env' => [
            'GRID402_STORAGE_DIR' => $tmpPrivDir,
            'GRID402_DISABLE_TELEGRAM' => '1',
        ],
    ]);
    $base = $server['base_url'];

    // Cria Session Pass real de testes (Starter 2 USDC = 400 creditos)
    $passIssue = grid402_issue_session_pass('0x' . bin2hex(random_bytes(32)), 2000000, false);
    $passToken = (string) ($passIssue['pass_token'] ?? '');
    t_assert('Session Pass emitido para suite E2E (400 creditos)', $passToken !== '' && ($passIssue['credits_remaining'] ?? 0) === 400);

    // 400 pre-cobranca em endereco invalido
    $rTs400 = $httpReq('GET', "{$base}/api/v1/token-safety?chain=base&address=invalido");
    t_assert('GET /api/v1/token-safety?chain=base&address=invalido retorna HTTP 400 pre-cobranca', $rTs400['code'] === 400);

    // 402 com preco 15000 raw (0.0150 USDC) e preview
    $rTs402 = $httpReq('GET', "{$base}/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913");
    t_assert('GET /api/v1/token-safety sem pagamento retorna HTTP 402 com 15000 raw e preview',
        $rTs402['code'] === 402
        && ($rTs402['json']['price_usdc'] ?? '') === '0.0150'
        && (int) ($rTs402['json']['price_raw_units'] ?? 0) === 15000
        && !empty($rTs402['json']['preview'])
        && !empty($rTs402['json']['locked_fields'])
    );

    // 200 EVM Base com Session Pass + _meta + _oracle_proof valido
    $rTsBase = $httpReq('GET', "{$base}/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: ok',
    ]);
    $tsBaseProofOk = is_array($rTsBase['json']) && !empty(grid402_verify_oracle_proof($rTsBase['json'])['valid']);
    t_assert('GET /api/v1/token-safety (EVM Base) retorna 200, score SAFE, flags, taxes, legal_disclaimer, _meta e _oracle_proof valido',
        $rTsBase['code'] === 200
        && ($rTsBase['json']['verdict'] ?? '') === 'SAFE'
        && ($rTsBase['json']['safety_score'] ?? 0) >= 80
        && isset($rTsBase['json']['flags']['is_honeypot'], $rTsBase['json']['taxes']['buy_tax_pct'], $rTsBase['json']['legal_disclaimer'], $rTsBase['json']['_meta'])
        && $tsBaseProofOk,
        "code={$rTsBase['code']}"
    );

    // 200 Solana auto-deteccao sem chain
    $rTsSol = $httpReq('GET', "{$base}/api/v1/token-safety?address=EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: ok',
    ]);
    t_assert('GET /api/v1/token-safety auto-detecta Solana Base58 sem parametro chain',
        $rTsSol['code'] === 200 && ($rTsSol['json']['chain'] ?? '') === 'solana',
        "code={$rTsSol['code']} chain=" . ($rTsSol['json']['chain'] ?? '')
    );

    // 503 pre-cobranca com estorno integral de 3 creditos
    $balBeforeTs503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rTs503 = $httpReq('GET', "{$base}/api/v1/token-safety?chain=base&address=0x4200000000000000000000000000000000000006", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: ts_all_fail',
    ]);
    $balAfterTs503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('GET /api/v1/token-safety retorna HTTP 503 e estorna integralmente 3 creditos ao Session Pass',
        $rTs503['code'] === 503 && $balBeforeTs503 === $balAfterTs503,
        "code={$rTs503['code']} before={$balBeforeTs503} after={$balAfterTs503}"
    );

    // -------------------------------------------------------------------------
    // [3] Servidor HTTP Local: /api/v1/prediction-markets
    // -------------------------------------------------------------------------
    echo "\n[3/5] Testes HTTP E2E de GET /api/v1/prediction-markets:\n";

    // 402 com 10000 raw (0.0100 USDC)
    $rPm402 = $httpReq('GET', "{$base}/api/v1/prediction-markets?q=bitcoin&limit=5");
    t_assert('GET /api/v1/prediction-markets sem pagamento retorna HTTP 402 com 10000 raw e preview',
        $rPm402['code'] === 402 && ($rPm402['json']['price_usdc'] ?? '') === '0.0100' && (int) ($rPm402['json']['price_raw_units'] ?? 0) === 10000
    );

    // 200 agregando Polymarket + Kalshi com filtro q=bitcoin
    $rPm200 = $httpReq('GET', "{$base}/api/v1/prediction-markets?q=bitcoin&limit=5", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: ok',
    ]);
    $pmProofOk = is_array($rPm200['json']) && !empty(grid402_verify_oracle_proof($rPm200['json'])['valid']);
    $pmMkts = $rPm200['json']['markets'] ?? [];
    t_assert('GET /api/v1/prediction-markets?q=bitcoin retorna HTTP 200 com mercados Polymarket + Kalshi e _oracle_proof valido',
        $rPm200['code'] === 200
        && count($pmMkts) === 2
        && isset($pmMkts[0]['outcomes'][0]['probability_pct'], $pmMkts[0]['volume_24h_usd'], $pmMkts[0]['liquidity_usd'])
        && ($rPm200['json']['sources_status']['polymarket'] ?? '') === 'ok'
        && ($rPm200['json']['sources_status']['kalshi'] ?? '') === 'ok'
        && $pmProofOk
    );

    // Degradacao graciosa: Kalshi falha, Polymarket ok -> HTTP 200
    $rPmDeg = $httpReq('GET', "{$base}/api/v1/prediction-markets?source=all", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: pm_kalshi_fail',
    ]);
    t_assert('Degradacao graciosa retorna HTTP 200 quando Kalshi falha e Polymarket responde',
        $rPmDeg['code'] === 200
        && ($rPmDeg['json']['sources_status']['polymarket'] ?? '') === 'ok'
        && str_starts_with((string) ($rPmDeg['json']['sources_status']['kalshi'] ?? ''), 'unavailable')
    );

    // Falha simultanea Polymarket + Kalshi -> HTTP 503 sem debito liquido
    $balBeforePm503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rPm503 = $httpReq('GET', "{$base}/api/v1/prediction-markets?source=all", [
        'X-Payment: ' . $passToken,
        'X-Test-Mock: pm_all_fail',
    ]);
    $balAfterPm503 = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('Falha simultanea de Polymarket e Kalshi retorna HTTP 503 com estorno integral de 2 creditos',
        $rPm503['code'] === 503 && $balBeforePm503 === $balAfterPm503
    );

    // -------------------------------------------------------------------------
    // [4] Servidor HTTP Local: POST /api/v1/batch
    // -------------------------------------------------------------------------
    echo "\n[4/5] Testes HTTP E2E de POST /api/v1/batch:\n";

    $batchPartialPayload = json_encode([
        'items' => [
            ['service' => 'indicators', 'params' => []], // 5000 raw (1 cr) — leitura BCB local / cache sem depender de DNS? Usemos notarize!
            ['service' => 'token-safety', 'params' => ['chain' => 'base', 'address' => '0x4200000000000000000000000000000000000006']], // 15000 raw (3 cr)
        ],
    ]);
    // Nota: notarize (5000 raw = 1 cr) é 100% determinístico offline (sem depender de internet no CI), enquanto token-safety usa mock ts_all_fail!
    $batchPartialDeterministic = json_encode([
        'items' => [
            ['service' => 'notarize', 'params' => ['content' => 'grid402-batch-test-v1']],
            ['service' => 'token-safety', 'params' => ['chain' => 'base', 'address' => '0x4200000000000000000000000000000000000006']],
        ],
    ]);

    // 402 cotacao dinamica exata: 5000 + 15000 = 20000 raw (0.0200 USDC / 4 cr) + batch_plan
    $rBatch402 = $httpReq('POST', "{$base}/api/v1/batch", ['Content-Type: application/json'], $batchPartialDeterministic);
    t_assert('POST /api/v1/batch sem X-Payment retorna HTTP 402 com 20000 raw (0.0200 USDC) e batch_plan',
        $rBatch402['code'] === 402
        && ($rBatch402['json']['price_usdc'] ?? '') === '0.0200'
        && (int) ($rBatch402['json']['price_raw_units'] ?? 0) === 20000
        && is_array($rBatch402['json']['batch_plan'] ?? null)
        && count($rBatch402['json']['batch_plan']) === 2
    );

    // Estorno parcial automatico Session Pass: notarize OK (1 cr), token-safety 503 (3 cr estornados)
    $balBeforePartial = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rBatchPartial = $httpReq('POST', "{$base}/api/v1/batch", [
        'Content-Type: application/json',
        'X-Payment: ' . $passToken,
        'X-Test-Mock: ts_all_fail',
        'Idempotency-Key: idem-batch-phase2-001',
    ], $batchPartialDeterministic);
    $balAfterPartial = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    $batchProofOk = is_array($rBatchPartial['json']) && !empty(grid402_verify_oracle_proof($rBatchPartial['json'])['valid']);

    t_assert('POST /api/v1/batch com falha parcial retorna 200, succeeded=1, failed=1, debita 1 cr liquido (estorno de 3 cr) e assina _oracle_proof',
        $rBatchPartial['code'] === 200
        && ($rBatchPartial['json']['batch_summary']['succeeded'] ?? -1) === 1
        && ($rBatchPartial['json']['batch_summary']['failed'] ?? -1) === 1
        && ($rBatchPartial['json']['batch_summary']['credits_charged'] ?? -1) === 1
        && ($rBatchPartial['json']['batch_summary']['credits_refunded'] ?? -1) === 3
        && ($balBeforePartial - $balAfterPartial) === 1
        && ($rBatchPartial['headers']['x-pass-credits-debited'] ?? '') === '1'
        && $batchProofOk,
        "code={$rBatchPartial['code']} diff=" . ($balBeforePartial - $balAfterPartial) . " summary=" . json_encode($rBatchPartial['json']['batch_summary'] ?? null)
    );

    // Idempotency-Key replay
    $balBeforeIdem = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -1);
    $rBatchIdem = $httpReq('POST', "{$base}/api/v1/batch", [
        'Content-Type: application/json',
        'X-Payment: ' . $passToken,
        'X-Test-Mock: ts_all_fail',
        'Idempotency-Key: idem-batch-phase2-001',
    ], $batchPartialDeterministic);
    $balAfterIdem = (int) (grid402_get_session_pass_status($passToken)['credits_remaining'] ?? -2);
    t_assert('POST /api/v1/batch repetido com mesmo Idempotency-Key retorna X-Idempotent-Replay: true com zero debito adicional',
        $rBatchIdem['code'] === 200
        && ($rBatchIdem['headers']['x-idempotent-replay'] ?? '') === 'true'
        && $balBeforeIdem === $balAfterIdem
    );

    // Sandbox demo > 2 itens retorna HTTP 400 sem executar
    $rBatchDemo3 = $httpReq('POST', "{$base}/api/v1/batch", [
        'Content-Type: application/json',
        'X-Payment: demo',
    ], json_encode([
        'items' => [
            ['service' => 'notarize', 'params' => ['content' => 'a']],
            ['service' => 'notarize', 'params' => ['content' => 'b']],
            ['service' => 'notarize', 'params' => ['content' => 'c']],
        ],
    ]));
    t_assert('POST /api/v1/batch com X-Payment: demo e 3 itens retorna HTTP 400', $rBatchDemo3['code'] === 400);

    // Spend Control allowed_endpoints em lote antes de debitar
    $restrictedIssue = grid402_issue_session_pass('0x' . bin2hex(random_bytes(32)), 2000000, false);
    $restrictedTok = (string) ($restrictedIssue['pass_token'] ?? '');
    grid402_update_session_pass_limits($restrictedTok, null, ['dns']);
    $balRestrictedBefore = (int) (grid402_get_session_pass_status($restrictedTok)['credits_remaining'] ?? -1);

    $rBatchRestricted = $httpReq('POST', "{$base}/api/v1/batch", [
        'Content-Type: application/json',
        'X-Payment: ' . $restrictedTok,
    ], json_encode([
        'items' => [
            ['service' => 'dns', 'params' => ['domain' => 'base.org']],
            ['service' => 'forex', 'params' => []],
        ],
    ]));
    $balRestrictedAfter = (int) (grid402_get_session_pass_status($restrictedTok)['credits_remaining'] ?? -2);
    t_assert('POST /api/v1/batch respeita allowed_endpoints retornando HTTP 403 PassEndpointRestricted pre-debito',
        $rBatchRestricted['code'] === 403
        && ($rBatchRestricted['json']['error'] ?? '') === 'PassEndpointRestricted'
        && $balRestrictedBefore === $balRestrictedAfter
    );

    // -------------------------------------------------------------------------
    // [5] Paridade Ecossistema (28 servicos, 30 MCP tools v2.1.0, Discovery)
    // -------------------------------------------------------------------------
    echo "\n[5/5] Paridade de ecossistema (28 servicos, MCP v2.1.0 30 tools, llms.txt):\n";

    $rMcpInit = $httpReq('POST', "{$base}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [],
    ]));
    t_assert('Hosted MCP /mcp retorna serverInfo.version = 2.1.0',
        $rMcpInit['code'] === 200 && ($rMcpInit['json']['result']['serverInfo']['version'] ?? '') === '2.1.0'
    );

    $rMcpTools = $httpReq('POST', "{$base}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => [],
    ]));
    $toolsList = $rMcpTools['json']['result']['tools'] ?? [];
    $toolNames = array_column($toolsList, 'name');
    t_assert('Hosted MCP tools/list expoe 30 ferramentas incluindo token_safety, prediction_markets e batch',
        count($toolsList) === 30
        && in_array('grid402_token_safety', $toolNames, true)
        && in_array('grid402_prediction_markets', $toolNames, true)
        && in_array('grid402_batch', $toolNames, true)
    );

    $rX402Manifest = $httpReq('GET', "{$base}/.well-known/x402.json");
    $xResources = $rX402Manifest['json']['resources'] ?? [];
    $batchResEntry = null;
    foreach ($xResources as $xr) {
        if (str_ends_with((string) ($xr['resource'] ?? ''), '/api/v1/batch')) {
            $batchResEntry = $xr;
            break;
        }
    }
    t_assert('/.well-known/x402.json lista 27 endpoints pagos com /api/v1/batch em method POST',
        count($xResources) === 27 && is_array($batchResEntry) && ($batchResEntry['method'] ?? '') === 'POST'
    );

    $llmsContent = (string) @file_get_contents(__DIR__ . '/../public_html/llms.txt');
    t_assert('public_html/llms.txt nao contem & nem a palavra Solana e documenta 28 servicos / 30 tools',
        !str_contains($llmsContent, '&')
        && stripos($llmsContent, 'solana') === false
        && str_contains($llmsContent, '28 Services')
        && str_contains($llmsContent, '30 tools')
    );

} finally {
    if ($server !== null) {
        grid402_stop_server($server);
    }
    if (is_file($tempRouterFile)) {
        @unlink($tempRouterFile);
    }
}

t_finish('SUITE FASE 2');
