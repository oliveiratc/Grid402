<?php
/**
 * Grid402 - Checkout de Passes de Sessão via Web3 (Base Mainnet)
 * Recebe um hash de transação USDC já confirmado on-chain, valida contra o
 * contrato USDC da Base, verifica anti-replay e emite o token pass_g402_...
 *
 * Fluxo Web3 One-Click Top-Up (PRD - Aceleração de Faturamento Web3, RF03):
 *   1. Usuário transfere USDC (2 / 5 / 20 USDC) para a carteira oráculo na Base
 *   2. Frontend envia o txHash para este endpoint
 *   3. Backend valida o recibo on-chain (RPC Base) e o evento ERC-20 Transfer
 *   4. Emite o passe em session_passes.json e retorna o token
 *
 * Zero-DB: persistência atômica via flock em arquivo JSON (sem SQL).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../x402.php';
require_once __DIR__ . '/pass.php';

/**
 * Valida o hash de transação USDC na Base Mainnet e emite um Session Pass.
 *
 * Passos:
 *   - Formato 0x + 64 hex
 *   - Trava atômica anti-replay (flock)
 *   - Recibo on-chain via eth_getTransactionReceipt (RPC Base com fallbacks)
 *   - Status 0x1 (sucesso)
 *   - Evento ERC-20 Transfer para GRID402_WALLET_ADDRESS com valor >= mínimo
 *   - Emissão do passe via grid402_issue_session_pass (inclui dedup por txHash)
 *
 * Retorna array com 'ok' => bool, 'pass_token', 'tier', 'credits', etc.
 */
function grid402_emitir_passe_por_tx(string $txHash): array {
    $txHash = strtolower(trim($txHash));

    // 1. Formato do hash
    if (!preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
        return [
            'ok' => false,
            'error' => 'InvalidTxHash',
            'message' => 'Hash de transação inválido. Esperado 0x + 64 caracteres hexadecimais.',
            'code' => 400,
        ];
    }

    // 2. Trava atômica anti-replay (mesma semântica de x402_validate_payment)
    if (!grid402_claim_transaction_lock($txHash)) {
        return [
            'ok' => false,
            'error' => 'ReplayDetected',
            'message' => 'Esta transação já foi utilizada para emitir um passe anteriormente.',
            'code' => 403,
        ];
    }

    try {
        // 3. Recibo on-chain na Base Mainnet
        $receipt = grid402_rpc_get_receipt($txHash);
        if ($receipt === null) {
            grid402_release_transaction_lock($txHash);
            return [
                'ok' => false,
                'error' => 'TxNotFound',
                'message' => 'Transação não encontrada ou ainda não confirmada na rede Base Mainnet.',
                'code' => 404,
            ];
        }

        // 4. Status de execução (0x1 = sucesso)
        if (($receipt['status'] ?? '') !== '0x1') {
            grid402_release_transaction_lock($txHash);
            return [
                'ok' => false,
                'error' => 'TxFailed',
                'message' => 'A transação falhou na blockchain da Base (status diferente de 0x1).',
                'code' => 402,
            ];
        }

        // 5. Evento ERC-20 Transfer para a carteira oráculo
        $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
        $usdcContract = strtolower(GRID402_USDC_CONTRACT);
        $targetWallet = substr(strtolower(GRID402_WALLET_ADDRESS), 2); // sem 0x

        $rawAmount = 0;
        $payer = '';
        $found = false;
        foreach ((array)($receipt['logs'] ?? []) as $log) {
            $contract = strtolower((string)($log['address'] ?? ''));
            if ($contract !== $usdcContract) {
                continue;
            }
            $topics = (array)($log['topics'] ?? []);
            if (count($topics) < 3 || strtolower((string)$topics[0] ?? '') !== $transferTopic) {
                continue;
            }
            $recipientTopic = strtolower((string)$topics[2] ?? '');
            $recipientAddress = substr($recipientTopic, 26);
            if ($recipientAddress !== $targetWallet) {
                continue;
            }
            $dataHex = (string)($log['data'] ?? '0x0');
            $rawAmount = (int) hexdec(substr($dataHex, 2));
            $payer = '0x' . substr(strtolower((string)($topics[1] ?? '')), -40);
            $found = true;
            break;
        }

        if (!$found || $rawAmount <= 0) {
            grid402_release_transaction_lock($txHash);
            return [
                'ok' => false,
                'error' => 'NoUsdcTransfer',
                'message' => 'Nenhuma transferência de USDC para a carteira oráculo do Grid402 foi identificada nesta transação.',
                'code' => 402,
            ];
        }

        // 6. Emissão do passe (o issue_session_pass faz dedup por txHash e calcula tier)
        $issued = grid402_issue_session_pass($txHash, $rawAmount, false);

        // Pagamento confirmado: trava retida. Retorna sucesso.
        return [
            'ok' => true,
            'error' => null,
            'message' => 'Passe de sessão emitido com sucesso.',
            'code' => 200,
            'network' => 'Base Mainnet',
            'chain_id' => GRID402_CHAIN_ID,
            'payer' => $payer,
            'tx_hash' => $txHash,
            'raw_usdc_units' => $rawAmount,
            'usdc_deposited' => number_format($rawAmount / 1e6, 4, '.', '') . ' USDC',
            'tier' => $issued['tier'] ?? 'standard',
            'pass_token' => $issued['pass_token'] ?? null,
            'mode' => $issued['mode'] ?? 'production',
            'credits_total' => (int)($issued['credits_total'] ?? 0),
            'credits_remaining' => (int)($issued['credits_remaining'] ?? 0),
            'issued_at' => $issued['issued_at'] ?? null,
            'expires_at' => $issued['expires_at'] ?? null,
            'usage_instructions' => $issued['usage_instructions'] ?? [],
        ];

    } catch (\Throwable $e) {
        @grid402_release_transaction_lock($txHash);
        return [
            'ok' => false,
            'error' => 'InternalError',
            'message' => 'Erro interno ao processar a transação: ' . $e->getMessage(),
            'code' => 500,
        ];
    }
}
