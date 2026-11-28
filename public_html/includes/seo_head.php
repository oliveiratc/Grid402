<?php
/**
 * Grid402 // Helper centralizado de Favicon, Web Manifest, Open Graph e Twitter Cards
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    exit('Acesso direto proibido.');
}

/**
 * Renderiza tags de favicon, theme-color, manifest e pré-visualização social (OG/Twitter).
 *
 * @param array{
 *   title?: string,
 *   description?: string,
 *   path?: string,
 *   locale?: string,
 *   card?: string,
 *   image_alt?: string,
 *   emit_canonical?: bool,
 *   emit_description?: bool,
 *   private?: bool
 * } $opts
 */
function grid402_render_seo_head(array $opts = []): void
{
    $ver = 'v=3';
    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    echo "    <meta name=\"theme-color\" content=\"#060B19\">\n";
    echo "    <link rel=\"icon\" href=\"/favicon.ico?{$ver}\" sizes=\"any\">\n";
    echo "    <link rel=\"icon\" type=\"image/svg+xml\" href=\"/favicon.svg?{$ver}\">\n";
    echo "    <link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"/assets/favicon-32x32.png?{$ver}\">\n";
    echo "    <link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"/assets/favicon-16x16.png?{$ver}\">\n";
    echo "    <link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"/assets/apple-touch-icon.png?{$ver}\">\n";
    echo "    <link rel=\"manifest\" href=\"/site.webmanifest?{$ver}\">\n";

    if (!empty($opts['private'])) {
        echo "    <meta name=\"robots\" content=\"noindex, nofollow\">\n";
        return;
    }

    $baseUrl = defined('GRID402_CANONICAL_DOMAIN')
        ? rtrim((string) GRID402_CANONICAL_DOMAIN, '/')
        : 'https://grid402.online';

    $rawPath = trim((string) ($opts['path'] ?? '/'));
    $path = ($rawPath === '' || $rawPath === '/') ? '/' : ('/' . ltrim($rawPath, '/'));
    $canonicalUrl = $baseUrl . $path;

    $cardKey = strtolower(trim((string) ($opts['card'] ?? 'global')));
    $isKybCard = in_array($cardKey, ['dossie-kyb', 'kyb', 'pt-br'], true);
    $imagePath = $isKybCard
        ? "/assets/og-card-dossie-kyb.png?{$ver}"
        : "/assets/og-card-global.png?{$ver}";
    $imageUrl = $baseUrl . $imagePath;

    $title = trim((string) ($opts['title'] ?? 'Grid402 - Global M2M Data & Network Utilities Oracle'));
    $description = trim((string) ($opts['description'] ?? '28 M2M public data and network utility APIs for AI agents and automated pipelines settled via HTTP x402 v2, MCP 2.1, and Session Pass.'));
    $locale = trim((string) ($opts['locale'] ?? ($isKybCard ? 'pt_BR' : 'en_US')));
    $imageAlt = trim((string) ($opts['image_alt'] ?? (
        $isKybCard
            ? 'Grid402 - Dossiê 360º de CNPJ com Veredito de Risco via Pix a partir de R$ 4,90'
            : 'Grid402 - 28 M2M APIs for AI Agents & Automated Pipelines via HTTP x402 v2 & MCP'
    )));

    if (!empty($opts['emit_description'])) {
        echo "    <meta name=\"description\" content=\"" . $esc($description) . "\">\n";
    }
    if (!empty($opts['emit_canonical'])) {
        echo "    <link rel=\"canonical\" href=\"" . $esc($canonicalUrl) . "\">\n";
    }

    echo "    <meta property=\"og:type\" content=\"website\">\n";
    echo "    <meta property=\"og:site_name\" content=\"Grid402\">\n";
    echo "    <meta property=\"og:locale\" content=\"" . $esc($locale) . "\">\n";
    echo "    <meta property=\"og:title\" content=\"" . $esc($title) . "\">\n";
    echo "    <meta property=\"og:description\" content=\"" . $esc($description) . "\">\n";
    echo "    <meta property=\"og:url\" content=\"" . $esc($canonicalUrl) . "\">\n";
    echo "    <meta property=\"og:image\" content=\"" . $esc($imageUrl) . "\">\n";
    echo "    <meta property=\"og:image:secure_url\" content=\"" . $esc($imageUrl) . "\">\n";
    echo "    <meta property=\"og:image:type\" content=\"image/png\">\n";
    echo "    <meta property=\"og:image:width\" content=\"1200\">\n";
    echo "    <meta property=\"og:image:height\" content=\"630\">\n";
    echo "    <meta property=\"og:image:alt\" content=\"" . $esc($imageAlt) . "\">\n";
    echo "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "    <meta name=\"twitter:title\" content=\"" . $esc($title) . "\">\n";
    echo "    <meta name=\"twitter:description\" content=\"" . $esc($description) . "\">\n";
    echo "    <meta name=\"twitter:image\" content=\"" . $esc($imageUrl) . "\">\n";
    echo "    <meta name=\"twitter:image:alt\" content=\"" . $esc($imageAlt) . "\">\n";
}
