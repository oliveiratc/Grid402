<?php
/**
 * Grid402 - Micro-serviço de Inspeção de Certificados TLS/SSL
 * Validação de vigência, emissor, SANs e dias até expiração na porta 443.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

function grid402_service_ssl(string $host): array {
    $host = strtolower(trim($host));
    $host = preg_replace('/^https?:\/\//i', '', $host);
    $host = explode('/', $host)[0];
    $host = explode(':', $host)[0];

    if ($host === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)) {
        return [
            'success' => false,
            'error' => 'InvalidHost',
            'message' => 'O parâmetro host deve ser um hostname FQDN válido (ex: host=base.org).',
            'code' => 400,
        ];
    }

    $g = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $t0 = microtime(true);
    $client = @stream_socket_client(
        "ssl://{$host}:443",
        $errno,
        $errstr,
        3.0,
        STREAM_CLIENT_CONNECT,
        $g
    );

    $durationMs = round((microtime(true) - $t0) * 1000, 2);

    if (!$client) {
        return [
            'success' => false,
            'error' => 'ConnectionFailed',
            'message' => "Não foi possível estabelecer handshake TLS com {$host}:443 ({$errstr})",
            'code' => 502,
        ];
    }

    $params = stream_context_get_params($client);
    fclose($client);

    $cert = $params['options']['ssl']['peer_certificate'] ?? null;
    if (!$cert) {
        return [
            'success' => false,
            'error' => 'CertificateNotFound',
            'message' => "Nenhum certificado de par retornado por {$host}:443",
            'code' => 502,
        ];
    }

    $certInfo = openssl_x509_parse($cert);
    if (!is_array($certInfo)) {
        return [
            'success' => false,
            'error' => 'ParseError',
            'message' => 'Falha ao analisar a estrutura do certificado X.509',
            'code' => 500,
        ];
    }

    $validFromTs = (int) ($certInfo['validFrom_time_t'] ?? 0);
    $validToTs = (int) ($certInfo['validTo_time_t'] ?? 0);
    $now = time();
    $daysRemaining = (int) floor(($validToTs - $now) / 86400);
    $isExpired = ($now > $validToTs);

    $sanString = (string) ($certInfo['extensions']['subjectAltName'] ?? '');
    $sans = [];
    if ($sanString !== '') {
        $parts = explode(',', $sanString);
        foreach ($parts as $p) {
            $p = trim($p);
            if (str_starts_with($p, 'DNS:')) {
                $sans[] = substr($p, 4);
            }
        }
    }

    return [
        'success' => true,
        'service' => 'ssl',
        'host' => $host,
        'handshake_duration_ms' => $durationMs,
        'certificate' => [
            'common_name' => $certInfo['subject']['CN'] ?? $host,
            'issuer' => $certInfo['issuer']['O'] ?? ($certInfo['issuer']['CN'] ?? 'Unknown'),
            'valid_from' => gmdate('Y-m-d\TH:i:s\Z', $validFromTs),
            'valid_to' => gmdate('Y-m-d\TH:i:s\Z', $validToTs),
            'days_remaining' => max(0, $daysRemaining),
            'is_valid' => (!$isExpired && $now >= $validFromTs),
            'is_expired' => $isExpired,
            'sans' => $sans,
            'serial_number' => $certInfo['serialNumberHex'] ?? ($certInfo['serialNumber'] ?? ''),
        ],
    ];
}

