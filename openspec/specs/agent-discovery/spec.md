# agent-discovery Specification

## Purpose
Define os artefatos de descoberta autônoma M2M, governança institucional (`/trust`), manifesto para LLMs (`llms.txt` / `llms-full.txt`), especificação `openapi.json`, `robots.txt` e transparência operacional (`CHANGELOG.md` e `/.well-known/security.txt`) para crawlers e agentes de IA.

## Requirements

### Requirement: Arquivos de Descoberta para LLMs
O servidor SHALL disponibilizar arquivos de texto puro `llms.txt` e `llms-full.txt` na raiz publica, formatados segundo a especificacao llmstxt.org, contendo explicacao sucinta e completa dos 22 servicos das APIs, precificacao unificada em USDC na Base (`0.0050 a 0.0300 USDC`), matematica exata do Session Pass (`2 USDC = 400 creditos`, `5 USDC = 1.000 creditos`, `20 USDC = 6.000 creditos`), fluxo HTTP 402 e apontando exclusivamente para o dominio canonico `https://grid402.online`.

#### Scenario: Requisicao com sucesso de llms.txt
- GIVEN o servidor web em producao
- WHEN um agente de IA efetua um GET em `/llms.txt`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `text/plain`, cabecalho `Access-Control-Allow-Origin: *`, listagem dos 22 servicos, tabela de Session Pass (`2 USDC = 400 creditos`, `5 USDC = 1.000 creditos`, `20 USDC = 6.000 creditos`) e todas as URLs apontando para `https://grid402.online`.

#### Scenario: Requisicao com sucesso de llms-full.txt
- GIVEN o servidor web em producao
- WHEN um agente de IA efetua um GET em `/llms-full.txt`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `text/plain` com documentacao tecnica e endpoints sob `https://grid402.online`.

### Requirement: Manifestos Padronizados de Agentes e MCP
O servidor SHALL expor manifestos JSON padronizados sob `/.well-known/` para descoberta automatica por clientes MCP e orquestradores de agentes, com endpoints referenciando a raiz `https://grid402.online`.

#### Scenario: Consulta ao manifesto MCP
- GIVEN o servidor web em producao
- WHEN um cliente efetua um GET em `/.well-known/mcp.json`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `application/json` e a URL base configurada para `https://grid402.online`.

#### Scenario: Consulta ao manifesto AI Plugin
- GIVEN o servidor web em producao
- WHEN um orquestrador de IA efetua um GET em `/.well-known/ai-plugin.json`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `application/json` com url apontando para `https://grid402.online/openapi.json`.

### Requirement: Central de Agentes e Desenvolvedores
O sistema SHALL prover uma rota `/agents` renderizando uma interface terminal interativa com snippets de configuracao copiados para Claude Desktop, Python e cURL.

#### Scenario: Acesso a pagina /agents no navegador
- GIVEN o usuario acessando o Grid402 via browser
- WHEN requisitar a rota `/agents`
- THEN o servidor SHALL retornar status HTTP 200, layout tematico dark/terminal responsivo, bloco copiador de configuracao MCP e exemplos de chamada x402.

### Requirement: Roteamento e Cabecalhos de Descoberta
O servidor SHALL configurar o `.htaccess` para rotear `/agents` amigavelmente e garantir que todos os arquivos de descoberta sejam servidos com CORS aberto.

#### Scenario: Pre-flight ou requisicao cross-origin em manifestos
- GIVEN uma requisicao cross-origin originada de um cliente web de IA
- WHEN consultar qualquer arquivo `.txt` ou `.json` de descoberta
- THEN o servidor SHALL incluir o cabecalho `Access-Control-Allow-Origin: *`.

### Requirement: Redirecionamento Canonico e Legado
O servidor SHALL forcar HTTPS e redirecionar todo o trafego com prefixo `www.` e o subdominio legado `orangered-jackal-900744.hostingersite.com` para o dominio canonico `https://grid402.online` atraves de redirecionamento HTTP 301 permanente.

#### Scenario: Acesso via www
- GIVEN um cliente acessando `http://www.grid402.online/api/v1/forex`
- WHEN a requisicao alcancar o servidor web
- THEN o servidor SHALL responder com status HTTP 301 e cabecalho `Location: https://grid402.online/api/v1/forex`.

#### Scenario: Acesso via subdominio legado
- GIVEN um agente de IA acessando `https://orangered-jackal-900744.hostingersite.com/api/v1/cnpj?cnpj=00000000000191`
- WHEN a requisicao alcancar o servidor legado
- THEN o servidor SHALL responder com status HTTP 301 e cabecalho `Location: https://grid402.online/api/v1/cnpj?cnpj=00000000000191`.

