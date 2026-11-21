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

const GRID402_FACILITATOR_VERIFY_TIMEOUT = 5;
const GRID402_FACILITATOR_SETTLE_TIMEOUT = 10;
const GRID402_NONCE_TTL_SECONDS = 604800; // 7 dias
const GRID402_CLOCK_SKEW_SECONDS = 30;

/**
 * Decodifica base64 padrao ou url-safe de forma estrita. Retorna null se invalido.
 */
function grid402_base64_decode_strict(string $value): ?string {
    $value = trim($value);
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
 * Tenta interpretar a prova como blob base64 contendo um objeto JSON (payload x402 padrao).
 */
function grid402_try_decode_x402_blob(string $proof): ?array {
    if (strlen(trim($proof)) < 32) {
        return null;
    }
    $json = grid402_base64_decode_strict($proof);
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

    return [
        'eip155:8453' => $baseSpec,
        'base' => $baseSpec,
        'eip155:137' => $polygonSpec,
        'polygon' => $polygonSpec,
        'eip155:42161' => $arbitrumSpec,
        'arbitrum' => $arbitrumSpec,
        'solana:5eykt4usfv8p8njdtrepy1vzqkqzkvdp' => $solanaSpec,
        'solana' => $solanaSpec,
    ];
}

/**
 * Decodifica e normaliza o payload x402 padrao (v1 ou v2, EVM EIP-3009 ou Solana SVM).
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
    if ($isSolanaNetwork || (isset($inner['transaction']) && !isset($inner['authorization']))) {
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
        $amountSvm = isset($accepted['amount']) && (is_string($accepted['amount']) || is_int($accepted['amount']))
            ? (string) $accepted['amount']
            : (isset($accepted['maxAmountRequired']) && (is_string($accepted['maxAmountRequired']) || is_int($accepted['maxAmountRequired']))
                ? (string) $accepted['maxAmountRequired']
                : null);

        return [
            'ok' => true,
            'kind' => 'svm',
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

    // Caminho EVM (EIP-3009 TransferWithAuthorization)
    $signature = $inner['signature'] ?? null;
    if (!is_string($signature) || !preg_match('/^0x[a-fA-F0-9]+$/', $signature)) {
        return ['ok' => false, 'error' => 'Payload x402 sem "payload.signature" valido (hex).'];
    }
    $auth = $inner['authorization'] ?? null;
    if (!is_array($auth)) {
        return ['ok' => false, 'error' => 'Payload x402 sem "payload.authorization" (EIP-3009).'];
    }
    foreach (['from', 'to', 'value', 'validAfter', 'validBefore', 'nonce'] as $field) {
        if (!isset($auth[$field]) || (!is_string($auth[$field]) && !is_int($auth[$field]))) {
            return ['ok' => false, 'error' => "Payload x402 sem o campo obrigatorio authorization.{$field}."];
        }
    }
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', (string) $auth['from']) || !preg_match('/^0x[a-fA-F0-9]{40}$/', (string) $auth['to'])) {
        return ['ok' => false, 'error' => 'authorization.from e authorization.to devem ser enderecos EVM validos.'];
    }
    if (!preg_match('/^0x[a-fA-F0-9]{64}$/', (string) $auth['nonce'])) {
        return ['ok' => false, 'error' => 'authorization.nonce deve ser 0x seguido de 64 caracteres hexadecimais.'];
    }
    foreach (['value', 'validAfter', 'validBefore'] as $numField) {
        if (!preg_match('/^\d{1,18}$/', (string) $auth[$numField])) {
            return ['ok' => false, 'error' => "authorization.{$numField} deve ser um inteiro decimal valido."];
        }
    }

    if (!is_string($networkRaw) || $networkLower === '') {
        return ['ok' => false, 'error' => 'Payload x402 sem o campo "network" (aceito ou raiz).'];
    }
    $asset = isset($accepted['asset']) && is_string($accepted['asset']) ? strtolower($accepted['asset']) : null;

    return [
        'ok' => true,
        'kind' => 'evm',
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
            'value' => (string) $auth['value'],
            'validAfter' => (string) $auth['validAfter'],
            'validBefore' => (string) $auth['validBefore'],
            'nonce' => strtolower((string) $auth['nonce']),
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
        $allowedDesc = (GRID402_X402_NETWORK === 'base-sepolia')
            ? 'Base Sepolia (eip155:84532)'
            : 'Base (eip155:8453), Polygon (eip155:137), Arbitrum (eip155:42161) ou Solana (solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp)';
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
    if ($auth['to'] !== strtolower((string) $netSpec['payTo'])) {
        return $fail('Destinatario incorreto. A autorizacao deve transferir para ' . $netSpec['payTo'] . '.');
    }
    if ((int) $auth['value'] < $requiredRawUnits) {
        return $fail('Valor insuficiente. Minimo exigido: ' . $requiredRawUnits . ' unidades de USDC (6 casas decimais).');
    }
    if (($now + GRID402_CLOCK_SKEW_SECONDS) < (int) $auth['validAfter']) {
        return $fail('Autorizacao ainda nao e valida (validAfter no futuro).', 'authorization_expired');
    }
    if ($now >= (int) $auth['validBefore']) {
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
        CURLOPT_CONNECTTIMEOUT => 3,
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
 * Decide como o facilitador sera autenticado:
 * 'override' (transporte de teste), 'cdp' (credenciais CDP) ou
 * 'public' (facilitador publico sem auth, ex.: x402.org testnet / PayAI / Mogami).
 */
function grid402_facilitator_auth_mode(): string {
    if (is_callable($GLOBALS['grid402_facilitator_transport'] ?? null)) {
        return 'override';
    }
    if (GRID402_CDP_API_KEY_ID !== '' && GRID402_CDP_API_KEY_SECRET !== '') {
        return 'cdp';
    }
    return 'public';
}

/**
 * Chama um endpoint do facilitador. Retorna:
 * ['ok' => bool, 'unavailable' => bool, 'data' => array, 'error' => string]
 * `unavailable` = timeout, falha de rede, erro 5xx ou credenciais ausentes (nunca vaza segredos).
 */
function grid402_facilitator_call(string $endpoint, array $body, int $timeout): array {
    $baseUrl = GRID402_FACILITATOR_URL;
    $url = $baseUrl . $endpoint;
    $parts = parse_url($url);
    $host = (string) ($parts['host'] ?? '');
    $path = (string) ($parts['path'] ?? '');

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $authMode = grid402_facilitator_auth_mode();

    if ($authMode === 'cdp') {
        $jwt = grid402_cdp_build_jwt(GRID402_CDP_API_KEY_ID, GRID402_CDP_API_KEY_SECRET, 'POST', $host, $path);
        if (!$jwt['ok']) {
            return ['ok' => false, 'unavailable' => true, 'data' => [], 'error' => $jwt['error']];
        }
        $headers[] = 'Authorization: Bearer ' . $jwt['token'];
    }
    // 'override' (transporte de teste) e 'public' (facilitador sem auth):
    // nenhuma credencial é enviada; segredos nunca são exigidos.

    $http = grid402_facilitator_http($url, $headers, (string) json_encode($body, JSON_UNESCAPED_SLASHES), $timeout);
    $status = (int) ($http['status'] ?? 0);
    if ($status === 0 || $status >= 500 || !empty($http['error'])) {
        return ['ok' => false, 'unavailable' => true, 'data' => [], 'error' => 'Facilitador indisponivel.'];
    }

    $data = @json_decode((string) ($http['body'] ?? ''), true);
    if (!is_array($data)) {
        return ['ok' => false, 'unavailable' => true, 'data' => [], 'error' => 'Resposta invalida do facilitador.'];
    }
    return ['ok' => $status >= 200 && $status < 300, 'unavailable' => false, 'data' => $data, 'error' => ''];
}

/**
 * Monta os requisitos de pagamento do servico (fonte da verdade do servidor) conforme a versao e a rede do payload.
 */
function grid402_build_payment_requirements(int $version, int $requiredRawUnits, string $resourceUrl = '', ?string $networkKey = null): array {
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

    $base = [
        'scheme' => 'exact',
        'payTo' => $netSpec['payTo'],
        'maxTimeoutSeconds' => 300,
        'asset' => $netSpec['asset'],
        'extra' => $netSpec['extra'],
    ];
    if ($version === 1) {
        return $base + [
            'network' => $netSpec['v1'],
            'maxAmountRequired' => (string) $requiredRawUnits,
            'resource' => $resourceUrl,
            'description' => 'Grid402 micro-service',
            'mimeType' => 'application/json',
        ];
    }
    return $base + [
        'network' => $netSpec['v2'],
        'amount' => (string) $requiredRawUnits,
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
    if ((empty($bazaar['schema']) || !$sameSlugBazaar) && function_exists('grid402_build_bazaar_extension')) {
        $existingExample = null;
        if ($sameSlugBazaar && !empty($bazaar['info']['output']['example']) && is_array($bazaar['info']['output']['example'])) {
            $existingExample = $bazaar['info']['output']['example'];
        }
        $paymentPayload['extensions'] = is_array($paymentPayload['extensions'] ?? null) ? $paymentPayload['extensions'] : [];
        $paymentPayload['extensions']['bazaar'] = grid402_build_bazaar_extension($existingExample, $serviceSlug);
    } elseif (!empty($bazaar)) {
        unset(
            $paymentPayload['extensions']['bazaar']['info']['input']['discoverable'],
            $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['discoverable']
        );
        if (isset($paymentPayload['extensions']['bazaar']['schema']['properties']['input'])) {
            $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['additionalProperties'] = false;
            if (isset($paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['method']['enum'])) {
                $paymentPayload['extensions']['bazaar']['schema']['properties']['input']['properties']['method']['enum'] = ['GET', 'HEAD', 'DELETE'];
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
    if ($isSvm) {
        $nonceKey = 'solana:' . hash('sha256', (string) ($decoded['transaction'] ?? ''));
        $payerAddress = '';
        $rawPaidUnits = (int) ($decoded['amount'] ?? $requiredRawUnits);
    } else {
        $auth = $decoded['authorization'];
        $baseKey = grid402_nonce_key($auth['from'], $auth['nonce']);
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
        if (is_string($resourceUrl) && preg_match('#/api/v1/([a-z0-9-]+)#i', $resourceUrl, $m)) {
            $serviceSlug = strtolower($m[1]);
        }
        $priceDisplay = number_format($requiredRawUnits / 1e6, 4, '.', '');
        grid402_normalize_v2_discovery_payload($paymentPayload, $serviceSlug, $priceDisplay, (string) $resourceUrl);
    }

    $requestBody = [
        'x402Version' => $decoded['version'],
        'paymentPayload' => $paymentPayload,
        'paymentRequirements' => grid402_build_payment_requirements($decoded['version'], $requiredRawUnits, (string) $resourceUrl, $decoded['network']),
    ];
    $unavailable = static fn(string $reason = 'Servico de liquidacao temporariamente indisponivel. Tente novamente em instantes.'): array => [
        'valid' => false,
        'code' => 503,
        'category' => 'facilitator_unavailable',
        'reason' => $reason,
        'retry_after' => 30,
    ];

    // 1) Verificacao
    $verify = grid402_facilitator_call(GRID402_FACILITATOR_VERIFY_PATH, $requestBody, GRID402_FACILITATOR_VERIFY_TIMEOUT);
    if ($verify['unavailable']) {
        grid402_release_nonce($nonceKey);
        return $unavailable();
    }
    if (!$verify['ok'] || empty($verify['data']['isValid'])) {
        grid402_release_nonce($nonceKey);
        $why = (string) ($verify['data']['invalidReason'] ?? $verify['data']['invalidMessage'] ?? 'assinatura ou autorizacao invalida');
        return [
            'valid' => false,
            'code' => 402,
            'category' => 'payment_rejected',
            'reason' => 'O facilitador rejeitou a autorizacao: ' . substr($why, 0, 200),
        ];
    }

    if ($payerAddress === '' && !empty($verify['data']['payer']) && is_string($verify['data']['payer'])) {
        $payerAddress = trim($verify['data']['payer']);
    }

    if ($mode === 'verify_only') {
        grid402_set_nonce_state($nonceKey, 'verified');
        return [
            'valid' => true,
            'sandbox' => true,
            'source' => 'facilitator_verify_only',
            'payer' => $payerAddress,
            'network' => $netSpec['v2'],
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
        'needs_settle' => true,
        '_settle_ctx' => [
            'nonce_key' => $nonceKey,
            'request_body' => $requestBody,
            'is_svm' => $isSvm,
            'net_v2' => $netSpec['v2'],
            'payer' => $payerAddress,
            'raw_amount' => $rawPaidUnits,
        ],
        'reason' => 'Autorizacao x402 verificada; aguardando liquidacao.',
        'code' => 200,
    ];
}

/**
 * Etapa 2 do pagamento x402 padrao: liquida via /settle uma autorizacao previamente verificada.
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

    // 2) Liquidacao (sem retentativa automatica para nao liquidar duas vezes)
    $settle = grid402_facilitator_call(GRID402_FACILITATOR_SETTLE_PATH, $requestBody, GRID402_FACILITATOR_SETTLE_TIMEOUT);
    if ($settle['unavailable']) {
        if ($nonceKey !== '') {
            grid402_set_nonce_state($nonceKey, 'pending');
        }
        return [
            'valid' => false,
            'code' => 503,
            'category' => 'facilitator_unavailable',
            'reason' => 'Liquidacao sem confirmacao do facilitador. Nao reenvie esta autorizacao; aguarde e consulte o saldo antes de tentar novamente.',
            'retry_after' => 30,
        ];
    }
    if (!$settle['ok'] || empty($settle['data']['success'])) {
        if ($nonceKey !== '') {
            grid402_release_nonce($nonceKey);
        }
        $why = (string) ($settle['data']['errorReason'] ?? $settle['data']['errorMessage'] ?? 'liquidacao nao concluida');
        return [
            'valid' => false,
            'code' => 402,
            'category' => 'payment_rejected',
            'reason' => 'O facilitador nao concluiu a liquidacao: ' . substr($why, 0, 200),
        ];
    }

    if ($nonceKey !== '') {
        grid402_set_nonce_state($nonceKey, 'settled');
    }
    $rawSettleTx = trim((string) ($settle['data']['transaction'] ?? ''));
    $settlementTx = $isSvm ? $rawSettleTx : strtolower($rawSettleTx);
    if ($payerAddress === '' && !empty($settle['data']['payer']) && is_string($settle['data']['payer'])) {
        $payerAddress = trim($settle['data']['payer']);
    }
    $settledNet = (string) ($settle['data']['network'] ?? $netV2);
    $txLockKey = '';
    if ($settlementTx !== '') {
        $txLockKey = match (true) {
            $settledNet === 'eip155:137' => 'polygon:' . strtolower($settlementTx),
            $settledNet === 'eip155:42161' => 'arbitrum:' . strtolower($settlementTx),
            str_starts_with(strtolower($settledNet), 'solana:') => 'solana:' . $settlementTx,
            default => strtolower($settlementTx),
        };
        if (function_exists('grid402_claim_transaction_lock')) {
            grid402_claim_transaction_lock($txLockKey);
        }
    }

    return [
        'valid' => true,
        'sandbox' => false,
        'source' => 'facilitator',
        'payer' => $payerAddress,
        'settlement_tx' => $settlementTx !== '' ? $settlementTx : null,
        'lock_key' => $txLockKey !== '' ? $txLockKey : null,
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

