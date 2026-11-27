<?php
/**
 * Grid402 - Micro-Servico de Auditoria de Seguranca de Tokens Multi-Chain (/api/v1/token-safety)
 *
 * Fontes 100% publicas sem API key paga:
 * - GoPlus Security API (EVM Base/Ethereum/Polygon/Arbitrum + Solana)
 * - DexScreener Public API (liquidez DEX, volume 24h, preco USD e pares)
 *
 * Cache Zero-DB atomico por 300s em GRID402_PRIVATE_DIR.
 * Retorno fail-closed (HTTP 503 pre-cobranca) quando ambas as fontes falham sem cache.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Mapa canonico de redes EVM/Solana aceitas por /api/v1/token-safety.
 *
 * @return array<string, array{slug: string, type: string, chain_id: ?string, name: string, dex_chain: string}>
 */
function grid402_token_safety_chains(): array {
    return [
        'base' => ['slug' => 'base', 'type' => 'evm', 'chain_id' => '8453', 'name' => 'Base Mainnet', 'dex_chain' => 'base'],
        'ethereum' => ['slug' => 'ethereum', 'type' => 'evm', 'chain_id' => '1', 'name' => 'Ethereum Mainnet', 'dex_chain' => 'ethereum'],
        'polygon' => ['slug' => 'polygon', 'type' => 'evm', 'chain_id' => '137', 'name' => 'Polygon PoS', 'dex_chain' => 'polygon'],
        'arbitrum' => ['slug' => 'arbitrum', 'type' => 'evm', 'chain_id' => '42161', 'name' => 'Arbitrum One', 'dex_chain' => 'arbitrum'],
        'solana' => ['slug' => 'solana', 'type' => 'solana', 'chain_id' => null, 'name' => 'Solana Mainnet', 'dex_chain' => 'solana'],
    ];
}

/**
 * Resolve e normaliza alias de rede (slug ou chain_id numerico) para token-safety.
 */
function grid402_token_safety_resolve_chain(string $chainRaw): ?array {
    $norm = strtolower(trim($chainRaw));
    if ($norm === '' || $norm === 'auto') {
        return null;
    }
    $aliases = [
        'base' => 'base', '8453' => 'base', 'eip155:8453' => 'base',
        'eth' => 'ethereum', 'ethereum' => 'ethereum', 'mainnet' => 'ethereum', '1' => 'ethereum', 'eip155:1' => 'ethereum',
        'matic' => 'polygon', 'polygon' => 'polygon', '137' => 'polygon', 'eip155:137' => 'polygon',
        'arb' => 'arbitrum', 'arbitrum' => 'arbitrum', '42161' => 'arbitrum', 'eip155:42161' => 'arbitrum',
        'sol' => 'solana', 'solana' => 'solana', 'solana:5eykt4usfv8p8njdtrepy1vzqkqzkvdp' => 'solana',
    ];
    $slug = $aliases[$norm] ?? null;
    if ($slug === null) {
        return null;
    }
    $chains = grid402_token_safety_chains();
    return $chains[$slug] ?? null;
}

/**
 * Valida estritamente endereço (EVM 0x ou Solana Base58) e compatibilidade com `chain`.
 *
 * @return array<string, mixed>
 */
function grid402_token_safety_validate_input(string $address, string $chain = ''): array {
    $addr = trim($address);
    $chainTrim = trim($chain);

    if ($addr === '') {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'MissingTokenAddress',
            'message' => 'Informe o endereco do token/contrato no parametro "address" (EVM 0x... ou Solana Base58).',
        ];
    }

    $isEvmAddr = preg_match('/^0x[a-fA-F0-9]{40}$/', $addr) === 1;
    $isSolAddr = !$isEvmAddr && (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $addr) === 1);

    if (!$isEvmAddr && !$isSolAddr) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidTokenAddress',
            'message' => 'Endereco de token invalido. Use formato EVM (0x + 40 hex) ou Solana Base58 (32-44 chars).',
            'address' => $addr,
        ];
    }

    if ($chainTrim !== '' && strtolower($chainTrim) !== 'auto') {
        $chainMeta = grid402_token_safety_resolve_chain($chainTrim);
        if ($chainMeta === null) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'UnsupportedTokenChain',
                'message' => "Rede '{$chainTrim}' nao suportada. Redes aceitas: base, ethereum, polygon, arbitrum, solana.",
            ];
        }
        if ($isEvmAddr && $chainMeta['type'] !== 'evm') {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'ChainAddressMismatch',
                'message' => 'Endereco EVM (0x...) incompativel com a rede Solana.',
            ];
        }
        if ($isSolAddr && $chainMeta['type'] !== 'solana') {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'ChainAddressMismatch',
                'message' => "Endereco Solana Base58 incompativel com a rede EVM '{$chainMeta['slug']}'.",
            ];
        }
    } else {
        $chains = grid402_token_safety_chains();
        $chainMeta = $isSolAddr ? $chains['solana'] : $chains['base'];
    }

    return [
        'ok' => true,
        'address' => $isEvmAddr ? strtolower($addr) : $addr,
        'address_type' => $isSolAddr ? 'solana' : 'evm',
        'chain' => $chainMeta['slug'],
        'chain_id' => $chainMeta['chain_id'],
        'chain_name' => $chainMeta['name'],
        'dex_chain' => $chainMeta['dex_chain'],
    ];
}

