# Design Técnico: Fase 2 — Token Safety, Prediction Markets e Batch M2M

## 1. Tabela de Serviços e Tarifas (`v2_5000`: 1 crédito = 0.0050 USDC = 5000 raw units)

| Slug | Método / Rota | Preço USDC | Raw Units | Créditos | Fontes Upstream Públicas | Cache Zero-DB |
|---|---|---|---|---|---|---|
| `token-safety` | `GET /api/v1/token-safety` | `0.0150` | `15000` | `3` | GoPlus Security API + DexScreener Public API | `300s` |
| `prediction-markets` | `GET /api/v1/prediction-markets` | `0.0100` | `10000` | `2` | Polymarket Gamma API + Kalshi Public Trade API v2 | `120s` |
| `batch` | `POST /api/v1/batch` | Dinâmico (soma dos itens; `0.0050` unitário base no catálogo) | Soma exata (`5000`..`250000`) | Soma exata (`1`..`50`) | Roteador interno dos micro-serviços Grid402 (1..5 itens) | Por sub-serviço / Idempotency `300s` |

---

## 2. Contrato de `GET /api/v1/token-safety` (`public_html/services/token_safety.php`)

- **Parâmetros de entrada:**
  - `address` (ou alias `token`): obrigatório. Aceita EVM (`0x[a-fA-F0-9]{40}`) ou Solana Base58 (`^[1-9A-HJ-NP-Za-km-z]{32,44}$`).
  - `chain` (opcional): `base` (8453), `ethereum` (1), `polygon` (137), `arbitrum` (42161), `solana`. Se omitido e endereço começar com `0x`, assume `base`; se Base58, assume `solana`. Se `chain` conflitar com o formato do endereço ou não for suportado → retorna **HTTP 400** pré-cobrança.
- **Upstreams 100% públicos (sem API key, timeout <= 4s):**
  - EVM GoPlus: `https://api.gopluslabs.io/api/v1/token_security/{chainId}?contract_addresses={address}`
  - Solana GoPlus: `https://api.gopluslabs.io/api/v1/solana/token_security?contract_addresses={address}`
  - DexScreener (EVM e Solana): `https://api.dexscreener.com/latest/dex/tokens/{address}`
  - Injeção de transporte mock opcional via `$GLOBALS['grid402_token_safety_http_override']` para testes 100% determinísticos offline.
- **Modelo de Risco Determinístico (`0–100`, maior = mais seguro):**
  - Penalidades objetivas:
    - `is_honeypot === true` ou `cannot_sell_all === true` → força veredito `CRITICAL` (score `<= 15`).
    - `sell_tax > 25%` ou `buy_tax > 25%` → `-35` pts (`HIGH_RISK`/`CRITICAL`).
    - `sell_tax > 5%` ou `buy_tax > 5%` → `-15` pts.
    - `is_mintable === true` → `-15` pts.
    - `is_proxy === true` ou `owner_change_balance === true` → `-20` pts.
    - `top10_holders_pct > 50%` → `-15` pts.
    - `liquidity_usd < 10000` (quando informado pelo DexScreener) → `-15` pts.
  - Faixas de veredito: `>= 80` → `SAFE`; `55..79` → `CAUTION`; `25..54` → `HIGH_RISK`; `0..24` ou honeypot → `CRITICAL`.
- **Degradação e Fail-Closed:**
  - Se ao menos uma fonte (GoPlus ou DexScreener) responder com dados válidos, normaliza e retorna HTTP `200` com `sources_status` transparente.
  - Se ambas falharem (erro de rede/HTTP ou token desconhecido sem metadados em nenhuma fonte), retorna HTTP `503` (ou `404` se confirmado inexistente) **antes de cobrar / com estorno automático no Session Pass**.
- **Saída JSON assinada (`_meta` + `_oracle_proof`):**
  - Inclui `chain`, `chain_id`, `address`, `token_name`, `token_symbol`, `safety_score`, `verdict`, `flags`, `taxes`, `market` (`liquidity_usd`, `price_usd`, `volume_24h_usd`, `dex_pair_count`), `sources_status`, `legal_disclaimer` e `cached`.

---

## 3. Contrato de `GET /api/v1/prediction-markets` (`public_html/services/prediction_markets.php`)

- **Parâmetros de entrada:**
  - `q` (opcional, max `120` chars): filtro textual case-insensitive em título/slug/tags.
  - `source` (opcional): `all` (padrão), `polymarket`, `kalshi`.
  - `status` (opcional): `active` (padrão) ou `closed`.
  - `limit` (opcional): inteiro `1..20` (padrão `10`).
- **Upstreams 100% públicos (timeout <= 4s, cache Zero-DB `120s`):**
  - Polymarket Gamma: `https://gamma-api.polymarket.com/markets?active={true|false}&closed={false|true}&limit=50`
  - Kalshi Public Trade API v2: `https://api.elections.kalshi.com/trade-api/v2/markets?status={open|closed}&limit=50`
  - Mock HTTP opcional via `$GLOBALS['grid402_prediction_markets_http_override']` para testes offline.
