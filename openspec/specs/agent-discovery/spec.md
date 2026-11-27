# agent-discovery Specification

## Purpose
Define os artefatos de descoberta autônoma M2M, governança institucional (`/trust`), manifesto para LLMs (`llms.txt` / `llms-full.txt`), especificação `openapi.json`, `robots.txt` e transparência operacional (`CHANGELOG.md` e `/.well-known/security.txt`) para crawlers e agentes de IA.

## Requirements

### Requirement: Arquivos de Descoberta para LLMs
O servidor SHALL disponibilizar `public_html/llms.txt` integralmente em inglês e `public_html/llms-full.txt` na raiz pública, formatados segundo llmstxt.org, documentando os 25 serviços, preços em USDC (`0.0050` a `0.0500`), Session Pass (`micro`, `starter`, `pro`, `fleet`), cupom `MCPTRY50`, `/mcp` com auto-trial, RapidAPI USD e `/dossie-kyb` sem Solana ativa e sem `&`.

#### Scenario: Requisicao com sucesso de llms.txt
- **GIVEN** o servidor web em produção com `GRID402_SOLANA_X402_ENABLED=false`
- **WHEN** um agente de IA efetua `GET /llms.txt`
- **THEN** o servidor SHALL retornar HTTP `200`, `Content-Type: text/plain` e cabeçalho `Access-Control-Allow-Origin: *`
- **AND** o documento SHALL estar redigido em inglês, conter `MCPTRY50`, `/mcp` (27 tools + auto-trial diário), `Micro` (R$ 4.90 / 50 credits) e tabelas `Starter/Pro/Fleet`, RapidAPI USD, `/dossie-kyb` e URLs sob `https://grid402.online`
- **AND** o conteúdo SHALL NOT conter o caractere `&` nem anunciar Solana como rede ativa.

#### Scenario: Requisicao com sucesso de llms-full.txt
- **GIVEN** o servidor web em produção
- **WHEN** um agente de IA efetua `GET /llms-full.txt`
- **THEN** o servidor SHALL retornar status HTTP `200`, `Content-Type: text/plain` com documentação técnica e endpoints sob `https://grid402.online` sem o caractere `&`.

### Requirement: Manifestos Padronizados de Agentes e MCP
O servidor SHALL expor manifestos JSON padronizados sob `/.well-known/mcp.json` e `/.well-known/mcp/server-card.json` para descoberta automática por clientes MCP e diretórios externos (como Glama e Smithery), listando todas as 24 ferramentas disponíveis com endpoints apontando para `https://grid402.online`.

#### Scenario: Consulta ao manifesto MCP
- **GIVEN** o servidor web em producao
- **WHEN** um cliente efetua um GET em `/.well-known/mcp.json` ou `/.well-known/mcp/server-card.json`
- **THEN** o servidor SHALL retornar status HTTP 200, Content-Type `application/json`, URL base configurada para `https://grid402.online` e as 24 ferramentas registradas sincronizadas com `mcp/tools.mjs`.

#### Scenario: Consulta ao manifesto AI Plugin
- **GIVEN** o servidor web em producao
- **WHEN** um orquestrador de IA efetua um GET em `/.well-known/ai-plugin.json`
- **THEN** o servidor SHALL retornar status HTTP 200, Content-Type `application/json` com url apontando para `https://grid402.online/openapi.json`.

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
O sistema SHALL expor em `/trust` e `/status` a identificação do operador, matriz de fontes públicas e TTL, SLA (`99.5% target`), status público de frescor/integridade do índice de sanções e a política transiente com respeito a `robots.txt`/diretivas anti-AI do `/api/v1/extract`, mantendo `CHANGELOG.md` e `/.well-known/security.txt`.

