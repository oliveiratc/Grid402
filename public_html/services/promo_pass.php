<?php
/**
 * Grid402 - Motor de Cupons Promocionais de Session Pass (Zero-DB & Zero-PII)
 *
 * Permite resgate em 1 clique de Session Passes promocionais (ex.: Cyber Monday mcptry: MCPTRY50)
 * com travas anti-abuso atômicas (flock):
 * - 1 resgate por IP (armazenado exclusivamente como HMAC-SHA256, nunca em texto claro);
 * - teto global de resgates por campanha;
 * - janela de validade da campanha;
 * - daily_credit_limit nativo via Spend Controls;
 * - validade própria do passe promocional (ex.: 30 dias) e marcação de depósito 0.0000 (Promo).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/pass.php';
require_once __DIR__ . '/pass_repository.php';

/**
 * Caminho do arquivo de controle de resgates promocionais (Zero-DB).
 */
function grid402_promo_claims_file(): string {
    $env = trim((string) grid402_env('GRID402_FILE_PROMO_CLAIMS', ''));
    if ($env !== '') {
        return $env;
    }
    return GRID402_PRIVATE_DIR . '/promo_claims.json';
}

/**
 * Segredo HMAC para anonimizar IP nos resgates promocionais (Zero-PII).
 */
function grid402_promo_hmac_secret(): string {
    $privKey = trim((string) grid402_env('GRID402_ORACLE_PRIVATE_KEY', ''));
    if ($privKey !== '') {
        return hash_hmac('sha256', 'grid402-promo-v1:' . $privKey, 'grid402-promo-v1');
    }
    $cron = defined('GRID402_CRON_SECRET') ? (string) GRID402_CRON_SECRET : 'grid402_local';
    $wallet = defined('GRID402_WALLET_ADDRESS') ? (string) GRID402_WALLET_ADDRESS : 'grid402_wallet';
    return hash_hmac('sha256', 'grid402-promo-v1:' . $cron . ':' . $wallet, 'grid402-promo-v1');
}

/**
 * Retorna HMAC irreversível do IP do cliente para deduplicação Zero-PII por campanha.
 */
function grid402_promo_ip_hmac(string $clientIp, string $code): string {
    $normIp = strtolower(trim($clientIp));
    if ($normIp === '') {
        $normIp = 'unknown';
    }
    return hash_hmac('sha256', strtoupper(trim($code)) . '|' . $normIp, grid402_promo_hmac_secret());
}

/**
 * Resolve o IP do cliente delegando exclusivamente para grid402_get_trusted_client_ip()
 * (imune a spoofing de CF-Connecting-IP quando fora da Cloudflare).
 */
function grid402_promo_resolve_client_ip(): string {
    if (function_exists('grid402_get_trusted_client_ip')) {
        return grid402_get_trusted_client_ip();
    }
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false) {
        return $remote;
    }
    return '127.0.0.1';
}

/**
 * Catálogo de campanhas promocionais ativas/configuradas.
 */
function grid402_promo_campaigns(): array {
    $mcptryMax = (int) grid402_env('GRID402_PROMO_MCPTRY50_MAX_CLAIMS', '250');
    if ($mcptryMax < 1) {
        $mcptryMax = 250;
    }

    return [
        'MCPTRY50' => [
            'code' => 'MCPTRY50',
            'storage_key' => 'MCPTRY50_v2',
            'title' => 'Cyber Monday x402 — mcptry Exclusive',
            'partner' => 'mcptry',
            'description' => '50 free credits (up to 50 API/MCP calls across all 27 tools) via instant prepaid Session Pass. No wallet, card, or signup required.',
            'credits' => 50,
            'validity_days' => 30,
            'daily_credit_limit' => 25,
            'max_claims' => $mcptryMax,
            // Ativo desde 23/nov (para validação editorial do mcptry) até 07/dez 23:59 PST (08/dez 07:59 UTC)
            'starts_at' => '2026-11-23T00:00:00Z',
            'ends_at' => '2026-12-08T08:00:00Z',
            'enabled' => true,
        ],
    ];
}

/**
 * Normaliza e busca uma campanha pelo código.
 */
function grid402_promo_find_campaign(string $rawCode): ?array {
    $code = strtoupper(trim($rawCode));
    if ($code === '' || strlen($code) > 40 || preg_match('/^[A-Z0-9_-]+$/', $code) !== 1) {
        return null;
    }
    $campaigns = grid402_promo_campaigns();
    return $campaigns[$code] ?? null;
}

/**
 * Chave de bucket persistida em promo_claims.json (permite reset limpo via storage_key).
 */
function grid402_promo_bucket_key(array $campaign): string {
    $sk = trim((string) ($campaign['storage_key'] ?? ''));
    return $sk !== '' ? $sk : (string) $campaign['code'];
}

