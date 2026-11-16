## ADDED Requirements

### Requirement: Dual-License Explicita e Higiene de Dominio e Precos Legados
O repositório SHALL explicitar em `LICENSE` e `README.md` a separação Dual-License entre o gateway proprietário (`public_html/`) e os SDKs, MCP Server e integrações sob licença MIT, garantindo o posicionamento *"The Verifiable Data and x402 Payment Layer for Autonomous AI Agents"* e zero referências ativas a `grid402.com.br` ou `0.0005`.

#### Scenario: Verificacao de Dual-License e ausencia de referencias legadas
- **GIVEN** os arquivos `LICENSE`, `README.md`, `.env.example`, `examples/*` e `sdk/*` do repositório
- **WHEN** um auditor ou suite automatizada inspecionar licenças, URLs e tarifas padrão
- **THEN** `LICENSE` e `README.md` SHALL explicitar a divisão entre servidor proprietário (`public_html/`) e SDKs/MCP em `MIT`
- **AND** nenhuma referência ativa em `examples/` ou `sdk/` SHALL apontar para `grid402.com.br` ou usar `0.0005` como preço vigente.

## MODIFIED Requirements

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
