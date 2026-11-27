<?php
/**
 * Grid402 - Modulo de Pagamentos x402 `exact` (EIP-3009) via Facilitador Externo
 *
 * Aceita o payload x402 padrao (cabecalhos PAYMENT-SIGNATURE / X-PAYMENT em base64),
 * valida localmente os requisitos, protege contra replay por `payer:nonce` e delega
 * a verificacao e a liquidacao a um facilitador (padrao: Coinbase CDP), sem exigir
 * ETH de gas na carteira do oraculo.
 *
 * Os segredos do facilitador nunca sao escritos em respostas, logs ou telemetria.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/multichain.php';

const GRID402_FACILITATOR_VERIFY_TIMEOUT = 10;
const GRID402_FACILITATOR_SETTLE_TIMEOUT = 25;
const GRID402_NONCE_TTL_SECONDS = 604800; // 7 dias
const GRID402_CLOCK_SKEW_SECONDS = 30;

/**
 * Indica se pagamentos gasless Solana x402 via facilitador PayAI estao habilitados (V-05).
 * Padrao: false em producao ate a carteira Solana recebedora ser inicializada on-chain com SOL + ATA SPL USDC.
 * Habilite via env GRID402_SOLANA_X402_ENABLED=true (ou GRID402_SOLANA_ATA_READY=true).
 */
if (!function_exists('grid402_is_solana_x402_enabled')) {
    function grid402_is_solana_x402_enabled(): bool {
        if (defined('GRID402_SOLANA_X402_ENABLED')) {
            $v = GRID402_SOLANA_X402_ENABLED;
            if (is_bool($v)) {
                return $v;
            }
            return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
        }
        $envVal = function_exists('grid402_env') ? (string) grid402_env('GRID402_SOLANA_X402_ENABLED', '') : '';
        if (trim($envVal) === '' && function_exists('grid402_env')) {
            $envVal = (string) grid402_env('GRID402_SOLANA_ATA_READY', '');
        }
        return in_array(strtolower(trim($envVal)), ['1', 'true', 'yes', 'on'], true);
    }
}

/**
 * Remove prefixos de esquema comuns em headers de pagamento ("x402 ", "Payment ", "Bearer ").
 */
function grid402_normalize_payment_proof_header(string $raw): string {
    $trimmed = trim($raw);
    if (preg_match('/^(?:x402|payment|bearer)\s+(.+)$/is', $trimmed, $m)) {
        return trim($m[1]);
    }
    return $trimmed;
}

/**
 * Decodifica base64 padrao ou url-safe de forma estrita (tolerando whitespace MIME). Retorna null se invalido.
 */
