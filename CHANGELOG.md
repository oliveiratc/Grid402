# Changelog — Grid402 (`grid402.online`)

Todas as mudanças relevantes de arquitetura, catálogo de serviços, segurança criptográfica e política de preços do **Grid402** são documentadas neste arquivo, seguindo o padrão [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e [Semantic Versioning](https://semver.org/lang/pt-BR/).

---

## [Unreleased]

### Alterado
- **Passo 3 — Rebalanceamento da Tabela de Créditos (`/cnpj`, `/lei` → `2 cr`; `/kyb-audit` → `5 cr`; `/ofac` mantido em `4 cr`):**
  - **Gateway, Catálogo e Telemetria (`public_html/config.php`, `index.php`, `admin.php`, `metrics.php`, `catalog.php`, `agents.php`, `mcp.php`, `seo_landing.php`, `services/pass_repository.php`, `i18n.js?v=3.4.3`):** Reduzidos `/api/v1/cnpj` e `/api/v1/lei` de `0.0200 USDC` (`4 cr`) para **`0.0100 USDC` (`2 cr`)** (`R$ 0,038` a `R$ 0,060`/CNPJ no Pass; margem >83% pós-PayAI), mantido `/api/v1/ofac` em **`0.0200 USDC` (`4 cr`)** e ajustado o bundle composto `/api/v1/kyb-audit` (CNPJ + OFAC + LEI) para **`0.0250 USDC` (`5 cr`)**, eliminando canibalização interna (37,5% de desconto sobre comprar os 3 separados = `8 cr`).
  - **Manifestos e Discovery (`public_html/.well-known/x402.json`, `.well-known/mcp*.json`, `openapi.json`, `docs/revenue/openapi/*.json`, `llms.txt`, `llms-full.txt`, `mcp/tools.mjs`, `README.md`, `SKILL.md`, `references/catalog-and-pricing.md`, `openspec/specs/`):** Sincronizados 100% dos preços, `accepts[].amount` multi-chain e specs RapidAPI 3.0.3 regeneradas.
- **Passo 2 — Escada de Desconto no Pro Pass (`1.000 → 1.200 créditos`) e Validade Estendida (`180 / 365 dias`):**
  - **Motor de Emissão e Checkout (`public_html/services/pass_repository.php`, `pass_checkout.php`, `mercadopago.php`, `growth.php`):** Pro Pass (`5 USDC` / `R$ 29`) agora emite **`1.200 créditos`** (`1.000 base + 200 bônus (+20%)`, desconto efetivo de `16,7%`), criando progressão real entre Starter (`0%`) e Fleet (`33,3%`). Validade ampliada para **180 dias** (Starter, Pro e Standard) e **365 dias** (Fleet) apenas para novas emissões, sem alterar passes já emitidos.
  - **UI, Comparador ROI, Discovery e MCP (`index.php`, `pass.php`, `catalog.php`, `dossie_kyb.php`, `seo_landing.php`, `x402.php`, `mcp.php`, `i18n.js?v=3.4.2`, `llms.txt`, `llms-full.txt`, `openapi.json`, `.well-known/mcp*.json`, `mcp/tools.mjs`, `README.md`):** Sincronizados créditos do Pro (`1.200`), presets do simulador ROI (`40 / 120 / 300 / 600` dossiês) e novas validades (`180d / 365d`).
  - **Specs e Testes (`openspec/specs/`, `tests/test_web3_tiers_pass.php`, `test_maturity_and_m2m_controls.php`, `test_mercadopago_checkout.php`, `test_payment_audit_fixes.php`, `test_growth.php`):** Atualizados cenários e asserções cobrindo `1.200 créditos` no Pro e validades `~180d` / `~365d`.
- **Passo 1 — Higiene de Preços, Credibilidade do Comparador ROI, Garantias Pró-Cliente e Proteção Anti-Arbitragem RapidAPI:**
  - **Comparador ROI verificável (`public_html/index.php` + `public_html/assets/js/i18n.js?v=3.4.1`):** Substituído o simulador genérico de CNPJ por comparativo auditável do **Dossiê 360º KYB (`/api/v1/dossier`)** frente a tabelas públicas verificáveis (`Infosimples`, `Direct Data`, `OpenSanctions`, `1Lookup`, referência Nov/2026), com presets reais de pacotes (`40 / 100 / 300 / 600` dossiês/mês) e nota transparente de escopo.
  - **Garantias pró-cliente em destaque (`public_html/pass.php`, `catalog.php`, `dossie_kyb.php`, `i18n.js`):** Adicionado bloco explícito comunicando estorno automático em qualquer falha (`HTTP != 200`), retentativa gratuita por 300s (`Idempotency-Key`) e regra transparente de consumo em créditos (`1 cr` padrão vs. `2–10 cr` bundles).
  - **Sincronização de preços e Fleet Pass (`public_html/x402.php`, `index.php`, `seo_landing.php`, `services/mercadopago.php`, `.agents/skills/grid402/SKILL.md`, `docs/revenue/rapidapi-pack.md`):** Corrigido resíduo `Pro R$ 28 / Fleet R$ 110` para `Pro R$ 29 / Fleet R$ 115` em `x402.php`, alinhado descritivo do Fleet Pass para `4.000 base + 2.000 bônus = 6.000 créditos` e unificados os planos RapidAPI (`1.500 / 6.000 / 25.000` chamadas padrão + cotas dedicadas para `/dossier` e `/compliance`).
  - **Trava suave anti-arbitragem RapidAPI (`public_html/config.php`, `public_html/index.php`, `.env.example`, `tests/test_revenue_engine_v4.php`):** Adicionadas salvaguardas opt-in `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` (teto diário UTC por assinante com `flock(LOCK_EX)`, HMAC Zero-PII de `X-RapidAPI-User`, `HTTP 429` + `Retry-After` e estorno automático em erro) e `GRID402_RAPIDAPI_BLOCKED_ENDPOINTS` (`HTTP 403`).

### Corrigido
- **Alinhamento sistêmico e documental (`25` micro-serviços, `27` tools MCP `v2.0.0`, Sandbox `3 req/dia`, tarifas de Session Pass e UI i18n):**
  - `public_html/services/pass_repository.php`: corrigida a tabela `credit_rates` retornada em `issuePass()` (`domain-audit` e `market-snapshot` movidos de 1 para 3 créditos; incluídos `extract` e `email-verify` em 1 crédito e `dossier` em 10 créditos).
  - `public_html/index.php` e `public_html/assets/js/i18n.js`: corrigida a leitura de quota de Sandbox no Playground (`quota_remaining_today` / `quota_limit_per_day` = 3 em vez dos campos legados por hora), adicionados presets/ações de `extract`, `email-verify` e `dossier` ao Playground e Console, preservado o badge multi-chain (`Base · Polygon · Arbitrum · Solana • 25/25`) no `setLanguage()`, atualizado cache-buster `i18n.js?v=3.4.0` e sincronizadas todas as strings PT/EN (`25` serviços, `27` ferramentas MCP, `3 consultas/dia`).
  - `public_html/badge.php` e `public_html/catalog.php`: `/badge/system.svg` atualizado de `6/6 ONLINE` estático para contagem dinâmica do catálogo (`25/25 ONLINE`) e incluído fallback de preço para `dossier` (`0.0500`) em `catalog.php`.
  - `.agents/skills/grid402/SKILL.md`, `references/catalog-and-pricing.md`, `mcp/README.md`, `mcp/smithery.yaml`, `README.md`, `integrations/`, `sdk/`, `docs/` e `.github/workflows/ci.yml`: sincronizados com 25 serviços, 27 ferramentas MCP (`v2.0.0`), Sandbox `3 req/dia` e links `/pass`.
- **Card mcprush (`27 tools` · `v2.0.0`), selo SealKeeper e manifesto `server.json` (`public_html/index.php`, `public_html/assets/mcprush-card.svg`, `public_html/mcp.php`, `mcp/tools.mjs`, `server.json`, `README.md`):** Atualizado `server.json` de `v1.9.1` (`24 MCP tools`) para `v2.0.0` (`27 MCP tools`), removidos exemplos de URL/e-mail dos schemas de `grid402_extract` e `grid402_email_verify` que acionavam retenção preventiva (`mcp_change_unresolved`) no scanner do SealKeeper, gerado card SVG local `public_html/assets/mcprush-card.svg` refletindo `27 tools` e `v2.0.0` e atualizado o selo SealKeeper/PayCensus/RapidAPI na Home e no `README.md`.

### Adicionado
- **Exibição dupla de horário `BRT (UTC-3)` + `UTC` no `/admin` (`public_html/admin.php`):** As tabelas **Diagnóstico Real de Falhas de Pagamento** e **Feed de Consultas Recentes** agora exibem o horário de Brasília (`America/Sao_Paulo`, ex.: `21/11 10:14:27 BRT`) como linha principal e o timestamp `UTC` original como linha secundária tanto no SSR quanto no polling em tempo real.
- **Integração com endpoint oficial `GET /api/verify/addr-proof-message` da PayCensus (`scripts/paycensus_bind_evm.mjs`, `scripts/paycensus_bind_solana.py`):** Ambos os scripts agora buscam programaticamente a mensagem UTF-8 canônica em `https://trust.paycensus.com/api/verify/addr-proof-message?vid=12` antes do fallback para DNS TXT / nonce estático, prevenindo divergências de transcrição manual.
- **Suporte multi-API para `X-RapidAPI-Proxy-Secret` e specs OpenAPI `3.0.3` (`public_html/config.php`, `public_html/admin.php`, `scripts/generate_rapidapi_openapi.mjs`):** `grid402_resolve_rapidapi_proxy_secret()` e `grid402_is_valid_rapidapi_proxy_request()` agora combinam múltiplos segredos do `.env` e de `rapidapi_config.json` (separados por vírgula/quebra de linha, até 10 APIs simultâneas) via `hash_equals`; painel `/admin` aceita e contabiliza múltiplas APIs (`ativo (N APIs)`). Geradas specs OpenAPI `3.0.3` compatíveis com RapidAPI Studio em `docs/revenue/openapi/` (`rapidapi-global.json`, `rapidapi-brazil-kyb.json` e `rapidapi-all.json`).
- **Diagnóstico real de falhas de pagamento no `/admin` (`public_html/logger.php` + `public_html/admin.php`):** Adicionado leitor Zero-PII `grid402_read_recent_payment_failures()` sobre `requests.jsonl` e painel interativo **Diagnóstico Real de Falhas de Pagamento** exibindo os motivos exatos das últimas 25 tentativas rejeitadas (sem desafios 402 vazios).

### Corrigido
- **Parsing tolerante de provas e headers x402 (`public_html/facilitator.php`, `x402.php`, `index.php`, `mcp.php`):** Suporte a prefixos (`x402 `, `Payment `, `Bearer `) em `X-Payment`, `PAYMENT-SIGNATURE` e `Authorization`, decodificação de Base64 com quebras de linha/espaços ou objeto JSON cru, `signature` / `nonce` sem `0x` e valores `uint256` em decimal (até 78 dígitos) ou hex (`0x...`) sem overflow em 64 bits.
- **Navegação Web3 em `/pass` e query `?cep=` em `/api/v1/cep` (`public_html/pass.php`, `public_html/index.php`):** Botões *Pay with USDC (Web3)* em `/pass` corrigidos de `/#topup` (inexistente) para `#multichain-web3`, e `/api/v1/cep` passou a aceitar `?cep=` além de `?code=` após pagamento.

### Adicionado (anteriores)
- **Configuração persistente do RapidAPI Bridge no `/admin` (`public_html/config.php` + `public_html/admin.php`):** Adicionado suporte a `grid402_storage/.env` no carregador de ambiente, helpers `grid402_rapidapi_config_path()` / `grid402_save_rapidapi_config()` / `grid402_resolve_rapidapi_proxy_secret()` / `grid402_rapidapi_runtime_status()` gravando `rapidapi_config.json` fora do DocumentRoot (imune a deploys na Hostinger) e endpoint `POST /admin?action=save_rapidapi_config` com modal `⚡ Configurar RapidAPI` no `/admin`. Também corrigida a leitura de redes no badge do facilitador (`1 chains` → `4 chains` via `grid402_build_accepts()`).
- **Fluxo de prova criptográfica EVM no PayCensus Trust (`scripts/paycensus_bind_evm.mjs`):** Implementa assinatura EIP-191 (`personal_sign`) da mensagem `paycensus-addr-proof=<nonce>` para envio a `trust.paycensus.com/api/verify/addr-proof`, com resolução automática do nonce via DNS TXT `_paycensus.<domain>`, interface web local temporária (`--browser` em `127.0.0.1` para MetaMask/Rabby/Ledger sem chave em disco), suporte a `--signature`, `--key-file` e `GRID402_EVM_SIGNING_KEY`, modo `--dry-run` e suíte de testes em `tests/test_paycensus_bind_evm.mjs`.
- **Vinculação de endereço Solana ao perfil PayCensus Trust (`scripts/paycensus_bind_solana.py`):** assina a mensagem exata `paycensus-addr-proof=paycensus-verify=<nonce>` (UTF-8, vid=12) com keypair ed25519 e POSTa em `trust.paycensus.com/api/verify/addr-proof-solana`. O endereço `2omGBXvZgbnzq1mbcqSYcJAouSqNjSqRTwzNHKxGkd29` passou a constar com `proven=1` em `trust.paycensus.com/api/profile?domain=grid402.online`, tornando o perfil do grid402.online multi-chain verificado. A seed do keypair fica apenas em `GRID402_SOLANA_SIGNING_KEY` no `.env` (gitignored); para vincular a wallet de produção `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C` (payTo do `.well-known/x402.json`), reutilizar o mesmo script com a chave real via `--keypair` ou env. O script aceita `--nonce`/`GRID402_PAYCENSUS_NONCE` (hex do portal, com ou sem o prefixo `paycensus-verify=`) e `--vid`/`GRID402_PAYCENSUS_VID`, carrega `.env` da raiz e de `private_data/.env` automaticamente e orienta a renovar o nonce quando o POST retorna `400`.

---

## [3.3.0] - 2026-11-16

### Adicionado
- **Extrato Zero-PII por Session Pass (`GET /api/v1/pass/statement` + `/payment-dashboard`):** Nova rota gratuita autenticável pelo próprio token `pass_g402_...` (via query `?token=`, `X-Payment` ou `Authorization: Bearer`), suportando filtros `?endpoint=` e `?outcome=` e exportação em `json` (padrão) ou `csv` (UTF-8 com BOM e sanitização OWASP anti Formula/CSV Injection). Retenção em ring buffer de até **200 lançamentos recentes por passe** cobrindo débitos (`200 consumed`), bloqueios de Spend Control (`403 endpoint_restricted`, `429 daily_limit_exceeded`) e replays idempotentes (`200 idempotent_replay`), estritamente sem query params ou IPs.
- **Abstração de Persistência Zero-DB (`PassStorageInterface`):** Criação de `public_html/services/pass_repository.php` com o contrato `PassStorageInterface` e implementação padrão `JsonFlockPassRepository` gravando extratos em `private_data/pass_statements.json` isolado de `session_passes.json` (sem deadlocks).
- **Suíte de Testes do Extrato e Repositório (`tests/test_session_pass_statement.php`):** 36 asserções cobrindo `PassStorageInterface`, ring buffer de `200` eventos, CSV injection, Zero-PII, filtros HTTP, interface `/payment-dashboard` e disputa concorrente multi-processo.

### Alterado
- **Descoberta M2M e Interface Bilíngue:** Atualização de `/payment-dashboard`, `i18n.js` (`PT`/`EN`), `/pass`, `openapi.json`, `llms.txt` e `llms-full.txt` para expor filtros, resumo financeiro e exportação CSV/JSON de extratos.
- **Publicação de Pacotes SDK:** Publicação das versões atualizadas `grid402@2.7.0` (npm) e `grid402@2.5.3` (PyPI), além do suporte a PyPI Trusted Publishing (OIDC) e actions v7 (Node 24) no `.github/workflows/ci.yml`.

### Corrigido
- **Leitura Segura sob `flock()` no Linux (`grid402_read_locked_json`):** Substituição das leituras baseadas em `filesize($file)` em cache (`statcache`) por `rewind($fp)` + `stream_get_contents($fp)` em `pass.php`, `x402.php`, `facilitator.php`, `growth.php`, `admin.php` e `metrics.php`, eliminando truncamento de JSON após crescimento de arquivo no mesmo processo PHP.
- **CommonJS SDK Test e CI em Checkout Limpo:** Correção de `TEST_ORACLE_PUBKEY` em `sdk/node/test_cjs.cjs`, ajuste de `scripts/verify_storage_integrity.php` para respeitar `GRID402_PRIVATE_DIR` e proteção `if (!defined('GRID402_EXEC'))` em `public_html/collector.php`.

---

## [3.2.0] - 2026-11-16

### Adicionado
- **Observabilidade Estruturada JSONL Zero-PII (`logger.php` + `X-Request-Id`):** Todas as respostas de API passam a emitir cabeçalho `X-Request-Id` para correlação ponta a ponta e registrar trilha sanitizada em `private_data/requests.jsonl` com rotação automática em `10 MB` (`.1`), utilizando prefixo HMAC de IP e sem armazenar tokens ou PII.
- **Telemetria de Latência P50/P95:** Armazenamento de janela circular de latências em `metrics.json` com cálculo de `latency_p95_ms` e exposição no `/admin` e `/trust`.
- **Instruções Nativas no Handshake MCP (`grid402-mcp` `1.8.2`):** Inclusão do campo `ServerOptions.instructions` em `mcp/index.mjs` e sincronização das **24 ferramentas** nos manifestos públicos `/.well-known/mcp.json` e `/.well-known/mcp/server-card.json`.
- **Suíte Real de Concorrência Paralela e Carga (`tests/test_concurrency_and_load.php`):** Validação multi-processo de disputa pelo último crédito de Session Pass, bloqueio concorrente de replay (`tx_hash` e `payer:nonce` via `flock`), rotação de logs e benchmark `p50`/`p95`/`p99`.

### Alterado
- **Dual-License Explícita (`LICENSE` e `README.md`):** Separação formal entre **MIT License** para SDKs, servidor MCP e integrações (`python/`, `mcp/`, `sdk/`, `integrations/`, `examples/`) e **Proprietary License** para a camada servidora (`public_html/`), alinhando o posicionamento principal *"The Verifiable Data and x402 Payment Layer for Autonomous AI Agents"*.
- **Extensão x402 v2 Bazaar:** Inclusão completa de `resource` e `extensions.bazaar` (com JSON Schema 2020-12) tanto nos desafios 402 quanto no payload enviado ao facilitador e no assinador Web3 do navegador.

### Corrigido
- **Higiene de URLs e Tarifas Legadas:** Remoção de ocorrências residuais de `grid402.com.br` (`examples/`) e `0.0005 USDC` (`sdk/` e `.env.example`), unificando todos os fluxos na tarifa oficial vigente (`0.0050` a `0.0300 USDC`).

---

## [3.1.0] - 2026-11-15

### Adicionado
- **Idempotência Financeira M2M (`Idempotency-Key`):** Suporte nativo ao cabeçalho `Idempotency-Key` (TTL de 300 segundos em `idempotency_cache.json`) em todas as rotas pagas `/api/v1/*`. Retentativas dentro da janela retornam o payload assinado original com `X-Idempotent-Replay: true` sem debitar créditos ou exigir novo pagamento.
- **Spend Controls por Session Pass:** Suporte a teto diário de créditos (`daily_credit_limit`, retornando HTTP 429 `PassDailyLimitExceeded` ao atingir o limite) e lista de endpoints permitidos (`allowed_endpoints`, retornando HTTP 403 `PassEndpointNotAllowed` fora do escopo).
- **Consulta Gratuita de Saldo (`GET /api/v1/pass/balance`):** Novo endpoint aberto sem cobrança para agentes consultarem `remaining_credits`, `tier`, `daily_credit_limit`, `credits_used_today`, `allowed_endpoints` e `expires_at` do seu `pass_g402_...`.
- **Metadados de Proveniência Assinados (`_meta`):** Todas as respostas HTTP 200 dos serviços pagos e de sandbox passam a incluir o bloco `_meta` (`service_id`, `upstream_source`, `fetched_at`, `cache_ttl_sec`, `operator`, `oracle_wallet`) coberto pelo digest canônico de `_oracle_proof`.
- **Série Histórica de Disponibilidade de 30 Dias (`uptime_history.json`):** Registro diário persistente de uptime e latência média exposto em `/status` (`uptime_30d`) e visualizado em `/trust`.
- **Governança Institucional e Segurança (`/.well-known/security.txt`):** Publicação de manifesto RFC 9116, identificação institucional do operador em `/trust`, matriz de proveniência/TTL das 22 fontes públicas e nota de desambiguação de marca (`grid402.online`).
- **Atestação de Proveniência CI/CD e Validação Zero-DB:** Configuração de npm `--provenance` e PyPI Trusted Publishing (PEP 740) nos workflows de publicação, além do utilitário `scripts/verify_storage_integrity.php`.

### Alterado
- **Unificação da Matemática Oficial do Session Pass:** Padronização em todos os canais (backend PHP, checkout Pix/Mercado Pago, Web3 Top-Up, `llms.txt`, `openapi.json`, `README.md` e SDKs) na razão **`1 crédito = 0.0050 USDC` (5.000 unidades brutas)**:
  - **Starter Pass (2 USDC / R$ 12):** `400 créditos` (validade 30 dias)
  - **Pro / Agent Pass (5 USDC / R$ 29):** `1.000 créditos` (validade 30 dias)
  - **Fleet / Enterprise Pass (20 USDC / R$ 115):** `5.000 + 1.000 bônus = 6.000 créditos` (validade 60 dias)
  - Consumo por chamada: Endpoints padrão (`0.0050 USDC`) = `1 crédito`; Simples/Sanctions (`0.0100 USDC`) = `2 créditos`; Domain Audit/Market Snapshot (`0.0120 USDC`) = `3 créditos`; CNPJ/LEI/OFAC/KYB Audit (`0.0200 USDC`) = `4 créditos`; Compliance 360 (`0.0300 USDC`) = `6 créditos`.
- **Paridade Completa do Servidor MCP (`grid402-mcp`):** Expansão de `mcp/tools.mjs` e `public_html/mcp.php` para cobrir todos os **22 serviços do catálogo** + ferramenta `grid402_pass_balance`, com descrições e schemas alinhados.
- **Acessibilidade para Crawlers de IA (`robots.txt`):** Permissão explícita RFC 9309 para `GPTBot`, `ChatGPT-User`, `OAI-SearchBot`, `ClaudeBot`, `Claude-Web`, `PerplexityBot` e `Google-Extended`, além de cabeçalhos CORS abertos em `/trust` e `/catalog`.

### Corrigido
- **Unificação de Endereço da Carteira Oráculo/Recebedora:** Remoção definitiva de qualquer referência residual ao endereço legado `0xa31c...356c`, consolidando `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` (`GRID402_WALLET_ADDRESS`) em 100% da plataforma.
- **Cobertura de Status Operacional (`22/22 UP`):** Sincronização integral de todos os 22 serviços entre `/status` e `/catalog`.

---

## [3.0.0] - 2026-11-10

### Adicionado
- **Endpoints Compostos de Alto Valor:**
  - `/api/v1/domain-audit` (`0.0120 USDC`): DNS + TLS/SSL + ASN/BGP em chamada única.
  - `/api/v1/kyb-audit` (`0.0200 USDC`): CNPJ + OFAC SDN + GLEIF LEI em chamada única.
  - `/api/v1/market-snapshot` (`0.0120 USDC`): Câmbio PTAX + Cripto Spot + Indicadores BCB em chamada única.
- **Facilitador x402 v2 (`exact` / EIP-3009):** Suporte nativo ao cabeçalho `PAYMENT-SIGNATURE` e descoberta no x402 Bazaar.
- **Checkout Híbrido BRL + Web3 (`/pass`):** Compra de Session Passes via Pix transparente e Cartão (Mercado Pago) além de USDC na Base.

---

## [2.4.0] - 2026-10-25

### Adicionado
- **Expansão Global M2M:**
  - `/api/v1/lei` (`0.0200 USDC`): Identidade Corporativa Global ISO 17442 via GLEIF (CC0 1.0).
  - `/api/v1/ofac` (`0.0200 USDC`): Triagem em listas de sanções OFAC SDN / ONU + telemetria AML Web3 na Base.
  - `/api/v1/notarize` (`0.0050 USDC`): Carimbo de tempo e certificado de existência SHA-256 + ECDSA secp256k1 Zero-Retention.
  - `/api/v1/global-markets` (`0.0050 USDC`): Calendário mundial de liquidação (NYSE, TARGET2, LSE, B3) + 30+ moedas do Banco Central Europeu.
- **Session Pass Pré-Pago (`/api/v1/pass`):** Liquidação em memória (`<15ms`) sem espera de bloco a cada requisição.

---

## [2.0.0] - 2026-10-01

### Adicionado
- **Suite de Compliance e Inteligência Financeira Brasileira:**
  - `/api/v1/compliance` (`0.0300 USDC`): Score 360 de integridade cadastral PJ.
  - `/api/v1/sanctions` (`0.0100 USDC`): Consulta aos cadastros CEIS e CNEP da CGU.
  - `/api/v1/simples` (`0.0100 USDC`): Enquadramento Simples Nacional e MEI.
  - `/api/v1/indicators`, `/api/v1/holidays`, `/api/v1/workdays` (`0.0050 USDC`).
- **Prova de Dados Criptográfica (`_oracle_proof`):** Assinatura determinística ECDSA `secp256k1-sha256` em todas as respostas HTTP 200 e verificador gratuito `/api/v1/verify`.
