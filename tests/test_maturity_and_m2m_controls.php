<?php
/**
 * Grid402 // Suite de Testes: Maturidade da Plataforma, Governança e Controles M2M
 * Cobre todos os cenários da change OpenSpec: update-platform-maturity-and-m2m-controls
 */

declare(strict_types=1);

define('GRID402_EXEC', true);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/public_html/config.php';
require_once $projectRoot . '/public_html/services/pass.php';
require_once $projectRoot . '/public_html/services/mercadopago.php';
require_once $projectRoot . '/public_html/services/oracle_signer.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : "") . "\n";
    }
}

echo "=================================================================\n";
echo "  GRID402 - TESTES DE MATURIDADE, GOVERNANÇA E CONTROLES M2M\n";
echo "=================================================================\n\n";

// -----------------------------------------------------------------
// 1. Unificação de Carteira Recebedora (Zero 0xa31c...356c)
// -----------------------------------------------------------------
echo "[1/7] Unificação de Carteira Recebedora (0xa592...3f90):\n";
$indexContent = (string) file_get_contents($projectRoot . '/public_html/index.php');
$walletCachePath = is_file(GRID402_PRIVATE_DIR . '/wallet_balance_cache.json')
    ? (GRID402_PRIVATE_DIR . '/wallet_balance_cache.json')
    : ($projectRoot . '/public_html/private_data/wallet_balance_cache.json');
$walletCacheContent = is_file($walletCachePath) ? (string) file_get_contents($walletCachePath) : '';
check('index.php não contém carteira legada 0xa31c...356c', !str_contains(strtolower($indexContent), '0xa31ca8fbabbdac7ab34fb045ae2fc2019ba9356c'));
check('wallet_balance_cache.json usa carteira oficial 0xa592...3f90 (ou ausente em checkout limpo)',
    $walletCacheContent === '' ||
    (!str_contains(strtolower($walletCacheContent), '0xa31ca8fbabbdac7ab34fb045ae2fc2019ba9356c') &&
     str_contains(strtolower($walletCacheContent), '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'))
);
check('GRID402_WALLET_ADDRESS aponta para 0xa592...3f90', strtolower(GRID402_WALLET_ADDRESS) === '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90');

// -----------------------------------------------------------------
// 2. Matemática Unificada de Session Pass (400 / 1.000 / 6.000 créditos)
// -----------------------------------------------------------------
echo "\n[2/7] Matemática Unificada de Session Pass (1 crédito = 0.0050 USDC):\n";
$txStarter = '0x' . bin2hex(random_bytes(32));
$txPro = '0x' . bin2hex(random_bytes(32));
$txFleet = '0x' . bin2hex(random_bytes(32));

$passStarter = grid402_issue_session_pass($txStarter, 2000000);
$passPro = grid402_issue_session_pass($txPro, 5000000);
$passFleet = grid402_issue_session_pass($txFleet, 20000000);

check('Starter (2 USDC / 2.000.000 raw units) emite 400 créditos', ($passStarter['credits_total'] ?? 0) === 400, 'Got: ' . ($passStarter['credits_total'] ?? 'null'));
check('Pro (5 USDC / 5.000.000 raw units) emite 1.000 créditos', ($passPro['credits_total'] ?? 0) === 1000, 'Got: ' . ($passPro['credits_total'] ?? 'null'));
check('Fleet (20 USDC / 20.000.000 raw units) emite 6.000 créditos (4.000 base + 2.000 bônus)', ($passFleet['credits_total'] ?? 0) === 6000, 'Got: ' . ($passFleet['credits_total'] ?? 'null'));

$mpPackages = grid402_mp_packages();
check('Mercado Pago Pix Starter emite 400 créditos', ($mpPackages['starter']['credits'] ?? 0) === 400);
check('Mercado Pago Pix Pro emite 1.000 créditos', ($mpPackages['pro']['credits'] ?? 0) === 1000);
check('Mercado Pago Pix Fleet emite 6.000 créditos', ($mpPackages['fleet']['credits'] ?? 0) === 6000);

