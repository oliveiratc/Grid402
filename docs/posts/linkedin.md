# LinkedIn — Post 1 (founder voice — lançamento Multi-Chain + Dossiê 360 IA)
> Tom pessoal, primeira pessoa, sem promessa mágica. Comentar o link na 1ª hora depois de postar.

Toda automação de KYB/KYC no Brasil esbarra no mesmo gargalo: CEP, CNPJ, Simples Nacional, sanções CEIS/CNEP, feriados ANBIMA — portais públicos sem API unificada, caros de manter em produção ou presos em contratos anuais de birôs.

Esta semana subimos uma atualização grande no **Grid402** — nossa infraestrutura de 25 APIs de dados públicos e rede para agentes de IA, acessível por um único `curl`, sem cadastro, sem contrato e sem chave de API:

    curl -sL -H "X-Payment: demo" \
      "https://grid402.online/api/v1/dossier?cnpj=00000000000191"

O que entrou no ar:
• **Novo Dossiê 360º KYB + Veredito IA (`/api/v1/dossier`, 0,0500 USDC / 10 créditos):** cruza Receita Federal (CNPJ), Simples/MEI e sanções CGU (CEIS/CNEP) em 1 chamada, devolvendo score 0–100 e veredito pronto para agentes (`APPROVED`, `FLAGGED` ou `REJECTED`), estritamente Zero-PII (LGPD) e com assinatura criptográfica ECDSA (`_oracle_proof`).
• **Multi-Chain em 6 redes + Pix/Cartão:** agora aceita USDC nativo em Base, Arbitrum, Polygon, Optimism, Ethereum e **Solana (SPL USDC)**, além de Pix/Cartão em Reais (Starter: 2 USDC ou R$ 12 = 400 créditos, `1 crédito = 0,0050 USDC`, latência `<15 ms`).
• **27 ferramentas MCP com selo Nota A no mcprush**, verificado também no Glama, x402scan e PayAPI Market — pronto para Claude Desktop, Cursor, n8n, LangChain, CrewAI e AgentKit.

Se você já perdeu tempo colando CNPJ em portal para enriquecer lead ou auditar fornecedor: me conta nos comentários qual consulta ainda trava sua operação.

Teste grátis no sandbox (3 req/day, sem cartão): links no primeiro comentário.
#IA #AgentesDeIA #Fintech #KYB #Compliance #MCP #Automação #Brasil

---

# LinkedIn — Post 2 (lista de utilidades)
> Formato "6 dados": alto alcance, leads qualificados.

6 consultas públicas que seu agente de IA já pode executar sozinho (sem API key e sem mensalidade fixa):

1. **Dossiê 360º KYB + Veredito IA (`/api/v1/dossier`)** → RFB + Simples/MEI + CGU CEIS/CNEP + Veredito `APPROVED/FLAGGED/REJECTED` — US$ 0,0500 (10 créditos)
2. **Auditoria KYB Global (`/api/v1/kyb-audit`)** → CNPJ + Sanções OFAC/ONU + GLEIF LEI — US$ 0,0250 (5 créditos)
3. **Compliance Score 360 (`/api/v1/compliance`)** → score de integridade 0–100 (RF + CGU) — US$ 0,0300 (6 créditos)
4. **Sanções CEIS/CNEP (`/api/v1/sanctions`)** → inidoneidade e impedimentos na CGU — US$ 0,0100 (2 créditos)
5. **CEP + IBGE (`/api/v1/cep`)** → logradouro, bairro, município e código IBGE — US$ 0,0050 (1 crédito)
6. **Dias úteis ANBIMA (`/api/v1/workdays`)** → contagem e projeção de vencimentos D+N — US$ 0,0050 (1 crédito)

Tudo via API pública, um `curl`, sem cadastro nem chave:

    curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"

Pagamento por chamada via x402 em USDC (6 redes: Base, Arbitrum, Polygon, Optimism, Ethereum e Solana) ou Session Pass via Pix (a partir de R$ 12 = 400 créditos) · prova criptográfica ECDSA do oráculo · zero PII (LGPD).

Pra quem constrói agentes que "agem" de verdade no Brasil: links no 1º comentário.

#IA #AgenticAI #Fintech #KYB #Brasil
