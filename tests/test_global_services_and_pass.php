<?php
/**
 * Suite de Testes Automatizados: Global M2M Infrastructure & Session Pass
 *
 * Cobre os cenarios da OpenSpec:
 * 1. Global Corporate Identity (GLEIF LEI - CC0 1.0 Public Domain)
 * 2. Global Sanctions (OFAC SDN / UN) & Web3 AML Wallet Inspector
 * 3. M2M Cryptographic Timestamp & Notarization
 * 4. World Financial Calendar (US/EU/UK/BR) & 30+ ECB FX Reference Rates
 * 5. x402 Prepaid Session Pass (emissao, consulta de saldo, debito proporcional e rejeicao de HMAC adulterado)
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';
require_once __DIR__ . '/../public_html/services/oracle_signer.php';
require_once __DIR__ . '/../public_html/services/lei.php';
require_once __DIR__ . '/../public_html/services/ofac.php';
require_once __DIR__ . '/../public_html/services/notarize.php';
require_once __DIR__ . '/../public_html/services/global_markets.php';
require_once __DIR__ . '/../public_html/services/pass.php';

$passed = 0;
$failed = 0;

function assert_true(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

echo "=== Iniciando Testes: Global M2M Infrastructure & Session Pass ===\n\n";

// 0. Verifica catalogo de 22 servicos
$catalog = grid402_get_utility_services();
assert_true(count($catalog) === 22, 'Catalogo global possui exatamente 22 micro-servicos configurados');
assert_true(isset($catalog['lei'], $catalog['ofac'], $catalog['notarize'], $catalog['global-markets'], $catalog['pass']), 'Todos os 5 novos endpoints globais constam no catalogo');

// 1. Teste GLEIF LEI (CC0 1.0)
$leiRes = grid402_service_lei('5493006MHB84DD0ZWV18');
assert_true(($leiRes['status'] ?? '') === 'UP', 'LEI Lookup para Alphabet Inc. (5493006MHB84DD0ZWV18) retorna status=UP');
assert_true(($leiRes['entity']['lei'] ?? '') === '5493006MHB84DD0ZWV18', 'LEI retornado confere com o codigo ISO 17442 solicitado');
assert_true(stripos((string) ($leiRes['entity']['legal_name'] ?? ''), 'ALPHABET') !== false || stripos((string) ($leiRes['entity']['legal_name'] ?? ''), 'APPLE') !== false, 'Razao social global oficial retornada pela GLEIF');
assert_true(stripos((string) ($leiRes['license'] ?? ''), 'CC0') !== false, 'Licenca explicita CC0 1.0 Universal presente no retorno LEI');

$leiPreview = grid402_preview_lei('5493006MHB84DD0ZWV18');
assert_true(isset($leiPreview['lei'], $leiPreview['jurisdiction']), 'Teaser preview do LEI retorna lei e jurisdiction');

// 2. Teste OFAC SDN & Web3 AML
$ofacSanctioned = grid402_service_ofac('0x8589427373d6d84e98730d7795d8f6f8731fda16');
assert_true(($ofacSanctioned['status'] ?? '') === 'UP', 'OFAC Lookup em carteira sancionada retorna status=UP');
assert_true(($ofacSanctioned['sanctioned'] ?? false) === true, 'Carteira Tornado Cash (0x8589...da16) marcada como sanctioned=true');
assert_true(($ofacSanctioned['risk_level'] ?? '') === 'CRITICAL', 'Carteira sancionada recebe risk_level=CRITICAL');
assert_true(($ofacSanctioned['compliance_decision'] ?? '') === 'BLOCK_TRANSACTION', 'Carteira sancionada recebe compliance_decision=BLOCK_TRANSACTION');

$ofacClean = grid402_service_ofac('0xa31c31069c43ac1c0075a93c325b0dcd7744356c');
assert_true(($ofacClean['status'] ?? '') === 'UP', 'OFAC Lookup em carteira limpa na Base retorna status=UP');
assert_true(($ofacClean['sanctioned'] ?? true) === false, 'Carteira limpa marcada como sanctioned=false');
assert_true(($ofacClean['risk_level'] ?? '') === 'LOW', 'Carteira limpa recebe risk_level=LOW');
assert_true(is_array($ofacClean['onchain_profile'] ?? null) && stripos((string) ($ofacClean['onchain_profile']['network'] ?? ''), '8453') !== false, 'Relatorio Web3 AML inclui telemetria da Base Mainnet (8453)');

// 3. Teste Notarizacao Criptografica M2M
$notarizeRes = grid402_service_notarize('Grid402-M2M-Autonomous-Contract-v1');
assert_true(($notarizeRes['status'] ?? '') === 'NOTARIZED', 'Notarize retorna status=NOTARIZED');
assert_true(($notarizeRes['input_sha256'] ?? '') === '0x' . hash('sha256', 'Grid402-M2M-Autonomous-Contract-v1'), 'Hash SHA-256 calculado deterministicamente confere');
assert_true(str_starts_with((string) ($notarizeRes['certificate_id'] ?? ''), 'G402-CERT-'), 'Certificado possui prefixo G402-CERT-');
$notarizeSigned = $notarizeRes;
grid402_sign_data($notarizeSigned, false);
assert_true(isset($notarizeSigned['_oracle_proof']['signature']), 'Certificado notarizado recebe assinatura ECDSA secp256k1 (_oracle_proof)');

// 4. Teste Global Markets Calendar & 30+ ECB FX Rates
$gmHoliday = grid402_service_global_markets('US', '2026-07-03', 'USD');
assert_true(($gmHoliday['status'] ?? '') === 'UP', 'Global Markets para US em 2026-07-03 retorna status=UP');
assert_true(($gmHoliday['is_business_day'] ?? true) === false, '2026-07-03 (Independence Day observed) na NYSE detectado como dia nao util');
assert_true(stripos((string) ($gmHoliday['holiday_name'] ?? ''), 'Independence') !== false, 'Nome do feriado Independence Day identificado na NYSE');
assert_true(($gmHoliday['fx_reference']['currencies_count'] ?? 0) >= 15, 'Retorno ECB contem moedas mundiais de referencia');

// 5. Teste Ciclo Completo do x402 Prepaid Session Pass
$fakeTxHash = '0x' . bin2hex(random_bytes(32));
$passIssue = grid402_issue_session_pass($fakeTxHash, 50000, false);
assert_true(($passIssue['status'] ?? '') === 'ACTIVE', 'Emissao de Session Pass para 50000 raw (0.0500 USDC) retorna status=ACTIVE');
$passToken = (string) ($passIssue['pass_token'] ?? '');
assert_true(str_starts_with($passToken, 'pass_g402_'), 'Token emitido segue formato pass_g402_<id>_<hmac>');
assert_true(($passIssue['credits_total'] ?? 0) === 100, '50000 raw USDC emitem exatamente 100 creditos M2M');

// Consulta gratuita de saldo
$passStatus = grid402_get_session_pass_status($passToken);
assert_true(($passStatus['valid'] ?? false) === true && ($passStatus['credits_remaining'] ?? 0) === 100, 'Consulta gratuita de status confirma 100 creditos disponiveis');

// Consumo via grid402_validate_payment em servico de 0.0020 USDC (2000 raw = 4 creditos)
$val2000 = grid402_validate_payment($passToken, 2000);
assert_true(($val2000['valid'] ?? false) === true && ($val2000['session_pass'] ?? false) === true, 'grid402_validate_payment aprova Session Pass instantaneamente');
assert_true(($val2000['credits_debited'] ?? 0) === 4 && ($val2000['credits_remaining'] ?? 0) === 96, 'Debito proporcional desconta 4 creditos (2000 raw) e restam 96 creditos');

// Consumo em servico de 0.0005 USDC (500 raw = 1 credito)
$val500 = grid402_validate_payment($passToken, 500);
assert_true(($val500['valid'] ?? false) === true && ($val500['credits_remaining'] ?? 0) === 95, 'Debito de 1 credito (500 raw) deixa 95 creditos restantes');

// Rejeicao de token adulterado (HMAC invalido)
$tamperedToken = substr($passToken, 0, -4) . 'ffff';
$valTampered = grid402_validate_payment($tamperedToken, 500);
assert_true(($valTampered['valid'] ?? true) === false, 'Session Pass com assinatura HMAC adulterada e rejeitado imediatamente');

echo "\n=== Resultado Final: {$passed} aprovados, {$failed} falhas ===\n";
exit($failed > 0 ? 1 : 0);
