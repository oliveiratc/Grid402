<?php
/**
 * Grid402 - Suíte de Testes da Change `add-conversion-boost-x402-pix-pdf`
 *
 * Cobre todos os cenários das 3 delta specs + PRD:
 * 1. Erros recuperáveis x402 (`required_raw_units`, `required_usdc`, `accepts[]`, `PAYMENT-REQUIRED`),
 *    paridade EIP-712 x402.php <-> facilitator.php e política M2M anti-abuso (zero MCPTRY50/promo-claim).
 * 2. Classificação `compliance_probe` vs. `payer_failure` em logger.php, `/admin?api=stats` e UI `/admin`.
 * 3. Card `429` do Playground na Home (`/`) com Pix inline (`micro`/`starter`) e botão `MCPTRY50`
 *    estritamente condicional ao status ativo da campanha.
 * 4. Gerador PDF 1.4 nativo em PHP puro (`services/dossier_pdf.php`), rota `POST /api/v1/dossier/pdf`
 *    com validação `_oracle_proof` / amostra ilustrativa e UI `/dossie-kyb`.
 */

declare(strict_types=1);

$passed = 0;
$failed = 0;

function cb_assert(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [OK] {$msg}\n";
    } else {
        $failed++;
        fwrite(STDERR, "  [FAIL] {$msg}\n");
    }
}

$root = dirname(__DIR__);
$publicDir = $root . '/public_html';
$tmpDir = sys_get_temp_dir() . '/grid402_conv_boost_' . getmypid() . '_' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0775, true);

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
require_once $publicDir . '/logger.php';
require_once $publicDir . '/services/oracle_signer.php';
require_once $publicDir . '/services/dossier_pdf.php';
require_once $publicDir . '/services/promo_pass.php';

