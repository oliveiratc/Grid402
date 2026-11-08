# Tasks: Calculadora de Dias Uteis Financeiros ANBIMA

## 1. Backend PHP: Servico de Calculadora de Dias Uteis

- [x] 1.1 Criar `public_html/services/workdays.php` com logica de calculo ANBIMA (intervalo, projecao e checagem pontual) e emissao de teaser 402
- [x] 1.2 Atualizar catalogo de servicos e taxas em `public_html/config.php`
- [x] 1.3 Integrar rota no despachante `public_html/index.php` e `.htaccess`
- [x] 1.4 Criar teste automatizado unitario em PHP para o calculo de dias uteis e teaser 402 e verificar execucao

## 2. Documentacao de Maquina e Console Web

- [x] 2.1 Atualizar `public_html/openapi.json` com schemas `WorkdaysResponse` e rota `/api/v1/workdays`
- [x] 2.2 Atualizar `public_html/llms.txt`, `public_html/sitemap.xml` e WebMCP em `public_html/index.php`
- [x] 2.3 Validar ausencia de ampersands em todas as documentacoes

## 3. SDK Node.js e TypeScript

- [x] 3.1 Atualizar `sdk/node/grid402.d.ts` com interfaces `WorkdaysParams` e `WorkdaysResponse`
- [x] 3.2 Implementar metodo `getWorkdays()` em `grid402.mjs` e `grid402.cjs`
- [x] 3.3 Adicionar testes em `sdk/node/test_sdk.mjs` e `test_cjs.cjs` e validar execucao
- [x] 3.4 Atualizar versao para 2.4.0 em `sdk/node/package.json` e documentacao em `README.md`

## 4. SDK Python

- [x] 4.1 Adicionar modelos Pydantic `WorkdaysInput` e `WorkdaysResponse` em `python/grid402/models.py`
- [x] 4.2 Implementar metodo `get_workdays()` em `python/grid402/client.py` e acao no `agentkit.py`
- [x] 4.3 Adicionar testes unitarios em `python/tests/test_sdk.py` e validar execucao
- [x] 4.4 Atualizar versao para 2.3.0 em `python/pyproject.toml` e documentacao em `README.md`

## 5. Servidor MCP

- [x] 5.1 Implementar ferramenta `grid402_workdays` em `mcp/tools.mjs` com schema de entrada
- [x] 5.2 Adicionar teste de execucao em `mcp/test_mcp.mjs` e validar as 11 ferramentas
- [x] 5.3 Atualizar versao para 1.4.0 em `mcp/package.json` e documentacao em `mcp/README.md`

## 6. Verificacao de Integracao E2E

- [x] 6.1 Executar teste E2E ponta a ponta validando todos os 11 servicos e conformidade do teaser
- [x] 6.2 Validar todas as especificacoes com `openspec validate add-workdays-calculator-service --strict`

