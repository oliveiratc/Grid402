<?php
/**
 * Testes Unitários e de Integração: Validação Local, Anti-Replay e Liquidação do Facilitador x402
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';

$tmpDir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/facilitator.php';

echo "=== TESTES DE REQUISITOS LOCAIS, NONCES E LIQUIDACAO DO FACILITADOR ===\n\n";

// -----------------------------------------------------------------------------
// 1. Validacao Local de Requisitos (sem acionar o facilitador)
// -----------------------------------------------------------------------------
echo "[1] Validacao Local de Requisitos:\n";

$validDecoded = grid402_decode_x402_payload(t_payload());
t_assert("Payload decodificado com sucesso", $validDecoded['ok']);

$localOk = grid402_validate_exact_requirements($validDecoded, 500);
t_assert("Requisitos validos passam", $localOk['valid']);

// Valor insuficiente
$underpaid = grid402_validate_exact_requirements($validDecoded, 2000);
t_assert("Valor insuficiente rejeitado com 402", !$underpaid['valid'] && $underpaid['code'] === 402);
t_assert("Categoria payment_rejected", $underpaid['category'] === 'payment_rejected');

// Destinatario errado
$wrongToDecoded = grid402_decode_x402_payload(t_payload(['auth' => ['to' => '0x' . str_repeat('00', 20)]]));
$wrongTo = grid402_validate_exact_requirements($wrongToDecoded, 500);
t_assert("Destinatario incorreto rejeitado", !$wrongTo['valid'] && $wrongTo['code'] === 402);

// Rede errada
$wrongNetDecoded = grid402_decode_x402_payload(t_payload(['accepted' => ['network' => 'eip155:1']]));
$wrongNet = grid402_validate_exact_requirements($wrongNetDecoded, 500);
t_assert("Rede incorreta rejeitada", !$wrongNet['valid'] && $wrongNet['code'] === 402);

// Ativo errado
$wrongAssetDecoded = grid402_decode_x402_payload(t_payload(['accepted' => ['asset' => '0x' . str_repeat('cc', 20)]]));
$wrongAsset = grid402_validate_exact_requirements($wrongAssetDecoded, 500);
t_assert("Ativo incorreto rejeitado", !$wrongAsset['valid'] && $wrongAsset['code'] === 402);

// validBefore expirado
$expiredDecoded = grid402_decode_x402_payload(t_payload(['auth' => ['validBefore' => (string)(time() - 10)]]));
$expired = grid402_validate_exact_requirements($expiredDecoded, 500);
t_assert("validBefore expirado rejeitado com authorization_expired", !$expired['valid'] && $expired['category'] === 'authorization_expired');

// validAfter no futuro
$futureDecoded = grid402_decode_x402_payload(t_payload(['auth' => ['validAfter' => (string)(time() + 600)]]));
$future = grid402_validate_exact_requirements($futureDecoded, 500);
t_assert("validAfter no futuro rejeitado", !$future['valid'] && $future['category'] === 'authorization_expired');

// -----------------------------------------------------------------------------
// 2. Anti-Replay por Nonce (used_nonces.json)
// -----------------------------------------------------------------------------
echo "\n[2] Anti-Replay por Nonce:\n";

$payer = '0x' . str_repeat('ee', 20);
$nonce = '0x' . bin2hex(random_bytes(32));
$nonceKey = grid402_nonce_key($payer, $nonce);

t_assert("Primeira reserva de nonce tem sucesso", grid402_claim_nonce($nonceKey) === true);
t_assert("Segunda reserva do mesmo nonce falha (replay)", grid402_claim_nonce($nonceKey) === false);

// Liberacao
grid402_release_nonce($nonceKey);
t_assert("Apos liberacao, nonce pode ser reservado novamente", grid402_claim_nonce($nonceKey) === true);

grid402_set_nonce_state($nonceKey, 'settled');

// -----------------------------------------------------------------------------
// 3. Geracao do JWT CDP
// -----------------------------------------------------------------------------
echo "\n[3] Geracao do JWT CDP:\n";

require_once __DIR__ . '/../public_html/services/oracle_signer.php';
$ecPem = GRID402_DEFAULT_ORACLE_PRIVKEY;

$jwtRes = grid402_cdp_build_jwt('test_key_id', $ecPem, 'POST', 'api.cdp.coinbase.com', '/v2/x402/verify');
t_assert("JWT gerado com sucesso via chave EC", $jwtRes['ok'] === true && !empty($jwtRes['token']));

$jwtParts = explode('.', (string)$jwtRes['token']);
t_assert("JWT possui 3 partes (header.payload.sig)", count($jwtParts) === 3);

$headerJson = json_decode(base64_decode(strtr($jwtParts[0], '-_', '+/')), true);
$claimsJson = json_decode(base64_decode(strtr($jwtParts[1], '-_', '+/')), true);
t_assert("Algoritmo ES256 no header", ($headerJson['alg'] ?? '') === 'ES256');
t_assert("Issuer 'cdp' nos claims", ($claimsJson['iss'] ?? '') === 'cdp');

// -----------------------------------------------------------------------------
// 4. Fluxo Completo com Facilitador Simulado
// -----------------------------------------------------------------------------
echo "\n[4] Fluxo Completo com Facilitador Simulado:\n";

$calls = [];
$handlers = [
    '/v2/x402/verify' => [
        'status' => 200,
        'body' => [
            'isValid' => true,
        ],
    ],
    '/v2/x402/settle' => [
        'status' => 200,
        'body' => [
            'success' => true,
            'transaction' => '0x' . str_repeat('aa', 32),
            'network' => 'eip155:8453',
        ],
    ],
];
t_install_transport($handlers, $calls);

$sampleProof = t_payload();
$res = grid402_validate_exact_payment($sampleProof, 500, '/api/v1/forex');

t_assert("Liquidacao bem-sucedida retorna valid=true", $res['valid'] === true);
t_assert("Codigo HTTP 200", $res['code'] === 200);
t_assert("Origem e 'facilitator'", $res['source'] === 'facilitator');
t_assert("Hash de liquidacao presente", !empty($res['settlement_tx']));
t_assert("Facilitador chamado em /verify e /settle", count($calls) === 2);

// Replay da mesma prova deve ser bloqueado com 403
$resReplay = grid402_validate_exact_payment($sampleProof, 500, '/api/v1/forex');
t_assert("Replay da mesma autorizacao retorna 403", $resReplay['valid'] === false && $resReplay['code'] === 403);
t_assert("Categoria 'replay'", $resReplay['category'] === 'replay');

// Rejeicao do facilitador em /verify
$calls = [];
$handlers['/v2/x402/verify'] = [
    'status' => 200,
    'body' => [
        'isValid' => false,
        'invalidReason' => 'Signature does not match payer',
    ],
];
t_install_transport($handlers, $calls);

$resRejected = grid402_validate_exact_payment(t_payload(), 500, '/api/v1/forex');
t_assert("Rejeicao em verify retorna 402", $resRejected['valid'] === false && $resRejected['code'] === 402);
t_assert("Categoria 'payment_rejected'", $resRejected['category'] === 'payment_rejected');

// Indisponibilidade do facilitador (timeout / 503)
$calls = [];
$handlers['/v2/x402/verify'] = [
    'status' => 500,
    'body' => null,
    'error' => 'Connection timed out',
];
t_install_transport($handlers, $calls);

$resUnavail = grid402_validate_exact_payment(t_payload(), 500, '/api/v1/forex');
t_assert("Indisponibilidade retorna 503", $resUnavail['valid'] === false && $resUnavail['code'] === 503);
t_assert("Categoria 'facilitator_unavailable'", $resUnavail['category'] === 'facilitator_unavailable');

// -----------------------------------------------------------------------------
// 5. Header PAYMENT-RESPONSE
// -----------------------------------------------------------------------------
echo "\n[5] Cabecalho PAYMENT-RESPONSE:\n";

$receiptHeader = grid402_build_payment_response_header($res);
t_assert("PAYMENT-RESPONSE e base64 valido", !empty($receiptHeader));

$decodedReceipt = json_decode((string)base64_decode($receiptHeader), true);
t_assert("Recibo contem success=true", $decodedReceipt['success'] === true);
t_assert("Recibo contem transaction", $decodedReceipt['transaction'] === $res['settlement_tx']);
t_assert("Recibo contem network", $decodedReceipt['network'] === 'eip155:8453');

// -----------------------------------------------------------------------------
// 6. Normalizacao de Discovery (resource + extensions.bazaar) no Settle
// -----------------------------------------------------------------------------
echo "\n[6] Normalizacao de Discovery (resource + extensions.bazaar):\n";

$handlers['/v2/x402/verify'] = ['status' => 200, 'body' => ['isValid' => true]];
$calls = [];
t_install_transport($handlers, $calls);

$summaryProofArr = json_decode((string) base64_decode(t_payload()), true);
$summaryProofArr['resource'] = ['url' => 'https://grid402.online/api/v1/summary', 'serviceName' => 'Grid402 SUMMARY'];
$summaryProofArr['extensions'] = ['bazaar' => ['info' => ['input' => ['type' => 'http', 'method' => 'GET', 'discoverable' => true]]]];
$summaryProof = base64_encode((string) json_encode($summaryProofArr));

$resDns = grid402_validate_exact_payment($summaryProof, 500, '/api/v1/dns?domain=github.com');
t_assert("Settle com payload summary em /api/v1/dns tem sucesso", $resDns['valid'] === true);
$settleCall = $calls[1]['body']['paymentPayload'] ?? [];
t_assert("resource.url normalizado para endpoint real /api/v1/dns sem querystring", ($settleCall['resource']['url'] ?? '') === 'https://grid402.online/api/v1/dns');
t_assert("resource.serviceName corrigido para Grid402 DNS", ($settleCall['resource']['serviceName'] ?? '') === 'Grid402 DNS');
$settleBazaar = $settleCall['extensions']['bazaar'] ?? [];
t_assert("discoverable legado removido de info.input", !array_key_exists('discoverable', $settleBazaar['info']['input'] ?? []));
t_assert("queryParams real extraido da requisicao /api/v1/dns", ($settleBazaar['info']['input']['queryParams']['domain'] ?? '') === 'github.com');
t_assert("schema.input.additionalProperties === false", ($settleBazaar['schema']['properties']['input']['additionalProperties'] ?? null) === false);

// Teste 2: URL relativa no client resource (/api/v1/workdays?...) preservando URL canonica absoluta
$calls = [];
t_install_transport($handlers, $calls);
$relProofArr = json_decode((string) base64_decode(t_payload()), true);
$relProofArr['resource'] = ['url' => '/api/v1/workdays?start_date=2026-12-23&end_date=2026-12-28', 'serviceName' => 'Grid402 WORKDAYS', 'description' => 'Custom 402 description'];
$relProof = base64_encode((string) json_encode($relProofArr));
$resWork = grid402_validate_exact_payment($relProof, 500, '/api/v1/workdays?start_date=2026-12-23&end_date=2026-12-28');
t_assert("Settle com URL relativa em /api/v1/workdays tem sucesso", $resWork['valid'] === true);
$settleWork = $calls[1]['body']['paymentPayload'] ?? [];
t_assert("URL relativa normalizada para canonica absoluta", ($settleWork['resource']['url'] ?? '') === 'https://grid402.online/api/v1/workdays');
t_assert("Descricao customizada preservada quando slug bate", ($settleWork['resource']['description'] ?? '') === 'Custom 402 description');
t_assert("queryParams preservado para workdays", ($settleWork['extensions']['bazaar']['info']['input']['queryParams']['start_date'] ?? '') === '2026-12-23');

// Teste 3: Endpoints sem params (/api/v1/forex) nao podem enviar queryParams.properties=[] para o Ajv do facilitador
$calls = [];
t_install_transport($handlers, $calls);
$forexProofArr = json_decode((string) base64_decode(t_payload()), true);
$forexProofArr['resource'] = ['url' => 'https://grid402.online/api/v1/forex', 'serviceName' => 'Grid402 FOREX'];
$forexProofArr['extensions'] = ['bazaar' => [
    'info' => ['input' => ['type' => 'http', 'method' => 'GET'], 'output' => ['type' => 'json', 'example' => ['status' => 'success']]],
    'schema' => [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'type' => 'object',
        'required' => ['input'],
        'properties' => ['input' => [
            'type' => 'object',
            'required' => ['type', 'method'],
            'properties' => [
                'type' => ['type' => 'string', 'const' => 'http'],
                'method' => ['type' => 'string', 'enum' => ['GET', 'HEAD', 'DELETE']],
                'queryParams' => ['type' => 'object', 'properties' => new stdClass()],
            ],
            'additionalProperties' => false,
        ]],
    ],
]];
$forexProof = base64_encode((string) json_encode($forexProofArr));
$resForex = grid402_validate_exact_payment($forexProof, 500, '/api/v1/forex');
t_assert("Settle em /api/v1/forex sem queryParams tem sucesso", $resForex['valid'] === true);
$settleForexInputProps = $calls[1]['body']['paymentPayload']['extensions']['bazaar']['schema']['properties']['input']['properties'] ?? [];
t_assert("queryParams vazio removido de schema.input.properties em /api/v1/forex", !array_key_exists('queryParams', $settleForexInputProps));

// Limpeza
@unlink(GRID402_FILE_USED_NONCES);

t_finish("test_x402_exact_settlement");
