# Grid402 + n8n // Automação de Enriquecimento B2B (CNPJ, Compliance e CEP)

Template pronto para importar no **n8n** e consultar dados oficiais brasileiros (Receita Federal CNPJ, Simples Nacional, Sanções CEIS/CNEP e CEP/IBGE) sem cadastro de API Key tradicional.

## Como importar e testar em 30 segundos

1. No n8n, crie um novo workflow e clique em **Menu (`...`) > Import from File**.
2. Selecione o arquivo `grid402_cnpj_compliance_workflow.json`.
3. Clique em **Test workflow**:
   - Por padrão, o nó `Configurar Consulta` envia `X-Payment: demo` (modo Sandbox gratuito, até 30 chamadas/hora por IP).
4. Para rodar em **produção** (sem limite de sandbox, latência `<15ms`):
   - Gere um Session Pass em `https://grid402.online/#topup` (400 consultas padrao por 2 USDC).
   - Substitua `demo` no campo `x_payment` pelo seu token `pass_g402_...`.

## Casos de uso prontos para adaptar
- **Qualificação de leads B2B no WhatsApp / Typebot / CRM:** receba o CNPJ no Webhook e devolva situação cadastral, CNAE, Simples Nacional e score de compliance automaticamente.
- **Antifraude e KYC/KYB:** valide se o fornecedor ou cliente possui sanções ativas no CEIS/CNEP antes de aprovar cadastro.
- **Logística e Checkout:** resolva logradouro, bairro, cidade, UF e código IBGE via `/api/v1/cep`.
