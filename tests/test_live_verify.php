<?php
declare(strict_types=1);

$liveUrl = 'https://grid402.online';
echo "Testando Hostinger Live: {$liveUrl}\n\n";

// 1. Landing Page
$ch = curl_init($liveUrl . '/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = (string)curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$hasPlayground = str_contains($html, 'id="playground"');
$hasAuditCard = str_contains($html, 'id="playground-audit-card"');
$hasNav = str_contains($html, 'href="#playground"');
echo "1. Landing Page HTML: HTTP {$code}\n";
echo "   - id=\"playground\": " . ($hasPlayground ? "OK" : "FALHA") . "\n";
echo "   - id=\"playground-audit-card\": " . ($hasAuditCard ? "OK" : "FALHA") . "\n";
echo "   - href=\"#playground\": " . ($hasNav ? "OK" : "FALHA") . "\n\n";

// 2. Consulta de Workdays no Sandbox
$ch = curl_init($liveUrl . '/api/v1/workdays?start_date=2026-11-06&days=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'X-Payment: demo']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$workdaysRes = (string)curl_exec($ch);
$workdaysCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$workdaysJson = json_decode($workdaysRes, true);

$hasProof = isset($workdaysJson['_oracle_proof']);
echo "2. Workdays Sandbox: HTTP {$workdaysCode}\n";
echo "   - _oracle_proof presente: " . ($hasProof ? "OK" : "FALHA") . "\n";
if ($hasProof) {
    echo "   - Algoritmo: " . $workdaysJson['_oracle_proof']['algorithm'] . "\n";
    echo "   - Carteira: " . $workdaysJson['_oracle_proof']['oracle_address'] . "\n";
}
echo "\n";

// 3. Verificacao da prova no endpoint /api/v1/verify
$ch = curl_init($liveUrl . '/api/v1/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($workdaysJson));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$verifyRes = (string)curl_exec($ch);
$verifyCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verifyJson = json_decode($verifyRes, true);

echo "3. Endpoint /api/v1/verify (POST):\n";
echo "   - HTTP Code: {$verifyCode}\n";
echo "   - Valido: " . (!empty($verifyJson['valid']) ? "SIM (AUTENTICO)" : "NAO") . "\n";
echo "   - Status: " . ($verifyJson['status'] ?? 'N/A') . "\n";
echo "   - Tempo de Verificacao: " . ($verifyJson['verification_time_ms'] ?? 'N/A') . " ms\n\n";

// 4. Teste de Adulteracao de Dados no Live
$tampered = $workdaysJson;
$tampered['workdays'] = 888;
$ch = curl_init($liveUrl . '/api/v1/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tampered));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$tamperedRes = (string)curl_exec($ch);
$tamperedCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$tamperedJson = json_decode($tamperedRes, true);

echo "4. Deteccao de Adulteracao no Live:\n";
echo "   - HTTP Code: {$tamperedCode}\n";
echo "   - Valido: " . (!empty($tamperedJson['valid']) ? "TRUE" : "FALSE (CORRETO - DETECTOU ADULTERACAO)") . "\n";
echo "   - Erro: " . ($tamperedJson['error'] ?? 'N/A') . "\n\n";

if ($hasPlayground && $hasAuditCard && $hasProof && !empty($verifyJson['valid']) && ($tamperedJson['error'] ?? '') === 'DataHashMismatch') {
    echo "=== SUCESSO TOTAL: PRODUCAO 100% OPERACIONAL E HOMOLOGADA! ===\n";
} else {
    echo "=== FALHA NA VALIDACAO AO VIVO ===\n";
    exit(1);
}

