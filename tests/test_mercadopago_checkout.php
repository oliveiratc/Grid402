<?php
/**
 * Testes E2E do Checkout Mercado Pago (Pix + Checkout Pro + Webhook + Idempotencia + Sandbox)
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
$dir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/services/mercadopago.php';

echo "=== TESTES DE CHECKOUT MERCADO PAGO (SESSION PASS) ===\n\n";

echo "[1] Renderizacao da pagina /pass com pacotes BRL:\n";
ob_start();
require __DIR__ . '/../public_html/pass.php';
$passHtml = (string) ob_get_clean();
t_assert('Exibe Starter R$ 12,00', str_contains($passHtml, '12,00'));
t_assert('Exibe Pro R$ 29,00', str_contains($passHtml, '29,00'));
t_assert('Exibe Fleet R$ 115,00', str_contains($passHtml, '115,00'));
t_assert('Exibe botoes Pix e Cartao', str_contains($passHtml, 'Pagar com Pix') && str_contains($passHtml, 'Pagar com Cartão'));

echo "\n[2] Gateway nao configurado fora do sandbox -> 503:\n";
$GLOBALS['grid402_mp_sandbox_override'] = false;
$GLOBALS['grid402_mp_token_override'] = '';
$GLOBALS['grid402_mp_http_transport'] = null;
$noCfg = grid402_mp_create_pix('pro');
t_assert('Retorna HTTP 503 sem token e sem sandbox', $noCfg['ok'] === false && $noCfg['code'] === 503);

echo "\n[3] Pacote invalido -> 400:\n";
$badTier = grid402_mp_create_pix('inexistente');
t_assert('Retorna HTTP 400 para tier inexistente', $badTier['ok'] === false && $badTier['code'] === 400);

echo "\n[4] Pix transparente com transporte mockado:\n";
$captured = [];
$GLOBALS['grid402_mp_token_override'] = 'APP_USR-test-token';
$GLOBALS['grid402_mp_http_transport'] = static function (string $method, string $path, ?array $body, ?string $idemKey) use (&$captured): array {
    $captured[] = compact('method', 'path', 'body', 'idemKey');
    if ($method === 'POST' && $path === '/v1/payments') {
        return [
            'status' => 201,
            'data' => [
                'id' => 99887766,
                'status' => 'pending',
                'date_of_expiration' => '2026-11-15T12:00:00Z',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '000201PIXMOCK',
                        'qr_code_base64' => base64_encode('PNG_MOCK'),
                        'ticket_url' => 'https://mp.test/ticket/99887766',
                    ],
                ],
            ],
            'error' => '',
        ];
    }
    if ($method === 'POST' && $path === '/checkout/preferences') {
        return [
            'status' => 201,
            'data' => [
                'id' => 'pref_mock_123',
                'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_mock_123',
            ],
            'error' => '',
        ];
    }
    if ($method === 'GET' && $path === '/v1/payments/99887766') {
        $lastPixOrder = $captured[0]['body']['external_reference'] ?? '';
        return [
            'status' => 200,
            'data' => [
                'id' => 99887766,
                'status' => 'approved',
                'transaction_amount' => 29.00,
                'external_reference' => $lastPixOrder,
                'live_mode' => true,
            ],
            'error' => '',
        ];
    }
    return ['status' => 404, 'data' => [], 'error' => 'Not found'];
};

$pixRes = grid402_mp_create_pix('pro', 'dev@empresa.com.br');
t_assert('Pix criado com sucesso (200)', $pixRes['ok'] === true && $pixRes['code'] === 200);
t_assert('Valor BRL e 29.00 e qr_code presente', $pixRes['amount_brl'] === 29.0 && $pixRes['qr_code'] === '000201PIXMOCK');

echo "\n[5] Checkout Pro (Cartao) para Fleet:\n";
$prefRes = grid402_mp_create_checkout_pro('fleet');
t_assert('Checkout Pro criado com checkout_url', $prefRes['ok'] === true && str_contains($prefRes['checkout_url'], 'pref_mock_123'));
$prefBody = $captured[1]['body'] ?? [];
t_assert('Preferencia envia unit_price 115 BRL', ($prefBody['items'][0]['unit_price'] ?? 0) === 115.0 && ($prefBody['items'][0]['currency_id'] ?? '') === 'BRL');
t_assert('Preferencia envia external_reference igual ao order_id', ($prefBody['external_reference'] ?? '') === $prefRes['order_id']);

echo "\n[6] Webhook HMAC invalido -> 401 e valido -> emite pass_g402_...:\n";
$secret = 'whsec_teste_grid402';
$GLOBALS['grid402_mp_webhook_secret_override'] = $secret;
$badWh = grid402_mp_handle_webhook(
    ['x-signature' => 'ts=1700000000,v1=assinatura_falsa', 'x-request-id' => 'req-1'],
    json_encode(['type' => 'payment', 'data' => ['id' => '99887766']]),
    []
);
t_assert('Assinatura HMAC invalida retorna 401', $badWh['ok'] === false && $badWh['code'] === 401);

$ts = '1700000000';
$reqId = 'req-2';
$dataId = '99887766';
$sig = hash_hmac('sha256', "id:{$dataId};request-id:{$reqId};ts:{$ts};", $secret);
$okWh = grid402_mp_handle_webhook(
    ['x-signature' => "ts={$ts},v1={$sig}", 'x-request-id' => $reqId],
    json_encode(['type' => 'payment', 'data' => ['id' => $dataId]]),
    []
);
t_assert('Webhook valido aprova e emite pass_g402_...', $okWh['ok'] === true && $okWh['status'] === 'approved' && str_starts_with((string) $okWh['pass_token'], 'pass_g402_'));

echo "\n[7] Polling e Webhook repetido (Idempotencia estrita):\n";
$poll1 = grid402_mp_get_order_status($pixRes['order_id']);
$wh2 = grid402_mp_handle_webhook(
    ['x-signature' => "ts={$ts},v1={$sig}", 'x-request-id' => $reqId],
    json_encode(['type' => 'payment', 'data' => ['id' => $dataId]]),
    []
);
t_assert('Polling retorna o mesmo pass_token', ($poll1['pass_token'] ?? '') === $okWh['pass_token']);
t_assert('Segundo webhook retorna o mesmo token sem duplicar', ($wh2['pass_token'] ?? '') === $okWh['pass_token'] && !empty($wh2['idempotent']));
$passesJson = @json_decode((string) file_get_contents(GRID402_FILE_PASSES), true) ?: [];
t_assert('Apenas 1 Session Pass emitido no registro', count($passesJson) === 1);

echo "\n[8] Modo Sandbox e bloqueio 403 em producao:\n";
$blockedApprove = grid402_mp_sandbox_approve($pixRes['order_id']);
t_assert('mp-sandbox-approve bloqueado com 403 fora do sandbox', $blockedApprove['ok'] === false && $blockedApprove['code'] === 403);

$GLOBALS['grid402_mp_sandbox_override'] = true;
$GLOBALS['grid402_mp_http_transport'] = null;
$sbxPix = grid402_mp_create_pix('starter');
t_assert('Pix em sandbox gera QR SVG simulado', $sbxPix['ok'] === true && $sbxPix['sandbox'] === true && $sbxPix['qr_code'] !== '');
$sbxApprove = grid402_mp_sandbox_approve($sbxPix['order_id']);
t_assert('Aprovacao sandbox emite pass_g402_... marcado como sandbox', $sbxApprove['ok'] === true && str_starts_with((string) $sbxApprove['pass_token'], 'pass_g402_') && $sbxApprove['sandbox'] === true);

$passStatus = grid402_get_session_pass_status($sbxApprove['pass_token']);
t_assert('Token emitido funciona em grid402_get_session_pass_status', !empty($passStatus['valid']) && (int) $passStatus['credits_remaining'] === 500);

t_rmdir($dir);
t_finish('RESULTADO FINAL (Mercado Pago Checkout)');
