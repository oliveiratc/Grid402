<?php
/**
 * Suite de Testes Deterministica - Fase 1 (Liquidez Global e Blindagem Juridica)
 *
 * Cobre integralmente todos os cenarios das specs:
 * 1. Dual-Facilitator (CDP primario na Base + PayAI fallback pre-settle, zero fallback no /settle)
 * 2. Conformidade estrita CDP Bazaar v2 (scripts/validate_cdp_bazaar.php)
 * 3. Manifesto dinamico /.well-known/x402.json (Solana flag off/on) + check_solana_readiness.php
 * 4. Sync streaming 4-blocos (OFAC SDN, ONU, EU FSFi, UK OFSI) + preservacao em falha
 * 5. /api/v1/ofac e /api/v1/kyb-audit (multi-ativo EVM/Solana/BTC, fail-closed 503 pre-debito, STALE > 72h, demo_subset)
 * 6. /api/v1/extract (100% transiente, robots.txt RFC 9309, X-Robots-Tag, Content-Signal, <meta>, 401/403, paywall, SSRF)
 * 7. Telemetria /status e /trust
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/helpers_server.php';

$tmpPrivDir = t_bootstrap('settle');
require_once __DIR__ . '/../public_html/services/sanctions_index.php';
require_once __DIR__ . '/../public_html/services/ofac.php';
require_once __DIR__ . '/../public_html/services/kyb_audit.php';
require_once __DIR__ . '/../public_html/services/extract.php';
require_once __DIR__ . '/../public_html/services/pass.php';
require_once __DIR__ . '/../public_html/services/pass_repository.php';
require_once __DIR__ . '/../scripts/sync_sanctions_index.php';
require_once __DIR__ . '/../scripts/check_solana_readiness.php';
require_once __DIR__ . '/../scripts/validate_cdp_bazaar.php';

echo "=== SUITE FASE 1: LIQUIDEZ GLOBAL E BLINDAGEM JURIDICA ===\n\n";

$tempWorkDirs = [];

try {
    // =========================================================================
    // [1] Dual-Facilitator: CDP primario na Base, fallback no /verify, zero fallback no /settle
    // =========================================================================
    echo "[1/7] Testando roteamento Dual-Facilitator e invariante anti-dupla-cobranca:\n";

    $GLOBALS['grid402_cdp_credentials_override'] = [
        'key_id' => 'cdp-test-key',
        'key_secret' => 'cdp-test-secret',
    ];
    $GLOBALS['grid402_facilitator_routing_override'] = 'auto';

    // 1.1 Base Mainnet com CDP configurado -> CDP primario em verify e settle
    $cdpCalls = [];
    $GLOBALS['grid402_facilitator_transport'] = static function (string $url, array $headers, string $body, int $timeout) use (&$cdpCalls): array {
        $cdpCalls[] = ['url' => $url, 'body' => json_decode($body, true)];
        if (str_contains($url, 'api.cdp.coinbase.com') && str_ends_with($url, '/verify')) {
            return ['status' => 200, 'body' => json_encode(['isValid' => true, 'payer' => '0x1111111111111111111111111111111111111111']), 'error' => null];
        }
        if (str_contains($url, 'api.cdp.coinbase.com') && str_ends_with($url, '/settle')) {
            return ['status' => 200, 'body' => json_encode(['success' => true, 'transaction' => '0x' . str_repeat('aa', 32), 'network' => 'eip155:8453']), 'error' => null];
        }
        return ['status' => 500, 'body' => '{}', 'error' => 'unexpected_url'];
    };

    $verCdp = grid402_verify_exact_payment(t_payload(['auth' => ['value' => '5000'], 'accepted' => ['amount' => '5000']]), 5000, 'https://grid402.online/api/v1/forex');
    t_assert('Base Mainnet com credenciais CDP verifica via CDP primario', !empty($verCdp['valid']) && ($verCdp['facilitator'] ?? '') === 'cdp');
    $setCdp = grid402_settle_exact_payment($verCdp);
    t_assert('Base Mainnet liquida via CDP primario', !empty($setCdp['valid']) && ($setCdp['facilitator'] ?? '') === 'cdp' && count($cdpCalls) === 2 && str_contains($cdpCalls[1]['url'], 'api.cdp.coinbase.com'));

    // 1.2 CDP indisponivel (503) no /verify -> fallback automatico para PayAI
    $fbCalls = [];
    $GLOBALS['grid402_facilitator_transport'] = static function (string $url, array $headers, string $body, int $timeout) use (&$fbCalls): array {
        $fbCalls[] = $url;
        if (str_contains($url, 'api.cdp.coinbase.com')) {
            return ['status' => 503, 'body' => '{"error":"service_unavailable"}', 'error' => null];
        }
        if (str_contains($url, 'facilitator.payai.network') && str_ends_with($url, '/verify')) {
            return ['status' => 200, 'body' => json_encode(['isValid' => true, 'payer' => '0x2222222222222222222222222222222222222222']), 'error' => null];
        }
        if (str_contains($url, 'facilitator.payai.network') && str_ends_with($url, '/settle')) {
            return ['status' => 200, 'body' => json_encode(['success' => true, 'transaction' => '0x' . str_repeat('bb', 32), 'network' => 'eip155:8453']), 'error' => null];
        }
        return ['status' => 500, 'body' => '{}', 'error' => null];
    };

    $verFb = grid402_verify_exact_payment(t_payload(['auth' => ['value' => '5000'], 'accepted' => ['amount' => '5000']]), 5000, 'https://grid402.online/api/v1/forex');
    t_assert('Fallback automatico para PayAI quando CDP retorna 503 no /verify', !empty($verFb['valid']) && ($verFb['facilitator'] ?? '') === 'payai');
    $setFb = grid402_settle_exact_payment($verFb);
    t_assert('Settle conclui no mesmo facilitador vencedor (PayAI)', !empty($setFb['valid']) && ($setFb['facilitator'] ?? '') === 'payai' && count($fbCalls) === 3);

    // 1.3 Rejeicao por merito (isValid=false) NO /verify NAO aciona fallback
    $meritCalls = [];
    $GLOBALS['grid402_facilitator_transport'] = static function (string $url) use (&$meritCalls): array {
        $meritCalls[] = $url;
        return ['status' => 200, 'body' => json_encode(['isValid' => false, 'invalidReason' => 'bad_signature']), 'error' => null];
    };
    $verMerit = grid402_verify_exact_payment(t_payload(['auth' => ['value' => '5000'], 'accepted' => ['amount' => '5000']]), 5000, 'https://grid402.online/api/v1/forex');
    t_assert('Rejeicao por merito (isValid=false) devolve 402 sem acionar PayAI fallback', empty($verMerit['valid']) && ($verMerit['code'] ?? 0) === 402 && count($meritCalls) === 1);

    // 1.4 Erro ambiguo/timeout no /settle NAO aciona fallback e marca nonce pending
    $settleErrCalls = [];
    $nonceFixed = '0x' . str_repeat('12', 32);
    $GLOBALS['grid402_facilitator_transport'] = static function (string $url) use (&$settleErrCalls): array {
        $settleErrCalls[] = $url;
        if (str_ends_with($url, '/verify')) {
            return ['status' => 200, 'body' => json_encode(['isValid' => true, 'payer' => '0x3333333333333333333333333333333333333333']), 'error' => null];
        }
        return ['status' => 0, 'body' => null, 'error' => 'Operation timed out'];
    };
    $verPend = grid402_verify_exact_payment(t_payload(['auth' => ['value' => '5000', 'nonce' => $nonceFixed], 'accepted' => ['amount' => '5000']]), 5000, 'https://grid402.online/api/v1/forex');
    $setPend = grid402_settle_exact_payment($verPend);
    $noncesRaw = @json_decode((string) @file_get_contents(GRID402_FILE_USED_NONCES), true) ?: [];
    $pendingKey = '0x' . str_repeat('ab', 20) . ':' . strtolower($nonceFixed);
    t_assert(
        'Erro ambiguo no /settle devolve 503, mantem nonce pending e NUNCA chama PayAI no /settle',
        empty($setPend['valid']) && ($setPend['code'] ?? 0) === 503 && count($settleErrCalls) === 2
            && !str_contains($settleErrCalls[1], 'payai.network')
            && (($noncesRaw[$pendingKey]['state'] ?? '') === 'pending')
    );

    // 1.5 Polygon roteia diretamente para PayAI sem acionar CDP
    $polyCandidates = grid402_resolve_facilitator_candidates('eip155:137');
    $solCandidates = grid402_resolve_facilitator_candidates('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp');
    t_assert('Polygon e Solana roteiam diretamente para PayAI mesmo com credenciais CDP ativas', ($polyCandidates[0]['name'] ?? '') === 'payai' && count($polyCandidates) === 1 && ($solCandidates[0]['name'] ?? '') === 'payai');

    unset($GLOBALS['grid402_facilitator_transport'], $GLOBALS['grid402_cdp_credentials_override'], $GLOBALS['grid402_facilitator_routing_override']);

    // =========================================================================
    // [2] Validador CDP Bazaar v2
    // =========================================================================
    echo "\n[2/7] Testando validador CDP Bazaar (scripts/validate_cdp_bazaar.php):\n";
    $bazaarReport = grid402_validate_cdp_bazaar_catalog();
    t_assert('Todos os 24 endpoints pagos passam na validacao estrutural CDP Bazaar v2', !empty($bazaarReport['ok']) && $bazaarReport['passed'] === 24 && $bazaarReport['failed'] === 0, json_encode($bazaarReport));

    $liveMockCalled = 0;
    $bazaarLiveMock = grid402_validate_cdp_bazaar_catalog('ofac', true, static function (string $url, string $body) use (&$liveMockCalled): array {
        $liveMockCalled++;
        return ['status' => 200, 'body' => json_encode(['valid' => true, 'simulation' => ['outcome' => 'accepted']]), 'error' => ''];
    });
    t_assert('Modo --live valida payload contra endpoint CDP /v2/x402/validate', !empty($bazaarLiveMock['ok']) && $liveMockCalled === 1 && ($bazaarLiveMock['endpoints']['ofac']['live']['outcome'] ?? '') === 'accepted');

    // =========================================================================
    // [3] Manifesto dinamico /.well-known/x402.json e check_solana_readiness.php
    // =========================================================================
    echo "\n[3/7] Testando /.well-known/x402.json dinamico e check_solana_readiness.php:\n";

    $manifestOffCmd = sprintf(
        '%s -r %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg("putenv('GRID402_SOLANA_X402_ENABLED=false'); define('GRID402_EXEC', true); define('GRID402_X402_MANIFEST_NO_EMIT', true); require '" . str_replace('\\', '/', __DIR__ . '/../public_html/well_known_x402.php') . "'; echo json_encode(grid402_build_well_known_x402_manifest());")
    );
    $mOff = json_decode((string) shell_exec($manifestOffCmd), true);
    t_assert('well_known_x402.php com flag false omite Solana de networks[]', is_array($mOff) && !in_array('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $mOff['networks'] ?? [], true) && ($mOff['solana_enabled'] ?? true) === false);

    $manifestOnCmd = sprintf(
        '%s -r %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg("putenv('GRID402_SOLANA_X402_ENABLED=true'); define('GRID402_EXEC', true); define('GRID402_X402_MANIFEST_NO_EMIT', true); require '" . str_replace('\\', '/', __DIR__ . '/../public_html/well_known_x402.php') . "'; echo json_encode(grid402_build_well_known_x402_manifest());")
    );
    $mOn = json_decode((string) shell_exec($manifestOnCmd), true);
    t_assert('well_known_x402.php com flag true inclui Solana em networks[] e accepts[]', is_array($mOn) && in_array('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $mOn['networks'] ?? [], true) && count($mOn['resources'][0]['accepts'] ?? []) === 4);

    $readyOk = grid402_check_solana_readiness(
        'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C',
        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        ['https://mock-solana.local'],
        static function (string $rpc, string $method): array {
            if ($method === 'getBalance') {
                return ['ok' => true, 'http_status' => 200, 'error' => null, 'result' => ['value' => 5000000]];
            }
            return ['ok' => true, 'http_status' => 200, 'error' => null, 'result' => ['value' => [['pubkey' => 'AtaMock123', 'account' => ['data' => ['parsed' => ['info' => ['tokenAmount' => ['amount' => '1000000', 'uiAmount' => 1.0]]]]]]]]];
        }
    );
    t_assert('check_solana_readiness retorna ready=true quando SOL > 0 e ATA USDC existe', !empty($readyOk['ready']) && !empty($readyOk['has_sol']) && !empty($readyOk['has_usdc_ata']));

    $readyMissing = grid402_check_solana_readiness(
        'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C',
        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        ['https://mock-solana.local'],
        static function (string $rpc, string $method): array {
            if ($method === 'getBalance') {
                return ['ok' => true, 'http_status' => 200, 'error' => null, 'result' => ['value' => 0]];
            }
            return ['ok' => true, 'http_status' => 200, 'error' => null, 'result' => ['value' => []]];
        }
    );
    t_assert('check_solana_readiness retorna ready=false detalhando ausencia de SOL e ATA', empty($readyMissing['ready']) && in_array('sol_balance', $readyMissing['missing'] ?? [], true) && in_array('usdc_ata', $readyMissing['missing'] ?? [], true));

    // =========================================================================
    // [4] Sincronizador Streaming dos 4 Blocos Oficiais + Preservacao em Falha
    // =========================================================================
    echo "\n[4/7] Testando sync streaming dos 4 blocos oficiais e preservacao atomica:\n";

    $fxDir = sys_get_temp_dir() . '/grid402_fx_sanctions_' . bin2hex(random_bytes(4));
    $idxDir = sys_get_temp_dir() . '/grid402_idx_sanctions_' . bin2hex(random_bytes(4));
    $tempWorkDirs[] = $fxDir;
    $tempWorkDirs[] = $idxDir;
    @mkdir($fxDir, 0775, true);

    file_put_contents($fxDir . '/ofac_sdn.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Sanctions>
  <ReferenceValueSets>
    <FeatureTypeValues>
      <FeatureType ID="344">Digital Currency Address - ETH</FeatureType>
      <FeatureType ID="345">Digital Currency Address - SOL</FeatureType>
      <FeatureType ID="346">Digital Currency Address - XBT</FeatureType>
    </FeatureTypeValues>
  </ReferenceValueSets>
  <DistinctParties>
    <DistinctParty FixedRef="1001">
      <Profile ID="1001">
        <Identity>
          <Alias Primary="true"><DocumentedName><DocumentedNamePart><NamePartValue>TORNADO CASH</NamePartValue></DocumentedNamePart></DocumentedName></Alias>
          <Alias Primary="false"><DocumentedName><DocumentedNamePart><NamePartValue>TORNADO PROTOCOL</NamePartValue></DocumentedNamePart></DocumentedName></Alias>
        </Identity>
        <Feature FeatureTypeID="344"><FeatureVersion><VersionDetail>0x8589427373d6d84e98730d7795d8f6f8731fda16</VersionDetail></FeatureVersion></Feature>
        <Feature FeatureTypeID="345"><FeatureVersion><VersionDetail>7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU</VersionDetail></FeatureVersion></Feature>
        <Feature FeatureTypeID="346"><FeatureVersion><VersionDetail>bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh</VersionDetail></FeatureVersion></Feature>
      </Profile>
    </DistinctParty>
  </DistinctParties>
</Sanctions>
XML);

    file_put_contents($fxDir . '/un_consolidated.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CONSOLIDATED_LIST>
  <ENTITIES>
    <ENTITY>
      <DATAID>2001</DATAID>
      <FIRST_NAME>AL QAEDA NETWORK CELL</FIRST_NAME>
      <UN_LIST_TYPE>Al-Qaida</UN_LIST_TYPE>
      <LISTED_ON>2021-05-01</LISTED_ON>
      <ENTITY_ALIAS><ALIAS_NAME>AQ CELL ALIAS</ALIAS_NAME></ENTITY_ALIAS>
    </ENTITY>
  </ENTITIES>
</CONSOLIDATED_LIST>
XML);

    file_put_contents($fxDir . '/eu_fsfi.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<export>
  <sanctionEntity logicalId="3001">
    <nameAlias wholeName="EU SANCTIONED LOGISTICS CORP" />
    <regulation programme="EU-UKR" publicationDate="2024-02-20" />
    <citizenship countryIso2Code="RU" />
  </sanctionEntity>
</export>
XML);

    file_put_contents($fxDir . '/uk_ofsi.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Designations>
  <Designation>
    <UniqueID>UK4001</UniqueID>
    <Names>
      <Name><Name6>UK FCDO FROZEN ALIAS CO</Name6><NameType>Alias</NameType></Name>
      <Name><Name6>UK OFSI FROZEN HOLDINGS LTD</Name6><NameType>Primary Name</NameType></Name>
    </Names>
    <RegimeName>Russia (Sanctions) (EU Exit)</RegimeName>
    <DateDesignated>2023-11-10</DateDesignated>
  </Designation>
</Designations>
XML);

    // 4.1 Antes do sync: fail-closed 503 em /api/v1/ofac e /api/v1/kyb-audit (producao)
    $GLOBALS['grid402_sanctions_index_dir_override'] = $idxDir;
    $ofacNoIdx = grid402_service_ofac('0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90', '', false);
    t_assert('/api/v1/ofac sem indice em producao retorna 503 fail-closed', !empty($ofacNoIdx['error']) && ($ofacNoIdx['code'] ?? 0) === 503 && ($ofacNoIdx['error_code'] ?? '') === 'SanctionsIndexUnavailable');

    $kybNoIdx = grid402_service_kyb_audit('', 'EMPRESA TESTE', '', '', false);
    t_assert('/api/v1/kyb-audit sem indice em producao propaga 503 fail-closed sem retornar CLEAR', !empty($kybNoIdx['error']) && ($kybNoIdx['code'] ?? 0) === 503);

    // Sandbox sem indice usa demo_subset rotulado
    $ofacDemo = grid402_service_ofac('0x8589427373d6d84e98730d7795d8f6f8731fda16', '', true);
    t_assert('/api/v1/ofac em modo demo sem indice usa demo_subset rotulado', ($ofacDemo['sanctioned'] ?? false) === true && ($ofacDemo['dataset_metadata']['dataset_mode'] ?? '') === 'demo_subset' && ($ofacDemo['dataset_metadata']['freshness_status'] ?? '') === 'DEMO_SUBSET');

    // Executa sync via CLI (--from-dir)
    $syncCmd = sprintf(
        '%s %s --from-dir=%s --output-dir=%s --json',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/../scripts/sync_sanctions_index.php'),
        escapeshellarg($fxDir),
        escapeshellarg($idxDir)
    );
    $syncOut = (string) shell_exec($syncCmd);
    $syncJson = json_decode($syncOut, true);
    $shaOriginal = (string) ($syncJson['manifest']['dataset_sha256'] ?? '');
    t_assert('sync_sanctions_index.php --from-dir gera manifest.json e shards dos 4 blocos', !empty($syncJson['ok']) && $shaOriginal !== '' && ($syncJson['manifest']['total_addresses'] ?? 0) === 3 && ($syncJson['manifest']['total_entities'] ?? 0) === 4);

    // Falha de 1 fonte preserva indice anterior integro
    $fxBrokenDir = sys_get_temp_dir() . '/grid402_fx_broken_' . bin2hex(random_bytes(4));
    $tempWorkDirs[] = $fxBrokenDir;
    @mkdir($fxBrokenDir, 0775, true);
    copy($fxDir . '/ofac_sdn.xml', $fxBrokenDir . '/ofac_sdn.xml');
    copy($fxDir . '/un_consolidated.xml', $fxBrokenDir . '/un_consolidated.xml');
    copy($fxDir . '/eu_fsfi.xml', $fxBrokenDir . '/eu_fsfi.xml');
    file_put_contents($fxBrokenDir . '/uk_ofsi.xml', '<broken><xml>');

    $syncFailed = false;
    try {
        grid402_run_sanctions_sync($fxBrokenDir, $idxDir);
    } catch (Throwable $e) {
        $syncFailed = true;
    }
    $afterFailManifest = grid402_sanctions_read_manifest($idxDir);
    t_assert('Falha de XML aborta sync e preserva dataset_sha256 anterior integro', $syncFailed && ($afterFailManifest['dataset_sha256'] ?? '') === $shaOriginal);

    // =========================================================================
    // [5] /api/v1/ofac multi-ativo, STALE > 72h, /api/v1/kyb-audit + zero debito Session Pass
    // =========================================================================
    echo "\n[5/7] Testando /api/v1/ofac multi-ativo, freshness STALE e estorno Session Pass em 503:\n";

    $nowFresh = time();
    $resEvm = grid402_service_ofac('0x8589427373d6d84e98730d7795d8f6f8731fda16', '', false, $nowFresh);
    $resSol = grid402_service_ofac('7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU', '', false, $nowFresh);
    $resBtc = grid402_service_ofac('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', '', false, $nowFresh);
    $resUn = grid402_service_ofac('', 'AQ CELL ALIAS', false, $nowFresh);
    $resEu = grid402_service_ofac('', 'EU SANCTIONED LOGISTICS CORP', false, $nowFresh);
    $resUk = grid402_service_ofac('', 'UK OFSI FROZEN HOLDINGS LTD', false, $nowFresh);

    t_assert('Triagem multi-ativo detecta EVM, Solana e BTC Bech32 no indice oficial', !empty($resEvm['sanctioned']) && !empty($resSol['sanctioned']) && ($resSol['query']['asset_type'] ?? '') === 'solana' && !empty($resBtc['sanctioned']) && ($resBtc['query']['asset_type'] ?? '') === 'btc_bech32');
    t_assert('Triagem de entidades detecta aliases/registros da ONU, UE FSFi e UK OFSI', !empty($resUn['sanctioned']) && !empty($resEu['sanctioned']) && !empty($resUk['sanctioned']));
    t_assert('Resposta FRESH inclui dataset_sha256, 4 blocos e legal_disclaimer', ($resSol['dataset_metadata']['freshness_status'] ?? '') === 'FRESH' && ($resSol['dataset_metadata']['dataset_sha256'] ?? '') === $shaOriginal && !empty($resSol['legal_disclaimer']));

    // STALE > 72h
    $futureNow = $nowFresh + (73 * 3600);
    $resStale = grid402_service_ofac('7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU', '', false, $futureNow);
    t_assert('Indice com >72h sinaliza freshness_status=STALE e stale_dataset_warning=true', ($resStale['dataset_metadata']['freshness_status'] ?? '') === 'STALE' && !empty($resStale['dataset_metadata']['stale_dataset_warning']));

    // Teste integrado HTTP + Session Pass: 503 em /api/v1/ofac sem indice estorna creditos (zero debito liquido)
    $server = grid402_start_server([
        'router' => __DIR__ . '/router.php',
        'probe' => '/status',
        'env' => [
            'GRID402_STORAGE_DIR' => $tmpPrivDir,
            'GRID402_SANCTIONS_INDEX_DIR' => $tmpPrivDir . '/sanctions_index_absent',
            'GRID402_DISABLE_TELEGRAM' => '1',
        ],
    ]);
    try {
        $passRes = grid402_issue_session_pass('0x' . bin2hex(random_bytes(32)), 2000000, false);
        $tok = (string) ($passRes['pass_token'] ?? '');
        $balBefore = grid402_get_session_pass_status($tok)['credits_remaining'] ?? 0;

        $ch = curl_init($server['base_url'] . '/api/v1/ofac?target=0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-Payment: ' . $tok],
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $r503Code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $balAfter = grid402_get_session_pass_status($tok)['credits_remaining'] ?? 0;
        t_assert('HTTP /api/v1/ofac sem indice retorna 503 e estorna integralmente o Session Pass', $r503Code === 503 && $balBefore === 400 && $balAfter === 400, "code={$r503Code}, before={$balBefore}, after={$balAfter}");

        // Testa também /.well-known/x402.json servido dinamicamente via router.php
        $chX402 = curl_init($server['base_url'] . '/.well-known/x402.json');
        curl_setopt_array($chX402, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $x402Body = (string) curl_exec($chX402);
        $x402Code = (int) curl_getinfo($chX402, CURLINFO_HTTP_CODE);
        curl_close($chX402);
        $x402Decoded = json_decode($x402Body, true);
        t_assert('HTTP GET /.well-known/x402.json dinamico retorna 200 com mapa facilitators e 24 recursos', $x402Code === 200 && is_array($x402Decoded['facilitators'] ?? null) && count($x402Decoded['resources'] ?? []) === 24);

        $chSyncDeny = curl_init($server['base_url'] . '/sync_sanctions.php');
        curl_setopt_array($chSyncDeny, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        curl_exec($chSyncDeny);
        $syncDenyCode = (int) curl_getinfo($chSyncDeny, CURLINFO_HTTP_CODE);
        curl_close($chSyncDeny);
        t_assert('HTTP GET /sync_sanctions.php sem token retorna 403 Forbidden', $syncDenyCode === 403, "code={$syncDenyCode}");
    } finally {
        grid402_stop_server($server);
    }

    unset($GLOBALS['grid402_sanctions_index_dir_override']);

    // =========================================================================
    // [6] /api/v1/extract: Transiencia total, robots.txt, anti-AI, 401/403, paywall
    // =========================================================================
    echo "\n[6/7] Testando blindagem juridica e operacao 100% transiente em /api/v1/extract:\n";

    $dnsOk = static fn(string $host): array => ['93.184.216.34'];
    $robotsAllowAll = static fn(string $url): array => ['ok' => true, 'http_status' => 200, 'body' => "User-agent: *\nAllow: /\n"];
    $robotsBlockSecret = static fn(string $url): array => ['ok' => true, 'http_status' => 200, 'body' => "User-agent: *\nDisallow: /private/\n"];

    $beforeExtractFiles = glob(GRID402_PRIVATE_DIR . '/extract_*.json') ?: [];
    $okExtract = grid402_service_extract(
        'https://example.com/public-article',
        4000,
        static fn(string $u): array => [
            'ok' => true,
            'final_url' => $u,
            'http_status' => 200,
            'content_type' => 'text/html; charset=utf-8',
            'x_robots_tag' => [],
            'content_signal' => [],
            'body' => '<html><head><title>Public Open Article</title></head><body><main><h1>Open Data</h1><p>Publicly available text.</p></main></body></html>',
            'bytes_downloaded' => 140,
        ],
        $dnsOk,
        $robotsAllowAll
    );
    $afterExtractFiles = glob(GRID402_PRIVATE_DIR . '/extract_*.json') ?: [];
    t_assert(
        'Extracao permitida retorna 200 transient_only=true, cached=false e zero gravacao em disco',
        !empty($okExtract['success']) && ($okExtract['transient_only'] ?? false) === true && ($okExtract['cached'] ?? true) === false && !empty($okExtract['legal_disclaimer']) && count($afterExtractFiles) === count($beforeExtractFiles)
    );

    $fetcherShouldNotRun = false;
    $blockedRobots = grid402_service_extract(
        'https://example.com/private/doc.html',
        4000,
        static function (string $u) use (&$fetcherShouldNotRun): array {
            $fetcherShouldNotRun = true;
            return ['ok' => true, 'http_status' => 200, 'body' => ''];
        },
        $dnsOk,
        $robotsBlockSecret
    );
    t_assert('robots.txt Disallow bloqueia com 403 RobotsDisallowed antes de baixar a pagina', ($blockedRobots['code'] ?? 0) === 403 && ($blockedRobots['error'] ?? '') === 'RobotsDisallowed' && $fetcherShouldNotRun === false);

    $blockedXRobots = grid402_service_extract(
        'https://example.com/doc',
        4000,
        static fn(string $u): array => ['ok' => true, 'http_status' => 200, 'x_robots_tag' => ['noai'], 'content_signal' => [], 'body' => '<html><body>Text</body></html>'],
        $dnsOk,
        $robotsAllowAll
    );
    t_assert('X-Robots-Tag: noai bloqueia com 403 DirectiveDisallowed', ($blockedXRobots['code'] ?? 0) === 403 && ($blockedXRobots['error'] ?? '') === 'DirectiveDisallowed');

    $blockedSignal = grid402_service_extract(
        'https://example.com/doc2',
        4000,
        static fn(string $u): array => ['ok' => true, 'http_status' => 200, 'x_robots_tag' => [], 'content_signal' => ['search=yes, ai-input=no'], 'body' => '<html><body>Text</body></html>'],
        $dnsOk,
        $robotsAllowAll
    );
    t_assert('Content-Signal ai-input=no bloqueia com 403 DirectiveDisallowed', ($blockedSignal['code'] ?? 0) === 403 && ($blockedSignal['error'] ?? '') === 'DirectiveDisallowed');

    $blockedMeta = grid402_service_extract(
        'https://example.com/doc3',
        4000,
        static fn(string $u): array => ['ok' => true, 'http_status' => 200, 'x_robots_tag' => [], 'content_signal' => [], 'body' => '<html><head><meta name="robots" content="noindex, noai"></head><body>Text</body></html>'],
        $dnsOk,
        $robotsAllowAll
    );
    t_assert('<meta name="robots" content="noai"> bloqueia com 403 DirectiveDisallowed', ($blockedMeta['code'] ?? 0) === 403 && ($blockedMeta['error'] ?? '') === 'DirectiveDisallowed');

    $blocked401 = grid402_service_extract(
        'https://example.com/auth',
        4000,
        static fn(string $u): array => ['ok' => true, 'http_status' => 401, 'x_robots_tag' => [], 'content_signal' => [], 'body' => 'Unauthorized'],
        $dnsOk,
        $robotsAllowAll
    );
    t_assert('HTTP 401 na origem bloqueia com 403 OriginAccessDenied', ($blocked401['code'] ?? 0) === 403 && ($blocked401['error'] ?? '') === 'OriginAccessDenied');

    $blockedPaywall = grid402_service_extract(
        'https://example.com/paywall',
        4000,
        static fn(string $u): array => ['ok' => true, 'http_status' => 200, 'x_robots_tag' => [], 'content_signal' => [], 'body' => '<html><head><script type="application/ld+json">{"@type":"NewsArticle","isAccessibleForFree":false}</script></head><body>Subscriber only</body></html>'],
        $dnsOk,
        $robotsAllowAll
    );
    t_assert('JSON-LD isAccessibleForFree=false bloqueia com 403 PaywallDetected', ($blockedPaywall['code'] ?? 0) === 403 && ($blockedPaywall['error'] ?? '') === 'PaywallDetected');

    // =========================================================================
    // [7] /status e /trust expoem telemetria de sancoes e politica transiente
    // =========================================================================
    echo "\n[7/7] Testando telemetria publica em /status e /trust:\n";
    ob_start();
    define('GRID402_INTERNAL_BOOTSTRAP', true);
    require __DIR__ . '/../public_html/collector.php';
    ob_end_clean();
    $summaryData = json_decode((string) file_get_contents(GRID402_FILE_FREE), true);
    t_assert('/status (summary_free.json) expoe bloco publico sanctions_index e 25/25 servicos', is_array($summaryData['sanctions_index'] ?? null) && count($summaryData['services'] ?? []) === 25);

    ob_start();
    require __DIR__ . '/../public_html/trust.php';
    $trustHtml = (string) ob_get_clean();
    t_assert('/trust exibe politica de 4 blocos de sancoes e operacao 100% transiente do /extract', str_contains($trustHtml, '4-Block Official Sanctions Index') && str_contains($trustHtml, '100% transient execution'));

    echo "\nRESULTADO: {$GLOBALS['t_passed']} PASSOU | {$GLOBALS['t_failed']} FALHOU\n";
    if ($GLOBALS['t_failed'] > 0) {
        exit(1);
    }
} finally {
    unset($GLOBALS['grid402_facilitator_transport'], $GLOBALS['grid402_cdp_credentials_override'], $GLOBALS['grid402_facilitator_routing_override'], $GLOBALS['grid402_sanctions_index_dir_override']);
    foreach ($tempWorkDirs as $d) {
        grid402_sanctions_remove_dir_recursive($d);
    }
    grid402_sanctions_remove_dir_recursive($tmpPrivDir);
}
