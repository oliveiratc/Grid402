# Design Técnico: Confiabilidade M2M, Sincronia MCP/SDK e Observabilidade Estruturada

## 1. Visão Geral da Arquitetura
Todas as melhorias operam sobre a arquitetura existente em PHP 8.1+ Zero-DB (Hostinger LiteSpeed) e Node.js ESM (`grid402-mcp`), sem adicionar bancos SQL ou dependências externas de runtime no backend.

## 2. Decisões de Design

### 2.1 MCP `instructions` e Sincronia dos 24 Tools
- Em `mcp/index.mjs`, o construtor do SDK `@modelcontextprotocol/sdk` (`new Server(serverInfo, options)`) aceita nativamente `options.instructions` (`string`). Ao fornecer um texto estruturado em inglês explicando:
  - uso gratuito (`grid402_summary`, `grid402_verify`, `grid402_pass_balance`);
  - modo Sandbox (`GRID402_SANDBOX=true` / `X-Payment: demo`, 5 req/h/IP);
  - fluxo de pagamento (`GRID402_SESSION_PASS` para `<15ms` ou `GRID402_PRIVATE_KEY` via carteira dedicada com teto baixo);
  - tratamento de erros HTTP `402`/`403`/`429` (`PassDailyLimitExceeded`, `PassEndpointRestricted`),
  os clientes MCP e indexadores como Glama passam a ler automaticamente as instruções no handshake `initialize`.
- `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` passam a espelhar integralmente as 24 definições de `mcp/tools.mjs`.

### 2.2 Dual-License Explícita e Posicionamento
- `LICENSE` na raiz mantém a proteção de propriedade intelectual para o código do servidor/oráculo (`public_html/`) e explicita em destaque que os pacotes clientes, SDKs, servidor MCP e integrações (`python/`, `mcp/`, `sdk/`, `integrations/`, `examples/`) são licenciados sob **MIT** (coerente com `package.json`, `mcp/package.json`, `mcp/LICENSE`, `python/LICENSE` e `python/pyproject.toml`).
- `README.md` destaca o posicionamento *"The Verifiable Data and x402 Payment Layer for Autonomous AI Agents"* logo na abertura e adiciona tabela clara de licenciamento por diretório.

### 2.3 Observabilidade Estruturada Zero-PII (`public_html/logger.php` + `X-Request-Id`)
- **Correlation ID (`X-Request-Id`):**
  - Função `grid402_get_request_id()` lê `HTTP_X_REQUEST_ID` se corresponder a `/^[a-zA-Z0-9._-]{1,64}$/` ou gera `g402_` + 16 hex bytes aleatórios.
  - O cabeçalho `X-Request-Id: <id>` é enviado em todas as respostas da API (`/status`, `/api/v1/*`, erros `402`/`403`/`404`/`429`, Mercado Pago e `/api/v1/verify`).
- **Log JSONL com Rotação e Zero-PII:**
  - Arquivo destino: `GRID402_FILE_REQUEST_LOGS` (padrão `GRID402_STORAGE_DIR . '/requests.jsonl'`), dentro de `private_data/` (protegido contra leitura web via `.htaccess`).
  - Schema por linha JSON:
    ```json
    {"ts":"2026-11-16T07:50:00Z","request_id":"g402_...","method":"GET","service":"forex","status":200,"latency_ms":12.4,"payment_source":"session_pass","ip_hmac":"a1b2c3d4e5f6","idempotent_replay":false,"error":null}
    ```
  - **Zero-PII estrito:** `ip_hmac` guarda apenas os 12 primeiros hex do HMAC-SHA256 com `GRID402_CRON_SECRET`. Tokens `pass_g402_...`, tx hashes completos e cabeçalhos brutos `X-Payment`/`PAYMENT-SIGNATURE` nunca são gravados em `requests.jsonl`.
  - **Rotação automática:** Ao escrever, adquire `flock(LOCK_EX)`. Se `filesize >= 10 * 1024 * 1024` (10 MB), move `requests.jsonl` para `requests.jsonl.1` (sobrescrevendo o `.1` anterior para manter teto de ~20 MB em shared hosting) e escreve a nova linha.
- **Latência `p95` Agregada (`metrics.php` + `/admin` + `/trust`):**
  - Além do acumulador médio, `metrics.json` mantém um buffer circular enxuto (`latency_samples_ms`, últimas `200` amostras numéricas float arredondadas a 2 casas) protegido sob o mesmo `flock` atômico de `grid402_record_telemetry()`.
  - Um helper `grid402_calculate_percentile(array $samples, float $pct = 95.0): float` calcula `p50`/`p95`/`p99` em O(N log N) sobre no máximo 200 floats (< 0.1 ms), evitando ter que ler arquivos JSONL de 10 MB a cada carregamento do `/trust` ou `/admin`!

### 2.4 Suíte de Carga e Concorrência Paralela (`tests/test_concurrency_and_load.php`)
- Sobe um servidor PHP embutido local (ou usa múltiplos sub-processos CLI via `proc_open` no Windows/Linux, evitando bloqueio single-thread caso PHP built-in server rode em 1 worker; como PHP CLI `-S` com `PHP_CLI_SERVER_WORKERS=4` no Linux ou múltiplos workers PHP CLI rodando concorrentemente sobre o mesmo arquivo `flock`, usar **`proc_open` paralelo em CLI diretamente contra `grid402_consume_session_pass()`, `grid402_claim_transaction_lock()` e `grid402_claim_nonce()`** + benchmark HTTP multi-conector via `curl_multi_*` garante teste real de race condition multi-processo inclusive no Windows!).
- Cenários verificados:
  1. **Last-credit Session Pass race:** Passe com `1` crédito restante disparado por `12` processos PHP concorrentes -> exatamente `1` recebe `valid=true` (`credits_remaining=0`), `11` recebem erro (`PassExhausted`), saldo final no disco é `0` (jamais negativo).
  2. **Concurrent tx_hash & nonce replay race:** Mesmo `tx_hash` e mesmo `payer:nonce` disputados simultaneamente por `10` processos PHP -> exatamente `1` adquire o lock (`true`) e `9` são bloqueados (`false`).
  3. **Benchmark de carga (`requests.jsonl` + `/status` + `/api/v1/pass/balance`):** Executa rajada de chamadas concurrentes via `curl_multi` contra servidor local e calcula `p50`, `p95`, `p99` e req/s.
