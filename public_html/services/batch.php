<?php
/**
 * Grid402 - Orquestrador Multi-Servico em Lote (POST /api/v1/batch)
 *
 * Regras:
 * - Aceita de 1 a 5 itens em producao (max 2 itens em modo sandbox/demo).
 * - Proibe recursao (batch) e rotas administrativas/emissao (pass, pass-emitir, verify, summary).
 * - Preco dinamico igual a soma exata dos precos oficiais dos itens em config.php.
 * - No Session Pass: checa allowed_endpoints de cada sub-servico antes do debito e estorna
 *   automaticamente os creditos de qualquer sub-item que falhar.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Servicos explicitamente proibidos dentro de um lote POST /api/v1/batch.
 *
 * @return string[]
 */
function grid402_batch_blocked_services(): array {
    return [
        'pass',
        'pass-emitir',
        'pass-balance',
        'pass-limits',
        'pass-statement',
        'batch',
        'verify',
        'summary',
    ];
}

/**
 * Calcula e valida o lote JSON antes de emitir HTTP 402 ou debitar pagamento.
 *
 * @return array<string, mixed>
 */
function grid402_batch_parse_and_validate(string $rawBody, bool $isSandbox = false): array {
    if (strlen($rawBody) > 16384) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'BatchPayloadTooLarge',
            'message' => 'O corpo JSON do lote excede o limite de 16 KB.',
        ];
    }

    $trimmed = trim($rawBody);
    if ($trimmed === '') {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'MissingBatchPayload',
            'message' => 'Envie POST JSON no formato {"items":[{"service":"dns","params":{"domain":"base.org"}}]}.',
        ];
    }

    $decoded = @json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidBatchJSON',
            'message' => 'O corpo enviado para POST /api/v1/batch nao e um JSON valido.',
        ];
    }

    $items = $decoded['items'] ?? null;
    if (!is_array($items) || count($items) === 0) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'EmptyBatchItems',
            'message' => 'O campo "items" deve conter de 1 a 5 sub-requisicoes.',
        ];
    }

    if (count($items) > 5) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'BatchLimitExceeded',
            'message' => 'Limite maximo de 5 itens por chamada em POST /api/v1/batch.',
            'max_items' => 5,
            'received_items' => count($items),
        ];
    }

    if ($isSandbox && count($items) > 2) {
        return [
            'ok' => false,
            'code' => 400,
            'sandbox' => true,
            'error' => 'SandboxBatchLimitExceeded',
            'message' => 'Modo sandbox/demo permite no maximo 2 itens por chamada POST /api/v1/batch.',
            'max_sandbox_items' => 2,
            'received_items' => count($items),
        ];
    }

    $catalog = function_exists('grid402_get_utility_services') ? grid402_get_utility_services() : [];
    $blocked = grid402_batch_blocked_services();
    $plan = [];
    $normalizedItems = [];
    $uniqueServices = [];
    $totalRaw = 0;

    foreach (array_values($items) as $idx => $rawItem) {
        if (!is_array($rawItem)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'InvalidBatchItem',
                'message' => "O item de indice {$idx} deve ser um objeto JSON com 'service' e 'params'.",
            ];
        }

        $svc = strtolower(trim((string) ($rawItem['service'] ?? '')));
        if ($svc === '') {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'MissingBatchService',
                'message' => "O item de indice {$idx} nao informou o campo 'service'.",
            ];
        }

        if (in_array($svc, $blocked, true)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'ForbiddenBatchService',
                'message' => "O servico '{$svc}' nao pode ser executado dentro de um lote POST /api/v1/batch.",
                'service' => $svc,
            ];
        }

        if (!isset($catalog[$svc])) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'UnknownBatchService',
                'message' => "Servico '{$svc}' desconhecido no catalogo Grid402.",
                'service' => $svc,
            ];
        }

        $params = $rawItem['params'] ?? [];
        if (!is_array($params)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'InvalidBatchParams',
                'message' => "O campo 'params' do item {$idx} ('{$svc}') deve ser um objeto JSON.",
            ];
        }

        $priceFloat = (float) ($catalog[$svc]['price_usdc'] ?? 0.0050);
        $rawUnits = (int) round($priceFloat * 1000000);
        if ($rawUnits <= 0) {
            $rawUnits = 5000;
        }
        $credits = max(1, (int) round($rawUnits / 5000));
        $itemId = trim((string) ($rawItem['id'] ?? ('item_' . ($idx + 1))));
        if ($itemId === '') {
            $itemId = 'item_' . ($idx + 1);
        }

        $totalRaw += $rawUnits;
        $uniqueServices[$svc] = true;
        $normalizedItems[] = [
            'index' => $idx,
            'id' => $itemId,
            'service' => $svc,
            'params' => $params,
            'raw_units' => $rawUnits,
            'credits' => $credits,
            'price_usdc' => number_format($rawUnits / 1000000, 4, '.', ''),
        ];
        $plan[] = [
            'id' => $itemId,
            'service' => $svc,
            'credits' => $credits,
            'raw_units' => $rawUnits,
            'price_usdc' => number_format($rawUnits / 1000000, 4, '.', ''),
        ];
    }

    $totalCredits = max(1, (int) round($totalRaw / 5000));

    return [
        'ok' => true,
        'items' => $normalizedItems,
        'batch_plan' => $plan,
        'unique_services' => array_keys($uniqueServices),
        'item_count' => count($normalizedItems),
        'total_raw_units' => $totalRaw,
        'total_credits' => $totalCredits,
        'total_usdc_display' => number_format($totalRaw / 1000000, 4, '.', ''),
    ];
}

