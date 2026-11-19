# PRD: Dossiê 360º de Conformidade Cadastral para Agentes Autônomos (KYB 360)

**Status:** Em revisão  
**Data:** 2026-11-18  
**Autor:** thiag + Antigravity  

---

## 1. Contexto e problema
Agentes autônomos de IA (operando em LangChain, CrewAI, ElizaOS e Coinbase AgentKit) e sistemas M2M corporativos precisam tomar decisões rápidas de negócio (ex: contratação de fornecedores, liberação de crédito, onboarding de parceiros e verificação de contrapartes).

Hoje, para avaliar uma empresa brasileira no Grid402, o agente precisa executar de 3 a 5 chamadas HTTP separadas (`/cnpj`, `/simples`, `/sanctions`, `/compliance`), gerando latência cumulativa, custo de orquestração e necessidade de o próprio modelo LLM interpretar e cruzar os dados brutos. Além disso, as chamadas individuais têm ticket baixo (\$0.005 a \$0.02), limitando o faturamento por cliente.

O **Dossiê 360º (`/api/v1/dossier`)** resolve esse problema ao entregar uma consolidação completa em chamada atômica única, já acompanhada de um **Veredito Sintetizado para IA (`APPROVED` / `FLAGGED` / `REJECTED`)**, elevando o ticket médio para **0.0500 USDC** (ou 10 créditos do Session Pass / R$ 0,50 no Pix).

---

## 2. Objetivos
- Disponibilizar o endpoint canônico `GET /api/v1/dossier?cnpj=...` com resposta consolidada sub-200ms em cache.
- Multiplicar o ticket médio de faturamento de dados cadastrais de 0.0200 USDC para **0.0500 USDC** (10 créditos de Session Pass / R$ 0,50 no Pix).
- Entregar um veredito estruturado de risco com score 0-100, status categórico (`APPROVED`, `FLAGGED`, `REJECTED`), alertas críticos e resumo executivo em português.
- Publicar a ferramenta oficial correspondente `grid402_get_dossier_360` no Servidor MCP (`grid402-mcp`), disponibilizando-a para Claude Desktop, Cursor, Glama e LangChain.
- Manter 100% de conformidade com LGPD/GDPR (Zero PII, sem CPFs ou quadro de sócios QSA) e assinatura criptográfica ECDSA secp256k1 (`_oracle_proof`).

---

## 3. Não objetivos (fora do escopo)
- Não incluir dados pessoais sensíveis (PII) de pessoas físicas (CPFs, patrimônio pessoal ou score de crédito pessoal).
- Não realizar consulta a birôs de crédito privados fechados (ex: Serasa Experian / Boa Vista) que exijam credenciais de terceiros ou termos de sigilo bancário.
- Não incluir triagem de certidões estaduais ou municipais fragmentadas (foco em bases federais oficiais: RFB, Simples Nacional, CGU CEIS e CGU CNEP).

---

## 4. Público-alvo e personas
1. **Agentes de Suprimentos e Compras (Procurement Agents):** Bots que recebem propostas de fornecedores e precisam aprovar ou barrar o cadastro de CNPJ em tempo real antes da emissão de ordens de serviço.
2. **Robôs de Onboarding Financeiro e B2B:** Gateways de pagamento, plataformas SaaS e dApps Web3 verificando a legitimidade cadastral de clientes PJ antes de liberar limites.
3. **Desenvolvedores Claude / Cursor / Glama:** Usuários que solicitam análises empresariais no chat e recebem um dossiê pronto e confiável com prova de integridade.

---

