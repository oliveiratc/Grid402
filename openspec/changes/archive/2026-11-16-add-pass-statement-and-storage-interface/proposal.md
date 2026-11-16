# Proposal: Extrato Detalhado por Session Pass e Abstração de Persistência Zero-DB (Fase 2)

## Why
Atualmente, usuários e operadores M2M de Session Passes (`pass_g402_...`) só visualizam o saldo agregado em `/payment-dashboard` e `/api/v1/pass/balance`. Falta um extrato individual Zero-PII com histórico das chamadas (`200`, bloqueios `403`/`429` de Spend Control e replays idempotentes), correlação por `request_id`, equivalente em USDC e exportação CSV/JSON para conciliação e depuração. Além disso, o acoplamento direto da lógica de negócio a leitura/escrita em arquivos JSON dificulta uma eventual evolução futura de persistência. (Ver PRD aprovado: [session-pass-statement-and-storage-interface.md](../../prds/session-pass-statement-and-storage-interface.md).)

## What Changes
- Introdução de `PassStorageInterface` e `JsonFlockPassRepository` em `public_html/services/pass_repository.php`, desacoplando `public_html/services/pass.php` sem exigir extensões externas (`pdo_sqlite`/`pdo_mysql`) em produção/CI.
- Registro automático Zero-PII (ring buffer limitado a `200` eventos recentes por `pass_id`) em arquivo dedicado `pass_statements.json` para desfechos `consumed` (`200`), `endpoint_restricted` (`403`), `daily_limit_exceeded` (`429`) e `idempotent_replay` (`200`).
- Novo endpoint gratuito `GET /api/v1/pass/statement` (roteado em `.htaccess` e `index.php`) autenticável pelo próprio token `pass_g402_...`, com filtros `endpoint` e `outcome` e saída `json` ou `csv` (UTF-8 com BOM e proteção contra CSV injection).
- Evolução de `/payment-dashboard` ([payment_dashboard.php](../../../public_html/payment_dashboard.php)) e [i18n.js](../../../public_html/assets/js/i18n.js) (`EN`/`PT`) exibindo resumo do extrato, filtros, alerta da janela de `200` lançamentos com contato de suporte e downloads CSV/JSON.
- Documentação de `/api/v1/pass/statement` em `openapi.json`, `llms.txt`, `llms-full.txt` e `/pass`, mais bateria automatizada dedicada `tests/test_session_pass_statement.php` integrada ao `.github/workflows/ci.yml`.

## Impact
- **Specs afetadas:** `x402-payment-oracle`, `i18n`, `agent-discovery`
- **Código afetado:** `public_html/config.php`, `public_html/.htaccess`, `public_html/services/pass_repository.php` (novo), `public_html/services/pass.php`, `public_html/index.php`, `public_html/payment_dashboard.php`, `public_html/pass.php`, `public_html/assets/js/i18n.js`, `public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt`, `tests/test_session_pass_statement.php` (novo), `.github/workflows/ci.yml`.
- **Fora do escopo:** provisionamento de SQLite/MySQL/Postgres, mudança das `24` ferramentas MCP ou novos métodos em SDKs npm/PyPI.
