<?php
/**
 * Grid402 - Indice Zero-DB Particionado de Sancoes Globais (4 Blocos Oficiais)
 *
 * Fontes suportadas:
 * 1. US Treasury OFAC SDN (sdn_advanced.xml - Dominio Publico 17 U.S.C. § 105)
 * 2. UN Security Council Consolidated Sanctions (consolidated.xml - UN Open Data)
 * 3. European Union Financial Sanctions (FSFi XML - EU Open Data)
 * 4. UK OFSI Consolidated List (XML/CSV - Open Government Licence v3.0)
 *
 * Opera 100% em arquivos JSON particionados (sem exigir sqlite3 nem mbstring).
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}

require_once __DIR__ . '/../config.php';

/**
 * Resolve o diretorio do indice de sancoes (suportando override em testes).
 */
function grid402_sanctions_index_dir(): string {
    $override = $GLOBALS['grid402_sanctions_index_dir_override'] ?? null;
    if (is_string($override) && trim($override) !== '') {
        return rtrim(trim($override), '/\\');
    }
    if (defined('GRID402_SANCTIONS_INDEX_DIR') && GRID402_SANCTIONS_INDEX_DIR !== '') {
        return rtrim((string) GRID402_SANCTIONS_INDEX_DIR, '/\\');
    }
    return rtrim((string) GRID402_PRIVATE_DIR, '/\\') . '/sanctions_index';
}

/**
 * Normaliza texto para busca de entidades (sem acentos, pontuacao reduzida, maiusculas ASCII).
 * Sem dependencia de mbstring.
 */
function grid402_sanctions_normalize_text(string $text): string {
    $clean = trim($text);
    if ($clean === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
        if (is_string($trans) && $trans !== '') {
            $clean = $trans;
        }
    }
    $clean = strtr($clean, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N',
    ]);
    $upper = strtoupper($clean);
    $upper = preg_replace('/[^A-Z0-9\s]+/', ' ', $upper) ?? $upper;
    $upper = preg_replace('/\s+/', ' ', trim($upper)) ?? trim($upper);
    return $upper;
}

/**
 * Detecta se uma entrada representa endereco cripto multi-ativo (EVM, Solana, BTC, Tron, XRP, LTC).
 * Retorna ['type' => string, 'normalized' => string, 'case_sensitive' => bool] ou null se for nome de entidade.
 */
function grid402_ofac_detect_address_type(string $input): ?array {
    $raw = trim($input);
    if ($raw === '' || preg_match('/\s/', $raw) === 1) {
        return null;
    }

    if (preg_match('/^0x[a-fA-F0-9]{40}$/', $raw) === 1) {
        return ['type' => 'evm', 'normalized' => strtolower($raw), 'case_sensitive' => false];
    }
    if (preg_match('/^(?:bc1|tb1)[a-zA-Z0-9]{25,62}$/', $raw) === 1) {
        return ['type' => 'btc_bech32', 'normalized' => strtolower($raw), 'case_sensitive' => false];
    }
    if (preg_match('/^ltc1[a-zA-Z0-9]{25,62}$/i', $raw) === 1) {
        return ['type' => 'ltc_bech32', 'normalized' => strtolower($raw), 'case_sensitive' => false];
    }
    if (preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $raw) === 1) {
        return ['type' => 'btc_legacy', 'normalized' => $raw, 'case_sensitive' => true];
    }
    if (preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $raw) === 1) {
        return ['type' => 'tron', 'normalized' => $raw, 'case_sensitive' => true];
    }
    if (preg_match('/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/', $raw) === 1) {
        return ['type' => 'xrp', 'normalized' => $raw, 'case_sensitive' => true];
    }
    if (preg_match('/^[LM][a-km-zA-HJ-NP-Z1-9]{26,33}$/', $raw) === 1) {
        return ['type' => 'ltc_legacy', 'normalized' => $raw, 'case_sensitive' => true];
    }
    // Solana Base58 (32-44 chars, nunca contem 0, O, I, l)
    if (preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $raw) === 1) {
        return ['type' => 'solana', 'normalized' => $raw, 'case_sensitive' => true];
    }
    // Enderecos longos hex/base58 genericos (ex.: XMR 95 chars)
    if (preg_match('/^[1-9A-HJ-NP-Za-km-z]{60,110}$/', $raw) === 1) {
        return ['type' => 'crypto_generic', 'normalized' => $raw, 'case_sensitive' => true];
    }

    return null;
}

function grid402_sanctions_addr_bucket(string $address): string {
    return substr(hash('sha256', strtolower(trim($address))), 0, 2);
}

/**
 * Extrai tokens alfanumericos relevantes (>= 3 chars) de um nome normalizado.
 *
 * @return string[]
 */
function grid402_sanctions_entity_tokens(string $normalizedText): array {
    if ($normalizedText === '') {
        return [];
    }
    $stopwords = ['THE', 'AND', 'FOR', 'LTD', 'INC', 'LLC', 'CORP', 'SA', 'LTDA', 'GMBH', 'PLC', 'PTY', 'JSC', 'PJSC', 'OOO', 'GROUP', 'HOLDING'];
    $parts = preg_split('/\s+/', $normalizedText, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return [];
    }
    $tokens = [];
    foreach ($parts as $p) {
        if (strlen($p) >= 3 && !in_array($p, $stopwords, true)) {
            $tokens[] = $p;
        }
    }
    if (empty($tokens)) {
        foreach ($parts as $p) {
            if (strlen($p) >= 2) {
                $tokens[] = $p;
            }
        }
    }
    return array_values(array_unique($tokens));
}

function grid402_sanctions_ent_bucket(string $token): string {
    $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($token)) ?? '';
    if (strlen($clean) >= 2) {
        return strtolower(substr($clean, 0, 2));
    }
    return '_other';
}

