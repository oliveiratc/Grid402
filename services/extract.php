<?php
/**
 * Grid402 - Micro-Serviço: Extrator Web para Markdown Limpo (/api/v1/extract)
 *
 * Baixa páginas públicas HTTP/HTTPS com proteção rigorosa contra SSRF
 * e devolve conteúdo estruturado em Markdown pronto para LLMs e agentes RAG.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

const GRID402_EXTRACT_MAX_BYTES = 1048576; // 1 MB
const GRID402_EXTRACT_TIMEOUT_SEC = 5;
const GRID402_EXTRACT_MAX_REDIRECTS = 3;
const GRID402_EXTRACT_CACHE_TTL = 600;

/**
 * Verifica se um endereço IPv4 ou IPv6 é estritamente público (não privado, reservado, loopback ou metadata).
 */
function grid402_extract_is_public_ip(string $ip): bool {
    $clean = trim($ip);
    if ($clean === '') {
        return false;
    }
    return filter_var($clean, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * Valida URL e resolve DNS previamente para bloquear SSRF (loopback, LAN, cloud metadata, esquemas perigosos).
 *
 * @param string $url
 * @param callable|null $dnsResolver Opcional em testes: fn(string $host): array<int, string>
 * @return array<string, mixed>
 */
function grid402_extract_validate_public_url(string $url, ?callable $dnsResolver = null): array {
    $raw = trim($url);
    if ($raw === '' || strlen($raw) > 2048) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidUrlParameter',
            'message' => 'Informe uma URL publica HTTP ou HTTPS valida no parametro "url".',
        ];
    }

    $parts = @parse_url($raw);
    if (!is_array($parts)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidUrlParameter',
            'message' => 'URL malformada.',
        ];
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidUrlParameter',
            'message' => 'Apenas esquemas http:// e https:// sao permitidos.',
        ];
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'BlockedTargetAddress',
            'message' => 'Credenciais embutidas na URL nao sao permitidas.',
        ];
    }

    $host = strtolower(trim((string) ($parts['host'] ?? ''), " \t\n\r\0\x0B[]"));
    if ($host === '' || str_contains($host, ' ') || !preg_match('/^[a-z0-9.:-]+$/', $host)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidUrlParameter',
            'message' => 'Hostname invalido na URL informada.',
        ];
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    if (!in_array($port, [80, 443], true)) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'BlockedTargetAddress',
            'message' => 'Apenas portas web padrao (80 e 443) sao permitidas.',
        ];
    }

    // Bloqueio explicito de hosts locais/internos
    if (
        $host === 'localhost'
        || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.internal')
        || $host === 'metadata.google.internal'
    ) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'BlockedTargetAddress',
            'message' => 'Acesso a enderecos locais ou internos bloqueado por protecao SSRF.',
        ];
    }

    // Se o host ja e um IP literal
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        if (!grid402_extract_is_public_ip($host)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'BlockedTargetAddress',
                'message' => 'IP privado, loopback ou reservado bloqueado por protecao SSRF.',
            ];
        }
        return [
            'ok' => true,
            'url' => $raw,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ip' => $host,
        ];
    }

    // Resolucao DNS previa (A + AAAA)
    $ips = [];
    if ($dnsResolver !== null) {
        $resolved = $dnsResolver($host);
        if (is_array($resolved)) {
            $ips = array_values(array_filter(array_map('strval', $resolved)));
        }
    } else {
        $recsA = @dns_get_record($host, DNS_A);
        if (is_array($recsA)) {
            foreach ($recsA as $r) {
                if (!empty($r['ip'])) {
                    $ips[] = (string) $r['ip'];
                }
            }
        }
        $recsAAAA = @dns_get_record($host, DNS_AAAA);
        if (is_array($recsAAAA)) {
            foreach ($recsAAAA as $r) {
                if (!empty($r['ipv6'])) {
                    $ips[] = (string) $r['ipv6'];
                }
            }
        }
        if (empty($ips)) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }
    }

    $ips = array_values(array_unique($ips));
    if (empty($ips)) {
        return [
            'ok' => false,
            'code' => 422,
            'error' => 'DnsResolutionFailed',
            'message' => 'Nao foi possivel resolver o dominio informado via DNS.',
        ];
    }

    // Todo IP retornado pelo DNS deve ser publico (bloqueia split-horizon / rebinding)
    foreach ($ips as $resolvedIp) {
        if (!grid402_extract_is_public_ip($resolvedIp)) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'BlockedTargetAddress',
                'message' => 'O dominio resolveu para IP privado ou reservado (bloqueio SSRF).',
            ];
        }
    }

    // Prefere IPv4 para CURLOPT_RESOLVE quando disponivel
    $chosenIp = $ips[0];
    foreach ($ips as $candidateIp) {
        if (filter_var($candidateIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $chosenIp = $candidateIp;
            break;
        }
    }

    return [
        'ok' => true,
        'url' => $raw,
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
        'ip' => $chosenIp,
    ];
}

