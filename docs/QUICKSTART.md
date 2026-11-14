# Grid402 // Quickstart (curl, Python, Node, MCP)

> Do zero a primeira consulta paga em menos de 5 minutos. Cobertura: Sandbox (gratis), Session Pass e x402 on-chain.

## 1. Teste gratuito (Sandbox) — 30 segundos

```bash
curl -s -H "X-Payment: demo" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"
```

Resposta (amostra gratis + desafio 402). Cota: 3 req/hora por IP.

## 2. Python — com Session Pass

```python
import os, requests

BASE = "https://grid402.online"
PASS = os.getenv("GRID402_SESSION_PASS", "pass_g402_SEU_TOKEN_AQUI")

r = requests.get(f"{BASE}/api/v1/cnpj", params={"cnpj": "00000000000191"},
                 headers={"X-Payment": PASS}, timeout=10)
print(r.status_code, r.json())
```

Instale: `pip install requests` (ou use o SDK `pip install grid402`).

## 3. Node.js — com Session Pass

```js
const BASE = "https://grid402.online";
const PASS = process.env.GRID402_SESSION_PASS || "pass_g402_SEU_TOKEN_AQUI";

const res = await fetch(`${BASE}/api/v1/cnpj?cnpj=00000000000191`, {
  headers: { "X-Payment": PASS }
});
console.log(res.status, await res.json());
```

## 4. MCP (Claude Desktop / Cursor)

```bash
npx -y @smithery/cli install grid402-mcp --client claude
```

Defina no config do cliente:

```json
{
  "env": {
    "GRID402_SESSION_PASS": "pass_g402_SEU_TOKEN_AQUI"
  }
}
```

Modos suportados (em ordem de prioridade):
1. `GRID402_SESSION_PASS` (pass_g402_...) — recomendado.
2. `GRID402_SANDBOX=true` — gratuito (3 req/hora).
3. `GRID402_PRIVATE_KEY` — auto-liquidacao x402 on-chain.

## 5. x402 on-chain (sem Session Pass)

```bash
# 1. Obtenha o desafio 402
curl -i "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"

# 2. Envie 0.0020 USDC (CNPJ) na Base (Chain ID 8453) para a wallet oracle
#    0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90

# 3. Refaca a requisicao com o hash da transacao
curl -s "https://grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: 0x<tx_hash_64_hex>"
```

## 6. Comprar um Session Pass

Acesse https://grid402.online/pass (ou https://grid402.online/#topup) e escolha:

| Pacote | Preco (USDC) | Creditos | Validade |
|---|---|---|---|
| Starter | 2 | 4.000 | 30 dias |
| Pro / Agent | 5 | 10.000 | 30 dias |
| Fleet / Enterprise | 20 | 50.000 + 10.000 bonus | 60 dias |

Pagamento via MetaMask, Coinbase Smart Wallet (Passkey) ou Rainbow na rede Base.

## 7. n8n (HTTP Request node)

- Method: GET
- URL: `https://grid402.online/api/v1/cnpj`
- Query param: `cnpj = 00000000000191`
- Header: `X-Payment: pass_g402_SEU_TOKEN` (ou `demo` para teste)
- Template pronto: `examples/n8n/grid402_cnpj_compliance_workflow.json`

## Referencias

- Portal: https://grid402.online
- Compra de pass: https://grid402.online/pass
- Descoberta para agentes: https://grid402.online/llms.txt
- OpenAPI 3.1: https://grid402.online/openapi.json
- MCP: `grid402-mcp` (npm)