#### Scenario: Consulta à página /trust para verificação de governança
- **GIVEN** um avaliador técnico acessando `https://grid402.online/trust`
- **WHEN** a página for renderizada
- **THEN** a página SHALL exibir a seção de Identificação do Operador e Governança, a matriz de proveniência e TTL por fonte (Receita Federal, BCB, IBGE, CGU, GLEIF, OFAC), o histórico de uptime de 30 dias, o resumo de Changelog de preços/versões e a nota de desambiguação de marca.

#### Scenario: Consulta à página /trust e /status exibe sanções e política transiente
- **GIVEN** um avaliador técnico acessando `https://grid402.online/trust` ou `https://grid402.online/status`
- **WHEN** a resposta for gerada
- **THEN** `/trust` SHALL exibir a política transiente sem retenção em disco e respeito a `robots.txt`/`X-Robots-Tag`/`Content-Signal` do `/api/v1/extract` e a matriz dos 4 blocos de sanções
- **AND** `/status` SHALL incluir o bloco público `sanctions_index` indicando disponibilidade e status de frescor sem expor caminhos internos do servidor

### Requirement: Dual-License Explicita e Higiene de Dominio e Precos Legados
O repositório SHALL explicitar em `LICENSE` e `README.md` a separação Dual-License entre o gateway proprietário (`public_html/`) e os SDKs, MCP Server e integrações sob licença MIT, garantindo o posicionamento *"The Verifiable Data and x402 Payment Layer for Autonomous AI Agents"* e zero referências ativas a `grid402.com.br` ou `0.0005`.

#### Scenario: Verificacao de Dual-License e ausencia de referencias legadas
- **GIVEN** os arquivos `LICENSE`, `README.md`, `.env.example`, `examples/*` e `sdk/*` do repositório
- **WHEN** um auditor ou suite automatizada inspecionar licenças, URLs e tarifas padrão
- **THEN** `LICENSE` e `README.md` SHALL explicitar a divisão entre servidor proprietário (`public_html/`) e SDKs/MCP em `MIT`
- **AND** nenhuma referência ativa em `examples/` ou `sdk/` SHALL apontar para `grid402.com.br` ou usar `0.0005` como preço vigente.

### Requirement: Descoberta da Rota Gratuita /api/v1/pass/statement nos Manifestos Públicos
Os documentos públicos de descoberta (`public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt` e `/pass`) SHALL documentar a rota gratuita `GET /api/v1/pass/statement` (`format=json|csv`, `endpoint`, `outcome`, retenção máxima de `200` eventos recentes por passe) mantendo conformidade estrita sem o caractere `&` nos arquivos `llms*.txt` e manifestos M2M.

#### Scenario: Presença da rota /api/v1/pass/statement no openapi.json e llms.txt sem ampersand
- **GIVEN** os arquivos públicos `public_html/openapi.json`, `public_html/llms.txt` e `public_html/llms-full.txt`
- **WHEN** auditados por clientes M2M ou pela suíte `tests/test_agent_discovery.php`
- **THEN** `openapi.json` SHALL descrever o path `/api/v1/pass/statement` com parâmetros `token`, `format`, `endpoint` e `outcome`, e `llms*.txt` SHALL permanecer livre do caractere `&`.

### Requirement: Manifesto Dinâmico /.well-known/x402.json e /.well-known/x402
O servidor SHALL servir `/.well-known/x402.json` e `/.well-known/x402` dinamicamente via PHP (`public_html/well_known_x402.php` roteado por `public_html/.htaccess`) com `Access-Control-Allow-Origin: *`, refletindo em tempo real `GRID402_SOLANA_X402_ENABLED` e os facilitadores ativos por rede.

#### Scenario: Manifesto /.well-known/x402.json oculta Solana quando flag está desligada
- **GIVEN** `GRID402_SOLANA_X402_ENABLED=false`
- **WHEN** um agente consulta `GET /.well-known/x402.json` ou `GET /.well-known/x402`
- **THEN** o JSON retornado SHALL conter HTTP `200`, `Access-Control-Allow-Origin: *`, redes EVM ativas (`eip155:8453`, `eip155:137`, `eip155:42161`) e SHALL NOT anunciar `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`

