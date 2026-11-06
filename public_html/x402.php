<?php
/**
 * Grid402 - Módulo de Validação On-Chain do Protocolo x402
 * 
 * Verifica pagamentos de 0.0005 USDC na rede Base Mainnet via JSON-RPC
 * e gerencia prevenção contra replay attacks com armazenamento local em JSON.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';

/**
 * Gravação atômica em JSON
 */
function grid402_atomic_write(string $filePath, array $data): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $tmpFile = tempnam($dir, 'tmp_');
    if ($tmpFile === false) {
        return false;
    }
    if (file_put_contents($tmpFile, $json) === false) {
        @unlink($tmpFile);
        return false;
    }
    chmod($tmpFile, 0644);
    return rename($tmpFile, $filePath);
}

/**
 * Consulta recibo de transação via JSON-RPC na Base Mainnet
 */
function grid402_rpc_get_receipt(string $txHash): ?array {
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_getTransactionReceipt',
        'params' => [$txHash],
    ]);

    $ch = curl_init(GRID402_BASE_RPC_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => GRID402_USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => GRID402_SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => GRID402_SSL_VERIFY ? 2 : 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return null;
    }

    $json = json_decode((string)$response, true);
    return $json['result'] ?? null;
}

/**
 * Carrega a lista de transações já liquidadas
 */
function grid402_get_used_transactions(): array {
    if (!file_exists(GRID402_FILE_USED_TXS)) {
        return [];
    }
    $content = file_get_contents(GRID402_FILE_USED_TXS);
    if ($content === false) {
        return [];
    }
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Registra uma transação como utilizada (com timestamp)
 */
function grid402_mark_transaction_used(string $txHash): bool {
    $txs = grid402_get_used_transactions();
    $txs[strtolower($txHash)] = time();

    // Auto-limpeza de registros com mais de 7 dias
    $now = time();
    $cleaned = [];
    foreach ($txs as $hash => $ts) {
        if (($now - (int)$ts) <= 604800) {
            $cleaned[$hash] = $ts;
        }
    }

    return grid402_atomic_write(GRID402_FILE_USED_TXS, $cleaned);
}

/**
 * Validação completa do comprovante x402
 * 
 * Retorna array ['valid' => bool, 'reason' => string, 'code' => int]
 */
function grid402_validate_payment(string $txHash): array {
    $txHash = strtolower(trim($txHash));

    // 1. Validação de formato do hash (0x + 64 hexadecimais)
    if (!preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
        return [
            'valid' => false,
            'reason' => 'Formato de tx_hash inválido. Esperado 0x seguido de 64 caracteres hexadecimais.',
            'code' => 400,
        ];
    }

    // 2. Prevenção de Replay Attack
    $usedTxs = grid402_get_used_transactions();
    if (isset($usedTxs[$txHash])) {
        return [
            'valid' => false,
            'reason' => 'Replay attack detectado: este hash de transação já foi utilizado anteriormente.',
            'code' => 403,
        ];
    }

    // 3. Consulta on-chain ao RPC da Base
    $receipt = grid402_rpc_get_receipt($txHash);
    if ($receipt === null) {
        return [
            'valid' => false,
            'reason' => 'Transação não encontrada ou ainda não confirmada na rede Base. Tente novamente em alguns segundos.',
            'code' => 402,
        ];
    }

    // Verifica status da transação (0x1 = Sucesso)
    if (($receipt['status'] ?? '') !== '0x1') {
        return [
            'valid' => false,
            'reason' => 'A transação falhou na blockchain da Base (status diferente de 0x1).',
            'code' => 402,
        ];
    }

    // 4. Verificação dos Logs de Transferência ERC-20 (USDC)
    $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    $usdcContract = GRID402_USDC_CONTRACT;
    $targetWallet = substr(GRID402_WALLET_ADDRESS, 2); // Remove 0x
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
        $recipientAddress = substr($recipientTopic, 26); // Pega os últimos 40 hex (20 bytes)

        if ($recipientAddress !== $targetWallet) {
            continue;
        }

        // Dados = Valor transferido em hex
        $dataHex = (string)($log['data'] ?? '0x0');
        $rawAmount = hexdec(substr($dataHex, 2));

        if ($rawAmount >= $requiredAmount) {
            $validTransferFound = true;
            break;
        }
    }

    if (!$validTransferFound) {
        return [
            'valid' => false,
            'reason' => 'Nenhuma transferência de USDC válida para a carteira do oráculo com valor mínimo de ' . GRID402_PRICE_DISPLAY . ' USDC foi identificada nesta transação.',
            'code' => 402,
        ];
    }

    // 5. Marcação segura como utilizada
    grid402_mark_transaction_used($txHash);

    return [
        'valid' => true,
        'reason' => 'Pagamento x402 validado com sucesso na Base.',
        'code' => 200,
    ];
}

/**
 * Emite a resposta HTTP 402 Payment Required estruturada
 */
function grid402_respond_402(?string $errorMessage = null): void {
    http_response_code(402);
    header('Content-Type: application/json; charset=utf-8');
    
    // O servidor CLI embutido do PHP reescreve qualquer resposta com 'WWW-Authenticate' para 401.
    // Em LiteSpeed/Apache de produção, 'WWW-Authenticate: x402' é mantido normalmente com 402.
    if (php_sapi_name() !== 'cli-server') {
        header('WWW-Authenticate: x402');
    }
    header('X-Authenticate: x402');
    header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . GRID402_PRICE_DISPLAY);

    $payload = [
        'error' => 'PaymentRequired',
        'message' => $errorMessage ?? 'Telemetria detalhada requer micropagamento sob demanda via protocolo x402.',
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
