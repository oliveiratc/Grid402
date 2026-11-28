<?php
/**
 * Grid402 - Micro-Servico de Auditoria de Vulnerabilidades (/api/v1/vuln-audit)
 *
 * Fontes 100% publicas sem API key paga:
 * - OSV.dev API v1 (api.osv.dev)
 * - CISA Known Exploited Vulnerabilities (KEV) Catalog (cisa.gov, cache 6h com fallback)
 *
 * Veredito deterministico: CLEAN, LOW, MODERATE, HIGH, CRITICAL, CRITICAL_KEV.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Mapa de ecossistemas suportados para o formato canonico do OSV.dev.
 *
 * @return array<string, string>
 */
function grid402_vuln_audit_ecosystem_map(): array {
    return [
        'npm' => 'npm',
        'pypi' => 'PyPI',
        'pip' => 'PyPI',
        'python' => 'PyPI',
        'go' => 'Go',
        'golang' => 'Go',
        'maven' => 'Maven',
        'packagist' => 'Packagist',
        'composer' => 'Packagist',
        'php' => 'Packagist',
        'cargo' => 'crates.io',
        'crates.io' => 'crates.io',
        'rust' => 'crates.io',
        'nuget' => 'NuGet',
        'rubygems' => 'RubyGems',
        'ruby' => 'RubyGems',
    ];
}

/**
 * Valida e normaliza parametros de consulta de /api/v1/vuln-audit.
 * Aceita modo pacote (ecosystem + package + version opcional) OU modo ID (id=CVE-.../GHSA-...).
 *
 * @return array<string, mixed>
 */
function grid402_vuln_audit_validate_input(
    string $ecosystem = '',
    string $package = '',
    string $version = '',
    string $id = ''
): array {
    $cleanId = strtoupper(trim($id));
    $cleanEco = strtolower(trim($ecosystem));
    $cleanPkg = trim($package);
    $cleanVer = trim($version);

    if ($cleanId !== '') {
        if (!preg_match('/^[A-Z0-9\-_.:]{4,64}$/', $cleanId)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'InvalidVulnId',
                'message' => 'Parametro "id" invalido. Use identificadores como CVE-2021-44228 ou GHSA-xxxx-xxxx-xxxx.',
            ];
        }
        return [
            'ok' => true,
            'mode' => 'id',
            'id' => $cleanId,
            'ecosystem' => '',
            'package' => '',
            'version' => '',
        ];
    }

    if ($cleanEco === '' && $cleanPkg === '') {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'MissingVulnAuditQuery',
            'message' => 'Informe "id" (ex.: CVE-2021-44228) OU "ecosystem" + "package" (ex.: ecosystem=npm&package=lodash).',
        ];
    }

    if ($cleanEco === '' || $cleanPkg === '') {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'IncompletePackageQuery',
            'message' => 'No modo pacote, ambos "ecosystem" e "package" sao obrigatorios.',
        ];
    }

    $ecoMap = grid402_vuln_audit_ecosystem_map();
    if (!isset($ecoMap[$cleanEco])) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'UnsupportedEcosystem',
            'message' => 'Ecossistema nao suportado. Aceitos: npm, PyPI, Go, Maven, Packagist, crates.io, NuGet, RubyGems.',
        ];
    }

    if (strlen($cleanPkg) > 160 || preg_match('/[\x00-\x1F\x7F\s]/', $cleanPkg)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidPackageName',
            'message' => 'Nome de pacote invalido para auditoria.',
        ];
    }

    if ($cleanVer !== '' && (strlen($cleanVer) > 80 || preg_match('/[\x00-\x1F\x7F\s]/', $cleanVer))) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidPackageVersion',
            'message' => 'Versao de pacote invalida.',
        ];
    }

    return [
        'ok' => true,
        'mode' => 'package',
        'id' => '',
        'ecosystem' => $ecoMap[$cleanEco],
        'package' => $cleanPkg,
        'version' => $cleanVer,
    ];
}

/**
 * Executa HTTP GET ou POST JSON com suporte a override em $GLOBALS['grid402_vuln_audit_http_override'].
 *
 * @return array{ok: bool, status: int, data: mixed, error: ?string}
 */
