<?php
/**
 * Grid402 - Gerador Binário PDF 1.4 Nativo em PHP Puro (Dossiê 360º KYB)
 *
 * - Zero dependências externas / Zero Composer / Compatível sem mbstring.
 * - Zero-PII / Zero-DB: gera tudo em memória sem gravar arquivos em disco.
 * - Valida assinatura ECDSA secp256k1 (_oracle_proof) antes de emitir laudo oficial,
 *   aceitando apenas a amostra estática oficial marcada como AMOSTRA ILUSTRATIVA.
 * - Desenha QR Code de auditoria vetorial diretamente em operadores gráficos PDF.
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
require_once __DIR__ . '/oracle_signer.php';

/**
 * Normaliza e valida um payload de Dossiê 360º KYB para geração de PDF.
 * Impede uso do gerador para forjar laudos falsos sem assinatura criptográfica do oráculo.
 *
 * @return array{ok: bool, code: int, error?: string, message?: string, is_sample?: bool, dossier?: array}
 */
function grid402_dossier_pdf_validate_payload(array $payload): array {
    $rawCnpj = preg_replace('/\D/', '', (string) ($payload['cnpj_raw'] ?? $payload['cnpj'] ?? ($payload['cnpj_data']['cnpj'] ?? '')));
    $isSampleDeclared = !empty($payload['is_sample_demo']) || !empty($payload['sample_demo']);

    if ($isSampleDeclared) {
        if ($rawCnpj !== '00000000000191') {
            return [
                'ok' => false,
                'code' => 403,
                'error' => 'InvalidSamplePayload',
                'message' => 'Amostra ilustrativa permitida apenas para o CNPJ de demonstracao oficial (00.000.000/0001-91).',
            ];
        }
        return [
            'ok' => true,
            'code' => 200,
            'is_sample' => true,
            'dossier' => $payload,
        ];
    }

    if ($rawCnpj === '' || strlen($rawCnpj) !== 14) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidDossierPayload',
            'message' => 'Payload de Dossie 360 KYB invalido ou sem CNPJ estruturado.',
        ];
    }

    if (empty($payload['verdict']) && empty($payload['company']) && empty($payload['cnpj_data'])) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidDossierPayload',
            'message' => 'Payload nao contem blocos de veredito ou dados cadastrais de Dossie KYB.',
        ];
    }

    $verify = grid402_verify_oracle_proof($payload);
    if (empty($verify['valid'])) {
        return [
            'ok' => false,
            'code' => 403,
            'error' => 'InvalidOracleProof',
            'message' => 'Assinatura criptografica _oracle_proof ausente ou invalida para emissao do PDF oficial.',
        ];
    }

    return [
        'ok' => true,
        'code' => 200,
        'is_sample' => false,
        'dossier' => $payload,
    ];
}

/**
 * Converte UTF-8 para WinAnsiEncoding (Windows-1252) em PHP puro (sem exigir mbstring/iconv)
 * e aplica escape de literais de string PDF.
 */
