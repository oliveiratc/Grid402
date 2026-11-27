<?php
/**
 * Grid402 - Pre-Checagem CLI On-Chain de Prontidao Solana (SOL + ATA SPL USDC)
 *
 * Verifica antes de habilitar GRID402_SOLANA_X402_ENABLED=true se a carteira Solana
 * configurada possui saldo nativo SOL (> 0 lamports) e conta de token associada (ATA)
 * para o mint SPL USDC oficial (EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../public_html/config.php';

/**
 * Retorna lista ordenada de RPCs saudaveis da Solana Mainnet.
 *
 * @return string[]
 */
function grid402_solana_readiness_rpc_urls(?string $overrideRpc = null): array {
    $urls = [];
    if ($overrideRpc !== null && trim($overrideRpc) !== '' && str_starts_with(trim($overrideRpc), 'https://')) {
        $urls[] = trim($overrideRpc);
    }
    $envPrimary = defined('GRID402_SOLANA_RPC_URL') ? trim((string) GRID402_SOLANA_RPC_URL) : '';
    if ($envPrimary !== '' && str_starts_with($envPrimary, 'https://')) {
        $urls[] = $envPrimary;
    }
    $envFallback = function_exists('grid402_env') ? trim((string) grid402_env('GRID402_RPC_SOLANA', '')) : '';
    if ($envFallback !== '' && str_starts_with($envFallback, 'https://')) {
        $urls[] = $envFallback;
    }
    foreach ([
        'https://api.mainnet-beta.solana.com',
        'https://solana.drpc.org',
        'https://rpc.ankr.com/solana',
    ] as $def) {
        $urls[] = $def;
    }
    return array_values(array_unique($urls));
}

function grid402_solana_rpc_post(string $rpcUrl, string $method, array $params, ?callable $rpcTransport = null): array {
    $payload = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params,
    ], JSON_UNESCAPED_SLASHES);

    if ($rpcTransport !== null) {
        return $rpcTransport($rpcUrl, $method, $params, $payload);
    }

    $ch = curl_init($rpcUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'Grid402-Solana-Readiness/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($status !== 200 || !is_string($raw) || $raw === '') {
        return ['ok' => false, 'http_status' => $status, 'error' => $err !== '' ? $err : ('HTTP ' . $status), 'result' => null];
    }
    $decoded = @json_decode($raw, true);
    if (!is_array($decoded) || isset($decoded['error'])) {
        $rpcErr = is_array($decoded['error'] ?? null) ? (string) ($decoded['error']['message'] ?? 'rpc_error') : 'invalid_json';
        return ['ok' => false, 'http_status' => $status, 'error' => $rpcErr, 'result' => null];
    }
    return ['ok' => true, 'http_status' => 200, 'error' => null, 'result' => $decoded['result'] ?? null];
}

/**
 * Executa a checagem de prontidao on-chain Solana (SOL + ATA SPL USDC).
 *
 * @return array<string, mixed>
 */
