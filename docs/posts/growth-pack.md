# Growth pack — textos prontos para publicar enquanto o PR awesome-x402 estiver em revisão
> Publicar é responsabilidade do mantenedor; todos os textos abaixo já são honestos e consistentes com a produção atual (preços, sandbox, payTo, SDKs).

---

## 1) Product Hunt (launch grátis)

**Tagline:** Grid402 — Brazilian public data APIs for AI agents, pay-per-call in USDC on Base

**Description:**
Grid402 is the first pay-per-call data hub for autonomous AI agents built on Brazil's public registries.
22 endpoints: CNPJ situation, Simples Nacional, CEIS/CNEP sanctions, CEP/IBGE, ANBIMA holidays/workdays, Compliance 360, forex, crypto, DNS/SSL/ASN.
Built on x402 v2 (HTTP 402): your agent reads the price from the 402 response, pays micro-USDC on Base, and gets oracle-signed ECDSA proofs — no signup, no API keys, no contracts.
Free sandbox (30 req/h), Session Pass (2 USDC = 400 calls) for <15ms latency.
MCP server: `npx grid402-mcp` · Python: `pip install grid402`

**CTA / first comment:** Try the sandbox free: `curl -H "X-Payment: demo" "https://grid402.online/api/v1/compliance?cnpj=00000000000191"`

---

## 2) Reddit — r/LocalLLaMA (feedback honesto, sem parecer promoção)

I built an open data hub for AI agents that pays per call using x402 (USDC on Base) — Brazilian public data mainly (CNPJ, Simples, CEIS/CNEP sanctions, CEP, ANBIMA calendar). Anonymous access, no API keys, free sandbox.
Harsh feedback welcome especially on the x402 flow design — did the 402 response carry everything an agent needs to pay without human help?

`curl -H "X-Payment: demo" "https://grid402.online/api/v1/compliance?cnpj=00000000000191"` works right now. Not trying to sell anything — genuinely want to know what breaks or is confusing.

## Reddit — r/n8n (mesmo ângulo, caso de uso concreto)

For anyone automating B2B enrichment in Brazil: I shipped a n8n template that does CNPJ + CEIS/CNEP + CEP with zero API keys (x402 micro-payment or free sandbox). Importable from `examples/n8n/` in the repo: https://github.com/oliveiratc/Grid402

---

## 3) Dev.to (artigo curto, canônico → grid402.online)

**Title:** Brazilian Public Data for AI Agents via x402 — No Signup, No API Keys

Brazil has rich, free public registries (Receita Federal, CGU, IBGE, ANBIMA) that are painful to automate — until now.
Grid402 (https://grid402.online) is a pay-per-call API hub for agents: CNPJ situation, Simples Nacional, CEIS/CNEP sanctions, Compliance 360, CEP/IBGE, banking calendar — all oracle-signed, AI-readable.

Try: `curl -H "X-Payment: demo" "https://grid402.online/api/v1/compliance?cnpj=00000000000191"`
MCP: `npx grid402-mcp` · Python: `pip install grid402`

---

## 4) GitHub Discussions (no seu próprio repo)

**Title:** Ask Grid402 — Brazilian public data for your AI agents

**Body:** If your agent needs CNPJ, sanctions, CEP, ANBIMA calendar or anything Brazilian — ask here, I'll answer with the exact curl. Pay-per-call via x402 (USDC on Base), sandbox is free.

---

## 5) Respostas-modelo para perguntas frequentes (colar nos comentários)

- **“O que é x402?”** → Um protocolo HTTP 402 em que o agente paga em USDC na Base diretamente pelo recurso. Sem cadastro, sem chave: o preço vem no header 402 e a carteira do agente assina o pagamento.
- **“X vs Stripe?”** → Stripe = humano, cartão, contrato. x402 = máquina, micro-USDC (centavos), sem conta — pensado para agentes autônomos.
- **“Preciso de carteira?”** → Sim, na Base Mainnet (ex.: Coinbase Smart Wallet, MetaMask). Você pode testar sem pagar nada: `X-Payment: demo`.
- **“Quanto custa?”** → 0,005–0,03 USDC por chamada; Session Pass 2 USDC = 400 chamadas.

---

## Regras de ouro antes/durante publicar

- Consistência com o site: nome, preço, payTo, wallet — tudo igual a produção.
- Pitch honesto — o que é, o que faz, onde está. Sem “o melhor do Brasil”.
- Responda cada comentário, mesmo que seja “thanks, I'll check”.
- Sem promessas de desconto ou freebies complexos; o sandbox grátis já é o trial.
- Sem spam nos mesmos grupos com variações do mesmo texto (plataforma bane).
