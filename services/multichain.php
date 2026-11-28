<?php
/**
 * Grid402 - Motor de Verificação Multi-Chain (5 Redes EVM + Solana SPL USDC)
 *
 * Suporta auditoria nativa on-chain de transferências USDC (6 decimais) sem custódia
 * e sem bridge intermediária em:
 * - Base Mainnet (Chain ID 8453)
 * - Arbitrum One (Chain ID 42161)
 * - Polygon PoS (Chain ID 137)
 * - Optimism (Chain ID 10)
 * - Ethereum Mainnet (Chain ID 1)
 * - Solana Mainnet (SPL USDC Mint EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v)
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

if (!defined('GRID402_MAX_TX_AGE_SECONDS')) {
    $grid402MaxTxAgeEnv = function_exists('grid402_env') ? (int) grid402_env('GRID402_MAX_TX_AGE_SECONDS', 259200) : 259200;
    // Invariante anti-replay (V-04): janela maxima de idade do bloco (padrao 72h = 259200s)
    // estritamente menor que o TTL de 7 dias (604800s) de used_txs.json.
    define('GRID402_MAX_TX_AGE_SECONDS', min(518400, max(300, $grid402MaxTxAgeEnv)));
    unset($grid402MaxTxAgeEnv);
}

/**
 * Registro canônico das 6 redes suportadas para pagamentos USDC no Grid402.
 *
 * @return array<string, array<string, mixed>>
 */