function grid402_check_solana_readiness(
    ?string $wallet = null,
    ?string $usdcMint = null,
    ?array $rpcUrls = null,
    ?callable $rpcTransport = null
): array {
    $walletAddr = trim((string) ($wallet ?? GRID402_SOLANA_WALLET_ADDRESS));
    $mintAddr = trim((string) ($usdcMint ?? GRID402_SOLANA_USDC_MINT));
    $rpcs = $rpcUrls ?? grid402_solana_readiness_rpc_urls();

    $errors = [];
    $rpcUsed = null;
    $solLamports = null;
    $ataAccounts = [];
    $usdcUiAmount = 0.0;

    foreach ($rpcs as $rpcUrl) {
        $balResp = grid402_solana_rpc_post($rpcUrl, 'getBalance', [$walletAddr, ['commitment' => 'confirmed']], $rpcTransport);
        if (empty($balResp['ok']) || !is_array($balResp['result'] ?? null) || !array_key_exists('value', $balResp['result'])) {
            $errors[] = "{$rpcUrl} (getBalance): " . (string) ($balResp['error'] ?? 'falha');
            continue;
        }

        $ataResp = grid402_solana_rpc_post(
            $rpcUrl,
            'getTokenAccountsByOwner',
            [$walletAddr, ['mint' => $mintAddr], ['encoding' => 'jsonParsed', 'commitment' => 'confirmed']],
            $rpcTransport
        );
        if (empty($ataResp['ok']) || !is_array($ataResp['result'] ?? null) || !is_array($ataResp['result']['value'] ?? null)) {
            $errors[] = "{$rpcUrl} (getTokenAccountsByOwner): " . (string) ($ataResp['error'] ?? 'falha');
            continue;
        }

        $rpcUsed = $rpcUrl;
        $solLamports = (int) ($balResp['result']['value'] ?? 0);
        foreach ($ataResp['result']['value'] as $acct) {
            $pubkey = (string) ($acct['pubkey'] ?? '');
            $tokenAmt = $acct['account']['data']['parsed']['info']['tokenAmount'] ?? [];
            $uiAmt = (float) ($tokenAmt['uiAmount'] ?? 0.0);
            $rawAmt = (string) ($tokenAmt['amount'] ?? '0');
            $usdcUiAmount += $uiAmt;
            $ataAccounts[] = [
                'pubkey' => $pubkey,
                'amount_raw' => $rawAmt,
                'ui_amount' => $uiAmt,
            ];
        }
        break;
    }

    if ($rpcUsed === null) {
        return [
            'ready' => false,
            'wallet' => $walletAddr,
            'usdc_mint' => $mintAddr,
            'has_sol' => false,
            'sol_lamports' => 0,
            'sol_balance' => 0.0,
            'has_usdc_ata' => false,
            'ata_accounts' => [],
            'usdc_balance' => 0.0,
            'rpc_used' => null,
            'rpc_errors' => $errors,
            'missing' => ['rpc_connectivity'],
            'next_step' => 'Configure GRID402_SOLANA_RPC_URL com um RPC Solana Mainnet saudavel e tente novamente.',
        ];
    }

    $hasSol = $solLamports > 0;
    $hasAta = count($ataAccounts) > 0;
    $missing = [];
    if (!$hasSol) {
        $missing[] = 'sol_balance';
    }
    if (!$hasAta) {
        $missing[] = 'usdc_ata';
    }
    $ready = $hasSol && $hasAta;

    $nextStep = $ready
        ? 'Carteira pronta on-chain. Defina GRID402_SOLANA_X402_ENABLED=true no .env de producao.'
        : ('Envie pequena fracao de SOL (ex.: 0.005 SOL) e crie a ATA SPL USDC (' . $mintAddr . ') para ' . $walletAddr . ' antes de ativar GRID402_SOLANA_X402_ENABLED=true.');

    return [
        'ready' => $ready,
        'wallet' => $walletAddr,
        'usdc_mint' => $mintAddr,
        'has_sol' => $hasSol,
        'sol_lamports' => $solLamports,
        'sol_balance' => round($solLamports / 1e9, 9),
        'has_usdc_ata' => $hasAta,
        'ata_accounts' => $ataAccounts,
        'usdc_balance' => round($usdcUiAmount, 6),
        'rpc_used' => $rpcUsed,
        'rpc_errors' => $errors,
        'missing' => $missing,
        'next_step' => $nextStep,
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $args = $_SERVER['argv'] ?? [];
    $asJson = in_array('--json', $args, true);
    $walletArg = null;
    $rpcArg = null;
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--wallet=')) {
            $walletArg = substr($arg, strlen('--wallet='));
        } elseif (str_starts_with($arg, '--rpc-url=')) {
            $rpcArg = substr($arg, strlen('--rpc-url='));
        }
    }

    $rpcs = grid402_solana_readiness_rpc_urls($rpcArg);
    $report = grid402_check_solana_readiness($walletArg, null, $rpcs);

    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "=== Grid402 Solana x402 Readiness Check ===\n";
        echo "Carteira: {$report['wallet']}\n";
        echo "USDC Mint: {$report['usdc_mint']}\n";
        echo "RPC Usado: " . ($report['rpc_used'] ?? 'NENHUM DISPONIVEL') . "\n";
        echo "Saldo SOL: {$report['sol_balance']} SOL ({$report['sol_lamports']} lamports) -> " . ($report['has_sol'] ? 'OK' : 'AUSENTE') . "\n";
        echo "ATA SPL USDC: " . ($report['has_usdc_ata'] ? ('OK (' . count($report['ata_accounts']) . ' conta(s), saldo=' . $report['usdc_balance'] . ' USDC)') : 'AUSENTE') . "\n";
        echo "Status Final: " . ($report['ready'] ? 'READY (exit 0)' : 'NOT READY (exit 1)') . "\n";
        echo "Proximo Passo: {$report['next_step']}\n";
    }
    exit($report['ready'] ? 0 : 1);
}
