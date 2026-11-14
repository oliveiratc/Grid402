<?php
/**
 * Grid402 // Bazaar Watch — verifica a cada execucao se o discovery da
 * PayAI (x402 Bazaar) catalogou o payTo do Grid402.
 *
 * Uso: php scripts/bazaar_watch.php
 *
 * Comportamento:
 *  - Grava no log `scripts/bazaar_watch.log` o estado atual (0 itens / N itens);
 *  - Se ha itens no discovery agora (transicao de 0 para >0): envia mensagem
 *    no Telegram configurado em .env (GRID402_TELEGRAM_BOT_TOKEN/CHAT_ID);
 *  - Se .env nao tiver credenciais de Telegram, apenas registra no log.
 *
 * Agendado via Windows Task Scheduler ("Grid402-BazaarWatch"), 2x ao dia.
 * Remover depois com: schtasks /delete /tn "Grid402-BazaarWatch" /F
 */

declare(strict_types=1);

const PAY_TO = '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';
const DISCOVERY_URL = 'https://facilitator.payai.network/discovery/resources?payTo=' . PAY_TO;

function bazaar_watch_log(string $line): void {
    $log = __DIR__ . '/bazaar_watch.log';
    $ts = gmdate('Y-m-d\TH:i:s\Z');
    file_put_contents($log, "[$ts] $line\n", FILE_APPEND);
}

function bazaar_watch_env(): array {
    $envFile = dirname(__DIR__) . '/.env';
    $env = [];
    if (is_file($envFile)) {
        $raw = file_get_contents($envFile);
        foreach (explode("\n", (string)$raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\"'");
        }
    }
    foreach (['GRID402_TELEGRAM_BOT_TOKEN', 'GRID402_TELEGRAM_CHAT_ID'] as $k) {
        if (($env[$k] ?? '') === '' && getenv($k)) {
            $env[$k] = (string) getenv($k);
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

// ---------------------------------------------------------------- fetch
$body = bazaar_watch_http_get(DISCOVERY_URL);
if ($body === null) {
    bazaar_watch_log('error: discovery indisponivel (curl falhou)');
    exit(0);
}
$json = json_decode($body, true);
if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
    bazaar_watch_log('error: payload inesperado da discovery: ' . substr($body, 0, 120));
    exit(0);
}

$items = $json['items'];
$count = count($items);
bazaar_watch_log("discovery ok: $count item(s) catalogado(s)");

// ------------------------------------------------------------ transition
if ($count === 0) {
    exit(0);
}

$env = bazaar_watch_env();
$token = $env['GRID402_TELEGRAM_BOT_TOKEN'] ?? '';
$chat = $env['GRID402_TELEGRAM_CHAT_ID'] ?? '';
if ($token === '' || $chat === '') {
    bazaar_watch_log('alert: BAZAAR CATALOGOU! (sem Telegram configurado em .env — apenas log)');
    exit(0);
}

$titles = [];
foreach ($items as $item) {
    $title = (string) ($item['resource']['title'] ?? $item['resource'] ?? ($item['title'] ?? '?'));
    $titles[] = '• ' . $title;
}
$text = "🎉 Grid402 BARAAR: discovery passou para $count item(s)!\n\n" . implode("\n", $titles) . "\n\nhttps://grid402.online";

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
bazaar_watch_log("alert Telegram enviado (HTTP $http): " . substr((string)$resp, 0, 120));
exit(0);
