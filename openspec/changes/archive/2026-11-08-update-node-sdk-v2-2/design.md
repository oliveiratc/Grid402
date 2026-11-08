# Design Tecnico: Grid402 SDK Node.js e TypeScript v2.2.0

## 1. Arquitetura de Modulos

O pacote `grid402` adota distribuicao hibrida suportando tanto projetos modernos (Node 18+, Next.js App Router, Vite, Bun) quanto ambientes legados baseados em CommonJS:

```
sdk/node/
├── grid402.mjs           # Implementacao ESM nativa (ES Modules)
├── grid402.cjs           # Wrapper / distribuicao CommonJS (require)
├── grid402.d.ts          # Declaracoes TypeScript completas
├── test_sdk.mjs          # Testes unitarios isolados
├── test_live_sandbox.mjs # Testes de integracao com a API real
├── package.json          # Metadados e export maps
├── README.md             # Documentacao em portugues
└── LICENSE               # Licenca MIT
```

## 2. Export Maps no `package.json`

```json
{
  "name": "grid402",
  "version": "2.2.0",
  "type": "module",
  "main": "./grid402.cjs",
  "module": "./grid402.mjs",
  "types": "./grid402.d.ts",
  "exports": {
    ".": {
      "types": "./grid402.d.ts",
      "import": "./grid402.mjs",
      "require": "./grid402.cjs",
      "default": "./grid402.mjs"
    }
  }
}
```

## 3. Interfaces e Componentes

### `Grid402Client`
- `constructor(options = { baseUrl, timeout, sandbox, paymentTxHash })`
- Se `sandbox: true`, injeta automaticamente o cabecalho `X-Payment: demo`.
- Se `paymentTxHash` for fornecido, injeta `X-Payment: <hash>`.
- Metodos:
  - `getSummary()`: Rota `/status` (ou `/api/v1/summary`)
  - `getForex(txHash?)`: Rota `/api/v1/forex`
  - `getCrypto(txHash?)`: Rota `/api/v1/crypto`
  - `getDns(domain, txHash?)`: Rota `/api/v1/dns`
  - `getSsl(host, txHash?)`: Rota `/api/v1/ssl`
  - `getAsn(ip?, txHash?)`: Rota `/api/v1/asn`
  - `getCep(code, txHash?)`: Rota `/api/v1/cep`
  - `getCnpj(cnpj, txHash?)`: Rota `/api/v1/cnpj`

### `Grid402ActionProvider`
- Compativel com Coinbase AgentKit, LangChain.js e ElizaOS.
- Inspeciona resposta HTTP 402, extrai `price_usdc` e `recipient_wallet`.
- Se `wallet_provider` estiver presente, executa `wallet_provider.transfer({ to, amount, assetId: "usdc" })`.
- Reenvia requisicao com o `txHash` retornado e anexa `_x402_settlement`.

### `PaymentRequiredError`
- Contem `challenge`, `preview_data`, `price_usdc`, `recipient_wallet`, `network` e `token`.

