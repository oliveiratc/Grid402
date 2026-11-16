# Endurecimento de Confiabilidade M2M, Sincronia MCP/SDK e Observabilidade Estruturada

## Por quê
Uma auditoria técnica e comercial externa (baseada no GitHub, PyPI e diretório Glama MCP) apontou inconsistências residuais e lacunas de evidência operacional descritas no PRD [audit-reliability-mcp-observability.md](../../prds/audit-reliability-mcp-observability.md):
- O servidor MCP (`mcp/index.mjs`) ainda não publica o campo `instructions` no handshake `initialize` (acusado no Glama) e os manifests públicos (`/.well-known/mcp.json` e `/.well-known/mcp/server-card.json`) expõem apenas 15 ferramentas em vez das 24 implementadas.
- Ainda existem ocorrências pontuais de `grid402.com.br` (`examples/`) e `0.0005` (`sdk/`, `.env.example`), e há ambiguidade documental entre `LICENSE` proprietário na raiz e `MIT` nos pacotes SDK/MCP.
- Faltam logs estruturados JSONL com correlação por `X-Request-Id` (Zero-PII + rotação em 10 MB), exibição de latência `p95` em `/admin` e `/trust`, e uma suíte real de concorrência/carga comprovando ausência de double-spend sob race condition paralela.

## O que muda
- **MCP Server e Discovery (`mcp/`, `public_html/.well-known/`):**
  - Adiciona `instructions` detalhadas no `Server` de `mcp/index.mjs`, alinha a versão para `1.8.2` em `package.json` raiz e `mcp/package.json` e atualiza o comentário de cabeçalho.
  - Atualiza `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` com todas as 24 ferramentas MCP sincronizadas e descrições em inglês consistentes.
- **Consistência de SDKs, Exemplos, Dual-License e Empacotamento:**
  - Remove referências legadas a `grid402.com.br` e `0.0005` em `examples/`, `sdk/`, `.env.example` e atualiza `python/README.md` + `CHANGELOG.md`.
  - Formaliza o modelo **Dual-License explícito** em `LICENSE` e `README.md` (Gateway/Backend `public_html/` Proprietário vs. SDKs, MCP Server e Integrações em **MIT**) e alinha o posicionamento principal *"The Verifiable Data and x402 Payment Layer for Autonomous AI Agents"*.
  - Valida builds locais (`python -m build` em `python/` e `npm pack` em `mcp/`).
- **Observabilidade Estruturada JSONL Zero-PII (`public_html/`):**
  - Gera/propaga header `X-Request-Id` em toda resposta da API/status e registra evento JSONL sanitizado em `private_data/requests.jsonl` com rotação automática ao atingir `10 MB` (`LOCK_EX`).
  - Calcula e exibe latência `p95` agregada em `/admin` (`admin.php` / `metrics.php`) e `/trust` (`trust.php`).
- **Suíte Real de Concorrência e Carga + Documentação de Chaves:**
  - Adiciona `tests/test_concurrency_and_load.php` testando corrida paralela no último crédito de Session Pass, replay concorrente de `tx_hash`/`payer:nonce` e benchmark `p50`/`p95`/`p99`.
  - Documenta política não-custodial de chaves M2M e Spend Controls em `/trust` e `docs/FACILITATOR_SETUP.md`.

## Impacto
- Specs afetadas: `mcp-server`, `agent-discovery`, `x402-payment-oracle`, `admin-dashboard`
- Fora do escopo: migração SQLite/PostgreSQL, extrato CSV/JSON por Session Pass no `/payment-dashboard` e marketplace de APIs de terceiros.
- Código afetado: `mcp/index.mjs`, `mcp/package.json`, `package.json`, `public_html/.well-known/mcp.json`, `public_html/.well-known/mcp/server-card.json`, `public_html/logger.php`, `public_html/index.php`, `public_html/metrics.php`, `public_html/admin.php`, `public_html/trust.php`, `examples/*`, `sdk/*`, `python/*`, `LICENSE`, `README.md`, `CHANGELOG.md`, `tests/test_concurrency_and_load.php`.
