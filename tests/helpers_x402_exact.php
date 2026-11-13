<?php
/**
 * Helpers compartilhados dos testes de pagamento x402 `exact` (EIP-3009).
 * Usa diretorio de armazenamento temporario para nao tocar nos dados reais.
 */

declare(strict_types=1);

$GLOBALS['t_passed'] = 0;
$GLOBALS['t_failed'] = 0;

function t_bootstrap(string $mode = 'settle', ?string $storageDir = null, array $extraEnv = []): string {
    $dir = $storageDir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'grid402_test_' . bin2hex(random_bytes(4)));
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    putenv('GRID402_STORAGE_DIR=' . $dir);
    putenv('GRID402_FACILITATOR_MODE=' . $mode);
    // Testes homologam o profile CDP (paths /v2/x402/*) independentemente
    // dos defaults de producao (PayAI /verify + /settle).
    putenv('GRID402_FACILITATOR_VERIFY_PATH=/v2/x402/verify');
    putenv('GRID402_FACILITATOR_SETTLE_PATH=/v2/x402/settle');
    foreach ($extraEnv as $k => $v) {
        putenv($k . '=' . $v);
    }
    if (!defined('GRID402_EXEC')) {
        define('GRID402_EXEC', true);
    }
    require_once __DIR__ . '/../public_html/config.php';
    require_once __DIR__ . '/../public_html/x402.php';
    return $dir;
}

function t_assert(string $name, bool $condition, string $detail = ''): void {
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $GLOBALS['t_passed']++;
    } else {
        echo "  [FAIL] {$name}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        $GLOBALS['t_failed']++;
    }
}

function t_finish(string $title): never {
    echo "\n=== {$title}: {$GLOBALS['t_passed']} PASSOU | {$GLOBALS['t_failed']} FALHOU ===\n";
    exit($GLOBALS['t_failed'] > 0 ? 1 : 0);
}

/**
 * Monta um payload x402 padrao em base64. $over: ['auth' => [...], 'accepted' => [...], 'drop' => ['payload.signature'], 'version' => 1|2]
 */
function t_payload(array $over = []): string {
    $version = (int) ($over['version'] ?? 2);
    $auth = array_merge([
        'from' => '0x' . str_repeat('ab', 20),
        'to' => GRID402_WALLET_ADDRESS,
        'value' => '500',
        'validAfter' => '0',
        'validBefore' => (string) (time() + 300),
        'nonce' => '0x' . bin2hex(random_bytes(32)),
    ], $over['auth'] ?? []);

    $raw = [
        'x402Version' => $version,
        'payload' => [
            'signature' => '0x' . str_repeat('11', 65),
            'authorization' => $auth,
        ],
    ];
    if ($version === 2) {
        $raw['accepted'] = array_merge([
            'scheme' => 'exact',
            'network' => 'eip155:8453',
            'asset' => GRID402_USDC_CONTRACT,
            'amount' => '500',
            'payTo' => GRID402_WALLET_ADDRESS,
        ], $over['accepted'] ?? []);
    } else {
        $raw['scheme'] = 'exact';
        $raw['network'] = 'base';
    }

    foreach ($over['drop'] ?? [] as $path) {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $ref = &$raw;
        foreach ($keys as $k) {
            $ref = &$ref[$k];
        }
        unset($ref[$last]);
        unset($ref);
    }

    return base64_encode((string) json_encode($raw));
}

/**
 * Transporte simulado do facilitador. $handlers: ['/v2/x402/verify' => callable|array, '/v2/x402/settle' => ...]
 * Cada handler retorna ['status' => int, 'body' => array|string|null, 'error' => ?string].
 */
function t_install_transport(array $handlers, array &$calls): void {
    $GLOBALS['grid402_facilitator_transport'] = static function (string $url, array $headers, string $body, int $timeout) use ($handlers, &$calls): array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (str_contains($path, '/v2/x402/')) {
            $endpoint = substr($path, (int) strpos($path, '/v2/'));
        } else {
            $endpoint = '/' . basename($path);
        }
        $calls[] = ['endpoint' => $endpoint, 'headers' => $headers, 'body' => json_decode($body, true), 'timeout' => $timeout];
        $handler = $handlers[$endpoint] ?? ['status' => 404, 'body' => '{}', 'error' => null];
        $res = is_callable($handler) ? $handler() : $handler;
        if (is_array($res['body'] ?? null)) {
            $res['body'] = json_encode($res['body']);
        }
        return $res + ['error' => null];
    };
}

function t_rmdir(string $dir): void {
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

