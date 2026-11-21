<?php
/**
 * Grid402 - Micro-serviço Composto de Auditoria de Domínio (DNS + SSL + ASN)
 * Ideal para agentes autônomos x402 realizarem verificação completa em 1 única chamada.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/dns.php';
require_once __DIR__ . '/ssl.php';
require_once __DIR__ . '/asn.php';

function grid402_service_domain_audit(string $domain): array {
    $cleanDomain = strtolower(trim($domain));
    $cleanDomain = preg_replace('/^https?:\/\//i', '', $cleanDomain);
    $cleanDomain = explode('/', $cleanDomain)[0];
    $cleanDomain = explode(':', $cleanDomain)[0];

    if ($cleanDomain === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $cleanDomain)) {
        return [
            'success' => false,
            'error' => 'InvalidDomain',
            'message' => 'Informe um domínio FQDN válido (ex: domain=base.org).',
            'code' => 400,
        ];
    }

    $dns = grid402_service_dns($cleanDomain);
    $ssl = grid402_service_ssl($cleanDomain);

    $primaryIp = null;
    if (isset($dns['records']['A'][0]['ip']) && is_string($dns['records']['A'][0]['ip'])) {
        $primaryIp = $dns['records']['A'][0]['ip'];
    } elseif (isset($dns['data']['A'][0]['ip']) && is_string($dns['data']['A'][0]['ip'])) {
        $primaryIp = $dns['data']['A'][0]['ip'];
    } else {
        $resolved = gethostbyname($cleanDomain);
        if ($resolved !== $cleanDomain && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $primaryIp = $resolved;
        }
    }

    $asn = $primaryIp ? grid402_service_asn($primaryIp) : [
        'success' => false,
        'error' => 'IpNotResolved',
        'message' => 'Nenhum IPv4 (registro A) encontrado para consulta ASN.',
    ];

    return [
        'success' => true,
        'domain' => $cleanDomain,
        'primary_ip' => $primaryIp,
        'audit' => [
            'dns' => $dns,
            'ssl' => $ssl,
            'asn' => $asn,
        ],
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}
