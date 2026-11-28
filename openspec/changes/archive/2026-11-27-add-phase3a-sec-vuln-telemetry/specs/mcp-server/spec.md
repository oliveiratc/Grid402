## ADDED Requirements

### Requirement: Ferramentas MCP da Onda 3A (`sec-company`, `vuln-audit` e `chain-telemetry` — 33 tools v2.2.0)
Os servidores MCP (`mcp/index.mjs`, `mcp/tools.mjs` e `public_html/mcp.php`) SHALL expor na versão `2.2.0` exatamente **33 ferramentas** sincronizadas (`grid402_summary`, os 31 serviços do catálogo incluindo `grid402_sec_company`, `grid402_vuln_audit` e `grid402_chain_telemetry`, mais `grid402_pass_balance` — mantendo paridade nos 30 tools anteriores + 3 novos da Onda 3A), preservando `_meta` e `_oracle_proof` e auto-liquidação x402/Session Pass.

#### Scenario: Handshake e listagem de exatamente 33 tools sincronizadas na versão 2.2.0
- **GIVEN** o servidor MCP `grid402-mcp` (stdio em `mcp/index.mjs` e HTTP em `/mcp`) atualizado para `2.2.0`
- **WHEN** um cliente envia `initialize` e `tools/list`
- **THEN** o servidor SHALL retornar `version = "2.2.0"` e exatamente `33` ferramentas incluindo `grid402_sec_company`, `grid402_vuln_audit` e `grid402_chain_telemetry` com schemas JSON válidos.

#### Scenario: Invocação das novas ferramentas MCP preserva _meta e _oracle_proof ou formata 402
- **GIVEN** um cliente MCP invocando `grid402_sec_company`, `grid402_vuln_audit` ou `grid402_chain_telemetry`
- **WHEN** a ferramenta consulta o gateway Grid402 (em modo sandbox/pago ou sem credencial)
- **THEN** o servidor MCP SHALL retornar o payload JSON preservando `_meta` e `_oracle_proof` em caso de HTTP `200`, ou desafio `402` formatado caso não haja meio de pagamento configurado.
