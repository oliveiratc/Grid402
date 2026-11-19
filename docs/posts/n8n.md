# Template n8n: Dossiê 360º KYB do Brasil (CNPJ + Simples/MEI + CEIS/CNEP + Veredito IA + CEP) em 30 segundos, sem API key

Publicamos um workflow de **enriquecimento e auditoria B2B** pronto para importar no n8n: um agente/lead entra, o workflow consulta dados públicos brasileiros oficiais e devolve um **dossiê de risco com veredito pronto para automação** — sem nenhuma chave de API tradicional, sem contrato e sem cadastro.

## O que ele faz

1. Configura a consulta (CNPJ + CEP);
2. Chama o Grid402:
   - **`/api/v1/dossier` (`0,0500 USDC` / 10 créditos):** Dossiê 360º KYB unificado (Receita Federal + Simples/MEI + Sanções CGU CEIS/CNEP + Score 0–100 + **Veredito IA `APPROVED` / `FLAGGED` / `REJECTED`**); ou `/api/v1/compliance` (`0,0300 USDC` / 6 créditos);
   - **`/api/v1/cep` (`0,0050 USDC` / 1 crédito):** endereço completo + código IBGE;
3. Consolida tudo num dossiê B2B pronto para aprovar, sinalizar ou bloquear o onboarding automaticamente.

## Testar em 30 segundos

1. Baixe o template: `examples/n8n_cnpj_compliance_pix.json` (ou `examples/n8n/grid402_cnpj_compliance_workflow.json`) no repo https://github.com/oliveiratc/Grid402
2. No n8n: **Menu (…) → Import from File**
3. Clique em **Test workflow** — vem com `X-Payment: demo` (Sandbox gratuito, **5 chamadas/hora por IP**)

## Para produção (via Pix/Cartão ou Multi-Chain USDC)

Troque `'demo'` por um **Session Pass** emitido em https://grid402.online/pass (aceita **Pix/Cartão** ou **USDC em 6 redes**: Base, Arbitrum, Polygon, Optimism, Ethereum e Solana SPL):
- **Starter:** R$ 12 / 2 USDC = **400 créditos** (30 dias)
- **Pro:** R$ 29 / 5 USDC = **1.000 créditos** (30 dias)
- **Fleet:** R$ 115 / 20 USDC = **6.000 créditos** (`4.000 base + 2.000 bônus`, 60 dias)
- Latência `<15 ms` por chamada, com Spend Controls (`daily_credit_limit`, `allowed_endpoints`) e `Idempotency-Key`.

## Casos de uso prontos

- **Qualificar leads B2B no WhatsApp/Typebot/CRM:** CNPJ entra, dossiê + veredito `APPROVED`/`FLAGGED`/`REJECTED` sai na hora.
- **KYC/KYB antifraude:** validar fornecedor contra Receita Federal + Simples/MEI + CEIS/CNEP **antes** de aprovar cadastro.
- **Logística e checkout:** resolver logradouro/bairro/IBGE de qualquer CEP brasileiro.

Tudo verificado criptograficamente (prova do oráculo ECDSA no payload, `_oracle_proof`) e em conformidade com a LGPD (dados públicos de PJ, zero PII).

**Repo:** https://github.com/oliveiratc/Grid402 · **Site:** https://grid402.online · **MCP (25 tools, Nota A):** `npx -y grid402-mcp`

Feedback bem-vindo — especialmente se você usar n8n com clientes B2B no Brasil e tiver dor com KYB manual.
