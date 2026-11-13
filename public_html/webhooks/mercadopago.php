<?php
// webhooks/mercadopago.php - Webhook de confirmação de pagamento Pix (Mercado Pago) - Alavanca 2
// Configura no painel do MP: URL = https://grid402.online/webhooks/mercadopago, eventos = payment.
declare(strict_types=1);
define('GRID402_EXEC', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/sales.php';
require_once __DIR__ . '/../services/pass.php';

// 1. Ler payload
$raw = (string) file_get_contents('php://input');
$payload = @json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'InvalidPayload']);
    exit;
}

// 2. Validar assinatura HMAC (MP envia x-signature: ts=<ts>,v1=<hmac>)
$secret = (string) grid402_env('MERCADOPAGO_WEBHOOK_SECRET', '');
$signatureHeader = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
$requestId = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
$signatureValid = false;
if ($secret !== '' && $signatureHeader !== '' && isset($payload['data']['id'])) {
    $parts = [];
    foreach (explode(',', $signatureHeader) as $p) {
        $kv = explode('=', $p, 2);
        if (count($kv) === 2) {
            $parts[trim($kv[0])] = trim($kv[1]);
        }
    }
    $ts = $parts['ts'] ?? '';
    $v1 = $parts['v1'] ?? '';
    $dataId = (string) $payload['data']['id'];
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $expected = hash_hmac('sha256', $manifest, $secret);
    if (hash_equals($expected, $v1)) {
        $signatureValid = true;
    }
}
if (!$signatureValid && $secret !== '') {
    http_response_code(401);
    echo json_encode(['error' => 'InvalidSignature']);
    exit;
}

// 3. Tipo de evento
$type = (string) ($payload['type'] ?? $payload['action'] ?? '');
if ($type !== 'payment' && $type !== 'payment.created' && $type !== 'payment.updated') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'ignored' => $type]);
    exit;
}

$paymentId = (string) ($payload['data']['id'] ?? '');
if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'MissingPaymentId']);
    exit;
}

// 4. Consultar o pagamento na API do MP (não confiar no payload)
$accessToken = (string) grid402_env('MERCADOPAGO_ACCESS_TOKEN', '');
if ($accessToken === '') {
    http_response_code(500);
    echo json_encode(['error' => 'MercadoPagoNotConfigured']);
    exit;
}
$ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
]);
$resp = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$payment = @json_decode((string) $resp, true);
if ($httpCode !== 200 || !is_array($payment)) {
    http_response_code(502);
    echo json_encode(['error' => 'MercadoPagoApiError', 'http' => $httpCode]);
    exit;
}

$status = (string) ($payment['status'] ?? '');
if ($status !== 'approved') {
    // Pagamento pendente/recusado — apenas ack, sem emitir passe
    http_response_code(200);
    echo json_encode(['ok' => true, 'status' => $status]);
    exit;
}

// 5. Mapear external_reference -> order_id (formato esperado: "grid402:<order_id>")
$externalRef = (string) ($payment['external_reference'] ?? '');
$orderId = '';
if (str_starts_with($externalRef, 'grid402:')) {
    $orderId = substr($externalRef, strlen('grid402:'));
}
if ($orderId === '') {
    // Sem order_id conhecido — cria um pedido on-the-fly a partir do valor pago
    $amount = (float) ($payment['transaction_amount'] ?? 0);
    $planId = 'starter';
    $credits = 20000;
    $plans = grid402_plans();
    // seleciona o plano mais próximo pelo valor
    $best = null;
    foreach ($plans as $p) {
        if ($best === null || abs($p['price_brl'] - $amount) < abs($best['price_brl'] - $amount)) {
            $best = $p;
        }
    }
    if ($best) {
        $planId = $best['id'];
        $credits = $best['credits'];
    }
    $email = (string) ($payment['payer']['email'] ?? '');
    $order = grid402_sales_create($planId, $amount, $email, $credits, 'pix');
    $orderId = $order['order_id'];
}

// 6. Marcar pago e emitir passe (idempotente)
$result = grid402_sales_mark_paid($orderId, 'pix', $paymentId);
if (empty($result['ok'])) {
    http_response_code(500);
    echo json_encode(['error' => 'PassIssuanceFailed', 'detail' => $result]);
    exit;
}

// 7. (Opcional) enviar e-mail com o token — template em docs/templates/email_session_pass.html
// TODO: integrar mailer (Hostinger mail() ou SMTP externo)

http_response_code(200);
echo json_encode([
    'ok' => true,
    'order_id' => $orderId,
    'pass_token' => $result['order']['pass_token'] ?? null,
    'credits' => $result['order']['credits'] ?? null,
]);
exit;