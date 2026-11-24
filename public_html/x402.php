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
require_once __DIR__ . '/facilitator.php';
require_once __DIR__ . '/services/multichain.php';

/**
 * Lista ordenada e saudavel de RPCs da Base Mainnet (V-21).
 * Prioriza RPC customizado (se diferente do default rate-limited e de nos mortos),
 * depois base-rpc.publicnode.com e fallbacks vivos, deixando mainnet.base.org por ultimo.
 *
 * @return string[]
 */
function grid402_base_rpc_urls(): array {
    $urls = [
        'https://base-rpc.publicnode.com',
        'https://1rpc.io/base',
        'https://base.drpc.org',
        'https://mainnet.base.org',
    ];
    $configured = defined('GRID402_BASE_RPC_URL') ? trim((string) GRID402_BASE_RPC_URL) : '';
    if (
        $configured !== ''
        && str_starts_with($configured, 'https://')
        && !in_array(rtrim(strtolower($configured), '/'), ['https://mainnet.base.org', 'https://base.llamarpc.com'], true)
    ) {
        array_unshift($urls, $configured);
    }
    $envCustom = function_exists('grid402_env') ? trim((string) grid402_env('GRID402_RPC_BASE', '')) : '';
    if ($envCustom !== '' && str_starts_with($envCustom, 'https://') && rtrim(strtolower($envCustom), '/') !== 'https://base.llamarpc.com') {
        array_unshift($urls, $envCustom);
    }
    return array_values(array_unique($urls));
}

/**
 * Indica se o fallback de sandbox na testnet Base Sepolia esta explicitamente habilitado.
 * Padrao: false em producao (opt-in via GRID402_ALLOW_SEPOLIA_SANDBOX=true).
 */
function grid402_allow_sepolia_sandbox(): bool {
    if (defined('GRID402_ALLOW_SEPOLIA_SANDBOX')) {
        $v = GRID402_ALLOW_SEPOLIA_SANDBOX;
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }
    $raw = function_exists('grid402_env') ? (string) grid402_env('GRID402_ALLOW_SEPOLIA_SANDBOX', 'false') : 'false';
    return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Consulta recibo de transação via JSON-RPC com fallback de nós públicos saudáveis da Base
 */
function grid402_rpc_get_receipt(string $txHash): ?array {
    $rpcUrls = grid402_base_rpc_urls();

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
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Grid402-x402-Auditor/2.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response)) {
            $json = @json_decode($response, true);
            // FIX: Use array_key_exists + strict null check to prevent bypass
            // when RPC returns {"result": null} (isset returns false for null,
            // but we also reject false/0/empty to be safe)
            if (is_array($json) && array_key_exists('result', $json) && $json['result'] !== null && $json['result'] !== false && $json['result'] !== 0 && $json['result'] !== '' && $json['result'] !== []) {
                if (is_array($json['result'])) {
                    return $json['result'];
                }
            }
        }
    }

    return null;
}

/**
 * Consulta recibo de transacao na testnet Base Sepolia via JSON-RPC
 */
function grid402_rpc_get_sepolia_receipt(string $txHash): ?array {
    $rpcUrl = defined('GRID402_SEPOLIA_RPC_URL') ? GRID402_SEPOLIA_RPC_URL : 'https://sepolia.base.org';

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_getTransactionReceipt',
        'params' => [$txHash],
    ]);

    $ch = curl_init($rpcUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'Grid402-x402-Sepolia-Auditor/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && is_string($response)) {
        $json = @json_decode($response, true);
        if (is_array($json) && is_array($json['result'] ?? null) && !empty($json['result'])) {
            return $json['result'];
        }
    }

    return null;
}

/**
 * Consulta timestamp Unix de um bloco na testnet Base Sepolia via eth_getBlockByNumber.
 */
function grid402_rpc_get_sepolia_block_timestamp(string $blockNumberHex): ?int {
    $cleanBlock = strtolower(trim($blockNumberHex));
    if (preg_match('/^0x[0-9a-f]+$/', $cleanBlock) !== 1) {
        return null;
    }
    $rpcUrl = defined('GRID402_SEPOLIA_RPC_URL') ? GRID402_SEPOLIA_RPC_URL : 'https://sepolia.base.org';
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_getBlockByNumber',
        'params' => [$cleanBlock, false],
    ]);

    $ch = curl_init($rpcUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'Grid402-x402-Sepolia-Auditor/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && is_string($response)) {
        $json = @json_decode($response, true);
        $tsRaw = is_array($json['result'] ?? null) ? ($json['result']['timestamp'] ?? null) : null;
        if (is_string($tsRaw) && preg_match('/^0x[0-9a-fA-F]+$/', $tsRaw) === 1) {
            $ts = (int) hexdec(substr($tsRaw, 2));
            return $ts > 0 ? $ts : null;
        }
        if (is_int($tsRaw) && $tsRaw > 0) {
            return $tsRaw;
        }
    }
    return null;
}

/**
 * Controle defensivo de taxa (Rate Limit) para requisicoes em Modo Sandbox (V-02).
 * Usa trava exclusiva atomica (fopen c+ + flock LOCK_EX) e comportamento estritamente fail-closed.
 * Permite ate 3 requisicoes por dia (janela 24h) por IP por padrao.
 */
