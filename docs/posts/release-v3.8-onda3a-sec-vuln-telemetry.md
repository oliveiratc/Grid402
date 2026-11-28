# Kit de Divulgação — Grid402 Onda 3A (`sec-company`, `vuln-audit` e `chain-telemetry`)

> Alinhado 100% com produção (`https://grid402.online`): **31/31 micro-serviços UP**, **30 recursos pagos** (validados `30/30` no CDP Bazaar v2 e indexados no **x402scan** com `0` falhas), **33 ferramentas MCP (`grid402-mcp@2.2.0` publicado no npm e Healthy no Glama.ai)**, **RapidAPI Studio** atualizado (`31` rotas / `32` operações), liquidação Dual-Facilitator (**Coinbase CDP + PayAI**) em **Base, Polygon, Arbitrum e Solana (SPL USDC)**, transferência direta em 6 redes e **Session Pass via Pix/Cartão/USDC**.

---

## Resumo Executivo (3 Novos Endpoints da Onda 3A)

1. **`GET /api/v1/sec-company` (`0.0100 USDC` / 2 créditos):** consulta corporativa oficial na **SEC EDGAR** por `ticker` (mapa ticker→CIK em cache de 24h) ou `cik`, retornando perfil regulatório, filings filtráveis (`10-K`, `10-Q`, `8-K`) com links diretos do EDGAR, `include_facts=true` opcional com métricas US-GAAP protegido por cap rígido de `8 MB` (`facts_status`), cache Zero-DB de `3600s`, `HTTP 404/503` estornável e assinatura `_meta` + `_oracle_proof`.
2. **`GET /api/v1/vuln-audit` (`0.0050 USDC` / 1 crédito):** auditoria determinística de vulnerabilidades para agentes DevSecOps e assistentes de código (Claude Code, Cursor, CI/CD) por **pacote** em **8 ecossistemas** (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo/crates.io`, `NuGet`, `RubyGems`) ou **ID direto** (`CVE-*`, `GHSA-*`, `OSV-*`), cruzando **OSV.dev + catálogo oficial CISA KEV** (`actively_exploited_kev`, `fixed_in`, CVSS) com veredito determinístico (`CLEAN` a `CRITICAL_KEV`), cache Zero-DB de `900s` e `_oracle_proof`.
3. **`GET /api/v1/chain-telemetry` (`0.0050 USDC` / 1 crédito):** telemetria multi-chain ao vivo para **Base, Ethereum, Polygon, Arbitrum e Solana** combinando **RPCs públicos com fallback + DeFiLlama TVL** (bloco/slot atual, gás/taxas prioritárias em gwei/lamports, latência RPC e TVL), degradação graciosa (`partial_data`), `HTTP 503` sem cobrança em falha total e cache Zero-DB de `60s`.

*(Todos os 3 novos endpoints também funcionam dentro de `POST /api/v1/batch`, junto aos demais serviços, com soma exata de 1 a 5 itens e estorno automático por sub-item falho no Session Pass. Além disso, o portal ganhou novos cards OG `1200x630`, favicons vetoriais e `webmanifest`.)*

---

## 1. LinkedIn (PT-BR — Post Principal + 1º Comentário)

> Calibrado abaixo de 2.400 caracteres em texto simples (limite do LinkedIn: 3.000).

Acabou de subir a **Onda 3A** no **Grid402** — agora são **31 micro-serviços UP**, **30 endpoints pagos** (30/30 validados no CDP Bazaar v2 e indexados no x402scan) e **33 ferramentas MCP (`grid402-mcp@2.2.0` no npm e Healthy no Glama)**.

Tudo 100% Zero-API-Key, sem cadastro e com assinatura ECDSA (`_oracle_proof`) em cada resposta. Confira os 3 novos endpoints:

**1. Consulta Corporativa SEC EDGAR (`GET /api/v1/sec-company` — `0,0100 USDC` / 2 créditos)**
Agentes financeiros e de research agora resolvem `ticker` ou `cik` direto na SEC EDGAR oficial:
• Perfil regulatório + lista filtrável de filings (`10-K`, `10-Q`, `8-K`) com links EDGAR;
• Resumo opcional de métricas contábeis US-GAAP (`include_facts=true`) protegido por cap rígido de 8 MB (`facts_status`);
• Cache Zero-DB de 3600s e `HTTP 404/503` estornável automaticamente.

**2. Auditoria de Vulnerabilidades e CISA KEV (`GET /api/v1/vuln-audit` — `0,0050 USDC` / 1 crédito)**
Feito para agentes de código (Claude Code, Cursor e pipelines DevSecOps) validarem dependências antes de instalar ou corrigirem CVEs:
• Suporta **8 ecossistemas** (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) ou ID direto (`CVE-*`/`GHSA-*`);
• Cruza **OSV.dev + catálogo oficial CISA KEV** (`actively_exploited_kev`, `fixed_in`, CVSS);
• Veredito determinístico de `CLEAN` a `CRITICAL_KEV` em cache de 900s.

**3. Telemetria Multi-Chain ao Vivo (`GET /api/v1/chain-telemetry` — `0,0050 USDC` / 1 crédito)**
Para agentes DeFi checarem condições de rede antes de operar:
• Bloco/slot atual, gás/taxas prioritárias, latência RPC e **TVL DeFiLlama** em **Base, Ethereum, Polygon, Arbitrum e Solana**;
• Multi-RPC com fallback e degradação graciosa (`partial_data`).

Os 3 já funcionam dentro do **`POST /api/v1/batch`** (1 a 5 chamadas em 1 liquidação x402 ou Session Pass via Pix a partir de R$ 4,90 com estorno automático em falhas).

Teste grátis no terminal (Sandbox 3 req/dia ou cupom `MCPTRY50` com 50 cr):
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`

Links oficiais no 1º comentário. Qual dos 3 resolve uma dor imediata nos seus agentes?

#AgentesDeIA #DevSecOps #MCP #x402 #DeFi #SEC #InteligenciaArtificial #APIs

**1º comentário (colar logo após publicar):**
Links oficiais do Grid402 (31 serviços UP · 33 tools MCP v2.2.0):
• Portal e Playground: https://grid402.online
• Catálogo completo (31 endpoints): https://grid402.online/catalog
• Session Pass (Pix a partir de R$ 4,90 ou Multi-Chain USDC): https://grid402.online/pass
• Trust Center: https://grid402.online/trust
• Servidor MCP v2.2.0: `npx -y grid402-mcp` | Remoto: https://grid402.online/mcp
• Glama: https://glama.ai/mcp/connectors/io.github.oliveiratc/grid402
• GitHub: https://github.com/oliveiratc/Grid402

---

## 2. X / Twitter — Thread Global (EN)

> Todos os 5 tweets calibrados estritamente `<= 270` caracteres brutos.

**Tweet 1:**
Wave 3A is LIVE on Grid402 (grid402.online) ⚡

31 live M2M APIs · 30 paid x402 v2 endpoints (30/30 CDP Bazaar + x402scan) · 33 MCP tools (v2.2.0 on npm & Glama).

Zero signup, zero API keys. Meet our 3 new global endpoints 👇🧵

**Tweet 2 (`/sec-company`):**
1/ SEC EDGAR Company Lookup (`GET /api/v1/sec-company` — `$0.0100` / 2 cr):
• Ticker->CIK resolution, regulatory profile & 10-K/10-Q/8-K filings with EDGAR links
• Optional US-GAAP XBRL facts (8 MB cap)
• 3600s Zero-DB cache + ECDSA `_oracle_proof`

**Tweet 3 (`/vuln-audit`):**
2/ Package & CVE Vuln Audit (`GET /api/v1/vuln-audit` — `$0.0050` / 1 cr):
• Scans npm, PyPI, Go, Maven, Packagist, Cargo, NuGet, RubyGems or CVE/GHSA IDs
• Combines OSV.dev + CISA KEV active exploitation catalog
• Verdict (`CLEAN` to `CRITICAL_KEV`) + `fixed_in`

**Tweet 4 (`/chain-telemetry`):**
3/ Multi-Chain Live Telemetry (`GET /api/v1/chain-telemetry` — `$0.0050` / 1 cr):
• Block/slot, gas/fees, RPC latency & DeFiLlama TVL for Base, ETH, Polygon, Arbitrum & Solana
• Multi-RPC fallback + graceful `partial_data`
• Batchable via `POST /api/v1/batch`

**Tweet 5 (CTA):**
31 APIs · 33 MCP tools (`npx -y grid402-mcp` v2.2.0).

Try free (3 req/day or `MCPTRY50`):
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`

https://grid402.online/catalog | https://grid402.online/mcp

---

## 3. X / Twitter — Thread em Português (PT-BR)

**Post 1:**
Subiu a **Onda 3A do Grid402** (`grid402.online`) ⚡

Agora são **31 serviços UP**, **30 rotas pagas no x402scan** e **33 ferramentas MCP (`grid402-mcp@2.2.0` no npm e Glama)** — sem cadastro e sem API key.

Conheça os 3 novos endpoints globais 👇🧵

**Post 2 (`/sec-company`):**
1/ **SEC EDGAR (`GET /api/v1/sec-company` — `0,0100 USDC` / 2 cr):**
• Resolve `ticker` -> `CIK`, perfil regulatório e filings (`10-K`, `10-Q`, `8-K`) com links EDGAR
• `include_facts=true` opcional (métricas US-GAAP com cap de 8 MB)
• Cache 3600s + `_oracle_proof` ECDSA

**Post 3 (`/vuln-audit`):**
2/ **Auditoria de Pacotes e CVEs (`GET /api/v1/vuln-audit` — `0,0050 USDC` / 1 cr):**
• **8 ecossistemas** (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) ou `CVE-*`/`GHSA-*`
• **OSV.dev + CISA KEV** (exploração ativa + `fixed_in`)
• Veredito `CLEAN` a `CRITICAL_KEV`

**Post 4 (`/chain-telemetry`):**
3/ **Telemetria Multi-Chain (`GET /api/v1/chain-telemetry` — `0,0050 USDC` / 1 cr):**
• Bloco/slot, gás/taxas, latência RPC e TVL DeFiLlama em Base, Ethereum, Polygon, Arbitrum e Solana
• Multi-RPC com fallback + `partial_data`
• Compatível com `POST /api/v1/batch`

**Post 5 (CTA):**
Teste grátis (3 req/dia ou cupom `MCPTRY50` com 50 cr):
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`

🌐 https://grid402.online/catalog | 📦 `npx -y grid402-mcp`

---

## 4. Farcaster (`@oliveiratco`)

**Cast Principal:**
> Wave 3A is LIVE on Grid402 (https://grid402.online) — now **31 live M2M APIs**, **30 paid x402 v2 resources (x402scan + CDP Bazaar)** & **33 MCP tools (`v2.2.0` on npm & Glama)** ⚡
>
> • `GET /api/v1/sec-company` ($0.0100): official SEC EDGAR company lookup, 10-K/10-Q/8-K filings & optional US-GAAP XBRL facts (8 MB safety cap)
> • `GET /api/v1/vuln-audit` ($0.0050): OSV.dev + CISA KEV active-exploitation screening across 8 package ecosystems or CVE/GHSA IDs (`CLEAN` to `CRITICAL_KEV`)
> • `GET /api/v1/chain-telemetry` ($0.0050): live block/slot, gas/priority fees, RPC latency & DeFiLlama TVL across Base, ETH, Polygon, Arbitrum & Solana
>
> All 3 work inside `POST /api/v1/batch` and carry ECDSA `_oracle_proof`.
>
> Try free (3 req/day or `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`
>
> `npx -y grid402-mcp` | https://grid402.online/catalog

**Fallback curto (`<= 305` chars, usar somente se houver trava de 320 chars):**
> Wave 3A LIVE on Grid402 (31 APIs · 33 MCP tools v2.2.0) ⚡
> • /sec-company ($0.01): SEC EDGAR filings + GAAP facts
> • /vuln-audit ($0.005): OSV.dev + CISA KEV (8 ecosystems)
> • /chain-telemetry ($0.005): Base/ETH/Polygon/Arb/Sol RPC + TVL
> https://grid402.online/catalog

---

## 5. Comunidades (Discord / Telegram / WhatsApp)

### Versão PT-BR
> Pessoal, acabou de subir a **Onda 3A no Grid402** (`https://grid402.online`) — agora com **31 serviços UP**, **30 rotas pagas no x402scan** e **33 ferramentas MCP (`grid402-mcp@2.2.0` no npm e Glama)**, tudo Zero-API-Key:
>
> • **`GET /api/v1/sec-company` (`0,0100 USDC` / 2 cr):** busca `ticker` ou `cik` na SEC EDGAR oficial com perfil, filings (`10-K`, `10-Q`, `8-K`) e resumo opcional US-GAAP (cap de 8 MB).
> • **`GET /api/v1/vuln-audit` (`0,0050 USDC` / 1 cr):** audita pacotes em 8 ecossistemas (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) ou `CVE-*`/`GHSA-*` cruzando **OSV.dev + CISA KEV** (`CLEAN` a `CRITICAL_KEV`).
> • **`GET /api/v1/chain-telemetry` (`0,0050 USDC` / 1 cr):** bloco/slot, gás, latência RPC e TVL DeFiLlama em Base, Ethereum, Polygon, Arbitrum e Solana.
>
> Os 3 entram no `POST /api/v1/batch` e vêm assinados com `_oracle_proof`.
>
> Teste grátis (3 req/dia ou cupom `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`
>
> Catálogo: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

### Versão EN
> Hey everyone! **Wave 3A is live on Grid402** (`https://grid402.online`) — now **31 live APIs**, **30 paid endpoints indexed on x402scan**, and **33 MCP tools (`grid402-mcp@2.2.0` on npm & Glama)**, zero signup & zero API keys:
>
> • **`GET /api/v1/sec-company` (`$0.0100` / 2 cr):** SEC EDGAR ticker/CIK lookup, `10-K`/`10-Q`/`8-K` filings & optional US-GAAP XBRL summary (8 MB cap).
> • **`GET /api/v1/vuln-audit` (`$0.0050` / 1 cr):** OSV.dev + CISA KEV vulnerability audit across 8 package ecosystems (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) or `CVE`/`GHSA` IDs (`CLEAN` to `CRITICAL_KEV`).
> • **`GET /api/v1/chain-telemetry` (`$0.0050` / 1 cr):** live block/slot, gas/fees, RPC latency & DeFiLlama TVL across Base, Ethereum, Polygon, Arbitrum & Solana.
>
> All 3 work inside `POST /api/v1/batch` with ECDSA `_oracle_proof`.
>
> Try free (3 req/day or `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`
>
> Catalog: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

---

## 6. E-mail Curto de Atualização (Parceiros e Integradores)

### Versão PT-BR
- **Assunto:** [Grid402 Onda 3A] Novos endpoints: SEC EDGAR, Vuln Audit (OSV + CISA KEV) e Chain Telemetry (31 APIs · 33 tools MCP)
- **Corpo:**

Olá,

Acabamos de colocar no ar a **Onda 3A do Grid402** (`https://grid402.online`), levando a plataforma para **31 micro-serviços UP**, **30 rotas pagas indexadas no x402scan** e **33 ferramentas MCP (`grid402-mcp@2.2.0`)**:

1. **`GET /api/v1/sec-company` (`0,0100 USDC` / 2 cr):** consulta corporativa oficial SEC EDGAR por `ticker` ou `cik`, com filings filtráveis (`10-K`, `10-Q`, `8-K`) e resumo contábil US-GAAP opcional (protegido por cap de 8 MB).
2. **`GET /api/v1/vuln-audit` (`0,0050 USDC` / 1 cr):** auditoria de pacotes em 8 ecossistemas (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) ou `CVE-*`/`GHSA-*`, cruzando **OSV.dev + CISA KEV** (`actively_exploited_kev`, `fixed_in`, veredito `CLEAN` a `CRITICAL_KEV`).
3. **`GET /api/v1/chain-telemetry` (`0,0050 USDC` / 1 cr):** bloco/slot atual, taxas de gás/prioridade, latência RPC e TVL DeFiLlama para Base, Ethereum, Polygon, Arbitrum e Solana.

Todos os 3 já são aceitos em `POST /api/v1/batch`, no RapidAPI Studio e no Session Pass (Pix a partir de R$ 4,90 ou USDC, com estorno automático em falhas).

Teste grátis no terminal (3 req/dia ou cupom `MCPTRY50` com 50 cr):
```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"
```

Catálogo: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

Abraço,
**Thiago Campos** — Grid402 Network Intelligence (`contato@grid402.online`)

### Versão EN
- **Subject:** [Grid402 Wave 3A] New endpoints: SEC EDGAR, OSV + CISA KEV Vuln Audit & Multi-Chain Telemetry (31 APIs · 33 MCP tools)
- **Body:**

Hi there,

We just shipped **Wave 3A on Grid402** (`https://grid402.online`), bringing the platform to **31 live APIs**, **30 paid endpoints registered on x402scan**, and **33 MCP tools (`grid402-mcp@2.2.0` on npm & Glama)**:

1. **`GET /api/v1/sec-company` (`$0.0100` / 2 cr):** official SEC EDGAR lookup by `ticker` or `cik`, filterable `10-K`/`10-Q`/`8-K` filings, and optional US-GAAP XBRL summary (8 MB cap).
2. **`GET /api/v1/vuln-audit` (`$0.0050` / 1 cr):** OSV.dev + CISA KEV vulnerability audit across 8 package ecosystems (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) or direct `CVE`/`GHSA` IDs (`CLEAN` to `CRITICAL_KEV`, `fixed_in`).
3. **`GET /api/v1/chain-telemetry` (`$0.0050` / 1 cr):** live block/slot, gas/priority fees, RPC latency, and DeFiLlama TVL across Base, Ethereum, Polygon, Arbitrum, and Solana.

All 3 support `POST /api/v1/batch`, RapidAPI, gasless x402 v2, and `<15ms` Session Passes with auto-refunds on errors.

Try free (3 req/day or `MCPTRY50` for 50 free credits):
```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"
```

Catalog: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

Best,
**Thiago Campos** — Grid402 Network Intelligence (`contato@grid402.online`)

---

## 7. Roteiro de Vídeo Curto (45–55s — Reels / Shorts / LinkedIn / X)

| Tempo | Visual / Tela | Narração (PT-BR) |
|---|---|---|
| **00–06s** | Terminal no tema `#060B19` mostrando `grid402.online/status` (`31/31 UP`) e `grid402-mcp@2.2.0` (`33 tools`). | *"O Grid402 acaba de chegar a 31 APIs e 33 ferramentas MCP com a Onda 3A — tudo sem cadastro e sem API key."* |
| **06–18s** | `curl` em `/api/v1/sec-company?ticker=AAPL&forms=10-K,10-Q` destacando CIK, filings EDGAR e `include_facts=true`. | *"Primeiro: `/sec-company`. Seu agente consulta empresas na SEC EDGAR por ticker ou CIK, lista `10-K`, `10-Q` e `8-K` e extrai dados contábeis US-GAAP com assinatura ECDSA."* |
| **18–31s** | `curl` em `/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20` destacando `OSV.dev`, `CISA KEV` e veredito. | *"Segundo: `/vuln-audit`. Antes de instalar um pacote no Claude Code ou Cursor, ele cruza OSV.dev com CISA KEV em 8 ecossistemas e devolve o veredito e a versão corrigida."* |
| **31–43s** | `curl` em `/api/v1/chain-telemetry?chain=base` e combinação dentro de `POST /api/v1/batch`. | *"Terceiro: `/chain-telemetry`. Bloco, gás, latência RPC e TVL DeFiLlama em 5 redes — e tudo já funciona dentro do `/batch`."* |
| **43–52s** | Tela de `grid402.online/catalog` + comando `npx -y grid402-mcp` + cupom `MCPTRY50`. | *"Testa grátis agora em `grid402.online` ou usa o cupom `MCPTRY50` para ganhar 50 créditos."* |
