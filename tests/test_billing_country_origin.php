<?php
/**
 * Suíte de Testes: País de Origem por Faturamento no Portal Admin e Telegram
 *
 * Valida:
 *  1. Módulo Zero-PII (services/geoip.php): normalização ISO-2, flags sem mbstring,
 *     nomes em PT-BR, prefixos /24 IPv4 e /48 IPv6, anti-spoofing de cabeçalhos,
 *     cache por prefixo e fallback para lookup externo controlado.
 *  2. Telemetria e Analytics (metrics.php): agregação de country_revenue apenas em
 *     pagamentos faturáveis (ignora 402/sandbox/promo/verify_only), Zero lookup
 *     externo em não-faturamentos e country_code em recent_events + top_consumers.
 *  3. Notificação Telegram (x402.php): inclusão da linha País em PT-BR com bandeira
 *     e ausência absoluta do caractere `&` após grid402_notify_telegram_purchase.
 *  4. Mercado Pago (services/mercadopago.php): captura de payer_country_code na
 *     criação da ordem e preservação quando webhook finaliza de outro IP.
 *  5. HTTP E2E (/mcp + /admin): MCP direto (x402) dispara Telegram com país e grava
 *     country_code; MCP Session Pass grava country_code sem Telegram; /admin HTML,
 *     ?api=stats, ?api=export_csv e ?api=export_csv&scope=paid expõem país (13 cols).
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpDir = t_bootstrap('settle', null, [
    'GRID402_DISABLE_TELEGRAM' => '0',
    'GRID402_DISABLE_GEOIP_LOOKUP' => '1',
    'GRID402_TRUST_CF_HEADER' => 'false',
    'GRID402_TRUST_GEO_HEADERS' => 'false',
    'GRID402_TELEGRAM_BOT_TOKEN' => '123456789:AAH_test_bot_token_for_country_suite',
    'GRID402_TELEGRAM_CHAT_ID' => '987654321',
]);

require_once __DIR__ . '/../public_html/metrics.php';
require_once __DIR__ . '/../public_html/logger.php';
require_once __DIR__ . '/../public_html/services/geoip.php';
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/services/mercadopago.php';

$cleanupRecursive = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $p = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) {
            foreach (glob($p . DIRECTORY_SEPARATOR . '*') ?: [] as $sub) {
                @unlink($sub);
            }
            @rmdir($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
};

$clearServerGeoEnv = static function (): void {
    unset(
        $_SERVER['GEOIP_COUNTRY_CODE'],
        $_SERVER['LITESPEED_GEOIP_COUNTRY_CODE'],
        $_SERVER['MM_COUNTRY_CODE'],
        $_SERVER['HTTP_CF_IPCOUNTRY'],
        $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'],
        $_SERVER['HTTP_X_VERCEL_IP_COUNTRY'],
        $_SERVER['HTTP_X_COUNTRY_CODE'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_X_PAYMENT'],
        $_SERVER['HTTP_USER_AGENT']
    );
    putenv('GRID402_TRUST_CF_HEADER=false');
    $_ENV['GRID402_TRUST_CF_HEADER'] = 'false';
    putenv('GRID402_TRUST_GEO_HEADERS=false');
    $_ENV['GRID402_TRUST_GEO_HEADERS'] = 'false';
    grid402_geoip_reset_memory_cache();
};

$httpCall = static function (string $method, string $url, array $headers = [], ?string $body = null, ?string $cookieFile = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($headers !== []) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    if ($cookieFile !== null) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $rawHeaders = substr($raw, 0, $hdrSize);
    $respBody = substr($raw, $hdrSize);
    return [
        'code' => $code,
        'headers' => $rawHeaders,
        'body' => $respBody,
        'json' => json_decode($respBody, true),
    ];
};

try {
    echo "=== TESTES DE PAIS DE ORIGEM POR FATURAMENTO (ADMIN + TELEGRAM) ===\n\n";

    // -------------------------------------------------------------------------
    // [1] Modulo GeoIP Zero-PII
    // -------------------------------------------------------------------------
    echo "[1] Modulo Zero-PII (services/geoip.php):\n";
    t_assert('Normalizacao ISO-2 br -> BR', grid402_normalize_country_code(' br ') === 'BR');
    t_assert('Rejeita sentinelas XX, T1, ZZ, GL',
        grid402_normalize_country_code('XX') === null
        && grid402_normalize_country_code('T1') === null
        && grid402_normalize_country_code('ZZ') === null
        && grid402_normalize_country_code('GL') === null
    );
    t_assert('Bandeira Unicode sem mbstring BR -> Brasil flag e null -> globo',
        grid402_country_flag('BR') === '🇧🇷'
        && grid402_country_flag('US') === '🇺🇸'
        && grid402_country_flag(null) === '🌐'
    );
    t_assert('Nomes em PT-BR e label formatado',
        grid402_country_name_pt('BR') === 'Brasil'
        && grid402_country_name_pt('US') === 'Estados Unidos'
        && grid402_country_name_pt(null) === 'Não identificado'
        && grid402_format_country('BR')['label'] === '🇧🇷 Brasil (BR)'
    );
    t_assert('Prefixos Zero-PII /24 IPv4 e /48 IPv6 sem IP bruto',
        grid402_geoip_ip_prefix('189.40.50.123') === '189.40.50.0/24'
        && grid402_geoip_ip_prefix('2606:4700:4700:1111::1111') === '2606:4700:4700::/48'
        && grid402_geoip_ip_prefix('127.0.0.1') === null
    );

    // Anti-spoofing de CF-IPCountry com REMOTE_ADDR publico nao confiavel
    $clearServerGeoEnv();
    t_assert('Rejeita HTTP_CF_IPCOUNTRY quando REMOTE_ADDR e IP publico nao confiavel',
        grid402_detect_country_from_headers([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_CF_IPCOUNTRY' => 'FR',
        ]) === null
    );
    t_assert('Aceita GEOIP_COUNTRY_CODE nativo mesmo com REMOTE_ADDR publico',
        grid402_detect_country_from_headers([
            'REMOTE_ADDR' => '198.51.100.10',
            'GEOIP_COUNTRY_CODE' => 'de',
            'HTTP_CF_IPCOUNTRY' => 'FR',
        ]) === 'DE'
    );
    putenv('GRID402_TRUST_CF_HEADER=true');
    $_ENV['GRID402_TRUST_CF_HEADER'] = 'true';
    t_assert('Aceita HTTP_CF_IPCOUNTRY quando GRID402_TRUST_CF_HEADER=true',
        grid402_detect_country_from_headers([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_CF_IPCOUNTRY' => 'JP',
        ]) === 'JP'
    );
    $clearServerGeoEnv();

    // Lookup externo + Cache /24 sem IP bruto em disco
    $geoLookups = 0;
    $GLOBALS['grid402_geoip_http_transport'] = static function (string $ip) use (&$geoLookups): ?string {
        $geoLookups++;
        if (str_starts_with($ip, '200.150.10.')) {
            return 'BR';
        }
        if (str_starts_with($ip, '8.8.8.')) {
            return 'US';
        }
        return null;
    };

    t_assert('Lookup externo desabilitado em allowExternalLookup=false nao faz chamadas externas',
        grid402_resolve_client_country('200.150.10.42', false) === null && $geoLookups === 0
    );
    t_assert('Lookup externo permitido resolve BR e grava cache /24',
        grid402_resolve_client_country('200.150.10.42', true) === 'BR' && $geoLookups === 1
    );
    grid402_geoip_reset_memory_cache();
    t_assert('Outro IP no mesmo /24 reutiliza cache em disco com 0 chamadas externas',
        grid402_resolve_client_country('200.150.10.99', true) === 'BR' && $geoLookups === 1
    );
    $cacheRaw = (string) @file_get_contents(grid402_geoip_cache_path());
    t_assert('geoip_cache.json armazena apenas prefixo /24 e NUNCA IP bruto',
        str_contains($cacheRaw, '200.150.10.0/24') && !str_contains($cacheRaw, '200.150.10.42')
    );

    // Negative cache + fallback
    t_assert('Miss grava negative cache e aplica fallbackCode quando fornecido',
        grid402_resolve_client_country('198.51.100.77', true, 'BR') === 'BR' && $geoLookups === 2
    );
    grid402_geoip_reset_memory_cache();
    t_assert('Negative cache evita nova consulta externa dentro do TTL de 10 min',
        grid402_resolve_client_country('198.51.100.88', true, null) === null && $geoLookups === 2
    );

    // -------------------------------------------------------------------------
    // [2] Telemetria e Analytics (metrics.php)
    // -------------------------------------------------------------------------
    echo "\n[2] Telemetria e Analytics por Pais:\n";
    grid402_atomic_write(GRID402_FILE_METRICS, grid402_default_metrics());
    $geoLookupsBeforeTelemetry = $geoLookups;

    // 402 / sandbox / promo / verify_only NAO devem fazer lookup externo nem somar em country_revenue
    grid402_record_telemetry('forex', 402, false, null, '45.10.20.30', null, 'challenge_402');
    grid402_record_telemetry('forex', 200, false, 'demo', '45.10.20.31', 0.0, 'sandbox', ['payment_source' => 'sandbox']);
    grid402_record_telemetry('pass', 200, true, 'promo:MCPTRY50', '45.10.20.32', 0.0, 'paid', ['payment_source' => 'promo_pass']);
    grid402_record_telemetry('forex', 200, true, null, '45.10.20.33', 0.0050, 'paid', ['payment_source' => 'facilitator_verify_only']);
    t_assert('Eventos nao faturaveis (402/sandbox/promo/verify_only) fazem 0 lookups externos',
        $geoLookups === $geoLookupsBeforeTelemetry
    );

    // Pagamentos reais
    grid402_record_telemetry('dossier', 200, true, '0x' . str_repeat('b1', 32), '200.150.10.5', 0.0500, 'paid', ['payment_source' => 'facilitator']); // BR via cache /24
    grid402_record_telemetry('cnpj', 200, true, '0x' . str_repeat('b2', 32), '8.8.8.8', 0.0100, 'paid', ['payment_source' => 'tx_hash']); // US via transport (+1)
    grid402_record_telemetry('forex', 200, true, '0x' . str_repeat('b3', 32), '203.0.113.55', 0.0050, 'paid', ['payment_source' => 'facilitator', 'country_code' => 'DE']); // DE explicito
    grid402_record_telemetry('cep', 200, true, '0x' . str_repeat('b4', 32), '198.51.100.88', 0.0050, 'paid', ['payment_source' => 'tx_hash']); // UNKNOWN (negative cached)

    $m = grid402_get_metrics();
    t_assert('country_revenue agrega apenas faturamentos reais (BR=0.0500, US=0.0100, DE=0.0050, UNKNOWN=0.0050)',
        ($m['country_revenue']['BR']['paid'] ?? 0) === 1
        && abs((float) ($m['country_revenue']['BR']['usdc'] ?? 0) - 0.0500) < 0.0001
        && ($m['country_revenue']['US']['paid'] ?? 0) === 1
        && ($m['country_revenue']['DE']['paid'] ?? 0) === 1
        && ($m['country_revenue']['UNKNOWN']['paid'] ?? 0) === 1
        && count($m['country_revenue']) === 4
    );
    t_assert('recent_events grava country_code corretamente',
        array_key_exists('country_code', $m['recent_events'][0] ?? [])
        && ($m['recent_events'][0]['country_code'] === null)
        && ($m['recent_events'][1]['country_code'] ?? '') === 'DE'
        && ($m['recent_events'][2]['country_code'] ?? '') === 'US'
        && ($m['recent_events'][3]['country_code'] ?? '') === 'BR'
    );

    $analytics = grid402_compute_admin_analytics($m, 5.50);
    $cr = $analytics['country_revenue'] ?? [];
    t_assert('grid402_compute_admin_analytics ordena country_revenue por receita DESC e traz flag/name/share_pct',
        count($cr) === 4
        && ($cr[0]['code'] ?? '') === 'BR'
        && ($cr[0]['flag'] ?? '') === '🇧🇷'
        && ($cr[0]['name'] ?? '') === 'Brasil'
        && abs((float) ($cr[0]['share_pct'] ?? 0) - 71.4) < 0.2
        && ($cr[1]['code'] ?? '') === 'US'
        && ($cr[3]['code'] ?? '') === 'UNKNOWN'
    );
    $hasConsumerCountry = false;
    foreach ($analytics['top_consumers'] ?? [] as $tc) {
        if (($tc['ip_masked'] ?? '') === '200.150.xxx.xxx' && ($tc['country_code'] ?? '') === 'BR') {
            $hasConsumerCountry = true;
        }
    }
    t_assert('top_consumers propaga country_code dos eventos recentes', $hasConsumerCountry);

    // -------------------------------------------------------------------------
    // [3] Notificacao Telegram com Pais e Regra Sem '&'
    // -------------------------------------------------------------------------
    echo "\n[3] Notificacao Telegram com Pais:\n";
    $tgPayloads = [];
    $GLOBALS['grid402_telegram_http_transport'] = static function (string $url, array $payloadArr, string $payloadJson) use (&$tgPayloads): array {
        $tgPayloads[] = ['url' => $url, 'payload' => $payloadArr];
        return ['ok' => true, 'http_code' => 200, 'error' => ''];
    };

    $okTgBr = grid402_notify_telegram_purchase('dossier', 0.0500, '0x' . str_repeat('c1', 32), false, 'base', 'BR');
    $okTgUnk = grid402_notify_telegram_purchase('forex', 0.0050, '0x' . str_repeat('c2', 32), false, 'polygon', null);
    t_assert('Telegram dispara com sucesso para pais BR e nao identificado',
        $okTgBr === true && $okTgUnk === true && count($tgPayloads) === 2
    );
    $txtBr = (string) ($tgPayloads[0]['payload']['text'] ?? '');
    $txtUnk = (string) ($tgPayloads[1]['payload']['text'] ?? '');
    t_assert('Mensagem Telegram inclui linha Pais formatada corretamente',
        str_contains($txtBr, "🌍 *Pais:* 🇧🇷 Brasil (BR)")
        && str_contains($txtUnk, "🌍 *Pais:* 🌐 Não identificado")
    );

    $x402Src = (string) file_get_contents(__DIR__ . '/../public_html/x402.php');
    $posFn = strpos($x402Src, 'function grid402_notify_telegram_purchase');
    $tailFn = $posFn !== false ? substr($x402Src, $posFn) : '';
    t_assert('x402.php nao contem caractere & em grid402_notify_telegram_purchase ou abaixo dela',
        $tailFn !== '' && strpos($tailFn, chr(38)) === false
    );

    // -------------------------------------------------------------------------
    // [4] Mercado Pago: Captura na criacao e preservacao no webhook
    // -------------------------------------------------------------------------
    echo "\n[4] Mercado Pago (criacao -> webhook cross-country):\n";
    $clearServerGeoEnv();
    $GLOBALS['grid402_mp_sandbox_override'] = false;
    $GLOBALS['grid402_mp_token_override'] = 'APP_USR-country-test-token';
    $GLOBALS['grid402_mp_http_transport'] = static function (string $method, string $path, ?array $body, ?string $idemKey): array {
        if ($method === 'POST' && $path === '/v1/payments') {
            return [
                'status' => 201,
                'data' => [
                    'id' => '99887766',
                    'date_of_expiration' => gmdate('Y-m-d\TH:i:s\Z', time() + 1800),
                    'point_of_interaction' => [
                        'transaction_data' => [
                            'qr_code' => '000201PIXTEST',
                            'qr_code_base64' => base64_encode('qr'),
                            'ticket_url' => '',
                        ],
                    ],
                ],
                'error' => '',
            ];
        }
        return ['status' => 404, 'data' => null, 'error' => 'not found'];
    };

    // Comprador cria Pix vindo dos EUA (header servidor confiavel)
    $_SERVER['GEOIP_COUNTRY_CODE'] = 'US';
    $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
    $pixOrder = grid402_mp_create_pix('starter', 'buyer@example.com');
    t_assert('Criacao de ordem Pix captura payer_country_code US',
        !empty($pixOrder['ok']) && !empty($pixOrder['order_id'])
    );

    // Simula webhook do Mercado Pago chegando de outro pais (NL)
    $clearServerGeoEnv();
    $_SERVER['GEOIP_COUNTRY_CODE'] = 'NL';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.25';
    $tgCountBeforeMp = count($tgPayloads);
    $finalMp = grid402_mp_finalize_approved_order((string) $pixOrder['order_id'], '99887766', 12.00, false);
    $tgAfterMp = $tgPayloads[$tgCountBeforeMp]['payload']['text'] ?? '';
    $mAfterMp = grid402_get_metrics();

    t_assert('Finalizacao via webhook preserva US no Telegram mesmo chegando de NL',
        !empty($finalMp['ok'])
        && count($tgPayloads) === ($tgCountBeforeMp + 1)
        && str_contains((string) $tgAfterMp, "🌍 *Pais:* 🇺🇸 Estados Unidos (US)")
    );
    t_assert('Finalizacao Mercado Pago soma 2.0000 USDC em US e grava US em recent_events',
        abs((float) ($mAfterMp['country_revenue']['US']['usdc'] ?? 0) - 2.0100) < 0.0001
        && ($mAfterMp['recent_events'][0]['country_code'] ?? '') === 'US'
    );

    unset($GLOBALS['grid402_mp_http_transport'], $GLOBALS['grid402_mp_sandbox_override'], $GLOBALS['grid402_mp_token_override'], $GLOBALS['grid402_geoip_http_transport'], $GLOBALS['grid402_telegram_http_transport']);
    $clearServerGeoEnv();

    // -------------------------------------------------------------------------
    // [5] HTTP E2E: /mcp (x402 direto vs Session Pass) + /admin (HTML/API/CSV)
    // -------------------------------------------------------------------------
    echo "\n[5] HTTP E2E (/mcp e /admin):\n";

    // Pre-configura admin_auth.json sem 2FA para validar login/stats/CSVs via HTTP
    $adminUser = 'admin_country';
    $adminPass = 'CountrySuite#2026';
    grid402_atomic_write(GRID402_FILE_ADMIN_AUTH, [
        'username' => $adminUser,
        'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]),
        'two_factor_telegram' => false,
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ]);

    // Emite um Session Pass pago para testar consumo MCP sem disparar Telegram
    $passIssued = grid402_issue_session_pass('0x' . str_repeat('d1', 32), 2000000, false);
    $livePassToken = (string) ($passIssued['pass_token'] ?? '');
    t_assert('Session Pass emitido para teste HTTP MCP', str_starts_with($livePassToken, 'pass_g402_'));

    $tgHttpLog = $tmpDir . DIRECTORY_SEPARATOR . 'telegram_http_capture.jsonl';
    $wrapperRouter = $tmpDir . DIRECTORY_SEPARATOR . 'mcp_country_router.php';
    $projectRouter = str_replace('\\', '/', realpath(__DIR__ . '/../public_html/router.php'));
    $walletAddrExport = var_export(GRID402_WALLET_ADDRESS, true);
    $tgHttpLogExport = var_export($tgHttpLog, true);
    $projectRouterExport = var_export($projectRouter, true);

    file_put_contents($wrapperRouter, <<<PHP
<?php
\$GLOBALS['grid402_facilitator_transport'] = static function (string \$url, array \$headers, string \$body, int \$timeout): array {
    \$path = (string) parse_url(\$url, PHP_URL_PATH);
    if (str_ends_with(\$path, '/verify')) {
        return ['status' => 200, 'body' => json_encode(['isValid' => true]), 'error' => null];
    }
    if (str_ends_with(\$path, '/settle')) {
        return ['status' => 200, 'body' => json_encode([
            'success' => true,
            'transaction' => '0x' . str_repeat('e5', 32),
            'network' => 'eip155:8453',
            'payer' => '0x' . str_repeat('ab', 20),
        ]), 'error' => null];
    }
    return ['status' => 404, 'body' => '{}', 'error' => null];
};
\$GLOBALS['grid402_telegram_http_transport'] = static function (string \$url, array \$payloadArr, string \$payloadJson) {
    @file_put_contents({$tgHttpLogExport}, json_encode(\$payloadArr, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    return ['ok' => true, 'http_code' => 200, 'error' => ''];
};
return require {$projectRouterExport};
PHP);

    $server = grid402_start_server([
        'router' => $wrapperRouter,
        'probe' => '/status',
        'env' => [
            'GRID402_STORAGE_DIR' => $tmpDir,
            'GRID402_DISABLE_TELEGRAM' => '0',
            'GRID402_DISABLE_GEOIP_LOOKUP' => '1',
            'GRID402_TRUST_CF_HEADER' => 'true',
            'GRID402_FACILITATOR_MODE' => 'settle',
            'GRID402_FACILITATOR_VERIFY_PATH' => '/v2/x402/verify',
            'GRID402_FACILITATOR_SETTLE_PATH' => '/v2/x402/settle',
            'GRID402_TELEGRAM_BOT_TOKEN' => '123456789:AAH_test_bot_token_for_country_suite',
            'GRID402_TELEGRAM_CHAT_ID' => '987654321',
        ],
    ]);

    try {
        $baseUrl = $server['base_url'];

        // 5.1 MCP pagamento direto x402 com CF-IPCountry: PT -> dispara Telegram com PT e soma em country_revenue['PT']
        $x402Proof = t_payload([
            'auth' => ['value' => '5000'],
            'accepted' => ['amount' => '5000'],
        ]);
        $rMcpDirect = $httpCall('POST', "{$baseUrl}/mcp", [
            'Content-Type: application/json',
            'CF-IPCountry: PT',
            "PAYMENT-SIGNATURE: {$x402Proof}",
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 901,
            'method' => 'tools/call',
            'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
        ]));
        t_assert('MCP direto x402 retorna HTTP 200 sem isError',
            $rMcpDirect['code'] === 200 && empty($rMcpDirect['json']['result']['isError']),
            substr($rMcpDirect['body'], 0, 220)
        );
        $tgLinesAfterDirect = is_file($tgHttpLog) ? file($tgHttpLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $tgDirectDecoded = !empty($tgLinesAfterDirect) ? json_decode((string) end($tgLinesAfterDirect), true) : null;
        t_assert('MCP direto x402 dispara alerta Telegram com Portugal (PT)',
            count($tgLinesAfterDirect) === 1 && str_contains((string) ($tgDirectDecoded['text'] ?? ''), '🇵🇹 Portugal (PT)'),
            json_encode($tgDirectDecoded)
        );

        // 5.2 MCP via Session Pass com CF-IPCountry: FR -> grava country_code FR sem disparar Telegram
        $rMcpPass = $httpCall('POST', "{$baseUrl}/mcp", [
            'Content-Type: application/json',
            'CF-IPCountry: FR',
            "X-Payment: {$livePassToken}",
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 902,
            'method' => 'tools/call',
            'params' => ['name' => 'grid402_holidays', 'arguments' => ['year' => 2026]],
        ]));
        t_assert('MCP Session Pass retorna HTTP 200 sem isError',
            $rMcpPass['code'] === 200 && empty($rMcpPass['json']['result']['isError'])
        );
        $tgLinesAfterPass = is_file($tgHttpLog) ? file($tgHttpLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        t_assert('MCP Session Pass NAO dispara Telegram adicional',
            count($tgLinesAfterPass) === 1
        );

        // 5.3 Login Admin e validacao de /admin HTML, ?api=stats e CSVs
        $cookieFile = $tmpDir . DIRECTORY_SEPARATOR . 'admin_cookies.txt';
        $rLoginGet = $httpCall('GET', "{$baseUrl}/admin", [], null, $cookieFile);
        preg_match('/name="csrf_token"\s+value="([^"]+)"/', $rLoginGet['body'], $mCsrf);
        $csrf = $mCsrf[1] ?? '';
        $rLoginPost = $httpCall('POST', "{$baseUrl}/admin", [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'username' => $adminUser,
            'password' => $adminPass,
            'csrf_token' => $csrf,
        ]), $cookieFile);
        t_assert('Login /admin efetuado com sucesso (HTTP 302)', $rLoginPost['code'] === 302);

        $rDash = $httpCall('GET', "{$baseUrl}/admin", [], null, $cookieFile);
        t_assert('/admin HTML exibe painel Faturamento por País, Brasil (BR), Portugal (PT) e coluna País',
            $rDash['code'] === 200
            && str_contains($rDash['body'], 'Faturamento por País')
            && str_contains($rDash['body'], 'Portugal (PT)')
            && str_contains($rDash['body'], '<th>País</th>')
            && str_contains($rDash['body'], '🇧🇷 BR')
        );

        $rStats = $httpCall('GET', "{$baseUrl}/admin?api=stats", [], null, $cookieFile);
        $statsCr = $rStats['json']['country_revenue'] ?? [];
        $codesInStats = array_column(is_array($statsCr) ? $statsCr : [], 'code');
        t_assert('/admin?api=stats retorna country_revenue com US, BR, PT, FR',
            $rStats['code'] === 200
            && in_array('US', $codesInStats, true)
            && in_array('BR', $codesInStats, true)
            && in_array('PT', $codesInStats, true)
            && in_array('FR', $codesInStats, true)
        );

        $rCsvRecent = $httpCall('GET', "{$baseUrl}/admin?api=export_csv", [], null, $cookieFile);
        $recentLines = array_values(array_filter(explode("\n", trim(ltrim($rCsvRecent['body'], "\xEF\xBB\xBF"))), static fn($l) => trim($l) !== ''));
        $recentCols = str_getcsv($recentLines[0] ?? '', ';');
        t_assert('/admin?api=export_csv contem 13 colunas com Pais e codigo PT',
            $rCsvRecent['code'] === 200
            && count($recentCols) === 13
            && ($recentCols[12] ?? '') === 'Pais'
            && str_contains($rCsvRecent['body'], ';PT')
        );

        $rCsvPaid = $httpCall('GET', "{$baseUrl}/admin?api=export_csv&scope=paid", [], null, $cookieFile);
        $paidLines = array_values(array_filter(explode("\n", trim(ltrim($rCsvPaid['body'], "\xEF\xBB\xBF"))), static fn($l) => trim($l) !== ''));
        $paidCols = str_getcsv($paidLines[0] ?? '', ';');
        t_assert('/admin?api=export_csv&scope=paid contem 13 colunas com Pais e registros PT/FR/US',
            $rCsvPaid['code'] === 200
            && count($paidCols) === 13
            && ($paidCols[12] ?? '') === 'Pais'
            && str_contains($rCsvPaid['body'], ';PT')
            && str_contains($rCsvPaid['body'], ';FR')
            && str_contains($rCsvPaid['body'], ';US')
        );
    } finally {
        grid402_stop_server($server);
    }
} finally {
    $cleanupRecursive($tmpDir);
}

t_finish('TESTES DE PAIS DE ORIGEM NO ADMIN E TELEGRAM');
