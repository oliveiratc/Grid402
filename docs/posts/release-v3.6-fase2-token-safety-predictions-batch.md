# Kit de Divulgação — Grid402 Fase 2 (`token-safety`, `prediction-markets` e `POST /api/v1/batch`)

> Alinhado 100% com produção (`https://grid402.online`): **28 micro-serviços UP**, **30 ferramentas MCP (`v2.1.0`)**, **27 endpoints pagos**, liquidação Dual-Facilitator (**Coinbase CDP + PayAI**) em **Base, Polygon, Arbitrum e Solana (SPL USDC)**, transferência direta em 6 redes, **Pix/Cartão** e **RapidAPI**.

---

## Resumo Executivo (3 Novos Endpoints da Fase 2)

1. **`GET /api/v1/token-safety` (`0.0150 USDC` / 3 créditos):** auditoria determinística de segurança de contratos e liquidez DEX em **5 redes** (**Base, Ethereum, Polygon, Arbitrum e Solana** com auto-detecção Base58), cruzando **GoPlus Security API + DexScreener API** com cache Zero-DB de 300s — detecta honeypot, mintable, proxy, trava de venda, taxas de compra/venda, Top 10 holders e liquidez USD, retornando score `0–100`, veredito (`SAFE`, `CAUTION`, `HIGH_RISK`, `CRITICAL`), `legal_disclaimer` e assinatura `_meta` + `_oracle_proof`.
2. **`GET /api/v1/prediction-markets` (`0.0100 USDC` / 2 créditos):** agregador normalizado da **Polymarket Gamma API + Kalshi Public Trade API v2** com probabilidades `0–100%`, volume 24h, liquidez USD e data de resolução, cache Zero-DB de 120s, degradação graciosa em falha parcial e `HTTP 503` pré-cobrança em falha total.
3. **`POST /api/v1/batch` (preço = soma exata de 1 a 5 sub-requisições; máximo 2 em sandbox `demo`):** executa até **5 endpoints diferentes em 1 única requisição HTTP/MCP e 1 única liquidação x402** (ou 1 chamada de Session Pass), bloqueia recursão e rotas administrativas, valida `allowed_endpoints` por sub-serviço antes de debitar, suporta `Idempotency-Key` e **estorna automaticamente créditos de sub-itens com falha** (ou aborta `/settle` se 100% falharem).

---

## 1. LinkedIn (PT-BR — Post Principal + 1º Comentário)

> Calibrado abaixo de 2.400 caracteres em texto simples (limite do LinkedIn: 3.000).

Acabou de subir a **Fase 2** no **Grid402** — agora são **28 micro-serviços UP** e **30 ferramentas MCP (`v2.1.0`)** para agentes autônomos de IA, bots DeFi e equipes de pesquisa operarem sem cadastro, sem API key e sem mensalidade.

Três novos endpoints já estão ao vivo em produção:

**1. Auditoria de Segurança de Tokens Multi-Chain (`GET /api/v1/token-safety` — `0,0150 USDC` / 3 créditos)**
Antes de um agente aprovar um swap ou recomendar um ativo, ele precisa saber em milissegundos se o contrato é seguro.
Nosso endpoint cobre **Base, Ethereum, Polygon, Arbitrum e Solana** (auto-detecção Base58) cruzando **GoPlus Security + DexScreener** em cache Zero-DB (300s):
• Flags de honeypot, mintable, proxy, trava de venda, taxas de compra/venda e concentração Top 10 holders;
• Liquidez DEX em USD, score determinístico `0–100` e veredito pronto para decisão (`SAFE`, `CAUTION`, `HIGH_RISK`, `CRITICAL`);
• Prova criptográfica ECDSA (`_oracle_proof`) + `_meta`.

**2. Mercados de Previsão Unificados (`GET /api/v1/prediction-markets` — `0,0100 USDC` / 2 créditos)**
Polymarket e Kalshi viraram o principal termômetro de probabilidades em tempo real para agentes de pesquisa e inteligência macro.
Agora você consulta os dois em **1 único schema normalizado** (probabilidades `0–100%`, volume 24h, liquidez e data de resolução), com degradação graciosa em falha parcial e `HTTP 503` pré-cobrança se ambas falharem.

**3. Execução Multi-Serviço em Lote (`POST /api/v1/batch` — soma exata dos itens)**
Chega de assinar 5 pagamentos separados para tomar 1 decisão.
Agrupe até **5 endpoints em uma única chamada** (ex.: `dns` + `ssl` + `ofac` + `token-safety` + `prediction-markets`) com 1 única liquidação x402 ou 1 chamada de Session Pass:
• Bloqueia recursão e rotas administrativas;
• Valida `allowed_endpoints` por sub-serviço antes de debitar;
• **Estorno automático** no Session Pass para qualquer sub-item que falhar + suporte a `Idempotency-Key`.