/**
 * Executa GET HTTPS curto para fontes publicas (suporta mock em $GLOBALS['grid402_token_safety_http_override']).
 *
 * @return array{ok: bool, status: int, data: ?array, error: ?string}
 */
function grid402_token_safety_http_get(string $url, int $timeoutSec = 4): array {
    $override = $GLOBALS['grid402_token_safety_http_override'] ?? null;
    if (is_callable($override)) {
        return $override($url, $timeoutSec);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSec),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Grid402-TokenSafety/1.0 (+https://grid402.online)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($status !== 200 || !is_string($raw) || $raw === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $err !== '' ? $err : ('HTTP ' . $status),
        ];
    }

    $decoded = @json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => 'InvalidJSON',
        ];
    }

    return ['ok' => true, 'status' => 200, 'data' => $decoded, 'error' => null];
}

/**
 * Converte valores heterogeneos da API GoPlus ("1", "0", 1, true, etc.) para bool.
 */
function grid402_ts_flag(mixed $val): ?bool {
    if ($val === null || $val === '') {
        return null;
    }
    if (is_bool($val)) {
        return $val;
    }
    $str = strtolower(trim((string) $val));
    if ($str === '1' || $str === 'true' || $str === 'yes') {
        return true;
    }
    if ($str === '0' || $str === 'false' || $str === 'no') {
        return false;
    }
    return null;
}

/**
 * Normaliza resposta GoPlus (EVM ou Solana) e DexScreener em score deterministico (0-100) + veredito.
 *
 * @return array<string, mixed>
 */
