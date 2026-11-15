## ADDED Requirements

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

## MODIFIED Requirements

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
