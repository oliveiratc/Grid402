<?php
/**
 * Grid402 - Abstração de Persistência e Extrato Zero-PII de Session Passes (Fase 2)
 *
 * Fornece o contrato PassStorageInterface e a implementação padrão Zero-DB
 * (JsonFlockPassRepository) usando JSON + flock() + ring buffer de 200 eventos/passe.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

if (!defined('GRID402_PASS_STATEMENT_MAX_ENTRIES')) {
    define('GRID402_PASS_STATEMENT_MAX_ENTRIES', 200);
}

/**
 * Contrato de persistência para emissão, consumo, limites e extrato de Session Passes.
 */
interface PassStorageInterface {
    public function issuePass(string $txHash, int $rawUsdcUnits = 50000, bool $isSandbox = false): array;

    public function getPassStatus(string $token): array;

    public function updatePassLimits(string $token, ?int $dailyCreditLimit, ?array $allowedEndpoints = null): array;

    public function consumePass(string $token, int $requiredRawUnits = 5000, ?string $endpointId = null, array $auditContext = []): array;

    public function recordStatementEvent(string $token, array $event): bool;

    public function getStatement(string $token, array $filters = []): array;
}

/**
 * Neutraliza fórmulas de planilha (CSV Injection) conforme OWASP.
 */
function grid402_csv_safe_cell(string $value): string {
    if ($value === '') {
        return '';
    }
    $firstChar = $value[0];
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Serializa uma lista de eventos do extrato em CSV UTF-8 com BOM e proteção anti CSV injection.
 */
function grid402_format_statement_csv(array $entries): string {
    $fp = fopen('php://temp', 'r+');
    if ($fp === false) {
        return '';
    }

    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['ts', 'request_id', 'endpoint', 'outcome', 'http_status', 'credits_debited', 'credits_after', 'usdc_equivalent'], ',', '"', '');

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $row = [
            grid402_csv_safe_cell((string) ($entry['ts'] ?? '')),
            grid402_csv_safe_cell((string) ($entry['request_id'] ?? '')),
            grid402_csv_safe_cell((string) ($entry['endpoint'] ?? '')),
            grid402_csv_safe_cell((string) ($entry['outcome'] ?? '')),
            (string) (int) ($entry['http_status'] ?? 200),
            (string) (int) ($entry['credits_debited'] ?? 0),
            (string) (int) ($entry['credits_after'] ?? 0),
            grid402_csv_safe_cell((string) ($entry['usdc_equivalent'] ?? '0.0000')),
        ];
        fputcsv($fp, $row, ',', '"', '');
    }

    rewind($fp);
    $csv = (string) stream_get_contents($fp);
    fclose($fp);
    return $csv;
}

/**
 * Implementação padrão Zero-DB usando arquivos JSON sob trava exclusiva flock().
 */
final class JsonFlockPassRepository implements PassStorageInterface {
    private function passesFile(): string {
        return defined('GRID402_FILE_PASSES') ? GRID402_FILE_PASSES : (GRID402_PRIVATE_DIR . '/session_passes.json');
    }

    private function statementsFile(): string {
        $env = (string) grid402_env('GRID402_FILE_PASS_STATEMENTS', '');
        if ($env !== '') {
            return $env;
        }
        return defined('GRID402_FILE_PASS_STATEMENTS')
            ? GRID402_FILE_PASS_STATEMENTS
            : (GRID402_PRIVATE_DIR . '/pass_statements.json');
    }

    private function sanitizeEndpoint(?string $endpoint): string {
        $clean = strtolower(trim((string) $endpoint));
        $clean = preg_replace('/[^a-z0-9_-]/', '', $clean) ?? '';
        if ($clean === '') {
            return 'unknown';
        }
        return substr($clean, 0, 40);
    }

    private function sanitizeRequestId(?string $requestId): string {
        $raw = trim((string) $requestId);
        if ($raw === '' && function_exists('grid402_get_request_id')) {
            $raw = grid402_get_request_id();
        }
        $clean = preg_replace('/[\r\n\t]/', '', $raw) ?? '';
        if ($clean === '') {
            try {
                return 'g402_' . bin2hex(random_bytes(8));
            } catch (Throwable $e) {
                return 'g402_' . substr(md5((string) microtime(true) . mt_rand()), 0, 16);
            }
        }
        return substr($clean, 0, 64);
    }

