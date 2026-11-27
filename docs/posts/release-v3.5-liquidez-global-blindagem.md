# Kit de Divulgação — Grid402 (Liquidez Global x402 v2, Sanções 4 Blocos, Extract Transiente e Novos Preços)

> Todos os materiais abaixo estão alinhados com a produção do **Grid402** (`https://grid402.online`): **25 micro-serviços UP**, **27 ferramentas MCP (`v2.0.0`, Nota A no mcprush)**, Dual-Facilitator (**Coinbase CDP + PayAI**), liquidação gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)** (além de transferência direta em 6 redes, **Pix/Cartão** e **RapidAPI**), sanções oficiais em **4 blocos (OFAC SDN, ONU, UE FSFi e UK OFSI)**, `/extract` **100% transiente** e preços/passes atualizados (`1 crédito = 0.0050 USDC`).

---

## Resumo Executivo (4 Pilares da Atualização)

1. **Liquidez Global x402 v2 (Coinbase CDP + PayAI + Solana):** roteamento Dual-Facilitator na Base (**Coinbase CDP** como primário com conformidade CDP Bazaar v2 + fallback automático seguro pré-liquidação `/verify` para **PayAI**, com invariante anti-dupla-cobrança que nunca reenvia após timeout/erro ambíguo no `/settle`), liquidação gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)**, manifesto dinâmico `/.well-known/x402.json` (além de transferência direta on-chain em 6 redes: Base, Arbitrum, Polygon, Optimism, Ethereum e Solana SPL USDC), **Pix/Cartão** em `/pass` e **RapidAPI (Cartão USD)**.
2. **Sanções Globais em 4 Blocos Oficiais + AML Web3 Multi-Ativo (`/api/v1/ofac` e `/api/v1/kyb-audit`):** sincronização diária em índice Zero-DB JSON particionado (`<= 30 ms`) cobrindo **US Treasury OFAC SDN**, **ONU (UN Security Council)**, **União Europeia (EU FSFi)** e **Reino Unido (UK OFSI)**, com triagem para carteiras **EVM (`0x...`), Solana (Base58), Bitcoin (`1/3/bc1`), Tron** e nomes/aliases + telemetria AML na Base Mainnet. Governança *fail-closed* (`HTTP 503` pré-cobrança se o índice estiver indisponível — zero falso `CLEAR` e zero cobrança em erro) e `dataset_sha256`, `last_synced_at` e `freshness_status` cobertos por `_meta` e `_oracle_proof` (ECDSA secp256k1).
3. **Extrator Web-to-Markdown 100% Transiente (`/api/v1/extract` — `0.0050 USDC` / 1 cr):** **0 bytes** de conteúdo de terceiros gravados em disco (`transient_only: true`), respeito nativo a `robots.txt` (RFC 9309), `X-Robots-Tag` (`noai`, `noimageai`, `noindex`, `none`), `Content-Signal` (`ai-input=no`), meta tags anti-AI, bloqueio de paywalls e HTTP `401/403` pré-liquidação, além de proteção contra SSRF e DNS rebinding.
4. **Preços Menores, Pro Pass Turbinado e Garantias Pró-Cliente:** `/api/v1/cnpj` e `/api/v1/lei` caíram **50%** para **`0.0100 USDC` (2 créditos)**; `/api/v1/kyb-audit` (CNPJ + Sanções 4 blocos + LEI) foi para **`0.0250 USDC` (5 créditos)** (37,5% de desconto vs. chamadas avulsas); **Pro Pass** subiu para **1.200 créditos** (`1.000 base + 200 bônus (+20%)`, 5 USDC / R$ 29) e a validade foi estendida para **180 dias** (Starter/Pro) e **365 dias** (Fleet — 6.000 créditos por 20 USDC / R$ 115). Inclui estorno automático em qualquer falha (`HTTP != 200`), retentativa gratuita por 300s via `Idempotency-Key` (`X-Idempotent-Replay: true`), Spend Controls (`daily_credit_limit`, `allowed_endpoints`), extrato Zero-PII JSON/CSV, Sandbox grátis (**3 req/dia por IP** com `X-Payment: demo`) e cupom **`MCPTRY50`** (50 créditos grátis).

---

## 1. LinkedIn (PT-BR — Post Principal + 1º Comentário)

