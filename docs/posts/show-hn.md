Show HN: Grid402 – Brazilian public data for AI agents, paid via x402 (USDC on Base)

I built a pay-per-call data hub for autonomous AI agents, focused on Brazilian public data: CNPJ (tax ID) situation, Simples Nacional tax regime, CEIS/CNEP sanctions (CGU), CEP postal codes + IBGE codes, ANBIMA banking holidays and workday count, plus network utilities (DNS, SSL, ASN), forex, and crypto.

No signup, no API keys, no contracts — an HTTP 402 response carries the price, you pay USDC on Base Mainnet, and the answer comes back with an ECDSA oracle proof verifiable offline.

Example:

    curl -H "X-Payment: demo" "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"

The free tier returns a teaser + the 402 challenge; `GET /api/v1/summary` is free.

Pay per call (~0.0050 USDC for most, 0.0200 for CNPJ/LEI/OFAC, 0.0300 for Compliance 360), or a Session Pass (2 USDC = 40,000 credits ≈ 400 calls) for <15ms latency.

MCP server: `npx grid402-mcp`. Python SDK: `pip install grid402`. Then just ask your agent.

I'm the solo maintainer (based in Brazil). Happy to answer questions about x402, the payment flow, or the Brazilian public-data surfaces — the whole point is that agents stop begging for scraped data and start buying what they need.
