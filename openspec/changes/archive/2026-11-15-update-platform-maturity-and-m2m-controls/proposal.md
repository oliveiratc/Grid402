# Maturidade Enterprise, Consistência Multi-Canal e Evolução da Infraestrutura M2M (Grid402 v3.0)

## Por quê
Auditorias independentes conduzidas por Claude, ChatGPT e Dola identificaram divergências críticas de documentação e operação (endereço de carteira truncado divergente no rodapé, matemática de créditos do Session Pass defasada em 10x–100x, catálogo MCP/README com 6 a 11 serviços contra 22 em produção, apenas 11/22 serviços com status `"UP"` verificado e bloqueio de crawlers de IA nas rotas `/trust` e `/openapi.json`), além de lacunas de governança institucional, histórico de uptime, proveniência de pacotes e controles financeiros para agentes autônomos.

Esta mudança implementa integralmente o plano aprovado no PRD `openspec/prds/platform-maturity-and-evolution-plan.md`, dividido em 3 fases estruturadas (Fase 1: Correções Críticas e Consistência; Fase 2: Profissionalização e Governança; Fase 3: Infraestrutura Financeira M2M com Idempotência e Spend Controls).

## O que muda
- **Fase 1 (Correções Críticas e Consistência Multi-Canal):**
  - Unificação do endereço da carteira recebedora (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` / `0xa592...3f90`) em `public_html/index.php` e documentos, removendo a exibição residual `0xa31c...356c`.
  - Padronização da matemática oficial do Session Pass (`1 crédito = 0.0050 USDC`): Starter (`2 USDC` = `400 créditos`), Pro (`5 USDC` = `1.000 créditos`) e Fleet (`20 USDC` = `5.000 + 1.000 bônus = 6.000 créditos`) em `public_html/x402.php`, `public_html/index.php`, `public_html/llms.txt`, `public_html/openapi.json` e `README.md`.
  - Cobertura completa de status operacional (`22/22 UP`) em `/status` e `/catalog` para todos os 22 serviços do catálogo.
  - Expansão e sincronização de `mcp/tools.mjs` e `README.md` para refletir o catálogo completo de serviços e descrições corretas.
  - Criação de `public_html/robots.txt` e cabeçalhos amigáveis a crawlers de LLMs (`ClaudeBot`, `GPTBot`, `ChatGPT-User`, `PerplexityBot`, `OAI-SearchBot`) em `/trust`, `/catalog`, `/status`, `/openapi.json` e `/llms.txt`.
- **Fase 2 (Profissionalização, Governança e Confiabilidade):**
  - Atualização da página `/trust` com transparência institucional do operador, tabela de fontes públicas oficiais + TTL de cache, posicionamento técnico transparente (Dados Públicos com Prova ECDSA vs Bureaus Privados), desambiguação de marca (`grid402.online`), criação de `CHANGELOG.md` e `public_html/.well-known/security.txt`.
  - Registro e exibição de histórico público de disponibilidade de 30 dias (`uptime_history.json`) em `/status` e `/trust`, mais injeção do bloco `_meta` (`upstream_source`, `fetched_at`, `cache_ttl_sec`) nas respostas da API.
  - Adição de proveniência verificável nos workflows de publicação CI/CD (PyPI Trusted Publishing / PEP 740 e `npm publish --provenance`) e script de verificação/backup de integridade UTF-8/JSON do armazenamento Zero-DB.
- **Fase 3 (Evolução da Infraestrutura Financeira M2M):**
  - Suporte ao cabeçalho `Idempotency-Key` (janela de 300s) nas rotas pagas `/api/v1/*` para impedir cobrança duplicada em retentativas de rede.
  - Criação do endpoint gratuito `GET /api/v1/pass/balance` e suporte a controles de gasto por Session Pass (`daily_credit_limit`, `allowed_endpoints`).

## Impacto
- **Specs afetadas:**
  - `x402-payment-oracle`
  - `agent-discovery`
  - `mcp-server`
  - `open-data-utilities`
  - `telemetry-collector`
- **Código afetado:**
  - `public_html/index.php`, `public_html/config.php`, `public_html/x402.php`, `public_html/openapi.json`, `public_html/llms.txt`, `public_html/robots.txt`, `public_html/.well-known/security.txt`
  - `mcp/tools.mjs`, `mcp/package.json`, `mcp/test_mcp.mjs`
  - `README.md`, `CHANGELOG.md`, `.github/workflows/`
  - `scripts/`, `tests/`
