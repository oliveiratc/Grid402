<?php
/**
 * Grid402 - Sanções Internacionais (OFAC SDN / ONU / UE FSFi / UK OFSI) e Inspetor AML Web3
 * Fontes:
 * 1. US Treasury OFAC SDN (sdn_advanced.xml - 17 U.S.C. § 105 - Domínio Público).
 * 2. UN Security Council Consolidated Sanctions List (UN Open Data).
 * 3. European Union Financial Sanctions FSFi (EU Open Data).
 * 4. UK HM Treasury OFSI Consolidated List (Open Government Licence v3.0).
 * 5. Telemetria On-Chain via RPC Público da Base Mainnet (Chain ID 8453).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/sanctions_index.php';

/**
 * Subset minimo exclusivo para modo Sandbox (`X-Payment: demo`) quando nao ha indice local sincronizado.
 */
function grid402_ofac_demo_subset_registry(): array {
    return [
        'addresses' => [
            '0x8589427373d6d84e98730d7795d8f6f8731fda16' => [
                'entity' => 'TORNADO CASH',
                'program' => 'CYBER2 / EO13694 (OFAC SDN)',
                'asset' => 'ETH',
                'listed_date' => '2022-08-08',
                'jurisdiction' => 'GLOBAL',
                'source' => 'ofac_sdn',
                'source_label' => 'US Treasury OFAC SDN (Demo Subset)',
            ],
            '0x098b716b8aaf21512996dc57eb0615e2383e2f96' => [
                'entity' => 'LAZARUS GROUP (RONIN BRIDGE EXPLOITER)',
                'program' => 'DPRK3 (OFAC SDN / UN)',
                'asset' => 'ETH',
                'listed_date' => '2022-04-14',
                'jurisdiction' => 'KP',
                'source' => 'ofac_sdn',
                'source_label' => 'US Treasury OFAC SDN (Demo Subset)',
            ],
        ],
        'entities' => [
            'TORNADO CASH' => ['program' => 'CYBER2 (OFAC SDN)', 'country' => 'GLOBAL', 'source' => 'ofac_sdn', 'source_label' => 'US Treasury OFAC SDN (Demo Subset)'],
            'LAZARUS GROUP' => ['program' => 'DPRK3 (OFAC SDN / UN)', 'country' => 'KP', 'source' => 'ofac_sdn', 'source_label' => 'US Treasury OFAC SDN (Demo Subset)'],
        ],
    ];
}

/**
 * Consulta telemetria on-chain da carteira na Base Mainnet via JSON-RPC em lote (Batch RPC).
 */