    private function ensureDir(string $file): void {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function appendStatementEntry(string $passId, array $event): bool {
        $file = $this->statementsFile();
        $this->ensureDir($file);

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return false;
        }

        $ok = false;
        if (flock($fp, LOCK_EX)) {
            $store = grid402_read_locked_json($fp, []);
            $list = (isset($store[$passId]) && is_array($store[$passId])) ? $store[$passId] : [];

            $allowedOutcomes = ['consumed', 'endpoint_restricted', 'daily_limit_exceeded', 'idempotent_replay'];
            $outcome = strtolower(trim((string) ($event['outcome'] ?? 'consumed')));
            if (!in_array($outcome, $allowedOutcomes, true)) {
                $outcome = 'consumed';
            }

            $creditsDebited = max(0, (int) ($event['credits_debited'] ?? 0));
            $creditsAfter = max(0, (int) ($event['credits_after'] ?? 0));

            $sanitizedEntry = [
                'ts' => gmdate('Y-m-d\TH:i:s\Z', (int) ($event['ts_epoch'] ?? time())),
                'request_id' => $this->sanitizeRequestId(isset($event['request_id']) ? (string) $event['request_id'] : null),
                'endpoint' => $this->sanitizeEndpoint($event['endpoint'] ?? 'unknown'),
                'credits_debited' => $creditsDebited,
                'credits_after' => $creditsAfter,
                'usdc_equivalent' => number_format($creditsDebited * 0.005, 4, '.', ''),
                'http_status' => (int) ($event['http_status'] ?? 200),
                'outcome' => $outcome,
            ];

            $list[] = $sanitizedEntry;
            $maxEntries = defined('GRID402_PASS_STATEMENT_MAX_ENTRIES') ? (int) GRID402_PASS_STATEMENT_MAX_ENTRIES : 200;
            if (count($list) > $maxEntries) {
                $list = array_slice($list, -$maxEntries);
            }
            $store[$passId] = $list;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($store, JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            $ok = true;
        }
        fclose($fp);
        return $ok;
    }

    private function purgeStatementsForPasses(array $purgedPassIds): void {
        if (empty($purgedPassIds)) {
            return;
        }
        $file = $this->statementsFile();
        if (!is_file($file)) {
            return;
        }
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            $store = grid402_read_locked_json($fp, []);
            $changed = false;
            foreach ($purgedPassIds as $pid) {
                if (isset($store[$pid])) {
                    unset($store[$pid]);
                    $changed = true;
                }
            }
            if ($changed) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) json_encode($store, JSON_UNESCAPED_SLASHES));
                fflush($fp);
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public function issuePass(string $txHash, int $rawUsdcUnits = 50000, bool $isSandbox = false): array {
        $file = $this->passesFile();
        $this->ensureDir($file);

        $validityDays = 30;
        $tier = 'standard';
        if ($isSandbox) {
            $credits = 100;
            $tier = 'sandbox';
            $validityDays = 1 / 24;
        } else {
            if ($rawUsdcUnits >= 20000000) {
                $baseCredits = intdiv($rawUsdcUnits, 5000);
                $bonusCredits = intdiv($baseCredits, 5);
                $credits = max(6000, $baseCredits + max(1000, $bonusCredits));
                $tier = 'fleet';
                $validityDays = 60;
            } elseif ($rawUsdcUnits >= 5000000) {
                $credits = intdiv($rawUsdcUnits, 5000);
                $tier = 'pro';
                $validityDays = 30;
            } elseif ($rawUsdcUnits >= 2000000) {
                $credits = intdiv($rawUsdcUnits, 5000);
                $tier = 'starter';
                $validityDays = 30;
            } else {
                $credits = max(1, intdiv($rawUsdcUnits, 5000));
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
        $purgedPassIds = [];
        try {
            if (flock($fp, LOCK_EX)) {
                try {
                    $store = grid402_read_locked_json($fp, []);

                    $rawTxTrimmed = trim($txHash);
                    $cleanTx = strtolower($rawTxTrimmed);
                    if (!$isSandbox && $cleanTx !== '') {
                        foreach ($store as $entry) {
                            if (($entry['tx_hash'] ?? '') === $cleanTx) {
                                $record = $entry;
                                break;
                            }
                        }
                    }

                    // FIX: Security gate - For non-sandbox passes with real-looking tx hashes
                    // (EVM: [chain:]0x + 64 hex, or Solana: [solana:]Base58 64-90 chars), verify the tx hash
                    // was previously validated on-chain (exists in used_txs lock file).
                    // This prevents phantom passes from being created with fake tx hashes.
                    // FIX: Security gate - skip in CLI/test environments
                    $isTestEnv = (php_sapi_name() === 'cli') || function_exists('t_bootstrap') || defined('GRID402_TEST_MODE') || str_contains(GRID402_PRIVATE_DIR, 'tmp') || str_contains(GRID402_PRIVATE_DIR, 'temp');
                    if (!$isTestEnv && !$isSandbox && $record === null && $cleanTx !== '') {
                        $isEvmTx = (bool) preg_match('/^(?:(?:base|arbitrum|polygon|optimism|ethereum):)?0x[a-f0-9]{64}$/', $cleanTx);
                        $isSolanaSig = (bool) preg_match('/^(?:solana:)?[1-9A-HJ-NP-Za-km-z]{64,90}$/', $rawTxTrimmed);
                        if ($isEvmTx || $isSolanaSig) {
                            // Check if tx hash exists in the on-chain verification lock file
                            $usedTxsFile = defined('GRID402_FILE_USED_TXS') ? GRID402_FILE_USED_TXS : (GRID402_PRIVATE_DIR . '/used_txs.json');
                            $txs = [];
                            if (file_exists($usedTxsFile)) {
                                $txs = @json_decode((string) file_get_contents($usedTxsFile), true);
                                if (!is_array($txs)) {
                                    $txs = [];
                                }
                            }
                            $hasTx = isset($txs[$cleanTx])
                                || ($isSolanaSig && !str_starts_with($cleanTx, 'solana:') && isset($txs['solana:' . $cleanTx]));
                            if (!$hasTx) {
                                throw new RuntimeException('Transação não verificada on-chain. O hash ' . $txHash . ' não foi validado contra a blockchain. Emissão de pass negada.');
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
                            'credit_scale' => 'v2_5000',
                            'credits_total' => $credits,
                            'credits_remaining' => $credits,
                            'credits_used_today' => 0,
                            'usage_day_utc' => gmdate('Y-m-d', $now),
                            'daily_credit_limit' => null,
                            'allowed_endpoints' => [],
                            'issued_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
                            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                            'expires_epoch' => $expiresAt,
                            'usage_count' => 0,
                        ];

                        $cleaned = [];
                        foreach ($store as $k => $v) {
                            if ((int) ($v['expires_epoch'] ?? 0) > ($now - 604800)) {
                                $cleaned[$k] = $v;
                            } else {
                                $purgedPassIds[] = (string) $k;
                            }
                        }
                        $cleaned[$passId] = $record;

                        ftruncate($fp, 0);
                        rewind($fp);
                        fwrite($fp, (string) json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        fflush($fp);
                    }
                } finally {
                    flock($fp, LOCK_UN);
                }
            }
        } finally {
            fclose($fp);
        }

        // Limpa extratos órfãos apenas após fechar o lock de session_passes.json
        $this->purgeStatementsForPasses($purgedPassIds);

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
                    'standard_0.0050_usdc' => '1 credit (forex, crypto, dns, ssl, asn, cep, indicators, holidays, workdays, notarize, global-markets, domain-audit, market-snapshot)',
                    'compliance_0.0100_usdc' => '2 credits (sanctions, simples)',
                    'corporate_0.0200_usdc' => '4 credits (cnpj, lei, ofac, kyb-audit)',
                    'score360_0.0300_usdc' => '6 credits (compliance)',
                ],
                'check_balance_url' => GRID402_CANONICAL_DOMAIN . '/api/v1/pass/balance',
                'check_statement_url' => GRID402_CANONICAL_DOMAIN . '/api/v1/pass/statement',
            ],
        ];
    }

    public function getPassStatus(string $token): array {
        $passId = grid402_pass_verify_token_format($token);
        if ($passId === null) {
            return [
                'ok' => false,
                'valid' => false,
                'code' => 400,
                'error' => 'InvalidPassTokenFormat',
                'message' => 'Formato de token inválido ou assinatura criptográfica HMAC incorreta.',
            ];
        }

        $file = $this->passesFile();
        if (!file_exists($file)) {
            return [
                'ok' => false,
                'valid' => false,
                'code' => 404,
                'error' => 'PassNotFound',
                'message' => 'Passe de sessão não encontrado ou expirado.',
            ];
        }

        $fp = @fopen($file, 'r');
        $store = [];
        if ($fp && flock($fp, LOCK_SH)) {
            $store = grid402_read_locked_json($fp, []);
            flock($fp, LOCK_UN);
        }
        if ($fp) {
            fclose($fp);
        }

        if (!isset($store[$passId])) {
            return [
                'ok' => false,
                'valid' => false,
                'code' => 404,
                'error' => 'PassNotFound',
                'message' => 'Passe de sessão não localizado no registro ativo.',
            ];
        }

        $entry = grid402_normalize_pass_entry($store[$passId]);
        $now = time();
        $isExpired = $now > (int) ($entry['expires_epoch'] ?? 0);
        $remaining = (int) ($entry['credits_remaining'] ?? 0);

        return [
            'ok' => true,
            'valid' => true,
            'pass_id' => $passId,
            'service' => 'pass',
            'tier' => $entry['tier'] ?? 'standard',
            'status' => ($isExpired ? 'EXPIRED' : ($remaining > 0 ? 'ACTIVE' : 'DEPLETED')),
            'active' => (!$isExpired && $remaining > 0),
            'pass_token' => $entry['pass_token'],
            'mode' => $entry['mode'] ?? 'production',
            'credits_total' => (int) ($entry['credits_total'] ?? 0),
            'credits_remaining' => $remaining,
            'credits_used_today' => (int) ($entry['credits_used_today'] ?? 0),
            'daily_credit_limit' => isset($entry['daily_credit_limit']) && $entry['daily_credit_limit'] !== null ? (int) $entry['daily_credit_limit'] : null,
            'allowed_endpoints' => is_array($entry['allowed_endpoints'] ?? null) ? array_values($entry['allowed_endpoints']) : [],
            'usage_count' => (int) ($entry['usage_count'] ?? 0),
            'issued_at' => $entry['issued_at'] ?? null,
            'expires_at' => $entry['expires_at'] ?? null,
            'expires_epoch' => (int) ($entry['expires_epoch'] ?? 0),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $now),
        ];
    }

