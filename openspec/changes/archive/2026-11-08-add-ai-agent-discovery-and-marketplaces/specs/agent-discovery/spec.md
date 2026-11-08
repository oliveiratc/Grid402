## ADDED Requirements

### Requirement: Arquivos de Descoberta para LLMs
O servidor SHALL disponibilizar arquivos de texto puro `llms.txt` e `llms-full.txt` na raiz publica, formatados segundo a especificacao llmstxt.org, contendo explicacao sucinta e completa das APIs, precificacao em USDC na Base e fluxo HTTP 402.

#### Scenario: Requisicao com sucesso de llms.txt
- GIVEN o servidor web em execucao
- WHEN um agente de IA efetua um GET em `/llms.txt`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `text/plain` e cabecalho `Access-Control-Allow-Origin: *`.

#### Scenario: Requisicao com sucesso de llms-full.txt
- GIVEN o servidor web em execucao
- WHEN um agente de IA efetua um GET em `/llms-full.txt`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `text/plain` com documentacao tecnica detalhada de todos os microservicos e schemas JSON.

### Requirement: Manifestos Padronizados de Agentes e MCP
O servidor SHALL expor manifestos JSON padronizados sob `/.well-known/` para descoberta automatica por clientes MCP e orquestradores de agentes.

#### Scenario: Consulta ao manifesto MCP
- GIVEN o servidor web em execucao
- WHEN um cliente efetua um GET em `/.well-known/mcp.json`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `application/json` e a lista completa de ferramentas disponiveis com parametros e schemas.

#### Scenario: Consulta ao manifesto AI Plugin
- GIVEN o servidor web em execucao
- WHEN um orquestrador de IA efetua um GET em `/.well-known/ai-plugin.json`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `application/json` com metadados do Grid402 e url para OpenAPI.

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
