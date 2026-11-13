<?php
/**
 * Grid402 // Serviço de Vendas e Assinaturas em BRL (Alavanca 2)
 * Ledger zero-DB (sales.json) + cálculo de MRR + emissão de pass fiat.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC') && php_sapi_name() !== 'cli') {
    // permite include direto pelos testes
}

function grid402_sales_file(): string {
    return defined('GRID402_FILE_SALES') ? GRID402_FILE_SALES : (GRID402_PRIVATE_DIR . '/sales.json');
}

function grid402_sales_load(): array {
    $file = grid402_sales_file();
    if (!file_exists($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = @json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function grid402_sales_save(array $sales): bool {
    $file = grid402_sales_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return (bool) @file_put_contents($file, json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Cria um pedido (status=pending). Retorna order_id.
 */
function grid402_sales_create(string $planId, float $amountBrl, string $customerEmail, int $credits, string $paymentMethod = 'pix'): array {
    $sales = grid402_sales_load();
    $orderId = 'ord_' . bin2hex(random_bytes(8));
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $sales[$orderId] = [
        'order_id' => $orderId,
        'plan' => $planId,
        'amount_brl' => round($amountBrl, 2),
        'credits' => $credits,
        'customer_email' => strtolower(trim($customerEmail)),
        'payment_method' => $paymentMethod,
        'status' => 'pending',
        'pass_id' => null,
        'pass_token' => null,
        'created_at' => $now,
        'paid_at' => null,
        'expires_at' => null,
    ];
    grid402_sales_save($sales);
    return $sales[$orderId];
}

/**
 * Marca pedido como pago e emite o passe. Idempotente.
 */
function grid402_sales_mark_paid(string $orderId, string $paymentSource = 'pix', string $paymentRef = ''): array {
    $sales = grid402_sales_load();
    if (!isset($sales[$orderId])) {
        return ['ok' => false, 'error' => 'OrderNotFound'];
    }
    $order = $sales[$orderId];
    if (($order['status'] ?? '') === 'paid' && !empty($order['pass_token'])) {
        return ['ok' => true, 'already_paid' => true, 'order' => $order];
    }
    $credits = (int) ($order['credits'] ?? 0);
    $rawUnits = $credits * 500; // 1 crédito = 500 raw = 0.0005 USDC
    $ref = $paymentRef !== '' ? $paymentRef : ('fiat:' . $orderId);
    require_once __DIR__ . '/pass.php';
    $result = grid402_issue_fiat_pass($ref, $rawUnits, (string) $order['plan'], (string) $order['customer_email']);
    if (empty($result['pass_token'])) {
        return ['ok' => false, 'error' => 'PassIssuanceFailed', 'detail' => $result];
    }
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $order['status'] = 'paid';
    $order['payment_source'] = $paymentSource;
    $order['payment_ref'] = $paymentRef;
    $order['pass_id'] = $result['pass_id'] ?? null;
    $order['pass_token'] = $result['pass_token'];
    $order['paid_at'] = $now;
    $order['expires_at'] = $result['expires_at'] ?? null;
    $sales[$orderId] = $order;
    grid402_sales_save($sales);
    return ['ok' => true, 'order' => $order, 'pass' => $result];
}

/**
 * Lista pedidos ordenados por data (mais recentes primeiro).
 */
function grid402_sales_list(int $limit = 100): array {
    $sales = grid402_sales_load();
    uasort($sales, function ($a, $b) {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    return array_slice(array_values($sales), 0, $limit, true);
}

/**
 * Calcula MRR aproximado (soma dos valores de pedidos pagos dos últimos 30 dias
 * + assinaturas ativas). Simplificação: MRR = soma de amount_brl de pedidos pagos
 * nos últimos 30 dias (tratados como recorrentes). Para assinatura real, use
 * Stripe subscription webhook para marcar recurring=true.
 */
function grid402_sales_mrr(): array {
    $sales = grid402_sales_load();
    $now = time();
    $thirtyDaysAgo = $now - 30 * 86400;
    $mrr = 0.0;
    $activeSubs = 0;
    $totalPaid = 0.0;
    $totalPending = 0.0;
    $onchainRevenue = 0.0;
    $fiatRevenue = 0.0;
    foreach ($sales as $s) {
        $status = (string) ($s['status'] ?? 'pending');
        $amount = (float) ($s['amount_brl'] ?? 0);
        if ($status === 'paid') {
            $totalPaid += $amount;
            $fiatRevenue += $amount;
            $paidAt = strtotime((string) ($s['paid_at'] ?? ''));
            if ($paidAt !== false && $paidAt >= $thirtyDaysAgo) {
                $mrr += $amount;
                $activeSubs++;
            }
        } elseif ($status === 'pending') {
            $totalPending += $amount;
        }
    }
    return [
        'mrr_brl' => round($mrr, 2),
        'mrr_usd' => round($mrr / 5.42, 2),
        'active_subscriptions_30d' => $activeSubs,
        'total_paid_brl' => round($totalPaid, 2),
        'total_pending_brl' => round($totalPending, 2),
        'fiat_revenue_brl' => round($fiatRevenue, 2),
        'orders_count' => count($sales),
    ];
}

/**
 * Planos BRL (preço por volume contratado, não por "requisição").
 * 1 crédito = 0.0005 USDC = ~R$ 0.0027. Preço por consulta efetivo = price/credits * cost.
 */
function grid402_plans(): array {
    return [
        'starter' => [
            'id' => 'starter',
            'name' => 'Starter',
            'price_brl' => 99.00,
            'credits' => 20000,
            'approx_cnpj' => 5000,
            'approx_compliance' => 3333,
            'approx_sanctions' => 10000,
            'description' => 'Para freelancers e pequenas agências. 20.000 créditos/mês.',
            'stripe_price_id' => grid402_env('STRIPE_PRICE_STARTER', ''),
            'mp_link' => grid402_env('MP_LINK_STARTER', ''),
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price_brl' => 249.00,
            'credits' => 60000,
            'approx_cnpj' => 15000,
            'approx_compliance' => 10000,
            'approx_sanctions' => 30000,
            'description' => 'Para agências e fintechs em crescimento. 60.000 créditos/mês.',
            'stripe_price_id' => grid402_env('STRIPE_PRICE_PRO', ''),
            'mp_link' => grid402_env('MP_LINK_PRO', ''),
        ],
        'business' => [
            'id' => 'business',
            'name' => 'Business',
            'price_brl' => 699.00,
            'credits' => 200000,
            'approx_cnpj' => 50000,
            'approx_compliance' => 33333,
            'approx_sanctions' => 100000,
            'description' => 'Para operações de compliance e qualificação em escala. 200.000 créditos/mês.',
            'stripe_price_id' => grid402_env('STRIPE_PRICE_BUSINESS', ''),
            'mp_link' => grid402_env('MP_LINK_BUSINESS', ''),
        ],
        'enterprise' => [
            'id' => 'enterprise',
            'name' => 'Enterprise',
            'price_brl' => 1999.00,
            'credits' => 800000,
            'approx_cnpj' => 200000,
            'approx_compliance' => 133333,
            'approx_sanctions' => 400000,
            'description' => 'SLA, volume, fatura e contrato. 800.000 créditos/mês.',
            'stripe_price_id' => grid402_env('STRIPE_PRICE_ENTERPRISE', ''),
            'mp_link' => grid402_env('MP_LINK_ENTERPRISE', ''),
        ],
    ];
}
