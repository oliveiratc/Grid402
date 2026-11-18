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
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
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
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'Grid402-x402-Sepolia-Auditor/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
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

    return null;
}

/**
 * Controle defensivo de taxa (Rate Limit) para requisicoes em Modo Sandbox
 * Permite ate 5 requisicoes por hora por IP
 */
function grid402_check_sandbox_rate_limit(string $clientIp): array {
    $file = defined('GRID402_FILE_SANDBOX_RATES') ? GRID402_FILE_SANDBOX_RATES : (dirname(__DIR__) . '/private_data/sandbox_rates.json');
    $maxRequests = defined('GRID402_SANDBOX_RATE_LIMIT') ? GRID402_SANDBOX_RATE_LIMIT : 5;
    $windowSeconds = 3600;
    $now = time();

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $rates = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) {
            $rates = @json_decode($raw, true) ?: [];
        }
    }

    // Limpeza de IPs expirados (mais de 1 hora)
    $cleanRates = [];
    foreach ($rates as $ipKey => $data) {
        if (isset($data['first_request']) && ($now - $data['first_request']) < $windowSeconds) {
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

    if ($ipData['count'] >= $maxRequests) {
        $retryAfter = max(1, $windowSeconds - ($now - $ipData['first_request']));
        return [
            'allowed' => false,
            'count' => $ipData['count'],
            'remaining' => 0,
            'retry_after' => $retryAfter,
            'limit' => $maxRequests,
        ];
    }

    $ipData['count']++;
    $ipData['last_request'] = $now;
    $cleanRates[$ipHashKey] = $ipData;

    if (function_exists('grid402_atomic_write')) {
        grid402_atomic_write($file, $cleanRates);
    } else {
        @file_put_contents($file, json_encode($cleanRates, JSON_PRETTY_PRINT), LOCK_EX);
    }

    return [
        'allowed' => true,
        'count' => $ipData['count'],
        'remaining' => max(0, $maxRequests - $ipData['count']),
        'retry_after' => 0,
        'limit' => $maxRequests,
    ];
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
function grid402_validate_payment(string $txHash, ?int $requiredRawUnits = null, ?string $clientIp = null, ?string $endpointId = null): array {
    // Payload x402 padrao (base64) e sensivel a maiusculas: classifica antes de normalizar
    if (grid402_classify_payment_proof($txHash) === 'x402_payload') {
        $resourceUrl = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        return grid402_validate_exact_payment(trim($txHash), $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW, $resourceUrl);
    }

    $txHash = strtolower(trim($txHash));
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
            return [
                'valid' => false,
                'sandbox' => true,
                'reason' => "Limite de {$rate['limit']} consultas gratuitas de sandbox por hora atingido para este IP. Para chamadas em producao sem limite de taxa, utilize micropagamentos em USDC na Base Mainnet.",
                'code' => 429,
                'retry_after' => $rate['retry_after'],
                'remaining' => 0,
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
        ];
    }

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

    // 4. Auditoria on-chain no RPC da Base Mainnet
    $receipt = grid402_rpc_get_receipt($txHash);
    if ($receipt === null) {
        // Fallback: tenta auditoria na testnet Base Sepolia
        $sepoliaReceipt = grid402_rpc_get_sepolia_receipt($txHash);
        if ($sepoliaReceipt !== null && ($sepoliaReceipt['status'] ?? '') === '0x1') {
            $rate = grid402_check_sandbox_rate_limit($clientIp);
            if (!$rate['allowed']) {
                grid402_release_transaction_lock($txHash);
                return [
                    'valid' => false,
                    'sandbox' => true,
                    'reason' => "Limite de {$rate['limit']} consultas gratuitas de sandbox por hora atingido para este IP.",
                    'code' => 429,
                    'retry_after' => $rate['retry_after'],
                    'remaining' => 0,
                ];
            }

            return [
                'valid' => true,
                'sandbox' => true,
                'source' => 'sandbox',
                'network' => 'Base Sepolia',
                'chain_id' => 84532,
                'reason' => 'Transacao validada com sucesso na testnet Base Sepolia (Modo Sandbox).',
                'code' => 200,
                'remaining' => $rate['remaining'],
                'limit' => $rate['limit'],
            ];
        }

        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'Transacao nao encontrada ou ainda nao confirmada na rede Base (Mainnet ou Sepolia). Tente novamente em alguns segundos.',
            'code' => 402,
        ];
    }

    // 5. Verificacao de status na Mainnet (0x1 = Sucesso)
    if (($receipt['status'] ?? '') !== '0x1') {
        grid402_release_transaction_lock($txHash);
        return [
            'valid' => false,
            'reason' => 'A transacao falhou na blockchain da Base (status de execucao diferente de 0x1).',
            'code' => 402,
        ];
    }

    // 6. Auditoria do evento ERC-20 Transfer (USDC)
    $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    $usdcContract = GRID402_USDC_CONTRACT;
    $targetWallet = substr(GRID402_WALLET_ADDRESS, 2); // sem 0x
    $requiredAmount = $requiredRawUnits ?? GRID402_USDC_REQUIRED_RAW;
    $displayAmount = ($requiredRawUnits !== null) ? number_format($requiredRawUnits / 1e6, 4) : GRID402_PRICE_DISPLAY;

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

        // Topico 2 = Destinatario (padded 32 bytes)
        $recipientTopic = strtolower((string)$topics[2]);
        $recipientAddress = substr($recipientTopic, 26);

        if ($recipientAddress !== $targetWallet) {
            continue;
        }

        // Dados = Quantidade em hexadecimal
        $dataHex = (string)($log['data'] ?? '0x0');
        $rawAmount = (int) hexdec(substr($dataHex, 2));

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

    // Pagamento confirmado e trava retida com sucesso!
    return [
        'payer' => strtolower((string)($receipt['from'] ?? '')),
        'valid' => true,
        'sandbox' => false,
        'source' => 'tx_hash',
        'raw_amount' => $matchedRawAmount ?? $requiredAmount,
        'reason' => 'Pagamento x402 validado com sucesso na Base Mainnet.',
        'code' => 200,
    ];
}

