<?php
/**
 * Grid402 - Checkout Mercado Pago (Pix Transparente + Checkout Pro)
 *
 * Permite compra de Session Passes (pass_g402_...) em BRL via API REST do Mercado Pago,
 * com persistencia atomica via flock(LOCK_EX), verificacao HMAC de webhook,
 * consulta ativa GET /v1/payments/{id} e emissao estritamente idempotente.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/pass.php';

/**
 * Tabela oficial de pacotes em BRL vinculados aos Session Passes.
 */
function grid402_mp_packages(): array {
    return [
        'starter' => [
            'tier' => 'starter',
            'label' => 'Starter Pass',
            'amount_brl' => 12.00,
            'raw_usdc_units' => 2000000, // 2 USDC -> 4.000 creditos
            'credits' => 4000,
            'ttl_days' => 30,
        ],
        'pro' => [
            'tier' => 'pro',
            'label' => 'Pro / Agent Pass',
            'amount_brl' => 29.00,
            'raw_usdc_units' => 5000000, // 5 USDC -> 10.000 creditos
            'credits' => 10000,
            'ttl_days' => 30,
        ],
        'fleet' => [
            'tier' => 'fleet',
            'label' => 'Fleet / Enterprise Pass',
            'amount_brl' => 115.00,
            'raw_usdc_units' => 20000000, // 20 USDC -> 50.000 + 10.000 bonus
            'credits' => 60000,
            'ttl_days' => 60,
        ],
    ];
}

function grid402_mp_is_sandbox(): bool {
    if (isset($GLOBALS['grid402_mp_sandbox_override'])) {
        return (bool) $GLOBALS['grid402_mp_sandbox_override'];
    }
    return defined('GRID402_MP_SANDBOX') ? (bool) GRID402_MP_SANDBOX : false;
}

function grid402_mp_access_token(): string {
    if (isset($GLOBALS['grid402_mp_token_override'])) {
        return trim((string) $GLOBALS['grid402_mp_token_override']);
    }
    return defined('GRID402_MP_ACCESS_TOKEN') ? trim((string) GRID402_MP_ACCESS_TOKEN) : '';
}

function grid402_mp_webhook_secret(): string {
    if (isset($GLOBALS['grid402_mp_webhook_secret_override'])) {
        return trim((string) $GLOBALS['grid402_mp_webhook_secret_override']);
    }
    return defined('GRID402_MP_WEBHOOK_SECRET') ? trim((string) GRID402_MP_WEBHOOK_SECRET) : '';
}

function grid402_mp_orders_file(): string {
    if (defined('GRID402_FILE_MP_ORDERS')) {
        return GRID402_FILE_MP_ORDERS;
    }
    $dir = defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR : (dirname(__DIR__) . '/private_data');
    return $dir . '/mp_orders.json';
}

/**
 * Executa operacao atomica sobre mp_orders.json protegido por flock(LOCK_EX).
 * O callback recebe (&$store) e retorna [mixed $result, bool $dirty].
 */
function grid402_mp_with_orders(callable $callback): mixed {
    $file = grid402_mp_orders_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return null;
    }

    $result = null;
    if (flock($fp, LOCK_EX)) {
        clearstatcache(true, $file);
        $size = (int) @filesize($file);
        $raw = $size > 0 ? (string) fread($fp, $size) : '';
        $store = $raw !== '' ? (@json_decode($raw, true) ?: []) : [];
        if (!isset($store['orders']) || !is_array($store['orders'])) {
            $store['orders'] = [];
        }
        if (!isset($store['payment_map']) || !is_array($store['payment_map'])) {
            $store['payment_map'] = [];
        }

        [$result, $dirty] = $callback($store);

        if ($dirty) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $result;
}

/**
 * Cliente HTTP para a API REST do Mercado Pago com suporte a transporte de teste.
 * Retorna ['status' => int, 'data' => array, 'error' => string].
 */