function grid402_sanctions_read_manifest(?string $dir = null): ?array {
    $baseDir = $dir !== null ? rtrim($dir, '/\\') : grid402_sanctions_index_dir();
    $manifestPath = $baseDir . '/manifest.json';
    if (!is_file($manifestPath) || !is_readable($manifestPath)) {
        return null;
    }
    $raw = @file_get_contents($manifestPath);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = @json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['synced_at']) || empty($decoded['dataset_sha256']) || !is_array($decoded['sources'] ?? null)) {
        return null;
    }
    return $decoded;
}

/**
 * Resumo publico seguro de disponibilidade e frescor do indice de sancoes.
 *
 * @return array<string, mixed>
 */
function grid402_sanctions_index_summary(?string $dir = null, ?int $now = null): array {
    $manifest = grid402_sanctions_read_manifest($dir);
    $staleHours = defined('GRID402_SANCTIONS_STALE_HOURS') ? (int) GRID402_SANCTIONS_STALE_HOURS : 72;
    if ($manifest === null) {
        return [
            'available' => false,
            'freshness_status' => 'MISSING',
            'stale_dataset_warning' => false,
            'last_synced_at' => null,
            'age_hours' => null,
            'freshness_ttl_hours' => $staleHours,
            'dataset_sha256' => null,
            'total_addresses' => 0,
            'total_entities' => 0,
            'lists_counts' => [],
            'sources' => [],
        ];
    }

    $nowTs = $now ?? time();
    $syncedTs = strtotime((string) $manifest['synced_at']);
    $ageHours = ($syncedTs !== false) ? round(max(0, $nowTs - $syncedTs) / 3600, 2) : null;
    $isStale = ($ageHours === null) || ($ageHours > $staleHours);

    $listsCounts = [];
    $publicSources = [];
    foreach ($manifest['sources'] as $k => $src) {
        if (!is_array($src)) {
            continue;
        }
        $listsCounts[$k] = [
            'records' => (int) ($src['records_count'] ?? 0),
            'addresses' => (int) ($src['addresses_count'] ?? 0),
        ];
        $publicSources[$k] = [
            'authority' => (string) ($src['authority'] ?? ''),
            'license' => (string) ($src['license'] ?? ''),
            'records_count' => (int) ($src['records_count'] ?? 0),
            'addresses_count' => (int) ($src['addresses_count'] ?? 0),
            'source_sha256' => (string) ($src['source_sha256'] ?? ''),
        ];
    }

    return [
        'available' => true,
        'freshness_status' => $isStale ? 'STALE' : 'FRESH',
        'stale_dataset_warning' => $isStale,
        'last_synced_at' => (string) $manifest['synced_at'],
        'age_hours' => $ageHours,
        'freshness_ttl_hours' => $staleHours,
        'dataset_sha256' => (string) $manifest['dataset_sha256'],
        'total_addresses' => (int) ($manifest['total_addresses'] ?? 0),
        'total_entities' => (int) ($manifest['total_entities'] ?? 0),
        'lists_counts' => $listsCounts,
        'sources' => $publicSources,
    ];
}

/**
 * Consulta endereco cripto no shard particionado correspondente (O(1)).
 *
 * @return array<int, array<string, mixed>>
 */
function grid402_sanctions_lookup_address(string $rawAddress, ?string $dir = null): array {
    $baseDir = $dir !== null ? rtrim($dir, '/\\') : grid402_sanctions_index_dir();
    $detected = grid402_ofac_detect_address_type($rawAddress);
    $lookupKey = $detected !== null ? strtolower((string) $detected['normalized']) : strtolower(trim($rawAddress));
    if ($lookupKey === '') {
        return [];
    }

    $bucket = grid402_sanctions_addr_bucket($lookupKey);
    $shardPath = $baseDir . '/addr_' . $bucket . '.json';
    if (!is_file($shardPath) || !is_readable($shardPath)) {
        return [];
    }

    $raw = @file_get_contents($shardPath);
    $map = is_string($raw) ? @json_decode($raw, true) : null;
    if (!is_array($map)) {
        return [];
    }

    $matches = [];
    if (isset($map[$lookupKey]) && is_array($map[$lookupKey])) {
        foreach ($map[$lookupKey] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $caseSensitive = ($detected !== null && !empty($detected['case_sensitive']));
            $entryOrig = (string) ($entry['original_address'] ?? '');
            if ($caseSensitive && $entryOrig !== '' && $entryOrig !== trim($rawAddress)) {
                continue;
            }
            $matches[] = $entry;
        }
    }

    return $matches;
}

/**
 * Consulta entidade/pessoa por nome ou alias nos shards particionados.
 *
 * @return array<int, array<string, mixed>>
 */
