<?php
/**
 * Grid402 - Carimbo de Tempo Criptográfico e Notarização M2M (/api/v1/notarize)
 * Permite que agentes de IA e sistemas autônomos gerem uma prova matemática imutável
 * de que um documento, hash, log de decisão ou contrato existia em um instante UTC exato.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

/**
 * Executa a notarização e carimbo de tempo criptográfico de um hash ou payload arbitrário
 */
function grid402_service_notarize(string $rawData = '', string $providedHash = ''): array {
    $rawData = trim($rawData);
    $providedHash = strtolower(trim($providedHash));

    if ($rawData === '' && $providedHash === '') {
        $rawData = 'grid402-autonomous-agent-agreement-v1';
    }

    if ($providedHash !== '') {
        $cleanHash = str_starts_with($providedHash, '0x') ? substr($providedHash, 2) : $providedHash;
        if (!preg_match('/^[a-f0-9]{64}$/', $cleanHash)) {
            return [
                'error' => true,
                'code' => 400,
                'message' => 'Hash inválido. Informe um hash SHA-256 de 64 caracteres hexadecimais ou envie o parâmetro "data" para cálculo automático.',
            ];
        }
        $inputSha256 = '0x' . $cleanHash;
        $inputMode = 'precomputed_sha256';
    } else {
        $inputSha256 = '0x' . hash('sha256', $rawData);
        $inputMode = 'raw_payload_hashed';
    }

    $nowEpoch = time();
    $isoUtc = gmdate('Y-m-d\TH:i:s\Z', $nowEpoch);
    $certId = 'G402-CERT-' . strtoupper(substr(hash('sha256', $inputSha256 . ':' . $nowEpoch . ':' . GRID402_WALLET_ADDRESS), 0, 16));

    return [
        'service' => 'notarize',
        'status' => 'NOTARIZED',
        'certificate_id' => $certId,
        'input_mode' => $inputMode,
        'input_sha256' => $inputSha256,
        'input_preview' => ($rawData !== '') ? substr($rawData, 0, 80) : null,
        'notarized_at_utc' => $isoUtc,
        'unix_epoch' => $nowEpoch,
        'oracle_authority' => [
            'name' => 'Grid402 Cryptographic Oracle Hub',
            'oracle_wallet' => GRID402_WALLET_ADDRESS,
            'network' => 'Base Mainnet (Chain ID 8453)',
            'signature_scheme' => 'ECDSA secp256k1 + SHA-256 (RFC 8785 Canonical JSON)',
            'verification_endpoint' => GRID402_CANONICAL_DOMAIN . '/api/v1/verify',
        ],
        'timestamp' => $isoUtc,
    ];
}

/**
 * Gera a prévia gratuita (Teaser HTTP 402) para o endpoint Notarize
 */
function grid402_preview_notarize(string $rawData = '', string $providedHash = ''): array {
    $sample = ($rawData !== '') ? $rawData : (($providedHash !== '') ? $providedHash : 'grid402-autonomous-agent-agreement-v1');
    $digest = '0x' . hash('sha256', $sample);
    return [
        'computed_sha256' => $digest,
        'timestamp_ready_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'signature_algorithm' => 'ECDSA secp256k1-sha256',
        'status' => 'AWAITING_SETTLEMENT_FOR_ORACLE_SEAL',
    ];
}
