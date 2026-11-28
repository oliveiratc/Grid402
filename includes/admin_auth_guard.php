<?php
/**
 * Grid402 - Guarda de Autenticacao e Sessao Segura do Portal Administrativo
 *
 * Recursos:
 *  - Cookie estrito HttpOnly + SameSite=Lax + Secure (HTTPS) + strict_mode
 *  - Vinculo criptografico de sessao (sub-rede IP /24 ou /64 + User-Agent)
 *  - Expiracao automatica por inatividade (padrao: 30 minutos / 1800s)
 *  - Suporte a desafio 2FA via Telegram (@GRIG402BOT) no login
 */

declare(strict_types=1);

if (!function_exists('grid402_admin_idle_timeout_sec')) {
    function grid402_admin_idle_timeout_sec(): int {
        $raw = function_exists('grid402_env') ? (int) grid402_env('GRID402_ADMIN_IDLE_TIMEOUT_SEC', 1800) : 1800;
        return max(5, $raw);
    }

    function grid402_admin_ip_subnet(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0') . '.' . ($parts[2] ?? '0') . '.0/24';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if (is_string($packed) && strlen($packed) === 16) {
                return bin2hex(substr($packed, 0, 8)) . '::/64';
            }
        }
        return $ip;
    }

    function grid402_admin_session_fingerprint(): string {
        $ip = function_exists('grid402_get_trusted_client_ip')
            ? grid402_get_trusted_client_ip()
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $subnet = grid402_admin_ip_subnet($ip);
        $ua = substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 256);
        return hash('sha256', 'g402_fp_v1|' . $subnet . '|' . $ua);
    }

    function grid402_admin_session_start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
                || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            session_name('GRID402_ADMIN_SESS');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    function grid402_set_admin_auth_marker_cookie(bool $active): void {
        $_COOKIE['g402_admin_auth'] = $active ? '1' : '';
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
                || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
            @setcookie('g402_admin_auth', $active ? '1' : '', [
                'expires' => $active ? (time() + grid402_admin_idle_timeout_sec()) : (time() - 3600),
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    function grid402_establish_admin_session(string $username): void {
        $now = time();
        session_regenerate_id(true);
        unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_recovery_pending']);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        $_SESSION['admin_login_at'] = $now;
        $_SESSION['admin_last_activity'] = $now;
        $_SESSION['admin_fingerprint'] = grid402_admin_session_fingerprint();
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        grid402_set_admin_auth_marker_cookie(true);
    }

    function grid402_invalidate_admin_session_state(): void {
        unset(
            $_SESSION['admin_logged_in'],
            $_SESSION['admin_user'],
            $_SESSION['admin_login_at'],
            $_SESSION['admin_last_activity'],
            $_SESSION['admin_fingerprint'],
            $_SESSION['admin_2fa_pending'],
            $_SESSION['admin_recovery_pending']
        );
        grid402_set_admin_auth_marker_cookie(false);
    }

    function grid402_verify_active_admin_session(bool $touchActivity = true): array {
        $idleTimeout = grid402_admin_idle_timeout_sec();
        if (empty($_SESSION['admin_logged_in'])) {
            return ['valid' => false, 'reason' => 'not_logged_in', 'idle_timeout_sec' => $idleTimeout];
        }

        $now = time();
        $currentFp = grid402_admin_session_fingerprint();
        $storedFp = (string) ($_SESSION['admin_fingerprint'] ?? '');

        if ($storedFp === '') {
            $_SESSION['admin_fingerprint'] = $currentFp;
        } elseif (!hash_equals($storedFp, $currentFp)) {
            grid402_invalidate_admin_session_state();
            return ['valid' => false, 'reason' => 'fingerprint_mismatch', 'idle_timeout_sec' => $idleTimeout];
        }

        $lastAct = (int) ($_SESSION['admin_last_activity'] ?? 0);
        if ($lastAct > 0 && ($now - $lastAct) > $idleTimeout) {
            grid402_invalidate_admin_session_state();
            return ['valid' => false, 'reason' => 'idle_timeout', 'idle_timeout_sec' => $idleTimeout];
        }

        if ($touchActivity || $lastAct === 0) {
            $_SESSION['admin_last_activity'] = $now;
            grid402_set_admin_auth_marker_cookie(true);
        }

        return [
            'valid' => true,
            'reason' => 'ok',
            'idle_timeout_sec' => $idleTimeout,
            'last_activity' => (int) $_SESSION['admin_last_activity'],
            'login_at' => (int) ($_SESSION['admin_login_at'] ?? $now),
        ];
    }

    function grid402_is_telegram_2fa_required(array $authData): bool {
        $prefEnabled = array_key_exists('two_factor_telegram', $authData)
            ? (bool) $authData['two_factor_telegram']
            : true;
        if (!$prefEnabled) {
            return false;
        }
        $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ((string) grid402_env('GRID402_TEST_OTP_CAPTURE', '') === '1' && in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }
        if (function_exists('grid402_telegram_runtime_status')) {
            $tg = grid402_telegram_runtime_status();
            return !empty($tg['configured']);
        }
        return false;
    }
}
