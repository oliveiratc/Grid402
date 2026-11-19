# Growth pack — textos prontos para Product Hunt, Reddit, Dev.to e GitHub Discussions
> Todos os textos abaixo estão 100% sincronizados com a produção atual (`23` serviços, `25` ferramentas MCP, faixa `0.0050`–`0.0500 USDC`, Multi-Chain em 6 redes: 5 EVM + Solana SPL, Sandbox `5 req/h/IP`, Session Pass `1 crédito = 0.0050 USDC`).

---

## 1) Product Hunt (launch grátis)

**Tagline:** Grid402 — Verifiable public data & KYB AI Verdict for agents, paid via Multi-Chain x402

**Description:**
Grid402 is a verifiable pay-per-call data & network intelligence hub for autonomous AI agents (23 endpoints, 25 MCP tools — mcprush Grade A, Glama & x402scan Verified).
Flagship `GET /api/v1/dossier` (`0.0500 USDC`) delivers a complete KYB corporate dossier (Federal Revenue CNPJ + Simples/MEI + CGU CEIS/CNEP sanctions) with a deterministic **AI Verdict** (`APPROVED` / `FLAGGED` / `REJECTED`), Zero-PII, and offline-verifiable ECDSA secp256k1 `_oracle_proof`.
Also includes GLEIF LEI (CC0), OFAC SDN + Web3 AML, CEP/IBGE, ANBIMA holidays/workdays, ECB FX, crypto spot, and DNS/SSL/ASN/domain-audit.
Pay per call (`0.0050`–`0.0500 USDC`) across **6 chains** (Base, Arbitrum, Polygon, Optimism, Ethereum + **Solana SPL USDC**) or top up `<15ms` Session Passes (Starter: 2 USDC / R$ 12 = 400 credits; Pro: 5 USDC = 1,000 credits; Fleet: 20 USDC = 4,000 base + 2,000 bonus = 6,000 credits).
Free sandbox: `5 req/h per IP` via `X-Payment: demo`.
MCP server: `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp` · Python: `pip install grid402`

**CTA / first comment:** Try the free sandbox right now: `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"`

---

## 2) Reddit — r/LocalLLaMA / r/AI_Agents (feedback técnico, sem parecer propaganda)

I built a verifiable open-data & KYB hub for AI agents paid per call via HTTP x402 — now supporting native USDC across **6 chains** (Base, Arbitrum, Polygon, Optimism, Ethereum + Solana SPL) plus a new single-call KYB Dossier (`/api/v1/dossier`) that returns a deterministic `APPROVED` / `FLAGGED` / `REJECTED` verdict with an offline-verifiable ECDSA `_oracle_proof` and Zero-PII.

Harsh feedback welcome especially on two things:
1. Does the HTTP 402 response carry everything an autonomous agent needs to settle without human intervention?
2. Are the 25 MCP tool schemas (`npx -y grid402-mcp` or remote `https://grid402.online/mcp`) clear enough for tool-calling models?

Free 5 req/h sandbox works right now with zero signup:
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"`

## Reddit — r/n8n (caso de uso concreto)

For anyone automating B2B KYB enrichment in Brazil: I shipped a zero-API-key n8n workflow template (`examples/n8n_cnpj_compliance_pix.json`) that queries Federal Revenue CNPJ + Simples/MEI + CGU CEIS/CNEP sanctions (or the new `/api/v1/dossier` with an AI Verdict) + CEP/IBGE. Works with free sandbox (`X-Payment: demo`), Pix/Card Session Pass, or Multi-Chain USDC (Base/Arbitrum/Polygon/Optimism/Ethereum/Solana): https://github.com/oliveiratc/Grid402

---

## 3) Dev.to (artigo curto, canônico → grid402.online)

**Title:** Multi-Chain x402 & KYB AI Verdict: Verifiable Public Data for AI Agents (5 EVM + Solana)

