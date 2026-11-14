# Grid402 // Base Ecosystem Grants & Coinbase Developer Platform (CDP) Dossier

> **Executive Application Package & Technical Pitch for Base Foundation Grants & CDP Agentic Commerce**  
> **Project Name:** Grid402  
> **Canonical Domain:** [https://grid402.online](https://grid402.online)  
> **Network:** Base Mainnet (Chain ID 8453)  
> **Settlement Asset:** USDC (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`)  
> **Oracle Wallet:** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`  
> **License:** MIT (Core Software) / CC0 1.0 Universal (Open Data)  
> **Contact:** `contato@grid402.online`

---

## 1. Executive Summary & Vision

Autonomous AI agents (ElizaOS, CrewAI, LangChain, Coinbase AgentKit) require access to real-world verified data: financial market tickers, global corporate identities, AML/OFAC sanctions compliance, and statutory network utilities.

Historically, traditional Web2 data providers force agents into human-centric models: mandatory credit card subscriptions, manual KYC onboarding, high minimum monthly commitments ($500+/mo), and brittle API keys.

**Grid402 solves this fundamentally:**
Grid402 is an autonomous, Zero-DB, high-performance public data and network intelligence oracle on **Base Mainnet**. Operating natively under the **HTTP x402 Payment Required** standard, it allows machine-to-machine (M2M) systems and AI agents to pay on-demand via micro-transactions in USDC ($0.0005 to $0.0020 per query) or via pre-paid batch **Session Passes** ($2 to $20 USDC) with instant sub-15ms latency.

---

## 2. Key Metrics & Live Traction

| Metric | Status / Value | Verification / Link |
|---|---|---|
| **Live Microservices** | **19 Active Endpoints** (Forex, Crypto, GLEIF LEI, OFAC SDN, ECDSA Notarization, ECB Markets, CNPJ, DNS, SSL, ASN, etc.) | `GET https://grid402.online/status` |
| **Blockchain Settlement** | Base Mainnet (EVM 8453) in USDC | On-chain verification via Base RPC |
| **Settlement Latency** | &lt;15ms (Session Pass) / Instant On-Chain verification | Benchmark verified in CI test suites |
| **Database Architecture** | **Zero-DB**: 100% stateless atomic memory & file-locked ledgers (`flock`) | Zero SQL overhead, infinite resilience |
| **Agent Discovery** | `llms.txt`, `llms-full.txt`, `/.well-known/mcp.json`, `/.well-known/ai-plugin.json` | Compliance with llmstxt.org |
| **Framework Integrations** | **Coinbase AgentKit** (`integrations/agentkit/`), **ElizaOS** (`integrations/elizaos/`), **Model Context Protocol (MCP)** via Smithery | Published SDKs in TypeScript & Python |

---

## 3. Why Base & Coinbase CDP?

1. **Natively Aligned with the Base Mission:** Base is onboarding the world on-chain, and autonomous agents are the fastest-growing cohort of on-chain economic actors. Grid402 turns Base into the universal clearinghouse for agent data queries.
2. **Coinbase AgentKit Synergy:** We provide ready-to-run action providers for CDP agents to seamlessly query compliance and market data without human intervention.
3. **Coinbase Smart Wallet & Passkey Integration:** Developers and users can top up balances in 1 click using biometric passkeys on Base without seed phrases.

---

## 4. Grant Ask & Allocation Breakdown

**Requested Grant:** **$10,000 to $25,000 USDC on Base**  
**Oracle Receiving Wallet:** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`

### Milestone Roadmap:
- **Milestone 1 (Months 1–2): ElizaOS Ecosystem Liquidity & Free Agent Tier Subsidies**
  - Subsidize 1,000,000 free x402 queries on Base for open-source AI agents built on ElizaOS and Coinbase AgentKit.
- **Milestone 2 (Months 2–3): EIP-712 Gasless Permit & Micro-Stream Settlement**
  - Upgrade x402 settlement to support EIP-712 gasless authorizations (`transferWithAuthorization`) and Coinbase Smart Wallet batch calls.
- **Milestone 3 (Months 3–4): On-Chain Attestation EAS (Ethereum Attestation Service)**
  - Integrate EAS on Base so that cryptographic oracle proofs (`_oracle_proof`) are recorded as on-chain attestations for smart contract consumption.

---

## 5. Ready-to-Submit Official Form Answers

### A. Base Ecosystem Grants Application Form

* **Project Name:** Grid402
* **Website / Repo:** `https://grid402.online` / `https://github.com/oliveiratc/Grid402`
* **Short Description (Tagline):**
  > Autonomous open-data & network utility hub powering AI agents on Base with native HTTP x402 USDC micropayments and sub-15ms Session Passes.
* **Problem Solved:**
  > AI agents cannot fill credit card forms or sign 12-month enterprise SaaS contracts. They need instant, permissionless data access. Grid402 provides 19 open data endpoints (forex, crypto, sanctions AML, global entity LEI, company registries) settled via USDC on Base without accounts or API keys.
* **Why Base?**
  > Base provides sub-cent gas fees and instant block finality required for real-time M2M agent transactions, and is home to the most vibrant AI agent community (AgentKit, ElizaOS, Farcaster).
* **Team & Track Record:**
  > Built with production-ready PHP/Node/TypeScript architecture, zero external database dependencies, automated CI pipelines, and audited ECDSA secp256k1 oracle signature proofs.

---

### B. Coinbase Developer Platform (CDP) / Agentic Commerce Bounties

* **Track:** Agentic Commerce & On-Chain Tools
* **Integration Details:**
  > Grid402 implements a dedicated `Grid402ActionProvider` for `@coinbase/agentkit` in both TypeScript and Python. When an agent receives an HTTP 402 challenge from Grid402, the CDP wallet provider automatically signs and transfers the USDC fee on Base, unblocking the data in real-time.
* **Live Demo Links:**
  - Landing & 1-Click Top-Up: `https://grid402.online/#topup`
  - Agent Discovery: `https://grid402.online/agents`
  - AgentKit Provider: `https://github.com/oliveiratc/Grid402/tree/main/integrations/agentkit`
  - ElizaOS Plugin: `https://github.com/oliveiratc/Grid402/tree/main/integrations/elizaos`

