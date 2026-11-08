# Design Técnico: Modo Sandbox e Suporte a Base Sepolia

## 1. Arquitetura da Solução

O fluxo de auditoria do pagamento no gateway é estendido para identificar requisições de sandbox antes de acionar a auditoria na Mainnet:

```
[Requisição com X-Payment: <valor>]
                │
                ▼
  ┌─────────────────────────────┐
  │ O valor é 'demo'/'sandbox'? │
  └──────────────┬──────────────┘
         SIM ────┼──────────────── NÃO
         │                             │
         ▼                             ▼
┌───────────────────────────┐    ┌───────────────────────────────────┐
│ Valida Rate Limit por IP  │    │ Tenta Validação na Base Mainnet   │
│ (Max 20 req/hora)         │    │ (Chain ID 8453)                   │
└────────────┬──────────────┘    └─────────────────┬─────────────────┘
   EXCEDEU ──┼── DENTRO DA COTA                    │ NÃO ENCONTRADO
   │               │                               ▼
   ▼               ▼                     ┌───────────────────────────────────┐
HTTP 429     HTTP 200                    │ Tenta Validação na Base Sepolia   │
(Too Many    (Payload completo com       │ (Chain ID 84532 - Testnet)        │
 Requests)    "sandbox": true)           └─────────────────┬─────────────────┘
                                                   SUCESSO ┴ FALHA
                                                   │           │
                                                   ▼           ▼
                                               HTTP 200     HTTP 402
                                               (Sandbox)   (Payment Required)
```

## 2. Controle de Taxa Defensivo (Rate Limit de Sandbox)

1. **Arquivo de Persistência:** `sandbox_rates.json` dentro do diretório privado persistente.
2. **Janela Deslizante:** 3600 segundos (1 hora).
3. **Estrutura do Registro:**
   ```json
   {
     "192.168.1.50": {
       "count": 5,
       "first_request": 1794100000,
       "last_request": 1794101200
     }
   }
   ```
4. **Mecanismo Atômico:** Gravação via `grid402_atomic_write` ou arquivo temporário + renomeação com exclusão de registros com mais de 1 hora de antiguidade.

## 3. Integração com Base Sepolia (Testnet)

1. **Constantes em `config.php`:**
   - `GRID402_SEPOLIA_RPC_URL`: `https://sepolia.base.org`
   - `GRID402_SEPOLIA_CHAIN_ID`: 84532
   - `GRID402_SEPOLIA_USDC_CONTRACT`: `0x036cbd53842c5426634e7929541ec2318f3dcf7e`
2. **Função `grid402_validate_payment`:**
   - Se a transação não for localizada na Mainnet, realiza uma tentativa de fallback contra o RPC da Sepolia.
   - Se confirmada na Sepolia, marca o retorno com `"sandbox": true` e `chain: "base-sepolia"`.

## 4. Interface do Console Web

- Botão adicional na barra de ação do console:
  `<button class="btn-sm btn-outline" id="btn-sandbox-pay" onclick="runSandboxSimulation()">🧪 Simular Pagamento (Sandbox)</button>`
- A função `runSandboxSimulation()` preenche o campo de transação com `demo` e dispara a consulta imediatamente, exibindo a tag de "Ambiente Sandbox" na resposta.