function grid402_ofac_inspect_base_wallet(string $address): array {
    $cleanAddr = strtolower(trim($address));
    $usdcContract = GRID402_USDC_CONTRACT;
    $balanceOfData = '0x70a08231' . str_pad(substr($cleanAddr, 2), 64, '0', STR_PAD_LEFT);

    $batchPayload = [
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_getBalance', 'params' => [$cleanAddr, 'latest']],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'eth_getTransactionCount', 'params' => [$cleanAddr, 'latest']],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'eth_getCode', 'params' => [$cleanAddr, 'latest']],
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'eth_call', 'params' => [['to' => $usdcContract, 'data' => $balanceOfData], 'latest']],
    ];

    $ch = curl_init(GRID402_BASE_RPC_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($batchPayload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ethBalance = 0.0;
    $usdcBalance = 0.0;
    $txCount = 0;
    $accountType = 'EOA (Externally Owned Account)';

    if ($httpCode === 200 && is_string($raw)) {
        $responses = @json_decode($raw, true);
        if (is_array($responses)) {
            $byId = [];
            foreach ($responses as $r) {
                if (isset($r['id'])) {
                    $byId[(int) $r['id']] = $r['result'] ?? null;
                }
            }
            if (!empty($byId[1]) && is_string($byId[1])) {
                $wei = hexdec(substr($byId[1], 2));
                $ethBalance = round((float) $wei / 1e18, 6);
            }
            if (!empty($byId[2]) && is_string($byId[2])) {
                $txCount = (int) hexdec(substr($byId[2], 2));
            }
            if (!empty($byId[3]) && is_string($byId[3]) && $byId[3] !== '0x' && $byId[3] !== '0x0') {
                $accountType = 'SMART_CONTRACT';
            }
            if (!empty($byId[4]) && is_string($byId[4]) && strlen($byId[4]) > 2) {
                $rawUsdc = hexdec(substr($byId[4], 2));
                $usdcBalance = round((float) $rawUsdc / 1e6, 4);
            }
        }
    }

    return [
        'network' => 'Base Mainnet (Chain ID 8453)',
        'address' => $cleanAddr,
        'account_type' => $accountType,
        'eth_balance' => $ethBalance,
        'usdc_balance' => $usdcBalance,
        'outbound_tx_count' => $txCount,
        'explorer_url' => 'https://basescan.org/address/' . $cleanAddr,
    ];
}

/**
 * Executa a verificação internacional OFAC SDN / ONU / UE FSFi / UK OFSI e AML On-Chain.
 */
function grid402_service_ofac(string $address = '', string $entityName = '', bool $isSandbox = false, ?int $now = null): array {
    $rawAddr = trim($address);
    $entityName = trim($entityName);

    if ($rawAddr === '' && $entityName === '') {
        $rawAddr = GRID402_WALLET_ADDRESS;
    }

    $detectedAddr = null;
    if ($rawAddr !== '') {
        $detectedAddr = grid402_ofac_detect_address_type($rawAddr);
        if ($detectedAddr === null) {
            if (str_starts_with(strtolower($rawAddr), '0x')) {
                return [
                    'error' => true,
                    'code' => 400,
                    'error_code' => 'InvalidAddressParameter',
                    'message' => 'Endereço cripto inválido. Informe endereço EVM (0x + 40 hex), Solana, Bitcoin, Tron válido ou nome da entidade.',
                ];
            }
            // Fallback retrocompatível se texto passado como endereço sem formato cripto
            if ($entityName === '') {
                $entityName = $rawAddr;
                $rawAddr = '';
            }
        }
    }

    $indexSummary = grid402_sanctions_index_summary(null, $now);
    $indexAvailable = !empty($indexSummary['available']);

    // Fail-closed em produção: nunca retorna CLEAR sem o índice oficial sincronizado
    if (!$indexAvailable && !$isSandbox) {
        return [
            'error' => true,
            'code' => 503,
            'error_code' => 'SanctionsIndexUnavailable',
            'service' => 'ofac',
            'message' => 'Official sanctions index is not synchronized on this node. No fee was charged; retry later or run scripts/sync_sanctions_index.php.',
        ];
    }

    $matchedLists = [];
    $sanctioned = false;
    $usingOfficialIndex = $indexAvailable;

    if ($usingOfficialIndex) {
        if ($rawAddr !== '') {
            $addrMatches = grid402_sanctions_lookup_address($rawAddr);
            foreach ($addrMatches as $m) {
                $sanctioned = true;
                $matchedLists[] = [
                    'match_type' => 'EXACT_CRYPTO_ADDRESS',
                    'source' => (string) ($m['source'] ?? 'ofac_sdn'),
                    'source_label' => (string) ($m['source_label'] ?? 'Official Sanctions Registry'),
                    'asset' => (string) ($m['asset'] ?? ($detectedAddr['type'] ?? 'CRYPTO')),
                    'entity' => (string) ($m['entity'] ?? ''),
                    'program' => (string) ($m['program'] ?? ''),
                    'listed_date' => $m['listed_date'] ?? null,
                    'jurisdiction' => (string) ($m['jurisdiction'] ?? 'GLOBAL'),
                ];
            }
        }
        if ($entityName !== '') {
            $entMatches = grid402_sanctions_lookup_entity($entityName);
            foreach ($entMatches as $m) {
                $sanctioned = true;
                $matchedLists[] = [
                    'match_type' => (string) ($m['match_type'] ?? 'ENTITY_NAME_MATCH'),
                    'source' => (string) ($m['source'] ?? ''),
                    'source_label' => (string) ($m['source_label'] ?? ''),
                    'entity' => (string) ($m['name'] ?? ''),
                    'program' => (string) ($m['program'] ?? ''),
                    'listed_date' => $m['listed_date'] ?? null,
                    'jurisdiction' => (string) ($m['jurisdiction'] ?? 'GLOBAL'),
                ];
            }
        }
    } else {
        // Modo Sandbox demo sem índice local: usa subset explicitamente rotulado
        $demo = grid402_ofac_demo_subset_registry();
        $lowerAddr = strtolower($rawAddr);
        if ($lowerAddr !== '' && isset($demo['addresses'][$lowerAddr])) {
            $sanctioned = true;
            $info = $demo['addresses'][$lowerAddr];
            $matchedLists[] = [
                'match_type' => 'EXACT_CRYPTO_ADDRESS',
                'source' => $info['source'],
                'source_label' => $info['source_label'],
                'asset' => $info['asset'],
                'entity' => $info['entity'],
                'program' => $info['program'],
                'listed_date' => $info['listed_date'],
                'jurisdiction' => $info['jurisdiction'],
            ];
        }
        if ($entityName !== '') {
            $upperName = grid402_sanctions_normalize_text($entityName);
            foreach ($demo['entities'] as $sName => $sMeta) {
                if (str_contains($upperName, $sName) || str_contains($sName, $upperName)) {
                    $sanctioned = true;
                    $matchedLists[] = [
                        'match_type' => 'ENTITY_NAME_MATCH',
                        'source' => $sMeta['source'],
                        'source_label' => $sMeta['source_label'],
                        'entity' => $sName,
                        'program' => $sMeta['program'],
                        'jurisdiction' => $sMeta['country'],
                    ];
                }
            }
        }
    }

    $isEvm = ($detectedAddr !== null && ($detectedAddr['type'] ?? '') === 'evm');
    $onchainProfile = $isEvm ? grid402_ofac_inspect_base_wallet((string) $detectedAddr['normalized']) : null;

    $datasetMetadata = $usingOfficialIndex ? [
        'dataset_mode' => 'official_synced',
        'freshness_status' => $indexSummary['freshness_status'],
        'stale_dataset_warning' => (bool) $indexSummary['stale_dataset_warning'],
        'last_synced_at' => $indexSummary['last_synced_at'],
        'age_hours' => $indexSummary['age_hours'],
        'freshness_ttl_hours' => $indexSummary['freshness_ttl_hours'],
        'dataset_sha256' => $indexSummary['dataset_sha256'],
        'total_addresses' => $indexSummary['total_addresses'],
        'total_entities' => $indexSummary['total_entities'],
        'lists_counts' => $indexSummary['lists_counts'],
    ] : [
        'dataset_mode' => 'demo_subset',
        'freshness_status' => 'DEMO_SUBSET',
        'stale_dataset_warning' => false,
        'last_synced_at' => null,
        'age_hours' => null,
        'freshness_ttl_hours' => $indexSummary['freshness_ttl_hours'],
        'dataset_sha256' => null,
        'total_addresses' => 2,
        'total_entities' => 2,
        'lists_counts' => ['demo_subset' => ['records' => 2, 'addresses' => 2]],
    ];

    $listsChecked = $usingOfficialIndex ? [
        'US Treasury OFAC SDN (sdn_advanced.xml)',
        'UN Security Council Consolidated Sanctions List',
        'European Union Financial Sanctions (FSFi)',
        'UK HM Treasury OFSI Consolidated List',
    ] : [
        'Demo Sandbox Subset ONLY (official 4-block index not loaded locally)',
    ];

    return [
        'service' => 'ofac',
        'status' => 'UP',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'query' => [
            'address' => $rawAddr !== '' ? ($detectedAddr['normalized'] ?? $rawAddr) : null,
            'asset_type' => $detectedAddr['type'] ?? null,
            'entity_name' => $entityName !== '' ? $entityName : null,
        ],
        'sanctioned' => $sanctioned,
        'risk_level' => $sanctioned ? 'CRITICAL' : 'LOW',
        'compliance_decision' => $sanctioned ? 'BLOCK_TRANSACTION' : 'CLEAR',
        'matched_lists' => $matchedLists,
        'lists_checked' => $listsChecked,
        'dataset_metadata' => $datasetMetadata,
        'onchain_profile' => $onchainProfile,
        'source' => $usingOfficialIndex
            ? 'US Treasury OFAC SDN + UN Security Council + EU FSFi + UK OFSI + Base Mainnet RPC'
            : 'Demo Sandbox Subset + Base Mainnet RPC',
        'legal_license' => 'US Public Domain (17 U.S.C. 105), UN Open Data, EU Open Data, UK OGL v3.0',
        'legal_disclaimer' => 'Best-effort technical screening against official public sanctions registries. Informational output only; does not constitute legal advice or replace regulated KYC/AML obligations.',
        'data_protection_compliance' => 'Zero PII Storage (Stateless screening of public sanctions registries and public blockchain state)',
    ];
}

/**
 * Gera a prévia gratuita (Teaser HTTP 402) para o endpoint OFAC.
 */
function grid402_preview_ofac(string $address = '', string $entityName = ''): array {
    $addr = trim($address) !== '' ? trim($address) : GRID402_WALLET_ADDRESS;
    return [
        'target' => $entityName !== '' ? $entityName : $addr,
        'lists_screened' => 'OFAC SDN + UN Consolidated + EU FSFi + UK OFSI + Base On-Chain',
        'screening_status' => 'COMPLETED_LOCKED',
        'network' => 'Base Mainnet (8453)',
    ];
}
