<?php
/**
 * Grid402 - Resolução Híbrida de País de Origem Zero-PII
 *
 * Prioridade de resolução:
 * 1. Variáveis/cabeçalhos confiáveis do servidor/CDN (0 ms)
 * 2. Cache em memória + cache persistente indexado por prefixo de rede (/24 IPv4, /48 IPv6)
 * 3. Lookup HTTPS externo opcional (timeout curto), usado apenas em faturamentos reais
 *
 * Nunca armazena IPs completos em disco.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(403);
    exit('Acesso direto proibido.');
}

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/../config.php';

const GRID402_GEOIP_HIT_TTL_SEC = 2592000; // 30 dias
const GRID402_GEOIP_MISS_TTL_SEC = 600;    // 10 minutos
const GRID402_GEOIP_MAX_ENTRIES = 2000;

/**
 * Caminho dinâmico do cache GeoIP Zero-PII (suporta override via env em testes).
 */
function grid402_geoip_cache_path(): string {
    $envPath = function_exists('grid402_env') ? trim((string) grid402_env('GRID402_FILE_GEOIP_CACHE', '')) : '';
    if ($envPath !== '') {
        return $envPath;
    }
    if (defined('GRID402_FILE_GEOIP_CACHE') && (string) GRID402_FILE_GEOIP_CACHE !== '') {
        return (string) GRID402_FILE_GEOIP_CACHE;
    }
    $privDir = defined('GRID402_PRIVATE_DIR') ? GRID402_PRIVATE_DIR : (dirname(__DIR__) . '/private_data');
    return rtrim($privDir, '/\\') . '/geoip_cache.json';
}

/**
 * Limpa o cache GeoIP em memória do processo atual (útil em testes).
 */
function grid402_geoip_reset_memory_cache(): void {
    $GLOBALS['grid402_geoip_mem_cache'] = [];
}

/**
 * Normaliza código ISO 3166-1 alpha-2 rejeitando sentinelas e valores inválidos.
 */
function grid402_normalize_country_code(?string $code): ?string {
    if ($code === null) {
        return null;
    }
    $clean = strtoupper(trim($code));
    if (preg_match('/^[A-Z]{2}$/', $clean) !== 1) {
        return null;
    }
    $sentinels = ['XX', 'T1', 'A1', 'A2', 'O1', 'ZZ', 'GL'];
    if (in_array($clean, $sentinels, true)) {
        return null;
    }
    return $clean;
}

/**
 * Codifica um codepoint Unicode em UTF-8 sem depender de mbstring.
 */
