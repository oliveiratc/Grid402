<?php
define('GRID402_EXEC', true);
require_once __DIR__ . '/../public_html/config.php';
require_once __DIR__ . '/../public_html/x402.php';
require_once __DIR__ . '/../public_html/services/pass_checkout.php';
require_once __DIR__ . '/../public_html/services/pass_repository.php';

$cleanTx = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
grid402_claim_transaction_lock($cleanTx); // consome a trava

// Emite um passe simulado com este tx_hash para acionar o ramo idempotente
grid402_issue_session_pass($cleanTx, 2000000, false);

$res = grid402_emitir_passe_por_tx($cleanTx, 'base');

if ($res['ok'] !== false || ($res['status'] ?? '') !== 'already_issued' || isset($res['pass_token']) || $res['code'] !== 409) {
    echo "FALHA: " . json_encode($res) . "\n";
    exit(1);
}

echo "[PASS] IDOR /pass-emitir mitigado com sucesso: pass_token nao exposto no retorno idempotente\n";
