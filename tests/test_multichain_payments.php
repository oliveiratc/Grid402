<?php
/**
 * Grid402 - Suíte de Testes Multi-Chain (5 Redes EVM + Solana SPL USDC)
 * Valida todos os cenários da spec add-multichain-evm-solana-payments.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';
require_once __DIR__ . '/../public_html/services/multichain.php';
require_once __DIR__ . '/../public_html/services/pass_checkout.php';
require_once __DIR__ . '/helpers_server.php';

echo "=== Testando Expansão Cross-Chain e Multimoedas (5 Redes EVM + Solana) ===\n";

$passed = 0;
function mc_check(bool $cond, string $msg): void {
    global $passed;
    if (!$cond) {
        fwrite(STDERR, "  [FAIL] {$msg}\n");
        exit(1);
    }
    $passed++;
    echo "  [PASS] {$msg}\n";
}

// 1. Registro canônico de 6 redes
echo "\n[1] Validando registro canônico multi-chain (6 redes)...\n";
$nets = grid402_supported_networks();
$expectedSlugs = ['base', 'arbitrum', 'polygon', 'optimism', 'ethereum', 'solana'];
mc_check(array_keys($nets) === $expectedSlugs, 'Registro contém exatamente as 6 redes esperadas');
mc_check($nets['solana']['recipient'] === 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C', 'Carteira Solana oficial configurada corretamente');
mc_check($nets['solana']['usdc_contract'] === 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', 'Mint oficial SPL USDC da Solana configurado');
mc_check(grid402_normalize_network_slug('eip155:42161') === 'arbitrum', 'Alias CAIP-2 eip155:42161 normalizado para arbitrum');
mc_check(grid402_normalize_network_slug('sol') === 'solana', 'Alias sol normalizado para solana');
mc_check(grid402_normalize_network_slug('rede_falsa') === null, 'Rede desconhecida retorna null');

// 2. Verificação EVM Multi-Chain (Arbitrum, Polygon, Optimism, Ethereum)
echo "\n[2] Validando verificação EVM ERC-20 Transfer multi-chain...\n";
$transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
$oracleHexNo0x = substr(strtolower(GRID402_WALLET_ADDRESS), 2);
$paddedTo = '0x' . str_pad($oracleHexNo0x, 64, '0', STR_PAD_LEFT);
$paddedFrom = '0x' . str_pad(str_repeat('1', 40), 64, '0', STR_PAD_LEFT);

$recentBlockTs = time() - 120;
$expiredBlockTs = time() - (80 * 3600); // > 72h (GRID402_MAX_TX_AGE_SECONDS)

$arbReceiptOk = [
    'status' => '0x1',
    'blockNumber' => '0x123456',
    'blockTimestamp' => $recentBlockTs,
    'logs' => [
        [
            'address' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831', // Arbitrum Native USDC
            'topics' => [$transferTopic, $paddedFrom, $paddedTo],
            'data' => '0x' . str_pad(dechex(2000000), 64, '0', STR_PAD_LEFT), // 2.00 USDC
        ],
    ],
];
$resArb = grid402_verify_evm_receipt_usdc($arbReceiptOk, 'arbitrum', 2000000);
mc_check(($resArb['valid'] ?? false) === true && ($resArb['network'] ?? '') === 'arbitrum' && ($resArb['raw_amount'] ?? 0) === 2000000, 'Recibo EVM válido na Arbitrum (2.00 USDC nativo) aprovado');

$arbReceiptExpired = $arbReceiptOk;
$arbReceiptExpired['blockTimestamp'] = $expiredBlockTs;
$resArbExp = grid402_verify_evm_receipt_usdc($arbReceiptExpired, 'arbitrum', 2000000);
mc_check(($resArbExp['valid'] ?? true) === false && ($resArbExp['code'] ?? 0) === 403 && ($resArbExp['error'] ?? '') === 'TransactionExpired', 'Recibo EVM com bloco >72h é rejeitado com HTTP 403 TransactionExpired (V-04)');

$arbReceiptNoTs = $arbReceiptOk;
unset($arbReceiptNoTs['blockTimestamp'], $arbReceiptNoTs['blockNumber']);
$resArbNoTs = grid402_verify_evm_receipt_usdc($arbReceiptNoTs, 'arbitrum', 2000000);
mc_check(($resArbNoTs['valid'] ?? true) === false && ($resArbNoTs['code'] ?? 0) === 403 && ($resArbNoTs['error'] ?? '') === 'TransactionExpired', 'Recibo EVM sem timestamp válido é rejeitado fail-closed com HTTP 403 TransactionExpired (V-04)');

$polyUsdceReceiptOk = [
    'status' => '0x1',
    'blockNumber' => '0x654321',
    'blockTimestamp' => $recentBlockTs,
    'logs' => [
        [
            'address' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', // Polygon USDC.e oficial Circle
            'topics' => [$transferTopic, $paddedFrom, $paddedTo],
            'data' => '0x' . str_pad(dechex(5000000), 64, '0', STR_PAD_LEFT), // 5.00 USDC
        ],
    ],
];
$resPoly = grid402_verify_evm_receipt_usdc($polyUsdceReceiptOk, 'polygon', 5000000);
mc_check(($resPoly['valid'] ?? false) === true && ($resPoly['network'] ?? '') === 'polygon' && ($resPoly['raw_amount'] ?? 0) === 5000000, 'Recibo EVM válido na Polygon (5.00 USDC.e oficial) aprovado');

$fakeTokenReceipt = [
    'status' => '0x1',
    'logs' => [
        [
            'address' => '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            'topics' => [$transferTopic, $paddedFrom, $paddedTo],
            'data' => '0x' . str_pad(dechex(99999999), 64, '0', STR_PAD_LEFT),
        ],
    ],
];
$resFake = grid402_verify_evm_receipt_usdc($fakeTokenReceipt, 'polygon', 5000);
mc_check(($resFake['valid'] ?? true) === false && ($resFake['error'] ?? '') === 'InsufficientOrMissingUsdcTransfer', 'Recibo EVM de contrato fraudulento é rejeitado');

$failedReceipt = ['status' => '0x0', 'logs' => []];
$resFailedEvm = grid402_verify_evm_receipt_usdc($failedReceipt, 'optimism', 5000);
mc_check(($resFailedEvm['valid'] ?? true) === false && ($resFailedEvm['error'] ?? '') === 'TransactionFailedOnChain', 'Recibo EVM com status 0x0 é rejeitado');

// 3. Verificação Nativa Solana SPL USDC
echo "\n[3] Validando verificação Solana SPL USDC (pre/postTokenBalances)...\n";
$solOwner = 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C';
$solMint = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';

$solTxOk = [
    'slot' => 310000000,
    'blockTime' => $recentBlockTs,
    'meta' => [
        'err' => null,
        'preTokenBalances' => [
            ['accountIndex' => 2, 'mint' => $solMint, 'owner' => $solOwner, 'uiTokenAmount' => ['amount' => '1000000', 'decimals' => 6]],
        ],
        'postTokenBalances' => [
            ['accountIndex' => 2, 'mint' => $solMint, 'owner' => $solOwner, 'uiTokenAmount' => ['amount' => '6000000', 'decimals' => 6]],
        ],
    ],
];
$resSolOk = grid402_verify_solana_tx_usdc($solTxOk, 5000000);
mc_check(($resSolOk['valid'] ?? false) === true && ($resSolOk['network'] ?? '') === 'solana' && ($resSolOk['raw_amount'] ?? 0) === 5000000, 'Transação Solana SPL USDC válida (+5.00 USDC delta) aprovada');

$solTxExpired = $solTxOk;
$solTxExpired['blockTime'] = $expiredBlockTs;
$resSolExp = grid402_verify_solana_tx_usdc($solTxExpired, 5000000);
mc_check(($resSolExp['valid'] ?? true) === false && ($resSolExp['code'] ?? 0) === 403 && ($resSolExp['error'] ?? '') === 'TransactionExpired', 'Transação Solana com blockTime >72h é rejeitada com HTTP 403 TransactionExpired (V-04)');

$solTxNoTime = $solTxOk;
unset($solTxNoTime['blockTime']);
$resSolNoTime = grid402_verify_solana_tx_usdc($solTxNoTime, 5000000);
mc_check(($resSolNoTime['valid'] ?? true) === false && ($resSolNoTime['code'] ?? 0) === 403 && ($resSolNoTime['error'] ?? '') === 'TransactionExpired', 'Transação Solana sem blockTime é rejeitada fail-closed com HTTP 403 TransactionExpired (V-04)');

$solTxNewAta = [
    'slot' => 310000001,
    'blockTime' => $recentBlockTs,
    'meta' => [
        'err' => null,
        'preTokenBalances' => [],
        'postTokenBalances' => [
            ['accountIndex' => 3, 'mint' => $solMint, 'owner' => $solOwner, 'uiTokenAmount' => ['amount' => '2000000', 'decimals' => 6]],
        ],
    ],
];
$resSolAta = grid402_verify_solana_tx_usdc($solTxNewAta, 2000000);
mc_check(($resSolAta['valid'] ?? false) === true && ($resSolAta['raw_amount'] ?? 0) === 2000000, 'Transação Solana criando nova ATA (+2.00 USDC) aprovada');

$solTxErr = ['slot' => 310000002, 'meta' => ['err' => ['InstructionError' => [0, 'Custom']], 'preTokenBalances' => [], 'postTokenBalances' => []]];
$resSolErr = grid402_verify_solana_tx_usdc($solTxErr, 5000);
mc_check(($resSolErr['valid'] ?? true) === false && ($resSolErr['error'] ?? '') === 'TransactionFailedOnChain', 'Transação Solana com meta.err != null é rejeitada');

$solTxFakeMint = [
    'slot' => 310000003,
    'meta' => [
        'err' => null,
        'preTokenBalances' => [],
        'postTokenBalances' => [
            ['accountIndex' => 1, 'mint' => 'FakeMint11111111111111111111111111111111111', 'owner' => $solOwner, 'uiTokenAmount' => ['amount' => '99000000', 'decimals' => 6]],
        ],
    ],
];
$resSolFake = grid402_verify_solana_tx_usdc($solTxFakeMint, 5000);
mc_check(($resSolFake['valid'] ?? true) === false && ($resSolFake['error'] ?? '') === 'InsufficientOrMissingUsdcTransfer', 'Transação Solana de mint falso é rejeitada');

// 4. Parsing de Rede, Anti-Replay Key e Explorers
echo "\n[4] Validando parsing de rede, chaves anti-replay e exploradores...\n";
$sampleHash = '0x' . str_repeat('a', 64);
$sampleSolSig = str_repeat('5', 87);

$pBase = grid402_parse_network_proof($sampleHash);
mc_check($pBase['network'] === 'base' && $pBase['lock_key'] === $sampleHash, 'Hash 0x sem prefixo cai para Base com chave legada retrocompatível');

$pArb = grid402_parse_network_proof('arbitrum:' . $sampleHash);
mc_check($pArb['network'] === 'arbitrum' && $pArb['tx_hash'] === $sampleHash && $pArb['lock_key'] === 'arbitrum:' . $sampleHash, 'Prefixo arbitrum:0x... resolve rede e chave anti-replay isolada');

$pHdr = grid402_parse_network_proof($sampleHash, 'polygon');
mc_check($pHdr['network'] === 'polygon' && $pHdr['lock_key'] === 'polygon:' . $sampleHash, 'Cabeçalho X-Payment-Network: polygon resolve rede e chave isolada');

$pSolAuto = grid402_parse_network_proof($sampleSolSig);
mc_check($pSolAuto['network'] === 'solana' && $pSolAuto['tx_hash'] === $sampleSolSig && $pSolAuto['lock_key'] === 'solana:' . $sampleSolSig, 'Assinatura Base58 é auto-detectada como Solana preservando case-sensitivity');

mc_check(str_starts_with(grid402_explorer_tx_url('arbitrum', $sampleHash), 'https://arbiscan.io/tx/'), 'Explorer Arbitrum aponta para Arbiscan');
mc_check(str_starts_with(grid402_explorer_tx_url('solana', $sampleSolSig), 'https://solscan.io/tx/'), 'Explorer Solana aponta para Solscan');

// 5. Testes E2E HTTP via servidor local
echo "\n[5] Subindo servidor HTTP local para testar HTTP 402, /api/v1/pass-emitir e UI /pass...\n";
$server = grid402_start_server(['router' => __DIR__ . '/router.php']);
$baseUrl = $server['base_url'];

$httpReq = static function (string $url, array $headers = [], ?string $postBody = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($postBody !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $postBody;
    }
    curl_setopt_array($ch, $opts);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'json' => @json_decode($body, true)];
};

// 5.1. Desafio HTTP 402 lista as 6 redes em supported_networks
$r402 = $httpReq("{$baseUrl}/api/v1/forex", ['Accept: application/json']);
mc_check($r402['code'] === 402, "Chamada sem pagamento retorna HTTP 402 (recebeu {$r402['code']})");
$supNets = $r402['json']['supported_networks'] ?? [];
mc_check(count($supNets) === 6 && isset($supNets['base'], $supNets['arbitrum'], $supNets['polygon'], $supNets['optimism'], $supNets['ethereum'], $supNets['solana']), 'HTTP 402 anuncia todas as 6 redes em supported_networks');
mc_check(($supNets['solana']['recipient_wallet'] ?? '') === 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C', 'HTTP 402 expõe carteira Solana oficial');

// 5.2. /api/v1/pass-emitir rejeita rede não suportada com HTTP 400 UnsupportedNetwork
$rBadChain = $httpReq(
    "{$baseUrl}/api/v1/pass-emitir",
    ['Content-Type: application/json', 'Accept: application/json'],
    json_encode(['txHash' => $sampleHash, 'chain' => 'rede_inexistente'])
);
mc_check($rBadChain['code'] === 400 && ($rBadChain['json']['error'] ?? '') === 'UnsupportedNetwork', "POST /api/v1/pass-emitir com rede inválida retorna HTTP 400 UnsupportedNetwork (recebeu {$rBadChain['code']})");

// 5.3. Chamada direta com X-Payment-Network inválido retorna HTTP 400 UnsupportedNetwork
$rBadHdr = $httpReq("{$baseUrl}/api/v1/forex", ['Accept: application/json', "X-Payment: {$sampleHash}", 'X-Payment-Network: rede_inexistente']);
mc_check($rBadHdr['code'] === 400 && ($rBadHdr['json']['error'] ?? '') === 'UnsupportedNetwork', "Chamada direta com X-Payment-Network inválido retorna HTTP 400 UnsupportedNetwork (recebeu {$rBadHdr['code']})");

// 5.4. Página /pass renderiza seletor das 6 redes e endereço Solana
$rPassHtml = $httpReq("{$baseUrl}/pass", ['Accept: text/html']);
mc_check($rPassHtml['code'] === 200, "GET /pass retorna HTTP 200 (recebeu {$rPassHtml['code']})");
mc_check(
    str_contains($rPassHtml['body'], 'selectPassNetwork(\'arbitrum\')')
    && str_contains($rPassHtml['body'], 'selectPassNetwork(\'polygon\')')
    && str_contains($rPassHtml['body'], 'selectPassNetwork(\'optimism\')')
    && str_contains($rPassHtml['body'], 'selectPassNetwork(\'ethereum\')')
    && str_contains($rPassHtml['body'], 'selectPassNetwork(\'solana\')')
    && str_contains($rPassHtml['body'], 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C'),
    'Página /pass renderiza seletor das 6 redes e exibe endereço Solana oficial'
);

// 5.5. Página inicial / (#topup) renderiza seletor das 6 redes
$rHomeHtml = $httpReq("{$baseUrl}/", ['Accept: text/html']);
mc_check(
    $rHomeHtml['code'] === 200
    && str_contains($rHomeHtml['body'], 'topup-network-selector')
    && str_contains($rHomeHtml['body'], 'selectTopupNetwork(\'solana\')')
    && str_contains($rHomeHtml['body'], 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C'),
    'Página inicial / (#topup) renderiza seletor visual das 6 redes incluindo Solana'
);

grid402_stop_server($server);

echo "\n=======================================================\n";
echo "Resultado Final: {$passed} testes aprovados, 0 reprovados.\n";
echo "=======================================================\n";
