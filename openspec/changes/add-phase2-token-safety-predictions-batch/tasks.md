## 1. Novos Micro-Serviços Core (Zero-DB, Fontes Públicas)
- [x] 1.1 Criar `public_html/services/token_safety.php` com suporte a Base, Ethereum, Polygon, Arbitrum e Solana (auto-detecção Base58), integração GoPlus Security API + DexScreener API pública, score determinístico `0–100`, veredito (`SAFE`/`CAUTION`/`HIGH_RISK`/`CRITICAL`), cache Zero-DB `300s`, disclaimer técnico e fallback `503` pré-cobrança.
- [x] 1.2 Criar `public_html/services/prediction_markets.php` agregando Polymarket Gamma API e Kalshi Public Trade API v2 com filtro `q`, `source`, `status` e `limit`, normalização `0–100%`, degradação graciosa entre fontes, cache Zero-DB `120s` e falha total `503` pré-cobrança.
- [x] 1.3 Criar `public_html/services/batch.php` implementando validação do lote (`1..5` itens, cap `<= 2` em sandbox, bloqueio de serviços recursivos/proibidos), cálculo dinâmico exato da soma em `raw_units` e orquestração interna de execução/estorno.

## 2. Porteiro x402, Catálogo e Cobrança no Gateway
- [x] 2.1 Registrar `token-safety` (`0.0150 USDC`), `prediction-markets` (`0.0100 USDC`) e `batch` (dinâmico, base `0.0050 USDC`) em `grid402_get_utility_services()` (`public_html/config.php`).
- [x] 2.2 Extrair/compartilhar despachante interno de serviços em `public_html/index.php` para rotear `GET /api/v1/token-safety`, `GET /api/v1/prediction-markets` e `POST /api/v1/batch` com cálculo dinâmico de preço no desafio `402`, Spend Controls (`allowed_endpoints` por sub-item + `daily_credit_limit`), estorno automático de sub-itens falhos no Session Pass, abort de `/settle` se 100% falharem, `Idempotency-Key` e `_meta` + `_oracle_proof`.
- [x] 2.3 Atualizar schemas e metadados CDP Bazaar v2 para os 3 novos endpoints em `public_html/x402.php` (incluindo `method: POST` e schema inline para `batch`).

## 3. Coletor, Páginas Públicas, Admin e i18n (`28/28 UP`)
- [x] 3.1 Atualizar `public_html/collector.php` para monitorar os 28 serviços e gerar `summary_free.json` com `28/28 UP`.
- [x] 3.2 Atualizar UI e traduções PT-BR/EN em `public_html/index.php`, `public_html/catalog.php`, `public_html/trust.php` e `public_html/assets/js/i18n.js` (cards, Playground presets, matriz de fontes/TTL e contadores de 28 endpoints).
- [x] 3.3 Atualizar `public_html/admin.php` e `public_html/includes/admin_cockpit_view.php` (tabela de preços/exportação CSV com os 28 serviços, barras de volume e labels).

## 4. Paridade MCP (`v2.1.0` — 30 Tools)
- [x] 4.1 Adicionar `grid402_token_safety`, `grid402_prediction_markets` e `grid402_batch` (suporte a `POST`) em `mcp/tools.mjs`, `mcp/index.mjs` e atualizar versão para `2.1.0`.
- [x] 4.2 Sincronizar `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` com as 30 ferramentas e versão `2.1.0`.
- [x] 4.3 Atualizar `mcp/test_mcp.mjs` para validar as 30 tools e os novos schemas.

## 5. Manifestos de Descoberta, OpenAPI e Documentação
- [x] 5.1 Atualizar `public_html/well_known_x402.php` e regenerar `public_html/.well-known/x402.json` com os 27 recursos pagos.
- [x] 5.2 Atualizar `public_html/openapi.json`, `public_html/pricing.json`, `public_html/.well-known/ai-agent.json`, `public_html/llms.txt`, `public_html/llms-full.txt`, `README.md` e `CHANGELOG.md` (sem o caractere `&` nos arquivos verificados).
- [x] 5.3 Executar `php scripts/validate_cdp_bazaar.php` confirmando aprovação `27/27`.

## 6. Testes Automatizados e Homologação
- [x] 6.1 Criar `tests/test_phase2_token_safety_predictions_batch.php` cobrindo todos os cenários das specs (mock HTTP GoPlus/DexScreener/Polymarket/Kalshi, fail-closed `503`, `400`, `402`, `POST /api/v1/batch` com soma exata, estorno parcial no Session Pass, `allowed_endpoints`, cap `2` em sandbox, `Idempotency-Key` e `_oracle_proof`).
- [x] 6.2 Atualizar contagens esperadas (`25` → `28` serviços e `27` → `30` tools MCP) nos testes existentes (`test_maturity_and_m2m_controls.php`, `test_global_services_and_pass.php`, `test_agent_discovery.php`, etc.).
- [x] 6.3 Executar toda a suíte de homologação (`verify_storage_integrity.php`, todos os testes PHP e `node mcp/test_mcp.mjs`) e `npx -y @fission-ai/openspec@latest validate --all --strict`.
