<?php
/**
 * Grid402 // Suíte Consolidada de Regressão e Homologação: Correções de Auditoria de Faturamento
 *
 * Cobre de ponta a ponta em storage temporário isolado (sem sujar public_html/private_data/):
 *  1. V-01: Bloqueio de demo/sandbox e pass-por-pass em /api/v1/pass e /mcp; teto 3 créditos sandbox
 *  2. V-02: CF-Connecting-IP ignorado sem opt-in; sandbox_rates.json sob flock fail-closed;
 *           promo_* com daily_credit_limit travado (403 PassLimitsLocked)
 *  3. V-03: Idempotency-Key segura (bloqueio cross-client, credencial inválida, exclusão /pass e sandbox,
 *           isolamento de body POST /notarize)
 *  4. V-04 + V-10: Rejeição 403 TransactionExpired (>72h e sem timestamp); anti-replay cross-mode v1 normalizado
 *  5. V-06 + Extra #1: mcp.php aceita objeto JSON _meta['x402/payment'], devolve @x402/mcp
 *                      (isError + _meta['x402/error'] + PAYMENT-REQUIRED), estorna Session Pass em erro != 200,
 *                      loga em requests.jsonl + growth.json
 *  6. V-11: Coerção int->string no PayAI (EIP-3009) + suporte completo a permit2Authorization (@x402/evm v2)
 *  7. V-12: /api/v1/pass-emitir rejeita < 5000 raw e retorna HTTP 200 idempotent: true no reenvio sem duplicar receita
 *  8. V-13 + V-14 + V-15: Teasers 402 estáticos/mascarados sem dados live; /status sem usd_brl/btc_usd + 25/25 price_usdc;
 *                         Accept: text/markdown restrito à raiz / (não sequestra /api/v1/*, /status, /mcp, /pass)
 *  9. V-16 + V-17 + V-18 + V-19: MP source_news=webhooks + HMAC sem request-id + merchant_order +
 *                                pass_purchase em growth.php + dedup CSV pago + page_views/bots fora de total_requests +
 *                                logger.php multichain/price_usdc/5xx/rotacionado .1
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$storageDir = t_bootstrap('settle');
$logFile = $storageDir . DIRECTORY_SEPARATOR . 'requests.jsonl';
$sandboxFile = $storageDir . DIRECTORY_SEPARATOR . 'sandbox_rates.json';
$promoFile = $storageDir . DIRECTORY_SEPARATOR . 'promo_claims.json';
putenv('GRID402_FILE_REQUEST_LOGS=' . $logFile);
$_ENV['GRID402_FILE_REQUEST_LOGS'] = $logFile;
putenv('GRID402_FILE_SANDBOX_RATES=' . $sandboxFile);
$_ENV['GRID402_FILE_SANDBOX_RATES'] = $sandboxFile;
putenv('GRID402_FILE_PROMO_CLAIMS=' . $promoFile);
$_ENV['GRID402_FILE_PROMO_CLAIMS'] = $promoFile;
putenv('GRID402_DISABLE_TELEGRAM=1');
$_ENV['GRID402_DISABLE_TELEGRAM'] = '1';

require_once __DIR__ . '/../public_html/logger.php';
require_once __DIR__ . '/../public_html/metrics.php';
require_once __DIR__ . '/../public_html/growth.php';
require_once __DIR__ . '/../public_html/services/multichain.php';
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/services/pass_repository.php';
require_once __DIR__ . '/../public_html/services/pass_checkout.php';
require_once __DIR__ . '/../public_html/services/promo_pass.php';
require_once __DIR__ . '/../public_html/services/mercadopago.php';
require_once __DIR__ . '/../public_html/services/admin_insights.php';
require_once __DIR__ . '/../public_html/services/dossier.php';
require_once __DIR__ . '/../public_html/services/cnpj.php';
require_once __DIR__ . '/../public_html/services/cep.php';
require_once __DIR__ . '/../public_html/services/workdays.php';
require_once __DIR__ . '/../public_html/services/global_markets.php';
require_once __DIR__ . '/../public_html/services/indicators.php';
require_once __DIR__ . '/../public_html/services/holidays.php';
require_once __DIR__ . '/../public_html/services/lei.php';

echo "=================================================================\n";
echo "  GRID402 // SUÍTE CONSOLIDADA: AUDITORIA DE FATURAMENTO (P0/P1/P2)\n";
echo "=================================================================\n\n";

$httpReq = static function (string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$respHeaders): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $rawBody = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'code' => $code,
        'headers' => $respHeaders,
        'body' => $rawBody,
        'json' => @json_decode($rawBody, true),
    ];
};

// Semeia summary_free.json isolado via collector em formato compatível com /status
$summarySeed = [
    'protocol' => 'x402',
    'chain_id' => 8453,
    'status' => 'OPERATIONAL',
    'services' => [],
];
foreach (grid402_get_utility_services() as $svcSlug => $svcDef) {
    $entry = [
        'status' => 'UP',
        'price_usdc' => (string) ($svcDef['price_usdc'] ?? '0.0050'),
        'upstream_source' => 'Unit Test Seed',
        'cache_ttl_sec' => 60,
        'latency_ms' => 12.0,
    ];
    if ($svcSlug === 'forex') {
        $entry['pairs_monitored'] = ['USD-BRL', 'EUR-BRL', 'GBP-BRL'];
    }
    if ($svcSlug === 'crypto') {
        $entry['assets_monitored'] = ['BTC', 'ETH', 'SOL'];
    }
    $summarySeed['services'][$svcSlug] = $entry;
}
grid402_atomic_write($storageDir . DIRECTORY_SEPARATOR . 'summary_free.json', $summarySeed);

// Emite dois Session Passes pagos reais para os testes HTTP (Pass A e Pass B)
$txPassA = '0x' . bin2hex(random_bytes(32));
$txPassB = '0x' . bin2hex(random_bytes(32));
grid402_claim_transaction_lock($txPassA);
grid402_claim_transaction_lock($txPassB);
$passA = grid402_issue_session_pass($txPassA, 2000000, false); // Starter: 400 créditos
$passB = grid402_issue_session_pass($txPassB, 2000000, false);
$tokenA = (string) ($passA['pass_token'] ?? '');
$tokenB = (string) ($passB['pass_token'] ?? '');

$server = grid402_start_server([
    'docroot' => __DIR__ . '/../public_html',
    'router' => __DIR__ . '/../public_html/router.php',
    'probe' => '/status',
    'env' => [
        'GRID402_STORAGE_DIR' => $storageDir,
        'GRID402_FILE_REQUEST_LOGS' => $logFile,
        'GRID402_FILE_SANDBOX_RATES' => $sandboxFile,
        'GRID402_FILE_PROMO_CLAIMS' => $promoFile,
        'GRID402_DISABLE_TELEGRAM' => '1',
        'GRID402_TRUST_CF_HEADER' => 'false',
        'GRID402_MP_WEBHOOK_SECRET' => 'whsec_audit_suite_123',
        'GRID402_MP_SANDBOX' => 'false',
    ],
]);
$baseUrl = $server['base_url'];

try {
    // =========================================================================
    // [1] V-01: Bloqueio de demo e pass-por-pass em /api/v1/pass e /mcp; teto 3 cr sandbox
    // =========================================================================
    echo "[1/9] V-01 — Bloqueio de demo e pass-por-pass + teto 3 créditos sandbox:\n";

    $rPassDemo = $httpReq('GET', "{$baseUrl}/api/v1/pass", ['Accept: application/json', 'X-Payment: demo']);
    t_assert('V-01: GET /api/v1/pass com X-Payment: demo retorna HTTP 403 SandboxNotAllowedForPass',
        $rPassDemo['code'] === 403 && ($rPassDemo['json']['error'] ?? '') === 'SandboxNotAllowedForPass',
        "code={$rPassDemo['code']} body=" . substr($rPassDemo['body'], 0, 180)
    );

    $rPassSbx = $httpReq('GET', "{$baseUrl}/api/v1/pass", ['Accept: application/json', 'X-Payment: sandbox']);
    t_assert('V-01: GET /api/v1/pass com X-Payment: sandbox retorna HTTP 403 SandboxNotAllowedForPass',
        $rPassSbx['code'] === 403 && ($rPassSbx['json']['error'] ?? '') === 'SandboxNotAllowedForPass',
        "code={$rPassSbx['code']}"
    );

    // Verifica que as tentativas bloqueadas não consumiram a cota diária sandbox
    $sbRatesAfterBlock = is_file($sandboxFile) ? (json_decode((string) file_get_contents($sandboxFile), true) ?: []) : [];
    t_assert('V-01: Bloqueio pré-validação em /api/v1/pass não gasta cota sandbox', empty($sbRatesAfterBlock));

    $beforePassA = grid402_get_session_pass_status($tokenA);
    $rPassByPass = $httpReq('GET', "{$baseUrl}/api/v1/pass", ['Accept: application/json', "X-Payment: {$tokenA}"]);
    $afterPassA = grid402_get_session_pass_status($tokenA);
    t_assert('V-01: GET /api/v1/pass com Session Pass existente retorna HTTP 403 PassCannotPurchasePass sem debitar',
        $rPassByPass['code'] === 403 &&
        ($rPassByPass['json']['error'] ?? '') === 'PassCannotPurchasePass' &&
        (int) ($beforePassA['credits_remaining'] ?? -1) === (int) ($afterPassA['credits_remaining'] ?? -2),
        "code={$rPassByPass['code']} err=" . ($rPassByPass['json']['error'] ?? '')
    );

    // MCP grid402_pass bloqueia demo e pass-por-pass
    $rMcpPassDemo = $httpReq('POST', "{$baseUrl}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 101, 'method' => 'tools/call',
        'params' => ['name' => 'grid402_pass', 'arguments' => ['payment_tx_hash' => 'demo']],
    ]));
    t_assert('V-01: MCP grid402_pass bloqueia payment_tx_hash=demo (isError + motivo sandbox)',
        $rMcpPassDemo['code'] === 200 &&
        ($rMcpPassDemo['json']['result']['isError'] ?? false) === true &&
        str_contains(strtolower((string) ($rMcpPassDemo['json']['result']['_meta']['x402/error']['error'] ?? '')), 'sandbox'),
        substr($rMcpPassDemo['body'], 0, 220)
    );

    $rMcpPassByPass = $httpReq('POST', "{$baseUrl}/mcp", ['Content-Type: application/json', "X-Payment: {$tokenA}"], json_encode([
        'jsonrpc' => '2.0', 'id' => 102, 'method' => 'tools/call',
        'params' => ['name' => 'grid402_pass', 'arguments' => []],
    ]));
    $afterMcpPassA = grid402_get_session_pass_status($tokenA);
    t_assert('V-01: MCP grid402_pass bloqueia pagamento pass-por-pass sem debitar saldo',
        $rMcpPassByPass['code'] === 200 &&
        ($rMcpPassByPass['json']['result']['isError'] ?? false) === true &&
        (int) ($beforePassA['credits_remaining'] ?? -1) === (int) ($afterMcpPassA['credits_remaining'] ?? -2),
        substr($rMcpPassByPass['body'], 0, 220)
    );

    // Defesa em profundidade: issue_session_pass em sandbox limita a 3 créditos com locked_limits=true
    $sbxPass = grid402_issue_session_pass('demo', 20000000, true);
    $sbxStatus = grid402_get_session_pass_status((string) ($sbxPass['pass_token'] ?? ''));
    t_assert('V-01: Defesa em profundidade limita passe sandbox a 3 créditos, daily_credit_limit=3 e locked_limits=true',
        (int) ($sbxPass['credits_total'] ?? 0) === 3 &&
        (int) ($sbxStatus['credits_remaining'] ?? 0) === 3 &&
        (int) ($sbxStatus['daily_credit_limit'] ?? 0) === 3 &&
        !empty($sbxStatus['locked_limits']),
        json_encode($sbxStatus)
    );

    // =========================================================================
    // [2] V-02: CF-Connecting-IP, sandbox_rates sob flock e promo_* travado
    // =========================================================================
    echo "\n[2/9] V-02 — IP confiável (CF-Connecting-IP), sandbox flock fail-closed e promo_* travado:\n";

    $origServer = $_SERVER;
    $_SERVER['REMOTE_ADDR'] = '198.51.100.77';
    $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.99';
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    putenv('GRID402_TRUST_CF_HEADER=false');
    $_ENV['GRID402_TRUST_CF_HEADER'] = 'false';
    $ipNoCf = grid402_get_trusted_client_ip();
    t_assert('V-02: CF-Connecting-IP é ignorado por padrão (GRID402_TRUST_CF_HEADER=false)', $ipNoCf === '198.51.100.77', "Got: {$ipNoCf}");

    putenv('GRID402_TRUST_CF_HEADER=true');
    $_ENV['GRID402_TRUST_CF_HEADER'] = 'true';
    $ipWithCf = grid402_get_trusted_client_ip();
    t_assert('V-02: CF-Connecting-IP é aceito apenas com opt-in GRID402_TRUST_CF_HEADER=true', $ipWithCf === '203.0.113.99', "Got: {$ipWithCf}");

    // Restaura padrão seguro
    putenv('GRID402_TRUST_CF_HEADER=false');
    $_ENV['GRID402_TRUST_CF_HEADER'] = 'false';
    $_SERVER = $origServer;

    // sandbox_rates.json sob flock: 3 permitidas, 4ª bloqueada
    $rlIp = '198.51.100.200';
    $rl1 = grid402_check_sandbox_rate_limit($rlIp);
    $rl2 = grid402_check_sandbox_rate_limit($rlIp);
    $rl3 = grid402_check_sandbox_rate_limit($rlIp);
    $rl4 = grid402_check_sandbox_rate_limit($rlIp);
    t_assert('V-02: grid402_check_sandbox_rate_limit (flock) libera 3 e bloqueia a 4ª',
        !empty($rl1['allowed']) && !empty($rl2['allowed']) && !empty($rl3['allowed']) && empty($rl4['allowed']),
        json_encode([$rl1['allowed'] ?? null, $rl2['allowed'] ?? null, $rl3['allowed'] ?? null, $rl4['allowed'] ?? null])
    );

    // Promo pass MCPTRY50 nasce com daily_credit_limit=25 e locked_limits=true -> 403 PassLimitsLocked ao tentar alterar
    $promoNow = strtotime('2026-11-25T12:00:00Z');
    $promoClaim = grid402_promo_claim_pass('MCPTRY50', '198.51.100.42', $promoNow);
    $promoToken = (string) ($promoClaim['pass_token'] ?? '');
    t_assert('V-02: Cupom MCPTRY50 emitido com daily_credit_limit=25',
        !empty($promoClaim['ok']) && str_starts_with($promoToken, 'pass_g402_') && (int) ($promoClaim['daily_credit_limit'] ?? 0) === 25,
        json_encode($promoClaim)
    );
    $promoStatus = grid402_get_session_pass_status($promoToken);
    t_assert('V-02: Promo pass expõe locked_limits=true', !empty($promoStatus['locked_limits']));

    $promoUnlockDirect = grid402_update_session_pass_limits($promoToken, 0, []);
    t_assert('V-02: Tentativa de remover daily_credit_limit em promo_* retorna 403 PassLimitsLocked',
        empty($promoUnlockDirect['ok']) && (int) ($promoUnlockDirect['code'] ?? 0) === 403 && ($promoUnlockDirect['error'] ?? '') === 'PassLimitsLocked',
        json_encode($promoUnlockDirect)
    );

    $rPromoLimitsHttp = $httpReq('POST', "{$baseUrl}/api/v1/pass/limits", [
        'Content-Type: application/json',
        'Accept: application/json',
        "X-Payment: {$promoToken}",
    ], json_encode(['daily_credit_limit' => 9999]));
    t_assert('V-02: POST /api/v1/pass/limits em promo_* retorna HTTP 403 PassLimitsLocked',
        $rPromoLimitsHttp['code'] === 403 && ($rPromoLimitsHttp['json']['error'] ?? '') === 'PassLimitsLocked',
        "code={$rPromoLimitsHttp['code']} body=" . substr($rPromoLimitsHttp['body'], 0, 180)
    );

    // =========================================================================
    // [3] V-03: Idempotency-Key segura (cross-client, credencial inválida, /pass, sandbox, POST body)
    // =========================================================================
    echo "\n[3/9] V-03 — Idempotency-Key segura:\n";

    $idemKey = 'idem_audit_' . bin2hex(random_bytes(8));
    $aBeforeIdem = (int) (grid402_get_session_pass_status($tokenA)['credits_remaining'] ?? 0);
    $bBeforeIdem = (int) (grid402_get_session_pass_status($tokenB)['credits_remaining'] ?? 0);

    $rIdem1 = $httpReq('GET', "{$baseUrl}/api/v1/holidays?year=2026", [
        'Accept: application/json',
        "X-Payment: {$tokenA}",
        "Idempotency-Key: {$idemKey}",
    ]);
    $aAfter1 = (int) (grid402_get_session_pass_status($tokenA)['credits_remaining'] ?? 0);
    t_assert('V-03: 1ª chamada com Idempotency-Key (Pass A) retorna 200, X-Idempotent-Replay: false e debita 1 cr',
        $rIdem1['code'] === 200 &&
        ($rIdem1['headers']['x-idempotent-replay'] ?? '') === 'false' &&
        $aAfter1 === ($aBeforeIdem - 1),
        "code={$rIdem1['code']} replay=" . ($rIdem1['headers']['x-idempotent-replay'] ?? '') . " rem={$aAfter1}"
    );

    $rIdem2 = $httpReq('GET', "{$baseUrl}/api/v1/holidays?year=2026", [
        'Accept: application/json',
        "X-Payment: {$tokenA}",
        "Idempotency-Key: {$idemKey}",
    ]);
    $aAfter2 = (int) (grid402_get_session_pass_status($tokenA)['credits_remaining'] ?? 0);
    t_assert('V-03: Reenvio pelo MESMO Pass A retorna X-Idempotent-Replay: true sem débito extra',
        $rIdem2['code'] === 200 &&
        ($rIdem2['headers']['x-idempotent-replay'] ?? '') === 'true' &&
        $aAfter2 === $aAfter1,
        "code={$rIdem2['code']} replay=" . ($rIdem2['headers']['x-idempotent-replay'] ?? '') . " rem={$aAfter2}"
    );

    // Tentativa cross-client (Pass B com mesma Idempotency-Key)
    $rIdemCross = $httpReq('GET', "{$baseUrl}/api/v1/holidays?year=2026", [
        'Accept: application/json',
        "X-Payment: {$tokenB}",
        "Idempotency-Key: {$idemKey}",
    ]);
    $bAfterCross = (int) (grid402_get_session_pass_status($tokenB)['credits_remaining'] ?? 0);
    t_assert('V-03: Pass B com mesma Idempotency-Key NÃO herda cache de Pass A (replay=false e debita Pass B)',
        $rIdemCross['code'] === 200 &&
        ($rIdemCross['headers']['x-idempotent-replay'] ?? '') === 'false' &&
        $bAfterCross === ($bBeforeIdem - 1),
        "code={$rIdemCross['code']} replay=" . ($rIdemCross['headers']['x-idempotent-replay'] ?? '')
    );

    // Tentativa com string inválida de X-Payment + mesma Idempotency-Key
    $rIdemInvalid = $httpReq('GET', "{$baseUrl}/api/v1/holidays?year=2026", [
        'Accept: application/json',
        'X-Payment: string_invalida_ataque_123',
        "Idempotency-Key: {$idemKey}",
    ]);
    t_assert('V-03: Credencial inválida + Idempotency-Key existente é rejeitada (>=400, nunca dá cache hit)',
        in_array($rIdemInvalid['code'], [400, 402, 403], true) && ($rIdemInvalid['headers']['x-idempotent-replay'] ?? '') !== 'true',
        "code={$rIdemInvalid['code']}"
    );

    // Exclusão de sandbox no cache de idempotência
    $idemSbxKey = 'idem_sbx_' . bin2hex(random_bytes(6));
    $rIdemSbx1 = $httpReq('GET', "{$baseUrl}/api/v1/holidays?year=2026", [
        'Accept: application/json',
        'X-Payment: demo',
        "Idempotency-Key: {$idemSbxKey}",
    ]);
    $rIdemSbx2 = $httpReq('GET', "{$baseUrl}/api/v1/holidays?year=2026", [
        'Accept: application/json',
        'X-Payment: demo',
        "Idempotency-Key: {$idemSbxKey}",
    ]);
    t_assert('V-03: Modo sandbox nunca retorna X-Idempotent-Replay: true',
        $rIdemSbx1['code'] === 200 &&
        $rIdemSbx2['code'] === 200 &&
        ($rIdemSbx2['headers']['x-idempotent-replay'] ?? '') !== 'true'
    );

    // Exclusão estrita de /pass e /pass-emitir no helper de idempotência
    grid402_idempotency_store('idem_pass_blocked', 'pass', [], ['ok' => true], 'pass:abc');
    grid402_idempotency_store('idem_emitir_blocked', 'pass-emitir', [], ['ok' => true], 'tx:abc');
    $idemFileRaw = is_file(GRID402_FILE_IDEMPOTENCY) ? (string) file_get_contents(GRID402_FILE_IDEMPOTENCY) : '';
    t_assert('V-03: grid402_idempotency_store/check rejeita endpoints pass e pass-emitir',
        grid402_idempotency_check('idem_pass_blocked', 'pass', [], 'pass:abc') === null &&
        grid402_idempotency_check('idem_emitir_blocked', 'pass-emitir', [], 'tx:abc') === null &&
        !str_contains($idemFileRaw, 'idem_pass_blocked') &&
        !str_contains($idemFileRaw, 'idem_emitir_blocked')
    );

    // Escopo de corpo POST /api/v1/notarize: mesma Idempotency-Key com body diferente NÃO retorna replay
    $idemPostKey = 'idem_post_' . bin2hex(random_bytes(6));
    $rNot1 = $httpReq('POST', "{$baseUrl}/api/v1/notarize", [
        'Content-Type: application/json',
        'Accept: application/json',
        "X-Payment: {$tokenA}",
        "Idempotency-Key: {$idemPostKey}",
    ], json_encode(['data' => 'Documento A para prova']));
    $rNot2 = $httpReq('POST', "{$baseUrl}/api/v1/notarize", [
        'Content-Type: application/json',
        'Accept: application/json',
        "X-Payment: {$tokenA}",
        "Idempotency-Key: {$idemPostKey}",
    ], json_encode(['data' => 'Documento B completamente diferente']));
    t_assert('V-03: POST /notarize com mesma Idempotency-Key e corpo diferente isola escopo (replay=false e hash diferente)',
        $rNot1['code'] === 200 &&
        $rNot2['code'] === 200 &&
        ($rNot2['headers']['x-idempotent-replay'] ?? '') === 'false' &&
        !empty($rNot1['json']['input_sha256']) &&
        !empty($rNot2['json']['input_sha256']) &&
        ($rNot1['json']['input_sha256'] ?? '') !== ($rNot2['json']['input_sha256'] ?? ''),
        "code1={$rNot1['code']} code2={$rNot2['code']} replay2=" . ($rNot2['headers']['x-idempotent-replay'] ?? '') . " h1=" . ($rNot1['json']['input_sha256'] ?? '') . " h2=" . ($rNot2['json']['input_sha256'] ?? '')
    );

    // =========================================================================
    // [4] V-04 + V-10: TransactionExpired (>72h) e Anti-Replay cross-mode v1 normalizado
    // =========================================================================
    echo "\n[4/9] V-04 + V-10 — Expiração >72h (403 TransactionExpired) e Anti-Replay cross-mode v1:\n";

    $oldTs = time() - (80 * 3600);
    $ageFail = grid402_validate_tx_block_timestamp($oldTs, 'Base Mainnet');
    t_assert('V-04: Bloco >72h rejeitado com HTTP 403 TransactionExpired',
        empty($ageFail['valid']) && ($ageFail['code'] ?? 0) === 403 && ($ageFail['error'] ?? '') === 'TransactionExpired',
        json_encode($ageFail)
    );
    $ageMissing = grid402_validate_tx_block_timestamp(null, 'Base Mainnet');
    t_assert('V-04: Bloco sem timestamp rejeitado fail-closed com HTTP 403 TransactionExpired',
        empty($ageMissing['valid']) && ($ageMissing['code'] ?? 0) === 403 && ($ageMissing['error'] ?? '') === 'TransactionExpired',
        json_encode($ageMissing)
    );

    // V-10: x402 v1 na Polygon ('polygon' slug) grava 'polygon:<tx>' normalizado em used_txs.json
    $v1SettleTx = '0x' . bin2hex(random_bytes(32));
    $v1Calls = [];
    t_install_transport([
        '/v2/x402/verify' => ['status' => 200, 'body' => ['isValid' => true, 'payer' => '0x' . str_repeat('22', 20)]],
        '/v2/x402/settle' => ['status' => 200, 'body' => ['success' => true, 'transaction' => $v1SettleTx, 'network' => 'polygon']],
    ], $v1Calls);

    $polyV1Payload = base64_encode((string) json_encode([
        'x402Version' => 1,
        'scheme' => 'exact',
        'network' => 'polygon',
        'payload' => [
            'signature' => '0x' . str_repeat('ab', 65),
            'authorization' => [
                'from' => '0x' . str_repeat('22', 20),
                'to' => GRID402_WALLET_ADDRESS,
                'value' => '5000',
                'validAfter' => (string) (time() - 10),
                'validBefore' => (string) (time() + 300),
                'nonce' => '0x' . bin2hex(random_bytes(32)),
            ],
        ],
    ]));
    $v1Settled = grid402_validate_exact_payment($polyV1Payload, 5000, 'forex');
    $usedAfterV1 = grid402_get_used_transactions();
    $v1ReplayDirect = grid402_validate_payment('polygon:' . $v1SettleTx, 5000);
    $v1ReplayCaip = grid402_validate_payment('eip155:137:' . $v1SettleTx, 5000);
    t_assert('V-10: Liquidação x402 v1 em polygon grava polygon:<tx> em used_txs.json e bloqueia replay cross-mode (polygon: e eip155:137:)',
        !empty($v1Settled['valid']) &&
        isset($usedAfterV1['polygon:' . strtolower($v1SettleTx)]) &&
        empty($v1ReplayDirect['valid']) && ($v1ReplayDirect['code'] ?? 0) === 403 &&
        empty($v1ReplayCaip['valid']) && ($v1ReplayCaip['code'] ?? 0) === 403,
        json_encode(['settled' => $v1Settled['valid'] ?? null, 'direct' => $v1ReplayDirect['code'] ?? null, 'caip' => $v1ReplayCaip['code'] ?? null])
    );

    // =========================================================================
    // [5] V-06 + Extra #1: mcp.php (@x402/mcp objeto JSON, isError, refund em erro != 200, logs + growth)
    // =========================================================================
    echo "\n[5/9] V-06 + Extra #1 — MCP HTTP (@x402/mcp, objeto _meta, refund e telemetria):\n";

    // 5.1 Sem pagamento -> 1a chamada recebe auto-trial 1/dia (mcp_sandbox); 2a chamada retorna formato @x402/mcp + header PAYMENT-REQUIRED
    $rMcpTrial = $httpReq('POST', "{$baseUrl}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 200, 'method' => 'tools/call',
        'params' => ['name' => 'grid402_forex', 'arguments' => []],
    ]));
    $mcpTrialTxt = (string) ($rMcpTrial['json']['result']['content'][0]['text'] ?? '');
    $mcpTrialPayload = json_decode($mcpTrialTxt, true) ?: [];
    t_assert('V-06/RF10: /mcp 1a chamada sem pagamento concede auto-trial 1/dia (mcp_sandbox)',
        $rMcpTrial['code'] === 200 &&
        empty($rMcpTrial['json']['result']['isError']) &&
        !empty($mcpTrialPayload['sandbox']) &&
        ($mcpTrialPayload['_trial_notice']['mode'] ?? '') === 'mcp_sandbox',
        substr($rMcpTrial['body'], 0, 240)
    );

    $rMcp402 = $httpReq('POST', "{$baseUrl}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 201, 'method' => 'tools/call',
        'params' => ['name' => 'grid402_forex', 'arguments' => []],
    ]));
    $mcpErrMeta = $rMcp402['json']['result']['_meta']['x402/error'] ?? [];
    t_assert('V-06: /mcp 2a chamada sem pagamento devolve result.isError=true + _meta[x402/error] + PAYMENT-REQUIRED',
        $rMcp402['code'] === 200 &&
        ($rMcp402['json']['result']['isError'] ?? false) === true &&
        ($mcpErrMeta['x402Version'] ?? 0) === 2 &&
        ($mcpErrMeta['price_usdc'] ?? '') === '0.0050' &&
        !empty($mcpErrMeta['accepts']) &&
        !empty($rMcp402['headers']['payment-required']),
        substr($rMcp402['body'], 0, 240)
    );

    // 5.2 Envio de _meta['x402/payment'] como OBJETO JSON (padrão @x402/mcp, não string base64)
    $expiredObjPayload = [
        'x402Version' => 2,
        'accepted' => [
            'scheme' => 'exact',
            'network' => 'eip155:8453',
            'asset' => GRID402_USDC_CONTRACT,
            'amount' => '5000',
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
        ],
        'payload' => [
            'signature' => '0x' . str_repeat('ab', 65),
            'authorization' => [
                'from' => '0x' . str_repeat('33', 20),
                'to' => GRID402_WALLET_ADDRESS,
                'value' => '5000',
                'validAfter' => '0',
                'validBefore' => (string) (time() - 120),
                'nonce' => '0x' . bin2hex(random_bytes(32)),
            ],
        ],
    ];
    $rMcpObjMeta = $httpReq('POST', "{$baseUrl}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 202, 'method' => 'tools/call',
        'params' => [
            'name' => 'grid402_forex',
            'arguments' => [],
            '_meta' => ['x402/payment' => $expiredObjPayload],
        ],
    ]));
    $objReason = strtolower((string) ($rMcpObjMeta['json']['result']['_meta']['x402/error']['error'] ?? ''));
    t_assert('V-06: /mcp aceita objeto JSON em params._meta["x402/payment"] (parseia x402 real e detecta expiração em vez de "Array")',
        $rMcpObjMeta['code'] === 200 &&
        ($rMcpObjMeta['json']['result']['isError'] ?? false) === true &&
        (str_contains($objReason, 'expir') || str_contains($objReason, 'validbefore')),
        "reason={$objReason}"
    );

    // 5.3 Chamada paga em /mcp com Session Pass + log em requests.jsonl e growth.json
    $rMcpPaid = $httpReq('POST', "{$baseUrl}/mcp", [
        'Content-Type: application/json',
        "X-Payment: {$tokenA}",
    ], json_encode([
        'jsonrpc' => '2.0', 'id' => 203, 'method' => 'tools/call',
        'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
    ]));
    $mcpPaidTxt = (string) ($rMcpPaid['json']['result']['content'][0]['text'] ?? '');
    $mcpPaidPayload = json_decode($mcpPaidTxt, true) ?: [];
    t_assert('V-06: /mcp com Session Pass retorna 200 com _oracle_proof e _meta',
        $rMcpPaid['code'] === 200 && isset($mcpPaidPayload['_oracle_proof'], $mcpPaidPayload['_meta'])
    );

    // 5.4 Extra #1: erro de execução pós-pagamento (!= 200) em /mcp estorna Session Pass
    $aBeforeMcpFail = (int) (grid402_get_session_pass_status($tokenA)['credits_remaining'] ?? 0);
    $rMcpExecErr = $httpReq('POST', "{$baseUrl}/mcp", [
        'Content-Type: application/json',
        "X-Payment: {$tokenA}",
    ], json_encode([
        'jsonrpc' => '2.0', 'id' => 204, 'method' => 'tools/call',
        'params' => ['name' => 'grid402_workdays', 'arguments' => ['start_date' => 'data-invalida']],
    ]));
    $aAfterMcpFail = (int) (grid402_get_session_pass_status($tokenA)['credits_remaining'] ?? 0);
    t_assert('Extra #1: Falha de execução (!= 200) em /mcp estorna créditos do Session Pass',
        $rMcpExecErr['code'] === 200 && $aAfterMcpFail === $aBeforeMcpFail,
        "before={$aBeforeMcpFail} after={$aAfterMcpFail} body=" . substr($rMcpExecErr['body'], 0, 200)
    );

    // Verifica que /mcp gravou eventos em requests.jsonl e growth.json
    $jsonlRaw = is_file($logFile) ? (string) file_get_contents($logFile) : '';
    $growthAfterMcp = grid402_growth_read();
    $hasMcpChallengeGrowth = false;
    $hasMcpPaidGrowth = false;
    foreach ($growthAfterMcp['events'] ?? [] as $gev) {
        if (($gev['type'] ?? '') === 'challenge_402' && ($gev['service'] ?? '') === 'forex') {
            $hasMcpChallengeGrowth = true;
        }
        if (($gev['type'] ?? '') === 'paid_request' && ($gev['service'] ?? '') === 'holidays') {
            $hasMcpPaidGrowth = true;
        }
    }
    t_assert('V-06/V-19: /mcp registra eventos em requests.jsonl (com price_usdc) e growth.json (challenge_402 + paid_request)',
        str_contains($jsonlRaw, '"service":"forex"') &&
        str_contains($jsonlRaw, '"price_usdc"') &&
        $hasMcpChallengeGrowth &&
        $hasMcpPaidGrowth
    );

    // =========================================================================
    // [6] V-11: Coerção int->string no PayAI + suporte permit2Authorization
    // =========================================================================
    echo "\n[6/9] V-11 — Coerção int->string (PayAI) e suporte Permit2 (@x402/evm v2):\n";

    $intAuthPayload = base64_encode((string) json_encode([
        'x402Version' => 2,
        'accepted' => [
            'scheme' => 'exact',
            'network' => 'eip155:8453',
            'asset' => GRID402_USDC_CONTRACT,
            'amount' => '5000',
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
        ],
        'payload' => [
            'signature' => '0x' . str_repeat('cd', 65),
            'authorization' => [
                'from' => '0x' . str_repeat('44', 20),
                'to' => GRID402_WALLET_ADDRESS,
                'value' => 5000, // int puro
                'validAfter' => time() - 10, // int puro
                'validBefore' => time() + 300, // int puro
                'nonce' => '0x' . bin2hex(random_bytes(32)),
            ],
        ],
    ]));
    $decInt = grid402_decode_x402_payload($intAuthPayload);
    $rawAuth = $decInt['raw']['payload']['authorization'] ?? [];
    t_assert('V-11: Coerção int->string em authorization.value/validAfter/validBefore',
        !empty($decInt['ok']) &&
        is_string($rawAuth['value'] ?? null) &&
        is_string($rawAuth['validAfter'] ?? null) &&
        is_string($rawAuth['validBefore'] ?? null),
        json_encode($rawAuth)
    );

    // Permit2 (@x402/evm v2 permit2Authorization)
    $p2Spender = '0x000000000022d473030f116ddee9f6b43ac78ba3';
    $p2Nonce = (string) random_int(100000, 999999999);
    $p2Payload = base64_encode((string) json_encode([
        'x402Version' => 2,
        'accepted' => [
            'scheme' => 'exact',
            'network' => 'eip155:8453',
            'asset' => GRID402_USDC_CONTRACT,
            'amount' => '5000',
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
        ],
        'payload' => [
            'signature' => '0x' . str_repeat('ef', 65),
            'permit2Authorization' => [
                'from' => '0x' . str_repeat('55', 20),
                'spender' => $p2Spender,
                'nonce' => (int) $p2Nonce, // int -> deve ser coagido a string
                'deadline' => time() + 300, // int -> string
                'permitted' => [
                    'token' => GRID402_USDC_CONTRACT,
                    'amount' => 5000, // int -> string
                ],
                'witness' => [
                    'to' => GRID402_WALLET_ADDRESS,
                    'validAfter' => time() - 10, // int -> string
                ],
            ],
        ],
    ]));
    $decP2 = grid402_decode_x402_payload($p2Payload);
    $valP2 = grid402_validate_exact_requirements($decP2, 5000);
    $p2Calls = [];
    $p2Tx = '0x' . bin2hex(random_bytes(32));
    t_install_transport([
        '/v2/x402/verify' => ['status' => 200, 'body' => ['isValid' => true, 'payer' => '0x' . str_repeat('55', 20)]],
        '/v2/x402/settle' => ['status' => 200, 'body' => ['success' => true, 'transaction' => $p2Tx, 'network' => 'eip155:8453']],
    ], $p2Calls);
    $p2Settled = grid402_validate_exact_payment($p2Payload, 5000, 'forex');
    $p2Replay = grid402_validate_exact_payment($p2Payload, 5000, 'forex');
    $sentP2Extra = $p2Calls[0]['body']['paymentRequirements']['extra'] ?? [];
    $sentP2Body = $p2Calls[0]['body']['paymentPayload']['payload']['permit2Authorization'] ?? [];
    t_assert('V-11: permit2Authorization decodifica, coage ints para string, propaga assetTransferMethod=permit2 e bloqueia replay',
        !empty($decP2['ok']) &&
        ($decP2['auth_type'] ?? '') === 'permit2' &&
        !empty($valP2['valid']) &&
        !empty($p2Settled['valid']) &&
        empty($p2Replay['valid']) && ($p2Replay['code'] ?? 0) === 403 &&
        ($sentP2Extra['assetTransferMethod'] ?? '') === 'permit2' &&
        is_string($sentP2Body['permitted']['amount'] ?? null) &&
        is_string($sentP2Body['nonce'] ?? null),
        json_encode(['extra' => $sentP2Extra, 'replay' => $p2Replay['code'] ?? null])
    );

    // =========================================================================
    // [7] V-12: /api/v1/pass-emitir (<5000 raw rejeitado + idempotent: true sem duplicar receita)
    // =========================================================================
    echo "\n[7/9] V-12 — /api/v1/pass-emitir (mínimo 5000 raw e reenvio idempotente sem duplicar receita):\n";

    $rejectedLowUnits = false;
    try {
        $lowTx = '0x' . bin2hex(random_bytes(32));
        grid402_claim_transaction_lock($lowTx);
        grid402_issue_session_pass($lowTx, 4999, false);
    } catch (\InvalidArgumentException $e) {
        $rejectedLowUnits = true;
    }
    t_assert('V-12: grid402_issue_session_pass rejeita depósito < 5000 raw units com InvalidArgumentException', $rejectedLowUnits);

    // Simula passe já emitido para $txIdemEmitir e reenvio em POST /api/v1/pass-emitir
    $txIdemEmitir = '0x' . bin2hex(random_bytes(32));
    grid402_claim_transaction_lock($txIdemEmitir);
    $issuedOrig = grid402_issue_session_pass($txIdemEmitir, 2000000, false);
    $revBeforeReemit = (float) (grid402_get_metrics()['total_revenue_usdc'] ?? 0.0);

    $rReemit = $httpReq('POST', "{$baseUrl}/api/v1/pass-emitir", [
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode(['txHash' => $txIdemEmitir, 'chain' => 'base']));
    $revAfterReemit = (float) (grid402_get_metrics()['total_revenue_usdc'] ?? 0.0);
    t_assert('V-12: Reenvio de tx já emitida em POST /api/v1/pass-emitir retorna HTTP 200 idempotent=true, mesmo pass_token e zero receita duplicada',
        $rReemit['code'] === 200 &&
        ($rReemit['json']['idempotent'] ?? false) === true &&
        ($rReemit['json']['pass_token'] ?? '') === ($issuedOrig['pass_token'] ?? '') &&
        (int) ($rReemit['json']['raw_usdc_units'] ?? -1) === 0 &&
        abs($revAfterReemit - $revBeforeReemit) < 0.000001,
        "code={$rReemit['code']} body=" . substr($rReemit['body'], 0, 220)
    );

    // Tx travada em used_txs sem Session Pass correspondente continua bloqueada 403 ReplayDetected
    $txOrphanReplay = '0x' . bin2hex(random_bytes(32));
    grid402_claim_transaction_lock($txOrphanReplay);
    $rOrphanReplay = $httpReq('POST', "{$baseUrl}/api/v1/pass-emitir", [
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode(['txHash' => $txOrphanReplay, 'chain' => 'base']));
    t_assert('V-12: Tx usada em consulta comum (sem pass emitido) continua retornando HTTP 403 ReplayDetected',
        $rOrphanReplay['code'] === 403 && ($rOrphanReplay['json']['error'] ?? '') === 'ReplayDetected',
        "code={$rOrphanReplay['code']} err=" . ($rOrphanReplay['json']['error'] ?? '')
    );

    // =========================================================================
    // [8] V-13 + V-14 + V-15: Teasers estáticos, /status limpo e Accept: text/markdown isolado
    // =========================================================================
    echo "\n[8/9] V-13 + V-14 + V-15 — Teasers 402 sem vazamento, /status limpo e Accept: text/markdown:\n";

    $teaserDossier = grid402_teaser_dossier('00000000000191');
    $teaserLei = grid402_preview_lei('5493006MHB84DD0ZWV18');
    $teaserGm = grid402_preview_global_markets('US');
    t_assert('V-13: Teasers de dossier, lei e global-markets são estáticos/mascarados sem dados live',
        ($teaserDossier['preview']['preview_mode'] ?? '') === 'static_schema_sample' &&
        !str_contains(strtoupper((string) json_encode($teaserDossier)), 'BANCO DO BRASIL') &&
        ($teaserLei['preview_mode'] ?? '') === 'static_schema_sample' &&
        ($teaserGm['preview_mode'] ?? '') === 'static_schema_sample'
    );

    // HTTP 402 em /api/v1/dossier com CNPJ real (Petrobras 33000167000101) retorna schema estático sem vazar razão social ou sanções live
    $r402Dos = $httpReq('GET', "{$baseUrl}/api/v1/dossier?cnpj=33000167000101", ['Accept: application/json']);
    $dosPreviewJson = strtoupper((string) json_encode($r402Dos['json']['preview'] ?? []));
    t_assert('V-13: HTTP 402 em /api/v1/dossier retorna schema estático sem vazar razão social ou sanções reais',
        $r402Dos['code'] === 402 &&
        ($r402Dos['json']['preview']['preview_mode'] ?? '') === 'static_schema_sample' &&
        !str_contains($dosPreviewJson, 'PETROBRAS') &&
        !str_contains($dosPreviewJson, 'PETROLEO') &&
        ($r402Dos['json']['preview']['sample_schema']['sanctions']['has_sanctions'] ?? '') === '[LOCKED]',
        "code={$r402Dos['code']} preview=" . substr($dosPreviewJson, 0, 300)
    );

    // V-14 + V-22: GET /status não vaza usd_brl/btc_usd e expõe 25/25 price_usdc com lei=0.0100
    $rStatus = $httpReq('GET', "{$baseUrl}/status", ['Accept: application/json']);
    $stSvcs = $rStatus['json']['services'] ?? [];
    t_assert('V-14/V-22: GET /status não vaza usd_brl/btc_usd e expõe 25 serviços com lei=0.0100',
        $rStatus['code'] === 200 &&
        count($stSvcs) === 25 &&
        !isset($stSvcs['forex']['usd_brl'], $stSvcs['crypto']['btc_usd']) &&
        ($stSvcs['lei']['price_usdc'] ?? '') === '0.0100',
        "code={$rStatus['code']} svcs=" . count($stSvcs)
    );

    // V-15: Accept: text/markdown só atua na raiz / e NÃO sequestra /api/v1/*, /status, /pass
    $rMdRoot = $httpReq('GET', "{$baseUrl}/", ['Accept: text/markdown']);
    $rMdApi = $httpReq('GET', "{$baseUrl}/api/v1/forex", ['Accept: text/markdown']);
    $rMdStatus = $httpReq('GET', "{$baseUrl}/status", ['Accept: text/markdown']);
    $rMdPass = $httpReq('GET', "{$baseUrl}/pass", ['Accept: text/markdown']);
    t_assert('V-15: Accept: text/markdown serve markdown na raiz /, mas preserva JSON 402 em /api/v1/*, JSON 200 em /status e HTML em /pass',
        $rMdRoot['code'] === 200 && str_contains(strtolower($rMdRoot['headers']['content-type'] ?? ''), 'text/markdown') &&
        $rMdApi['code'] === 402 && is_array($rMdApi['json']) &&
        $rMdStatus['code'] === 200 && is_array($rMdStatus['json']) &&
        $rMdPass['code'] === 200 && str_contains(strtolower($rMdPass['headers']['content-type'] ?? ''), 'text/html'),
        "root={$rMdRoot['code']}/" . ($rMdRoot['headers']['content-type'] ?? '') . " api={$rMdApi['code']} status={$rMdStatus['code']} pass={$rMdPass['code']}"
    );

    // =========================================================================
    // [9] V-16 + V-17 + V-18 + V-19: MP webhooks/merchant_order/dedup, Growth pass_purchase, Metrics e Logger
    // =========================================================================
    echo "\n[9/9] V-16 + V-17 + V-18 + V-19 — MP Webhook/MerchantOrder/Dedup, Growth, Metrics e Logger:\n";

    // V-16: notification_url com source_news=webhooks + HMAC sem request-id + merchant_order
    $mpCaptured = [];
    $GLOBALS['grid402_mp_sandbox_override'] = false;
    $GLOBALS['grid402_mp_token_override'] = 'APP_USR-audit-token-123';
    $GLOBALS['grid402_mp_webhook_secret_override'] = 'whsec_audit_suite_123';
    $GLOBALS['grid402_mp_http_transport'] = static function (string $method, string $path, ?array $body, ?string $idemKey) use (&$mpCaptured): array {
        $mpCaptured[] = compact('method', 'path', 'body');
        if ($method === 'POST' && $path === '/v1/payments') {
            return [
                'status' => 201,
                'data' => [
                    'id' => 77112233,
                    'status' => 'pending',
                    'point_of_interaction' => ['transaction_data' => ['qr_code' => '000201PIX', 'qr_code_base64' => base64_encode('PNG')]],
                ],
                'error' => '',
            ];
        }
        if ($method === 'POST' && $path === '/checkout/preferences') {
            return [
                'status' => 201,
                'data' => ['id' => 'pref_audit_1', 'init_point' => 'https://mp.test/checkout/pref_audit_1'],
                'error' => '',
            ];
        }
        if ($method === 'GET' && $path === '/merchant_orders/55443322') {
            $prefOrder = '';
            foreach ($mpCaptured as $c) {
                if (($c['path'] ?? '') === '/checkout/preferences') {
                    $prefOrder = (string) ($c['body']['external_reference'] ?? '');
                }
            }
            return [
                'status' => 200,
                'data' => [
                    'id' => 55443322,
                    'external_reference' => $prefOrder,
                    'order_status' => 'paid',
                    'payments' => [
                        ['id' => 88990011, 'status' => 'approved', 'total_paid_amount' => 12.00],
                    ],
                ],
                'error' => '',
            ];
        }
        if ($method === 'GET' && $path === '/v1/payments/88990011') {
            $prefOrder = '';
            foreach ($mpCaptured as $c) {
                if (($c['path'] ?? '') === '/checkout/preferences') {
                    $prefOrder = (string) ($c['body']['external_reference'] ?? '');
                }
            }
            return [
                'status' => 200,
                'data' => [
                    'id' => 88990011,
                    'status' => 'approved',
                    'transaction_amount' => 12.00,
                    'external_reference' => $prefOrder,
                    'live_mode' => true,
                ],
                'error' => '',
            ];
        }
        return ['status' => 404, 'data' => [], 'error' => 'Not found'];
    };

    $pixAudit = grid402_mp_create_pix('starter');
    $prefAudit = grid402_mp_create_checkout_pro('starter');
    $pixNotifUrl = (string) ($mpCaptured[0]['body']['notification_url'] ?? '');
    $prefNotifUrl = (string) ($mpCaptured[1]['body']['notification_url'] ?? '');
    t_assert('V-16: Pix e Checkout Pro enviam notification_url com ?source_news=webhooks',
        !empty($pixAudit['ok']) && !empty($prefAudit['ok']) &&
        str_ends_with($pixNotifUrl, '/api/v1/pass/mp-webhook?source_news=webhooks') &&
        str_ends_with($prefNotifUrl, '/api/v1/pass/mp-webhook?source_news=webhooks'),
        "pix={$pixNotifUrl} pref={$prefNotifUrl}"
    );

    // Manifest HMAC sem request-id + data.id alfanumérico em uppercase -> lowercase
    $tsNoReq = '1700001234';
    $alphaDataId = 'ABC123XYZ';
    $sigNoReq = hash_hmac('sha256', "id:abc123xyz;ts:{$tsNoReq};", 'whsec_audit_suite_123');
    t_assert('V-16: HMAC webhook sem x-request-id e com data.id alfanumérico maiúsculo é aceito conforme spec MP',
        grid402_mp_verify_webhook_signature("ts={$tsNoReq},v1={$sigNoReq}", '', $alphaDataId, 'whsec_audit_suite_123') === true
    );

    // Webhook topic=merchant_order via resource URL (sem x-request-id) + contagem exata no JSONL (sem duplicar)
    $countMpLogLines = static function (string $file): int {
        if (!is_file($file)) {
            return 0;
        }
        $n = 0;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
            $row = json_decode($ln, true);
            if (is_array($row) && ($row['payment_source'] ?? '') === 'mercadopago' && ($row['service'] ?? '') === 'pass') {
                $n++;
            }
        }
        return $n;
    };
    $mpLinesBefore = $countMpLogLines($logFile);

    $moSig = hash_hmac('sha256', "id:55443322;ts:{$tsNoReq};", 'whsec_audit_suite_123');
    $moRes1 = grid402_mp_handle_webhook(
        ['x-signature' => "ts={$tsNoReq},v1={$moSig}"],
        json_encode(['topic' => 'merchant_order', 'resource' => 'https://api.mercadolibre.com/merchant_orders/55443322']),
        []
    );
    $moRes2 = grid402_mp_handle_webhook(
        ['x-signature' => "ts={$tsNoReq},v1={$moSig}"],
        json_encode(['topic' => 'merchant_order', 'resource' => 'https://api.mercadolibre.com/merchant_orders/55443322']),
        []
    );
    // Polling HTTP repetido em /api/v1/pass/mp-status não deve duplicar linha mercadopago no CSV pago
    $rMpStatusPoll1 = $httpReq('GET', "{$baseUrl}/api/v1/pass/mp-status?order_id=" . urlencode((string) $prefAudit['order_id']));
    $rMpStatusPoll2 = $httpReq('GET', "{$baseUrl}/api/v1/pass/mp-status?order_id=" . urlencode((string) $prefAudit['order_id']));
    $mpLinesAfter = $countMpLogLines($logFile);

    t_assert('V-16: Webhook topic=merchant_order emite pass_g402_..., dedup idempotente e grava exatamente 1 linha paga mercadopago no JSONL mesmo após polling mp-status',
        !empty($moRes1['ok']) &&
        str_starts_with((string) ($moRes1['pass_token'] ?? ''), 'pass_g402_') &&
        !empty($moRes1['logged_paid_request']) &&
        !empty($moRes2['idempotent']) &&
        $rMpStatusPoll1['code'] === 200 && $rMpStatusPoll2['code'] === 200 &&
        ($mpLinesAfter - $mpLinesBefore) === 1,
        "linesDelta=" . ($mpLinesAfter - $mpLinesBefore) . " res1=" . json_encode($moRes1)
    );

    // V-17: pass_purchase em growth.php soma paid_revenue_usdc e deduplica por proof
    $gBefore = grid402_growth_metrics();
    $revGBefore = (float) ($gBefore['paid_revenue_usdc'] ?? 0.0);
    $proofUnique = 'tx_growth_unique_' . bin2hex(random_bytes(6));
    grid402_growth_event('pass_purchase', [
        'service' => 'pass',
        'payer' => '0x' . str_repeat('88', 20),
        'amount_usdc' => 5.0,
        'proof' => $proofUnique,
    ]);
    // Reenvio do mesmo proof não pode duplicar receita
    grid402_growth_event('pass_purchase', [
        'service' => 'pass',
        'payer' => '0x' . str_repeat('88', 20),
        'amount_usdc' => 5.0,
        'proof' => $proofUnique,
    ]);
    $gAfter = grid402_growth_metrics();
    $revGAfter = (float) ($gAfter['paid_revenue_usdc'] ?? 0.0);
    t_assert('V-17: growth.php contabiliza pass_purchase em paid_revenue_usdc e deduplica proof repetido',
        abs(($revGAfter - $revGBefore) - 5.0) < 0.0001 && (int) ($gAfter['wallets_paying'] ?? 0) >= 1,
        "before={$revGBefore} after={$revGAfter}"
    );

    // V-18: page_views humanos e bots fora de total_requests; HEAD ignorado; idle_session_passes ignora promo/sandbox
    grid402_atomic_write(GRID402_FILE_METRICS, grid402_default_metrics());
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0';
    grid402_record_page_visit('home', '198.51.100.10');
    $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
    grid402_record_page_visit('home', '198.51.100.11');
    $_SERVER['REQUEST_METHOD'] = 'HEAD';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
    grid402_record_page_visit('home', '198.51.100.12');
    $_SERVER = $origServer;

    $mVisits = grid402_get_metrics();
    t_assert('V-18: page_views e bots não incrementam total_requests e HEAD é ignorado',
        (int) ($mVisits['total_requests'] ?? -1) === 0 &&
        (int) ($mVisits['total_page_views'] ?? -1) === 1 &&
        (int) ($mVisits['total_bot_views'] ?? -1) === 1,
        json_encode(['req' => $mVisits['total_requests'], 'pv' => $mVisits['total_page_views'], 'bot' => $mVisits['total_bot_views']])
    );

    // service_paid_counts / service_revenue_usdc exclui sandbox e promo_pass
    grid402_record_telemetry('cnpj', 200, false, null, '198.51.100.20', 0.01, 'sandbox', ['payment_source' => 'sandbox']);
    grid402_record_telemetry('cnpj', 200, true, '0x' . str_repeat('9', 64), '198.51.100.20', 0.01, 'paid', ['payment_source' => 'promo_pass']);
    grid402_record_telemetry('cnpj', 200, true, '0x' . str_repeat('a', 64), '198.51.100.20', 0.01, 'paid', ['payment_source' => 'tx_hash']);
    $mPaidSplit = grid402_get_metrics();
    t_assert('V-18: service_paid_counts e service_revenue_usdc contam apenas tráfego pago real (excluindo sandbox e promo_pass)',
        (int) ($mPaidSplit['service_paid_counts']['cnpj'] ?? 0) === 1 &&
        abs((float) ($mPaidSplit['service_revenue_usdc']['cnpj'] ?? 0.0) - 0.01) < 0.0001,
        json_encode(['paid_counts' => $mPaidSplit['service_paid_counts'] ?? [], 'rev' => $mPaidSplit['service_revenue_usdc'] ?? []])
    );

    // V-19: logger.php preserva payment_sources multichain + price_usdc e lê 5xx + arquivo rotacionado .1
    $rotBase = $storageDir . DIRECTORY_SEPARATOR . 'rot_test.jsonl';
    putenv('GRID402_FILE_REQUEST_LOGS=' . $rotBase);
    $_ENV['GRID402_FILE_REQUEST_LOGS'] = $rotBase;
    file_put_contents($rotBase . '.1', json_encode([
        'ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 60),
        'request_id' => 'g402_rot1',
        'service' => 'cnpj',
        'status' => 502,
        'payment_source' => 'solana_spl',
        'price_usdc' => '0.0100',
        'error' => 'UpstreamTimeout',
        'ip_hmac' => 'aaa',
    ]) . "\n");
    grid402_log_request([
        'service' => 'dossier',
        'status' => 200,
        'payment_source' => 'evm_polygon',
        'price_usdc' => '0.0500',
        'method' => 'GET',
    ]);
    grid402_log_request([
        'service' => 'pass',
        'status' => 200,
        'payment_source' => 'web3_topup',
        'price_usdc' => '2.0000',
        'method' => 'POST',
    ]);
    $rotMainLines = file($rotBase, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $rotRow1 = json_decode($rotMainLines[0] ?? '{}', true) ?: [];
    $rotRow2 = json_decode($rotMainLines[1] ?? '{}', true) ?: [];
    $rotFailures = grid402_read_recent_payment_failures(10);
    t_assert('V-19: logger.php preserva payment_source multichain (evm_polygon, web3_topup), grava price_usdc e lê falhas 5xx do arquivo rotacionado .1',
        ($rotRow1['payment_source'] ?? '') === 'evm_polygon' &&
        abs((float) ($rotRow1['price_usdc'] ?? 0) - 0.05) < 0.0001 &&
        ($rotRow2['payment_source'] ?? '') === 'web3_topup' &&
        abs((float) ($rotRow2['price_usdc'] ?? 0) - 2.0) < 0.0001 &&
        count($rotFailures) === 1 &&
        ($rotFailures[0]['status'] ?? 0) === 502 &&
        ($rotFailures[0]['payment_source'] ?? '') === 'solana_spl',
        json_encode(['r1' => $rotRow1, 'r2' => $rotRow2, 'fail' => $rotFailures])
    );
} finally {
    grid402_stop_server($server);
    t_rmdir($storageDir);
}

t_finish('RESULTADO FINAL (Billing Audit Fixes V-01..V-19 + Extra #1)');