function grid402_token_safety_score(array $meta, ?array $goplusRaw, ?array $dexRaw): array {
    $address = (string) $meta['address'];
    $chain = (string) $meta['chain'];
    $isSolana = ($meta['address_type'] === 'solana');

    $gpEntry = null;
    if (is_array($goplusRaw) && isset($goplusRaw['result']) && is_array($goplusRaw['result'])) {
        foreach ($goplusRaw['result'] as $k => $v) {
            if (strtolower((string) $k) === strtolower($address) && is_array($v)) {
                $gpEntry = $v;
                break;
            }
        }
        if ($gpEntry === null && count($goplusRaw['result']) === 1) {
            $first = reset($goplusRaw['result']);
            if (is_array($first)) {
                $gpEntry = $first;
            }
        }
    }

    $dexPairs = [];
    if (is_array($dexRaw) && isset($dexRaw['pairs']) && is_array($dexRaw['pairs'])) {
        foreach ($dexRaw['pairs'] as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $pairChain = strtolower(trim((string) ($pair['chainId'] ?? '')));
            if ($pairChain === '' || $pairChain === strtolower((string) $meta['dex_chain'])) {
                $dexPairs[] = $pair;
            }
        }
        if (empty($dexPairs)) {
            $dexPairs = array_values(array_filter($dexRaw['pairs'], 'is_array'));
        }
    }

    usort($dexPairs, static function (array $a, array $b): int {
        $liqA = (float) ($a['liquidity']['usd'] ?? 0.0);
        $liqB = (float) ($b['liquidity']['usd'] ?? 0.0);
        return $liqB <=> $liqA;
    });

    $topPair = $dexPairs[0] ?? null;
    $hasGoPlus = is_array($gpEntry) && !empty($gpEntry);
    $hasDex = is_array($topPair);

    $tokenName = null;
    $tokenSymbol = null;
    if ($hasGoPlus) {
        $tokenName = $gpEntry['token_name'] ?? ($gpEntry['metadata']['name'] ?? null);
        $tokenSymbol = $gpEntry['token_symbol'] ?? ($gpEntry['metadata']['symbol'] ?? null);
    }
    if (($tokenName === null || $tokenName === '') && $hasDex) {
        $tokenName = $topPair['baseToken']['name'] ?? null;
    }
    if (($tokenSymbol === null || $tokenSymbol === '') && $hasDex) {
        $tokenSymbol = $topPair['baseToken']['symbol'] ?? null;
    }

    $isHoneypot = false;
    $cannotSellAll = false;
    $isMintable = false;
    $isProxy = false;
    $ownerChangeBalance = false;
    $isOpenSource = null;
    $buyTaxPct = null;
    $sellTaxPct = null;
    $top10HoldersPct = null;

    if ($hasGoPlus) {
        if ($isSolana) {
            $isHoneypot = grid402_ts_flag($gpEntry['non_transferable'] ?? null) === true;
            $isMintable = grid402_ts_flag($gpEntry['mintable']['status'] ?? ($gpEntry['mintable'] ?? null)) === true;
            $freezeable = grid402_ts_flag($gpEntry['freezable']['status'] ?? ($gpEntry['freezable'] ?? null)) === true;
            $ownerChangeBalance = $freezeable || (grid402_ts_flag($gpEntry['balance_mutable_authority']['status'] ?? null) === true);
            $feeRate = $gpEntry['transfer_fee']['current_fee_rate'] ?? null;
            if (is_numeric($feeRate)) {
                $pct = round(((float) $feeRate) * 100.0, 2);
                $buyTaxPct = $pct;
                $sellTaxPct = $pct;
            }
        } else {
            $isHoneypot = grid402_ts_flag($gpEntry['is_honeypot'] ?? null) === true;
            $cannotSellAll = grid402_ts_flag($gpEntry['cannot_sell_all'] ?? null) === true;
            $isMintable = grid402_ts_flag($gpEntry['is_mintable'] ?? null) === true;
            $isProxy = grid402_ts_flag($gpEntry['is_proxy'] ?? null) === true;
            $ownerChangeBalance = grid402_ts_flag($gpEntry['owner_change_balance'] ?? null) === true;
            $isOpenSource = grid402_ts_flag($gpEntry['is_open_source'] ?? null);
            if (isset($gpEntry['buy_tax']) && is_numeric($gpEntry['buy_tax'])) {
                $buyTaxPct = round(((float) $gpEntry['buy_tax']) * 100.0, 2);
            }
            if (isset($gpEntry['sell_tax']) && is_numeric($gpEntry['sell_tax'])) {
                $sellTaxPct = round(((float) $gpEntry['sell_tax']) * 100.0, 2);
            }
        }

        if (isset($gpEntry['holders']) && is_array($gpEntry['holders'])) {
            $sumTop = 0.0;
            $slice = array_slice($gpEntry['holders'], 0, 10);
            foreach ($slice as $h) {
                if (is_array($h) && isset($h['percent']) && is_numeric($h['percent'])) {
                    $sumTop += ((float) $h['percent']) * 100.0;
                }
            }
            if ($sumTop > 0.0) {
                $top10HoldersPct = round(min(100.0, $sumTop), 2);
            }
        }
    }

    $totalLiqUsd = 0.0;
    $totalVol24hUsd = 0.0;
    $priceUsd = null;
    if ($hasDex) {
        foreach ($dexPairs as $pair) {
            $totalLiqUsd += (float) ($pair['liquidity']['usd'] ?? 0.0);
            $totalVol24hUsd += (float) ($pair['volume']['h24'] ?? 0.0);
        }
        if (isset($topPair['priceUsd']) && is_numeric($topPair['priceUsd'])) {
            $priceUsd = (float) $topPair['priceUsd'];
        }
    }

    $score = 100;
    $riskFactors = [];

    if ($isHoneypot) {
        $score -= 85;
        $riskFactors[] = 'honeypot_or_non_transferable';
    }
    if ($cannotSellAll) {
        $score -= 75;
        $riskFactors[] = 'cannot_sell_all';
    }
    $maxTax = max((float) ($buyTaxPct ?? 0.0), (float) ($sellTaxPct ?? 0.0));
    if ($maxTax > 25.0) {
        $score -= 35;
        $riskFactors[] = 'extreme_transfer_tax_gt_25pct';
    } elseif ($maxTax > 5.0) {
        $score -= 15;
        $riskFactors[] = 'elevated_transfer_tax_gt_5pct';
    }
    if ($isMintable) {
        $score -= 15;
        $riskFactors[] = 'mintable_supply';
    }
    if ($isProxy) {
        $score -= 15;
        $riskFactors[] = 'upgradeable_proxy_contract';
    }
    if ($ownerChangeBalance) {
        $score -= 20;
        $riskFactors[] = 'owner_can_modify_balance_or_freeze';
    }
    if ($isOpenSource === false) {
        $score -= 15;
        $riskFactors[] = 'unverified_source_code';
    }
    if ($top10HoldersPct !== null && $top10HoldersPct > 50.0) {
        $score -= 15;
        $riskFactors[] = 'top10_holders_concentration_gt_50pct';
    }
    if ($hasDex && $totalLiqUsd < 10000.0) {
        $score -= 15;
        $riskFactors[] = 'low_dex_liquidity_lt_10k_usd';
    }

    $score = max(0, min(100, $score));
    if ($isHoneypot || $cannotSellAll || $score <= 24) {
        $verdict = 'CRITICAL';
    } elseif ($score <= 54) {
        $verdict = 'HIGH_RISK';
    } elseif ($score <= 79) {
        $verdict = 'CAUTION';
    } else {
        $verdict = 'SAFE';
    }

    return [
        'chain' => $chain,
        'chain_id' => $meta['chain_id'],
        'chain_name' => $meta['chain_name'],
        'address' => $address,
        'token_name' => is_string($tokenName) && $tokenName !== '' ? $tokenName : null,
        'token_symbol' => is_string($tokenSymbol) && $tokenSymbol !== '' ? $tokenSymbol : null,
        'safety_score' => $score,
        'verdict' => $verdict,
        'risk_factors' => $riskFactors,
        'flags' => [
            'is_honeypot' => $isHoneypot,
            'cannot_sell_all' => $cannotSellAll,
            'is_mintable' => $isMintable,
            'is_proxy' => $isProxy,
            'owner_can_modify_or_freeze' => $ownerChangeBalance,
            'is_open_source' => $isOpenSource,
        ],
        'taxes' => [
            'buy_tax_pct' => $buyTaxPct,
            'sell_tax_pct' => $sellTaxPct,
        ],
        'holders' => [
            'top10_holders_pct' => $top10HoldersPct,
        ],
        'market' => [
            'dex_pair_count' => count($dexPairs),
            'liquidity_usd' => $hasDex ? round($totalLiqUsd, 2) : null,
            'volume_24h_usd' => $hasDex ? round($totalVol24hUsd, 2) : null,
            'price_usd' => $priceUsd,
            'primary_dex' => $hasDex ? ($topPair['dexId'] ?? null) : null,
        ],
        'has_goplus_data' => $hasGoPlus,
        'has_dex_data' => $hasDex,
    ];
}

