# Grid402 Client SDKs

Bibliotecas oficiais de integração cliente (zero dependências externas) para agentes autônomos de IA e sistemas M2M consumirem o Oráculo de Telemetria Grid402.

---

## 🐍 Python SDK (`sdk/python/grid402.py`)

Zero dependências externas (utiliza apenas `urllib` da biblioteca padrão).

### Instalação / Uso
Copie o arquivo `grid402.py` para seu projeto:

```python
from grid402 import Grid402Client, PaymentRequiredError

client = Grid402Client()

# 1. Consulta gratuita de saúde
summary = client.get_summary()
print("Status do sistema:", summary["system_status"])

# 2. Verificação rápida booleana
if client.is_healthy("SEFAZ"):
    print("SEFAZ está operante! Autorizando lote...")

# 3. Padrão Circuit Breaker (Guard)
def enviar_notas_fiscais():
    # Código que emite 500 notas fiscais
    return "Lote processado"

resultado = client.guard("SEFAZ", enviar_notas_fiscais)

# 4. Handshake de Pagamento x402 (Telemetria Detalhada)
try:
    telemetria = client.get_detailed_telemetry("0x<tx_hash_na_base>")
    print(telemetria["targets"])
except PaymentRequiredError as p:
    print(f"Envie {p.price_usdc} USDC para a carteira {p.recipient_wallet} na Base.")
```

---

## 🟢 Node.js / TypeScript SDK (`sdk/node/grid402.mjs`)

Suporte nativo a ESM e Fetch API (zero dependências no Node.js 18+).

### Instalação / Uso
Copie `grid402.mjs` e `grid402.d.ts` para seu projeto:

```javascript
import { Grid402Client, PaymentRequiredError } from './grid402.mjs';

const client = new Grid402Client();

// 1. Resumo gratuito
const summary = await client.getSummary();
console.log('Status geral:', summary.system_status);

// 2. Checagem direta de categoria ou alvo
if (await client.isHealthy('Gateways')) {
    console.log('Gateways de pagamento saudáveis!');
}

// 3. Padrão Circuit Breaker (Guard)
const resultado = await client.guard('Gateways', async () => {
    return 'Transações liquidadas com sucesso!';
});

// 4. Fluxo x402
const challenge = await client.getChallenge();
console.log(`Preço: ${challenge.price_usdc} USDC | Carteira: ${challenge.recipient_wallet}`);
```

