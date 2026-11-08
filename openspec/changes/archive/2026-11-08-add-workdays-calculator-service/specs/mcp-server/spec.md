## ADDED Requirements

### Requirement: MCP tools for workdays calculator
O servidor MCP (`grid402-mcp`) SHALL registrar e expor a ferramenta `grid402_workdays` com schema JSON Schema de entrada e suporte automatico a auto-liquidacao on-chain ou Modo Sandbox.

#### Scenario: Tool execution for grid402_workdays
- **GIVEN** os argumentos de contagem de dias uteis (`start_date: "2026-12-23"`, `end_date: "2026-12-28"`)
- **WHEN** um agente MCP (Claude Desktop ou Cursor) invoca a ferramenta `grid402_workdays`
- **THEN** o servidor executa a consulta da calculadora e retorna a resposta formatada em texto e JSON

