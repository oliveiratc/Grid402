# Design Técnico — Onda 3A (`sec-company`, `vuln-audit`, `chain-telemetry`)

## 1. Tabela de Precificação e Cache (Escala Oficial `v2_5000`)

| Endpoint | Serviço (`$apiParam`) | Preço USDC | Créditos | Raw Units | TTL Cache | Fontes Públicas (Zero-API-Key) |
|---|---|---:|---:|---:|---:|---|
| `GET /api/v1/sec-company` | `sec-company` | `0.0100` | `2` | `10000` | `3600s` (tickers `86400s`) | SEC EDGAR (`sec.gov` + `data.sec.gov`) |
| `GET /api/v1/vuln-audit` | `vuln-audit` | `0.0050` | `1` | `5000` | `900s` (CISA KEV `21600s`) | OSV.dev API v1 + CISA KEV Feed |
| `GET /api/v1/chain-telemetry` | `chain-telemetry` | `0.0050` | `1` | `5000` | `60s` (DeFiLlama `300s`) | RPCs públicos multi-fallback + DeFiLlama API |

Os 3 serviços permanecem elegíveis em `POST /api/v1/batch` somando exatamente seus respectivos `raw_units` (`10000`, `5000`, `5000`) e respeitando `allowed_endpoints`, `daily_credit_limit` e estorno parcial automático em Session Pass.

---

## 2. Arquitetura e Contratos dos Novos Serviços

### 2.1. `public_html/services/sec_company.php` (`grid402_get_sec_company_data`)
- **Entrada (`GET`):**
  - `ticker` (ex.: `AAPL`, `BRK.B`, normalizado maiúsculo `^[A-Z0-9.\-]{1,10}$`) **ou** `cik` (`^[0-9]{1,10}$`, normalizado com `str_pad(..., 10, '0', STR_PAD_LEFT)`). Ausência ou formato inválido de ambos resulta em **HTTP 400** pré-cobrança (`status_code: 400`).
  - `limit` (opcional, inteiro clamped `1..25`, padrão `10`).
  - `forms` (opcional, CSV ex.: `10-K,10-Q,8-K`, sanitizado contra regex `^[A-Z0-9\-\/]+$`).
  - `include_facts` (opcional, booleano `true|false|1|0`, padrão `false`).
- **Fontes e Proteção SEC EDGAR:**
  - Header institucional fixo: `User-Agent: Grid402/3.8 (contato@grid402.online)` e `Accept: application/json`.
  - Mapa ticker→CIK (`https://www.sec.gov/files/company_tickers.json`) salvo em cache compacto (`GRID402_PRIVATE_DIR/sec_tickers_cache.json`) mapeando apenas `ticker => cik10`, com TTL `86400s` e fallback para cópia em disco se a SEC falhar momentaneamente.
  - Submissions (`https://data.sec.gov/submissions/CIK{cik10}.json`): cacheado em `sec_submissions_{cik10}.json` por `3600s` (armazenando apenas o recorte resumido necessário do perfil + últimos filings para manter disco leve).
  - **Teto rígido em `include_facts=true`:** download de `https://data.sec.gov/api/xbrl/companyfacts/CIK{cik10}.json` utiliza callback cURL de progresso (`CURLOPT_PROGRESSFUNCTION`) abortando acima de **8 MB** (`8388608` bytes). Se exceder `8 MB`, retorna o perfil + filings normalmente com `facts_status: "skipped_size_limit"` e `facts: null` (sem quebrar o PHP na Hostinger). Quando dentro do limite, extrai as métricas US-GAAP mais recentes (`revenue`, `net_income`, `assets`, `liabilities`, `cash_and_equivalents`, `eps_diluted`) com `facts_status: "ok"`.
- **Saída normalizada:** `cik`, `ticker`, `tickers`, `company_name`, `sic`, `sic_description`, `exchanges`, `entity_type`, `state_of_incorporation`, `fiscal_year_end`, `filings` (array com `form`, `filing_date`, `report_date`, `accession_number`, `primary_document`, `edgar_url`), `facts_status`, `facts`, `legal_disclaimer`.

### 2.2. `public_html/services/vuln_audit.php` (`grid402_get_vuln_audit_data`)
- **Entrada (`GET`):**
  - **Modo Pacote:** `ecosystem` + `package` (e `version` opcional). Mapa de normalização para nomes canônicos do OSV.dev: `npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`, `crates.io`→`crates.io`.
  - **Modo ID Direto:** `id` (`^[A-Za-z0-9\-_.:]{4,64}$`, ex.: `CVE-2021-44228`, `GHSA-jfh8-c2jp-5v3q`).
  - Parâmetros ausentes/conflitantes ou malformados → **HTTP 400** pré-cobrança.
- **Fontes e CISA KEV:**
  - `POST https://api.osv.dev/v1/query` (Modo Pacote) ou `GET https://api.osv.dev/v1/vulns/{id}` (Modo ID). Timeout `connect=2s`, `total=5s`.
  - Feed CISA KEV (`https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json`) mantido em `GRID402_PRIVATE_DIR/cisa_kev_cache.json` com TTL `21600s` (6h). Se a CISA oscilar na renovação e existir cache prévio em disco, reutiliza o cache existente sinalizando `kev_source_status: "stale_cache"`; sem nenhum cache prévio, continua com `kev_source_status: "unavailable"` sem bloquear o retorno do OSV.
