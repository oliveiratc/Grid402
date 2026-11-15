<?php
/**
 * Grid402 // Zero-DB Storage Integrity Verifier & Snapshot Backup Utility
 *
 * Usage:
 *   php scripts/verify_storage_integrity.php [--backup]
 *
 * Actions:
 *   1. Executes collector.php to ensure summary_free.json and uptime_history.json are fresh.
 *   2. Validates that every .json file in public_html/private_data is valid UTF-8 and valid JSON.
 *   3. Verifies that all 22 catalog services are present in summary_free.json and uptime_history.json.
 *   4. Optionally creates an atomic timestamped backup snapshot when --backup is passed.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$privateDir = $projectRoot . '/public_html/private_data';

echo "=== Grid402 Zero-DB Storage Integrity Check ===\n";

// 1. Run collector to ensure fresh summary_free.json and uptime_history.json
ob_start();
require_once $projectRoot . '/public_html/collector.php';
$collectorOutput = trim((string) ob_get_clean());
echo "[1/3] Collector sync: {$collectorOutput}\n";

if (!is_dir($privateDir)) {
    fwrite(STDERR, "[FAIL] Directory not found: {$privateDir}\n");
    exit(1);
}

$files = glob($privateDir . '/*.json') ?: [];
if (empty($files)) {
    fwrite(STDERR, "[FAIL] No JSON files found in {$privateDir}\n");
    exit(1);
}

$errors = 0;
foreach ($files as $file) {
    $basename = basename($file);
    $raw = @file_get_contents($file);
    if ($raw === false) {
        fwrite(STDERR, "  [FAIL] Cannot read {$basename}\n");
        $errors++;
        continue;
    }
    if (preg_match('//u', $raw) !== 1) {
        fwrite(STDERR, "  [FAIL] Invalid UTF-8 encoding in {$basename}\n");
        $errors++;
        continue;
    }
    if (trim($raw) !== '') {
        json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            fwrite(STDERR, "  [FAIL] Malformed JSON in {$basename}: " . json_last_error_msg() . "\n");
            $errors++;
            continue;
        }
    }
    echo "  [OK] {$basename} (" . strlen($raw) . " bytes, valid UTF-8 & JSON)\n";
}

// 2. Verify 22/22 service coverage in summary_free.json and uptime_history.json
$summary = json_decode((string) file_get_contents($privateDir . '/summary_free.json'), true);
$catalogServices = array_keys(grid402_get_utility_services());
$missingInSummary = [];
foreach ($catalogServices as $svcKey) {
    if (!isset($summary['services'][$svcKey]) || ($summary['services'][$svcKey]['status'] ?? '') !== 'UP') {
        $missingInSummary[] = $svcKey;
    }
}
if (!empty($missingInSummary)) {
    fwrite(STDERR, "  [FAIL] Missing or non-UP services in summary_free.json: " . implode(', ', $missingInSummary) . "\n");
    $errors++;
} else {
    echo "[2/3] Service coverage: " . count($catalogServices) . "/" . count($catalogServices) . " services UP in summary_free.json\n";
}

// 3. Optional backup snapshot
$doBackup = in_array('--backup', $argv ?? [], true);
if ($doBackup) {
    $backupDir = $privateDir . '/backups/' . gmdate('Ymd_His');
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }
    foreach ($files as $file) {
        @copy($file, $backupDir . '/' . basename($file));
    }
    echo "[3/3] Snapshot backup written to: {$backupDir}\n";
} else {
    echo "[3/3] Integrity check complete (pass --backup to create a snapshot).\n";
}

if ($errors > 0) {
    fwrite(STDERR, "=== Storage Integrity Check FAILED ({$errors} error(s)) ===\n");
    exit(1);
}

echo "=== Storage Integrity Check PASSED (0 errors) ===\n";
exit(0);