function grid402_sanctions_lookup_entity(string $entityName, ?string $dir = null): array {
    $baseDir = $dir !== null ? rtrim($dir, '/\\') : grid402_sanctions_index_dir();
    $normQuery = grid402_sanctions_normalize_text($entityName);
    if ($normQuery === '' || strlen($normQuery) < 3) {
        return [];
    }

    $tokens = grid402_sanctions_entity_tokens($normQuery);
    if (empty($tokens)) {
        return [];
    }

    // Ordena pelo token mais longo/especifico primeiro para reduzir candidatos
    usort($tokens, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    $bucketsToCheck = [];
    foreach (array_slice($tokens, 0, 2) as $tok) {
        $bucketsToCheck[grid402_sanctions_ent_bucket($tok)] = true;
    }

    $candidatesByUid = [];
    foreach (array_keys($bucketsToCheck) as $bucket) {
        $shardPath = $baseDir . '/ent_' . $bucket . '.json';
        if (!is_file($shardPath) || !is_readable($shardPath)) {
            continue;
        }
        $raw = @file_get_contents($shardPath);
        $list = is_string($raw) ? @json_decode($raw, true) : null;
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $rec) {
            if (!is_array($rec) || empty($rec['uid'])) {
                continue;
            }
            $uid = (string) $rec['source'] . ':' . (string) $rec['uid'];
            $candidatesByUid[$uid] = $rec;
        }
    }

    $matches = [];
    foreach ($candidatesByUid as $rec) {
        $allNames = [];
        if (!empty($rec['normalized_name'])) {
            $allNames[] = (string) $rec['normalized_name'];
        }
        foreach ($rec['normalized_aliases'] ?? [] as $al) {
            if (is_string($al) && $al !== '') {
                $allNames[] = $al;
            }
        }

        $matchedType = null;
        foreach ($allNames as $candName) {
            if ($candName === $normQuery) {
                $matchedType = 'EXACT_ENTITY_MATCH';
                break;
            }
            if (strlen($normQuery) >= 5 && (str_contains($candName, $normQuery) || (strlen($candName) >= 5 && str_contains($normQuery, $candName)))) {
                $matchedType = 'SUBSTRING_ENTITY_MATCH';
            } else {
                // Todos os tokens da query presentes no candidato
                $allTokensPresent = true;
                foreach ($tokens as $tok) {
                    if (!preg_match('/\b' . preg_quote($tok, '/') . '\b/', $candName)) {
                        $allTokensPresent = false;
                        break;
                    }
                }
                if ($allTokensPresent && count($tokens) >= 1) {
                    $matchedType = $matchedType ?? 'TOKEN_ENTITY_MATCH';
                }
            }
        }

        if ($matchedType !== null) {
            $rec['match_type'] = $matchedType;
            $matches[] = $rec;
            if (count($matches) >= 20) {
                break;
            }
        }
    }

    return $matches;
}

/* -------------------------------------------------------------------------
 * Parsers Streaming (XMLReader / CSV incremental) para os 4 blocos oficiais
 * ---------------------------------------------------------------------- */

function grid402_sanctions_extract_asset_from_label(string $label): string {
    if (preg_match('/Digital\s+Currency\s+Address\s*[-:]\s*([A-Z0-9]+)/i', $label, $m) === 1) {
        return strtoupper($m[1]);
    }
    return 'CRYPTO';
}

/**
 * Faz parse em streaming do XML oficial US Treasury OFAC (sdn_advanced.xml ou sdnList).
 *
 * @return array{records: array<int, array<string, mixed>>, addresses: array<int, array<string, mixed>>}
 */
