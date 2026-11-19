<?php
/**
 * Grid402 - Suíte de Testes do Dossiê 360º KYB (/api/v1/dossier) e MCP
 * Valida todos os cenários da spec add-dossier-360-kyb-endpoint.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/dossier.php';
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/helpers_server.php';

echo "=== Testando Dossiê 360º KYB (/api/v1/dossier) e Ferramenta MCP ===\n";

$passed = 0;
function check(bool $cond, string $msg): void {
    global $passed;
    if (!$cond) {
        fwrite(STDERR, "  [FAIL] {$msg}\n");
        exit(1);
    }
    $passed++;
    echo "  [PASS] {$msg}\n";
}

// 1. Testes unitários do motor de veredito determinístico (grid402_dossier_compute_verdict)
echo "\n[1] Validando Motor de Veredito para IA (APPROVED / FLAGGED / REJECTED)...\n";

$vApproved = grid402_dossier_compute_verdict(
    ['situacao_cadastral' => 'ATIVA', 'capital_social' => 1000000],
    ['opcao_pelo_simples' => false, 'opcao_pelo_mei' => false],
    ['has_sanctions' => false, 'total_sanctions' => 0],
    ['score' => 95, 'flags' => []]
);
check($vApproved['status'] === 'APPROVED' && $vApproved['risk_level'] === 'LOW', 'Empresa ativa, sem sanções e score 95 recebe APPROVED / LOW');

$vRejectedSanction = grid402_dossier_compute_verdict(
    ['situacao_cadastral' => 'ATIVA', 'capital_social' => 500000],
    ['opcao_pelo_simples' => false, 'opcao_pelo_mei' => false],
    ['has_sanctions' => true, 'total_sanctions' => 1],
    ['score' => 30, 'flags' => []]
);
check($vRejectedSanction['status'] === 'REJECTED' && $vRejectedSanction['risk_level'] === 'HIGH' && !empty($vRejectedSanction['alerts']), 'Empresa com sanção ativa CGU recebe REJECTED / HIGH com alerta');

$vRejectedInactive = grid402_dossier_compute_verdict(
    ['situacao_cadastral' => 'BAIXADA', 'capital_social' => 500000],
    ['opcao_pelo_simples' => false, 'opcao_pelo_mei' => false],
    ['has_sanctions' => false, 'total_sanctions' => 0],
    ['score' => 40, 'flags' => []]
);
check($vRejectedInactive['status'] === 'REJECTED' && $vRejectedInactive['risk_level'] === 'HIGH', 'Empresa BAIXADA na RFB recebe REJECTED / HIGH');

$vFlaggedScore = grid402_dossier_compute_verdict(
    ['situacao_cadastral' => 'ATIVA', 'capital_social' => 10000],
    ['opcao_pelo_simples' => true, 'opcao_pelo_mei' => false],
    ['has_sanctions' => false, 'total_sanctions' => 0],
    ['score' => 65, 'flags' => []]
);
check($vFlaggedScore['status'] === 'FLAGGED' && $vFlaggedScore['risk_level'] === 'MEDIUM', 'Empresa ativa sem sanções mas score < 70 recebe FLAGGED / MEDIUM');

$vFlaggedCapital = grid402_dossier_compute_verdict(
    ['situacao_cadastral' => 'ATIVA', 'capital_social' => 0],
    ['opcao_pelo_simples' => false, 'opcao_pelo_mei' => false],
    ['has_sanctions' => false, 'total_sanctions' => 0],
    ['score' => 90, 'flags' => []]
);
check($vFlaggedCapital['status'] === 'FLAGGED' && count($vFlaggedCapital['alerts']) >= 1, 'Empresa não-MEI com capital social zerado recebe FLAGGED com alerta');

// 2. Testes unitários de grid402_service_dossier
echo "\n[2] Validando serviço grid402_service_dossier (CNPJ inválido, válido e Zero PII)...\n";

$invalidRes = grid402_service_dossier('12345678000100');
check(($invalidRes['success'] ?? true) === false && ($invalidRes['code'] ?? 0) === 400 && ($invalidRes['error'] ?? '') === 'InvalidCNPJ', 'CNPJ inválido retorna HTTP 400 InvalidCNPJ');

$validRes = grid402_service_dossier('00.000.000/0001-91');
check(($validRes['success'] ?? false) === true && ($validRes['service'] ?? '') === 'dossier', 'CNPJ formatado é normalizado e retorna service=dossier');
check(isset($validRes['verdict']['status'], $validRes['company']['razao_social'], $validRes['tax_regime'], $validRes['sanctions'], $validRes['compliance_score']), 'Payload consolidado contém todos os blocos obrigatórios');

$jsonFlat = json_encode($validRes) ?: '';
check(!str_contains($jsonFlat, '"qsa"') && !str_contains($jsonFlat, '"socios"') && !str_contains($jsonFlat, '"cpf"'), 'Garantia Zero PII: nenhuma chave qsa, socios ou cpf no JSON');

// 3. Testes E2E HTTP via servidor local
echo "\n[3] Subindo servidor HTTP local para testar /api/v1/dossier e /mcp...\n";
$server = grid402_start_server(['router' => __DIR__ . '/router.php']);
$port = $server['port'];
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

// 3.1. HTTP 402 com preço 0.0500 USDC, credits_cost 10 e Teaser
$resp402 = $httpReq("{$baseUrl}/api/v1/dossier?cnpj=00000000000191", ['Accept: application/json']);
check($resp402['code'] === 402, "Sem pagamento retorna HTTP 402 (recebeu {$resp402['code']})");
check(($resp402['json']['price_usdc'] ?? '') === '0.0500', 'Desafio 402 exige exatamente 0.0500 USDC');
check((int)($resp402['json']['price_raw_units'] ?? 0) === 50000, 'Desafio 402 exige price_raw_units = 50000');
check((int)($resp402['json']['credits_cost'] ?? 0) === 10, 'Desafio 402 reporta credits_cost = 10');
check(is_array($resp402['json']['teaser_data'] ?? null) && is_array($resp402['json']['preview'] ?? null), 'Desafio 402 inclui teaser_data e preview');

// 3.2. CNPJ inválido retorna 400 antes de cobrar
$resp400 = $httpReq("{$baseUrl}/api/v1/dossier?cnpj=12345678000100", ['Accept: application/json', 'X-Payment: demo']);
check($resp400['code'] === 400 && ($resp400['json']['error'] ?? '') === 'InvalidCNPJ', "CNPJ inválido via HTTP retorna 400 InvalidCNPJ (recebeu {$resp400['code']})");

// 3.3. Consulta paga via Sandbox (X-Payment: demo) retorna 200 com verdict, _oracle_proof e _meta
$respDemo = $httpReq("{$baseUrl}/api/v1/dossier?cnpj=00.000.000/0001-91", ['Accept: application/json', 'X-Payment: demo']);
check($respDemo['code'] === 200, "Consulta Sandbox retorna HTTP 200 (recebeu {$respDemo['code']})");
check(isset($respDemo['json']['verdict']['status']), 'Resposta HTTP 200 contém bloco verdict.status');
check(isset($respDemo['json']['_oracle_proof']['signature'], $respDemo['json']['_meta']['service_id']), 'Resposta HTTP 200 inclui assinatura _oracle_proof e _meta');

// 3.4. Consulta via Session Pass (X-Session-Pass) debita exatamente 10 créditos
$passesFile = GRID402_FILE_PASSES;
$passesBackup = file_exists($passesFile) ? (string) file_get_contents($passesFile) : null;
$issuedPass = grid402_issue_session_pass('0x' . bin2hex(random_bytes(32)), 100000, false); // 20 créditos exatos
$testPassToken = (string) ($issuedPass['pass_token'] ?? '');
check($testPassToken !== '', 'Session Pass de teste emitido com 20 créditos');

$respPass = $httpReq("{$baseUrl}/api/v1/dossier?cnpj=00000000000191", ['Accept: application/json', "X-Session-Pass: {$testPassToken}"]);
check($respPass['code'] === 200, "Consulta via X-Session-Pass retorna HTTP 200 (recebeu {$respPass['code']})");
check((int)($respPass['json']['_billing']['credits_debited'] ?? 0) === 10, 'Session Pass debita exatamente 10 créditos no _billing');
check((int)($respPass['json']['_billing']['credits_remaining'] ?? -1) === 10, 'Saldo restante no Session Pass é 10 créditos (20 - 10)');

// Restaura arquivo de passes
if ($passesBackup !== null) {
    @file_put_contents($passesFile, $passesBackup, LOCK_EX);
} else {
    @unlink($passesFile);
}

// 3.5. Endpoint remoto MCP /mcp lista grid402_get_dossier_360
$mcpListBody = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object)[]]);
$respMcp = $httpReq("{$baseUrl}/mcp", ['Content-Type: application/json', 'Accept: application/json'], $mcpListBody);
$tools = $respMcp['json']['result']['tools'] ?? [];
$dossierTool = null;
foreach ($tools as $t) {
    if (($t['name'] ?? '') === 'grid402_get_dossier_360') {
        $dossierTool = $t;
        break;
    }
}
check($dossierTool !== null, 'Endpoint remoto /mcp expõe a ferramenta grid402_get_dossier_360');
check(str_contains((string)($dossierTool['description'] ?? ''), '0.0500 USDC'), 'Descrição MCP informa custo de 0.0500 USDC');
check(in_array('cnpj', $dossierTool['inputSchema']['required'] ?? [], true), 'Schema MCP exige parâmetro obrigatório cnpj');

grid402_stop_server($server);

echo "\n=======================================================\n";
echo "Resultado Final: {$passed} testes aprovados, 0 reprovados.\n";
echo "=======================================================\n";