/**
 * Teaser gratuito para resposta HTTP 402.
 *
 * @return array<string, mixed>
 */
function grid402_service_token_safety_preview(string $address = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', string $chain = ''): array {
    $val = grid402_token_safety_validate_input($address !== '' ? $address : '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', $chain);
    if (empty($val['ok'])) {
        return [
            'preview' => [
                'address' => trim($address),
                'chain' => trim($chain) !== '' ? trim($chain) : 'base',
                'supported_chains' => ['base', 'ethereum', 'polygon', 'arbitrum', 'solana'],
                'sources' => ['GoPlus Security Public API', 'DexScreener Public API'],
            ],
            'locked_fields' => ['safety_score', 'verdict', 'flags', 'taxes', 'market', '_oracle_proof'],
        ];
    }

    return [
        'preview' => [
            'address' => $val['address'],
            'chain' => $val['chain'],
            'chain_id' => $val['chain_id'],
            'chain_name' => $val['chain_name'],
            'address_type' => $val['address_type'],
            'sources' => ['GoPlus Security Public API', 'DexScreener Public API'],
        ],
        'locked_fields' => ['safety_score', 'verdict', 'risk_factors', 'flags', 'taxes', 'holders', 'market', '_oracle_proof'],
    ];
}

/**
 * Executa a auditoria de seguranca de token/contrato multi-chain com cache Zero-DB (300s).
 *
 * @return array<string, mixed>
 */