#### Scenario: Manifesto /.well-known/x402.json anuncia Solana automaticamente quando flag está ligada
- **GIVEN** `GRID402_SOLANA_X402_ENABLED=true`
- **WHEN** um agente consulta `GET /.well-known/x402.json`
- **THEN** o JSON retornado SHALL incluir `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` em `networks` e nas ofertas `accepts[]` dos recursos pagos com a carteira Solana oficial, mint SPL USDC e `feePayer`

### Requirement: Pré-Checagem CLI On-Chain de Prontidão Solana
O repositório MUST disponibilizar o script CLI `scripts/check_solana_readiness.php` para validar, antes de habilitar `GRID402_SOLANA_X402_ENABLED=true`, se a carteira Solana configurada possui saldo nativo SOL (`> 0` lamports) e conta de token associada (ATA) para o mint SPL USDC oficial usando RPCs saudáveis da Solana Mainnet.

#### Scenario: Carteira pronta com SOL e ATA USDC retorna código 0
- **GIVEN** uma resposta JSON-RPC (real ou simulada) retornando saldo SOL maior que zero e ao menos uma conta SPL Token do mint `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`
- **WHEN** o operador executa `php scripts/check_solana_readiness.php --json`
- **THEN** o script SHALL sair com código `0` e JSON contendo `"ready": true`, `"has_sol": true` e `"has_usdc_ata": true`

#### Scenario: Carteira sem SOL ou sem ATA USDC retorna código 1 com diagnóstico
- **GIVEN** uma carteira Solana com `0` lamports de SOL ou sem ATA SPL USDC criada
- **WHEN** o operador executa `php scripts/check_solana_readiness.php --json`
- **THEN** o script SHALL sair com código `1` e JSON contendo `"ready": false` detalhando exatamente o pré-requisito pendente

### Requirement: Descoberta e Validação CDP Bazaar v2 para os 28 Serviços da Fase 2
Os artefatos de descoberta pública (`/.well-known/x402.json`, `openapi.json`, `pricing.json`, `ai-agent.json`, `mcp.json`, `server-card.json`, `llms.txt` e `README.md`) SHALL anunciar os **28 serviços** do catálogo (`27` pagos e `verify`), incluindo `token-safety`, `prediction-markets` e `batch`, sem `$ref` externo nas extensões Bazaar e sem `&` nos arquivos monitorados.

#### Scenario: Manifesto /.well-known/x402.json e validador CDP Bazaar aprovam os 27 recursos pagos
- **GIVEN** os 28 serviços registrados em `public_html/config.php`
- **WHEN** um cliente consulta `GET /.well-known/x402.json` ou executa `php scripts/validate_cdp_bazaar.php`
- **THEN** o manifesto SHALL listar todos os `27` recursos pagos (incluindo `token-safety`, `prediction-markets` e `batch` com método e schemas inline válidos sem `$ref`) e o validador SHALL aprovar `27/27` endpoints com `0` falhas.

#### Scenario: Manifestos OpenAPI, Pricing, MCP e llms.txt sincronizados sem caractere ampersand
- **GIVEN** os arquivos públicos `openapi.json`, `pricing.json`, `ai-agent.json`, `llms.txt`, `llms-full.txt`, `.well-known/mcp.json`, `.well-known/mcp/server-card.json` e `README.md`
- **WHEN** a suíte `tests/test_agent_discovery.php` e `tests/test_agentkit_integration.php` validam descoberta e higiene de texto
- **THEN** todos os documentos SHALL refletir os 28 serviços, 30 ferramentas MCP (`2.1.0`), tarifas oficiais (`0.0150` e `0.0100` USDC) e SHALL NOT conter o caractere `&` nos arquivos monitorados.
