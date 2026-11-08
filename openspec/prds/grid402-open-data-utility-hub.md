# PRD: Grid402 // Hub de Utilitários de Rede e Dados Públicos Abertos para Agentes M2M (Protocolo x402)

**Status:** Em revisão  
**Data:** 2026-11-07  
**Autor:** oliveiratc + Antigravity  

---

## 1. Contexto e Problema

Agentes autônomos de IA (CrewAI, LangChain, AutoGen), robôs e pipelines de automação/scraping precisam constantemente de utilitários técnicos e fatos do mundo real para tomada de decisão em tempo de execução:
- Cotações confiáveis de moedas fiduciárias e criptoativos;
- Resolução de registros DNS e validação de certificados SSL de domínios;
- Identificação de blocos IP e ASNs de roteamento;
- Resolução de endereçamento público e códigos IBGE municipais.

### A Dor Atual dos Agentes Autônomos
Atualmente, o consumo dessas micro-ferramentas exige:
1. **Contas e Chaves de API (API Keys):** Necessidade de cadastro humano, validação de e-mail e geração de tokens pré-pagos em dezenas de provedores SaaS diferentes;
2. **Fricção de Pagamento:** Cartões de crédito tradicionais não podem ser operados de forma autônoma e segura por agentes de IA;
3. **Riscos Jurídicos e LGPD para o Operador:** Monitoramento de alvos corporativos ou scraping de dados privados gera risco regulatório incompatível com operação por **Pessoa Física**.

O **Grid402** resolve esse problema ao fornecer um catálogo unificado de 6 micro-serviços essenciais, 100% baseados em **dados de domínio público e protocolos abertos da internet**, monetizados chamada a chamada via protocolo **HTTP 402 (`x402`)** liquidado em USDC na rede **Base Mainnet**.

---

## 2. Objetivos

- **O1:** Disponibilizar 6 micro-serviços core de alta demanda para robôs e agentes com latência média de entrega inferior a **50 ms** para dados em cache.
- **O2:** Implementar monetização M2M fluida via protocolo HTTP 402 com custo fixo e acessível de **US$ 0.0005 USDC** (500 unidades atômicas) na Base Mainnet por consulta aprofundada.
- **O3:** Garantir **Risco Zero sob a LGPD** e conformidade jurídica absoluta para Pessoa Física, utilizando estritamente dados públicos, utilitários de infraestrutura e zero tratamento de dados pessoais (PII).
- **O4:** Manter arquitetura **Zero-DB** sobre a infraestrutura existente (Hostinger LiteSpeed + PHP 8.3), com custo fixo praticamente nulo e atomicidade de escrita.

---

## 3. Não Objetivos (Fora do Escopo)

- **Scraping de Sites Privados:** O Grid402 NÃO fará raspagem de conteúdo protegido por direitos autorais ou paywalls de terceiros.
- **Armazenamento de Dados Pessoais (PII):** Nenhum dado identificável de cidadãos (CPF, nome, e-mail, telefone) será coletado, armazenado ou retornado.
- **Banco de Dados Relacional (SQL):** Não haverá MySQL, PostgreSQL ou SQLite; toda a persistência de cache e controle de anti-replay permanecerá em flat-files JSON atômicos.
- **Custódia de Criptoativos:** O portal não realiza intermediação, swap ou custódia de fundos (não é VASP/corretora).

---

## 4. Público-Alvo e Personas

1. **Agente Autônomo de IA (Persona Primária):** Scripts autônomos em Python, TypeScript ou frameworks como LangChain e CrewAI que possuem carteiras Web3 (EVM) e demandam utilitários de rede ou conversões financeiras em tempo real.
2. **Desenvolvedores de Bots e Scraping:** Engenheiros que constroem pipelines de dados e precisam enriquecer metadados (DNS, SSL, ASN, CEP) sem rate-limits asfixiantes de APIs públicas gratuitas.
3. **Sistemas M2M e Microsserviços:** Aplicações de backend que necessitam de consultas rápidas sob demanda sem assinar planos SaaS mensais caros.

---

## 5. Histórias de Usuário

- **US01:** Como agente autônomo, quero consultar a taxa de câmbio USD/BRL e EUR/BRL atualizada via GET simples para converter valores de liquidação em tempo de execução.
- **US02:** Como robô de trading/arbitragem, quero consultar cotações das principais criptomoedas (BTC, ETH, SOL, USDC) com timestamp preciso.
- **US03:** Como pipeline de auditoria, quero inspecionar os registros DNS (A, MX, TXT, NS) de um domínio sem configurar ferramentas de terminal locais.
- **US04:** Como agente de segurança, quero verificar a cadeia de certificados SSL de um host e quantos dias restam até a expiração para disparar alertas automáticos.
- **US05:** Como bot de enriquecimento geográfico, quero consultar um CEP brasileiro e obter logradouro, bairro, cidade, UF e código IBGE oficial.
- **US06:** Como agente de IA com carteira Base, quero receber um desafio HTTP 402 limpo contendo o valor e carteira destino, pagar 0.0005 USDC e obter a resposta completa enviando o `X-Payment: <tx_hash>`.

---

## 6. Requisitos Funcionais

