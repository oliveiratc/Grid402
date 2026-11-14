<?php
/**
 * Grid402 // Servico de Assinatura Criptografica de Oraculo (Proof of Data)
 * 
 * Atribui autenticidade e garantia matematica de integridade as respostas tecnicas do Grid402
 * utilizando ECDSA sobre a curva secp256k1 compativel com Ethereum e Base Mainnet.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    http_response_code(403);
    die('Acesso direto proibido.');
}

// Chaves determinísticas padrão para testes locais e desenvolvimento
if (!defined('GRID402_DEFAULT_ORACLE_PUBKEY')) {
    define('GRID402_DEFAULT_ORACLE_PUBKEY', "-----BEGIN PUBLIC KEY-----\nMFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEZh7FI41OltM2/P7miW0L/D0OE6FzEQRk\n2YJoMPEXO/mKiZ6oSi5l9MCIvS/W7IoIpe3z1EV9rYVEsADOgHHkJw==\n-----END PUBLIC KEY-----");
}

if (!defined('GRID402_DEFAULT_ORACLE_PRIVKEY')) {
    define('GRID402_DEFAULT_ORACLE_PRIVKEY', "-----BEGIN PRIVATE KEY-----\nMIGEAgEAMBAGByqGSM49AgEGBSuBBAAKBG0wawIBAQQgbx7yBcDIEAJOTpIxrSji\nFuMG+zevhna+MX1NMJ1+yZ+hRANCAARmHsUjjU6W0zb8/uaJbQv8PQ4ToXMRBGTZ\ngmgw8Rc7+YqJnqhKLmX0wIi9L9bsigil7fPURX2thUSwAM6AceQn\n-----END PRIVATE KEY-----");
}

/**
 * Normaliza recursivamente o array para canonização determinística
 */
function grid402_canonicalize_payload(array $data): array {
    $exclude = ['_oracle_proof', 'sandbox_info', '_x402_settlement'];
    $clean = [];
    foreach ($data as $key => $val) {
        if (in_array($key, $exclude, true)) {
            continue;
        }
        if (is_array($val)) {
            $clean[$key] = grid402_canonicalize_payload($val);
        } else {
            $clean[$key] = $val;
        }
    }
    if (!empty($clean) && array_keys($clean) !== range(0, count($clean) - 1)) {
        ksort($clean);
    }
    return $clean;
}

/**
 * Serializa os dados em JSON canônico determinístico
 */
function grid402_canonical_json(array $data): string {
    $clean = grid402_canonicalize_payload($data);
    return (string) json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Calcula o hash SHA-256 do payload canônico
 */
function grid402_compute_data_hash(array $data): string {
    $json = grid402_canonical_json($data);
    return '0x' . hash('sha256', $json);
}

/**
 * Assina o payload e anexa o bloco _oracle_proof
 */
function grid402_sign_data(array &$data, bool $isSandbox = false): void {
    if (isset($data['error']) || isset($data['payment_required']) || (isset($data['code']) && $data['code'] !== 200)) {
        return;
    }

    $timestamp = time();
    $dataHash = grid402_compute_data_hash($data);
    $messageToSign = $dataHash . ':' . $timestamp . ':' . ($isSandbox ? 'sandbox' : 'mainnet');

    $privKeyPem = (string)(getenv('GRID402_ORACLE_PRIVATE_KEY') ?: (defined('GRID402_ORACLE_PRIVATE_KEY') && GRID402_ORACLE_PRIVATE_KEY !== '' ? GRID402_ORACLE_PRIVATE_KEY : GRID402_DEFAULT_ORACLE_PRIVKEY));
    // .env guarda PEMs em linha unica com \n literal (parser nao suporta multilinha)
    $privKeyPem = (string) str_replace('\\n', "\n", $privKeyPem);
    $oracleWallet = defined('GRID402_ORACLE_WALLET') ? GRID402_ORACLE_WALLET : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90';

    $signatureHex = '';
    $pkey = openssl_pkey_get_private($privKeyPem);
    if ($pkey) {
        $sigBinary = '';
        if (openssl_sign($messageToSign, $sigBinary, $pkey, OPENSSL_ALGO_SHA256)) {
            $signatureHex = '0x' . bin2hex($sigBinary);
        }
    }

    $data['_oracle_proof'] = [
        'oracle_address' => strtolower($oracleWallet),
        'timestamp' => $timestamp,
        'data_hash' => $dataHash,
        'signature' => $signatureHex,
        'algorithm' => 'secp256k1-sha256',
        'sandbox' => $isSandbox
    ];
}

/**
 * Valida a prova de oráculo diretamente no PHP
 */
function grid402_verify_oracle_proof(array $data, ?string $pubKeyPem = null): array {
    if (empty($data['_oracle_proof']) || !is_array($data['_oracle_proof'])) {
        return ['valid' => false, 'error' => 'MissingProofBlock'];
    }

    $proof = $data['_oracle_proof'];
    $declaredAddress = strtolower((string) ($proof['oracle_address'] ?? ''));
    $expectedAddress = defined('GRID402_ORACLE_WALLET') ? strtolower((string) GRID402_ORACLE_WALLET) : '';
    if ($expectedAddress !== '' && $declaredAddress !== '' && $declaredAddress !== $expectedAddress) {
        return [
            'valid' => false,
            'error' => 'OracleAddressMismatch',
            'expected_address' => $expectedAddress,
            'received_address' => $declaredAddress
        ];
    }

    $dataHash = grid402_compute_data_hash($data);

    if (($proof['data_hash'] ?? '') !== $dataHash) {
        return [
            'valid' => false,
            'error' => 'DataHashMismatch',
            'expected_hash' => $dataHash,
            'received_hash' => $proof['data_hash'] ?? null
        ];
    }

    $timestamp = (int)($proof['timestamp'] ?? 0);
    $isSandbox = !empty($proof['sandbox']);
    $messageToSign = $dataHash . ':' . $timestamp . ':' . ($isSandbox ? 'sandbox' : 'mainnet');

    $sigHex = (string)($proof['signature'] ?? '');
    if (str_starts_with($sigHex, '0x')) {
        $sigHex = substr($sigHex, 2);
    }
    $sigBinary = hex2bin($sigHex);
    if ($sigBinary === false) {
        return ['valid' => false, 'error' => 'InvalidSignatureHex'];
    }

    $pubKey = $pubKeyPem ?: ((defined('GRID402_ORACLE_PUBLIC_KEY') && GRID402_ORACLE_PUBLIC_KEY !== '') ? GRID402_ORACLE_PUBLIC_KEY : (getenv('GRID402_ORACLE_PUBLIC_KEY') ?: GRID402_DEFAULT_ORACLE_PUBKEY));
    $pubKey = (string) str_replace('\\n', "\n", (string) $pubKey);
    $res = openssl_verify($messageToSign, $sigBinary, $pubKey, OPENSSL_ALGO_SHA256);

    if ($res !== 1) {
        return ['valid' => false, 'error' => 'InvalidSignature'];
    }

    return [
        'valid' => true,
        'oracle_address' => $proof['oracle_address'] ?? null,
        'timestamp' => $timestamp,
        'sandbox' => $isSandbox
    ];
}