function grid402_pdf_encode_winansi(string $text): string {
    static $map = [
        '€' => "\x80", '‚' => "\x82", '„' => "\x84", '…' => "\x85", '†' => "\x86",
        '‡' => "\x87", '‰' => "\x89", '‹' => "\x8B", '‘' => "\x91", '’' => "\x92",
        '“' => "\x93", '”' => "\x94", '•' => "\x95", '–' => "\x96", '—' => "\x97",
        '™' => "\x99", '›' => "\x9B", "\xC2\xA0" => ' ', '¡' => "\xA1", '¢' => "\xA2",
        '£' => "\xA3", '¤' => "\xA4", '¥' => "\xA5", '§' => "\xA7", '¨' => "\xA8",
        '©' => "\xA9", 'ª' => "\xAA", '«' => "\xAB", '¬' => "\xAC", '®' => "\xAE",
        '°' => "\xB0", '±' => "\xB1", '²' => "\xB2", '³' => "\xB3", '´' => "\xB4",
        'µ' => "\xB5", '·' => "\xB7", '¹' => "\xB9", 'º' => "\xBA", '»' => "\xBB",
        '¼' => "\xBC", '½' => "\xBD", '¾' => "\xBE", '¿' => "\xBF",
        'À' => "\xC0", 'Á' => "\xC1", 'Â' => "\xC2", 'Ã' => "\xC3", 'Ä' => "\xC4",
        'Å' => "\xC5", 'Æ' => "\xC6", 'Ç' => "\xC7", 'È' => "\xC8", 'É' => "\xC9",
        'Ê' => "\xCA", 'Ë' => "\xCB", 'Ì' => "\xCC", 'Í' => "\xCD", 'Î' => "\xCE",
        'Ï' => "\xCF", 'Ñ' => "\xD1", 'Ò' => "\xD2", 'Ó' => "\xD3", 'Ô' => "\xD4",
        'Õ' => "\xD5", 'Ö' => "\xD6", '×' => "\xD7", 'Ø' => "\xD8", 'Ù' => "\xD9",
        'Ú' => "\xDA", 'Û' => "\xDB", 'Ü' => "\xDC", 'Ý' => "\xDD", 'ß' => "\xDF",
        'à' => "\xE0", 'á' => "\xE1", 'â' => "\xE2", 'ã' => "\xE3", 'ä' => "\xE4",
        'å' => "\xE5", 'æ' => "\xE6", 'ç' => "\xE7", 'è' => "\xE8", 'é' => "\xE9",
        'ê' => "\xEA", 'ë' => "\xEB", 'ì' => "\xEC", 'í' => "\xED", 'î' => "\xEE",
        'ï' => "\xEF", 'ñ' => "\xF1", 'ò' => "\xF2", 'ó' => "\xF3", 'ô' => "\xF4",
        'õ' => "\xF5", 'ö' => "\xF6", '÷' => "\xF7", 'ø' => "\xF8", 'ù' => "\xF9",
        'ú' => "\xFA", 'û' => "\xFB", 'ü' => "\xFC", 'ý' => "\xFD", 'ÿ' => "\xFF",
        '✓' => 'OK', '✗' => 'X', '→' => '->', '↗' => '', '≈' => '~',
    ];

    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';
    $chars = preg_split('//u', $clean, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        // Fallback seguro byte a byte caso string possua sequencia UTF-8 invalida
        $chars = str_split($clean);
    }

    $out = '';
    foreach ($chars as $ch) {
        if (isset($map[$ch])) {
            $out .= $map[$ch];
            continue;
        }
        if (strlen($ch) === 1 && ord($ch) < 0x80) {
            if ($ch === '\\' || $ch === '(' || $ch === ')') {
                $out .= '\\' . $ch;
            } else {
                $out .= $ch;
            }
            continue;
        }
        $out .= '?';
    }

    return $out;
}

/**
 * Quebra uma string UTF-8 em múltiplas linhas respeitando largura aproximada em caracteres.
 *
 * @return array<int, string>
 */