/**
 * Consulta o status público de uma campanha promocional (sem PII).
 */
function grid402_promo_get_status(string $rawCode, ?int $nowEpoch = null): array {
    $campaign = grid402_promo_find_campaign($rawCode);
    if ($campaign === null) {
        return [
            'ok' => false,
            'code' => 404,
            'error' => 'PromoNotFound',
            'message' => 'Promo code not found or invalid.',
        ];
    }

    $now = $nowEpoch ?? time();
    $startsEpoch = strtotime((string) $campaign['starts_at']) ?: 0;
    $endsEpoch = strtotime((string) $campaign['ends_at']) ?: 0;
    $maxClaims = (int) $campaign['max_claims'];
    $bucketKey = grid402_promo_bucket_key($campaign);

    $totalClaims = 0;
    $file = grid402_promo_claims_file();
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = is_string($raw) ? @json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded[$bucketKey]['total_claims'])) {
            $totalClaims = (int) $decoded[$bucketKey]['total_claims'];
        }
    }

    $remaining = max(0, $maxClaims - $totalClaims);
    $isStarted = ($now >= $startsEpoch);
    $isExpired = ($endsEpoch > 0 && $now > $endsEpoch);
    $isExhausted = ($remaining <= 0);
    $isActive = !empty($campaign['enabled']) && $isStarted && !$isExpired && !$isExhausted;

    return [
        'ok' => true,
        'code' => 200,
        'campaign' => [
            'code' => $campaign['code'],
            'title' => $campaign['title'],
            'partner' => $campaign['partner'],
            'description' => $campaign['description'],
            'credits' => (int) $campaign['credits'],
            'validity_days' => (int) $campaign['validity_days'],
            'daily_credit_limit' => (int) $campaign['daily_credit_limit'],
            'max_claims' => $maxClaims,
            'claimed_count' => $totalClaims,
            'remaining_claims' => $remaining,
            'starts_at' => $campaign['starts_at'],
            'ends_at' => $campaign['ends_at'],
            'active' => $isActive,
            'expired' => $isExpired,
            'exhausted' => $isExhausted,
        ],
    ];
}

/**
 * Ajusta metadados promocionais (validade 30d, tier promo, daily_credit_limit, locked_limits=true
 * e usdc_deposited=0.0000) em um único flock de session_passes.json (sem locks aninhados).
 */
