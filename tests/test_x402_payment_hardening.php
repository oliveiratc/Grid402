<?php
/**
 * Suite de Regressao: Hardening do Fluxo de Pagamento x402 (Grid402)
 *
 * Cobre:
 * 1. .well-known/x402.json: 24 recursos x 4 accepts[] com maxTimeoutSeconds === 300
 * 2. facilitator.php: backfill de accepted.maxTimeoutSeconds, clock skew (+10s vs +600s),
 *    verify -> abort (libera nonce sem chamar /settle), verify -> settle (grava settlement_tx
 *    em used_txs.json bloqueando replay como X-Payment: 0x...)
 * 3. multichain.php: validacao real do JSON-RPC getTransaction da Solana (slot/meta/transaction)
 * 4. pass_repository.php: security gate real (cli-server) aceita solana:<sig> e arbitrum:0x...
 *    em used_txs.json e rejeita hash ausente sem vazar lock
 * 5. HTTP E2E via router.php: 402 de falha emite PAYMENT-REQUIRED + resource + accepts,
 *    CORS OPTIONS libera X-Payment-Network e X-Session-Pass e expoe PAYMENT-REQUIRED/PAYMENT-RESPONSE,
 *    falha de servico pos-porteiro nao grava status: 200 no JSONL
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$dir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/services/multichain.php';
require_once __DIR__ . '/../public_html/services/pass_repository.php';

echo "=== SUITE DE REGRESSAO: HARDENING x402 ===\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. .well-known/x402.json com maxTimeoutSeconds === 300
    // -------------------------------------------------------------------------
    echo "[1] Validando .well-known/x402.json:\n";
    $x402Path = __DIR__ . '/../public_html/.well-known/x402.json';
    $x402Data = json_decode((string) file_get_contents($x402Path), true);
    $resources = is_array($x402Data['resources'] ?? null) ? $x402Data['resources'] : [];
    t_assert('x402.json contem 27 recursos', count($resources) === 27, 'Contagem: ' . count($resources));

    $acceptCount = 0;
    $allHaveTimeout = true;
    foreach ($resources as $resItem) {
        $accepts = is_array($resItem['accepts'] ?? null) ? $resItem['accepts'] : [];
        if (count($accepts) !== 3) {
            $allHaveTimeout = false;
        }
        foreach ($accepts as $acc) {
            $acceptCount++;
            if (($acc['maxTimeoutSeconds'] ?? null) !== 300) {
                $allHaveTimeout = false;
            }
        }
    }
    t_assert('Todos os 81 accepts[] EVM (27 recursos x 3 redes default V-05) em x402.json possuem maxTimeoutSeconds === 300', $acceptCount === 81 && $allHaveTimeout, "Accepts={$acceptCount}");

    // Verifica que com GRID402_SOLANA_X402_ENABLED=true, grid402_build_accepts emite 4 redes (incluindo Solana) com maxTimeoutSeconds === 300
    $solFlagCmd = sprintf(
        '%s -r %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg("putenv('GRID402_SOLANA_X402_ENABLED=true'); define('GRID402_EXEC', true); require '" . str_replace('\\', '/', __DIR__ . '/../public_html/config.php') . "'; require '" . str_replace('\\', '/', __DIR__ . '/../public_html/x402.php') . "'; echo json_encode(grid402_build_accepts(5000));")
    );
    $solFlagAccepts = json_decode((string) shell_exec($solFlagCmd), true);
    $solFlagOk = is_array($solFlagAccepts) && count($solFlagAccepts) === 4 && ($solFlagAccepts[3]['maxTimeoutSeconds'] ?? 0) === 300 && str_starts_with((string) ($solFlagAccepts[3]['network'] ?? ''), 'solana:');
    t_assert('Com GRID402_SOLANA_X402_ENABLED=true, grid402_build_accepts emite 4 redes com maxTimeoutSeconds === 300', $solFlagOk, json_encode($solFlagAccepts));

    // -------------------------------------------------------------------------
    // 2. facilitator.php: backfill maxTimeoutSeconds, clock skew, verify/abort/settle + anti-replay
    // -------------------------------------------------------------------------
    echo "\n[2] Validando facilitator.php (backfill, clock skew, abort, settle + used_txs):\n";

    $calls = [];
    $settleTxHash = '0x' . str_repeat('cd', 32);
    $handlers = [
        '/v2/x402/verify' => ['status' => 200, 'body' => ['isValid' => true]],
        '/v2/x402/settle' => ['status' => 200, 'body' => ['success' => true, 'transaction' => $settleTxHash, 'network' => 'eip155:8453']],
    ];
    t_install_transport($handlers, $calls);

    // 2.1 Backfill de accepted.maxTimeoutSeconds quando ausente no payload cliente
    $proofMissingTimeout = t_payload(['drop' => ['accepted.maxTimeoutSeconds']]);
    $verifiedBackfill = grid402_verify_exact_payment($proofMissingTimeout, 500, '/api/v1/forex');
    t_assert('Verify aceita payload v2 sem maxTimeoutSeconds', !empty($verifiedBackfill['valid']) && !empty($verifiedBackfill['needs_settle']));
    $sentTimeout = $calls[0]['body']['paymentPayload']['accepted']['maxTimeoutSeconds'] ?? null;
    t_assert('accepted.maxTimeoutSeconds preenchido automaticamente para 300 no verify', $sentTimeout === 300, 'Valor: ' . json_encode($sentTimeout));

    // 2.2 Verify -> Abort libera nonce sem chamar /settle
    t_assert('Verify chamou apenas /verify ate aqui (1 chamada)', count($calls) === 1);
    grid402_abort_verified_exact_payment($verifiedBackfill);
    t_assert('Abort nao chamou /settle', count($calls) === 1);

    // O mesmo payload deve poder ser verificado novamente apos abort
    $verifiedAgain = grid402_verify_exact_payment($proofMissingTimeout, 500, '/api/v1/forex');
    t_assert('Apos abort, mesmo nonce pode ser verificado novamente', !empty($verifiedAgain['valid']));

    // 2.3 Verify -> Settle grava settlement_tx em used_txs.json e bloqueia replay como X-Payment: 0x...
    $settled = grid402_settle_exact_payment($verifiedAgain);
    t_assert('Settle conclui pagamento e retorna settlement_tx', !empty($settled['valid']) && ($settled['settlement_tx'] ?? '') === $settleTxHash);
    $usedTxsAfterSettle = grid402_get_used_transactions();
    t_assert('settlement_tx gravado em used_txs.json apos settle', isset($usedTxsAfterSettle[$settleTxHash]));
    $replayAsTx = grid402_validate_payment($settleTxHash, 500);
    t_assert('Reuso do settlement_tx via X-Payment: 0x... bloqueado com 403', empty($replayAsTx['valid']) && ($replayAsTx['code'] ?? 0) === 403);

    // 2.4 Tolerancia de clock skew (validAfter +10s aceito, +600s rejeitado)
    $nowFixed = 1800000000;
    $skewOkDecoded = grid402_decode_x402_payload(t_payload([
        'auth' => ['validAfter' => (string) ($nowFixed + 10), 'validBefore' => (string) ($nowFixed + 300)],
    ]));
    $skewOk = grid402_validate_exact_requirements($skewOkDecoded, 500, $nowFixed);
    t_assert('Clock skew validAfter +10s e aceito (tolerancia 30s)', !empty($skewOk['valid']));

    $skewFailDecoded = grid402_decode_x402_payload(t_payload([
        'auth' => ['validAfter' => (string) ($nowFixed + 600), 'validBefore' => (string) ($nowFixed + 900)],
    ]));
    $skewFail = grid402_validate_exact_requirements($skewFailDecoded, 500, $nowFixed);
    t_assert('validAfter +600s continua rejeitado como authorization_expired', empty($skewFail['valid']) && ($skewFail['category'] ?? '') === 'authorization_expired');

    // -------------------------------------------------------------------------
    // 3. multichain.php: Solana getTransaction
    // -------------------------------------------------------------------------
    echo "\n[3] Validando parser RPC getTransaction da Solana:\n";
    $validSolJson = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'slot' => 310000123,
            'meta' => ['err' => null, 'preTokenBalances' => [], 'postTokenBalances' => []],
            'transaction' => ['signatures' => ['5abc'], 'message' => []],
        ],
    ]);
    $parsedSolOk = grid402_extract_solana_tx_result($validSolJson, 200);
    t_assert('Resposta real Solana (slot + meta + transaction) e aceita', is_array($parsedSolOk) && ($parsedSolOk['slot'] ?? 0) === 310000123);
    t_assert('Resposta null do RPC Solana e rejeitada', grid402_extract_solana_tx_result('{"jsonrpc":"2.0","id":1,"result":null}', 200) === null);
    t_assert('Resposta vazia [] do RPC Solana e rejeitada', grid402_extract_solana_tx_result('{"jsonrpc":"2.0","id":1,"result":[]}', 200) === null);
    t_assert('Resposta HTTP != 200 do RPC Solana e rejeitada', grid402_extract_solana_tx_result($validSolJson, 502) === null);

    // -------------------------------------------------------------------------
    // 4. pass_repository.php: Security gate real (cli-server sem tmp/temp no path)
    // -------------------------------------------------------------------------
    echo "\n[4] Validando security gate de pass_repository.php em modo nao-CLI:\n";
    $secStorageDir = __DIR__ . '/.secgate_storage_' . bin2hex(random_bytes(4));
    $secDocRoot = __DIR__ . '/.secgate_docroot_' . bin2hex(random_bytes(4));
    mkdir($secStorageDir, 0775, true);
    mkdir($secDocRoot, 0775, true);

    $solSigCase = '5Jq8Z4kLpM9nR2vTxW7yB3cF6hN1qP4sU8wY2zA5bD9eG3jK7mP2rT6vX9yC4fH8kL1nQ5sV7wZ3bE6';
    $arbHash = '0x' . bin2hex(random_bytes(32));
    $missingHash = '0x' . bin2hex(random_bytes(32));

    $secUsedTxs = [
        strtolower('solana:' . $solSigCase) => time(),
        strtolower('arbitrum:' . $arbHash) => time(),
    ];
    file_put_contents($secStorageDir . '/used_txs.json', json_encode($secUsedTxs));

    $secRouter = $secDocRoot . '/index.php';
    file_put_contents($secRouter, <<<'PHP'
<?php
declare(strict_types=1);
define('GRID402_EXEC', true);
require_once __DIR__ . '/../../public_html/config.php';
require_once __DIR__ . '/../../public_html/services/pass.php';
require_once __DIR__ . '/../../public_html/services/pass_repository.php';
header('Content-Type: application/json; charset=utf-8');
$repo = new JsonFlockPassRepository();
$tx = (string) ($_GET['tx'] ?? '');
$units = (int) ($_GET['units'] ?? 2000000);
try {
    $res = $repo->issuePass($tx, $units, false);
    echo json_encode(['ok' => true, 'pass' => $res]);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
PHP
    );

    $secServer = grid402_start_server([
        'docroot' => $secDocRoot,
        'router' => $secRouter,
        'probe' => '/?tx=probe',
        'env' => ['GRID402_STORAGE_DIR' => $secStorageDir],
    ]);

    try {
        $reqGate = static function (string $url): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $body = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['code' => $code, 'json' => json_decode($body, true)];
        };

        $rMissing = $reqGate($secServer['base_url'] . '/?tx=' . urlencode($missingHash));
        t_assert('Security gate rejeita hash nao verificado em used_txs.json', $rMissing['code'] === 403 && empty($rMissing['json']['ok']));

        // Confirma que o lock de session_passes.json nao vazou apos a excecao: uma chamada valida logo em seguida funciona
        $rArbOk = $reqGate($secServer['base_url'] . '/?tx=' . urlencode('arbitrum:' . $arbHash));
        t_assert('Security gate aceita arbitrum:0x... presente em used_txs.json sem lock preso', $rArbOk['code'] === 200 && !empty($rArbOk['json']['pass']['pass_token']));

        $rSolOk = $reqGate($secServer['base_url'] . '/?tx=' . urlencode('solana:' . $solSigCase));
        t_assert('Security gate aceita solana:<sig> (com maiusculas/L) presente em used_txs.json', $rSolOk['code'] === 200 && !empty($rSolOk['json']['pass']['pass_token']));
    } finally {
        grid402_stop_server($secServer);
        t_rmdir($secStorageDir);
        t_rmdir($secDocRoot);
    }

    // -------------------------------------------------------------------------
    // 5. HTTP E2E: 402 PAYMENT-REQUIRED em falhas, CORS OPTIONS, log status real
    // -------------------------------------------------------------------------
    echo "\n[5] Validando HTTP E2E (PAYMENT-REQUIRED em 402 de falha, CORS e log JSONL):\n";
    $e2eStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_e2e_hard_' . bin2hex(random_bytes(4));
    mkdir($e2eStorage, 0775, true);
    $e2eLogs = $e2eStorage . DIRECTORY_SEPARATOR . 'requests.jsonl';
    $e2eSandbox = $e2eStorage . DIRECTORY_SEPARATOR . 'sandbox_rates.json';

    $e2eServer = grid402_start_server([
        'docroot' => __DIR__ . '/../public_html',
        'router' => __DIR__ . '/router.php',
        'probe' => '/status',
        'env' => [
            'GRID402_STORAGE_DIR' => $e2eStorage,
            'GRID402_FILE_REQUEST_LOGS' => $e2eLogs,
            'GRID402_FILE_SANDBOX_RATES' => $e2eSandbox,
            'GRID402_DISABLE_TELEGRAM' => '1',
        ],
    ]);

    try {
        $httpRaw = static function (string $method, string $url, array $headers = []): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 8,
            ]);
            $raw = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $hSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            return [
                'code' => $code,
                'headers' => substr($raw, 0, $hSize),
                'body' => substr($raw, $hSize),
            ];
        };

        // 5.1 Prova expirada retorna HTTP 402 com cabecalho PAYMENT-REQUIRED + resource + accepts
        $expiredProof = t_payload(['auth' => ['validBefore' => (string) (time() - 60)]]);
        $rExp = $httpRaw('GET', $e2eServer['base_url'] . '/api/v1/forex', [
            'Accept: application/json',
            'PAYMENT-SIGNATURE: ' . $expiredProof,
        ]);
        $jExp = json_decode($rExp['body'], true);
        t_assert('Autorizacao expirada retorna HTTP 402 com resource e accepts no body', $rExp['code'] === 402 && isset($jExp['resource'], $jExp['accepts']));
        $hasPrHdr = preg_match('/^PAYMENT-REQUIRED:\s*(\S+)/im', $rExp['headers'], $prMatch) === 1;
        t_assert('HTTP 402 de falha inclui cabecalho PAYMENT-REQUIRED', $hasPrHdr);
        if ($hasPrHdr) {
            $prDecoded = json_decode((string) base64_decode(trim($prMatch[1])), true);
            t_assert('PAYMENT-REQUIRED decodificado contem x402Version=2 e accepts com maxTimeoutSeconds=300',
                ($prDecoded['x402Version'] ?? 0) === 2 && ($prDecoded['accepts'][0]['maxTimeoutSeconds'] ?? 0) === 300
            );
        }

        // 5.2 CORS preflight OPTIONS
        $rOpt = $httpRaw('OPTIONS', $e2eServer['base_url'] . '/api/v1/forex', [
            'Origin: https://example.com',
            'Access-Control-Request-Method: GET',
            'Access-Control-Request-Headers: X-Payment-Network, X-Session-Pass, PAYMENT-SIGNATURE',
        ]);
        t_assert('OPTIONS retorna HTTP 204', $rOpt['code'] === 204);
        t_assert('Access-Control-Allow-Headers inclui X-Payment-Network e X-Session-Pass',
            stripos($rOpt['headers'], 'X-Payment-Network') !== false &&
            stripos($rOpt['headers'], 'X-Session-Pass') !== false
        );
        t_assert('Access-Control-Expose-Headers inclui PAYMENT-REQUIRED e PAYMENT-RESPONSE',
            stripos($rOpt['headers'], 'PAYMENT-REQUIRED') !== false &&
            stripos($rOpt['headers'], 'PAYMENT-RESPONSE') !== false
        );

        // 5.3 Falha de servico pos-porteiro nao grava status: 200 no JSONL
        $rFailSvc = $httpRaw('GET', $e2eServer['base_url'] . '/api/v1/workdays?start_date=data_invalida', [
            'Accept: application/json',
            'X-Payment: demo',
        ]);
        t_assert('Falha de servico retorna HTTP 400 real', $rFailSvc['code'] === 400);
        t_assert('Falha de servico nao emite X-Payment-Status: Accepted', stripos($rFailSvc['headers'], 'X-Payment-Status: Accepted') === false);

        $logLines = file($e2eLogs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $workdaysLog = null;
        foreach ($logLines as $ln) {
            $entry = json_decode($ln, true);
            if (is_array($entry) && ($entry['service'] ?? '') === 'workdays') {
                $workdaysLog = $entry;
            }
        }
        t_assert('Log JSONL registra status 400 real (nunca 200) para falha de servico',
            is_array($workdaysLog) && ($workdaysLog['status'] ?? 0) === 400
        );

        // [6] Parsing tolerante de headers/payloads, /pass #multichain-web3 e ?cep=
        echo "\n[6] Validando parsing tolerante x402/headers, /pass e ?cep=:\n";
        t_assert('Normaliza prefixo x402 em prova', grid402_normalize_payment_proof_header('x402 0x' . str_repeat('a', 64)) === '0x' . str_repeat('a', 64));
        t_assert('Normaliza prefixo Payment em prova', grid402_normalize_payment_proof_header('Payment pass_g402_abc') === 'pass_g402_abc');
        t_assert('Normaliza prefixo Bearer em prova', grid402_normalize_payment_proof_header('Bearer pass_g402_abc') === 'pass_g402_abc');

        $b64Sample = t_payload(['auth' => ['value' => '5000']]);
        $rawJsonSample = (string) base64_decode($b64Sample);
        t_assert('JSON cru em blob e decodificado', is_array(grid402_try_decode_x402_blob($rawJsonSample)));
        $decRawJson = grid402_decode_x402_payload('Bearer ' . $rawJsonSample);
        t_assert('JSON cru com prefixo Bearer e aceito em decode_x402_payload', ($decRawJson['ok'] ?? false) === true && ($decRawJson['version'] ?? 0) === 2);

        $wrappedB64 = wordwrap($b64Sample, 32, "\r\n ", true);
        t_assert('Base64 com quebras MIME e espacos e decodificado', is_array(grid402_try_decode_x402_blob($wrappedB64)));

        $uint256Max = '115792089237316195423570985008687907853269984665640564039457584007913129639935';
        $sigNo0xArr = [
            'x402Version' => 2,
            'accepted' => [
                'scheme' => 'exact',
                'network' => 'eip155:8453',
                'asset' => GRID402_USDC_CONTRACT,
                'amount' => '5000',
                'payTo' => GRID402_WALLET_ADDRESS,
            ],
            'payload' => [
                'signature' => str_repeat('ab', 65),
                'authorization' => [
                    'from' => '0x' . str_repeat('11', 20),
                    'to' => GRID402_WALLET_ADDRESS,
                    'value' => '5000',
                    'validAfter' => '0',
                    'validBefore' => $uint256Max,
                    'nonce' => str_repeat('cd', 32),
                ],
            ],
        ];
        $decFlex = grid402_decode_x402_payload((string) json_encode($sigNo0xArr));
        t_assert('Signature/nonce sem 0x e validBefore uint256.max sao normalizados',
            ($decFlex['ok'] ?? false) === true
            && str_starts_with((string) ($decFlex['signature'] ?? ''), '0x')
            && str_starts_with((string) ($decFlex['authorization']['nonce'] ?? ''), '0x')
        );
        if (!empty($decFlex['ok'])) {
            $reqFlex = grid402_validate_exact_requirements($decFlex, 5000);
            t_assert('validBefore uint256.max passa na validacao local sem overflow', ($reqFlex['valid'] ?? false) === true);
        } else {
            t_assert('validBefore uint256.max passa na validacao local sem overflow', false);
        }

        // /pass deve apontar para #multichain-web3 (nao para /#topup)
        $rPassHtml = $httpRaw('GET', $e2eServer['base_url'] . '/pass');
        t_assert('/pass contem href="#multichain-web3" e nao contem /#topup',
            $rPassHtml['code'] === 200
            && str_contains($rPassHtml['body'], 'href="#multichain-web3"')
            && !str_contains($rPassHtml['body'], '/#topup')
        );

        // /api/v1/cep deve respeitar ?cep= alem de ?code=
        $rCepParam = $httpRaw('GET', $e2eServer['base_url'] . '/api/v1/cep?cep=01001000', [
            'Accept: application/json',
            'X-Payment: demo',
        ]);
        t_assert('/api/v1/cep?cep=01001000 usa o CEP informado (nunca o default 01310)',
            str_contains($rCepParam['body'], '01001') && !str_contains($rCepParam['body'], '01310')
        );

        // Authorization: Payment demo aceito no HTTP gateway
        $rAuthHdr = $httpRaw('GET', $e2eServer['base_url'] . '/api/v1/forex', [
            'Accept: application/json',
            'Authorization: Payment demo',
        ]);
        t_assert('Authorization: Payment demo e aceito no gateway HTTP', $rAuthHdr['code'] === 200);
    } finally {
        grid402_stop_server($e2eServer);
        t_rmdir($e2eStorage);
    }
} finally {
    t_rmdir($dir);
}

t_finish('test_x402_payment_hardening');
