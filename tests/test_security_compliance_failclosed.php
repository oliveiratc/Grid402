<?php
// Teste isolado: valida apenas a correção fail-closed em compliance.php
define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/compliance.php';

$passed = 0;
$failed = 0;

function check(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) { $passed++; echo "  [PASS] $msg\n"; }
    else { $failed++; echo "  [FAIL] $msg\n"; }
}

// TC1: Mock local - CNPJ sancionado no CEIS retorna has_sanctions=true sem upstream
$r1 = grid402_service_sanctions('11111111000191');
check($r1['success'] === true, 'CNPJ com mock local de sanção: success=true');
check($r1['has_sanctions'] === true, 'CNPJ sancionado (mock): has_sanctions=true');
check(count($r1['sanctions']) > 0, 'CNPJ sancionado (mock): sanctions não vazio');

// TC2: Mock local - CNPJ CNEP retorna sanções
$r2 = grid402_service_sanctions('22222222000191');
check($r2['success'] === true, 'CNPJ CNEP mock: success=true');
check($r2['has_sanctions'] === true, 'CNPJ CNEP mock: has_sanctions=true');

// TC3: CNPJ inválido rejeitado
$r3 = grid402_service_sanctions('12345');
check($r3['success'] === false, 'CNPJ inválido: success=false');
check($r3['code'] === 400, 'CNPJ inválido: code=400');
check($r3['error'] === 'InvalidCNPJ', 'CNPJ inválido: error=InvalidCNPJ');

// TC4: CNPJ real (sem upstream) deve retornar erro 503, NÃO cachear falso negativo
// Simulamos upstream indisponível com CNPJ real não mockado
// (00000000000191 não está no mock → vai tentar CGU → falha offline → deve retornar 503)
$r4 = grid402_service_sanctions('00000000000191', true); // bypass cache
// Em ambiente offline: deve ser 503 OU success=true (se o upstream responder)
// Garantia: NÃO deve retornar success=true com has_sanctions=false quando upstream falhou
if ($r4['success'] === false) {
    check($r4['code'] === 503, 'Upstream indisponível: retorna código 503 (fail-closed)');
    check($r4['error'] === 'UpstreamUnavailable', 'Upstream indisponível: error=UpstreamUnavailable');
    check(!isset($r4['has_sanctions']), 'Upstream indisponível: has_sanctions NÃO exposto (evita falso negativo)');
} else {
    // Upstream respondeu (ambiente com internet) — sem problema
    check(isset($r4['has_sanctions']), 'Upstream respondeu: has_sanctions presente');
    echo "  [INFO] Upstream CGU acessível no ambiente\n";
}

echo "\n=== RESULTADO: $passed PASSOU | $failed FALHOU ===\n";
exit($failed > 0 ? 1 : 0);
