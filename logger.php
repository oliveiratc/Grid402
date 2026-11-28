<?php
/**
 * Grid402 - Modulo de Observabilidade Estruturada JSONL Zero-PII
 *
 * - Gera ou propaga `X-Request-Id` para correlacao ponta a ponta.
 * - Registra eventos estruturados em `requests.jsonl` com trava exclusiva (`flock`).
 * - Rotaciona automaticamente o log ao atingir o limite configurado (padrao: 10 MB).
 * - Sanitiza estritamente tokens (`pass_g402_...`), payloads base64 e enderecos IP.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(403);
    exit('Acesso direto proibido.');
}

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
if (is_file(__DIR__ . '/services/geoip.php')) {
    require_once __DIR__ . '/services/geoip.php';
}

if (!isset($GLOBALS['GRID402_REQ_START'])) {
    $GLOBALS['GRID402_REQ_START'] = microtime(true);
}

/**
 * Retorna o ID unico de correlacao da requisicao atual (`X-Request-Id`).
 */
function grid402_get_request_id(): string {
    static $requestId = null;
    if ($requestId !== null && !defined('GRID402_TEST_ALLOW_RESET_REQ_ID')) {
        return $requestId;
    }

    $incoming = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($incoming !== '' && preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $incoming) === 1) {
        $requestId = $incoming;
    } else {
        try {
            $requestId = 'g402_' . bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $requestId = 'g402_' . substr(md5((string) microtime(true) . mt_rand()), 0, 16);
        }
    }

    return $requestId;
}

/**
 * Emite o cabecalho `X-Request-Id` na resposta HTTP se ainda nao enviado.
 */
function grid402_emit_request_id_header(): string {
    $reqId = grid402_get_request_id();
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        header('X-Request-Id: ' . $reqId);
    }
    return $reqId;
}

/**
 * Calcula o tempo decorrido da requisicao em milissegundos.
 */
function grid402_request_elapsed_ms(): float {
    $start = (float) ($GLOBALS['GRID402_REQ_START'] ?? microtime(true));
    $elapsed = (microtime(true) - $start) * 1000.0;
    return round(max(0.01, $elapsed), 2);
}

/**
 * Retorna o caminho ativo para o arquivo de log estruturado JSONL.
 */
function grid402_get_request_log_path(): string {
    $envPath = (string) grid402_env('GRID402_FILE_REQUEST_LOGS', '');
    if ($envPath !== '') {
        return $envPath;
    }
    if (defined('GRID402_FILE_REQUEST_LOGS')) {
        return GRID402_FILE_REQUEST_LOGS;
    }
    return GRID402_PRIVATE_DIR . '/requests.jsonl';
}

/**
 * Retorna o caminho ativo para o ring buffer dedicado de falhas reais de pagamento.
 */
function grid402_get_payment_failures_path(): string {
    $envPath = (string) grid402_env('GRID402_FILE_PAYMENT_FAILURES', '');
    if ($envPath !== '') {
        return $envPath;
    }
    if (defined('GRID402_FILE_PAYMENT_FAILURES')) {
        return GRID402_FILE_PAYMENT_FAILURES;
    }
    return GRID402_PRIVATE_DIR . '/payment_failures.json';
}

/**
 * Sanitiza strings para garantir ausencia de tokens `pass_g402_...`, tokens MP ou tx hashes longos nos logs.
 */
function grid402_sanitize_log_text(?string $value, int $maxLen = 180): ?string {
    if ($value === null) {
        return null;
    }
    $clean = trim(preg_replace('/[\r\n\t]+/', ' ', $value) ?? '');
    if ($clean === '') {
        return null;
    }
    $clean = preg_replace('/pass_g402_[a-zA-Z0-9_-]+/i', 'pass_g402_[REDACTED]', $clean) ?? $clean;
    $clean = preg_replace('/(?:APP_USR|TEST)-[a-zA-Z0-9_-]{8,}/i', '[MP_TOKEN_REDACTED]', $clean) ?? $clean;
    $clean = preg_replace('/Bearer\s+[a-zA-Z0-9._-]{10,}/i', 'Bearer [REDACTED]', $clean) ?? $clean;
    $clean = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL_REDACTED]', $clean) ?? $clean;
    $clean = preg_replace('/0x[a-fA-F0-9]{20,}/', '0x[REDACTED]', $clean) ?? $clean;
    if (strlen($clean) > $maxLen) {
        $clean = substr($clean, 0, $maxLen);
    }
    return $clean;
}

