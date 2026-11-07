<?php
/**
 * Testes unitários para o gerador de badges SVG (badge.php)
 */

declare(strict_types=1);

echo "=== Testando Gerador de Badges SVG ===\n";

// Caso 1: Badge de alvo específico (sefaz_sp)
$_GET = ['target' => 'sefaz_sp'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgSefaz = ob_get_clean();

assert(str_contains($svgSefaz, '<svg'), "Falha: SVG do SEFAZ não contém tag <svg");
assert(str_contains($svgSefaz, 'SEFAZ São Paulo'), "Falha: SVG do SEFAZ não contém nome do alvo");
assert(str_contains($svgSefaz, '#10b981') || str_contains($svgSefaz, '#f59e0b') || str_contains($svgSefaz, '#ef4444'), "Falha: cor de status não encontrada");
echo "✓ Badge individual (SEFAZ SP) gerado com sucesso!\n";

// Caso 2: Badge de categoria (sefaz)
$_GET = ['target' => 'sefaz'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgCat = ob_get_clean();
assert(str_contains($svgCat, '<svg'), "Falha: SVG de categoria não contém tag <svg");
assert(str_contains($svgCat, 'SEFAZ'), "Falha: SVG de categoria não contém 'SEFAZ'");
echo "✓ Badge de categoria (SEFAZ) gerado com sucesso!\n";

// Caso 3: Badge global de sistema (grid402 / system)
$_GET = ['target' => 'system'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgSys = ob_get_clean();
assert(str_contains($svgSys, '<svg'), "Falha: SVG global não contém tag <svg");
assert(str_contains($svgSys, 'Grid402'), "Falha: SVG global não contém 'Grid402'");
echo "✓ Badge de sistema (Grid402) gerado com sucesso!\n";

echo "=== Todos os testes de Badges foram APROVADOS! ===\n";