function grid402_mp_http(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array {
    if (is_callable($GLOBALS['grid402_mp_http_transport'] ?? null)) {
        return ($GLOBALS['grid402_mp_http_transport'])($method, $path, $body, $idempotencyKey);
    }

    $token = grid402_mp_access_token();
    if ($token === '') {
        return ['status' => 0, 'data' => [], 'error' => 'MP_ACCESS_TOKEN ausente.'];
    }

    $base = defined('GRID402_MP_API_BASE') ? GRID402_MP_API_BASE : 'https://api.mercadopago.com';
    $url = rtrim($base, '/') . $path;

    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'Grid402-MercadoPago-Client/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = (string) json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw) || $status === 0) {
        return ['status' => 0, 'data' => [], 'error' => $curlErr !== '' ? $curlErr : 'Falha de rede ao consultar Mercado Pago.'];
    }

    $decoded = @json_decode($raw, true);
    return [
        'status' => $status,
        'data' => is_array($decoded) ? $decoded : [],
        'error' => '',
    ];
}

/**
 * Gera QR Code SVG simples em base64 para o modo sandbox sem dependencias externas.
 */
function grid402_mp_sandbox_qr_base64(string $orderId, float $amountBrl): string {
    $label = htmlspecialchars($orderId . ' | R$ ' . number_format($amountBrl, 2, ',', '.'), ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220" viewBox="0 0 220 220">'
        . '<rect width="220" height="220" fill="#ffffff"/>'
        . '<rect x="16" y="16" width="52" height="52" fill="#060B19"/><rect x="24" y="24" width="36" height="36" fill="#fff"/><rect x="32" y="32" width="20" height="20" fill="#060B19"/>'
        . '<rect x="152" y="16" width="52" height="52" fill="#060B19"/><rect x="160" y="24" width="36" height="36" fill="#fff"/><rect x="168" y="32" width="20" height="20" fill="#060B19"/>'
        . '<rect x="16" y="152" width="52" height="52" fill="#060B19"/><rect x="24" y="160" width="36" height="36" fill="#fff"/><rect x="32" y="168" width="20" height="20" fill="#060B19"/>'
        . '<rect x="88" y="88" width="44" height="44" fill="#0052FF"/>'
        . '<text x="110" y="196" font-family="monospace" font-size="9" text-anchor="middle" fill="#060B19">SANDBOX PIX</text>'
        . '<title>' . $label . '</title></svg>';
    return base64_encode($svg);
}

/**
 * Cria cobranca Pix transparente para o pacote selecionado.
 */
function grid402_mp_create_pix(string $tier, string $email = ''): array {
    $packages = grid402_mp_packages();
    $tierKey = strtolower(trim($tier));
    if (!isset($packages[$tierKey])) {
        return ['ok' => false, 'code' => 400, 'error' => 'InvalidTier', 'message' => 'Pacote invalido. Escolha starter, pro ou fleet.'];
    }
    $pkg = $packages[$tierKey];

    $sandbox = grid402_mp_is_sandbox();
    $hasTransport = is_callable($GLOBALS['grid402_mp_http_transport'] ?? null);
    if (!$sandbox && !$hasTransport && grid402_mp_access_token() === '') {
        return [
            'ok' => false,
            'code' => 503,
            'error' => 'MercadoPagoNotConfigured',
            'message' => 'Checkout em BRL (Mercado Pago) ainda nao configurado neste servidor. Utilize pagamento Web3 USDC ou habilite o modo sandbox.',
        ];
    }

    $payerEmail = filter_var(trim($email), FILTER_VALIDATE_EMAIL) ?: 'pagador@grid402.online';
    $orderId = 'mp_ord_' . bin2hex(random_bytes(8));
    $domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
    $now = time();

    if ($sandbox && !$hasTransport) {
        $qrCode = '00020126580014BR.GOV.BCB.PIX0136grid402-sandbox-' . $orderId . '5204000053039865405' . number_format($pkg['amount_brl'], 2, '.', '') . '5802BR5907GRID4026009SAO PAULO62070503***6304ABCD';
        $qrBase64 = grid402_mp_sandbox_qr_base64($orderId, (float) $pkg['amount_brl']);
        $qrMime = 'image/svg+xml';
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', $now + 1800);
        $mpPaymentId = 'sbx_' . bin2hex(random_bytes(4));
        $ticketUrl = '';
    } else {
        $payload = [
            'transaction_amount' => (float) $pkg['amount_brl'],
            'description' => 'Grid402 ' . $pkg['label'] . ' (' . number_format($pkg['credits'], 0, '', '.') . ' req)',
            'payment_method_id' => 'pix',
            'external_reference' => $orderId,
            'notification_url' => $domain . '/api/v1/pass/mp-webhook',
            'payer' => ['email' => $payerEmail],
        ];
        $resp = grid402_mp_http('POST', '/v1/payments', $payload, $orderId);
        if ($resp['status'] < 200 || $resp['status'] >= 300 || empty($resp['data']['id'])) {
            $detail = (string) ($resp['data']['message'] ?? $resp['error'] ?? 'Erro ao gerar Pix no Mercado Pago.');
            return ['ok' => false, 'code' => 502, 'error' => 'MercadoPagoGatewayError', 'message' => $detail];
        }

        $txData = $resp['data']['point_of_interaction']['transaction_data'] ?? [];
        $qrCode = (string) ($txData['qr_code'] ?? '');
        $qrBase64 = (string) ($txData['qr_code_base64'] ?? '');
        $qrMime = 'image/png';
        $ticketUrl = (string) ($txData['ticket_url'] ?? '');
        $expiresAt = (string) ($resp['data']['date_of_expiration'] ?? gmdate('Y-m-d\TH:i:s\Z', $now + 1800));
        $mpPaymentId = (string) $resp['data']['id'];
    }

    $orderRecord = [
        'order_id' => $orderId,
        'tier' => $pkg['tier'],
        'label' => $pkg['label'],
        'amount_brl' => (float) $pkg['amount_brl'],
        'raw_usdc_units' => (int) $pkg['raw_usdc_units'],
        'credits' => (int) $pkg['credits'],
        'ttl_days' => (int) $pkg['ttl_days'],
        'method' => 'pix',
        'status' => 'pending',
        'sandbox' => $sandbox,
        'mp_payment_id' => $mpPaymentId,
        'mp_preference_id' => null,
        'pass_token' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    grid402_mp_with_orders(static function (array &$store) use ($orderId, $orderRecord): array {
        $store['orders'][$orderId] = $orderRecord;
        return [true, true];
    });

    return [
        'ok' => true,
        'code' => 200,
        'order_id' => $orderId,
        'tier' => $pkg['tier'],
        'label' => $pkg['label'],
        'amount_brl' => (float) $pkg['amount_brl'],
        'credits' => (int) $pkg['credits'],
        'ttl_days' => (int) $pkg['ttl_days'],
        'status' => 'pending',
        'sandbox' => $sandbox,
        'mp_payment_id' => $mpPaymentId,
        'qr_code' => $qrCode,
        'qr_code_base64' => $qrBase64,
        'qr_mime' => $qrMime,
        'ticket_url' => $ticketUrl,
        'expires_at' => $expiresAt,
    ];
}

/**
 * Cria preferencia do Checkout Pro (Cartao / Saldo MP) para o pacote selecionado.
 */
function grid402_mp_create_checkout_pro(string $tier, string $email = ''): array {
    $packages = grid402_mp_packages();
    $tierKey = strtolower(trim($tier));
    if (!isset($packages[$tierKey])) {
        return ['ok' => false, 'code' => 400, 'error' => 'InvalidTier', 'message' => 'Pacote invalido. Escolha starter, pro ou fleet.'];
    }
    $pkg = $packages[$tierKey];

    $sandbox = grid402_mp_is_sandbox();
    $hasTransport = is_callable($GLOBALS['grid402_mp_http_transport'] ?? null);
    if (!$sandbox && !$hasTransport && grid402_mp_access_token() === '') {
        return [
            'ok' => false,
            'code' => 503,
            'error' => 'MercadoPagoNotConfigured',
            'message' => 'Checkout em BRL (Mercado Pago) ainda nao configurado neste servidor. Utilize pagamento Web3 USDC ou habilite o modo sandbox.',
        ];
    }

    $orderId = 'mp_ord_' . bin2hex(random_bytes(8));
    $domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
    $backUrl = $domain . '/pass?mp_order=' . urlencode($orderId);
    $now = time();

    if ($sandbox && !$hasTransport) {
        $prefId = 'sbx_pref_' . bin2hex(random_bytes(4));
        $checkoutUrl = $backUrl . '&sandbox_approve=1';
    } else {
        $prefPayload = [
            'items' => [[
                'id' => 'grid402_' . $pkg['tier'],
                'title' => 'Grid402 ' . $pkg['label'] . ' (' . $pkg['credits'] . ' req)',
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => (float) $pkg['amount_brl'],
            ]],
            'external_reference' => $orderId,
            'notification_url' => $domain . '/api/v1/pass/mp-webhook',
            'back_urls' => [
                'success' => $backUrl,
                'pending' => $backUrl,
                'failure' => $backUrl,
            ],
            'auto_return' => 'approved',
        ];
        $payerEmail = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if ($payerEmail) {
            $prefPayload['payer'] = ['email' => $payerEmail];
        }

        $resp = grid402_mp_http('POST', '/checkout/preferences', $prefPayload, $orderId);
        if ($resp['status'] < 200 || $resp['status'] >= 300 || empty($resp['data']['id'])) {
            $detail = (string) ($resp['data']['message'] ?? $resp['error'] ?? 'Erro ao criar preferencia Checkout Pro.');
            return ['ok' => false, 'code' => 502, 'error' => 'MercadoPagoGatewayError', 'message' => $detail];
        }
        $prefId = (string) $resp['data']['id'];
        $checkoutUrl = (string) ($sandbox ? ($resp['data']['sandbox_init_point'] ?? $resp['data']['init_point'] ?? '') : ($resp['data']['init_point'] ?? $resp['data']['sandbox_init_point'] ?? ''));
    }

    $orderRecord = [
        'order_id' => $orderId,
        'tier' => $pkg['tier'],
        'label' => $pkg['label'],
        'amount_brl' => (float) $pkg['amount_brl'],
        'raw_usdc_units' => (int) $pkg['raw_usdc_units'],
        'credits' => (int) $pkg['credits'],
        'ttl_days' => (int) $pkg['ttl_days'],
        'method' => 'checkout_pro',
        'status' => 'pending',
        'sandbox' => $sandbox,
        'mp_payment_id' => null,
        'mp_preference_id' => $prefId,
        'pass_token' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    grid402_mp_with_orders(static function (array &$store) use ($orderId, $orderRecord): array {
        $store['orders'][$orderId] = $orderRecord;
        return [true, true];
    });

    return [
        'ok' => true,
        'code' => 200,
        'order_id' => $orderId,
        'tier' => $pkg['tier'],
        'label' => $pkg['label'],
        'amount_brl' => (float) $pkg['amount_brl'],
        'credits' => (int) $pkg['credits'],
        'status' => 'pending',
        'sandbox' => $sandbox,
        'preference_id' => $prefId,
        'checkout_url' => $checkoutUrl,
    ];
}

/**
 * Valida assinatura HMAC x-signature dos webhooks do Mercado Pago.
 */
function grid402_mp_verify_webhook_signature(string $xSignature, string $xRequestId, string $dataId, string $secret): bool {
    if ($secret === '') {
        return true;
    }
    if ($xSignature === '' || $dataId === '') {
        return false;
    }

    $ts = '';
    $v1 = '';
    foreach (explode(',', $xSignature) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        $k = strtolower(trim($kv[0]));
        $v = trim($kv[1]);
        if ($k === 'ts') {
            $ts = $v;
        } elseif ($k === 'v1') {
            $v1 = strtolower($v);
        }
    }

    if ($ts === '' || $v1 === '') {
        return false;
    }

    $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
    $expected = hash_hmac('sha256', $manifest, $secret);
    return hash_equals($expected, $v1);
}

/**
 * Finaliza ordem aprovada e emite Session Pass de forma estritamente idempotente.
 */
function grid402_mp_finalize_approved_order(string $orderId, string $mpPaymentId, float $paidAmount, bool $isSandbox): array {
    return grid402_mp_with_orders(static function (array &$store) use ($orderId, $mpPaymentId, $paidAmount, $isSandbox): array {
        if (!isset($store['orders'][$orderId])) {
            return [['ok' => false, 'code' => 404, 'error' => 'OrderNotFound', 'message' => 'Ordem nao encontrada.'], false];
        }

        $order = $store['orders'][$orderId];
        if (!empty($order['pass_token']) && $order['status'] === 'approved') {
            return [[
                'ok' => true,
                'code' => 200,
                'order_id' => $orderId,
                'status' => 'approved',
                'pass_token' => $order['pass_token'],
                'tier' => $order['tier'],
                'credits' => $order['credits'],
                'ttl_days' => $order['ttl_days'],
                'sandbox' => (bool) ($order['sandbox'] ?? $isSandbox),
                'idempotent' => true,
            ], false];
        }

        // Protecao contra reutilizacao de um mesmo mp_payment_id em ordens diferentes
        if ($mpPaymentId !== '' && isset($store['payment_map'][$mpPaymentId])) {
            $existingOrderId = (string) $store['payment_map'][$mpPaymentId];
            if ($existingOrderId !== $orderId && !empty($store['orders'][$existingOrderId]['pass_token'])) {
                $existing = $store['orders'][$existingOrderId];
                return [[
                    'ok' => true,
                    'code' => 200,
                    'order_id' => $existingOrderId,
                    'status' => 'approved',
                    'pass_token' => $existing['pass_token'],
                    'tier' => $existing['tier'],
                    'credits' => $existing['credits'],
                    'ttl_days' => $existing['ttl_days'],
                    'sandbox' => (bool) ($existing['sandbox'] ?? $isSandbox),
                    'idempotent' => true,
                ], false];
            }
        }

        $expectedBrl = (float) ($order['amount_brl'] ?? 0);
        if ($paidAmount + 0.01 < $expectedBrl) {
            return [['ok' => false, 'code' => 402, 'error' => 'InsufficientAmount', 'message' => 'Valor pago inferior ao valor do pacote.'], false];
        }

        $issued = grid402_issue_session_pass('mp_pay_' . $mpPaymentId, (int) $order['raw_usdc_units'], $isSandbox);
        if (empty($issued['pass_token'])) {
            return [['ok' => false, 'code' => 500, 'error' => 'PassIssuanceFailed', 'message' => 'Falha ao emitir Session Pass.'], false];
        }

        $now = time();
        $store['orders'][$orderId]['status'] = 'approved';
        $store['orders'][$orderId]['mp_payment_id'] = $mpPaymentId;
        $store['orders'][$orderId]['pass_token'] = $issued['pass_token'];
        $store['orders'][$orderId]['sandbox'] = (bool) ($order['sandbox'] ?? $isSandbox);
        $store['orders'][$orderId]['updated_at'] = $now;
        if ($mpPaymentId !== '') {
            $store['payment_map'][$mpPaymentId] = $orderId;
        }

        return [[
            'ok' => true,
            'code' => 200,
            'order_id' => $orderId,
            'status' => 'approved',
            'pass_token' => $issued['pass_token'],
            'tier' => $order['tier'],
            'credits' => $issued['credits'] ?? $order['credits'],
            'ttl_days' => $order['ttl_days'],
            'expires_at' => $issued['expires_at'] ?? null,
            'sandbox' => (bool) ($order['sandbox'] ?? $isSandbox),
            'idempotent' => false,
        ], true];
    });
}

/**
 * Consulta pagamento no Mercado Pago e sincroniza ordem local se aprovado.
 */
function grid402_mp_sync_payment(string $mpPaymentId, ?string $expectedOrderId = null): array {
    $resp = grid402_mp_http('GET', '/v1/payments/' . rawurlencode($mpPaymentId));
    if ($resp['status'] < 200 || $resp['status'] >= 300 || empty($resp['data'])) {
        return ['ok' => false, 'code' => 502, 'error' => 'MercadoPagoLookupFailed', 'message' => 'Nao foi possivel consultar o pagamento no Mercado Pago.'];
    }

    $payment = $resp['data'];
    $mpStatus = strtolower((string) ($payment['status'] ?? 'pending'));
    $extRef = (string) ($payment['external_reference'] ?? '');
    $orderId = $extRef !== '' ? $extRef : (string) $expectedOrderId;
    if ($orderId === '') {
        return ['ok' => false, 'code' => 404, 'error' => 'OrderReferenceMissing', 'message' => 'Pagamento sem external_reference correspondente.'];
    }
    if ($expectedOrderId !== null && $expectedOrderId !== '' && $extRef !== '' && $extRef !== $expectedOrderId) {
        return ['ok' => false, 'code' => 400, 'error' => 'OrderMismatch', 'message' => 'Pagamento nao pertence a ordem informada.'];
    }

    if ($mpStatus === 'approved') {
        $paidAmount = (float) ($payment['transaction_amount'] ?? 0);
        $isLive = isset($payment['live_mode']) ? !$payment['live_mode'] : grid402_mp_is_sandbox();
        return grid402_mp_finalize_approved_order($orderId, (string) ($payment['id'] ?? $mpPaymentId), $paidAmount, $isLive);
    }

    if (in_array($mpStatus, ['rejected', 'cancelled', 'refunded', 'charged_back'], true)) {
        grid402_mp_with_orders(static function (array &$store) use ($orderId, $mpStatus, $mpPaymentId): array {
            if (isset($store['orders'][$orderId]) && $store['orders'][$orderId]['status'] === 'pending') {
                $store['orders'][$orderId]['status'] = $mpStatus;
                $store['orders'][$orderId]['mp_payment_id'] = $mpPaymentId;
                $store['orders'][$orderId]['updated_at'] = time();
                return [true, true];
            }
            return [false, false];
        });
    }

    return [
        'ok' => true,
        'code' => 200,
        'order_id' => $orderId,
        'status' => $mpStatus,
        'pass_token' => null,
    ];
}

/**
 * Processa notificacao de webhook do Mercado Pago.
 */
function grid402_mp_handle_webhook(array $headers, string $rawBody, array $query): array {
    $normHeaders = [];
    foreach ($headers as $k => $v) {
        $normHeaders[strtolower((string) $k)] = (string) $v;
    }

    $body = @json_decode($rawBody, true);
    $body = is_array($body) ? $body : [];

    $dataId = (string) ($query['data_id'] ?? $query['data.id'] ?? $body['data']['id'] ?? $query['id'] ?? '');
    $topic = strtolower((string) ($query['type'] ?? $query['topic'] ?? $body['type'] ?? $body['action'] ?? ''));

    $secret = grid402_mp_webhook_secret();
    if ($secret !== '') {
        $xSig = $normHeaders['x-signature'] ?? '';
        $xReqId = $normHeaders['x-request-id'] ?? '';
        if (!grid402_mp_verify_webhook_signature($xSig, $xReqId, $dataId, $secret)) {
            return ['ok' => false, 'code' => 401, 'error' => 'InvalidSignature', 'message' => 'Assinatura HMAC do webhook invalida.'];
        }
    }

    if ($dataId === '' || ($topic !== '' && !str_contains($topic, 'payment'))) {
        return ['ok' => true, 'code' => 200, 'ignored' => true];
    }

    return grid402_mp_sync_payment($dataId);
}

/**
 * Consulta status da ordem para polling no frontend ou retorno do Checkout Pro.
 */
function grid402_mp_get_order_status(string $orderId, ?string $paymentIdHint = null): array {
    $orderId = trim($orderId);
    if ($orderId === '') {
        return ['ok' => false, 'code' => 400, 'error' => 'MissingOrderId', 'message' => 'Parametro order_id obrigatorio.'];
    }

    $order = grid402_mp_with_orders(static function (array &$store) use ($orderId): array {
        return [$store['orders'][$orderId] ?? null, false];
    });

    if (!$order) {
        return ['ok' => false, 'code' => 404, 'error' => 'OrderNotFound', 'message' => 'Ordem nao encontrada.'];
    }

    if ($order['status'] === 'approved' && !empty($order['pass_token'])) {
        return [
            'ok' => true,
            'code' => 200,
            'order_id' => $order['order_id'],
            'tier' => $order['tier'],
            'label' => $order['label'],
            'amount_brl' => $order['amount_brl'],
            'credits' => $order['credits'],
            'ttl_days' => $order['ttl_days'],
            'status' => 'approved',
            'sandbox' => (bool) ($order['sandbox'] ?? false),
            'pass_token' => $order['pass_token'],
        ];
    }

    $pid = trim((string) ($paymentIdHint ?? $order['mp_payment_id'] ?? ''));
    $hasTransport = is_callable($GLOBALS['grid402_mp_http_transport'] ?? null);
    if ($order['status'] === 'pending' && $pid !== '' && (!empty($hasTransport) || empty($order['sandbox']))) {
        $sync = grid402_mp_sync_payment($pid, $orderId);
        if (!empty($sync['pass_token'])) {
            return $sync;
        }
        if (isset($sync['status'])) {
            $order['status'] = $sync['status'];
        }
    }

    return [
        'ok' => true,
        'code' => 200,
        'order_id' => $order['order_id'],
        'tier' => $order['tier'],
        'label' => $order['label'],
        'amount_brl' => $order['amount_brl'],
        'credits' => $order['credits'],
        'ttl_days' => $order['ttl_days'],
        'status' => $order['status'],
        'sandbox' => (bool) ($order['sandbox'] ?? false),
        'pass_token' => $order['pass_token'] ?? null,
    ];
}

/**
 * Aprova ordem simulada apenas quando GRID402_MP_SANDBOX=true.
 */
function grid402_mp_sandbox_approve(string $orderId): array {
    if (!grid402_mp_is_sandbox()) {
        return ['ok' => false, 'code' => 403, 'error' => 'SandboxDisabled', 'message' => 'Aprovacao simulada disponivel apenas com GRID402_MP_SANDBOX=true.'];
    }

    $orderId = trim($orderId);
    $order = grid402_mp_with_orders(static function (array &$store) use ($orderId): array {
        return [$store['orders'][$orderId] ?? null, false];
    });
    if (!$order) {
        return ['ok' => false, 'code' => 404, 'error' => 'OrderNotFound', 'message' => 'Ordem nao encontrada.'];
    }

    return grid402_mp_finalize_approved_order(
        $orderId,
        'sandbox_' . $orderId,
        (float) $order['amount_brl'],
        true
    );
}
