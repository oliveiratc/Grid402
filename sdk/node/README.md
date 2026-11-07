# grid402 (Node.js & TypeScript SDK) ⚡

[![npm version](https://img.shields.io/npm/v/grid402.svg)](https://www.npmjs.com/package/grid402)
[![Base Mainnet](https://img.shields.io/badge/Network-Base%20(8453)-0052FF.svg)](https://base.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Grid402 Status](https://www.grid402.com.br/badge/system.svg)](https://www.grid402.com.br)

SDK oficial em JavaScript/TypeScript (zero dependências externas) para o **Grid402** — Hub de Alta Performance de Utilitários de Rede e Dados Públicos com micropagamentos via protocolo **x402** na rede **Base Mainnet** (US$ 0.0005 USDC por consulta).

---

## 🚀 Funcionalidades

* **Zero Dependências:** Construído unicamente sobre a API nativa `fetch` (Node.js 18+ e navegadores modernos).
* **TypeScript Nativo:** Tipagem estrita completa via `grid402.d.ts`.
* **6 Micro-serviços Técnicos:**
  1. `getForex()`: Cotações fiduciárias em tempo real (USD, EUR, GBP para BRL).
  2. `getCrypto()`: Cotações spot de criptoativos (BTC, ETH, SOL, USDC, BASE).
  3. `getDns(domain)`: Resolução de registros RFC 1035 (A, AAAA, MX, TXT, NS, CNAME).
  4. `getSsl(host)`: Inspeção TLS na porta 443 (validade, emissor, SANs e dias restantes).
  5. `getAsn(ip)`: Metadados BGP, ASN e país para um IP.
  6. `getCep(code)`: Endereço público e código oficial IBGE do município.
* **Resumo Gratuito:** `/api/v1/summary` para monitoramento e discovery sem fricção.
* **Protocolo x402 Nativo:** Suporte a cabeçalhos `X-Payment` e tratamento de erros de desafio HTTP 402.
* **Circuit Breaker (`guard`):** Execução segura de pipelines e automações.

---

## 📦 Instalação

```bash
npm install grid402
```

---

## ⚡ Guia Rápido

### 1. Resumo Gratuito e Checagem de Saúde
```typescript
import { Grid402Client } from 'grid402';

const client = new Grid402Client();

// Resumo gratuito
const summary = await client.getSummary();
console.log('Status do Hub:', summary.status);

// Checagem booleana rápida
if (await client.isHealthy('dns')) {
  console.log('Resolução DNS operacional!');
}
```

### 2. Consultas Técnicas com Micropagamento x402
```typescript
import { Grid402Client, PaymentRequiredError } from 'grid402';

const client = new Grid402Client();
const txHash = '0x<sua_transacao_base_mainnet>';

try {
  // Câmbio
  const forex = await client.getForex(txHash);
  console.log('USD/BRL:', forex.rates.USD.bid);

  // DNS
  const dns = await client.getDns('github.com', txHash);
  console.log('IPs (A):', dns.records.A);

  // Inspetor SSL
  const ssl = await client.getSsl('base.org', txHash);
  console.log('Expira em dias:', ssl.certificate?.days_remaining);

  // CEP & IBGE
  const cep = await client.getCep('01310100', txHash);
  console.log('Código IBGE:', cep.ibge);

} catch (err) {
  if (err instanceof PaymentRequiredError) {
    console.log(`Pagamento requerido: envie ${err.priceUsdc} USDC para ${err.recipientWallet} na Base.`);
  }
}
```

---

## 📄 Licença e Conformidade Legal
- **Licença:** MIT.
- **Isenção AS-IS:** Dados disponibilizados no estado em que se encontram sem garantias comerciais.
- **Zero PII / LGPD:** Todos os utilitários operam sobre registros técnicos de domínio e protocolo sem dados pessoais.
