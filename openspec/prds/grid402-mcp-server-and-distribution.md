# PRD: Servidor MCP Oficial e Ecossistema de Distribuição M2M para Agentes de IA

**Status:** Aprovado  
**Data:** 2026-11-07  
**Autor:** Thiago Campos + Antigravity  

---

## 1. Contexto e problema
O **Grid402** está ativo em produção na Hostinger fornecendo 6 micro-serviços de alta performance (Câmbio, Cripto, DNS, SSL, ASN e CEP) monetizados por chamada via protocolo **x402** na rede **Base Mainnet**.
Entretanto, o acesso de agentes autônomos de IA enfrenta fricções de adoção:
1. Agentes modernos (como Claude Desktop, Cursor, Open-Interpreter e Smithery.ai) padronizaram o consumo de ferramentas através do **Model Context Protocol (MCP)**, tornando necessária a existência de um MCP Server oficial compatível com `npx`.
2. Quando um agente recebe um desafio `HTTP 402 Payment Required`, o pagamento de 0.0005 USDC exige automação via carteira Base do operador do agente; sem um mecanismo de pagamento automático assistido por variável de ambiente, o agente pode interromper o fluxo se não souber assinar e enviar transações ERC-20 na Base.
3. Os SDKs oficiais (Python e Node.js) precisam estar publicados nos registros universais (**PyPI** e **npm**) e acompanhados de materiais prontos de divulgação direcionados às comunidades ativas de agentes de IA na rede Base e Farcaster.

---

## 2. Objetivos
- Desenvolver e disponibilizar um **Servidor MCP Oficial em Node.js** (`@grid402/mcp-server` / `npx grid402-mcp`) compatível com a especificação Model Context Protocol da Anthropic, instalável com um comando no Claude Desktop, Cursor e Smithery.ai.
- Disponibilizar ferramentas MCP para todos os 6 utilitários do catálogo:
  - `grid402_forex`: Cotações USD, EUR, GBP para BRL.
  - `grid402_crypto`: Tickers BTC, ETH, SOL, USDC na Base.
  - `grid402_dns`: Resolução de registros DNS (A, AAAA, MX, TXT, NS, CNAME).
  - `grid402_ssl`: Auditoria de validade, emissor e dias restantes de certificados TLS/SSL.
  - `grid402_asn`: Inteligência de roteamento BGP, número ASN e bloco IP.
  - `grid402_cep`: Consulta de endereçamento postal brasileiro e código IBGE.
  - `grid402_summary`: Resumo consolidado de saúde dos serviços (gratuito).
- Implementar **pagamento x402 assistido/automático**: se configurada a variável de ambiente `GRID402_PRIVATE_KEY` (chave de carteira EVM Base com saldo de USDC), o cliente/MCP assina a transferência de 0.0005 USDC, aguarda a confirmação on-chain, injeta o cabeçalho `X-Payment` e retorna os dados completos; na ausência da chave, retorna o desafio 402 estruturado.
- Atualizar e empacotar os SDKs Python (`sdk/python`) e Node.js (`sdk/node`) para publicação oficial no PyPI e npm.
- Gerar materiais completos de divulgação e templates de bots para distribuição no ecossistema Base / Farcaster (Warpcast).

---

## 3. Não objetivos (fora do escopo)
- Não haverá custódia de fundos: o MCP Server apenas assina transações locais enviadas diretamente ao nó RPC da Base (`https://mainnet.base.org`). Chaves privadas nunca são enviadas ao servidor Grid402.
- Não haverá criação de novos micro-serviços além dos 6 já homologados no Grid402.
- Não haverá criação de interface visual adicional além da documentação e configuração nos clientes MCP (Claude/Cursor).

---