function grid402_pdf_wrap_lines(string $text, int $maxChars = 84): array {
    $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($normalized === '') {
        return ['—'];
    }
    $words = explode(' ', $normalized);
    $lines = [];
    $current = '';

    $utf8Len = static function (string $s): int {
        return preg_match_all('/./u', $s) ?: strlen($s);
    };

    foreach ($words as $word) {
        if ($current === '') {
            if ($utf8Len($word) <= $maxChars) {
                $current = $word;
            } else {
                while ($utf8Len($word) > $maxChars) {
                    $chunk = substr($word, 0, $maxChars);
                    $lines[] = $chunk;
                    $word = substr($word, strlen($chunk));
                }
                $current = $word;
            }
            continue;
        }
        if ($utf8Len($current . ' ' . $word) <= $maxChars) {
            $current .= ' ' . $word;
        } else {
            $lines[] = $current;
            $current = $word;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines ?: ['—'];
}

/**
 * Codificador QR Code vetorial em PHP puro (Modo Byte, ECC-L, Versões 1 a 5 até 106 bytes).
 *
 * @return array<int, array<int, int>> Matriz quadrada 0/1 (branco/preto)
 */
function grid402_qr_encode_matrix(string $text): array {
    $data = substr($text, 0, 106);
    $dataLen = strlen($data);

    // Especificação ECC-L bloco único: [versão => [size, totalDataCodewords, ecCodewords, alignCoords]]
    $specs = [
        1 => [21, 19, 7, []],
        2 => [25, 34, 10, [6, 18]],
        3 => [29, 55, 15, [6, 22]],
        4 => [33, 80, 20, [6, 26]],
        5 => [37, 108, 26, [6, 30]],
    ];

    $version = 5;
    foreach ($specs as $v => $sp) {
        if ($dataLen <= ($sp[1] - 2)) {
            $version = $v;
            break;
        }
    }
    [$size, $dataCap, $ecCount, $alignCoords] = $specs[$version];

    // Bitstream: Mode Byte (0100) + Length (8 bits) + Bytes + Terminator + Pad 0xEC/0x11
    $bits = [];
    $pushBits = static function (int $val, int $count) use (&$bits): void {
        for ($i = $count - 1; $i >= 0; $i--) {
            $bits[] = ($val >> $i) & 1;
        }
    };
    $pushBits(0b0100, 4);
    $pushBits($dataLen, 8);
    for ($i = 0; $i < $dataLen; $i++) {
        $pushBits(ord($data[$i]), 8);
    }
    $maxBits = $dataCap * 8;
    $termLen = min(4, $maxBits - count($bits));
    for ($i = 0; $i < $termLen; $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }
    $codewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($b = 0; $b < 8; $b++) {
            $byte = ($byte << 1) | $bits[$i + $b];
        }
        $codewords[] = $byte;
    }
    $padBytes = [0xEC, 0x11];
    $padIdx = 0;
    while (count($codewords) < $dataCap) {
        $codewords[] = $padBytes[$padIdx % 2];
        $padIdx++;
    }

    // GF(256) tabelas com polinômio primitivo 0x11D
    $gfExp = array_fill(0, 512, 0);
    $gfLog = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $gfExp[$i] = $x;
        $gfLog[$x] = $i;
        $x <<= 1;
        if ($x >= 256) {
            $x ^= 0x11D;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $gfExp[$i] = $gfExp[$i - 255];
    }
    $gfMul = static fn(int $a, int $b): int => ($a === 0 || $b === 0) ? 0 : $gfExp[$gfLog[$a] + $gfLog[$b]];

    // Polinômio gerador Reed-Solomon grau $ecCount
    $gen = [1];
    for ($i = 0; $i < $ecCount; $i++) {
        $nextGen = array_fill(0, count($gen) + 1, 0);
        $root = $gfExp[$i];
        for ($j = 0; $j < count($gen); $j++) {
            $nextGen[$j] ^= $gen[$j];
            $nextGen[$j + 1] ^= $gfMul($gen[$j], $root);
        }
        $gen = $nextGen;
    }

    // Cálculo dos bytes de correção de erro
    $ec = array_fill(0, $ecCount, 0);
    foreach ($codewords as $cw) {
        $factor = $cw ^ $ec[0];
        array_shift($ec);
        $ec[] = 0;
        for ($j = 0; $j < $ecCount; $j++) {
            $ec[$j] ^= $gfMul($gen[$j + 1], $factor);
        }
    }
    $allCodewords = array_merge($codewords, $ec);

    // Matriz (-1 = não preenchido, 0 = branco, 1 = preto)
    $matrix = array_fill(0, $size, array_fill(0, $size, -1));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));

    $setFunc = static function (int $r, int $c, int $val) use (&$matrix, &$reserved, $size): void {
        if ($r >= 0 && $r < $size && $c >= 0 && $c < $size) {
            $matrix[$r][$c] = $val;
            $reserved[$r][$c] = true;
        }
    };

    $drawFinder = static function (int $top, int $left) use ($setFunc): void {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                if ($dr === -1 || $dr === 7 || $dc === -1 || $dc === 7) {
                    $setFunc($top + $dr, $left + $dc, 0);
                } else {
                    $isDark = ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6)
                        || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4);
                    $setFunc($top + $dr, $left + $dc, $isDark ? 1 : 0);
                }
            }
        }
    };
    $drawFinder(0, 0);
    $drawFinder(0, $size - 7);
    $drawFinder($size - 7, 0);

    // Alignment pattern (versões 2–5)
    foreach ($alignCoords as $ar) {
        foreach ($alignCoords as $ac) {
            if ($reserved[$ar][$ac]) {
                continue;
            }
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $isDark = (max(abs($dr), abs($dc)) !== 1);
                    $setFunc($ar + $dr, $ac + $dc, $isDark ? 1 : 0);
                }
            }
        }
    }

    // Timing patterns
    for ($i = 8; $i < $size - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        if (!$reserved[6][$i]) {
            $setFunc(6, $i, $bit);
        }
        if (!$reserved[$i][6]) {
            $setFunc($i, 6, $bit);
        }
    }

    // Dark module + reserva temporária dos bits de formato
    $setFunc(4 * $version + 9, 8, 1);
    for ($i = 0; $i < 9; $i++) {
        if ($i !== 6) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $reserved[8][$size - 1 - $i] = true;
        $reserved[$size - 1 - $i][8] = true;
    }

    // Bitstream total + colocação em zigue-zague com máscara 0 ((r + c) % 2 === 0)
    $allBits = [];
    foreach ($allCodewords as $cw) {
        for ($b = 7; $b >= 0; $b--) {
            $allBits[] = ($cw >> $b) & 1;
        }
    }
    $bitIdx = 0;
    $bitTotal = count($allBits);
    $up = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) {
            $col--;
        }
        for ($step = 0; $step < $size; $step++) {
            $r = $up ? ($size - 1 - $step) : $step;
            for ($dc = 0; $dc < 2; $dc++) {
                $c = $col - $dc;
                if ($reserved[$r][$c]) {
                    continue;
                }
                $rawBit = ($bitIdx < $bitTotal) ? $allBits[$bitIdx++] : 0;
                $masked = ((($r + $c) % 2) === 0) ? ($rawBit ^ 1) : $rawBit;
                $matrix[$r][$c] = $masked;
            }
        }
        $up = !$up;
    }

    // Format Info BCH(15, 5) dinâmico para ECC-L (01) + Mask 0 (000)
    $fmtData = (0b01 << 3) | 0b000;
    $rem = $fmtData << 10;
    for ($i = 4; $i >= 0; $i--) {
        if (($rem & (1 << ($i + 10))) !== 0) {
            $rem ^= (0b10100110111 << $i);
        }
    }
    $fmtBits = (($fmtData << 10) | $rem) ^ 0b101010000010010;

    for ($i = 0; $i <= 5; $i++) {
        $b = ($fmtBits >> $i) & 1;
        $matrix[8][$i] = $b;
        $matrix[$size - 1 - $i][8] = $b;
    }
    $matrix[8][7] = ($fmtBits >> 6) & 1;
    $matrix[8][8] = ($fmtBits >> 7) & 1;
    $matrix[7][8] = ($fmtBits >> 8) & 1;
    $matrix[$size - 7][8] = ($fmtBits >> 6) & 1;
    $matrix[8][$size - 8] = ($fmtBits >> 7) & 1;
    for ($i = 9; $i <= 14; $i++) {
        $b = ($fmtBits >> $i) & 1;
        $matrix[14 - $i][8] = $b;
        $matrix[8][$size - 15 + $i] = $b;
    }

    return $matrix;
}

