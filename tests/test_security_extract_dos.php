<?php
// Teste de segurança: DoS via robots.txt com excesso de regras e user-agents
define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/services/extract.php';

$passed = 0;
$failed = 0;

function check(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) { $passed++; echo "  [PASS] $msg\n"; }
    else { $failed++; echo "  [FAIL] $msg\n"; }
}

// TC1: robots.txt normal sem limites é parseado corretamente
$normal = "User-agent: *\nDisallow: /private/\nAllow: /\n";
$r1 = grid402_extract_parse_robots_txt($normal);
check(isset($r1['*']), 'robots.txt normal: grupo * presente');
check(count($r1['*']) === 2, 'robots.txt normal: 2 regras para * (Disallow + Allow)');

// TC2: robots.txt com 1000 regras por user-agent deve ser truncado em 500
$manyRules = "User-agent: *\n";
for ($i = 0; $i < 1000; $i++) {
    $manyRules .= "Disallow: /path$i/\n";
}
$r2 = grid402_extract_parse_robots_txt($manyRules);
check(isset($r2['*']), 'robots.txt com 1000 regras: grupo * presente');
check(count($r2['*']) <= 500, 'robots.txt com 1000 regras: truncado em <= 500 (limite DoS)');
check(count($r2['*']) === 500, 'robots.txt com 1000 regras: exatamente 500 regras retidas');

// TC3: robots.txt com 200 user-agents distintos deve ser truncado em 100
$manyAgents = "";
for ($i = 0; $i < 200; $i++) {
    $manyAgents .= "User-agent: bot-$i\nDisallow: /private/\n\n";
}
$r3 = grid402_extract_parse_robots_txt($manyAgents);
check(count($r3) <= 100, 'robots.txt com 200 user-agents: truncado em <= 100');

// TC4: Regra disallow padrão funciona corretamente após limites
$r4 = grid402_extract_parse_robots_txt("User-agent: *\nDisallow: /admin/\n");
$allowed = grid402_extract_robots_is_allowed($r4, '/admin/secret', 'TestBot');
check($allowed === false, 'Regra Disallow /admin/ bloqueia /admin/secret');
$allowed2 = grid402_extract_robots_is_allowed($r4, '/public/', 'TestBot');
check($allowed2 === true, 'Regra Disallow /admin/ permite /public/');

// TC5: CNPJ de teste fictício para validação SSRF
$ssrfCheck = grid402_extract_validate_public_url('http://localhost/test');
check($ssrfCheck['ok'] === false, 'SSRF localhost bloqueado');
check($ssrfCheck['error'] === 'BlockedTargetAddress', 'SSRF: error=BlockedTargetAddress');

$ssrfCheck2 = grid402_extract_validate_public_url('http://169.254.169.254/metadata');
check($ssrfCheck2['ok'] === false, 'SSRF metadata AWS bloqueado (IP privado/reservado)');

echo "\n=== RESULTADO: $passed PASSOU | $failed FALHOU ===\n";
exit($failed > 0 ? 1 : 0);
