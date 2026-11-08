<?php
/**
 * Teste de Validação das Integrações de Distribuição:
 * - Manifesto Smithery.ai (smithery.yaml)
 * - Provedor de Ação Coinbase AgentKit em TypeScript e Python
 * - Documentações e Landing Page (public_html/index.php)
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$passCount = 0;
$failCount = 0;

function assertCondition(string $description, bool $condition, ?string $details = null): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description}\n";
        if ($details !== null) {
            echo "         Detalhes: {$details}\n";
        }
    }
}

echo "=== Iniciando Bateria de Testes: Smithery e Coinbase AgentKit ===\n\n";

// 1. Validação do smithery.yaml
echo "[1] Validação do Manifesto Smithery (smithery.yaml):\n";
$smitheryFile = $projectRoot . '/smithery.yaml';
assertCondition("Arquivo smithery.yaml existe na raiz", file_exists($smitheryFile));

if (file_exists($smitheryFile)) {
    $smitheryContent = (string)file_get_contents($smitheryFile);
    assertCondition("Define startCommand com transporte stdio", str_contains($smitheryContent, 'type: stdio'));
    assertCondition("Define comando npx -y grid402-mcp", str_contains($smitheryContent, 'grid402-mcp') && str_contains($smitheryContent, 'npx'));
    assertCondition("Define propriedade sandbox no configSchema", str_contains($smitheryContent, 'sandbox:'));
    assertCondition("Define propriedade privateKey no configSchema", str_contains($smitheryContent, 'privateKey:'));
}

echo "\n";

// 2. Validação do Provedor de Ação Coinbase AgentKit em TypeScript
echo "[2] Validação do Provedor Coinbase AgentKit (TypeScript):\n";
$tsFile = $projectRoot . '/integrations/agentkit/grid402_action_provider.ts';
assertCondition("Arquivo grid402_action_provider.ts existe", file_exists($tsFile));

if (file_exists($tsFile)) {
    $tsContent = (string)file_get_contents($tsFile);
    assertCondition("Exporta a classe Grid402ActionProvider", str_contains($tsContent, 'class Grid402ActionProvider'));
    assertCondition("Implementa suporte a modo sandbox", str_contains($tsContent, 'sandbox: boolean'));
    assertCondition("Intercepta HTTP 402 para auto-liquidacao", str_contains($tsContent, 'res.status === 402'));
    assertCondition("Aciona walletProvider.transfer()", str_contains($tsContent, 'walletProvider.transfer'));
    assertCondition("Possui as 7 acoes essenciais mapeadas", 
        str_contains($tsContent, 'get_summary') &&
        str_contains($tsContent, 'get_forex') &&
        str_contains($tsContent, 'get_crypto') &&
        str_contains($tsContent, 'get_dns') &&
        str_contains($tsContent, 'get_ssl') &&
        str_contains($tsContent, 'get_asn') &&
        str_contains($tsContent, 'get_cep')
    );
}

echo "\n";

// 3. Validação do Provedor de Ação Coinbase AgentKit em Python
echo "[3] Validação do Provedor Coinbase AgentKit (Python):\n";
$pyFile = $projectRoot . '/integrations/agentkit/grid402_action_provider.py';
assertCondition("Arquivo grid402_action_provider.py existe", file_exists($pyFile));

if (file_exists($pyFile)) {
    $pyContent = (string)file_get_contents($pyFile);
    assertCondition("Define a classe Grid402ActionProvider", str_contains($pyContent, 'class Grid402ActionProvider'));
    assertCondition("Trata desafio HTTP 402 com auto-transferencia", str_contains($pyContent, '402') && str_contains($pyContent, 'wallet_provider.transfer'));
    assertCondition("Possui schemas Pydantic para os parametros", str_contains($pyContent, 'BaseModel') && str_contains($pyContent, 'class DnsInput'));
}

echo "\n";

// 4. Validação da Landing Page e Documentações
echo "[4] Validação da Landing Page e Documentações Públicas:\n";
$indexFile = $projectRoot . '/public_html/index.php';
$mcpReadme = $projectRoot . '/mcp/README.md';
$rootReadme = $projectRoot . '/README.md';

assertCondition("index.php contém comando do Smithery", file_exists($indexFile) && str_contains((string)file_get_contents($indexFile), 'install grid402-mcp'));
assertCondition("index.php contém aba do Coinbase AgentKit", file_exists($indexFile) && str_contains((string)file_get_contents($indexFile), 'tab-agentkit'));
assertCondition("mcp/README.md documenta instalacao via Smithery e AgentKit", file_exists($mcpReadme) && str_contains((string)file_get_contents($mcpReadme), 'smithery') && str_contains((string)file_get_contents($mcpReadme), 'AgentKit'));
assertCondition("README.md principal documenta distribuicao Smithery e AgentKit", file_exists($rootReadme) && str_contains((string)file_get_contents($rootReadme), 'smithery.yaml') && str_contains((string)file_get_contents($rootReadme), 'AgentKit'));

echo "\n";

// 5. Verificação da regra estrita: ausência total do caractere & em textos/documentações
echo "[5] Verificação de Ausência do Caractere '&' em Documentações:\n";
$docsToCheck = [
    $smitheryFile,
    $projectRoot . '/integrations/agentkit/README.md',
    $mcpReadme,
    $rootReadme,
];

foreach ($docsToCheck as $doc) {
    if (file_exists($doc)) {
        $content = (string)file_get_contents($doc);
        $basename = basename($doc);
        assertCondition("Arquivo {$basename} não possui o caractere '&'", !str_contains($content, '&'));
    }
}

echo "\n=======================================================\n";
echo "Resultado Final: {$passCount} aprovados, {$failCount} reprovados.\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
