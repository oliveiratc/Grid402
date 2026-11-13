<?php
/**
 * Grid402 - Growth Engine, Agent Rewards e Reputation Layer
 *
 * Armazenamento SQLite-free (Zero-DB) com flock atômico em JSON:
 * - Identidade de carteiras por HMAC (não armazenamos PII nem seed/private-key);
 * - Telemetria de funil de compra; pass e x402 separados;
 * - Incentivos pendentes até validação administrativa e antifraude;
 * - Reputação baseada em atividade legítima, não apenas valor financeiro.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/services/pass.php';
require_once __DIR__ . '/services/pass.php';

/**
 * Caminho do armazenamento de growth (Zero-DB).
 */
function grid402_growth_file(): string {
    return GRID402_PRIVATE_DIR . '/growth.json';
}

/**
 * Segredo local para HMAC. Usa a mesma ordem de precedência do oráculo.
 */
function grid402_growth_secret(): string {
    $privKey = (string) grid402_env('GRID402_ORACLE_PRIVATE_KEY', '');
    if ($privKey !== '') {
        return hash_hmac('sha256', 'grid402-growth-v1:' . $privKey, 'grid402-growth-v1');
    }
    return hash_hmac('sha256', 'grid402_growth_v1:' . GRID402_CRON_SECRET . ':' . GRID402_WALLET_ADDRESS, 'grid402-growth-v1');
}

/**
 * Hash opaco da carteira/pagamento. Usa wallet quando disponível; caso contrário prova x402.
 */
function grid402_growth_actor_id(?string $payer = null, ?string $proof = null, ?string $ip = null): string {
    $seed = strtolower(trim((string)$payer));
    if ($seed === '' || $seed === 'unknown') {
        $seed = strtolower(trim((string)$proof));
    }
    if ($seed === '' || $seed === 'demo' || $seed === 'sandbox') {
        $seed = trim((string)$ip) !== '' ? grid402_mask_ip((string)$ip) : 'anonymous';
    }
    return 'g_' . substr(hash_hmac('sha256', substr($seed, 0, 160), grid402_growth_secret()), 0, 32);
}

/**
 * Configuração padrão de crescimento. Ajustes só entram pelo painel admin.
 */
function grid402_growth_defaults(): array {
    return [
        'version' => 1,
        'free_trial_limit' => 3,
        'recurring_service_threshold' => 3,
        'recurring_24h_threshold' => 5,
        'low_balance_threshold' => 10,
        'recharge_starter_credits' => 4000,
        'recharge_pro_credits' => 10000,
        'recharge_fleet_credits' => 50000,
        'agent_spend_limit_usdc' => 5.0,
        'agent_spend_window_days' => 30,
        'starter_promo_enabled' => true,
        'starter_promo_credits' => 10,
        'starter_promo_limit_per_wallet' => 1,
        'starter_promo_requires_first_paid' => true,
        'referrals_enabled' => true,
        'referral_reward_credits' => 25,
        'builder_reward_credits' => 100,
        'builder_activation_requests' => 5,
        'promo_auto_issue' => false,
        'rewards_pending_validation' => true,
        'max_rewards_per_day' => 25,
        'self_referral_block' => true,
        'rewards_enabled' => true,
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}

/**
 * Limpa texto vindo de admin/agentes antes de persistir.
 */
function grid402_growth_clean_text(?string $value, int $max = 240): string {
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string)$value));
    $value = preg_replace('/\s+/u', ' ', (string)$value);
    return substr((string)$value, 0, $max);
}

/**
 * Normaliza payload de crescimento preservando campos desconhecidos somente se seguros.
 */
function grid402_growth_normalize(array $data): array {
    $defaults = grid402_growth_defaults();
    $out = $defaults;

    if (isset($data['config']) && is_array($data['config'])) {
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $data['config'])) {
                continue;
            }
            $value = $data['config'][$key];
            if (is_bool($default)) {
                $out['config'][$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($default) || is_float($default)) {
                $out['config'][$key] = (float)$value;
                if (is_int($default)) {
                    $out['config'][$key] = (int) round((float)$value);
                }
            } elseif (is_string($default)) {
                $out['config'][$key] = grid402_growth_clean_text((string)$value, 240);
            }
        }
    }

    $out['config'] = array_merge($defaults, $out['config'] ?? []);
    $out['payload_version'] = 1;
    $out['profiles'] = is_array($data['profiles'] ?? null) ? $data['profiles'] : [];
    $out['builders'] = is_array($data['builders'] ?? null) ? $data['builders'] : [];
    $out['referrals'] = is_array($data['referrals'] ?? null) ? $data['referrals'] : [];
    $out['rewards'] = is_array($data['rewards'] ?? null) ? $data['rewards'] : [];
    $out['audit_log'] = is_array($data['audit_log'] ?? null) ? array_slice($data['audit_log'], -100) : [];
    $out['events'] = is_array($data['events'] ?? null) ? array_slice($data['events'], -100) : [];
    $out['created_at'] = $data['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z');
    $out['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
    return $out;
}

