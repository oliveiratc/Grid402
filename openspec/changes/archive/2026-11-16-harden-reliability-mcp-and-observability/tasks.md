## 1. MCP Server, Manifests e Consistência (Bloco 1)
- [x] 1.1 Adicionar `instructions` nativo em `mcp/index.mjs`, atualizar comentário/versão para `1.8.2` em `mcp/index.mjs`, `mcp/package.json` e `package.json` raiz.
- [x] 1.2 Sincronizar `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` para listar todas as 24 ferramentas MCP com URLs em `https://grid402.online`.
- [x] 1.3 Corrigir referências legadas (`grid402.com.br` e `0.0005`) em `examples/client_agent.mjs`, `examples/client_agent.py`, `sdk/node/grid402.cjs`, `sdk/python/grid402_tool.py` e `.env.example`, além de atualizar `python/README.md`.
- [x] 1.4 Formalizar Dual-License explícita (Gateway `public_html/` Proprietário vs. SDKs/MCP/Integrações em `MIT`) em `LICENSE` e `README.md`, alinhando o posicionamento principal *"The Verifiable Data and x402 Payment Layer for Autonomous AI Agents"*.
- [x] 1.5 Atualizar `CHANGELOG.md` e documentar boas práticas de gestão não-custodial de chaves M2M no `public_html/trust.php` e `docs/FACILITATOR_SETUP.md`.

## 2. Observabilidade Estruturada e Telemetria P95 (Bloco 2)
- [x] 2.1 Criar módulo `public_html/logger.php` com `grid402_get_request_id()`, gravação JSONL Zero-PII (`requests.jsonl`) protegida por `flock` e rotação automática em `10 MB` (`.1`).
- [x] 2.2 Integrar `X-Request-Id` e `grid402_log_request(...)` no fluxo de respostas de `public_html/index.php` (sucesso 200, desafio 402, replay 403, rate limit 429, erro 404/400, verify e Mercado Pago).
- [x] 2.3 Atualizar `public_html/metrics.php` com buffer circular de latências (`latency_samples_ms`) e cálculo de `latency_p50_ms` / `latency_p95_ms`, exibindo `p95` em `public_html/admin.php` e `public_html/trust.php` (com suporte `i18n.js`).

## 3. Testes, Empacotamento e Verificação
- [x] 3.1 Criar `tests/test_concurrency_and_load.php` testando corrida paralela no último crédito do Session Pass, replay concorrente de `tx_hash` e `payer:nonce`, `requests.jsonl` + rotação 10 MB + `X-Request-Id`, e benchmark `p50`/`p95`/`p99`.
- [x] 3.2 Atualizar/validar `tests/test_agent_discovery.php` e `mcp/test_mcp.mjs` para verificar `24` ferramentas nos manifests `/.well-known/` e `instructions` no `mcp/index.mjs`.
- [x] 3.3 Executar todas as suítes de testes PHP, Python e Node.js/MCP, validar builds locais (`python -m build` no `python/` e `npm pack --dry-run` no `mcp/`) e regerar `dist/grid402_hostinger_deploy.zip`.
