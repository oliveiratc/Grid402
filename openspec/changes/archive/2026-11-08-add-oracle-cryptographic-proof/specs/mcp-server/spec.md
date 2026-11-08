## ADDED Requirements

### Requirement: MCP tool preservation of oracle proof
O servidor MCP (`grid402-mcp`) SHALL preservar e expor intacto o objeto `_oracle_proof` nas respostas retornadas pelas ferramentas quando a requisicao for liquidada on-chain ou simulada em modo sandbox.

#### Scenario: Preserving oracle proof in MCP tool output
- **GIVEN** uma chamada a qualquer ferramenta MCP do Grid402 liquidada com sucesso ou executada em modo sandbox
- **WHEN** o resultado JSON e despachado para o cliente MCP
- **THEN** o objeto `_oracle_proof` permanece integrado ao payload para auditoria pelo agente ou modelo de linguagem

