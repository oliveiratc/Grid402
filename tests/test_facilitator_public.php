<?php
/**
 * Grid402 - Ent-to-end local do perfil "Sepolia + facilitador publico x402.org"
 * (homologacao). Nenhum trafego de rede e feito: o transporte do facilitador e simulado.
 *
 * Verifica, sem tocar a rede, que:
 * - o aceite do servidor vira eip155:84532 + USDC Sepolia com GRID402_X402_NETWORK=base-sepolia;
 * - caminhos customizados /verify e /settle sao respeitados;
 * - nenhuma chamada carrega o cabecalho Authorization;
 * - o fluxo completo settle retorna valid=true.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';

$dir = t_bootstrap('settle', null, [
    'GRID402_X402_NETWORK' => 'base-sepolia',
    'GRID402_FACILITATOR_URL' => 'https://x402.org/facilitator',
    'GRID402_FACILITATOR_VERIFY_PATH' => '/verify',
    'GRID402_FACILITATOR_SETTLE_PATH' => '/settle',
    'GRID402_CDP_API_KEY_ID' => '',
    'GRID402_CDP_API_KEY_SECRET' => '',
]);

echo "=== Teste do Perfil Sepolia + Facilitador Publico (homologacao) ===\n";

echo "\n[1] Constantes de rede (opt-in GRID402_X402_NETWORK=base-sepolia):\n";
t_assert("GRID402_X402_NETWORK e base-sepolia", GRID402_X402_NETWORK === 'base-sepolia');
t_assert("GRID402_X402_CHAIN_ID e 84532", GRID402_X402_CHAIN_ID === 84532);
t_assert("GRID402_X402_NETWORK_V2 e eip155:84532", GRID402_X402_NETWORK_V2 === 'eip155:84532');
t_assert("GRID402_X402_NETWORK_V1 e base-sepolia", GRID402_X402_NETWORK_V1 === 'base-sepolia');
t_assert("USDC Sepolia correto", GRID402_X402_USDC_CONTRACT === '0x036cbd53842c5426634e7929541ec2318f3dcf7e');
t_assert("auth_mode retorna 'public' sem credenciais CDP", grid402_facilitator_auth_mode() === 'public');

echo "\n[2] Fluxo settle via /verify e /settle (transporte simulado):\n";
$calls = [];
$handlers = [
    '/verify' => [
        'status' => 200,
        'body' => ['isValid' => true],
    ],
    '/settle' => [
        'status' => 200,
        'body' => [
            'success' => true,
            'transaction' => '0x' . str_repeat('bb', 32),
            'network' => 'eip155:84532',
        ],
    ],
];
t_install_transport($handlers, $calls);

$sampleProof = t_payload(['accepted' => [
    'scheme' => 'exact',
    'network' => 'eip155:84532',
    'asset' => GRID402_X402_USDC_CONTRACT,
    'amount' => '500',
    'payTo' => GRID402_WALLET_ADDRESS,
]]);

$res = grid402_validate_exact_payment($sampleProof, 500, '/api/v1/crypto');

t_assert("Liquidacao bem-sucedida retorna valid=true", $res['valid'] === true);
t_assert("Codigo HTTP 200", $res['code'] === 200);
t_assert("Origem e 'facilitator'", ($res['source'] ?? '') === 'facilitator');
t_assert("Chamou /verify e /settle (2 chamadas)", count($calls) === 2);
t_assert("Primeira chamada foi /verify", ($calls[0]['endpoint'] ?? '') === '/verify');
t_assert("Segunda chamada foi /settle", ($calls[1]['endpoint'] ?? '') === '/settle');

$noAuth = true;
foreach ($calls as $call) {
    foreach ($call['headers'] as $header) {
        if (stripos((string) $header, 'authorization') === 0) {
            $noAuth = false;
        }
    }
}
t_assert("Nenhuma chamada carrega o cabecalho Authorization", $noAuth);

echo "\n[3] Requisitos construidos para o facilitador (fonte da verdade do servidor):\n";
$reqV2 = grid402_build_payment_requirements(2, 500, 'https://grid402.online/api/v1/crypto');
t_assert("Requirements v2 usam eip155:84532", $reqV2['network'] === 'eip155:84532');
t_assert("Requirements v2 usam USDC Sepolia", strtolower($reqV2['asset']) === GRID402_X402_USDC_CONTRACT);
t_assert("Requirements v2 valor correto", $reqV2['amount'] === '500');

echo "\n[4] auth_mode sob transporte simulado:\n";
t_assert("auth_mode retorna 'override' com transporte injetado", grid402_facilitator_auth_mode() === 'override');

t_rmdir($dir);
t_finish('perfil sepolia + facilitador publico');
