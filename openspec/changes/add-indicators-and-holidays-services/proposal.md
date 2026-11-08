# Proposta: Adicao de Indicadores Economicos e Feriados Nacionais e Bancarios ao Grid402

## Por que

Agentes autonomos de IA, tesourarias digitais e sistemas M2M necessitam de informacoes financeiras e de calendario oficiais do Brasil (Taxa Selic, CDI, IPCA e dias de expediente bancario) para liquidacoes automaticas, calculos de desconto e agendamento de contratos. Atualmente, essas consultas exigem scrapers instaveis ou assinaturas proprietarias custosas.

Esta mudanca expande o Grid402 adicionando dois novos micro-servicos de alta performance sob o protocolo x402, com amostra gratuita no status HTTP 402, integracao com o Modo Sandbox e suporte nativo em todos os clientes oficiais do ecossistema.

Referencia: [`openspec/prds/grid402-indicators-and-holidays-services.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-indicators-and-holidays-services.md).

## O que muda

- **Backend Hub PHP:**
  - Novo endpoint `/api/v1/indicators` com taxas Selic meta, Selic efetiva diaria, CDI e IPCA do Banco Central.
  - Novo endpoint `/api/v1/holidays` com calendario de feriados nacionais e bancarios com parametro opcional `?year=AAAA`.
  - Amostras gratuitas (Teaser Data) no status HTTP 402 revelando Selic meta no `indicators` e proximo feriado no `holidays`.
  - Cache local transiente resiliente (1h para indicators e 24h para holidays) operando 100% Zero-DB.
  - Rotas amigaveis configuradas no `.htaccess`.
  - Catalogo expandido no `config.php` e documentacoes de maquina (`openapi.json`, `llms.txt`, `sitemap.xml`, `server-card.json`).
- **SDK Node.js / TypeScript (v2.3.0):**
  - Novos metodos tipados `client.getIndicators()` e `client.getHolidays(year?)`.
  - Suporte hibrido ESM e CommonJS com tipos TypeScript completos.
- **SDK Python (v2.2.0):**
  - Novos metodos `client.get_indicators()` e `client.get_holidays(year=None)`.
  - Modelos Pydantic v2 `IndicatorsResponse` e `HolidaysResponse`.
- **Servidor MCP (v1.3.0):**
  - Novas ferramentas `grid402_indicators` e `grid402_holidays` prontas para Claude Desktop, Cursor e Smithery.

## Impacto

- **Specs afetadas:**
  - `open-data-utilities`
  - `node-sdk`
  - `python-sdk`
  - `mcp-server`
- **Codigo afetado:**
  - `public_html/services/indicators.php` (novo)
  - `public_html/services/holidays.php` (novo)
  - `public_html/config.php`
  - `public_html/x402.php`
  - `public_html/index.php`
  - `public_html/.htaccess`
  - `public_html/openapi.json`
  - `public_html/llms.txt`
  - `public_html/sitemap.xml`
  - `public_html/.well-known/mcp/server-card.json`
  - `sdk/node/src/` e `sdk/node/package.json`
  - `python/src/grid402/` e `python/pyproject.toml`
  - `mcp/index.mjs`, `mcp/tools.mjs` e `mcp/package.json`

