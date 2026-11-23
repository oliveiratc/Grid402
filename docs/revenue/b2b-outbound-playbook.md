# Playbook Comercial de Outbound B2B — Primeiros Clientes Pagantes (Grid402)

> **Objetivo:** Fechar os primeiros **10 a 30 compradores pagantes reais** via Pix (`R$ 12 / R$ 29 / R$ 115`) ou recorrência em 14 dias, mirando empresas e profissionais que **já pagam caro** (R$ 300 a R$ 1.500/mês) ou perdem tempo checando CNPJ, Simples Nacional e Sanções CGU manualmente.

## 1. Perfis de Cliente Ideal (ICP) com Dor Imediata

| Segmento ICP | Dor Principal | Oferta Grid402 que Fecha na Hora | Ticket Esperado |
|---|---|---|---|
| **1. Escritórios de Contabilidade e BPO Financeiro** | Consultam CNPJ, Simples/MEI e regularidade de fornecedores manualmente todos os dias | Gerador PDF `/dossie-kyb` por **R$ 12 no Pix (40 dossiês = R$ 0,30 cada)** | R$ 12 → R$ 29/mês |
| **2. Agências de Automação (n8n / Make / IA)** | Precisam enriquecer CNPJ, validar e-mails B2B e extrair sites para RAG sem mensalidade cara | Session Pass **Pro R$ 29 (1.200 cr · 180d)** ou **Fleet R$ 115 (6.000 cr · 365d)** | R$ 29 → R$ 115/mês |
| **3. Factorings, FIDCs, Correspondentes e Deptos. de Compras** | Homologação de sacados/fornecedores com evidência auditável (CGU CEIS/CNEP + Receita) | Dossiê 360º KYB PDF com assinatura criptográfica ECDSA (`_oracle_proof`) | R$ 29 → R$ 115/mês |
| **4. Desenvolvedores de Agentes IA (Web3 / MCP / Base / Solana)** | Precisam de ferramentas baratas (`0.0050 USDC`) para extrair Markdown (`/extract`), validar e-mail (`/email-verify`) e KYB | Gasless x402 via PayAI (Base, Polygon, Arbitrum, Solana) ou `npx -y grid402-mcp` | 2 a 20 USDC/lote |

---

## 2. Cadência Diária Enxuta (45 min/dia — Meta: 15 abordagens qualificadas/dia)

1. **Lista diária (10 min):** Buscar no LinkedIn / Google Maps / grupos de n8n Brasil e contabilidade 15 decisores reais (Sócio-Contador, Head de Automação, Analista de Compras/Compliance, Founder de SaaS B2B).
2. **Dossiê Isca Personalizado (10 min):** Acessar `https://grid402.online/dossie-kyb`, consultar o CNPJ de uma empresa pública conhecida (ou da própria empresa prospectada) e gerar 1 link / print limpo do relatório PDF.
3. **Envio direto (25 min):** Enviar 1 mensagem curta, sem spam, sem link genérico frio — direto ao ponto com prova visual + link direto para testar grátis a prévia em `/dossie-kyb`.

---

## 3. Scripts Prontos (WhatsApp, LinkedIn DM e Cold E-mail)

### Script A — LinkedIn DM / WhatsApp para Escritórios de Contabilidade e BPO Financeiro
> Olá [Nome], tudo bem? Vi que você lidera a operação na [Empresa].
>
> Criei uma ferramenta 100% online sem cadastro e sem mensalidade que gera um **Dossiê 360º de CNPJ em PDF** em 2 segundos (juntando Receita Federal + Simples Nacional/MEI + Sanções CGU CEIS/CNEP + Veredito de Risco).
>
> Em vez de pagar birô caro ou consultar 3 sites separados, dá para ver a prévia grátis e gerar **40 dossiês completos por R$ 12 no Pix** (R$ 0,30 cada):
> https://grid402.online/dossie-kyb
>
> Se quiser testar com um CNPJ seu agora, me avisa o que achou!

