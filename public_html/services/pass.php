<?php
/**
 * Grid402 - Motor de Passes de Sessão Pré-pagos (x402 Session Pass)
 * Permite que agentes M2M façam 1 única transação USDC na Base e consumam
 * centenas de chamadas subsequentes em <15ms sem aguardar blocos adicionais.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

/**
 * Obtém o segredo criptográfico para assinatura HMAC dos tokens de passe
 */
function grid402_pass_secret(): string {
    $privKey = (string) grid402_env('GRID402_ORACLE_PRIVATE_KEY', '');
    if ($privKey !== '') {
        return hash('sha256', 'grid402_pass_v1:' . $privKey);
    }
    return hash('sha256', 'grid402_pass_v1:' . GRID402_CRON_SECRET . ':' . GRID402_WALLET_ADDRESS);
}

/**
 * Gera a assinatura HMAC curta (16 hex) para um passId
 */
function grid402_pass_sign_id(string $passId): string {
    return substr(hash_hmac('sha256', strtolower($passId), grid402_pass_secret()), 0, 16);
}

/**
 * Valida estruturalmente e criptograficamente o formato do token antes de ler o disco
 */
function grid402_pass_verify_token_format(string $token): ?string {
    $token = strtolower(trim($token));
    if (!preg_match('/^pass_g402_([a-z0-9_]{8,24})_([a-f0-9]{16})$/', $token, $m)) {
        return null;
    }
    $passId = $m[1];
    $providedHmac = $m[2];
    $expectedHmac = grid402_pass_sign_id($passId);
    if (!hash_equals($expectedHmac, $providedHmac)) {
        return null;
    }
    return $passId;
}

/**
 * Emite um novo Passe de Sessão Pré-pago (ou retorna o passe já emitido para a mesma txHash)
 */
