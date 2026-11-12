<?php
/**
 * Testes: classificacao da prova de pagamento e decodificacao do payload x402 padrao (change add-x402-exact-facilitator-payments, tarefa 1.2)
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
$dir = t_bootstrap('settle');

echo "=== TESTES DE CLASSIFICACAO E DECODIFICACAO DO PAYLOAD x402 ===\n\n";

echo "[1] Classificacao do formato da prova:\n";
$hash = '0x' . bin2hex(random_bytes(32));
t_assert('Vazio -> empty', grid402_classify_payment_proof('') === 'empty');
t_assert('pass_g402_ -> pass', grid402_classify_payment_proof('pass_g402_abc_def') === 'pass');
t_assert('DEMO (maiusculas) -> demo', grid402_classify_payment_proof('DEMO') === 'demo');
t_assert('sandbox -> demo', grid402_classify_payment_proof('sandbox') === 'demo');
t_assert('0x + 64 hex -> tx_hash', grid402_classify_payment_proof($hash) === 'tx_hash');
t_assert('Payload base64 -> x402_payload', grid402_classify_payment_proof(t_payload()) === 'x402_payload');
$urlSafe = rtrim(strtr(t_payload(), '+/', '-_'), '=');
t_assert('Payload base64 url-safe -> x402_payload', grid402_classify_payment_proof($urlSafe) === 'x402_payload');
t_assert('Hash malformado -> unknown', grid402_classify_payment_proof('0x123invalido') === 'unknown');
t_assert('Texto qualquer -> unknown', grid402_classify_payment_proof('abc') === 'unknown');
t_assert('Base64 de texto nao JSON -> unknown', grid402_classify_payment_proof(base64_encode(str_repeat('texto livre ', 5))) === 'unknown');
t_assert('Base64 de lista JSON -> unknown', grid402_classify_payment_proof(base64_encode(json_encode(range(1, 40)))) === 'unknown');

echo "\n[2] Decodificacao do payload:\n";
$ok = grid402_decode_x402_payload(t_payload());
t_assert('Payload v2 valido e decodificado', $ok['ok'] === true && $ok['version'] === 2);
t_assert('Rede e ativo normalizados', $ok['network'] === 'eip155:8453' && $ok['asset'] === GRID402_USDC_CONTRACT);
$mixed = grid402_decode_x402_payload(t_payload(['auth' => ['from' => '0x' . strtoupper(str_repeat('ab', 20))]]));
t_assert('Endereco do pagador normalizado em minusculas', $mixed['ok'] && $mixed['authorization']['from'] === '0x' . str_repeat('ab', 20));
$v1 = grid402_decode_x402_payload(t_payload(['version' => 1]));
t_assert('Payload v1 (network "base") valido', $v1['ok'] === true && $v1['version'] === 1 && $v1['network'] === 'base');

$bad = [
    'Sem payload.signature' => ['drop' => ['payload.signature']],
    'Sem payload.authorization' => ['drop' => ['payload.authorization']],
    'Sem authorization.nonce' => ['drop' => ['payload.authorization.nonce']],
    'Sem authorization.value' => ['drop' => ['payload.authorization.value']],
    'Nonce em formato invalido' => ['auth' => ['nonce' => '0x1234']],
    'Valor nao numerico' => ['auth' => ['value' => 'abc']],
    'Sem network (v2 accepted)' => ['drop' => ['accepted.network']],
];
foreach ($bad as $label => $over) {
    $r = grid402_decode_x402_payload(t_payload($over));
    t_assert("{$label} -> rejeitado com mensagem", $r['ok'] === false && !empty($r['error']), json_encode($r));
}
$v3 = grid402_decode_x402_payload(base64_encode(json_encode(['x402Version' => 3, 'payload' => ['x' => 1]])));
t_assert('Versao nao suportada -> rejeitado', $v3['ok'] === false);

echo "\n[3] Formatos legados preservados em grid402_validate_payment:\n";
$invalid = grid402_validate_payment('0x123invalido');
t_assert('Hash malformado -> 400 com categoria invalid_proof', $invalid['valid'] === false && $invalid['code'] === 400 && ($invalid['category'] ?? '') === 'invalid_proof');
t_assert('Mensagem lista os formatos aceitos', str_contains($invalid['reason'], 'PAYMENT-SIGNATURE') && str_contains($invalid['reason'], 'demo'));
$demo = grid402_validate_payment('demo', null, '203.0.113.9');
t_assert('demo continua valido como sandbox', $demo['valid'] === true && !empty($demo['sandbox']));

t_rmdir($dir);
t_finish('RESULTADO FINAL (parsing x402 exact)');

