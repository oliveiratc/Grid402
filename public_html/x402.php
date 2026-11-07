<?php
/**
 * Grid402 - Módulo de Validação On-Chain do Protocolo x402
 * 
 * Validação de pagamentos em USDC na rede Base Mainnet via JSON-RPC
 * com trava atômica de arquivo (flock) para proteção estrita contra concorrência (TOCTOU).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';

/**
 * Consulta recibo de transação via JSON-RPC com fallback de nós públicos da Base
 */
function grid402_rpc_get_receipt(string $txHash): ?array {
    $rpcUrls = [
        GRID402_BASE_RPC_URL,
        'https://base.llamarpc.com',
        'https://1rpc.io/base',
    ];

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_getTransactionReceipt',
        'params' => [$txHash],
    ]);

    foreach ($rpcUrls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_USERAGENT => 'Grid402-x402-Auditor/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response)) {
            $json = @json_decode($response, true);
            if (isset($json['result'])) {
                return $json['result'];
            }
        }
    }

    return null;
}

/**
 * Adquire trava atômica exclusiva (flock) e reserva a transação contra condições de corrida (TOCTOU)
 */
function grid402_claim_transaction_lock(string $txHash): bool {
    $file = GRID402_FILE_USED_TXS;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return false;
    }

    $acquired = false;
    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($file);
        $content = ($filesize > 0) ? (string) fread($fp, $filesize) : '';
        $txs = $content !== '' ? (@json_decode($content, true) ?: []) : [];

        $lowerHash = strtolower($txHash);

        if (isset($txs[$lowerHash])) {
            // Já utilizada por outra requisição (Replay Attack bloqueado)
            $acquired = false;
        } else {
            // Reserva atômica imediata
            $now = time();
            $txs[$lowerHash] = $now;

            // Auto-limpeza de registros com mais de 7 dias (604800 segundos)
            $cleaned = [];
            foreach ($txs as $h => $ts) {
                if (($now - (int)$ts) <= 604800) {
                    $cleaned[$h] = $ts;
                }
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            $acquired = true;
        }

        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $acquired;
}

/**
 * Libera a reserva da transação se a verificação on-chain falhar
 */
