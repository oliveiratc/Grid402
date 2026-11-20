<?php
/**
 * Grid402 - Micro-Serviço: Auditoria de E-mail e Entregabilidade B2B (/api/v1/email-verify)
 *
 * Valida sintaxe RFC, registros DNS MX, políticas SPF/DMARC, provedores gratuitos,
 * domínios descartáveis (disposable) e endereços role-based em estrita conformidade Zero-PII.
 * Nunca armazena o endereço de e-mail completo em disco ou cache.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

const GRID402_EMAIL_VERIFY_CACHE_TTL = 3600;

/**
 * Base curada de domínios descartáveis / temporários conhecidos.
 *
 * @return array<string, bool>
 */
function grid402_email_disposable_domains(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $list = [
        '10minutemail.com', '10minutemail.net', '10minutemail.org', '20minutemail.com',
        '33mail.com', 'anonbox.net', 'binkmail.com', 'bobmail.info', 'bofthew.com',
        'burnermail.io', 'clrmail.com', 'crazymailing.com', 'deadaddress.com',
        'discard.email', 'discardmail.com', 'dispostable.com', 'dodgeit.com',
        'dropmail.me', 'emailondeck.com', 'emkei.cz', 'fakeinbox.com', 'fakemail.net',
        'fakemailgenerator.com', 'filzmail.com', 'getairmail.com', 'getnada.com',
        'grr.la', 'guerrillamail.biz', 'guerrillamail.com', 'guerrillamail.de',
        'guerrillamail.info', 'guerrillamail.net', 'guerrillamail.org', 'guerrillamailblock.com',
        'harakirimail.com', 'imgof.com', 'incognitomail.com', 'inboxalias.com',
        'inboxbear.com', 'instantemailaddress.com', 'jetable.org', 'kasmail.com',
        'koszmail.pl', 'kurzepost.de', 'lhsdv.com', 'linshi-email.com', 'lroid.com',
        'mail-temporaire.fr', 'mailcatch.com', 'maildrop.cc', 'mailexpire.com',
        'mailforspam.com', 'mailfreeonline.com', 'mailhazard.com', 'mailimate.com',
        'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator.org',
        'mailinator2.com', 'mailismagic.com', 'mailmetrash.com', 'mailmoat.com',
        'mailnesia.com', 'mailnull.com', 'mailsac.com', 'mailscrap.com', 'mailshell.com',
        'mailsiphon.com', 'mailtemp.info', 'mailtothis.com', 'mintemail.com',
        'mohmal.com', 'moakt.com', 'my10minutemail.com', 'mytemp.email', 'mytrashmail.com',
        'nada.email', 'nada.ltd', 'neverbox.com', 'no-spam.ws', 'nobulk.com',
        'noclickemail.com', 'nogmailspam.info', 'nomail.xl.cx', 'nomail2me.com',
        'nospam4.us', 'nospamfor.us', 'nowmymail.com', 'objectmail.com', 'obobbo.com',
        'oneoffemail.com', 'onewaymail.com', 'owlpic.com', 'pookmail.com', 'proxymail.eu',
        'putthisinyourspamdatabase.com', 'quickinbox.com', 'rcpt.at', 'recode.me',
        'regbypass.com', 'rhyta.com', 'rppkn.com', 'rtrtr.com', 's0ny.net',
        'safe-mail.net', 'safetymail.info', 'sandelf.de', 'saynotospams.com',
        'selfdestructingmail.com', 'sharklasers.com', 'shieldedmail.com', 'shitmail.me',
        'shortmail.net', 'sibmail.com', 'skeefmail.com', 'slaskpost.se', 'slopsbox.com',
        'smashmail.de', 'smellfear.com', 'snakemail.com', 'sneakemail.com', 'sofimail.com',
        'sogetthis.com', 'soodonims.com', 'spam.la', 'spam4.me', 'spamavert.com',
        'spambob.com', 'spambog.com', 'spambox.us', 'spamcero.com', 'spamcon.org',
        'spamcorptastic.com', 'spamcowboy.com', 'spamday.com', 'spamex.com', 'spamfree24.org',
        'spamgourmet.com', 'spamherelots.com', 'spamhole.com', 'spamify.com',
        'spaminator.de', 'spamkill.info', 'spaml.com', 'spammotel.com', 'spamobox.com',
        'spamspot.com', 'spamthis.co.uk', 'spamtroll.net', 'speed.1s.fr', 'superrito.com',
        'suremail.info', 'tagyourself.com', 'teewars.org', 'teleworm.us', 'temp-mail.io',
        'temp-mail.org', 'temp-mail.ru', 'tempalias.com', 'tempe-mail.com', 'tempemail.biz',
        'tempemail.com', 'tempemail.net', 'tempinbox.co.uk', 'tempinbox.com', 'tempmail.co',
        'tempmail.de', 'tempmail.eu', 'tempmail.it', 'tempmail.net', 'tempmail.org',
        'tempmail.plus', 'tempmail2.com', 'tempmailaddress.com', 'tempmaildemo.com',
        'tempmailer.com', 'tempomail.fr', 'temporaryemail.net', 'temporaryforwarding.com',
        'temporaryinbox.com', 'tempr.email', 'thanksnospam.info', 'thankyou2010.com',
        'thisisnotmyrealemail.com', 'throwawayemailaddress.com', 'throwawaymail.com',
        'tilien.com', 'tmailinator.com', 'tradermail.info', 'trash-amil.com', 'trash-mail.at',
        'trash-mail.com', 'trash-mail.de', 'trash2009.com', 'trashemail.de', 'trashmail.at',
        'trashmail.com', 'trashmail.de', 'trashmail.me', 'trashmail.net', 'trashmail.org',
        'trashymail.com', 'trbvm.com', 'turual.com', 'twinmail.de', 'tyldd.com',
        'uggsrock.com', 'upliftnow.com', 'uplipht.com', 'venompen.com', 'veryrealemail.com',
        'viditag.com', 'viewcastmedia.com', 'webm4il.info', 'wegwerfadresse.de',
        'wegwerfemail.com', 'wegwerfemail.de', 'wegwerfmail.de', 'wegwerfmail.net',
        'wegwerfmail.org', 'wh4f.org', 'whyspam.me', 'willselfdestruct.com', 'winemaven.info',
        'wronghead.com', 'wuzup.net', 'wuzupmail.net', 'xagloo.com', 'xemaps.com',
        'xents.com', 'xmaily.com', 'xoxy.net', 'yep.it', 'yogamaven.com', 'yopmail.com',
        'yopmail.fr', 'yopmail.net', 'ypmail.webarnak.fr.eu.org', 'yuurok.com', 'zehnminutenmail.de',
        'zippymail.info', 'zoaxe.com', 'zoemail.org',
    ];
    $map = array_fill_keys($list, true);
    return $map;
}

