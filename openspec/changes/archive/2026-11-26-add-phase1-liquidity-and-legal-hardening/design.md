# Design Técnico: Fase 1 — Liquidez Global e Blindagem Jurídica

## 1. Visão Geral da Arquitetura

Esta change mantém intacta a arquitetura **Zero-DB** do Grid402 (PHP 8.1+ em LiteSpeed/Hostinger e PHP CLI local sem exigir `sqlite3`, `pdo_sqlite` ou `mbstring`), evoluindo quatro pilares críticos de forma modular:

1. **Roteamento Dual-Facilitator (`public_html/facilitator.php`, `public_html/config.php`):** seleção por rede e fallback controlado exclusivamente até a etapa `/verify`.
2. **Conformidade CDP Bazaar e Manifesto Dinâmico (`public_html/x402.php`, `public_html/well_known_x402.php`, `scripts/validate_cdp_bazaar.php`, `scripts/check_solana_readiness.php`).**
3. **Motor Zero-DB de Sanções 4-Blocos (`public_html/services/sanctions_index.php`, `scripts/sync_sanctions_index.php`, `public_html/services/ofac.php`, `public_html/services/kyb_audit.php`).**
4. **Extração Web 100% Transiente com Respeito a Diretivas (`public_html/services/extract.php`).**

---

## 2. Roteamento Dual-Facilitator (`Coinbase CDP` + `PayAI`)

### 2.1 Variáveis de Configuração e Perfis
Em `public_html/config.php` (e documentado em `.env.example`), preservando 100% de retrocompatibilidade com perfis existentes (`GRID402_FACILITATOR_MODE`, `GRID402_FACILITATOR_URL`):
- `GRID402_FACILITATOR_ROUTING`: `auto` (padrão) ou `single`.
- `GRID402_CDP_FACILITATOR_URL`: padrão `https://api.cdp.coinbase.com/platform`.
- `GRID402_CDP_VERIFY_PATH`: padrão `/v2/x402/verify`.
- `GRID402_CDP_SETTLE_PATH`: padrão `/v2/x402/settle`.
- `GRID402_PAYAI_FACILITATOR_URL`: padrão `https://facilitator.payai.network`.
- `GRID402_PAYAI_VERIFY_PATH`: padrão `/verify`.
- `GRID402_PAYAI_SETTLE_PATH`: padrão `/settle`.

### 2.2 Política de Roteamento (`grid402_resolve_facilitator_candidates`)
Dado `$networkV2` e `GRID402_X402_NETWORK`:
- Se `GRID402_X402_NETWORK === 'base-sepolia'` ou `GRID402_FACILITATOR_ROUTING === 'single'` ou houver URL customizada explícita diferente dos padrões PayAI/CDP → retorna apenas o perfil único configurado (comportamento legado).
- Caso contrário (`auto` em produção):
  - Se `$networkV2 === 'eip155:8453'` (Base Mainnet) e `GRID402_CDP_API_KEY_ID !== ''` e `GRID402_CDP_API_KEY_SECRET !== ''`:
    1. Primário: `cdp` (`GRID402_CDP_FACILITATOR_URL`, `auth_mode: 'cdp'`)
    2. Fallback: `payai` (`GRID402_PAYAI_FACILITATOR_URL`, `auth_mode: 'public'`)
  - Se `$networkV2 === 'eip155:8453'` sem credenciais CDP → `payai`.
  - Se `$networkV2` for `eip155:137` (Polygon), `eip155:42161` (Arbitrum) ou `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` (Solana) → `payai`.

### 2.3 Invariante Anti-Dupla-Cobrança (`/verify` vs. `/settle`)
- **Na etapa `grid402_verify_exact_payment()`:**
  - O sistema tenta `/verify` no primeiro candidato.
  - Se retornar `$verify['unavailable'] === true` (timeout, falha de transporte, HTTP `5xx`, `401`, `403`, `408`, `429` ou `free_tier_exhausted`), tenta o candidato de fallback automaticamente.
  - Se retornar `unavailable === false` e `isValid === false` (rejeição por mérito da assinatura/saldo/autorização), libera o nonce e retorna `402` imediatamente **sem** tentar fallback.
  - O perfil que aprovou `/verify` é salvo em `_settle_ctx['facilitator']`.
- **Na etapa `grid402_settle_exact_payment()`:**
  - Chama `/settle` **exclusivamente** no perfil gravado em `_settle_ctx['facilitator']`.
  - Se `/settle` retornar `unavailable === true` (timeout, erro 5xx, resposta sem hash `transaction`), marca o nonce como `pending` em `used_nonces.json` e retorna `503` com `Retry-After: 30`. **Nunca** aciona outro facilitador no `/settle`, eliminando o risco de liquidação duplicada.