> **Dica de alcance:** publique o corpo principal sem links externos e cole os links no 1º comentário logo após publicar.

Acabamos de colocar em produção uma nova fase do **Grid402** — nossa infraestrutura de dados públicos verificáveis e utilitários M2M para agentes de IA e automações B2B.

Quem coloca agentes autônomos em produção esbarra sempre nos mesmos três muros: **liquidez de pagamento**, **confiabilidade dos dados de compliance** e **risco jurídico ao extrair conteúdo da web**.

Nessa atualização, resolvemos os três:

**1. Liquidez Global x402 v2: Coinbase CDP + PayAI + Solana**
Passamos a operar com roteamento **Dual-Facilitator** na rede Base (**Coinbase CDP** como primário com conformidade CDP Bazaar v2 + fallback automático pré-liquidação para **PayAI**, garantindo que nenhum erro ambíguo no `/settle` gere dupla cobrança). A liquidação gasless já cobre **Base, Polygon, Arbitrum e Solana (SPL USDC)** — além de transferência direta em 6 redes, **Pix/Cartão** e **RapidAPI (Cartão USD)**. Tudo sem cadastro, sem API key e sem mensalidade.

**2. Sanções Globais em 4 Blocos Oficiais + AML Web3 Multi-Ativo**
Nosso `/api/v1/ofac` (e o bundle `/api/v1/kyb-audit`) agora consulta diariamente os cadastros oficiais completos de **OFAC SDN (EUA), ONU, União Europeia (EU FSFi) e Reino Unido (UK OFSI)** em índice Zero-DB particionado (`<= 30 ms`), cobrindo carteiras **EVM, Solana, Bitcoin, Tron** e entidades/aliases.
Tudo com governança *fail-closed* (retorna `HTTP 503` antes de cobrar se o índice não estiver íntegro — zero falso `CLEAR`) e assinatura ECDSA secp256k1 (`_oracle_proof`) cobrindo `dataset_sha256`, `last_synced_at` e `freshness_status`.

**3. Extrator Web-to-Markdown 100% Transiente (`/api/v1/extract`)**
Para pipelines de RAG e agentes web operarem com tranquilidade jurídica:
• **0 bytes** de conteúdo de terceiros gravados em disco (`transient_only: true`);
• Respeito estrito a `robots.txt`, `X-Robots-Tag` (`noai`, `noimageai`, `noindex`), `Content-Signal` e meta tags anti-AI;
• Bloqueio automático de paywalls e HTTP `401/403` antes da cobrança;
• Blindagem SSRF + DNS rebinding com Markdown limpo por `0,0050 USDC` (1 crédito).

**4. Preços menores, Pro Pass turbinado e garantias pró-cliente**
• `/api/v1/cnpj` e `/api/v1/lei` caíram 50% → **`0,0100 USDC`** (2 créditos);
• Bundle `/api/v1/kyb-audit` (CNPJ + Sanções 4 blocos + LEI) → **`0,0250 USDC`** (5 créditos);
• **Pro Pass** subiu para **1.200 créditos** (`1.000 base + 200 bônus`, R$ 29 / 5 USDC) e a validade foi estendida para **180 dias** (Starter/Pro) e **365 dias** (Fleet — 6.000 créditos);
• Estorno automático em qualquer erro (`HTTP != 200`), retentativa grátis por 300s com `Idempotency-Key` e Spend Controls (`daily_credit_limit` e `allowed_endpoints`).

Hoje são **25 micro-serviços em produção** (incluindo o **Dossiê 360º KYB + Veredito IA**) e **27 ferramentas MCP (`v2.0.0`, Nota A no mcprush)** prontas para Claude Desktop, Cursor, n8n, LangChain, CrewAI e Coinbase AgentKit.

Teste em 10 segundos no terminal, sem cartão e sem cadastro (Sandbox 3 req/dia ou cupom `MCPTRY50` com 50 créditos grátis):

```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"
```

Links do portal, catálogo, Trust Center, MCP e GitHub no primeiro comentário. Na sua stack de agentes hoje, o que mais trava o go-live: pagamento M2M, compliance ou extração de dados?

#AgentesDeIA #InteligenciaArtificial #MCP #Compliance #KYB #Web3 #Fintech #APIs

