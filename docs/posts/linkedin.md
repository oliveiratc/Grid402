# LinkedIn — Post 1 (founder voice — Fase 1: Liquidez Global, Sanções 4 Blocos e Extract Transiente)
> Tom pessoal, primeira pessoa, sem promessa mágica. Comentar os links na 1ª hora depois de postar.

Quem coloca agentes de IA em produção esbarra sempre nos mesmos três muros: **liquidez de pagamento**, **confiabilidade dos dados de compliance** e **risco jurídico ao extrair conteúdo da web**.

Acabamos de subir uma atualização importante no **Grid402** atacando os três frentes sem cadastro, sem API key e sem mensalidade fixa — tudo em um único `curl`:

    curl -sL -H "X-Payment: demo" \
      "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"

O que entrou no ar:
• **Liquidez Global x402 v2 (Dual-Facilitator + Solana):** roteamento **Coinbase CDP** como primário na rede Base + fallback automático pré-liquidação para **PayAI** (com proteção estrita contra dupla cobrança no `/settle`) e liquidação gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)**, além de Pix/Cartão e RapidAPI.
• **Sanções Oficiais em 4 Blocos + AML Web3 Multi-Ativo (`/api/v1/ofac` e `/kyb-audit`):** base sincronizada diariamente com **OFAC SDN (EUA), ONU, União Europeia (FSFi) e Reino Unido (UK OFSI)** para carteiras **EVM, Solana, Bitcoin, Tron** e empresas — em modo *fail-closed* (HTTP 503 sem cobrar se o índice estiver fora) e prova criptográfica ECDSA (`_oracle_proof` + `dataset_sha256`).
• **Extrator Web-to-Markdown 100% Transiente (`/api/v1/extract`):** **0 bytes** de conteúdo de terceiros gravados em disco (`transient_only: true`), respeito nativo a `robots.txt`, `X-Robots-Tag: noai`, `Content-Signal` e bloqueio de paywalls pré-cobrança.
• **Preços reduzidos e Pro Pass turbinado:** `/cnpj` e `/lei` caíram 50% para **0,0100 USDC** (2 créditos); `/kyb-audit` foi para **0,0250 USDC** (5 créditos); **Pro Pass** subiu para **1.200 créditos** (R$ 29 / 5 USDC · 180 dias) e **Fleet** para **6.000 créditos** (R$ 115 / 20 USDC · 365 dias), com estorno automático em qualquer erro e retentativa grátis por 300s (`Idempotency-Key`).

Hoje são **25 micro-serviços em produção** (incluindo o **Dossiê 360º KYB + Veredito IA**) e **27 ferramentas MCP (`v2.0.0`, Nota A no mcprush)** prontas para Claude Desktop, Cursor, n8n, LangChain e CrewAI.

Na sua operação hoje, qual desses três gargalos mais segura seus agentes antes de ir para produção?

Teste grátis no sandbox (3 req/dia ou cupom `MCPTRY50` com 50 créditos): links no primeiro comentário.
#IA #AgentesDeIA #Fintech #KYB #Compliance #MCP #Automação #Brasil

---

# LinkedIn — Post 2 (lista de utilidades — preços e escopo atualizados)
> Formato "6 dados": alto alcance, leads qualificados.

6 consultas públicas e de compliance que seu agente de IA já executa sozinho (sem API key, sem mensalidade e com estorno automático em caso de erro):

1. **Dossiê 360º KYB + Veredito IA (`/api/v1/dossier`)** → RFB + Simples/MEI + CGU CEIS/CNEP + Veredito `APPROVED/FLAGGED/REJECTED` — US$ 0,0500 (10 créditos)
2. **Sanções Globais 4 Blocos + AML Web3 (`/api/v1/ofac`)** → OFAC SDN (EUA) + ONU + UE FSFi + UK OFSI para EVM, Solana, BTC, Tron e empresas — US$ 0,0200 (4 créditos)
3. **Auditoria KYB Global (`/api/v1/kyb-audit`)** → CNPJ + Sanções 4 Blocos + GLEIF LEI — US$ 0,0250 (5 créditos)
4. **CNPJ ou GLEIF LEI (`/api/v1/cnpj` · `/api/v1/lei`)** → dados cadastrais RFB ou identidade global ISO 17442 — US$ 0,0100 (2 créditos, 50% menor)
5. **Web-to-Markdown 100% Transiente (`/api/v1/extract`)** → HTML para Markdown limpo com `0 bytes` em disco, respeitando `robots.txt`, `noai` e paywalls — US$ 0,0050 (1 crédito)
6. **CEP/IBGE e Dias Úteis ANBIMA (`/api/v1/cep` · `/api/v1/workdays`)** → endereço + código IBGE e projeção D+N — US$ 0,0050 (1 crédito)

Tudo via API pública, um `curl`, sem cadastro nem chave:

    curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"

Liquidação gasless via Coinbase CDP / PayAI x402 (Base, Polygon, Arbitrum e Solana) ou Session Pass via Pix (Starter R$ 12 = 400 cr / 180d · Pro R$ 29 = 1.200 cr / 180d) · prova criptográfica ECDSA do oráculo · Zero-PII (LGPD).

Links do catálogo e playground no 1º comentário.

#IA #AgenticAI #Fintech #KYB #Compliance #Brasil

---

# LinkedIn — Post 3 (engenharia e confiabilidade em produção)
> Foco em arquitetura: fail-closed, anti-dupla-cobrança e Zero-DB.

Três decisões de engenharia que tomamos recentemente no **Grid402** para tirar agentes de IA do protótipo e colocar em produção com segurança:

1. **Compliance *fail-closed* antes da cobrança:** se a base sincronizada dos 4 blocos oficiais de sanções (OFAC SDN, ONU, UE FSFi e UK OFSI) não estiver íntegra, a API retorna `HTTP 503` **antes** de debitar o Session Pass ou liquidar o x402. Nunca mascaramos indisponibilidade com um falso `CLEAR`.
2. **Dual-Facilitator sem dupla cobrança:** na Base usamos **Coinbase CDP** como facilitador primário com fallback automático para **PayAI** apenas na fase pré-liquidação (`/verify`). Se houver timeout ou erro ambíguo durante o `/settle`, o nonce permanece travado em `pending` e nunca é reenviado ao segundo facilitador.
3. **Extração RAG 100% transiente:** o `/api/v1/extract` não grava **nenhum byte** de conteúdo de terceiros em disco, respeita `robots.txt`, `X-Robots-Tag: noai`, `Content-Signal` e bloqueia paywalls antes de cobrar.

Infraestrutura M2M de verdade precisa proteger o integrador tanto no código quanto no risco jurídico.

Documentação técnica e Trust Center nos comentários.

#EngenhariaDeSoftware #AgentesDeIA #x402 #Compliance #MCP #Arquitetura
