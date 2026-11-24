<?php
/**
 * Grid402 - Reconciliação On-Chain (read-only)
 *
 * Compara a telemetria interna (metrics.json/growth.json) com eventos ERC-20
 * Transfer (USDC) recebidos pela carteira oráculo na Base Mainnet e saldos
 * USDC multi-chain (Base, Polygon, Arbitrum e Solana).
 *
 * - Nenhuma transação é assinada ou enviada.
 * - Nenhum endereço de pagador é persistido: apenas contagem e últimos 4 hex.
 * - Resultado fica em onchain_stats_cache.json e é lido pelo /admin.
 *
 * Execução:
 *   CLI:  php services/onchain_stats.php [window_blocks]
 *   HTTP: /services/onchain_stats.php?token=CRON_SECRET&blocks=200000
 */

declare(strict_types=1);

// Permite execução direta apenas via CLI ou com token; inclui via require livremente.
if (defined('GRID402_EXEC')) {
    // Inclusão interna: apenas funções.
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/multichain.php';

/**
 * Chamada JSON-RPC genérica com failover sobre uma lista de URLs RPC.
 */
function grid402_json_rpc_call(array $rpcUrls, string $method, array $params, int $timeoutSec = 5): array {
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params,
    ], JSON_UNESCAPED_SLASHES);

    $errors = [];

    foreach (array_values(array_unique($rpcUrls)) as $url) {
        $url = trim((string) $url);
        if ($url === '' || !str_starts_with($url, 'https://')) {
            continue;
        }
        $ch = curl_init($url);
        if (!$ch) {
            $errors[] = 'curl_init_failed';
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Grid402-OnchainRecon/2.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = ($response === false) ? (string) curl_error($ch) : '';
        curl_close($ch);

        if ($httpCode === 200 && is_string($response)) {
            $json = @json_decode($response, true);
            if (is_array($json) && array_key_exists('result', $json)) {
                return ['ok' => true, 'result' => $json['result'], 'rpc' => $url];
            }
            if (is_array($json) && isset($json['error'])) {
                $errors[] = (string) ($json['error']['message'] ?? 'rpc_error');
                continue;
            }
            $errors[] = 'malformed_response';
            continue;
        }
        $errors[] = $curlErr !== '' ? ('curl:' . substr($curlErr, 0, 48)) : ('http_' . $httpCode);
    }

    return ['ok' => false, 'error' => implode(' | ', $errors) ?: 'all_nodes_failed'];
}

/**
 * Chamada JSON-RPC genérica com fallback de nós públicos saudáveis da Base (V-20 / V-21).
 * Remove base.llamarpc.com (HTTP 525) e exclui 1rpc.io/base e base.drpc.org para eth_getLogs
 * (onde limitam range a 50-100 blocos).
 */
function grid402_base_rpc_call(string $method, array $params, int $timeoutSec = 5): ?array {
    $isGetLogs = (strtolower($method) === 'eth_getlogs');

    // Lista saudavel diferenciada para eth_getLogs vs chamadas pontuais (eth_blockNumber / eth_call)
    $rpcUrls = $isGetLogs
        ? [
            'https://base-rpc.publicnode.com',
            'https://mainnet.base.org',
        ]
        : [
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
        array_unshift($rpcUrls, $configured);
    }

    $envCustom = function_exists('grid402_env') ? trim((string) grid402_env('GRID402_RPC_BASE', '')) : '';
    if ($envCustom !== '' && str_starts_with($envCustom, 'https://') && rtrim(strtolower($envCustom), '/') !== 'https://base.llamarpc.com') {
        array_unshift($rpcUrls, $envCustom);
    }

    return grid402_json_rpc_call($rpcUrls, $method, $params, $timeoutSec);
}