function grid402_check_sandbox_rate_limit(string $clientIp): array {
    $file = defined('GRID402_FILE_SANDBOX_RATES') ? GRID402_FILE_SANDBOX_RATES : (dirname(__DIR__) . '/private_data/sandbox_rates.json');
    $maxRequests = defined('GRID402_SANDBOX_RATE_LIMIT') ? GRID402_SANDBOX_RATE_LIMIT : 3;
    $windowSeconds = defined('GRID402_SANDBOX_WINDOW_SECONDS') ? max(1, (int) GRID402_SANDBOX_WINDOW_SECONDS) : 86400;
    $now = time();

    $failClosed = static fn(string $errCode = 'RateLimitStorageUnavailable'): array => [
        'allowed' => false,
        'count' => $maxRequests,
        'remaining' => 0,
        'retry_after' => 60,
        'limit' => $maxRequests,
        'window_seconds' => $windowSeconds,
        'error' => $errCode,
    ];

    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return $failClosed('RateLimitDirUnavailable');
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return $failClosed('RateLimitFileOpenFailed');
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return $failClosed('RateLimitLockFailed');
    }

    try {
        if (function_exists('grid402_read_locked_json')) {
            $rates = grid402_read_locked_json($fp, []);
        } else {
            rewind($fp);
            $raw = stream_get_contents($fp);
            $decoded = ($raw !== false && trim($raw) !== '') ? @json_decode($raw, true) : [];
            $rates = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rates)) {
            $rates = [];
        }

        // Limpeza de IPs expirados fora da janela configurada
        $cleanRates = [];
        foreach ($rates as $ipKey => $data) {
            if (is_array($data) && isset($data['first_request']) && ($now - (int) $data['first_request']) < $windowSeconds) {
                $cleanRates[$ipKey] = $data;
            }
        }

        // Pseudonimizacao criptografica do IP via HMAC-SHA256 (LGPD Art. 6º, III e GDPR Art. 5(1)(c))
        $ipSecret = defined('GRID402_CRON_SECRET') ? GRID402_CRON_SECRET : 'grid402_ip_salt';
        $ipHashKey = 'ip_' . substr(hash_hmac('sha256', trim($clientIp), $ipSecret), 0, 24);

        $ipData = $cleanRates[$ipHashKey] ?? [
            'count' => 0,
            'first_request' => $now,
            'last_request' => $now,
        ];
        $currentCount = (int) ($ipData['count'] ?? 0);
        $firstReq = (int) ($ipData['first_request'] ?? $now);

        if ($currentCount >= $maxRequests) {
            $retryAfter = max(1, $windowSeconds - ($now - $firstReq));
            return [
                'allowed' => false,
                'count' => $currentCount,
                'remaining' => 0,
                'retry_after' => $retryAfter,
                'limit' => $maxRequests,
                'window_seconds' => $windowSeconds,
            ];
        }

        $ipData['count'] = $currentCount + 1;
        $ipData['first_request'] = $firstReq;
        $ipData['last_request'] = $now;
        $cleanRates[$ipHashKey] = $ipData;

        $encoded = json_encode($cleanRates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return $failClosed('RateLimitEncodeFailed');
        }
        if (!ftruncate($fp, 0) || !rewind($fp)) {
            return $failClosed('RateLimitTruncateFailed');
        }
        $written = fwrite($fp, $encoded);
        if ($written === false || $written !== strlen($encoded) || !fflush($fp)) {
            return $failClosed('RateLimitWriteFailed');
        }

        return [
            'allowed' => true,
            'count' => $ipData['count'],
            'remaining' => max(0, $maxRequests - $ipData['count']),
            'retry_after' => 0,
            'limit' => $maxRequests,
            'window_seconds' => $windowSeconds,
        ];
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
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
        $txs = grid402_read_locked_json($fp, []);

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
        $txs = grid402_read_locked_json($fp, []);

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
function grid402_validate_payment(
    string $txHash,
    ?int $requiredRawUnits = null,
    ?string $clientIp = null,
    ?string $endpointId = null,
    ?string $paymentNetwork = null,
    bool $deferSettle = false,
    ?string $resourceUrlOverride = null
): array {
    if (function_exists('grid402_normalize_payment_proof_header')) {
        $txHash = grid402_normalize_payment_proof_header($txHash);
    }
    // Payload x402 padrao (base64) e sensivel a maiusculas: classifica antes de normalizar
    if (grid402_classify_payment_proof($txHash) === 'x402_payload') {
        $overrideTrim = ($resourceUrlOverride !== null) ? trim($resourceUrlOverride) : '';
        if ($overrideTrim !== '') {
            $resourceUrl = $overrideTrim;
        } else {
            $resourceUrl = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
            $epClean = ($endpointId !== null) ? strtolower(trim($endpointId)) : '';
            if (
                $epClean !== ''
                && ($resourceUrl === '' || str_starts_with($resourceUrl, '/mcp') || !preg_match('#/api/v1/[a-z0-9-]+#i', $resourceUrl))
            ) {
                $canonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? rtrim((string) GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
                $resourceUrl = $canonicalBase . '/api/v1/' . $epClean;
            }
        }
        return $deferSettle
            ? grid402_verify_exact_payment(trim($txHash), $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW, $resourceUrl)
            : grid402_validate_exact_payment(trim($txHash), $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW, $resourceUrl);
    }

    $rawProof = trim($txHash);
    $txHash = strtolower($rawProof);
    $clientIp = $clientIp ?? (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    // 0. Verificacao instantanea de Passe de Sessao Pre-pago (pass_g402_...)
    if (str_starts_with($txHash, 'pass_g402_')) {
        require_once __DIR__ . '/services/pass.php';
        return grid402_consume_session_pass($txHash, $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW, $endpointId);
    }

    // 1. Verificacao de Modo Sandbox simulado via cabecalho ('demo' ou 'sandbox')
    if ($txHash === 'demo' || $txHash === 'sandbox') {
        $rate = grid402_check_sandbox_rate_limit($clientIp);
        if (!$rate['allowed']) {
            $periodLabel = (($rate['window_seconds'] ?? 86400) >= 86400) ? 'por dia' : 'por hora';
            return [
                'valid' => false,
                'sandbox' => true,
                'category' => 'sandbox_rate_limited',
                'reason' => "Limite de {$rate['limit']} consultas gratuitas de sandbox {$periodLabel} atingido para este IP. Para continuar agora sem limite de taxa (<15ms), emita um Session Pass via Pix/Cartao (a partir de R$ 12 = 400 creditos) ou Multi-Chain USDC em https://grid402.online/pass",
                'code' => 429,
                'retry_after' => $rate['retry_after'],
                'remaining' => 0,
                'upgrade_url' => 'https://grid402.online/pass',
            ];
        }

        return [
            'valid' => true,
            'sandbox' => true,
            'source' => 'sandbox',
            'reason' => 'Modo Sandbox autenticado com sucesso via cabecalho de simulacao.',
            'code' => 200,
            'remaining' => $rate['remaining'],
            'limit' => $rate['limit'],
            'window_seconds' => $rate['window_seconds'] ?? 86400,
        ];
    }

    // 1.5. Resolucao Multi-Chain (Arbitrum, Polygon, Optimism, Ethereum, Solana ou Base)
    $headerNet = $paymentNetwork ?? (string) ($_SERVER['HTTP_X_PAYMENT_NETWORK'] ?? '');
    $parsedNet = grid402_parse_network_proof($rawProof, $headerNet !== '' ? $headerNet : null);

    if ($parsedNet['invalid_network'] !== null) {
        return [
            'valid' => false,
            'error' => 'UnsupportedNetwork',
            'reason' => "Rede de pagamento nao suportada: {$parsedNet['invalid_network']}. Redes aceitas: base, arbitrum, polygon, optimism, ethereum, solana.",
            'category' => 'invalid_proof',
            'code' => 400,
        ];
    }

    if ($parsedNet['network'] !== 'base') {
        $mcResult = grid402_verify_multichain_usdc_tx(
            $parsedNet['network'],
            $parsedNet['tx_hash'],
            $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW
        );
        if (empty($mcResult['valid'])) {
            $mcErr = (string) ($mcResult['error'] ?? 'MultiChainVerificationFailed');
            $mcCat = match ($mcErr) {
                'TransactionAlreadyUsed' => 'replay',
                'TransactionExpired' => 'tx_expired',
                default => 'payment_rejected',
            };
            return [
                'valid' => false,
                'error' => $mcErr,
                'reason' => (string) ($mcResult['message'] ?? 'Falha na validacao multi-chain do pagamento.'),
                'category' => $mcCat,
                'code' => (int) ($mcResult['code'] ?? 403),
                'network' => $parsedNet['network'],
            ];
        }
        return [
            'valid' => true,
            'sandbox' => false,
            'source' => (string) ($mcResult['source'] ?? ('multichain_' . $parsedNet['network'])),
            'payer' => (string) ($mcResult['payer'] ?? ''),
            'network' => $parsedNet['network'],
            'chain_id' => $mcResult['chain_id'] ?? null,
            'raw_amount' => (int) ($mcResult['raw_amount'] ?? 0),
            'settlement_tx' => (string) ($mcResult['tx_hash'] ?? $parsedNet['tx_hash']),
            'lock_key' => (string) ($mcResult['lock_key'] ?? $parsedNet['lock_key']),
            'explorer_url' => (string) ($mcResult['explorer_url'] ?? ''),
            'block_timestamp' => $mcResult['block_timestamp'] ?? null,
            'reason' => "Pagamento USDC verificado com sucesso na rede {$parsedNet['network']}.",
            'code' => 200,
        ];
    }

    $txHash = $parsedNet['tx_hash'];

    // 2. Validacao do formato (0x + 64 hex)
    if (!preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
        return [
            'valid' => false,
            'reason' => 'Prova de pagamento invalida. Formatos aceitos: "X-Payment: 0x<64 hex>" (hash de transacao USDC na Base), "X-Payment: pass_g402_..." (Session Pass), "X-Payment: demo" (sandbox) ou payload x402 padrao em base64 (PAYMENT-SIGNATURE). Guia: /llms.txt',
            'category' => 'invalid_proof',
            'code' => 400,
        ];
    }

    // 3. Trava atomica anti-replay contra condicoes de corrida (TOCTOU)
    if (!grid402_claim_transaction_lock($txHash)) {
        return [
            'valid' => false,
            'reason' => 'Replay attack detectado: este hash de transacao ja foi liquidado anteriormente.',
            'category' => 'replay',
            'code' => 403,
        ];
    }

    $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    $targetWallet = substr(GRID402_WALLET_ADDRESS, 2); // sem 0x
    $requiredAmount = $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW;
    $displayAmount = ($requiredRawUnits !== null) ? number_format($requiredRawUnits / 1e6, 4) : GRID402_PRICE_DISPLAY;

    // 4. Auditoria on-chain no RPC da Base Mainnet
    $receipt = grid402_rpc_get_receipt($txHash);
    if ($receipt === null) {
        // Fallback endurecido na testnet Base Sepolia (somente se opt-in GRID402_ALLOW_SEPOLIA_SANDBOX=true)
        if (grid402_allow_sepolia_sandbox()) {
            $sepoliaReceipt = grid402_rpc_get_sepolia_receipt($txHash);
            if ($sepoliaReceipt !== null && strtolower((string) ($sepoliaReceipt['status'] ?? '')) === '0x1') {
                $sepoliaUsdcContract = strtolower(defined('GRID402_SEPOLIA_USDC_CONTRACT') ? (string) GRID402_SEPOLIA_USDC_CONTRACT : '0x036cbd53842c5426634e7929541ec2318f3dcf7e');
                $sepoliaValidTransfer = false;
                foreach ((array) ($sepoliaReceipt['logs'] ?? []) as $sLog) {
                    if (!is_array($sLog)) {
                        continue;
                    }
                    if (strtolower((string) ($sLog['address'] ?? '')) !== $sepoliaUsdcContract) {
                        continue;
                    }
                    $sTopics = is_array($sLog['topics'] ?? null) ? $sLog['topics'] : [];
                    if (count($sTopics) < 3 || strtolower((string) $sTopics[0]) !== $transferTopic) {
                        continue;
                    }
                    $sRecipient = substr(strtolower((string) $sTopics[2]), -40);
                    if ($sRecipient !== $targetWallet) {
                        continue;
                    }
                    $sDataHex = strtolower((string) ($sLog['data'] ?? '0x0'));
                    $sCleanHex = ltrim(str_starts_with($sDataHex, '0x') ? substr($sDataHex, 2) : $sDataHex, '0');
                    $sRawAmt = ($sCleanHex === '') ? 0 : (int) hexdec($sCleanHex);
                    if ($sRawAmt >= $requiredAmount) {
                        $sepoliaValidTransfer = true;
                        break;
                    }
                }

                if ($sepoliaValidTransfer) {
                    $sepBlockTs = grid402_extract_receipt_timestamp($sepoliaReceipt);
                    if ($sepBlockTs === null && isset($sepoliaReceipt['blockNumber']) && is_string($sepoliaReceipt['blockNumber'])) {
                        $sepBlockTs = grid402_rpc_get_sepolia_block_timestamp($sepoliaReceipt['blockNumber']);
                    }
                    $sepAge = grid402_validate_tx_block_timestamp($sepBlockTs, 'Base Sepolia');
                    if (empty($sepAge['valid'])) {
                        grid402_release_transaction_lock($txHash);
                        return [
                            'valid' => false,
                            'error' => 'TransactionExpired',
                            'category' => 'tx_expired',
                            'reason' => (string) ($sepAge['message'] ?? 'Transacao expirada na Base Sepolia.'),
                            'code' => 403,
                        ];
                    }

                    $rate = grid402_check_sandbox_rate_limit($clientIp);
                    if (!$rate['allowed']) {
                        grid402_release_transaction_lock($txHash);
                        return [
                            'valid' => false,
                            'sandbox' => true,
                            'category' => 'sandbox_rate_limited',
                            'reason' => "Limite de {$rate['limit']} consultas gratuitas de sandbox atingido para este IP.",
                            'code' => 429,
                            'retry_after' => $rate['retry_after'],
                            'remaining' => 0,
                        ];
                    }

                    return [
                        'valid' => true,
                        'sandbox' => true,
                        'source' => 'sandbox',
                        'payer' => strtolower((string) ($sepoliaReceipt['from'] ?? '')),
                        'network' => 'Base Sepolia',
                        'chain_id' => 84532,
                        'reason' => 'Transacao USDC validada com sucesso na testnet Base Sepolia (Modo Sandbox).',
                        'code' => 200,
                        'remaining' => $rate['remaining'],
                        'limit' => $rate['limit'],
                    ];
                }
            }
        }

        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'Transacao nao encontrada ou ainda nao confirmada na rede Base Mainnet. Tente novamente em alguns segundos.',
            'code' => 402,
        ];
    }

    // 5. Verificacao de status na Mainnet (0x1 = Sucesso)
    if (strtolower((string) ($receipt['status'] ?? '')) !== '0x1') {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'A transacao falhou na blockchain da Base (status de execucao diferente de 0x1).',
            'code' => 402,
        ];
    }

    // 6. Auditoria do evento ERC-20 Transfer (USDC)
    $usdcContract = GRID402_USDC_CONTRACT;

    $validTransferFound = false;
    $matchedRawAmount = 0;
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

        // Topico 2 = Destinatario (padded 32 bytes)
        $recipientTopic = strtolower((string)$topics[2]);
        $recipientAddress = substr($recipientTopic, -40);

        if ($recipientAddress !== $targetWallet) {
            continue;
        }

        // Dados = Quantidade em hexadecimal
        $dataHex = strtolower((string)($log['data'] ?? '0x0'));
        $cleanHex = ltrim(str_starts_with($dataHex, '0x') ? substr($dataHex, 2) : $dataHex, '0');
        $rawAmount = ($cleanHex === '') ? 0 : (int) hexdec($cleanHex);

        if ($rawAmount >= $requiredAmount) {
            $validTransferFound = true;
            $matchedRawAmount = $rawAmount;
            break;
        }
    }

    if (!$validTransferFound) {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'Nenhuma transferencia de USDC valida para a carteira do oraculo com valor minimo de ' . $displayAmount . ' USDC foi identificada nesta transacao.',
            'code' => 402,
        ];
    }

    // 7. Validacao obrigatoria de idade maxima do bloco (V-04): fecha replay ilimitado apos 7 dias em used_txs.json
    $blockTimestamp = grid402_extract_receipt_timestamp($receipt);
    if ($blockTimestamp === null && isset($receipt['blockNumber']) && is_string($receipt['blockNumber'])) {
        $blockTimestamp = grid402_rpc_get_evm_block_timestamp('base', $receipt['blockNumber']);
    }
    $ageCheck = grid402_validate_tx_block_timestamp($blockTimestamp, 'Base Mainnet');
    if (empty($ageCheck['valid'])) {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'error' => 'TransactionExpired',
            'category' => 'tx_expired',
            'reason' => (string) ($ageCheck['message'] ?? 'Transacao expirada na Base Mainnet.'),
            'code' => 403,
        ];
    }

    // Pagamento confirmado e trava retida com sucesso!
    return [
        'payer' => strtolower((string)($receipt['from'] ?? '')),
        'valid' => true,
        'sandbox' => false,
        'source' => 'tx_hash',
        'settlement_tx' => $txHash,
        'lock_key' => $txHash,
        'raw_amount' => $matchedRawAmount ?: $requiredAmount,
        'block_timestamp' => $blockTimestamp,
        'reason' => 'Pagamento x402 validado com sucesso na Base Mainnet.',
        'code' => 200,
    ];
}