function grid402_promo_finalize_pass_record(string $passToken, array $campaign, int $nowEpoch): array {
    $parts = explode('_', $passToken);
    $passId = $parts[2] ?? '';
    $validityDays = max(1, (int) ($campaign['validity_days'] ?? 30));
    $dailyLimit = max(0, (int) ($campaign['daily_credit_limit'] ?? 0));
    $expiresEpoch = $nowEpoch + ($validityDays * 86400);
    $expiresIso = gmdate('Y-m-d\TH:i:s\Z', $expiresEpoch);
    $tierLabel = 'promo_' . strtolower((string) $campaign['code']);
    $usdcLabel = '0.0000 (Promo ' . $campaign['code'] . ')';

    $passesFile = defined('GRID402_FILE_PASSES') ? GRID402_FILE_PASSES : (GRID402_PRIVATE_DIR . '/session_passes.json');
    $fp = @fopen($passesFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        try {
            $store = grid402_read_locked_json($fp, []);
            if ($passId !== '' && isset($store[$passId]) && is_array($store[$passId])) {
                $store[$passId]['tier'] = $tierLabel;
                $store[$passId]['usdc_deposited'] = $usdcLabel;
                $store[$passId]['expires_at'] = $expiresIso;
                $store[$passId]['expires_epoch'] = $expiresEpoch;
                $store[$passId]['promo_code'] = (string) $campaign['code'];
                $store[$passId]['promo_partner'] = (string) ($campaign['partner'] ?? '');
                $store[$passId]['locked_limits'] = true;
                if ($dailyLimit > 0) {
                    $store[$passId]['daily_credit_limit'] = $dailyLimit;
                }
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    } elseif ($fp) {
        fclose($fp);
    }

    return [
        'tier' => $tierLabel,
        'expires_at' => $expiresIso,
        'expires_epoch' => $expiresEpoch,
        'validity_days' => $validityDays,
    ];
}

/**
 * Reverte uma reserva pendente em promo_claims.json caso a emissão do Session Pass falhe.
 */
function grid402_promo_rollback_reservation(string $file, string $bucketKey, string $ipHmac, string $pendingMarker): void {
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }
    try {
        if (flock($fp, LOCK_EX)) {
            try {
                $store = grid402_read_locked_json($fp, []);
                if (isset($store[$bucketKey]['claims_by_ip_hmac'][$ipHmac])
                    && ($store[$bucketKey]['claims_by_ip_hmac'][$ipHmac]['pass_id'] ?? '') === $pendingMarker
                ) {
                    unset($store[$bucketKey]['claims_by_ip_hmac'][$ipHmac]);
                    $curTotal = (int) ($store[$bucketKey]['total_claims'] ?? 0);
                    $store[$bucketKey]['total_claims'] = max(0, $curTotal - 1);
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    fflush($fp);
                }
            } finally {
                flock($fp, LOCK_UN);
            }
        }
    } finally {
        fclose($fp);
    }
}

/**
 * Executa o resgate atômico em duas fases sem locks aninhados (1 por IP HMAC, até max_claims).
 */
function grid402_promo_claim_pass(string $rawCode, ?string $clientIp = null, ?int $nowEpoch = null): array {
    $campaign = grid402_promo_find_campaign($rawCode);
    if ($campaign === null) {
        return [
            'ok' => false,
            'code' => 404,
            'error' => 'PromoNotFound',
            'message' => 'Promo code not found or invalid.',
        ];
    }

    if (empty($campaign['enabled'])) {
        return [
            'ok' => false,
            'code' => 410,
            'error' => 'PromoDisabled',
            'message' => 'This promotional campaign is no longer active.',
        ];
    }

    $now = $nowEpoch ?? time();
    $startsEpoch = strtotime((string) $campaign['starts_at']) ?: 0;
    $endsEpoch = strtotime((string) $campaign['ends_at']) ?: 0;

    if ($now < $startsEpoch) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'PromoNotStarted',
            'message' => 'This promotional campaign has not started yet.',
            'starts_at' => $campaign['starts_at'],
        ];
    }

    if ($endsEpoch > 0 && $now > $endsEpoch) {
        return [
            'ok' => false,
            'code' => 410,
            'error' => 'PromoExpired',
            'message' => 'This promotional campaign has expired. You can still top up a Starter Pass ($2 / 400 credits) or Pro Pass ($5 / 1,200 credits) at https://grid402.online/pass.',
            'ends_at' => $campaign['ends_at'],
        ];
    }

    $ip = $clientIp !== null && trim($clientIp) !== '' ? trim($clientIp) : grid402_promo_resolve_client_ip();
    $code = (string) $campaign['code'];
    $bucketKey = grid402_promo_bucket_key($campaign);
    $ipHmac = grid402_promo_ip_hmac($ip, $bucketKey);
    $maxClaims = (int) $campaign['max_claims'];
    $credits = (int) $campaign['credits'];
    $dailyLimit = (int) $campaign['daily_credit_limit'];

    $file = grid402_promo_claims_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return [
            'ok' => false,
            'code' => 500,
            'error' => 'StorageUnavailable',
            'message' => 'Unable to access promo claims storage.',
        ];
    }

    // Fase 1 (lock exclusivo apenas em promo_claims.json): valida IP/cota e reserva vaga atomicamente
    $reservation = null;
    try {
        if (!flock($fp, LOCK_EX)) {
            return [
                'ok' => false,
                'code' => 503,
                'error' => 'LockUnavailable',
                'message' => 'Promo claim service busy, please retry.',
            ];
        }

        try {
            $store = grid402_read_locked_json($fp, []);
            $bucket = (isset($store[$bucketKey]) && is_array($store[$bucketKey])) ? $store[$bucketKey] : [
                'total_claims' => 0,
                'claims_by_ip_hmac' => [],
            ];

            $totalClaims = (int) ($bucket['total_claims'] ?? 0);
            $byIp = (isset($bucket['claims_by_ip_hmac']) && is_array($bucket['claims_by_ip_hmac']))
                ? $bucket['claims_by_ip_hmac']
                : [];

            if (isset($byIp[$ipHmac])) {
                return [
                    'ok' => false,
                    'code' => 409,
                    'error' => 'PromoAlreadyClaimed',
                    'message' => 'This promo code has already been claimed from your network (limit: 1 free pass per IP). Need more credits? Top up a Starter ($2 = 400 cr) or Pro ($5 = 1,200 cr) Pass anytime.',
                    'remaining_claims' => max(0, $maxClaims - $totalClaims),
                ];
            }

            if ($totalClaims >= $maxClaims) {
                return [
                    'ok' => false,
                    'code' => 410,
                    'error' => 'PromoExhausted',
                    'message' => 'All ' . $maxClaims . ' promotional passes for ' . $code . ' have been claimed. You can still test for free with X-Payment: demo (3 req/day) or grab a Starter/Pro Pass.',
                    'remaining_claims' => 0,
                ];
            }

            $nextIndex = $totalClaims + 1;
            $nonce = bin2hex(random_bytes(8));
            $pendingMarker = 'pending:' . $nonce;
            $syntheticTx = 'promo:' . strtolower($code) . ':' . $nextIndex . ':' . $nonce;

            $byIp[$ipHmac] = [
                'pass_id' => $pendingMarker,
                'claimed_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
            ];
            $bucket['total_claims'] = $nextIndex;
            $bucket['claims_by_ip_hmac'] = $byIp;
            $store[$bucketKey] = $bucket;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);

            $reservation = [
                'next_index' => $nextIndex,
                'pending_marker' => $pendingMarker,
                'synthetic_tx' => $syntheticTx,
            ];
        } finally {
            flock($fp, LOCK_UN);
        }
    } finally {
        fclose($fp);
    }

    // Fase 2 (fora do lock de promo_claims.json — sem locks aninhados): emite e finaliza o Session Pass
    $token = '';
    $meta = [];
    try {
        $repo = grid402_pass_repository();
        $issued = $repo->issuePass($reservation['synthetic_tx'], $credits * 5000, false);
        $token = (string) ($issued['pass_token'] ?? '');
        if ($token === '') {
            grid402_promo_rollback_reservation($file, $bucketKey, $ipHmac, $reservation['pending_marker']);
            return [
                'ok' => false,
                'code' => 500,
                'error' => 'PassIssueFailed',
                'message' => 'Failed to issue promotional Session Pass.',
            ];
        }
        $meta = grid402_promo_finalize_pass_record($token, $campaign, $now);
    } catch (Throwable $e) {
        grid402_promo_rollback_reservation($file, $bucketKey, $ipHmac, $reservation['pending_marker']);
        return [
            'ok' => false,
            'code' => 500,
            'error' => 'PassIssueFailed',
            'message' => 'Failed to issue promotional Session Pass.',
        ];
    }

    // Fase 3 (lock rápido em promo_claims.json): substitui marcador pendente pelo pass_id final
    $parts = explode('_', $token);
    $passId = $parts[2] ?? '';
    $fpCommit = @fopen($file, 'c+');
    if ($fpCommit) {
        try {
            if (flock($fpCommit, LOCK_EX)) {
                try {
                    $store = grid402_read_locked_json($fpCommit, []);
                    if (isset($store[$bucketKey]['claims_by_ip_hmac'][$ipHmac])) {
                        $store[$bucketKey]['claims_by_ip_hmac'][$ipHmac] = [
                            'pass_id' => $passId,
                            'claimed_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
                        ];
                        ftruncate($fpCommit, 0);
                        rewind($fpCommit);
                        fwrite($fpCommit, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        fflush($fpCommit);
                    }
                } finally {
                    flock($fpCommit, LOCK_UN);
                }
            }
        } finally {
            fclose($fpCommit);
        }
    }

    $nextIndex = (int) $reservation['next_index'];
    $domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';

    return [
        'ok' => true,
        'code' => 200,
        'promo_code' => $code,
        'campaign_title' => $campaign['title'],
        'pass_token' => $token,
        'tier' => $meta['tier'],
        'credits' => $credits,
        'ttl_days' => $meta['validity_days'],
        'daily_credit_limit' => $dailyLimit,
        'locked_limits' => true,
        'expires_at' => $meta['expires_at'],
        'remaining_claims' => max(0, $maxClaims - $nextIndex),
        'max_claims' => $maxClaims,
        'curl_snippet' => 'curl -s -H "X-Payment: ' . $token . '" "' . $domain . '/api/v1/extract?url=https://example.com"',
        'mcp_snippet' => 'GRID402_SESSION_PASS=' . $token . ' npx -y grid402-mcp',
    ];
}

