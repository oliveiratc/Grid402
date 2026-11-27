# Tasks

## 1. Roteamento Dual-Facilitator e Conformidade Coinbase CDP Bazaar (RF01, RF02, RF10)

- [x] 1.1 Adicionar variáveis de roteamento Dual-Facilitator em `public_html/config.php` (`GRID402_FACILITATOR_ROUTING`, `GRID402_CDP_FACILITATOR_URL`, `GRID402_CDP_VERIFY_PATH`, `GRID402_CDP_SETTLE_PATH`, `GRID402_PAYAI_FACILITATOR_URL`, `GRID402_PAYAI_VERIFY_PATH`, `GRID402_PAYAI_SETTLE_PATH`) e atualizar `.env.example` sem expor segredos
- [x] 1.2 Implementar `grid402_resolve_facilitator_candidates()` e roteamento Dual-Facilitator em `public_html/facilitator.php`: Coinbase CDP primário na Base Mainnet (`eip155:8453`) quando credenciais CDP estiverem presentes + fallback automático para PayAI apenas em erro de infra/quota pré-`/settle`; PayAI direto para Polygon, Arbitrum e Solana; e **invariante anti-dupla-cobrança** mantendo nonce `pending` sem fallback quando ocorrer erro ambíguo/timeout em `/settle`
- [x] 1.3 Auditar e endurecer `public_html/x402.php` (`grid402_build_resource_info()` e `grid402_build_bazaar_extension()`) para conformidade estrita CDP Bazaar v2 (`url` HTTPS absoluta, `description <= 500` chars técnica sem texto de prompt-injection, schemas 2020-12 inlined sem `$ref`/`$id` externos)
- [x] 1.4 Criar o validador CLI `scripts/validate_cdp_bazaar.php` suportando modo offline (`--json`, `--endpoint`) e validação remota opcional (`--live` contra `https://api.cdp.coinbase.com/platform/v2/x402/validate`)

## 2. Manifesto Dinâmico `/.well-known/x402.json` e Prontidão Solana (RF03, RF04)

- [x] 2.1 Criar `public_html/well_known_x402.php` e adicionar a regra de rewrite em `public_html/.htaccess` (`^\.well-known/x402(?:\.json)?/?$ -> well_known_x402.php`) para servir o manifesto dinamicamente com CORS aberto, refletindo `GRID402_SOLANA_X402_ENABLED` e o mapa de facilitadores ativos por rede (mantendo `public_html/.well-known/x402.json` atualizado como fallback)
- [x] 2.2 Criar o script CLI `scripts/check_solana_readiness.php` (com `--json` e `--rpc-url`) para validar saldo SOL (`> 0` lamports) e existência da ATA SPL USDC (`GRID402_SOLANA_USDC_MINT`) da carteira `GRID402_SOLANA_WALLET_ADDRESS` usando RPCs saudáveis com fallback

## 3. Sincronização dos 4 Blocos Oficiais e Triagem Multi-Ativo em Sanções (RF05, RF06, RF07)

- [x] 3.1 Criar `public_html/services/sanctions_index.php` com leitor/escritor Zero-DB particionado (`manifest.json`, shards `addr_<xx>.json` e `ent_<xx>.json`), normalização de nomes/aliases, detecção de endereços multi-ativo (EVM, Solana, BTC Legacy/Bech32, Tron etc.), cálculo de `dataset_sha256` e status de frescor (`FRESH` / `STALE > 72h` / `MISSING`)
- [x] 3.2 Criar `scripts/sync_sanctions_index.php` com parsing em streaming (`XMLReader`/leitura incremental sem SQLite) das 4 listas públicas oficiais (**OFAC SDN `sdn_advanced.xml`**, **ONU Consolidated**, **EU FSFi** e **UK OFSI**), suporte `--from-dir` para fixtures offline e promoção atômica via diretório *staging* preservando o índice anterior em caso de falha
- [x] 3.3 Atualizar `public_html/services/ofac.php`, `public_html/services/kyb_audit.php` e o roteamento em `public_html/index.php` (`case 'ofac':`) para triagem multi-ativo contra o índice particionado, mantendo Base RPC para EVM e preço `0.0200 USDC`, com fail-closed HTTP `503` pré-cobrança sem índice, `STALE` > 72h, `dataset_metadata` + `legal_disclaimer`, `demo_subset` em sandbox e propagação segura de `503` em `/api/v1/kyb-audit`

## 4. Blindagem Jurídica e Operação 100% Transiente em `/api/v1/extract` (RF08, RF09)

- [x] 4.1 Implementar em `public_html/services/extract.php` a validação `robots.txt` (RFC 9309) sob o guarda anti-SSRF (timeout `<= 2s`, cache leve exclusivo das diretivas por host), bloqueando com HTTP `403` (`RobotsDisallowed`) quando proibido para `Grid402-Extract-Bot` ou `*`
- [x] 4.2 Implementar em `public_html/services/extract.php` a inspeção e bloqueio de `X-Robots-Tag` (`noai`, `noimageai`, `noindex`, `none`), `Content-Signal` (`ai-input=no`, `ai-train=no`), meta tags HTML restritivas (`DirectiveDisallowed`), HTTP `401`/`402`/`403`/`407` (`OriginAccessDenied`) e sinais estruturais de paywall (`PaywallDetected`)
- [x] 4.3 Remover integralmente leitura/gravação de cache em disco de texto/Markdown extraído em `public_html/services/extract.php`, garantindo `transient_only: true`, `cached: false` e `legal_disclaimer`

## 5. Telemetria Pública `/status` e `/trust` (RF11)

- [x] 5.1 Atualizar `public_html/collector.php` e `public_html/trust.php` para expor os metadados públicos do índice de sanções (`sanctions_index` sem caminhos internos) e documentar a política transiente + `robots.txt` do `/api/v1/extract`, preservando `25/25 UP`

## 6. Testes Automatizados, Homologação e Empacotamento (RF12)

- [x] 6.1 Criar `tests/test_phase1_liquidity_and_legal_hardening.php` cobrindo com fixtures e mocks offline determinísticos todos os cenários das delta specs (Dual-Facilitator CDP+PayAI, fallback no `/verify`, bloqueio de fallback em erro ambíguo no `/settle`, `validate_cdp_bazaar.php`, `well_known_x402.php` dinâmico com flag Solana on/off, `check_solana_readiness.php`, `sync_sanctions_index.php --from-dir`, preservação de índice em falha, `/api/v1/ofac` multi-ativo / fail-closed 503 pré-cobrança / `STALE` >72h / `demo_subset`, `/api/v1/kyb-audit` fail-closed, e `/api/v1/extract` `robots.txt` / `X-Robots-Tag` / `Content-Signal` / `<meta>` / paywall / zero arquivo de conteúdo em disco)
- [x] 6.2 Executar toda a bateria de homologação (`verify_storage_integrity.php`, `test_maturity_and_m2m_controls.php`, `test_concurrency_and_load.php`, `test_session_pass_statement.php`, `test_agentkit_integration.php`, `test_e2e_full.php`, `test_phase1_liquidity_and_legal_hardening.php`, `node mcp/test_mcp.mjs` e `npx -y @fission-ai/openspec@latest validate --all --strict`) e gerar o pacote `dist/grid402_hostinger_deploy.zip` via `scripts/package_deploy.ps1`
