# Proposal

## Why

Com os fluxos de pagamento, checkout Pix/USD, `/dossie-kyb` e `/mcp` destravados pela Change 1 (`unblock-revenue-checkout-mcp-kyb`), o cockpit `/admin` do Grid402 ainda exibe distorções que impedem leitura fiel do funil comercial (80–92% da API misturada com crawlers de diretórios x402, bloqueios `429` somados em `total_sandbox_requests` e `payment_sources['sandbox']`, `Wallets Conectadas` contando scanners, falhas reais de pagamento soterradas em ~9h pelo tail de 256 KB de `requests.jsonl` e erros `502`/`503` do Mercado Pago invisíveis no painel). Além disso, `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP=0` mantém o endpoint premium `/api/v1/dossier` exposto a arbitragem no RapidAPI, `llms.txt` segue em português para indexadores globais de IA e respostas `402` duplicam `teaser_data` e `preview` (PRD `openspec/prds/revenue-unblock-and-real-telemetry.md`, RF12–RF18).

## What Changes

- **Separação de crawlers de diretórios e endurecimento do filtro de bots (RF12):** classifica chamadas automatizadas de crawlers/scanners de diretórios x402 separadamente de chamadas reais de API em `public_html/metrics.php` e no `/admin`; amplia `grid402_is_bot_user_agent()` (`Go-http-client`, `undici`, `node-fetch`, `axios`, `httpx`, `aiohttp`, `Scrapy`, `Java/`, `okhttp`, `Guzzle`, `ChatGPT-User`, `Claude-Web`, `Meta-ExternalAgent`, `Playwright`, `Puppeteer`); impede que requisições `Accept: text/markdown` em `/` e navegações com sessão administrativa ativa em `/admin` incrementem `total_page_views` humanos.
- **Desacoplamento de HTTP `429` do contador de Sandbox e separação de `mcp_sandbox` (RF13):** corrige `public_html/index.php`, `public_html/mcp.php` e `public_html/metrics.php` para que bloqueios HTTP `429` (`SandboxRateLimitExceeded`) não incrementem `total_sandbox_requests` nem `payment_sources['sandbox']`, ficando restritos a `failure_categories['sandbox_rate_limited']` e `status_counts['429']`, além de contabilizar o auto-trial do `/mcp` em `payment_sources['mcp_sandbox']`.
- **Métrica real de `Wallets Conectadas` vs. `Agentes/Perfis Identificados` (RF14):** separa perfis gerais M2M (`identified_profiles`) de conexões reais de carteira Web3 (`wallets_connected`) em `public_html/growth.php`, adicionando endpoint leve protegido contra spam (`POST /api/v1/growth/wallet-connect`) acionado após `eth_requestAccounts` no front-end (`index.php` e `pass.php`).
- **Ring buffer dedicado `payment_failures.json` + telemetria e alertas do Mercado Pago (RF15):** cria persistência Zero-DB `private_data/payment_failures.json` (com `flock(LOCK_EX)` e retenção mínima de 500 eventos sanitizados Zero-PII) para falhas reais de x402, Session Pass e erros `502`/`503` das rotas `/api/v1/pass/mp-*`; atualiza `logger.php` e `/admin` para ler dessa fonte e adiciona alertas automáticos em `services/admin_insights.php` quando `GRID402_MP_ACCESS_TOKEN` estiver ausente em produção ou houver falhas recentes do gateway Mercado Pago.
- **Clareza de status HTTP e reconciliação visual RapidAPI no `/admin` (RF16):** diferencia no cockpit HTTP `400` de prova inválida (`invalid_proof`) de `400` por parâmetro inválido (`bad_request`), rotula explicitamente resíduos históricos pré-24/11 sem `status_counts` e exibe indicação de faturamento externo no RapidAPI Hub quando houver chamadas pagas via `rapidapi`.
- **Proteção de margem RapidAPI no `/api/v1/dossier` (RF17):** altera o valor padrão de `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` de `0` para `15` em `public_html/config.php` (sobrescrevível via `.env`), garantindo retorno HTTP `429` a partir da 16ª consulta diária por assinante RapidAPI.
- **Tradução de `llms.txt` para inglês e limpeza de `teaser_data` no HTTP `402` (RF18):** traduz integralmente `public_html/llms.txt` para inglês alinhado à oferta vigente (`MCPTRY50`, `/mcp` auto-trial, pacotes `micro`/Starter/Pro/Fleet, Base/Polygon/Arbitrum, RapidAPI USD e `/dossie-kyb`, sem Solana ativa e sem `&`) e omite a chave duplicada `teaser_data` nos desafios `402` fora de `/api/v1/dossier`.