**1º comentário (colar logo após publicar):**
Links oficiais do Grid402:
• Portal, Playground e Sandbox grátis (3 req/dia): https://grid402.online
• Catálogo dos 25 endpoints e preços transparentes: https://grid402.online/catalog
• Session Pass via Pix ou Multi-Chain USDC (<15ms): https://grid402.online/pass
• Trust Center (SLA 30d, proveniência e governança): https://grid402.online/trust
• Servidor MCP: `npx -y grid402-mcp` | Remoto: https://grid402.online/mcp
• GitHub (SDKs Python/Node + integrações): https://github.com/oliveiratc/Grid402

---

## 2. X / Twitter — Thread em Português (PT-BR)

**Post 1 (Gancho):**
Acabou de subir uma atualização pesada no **Grid402** ⚡

Mais liquidez multi-chain, compliance global em 4 blocos oficiais, extração web 100% transiente e preços até 50% menores para agentes de IA e automações B2B — sem cadastro e sem API key.

O que mudou em produção 👇🧵

**Post 2 (Dual-Facilitator + Multi-Chain):**
1/ **Liquidez Global x402 v2:**
• Roteamento **Dual-Facilitator** na Base: **Coinbase CDP** primário (CDP Bazaar v2) + fallback automático pré-liquidação para **PayAI** (invariante anti-dupla-cobrança no `/settle`)
• Gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)** + manifesto dinâmico `/.well-known/x402.json`
• Também aceita transferência direta em 6 redes, **Pix/Cartão** e **RapidAPI**

**Post 3 (Sanções 4 Blocos + Multi-Ativo):**
2/ **Sanções Oficiais em 4 Blocos (`/api/v1/ofac` e `/kyb-audit`):**
Sincronização diária Zero-DB (`<= 30 ms`) das listas completas:
• US Treasury **OFAC SDN**
• **ONU** (UN Security Council)
• **União Europeia** (EU FSFi)
• **Reino Unido** (UK OFSI)

Triagem para **EVM, Solana, BTC, Tron** e entidades — *fail-closed* (HTTP 503 sem cobrar se indisponível) + `dataset_sha256` assinado em ECDSA (`_oracle_proof`).

**Post 4 (`/extract` Transiente):**
3/ **Extrator Web-to-Markdown blindado (`/api/v1/extract`):**
• **100% transiente:** 0 bytes de terceiros gravados em disco
• Respeita `robots.txt`, `X-Robots-Tag` (`noai`), `Content-Signal` e meta tags anti-AI
• Bloqueia paywalls e HTTP 401/403 antes da cobrança
• Proteção SSRF/DNS rebinding por **0,0050 USDC** (1 crédito)

**Post 5 (Preços e Session Pass):**
4/ **Preços menores + Session Pass turbinado:**
• `/cnpj` e `/lei`: corte de 50% → **0,0100 USDC** (2 cr)
• `/kyb-audit` (CNPJ + Sanções 4 blocos + LEI): **0,0250 USDC** (5 cr)
• **Pro Pass:** subiu para **1.200 créditos** (5 USDC / R$ 29 · 180 dias)
• **Fleet Pass:** **6.000 créditos** (20 USDC / R$ 115 · 365 dias)
• Estorno automático em falhas + retentativa grátis por 300s (`Idempotency-Key`)

**Post 6 (CTA):**
25 micro-serviços UP + 27 ferramentas MCP (`v2.0.0`, Nota A no mcprush).

Teste grátis agora (3 req/dia sem carteira ou cupom `MCPTRY50` com 50 créditos):
```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"
```

🌐 https://grid402.online
⚡ Passes: https://grid402.online/pass
📦 MCP: `npx -y grid402-mcp`

---

## 3. X / Twitter — Thread Global (EN)

**Tweet 1 (Hook):**
Major production upgrade live on **Grid402** (`grid402.online`) ⚡

Dual-Facilitator x402 v2 liquidity, full 4-block official sanctions screening, 100% transient RAG web extraction, and up to 50% lower M2M pricing — zero signup, zero API keys.

What just shipped 👇🧵

