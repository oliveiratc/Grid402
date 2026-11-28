# Cast 1 — /base (Wave 3A: SEC EDGAR, Vuln Audit & Chain Telemetry + 31 APIs / 33 MCP Tools)
> Wave 3A is LIVE on **Grid402** (`https://grid402.online`) — now **31 live M2M APIs**, **30 paid x402 v2 resources (x402scan + CDP Bazaar)** & **33 MCP tools (`v2.2.0` on npm & Glama)** ⚡
>
> • `GET /api/v1/sec-company` ($0.0100): official SEC EDGAR company lookup, 10-K/10-Q/8-K filings & optional US-GAAP XBRL facts (8 MB safety cap)
> • `GET /api/v1/vuln-audit` ($0.0050): OSV.dev + CISA KEV active-exploitation screening across 8 package ecosystems or CVE/GHSA IDs (`CLEAN` to `CRITICAL_KEV`)
> • `GET /api/v1/chain-telemetry` ($0.0050): live block/slot, gas/priority fees, RPC latency & DeFiLlama TVL across Base, ETH, Polygon, Arbitrum & Solana
>
> All 3 work inside `POST /api/v1/batch` and carry ECDSA `_oracle_proof`.
>
> Try free (3 req/day or `MCPTRY50`): `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`

# Cast 2 — /ai-agents (MCP v2.2.0 — 33 Tools + DevSecOps + Financial Research)
> Coding and research agents shouldn't juggle 5 API keys just to check CVEs, SEC filings, or gas fees.
>
> **Grid402 MCP v2.2.0** (33 tools, 31 live APIs — Healthy on Glama, 30/30 verified on x402scan & CDP Bazaar) just shipped Wave 3A:
> • `grid402_vuln_audit`: OSV.dev + CISA KEV across 8 package ecosystems (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) or `CVE`/`GHSA` IDs
> • `grid402_sec_company`: SEC EDGAR ticker/CIK filings + optional US-GAAP facts
> • `grid402_chain_telemetry`: live RPC + DeFiLlama TVL across 5 chains
> • Plus `grid402_batch` (`1–5` calls in 1 x402 settlement), Token Safety, Prediction Markets & 4-block sanctions
>
> `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp`

# Cast 3 — /solana (Solana SPL USDC x402 + Chain Telemetry + Token Safety + Batch)
> Solana AI & DeFi agents can now query **Chain Telemetry**, **Token Safety**, **Vuln Audit**, **SEC EDGAR**, **Prediction Markets**, and **Batches** natively in **SPL USDC** on **Grid402** ⚡
>
> 31 live APIs · 33 MCP tools (`v2.2.0`):
> • `/api/v1/chain-telemetry?chain=solana` ($0.0050): live slot, priority fees, RPC latency & DeFiLlama TVL
> • `/api/v1/token-safety` ($0.0150): auto-detects Base58 Solana tokens + EVM chains (GoPlus + DexScreener)
> • `POST /api/v1/batch`: 1–5 calls in 1 payment + 4-block OFAC/UN/EU/UK sanctions + 100% transient `/extract`
>
> Free sandbox: `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/chain-telemetry?chain=solana"`

# Cast 4 — PT-BR (/brasil /dev)
> Onda 3A no ar no **Grid402** 🇧🇷⚡ Agora são **31 serviços UP**, **30 rotas pagas no x402scan** e **33 tools MCP (`v2.2.0` no npm e Glama)**:
>
> • `GET /api/v1/sec-company` (`0,0100 USDC`): SEC EDGAR por ticker/CIK (`10-K`/`10-Q`/`8-K` + US-GAAP opcional)
> • `GET /api/v1/vuln-audit` (`0,0050 USDC`): OSV.dev + CISA KEV em 8 ecossistemas ou `CVE`/`GHSA`
> • `GET /api/v1/chain-telemetry` (`0,0050 USDC`): bloco/slot, gás, latência RPC e TVL DeFiLlama em 5 redes
>
> Os 3 funcionam dentro do `POST /api/v1/batch`. Teste grátis (3 req/dia ou `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20"`
> https://grid402.online/catalog