## Capabilities

### New Capabilities
<!-- Nenhuma nova capability isolada é introduzida; todas as mudanças evoluem capabilities existentes. -->

### Modified Capabilities
- `admin-dashboard`: separação entre chamadas reais de API e crawlers/diretórios x402, filtro ampliado de bots/markdown/sessão admin em page-views, exclusão de `429` do contador Sandbox e origem `mcp_sandbox`, distinção entre `Wallets Conectadas` reais e perfis M2M identificados, ring buffer dedicado `payment_failures.json` (500 eventos), telemetria e alertas do Mercado Pago, distinção de `400` (`invalid_proof` vs. `bad_request`), rotulagem de resíduo histórico e nota de receita externa RapidAPI.
- `x402-payment-oracle`: definição do teto diário padrão `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP=15` para consultas RapidAPI ao `/api/v1/dossier` e remoção de `teaser_data` duplicado nos desafios HTTP `402` fora de `/api/v1/dossier`.
- `agent-discovery`: tradução integral de `public_html/llms.txt` para inglês alinhada à oferta vigente (sem Solana ativa, com `MCPTRY50`, `/mcp`, pacote `micro`, RapidAPI USD e `/dossie-kyb`, sem `&`).

## Impact

- **Código afetado (exclusivo em `public_html/` e `tests/`):**
  - `public_html/config.php` (`GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` padrão `15`, constante `GRID402_FILE_PAYMENT_FAILURES`)
  - `public_html/metrics.php` (detecção ampliada de bots, exclusão de markdown/admin de page-views, classificação de crawlers de diretórios na API, desacoplamento de `429` do Sandbox e origem `mcp_sandbox`)
  - `public_html/logger.php` (gravação e leitura prioritária de `private_data/payment_failures.json` com retenção de 500 eventos Zero-PII + fallback para `requests.jsonl`)
  - `public_html/growth.php` (registro verificado de `wallet_connected` com deduplicação/rate-limit e separação entre `wallets_connected` reais e `identified_profiles`)
  - `public_html/index.php` e `public_html/pass.php` (rota `POST /api/v1/growth/wallet-connect`, ping pós-`eth_requestAccounts`, telemetria de falhas `502`/`503` em `/api/v1/pass/mp-*`, não-contagem de `text/markdown` como page-view humano e não-marcação de `429` como `sandbox` aprovado)
  - `public_html/mcp.php` (registro explícito de `payment_source = 'mcp_sandbox'` e falhas reais em `payment_failures.json`)
  - `public_html/services/admin_insights.php`, `public_html/admin.php` e `public_html/includes/admin_cockpit_view.php` (KPIs separados, alertas do Mercado Pago, tabela de falhas dedicada, distinção de `400`, resíduo histórico rotulado e nota de receita externa RapidAPI)
  - `public_html/x402.php` (omissão de `teaser_data` duplicado exceto em `/api/v1/dossier`)
  - `public_html/llms.txt` (tradução completa para inglês sem `&` e sem Solana ativa)
  - `tests/test_real_telemetry_change2.php` (nova suíte RF12–RF18) e ajustes pontuais em testes impactados
- **Fora do escopo:** pacotes externos `mcp/`, `sdk/`, `python/`, novos gateways fiat USD e ativação on-chain da Solana.
