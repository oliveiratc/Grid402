# Tasks: Maturidade Enterprise, Consistência Multi-Canal e Controles M2M

## 1. Fase 1: Correções Críticas, Consistência e Desbloqueio de Agentes
- [x] 1.1 Unificar o endereço da carteira recebedora (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` / `0xa592...3f90`) em `public_html/index.php` e demais arquivos, removendo qualquer ocorrência de `0xa31c...356c`.
- [x] 1.2 Padronizar a matemática oficial do Session Pass (`1 crédito = 0.0050 USDC`: Starter = `400 créditos`, Pro = `1.000 créditos`, Fleet = `5.000 + 1.000 bônus = 6.000 créditos`) em `public_html/x402.php`, `public_html/index.php`, `public_html/llms.txt`, `public_html/openapi.json` e `README.md`.
- [x] 1.3 Atualizar o motor de status e catálogo em `public_html/config.php` e `public_html/index.php` para que todos os **22 serviços** apresentem status operacional verificado (`"UP"`, latência e fonte upstream) em `/status` e `/catalog`.
- [x] 1.4 Expandir `mcp/tools.mjs` e atualizar `README.md` para cobrir todos os serviços do catálogo com descrições exatas, preços reais (`0.0050 a 0.0300 USDC`) e paridade de schemas.
- [x] 1.5 Criar `public_html/robots.txt` e garantir cabeçalhos HTTP abertos (`Access-Control-Allow-Origin: *`, sem bloqueio de User-Agent) para crawlers de IA (`ClaudeBot`, `GPTBot`, `ChatGPT-User`, `PerplexityBot`, `OAI-SearchBot`) em `/trust`, `/catalog`, `/status`, `/openapi.json` e `/llms.txt`.

## 2. Fase 2: Profissionalização, Governança e Confiabilidade Operacional
- [x] 2.1 Atualizar a página `/trust` em `public_html/index.php` com identificação institucional do operador, tabela de proveniência e TTL por fonte pública, posicionamento claro (Dados Públicos com Prova ECDSA vs Bureaus Privados) e nota de desambiguação de marca (`grid402.online`).
- [x] 2.2 Criar `CHANGELOG.md` na raiz do projeto (documentando histórico de versões e evolução de preços) e `public_html/.well-known/security.txt`.
- [x] 2.3 Implementar persistência de série histórica de disponibilidade de 30 dias (`uptime_history.json`) e exibi-la em `/status` e `/trust`, além de injetar o bloco `_meta` (`upstream_source`, `fetched_at`, `cache_ttl_sec`, `operator`) nas respostas HTTP 200 dos serviços pagos.
- [x] 2.4 Configurar atestação de proveniência nos workflows de CI/CD (`.github/workflows/`) para publicação PyPI (PEP 740 / Trusted Publishing) e npm (`--provenance`), e criar script `scripts/verify_storage_integrity.php` para validação UTF-8/JSON e snapshot do armazenamento Zero-DB.

## 3. Fase 3: Evolução da Infraestrutura Financeira M2M (Idempotência e Spend Controls)
- [x] 3.1 Implementar suporte ao cabeçalho `Idempotency-Key` (TTL de 300 segundos em `idempotency_cache.json`) nas rotas pagas `/api/v1/*`, retornando `X-Idempotent-Replay: true` sem debitar créditos em retentativas.
- [x] 3.2 Implementar o endpoint gratuito `GET /api/v1/pass/balance` e os controles de gasto por Session Pass (`daily_credit_limit` e `allowed_endpoints`, com bloqueio HTTP 429 `PassDailyLimitExceeded` ao atingir o teto diário).

## 4. Testes e Verificação
- [x] 4.1 Criar e executar suíte de testes automatizados (`tests/test_maturity_and_m2m_controls.php` e `mcp/test_mcp.mjs`) validando todos os cenários das specs (Fase 1, Fase 2 e Fase 3) e regressão completa do projeto.
