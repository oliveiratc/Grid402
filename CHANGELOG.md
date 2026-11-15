# Changelog — Grid402 (`grid402.online`)

Todas as mudanças relevantes de arquitetura, catálogo de serviços, segurança criptográfica e política de preços do **Grid402** são documentadas neste arquivo, seguindo o padrão [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e [Semantic Versioning](https://semver.org/lang/pt-BR/).

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