function grid402_service_token_safety(string $address, string $chain = ''): array {
    $val = grid402_token_safety_validate_input($address, $chain);
    if (empty($val['ok'])) {
        return [
            'success' => false,
            'error' => $val['error'],
            'message' => $val['message'],
            'code' => (int) ($val['code'] ?? 400),
        ];
    }

    $privateDir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/../private_data');
    $cacheKey = hash('sha256', $val['chain'] . ':' . $val['address']);
    $cacheFile = $privateDir . '/token_safety_' . substr($cacheKey, 0, 24) . '.json';
    $cacheTtl = 300;
    $useOverride = is_callable($GLOBALS['grid402_token_safety_http_override'] ?? null);

    if (!$useOverride && file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = $rawCache ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < $cacheTtl) {
            $decodedCache['cached'] = true;
            return $decodedCache;
        }
    }

    if ($val['address_type'] === 'solana') {
        $goplusUrl = 'https://api.gopluslabs.io/api/v1/solana/token_security?contract_addresses=' . rawurlencode((string) $val['address']);
    } else {
        $goplusUrl = 'https://api.gopluslabs.io/api/v1/token_security/' . rawurlencode((string) $val['chain_id']) . '?contract_addresses=' . rawurlencode((string) $val['address']);
    }
    $dexUrl = 'https://api.dexscreener.com/latest/dex/tokens/' . rawurlencode((string) $val['address']);

    $gpResp = grid402_token_safety_http_get($goplusUrl, 4);
    $dexResp = grid402_token_safety_http_get($dexUrl, 4);

    $gpOk = !empty($gpResp['ok']) && is_array($gpResp['data']);
    $dexOk = !empty($dexResp['ok']) && is_array($dexResp['data']);

    if (!$gpOk && !$dexOk) {
        if (!$useOverride && file_exists($cacheFile)) {
            $staleRaw = @file_get_contents($cacheFile);
            $stale = $staleRaw ? @json_decode($staleRaw, true) : null;
            if (is_array($stale)) {
                $stale['cached'] = true;
                $stale['stale_fallback'] = true;
                return $stale;
            }
        }
        return [
            'success' => false,
            'error' => 'UpstreamUnavailable',
            'message' => 'Nao foi possivel consultar GoPlus Security nem DexScreener para este token no momento. Nenhum debito foi mantido.',
            'sources_status' => [
                'goplus' => 'unavailable (' . (string) ($gpResp['error'] ?? 'error') . ')',
                'dexscreener' => 'unavailable (' . (string) ($dexResp['error'] ?? 'error') . ')',
            ],
            'code' => 503,
        ];
    }

    $scored = grid402_token_safety_score(
        $val,
        $gpOk ? $gpResp['data'] : null,
        $dexOk ? $dexResp['data'] : null
    );

    if (empty($scored['has_goplus_data']) && empty($scored['has_dex_data'])) {
        return [
            'success' => false,
            'error' => 'TokenNotFound',
            'message' => 'Token ou contrato nao localizado nas fontes publicas GoPlus/DexScreener para a rede ' . $val['chain'] . '.',
            'chain' => $val['chain'],
            'address' => $val['address'],
            'code' => 404,
        ];
    }

    unset($scored['has_goplus_data'], $scored['has_dex_data']);

    $payload = array_merge([
        'protocol' => 'x402',
        'service' => 'token-safety',
        'status' => 'UP',
    ], $scored, [
        'sources_status' => [
            'goplus' => $gpOk ? 'ok' : ('unavailable (' . (string) ($gpResp['error'] ?? 'error') . ')'),
            'dexscreener' => $dexOk ? 'ok' : ('unavailable (' . (string) ($dexResp['error'] ?? 'error') . ')'),
        ],
        'legal_disclaimer' => 'Automated technical contract and DEX liquidity analysis derived from public third-party feeds (GoPlus Security, DexScreener). Informational only; does not constitute financial, legal, or investment advice.',
        'cached' => false,
        'cached_at' => time(),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ]);

    if (!$useOverride && function_exists('grid402_atomic_write')) {
        grid402_atomic_write($cacheFile, $payload);
    }

    return $payload;
}