| ID | Requisito | Descrição | Prioridade |
|---|---|---|---|
| **RF01** | **API de Câmbio Fiduciário** | Retorna cotação em tempo real de USD, EUR, GBP para BRL com timestamp e variação diária. | Must |
| **RF02** | **API de Criptoativos** | Retorna cotação de BTC, ETH, SOL e tokens da Base em USD e BRL. | Must |
| **RF03** | **API de DNS Lookup** | Resolve registros A, AAAA, MX, TXT, NS e CNAME de qualquer domínio solicitado via query string. | Must |
| **RF04** | **API de Inspeção SSL** | Conecta na porta 443 do host alvo e extrai emissor, validade, SANs e dias até expirar. | Must |
| **RF05** | **API de ASN e IP Info** | Retorna metadados de rede pública de um IP ou do requisitante (ASN, organização, país). | Must |
| **RF06** | **API de CEP e IBGE** | Consulta base pública de endereçamento nacional retornando logradouro, bairro, UF e código IBGE. | Must |
| **RF07** | **Porteiro HTTP 402 (x402)** | Emite desafio `402 Payment Required` quando solicitada consulta detalhada/raw e valida o recibo on-chain na Base Mainnet. | Must |
| **RF08** | **Prevenção Anti-Replay Atômica** | Garante com trava de arquivo (`flock`) que cada hash de transação na Base só possa ser liquidado uma única vez. | Must |
| **RF09** | **Camada Gratuita de Descoberta** | Endpoint `/status` ou `/summary` entregando visão consolidada dos utilitários sem cobrança para health checks. | Must |
| **RF10** | **Landing Page e Playground** | Interface dark-mode adaptativa para navegadores com console interativo de testes de cada um dos 6 serviços. | Should |
| **RF11** | **Manifesto `llms.txt`** | Documentação padronizada na raiz do domínio para indexação direta por crawlers de LLM (GPT, Claude, Gemini, Perplexity). | Must |
| **RF12** | **Badges Dinâmicos SVG** | Rota `/badge/{servico}.svg` para exibição de status em READMEs do GitHub. | Should |

---

## 7. Requisitos Não Funcionais

- **RNF01 (Performance):** Consultas com dados em cache devem responder em menos de **50 ms**; consultas ativas de rede (DNS/SSL) devem responder em menos de **600 ms**.
- **RNF02 (Arquitetura Zero-DB):** Persistência estritamente baseada em arquivos JSON atômicos com `.htaccess` bloqueando acesso externo a `private_data/`.
- **RNF03 (Segurança e LGPD):** Zero armazenamento de dados pessoais. Registros de IP mantidos por 6 meses estritamente conforme o Art. 15 do Marco Civil da Internet.
- **RNF04 (Compatibilidade de Plataforma):** Código PHP 8.1+ puro, sem extensões exóticas, compatível com o ambiente Hostinger LiteSpeed.
- **RNF05 (Robustez Blockchain):** Validação JSON-RPC direta contra a Base Mainnet (`eth_getTransactionReceipt`) checando status de sucesso, valor mínimo (500 units) e contrato oficial do USDC (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`).

---

## 8. Fluxos e UX

### 8.1. Fluxo de Máquina Gratuito (Resumo / Health Check)
```
Agente -> GET /api/v1/summary (Accept: application/json)
Servidor -> 200 OK (JSON com cotações recentes e status dos 6 utilitários)
```

### 8.2. Fluxo Pago x402 (Consulta Técnica Profunda / On-Demand)
```
1. Agente -> GET /api/v1/dns?domain=github.com
2. Servidor -> 402 Payment Required
   Headers:
     X-Authenticate: x402
     X-Accept-Payment: base:usdc:0xa31c31069c43ac1c0075a93c325b0dcd7744356c:0.0005
   Body: JSON com instrução de pagamento na Base

3. Agente -> Transfere 0.0005 USDC na Base -> obtém <tx_hash>
4. Agente -> GET /api/v1/dns?domain=github.com
   Header: X-Payment: <tx_hash>
5. Servidor -> Valida recibo on-chain na Base -> Grava anti-replay com trava -> 200 OK (Dados DNS completos)
```

---

## 9. Métricas de Sucesso

- **M01:** 100% dos testes unitários e de integração E2E passando com sucesso.
- **M02:** Tempo de resposta do servidor inferior a 50 ms para requisições em cache.
- **M03:** Zero alertas ou inconformidades de LGPD na auditoria de conformidade.
- **M04:** Taxa de rejeição de replay attacks de 100% em testes concorrentes.

---

## 10. Riscos, Dependências e Premissas

- **P01:** O usuário opera como Pessoa Física e deseja manter despesas fixas mínimas (hospedagem Hostinger existente).
- **R01 (Rate Limit de Provedores Públicos):** APIs públicas de câmbio/cripto podem impor rate-limit se consultadas em excesso.  
  *Mitigação:* Implementar cache TTL de 60 segundos nos coletores, evitando chamadas repetitivas.
- **R02 (Instabilidade do RPC da Base):** O RPC público da Base pode apresentar latência transitória.  
  *Mitigação:* Configurar timeout agressivo (1.8s) e fallback estruturado.

---

## 11. Questões em Aberto

*Nenhuma no momento. Todas as decisões de catálogo, modelo de preço, stack técnica e aspectos jurídicos foram alinhadas e aprovadas na etapa de Grill-me.*