function grid402_base64_decode_strict(string $value): ?string {
    $value = trim($value);
    if (preg_match('/%(?:2[bBfF]|3[dD])/', $value) === 1) {
        $value = rawurldecode($value);
    }
    $value = preg_replace('/\s+/', '', $value) ?? '';
    if ($value === '' || !preg_match('/^[A-Za-z0-9+\/_-]+={0,2}$/', $value)) {
        return null;
    }
    $normalized = strtr($value, '-_', '+/');
    $pad = strlen($normalized) % 4;
    if ($pad > 0) {
        $normalized .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($normalized, true);
    return $decoded === false ? null : $decoded;
}

/**
 * Tenta interpretar a prova como blob base64 ou objeto JSON cru contendo um payload x402 padrao.
 */
function grid402_try_decode_x402_blob(string $proof): ?array {
    $p = grid402_normalize_payment_proof_header($proof);
    if (strlen($p) >= 2 && str_starts_with($p, '{') && str_ends_with($p, '}')) {
        $data = @json_decode($p, true);
        if (is_array($data) && !array_is_list($data)) {
            return $data;
        }
        return null;
    }
    if (strlen($p) < 32) {
        return null;
    }
    $json = grid402_base64_decode_strict($p);
    if ($json === null) {
        return null;
    }
    $data = @json_decode($json, true);
    if (!is_array($data) || array_is_list($data)) {
        return null;
    }
    return $data;
}

/**
 * Classifica o formato da prova de pagamento recebida.
 * Retorna: empty | pass | demo | tx_hash | x402_payload | unknown
 */
function grid402_classify_payment_proof(string $proof): string {
    $p = trim($proof);
    if ($p === '') {
        return 'empty';
    }
    $lower = strtolower($p);
    if (str_starts_with($lower, 'pass_g402_')) {
        return 'pass';
    }
    if ($lower === 'demo' || $lower === 'sandbox') {
        return 'demo';
    }
    if (preg_match('/^0x[a-f0-9]{64}$/', $lower)) {
        return 'tx_hash';
    }
    if (grid402_try_decode_x402_blob($p) !== null) {
        return 'x402_payload';
    }
    return 'unknown';
}

/**
 * Retorna mapa de redes aceitas pelo fluxo x402 `exact` via facilitador externo.
 * Em producao (`base`): Base, Polygon, Arbitrum (EIP-3009) e Solana (SVM transfer via PayAI).
 * Em testnet (`base-sepolia`): exclusivamente Base Sepolia para isolar testes.
 */
function grid402_exact_supported_networks(): array {
    if (GRID402_X402_NETWORK === 'base-sepolia') {
        $spec = [
            'kind' => 'evm',
            'v1' => 'base-sepolia',
            'v2' => 'eip155:84532',
            'asset' => GRID402_X402_USDC_CONTRACT,
            'payTo' => GRID402_WALLET_ADDRESS,
            'extra' => ['name' => GRID402_X402_TOKEN_NAME, 'version' => GRID402_X402_TOKEN_VERSION],
        ];
        return [
            'eip155:84532' => $spec,
            'base-sepolia' => $spec,
        ];
    }

    $baseSpec = [
        'kind' => 'evm',
        'v1' => 'base',
        'v2' => 'eip155:8453',
        'asset' => GRID402_X402_USDC_CONTRACT,
        'payTo' => GRID402_WALLET_ADDRESS,
        'extra' => ['name' => GRID402_X402_TOKEN_NAME, 'version' => GRID402_X402_TOKEN_VERSION],
    ];
    $polygonSpec = [
        'kind' => 'evm',
        'v1' => 'polygon',
        'v2' => 'eip155:137',
        'asset' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
        'payTo' => GRID402_WALLET_ADDRESS,
        'extra' => ['name' => 'USD Coin', 'version' => '2'],
    ];
    $arbitrumSpec = [
        'kind' => 'evm',
        'v1' => 'arbitrum',
        'v2' => 'eip155:42161',
        'asset' => '0xaf88d065e77c8cc2239327c5edb3a432268e5831',
        'payTo' => GRID402_WALLET_ADDRESS,
        'extra' => ['name' => 'USD Coin', 'version' => '2'],
    ];
    $networks = [
        'eip155:8453' => $baseSpec,
        'base' => $baseSpec,
        'eip155:137' => $polygonSpec,
        'polygon' => $polygonSpec,
        'eip155:42161' => $arbitrumSpec,
        'arbitrum' => $arbitrumSpec,
    ];

    // V-05: so anuncia/aceita Solana x402 gasless quando habilitado via GRID402_SOLANA_X402_ENABLED=true
    if (grid402_is_solana_x402_enabled()) {
        $solWallet = defined('GRID402_SOLANA_WALLET_ADDRESS') ? (string) GRID402_SOLANA_WALLET_ADDRESS : 'CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C';
        $solMint = defined('GRID402_SOLANA_USDC_MINT') ? (string) GRID402_SOLANA_USDC_MINT : 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';
        $solFeePayer = defined('GRID402_PAYAI_SOLANA_FEE_PAYER') ? (string) GRID402_PAYAI_SOLANA_FEE_PAYER : 'CjNFTjvBhbJJd2B5ePPMHRLx1ELZpa8dwQgGL727eKww';
        $solanaSpec = [
            'kind' => 'svm',
            'v1' => 'solana',
            'v2' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
            'asset' => $solMint,
            'payTo' => $solWallet,
            'extra' => ['feePayer' => $solFeePayer],
        ];
        $networks['solana:5eykt4usfv8p8njdtrepy1vzqkqzkvdp'] = $solanaSpec;
        $networks['solana'] = $solanaSpec;
    }

    return $networks;
}

/**
 * Converte string hexadecimal arbitraria (sem 0x, ate 64 hex chars / uint256) para string decimal exata sem depender de bcmath/gmp.
 */
function grid402_hex_to_dec_string(string $hex): string {
    $clean = ltrim(strtolower(trim($hex)), '0');
    if ($clean === '') {
        return '0';
    }
    if (strlen($clean) <= 14) {
        return (string) hexdec($clean);
    }
    $dec = [0];
    $len = strlen($clean);
    for ($i = 0; $i < $len; $i++) {
        $carry = hexdec($clean[$i]);
        for ($j = count($dec) - 1; $j >= 0; $j--) {
            $val = $dec[$j] * 16 + $carry;
            $dec[$j] = $val % 10;
            $carry = intdiv($val, 10);
        }
        while ($carry > 0) {
            array_unshift($dec, $carry % 10);
            $carry = intdiv($carry, 10);
        }
    }
    return implode('', $dec);
}

/**
 * Normaliza inteiro decimal ou hex (0x...) para string decimal canonica exigida pelo PayAI (V-11).
 */
function grid402_normalize_uint_string(mixed $val): ?string {
    if (!is_string($val) && !is_int($val)) {
        return null;
    }
    $raw = trim((string) $val);
    if (preg_match('/^0x([a-fA-F0-9]{1,64})$/', $raw, $m) === 1) {
        return grid402_hex_to_dec_string($m[1]);
    }
    if (preg_match('/^\d{1,78}$/', $raw) === 1) {
        return $raw;
    }
    return null;
}

/**
 * Le um inteiro compact-u16 da serializacao de transacao binaria Solana.
 */
function grid402_solana_read_compact_u16(string $bytes, int &$offset): ?int {
    $len = strlen($bytes);
    $val = 0;
    $shift = 0;
    for ($i = 0; $i < 3; $i++) {
        if ($offset >= $len) {
            return null;
        }
        $b = ord($bytes[$offset++]);
        $val |= ($b & 0x7f) << $shift;
        if (($b & 0x80) === 0) {
            return $val;
        }
        $shift += 7;
    }
    return null;
}

/**
 * Extrai a quantidade de SPL Token Transfer/TransferChecked (u64 LE) de uma transacao Solana binaria (Legacy ou v0).
 * Usado como fallback em payloads Solana x402 v1 (sem campo accepted.amount) para nao subcreditar Pro/Fleet em /api/v1/pass.
 */
function grid402_solana_extract_spl_transfer_amount(string $txBytes): ?int {
    $len = strlen($txBytes);
    if ($len < 68) {
        return null;
    }
    $offset = 0;
    $sigCount = grid402_solana_read_compact_u16($txBytes, $offset);
    if ($sigCount === null || $sigCount < 1 || $sigCount > 16) {
        return null;
    }
    $offset += $sigCount * 64;
    if ($offset + 4 > $len) {
        return null;
    }

    // Versioned transaction prefix (bit 7 = 1 -> v0)
    $firstMsgByte = ord($txBytes[$offset]);
    if (($firstMsgByte & 0x80) !== 0) {
        $version = $firstMsgByte & 0x7f;
        if ($version !== 0) {
            return null;
        }
        $offset++;
        if ($offset + 3 > $len) {
            return null;
        }
    }

    // Message header: numRequiredSignatures, numReadonlySigned, numReadonlyUnsigned
    $offset += 3;
    $numAccounts = grid402_solana_read_compact_u16($txBytes, $offset);
    if ($numAccounts === null || $numAccounts < 2 || $numAccounts > 128) {
        return null;
    }
    if ($offset + ($numAccounts * 32) + 32 > $len) {
        return null;
    }

    $splTokenBin = hex2bin('06ddf6e1d765a193d9cbe146ceeb79ac1cb485ed5f5b37913a8cf5857eff00a9');
    $splToken22Bin = hex2bin('06ddf6e1ee758fde18425dbce46ccddab61afc4d83b90d27febdf928d8a18bfc');
    $tokenProgIndices = [];
    for ($i = 0; $i < $numAccounts; $i++) {
        $key = substr($txBytes, $offset, 32);
        $offset += 32;
        if ($key === $splTokenBin || $key === $splToken22Bin) {
            $tokenProgIndices[$i] = true;
        }
    }
    if (empty($tokenProgIndices)) {
        return null;
    }

    // Recent blockhash (32 bytes)
    $offset += 32;
    $numIx = grid402_solana_read_compact_u16($txBytes, $offset);
    if ($numIx === null || $numIx < 1 || $numIx > 64) {
        return null;
    }

    $maxFound = null;
    for ($i = 0; $i < $numIx; $i++) {
        if ($offset >= $len) {
            break;
        }
        $progIdx = ord($txBytes[$offset++]);
        $acctCount = grid402_solana_read_compact_u16($txBytes, $offset);
        if ($acctCount === null || $offset + $acctCount > $len) {
            break;
        }
        $offset += $acctCount;
        $dataLen = grid402_solana_read_compact_u16($txBytes, $offset);
        if ($dataLen === null || $offset + $dataLen > $len) {
            break;
        }
        $ixData = substr($txBytes, $offset, $dataLen);
        $offset += $dataLen;

        if (!isset($tokenProgIndices[$progIdx])) {
            continue;
        }
        $opcode = $dataLen >= 1 ? ord($ixData[0]) : -1;
        // SPL Token Transfer (opcode 3, 9 bytes) ou TransferChecked (opcode 12, 10 bytes, decimals === 6)
        $isTransfer = ($opcode === 3 && $dataLen === 9);
        $isTransferChecked = ($opcode === 12 && $dataLen === 10 && ord($ixData[9]) === 6);
        if ($isTransfer || $isTransferChecked) {
            $unpacked = @unpack('Pamt', substr($ixData, 1, 8));
            if (is_array($unpacked) && isset($unpacked['amt']) && (int) $unpacked['amt'] > 0) {
                $amt = (int) $unpacked['amt'];
                if ($maxFound === null || $amt > $maxFound) {
                    $maxFound = $amt;
                }
            }
        }
    }

    return $maxFound;
}

/**
 * Decodifica e normaliza o payload x402 padrao (v1 ou v2, EVM EIP-3009, EVM Permit2 ou Solana SVM).
 * Retorna ['ok' => true, ...campos normalizados] ou ['ok' => false, 'error' => string].
 */
function grid402_decode_x402_payload(string $proof): array {
    $raw = grid402_try_decode_x402_blob($proof);
    if ($raw === null) {
        return ['ok' => false, 'error' => 'Payload x402 invalido: esperado JSON em base64.'];
    }

    $version = (int) ($raw['x402Version'] ?? 1);
    if (!in_array($version, [1, 2], true)) {
        return ['ok' => false, 'error' => 'Versao x402 nao suportada. Use x402Version 1 ou 2.'];
    }

    $inner = $raw['payload'] ?? null;
    if (!is_array($inner)) {
        return ['ok' => false, 'error' => 'Payload x402 sem o campo obrigatorio "payload".'];
    }

    $accepted = is_array($raw['accepted'] ?? null) ? $raw['accepted'] : [];
    $scheme = (string) ($accepted['scheme'] ?? $raw['scheme'] ?? 'exact');
    $networkRaw = $accepted['network'] ?? $raw['network'] ?? null;
    $networkLower = is_string($networkRaw) ? strtolower(trim($networkRaw)) : '';

    // Caminho Solana SVM (`payload.transaction` em base64)
    $isSolanaNetwork = ($networkLower === 'solana' || str_starts_with($networkLower, 'solana:'));
    if ($isSolanaNetwork || (isset($inner['transaction']) && !isset($inner['authorization']) && !isset($inner['permit2Authorization']))) {
        if (!is_string($networkRaw) || trim($networkRaw) === '') {
            return ['ok' => false, 'error' => 'Payload x402 sem o campo "network" (aceito ou raiz).'];
        }
        $txB64 = $inner['transaction'] ?? null;
        if (!is_string($txB64) || trim($txB64) === '') {
            return ['ok' => false, 'error' => 'Payload x402 Solana sem o campo obrigatorio "payload.transaction" (base64).'];
        }
        $txBytes = grid402_base64_decode_strict($txB64);
        if ($txBytes === null || strlen($txBytes) < 64 || strlen($txBytes) > 16384) {
            return ['ok' => false, 'error' => 'Payload x402 Solana com "payload.transaction" base64 invalida.'];
        }
        $assetSvm = isset($accepted['asset']) && is_string($accepted['asset']) ? trim($accepted['asset']) : null;
        $payToSvm = isset($accepted['payTo']) && is_string($accepted['payTo']) ? trim($accepted['payTo']) : null;

        $amountCandidate = $accepted['amount']
            ?? $accepted['maxAmountRequired']
            ?? $raw['amount']
            ?? $raw['maxAmountRequired']
            ?? $inner['amount']
            ?? null;
        $amountSvm = null;
        if (is_string($amountCandidate) || is_int($amountCandidate)) {
            $amountSvm = trim((string) $amountCandidate);
        }
        if ($amountSvm === null || $amountSvm === '') {
            $extractedSpl = grid402_solana_extract_spl_transfer_amount($txBytes);
            if ($extractedSpl !== null && $extractedSpl > 0) {
                $amountSvm = (string) $extractedSpl;
            }
        }

        return [
            'ok' => true,
            'kind' => 'svm',
            'auth_type' => 'svm',
            'version' => $version,
            'raw' => $raw,
            'scheme' => strtolower($scheme),
            'network' => $networkLower,
            'network_raw' => trim($networkRaw),
            'asset' => $assetSvm,
            'payTo' => $payToSvm,
            'amount' => $amountSvm,
            'transaction' => trim($txB64),
            'authorization' => null,
        ];
    }

    // Caminho EVM (EIP-3009 TransferWithAuthorization OU Permit2 permit2Authorization)
    $signature = $inner['signature'] ?? null;
    if (!is_string($signature)) {
        return ['ok' => false, 'error' => 'Payload x402 sem "payload.signature" valido (hex).'];
    }
    $signature = trim($signature);
    if (preg_match('/^[a-fA-F0-9]{128,512}$/', $signature)) {
        $signature = '0x' . $signature;
        $raw['payload']['signature'] = $signature;
    } elseif (!preg_match('/^0x[a-fA-F0-9]+$/', $signature)) {
        return ['ok' => false, 'error' => 'Payload x402 sem "payload.signature" valido (hex).'];
    }

    if (!is_string($networkRaw) || $networkLower === '') {
        return ['ok' => false, 'error' => 'Payload x402 sem o campo "network" (aceito ou raiz).'];
    }
    $asset = isset($accepted['asset']) && is_string($accepted['asset']) ? strtolower($accepted['asset']) : null;

    // Subcaminho EVM Permit2 (@x402/evm v2 payload.permit2Authorization)
    if (isset($inner['permit2Authorization']) && is_array($inner['permit2Authorization']) && !isset($inner['authorization'])) {
        $p2 = $inner['permit2Authorization'];
        $p2From = $p2['from'] ?? null;
        $p2Spender = $p2['spender'] ?? null;
        $p2Permitted = is_array($p2['permitted'] ?? null) ? $p2['permitted'] : null;
        $p2Witness = is_array($p2['witness'] ?? null) ? $p2['witness'] : null;

        if (!is_string($p2From) || !preg_match('/^0x[a-fA-F0-9]{40}$/', trim($p2From))) {
            return ['ok' => false, 'error' => 'permit2Authorization.from deve ser um endereco EVM valido.'];
        }
        if (!is_string($p2Spender) || !preg_match('/^0x[a-fA-F0-9]{40}$/', trim($p2Spender))) {
            return ['ok' => false, 'error' => 'permit2Authorization.spender deve ser um endereco EVM valido.'];
        }
        if ($p2Permitted === null || !isset($p2Permitted['token'], $p2Permitted['amount'])) {
            return ['ok' => false, 'error' => 'Payload x402 Permit2 sem permit2Authorization.permitted (token e amount).'];
        }
        if (!is_string($p2Permitted['token']) || !preg_match('/^0x[a-fA-F0-9]{40}$/', trim($p2Permitted['token']))) {
            return ['ok' => false, 'error' => 'permit2Authorization.permitted.token deve ser um endereco EVM valido.'];
        }
        if ($p2Witness === null || !isset($p2Witness['to'], $p2Witness['validAfter'])) {
            return ['ok' => false, 'error' => 'Payload x402 Permit2 sem permit2Authorization.witness (to e validAfter).'];
        }
        if (!is_string($p2Witness['to']) || !preg_match('/^0x[a-fA-F0-9]{40}$/', trim($p2Witness['to']))) {
            return ['ok' => false, 'error' => 'permit2Authorization.witness.to deve ser um endereco EVM valido.'];
        }

        $p2AmountStr = grid402_normalize_uint_string($p2Permitted['amount']);
        $p2NonceStr = grid402_normalize_uint_string($p2['nonce'] ?? null);
        $p2DeadlineStr = grid402_normalize_uint_string($p2['deadline'] ?? null);
        $p2ValidAfterStr = grid402_normalize_uint_string($p2Witness['validAfter']);

        if ($p2AmountStr === null) {
            return ['ok' => false, 'error' => 'permit2Authorization.permitted.amount deve ser um inteiro decimal valido.'];
        }
        if ($p2NonceStr === null) {
            return ['ok' => false, 'error' => 'permit2Authorization.nonce deve ser um inteiro decimal ou hex valido.'];
        }
        if ($p2DeadlineStr === null) {
            return ['ok' => false, 'error' => 'permit2Authorization.deadline deve ser um inteiro decimal valido.'];
        }
        if ($p2ValidAfterStr === null) {
            return ['ok' => false, 'error' => 'permit2Authorization.witness.validAfter deve ser um inteiro decimal valido.'];
        }

        // V-11: garante que todos os campos numericos sejam serializados como string JSON para o PayAI
        $raw['payload']['permit2Authorization']['permitted']['amount'] = $p2AmountStr;
        $raw['payload']['permit2Authorization']['nonce'] = $p2NonceStr;
        $raw['payload']['permit2Authorization']['deadline'] = $p2DeadlineStr;
        $raw['payload']['permit2Authorization']['witness']['validAfter'] = $p2ValidAfterStr;

        return [
            'ok' => true,
            'kind' => 'evm',
            'auth_type' => 'permit2',
            'version' => $version,
            'raw' => $raw,
            'scheme' => strtolower($scheme),
            'network' => $networkLower,
            'network_raw' => trim($networkRaw),
            'asset' => $asset ?? strtolower(trim($p2Permitted['token'])),
            'permit2_token' => strtolower(trim($p2Permitted['token'])),
            'permit2_spender' => strtolower(trim($p2Spender)),
            'signature' => $signature,
            'authorization' => [
                'from' => strtolower(trim($p2From)),
                'to' => strtolower(trim($p2Witness['to'])),
                'value' => $p2AmountStr,
                'validAfter' => $p2ValidAfterStr,
                'validBefore' => $p2DeadlineStr,
                'nonce' => $p2NonceStr,
            ],
        ];
    }

    // Subcaminho EVM EIP-3009 (payload.authorization)
    $auth = $inner['authorization'] ?? null;
    if (!is_array($auth)) {
        return ['ok' => false, 'error' => 'Payload x402 sem "payload.authorization" (EIP-3009) ou "payload.permit2Authorization" (Permit2).'];
    }
    foreach (['from', 'to', 'value', 'validAfter', 'validBefore', 'nonce'] as $field) {
        if (!isset($auth[$field]) || (!is_string($auth[$field]) && !is_int($auth[$field]))) {
            return ['ok' => false, 'error' => "Payload x402 sem o campo obrigatorio authorization.{$field}."];
        }
    }
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', (string) $auth['from']) || !preg_match('/^0x[a-fA-F0-9]{40}$/', (string) $auth['to'])) {
        return ['ok' => false, 'error' => 'authorization.from e authorization.to devem ser enderecos EVM validos.'];
    }
    $nonceRaw = trim((string) $auth['nonce']);
    if (preg_match('/^[a-fA-F0-9]{64}$/', $nonceRaw)) {
        $nonceNorm = '0x' . strtolower($nonceRaw);
        $raw['payload']['authorization']['nonce'] = $nonceNorm;
    } elseif (preg_match('/^0x[a-fA-F0-9]{64}$/', $nonceRaw)) {
        $nonceNorm = strtolower($nonceRaw);
    } else {
        return ['ok' => false, 'error' => 'authorization.nonce deve ser 0x seguido de 64 caracteres hexadecimais.'];
    }
    $authNormNums = [];
    foreach (['value', 'validAfter', 'validBefore'] as $numField) {
        $strVal = grid402_normalize_uint_string($auth[$numField]);
        if ($strVal === null) {
            return ['ok' => false, 'error' => "authorization.{$numField} deve ser um inteiro decimal valido."];
        }
        $authNormNums[$numField] = $strVal;
        // V-11: forca string JSON explicitamente mesmo quando o cliente enviou int (evita HTTP 400 invalid_payload no PayAI)
        $raw['payload']['authorization'][$numField] = $strVal;
    }

    return [
        'ok' => true,
        'kind' => 'evm',
        'auth_type' => 'eip3009',
        'version' => $version,
        'raw' => $raw,
        'scheme' => strtolower($scheme),
        'network' => $networkLower,
        'network_raw' => trim($networkRaw),
        'asset' => $asset,
        'signature' => $signature,
        'authorization' => [
            'from' => strtolower((string) $auth['from']),
            'to' => strtolower((string) $auth['to']),
            'value' => $authNormNums['value'],
            'validAfter' => $authNormNums['validAfter'],
            'validBefore' => $authNormNums['validBefore'],
            'nonce' => $nonceNorm,
        ],
    ];
}

