<?php
/**
 * Grid402 - Sincronizador Diário CLI/Cron dos 4 Blocos Oficiais de Sanções
 *
 * Fontes públicas oficiais (Zero risco de copyright / domínio público / dados abertos):
 * 1. US Treasury OFAC SDN (sdn_advanced.xml - 17 U.S.C. § 105)
 * 2. UN Security Council Consolidated Sanctions List (consolidated.xml)
 * 3. EU Financial Sanctions (FSFi XML)
 * 4. UK OFSI Consolidated List (XML/CSV - Open Government Licence v3.0)
 *
 * Uso:
 *   php scripts/sync_sanctions_index.php
 *   php scripts/sync_sanctions_index.php --from-dir=/caminho/fixtures --output-dir=/caminho/destino --json
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../public_html/sync_sanctions.php';

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
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