## 5. Histórias de usuário
- **HU01:** Como agente autônomo de compras, quero enviar um CNPJ em uma chamada única para saber imediatamente se a empresa está ativa, sem sanções e apta para contratação.
- **HU02:** Como desenvolvedor de IA, quero receber um campo estruturado `verdict` com status (`APPROVED`, `FLAGGED`, `REJECTED`) para que minha lógica de controle condicional não precise analisar textos longos com regex.
- **HU03:** Como operador do Grid402, quero monetizar consultas corporativas a 0.0500 USDC (10 créditos), aumentando o faturamento médio por sessão.
- **HU04:** Como usuário do Claude Desktop via MCP, quero usar a ferramenta `grid402_get_dossier_360` e receber o dossiê formatado no chat com 1 clique.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Disponibilizar a rota `GET /api/v1/dossier?cnpj={cnpj}` aceitando CNPJ com ou sem máscara. | **Must** |
| **RF02** | Exigir micropagamento HTTP x402 de 0.0500 USDC na Base Mainnet (ou 10 créditos via `X-Session-Pass`, ou autorização `demo` em sandbox). | **Must** |
| **RF03** | Consolidar na resposta: dados cadastrais RFB (razão social, CNAE, situação, capital), enquadramento Simples/MEI e sanções CGU (CEIS e CNEP). | **Must** |
| **RF04** | Calcular e retornar o bloco `verdict` com `status` (`APPROVED`, `FLAGGED`, `REJECTED`), `score` (0 a 100), `alerts` (lista de restrições) e `summary` executivo. | **Must** |
| **RF05** | Retornar `REJECTED` se a empresa estiver INAPTA/BAIXADA na RFB ou constar com sanção ativa no CEIS/CNEP da CGU. | **Must** |
| **RF06** | Retornar `FLAGGED` se a empresa estiver ATIVA, mas possuir CNAE de alto risco, capital social desproporcional ou ausência de enquadramento tributário regular. | **Must** |
| **RF07** | Retornar `APPROVED` se a empresa estiver ATIVA na RFB, sem registros punitivos no CEIS/CNEP e score $\ge 70$. | **Must** |
| **RF08** | Assinar o resultado final com a chave privada do oráculo gerando o bloco `_oracle_proof` (ECDSA secp256k1) e `_meta`. | **Must** |
| **RF09** | Oferecer amostra gratuita no desafio 402 (Teaser) com razão social, UF e indicação de existência de sanções antes do pagamento. | **Must** |
| **RF10** | Implementar a ferramenta `grid402_get_dossier_360` no Servidor MCP (`mcp/tools.mjs` e `mcp/index.mjs`) com schema validado. | **Must** |
| **RF11** | Adicionar o novo endpoint no catálogo do `index.php`, `README.md`, `llms.txt` e `/status`. | **Should** |

---

## 7. Requisitos não funcionais
- **Performance:** Resposta em menos de 100ms quando em cache local; menos de 800ms em consulta fria aos oráculos internos.
- **Armazenamento Zero-DB:** Cache atômico com concorrência segura (`flock`) em `grid402_storage/dossier_cache.json`.
- **Privacidade e LGPD:** Ausência total de dados pessoais de sócios (Zero PII). Exclusivo para dados de Pessoa Jurídica.
- **Idempotência:** Suporte ao cabeçalho `Idempotency-Key: <uuid>` em conjunto com Session Pass para evitar débitos repetidos em retentativas.

---

## 8. Fluxos e UX

### 8.1. Fluxo Principal (Agente com Session Pass ou Carteira Base)
1. Agente envia `GET /api/v1/dossier?cnpj=00000000000191` com cabeçalho `X-Session-Pass: pass_g402_...` (ou assinatura x402 de 0.0500 USDC).
2. O Grid402 valida o pagamento (debita 10 créditos ou confirma liquidação Base).
3. O orquestrador interno consulta o cache consolidado ou aciona os oráculos de CNPJ, Simples e Sanções.
4. O motor computa o score, os alertas e o veredito (`APPROVED`, `FLAGGED` ou `REJECTED`).
5. O oráculo assina o hash com ECDSA secp256k1.
6. A resposta JSON é entregue com HTTP 200.

### 8.2. Fluxo Alternativo (Desafio HTTP 402 com Teaser)
1. Agente envia `GET /api/v1/dossier?cnpj=00000000000191` sem pagamento.
2. O Grid402 responde HTTP 402 com preço `0.0500` USDC, carteira do oráculo e prévia segura dos dados (razão social, município, UF e contagem de sanções).
3. O agente analisa a prévia, aprova a despesa de 0.0500 USDC e envia a transação na Base.

---

## 9. Métricas de sucesso
- **Aumento do ARPU:** Crescimento do ticket médio por consulta cadastral de \$0.02 para \$0.05 USDC.
- **Adoção do MCP:** Registro de chamadas da nova tool `grid402_get_dossier_360` no log de telemetria.
- **Taxa de Sucesso Técnico:** Mais de 99.5% de disponibilidade com latência média sub-100ms em cache.
- **Cobertura de Testes:** 100% dos requisitos funcionais (Must) validados na suíte automatizada de testes PHP.

---

## 10. Riscos, dependências e premissas
- **Dependência de Upstream:** Depende da estabilidade dos oráculos internos de CNPJ (Receita Federal) e Sanções (CGU). Mitigado com cache atômico de 24h a 7 dias.
- **Falso Positivo de Risco:** Score conservador baseado em evidências documentais públicas incontestáveis (CEIS/CNEP oficiais).
- **Sobrecarga de Requisições:** Mitigado pelo buffer circular em cache e travamento atômico Zero-DB.

---

## 11. Questões em aberto
- [x] Precificação definida em 0.0500 USDC (10 créditos do Session Pass).
- [x] Rota canônica definida em `/api/v1/dossier`.
- [x] Inclusão confirmada no Servidor MCP (`grid402_get_dossier_360`).