/**
 * Valida localmente se a autorizacao atende ao servico (sem acionar o facilitador).
 * Retorna ['valid' => true, 'network_spec' => array] ou ['valid' => false, 'code' => int, 'reason' => string, 'category' => string].
 */
function grid402_validate_exact_requirements(array $decoded, int $requiredRawUnits, ?int $now = null): array {
    $now = $now ?? time();
    $fail = static fn(string $reason, string $category = 'payment_rejected'): array => [
        'valid' => false,
        'code' => 402,
        'reason' => $reason,
        'category' => $category,
    ];

    if ($decoded['scheme'] !== 'exact') {
        return $fail('Esquema de pagamento nao suportado. O Grid402 aceita apenas o esquema "exact".');
    }

    $supported = grid402_exact_supported_networks();
    $netKey = strtolower((string) ($decoded['network'] ?? ''));
    if (!isset($supported[$netKey])) {
        if (($netKey === 'solana' || str_starts_with($netKey, 'solana:')) && !grid402_is_solana_x402_enabled()) {
            return $fail('Pagamentos x402 gasless na Solana estao temporariamente desabilitados (aguardando inicializacao da ATA SPL USDC). Use Base (eip155:8453), Polygon (eip155:137) ou Arbitrum (eip155:42161).');
        }
        $allowedDesc = (GRID402_X402_NETWORK === 'base-sepolia')
            ? 'Base Sepolia (eip155:84532)'
            : (grid402_is_solana_x402_enabled()
                ? 'Base (eip155:8453), Polygon (eip155:137), Arbitrum (eip155:42161) ou Solana (solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp)'
                : 'Base (eip155:8453), Polygon (eip155:137) ou Arbitrum (eip155:42161)');
        return $fail('Rede de pagamento incorreta. Use ' . $allowedDesc . '.');
    }

    $netSpec = $supported[$netKey];
    $kind = (string) ($decoded['kind'] ?? 'evm');
    if ($kind !== $netSpec['kind']) {
        return $fail('Tipo de payload incompativel com a rede informada (' . $netSpec['v2'] . ').');
    }

    if ($kind === 'svm') {
        if ($decoded['asset'] !== null && $decoded['asset'] !== $netSpec['asset']) {
            return $fail('Ativo de pagamento incorreto. Use o SPL USDC oficial da Solana: ' . $netSpec['asset'] . '.');
        }
        if ($decoded['payTo'] !== null && $decoded['payTo'] !== $netSpec['payTo']) {
            return $fail('Destinatario incorreto. A transacao Solana deve transferir para ' . $netSpec['payTo'] . '.');
        }
        if ($decoded['amount'] !== null && (!preg_match('/^\d{1,18}$/', (string) $decoded['amount']) || (int) $decoded['amount'] < $requiredRawUnits)) {
            return $fail('Valor insuficiente. Minimo exigido: ' . $requiredRawUnits . ' unidades de USDC (6 casas decimais).');
        }
        return ['valid' => true, 'network_spec' => $netSpec];
    }

    $auth = $decoded['authorization'];
    if ($decoded['asset'] !== null && strtolower((string) $decoded['asset']) !== strtolower((string) $netSpec['asset'])) {
        return $fail('Ativo de pagamento incorreto. Use o USDC oficial da rede: ' . $netSpec['asset'] . '.');
    }
    if (($decoded['auth_type'] ?? 'eip3009') === 'permit2') {
        $p2Token = strtolower((string) ($decoded['permit2_token'] ?? ''));
        if ($p2Token !== strtolower((string) $netSpec['asset'])) {
            return $fail('Token Permit2 incorreto. Use o USDC oficial da rede: ' . $netSpec['asset'] . '.');
        }
    }
    if ($auth['to'] !== strtolower((string) $netSpec['payTo'])) {
        return $fail('Destinatario incorreto. A autorizacao deve transferir para ' . $netSpec['payTo'] . '.');
    }
    $valStr = ltrim((string) $auth['value'], '0');
    $valStr = $valStr === '' ? '0' : $valStr;
    if (strlen($valStr) <= 18 && (int) $valStr < $requiredRawUnits) {
        return $fail('Valor insuficiente. Minimo exigido: ' . $requiredRawUnits . ' unidades de USDC (6 casas decimais).');
    }
    $vaStr = ltrim((string) $auth['validAfter'], '0');
    $vaStr = $vaStr === '' ? '0' : $vaStr;
    if (strlen($vaStr) > 15 || ($now + GRID402_CLOCK_SKEW_SECONDS) < (int) $vaStr) {
        return $fail('Autorizacao ainda nao e valida (validAfter no futuro).', 'authorization_expired');
    }
    $vbStr = ltrim((string) $auth['validBefore'], '0');
    $vbStr = $vbStr === '' ? '0' : $vbStr;
    if (strlen($vbStr) <= 15 && $now >= (int) $vbStr) {
        return $fail('Autorizacao expirada (validBefore ultrapassado). Gere uma nova assinatura.', 'authorization_expired');
    }

    return ['valid' => true, 'network_spec' => $netSpec];
}

