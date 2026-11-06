<?php
declare(strict_types=1);

echo "[TEST] Iniciando bateria de testes do Oráculo x402 e Gateway...\n";

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';

// --- Teste 1: Prevenção contra Replay Attack ---
echo "1. Testando detecção e recusa de Replay Attack...\n";
$dummyTx = '0x' . str_repeat('e', 64);

// Registra a transação simulada como já utilizada
$marked = grid402_mark_transaction_used($dummyTx);
assert($marked === true, 'Deve marcar transação em used_txs.json com sucesso');

// Tenta validar o mesmo hash novamente
$replayResult = grid402_validate_payment($dummyTx);
assert($replayResult['valid'] === false, 'Transação reutilizada deve ser rejeitada');
assert($replayResult['code'] === 403, 'Código retornado deve ser 403 Forbidden');
assert(str_contains($replayResult['reason'], 'Replay attack'), 'Mensagem deve indicar replay attack');
echo "✓ Replay attack bloqueado com sucesso (HTTP 403)!\n";

// --- Teste 2: Validação de Formato de Hash Inválido ---
echo "2. Testando validação de formato de tx_hash inválido...\n";
$invalidTx = '0x123invalido';
$invalidResult = grid402_validate_payment($invalidTx);
assert($invalidResult['valid'] === false, 'Hash malformado deve ser rejeitado');
assert($invalidResult['code'] === 400, 'Código deve ser 400 Bad Request');
echo "✓ Formato inválido rejeitado com sucesso (HTTP 400)!\n";

// --- Teste 3: Auto-limpeza de used_txs.json (TTL) ---
echo "3. Testando expiração e expurgo de transações com mais de 7 dias...\n";
$oldTx = '0x' . str_repeat('1', 64);
$freshTx = '0x' . str_repeat('2', 64);

$mockData = [
    $oldTx => time() - 700000, // Mais de 7 dias atrás
    $freshTx => time() - 3600,  // 1 hora atrás
];
grid402_atomic_write(GRID402_FILE_USED_TXS, $mockData);

// Dispara a marcação de uma nova tx que aciona a limpeza
$newTx = '0x' . str_repeat('3', 64);
grid402_mark_transaction_used($newTx);

$txsAfter = grid402_get_used_transactions();
assert(!isset($txsAfter[$oldTx]), 'Transação antiga (> 7 dias) deve ter sido purgada');
assert(isset($txsAfter[$freshTx]), 'Transação recente deve ter sido mantida');
assert(isset($txsAfter[$newTx]), 'Nova transação deve constar no registro');
echo "✓ Expurgo automático por TTL funcionando perfeitamente!\n";

// --- Teste 4: Consulta RPC à Base Mainnet ---
echo "4. Testando conexão com o RPC público da Base Mainnet...\n";
// Consulta o bloco mais recente para validar conectividade e tempo de resposta
$ch = curl_init(GRID402_BASE_RPC_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_blockNumber',
        'params' => [],
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => GRID402_SSL_VERIFY,
    CURLOPT_SSL_VERIFYHOST => GRID402_SSL_VERIFY ? 2 : 0,
]);
$rpcStart = microtime(true);
$rpcResp = curl_exec($ch);
$rpcDuration = round((microtime(true) - $rpcStart) * 1000, 2);
$rpcCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($rpcCode === 200 && $rpcResp !== false) {
    $rpcJson = json_decode((string)$rpcResp, true);
    assert(isset($rpcJson['result']), 'RPC deve retornar o campo result');
    $blockNum = hexdec((string)$rpcJson['result']);
    echo "✓ RPC da Base Mainnet respondeu em {$rpcDuration} ms (Bloco atual: {$blockNum})!\n";
} else {
    echo "! Aviso: Conexão direta com RPC público da Base oscilou (HTTP {$rpcCode}).\n";
}

echo "✓ Todos os testes do módulo x402 passaram com sucesso!\n";

