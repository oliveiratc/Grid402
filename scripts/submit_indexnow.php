<?php
/**
 * Submete todas as URLs de public_html/sitemap.xml para a API oficial do IndexNow
 * (Bing, DuckDuckGo, Yandex, Seznam, Naver).
 *
 * Uso: php scripts/submit_indexnow.php
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$sitemapFile = $projectRoot . '/public_html/sitemap.xml';
$host = 'grid402.online';
$key = 'g402indexnow8453a9b2c7e1f4d60318';
$keyLocation = "https://{$host}/{$key}.txt";

if (!is_file($sitemapFile)) {
    fwrite(STDERR, "ERRO: sitemap.xml não encontrado em {$sitemapFile}\n");
    exit(1);
}

$xml = @simplexml_load_file($sitemapFile);
if ($xml === false) {
    fwrite(STDERR, "ERRO: sitemap.xml inválido.\n");
    exit(1);
}

$urls = [];
foreach ($xml->url as $urlNode) {
    $loc = trim((string) ($urlNode->loc ?? ''));
    if ($loc !== '' && str_starts_with($loc, "https://{$host}/")) {
        $urls[] = $loc;
    }
}
$urls = array_values(array_unique($urls));

if (empty($urls)) {
    fwrite(STDERR, "ERRO: Nenhuma URL encontrada no sitemap.xml.\n");
    exit(1);
}

// 1. Confirma que a chave está acessível em produção antes de notificar o IndexNow
$chKey = curl_init($keyLocation);
curl_setopt_array($chKey, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$keyBody = trim((string) curl_exec($chKey));
$keyHttp = (int) curl_getinfo($chKey, CURLINFO_HTTP_CODE);
$keyErr = curl_error($chKey);
curl_close($chKey);

if ($keyHttp !== 200 || $keyBody !== $key) {
    fwrite(STDERR, "ERRO: Chave IndexNow em {$keyLocation} retornou HTTP {$keyHttp} ({$keyErr}) | body='" . substr($keyBody, 0, 80) . "'\n");
    exit(1);
}
echo "[OK] Chave verificada em {$keyLocation} (HTTP 200).\n";

// 2. Submete lote completo para https://api.indexnow.org/indexnow
$payload = json_encode([
    'host' => $host,
    'key' => $key,
    'keyLocation' => $keyLocation,
    'urlList' => $urls,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.indexnow.org/indexnow');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$resp = (string) curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "IndexNow HTTP {$httpCode} | URLs enviadas: " . count($urls) . "\n";
if (trim($resp) !== '') {
    echo "Resposta: " . trim($resp) . "\n";
}
if ($curlErr !== '') {
    echo "cURL error: {$curlErr}\n";
}

if ($httpCode !== 200 && $httpCode !== 202) {
    exit(1);
}
echo "[OK] Lote IndexNow aceito com sucesso.\n";
exit(0);