function grid402_supported_networks(): array {
    $evmWallet = strtolower(defined('GRID402_WALLET_ADDRESS') ? (string) GRID402_WALLET_ADDRESS : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90');
    $solWallet = defined('GRID402_SOLANA_WALLET_ADDRESS') ? trim((string) GRID402_SOLANA_WALLET_ADDRESS) : 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C';
    $solMint = defined('GRID402_SOLANA_USDC_MINT') ? trim((string) GRID402_SOLANA_USDC_MINT) : 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';

    $envRpc = static function (string $envKey, array $defaults): array {
        $custom = function_exists('grid402_env') ? trim((string) grid402_env($envKey, '')) : '';
        if ($custom !== '' && str_starts_with($custom, 'https://')) {
            return array_values(array_unique(array_merge([$custom], $defaults)));
        }
        return $defaults;
    };

    $baseRpc = defined('GRID402_BASE_RPC_URL') ? trim((string) GRID402_BASE_RPC_URL) : 'https://mainnet.base.org';
    $baseDefaultRpcs = [
        'https://base-rpc.publicnode.com',
        'https://1rpc.io/base',
        'https://base.drpc.org',
        'https://mainnet.base.org',
    ];
    // Se o operador configurou um RPC privado/customizado (diferente do default 429 e de nos mortos), prioriza-o.
    if (
        $baseRpc !== ''
        && str_starts_with($baseRpc, 'https://')
        && !in_array(rtrim(strtolower($baseRpc), '/'), ['https://mainnet.base.org', 'https://base.llamarpc.com'], true)
    ) {
        $baseDefaultRpcs = array_values(array_unique(array_merge([$baseRpc], $baseDefaultRpcs)));
    }
    $baseUsdc = strtolower(defined('GRID402_USDC_CONTRACT') ? (string) GRID402_USDC_CONTRACT : '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913');

    return [
        'base' => [
            'slug' => 'base',
            'name' => 'Base',
            'display_name' => 'Base Mainnet',
            'type' => 'evm',
            'chain_id' => 8453,
            'chain_id_hex' => '0x2105',
            'caip2' => 'eip155:8453',
            'usdc_contract' => $baseUsdc,
            'usdc_contracts' => [$baseUsdc],
            'decimals' => 6,
            'recipient' => $evmWallet,
            'rpcs' => $envRpc('GRID402_RPC_BASE', $baseDefaultRpcs),
            'explorer_tx_url' => 'https://basescan.org/tx/',
            'native_currency' => ['name' => 'Ether', 'symbol' => 'ETH', 'decimals' => 18],
        ],
        'arbitrum' => [
            'slug' => 'arbitrum',
            'name' => 'Arbitrum',
            'display_name' => 'Arbitrum One',
            'type' => 'evm',
            'chain_id' => 42161,
            'chain_id_hex' => '0xa4b1',
            'caip2' => 'eip155:42161',
            'usdc_contract' => '0xaf88d065e77c8cc2239327c5edb3a432268e5831',
            'usdc_contracts' => [
                '0xaf88d065e77c8cc2239327c5edb3a432268e5831', // Circle Native USDC
                '0xff970a61a04b1ca14834a43f5de4533ebddb5cc8', // Circle Bridged USDC.e
            ],
            'decimals' => 6,
            'recipient' => $evmWallet,
            'rpcs' => $envRpc('GRID402_RPC_ARBITRUM', ['https://arbitrum-one-rpc.publicnode.com', 'https://arb1.arbitrum.io/rpc', 'https://1rpc.io/arb', 'https://arbitrum.drpc.org']),
            'explorer_tx_url' => 'https://arbiscan.io/tx/',
            'native_currency' => ['name' => 'Ether', 'symbol' => 'ETH', 'decimals' => 18],
        ],
        'polygon' => [
            'slug' => 'polygon',
            'name' => 'Polygon',
            'display_name' => 'Polygon PoS',
            'type' => 'evm',
            'chain_id' => 137,
            'chain_id_hex' => '0x89',
            'caip2' => 'eip155:137',
            'usdc_contract' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
            'usdc_contracts' => [
                '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', // Circle Native USDC
                '0x2791bca1f2de4661ed88a30c99a7a9449aa84174', // Circle Bridged USDC.e
            ],
            'decimals' => 6,
            'recipient' => $evmWallet,
            'rpcs' => $envRpc('GRID402_RPC_POLYGON', ['https://polygon-bor-rpc.publicnode.com', 'https://1rpc.io/matic', 'https://polygon.drpc.org']),
            'explorer_tx_url' => 'https://polygonscan.com/tx/',
            'native_currency' => ['name' => 'POL', 'symbol' => 'POL', 'decimals' => 18],
        ],
        'optimism' => [
            'slug' => 'optimism',
            'name' => 'Optimism',
            'display_name' => 'OP Mainnet',
            'type' => 'evm',
            'chain_id' => 10,
            'chain_id_hex' => '0xa',
            'caip2' => 'eip155:10',
            'usdc_contract' => '0x0b2c639c533813f4aa9d7837caf62653d097ff85',
            'usdc_contracts' => [
                '0x0b2c639c533813f4aa9d7837caf62653d097ff85', // Circle Native USDC
                '0x7f5c764cbc14f9669b88837ca1490cca17c31607', // Circle Bridged USDC.e
            ],
            'decimals' => 6,
            'recipient' => $evmWallet,
            'rpcs' => $envRpc('GRID402_RPC_OPTIMISM', ['https://optimism-rpc.publicnode.com', 'https://mainnet.optimism.io', 'https://1rpc.io/op']),
            'explorer_tx_url' => 'https://optimistic.etherscan.io/tx/',
            'native_currency' => ['name' => 'Ether', 'symbol' => 'ETH', 'decimals' => 18],
        ],
        'ethereum' => [
            'slug' => 'ethereum',
            'name' => 'Ethereum',
            'display_name' => 'Ethereum Mainnet',
            'type' => 'evm',
            'chain_id' => 1,
            'chain_id_hex' => '0x1',
            'caip2' => 'eip155:1',
            'usdc_contract' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'usdc_contracts' => ['0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48'],
            'decimals' => 6,
            'recipient' => $evmWallet,
            'rpcs' => $envRpc('GRID402_RPC_ETHEREUM', ['https://ethereum-rpc.publicnode.com', 'https://1rpc.io/eth', 'https://eth.drpc.org']),
            'explorer_tx_url' => 'https://etherscan.io/tx/',
            'native_currency' => ['name' => 'Ether', 'symbol' => 'ETH', 'decimals' => 18],
        ],
        'solana' => [
            'slug' => 'solana',
            'name' => 'Solana',
            'display_name' => 'Solana Mainnet',
            'type' => 'solana',
            'chain_id' => null,
            'chain_id_hex' => null,
            'caip2' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
            'usdc_contract' => $solMint,
            'usdc_contracts' => [$solMint],
            'decimals' => 6,
            'recipient' => $solWallet,
            'rpcs' => $envRpc('GRID402_RPC_SOLANA', ['https://solana-rpc.publicnode.com', 'https://api.mainnet-beta.solana.com']),
            'explorer_tx_url' => 'https://solscan.io/tx/',
            'native_currency' => ['name' => 'Solana', 'symbol' => 'SOL', 'decimals' => 9],
        ],
    ];
}

/**
 * Normaliza apelidos, Chain IDs e CAIP-2 para o slug canônico da rede suportada.
 * Retorna null se a rede não for suportada.
 */
function grid402_normalize_network_slug(?string $raw): ?string {
    $val = strtolower(trim((string) $raw));
    if ($val === '') {
        return null;
    }

    return match ($val) {
        'base', 'base-mainnet', 'eip155:8453', '8453' => 'base',
        'arbitrum', 'arb', 'arbitrum-one', 'arb1', 'eip155:42161', '42161' => 'arbitrum',
        'polygon', 'matic', 'pol', 'polygon-pos', 'eip155:137', '137' => 'polygon',
        'optimism', 'op', 'op-mainnet', 'eip155:10', '10' => 'optimism',
        'ethereum', 'eth', 'mainnet', 'eth-mainnet', 'eip155:1', '1' => 'ethereum',
        'solana', 'sol', 'solana-mainnet', 'solana:5eykt4usfv8p8njdtrepy1vzqkqzkvdp' => 'solana',
        default => null,
    };
}

/**
 * Valida formato Base58 de uma assinatura de transação Solana (64 a 90 caracteres).
 */
function grid402_is_valid_solana_signature(string $sig): bool {
    return preg_match('/^[1-9A-HJ-NP-Za-km-z]{64,90}$/', trim($sig)) === 1;
}

/**
 * Faz o parsing do comprovante de pagamento e resolve rede canônica, tx_hash e chave de trava anti-replay.
 *
 * Aceita:
 * - Prefixo explícito: "arbitrum:0x...", "solana:<base58>", "polygon:0x..."
 * - Cabeçalho X-Payment-Network ou parâmetro chain/network
 * - Detecção automática de assinatura Solana (Base58) quando não começa com 0x
 * - Fallback retrocompatível para 'base'
 */
function grid402_parse_network_proof(string $rawProof, ?string $headerNetwork = null): array {
    $proof = trim($rawProof);
    $explicitNetwork = false;
    $resolvedNetwork = null;
    $invalidNetwork = null;

    // 1. Prefixo explícito CAIP-2 ("eip155:137:0x...", "solana:5eykt...:sig") ou "chain:tx" (ex: arbitrum:0x..., solana:5x...)
    if (preg_match('/^(eip155:[0-9]{1,10}):(.+)$/i', $proof, $cm) === 1) {
        $caipPrefix = strtolower(trim($cm[1]));
        $rest = trim($cm[2]);
        $norm = grid402_normalize_network_slug($caipPrefix);
        if ($norm !== null) {
            $resolvedNetwork = $norm;
            $proof = $rest;
            $explicitNetwork = true;
        } else {
            $invalidNetwork = $caipPrefix;
            $proof = $rest;
            $explicitNetwork = true;
        }
    } elseif (preg_match('/^(solana:[1-9A-HJ-NP-Za-km-z]{16,44}):(.+)$/i', $proof, $sm) === 1) {
        $caipPrefix = strtolower(trim($sm[1]));
        $rest = trim($sm[2]);
        $norm = grid402_normalize_network_slug($caipPrefix);
        if ($norm !== null) {
            $resolvedNetwork = $norm;
            $proof = $rest;
            $explicitNetwork = true;
        } else {
            $invalidNetwork = $caipPrefix;
            $proof = $rest;
            $explicitNetwork = true;
        }
    } elseif (preg_match('/^([a-zA-Z0-9_-]{2,20}):(.+)$/', $proof, $m) === 1) {
        $prefix = strtolower(trim($m[1]));
        $rest = trim($m[2]);
        // Evita confundir tokens ou esquemas conhecidos
        if (!in_array($prefix, ['http', 'https', 'pass_g402', 'bearer', 'x402'], true)) {
            $norm = grid402_normalize_network_slug($prefix);
            if ($norm !== null) {
                $resolvedNetwork = $norm;
                $proof = $rest;
                $explicitNetwork = true;
            } else {
                $invalidNetwork = $prefix;
                $proof = $rest;
                $explicitNetwork = true;
            }
        }
    }

    // 2. Cabeçalho X-Payment-Network ou parâmetro chain explícito
    if ($resolvedNetwork === null && $invalidNetwork === null && $headerNetwork !== null && trim($headerNetwork) !== '') {
        $norm = grid402_normalize_network_slug($headerNetwork);
        if ($norm !== null) {
            $resolvedNetwork = $norm;
            $explicitNetwork = true;
        } else {
            $invalidNetwork = strtolower(trim($headerNetwork));
            $explicitNetwork = true;
        }
    }

    // 3. Auto-detecção Solana Base58 quando nenhum cabeçalho foi enviado
    if ($resolvedNetwork === null && $invalidNetwork === null) {
        if (!str_starts_with(strtolower($proof), '0x') && !str_starts_with(strtolower($proof), 'pass_g402_') && strtolower($proof) !== 'demo' && grid402_is_valid_solana_signature($proof)) {
            $resolvedNetwork = 'solana';
        } else {
            $resolvedNetwork = 'base';
        }
    }

    $finalNet = $resolvedNetwork ?? 'base';
    $normalizedTx = ($finalNet === 'solana') ? $proof : strtolower($proof);
    // Retrocompatibilidade: na Base a chave anti-replay continua sendo apenas o hash 0x lowercase
    $lockKey = ($finalNet === 'base') ? $normalizedTx : ($finalNet . ':' . $normalizedTx);

    return [
        'network' => $finalNet,
        'tx_hash' => $normalizedTx,
        'lock_key' => $lockKey,
        'explicit_network' => $explicitNetwork,
        'invalid_network' => $invalidNetwork,
    ];
}

/**
 * Retorna a URL completa da transação no block explorer oficial da rede.
 */
function grid402_explorer_tx_url(string $network, string $txHash): string {
    $networks = grid402_supported_networks();
    $slug = grid402_normalize_network_slug($network) ?? 'base';
    $base = (string) ($networks[$slug]['explorer_tx_url'] ?? 'https://basescan.org/tx/');
    return $base . $txHash;
}

/**
 * Indica se a rede Solana esta habilitada para exposicao publica em UI/API/manifestos.
 * Mantem o suporte interno preservado atras da flag GRID402_SOLANA_X402_ENABLED (padrao false).
 */
function grid402_is_solana_public_enabled(): bool {
    if (function_exists('grid402_is_solana_x402_enabled')) {
        return grid402_is_solana_x402_enabled();
    }
    if (defined('GRID402_SOLANA_X402_ENABLED')) {
        $v = GRID402_SOLANA_X402_ENABLED;
        return is_bool($v) ? $v : in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }
    $envVal = function_exists('grid402_env') ? (string) grid402_env('GRID402_SOLANA_X402_ENABLED', '') : '';
    if (trim($envVal) === '' && function_exists('grid402_env')) {
        $envVal = (string) grid402_env('GRID402_SOLANA_ATA_READY', '');
    }
    return in_array(strtolower(trim($envVal)), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Retorna apenas as redes atualmente habilitadas para oferta publica (omite Solana quando a flag estiver desligada).
 *
 * @return array<string, array<string, mixed>>
 */
function grid402_public_networks(): array {
    $nets = grid402_supported_networks();
    if (!grid402_is_solana_public_enabled()) {
        unset($nets['solana']);
    }
    return $nets;
}

/**
 * Resumo público compacto das redes ativas suportadas (exposto no desafio HTTP 402 e em /api/v1/pass).
 */
function grid402_supported_networks_public_summary(): array {
    $out = [];
    foreach (grid402_public_networks() as $slug => $net) {
        $out[$slug] = [
            'name' => $net['display_name'],
            'type' => $net['type'],
            'chain_id' => $net['chain_id'],
            'caip2' => $net['caip2'],
            'token' => 'USDC',
            'usdc_contract' => $net['usdc_contract'],
            'recipient_wallet' => $net['recipient'],
            'explorer' => $net['explorer_tx_url'],
        ];
    }
    return $out;
}

/**
 * Consulta eth_getTransactionReceipt em qualquer rede EVM suportada com failover automático de RPC.
 */
function grid402_rpc_get_evm_receipt(string $network, string $txHash): ?array {
    $networks = grid402_supported_networks();
    $slug = grid402_normalize_network_slug($network);
    if ($slug === null || !isset($networks[$slug]) || ($networks[$slug]['type'] ?? '') !== 'evm') {
        return null;
    }

    $rpcs = $networks[$slug]['rpcs'] ?? [];
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_getTransactionReceipt',
        'params' => [$txHash],
    ]);

    foreach ($rpcs as $rpcUrl) {
        $ch = curl_init((string) $rpcUrl);
        if (!$ch) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Grid402-MultiChain-Verifier/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response) && $response !== '') {
            $decoded = @json_decode($response, true);
            if (is_array($decoded) && array_key_exists('result', $decoded) && $decoded['result'] !== null && $decoded['result'] !== false && is_array($decoded['result']) && !empty($decoded['result']) && (isset($decoded['result']['transactionHash']) || isset($decoded['result']['blockNumber']))) {
                return $decoded['result'];
            }
        }
    }

    return null;
}