function grid402_issue_session_pass(string $txHash, int $rawUsdcUnits = 50000, bool $isSandbox = false): array {
    $file = defined('GRID402_FILE_PASSES') ? GRID402_FILE_PASSES : (GRID402_PRIVATE_DIR . '/session_passes.json');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    // Calibração dos Tiers Web3 do Grid402:
    // Starter: >= 2 USDC (2.000.000 raw) -> 4.000 reqs, 30 dias
    // Pro:     >= 5 USDC (5.000.000 raw) -> 10.000 reqs, 30 dias
    // Fleet:   >= 20 USDC (20.000.000 raw) -> 50.000 reqs (+10k bonus), 60 dias
    $validityDays = 30;
    $tier = 'standard';
    if ($isSandbox) {
        $credits = 5;
        $tier = 'sandbox';
        $validityDays = 1 / 24; // 1 hora
    } else {
        if ($rawUsdcUnits >= 20000000) {
            $credits = max(50000, intdiv($rawUsdcUnits, 400));
            $tier = 'fleet';
            $validityDays = 60;
        } elseif ($rawUsdcUnits >= 5000000) {
            $credits = intdiv($rawUsdcUnits, 500);
            $tier = 'pro';
            $validityDays = 30;
        } elseif ($rawUsdcUnits >= 2000000) {
            $credits = intdiv($rawUsdcUnits, 500);
            $tier = 'starter';
            $validityDays = 30;
        } else {
            $credits = max(10, intdiv($rawUsdcUnits, 500));
            $tier = 'standard';
            $validityDays = 30;
        }
    }
    $usdcAmount = $isSandbox ? '0.0000 (Sandbox)' : number_format($rawUsdcUnits / 1e6, 4, '.', '');
    $now = time();
    $expiresAt = $now + (int) round($validityDays * 86400);

    $fp = fopen($file, 'c+');
    if (!$fp) {
        throw new RuntimeException('Não foi possível acessar o armazenamento de passes de sessão.');
    }

    $record = null;
    if (flock($fp, LOCK_EX)) {
        $size = filesize($file);
        $rawContent = ($size > 0) ? (string) fread($fp, $size) : '';
        $store = ($rawContent !== '') ? (@json_decode($rawContent, true) ?: []) : [];

        // Se a mesma txHash (não demo) já gerou um passe, retorna o passe existente
        $cleanTx = strtolower(trim($txHash));
        if (!$isSandbox && $cleanTx !== '') {
            foreach ($store as $existingId => $entry) {
                if (($entry['tx_hash'] ?? '') === $cleanTx) {
                    $record = $entry;
                    break;
                }
            }
        }

        if ($record === null) {
            $prefix = $isSandbox ? 'demo' : 'live';
            $passId = $prefix . bin2hex(random_bytes(6));
            $hmac = grid402_pass_sign_id($passId);
            $token = "pass_g402_{$passId}_{$hmac}";

            $record = [
                'pass_id' => $passId,
                'pass_token' => $token,
                'tier' => $tier,
                'mode' => $isSandbox ? 'sandbox' : 'production',
                'tx_hash' => $cleanTx,
                'usdc_deposited' => $usdcAmount,
                'credits_total' => $credits,
                'credits_remaining' => $credits,
                'issued_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'expires_epoch' => $expiresAt,
                'usage_count' => 0,
            ];

            // Limpeza automática de passes expirados há mais de 7 dias
            $cleaned = [];
            foreach ($store as $k => $v) {
                if ((int)($v['expires_epoch'] ?? 0) > ($now - 604800)) {
                    $cleaned[$k] = $v;
                }
            }
            $cleaned[$passId] = $record;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return [
        'service' => 'pass',
        'status' => 'ACTIVE',
        'tier' => $record['tier'] ?? 'standard',
        'pass_token' => $record['pass_token'],
        'mode' => $record['mode'],
        'credits_total' => $record['credits_total'],
        'credits_remaining' => $record['credits_remaining'],
        'usdc_deposited' => $record['usdc_deposited'],
        'issued_at' => $record['issued_at'],
        'expires_at' => $record['expires_at'],
        'expires_epoch' => $record['expires_epoch'] ?? 0,
        'usage_instructions' => [
            'header' => 'X-Payment: ' . $record['pass_token'],
            'credit_rates' => [
                'standard_0.0005_usdc' => '1 credit (forex, crypto, dns, ssl, asn, cep, indicators, holidays, workdays, notarize, global-markets)',
                'compliance_0.0010_usdc' => '2 credits (sanctions, simples)',
                'corporate_0.0020_usdc' => '4 credits (cnpj, lei, ofac)',
                'score360_0.0030_usdc' => '6 credits (compliance)',
            ],
            'check_balance_url' => GRID402_CANONICAL_DOMAIN . '/api/v1/pass?status=' . $record['pass_token'],
        ],
    ];
}

/**
 * Consulta gratuitamente o status e saldo de um Session Pass existente
 */
function grid402_get_session_pass_status(string $token): array {
    $passId = grid402_pass_verify_token_format($token);
    if ($passId === null) {
        return [
            'valid' => false,
            'code' => 400,
            'error' => 'InvalidPassTokenFormat',
            'message' => 'Formato de token inválido ou assinatura criptográfica HMAC incorreta.',
        ];
    }

    $file = defined('GRID402_FILE_PASSES') ? GRID402_FILE_PASSES : (GRID402_PRIVATE_DIR . '/session_passes.json');
    if (!file_exists($file)) {
        return [
            'valid' => false,
            'code' => 404,
            'error' => 'PassNotFound',
            'message' => 'Passe de sessão não encontrado ou expirado.',
        ];
    }

    $content = @file_get_contents($file);
    $store = $content ? (@json_decode($content, true) ?: []) : [];
    if (!isset($store[$passId])) {
        return [
            'valid' => false,
            'code' => 404,
            'error' => 'PassNotFound',
            'message' => 'Passe de sessão não localizado no registro ativo.',
        ];
    }

    $entry = $store[$passId];
    $now = time();
    $isExpired = $now > (int)($entry['expires_epoch'] ?? 0);
    $remaining = (int)($entry['credits_remaining'] ?? 0);

    return [
        'valid' => true,
        'service' => 'pass',
        'tier' => $entry['tier'] ?? 'standard',
        'status' => ($isExpired ? 'EXPIRED' : ($remaining > 0 ? 'ACTIVE' : 'DEPLETED')),
        'active' => (!$isExpired && $remaining > 0),
        'pass_token' => $entry['pass_token'],
        'mode' => $entry['mode'] ?? 'production',
        'credits_total' => (int)($entry['credits_total'] ?? 0),
        'credits_remaining' => $remaining,
        'usage_count' => (int)($entry['usage_count'] ?? 0),
        'issued_at' => $entry['issued_at'] ?? null,
        'expires_at' => $entry['expires_at'] ?? null,
        'expires_epoch' => (int)($entry['expires_epoch'] ?? 0),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $now),
    ];
}

/**
 * Valida e debita atomicamente créditos de um Session Pass em uma chamada de API (<15ms)
 */
function grid402_consume_session_pass(string $token, int $requiredRawUnits = 500): array {
    $passId = grid402_pass_verify_token_format($token);
    if ($passId === null) {
        return [
            'valid' => false,
            'code' => 403,
            'reason' => 'Token de Session Pass inválido ou assinatura HMAC rejeitada pelo Oráculo.',
        ];
    }

    $creditsNeeded = max(1, intdiv($requiredRawUnits, 500));
    $file = defined('GRID402_FILE_PASSES') ? GRID402_FILE_PASSES : (GRID402_PRIVATE_DIR . '/session_passes.json');
    if (!file_exists($file)) {
        return [
            'valid' => false,
            'code' => 402,
            'reason' => 'Passe de sessão não encontrado. Emita um novo passe em /api/v1/pass.',
        ];
    }

    $fp = fopen($file, 'c+');
    if (!$fp) {
        return [
            'valid' => false,
            'code' => 500,
            'reason' => 'Erro interno ao validar armazenamento de passes.',
        ];
    }

    $result = [
        'valid' => false,
        'code' => 402,
        'reason' => 'Passe não encontrado.',
    ];

    if (flock($fp, LOCK_EX)) {
        $size = filesize($file);
        $rawContent = ($size > 0) ? (string) fread($fp, $size) : '';
        $store = ($rawContent !== '') ? (@json_decode($rawContent, true) ?: []) : [];

        if (!isset($store[$passId])) {
            $result = [
                'valid' => false,
                'code' => 402,
                'reason' => 'Passe de sessão não encontrado ou já expurgado.',
            ];
        } else {
            $entry = $store[$passId];
            $now = time();
            $expiresEpoch = (int)($entry['expires_epoch'] ?? 0);
            $remaining = (int)($entry['credits_remaining'] ?? 0);

            if ($now > $expiresEpoch) {
                $result = [
                    'valid' => false,
                    'code' => 402,
                    'reason' => 'Este Session Pass expirou em ' . ($entry['expires_at'] ?? 'data anterior') . '. Recarregue em /api/v1/pass.',
                ];
            } elseif ($remaining < $creditsNeeded) {
                $result = [
                    'valid' => false,
                    'code' => 402,
                    'reason' => "Saldo insuficiente no Session Pass: restam {$remaining} crédito(s), mas este serviço requer {$creditsNeeded} crédito(s).",
                ];
            } else {
                $entry['credits_remaining'] = $remaining - $creditsNeeded;
                $entry['usage_count'] = (int)($entry['usage_count'] ?? 0) + 1;
                $entry['last_used_at'] = gmdate('Y-m-d\TH:i:s\Z', $now);
                $store[$passId] = $entry;

                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);

                $isSandbox = (($entry['mode'] ?? '') === 'sandbox');
                $result = [
                    'valid' => true,
                    'sandbox' => $isSandbox,
                    'session_pass' => true,
                    'pass_id' => $passId,
                    'credits_debited' => $creditsNeeded,
                    'credits_remaining' => $entry['credits_remaining'],
                    'code' => 200,
                    'reason' => 'Autenticado instantaneamente via x402 Prepaid Session Pass.',
                ];
            }
        }

        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return $result;
}