function grid402_sanctions_parse_ofac_xml(string $filePath): array {
    if (!is_file($filePath) || !class_exists('XMLReader')) {
        throw new RuntimeException('Arquivo OFAC ausente ou XMLReader indisponivel.');
    }
    $reader = new XMLReader();
    $prevErrors = libxml_use_internal_errors(true);
    if (!$reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
        libxml_use_internal_errors($prevErrors);
        throw new RuntimeException('Nao foi possivel abrir o XML OFAC: ' . $filePath);
    }

    $featureTypes = [];
    $sanctionsPrograms = [];
    $programsByParty = [];
    $records = [];
    $addresses = [];

    try {
        while (@$reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            $localName = $reader->localName;

            if ($localName === 'FeatureType') {
                $id = (string) $reader->getAttribute('ID');
                $xml = $reader->readOuterXML();
                $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                if ($id !== '' && $el !== false) {
                    $featureTypes[$id] = trim((string) $el);
                }
                unset($xml, $el);
            } elseif ($localName === 'SanctionsProgram') {
                $id = (string) $reader->getAttribute('ID');
                $xml = $reader->readOuterXML();
                $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                if ($id !== '' && $el !== false) {
                    $sanctionsPrograms[$id] = trim((string) $el);
                }
                unset($xml, $el);
            } elseif ($localName === 'SanctionsEntry') {
                $xml = $reader->readOuterXML();
                $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                if ($el !== false) {
                    $profileId = (string) ($el['ProfileID'] ?? '');
                    $progs = [];
                    foreach ($el->xpath('.//*[local-name()="SanctionsMeasure"]') ?: [] as $m) {
                        $comment = trim((string) ($m->Comment ?? ''));
                        if ($comment !== '') {
                            $progs[] = $comment;
                        }
                        $spId = trim((string) ($m['SanctionsProgramID'] ?? $m['ProgramID'] ?? ''));
                        if ($spId !== '' && isset($sanctionsPrograms[$spId]) && $sanctionsPrograms[$spId] !== 'Unknown') {
                            $progs[] = $sanctionsPrograms[$spId];
                        }
                    }
                    $listId = trim((string) ($el['ListID'] ?? ''));
                    if ($profileId !== '') {
                        if (!isset($programsByParty[$profileId])) {
                            $programsByParty[$profileId] = [];
                        }
                        foreach ($progs as $pVal) {
                            $programsByParty[$profileId][] = $pVal;
                        }
                        if (empty($programsByParty[$profileId]) && $listId !== '') {
                            $programsByParty[$profileId][] = 'OFAC SDN';
                        }
                    }
                }
                unset($xml, $el);
            } elseif ($localName === 'DistinctParty') {
                $xml = $reader->readOuterXML();
                $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                if ($el === false) {
                    unset($xml);
                    continue;
                }
                $uid = (string) ($el['FixedRef'] ?? '');
                if ($uid === '') {
                    unset($xml, $el);
                    continue;
                }
                $primaryName = '';
                $aliases = [];
                foreach ($el->xpath('.//*[local-name()="Alias"]') ?: [] as $aliasEl) {
                    $isPrimaryAlias = strtolower((string) ($aliasEl['Primary'] ?? '')) === 'true';
                    $docNames = $aliasEl->xpath('.//*[local-name()="DocumentedName"]') ?: [];
                    if (!empty($docNames)) {
                        foreach ($docNames as $dnIdx => $dnEl) {
                            $parts = [];
                            foreach ($dnEl->xpath('.//*[local-name()="NamePartValue"]') ?: [] as $np) {
                                $t = trim((string) $np);
                                if ($t !== '') {
                                    $parts[] = $t;
                                }
                            }
                            $full = trim(implode(' ', $parts));
                            if ($full === '') {
                                continue;
                            }
                            if ($isPrimaryAlias && $dnIdx === 0 && $primaryName === '') {
                                $primaryName = $full;
                            } else {
                                $aliases[] = $full;
                            }
                        }
                    } else {
                        $parts = [];
                        foreach ($aliasEl->xpath('.//*[local-name()="NamePartValue"]') ?: [] as $np) {
                            $t = trim((string) $np);
                            if ($t !== '') {
                                $parts[] = $t;
                            }
                        }
                        $full = trim(implode(' ', $parts));
                        if ($full !== '') {
                            if ($isPrimaryAlias && $primaryName === '') {
                                $primaryName = $full;
                            } else {
                                $aliases[] = $full;
                            }
                        }
                    }
                }
                if ($primaryName === '' && !empty($aliases)) {
                    $primaryName = array_shift($aliases);
                }
                if ($primaryName === '') {
                    unset($xml, $el);
                    continue;
                }
                $aliases = array_values(array_unique($aliases));
                $profileNode = ($el->xpath('.//*[local-name()="Profile"]') ?: [])[0] ?? null;
                $profileId = $profileNode ? (string) ($profileNode['ID'] ?? $uid) : $uid;

                $records[] = [
                    'uid' => $uid,
                    'source' => 'ofac_sdn',
                    'source_label' => 'US Treasury OFAC SDN',
                    'name' => $primaryName,
                    'aliases' => $aliases,
                    'program' => 'OFAC SDN',
                    'listed_date' => null,
                    'jurisdiction' => 'US/OFAC',
                    '_profile_id' => $profileId,
                ];

                foreach ($el->xpath('.//*[local-name()="Feature"]') ?: [] as $feat) {
                    $ftId = (string) ($feat['FeatureTypeID'] ?? '');
                    $ftLabel = $featureTypes[$ftId] ?? '';
                    if (stripos($ftLabel, 'Digital Currency Address') === false) {
                        continue;
                    }
                    $asset = grid402_sanctions_extract_asset_from_label($ftLabel);
                    foreach ($feat->xpath('.//*[local-name()="VersionDetail"]') ?: [] as $vd) {
                        $addrVal = trim((string) $vd);
                        if ($addrVal !== '') {
                            $addresses[] = [
                                'uid' => $uid,
                                'source' => 'ofac_sdn',
                                'source_label' => 'US Treasury OFAC SDN',
                                'entity' => $primaryName,
                                'program' => 'OFAC SDN',
                                'asset' => $asset,
                                'address' => $addrVal,
                                'listed_date' => null,
                                'jurisdiction' => 'GLOBAL',
                                '_profile_id' => $profileId,
                            ];
                        }
                    }
                }
                unset($xml, $el);
            } elseif ($localName === 'sdnEntry') {
                // Suporte ao formato legado sdn.xml
                $xml = $reader->readOuterXML();
                $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                if ($el === false) {
                    continue;
                }
                $uid = trim((string) ($el->uid ?? ''));
                $name = trim(trim((string) ($el->firstName ?? '')) . ' ' . trim((string) ($el->lastName ?? '')));
                if ($uid === '' || $name === '') {
                    continue;
                }
                $progs = [];
                if (isset($el->programList->program)) {
                    foreach ($el->programList->program as $p) {
                        $progs[] = trim((string) $p);
                    }
                }
                $program = !empty($progs) ? implode('; ', $progs) : 'OFAC SDN';
                $aliases = [];
                if (isset($el->akaList->aka)) {
                    foreach ($el->akaList->aka as $aka) {
                        $akName = trim(trim((string) ($aka->firstName ?? '')) . ' ' . trim((string) ($aka->lastName ?? '')));
                        if ($akName !== '') {
                            $aliases[] = $akName;
                        }
                    }
                }
                $records[] = [
                    'uid' => $uid,
                    'source' => 'ofac_sdn',
                    'source_label' => 'US Treasury OFAC SDN',
                    'name' => $name,
                    'aliases' => array_values(array_unique($aliases)),
                    'program' => $program,
                    'listed_date' => null,
                    'jurisdiction' => 'US/OFAC',
                ];
                if (isset($el->idList->id)) {
                    foreach ($el->idList->id as $idEl) {
                        $idType = trim((string) ($idEl->idType ?? ''));
                        $idNum = trim((string) ($idEl->idNumber ?? ''));
                        if ($idNum !== '' && stripos($idType, 'Digital Currency Address') !== false) {
                            $addresses[] = [
                                'uid' => $uid,
                                'source' => 'ofac_sdn',
                                'source_label' => 'US Treasury OFAC SDN',
                                'entity' => $name,
                                'program' => $program,
                                'asset' => grid402_sanctions_extract_asset_from_label($idType),
                                'address' => $idNum,
                                'listed_date' => null,
                                'jurisdiction' => 'GLOBAL',
                            ];
                        }
                    }
                }
            }
        }
        } finally {
        $errs = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);
        $reader->close();
        if (!empty($errs) && empty($records)) {
            throw new RuntimeException('XML OFAC malformado: ' . trim($errs[0]->message));
        }
    }

    // Pos-processa programas por ProfileID (sdn_advanced.xml coloca SanctionsEntry depois de DistinctParty)
    foreach ($records as &$rec) {
        $pId = (string) ($rec['_profile_id'] ?? '');
        unset($rec['_profile_id']);
        if ($pId !== '' && !empty($programsByParty[$pId])) {
            $rec['program'] = implode('; ', array_values(array_unique($programsByParty[$pId])));
        }
    }
    unset($rec);

    foreach ($addresses as &$addr) {
        $pId = (string) ($addr['_profile_id'] ?? '');
        unset($addr['_profile_id']);
        $resolvedProg = ($pId !== '' && !empty($programsByParty[$pId]))
            ? implode('; ', array_values(array_unique($programsByParty[$pId])))
            : (string) ($addr['program'] ?? 'OFAC SDN');
        $addr['program'] = str_contains($resolvedProg, 'OFAC SDN') ? $resolvedProg : ($resolvedProg . ' (OFAC SDN)');
    }
    unset($addr);

    return ['records' => $records, 'addresses' => $addresses];
}

