## ADDED Requirements

### Requirement: Ferramentas MCP para Catálogo Completo de Compliance, Mercados e Governança M2M
O servidor MCP (`grid402-mcp`) SHALL expor definições formais de ferramentas em `mcp/tools.mjs` cobrindo todo o catálogo do Grid402 (incluindo `grid402_simples`, `grid402_sanctions`, `grid402_compliance`, `grid402_kyb`, `grid402_lei`, `grid402_ofac`, `grid402_domain_audit`, `grid402_global_markets`, `grid402_market_snapshot`, `grid402_notarize` e `grid402_pass_balance`), com descrições fidedignas e preços alinhados (`0.0050 a 0.0300 USDC`).

#### Scenario: Listagem completa de ferramentas no servidor MCP
- **GIVEN** o servidor MCP inicializado via `node mcp/index.mjs`
- **WHEN** um cliente MCP envia `tools/list`
- **THEN** o servidor SHALL retornar todas as ferramentas correspondentes aos serviços do catálogo sem descrições truncadas ou trocadas (ex.: `grid402_ssl` descrita exclusivamente como inspeção TLS/SSL e `grid402_cep` como consulta postal).

## MODIFIED Requirements

### Requirement: Execução de Consulta Gratuita
O servidor MCP SHALL executar consultas gratuitas (`grid402_summary`, `grid402_verify` e `grid402_pass_balance`) sem exigir chave privada de carteira.

#### Scenario: Execução da ferramenta summary
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_summary`
- THEN o servidor SHALL retornar status 200 OK com o resumo consolidado de saúde dos 22 serviços do catálogo.
