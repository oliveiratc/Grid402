# Grid402 + n8n // Automação de Enriquecimento B2B (CNPJ, Compliance e CEP)

Template pronto para importar no **n8n** e consultar dados oficiais brasileiros (Receita Federal CNPJ, Simples Nacional, Sanções CEIS/CNEP e CEP/IBGE) sem cadastro de API Key tradicional.

## Como importar e testar em 30 segundos

1. No n8n, crie um novo workflow e clique em **Menu (`...`) > Import from File**.
2. Selecione o arquivo `grid402_cnpj_compliance_workflow.json`.
3. Clique em **Test workflow**:
   - Por padrão, o nó `Configurar Consulta` envia `X-Payment: demo` (modo Sandbox gratuito, até 3 chamadas/dia por IP).
4. Para rodar em **produção** (sem limite de sandbox, latência `<15ms` e suporte a `Idempotency-Key`):
   - Gere um **Session Pass** em `https://grid402.online/pass` via **Pix** (Starter: R$ 12 por 400 créditos válidos por 180 dias; Pro: R$ 29 por 1.200 créditos; Fleet: R$ 115 por 6.000 créditos por 365 dias) ou **USDC** (a partir de 2 USDC).
   - Substitua `demo` no campo `x_payment` pelo seu token `pass_g402_...`.

## Casos de uso prontos para adaptar
- **Qualificação de leads B2B no WhatsApp / Typebot / CRM:** receba o CNPJ no Webhook e devolva situação cadastral, CNAE, Simples Nacional e score de compliance automaticamente.
- **Antifraude e KYC/KYB:** valide se o fornecedor ou cliente possui sanções ativas no CEIS/CNEP antes de aprovar cadastro (ou troque a URL para `/api/v1/dossier?cnpj=...` para receber o Dossiê 360° KYB com veredito determinístico `APPROVED`, `FLAGGED` ou `REJECTED`).
- **Logística e Checkout:** resolva logradouro, bairro, cidade, UF e código IBGE via `/api/v1/cep`.