/**
 * Consulta o timestamp Unix (segundos) de um bloco EVM via eth_getBlockByNumber com failover RPC.
 * Pode ser sobrescrito em testes offline via $GLOBALS['grid402_evm_block_timestamp_resolver'].
 */
function grid402_rpc_get_evm_block_timestamp(string $network, string $blockNumberHex): ?int {
    $networks = grid402_supported_networks();
    $slug = grid402_normalize_network_slug($network);
    if ($slug === null || !isset($networks[$slug]) || ($networks[$slug]['type'] ?? '') !== 'evm') {
        return null;
    }

    $cleanBlock = strtolower(trim($blockNumberHex));
    if (preg_match('/^0x[0-9a-f]+$/', $cleanBlock) !== 1) {
        return null;
    }

    $override = $GLOBALS['grid402_evm_block_timestamp_resolver'] ?? null;
    if (is_callable($override)) {
        $resolved = $override($slug, $cleanBlock);
        return (is_int($resolved) && $resolved > 0) ? $resolved : null;
    }

    $rpcs = $networks[$slug]['rpcs'] ?? [];
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_getBlockByNumber',
        'params' => [$cleanBlock, false],
    ]);

    foreach ($rpcs as $rpcUrl) {
        $ch = curl_init((string) $rpcUrl);
        if (!$ch) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Grid402-MultiChain-Verifier/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response) && $response !== '') {
            $decoded = @json_decode($response, true);
            $tsRaw = is_array($decoded['result'] ?? null) ? ($decoded['result']['timestamp'] ?? null) : null;
            if (is_string($tsRaw) && preg_match('/^0x[0-9a-fA-F]+$/', $tsRaw) === 1) {
                $ts = (int) hexdec(substr($tsRaw, 2));
                if ($ts > 0) {
                    return $ts;
                }
            } elseif (is_int($tsRaw) && $tsRaw > 0) {
                return $tsRaw;
            }
        }
    }

    return null;
}

