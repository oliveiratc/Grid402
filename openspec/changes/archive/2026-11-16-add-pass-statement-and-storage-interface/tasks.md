## 1. Camada de Repositório Zero-DB (`PassStorageInterface`)
- [x] 1.1 Definir `GRID402_FILE_PASS_STATEMENTS` em `public_html/config.php` respeitando override de ambiente `GRID402_FILE_PASS_STATEMENTS`.
- [x] 1.2 Criar `public_html/services/pass_repository.php` com `PassStorageInterface`, implementação `JsonFlockPassRepository` (usando `grid402_read_locked_json` + `flock` sem deadlocks), ring buffer de até `200` eventos/pass Zero-PII e sanitização anti CSV injection (`grid402_csv_safe_cell`).
- [x] 1.3 Integrar `PassStorageInterface` / `JsonFlockPassRepository` em `public_html/services/pass.php`, registrando automaticamente desfechos `consumed` (`200`), `endpoint_restricted` (`403`) e `daily_limit_exceeded` (`429`) e expondo `grid402_record_pass_statement_event` e `grid402_get_session_pass_statement`.

## 2. API HTTP `GET /api/v1/pass/statement` e Replay Idempotente
- [x] 2.1 Atualizar `public_html/.htaccess` (`^api/v1/pass/(balance|limits|statement)/?$`) e `public_html/index.php` para rotear `GET /api/v1/pass/statement` (suportando `?token=`, `X-Payment`, `Authorization: Bearer`, filtros `?endpoint=` e `?outcome=`, e saída `?format=json|csv` com BOM UTF-8 e headers de download), emitindo `X-Request-Id` e log estruturado Zero-PII.
- [x] 2.2 Registrar evento `outcome: "idempotent_replay"` (`credits_debited: 0`, `http_status: 200`) no extrato quando uma chamada com `Idempotency-Key` e `X-Payment: pass_g402_...` retornar do cache sem cobrar créditos.

## 3. UI `/payment-dashboard`, i18n e Descoberta M2M
- [x] 3.1 Atualizar `public_html/payment_dashboard.php` com formulário de filtros (`endpoint`, `outcome`), resumo de extrato, banner de retenção de `200` eventos com contato de suporte, botões **Export CSV** / **Export JSON**, tabela semântica responsiva e estado vazio amigável.
- [x] 3.2 Adicionar todas as chaves `EN` e `PT` correspondentes em `public_html/assets/js/i18n.js` e validar paridade `count >= 2`.
- [x] 3.3 Documentar `GET /api/v1/pass/statement` em `public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt` e `public_html/pass.php` sem inserir `&` nos manifests.

## 4. Testes, CI e Verificação
- [x] 4.1 Criar `tests/test_session_pass_statement.php` cobrindo `PassStorageInterface`, registro `200`/`403`/`429`/`idempotent_replay`, cap em `200` entradas, Zero-PII, proteção anti CSV injection, filtros e validação HTTP (400/404/200 CSV e JSON) + disputa concorrente.
- [x] 4.2 Integrar `php tests/test_session_pass_statement.php` em `.github/workflows/ci.yml` e executar toda a suíte local em diretório `GRID402_STORAGE_DIR` limpo + `scripts/verify_storage_integrity.php` + `scripts/package_deploy.ps1`.
