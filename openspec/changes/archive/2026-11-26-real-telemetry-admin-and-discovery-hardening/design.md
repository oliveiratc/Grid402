# Design

## Context

Veja `proposal.md` (`## Why`) e `openspec/prds/revenue-unblock-and-real-telemetry.md` (RF12–RF18) para a motivação completa. Na Change 1 foram destravados os fluxos de pagamento (x402 v1/v2 PayAI, CORS, ocultação de Solana, Mercado Pago Micro/Starter, `/dossie-kyb`, `pass_storage.js` e `/mcp` auto-trial). Nesta Change 2, o foco é purificar toda a telemetria e governança operacional em `public_html/` sem quebrar a compatibilidade retroativa dos campos legados em `metrics.json`, `growth.json` e `/admin?api=stats`, mantendo arquitetura 100% Zero-DB (`flock(LOCK_EX)`) e sem dependência de `mbstring`.

## Goals / Non-Goals

**Goals:**
- Garantir que `/admin` separe claramente tráfego real vs. crawlers de diretórios x402, page-views humanos vs. bots/markdown/admin, uso real de Sandbox (`200`) vs. bloqueios `429` vs. `mcp_sandbox`, e conexões reais de wallet vs. perfis M2M identificados.
- Garantir que falhas reais de pagamento (x402, Session Pass e Mercado Pago `502`/`503`) nunca desapareçam sob enxurradas de desafios `402` no `requests.jsonl` de `256 KB`.
- Ativar alerta imediato no `/admin` se o Mercado Pago estiver sem `GRID402_MP_ACCESS_TOKEN` em produção ou sofrer falhas recentes de gateway.
- Proteger a margem do `/api/v1/dossier` no RapidAPI com teto padrão `15/dia`, traduzir `llms.txt` para inglês e eliminar `teaser_data` duplicado fora do `/dossier`.

**Non-Goals:**
- Não alterar arquivos nos pacotes externos (`mcp/`, `sdk/`, `python/`).
- Não apagar dados históricos já gravados em `metrics.json` ou `growth.json` — toda evolução é aditiva ou reclassificada em leitura/exibição.
- Não adicionar banco SQL nem alterar assinaturas `_oracle_proof` existentes.

## Decisions

### 1. Separação de Crawlers de Diretórios x402 & Endurecimento de Page-Views (RF12)
- **Decisão:** Em `public_html/metrics.php`, introduzir `grid402_is_directory_crawler_request(?string $ua, string $service, int $statusCode, ?string $proof)`:
  - Classifica como crawler/scanner de diretório chamadas de API sem prova válida (especialmente `statusCode === 402` ou `service === 'summary'`) originadas de User-Agents de crawler/HTTP CLI (`grid402_is_bot_user_agent($ua)` ampliado com `Go-http-client`, `undici`, `node-fetch`, `axios`, `httpx`, `aiohttp`, `Scrapy`, `Java/`, `okhttp`, `Guzzle`, `ChatGPT-User`, `Claude-Web`, `Meta-ExternalAgent`, `Playwright`, `Puppeteer`) ou padrões de varredura sem browser.
  - Incrementa `total_crawler_requests` e `daily[$today]['crawler_requests']` preservando `total_requests` intacto (para manter retrocompatibilidade de contratos e testes legados) e expondo `real_api_requests = max(0, total_requests - total_crawler_requests)` em `/admin?api=stats` e na interface.
  - **Page-Views:** Em `public_html/index.php` (`grid402_record_page_visit`), pular o registro quando: (a) `Accept` solicitar `text/markdown` sem `text/html` explícito antes, OU (b) houver cookie de sessão PHP ativa autenticado como operador `/admin` (ou rota `/admin`).
- **Alternativa rejeitada:** Deletar eventos de crawlers antes de salvar em `metrics.json` — descartada porque o operador ainda quer monitorar a saúde do rastreamento dos diretórios e o volume bruto histórico.