/**
 * Valida a idade máxima do bloco da transação (V-04) contra GRID402_MAX_TX_AGE_SECONDS (padrão 72h).
 * Rejeita transações sem timestamp válido ou mais antigas que a janela segura (estritamente < TTL de 7 dias de used_txs.json).
 */
function grid402_validate_tx_block_timestamp(?int $blockTimestamp, string $networkDisplayName, ?int $now = null): array {
    $now = $now ?? time();
    $maxAge = defined('GRID402_MAX_TX_AGE_SECONDS') ? (int) GRID402_MAX_TX_AGE_SECONDS : 259200;

    if ($blockTimestamp === null || $blockTimestamp <= 0) {
        return [
            'valid' => false,
            'error' => 'TransactionExpired',
            'category' => 'tx_expired',
            'message' => "Não foi possível validar o timestamp do bloco na rede {$networkDisplayName}. Por segurança anti-replay, apenas transações recentes com timestamp confirmado são aceitas.",
            'code' => 403,
        ];
    }

    if (($now - $blockTimestamp) > $maxAge) {
        $maxHours = max(1, (int) round($maxAge / 3600));
        return [
            'valid' => false,
            'error' => 'TransactionExpired',
            'category' => 'tx_expired',
            'message' => "Transação expirada na rede {$networkDisplayName}: apenas transações confirmadas nas últimas {$maxHours} horas são aceitas (proteção anti-replay).",
            'code' => 403,
        ];
    }

    // Rejeita timestamps forjados muito no futuro (> 1h)
    if (($blockTimestamp - $now) > 3600) {
        return [
            'valid' => false,
            'error' => 'TransactionExpired',
            'category' => 'tx_expired',
            'message' => "Timestamp de bloco inválido na rede {$networkDisplayName} (muito à frente do relógio UTC).",
            'code' => 403,
        ];
    }

    return [
        'valid' => true,
        'block_timestamp' => $blockTimestamp,
    ];
}

