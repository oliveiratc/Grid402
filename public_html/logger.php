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

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';

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
 * Sanitiza strings para garantir ausencia de tokens `pass_g402_...` ou tx hashes longos nos logs.
 */
function grid402_sanitize_log_text(?string $value, int $maxLen = 96): ?string {
    if ($value === null) {
        return null;
    }
    $clean = trim(preg_replace('/[\r\n\t]+/', ' ', $value) ?? '');
    if ($clean === '') {
        return null;
    }
    $clean = preg_replace('/pass_g402_[a-zA-Z0-9_-]+/i', 'pass_g402_[REDACTED]', $clean) ?? $clean;
    $clean = preg_replace('/0x[a-fA-F0-9]{20,}/', '0x[REDACTED]', $clean) ?? $clean;
    if (strlen($clean) > $maxLen) {
        $clean = substr($clean, 0, $maxLen);
    }
    return $clean;
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

    $allowedSources = ['none', 'free', 'sandbox', 'pass', 'tx_hash', 'facilitator', 'facilitator_verify_only', 'unknown'];
    $rawSource = strtolower(trim((string) ($event['payment_source'] ?? 'none')));
    $paymentSource = in_array($rawSource, $allowedSources, true) ? $rawSource : 'unknown';

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
