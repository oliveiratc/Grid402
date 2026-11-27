<?php
/**
 * Grid402 - Suíte de Testes da Change 1 (unblock-revenue-checkout-mcp-kyb)
 * Cobre todos os cenários das 6 delta specs (RF01 a RF11):
 * - x402 v1 resource absoluto e v2 accepted deepEqual PayAI
 * - Ocultação consistente de Solana quando GRID402_SOLANA_X402_ENABLED=false (+ reativação quando true)
 * - CORS completo em /api/v1/* e /mcp + aliases X-Payment-Signature / Payment
 * - Mercado Pago pacote micro (R$ 4,90 / 50 cr), remoção de pagador@grid402.online, UI sem autopix 250ms, CTA USD RapidAPI
 * - Persistência unificada pass_storage.js, Top-Up Web3 (copyOracleAddress único + 5 retries + txHash), Playground (Pass, 429, 4001)
 * - Navbar global (6 links com /dossie-kyb) e /dossie-kyb (amostra real + micro/starter + qr_mime)
 * - /mcp hospedado (negociação protocolVersion, auto-trial 1/dia mcp_sandbox, bloqueio 2ª chamada com MCPTRY50, negação em grid402_pass)
 */

declare(strict_types=1);

$passed = 0;
$failed = 0;