/**
 * Lê armazenamento com lock compartilhado.
 */
function grid402_growth_read(): array {
    $file = grid402_growth_file();
    if (!file_exists($file)) {
        $default = grid402_growth_normalize([]);
        $default['config'] = grid402_growth_defaults();
        return $default;
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return grid402_growth_normalize([]);
    }

    $payload = '';
    if (flock($fp, LOCK_SH)) {
        $size = filesize($file);
        $payload = $size > 0 ? (string) fread($fp, $size) : '';
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    $decoded = $payload !== '' ? @json_decode($payload, true) : [];
    return is_array($decoded) ? grid402_growth_normalize($decoded) : grid402_growth_normalize([]);
}

/**
 * Grava armazenamento com lock exclusivo.
 */
function grid402_growth_persist_locked(array $data): bool {
    $file = grid402_growth_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return false;
    }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        $normalized = grid402_growth_normalize($data);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = true;
    }
    fclose($fp);
    return $ok;
}

/**
 * Executa mutação atomicamente sobre o arquivo growth.json.
 */
function grid402_growth_update(callable $mutator): array {
    $file = grid402_growth_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        $data = grid402_growth_read();
        try {
            return $mutator($data) ?: $data;
        } catch (Throwable $e) {
            return $data;
        }
    }

    $result = null;
    if (flock($fp, LOCK_EX)) {
        $size = filesize($file);
        $payload = $size > 0 ? (string) fread($fp, $size) : '';
        $decoded = $payload !== '' ? @json_decode($payload, true) : [];
        $data = is_array($decoded) ? grid402_growth_normalize($decoded) : grid402_growth_normalize([]);
        try {
            $result = $mutator($data);
            if (!is_array($result)) {
                $result = $data;
            }
            $normalized = grid402_growth_normalize($result);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        } catch (Throwable $e) {
            $result = $data;
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return is_array($result) ? $result : grid402_growth_read();
}

/**
 * Incrementa contadores e mantém estado de funil por carteira.
 */
function grid402_growth_event(string $type, array $context = []): array {
    $type = grid402_growth_clean_text($type, 80);
    if ($type === '') {
        $type = 'event';
    }

    return grid402_growth_update(function (array $data) use ($type, $context) {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $actor = grid402_growth_actor_id(
            isset($context['payer']) ? (string)$context['payer'] : null,
            isset($context['proof']) ? (string)$context['proof'] : null,
            isset($context['ip']) ? (string)$context['ip'] : null
        );

        if (!isset($data['profiles'][$actor]) || !is_array($data['profiles'][$actor])) {
            $data['profiles'][$actor] = [
                'actor_id' => $actor,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'first_paid_at' => null,
                'second_paid_at' => null,
                'wallet_last4' => isset($context['payer']) ? substr(strtolower((string)$context['payer']), -4) : null,
                'services' => [],
                'paid_requests' => 0,
                'paid_revenue_usdc' => 0.0,
                'requests_24h' => 0,
                'requests_24h_start' => $now,
                'sandbox_requests' => 0,
                'challenges_402' => 0,
                'days_active' => 0,
                'active_days' => [],
                'first_service' => null,
            ];
        }

        $p =& $data['profiles'][$actor];
        $p['last_seen_at'] = $now;
        $day = gmdate('Y-m-d');
        if (!in_array($day, (array)($p['active_days'] ?? []), true)) {
            $p['active_days'] = array_values(array_unique(array_merge((array)($p['active_days'] ?? []), [$day])));
            $p['days_active'] = count($p['active_days']);
        }

        $service = grid402_growth_clean_text(isset($context['service']) ? (string)$context['service'] : 'unknown', 80);
        $p['services'][$service] = (int)($p['services'][$service] ?? 0) + 1;

        $isPaid = !empty($context['is_paid']);
        $amount = (float)($context['amount_usdc'] ?? 0.0);

        if ($type === 'challenge_402') {
            $p['challenges_402'] = (int)($p['challenges_402'] ?? 0) + 1;
        } elseif ($type === 'sandbox' || !empty($context['sandbox'])) {
            $p['sandbox_requests'] = (int)($p['sandbox_requests'] ?? 0) + 1;
        } elseif ($type === 'pass_purchase' || $type === 'promo_pass') {
            // Compra de créditos não tem receita incremental de consulta; rastreamos como sinal de intenção.
        } elseif ($isPaid) {
            $p['paid_requests'] = (int)($p['paid_requests'] ?? 0) + 1;
            $p['paid_revenue_usdc'] = round((float)($p['paid_revenue_usdc'] ?? 0.0) + $amount, 8);
            if (empty($p['first_paid_at'])) {
                $p['first_paid_at'] = $now;
                $p['first_service'] = $service;
            } elseif (empty($p['second_paid_at'])) {
                $p['second_paid_at'] = $now;
            }
        }

        // Janela deslizante de ~24h para "requests_24h" (não é mais acumulador vitalício)
        $windowStart = strtotime((string)($p['requests_24h_start'] ?? $now)) ?: time();
        if ((time() - $windowStart) >= 86400) {
            $p['requests_24h'] = 0;
            $p['requests_24h_start'] = $now;
        }
        $p['requests_24h'] = (int)($p['requests_24h'] ?? 0) + (($type !== 'page_visit') ? 1 : 0);

        $data['events'][] = [
            'timestamp' => $now,
            'type' => $type,
            'actor_id' => $actor,
            'service' => $service,
            'amount_usdc' => $isPaid ? $amount : 0.0,
        ];
        if (count($data['events']) > 100) {
            $data['events'] = array_values(array_slice($data['events'], -100));
        }

        return $data;
    });
}

/**
 * Marca evento de primeira/segunda transação a partir de uma resposta paga.
 */
function grid402_growth_track_paid_request(array $context): array {
    $context['is_paid'] = true;
    $context['type'] = 'paid_request';
    return grid402_growth_event('paid_request', $context);
}

/**
 * Cria uma solicitação de recompensa pendente de validação humana/admin.
 */
function grid402_growth_submit_reward_request(array $input, string $actorId): array {
    return grid402_growth_update(function (array $data) use ($input, $actorId) {
        $cfg = $data['config'];
        $type = grid402_growth_clean_text($input['type'] ?? '', 60);
        $credits = max(0, (int)($input['credits'] ?? 0));
        $allowed = ['starter_promo', 'builder_reward', 'referral_bonus'];
        if (!in_array($type, $allowed, true) || ($credits > 0 && !($cfg['rewards_enabled'] ?? true))) {
            return ['error' => 'invalid_reward_type'];
        }

        $today = gmdate('Y-m-d');
        $issuedToday = 0;
        foreach ((array)($data['rewards'] ?? []) as $r) {
            if ((($r['status'] ?? '') === 'issued' || ($r['status'] ?? '') === 'pending') && gmdate('Y-m-d', strtotime((string)($r['created_at'] ?? 'now'))) === $today) {
                $issuedToday++;
            }
        }
        if ($issuedToday >= (int)($cfg['max_rewards_per_day'] ?? 25)) {
            return ['error' => 'daily_limit_reached'];
        }

        $data['rewards'][] = [
            'reward_id' => 'rew_' . bin2hex(random_bytes(8)),
            'type' => $type,
            'actor_id' => $actorId,
            'wallet_last4' => grid402_growth_clean_text($input['wallet_last4'] ?? '', 4),
            'credits' => $credits,
            'status' => 'pending',
            'reason' => grid402_growth_clean_text($input['reason'] ?? '', 160),
            'referral_code' => grid402_growth_clean_text($input['referral_code'] ?? '', 32),
            'builder_slug' => grid402_growth_clean_text($input['builder_slug'] ?? '', 80),
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        if (count($data['rewards']) > 200) {
            $data['rewards'] = array_slice($data['rewards'], -200);
        }
        return $data;
    });
}

/**
 * Registra um builder da comunidade. Nenhum crédito é emitido automaticamente.
 */
function grid402_growth_register_builder(array $input): array {
    return grid402_growth_update(function (array $data) use ($input) {
        $name = grid402_growth_clean_text($input['name'] ?? '', 80);
        $email = grid402_growth_clean_text($input['email'] ?? '', 120);
        $url = grid402_growth_clean_text($input['url'] ?? '', 200);
        if (strlen($name) < 2 || strlen($email) > 0 && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'invalid_builder'];
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name) ?? '');
        $slug = trim($slug, '-') ?: 'builder-' . bin2hex(random_bytes(4));
        $actorId = grid402_growth_actor_id(null, null, (string)($input['proof_id'] ?? $email ?: $name));
        $id = 'bld_' . bin2hex(random_bytes(8));
        $data['builders'][] = [
            'builder_id' => $id,
            'slug' => $slug,
            'actor_id' => $actorId,
            'name' => $name,
            'email' => $email,
            'url' => $url ?: $slug,
            'wallet_last4' => '',
            'validated_at' => null,
            'status' => 'pending',
            'registered_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'paid_requests_tracked' => 0,
        ];
        return $data;
    });
}

