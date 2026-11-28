# Guia de Submissão em Marketplaces de Agentes de IA e Diretórios MCP

Este guia contém todas as informações, descrições, metadados e tags atualizados para o **Grid402 v3.8 / MCP v2.2.0** (31 serviços, 30 recursos x402 pagos, 33 MCP tools), prontos para copiar e colar no cadastro nos principais diretórios mundiais de agentes de IA e servidores MCP.

---

## Resumo Executivo da Plataforma

- **Nome Oficial:** Grid402 - Global & LATAM Public Data Hub, KYB Oracle & Agent Utilities
- **Tagline:** Verifiable public data, corporate filings, cybersecurity audit and economic oracle for autonomous AI agents via HTTP 402 & MCP.
- **Base URL:** `https://grid402.online`
- **MCP Endpoint (Streamable HTTP):** `https://grid402.online/mcp`
- **MCP Manifest:** `https://grid402.online/.well-known/mcp.json`
- **MCP Server Card:** `https://grid402.online/.well-known/mcp/server-card.json`
- **x402 Discovery:** `https://grid402.online/.well-known/x402.json`
- **OpenAPI Schema:** `https://grid402.online/openapi.json`
- **Documentação LLM:** `https://grid402.online/llms.txt` e `https://grid402.online/llms-full.txt`
- **Settlement Networks:** Base Mainnet (Chain ID 8453), Polygon (137), Arbitrum (42161), Solana (101)
- **Settlement Asset:** USDC
- **Recebedor Oficial de Pagamentos (EVM):** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`
- **Recebedor Oficial (Solana):** `7hK48bT8LcxRsqG9F1W5LgNf3B9fP4M5m2L6q4Q3k7E`

---

## 1. Diretórios MCP (Glama, Awesome-MCP, mcp.so, PulseMCP)

### Glama.ai (`glama.ai`)
- **Status:** Healthy (`glama.ai/mcp/connectors/io.github.oliveiratc/grid402`)
- **Tools Count:** 33 tools
- **Registry:** npm (`grid402-mcp@2.2.0`)
- **Claude Desktop Configuration:**
```json
{
  "mcpServers": {
    "grid402": {
      "command": "npx",
      "args": ["-y", "grid402-mcp@2.2.0"],
      "env": {
        "GRID402_BASE_URL": "https://grid402.online"
      }
    }
  }
}
```

### Lista Completa de Ferramentas MCP (33 Tools):
1. `grid402_summary` (Free system status & catalog)
2. `grid402_forex` (Official BRL & global FX rates)
3. `grid402_crypto` (Spot prices for BTC, ETH, SOL, USDC)
4. `grid402_dns` (DNS A, AAAA, MX, TXT records)
5. `grid402_ssl` (TLS/SSL certificate inspector)
6. `grid402_asn` (BGP routing and ASN inspector)
7. `grid402_cep` (Brazilian postal code & IBGE data)
8. `grid402_cnpj` (Receita Federal company data & status)
9. `grid402_indicators` (Selic, CDI, IPCA inflation rates)
10. `grid402_holidays` (National and banking holidays)
11. `grid402_workdays` (ANBIMA business days calculator)
12. `grid402_verify` (Stateless ECDSA oracle verifier)
13. `grid402_ceis` (CGU CEIS sanctions registry)
14. `grid402_cnep` (CGU CNEP ineligibility registry)
15. `grid402_simples` (Simples Nacional & MEI tax status)
16. `grid402_compliance` (Consolidated compliance scoring)
17. `grid402_dossier` (Executive KYB Dossier generator)
18. `grid402_sanctions` (Global OFAC, UN & EU sanctions)
19. `grid402_lei` (GLEIF Legal Entity Identifier lookup)
20. `grid402_markdown` (Web-to-Markdown scraper & cleaner)
21. `grid402_email_verify` (Zero-PII email MX & syntax verification)
22. `grid402_ecb_rates` (European Central Bank official rates)
23. `grid402_whois` (TLD domain registration & WHOIS lookup)
24. `grid402_company_dossier` (Full global corporate audit)
25. `grid402_token_safety` (Token audit, honeypot check & liquidity)
26. `grid402_prediction_markets` (Prediction market probabilities)
27. `grid402_batch` (Multi-call atomic batch execution)
28. `grid402_sec_company` (SEC EDGAR CIK & 10-K/10-Q filings)
29. `grid402_vuln_audit` (NVD CVE vulnerability scanner & audit)
30. `grid402_chain_telemetry` (L1/L2 gas, TPS, block height & finality)
31. `grid402_quote` (Pre-flight cost calculator & fee estimation)
32. `grid402_rates` (Complete dynamic fee schedule & network status)
33. `grid402_catalog` (Full metadata & capabilities introspection)

---

## 2. Marketplaces x402 e Redes de Agentes (x402scan, PayAI Bazaar, Coinbase AgentKit)

### x402scan (`x402scan.com`)
- **Status:** Registrado via `registerFromOrigin` com 30 recursos (29 endpoints pay-per-call + 1 endpoint batch).
- **Saldo On-Chain na Base:** `US$ 4.7550 USDC`
- **Métricas:** 32 chamadas pagas, 9.197 requests diárias, Uptime 100%.

### PayAI Bazaar (`payai.network`)
- **Catálogo:** Sincronizado com 30 recursos.
- **Seeding Realizado:** `0.0200 USDC` em transação on-chain na Base Mainnet cobrindo a Onda 3A.

### Coinbase AgentKit
- **Repositório:** `coinbase/agentkit` (PR #1634)
- **Action Provider:** Suporte a dados públicos verificáveis e chamadas autenticadas via carteira on-chain.

---

## 3. Diretórios de APIs e Marketplaces Gerais (RapidAPI)

### RapidAPI Studio (`rapidapi.com`)
- **API Geral (31 serviços):** `grid402-open-data-kyb-ai-utilities`
- **API Global (21 serviços sem dependência regional):** `grid402-web-to-markdown-email-verify-network-utilities`
- **Especificações:** `docs/revenue/openapi/rapidapi-all.json` e `docs/revenue/openapi/rapidapi-global.json`
