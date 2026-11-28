## ADDED Requirements

### Requirement: Descoberta e Validação CDP Bazaar v2 para os 31 Serviços da Onda 3A
Os manifestos públicos (`/.well-known/x402.json`, `openapi.json`, `pricing.json`, `ai-agent.json`, `mcp.json`, `server-card.json`, `llms.txt` e `README.md`) SHALL anunciar os 31 serviços (`30` pagos e `verify`), incluindo `sec-company`, `vuln-audit` e `chain-telemetry`, sem `$ref` no Bazaar e sem `&` nos arquivos monitorados.

#### Scenario: Manifesto /.well-known/x402.json e validador CDP Bazaar aprovam os 30 recursos pagos
- **GIVEN** os 31 serviços registrados em `public_html/config.php`
- **WHEN** um cliente consulta `GET /.well-known/x402.json` ou executa `php scripts/validate_cdp_bazaar.php`
- **THEN** o manifesto SHALL listar todos os `30` recursos pagos (incluindo `sec-company`, `vuln-audit` e `chain-telemetry` com schemas inline válidos sem `$ref` e descrição dentro de 500 caracteres) e o validador SHALL aprovar `30/30` endpoints com `0` falhas.

#### Scenario: Manifestos públicos e specs RapidAPI sincronizados sem regressão de higiene
- **GIVEN** os arquivos públicos `openapi.json`, `pricing.json`, `.well-known/ai-agent.json`, `llms.txt`, `llms-full.txt`, `.well-known/mcp.json`, `.well-known/mcp/server-card.json`, `README.md` e `docs/revenue/openapi/{rapidapi-global,rapidapi-all}.json`
- **WHEN** as suítes `tests/test_agent_discovery.php`, `tests/test_agentkit_integration.php` e `tests/test_revenue_unblock_change1.php` executam suas verificações
- **THEN** todos os manifestos SHALL refletir os 31 serviços, 33 tools MCP (`2.2.0`) e tarifas da Onda 3A (`0.0100` e `0.0050` USDC) mantendo zero ocorrências de `&` e conformidade com os testes estáticos.