- **Veredito Determinístico:**
  - Qualquer match (ID ou alias `CVE-*`) presente no catálogo KEV → **`CRITICAL_KEV`**
  - Caso contrário, maior severidade entre os registros: `CRITICAL` (CVSS >= 9.0 ou label CRITICAL) → **`CRITICAL`**; `HIGH` (CVSS 7.0–8.9 ou HIGH) → **`HIGH`**; `MODERATE` (CVSS 4.0–6.9 ou MEDIUM/MODERATE) → **`MODERATE`**; `LOW` (qualquer outra vulnerabilidade) → **`LOW`**; zero vulnerabilidades → **`CLEAN`**.
- **Saída normalizada:** `query_mode` (`package` | `id`), `target`, `verdict`, `vulnerability_count`, `kev_match_count`, `severity_summary` (`critical`, `high`, `moderate`, `low`, `unknown`), `vulnerabilities` (limitado a 25 itens ordenados por KEV + severidade, cada item com `id`, `aliases`, `summary`, `severity`, `cvss_score`, `actively_exploited_kev`, `kev_due_date`, `fixed_in`, `references`), `kev_source_status`, `legal_disclaimer`.

### 2.3. `public_html/services/chain_telemetry.php` (`grid402_get_chain_telemetry_data`)
- **Entrada (`GET`):** `chain` opcional (padrão `base`; aceitas: `base`, `ethereum`, `polygon`, `arbitrum`, `solana`). Qualquer outro valor → **HTTP 400** pré-cobrança.
- **Fontes e Fallback:**
  - **RPCs públicos com fallback por rede:**
    - EVM (`eth_blockNumber`, `eth_gasPrice`, `eth_maxPriorityFeePerGas`):
      - `base`: `https://mainnet.base.org`, `https://base.llamarpc.com`
      - `ethereum`: `https://eth.llamarpc.com`, `https://cloudflare-eth.com`
      - `polygon`: `https://polygon-rpc.com`, `https://polygon.llamarpc.com`
      - `arbitrum`: `https://arb1.arbitrum.io/rpc`, `https://arbitrum.llamarpc.com`
    - Solana (`getSlot`, `getRecentPrioritizationFees`):
      - `solana`: `https://api.mainnet-beta.solana.com`, `https://solana-rpc.publicnode.com`
  - **DeFiLlama:** `https://api.llama.fi/v2/chains` em cache compartilhado de `300s` (`defillama_chains_cache.json`) buscando TVL em USD da cadeia selecionada.
- **Degradação Graciosa e Cache:**
  - Se RPC responder e DeFiLlama falhar (ou vice-versa), retorna **HTTP 200** com `partial_data: true` e `sources_status`.
  - Retorna **HTTP 503** pré-cobrança/estornável apenas se tanto RPC quanto DeFiLlama falharem sem cache válido.
  - Cache por rede de `60s`.

---

## 3. Injeção de Transporte HTTP para Testes Offline Determinísticos

Seguindo o padrão de testabilidade da Fase 2 (`token_safety.php` / `prediction_markets.php`), os três serviços aceitam um callable opcional `$httpTransport` como parâmetro de função ou fallback padrão cURL. Isso permite que `tests/test_phase3a_sec_vuln_telemetry.php` valide todos os caminhos (cache hit/miss, 404, cap de 8 MB em `companyfacts`, match CISA KEV, fallback de cache KEV, degradação RPC vs DeFiLlama, falha total 503 com estorno de Session Pass e sub-itens no `POST /batch`) 100% offline, determinístico e rápido.

---

## 4. Regras de Higiene de Manifestos e Testes Existentes

1. **Proibição de `&` em manifestos e READMEs:** manter zero ocorrências de `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md`, `smithery.yaml`, `public_html/llms.txt`, `public_html/llms-full.txt` e `public_html/.well-known/ai-agent.json` (`tests/test_agent_discovery.php` e `tests/test_agentkit_integration.php`).
2. **Proteção contra literal `solana` nos 6 manifestos estáticos:** conforme `tests/test_revenue_unblock_change1.php`, os arquivos estáticos `public_html/pricing.json`, `public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt`, `public_html/.well-known/ai-agent.json` e `public_html/.well-known/mcp/server-card.json` não podem conter a palavra literal `solana` (case-insensitive) — usar termos como `SPL` / `SVM` / `EVM chains` na redação desses 6 arquivos estáticos, mantendo `solana` normalmente nos endpoints PHP dinâmicos (`well_known_x402.php`, `chain_telemetry.php`).
3. **Sem `$ref` nos schemas Bazaar:** os metadados inline `extensions.bazaar` de `well_known_x402.php` e `.well-known/x402.json` usam apenas esquemas JSON inline com descrições `<= 500` caracteres (`scripts/validate_cdp_bazaar.php`).