/**
 * Resolve link relativo para URL absoluta com base na URL final da pagina.
 */
function grid402_extract_resolve_url(string $href, string $baseUrl): ?string {
    $href = trim($href);
    if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:') || str_starts_with(strtolower($href), 'mailto:')) {
        return null;
    }
    if (preg_match('#^https?://#i', $href) === 1) {
        return $href;
    }
    $base = @parse_url($baseUrl);
    if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
        return null;
    }
    $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    if (str_starts_with($href, '//')) {
        return $base['scheme'] . ':' . $href;
    }
    if (str_starts_with($href, '/')) {
        return $origin . $href;
    }
    $basePath = (string) ($base['path'] ?? '/');
    $dir = preg_replace('#/[^/]*$#', '/', $basePath) ?? '/';
    return $origin . $dir . $href;
}

/**
 * Converte HTML bruto em Markdown limpo, metadados e links absolutos para agentes LLM.
 */
function grid402_extract_html_to_markdown(string $html, string $finalUrl, int $maxChars = 12000): array {
    $maxChars = max(500, min(25000, $maxChars));
    $cleanHtml = preg_match('//u', $html) === 1 ? $html : utf8_encode($html);

    $title = '';
    $description = '';
    $canonical = $finalUrl;
    $lang = '';
    $links = [];
    $seenLinks = [];
    $lines = [];

    if (class_exists('DOMDocument')) {
        $prevLibxml = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $cleanHtml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prevLibxml);

        $htmlEls = $dom->getElementsByTagName('html');
        if ($htmlEls->length > 0 && $htmlEls->item(0) instanceof DOMElement) {
            $lang = trim((string) $htmlEls->item(0)->getAttribute('lang'));
        }

        $titleEls = $dom->getElementsByTagName('title');
        if ($titleEls->length > 0 && $titleEls->item(0) !== null) {
            $title = trim((string) preg_replace('/\s+/', ' ', (string) $titleEls->item(0)->textContent));
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (!($meta instanceof DOMElement)) {
                continue;
            }
            $name = strtolower(trim($meta->getAttribute('name')));
            $prop = strtolower(trim($meta->getAttribute('property')));
            $content = trim($meta->getAttribute('content'));
            if ($content === '') {
                continue;
            }
            if ($description === '' && ($name === 'description' || $prop === 'og:description')) {
                $description = (string) preg_replace('/\s+/', ' ', $content);
            }
            if ($title === '' && $prop === 'og:title') {
                $title = (string) preg_replace('/\s+/', ' ', $content);
            }
        }

        foreach ($dom->getElementsByTagName('link') as $linkEl) {
            if ($linkEl instanceof DOMElement && strtolower(trim($linkEl->getAttribute('rel'))) === 'canonical') {
                $href = trim($linkEl->getAttribute('href'));
                if ($href !== '') {
                    $resolved = grid402_extract_resolve_url($href, $finalUrl);
                    if ($resolved !== null) {
                        $canonical = $resolved;
                    }
                }
            }
        }

        // Remove ruido nao textual (scripts, estilos, navegacao e rodape)
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//script|//style|//noscript|//svg|//iframe|//canvas|//template|//nav|//footer') as $noise) {
            if ($noise->parentNode !== null) {
                $noise->parentNode->removeChild($noise);
            }
        }

        // Coleta links relevantes (ate 30)
        foreach ($xpath->query('//a[@href]') as $aNode) {
            if (!($aNode instanceof DOMElement)) {
                continue;
            }
            $resolved = grid402_extract_resolve_url($aNode->getAttribute('href'), $finalUrl);
            if ($resolved === null || isset($seenLinks[$resolved])) {
                continue;
            }
            $seenLinks[$resolved] = true;
            $label = trim((string) preg_replace('/\s+/', ' ', (string) $aNode->textContent));
            $links[] = [
                'text' => substr($label !== '' ? $label : $resolved, 0, 120),
                'url' => $resolved,
            ];
            if (count($links) >= 30) {
                break;
            }
        }

        // Prioriza <main> ou <article> quando existir; fallback para <body>
        $rootNode = null;
        foreach (['//main', '//article', '//body'] as $expr) {
            $found = $xpath->query($expr);
            if ($found !== false && $found->length > 0) {
                $rootNode = $found->item(0);
                break;
            }
        }

        if ($rootNode !== null) {
            $blockNodes = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//p|.//li|.//blockquote|.//pre', $rootNode);
            if ($blockNodes !== false && $blockNodes->length > 0) {
                $seenText = [];
                foreach ($blockNodes as $node) {
                    $tag = strtolower($node->nodeName);
                    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $node->textContent));
                    if ($text === '' || isset($seenText[$text])) {
                        continue;
                    }
                    $seenText[$text] = true;
                    $lines[] = match ($tag) {
                        'h1' => '# ' . $text,
                        'h2' => '## ' . $text,
                        'h3' => '### ' . $text,
                        'h4' => '#### ' . $text,
                        'li' => '- ' . $text,
                        'blockquote' => '> ' . $text,
                        'pre' => "```\n" . trim((string) $node->textContent) . "\n```",
                        default => $text,
                    };
                }
            } else {
                $fallbackText = trim((string) preg_replace('/\s+/u', ' ', (string) $rootNode->textContent));
                if ($fallbackText !== '') {
                    $lines[] = $fallbackText;
                }
            }
        }
    }

    $markdown = trim(implode("\n\n", $lines));
    if ($markdown === '') {
        $stripped = trim((string) preg_replace('/\s+/u', ' ', strip_tags($cleanHtml)));
        $markdown = $stripped;
    }

    $truncated = false;
    if (strlen($markdown) > $maxChars) {
        $markdown = substr($markdown, 0, $maxChars) . "\n\n...[truncated]";
        $truncated = true;
    }

    $words = preg_split('/\s+/u', trim(strip_tags($markdown)), -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = is_array($words) ? count($words) : 0;

    return [
        'title' => $title,
        'description' => $description,
        'canonical_url' => $canonical,
        'language' => $lang !== '' ? $lang : null,
        'word_count' => $wordCount,
        'char_count' => strlen($markdown),
        'truncated' => $truncated,
        'markdown' => $markdown,
        'links_count' => count($links),
        'links' => $links,
    ];
}

