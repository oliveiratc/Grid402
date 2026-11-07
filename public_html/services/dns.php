<?php
/**
 * Grid402 - Micro-serviço de DNS Lookup
 * Resolução autoritativa de registros de domínio (A, AAAA, MX, TXT, NS, CNAME).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

function grid402_service_dns(string $domain): array {
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^https?:\/\//i', '', $domain);
    $domain = explode('/', $domain)[0];
    $domain = explode(':', $domain)[0];

    // Validação de formato de domínio RFC 1035
    if ($domain === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
        return [
            'success' => false,
            'error' => 'InvalidDomain',
            'message' => 'O parâmetro domain deve ser um nome de domínio válido (ex: domain=github.com).',
            'code' => 400,
        ];
    }

    $types = [
        'A' => DNS_A,
        'AAAA' => DNS_AAAA,
        'MX' => DNS_MX,
        'TXT' => DNS_TXT,
        'NS' => DNS_NS,
        'CNAME' => DNS_CNAME,
    ];

    $records = [];
    $rawRecords = @dns_get_record($domain, DNS_ALL);

    if (is_array($rawRecords)) {
        foreach ($rawRecords as $r) {
            $type = $r['type'] ?? '';
            $entry = ['type' => $type, 'ttl' => $r['ttl'] ?? 300];

            switch ($type) {
                case 'A':
                    $entry['ip'] = $r['ip'] ?? '';
                    break;
                case 'AAAA':
                    $entry['ipv6'] = $r['ipv6'] ?? '';
                    break;
                case 'MX':
                    $entry['target'] = $r['target'] ?? '';
                    $entry['pri'] = $r['pri'] ?? 10;
                    break;
                case 'TXT':
                    $entry['txt'] = $r['txt'] ?? ($r['entries'][0] ?? '');
                    break;
                case 'NS':
                case 'CNAME':
                    $entry['target'] = $r['target'] ?? '';
                    break;
                default:
                    continue 2;
            }

            $records[$type][] = $entry;
        }
    }

    return [
        'success' => true,
        'service' => 'dns',
        'domain' => $domain,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'total_records' => is_array($rawRecords) ? count($rawRecords) : 0,
        'records' => $records,
    ];
}