**Tweet 2 (Dual-Facilitator + Solana):**
1/ **Global x402 v2 Liquidity:**
• **Coinbase CDP** primary facilitator on Base (CDP Bazaar v2 compliant) with safe pre-settle (`/verify`) fallback to **PayAI** — strict anti-double-charge state machine on `/settle`
• Gasless x402 across **Base, Polygon, Arbitrum & Solana (SPL USDC)** + dynamic `/.well-known/x402.json`
• Plus direct transfers on 6 chains, **Pix/Card**, and **RapidAPI**

**Tweet 3 (4-Block Sanctions + Multi-Asset AML):**
2/ **Full 4-Block Official Sanctions + Multi-Asset AML (`/api/v1/ofac` & `/kyb-audit`):**
Daily-synced Zero-DB sharded index (`<= 30ms`):
• US **OFAC SDN**
• **UN** Security Council
• **EU** FSFi
• **UK** OFSI

Screens **EVM, Solana, BTC, Tron** wallets & entities — *fail-closed* (HTTP 503 pre-charge if offline) with `dataset_sha256` & ECDSA `_oracle_proof`.

**Tweet 4 (100% Transient `/extract`):**
3/ **Legally Hardened Web-to-Markdown (`/api/v1/extract`):**
• **100% transient** (`transient_only: true` — 0 bytes of third-party text written to disk)
• Strictly honors `robots.txt`, `X-Robots-Tag` (`noai`), `Content-Signal`, anti-AI meta tags & blocks paywalls/401/403 before settlement
• SSRF & DNS-rebinding hardened for `$0.0050 USDC`

**Tweet 5 (Pricing & Agent Guarantees):**
4/ **Lower Prices + Upgraded Session Passes:**
• `/cnpj` & `/lei` cut 50% → `$0.0100 USDC` (2 credits)
• `/kyb-audit` (CNPJ + 4-block sanctions + LEI) → `$0.0250 USDC` (5 credits)
• **Pro Pass:** boosted to **1,200 credits** ($5 USDC · 180d) · **Fleet:** **6,000 credits** ($20 USDC · 365d)
• Auto-refund on any `HTTP != 200` + free 300s retries via `Idempotency-Key`

**Tweet 6 (CTA):**
25 live APIs (incl. **KYB Dossier 360° + AI Verdict**) · 27 MCP tools (`v2.0.0`, mcprush Grade A, Glama & x402scan verified).

Try free now (3 req/day sandbox or `MCPTRY50` for 50 free credits):
```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"
```

🌐 https://grid402.online | 📦 `npx -y grid402-mcp` | 🔐 https://grid402.online/trust

---

## 4. Farcaster — Casts Direcionados (`/base`, `/solana`, `/ai-agents`)

### Cast `/base`
> Shipped a major upgrade to **Grid402** (`https://grid402.online`) ⚡
>
> • **Dual-Facilitator x402 v2:** **Coinbase CDP** primary on Base (CDP Bazaar compliant) + safe pre-settle **PayAI** fallback (zero double-charge on `/settle`) across Base, Polygon, Arbitrum & **Solana SPL USDC**
> • **4-Block Official Sanctions (`/api/v1/ofac`):** daily-synced US OFAC SDN, UN, EU FSFi & UK OFSI for EVM/Solana/BTC/Tron wallets & entities — fail-closed (`503` pre-charge) + `dataset_sha256` + ECDSA `_oracle_proof`
> • **100% Transient `/api/v1/extract`:** 0 bytes on disk, honors `robots.txt`, `noai`, `Content-Signal` & paywall blocks
> • **Upgraded Session Passes:** Starter 400 cr (2 USDC · 180d), Pro **1,200 cr** (5 USDC · 180d), Fleet **6,000 cr** (20 USDC · 365d) + auto-refund on failures & `Idempotency-Key`
>
> 25 live APIs · 27 MCP tools (`npx -y grid402-mcp`). Try free:
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"`

### Cast `/solana`
> Solana AI agents can now settle natively in **SPL USDC** on **Grid402** ⚡
>
> 25 verifiable public-data & network APIs (4-block OFAC/UN/EU/UK sanctions + multi-asset AML, KYB Dossier 360 + AI Verdict, GLEIF LEI, 100% transient `/extract`, DNS/SSL/ASN, ECDSA notarization) — zero signup, zero API keys.
>
> Gasless x402 v2 via dynamic `/.well-known/x402.json` or `<15ms` Session Passes (Pro: 1,200 cr / 5 USDC / 180d) at https://grid402.online/pass.
>
> Free sandbox: `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"`

