# Expansão Cross-Chain e Multimoedas (EVM Multi-Chain + Solana SPL USDC)

## Why
Atualmente o Grid402 aceita pagamentos Web3 apenas em USDC na rede Base Mainnet. Mais de 70% dos agentes autônomos de IA e tesourarias Web3 mantêm reservas em USDC em outras redes líderes (Solana, Arbitrum, Polygon, Optimism e Ethereum Mainnet). Conforme o PRD aprovado [`openspec/prds/expansao-cross-chain-multimoedas.md`](../../prds/expansao-cross-chain-multimoedas.md), suportar verificação nativa multi-chain tanto para emissão de Session Pass quanto para micropagamentos diretos por chamada elimina o atrito de bridge e expande drasticamente o faturamento.

## What Changes
- Novo módulo `public_html/services/multichain.php` com registro canônico de 6 redes (Base, Arbitrum One, Polygon PoS, Optimism, Ethereum Mainnet e Solana Mainnet), verificação EVM ERC-20 `Transfer` com failover de RPC e verificação nativa Solana SPL USDC (`preTokenBalances`/`postTokenBalances`).
- Configuração de `GRID402_SOLANA_WALLET_ADDRESS` (`CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`) em `public_html/config.php`.
- Integração multi-chain em `public_html/x402.php` (`grid402_validate_payment`, `supported_networks` no desafio HTTP 402 e links do block explorer correto nas notificações do Telegram) e em `public_html/services/pass_checkout.php` (`/api/v1/pass-emitir` aceitando parâmetro `chain`/`network`).
- Trava anti-replay Zero-DB atômica por rede (`chain:tx_hash`) preservando 100% de retrocompatibilidade para a Base Mainnet.
- Seletor visual de rede em 1 clique na UI Web3 (`/pass` e `#topup` em `public_html/index.php`) com troca automática de rede na carteira EVM e fluxo dedicado para Solana.
- Suíte automatizada `tests/test_multichain_payments.php`.

## Out of Scope
- Bridges custodiais ou aceitação de tokens voláteis (ETH, SOL, MATIC) sem liquidação em USDC.
- Quebra de compatibilidade com clientes legados que enviam `X-Payment: 0x...` sem cabeçalho de rede.

## Impact
- **Specs afetadas:** `x402-payment-oracle`
- **Código afetado:** `public_html/config.php`, `public_html/services/multichain.php`, `public_html/x402.php`, `public_html/services/pass_checkout.php`, `public_html/index.php`, `public_html/llms.txt`, `README.md`, `tests/test_multichain_payments.php`.
