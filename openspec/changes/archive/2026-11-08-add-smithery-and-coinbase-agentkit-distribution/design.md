# Design Técnico: Distribuição Smithery e Coinbase AgentKit

## 1. Especificação do Manifesto Smithery (`smithery.yaml`)

O Smithery utiliza o arquivo `smithery.yaml` para registrar o servidor MCP e disponibilizar um instalador interativo na CLI e no site:

```yaml
startCommand:
  type: stdio
  configSchema:
    type: object
    properties:
      privateKey:
        type: string
        description: Chave privada EVM (com 0x) na Base para liquidar tarifas em USDC automaticamente.
      sandbox:
        type: boolean
        description: Habilita o modo de testes gratuito (sem custos) via cabecalho demo.
        default: false
      apiUrl:
        type: string
        description: URL base do hub Grid402.
        default: https://grid402.com.br
  commandFunction:
    node:
      cmd: npx
      args:
        - -y
        - grid402-mcp
```

## 2. Arquitetura do Coinbase AgentKit Action Provider

O framework AgentKit da Coinbase define o conceito de `ActionProvider` que expõe métodos decorados como ações acionáveis pelo modelo de linguagem:

1. **Estrutura de Ações:**
   - `grid402_query_cnpj`: Consulta cadastral de PJ (0.0020 USDC).
   - `grid402_query_cep`: Resolução de endereço postal e IBGE (0.0005 USDC).
   - `grid402_query_forex`: Cotações cambiais USD, EUR, GBP (0.0005 USDC).
   - `grid402_query_crypto`: Tickers cripto BTC, ETH, SOL, USDC (0.0005 USDC).
   - `grid402_query_dns`: Resolução de registros A, AAAA, MX, TXT (0.0005 USDC).
   - `grid402_query_ssl`: Auditoria de validade de certificados TLS/SSL (0.0005 USDC).
   - `grid402_query_asn`: Roteamento BGP e ASN de IPs (0.0005 USDC).

2. **Fluxo de Auto-Liquidação x402 no Provider:**
   - O Action Provider recebe a chamada do agente.
   - Dispara requisição HTTP inicial para o Grid402.
   - Se retornar HTTP 200 (caso seja summary ou modo sandbox): retorna imediatamente.
   - Se retornar HTTP 402:
     - Lê os metadados de pagamento do cabeçalho `X-Accept-Payment` ou do corpo JSON (`token_contract`, `amount_raw`, `recipient_wallet`).
     - Invoca o `walletProvider.transfer()` nativo do AgentKit para transferir o montante exato em USDC na Base.
     - Captura o hash gerado pela carteira CDP.
     - Reenvia a requisição HTTP com o cabeçalho `X-Payment: <hash>`.
     - Retorna o JSON completo enriquecido para o LLM.

## 3. Implementações nas Duas Linguagens

- **TypeScript (`grid402_action_provider.ts`):** Estende `ActionProvider<EvmWalletProvider>` do pacote `@coinbase/agentkit`.
- **Python (`grid402_action_provider.py`):** Estende `ActionProvider[EvmWalletProvider]` do pacote `cdp_agentkit_core.actions`.