### 2. Desacoplamento de HTTP `429` do Sandbox & Separação `mcp_sandbox` (RF13)
- **Decisão:** Em `public_html/metrics.php:grid402_record_telemetry()`:
  - Só definir `$eventTrafficType = 'sandbox'` quando `$statusCode === 200` (uso efetivo de sandbox). Quando `$statusCode === 429` (e `failure_category === 'sandbox_rate_limited'` ou `type === 'rate_limited_429'`), gravar `$eventTrafficType = 'rate_limited_429'` e **não** incrementar `total_sandbox_requests` nem `payment_sources['sandbox']`/`payment_sources['mcp_sandbox']`.
  - Registrar bloqueios `429` exclusivamente em `status_counts['429']` e `failure_categories['sandbox_rate_limited']`.
  - Adicionar `'mcp_sandbox' => 0` aos defaults de `payment_sources` em `metrics.php` e `admin.php`; no auto-trial bem-sucedido (`200`) em `public_html/mcp.php`, enviar explicitamente `payment_source => 'mcp_sandbox'`. No `429` em `index.php` (e bloqueio pós-trial no `mcp.php`), enviar `type => 'rate_limited_429'`.
- **Impacto em `telemetry_paid`/`daily`:** mantido 100% compatível; `total_sandbox_requests` passa a refletir apenas execuções sandbox entregues (`200 OK`).

### 3. Métrica Real de `Wallets Conectadas` vs. `Perfis Identificados` (RF14)
- **Decisão:** Em `public_html/growth.php`:
  - Preservar todos os perfis em `growth.json['profiles']`, adicionando um campo booleano opcional `wallet_verified` (`false` por padrão para perfis passivos/402/sandbox).
  - Marcar `wallet_verified = true` apenas quando: (a) ocorrer pagamento on-chain/facilitador com `payer` EVM (`^0x[a-fA-F0-9]{40}$`) ou SVM válido, OU (b) o front-end chamar o novo endpoint `POST /api/v1/growth/wallet-connect` (em `index.php`) enviando `{"address": "0x..."}` após aprovação de `eth_requestAccounts`.
  - **Proteção anti-spam de `POST /api/v1/growth/wallet-connect`:** validar estritamente regex `^0x[a-fA-F0-9]{40}$` (rejeitando zero-address e formatos inválidos com `400`), aplicar rate-limit por IP HMAC (`private_data/wallet_connect_rates.json` sob `flock(LOCK_EX)`, máx. 10 registros/hora/IP retornando `429`), gravar apenas HMAC opaco + `wallet_last4` (Zero-PII) e deduplicar carteiras repetidas (`wallet_verified` idempotente).
  - Em `grid402_growth_metrics()`, retornar `wallets_connected` (contando exclusivamente perfis não-proxy com `wallet_verified === true`) e adicionar `identified_profiles` (total de perfis não-proxy acumulados, incluindo scanners/402), além de `new_verified_wallets_by_day` e fallback limpo no `/admin`.
- **Alternativa rejeitada:** Zerar `growth.json` — descartada porque perderia histórico de retencão/conversão de pagantes reais já liquidados (cujos perfis serão considerados verificados quando possuírem `paid_requests > 0` e `channel === 'web3'` com `wallet_last4 !== null`!).

