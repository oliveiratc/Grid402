<?php
/**
 * Grid402 - Micro-serviço Composto de Auditoria KYB & Compliance
 * Consolida CNPJ + Sanções Globais (OFAC/ONU/UE/UK) + LEI em uma única chamada paga via x402.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/cnpj.php';
require_once __DIR__ . '/ofac.php';
require_once __DIR__ . '/lei.php';

function grid402_service_kyb_audit(string $cnpj = '', string $entityName = '', string $leiCode = '', string $cryptoAddress = '', bool $isSandbox = false): array {
    $cleanCnpj = preg_replace('/\D+/', '', $cnpj);
    $cleanName = trim($entityName);
    $cleanLei = strtoupper(trim($leiCode));
    $cleanAddress = trim($cryptoAddress);

    if ($cleanName !== '' && $cleanAddress === '' && grid402_ofac_detect_address_type($cleanName) !== null) {
        $cleanAddress = $cleanName;
        $cleanName = '';
    }

    if ($cleanCnpj === '' && $cleanName === '' && $cleanLei === '' && $cleanAddress === '') {
        $cleanCnpj = '00000000000191';
    }

    $cnpjData = $cleanCnpj !== '' ? grid402_service_cnpj($cleanCnpj) : null;
    if ($cleanName === '' && is_array($cnpjData)) {
        $cleanName = (string) ($cnpjData['razao_social'] ?? $cnpjData['data']['razao_social'] ?? '');
    }

    $ofacData = ($cleanAddress !== '' || $cleanName !== '')
        ? grid402_service_ofac($cleanAddress, $cleanName, $isSandbox)
        : null;

    // Propagacao fail-closed obrigatoria: nunca mascarar indisponibilidade do indice oficial como CLEAR
    if (is_array($ofacData) && !empty($ofacData['error']) && ((int) ($ofacData['code'] ?? 0) === 503)) {
        return [
            'error' => true,
            'code' => 503,
            'error_code' => (string) ($ofacData['error_code'] ?? 'SanctionsIndexUnavailable'),
            'service' => 'kyb-audit',
            'message' => (string) ($ofacData['message'] ?? 'Official sanctions index unavailable for KYB audit.'),
        ];
    }

    $leiData = $cleanLei !== '' ? grid402_service_lei($cleanLei) : null;

    return [
        'success' => true,
        'status' => 'success',
        'service' => 'kyb-audit',
        'query' => [
            'cnpj' => $cleanCnpj !== '' ? $cleanCnpj : null,
            'name' => $cleanName !== '' ? $cleanName : null,
            'lei' => $cleanLei !== '' ? $cleanLei : null,
            'address' => $cleanAddress !== '' ? $cleanAddress : null,
        ],
        'audit' => [
            'cnpj' => $cnpjData,
            'ofac_sanctions' => $ofacData,
            'lei' => $leiData,
        ],
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}