/**
 * Monta a lista `accepts` padrão do fluxo x402 exact (usada no desafio 402 e na rejeição).
 * Em produção (Base Mainnet), expõe as redes gasless saudáveis do PayAI Facilitator:
 * Base (eip155:8453), Polygon (eip155:137), Arbitrum (eip155:42161) e, quando habilitada via
 * GRID402_SOLANA_X402_ENABLED=true, Solana (solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp).
 */
function grid402_build_accepts(int $priceRawUnits): array {
    $amountStr = (string) $priceRawUnits;
    if (GRID402_X402_NETWORK === 'base-sepolia') {
        return [[
            'scheme' => 'exact',
            'network' => GRID402_X402_NETWORK_V2,
            'asset' => GRID402_X402_USDC_CONTRACT,
            'amount' => $amountStr,
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
            'extra' => [
                'name' => GRID402_X402_TOKEN_NAME,
                'version' => GRID402_X402_TOKEN_VERSION,
            ],
        ]];
    }

    $accepts = [
        [
            'scheme' => 'exact',
            'network' => 'eip155:8453',
            'asset' => GRID402_X402_USDC_CONTRACT,
            'amount' => $amountStr,
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
            'extra' => [
                'name' => GRID402_X402_TOKEN_NAME,
                'version' => GRID402_X402_TOKEN_VERSION,
            ],
        ],
        [
            'scheme' => 'exact',
            'network' => 'eip155:137',
            'asset' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
            'amount' => $amountStr,
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
            'extra' => [
                'name' => 'USD Coin',
                'version' => '2',
            ],
        ],
        [
            'scheme' => 'exact',
            'network' => 'eip155:42161',
            'asset' => '0xaf88d065e77c8cc2239327c5edb3a432268e5831',
            'amount' => $amountStr,
            'payTo' => GRID402_WALLET_ADDRESS,
            'maxTimeoutSeconds' => 300,
            'extra' => [
                'name' => 'USD Coin',
                'version' => '2',
            ],
        ],
    ];

    if (function_exists('grid402_is_solana_x402_enabled') && grid402_is_solana_x402_enabled()) {
        $solWallet = defined('GRID402_SOLANA_WALLET_ADDRESS') ? (string) GRID402_SOLANA_WALLET_ADDRESS : 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C';
        $solMint = defined('GRID402_SOLANA_USDC_MINT') ? (string) GRID402_SOLANA_USDC_MINT : 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';
        $solFeePayer = defined('GRID402_PAYAI_SOLANA_FEE_PAYER') ? (string) GRID402_PAYAI_SOLANA_FEE_PAYER : 'CjNFTjvBhbJJd2B5ePPMHRLx1ELZpa8dwQgGL727eKww';
        $accepts[] = [
            'scheme' => 'exact',
            'network' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
            'asset' => $solMint,
            'amount' => $amountStr,
            'payTo' => $solWallet,
            'maxTimeoutSeconds' => 300,
            'extra' => [
                'feePayer' => $solFeePayer,
            ],
        ];
    }

    return $accepts;
}

/**
 * Metadados semanticos canonicos em ingles para descoberta no x402 Bazaar (resource.description, tags e output.example real).
 * Garante alto match semantico para agentes autonomos globais em vez de textos genericos ou teasers travados.
 */
