<?php
/**
 * Grid402 - Checkout Multi-Chain de Passes de Sessão via Web3 (5 Redes EVM + Solana)
 * Recebe um hash/assinatura de transação USDC já confirmado on-chain em qualquer uma das
 * 6 redes suportadas (Base, Arbitrum, Polygon, Optimism, Ethereum ou Solana), valida contra
 * o contrato/mint oficial de USDC da rede, verifica anti-replay atômico e emite o token pass_g402_...
 *
 * Zero-DB: persistência atômica via flock em arquivo JSON (sem SQL).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../x402.php';
require_once __DIR__ . '/multichain.php';
require_once __DIR__ . '/pass.php';

/**
 * Valida o hash/assinatura de transação USDC na rede informada e emite um Session Pass.
 *
 * @param string      $txHash  Hash EVM (0x + 64 hex), assinatura Solana Base58 ou formato "chain:tx".
 * @param string|null $network Rede opcional (base, arbitrum, polygon, optimism, ethereum, solana).
 * @return array<string, mixed>
 */
function grid402_emitir_passe_por_tx(string $txHash, ?string $network = null): array {
    $parsed = grid402_parse_network_proof($txHash, $network);

    if ($parsed['invalid_network'] !== null) {
        return [
            'ok' => false,
            'error' => 'UnsupportedNetwork',
            'message' => "Rede não suportada: {$parsed['invalid_network']}. Redes aceitas: base, arbitrum, polygon, optimism, ethereum, solana.",
            'supported_networks' => array_keys(grid402_supported_networks()),
            'code' => 400,
        ];
    }

    $slug = $parsed['network'];
    $cleanTx = $parsed['tx_hash'];
    $lockKey = $parsed['lock_key'];
    $networks = grid402_supported_networks();
    $netCfg = $networks[$slug];

    // 1. Formato do hash/assinatura
    if ($slug === 'solana') {
        if (!grid402_is_valid_solana_signature($cleanTx)) {
            return [
                'ok' => false,
                'error' => 'InvalidTxHash',
                'message' => 'Assinatura de transação Solana inválida. Esperado Base58 com 64 a 90 caracteres.',
                'code' => 400,
            ];
        }
    } else {
        if (preg_match('/^0x[a-f0-9]{64}$/', $cleanTx) !== 1) {
            return [
                'ok' => false,
                'error' => 'InvalidTxHash',
                'message' => "Hash de transação inválido para {$netCfg['display_name']}. Esperado 0x + 64 caracteres hexadecimais.",
                'code' => 400,
            ];
        }
    }

    // 2. Trava atômica anti-replay isolada por rede (Base mantém chave legada)
    if (!grid402_claim_transaction_lock($lockKey)) {
        // Idempotência: se esta tx já emitiu um Session Pass, devolve o passe existente sem reemitir nem cobrar novamente
        $existingPass = function_exists('grid402_find_session_pass_by_tx')
            ? grid402_find_session_pass_by_tx($lockKey)
            : null;
        if ($existingPass === null && $slug === 'solana' && function_exists('grid402_find_session_pass_by_tx')) {
            $existingPass = grid402_find_session_pass_by_tx($cleanTx);
        }
        if (is_array($existingPass) && !empty($existingPass['pass_token'])) {
            $existingUsdc = trim((string) ($existingPass['usdc_deposited'] ?? '0.0000'));
            if (!str_contains(strtoupper($existingUsdc), 'USDC')) {
                $existingUsdc .= ' USDC';
            }
            $existingTier = (string) ($existingPass['tier'] ?? 'standard');
            // IDOR Protection: não expor o pass_token em texto claro no retorno idempotente para evitar roubo de terceiros
            return [
                'ok' => false,
                'idempotent' => true,
                'status' => 'already_issued',
                'error' => 'PassAlreadyIssued',
                'message' => "Passe de sessão já emitido anteriormente para esta transação. Por segurança, o token de acesso não é reexibido.",
                'code' => 409,
                'network' => $netCfg['display_name'],
                'network_slug' => $slug,
                'chain_id' => $netCfg['chain_id'],
                'tx_hash' => $cleanTx,
                'explorer_url' => grid402_explorer_tx_url($slug, $cleanTx),
                'tier' => $existingTier,
                'mode' => $existingPass['mode'] ?? 'production',
                'issued_at' => $existingPass['issued_at'] ?? null,
                'expires_at' => $existingPass['expires_at'] ?? null,
            ];
        }

        return [
            'ok' => false,
            'error' => 'ReplayDetected',
            'message' => "Esta transação na rede {$netCfg['display_name']} já foi utilizada anteriormente.",
            'code' => 403,
        ];
    }

    $minRawUnits = defined('GRID402_CREDIT_UNIT_RAW') ? max(5000, (int) GRID402_CREDIT_UNIT_RAW) : 5000;

    try {
        $rawAmount = 0;
        $payer = '';

        if ($slug === 'solana') {
            $txData = grid402_rpc_get_solana_transaction($cleanTx);
            if ($txData === null) {
                grid402_release_transaction_lock($lockKey);
                return [
                    'ok' => false,
                    'error' => 'TxNotFound',
                    'message' => 'Transação não encontrada ou ainda não confirmada na rede Solana Mainnet.',
                    'code' => 404,
                ];
            }

            $check = grid402_verify_solana_tx_usdc($txData, $minRawUnits);
            if (empty($check['valid'])) {
                grid402_release_transaction_lock($lockKey);
                $errCode = (string) ($check['error'] ?? 'NoUsdcTransfer');
                $mappedErr = match ($errCode) {
                    'TransactionFailedOnChain' => 'TxFailed',
                    'TransactionExpired' => 'TransactionExpired',
                    'InsufficientAmount', 'InsufficientUsdcAmount' => 'InsufficientAmount',
                    default => 'NoUsdcTransfer',
                };
                $httpCode = ($mappedErr === 'TransactionExpired') ? 403 : (int) ($check['code'] ?? 402);
                return [
                    'ok' => false,
                    'error' => $mappedErr,
                    'message' => (string) ($check['message'] ?? 'Nenhuma transferência válida de SPL USDC (>= 0.0050 USDC) encontrada na Solana.'),
                    'code' => $httpCode,
                ];
            }
            $rawAmount = (int) ($check['raw_amount'] ?? 0);
            $payer = trim((string) ($check['payer'] ?? ''));
            if ($payer === '') {
                $payer = 'solana';
            }
        } else {
            $receipt = ($slug === 'base')
                ? grid402_rpc_get_receipt($cleanTx)
                : grid402_rpc_get_evm_receipt($slug, $cleanTx);

            if ($receipt === null) {
                grid402_release_transaction_lock($lockKey);
                return [
                    'ok' => false,
                    'error' => 'TxNotFound',
                    'message' => "Transação não encontrada ou ainda não confirmada na rede {$netCfg['display_name']}.",
                    'code' => 404,
                ];
            }

            $check = grid402_verify_evm_receipt_usdc($receipt, $slug, $minRawUnits);
            if (empty($check['valid'])) {
                grid402_release_transaction_lock($lockKey);
                $errCode = (string) ($check['error'] ?? 'NoUsdcTransfer');
                $mappedErr = match ($errCode) {
                    'TransactionFailedOnChain' => 'TxFailed',
                    'TransactionExpired' => 'TransactionExpired',
                    'InsufficientAmount', 'InsufficientUsdcAmount' => 'InsufficientAmount',
                    default => 'NoUsdcTransfer',
                };
                $httpCode = ($mappedErr === 'TransactionExpired') ? 403 : (int) ($check['code'] ?? 402);
                return [
                    'ok' => false,
                    'error' => $mappedErr,
                    'message' => (string) ($check['message'] ?? "Nenhuma transferência de USDC oficial (>= 0.0050 USDC) encontrada na rede {$netCfg['display_name']}."),
                    'code' => $httpCode,
                ];
            }
            $rawAmount = (int) ($check['raw_amount'] ?? 0);
            $payer = strtolower(trim((string) ($check['payer'] ?? $receipt['from'] ?? '')));
        }

        if ($rawAmount < $minRawUnits) {
            grid402_release_transaction_lock($lockKey);
            return [
                'ok' => false,
                'error' => 'InsufficientAmount',
                'message' => sprintf(
                    'Valor transferido insuficiente. Mínimo exigido para emissão de Session Pass: %.4f USDC (%d raw units = 1 crédito).',
                    $minRawUnits / 1e6,
                    $minRawUnits
                ),
                'code' => 402,
            ];
        }

        // 3. Emissão do passe (o issue_session_pass faz dedup por lockKey e calcula tier)
        $issued = grid402_issue_session_pass($lockKey, $rawAmount, false);

        return [
            'ok' => true,
            'idempotent' => false,
            'error' => null,
            'message' => "Passe de sessão emitido com sucesso via {$netCfg['display_name']}.",
            'code' => 200,
            'network' => $netCfg['display_name'],
            'network_slug' => $slug,
            'chain_id' => $netCfg['chain_id'],
            'payer' => $payer,
            'tx_hash' => $cleanTx,
            'explorer_url' => grid402_explorer_tx_url($slug, $cleanTx),
            'raw_usdc_units' => $rawAmount,
            'usdc_deposited' => number_format($rawAmount / 1e6, 4, '.', '') . ' USDC',
            'tier' => $issued['tier'] ?? 'standard',
            'pass_token' => $issued['pass_token'] ?? null,
            'mode' => $issued['mode'] ?? 'production',
            'credits_total' => (int)($issued['credits_total'] ?? 0),
            'credits_remaining' => (int)($issued['credits_remaining'] ?? 0),
            'issued_at' => $issued['issued_at'] ?? null,
            'expires_at' => $issued['expires_at'] ?? null,
            'ttl_days' => (int) ($issued['validity_days'] ?? (($issued['tier'] ?? '') === 'fleet' ? 365 : 180)),
            'usage_instructions' => $issued['usage_instructions'] ?? [],
        ];

    } catch (\Throwable $e) {
        @grid402_release_transaction_lock($lockKey);
        return [
            'ok' => false,
            'error' => 'InternalError',
            'message' => 'Erro interno ao processar a transação: ' . $e->getMessage(),
            'code' => 500,
        ];
    }
}
