<?php
/**
 * Grid402 // Bazaar Watch — verifica periodicamente o catálogo PayAI (x402 Bazaar)
 * para a carteira canônica do Grid402 com controle de estado anti-spam.
 *
 * Uso: php scripts/bazaar_watch.php
 *
 * Comportamento:
 *  - Persiste o último estado/contagem em `scripts/bazaar_watch_state.json`;
 *  - Grava no log `scripts/bazaar_watch.log` o estado atual (0 itens / N itens);
 *  - Envia alerta no Telegram SOMENTE quando houver mudança real (expansão, atualização
 *    da lista de recursos, queda de contagem ou queda/recuperação do endpoint discovery),
 *    eliminando alertas repetidos 2x/dia quando nada mudou;
 *  - Carrega credenciais de `.env` da raiz, de `GRID402_PRIVATE_DIR/.env`, de
 *    `grid402_storage/.env`, de `public_html/private_data/.env` e de `telegram_config.json`.
 *
 * Agendado via Windows Task Scheduler ("Grid402-BazaarWatch"), 2x ao dia.
 * Remover depois com: schtasks /delete /tn "Grid402-BazaarWatch" /F
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
$configFile = dirname(__DIR__) . '/public_html/config.php';
if (is_file($configFile)) {
    require_once $configFile;
}

const PAY_TO = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
const DISCOVERY_URL = 'https://facilitator.payai.network/discovery/resources?payTo=' . PAY_TO;

function bazaar_watch_state_file(): string {
    $custom = trim((string) getenv('GRID402_BAZAAR_STATE_FILE'));
    if ($custom !== '') {
        return $custom;
    }
    return __DIR__ . '/bazaar_watch_state.json';
}

function bazaar_watch_log(string $line): void {
    $log = __DIR__ . '/bazaar_watch.log';
    $ts = gmdate('Y-m-d\TH:i:s\Z');
    @file_put_contents($log, "[$ts] $line\n", FILE_APPEND | LOCK_EX);
}

function bazaar_watch_read_state(): array {
    $file = bazaar_watch_state_file();
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $decoded = $raw ? @json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function bazaar_watch_write_state(array $state): void {
    $file = bazaar_watch_state_file();
    if (function_exists('grid402_atomic_write')) {
        grid402_atomic_write($file, $state);
        return;
    }
    $json = (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $file)) {
        return;
    }
    @unlink($tmp);
    @file_put_contents($file, $json, LOCK_EX);
}

function bazaar_watch_env(): array {
    $rootDir = dirname(__DIR__);
    $candidates = [
        $rootDir . '/.env',
    ];
    if (defined('GRID402_PRIVATE_DIR')) {
        $candidates[] = rtrim((string) GRID402_PRIVATE_DIR, '/\\') . '/.env';
    }
    $candidates[] = $rootDir . '/grid402_storage/.env';
    $candidates[] = $rootDir . '/public_html/private_data/.env';
    $candidates[] = $rootDir . '/public_html/.env';

    $env = [];
    foreach (array_values(array_unique($candidates)) as $envFile) {
        if (!is_file($envFile) || !is_readable($envFile)) {
            continue;
        }
        $raw = (string) @file_get_contents($envFile);
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if ($v !== '' && (($env[$k] ?? '') === '')) {
                $env[$k] = $v;
            }
        }
    }

    foreach (['GRID402_TELEGRAM_BOT_TOKEN', 'GRID402_TELEGRAM_CHAT_ID'] as $k) {
        if (($env[$k] ?? '') === '') {
            $val = function_exists('grid402_env') ? (string) grid402_env($k, '') : (string) getenv($k);
            if ($val !== '') {
                $env[$k] = $val;
            }
        }
    }

    // Fallback para telegram_config.json em GRID402_PRIVATE_DIR (salvo via /admin)
    if ((($env['GRID402_TELEGRAM_BOT_TOKEN'] ?? '') === '' || ($env['GRID402_TELEGRAM_CHAT_ID'] ?? '') === '')
        && function_exists('grid402_resolve_telegram_credentials')) {
        $resolved = grid402_resolve_telegram_credentials();
        if (($env['GRID402_TELEGRAM_BOT_TOKEN'] ?? '') === '' && !empty($resolved['token'])) {
            $env['GRID402_TELEGRAM_BOT_TOKEN'] = (string) $resolved['token'];
        }
        if (($env['GRID402_TELEGRAM_CHAT_ID'] ?? '') === '' && !empty($resolved['chat_id'])) {
            $env['GRID402_TELEGRAM_CHAT_ID'] = (string) $resolved['chat_id'];
        }
    }

    return $env;
}

function bazaar_watch_http_get(string $url, int $timeoutSec = 15): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) ? (string) $body : null;
}

function bazaar_watch_send_telegram(string $text): bool {
    $env = bazaar_watch_env();
    $token = $env['GRID402_TELEGRAM_BOT_TOKEN'] ?? '';
    $chat = $env['GRID402_TELEGRAM_CHAT_ID'] ?? '';
    if ($token === '' || $chat === '') {
        bazaar_watch_log('alert: BAZAAR (sem Telegram configurado em .env/GRID402_PRIVATE_DIR — apenas log)');
        return false;
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chat,
            'text' => $text,
            'disable_notification' => 'false',
        ]),
    ]);
    $resp = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    bazaar_watch_log("alert Telegram enviado (HTTP $http): " . substr((string) $resp, 0, 120));
    return ($http >= 200 && $http < 300);
}

// ---------------------------------------------------------------- estado anterior
$nowIso = gmdate('Y-m-d\TH:i:s\Z');
$prevState = bazaar_watch_read_state();
$prevCount = array_key_exists('last_count', $prevState) ? (int) $prevState['last_count'] : null;
$prevHash = (string) ($prevState['last_resources_hash'] ?? '');
$prevStatus = (string) ($prevState['last_status'] ?? 'unknown');

// ---------------------------------------------------------------- fetch
$body = bazaar_watch_http_get(DISCOVERY_URL);
if ($body === null) {
    bazaar_watch_log('error: discovery indisponivel (curl falhou)');
    $shouldAlertOutage = ($prevStatus === 'ok');
    if ($shouldAlertOutage) {
        bazaar_watch_send_telegram("⚠️ Grid402 BAZAAR: endpoint discovery indisponível (antes havia " . ($prevCount ?? 0) . " item(s)).\n\nhttps://grid402.online");
    }
    $prevState['last_status'] = 'error';
    $prevState['last_checked_at'] = $nowIso;
    if ($shouldAlertOutage) {
        $prevState['last_alert_at'] = $nowIso;
    }
    bazaar_watch_write_state($prevState);
    exit(0);
}

$json = json_decode($body, true);
if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
    bazaar_watch_log('error: payload inesperado da discovery: ' . substr($body, 0, 120));
    $shouldAlertBadPayload = ($prevStatus === 'ok');
    if ($shouldAlertBadPayload) {
        bazaar_watch_send_telegram("⚠️ Grid402 BAZAAR: discovery retornou payload inesperado.\n\nhttps://grid402.online");
    }
    $prevState['last_status'] = 'error';
    $prevState['last_checked_at'] = $nowIso;
    if ($shouldAlertBadPayload) {
        $prevState['last_alert_at'] = $nowIso;
    }
    bazaar_watch_write_state($prevState);
    exit(0);
}

$items = $json['items'];
$count = count($items);
$titles = [];
$signatureParts = [];
foreach ($items as $item) {
    $res = is_array($item['resource'] ?? null) ? $item['resource'] : [];
    $resUrl = is_string($item['resource'] ?? null) ? (string) $item['resource'] : (string) ($res['url'] ?? ($item['url'] ?? ''));
    $title = (string) ($res['title'] ?? ($resUrl !== '' ? $resUrl : ($item['title'] ?? '?')));
    $titles[] = '• ' . $title;
    $signatureParts[] = $resUrl !== '' ? $resUrl : $title;
}
sort($signatureParts, SORT_STRING);
$resourcesHash = sha1(implode('|', $signatureParts));

bazaar_watch_log("discovery ok: $count item(s) catalogado(s) (anterior: " . ($prevCount === null ? 'n/a' : (string) $prevCount) . ")");

// ------------------------------------------------------------ deteccao de mudanca / queda
$countChanged = ($prevCount === null) ? ($count > 0) : ($count !== $prevCount);
$catalogChanged = ($prevCount !== null && $count === $prevCount && $prevHash !== '' && $resourcesHash !== $prevHash);
$recoveredFromError = ($prevStatus === 'error');

$shouldNotify = $countChanged || $catalogChanged || $recoveredFromError;

$newState = [
    'pay_to' => PAY_TO,
    'last_count' => $count,
    'last_resources_hash' => $resourcesHash,
    'last_status' => 'ok',
    'last_checked_at' => $nowIso,
    'last_alert_at' => (string) ($prevState['last_alert_at'] ?? ''),
];

if (!$shouldNotify) {
    bazaar_watch_log("sem alteracao (mantido em $count item(s); alerta Telegram suprimido)");
    bazaar_watch_write_state($newState);
    exit(0);
}

if ($prevCount !== null && $count < $prevCount) {
    $headline = "⚠️ Grid402 BAZAAR: queda de {$prevCount} para {$count} item(s)!";
} elseif ($prevCount !== null && $count > $prevCount) {
    $headline = "🎉 Grid402 BAZAAR: discovery passou de {$prevCount} para {$count} item(s)!";
} elseif ($recoveredFromError) {
    $headline = "✅ Grid402 BAZAAR: discovery recuperado com {$count} item(s)!";
} elseif ($catalogChanged) {
    $headline = "🔄 Grid402 BAZAAR: catálogo atualizado ({$count} item(s) com alteração de recursos)!";
} else {
    $headline = "🎉 Grid402 BAZAAR: discovery passou para {$count} item(s)!";
}

$bodyList = !empty($titles) ? ("\n\n" . implode("\n", array_slice($titles, 0, 30))) : '';
$text = $headline . $bodyList . "\n\nhttps://grid402.online";

bazaar_watch_send_telegram($text);
$newState['last_alert_at'] = $nowIso;
bazaar_watch_write_state($newState);
exit(0);