/**
 * Extrai um timestamp inteiro (segundos Unix) de um campo opcional do recibo EVM (blockTimestamp/_block_timestamp).
 */
function grid402_extract_receipt_timestamp(array $receipt): ?int {
    foreach (['blockTimestamp', '_block_timestamp'] as $key) {
        if (!isset($receipt[$key])) {
            continue;
        }
        $val = $receipt[$key];
        if (is_int($val) && $val > 0) {
            return $val;
        }
        if (is_string($val)) {
            $trimmed = trim($val);
            if (preg_match('/^0x[0-9a-fA-F]+$/', $trimmed) === 1) {
                $parsed = (int) hexdec(substr($trimmed, 2));
                if ($parsed > 0) {
                    return $parsed;
                }
            } elseif (preg_match('/^\d{9,12}$/', $trimmed) === 1) {
                $parsed = (int) $trimmed;
                if ($parsed > 0) {
                    return $parsed;
                }
            }
        }
    }
    return null;
}

/**
 * Valida um recibo EVM (logs ERC-20 Transfer + idade máxima do bloco) contra a allowlist de contratos USDC da rede.
 */
function grid402_verify_evm_receipt_usdc(array $receipt, string $network, int $requiredRawUnits, ?string $expectedRecipient = null, ?int $now = null): array {
    $networks = grid402_supported_networks();
    $slug = grid402_normalize_network_slug($network);
    if ($slug === null || !isset($networks[$slug]) || ($networks[$slug]['type'] ?? '') !== 'evm') {
        return [
            'valid' => false,
            'error' => 'UnsupportedNetwork',
            'message' => "Rede EVM não suportada: {$network}.",
            'code' => 400,
        ];
    }

    if (strtolower((string) ($receipt['status'] ?? '0x0')) !== '0x1') {
        return [
            'valid' => false,
            'error' => 'TransactionFailedOnChain',
            'message' => "A transação foi revertida ou falhou na rede {$networks[$slug]['display_name']}.",
            'code' => 403,
        ];
    }

    $allowedContracts = array_map('strtolower', (array) ($networks[$slug]['usdc_contracts'] ?? []));
    $recipient = strtolower($expectedRecipient ?? (string) $networks[$slug]['recipient']);
    $expectedRecipientHex = str_starts_with($recipient, '0x') ? substr($recipient, 2) : $recipient;
    $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    $totalUsdcRaw = 0;
    $matchedContract = null;
    $logs = is_array($receipt['logs'] ?? null) ? $receipt['logs'] : [];

    foreach ($logs as $log) {
        if (!is_array($log)) {
            continue;
        }
        $logAddr = strtolower((string) ($log['address'] ?? ''));
        if (!in_array($logAddr, $allowedContracts, true)) {
            continue;
        }
        $topics = is_array($log['topics'] ?? null) ? $log['topics'] : [];
        if (count($topics) < 3) {
            continue;
        }
        if (strtolower((string) $topics[0]) !== $transferTopic) {
            continue;
        }
        $toTopic = strtolower((string) $topics[2]);
        $toAddrHex = substr(str_starts_with($toTopic, '0x') ? substr($toTopic, 2) : $toTopic, -40);
        if ($toAddrHex !== $expectedRecipientHex) {
            continue;
        }

        $dataHex = strtolower((string) ($log['data'] ?? '0x0'));
        $cleanHex = str_starts_with($dataHex, '0x') ? substr($dataHex, 2) : $dataHex;
        $cleanHex = ltrim($cleanHex, '0');
        $val = ($cleanHex === '') ? 0 : (int) hexdec($cleanHex);
        if ($val > 0) {
            $totalUsdcRaw += $val;
            $matchedContract = $logAddr;
        }
    }

    if ($totalUsdcRaw < $requiredRawUnits) {
        $reqFormatted = number_format($requiredRawUnits / 1000000, 4, '.', '');
        $gotFormatted = number_format($totalUsdcRaw / 1000000, 4, '.', '');
        return [
            'valid' => false,
            'error' => 'InsufficientOrMissingUsdcTransfer',
            'message' => "Transferência de USDC oficial insuficiente na rede {$networks[$slug]['display_name']}. Exigido: {$reqFormatted} USDC, encontrado: {$gotFormatted} USDC.",
            'code' => 403,
            'network' => $slug,
            'raw_amount' => $totalUsdcRaw,
        ];
    }

    // Validação obrigatória de idade do bloco (V-04): fecha replay ilimitado após expurgo de 7 dias em used_txs.json
    $blockTimestamp = grid402_extract_receipt_timestamp($receipt);
    if ($blockTimestamp === null && isset($receipt['blockNumber']) && is_string($receipt['blockNumber'])) {
        $blockTimestamp = grid402_rpc_get_evm_block_timestamp($slug, $receipt['blockNumber']);
    }
    $ageCheck = grid402_validate_tx_block_timestamp($blockTimestamp, (string) $networks[$slug]['display_name'], $now);
    if (empty($ageCheck['valid'])) {
        $ageCheck['network'] = $slug;
        return $ageCheck;
    }

    return [
        'valid' => true,
        'network' => $slug,
        'chain_id' => $networks[$slug]['chain_id'],
        'contract' => $matchedContract,
        'payer' => strtolower((string) ($receipt['from'] ?? '')),
        'raw_amount' => $totalUsdcRaw,
        'usdc_amount' => round($totalUsdcRaw / 1000000, 6),
        'block_number' => isset($receipt['blockNumber']) ? hexdec((string) $receipt['blockNumber']) : null,
        'block_timestamp' => $blockTimestamp,
        'source' => ($slug === 'base') ? 'tx_hash' : ('evm_' . $slug),
    ];
}