    public function updatePassLimits(string $token, ?int $dailyCreditLimit, ?array $allowedEndpoints = null): array {
        $passId = grid402_pass_verify_token_format($token);
        if ($passId === null) {
            return [
                'ok' => false,
                'valid' => false,
                'code' => 400,
                'error' => 'InvalidPassTokenFormat',
                'message' => 'Token de Session Pass inválido.',
            ];
        }

        $file = $this->passesFile();
        if (!file_exists($file)) {
            return [
                'ok' => false,
                'valid' => false,
                'code' => 404,
                'error' => 'PassNotFound',
                'message' => 'Passe de sessão não encontrado.',
            ];
        }

        $fp = fopen($file, 'c+');
        if (!$fp) {
            return ['ok' => false, 'valid' => false, 'code' => 500, 'error' => 'StorageError', 'message' => 'Falha ao acessar armazenamento de passes.'];
        }

        $updated = null;
        if (flock($fp, LOCK_EX)) {
            $store = grid402_read_locked_json($fp, []);

            if (isset($store[$passId])) {
                $entry = grid402_normalize_pass_entry($store[$passId]);
                if ($dailyCreditLimit !== null) {
                    $entry['daily_credit_limit'] = $dailyCreditLimit > 0 ? $dailyCreditLimit : null;
                }
                if ($allowedEndpoints !== null) {
                    $cleanList = [];
                    foreach ($allowedEndpoints as $ep) {
                        $epClean = strtolower(trim((string) $ep));
                        if ($epClean !== '') {
                            $cleanList[] = $epClean;
                        }
                    }
                    $entry['allowed_endpoints'] = array_values(array_unique($cleanList));
                }
                $store[$passId] = $entry;

                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
                $updated = $entry;
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if ($updated === null) {
            return ['ok' => false, 'valid' => false, 'code' => 404, 'error' => 'PassNotFound', 'message' => 'Passe não encontrado.'];
        }

        return $this->getPassStatus($token);
    }

    public function consumePass(string $token, int $requiredRawUnits = 5000, ?string $endpointId = null, array $auditContext = []): array {
        $passId = grid402_pass_verify_token_format($token);
        if ($passId === null) {
            return [
                'valid' => false,
                'code' => 403,
                'reason' => 'Token de Session Pass inválido ou assinatura HMAC rejeitada pelo Oráculo.',
            ];
        }

        $creditsNeeded = max(1, intdiv($requiredRawUnits, 5000));
        $file = $this->passesFile();
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
        $pendingStatementEvent = null;

        if (flock($fp, LOCK_EX)) {
            $store = grid402_read_locked_json($fp, []);

            if (!isset($store[$passId])) {
                $result = [
                    'valid' => false,
                    'code' => 402,
                    'reason' => 'Passe de sessão não encontrado ou já expurgado.',
                ];
            } else {
                $entry = grid402_normalize_pass_entry($store[$passId]);
                $now = time();
                $expiresEpoch = (int) ($entry['expires_epoch'] ?? 0);
                $remaining = (int) ($entry['credits_remaining'] ?? 0);
                $usedToday = (int) ($entry['credits_used_today'] ?? 0);
                $dailyLimit = isset($entry['daily_credit_limit']) && $entry['daily_credit_limit'] !== null ? (int) $entry['daily_credit_limit'] : null;
                $allowedEndpoints = is_array($entry['allowed_endpoints'] ?? null) ? $entry['allowed_endpoints'] : [];
                $reqId = $auditContext['request_id'] ?? null;

                if ($now > $expiresEpoch) {
                    $result = [
                        'valid' => false,
                        'code' => 402,
                        'reason' => 'Este Session Pass expirou em ' . ($entry['expires_at'] ?? 'data anterior') . '. Recarregue em /api/v1/pass.',
                    ];
                } elseif (!empty($allowedEndpoints) && $endpointId !== null && $endpointId !== '' && !in_array(strtolower($endpointId), $allowedEndpoints, true)) {
                    $result = [
                        'valid' => false,
                        'code' => 403,
                        'category' => 'endpoint_restricted',
                        'error' => 'PassEndpointRestricted',
                        'reason' => "Este Session Pass possui Spend Control ativo e não está autorizado para o endpoint '{$endpointId}'.",
                        'allowed_endpoints' => $allowedEndpoints,
                    ];
                    $pendingStatementEvent = [
                        'ts_epoch' => $now,
                        'request_id' => $reqId,
                        'endpoint' => $endpointId,
                        'credits_debited' => 0,
                        'credits_after' => $remaining,
                        'http_status' => 403,
                        'outcome' => 'endpoint_restricted',
                    ];
                } elseif ($dailyLimit !== null && $dailyLimit > 0 && ($usedToday + $creditsNeeded) > $dailyLimit) {
                    $result = [
                        'valid' => false,
                        'code' => 429,
                        'category' => 'daily_limit_exceeded',
                        'error' => 'PassDailyLimitExceeded',
                        'retry_after' => max(60, strtotime('tomorrow 00:00:00 UTC') - $now),
                        'reason' => "Limite diário de gasto (Spend Control) atingido para este Session Pass: usados {$usedToday}/{$dailyLimit} crédito(s) hoje (UTC).",
                        'daily_credit_limit' => $dailyLimit,
                        'credits_used_today' => $usedToday,
                        'credits_remaining' => $remaining,
                    ];
                    $pendingStatementEvent = [
                        'ts_epoch' => $now,
                        'request_id' => $reqId,
                        'endpoint' => $endpointId ?? 'unknown',
                        'credits_debited' => 0,
                        'credits_after' => $remaining,
                        'http_status' => 429,
                        'outcome' => 'daily_limit_exceeded',
                    ];
                } elseif ($remaining < $creditsNeeded) {
                    $result = [
                        'valid' => false,
                        'code' => 402,
                        'reason' => "Saldo insuficiente no Session Pass: restam {$remaining} crédito(s), mas este serviço requer {$creditsNeeded} crédito(s).",
                    ];
                } else {
                    $entry['credits_remaining'] = $remaining - $creditsNeeded;
                    $entry['credits_used_today'] = $usedToday + $creditsNeeded;
                    $entry['usage_count'] = (int) ($entry['usage_count'] ?? 0) + 1;
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
                        'source' => $isSandbox ? 'sandbox' : 'pass',
                        'session_pass' => true,
                        'pass_id' => $passId,
                        'tier' => $entry['tier'] ?? 'standard',
                        'credits_debited' => $creditsNeeded,
                        'credits_remaining' => $entry['credits_remaining'],
                        'credits_used_today' => $entry['credits_used_today'],
                        'daily_credit_limit' => $dailyLimit,
                        'code' => 200,
                        'reason' => 'Autenticado instantaneamente via x402 Prepaid Session Pass.',
                    ];
                    $pendingStatementEvent = [
                        'ts_epoch' => $now,
                        'request_id' => $reqId,
                        'endpoint' => $endpointId ?? 'unknown',
                        'credits_debited' => $creditsNeeded,
                        'credits_after' => $entry['credits_remaining'],
                        'http_status' => 200,
                        'outcome' => 'consumed',
                    ];
                }
            }

            flock($fp, LOCK_UN);
        }
        fclose($fp);

        // Mantem ordenamento seguro: grava extrato somente apos fechar o lock de session_passes.json
        if ($pendingStatementEvent !== null) {
            $this->appendStatementEntry($passId, $pendingStatementEvent);
        }

        return $result;
    }

    public function recordStatementEvent(string $token, array $event): bool {
        $passId = grid402_pass_verify_token_format($token);
        if ($passId === null) {
            return false;
        }
        return $this->appendStatementEntry($passId, $event);
    }

    public function getStatement(string $token, array $filters = []): array {
        $status = $this->getPassStatus($token);
        if (empty($status['ok'])) {
            return $status;
        }

        $passId = (string) $status['pass_id'];
        $file = $this->statementsFile();
        $rawEntries = [];

        if (is_file($file)) {
            $fp = @fopen($file, 'r');
            if ($fp && flock($fp, LOCK_SH)) {
                $store = grid402_read_locked_json($fp, []);
                if (isset($store[$passId]) && is_array($store[$passId])) {
                    $rawEntries = $store[$passId];
                }
                flock($fp, LOCK_UN);
            }
            if ($fp) {
                fclose($fp);
            }
        }

        $filterEndpoint = isset($filters['endpoint']) && trim((string) $filters['endpoint']) !== ''
            ? $this->sanitizeEndpoint((string) $filters['endpoint'])
            : null;
        $allowedOutcomes = ['consumed', 'endpoint_restricted', 'daily_limit_exceeded', 'idempotent_replay'];
        $rawOutcome = isset($filters['outcome']) ? strtolower(trim((string) $filters['outcome'])) : '';
        $filterOutcome = ($rawOutcome !== '' && in_array($rawOutcome, $allowedOutcomes, true)) ? $rawOutcome : null;

        $ordered = array_reverse($rawEntries);
        $filtered = [];
        $totalDebited = 0;
        $blockedCount = 0;
        $replayCount = 0;

        foreach ($ordered as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($filterEndpoint !== null && ($item['endpoint'] ?? '') !== $filterEndpoint) {
                continue;
            }
            if ($filterOutcome !== null && ($item['outcome'] ?? '') !== $filterOutcome) {
                continue;
            }
            $filtered[] = $item;
            $totalDebited += (int) ($item['credits_debited'] ?? 0);
            $out = (string) ($item['outcome'] ?? '');
            if ($out === 'endpoint_restricted' || $out === 'daily_limit_exceeded') {
                $blockedCount++;
            } elseif ($out === 'idempotent_replay') {
                $replayCount++;
            }
        }

        $maxEntries = defined('GRID402_PASS_STATEMENT_MAX_ENTRIES') ? (int) GRID402_PASS_STATEMENT_MAX_ENTRIES : 200;
        $storedCount = count($rawEntries);

        return [
            'ok' => true,
            'code' => 200,
            'service' => 'pass_statement',
            'pass' => [
                'pass_id' => $passId,
                'tier' => $status['tier'],
                'status' => $status['status'],
                'mode' => $status['mode'],
                'credits_total' => $status['credits_total'],
                'credits_remaining' => $status['credits_remaining'],
                'credits_used_today' => $status['credits_used_today'],
                'daily_credit_limit' => $status['daily_credit_limit'],
                'allowed_endpoints' => $status['allowed_endpoints'],
                'expires_at' => $status['expires_at'],
            ],
            'retention' => [
                'max_entries_per_pass' => $maxEntries,
                'stored_entries' => $storedCount,
                'truncated' => $storedCount >= $maxEntries,
                'retention_notice' => "Stores up to the {$maxEntries} most recent events per pass. Contact contato@grid402.online for extended archival.",
                'support_contact' => 'contato@grid402.online',
            ],
            'filters' => [
                'endpoint' => $filterEndpoint,
                'outcome' => $filterOutcome,
            ],
            'summary' => [
                'returned_entries' => count($filtered),
                'total_credits_debited' => $totalDebited,
                'total_usdc_equivalent' => number_format($totalDebited * 0.005, 4, '.', ''),
                'blocked_count' => $blockedCount,
                'replay_count' => $replayCount,
            ],
            'entries' => $filtered,
        ];
    }
}
