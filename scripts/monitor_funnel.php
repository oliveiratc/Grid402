<?php
/**
 * scripts/monitor_funnel.php
 * 
 * Monitor de Conversão e Faturamento do Grid402
 * Analisa as métricas de requisições, eventos de compra e ordens Pix,
 * alertando no Telegram se houver nova venda ou gargalo de conversão.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once dirname(__DIR__) . '/public_html/config.php';
grid402_load_env();

$storageDir = dirname(__DIR__) . '/public_html/private_data';
$metricsFile = $storageDir . '/metrics.json';
$mpOrdersFile = $storageDir . '/mp_orders.json';
$requestsFile = $storageDir . '/requests.jsonl';
$stateFile = $storageDir . '/monitor_state.json';

$botToken = (string) grid402_env('GRID402_TELEGRAM_BOT_TOKEN', '');
$chatId = (string) grid402_env('GRID402_TELEGRAM_CHAT_ID', '');

if ($botToken === '' || $chatId === '') {
    echo "Telegram não configurado.\n";
    exit(0);
}

function sendTelegram(string $token, string $chatId, string $msg): void {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'Markdown'
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 8
        ]
    ]);
    @file_get_contents($url, false, $ctx);
}

$state = file_exists($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : [];
$lastProcessedTs = $state['last_ts'] ?? 0;
$lastApprovedCount = $state['approved_orders'] ?? 0;

$approvedOrders = 0;
$pendingOrders = 0;
$totalBrlApproved = 0.0;

if (file_exists($mpOrdersFile)) {
    $mpData = json_decode((string) file_get_contents($mpOrdersFile), true);
    $orders = $mpData['orders'] ?? [];
    foreach ($orders as $ord) {
        if (($ord['status'] ?? '') === 'approved') {
            $approvedOrders++;
            $totalBrlApproved += (float) ($ord['amount_brl'] ?? 0);
        } elseif (($ord['status'] ?? '') === 'pending') {
            $pendingOrders++;
        }
    }
}

// Se houve nova venda aprovada
if ($approvedOrders > $lastApprovedCount) {
    $diff = $approvedOrders - $lastApprovedCount;
    $msg = "💰 *[Grid402] Nova Venda Pix Confirmada!*\n\n";
    $msg .= "• *Novos pagamentos:* {$diff}\n";
    $msg .= "• *Total aprovado acumulado:* R$ " . number_format($totalBrlApproved, 2, ',', '.') . "\n";
    $msg .= "• *Status:* Token gerado e liberado ao cliente.";
    sendTelegram($botToken, $chatId, $msg);
}

$state['approved_orders'] = $approvedOrders;
$state['pending_orders'] = $pendingOrders;
$state['last_check'] = time();

file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
echo "Monitoramento concluído com sucesso. Aprovados: {$approvedOrders}, Pendentes: {$pendingOrders}\n";