/**
 * Resume estritamente o formato estrutural de uma prova rejeitada (Zero-PII e Zero-Secret)
 * para diagnostico de vazamento no /admin sem expor tokens, hashes, enderecos ou assinaturas.
 */
function grid402_summarize_rejected_proof_shape(string $headerSource, string $rawProof, ?string $userAgent = null): string {
    $hdrClean = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($headerSource))) ?? '';
    if ($hdrClean === '') {
        $hdrClean = 'none';
    }
    $hdrClean = substr($hdrClean, 0, 32);

    $trimmed = trim($rawProof);
    $len = strlen($trimmed);
    $lower = strtolower($trimmed);

    if ($trimmed === '') {
        $shape = 'empty';
    } elseif (str_starts_with($lower, 'pass_g402_')) {
        $shape = "pass_token(len={$len})";
    } elseif (preg_match('/^0x[a-f0-9]{40}$/', $lower) === 1) {
        $shape = "evm_address_40hex(len={$len})";
    } elseif (preg_match('/^0x[a-f0-9]{64}$/', $lower) === 1) {
        $shape = "evm_txhash_64hex(len={$len})";
    } elseif (str_starts_with($lower, '0x')) {
        $shape = "hex_other(len={$len})";
    } elseif (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $trimmed) === 1) {
        $shape = "jwt_3parts(len={$len})";
    } else {
        $decodedObj = null;
        if (function_exists('grid402_try_decode_x402_blob')) {
            $decodedObj = grid402_try_decode_x402_blob($trimmed);
        } elseif ($len >= 2 && str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $tmp = @json_decode($trimmed, true);
            if (is_array($tmp) && !array_is_list($tmp)) {
                $decodedObj = $tmp;
            }
        }

        if (is_array($decodedObj)) {
            $safeKeys = [];
            foreach (array_slice(array_keys($decodedObj), 0, 6) as $k) {
                $sk = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $k) ?? '';
                if ($sk !== '') {
                    $safeKeys[] = substr($sk, 0, 24);
                }
            }
            $metaParts = [];
            if (isset($decodedObj['x402Version']) && is_scalar($decodedObj['x402Version'])) {
                $metaParts[] = 'v=' . preg_replace('/[^0-9]/', '', (string) $decodedObj['x402Version']);
            }
            $netVal = $decodedObj['accepted']['network'] ?? $decodedObj['network'] ?? null;
            if (is_string($netVal) && $netVal !== '') {
                $safeNet = preg_replace('/[^a-zA-Z0-9:_-]/', '', $netVal) ?? '';
                if ($safeNet !== '') {
                    $metaParts[] = 'net=' . substr($safeNet, 0, 32);
                }
            }
            $keysStr = implode(',', $safeKeys);
            $extraMeta = !empty($metaParts) ? (';' . implode(';', $metaParts)) : '';
            $shape = "x402_json[keys={$keysStr}{$extraMeta}](len={$len})";
        } elseif ($len >= 16 && preg_match('/^[A-Za-z0-9+\/_-]+={0,2}$/', $trimmed) === 1 && base64_decode(strtr($trimmed, '-_', '+/'), true) !== false) {
            $shape = "base64_non_json(len={$len})";
        } elseif (preg_match('/^[1-9A-HJ-NP-Za-km-z]{64,90}$/', $trimmed) === 1) {
            $shape = "solana_b58(len={$len})";
        } elseif (preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $trimmed) === 1) {
            $shape = "word(len={$len})";
        } else {
            $shape = "other(len={$len})";
        }
    }

    $uaRaw = trim((string) ($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $uaToken = 'none';
    if ($uaRaw !== '' && preg_match('/([a-zA-Z0-9._-]{1,24}(?:\/[a-zA-Z0-9._-]{1,12})?)/', $uaRaw, $uaMatch) === 1) {
        $uaToken = $uaMatch[1];
    }

    return grid402_sanitize_log_text("hdr={$hdrClean} | shape={$shape} | ua={$uaToken}", 180) ?? "hdr={$hdrClean}";
}

/**
 * Grava uma linha estruturada JSONL Zero-PII com rotacao automatica em 10 MB.
 *
 * Campos suportados em $event:
 * - service (string)
 * - status (int)
 * - latency_ms (float, opcional)
 * - payment_source (?string: none|sandbox|pass|tx_hash|facilitator|facilitator_verify_only)
 * - idempotent_replay (bool, opcional)
 * - error (?string, opcional)
 * - method (string, opcional)
 * - ip (string, opcional)
 */
function grid402_log_request(array $event): bool {
    $logFile = grid402_get_request_log_path();
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $rawIp = (string) ($event['ip'] ?? (function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')));
    $ipSecret = defined('GRID402_CRON_SECRET') ? GRID402_CRON_SECRET : 'grid402_ip_salt';
    $ipHmac = substr(hash_hmac('sha256', trim($rawIp), $ipSecret), 0, 12);

    $allowedSources = [
        'none',
        'free',
        'sandbox',
        'pass',
        'promo_pass',
        'tx_hash',
        'facilitator',
        'facilitator_verify_only',
        'rapidapi',
        'mercadopago',
        'web3_topup',
        'solana_spl',
        'mcp',
        'mcp_sandbox',
        'unknown',
    ];
    $rawSource = strtolower(trim((string) ($event['payment_source'] ?? 'none')));
    $isDynamicChainSource = preg_match('/^(?:evm|multichain)_[a-z0-9_-]{1,32}$/', $rawSource) === 1;
    $paymentSource = (in_array($rawSource, $allowedSources, true) || $isDynamicChainSource) ? $rawSource : 'unknown';

    $entry = [
        'ts' => gmdate('Y-m-d\TH:i:s\Z'),
        'request_id' => (string) ($event['request_id'] ?? grid402_get_request_id()),
        'method' => strtoupper((string) ($event['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'))),
        'service' => strtolower(trim((string) ($event['service'] ?? 'unknown'))),
        'status' => (int) ($event['status'] ?? 200),
        'latency_ms' => isset($event['latency_ms']) ? round((float) $event['latency_ms'], 2) : grid402_request_elapsed_ms(),
        'payment_source' => $paymentSource,
        'ip_hmac' => $ipHmac,
        'idempotent_replay' => !empty($event['idempotent_replay']),
        'error' => grid402_sanitize_log_text(isset($event['error']) ? (string) $event['error'] : null),
    ];
    if (isset($event['price_usdc']) && is_numeric($event['price_usdc'])) {
        $entry['price_usdc'] = round((float) $event['price_usdc'], 6);
    }
    $logCountry = null;
    if (function_exists('grid402_normalize_country_code')) {
        $logCountry = grid402_normalize_country_code((string) ($event['country_code'] ?? $event['country'] ?? ''));
        if ($logCountry === null && function_exists('grid402_resolve_client_country')) {
            $logCountry = grid402_resolve_client_country($rawIp, false);
        }
    }
    if ($logCountry !== null) {
        $entry['country_code'] = $logCountry;
    }
    if (!empty($event['proof_diag']) && is_string($event['proof_diag'])) {
        $sanitizedDiag = grid402_sanitize_log_text($event['proof_diag'], 180);
        if ($sanitizedDiag !== null) {
            $entry['proof_diag'] = $sanitizedDiag;
        }
    }

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    if (!is_string($line)) {
        return false;
    }

    $maxBytesEnv = (int) grid402_env('GRID402_LOG_MAX_BYTES', 0);
    $maxBytes = $maxBytesEnv > 0 ? $maxBytesEnv : (10 * 1024 * 1024);

    // Arquivo de trava dedicado garante portabilidade de rename() entre Linux e Windows sob flock
    $lockFile = $logFile . '.lock';
    $lockFp = @fopen($lockFile, 'c+');
    if (!$lockFp) {
        return @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    $ok = false;
    if (flock($lockFp, LOCK_EX)) {
        clearstatcache(true, $logFile);
        if (is_file($logFile)) {
            $currentSize = (int) @filesize($logFile);
            if ($currentSize >= $maxBytes) {
                $rotated = $logFile . '.1';
                @unlink($rotated);
                @rename($logFile, $rotated);
            }
        }
        $ok = (@file_put_contents($logFile, $line, FILE_APPEND) !== false);
        flock($lockFp, LOCK_UN);
    }
    fclose($lockFp);

    return $ok;
}

/**
 * Avalia se um evento representa falha real de pagamento (ignorando 402 vazios, probes gratuitos e 429 sandbox).
 */
function grid402_is_noise_payment_failure(int $status, string $svc, string $src, string $err, string $failureCategory = ''): bool {
    $allowedFailureStatuses = [400, 402, 403, 429, 500, 502, 503, 504];
    if (!in_array($status, $allowedFailureStatuses, true)) {
        return true;
    }
    $errTrim = trim($err);
    if ($errTrim === '' || $errTrim === 'PaymentRequired') {
        return true;
    }
    $svcLow = strtolower(trim($svc));
    $srcLow = strtolower(trim($src));
    $failCatLow = strtolower(trim($failureCategory));
    if ($srcLow === 'free' && in_array($svcLow, ['pass-balance', 'pass-statement', 'pass-limits', 'verify', 'summary'], true)) {
        return true;
    }
    if ($svcLow === 'pass-emitir' && $status === 400 && $errTrim === 'InvalidTxHash') {
        return true;
    }
    if ($status === 429 && (
        $failCatLow === 'sandbox_rate_limited'
        || (in_array($srcLow, ['sandbox', 'mcp_sandbox', 'unknown', ''], true) && (str_starts_with($errTrim, 'SandboxRateLimitExceeded') || str_starts_with($errTrim, 'sandbox_rate_limited')))
    )) {
        return true;
    }
    return false;
}

/**
 * Identifica padrões óbvios de provas sintéticas geradas por suítes/scanners de conformidade.
 */
function grid402_is_synthetic_probe_failure(string $error, string $proofDiag = ''): bool {
    $haystack = strtolower($error . ' ' . $proofDiag);
    if (preg_match('/0x(?:1{16,}|a{16,}|0{16,}|f{16,}|deadbeef)/i', $haystack) === 1) {
        return true;
    }
    if (str_contains($haystack, 'compliance_probe') || str_contains($haystack, 'synthetic_probe')) {
        return true;
    }
    return false;
}

/**
 * Classifica uma lista de falhas de pagamento separando probes sintéticos de conformidade (`compliance_probe`)
 * de falhas isoladas de pagadores reais (`payer_failure`), preservando a ordem original do array.
 *
 * @param array<int, array<string, mixed>> $records
 * @return array<int, array<string, mixed>>
 */
function grid402_classify_payment_failures(array $records, int $burstWindowSec = 15, int $burstThreshold = 3): array {
    if (empty($records)) {
        return [];
    }
    $burstMarks = [];
    $byIp = [];

    foreach ($records as $idx => $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $existingOrigin = strtolower(trim((string) ($rec['failure_origin'] ?? '')));
        if ($existingOrigin === 'compliance_probe' || grid402_is_synthetic_probe_failure((string) ($rec['error'] ?? ''), (string) ($rec['proof_diag'] ?? ''))) {
            $burstMarks[$idx] = 'compliance_probe';
        }
        $ipHmac = trim((string) ($rec['ip_hmac'] ?? ''));
        $tsEpoch = @strtotime((string) ($rec['ts'] ?? ''));
        if ($ipHmac !== '' && $tsEpoch !== false) {
            $byIp[$ipHmac][] = ['idx' => $idx, 'ts' => (int) $tsEpoch];
        }
    }

    foreach ($byIp as $entries) {
        if (count($entries) < $burstThreshold) {
            continue;
        }
        usort($entries, static fn(array $a, array $b): int => $a['ts'] <=> $b['ts']);
        $n = count($entries);
        $left = 0;
        for ($right = 0; $right < $n; $right++) {
            while (($entries[$right]['ts'] - $entries[$left]['ts']) > $burstWindowSec) {
                $left++;
            }
            if (($right - $left + 1) >= $burstThreshold) {
                for ($k = $left; $k <= $right; $k++) {
                    $burstMarks[$entries[$k]['idx']] = 'compliance_probe';
                }
            }
        }
    }

    foreach ($records as $idx => $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $records[$idx]['failure_origin'] = $burstMarks[$idx] ?? 'payer_failure';
    }

    return $records;
}

/**
 * Consolida totais por origem de falha (`payer_failure` vs `compliance_probe`).
 *
 * @param array<int, array<string, mixed>> $records
 * @return array{total: int, payer_failure: int, compliance_probe: int}
 */
function grid402_summarize_payment_failures(array $records): array {
    $payer = 0;
    $probe = 0;
    foreach ($records as $rec) {
        if (!is_array($rec)) {
            continue;
        }
        if (($rec['failure_origin'] ?? 'payer_failure') === 'compliance_probe') {
            $probe++;
        } else {
            $payer++;
        }
    }
    return [
        'total' => $payer + $probe,
        'payer_failure' => $payer,
        'compliance_probe' => $probe,
    ];
}

/**
 * Grava falha real de pagamento em ring buffer dedicado `payment_failures.json` (ate 500 eventos Zero-PII) sob flock(LOCK_EX).
 */
function grid402_record_payment_failure(array $event): bool {
    $status = (int) ($event['status'] ?? 402);
    $svc = strtolower(trim((string) ($event['service'] ?? 'unknown')));
    $src = strtolower(trim((string) ($event['payment_source'] ?? 'unknown')));
    $errRaw = (string) ($event['error'] ?? '');
    $failCatRaw = (string) ($event['failure_category'] ?? '');

    if (grid402_is_noise_payment_failure($status, $svc, $src, $errRaw, $failCatRaw)) {
        return false;
    }

    $rawIp = (string) ($event['ip'] ?? (function_exists('grid402_get_trusted_client_ip') ? grid402_get_trusted_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')));
    $ipSecret = defined('GRID402_CRON_SECRET') ? GRID402_CRON_SECRET : 'grid402_ip_salt';
    $ipHmac = (string) ($event['ip_hmac'] ?? substr(hash_hmac('sha256', trim($rawIp), $ipSecret), 0, 12));

    $sanitizedError = grid402_sanitize_log_text($errRaw, 200) ?? '';
    if ($sanitizedError === '') {
        return false;
    }
    $proofDiagSanitized = grid402_sanitize_log_text(isset($event['proof_diag']) ? (string) $event['proof_diag'] : null, 180) ?? '';
    $explicitOrigin = strtolower(trim((string) ($event['failure_origin'] ?? '')));
    $initialOrigin = ($explicitOrigin === 'compliance_probe' || grid402_is_synthetic_probe_failure($sanitizedError, $proofDiagSanitized))
        ? 'compliance_probe'
        : 'payer_failure';

    $record = [
        'ts' => (string) ($event['ts'] ?? gmdate('Y-m-d\TH:i:s\Z')),
        'request_id' => (string) ($event['request_id'] ?? grid402_get_request_id()),
        'service' => $svc !== '' ? $svc : 'unknown',
        'status' => $status,
        'payment_source' => $src !== '' ? $src : 'unknown',
        'failure_category' => strtolower(trim((string) ($event['failure_category'] ?? 'unknown'))),
        'failure_origin' => $initialOrigin,
        'error' => $sanitizedError,
        'proof_diag' => $proofDiagSanitized,
        'ip_hmac' => $ipHmac,
    ];

    $path = grid402_get_payment_failures_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return false;
    }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        $items = grid402_read_locked_json($fp, []);
        if (!is_array($items)) {
            $items = [];
        }
        $items[] = $record;
        if (count($items) > 500) {
            $items = array_values(array_slice($items, -500));
        }
        $tailStart = max(0, count($items) - 25);
        $tailSlice = array_slice($items, $tailStart);
        $classifiedTail = grid402_classify_payment_failures($tailSlice);
        array_splice($items, $tailStart, count($tailSlice), $classifiedTail);

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, (string) json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = ($written !== false);
    }
    fclose($fp);

    return $ok;
}

/**
 * Le os registros recentes de falhas reais de pagamento priorizando o ring buffer `payment_failures.json`
 * (imune a rajadas 402 > 256 KB) com fallback retrocompativel para `requests.jsonl` e `.1`.
 *
 * @return array<int, array<string, mixed>>
 */
function grid402_read_recent_payment_failures(int $limit = 25): array {
    $limit = max(1, min(100, $limit));
    $failures = [];
    $seenIds = [];

    // 1. Fonte primaria dedicada: payment_failures.json (ate 500 falhas reais preservadas)
    $dedicatedPath = grid402_get_payment_failures_path();
    if (is_file($dedicatedPath) && is_readable($dedicatedPath)) {
        $fpDed = @fopen($dedicatedPath, 'rb');
        if ($fpDed) {
            $rawDed = '';
            if (flock($fpDed, LOCK_SH)) {
                $rawDed = (string) stream_get_contents($fpDed);
                flock($fpDed, LOCK_UN);
            }
            fclose($fpDed);
            $items = $rawDed !== '' ? @json_decode($rawDed, true) : null;
            if (is_array($items)) {
                for ($i = count($items) - 1; $i >= 0; $i--) {
                    $entry = $items[$i];
                    if (!is_array($entry)) {
                        continue;
                    }
                    $status = (int) ($entry['status'] ?? 0);
                    $svc = (string) ($entry['service'] ?? '');
                    $src = (string) ($entry['payment_source'] ?? 'unknown');
                    $err = (string) ($entry['error'] ?? '');
                    $failCat = (string) ($entry['failure_category'] ?? '');
                    if (grid402_is_noise_payment_failure($status, $svc, $src, $err, $failCat)) {
                        continue;
                    }
                    $reqId = (string) ($entry['request_id'] ?? '');
                    if ($reqId !== '') {
                        $seenIds["{$reqId}|" . strtolower(trim($svc)) . "|{$status}"] = true;
                    }
                    $failures[] = [
                        'ts' => (string) ($entry['ts'] ?? ''),
                        'request_id' => $reqId,
                        'service' => $svc,
                        'status' => $status,
                        'payment_source' => $src !== '' ? $src : 'unknown',
                        'failure_category' => (string) ($entry['failure_category'] ?? ''),
                        'failure_origin' => (string) ($entry['failure_origin'] ?? ''),
                        'error' => grid402_sanitize_log_text($err, 200) ?? '',
                        'proof_diag' => grid402_sanitize_log_text(isset($entry['proof_diag']) ? (string) $entry['proof_diag'] : null, 180) ?? '',
                        'ip_hmac' => (string) ($entry['ip_hmac'] ?? ''),
                    ];
                    if (count($failures) >= $limit) {
                        return grid402_classify_payment_failures($failures);
                    }
                }
            }
        }
    }

    // 2. Fallback complementar: tail de requests.jsonl e rotacionado .1
    $logFile = grid402_get_request_log_path();
    $candidateFiles = [$logFile, $logFile . '.1'];

    $readTailLines = static function (string $filePath): array {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }
        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            return [];
        }
        $rawTail = '';
        $truncatedHead = false;
        if (flock($fp, LOCK_SH)) {
            $stat = fstat($fp);
            $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
            $tailBytes = 262144; // 256 KB
            if ($size > $tailBytes) {
                fseek($fp, -$tailBytes, SEEK_END);
                $truncatedHead = true;
            }
            $rawTail = (string) stream_get_contents($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if ($rawTail === '') {
            return [];
        }
        if ($truncatedHead) {
            $nlPos = strpos($rawTail, "\n");
            $rawTail = ($nlPos !== false) ? substr($rawTail, $nlPos + 1) : '';
        }
        return preg_split('/\r?\n/', trim($rawTail)) ?: [];
    };

    foreach ($candidateFiles as $filePath) {
        if (count($failures) >= $limit) {
            break;
        }
        $lines = $readTailLines($filePath);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $ln = trim((string) $lines[$i]);
            if ($ln === '') {
                continue;
            }
            $entry = @json_decode($ln, true);
            if (!is_array($entry)) {
                continue;
            }
            $reqId = (string) ($entry['request_id'] ?? '');
            $status = (int) ($entry['status'] ?? 0);
            $err = trim((string) ($entry['error'] ?? ''));
            $svc = strtolower(trim((string) ($entry['service'] ?? '')));
            $src = strtolower(trim((string) ($entry['payment_source'] ?? 'unknown')));
            $failCat = strtolower(trim((string) ($entry['failure_category'] ?? '')));
            if ($reqId !== '' && isset($seenIds["{$reqId}|{$svc}|{$status}"])) {
                continue;
            }
            if (grid402_is_noise_payment_failure($status, $svc, $src, $err, $failCat)) {
                continue;
            }
            $failures[] = [
                'ts' => (string) ($entry['ts'] ?? ''),
                'request_id' => $reqId,
                'service' => (string) ($entry['service'] ?? ''),
                'status' => $status,
                'payment_source' => (string) ($entry['payment_source'] ?? 'unknown'),
                'error' => grid402_sanitize_log_text($err, 200) ?? '',
                'proof_diag' => grid402_sanitize_log_text(isset($entry['proof_diag']) ? (string) $entry['proof_diag'] : null, 180) ?? '',
                'ip_hmac' => (string) ($entry['ip_hmac'] ?? ''),
            ];
            if (count($failures) >= $limit) {
                break;
            }
        }
    }

    return grid402_classify_payment_failures($failures);
}