### Script B — LinkedIn DM / Comunidades para Agências de Automação (n8n / Make / IA)
> Fala [Nome]! Vi seu trabalho com automações B2B e n8n.
>
> Lançamos no Grid402 **25 APIs prontas para n8n e Agentes IA** sem mensalidade e sem chave burocrática — incluindo:
> - `/api/v1/dossier` (CNPJ + Simples + Sanções CGU + Veredito IA em 1 GET)
> - `/api/v1/extract` (URL para Markdown limpo para LLM/RAG)
> - `/api/v1/email-verify` (MX/SPF/DMARC + bloqueio de descartáveis Zero-PII)
> - `/api/v1/workdays` (dias úteis ANBIMA D+N)
>
> Com **R$ 12 no Pix** você pega 400 créditos na hora (`pass_g402_...`) e já pluga num nó HTTP Request do n8n:
> https://grid402.online/pass?tier=starter&method=pix
>
> Se quiser, te mando o JSON pronto do fluxo de qualificação de CNPJ/Leads no n8n!

### Script C — Cold E-mail Curto para Compras / Compliance / Factoring
- **Assunto:** Dossiê CNPJ + Sanções CGU em PDF por R$ 0,30 (sem mensalidade)
- **Corpo:**
  > Olá [Nome],
  >
  > Vocês hoje checam situação cadastral, Simples Nacional e sanções federais da CGU (CEIS/CNEP) na homologação de novos fornecedores/clientes?
  >
  > No **Grid402** consolidamos essas 3 fontes oficiais em um **Dossiê 360º KYB** com veredito determinístico (`APPROVED` / `FLAGGED` / `REJECTED`), exportação em PDF e assinatura criptográfica ECDSA para trilha de auditoria — estritamente Zero-PII (LGPD Art. 1º).
  >
  > - Sem contrato e sem mensalidade
  > - **Starter Pass:** R$ 12 no Pix = 400 créditos (= **40 dossiês completos**, validade 180 dias)
  > - **Pro Pass:** R$ 29 no Pix = 1.200 créditos (= **120 dossiês**, +20% bônus, validade 180 dias)
  > - **Fleet Pass:** R$ 115 no Pix = 6.000 créditos (= **600 dossiês**, +50% bônus, validade 365 dias)
  >
  > Teste uma prévia gratuita agora (sem cadastro): https://grid402.online/dossie-kyb
  >
  > Abraço,
  > Thiago — Grid402 (https://grid402.online)

### Script D — Comunidades Web3 / AI Agents (Discord Base, PayAI, Solana, MCP)
> We just shipped **Grid402 v3.4 / MCP v2.0.0** — 25 verifiable public-data & agent utility endpoints with **gasless x402 v2 settlement via PayAI across Base, Polygon, Arbitrum, and Solana**:
> - `GET /api/v1/extract?url=...` — SSRF-hardened Web-to-Markdown for RAG (`0.0050 USDC`)
> - `GET /api/v1/email-verify?email=...` — Zero-PII RFC/MX/SPF/DMARC/disposable audit (`0.0050 USDC`)
> - `GET /api/v1/dossier?cnpj=...` — Brazilian KYB 360 AI Dossier (`0.0500 USDC`)
> - 27 MCP tools (`npx -y grid402-mcp`) + ECDSA secp256k1 `_oracle_proof` on every response.
> Try free (3 req/day): `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/extract?url=https://example.com"`

---

## 4. Acompanhamento de Conversão (Métrica Diária)
Acompanhe diariamente via painel `/admin` ou `/collector.php?action=metrics&token=...`:
- **Cliques / visitas em `/dossie-kyb` e `/pass`**
- **Pedidos Pix gerados vs. Pix aprovados** (taxa de conversão alvo: > 25%)
- **Liquidações x402 via PayAI por rede** (`Base`, `Polygon`, `Arbitrum`, `Solana`)
- **Follow-up D+2 e D+5** apenas para quem abriu/respondeu pedindo exemplo técnico.
