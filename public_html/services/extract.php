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
const GRID402_EXTRACT_ROBOTS_TIMEOUT_SEC = 2;
const GRID402_EXTRACT_ROBOTS_MAX_BYTES = 262144; // 256 KB
const GRID402_EXTRACT_ROBOTS_CACHE_TTL = 3600; // Apenas diretivas robots.txt (zero conteudo de paginas)
const GRID402_EXTRACT_BOT_NAME = 'Grid402-Extract-Bot';

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
        $xRobotsTags = [];
        $contentSignals = [];

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
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$location, &$contentType, &$xRobotsTags, &$contentSignals): int {
                $len = strlen($headerLine);
                if (stripos($headerLine, 'location:') === 0) {
                    $location = trim(substr($headerLine, 9));
                } elseif (stripos($headerLine, 'content-type:') === 0) {
                    $contentType = trim(substr($headerLine, 13));
                } elseif (stripos($headerLine, 'x-robots-tag:') === 0) {
                    $val = trim(substr($headerLine, 13));
                    if ($val !== '') {
                        $xRobotsTags[] = $val;
                    }
                } elseif (stripos($headerLine, 'content-signal:') === 0) {
                    $val = trim(substr($headerLine, 15));
                    if ($val !== '') {
                        $contentSignals[] = $val;
                    }
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
            'x_robots_tag' => $xRobotsTags,
            'content_signal' => $contentSignals,
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
 * Faz parse de um robots.txt seguindo precedencia RFC 9309 para grupos User-agent.
 *
 * @return array<string, array<int, array{type: string, pattern: string}>>
 */
function grid402_extract_parse_robots_txt(string $content): array {
    $groups = [];
    $currentAgents = [];
    $sawRuleInGroup = false;

    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
    foreach ($lines as $line) {
        $commentPos = strpos($line, '#');
        if ($commentPos !== false) {
            $line = substr($line, 0, $commentPos);
        }
        $line = trim($line);
        if ($line === '' || !str_contains($line, ':')) {
            continue;
        }
        [$field, $value] = explode(':', $line, 2);
        $fieldLower = strtolower(trim($field));
        $valTrim = trim($value);

        if ($fieldLower === 'user-agent') {
            $ua = strtolower($valTrim);
            if ($sawRuleInGroup || empty($currentAgents)) {
                $currentAgents = [$ua];
                $sawRuleInGroup = false;
            } else {
                $currentAgents[] = $ua;
            }
            if (!isset($groups[$ua])) {
                $groups[$ua] = [];
            }
        } elseif ($fieldLower === 'allow' || $fieldLower === 'disallow') {
            if (empty($currentAgents)) {
                continue;
            }
            $sawRuleInGroup = true;
            if ($fieldLower === 'disallow' && $valTrim === '') {
                continue;
            }
            foreach ($currentAgents as $ua) {
                $groups[$ua][] = ['type' => $fieldLower, 'pattern' => $valTrim];
            }
        }
    }

    return $groups;
}

function grid402_extract_robots_pattern_matches(string $pattern, string $targetPath): bool {
    if ($pattern === '') {
        return false;
    }
    $endAnchored = str_ends_with($pattern, '$');
    $core = $endAnchored ? substr($pattern, 0, -1) : $pattern;
    $parts = explode('*', $core);
    $escaped = array_map(static fn(string $p): string => preg_quote($p, '#'), $parts);
    $regex = '#^' . implode('.*', $escaped) . ($endAnchored ? '$' : '') . '#';
    return preg_match($regex, $targetPath) === 1;
}

/**
 * Avalia regras parsed de robots.txt para um path (RFC 9309: grupo especifico > *, longest match, Allow > Disallow em empate).
 */
function grid402_extract_robots_is_allowed(array $parsedGroups, string $targetPath, string $botName = GRID402_EXTRACT_BOT_NAME): bool {
    $botLower = strtolower($botName);
    $rules = null;
    if (isset($parsedGroups[$botLower])) {
        $rules = $parsedGroups[$botLower];
    } elseif (isset($parsedGroups['*'])) {
        $rules = $parsedGroups['*'];
    }
    if (empty($rules)) {
        return true;
    }

    $bestLen = -1;
    $bestType = 'allow';
    foreach ($rules as $rule) {
        $pat = (string) ($rule['pattern'] ?? '');
        $type = (string) ($rule['type'] ?? 'allow');
        if (!grid402_extract_robots_pattern_matches($pat, $targetPath)) {
            continue;
        }
        $len = strlen($pat);
        if ($len > $bestLen || ($len === $bestLen && $type === 'allow')) {
            $bestLen = $len;
            $bestType = $type;
        }
    }

    return $bestType !== 'disallow';
}

/**
 * Busca e valida /robots.txt da origem sob guarda anti-SSRF (cache leve exclusivo de diretivas por host).
 *
 * @return array<string, mixed>
 */
function grid402_extract_check_robots(
    string $scheme,
    string $host,
    int $port,
    string $requestPath,
    ?callable $dnsResolver = null,
    ?callable $robotsFetcher = null
): array {
    $defaultPort = ($scheme === 'https') ? 443 : 80;
    $portSuffix = ($port !== $defaultPort) ? (':' . $port) : '';
    $robotsUrl = "{$scheme}://{$host}{$portSuffix}/robots.txt";

    $urlCheck = grid402_extract_validate_public_url($robotsUrl, $dnsResolver);
    if (empty($urlCheck['ok'])) {
        return [
            'allowed' => false,
            'code' => (int) ($urlCheck['code'] ?? 400),
            'error' => (string) ($urlCheck['error'] ?? 'BlockedTargetAddress'),
            'message' => (string) ($urlCheck['message'] ?? 'Origem invalida para consulta de robots.txt.'),
        ];
    }

    $cacheKey = hash('sha256', strtolower("{$scheme}://{$host}:{$port}"));
    $cacheFile = GRID402_PRIVATE_DIR . '/robots_cache_' . substr($cacheKey, 0, 20) . '.json';
    $cachedEntry = null;

    if ($robotsFetcher === null && is_file($cacheFile) && is_readable($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $decodedCache = is_string($rawCache) ? @json_decode($rawCache, true) : null;
        if (is_array($decodedCache) && isset($decodedCache['cached_at']) && (time() - (int) $decodedCache['cached_at']) < GRID402_EXTRACT_ROBOTS_CACHE_TTL) {
            $cachedEntry = $decodedCache;
        }
    }

    if ($cachedEntry !== null) {
        $robotsStatus = (int) ($cachedEntry['http_status'] ?? 200);
        $groups = is_array($cachedEntry['groups'] ?? null) ? $cachedEntry['groups'] : [];
    } else {
        if ($robotsFetcher !== null) {
            $fetchedRobots = $robotsFetcher($robotsUrl);
            $robotsStatus = (int) ($fetchedRobots['http_status'] ?? 200);
            $robotsBody = (string) ($fetchedRobots['body'] ?? '');
            $fetchOk = !isset($fetchedRobots['ok']) || !empty($fetchedRobots['ok']);
        } else {
            $ip = (string) $urlCheck['ip'];
            $resolveEntry = (str_contains($ip, ':')) ? "{$host}:{$port}:[{$ip}]" : "{$host}:{$port}:{$ip}";
            $robotsBody = '';
            $robotsOverflow = false;
            $ch = curl_init($robotsUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => GRID402_EXTRACT_ROBOTS_TIMEOUT_SEC,
                CURLOPT_USERAGENT => 'Grid402-Extract-Bot/1.0 (+https://grid402.online/api/v1/extract)',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_RESOLVE => [$resolveEntry],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$robotsBody, &$robotsOverflow): int {
                    $len = strlen($chunk);
                    if (strlen($robotsBody) + $len > GRID402_EXTRACT_ROBOTS_MAX_BYTES) {
                        $robotsOverflow = true;
                        return 0;
                    }
                    $robotsBody .= $chunk;
                    return $len;
                },
            ]);
            curl_exec($ch);
            $robotsStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $fetchOk = ($robotsStatus > 0) || $robotsOverflow;
        }

        if (!$fetchOk || $robotsStatus === 429 || $robotsStatus >= 500) {
            return [
                'allowed' => false,
                'code' => 503,
                'error' => 'RobotsUnavailable',
                'message' => 'Nao foi possivel verificar robots.txt do host de destino com seguranca (HTTP ' . $robotsStatus . ').',
            ];
        }

        $groups = ($robotsStatus === 200 && trim($robotsBody) !== '')
            ? grid402_extract_parse_robots_txt($robotsBody)
            : [];

        if ($robotsFetcher === null && function_exists('grid402_atomic_write')) {
            grid402_atomic_write($cacheFile, [
                'cached_at' => time(),
                'http_status' => $robotsStatus,
                'groups' => $groups,
            ]);
        }
    }

    if (in_array($robotsStatus, [404, 410], true)) {
        return ['allowed' => true, 'robots_status' => $robotsStatus];
    }
    if (in_array($robotsStatus, [401, 403], true)) {
        return [
            'allowed' => false,
            'code' => 403,
            'error' => 'RobotsDisallowed',
            'message' => 'Acesso bloqueado pelas diretivas de robots.txt do host (HTTP ' . $robotsStatus . ').',
        ];
    }

    if (!grid402_extract_robots_is_allowed($groups, $requestPath, GRID402_EXTRACT_BOT_NAME)) {
        return [
            'allowed' => false,
            'code' => 403,
            'error' => 'RobotsDisallowed',
            'message' => 'Extracao bloqueada pelo arquivo robots.txt do host para o caminho solicitado.',
        ];
    }

    return ['allowed' => true, 'robots_status' => $robotsStatus];
}

/**
 * Inspeciona status HTTP, cabecalhos anti-AI e HTML por diretivas restritivas ou paywall.
 *
 * @return array{code: int, error: string, message: string}|null
 */
function grid402_extract_inspect_compliance(int $httpStatus, array $xRobotsTags, array $contentSignals, string $body): ?array {
    if (in_array($httpStatus, [401, 402, 403, 407], true)) {
        return [
            'code' => 403,
            'error' => 'OriginAccessDenied',
            'message' => "A origem retornou HTTP {$httpStatus} (acesso restrito, autenticacao ou pagamento exigido).",
        ];
    }

    $blockedDirectives = ['noai', 'noimageai', 'noindex', 'none'];
    foreach ($xRobotsTags as $hdrVal) {
        $lowerHdr = strtolower((string) $hdrVal);
        // Suporta formato "botname: noai" ou "noai, nofollow"
        $applies = true;
        if (str_contains($lowerHdr, ':')) {
            [$targetBot, $rulePart] = explode(':', $lowerHdr, 2);
            $targetBot = trim($targetBot);
            $applies = ($targetBot === '*' || $targetBot === 'all' || str_contains($targetBot, 'grid402'));
            $checkStr = $rulePart;
        } else {
            $checkStr = $lowerHdr;
        }
        if ($applies) {
            foreach ($blockedDirectives as $dir) {
                if (preg_match('/\b' . $dir . '\b/', $checkStr) === 1) {
                    return [
                        'code' => 403,
                        'error' => 'DirectiveDisallowed',
                        'message' => "Extracao bloqueada pelo cabecalho X-Robots-Tag ({$dir}).",
                    ];
                }
            }
        }
    }

    foreach ($contentSignals as $sigVal) {
        $lowerSig = strtolower((string) $sigVal);
        if (preg_match('/\b(?:ai-input|ai-train)\s*=\s*no\b/', $lowerSig) === 1) {
            return [
                'code' => 403,
                'error' => 'DirectiveDisallowed',
                'message' => 'Extracao bloqueada pela diretiva HTTP Content-Signal (ai-input=no / ai-train=no).',
            ];
        }
    }

    if (trim($body) !== '' && class_exists('DOMDocument')) {
        $prevErr = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $cleanHtml = preg_match('//u', $body) === 1 ? $body : utf8_encode($body);
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $cleanHtml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (!($meta instanceof DOMElement)) {
                continue;
            }
            $name = strtolower(trim($meta->getAttribute('name')));
            $prop = strtolower(trim($meta->getAttribute('property')));
            $content = strtolower(trim($meta->getAttribute('content')));

            if (in_array($name, ['robots', 'googlebot', 'bingbot', 'gptbot', 'ccbot', 'grid402-extract-bot'], true)) {
                foreach ($blockedDirectives as $dir) {
                    if (preg_match('/\b' . $dir . '\b/', $content) === 1) {
                        return [
                            'code' => 403,
                            'error' => 'DirectiveDisallowed',
                            'message' => "Extracao bloqueada pela meta tag {$name} ({$dir}).",
                        ];
                    }
                }
            }

            if ($name === 'paywall' && in_array($content, ['true', '1', 'yes', 'locked', 'metered'], true)) {
                return [
                    'code' => 403,
                    'error' => 'PaywallDetected',
                    'message' => 'Conteudo protegido por paywall detectado na pagina de destino.',
                ];
            }
            if (($prop === 'article:content_tier' || $name === 'article:content_tier') && in_array($content, ['locked', 'metered', 'subscriber'], true)) {
                return [
                    'code' => 403,
                    'error' => 'PaywallDetected',
                    'message' => 'Conteudo protegido por assinatura/paywall detectado (content_tier=' . $content . ').',
                ];
            }
        }
    }

    // Sinais estruturais de paywall em JSON-LD Schema.org
    if (preg_match('/"isAccessibleForFree"\s*:\s*(?:false|"false"|0)\b/i', $body) === 1) {
        return [
            'code' => 403,
            'error' => 'PaywallDetected',
            'message' => 'Conteudo marcado com isAccessibleForFree=false (paywall) na pagina de destino.',
        ];
    }

    return null;
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
            'output_format' => 'Transient LLM-ready Clean Markdown + Metadata + Links',
            'policy' => '100% transient (zero disk content cache) + robots.txt / X-Robots-Tag / Content-Signal / paywall guard',
            'ssrf_protection' => 'Strict DNS pre-resolution + 1 MB cap + max 3 redirects',
        ],
        'locked_fields' => ['title', 'description', 'markdown', 'word_count', 'links', '_oracle_proof'],
    ];
}