/**
 * Extrai o objeto de transação do retorno JSON-RPC `getTransaction` da Solana.
 */
function grid402_extract_solana_tx_result(?string $response, int $httpCode = 200): ?array {
    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return null;
    }
    $decoded = @json_decode($response, true);
    if (
        is_array($decoded)
        && array_key_exists('result', $decoded)
        && is_array($decoded['result'])
        && !empty($decoded['result'])
        && is_array($decoded['result']['meta'] ?? null)
        && (isset($decoded['result']['slot']) || isset($decoded['result']['transaction']))
    ) {
        return $decoded['result'];
    }
    return null;
}

/**
 * Consulta getTransaction (jsonParsed) na Solana Mainnet com failover automático de RPC.
 */
function grid402_rpc_get_solana_transaction(string $signature): ?array {
    if (!grid402_is_valid_solana_signature($signature)) {
        return null;
    }

    $networks = grid402_supported_networks();
    $rpcs = $networks['solana']['rpcs'] ?? [];
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'getTransaction',
        'params' => [
            $signature,
            [
                'encoding' => 'jsonParsed',
                'commitment' => 'confirmed',
                'maxSupportedTransactionVersion' => 0,
            ],
        ],
    ]);

    foreach ($rpcs as $rpcUrl) {
        $ch = curl_init((string) $rpcUrl);
        if (!$ch) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Grid402-Solana-Verifier/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $txResult = grid402_extract_solana_tx_result(is_string($response) ? $response : null, $httpCode);
        if ($txResult !== null) {
            return $txResult;
        }
    }

    return null;
}

