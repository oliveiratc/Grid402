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
 * Decodifica e normaliza o payload x402 padrao (v1 ou v2).
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

    $accepted = is_array($raw['accepted'] ?? null) ? $raw['accepted'] : [];
    $scheme = (string) ($accepted['scheme'] ?? $raw['scheme'] ?? 'exact');
    $network = $accepted['network'] ?? $raw['network'] ?? null;
    if (!is_string($network) || $network === '') {
        return ['ok' => false, 'error' => 'Payload x402 sem o campo "network" (aceito ou raiz).'];
    }
    $asset = isset($accepted['asset']) && is_string($accepted['asset']) ? strtolower($accepted['asset']) : null;

    return [
        'ok' => true,
        'version' => $version,
        'raw' => $raw,
        'scheme' => strtolower($scheme),
        'network' => strtolower($network),
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
 * Retorna ['valid' => true] ou ['valid' => false, 'code' => int, 'reason' => string, 'category' => string].
 */
function grid402_validate_exact_requirements(array $decoded, int $requiredRawUnits, ?int $now = null): array {
    $now = $now ?? time();
    $auth = $decoded['authorization'];
    $fail = static fn(string $reason, string $category = 'payment_rejected'): array => [
        'valid' => false,
        'code' => 402,
        'reason' => $reason,
        'category' => $category,
    ];

    if ($decoded['scheme'] !== 'exact') {
        return $fail('Esquema de pagamento nao suportado. O Grid402 aceita apenas o esquema "exact".');
    }
    if (!in_array($decoded['network'], [GRID402_X402_NETWORK_V2, GRID402_X402_NETWORK_V1], true)) {
        return $fail('Rede de pagamento incorreta. Use a rede ' . (GRID402_X402_NETWORK === 'base-sepolia' ? 'Base Sepolia (eip155:84532)' : 'Base Mainnet (eip155:' . GRID402_X402_CHAIN_ID . ')') . '.');
    }
    if ($decoded['asset'] !== null && $decoded['asset'] !== GRID402_X402_USDC_CONTRACT) {
        return $fail('Ativo de pagamento incorreto. Use o USDC oficial da rede: ' . GRID402_X402_USDC_CONTRACT . '.');
    }
    if ($auth['to'] !== GRID402_WALLET_ADDRESS) {
        return $fail('Destinatario incorreto. A autorizacao deve transferir para ' . GRID402_WALLET_ADDRESS . '.');
    }
    if ((int) $auth['value'] < $requiredRawUnits) {
        return $fail('Valor insuficiente. Minimo exigido: ' . $requiredRawUnits . ' unidades de USDC (6 casas decimais).');
    }
    if ($now < (int) $auth['validAfter']) {
        return $fail('Autorizacao ainda nao e valida (validAfter no futuro).', 'authorization_expired');
    }
    if ($now >= (int) $auth['validBefore']) {
        return $fail('Autorizacao expirada (validBefore ultrapassado). Gere uma nova assinatura.', 'authorization_expired');
    }

    return ['valid' => true];
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
        $size = filesize($file);
        $content = ($size > 0) ? (string) fread($fp, $size) : '';
        $entries = $content !== '' ? (@json_decode($content, true) ?: []) : [];

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
 * Monta os requisitos de pagamento do servico (fonte da verdade do servidor) conforme a versao do payload.
 */
function grid402_build_payment_requirements(int $version, int $requiredRawUnits, string $resourceUrl = ''): array {
    $base = [
        'scheme' => 'exact',
        'payTo' => GRID402_WALLET_ADDRESS,
        'maxTimeoutSeconds' => 300,
        'asset' => GRID402_X402_USDC_CONTRACT,
        'extra' => ['name' => GRID402_X402_TOKEN_NAME, 'version' => GRID402_X402_TOKEN_VERSION],
    ];
    if ($version === 1) {
        return $base + [
            'network' => GRID402_X402_NETWORK_V1,
            'maxAmountRequired' => (string) $requiredRawUnits,
            'resource' => $resourceUrl,
            'description' => 'Grid402 micro-service',
            'mimeType' => 'application/json',
        ];
    }
    return $base + [
        'network' => GRID402_X402_NETWORK_V2,
        'amount' => (string) $requiredRawUnits,
    ];
}

/**
 * Fluxo completo do pagamento x402 padrao: modo, decodificacao, validacao local,
 * anti-replay, verify e settle. Retorna o mesmo formato de grid402_validate_payment().
 */
function grid402_validate_exact_payment(string $rawProof, int $requiredRawUnits, ?string $resourceUrl = null, ?int $now = null): array {
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

    $auth = $decoded['authorization'];
    $nonceKey = grid402_nonce_key($auth['from'], $auth['nonce']);
    if (!grid402_claim_nonce($nonceKey)) {
        return [
            'valid' => false,
            'code' => 403,
            'category' => 'replay',
            'reason' => 'Replay attack detectado: esta autorizacao (payer e nonce) ja foi utilizada.',
        ];
    }

    $requestBody = [
        'x402Version' => $decoded['version'],
        'paymentPayload' => $decoded['raw'],
        'paymentRequirements' => grid402_build_payment_requirements($decoded['version'], $requiredRawUnits, (string) $resourceUrl),
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

    if ($mode === 'verify_only') {
        grid402_set_nonce_state($nonceKey, 'verified');
        return [
            'valid' => true,
            'sandbox' => true,
            'source' => 'facilitator_verify_only',
            'payer' => $auth['from'],
            'reason' => 'Autorizacao verificada pelo facilitador (modo verify_only: nao liquidada, nao faturada).',
            'code' => 200,
        ];
    }

    // 2) Liquidacao (sem retentativa automatica para nao liquidar duas vezes)
    $settle = grid402_facilitator_call(GRID402_FACILITATOR_SETTLE_PATH, $requestBody, GRID402_FACILITATOR_SETTLE_TIMEOUT);
    if ($settle['unavailable']) {
        grid402_set_nonce_state($nonceKey, 'pending');
        return $unavailable('Liquidacao sem confirmacao do facilitador. Nao reenvie esta autorizacao; aguarde e consulte o saldo antes de tentar novamente.');
    }
    if (!$settle['ok'] || empty($settle['data']['success'])) {
        grid402_release_nonce($nonceKey);
        $why = (string) ($settle['data']['errorReason'] ?? $settle['data']['errorMessage'] ?? 'liquidacao nao concluida');
        return [
            'valid' => false,
            'code' => 402,
            'category' => 'payment_rejected',
            'reason' => 'O facilitador nao concluiu a liquidacao: ' . substr($why, 0, 200),
        ];
    }

    grid402_set_nonce_state($nonceKey, 'settled');
    $settlementTx = strtolower((string) ($settle['data']['transaction'] ?? ''));
    return [
        'valid' => true,
        'sandbox' => false,
        'source' => 'facilitator',
        'payer' => $auth['from'],
        'settlement_tx' => $settlementTx !== '' ? $settlementTx : null,
        'raw_amount' => (int) $auth['value'],
        'network' => (string) ($settle['data']['network'] ?? GRID402_X402_NETWORK_V2),
        'reason' => 'Pagamento x402 liquidado pelo facilitador.',
        'code' => 200,
    ];
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

