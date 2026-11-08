## ADDED Requirements

### Requirement: MCP tools for indicators and holidays
O servidor MCP (`grid402-mcp`) SHALL registrar e expor as ferramentas `grid402_indicators` e `grid402_holidays` com schemas JSON Schema de entrada e suporte automatico a auto-liquidacao on-chain ou Modo Sandbox.

#### Scenario: Tool execution for grid402_indicators
- **WHEN** um agente MCP (Claude Desktop ou Cursor) invoca a ferramenta `grid402_indicators`
- **THEN** o servidor executa a consulta aos indicadores economicos e retorna a resposta formatada em texto e JSON

#### Scenario: Tool execution for grid402_holidays
- **GIVEN** o argumento `year: 2026`
- **WHEN** um agente MCP invoca a ferramenta `grid402_holidays` com esse argumento
- **THEN** o servidor executa a consulta ao calendario de feriados e retorna a colecao formatada