function grid402_geoip_utf8_chr(int $cp): string {
    if ($cp < 0x80) {
        return chr($cp);
    }
    if ($cp < 0x800) {
        return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
    }
    if ($cp < 0x10000) {
        return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
    if ($cp <= 0x10FFFF) {
        return chr(0xF0 | ($cp >> 18))
            . chr(0x80 | (($cp >> 12) & 0x3F))
            . chr(0x80 | (($cp >> 6) & 0x3F))
            . chr(0x80 | ($cp & 0x3F));
    }
    return '';
}

/**
 * Converte código ISO-2 na bandeira Unicode correspondente ou globo caso não identificado.
 */
function grid402_country_flag(?string $code): string {
    $cc = grid402_normalize_country_code($code);
    if ($cc === null) {
        return '🌐';
    }
    $first = 0x1F1E6 + (ord($cc[0]) - 65);
    $second = 0x1F1E6 + (ord($cc[1]) - 65);
    return grid402_geoip_utf8_chr($first) . grid402_geoip_utf8_chr($second);
}

/**
 * Retorna o nome do país em português do Brasil a partir do código ISO-2.
 */
function grid402_country_name_pt(?string $code): string {
    $cc = grid402_normalize_country_code($code);
    if ($cc === null) {
        return 'Não identificado';
    }

    static $map = [
        'AE' => 'Emirados Árabes',
        'AF' => 'Afeganistão',
        'AO' => 'Angola',
        'AR' => 'Argentina',
        'AT' => 'Áustria',
        'AU' => 'Austrália',
        'BD' => 'Bangladesh',
        'BE' => 'Bélgica',
        'BG' => 'Bulgária',
        'BO' => 'Bolívia',
        'BR' => 'Brasil',
        'CA' => 'Canadá',
        'CH' => 'Suíça',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colômbia',
        'CR' => 'Costa Rica',
        'CY' => 'Chipre',
        'CZ' => 'República Tcheca',
        'DE' => 'Alemanha',
        'DK' => 'Dinamarca',
        'DO' => 'República Dominicana',
        'EC' => 'Equador',
        'EE' => 'Estônia',
        'EG' => 'Egito',
        'ES' => 'Espanha',
        'FI' => 'Finlândia',
        'FR' => 'França',
        'GB' => 'Reino Unido',
        'GR' => 'Grécia',
        'GT' => 'Guatemala',
        'HK' => 'Hong Kong',
        'HN' => 'Honduras',
        'HR' => 'Croácia',
        'HU' => 'Hungria',
        'ID' => 'Indonésia',
        'IE' => 'Irlanda',
        'IL' => 'Israel',
        'IN' => 'Índia',
        'IS' => 'Islândia',
        'IT' => 'Itália',
        'JP' => 'Japão',
        'KE' => 'Quênia',
        'KR' => 'Coreia do Sul',
        'KZ' => 'Cazaquistão',
        'LT' => 'Lituânia',
        'LU' => 'Luxemburgo',
        'LV' => 'Letônia',
        'MA' => 'Marrocos',
        'MT' => 'Malta',
        'MX' => 'México',
        'MY' => 'Malásia',
        'MZ' => 'Moçambique',
        'NG' => 'Nigéria',
        'NI' => 'Nicarágua',
        'NL' => 'Países Baixos',
        'NO' => 'Noruega',
        'NZ' => 'Nova Zelândia',
        'PA' => 'Panamá',
        'PE' => 'Peru',
        'PH' => 'Filipinas',
        'PK' => 'Paquistão',
        'PL' => 'Polônia',
        'PR' => 'Porto Rico',
        'PT' => 'Portugal',
        'PY' => 'Paraguai',
        'RO' => 'Romênia',
        'RS' => 'Sérvia',
        'RU' => 'Rússia',
        'SA' => 'Arábia Saudita',
        'SE' => 'Suécia',
        'SG' => 'Singapura',
        'SI' => 'Eslovênia',
        'SK' => 'Eslováquia',
        'SV' => 'El Salvador',
        'TH' => 'Tailândia',
        'TR' => 'Turquia',
        'TW' => 'Taiwan',
        'UA' => 'Ucrânia',
        'US' => 'Estados Unidos',
        'UY' => 'Uruguai',
        'VE' => 'Venezuela',
        'VN' => 'Vietnã',
        'ZA' => 'África do Sul',
    ];

    if (isset($map[$cc])) {
        return $map[$cc];
    }

    if (class_exists('Locale')) {
        $display = @Locale::getDisplayRegion('-' . $cc, 'pt_BR');
        if (is_string($display) && $display !== '' && strtoupper($display) !== $cc) {
            return $display;
        }
    }

    return $cc;
}

/**
 * Formata representação estruturada do país para UI, CSV e notificações.
 */
function grid402_format_country(?string $code): array {
    $cc = grid402_normalize_country_code($code);
    $flag = grid402_country_flag($cc);
    $name = grid402_country_name_pt($cc);
    return [
        'code' => $cc ?? '',
        'name' => $name,
        'flag' => $flag,
        'label' => $cc !== null ? "{$flag} {$name} ({$cc})" : '🌐 Não identificado',
        'short' => $cc !== null ? "{$flag} {$cc}" : '🌐 —',
    ];
}

/**
 * Converte IP público em prefixo anônimo Zero-PII (/24 IPv4 ou /48 IPv6).
 * Retorna null para IPs inválidos, privados, loopback ou reservados.
 */
function grid402_geoip_ip_prefix(string $ip): ?string {
    $clean = trim($ip);
    if ($clean === '' || filter_var($clean, FILTER_VALIDATE_IP) === false) {
        return null;
    }
    $isPrivateOrRes = function_exists('grid402_is_private_or_reserved_ip')
        ? grid402_is_private_or_reserved_ip($clean)
        : (filter_var($clean, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
    if ($isPrivateOrRes) {
        return null;
    }

    if (filter_var($clean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $parts = explode('.', $clean);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }
        return null;
    }

    if (filter_var($clean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $packed = @inet_pton($clean);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }
        $hextets = @unpack('n3', substr($packed, 0, 6));
        if (!is_array($hextets) || count($hextets) < 3) {
            return null;
        }
        return sprintf('%04x:%04x:%04x::/48', (int) $hextets[1], (int) $hextets[2], (int) $hextets[3]);
    }

    return null;
}

/**
 * Extrai país de origem a partir de variáveis de servidor ou cabeçalhos confiáveis de CDN/proxy.
 */
function grid402_detect_country_from_headers(?array $server = null): ?string {
    $srv = $server ?? $_SERVER;

    // Variáveis nativas de servidor web / módulo GeoIP (não controladas por cabeçalho HTTP do cliente)
    foreach (['GEOIP_COUNTRY_CODE', 'LITESPEED_GEOIP_COUNTRY_CODE', 'MM_COUNTRY_CODE'] as $srvKey) {
        if (!empty($srv[$srvKey])) {
            $cc = grid402_normalize_country_code((string) $srv[$srvKey]);
            if ($cc !== null) {
                return $cc;
            }
        }
    }

    // Cabeçalhos HTTP de proxy/CDN: só confia quando proxy confiável ou opt-in explícito
    $trustCfRaw = strtolower(trim((string) (function_exists('grid402_env') ? grid402_env('GRID402_TRUST_CF_HEADER', 'false') : 'false')));
    $trustGeoRaw = strtolower(trim((string) (function_exists('grid402_env') ? grid402_env('GRID402_TRUST_GEO_HEADERS', 'false') : 'false')));
    $explicitTrust = in_array($trustCfRaw, ['1', 'true', 'yes', 'on'], true)
        || in_array($trustGeoRaw, ['1', 'true', 'yes', 'on'], true);

    $remote = trim((string) ($srv['REMOTE_ADDR'] ?? ''));
    $isTrustedProxy = false;
    if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false) {
        $isPrivateRemote = function_exists('grid402_is_private_or_reserved_ip')
            ? grid402_is_private_or_reserved_ip($remote)
            : (filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);

        $trustedProxiesRaw = trim((string) (function_exists('grid402_env') ? grid402_env('GRID402_TRUSTED_PROXIES', '') : ''));
        $trustedProxies = [];
        if ($trustedProxiesRaw !== '') {
            $parsed = preg_split('/[\s,;]+/', $trustedProxiesRaw, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($parsed)) {
                foreach ($parsed as $p) {
                    $pClean = trim($p);
                    if (filter_var($pClean, FILTER_VALIDATE_IP) !== false) {
                        $trustedProxies[] = $pClean;
                    }
                }
            }
        }
        $isTrustedProxy = $isPrivateRemote || in_array($remote, $trustedProxies, true);
    }

    if (!$explicitTrust && !$isTrustedProxy) {
        return null;
    }

    foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_COUNTRY_CODE'] as $hdrKey) {
        if (!empty($srv[$hdrKey])) {
            $cc = grid402_normalize_country_code((string) $srv[$hdrKey]);
            if ($cc !== null) {
                return $cc;
            }
        }
    }

    return null;
}

/**
 * Lê entrada do cache persistente GeoIP por prefixo (/24 ou /48).
 * Retorna ['hit' => bool, 'cc' => ?string].
 */
function grid402_geoip_cache_get(string $prefix): array {
    $file = grid402_geoip_cache_path();
    if (!is_file($file) || !is_readable($file)) {
        return ['hit' => false, 'cc' => null];
    }

    $fp = @fopen($file, 'rb');
    if (!$fp) {
        return ['hit' => false, 'cc' => null];
    }

    $raw = '';
    if (flock($fp, LOCK_SH)) {
        $raw = (string) stream_get_contents($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    $decoded = $raw !== '' ? @json_decode($raw, true) : null;
    if (!is_array($decoded) || !isset($decoded['entries']) || !is_array($decoded['entries'])) {
        return ['hit' => false, 'cc' => null];
    }

    $entry = $decoded['entries'][$prefix] ?? null;
    if (!is_array($entry)) {
        return ['hit' => false, 'cc' => null];
    }

    $ts = (int) ($entry['ts'] ?? 0);
    $ccRaw = (string) ($entry['cc'] ?? '');
    $cc = $ccRaw !== '' ? grid402_normalize_country_code($ccRaw) : null;
    $ttl = ($cc !== null) ? GRID402_GEOIP_HIT_TTL_SEC : GRID402_GEOIP_MISS_TTL_SEC;

    if ($ts <= 0 || (time() - $ts) > $ttl) {
        return ['hit' => false, 'cc' => null];
    }

    return ['hit' => true, 'cc' => $cc];
}

/**
 * Grava entrada no cache persistente GeoIP sob flock(LOCK_EX), apenas por prefixo (/24 ou /48).
 */
function grid402_geoip_cache_put(string $prefix, ?string $countryCode): void {
    $file = grid402_geoip_cache_path();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }

    $ccNorm = grid402_normalize_country_code($countryCode) ?? '';

    if (flock($fp, LOCK_EX)) {
        $store = function_exists('grid402_read_locked_json')
            ? grid402_read_locked_json($fp, ['entries' => []])
            : ['entries' => []];
        if (!is_array($store) || !isset($store['entries']) || !is_array($store['entries'])) {
            $store = ['entries' => []];
        }

        $now = time();
        $store['entries'][$prefix] = [
            'cc' => $ccNorm,
            'ts' => $now,
        ];

        // Evita crescimento ilimitado preservando as entradas mais recentes
        if (count($store['entries']) > GRID402_GEOIP_MAX_ENTRIES) {
            uasort($store['entries'], static function ($a, $b): int {
                $ta = is_array($a) ? (int) ($a['ts'] ?? 0) : 0;
                $tb = is_array($b) ? (int) ($b['ts'] ?? 0) : 0;
                return $tb <=> $ta;
            });
            $store['entries'] = array_slice($store['entries'], 0, GRID402_GEOIP_MAX_ENTRIES, true);
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($store, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

/**
 * Executa consulta HTTPS rápida de GeoIP (somente chamada para IPs públicos e fora de flock).
 */
function grid402_geoip_external_lookup(string $ip): ?string {
    $cleanIp = trim($ip);
    if (grid402_geoip_ip_prefix($cleanIp) === null) {
        return null;
    }

    $transport = $GLOBALS['grid402_geoip_http_transport'] ?? null;
    if (is_callable($transport)) {
        try {
            $rawCc = $transport($cleanIp);
            return grid402_normalize_country_code(is_string($rawCc) ? $rawCc : null);
        } catch (Throwable $e) {
            return null;
        }
    }

    $disabled = function_exists('grid402_env') ? trim((string) grid402_env('GRID402_DISABLE_GEOIP_LOOKUP', '')) : '';
    if ($disabled === '1' || !function_exists('curl_init')) {
        return null;
    }

    // Provedor primário: ipwho.is
    $ch = @curl_init('https://ipwho.is/' . rawurlencode($cleanIp) . '?fields=success,country_code');
    if ($ch) {
        @curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Grid402-GeoIP/1.0',
        ]);
        $raw = @curl_exec($ch);
        $code = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        if ($code === 200 && is_string($raw) && $raw !== '') {
            $json = @json_decode($raw, true);
            if (is_array($json) && !empty($json['success']) && !empty($json['country_code'])) {
                $cc = grid402_normalize_country_code((string) $json['country_code']);
                if ($cc !== null) {
                    return $cc;
                }
            }
        }
    }

    // Fallback: ipapi.co/<ip>/country/
    $chFb = @curl_init('https://ipapi.co/' . rawurlencode($cleanIp) . '/country/');
    if ($chFb) {
        @curl_setopt_array($chFb, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Grid402-GeoIP/1.0',
        ]);
        $rawFb = @curl_exec($chFb);
        $codeFb = (int) @curl_getinfo($chFb, CURLINFO_HTTP_CODE);
        @curl_close($chFb);
        if ($codeFb === 200 && is_string($rawFb)) {
            $ccFb = grid402_normalize_country_code($rawFb);
            if ($ccFb !== null) {
                return $ccFb;
            }
        }
    }

    return null;
}

/**
 * Resolve o país ISO-2 do cliente de forma híbrida (headers -> cache Zero-PII -> lookup opcional -> fallback).
 */
function grid402_resolve_client_country(?string $ip = null, bool $allowExternalLookup = false, ?string $fallbackCode = null, ?array $server = null): ?string {
    $reqTrustedIp = function_exists('grid402_get_trusted_client_ip')
        ? grid402_get_trusted_client_ip()
        : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    $explicitIpProvided = ($ip !== null && trim($ip) !== '');
    $targetIp = $explicitIpProvided ? trim((string) $ip) : $reqTrustedIp;

    // Só lê headers globais se não houver um IP sintético distinto do IP da requisição atual
    $canReadHeaders = ($server !== null) || !$explicitIpProvided || ($targetIp === $reqTrustedIp);
    if ($canReadHeaders) {
        $hdrCc = grid402_detect_country_from_headers($server);
        if ($hdrCc !== null) {
            $prefixHdr = grid402_geoip_ip_prefix($targetIp);
            if ($prefixHdr !== null) {
                $cacheFile = grid402_geoip_cache_path();
                $GLOBALS['grid402_geoip_mem_cache'][$cacheFile . '|' . $prefixHdr] = ['hit' => true, 'cc' => $hdrCc];
            }
            return $hdrCc;
        }
    }

    $prefix = grid402_geoip_ip_prefix($targetIp);
    if ($prefix === null) {
        return grid402_normalize_country_code($fallbackCode);
    }

    $cacheFile = grid402_geoip_cache_path();
    $memKey = $cacheFile . '|' . $prefix;
    if (isset($GLOBALS['grid402_geoip_mem_cache'][$memKey]) && is_array($GLOBALS['grid402_geoip_mem_cache'][$memKey])) {
        $memEntry = $GLOBALS['grid402_geoip_mem_cache'][$memKey];
        if (!empty($memEntry['hit'])) {
            return $memEntry['cc'] ?? grid402_normalize_country_code($fallbackCode);
        }
    }

    $diskEntry = grid402_geoip_cache_get($prefix);
    if (!empty($diskEntry['hit'])) {
        $GLOBALS['grid402_geoip_mem_cache'][$memKey] = $diskEntry;
        return $diskEntry['cc'] ?? grid402_normalize_country_code($fallbackCode);
    }

    if ($allowExternalLookup) {
        $resolvedCc = grid402_geoip_external_lookup($targetIp);
        grid402_geoip_cache_put($prefix, $resolvedCc);
        $GLOBALS['grid402_geoip_mem_cache'][$memKey] = ['hit' => true, 'cc' => $resolvedCc];
        if ($resolvedCc !== null) {
            return $resolvedCc;
        }
    }

    return grid402_normalize_country_code($fallbackCode);
}
