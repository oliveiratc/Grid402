## 1. Serviços Core (Onda 3A)
- [x] 1.1 Criar `public_html/services/sec_company.php` com resolução ticker→CIK (cache 24h), consulta `submissions` (cache 3600s), filtro de `forms`/`limit`, suporte opcional a `include_facts=true` limitado a `8 MB`, header `User-Agent` SEC, validação pré-cobrança e injeção opcional de transporte HTTP para testes.
- [x] 1.2 Criar `public_html/services/vuln_audit.php` com consulta OSV.dev (modo pacote e modo ID direto), sync/cache de `6h` do feed CISA KEV com fallback para cópia anterior íntegra em disco, normalização de severidade/CVSS, veredito determinístico (`CLEAN` a `CRITICAL_KEV`), cache `900s` e injeção opcional de transporte HTTP.
- [x] 1.3 Criar `public_html/services/chain_telemetry.php` cobrindo `base`, `ethereum`, `polygon`, `arbitrum` e `solana` via RPCs públicos com fallback + TVL DeFiLlama (cache `300s`), cache por rede de `60s`, degradação graciosa (`partial_data`) e injeção opcional de transporte HTTP.

## 2. Gateway, Batch, Catálogo e UI (`31/31 UP`)
- [x] 2.1 Registrar `sec-company`, `vuln-audit` e `chain-telemetry` em `public_html/config.php` (`grid402_get_utility_services()` + proveniência `_meta`).
- [x] 2.2 Atualizar `public_html/services/batch.php` e `public_html/index.php` (roteamento, `$servicePrices`, validação pré-cobrança, teasers HTTP `402`, estorno Session Pass em `404`/`503`, enriquecimento de `/status` e listagem na landing/playground).
- [x] 2.3 Atualizar `public_html/collector.php` (garantindo `31/31 UP` em `summary_free.json`), `public_html/catalog.php`, `public_html/trust.php`, `public_html/agents.php`, `public_html/admin.php`, `public_html/includes/admin_cockpit_view.php` e `public_html/assets/js/i18n.js` (`?v=3.8.0`).

## 3. Servidor MCP v2.2.0 (33 Tools)
- [x] 3.1 Adicionar `grid402_sec_company`, `grid402_vuln_audit` e `grid402_chain_telemetry` em `mcp/tools.mjs`, `mcp/index.mjs`, `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json`, elevando a versão para `2.2.0` e mantendo exatas `33` tools.
- [x] 3.2 Atualizar `mcp/package.json`, `server.json`, `mcp/README.md` e `mcp/test_mcp.mjs` verificando as `33` tools e a versão `2.2.0`.

## 4. Descoberta, OpenAPI, Bazaar v2 e RapidAPI
- [x] 4.1 Atualizar `public_html/well_known_x402.php` e regenerar/sincronizar o fallback estático `public_html/.well-known/x402.json` com `30` recursos pagos e metadados Bazaar v2 sem `$ref`.
- [x] 4.2 Atualizar `public_html/pricing.json`, `public_html/openapi.json`, `public_html/.well-known/ai-agent.json`, `public_html/llms.txt`, `public_html/llms-full.txt`, `README.md` e `CHANGELOG.md` (sem `&` e respeitando a higiene de manifestos estáticos).
- [x] 4.3 Atualizar `scripts/generate_rapidapi_openapi.mjs` e `scripts/bazaar_seed.mjs`, regenerando `docs/revenue/openapi/rapidapi-global.json` e `docs/revenue/openapi/rapidapi-all.json`.

## 5. Testes e Verificação Final
- [x] 5.1 Criar `tests/test_phase3a_sec_vuln_telemetry.php` cobrindo todos os cenários das delta specs (offline determinístico + servidor HTTP local).
- [x] 5.2 Atualizar asserções de contagem exata nos testes de regressão afetados (`28` → `31` serviços, `27` → `30` pagos, `30` → `33` tools MCP).
- [x] 5.3 Executar `php scripts/verify_storage_integrity.php`, `php scripts/validate_cdp_bazaar.php`, suíte da Onda 3A, suítes de regressão PHP, `node mcp/test_mcp.mjs` e `npx -y @fission-ai/openspec@latest validate --all --strict`.