function grid402_bazaar_service_metadata(?string $serviceName): array {
    $slug = strtolower(trim((string) $serviceName));
    $oracleProofSample = [
        'algorithm' => 'secp256k1-sha256',
        'signer' => defined('GRID402_WALLET_ADDRESS') ? GRID402_WALLET_ADDRESS : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
        'payload_hash' => '0x...',
        'signature' => '0x...',
    ];

    $catalog = [
        'ofac' => [
            'description' => 'Screen EVM wallet addresses, entities and individuals against US Treasury OFAC SDN, UN Consolidated Sanctions and Base AML risk with ECDSA oracle proof.',
            'tags' => ['ofac', 'sanctions', 'aml', 'compliance', 'kyb', 'base', 'x402'],
            'output_example' => ['target' => '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90', 'is_sanctioned' => false, 'risk_level' => 'LOW', 'lists_screened' => ['OFAC_SDN', 'UN_CONSOLIDATED', 'BASE_ONCHAIN'], '_oracle_proof' => $oracleProofSample],
        ],
        'dossier' => [
            'description' => 'Full 360-degree Brazil KYB corporate dossier (Receita Federal CNPJ, Simples Nacional/MEI, CGU CEIS/CNEP sanctions) with deterministic AI verdict (APPROVED, FLAGGED, REJECTED) and ECDSA proof.',
            'tags' => ['kyb', 'cnpj', 'compliance', 'brazil', 'sanctions', 'due-diligence', 'x402'],
            'output_example' => ['cnpj' => '00000000000191', 'legal_name' => 'BANCO DO BRASIL SA', 'verdict' => 'APPROVED', 'compliance_score' => 100, '_oracle_proof' => $oracleProofSample],
        ],
        'kyb-audit' => [
            'description' => 'Global corporate KYB audit combining Brazil Receita Federal CNPJ, US OFAC/UN sanctions screening and GLEIF ISO 17442 LEI verification in a single signed call.',
            'tags' => ['kyb', 'compliance', 'ofac', 'lei', 'cnpj', 'due-diligence', 'x402'],
            'output_example' => ['cnpj' => '00000000000191', 'risk_score' => 98, 'ofac_clear' => true, 'lei_verified' => true, '_oracle_proof' => $oracleProofSample],
        ],
        'compliance' => [
            'description' => 'Brazil corporate Compliance 360 risk score (0-100) cross-checking Receita Federal registration status, CGU CEIS/CNEP sanctions and tax profile.',
            'tags' => ['compliance', 'kyb', 'cnpj', 'risk-score', 'brazil', 'x402'],
            'output_example' => ['cnpj' => '00000000000191', 'compliance_score' => 100, 'status' => 'APPROVED', 'sanctions_count' => 0, '_oracle_proof' => $oracleProofSample],
        ],
        'extract' => [
            'description' => 'SSRF-hardened web page URL to clean LLM-ready Markdown extractor for RAG and AI agents, returning title, metadata, clean Markdown body and absolute links.',
            'tags' => ['web-scraping', 'markdown', 'rag', 'llm', 'extractor', 'x402'],
            'output_example' => ['url' => 'https://example.com', 'title' => 'Example Domain', 'markdown' => '# Example Domain...', 'char_count' => 1420, '_oracle_proof' => $oracleProofSample],
        ],
        'email-verify' => [
            'description' => 'Zero-PII B2B email deliverability and risk audit checking RFC syntax, authoritative DNS MX, SPF, DMARC, disposable domains and role-based addresses with 0-100 score.',
            'tags' => ['email-verification', 'b2b', 'deliverability', 'dns', 'anti-fraud', 'x402'],
            'output_example' => ['deliverable' => true, 'score' => 95, 'mx_valid' => true, 'spf_present' => true, 'dmarc_present' => true, 'disposable' => false, '_oracle_proof' => $oracleProofSample],
        ],
        'domain-audit' => [
            'description' => 'Consolidated domain security and infrastructure audit (authoritative DNS + TLS/SSL certificate + BGP/ASN routing) with risk score in one call.',
            'tags' => ['domain-audit', 'dns', 'ssl', 'asn', 'security', 'network', 'x402'],
            'output_example' => ['domain' => 'base.org', 'tls_valid' => true, 'days_until_expiry' => 64, 'asn' => 'AS13335', 'risk_score' => 95, '_oracle_proof' => $oracleProofSample],
        ],
        'lei' => [
            'description' => 'Global Legal Entity Identifier (ISO 17442) corporate identity lookup via official GLEIF registry covering 200+ jurisdictions with ECDSA oracle proof.',
            'tags' => ['lei', 'gleif', 'kyb', 'iso-17442', 'corporate-identity', 'x402'],
            'output_example' => ['lei' => '5493006MHB84DD0ZWV18', 'legal_name' => 'BANCO DO BRASIL S.A.', 'status' => 'ISSUED', 'jurisdiction' => 'BR', '_oracle_proof' => $oracleProofSample],
        ],
        'cnpj' => [
            'description' => 'Official Brazil Receita Federal CNPJ company lookup returning legal name, trade name, active status, CNAE activity codes, address and share capital.',
            'tags' => ['cnpj', 'brazil', 'kyb', 'receita-federal', 'company-data', 'x402'],
            'output_example' => ['cnpj' => '00000000000191', 'legal_name' => 'BANCO DO BRASIL SA', 'status' => 'ATIVA', 'uf' => 'DF', '_oracle_proof' => $oracleProofSample],
        ],
        'sanctions' => [
            'description' => 'Brazil CGU administrative sanctions lookup (CEIS and CNEP registries) checking whether a CNPJ is debarred or sanctioned.',
            'tags' => ['sanctions', 'ceis', 'cnep', 'cgu', 'brazil', 'compliance', 'x402'],
            'output_example' => ['cnpj' => '00000000000191', 'has_sanctions' => false, 'ceis_count' => 0, 'cnep_count' => 0, '_oracle_proof' => $oracleProofSample],
        ],
        'simples' => [
            'description' => 'Brazil Simples Nacional and SIMEI (MEI) tax regime enrollment verification by CNPJ.',
            'tags' => ['simples-nacional', 'mei', 'tax', 'cnpj', 'brazil', 'x402'],
            'output_example' => ['cnpj' => '00000000000191', 'optante_simples' => false, 'optante_mei' => false, '_oracle_proof' => $oracleProofSample],
        ],
        'market-snapshot' => [
            'description' => 'Consolidated financial and macro market snapshot combining commercial Forex (USD/EUR/GBP to BRL), Crypto spot prices, Selic/CDI/IPCA and ANBIMA calendar.',
            'tags' => ['finance', 'markets', 'forex', 'crypto', 'macro', 'x402'],
            'output_example' => ['usd_brl' => 5.75, 'btc_usd' => 95000, 'selic_pct' => 11.25, '_oracle_proof' => $oracleProofSample],
        ],
        'global-markets' => [
            'description' => 'Global exchange settlement calendar (US NYSE/Fed, EU TARGET2, UK LSE, BR B3) and 30+ European Central Bank (ECB) FX reference rates.',
            'tags' => ['forex', 'ecb', 'markets', 'settlement-calendar', 'finance', 'x402'],
            'output_example' => ['market' => 'US', 'is_open' => true, 'base' => 'USD', 'rates' => ['EUR' => 0.92, 'GBP' => 0.79, 'BRL' => 5.75], '_oracle_proof' => $oracleProofSample],
        ],
        'forex' => [
            'description' => 'Official commercial fiat exchange rates (USD, EUR, GBP to BRL) with daily percentage change and ECDSA oracle proof.',
            'tags' => ['forex', 'fx', 'currency', 'brl', 'finance', 'x402'],
            'output_example' => ['rates' => ['USD' => ['bid' => 5.75, 'ask' => 5.76], 'EUR' => ['bid' => 6.20, 'ask' => 6.21]], '_oracle_proof' => $oracleProofSample],
        ],
        'crypto' => [
            'description' => 'Real-time cryptocurrency spot prices and 24h changes for BTC, ETH, SOL, USDC and BASE in USD and BRL with signed oracle proof.',
            'tags' => ['crypto', 'prices', 'btc', 'eth', 'sol', 'base', 'x402'],
            'output_example' => ['prices' => ['BTC' => ['usd' => 95000], 'ETH' => ['usd' => 3400], 'SOL' => ['usd' => 220]], '_oracle_proof' => $oracleProofSample],
        ],
        'dns' => [
            'description' => 'Authoritative DNS record resolution returning A, AAAA, MX, TXT, NS and CNAME records with query latency.',
            'tags' => ['dns', 'network', 'domain', 'infrastructure', 'x402'],
            'output_example' => ['domain' => 'base.org', 'records' => ['A' => ['104.18.0.1'], 'MX' => ['aspmx.l.google.com']], '_oracle_proof' => $oracleProofSample],
        ],
        'ssl' => [
            'description' => 'Port 443 TLS/SSL certificate inspector returning issuer, validity dates, SANs, cipher suite and days until expiration.',
            'tags' => ['ssl', 'tls', 'security', 'certificates', 'network', 'x402'],
            'output_example' => ['host' => 'base.org', 'valid' => true, 'issuer' => 'Google Trust Services', 'days_remaining' => 64, '_oracle_proof' => $oracleProofSample],
        ],
        'asn' => [
            'description' => 'BGP routing and ASN intelligence returning ASN number, IP prefix, owner organization and country for any IPv4/IPv6 address.',
            'tags' => ['asn', 'bgp', 'ip-intelligence', 'network', 'security', 'x402'],
            'output_example' => ['ip' => '1.1.1.1', 'asn' => 'AS13335', 'organization' => 'Cloudflare, Inc.', 'country' => 'US', '_oracle_proof' => $oracleProofSample],
        ],
        'cep' => [
            'description' => 'Official Brazil postal code (CEP) lookup returning street, neighborhood, city, state and IBGE municipality code.',
            'tags' => ['cep', 'postal-code', 'address', 'ibge', 'brazil', 'x402'],
            'output_example' => ['cep' => '01310-100', 'street' => 'Avenida Paulista', 'city' => 'Sao Paulo', 'state' => 'SP', 'ibge' => '3550308', '_oracle_proof' => $oracleProofSample],
        ],
        'indicators' => [
            'description' => 'Official Brazil Central Bank (BCB) and IBGE macroeconomic indicators: Selic target/daily, CDI and 12-month IPCA inflation.',
            'tags' => ['macro', 'selic', 'cdi', 'ipca', 'bcb', 'brazil', 'x402'],
            'output_example' => ['selic_target_pct' => 11.25, 'cdi_annual_pct' => 11.15, 'ipca_12m_pct' => 4.5, '_oracle_proof' => $oracleProofSample],
        ],
        'holidays' => [
            'description' => 'Official Brazil national and ANBIMA/Febraban banking holidays calendar for any year (1900-2100).',
            'tags' => ['holidays', 'anbima', 'banking-calendar', 'brazil', 'finance', 'x402'],
            'output_example' => ['year' => 2026, 'total_holidays' => 12, 'holidays' => [['date' => '2026-01-01', 'name' => 'Confraternizacao Universal']], '_oracle_proof' => $oracleProofSample],
        ],
        'workdays' => [
            'description' => 'ANBIMA banking business-day calculator and D+N settlement maturity projector for Brazilian financial operations.',
            'tags' => ['workdays', 'anbima', 'settlement', 'finance', 'brazil', 'x402'],
            'output_example' => ['start_date' => '2026-12-23', 'end_date' => '2026-12-28', 'business_days' => 2, 'is_business_day' => true, '_oracle_proof' => $oracleProofSample],
        ],
        'notarize' => [
            'description' => 'M2M cryptographic timestamping and SHA-256 proof-of-existence notarization signed with ECDSA secp256k1.',
            'tags' => ['notarization', 'timestamp', 'sha256', 'ecdsa', 'cryptography', 'x402'],
            'output_example' => ['sha256' => '0x...', 'timestamp_utc' => '2026-11-23T20:00:00Z', '_oracle_proof' => $oracleProofSample],
        ],
        'pass' => [
            'description' => 'Issue a sub-15ms x402 prepaid Session Pass (Starter 2 USDC = 400 credits, Pro 5 USDC = 1,200 credits, Fleet 20 USDC = 6,000 credits) with Spend Controls.',
            'tags' => ['session-pass', 'prepaid', 'high-frequency', 'x402', 'usdc'],
            'output_example' => ['token' => 'pass_g402_...', 'credits_total' => 400, 'credits_remaining' => 400, 'expires_at' => '2027-05-22T00:00:00Z'],
        ],
    ];

    return $catalog[$slug] ?? [
        'description' => $slug !== ''
            ? "Verifiable public data API '{$slug}' by Grid402 with ECDSA secp256k1 oracle proof and HTTP x402 settlement."
            : 'Verifiable public data and network utilities API by Grid402 with ECDSA oracle proof and HTTP x402 settlement.',
        'tags' => ['public-data', 'oracle', 'x402', 'base', 'ai-agents'],
        'output_example' => null,
    ];
}