/**
 * Renderiza o Dossiê 360º KYB em arquivo binário PDF 1.4 válido em memória (A4 multipágina).
 */
function grid402_dossier_pdf_render(array $dossier, bool $isSample = false): string {
    $cnpjRaw = preg_replace('/\D/', '', (string) ($dossier['cnpj_raw'] ?? $dossier['cnpj'] ?? ($dossier['cnpj_data']['cnpj'] ?? '00000000000000')));
    $cnpjFmt = (strlen($cnpjRaw) === 14 && function_exists('grid402_format_cnpj'))
        ? grid402_format_cnpj($cnpjRaw)
        : (string) ($dossier['cnpj'] ?? $cnpjRaw);

    $verdict = is_array($dossier['verdict'] ?? null) ? $dossier['verdict'] : [];
    $verdictStatus = strtoupper((string) ($verdict['status'] ?? $verdict['decision'] ?? $dossier['ai_verdict'] ?? 'APPROVED'));
    if (!in_array($verdictStatus, ['APPROVED', 'FLAGGED', 'REJECTED'], true)) {
        $verdictStatus = 'FLAGGED';
    }
    $compScoreObj = $dossier['compliance_score'] ?? null;
    $scoreRaw = $verdict['score'] ?? (is_array($compScoreObj) ? ($compScoreObj['score'] ?? null) : $compScoreObj) ?? ($dossier['score'] ?? 0);
    $score = (int) $scoreRaw;
    $verdictSummary = (string) ($verdict['summary'] ?? $verdict['reason'] ?? 'Auditoria corporativa consolidada a partir de fontes públicas oficiais.');

    $company = is_array($dossier['company'] ?? null)
        ? $dossier['company']
        : (is_array($dossier['cnpj_data'] ?? null) ? $dossier['cnpj_data'] : []);
    $tax = is_array($dossier['tax_regime'] ?? null)
        ? $dossier['tax_regime']
        : (is_array($dossier['simples'] ?? null) ? $dossier['simples'] : []);
    $sanctions = is_array($dossier['sanctions'] ?? null)
        ? $dossier['sanctions']
        : (is_array($dossier['cgu_sanctions'] ?? null) ? $dossier['cgu_sanctions'] : []);
    $proof = is_array($dossier['_oracle_proof'] ?? null) ? $dossier['_oracle_proof'] : [];
    $meta = is_array($dossier['_meta'] ?? null) ? $dossier['_meta'] : [];

    $pages = [''];
    $pageIdx = 0;
    $y = 841.89;

    $appendCmd = static function (string $cmd) use (&$pages, &$pageIdx): void {
        $pages[$pageIdx] .= $cmd . "\n";
    };
    $fmtRgb = static fn(array $rgb): string => sprintf('%.3F %.3F %.3F', $rgb[0], $rgb[1], $rgb[2]);

    $drawRect = static function (float $x, float $topY, float $w, float $h, array $fillRgb, ?array $strokeRgb = null, float $lw = 0.6) use ($appendCmd, $fmtRgb): void {
        $bottomY = $topY - $h;
        $appendCmd('q');
        $appendCmd($fmtRgb($fillRgb) . ' rg');
        if ($strokeRgb !== null) {
            $appendCmd(sprintf('%.2F w ', $lw) . $fmtRgb($strokeRgb) . ' RG');
            $appendCmd(sprintf('%.2F %.2F %.2F %.2F re B', $x, $bottomY, $w, $h));
        } else {
            $appendCmd(sprintf('%.2F %.2F %.2F %.2F re f', $x, $bottomY, $w, $h));
        }
        $appendCmd('Q');
    };

    $drawText = static function (float $x, float $topY, string $txt, string $font = 'F1', float $size = 9.5, array $rgb = [0.09, 0.13, 0.22]) use ($appendCmd, $fmtRgb): void {
        $encoded = grid402_pdf_encode_winansi($txt);
        $appendCmd('BT');
        $appendCmd("/{$font} " . sprintf('%.2F', $size) . ' Tf');
        $appendCmd($fmtRgb($rgb) . ' rg');
        $appendCmd(sprintf('1 0 0 1 %.2F %.2F Tm', $x, $topY));
        $appendCmd("({$encoded}) Tj");
        $appendCmd('ET');
    };

    $tzBrt = new DateTimeZone('America/Sao_Paulo');
    $nowBrt = (new DateTime('now', $tzBrt))->format('d/m/Y H:i:s') . ' BRT';
    $fetchedIso = (string) ($meta['fetched_at'] ?? $dossier['checked_at'] ?? gmdate('Y-m-d\TH:i:s\Z'));

    $startPageHeader = static function (bool $isContinuation) use (&$y, $drawRect, $drawText, $isSample, $nowBrt, $cnpjFmt): void {
        // Cabeçalho institucional Grid402
        $headerH = $isContinuation ? 48.0 : 66.0;
        $drawRect(0.0, 841.89, 595.28, $headerH, [0.043, 0.075, 0.169]);
        $drawRect(0.0, 841.89 - $headerH, 595.28, 2.5, [0.0, 0.72, 0.85]);

        $drawText(36.0, 816.0, 'GRID402 // DOSSIE 360 KYB CORPORATIVO' . ($isContinuation ? ' (CONT.)' : ''), 'F2', $isContinuation ? 11.5 : 14.0, [1.0, 1.0, 1.0]);
        if (!$isContinuation) {
            $drawText(36.0, 798.0, 'Receita Federal (CNPJ) | Simples Nacional / MEI | Sancoes CGU (CEIS/CNEP) | Oracle Proof ECDSA', 'F1', 8.2, [0.58, 0.72, 0.88]);
            $drawText(36.0, 784.5, "CNPJ Auditado: {$cnpjFmt}   |   Emitido: {$nowBrt}", 'F3', 8.0, [0.0, 0.94, 1.0]);
        } else {
            $drawText(36.0, 801.0, "CNPJ Auditado: {$cnpjFmt}   |   Emitido: {$nowBrt}", 'F3', 8.0, [0.58, 0.72, 0.88]);
        }

        $y = 841.89 - $headerH - 14.0;

        if ($isSample) {
            $drawRect(36.0, $y, 523.28, 20.0, [0.996, 0.953, 0.780], [0.851, 0.467, 0.024], 0.9);
            $drawText(46.0, $y - 13.5, 'AMOSTRA ILUSTRATIVA - SEM VALOR LEGAL (Emitido para demonstracao em /dossie-kyb)', 'F2', 8.8, [0.573, 0.251, 0.055]);
            $y -= 28.0;
        }
    };

    $ensureSpace = static function (float $neededHeight) use (&$pages, &$pageIdx, &$y, $startPageHeader, $drawText): void {
        if (($y - $neededHeight) < 58.0) {
            $drawText(36.0, 26.0, 'Grid402 Network Intelligence (https://grid402.online/dossie-kyb) · Pagina ' . ($pageIdx + 1), 'F1', 7.5, [0.45, 0.50, 0.60]);
            $pageIdx++;
            $pages[$pageIdx] = '';
            $startPageHeader(true);
        }
    };

    $drawSectionTitle = static function (string $title) use (&$y, $ensureSpace, $drawRect, $drawText): void {
        $ensureSpace(34.0);
        $drawRect(36.0, $y, 523.28, 19.0, [0.933, 0.957, 0.984], [0.796, 0.835, 0.882], 0.5);
        $drawRect(36.0, $y, 3.5, 19.0, [0.0, 0.322, 1.0]);
        $drawText(46.0, $y - 13.0, $title, 'F2', 9.8, [0.06, 0.11, 0.22]);
        $y -= 27.0;
    };

    $drawKvRows = static function (array $rows) use (&$y, $ensureSpace, $drawText): void {
        foreach ($rows as [$label, $val]) {
            $wrapped = grid402_pdf_wrap_lines((string) $val, 62);
            $rowH = max(14.5, count($wrapped) * 12.2 + 3.0);
            $ensureSpace($rowH);
            $drawText(42.0, $y, $label . ':', 'F2', 8.7, [0.28, 0.34, 0.44]);
            $lineY = $y;
            foreach ($wrapped as $wLine) {
                $drawText(195.0, $lineY, $wLine, 'F1', 8.8, [0.07, 0.10, 0.16]);
                $lineY -= 12.0;
            }
            $y -= $rowH;
        }
        $y -= 6.0;
    };

    // Página 1: Cabeçalho e Card de Veredito
    $startPageHeader(false);

    $verdictColors = match ($verdictStatus) {
        'APPROVED' => ['bg' => [0.925, 0.992, 0.961], 'border' => [0.063, 0.725, 0.506], 'fg' => [0.024, 0.373, 0.275]],
        'REJECTED' => ['bg' => [0.996, 0.949, 0.949], 'border' => [0.937, 0.267, 0.267], 'fg' => [0.600, 0.106, 0.106]],
        default => ['bg' => [1.0, 0.984, 0.922], 'border' => [0.961, 0.620, 0.043], 'fg' => [0.573, 0.251, 0.055]],
    };
    $summaryLines = grid402_pdf_wrap_lines($verdictSummary, 66);
    $verdictCardH = max(56.0, 34.0 + count($summaryLines) * 12.0);
    $drawRect(36.0, $y, 523.28, $verdictCardH, $verdictColors['bg'], $verdictColors['border'], 1.1);
    $drawText(48.0, $y - 16.0, 'VEREDITO DETERMINISTICO KYB: ' . $verdictStatus, 'F2', 11.5, $verdictColors['fg']);
    $drawText(450.0, $y - 16.0, "SCORE: {$score}/100", 'F2', 11.5, $verdictColors['fg']);
    $sumY = $y - 32.0;
    foreach ($summaryLines as $sLine) {
        $drawText(48.0, $sumY, $sLine, 'F1', 8.8, [0.12, 0.16, 0.24]);
        $sumY -= 11.5;
    }
    $y -= ($verdictCardH + 14.0);

    // Seção 1: Identidade Cadastral
    $drawSectionTitle('1. Identidade Cadastral (Receita Federal - Zero-PII)');
    $capSocial = isset($company['capital_social']) && is_numeric($company['capital_social'])
        ? ('R$ ' . number_format((float) $company['capital_social'], 2, ',', '.'))
        : '—';
    $cnaeTxt = trim(implode(' - ', array_filter([
        isset($company['cnae_fiscal']) ? (string) $company['cnae_fiscal'] : '',
        (string) ($company['cnae_fiscal_descricao'] ?? ''),
    ])));
    $endObj = is_array($company['endereco'] ?? null) ? $company['endereco'] : $company;
    $addrTxt = trim(implode(', ', array_filter([
        (string) ($endObj['logradouro'] ?? ''),
        (string) ($endObj['numero'] ?? ''),
        (string) ($endObj['bairro'] ?? ''),
        (string) ($endObj['municipio'] ?? ($company['municipio'] ?? '')),
        (string) ($endObj['uf'] ?? ($company['uf'] ?? '')),
    ])));

    $drawKvRows([
        ['CNPJ', $cnpjFmt],
        ['Razao Social', (string) ($company['razao_social'] ?? $company['legal_name'] ?? '—')],
        ['Nome Fantasia', (string) ($company['nome_fantasia'] ?? $company['trade_name'] ?? '—')],
        ['Situacao Cadastral', (string) ($company['situacao_cadastral'] ?? $company['status'] ?? '—')],
        ['Data de Abertura', (string) ($company['data_abertura'] ?? $company['opening_date'] ?? '—')],
        ['Capital Social', $capSocial],
        ['CNAE Principal', $cnaeTxt !== '' ? $cnaeTxt : '—'],
        ['Localizacao / Endereco', $addrTxt !== '' ? $addrTxt : '—'],
    ]);

    // Seção 2: Regime Tributário
    $drawSectionTitle('2. Enquadramento Tributario (Simples Nacional e MEI)');
    $optSimples = $tax['opcao_pelo_simples'] ?? $tax['optante_simples'] ?? null;
    $optMei = $tax['opcao_pelo_mei'] ?? $tax['optante_mei'] ?? null;
    $simplesTxt = ($optSimples === true) ? 'Sim (Optante)' : (($optSimples === false) ? 'Nao' : 'Nao informado');
    $meiTxt = ($optMei === true) ? 'Sim (SIMEI / MEI)' : (($optMei === false) ? 'Nao' : 'Nao informado');
    $taxRows = [
        ['Simples Nacional', $simplesTxt],
        ['MEI (SIMEI)', $meiTxt],
    ];
    if (!empty($tax['regime_resumido'])) {
        $taxRows[] = ['Regime Resumido', (string) $tax['regime_resumido']];
    }
    if (!empty($tax['data_opcao_simples'])) {
        $taxRows[] = ['Data Opcao Simples', (string) $tax['data_opcao_simples']];
    }
    $drawKvRows($taxRows);

    // Seção 3: Sanções CGU
    $drawSectionTitle('3. Sancoes Administrativas e Idoneidade (CGU CEIS / CNEP)');
    $hasSanctions = !empty($sanctions['has_sanctions']);
    $sanctionRecords = is_array($sanctions['records'] ?? null)
        ? $sanctions['records']
        : (is_array($sanctions['sanctions'] ?? null) ? $sanctions['sanctions'] : []);
    $totalSanctions = (int) ($sanctions['total_sanctions'] ?? count($sanctionRecords));
    $drawKvRows([
        ['Status CEIS / CNEP', $hasSanctions ? 'CONSTA SANCAO ATIVA (Atencao)' : 'NADA CONSTA (Sem sancoes ativas)'],
        ['Total de Ocorrencias', (string) $totalSanctions],
        ['Bases Auditadas', 'CGU CEIS, CGU CNEP e Acordos de Leniencia (Dados Abertos LAI)'],
    ]);
    if (!empty($sanctionRecords)) {
        foreach (array_slice($sanctionRecords, 0, 5) as $idxS => $recS) {
            $summaryRec = is_array($recS) ? (string) json_encode($recS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $recS;
            foreach (grid402_pdf_wrap_lines('Ocorrencia #' . ($idxS + 1) . ': ' . $summaryRec, 84) as $rLn) {
                $ensureSpace(14.0);
                $drawText(42.0, $y, $rLn, 'F3', 7.8, [0.60, 0.11, 0.11]);
                $y -= 11.5;
            }
        }
        $y -= 6.0;
    }

    // Alertas do Veredito (se existirem)
    $alertsList = is_array($verdict['alerts'] ?? null) ? $verdict['alerts'] : [];
    if (!empty($alertsList)) {
        $drawSectionTitle('4. Alertas e Pontos de Atencao Detectados');
        foreach (array_slice($alertsList, 0, 8) as $al) {
            if (!is_array($al)) {
                continue;
            }
            $sev = strtoupper((string) ($al['severity'] ?? 'WARNING'));
            $msg = (string) ($al['message'] ?? '');
            foreach (grid402_pdf_wrap_lines("[{$sev}] {$msg}", 84) as $aLn) {
                $ensureSpace(14.0);
                $drawText(42.0, $y, $aLn, 'F1', 8.5, $sev === 'CRITICAL' ? [0.60, 0.11, 0.11] : [0.57, 0.25, 0.05]);
                $y -= 12.0;
            }
        }
        $y -= 4.0;
    }

    // Bloco final: Prova Criptográfica ECDSA + QR Code Vetorial
    $ensureSpace(122.0);
    $proofTitleNum = !empty($alertsList) ? '5' : '4';
    $drawSectionTitle("{$proofTitleNum}. Prova Criptografica de Oraculo (_oracle_proof)");

    $oracleAddr = (string) ($proof['oracle_address'] ?? (defined('GRID402_ORACLE_WALLET') ? GRID402_ORACLE_WALLET : '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'));
    $dataHash = (string) ($proof['data_hash'] ?? '—');
    $sigHex = (string) ($proof['signature'] ?? '—');
    $algo = (string) ($proof['algorithm'] ?? 'secp256k1-sha256');
    $sourceAuth = (string) ($meta['source_authority'] ?? 'Receita Federal + Simples/MEI + CGU CEIS/CNEP');

    $boxTop = $y;
    $boxH = 86.0;
    $drawRect(36.0, $boxTop, 523.28, $boxH, [0.973, 0.980, 0.988], [0.796, 0.835, 0.882], 0.7);

    $drawText(46.0, $boxTop - 15.0, 'Oracle Address : ' . $oracleAddr, 'F3', 7.6, [0.10, 0.14, 0.22]);
    $drawText(46.0, $boxTop - 27.0, 'Algorithm      : ' . $algo . ($isSample ? ' (AMOSTRA)' : ' (ECDSA VERIFIED)'), 'F3', 7.6, [0.10, 0.14, 0.22]);
    $drawText(46.0, $boxTop - 39.0, 'Data Hash      : ' . substr($dataHash, 0, 56), 'F3', 7.4, [0.10, 0.14, 0.22]);
    $drawText(46.0, $boxTop - 51.0, 'Signature Hex  : ' . substr($sigHex, 0, 48) . (strlen($sigHex) > 48 ? '...' : ''), 'F3', 7.4, [0.10, 0.14, 0.22]);
    $drawText(46.0, $boxTop - 63.0, 'Fetched UTC    : ' . $fetchedIso, 'F3', 7.4, [0.25, 0.30, 0.38]);
    $drawText(46.0, $boxTop - 75.0, 'Fontes         : ' . substr($sourceAuth, 0, 58), 'F3', 7.2, [0.25, 0.30, 0.38]);

    // QR Code vetorial de verificação no canto direito do card de prova
    $verifyHashShort = preg_replace('/[^a-f0-9]/i', '', str_replace('0x', '', $dataHash));
    $qrTargetUrl = $isSample
        ? 'https://grid402.online/dossie-kyb'
        : ('https://grid402.online/trust?verify=' . substr($verifyHashShort !== '' ? $verifyHashShort : 'grid402', 0, 16));
    $qrMatrix = grid402_qr_encode_matrix($qrTargetUrl);
    $qrN = count($qrMatrix);
    $qrRenderSize = 68.0;
    $qrCell = $qrRenderSize / max(1, $qrN);
    $qrLeft = 474.0;
    $qrTop = $boxTop - 9.0;

    $drawRect($qrLeft - 3.0, $qrTop + 3.0, $qrRenderSize + 6.0, $qrRenderSize + 6.0, [1.0, 1.0, 1.0], [0.82, 0.86, 0.91], 0.5);
    $appendCmd('q 0.043 0.075 0.169 rg');
    for ($r = 0; $r < $qrN; $r++) {
        for ($c = 0; $c < $qrN; $c++) {
            if (($qrMatrix[$r][$c] ?? 0) === 1) {
                $cellX = $qrLeft + ($c * $qrCell);
                $cellY = $qrTop - (($r + 1) * $qrCell);
                $appendCmd(sprintf('%.2F %.2F %.2F %.2F re f', $cellX, $cellY, $qrCell + 0.04, $qrCell + 0.04));
            }
        }
    }
    $appendCmd('Q');

    $drawText(36.0, 26.0, 'Grid402 Network Intelligence (https://grid402.online) · LGPD Art. 1 / LAI 12.527/2011 · Pagina ' . ($pageIdx + 1) . ' de ' . count($pages), 'F1', 7.5, [0.45, 0.50, 0.60]);

    // Monta objetos binários PDF 1.4 em memória
    $objects = [];
    $addObj = static function (string $body) use (&$objects): int {
        $id = count($objects) + 1;
        $objects[$id] = $body;
        return $id;
    };

    $catalogId = $addObj(''); // 1
    $pagesId = $addObj('');   // 2
    $f1Id = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $f2Id = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
    $f3Id = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>');

    $pageObjIds = [];
    foreach ($pages as $streamContent) {
        $contentId = $addObj("<< /Length " . strlen($streamContent) . " >>\nstream\n" . $streamContent . "endstream");
        $pageId = $addObj("<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 {$f1Id} 0 R /F2 {$f2Id} 0 R /F3 {$f3Id} 0 R >> >> /Contents {$contentId} 0 R >>");
        $pageObjIds[] = $pageId;
    }

    $kidsRefs = implode(' ', array_map(static fn(int $id): string => "{$id} 0 R", $pageObjIds));
    $objects[$catalogId] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
    $objects[$pagesId] = "<< /Type /Pages /Kids [{$kidsRefs}] /Count " . count($pageObjIds) . " >>";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0 => 0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $totalObjs = count($objects) + 1;
    $pdf .= "xref\n0 {$totalObjs}\n";
    $pdf .= "0000000000 65535 f \n";
    for ($id = 1; $id <= count($objects); $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= "trailer\n<< /Size {$totalObjs} /Root {$catalogId} 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";

    return $pdf;
}
