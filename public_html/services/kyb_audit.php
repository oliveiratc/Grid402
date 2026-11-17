<?php
/**
 * Grid402 - Micro-serviço Composto de Auditoria KYB & Compliance
 * Consolida CNPJ + Sanções/OFAC + LEI em uma única chamada paga via x402.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/cnpj.php';
require_once __DIR__ . '/ofac.php';
require_once __DIR__ . '/lei.php';

function grid402_service_kyb_audit(string $cnpj = '', string $entityName = '', string $leiCode = '', string $evmAddress = ''): array {
    $cleanCnpj = preg_replace('/\D+/', '', $cnpj);
    $cleanName = trim($entityName);
    $cleanLei = strtoupper(trim($leiCode));
    $cleanAddress = strtolower(trim($evmAddress));

    if ($cleanName !== '' && preg_match('/^0x[a-f0-9]{40}$/i', $cleanName) && $cleanAddress === '') {
        $cleanAddress = strtolower($cleanName);
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
        ? grid402_service_ofac($cleanAddress, $cleanName)
        : null;
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