function cb_http(string $method, string $url, array $headers = [], ?string $body = null, ?string $cookieFile = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    if ($cookieFile !== null) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawStr = is_string($raw) ? $raw : '';
    $hdrRaw = substr($rawStr, 0, $hdrLen);
    $respBody = substr($rawStr, $hdrLen);

    $hdrMap = [];
    foreach (explode("\r\n", $hdrRaw) as $line) {
        $pos = strpos($line, ':');
        if ($pos !== false) {
            $k = strtolower(trim(substr($line, 0, $pos)));
            $v = trim(substr($line, $pos + 1));
            $hdrMap[$k] = $v;
        }
    }

    $decoded = @json_decode($respBody, true);
    return [
        'status' => $status,
        'headers' => $hdrMap,
        'body' => $respBody,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

// =========================================================================
// BLOCO 1: Auditoria EIP-712 + PDF Encoding + QR Vetorial (Unitários)
// =========================================================================
echo "=== [1/4] Auditoria EIP-712, WinAnsi PT-BR e QR Code Vetorial ===\n";

$exactNets = grid402_exact_supported_networks();
$acceptsAudit = grid402_build_accepts(15000, '/api/v1/dossier', 'eip155:8453');
$acceptsByNet = [];
foreach ($acceptsAudit as $opt) {
    $acceptsByNet[(string) ($opt['network'] ?? '')] = $opt;
}
foreach (['eip155:8453', 'eip155:137', 'eip155:42161'] as $evmNet) {
    $aExtra = $acceptsByNet[$evmNet]['extra'] ?? [];
    $eCfg = $exactNets[$evmNet] ?? [];
    cb_assert(
        !empty($acceptsByNet[$evmNet]) &&
        ($aExtra['name'] ?? '') === ($eCfg['extra']['name'] ?? 'USD Coin') &&
        ($aExtra['version'] ?? '') === ($eCfg['extra']['version'] ?? '2') &&
        strtolower((string) ($acceptsByNet[$evmNet]['asset'] ?? '')) === strtolower((string) ($eCfg['asset'] ?? '')),
        "Paridade EIP-712 exacta e contrato USDC verificada entre x402.php e facilitator.php para {$evmNet}"
    );
}

$winAnsiSample = grid402_pdf_encode_winansi('Dossiê KYB Jurídico — Situação Ativa € 100%');
cb_assert(
    str_contains($winAnsiSample, "\xEA") && // ê
    str_contains($winAnsiSample, "\xED") && // í
    str_contains($winAnsiSample, "\xE7") && // ç
    str_contains($winAnsiSample, "\xE3") && // ã
    str_contains($winAnsiSample, "\x80"),   // €
    'Codificacao WinAnsi em PHP puro preserva acentuacao PT-BR e Euro sem mbstring'
);

$qrMatrix = grid402_qr_encode_matrix('https://grid402.online/dossie-kyb');
$qrSize = count($qrMatrix);
cb_assert(
    $qrSize >= 21 &&
    isset($qrMatrix[0][0]) && $qrMatrix[0][0] === 1 &&
    isset($qrMatrix[0][$qrSize - 1]) && $qrMatrix[0][$qrSize - 1] === 1 &&
    isset($qrMatrix[$qrSize - 1][0]) && $qrMatrix[$qrSize - 1][0] === 1 &&
    count($qrMatrix[0]) === $qrSize,
    "QR Code vetorial puro gera matriz quadrada ({$qrSize}x{$qrSize}) com finder patterns validos"
);

// =========================================================================
// BLOCO 2: Classificacao de Falhas (compliance_probe vs. payer_failure)
// =========================================================================
echo "\n=== [2/4] Classificacao de Falhas: compliance_probe vs. payer_failure ===\n";

cb_assert(
    grid402_is_synthetic_probe_failure('InvalidSignature', '0x' . str_repeat('1', 64)) === true &&
    grid402_is_synthetic_probe_failure('compliance_probe test', '') === true &&
    grid402_is_synthetic_probe_failure('Underpaid', '') === false,
    'Detector de probe sintetico reconhece padroes artificiais mantendo subpagamento isolado como real'
);

$probeIpHmac = hash_hmac('sha256', '198.51.100.10', 'test_secret');
$payerIpHmac = hash_hmac('sha256', '203.0.113.44', 'test_secret');
$nowTs = time();
$mixedFailures = [
    ['ts' => gmdate('c', $nowTs - 120), 'service' => 'ofac', 'status' => 402, 'error' => 'InsufficientAmount', 'ip_hmac' => $payerIpHmac, 'country' => 'US'],
    ['ts' => gmdate('c', $nowTs - 4), 'service' => 'forex', 'status' => 402, 'error' => 'InvalidSignature', 'ip_hmac' => $probeIpHmac, 'country' => 'US'],
    ['ts' => gmdate('c', $nowTs - 3), 'service' => 'dns', 'status' => 402, 'error' => 'ExpiredSignature', 'ip_hmac' => $probeIpHmac, 'country' => 'US'],
    ['ts' => gmdate('c', $nowTs - 2), 'service' => 'ssl', 'status' => 400, 'error' => 'UnsupportedNetwork', 'ip_hmac' => $probeIpHmac, 'country' => 'US'],
];
$classified = grid402_classify_payment_failures($mixedFailures, 15, 3);
$summaryPf = grid402_summarize_payment_failures($classified);
cb_assert(
    ($classified[0]['failure_origin'] ?? '') === 'payer_failure' &&
    ($classified[1]['failure_origin'] ?? '') === 'compliance_probe' &&
    ($classified[2]['failure_origin'] ?? '') === 'compliance_probe' &&
    ($classified[3]['failure_origin'] ?? '') === 'compliance_probe' &&
    ($summaryPf['total'] ?? 0) === 4 &&
    ($summaryPf['payer_failure'] ?? 0) === 1 &&
    ($summaryPf['compliance_probe'] ?? 0) === 3,
    'Rajada >= 3 falhas do mesmo ip_hmac em <= 15s vira compliance_probe enquanto falha isolada permanece payer_failure'
);

// Persiste eventos reais + rajada via grid402_record_payment_failure() para verificar leitura e admin
grid402_record_payment_failure([
    'service' => 'cnpj', 'status' => 402, 'error' => 'Underpaid', 'ip' => '203.0.113.77', 'country' => 'BR',
]);
for ($p = 0; $p < 3; $p++) {
    grid402_record_payment_failure([
        'service' => 'forex', 'status' => 402, 'error' => 'InvalidPaymentSignature', 'ip' => '198.51.100.99', 'country' => 'US',
    ]);
}
$recentPf = grid402_read_recent_payment_failures(20);
$recentSummary = grid402_summarize_payment_failures($recentPf);
cb_assert(
    ($recentSummary['payer_failure'] ?? 0) >= 1 &&
    ($recentSummary['compliance_probe'] ?? 0) >= 3,
    'payment_failures.json persiste e reclassifica eventos gravados em disco mantendo totais consistentes'
);

// =========================================================================
// BLOCO 3: Testes HTTP no Servidor Embutido (x402 + MCP + Admin + Home + PDF)
// =========================================================================
echo "\n=== [3/4] Testes HTTP E2E: x402, MCP, Admin, Playground 429 e PDF ===\n";

$server = grid402_start_server([
    'docroot' => $publicDir,
    'env' => [
        'GRID402_STORAGE_DIR' => $tmpDir,
        'GRID402_SOLANA_X402_ENABLED' => 'false',
        'GRID402_DISABLE_TELEGRAM' => '1',
    ],
]);
$base = $server['base_url'];

try {
    // 3.1 HTTP 402 estruturado sem promo-claim/MCPTRY50
    $r402 = cb_http('GET', "{$base}/index.php?api=dossier&cnpj=00000000000191");
    $j402 = $r402['json'] ?? [];
    cb_assert(
        $r402['status'] === 402 &&
        (int) ($j402['required_raw_units'] ?? 0) === 50000 &&
        ($j402['required_usdc'] ?? '') === '0.0500' &&
        !empty($j402['accepts']) &&
        !empty($r402['headers']['payment-required']) &&
        !str_contains($r402['body'], 'MCPTRY50') &&
        !str_contains($r402['body'], 'promo-claim'),
        'HTTP 402 /api/v1/dossier expoe required_raw_units=50000, required_usdc=0.0500, accepts[], PAYMENT-REQUIRED e omite MCPTRY50/promo-claim'
    );

    // 3.2 HTTP 402 com prova invalida mantem required_raw_units + accepts[]
    $r402BadProof = cb_http('GET', "{$base}/index.php?api=ofac", ['PAYMENT-SIGNATURE: invalid_mock_proof']);
    $j402Bad = $r402BadProof['json'] ?? [];
    cb_assert(
        in_array($r402BadProof['status'], [400, 402], true) &&
        (int) ($j402Bad['required_raw_units'] ?? 0) > 0 &&
        !empty($j402Bad['required_usdc']) &&
        !empty($j402Bad['accepts']) &&
        !empty($r402BadProof['headers']['payment-required']) &&
        !str_contains($r402BadProof['body'], 'MCPTRY50'),
        'Falha recuperavel com PAYMENT-SIGNATURE invalida retorna required_raw_units, required_usdc, accepts[] e PAYMENT-REQUIRED'
    );

    // 3.3 HTTP 400 UnsupportedNetwork mantendo required_raw_units + accepts[] + PAYMENT-REQUIRED
    $r400Net = cb_http('GET', "{$base}/index.php?api=forex", [
        'X-Payment: fakenet:0x' . str_repeat('22', 32),
    ]);
    $j400Net = $r400Net['json'] ?? [];
    cb_assert(
        $r400Net['status'] === 400 &&
        ($j400Net['error'] ?? '') === 'UnsupportedNetwork' &&
        (int) ($j400Net['required_raw_units'] ?? 0) === 5000 &&
        ($j400Net['required_usdc'] ?? '') === '0.0050' &&
        !empty($j400Net['accepts']) &&
        !empty($r400Net['headers']['payment-required']) &&
        !str_contains($r400Net['body'], 'MCPTRY50'),
        'HTTP 400 UnsupportedNetwork inclui required_raw_units=5000, required_usdc=0.0050, accepts[] e PAYMENT-REQUIRED'
    );

    // 3.4 HTTP 429 Sandbox (4a chamada apos cota 3/dia) sem MCPTRY50/promo-claim + campos estruturados
    cb_http('GET', "{$base}/index.php?api=forex", ['X-Payment: demo']);
    cb_http('GET', "{$base}/index.php?api=forex", ['X-Payment: demo']);
    cb_http('GET', "{$base}/index.php?api=forex", ['X-Payment: demo']);
    $r429 = cb_http('GET', "{$base}/index.php?api=forex", ['X-Payment: demo']);
    $j429 = $r429['json'] ?? [];
    cb_assert(
        $r429['status'] === 429 &&
        !empty($r429['headers']['retry-after']) &&
        !empty($r429['headers']['payment-required']) &&
        (int) ($j429['required_raw_units'] ?? 0) === 5000 &&
        ($j429['required_usdc'] ?? '') === '0.0050' &&
        !empty($j429['accepts']) &&
        !str_contains($r429['body'], 'MCPTRY50') &&
        !str_contains($r429['body'], 'promo-claim') &&
        !empty($j429['upgrade_to_production']['session_pass_url']),
        'HTTP 429 sandbox inclui required_raw_units, accepts[], Retry-After, canais pagos e zero mencao a MCPTRY50/promo-claim'
    );

    // 3.5 Hosted MCP: 33 tools e respostas pagas sem promocao gratuita
    $mcpList = cb_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [],
    ]));
    $mcpTools = $mcpList['json']['result']['tools'] ?? [];
    $mcpToolNames = array_column($mcpTools, 'name');
    cb_assert(
        count($mcpTools) === 33 && !in_array('grid402_promo_claim', $mcpToolNames, true),
        'POST /mcp tools/list mantem exatamente 33 ferramentas pagas sem adicionar tool gratuita'
    );

    cb_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
    ]));
    $mcpBlocked = cb_http('POST', "{$base}/mcp.php", ['Content-Type: application/json'], json_encode([
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
    ]));
    $mcpErrMeta = $mcpBlocked['json']['result']['_meta']['x402/error'] ?? [];
    cb_assert(
        !empty($mcpBlocked['json']['result']['isError']) &&
        (int) ($mcpErrMeta['required_raw_units'] ?? 0) > 0 &&
        !empty($mcpErrMeta['required_usdc']) &&
        !empty($mcpErrMeta['accepts']) &&
        !empty($mcpBlocked['headers']['payment-required']) &&
        !str_contains($mcpBlocked['body'], 'MCPTRY50') &&
        !str_contains($mcpBlocked['body'], 'promo-claim'),
        'POST /mcp 402 inclui required_raw_units/required_usdc/accepts/PAYMENT-REQUIRED e omite MCPTRY50/promo-claim'
    );

    // 3.6 Home '/' Card 429 Pix Inline + MCPTRY50 visivel quando campanha ativa
    $homeHtml = cb_http('GET', "{$base}/index.php");
    cb_assert(
        $homeHtml['status'] === 200 &&
        str_contains($homeHtml['body'], 'id="playground-rate-limit-card"') &&
        str_contains($homeHtml['body'], 'id="pg-rl-pix-checkout"') &&
        str_contains($homeHtml['body'], 'id="pg-rl-pix-email"') &&
        str_contains($homeHtml['body'], 'id="btn-pg-rl-gen-pix"') &&
        str_contains($homeHtml['body'], 'activatePlaygroundPassAndRetry') &&
        str_contains($homeHtml['body'], 'runPlaygroundQuery') &&
        str_contains($homeHtml['body'], 'id="btn-pg-rl-claim-promo"') &&
        str_contains($homeHtml['body'], 'MCPTRY50'),
        'Home / SSR renderiza card 429 com checkout Pix inline (micro/starter) e botao MCPTRY50 enquanto ativo'
    );

    // 3.7 POST /api/v1/dossier/pdf: autenticidade, amostra ilustrativa e Zero-DB/Zero-PII
    $filesBeforePdf = scandir($tmpDir);

    // 3.7a Bloqueia dossie forjado / sem assinatura valida
    $forgedPayload = [
        'cnpj' => '12345678000199',
        'verdict' => ['status' => 'APPROVED', 'risk_level' => 'LOW', 'recommendation' => 'Teste forjado'],
        'company' => ['razao_social' => 'Empresa Forjada LTDA', 'situacao_cadastral' => 'ATIVA'],
        '_oracle_proof' => ['data_hash' => '0xdeadbeef', 'signature' => '0xbad', 'signer_address' => '0x0'],
    ];
    $rPdfForged = cb_http('POST', "{$base}/api/v1/dossier/pdf", ['Content-Type: application/json'], json_encode(['dossier' => $forgedPayload]));
    cb_assert(
        $rPdfForged['status'] === 403 && ($rPdfForged['json']['error'] ?? '') === 'InvalidOracleProof',
        'POST /api/v1/dossier/pdf rejeita com HTTP 403 InvalidOracleProof dossie com assinatura falsa'
    );

    // 3.7b Bloqueia tentativa de usar amostra ilustrativa fora do CNPJ 00000000000191
    $forgedSample = $forgedPayload;
    $forgedSample['is_sample_demo'] = true;
    $rPdfBadSample = cb_http('POST', "{$base}/api/v1/dossier/pdf", ['Content-Type: application/json'], json_encode(['dossier' => $forgedSample]));
    cb_assert(
        $rPdfBadSample['status'] === 403 && ($rPdfBadSample['json']['error'] ?? '') === 'InvalidSamplePayload',
        'POST /api/v1/dossier/pdf rejeita flag is_sample_demo fora do CNPJ oficial 00000000000191'
    );

    // 3.7c Gera PDF oficial %PDF-1.4 para amostra oficial 00000000000191
    $officialSample = [
        'cnpj_raw' => '00000000000191',
        'is_sample_demo' => true,
        'verdict' => ['decision' => 'APPROVED', 'risk_score' => 94, 'rationale' => 'Instituicao financeira publica ativa sem restricoes'],
        'cnpj_data' => ['cnpj' => '00.000.000/0001-91', 'razao_social' => 'BANCO DO BRASIL SA', 'situacao_cadastral' => 'ATIVA', 'uf' => 'DF'],
        'simples' => ['opcao_pelo_simples' => false, 'opcao_pelo_mei' => false],
        'sanctions' => ['has_sanctions' => false, 'total_sanctions' => 0],
    ];
    $rPdfSample = cb_http('POST', "{$base}/api/v1/dossier/pdf", ['Content-Type: application/json'], json_encode(['dossier' => $officialSample]));
    cb_assert(
        $rPdfSample['status'] === 200 &&
        str_starts_with($rPdfSample['body'], '%PDF-1.4') &&
        str_contains($rPdfSample['body'], '%%EOF') &&
        str_contains((string) ($rPdfSample['headers']['content-type'] ?? ''), 'application/pdf') &&
        str_contains((string) ($rPdfSample['headers']['content-disposition'] ?? ''), 'dossie-kyb-00000000000191-amostra.pdf') &&
        str_contains($rPdfSample['body'], 'AMOSTRA ILUSTRATIVA') &&
        str_contains($rPdfSample['body'], " re f\n"),
        'POST /api/v1/dossier/pdf gera PDF 1.4 valido com selo AMOSTRA ILUSTRATIVA e QR Code vetorial para 00000000000191'
    );

    // 3.7d Gera PDF oficial autentico para dossie assinado por oracle_signer.php sem gravar em disco
    $signedDossier = [
        'cnpj' => '11222333000181',
        'checked_at' => gmdate('c'),
        'verdict' => [
            'status' => 'APPROVED',
            'risk_level' => 'LOW',
            'recommendation' => 'Operacao liberada sem apontamentos restritivos.',
        ],
        'company' => [
            'razao_social' => 'TECNOLOGIA BRASIL COMÉRCIO S.A.',
            'situacao_cadastral' => 'ATIVA',
            'municipio' => 'São Paulo',
            'uf' => 'SP',
            'capital_social' => 500000,
        ],
        'tax_regime' => ['regime' => 'LUCRO PRESUMIDO', 'opcao_pelo_simples' => false, 'opcao_pelo_mei' => false],
        'sanctions' => ['has_sanctions' => false, 'total_sanctions' => 0],
        'compliance' => ['score' => 92, 'flags' => []],
    ];
    grid402_sign_data($signedDossier, false);
    $rPdfReal = cb_http('POST', "{$base}/api/v1/dossier/pdf", ['Content-Type: application/json'], json_encode(['dossier' => $signedDossier]));
    $filesAfterPdf = scandir($tmpDir);
    cb_assert(
        $rPdfReal['status'] === 200 &&
        str_starts_with($rPdfReal['body'], '%PDF-1.4') &&
        str_contains($rPdfReal['body'], '%%EOF') &&
        str_contains((string) ($rPdfReal['headers']['content-disposition'] ?? ''), 'dossie-kyb-11222333000181.pdf') &&
        !str_contains($rPdfReal['body'], 'AMOSTRA ILUSTRATIVA') &&
        $filesBeforePdf === $filesAfterPdf,
        'POST /api/v1/dossier/pdf gera PDF oficial autentico para payload assinado sem gravar arquivos em disco (Zero-DB/Zero-PII)'
    );

    // 3.8 Pagina /dossie-kyb expoe download nativo + fallback window.print()
    $kybHtml = cb_http('GET', "{$base}/index.php?page=dossie-kyb");
    // fallback caso rota seja /dossie-kyb ou /dossie_kyb.php direto
    if ($kybHtml['status'] !== 200 || !str_contains($kybHtml['body'], 'btn-download-pdf')) {
        $kybHtml = cb_http('GET', "{$base}/dossie_kyb.php");
    }
    cb_assert(
        $kybHtml['status'] === 200 &&
        str_contains($kybHtml['body'], 'id="btn-download-pdf"') &&
        str_contains($kybHtml['body'], '/api/v1/dossier/pdf') &&
        str_contains($kybHtml['body'], 'id="btn-print-pdf"') &&
        str_contains($kybHtml['body'], 'window.print()'),
        'Pagina /dossie-kyb oferece botao principal de download PDF oficial com fallback window.print()'
    );

    // 3.9 Admin setup -> stats e filtros de origem de falhas
    $cookieFile = $tmpDir . '/admin_cookies.txt';
    $adminSetup = cb_http('GET', "{$base}/admin.php", [], null, $cookieFile);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $adminSetup['body'], $csrfM);
    $setupCsrf = $csrfM[1] ?? '';
    cb_http('POST', "{$base}/admin.php", [], http_build_query([
        'username' => 'admin_cb_test',
        'password' => 'SenhaForte#2026!',
        'password_confirm' => 'SenhaForte#2026!',
        'csrf_token' => $setupCsrf,
    ]), $cookieFile);

    $adminHtml = cb_http('GET', "{$base}/admin.php", [], null, $cookieFile);
    $adminStats = cb_http('GET', "{$base}/admin.php?api=stats", ['Accept: application/json'], null, $cookieFile);
    $statsJson = $adminStats['json'] ?? [];
    cb_assert(
        $adminStats['status'] === 200 &&
        isset($statsJson['payment_failures_summary']['total'], $statsJson['payment_failures_summary']['payer_failure'], $statsJson['payment_failures_summary']['compliance_probe']) &&
        (int) $statsJson['payment_failures_summary']['compliance_probe'] >= 3 &&
        (int) $statsJson['payment_failures_summary']['payer_failure'] >= 1 &&
        str_contains($adminHtml['body'], 'id="pf-filter-btn-all"') &&
        str_contains($adminHtml['body'], 'id="pf-filter-btn-payer"') &&
        str_contains($adminHtml['body'], 'id="pf-filter-btn-probe"') &&
        str_contains($adminHtml['body'], 'data-failure-origin='),
        '/admin?api=stats expoe payment_failures_summary e UI exibe filtros e badges de Pagador Real vs Probe Conformidade'
    );
} finally {
    grid402_stop_server($server);
}