/**
 * Executa os sub-itens do lote usando o despachante interno compartilhado do gateway.
 *
 * @param array<string, mixed> $validatedBatch
 * @param callable(string, array, bool): array $dispatcher
 * @return array<string, mixed>
 */
function grid402_batch_execute_items(array $validatedBatch, callable $dispatcher, bool $isSandbox = false): array {
    $items = is_array($validatedBatch['items'] ?? null) ? $validatedBatch['items'] : [];
    $results = [];
    $succeeded = 0;
    $failed = 0;
    $chargedRaw = 0;
    $refundedRaw = 0;
    $chargedCredits = 0;
    $refundedCredits = 0;

    foreach ($items as $item) {
        $svc = (string) $item['service'];
        $params = is_array($item['params'] ?? null) ? $item['params'] : [];
        $rawCost = (int) $item['raw_units'];
        $creditsCost = (int) $item['credits'];

        try {
            $subRes = $dispatcher($svc, $params, $isSandbox);
        } catch (\Throwable $e) {
            $subRes = [
                'success' => false,
                'error' => 'BatchSubItemException',
                'message' => $e->getMessage(),
                'code' => 500,
            ];
        }

        $subStatus = (is_array($subRes) && isset($subRes['code']) && is_int($subRes['code']) && $subRes['code'] >= 100 && $subRes['code'] < 600)
            ? (int) $subRes['code']
            : 200;
        $isOk = ($subStatus === 200);

        if ($isOk) {
            $succeeded++;
            $chargedRaw += $rawCost;
            $chargedCredits += $creditsCost;
            $results[] = [
                'index' => (int) $item['index'],
                'id' => (string) $item['id'],
                'service' => $svc,
                'ok' => true,
                'status' => 200,
                'credits_charged' => $creditsCost,
                'price_usdc' => (string) $item['price_usdc'],
                'data' => $subRes,
            ];
        } else {
            $failed++;
            $refundedRaw += $rawCost;
            $refundedCredits += $creditsCost;
            $results[] = [
                'index' => (int) $item['index'],
                'id' => (string) $item['id'],
                'service' => $svc,
                'ok' => false,
                'status' => $subStatus,
                'credits_charged' => 0,
                'price_usdc' => '0.0000',
                'error' => is_array($subRes) ? ($subRes['error'] ?? 'SubItemFailed') : 'SubItemFailed',
                'message' => is_array($subRes) ? ($subRes['message'] ?? 'Sub-item failed during batch execution.') : 'Sub-item failed.',
            ];
        }
    }

    if ($succeeded === 0) {
        return [
            'success' => false,
            'error' => 'BatchAllItemsFailed',
            'message' => 'Todos os itens do lote falharam durante a execucao. Nenhuma cobranca foi mantida.',
            'batch_summary' => [
                'total_items' => count($items),
                'succeeded' => 0,
                'failed' => $failed,
                'total_credits_quoted' => (int) ($validatedBatch['total_credits'] ?? 0),
                'credits_charged' => 0,
                'credits_refunded' => $refundedCredits,
                'total_usdc_charged' => '0.0000',
                'failed_raw_units' => $refundedRaw,
            ],
            'results' => $results,
            'code' => 503,
        ];
    }

    return [
        'protocol' => 'x402',
        'service' => 'batch',
        'status' => $failed > 0 ? 'PARTIAL_SUCCESS' : 'UP',
        'batch_summary' => [
            'total_items' => count($items),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'total_credits_quoted' => (int) ($validatedBatch['total_credits'] ?? 0),
            'credits_charged' => $chargedCredits,
            'credits_refunded' => $refundedCredits,
            'total_usdc_charged' => number_format($chargedRaw / 1000000, 4, '.', ''),
            'failed_raw_units' => $refundedRaw,
        ],
        'results' => $results,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}
