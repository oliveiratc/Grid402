## ADDED Requirements

### Requirement: Registro Canônico Multi-Chain (5 Redes EVM + Solana)
O sistema MUST manter um registro canônico em `public_html/services/multichain.php` suportando `base`, `arbitrum`, `polygon`, `optimism`, `ethereum` e `solana`, com contratos oficiais de USDC (6 decimais), endereços destinatários (`GRID402_WALLET_ADDRESS` para EVM e `GRID402_SOLANA_WALLET_ADDRESS` = `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C` para Solana), listas de RPC com failover e URLs de block explorer.

#### Scenario: Registro retorna todas as 6 redes com parâmetros válidos
- GIVEN o módulo `multichain.php` carregado com `config.php`
- WHEN `grid402_supported_networks()` é consultado
- THEN retorna exatamente as 6 redes (`base`, `arbitrum`, `polygon`, `optimism`, `ethereum`, `solana`) com contratos USDC e carteiras válidas.

### Requirement: Verificação On-Chain EVM Multi-Chain e Solana SPL USDC
O sistema MUST auditar recibos ERC-20 `Transfer` de USDC em qualquer rede EVM suportada (`grid402_verify_evm_receipt_usdc`) e transações SPL USDC na Solana via delta de `postTokenBalances - preTokenBalances` (`grid402_verify_solana_tx_usdc`), rejeitando transações falhas, contratos não autorizados, destinatários incorretos ou valores insuficientes.

#### Scenario: Recibo EVM válido na Arbitrum com USDC nativo é aprovado
- GIVEN um recibo JSON-RPC `status: 0x1` na rede `arbitrum` com log ERC-20 `Transfer` do contrato `0xaf88d065e77c8cc2239327c5edb3a432268e5831` enviando 2000000 raw (2.00 USDC) para `GRID402_WALLET_ADDRESS`
- WHEN `grid402_verify_evm_receipt_usdc($receipt, 'arbitrum', 2000000)` é executado
- THEN retorna `valid: true`, `network: "arbitrum"` e `raw_amount: 2000000`.

#### Scenario: Recibo EVM de contrato falso (token fraudulento) é rejeitado
- GIVEN um recibo `status: 0x1` na rede `polygon` contendo evento `Transfer` de um contrato não listado na allowlist USDC da Polygon
- WHEN `grid402_verify_evm_receipt_usdc($receipt, 'polygon', 5000)` é executado
- THEN retorna `valid: false` e erro `InsufficientOrMissingUsdcTransfer`.

#### Scenario: Transação Solana SPL USDC válida é aprovada pelo delta de saldo
- GIVEN um objeto `getTransaction` da Solana com `meta.err: null` onde `preTokenBalances` da conta USDC do owner `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C` é `1000000` e `postTokenBalances` é `6000000` (delta líquido `+5000000` = 5.00 USDC)
- WHEN `grid402_verify_solana_tx_usdc($txResult, 5000000)` é executado
- THEN retorna `valid: true`, `network: "solana"` e `raw_amount: 5000000`.

#### Scenario: Transação Solana com erro de execução (`meta.err != null`) é rejeitada
- GIVEN uma resposta `getTransaction` da Solana com `meta.err` não nulo
- WHEN `grid402_verify_solana_tx_usdc($txResult, 5000)` é executado
- THEN retorna `valid: false` e erro `TransactionFailedOnChain`.

### Requirement: Resolução de Rede, Anti-Replay Cross-Chain e Descoberta no HTTP 402
O sistema MUST resolver a rede via prefixo `chain:tx_hash`, cabeçalho `X-Payment-Network` ou parâmetro `chain` (com fallback retrocompatível para `base`), aplicar trava anti-replay atômica em `used_txs.json` por chave canônica de rede e expor `supported_networks` no desafio HTTP 402.

#### Scenario: Prefixo `chain:tx_hash` e cabeçalho `X-Payment-Network` são interpretados corretamente
- GIVEN um comprovante `"arbitrum:0x" . str_repeat("a", 64)` ou cabeçalho `X-Payment-Network: polygon` com hash `0x...`
- WHEN `grid402_parse_network_proof()` processa a entrada
- THEN identifica a rede correta, extrai o `tx_hash` limpo e gera a chave de trava `"{network}:{tx_hash}"` (mantendo apenas `tx_hash` para `base`).

#### Scenario: Desafio HTTP 402 anuncia as 6 redes aceitas
- GIVEN uma chamada a um endpoint pago sem cabeçalho de pagamento
- WHEN o gateway emite HTTP 402
- THEN o payload JSON inclui `supported_networks` listando `base`, `arbitrum`, `polygon`, `optimism`, `ethereum` e `solana` com seus respectivos contratos USDC e carteiras destinatárias.

### Requirement: Emissão de Session Pass Multi-Chain e Seletor Web3 na UI (`/pass`)
O endpoint `/api/v1/pass-emitir` MUST aceitar o parâmetro `chain`/`network` para emitir Session Passes a partir de qualquer uma das 6 redes suportadas, e a interface Web3 em `public_html/index.php` MUST exibir seletor visual de rede com troca automática EVM e painel dedicado Solana.

#### Scenario: `/api/v1/pass-emitir` rejeita rede não suportada com HTTP 400
- GIVEN uma requisição `POST /api/v1/pass-emitir` com `{"txHash": "0x...", "chain": "rede_inexistente"}`
- WHEN o endpoint valida o parâmetro de rede
- THEN responde HTTP 400 com erro `UnsupportedNetwork`.

#### Scenario: Interface `/pass` renderiza seletor das 6 redes incluindo Solana
- GIVEN uma requisição `GET /pass` com `Accept: text/html`
- WHEN a página é renderizada
- THEN contém o seletor multi-chain com Base, Arbitrum, Polygon, Optimism, Ethereum e Solana, exibindo o endereço Solana `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`.