/**
 * Agrega logs Transfer de forma pura (testável offline).
 *
 * @param array  $logs              Logs retornados por eth_getLogs
 * @param string $expectedRecipient Endereço recebedor sem 0x, minúsculo (40 hex)
 * @param string $usdcContract      Contrato USDC minúsculo
 * @param string $transferTopic     Topic0 do evento Transfer
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
        $contract = strtolower((string) ($log['address'] ?? ''));
        if ($contract !== $usdcContract) {
            continue;
        }
        $topics = (array) ($log['topics'] ?? []);
        if (count($topics) < 3 || strtolower((string) $topics[0]) !== $transferTopic) {
            continue;
        }
        $recipientTopic = strtolower((string) $topics[2]);
        if (substr($recipientTopic, -40) !== $expectedRecipient) {
            continue;
        }
        $dataHex = strtolower((string) ($log['data'] ?? '0x0'));
        $raw = 0;
        if (preg_match('/^0x[0-9a-f]+$/', $dataHex)) {
            $cleanHex = ltrim(substr($dataHex, 2), '0');
            $raw = ($cleanHex === '') ? 0 : (int) hexdec($cleanHex);
        }
        $totalRaw += $raw;
        $transfers++;
        $sender = strtolower((string) $topics[1]);
        $senders[$sender] = true;
        $block = isset($log['blockNumber']) ? (int) hexdec((string) $log['blockNumber']) : 0;
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
            'wallet_usdc_balance' => null,
            'wallet_usdc_balance_total' => null,
            'wallet_balances' => [],
            'rpc_ok' => false,
            'rpc_partial' => false,
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
        'wallet_usdc_balance' => null,
        'wallet_usdc_balance_total' => null,
        'wallet_balances' => [],
        'rpc_ok' => false,
        'rpc_partial' => false,
        'note' => 'decode_error',
    ];
}

/**
 * Busca logs Transfer fatiados em janelas menores (compatível com limites de public RPCs).
 * Retorna [logs[], chunksFailed, chunksTotal].
 */