/* -------------------------------------------------------------------------
 * Anti-replay por payer:nonce (mesmo padrao atomico de used_txs.json)
 * ---------------------------------------------------------------------- */

function grid402_nonce_key(string $payer, string $nonce): string {
    return strtolower($payer) . ':' . strtolower($nonce);
}

/**
 * Executa uma mutacao atomica no arquivo de nonces. O callback recebe o array por referencia
 * e retorna o resultado da operacao.
 */
function grid402_nonce_store_mutate(callable $mutator): mixed {
    $file = GRID402_FILE_USED_NONCES;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $fp = fopen($file, 'c+');
    if (!$fp) {
        return null;
    }

    $result = null;
    if (flock($fp, LOCK_EX)) {
        $entries = grid402_read_locked_json($fp, []);

        $now = time();
        foreach ($entries as $k => $entry) {
            if (($now - (int) ($entry['ts'] ?? 0)) > GRID402_NONCE_TTL_SECONDS) {
                unset($entries[$k]);
            }
        }

        $result = $mutator($entries, $now);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $result;
}

/**
 * Reserva atomicamente o nonce. Retorna false se ja utilizado (replay).
 */
function grid402_claim_nonce(string $key): bool {
    return (bool) grid402_nonce_store_mutate(static function (array &$entries, int $now) use ($key): bool {
        if (isset($entries[$key])) {
            return false;
        }
        $entries[$key] = ['ts' => $now, 'state' => 'reserved'];
        return true;
    });
}

function grid402_release_nonce(string $key): void {
    grid402_nonce_store_mutate(static function (array &$entries) use ($key): void {
        unset($entries[$key]);
    });
}

/**
 * Atualiza o estado do nonce: reserved | verified | pending | settled
 */
function grid402_set_nonce_state(string $key, string $state): void {
    grid402_nonce_store_mutate(static function (array &$entries, int $now) use ($key, $state): void {
        $entries[$key] = ['ts' => $entries[$key]['ts'] ?? $now, 'state' => $state];
    });
}

/* -------------------------------------------------------------------------
 * JWT do CDP (ES256 ou Ed25519) em PHP puro
 * ---------------------------------------------------------------------- */

function grid402_base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Converte assinatura ECDSA em DER para o formato R||S de 64 bytes exigido pelo JWT ES256.
 */
function grid402_ecdsa_der_to_raw(string $der, int $partLength = 32): ?string {
    $offset = 0;
    if (strlen($der) < 8 || ord($der[$offset++]) !== 0x30) {
        return null;
    }
    $seqLen = ord($der[$offset++]);
    if ($seqLen & 0x80) {
        $offset += ($seqLen & 0x7f);
    }
    $parts = [];
    for ($i = 0; $i < 2; $i++) {
        if (!isset($der[$offset]) || ord($der[$offset++]) !== 0x02) {
            return null;
        }
        $len = ord($der[$offset++]);
        $int = substr($der, $offset, $len);
        $offset += $len;
        $int = ltrim($int, "\x00");
        if (strlen($int) > $partLength) {
            return null;
        }
        $parts[] = str_pad($int, $partLength, "\x00", STR_PAD_LEFT);
    }
    return $parts[0] . $parts[1];
}

/**
 * Gera o JWT exigido pelo CDP para uma chamada ao facilitador.
 * Retorna ['ok' => true, 'token' => string] ou ['ok' => false, 'error' => string] (sem vazar segredos).
 */
function grid402_cdp_build_jwt(string $keyId, string $secret, string $method, string $host, string $path, ?int $now = null): array {
    if ($keyId === '' || $secret === '') {
        return ['ok' => false, 'error' => 'Credenciais do facilitador nao configuradas.'];
    }
    $now = $now ?? time();
    $secret = trim(str_replace('\\n', "\n", $secret));
    $isPem = str_contains($secret, '-----BEGIN');

    $header = [
        'alg' => $isPem ? 'ES256' : 'EdDSA',
        'typ' => 'JWT',
        'kid' => $keyId,
        'nonce' => bin2hex(random_bytes(16)),
    ];
    $claims = [
        'sub' => $keyId,
        'iss' => 'cdp',
        'nbf' => $now,
        'exp' => $now + 120,
        'uris' => [strtoupper($method) . ' ' . $host . $path],
    ];
    $signingInput = grid402_base64url((string) json_encode($header)) . '.' . grid402_base64url((string) json_encode($claims));

    if ($isPem) {
        $key = @openssl_pkey_get_private($secret);
        if ($key === false) {
            return ['ok' => false, 'error' => 'Chave privada EC do facilitador invalida.'];
        }
        $der = '';
        if (!openssl_sign($signingInput, $der, $key, OPENSSL_ALGO_SHA256)) {
            return ['ok' => false, 'error' => 'Falha ao assinar o JWT do facilitador.'];
        }
        $raw = grid402_ecdsa_der_to_raw($der);
        if ($raw === null) {
            return ['ok' => false, 'error' => 'Assinatura ES256 em formato inesperado.'];
        }
        return ['ok' => true, 'token' => $signingInput . '.' . grid402_base64url($raw)];
    }

    if (!function_exists('sodium_crypto_sign_detached')) {
        return ['ok' => false, 'error' => 'Extensao sodium indisponivel para chave Ed25519.'];
    }
    $bytes = base64_decode($secret, true);
    if ($bytes === false || !in_array(strlen($bytes), [32, 64], true)) {
        return ['ok' => false, 'error' => 'Chave privada Ed25519 do facilitador invalida.'];
    }
    $secretKey = strlen($bytes) === 32
        ? sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($bytes))
        : $bytes;
    $signature = sodium_crypto_sign_detached($signingInput, $secretKey);
    return ['ok' => true, 'token' => $signingInput . '.' . grid402_base64url($signature)];
}

/* -------------------------------------------------------------------------
 * Cliente do facilitador (verify / settle)
 * ---------------------------------------------------------------------- */

/**
 * Transporte HTTP padrao (cURL). Retorna ['status' => int, 'body' => ?string, 'error' => ?string].
 * Pode ser substituido em testes via $GLOBALS['grid402_facilitator_transport'].
 */
function grid402_facilitator_http(string $url, array $headers, string $body, int $timeout): array {
    $override = $GLOBALS['grid402_facilitator_transport'] ?? null;
    if (is_callable($override)) {
        return $override($url, $headers, $body, $timeout);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Grid402-x402-Resource/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = $response === false ? curl_error($ch) : null;
    curl_close($ch);

    return ['status' => $status, 'body' => is_string($response) ? $response : null, 'error' => $error];
}

/**
 * Registra erro estruturado de infraestrutura ou rejeicao do facilitador sem expor segredos ou assinaturas.
 */
function grid402_facilitator_log_error(string $endpoint, int $httpStatus, string $errorCode = '', string $errorMessage = ''): void {
    $safeEp = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $endpoint) ?: '/facilitator';
    $safeCode = substr(preg_replace('/[\r\n\t]+/', ' ', trim($errorCode)) ?? '', 0, 80);
    $safeMsg = substr(preg_replace('/[\r\n\t]+/', ' ', trim($errorMessage)) ?? '', 0, 220);
    @error_log(sprintf(
        '[Grid402][facilitator] endpoint=%s http_status=%d error=%s message=%s',
        $safeEp,
        $httpStatus,
        $safeCode !== '' ? $safeCode : 'none',
        $safeMsg !== '' ? $safeMsg : 'none'
    ));
}

/**
 * Verifica se ha credenciais Coinbase CDP configuradas (ou override de testes).
 */
