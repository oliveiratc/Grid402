# Design: Aceleração de Faturamento Web3

## 1. Arquitetura de Pagamentos Web3 no Frontend
O portal `public_html/index.php` atualmente possui um modal simples de pagamento unitário (`openWeb3Modal()`).
Iremos adicionar uma seção dedicada e proeminente: **"Abastecer Saldo On-Chain (Base) / One-Click Top-Up"**.

### 1.1. Seleção de Tiers
- **Starter:** 2 USDC ($2.000.000 unidades mínimas) $\rightarrow$ 4.000 requisições
- **Pro:** 5 USDC ($5.000.000 unidades mínimas) $\rightarrow$ 10.000 requisições
- **Fleet:** 20 USDC ($20.000.000 unidades mínimas) $\rightarrow$ 50.000 requisições

### 1.2. Fluxo On-Chain
1. Detecção do provedor (`window.ethereum` ou Coinbase Smart Wallet SDK).
2. Validação da chain `0x2105` (8453 - Base Mainnet). Se não estiver, dispara `wallet_switchEthereumChain` com fallback para `wallet_addEthereumChain`.
3. Execução de chamada direta ERC-20 `transfer(to, amount)` para o endereço do contrato USDC da Base (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`) enviando para `0xa31c31069c43ac1c0075a93c325b0dcd7744356c`.
4. Aguardo do hash da transação.
5. Envio imediato do `txHash` para `/api/v1/pass?tx=0x...`.
6. O backend em `pass.php` verifica a transação via RPC da Base, registra o passe em `session_passes.json` e retorna o token `pass_g402_{id}_{hmac}`.
7. Exibição do token em card destacado com botão "Copiar Token" e status de créditos ativos.

## 2. Estrutura do Plugin ElizaOS (`integrations/elizaos/`)
O plugin segue a arquitetura oficial do framework ElizaOS (`@elizaos/core`):
- `src/index.ts`: Ponto de entrada do plugin exportando o objeto `grid402Plugin`.
- `src/actions/`:
  - `forex.ts`: Consulta de cotações em tempo real de câmbio comercial.
  - `crypto.ts`: Cotações spot de criptoativos na Base e mercado geral.
  - `globalMarkets.ts`: Taxas de referência internacionais (BCE, commodities).
  - `sanctions.ts`: Consulta unificada em listas OFAC SDN e ONU.
  - `lei.ts`: Resolução de identificador LEI mundial (GLEIF).
  - `cnpj.ts`: Consulta de situação cadastral e sócios na Receita Federal.
- `src/providers/`: Provider de contexto com estado de saldo ou status de rede do Grid402.
- `package.json`, `tsconfig.json` e `README.md`.

## 3. Dossiê de Grants (`docs/BASE_GRANTS_AND_CDP_DOSSIER.md`)
O dossiê será composto por:
1. Executive Summary & Problem Statement
2. Live Traction & Technical Metrics (19 endpoints, x402 on Base, latências < 15ms)
3. Architecture & Zero-DB Innovation
4. Why Base & CDP: How Grid402 powers autonomous agent economies on Base
5. Funding Ask & Milestones Breakdown
6. Ready-to-Submit Form Responses (para submeter no Base Builder Grants e CDP Portal)