/**
 * Faz parse em streaming do XML oficial UN Security Council Consolidated List.
 *
 * @return array{records: array<int, array<string, mixed>>, addresses: array<int, array<string, mixed>>}
 */
function grid402_sanctions_parse_un_xml(string $filePath): array {
    if (!is_file($filePath) || !class_exists('XMLReader')) {
        throw new RuntimeException('Arquivo ONU ausente ou XMLReader indisponivel.');
    }
    $reader = new XMLReader();
    $prevErrors = libxml_use_internal_errors(true);
    if (!$reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
        libxml_use_internal_errors($prevErrors);
        throw new RuntimeException('Nao foi possivel abrir o XML ONU: ' . $filePath);
    }

    $records = [];
    try {
        while (@$reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->localName !== 'INDIVIDUAL' && $reader->localName !== 'ENTITY') {
                continue;
            }
            $xml = $reader->readOuterXML();
            $el = $xml !== '' ? @simplexml_load_string($xml) : false;
            if ($el === false) {
                continue;
            }
            $uid = trim((string) ($el->DATAID ?? ''));
            $nameParts = array_filter([
                trim((string) ($el->FIRST_NAME ?? '')),
                trim((string) ($el->SECOND_NAME ?? '')),
                trim((string) ($el->THIRD_NAME ?? '')),
                trim((string) ($el->FOURTH_NAME ?? '')),
            ], static fn(string $v): bool => $v !== '');
            $name = implode(' ', $nameParts);
            if ($uid === '' || $name === '') {
                continue;
            }

            $aliases = [];
            foreach (['INDIVIDUAL_ALIAS', 'ENTITY_ALIAS'] as $aliasTag) {
                if (isset($el->{$aliasTag})) {
                    foreach ($el->{$aliasTag} as $al) {
                        $alName = trim((string) ($al->ALIAS_NAME ?? ''));
                        if ($alName !== '') {
                            $aliases[] = $alName;
                        }
                    }
                }
            }
            $listType = trim((string) ($el->UN_LIST_TYPE ?? 'UN Consolidated'));
            $listedOn = trim((string) ($el->LISTED_ON ?? ''));
            $nat = trim((string) ($el->NATIONALITY->VALUE ?? 'UN'));

            $records[] = [
                'uid' => $uid,
                'source' => 'un_consolidated',
                'source_label' => 'UN Security Council Consolidated List',
                'name' => $name,
                'aliases' => array_values(array_unique($aliases)),
                'program' => $listType !== '' ? $listType : 'UN Security Council',
                'listed_date' => $listedOn !== '' ? $listedOn : null,
                'jurisdiction' => $nat !== '' ? $nat : 'UN',
            ];
        }
    } finally {
        $errs = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);
        $reader->close();
        if (!empty($errs) && empty($records)) {
            throw new RuntimeException('XML ONU malformado: ' . trim($errs[0]->message));
        }
    }

    return ['records' => $records, 'addresses' => []];
}

/**
 * Faz parse em streaming do XML oficial EU Financial Sanctions (FSFi).
 *
 * @return array{records: array<int, array<string, mixed>>, addresses: array<int, array<string, mixed>>}
 */
function grid402_sanctions_parse_eu_xml(string $filePath): array {
    if (!is_file($filePath) || !class_exists('XMLReader')) {
        throw new RuntimeException('Arquivo EU FSFi ausente ou XMLReader indisponivel.');
    }
    $reader = new XMLReader();
    $prevErrors = libxml_use_internal_errors(true);
    if (!$reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
        libxml_use_internal_errors($prevErrors);
        throw new RuntimeException('Nao foi possivel abrir o XML EU FSFi: ' . $filePath);
    }

    $records = [];
    try {
        while (@$reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sanctionEntity') {
                continue;
            }
            $xml = $reader->readOuterXML();
            $el = $xml !== '' ? @simplexml_load_string($xml) : false;
            if ($el === false) {
                continue;
            }
            $uid = trim((string) ($el['logicalId'] ?? $el['euReferenceNumber'] ?? ''));
            $names = [];
            foreach ($el->xpath('.//*[local-name()="nameAlias"]') ?: [] as $na) {
                $whole = trim((string) ($na['wholeName'] ?? ''));
                if ($whole === '') {
                    $whole = trim(implode(' ', array_filter([
                        trim((string) ($na['firstName'] ?? '')),
                        trim((string) ($na['middleName'] ?? '')),
                        trim((string) ($na['lastName'] ?? '')),
                    ], static fn(string $v): bool => $v !== '')));
                }
                if ($whole !== '') {
                    $names[] = $whole;
                }
            }
            $names = array_values(array_unique($names));
            if ($uid === '' || empty($names)) {
                continue;
            }
            $primaryName = array_shift($names);
            $regEl = ($el->xpath('.//*[local-name()="regulation"]') ?: [])[0] ?? null;
            $program = $regEl ? trim((string) ($regEl['programme'] ?? 'EU FSFi')) : 'EU FSFi';
            $listedDate = $regEl ? trim((string) ($regEl['publicationDate'] ?? $regEl['entryIntoForceDate'] ?? '')) : '';
            $citEl = ($el->xpath('.//*[local-name()="citizenship"]') ?: [])[0] ?? null;
            $country = $citEl ? trim((string) ($citEl['countryIso2Code'] ?? 'EU')) : 'EU';

            $records[] = [
                'uid' => $uid,
                'source' => 'eu_fsfi',
                'source_label' => 'EU Financial Sanctions (FSFi)',
                'name' => $primaryName,
                'aliases' => $names,
                'program' => $program !== '' ? $program : 'EU FSFi',
                'listed_date' => $listedDate !== '' ? $listedDate : null,
                'jurisdiction' => $country !== '' ? $country : 'EU',
            ];
        }
    } finally {
        $errs = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);
        $reader->close();
        if (!empty($errs) && empty($records)) {
            throw new RuntimeException('XML EU FSFi malformado: ' . trim($errs[0]->message));
        }
    }

    return ['records' => $records, 'addresses' => []];
}

