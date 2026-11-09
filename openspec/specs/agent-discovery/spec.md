# agent-discovery Specification

## Purpose
TBD - created by archiving change add-ai-agent-discovery-and-marketplaces. Update Purpose after archive.

## Requirements

### Requirement: Arquivos de Descoberta para LLMs
O servidor SHALL disponibilizar arquivos de texto puro `llms.txt` e `llms-full.txt` na raiz publica, formatados segundo a especificacao llmstxt.org, contendo explicacao sucinta e completa das APIs, precificacao em USDC na Base, fluxo HTTP 402 e apontando exclusivamente para o dominio canonico `https://grid402.online`.

#### Scenario: Requisicao com sucesso de llms.txt
- GIVEN o servidor web em producao
- WHEN um agente de IA efetua um GET em `/llms.txt`
- THEN o servidor SHALL retornar status HTTP 200, Content-Type `text/plain`, cabecalho `Access-Control-Allow-Origin: *` e todas as URLs apontando para `https://grid402.online`.

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
