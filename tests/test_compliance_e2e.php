<?php
/**
 * Teste E2E HTTP: Novos Endpoints de Compliance e Antifraude
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';

echo "=== Homologando Endpoints HTTP de Compliance e Antifraude ===\n\n";

// Inicia servidor PHP embutido para testes
$host = '127.0.0.1';
$port = 8899;
$docRoot = realpath(__DIR__ . '/../public_html');

$cmd = sprintf('php -S %s:%d -t %s', $host, $port, escapeshellarg($docRoot));
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    echo "Erro ao iniciar servidor local de teste.\n";
    exit(1);
}

// Aguarda subida do servidor
usleep(500000);

$baseUrl = "http://{$host}:{$port}";
$passed = 0;
$total = 0;

function assertE2E(bool $condition, string $msg): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  ✓ {$msg}\n";
    } else {
        echo "  ✗ FALHA: {$msg}\n";
    }
}

try {
    // 1. Teste Desafio HTTP 402 para /api/v1/sanctions
    echo "[1/4] Testando desafio 402 em /api/v1/sanctions...\n";
    $ctx402 = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n"
        ]
    ]);
    $res402 = file_get_contents("{$baseUrl}/api/v1/sanctions?cnpj=00000000000191", false, $ctx402);
    $httpCode = 0;
    if (isset($http_response_header[0])) {
        if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
    }
    assertE2E($httpCode === 402, "Endpoint /api/v1/sanctions emite HTTP 402 Payment Required (Código recebido: {$httpCode})");
    $json402 = json_decode($res402 ?: '{}', true);
    assertE2E(isset($json402['price_usdc']), 'Desafio 402 contém campo price_usdc');
    assertE2E($json402['price_usdc'] === '0.0010', 'Preço escalonado de 0.0010 USDC para sanções');
    assertE2E(isset($json402['preview']['tipo_consulta']), 'Prévia de dados presente no desafio 402');

    // 2. Teste Execução no Sandbox para /api/v1/sanctions
    echo "\n[2/4] Testando execução em modo sandbox para /api/v1/sanctions...\n";
    $ctxDemo = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nX-Payment: demo\r\n"
        ]
    ]);
    $resDemo = file_get_contents("{$baseUrl}/api/v1/sanctions?cnpj=00000000000191", false, $ctxDemo);
    $jsonDemo = json_decode($resDemo ?: '{}', true);
    assertE2E(($jsonDemo['success'] ?? false) === true, 'Sandbox retornou success: true para sanções');
    assertE2E($jsonDemo['has_sanctions'] === false, 'Entidade limpa sem sanções no payload sandbox');
    assertE2E(isset($jsonDemo['_oracle_proof']['signature']), 'Carimbo criptográfico secp256k1 presente');

    // 3. Teste Execução no Sandbox para /api/v1/simples
    echo "\n[3/4] Testando execução em modo sandbox para /api/v1/simples...\n";
    $resSimples = file_get_contents("{$baseUrl}/api/v1/simples?cnpj=00000000000191", false, $ctxDemo);
    $jsonSimples = json_decode($resSimples ?: '{}', true);
    assertE2E(($jsonSimples['success'] ?? false) === true, 'Sandbox retornou success: true para simples');
    assertE2E(isset($jsonSimples['situacao_atual']), 'Campo situacao_atual presente no retorno do simples');
    assertE2E(isset($jsonSimples['_oracle_proof']['signature']), 'Prova de oráculo presente no retorno do simples');

    // 4. Teste Execução e Desafio do Score 360 /api/v1/compliance
    echo "\n[4/4] Testando Compliance Score 360 em /api/v1/compliance...\n";
    $resScore402 = file_get_contents("{$baseUrl}/api/v1/compliance?cnpj=00000000000191", false, $ctx402);
    $jsonScore402 = json_decode($resScore402 ?: '{}', true);
    assertE2E(($jsonScore402['price_usdc'] ?? '') === '0.0030', 'Preço de 0.0030 USDC configurado para Compliance Score');

    $resScore = file_get_contents("{$baseUrl}/api/v1/compliance?cnpj=00000000000191", false, $ctxDemo);
    $jsonScore = json_decode($resScore ?: '{}', true);
    assertE2E(($jsonScore['success'] ?? false) === true, 'Score 360 retornou success: true');
    assertE2E($jsonScore['status'] === 'APROVADO', 'Empresa idônea ativa aprovada');
    assertE2E($jsonScore['score'] >= 85, 'Score de integridade elevado (> 85)');

    $resScoreBad = file_get_contents("{$baseUrl}/api/v1/compliance?cnpj=11111111000191", false, $ctxDemo);
    $jsonScoreBad = json_decode($resScoreBad ?: '{}', true);
    assertE2E($jsonScoreBad['status'] === 'REJEITADO', 'Empresa com sanções ativas rejeitada no score 360');
    assertE2E($jsonScoreBad['score'] < 50, 'Score de empresa sancionada inferior a 50 pontos');

} finally {
    // Encerra servidor
    if (is_resource($process)) {
        proc_terminate($process);
    }
}

echo "\n--------------------------------------------------\n";
echo "Resultado E2E: {$passed} de {$total} asserções validadas com sucesso.\n";

if ($passed === $total) {
    echo "Status: SUCESSO E2E TOTAL (100% aprovado)\n";
    exit(0);
} else {
    echo "Status: FALHA NO TESTE E2E\n";
    exit(1);
}