function grid402_release_transaction_lock(string $txHash): void {
    $file = GRID402_FILE_USED_TXS;
    if (!file_exists($file)) {
        return;
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($file);
        $content = ($filesize > 0) ? (string) fread($fp, $filesize) : '';
        $txs = $content !== '' ? (@json_decode($content, true) ?: []) : [];

        $lowerHash = strtolower($txHash);
        if (isset($txs[$lowerHash])) {
            unset($txs[$lowerHash]);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($txs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

/**
 * Obtém o array de transações utilizadas armazenadas
 */
function grid402_get_used_transactions(): array {
    $file = GRID402_FILE_USED_TXS;
    if (!file_exists($file)) {
        return [];
    }
    $content = @file_get_contents($file);
    if (!$content) {
        return [];
    }
    return @json_decode($content, true) ?: [];
}

/**
 * Escrita atômica em arquivo via arquivo temporário e rename
 */
if (!function_exists('grid402_atomic_write')) {
    function grid402_atomic_write(string $filePath, mixed $data): bool {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $content = is_array($data) ? (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string)$data;
        $tmp = $filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        return @rename($tmp, $filePath);
    }
}

/**
 * Marca uma transação como utilizada manualmente (usado em testes ou bootstrap)
 */
function grid402_mark_transaction_used(string $txHash): bool {
    return grid402_claim_transaction_lock($txHash);
}

/**
 * Validação completa on-chain do pagamento x402 com proteção atômica
 * 
 * Retorna array ['valid' => bool, 'reason' => string, 'code' => int]
 */
function grid402_validate_payment(string $txHash): array {
    $txHash = strtolower(trim($txHash));

    // 1. Validação do formato (0x + 64 hex)
    if (!preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
        return [
            'valid' => false,
            'reason' => 'Formato de tx_hash inválido. Esperado 0x seguido de 64 caracteres hexadecimais.',
            'code' => 400,
        ];
    }

    // 2. Trava atômica anti-replay contra condições de corrida (TOCTOU)
    if (!grid402_claim_transaction_lock($txHash)) {
        return [
            'valid' => false,
            'reason' => 'Replay attack detectado: este hash de transação já foi liquidado anteriormente.',
            'code' => 403,
        ];
    }

    // 3. Auditoria on-chain no RPC da Base
    $receipt = grid402_rpc_get_receipt($txHash);
    if ($receipt === null) {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'Transação não encontrada ou ainda não confirmada na rede Base. Tente novamente em alguns segundos.',
            'code' => 402,
        ];
    }

    // 4. Verificação de status (0x1 = Sucesso)
    if (($receipt['status'] ?? '') !== '0x1') {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'A transação falhou na blockchain da Base (status de execução diferente de 0x1).',
            'code' => 402,
        ];
    }

    // 5. Auditoria do evento ERC-20 Transfer (USDC)
    $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    $usdcContract = GRID402_USDC_CONTRACT;
    $targetWallet = substr(GRID402_WALLET_ADDRESS, 2); // sem 0x
    $requiredAmount = GRID402_USDC_REQUIRED_RAW;

    $validTransferFound = false;
    $logs = $receipt['logs'] ?? [];

    foreach ($logs as $log) {
        $contract = strtolower((string)($log['address'] ?? ''));
        $topics = $log['topics'] ?? [];

        if ($contract !== $usdcContract) {
            continue;
        }

        if (count($topics) < 3 || strtolower((string)$topics[0]) !== $transferTopic) {
            continue;
        }

        // Tópico 2 = Destinatário (padded 32 bytes)
        $recipientTopic = strtolower((string)$topics[2]);
        $recipientAddress = substr($recipientTopic, 26);

        if ($recipientAddress !== $targetWallet) {
            continue;
        }

        // Dados = Quantidade em hexadecimal
        $dataHex = (string)($log['data'] ?? '0x0');
        $rawAmount = hexdec(substr($dataHex, 2));

        if ($rawAmount >= $requiredAmount) {
            $validTransferFound = true;
            break;
        }
    }

    if (!$validTransferFound) {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'Nenhuma transferência de USDC válida para a carteira do oráculo com valor mínimo de ' . GRID402_PRICE_DISPLAY . ' USDC foi identificada nesta transação.',
            'code' => 402,
        ];
    }

    // Pagamento confirmado e trava retida com sucesso!
    return [
        'valid' => true,
        'reason' => 'Pagamento x402 validado com sucesso na Base.',
        'code' => 200,
    ];
}

/**
 * Emite a resposta HTTP 402 Payment Required estruturada
 */
function grid402_respond_402(?string $serviceName = null, ?string $errorMessage = null): void {
    if (function_exists('grid402_record_telemetry')) {
        grid402_record_telemetry($serviceName ?? 'unknown', 402, false);
    }
    http_response_code(402);
    header('HTTP/1.1 402 Payment Required', true, 402);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Authenticate: x402');
    header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . GRID402_PRICE_DISPLAY);

    $msg = $errorMessage ?? ($serviceName ? "Acesso à API '{$serviceName}' requer micropagamento de " . GRID402_PRICE_DISPLAY . " USDC via protocolo x402." : 'Consulta técnica requer micropagamento sob demanda via protocolo x402.');

    $payload = [
        'error' => 'PaymentRequired',
        'message' => $msg,
        'protocol' => 'x402',
        'network' => 'Base',
        'chain_id' => GRID402_CHAIN_ID,
        'token' => 'USDC',
        'token_contract' => GRID402_USDC_CONTRACT,
        'price_usdc' => GRID402_PRICE_DISPLAY,
        'price_raw_units' => GRID402_USDC_REQUIRED_RAW,
        'recipient_wallet' => GRID402_WALLET_ADDRESS,
        'action_required' => 'Envie 0.0005 USDC para a carteira recipient_wallet na rede Base e refaça a requisição incluindo o cabeçalho "X-Payment: <tx_hash>".',
        'docs' => 'https://www.grid402.com.br/llms.txt',
    ];

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