/**
 * Constroi o objeto canônico `resource` para respostas 402 e payloads x402 v2 (Bazaar).
 */
function grid402_build_resource_info(?string $serviceName = null, ?string $priceDisplay = null, ?string $fullUrl = null, ?string $customDescription = null): array {
    $priceDisplay = $priceDisplay ?? GRID402_PRICE_DISPLAY;
    $canonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';

    if ($fullUrl === null || $fullUrl === '') {
        $host = $_SERVER['HTTP_HOST'] ?? (defined('GRID402_CANONICAL_HOST') ? GRID402_CANONICAL_HOST : 'grid402.online');
        $isHttps = (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] === 'on') or (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) and $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $proto = $isHttps ? 'https' : 'http';
        $reqUri = $_SERVER['REQUEST_URI'] ?? ('/api/v1/' . ($serviceName ?? 'forex'));
        $resourceBase = rtrim((string) grid402_env('GRID402_X402_RESOURCE_URL', ''), '/');
        $fullUrl = ($resourceBase !== '') ? ($resourceBase . $reqUri) : ($proto . '://' . $host . $reqUri);
    } elseif (str_starts_with($fullUrl, '/')) {
        $fullUrl = $canonicalBase . $fullUrl;
    }

    $meta = grid402_bazaar_service_metadata($serviceName);
    $msg = ($customDescription !== null && trim($customDescription) !== '')
        ? $customDescription
        : ($meta['description'] ?? ($serviceName
            ? "Access to Grid402 '{$serviceName}' API ({$priceDisplay} USDC via HTTP x402)."
            : 'Verifiable public data query via HTTP x402 protocol.'));

    return [
        'url' => $fullUrl,
        'description' => $msg,
        'mimeType' => 'application/json',
        'serviceName' => 'Grid402 ' . strtoupper((string) ($serviceName ?? 'hub')),
        'tags' => $meta['tags'] ?? ['public-data', 'oracle', 'x402', 'base', 'ai-agents'],
        'iconUrl' => $canonicalBase . '/assets/icon-192x192.png',
    ];
}

function grid402_bazaar_default_query_params(?string $serviceName): array {
    return match (strtolower(trim((string) $serviceName))) {
        'dns', 'domain-audit' => ['domain' => 'base.org'],
        'ssl' => ['host' => 'base.org'],
        'asn' => ['ip' => '1.1.1.1'],
        'cep' => ['code' => '01310100'],
        'cnpj', 'sanctions', 'simples', 'compliance', 'kyb-audit', 'dossier' => ['cnpj' => '00000000000191'],
        'holidays' => ['year' => '2026'],
        'workdays' => ['start_date' => '2026-12-23', 'end_date' => '2026-12-28'],
        'lei' => ['lei' => '5493006MHB84DD0ZWV18'],
        'ofac' => ['target' => '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'],
        'notarize' => ['content' => 'Grid402-M2M-Contract-v1'],
        'global-markets' => ['market' => 'US', 'base' => 'USD'],
        'extract' => ['url' => 'https://example.com'],
        'email-verify' => ['email' => 'contato@grid402.online'],
        default => [],
    };
}

/**
 * Constroi a extensao `extensions.bazaar` conforme @x402/extensions/bazaar (v2 createQueryDiscoveryExtension / POST)
 * com `info` + `schema` JSON Schema 2020-12 estrito (`additionalProperties: false` em `input`).
 */