- **Normalização Canônica:**
  - Cada item em `markets[]`: `id`, `source` (`polymarket` | `kalshi`), `title`, `slug`, `url`, `active` (bool), `outcomes` (`[{name, probability_pct}]` normalizado para `0.0–100.0`), `volume_24h_usd`, `liquidity_usd`, `end_date`.
  - Ordenação descendente por `volume_24h_usd` respeitando `limit`.
- **Degradação Graciosa:** se uma fonte falhar e a outra responder, entrega os mercados disponíveis registrando `sources_status.<fonte> = "unavailable"`. Só retorna HTTP `503` pré-cobrança se todas as fontes selecionadas falharem.

---

## 4. Contrato e Fluxo Financeiro de `POST /api/v1/batch` (`public_html/services/batch.php`)

- **Entrada JSON (`Content-Type: application/json`, limite `16 KB`):**
  ```json
  {
    "items": [
      { "id": "item_1", "service": "dns", "params": { "domain": "base.org" } },
      { "id": "item_2", "service": "token-safety", "params": { "chain": "base", "address": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913" } }
    ]
  }
  ```
  - Também aceita `GET /api/v1/batch` apenas para descoberta/desafio `402` padrão de catálogo ou diagnóstico JSON-Schema se não houver payload de execução (retornando `400` se inválido ou `402` base `5000 raw` para indexação CDP Bazaar com `inputSchema` explícito).
- **Regras de Validação Pré-Cobrança (`HTTP 400`):**
  - `items` deve ser array com `1 <= count(items) <= 5`.
  - Serviços permitidos: todos os endpoints de dados pagos do catálogo atual **exceto** `pass`, `pass-emitir`, `batch`, `verify`, `summary`.
  - Cada item herda validação básica e preço oficial de `grid402_get_utility_services()` (`raw_units = round(price_usdc * 1_000_000)`).
  - Soma exata: `totalRawUnits = sum(itemRawUnits)`, `totalCredits = sum(itemCredits)`.
- **Regra por Método de Pagamento:**
  1. **Sem pagamento:** HTTP `402` com `accepts` usando `$totalRawUnits` dinâmico + `batch_plan` (lista dos itens e custos individuais).
  2. **Idempotency-Key:** cache key baseada em `batch` + hash SHA-256 canônico dos `items` (TTL `300s`), respondendo `X-Idempotent-Replay: true` sem débito adicional.
  3. **Sandbox (`X-Payment: demo`):** cap máximo de **2 itens** no lote (`HTTP 400 SandboxBatchLimitExceeded` se `> 2`), consome 1 chamada da cota diária de sandbox por IP.
  4. **Session Pass (`pass_g402_...`):**
     - Verifica `allowed_endpoints` **para cada sub-serviço** antes de qualquer débito → `HTTP 403 PassEndpointRestricted` caso algum item seja bloqueado.
     - Debita atomicamente `$totalRawUnits` respeitando `daily_credit_limit`.
     - Executa os sub-itens via despachante interno compartilhado (`grid402_dispatch_service_call($slug, $params, $isSandbox)`), evitando loopback HTTP.
     - **Estorno automático parcial/total:** soma `$failedRawUnits` dos sub-itens cujo resultado teve falha (`!ok` ou `status >= 400`) e chama `grid402_refund_session_pass($token, $failedRawUnits, 'batch_partial_refund')` (liberando lock anterior antes de adquirir novo lock em `PassStorageInterface`). Se 100% dos itens falharem, estorna tudo e retorna HTTP `503`.
  5. **x402 Gasless (`PAYMENT-SIGNATURE`) / TxHash / RapidAPI:**
     - Pré-verifica que `$totalRawUnits` exigido foi satisfeito.
     - Executa os sub-itens; se **todos** falharem (`succeeded_count === 0`), aborta `/settle` e retorna HTTP `503` (zero cobrança); se `>= 1` suceder, liquida normalmente e retorna `200` com `results[]` detalhando `ok`, `status`, `credits_charged` e `data`/`error` por item.
- **Saída Consolidada:**
  - `batch_summary`: `{ total_items, succeeded, failed, total_credits_quoted, credits_charged, credits_refunded, total_usdc_charged }`
  - `results`: array ordenado preservando `index`, `id`, `service`, `ok`, `status`, `price_usdc`, `data` ou `error`
  - `_meta` + `_oracle_proof` ECDSA secp256k1 assinando o envelope completo.

---

## 5. Paridade de Catálogo, MCP v2.1.0 e CDP Bazaar v2

- **Catálogo (`public_html/config.php` + `public_html/collector.php`):** 28 serviços (`25` atuais + `token-safety`, `prediction-markets`, `batch`), garantindo `28/28 UP` em `summary_free.json`, `/status`, `/catalog`, `/trust` e `/admin`.
- **MCP (`v2.1.0`):** `27 + 3 = 30 tools` (`grid402_token_safety`, `grid402_prediction_markets`, `grid402_batch`), com schemas JSON validados em `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs`, `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json`.
- **CDP Bazaar v2 (`well_known_x402.php` + `scripts/validate_cdp_bazaar.php`):** incluir `token-safety`, `prediction-markets` e `batch` com descrições técnicas `<= 500` chars (sem `&`) e `inputSchema`/`outputSchema` 100% *inlined* sem `$ref`, alcançando **27/27 endpoints pagos aprovados** (28 serviços totais menos o `/verify` gratuito).