/**
 * Faz parse em streaming do arquivo oficial UK OFSI (XML ou CSV).
 *
 * @return array{records: array<int, array<string, mixed>>, addresses: array<int, array<string, mixed>>}
 */
function grid402_sanctions_parse_uk_ofsi(string $filePath): array {
    if (!is_file($filePath)) {
        throw new RuntimeException('Arquivo UK OFSI ausente.');
    }

    $fhHead = @fopen($filePath, 'rb');
    $peek = $fhHead ? (string) @fread($fhHead, 512) : '';
    if ($fhHead) {
        fclose($fhHead);
    }

    // Detecta se e XML ou CSV
    if (str_contains($peek, '<')) {
        if (!class_exists('XMLReader')) {
            throw new RuntimeException('XMLReader indisponivel para UK OFSI.');
        }
        $reader = new XMLReader();
        $prevErrors = libxml_use_internal_errors(true);
        if (!$reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            libxml_use_internal_errors($prevErrors);
            throw new RuntimeException('Nao foi possivel abrir XML UK OFSI: ' . $filePath);
        }

        $grouped = [];
        try {
            while (@$reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->localName === 'FinancialSanctionsTarget') {
                    $xml = $reader->readOuterXML();
                    $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                    if ($el === false) {
                        continue;
                    }
                    $uid = trim((string) ($el->GroupID ?? $el->OFSI_Group_ID ?? ''));
                    $fullName = trim(implode(' ', array_filter([
                        trim((string) ($el->Name1 ?? '')),
                        trim((string) ($el->Name2 ?? '')),
                        trim((string) ($el->Name3 ?? '')),
                        trim((string) ($el->Name4 ?? '')),
                        trim((string) ($el->Name5 ?? '')),
                        trim((string) ($el->Name6 ?? '')),
                    ], static fn(string $v): bool => $v !== '')));
                    if ($uid === '' || $fullName === '') {
                        continue;
                    }
                    $aliasType = strtolower(trim((string) ($el->AliasType ?? 'primary name')));
                    $regime = trim((string) ($el->RegimeName ?? $el->Regime ?? 'UK OFSI'));
                    $listedDate = trim((string) ($el->DateListed ?? $el->DateDesignated ?? ''));
                    $country = trim((string) ($el->Country ?? 'UK'));
                    if (!isset($grouped[$uid])) {
                        $grouped[$uid] = [
                            'uid' => $uid,
                            'source' => 'uk_ofsi',
                            'source_label' => 'UK OFSI Consolidated List',
                            'name' => $fullName,
                            'aliases' => [],
                            'program' => $regime !== '' ? $regime : 'UK OFSI',
                            'listed_date' => $listedDate !== '' ? $listedDate : null,
                            'jurisdiction' => $country !== '' ? $country : 'UK',
                        ];
                    } else {
                        if (str_contains($aliasType, 'primary') && $grouped[$uid]['name'] === '') {
                            $grouped[$uid]['name'] = $fullName;
                        } else {
                            $grouped[$uid]['aliases'][] = $fullName;
                        }
                    }
                } elseif ($reader->localName === 'Designation') {
                    $xml = $reader->readOuterXML();
                    $el = $xml !== '' ? @simplexml_load_string($xml) : false;
                    if ($el === false) {
                        unset($xml);
                        continue;
                    }
                    $uid = trim((string) ($el->UniqueID ?? $el->OFSIGroupID ?? ''));
                    $primary = '';
                    $aliases = [];
                    foreach ($el->xpath('.//*[local-name()="Name"]') ?: [] as $nEl) {
                        $full = trim(implode(' ', array_filter([
                            trim((string) ($nEl->Name1 ?? '')),
                            trim((string) ($nEl->Name2 ?? '')),
                            trim((string) ($nEl->Name3 ?? '')),
                            trim((string) ($nEl->Name4 ?? '')),
                            trim((string) ($nEl->Name5 ?? '')),
                            trim((string) ($nEl->Name6 ?? '')),
                        ], static fn(string $v): bool => $v !== '')));
                        if ($full === '') {
                            continue;
                        }
                        $nameType = strtolower(trim((string) ($nEl->NameType ?? '')));
                        if ($nameType === 'primary name' && $primary === '') {
                            $primary = $full;
                        } else {
                            $aliases[] = $full;
                        }
                    }
                    if ($primary === '' && !empty($aliases)) {
                        $primary = array_shift($aliases);
                    }
                    if ($uid === '' || $primary === '') {
                        unset($xml, $el);
                        continue;
                    }
                    $aliases = array_values(array_unique(array_filter(
                        $aliases,
                        static fn(string $a): bool => $a !== $primary
                    )));
                    $regime = trim((string) ($el->RegimeName ?? 'UK Sanctions List (FCDO/OFSI)'));
                    $listedDate = trim((string) ($el->DateDesignated ?? ''));
                    $grouped[$uid] = [
                        'uid' => $uid,
                        'source' => 'uk_ofsi',
                        'source_label' => 'UK Sanctions List (FCDO/OFSI)',
                        'name' => $primary,
                        'aliases' => $aliases,
                        'program' => $regime !== '' ? $regime : 'UK Sanctions List (FCDO/OFSI)',
                        'listed_date' => $listedDate !== '' ? $listedDate : null,
                        'jurisdiction' => 'UK',
                    ];
                    unset($xml, $el);
                }
            }
        } finally {
            $errs = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrors);
            $reader->close();
            if (!empty($errs) && empty($grouped)) {
                throw new RuntimeException('XML UK OFSI malformado: ' . trim($errs[0]->message));
            }
        }

        foreach ($grouped as &$g) {
            $g['aliases'] = array_values(array_unique($g['aliases']));
        }
        unset($g);

        return ['records' => array_values($grouped), 'addresses' => []];
    }

    // Fallback CSV streaming
    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        throw new RuntimeException('Nao foi possivel abrir CSV UK OFSI.');
    }
    $headers = null;
    $grouped = [];
    try {
        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
            if (!is_array($row) || count($row) < 3) {
                continue;
            }
            if ($headers === null) {
                $joined = implode(',', $row);
                if (stripos($joined, 'Name 1') !== false || stripos($joined, 'Group ID') !== false) {
                    $headers = array_map(static fn($h): string => strtolower(trim((string) $h)), $row);
                }
                continue;
            }
            $assoc = [];
            foreach ($headers as $idx => $colName) {
                $assoc[$colName] = trim((string) ($row[$idx] ?? ''));
            }
            $uid = $assoc['group id'] ?? $assoc['ofsi group id'] ?? '';
            $full = trim(implode(' ', array_filter([
                $assoc['name 1'] ?? '',
                $assoc['name 2'] ?? '',
                $assoc['name 3'] ?? '',
                $assoc['name 4'] ?? '',
                $assoc['name 5'] ?? '',
                $assoc['name 6'] ?? '',
            ], static fn(string $v): bool => $v !== '')));
            if ($uid === '' || $full === '') {
                continue;
            }
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'uid' => $uid,
                    'source' => 'uk_ofsi',
                    'source_label' => 'UK OFSI Consolidated List',
                    'name' => $full,
                    'aliases' => [],
                    'program' => ($assoc['regime'] ?? '') !== '' ? $assoc['regime'] : 'UK OFSI',
                    'listed_date' => ($assoc['listed on'] ?? '') !== '' ? $assoc['listed on'] : null,
                    'jurisdiction' => ($assoc['country'] ?? '') !== '' ? $assoc['country'] : 'UK',
                ];
            } else {
                $grouped[$uid]['aliases'][] = $full;
            }
        }
    } finally {
        fclose($fp);
    }

    return ['records' => array_values($grouped), 'addresses' => []];
}