### Cast `/ai-agents`
> Three things production AI agents actually need: reliable x402 settlement, real sanctions coverage, and legally safe RAG extraction.
>
> **Grid402 v2.0.0 MCP** (27 tools, mcprush Grade A, Glama & x402scan verified) just shipped:
> • Dual-Facilitator Coinbase CDP + PayAI (Base, Polygon, Arbitrum, Solana)
> • Full 4-block official sanctions (OFAC/UN/EU/UK) across EVM/Solana/BTC/Tron — fail-closed + ECDSA `_oracle_proof`
> • 100% transient `/extract` honoring `robots.txt`, `noai`, `Content-Signal` & paywalls
> • `/cnpj` & `/lei` cut 50% (`0.0100 USDC`), auto-refund on errors, free 300s `Idempotency-Key` retries
>
> `npx -y grid402-mcp` · Remote: `https://grid402.online/mcp`

---

## 5. Comunidades (Discord / Telegram / WhatsApp)

### Versão PT-BR (Devs BR, IA/Agentes, n8n, Fintech)
> Pessoal, subimos uma atualização grande no **Grid402** (`https://grid402.online`) — hub de 25 APIs de dados públicos e utilitários M2M para agentes de IA (sem cadastro e sem API key):
>
> • **Liquidez Global x402 v2:** Dual-Facilitator **Coinbase CDP + PayAI** na Base (sem risco de dupla cobrança) e liquidação gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)**, além de **Pix/Cartão** e **RapidAPI**.
> • **Sanções Oficiais em 4 Blocos (`/api/v1/ofac` e `/kyb-audit`):** base diária completa de **OFAC SDN, ONU, UE FSFi e UK OFSI** para carteiras **EVM, Solana, BTC, Tron** e empresas — modo *fail-closed* (HTTP 503 sem cobrar se indisponível) + `dataset_sha256` assinado em ECDSA.
> • **Extrator Web-to-Markdown 100% Transiente (`/api/v1/extract`):** 0 bytes gravados em disco, respeita `robots.txt`, `X-Robots-Tag: noai`, `Content-Signal` e bloqueia paywalls pré-cobrança.
> • **Preços menores + Passes turbinados:** `/cnpj` e `/lei` caíram 50% para **0,0100 USDC** (2 cr); `/kyb-audit` foi para **0,0250 USDC** (5 cr); **Pro Pass** subiu para **1.200 créditos** (R$ 29 / 180 dias) e **Fleet** para **6.000 créditos** (R$ 115 / 365 dias), com estorno automático em falhas e retentativa grátis (`Idempotency-Key`).
>
> Dá pra testar grátis agora no terminal (3 req/dia ou cupom `MCPTRY50` com 50 créditos):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"`
>
> MCP (27 tools): `npx -y grid402-mcp` | Catálogo: https://grid402.online/catalog

### Versão EN (Base / Solana / x402 / MCP Discords)
> Hey everyone! Sharing a major production update on **Grid402** (`https://grid402.online`), our verifiable public-data & network utilities hub for AI agents (25 APIs, 27 MCP tools `v2.0.0`):
>
> • **Dual-Facilitator x402 v2 & Multi-Chain:** **Coinbase CDP** primary on Base (CDP Bazaar compliant) with safe pre-settle **PayAI** fallback (anti-double-charge state machine on `/settle`), plus gasless USDC across **Base, Polygon, Arbitrum, and Solana (SPL USDC)** and dynamic `/.well-known/x402.json`.
> • **4-Block Official Sanctions (`/api/v1/ofac` & `/kyb-audit`):** daily-synced Zero-DB sharded index (`<=30ms`) of **US OFAC SDN, UN, EU FSFi, and UK OFSI** across **EVM, Solana, BTC, Tron** wallets & entities — fail-closed (`503` before settlement if unavailable), `dataset_sha256`, and ECDSA `_oracle_proof`.
> • **100% Transient `/api/v1/extract`:** 0 bytes of third-party content stored on disk, honoring `robots.txt`, `X-Robots-Tag` (`noai`), `Content-Signal`, anti-AI meta tags, and paywall/401/403 blocks pre-charge.
> • **New pricing & Passes:** `/cnpj` & `/lei` cut 50% to `$0.0100 USDC`, `/kyb-audit` at `$0.0250 USDC`, Pro Pass boosted to **1,200 credits** ($5 USDC / 180d), Fleet **6,000 credits** ($20 USDC / 365d), auto-refund on any non-200 error, and free 300s retries via `Idempotency-Key`.
>
> Free sandbox (3 req/day or coupon `MCPTRY50` for 50 free credits):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"`
>
> Repo: https://github.com/oliveiratc/Grid402 — feedback is very welcome!

