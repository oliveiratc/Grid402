# Checklist de Diretórios, Indexadores e Seeding de Descoberta (Grid402 v3.7 / MCP v2.1.0)

> **Objetivo:** Garantir que todos os **28 serviços** (incluindo Fase 2: `/api/v1/token-safety`, `/api/v1/prediction-markets` e `POST /api/v1/batch`), os **27 recursos pagos** (`/.well-known/x402.json`), as **4 redes gasless x402** (`Base`, `Polygon`, `Arbitrum`, `Solana`), as **30 ferramentas MCP (`grid402-mcp@2.1.0`)** e as **páginas SEO PT-BR** estejam indexados em todos os motores de descoberta de agentes e humanos.

## 1. Pós-Deploy Imediato (Produção `https://grid402.online`)

- [x] **Deploy Hostinger (`grid402.online`):** pacote `dist/grid402_hostinger_deploy.zip` implantado com sucesso.
- [x] **Coletor & Saúde (`/status`):** `28/28 serviços operantes`, incluindo `token-safety`, `prediction-markets` e `batch`.
- [x] **Validar HTTP 402 multi-chain e Fase 2 em produção:**
  - `GET /api/v1/token-safety` → HTTP 402 (`0.0150 USDC` / `15000`)
  - `GET /api/v1/prediction-markets` → HTTP 402 (`0.0100 USDC` / `10000`)
  - `POST /api/v1/batch` → HTTP 402 soma dinâmica exata (ex.: `dns` + `token-safety` = `0.0200 USDC` / `20000`)
  - `/.well-known/x402.json` → `27` recursos pagos (`accepts[]` com `eip155:8453`, `eip155:137`, `eip155:42161`, `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`).
- [ ] **Validar páginas SEO PT-BR após últimas atualizações (checagem visual rápida):**
  - `https://grid402.online/dossie-kyb` (HTTP 200, gerar 1 prévia grátis + verificar botão Pix R$ 12)
  - `https://grid402.online/api-cnpj` (HTTP 200)
  - `https://grid402.online/api-sancoes-cgu` (HTTP 200)
  - `https://grid402.online/api-dias-uteis-anbima` (HTTP 200)

## 2. Seeding & Liquidação On-Chain na Base Mainnet (Fase 2 Concluída)

- [x] **Execução real na Base Mainnet (`ChainID 8453`, `2026-11-27 13:43 UTC`):**
  - `token-safety` (`0.0150 USDC`, tx `0x6ee11db5b2090d733d617d2fd55b3a8...`)
  - `prediction-markets` (`0.0100 USDC`, tx `0x5a8bb67fe992864d22d18f37445e9c61f...`)
  - `POST /batch` (`0.0100 USDC`, tx `0x51540de836ba8fe7750c8ae6ee0905ad...`)
- [ ] **Verificar propagação no PayAI Bazaar (`28` recursos):**
  Conferir periodicamente `https://facilitator.payai.network/discovery/resources?payTo=0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` (atualmente em `25` itens; os 3 novos da Fase 2 aguardam propagação ou indexação direta via CDP/PayAI). Para revalidar via CLI sem gastar USDC:
  ```powershell
  node scripts/bazaar_seed.mjs --only=token-safety,prediction-markets,batch
  ```

## 3. Publicação npm e Atualização de Diretórios MCP / x402

- [x] **`grid402-mcp@2.1.0` publicado no npm (`30` tools):**
  `npm view grid402-mcp version` confirmado em `2.1.0`; `POST https://grid402.online/mcp` retorna `2.1.0` com `30` tools.
- [ ] **Smithery.ai:** re-sincronizar repositório `oliveiratc/Grid402` para refletir `v2.1.0` e `30 tools`.
- [ ] **Glama.ai (`glama.ai/mcp/connectors/io.github.oliveiratc/grid402`):** acionar re-scan do conector para atualizar para `30` ferramentas (`v2.1.0`).
- [ ] **x402scan (`x402scan.com`):** verificar listagem multi-chain (`Base`, `Polygon`, `Arbitrum`, `Solana`) e os endpoints `token-safety`, `prediction-markets` e `POST /batch`.
- [ ] **PayAPI Market (`payapi.market`):** atualizar descrição destacando 28 serviços / 27 endpoints pagos + `token-safety` + `prediction-markets` + `POST /batch` + 4 redes gasless.
- [ ] **Awesome-x402 / Awesome-MCP (PRs no GitHub):** atualizar descrição para 28 serviços, 30 MCP tools (`grid402-mcp@2.1.0`) e suporte multi-chain gasless (Base, Polygon, Arbitrum, Solana).
- [ ] **mcp.so / PulseMCP / mcprush / mcptry:** re-sincronizar catálogo apontando para `https://grid402.online/.well-known/mcp/server-card.json` e `https://grid402.online/mcp`.

## 4. Indexação Google Search Console e Bing Webmaster (Tráfego Orgânico Pix BR)

- [ ] Acessar **Google Search Console** (`https://search.google.com/search-console`) → **Sitemaps** → Reenviar `https://grid402.online/sitemap.xml`.
- [ ] Solicitar **Inspeção de URL + Indexação Manual** para as 5 páginas de conversão:
  1. `https://grid402.online/dossie-kyb`
  2. `https://grid402.online/pass`
  3. `https://grid402.online/api-cnpj`
  4. `https://grid402.online/api-sancoes-cgu`
  5. `https://grid402.online/api-dias-uteis-anbima`
- [ ] Repetir submissão do `sitemap.xml` no **Bing Webmaster Tools** (que também alimenta ChatGPT Search / Copilot).

## 5. Resumo Oficial de Preços (Fonte Única de Verdade)
- **Pay-per-call x402 (gasless via PayAI em Base, Polygon, Arbitrum e Solana):** `0.0050 USDC` (padrão: `extract`, `email-verify`, `forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `indicators`, `holidays`, `workdays`, `notarize`, `global-markets`) até `0.0500 USDC` (`dossier`).
- **Session Pass Pré-Pago (Pix / Cartão / USDC 6 redes — `1 crédito = 0.0050 USDC`):**
  - **Starter:** R$ 12,00 ou 2 USDC → **400 créditos** (= 40 Dossiês KYB ou 400 chamadas padrão, 180 dias)
  - **Pro:** R$ 29,00 ou 5 USDC → **1.200 créditos** (1.000 base + 200 bônus = 120 Dossiês KYB ou 1.200 chamadas padrão, 180 dias)
  - **Fleet:** R$ 115,00 ou 20 USDC → **6.000 créditos** (4.000 base + 2.000 bônus, 365 dias)
