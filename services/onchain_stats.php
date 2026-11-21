<?php
/**
 * Grid402 - Reconciliação On-Chain (read-only)
 *
 * Compara a telemetria interna (metrics.json/growth.json) com eventos ERC-20
 * Transfer (USDC) recebidos pela carteira oráculo na Base Mainnet.
 *
 * - Nenhuma transação é assinada ou enviada.
 * - Nenhum endereço de pagador é persistido: apenas contagem e últimos 4 hex.
 * - Resultado fica em onchain_stats_cache.json e é lido pelo /admin.
 *
 * Execução:
 *   CLI:  php services/onchain_stats.php [window_blocks]
 *   HTTP: /services/onchain_stats.php?token=CRON_SECRET&blocks=50000
 */

declare(strict_types=1);

// Permite execução direta apenas via CLI ou com token; inclui via require livremente.
if (defined('GRID402_EXEC')) {
    // Inclusão interna: apenas funções.
}

require_once __DIR__ . '/../config.php';

/**
 * Chamada JSON-RPC genérica com fallback de nós públicos da Base.
 * Tenta todos os nós; retorna o primeiro sucesso.
 */
function grid402_base_rpc_call(string $method, array $params, int $timeoutSec = 4): ?array {
    $rpcUrls = [
        GRID402_BASE_RPC_URL,
        'https://base.llamarpc.com',
        'https://1rpc.io/base',
    ];

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params,
    ], JSON_UNESCAPED_SLASHES);

    $errors = [];

    foreach ($rpcUrls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_USERAGENT => 'Grid402-OnchainRecon/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response)) {
            $json = @json_decode($response, true);
            if (is_array($json) && array_key_exists('result', $json)) {
                return ['ok' => true, 'result' => $json['result']];
            }
            if (is_array($json) && isset($json['error'])) {
                $errors[] = (string)($json['error']['message'] ?? 'rpc_error');
                continue;
            }
            $errors[] = 'malformed_response';
            continue;
        }
        $errors[] = 'http_' . $httpCode;
    }

    return ['ok' => false, 'error' => implode(' | ', $errors) ?: 'all_nodes_failed'];
}

/**
 * Agrega logs Transfer de forma pura (testável offline).
 *
 * @param array  $logs             Logs retornados por eth_getLogs
 * @param string $expectedRecipient Endereço recebedor sem 0x, minúsculo (40 hex)
 * @param string $usdcContract     Contrato USDC minúsculo
 * @param string $transferTopic    Topic0 do evento Transfer
 * @return array
 */
function grid402_onchain_aggregate_logs(array $logs, string $expectedRecipient, string $usdcContract, string $transferTopic): array {
    $expectedRecipient = strtolower($expectedRecipient);
    $usdcContract = strtolower($usdcContract);
    $transferTopic = strtolower($transferTopic);

    $totalRaw = 0;
    $transfers = 0;
    $senders = [];
    $maxBlock = 0;

    foreach ($logs as $log) {
        $contract = strtolower((string)($log['address'] ?? ''));
        if ($contract !== $usdcContract) {
            continue;
        }
        $topics = (array)($log['topics'] ?? []);
        if (count($topics) < 3 || strtolower((string)$topics[0]) !== $transferTopic) {
            continue;
        }
        $recipientTopic = strtolower((string)$topics[2]);
        if (substr($recipientTopic, 26) !== $expectedRecipient) {
            continue;
        }
        $dataHex = (string)($log['data'] ?? '0x0');
        $raw = 0;
        if (preg_match('/^0x[0-9a-fA-F]+$/', $dataHex)) {
            $raw = (int) hexdec(substr($dataHex, 2));
        }
        $totalRaw += $raw;
        $transfers++;
        $sender = strtolower((string)$topics[1]);
        $senders[$sender] = true;
        $block = isset($log['blockNumber']) ? (int) hexdec((string)$log['blockNumber']) : 0;
        if ($block > $maxBlock) {
            $maxBlock = $block;
        }
    }

    $uniqueSenders = count($senders);
    $senderLast4 = [];
    foreach (array_keys($senders) as $s) {
        $senderLast4[] = substr($s, -4);
        if (count($senderLast4) >= 25) {
            break;
        }
    }

    return [
        'received_raw_units' => $totalRaw,
        'received_usdc' => round($totalRaw / 1000000, 8),
        'transfers' => $transfers,
        'unique_senders' => $uniqueSenders,
        'sender_last4' => $senderLast4,
        'max_block' => $maxBlock,
    ];
}