function grid402_sanctions_remove_dir_recursive(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = @scandir($dir);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . '/' . $item;
            if (is_dir($full)) {
                grid402_sanctions_remove_dir_recursive($full);
            } else {
                @unlink($full);
            }
        }
    }
    @rmdir($dir);
}

/**
 * Compila e promove atomicamente o indice particionado de 4 blocos em disco.
 *
 * @param array<string, array{records: array, addresses: array, authority: string, license: string, url: string, source_sha256: string, fetched_at: string}> $parsedBySource
 * @return array<string, mixed>
 */
function grid402_sanctions_build_index(array $parsedBySource, string $targetDir, ?string $syncedAt = null): array {
    $required = ['ofac_sdn', 'un_consolidated', 'eu_fsfi', 'uk_ofsi'];
    foreach ($required as $reqKey) {
        if (empty($parsedBySource[$reqKey]) || empty($parsedBySource[$reqKey]['records'])) {
            throw new RuntimeException("Fonte obrigatoria ausente ou vazia ao construir indice: {$reqKey}");
        }
    }

    $targetDir = rtrim($targetDir, '/\\');
    $parentDir = dirname($targetDir);
    if (!is_dir($parentDir) && !@mkdir($parentDir, 0775, true) && !is_dir($parentDir)) {
        throw new RuntimeException("Diretorio pai inacessivel para indice de sancoes: {$parentDir}");
    }

    $rand = bin2hex(random_bytes(5));
    $stagingDir = $targetDir . '.staging.' . $rand;
    if (!@mkdir($stagingDir, 0775, true)) {
        throw new RuntimeException("Falha ao criar diretorio staging: {$stagingDir}");
    }

    try {
        $addrShards = [];
        $entShards = [];
        $totalAddresses = 0;
        $totalEntities = 0;
        $sourcesMeta = [];
        $hashSeedParts = [];

        foreach ($required as $sourceKey) {
            $block = $parsedBySource[$sourceKey];
            $records = is_array($block['records'] ?? null) ? $block['records'] : [];
            $addrs = is_array($block['addresses'] ?? null) ? $block['addresses'] : [];

            $sourcesMeta[$sourceKey] = [
                'authority' => (string) ($block['authority'] ?? $sourceKey),
                'license' => (string) ($block['license'] ?? 'Official Public Data'),
                'url' => (string) ($block['url'] ?? ''),
                'records_count' => count($records),
                'addresses_count' => count($addrs),
                'source_sha256' => (string) ($block['source_sha256'] ?? ''),
                'fetched_at' => (string) ($block['fetched_at'] ?? gmdate('Y-m-d\TH:i:s\Z')),
            ];
            $hashSeedParts[] = $sourceKey . ':' . $sourcesMeta[$sourceKey]['source_sha256'] . ':' . count($records) . ':' . count($addrs);

            foreach ($addrs as $addrEntry) {
                $origAddr = trim((string) ($addrEntry['address'] ?? ''));
                if ($origAddr === '') {
                    continue;
                }
                $normAddr = strtolower($origAddr);
                $bucket = grid402_sanctions_addr_bucket($normAddr);
                $addrShards[$bucket][$normAddr][] = [
                    'uid' => (string) ($addrEntry['uid'] ?? ''),
                    'source' => $sourceKey,
                    'source_label' => (string) ($addrEntry['source_label'] ?? $sourcesMeta[$sourceKey]['authority']),
                    'entity' => (string) ($addrEntry['entity'] ?? ''),
                    'program' => (string) ($addrEntry['program'] ?? ''),
                    'asset' => (string) ($addrEntry['asset'] ?? 'CRYPTO'),
                    'original_address' => $origAddr,
                    'listed_date' => $addrEntry['listed_date'] ?? null,
                    'jurisdiction' => (string) ($addrEntry['jurisdiction'] ?? 'GLOBAL'),
                ];
                $totalAddresses++;
            }

            foreach ($records as $rec) {
                $name = trim((string) ($rec['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $normName = grid402_sanctions_normalize_text($name);
                $aliases = is_array($rec['aliases'] ?? null) ? $rec['aliases'] : [];
                $normAliases = [];
                $allTokenBuckets = [];

                foreach (grid402_sanctions_entity_tokens($normName) as $tok) {
                    $allTokenBuckets[grid402_sanctions_ent_bucket($tok)] = true;
                }
                foreach ($aliases as $al) {
                    $na = grid402_sanctions_normalize_text((string) $al);
                    if ($na !== '') {
                        $normAliases[] = $na;
                        foreach (grid402_sanctions_entity_tokens($na) as $tok) {
                            $allTokenBuckets[grid402_sanctions_ent_bucket($tok)] = true;
                        }
                    }
                }
                $normAliases = array_values(array_unique($normAliases));
                if (empty($allTokenBuckets)) {
                    $allTokenBuckets['_other'] = true;
                }

                $compactRec = [
                    'uid' => (string) ($rec['uid'] ?? ''),
                    'source' => $sourceKey,
                    'source_label' => (string) ($rec['source_label'] ?? $sourcesMeta[$sourceKey]['authority']),
                    'name' => $name,
                    'normalized_name' => $normName,
                    'aliases' => array_slice($aliases, 0, 25),
                    'normalized_aliases' => array_slice($normAliases, 0, 25),
                    'program' => (string) ($rec['program'] ?? ''),
                    'listed_date' => $rec['listed_date'] ?? null,
                    'jurisdiction' => (string) ($rec['jurisdiction'] ?? 'GLOBAL'),
                ];

                foreach (array_keys($allTokenBuckets) as $bucket) {
                    $entShards[$bucket][] = $compactRec;
                }
                $totalEntities++;
            }
        }

        foreach ($addrShards as $bucket => $map) {
            $json = json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json) || @file_put_contents($stagingDir . '/addr_' . $bucket . '.json', $json, LOCK_EX) === false) {
                throw new RuntimeException("Falha ao gravar shard addr_{$bucket}");
            }
        }
        foreach ($entShards as $bucket => $list) {
            $json = json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json) || @file_put_contents($stagingDir . '/ent_' . $bucket . '.json', $json, LOCK_EX) === false) {
                throw new RuntimeException("Falha ao gravar shard ent_{$bucket}");
            }
        }

        $syncedIso = $syncedAt ?? gmdate('Y-m-d\TH:i:s\Z');
        $datasetSha = hash('sha256', implode('|', $hashSeedParts));
        $manifest = [
            'schema_version' => 1,
            'synced_at' => $syncedIso,
            'dataset_sha256' => $datasetSha,
            'freshness_ttl_hours' => defined('GRID402_SANCTIONS_STALE_HOURS') ? (int) GRID402_SANCTIONS_STALE_HOURS : 72,
            'total_addresses' => $totalAddresses,
            'total_entities' => $totalEntities,
            'addr_shards_count' => count($addrShards),
            'ent_shards_count' => count($entShards),
            'sources' => $sourcesMeta,
        ];
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($manifestJson) || @file_put_contents($stagingDir . '/manifest.json', $manifestJson, LOCK_EX) === false) {
            throw new RuntimeException('Falha ao gravar manifest.json em staging.');
        }

        // Promocao atomica preservando o indice anterior em caso de erro
        $backupDir = $targetDir . '.old.' . $rand;
        $hadExisting = is_dir($targetDir);
        if ($hadExisting && !@rename($targetDir, $backupDir)) {
            throw new RuntimeException('Nao foi possivel mover o indice anterior para backup temporario.');
        }
        if (!@rename($stagingDir, $targetDir)) {
            if ($hadExisting && is_dir($backupDir)) {
                @rename($backupDir, $targetDir);
            }
            throw new RuntimeException('Falha ao promover diretorio staging para destino final.');
        }
        if ($hadExisting && is_dir($backupDir)) {
            grid402_sanctions_remove_dir_recursive($backupDir);
        }

        return $manifest;
    } catch (Throwable $e) {
        grid402_sanctions_remove_dir_recursive($stagingDir);
        throw $e;
    }
}