/**
 * Cria um código de indicação associado a uma carteira (anonimizado).
 */
function grid402_growth_create_referral(array $input): array {
    return grid402_growth_update(function (array $data) use ($input) {
        $wallet = strtolower(trim((string)($input['wallet'] ?? '')));
        if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet)) {
            return ['error' => 'invalid_wallet'];
        }
        $actorId = grid402_growth_actor_id($wallet);
        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $data['referrals'][] = [
            'code' => $code,
            'actor_id' => $actorId,
            'wallet_last4' => substr($wallet, -4),
            'status' => 'active',
            'uses' => 0,
            'first_payment_volume_usdc' => 0.0,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        return $data;
    });
}

/**
 * Atualiza config e registra auditoria.
 */

function grid402_growth_update_config(array $cfg): array {
    return grid402_growth_update(function (array $data) use ($cfg) {
        $current = $data['config'] ?? grid402_growth_defaults();
        $incoming = is_array($cfg) ? $cfg : [];
        $keys = array_keys(grid402_growth_defaults());
        foreach ($keys as $k) {
            if (!array_key_exists($k, $incoming)) {
                continue;
            }
            $v = $incoming[$k];
            if (is_bool(grid402_growth_defaults()[$k])) {
                $current[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int(grid402_growth_defaults()[$k])) {
                $current[$k] = max(0, (int)$v);
            } else {
                $current[$k] = (float)$v;
            }
        }
        $current['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $data['config'] = $current;
        $data['audit_log'][] = [
            'timestamp' => $current['updated_at'],
            'action' => 'config_update',
            'user' => 'admin',
            'summary' => 'Configuração Growth atualizada',
        ];
        return $data;
    });
}

/**
 * Emite uma recompensa pendente já aprovada. Retorna o token ou null.
 */
function grid402_growth_issue_pending_reward(string $rewardId): array {
    $data = grid402_growth_read();
    $idx = -1;
    foreach ($data['rewards'] as $i => $r) {
        if (($r['reward_id'] ?? '') === $rewardId) {
            $idx = $i;
            break;
        }
    }
    if ($idx === -1) {
        return ['error' => 'not_found'];
    }
    $r = $data['rewards'][$idx];
    if (($r['status'] ?? '') !== 'pending') {
        return ['error' => 'not_pending'];
    }
    $credits = max(0, (int)($r['credits'] ?? 10));
    if ($credits <= 0) {
        return ['error' => 'no_credits'];
    }
    $issued = grid402_issue_session_pass('promo_' . $rewardId, $credits * 500, false);
    $data['rewards'][$idx]['status'] = $issued['status'] === 'ACTIVE' ? 'issued' : 'failed';
    $data['rewards'][$idx]['pass_token'] = $issued['pass_token'] ?? null;
    $data['rewards'][$idx]['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
    $data = grid402_growth_persist_locked($data);
    return ['ok' => true, 'credits' => $credits, 'reward' => $data['rewards'][$idx] ?? $r];
}

/**
 * Indicadores agregados para dashboard e crescimento.
 */
function grid402_growth_metrics(): array {
    $data = grid402_growth_read();
    $profiles = is_array($data['profiles'] ?? null) ? $data['profiles'] : [];
    $cfg = $data['config'];

    $walletsConnected = count($profiles);
    $walletsPaying = 0;
    $walletsSecondPayment = 0;
    $revenue = 0.0;
    $txs = 0;
    $paidServices = [];
    $recurrent = 0;
    $newByDay = [];
    $activeDays = [];
    $promoIssued = 0;
    $referrals = count($data['referrals']);
    $builders = count($data['builders']);

    foreach ($profiles as $p) {
        $paidRequests = (int)($p['paid_requests'] ?? 0);
        $txs += $paidRequests;
        $revenue += (float)($p['paid_revenue_usdc'] ?? 0.0);
        if ($paidRequests > 0) {
            $walletsPaying++;
        }
        if (!empty($p['second_paid_at'])) {
            $walletsSecondPayment++;
        }
        if ((int)($p['paid_requests'] ?? 0) >= 3) {
            $recurrent++;
        }
        foreach ((array)($p['services'] ?? []) as $svc => $count) {
            $paidServices[$svc] = (int)($paidServices[$svc] ?? 0) + (int)$count;
        }
        $day = gmdate('Y-m-d', strtotime((string)($p['first_seen_at'] ?? 'now')));
        $newByDay[$day] = (int)($newByDay[$day] ?? 0) + 1;
        $activeDays[gmdate('Y-m-d', strtotime((string)($p['last_seen_at'] ?? 'now')))] = true;
    }

    $conversion = $walletsConnected > 0 ? round(($walletsPaying / $walletsConnected) * 100, 1) : 0.0;
    $second = $walletsPaying > 0 ? round(($walletsSecondPayment / $walletsPaying) * 100, 1) : 0.0;
    $revenuePerWallet = $walletsConnected > 0 ? round($revenue / $walletsConnected, 8) : 0.0;
    $revenuePerAgent = $walletsPaying > 0 ? round($revenue / $walletsPaying, 8) : 0.0;

    return [
        'wallets_connected' => $walletsConnected,
        'unique_wallets' => $walletsConnected,
        'new_wallets_by_day' => $newByDay,
        'active_wallets_14d' => count($activeDays),
        'wallets_paying' => $walletsPaying,
        'fixed_first_payment_conversion_rate' => $conversion,
        'conversion_first_payment' => $conversion,
        'conversion_second_payment' => $second,
        'wallet_retention' => $walletsPaying > 0 ? round(($recurrent / $walletsPaying) * 100, 1) : 0.0,
        'agent_activation_rate' => $walletsPaying > 0 ? round(($walletsPaying / max(1, $walletsConnected)) * 100, 1) : 0.0,
        'revenue_per_wallet_usdc' => $revenuePerWallet,
        'revenue_per_agent_usdc' => $revenuePerAgent,
        'paid_revenue_usdc' => round($revenue, 8),
        'paid_transactions' => $txs,
        'paid_requests_per_service' => $paidServices,
        'paid_requests' => $txs,
        'usdc_transaction_volume' => round($revenue, 8),
        'referrals_registered' => $referrals,
        'builders_registered' => $builders,
        'promo_credits_total' => array_sum(array_map('intval', array_column(array_filter($data['rewards'] ?? [], fn($r) => ($r['status'] ?? '') === 'issued'), 'credits'))),
        'config' => $cfg,
    ];
}

/**
 * Resposta administrativa sanitizada do Growth Engine.
 */
function grid402_growth_admin_payload(): array {
    $data = grid402_growth_read();
    $metrics = grid402_growth_metrics();
    $profiles = array_values(is_array($data['profiles'] ?? null) ? $data['profiles'] : []);

    usort($profiles, function ($a, $b) {
        return ((int)($b['paid_requests'] ?? 0)) <=> ((int)($a['paid_requests'] ?? 0));
    });

    return [
        'status' => 'success',
        'metrics' => $metrics,
        'recent_events' => array_slice(array_reverse((array)($data['events'] ?? [])), 0, 30),
        'profiles_top' => array_slice($profiles, 0, 15),
        'builders' => array_slice((array)($data['builders'] ?? []), -15),
        'referrals' => array_slice((array)($data['referrals'] ?? []), -15),
        'rewards' => array_slice((array)($data['rewards'] ?? []), -15),
        'config' => $data['config'] ?? grid402_growth_defaults(),
        'audit_log' => array_slice((array)($data['audit_log'] ?? []), -20),
    ];
}