/**
 * Caminho do cache on-chain.
 */
function grid402_onchain_stats_file(): string {
    return GRID402_PRIVATE_DIR . '/onchain_stats_cache.json';
}

/**
 * Lê o snapshot on-chain cacheado.
 */
function grid402_onchain_stats_read(): array {
    $file = grid402_onchain_stats_file();
    if (!file_exists($file)) {
        return [
            'generated_at' => null,
            'window_blocks' => 0,
            'from_block' => null,
            'to_block' => null,
            'received_raw_units' => 0,
            'received_usdc' => 0.0,
            'transfers' => 0,
            'unique_senders' => 0,
            'sender_last4' => [],
            'max_block' => 0,
            'rpc_ok' => false,
            'note' => 'never_run',
        ];
    }
    $decoded = @json_decode((string) @file_get_contents($file), true);
    return is_array($decoded) ? $decoded : grid402_onchain_stats_read_default();
}

function grid402_onchain_stats_read_default(): array {
    return [
        'generated_at' => null,
        'window_blocks' => 0,
        'from_block' => null,
        'to_block' => null,
        'received_raw_units' => 0,
        'received_usdc' => 0.0,
        'transfers' => 0,
        'unique_senders' => 0,
        'sender_last4' => [],
        'max_block' => 0,
        'rpc_ok' => false,
        'note' => 'decode_error',
    ];
}

/**
 * Busca logs Transfer fatiados em janelas menores (limite típico de public RPCs).
 * Retorna [logs[], chunksFailed, chunksTotal].
 */
function grid402_onchain_fetch_logs_chunked(array $filter, int $fromBlock, int $toBlock): array {
    $logs = [];
    $chunksFailed = 0;
    $chunksTotal = 0;

    for ($start = $fromBlock; $start <= $toBlock; $start += 5000) {
        $chunksTotal++;
        $end = min($toBlock, $start + 4999);
        $chunkFilter = $filter;
        $chunkFilter['fromBlock'] = '0x' . dechex($start);
        $chunkFilter['toBlock'] = '0x' . dechex($end);

        $resp = grid402_base_rpc_call('eth_getLogs', [$chunkFilter], 6);
        if (!$resp || empty($resp['ok'])) {
            $chunksFailed++;
            continue;
        }
        foreach ((array) $resp['result'] as $log) {
            $logs[] = $log;
        }
    }

    return [$logs, $chunksFailed, $chunksTotal];
}

/**
 * Atualiza o snapshot on-chain via eth_getLogs. Read-only.
 * Janela limitada a 60.000 blocos por chamada (~33h na Base).
 */
