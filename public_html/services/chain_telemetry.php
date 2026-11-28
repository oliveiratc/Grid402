<?php
/**
 * Grid402 - Micro-Servico de Telemetria Multi-Chain (/api/v1/chain-telemetry)
 *
 * Fontes 100% publicas sem API key paga:
 * - RPCs publicos multi-fallback (Base, Ethereum, Polygon, Arbitrum, Solana)
 * - DeFiLlama Public API (api.llama.fi/v2/chains, cache compartilhado 300s)
 *
 * Cache Zero-DB por rede de 60s em GRID402_PRIVATE_DIR.
 * Degradacao graciosa se apenas RPC ou DeFiLlama responder; HTTP 503 se ambos falharem.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Configuracao das 5 redes suportadas em /api/v1/chain-telemetry.
 *
 * @return array<string, array<string, mixed>>
 */
function grid402_chain_telemetry_supported_chains(): array {
    return [
        'base' => [
            'type' => 'evm',
            'chain_id' => 8453,
            'llama_names' => ['Base'],
            'rpcs' => ['https://mainnet.base.org', 'https://base.llamarpc.com'],
        ],
        'ethereum' => [
            'type' => 'evm',
            'chain_id' => 1,
            'llama_names' => ['Ethereum'],
            'rpcs' => ['https://eth.llamarpc.com', 'https://cloudflare-eth.com'],
        ],
        'polygon' => [
            'type' => 'evm',
            'chain_id' => 137,
            'llama_names' => ['Polygon'],
            'rpcs' => ['https://polygon-rpc.com', 'https://polygon.llamarpc.com'],
        ],
        'arbitrum' => [
            'type' => 'evm',
            'chain_id' => 42161,
            'llama_names' => ['Arbitrum'],
            'rpcs' => ['https://arb1.arbitrum.io/rpc', 'https://arbitrum.llamarpc.com'],
        ],
        'solana' => [
            'type' => 'solana',
            'chain_id' => 'mainnet-beta',
            'llama_names' => ['Solana'],
            'rpcs' => ['https://api.mainnet-beta.solana.com', 'https://solana-rpc.publicnode.com'],
        ],
    ];
}

/**
 * Valida e normaliza parametro "chain" de /api/v1/chain-telemetry.
 *
 * @return array<string, mixed>
 */
function grid402_chain_telemetry_validate_input(string $chain = 'base'): array {
    $normChain = strtolower(trim($chain));
    if ($normChain === '') {
        $normChain = 'base';
    }
    $aliases = [
        'eth' => 'ethereum',
        'matic' => 'polygon',
        'arb' => 'arbitrum',
        'sol' => 'solana',
    ];
    if (isset($aliases[$normChain])) {
        $normChain = $aliases[$normChain];
    }

    $supported = grid402_chain_telemetry_supported_chains();
    if (!isset($supported[$normChain])) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidChainParameter',
            'message' => 'Rede invalida. Redes suportadas: base, ethereum, polygon, arbitrum, solana.',
        ];
    }

    return [
        'ok' => true,
        'chain' => $normChain,
        'meta' => $supported[$normChain],
    ];
}

/**
 * Executa HTTP GET ou POST JSON com suporte a override em $GLOBALS['grid402_chain_telemetry_http_override'].
 *
 * @return array{ok: bool, status: int, data: mixed, latency_ms: int, error: ?string}
 */
function grid402_chain_telemetry_http_request(string $method, string $url, ?array $jsonBody = null, int $timeoutSec = 4): array {
    $override = $GLOBALS['grid402_chain_telemetry_http_override'] ?? null;
    if (is_callable($override)) {
        $res = $override($method, $url, $jsonBody, $timeoutSec);
        if (!isset($res['latency_ms'])) {
            $res['latency_ms'] = 12;
        }
        return $res;
    }

    $started = microtime(true);
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSec),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Grid402-ChainTelemetry/1.0 (+https://grid402.online)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if (strtoupper($method) === 'POST') {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = (string) json_encode($jsonBody ?? [], JSON_UNESCAPED_SLASHES);
    }

    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $latencyMs = max(1, (int) round((microtime(true) - $started) * 1000));

    if ($status !== 200 || !is_string($raw) || $raw === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'latency_ms' => $latencyMs,
            'error' => $err !== '' ? $err : ('HTTP ' . $status),
        ];
    }

    $decoded = @json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'latency_ms' => $latencyMs,
            'error' => 'InvalidJSON',
        ];
    }

    return ['ok' => true, 'status' => 200, 'data' => $decoded, 'latency_ms' => $latencyMs, 'error' => null];
}