---

## 6. E-mail para Base, Parceiros e Integradores B2B

### Versão PT-BR (Integradores Brasil, B2B e Usuários de Session Pass / Pix)

- **Assunto (Opção A):** Novidades no Grid402: CNPJ e LEI pela metade do preço, Pro Pass com 1.200 créditos e Sanções em 4 blocos oficiais
- **Assunto (Opção B):** [Grid402] Atualização de produção: Coinbase CDP + Solana x402, compliance 4 blocos e /extract 100% transiente
- **Preheader:** 25 APIs M2M, 27 ferramentas MCP e mais fôlego no Session Pass (validade de até 365 dias) com estorno automático em falhas.

**Corpo do e-mail:**

Olá,

Acabamos de liberar em produção uma atualização estrutural no **Grid402** (`https://grid402.online`) focada em três pilares pedidos por quem roda agentes de IA e automações B2B em escala: **mais liquidez de pagamento**, **compliance auditável de ponta a ponta** e **custo menor por consulta**.

Confira o que já está ativo para sua integração:

**1. Preços menores em KYB e mais créditos no Session Pass**
- **`/api/v1/cnpj` e `/api/v1/lei` caíram 50%:** agora custam **`0,0100 USDC` (2 créditos)** cada — chegando a ~R$ 0,038 por consulta no Fleet Pass.
- **`/api/v1/kyb-audit`** (CNPJ + Sanções 4 blocos + LEI em 1 chamada): **`0,0250 USDC` (5 créditos)** — 37,5% mais barato que chamar os três endpoints separados.
- **Pro Pass turbinado:** passou de 1.000 para **`1.200 créditos`** (`1.000 base + 200 bônus`) pelos mesmos **R$ 29 / 5 USDC**.
- **Validade estendida:** **180 dias** no Starter (400 cr · R$ 12 / 2 USDC) e Pro (1.200 cr), e **365 dias** no Fleet (6.000 cr · R$ 115 / 20 USDC).
- **Garantias pró-cliente:** estorno automático em qualquer falha (`HTTP != 200`), retentativa gratuita por 300s com `Idempotency-Key` e Spend Controls (`daily_credit_limit` e `allowed_endpoints`) com extrato Zero-PII em JSON/CSV.

**2. Triagem oficial de sanções em 4 blocos + AML Web3 multi-ativo**
- `/api/v1/ofac` e `/api/v1/kyb-audit` agora consultam um índice Zero-DB sincronizado diariamente (`<= 30 ms`) com os 4 cadastros oficiais completos: **OFAC SDN (EUA)**, **ONU**, **União Europeia (EU FSFi)** e **Reino Unido (UK OFSI)**.
- Suporte nativo a carteiras **EVM, Solana, Bitcoin, Tron** e nomes/aliases de entidades.
- Comportamento *fail-closed*: se o índice estiver indisponível, retorna `HTTP 503` antes de debitar créditos ou liquidar pagamento — eliminando falsos `CLEAR`. Cada resposta traz `dataset_sha256`, `last_synced_at` e `freshness_status` cobertos pela assinatura ECDSA `_oracle_proof`.

**3. Liquidez Global x402 v2 (Coinbase CDP + PayAI + Solana)**
- Roteamento **Dual-Facilitator** na rede Base (**Coinbase CDP** como primário + fallback automático pré-liquidação para **PayAI**, com proteção estrita contra dupla cobrança) e liquidação gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)**, além de **Pix/Cartão** (`/pass`) e **RapidAPI**.

**4. Extrator Web-to-Markdown 100% Transiente (`/api/v1/extract`)**
- Operação com **0 bytes** de conteúdo de terceiros armazenados em disco (`transient_only: true`), respeito estrito a `robots.txt`, `X-Robots-Tag: noai`, `Content-Signal` e bloqueio automático de paywalls/401/403 antes da cobrança.

