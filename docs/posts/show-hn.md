Show HN: Grid402 – Verifiable public data for AI agents, paid via Multi-Chain x402 (5 EVM + Solana USDC)

I built a pay-per-call verifiable data and network utility hub for autonomous AI agents (23 microservices, 25 MCP tools — Grade A on mcprush, verified on Glama, x402scan, and PayAPI Market).

Main focus is public corporate/compliance registries and M2M utilities:
- Flagship `GET /api/v1/dossier` (0.0500 USDC): single-call Brazilian corporate KYB dossier (Federal Revenue CNPJ + Simples Nacional/MEI + CGU CEIS/CNEP sanctions) with a deterministic 0–100 score and AI Verdict (`APPROVED` / `FLAGGED` / `REJECTED`), strictly Zero-PII
- Global KYB & Sanctions: GLEIF LEI (200+ jurisdictions, CC0), US Treasury OFAC SDN / UN + Base Web3 AML, composite `/api/v1/kyb-audit`
- Financial & Network Utilities: CEP/IBGE, ANBIMA banking holidays & workdays, ECB 30+ FX & world exchange calendars, BCB indicators, DNS/SSL/ASN/domain-audit, and ECDSA SHA-256 timestamp notarization

No signup, no API keys, no contracts — an HTTP 402 response carries the exact price, you pay USDC across **6 chains** (Base, Arbitrum, Polygon, Optimism, Ethereum, or **Solana SPL USDC** — zero bridge fees), and every paid/sandbox response includes an offline-verifiable ECDSA secp256k1 `_oracle_proof`.

Example (free 5 req/hour sandbox, no wallet needed):

    curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"

Pricing: `0.0050`–`0.0500` USDC per call (`1 credit = 0.0050 USDC`), or `<15ms` prepaid Session Passes (Starter: 2 USDC = 400 credits; Pro: 5 USDC = 1,000 credits; Fleet: 20 USDC = 4,000 base + 2,000 bonus = 6,000 credits) with `Idempotency-Key` and Spend Controls (`daily_credit_limit`, `allowed_endpoints`). `/status`, `/api/v1/verify`, and `/api/v1/pass/balance` are free.

- MCP server (stdio): `npx -y grid402-mcp` | Remote MCP (streamable HTTP): `https://grid402.online/mcp`
- SDKs & Frameworks: `pip install grid402`, `npm install grid402`, Coinbase AgentKit, ElizaOS, LangChain/CrewAI quickstart
- Repo: https://github.com/oliveiratc/Grid402 | Portal: https://grid402.online

I'm the solo maintainer (based in Brazil). Happy to answer questions about HTTP x402, Multi-Chain EVM + Solana verification without a database, or ECDSA oracle proofs — the whole point is that agents stop scraping fragile portals and start buying verifiable data autonomously.