/**
 * Converte string hex ("0x...") em inteiro/float de forma segura sem estourar 32-bit.
 */
function grid402_chain_telemetry_hex_to_float(string $hex): float {
    $clean = strtolower(trim($hex));
    if (str_starts_with($clean, '0x')) {
        $clean = substr($clean, 2);
    }
    if ($clean === '' || !preg_match('/^[0-9a-f]+$/', $clean)) {
        return 0.0;
    }
    return (float) hexdec($clean);
}

/**
 * Consulta RPC EVM com fallback entre endpoints da rede.
 *
 * @param string[] $rpcs
 * @return array{ok: bool, block_number?: int, gas_gwei?: float, priority_fee_gwei?: ?float, rpc_latency_ms?: int, rpc_url?: string, error?: string}
 */
function grid402_chain_telemetry_fetch_evm_rpc(array $rpcs): array {
    $lastErr = 'AllRpcsFailed';
    foreach ($rpcs as $rpcUrl) {
        $batchReq = [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'eth_gasPrice', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'eth_maxPriorityFeePerGas', 'params' => []],
        ];
        $resp = grid402_chain_telemetry_http_request('POST', $rpcUrl, $batchReq, 4);
        if (empty($resp['ok']) || !is_array($resp['data'])) {
            $lastErr = (string) ($resp['error'] ?? 'RpcError');
            continue;
        }

        $byId = [];
        $data = $resp['data'];
        if (isset($data['id'])) {
            $byId[(int) $data['id']] = $data;
        } else {
            foreach ($data as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $byId[(int) $row['id']] = $row;
                }
            }
        }

        $blockHex = $byId[1]['result'] ?? null;
        $gasHex = $byId[2]['result'] ?? null;
        if (!is_string($blockHex) || !is_string($gasHex)) {
            $lastErr = 'MissingRpcResult';
            continue;
        }

        $blockNum = (int) round(grid402_chain_telemetry_hex_to_float($blockHex));
        $gasWei = grid402_chain_telemetry_hex_to_float($gasHex);
        $gasGwei = round($gasWei / 1e9, 6);

        $prioGwei = null;
        $prioHex = $byId[3]['result'] ?? null;
        if (is_string($prioHex)) {
            $prioGwei = round(grid402_chain_telemetry_hex_to_float($prioHex) / 1e9, 6);
        }

        return [
            'ok' => true,
            'block_number' => $blockNum,
            'gas_gwei' => $gasGwei,
            'priority_fee_gwei' => $prioGwei,
            'rpc_latency_ms' => (int) ($resp['latency_ms'] ?? 15),
            'rpc_url' => $rpcUrl,
        ];
    }

    return ['ok' => false, 'error' => $lastErr];
}

/**
 * Consulta RPC Solana com fallback entre endpoints publicos.
 *
 * @param string[] $rpcs
 * @return array{ok: bool, block_number?: int, slot?: int, priority_fee_microlamports_median?: float, rpc_latency_ms?: int, rpc_url?: string, error?: string}
 */
