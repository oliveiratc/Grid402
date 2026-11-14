# Template n8n: KYB completo do Brasil (CNPJ + CEIS/CNEP + CEP) em 30 segundos, sem API key

Acabei de publicar um workflow de **enriquecimento B2B** pronto para importar no n8n: um agente/lead entra, o workflow consulta dados públicos brasileiros oficiais e devolve um **dossiê de risco** — sem nenhuma chave de API tradicional, sem contrato, sem cadastro.

## O que ele faz

1. Configura a consulta (CNPJ + CEP);
2. Chama o Grid402: `/api/v1/compliance` (score de risco, Receita Federal + CGU + societário) e `/api/v1/cep` (endereço + IBGE);
3. Consolida tudo num dossiê B2B.

## Testar em 30 segundos

1. Baixe o template: `examples/n8n/grid402_cnpj_compliance_workflow.json` no repo https://github.com/oliveiratc/Grid402
2. No n8n: **Menu (…) → Import from File**
3. Clique em **Test workflow** — vem com `X-Payment: demo` (amostra grátis + desafio 402)

## Para produção (com limite real)

Troque o `demo` por um **Session Pass** (2 USDC = 40.000 créditos = 400 chamadas padrão; <15 ms por chamada).

## Casos de uso prontos

- **Qualificar leads B2B no WhatsApp/Typebot/CRM:** CNPJ entra, dossiê sai — situação cadastral, CNAE, Simples, sanções, endereço.
- **KYC/KYB antifraude:** validar fornecedor contra CEIS/CNEP **antes** de aprovar cadastro.
- **Logística e checkout:** resolver logradouro/bairro/IBGE de qualquer CEP brasileiro.

Tudo verificado criptograficamente (prova do oráculo ECDSA no payload, `_oracle_proof`) e em conformidade com a LGPD (dados públicos, zero PII).

**Repo:** https://github.com/oliveiratc/Grid402 · **Site:** https://grid402.online · **MCP:** `npx grid402-mcp`

Feedback bem-vindo — especialmente se você usar n8n com clientes B2B no Brasil e tiver dor com KYB manual.