function grid402_onchain_stats_refresh(int $blockWindow = 30000): array {
    $blockWindow = max(1000, min(60000, $blockWindow));

    $latest = grid402_base_rpc_call('eth_blockNumber', []);
    $cached = grid402_onchain_stats_read();

    if (!$latest || empty($latest['ok']) || empty($latest['result'])) {
        $cached['rpc_ok'] = false;
        $cached['note'] = 'blockNumber unavailable; keeping previous snapshot';
        $cached['refresh_attempted_at'] = gmdate('Y-m-d\TH:i:s\Z');
        grid402_onchain_stats_write($cached);
        return $cached;
    }

    $toBlock = (int) hexdec((string) $latest['result']);
    $fromBlock = max(0, $toBlock - $blockWindow);

    $usdcContract = strtolower(GRID402_USDC_CONTRACT);
    $targetWalletTopic = '0x' . str_pad(substr(strtolower(GRID402_WALLET_ADDRESS), 2), 64, '0', STR_PAD_LEFT);
    $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    $filter = [
        'address' => GRID402_USDC_CONTRACT,
        'topics' => [$transferTopic, null, $targetWalletTopic],
        'fromBlock' => null,
        'toBlock' => null,
    ];

    [$logs, $chunksFailed, $chunksTotal] = grid402_onchain_fetch_logs_chunked($filter, $fromBlock, $toBlock);

    if ($chunksTotal > 0 && $chunksFailed === $chunksTotal) {
        $cached['rpc_ok'] = false;
        $cached['note'] = 'eth_getLogs unavailable on all chunks; keeping previous snapshot';
        $cached['refresh_attempted_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $cached['to_block_attempted'] = $toBlock;
        grid402_onchain_stats_write($cached);
        return $cached;
    }

    $agg = grid402_onchain_aggregate_logs(
        $logs,
        substr(strtolower(GRID402_WALLET_ADDRESS), 2),
        $usdcContract,
        $transferTopic
    );

    $note = ($chunksFailed > 0)
        ? ('partial: ' . $chunksFailed . '/' . $chunksTotal . ' chunks failed; values are undercounted')
        : null;

    $snapshot = array_merge([
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'window_blocks' => $blockWindow,
        'from_block' => $fromBlock,
        'to_block' => $toBlock,
        'rpc_ok' => true,
        'partial' => $chunksFailed > 0,
        'chunks_failed' => $chunksFailed,
        'chunks_total' => $chunksTotal,
        'note' => $note,
    ], $agg);

    grid402_onchain_stats_write($snapshot);
    return $snapshot;
}

/**
 * Persistência atômica do snapshot.
 */
function grid402_onchain_stats_write(array $snapshot): bool {
    $file = grid402_onchain_stats_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = tempnam($dir, 'onchain_');
    if ($tmp === false) {
        return file_put_contents($file, $json) !== false;
    }
    file_put_contents($tmp, $json);
    $ok = @rename($tmp, $file);
    if (!$ok) {
        @file_put_contents($file, $json);
        @unlink($tmp);
    }
    @chmod($file, 0600);
    return true;
}

/**
 * Comparativo entre telemetria interna e chain (para dashboard).
 */
function grid402_onchain_stats_compare(array $onchain, array $metrics): array {
    $telemetryRevenue = (float)($metrics['total_revenue_usdc'] ?? 0.0);
    $telemetryPaid = (int)($metrics['total_paid_requests'] ?? ($metrics['paid_requests'] ?? 0));
    $sources = (array)($metrics['payment_sources'] ?? []);
    $telemetryTxHashPayments = (int)(($sources['tx_hash'] ?? 0)) + (int)(($sources['facilitator'] ?? 0));

    $onchainUsdc = (float)($onchain['received_usdc'] ?? 0.0);
    $onchainTransfers = (int)($onchain['transfers'] ?? 0);

    $revenueDelta = round($onchainUsdc - (float)($metrics['total_revenue_usdc'] ?? 0.0), 8);

    return [
        'window_blocks' => (int)($onchain['window_blocks'] ?? 0),
        'onchain_received_usdc' => $onchainUsdc,
        'onchain_transfers' => $onchainTransfers,
        'onchain_unique_senders' => (int)($onchain['unique_senders'] ?? 0),
        'telemetry_total_revenue_usdc' => round($telemetryRevenue, 8),
        'telemetry_paid_requests' => $telemetryPaid,
        'telemetry_txhash_and_facilitator_count' => $telemetryTxHashPayments,
        'revenue_delta_usdc' => $revenueDelta,
        'note' => 'delta inclui pass purchases e qualquer Transfer recebida na janela; use como sinal, nao como verdade absoluta',
        'generated_at' => $onchain['generated_at'] ?? null,
        'rpc_ok' => (bool)($onchain['rpc_ok'] ?? false),
    ];
}

// ============================================================
// Modo direto: APENAS quando executado como script (CLI ou HTTP direto)
// Inclusões via require (admin, tests) nunca executam o modo direto.
// ============================================================
$isDirect = (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__);

if ($isDirect) {
    $authorized = (php_sapi_name() === 'cli');

    if (!$authorized) {
        $queryToken = (string) ($_GET['token'] ?? '');
        $authorized = ($queryToken !== '' && hash_equals(GRID402_CRON_SECRET, $queryToken));
    }

    if (!$authorized) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden'], JSON_PRETTY_PRINT);
        exit;
    }

    $window = 30000;
    if (php_sapi_name() === 'cli' && isset($argv[1]) && is_numeric($argv[1])) {
        $window = (int) $argv[1];
    } elseif (isset($_GET['blocks']) && is_numeric($_GET['blocks'])) {
        $window = (int) $_GET['blocks'];
    }

    $snapshot = grid402_onchain_stats_refresh($window);

    require_once __DIR__ . '/../metrics.php';
    $metrics = grid402_get_metrics();
    $compare = grid402_onchain_stats_compare($snapshot, $metrics);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'snapshot' => $snapshot,
        'compare' => $compare,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