function grid402_cdp_credentials_configured(): bool {
    $override = $GLOBALS['grid402_cdp_credentials_override'] ?? null;
    if (is_array($override)) {
        return trim((string) ($override['key_id'] ?? '')) !== ''
            && trim((string) ($override['key_secret'] ?? '')) !== '';
    }
    return GRID402_CDP_API_KEY_ID !== '' && GRID402_CDP_API_KEY_SECRET !== '';
}

/**
 * Decide como um perfil de facilitador sera autenticado:
 * 'override' (transporte de teste), 'cdp' (credenciais CDP) ou 'public' (PayAI / x402.org).
 */
function grid402_facilitator_auth_mode(?array $profile = null): string {
    if (is_callable($GLOBALS['grid402_facilitator_transport'] ?? null)) {
        return 'override';
    }
    if (is_array($profile) && !empty($profile['auth_mode'])) {
        return (string) $profile['auth_mode'];
    }
    if (grid402_cdp_credentials_configured()) {
        return 'cdp';
    }
    return 'public';
}

/**
 * Resolve a lista ordenada de facilitadores elegiveis para uma rede CAIP-2.
 * Em producao com routing=auto:
 * - Base Mainnet (eip155:8453): CDP primario (quando configurado) com fallback PayAI apenas pre-settle.
 * - Polygon, Arbitrum e Solana: PayAI diretamente.
 *
 * @return array<int, array<string, string>>
 */
function grid402_resolve_facilitator_candidates(string $networkV2): array {
    $routingOverride = $GLOBALS['grid402_facilitator_routing_override'] ?? null;
    $routing = is_string($routingOverride) && in_array($routingOverride, ['auto', 'single'], true)
        ? $routingOverride
        : (defined('GRID402_FACILITATOR_ROUTING') ? (string) GRID402_FACILITATOR_ROUTING : 'auto');

    $cdpUrl = defined('GRID402_CDP_FACILITATOR_URL') ? (string) GRID402_CDP_FACILITATOR_URL : 'https://api.cdp.coinbase.com/platform';
    $cdpVerify = defined('GRID402_CDP_VERIFY_PATH') ? (string) GRID402_CDP_VERIFY_PATH : '/v2/x402/verify';
    $cdpSettle = defined('GRID402_CDP_SETTLE_PATH') ? (string) GRID402_CDP_SETTLE_PATH : '/v2/x402/settle';

    $payaiUrl = defined('GRID402_PAYAI_FACILITATOR_URL') ? (string) GRID402_PAYAI_FACILITATOR_URL : 'https://facilitator.payai.network';
    $payaiVerify = defined('GRID402_PAYAI_VERIFY_PATH') ? (string) GRID402_PAYAI_VERIFY_PATH : '/verify';
    $payaiSettle = defined('GRID402_PAYAI_SETTLE_PATH') ? (string) GRID402_PAYAI_SETTLE_PATH : '/settle';

    $configuredUrl = rtrim((string) GRID402_FACILITATOR_URL, '/');
    $isCustomUrl = !in_array($configuredUrl, [
        rtrim($payaiUrl, '/'),
        rtrim($cdpUrl, '/'),
        'https://facilitator.payai.network',
        'https://api.cdp.coinbase.com/platform',
    ], true);

    if (GRID402_X402_NETWORK === 'base-sepolia' || $routing === 'single' || $isCustomUrl) {
        $singleName = str_contains($configuredUrl, 'api.cdp.coinbase.com') ? 'cdp' : (str_contains($configuredUrl, 'payai.network') ? 'payai' : 'custom');
        return [[
            'name' => $singleName,
            'base_url' => $configuredUrl,
            'verify_path' => (string) GRID402_FACILITATOR_VERIFY_PATH,
            'settle_path' => (string) GRID402_FACILITATOR_SETTLE_PATH,
            'auth_mode' => ($singleName === 'cdp' && grid402_cdp_credentials_configured()) ? 'cdp' : 'public',
        ]];
    }

    $cdpProfile = [
        'name' => 'cdp',
        'base_url' => $cdpUrl,
        'verify_path' => $cdpVerify,
        'settle_path' => $cdpSettle,
        'auth_mode' => 'cdp',
    ];
    $hasCdp = grid402_cdp_credentials_configured();
    $explicitPayaiVerify = function_exists('grid402_env') ? (string) grid402_env('GRID402_PAYAI_VERIFY_PATH', '') : '';
    $explicitPayaiSettle = function_exists('grid402_env') ? (string) grid402_env('GRID402_PAYAI_SETTLE_PATH', '') : '';
    $payaiProfile = [
        'name' => 'payai',
        'base_url' => $payaiUrl,
        'verify_path' => (!$hasCdp && $explicitPayaiVerify === '') ? (string) GRID402_FACILITATOR_VERIFY_PATH : $payaiVerify,
        'settle_path' => (!$hasCdp && $explicitPayaiSettle === '') ? (string) GRID402_FACILITATOR_SETTLE_PATH : $payaiSettle,
        'auth_mode' => 'public',
    ];

    $net = strtolower(trim($networkV2));
    if ($net === 'eip155:8453' && $hasCdp) {
        return [$cdpProfile, $payaiProfile];
    }

    return [$payaiProfile];
}

/**
 * Mapa publico seguro dos facilitadores ativos por rede (sem segredos).
 *
 * @return array<string, array<string, mixed>>
 */
function grid402_facilitator_active_map(): array {
    $networks = ['eip155:8453', 'eip155:137', 'eip155:42161'];
    if (grid402_is_solana_x402_enabled()) {
        $networks[] = 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp';
    }
    if (GRID402_X402_NETWORK === 'base-sepolia') {
        $networks = ['eip155:84532'];
    }
    $map = [];
    foreach ($networks as $net) {
        $cands = grid402_resolve_facilitator_candidates($net);
        $primary = $cands[0];
        $map[$net] = [
            'primary' => $primary['name'],
            'url' => $primary['base_url'],
            'fallback' => isset($cands[1]) ? $cands[1]['name'] : null,
            'fallback_url' => isset($cands[1]) ? $cands[1]['base_url'] : null,
        ];
    }
    return $map;
}

/**
 * Chama um endpoint do facilitador. Retorna:
 * ['ok' => bool, 'unavailable' => bool, 'http_status' => int, 'data' => array, 'error' => string, 'infra_error' => string]
 * `unavailable` = timeout, falha de rede, HTTP 5xx, HTTP 401/403/408/429 ou erro de infra/quota do PayAI (ex.: free_tier_exhausted).
 */
function grid402_facilitator_call(string $endpoint, array $body, int $timeout, ?array $profile = null): array {
    $baseUrl = is_array($profile) && !empty($profile['base_url']) ? rtrim((string) $profile['base_url'], '/') : GRID402_FACILITATOR_URL;
    $epPath = '/' . ltrim($endpoint, '/');
    $url = $baseUrl . $epPath;
    $parts = parse_url($url);
    $host = (string) ($parts['host'] ?? '');
    $path = (string) ($parts['path'] ?? '');

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $authMode = grid402_facilitator_auth_mode($profile);

    if ($authMode === 'cdp') {
        $credOverride = $GLOBALS['grid402_cdp_credentials_override'] ?? null;
        $keyId = is_array($credOverride) ? (string) ($credOverride['key_id'] ?? '') : GRID402_CDP_API_KEY_ID;
        $keySecret = is_array($credOverride) ? (string) ($credOverride['key_secret'] ?? '') : GRID402_CDP_API_KEY_SECRET;
        $jwt = grid402_cdp_build_jwt($keyId, $keySecret, 'POST', $host, $path);
        if (!$jwt['ok']) {
            grid402_facilitator_log_error($epPath, 0, 'cdp_jwt_error', (string) $jwt['error']);
            return ['ok' => false, 'unavailable' => true, 'http_status' => 0, 'data' => [], 'error' => $jwt['error'], 'infra_error' => 'cdp_jwt_error'];
        }
        $headers[] = 'Authorization: Bearer ' . $jwt['token'];
    }
    // 'override' (transporte de teste) e 'public' (facilitador sem auth):
    // nenhuma credencial é enviada; segredos nunca são exigidos.

    $http = grid402_facilitator_http($url, $headers, (string) json_encode($body, JSON_UNESCAPED_SLASHES), $timeout);
    $status = (int) ($http['status'] ?? 0);
    $rawBody = is_string($http['body'] ?? null) ? $http['body'] : '';
    $data = $rawBody !== '' ? @json_decode($rawBody, true) : null;
    $dataArr = is_array($data) ? $data : [];

    $errCode = (string) ($dataArr['error'] ?? $dataArr['code'] ?? '');
    $errMsg = (string) ($dataArr['message'] ?? $dataArr['invalidMessage'] ?? $dataArr['errorMessage'] ?? '');

    if ($status === 0 || $status >= 500 || !empty($http['error'])) {
        $detail = !empty($http['error']) ? (string) $http['error'] : ($errMsg !== '' ? $errMsg : 'HTTP ' . $status);
        grid402_facilitator_log_error($endpoint, $status, $errCode !== '' ? $errCode : 'transport_or_5xx', $detail);
        return [
            'ok' => false,
            'unavailable' => true,
            'http_status' => $status,
            'data' => $dataArr,
            'error' => 'Facilitador indisponivel.',
            'infra_error' => $errCode !== '' ? $errCode : ('http_' . $status),
        ];
    }

    // Erros de quota/infraestrutura do facilitador (ex.: PayAI 403 free_tier_exhausted, 429 rate limit, 401 auth, 408 timeout)
    // nunca devem ser mascarados como payment_rejected do cliente.
    $infraErrorCodes = [
        'free_tier_exhausted',
        'insufficient_credits',
        'credits_exhausted',
        'rate_limited',
        'rate_limit_exceeded',
        'too_many_requests',
        'service_unavailable',
        'facilitator_unavailable',
        'upstream_error',
    ];
    if (in_array($status, [401, 403, 408, 429], true) || in_array(strtolower($errCode), $infraErrorCodes, true)) {
        grid402_facilitator_log_error($endpoint, $status, $errCode !== '' ? $errCode : ('http_' . $status), $errMsg);
        return [
            'ok' => false,
            'unavailable' => true,
            'http_status' => $status,
            'data' => $dataArr,
            'error' => trim('Facilitador indisponivel (HTTP ' . $status . ($errCode !== '' ? ' ' . $errCode : '') . ').'),
            'infra_error' => $errCode !== '' ? $errCode : ('http_' . $status),
        ];
    }

    if (!is_array($data)) {
        grid402_facilitator_log_error($endpoint, $status, 'malformed_json', substr($rawBody, 0, 180));
        return [
            'ok' => false,
            'unavailable' => true,
            'http_status' => $status,
            'data' => [],
            'error' => 'Resposta invalida do facilitador.',
            'infra_error' => 'malformed_json',
        ];
    }

    $isOk = ($status >= 200 && $status < 300);
    if (!$isOk) {
        grid402_facilitator_log_error($endpoint, $status, $errCode !== '' ? $errCode : (string) ($dataArr['invalidReason'] ?? $dataArr['errorReason'] ?? 'http_4xx'), $errMsg);
    }

    return [
        'ok' => $isOk,
        'unavailable' => false,
        'http_status' => $status,
        'data' => $dataArr,
        'error' => $errMsg !== '' ? $errMsg : $errCode,
        'infra_error' => '',
    ];
}