function grid402_vuln_audit_http_request(string $method, string $url, ?array $jsonBody = null, int $timeoutSec = 5): array {
    $override = $GLOBALS['grid402_vuln_audit_http_override'] ?? null;
    if (is_callable($override)) {
        return $override($method, $url, $jsonBody, $timeoutSec);
    }

    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSec),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Grid402-VulnAudit/1.0 (+https://grid402.online)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if (strtoupper($method) === 'POST') {
        $payload = (string) json_encode($jsonBody ?? [], JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $payload;
    }

    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($status !== 200 || !is_string($raw) || $raw === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $err !== '' ? $err : ('HTTP ' . $status),
        ];
    }

    $decoded = @json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => 'InvalidJSON',
        ];
    }

    return ['ok' => true, 'status' => 200, 'data' => $decoded, 'error' => null];
}

/**
 * Carrega mapa indexado do catalogo oficial CISA KEV com cache 6h e fallback para copia em disco.
 *
 * @return array{status: string, map: array<string, array<string, mixed>>}
 */
function grid402_vuln_audit_load_kev_map(string $privateDir): array {
    $kevCacheFile = $privateDir . '/cisa_kev_cache.json';
    $kevTtl = 21600;
    $existingMap = null;

    if (file_exists($kevCacheFile)) {
        $raw = @file_get_contents($kevCacheFile);
        $decoded = $raw ? @json_decode($raw, true) : null;
        if (is_array($decoded) && is_array($decoded['map'] ?? null)) {
            $existingMap = $decoded['map'];
            if (isset($decoded['cached_at']) && (time() - (int) $decoded['cached_at']) < $kevTtl) {
                return ['status' => 'ok', 'map' => $existingMap];
            }
        }
    }

    $kevUrl = 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json';
    $resp = grid402_vuln_audit_http_request('GET', $kevUrl, null, 6);
    if (!empty($resp['ok']) && is_array($resp['data']) && is_array($resp['data']['vulnerabilities'] ?? null)) {
        $map = [];
        foreach ($resp['data']['vulnerabilities'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cve = strtoupper(trim((string) ($item['cveID'] ?? '')));
            if ($cve === '') {
                continue;
            }
            $map[$cve] = [
                'date_added' => $item['dateAdded'] ?? null,
                'due_date' => $item['dueDate'] ?? null,
                'known_ransomware' => $item['knownRansomwareCampaignUse'] ?? null,
                'vendor_project' => $item['vendorProject'] ?? null,
                'product' => $item['product'] ?? null,
            ];
        }

        if (!empty($map) && function_exists('grid402_atomic_write')) {
            grid402_atomic_write($kevCacheFile, [
                'cached_at' => time(),
                'count' => count($map),
                'map' => $map,
            ]);
        }

        return ['status' => 'ok', 'map' => $map];
    }

    if (is_array($existingMap)) {
        return ['status' => 'stale_cache', 'map' => $existingMap];
    }

    return ['status' => 'unavailable', 'map' => []];
}

/**
 * Classifica severidade de uma entrada OSV.
 *
 * @param array<string, mixed> $vuln
 * @return array{severity: string, cvss_score: ?float}
 */
function grid402_vuln_audit_extract_severity(array $vuln): array {
    $cvssScore = null;
    $label = null;

    if (isset($vuln['database_specific']['severity']) && is_string($vuln['database_specific']['severity'])) {
        $rawLbl = strtoupper(trim($vuln['database_specific']['severity']));
        if (in_array($rawLbl, ['CRITICAL', 'HIGH', 'MEDIUM', 'MODERATE', 'LOW'], true)) {
            $label = $rawLbl === 'MEDIUM' ? 'MODERATE' : $rawLbl;
        }
    }

    if (isset($vuln['severity']) && is_array($vuln['severity'])) {
        foreach ($vuln['severity'] as $sevItem) {
            if (!is_array($sevItem)) {
                continue;
            }
            $scoreVal = $sevItem['score'] ?? null;
            if (is_numeric($scoreVal)) {
                $cvssScore = round((float) $scoreVal, 1);
                break;
            }
            if (is_string($scoreVal)) {
                if (preg_match('/^[0-9]+(\.[0-9]+)?$/', trim($scoreVal))) {
                    $cvssScore = round((float) trim($scoreVal), 1);
                    break;
                }
            }
        }
    }

    if ($cvssScore === null && isset($vuln['database_specific']['cvss_score']) && is_numeric($vuln['database_specific']['cvss_score'])) {
        $cvssScore = round((float) $vuln['database_specific']['cvss_score'], 1);
    }

    if ($label === null && $cvssScore !== null) {
        if ($cvssScore >= 9.0) {
            $label = 'CRITICAL';
        } elseif ($cvssScore >= 7.0) {
            $label = 'HIGH';
        } elseif ($cvssScore >= 4.0) {
            $label = 'MODERATE';
        } else {
            $label = 'LOW';
        }
    }

    return [
        'severity' => $label ?? 'UNKNOWN',
        'cvss_score' => $cvssScore,
    ];
}

/**
 * Normaliza uma vulnerabilidade OSV cruzando IDs e aliases com CISA KEV.
 *
 * @param array<string, mixed> $vuln
 * @param array<string, array<string, mixed>> $kevMap
 * @return array<string, mixed>
 */
function grid402_vuln_audit_normalize_vuln(array $vuln, array $kevMap): array {
    $id = strtoupper(trim((string) ($vuln['id'] ?? '')));
    $aliases = [];
    if (isset($vuln['aliases']) && is_array($vuln['aliases'])) {
        foreach ($vuln['aliases'] as $al) {
            $alClean = strtoupper(trim((string) $al));
            if ($alClean !== '') {
                $aliases[] = $alClean;
            }
        }
    }

    $candidates = array_values(array_unique(array_merge([$id], $aliases)));
    $kevEntry = null;
    foreach ($candidates as $cand) {
        if ($cand !== '' && isset($kevMap[$cand])) {
            $kevEntry = $kevMap[$cand];
            break;
        }
    }

    $sevInfo = grid402_vuln_audit_extract_severity($vuln);
    $fixedIn = [];
    if (isset($vuln['affected']) && is_array($vuln['affected'])) {
        foreach ($vuln['affected'] as $aff) {
            if (!is_array($aff['ranges'] ?? null)) {
                continue;
            }
            foreach ($aff['ranges'] as $range) {
                if (!is_array($range['events'] ?? null)) {
                    continue;
                }
                foreach ($range['events'] as $evt) {
                    if (is_array($evt) && isset($evt['fixed']) && is_string($evt['fixed']) && trim($evt['fixed']) !== '') {
                        $fixedIn[] = trim($evt['fixed']);
                    }
                }
            }
        }
    }
    $fixedIn = array_values(array_unique($fixedIn));

    $refs = [];
    if (isset($vuln['references']) && is_array($vuln['references'])) {
        foreach (array_slice($vuln['references'], 0, 5) as $ref) {
            if (is_array($ref) && isset($ref['url']) && is_string($ref['url'])) {
                $refs[] = $ref['url'];
            }
        }
    }

    return [
        'id' => (string) ($vuln['id'] ?? $id),
        'aliases' => $aliases,
        'summary' => isset($vuln['summary']) ? (string) $vuln['summary'] : (isset($vuln['details']) ? substr((string) $vuln['details'], 0, 240) : null),
        'severity' => $sevInfo['severity'],
        'cvss_score' => $sevInfo['cvss_score'],
        'actively_exploited_kev' => $kevEntry !== null,
        'kev_due_date' => $kevEntry['due_date'] ?? null,
        'kev_ransomware' => $kevEntry['known_ransomware'] ?? null,
        'fixed_in' => $fixedIn,
        'published' => isset($vuln['published']) ? (string) $vuln['published'] : null,
        'modified' => isset($vuln['modified']) ? (string) $vuln['modified'] : null,
        'references' => $refs,
    ];
}

/**
 * Teaser gratuito para resposta HTTP 402.
 *
 * @return array<string, mixed>
 */
function grid402_service_vuln_audit_preview(
    string $ecosystem = 'npm',
    string $package = 'lodash',
    string $version = '',
    string $id = ''
): array {
    $hasInput = ($ecosystem !== '' || $package !== '' || $id !== '');
    $val = $hasInput
        ? grid402_vuln_audit_validate_input($ecosystem, $package, $version, $id)
        : grid402_vuln_audit_validate_input('npm', 'lodash', '', '');

    return [
        'preview' => [
            'query_mode' => $val['ok'] ? $val['mode'] : 'package',
            'ecosystem' => $val['ok'] && $val['ecosystem'] !== '' ? $val['ecosystem'] : null,
            'package' => $val['ok'] && $val['package'] !== '' ? $val['package'] : null,
            'id' => $val['ok'] && $val['id'] !== '' ? $val['id'] : null,
            'sources' => ['OSV.dev Public API', 'CISA KEV Official Catalog'],
        ],
        'locked_fields' => ['verdict', 'vulnerability_count', 'kev_match_count', 'severity_summary', 'vulnerabilities', '_oracle_proof'],
    ];
}

/**
 * Auditoria deterministica de vulnerabilidades por pacote ou ID (OSV + CISA KEV).
 *
 * @return array<string, mixed>
 */
function grid402_service_vuln_audit(
    string $ecosystem = '',
    string $package = '',
    string $version = '',
    string $id = ''
): array {
    $val = grid402_vuln_audit_validate_input($ecosystem, $package, $version, $id);
    if (empty($val['ok'])) {
        return [
            'success' => false,
            'error' => $val['error'],
            'message' => $val['message'],
            'code' => (int) ($val['code'] ?? 400),
        ];
    }

    $mode = (string) $val['mode'];
    $idClean = (string) $val['id'];
    $ecoClean = (string) $val['ecosystem'];
    $pkgClean = (string) $val['package'];
    $verClean = (string) $val['version'];

    $privateDir = defined('GRID402_PRIVATE_DIR') ? rtrim((string) GRID402_PRIVATE_DIR, '/\\') : (__DIR__ . '/../private_data');
    $useOverride = is_callable($GLOBALS['grid402_vuln_audit_http_override'] ?? null);

    $cacheKey = hash('sha256', $mode . '|' . $idClean . '|' . strtolower($ecoClean) . '|' . strtolower($pkgClean) . '|' . $verClean);
    $cacheFile = $privateDir . '/cache_vuln_audit_' . substr($cacheKey, 0, 24) . '.json';
    $cacheTtl = 900;

    if (!$useOverride && file_exists($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = $rawCache ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < $cacheTtl) {
            $decodedCache['cached'] = true;
            return $decodedCache;
        }
    }

    $rawVulns = [];
    if ($mode === 'id') {
        $osvUrl = 'https://api.osv.dev/v1/vulns/' . rawurlencode($idClean);
        $osvResp = grid402_vuln_audit_http_request('GET', $osvUrl, null, 5);
        if (empty($osvResp['ok']) || !is_array($osvResp['data'])) {
            if ((int) ($osvResp['status'] ?? 0) === 404) {
                return [
                    'success' => false,
                    'error' => 'VulnerabilityNotFound',
                    'message' => "Identificador '{$idClean}' nao encontrado no OSV.dev.",
                    'code' => 404,
                ];
            }
            if (!$useOverride && file_exists($cacheFile)) {
                $staleRaw = @file_get_contents($cacheFile);
                $stale = $staleRaw ? @json_decode($staleRaw, true) : null;
                if (is_array($stale)) {
                    $stale['cached'] = true;
                    $stale['stale_fallback'] = true;
                    return $stale;
                }
            }
            return [
                'success' => false,
                'error' => 'UpstreamUnavailable',
                'message' => 'OSV.dev API indisponivel no momento (' . (string) ($osvResp['error'] ?? 'error') . '). Nenhum debito foi mantido.',
                'code' => 503,
            ];
        }
        $rawVulns = [$osvResp['data']];
    } else {
        $queryBody = [
            'package' => [
                'ecosystem' => $ecoClean,
                'name' => $pkgClean,
            ],
        ];
        if ($verClean !== '') {
            $queryBody['version'] = $verClean;
        }
        $osvResp = grid402_vuln_audit_http_request('POST', 'https://api.osv.dev/v1/query', $queryBody, 5);
        if (empty($osvResp['ok']) || !is_array($osvResp['data'])) {
            if (!$useOverride && file_exists($cacheFile)) {
                $staleRaw = @file_get_contents($cacheFile);
                $stale = $staleRaw ? @json_decode($staleRaw, true) : null;
                if (is_array($stale)) {
                    $stale['cached'] = true;
                    $stale['stale_fallback'] = true;
                    return $stale;
                }
            }
            return [
                'success' => false,
                'error' => 'UpstreamUnavailable',
                'message' => 'OSV.dev API indisponivel no momento (' . (string) ($osvResp['error'] ?? 'error') . '). Nenhum debito foi mantido.',
                'code' => 503,
            ];
        }
        $rawVulns = is_array($osvResp['data']['vulns'] ?? null) ? $osvResp['data']['vulns'] : [];
    }

    $kevState = grid402_vuln_audit_load_kev_map($privateDir);
    $kevMap = $kevState['map'];
    $kevStatus = $kevState['status'];

    $normalized = [];
    $kevMatchCount = 0;
    $summaryCounts = [
        'critical' => 0,
        'high' => 0,
        'moderate' => 0,
        'low' => 0,
        'unknown' => 0,
    ];

    foreach ($rawVulns as $rv) {
        if (!is_array($rv)) {
            continue;
        }
        $item = grid402_vuln_audit_normalize_vuln($rv, $kevMap);
        if (!empty($item['actively_exploited_kev'])) {
            $kevMatchCount++;
        }
        $sevLower = strtolower((string) $item['severity']);
        if (isset($summaryCounts[$sevLower])) {
            $summaryCounts[$sevLower]++;
        } else {
            $summaryCounts['unknown']++;
        }
        $normalized[] = $item;
    }

    $sevWeight = ['CRITICAL' => 4, 'HIGH' => 3, 'MODERATE' => 2, 'LOW' => 1, 'UNKNOWN' => 0];
    usort($normalized, static function (array $a, array $b) use ($sevWeight): int {
        $kevCmp = ((int) !empty($b['actively_exploited_kev'])) <=> ((int) !empty($a['actively_exploited_kev']));
        if ($kevCmp !== 0) {
            return $kevCmp;
        }
        return ($sevWeight[$b['severity']] ?? 0) <=> ($sevWeight[$a['severity']] ?? 0);
    });

    $totalVulns = count($normalized);
    if ($kevMatchCount > 0) {
        $verdict = 'CRITICAL_KEV';
    } elseif ($summaryCounts['critical'] > 0) {
        $verdict = 'CRITICAL';
    } elseif ($summaryCounts['high'] > 0) {
        $verdict = 'HIGH';
    } elseif ($summaryCounts['moderate'] > 0) {
        $verdict = 'MODERATE';
    } elseif ($totalVulns > 0) {
        $verdict = 'LOW';
    } else {
        $verdict = 'CLEAN';
    }

    $target = $mode === 'id'
        ? ['id' => $idClean]
        : [
            'ecosystem' => $ecoClean,
            'package' => $pkgClean,
            'version' => $verClean !== '' ? $verClean : null,
        ];

    $payload = [
        'protocol' => 'x402',
        'service' => 'vuln-audit',
        'status' => 'UP',
        'query_mode' => $mode,
        'target' => $target,
        'verdict' => $verdict,
        'actively_exploited_kev' => $kevMatchCount > 0,
        'vulnerability_count' => $totalVulns,
        'kev_match_count' => $kevMatchCount,
        'severity_summary' => $summaryCounts,
        'vulnerabilities' => array_slice($normalized, 0, 25),
        'kev_source_status' => $kevStatus,
        'sources_status' => [
            'osv' => 'ok',
            'cisa_kev' => $kevStatus,
        ],
        'legal_disclaimer' => 'Automated open-source advisory aggregation from OSV.dev and CISA KEV. Informational security telemetry only; verify patch compatibility before production deployment.',
        'cached' => false,
        'cached_at' => time(),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if (!$useOverride && function_exists('grid402_atomic_write')) {
        grid402_atomic_write($cacheFile, $payload);
    }

    return $payload;
}
