# Tasks

## 1. Telemetria Real de Crawlers, Page-Views, HTTP 429 e `mcp_sandbox` (RF12, RF13)

- [x] 1.1 Ampliar `grid402_is_bot_user_agent()` em `public_html/metrics.php` (`Go-http-client`, `undici`, `node-fetch`, `axios`, `httpx`, `aiohttp`, `Scrapy`, `Java/`, `okhttp`, `Guzzle`, `ChatGPT-User`, `Claude-Web`, `Meta-ExternalAgent`, `Playwright`, `Puppeteer`) e impedir que chamadas `Accept: text/markdown` em `/` ou visitas com sessão administrativa autenticada incrementem `total_page_views`, verificando via testes unitários em `tests/test_real_telemetry_change2.php`
- [x] 1.2 Implementar classificação separada de crawlers/scanners de diretórios x402 em `public_html/metrics.php` (`total_crawler_requests`, `daily[$today]['crawler_requests']` e exposição de `real_api_requests` mantendo `total_requests` retrocompatível), verificando em `tests/test_real_telemetry_change2.php` que chamadas de crawlers não inflam o indicador real de API
- [x] 1.3 Desacoplar bloqueios HTTP `429` (`SandboxRateLimitExceeded`) de `total_sandbox_requests` e `payment_sources['sandbox']` em `public_html/metrics.php`, `public_html/index.php` e `public_html/mcp.php`, registrando `mcp_sandbox` separadamente apenas em execuções auto-trial HTTP `200`, verificando em teste HTTP que 1 sandbox `200` + 3 bloqueios `429` resultam em `total_sandbox_requests === 1` e `failure_categories['sandbox_rate_limited'] === 3`

## 2. Métrica Real de `Wallets Conectadas` vs. Perfis Identificados (RF14)

- [x] 2.1 Atualizar `public_html/growth.php` para distinguir `wallet_verified: true` em `profiles`, fazendo `grid402_growth_metrics()` retornar `wallets_connected` apenas para carteiras Web3 verificadas (EVM/SVM pagantes ou conexão autenticada) e `identified_profiles` para todos os demais perfis M2M, verificando em teste unitário que eventos `402`/`sandbox` sem carteira mantêm `wallets_connected === 0`
- [x] 2.2 Adicionar rota protegida `POST /api/v1/growth/wallet-connect` em `public_html/index.php` (validação `^0x[a-fA-F0-9]{40}$`, rate-limit em `private_data/wallet_connect_rates.json` sob `flock(LOCK_EX)` retornando `429` em abuso, deduplicação por HMAC) e acionar o registro após `eth_requestAccounts` em `public_html/index.php` e `public_html/pass.php`, verificando deduplicação e bloqueio `429` em teste HTTP

## 3. Ring Buffer `payment_failures.json`, Telemetria/Alertas MP e Clareza no `/admin` (RF15, RF16)

- [x] 3.1 Definir `GRID402_FILE_PAYMENT_FAILURES` em `public_html/config.php` e implementar em `public_html/logger.php` a gravação atômica (`flock(LOCK_EX)`) de até `500` falhas reais Zero-PII em `payment_failures.json` + leitura prioritária em `grid402_read_recent_payment_failures()`, verificando em teste que falhas permanecem visíveis no `/admin` mesmo após gravar `> 300 KB` de desafios `402` em `requests.jsonl`
- [x] 3.2 Registrar falhas `502`/`503` das rotas `/api/v1/pass/mp-*` em `failure_categories['mercadopago_gateway_error']` e em `payment_failures.json` (`public_html/index.php` e `public_html/mcp.php`), e adicionar alertas automáticos em `public_html/services/admin_insights.php` para token Mercado Pago ausente em produção e falhas recentes do gateway, validando em `tests/test_real_telemetry_change2.php` e mantendo aprovação integral de `tests/test_admin_dashboard.php`
- [x] 3.3 Atualizar `public_html/admin.php` e `public_html/includes/admin_cockpit_view.php` para exibir tráfego real vs. crawlers x402, `Wallets Conectadas Reais` vs. `Perfis Identificados`, `mcp_sandbox` nas origens, distinção de HTTP `400` (`400 Prova inválida` vs. `400 Parâmetro inválido`), rótulo explícito de resíduo histórico pré-24/11 e nota de apuração externa para receita RapidAPI Hub, verificando o HTML SSR e JSON `/admin?api=stats` em teste automatizado

## 4. Proteção de Margem RapidAPI, `llms.txt` EN e Limpeza `teaser_data` (RF17, RF18)

- [x] 4.1 Alterar o valor padrão de `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` para `15` em `public_html/config.php` (preservando override via env) e verificar em teste HTTP que a 16ª chamada diária ao `/api/v1/dossier` via RapidAPI retorna HTTP `429` (`RapidApiEndpointCapExceeded`)
- [x] 4.2 Traduzir integralmente `public_html/llms.txt` para inglês alinhado à oferta vigente (`MCPTRY50`, `/mcp` auto-trial, tiers `Micro`/`Starter`/`Pro`/`Fleet`, RapidAPI USD e `/dossie-kyb`, sem Solana ativa e sem `&`) e remover `teaser_data` duplicado dos desafios HTTP `402` em `public_html/x402.php` preservando-o exclusivamente em `/api/v1/dossier`, verificando via testes automatizados

## 5. Verificação Integrada e Empacotamento da Change 2

- [x] 5.1 Executar `tests/test_real_telemetry_change2.php`, atualizar eventuais asserções legadas impactadas pelas specs desta change, rodar toda a suíte de homologação PHP/MCP (`verify_storage_integrity.php`, `test_admin_dashboard.php`, `test_agent_discovery.php`, `test_billing_audit_fixes.php`, `test_revenue_engine_v4.php`, `test_revenue_unblock_change1.php`, `test_dossier.php`, `test_maturity_and_m2m_controls.php`, `test_concurrency_and_load.php`, `test_session_pass_statement.php`, `test_agentkit_integration.php`, `test_e2e_full.php`, `node mcp/test_mcp.mjs` e `npx -y @fission-ai/openspec@latest validate --all --strict`) confirmando 100% de aprovação
- [x] 5.2 Gerar o pacote atualizado de deploy Hostinger (`powershell -ExecutionPolicy Bypass -File scripts/package_deploy.ps1`) e validar `dist/grid402_hostinger_deploy.zip`

## Workflow follow-up

- Após validação integral contra todos os cenários das specs, arquivar a change `real-telemetry-admin-and-discovery-hardening` via `openspec archive` (mesclando delta specs em `openspec/specs/`).