function grid402_build_bazaar_extension(?array $exampleOutput = null, ?string $serviceName = null, ?string $httpMethod = null): array {
    $meta = grid402_bazaar_service_metadata($serviceName);
    $canonicalExample = is_array($meta['output_example'] ?? null) ? $meta['output_example'] : null;
    $example = $canonicalExample
        ?? (($exampleOutput !== null && !empty($exampleOutput)) ? $exampleOutput : ['status' => 'success']);
    $queryDefaults = grid402_bazaar_default_query_params($serviceName);

    $method = strtoupper(trim((string) ($httpMethod ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'))));
    if (!in_array($method, ['GET', 'POST', 'HEAD', 'DELETE'], true)) {
        $method = 'GET';
    }

    $inputInfo = [
        'type' => 'http',
        'method' => $method,
    ];
    $querySchemaProps = [];
    if (!empty($queryDefaults)) {
        $inputInfo['queryParams'] = $queryDefaults;
        foreach ($queryDefaults as $paramName => $_val) {
            $querySchemaProps[$paramName] = ['type' => 'string'];
        }
    }

    $inputSchemaProps = [
        'type' => ['type' => 'string', 'const' => 'http'],
        'method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'HEAD', 'DELETE']],
    ];
    if ($method === 'POST') {
        $inputInfo['bodyType'] = 'json';
        $inputSchemaProps['bodyType'] = ['type' => 'string', 'enum' => ['json', 'form-data', 'text']];
        $inputSchemaProps['body'] = ['type' => 'object'];
    }
    // So declara queryParams no schema quando ha parametros; evita `{}` virar `[]` em round-trips PHP assoc
    if (!empty($querySchemaProps)) {
        $inputSchemaProps['queryParams'] = [
            'type' => 'object',
            'properties' => $querySchemaProps,
        ];
    }

    return [
        'info' => [
            'input' => $inputInfo,
            'output' => [
                'type' => 'json',
                'example' => $example,
            ],
        ],
        'schema' => [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['input'],
            'properties' => [
                'input' => [
                    'type' => 'object',
                    'required' => ['type', 'method'],
                    'properties' => $inputSchemaProps,
                    'additionalProperties' => false,
                ],
                'output' => [
                    'type' => 'object',
                    'required' => ['type'],
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'example' => ['type' => 'object'],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Constroi e emite o cabecalho padrao x402 v2 PAYMENT-REQUIRED (base64).
 */
function grid402_emit_payment_required_header(?string $serviceName, string $priceDisplay, int $priceRawUnits, string $errorText = 'Payment required', ?array $preview = null, ?string $resourceDescription = null): array {
    $acceptsList = grid402_build_accepts($priceRawUnits);
    $resourceInfo = grid402_build_resource_info($serviceName, $priceDisplay, null, $resourceDescription);
    $bazaarInfo = grid402_build_bazaar_extension($preview, $serviceName);

    $x402V2HeaderData = [
        'x402Version' => 2,
        'error' => $errorText,
        'resource' => $resourceInfo,
        'accepts' => $acceptsList,
        'extensions' => [
            'bazaar' => $bazaarInfo,
        ],
    ];

    $b64Header = base64_encode((string) json_encode($x402V2HeaderData, JSON_UNESCAPED_SLASHES));
    header('PAYMENT-REQUIRED: ' . $b64Header);

    return [
        'accepts' => $acceptsList,
        'resource' => $resourceInfo,
        'bazaar' => $bazaarInfo,
        'header' => $b64Header,
    ];
}

/**
 * Emite a resposta HTTP 402 Payment Required estruturada compativel com x402 v2
 */
function grid402_respond_402(?string $serviceName = null, ?string $errorMessage = null, ?string $priceDisplay = null, ?int $priceRawUnits = null, ?array $preview = null, ?array $lockedFields = null): void {
    $priceDisplay = $priceDisplay ?? GRID402_PRICE_DISPLAY;
    $priceRawUnits = $priceRawUnits ?? GRID402_USDC_REQUIRED_RAW;

    if (function_exists('grid402_record_telemetry')) {
        grid402_record_telemetry($serviceName ?? 'unknown', 402, false, null, null, (float)$priceDisplay);
    }
    http_response_code(402);
    header('HTTP/1.1 402 Payment Required', true, 402);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    if (function_exists('grid402_emit_request_id_header')) {
        grid402_emit_request_id_header();
    }
    header('X-Authenticate: x402');
    header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . $priceDisplay);
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Authenticate, X-Accept-Payment, X-Payment-Status, X-Payment-Amount, X-Payment-Network, PAYMENT-REQUIRED, Payment-Required, payment-required, PAYMENT-RESPONSE, X-PAYMENT-RESPONSE, X-Idempotent-Replay, X-Request-Id');

    $bazaarMeta = grid402_bazaar_service_metadata($serviceName);
    $msg = $errorMessage ?? ($serviceName
        ? "Access to Grid402 '{$serviceName}' API requires a {$priceDisplay} USDC micropayment via HTTP x402 (or a prepaid Session Pass)."
        : 'Query requires on-demand micropayment via HTTP x402 protocol.');

    $prBundle = grid402_emit_payment_required_header($serviceName, $priceDisplay, (int) $priceRawUnits, 'Payment required', $preview, $errorMessage);
    $acceptsList = $prBundle['accepts'];
    $resourceInfo = $prBundle['resource'];
    $bazaarInfo = $prBundle['bazaar'];

    // Gatilhos de conversão visíveis apenas para clientes que não pagaram (402).
    // Não altera requisitos de pagamento, apenas informa opções.
    $growthCfgForAds = ['starter_promo_enabled' => false, 'starter_promo_credits' => 10, 'starter_promo_requires_first_paid' => true];
    if (function_exists('grid402_growth_read')) {
        $growthDataForAds = grid402_growth_read();
        $growthCfgForAds = $growthDataForAds['config'] ?? $growthCfgForAds;
    }
    $canonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online';
    $fiatAndCardCheckout = [
        'pix_instant_brl' => [
            'starter_url' => $canonicalBase . '/pass?tier=starter&autopix=1',
            'pro_url' => $canonicalBase . '/pass?tier=pro&autopix=1',
            'fleet_url' => $canonicalBase . '/pass?tier=fleet&autopix=1',
            'pricing' => 'Starter R$ 12 (400 credits, 180d) | Pro R$ 29 (1,200 credits, 180d) | Fleet R$ 115 (6,000 credits, 365d)',
            'header_usage' => 'X-Payment: pass_g402_<token>',
        ],
        'rapidapi_credit_card_usd' => [
            'plans' => 'BASIC Free (25/mo) | PRO $9.90/mo (1,500 std calls) | ULTRA $29.00/mo (6,000 std calls) | MEGA $79.00/mo (25,000 std calls; dedicated KYB quotas for /dossier and /compliance)',
            'all_25_apis_url' => 'https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities',
            'web_markdown_email_network_url' => 'https://rapidapi.com/oliveiratc/api/grid402-web-to-markdown-email-verify-network-utilities',
            'brazil_kyb_cnpj_anbima_url' => 'https://rapidapi.com/oliveiratc/api/brazil-kyb-cnpj-cgu-sanctions-anbima-workdays-api',
        ],
    ];
    $conversionInfo = [
        'starter_promo' => [
            'enabled' => (bool)($growthCfgForAds['starter_promo_enabled'] ?? false),
            'credits' => (int)($growthCfgForAds['starter_promo_credits'] ?? 10),
            'claim_url' => $canonicalBase . '/growth',
        ],
        'session_pass' => [
            'description' => 'Sub-15ms prepaid credits with Spend Controls (Pix, Card or Multi-Chain USDC)',
            'url' => $canonicalBase . '/pass',
            'pix_instant_starter_url' => $canonicalBase . '/pass?tier=starter&autopix=1',
        ],
        'rapidapi_url' => $fiatAndCardCheckout['rapidapi_credit_card_usd']['all_25_apis_url'],
        'catalog_url' => $canonicalBase . '/catalog',
    ];

    $payload = [
        'x402Version' => 2,
        'error' => 'PaymentRequired',
        'message' => $msg,
        'resource' => $resourceInfo,
        'accepts' => $acceptsList,
        'protocol' => 'x402',
        'service' => $serviceName ?? 'unknown',
        'network' => (GRID402_X402_NETWORK === 'base-sepolia' ? 'Base Sepolia' : 'Base'),
        'chain_id' => GRID402_X402_CHAIN_ID,
        'token' => 'USDC',
        'token_contract' => GRID402_X402_USDC_CONTRACT,
        'price_usdc' => $priceDisplay,
        'price_raw_units' => $priceRawUnits,
        'credits_cost' => max(1, (int) ceil($priceRawUnits / 5000)),
        'recipient_wallet' => GRID402_WALLET_ADDRESS,
        'supported_networks' => grid402_supported_networks_public_summary(),
        'extensions' => [
            'bazaar' => $bazaarInfo,
        ],
        'sandbox_test' => [
            'header' => 'X-Payment: demo',
            'rate_limit' => GRID402_SANDBOX_RATE_LIMIT . ' req/day per IP',
            'description' => 'Test the full response schema for free by sending header X-Payment: demo.',
        ],
        'action_required' => "1) Sign x402 v2 PAYMENT-SIGNATURE (gasless via PayAI on " . ((function_exists('grid402_is_solana_x402_enabled') && grid402_is_solana_x402_enabled()) ? 'Base/Polygon/Arbitrum/Solana' : 'Base/Polygon/Arbitrum') . ") or send {$priceDisplay} USDC and retry with X-Payment: <tx_hash>. 2) Sub-15ms Session Pass (USDC or Pix R$12 = 400 credits): {$canonicalBase}/pass?tier=starter&autopix=1 -> X-Payment: pass_g402_<token>. 3) Free Sandbox (" . GRID402_SANDBOX_RATE_LIMIT . " req/day): X-Payment: demo.",
        'fiat_and_card_checkout' => $fiatAndCardCheckout,
        'conversion' => $conversionInfo,
        'docs' => $canonicalBase . '/llms.txt',
    ];

    if ($preview !== null and !empty($preview)) {
        $payload['preview'] = $preview;
        $payload['teaser_data'] = $preview;
    }
    if ($lockedFields !== null and !empty($lockedFields)) {
        $payload['locked_fields'] = $lockedFields;
    }

    if (function_exists('grid402_log_request')) {
        grid402_log_request([
            'service' => $serviceName ?? 'unknown',
            'status' => 402,
            'payment_source' => 'none',
            'error' => 'PaymentRequired',
        ]);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Caminho privado para gravar o resultado resumido (sem segredos) do ultimo envio Telegram.
 */
function grid402_telegram_status_path(): string {
    $dir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/private_data');
    return $dir . '/telegram_last_status.json';
}

/**
 * Caminho privado para credenciais Telegram salvas via painel /admin (fallback quando .env nao contem).
 */
function grid402_telegram_config_path(): string {
    $dir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/private_data');
    return $dir . '/telegram_config.json';
}

function grid402_read_telegram_config(): array {
    $path = grid402_telegram_config_path();
    if (!is_file($path)) {
        return ['bot_token' => '', 'chat_id' => ''];
    }
    $raw = @file_get_contents($path);
    $decoded = $raw ? @json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return ['bot_token' => '', 'chat_id' => ''];
    }
    return [
        'bot_token' => trim((string) ($decoded['bot_token'] ?? '')),
        'chat_id' => trim((string) ($decoded['chat_id'] ?? '')),
    ];
}

function grid402_save_telegram_config(string $botToken, string $chatId): bool {
    $path = grid402_telegram_config_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = json_encode([
        'bot_token' => trim($botToken),
        'chat_id' => trim($chatId),
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payload)) {
        return false;
    }
    return (@file_put_contents($path, $payload, LOCK_EX) !== false);
}

/**
 * Resolve credenciais do Telegram priorizando variaveis de ambiente (.env) com fallback para telegram_config.json.
 */
function grid402_resolve_telegram_credentials(): array {
    $envToken = defined('GRID402_TELEGRAM_BOT_TOKEN') ? GRID402_TELEGRAM_BOT_TOKEN : (string) grid402_env('GRID402_TELEGRAM_BOT_TOKEN', '');
    $envChat = defined('GRID402_TELEGRAM_CHAT_ID') ? GRID402_TELEGRAM_CHAT_ID : (string) grid402_env('GRID402_TELEGRAM_CHAT_ID', '');
    $envToken = trim($envToken);
    $envChat = trim($envChat);

    if ($envToken !== '' and $envChat !== '') {
        return ['token' => $envToken, 'chat_id' => $envChat, 'source' => 'env'];
    }

    $fileCfg = grid402_read_telegram_config();
    $token = $envToken !== '' ? $envToken : $fileCfg['bot_token'];
    $chatId = $envChat !== '' ? $envChat : $fileCfg['chat_id'];
    $source = ($token !== '' and $chatId !== '') ? (($envToken !== '' or $envChat !== '') ? 'mixed' : 'admin_file') : 'none';

    return ['token' => $token, 'chat_id' => $chatId, 'source' => $source];
}

function grid402_record_telegram_status(bool $ok, int $httpCode, string $error, string $service, bool $isTest): void {
    $path = grid402_telegram_status_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = json_encode([
        'ok' => $ok,
        'http_code' => $httpCode,
        'error' => substr($error, 0, 180),
        'service' => $service,
        'is_test' => $isTest,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($payload)) {
        @file_put_contents($path, $payload, LOCK_EX);
    }
}

/**
 * Snapshot seguro (sem expor token nem chat_id) da configuracao e ultimo disparo Telegram.
 */
function grid402_telegram_runtime_status(): array {
    $disabled = ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1');
    $creds = grid402_resolve_telegram_credentials();
    $token = $creds['token'];
    $chatId = $creds['chat_id'];
    $lastStatus = null;
    $path = grid402_telegram_status_path();
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = $raw ? @json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $lastStatus = $decoded;
        }
    }
    return [
        'disabled' => $disabled,
        'token_configured' => ($token !== ''),
        'chat_id_configured' => ($chatId !== ''),
        'configured' => (!$disabled and $token !== '' and $chatId !== ''),
        'source' => $creds['source'],
        'last_status' => $lastStatus,
    ];
}

/**
 * Envia codigo OTP de 6 digitos via Telegram para autenticacao de dois fatores (2FA) no portal /admin.
 */
function grid402_send_telegram_admin_otp(string $username, string $otpCode, string $maskedIp): bool {
    if (preg_match('/^\d{6}$/', $otpCode) !== 1) {
        return false;
    }

    // Modo exclusivo de captura local para suite de testes automatizados (loopback estrito)
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ((string) grid402_env('GRID402_TEST_OTP_CAPTURE', '') === '1' and in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        $dir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/private_data');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/admin_test_otp.json', (string) json_encode([
            'username' => $username,
            'otp' => $otpCode,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES), LOCK_EX);
        return true;
    }

    if ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1') {
        return false;
    }

    $creds = grid402_resolve_telegram_credentials();
    $token = $creds['token'];
    $chatId = $creds['chat_id'];
    if ($token === '' or $chatId === '') {
        return false;
    }

    $safeUser = preg_replace('/[^a-zA-Z0-9_.-]/', '', $username) ?: 'admin';
    $safeIp = preg_replace('/[^a-zA-Z0-9:._-]/', '', $maskedIp) ?: 'unknown';
    $utcNow = gmdate('d/m/Y H:i:s \U\T\C');

    $lines = [
        "🔐 <b>Código de Acesso 2FA - Grid402 Admin</b>",
        "",
        "👤 <b>Operador:</b> <code>{$safeUser}</code>",
        "🔑 <b>Código OTP:</b> <code>{$otpCode}</code>",
        "🌐 <b>Origem:</b> <code>{$safeIp}</code>",
        "⏱ <b>Validade:</b> 5 minutos",
        "📅 <b>Data:</b> {$utcNow}",
    ];
    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => implode("\n", $lines),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($payload)) {
        return false;
    }

    try {
        $ch = @curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        if (!$ch) {
            return false;
        }
        $curlOpts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Grid402-Telegram-Notifier/1.0',
        ];
        if (defined('CURLOPT_IPRESOLVE') and defined('CURL_IPRESOLVE_V4')) {
            $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        @curl_setopt_array($ch, $curlOpts);
        $rawResp = @curl_exec($ch);
        $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = (string) @curl_error($ch);
        @curl_close($ch);

        if ($httpCode === 200) {
            grid402_record_telegram_status(true, 200, '', 'admin_2fa', false);
            return true;
        }
        $tgDesc = '';
        if (is_string($rawResp) and $rawResp !== '') {
            $dec = @json_decode($rawResp, true);
            if (is_array($dec) and isset($dec['description'])) {
                $tgDesc = (string) $dec['description'];
            }
        }
        grid402_record_telegram_status(false, $httpCode, $tgDesc !== '' ? $tgDesc : ($curlErr !== '' ? $curlErr : "HTTP {$httpCode}"), 'admin_2fa', false);
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Envia codigo OTP de 6 digitos via Telegram para recuperacao de senha no portal /admin.
 */
function grid402_send_telegram_admin_recovery_otp(string $username, string $otpCode, string $maskedIp): bool {
    if (preg_match('/^\d{6}$/', $otpCode) !== 1) {
        return false;
    }

    // Modo exclusivo de captura local para suite de testes automatizados (loopback estrito)
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ((string) grid402_env('GRID402_TEST_OTP_CAPTURE', '') === '1' and in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        $dir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/private_data');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/admin_test_recovery_otp.json', (string) json_encode([
            'username' => $username,
            'otp' => $otpCode,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES), LOCK_EX);
        return true;
    }

    if ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1') {
        return false;
    }

    $creds = grid402_resolve_telegram_credentials();
    $token = $creds['token'];
    $chatId = $creds['chat_id'];
    if ($token === '' or $chatId === '') {
        return false;
    }

    $safeUser = preg_replace('/[^a-zA-Z0-9_.-]/', '', $username) ?: 'admin';
    $safeIp = preg_replace('/[^a-zA-Z0-9:._-]/', '', $maskedIp) ?: 'unknown';
    $utcNow = gmdate('d/m/Y H:i:s \U\T\C');

    $lines = [
        "🚨 <b>Recuperação de Acesso - Grid402 Admin</b>",
        "",
        "👤 <b>Operador:</b> <code>{$safeUser}</code>",
        "🔑 <b>Código de Recuperação:</b> <code>{$otpCode}</code>",
        "🌐 <b>Origem:</b> <code>{$safeIp}</code>",
        "⏱ <b>Validade:</b> 10 minutos",
        "📅 <b>Data:</b> {$utcNow}",
        "",
        "⚠️ <i>Se você não solicitou esta recuperação, ignore este alerta. Nenhuma alteração foi realizada.</i>",
    ];
    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => implode("\n", $lines),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($payload)) {
        return false;
    }

    try {
        $ch = @curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        if (!$ch) {
            return false;
        }
        $curlOpts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Grid402-Telegram-Notifier/1.0',
        ];
        if (defined('CURLOPT_IPRESOLVE') and defined('CURL_IPRESOLVE_V4')) {
            $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        @curl_setopt_array($ch, $curlOpts);
        $rawResp = @curl_exec($ch);
        $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = (string) @curl_error($ch);
        @curl_close($ch);

        if ($httpCode === 200) {
            grid402_record_telegram_status(true, 200, '', 'admin_recovery', false);
            return true;
        }
        $tgDesc = '';
        if (is_string($rawResp) and $rawResp !== '') {
            $dec = @json_decode($rawResp, true);
            if (is_array($dec) and isset($dec['description'])) {
                $tgDesc = (string) $dec['description'];
            }
        }
        grid402_record_telegram_status(false, $httpCode, $tgDesc !== '' ? $tgDesc : ($curlErr !== '' ? $curlErr : "HTTP {$httpCode}"), 'admin_recovery', false);
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Notifica o operador via Telegram que a senha do administrador foi alterada com sucesso via recuperacao.
 */
function grid402_notify_telegram_admin_password_reset(string $username, string $maskedIp): bool {
    if ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1') {
        return false;
    }
    $creds = grid402_resolve_telegram_credentials();
    $token = $creds['token'];
    $chatId = $creds['chat_id'];
    if ($token === '' or $chatId === '') {
        return false;
    }

    $safeUser = preg_replace('/[^a-zA-Z0-9_.-]/', '', $username) ?: 'admin';
    $safeIp = preg_replace('/[^a-zA-Z0-9:._-]/', '', $maskedIp) ?: 'unknown';
    $utcNow = gmdate('d/m/Y H:i:s \U\T\C');

    $lines = [
        "✅ <b>Senha Redefinida com Sucesso - Grid402 Admin</b>",
        "",
        "👤 <b>Operador:</b> <code>{$safeUser}</code>",
        "🌐 <b>Origem:</b> <code>{$safeIp}</code>",
        "📅 <b>Data:</b> {$utcNow}",
        "",
        "🔒 <i>A senha do painel foi redefinida com sucesso e a sessão administrativa foi liberada.</i>",
    ];
    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => implode("\n", $lines),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($payload)) {
        return false;
    }

    try {
        $ch = @curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        if (!$ch) {
            return false;
        }
        $curlOpts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Grid402-Telegram-Notifier/1.0',
        ];
        if (defined('CURLOPT_IPRESOLVE') and defined('CURL_IPRESOLVE_V4')) {
            $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        @curl_setopt_array($ch, $curlOpts);
        $res = @curl_exec($ch);
        $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        return ($httpCode === 200);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Notificacao em tempo real de micropagamentos x402 no Telegram
 * Disparo nao-bloqueante (IPv4 forcado, timeout curto) com registro de diagnostico sem segredos.
 */
function grid402_notify_telegram_purchase(string $service, float $priceUsdc, string $txHash, bool $isTest = false, string $network = 'base'): bool {
    $cleanSvc = strtolower(trim($service));
    if ((string) grid402_env('GRID402_DISABLE_TELEGRAM', '') === '1') {
        return false;
    }

    $creds = grid402_resolve_telegram_credentials();
    $token = $creds['token'];
    $chatId = $creds['chat_id'];

    if ($token === '' or $chatId === '') {
        grid402_record_telegram_status(false, 0, 'Credenciais GRID402_TELEGRAM_BOT_TOKEN / GRID402_TELEGRAM_CHAT_ID ausentes neste servidor.', $cleanSvc, $isTest);
        return false;
    }

    $cleanTx = trim($txHash);
    $lowerTx = strtolower($cleanTx);
    if (str_starts_with($lowerTx, 'pass_g402_')) {
        return false;
    }

    $netSlug = grid402_normalize_network_slug($network) ?? 'base';
    $isEvmHash = (preg_match('/^0x[a-f0-9]{64}$/i', $cleanTx) === 1);
    $isSolSig = ($netSlug === 'solana' and grid402_is_valid_solana_signature($cleanTx));
    $isOnChainHash = ($isEvmHash or $isSolSig);
    $isPixOrderRef = (!$isOnChainHash and str_contains($cleanSvc, 'pix/mp') and preg_match('/^[a-zA-Z0-9_-]{4,64}$/', $cleanTx) === 1);
    if (!$isOnChainHash and !$isPixOrderRef) {
        return false;
    }

    $serviceNames = [
        'cnpj' => 'Receita Federal CNPJ',
        'cep' => 'Correios e IBGE Geolocation CEP',
        'forex' => 'Cotacoes de Cambio BRL',
        'crypto' => 'Precos Cripto Spot (BTC, ETH, SOL)',
        'indicators' => 'Indicadores Economicos (Selic, CDI e IPCA)',
        'holidays' => 'Feriados Nacionais e Bancarios ANBIMA',
        'workdays' => 'Calculadora de Dias Uteis Financeiros',
        'dns' => 'Utilitario DNS Avancado',
        'ssl' => 'Auditoria de Certificado SSL e TLS',
        'asn' => 'Roteamento BGP e ASN de Rede',
        'sanctions' => 'Sancoes CGU (CEIS e CNEP)',
        'simples' => 'Simples Nacional e MEI',
        'compliance' => 'Compliance Score 360',
        'lei' => 'Identidade Global GLEIF LEI',
        'ofac' => 'OFAC SDN / ONU e AML Web3',
        'notarize' => 'Notarizacao M2M ECDSA',
        'global-markets' => 'Mercados Globais e Cambio BCE',
        'pass' => 'Session Pass x402',
        'domain-audit' => 'Auditoria Completa de Dominio (DNS + SSL + ASN)',
        'kyb-audit' => 'Auditoria KYB e Compliance (CNPJ + OFAC + LEI)',
        'dossier' => 'Dossie 360 KYB + Veredito IA (CNPJ + Simples + CGU + Score)',
        'market-snapshot' => 'Snapshot Consolidado de Mercado (Forex + Crypto + Indicadores)',
    ];

    $displayName = $serviceNames[$cleanSvc] ?? ('Servico ' . strtoupper($service));
    $displayPrice = number_format($priceUsdc, 4, '.', '') . ' USDC';
    $nowUtc = gmdate('Y-m-d H:i:s') . ' UTC';

    $isTestTx = $isTest || ($isOnChainHash and preg_match('/^0x(a{64}|0{64}|f{64}|1{64}|demo)$/i', $cleanTx) === 1);
    $titleHeader = $isTestTx ? "🧪 *TESTE DO SISTEMA: Simulação de Compra no Grid402*" : "💰 *Nova Compra no Grid402!*";

    $netMetaMap = grid402_supported_networks();
    $netMeta = $netMetaMap[$netSlug] ?? $netMetaMap['base'];
    $explorerNames = [
        'base' => 'BaseScan',
        'arbitrum' => 'Arbiscan',
        'polygon' => 'PolygonScan',
        'optimism' => 'Optimistic Etherscan',
        'ethereum' => 'Etherscan',
        'solana' => 'Solscan',
    ];
    $explorerName = $explorerNames[$netSlug] ?? 'Explorer';
    $explorerLink = grid402_explorer_tx_url($netSlug, $cleanTx);

    $netLabel = $isPixOrderRef
        ? 'Pix / Mercado Pago (BRL)'
        : ($netMeta['display_name'] . (!empty($netMeta['chain_id']) ? (' (ChainID ' . $netMeta['chain_id'] . ')') : ''));
    $proofLine = $isPixOrderRef
        ? ("🧾 *Comprovante MP:* `" . $cleanTx . "`")
        : ("🔗 *Transacao:* [" . $explorerName . "](" . $explorerLink . ")");

    $text = $titleHeader . "\n\n"
          . "📦 *Servico:* " . $displayName . "\n"
          . "💵 *Valor:* " . $displayPrice . "\n"
          . "🌐 *Rede:* " . $netLabel . "\n"
          . "🕒 *Data:* " . $nowUtc . "\n"
          . ($isTestTx ? "ℹ️ *Nota:* Disparo originado por teste automatizado.\n" : "")
          . $proofLine;

    $apiUrl = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payloadJson = json_encode([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = @curl_init($apiUrl);
    if (!$ch) {
        grid402_record_telegram_status(false, 0, 'curl_init falhou', $cleanSvc, $isTestTx);
        return false;
    }

    @curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $resp = @curl_exec($ch);
    $curlErr = ($resp === false) ? (string) @curl_error($ch) : '';
    $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);

    $ok = ($httpCode === 200);
    $errMsg = '';
    if (!$ok) {
        $respData = is_string($resp) ? @json_decode($resp, true) : null;
        $tgDesc = is_array($respData) ? (string) ($respData['description'] ?? '') : '';
        $errMsg = $tgDesc !== '' ? $tgDesc : ($curlErr !== '' ? $curlErr : ('HTTP ' . $httpCode));
    }
    grid402_record_telegram_status($ok, $httpCode, $errMsg, $cleanSvc, $isTestTx);

    return $ok;
}
