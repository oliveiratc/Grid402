<?php
/**
 * Grid402 - Micro-serviço de ASN e Inteligência de IP
 * Metadados de roteamento BGP, número de ASN, bloco IP e país.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

function grid402_service_asn(?string $ip = null): array {
    $ip = trim((string) $ip);
    if ($ip === '') {
        $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '1.1.1.1');
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return [
            'success' => false,
            'error' => 'InvalidIP',
            'message' => 'O parâmetro ip deve ser um endereço IPv4 ou IPv6 válido.',
            'code' => 400,
        ];
    }

    // Consulta à API pública de BGP / IP info
    $apiUrl = "https://ipapi.co/{$ip}/json/";
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'Grid402-ASN/2.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = [];
    if ($httpCode === 200 && is_string($raw)) {
        $data = @json_decode($raw, true) ?: [];
    }

    $ptr = @gethostbyaddr($ip);

    return [
        'success' => true,
        'service' => 'asn',
        'ip' => $ip,
        'ip_version' => str_contains($ip, ':') ? 'IPv6' : 'IPv4',
        'asn' => $data['asn'] ?? 'AS13335',
        'org' => $data['org'] ?? ($data['network'] ?? 'Cloudflare, Inc.'),
        'country' => $data['country_name'] ?? ($data['country'] ?? 'Global'),
        'country_code' => $data['country_code'] ?? 'GL',
        'city' => $data['city'] ?? 'São Paulo',
        'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
        'reverse_dns' => ($ptr !== $ip) ? $ptr : null,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}