/**
 * Valida o retorno JSON-RPC getTransaction da Solana computando o delta líquido
 * de SPL USDC creditado na conta pertencente à carteira oficial do Grid402 + idade máxima do bloco (V-04).
 */
function grid402_verify_solana_tx_usdc(array $txResult, int $requiredRawUnits, ?string $expectedRecipient = null, ?string $expectedMint = null, ?int $now = null): array {
    $networks = grid402_supported_networks();
    $solCfg = $networks['solana'];
    $recipient = $expectedRecipient ?? (string) $solCfg['recipient'];
    $mint = $expectedMint ?? (string) $solCfg['usdc_contract'];

    $meta = is_array($txResult['meta'] ?? null) ? $txResult['meta'] : null;
    if ($meta === null) {
        return [
            'valid' => false,
            'error' => 'InvalidSolanaTransaction',
            'message' => 'Metadados da transação Solana ausentes.',
            'code' => 403,
        ];
    }

    if (array_key_exists('err', $meta) && $meta['err'] !== null) {
        return [
            'valid' => false,
            'error' => 'TransactionFailedOnChain',
            'message' => 'A transação falhou na blockchain Solana (meta.err != null).',
            'code' => 403,
        ];
    }

    $preMap = [];
    $allPreByIdx = [];
    foreach ((array) ($meta['preTokenBalances'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['mint'] ?? '') !== $mint) {
            continue;
        }
        $idx = (int) ($entry['accountIndex'] ?? -1);
        $rawAmt = (int) ($entry['uiTokenAmount']['amount'] ?? 0);
        $owner = (string) ($entry['owner'] ?? '');
        if ($idx >= 0) {
            $allPreByIdx[$idx] = ['owner' => $owner, 'amount' => $rawAmt];
        }
        if ($owner !== $recipient) {
            continue;
        }
        if ($idx >= 0) {
            $preMap[$idx] = $rawAmt;
        }
    }

    $netCreditedRaw = 0;
    $allPostByIdx = [];
    foreach ((array) ($meta['postTokenBalances'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['mint'] ?? '') !== $mint) {
            continue;
        }
        $idx = (int) ($entry['accountIndex'] ?? -1);
        $postAmt = (int) ($entry['uiTokenAmount']['amount'] ?? 0);
        $owner = (string) ($entry['owner'] ?? '');
        if ($idx >= 0) {
            $allPostByIdx[$idx] = ['owner' => $owner, 'amount' => $postAmt];
        }
        if ($owner !== $recipient) {
            continue;
        }
        $preAmt = $preMap[$idx] ?? 0;
        if ($postAmt > $preAmt) {
            $netCreditedRaw += ($postAmt - $preAmt);
        }
    }

    if ($netCreditedRaw < $requiredRawUnits) {
        $reqFormatted = number_format($requiredRawUnits / 1000000, 4, '.', '');
        $gotFormatted = number_format($netCreditedRaw / 1000000, 4, '.', '');
        return [
            'valid' => false,
            'error' => 'InsufficientOrMissingUsdcTransfer',
            'message' => "Transferência de SPL USDC insuficiente na Solana para {$recipient}. Exigido: {$reqFormatted} USDC, creditado: {$gotFormatted} USDC.",
            'code' => 403,
            'network' => 'solana',
            'raw_amount' => $netCreditedRaw,
        ];
    }

    // Validação obrigatória de idade do bloco (V-04): bloqueia replay ilimitado após expurgo de 7 dias em used_txs.json
    $blockTime = (isset($txResult['blockTime']) && is_numeric($txResult['blockTime'])) ? (int) $txResult['blockTime'] : null;
    $ageCheck = grid402_validate_tx_block_timestamp($blockTime, 'Solana Mainnet', $now);
    if (empty($ageCheck['valid'])) {
        $ageCheck['network'] = 'solana';
        return $ageCheck;
    }

    // Identifica pagador (sender real de SPL USDC com saldo reduzido, fallback para feePayer/accountKeys[0])
    $solanaPayer = '';
    foreach ($allPreByIdx as $idx => $preInfo) {
        $preOwner = (string) ($preInfo['owner'] ?? '');
        if ($preOwner === '' || $preOwner === $recipient) {
            continue;
        }
        $postAmt = (int) ($allPostByIdx[$idx]['amount'] ?? 0);
        if ($postAmt < (int) $preInfo['amount']) {
            $solanaPayer = $preOwner;
            break;
        }
    }
    if ($solanaPayer === '') {
        $acctKeys = $txResult['transaction']['message']['accountKeys'] ?? [];
        if (is_array($acctKeys) && isset($acctKeys[0])) {
            if (is_string($acctKeys[0])) {
                $solanaPayer = trim($acctKeys[0]);
            } elseif (is_array($acctKeys[0]) && !empty($acctKeys[0]['pubkey']) && is_string($acctKeys[0]['pubkey'])) {
                $solanaPayer = trim($acctKeys[0]['pubkey']);
            }
        }
    }

    return [
        'valid' => true,
        'network' => 'solana',
        'chain_id' => null,
        'contract' => $mint,
        'payer' => $solanaPayer,
        'raw_amount' => $netCreditedRaw,
        'usdc_amount' => round($netCreditedRaw / 1000000, 6),
        'slot' => isset($txResult['slot']) ? (int) $txResult['slot'] : null,
        'block_timestamp' => $blockTime,
        'source' => 'solana_spl',
    ];
}

