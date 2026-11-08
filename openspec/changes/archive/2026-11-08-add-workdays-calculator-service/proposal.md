# Proposal: Calculadora de Dias Uteis Financeiros ANBIMA

## Why

Agentes de inteligencia artificial, robos financeiros e desenvolvedores necessitam calcular prazos de liquidacao D+1 e D+2, vencimentos de titulos e contagem de dias uteis bancarios no Brasil sem manter calendarios locais ou bancos de dados pesados. A adicao do servico de dias uteis (`/api/v1/workdays`) sob o protocolo HTTP 402 viabiliza consultas stateless de altissima precisao temporal a baixo custo (0.0005 USDC) com amostra gratuita (teaser) no desafio 402.

## What Changes

- Disponibilizacao do novo endpoint `/api/v1/workdays` com suporte a 3 modos de operacao: contagem de intervalo (padrao ANBIMA D+0 exclusivo no inicio e inclusivo no fim), projecao de vencimento (adicao ou subtracao de N dias uteis) e checagem pontual de dia util.
- Integracao do desafio HTTP 402 com emissao de teaser gratuito contendo o status de dia util da data base e o proximo feriado bancario.
- Suporte nativo ao Modo Sandbox gratuito via cabecalho `X-Payment: demo`.
- Adicao do metodo `getWorkdays()` no SDK Node.js e TypeScript (`grid402`).
- Adicao do metodo `get_workdays()` e modelos Pydantic v2 `WorkdaysInput` e `WorkdaysResponse` no SDK Python (`grid402`).
- Registro da ferramenta `grid402_workdays` no servidor MCP (`grid402-mcp`).
- Atualizacao das documentacoes de maquina: `openapi.json`, `llms.txt`, `sitemap.xml`, console web e WebMCP.

## Capabilities

### New Capabilities

### Modified Capabilities

- `open-data-utilities`: Adicao dos requisitos do servico de calculadora de dias uteis financeiros ANBIMA e emissao de teaser gratuito no endpoint `/api/v1/workdays`.
- `node-sdk`: Adicao do metodo `getWorkdays()` com tipagem TypeScript estrita e suporte a auto-liquidacao x402 e Sandbox.
- `python-sdk`: Adicao do metodo `get_workdays()` com validacao Pydantic v2 e suporte a auto-liquidacao x402 e Sandbox.
- `mcp-server`: Registro e disponibilizacao da ferramenta `grid402_workdays` via stdio para Claude Desktop, Cursor e orquestradores de IA.

## Impact

- Backend PHP: Novo arquivo `public_html/services/workdays.php` e integracao do roteamento em `public_html/index.php`.
- Documentacao: `public_html/openapi.json`, `public_html/llms.txt`, `public_html/sitemap.xml`.
- SDK Node.js: `sdk/node/grid402.mjs`, `grid402.cjs`, `grid402.d.ts`, `package.json` (bump para 2.4.0).
- SDK Python: `python/grid402/models.py`, `client.py`, `agentkit.py`, `pyproject.toml` (bump para 2.3.0).
- MCP Server: `mcp/tools.mjs`, `package.json` (bump para 1.4.0).
- Testes: Novos testes em PHP, Node.js, Python e MCP.