function grid402_chain_telemetry_fetch_solana_rpc(array $rpcs): array {
    $lastErr = 'AllSolanaRpcsFailed';
    foreach ($rpcs as $rpcUrl) {
        $batchReq = [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'getSlot', 'params' => [['commitment' => 'confirmed']]],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'getRecentPrioritizationFees', 'params' => []],
        ];
        $resp = grid402_chain_telemetry_http_request('POST', $rpcUrl, $batchReq, 4);
        if (empty($resp['ok']) || !is_array($resp['data'])) {
            $lastErr = (string) ($resp['error'] ?? 'SolanaRpcError');
            continue;
        }

        $byId = [];
        $data = $resp['data'];
        if (isset($data['id'])) {
            $byId[(int) $data['id']] = $data;
        } else {
            foreach ($data as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $byId[(int) $row['id']] = $row;
                }
            }
        }

        $slotVal = $byId[1]['result'] ?? null;
        if (!is_numeric($slotVal)) {
            $lastErr = 'MissingSolanaSlot';
            continue;
        }

        $fees = [];
        if (isset($byId[2]['result']) && is_array($byId[2]['result'])) {
            foreach ($byId[2]['result'] as $feeRow) {
                if (is_array($feeRow) && isset($feeRow['prioritizationFee']) && is_numeric($feeRow['prioritizationFee'])) {
                    $fees[] = (float) $feeRow['prioritizationFee'];
                }
            }
        }
        sort($fees);
        $medianFee = 0.0;
        if (!empty($fees)) {
            $mid = (int) floor(count($fees) / 2);
            $medianFee = round($fees[$mid], 2);
        }

        $slotInt = (int) $slotVal;
        return [
            'ok' => true,
            'block_number' => $slotInt,
            'slot' => $slotInt,
            'priority_fee_microlamports_median' => $medianFee,
            'rpc_latency_ms' => (int) ($resp['latency_ms'] ?? 18),
            'rpc_url' => $rpcUrl,
        ];
    }

    return ['ok' => false, 'error' => $lastErr];
}

/**
 * Consulta TVL da cadeia na DeFiLlama com cache compartilhado de 300s.
 *
 * @param string[] $llamaNames
 * @return array{ok: bool, tvl_usd?: ?float, llama_name?: ?string, error?: string}
 */
function grid402_chain_telemetry_fetch_defillama(array $llamaNames, string $privateDir, bool $useOverride): array {
    $cacheFile = $privateDir . '/defillama_chains_cache.json';
    $ttl = 300;
    $chainsList = null;

    if (!$useOverride && file_exists($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = $raw ? @json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['cached_at']) && (time() - (int) $decoded['cached_at']) < $ttl && is_array($decoded['chains'] ?? null)) {
            $chainsList = $decoded['chains'];
        }
    }

    if ($chainsList === null) {
        $resp = grid402_chain_telemetry_http_request('GET', 'https://api.llama.fi/v2/chains', null, 4);
        if (!empty($resp['ok']) && is_array($resp['data'])) {
            $chainsList = $resp['data'];
            if (!$useOverride && function_exists('grid402_atomic_write')) {
                grid402_atomic_write($cacheFile, [
                    'cached_at' => time(),
                    'chains' => $chainsList,
                ]);
            }
        } else {
            return ['ok' => false, 'error' => (string) ($resp['error'] ?? 'DeFiLlamaUnavailable')];
        }
    }

    $targets = array_map('strtolower', $llamaNames);
    foreach ($chainsList as $row) {
        if (!is_array($row)) {
            continue;
        }
        $nameLower = strtolower(trim((string) ($row['name'] ?? '')));
        if ($nameLower !== '' && in_array($nameLower, $targets, true)) {
            $tvl = isset($row['tvl']) && is_numeric($row['tvl']) ? round((float) $row['tvl'], 2) : null;
            return [
                'ok' => true,
                'tvl_usd' => $tvl,
                'llama_name' => (string) ($row['name'] ?? $llamaNames[0]),
            ];
        }
    }

    return ['ok' => true, 'tvl_usd' => null, 'llama_name' => $llamaNames[0] ?? null];
}

/**
 * Teaser gratuito para resposta HTTP 402.
 *
 * @return array<string, mixed>
 */
function grid402_service_chain_telemetry_preview(string $chain = 'base'): array {
    $val = grid402_chain_telemetry_validate_input($chain !== '' ? $chain : 'base');
    return [
        'preview' => [
            'chain' => $val['ok'] ? $val['chain'] : 'base',
            'chain_id' => $val['ok'] ? $val['meta']['chain_id'] : 8453,
            'supported_chains' => array_keys(grid402_chain_telemetry_supported_chains()),
            'sources' => ['Public Network RPC', 'DeFiLlama Chains API'],
        ],
        'locked_fields' => ['block_number', 'gas_gwei', 'tvl_usd', 'rpc_latency_ms', 'sources_status', '_oracle_proof'],
    ];
}

