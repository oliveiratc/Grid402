# Tasks: Grid402 Revenue Engine v4 (`add-revenue-engine-v4`)

## 1. x402 Multi-Chain Gasless via PayAI (`Base`, `Polygon`, `Arbitrum`, `Solana`) e Sandbox `3 req/dia`
- [x] 1.1 Atualizar `public_html/config.php` com `GRID402_SANDBOX_RATE_LIMIT = 3`, `GRID402_SANDBOX_WINDOW_SECONDS = 86400`, `GRID402_PAYAI_SOLANA_FEE_PAYER` e registro dos serviços `extract` e `email-verify` no catálogo oficial (`25` serviços).
- [x] 1.2 Atualizar `public_html/x402.php`: ajustar `grid402_check_sandbox_rate_limit()` para janela configurável de 24h (`3 req/dia`), atualizar `grid402_build_accepts()` para emitir 4 redes `exact` em mainnet (`Base`, `Polygon`, `Arbitrum`, `Solana` mantendo oferta única em `base-sepolia`) e incluir `extract` e `email-verify` em `grid402_bazaar_default_query_params()`.
- [x] 1.3 Atualizar `public_html/facilitator.php` para decodificar, validar localmente e liquidar via PayAI Facilitator payloads `exact` em `eip155:8453`, `eip155:137`, `eip155:42161` (EIP-3009) e `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` (`payload.transaction` base64 + anti-replay atômico).

## 2. Novos Micro-Serviços Globais de Alto Volume (`/api/v1/extract` e `/api/v1/email-verify`)
- [x] 2.1 Criar `public_html/services/extract.php` com proteção SSRF rigorosa (esquema http/https, DNS prévio + bloqueio de IPs privados/loopback/metadata, redirects manuais máx. 3, limite 1 MB, timeout 5s) e conversão HTML → Markdown limpo + metadados + links.
- [x] 2.2 Criar `public_html/services/email_verify.php` com validação RFC, consulta DNS `MX` + `SPF`/`DMARC`, detecção de provedor gratuito, lista de domínios descartáveis (*disposable*), `deliverability_score` (0–100) e `verdict` (`VALID`/`RISKY`/`INVALID`) estritamente Zero-PII.
- [x] 2.3 Integrar `/api/v1/extract` e `/api/v1/email-verify` em `public_html/.htaccess`, `public_html/index.php` (roteamento, preços `0.0050 USDC`, validação pré-cobrança, teasers HTTP 402 e enriquecimento), `public_html/collector.php` (`25/25 UP`), `public_html/catalog.php` e `public_html/trust.php`.

## 3. Paridade MCP (`27 tools`, `v2.0.0`), Manifestos e Bazaar Seeder
- [x] 3.1 Adicionar `grid402_extract` e `grid402_email_verify` em `public_html/mcp.php`, `mcp/tools.mjs`, `mcp/index.mjs` e `mcp/test_mcp.mjs`, sincronizando a versão `2.0.0` em `mcp/package.json`, `package.json`, `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json`.
- [x] 3.2 Atualizar `public_html/openapi.json`, `public_html/.well-known/x402.json` (24 endpoints pagos + `accepts` multi-chain), `public_html/llms.txt`, `public_html/llms-full.txt`, `README.md` (zero `&`) e `scripts/bazaar_seed.mjs` (incluindo `dossier`, `extract` e `email-verify`).

## 4. Funil Web Self-Service (`/dossie-kyb`), SEO PT-BR e Hero Conversion
- [x] 4.1 Criar `public_html/dossie_kyb.php` (PT-BR SSR): formulário CNPJ, prévia grátis via HTTP 402, checkout Pix R$ 12 inline (`/api/v1/pass/mp-pix` + polling `/mp-status`), suporte a `pass_g402_...` existente, renderização do Dossiê 360º completo e folha `@media print` otimizada para PDF.
- [x] 4.2 Criar `public_html/seo_landing.php` atendendo `/api-cnpj`, `/api-sancoes-cgu` e `/api-dias-uteis-anbima` (100% SSR PT-BR, JSON-LD, exemplos `curl -sL`/Python/n8n e CTAs Pix/Dossiê), registrar rotas em `public_html/.htaccess` e atualizar `public_html/sitemap.xml` (incluindo `/pass`, `/dossie-kyb` e as 3 páginas SEO).
- [x] 4.3 Atualizar Hero e textos de Sandbox (`3 req/dia`) em `public_html/index.php` (botão Pix R$ 12 + link `/dossie-kyb`) e ativar auto-detecção PT-BR para visitantes `pt*` em `public_html/assets/js/i18n.js`.

## 5. Onda 2 — Pacotes de Distribuição Paga e Playbook Comercial B2B (`docs/revenue/`)
- [x] 5.1 Criar `docs/revenue/rapidapi-pack.md`, `docs/revenue/n8n-creator-submission.md`, `docs/revenue/b2b-outbound-playbook.md` e `docs/revenue/directory-checklist.md`.

## 6. Testes Automatizados, Validação e Empacotamento
- [x] 6.1 Criar suíte dedicada `tests/test_revenue_engine_v4.php` cobrindo: Multi-Chain `accepts[]` (4 redes mainnet vs. 1 em Sepolia), decodificação/validação EVM (Polygon/Arbitrum) e Solana SVM no facilitador, SSRF block + extração em `/api/v1/extract`, validação + disposable + Zero-PII em `/api/v1/email-verify`, rate-limit Sandbox `3 req/dia`, e renderização SSR de `/dossie-kyb` e das 3 páginas SEO PT-BR.
- [x] 6.2 Atualizar testes existentes que verificavam contagens antigas (`23` serviços, `25` tools MCP, `v1.9.0` ou limite `5` sandbox) e executar toda a bateria (`verify_storage_integrity.php`, `test_revenue_engine_v4.php`, `test_agent_discovery.php`, `test_agentkit_integration.php`, `test_maturity_and_m2m_controls.php`, `test_x402_exact_parsing.php`, `test_e2e_full.php`, `node mcp/test_mcp.mjs`).
- [x] 6.3 Validar `npx -y @fission-ai/openspec@latest validate --all --strict` e gerar o pacote final `dist/grid402_hostinger_deploy.zip` via `scripts/package_deploy.ps1`.
