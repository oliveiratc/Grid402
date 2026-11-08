# Adicionar Descoberta Autonoma para Agentes de IA e Indexacao em Marketplaces

## Por que
O Grid402 oferece microservicos de dados publicos brasileiros com oraculo criptografico e pagamento x402 via HTTP 402, porem agentes autonomos e diretorios de IA (Agentic Market, PayAPI, Smithery, Claude Desktop) necessitam de manifestos padronizados de descoberta (llms.txt, .well-known/mcp.json, ai-plugin.json) e documentacao amigavel para consumir e indexar os servicos automaticamente (conforme PRD em `openspec/prds/grid402-ai-agent-discovery-and-marketplaces.md`).

## O que muda
- Adicao de `public_html/llms.txt` e `public_html/llms-full.txt` no padrao de contexto para LLMs.
- Adicao de manifestos legiveis por maquina em `public_html/.well-known/mcp.json` e `public_html/.well-known/ai-plugin.json`.
- Criacao da rota publica `/agents` (`public_html/agents.php`) com documentacao interativa, snippets de codigo para Claude Desktop, Python e cURL.
- Ajuste de regras no `.htaccess` para rotear `/agents` e servir manifestos com cabecalhos CORS irrestritos (`Access-Control-Allow-Origin: *`).
- Criacao do guia de submissao `docs/AGENT_MARKETPLACE_SUBMISSIONS.md` com fichas preenchidas para Agentic Market, PayAPI, Smithery e Glama.
- Suite de testes automatizados PHP para validar status HTTP 200, cabecalhos CORS e sintaxe dos manifestos.

## Impacto
- Specs afetadas: `agent-discovery`
- Codigo afetado:
  - `public_html/llms.txt` (novo)
  - `public_html/llms-full.txt` (novo)
  - `public_html/.well-known/mcp.json` (novo)
  - `public_html/.well-known/ai-plugin.json` (novo)
  - `public_html/agents.php` (novo)
  - `public_html/.htaccess` (modificado para rota /agents)
  - `public_html/index.php` (links de navegacao no header e footer)
  - `docs/AGENT_MARKETPLACE_SUBMISSIONS.md` (novo)
  - `tests/test_agent_discovery.php` (novo)

