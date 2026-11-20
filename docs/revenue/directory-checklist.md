# Checklist de Diretórios, Indexadores e Seeding de Descoberta (Grid402 v3.4 / MCP v2.0.0)

> **Objetivo:** Garantir que todos os novos endpoints (`/api/v1/dossier`, `/api/v1/extract`, `/api/v1/email-verify`), as **4 redes gasless x402** (`Base`, `Polygon`, `Arbitrum`, `Solana`), as **27 ferramentas MCP (`v2.0.0`)** e as **páginas SEO PT-BR** estejam indexados em todos os motores de descoberta de agentes e humanos.

## 1. Pós-Deploy Imediato (Produção `https://grid402.online`)

- [ ] **Upload do pacote Hostinger:** subir e extrair `dist/grid402_hostinger_deploy.zip` na pasta `public_html` da Hostinger.
- [ ] **Atualizar cache do coletor:** abrir `https://grid402.online/collector.php?token=<SEU_CRON_SECRET>` e confirmar retorno `25/25 serviços operantes`.
- [ ] **Validar HTTP 402 multi-chain em produção:**
  ```bash
  curl -sL "https://grid402.online/api/v1/extract?url=https://example.com"
  ```
  Confirmar `accepts[]` contendo 4 redes (`eip155:8453`, `eip155:137`, `eip155:42161`, `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`).
- [ ] **Validar novas páginas PT-BR em produção:**
  - `https://grid402.online/dossie-kyb` (HTTP 200, gerar 1 prévia grátis + verificar botão Pix R$ 12)
  - `https://grid402.online/api-cnpj` (HTTP 200)
  - `https://grid402.online/api-sancoes-cgu` (HTTP 200)
  - `https://grid402.online/api-dias-uteis-anbima` (HTTP 200)

## 2. PayAI Bazaar Seeding (Indexação Automática dos 3 Novos Endpoints)

Com apenas **~0.0600 USDC** na carteira pagadora (que **retorna 100% para sua carteira oficial** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`), indexe `dossier`, `extract` e `email-verify` no PayAI Bazaar:

```powershell
# 1. Dry-run (nao gasta nada — apenas verifica quais faltam no PayAI Bazaar)
node scripts/bazaar_seed.mjs --only=dossier,extract,email-verify

# 2. Execução real na Base Mainnet (total = 0.0600 USDC, 100% retorna para sua carteira)
$env:GRID402_BUYER_PRIVATE_KEY='0xSUA_CHAVE_COM_USDC_NA_BASE'
node scripts/bazaar_seed.mjs --only=dossier,extract,email-verify --execute
```

- [ ] Conferir em `https://facilitator.payai.network/discovery/resources?payTo=0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` que agora existem **25 recursos indexados** (incluindo `dossier`, `extract`, `email-verify`).

## 3. Publicação npm e Atualização de Diretórios MCP / x402

- [ ] **Publicar `grid402-mcp@2.0.0` no npm:**
  ```powershell
  cd mcp
  npm publish --access public
  ```
- [ ] **Smithery.ai:** re-sincronizar repositório `oliveiratc/Grid402` para refletir `v2.0.0` e `27 tools`.
- [ ] **Glama.ai (`glama.ai/mcp/connectors/io.github.oliveiratc/grid402`):** acionar re-scan do conector para atualizar de 25 para 27 ferramentas.
- [ ] **x402scan (`x402scan.com`):** verificar listagem multi-chain (`Base`, `Polygon`, `Arbitrum`, `Solana`) e os novos endpoints `extract` e `email-verify`.
- [ ] **PayAPI Market (`payapi.market`):** atualizar descrição destacando 25 endpoints + `extract` + `email-verify` + 4 redes gasless.
- [ ] **Awesome-x402 / Awesome-MCP (PRs no GitHub):** atualizar descrição para 25 endpoints, 27 MCP tools e suporte multi-chain gasless PayAI (Base, Polygon, Arbitrum, Solana).
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
  - **Starter:** R$ 12,00 ou 2 USDC → **400 créditos** (= 40 Dossiês KYB ou 400 chamadas padrão, 30 dias)
  - **Pro:** R$ 29,00 ou 5 USDC → **1.000 créditos** (= 100 Dossiês KYB ou 1.000 chamadas padrão, 30 dias)
  - **Fleet:** R$ 115,00 ou 20 USDC → **6.000 créditos** (4.000 base + 2.000 bônus, 60 dias)