**Teste agora em 10 segundos (Sandbox 3 req/dia ou cupom `MCPTRY50` para 50 créditos grátis):**

```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"
```

- **Catálogo de 25 endpoints:** https://grid402.online/catalog
- **Emitir ou gerenciar Session Pass:** https://grid402.online/pass
- **Trust Center (SLA 30d e proveniência):** https://grid402.online/trust
- **MCP Server (27 tools `v2.0.0`):** `npx -y grid402-mcp` | https://grid402.online/mcp

Qualquer dúvida técnica ou pedido de cota dedicada, é só responder a este e-mail.

Abraço,
**Thiago Campos** — Grid402 Network Intelligence (`contato@grid402.online`)

---

### Versão EN (Global Partners, AI Agent Builders & x402 Integrators)

- **Subject (Option A):** Grid402 Production Update: Dual-Facilitator (Coinbase CDP + PayAI), Solana x402, 4-Block Sanctions & 50% Lower KYB Rates
- **Subject (Option B):** [Grid402] Major release: 4-block official sanctions, 100% transient /extract & upgraded 1,200-credit Pro Pass
- **Preheader:** 25 verifiable M2M APIs, 27 MCP tools (v2.0.0), zero-disk web extraction, and auto-refund guarantees.

**Email body:**

Hi there,

We just shipped a major production upgrade to **Grid402** (`https://grid402.online`) designed around the three blockers teams hit when running autonomous agents in production: **x402 settlement liquidity**, **auditable sanctions compliance**, and **legally safe web extraction**.

Here is what is live now:

**1. Dual-Facilitator x402 v2 Liquidity (Coinbase CDP + PayAI + Solana)**
- **Dual-Facilitator routing on Base:** **Coinbase CDP** serves as primary facilitator (full CDP Bazaar v2 compliance) with automatic pre-settle (`/verify`) fallback to **PayAI** and a strict anti-double-charge state machine on `/settle`.
- **Multi-chain gasless USDC:** Native x402 v2 settlement across **Base, Polygon, Arbitrum, and Solana (SPL USDC)** via dynamic `/.well-known/x402.json` — alongside direct 6-chain transfers, **Pix/Card**, and **RapidAPI**.

**2. Full 4-Block Official Sanctions + Multi-Asset Web3 AML (`/api/v1/ofac` & `/api/v1/kyb-audit`)**
- Daily-synced Zero-DB sharded index (`<= 30ms`) covering **US Treasury OFAC SDN**, **UN Security Council**, **EU FSFi**, and **UK OFSI**.
- Screens **EVM, Solana, Bitcoin, Tron** addresses and entity names/aliases + live Base Mainnet AML telemetry.
- **Fail-closed governance:** returns `HTTP 503` before charging if the official index is unavailable (zero false `CLEAR`s), and signs `dataset_sha256`, `last_synced_at`, and `freshness_status` inside `_meta` + ECDSA secp256k1 `_oracle_proof`.

**3. 100% Transient Web-to-Markdown Extractor (`/api/v1/extract`)**
- **Zero bytes** of third-party page text written to disk (`transient_only: true`).
- Strictly honors `robots.txt` (RFC 9309), `X-Robots-Tag` (`noai`, `noimageai`, `noindex`), `Content-Signal`, anti-AI meta tags, and blocks paywalls / HTTP `401/403` before settlement — SSRF-hardened for `$0.0050 USDC` (1 credit).

**4. Up to 50% Lower KYB Pricing & Upgraded Session Passes**
- **`/api/v1/cnpj` and `/api/v1/lei` cut by 50%** to **`$0.0100 USDC` (2 credits)**; **`/api/v1/kyb-audit`** bundle is now **`$0.0250 USDC` (5 credits)**.
- **Pro Pass** boosted from 1,000 to **1,200 credits** (`$5 USDC`), with extended validity of **180 days** (Starter 400 cr / Pro 1,200 cr) and **365 days** (Fleet 6,000 cr · `$20 USDC`).
- **Pro-customer guarantees:** automatic credit refund on any `HTTP != 200` failure, free 300s retries via `Idempotency-Key`, and Spend Controls (`daily_credit_limit`, `allowed_endpoints`).