/**
 * Monta a lista `accepts` padrão do fluxo x402 exact (usada no desafio 402 e na rejeição).
 */
function grid402_build_accepts(int $priceRawUnits): array {
    return [[
        'scheme' => 'exact',
        'network' => GRID402_X402_NETWORK_V2,
        'asset' => GRID402_X402_USDC_CONTRACT,
        'amount' => (string) $priceRawUnits,
        'payTo' => GRID402_WALLET_ADDRESS,
        'maxTimeoutSeconds' => 300,
        'extra' => [
            'name' => GRID402_X402_TOKEN_NAME,
            'version' => GRID402_X402_TOKEN_VERSION,
        ],
    ]];
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

    $msg = $customDescription ?? ($serviceName
        ? "Access to Grid402 '{$serviceName}' API requires a {$priceDisplay} USDC micropayment via x402 protocol."
        : 'Technical query requires on-demand micropayment via x402 protocol.');

    return [
        'url' => $fullUrl,
        'description' => $msg,
        'mimeType' => 'application/json',
        'serviceName' => 'Grid402 ' . strtoupper((string) ($serviceName ?? 'hub')),
        'tags' => ['public-data', 'utility', 'x402', 'base', 'agents'],
        'iconUrl' => $canonicalBase . '/assets/icon-192x192.png',
    ];
}

function grid402_bazaar_default_query_params(?string $serviceName): array {
    return match (strtolower(trim((string) $serviceName))) {
        'dns', 'domain-audit' => ['domain' => 'base.org'],
        'ssl' => ['host' => 'base.org'],
        'asn' => ['ip' => '1.1.1.1'],
        'cep' => ['code' => '01310100'],
        'cnpj', 'sanctions', 'simples', 'compliance', 'kyb-audit' => ['cnpj' => '00000000000191'],
        'holidays' => ['year' => '2026'],
        'workdays' => ['start_date' => '2026-12-23', 'end_date' => '2026-12-28'],
        'lei' => ['lei' => '5493006MHB84DD0ZWV18'],
        'ofac' => ['target' => '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'],
        'notarize' => ['content' => 'Grid402-M2M-Contract-v1'],
        'global-markets' => ['market' => 'US', 'base' => 'USD'],
        default => [],
    };
}

/**
 * Constroi a extensao `extensions.bazaar` conforme @x402/extensions/bazaar (v2 createQueryDiscoveryExtension)
 * com `info` + `schema` JSON Schema 2020-12 estrito (`additionalProperties: false` em `input`).
 */
