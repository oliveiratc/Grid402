# Grid402 // Estratégia de Faturamento

> Documento operacional de receita. Complementa `PLANO_TRACAO_CONSULTAS_PAGAS.md` (diagnóstico técnico) e `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` (grants). Foco em **trazer faturamento real em USDC on-chain**.

**Última atualização:** 12/11/2026
**Owner:** Grid402 Network Intelligence
**Wallet oracle:** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`
**Domínio:** https://grid402.online

---

## 0. Nota sobre a imagem anexada
A imagem enviada não pôde ser lida por este modelo (sem suporte a entrada de imagem). Se ela contiver dados do painel `/admin` que você queira considerar, descreva-os e eu incorporo nesta estratégia.

---

## 1. Diagnóstico resumido (estado atual)

| Métrica | Valor | Fonte |
|---|---|---|
| Consultas totais | 497 | painel `/admin` |
| Consultas pagas | 15 (7,3% de conversão sobre desafios 402) | painel `/admin` |
| Adoção Sandbox | 0,0% (antes do fix) | painel `/admin` |
| SLA / Sucesso técnico | 22,7% | painel `/admin` |
| Faturamento total | US$ 0,0000 | painel `/admin` |
| Saldo on-chain (Base) | US$ 0,0000 | painel `/admin` |
| Session Passes ativos | 0 | painel `/admin` |

**Bloqueio #1 (técnico):** `GRID402_FACILITATOR_MODE=disabled` no `.env` — nenhuma liquidação real acontece. Sem ativar `settle`, nenhuma receita entra, independente de tráfego.

**Bloqueio #2 (produto):** 0 Session Passes ativos. Comprar um pass exige USDC na Base Mainnet + carteira — fricção intransponível para devs tradicionais (especialmente no Brasil).

**Bloqueio #3 (descoberta):** agentes "sem wallet" (crawlers/bots de nuvem) batem nos endpoints e desistem. Sandbox adoption era 0% antes do fix do `sandbox_test` no `402`.

**Bloqueio #4 (posicionamento):** crypto/dns/ssl são commodities com dezenas de alternativas gratuitas. O diferencial real com demanda comercial imediata é **CNPJ, CEP, Compliance, Sanções (CEIS/CNEP), LEI/GLEIF, OFAC, Dias úteis ANBIMA, Indicadores** (onboarding B2B, antifraude, KYC/KYB, logística, cobrança).

---

## 2. North Star e métricas

**North Star:** Receita on-chain mensal (USDC) na wallet oracle.

| Métrica | Mês 1 | Mês 3 | Mês 6 |
|---|---|---|---|
| Receita on-chain (USDC) | ≥ 100 | ≥ 1.000 | ≥ 5.000 |
| Session Passes vendidos/mês | ≥ 5 | ≥ 20 | ≥ 60 |
| Agentes pagadores recorrentes (MAU pagante) | ≥ 3 | ≥ 15 | ≥ 50 |
| Conversão desafio 402 → pagamento | 7% → 15% | 20% | 25% |
| ARPU (USDC/usuário/mês) | — | ≥ 3 | ≥ 5 |

Métricas de suporte: créditos consumidos, CAC por agente pagador, LTV, churn de passes, latência p95 de liquidação.

---

## 3. Fase 0 — Destravar o caixa (0–2 semanas) · "plugar o ralo"

> Objetivo: registrar a primeira receita on-chain real. Sem isso, nenhuma fase seguinte importa.

1. **Ativar liquidação real.** Mudar `GRID402_FACILITATOR_MODE` de `disabled` para `settle` (com credenciais CDP válidas) em produção; `verify_only` apenas em homologação. Reconciliar com saldo on-chain após cada deploy.
2. **Corrigir telemetria do admin.** `arpu_usdc` caindo no fallback `0.0005` mascara o ARPU real; forçar `0` quando `total_revenue_usdc = 0`.
3. **Deploy dos fixes já aplicados.**
   - `public_html/x402.php` com `sandbox_test` + link de top-up.
   - `mcp/tools.mjs` com suporte a `GRID402_SESSION_PASS`.
   - Publicar `grid402-mcp@1.8.1` no npm (`cd mcp && npm publish --access public`).
4. **Validar end-to-end.** 1 compra real de Session Pass + 1 pagamento x402 on-chain + conferir saldo da wallet oracle + conferir registro em `used_txs.json`.
5. **Executar checklist de deploy** (seção 5 de `PLANO_TRACAO_CONSULTAS_PAGAS.md`) até o fim.

**Saída esperada:** primeira receita on-chain > US$ 0,00 e ≥ 1 Session Pass ativo.

---

## 4. Fase 1 — Descoberta e distribuição (2–6 semanas) · "ser encontrado"

> Objetivo: colocar Grid402 na frente de agentes e devs que já pagam.

### 4.1 Diretórios de agentes e MCP
- Submeter ao **x402 Bazaar / x402 Ecosystem** (https://www.x402.org/ecosystem) — textos prontos em `PLANO_TRACAO_CONSULTAS_PAGAS.md` §5.
- Submeter ao **Base Ecosystem Directory** (https://www.base.org/ecosystem) — formulário pronto em `DIVULGACAO.md` §2.
- Confirmar publicação de `grid402-mcp` no **npm, Smithery.ai, Glama.ai, PulseMCP, mcp.so** (`smithery.yaml`, `glama.json`, `server-card.json` já no repo).
- Publicar SDKs `grid402` no **PyPI** e **npm** (comandos em `DIVULGACAO.md` §1).

### 4.2 Integrações de frameworks
- Garantir que `Grid402ActionProvider` (TS + Python) e `grid402-plugin-elizaos` estejam funcionais e documentados.
- Buscar menção em canais oficiais da Coinbase/Base (Discord, Farcaster).
- Exemplos de uso por framework no README e em `integrations/*/README.md`.

### 4.3 Agent discovery (SEO/GEO para LLMs)
- Manter `llms.txt`, `llms-full.txt`, `openapi.json`, `/.well-known/*` atualizados.
- Adicionar exemplos de chamada (curl + Python + Node) por endpoint.
- Garantir que o desafio `402` inclua `sandbox_test` e link de top-up (já corrigido).

### 4.4 Conteúdo e comunidade
- Publicar case study "Compliance 360 em n8n" (template pronto em `examples/n8n/grid402_cnpj_compliance_workflow.json` + `examples/n8n/README.md`) no LinkedIn, TabNews, Discord/Telegram n8n Brasil.
- Criar 3–5 tutoriais curtos (vídeo/GIF) de 30s: n8n, Python, Claude Desktop via MCP.

**Saída esperada:** ≥ 100 novos desafios 402/semana de IPs distintos; ≥ 5 agentes pagadores recorrentes.

---

## 5. Fase 2 — Remover fricção de compra (4–10 semanas) · "converter devs brasileiros"

> O maior gargalo não é falta de demanda — é falta de USDC na carteira do dev. Esta fase pode multiplicar a conversão por 10x.

### 5.1 Checkout em moeda fiduciária
- **Pix (BRL) + cartão (Stripe / Mercado Pago / Coinbase Onramp):** usuário paga R$ 5–15 e recebe `pass_g402_...` na tela, instantaneamente.
- Implementar webhook de confirmação de pagamento → geração automática do token → exibição na UI + envio por e-mail.
- Registrar cada venda em `private_data/` com reconciliação on-chain.

### 5.2 Página dedicada de compra
- Criar rota/página `/pass` (hoje só `#topup`) linkada diretamente na resposta `402`.
- Incluir tabela de pacotes (Starter/Pro/Fleet) com preço em BRL e USDC.

### 5.3 Onboarding em 30s
- Vídeo/GIF mostrando n8n → Grid402 → resultado.
- Quickstart por linguagem (curl, Python, Node, MCP) na landing.

### 5.4 Trial generoso
- Sandbox de 10 req/hora por IP (já existe) + 1 Session Pass de 100 créditos para novos usuários verificados (e-mail/whitelist).

### 5.5 Cupons e afiliados
- Código de desconto para comunidades (n8n Brasil, grupos de automação) para gerar tração inicial e depoimentos.
- Programa de afiliados simples (10% em créditos) para criadores de conteúdo.

**Saída esperada:** ≥ 20 Session Passes vendidos/mês; conversão desafio→pagamento ≥ 15%.

---

## 6. Fase 3 — Expansão de produto e pricing (2–4 meses) · "aumentar ticket"

### 6.1 Novos endpoints de alto valor
Priorizar os com maior disposição a pagar (compliance/KYC/KYB):
- Simples Nacional, Sanções (CEIS/CNEP), Dias úteis ANBIMA, LEI/GLEIF, OFAC SDN, indicadores macro.
- Alguns já estão no roadmap do dossiê; priorizar por demanda validada em entrevistas com devs/contadores.

### 6.2 Pricing por valor
- Endpoints premium (compliance, sanctions): 0,0020–0,0030 USDC.
- Endpoints básicos (CEP, forex, crypto, dns): 0,0005 USDC.
- Revisar `GRID402_USDC_REQUIRED_RAW` e `PRICE_DISPLAY` por rota em `config.php`.

### 6.3 Planos recorrentes
- Além de créditos avulsos: "Fleet" anual com desconto (~15%).
- Renovação automática opcional para passes de alto volume.

### 6.4 SLA / Enterprise
- Suporte dedicado, rate limits maiores, endpoint privado, fatura em BRL.
- Para agências de automação, fintechs e contabilidades.

### 6.5 White-label / licenciamento
- Vender a infraestrutura x402 + Zero-DB para outros provedores de dados públicos.
- Modelo: setup fee + revenue share.

**Saída esperada:** ARPU ≥ US$ 5/usuário/mês; MRR ≥ US$ 500.

---

## 7. Fase 4 — Grants e parcerias (paralelo) · "capital não-dilutivo"

### 7.1 Base Grants / CDP
- Submeter dossiê pronto (`docs/BASE_GRANTS_AND_CDP_DOSSIER.md`) pedindo US$ 10k–25k para subsidiar 1M queries gratuitas para agentes ElizaOS/AgentKit.
- Isso gera volume, case studies e credibilidade — não é receita direta, mas reduz CAC e valida produto.

### 7.2 Bounties Agentic Commerce
- Participar de hackathons/bounties da Coinbase com o `Grid402ActionProvider`.

### 7.3 Parcerias de distribuição
- Integrar com provedores de MCP populares, marketplaces de agentes, plataformas de automação (n8n, Make, Zapier) como "data provider recomendado".
- Co-marketing com comunidades (n8n Brasil, grupos de automação, fintechs).

---

## 8. Posicionamento e messaging

**Mensagem central:** "Dados públicos brasileiros (CNPJ, CEP, Compliance) para agentes de IA e automações, pagos por chamada em centavos de dólar, sem cadastro nem cartão."

**Públicos prioritários (em ordem):**
1. Devs/agências de automação no Brasil (n8n, Make, WhatsApp, ERPs) — maior propensão a pagar por CNPJ/CEP/Compliance.
2. Agentes autônomos Web3 (ElizaOS, CrewAI, LangChain, AgentKit) — maior aderência ao x402 nativo.
3. Fintechs/contabilidades — compliance e KYC/KYB em escala.

**O que NÃO enfatizar:** crypto/dns/ssl (commodities). Manter no catálogo, mas não liderar comunicação.

---

## 9. Riscos e mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Facilitador CDP indisponível ou caro | Média | Alto | Manter modo `settle` com fallback on-chain + Session Pass; monitorar SLA |
| Baixa adoção de USDC por devs BR | Alta | Alto | Checkout Pix/cartão (Fase 2) |
| Commoditização (crypto/dns grátis) | Alta | Médio | Focar em dados BR exclusivos (compliance) |
| Bugs de telemetria mascarando receita | Média | Médio | Corrigir admin + reconciliar on-chain semanalmente |
| Dependência de fontes públicas (Receita, ViaCEP) | Média | Alto | Cache agressivo, fallbacks, monitoramento de uptime |
| Concorrência de providers x402 | Baixa | Médio | Diferenciação por dados BR + latência <15ms |

---

## 10. Plano de ação — esta semana

1. Ativar `GRID402_FACILITATOR_MODE=settle` com credenciais CDP e fazer 1 pagamento real de teste.
2. Publicar `grid402-mcp@1.8.1` no npm.
3. Submeter ao x402 Bazaar + Base Ecosystem Directory.
4. Publicar case study n8n no LinkedIn/TabNews.
5. Criar conta Stripe/Mercado Pago para checkout de Session Pass em BRL (protótipo).
6. Corrigir `arpu_usdc` no admin.
7. Submeter dossiê Base Grants.

---

## 11. Referências

- `PLANO_TRACAO_CONSULTAS_PAGAS.md` — diagnóstico técnico e checklist de deploy.
- `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` — dossiê de grants.
- `DIVULGACAO.md` — textos de submissão em diretórios.
- `examples/n8n/` — template de workflow n8n (Compliance 360).
- `mcp/` — servidor MCP (npm `grid402-mcp`).
- `integrations/agentkit/` — provedor Coinbase AgentKit (TS + Python).
- `integrations/elizaos/` — plugin ElizaOS.
- `.env.example` — variáveis de ambiente (facilitador, pricing, etc.).