**Try it in 10 seconds (free 3 req/day sandbox or `MCPTRY50` for 50 free credits):**

```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"
```

- **25-endpoint catalog:** https://grid402.online/catalog
- **Session Pass & Multi-Chain Top-Up:** https://grid402.online/pass
- **Trust Center:** https://grid402.online/trust
- **MCP Server (27 tools, mcprush Grade A):** `npx -y grid402-mcp` | Remote: https://grid402.online/mcp

Reply directly if you need custom daily limits, RapidAPI onboarding, or integration support.

Best,
**Thiago Campos** — Grid402 Network Intelligence (`contato@grid402.online`)

---

## 7. Roteiro de Vídeo Curto (45–60s — Reels / Shorts / LinkedIn / X)

**Formato:** gravação de tela (terminal + navegador em `grid402.online` / `/catalog` / `/pass`) com legenda dinâmica e ritmo direto.

| Tempo | Visual / Tela | Narração (PT-BR) |
|---|---|---|
| **00–06s** | Tela dividida: agente falhando com erro de API key / portal travado vs. terminal limpo no tema `#060B19` do Grid402. | *"Seus agentes de IA ainda travam em API key, mensalidade cara ou dado sem auditoria? Acabamos de atualizar o Grid402."* |
| **06–16s** | Terminal rodando `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"` e destacando `OFAC SDN`, `UN`, `EU FSFi`, `UK OFSI`, `dataset_sha256` e `_oracle_proof`. | *"Primeiro: sanções globais de verdade. O `/ofac` agora varre 4 blocos oficiais — EUA, ONU, União Europeia e Reino Unido — para carteiras EVM, Solana, Bitcoin e empresas, em modo fail-closed e assinado por criptografia ECDSA."* |
| **16–27s** | Terminal chamando `/api/v1/extract` (mostrando `transient_only: true` e bloqueio de `robots.txt`/`noai`) + abertura rápida de `grid402.online/pass` exibindo Base, Polygon, Arbitrum, Solana e Pix. | *"Segundo: o extrator Web-to-Markdown ficou 100% transiente — zero bytes gravados em disco, respeitando `robots.txt`, `noai` e paywalls. E o pagamento ganhou Dual-Facilitator Coinbase CDP + PayAI com liquidação gasless até na Solana, além de Pix."* |
| **27–42s** | Tela de `grid402.online/catalog` destacando `/cnpj` e `/lei` por `0.0100 USDC` (2 cr), `/kyb-audit` por `0.0250 USDC` (5 cr), **Pro Pass 1.200 créditos (180d)** e bloco de garantias (estorno automático + `Idempotency-Key`). | *"Terceiro: cortamos `/cnpj` e `/lei` pela metade, dobramos a validade do Session Pass para 180 e 365 dias com Pro Pass de 1.200 créditos — e qualquer erro estorna seus créditos automaticamente."* |
| **42–55s** | Editor (Cursor / Claude Desktop) mostrando `npx -y grid402-mcp` com **27 ferramentas MCP** e URL `grid402.online` + cupom `MCPTRY50`. | *"São 25 APIs M2M e 27 ferramentas MCP prontas para usar sem cadastro. Testa grátis agora em `grid402.online` ou usa o cupom `MCPTRY50` para ganhar 50 créditos."* |

---

## 8. Checklist Pré-Publicação (Produção Hostinger)

Antes de disparar os anúncios públicos que citam **Coinbase CDP**, **Solana gasless** e **4 blocos de sanções** ao vivo, confirme os 3 itens operacionais do [PRD da Fase 1](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/fase1-liquidez-global-e-blindagem-juridica.md#L182-L186):

1. `GRID402_CDP_API_KEY_ID` e `GRID402_CDP_API_KEY_SECRET` ativos no `.env` de produção e validados com `php scripts/validate_cdp_bazaar.php --live` + 1 liquidação real na Base;
2. `GRID402_SOLANA_X402_ENABLED=true` após confirmar saldo SOL e ATA SPL USDC com `php scripts/check_solana_readiness.php`;
3. Primeiro sync e cron diário de `php scripts/sync_sanctions_index.php` executados em produção (garantindo índice fresco e evitando `HTTP 503` em `/api/v1/ofac` e `/api/v1/kyb-audit`).
