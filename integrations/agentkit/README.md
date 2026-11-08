# Grid402 Action Provider para Coinbase AgentKit

Este modulo fornece a integracao oficial do **Grid402** com o ecossistema **Coinbase Developer Platform (CDP) AgentKit** na rede **Base**.

Com este provedor, qualquer agente de IA (LangChain, CrewAI, AutoGen ou ElizaOS) dotado de uma carteira CDP na Base pode realizar consultas tecnicas e cadastrais enriquecidas, liquidando automaticamente as micro-tarifas x402 (0.0005 a 0.0020 USDC) diretamente on-chain.

---

## 1. Ferramentas Disponibilizadas

| Acao / Ferramenta | Descricao | Tarifa x402 (Base) |
|---|---|---|
| `query_cnpj` | Dados cadastrais completos da PJ na Receita Federal (Razao, CNAEs, Capital, Endereco) | 0.0020 USDC |
| `query_cep` | Resolucao de CEP postal brasileiro e codigo IBGE | 0.0005 USDC |
| `query_forex` | Cotacoes cambiais oficiais de USD, EUR e GBP para BRL | 0.0005 USDC |
| `query_crypto` | Precos spot de mercado de BTC, ETH, SOL e USDC | 0.0005 USDC |
| `query_dns` | Resolucao autoritativa de registros A, AAAA, MX, TXT, NS | 0.0005 USDC |
| `query_ssl` | Inspecao e dias de validade de certificados TLS/SSL | 0.0005 USDC |
| `query_asn` | Roteamento BGP, numero de ASN e organizacao titular de IP | 0.0005 USDC |
| `query_indicators` | Indicadores macroeconomicos oficiais (Selic meta e diaria, CDI anual e IPCA) | 0.0005 USDC |
| `query_holidays` | Calendario oficial de feriados nacionais e bancarios ANBIMA por ano | 0.0005 USDC |
| `query_workdays` | Calculadora de dias uteis financeiros e projecao de vencimentos ANBIMA | 0.0005 USDC |
| `verify_proof` | Validacao matematica de prova criptografica de oraculo (_oracle_proof) secp256k1 | Gratuito |

---

## 2. Exemplo de Uso em Node.js / TypeScript

```typescript
import { CdpWalletProvider } from "@coinbase/agentkit";
import { Grid402ActionProvider } from "./grid402_action_provider.js";

// 1. Inicializa o provedor de carteira CDP na Base
const walletProvider = await CdpWalletProvider.configureWithWallet({
  apiKeyName: process.env.CDP_API_KEY_NAME,
  apiKeyPrivateKey: process.env.CDP_API_KEY_PRIVATE_KEY,
  networkId: "base-mainnet", // ou 'base-sepolia' para testes
});

// 2. Inicializa o provedor de acoes do Grid402
const grid402 = new Grid402ActionProvider({
  baseUrl: "https://orangered-jackal-900744.hostingersite.com",
  sandbox: false, // configure true para testar sem custos (cota de 20 req/h)
});

// 3. Executa a consulta com auto-liquidacao de 0.0020 USDC
const empresa = await grid402.queryCnpj("00000000000191", walletProvider);

console.log("Razao Social:", empresa.razao_social);
console.log("Comprovante on-chain:", empresa._x402_settlement.tx_hash);
```

---

## 3. Exemplo de Uso em Python (LangChain / CrewAI)

```python
from cdp_agentkit_core.actions import CdpWalletProvider
from grid402_action_provider import Grid402ActionProvider

# 1. Inicializa a carteira CDP na Base
wallet = CdpWalletProvider(
    api_key_name="sua_chave_cdp",
    api_key_private_key="seu_segredo_cdp",
    network_id="base-mainnet"
)

# 2. Inicializa o provedor Grid402
grid402 = Grid402ActionProvider(sandbox=False)

# 3. Consulta automatizada com auto-liquidacao
dados_cep = grid402.query_cep("01310100", wallet_provider=wallet)
print("Logradouro:", dados_cep.get("logradouro"))
print("Cidade:", dados_cep.get("cidade"))
```

---

## 4. Modo Sandbox para Testes Locais e CI/CD

Para testar seu agente localmente sem gastar USDC real ou configurar chaves da Coinbase, basta passar o parametro `sandbox=True`:

```python
grid402 = Grid402ActionProvider(sandbox=True)
empresa = grid402.query_cnpj("00000000000191")
print("Status Sandbox:", empresa.get("sandbox"))
```
No modo sandbox, o cabeçalho `X-Payment: demo` e injetado automaticamente, liberando todas as consultas com cota defensiva de 20 chamadas por hora por IP.