/**
 * Orquestrador unificado de validação on-chain multi-chain com trava atômica anti-replay Zero-DB.
 */
function grid402_verify_multichain_usdc_tx(string $network, string $txHash, int $requiredRawUnits): array {
    $slug = grid402_normalize_network_slug($network);
    if ($slug === null) {
        return [
            'valid' => false,
            'error' => 'UnsupportedNetwork',
            'message' => "Rede não suportada: {$network}. Redes aceitas: base, arbitrum, polygon, optimism, ethereum, solana.",
            'code' => 400,
        ];
    }

    $networks = grid402_supported_networks();
    $netCfg = $networks[$slug];
    $cleanTx = ($slug === 'solana') ? trim($txHash) : strtolower(trim($txHash));

    if ($slug === 'solana') {
        if (!grid402_is_valid_solana_signature($cleanTx)) {
            return [
                'valid' => false,
                'error' => 'InvalidTransactionFormat',
                'message' => 'Assinatura de transação Solana inválida. Deve ser uma string Base58 de 64 a 90 caracteres.',
                'code' => 400,
            ];
        }
    } else {
        if (preg_match('/^0x[a-f0-9]{64}$/', $cleanTx) !== 1) {
            return [
                'valid' => false,
                'error' => 'InvalidTransactionFormat',
                'message' => "Formato de hash de transação EVM inválido para {$netCfg['display_name']}. Esperado 0x seguido de 64 caracteres hexadecimais.",
                'code' => 400,
            ];
        }
    }

    $lockKey = ($slug === 'base') ? $cleanTx : ($slug . ':' . $cleanTx);

    if (function_exists('grid402_claim_transaction_lock') && !grid402_claim_transaction_lock($lockKey)) {
        return [
            'valid' => false,
            'error' => 'TransactionAlreadyUsed',
            'message' => "Esta transação na rede {$netCfg['display_name']} já foi utilizada anteriormente (proteção anti-replay).",
            'code' => 403,
        ];
    }

    if ($slug === 'solana') {
        $txData = grid402_rpc_get_solana_transaction($cleanTx);
        if ($txData === null) {
            if (function_exists('grid402_release_transaction_lock')) {
                grid402_release_transaction_lock($lockKey);
            }
            return [
                'valid' => false,
                'error' => 'TransactionNotFound',
                'message' => 'Transação não encontrada ou ainda não confirmada na Solana Mainnet. Aguarde alguns segundos e tente novamente.',
                'code' => 402,
            ];
        }
        $check = grid402_verify_solana_tx_usdc($txData, $requiredRawUnits);
    } else {
        $receipt = grid402_rpc_get_evm_receipt($slug, $cleanTx);
        if ($receipt === null) {
            if (function_exists('grid402_release_transaction_lock')) {
                grid402_release_transaction_lock($lockKey);
            }
            return [
                'valid' => false,
                'error' => 'TransactionNotFound',
                'message' => "Transação não encontrada ou ainda não minerada na rede {$netCfg['display_name']}.",
                'code' => 402,
            ];
        }
        $check = grid402_verify_evm_receipt_usdc($receipt, $slug, $requiredRawUnits);
    }

    if (empty($check['valid'])) {
        if (function_exists('grid402_release_transaction_lock')) {
            grid402_release_transaction_lock($lockKey);
        }
        return $check;
    }

    $check['tx_hash'] = $cleanTx;
    $check['lock_key'] = $lockKey;
    $check['explorer_url'] = grid402_explorer_tx_url($slug, $cleanTx);
    return $check;
}
