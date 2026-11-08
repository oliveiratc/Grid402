# Sincronizacao de SDKs e Coinbase AgentKit (v2.5.0)

## Por que
O catalogo de microservicos do Grid402 em producao conta com 12 servicos (incluindo Indicadores Selic e CDI, Feriados ANBIMA, Dias Uteis e Verificador de Oraculo Criptografico), porem o servidor MCP oficial (`grid402-mcp`), os Action Providers do Coinbase AgentKit (em TypeScript e Python) e a versao do Python SDK necessitam de sincronizacao de paridade e atualizacao para a versao 2.5.0 (conforme PRD em `openspec/prds/grid402-sdk-and-agentkit-v2-5-sync.md`).

## O que muda
- Adicao da 12ª ferramenta `grid402_verify` em `mcp/tools.mjs` com schema de validacao e teste unitario.
- Atualizacao do `mcp/package.json` para versao `1.6.0` e documentacao em `mcp/README.md`.
- Atualizacao dos provedores Coinbase AgentKit em `integrations/agentkit/grid402_action_provider.ts` e `.py` com as acoes de indicadores, feriados, dias uteis e verificacao de oraculo.
- Atualizacao da URL padrao dos clientes para o endpoint de producao da Hostinger.
- Atualizacao da versao do Python SDK para `2.5.0` em `python/pyproject.toml` e `python/grid402/__init__.py`.
- Compilacao dos novos artefatos de distribuicao local em `python/dist/` (wheel e sdist).
- Execucao e validacao de testes em Python, Node.js e MCP.

## Impacto
- Specs afetadas: `mcp-server`, `python-sdk`
- Codigo afetado:
  - `mcp/tools.mjs` (adicao da ferramenta grid402_verify)
  - `mcp/package.json` (bump para 1.6.0)
  - `mcp/test_mcp.mjs` (teste da ferramenta verify)
  - `mcp/README.md` (documentacao da ferramenta verify)
  - `integrations/agentkit/grid402_action_provider.ts` (novas acoes e endpoint)
  - `integrations/agentkit/grid402_action_provider.py` (novas acoes e endpoint)
  - `integrations/agentkit/README.md` (documentacao das novas acoes)
  - `python/pyproject.toml` (bump para 2.5.0)
  - `python/grid402/__init__.py` (bump __version__ para 2.5.0)
  - `python/dist/` (geracao de novos pacotes locais)

