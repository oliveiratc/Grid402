# Spec Delta

## ADDED Requirements

### Requirement: MCP tool execution shall respect sandbox mode and schema boundary
O servidor MCP SHALL validar argumentos estritamente contra o esquema de entrada e SHALL bloquear auto-liquidação on-chain com fundos reais quando o modo sandbox estiver ativado.

#### Scenario: Chamada de ferramenta com parametros nao declarados e sandbox ativo
- **WHEN** uma ferramenta MCP é invocada com argumentos extras ou `GRID402_SANDBOX=true`
- **THEN** o servidor ignora propriedades não declaradas e não dispara liquidação em mainnet