---

## 3. Conformidade `extensions.bazaar` v2 e Validador CLI

- Em `public_html/x402.php`:
  - `grid402_build_resource_info()` garante `url` HTTPS absoluto, `description` estritamente técnica e truncada/limitada a `<= 500` caracteres, livre de frases imperativas ou instruções direcionadas a agentes.
  - `grid402_build_bazaar_extension()` emite apenas JSON Schema 2020-12 inline (sem `$ref` ou `$id` externos).
- Em `scripts/validate_cdp_bazaar.php`:
  - Inspeciona todos os endpoints pagos do catálogo (`grid402_get_utility_services()` excluindo gratuitos), simulando o bundle `PAYMENT-REQUIRED` v2 e verificando: URL HTTPS absoluta, descrição `<= 500` chars, ausência de padrões comuns de prompt-injection, ausência de `$ref`/`$id` externos, presença de `input` e `output` válidos.
  - Com `--live`, envia `POST https://api.cdp.coinbase.com/platform/v2/x402/validate` com `{ "resource": ..., "method": "GET" }` (ou `POST` quando aplicável) e consolida resultado por rota (`--endpoint=<slug>` opcional para teste pontual).

---

## 4. Manifesto Dinâmico `/.well-known/x402.json` e Prontidão Solana

- **`public_html/.htaccess`:** adiciona regra antes do fallback estático:
  `RewriteRule ^\.well-known/x402(?:\.json)?/?$ well_known_x402.php [L]`
- **`public_html/well_known_x402.php`:**
  - Define headers `Content-Type: application/json; charset=utf-8`, `Access-Control-Allow-Origin: *` e `Cache-Control: no-cache, must-revalidate`.
  - Monta o catálogo de recursos pagos usando a tabela canônica de preços (`config.php`) e `grid402_build_accepts($priceRawUnits)`, refletindo dinamicamente `GRID402_SOLANA_X402_ENABLED`.
  - Expõe `networks`, `facilitator` (primário compatível) e `facilitators` (mapa por rede: Base → CDP quando ativo + PayAI fallback; Polygon/Arbitrum/Solana → PayAI).
  - O arquivo estático `public_html/.well-known/x402.json` permanece no repositório atualizado para ferramentas que o leiam direto em disco.
- **`scripts/check_solana_readiness.php`:**
  - Consulta RPCs públicos saudáveis da Solana Mainnet (priorizando `GRID402_SOLANA_RPC_URL` / `GRID402_RPC_SOLANA` e fallbacks públicos como `https://api.mainnet-beta.solana.com` e `https://solana.drpc.org`).
  - Executa `getBalance` (verifica `lamports > 0`) e `getTokenAccountsByOwner` com filtro `{ "mint": GRID402_SOLANA_USDC_MINT }` (verifica existência da ATA SPL USDC para `GRID402_SOLANA_WALLET_ADDRESS`).
  - Emite saída legível e modo `--json` (`ready`, `sol_lamports`, `ata_exists`, `ata_accounts`, `rpc_used`, `next_step`), saindo com `0` se pronto ou `1` caso falte SOL ou ATA.

---

## 5. Índice Zero-DB Particionado de Sanções (4 Blocos Oficiais)

### 5.1 Por que Índice JSON Particionado (e não SQLite)
A verificação no ambiente PHP local confirmou que `sqlite3` e `pdo_sqlite` não estão carregados por padrão, enquanto `XMLReader`, `SimpleXML`, `zlib`, `curl` e `openssl` estão ativos. O particionamento em shards JSON garante compatibilidade universal (Hostinger e PHP CLI Windows/Linux) mantendo lookup `<= 30 ms` e consumo de memória `< 64 MB` no sincronizador.

### 5.2 Estrutura em Disco (`GRID402_PRIVATE_DIR/sanctions_index/`)
- `manifest.json`:
  - `schema_version`: `1`
  - `synced_at`: ISO 8601 UTC
  - `dataset_sha256`: SHA-256 determinístico calculado sobre os hashes individuais e contagens das 4 listas
  - `freshness_ttl_hours`: `72`
  - `total_addresses`: inteiro
  - `total_entities`: inteiro
  - `sources`: mapa com `ofac_sdn`, `un_consolidated`, `eu_fsfi`, `uk_ofsi` contendo `url`, `license`, `authority`, `records_count`, `addresses_count`, `source_sha256`, `fetched_at`