function grid402_onchain_fetch_logs_chunked(array $filter, int $fromBlock, int $toBlock): array {
    $logs = [];
    $chunksFailed = 0;
    $chunksTotal = 0;
    $step = ($toBlock - $fromBlock) > 60000 ? 40000 : 10000;

    for ($start = $fromBlock; $start <= $toBlock; $start += $step) {
        $chunksTotal++;
        $end = min($toBlock, $start + $step - 1);
        $chunkFilter = $filter;
        $chunkFilter['fromBlock'] = '0x' . dechex($start);
        $chunkFilter['toBlock'] = '0x' . dechex($end);

        $resp = grid402_base_rpc_call('eth_getLogs', [$chunkFilter], 8);
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
 * Consulta o saldo atual de USDC (ERC-20 balanceOf) da carteira oficial em uma rede EVM suportada.
 * Soma todos os contratos oficiais USDC da rede (ex.: Native USDC + USDC.e em Polygon/Arbitrum).
 */
function grid402_onchain_fetch_evm_usdc_balance(string $networkSlug, int $timeoutSec = 5): ?float {
    $networks = grid402_supported_networks();
    $slug = grid402_normalize_network_slug($networkSlug);
    if ($slug === null || !isset($networks[$slug]) || ($networks[$slug]['type'] ?? '') !== 'evm') {
        return null;
    }

    $cfg = $networks[$slug];
    $wallet = strtolower((string) ($cfg['recipient'] ?? GRID402_WALLET_ADDRESS));
    $cleanAddr = str_starts_with($wallet, '0x') ? substr($wallet, 2) : $wallet;
    $callData = '0x70a08231' . str_pad($cleanAddr, 64, '0', STR_PAD_LEFT);

    $contracts = (array) ($cfg['usdc_contracts'] ?? [$cfg['usdc_contract']]);
    $rpcs = (array) ($cfg['rpcs'] ?? []);

    $totalRaw = 0;
    $anyOk = false;
    foreach ($contracts as $contract) {
        $res = ($slug === 'base')
            ? grid402_base_rpc_call('eth_call', [['to' => (string) $contract, 'data' => $callData], 'latest'], $timeoutSec)
            : grid402_json_rpc_call($rpcs, 'eth_call', [['to' => (string) $contract, 'data' => $callData], 'latest'], $timeoutSec);

        if (!$res || empty($res['ok']) || !is_string($res['result'] ?? null) || !str_starts_with($res['result'], '0x')) {
            continue;
        }
        $anyOk = true;
        $hexVal = ltrim(substr(strtolower($res['result']), 2), '0');
        if ($hexVal !== '') {
            $totalRaw += (int) hexdec($hexVal);
        }
    }

    return $anyOk ? round($totalRaw / 1000000, 6) : null;
}

/**
 * Consulta o saldo atual de USDC (ERC-20 balanceOf) da carteira oficial na Base Mainnet.
 */
function grid402_onchain_fetch_wallet_usdc_balance(): ?float {
    return grid402_onchain_fetch_evm_usdc_balance('base', 5);
}

/**
 * Consulta saldo SPL USDC e status de inicializacao da ATA da carteira Solana oficial (read-only).
 */
function grid402_onchain_fetch_solana_usdc_balance(int $timeoutSec = 5): array {
    $networks = grid402_supported_networks();
    $solCfg = $networks['solana'] ?? [];
    $solWallet = (string) ($solCfg['recipient'] ?? '');
    $solMint = (string) ($solCfg['usdc_contract'] ?? 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v');
    $rpcs = (array) ($solCfg['rpcs'] ?? ['https://solana-rpc.publicnode.com', 'https://api.mainnet-beta.solana.com']);

    if ($solWallet === '') {
        return ['rpc_ok' => false, 'usdc_balance' => null, 'ata_initialized' => false];
    }

    $res = grid402_json_rpc_call($rpcs, 'getTokenAccountsByOwner', [
        $solWallet,
        ['mint' => $solMint],
        ['encoding' => 'jsonParsed'],
    ], $timeoutSec);

    if (empty($res['ok']) || !is_array($res['result'] ?? null) || !array_key_exists('value', $res['result'])) {
        return ['rpc_ok' => false, 'usdc_balance' => null, 'ata_initialized' => false];
    }

    $accounts = is_array($res['result']['value']) ? $res['result']['value'] : [];
    $totalRaw = 0;
    foreach ($accounts as $acc) {
        $amtStr = (string) ($acc['account']['data']['parsed']['info']['tokenAmount']['amount'] ?? '0');
        if (ctype_digit($amtStr)) {
            $totalRaw += (int) $amtStr;
        }
    }

    return [
        'rpc_ok' => true,
        'usdc_balance' => round($totalRaw / 1000000, 6),
        'ata_initialized' => count($accounts) > 0,
        'token_accounts' => count($accounts),
    ];
}

/**
 * Consulta saldos reais de USDC nas 4 redes x402 principais (Base, Polygon, Arbitrum e Solana).
 * Independente de falhas em eth_getLogs (V-20).
 */
function grid402_onchain_fetch_multichain_balances(): array {
    $balances = [];
    $totalUsdc = 0.0;
    $anyOk = false;

    foreach (['base', 'polygon', 'arbitrum'] as $evmSlug) {
        $bal = grid402_onchain_fetch_evm_usdc_balance($evmSlug, 5);
        $balances[$evmSlug] = [
            'usdc_balance' => $bal,
            'rpc_ok' => ($bal !== null),
        ];
        if ($bal !== null) {
            $totalUsdc += $bal;
            $anyOk = true;
        }
    }

    $sol = grid402_onchain_fetch_solana_usdc_balance(5);
    $balances['solana'] = $sol;
    if (!empty($sol['rpc_ok']) && is_numeric($sol['usdc_balance'])) {
        $totalUsdc += (float) $sol['usdc_balance'];
        $anyOk = true;
    }

    return [
        'wallet_balances' => $balances,
        'base_usdc_balance' => $balances['base']['usdc_balance'],
        'total_usdc_balance' => $anyOk ? round($totalUsdc, 6) : null,
    ];
}

/**
 * Atualiza o snapshot on-chain via balanceOf multi-chain + eth_getLogs (Base). Read-only.
 * Base opera a ~5 blocos/s (~18.000 blocos/h, ~432.000 blocos/24h).
 */
function grid402_onchain_stats_refresh(int $blockWindow = 200000): array {
    $blockWindow = max(1000, min(1000000, $blockWindow));
    $cached = grid402_onchain_stats_read();

    // V-20: SEMPRE consulta saldos reais on-chain (Base + Polygon + Arbitrum + Solana)
    // ANTES e INDEPENDENTEMENTE de falhas em eth_blockNumber ou eth_getLogs.
    $mcBalances = grid402_onchain_fetch_multichain_balances();
    $walletUsdcBalance = $mcBalances['base_usdc_balance']
        ?? (isset($cached['wallet_usdc_balance']) && is_numeric($cached['wallet_usdc_balance']) ? (float) $cached['wallet_usdc_balance'] : null);
    $walletUsdcTotal = $mcBalances['total_usdc_balance']
        ?? (isset($cached['wallet_usdc_balance_total']) && is_numeric($cached['wallet_usdc_balance_total']) ? (float) $cached['wallet_usdc_balance_total'] : $walletUsdcBalance);

    $latest = grid402_base_rpc_call('eth_blockNumber', []);

    if (!$latest || empty($latest['ok']) || empty($latest['result'])) {
        $cached['wallet_usdc_balance'] = $walletUsdcBalance;
        $cached['wallet_usdc_balance_total'] = $walletUsdcTotal;
        $cached['wallet_balances'] = $mcBalances['wallet_balances'];
        $cached['rpc_ok'] = false;
        $cached['rpc_partial'] = false;
        $cached['note'] = 'blockNumber unavailable; balances refreshed and previous logs snapshot kept';
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
        $cached['wallet_usdc_balance'] = $walletUsdcBalance;
        $cached['wallet_usdc_balance_total'] = $walletUsdcTotal;
        $cached['wallet_balances'] = $mcBalances['wallet_balances'];
        $cached['rpc_ok'] = false;
        $cached['rpc_partial'] = false;
        $cached['chunks_failed'] = $chunksFailed;
        $cached['chunks_total'] = $chunksTotal;
        $cached['note'] = 'eth_getLogs unavailable on all chunks; balances refreshed and previous logs snapshot kept';
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

    $isPartial = ($chunksFailed > 0);
    $preservedPreviousLogs = false;

    // V-20: nunca sobrescreve receita positiva anterior com 0 parcial quando chunks falharam
    if ($isPartial && (int) ($agg['received_raw_units'] ?? 0) === 0 && (int) ($cached['received_raw_units'] ?? 0) > 0) {
        $agg = [
            'received_raw_units' => (int) ($cached['received_raw_units'] ?? 0),
            'received_usdc' => (float) ($cached['received_usdc'] ?? 0.0),
            'transfers' => (int) ($cached['transfers'] ?? 0),
            'unique_senders' => (int) ($cached['unique_senders'] ?? 0),
            'sender_last4' => (array) ($cached['sender_last4'] ?? []),
            'max_block' => (int) ($cached['max_block'] ?? 0),
        ];
        $preservedPreviousLogs = true;
    }

    $note = $isPartial
        ? ('partial: ' . $chunksFailed . '/' . $chunksTotal . ' chunks failed' . ($preservedPreviousLogs ? '; previous positive logs preserved' : '; values may be undercounted'))
        : null;

    $snapshot = array_merge([
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'window_blocks' => $blockWindow,
        'from_block' => $fromBlock,
        'to_block' => $toBlock,
        'wallet_usdc_balance' => $walletUsdcBalance,
        'wallet_usdc_balance_total' => $walletUsdcTotal,
        'wallet_balances' => $mcBalances['wallet_balances'],
        'rpc_ok' => !$isPartial,
        'rpc_partial' => $isPartial,
        'partial' => $isPartial,
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
 * Comparativo entre telemetria interna e saldos/eventos on-chain multi-chain (para dashboard).
 */
function grid402_onchain_stats_compare(array $onchain, array $metrics): array {
    $telemetryRevenue = (float) ($metrics['total_revenue_usdc'] ?? 0.0);
    $telemetryPaid = (int) ($metrics['total_paid_requests'] ?? ($metrics['paid_requests'] ?? 0));
    $sources = (array) ($metrics['payment_sources'] ?? []);

    // V-20: reconhece todas as fontes on-chain (tx_hash, facilitator, web3_topup, solana_spl, evm_*, multichain_*)
    $telemetryOnchainPayments = 0;
    $onchainSourcesBreakdown = [];
    foreach ($sources as $srcKey => $srcCount) {
        $k = strtolower((string) $srcKey);
        $cnt = (int) $srcCount;
        $isOnchainSource = in_array($k, ['tx_hash', 'facilitator', 'web3_topup', 'solana_spl'], true)
            || str_starts_with($k, 'evm_')
            || str_starts_with($k, 'multichain_');
        if ($isOnchainSource) {
            $telemetryOnchainPayments += $cnt;
            if ($cnt > 0) {
                $onchainSourcesBreakdown[$k] = $cnt;
            }
        }
    }

    $onchainUsdc = (float) ($onchain['received_usdc'] ?? 0.0);
    $onchainTransfers = (int) ($onchain['transfers'] ?? 0);
    $walletUsdc = isset($onchain['wallet_usdc_balance']) && is_numeric($onchain['wallet_usdc_balance'])
        ? (float) $onchain['wallet_usdc_balance']
        : null;
    $walletUsdcTotal = isset($onchain['wallet_usdc_balance_total']) && is_numeric($onchain['wallet_usdc_balance_total'])
        ? (float) $onchain['wallet_usdc_balance_total']
        : $walletUsdc;

    // Compara saldo acumulado real nas carteiras contra receita acumulada (quando disponivel),
    // evitando falso alarme ao comparar janela curta de blocos contra total vitalicio.
    $referenceUsdc = $walletUsdcTotal !== null ? $walletUsdcTotal : $onchainUsdc;
    $revenueDelta = round($referenceUsdc - $telemetryRevenue, 8);
    $rpcPartial = (bool) ($onchain['rpc_partial'] ?? $onchain['partial'] ?? false);

    return [
        'window_blocks' => (int) ($onchain['window_blocks'] ?? 0),
        'onchain_received_usdc' => $onchainUsdc,
        'wallet_usdc_balance' => $walletUsdc,
        'wallet_usdc_balance_total' => $walletUsdcTotal,
        'wallet_balances' => is_array($onchain['wallet_balances'] ?? null) ? $onchain['wallet_balances'] : null,
        'onchain_transfers' => $onchainTransfers,
        'onchain_unique_senders' => (int) ($onchain['unique_senders'] ?? 0),
        'telemetry_total_revenue_usdc' => round($telemetryRevenue, 8),
        'telemetry_paid_requests' => $telemetryPaid,
        'telemetry_txhash_and_facilitator_count' => $telemetryOnchainPayments,
        'telemetry_onchain_sources' => $onchainSourcesBreakdown,
        'revenue_delta_usdc' => $revenueDelta,
        'note' => $walletUsdcTotal !== null
            ? 'delta calculado entre saldo USDC real multi-chain (Base/Polygon/Arbitrum/Solana) e receita registrada na telemetria (transfers da janela exibidos separadamente)'
            : 'delta inclui pass purchases e qualquer Transfer recebida na janela; use como sinal, nao como verdade absoluta',
        'generated_at' => $onchain['generated_at'] ?? null,
        'rpc_ok' => (bool) ($onchain['rpc_ok'] ?? false),
        'rpc_partial' => $rpcPartial,
    ];
}

// ============================================================
// Modo direto: APENAS quando executado como script (CLI ou HTTP direto)
// Inclusões via require (admin, tests) nunca executam o modo direto.
// ============================================================
$isDirect = (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__);

if ($isDirect) {
    $authorized = (php_sapi_name() === 'cli');

    if (!$authorized) {
        $queryToken = (string) ($_GET['token'] ?? '');
        $authorized = (
            !grid402_is_default_secret()
            && $queryToken !== ''
            && hash_equals(GRID402_CRON_SECRET, $queryToken)
        );
    }

    if (!$authorized) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden'], JSON_PRETTY_PRINT);
        exit;
    }

    $window = 200000;
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
