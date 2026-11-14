# Submission pack — Glama & mcp.so (você preenche; eu não posso submeter por você)
> Notas (2026-11-14): PulseMCP — submissões pausadas. Recall-Kitchen — PR #153 aberto com o MCP remoto. MCP remoto em produção: https://grid402.online/mcp

---

## Glama (glama.ai — “Add your server”)
URL: https://glama.ai/mcp/servers/submit — na página inicial, botão “Add your server” (exige conta).

**Servidor:** `grid402-mcp`
**Repo:** https://github.com/oliveiratc/Grid402
**npm:** https://www.npmjs.com/package/grid402-mcp
**Remote MCP (streamable HTTP):** https://grid402.online/mcp
**PyPI (SDK):** https://pypi.org/project/grid402
**URL produção:** https://grid402.online
**Descrição curta:**
Grid402 is an x402 pay-per-call API hub for AI agents: Brazilian public data (CNPJ, CEP/IBGE, CEIS/CNEP sanctions, ANBIMA banking calendar/workdays, Simples Nacional, Compliance 360, LEI, OFAC) plus network utilities (DNS, SSL, ASN, forex, crypto, indicators). No signup — agents pay per call in USDC on Base Mainnet, responses carry ECDSA oracle proofs verifiable offline. Free sandbox: `X-Payment: demo` (30 req/h). Session Pass for <15ms calls.
**Type:** MCP Server — **remote, hosted** (`https://grid402.online/mcp`) + stdio (`npx grid402-mcp`)
**Transport:** streamable HTTP (POST) — anonymous `initialize`/`tools/list`; tools/call free for `grid402_summary`/`grid402_verify`, others require x402 payment.
**Custo:** pago por chamada via x402 (USDC on Base); sandbox grátis; verify e summary gratuitos.

---

## mcp.so (mcp.so/submit — exige conta; NÃO pagar os $39)
URL: https://mcp.so/submit?type=server

**Repository URL:** https://github.com/oliveiratc/Grid402
**Name:** grid402-mcp
**Description:**
Brazilian public data & network utilities for AI agents, paid per call via x402 (USDC on Base). CNPJ, Simples Nacional, CEIS/CNEP sanctions, CEP/IBGE, ANBIMA banking calendar/workdays, Compliance 360, LEI, OFAC — plus DNS/SSL/ASN, forex, crypto, indicators. No signup or API keys. Remote MCP at https://grid402.online/mcp; free sandbox included.
**Category:** Data APIs / Public Data (ou "Brazilian Data")
**Install:** `npx grid402-mcp` (stdio) ou remoto `https://grid402.online/mcp`
**Hosted:** sim, remoto público (`/mcp`), além do npm stdio.

---

## Status (2026-11-14)
- xpaysh/awesome-x402 → PR #2222 aberto (aguardando review)
- Recall-Kitchen/awesome-x402-mcp-services → PR #153 aberto (aguardando review; MCP remoto construído hoje)
- PulseMCP → submissões pausadas pela direção
- Glama → formulário “Add your server” (ação manual sua)
- mcp.so → formulário “Submit a server” (ação manual sua)
- PayAPI Market → exige que o curador pague/teste (fora do nosso controle)

## MCP remoto (produção)
- `POST https://grid402.online/mcp` — JSON-RPC 2.0 stateless, `initialize`/`tools/list` livres, tools/call free para summary/verify; 402 com x402 accepts para pagos.
- Deploy `3d8966d`; verificado ao vivo (initialize 200, tools/list 15 tools, cnpj unpaid → PaymentRequired com accepts).
