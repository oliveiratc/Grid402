# Checklist de Diretórios, Indexadores e Seeding de Descoberta (Grid402 v3.8 / MCP v2.2.0)

> **Objetivo:** Garantir que todos os **31 serviços** (incluindo Onda 3A: `/api/v1/sec-company`, `/api/v1/vuln-audit`, `/api/v1/chain-telemetry`, além da Fase 2: `/api/v1/token-safety`, `/api/v1/prediction-markets` e `POST /api/v1/batch`), os **30 recursos pagos** (`/.well-known/x402.json`), as **4 redes gasless x402** (`Base`, `Polygon`, `Arbitrum`, `Solana`), as **33 ferramentas MCP (`grid402-mcp@2.2.0`)** e as **páginas SEO PT-BR / EN** estejam indexados em todos os motores de descoberta de agentes e humanos.

## 1. Pós-Deploy Imediato (Produção `https://grid402.online`)

- [x] **Deploy Hostinger (`grid402.online`):** branch `deploy` sincronizada via GitHub Actions (`Sync public_html to deploy branch` / Git Auto-Deployment).
- [x] **Coletor e Saúde (`/status`):** `31/31 serviços operantes` (`status=OPERATIONAL`), incluindo `sec-company`, `vuln-audit` e `chain-telemetry`.
- [x] **Validar HTTP 402 multi-chain e Wave 3A em produção:**
  - `GET /api/v1/sec-company` -> HTTP 402 (`0.0100 USDC` / `10000`)
  - `GET /api/v1/vuln-audit` -> HTTP 402 (`0.0050 USDC` / `5000`)
  - `GET /api/v1/chain-telemetry` -> HTTP 402 (`0.0050 USDC` / `5000`)
  - `/.well-known/x402.json` -> `30` recursos pagos validados em `scripts/validate_cdp_bazaar.php` (`30/30`).
- [x] **Validar páginas SEO após últimas atualizações (checagem visual e HTTP):**
  - `https://grid402.online/dossie-kyb` (HTTP 200 verificado)
  - `https://grid402.online/api-cnpj` / `https://grid402.online/api-sancoes-cgu` / `https://grid402.online/api-dias-uteis-anbima` (HTTP 200)
  - `https://grid402.online/url-to-markdown-api` / `https://grid402.online/global-kyb-api` / `https://grid402.online/zero-pii-email-verification-api` (HTTP 200)

## 2. Seeding e Liquidação On-Chain na Base Mainnet

- [x] **Fase 2 liquidada on-chain (`ChainID 8453`, `2026-11-27 13:43 UTC`):**
  - `token-safety` (`0.0150 USDC`), `prediction-markets` (`0.0100 USDC`), `POST /batch` (`0.0100 USDC`).
- [x] **Seeding Onda 3A no PayAI Bazaar (`0.0200 USDC` total — `sec-company`, `vuln-audit`, `chain-telemetry`):**
  ```powershell
  $env:GRID402_BUYER_PRIVATE_KEY='0xSUA_CHAVE'
  node scripts/bazaar_seed.mjs --only=sec-company,vuln-audit,chain-telemetry --execute
  ```
  (Executado on-chain na Base Mainnet em 2026-11-27, retornando `0.0200 USDC` para `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`; aguardando propagação assíncrona no endpoint `/discovery/resources` do PayAI.)

## 3. Publicação npm, RapidAPI Studio e Diretórios MCP / x402

- [x] **`grid402-mcp@2.2.0` publicado no npm (`33` tools):**
  `npm view grid402-mcp version` em `2.2.0`; `POST https://grid402.online/mcp` e `/.well-known/mcp.json` em `2.2.0` (`33` tools).
- [x] **RapidAPI Studio (`rapidapi.com`) atualizado:**
  - `grid402-open-data-kyb-ai-utilities`: importado `docs/revenue/openapi/rapidapi-all.json` (`31` rotas / `32` operações).
  - `grid402-web-to-markdown-email-verify-network-utilities`: importado `docs/revenue/openapi/rapidapi-global.json` (`21` rotas / `22` operações).
- [x] **Glama.ai (`glama.ai/mcp/connectors/io.github.oliveiratc/grid402`):**
  Health Check `Healthy` (`33 tools`), descrições do conector e profile atualizadas para `31 serviços` / `MCP v2.2.0 (33 tools)` e sync de repositório agendado (`commit 05ea7ec`).
- [~] **Smithery.ai (`smithery.ai`):** Serviço/Proxy/CLI para usuários encerrado pela plataforma (substituído por diretório curado da Arcade; sem listing ativo para `oliveiratc/Grid402`).
- [x] **x402scan (`x402scan.com`):** **30 recursos** registrados via `registerFromOrigin` (`registered: 29`, `apiKeyCount: 1`, `failed: 0`), incluindo `sec-company`, `vuln-audit` e `chain-telemetry`.
- [x] **PayAPI Market (`payapi.market`):** Submissão já registrada anteriormente (sem duplicação nesta etapa).
- [x] **Awesome-x402 / Awesome-MCP (PRs no GitHub):** atualizados títulos e descrições para 31 serviços, 30 recursos x402 e 33 MCP tools (`grid402-mcp@2.2.0`, PRs #20515, #5701 e #2222).
- [x] **mcp.so / PulseMCP / mcprush / mcptry:** catálogo alinhado apontando para `https://grid402.online/.well-known/mcp/server-card.json` e `https://grid402.online/mcp`.

## 4. Indexação Google Search Console e Bing Webmaster (Tráfego Orgânico)

- [x] **IndexNow Bing / Yandex / Naver:** disparado lote de 27 URLs via `scripts/submit_indexnow.php` com HTTP 200.
- [x] Conferir indexação contínua de `https://grid402.online/sitemap.xml` no **Google Search Console** e **Bing Webmaster Tools**.

## 5. Resumo Oficial de Preços (Fonte Única de Verdade)

- **Pay-per-call x402 (gasless via PayAI em Base, Polygon, Arbitrum e Solana):** `0.0050 USDC` (1 cr, incluindo `vuln-audit` e `chain-telemetry`), `0.0100 USDC` (2 cr, incluindo `sec-company`, `prediction-markets`, `cnpj`, `lei`) até `0.0500 USDC` (`dossier`).
- **Session Pass Pré-Pago (Pix / Cartão / USDC 6 redes — `1 crédito = 0.0050 USDC`):**
  - **Micro:** R$ 4,90 → **50 créditos** (`5` Dossiês KYB ou `50` chamadas padrão, 90 dias)
  - **Starter:** R$ 12,00 ou 2 USDC → **400 créditos** (`180` dias)
  - **Pro:** R$ 29,00 ou 5 USDC → **1.200 créditos** (1.000 base + 200 bônus, `180` dias)
  - **Fleet:** R$ 115,00 ou 20 USDC → **6.000 créditos** (4.000 base + 2.000 bônus, `365` dias)
