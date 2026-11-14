# Guia de Submissao em Marketplaces de Agentes de IA

Este guia contem todas as informacoes, descricoes, metadados e tags prontos para copiar e colar no cadastro do **Grid402** nos principais diretorios mundiais de agentes de IA e servidores MCP.

---

## 1. Agentic Market (`agentic.market`)
*Plataforma de referencia para servicos x402 e Coinbase AgentKit na rede Base.*

- **Name:** Grid402 - Brazil Public Data e Cryptographic Oracle
- **Tagline:** Brazil official economic and public data for AI agents via HTTP 402 on Base.
- **Category:** Data / Oracle / Finance
- **Base URL:** `https://grid402.online`
- **Documentation URL:** `https://grid402.online/llms.txt`
- **OpenAPI URL:** `https://grid402.online/openapi.json`
- **Settlement Protocol:** x402 (HTTP 402)
- **Settlement Network:** Base Mainnet (Chain ID 8453)
- **Settlement Asset:** USDC (Token: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`)
- **Payment Receiver:** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`
- **Pricing:**
  - Standard Calls: `0.0005 USDC` (Forex, Crypto, DNS, SSL, ASN, CEP, Indicators, Holidays, Workdays)
  - CNPJ Corporate Registry: `0.0020 USDC`
  - Summary e Oracle Verifier: `Free / 0.0000 USDC`
- **Description (English):**
  > Grid402 is an ultra-low latency (<20ms), Zero-DB open data hub and cryptographic oracle designed for autonomous AI agents. It delivers verified Brazilian public datasets including Receita Federal CNPJ corporate registry, CEP postal geolocation, official Central Bank Selic rates, CDI, IPCA inflation indexes, ANBIMA banking holidays and workdays calculators. Every response is cryptographically attested with a deterministic secp256k1 ECDSA signature (_oracle_proof) that can be verified on-chain or off-chain.
- **Tags:** `brazil`, `x402`, `base`, `oracle`, `cnpj`, `cep`, `selic`, `cdi`, `mcp`, `coinbase-agentkit`

---

## 2. PayAPI Market (`payapi.market`)
*Marketplace focado em micropagamentos pay-per-call e ferramentas para agentes Claude.*

- **API Title:** Grid402 Brazilian Public Data Hub
- **Short Summary:** Pay-per-call Brazilian government, economic and network data with cryptographic oracle proof.
- **Protocol:** x402 / HTTP 402 Payment Required
- **Auth Header:** `X-Payment` (Transaction Hash on Base Mainnet)
- **OpenAPI Schema:** `https://grid402.online/openapi.json`
- **MCP Manifest:** `https://grid402.online/.well-known/mcp.json`
- **Website:** `https://grid402.online/agents`
- **Full Description:**
  > Provides automated, friction-free access to official Brazilian datasets for AI agents without monthly subscriptions or API keys. Pay strictly per call in fractions of a cent using USDC on Base. Includes deterministic cryptographic proofs for smart contracts and autonomous audit agents.

---

## 3. Smithery.ai (`smithery.ai`)
*Diretorio oficial de servidores Model Context Protocol (MCP).*

- **MCP Package Name:** `grid402-mcp`
- **Registry:** npm (`https://www.npmjs.com/package/grid402-mcp`)
- **Transport:** stdio
- **Command:** `npx -y grid402-mcp@latest`
- **Environment Variables:**
  - `GRID402_BASE_URL`: `https://grid402.online`
  - `GRID402_PRIVATE_KEY`: *(Opcional - chave privada de carteira Base para auto-liquidacao de micropagamentos)*
- **Tools Included:**
  1. `grid402_summary` (Free system status)
  2. `grid402_forex` (Official BRL exchange rates)
  3. `grid402_crypto` (Spot prices for BTC, ETH, SOL, USDC, BASE)
  4. `grid402_dns` (DNS A, AAAA, MX, TXT records)
  5. `grid402_ssl` (TLS and SSL certificate inspector)
  6. `grid402_asn` (BGP routing and ASN info)
  7. `grid402_cep` (Brazilian postal code and IBGE)
  8. `grid402_cnpj` (Receita Federal company data)
  9. `grid402_indicators` (Selic, CDI, IPCA inflation)
  10. `grid402_holidays` (National and banking holidays)
  11. `grid402_workdays` (ANBIMA business days calculator)
  12. `grid402_verify` (Stateless oracle signature verifier)

---

## 4. Glama.ai (`glama.ai`)
*Catalogo de extensoes e integracoes para Claude Desktop e assistentes de codigo.*

- **Name:** Grid402 MCP Server
- **Repository:** `https://github.com/oliveiratc/Grid402`
- **npm Package:** `grid402-mcp`
- **Category:** Tools / Developer Tools / Financial Data
- **Description:**
  > Connect your AI assistant to Brazilian official public datasets with autonomous micropayments on Base and cryptographic oracle verification.
- **Claude Desktop Configuration:**
```json
{
  "mcpServers": {
    "grid402": {
      "command": "npx",
      "args": ["-y", "grid402-mcp@latest"],
      "env": {
        "GRID402_BASE_URL": "https://grid402.online"
      }
    }
  }
}
```

