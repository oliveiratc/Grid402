<?php
/**
 * Grid402 — Helper para fila de reengagement de Session Pass
 *
 * Lê public_html/private_data/pass_reengagement_queue.json (gerado por
 * scripts/pass-reengagement.js) e retorna estatísticas dos passes ociosos
 * do usuário atual.
 *
 * Identificação do usuário:
 *   - Parâmetro de URL ?token= (ex: payment_dashboard.php?token=pass_g402_...)
 *   - Cookie g402_pass_token (definido pelo frontend após emissão de pass)
 */

declare(strict_types=1);

if (!function_exists('grid402_get_user_idle_pass_stats')) {
    function grid402_get_user_idle_pass_stats(): array {
        $privateDir = defined('GRID402_PRIVATE_DIR')
            ? rtrim((string) GRID402_PRIVATE_DIR, '/\\')
            : (function_exists('grid402_get_storage_dir')
                ? rtrim((string) grid402_get_storage_dir(), '/\\')
                : (__DIR__ . '/../private_data'));
        $queuePath = $privateDir . '/pass_reengagement_queue.json';
        if (!file_exists($queuePath)) {
            $fallbackPath = __DIR__ . '/../private_data/pass_reengagement_queue.json';
            if (file_exists($fallbackPath)) {
                $queuePath = $fallbackPath;
            }
        }

        if (!file_exists($queuePath) || !is_readable($queuePath)) {
            return [
                'idle_passes'   => 0,
                'idle_usdc'     => 0.0,
                'idle_credits'  => 0,
                'expires_min_days' => null,
                'reasons'       => [],
                'tokens'        => [],
            ];
        }

        $json = file_get_contents($queuePath);
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['queue']) || !is_array($data['queue'])) {
            return [
                'idle_passes'   => 0,
                'idle_usdc'     => 0.0,
                'idle_credits'  => 0,
                'expires_min_days' => null,
                'reasons'       => [],
                'tokens'        => [],
            ];
        }

        // Identifica o token do usuário atual
        $userToken = '';

        // 1. Parâmetro de URL (payment_dashboard.php?token=...)
        $urlToken = (string) ($_GET['token'] ?? '');
        if ($urlToken !== '') {
            $userToken = $urlToken;
        }

        // 2. Cookie definido pelo frontend após emissão de pass
        if ($userToken === '' && isset($_COOKIE['g402_pass_token'])) {
            $userToken = (string) $_COOKIE['g402_pass_token'];
        }

        // Se não há identificação do usuário, retorna stats globais vazias
        // (o banner exibirá a variante genérica de conscientização)
        if ($userToken === '') {
            return [
                'idle_passes'   => 0,
                'idle_usdc'     => 0.0,
                'idle_credits'  => 0,
                'expires_min_days' => null,
                'reasons'       => [],
                'tokens'        => [],
            ];
        }

        // Filtra a fila para este usuário (pass_token corresponde)
        $userQueue = array_filter($data['queue'], function (array $item) use ($userToken): bool {
            return isset($item['pass_token']) && $item['pass_token'] === $userToken;
        });

        if (empty($userQueue)) {
            return [
                'idle_passes'   => 0,
                'idle_usdc'     => 0.0,
                'idle_credits'  => 0,
                'expires_min_days' => null,
                'reasons'       => [],
                'tokens'        => [],
            ];
        }

        $idleUsdc = 0.0;
        $idleCredits = 0;
        $expiresMinDays = null;
        $reasons = [];
        $tokens = [];

        foreach ($userQueue as $item) {
            $usdc = (float) ($item['usdc_deposited'] ?? 0.0);
            $credits = (int) ($item['credits_remaining'] ?? 0);
            $days = (int) ($item['days_until_expiry'] ?? 0);
            $reason = (string) ($item['reason'] ?? '');

            $idleUsdc += $usdc;
            $idleCredits += $credits;
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            $tokens[] = $item['pass_token'];

            if ($days > 0 && ($expiresMinDays === null || $days < $expiresMinDays)) {
                $expiresMinDays = $days;
            }
        }

        return [
            'idle_passes'   => count($userQueue),
            'idle_usdc'     => round($idleUsdc, 4),
            'idle_credits'  => $idleCredits,
            'expires_min_days' => $expiresMinDays,
            'reasons'       => $reasons,
            'tokens'        => $tokens,
        ];
    }
}

if (!function_exists('grid402_set_pass_token_cookie')) {
    function grid402_set_pass_token_cookie(string $token): void {
        if ($token === '') return;
        setcookie('g402_pass_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
}
