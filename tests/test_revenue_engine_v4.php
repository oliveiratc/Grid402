<?php
/**
 * Grid402 // Suíte Dedicada de Testes: Revenue Engine v4 (add-revenue-engine-v4)
 *
 * Valida todos os cenários das 5 delta specs:
 * 1. Multi-Chain x402 accepts[] (4 redes PayAI em mainnet vs. 1 em base-sepolia)
 * 2. Facilitador exact para Polygon, Arbitrum (EIP-3009) e Solana SVM (payload.transaction + anti-replay)
 * 3. /api/v1/extract (SSRF block, 402 multi-chain teaser, extração HTML -> Markdown com mock)
 * 4. /api/v1/email-verify (400 pré-cobrança, MX/SPF/DMARC VALID, disposable RISKY, estrito Zero-PII)
 * 5. Sandbox rate-limit 3 req/dia (3 permitidas, 4ª retorna HTTP 429 + Retry-After + upgrade_to_production)
 * 6. Hosted MCP POST /mcp (version 2.0.0 e 27 tools incluindo grid402_extract e grid402_email_verify)
 * 7. Funil Web Humano SSR (/dossie-kyb, /api-cnpj, /api-sancoes-cgu, /api-dias-uteis-anbima, Home Hero, sitemap.xml e docs/revenue/)
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$storageDir = t_bootstrap('settle');

require_once __DIR__ . '/../public_html/services/extract.php';
require_once __DIR__ . '/../public_html/services/email_verify.php';

echo "=================================================================\n";
echo "  GRID402 // SUÍTE DE TESTES: REVENUE ENGINE V4\n";
echo "=================================================================\n\n";

// -----------------------------------------------------------------
// 1. Multi-Chain accepts[] (Mainnet 3 EVM default V-05, 4 com flag Solana vs. Sepolia 1 rede)
// -----------------------------------------------------------------
echo "[1/7] Multi-Chain x402 accepts[] (Base, Polygon, Arbitrum default V-05; Solana via flag vs. Sepolia):\n";
$acceptsMainnet = grid402_build_accepts(5000);
t_assert('Mainnet emite exatamente 3 ofertas EVM exact por padrão (V-05 Solana opt-in)', count($acceptsMainnet) === 3, 'Got: ' . count($acceptsMainnet));
$nets = array_column($acceptsMainnet, 'network');
t_assert('Base (eip155:8453) é o primeiro item', ($nets[0] ?? '') === 'eip155:8453');
t_assert('Polygon (eip155:137) presente com contrato oficial',
    in_array('eip155:137', $nets, true) &&
    strtolower((string) ($acceptsMainnet[1]['asset'] ?? '')) === strtolower('0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359')
);
t_assert('Arbitrum (eip155:42161) presente com contrato oficial',
    in_array('eip155:42161', $nets, true) &&
    strtolower((string) ($acceptsMainnet[2]['asset'] ?? '')) === strtolower('0xaf88d065e77c8cC2239327C5EDb3A432268e5831')
);
t_assert('Solana NÃO anunciada quando GRID402_SOLANA_X402_ENABLED=false (V-05)',
    !in_array('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $nets, true)
);

// Subprocesso isolado para validar GRID402_SOLANA_X402_ENABLED=true (4 redes incluindo Solana)
$solEnabledCmd = sprintf(
    '%s -r %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg("putenv('GRID402_SOLANA_X402_ENABLED=true'); define('GRID402_EXEC', true); require '" . str_replace('\\', '/', __DIR__ . '/../public_html/config.php') . "'; require '" . str_replace('\\', '/', __DIR__ . '/../public_html/x402.php') . "'; echo json_encode(grid402_build_accepts(5000));")
);
$solEnabledOut = (string) shell_exec($solEnabledCmd);
$solEnabledAccepts = json_decode($solEnabledOut, true);
t_assert('Com GRID402_SOLANA_X402_ENABLED=true, Mainnet emite 4 ofertas incluindo Solana SPL USDC, payTo Solana e feePayer',
    is_array($solEnabledAccepts) &&
    count($solEnabledAccepts) === 4 &&
    ($solEnabledAccepts[3]['network'] ?? '') === 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp' &&
    ($solEnabledAccepts[3]['asset'] ?? '') === 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v' &&
    ($solEnabledAccepts[3]['payTo'] ?? '') === GRID402_SOLANA_WALLET_ADDRESS &&
    !empty($solEnabledAccepts[3]['extra']['feePayer']),
    $solEnabledOut
);

// Subprocesso isolado para validar GRID402_X402_NETWORK=base-sepolia
$sepoliaCmd = sprintf(
    '%s -r %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg("putenv('GRID402_X402_NETWORK=base-sepolia'); define('GRID402_EXEC', true); require '" . str_replace('\\', '/', __DIR__ . '/../public_html/config.php') . "'; require '" . str_replace('\\', '/', __DIR__ . '/../public_html/x402.php') . "'; echo json_encode(grid402_build_accepts(5000));")
);
$sepoliaOut = (string) shell_exec($sepoliaCmd);
$sepoliaAccepts = json_decode($sepoliaOut, true);
t_assert('Modo base-sepolia preserva oferta única eip155:84532',
    is_array($sepoliaAccepts) && count($sepoliaAccepts) === 1 && ($sepoliaAccepts[0]['network'] ?? '') === 'eip155:84532',
    $sepoliaOut
);

// -----------------------------------------------------------------
// 2. Facilitador Multi-Chain (Polygon, Arbitrum EIP-3009 e Solana SVM)
// -----------------------------------------------------------------
echo "\n[2/7] Facilitador Multi-Chain (Polygon, Arbitrum EIP-3009 e Solana SVM + Anti-Replay):\n";

$polygonPayload = t_payload([
    'auth' => ['value' => '5000'],
    'accepted' => [
        'network' => 'eip155:137',
        'asset' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
        'amount' => '5000',
    ],
]);
$decPoly = grid402_decode_x402_payload($polygonPayload);
t_assert('Payload Polygon v2 decodificado corretamente', $decPoly['ok'] === true && $decPoly['network'] === 'eip155:137');
$valPolyReq = grid402_validate_exact_requirements($decPoly, 5000);
t_assert('Requisitos locais Polygon validados com sucesso', !empty($valPolyReq['valid']), json_encode($valPolyReq));

$arbPayload = t_payload([
    'auth' => ['value' => '5000'],
    'accepted' => [
        'network' => 'eip155:42161',
        'asset' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
        'amount' => '5000',
    ],
]);
$decArb = grid402_decode_x402_payload($arbPayload);
t_assert('Payload Arbitrum v2 decodificado corretamente', $decArb['ok'] === true && $decArb['network'] === 'eip155:42161');
$valArbReq = grid402_validate_exact_requirements($decArb, 5000);
t_assert('Requisitos locais Arbitrum validados com sucesso', !empty($valArbReq['valid']), json_encode($valArbReq));

// Solana SVM payload (>= 64 bytes binários como transação Solana real)
$solTxBytes = base64_encode(random_bytes(160));
$solEnvelope = [
    'x402Version' => 2,
    'accepted' => [
        'scheme' => 'exact',
        'network' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
        'asset' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        'amount' => '5000',
        'payTo' => GRID402_SOLANA_WALLET_ADDRESS,
        'extra' => ['feePayer' => GRID402_PAYAI_SOLANA_FEE_PAYER],
    ],
    'payload' => [
        'transaction' => $solTxBytes,
    ],
];
$solProofB64 = base64_encode((string) json_encode($solEnvelope));
$decSol = grid402_decode_x402_payload($solProofB64);
t_assert('Payload Solana SVM decodificado corretamente (kind=svm)', $decSol['ok'] === true && ($decSol['kind'] ?? '') === 'svm', json_encode($decSol));

// Com flag default false (V-05), Solana gasless é rejeitada localmente com 402
$valSolDefaultOff = grid402_validate_exact_requirements($decSol, 5000);
t_assert('Com GRID402_SOLANA_X402_ENABLED=false, requisitos locais Solana são rejeitados (V-05)', empty($valSolDefaultOff['valid']) && ($valSolDefaultOff['code'] ?? 0) === 402, json_encode($valSolDefaultOff));

// Em subprocesso isolado com GRID402_SOLANA_X402_ENABLED=true, valida + liquida + bloqueia replay
$solSubStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_sol_v4_' . bin2hex(random_bytes(4));
mkdir($solSubStorage, 0775, true);
$solSubScript = $solSubStorage . DIRECTORY_SEPARATOR . 'run_sol.php';
file_put_contents($solSubScript, <<<'PHP'
<?php
require_once $argv[1] . '/tests/helpers_x402_exact.php';
$dir = t_bootstrap('settle');
$solProofB64 = $argv[2];
$decSol = grid402_decode_x402_payload($solProofB64);
$valSolReq = grid402_validate_exact_requirements($decSol, 5000);
$calls = [];
t_install_transport([
    '/v2/x402/verify' => ['status' => 200, 'body' => ['isValid' => true, 'payer' => 'So1anaPayerTestAddress111111111111111111111']],
    '/v2/x402/settle' => ['status' => 200, 'body' => ['success' => true, 'transaction' => '5eyktSolanaTxSignatureMock11111111111111111111111111111111111111111111', 'network' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp']],
], $calls);
$solSettle1 = grid402_validate_exact_payment($solProofB64, 5000, 'extract');
$solReplay = grid402_validate_exact_payment($solProofB64, 5000, 'extract');
t_rmdir($dir);
echo json_encode([
    'req_valid' => !empty($valSolReq['valid']),
    'settle1' => $solSettle1,
    'replay' => $solReplay,
]);
PHP);
$solSubEnv = [];
foreach (array_merge($_ENV, $_SERVER) as $ek => $ev) {
    if (is_string($ek) && (is_string($ev) || is_numeric($ev))) {
        $solSubEnv[$ek] = (string) $ev;
    }
}
$solSubEnv['GRID402_SOLANA_X402_ENABLED'] = 'true';
$solProc = proc_open([PHP_BINARY, $solSubScript, dirname(__DIR__), $solProofB64], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $solPipes, dirname(__DIR__), $solSubEnv);
$solOutJson = is_resource($solProc) ? (string) stream_get_contents($solPipes[1]) : '';
if (is_resource($solProc)) {
    fclose($solPipes[1]);
    fclose($solPipes[2]);
    proc_close($solProc);
}
t_rmdir($solSubStorage);
$solSubRes = json_decode($solOutJson, true) ?: [];
t_assert('Com GRID402_SOLANA_X402_ENABLED=true, requisitos locais Solana são validados com sucesso', !empty($solSubRes['req_valid']), $solOutJson);
t_assert('Liquidação Solana via PayAI mock aprovada (HTTP 200, source=facilitator, network CAIP-2 Solana)',
    !empty($solSubRes['settle1']['valid']) &&
    ($solSubRes['settle1']['source'] ?? '') === 'facilitator' &&
    ($solSubRes['settle1']['network'] ?? '') === 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
    $solOutJson
);
t_assert('Replay da mesma transação Solana bloqueado com HTTP 403',
    empty($solSubRes['replay']['valid']) && ($solSubRes['replay']['code'] ?? 0) === 403,
    $solOutJson
);

// Rejeição de carteira/ativo inválido em Polygon
$badPoly = t_payload([
    'auth' => ['value' => '5000'],
    'accepted' => [
        'network' => 'eip155:137',
        'asset' => '0x0000000000000000000000000000000000000001',
        'amount' => '5000',
    ],
]);
$badPolyRes = grid402_validate_exact_requirements(grid402_decode_x402_payload($badPoly), 5000);
t_assert('Ativo incorreto em Polygon rejeitado com HTTP 402 antes do facilitador',
    empty($badPolyRes['valid']) && ($badPolyRes['code'] ?? 0) === 402,
    json_encode($badPolyRes)
);

// -----------------------------------------------------------------
// 3. Serviço /api/v1/extract (SSRF Protection + Conversão Markdown)
// -----------------------------------------------------------------
echo "\n[3/7] /api/v1/extract — Proteção SSRF e Conversão HTML -> Markdown:\n";
$ssrfUrls = [
    'http://127.0.0.1/',
    'http://localhost/admin',
    'http://169.254.169.254/latest/meta-data/',
    'file:///etc/passwd',
    'http://10.0.0.1/internal',
    'http://[::1]/',
];
foreach ($ssrfUrls as $badUrl) {
    $chk = grid402_extract_validate_public_url($badUrl);
    t_assert("SSRF bloqueado para {$badUrl}", $chk['ok'] === false && ($chk['code'] ?? 0) === 400, json_encode($chk));
}

$dnsPrivateMock = static fn(string $host): array => ['192.168.1.50'];
$chkPrivateDns = grid402_extract_validate_public_url('https://internal.corp.example', $dnsPrivateMock);
t_assert('Domínio resolvendo para IP privado RFC1918 bloqueado (BlockedTargetAddress)',
    $chkPrivateDns['ok'] === false && ($chkPrivateDns['error'] ?? '') === 'BlockedTargetAddress'
);

$dnsPublicMock = static fn(string $host): array => ['93.184.216.34'];
$httpMock = static fn(string $url): array => [
    'ok' => true,
    'final_url' => $url,
    'http_status' => 200,
    'content_type' => 'text/html; charset=utf-8',
    'bytes_downloaded' => 380,
    'body' => '<!doctype html><html lang="en"><head><title>Example Article</title><meta name="description" content="Clean summary"><link rel="canonical" href="https://example.com/article"></head><body><nav>Menu</nav><main><h1>Hello World</h1><p>First paragraph for RAG agents.</p><a href="/docs">Read Docs</a></main><script>alert(1)</script></body></html>',
];
$extRes = grid402_service_extract('https://example.com/article', 5000, $httpMock, $dnsPublicMock);
t_assert('Extração HTML -> Markdown gera title, markdown sem <script>, word_count e links absolutos',
    !empty($extRes['success']) &&
    ($extRes['title'] ?? '') === 'Example Article' &&
    str_contains((string) ($extRes['markdown'] ?? ''), '# Hello World') &&
    !str_contains((string) ($extRes['markdown'] ?? ''), 'alert(1)') &&
    ($extRes['word_count'] ?? 0) > 3 &&
    ($extRes['links'][0]['url'] ?? '') === 'https://example.com/docs',
    json_encode($extRes)
);

// -----------------------------------------------------------------
// 4. Serviço /api/v1/email-verify (RFC, MX/SPF/DMARC, Disposable, Zero-PII)
// -----------------------------------------------------------------
echo "\n[4/7] /api/v1/email-verify — Validação, Disposable e Conformidade Zero-PII:\n";
$badEmail = grid402_service_email_verify('nao-e-email');
t_assert('E-mail inválido rejeitado com HTTP 400 (InvalidEmailParameter)',
    empty($badEmail['success']) && ($badEmail['code'] ?? 0) === 400
);

$dnsCorpMock = static function (string $host, int $type): array {
    if ($type === DNS_MX) {
        return [['target' => 'mx1.grid402.online', 'pri' => 10]];
    }
    if ($type === DNS_TXT && str_starts_with($host, '_dmarc.')) {
        return [['txt' => 'v=DMARC1; p=reject; rua=mailto:dmarc@grid402.online']];
    }
    if ($type === DNS_TXT) {
        return [['txt' => 'v=spf1 include:_spf.hostinger.com ~all']];
    }
    return [['ip' => '93.184.216.34']];
};
$corpEmail = 'executivo.silva@empresavalida.com.br';
$corpRes = grid402_service_email_verify($corpEmail, $dnsCorpMock);
t_assert('E-mail corporativo com MX+SPF+DMARC retorna VALID, score=100 e email_hash_sha256',
    !empty($corpRes['success']) &&
    ($corpRes['verdict'] ?? '') === 'VALID' &&
    ($corpRes['has_mx'] ?? false) === true &&
    ($corpRes['has_spf'] ?? false) === true &&
    ($corpRes['has_dmarc'] ?? false) === true &&
    ($corpRes['disposable'] ?? true) === false &&
    ($corpRes['deliverability_score'] ?? 0) === 100 &&
    ($corpRes['email_hash_sha256'] ?? '') === hash('sha256', $corpEmail),
    json_encode($corpRes)
);

$dispRes = grid402_service_email_verify('teste@mailinator.com', $dnsCorpMock);
t_assert('Domínio descartável (mailinator.com) retorna disposable=true e verdict=RISKY',
    !empty($dispRes['success']) && ($dispRes['disposable'] ?? false) === true && ($dispRes['verdict'] ?? '') === 'RISKY',
    json_encode($dispRes)
);

// Garantia Zero-PII: nenhum arquivo no storageDir pode conter o e-mail completo
$leakedEmail = false;
foreach (glob($storageDir . DIRECTORY_SEPARATOR . '*') ?: [] as $storedFile) {
    if (is_file($storedFile) && str_contains((string) file_get_contents($storedFile), $corpEmail)) {
        $leakedEmail = true;
    }
}
t_assert('Zero-PII garantido: endereço de e-mail completo nunca é persistido em disco', !$leakedEmail);

// -----------------------------------------------------------------
// 5, 6, 7. Testes HTTP E2E no servidor local (402, 400, Sandbox 3/dia, /mcp, SSR PT-BR)
// -----------------------------------------------------------------
echo "\n[5/7] Testes HTTP: /extract, /email-verify e Sandbox 3 req/dia:\n";
$sandboxFile = $storageDir . DIRECTORY_SEPARATOR . 'sandbox_v4_test.json';
$rapidSecretTest = 'rapidapi_test_secret_' . bin2hex(random_bytes(12));
$server = grid402_start_server([
    'docroot' => __DIR__ . '/../public_html',
    'router' => __DIR__ . '/router.php',
    'probe' => '/status',
    'env' => [
        'GRID402_STORAGE_DIR' => $storageDir,
        'GRID402_FILE_SANDBOX_RATES' => $sandboxFile,
        'GRID402_DISABLE_TELEGRAM' => '1',
        'GRID402_RAPIDAPI_PROXY_SECRET' => $rapidSecretTest,
    ],
]);
$baseUrl = $server['base_url'];

$httpCall = static function (string $url, array $headers = [], ?string $postBody = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 6,
    ];
    if ($postBody !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $postBody;
    }
    curl_setopt_array($ch, $opts);
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

try {
    // SSRF pré-cobrança em /api/v1/extract
    $rSsrf = $httpCall("{$baseUrl}/api/v1/extract?url=" . urlencode('http://127.0.0.1/'));
    t_assert('HTTP GET /api/v1/extract?url=http://127.0.0.1/ retorna 400 antes do 402', $rSsrf['code'] === 400, "Code: {$rSsrf['code']}");

    // Email inválido pré-cobrança em /api/v1/email-verify
    $rBadEmail = $httpCall("{$baseUrl}/api/v1/email-verify?email=nao-e-email");
    t_assert('HTTP GET /api/v1/email-verify?email=nao-e-email retorna 400 antes do 402', $rBadEmail['code'] === 400, "Code: {$rBadEmail['code']}");

    // 402 multi-chain em /api/v1/extract (3 redes EVM default V-05)
    $r402Ext = $httpCall("{$baseUrl}/api/v1/extract?url=" . urlencode('https://example.com'));
    $j402Ext = json_decode($r402Ext['body'], true);
    t_assert('HTTP 402 em /api/v1/extract inclui price_usdc=0.0050, 3 redes EVM em accepts[] (default V-05), preview e checkout Pix + RapidAPI',
        $r402Ext['code'] === 402 &&
        ($j402Ext['price_usdc'] ?? '') === '0.0050' &&
        count($j402Ext['accepts'] ?? []) === 3 &&
        isset($j402Ext['preview'], $j402Ext['locked_fields']) &&
        str_contains((string) ($j402Ext['fiat_and_card_checkout']['pix_instant_brl']['starter_url'] ?? ''), 'autopix=1') &&
        str_contains((string) ($j402Ext['fiat_and_card_checkout']['rapidapi_credit_card_usd']['all_25_apis_url'] ?? ''), 'rapidapi.com'),
        "Code={$r402Ext['code']} accepts=" . count($j402Ext['accepts'] ?? [])
    );

    // Sandbox 3 req/dia -> 3x HTTP 200, 4ª retorna HTTP 429
    $sbCodes = [];
    $firstSbBody = null;
    for ($i = 1; $i <= 4; $i++) {
        $rSb = $httpCall("{$baseUrl}/api/v1/holidays?year=2026", ['X-Payment: demo']);
        $sbCodes[] = $rSb['code'];
        if ($i === 1) {
            $firstSbBody = json_decode($rSb['body'], true);
        }
        if ($i === 4) {
            $fourthSbHdrs = $rSb['headers'];
            $fourthSbBody = json_decode($rSb['body'], true);
        }
    }
    t_assert('Sandbox permite exatamente 3 chamadas (200, 200, 200) e bloqueia a 4ª com HTTP 429',
        $sbCodes === [200, 200, 200, 429],
        'Codes: ' . implode(',', $sbCodes)
    );
    t_assert('1ª chamada sandbox expõe quota_remaining_today=2 e quota_limit_per_day=3',
        ($firstSbBody['sandbox_info']['quota_remaining_today'] ?? -1) === 2 &&
        ($firstSbBody['sandbox_info']['quota_limit_per_day'] ?? -1) === 3
    );
    t_assert('4ª chamada sandbox retorna Retry-After, PAYMENT-REQUIRED, x402 v2 accepts, retry_after_seconds e upgrade_to_production com how_to_continue',
        stripos($fourthSbHdrs ?? '', 'Retry-After:') !== false &&
        stripos($fourthSbHdrs ?? '', 'PAYMENT-REQUIRED:') !== false &&
        (int) ($fourthSbBody['x402Version'] ?? 0) === 2 &&
        !empty($fourthSbBody['accepts']) &&
        (int) ($fourthSbBody['retry_after_seconds'] ?? 0) > 0 &&
        str_contains((string) ($fourthSbBody['message'] ?? ''), 'Free sandbox quota') &&
        !empty($fourthSbBody['upgrade_to_production']['how_to_continue']['session_pass_no_wallet']) &&
        str_contains((string) ($fourthSbBody['upgrade_to_production']['session_pass_url'] ?? ''), '/pass') &&
        str_contains((string) ($fourthSbBody['upgrade_to_production']['pix_instant_starter_url'] ?? ''), 'autopix=1') &&
        str_contains((string) ($fourthSbBody['upgrade_to_production']['rapidapi_credit_card_usd']['brazil_kyb_cnpj_anbima_url'] ?? ''), 'rapidapi.com')
    );

    // -----------------------------------------------------------------
    // 6. Hosted MCP POST /mcp (version 2.0.0 e 27 tools)
    // -----------------------------------------------------------------
    echo "\n[6/7] Hosted MCP POST /mcp (version 2.0.0 e 27 tools):\n";
    $rMcpInit = $httpCall("{$baseUrl}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [],
    ]));
    $jMcpInit = json_decode($rMcpInit['body'], true);
    t_assert('POST /mcp initialize retorna version=2.0.0',
        $rMcpInit['code'] === 200 && ($jMcpInit['result']['serverInfo']['version'] ?? '') === '2.0.0',
        $rMcpInit['body']
    );

    $rMcpList = $httpCall("{$baseUrl}/mcp", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => [],
    ]));
    $jMcpList = json_decode($rMcpList['body'], true);
    $mcpTools = $jMcpList['result']['tools'] ?? [];
    $mcpNames = array_column($mcpTools, 'name');
    t_assert('POST /mcp tools/list expõe exatamente 27 ferramentas incluindo extract e email_verify',
        count($mcpTools) === 27 &&
        in_array('grid402_extract', $mcpNames, true) &&
        in_array('grid402_email_verify', $mcpNames, true),
        'Count: ' . count($mcpTools)
    );

    // -----------------------------------------------------------------
    // 7. Funil Web Humano SSR (/dossie-kyb, 3 páginas SEO, Home CTA, Sitemap, docs/revenue/)
    // -----------------------------------------------------------------
    echo "\n[7/7] Funil Web Humano PT-BR, SEO SSR, Home CTA, Sitemap e Pacotes Comerciais:\n";

    $rHome = $httpCall("{$baseUrl}/", ['Accept: text/html']);
    t_assert('Home (/) mantém SSR EN e inclui CTA Pix R$ 12 + link /dossie-kyb',
        $rHome['code'] === 200 &&
        str_contains($rHome['body'], '<html lang="en">') &&
        str_contains($rHome['body'], '/pass?tier=starter') &&
        str_contains($rHome['body'], '/dossie-kyb')
    );

    $rDossie = $httpCall("{$baseUrl}/dossie-kyb", ['Accept: text/html']);
    $h1CountDossie = preg_match_all('/<h1\b/i', $rDossie['body']);
    t_assert('/dossie-kyb retorna HTTP 200, lang="pt-BR", único <h1>, Pix R$ 12 e @media print',
        $rDossie['code'] === 200 &&
        str_contains($rDossie['body'], '<html lang="pt-BR">') &&
        $h1CountDossie === 1 &&
        str_contains($rDossie['body'], '/api/v1/pass/mp-pix') &&
        str_contains($rDossie['body'], '@media print')
    );

    $titlesSeen = [];
    foreach (['/api-cnpj', '/api-sancoes-cgu', '/api-dias-uteis-anbima'] as $seoPath) {
        $rSeo = $httpCall("{$baseUrl}{$seoPath}", ['Accept: text/html']);
        $h1Count = preg_match_all('/<h1\b/i', $rSeo['body']);
        preg_match('/<title>(.*?)<\/title>/is', $rSeo['body'], $tm);
        $titleVal = trim((string) ($tm[1] ?? ''));
        $titlesSeen[$seoPath] = $titleVal;
        $hasLd = preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $rSeo['body'], $ldm) === 1
            && is_array(json_decode($ldm[1], true));
        t_assert("SEO {$seoPath} retorna HTTP 200, pt-BR, único <h1>, JSON-LD válido e CTA Pix",
            $rSeo['code'] === 200 &&
            str_contains($rSeo['body'], '<html lang="pt-BR">') &&
            $h1Count === 1 &&
            $titleVal !== '' &&
            $hasLd &&
            str_contains($rSeo['body'], '/pass?tier=starter') &&
            str_contains($rSeo['body'], '/dossie-kyb')
        );
    }
    t_assert('As 3 páginas SEO possuem <title> distintos entre si', count(array_unique(array_values($titlesSeen))) === 3);

    $enTitlesSeen = [];
    foreach (['/url-to-markdown-api', '/global-kyb-api', '/zero-pii-email-verification-api'] as $enSeoPath) {
        $rEnSeo = $httpCall("{$baseUrl}{$enSeoPath}", ['Accept: text/html']);
        $enH1Count = preg_match_all('/<h1\b/i', $rEnSeo['body']);
        preg_match('/<title>(.*?)<\/title>/is', $rEnSeo['body'], $etm);
        $enTitleVal = trim((string) ($etm[1] ?? ''));
        $enTitlesSeen[$enSeoPath] = $enTitleVal;
        $enHasLd = preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $rEnSeo['body'], $eldm) === 1
            && is_array(json_decode($eldm[1], true));
        t_assert("SEO Global EN {$enSeoPath} retorna HTTP 200, lang=\"en\", único <h1>, JSON-LD USD e CTA RapidAPI",
            $rEnSeo['code'] === 200 &&
            str_contains($rEnSeo['body'], '<html lang="en">') &&
            $enH1Count === 1 &&
            $enTitleVal !== '' &&
            $enHasLd &&
            str_contains($rEnSeo['body'], 'rapidapi.com') &&
            str_contains($rEnSeo['body'], '/pass')
        );
    }
    t_assert('As 3 páginas SEO Globais EN possuem <title> distintos entre si', count(array_unique(array_values($enTitlesSeen))) === 3);

    $sitemap = (string) file_get_contents(__DIR__ . '/../public_html/sitemap.xml');
    t_assert('sitemap.xml inclui as 6 páginas SEO (3 PT-BR + 3 Globais EN), /pass e /dossie-kyb',
        str_contains($sitemap, 'https://grid402.online/pass') &&
        str_contains($sitemap, 'https://grid402.online/dossie-kyb') &&
        str_contains($sitemap, 'https://grid402.online/api-cnpj') &&
        str_contains($sitemap, 'https://grid402.online/api-sancoes-cgu') &&
        str_contains($sitemap, 'https://grid402.online/api-dias-uteis-anbima') &&
        str_contains($sitemap, 'https://grid402.online/url-to-markdown-api') &&
        str_contains($sitemap, 'https://grid402.online/global-kyb-api') &&
        str_contains($sitemap, 'https://grid402.online/zero-pii-email-verification-api')
    );

    $revDocs = [
        'rapidapi-pack.md',
        'n8n-creator-submission.md',
        'b2b-outbound-playbook.md',
        'directory-checklist.md',
    ];
    foreach ($revDocs as $docName) {
        $docPath = __DIR__ . '/../docs/revenue/' . $docName;
        $docBody = is_file($docPath) ? (string) file_get_contents($docPath) : '';
        t_assert("Pacote comercial docs/revenue/{$docName} existe e contém pricing coerente",
            $docBody !== '' && str_contains($docBody, 'R$ 12') && str_contains($docBody, '0.0050')
        );
    }

    // -----------------------------------------------------------------
    // 8. Ponte Nativa RapidAPI (X-RapidAPI-Proxy-Secret)
    // -----------------------------------------------------------------
    echo "\n[8/8] Ponte Nativa RapidAPI (X-RapidAPI-Proxy-Secret):\n";
    $rRapidOk = $httpCall("{$baseUrl}/api/v1/holidays?year=2026", ["X-RapidAPI-Proxy-Secret: {$rapidSecretTest}"]);
    $jRapidOk = json_decode($rRapidOk['body'], true);
    t_assert('X-RapidAPI-Proxy-Secret válido libera consulta (HTTP 200, source=rapidapi, _oracle_proof)',
        $rRapidOk['code'] === 200 &&
        ($jRapidOk['_billing']['payment_source'] ?? '') === 'rapidapi' &&
        !empty($jRapidOk['_oracle_proof']) &&
        stripos($rRapidOk['headers'], 'X-Payment-Network: RapidAPI Proxy') !== false,
        "Code={$rRapidOk['code']}"
    );

    $rRapidBad = $httpCall("{$baseUrl}/api/v1/holidays?year=2026", ['X-RapidAPI-Proxy-Secret: segredo_incorreto_1234567890']);
    t_assert('X-RapidAPI-Proxy-Secret inválido retorna HTTP 402', $rRapidBad['code'] === 402, "Code={$rRapidBad['code']}");

    $rRapidPassBlock = $httpCall("{$baseUrl}/api/v1/pass", ["X-RapidAPI-Proxy-Secret: {$rapidSecretTest}"]);
    t_assert('X-RapidAPI-Proxy-Secret NUNCA libera emissão gratuita em /api/v1/pass (HTTP 402)',
        $rRapidPassBlock['code'] === 402,
        "Code={$rRapidPassBlock['code']}"
    );

    // -----------------------------------------------------------------
    // 9. Passo 1 — Higiene de Precos, Comparador ROI Verificavel, Garantias e Trava RapidAPI
    // -----------------------------------------------------------------
    echo "\n[9/9] Higiene de Preços, Comparador Verificável, Garantias e Trava Anti-Arbitragem RapidAPI:\n";

    $rHome = $httpCall("{$baseUrl}/");
    t_assert('Home (/) traz comparador ROI verificável (OpenSanctions/Infosimples/Direct Data, presets 40/600) sem $ 285.00 legado',
        $rHome['code'] === 200 &&
        str_contains($rHome['body'], 'OpenSanctions') &&
        str_contains($rHome['body'], 'Infosimples') &&
        str_contains($rHome['body'], 'Direct Data') &&
        str_contains($rHome['body'], 'setRoiPreset(40)') &&
        str_contains($rHome['body'], 'setRoiPreset(600)') &&
        !str_contains($rHome['body'], '$ 285.00')
    );

    $rPassPage = $httpCall("{$baseUrl}/pass");
    $rCatalogPage = $httpCall("{$baseUrl}/catalog");
    $rDossiePage = $httpCall("{$baseUrl}/dossie-kyb");
    t_assert('/pass, /catalog e /dossie-kyb destacam garantias pró-cliente (estorno automático + Idempotency-Key)',
        $rPassPage['code'] === 200 && str_contains($rPassPage['body'], 'guarantees.g1.title') &&
        $rCatalogPage['code'] === 200 && str_contains($rCatalogPage['body'], 'guarantees.g1.title') &&
        $rDossiePage['code'] === 200 && str_contains($rDossiePage['body'], 'Erro não cobra')
    );

    t_assert('HTTP 402 em /api/v1/extract exibe tabela sincronizada (Pro R$ 29, Fleet R$ 115, RapidAPI 1,500 std calls)',
        str_contains((string) ($j402Ext['fiat_and_card_checkout']['pix_instant_brl']['pricing'] ?? ''), 'Pro R$ 29') &&
        str_contains((string) ($j402Ext['fiat_and_card_checkout']['pix_instant_brl']['pricing'] ?? ''), 'Fleet R$ 115') &&
        str_contains((string) ($j402Ext['fiat_and_card_checkout']['rapidapi_credit_card_usd']['plans'] ?? ''), '1,500 std calls')
    );

    // Teste isolado da trava suave anti-arbitragem RapidAPI (GRID402_RAPIDAPI_DOSSIER_DAILY_CAP=2 + BLOCKED=compliance)
    $guardDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_rapid_guard_' . bin2hex(random_bytes(4));
    @mkdir($guardDir, 0775, true);
    $guardCode = <<<'PHP'
define('GRID402_EXEC', true);
require __DIR__ . '/public_html/config.php';
$_SERVER['HTTP_X_RAPIDAPI_USER'] = 'alice_pro_sub';
$g1 = grid402_check_rapidapi_endpoint_guard('dossier');
$g2 = grid402_check_rapidapi_endpoint_guard('dossier');
$g3 = grid402_check_rapidapi_endpoint_guard('dossier');
// Estorna 1 slot (simulando HTTP != 200) e verifica liberacao
grid402_refund_rapidapi_endpoint_guard('dossier');
$g4 = grid402_check_rapidapi_endpoint_guard('dossier');
// Outro assinante deve ter cota isolada
$_SERVER['HTTP_X_RAPIDAPI_USER'] = 'bob_pro_sub';
$gBob = grid402_check_rapidapi_endpoint_guard('dossier');
// Rota nao-dossier nao e bloqueada pelo cap de dossier
$gHol = grid402_check_rapidapi_endpoint_guard('holidays');
// Rota em GRID402_RAPIDAPI_BLOCKED_ENDPOINTS retorna 403
$gComp = grid402_check_rapidapi_endpoint_guard('compliance');
$rawFile = (string) @file_get_contents(GRID402_FILE_RAPIDAPI_LIMITS);
$ok = !empty($g1['allowed']) && ($g1['used_today'] ?? 0) === 1
    && !empty($g2['allowed']) && ($g2['used_today'] ?? 0) === 2
    && empty($g3['allowed']) && ($g3['http_status'] ?? 0) === 429 && ($g3['error'] ?? '') === 'RapidApiEndpointCapExceeded' && ($g3['retry_after'] ?? 0) > 0
    && !empty($g4['allowed']) && ($g4['used_today'] ?? 0) === 2
    && !empty($gBob['allowed']) && ($gBob['used_today'] ?? 0) === 1
    && !empty($gHol['allowed'])
    && empty($gComp['allowed']) && ($gComp['http_status'] ?? 0) === 403 && ($gComp['error'] ?? '') === 'RapidApiEndpointRestricted'
    && !str_contains($rawFile, 'alice_pro_sub') && !str_contains($rawFile, 'bob_pro_sub');
echo $ok ? 'GUARD_OK' : ('GUARD_FAIL:' . json_encode([$g1, $g2, $g3, $g4, $gBob, $gHol, $gComp]));
PHP;
    $guardEnv = array_merge($_ENV, $_SERVER, [
        'GRID402_STORAGE_DIR' => $guardDir,
        'GRID402_RAPIDAPI_DOSSIER_DAILY_CAP' => '2',
        'GRID402_RAPIDAPI_BLOCKED_ENDPOINTS' => 'compliance',
    ]);
    // Filtra apenas variaveis escalares para proc_open
    $cleanGuardEnv = [];
    foreach ($guardEnv as $ek => $ev) {
        if (is_string($ek) && (is_string($ev) || is_numeric($ev))) {
            $cleanGuardEnv[$ek] = (string) $ev;
        }
    }
    $descSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $gProc = proc_open([PHP_BINARY, '-r', $guardCode], $descSpec, $gPipes, dirname(__DIR__), $cleanGuardEnv);
    $gOut = '';
    $gErr = '';
    $gExit = -1;
    if (is_resource($gProc)) {
        fclose($gPipes[0]);
        $gOut = (string) stream_get_contents($gPipes[1]);
        $gErr = (string) stream_get_contents($gPipes[2]);
        fclose($gPipes[1]);
        fclose($gPipes[2]);
        $gExit = proc_close($gProc);
    }
    t_rmdir($guardDir);
    t_assert('Trava suave RapidAPI aplica cap diário (429), estorno em erro, isolamento Zero-PII por assinante e bloqueio explícito (403)',
        $gExit === 0 && trim($gOut) === 'GUARD_OK',
        "Exit={$gExit} Out={$gOut} Err={$gErr}"
    );

    // 10. Passo 3 — Rebalanceamento de precos x402 (/cnpj 0.0100, /lei 0.0100, /ofac 0.0200, /kyb-audit 0.0250)
    $r402Cnpj = $httpCall("{$baseUrl}/api/v1/cnpj?cnpj=00000000000191");
    $j402Cnpj = json_decode($r402Cnpj['body'], true);
    $r402Lei = $httpCall("{$baseUrl}/api/v1/lei?lei=5493006MHB84DD0ZWV18");
    $j402Lei = json_decode($r402Lei['body'], true);
    $r402Ofac = $httpCall("{$baseUrl}/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16");
    $j402Ofac = json_decode($r402Ofac['body'], true);
    $r402Kyb = $httpCall("{$baseUrl}/api/v1/kyb-audit?cnpj=00000000000191");
    $j402Kyb = json_decode($r402Kyb['body'], true);

    t_assert('Desafios HTTP 402 refletem tabela rebalanceada: /cnpj=0.0100, /lei=0.0100, /ofac=0.0200 e /kyb-audit=0.0250',
        $r402Cnpj['code'] === 402 && ($j402Cnpj['price_usdc'] ?? '') === '0.0100' && (($j402Cnpj['accepts'][0]['amount'] ?? '') === '10000') &&
        $r402Lei['code'] === 402 && ($j402Lei['price_usdc'] ?? '') === '0.0100' && (($j402Lei['accepts'][0]['amount'] ?? '') === '10000') &&
        $r402Ofac['code'] === 402 && ($j402Ofac['price_usdc'] ?? '') === '0.0200' && (($j402Ofac['accepts'][0]['amount'] ?? '') === '20000') &&
        $r402Kyb['code'] === 402 && ($j402Kyb['price_usdc'] ?? '') === '0.0250' && (($j402Kyb['accepts'][0]['amount'] ?? '') === '25000'),
        'Prices=' . json_encode([
            'cnpj' => $j402Cnpj['price_usdc'] ?? null,
            'lei' => $j402Lei['price_usdc'] ?? null,
            'ofac' => $j402Ofac['price_usdc'] ?? null,
            'kyb' => $j402Kyb['price_usdc'] ?? null,
        ])
    );
} finally {
    grid402_stop_server($server);
    t_rmdir($storageDir);
}

t_finish('RESULTADO FINAL (Revenue Engine v4)');