- `addr_<xx>.json` (`00`–`ff`, 256 buckets pelo primeiro byte do `sha256` do endereço normalizado):
  - Dicionário `normalized_address => [ { uid, source, entity, program, asset, listed_date, jurisdiction } ]`
- `ent_<xx>.json` (buckets pelos 2 primeiros caracteres `[a-z0-9]` de cada token indexável `>= 3` chars do nome canônico e aliases, mais `_other`):
  - Lista de registros `[ { uid, name, normalized_name, aliases, normalized_aliases, source, program, listed_date, jurisdiction } ]`

### 5.3 Sincronizador Streaming (`scripts/sync_sanctions_index.php` + `public_html/services/sanctions_index.php`)
- Baixa as 4 fontes públicas oficiais (ou lê de `--from-dir=<path>` em testes offline determinísticos):
  1. **OFAC SDN (`sdn_advanced.xml`):** URL oficial do Tesouro EUA (domínio público `17 U.S.C. § 105`), parse via `XMLReader` lendo `DistinctParty`, nomes/aliases (`NamePartValue`), programas (`SanctionsProgram`) e `FeatureType` de `Digital Currency Address` (extraindo ativo e endereço cripto).
  2. **ONU Consolidated (`consolidated.xml`):** URL oficial UN Security Council, parse via `XMLReader` lendo `INDIVIDUAL` e `ENTITY` + `ALIAS` + `UN_LIST_TYPE`.
  3. **EU Financial Sanctions (`FSFi` XML):** URL oficial da Comissão Europeia, parse via `XMLReader` lendo `sanctionEntity` + `nameAlias` + `regulation`.
  4. **UK OFSI Consolidated List (XML/CSV):** URL oficial HM Treasury / OFSI (OGL v3.0), parse via streaming lendo `Name` / `Alias` / `GroupType` / `Regime`.
- Monta tudo em `sanctions_index.staging.<rand>/`, valida `total_entities > 0` e cada um dos 4 blocos presentes com `records_count > 0`, e executa troca atômica (`sanctions_index.old.<rand>` → `rename` staging → `sanctions_index` → remove `.old`). Se qualquer download ou XML falhar, aborta e mantém o índice anterior intacto.

### 5.4 Comportamento de `/api/v1/ofac` e `/api/v1/kyb-audit`
- **Detector Multi-Ativo (`grid402_ofac_detect_address_type`):**
  - EVM: `^0x[a-fA-F0-9]{40}$` (normaliza lowercase)
  - BTC Bech32: `^bc1[a-zA-Z0-9]{25,62}$` (normaliza lowercase)
  - BTC Legacy/P2SH: `^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$` (preserva case)
  - Solana Base58: `^[1-9A-HJ-NP-Za-km-z]{32,44}$` (preserva case)
  - Tron: `^T[1-9A-HJ-NP-Za-km-z]{33}$` (preserva case)
  - Outros formatos longos presentes no índice (XRP `r...`, LTC `L...`/`M...`/`ltc1...`, XMR etc.)
  - Atualiza `public_html/index.php` (`case 'ofac':`) para passar endereços multi-ativo no parâmetro `$addrVal` (não apenas `0x...`).
- **Fail-Closed & Freshness (`grid402_service_ofac($address, $entityName, $isSandbox)`):**
  - Modo produção (`!$isSandbox`) sem índice válido em `GRID402_PRIVATE_DIR/sanctions_index/manifest.json`: retorna `['error' => true, 'code' => 503, 'error_code' => 'SanctionsIndexUnavailable', ...]`. O porteiro em `public_html/index.php` (linhas 1990–2005) automaticamente aborta o `/settle` ou estorna créditos de `Session Pass`.
  - Modo sandbox (`$isSandbox === true`) sem índice local: usa `demo_subset` embutido, marcando `dataset_metadata.dataset_mode = 'demo_subset'` e `freshness_status = 'DEMO_SUBSET'`.
  - Índice presente: verifica `synced_at`; se idade `> 72h`, marca `freshness_status = 'STALE'` e `stale_dataset_warning = true`; caso contrário `FRESH` e `false`.
  - Inclui sempre `dataset_metadata` (`dataset_sha256`, `last_synced_at`, `lists_counts`, `freshness_status`, `dataset_mode`) e `legal_disclaimer` na resposta.
  - `grid402_service_kyb_audit(..., bool $isSandbox = false)` repassa `$isSandbox` para `grid402_service_ofac()`, aceita alvos multi-ativo em `evmAddress`/`target` e, se `ofacData` retornar `error: true` com `code: 503`, propaga erro `503` pré-cobrança sem mascarar como `CLEAR`.