/**
 * Servico principal: GET /api/v1/extract?url=... (100% transiente, sem gravacao de texto em disco).
 */
function grid402_service_extract(
    string $url,
    int $maxChars = 12000,
    ?callable $httpFetcher = null,
    ?callable $dnsResolver = null,
    ?callable $robotsFetcher = null
): array {
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
    $parsedUrl = @parse_url($normalizedUrl);
    $reqPath = (string) ($parsedUrl['path'] ?? '/');
    if ($reqPath === '') {
        $reqPath = '/';
    }
    if (!empty($parsedUrl['query'])) {
        $reqPath .= '?' . $parsedUrl['query'];
    }

    // Checagem obrigatoria de robots.txt (em testes legados com apenas $httpMock sem $robotsFetcher, mantem isolamento offline)
    if ($robotsFetcher !== null || $httpFetcher === null) {
        $robotsCheck = grid402_extract_check_robots(
            (string) $check['scheme'],
            (string) $check['host'],
            (int) $check['port'],
            $reqPath,
            $dnsResolver,
            $robotsFetcher
        );
        if (empty($robotsCheck['allowed'])) {
            return [
                'success' => false,
                'status' => 'error',
                'service' => 'extract',
                'error' => (string) ($robotsCheck['error'] ?? 'RobotsDisallowed'),
                'message' => (string) ($robotsCheck['message'] ?? 'Extracao bloqueada por robots.txt.'),
                'code' => (int) ($robotsCheck['code'] ?? 403),
            ];
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
    $httpStatus = (int) ($fetched['http_status'] ?? 200);
    $contentType = (string) ($fetched['content_type'] ?? 'text/html');
    $rawBody = (string) ($fetched['body'] ?? '');

    // Normaliza headers vindos tanto do cURL real quanto de mocks em testes
    $xRobotsTags = is_array($fetched['x_robots_tag'] ?? null)
        ? $fetched['x_robots_tag']
        : (is_string($fetched['x_robots_tag'] ?? null) ? [$fetched['x_robots_tag']] : []);
    $contentSignals = is_array($fetched['content_signal'] ?? null)
        ? $fetched['content_signal']
        : (is_string($fetched['content_signal'] ?? null) ? [$fetched['content_signal']] : []);
    if (is_array($fetched['headers'] ?? null)) {
        foreach ($fetched['headers'] as $hk => $hv) {
            $hkLower = strtolower((string) $hk);
            $vals = is_array($hv) ? $hv : [$hv];
            if ($hkLower === 'x-robots-tag') {
                foreach ($vals as $v) {
                    $xRobotsTags[] = (string) $v;
                }
            } elseif ($hkLower === 'content-signal') {
                foreach ($vals as $v) {
                    $contentSignals[] = (string) $v;
                }
            }
        }
    }

    $complianceBlock = grid402_extract_inspect_compliance($httpStatus, $xRobotsTags, $contentSignals, $rawBody);
    if ($complianceBlock !== null) {
        return [
            'success' => false,
            'status' => 'error',
            'service' => 'extract',
            'error' => $complianceBlock['error'],
            'message' => $complianceBlock['message'],
            'code' => $complianceBlock['code'],
        ];
    }

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

    return [
        'success' => true,
        'status' => 'success',
        'service' => 'extract',
        'url' => $normalizedUrl,
        'final_url' => $finalUrl,
        'http_status' => $httpStatus,
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
        'transient_only' => true,
        'legal_disclaimer' => 'Transient technical transformation of publicly accessible web content respecting robots.txt and crawl directives. Grid402 stores zero third-party page content on disk and grants no copyright license.',
    ];
}
