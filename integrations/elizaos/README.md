# @grid402/plugin-elizaos // Official ElizaOS Plugin for Grid402

Official [ElizaOS](https://github.com/elizaos/eliza) plugin for **Grid402**: the autonomous open data and network utility hub on **Base Mainnet (8453)** powered by the HTTP x402 micropayment protocol.

Equip your AI agents with real-time financial market tickers, global corporate identity, OFAC/AML sanctions screening, and Brazilian government records with zero API keys or monthly subscriptions.

---

## ⚡ Features & Actions Included

| Action | Description | Settlement / Cost |
|---|---|---|
| `GET_FOREX_RATE` | Real-time commercial exchange rates for USD, EUR, and GBP to BRL | 0.0005 USDC (or Session Pass) |
| `GET_CRYPTO_PRICE` | Spot market tickers and 24h variation for BTC, ETH, SOL, USDC, BASE | 0.0005 USDC (or Session Pass) |
| `GET_GLOBAL_MARKETS` | ECB reference rates (30+ currencies) and stock exchange holiday status (NYSE/LSE) | 0.0005 USDC (or Session Pass) |
| `CHECK_SANCTIONS_OFAC` | Real-time AML compliance check against US Treasury OFAC SDN & UN lists | 0.0020 USDC (or Session Pass) |
| `LOOKUP_LEI` | GLEIF Legal Entity Identifier (ISO 17442) verification across 200+ countries | 0.0020 USDC (or Session Pass) |
| `LOOKUP_CNPJ` | Brazilian Federal Revenue corporate registry, status, CNAE, and partners | 0.0020 USDC (or Session Pass) |

---

## 📦 Installation

```bash
npm install @grid402/plugin-elizaos
```

Or install locally in your Eliza project:

```bash
pnpm add ./integrations/elizaos
```

---

## 🚀 Quick Setup in Your Eliza Character

Add `grid402Plugin` to your character JSON or TypeScript configuration:

```typescript
import { grid402Plugin } from "@grid402/plugin-elizaos";

export const myAgentCharacter = {
  name: "DeFiMarketAnalyst",
  plugins: [grid402Plugin],
  settings: {
    secrets: {
      // 1-Click Top-Up Pass Token from https://grid402.online/#topup
      GRID402_PASS_TOKEN: "pass_g402_live..._..."
    }
  }
};
```

---

## 🔑 Environment Variables

| Variable | Description | Default |
|---|---|---|
| `GRID402_PASS_TOKEN` | Pre-paid Session Pass token for fast-track queries (&lt;15ms latency). Buy passes at [grid402.online/#topup](https://grid402.online/#topup). | *Optional* |
| `GRID402_SANDBOX` | Set to `true` to enable free testing mode (up to 20 reqs/hour per IP). | `false` |
| `GRID402_BASE_URL` | Base API URL. | `https://grid402.online` |

---

## 💡 How Session Passes Work (Zero Friction M2M)

Instead of signing an on-chain transaction for every single HTTP GET request:
1. Top up **2 USDC, 5 USDC, or 20 USDC** once at [https://grid402.online/#topup](https://grid402.online/#topup).
2. Receive your `pass_g402_live...` token (4,000 to 50,000 requests).
3. Provide the token in `GRID402_PASS_TOKEN`. Your Eliza agent will execute instant queries with &lt;15ms response times.

---

## 📜 License & Compliance

MIT License. All open data records provided by Grid402 comply strictly with public domain directives: CC0 1.0 Universal (GLEIF), 17 U.S.C. § 105 (US OFAC Public Domain), and EU Open Data Directive 2019/1024.

