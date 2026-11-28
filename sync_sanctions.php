<?php
/**
 * Grid402 - Sincronizador CLI/Cron/HTTP Autenticado dos 4 Blocos Oficiais de Sancoes
 *
 * Fontes publicas oficiais (Zero risco de copyright / dominio publico / dados abertos):
 * 1. US Treasury OFAC SDN (sdn_advanced.xml - 17 U.S.C. 105)
 * 2. UN Security Council Consolidated Sanctions List (consolidated.xml)
 * 3. EU Financial Sanctions (FSFi XML)
 * 4. UK Sanctions List FCDO/OFSI (UK-Sanctions-List.xml - Open Government Licence v3.0)
 *
 * Uso CLI:
 *   /usr/bin/php /home/.../public_html/sync_sanctions.php
 *   php public_html/sync_sanctions.php --json
 *
 * Uso HTTP autenticado:
 *   GET/POST /sync_sanctions.php?token=CRON_SECRET (ou Authorization: Bearer CRON_SECRET)
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/sanctions_index.php';

if (!function_exists('grid402_sanctions_feed_definitions')) {
    function grid402_sanctions_feed_definitions(): array {
        return [
            'ofac_sdn' => [
                'url' => (string) grid402_env('GRID402_SANCTIONS_OFAC_URL', 'https://sanctionslistservice.ofac.treas.gov/api/publicationpreview/exports/sdn_advanced.xml'),
                'authority' => 'US Treasury OFAC SDN',
                'license' => 'Public Domain (17 U.S.C. 105 - US Government Works)',
                'fixture_candidates' => ['ofac_sdn.xml', 'sdn_advanced.xml'],
                'parser' => 'grid402_sanctions_parse_ofac_xml',
            ],
            'un_consolidated' => [
                'url' => (string) grid402_env('GRID402_SANCTIONS_UN_URL', 'https://scsanctions.un.org/resources/xml/en/consolidated.xml'),
                'authority' => 'UN Security Council Consolidated List',
                'license' => 'UN Open Data',
                'fixture_candidates' => ['un_consolidated.xml', 'consolidated.xml'],
                'parser' => 'grid402_sanctions_parse_un_xml',
            ],
            'eu_fsfi' => [
                'url' => (string) grid402_env('GRID402_SANCTIONS_EU_URL', 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content?token=dG9rZW4tMjAxNw'),
                'authority' => 'European Union Financial Sanctions (FSFi)',
                'license' => 'EU Open Data (Commission Decision 2011/833/EU)',
                'fixture_candidates' => ['eu_fsfi.xml', 'eu_fsf.xml'],
                'parser' => 'grid402_sanctions_parse_eu_xml',
            ],
            'uk_ofsi' => [
                'url' => (string) grid402_env('GRID402_SANCTIONS_UK_URL', 'https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.xml'),
                'authority' => 'UK Sanctions List (FCDO / HM Treasury OFSI)',
                'license' => 'Open Government Licence v3.0 (UK OGL)',
                'fixture_candidates' => ['uk_ofsi.xml', 'UK-Sanctions-List.xml', 'ConList.xml', 'uk_ofsi.csv', 'ConList.csv'],
                'parser' => 'grid402_sanctions_parse_uk_ofsi',
            ],
        ];
    }
}

if (!function_exists('grid402_sanctions_download_to_file')) {
    function grid402_sanctions_download_to_file(string $url, string $destPath): void {
        $fp = @fopen($destPath, 'wb');
        if (!$fp) {
            throw new RuntimeException("Falha ao criar arquivo temporario de download: {$destPath}");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Grid402-Sanctions-Sync/1.0 (+https://grid402.online)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $httpCode < 200 || $httpCode >= 300 || !is_file($destPath) || filesize($destPath) === 0) {
            @unlink($destPath);
            throw new RuntimeException("Falha ao baixar {$url} (HTTP {$httpCode}): {$err}");
        }
    }
}

if (!function_exists('grid402_sanctions_ensure_memory_limit')) {
    function grid402_sanctions_ensure_memory_limit(int $minBytes = 536870912): void {
        $raw = trim((string) @ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return;
        }
        $unit = strtolower(substr($raw, -1));
        $val = (int) $raw;
        $bytes = match ($unit) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
        if ($bytes > 0 && $bytes < $minBytes) {
            @ini_set('memory_limit', '512M');
        }
    }
}

if (!function_exists('grid402_run_sanctions_sync')) {
    function grid402_run_sanctions_sync(?string $fromDir = null, ?string $outputDir = null, ?string $syncedAt = null): array {
        grid402_sanctions_ensure_memory_limit(536870912);
        if (php_sapi_name() === 'cli') {
            @set_time_limit(0);
        } else {
            @set_time_limit(360);
        }

        $feeds = grid402_sanctions_feed_definitions();
        $targetDir = ($outputDir !== null && trim($outputDir) !== '')
            ? rtrim(trim($outputDir), '/\\')
            : grid402_sanctions_index_dir();

        $tmpWorkDir = sys_get_temp_dir() . '/grid402_sync_' . bin2hex(random_bytes(5));
        if (!@mkdir($tmpWorkDir, 0775, true)) {
            throw new RuntimeException("Nao foi possivel criar diretorio temporario {$tmpWorkDir}");
        }

        try {
            $parsedBySource = [];
            foreach ($feeds as $sourceKey => $meta) {
                $localFile = null;
                if ($fromDir !== null && trim($fromDir) !== '') {
                    $baseFrom = rtrim(trim($fromDir), '/\\');
                    foreach ($meta['fixture_candidates'] as $cand) {
                        $p = $baseFrom . '/' . $cand;
                        if (is_file($p) && is_readable($p)) {
                            $localFile = $p;
                            break;
                        }
                    }
                    if ($localFile === null) {
                        throw new RuntimeException("Fixture nao encontrada para {$sourceKey} em {$baseFrom}");
                    }
                } else {
                    $ext = str_ends_with(strtolower($meta['url']), '.csv') ? '.csv' : '.xml';
                    $localFile = $tmpWorkDir . '/' . $sourceKey . $ext;
                    grid402_sanctions_download_to_file($meta['url'], $localFile);
                }

                $parserFn = $meta['parser'];
                $parsed = $parserFn($localFile);
                $sha = hash_file('sha256', $localFile) ?: '';

                $parsedBySource[$sourceKey] = [
                    'records' => $parsed['records'],
                    'addresses' => $parsed['addresses'],
                    'authority' => $meta['authority'],
                    'license' => $meta['license'],
                    'url' => $meta['url'],
                    'source_sha256' => $sha,
                    'fetched_at' => $syncedAt ?? gmdate('Y-m-d\TH:i:s\Z'),
                ];
            }

            $manifest = grid402_sanctions_build_index($parsedBySource, $targetDir, $syncedAt);
            return [
                'ok' => true,
                'target_dir' => $targetDir,
                'manifest' => $manifest,
            ];
        } finally {
            grid402_sanctions_remove_dir_recursive($tmpWorkDir);
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $isCli = (php_sapi_name() === 'cli');

    if ($isCli) {
        $args = $_SERVER['argv'] ?? [];
        $asJson = in_array('--json', $args, true);
        $fromDir = null;
        $outputDir = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--from-dir=')) {
                $fromDir = substr($arg, strlen('--from-dir='));
            } elseif (str_starts_with($arg, '--output-dir=')) {
                $outputDir = substr($arg, strlen('--output-dir='));
            }
        }

        try {
            $res = grid402_run_sanctions_sync($fromDir, $outputDir);
            if ($asJson) {
                echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $m = $res['manifest'];
                echo "[OK] Indice de sancoes sincronizado em {$res['target_dir']}\n";
                echo "     synced_at={$m['synced_at']} | sha256={$m['dataset_sha256']}\n";
                echo "     total_entities={$m['total_entities']} | total_addresses={$m['total_addresses']}\n";
            }
            exit(0);
        } catch (Throwable $e) {
            $errOut = ['ok' => false, 'error' => $e->getMessage()];
            if ($asJson) {
                echo json_encode($errOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                fwrite(STDERR, "[FAIL] Sync abortado (indice anterior preservado): {$e->getMessage()}\n");
            }
            exit(1);
        }
    }

    // Execucao HTTP autenticada via token CRON_SECRET
    header('Content-Type: application/json; charset=utf-8');
    $queryToken = (string) ($_GET['token'] ?? '');
    $headerAuth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($headerAuth === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $headerAuth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    $bearerToken = str_starts_with($headerAuth, 'Bearer ') ? substr($headerAuth, 7) : '';
    $providedToken = ($queryToken !== '') ? $queryToken : $bearerToken;

    $isAuthorized = !grid402_is_default_secret()
        && $providedToken !== ''
        && hash_equals(GRID402_CRON_SECRET, $providedToken);

    if (!$isAuthorized) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Forbidden',
            'message' => 'Acesso nao autorizado ao sincronizador de sancoes.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    ignore_user_abort(true);
    @set_time_limit(360);

    try {
        $res = grid402_run_sanctions_sync();
        $m = $res['manifest'];
        $publicSources = [];
        foreach (($m['sources'] ?? []) as $k => $src) {
            $publicSources[$k] = [
                'authority' => $src['authority'] ?? $k,
                'records_count' => (int) ($src['records_count'] ?? 0),
                'addresses_count' => (int) ($src['addresses_count'] ?? 0),
                'source_sha256' => (string) ($src['source_sha256'] ?? ''),
            ];
        }
        echo json_encode([
            'ok' => true,
            'synced_at' => $m['synced_at'],
            'dataset_sha256' => $m['dataset_sha256'],
            'total_entities' => (int) $m['total_entities'],
            'total_addresses' => (int) $m['total_addresses'],
            'sources' => $publicSources,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}
