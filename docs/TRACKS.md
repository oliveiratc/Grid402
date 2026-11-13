# Grid402 — Tração M2M: Plano de Distribuição (14 dias)

**Estado em 2026-11-13:** produto no ar, 22 serviços, prices atualizados (0.0050–0.0300 USDC), npm `grid402-mcp` publicado, PyPI `grid402` publicado, diretórios submetidos pelo mantenedor (Agentic Market, Glama, mcp.so, PulseMCP), aut deploy contínuo (main → deploy → Hostinger).

**Objetivo dia 14:** primeiras 3 transações pagas reais na Base Mainnet e um serviço descoberto por buscadores/agenticos.

> Honestidade bruta: conversão ainda não existe. 720 consultas, 16 pagas históricas, 1 pagante provável. Distribuição só vira receita se o produto resolver um problema concreto de quem tem USDC na carteira ou quem compra Session Pass.

---

## Semana 1 — Destravar canais que já existem

### 1. Farcaster/Twitter — posts técnicos (use os textos de `DIVULGACAO.md` §3, já atualizados com 0.0050 USDC)

**EN /base + /ai-agents:**
```
Built an autonomous open data hub on @base using HTTP 402: Grid402.

Agents and pipelines query real life APIs with zero signup and instant USDC micro-settlement on Base Mainnet:
- CNPJ + Simples Nacional + CEIS/CNEP sanções
- CEP/IBGE + Feriados bancários ANBIMA + Dias úteis
- Forex USD/EUR/GBP → BRL
- Compliance Score 360, OFAC/LEI AML

$0.0050 USDC per query · Session Pass <15ms · Zero PII
https://grid402.online — self-registering agents: curl -H "X-Payment: demo" …/api/v1/compliance
```

**PT (LinkedIn / X / Brasil):**
```
Lançamos o Grid402 (https://grid402.online): dados públicos brasileiros + utilitários de rede para agentes de IA, via HTTP 402 e USDC na Base.

Agentes não precisam de cadastro nem chave de API: pagam por consulta (browsers podem usar /api/v1/summary grátis para testar).
- CNPJ, Simples, CEIS/CNEP, Compliance 360
- CEP/IBGE, feriados/dias úteis ANBIMA
- Forex, cripto, DNS, SSL, ASN

US$ 0.0050 por consulta. Chaves não são guardadas: só prova ECDSA de que o oráculo assinou o dado.
```

### 2. Show HN (rascunho)
```
Title: Show HN: Grid402 – Brazilian public data APIs for AI agents, paid via x402 (USDC on Base)

I built a pay-per-call data hub for autonomous agents. No signup, no API keys — the HTTP 402 response carries the price, you pay USDC on Base, and get the answer with a cryptographic oracle proof.

Includes CNPJ/Simples/CEIS sanctions lookups (KYB/AML), CEP/IBGE, ANBIMA banking calendar, forex, and network utilities (DNS, SSL, ASN).

Free trial: curl -H "X-Payment: demo" https://grid402.online/api/v1/summary

Happy to answer questions on the protocol (x402), pricing model, or the Brazilian public-data surfaces.
```

### 3. Awesome Lists (PRs de 1 linha)
- `awesome-base` → seção _AI / Infrastructure / Micropayments_
- `awesome-ai-agents` → _Agent Infrastructure_
- `awesome-brasil` → _APIs Públicas_

### 4. TabNews (PT-BR)
```
Grid402: dados públicos do Brasil + utilitários de rede para agentes de IA via HTTP 402

https://grid402.online
Quer testar grátis: curl -H "X-Payment: demo" https://grid402.online/api/v1/cep?cep=01310-100
```

### 5. n8n community (Discord/forum) — caso de uso CNPJ compliance
Publique o workflow pronto `examples/n8n/grid402_cnpj_compliance_workflow.json` com texto:
```
Onboarding B2B no Brasil sem API keys: workflow n8n que consulta CNPJ + CEIS/CNEP e retorna score de risco. Testa em 30s com X-Payment: demo, vai pra produção com Session Pass (<15ms).
Repo: https://github.com/oliveiratc/Grid402
```

---

## Semana 2 — Autoridade e parcerias pequenas

- **LinkedIn B2B**: 1 post por dia abordando 1 dor de compliance/rep (ex.: “onboarding de PJ em <30s sem abrir planilha da Receita”, “consulta CEP+CNPJ em lote para ERP logístico”).
- **Parcerias**: 5 mensagens diretas para builders de agentes/automação no Brasil (n8n, Make, Zapier) oferecendo Session Pass de cortesia para teste — sem pedir nada em troca, peça apenas feedback.
- **Badges** em READMEs: `![Grid402](https://grid402.online/badge/system.svg)` — cada repo que usar o badge vira backlink.
- **YouTube short / Loom de 3 min**: "Como meu agente paga por dados sem cadastro" — demonstração do playground `/#playground` com carteira Web3 real.
- **Blog post técnico**: "Como o HTTP 402 emenda agentes e dados públicos no Brasil" — publicar em Dev.to / Medium e apontar para grid402.online.

---

## O que NÃO fazer

- Não comprar seguidores/tráfego pago antes de conversão orgânica aparecer (hoje: 6% 402→pago, 1.4% sandbox).
- Não publicar métricas/provas que não existem ("usamos em produção", "milhares de consultas/dia").
- Não spammar fóruns com o mesmo link repetido.
- Não unificar tarifas com outros providers ("mais barato que X") — o foco é ser o único caminho simples para dados públicos BR com prova de oráculo.

---

## Métricas para acompanhar (`/admin`)

- **Consultas pagas / 402 emitidos** — alvo: manter >6% e subir.
- **Adoção sandbox** — alvo: >10% (se não subir, o onboarding da resposta 402 está confuso).
- **Passes ativos** — alvo: ≥1.
- **Fontes de pagamento** (tx_hash vs pass vs facilitator) — mostra qual canal de pagamento melhor performa.

---

## Checklist rápido

- [ ] Posts Farcaster/Twitter no ar (Frase EN + PT)
- [ ] Show HN publicado
- [ ] Awesome Lists PRs abertos (3)
- [ ] TabNews publicado
- [ ] Workflow n8n no Discord/forum
- [ ] Post LinkedIn dia 1 e 2 (B2B compliance)
- [ ] 5 DMs para builders BR (cortesia, pedir feedback)
- [ ] 1 post técnico dev.to/Medium com link para /llms.txt