/**
 * Zera o contador e o histórico de IPs de uma campanha promocional (uso exclusivo admin autenticado).
 */
function grid402_promo_reset_campaign(string $rawCode): array {
    $campaign = grid402_promo_find_campaign($rawCode);
    if ($campaign === null) {
        return ['ok' => false, 'code' => 404, 'error' => 'PromoNotFound', 'message' => 'Promo code not found.'];
    }
    $code = (string) $campaign['code'];
    $bucketKey = grid402_promo_bucket_key($campaign);
    $file = grid402_promo_claims_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return ['ok' => false, 'code' => 500, 'error' => 'StorageUnavailable', 'message' => 'Unable to access promo claims storage.'];
    }
    try {
        if (flock($fp, LOCK_EX)) {
            try {
                $store = grid402_read_locked_json($fp, []);
                $previous = (int) ($store[$bucketKey]['total_claims'] ?? 0);
                $store[$bucketKey] = [
                    'total_claims' => 0,
                    'claims_by_ip_hmac' => [],
                ];
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
            } finally {
                flock($fp, LOCK_UN);
            }
        }
    } finally {
        fclose($fp);
    }
    return [
        'ok' => true,
        'code' => 200,
        'promo_code' => $code,
        'previous_claims' => $previous ?? 0,
        'remaining_claims' => (int) $campaign['max_claims'],
        'max_claims' => (int) $campaign['max_claims'],
    ];
}