AI agents shouldn't scrape fragile government portals or hold static API keys just to verify a supplier.
Grid402 (https://grid402.online) exposes 23 verifiable public-data & network intelligence endpoints (25 MCP tools — Grade A on mcprush, verified on Glama & x402scan), now accepting native USDC across **Base, Arbitrum, Polygon, Optimism, Ethereum, and Solana SPL** (plus BRL Pix/Card).

Flagship endpoint: `GET /api/v1/dossier?cnpj=00000000000191` (`0.0500 USDC` / 10 credits) — combines Federal Revenue CNPJ + Simples/MEI + CGU CEIS/CNEP sanctions into one Zero-PII JSON with a deterministic AI Verdict (`APPROVED`/`FLAGGED`/`REJECTED`) and ECDSA secp256k1 `_oracle_proof`.

Try free (5 req/h sandbox):
`curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"`

MCP: `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp` · Python: `pip install grid402` · LangChain/CrewAI quickstart: `examples/langchain_quickstart.py`

---

## 4) GitHub Discussions (no seu próprio repo)

**Title:** Grid402 v3.4 Update — Multi-Chain USDC (5 EVM + Solana) + Dossier 360° KYB AI Verdict + 25 MCP Tools

**Body:** We just shipped native Multi-Chain USDC support across 6 chains (Base, Arbitrum, Polygon, Optimism, Ethereum, and Solana SPL), our flagship `/api/v1/dossier` endpoint (AI Verdict `APPROVED`/`FLAGGED`/`REJECTED`, Zero-PII, ECDSA signed), and `grid402_get_dossier_360` as our 25th MCP tool (mcprush Grade A). Drop your agent use case below and I'll reply with the exact `curl` or MCP snippet!

---

## 5) Respostas-modelo para perguntas frequentes (colar nos comentários)

- **“O que é x402?”** → Protocolo HTTP 402 em que o agente paga em USDC diretamente pelo recurso. Sem cadastro, sem API key: o preço vem no header 402 e o agente liquida via EIP-3009, transferência Multi-Chain ou Session Pass `<15 ms`.
- **“Preciso de USDC só na Base?”** → Não mais! Aceitamos USDC nativo em **6 redes** sem taxa de bridge: Base, Arbitrum, Polygon, Optimism, Ethereum e **Solana (SPL USDC)** — além de **Pix/Cartão** em Reais no `/pass`.
- **“Qual a diferença entre `/api/v1/kyb-audit` e `/api/v1/dossier`?”** → `/api/v1/kyb-audit` (`0.0200 USDC` / 4 créditos) faz triagem global combinando CNPJ + OFAC/ONU + GLEIF LEI. Já `/api/v1/dossier` (`0.0500 USDC` / 10 créditos) é o dossiê corporativo 360º brasileiro aprofundado (RFB + Simples/MEI + CGU CEIS/CNEP + Score 0–100 + **AI Verdict** `APPROVED`/`FLAGGED`/`REJECTED`).
- **“Quanto custa?”** → `0.0050` a `0.0500 USDC` por chamada (`1 crédito = 0.0050 USDC`). Session Pass: Starter `2 USDC / R$ 12` = 400 créditos; Pro `5 USDC / R$ 29` = 1.000 créditos; Fleet `20 USDC / R$ 115` = 4.000 base + 2.000 bônus (+50%) = 6.000 créditos. Sandbox grátis: `5 req/h` por IP via `X-Payment: demo`.

---

## Regras de ouro antes/durante publicar

- Consistência com o site: nome, preço (`0.0050`–`0.0500 USDC`), carteiras EVM/Solana e cota Sandbox (`5 req/h/IP`) — tudo igual à produção.
- Pitch honesto — o que é, o que faz, onde está. Sem hipérboles vazias.
- Responda cada comentário rapidamente com exemplos reais de `curl`.
- Sem spam repetitivo nos mesmos grupos no mesmo dia.