### Requirement: Plugin Oficial para Framework ElizaOS
O repositório SHALL prover uma integração oficial em TypeScript no padrão ElizaOS (`@elizaos/core`), permitindo que agentes autônomos executem ações de consulta aos serviços de Mercados e Compliance do Grid402.

#### Scenario: Agente consulta câmbio via Plugin ElizaOS
- GIVEN um agente executando com o plugin `@grid402/plugin-elizaos` carregado
- WHEN o usuário solicitar uma cotação de câmbio (ex: USD para BRL)
- THEN o plugin SHALL disparar a ação `GET_FOREX_RATE`, injetar o Session Pass configurado ou liquidar via x402 e responder com os dados normalizados de câmbio.

#### Scenario: Agente verifica sanções OFAC via Plugin ElizaOS
- GIVEN um agente executando com o plugin do Grid402
- WHEN o usuário pedir para checar um nome ou entidade em listas de sanções internacionais
- THEN o plugin SHALL executar a ação `CHECK_SANCTIONS_OFAC` e retornar o resultado detalhado com pontuação de risco.

### Requirement: Dossiê Executivo para Base Grants e CDP
O repositório SHALL conter documentação detalhada em `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` formatada para submissão imediata aos programas de incentivo e subsídio de infraestrutura da Base Foundation e Coinbase Developer Platform.

#### Scenario: Consulta ao dossiê de submissão
- GIVEN o operador preparando a submissão de grants
- WHEN acessar `docs/BASE_GRANTS_AND_CDP_DOSSIER.md`
- THEN o documento SHALL conter o resumo executivo, métricas de rede, arquitetura do x402, casos de uso para agentes de IA e as respostas para os formulários oficiais da Base e Coinbase CDP.

### Requirement: Acessibilidade para Crawlers de IA e Auditoria Automatizada
O servidor SHALL disponibilizar o arquivo `public_html/robots.txt` autorizando explicitamente os agentes de rastreamento de modelos de linguagem (`ClaudeBot`, `Claude-Web`, `GPTBot`, `ChatGPT-User`, `OAI-SearchBot`, `PerplexityBot`, `Google-Extended`) e garantir que as rotas `/trust`, `/catalog`, `/status`, `/openapi.json`, `/llms.txt` e `/.well-known/security.txt` respondam com HTTP 200 e cabeçalho `Access-Control-Allow-Origin: *`.

#### Scenario: Consulta ao robots.txt por crawler de LLM
- **GIVEN** um crawler de auditoria de IA (`ClaudeBot` ou `GPTBot`) inspecionando o domínio
- **WHEN** efetuar uma requisição `GET /robots.txt`
- **THEN** o servidor SHALL responder com HTTP 200, `Content-Type: text/plain` e regras `Allow: /` explícitas para os principais User-Agents de IA, além de referenciar o Sitemap e o `/llms.txt`.

#### Scenario: Auditoria direta da página /trust e /openapi.json por agente
- **GIVEN** um agente de IA requisitando `/trust` ou `/openapi.json`
- **WHEN** a requisição alcançar o servidor
- **THEN** o servidor SHALL responder com HTTP 200 sem bloqueio de User-Agent, exibindo a documentação de confiança e o contrato OpenAPI 3.1 atualizado com os 22 serviços.

### Requirement: Transparência Institucional, Governança e Security.txt
O sistema SHALL expor em `/trust` a identificação institucional do operador responsável, a tabela de fontes públicas oficiais com seus respectivos tempos de cache (TTL), o SLA de Best-Effort (`99.5% target`), a distinção explícita entre dados públicos verificados com prova ECDSA e birôs privados de crédito, a nota de desambiguação de marca (`grid402.online`), além de disponibilizar `CHANGELOG.md` na raiz do repositório e `/.well-known/security.txt` em produção.

#### Scenario: Consulta à página /trust para verificação de governança
- **GIVEN** um avaliador técnico acessando `https://grid402.online/trust`
- **WHEN** a página for renderizada
- **THEN** a página SHALL exibir a seção de Identificação do Operador e Governança, a matriz de proveniência e TTL por fonte (Receita Federal, BCB, IBGE, CGU, GLEIF, OFAC), o histórico de uptime de 30 dias, o resumo de Changelog de preços/versões e a nota de desambiguação de marca.