/**
 * Consulta consolidada de telemetria multi-chain com cache Zero-DB (60s).
 *
 * @return array<string, mixed>
 */
function grid402_service_chain_telemetry(string $chain = 'base'): array {
    $val = grid402_chain_telemetry_validate_input($chain);
    if (empty($val['ok'])) {
        return [
            'success' => false,
            'error' => $val['error'],
            'message' => $val['message'],
            'code' => (int) ($val['code'] ?? 400),
        ];
    }

    $chainKey = (string) $val['chain'];
    $meta = $val['meta'];

    $privateDir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/../private_data');
    $useOverride = is_callable($GLOBALS['grid402_chain_telemetry_http_override'] ?? null);
    $cacheFile = $privateDir . '/cache_chain_telemetry_' . $chainKey . '.json';
    $cacheTtl = 60;

    if (!$useOverride && file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = $rawCache ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < $cacheTtl) {
            $decodedCache['cached'] = true;
            return $decodedCache;
        }
    }

    $rpcRes = ($meta['type'] === 'solana')
        ? grid402_chain_telemetry_fetch_solana_rpc($meta['rpcs'])
        : grid402_chain_telemetry_fetch_evm_rpc($meta['rpcs']);

    $llamaRes = grid402_chain_telemetry_fetch_defillama($meta['llama_names'], $privateDir, $useOverride);

    $rpcOk = !empty($rpcRes['ok']);
    $llamaOk = !empty($llamaRes['ok']);

    $sourcesStatus = [
        'rpc' => $rpcOk ? 'ok' : ('unavailable (' . (string) ($rpcRes['error'] ?? 'error') . ')'),
        'defillama' => $llamaOk ? 'ok' : ('unavailable (' . (string) ($llamaRes['error'] ?? 'error') . ')'),
    ];

    if (!$rpcOk && !$llamaOk) {
        if (!$useOverride && file_exists($cacheFile)) {
            $staleRaw = @file_get_contents($cacheFile);
            $stale = $staleRaw ? @json_decode($staleRaw, true) : null;
            if (is_array($stale)) {
                $stale['cached'] = true;
                $stale['stale_fallback'] = true;
                return $stale;
            }
        }
        return [
            'success' => false,
            'error' => 'UpstreamUnavailable',
            'message' => 'RPC publico e DeFiLlama indisponiveis para a rede solicitada. Nenhum debito foi mantido.',
            'sources_status' => $sourcesStatus,
            'code' => 503,
        ];
    }

    $payload = [
        'protocol' => 'x402',
        'service' => 'chain-telemetry',
        'status' => 'UP',
        'chain' => $chainKey,
        'chain_type' => $meta['type'],
        'chain_id' => $meta['chain_id'],
        'block_number' => $rpcOk ? ($rpcRes['block_number'] ?? null) : null,
        'slot' => ($meta['type'] === 'solana' && $rpcOk) ? ($rpcRes['slot'] ?? null) : null,
        'gas_gwei' => ($meta['type'] === 'evm' && $rpcOk) ? ($rpcRes['gas_gwei'] ?? null) : null,
        'priority_fee_gwei' => ($meta['type'] === 'evm' && $rpcOk) ? ($rpcRes['priority_fee_gwei'] ?? null) : null,
        'priority_fee_microlamports_median' => ($meta['type'] === 'solana' && $rpcOk) ? ($rpcRes['priority_fee_microlamports_median'] ?? null) : null,
        'rpc_latency_ms' => $rpcOk ? ($rpcRes['rpc_latency_ms'] ?? null) : null,
        'tvl_usd' => $llamaOk ? ($llamaRes['tvl_usd'] ?? null) : null,
        'partial_data' => !($rpcOk && $llamaOk),
        'sources_status' => $sourcesStatus,
        'legal_disclaimer' => 'Real-time public blockchain RPC and DeFiLlama chain telemetry snapshot. Informational network data only.',
        'cached' => false,
        'cached_at' => time(),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if (!$useOverride && function_exists('grid402_atomic_write')) {
        grid402_atomic_write($cacheFile, $payload);
    }

    return $payload;
}
