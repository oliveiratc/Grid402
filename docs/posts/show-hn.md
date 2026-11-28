Show HN: Grid402: Verifiable public data for AI agents via Dual-Facilitator x402

I built a pay-per-call verifiable data and network utility hub for autonomous AI agents (31 live microservices, 30 paid x402 v2 endpoints validated 30/30 on CDP Bazaar & registered on x402scan, 33 MCP tools `v2.2.0` on npm & Healthy on Glama).

Main capabilities live in production:
- **Wave 3A (`sec-company`, `vuln-audit`, `chain-telemetry`):**
  - `GET /api/v1/sec-company` (0.0100 USDC / 2 credits): official SEC EDGAR company lookup by ticker or CIK, filterable `10-K`/`10-Q`/`8-K` filings with EDGAR links, optional `include_facts=true` US-GAAP XBRL summary protected by an 8 MB size cap (`facts_status`), 3600s Zero-DB cache, and refundable `404`/`503`.
  - `GET /api/v1/vuln-audit` (0.0050 USDC / 1 credit): deterministic package (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) and CVE/GHSA lookup combining OSV.dev + CISA KEV (`actively_exploited_kev`, `fixed_in`, CVSS) with verdict (`CLEAN` to `CRITICAL_KEV`) and 900s Zero-DB cache.
  - `GET /api/v1/chain-telemetry` (0.0050 USDC / 1 credit): live block/slot, gas/priority fees, RPC latency, and DeFiLlama TVL across Base, Ethereum, Polygon, Arbitrum, and Solana with multi-RPC fallback and graceful `partial_data` degradation (60s cache).
- **Phase 2 (`token-safety`, `prediction-markets`, `POST /api/v1/batch`):**
  - `GET /api/v1/token-safety` (0.0150 USDC / 3 credits): deterministic multi-chain token security & DEX liquidity audit across Base, Ethereum, Polygon, Arbitrum, and Solana (auto-detected Base58) combining GoPlus Security + DexScreener with 0–100 score and verdict (`SAFE` / `CAUTION` / `HIGH_RISK` / `CRITICAL`).
  - `GET /api/v1/prediction-markets` (0.0100 USDC / 2 credits): normalized Polymarket Gamma + Kalshi v2 aggregator returning 0–100% probabilities, 24h volume, liquidity, and end dates with graceful fallback.
  - `POST /api/v1/batch`: execute 1–5 sub-requests (including Wave 3A endpoints) in one HTTP/MCP call and one exact-sum x402 settlement (or Session Pass debit with automatic credit refunds for failed sub-items and `Idempotency-Key`).
- **Dual-Facilitator x402 v2 routing:** Coinbase CDP as primary facilitator on Base (full CDP Bazaar v2 compliance) with safe pre-settle (`/verify`) fallback to PayAI and a strict anti-double-charge state machine on `/settle`. Gasless USDC across Base, Polygon, Arbitrum, and Solana (SPL USDC) via dynamic `/.well-known/x402.json`, plus direct 6-chain transfers, Pix/Card, and RapidAPI.
- **Full 4-block official sanctions + multi-asset AML (`/api/v1/ofac` & `/api/v1/kyb-audit`):** daily-synced Zero-DB sharded JSON index (`<= 30ms`, streaming XMLReader parser, no SQLite) covering US Treasury OFAC SDN, UN Security Council, EU FSFi, and UK OFSI across EVM, Solana, BTC, and Tron addresses plus entity names/aliases. Fail-closed before settlement (`HTTP 503` if unavailable — zero false `CLEAR`).
- **100% transient Web-to-Markdown (`/api/v1/extract`, 0.0050 USDC):** zero bytes of third-party page content stored on disk (`transient_only: true`), SSRF and DNS-rebinding hardened, honoring `robots.txt` (RFC 9309), `X-Robots-Tag` (`noai`), `Content-Signal`, anti-AI meta tags, and blocking paywalls / HTTP 401/403 before charging.
- **Dossier 360° KYB + AI Verdict (`GET /api/v1/dossier`, 0.0500 USDC):** single-call Brazilian corporate KYB dossier (Federal Revenue CNPJ + Simples Nacional/MEI + CGU CEIS/CNEP sanctions) with a deterministic 0–100 score and AI Verdict (`APPROVED` / `FLAGGED` / `REJECTED`), strictly Zero-PII.

Try free right now (3 req/day sandbox, no wallet needed, or coupon `MCPTRY50` for 50 free credits):

    curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"

Pricing: `0.0050`–`0.0500` USDC per call (`1 credit = 0.0050 USDC`), or `<15ms` prepaid Session Passes (Micro: R$ 4.90 = 50 credits / 90d; Starter: 2 USDC = 400 credits / 180d; Pro: 5 USDC = 1,000 base + 200 bonus = 1,200 credits / 180d; Fleet: 20 USDC = 4,000 base + 2,000 bonus = 6,000 credits / 365d) with auto-refund on any `HTTP != 200`, free 300s `Idempotency-Key` retries, Spend Controls (`daily_credit_limit`, `allowed_endpoints`), and Zero-PII CSV/JSON statements. All paid/sandbox responses include offline-verifiable ECDSA secp256k1 `_oracle_proof` + `_meta`. `/status`, `/api/v1/verify`, `/api/v1/pass/balance`, and `/api/v1/pass/statement` are free.

- MCP server (stdio): `npx -y grid402-mcp` | Remote MCP (streamable HTTP): `https://grid402.online/mcp`
- SDKs & Frameworks: `pip install grid402`, `npm install grid402`, Coinbase AgentKit, ElizaOS, LangChain/CrewAI quickstart
- Repo: https://github.com/oliveiratc/Grid402 | Portal: https://grid402.online | Trust Center: https://grid402.online/trust

I'm the solo maintainer (based in Brazil). Happy to discuss Dual-Facilitator x402 settlement invariants, Zero-DB `flock` concurrency and SEC XBRL memory caps in PHP, or M2M batching with partial auto-refunds.