Teste grátis agora no terminal (Sandbox 3 req/dia ou cupom `MCPTRY50` com 50 créditos):

`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"`

Links do catálogo atualizado, MCP v2.1.0 e GitHub no primeiro comentário. Qual combinação você colocaria primeiro dentro de uma chamada `/batch`?

#AgentesDeIA #DeFi #MCP #x402 #PredictionMarkets #Web3 #InteligenciaArtificial #APIs

**1º comentário (colar logo após publicar):**
Links oficiais do Grid402 (28 serviços UP · 30 tools MCP v2.1.0):
• Portal e Playground interativo: https://grid402.online
• Catálogo completo com os 3 novos endpoints: https://grid402.online/catalog
• Session Pass (Pix/Cartão ou Multi-Chain USDC): https://grid402.online/pass
• Trust Center: https://grid402.online/trust
• Servidor MCP v2.1.0: `npx -y grid402-mcp` | Remoto: https://grid402.online/mcp
• GitHub: https://github.com/oliveiratc/Grid402

---

## 2. X / Twitter — Thread Global (EN)

> Todos os 5 tweets calibrados abaixo de 275 caracteres brutos.

**Tweet 1:**
Phase 2 is LIVE on Grid402 (grid402.online) ⚡

28 live M2M APIs · 30 MCP tools (v2.1.0) · 27 x402 v2 endpoints — zero signup, zero API keys.

Meet our 3 new endpoints for DeFi, research & autonomous AI agents 👇🧵

**Tweet 2 (`/token-safety`):**
1/ Token Safety Audit (`GET /api/v1/token-safety` — $0.0150 / 3 cr):
• Base, Ethereum, Polygon, Arbitrum & Solana (Base58 auto-detect)
• GoPlus Security + DexScreener liquidity
• Honeypot/mint/tax flags, 0-100 score & verdict (SAFE / CAUTION / HIGH_RISK / CRITICAL) + ECDSA proof

**Tweet 3 (`/prediction-markets`):**
2/ Unified Prediction Markets (`GET /api/v1/prediction-markets` — $0.0100 / 2 cr):
• Aggregates Polymarket + Kalshi v2 in one normalized schema
• 0-100% probabilities, 24h volume, liquidity & end dates
• Graceful fallback on partial failure + 503 pre-charge if both fail

**Tweet 4 (`POST /api/v1/batch`):**
3/ Multi-Service Batching (`POST /api/v1/batch` — exact sum of 1-5 items):
• Bundle up to 5 endpoints (dns + ssl + ofac + token-safety) in ONE x402 settlement or Session Pass call
• Auto-refunds failed sub-items on Session Pass
• `Idempotency-Key` + Spend Controls per sub-service

**Tweet 5 (CTA):**
28 live APIs · 30 MCP tools (`npx -y grid402-mcp` v2.1.0).

Try free (3 req/day or `MCPTRY50`):
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/prediction-markets?q=bitcoin&limit=5"`

https://grid402.online/catalog | https://grid402.online/mcp

---

## 3. X / Twitter — Thread em Português (PT-BR)

**Post 1:**
Acabou de subir a **Fase 2 do Grid402** (`grid402.online`) ⚡

Agora são **28 APIs M2M UP**, **30 ferramentas MCP (`v2.1.0`)** e **27 endpoints pagos** — sem cadastro, sem API key e sem mensalidade.

Conheça os 3 novos lançamentos 👇🧵

**Post 2 (`/token-safety`):**
1/ **Auditoria Multi-Chain de Tokens (`GET /api/v1/token-safety` — `0,0150 USDC` / 3 cr):**
• Base, Ethereum, Polygon, Arbitrum e Solana (Base58 automático)
• GoPlus Security + DexScreener (liquidez USD, honeypot, mintable, taxas, Top 10 holders)
• Score `0–100` + veredito (`SAFE`/`CAUTION`/`HIGH_RISK`/`CRITICAL`) + `_oracle_proof`

**Post 3 (`/prediction-markets`):**
2/ **Mercados de Previsão (`GET /api/v1/prediction-markets` — `0,0100 USDC` / 2 cr):**
• Polymarket + Kalshi v2 unificados em 1 schema
• Probabilidades `0–100%`, volume 24h, liquidez e data de encerramento
• Degradação graciosa em falha parcial e HTTP 503 pré-cobrança se ambas falharem

