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
require_once __DIR__ . '/pass_repository.php';

/**
 * Retorna ou substitui a instância de PassStorageInterface ativa (Zero-DB JsonFlockPassRepository por padrão).
 */
function grid402_pass_repository(?PassStorageInterface $override = null): PassStorageInterface {
    static $repo = null;
    if ($override !== null) {
        $repo = $override;
    } elseif ($repo === null) {
        $repo = new JsonFlockPassRepository();
    }
    return $repo;
}

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
 * Normaliza um registro de passe legado (escalas 10x ou 100x) para a escala oficial v2_5000
 * (1 crédito = 0.0050 USDC), utilizando usdc_deposited quando disponível para detectar o fator real.
 */
function grid402_normalize_pass_entry(array $entry): array {
    if (($entry['credit_scale'] ?? '') !== 'v2_5000') {
        $total = max(0, (int) ($entry['credits_total'] ?? 0));
        $rem = max(0, (int) ($entry['credits_remaining'] ?? 0));
        $mode = strtolower(trim((string) ($entry['mode'] ?? 'production')));
        $tier = strtolower(trim((string) ($entry['tier'] ?? 'standard')));
        $usdcRaw = trim((string) ($entry['usdc_deposited'] ?? ''));

        $amountUsdc = 0.0;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)/', $usdcRaw, $m) === 1) {
            $amountUsdc = (float) $m[1];
        }

        $divisor = 1;
        if ($amountUsdc > 0.0 && $total > 0) {
            // Na escala v2_5000 (1 cr = 0.0050 USDC), 1 USDC gera 200 créditos base (até 1.5x com bônus Fleet).
            // Escala legada 10x gera ratio ~10..15; escala legada 100x gera ratio ~100..150.
            $expectedBaseCredits = $amountUsdc / 0.005;
            $ratio = $total / max(0.0001, $expectedBaseCredits);
            if ($ratio >= 50.0) {
                $divisor = 100;
            } elseif ($ratio >= 5.0) {
                $divisor = 10;
            }
        } elseif ($mode !== 'sandbox' && !str_starts_with($tier, 'promo_') && $total > 0) {
            // Fallback conservador quando usdc_deposited estiver ausente em registros antigos
            if ($total >= 40000) {
                $divisor = 100;
            } elseif ($total >= 4000 && $tier !== 'fleet') {
                $divisor = 10;
            }
        }

        if ($divisor > 1) {
            $newTotal = max(1, intdiv($total, $divisor));
            $newRem = min($newTotal, max(0, intdiv($rem, $divisor)));
            $entry['credits_total'] = $newTotal;
            $entry['credits_remaining'] = $newRem;
        }
        $entry['credit_scale'] = 'v2_5000';
    }
    $todayUtc = gmdate('Y-m-d');
    if (($entry['usage_day_utc'] ?? '') !== $todayUtc) {
        $entry['usage_day_utc'] = $todayUtc;
        $entry['credits_used_today'] = 0;
    }
    return $entry;
}

/**
 * Busca um Session Pass já emitido pelo tx_hash/lock_key para retorno idempotente no checkout Web3.
 */
function grid402_find_session_pass_by_tx(string $txHash): ?array {
    $repo = grid402_pass_repository();
    if (method_exists($repo, 'findPassByTxHash')) {
        return $repo->findPassByTxHash($txHash);
    }
    return null;
}

/**
 * Emite um novo Passe de Sessão Pré-pago (ou retorna o passe já emitido para a mesma txHash)
 */
function grid402_issue_session_pass(string $txHash, int $rawUsdcUnits = 50000, bool $isSandbox = false): array {
    return grid402_pass_repository()->issuePass($txHash, $rawUsdcUnits, $isSandbox);
}

/**
 * Consulta gratuitamente o status, saldo e limites de gasto (Spend Controls) de um Session Pass existente
 */
function grid402_get_session_pass_status(string $token): array {
    return grid402_pass_repository()->getPassStatus($token);
}

/**
 * Configura controles de gasto (Spend Controls: daily_credit_limit e allowed_endpoints) de um Session Pass
 */
function grid402_update_session_pass_limits(string $token, ?int $dailyCreditLimit, ?array $allowedEndpoints = null): array {
    return grid402_pass_repository()->updatePassLimits($token, $dailyCreditLimit, $allowedEndpoints);
}

/**
 * Valida e debita atomicamente créditos de um Session Pass em uma chamada de API (<15ms)
 */
function grid402_consume_session_pass(string $token, int $requiredRawUnits = 5000, ?string $endpointId = null, array $auditContext = []): array {
    return grid402_pass_repository()->consumePass($token, $requiredRawUnits, $endpointId, $auditContext);
}

/**
 * Estorna atomicamente créditos debitados de um Session Pass quando a execução do serviço falha (HTTP != 200)
 */
function grid402_refund_session_pass(string $token, int $creditsToRefund, ?string $endpointId = null, array $auditContext = []): bool {
    return grid402_pass_repository()->refundPass($token, $creditsToRefund, $endpointId, $auditContext);
}

/**
 * Registra evento estruturado Zero-PII no extrato do Session Pass (ex.: replay idempotente)
 */
function grid402_record_pass_statement_event(string $token, array $event): bool {
    return grid402_pass_repository()->recordStatementEvent($token, $event);
}

/**
 * Consulta o extrato detalhado Zero-PII de um Session Pass (máximo 200 lançamentos recentes)
 */
function grid402_get_session_pass_statement(string $token, array $filters = []): array {
    return grid402_pass_repository()->getStatement($token, $filters);
}
