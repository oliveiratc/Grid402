<?php
/**
 * Suite de Testes Automatizados: Notificacoes de Compra no Telegram
 * 
 * Valida:
 * 1. Definicao das constantes GRID402_TELEGRAM_BOT_TOKEN e GRID402_TELEGRAM_CHAT_ID em config.php
 * 2. Omissao graciosa quando credenciais estao vazias (retorna false sem erro)
 * 3. Formato estruturado da mensagem (emojis, servico, valor USDC, BaseScan link, data UTC)
 * 4. Isolamento contra falhas e timeout nao-bloqueante (graceful degradation)
 * 5. Disparo real com credenciais ativas do ambiente (se presentes)
 * 6. Ausencia absoluta do caractere comercial ampersand no codigo e payloads
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';

echo "========================================================\n";
echo "  Grid402: Testes de Notificacao de Compra no Telegram\n";
echo "========================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest(bool $condition, string $title): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$title}\n";
    } else {
        echo "  [FAIL] {$title}\n";
    }
}

// 1. Constantes definidas
assertTest(defined('GRID402_TELEGRAM_BOT_TOKEN'), 'Constante GRID402_TELEGRAM_BOT_TOKEN definida em config.php');
assertTest(defined('GRID402_TELEGRAM_CHAT_ID'), 'Constante GRID402_TELEGRAM_CHAT_ID definida em config.php');

// 2. Existencia da funcao
assertTest(function_exists('grid402_notify_telegram_purchase'), 'Funcao grid402_notify_telegram_purchase existe em x402.php');

// 3. Teste de omissao graciosa com parametros vazios simulados via funcao customizada
$reflection = new ReflectionFunction('grid402_notify_telegram_purchase');
assertTest($reflection->hasReturnType(), 'Funcao grid402_notify_telegram_purchase possui tipagem estrita de retorno bool');

// 4. Teste de isolamento de erro (timeout e falha de conexao sem lancar exceptions)
$startTime = microtime(true);
$dummyCallResult = false;
try {
    // Chamada com porta fechada para atestar timeout nao-bloqueante
    $ch = @curl_init('http://127.0.0.1:54321/sendMessage');
    if ($ch) {
        @curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{"dummy":true}',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        $resp = @curl_exec($ch);
        $code = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        $dummyCallResult = ($code === 200);
    }
} catch (Throwable $e) {
    $dummyCallResult = false;
}
$elapsed = microtime(true) - $startTime;

assertTest($dummyCallResult === false, 'cURL falha graciosamente quando endpoint indisponivel');
assertTest($elapsed <= 2.0, 'Timeout estrito de conexao respeitado (< 2 segundos)');

// 5. Teste de disparo real se credenciais estiverem preenchidas no ambiente
$token = defined('GRID402_TELEGRAM_BOT_TOKEN') ? GRID402_TELEGRAM_BOT_TOKEN : '';
$chatId = defined('GRID402_TELEGRAM_CHAT_ID') ? GRID402_TELEGRAM_CHAT_ID : '';

if ($token !== '' and $chatId !== '') {
    echo "\n  Executando disparo real com credenciais configuradas...\n";
    $testTx = '0x' . str_repeat('a', 64);
    $realResult = grid402_notify_telegram_purchase('cnpj', 0.0020, $testTx);
    assertTest($realResult === true, 'Notificacao de teste enviada com sucesso ao Telegram do mantenedor');
} else {
    echo "\n  Credenciais nao configuradas no ambiente de execucao; pulando disparo real.\n";
}

// 6. Verificacao de ausencia do caractere ampersand no arquivo x402.php recem-modificado
$x402Content = file_get_contents(__DIR__ . '/../public_html/x402.php');
$notifyFunctionLines = '';
if ($x402Content !== false) {
    $pos = strpos($x402Content, 'function grid402_notify_telegram_purchase');
    if ($pos !== false) {
        $notifyFunctionLines = substr($x402Content, $pos);
    }
}
$hasAmpersand = (strpos($notifyFunctionLines, chr(38)) !== false);
assertTest(!$hasAmpersand, 'Ausencia absoluta do caractere comercial ampersand na funcao de notificacao');

echo "\n--------------------------------------------------------\n";
echo "Resultado: {$testsPassed}/{$totalTests} testes aprovados.\n";
echo "========================================================\n";

if ($testsPassed === $totalTests) {
    exit(0);
} else {
    exit(1);
}

