# Submission pack — Glama & mcp.so (você preenche; eu não posso submeter por você)
> Notas (2026-11-14): PulseMCP — submissões pausadas. Recall-Kitchen — exige MCP streamable-HTTP público. Glama e mcp.so — exigem ação manual sua no formulário.

---

## Glama (glama.ai — “Add your server”)
URL: https://glama.ai/mcp/servers/submit — na página inicial, botão “Add your server” (exige conta).

**Servidor:** `grid402-mcp`
**Repo:** https://github.com/oliveiratc/Grid402
**npm:** https://www.npmjs.com/package/grid402-mcp
**PyPI (SDK):** https://pypi.org/project/grid402
**URL produção:** https://grid402.online
**Descrição curta:**
Grid402 is an x402 pay-per-call API hub for AI agents: Brazilian public data (CNPJ, CEP/IBGE, CEIS/CNEP sanctions, ANBIMA banking calendar/workdays, Simples Nacional, Compliance 360, LEI, OFAC) plus network utilities (DNS, SSL, ASN, forex, crypto, indicators). No signup — agents pay per call in USDC on Base Mainnet, responses carry ECDSA oracle proofs verifiable offline. Free sandbox: `X-Payment: demo` (30 req/h). Session Pass for <15ms calls.
**Type:** MCP Server (stdio via `npx grid402-mcp`)
**Install:** `npx grid402-mcp`
**Transport:** stdio (open-source npm package)
**Custo:** pago por chamada via x402 (USDC on Base); sandbox grátis; verify e summary gratuitos.

---

## mcp.so (mcp.so/submit — exige conta; NÃO pagar os $39)
URL: https://mcp.so/submit?type=server

**Repository URL:** https://github.com/oliveiratc/Grid402
**Name:** grid402-mcp
**Description:**
Brazilian public data & network utilities for AI agents, paid per call via x402 (USDC on Base). CNPJ, Simples Nacional, CEIS/CNEP sanctions, CEP/IBGE, ANBIMA banking calendar/workdays, Compliance 360, LEI, OFAC — plus DNS/SSL/ASN, forex, crypto, indicators. No signup or API keys. Free sandbox included.
**Category:** Data APIs / Public Data (ou "Brazilian Data")
**Install:** `npx grid402-mcp`
**Hosted:** não (stdio npm) — mcp.so aceita repos como fonte.

---

## Status (2026-11-14)
- xpaysh/awesome-x402 → PR #2222 aberto (aguardando review)
- PulseMCP → submissões pausadas pela direção
- Glama → formulário “Add your server” (ação manual sua)
- mcp.so → formulário “Submit a server” (ação manual sua)
- Recall-Kitchen → bloqueado sem MCP streamable-HTTP público (a construir)
- PayAPI Market → exige que o curador pague/teste (fora do nosso controle)
