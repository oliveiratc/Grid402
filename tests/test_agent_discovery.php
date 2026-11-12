<?php
/**
 * Suite de Testes Automatizados: Descoberta de Agentes e Marketplaces
 * 
 * Valida:
 * 1. GET /llms.txt (HTTP 200, text/plain)
 * 2. GET /llms-full.txt (HTTP 200, text/plain)
 * 3. GET /.well-known/mcp.json (HTTP 200, valid JSON com tools)
 * 4. GET /.well-known/ai-plugin.json (HTTP 200, valid JSON com metadados)
 * 5. GET /agents (HTTP 200, HTML dark terminal com snippets de codigo)
 * 6. Integridade sintatica e ausencia absoluta do caractere comercial ampersand
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_server.php';

echo "========================================================\n";
echo "  Grid402: Testes de Descoberta para Agentes e Marketplaces\n";
echo "========================================================\n\n";

try {
    $server = grid402_start_server([
        'docroot' => __DIR__ . '/../public_html',
        'router' => __DIR__ . '/router.php',
        'probe' => '/llms.txt',
    ]);
} catch (Throwable $e) {
    echo "FALHA: Nao foi possivel iniciar o servidor embutido do PHP: " . $e->getMessage() . "\n";
    exit(1);
}

$baseUrl = $server['base_url'];

function test_http_request(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [
        'code' => $code,
        'body' => (string)$body,
        'content_type' => $contentType
    ];
}

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $title, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$title} - {$details}\n";
        $failed++;
    }
}

try {
    // 1. Teste /llms.txt
    echo "1. Validando /llms.txt...\n";
    $resLlms = test_http_request("{$baseUrl}/llms.txt");
    assert_test($resLlms['code'] === 200, "Status HTTP 200 para /llms.txt", "Recebido: {$resLlms['code']}");
    assert_test(str_contains($resLlms['content_type'], 'text/plain'), "Content-Type text/plain para /llms.txt", "Recebido: {$resLlms['content_type']}");
    assert_test(str_contains($resLlms['body'], 'Grid402'), "Contem nome Grid402");
    assert_test(str_contains($resLlms['body'], '0xa31c31069c43ac1c0075a93c325b0dcd7744356c'), "Contem carteira do oraculo");
    assert_test(str_contains($resLlms['body'], '/agents'), "Contem link para /agents");

    // 2. Teste /llms-full.txt
    echo "\n2. Validando /llms-full.txt...\n";
    $resFull = test_http_request("{$baseUrl}/llms-full.txt");
    assert_test($resFull['code'] === 200, "Status HTTP 200 para /llms-full.txt", "Recebido: {$resFull['code']}");
    assert_test(str_contains($resFull['content_type'], 'text/plain'), "Content-Type text/plain para /llms-full.txt", "Recebido: {$resFull['content_type']}");
    assert_test(str_contains($resFull['body'], '_oracle_proof'), "Contem documentacao da prova criptografica de oraculo");
    assert_test(str_contains($resFull['body'], 'consultar_cnpj'), "Contem exemplo de integracao em Python");
    assert_test(str_contains($resFull['body'], '/api/v1/workdays'), "Contem endpoint de dias uteis");

    // 3. Teste /.well-known/mcp.json
    echo "\n3. Validando /.well-known/mcp.json...\n";
    $resMcp = test_http_request("{$baseUrl}/.well-known/mcp.json");
    assert_test($resMcp['code'] === 200, "Status HTTP 200 para /.well-known/mcp.json", "Recebido: {$resMcp['code']}");
    $mcpData = json_decode($resMcp['body'], true);
    assert_test(is_array($mcpData), "Manifesto MCP e um JSON valido");
    assert_test(($mcpData['name'] ?? '') === 'grid402-mcp', "Nome do servidor MCP e grid402-mcp");
    assert_test(isset($mcpData['tools']) and is_array($mcpData['tools']), "Manifesto declara array de tools");
    
    $toolNames = array_column($mcpData['tools'] ?? [], 'name');
    assert_test(in_array('grid402_summary', $toolNames, true), "Tool grid402_summary presente");
    assert_test(in_array('grid402_cnpj', $toolNames, true), "Tool grid402_cnpj presente");
    assert_test(in_array('grid402_indicators', $toolNames, true), "Tool grid402_indicators presente");
    assert_test(in_array('grid402_workdays', $toolNames, true), "Tool grid402_workdays presente");
    assert_test(in_array('grid402_verify', $toolNames, true), "Tool grid402_verify presente");

    // 4. Teste /.well-known/ai-plugin.json
    echo "\n4. Validando /.well-known/ai-plugin.json...\n";
    $resPlugin = test_http_request("{$baseUrl}/.well-known/ai-plugin.json");
    assert_test($resPlugin['code'] === 200, "Status HTTP 200 para /.well-known/ai-plugin.json", "Recebido: {$resPlugin['code']}");
    $pluginData = json_decode($resPlugin['body'], true);
    assert_test(is_array($pluginData), "Manifesto AI Plugin e um JSON valido");
    assert_test(($pluginData['schema_version'] ?? '') === 'v1', "Schema version v1 presente");
    assert_test(($pluginData['name_for_model'] ?? '') === 'grid402_brazil_data', "name_for_model correto");

    // 5. Teste /agents (Portal Web para Desenvolvedores de Agentes)
    echo "\n5. Validando pagina /agents...\n";
    $resAgents = test_http_request("{$baseUrl}/agents");
    assert_test($resAgents['code'] === 200, "Status HTTP 200 para /agents", "Recebido: {$resAgents['code']}");
    assert_test(str_contains($resAgents['body'], '<!DOCTYPE html>'), "Pagina /agents retorna documento HTML5");
    assert_test(str_contains($resAgents['body'], 'Central de Agentes de IA'), "Titulo e header presentes");
    assert_test(str_contains($resAgents['body'], 'grid402-mcp'), "Snippet do Claude Desktop MCP presente");
    assert_test(str_contains($resAgents['body'], 'consultar_dados'), "Snippet Python para agentes presente");
    assert_test(str_contains($resAgents['body'], 'copySnippet'), "Script interativo de copia presente");
    assert_test(str_contains($resAgents['body'], 'Catalogo de Endpoints'), "Tabela de endpoints e precos presente");

    // 6. Teste de Ausencia do Caractere Comercial Ampersand nos Arquivos
    echo "\n6. Validando conformidade estrita da regra de caracteres...\n";
    $filesToCheck = [
        __DIR__ . '/../public_html/llms.txt',
        __DIR__ . '/../public_html/llms-full.txt',
        __DIR__ . '/../public_html/.well-known/mcp.json',
        __DIR__ . '/../public_html/.well-known/ai-plugin.json',
        __DIR__ . '/../public_html/agents.php',
        __DIR__ . '/../docs/AGENT_MARKETPLACE_SUBMISSIONS.md'
    ];
    $hasAmp = false;
    foreach ($filesToCheck as $filePath) {
        if (!file_exists($filePath)) {
            continue;
        }
        $content = file_get_contents($filePath);
        if (str_contains($content, chr(38))) { // chr(38) e o caractere ampersand
            echo "  [FAIL] Encontrado caractere proibido em: {$filePath}\n";
            $hasAmp = true;
        }
    }
    assert_test(!$hasAmp, "Nenhum arquivo de descoberta contem o caractere ampersand");

} finally {
    // Encerramento do servidor web de teste (mata a arvore de processos)
    grid402_stop_server($server);
}

echo "\n--------------------------------------------------------\n";
echo "Resultado Final: {$passed} passaram, {$failed} falharam.\n";
echo "--------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}

echo "Todos os testes de descoberta de agentes foram concluidos com sucesso!\n";
exit(0);

