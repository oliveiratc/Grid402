<?php
/**
 * Grid402 - Validador CLI de Conformidade Coinbase CDP Bazaar v2
 *
 * Valida offline todos os endpoints pagos do catalogo contra as restricoes do CDP Bazaar:
 * - resource.url em HTTPS absoluto canonico
 * - resource.description estritamente tecnica com <= 500 caracteres
 * - ausencia de padroes de prompt-injection
 * - schema 2020-12 totalmente inlined (sem $ref ou $id externos)
 * - accepts[] validos com amount numerico e rede CAIP-2
 *
 * Opcional (--live): consulta https://api.cdp.coinbase.com/platform/v2/x402/validate (sem API key).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';

function grid402_bazaar_find_external_refs(mixed $node, string $path = ''): array {
    $issues = [];
    if (!is_array($node)) {
        return $issues;
    }
    foreach ($node as $key => $val) {
        $currPath = $path === '' ? (string) $key : ($path . '.' . (string) $key);
        if (($key === '$ref' || $key === '$id') && is_string($val)) {
            if (preg_match('#^https?://#i', trim($val)) === 1 || str_starts_with(trim($val), '//')) {
                $issues[] = "Referencia externa nao permitida em {$currPath}: {$val}";
            }
        }
        if (is_array($val)) {
            $issues = array_merge($issues, grid402_bazaar_find_external_refs($val, $currPath));
        }
    }
    return $issues;
}

function grid402_bazaar_has_prompt_injection(string $text): bool {
    $patterns = [
        '/ignore\s+(?:all\s+)?previous\s+instructions/i',
        '/disregard\s+(?:all\s+)?(?:prior|previous)\s+instructions/i',
        '/system\s+prompt/i',
        '/you\s+must\s+(?:buy|call|pay)/i',
        '/<\/?script\b/i',
    ];
    foreach ($patterns as $pat) {
        if (preg_match($pat, $text) === 1) {
            return true;
        }
    }
    return false;
}

function grid402_validate_cdp_bazaar_catalog(?string $onlySlug = null, bool $liveCheck = false, ?callable $liveHttp = null): array {
    $services = grid402_get_utility_services();
    $results = [];
    $passed = 0;
    $failed = 0;

    foreach ($services as $slug => $svc) {
        $priceStr = (string) ($svc['price_usdc'] ?? '0');
        if ($slug === 'verify' || strtolower($priceStr) === 'gratuito' || (float) $priceStr <= 0.0) {
            continue;
        }
        if ($onlySlug !== null && $onlySlug !== '' && $slug !== strtolower(trim($onlySlug))) {
            continue;
        }

        $priceRaw = (int) round(((float) $priceStr) * 1000000);
        $canonicalPath = '/api/v1/' . $slug;
        $resource = grid402_build_resource_info($slug, $priceStr, $canonicalPath);
        $bazaar = grid402_build_bazaar_extension(null, $slug, 'GET');
        $accepts = grid402_build_accepts($priceRaw);

        $errors = [];
        $url = (string) ($resource['url'] ?? '');
        $desc = (string) ($resource['description'] ?? '');

        if (!preg_match('#^https://[a-z0-9.-]+/api/v1/[a-z0-9-]+$#i', $url)) {
            $errors[] = "resource.url invalida para CDP Bazaar: {$url}";
        }
        if ($desc === '' || strlen($desc) > 500) {
            $errors[] = 'resource.description ausente ou > 500 chars (' . strlen($desc) . ')';
        }
        if (grid402_bazaar_has_prompt_injection($desc)) {
            $errors[] = 'resource.description contem padrao suspeito de prompt-injection';
        }
        if (empty($bazaar['info']['input']) || empty($bazaar['info']['output']) || empty($bazaar['schema'])) {
            $errors[] = 'extensions.bazaar incompleta (info.input, info.output e schema obrigatorios)';
        }
        $extRefs = grid402_bazaar_find_external_refs($bazaar['schema'] ?? []);
        if (!empty($extRefs)) {
            $errors = array_merge($errors, $extRefs);
        }
        if (empty($accepts)) {
            $errors[] = 'accepts[] vazio';
        } else {
            foreach ($accepts as $idx => $acc) {
                if (!preg_match('/^\d+$/', (string) ($acc['amount'] ?? ''))) {
                    $errors[] = "accepts[{$idx}].amount deve ser string inteira numerica";
                }
                if (empty($acc['network']) || !str_contains((string) $acc['network'], ':')) {
                    $errors[] = "accepts[{$idx}].network deve usar formato CAIP-2";
                }
            }
        }

        $liveOutcome = null;
        if ($liveCheck && empty($errors)) {
            $payload = (string) json_encode(['resource' => $url, 'method' => 'GET'], JSON_UNESCAPED_SLASHES);
            if ($liveHttp !== null) {
                $resp = $liveHttp('https://api.cdp.coinbase.com/platform/v2/x402/validate', $payload);
            } else {
                $ch = curl_init('https://api.cdp.coinbase.com/platform/v2/x402/validate');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                $rawResp = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                $resp = ['status' => $httpCode, 'body' => is_string($rawResp) ? $rawResp : '', 'error' => $err];
            }

            $decodedLive = @json_decode((string) ($resp['body'] ?? ''), true);
            $liveValid = ((int) ($resp['status'] ?? 0) === 200) && is_array($decodedLive) && !empty($decodedLive['valid']);
            $liveOutcome = [
                'http_status' => (int) ($resp['status'] ?? 0),
                'valid' => $liveValid,
                'outcome' => is_array($decodedLive) ? ($decodedLive['simulation']['outcome'] ?? null) : null,
                'error' => !$liveValid ? ($resp['error'] ?: ($decodedLive['message'] ?? $decodedLive['error'] ?? 'CDP live validation failed')) : null,
            ];
            if (!$liveValid) {
                $errors[] = 'CDP live validate falhou: ' . (string) $liveOutcome['error'];
            }
        }

        $ok = empty($errors);
        if ($ok) {
            $passed++;
        } else {
            $failed++;
        }

        $results[$slug] = [
            'ok' => $ok,
            'resource_url' => $url,
            'description_len' => strlen($desc),
            'accepts_count' => count($accepts),
            'errors' => $errors,
            'live' => $liveOutcome,
        ];
    }

    return [
        'ok' => ($failed === 0 && $passed > 0),
        'total' => $passed + $failed,
        'passed' => $passed,
        'failed' => $failed,
        'live_mode' => $liveCheck,
        'endpoints' => $results,
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $args = $_SERVER['argv'] ?? [];
    $asJson = in_array('--json', $args, true);
    $live = in_array('--live', $args, true);
    $onlyEndpoint = null;
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--endpoint=')) {
            $onlyEndpoint = substr($arg, strlen('--endpoint='));
        }
    }

    $report = grid402_validate_cdp_bazaar_catalog($onlyEndpoint, $live);
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "=== Grid402 CDP Bazaar v2 Validator ===\n";
        echo "Modo: " . ($live ? 'Offline + CDP Live' : 'Offline Estrutural') . "\n";
        echo "Total: {$report['total']} | Aprovados: {$report['passed']} | Falhas: {$report['failed']}\n\n";
        foreach ($report['endpoints'] as $slug => $row) {
            $tag = $row['ok'] ? '[PASS]' : '[FAIL]';
            echo "{$tag} {$slug} ({$row['resource_url']}, desc={$row['description_len']}c, accepts={$row['accepts_count']})\n";
            foreach ($row['errors'] as $err) {
                echo "       -> {$err}\n";
            }
        }
    }
    exit($report['ok'] ? 0 : 1);
}
