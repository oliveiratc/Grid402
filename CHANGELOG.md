# Changelog — Grid402 (`grid402.online`)

Todas as mudanças relevantes de arquitetura, catálogo de serviços, segurança criptográfica e política de preços do **Grid402** são documentadas neste arquivo, seguindo o padrão [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e [Semantic Versioning](https://semver.org/lang/pt-BR/).

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