/**
 * Provedores gratuitos populares (globais e Brasil).
 *
 * @return array<string, bool>
 */
function grid402_email_free_providers(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $list = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'outlook.com.br', 'hotmail.com',
        'hotmail.com.br', 'live.com', 'msn.com', 'yahoo.com', 'yahoo.com.br',
        'icloud.com', 'me.com', 'mac.com', 'proton.me', 'protonmail.com', 'pm.me',
        'aol.com', 'zoho.com', 'gmx.com', 'gmx.net', 'mail.com', 'yandex.com',
        'uol.com.br', 'bol.com.br', 'terra.com.br', 'ig.com.br', 'globo.com',
        'globomail.com', 'r7.com', 'zipmail.com.br', 'fastmail.com', 'tuta.io', 'tutanota.com',
    ];
    $map = array_fill_keys($list, true);
    return $map;
}

/**
 * Prefixos comuns de caixas corporativas genericas (role-based).
 *
 * @return array<string, bool>
 */
function grid402_email_role_prefixes(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $list = [
        'admin', 'administrator', 'abuse', 'billing', 'comercial', 'compliance',
        'contact', 'contato', 'dev', 'finance', 'financeiro', 'hello', 'help',
        'helpdesk', 'hr', 'info', 'juridico', 'legal', 'mail', 'marketing',
        'no-reply', 'noreply', 'office', 'ops', 'postmaster', 'privacy', 'rh',
        'root', 'sac', 'sales', 'security', 'service', 'suporte', 'support',
        'team', 'vendas', 'webmaster',
    ];
    $map = array_fill_keys($list, true);
    return $map;
}

/**
 * Valida entrada de e-mail antes da cobranca.
 */
