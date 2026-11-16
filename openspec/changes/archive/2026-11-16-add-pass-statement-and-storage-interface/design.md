# Design: PassStorageInterface & Extrato Zero-PII de Session Pass

## 1. Arquitetura de Repositório (`PassStorageInterface`)
Arquivo novo: `public_html/services/pass_repository.php`

```php
interface PassStorageInterface {
    public function issuePass(string $txHash, int $rawUsdcUnits = 50000, bool $isSandbox = false): array;
    public function getPassStatus(string $token): array;
    public function updatePassLimits(string $token, ?int $dailyCreditLimit, ?array $allowedEndpoints = null): array;
    public function consumePass(string $token, int $requiredRawUnits = 5000, ?string $endpointId = null, array $auditContext = []): array;
    public function recordStatementEvent(string $token, array $event): bool;
    public function getStatement(string $token, array $filters = []): array;
}
```

Implementação padrão: `JsonFlockPassRepository implements PassStorageInterface`.
- Usa `grid402_read_locked_json()` (`rewind()` + `stream_get_contents()`) sob `flock(LOCK_EX)`.
- A singleton/factory helper `grid402_pass_repository()` em `services/pass.php` permite que chamadas existentes (`grid402_issue_session_pass`, `grid402_get_session_pass_status`, `grid402_update_session_pass_limits`, `grid402_consume_session_pass`) preservem assinatura retrocompatível enquanto delegam ao repositório ativo.

## 2. Separação de Storage e Ordem de Lock (Zero Deadlock)
- **Arquivo quente:** `GRID402_FILE_PASSES` (`session_passes.json`) guarda apenas estado e contadores de cada passe, mantendo `< 15 ms` por requisição.
- **Arquivo de extratos:** `GRID402_FILE_PASS_STATEMENTS` (`GRID402_PRIVATE_DIR . '/pass_statements.json'`, definido em `public_html/config.php` respeitando override de ambiente `GRID402_FILE_PASS_STATEMENTS`).
- **Prevenção de deadlock:** `consumePass()` **sempre** fecha o file handle de `session_passes.json` (`flock(LOCK_UN)` + `fclose()`) **antes** de abrir `pass_statements.json` sob `flock(LOCK_EX)`. Nunca dois locks ficam abertos simultaneamente.
- **Limpeza automática:** Quando `issuePass()` remove passes expirados há mais de 7 dias em `session_passes.json`, remove as chaves correspondentes em `pass_statements.json` quando aplicável (ou filtra durante atualização).

## 3. Schema Zero-PII e Ring Buffer (`max 200`)
Cada item armazenado para `pass_id` possui estritamente:
```json
{
  "ts": "2026-11-16T08:30:00Z",
  "request_id": "g402_a1b2c3d4e5f60718",
  "endpoint": "cnpj",
  "credits_debited": 4,
  "credits_after": 396,
  "usdc_equivalent": "0.0200",
  "http_status": 200,
  "outcome": "consumed"
}
```
- **Ring Buffer:** Ao anexar evento para `$passId`, caso `count($list) > 200`, executa `array_slice($list, -200)`.
- **Sanitização:** `endpoint` reduzido a `[a-z0-9_-]{1,40}` (sem query string); `request_id` via `grid402_get_request_id()`; sem IP, sem CNPJ/CEP/domínio, sem assinatura/token completo.
- **Outcomes válidos:** `consumed` (`200`), `endpoint_restricted` (`403`), `daily_limit_exceeded` (`429`), `idempotent_replay` (`200`, `credits_debited = 0`).

## 4. Contrato HTTP `GET /api/v1/pass/statement` e CSV Seguro
- **Roteamento:** `.htaccess` (`^api/v1/pass/(balance|limits|statement)/?$ -> index.php?api=pass-$1`) + `index.php` tratando `$apiParam === 'pass-statement'`.
- **Autenticação:** extrai `pass_g402_...` de `$_GET['token']`, `$_SERVER['HTTP_X_PAYMENT']` ou `Authorization: Bearer ...`. Valida HMAC com `grid402_pass_verify_token_format()` (`400 InvalidPassTokenFormat` se inválido/ausente, `404 PassNotFound` se inexistente). Zero débito de créditos.
- **Filtros:** `?endpoint=<id>` e `?outcome=<consumed|endpoint_restricted|daily_limit_exceeded|idempotent_replay>`.
- **Saída JSON (`?format=json` ou padrão):**
  ```json
  {
    "ok": true,
    "service": "pass_statement",
    "pass": { "tier": "...", "status": "ACTIVE", "mode": "production", "credits_total": 400, "credits_remaining": 396, "credits_used_today": 4, "daily_credit_limit": null, "allowed_endpoints": [], "expires_at": "..." },
    "retention": { "max_entries_per_pass": 200, "stored_entries": 12, "truncated": false, "retention_notice": "Stores up to the 200 most recent events per pass. Contact contato@grid402.online for extended archival.", "support_contact": "contato@grid402.online" },
    "filters": { "endpoint": null, "outcome": null },
    "summary": { "returned_entries": 12, "total_credits_debited": 18, "total_usdc_equivalent": "0.0900", "blocked_count": 1, "replay_count": 1 },
    "entries": [ ...em ordem decrescente (mais recente primeiro)... ]
  }
  ```
- **Saída CSV (`?format=csv`):**
  - Header `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment; filename="grid402_statement_<passId>.csv"`.
  - BOM UTF-8 (`"\xEF\xBB\xBF"`).
  - Colunas: `ts,request_id,endpoint,outcome,http_status,credits_debited,credits_after,usdc_equivalent`.
  - Função `grid402_csv_safe_cell(string $value)`: prefixa `'` se `$value !== ''` e primeiro caractere estiver em `['=', '+', '-', '@', "\t", "\r"]` (anti Formula/CSV Injection).

## 5. UX `/payment-dashboard` + `i18n.js`
- Ao consultar `?token=pass_g402_...` válido no `payment_dashboard.php`:
  - Mantém cards de saldo / alerta de saldo baixo existentes.
  - Adiciona painel de **Extrato de Consumo (Session Pass Statement)**:
    - Formulário `GET /payment-dashboard` preservando `token`, com selects `endpoint` e `outcome`, e botões diretos de download **Export CSV** e **Export JSON** apontando para `/api/v1/pass/statement?...&format=csv|json`.
    - Banner informativo discreto da janela de `200` eventos recentes e link para `mailto:contato@grid402.online`.
    - Cards de resumo filtrado e tabela acessível (`<th scope="col">`) listando todos os lançamentos ou estado vazio amigável.
  - Todas as novas chaves adicionadas nas tabelas `en` e `pt` do `public_html/assets/js/i18n.js` (default inglês no SSR, alternância instantânea no seletor PT/EN).
