# Tarefas: Adicao de Indicadores Economicos e Feriados Nacionais e Bancarios

## 1. Backend Hub PHP (Hostinger)
- [x] 1.1 Implementar `public_html/services/indicators.php` com consulta a dados do Banco Central (Olinda / SGS), cache de 1h e suporte a fallback resiliente
- [x] 1.2 Implementar `public_html/services/holidays.php` com calculo matematico de feriados moveis (Meeus), feriados fixos e calendario bancario
- [x] 1.3 Configurar regras de tarifacao e teaser data no `public_html/x402.php` e catalogo de servicos no `public_html/config.php`
- [x] 1.4 Adicionar rotas `/api/v1/indicators` e `/api/v1/holidays` no `public_html/.htaccess` e no despachante `public_html/index.php`
- [x] 1.5 Atualizar o console interativo na homepage (`public_html/index.php`) com acoes para testar Indicadores e Feriados

## 2. Documentacao de Maquina e Metadados
- [x] 2.1 Atualizar `public_html/openapi.json` adicionando paths `/api/v1/indicators` e `/api/v1/holidays` com schemas de requisicao e resposta
- [x] 2.2 Atualizar `public_html/llms.txt`, `public_html/sitemap.xml` e `public_html/.well-known/mcp/server-card.json`
- [x] 2.3 Atualizar registro WebMCP no `public_html/index.php` para incluir as ferramentas `grid402_indicators` e `grid402_holidays`

## 3. SDK Node.js e TypeScript (v2.3.0)
- [x] 3.1 Adicionar interfaces `IndicatorsResponse` e `HolidaysResponse` nos tipos TypeScript
- [x] 3.2 Adicionar metodos `getIndicators()` e `getHolidays()` em `sdk/node/src/` e bundles CJS/ESM
- [x] 3.3 Adicionar testes unitarios em `sdk/node/tests/` e atualizar versao para 2.3.0 no `package.json`

## 4. SDK Python (v2.2.0)
- [x] 4.1 Adicionar modelos Pydantic `IndicatorsResponse` e `HolidaysResponse` em `python/src/grid402/`
- [x] 4.2 Adicionar metodos `get_indicators()` e `get_holidays()` no cliente Python
- [x] 4.3 Adicionar testes unitarios em `python/tests/` e atualizar versao para 2.2.0 no `pyproject.toml`

## 5. Servidor MCP (v1.3.0)
- [x] 5.1 Adicionar definicoes e executores das ferramentas `grid402_indicators` e `grid402_holidays` em `mcp/tools.mjs`
- [x] 5.2 Atualizar versao para 1.3.0 em `mcp/package.json` e validar via `test_mcp.mjs`

## 6. Deploy e Verificacao Ponta a Ponta
- [x] 6.1 Empacotar e realizar deploy na Hostinger via script e ferramenta `hostinger_deploy`
- [x] 6.2 Executar testes automatizados ponta a ponta contra o endpoint remoto na Hostinger
- [x] 6.3 Executar testes unitarios dos SDKs Node.js e Python