// =========================================================================
// BLOCO 4: Home '/' oculta MCPTRY50 quando campanha esgotada/inativa
// =========================================================================
echo "\n=== [4/4] Home '/' oculta MCPTRY50 quando campanha esta inativa/esgotada ===\n";

$tmpInactive = sys_get_temp_dir() . '/grid402_conv_boost_inactive_' . getmypid() . '_' . bin2hex(random_bytes(4));
@mkdir($tmpInactive, 0775, true);
@file_put_contents($tmpInactive . '/promo_claims.json', json_encode([
    'MCPTRY50_v2' => ['total_claims' => 1, 'ip_hmacs' => ['seed' => time()]],
]));

$srvInactive = grid402_start_server([
    'docroot' => $publicDir,
    'env' => [
        'GRID402_STORAGE_DIR' => $tmpInactive,
        'GRID402_PROMO_MCPTRY50_MAX_CLAIMS' => '1',
        'GRID402_DISABLE_TELEGRAM' => '1',
    ],
]);
try {
    $homeInactive = cb_http('GET', $srvInactive['base_url'] . '/index.php');
    cb_assert(
        $homeInactive['status'] === 200 &&
        str_contains($homeInactive['body'], 'id="pg-rl-pix-checkout"') &&
        !str_contains($homeInactive['body'], 'id="btn-pg-rl-claim-promo"'),
        'Com campanha MCPTRY50 esgotada/inativa, Home / mantem Pix inline e suprime btn-pg-rl-claim-promo'
    );
} finally {
    grid402_stop_server($srvInactive);
}

// Limpeza dos diretorios temporarios
foreach ([$tmpDir, $tmpInactive] as $dir) {
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}

echo "\n=== Resumo Final ===\n";
echo "Passou: {$passed} | Falhou: {$failed}\n";
exit($failed > 0 ? 1 : 0);
