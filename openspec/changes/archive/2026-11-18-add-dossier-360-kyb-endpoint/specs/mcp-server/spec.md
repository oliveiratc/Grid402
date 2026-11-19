## ADDED Requirements

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