function g402_assert(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [OK] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

$root = dirname(__DIR__);
$publicDir = $root . '/public_html';
$tmpDir = sys_get_temp_dir() . '/grid402_change1_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0775, true);

// Isola armazenamento privado em diretorio temporario
putenv('GRID402_STORAGE_DIR=' . $tmpDir);
putenv('GRID402_SOLANA_X402_ENABLED=false');
putenv('GRID402_MP_SANDBOX=false');
putenv('GRID402_MP_ACCESS_TOKEN=');
putenv('GRID402_FACILITATOR_MODE=settle');
putenv('GRID402_DISABLE_TELEGRAM=1');

define('GRID402_EXEC', true);
require_once __DIR__ . '/helpers_server.php';
require_once $publicDir . '/config.php';
require_once $publicDir . '/services/multichain.php';
require_once $publicDir . '/facilitator.php';
require_once $publicDir . '/x402.php';
require_once $publicDir . '/services/mercadopago.php';
require_once $publicDir . '/includes/nav.php';

echo "=== 1. x402 v1 resource absoluto & v2 accepted deepEqual PayAI ===\n";

// 1a) x402 v1 com resource relativo deve sempre virar URL absoluta
$reqV1 = grid402_build_payment_requirements(1, 5000, '/api/v1/forex', 'eip155:8453');
g402_assert(
    str_starts_with((string) ($reqV1['resource'] ?? ''), 'https://grid402.online/api/v1/forex'),
    'x402 v1 normaliza resource relativo para https://grid402.online/api/v1/forex'
);

// 1b) x402 v2 normaliza accepted.amount (int -> string), accepted.network (alias -> CAIP-2) e extra.assetTransferMethod
$capturedFacilitatorBody = null;
$GLOBALS['grid402_facilitator_transport'] = static function (string $url, array $headers, string $body, int $timeout) use (&$capturedFacilitatorBody): array {
    $decodedBody = json_decode($body, true);
    if (str_contains($url, '/verify')) {
        $capturedFacilitatorBody = $decodedBody;
        return ['status' => 200, 'body' => (string) json_encode(['isValid' => true, 'payer' => '0x1111111111111111111111111111111111111111']), 'error' => null];
    }
    return ['status' => 200, 'body' => (string) json_encode(['success' => true, 'transaction' => '0x' . str_repeat('a', 64)]), 'error' => null];
};

$now = time();
$v2PayloadObj = [
    'x402Version' => 2,
    'accepted' => [
        'scheme' => 'exact',
        'network' => 'base', // alias nao canonico
        'amount' => 5000,    // inteiro JSON
        'asset' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        'payTo' => GRID402_WALLET_ADDRESS,
        'maxTimeoutSeconds' => 300,
        'extra' => [
            'name' => 'USD Coin',
            'version' => '2',
            'assetTransferMethod' => 'eip3009',
        ],
    ],
    'payload' => [
        'signature' => '0x' . str_repeat('b', 130),
        'authorization' => [
            'from' => '0x1111111111111111111111111111111111111111',
            'to' => GRID402_WALLET_ADDRESS,
            'value' => 5000,
            'validAfter' => $now - 60,
            'validBefore' => $now + 300,
            'nonce' => '0x' . bin2hex(random_bytes(32)),
        ],
    ],
];
$v2Proof = base64_encode((string) json_encode($v2PayloadObj));
$_SERVER['REQUEST_URI'] = '/api/v1/forex';
$v2Res = grid402_validate_payment($v2Proof, 5000, '127.0.0.1', 'forex', null, true);
g402_assert(!empty($v2Res['valid']), 'x402 v2 com amount int, network alias e extra.eip3009 passa verificacao (' . (string) ($v2Res['reason'] ?? 'ok') . ')');
g402_assert(is_array($capturedFacilitatorBody), 'Payload enviado ao facilitador capturado');
$sentAcc = $capturedFacilitatorBody['paymentPayload']['accepted'] ?? [];
$sentReq = $capturedFacilitatorBody['paymentRequirements'] ?? [];
g402_assert(($sentAcc['amount'] ?? null) === '5000' && ($sentReq['amount'] ?? null) === '5000', 'accepted.amount e paymentRequirements.amount ambos string "5000"');
g402_assert(($sentAcc['network'] ?? null) === 'eip155:8453' && ($sentReq['network'] ?? null) === 'eip155:8453', 'accepted.network e paymentRequirements.network ambos "eip155:8453"');
g402_assert(($sentAcc['extra']['assetTransferMethod'] ?? null) === 'eip3009' && ($sentReq['extra']['assetTransferMethod'] ?? null) === 'eip3009', 'extra.assetTransferMethod espelhado igualmente nos dois objetos');
g402_assert($sentAcc == $sentReq, 'paymentPayload.accepted coincide com paymentRequirements para deepEqual');
unset($GLOBALS['grid402_facilitator_transport']);

function g402_run_php_isolated(string $phpCode, array $envOverrides = []): string {
    $env = array_merge($_ENV, getenv() ?: [], $envOverrides);
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p = proc_open([PHP_BINARY], $spec, $pipes, null, $env);
    if (!is_resource($p)) {
        return '';
    }
    fwrite($pipes[0], "<?php\n" . $phpCode);
    fclose($pipes[0]);
    $out = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($p);
    return $out;
}

echo "\n=== 2. Ocultacao consistente de Solana quando desabilitada ===\n";
$acceptsOff = grid402_build_accepts(5000);
$acceptNetsOff = array_column($acceptsOff, 'network');
g402_assert(count($acceptNetsOff) === 3 && !in_array('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $acceptNetsOff, true), 'accepts[] com Solana desligada expoe exatamente 3 redes EVM');
$pubNetsOff = grid402_public_networks();
g402_assert(!isset($pubNetsOff['solana']) && count($pubNetsOff) === 5, 'grid402_public_networks() omite Solana e mantem 5 redes EVM');
$pubSummaryOff = grid402_supported_networks_public_summary();
g402_assert(!isset($pubSummaryOff['solana']), 'grid402_supported_networks_public_summary() omite Solana');

// Subprocesso PHP com GRID402_SOLANA_X402_ENABLED=true garante reativacao limpa pela flag
$phpBin = PHP_BINARY;
$solOnOut = trim(g402_run_php_isolated(
    'define("GRID402_EXEC", true); require ' . var_export($publicDir . '/x402.php', true) . '; echo count(grid402_build_accepts(5000)) . ":" . (isset(grid402_public_networks()["solana"]) ? "1" : "0");',
    ['GRID402_SOLANA_X402_ENABLED' => 'true', 'GRID402_STORAGE_DIR' => $tmpDir]
));
g402_assert($solOnOut === '4:1', 'Com GRID402_SOLANA_X402_ENABLED=true, accepts[] volta a expor 4 redes e public_networks inclui solana (' . $solOnOut . ')');

foreach (['pricing.json', 'openapi.json', 'llms.txt', 'llms-full.txt', '.well-known/ai-agent.json', '.well-known/ai-plugin.json', '.well-known/mcp.json', '.well-known/mcp/server-card.json'] as $manifestRel) {
    $mContent = (string) file_get_contents($publicDir . '/' . $manifestRel);
    g402_assert(stripos($mContent, 'solana') === false, "Manifesto {$manifestRel} nao anuncia Solana ativa");
}

echo "\n=== 3. Mercado Pago: pacote micro (R$ 4,90), email sem @grid402.online e UI ===\n";
$mpPkgs = grid402_mp_packages();
g402_assert(isset($mpPkgs['micro']) && (float) $mpPkgs['micro']['amount_brl'] === 4.9 && (int) $mpPkgs['micro']['credits'] === 50 && (int) $mpPkgs['micro']['raw_usdc_units'] === 250000 && (int) $mpPkgs['micro']['ttl_days'] === 90, 'grid402_mp_packages() inclui pacote micro R$ 4,90 / 50 creditos / 90d na escala v2_5000');

$capturedMpBodies = [];
$GLOBALS['grid402_mp_http_transport'] = static function (string $method, string $path, ?array $body, ?string $idem) use (&$capturedMpBodies): array {
    $capturedMpBodies[] = ['method' => $method, 'path' => $path, 'body' => $body];
    if ($path === '/v1/payments') {
        return [
            'status' => 201,
            'data' => [
                'id' => 'mp_test_' . bin2hex(random_bytes(3)),
                'date_of_expiration' => gmdate('Y-m-d\TH:i:s\Z', time() + 1800),
                'point_of_interaction' => ['transaction_data' => ['qr_code' => '000201PIXTEST', 'qr_code_base64' => base64_encode('PNGDATA')]],
            ],
            'error' => '',
        ];
    }
    return [
        'status' => 201,
        'data' => ['id' => 'pref_test_1', 'init_point' => 'https://mercadopago.com.br/checkout/test'],
        'error' => '',
    ];
};

$pixMicro = grid402_mp_create_pix('micro', 'compras@empresa.com.br');
g402_assert(!empty($pixMicro['ok']) && (float) $pixMicro['amount_brl'] === 4.9 && (int) $pixMicro['credits'] === 50 && ($pixMicro['qr_mime'] ?? '') === 'image/png', 'POST mp-pix para tier micro retorna R$ 4,90, 50 creditos e qr_mime');

$pixNoEmail = grid402_mp_create_pix('starter', '');
$sentEmail = (string) ($capturedMpBodies[1]['body']['payer']['email'] ?? '');
g402_assert(!empty($pixNoEmail['ok']) && $sentEmail !== '' && !str_contains(strtolower($sentEmail), '@grid402.online'), 'mp-pix sem e-mail nunca envia @grid402.online (' . $sentEmail . ')');

$cpMicro = grid402_mp_create_checkout_pro('micro', 'compras@empresa.com.br');
$sentPrice = (float) ($capturedMpBodies[2]['body']['items'][0]['unit_price'] ?? 0);
g402_assert(!empty($cpMicro['ok']) && $sentPrice === 4.9, 'mp-checkout para tier micro envia unit_price 4.9 BRL');
unset($GLOBALS['grid402_mp_http_transport']);

echo "\n=== 4. Navbar global (6 links) & paginas HTML SSR (/pass, /dossie-kyb, /, /payment-dashboard) ===\n";
ob_start();
grid402_render_public_nav(['active' => '/dossie-kyb', 'show_lang' => false]);
$navHtml = (string) ob_get_clean();
g402_assert(str_contains($navHtml, 'href="/dossie-kyb"') && str_contains($navHtml, 'aria-current="page"'), 'Navbar publica inclui /dossie-kyb com aria-current="page"');
g402_assert(preg_match_all('/class="g402-nav-link(?:\s|")/', $navHtml) === 6, 'Navbar publica renderiza exatamente 6 links principais');

// Renderiza pass.php sem token MP (sandbox=false) via subprocesso CLI limpo (isolado do .env local)
$passHtml = g402_run_php_isolated(
    '$GLOBALS["grid402_mp_sandbox_override"]=false; $GLOBALS["grid402_mp_token_override"]=""; $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/pass"; require ' . var_export($publicDir . '/pass.php', true) . ';',
    ['GRID402_STORAGE_DIR' => $tmpDir, 'GRID402_SOLANA_X402_ENABLED' => 'false']
);
g402_assert(str_contains($passHtml, 'data-tier-card="micro"') && str_contains($passHtml, 'R$ 4,90'), '/pass renderiza pacote Micro R$ 4,90');
g402_assert(str_contains($passHtml, 'mp-unavailable-banner'), '/pass exibe aviso quando GRID402_MP_ACCESS_TOKEN esta ausente em producao');
g402_assert(str_contains($passHtml, 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities'), '/pass exibe CTA Cartao USD via RapidAPI');
g402_assert(!str_contains($passHtml, 'data-chain="solana"') && !str_contains($passHtml, 'pass-sol-addr'), '/pass oculta seletor e carteira Solana quando flag desligada');
g402_assert(!str_contains($passHtml, "setTimeout(() => startMpPix"), '/pass nao dispara startMpPix() automaticamente em 250ms');
g402_assert(str_contains($passHtml, 'pass_storage.js'), '/pass carrega helper unificado pass_storage.js');

// Renderiza dossie_kyb.php
$kybHtml = g402_run_php_isolated(
    '$GLOBALS["grid402_mp_sandbox_override"]=false; $GLOBALS["grid402_mp_token_override"]=""; $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/dossie-kyb"; require ' . var_export($publicDir . '/dossie_kyb.php', true) . ';',
    ['GRID402_STORAGE_DIR' => $tmpDir]
);
g402_assert(str_contains($kybHtml, '<html lang="pt-BR">') && str_contains($kybHtml, 'btn-open-sample-dossier') && str_contains($kybHtml, 'SAMPLE_REAL_DOSSIER'), '/dossie-kyb renderiza em PT-BR com amostra real completa');
g402_assert(str_contains($kybHtml, 'btn-tier-micro') && str_contains($kybHtml, 'R$ 4,90') && str_contains($kybHtml, 'R$ 12,00'), '/dossie-kyb oferece Micro R$ 4,90 e Starter R$ 12,00');
g402_assert(str_contains($kybHtml, 'data.qr_mime') && str_contains($kybHtml, 'pass_storage.js') && str_contains($kybHtml, 'kyb-mp-unavailable'), '/dossie-kyb usa qr_mime dinamico, pass_storage.js e valida token MP');

// Verifica Home index.php (copyOracleAddress unico, btn-copy-oracle unico, retry 5x, Playground Pass + 429 + RapidAPI USD)
$homeHtml = g402_run_php_isolated(
    '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/"; $_SERVER["HTTP_ACCEPT"]="text/html"; require ' . var_export($publicDir . '/index.php', true) . ';',
    ['GRID402_STORAGE_DIR' => $tmpDir, 'GRID402_SOLANA_X402_ENABLED' => 'false']
);
g402_assert(substr_count($homeHtml, 'function copyOracleAddress(') === 1, 'Home contem exatamente 1 definicao de copyOracleAddress()');
g402_assert(substr_count($homeHtml, 'id="btn-copy-oracle"') === 1, 'Home contem exatamente 1 elemento com id="btn-copy-oracle"');
g402_assert(str_contains($homeHtml, 'copyWeb3ModalAddress()') && str_contains($homeHtml, 'retryDelaysMs = [2500, 3000, 4000, 5000, 6000]'), 'Home tem copyWeb3ModalAddress e 5 retries no startWeb3Topup');
g402_assert(str_contains($homeHtml, 'id="btn-mode-pass"') && str_contains($homeHtml, 'id="playground-rate-limit-card"') && str_contains($homeHtml, 'MCPTRY50'), 'Playground inclui modo Session Pass e card dedicado HTTP 429 com MCPTRY50');
g402_assert(str_contains($homeHtml, 'topup-rapidapi-usd-box') && !str_contains($homeHtml, 'data-chain="solana"'), 'Home exibe CTA RapidAPI USD no Top-Up e oculta Solana');
g402_assert(str_contains($homeHtml, 'err.code === 4001') && str_contains($homeHtml, 'pass_storage.js'), 'Home diferencia erro 4001 de falha tecnica e carrega pass_storage.js');

// Verifica payment_dashboard.php lendo cookie g402_pass_token
$dashHtml = g402_run_php_isolated(
    '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/payment-dashboard"; $_COOKIE["g402_pass_token"]="pass_g402_abcd1234_0123456789abcdef"; require ' . var_export($publicDir . '/payment_dashboard.php', true) . ';',
    ['GRID402_STORAGE_DIR' => $tmpDir]
);
g402_assert(str_contains($dashHtml, 'pass_storage.js') && str_contains($dashHtml, 'pass_g402_abcd1234_0123456789abcdef'), '/payment-dashboard integra pass_storage.js e restaura token do cookie');

echo "\n=== 5. Testes HTTP ao vivo em servidor PHP embutido (CORS, 429 MCPTRY50, /mcp) ===\n";
$server = grid402_start_server([
    'docroot' => $publicDir,
    'router' => __DIR__ . '/router.php',
    'probe' => '/status',
    'env' => [
        'GRID402_STORAGE_DIR' => $tmpDir,
        'GRID402_FILE_SANDBOX_RATES' => $tmpDir . '/sandbox_rates_change1.json',
        'GRID402_SOLANA_X402_ENABLED' => 'false',
        'GRID402_SANDBOX_RATE_LIMIT' => '1',
        'GRID402_DISABLE_TELEGRAM' => '1',
    ],
]);
$ready = true;
$port = $server['port'];
g402_assert($ready, "Servidor PHP local iniciado e pronto na porta {$port}");

function g402_http(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$respHeaders): int {
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $k = strtolower(trim(substr($line, 0, $pos)));
                $respHeaders[$k] = trim(substr($line, $pos + 1));
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'headers' => $respHeaders, 'body' => $raw, 'json' => json_decode($raw, true)];
}

if ($ready) {
    $base = "http://127.0.0.1:{$port}";

    // 5a) Preflight OPTIONS em /index.php?api=forex
    $optApi = g402_http('OPTIONS', "{$base}/index.php?api=forex");
    $allowApi = strtolower((string) ($optApi['headers']['access-control-allow-headers'] ?? ''));
    g402_assert(
        $optApi['status'] === 204 && str_contains($allowApi, 'x-payment-signature') && str_contains($allowApi, 'payment') && str_contains($allowApi, 'x-grid402-channel'),
        'OPTIONS /api/v1/* retorna 204 com X-Payment-Signature, Payment e X-Grid402-Channel'
    );

    // 5b) Sandbox 1a chamada OK -> 2a chamada 429 promovendo MCPTRY50 sem Solana
    $sb1 = g402_http('GET', "{$base}/index.php?api=holidays&year=2026", ['X-Payment: demo']);
    $sb2 = g402_http('GET', "{$base}/index.php?api=holidays&year=2026", ['X-Payment: demo']);
    g402_assert($sb1['status'] === 200 && $sb2['status'] === 429, 'Cota sandbox (1/dia) retorna 200 na 1a e 429 na 2a chamada');
    $j429 = is_array($sb2['json']) ? $sb2['json'] : [];
    $chains429 = $j429['upgrade_to_production']['supported_chains'] ?? [];
    g402_assert(
        ($j429['promo_code'] ?? '') === 'MCPTRY50'
        && str_contains((string) ($j429['promo_url'] ?? ''), '/pass?promo=MCPTRY50')
        && !empty($j429['upgrade_to_production']['rapidapi_credit_card_usd']['all_25_apis_url'])
        && !in_array('solana', $chains429, true),
        'Resposta HTTP 429 promove MCPTRY50, RapidAPI USD e omite Solana'
    );

    // 5c) Preflight OPTIONS em /mcp.php
    $optMcp = g402_http('OPTIONS', "{$base}/mcp.php");
    $allowMcp = strtolower((string) ($optMcp['headers']['access-control-allow-headers'] ?? ''));
    g402_assert(
        $optMcp['status'] === 204 && str_contains($allowMcp, 'x-payment-signature') && str_contains($allowMcp, 'mcp-session-id') && str_contains($allowMcp, 'mcp-protocol-version'),
        'OPTIONS /mcp retorna 204 com X-Payment-Signature, Payment, Mcp-Session-Id e MCP-Protocol-Version'
    );

    // 5d) /mcp initialize negociando protocolVersion 2025-03-26
    $initMcp = g402_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2025-03-26', 'clientInfo' => ['name' => 'test', 'version' => '1.0']],
    ]));
    g402_assert(
        ($initMcp['json']['result']['protocolVersion'] ?? '') === '2025-03-26'
        && ($initMcp['json']['result']['serverInfo']['version'] ?? '') === '2.1.0',
        '/mcp initialize negocia protocolVersion 2025-03-26 mantendo serverInfo 2.1.0'
    );

    // 5e) /mcp grid402_pass nunca recebe auto-trial (mesmo antes de usar trial de dados!)
    $mcpPass = g402_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_pass', 'arguments' => []],
    ]));
    g402_assert(!empty($mcpPass['json']['result']['isError']), '/mcp nunca libera auto-trial para grid402_pass');

    // 5f) /mcp 1a chamada sem credencial em grid402_holidays recebe auto-trial 1/dia com _trial_notice
    $mcpTrial1 = g402_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
    ]));
    $t1Text = (string) ($mcpTrial1['json']['result']['content'][0]['text'] ?? '');
    $t1Payload = json_decode($t1Text, true);
    g402_assert(
        empty($mcpTrial1['json']['result']['isError'])
        && !empty($t1Payload['sandbox'])
        && ($t1Payload['_trial_notice']['mode'] ?? '') === 'mcp_sandbox'
        && ($t1Payload['_trial_notice']['promo_code'] ?? '') === 'MCPTRY50'
        && !empty($t1Payload['_oracle_proof']),
        '/mcp concede 1 auto-trial diario (mcp_sandbox) com _trial_notice MCPTRY50 e _oracle_proof'
    );

    // 5g) /mcp 2a chamada sem credencial no mesmo dia bloqueia promovendo MCPTRY50 sem Solana
    $mcpTrial2 = g402_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
    ]));
    $t2Text = (string) ($mcpTrial2['json']['result']['content'][0]['text'] ?? '');
    $t2Accepts = $mcpTrial2['json']['result']['_meta']['x402/error']['accepts'] ?? [];
    $t2Nets = array_column($t2Accepts, 'network');
    g402_assert(
        !empty($mcpTrial2['json']['result']['isError'])
        && str_contains($t2Text, 'MCPTRY50')
        && str_contains($t2Text, '/pass?promo=MCPTRY50')
        && str_contains($t2Text, 'rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities')
        && !in_array('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $t2Nets, true),
        '/mcp 2a chamada sem prova promove MCPTRY50 e RapidAPI USD em ingles sem Solana'
    );

    // 5h) Alias de header X-Payment-Signature reconhecido no /mcp (prova invalida gera motivo de validacao, nao challenge vazio)
    $mcpAlias = g402_http('POST', "{$base}/mcp.php", ['Content-Type: application/json', 'X-Payment-Signature: invalid_blob_123'], json_encode([
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
    ]));
    $aliasErr = (string) ($mcpAlias['json']['result']['_meta']['x402/error']['error'] ?? '');
    g402_assert(
        !empty($mcpAlias['json']['result']['isError']) && !str_contains($aliasErr, 'Daily free MCP auto-trial'),
        '/mcp le alias X-Payment-Signature e o encaminha ao validador de pagamento'
    );
}

if (isset($server) && is_array($server)) {
    grid402_stop_server($server);
}

// Limpeza temporaria
foreach (glob($tmpDir . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmpDir);

echo "\nResumo Change 1: {$passed} passaram, {$failed} falharam.\n";
exit($failed > 0 ? 1 : 0);