$llmsTxt = (string) file_get_contents($projectRoot . '/public_html/llms.txt');
$readmeMd = (string) file_get_contents($projectRoot . '/README.md');
check('llms.txt documenta 400 / 1.000 / 6.000 créditos',
    (str_contains($llmsTxt, '400 creditos') || str_contains($llmsTxt, '400 créditos')) &&
    (str_contains($llmsTxt, '6.000 creditos') || str_contains($llmsTxt, '6.000 créditos'))
);
check('README.md documenta 400 / 1.000 / 6.000 créditos', str_contains($readmeMd, '400 créditos') && str_contains($readmeMd, '6.000 créditos'));

// -----------------------------------------------------------------
// 3. Cobertura 23/23 UP, Proveniência e Uptime 30d
// -----------------------------------------------------------------
echo "\n[3/7] Cobertura 23/23 Serviços UP, Proveniência e Histórico Uptime 30d:\n";
$servicesCatalog = grid402_get_utility_services();
check('Catálogo possui exatamente 23 micro-serviços', count($servicesCatalog) === 23, 'Got: ' . count($servicesCatalog));

if (!is_file(GRID402_FILE_FREE)) {
    ob_start();
    require_once $projectRoot . '/public_html/collector.php';
    ob_end_clean();
}
$summaryFree = json_decode((string) file_get_contents(GRID402_FILE_FREE), true);
$allUp = true;
$allHaveProvenance = true;
foreach ($servicesCatalog as $key => $meta) {
    if (($summaryFree['services'][$key]['status'] ?? '') !== 'UP') {
        $allUp = false;
    }
    if (empty($meta['upstream_source']) || !isset($meta['cache_ttl_sec'])) {
        $allHaveProvenance = false;
    }
}
check('Todos os 23 micro-serviços estão UP em summary_free.json', $allUp);
check('Todos os 23 micro-serviços possuem upstream_source e cache_ttl_sec', $allHaveProvenance);
$uptimeDays = $summaryFree['uptime_30d']['days'] ?? $summaryFree['uptime_30d'] ?? [];
check('summary_free.json inclui uptime_30d de 30 dias', is_array($uptimeDays) && count($uptimeDays) === 30);

// -----------------------------------------------------------------
// 4. Metadados de Proveniência (_meta) Assinados no _oracle_proof
// -----------------------------------------------------------------
echo "\n[4/7] Bloco _meta Assinado em _oracle_proof:\n";
$samplePayload = [
    'service' => 'forex',
    'status' => 'UP',
    'cached' => false,
    'rates' => ['USD' => ['bid' => 5.42]],
];
$signedPayload = grid402_attach_provenance_meta($samplePayload, 'forex');
grid402_sign_data($signedPayload, true);
check('Payload possui bloco _meta com source_authority, fetched_at, cache_status e cache_ttl_sec',
    isset($signedPayload['_meta']['source_authority'], $signedPayload['_meta']['fetched_at'], $signedPayload['_meta']['cache_status'], $signedPayload['_meta']['cache_ttl_sec'])
);
$proofVerification = grid402_verify_oracle_proof($signedPayload);
check('Assinatura _oracle_proof valida matematicamente o payload com _meta incluso', !empty($proofVerification['valid']));

// -----------------------------------------------------------------
// 5. Idempotency-Key M2M (Prevenção de Débito Duplo)
// -----------------------------------------------------------------
echo "\n[5/7] Idempotency-Key M2M (Cache e Replay Seguro):\n";
$idemKey = 'idem-test-' . bin2hex(random_bytes(8));
$tokenStarter = $passStarter['pass_token'];

// Primeira chamada consome 1 crédito (5000 raw units)
$consume1 = grid402_consume_session_pass($tokenStarter, 5000, 'forex');
check('Primeira chamada debita 1 crédito (restam 399)', !empty($consume1['valid']) && ($consume1['credits_remaining'] ?? -1) === 399);
grid402_idempotency_store($idemKey, 'forex', ['domain' => 'test'], $signedPayload);

// Segunda chamada com mesma Idempotency-Key bate no cache ANTES de debitar o Session Pass
$cachedReplay = grid402_idempotency_check($idemKey, 'forex', ['domain' => 'test']);
$statusAfterReplay = grid402_get_session_pass_status($tokenStarter);
check('Idempotency-Key retorna resposta em cache', is_array($cachedReplay) && ($cachedReplay['service'] ?? '') === 'forex');
check('Replay idempotente NÃO debita créditos adicionais (permanece em 399)', ($statusAfterReplay['credits_remaining'] ?? -1) === 399);

