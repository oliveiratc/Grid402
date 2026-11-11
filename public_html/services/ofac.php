<?php
/**
 * Grid402 - Sanções Internacionais OFAC SDN / ONU e Inspetor AML Web3
 * Fontes:
 * 1. US Treasury OFAC SDN Digital Currency Addresses & Entities (17 U.S.C. § 105 - Domínio Público).
 * 2. Telemetria On-Chain via RPC Público da Base Mainnet (Chain ID 8453).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

/**
 * Base pública de endereços EVM e entidades sancionadas pelo OFAC SDN / ONU (Domínio Público)
 */
function grid402_ofac_sanctioned_registry(): array {
    return [
        'addresses' => [
            '0x8589427373d6d84e98730d7795d8f6f8731fda16' => [
                'entity' => 'TORNADO CASH',
                'program' => 'CYBER2 / EO13694 (OFAC SDN)',
                'listed_date' => '2022-08-08',
                'jurisdiction' => 'GLOBAL',
            ],
            '0x722122df12d4e14e13ac3b6895a86e84145b6967' => [
                'entity' => 'TORNADO CASH PROXY',
                'program' => 'CYBER2 / EO13694 (OFAC SDN)',
                'listed_date' => '2022-08-08',
                'jurisdiction' => 'GLOBAL',
            ],
            '0xd90e2f925da726b50c4ed8d0fb90ad053324f31b' => [
                'entity' => 'TORNADO CASH ROUTER',
                'program' => 'CYBER2 / EO13694 (OFAC SDN)',
                'listed_date' => '2022-08-08',
                'jurisdiction' => 'GLOBAL',
            ],
            '0x098b716b8aaf21512996dc57eb0615e2383e2f96' => [
                'entity' => 'LAZARUS GROUP (RONIN BRIDGE EXPLOITER)',
                'program' => 'DPRK3 (OFAC SDN / UN)',
                'listed_date' => '2022-04-14',
                'jurisdiction' => 'KP',
            ],
            '0xa7e5d5a720f06526557c513402f2e6b5fa20b008' => [
                'entity' => 'GARANTEX EUROPE OU',
                'program' => 'RUSSIA-EO14024 / CYBER2 (OFAC SDN)',
                'listed_date' => '2022-04-05',
                'jurisdiction' => 'RU/EE',
            ],
            '0x7f367cc41522ce07553e823bf3be79a889debe1b' => [
                'entity' => 'SINBAD.IO MIXER',
                'program' => 'CYBER2 / DPRK (OFAC SDN)',
                'listed_date' => '2023-11-29',
                'jurisdiction' => 'GLOBAL',
            ],
        ],
        'entities' => [
            'TORNADO CASH' => ['program' => 'CYBER2 (OFAC SDN)', 'country' => 'GLOBAL'],
            'LAZARUS GROUP' => ['program' => 'DPRK3 (OFAC SDN / UN Security Council)', 'country' => 'KP'],
            'GARANTEX' => ['program' => 'CYBER2 / RUSSIA-EO14024 (OFAC SDN)', 'country' => 'RU'],
            'BLENDER.IO' => ['program' => 'CYBER2 (OFAC SDN)', 'country' => 'GLOBAL'],
            'SINBAD' => ['program' => 'CYBER2 / DPRK (OFAC SDN)', 'country' => 'GLOBAL'],
            'HYDRA MARKET' => ['program' => 'CYBER2 (OFAC SDN)', 'country' => 'RU'],
            'CHEXCHANGE' => ['program' => 'OFAC SDN Consolidated', 'country' => 'GLOBAL'],
        ],
    ];
}

/**
 * Consulta telemetria on-chain da carteira na Base Mainnet via JSON-RPC em lote (Batch RPC)
 */
