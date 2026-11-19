# Submission & Directory Pack — Diretórios MCP e x402 (Atualizado em 2026-11-19)

> Status verificado em produção (`https://grid402.online`): **23 micro-serviços** (22 pagos + `/api/v1/verify` gratuito), **25 ferramentas MCP** (incluindo `grid402_get_dossier_360`), **Multi-Chain USDC em 6 redes** (Base, Arbitrum, Polygon, Optimism, Ethereum + Solana SPL) e Sandbox `5 req/h por IP`.

---

## Status Atual dos Diretórios

- **mcprush:** ✅ **Verificado — Grade A (25 tools)** — https://mcprush.com/to/grid402-brazil-public-data-network-oracle-mcp
- **Glama:** ✅ **Verificado (ownership confirmado via `glama.json`)** — https://glama.ai/mcp/connectors/io.github.oliveiratc/grid402
- **x402scan:** ✅ **Verified Server** — https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50
- **PayAPI Market:** ✅ **Verified** — https://payapi.market/api/grid402-brazil-public-data-network-oracle
- **mcptry:** ✅ **Listado** — https://mcptry.com/servers/io.github.oliveiratc/grid402
- **xpaysh/awesome-x402:** PR #2222 aberto
- **Recall-Kitchen/awesome-x402-mcp-services:** PR #153 aberto
- **mcp.so:** formulário manual gratuito (não pagar plano pago) — https://mcp.so/submit?type=server

---

## Ficha Canônica para Novos Diretórios (Copiar e Colar)

**Servidor / Nome:** `grid402-mcp`
**Repo:** https://github.com/oliveiratc/Grid402
**npm:** https://www.npmjs.com/package/grid402-mcp
**Remote MCP (streamable HTTP):** https://grid402.online/mcp
**PyPI (SDK):** https://pypi.org/project/grid402
**URL produção:** https://grid402.online
**Manifestos:**
- `https://grid402.online/.well-known/mcp.json`
- `https://grid402.online/.well-known/mcp/server-card.json`
- `https://grid402.online/.well-known/x402.json`
- `https://grid402.online/openapi.json`
- `https://grid402.online/llms.txt`

**Descrição curta (EN — sem `&`):**
Grid402 is a Multi-Chain x402 pay-per-call verifiable data and network oracle for AI agents (23 microservices, 25 MCP tools — Grade A on mcprush, Glama and x402scan Verified). Features flagship `/api/v1/dossier` (KYB Dossier 360 with deterministic AI Verdict APPROVED/FLAGGED/REJECTED), CNPJ, Simples/MEI, CGU CEIS/CNEP sanctions, GLEIF LEI (CC0), OFAC/UN and Web3 AML, CEP/IBGE, ANBIMA banking calendar/workdays, ECB 30+ FX, crypto spot, ECDSA timestamp notarization, and DNS/SSL/ASN/domain-audit. Settles native USDC across 6 chains (Base, Arbitrum, Polygon, Optimism, Ethereum, and Solana SPL) or BRL Pix/Card, with ECDSA secp256k1 `_oracle_proof`, Zero-PII, free sandbox (`X-Payment: demo`, 5 req/h/IP), and `<15ms` prepaid Session Passes.

**Type:** MCP Server — **remote, hosted** (`https://grid402.online/mcp`) + stdio (`npx -y grid402-mcp`)
**Transport:** Streamable HTTP (`POST https://grid402.online/mcp`) — anonymous `initialize`/`tools/list` (25 tools); `grid402_summary`, `grid402_verify`, and `grid402_pass_balance` are free; paid tools return HTTP 402 with x402 v2 accepts.
**Custo:** `0.0050`–`0.0500 USDC` por chamada (`1 crédito = 0.0050 USDC`); Sandbox grátis (`5 req/h/IP`); `summary`, `verify` e `pass/balance` gratuitos.