/**
 * Executa fetch HTTP seguro com redirecionamento manual validado contra SSRF e limite de 1 MB.
 */
function grid402_extract_fetch_url(string $url, ?callable $dnsResolver = null): array {
    $currentUrl = $url;
    for ($hop = 0; $hop <= GRID402_EXTRACT_MAX_REDIRECTS; $hop++) {
        $check = grid402_extract_validate_public_url($currentUrl, $dnsResolver);
        if (!$check['ok']) {
            return $check;
        }

        $host = (string) $check['host'];
        $port = (int) $check['port'];
        $ip = (string) $check['ip'];
        $resolveEntry = (str_contains($ip, ':')) ? "{$host}:{$port}:[{$ip}]" : "{$host}:{$port}:{$ip}";

        $body = '';
        $overflow = false;
        $location = '';
        $contentType = '';

        $ch = curl_init($currentUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => GRID402_EXTRACT_TIMEOUT_SEC,
            CURLOPT_USERAGENT => 'Grid402-Extract-Bot/1.0 (+https://grid402.online/api/v1/extract)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain,application/json;q=0.8'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_RESOLVE => [$resolveEntry],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$location, &$contentType): int {
                $len = strlen($headerLine);
                if (stripos($headerLine, 'location:') === 0) {
                    $location = trim(substr($headerLine, 9));
                } elseif (stripos($headerLine, 'content-type:') === 0) {
                    $contentType = trim(substr($headerLine, 13));
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$overflow): int {
                $len = strlen($chunk);
                if (strlen($body) + $len > GRID402_EXTRACT_MAX_BYTES) {
                    $allowed = GRID402_EXTRACT_MAX_BYTES - strlen($body);
                    if ($allowed > 0) {
                        $body .= substr($chunk, 0, $allowed);
                    }
                    $overflow = true;
                    return 0; // Interrompe download imediatamente
                }
                $body .= $chunk;
                return $len;
            },
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if (in_array($httpCode, [301, 302, 303, 307, 308], true) && $location !== '') {
            $nextUrl = grid402_extract_resolve_url($location, $currentUrl);
            if ($nextUrl === null) {
                return [
                    'ok' => false,
                    'code' => 502,
                    'error' => 'InvalidRedirectTarget',
                    'message' => 'Redirecionamento invalido retornado pelo servidor de destino.',
                ];
            }
            $currentUrl = $nextUrl;
            continue;
        }

        if ($httpCode === 0 && !$overflow) {
            return [
                'ok' => false,
                'code' => 502,
                'error' => 'UpstreamFetchFailed',
                'message' => 'Nao foi possivel conectar a URL de destino: ' . substr($curlErr, 0, 120),
            ];
        }

        return [
            'ok' => true,
            'final_url' => $currentUrl,
            'http_status' => $httpCode,
            'content_type' => $contentType,
            'body' => $body,
            'bytes_downloaded' => strlen($body),
            'size_capped' => $overflow,
        ];
    }

    return [
        'ok' => false,
        'code' => 502,
        'error' => 'TooManyRedirects',
        'message' => 'Numero maximo de redirecionamentos (' . GRID402_EXTRACT_MAX_REDIRECTS . ') excedido.',
    ];
}

/**
 * Amostra gratuita (teaser) exibida no desafio HTTP 402.
 */
function grid402_teaser_extract(string $url): array {
    $clean = trim($url) !== '' ? trim($url) : 'https://example.com';
    $host = (string) (parse_url($clean, PHP_URL_HOST) ?: 'example.com');
    return [
        'preview' => [
            'target_url' => $clean,
            'host' => strtolower($host),
            'output_format' => 'LLM-ready Clean Markdown + Metadata + Links',
            'ssrf_protection' => 'Strict DNS pre-resolution + 1 MB cap + max 3 redirects',
        ],
        'locked_fields' => ['title', 'description', 'markdown', 'word_count', 'links', '_oracle_proof'],
    ];
}

/**
 * Servico principal: GET /api/v1/extract?url=...
 */
function grid402_service_extract(string $url, int $maxChars = 12000, ?callable $httpFetcher = null, ?callable $dnsResolver = null): array {
    $check = grid402_extract_validate_public_url($url, $dnsResolver);
    if (!$check['ok']) {
        return [
            'success' => false,
            'status' => 'error',
            'service' => 'extract',
            'error' => $check['error'],
            'message' => $check['message'],
            'code' => $check['code'],
        ];
    }

    $normalizedUrl = (string) $check['url'];
    $cacheKey = hash('sha256', $normalizedUrl . '|' . $maxChars);
    $cacheFile = GRID402_PRIVATE_DIR . '/extract_' . substr($cacheKey, 0, 20) . '.json';

    if ($httpFetcher === null && is_file($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $cached = $rawCache ? @json_decode($rawCache, true) : null;
        if (is_array($cached) && isset($cached['_cached_at']) && (time() - (int) $cached['_cached_at']) < GRID402_EXTRACT_CACHE_TTL) {
            unset($cached['_cached_at']);
            $cached['cached'] = true;
            return $cached;
        }
    }

    $fetched = ($httpFetcher !== null)
        ? $httpFetcher($normalizedUrl)
        : grid402_extract_fetch_url($normalizedUrl, $dnsResolver);

    if (empty($fetched['ok'])) {
        return [
            'success' => false,
            'status' => 'error',
            'service' => 'extract',
            'error' => (string) ($fetched['error'] ?? 'UpstreamFetchFailed'),
            'message' => (string) ($fetched['message'] ?? 'Falha ao baixar a URL solicitada.'),
            'code' => (int) ($fetched['code'] ?? 502),
        ];
    }

    $finalUrl = (string) ($fetched['final_url'] ?? $normalizedUrl);
    $contentType = (string) ($fetched['content_type'] ?? 'text/html');
    $rawBody = (string) ($fetched['body'] ?? '');

    if (stripos($contentType, 'text/plain') !== false || stripos($contentType, 'application/json') !== false) {
        $trimmed = strlen($rawBody) > $maxChars ? (substr($rawBody, 0, $maxChars) . "\n\n...[truncated]") : $rawBody;
        $words = preg_split('/\s+/u', trim($trimmed), -1, PREG_SPLIT_NO_EMPTY);
        $parsed = [
            'title' => (string) (parse_url($finalUrl, PHP_URL_HOST) ?: ''),
            'description' => '',
            'canonical_url' => $finalUrl,
            'language' => null,
            'word_count' => is_array($words) ? count($words) : 0,
            'char_count' => strlen($trimmed),
            'truncated' => strlen($rawBody) > $maxChars,
            'markdown' => $trimmed,
            'links_count' => 0,
            'links' => [],
        ];
    } else {
        $parsed = grid402_extract_html_to_markdown($rawBody, $finalUrl, $maxChars);
    }

    $response = [
        'success' => true,
        'status' => 'success',
        'service' => 'extract',
        'url' => $normalizedUrl,
        'final_url' => $finalUrl,
        'http_status' => (int) ($fetched['http_status'] ?? 200),
        'content_type' => $contentType,
        'bytes_downloaded' => (int) ($fetched['bytes_downloaded'] ?? strlen($rawBody)),
        'title' => $parsed['title'],
        'description' => $parsed['description'],
        'canonical_url' => $parsed['canonical_url'],
        'language' => $parsed['language'],
        'word_count' => $parsed['word_count'],
        'char_count' => $parsed['char_count'],
        'truncated' => $parsed['truncated'],
        'markdown' => $parsed['markdown'],
        'links_count' => $parsed['links_count'],
        'links' => $parsed['links'],
        'tariff_usdc' => GRID402_PRICE_DISPLAY,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'cached' => false,
    ];

    if ($httpFetcher === null && function_exists('grid402_atomic_write')) {
        $toStore = $response;
        $toStore['_cached_at'] = time();
        grid402_atomic_write($cacheFile, $toStore);
    }

    return $response;
}
