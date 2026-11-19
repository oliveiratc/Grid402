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
    assert_test(str_contains($resLlms['body'], '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'), "Contem carteira do oraculo");
    assert_test(str_contains($resLlms['body'], '/agents'), "Contem link para /agents");

    // 2. Teste /llms-full.txt
    echo "\n2. Validando /llms-full.txt...\n";
    $resFull = test_http_request("{$baseUrl}/llms-full.txt");
    assert_test($resFull['code'] === 200, "Status HTTP 200 para /llms-full.txt", "Recebido: {$resFull['code']}");
    assert_test(str_contains($resFull['content_type'], 'text/plain'), "Content-Type text/plain para /llms-full.txt", "Recebido: {$resFull['content_type']}");
    assert_test(str_contains($resFull['body'], '_oracle_proof'), "Contem documentacao da prova criptografica de oraculo");
    assert_test(str_contains($resFull['body'], 'consultar_cnpj'), "Contem exemplo de integracao em Python");
    assert_test(str_contains($resFull['body'], '/api/v1/workdays'), "Contem endpoint de dias uteis");

    // 3. Teste /.well-known/mcp.json e /.well-known/mcp/server-card.json
    echo "\n3. Validando /.well-known/mcp.json e /.well-known/mcp/server-card.json...\n";
    $resMcp = test_http_request("{$baseUrl}/.well-known/mcp.json");
    assert_test($resMcp['code'] === 200, "Status HTTP 200 para /.well-known/mcp.json", "Recebido: {$resMcp['code']}");
    $mcpData = json_decode($resMcp['body'], true);
    assert_test(is_array($mcpData), "Manifesto MCP e um JSON valido");
    assert_test(($mcpData['name'] ?? '') === 'grid402-mcp', "Nome do servidor MCP e grid402-mcp");
    assert_test(($mcpData['version'] ?? '') === '1.8.2', "Versao do manifesto MCP sincronizada em 1.8.2", "Recebido: " . ($mcpData['version'] ?? 'null'));
    assert_test(is_string($mcpData['instructions'] ?? null) && str_contains($mcpData['instructions'], 'GRID402_SESSION_PASS'), "Campo instructions presente no manifesto MCP");
    assert_test(isset($mcpData['tools']) and is_array($mcpData['tools']), "Manifesto declara array de tools");
    assert_test(count($mcpData['tools'] ?? []) === 25, "Manifesto MCP declara exatamente 25 tools sincronizadas", "Contagem: " . count($mcpData['tools'] ?? []));

    $toolNames = array_column($mcpData['tools'] ?? [], 'name');
    assert_test(in_array('grid402_summary', $toolNames, true), "Tool grid402_summary presente");
    assert_test(in_array('grid402_cnpj', $toolNames, true), "Tool grid402_cnpj presente");
    assert_test(in_array('grid402_get_dossier_360', $toolNames, true), "Tool grid402_get_dossier_360 presente");
    assert_test(in_array('grid402_indicators', $toolNames, true), "Tool grid402_indicators presente");
    assert_test(in_array('grid402_workdays', $toolNames, true), "Tool grid402_workdays presente");
    assert_test(in_array('grid402_verify', $toolNames, true), "Tool grid402_verify presente");
    assert_test(in_array('grid402_pass_balance', $toolNames, true), "Tool grid402_pass_balance presente");

    $resCard = test_http_request("{$baseUrl}/.well-known/mcp/server-card.json");
    assert_test($resCard['code'] === 200, "Status HTTP 200 para /.well-known/mcp/server-card.json", "Recebido: {$resCard['code']}");
    $cardData = json_decode($resCard['body'], true);
    assert_test(is_array($cardData) && ($cardData['version'] ?? '') === '1.8.2', "Server Card MCP valido e sincronizado em 1.8.2");
    assert_test(count($cardData['tools'] ?? []) === 25, "Server Card MCP declara exatamente 25 tools", "Contagem: " . count($cardData['tools'] ?? []));
    assert_test(is_string($cardData['instructions'] ?? null) && strlen($cardData['instructions']) > 40, "Server Card MCP expoe instructions");

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
    assert_test(str_contains($resAgents['body'], 'Global Data and M2M Utility Infrastructure for AI Agents'), "Titulo e header SSR em ingles presentes");
    assert_test(str_contains($resAgents['body'], 'grid402-mcp'), "Snippet do Claude Desktop MCP presente");
    assert_test(str_contains($resAgents['body'], 'query_oracle'), "Snippet Python para agentes presente");
    assert_test(str_contains($resAgents['body'], 'copySnippet'), "Script interativo de copia presente");
    assert_test(str_contains($resAgents['body'], 'Catalog of All 23 Global Endpoints and Fees'), "Tabela de endpoints e precos presente");

    // 6. Teste de Ausencia do Caractere Comercial Ampersand nos Arquivos
    echo "\n6. Validando conformidade estrita da regra de caracteres...\n";
    $filesToCheck = [
        __DIR__ . '/../public_html/llms.txt',
        __DIR__ . '/../public_html/llms-full.txt',
        __DIR__ . '/../public_html/.well-known/mcp.json',
        __DIR__ . '/../public_html/.well-known/ai-plugin.json',
        __DIR__ . '/../public_html/.well-known/x402.json',
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

    // 7. Teste de Content-Signal em /robots.txt e Schema.org PostalAddress
    echo "\n7. Validando Content-Signal em /robots.txt e Schema.org PostalAddress...\n";
    $chRobots = curl_init("{$baseUrl}/robots.txt");
    curl_setopt_array($chRobots, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 4]);
    $rawRobots = (string) curl_exec($chRobots);
    $robotsCode = (int) curl_getinfo($chRobots, CURLINFO_HTTP_CODE);
    $robotsHdrSize = (int) curl_getinfo($chRobots, CURLINFO_HEADER_SIZE);
    curl_close($chRobots);
    $robotsHdrs = substr($rawRobots, 0, $robotsHdrSize);
    $robotsBody = substr($rawRobots, $robotsHdrSize);
    assert_test($robotsCode === 200, "Status HTTP 200 para /robots.txt");
    assert_test(str_contains($robotsBody, 'Content-Signal: search=yes, ai-input=yes, ai-train=no'), "Diretiva Content-Signal declarada em /robots.txt");
    assert_test(stripos($robotsHdrs, 'Content-Signal: search=yes, ai-input=yes, ai-train=no') !== false, "Cabecalho HTTP Content-Signal emitido em /robots.txt");

    foreach (['/', '/about'] as $schemaRoute) {
        $resHtml = test_http_request("{$baseUrl}{$schemaRoute}", ['Accept: text/html']);
        assert_test($resHtml['code'] === 200, "Status HTTP 200 HTML para {$schemaRoute}");
        $matched = preg_match('#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s', $resHtml['body'], $ldMatch);
        $ldJson = ($matched === 1) ? json_decode($ldMatch[1], true) : null;
        $orgNode = $ldJson['@graph'][0] ?? null;
        assert_test(is_array($orgNode), "JSON-LD estruturado e valido em {$schemaRoute}");
        assert_test(
            is_array($orgNode['address'] ?? null) &&
            ($orgNode['address']['@type'] ?? '') === 'PostalAddress' &&
            ($orgNode['address']['addressLocality'] ?? '') === 'São Paulo' &&
            ($orgNode['address']['addressRegion'] ?? '') === 'SP' &&
            ($orgNode['address']['addressCountry'] ?? '') === 'BR',
            "Business node em {$schemaRoute} contem PostalAddress completo (Sao Paulo, SP, BR)"
        );
    }

    // 8. Teste /.well-known/x402.json e /.well-known/x402 sincronizados com config.php
    echo "\n8. Validando /.well-known/x402.json e /.well-known/x402...\n";
    if (!defined('GRID402_EXEC')) {
        define('GRID402_EXEC', true);
    }
    require_once __DIR__ . '/../public_html/config.php';
    $expectedSvcs = array_filter(grid402_get_utility_services(), static fn(array $s, string $k): bool => $k !== 'verify', ARRAY_FILTER_USE_BOTH);
    foreach (['/.well-known/x402.json', '/.well-known/x402'] as $x402Path) {
        $resX = test_http_request("{$baseUrl}{$x402Path}");
        assert_test($resX['code'] === 200, "Status HTTP 200 para {$x402Path}", "Recebido: {$resX['code']}");
        $xData = json_decode($resX['body'], true);
        assert_test(is_array($xData) && ($xData['x402Version'] ?? 0) === 2, "Manifesto {$x402Path} e x402Version 2 valido");
        if ($x402Path === '/.well-known/x402.json') {
            $resByUrl = [];
            foreach ($xData['resources'] ?? [] as $item) {
                $resByUrl[$item['resource'] ?? ''] = $item['accepts'][0]['amount'] ?? null;
            }
            assert_test(count($resByUrl) === count($expectedSvcs), "Manifesto x402.json lista todos os " . count($expectedSvcs) . " endpoints pagos", "Contagem: " . count($resByUrl));
            $allPricesMatch = true;
            $priceErrors = [];
            foreach ($expectedSvcs as $slug => $svc) {
                $canonicalUrl = "https://grid402.online/api/v1/{$slug}";
                $expectedRaw = (string) round(((float) $svc['price_usdc']) * 1000000);
                if (($resByUrl[$canonicalUrl] ?? null) !== $expectedRaw) {
                    $allPricesMatch = false;
                    $priceErrors[] = "{$slug}: got=" . ($resByUrl[$canonicalUrl] ?? 'missing') . " exp={$expectedRaw}";
                }
            }
            assert_test($allPricesMatch, "Todos os precos raw USDC em x402.json batem 100% com config.php", implode('; ', $priceErrors));
        }
    }

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