### 4. Ring Buffer `payment_failures.json` + Telemetria/Alertas do Mercado Pago (RF15)
- **Decisão:**
  - Em `public_html/config.php`, definir constante `GRID402_FILE_PAYMENT_FAILURES` (respeitando `GRID402_ENV`/`GRID402_PRIVATE_DIR`, padrão `<private_dir>/payment_failures.json`).
  - Em `public_html/logger.php`:
    - Criar `grid402_record_payment_failure(array $event): bool` com trava exclusiva `flock(LOCK_EX)`, sanitização Zero-PII (`grid402_sanitize_log_text()` + `ip_hmac` + `proof_diag`, e opcional `grid402_mp_sanitize_error_detail` para erros MP) mantendo ring buffer de **até 500 eventos** mais recentes (`array_slice(..., -500)`).
    - Atualizar `grid402_read_recent_payment_failures(int $limit = 25)` para ler **prioritariamente** de `payment_failures.json` (mais recente primeiro), complementando com `requests.jsonl` apenas para registros anteriores que não estejam duplicados por `request_id`.
    - Gravar em `payment_failures.json` em: (1) `$emitPaymentFailure` em `index.php` quando não for `sandbox_rate_limited` (`429`); (2) falhas de prova/execução em `mcp.php`; e (3) rotas `/api/v1/pass/mp-*` em `index.php` quando `!$res['ok']` e `code >= 400` (especialmente `502`/`503` do Mercado Pago), incrementando também `failure_categories['mercadopago_gateway_error']` em `metrics.php`.
  - **Alertas em `public_html/services/admin_insights.php`:**
    - Regra `mercadopago_unconfigured` (severidade `high` quando `!$mpSandbox && $mpToken === ''` e não houver flag explícita de silenciamento em testes sem `.env`; observar cuidadosamente testes de `test_admin_dashboard.php` para não quebrar cenários existentes que simulam 0 alertas `high`!). **Nota crítica para implementação:** em `test_admin_dashboard.php`, verificar como os testes de alerta `high` são executados antes de disparar `mercadopago_unconfigured` em snapshots sintéticos — só emitir quando `$runtimeStatus['mercadopago']` for avaliado ou conforme condições explícitas em `tests/test_admin_dashboard.php`!
    - Regra `mercadopago_gateway_error` (severidade `high`) quando `failure_categories['mercadopago_gateway_error'] > 0` ou houver falha `mercadopago`/`pass-mp` `502`/`503` recente em `payment_failures.json`.

### 5. Status HTTP `400`, Resíduo Histórico & Nota Externa RapidAPI (RF16)
- **Decisão:** Em `public_html/includes/admin_cockpit_view.php` (e JS correspondente em `admin.php`):
  - Derivar `400 Prova inválida` a partir de `failure_categories['invalid_proof']` (limitado ao total `status_counts['400']`) e exibir eventual saldo restante como `400 Parâmetro inválido` (`bad_request`).
  - Renomear o resíduo `total_requests - sum(status_counts)` para `Histórico pré-24/11 (sem status)` em vez de `Outros` genérico.
  - Exibir na seção `Origem dos Pagamentos` uma nota/subtítulo explícito informando que chamadas `RapidAPI Hub` têm faturamento liquidado externamente na plataforma RapidAPI (`US$ 0,00` local).

### 6. Proteção de Margem RapidAPI + `llms.txt` EN + Limpeza `teaser_data` (RF17, RF18)
- **Decisão:**
  - `public_html/config.php`: alterar default de `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` de `0` para `15`.
  - `public_html/llms.txt`: traduzir integralmente para inglês alinhado à oferta vigente (25 serviços, USDC `0.0050`–`0.0500`, `MCPTRY50`, `/mcp` auto-trial, tiers `Micro R$ 4.90 / Starter / Pro / Fleet`, RapidAPI USD, `/dossie-kyb`, sem Solana ativa e sem `&`).
  - `public_html/x402.php`: na construção da resposta HTTP `402` (`grid402_respond_402`), preencher `$payload['teaser_data'] = $preview` **apenas** quando `strtolower((string) $service) === 'dossier'`, removendo a duplicidade nos demais endpoints (`tests/test_dossier.php` segue 100% compatível).

## Risks / Trade-offs

- **[Risco] Novas regras de alerta em `admin_insights.php` quebrarem contagem fixa de `high` alerts em `tests/test_admin_dashboard.php`** → **Mitigação:** ler `tests/test_admin_dashboard.php` no início da implementação e assegurar que snapshots de teste que já controlam estado de `.env`/runtime continuem com expectativa compatível (ou passar `mercadopago_status` via `runtimeStatus`).
- **[Risco] Uso de `header()` ou cookies em testes CLI embutidos ao checar sessão admin ou `Accept: text/markdown`** → **Mitigação:** encapsular a detecção em helper puro em `metrics.php` usando `isset($_COOKIE[session_name()])` / `$_SESSION` somente quando já iniciada e header `Accept` via parâmetro opcional para testabilidade unitária.
- **[Risco] Limite de tamanho do arquivo `payment_failures.json`** → **Mitigação:** corte estrito em `500` itens via `array_slice(..., -500)` mantém o arquivo abaixo de ~150 KB mesmo no pior caso.
