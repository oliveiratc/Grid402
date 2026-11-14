# LinkedIn — Post 1 (founder voice)
> Tom pessoal, primeira pessoa, sem promessa mágica. Comentar o link na 1ª hora depois de postar.

Toda automação de KYB/KYC no Brasil esbarra no mesmo gargalo: CEP, CNPJ, sanções CEIS/CNEP, feriados ANBIMA — portais públicos sem API, dados grátis para burocratizar e caros de integrar em sistema de verdade.

Então construí o Grid402 (https://grid402.online) — um hub de dados públicos brasileiros para agentes de IA, acessível por um único `curl`, sem cadastro, sem contrato e sem chave de API:

    curl -H "X-Payment: demo" \
      "https://grid402.online/api/v1/compliance?cnpj=00000000000191"

Ele devolve situação cadastral, Simples, CNAE, endereço IBGE e sanções CEIS/CNEP — assinado criptograficamente (ECDSA), para o agente provar a origem dos dados. Custa US$ 0,0050–0,0300 por chamada na Base (ou um Session Pass: 2 USDC = 40.000 créditos = 400 chamadas padrão).

Se você já perdeu tempo colando CNPJ em portal para enriquecer lead: me conta — é exatamente a dor que o projeto ataca.

Teste grátis (sandbox, sem cartão): link no comentário.
#IA #Fintech #KYC #Automação #Brasil

---

# LinkedIn — Post 2 (lista de utilidades)
> Formato "5 dados": alto alcance, leads qualificados.

5 dados públicos brasileiros que seu agente de IA deveria consultar sozinho (em vez de preencher planilha à mão):

1. CNPJ → situação, CNAE, Simples Nacional (Receita Federal) — US$ 0,0200
2. CEIS/CNEP → sanções e inidoneidade (CGU) — US$ 0,0100
3. Compliance 360 → score de risco (RF + CGU) — US$ 0,0300
4. CEP → logradouro, bairro, IBGE — US$ 0,0050
5. Dias úteis ANBIMA → contagem e projeção de prazos — US$ 0,0050

Tudo via API pública, um `curl`, sem cadastro nem chave:

    curl -H "X-Payment: demo" "https://grid402.online/api/v1/compliance?cnpj=00000000000191"

Custo por chamada em USDC na Base · prova criptográfica do oráculo · zero PII (LGPD).

Pra quem constrói agentes que "agem" de verdade no Brasil: aqui está uma infraestrutura de dados pública, barata e imediata.

#IA #AgenticAI #Fintech #KYB #Brasil