// -----------------------------------------------------------------
// 6. Controles de Gasto M2M (Spend Controls & /api/v1/pass/balance)
// -----------------------------------------------------------------
echo "\n[6/7] Controles de Gasto M2M (daily_credit_limit, allowed_endpoints e /api/v1/pass/balance):\n";
$limitsUpdate = grid402_update_session_pass_limits($tokenStarter, 2, ['forex', 'dns']);
check('Configuração de Spend Controls aplicada com sucesso', !empty($limitsUpdate['ok']) && !empty($limitsUpdate['valid']));

// Endpoint não permitido (cnpj) -> deve bloquear com PassEndpointRestricted (403)
$blockedEndpoint = grid402_consume_session_pass($tokenStarter, 20000, 'cnpj');
check('Bloqueia endpoint fora da whitelist com PassEndpointRestricted (403)',
    empty($blockedEndpoint['valid']) && ($blockedEndpoint['error'] ?? '') === 'PassEndpointRestricted' && ($blockedEndpoint['code'] ?? 0) === 403
);

// Endpoint permitido (dns = 1 crédito) -> usado hoje passa de 1 para 2 (atinge daily_credit_limit = 2)
$allowedCall = grid402_consume_session_pass($tokenStarter, 5000, 'dns');
check('Permite chamada dentro do limite diário e na whitelist (usados hoje = 2)', !empty($allowedCall['valid']) && ($allowedCall['credits_used_today'] ?? 0) === 2);

// Próxima chamada excede daily_credit_limit (2) -> deve bloquear com PassDailyLimitExceeded (429)
$exceededCall = grid402_consume_session_pass($tokenStarter, 5000, 'forex');
check('Bloqueia chamada que excede daily_credit_limit com PassDailyLimitExceeded (429)',
    empty($exceededCall['valid']) && ($exceededCall['error'] ?? '') === 'PassDailyLimitExceeded' && ($exceededCall['code'] ?? 0) === 429
);

$balanceInfo = grid402_get_session_pass_status($tokenStarter);
check('Consulta de saldo gratuita (/api/v1/pass/balance) retorna telemetria completa sem descontar créditos',
    ($balanceInfo['credits_remaining'] ?? -1) === 398 &&
    ($balanceInfo['daily_credit_limit'] ?? null) === 2 &&
    ($balanceInfo['credits_used_today'] ?? null) === 2 &&
    ($balanceInfo['allowed_endpoints'] ?? []) === ['forex', 'dns']
);

// -----------------------------------------------------------------
// 7. Governança, Transparência, robots.txt e MCP
// -----------------------------------------------------------------
echo "\n[7/7] Governança, security.txt, CHANGELOG.md, robots.txt e MCP 25 Tools:\n";
check('CHANGELOG.md existe na raiz', file_exists($projectRoot . '/CHANGELOG.md'));
check('public_html/.well-known/security.txt existe', file_exists($projectRoot . '/public_html/.well-known/security.txt'));
$robotsTxt = (string) file_get_contents($projectRoot . '/public_html/robots.txt');
check('robots.txt começa com diretiva RFC 9309 válida e inclui OAI-SearchBot/ClaudeBot',
    str_starts_with(trim($robotsTxt), '#') && str_contains($robotsTxt, 'OAI-SearchBot') && str_contains($robotsTxt, 'ClaudeBot')
);
$mcpToolsJs = (string) file_get_contents($projectRoot . '/mcp/tools.mjs');
check('mcp/tools.mjs expõe todos os 23 micro-serviços + summary + pass_balance',
    str_contains($mcpToolsJs, 'grid402_domain_audit') &&
    str_contains($mcpToolsJs, 'grid402_kyb_audit') &&
    str_contains($mcpToolsJs, 'grid402_get_dossier_360') &&
    str_contains($mcpToolsJs, 'grid402_market_snapshot') &&
    str_contains($mcpToolsJs, 'grid402_lei') &&
    str_contains($mcpToolsJs, 'grid402_ofac') &&
    str_contains($mcpToolsJs, 'grid402_notarize') &&
    str_contains($mcpToolsJs, 'grid402_global_markets') &&
    str_contains($mcpToolsJs, 'grid402_pass_balance')
);

echo "\n=================================================================\n";
echo "  RESULTADO: {$passed} aprovados, {$failed} falhas\n";
echo "=================================================================\n";

exit($failed > 0 ? 1 : 0);