function grid402_email_verify_validate_input(string $email): array {
    $clean = strtolower(trim($email));
    if ($clean === '' || strlen($clean) > 254 || filter_var($clean, FILTER_VALIDATE_EMAIL) === false) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidEmailParameter',
            'message' => 'Informe um endereco de e-mail sintaticamente valido no parametro "email".',
        ];
    }

    $parts = explode('@', $clean, 2);
    $local = $parts[0] ?? '';
    $domain = $parts[1] ?? '';
    if ($local === '' || strlen($local) > 64 || $domain === '' || !str_contains($domain, '.')) {
        return [
            'ok' => false,
            'code' => 400,
            'error' => 'InvalidEmailParameter',
            'message' => 'Dominio ou parte local do e-mail invalida.',
        ];
    }

    return [
        'ok' => true,
        'email' => $clean,
        'local' => $local,
        'domain' => $domain,
    ];
}

/**
 * Inspeciona postura DNS do dominio (MX, A/AAAA, SPF, DMARC) com cache seguro somente por dominio (Zero-PII).
 */
function grid402_email_inspect_domain_dns(string $domain, ?callable $dnsLookup = null): array {
    $cacheFile = GRID402_PRIVATE_DIR . '/email_domain_' . substr(hash('sha256', $domain), 0, 20) . '.json';
    if ($dnsLookup === null && is_file($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $cached = $raw ? @json_decode($raw, true) : null;
        if (is_array($cached) && isset($cached['_cached_at']) && (time() - (int) $cached['_cached_at']) < GRID402_EMAIL_VERIFY_CACHE_TTL) {
            unset($cached['_cached_at']);
            $cached['cached'] = true;
            return $cached;
        }
    }

    $query = $dnsLookup ?? static function (string $host, int $type): array {
        $res = @dns_get_record($host, $type);
        return is_array($res) ? $res : [];
    };

    $mxRaw = $query($domain, DNS_MX);
    $mxList = [];
    foreach ($mxRaw as $rec) {
        if (!empty($rec['target'])) {
            $mxList[] = [
                'host' => strtolower(rtrim((string) $rec['target'], '.')),
                'priority' => (int) ($rec['pri'] ?? 0),
            ];
        }
    }
    usort($mxList, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

    $aRaw = $query($domain, DNS_A);
    $aaaaRaw = $query($domain, DNS_AAAA);
    $hasAddressFallback = (!empty($aRaw) || !empty($aaaaRaw));

    $txtRaw = $query($domain, DNS_TXT);
    $hasSpf = false;
    $spfRecord = null;
    foreach ($txtRaw as $rec) {
        $txt = trim((string) ($rec['txt'] ?? ''));
        if (stripos($txt, 'v=spf1') === 0) {
            $hasSpf = true;
            $spfRecord = substr($txt, 0, 240);
            break;
        }
    }

    $dmarcRaw = $query('_dmarc.' . $domain, DNS_TXT);
    $hasDmarc = false;
    $dmarcPolicy = null;
    foreach ($dmarcRaw as $rec) {
        $txt = trim((string) ($rec['txt'] ?? ''));
        if (stripos($txt, 'v=DMARC1') === 0) {
            $hasDmarc = true;
            if (preg_match('/\bp=([a-zA-Z]+)/', $txt, $pm)) {
                $dmarcPolicy = strtolower($pm[1]);
            }
            break;
        }
    }

    $posture = [
        'has_mx' => !empty($mxList),
        'mx_count' => count($mxList),
        'mx_hosts' => array_slice($mxList, 0, 10),
        'has_address_fallback' => $hasAddressFallback,
        'has_spf' => $hasSpf,
        'spf_record' => $spfRecord,
        'has_dmarc' => $hasDmarc,
        'dmarc_policy' => $dmarcPolicy,
        'cached' => false,
    ];

    if ($dnsLookup === null && function_exists('grid402_atomic_write')) {
        $toStore = $posture;
        $toStore['_cached_at'] = time();
        grid402_atomic_write($cacheFile, $toStore);
    }

    return $posture;
}

/**
 * Amostra gratuita (teaser Zero-PII) para o desafio HTTP 402.
 */
function grid402_teaser_email_verify(string $email): array {
    $clean = strtolower(trim($email));
    $domain = str_contains($clean, '@') ? (explode('@', $clean, 2)[1] ?: 'grid402.online') : 'grid402.online';
    return [
        'preview' => [
            'domain' => $domain,
            'privacy_policy' => 'Zero-PII (raw email is hashed with SHA-256 and never persisted)',
            'checks' => ['rfc_syntax', 'dns_mx', 'spf', 'dmarc', 'disposable_db', 'role_based'],
        ],
        'locked_fields' => ['verdict', 'deliverability_score', 'has_mx', 'mx_hosts', 'has_spf', 'has_dmarc', 'disposable', '_oracle_proof'],
    ];
}

/**
 * Servico principal: GET /api/v1/email-verify?email=...
 */
function grid402_service_email_verify(string $email, ?callable $dnsLookup = null): array {
    $parsed = grid402_email_verify_validate_input($email);
    if (!$parsed['ok']) {
        return [
            'success' => false,
            'status' => 'error',
            'service' => 'email-verify',
            'error' => $parsed['error'],
            'message' => $parsed['message'],
            'code' => $parsed['code'],
        ];
    }

    $normalizedEmail = (string) $parsed['email'];
    $local = (string) $parsed['local'];
    $domain = (string) $parsed['domain'];

    $disposableMap = grid402_email_disposable_domains();
    $freeMap = grid402_email_free_providers();
    $roleMap = grid402_email_role_prefixes();

    $isDisposable = isset($disposableMap[$domain]);
    $isFree = isset($freeMap[$domain]);
    $baseLocal = explode('+', $local, 2)[0];
    $isRoleBased = isset($roleMap[$baseLocal]);

    $dns = grid402_email_inspect_domain_dns($domain, $dnsLookup);
    $hasMx = (bool) $dns['has_mx'];
    $hasFallback = (bool) $dns['has_address_fallback'];
    $hasSpf = (bool) $dns['has_spf'];
    $hasDmarc = (bool) $dns['has_dmarc'];

    $score = 0;
    $flags = [];
    if ($hasMx) {
        $score += 60;
    } elseif ($hasFallback) {
        $score += 25;
        $flags[] = 'NO_MX_FALLBACK_A_ONLY';
    } else {
        $flags[] = 'DOMAIN_NO_MAIL_RECORDS';
    }

    if ($hasSpf) {
        $score += 20;
    } else {
        $flags[] = 'MISSING_SPF';
    }

    if ($hasDmarc) {
        $score += 15;
    } else {
        $flags[] = 'MISSING_DMARC';
    }

    if (!$isDisposable) {
        $score += 5;
    } else {
        $score = min($score, 25);
        $flags[] = 'DISPOSABLE_DOMAIN';
    }

    if ($isRoleBased) {
        $score = max(0, $score - 10);
        $flags[] = 'ROLE_BASED_ADDRESS';
    }
    if ($isFree) {
        $flags[] = 'FREE_EMAIL_PROVIDER';
    }

    $score = max(0, min(100, $score));
    if (!$hasMx && !$hasFallback) {
        $verdict = 'INVALID';
    } elseif ($isDisposable || !$hasMx || ($isRoleBased && !$hasSpf && !$hasDmarc)) {
        $verdict = 'RISKY';
    } else {
        $verdict = 'VALID';
    }

    return [
        'success' => true,
        'status' => 'success',
        'service' => 'email-verify',
        'domain' => $domain,
        'email_hash_sha256' => hash('sha256', $normalizedEmail),
        'local_part_type' => $isRoleBased ? 'role_based' : 'personal',
        'syntax_valid' => true,
        'verdict' => $verdict,
        'deliverability_score' => $score,
        'has_mx' => $hasMx,
        'mx_count' => (int) $dns['mx_count'],
        'mx_hosts' => $dns['mx_hosts'],
        'has_spf' => $hasSpf,
        'spf_record' => $dns['spf_record'],
        'has_dmarc' => $hasDmarc,
        'dmarc_policy' => $dns['dmarc_policy'],
        'disposable' => $isDisposable,
        'free_provider' => $isFree,
        'role_based' => $isRoleBased,
        'flags' => $flags,
        'privacy' => 'Zero-PII: raw email address is never stored on disk',
        'tariff_usdc' => GRID402_PRICE_DISPLAY,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'cached' => (bool) $dns['cached'],
    ];
}
