# mcp-server Specification

## Purpose
Prover o servidor MCP oficial (`grid402-mcp`) via transporte stdio para conectar agentes de IA (Cursor, Claude Desktop, Antigravity) aos utilitarios de dados publicos com auto-liquidacao x402.

## Requirements

### Requirement: Inicialização do Protocolo MCP via Stdio
O servidor MCP SHALL responder a requisições JSON-RPC de inicialização e listar todas as ferramentas disponíveis (24 tools) via transporte `stdio`.

#### Scenario: Listagem de ferramentas disponíveis
- **GIVEN** o servidor MCP em execução via `node index.mjs`
- **WHEN** o cliente MCP envia uma requisição `tools/list`
- **THEN** o servidor SHALL retornar todas as 24 ferramentas registradas em `mcp/tools.mjs` com seus respectivos schemas, incluindo os bundles (`grid402_domain_audit`, `grid402_kyb_audit`, `grid402_market_snapshot`), endpoints globais e `grid402_pass_balance`.

### Requirement: Execução de Consulta Gratuita
O servidor MCP SHALL executar consultas gratuitas (`grid402_summary`, `grid402_verify` e `grid402_pass_balance`) sem exigir chave privada de carteira.

#### Scenario: Execução da ferramenta summary
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_summary`
- THEN o servidor SHALL retornar status 200 OK com o resumo consolidado de saúde dos 22 serviços do catálogo.

### Requirement: Liquidação Automática com Chave Privada
Quando uma chave privada for informada na variável de ambiente `GRID402_PRIVATE_KEY` e a rota exigir pagamento x402, o servidor SHALL transferir entre 0.0050 e 0.0300 USDC na Base Mainnet (conforme tabela oficial do endpoint), obter a prova/autorização de liquidação e refazer a consulta com o cabeçalho de pagamento adequado, além de priorizar `GRID402_SESSION_PASS` quando configurado.

#### Scenario: Consulta detalhada com chave configurada
- **GIVEN** a variável `GRID402_PRIVATE_KEY` configurada com uma carteira contendo saldo de USDC e ETH na Base
- **WHEN** o cliente invoca `grid402_dns` com `domain: "github.com"`
- **THEN** o servidor SHALL liquidar 0.0050 USDC para a carteira recebedora oficial do Grid402 (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`) e retornar o payload completo de registros DNS com HTTP 200.

### Requirement: Fallback Estruturado sem Chave Privada
Quando nenhuma chave privada for informada e a consulta exigir pagamento, o servidor SHALL retornar um payload amigável informando o desafio `PaymentRequired`.

#### Scenario: Consulta detalhada sem chave configurada
- GIVEN a variável `GRID402_PRIVATE_KEY` não definida
- WHEN o cliente invoca `grid402_ssl` com `host: "base.org"` sem fornecer `payment_tx_hash`
- THEN o servidor SHALL retornar o desafio 402 com as instruções de liquidação e o endereço da carteira destino.

### Requirement: Execução da Ferramenta de CNPJ no MCP
O servidor MCP SHALL permitir a consulta de dados cadastrais de Pessoa Jurídica via ferramenta `grid402_cnpj`.

#### Scenario: Consulta de CNPJ com liquidação automática de 0.0020 USDC
- **GIVEN** a variável `GRID402_PRIVATE_KEY` configurada com saldo suficiente de USDC na Base
- **WHEN** o cliente invoca `grid402_cnpj` com parâmetro `cnpj: "00000000000191"`
- **THEN** o servidor SHALL transferir 0.0020 USDC on-chain na Base para a carteira do oráculo e retornar os dados cadastrais da empresa com status HTTP 200.

#### Scenario: Consulta de CNPJ sem chave privada retorna desafio x402 de 0.0020 USDC
- **GIVEN** nenhuma chave privada configurada
- **WHEN** o cliente invoca `grid402_cnpj` com parâmetro `cnpj: "00000000000191"`
- **THEN** o servidor SHALL retornar o desafio 402 com instrução de pagamento de 0.0020 USDC e carteira de destino.

### Requirement: Registro e Configuração Smithery.ai
O repositório SHALL incluir o arquivo de manifesto `smithery.yaml` permitindo a descoberta e instalação do servidor MCP com suporte a configuração de chave privada e modo sandbox.

#### Scenario: Instalação automatizada via CLI Smithery
- **WHEN** um usuário executa `npx -y @smithery/cli install grid402-mcp`
- **THEN** o Smithery inicializa o servidor MCP via `npx -y grid402-mcp` passando os parâmetros de configuração especificados no schema