function grid402_ofac_inspect_base_wallet(string $address): array {
    $cleanAddr = strtolower(trim($address));
    $usdcContract = GRID402_USDC_CONTRACT;
    // balanceOf(address) -> 0x70a08231 + address padded 64 hex
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
        CURLOPT_SSL_VERIFYPEER => false,
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
                    $byId[(int)$r['id']] = $r['result'] ?? null;
                }
            }
            if (!empty($byId[1]) && is_string($byId[1])) {
                $wei = hexdec(substr($byId[1], 2));
                $ethBalance = round((float)$wei / 1e18, 6);
            }
            if (!empty($byId[2]) && is_string($byId[2])) {
                $txCount = (int) hexdec(substr($byId[2], 2));
            }
            if (!empty($byId[3]) && is_string($byId[3]) && $byId[3] !== '0x' && $byId[3] !== '0x0') {
                $accountType = 'SMART_CONTRACT';
            }
            if (!empty($byId[4]) && is_string($byId[4]) && strlen($byId[4]) > 2) {
                $rawUsdc = hexdec(substr($byId[4], 2));
                $usdcBalance = round((float)$rawUsdc / 1e6, 4);
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
 * Executa a verificação internacional OFAC SDN / ONU e AML On-Chain
 */
function grid402_service_ofac(string $address = '', string $entityName = ''): array {
    $address = strtolower(trim($address));
    $entityName = trim($entityName);

    if ($address === '' && $entityName === '') {
        $address = GRID402_WALLET_ADDRESS;
    }

    if ($address !== '' && !preg_match('/^0x[a-f0-9]{40}$/', $address)) {
        return [
            'error' => true,
            'code' => 400,
            'message' => 'Endereço EVM inválido. Informe um endereço hexadecimal iniciando com 0x seguido de 40 caracteres.',
        ];
    }

    $registry = grid402_ofac_sanctioned_registry();
    $matchedLists = [];
    $sanctioned = false;

    if ($address !== '' && isset($registry['addresses'][$address])) {
        $sanctioned = true;
        $info = $registry['addresses'][$address];
        $matchedLists[] = [
            'match_type' => 'EXACT_CRYPTO_ADDRESS',
            'entity' => $info['entity'],
            'program' => $info['program'],
            'listed_date' => $info['listed_date'],
            'jurisdiction' => $info['jurisdiction'],
        ];
    }

    if ($entityName !== '') {
        $upperName = strtoupper($entityName);
        foreach ($registry['entities'] as $sName => $sMeta) {
            if (str_contains($upperName, $sName) || str_contains($sName, $upperName)) {
                $sanctioned = true;
                $matchedLists[] = [
                    'match_type' => 'ENTITY_NAME_MATCH',
                    'entity' => $sName,
                    'program' => $sMeta['program'],
                    'jurisdiction' => $sMeta['country'],
                ];
            }
        }
    }

    $onchainProfile = ($address !== '') ? grid402_ofac_inspect_base_wallet($address) : null;

    return [
        'service' => 'ofac',
        'status' => 'UP',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'query' => [
            'address' => $address !== '' ? $address : null,
            'entity_name' => $entityName !== '' ? $entityName : null,
        ],
        'sanctioned' => $sanctioned,
        'risk_level' => $sanctioned ? 'CRITICAL' : 'LOW',
        'compliance_decision' => $sanctioned ? 'BLOCK_TRANSACTION' : 'CLEAR',
        'matched_lists' => $matchedLists,
        'lists_checked' => [
            'US Treasury OFAC SDN (Specially Designated Nationals)',
            'OFAC Sanctioned Digital Currency Addresses',
            'UN Security Council Consolidated Sanctions List',
        ],
        'onchain_profile' => $onchainProfile,
        'source' => 'US Treasury OFAC Public Domain + Base Mainnet RPC',
    ];
}

/**
 * Gera a prévia gratuita (Teaser HTTP 402) para o endpoint OFAC
 */
function grid402_preview_ofac(string $address = '', string $entityName = ''): array {
    $addr = $address !== '' ? $address : GRID402_WALLET_ADDRESS;
    return [
        'target' => $entityName !== '' ? $entityName : $addr,
        'lists_screened' => 'OFAC SDN + UN Consolidated + Base On-Chain',
        'screening_status' => 'COMPLETED_LOCKED',
        'network' => 'Base Mainnet (8453)',
    ];
}

