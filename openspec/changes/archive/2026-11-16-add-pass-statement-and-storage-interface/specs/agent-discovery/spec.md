## ADDED Requirements

### Requirement: Descoberta da Rota Gratuita /api/v1/pass/statement nos Manifestos Públicos
Os documentos públicos de descoberta (`public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt` e `/pass`) SHALL documentar a rota gratuita `GET /api/v1/pass/statement` (`format=json|csv`, `endpoint`, `outcome`, retenção máxima de `200` eventos recentes por passe) mantendo conformidade estrita sem o caractere `&` nos arquivos `llms*.txt` e manifestos M2M.

#### Scenario: Presença da rota /api/v1/pass/statement no openapi.json e llms.txt sem ampersand
- **GIVEN** os arquivos públicos `public_html/openapi.json`, `public_html/llms.txt` e `public_html/llms-full.txt`
- **WHEN** auditados por clientes M2M ou pela suíte `tests/test_agent_discovery.php`
- **THEN** `openapi.json` SHALL descrever o path `/api/v1/pass/statement` com parâmetros `token`, `format`, `endpoint` e `outcome`, e `llms*.txt` SHALL permanecer livre do caractere `&`.
