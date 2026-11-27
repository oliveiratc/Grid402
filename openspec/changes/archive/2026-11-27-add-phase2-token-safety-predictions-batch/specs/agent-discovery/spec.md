## ADDED Requirements

### Requirement: Descoberta e Validação CDP Bazaar v2 para os 28 Serviços da Fase 2
Os artefatos de descoberta pública (`/.well-known/x402.json`, `openapi.json`, `pricing.json`, `ai-agent.json`, `mcp.json`, `server-card.json`, `llms.txt` e `README.md`) SHALL anunciar os **28 serviços** do catálogo (`27` pagos e `verify`), incluindo `token-safety`, `prediction-markets` e `batch`, sem `$ref` externo nas extensões Bazaar e sem `&` nos arquivos monitorados.

#### Scenario: Manifesto /.well-known/x402.json e validador CDP Bazaar aprovam os 27 recursos pagos
- **GIVEN** os 28 serviços registrados em `public_html/config.php`
- **WHEN** um cliente consulta `GET /.well-known/x402.json` ou executa `php scripts/validate_cdp_bazaar.php`
- **THEN** o manifesto SHALL listar todos os `27` recursos pagos (incluindo `token-safety`, `prediction-markets` e `batch` com método e schemas inline válidos sem `$ref`) e o validador SHALL aprovar `27/27` endpoints com `0` falhas.

#### Scenario: Manifestos OpenAPI, Pricing, MCP e llms.txt sincronizados sem caractere ampersand
- **GIVEN** os arquivos públicos `openapi.json`, `pricing.json`, `ai-agent.json`, `llms.txt`, `llms-full.txt`, `.well-known/mcp.json`, `.well-known/mcp/server-card.json` e `README.md`
- **WHEN** a suíte `tests/test_agent_discovery.php` e `tests/test_agentkit_integration.php` validam descoberta e higiene de texto
- **THEN** todos os documentos SHALL refletir os 28 serviços, 30 ferramentas MCP (`2.1.0`), tarifas oficiais (`0.0150` e `0.0100` USDC) e SHALL NOT conter o caractere `&` nos arquivos monitorados.