### Requirement: Provedor de Ações para Coinbase AgentKit
O repositório SHALL disponibilizar provedor de ações (`Grid402ActionProvider`) compatível com o ecossistema Coinbase AgentKit nas linguagens TypeScript e Python, permitindo auto-liquidação de consultas na Base.

#### Scenario: Execução de ação com carteira CDP na Base
- **GIVEN** um agente autônomo configurado com o CDP Wallet Provider e saldo em USDC na Base
- **WHEN** o agente invoca uma ação do Grid402 (CNPJ, CEP, Forex, Crypto, DNS, SSL ou ASN)
- **THEN** o Action Provider liquida a tarifa de 0.0005 ou 0.0020 USDC via CDP Wallet Provider na Base e entrega os dados enriquecidos com HTTP 200

### Requirement: Publicação e Resolução Global do Pacote npm v1.2.0
O pacote `grid402-mcp` SHALL ser distribuído no registro oficial do npm na versão `1.2.0`, empacotando os recursos de CNPJ e Modo Sandbox para download público.

#### Scenario: Consulta de metadados da versão no registro npm
- **GIVEN** o pacote `grid402-mcp` publicado no npm
- **WHEN** um cliente consulta o registro via `npm view grid402-mcp version`
- **THEN** o registro SHALL retornar exatamente a versão `1.2.0`.

#### Scenario: Inicialização remota com suporte a Modo Sandbox
- **GIVEN** o pacote instalado via `npx -y grid402-mcp@latest` com a variável `GRID402_SANDBOX=true`
- **WHEN** o cliente invoca a ferramenta `grid402_cnpj` com parâmetro `cnpj: "00000000000191"`
- **THEN** o servidor SHALL executar a chamada utilizando o token de simulação e retornar os dados completos sem exigir saldo em carteira.

### Requirement: MCP tools for indicators and holidays
O servidor MCP (`grid402-mcp`) SHALL registrar e expor as ferramentas `grid402_indicators` e `grid402_holidays` com schemas JSON Schema de entrada e suporte automatico a auto-liquidacao on-chain ou Modo Sandbox.

#### Scenario: Tool execution for grid402_indicators
- **WHEN** um agente MCP (Claude Desktop ou Cursor) invoca a ferramenta `grid402_indicators`
- **THEN** o servidor executa a consulta aos indicadores economicos e retorna a resposta formatada em texto e JSON

#### Scenario: Tool execution for grid402_holidays
- **GIVEN** o argumento `year: 2026`
- **WHEN** um agente MCP invoca a ferramenta `grid402_holidays` com esse argumento
- **THEN** o servidor executa a consulta ao calendario de feriados e retorna a colecao formatada

### Requirement: MCP tools for workdays calculator
O servidor MCP (`grid402-mcp`) SHALL registrar e expor a ferramenta `grid402_workdays` com schema JSON Schema de entrada e suporte automatico a auto-liquidacao on-chain ou Modo Sandbox.

#### Scenario: Tool execution for grid402_workdays
- **GIVEN** os argumentos de contagem de dias uteis (`start_date: "2026-12-23"`, `end_date: "2026-12-28"`)
- **WHEN** um agente MCP (Claude Desktop ou Cursor) invoca a ferramenta `grid402_workdays`
- **THEN** o servidor executa a consulta da calculadora e retorna a resposta formatada em texto e JSON

### Requirement: MCP tool preservation of oracle proof
O servidor MCP (`grid402-mcp`) SHALL preservar e expor intacto o objeto `_oracle_proof` nas respostas retornadas pelas ferramentas quando a requisicao for liquidada on-chain ou simulada em modo sandbox.

#### Scenario: Preserving oracle proof in MCP tool output
- **GIVEN** uma chamada a qualquer ferramenta MCP do Grid402 liquidada com sucesso ou executada em modo sandbox
- **WHEN** o resultado JSON e despachado para o cliente MCP
- **THEN** o objeto `_oracle_proof` permanece integrado ao payload para auditoria pelo agente ou modelo de linguagem

### Requirement: Ferramenta MCP de Verificacao de Oraculo
O servidor MCP (`grid402-mcp`) SHALL registrar e expor a ferramenta `grid402_verify` para validacao sem estado de assinaturas criptograficas secp256k1 geradas pelo oraculo do Grid402.

