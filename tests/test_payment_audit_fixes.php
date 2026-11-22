<?php
/**
 * Testes de Regressão — Auditoria Financeira e Regras de Negócio (7 Correções)
 *
 * Cobre:
 * 1. Preservação de case em assinaturas Solana Base58 na emissão de Session Pass
 * 2. Estorno automático de Session Pass e liberação de lock tx_hash em falhas HTTP != 200
 * 3. Escala correta (v2_5000) e retorno de recompensa pendente no Growth Engine
 * 4. Reconciliação on-chain comparando saldo real da carteira (wallet_usdc_balance)
 * 5. Normalização de rede CAIP-2 Solana e valor real pago na notificação Telegram
 * 6. Fallback Checkout Pro (external_reference) no Mercado Pago sem nested locks
 * 7. Isolamento de canal RapidAPI Proxy no Growth Engine e CSV histórico ?scope=paid
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$dir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/services/mercadopago.php';
require_once __DIR__ . '/../public_html/services/onchain_stats.php';
require_once __DIR__ . '/../public_html/growth.php';

echo "=== TESTES DAS 7 CORRECOES DA AUDITORIA FINANCEIRA ===\n\n";

// -------------------------------------------------------------------------
// [1] Bug #1: Solana Base58 case-sensitivity em Session Pass
// -------------------------------------------------------------------------
echo "[1] Bug #1 — Solana Base58 case-sensitive na emissao de Session Pass:\n";
$solSig1 = '5VERv8NMvzbJMEkV8xnrLkEaWRtSz9CosKDYjCJjBRnbJLgp8uirBgmQpjKhoR4tjF3ZpRzrFmBV6UjKdiSZkQUW';
$solSigDifferentCase = '5verv8NMvzbJMEkV8xnrLkEaWRtSz9CosKDYjCJjBRnbJLgp8uirBgmQpjKhoR4tjF3ZpRzrFmBV6UjKdiSZkQUW';
$solPass1 = grid402_issue_session_pass('solana:' . $solSig1, 2000000, false);
$solPass1Repeat = grid402_issue_session_pass('solana:' . $solSig1, 2000000, false);
$solPass2 = grid402_issue_session_pass('solana:' . $solSigDifferentCase, 2000000, false);

$passesRaw = @json_decode((string) file_get_contents(GRID402_FILE_PASSES), true) ?: [];
$solPass1Id = grid402_pass_verify_token_format((string) ($solPass1['pass_token'] ?? ''));
t_assert('Pass Solana emitido com sucesso', !empty($solPass1['pass_token']) && $solPass1Id !== null);
t_assert('tx_hash Solana preserva caixa Base58 original no storage', ($passesRaw[$solPass1Id]['tx_hash'] ?? '') === ('solana:' . $solSig1));
t_assert('Idempotencia preserva mesmo token para assinatura Base58 identica', ($solPass1Repeat['pass_token'] ?? '') === ($solPass1['pass_token'] ?? ''));
t_assert('Assinatura Solana com caixa distinta nao colide indevidamente', ($solPass2['pass_token'] ?? '') !== ($solPass1['pass_token'] ?? ''));

$evmHashUpper = '0x' . strtoupper(str_repeat('ab', 32));
$evmHashLower = strtolower($evmHashUpper);
$evmPass1 = grid402_issue_session_pass($evmHashUpper, 2000000, false);
$evmPass2 = grid402_issue_session_pass($evmHashLower, 2000000, false);
t_assert('Hash EVM continua case-insensitive e idempotente', ($evmPass1['pass_token'] ?? '') === ($evmPass2['pass_token'] ?? ''));

// -------------------------------------------------------------------------
// [2] Bug #2: Estorno de Session Pass e liberacao de trava tx_hash em erro HTTP
// -------------------------------------------------------------------------
echo "\n[2] Bug #2 — Estorno de Session Pass e liberacao de trava tx_hash:\n";
$refundPass = grid402_issue_session_pass('0x' . str_repeat('c1', 32), 2000000, false);
$refundToken = (string) ($refundPass['pass_token'] ?? '');
$debitRes = grid402_consume_session_pass($refundToken, 20000, 'cnpj', ['request_id' => 'req_debit_1']);
t_assert('Debito inicial de 4 creditos aplicado (396 restantes)', !empty($debitRes['valid']) && (int) ($debitRes['credits_remaining'] ?? -1) === 396);

$refundOk = grid402_refund_session_pass($refundToken, 4, 'cnpj', ['http_status' => 400, 'request_id' => 'req_debit_1']);
$afterRefund = grid402_get_session_pass_status($refundToken);
t_assert('Estorno retorna true e restaura 400 creditos', $refundOk === true && (int) ($afterRefund['credits_remaining'] ?? -1) === 400);
t_assert('Contadores diarios e usage_count revertidos apos estorno', (int) ($afterRefund['credits_used_today'] ?? -1) === 0 && (int) ($afterRefund['usage_count'] ?? -1) === 0);

// Estorno nunca excede credits_total
$overRefund = grid402_refund_session_pass($refundToken, 999, 'cnpj', ['http_status' => 502]);
$afterOver = grid402_get_session_pass_status($refundToken);
t_assert('Estorno respeita teto de credits_total (400)', $overRefund === true && (int) ($afterOver['credits_remaining'] ?? -1) === 400);

$stmt = grid402_get_session_pass_statement($refundToken, ['limit' => 10]);
$outcomes = array_column((array) ($stmt['entries'] ?? []), 'outcome');
t_assert('Extrato Zero-PII registra evento outcome=refunded', in_array('refunded', $outcomes, true));

// Trava anti-replay de tx_hash pode ser liberada em caso de erro de servico
$lockKey = 'base:0x' . str_repeat('d2', 32);
t_assert('Lock de tx_hash adquirido inicialmente', grid402_claim_transaction_lock($lockKey) === true);
t_assert('Replay bloqueado enquanto lock ativo', grid402_claim_transaction_lock($lockKey) === false);
grid402_release_transaction_lock($lockKey);
t_assert('Lock liberado permite reutilizacao do pagamento apos falha sem entrega', grid402_claim_transaction_lock($lockKey) === true);

// -------------------------------------------------------------------------
// [3] Bug #3: Recompensa pendente Growth Engine (escala v2_5000 + retorno valido)
// -------------------------------------------------------------------------
echo "\n[3] Bug #3 — Recompensa Growth na escala v2_5000 (25 creditos = 25 creditos):\n";
$rewardId = 'rw_audit_test_25';
grid402_growth_update(static function (array $data) use ($rewardId): array {
    $data['rewards'][] = [
        'reward_id' => $rewardId,
        'actor_id' => 'actor_test',
        'reason' => 'audit_test',
        'credits' => 25,
        'status' => 'pending',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    return $data;
});
$rwRes = grid402_growth_issue_pending_reward($rewardId);
t_assert('Recompensa emitida com ok=true e credits=25', !empty($rwRes['ok']) && (int) ($rwRes['credits'] ?? 0) === 25);
$rwToken = (string) ($rwRes['reward']['pass_token'] ?? '');
t_assert('Objeto reward retornado com status=issued e pass_token valido', ($rwRes['reward']['status'] ?? '') === 'issued' && str_starts_with($rwToken, 'pass_g402_'));
$rwPassStatus = grid402_get_session_pass_status($rwToken);
t_assert('Session Pass da recompensa possui exatamente 25 creditos (nao 2)', !empty($rwPassStatus['valid']) && (int) ($rwPassStatus['credits_remaining'] ?? 0) === 25);

// -------------------------------------------------------------------------
// [4] Bug #4: Reconciliacao on-chain com wallet_usdc_balance
// -------------------------------------------------------------------------
echo "\n[4] Bug #4 — Reconciliacao on-chain contra saldo real da carteira:\n";
$cmpWithBalance = grid402_onchain_stats_compare(
    [
        'window_blocks' => 100000,
        'received_usdc' => 0.0,
        'wallet_usdc_balance' => 4.29,
        'transfers' => 0,
        'unique_senders' => 0,
        'rpc_ok' => true,
        'generated_at' => '2026-11-22T10:00:00Z',
    ],
    [
        'total_revenue_usdc' => 4.29,
        'total_paid_requests' => 34,
        'payment_sources' => ['facilitator' => 34],
    ]
);
t_assert('Delta on-chain zerado quando wallet_usdc_balance bate com total_revenue_usdc', abs((float) ($cmpWithBalance['revenue_delta_usdc'] ?? 99)) < 0.000001 && (float) ($cmpWithBalance['wallet_usdc_balance'] ?? 0) === 4.29);

$cmpFallback = grid402_onchain_stats_compare(
    [
        'window_blocks' => 100000,
        'received_usdc' => 1.50,
        'wallet_usdc_balance' => null,
        'transfers' => 3,
        'rpc_ok' => true,
    ],
    ['total_revenue_usdc' => 1.00]
);
t_assert('Fallback para received_usdc quando wallet_usdc_balance e null', abs((float) ($cmpFallback['revenue_delta_usdc'] ?? 0) - 0.50) < 0.000001);

// -------------------------------------------------------------------------
// [5] Bug #5: Normalizacao Solana CAIP-2 e preco real na notificacao Telegram
// -------------------------------------------------------------------------
echo "\n[5] Bug #5 — Solana CAIP-2 e valor real do pacote na notificacao Telegram:\n";
t_assert('CAIP-2 Solana normalizado para slug solana', grid402_normalize_network_slug('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp') === 'solana');
t_assert('Assinatura Base58 Solana validada corretamente', grid402_is_valid_solana_signature($solSig1) === true);
$indexSrc = (string) file_get_contents(__DIR__ . '/../public_html/index.php');
t_assert('index.php normaliza $validatedNetSlug antes da notificacao Telegram', str_contains($indexSrc, 'grid402_normalize_network_slug($rawValidatedNet)'));
t_assert('index.php passa $actualPaidUsdc para grid402_notify_telegram_purchase', str_contains($indexSrc, 'grid402_notify_telegram_purchase($apiParam, (float) $actualPaidUsdc, $txForNotification, false, $validatedNetSlug)'));

// -------------------------------------------------------------------------
// [6] Bug #6: Mercado Pago Checkout Pro fallback external_reference
// -------------------------------------------------------------------------
echo "\n[6] Bug #6 — Fallback Checkout Pro via /v1/payments/search?external_reference=...:\n";
$GLOBALS['grid402_mp_sandbox_override'] = false;
$GLOBALS['grid402_mp_token_override'] = 'APP_USR-audit-token';
$mpSearchCalls = 0;
$lastOrderId = '';
$GLOBALS['grid402_mp_http_transport'] = static function (string $method, string $path, ?array $body, ?string $idemKey) use (&$mpSearchCalls, &$lastOrderId): array {
    if ($method === 'POST' && $path === '/checkout/preferences') {
        $lastOrderId = (string) ($body['external_reference'] ?? '');
        return [
            'status' => 201,
            'data' => [
                'id' => 'pref_audit_pro_1',
                'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_audit_pro_1',
            ],
            'error' => '',
        ];
    }
    if ($method === 'GET' && str_starts_with($path, '/v1/payments/search?external_reference=')) {
        $mpSearchCalls++;
        return [
            'status' => 200,
            'data' => [
                'results' => [
                    [
                        'id' => 77665544,
                        'status' => 'approved',
                        'transaction_amount' => 29.00,
                        'external_reference' => $lastOrderId,
                        'live_mode' => true,
                    ],
                ],
            ],
            'error' => '',
        ];
    }
    return ['status' => 404, 'data' => [], 'error' => 'Unexpected path: ' . $path];
};

$proOrder = grid402_mp_create_checkout_pro('pro', 'audit@grid402.online');
t_assert('Ordem Checkout Pro criada com mp_payment_id nulo inicialmente', !empty($proOrder['ok']) && !empty($proOrder['order_id']));

$pollStatus = grid402_mp_get_order_status((string) $proOrder['order_id']);
t_assert('Fallback consultou /v1/payments/search por external_reference', $mpSearchCalls === 1);
t_assert('Ordem Checkout Pro aprovada e Session Pass emitido sem webhook previo', ($pollStatus['status'] ?? '') === 'approved' && str_starts_with((string) ($pollStatus['pass_token'] ?? ''), 'pass_g402_'));
$proPassInfo = grid402_get_session_pass_status((string) ($pollStatus['pass_token'] ?? ''));
t_assert('Session Pass Pro emitido via fallback possui 1000 creditos', !empty($proPassInfo['valid']) && (int) ($proPassInfo['credits_remaining'] ?? 0) === 1000);

$pollAgain = grid402_mp_get_order_status((string) $proOrder['order_id']);
t_assert('Segunda consulta usa estado aprovado local sem reconsultar API MP', $mpSearchCalls === 1 && ($pollAgain['pass_token'] ?? '') === ($pollStatus['pass_token'] ?? ''));
$GLOBALS['grid402_mp_http_transport'] = null;

// -------------------------------------------------------------------------
// [7] Bug #7: Isolamento RapidAPI Proxy no Growth + HTTP E2E (Estorno + CSV ?scope=paid)
// -------------------------------------------------------------------------
echo "\n[7] Bug #7 — Isolamento RapidAPI Proxy no Growth + E2E HTTP (Estorno & CSV Historico):\n";
// Limpa growth.json para medicao exata
@unlink(GRID402_PRIVATE_DIR . '/growth.json');
grid402_growth_event('paid_request', ['service' => 'forex', 'payer' => 'rapidapi_proxy', 'amount_usdc' => 0.0, 'type' => 'rapidapi']);
grid402_growth_event('paid_request', ['service' => 'cep', 'payer' => 'rapidapi_proxy', 'amount_usdc' => 0.0, 'type' => 'rapidapi']);
grid402_growth_event('paid_request', ['service' => 'cnpj', 'payer' => '0x1234567890abcdef1234567890abcdef1234abcd', 'amount_usdc' => 0.02, 'type' => 'x402']);

$gm = grid402_growth_metrics();
t_assert('rapidapi_proxy nao infla wallets_connected nem wallets_paying (1 carteira Web3)', ($gm['wallets_connected'] ?? -1) === 1 && ($gm['wallets_paying'] ?? -1) === 1);
t_assert('rapidapi_paid_requests contabiliza separadamente (2)', ($gm['rapidapi_paid_requests'] ?? -1) === 2);
t_assert('paid_requests total mantem soma completa (3) e paid_requests_per_service integro', ($gm['paid_requests'] ?? -1) === 3 && (int) ($gm['paid_requests_per_service']['forex'] ?? 0) === 1 && (int) ($gm['paid_requests_per_service']['cnpj'] ?? 0) === 1);

// Sobe servidor HTTP local com storage isolado para testar:
// (a) Estorno automatico de Session Pass em endpoint real que retorna HTTP 400 (/api/v1/cep?cep=123)
// (b) Exportacao CSV historica (/admin?api=export_csv&scope=paid) mesmo apos 150 eventos no log
$reqLog = $dir . '/requests.jsonl';
$lines = [];
$lines[] = json_encode(['ts' => '2026-11-10T10:00:00Z', 'request_id' => 'req_hist_1', 'service' => 'cnpj', 'status' => 200, 'payment_source' => 'facilitator', 'ip_hmac' => 'aa11bb22']);
$lines[] = json_encode(['ts' => '2026-11-11T11:00:00Z', 'request_id' => 'req_hist_2', 'service' => 'forex', 'status' => 200, 'payment_source' => 'tx_hash', 'ip_hmac' => 'cc33dd44']);
$lines[] = json_encode(['ts' => '2026-11-12T12:00:00Z', 'request_id' => 'req_hist_3', 'service' => 'cep', 'status' => 200, 'payment_source' => 'rapidapi', 'ip_hmac' => 'ee55ff66']);
for ($i = 0; $i < 130; $i++) {
    $lines[] = json_encode(['ts' => '2026-11-22T09:00:00Z', 'request_id' => 'req_402_' . $i, 'service' => 'forex', 'status' => 402, 'payment_source' => 'none', 'ip_hmac' => '99887766']);
}
file_put_contents($reqLog, implode("\n", $lines) . "\n");

// Pre-aquece caches de forex e wallet para evitar chamadas de rede externas no servidor single-thread
file_put_contents($dir . '/wallet_balance_cache.json', json_encode([
    'oracle_address' => '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
    'usdc_balance' => 4.29,
    'eth_balance' => 0.01,
    'network' => 'Base Mainnet',
    'chain_id' => 8453,
    'cached_at' => time(),
    'rpc_operational' => true,
]));
file_put_contents($dir . '/forex_cache.json', json_encode([
    'timestamp' => time(),
    'data' => [
        'rates' => ['USD' => ['bid' => 5.50, 'ask' => 5.51]],
    ],
]));

// Prepara admin_auth.json para login direto no servidor local
$adminUser = 'audit_admin';
$adminPass = 'AuditPass!2026';
file_put_contents($dir . '/admin_auth.json', json_encode([
    'username' => $adminUser,
    'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]),
    'two_factor_telegram' => false,
    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
]));

$server = grid402_start_server([
    'docroot' => __DIR__ . '/../public_html',
    'router' => __DIR__ . '/../public_html/router.php',
    'probe' => '/admin',
    'env' => [
        'GRID402_STORAGE_DIR' => $dir,
        'GRID402_FILE_REQUEST_LOGS' => $reqLog,
        'GRID402_DISABLE_TELEGRAM' => '1',
    ],
]);
$port = $server['port'];
t_assert('Servidor HTTP local iniciado e respondendo na porta ' . $port, $port > 0);

try {
    // (a) Teste E2E de estorno de Session Pass quando /api/v1/cep retorna HTTP 400
    $beforeHttpPass = grid402_get_session_pass_status($refundToken);
    $creditsBeforeHttp = (int) ($beforeHttpPass['credits_remaining'] ?? 0);

    $ch = curl_init("http://127.0.0.1:{$port}/api/v1/cep?cep=INVALIDO");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Payment: ' . $refundToken, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 5,
    ]);
    $cepResp = curl_exec($ch);
    $cepCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $afterHttpPass = grid402_get_session_pass_status($refundToken);
    $creditsAfterHttp = (int) ($afterHttpPass['credits_remaining'] ?? -1);
    t_assert('Endpoint /api/v1/cep?cep=INVALIDO retorna HTTP 400', $cepCode === 400);
    t_assert('Session Pass estornado automaticamente via HTTP apos erro 400 (saldo intacto)', $creditsAfterHttp === $creditsBeforeHttp);

    // (b) Teste E2E de /admin?api=export_csv&scope=paid
    $cookieFile = $dir . '/admin_cookies.txt';
    $chGet = curl_init("http://127.0.0.1:{$port}/admin");
    curl_setopt_array($chGet, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Grid402-AuditTest/1.0',
        CURLOPT_TIMEOUT => 5,
    ]);
    $loginHtml = (string) curl_exec($chGet);
    curl_close($chGet);
    unset($chGet);

    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginHtml, $mCsrf);
    $csrf = $mCsrf[1] ?? '';
    t_assert('Token CSRF extraido da tela de login do /admin', $csrf !== '');

    $chLogin = curl_init("http://127.0.0.1:{$port}/admin");
    curl_setopt_array($chLogin, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => $adminUser,
            'password' => $adminPass,
            'csrf_token' => $csrf,
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Grid402-AuditTest/1.0',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 5,
    ]);
    $loginRespBody = (string) curl_exec($chLogin);
    $loginCode = (int) curl_getinfo($chLogin, CURLINFO_HTTP_CODE);
    curl_close($chLogin);
    unset($chLogin);
    if ($loginCode !== 302) {
        preg_match('/<div class="alert-error">(.*?)<\/div>/s', $loginRespBody, $mErr);
        echo "    [DEBUG] loginCode={$loginCode} err=" . ($mErr[1] ?? 'none') . " hasSetupForm=" . (str_contains($loginRespBody, 'Configurar Acesso') ? 'YES' : 'NO') . "\n";
    }
    t_assert('Login administrativo efetuado com sucesso (HTTP 302 redirect)', $loginCode === 302);

    $chCsv = curl_init("http://127.0.0.1:{$port}/admin?api=export_csv&scope=paid");
    curl_setopt_array($chCsv, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Grid402-AuditTest/1.0',
        CURLOPT_TIMEOUT => 5,
    ]);
    $csvBody = (string) curl_exec($chCsv);
    $csvCode = (int) curl_getinfo($chCsv, CURLINFO_HTTP_CODE);
    curl_close($chCsv);

    if ($csvCode !== 200 || !str_starts_with($csvBody, "\xEF\xBB\xBF")) {
        echo "    [DEBUG] csvCode={$csvCode} body=" . substr($csvBody, 0, 220) . "\n";
    }
    t_assert('CSV historico ?scope=paid retorna HTTP 200 com BOM UTF-8', $csvCode === 200 && str_starts_with($csvBody, "\xEF\xBB\xBF"));
    $csvLines = array_values(array_filter(explode("\n", trim(substr($csvBody, 3))), static fn($l) => trim($l) !== ''));
    $headerCols = str_getcsv($csvLines[0] ?? '', ';');
    t_assert('CSV historico mantem contrato de 12 colunas', count($headerCols) === 12);
    t_assert('CSV historico exporta exatamente as 3 transacoes pagas ignorando 130 eventos 402', count($csvLines) === 4);
    t_assert('CSV historico contem req_hist_1, req_hist_2 e req_hist_3', str_contains($csvBody, 'req_hist_1') && str_contains($csvBody, 'req_hist_2') && str_contains($csvBody, 'req_hist_3'));
} finally {
    grid402_stop_server($server);
}

t_rmdir($dir);
t_finish('RESULTADO FINAL (Auditoria Financeira — 7 Correcoes)');