**Post 4 (`POST /api/v1/batch`):**
3/ **Lote Multi-Serviço (`POST /api/v1/batch` — soma exata de 1–5 itens):**
• Agrupe até 5 consultas em 1 única chamada e 1 única liquidação x402 ou Session Pass
• Estorno automático por sub-item falho no Session Pass
• `Idempotency-Key` e `allowed_endpoints` validados por sub-serviço

**Post 5 (CTA):**
Teste grátis agora (3 req/dia ou cupom `MCPTRY50` com 50 cr):
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/prediction-markets?q=bitcoin&limit=5"`

🌐 https://grid402.online/catalog | 📦 `npx -y grid402-mcp`

---

## 4. Farcaster (`@oliveiratco`)

> Shipped Phase 2 on Grid402 (https://grid402.online) — now **28 live M2M APIs** & **30 MCP tools (`v2.1.0`)** ⚡
>
> • `GET /api/v1/token-safety` ($0.0150 USDC): Base, ETH, Polygon, Arbitrum & Solana audit (GoPlus + DexScreener) with honeypot/tax/liquidity checks, 0–100 score & deterministic verdict (`SAFE` / `CAUTION` / `HIGH_RISK` / `CRITICAL`)
> • `GET /api/v1/prediction-markets` ($0.0100 USDC): normalized Polymarket + Kalshi aggregator with 0–100% probabilities, 24h volume & liquidity
> • `POST /api/v1/batch`: run 1–5 endpoints in ONE x402 settlement or Session Pass call (exact sum pricing, auto-refund on failed sub-items, `Idempotency-Key`)
>
> Every response includes `_meta` + offline-verifiable ECDSA `_oracle_proof`.
>
> Try free (3 req/day or `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"`
>
> MCP: `npx -y grid402-mcp` | Catalog: https://grid402.online/catalog

---

## 5. Comunidades (Discord / Telegram / WhatsApp)

### Versão PT-BR
> Pessoal, acabou de ir para produção a **Fase 2 do Grid402** (`https://grid402.online`) — agora com **28 APIs UP** e **30 ferramentas MCP (`v2.1.0`)**, sem cadastro e sem API key:
>
> • **`GET /api/v1/token-safety` (`0,0150 USDC` / 3 cr):** auditoria de contratos e liquidez DEX em Base, Ethereum, Polygon, Arbitrum e Solana (GoPlus + DexScreener) com score `0–100`, veredito (`SAFE`/`CAUTION`/`HIGH_RISK`/`CRITICAL`) e `_oracle_proof` ECDSA.
> • **`GET /api/v1/prediction-markets` (`0,0100 USDC` / 2 cr):** Polymarket + Kalshi unificados em um só schema (probabilidades `0–100%`, volume 24h e liquidez).
> • **`POST /api/v1/batch`:** agrupe até **5 endpoints em 1 única chamada e 1 única liquidação x402** (soma exata dos itens, `Idempotency-Key` e estorno automático por sub-item falho no Session Pass).
>
> Teste grátis (3 req/dia ou cupom `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/prediction-markets?q=bitcoin&limit=5"`
>
> Catálogo: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

### Versão EN
> Hey everyone! **Phase 2 is live on Grid402** (`https://grid402.online`) — now **28 live APIs** and **30 MCP tools (`v2.1.0`)**, zero signup and zero API keys:
>
> • **`GET /api/v1/token-safety` (`$0.0150 USDC` / 3 cr):** multi-chain token security & DEX liquidity audit across Base, Ethereum, Polygon, Arbitrum & Solana (GoPlus + DexScreener) with 0–100 score, deterministic verdict (`SAFE`/`CAUTION`/`HIGH_RISK`/`CRITICAL`), and ECDSA `_oracle_proof`.
> • **`GET /api/v1/prediction-markets` (`$0.0100 USDC` / 2 cr):** normalized Polymarket + Kalshi v2 aggregator (0–100% probabilities, 24h volume, liquidity, end dates) with graceful fallback.
> • **`POST /api/v1/batch`:** execute 1–5 endpoints in a single HTTP/MCP call and single x402 settlement (exact-sum pricing, `Idempotency-Key`, and automatic Session Pass refund for failed sub-items).
>
> Try free (3 req/day or `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"`
>
> Catalog: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

---

## 6. E-mail Curto de Atualização (Parceiros e Integradores)

### Versão PT-BR
- **Assunto:** [Grid402 Fase 2] Novos endpoints: Token Safety, Prediction Markets e POST /api/v1/batch (28 APIs · 30 tools MCP)
- **Corpo:**

Olá,

Subimos hoje a **Fase 2 do Grid402** (`https://grid402.online`), ampliando o catálogo para **28 micro-serviços UP** e **30 ferramentas MCP (`grid402-mcp@2.1.0`)**:

1. **`GET /api/v1/token-safety` (`0,0150 USDC` / 3 cr):** auditoria de segurança de tokens e liquidez DEX em Base, Ethereum, Polygon, Arbitrum e Solana (GoPlus + DexScreener), com score `0–100`, veredito (`SAFE`, `CAUTION`, `HIGH_RISK`, `CRITICAL`) e `_oracle_proof`.
2. **`GET /api/v1/prediction-markets` (`0,0100 USDC` / 2 cr):** Polymarket e Kalshi normalizados em um único schema com probabilidades `0–100%`, volume 24h e liquidez.
3. **`POST /api/v1/batch` (soma exata de 1 a 5 itens):** execute múltiplos endpoints em 1 única chamada HTTP/MCP e 1 única liquidação x402 ou Session Pass, com `Idempotency-Key` e estorno automático de créditos por sub-item falho.

Teste grátis no sandbox (`3 req/dia` ou cupom `MCPTRY50` com 50 créditos):
```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"
```

Catálogo: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

Abraço,
**Thiago Campos** — Grid402 Network Intelligence (`contato@grid402.online`)

### Versão EN
- **Subject:** [Grid402 Phase 2] New endpoints: Multi-Chain Token Safety, Prediction Markets & POST /api/v1/batch
- **Body:**

Hi there,

We just shipped **Phase 2 on Grid402** (`https://grid402.online`), expanding the hub to **28 live APIs** and **30 MCP tools (`grid402-mcp@2.1.0`)**:

1. **`GET /api/v1/token-safety` (`$0.0150 USDC` / 3 cr):** deterministic token & DEX liquidity audit across Base, Ethereum, Polygon, Arbitrum, and Solana (GoPlus + DexScreener) with 0–100 score, verdict (`SAFE`, `CAUTION`, `HIGH_RISK`, `CRITICAL`), and ECDSA `_oracle_proof`.
2. **`GET /api/v1/prediction-markets` (`$0.0100 USDC` / 2 cr):** unified Polymarket + Kalshi v2 aggregator returning 0–100% probabilities, 24h volume, and liquidity.
3. **`POST /api/v1/batch` (exact sum of 1–5 sub-requests):** run multiple endpoints in one HTTP/MCP call and one x402 settlement or Session Pass debit, with `Idempotency-Key` and automatic credit refunds for failed sub-items.

Try free now (3 req/day or `MCPTRY50` for 50 free credits):
```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"
```

Catalog: https://grid402.online/catalog | MCP: `npx -y grid402-mcp`

Best,
**Thiago Campos** — Grid402 Network Intelligence (`contato@grid402.online`)

---

## 7. Roteiro de Vídeo Curto (45–55s — Reels / Shorts / LinkedIn / X)

| Tempo | Visual / Tela | Narração (PT-BR) |
|---|---|---|
| **00–06s** | Terminal no tema `#060B19` mostrando `grid402.online/status` com `28/28 UP`. | *"O Grid402 acabou de ganhar a Fase 2: agora são 28 APIs M2M e 30 ferramentas MCP sem cadastro e sem API key."* |
| **06–18s** | `curl` em `/api/v1/token-safety?chain=base&address=0x8335...` destacando score `0–100`, flags GoPlus + DexScreener e veredito `SAFE`. | *"Primeiro: `/token-safety`. Antes do seu agente aprovar um swap na Base, Ethereum, Polygon, Arbitrum ou Solana, ele audita honeypot, taxas e liquidez em milissegundos."* |
| **18–30s** | `curl` em `/api/v1/prediction-markets?q=bitcoin` exibindo mercados Polymarket e Kalshi normalizados com probabilidades `0–100%`. | *"Segundo: `/prediction-markets`. Polymarket e Kalshi juntos em um único schema para agentes de pesquisa e inteligência macro."* |
| **30–43s** | Payload JSON de `POST /api/v1/batch` agrupando `dns` + `ofac` + `token-safety` em 1 chamada e 1 cobrança. | *"Terceiro: `/batch`. Agrupe até 5 consultas em uma única requisição e um único pagamento x402 — e se algum item falhar no Session Pass, o estorno é automático."* |
| **43–52s** | Tela de `grid402.online/catalog` + comando `npx -y grid402-mcp` + cupom `MCPTRY50`. | *"Testa grátis em `grid402.online` ou usa o cupom `MCPTRY50` para ganhar 50 créditos."* |
