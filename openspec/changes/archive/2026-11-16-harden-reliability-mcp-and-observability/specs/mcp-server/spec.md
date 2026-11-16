## ADDED Requirements

### Requirement: Instrucoes Nativas no Handshake MCP
O servidor MCP (`mcp/index.mjs`) SHALL declarar o campo `instructions` na inicializacao (`ServerOptions.instructions`), orientando clientes e diretorios (Glama, Smithery, Claude Desktop, Cursor) sobre selecao de ferramentas, rotas gratuitas, Modo Sandbox (`demo`), Session Pass (`<15ms`), limites de gasto (Spend Controls) e tratamento de erros x402.

#### Scenario: Inspecao das instrucoes e versao sincronizada no servidor MCP
- **GIVEN** o codigo-fonte do servidor MCP em `mcp/index.mjs` e seus metadados em `mcp/package.json`
- **WHEN** o servidor `grid402-mcp-server` e inicializado via stdio
- **THEN** o objeto de configuracao passado ao `Server` SHALL conter `instructions` nao vazio documentando `demo`, Session Pass e `/api/v1/verify`
- **AND** a versao declarada em `mcp/index.mjs`, `mcp/package.json` e `package.json` raiz SHALL estar sincronizada (`1.8.2`).

## MODIFIED Requirements

### Requirement: Liquidação Automática com Chave Privada
Quando uma chave privada for informada na variável de ambiente `GRID402_PRIVATE_KEY` e a rota exigir pagamento x402, o servidor SHALL transferir entre 0.0050 e 0.0300 USDC na Base Mainnet (conforme tabela oficial do endpoint), obter a prova/autorização de liquidação e refazer a consulta com o cabeçalho de pagamento adequado, além de priorizar `GRID402_SESSION_PASS` quando configurado.

#### Scenario: Consulta detalhada com chave configurada
- **GIVEN** a variável `GRID402_PRIVATE_KEY` configurada com uma carteira contendo saldo de USDC e ETH na Base
- **WHEN** o cliente invoca `grid402_dns` com `domain: "github.com"`
- **THEN** o servidor SHALL liquidar 0.0050 USDC para a carteira recebedora oficial do Grid402 (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`) e retornar o payload completo de registros DNS com HTTP 200.

### Requirement: Inicialização do Protocolo MCP via Stdio
O servidor MCP SHALL responder a requisições JSON-RPC de inicialização e listar todas as ferramentas disponíveis (24 tools) via transporte `stdio`.

#### Scenario: Listagem de ferramentas disponíveis
- **GIVEN** o servidor MCP em execução via `node index.mjs`
- **WHEN** o cliente MCP envia uma requisição `tools/list`
- **THEN** o servidor SHALL retornar todas as 24 ferramentas registradas em `mcp/tools.mjs` com seus respectivos schemas, incluindo os bundles (`grid402_domain_audit`, `grid402_kyb_audit`, `grid402_market_snapshot`), endpoints globais e `grid402_pass_balance`.