function grid402_build_bazaar_extension(?array $exampleOutput = null, ?string $serviceName = null): array {
    $example = ($exampleOutput !== null && !empty($exampleOutput)) ? $exampleOutput : ['status' => 'success'];
    $queryDefaults = grid402_bazaar_default_query_params($serviceName);

    $inputInfo = [
        'type' => 'http',
        'method' => 'GET',
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
        'method' => ['type' => 'string', 'enum' => ['GET', 'HEAD', 'DELETE']],
    ];
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
    if (function_exists('grid402_emit_request_id_header')) {
        grid402_emit_request_id_header();
    }
    header('X-Authenticate: x402');
    header('X-Accept-Payment: base:usdc:' . GRID402_WALLET_ADDRESS . ':' . $priceDisplay);
    header('Access-Control-Expose-Headers: WWW-Authenticate, X-Authenticate, X-Accept-Payment, X-Payment-Status, X-Payment-Amount, X-Payment-Network, Payment-Required, payment-required, X-Request-Id');

    $msg = $errorMessage ?? ($serviceName ? "Acesso à API '{$serviceName}' requer micropagamento de {$priceDisplay} USDC via protocolo x402." : 'Consulta técnica requer micropagamento sob demanda via protocolo x402.');

    $acceptsList = grid402_build_accepts($priceRawUnits);
    $resourceInfo = grid402_build_resource_info($serviceName, $priceDisplay, null, $msg);
    $bazaarInfo = grid402_build_bazaar_extension($preview, $serviceName);

    $x402V2HeaderData = [
        'x402Version' => 2,
        'error' => 'Payment required',
        'resource' => $resourceInfo,
        'accepts' => $acceptsList,
        'extensions' => [
            'bazaar' => $bazaarInfo,
        ],
    ];

    $b64Header = base64_encode((string) json_encode($x402V2HeaderData, JSON_UNESCAPED_SLASHES));
    header('Payment-Required: ' . $b64Header);
    header('payment-required: ' . $b64Header);

    // Gatilhos de conversão visíveis apenas para clientes que não pagaram (402).
    // Não altera requisitos de pagamento, apenas informa opções.
    $growthCfgForAds = ['starter_promo_enabled' => false, 'starter_promo_credits' => 10, 'starter_promo_requires_first_paid' => true];
    if (function_exists('grid402_growth_read')) {
        $growthDataForAds = grid402_growth_read();
        $growthCfgForAds = $growthDataForAds['config'] ?? $growthCfgForAds;
    }
    $canonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online';
    $conversionInfo = [
        'starter_promo' => [
            'enabled' => (bool)($growthCfgForAds['starter_promo_enabled'] ?? false),
            'credits' => (int)($growthCfgForAds['starter_promo_credits'] ?? 10),
            'terms' => 'Uma vez por carteira; exige primeira consulta paga validada; recompensas passam por aprovação administrativa.',
            'claim_url' => $canonicalBase . '/growth',
        ],
        'session_pass' => [
            'description' => 'Melhor custo por chamada recorrente + latência <15ms',
            'url' => $canonicalBase . '/pass',
        ],
        'catalog' => [
            'description' => 'Compare preços por endpoint e escolha seu pacote',
            'url' => $canonicalBase . '/catalog',
        ],
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
        'recipient_wallet' => GRID402_WALLET_ADDRESS,
        'extensions' => [
            'bazaar' => $bazaarInfo,
        ],
        'sandbox_test' => [
            'header' => 'X-Payment: demo',
            'rate_limit' => GRID402_SANDBOX_RATE_LIMIT . ' req/hora por IP',
            'description' => 'Teste a estrutura completa da resposta sem custo enviando o cabecalho X-Payment: demo.',
        ],
        'action_required' => "1) Assine a autorizacao x402 v2 (cabecalho PAYMENT-SIGNATURE ou X-Payment em base64) para liquidacao automatica via facilitador, OU envie {$priceDisplay} USDC " . (GRID402_X402_NETWORK === 'base-sepolia' ? 'na Base Sepolia (Chain ID 84532)' : 'na Base (Chain ID 8453)') . " para recipient_wallet e refaca a requisicao com \"X-Payment: <tx_hash>\". 2) Para chamadas de alta frequencia sem latencia on-chain, adquira um Session Pass em " . (defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online') . "/pass (pacotes Starter/Pro/Fleet) e utilize \"X-Payment: pass_g402_...\". 3) Para testar gratuitamente agora (cota de 3 req/hora), envie \"X-Payment: demo\".",
        'conversion' => $conversionInfo,
        'docs' => (defined('GRID402_CANONICAL_DOMAIN') ? GRID402_CANONICAL_DOMAIN : 'https://grid402.online') . '/llms.txt',
    ];

    if ($preview !== null and !empty($preview)) {
        $payload['preview'] = $preview;
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
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
    $source = ($token !== '' and $chatId !== '') ? ($envToken !== '' or $envChat !== '' ? 'mixed' : 'admin_file') : 'none';

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
 * Notificacao em tempo real de micropagamentos x402 no Telegram
 * Disparo nao-bloqueante (IPv4 forcado, timeout curto) com registro de diagnostico sem segredos.
 */
function grid402_notify_telegram_purchase(string $service, float $priceUsdc, string $txHash, bool $isTest = false): bool {
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

    $isOnChainHash = (preg_match('/^0x[a-f0-9]{64}$/i', $cleanTx) === 1);
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
        'market-snapshot' => 'Snapshot Consolidado de Mercado (Forex + Crypto + Indicadores)',
    ];

    $displayName = $serviceNames[$cleanSvc] ?? ('Servico ' . strtoupper($service));
    $displayPrice = number_format($priceUsdc, 4, '.', '') . ' USDC';
    $nowUtc = gmdate('Y-m-d H:i:s') . ' UTC';

    $isTestTx = $isTest || ($isOnChainHash and preg_match('/^0x(a{64}|0{64}|f{64}|1{64}|demo)$/i', $cleanTx) === 1);
    $titleHeader = $isTestTx ? "🧪 *TESTE DO SISTEMA: Simulação de Compra no Grid402*" : "💰 *Nova Compra no Grid402!*";

    $netLabel = $isPixOrderRef ? 'Pix / Mercado Pago (BRL)' : 'Base Mainnet (ChainID 8453)';
    $proofLine = $isPixOrderRef
        ? ("🧾 *Comprovante MP:* `" . $cleanTx . "`")
        : ("🔗 *Transacao:* [BaseScan](https://basescan.org/tx/" . $cleanTx . ")");

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
