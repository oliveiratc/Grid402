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

function grid402_service_kyb_audit(string $cnpj = '', string $entityName = '', string $leiCode = ''): array {
    $cleanCnpj = preg_replace('/\D+/', '', $cnpj);
    $cleanName = trim($entityName);
    $cleanLei = strtoupper(trim($leiCode));

    if ($cleanCnpj === '' && $cleanName === '' && $cleanLei === '') {
        return [
            'success' => false,
            'error' => 'MissingParameters',
            'message' => 'Provide at least one identifier: cnpj, name, or lei.',
            'code' => 400,
        ];
    }

    $cnpjData = $cleanCnpj !== '' ? grid402_service_cnpj($cleanCnpj) : null;
    if ($cleanName === '' && is_array($cnpjData)) {
        $cleanName = (string) ($cnpjData['razao_social'] ?? $cnpjData['data']['razao_social'] ?? '');
    }

    $ofacData = $cleanName !== '' ? grid402_service_ofac($cleanName) : null;
    $leiData = $cleanLei !== '' ? grid402_service_lei($cleanLei) : null;

    return [
        'success' => true,
        'query' => [
            'cnpj' => $cleanCnpj !== '' ? $cleanCnpj : null,
            'name' => $cleanName !== '' ? $cleanName : null,
            'lei' => $cleanLei !== '' ? $cleanLei : null,
        ],
        'audit' => [
            'cnpj' => $cnpjData,
            'ofac_sanctions' => $ofacData,
            'lei' => $leiData,
        ],
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}