---

## 6. Blindagem Jurídica e Transiência do `/api/v1/extract`

Em `public_html/services/extract.php`:
1. **Remoção do cache de conteúdo em disco:** eliminar leitura e gravação de `extract_<hash>.json`. A resposta passa a incluir `'cached' => false`, `'transient_only' => true` e `'legal_disclaimer' => 'Transient technical transformation of publicly accessible content; Grid402 stores no third-party page content on disk and does not grant copyright licenses.'`.
2. **Checagem `robots.txt` (RFC 9309) sob SSRF Guard:**
   - Função `grid402_extract_check_robots($scheme, $host, $port, $path, $dnsResolver, $robotsFetcher)`:
   - Valida a URL `<scheme>://<host>[:<port>]/robots.txt` com `grid402_extract_validate_public_url()` e `CURLOPT_RESOLVE` + timeout `2s`.
   - Armazena **exclusivamente** as diretivas extraídas de `robots.txt` em `GRID402_PRIVATE_DIR/robots_cache_<sha256>.json` (TTL `3600s`; zero conteúdo de páginas HTML/artigos).
   - Avalia grupos `User-agent: Grid402-Extract-Bot` (precedência específica RFC 9309) e fallback `User-agent: *` usando regra de *longest match* (`Allow` vence `Disallow` em empate de comprimento).
   - Se bloqueado → retorna `code: 403, error: 'RobotsDisallowed'`.
   - Se `robots.txt` retornar HTTP `404` ou `410` → permitido conforme RFC 9309. Se retornar HTTP `429` ou `5xx` → retorna `code: 503, error: 'RobotsUnavailable'` (comportamento seguro para não contornar indisponibilidade temporária).
3. **Bloqueio de Cabeçalhos, Meta Tags e Paywalls:**
   - Captura todos os headers `X-Robots-Tag` e `Content-Signal` no `CURLOPT_HEADERFUNCTION`.
   - Se HTTP de destino for `401`, `402`, `403` ou `407` → retorna `code: 403, error: 'OriginAccessDenied'`.
   - Se `X-Robots-Tag` contiver `noai`, `noimageai`, `noindex` ou `none` (global ou direcionado a `Grid402-Extract-Bot`), ou `Content-Signal` contiver `ai-input=no` ou `ai-train=no` → retorna `code: 403, error: 'DirectiveDisallowed'`.
   - Se HTML contiver `<meta name="robots|googlebot|grid402-extract-bot" content="...">` com `noai`, `noimageai`, `noindex` ou `none` → retorna `code: 403, error: 'DirectiveDisallowed'`.
   - Se HTML contiver sinais inequívocos de paywall (JSON-LD `"isAccessibleForFree": false` ou `"false"`, `<meta name="paywall" ...>`, `<meta property="article:content_tier" content="locked|metered">`) → retorna `code: 403, error: 'PaywallDetected'`.
   - Em todos esses bloqueios (`code !== 200`), o porteiro em `public_html/index.php` impede `/settle` e estorna `Session Pass`.

---

## 7. Telemetria `/status`, `/trust` e `.env.example`

- `public_html/services/sanctions_index.php` expõe `grid402_sanctions_index_summary()` (metadados públicos seguros sem vazar caminhos internos).
- `public_html/collector.php` inclui `sanctions_index` em `summary_free.json` (mantendo execução rápida sem I/O pesado; apenas lê `manifest.json` se existir).
- `public_html/trust.php` exibe:
  - Status vivo do índice de sanções (4 blocos oficiais, `last_synced_at`, `freshness_status`, `dataset_sha256` resumido e disclaimer).
  - Política pública do `/api/v1/extract`: operação 100% transiente (sem armazenamento de texto de terceiros em disco), respeito a `robots.txt` (RFC 9309), `X-Robots-Tag`, `Content-Signal` e bloqueio de paywalls.
- `.env.example` documenta `GRID402_FACILITATOR_ROUTING`, `GRID402_CDP_FACILITATOR_URL`, `GRID402_CDP_VERIFY_PATH`, `GRID402_CDP_SETTLE_PATH`, `GRID402_PAYAI_FACILITATOR_URL`, `GRID402_SOLANA_RPC_URL` e `GRID402_SANCTIONS_STALE_HOURS`.
