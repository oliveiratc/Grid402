# grid402 (Node.js & TypeScript SDK) ⚡

[![npm version](https://img.shields.io/npm/v/grid402.svg)](https://www.npmjs.com/package/grid402)
[![Base Mainnet](https://img.shields.io/badge/Network-Base%20(8453)-0052FF.svg)](https://base.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Grid402 Status](https://www.grid402.com.br/badge/system.svg)](https://www.grid402.com.br)

Official zero-dependency JavaScript/TypeScript client for the **Grid402 Telemetry Oracle** — real-time health, latency, and status monitoring for Brazilian infrastructure (SEFAZ, PIX/BACEN, payment gateways) with built-in circuit-breaker patterns and x402 on-chain micro-settlement in USDC on Base.

---

## 🚀 Key Features

* **Zero Dependencies:** Built entirely with native `fetch` (Node.js 18+ and modern browsers).
* **TypeScript Native:** Full type definitions (`grid402.d.ts`).
* **Circuit Breaker Pattern (`guard`):** Automatically protects backend jobs and payment pipelines from cascading timeouts.
* **x402 Protocol Support:** Autonomous handshakes on Base Mainnet ($0.0005 USDC per query).

---

## 📦 Installation

```bash
npm install grid402
```

---

## ⚡ Quick Start

### 1. Free Public Health Check
```typescript
import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// Overall infrastructure status
const summary = await client.getSummary();
console.log('Status geral:', summary.system_status); // UP / DEGRADED / DOWN

// Quick boolean checks
if (await client.isHealthy('SEFAZ')) {
  console.log('Autorizadores fiscais operantes!');
}
```

### 2. Circuit Breaker (`guard`)
```typescript
const result = await client.guard(
  'Gateways',
  async () => {
    // Process payment batch
    return await processCreditCardBatch();
  },
  async () => {
    // Fallback if gateways are degraded
    return await routeToBackupProvider();
  }
);
```

### 3. Detailed Telemetry with x402 Micropayment
```typescript
import { Grid402Client, PaymentRequiredError } from 'grid402';

const client = new Grid402Client();

try {
  const telemetry = await client.getDetailedTelemetry('0x<your_base_tx_hash>');
  console.log('Alvos monitorados:', telemetry.targets);
} catch (err) {
  if (err instanceof PaymentRequiredError) {
    console.log(`Pague ${err.priceUsdc} USDC para ${err.recipientWallet} na rede Base.`);
  }
}
```

---

## 📄 License
MIT License.