#### Scenario: Execucao da ferramenta grid402_verify com sucesso
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_verify` passando um payload JSON contendo a chave `_oracle_proof`
- THEN o servidor SHALL executar a verificacao e retornar `valid: true` acompanhado do hash dos dados e do endereco do oraculo.

#### Scenario: Execucao da ferramenta grid402_verify com payload invalido
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_verify` passando um payload sem a chave `_oracle_proof` ou com dados adulterados
- THEN o servidor SHALL retornar o resultado da verificacao com `valid: false` e detalhes da rejeicao.

### Requirement: Atualizacao do Pacote MCP para Versao 1.6.0
O pacote `grid402-mcp` SHALL ser atualizado para a versao `1.6.0` empacotando as 12 ferramentas do hub de dados e utilitarios de rede.

#### Scenario: Verificacao da lista de ferramentas na versao 1.6.0
- GIVEN o servidor MCP em execucao via `node index.mjs`
- WHEN o cliente envia uma requisicao `tools/list`
- THEN o servidor SHALL retornar 12 ferramentas registradas, incluindo `grid402_indicators`, `grid402_holidays`, `grid402_workdays` e `grid402_verify`.

### Requirement: Ferramentas MCP para Catálogo Completo de Compliance, Mercados e Governança M2M
O servidor MCP (`grid402-mcp`) SHALL expor definições formais de ferramentas em `mcp/tools.mjs` cobrindo todo o catálogo do Grid402 (incluindo `grid402_simples`, `grid402_sanctions`, `grid402_compliance`, `grid402_kyb`, `grid402_lei`, `grid402_ofac`, `grid402_domain_audit`, `grid402_global_markets`, `grid402_market_snapshot`, `grid402_notarize` e `grid402_pass_balance`), com descrições fidedignas e preços alinhados (`0.0050 a 0.0300 USDC`).

#### Scenario: Listagem completa de ferramentas no servidor MCP
- **GIVEN** o servidor MCP inicializado via `node mcp/index.mjs`
- **WHEN** um cliente MCP envia `tools/list`
- **THEN** o servidor SHALL retornar todas as ferramentas correspondentes aos serviços do catálogo sem descrições truncadas ou trocadas (ex.: `grid402_ssl` descrita exclusivamente como inspeção TLS/SSL e `grid402_cep` como consulta postal).

### Requirement: Instrucoes Nativas no Handshake MCP
O servidor MCP (`mcp/index.mjs`) SHALL declarar o campo `instructions` na inicializacao (`ServerOptions.instructions`), orientando clientes e diretorios (Glama, Smithery, Claude Desktop, Cursor) sobre selecao de ferramentas, rotas gratuitas, Modo Sandbox (`demo`), Session Pass (`<15ms`), limites de gasto (Spend Controls) e tratamento de erros x402.

#### Scenario: Inspecao das instrucoes e versao sincronizada no servidor MCP
- **GIVEN** o codigo-fonte do servidor MCP em `mcp/index.mjs` e seus metadados em `mcp/package.json`
- **WHEN** o servidor `grid402-mcp-server` e inicializado via stdio
- **THEN** o objeto de configuracao passado ao `Server` SHALL conter `instructions` nao vazio documentando `demo`, Session Pass e `/api/v1/verify`
- **AND** a versao declarada em `mcp/index.mjs`, `mcp/package.json` e `package.json` raiz SHALL estar sincronizada (`1.9.0`).

### Requirement: Ferramenta MCP `grid402_get_dossier_360`
O servidor MCP oficial (`grid402-mcp`) MUST expor a ferramenta `grid402_get_dossier_360` com schema JSON exigindo o parâmetro `cnpj` (string) e roteando a chamada para `GET /api/v1/dossier?cnpj={cnpj}`.

#### Scenario: Listagem de ferramentas MCP inclui `grid402_get_dossier_360`
- GIVEN um cliente MCP (Claude Desktop, Cursor ou Glama) conectado ao `grid402-mcp`
- WHEN solicita `tools/list`
- THEN a lista contém `grid402_get_dossier_360` com descrição indicando o custo de `0.0500 USDC` (10 créditos) e propriedade obrigatória `cnpj`.

#### Scenario: Invocação da ferramenta `grid402_get_dossier_360` consulta o endpoint `/api/v1/dossier`
- GIVEN um agente invocando `tools/call` com nome `grid402_get_dossier_360` e argumento `{"cnpj": "00000000000191"}`
- WHEN o servidor MCP executa a requisição
- THEN o servidor aciona `/api/v1/dossier?cnpj=00000000000191` usando a estratégia de pagamento configurada (Sandbox, Session Pass ou x402 auto-settlement) e retorna o JSON do dossiê com o bloco `verdict`.
