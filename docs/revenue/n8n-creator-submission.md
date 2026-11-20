# Pacote de Submissão — n8n Creator Hub Templates (Grid402)

> **Objetivo:** Publicar 3 templates oficiais no diretório `https://n8n.io/workflows/` (que traz tráfego orgânico diário de gestores de automação, agências B2B e devs no Brasil e no mundo), apontando 100% para o checkout Pix (`R$ 12 = 400 créditos` em `https://grid402.online/pass?tier=starter&method=pix`) e para o gerador `/dossie-kyb`.

## Pré-requisito de Conta Creator
1. Acesse `https://n8n.io/creators/` e vincule sua conta (perfil já criado na comunidade n8n: `oliveiratc`).
2. Cada template abaixo usa **exclusivamente nós nativos do n8n** (`Webhook`, `HTTP Request`, `IF`, `Switch`, `Google Sheets` / `Slack` / `Telegram`) — **zero dependência de community node externo**, o que garante aprovação mais rápida e funcionamento em n8n Cloud e self-hosted!

---

## Template 1: Triagem Automática de Fornecedor e Cliente B2B (Dossiê 360º KYB + Veredito IA)

- **Título sugerido:** `Automated Brazilian KYB & Supplier Due Diligence (CNPJ + CGU Sanctions + AI Verdict)`
- **Categorias:** `Finance & Accounting`, `SecOps`, `Sales`
- **Resumo (How it works):**
  1. Recebe um CNPJ via formulário, Webhook ou linha nova no Google Sheets.
  2. Chama `GET https://grid402.online/api/v1/dossier?cnpj={{ $json.cnpj }}` com cabeçalho `X-Payment: pass_g402_SEU_TOKEN` (10 créditos = R$ 0,30 no Starter Pass R$ 12).
  3. Lê `verdict.decision` (`APPROVED`, `FLAGGED` ou `REJECTED`) e o `score` (0–100).
  4. Encaminha via nó `Switch`:
     - `APPROVED` → aprova no CRM / Slack verde.
     - `FLAGGED` → abre revisão manual para o time de Compliance.
     - `REJECTED` → bloqueia cadastro e anexa prova criptográfica `_oracle_proof`.
- **Setup instructions para o usuário:**
  - Teste grátis com `X-Payment: demo` (3 req/dia por IP).
  - Em produção, compre 400 créditos (40 dossiês) via Pix em **R$ 12,00** (ou 2 USDC) em `https://grid402.online/pass?tier=starter&method=pix` e cole `pass_g402_...` no header `X-Payment`.

---

## Template 2: Enriquecimento e Limpeza de Leads B2B (Email Verify Zero-PII + URL to Markdown para IA)

- **Título sugerido:** `AI Lead Qualification: Zero-PII Email Deliverability Audit + Website Markdown Extractor`
- **Categorias:** `AI`, `Marketing`, `Sales`
- **Resumo (How it works):**
  1. Recebe novo lead (`email` e `website_url`) via Webhook ou HubSpot/Pipedrive.
  2. Nó 1 — `GET https://grid402.online/api/v1/email-verify?email={{ $json.email }}` (`0.0050 USDC` / 1 crédito): bloqueia domínios descartáveis (`disposable: true`) ou sem MX (`verdict: INVALID`) antes de gastar tokens de LLM.
  3. Nó 2 (`IF verdict === "VALID"`): chama `GET https://grid402.online/api/v1/extract?url={{ $json.website_url }}&max_chars=8000` (`0.0050 USDC` / 1 crédito) para obter Markdown limpo do site do lead sem precisar de Playwright/Firecrawl.
  4. Nó 3 — Envia o Markdown para Gemini/OpenAI gerar abordagem comercial personalizada e salva no CRM.
- **Custo total por lead qualificado:** apenas **2 créditos** (`0.0100 USDC` ou **R$ 0,06** no Pix R$ 12 = 200 leads completos).

---

## Template 3: Cálculo Automático de Vencimentos Bancários ANBIMA (D+N) e Cotações PTAX para Financeiro

- **Título sugerido:** `Brazilian Banking Workdays (ANBIMA D+N) & Official BCB PTAX / Selic Financial Sync`
- **Categorias:** `Finance & Accounting`, `IT Ops`
- **Resumo (How it works):**
  1. Dispara diariamente às 08h30 (`Schedule Trigger`) ou a cada nova cobrança gerada no ERP.
  2. Chama `GET https://grid402.online/api/v1/workdays?start_date={{ $now.toFormat('yyyy-MM-dd') }}&days=5` (1 crédito) para projetar vencimento real ignorando feriados bancários ANBIMA/Febraban.
  3. Chama `GET https://grid402.online/api/v1/market-snapshot` (3 créditos) para capturar USD/EUR/GBP PTAX, Selic, CDI e IPCA em 1 única chamada.
  4. Atualiza planilha financeira / envia resumo diário no Slack ou Telegram.

---

## Checklist de Submissão no n8n Creators
- [ ] Exportar JSON de cada um dos 3 workflows sem nenhum token real (usar placeholder `pass_g402_SEU_TOKEN_AQUI`).
- [ ] Adicionar Sticky Notes coloridas dentro do canvas explicando: (1) onde pegar o token Pix R$ 12 (`/pass?tier=starter&method=pix`), (2) como testar com `demo` e (3) link para o gerador visual `/dossie-kyb`.
- [ ] Submeter via `https://n8n.io/creators/` e acompanhar aprovação por e-mail.
