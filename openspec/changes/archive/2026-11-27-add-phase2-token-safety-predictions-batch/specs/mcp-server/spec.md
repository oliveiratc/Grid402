## ADDED Requirements

### Requirement: Ferramentas MCP da Fase 2 (token-safety, prediction-markets e batch — 30 tools v2.1.0)
Os servidores MCP (`mcp/index.mjs`, `mcp/tools.mjs` e `public_html/mcp.php`) SHALL expor na versão `2.1.0` exatamente **30 ferramentas** sincronizadas (`grid402_summary`, os 28 serviços de dados/utilidades incluindo `grid402_token_safety`, `grid402_prediction_markets` e `grid402_batch`, mais `grid402_pass_balance` — contabilizando as 27 ferramentas anteriores + 3 novas ferramentas da Fase 2), preservando `_meta` e `_oracle_proof` e auto-liquidação x402/Session Pass.

#### Scenario: Handshake e listagem de exatamente 30 tools sincronizadas
- **GIVEN** o servidor MCP `grid402-mcp` (stdio e HTTP `/mcp`) atualizado para a versão `2.1.0`
- **WHEN** um cliente envia `initialize` e `tools/list`
- **THEN** o servidor SHALL retornar `version = "2.1.0"` e exatamente `30` ferramentas incluindo `grid402_token_safety`, `grid402_prediction_markets` e `grid402_batch` com schemas JSON válidos.

#### Scenario: Invocação das novas ferramentas MCP em modo sandbox ou pago preserva _oracle_proof
- **GIVEN** um cliente MCP invocando `grid402_token_safety`, `grid402_prediction_markets` ou `grid402_batch`
- **WHEN** a ferramenta executa a chamada contra o gateway Grid402 (via HTTP GET ou `POST /api/v1/batch`)
- **THEN** o servidor MCP SHALL retornar o payload estruturado preservando `_meta` e `_oracle_proof`, ou desafio `402` formatado caso não haja meio de pagamento configurado.
