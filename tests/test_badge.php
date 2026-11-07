<?php
/**
 * Testes unitários para o gerador de badges SVG (badge.php)
 */

declare(strict_types=1);

echo "=== Testando Gerador de Badges SVG ===\n";

// Caso 1: Badge de Câmbio (forex / usd)
$_GET = ['target' => 'forex'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgForex = ob_get_clean();
assert(str_contains($svgForex, '<svg'), "Falha: SVG de Câmbio não contém tag <svg");
assert(str_contains($svgForex, 'USD / BRL'), "Falha: SVG de Câmbio não contém 'USD / BRL'");
echo "✓ Badge de Câmbio (USD / BRL) gerado com sucesso!\n";

// Caso 2: Badge de Cripto (crypto / btc)
$_GET = ['target' => 'crypto'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgCrypto = ob_get_clean();
assert(str_contains($svgCrypto, '<svg'), "Falha: SVG de Cripto não contém tag <svg");
assert(str_contains($svgCrypto, 'BTC / USD'), "Falha: SVG de Cripto não contém 'BTC / USD'");
echo "✓ Badge de Cripto (BTC / USD) gerado com sucesso!\n";

// Caso 3: Badge de DNS (dns)
$_GET = ['target' => 'dns'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgDns = ob_get_clean();
assert(str_contains($svgDns, '<svg'), "Falha: SVG de DNS não contém tag <svg");
assert(str_contains($svgDns, 'DNS Resolver'), "Falha: SVG de DNS não contém 'DNS Resolver'");
echo "✓ Badge de DNS gerado com sucesso!\n";

// Caso 4: Badge global de sistema (grid402 / system)
$_GET = ['target' => 'system'];
ob_start();
include __DIR__ . '/../public_html/badge.php';
$svgSys = ob_get_clean();
assert(str_contains($svgSys, '<svg'), "Falha: SVG global não contém tag <svg");
assert(str_contains($svgSys, 'Grid402 Hub'), "Falha: SVG global não contém 'Grid402 Hub'");
echo "✓ Badge de sistema (Grid402 Hub) gerado com sucesso!\n";

echo "=== Todos os testes de Badges foram APROVADOS! ===\n";