## 4. Público-alvo e personas
1. **Desenvolvedores de Agentes Autônomos (CrewAI, LangChain, AutoGen):** Precisam de ferramentas de utilidade de dados prontas, confiáveis e sem limites rígidos de rate-limit.
2. **Usuários do Claude Desktop e Cursor:** Profissionais técnicos que desejam consultar câmbio, DNS, SSL e CEP diretamente do seu assistente de IA.
3. **Comunidade Web3 e Base Builders:** Criadores de agentes autônomos no Farcaster que já utilizam USDC na Base para micropagamentos M2M.

---

## 5. Histórias de usuário
1. **Como usuário do Claude Desktop ou Cursor**, quero adicionar o Grid402 nas minhas configurações MCP via `npx @grid402/mcp-server`, para que o assistente possa resolver DNS, auditar SSL ou checar cotações fiduciárias diretamente no chat.
2. **Como desenvolvedor de IA**, quero fornecer uma chave privada com 1 USDC de saldo em `GRID402_PRIVATE_KEY` no `.env`, para que o SDK/MCP liquide as consultas pagas de 0.0005 USDC automaticamente sem interromper a execução do agente.
3. **Como desenvolvedor Python**, quero instalar `pip install grid402` e instanciar ferramentas prontas para CrewAI ou LangChain com 2 linhas de código.
4. **Como desenvolvedor Node.js**, quero rodar `npm install grid402` para importar funções assíncronas com tipagem TypeScript completa (`.d.ts`).
5. **Como operador do Grid402**, quero posts de lançamento técnicos e objetivos prontos para divulgação no Farcaster/Warpcast e GitHub, para atrair tráfego e consultas reais de agentes.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Servidor MCP oficial em Node.js (`mcp/`) compatível com StdioServerTransport da especificação MCP. | Must |
| **RF02** | Exportação de 7 ferramentas MCP completas (`forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `summary`) com schemas JSON rigorosos. | Must |
| **RF03** | Módulo de liquidação x402 automática na Base Mainnet via `ethers.js` ou `viem` quando chave privada for fornecida. | Must |
| **RF04** | Modo fallback seguro: retorno de payload explicativo de `PaymentRequired` se nenhuma chave de pagamento estiver configurada. | Must |
| **RF05** | Configuração manifesta para Smithery.ai (`smithery.yaml`) e instruções para Claude Desktop (`claude_desktop_config.json`). | Must |
| **RF06** | Atualização do pacote Node.js (`sdk/node`) com metadados do hub, scripts e tipos TypeScript (`grid402.d.ts`). | Must |
| **RF07** | Atualização do pacote Python (`sdk/python`) com `pyproject.toml`, build de distribuição e homologação. | Must |
| **RF08** | Guia de distribuição e templates para Farcaster/Warpcast, CDP AgentKit e comunidades de IA. | Should |

---

## 7. Requisitos não funcionais
- **RNF01 (Segurança de Chaves):** Chaves privadas manipuladas pelo MCP Server nunca trafegam pela rede e nunca saem da memória do processo local.
- **RNF02 (Tolerância a Falhas RPC):** Fallback para nós RPC alternativos da Base (`mainnet.base.org`, `1rpc.io/base`, `base.llamarpc.com`).
- **RNF03 (Compatibilidade Node.js):** Compatibilidade estrita com Node.js 18+ LTS.
- **RNF04 (Zero Bloqueio):** Operações assíncronas sem travar o transporte stdio do MCP.

---

## 8. Métricas de sucesso
- 100% das 7 ferramentas MCP testadas e respondendo com dados reais via stdio.
- Teste de integração de pagamento automático simulado com sucesso na Base.
- Pacotes `grid402` (Python) e `@grid402/mcp-server` prontos para publicação nos registros PyPI e npm.

---

## 9. Riscos e premissas
- **P01:** O domínio temporário da Hostinger continuará ativo até que o domínio oficial seja apontado, sendo o endpoint padrão configurável via parâmetro ou variável `GRID402_ENDPOINT`.
- **R01 (Gás na Base):** A transação de transferência de USDC na Base consome uma fração de centavo de ETH (≈ US$ 0.0001). A carteira pagadora precisa ter saldo mínimo de ETH para gás além do USDC.

