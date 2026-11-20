## ADDED Requirements

### Requirement: Ferramentas MCP `grid402_extract` e `grid402_email_verify` e Paridade de 27 Tools
O servidor MCP oficial (`grid402-mcp` stdio em `mcp/tools.mjs` / `mcp/index.mjs` e hosted HTTP em `public_html/mcp.php`), bem como os manifestos `/.well-known/mcp.json` e `/.well-known/mcp/server-card.json`, MUST expor exatamente **27 ferramentas** sincronizadas na versão `2.0.0`, adicionando `grid402_extract` (`GET /api/v1/extract`) e `grid402_email_verify` (`GET /api/v1/email-verify`).

#### Scenario: Listagem de ferramentas MCP retorna 27 tools na versão 2.0.0
- **GIVEN** o servidor MCP inicializado via `node mcp/index.mjs` ou `POST /mcp`
- **WHEN** um cliente MCP solicita `initialize` e `tools/list`
- **THEN** o handshake retorna versão `2.0.0` e `tools/list` contém exatamente 27 ferramentas, incluindo `grid402_extract` (com parâmetro obrigatório `url`) e `grid402_email_verify` (com parâmetro obrigatório `email`)

#### Scenario: Manifestos públicos well-known sincronizados com 27 tools
- **GIVEN** os arquivos `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json`
- **WHEN** consultados via HTTP GET
- **THEN** ambos respondem HTTP 200 com `version: "2.0.0"` e exatamente 27 ferramentas registradas sem conter o caractere `&`