/**
 * Monta os requisitos de pagamento do servico (fonte da verdade do servidor) conforme a versao, rede e metodo (EIP-3009 ou Permit2).
 */
function grid402_build_payment_requirements(int $version, int $requiredRawUnits, string $resourceUrl = '', ?string $networkKey = null, string $authType = 'eip3009', ?array $acceptedExtra = null, ?array $clientAccepted = null): array {
    $supported = grid402_exact_supported_networks();
    $resolvedKey = ($networkKey !== null && $networkKey !== '') ? strtolower(trim($networkKey)) : GRID402_X402_NETWORK_V2;
    $netSpec = $supported[$resolvedKey] ?? [
        'kind' => 'evm',
        'v1' => GRID402_X402_NETWORK_V1,
        'v2' => GRID402_X402_NETWORK_V2,
        'asset' => GRID402_X402_USDC_CONTRACT,
        'payTo' => GRID402_WALLET_ADDRESS,
        'extra' => ['name' => GRID402_X402_TOKEN_NAME, 'version' => GRID402_X402_TOKEN_VERSION],
    ];

    $extra = is_array($netSpec['extra'] ?? null) ? $netSpec['extra'] : [];
    if (($netSpec['kind'] ?? 'evm') === 'evm') {
        if ($authType === 'permit2') {
            $extra['assetTransferMethod'] = 'permit2';
            if (is_array($acceptedExtra) && !empty($acceptedExtra['spenderAddress']) && is_string($acceptedExtra['spenderAddress']) && preg_match('/^0x[a-fA-F0-9]{40}$/', trim($acceptedExtra['spenderAddress']))) {
                $extra['spenderAddress'] = trim($acceptedExtra['spenderAddress']);
            }
        } elseif (is_array($acceptedExtra) && isset($acceptedExtra['assetTransferMethod']) && is_string($acceptedExtra['assetTransferMethod']) && strtolower(trim($acceptedExtra['assetTransferMethod'])) === 'eip3009') {
            $extra['assetTransferMethod'] = 'eip3009';
        }
        if (is_array($acceptedExtra) && isset($acceptedExtra['eip2612GasSponsoring']) && is_bool($acceptedExtra['eip2612GasSponsoring'])) {
            $extra['eip2612GasSponsoring'] = $acceptedExtra['eip2612GasSponsoring'];
        }
    }

    $reqPayTo = (string) $netSpec['payTo'];
    $reqAsset = (string) $netSpec['asset'];
    $reqTimeout = 300;
    $reqAmountStr = (string) $requiredRawUnits;

    if ($version === 2 && is_array($clientAccepted)) {
        if (($netSpec['kind'] ?? 'evm') === 'evm') {
            if (is_string($clientAccepted['asset'] ?? null) && strtolower(trim($clientAccepted['asset'])) === strtolower($reqAsset)) {
                $reqAsset = trim($clientAccepted['asset']);
            }
            if (is_string($clientAccepted['payTo'] ?? null) && strtolower(trim($clientAccepted['payTo'])) === strtolower($reqPayTo)) {
                $reqPayTo = trim($clientAccepted['payTo']);
            }
        }
        if (isset($clientAccepted['maxTimeoutSeconds']) && is_numeric($clientAccepted['maxTimeoutSeconds'])) {
            $cliTimeout = (int) $clientAccepted['maxTimeoutSeconds'];
            if ($cliTimeout >= 30 && $cliTimeout <= 3600) {
                $reqTimeout = $cliTimeout;
            }
        }
        if (isset($clientAccepted['amount'])) {
            $accAmt = grid402_normalize_uint_string($clientAccepted['amount']);
            if ($accAmt !== null && strlen($accAmt) <= 18 && (int) $accAmt >= $requiredRawUnits) {
                $reqAmountStr = $accAmt;
            }
        }
    }

    $base = [
        'scheme' => 'exact',
        'payTo' => $reqPayTo,
        'maxTimeoutSeconds' => $reqTimeout,
        'asset' => $reqAsset,
        'extra' => $extra,
    ];
    if ($version === 1) {
        $absResource = trim($resourceUrl);
        if ($absResource !== '' && !preg_match('#^https?://#i', $absResource)) {
            $canonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? rtrim((string) GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
            $absResource = $canonicalBase . '/' . ltrim($absResource, '/');
        }
        return $base + [
            'network' => $netSpec['v1'],
            'maxAmountRequired' => (string) $requiredRawUnits,
            'resource' => $absResource,
            'description' => 'Grid402 micro-service',
            'mimeType' => 'application/json',
        ];
    }
    return $base + [
        'network' => $netSpec['v2'],
        'amount' => $reqAmountStr,
    ];
}

/**
 * Normaliza `resource` e `extensions.bazaar` do payload v2 para o endpoint real pago,
 * evitando URLs relativas, query strings duplicadas ou metadados de fallback (`summary`).
 */
function grid402_normalize_v2_discovery_payload(array &$paymentPayload, ?string $serviceSlug, string $priceDisplay, string $resourceUrl): void {
    if (isset($paymentPayload['accepted']) && is_array($paymentPayload['accepted']) && !isset($paymentPayload['accepted']['maxTimeoutSeconds'])) {
        $paymentPayload['accepted']['maxTimeoutSeconds'] = 300;
    }

    $clientResource = is_array($paymentPayload['resource'] ?? null) ? $paymentPayload['resource'] : [];
    $clientResourceUrl = (string) ($clientResource['url'] ?? '');
    $clientSlug = null;
    if ($clientResourceUrl !== '' && preg_match('#/api/v1/([a-z0-9-]+)#i', $clientResourceUrl, $cm)) {
        $clientSlug = strtolower($cm[1]);
    }

    $hasRealSlug = ($serviceSlug !== null && $serviceSlug !== '' && $serviceSlug !== 'summary');
    $canonicalPath = $hasRealSlug ? ('/api/v1/' . $serviceSlug) : $resourceUrl;
    $canonicalBase = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';

    if ($hasRealSlug && function_exists('grid402_build_resource_info')) {
        if (empty($clientResource) || $clientSlug !== $serviceSlug) {
            $paymentPayload['resource'] = grid402_build_resource_info($serviceSlug, $priceDisplay, $canonicalPath);
        } else {
            $paymentPayload['resource']['url'] = $canonicalBase . $canonicalPath;
        }
    } elseif (empty($paymentPayload['resource']) && function_exists('grid402_build_resource_info')) {
        $paymentPayload['resource'] = grid402_build_resource_info($serviceSlug, $priceDisplay, $resourceUrl);
    }

    $bazaar = is_array($paymentPayload['extensions']['bazaar'] ?? null) ? $paymentPayload['extensions']['bazaar'] : [];
    $sameSlugBazaar = (!$hasRealSlug || $clientSlug === null || $clientSlug === $serviceSlug);
    $reqMethod = strtoupper(trim((string) ($bazaar['info']['input']['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'))));
    if (!in_array($reqMethod, ['GET', 'POST', 'HEAD', 'DELETE'], true)) {
        $reqMethod = 'GET';
    }
    if ((empty($bazaar['schema']) || !$sameSlugBazaar) && function_exists('grid402_build_bazaar_extension')) {
        $existingExample = null;
        if ($sameSlugBazaar && !empty($bazaar['info']['output']['example']) && is_array($bazaar['info']['output']['example'])) {
            $existingExample = $bazaar['info']['output']['example'];
        }
        $paymentPayload['extensions'] = is_array($paymentPayload['extensions'] ?? null) ? $paymentPayload['extensions'] : [];
        $paymentPayload['extensions']['bazaar'] = grid402_build_bazaar_extension($existingExample, $serviceSlug, $reqMethod);
    } elseif (!empty($bazaar)) {
        unset(
            $paymentPayload['extensions']['bazaar']['info']['input']['discoverable'],
            $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['discoverable']
        );
        if (isset($paymentPayload['extensions']['bazaar']['schema']['properties']['input'])) {
            $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['additionalProperties'] = false;
            if (isset($paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['method']['enum'])) {
                $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['method']['enum'] = ['GET', 'POST', 'HEAD', 'DELETE'];
            }
            if ($reqMethod === 'POST') {
                if (isset($paymentPayload['extensions']['bazaar']['info']['input']['bodyType']) && !isset($paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['bodyType'])) {
                    $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['bodyType'] = ['type' => 'string'];
                }
                if (isset($paymentPayload['extensions']['bazaar']['info']['input']['body']) && !isset($paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['body'])) {
                    $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['body'] = ['type' => 'object'];
                }
            }
        }
    }

    // Preenche/atualiza queryParams reais da requisicao (ou defaults do servico) quando ausentes ou quando bazaar veio de slug divergente
    if ($hasRealSlug && !empty($paymentPayload['extensions']['bazaar']['info']['input'])) {
        $parsedQp = [];
        $queryRaw = (string) parse_url($resourceUrl, PHP_URL_QUERY);
        if ($queryRaw !== '') {
            parse_str($queryRaw, $parsedQp);
            unset($parsedQp['api'], $parsedQp['format']);
        }
        $currentQp = $paymentPayload['extensions']['bazaar']['info']['input']['queryParams'] ?? [];
        if (empty($currentQp) || (!$sameSlugBazaar && !empty($parsedQp))) {
            $fallbackQp = !empty($parsedQp)
                ? $parsedQp
                : (function_exists('grid402_bazaar_default_query_params') ? grid402_bazaar_default_query_params($serviceSlug) : []);
            if (!empty($fallbackQp) && is_array($fallbackQp)) {
                $cleanQp = [];
                $qpSchema = [];
                foreach ($fallbackQp as $k => $v) {
                    $cleanQp[(string) $k] = is_scalar($v) ? (string) $v : '';
                    $qpSchema[(string) $k] = ['type' => 'string'];
                }
                $paymentPayload['extensions']['bazaar']['info']['input']['queryParams'] = $cleanQp;
                $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['queryParams'] = [
                    'type' => 'object',
                    'properties' => $qpSchema,
                ];
            }
        }
    }

    // Evita `queryParams.properties: []` (array JSON invalido no Ajv 2020-12 apos json_decode(..., true))
    if (empty($paymentPayload['extensions']['bazaar']['info']['input']['queryParams'])) {
        unset(
            $paymentPayload['extensions']['bazaar']['info']['input']['queryParams'],
            $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['queryParams']
        );
    } elseif (empty($paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['queryParams']['properties'])) {
        $qpSchema = [];
        foreach ($paymentPayload['extensions']['bazaar']['info']['input']['queryParams'] as $k => $_v) {
            $qpSchema[(string) $k] = ['type' => 'string'];
        }
        $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['queryParams'] = [
            'type' => 'object',
            'properties' => $qpSchema,
        ];
    }
}

/**
 * Etapa 1 do pagamento x402 padrao: modo, decodificacao, validacao local,
 * reserva anti-replay e chamada ao /verify do facilitador (sem liquidar).
 */
function grid402_verify_exact_payment(string $rawProof, int $requiredRawUnits, ?string $resourceUrl = null, ?int $now = null): array {
    $mode = GRID402_FACILITATOR_MODE;
    if ($mode === 'disabled') {
        return [
            'valid' => false,
            'code' => 402,
            'category' => 'facilitator_disabled',
            'reason' => 'Pagamento x402 padrao (assinatura EIP-3009) nao esta habilitado neste servidor. Envie USDC na Base para a carteira do oraculo e repita a requisicao com "X-Payment: <tx_hash>" (ou use "X-Payment: demo" para testar).',
        ];
    }

    $decoded = grid402_decode_x402_payload($rawProof);
    if (!$decoded['ok']) {
        return ['valid' => false, 'code' => 400, 'category' => 'invalid_proof', 'reason' => $decoded['error']];
    }

    $local = grid402_validate_exact_requirements($decoded, $requiredRawUnits, $now);
    if (!$local['valid']) {
        return $local;
    }

    $netSpec = $local['network_spec'];
    $isSvm = (($decoded['kind'] ?? 'evm') === 'svm');
    $authType = (string) ($decoded['auth_type'] ?? ($isSvm ? 'svm' : 'eip3009'));
    if ($isSvm) {
        $nonceKey = 'solana:' . hash('sha256', (string) ($decoded['transaction'] ?? ''));
        $payerAddress = '';
        $decodedSvmAmt = (isset($decoded['amount']) && is_numeric($decoded['amount'])) ? (int) $decoded['amount'] : 0;
        $rawPaidUnits = ($decodedSvmAmt >= $requiredRawUnits) ? $decodedSvmAmt : $requiredRawUnits;
    } else {
        $auth = $decoded['authorization'];
        $nonceToken = ($authType === 'permit2') ? ('permit2:' . $auth['nonce']) : $auth['nonce'];
        $baseKey = grid402_nonce_key($auth['from'], $nonceToken);
        $nonceKey = ($netSpec['v2'] === 'eip155:8453' || $netSpec['v2'] === 'eip155:84532')
            ? $baseKey
            : ($netSpec['v2'] . ':' . $baseKey);
        $payerAddress = $auth['from'];
        $rawPaidUnits = (int) $auth['value'];
    }

    if (!grid402_claim_nonce($nonceKey)) {
        return [
            'valid' => false,
            'code' => 403,
            'category' => 'replay',
            'reason' => 'Replay attack detectado: esta autorizacao (payer e nonce) ja foi utilizada.',
        ];
    }

    $paymentPayload = $decoded['raw'];
    if ($decoded['version'] === 2) {
        $serviceSlug = null;
        if (is_string($resourceUrl) && $resourceUrl !== '') {
            if (preg_match('#/api/v1/([a-z0-9-]+)#i', $resourceUrl, $m)) {
                $serviceSlug = strtolower($m[1]);
            } else {
                $qStr = (string) parse_url($resourceUrl, PHP_URL_QUERY);
                if ($qStr !== '') {
                    parse_str($qStr, $qParams);
                    if (!empty($qParams['api']) && is_string($qParams['api']) && preg_match('/^[a-z0-9-]+$/i', trim($qParams['api']))) {
                        $serviceSlug = strtolower(trim($qParams['api']));
                    }
                }
            }
        }
        $priceDisplay = number_format($requiredRawUnits / 1e6, 4, '.', '');
        grid402_normalize_v2_discovery_payload($paymentPayload, $serviceSlug, $priceDisplay, (string) $resourceUrl);
    }

    $clientAccepted = is_array($paymentPayload['accepted'] ?? null) ? $paymentPayload['accepted'] : null;
    if ($decoded['version'] === 2 && !$isSvm && is_array($clientAccepted) && isset($clientAccepted['amount'])) {
        $accAmtNorm = grid402_normalize_uint_string($clientAccepted['amount']);
        if ($accAmtNorm !== null && strlen($accAmtNorm) <= 18 && (int) $accAmtNorm > $rawPaidUnits) {
            unset($clientAccepted['amount']);
        }
    }
    $acceptedExtra = is_array($clientAccepted['extra'] ?? null) ? $clientAccepted['extra'] : null;
    $paymentRequirements = grid402_build_payment_requirements($decoded['version'], $requiredRawUnits, (string) $resourceUrl, $decoded['network'], $authType, $acceptedExtra, $clientAccepted);

    // Normaliza paymentPayload.accepted (x402 v2) para evitar falsas rejeicoes de deepEqual no PayAI
    if ($decoded['version'] === 2 && isset($paymentPayload['accepted']) && is_array($paymentPayload['accepted'])) {
        $paymentPayload['accepted']['scheme'] = $paymentRequirements['scheme'];
        $paymentPayload['accepted']['network'] = $paymentRequirements['network'];
        $paymentPayload['accepted']['amount'] = $paymentRequirements['amount'];
        $paymentPayload['accepted']['asset'] = $paymentRequirements['asset'];
        $paymentPayload['accepted']['payTo'] = $paymentRequirements['payTo'];
        $paymentPayload['accepted']['maxTimeoutSeconds'] = $paymentRequirements['maxTimeoutSeconds'];
        $paymentPayload['accepted']['extra'] = $paymentRequirements['extra'];
    }

    $requestBody = [
        'x402Version' => $decoded['version'],
        'paymentPayload' => $paymentPayload,
        'paymentRequirements' => $paymentRequirements,
    ];
    $unavailable = static fn(string $reason = 'Servico de liquidacao temporariamente indisponivel. Tente novamente em instantes.'): array => [
        'valid' => false,
        'code' => 503,
        'category' => 'facilitator_unavailable',
        'reason' => $reason,
        'retry_after' => 30,
    ];

    // 1) Verificacao com suporte Dual-Facilitator e fallback seguro pre-settle
    $candidates = grid402_resolve_facilitator_candidates($netSpec['v2']);
    $verify = null;
    $chosenProfile = $candidates[0] ?? [
        'name' => 'default',
        'base_url' => GRID402_FACILITATOR_URL,
        'verify_path' => GRID402_FACILITATOR_VERIFY_PATH,
        'settle_path' => GRID402_FACILITATOR_SETTLE_PATH,
        'auth_mode' => grid402_facilitator_auth_mode(),
    ];

    foreach ($candidates as $idx => $candidateProfile) {
        $verifyPath = (string) ($candidateProfile['verify_path'] ?? GRID402_FACILITATOR_VERIFY_PATH);
        $attempt = grid402_facilitator_call($verifyPath, $requestBody, GRID402_FACILITATOR_VERIFY_TIMEOUT, $candidateProfile);
        $verify = $attempt;
        $chosenProfile = $candidateProfile;

        if (!empty($attempt['unavailable'])) {
            $hasNext = isset($candidates[$idx + 1]);
            if ($hasNext) {
                $nextName = (string) ($candidates[$idx + 1]['name'] ?? 'fallback');
                grid402_facilitator_log_error($verifyPath, (int) ($attempt['http_status'] ?? 0), 'fallback_verify', 'Fallback de ' . ($candidateProfile['name'] ?? 'primary') . ' para ' . $nextName);
                continue;
            }
            break;
        }
        // Resposta conclusiva (valida ou rejeicao por merito do cliente): nunca aciona fallback
        break;
    }

    if (!is_array($verify) || !empty($verify['unavailable'])) {
        grid402_release_nonce($nonceKey);
        return $unavailable();
    }
    if (!$verify['ok'] || empty($verify['data']['isValid'])) {
        grid402_release_nonce($nonceKey);
        $why = (string) ($verify['data']['invalidReason'] ?? $verify['data']['invalidMessage'] ?? $verify['data']['error'] ?? $verify['data']['message'] ?? 'assinatura ou autorizacao invalida');
        return [
            'valid' => false,
            'code' => 402,
            'category' => 'payment_rejected',
            'reason' => 'O facilitador rejeitou a autorizacao: ' . substr($why, 0, 200),
            'facilitator' => (string) ($chosenProfile['name'] ?? 'default'),
        ];
    }

    if ($payerAddress === '' && !empty($verify['data']['payer']) && is_string($verify['data']['payer'])) {
        $payerAddress = trim($verify['data']['payer']);
    }
    if ($isSvm) {
        $verifyAmt = $verify['data']['amount'] ?? $verify['data']['value'] ?? null;
        if ((is_string($verifyAmt) || is_int($verifyAmt)) && preg_match('/^\d{1,18}$/', trim((string) $verifyAmt)) === 1) {
            $parsedVerifyAmt = (int) trim((string) $verifyAmt);
            if ($parsedVerifyAmt > $rawPaidUnits) {
                $rawPaidUnits = $parsedVerifyAmt;
            }
        }
    }

    if ($mode === 'verify_only') {
        grid402_set_nonce_state($nonceKey, 'verified');
        return [
            'valid' => true,
            'sandbox' => true,
            'source' => 'facilitator_verify_only',
            'payer' => $payerAddress,
            'network' => $netSpec['v2'],
            'facilitator' => (string) ($chosenProfile['name'] ?? 'default'),
            'reason' => 'Autorizacao verificada pelo facilitador (modo verify_only: nao liquidada, nao faturada).',
            'code' => 200,
        ];
    }

    grid402_set_nonce_state($nonceKey, 'verified');
    return [
        'valid' => true,
        'sandbox' => false,
        'source' => 'facilitator',
        'payer' => $payerAddress,
        'raw_amount' => $rawPaidUnits,
        'network' => $netSpec['v2'],
        'facilitator' => (string) ($chosenProfile['name'] ?? 'default'),
        'needs_settle' => true,
        '_settle_ctx' => [
            'nonce_key' => $nonceKey,
            'request_body' => $requestBody,
            'is_svm' => $isSvm,
            'net_v2' => $netSpec['v2'],
            'payer' => $payerAddress,
            'raw_amount' => $rawPaidUnits,
            'facilitator' => $chosenProfile,
        ],
        'reason' => 'Autorizacao x402 verificada; aguardando liquidacao.',
        'code' => 200,
    ];
}

/**
 * Etapa 2 do pagamento x402 padrao: liquida via /settle uma autorizacao previamente verificada.
 * INVIOLAVEL: nunca realiza fallback para outro facilitador no /settle para impedir dupla cobranca.
 */
function grid402_settle_exact_payment(array $verified): array {
    if (empty($verified['needs_settle']) || !is_array($verified['_settle_ctx'] ?? null)) {
        return $verified;
    }

    $ctx = $verified['_settle_ctx'];
    $nonceKey = (string) ($ctx['nonce_key'] ?? '');
    $requestBody = is_array($ctx['request_body'] ?? null) ? $ctx['request_body'] : [];
    $isSvm = !empty($ctx['is_svm']);
    $netV2 = (string) ($ctx['net_v2'] ?? GRID402_X402_NETWORK_V2);
    $payerAddress = (string) ($ctx['payer'] ?? '');
    $rawPaidUnits = (int) ($ctx['raw_amount'] ?? 0);
    $chosenProfile = is_array($ctx['facilitator'] ?? null) ? $ctx['facilitator'] : null;
    $settlePath = is_array($chosenProfile) && !empty($chosenProfile['settle_path'])
        ? (string) $chosenProfile['settle_path']
        : GRID402_FACILITATOR_SETTLE_PATH;
    $facilitatorName = is_array($chosenProfile) ? (string) ($chosenProfile['name'] ?? 'default') : 'default';

    // 2) Liquidacao (sem retentativa e sem fallback para nunca liquidar duas vezes)
    $settle = grid402_facilitator_call($settlePath, $requestBody, GRID402_FACILITATOR_SETTLE_TIMEOUT, $chosenProfile);
    if ($settle['unavailable']) {
        if ($nonceKey !== '') {
            grid402_set_nonce_state($nonceKey, 'pending');
        }
        return [
            'valid' => false,
            'code' => 503,
            'category' => 'facilitator_unavailable',
            'facilitator' => $facilitatorName,
            'reason' => 'Liquidacao sem confirmacao do facilitador. Nao reenvie esta autorizacao; aguarde e consulte o saldo antes de tentar novamente.',
            'retry_after' => 30,
        ];
    }
    if (!$settle['ok'] || empty($settle['data']['success'])) {
        if ($nonceKey !== '') {
            grid402_release_nonce($nonceKey);
        }
        $why = (string) ($settle['data']['errorReason'] ?? $settle['data']['errorMessage'] ?? $settle['data']['error'] ?? $settle['data']['message'] ?? 'liquidacao nao concluida');
        return [
            'valid' => false,
            'code' => 402,
            'category' => 'payment_rejected',
            'facilitator' => $facilitatorName,
            'reason' => 'O facilitador nao concluiu a liquidacao: ' . substr($why, 0, 200),
        ];
    }

    $rawSettleTx = trim((string) ($settle['data']['transaction'] ?? ''));
    if ($rawSettleTx === '') {
        if ($nonceKey !== '') {
            grid402_set_nonce_state($nonceKey, 'pending');
        }
        grid402_facilitator_log_error(
            $settlePath,
            (int) ($settle['http_status'] ?? 200),
            'empty_settlement_transaction',
            'Facilitator returned success=true with empty transaction hash'
        );
        return [
            'valid' => false,
            'code' => 503,
            'category' => 'facilitator_unavailable',
            'facilitator' => $facilitatorName,
            'reason' => 'O facilitador retornou sucesso sem hash de transacao (transaction vazio). Aguarde antes de tentar novamente.',
            'retry_after' => 30,
        ];
    }

    if ($nonceKey !== '') {
        grid402_set_nonce_state($nonceKey, 'settled');
    }
    $settlementTx = $isSvm ? $rawSettleTx : strtolower($rawSettleTx);
    if ($payerAddress === '' && !empty($settle['data']['payer']) && is_string($settle['data']['payer'])) {
        $payerAddress = trim($settle['data']['payer']);
    }
    $settledNet = (string) ($settle['data']['network'] ?? $netV2);
    // V-10: normaliza rede (v1 legado como 'polygon'/'arbitrum'/'solana' e v2 CAIP-2) antes de gravar em used_txs.json
    $settledSlug = grid402_normalize_network_slug($settledNet)
        ?? grid402_normalize_network_slug($netV2)
        ?? 'base';
    $txLockKey = match ($settledSlug) {
        'base' => strtolower($settlementTx),
        'solana' => 'solana:' . $settlementTx,
        default => $settledSlug . ':' . strtolower($settlementTx),
    };
    if (function_exists('grid402_claim_transaction_lock')) {
        grid402_claim_transaction_lock($txLockKey);
    }

    return [
        'valid' => true,
        'sandbox' => false,
        'source' => 'facilitator',
        'facilitator' => $facilitatorName,
        'payer' => $payerAddress,
        'settlement_tx' => $settlementTx,
        'lock_key' => $txLockKey,
        'raw_amount' => $rawPaidUnits,
        'network' => $settledNet,
        'reason' => 'Pagamento x402 liquidado pelo facilitador.',
        'code' => 200,
    ];
}

/**
 * Libera a reserva de nonce de uma autorizacao verificada quando a execucao do servico falha antes do settle.
 */
function grid402_abort_verified_exact_payment(array $verified): void {
    if (!empty($verified['needs_settle']) && !empty($verified['_settle_ctx']['nonce_key'])) {
        grid402_release_nonce((string) $verified['_settle_ctx']['nonce_key']);
    }
}

/**
 * Fluxo completo do pagamento x402 padrao: modo, decodificacao, validacao local,
 * anti-replay, verify e settle. Retorna o mesmo formato de grid402_validate_payment().
 */
function grid402_validate_exact_payment(string $rawProof, int $requiredRawUnits, ?string $resourceUrl = null, ?int $now = null): array {
    $verified = grid402_verify_exact_payment($rawProof, $requiredRawUnits, $resourceUrl, $now);
    if (empty($verified['valid']) || empty($verified['needs_settle'])) {
        return $verified;
    }
    return grid402_settle_exact_payment($verified);
}

/**
 * Constroi o valor do cabecalho PAYMENT-RESPONSE (base64 de JSON) com o recibo de liquidacao.
 */
function grid402_build_payment_response_header(array $validation): string {
    return base64_encode((string) json_encode([
        'success' => true,
        'transaction' => $validation['settlement_tx'] ?? null,
        'network' => $validation['network'] ?? GRID402_X402_NETWORK_V2,
        'payer' => $validation['payer'] ?? null,
    ], JSON_UNESCAPED_SLASHES));
}

