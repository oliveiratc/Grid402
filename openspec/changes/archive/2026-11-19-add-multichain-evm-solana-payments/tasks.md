# Tasks: Expansão Cross-Chain e Multimoedas (EVM + Solana SPL USDC)

## 1. Configuração e Módulo Multi-Chain (`multichain.php`)
- [x] 1.1 Definir `GRID402_SOLANA_WALLET_ADDRESS` (`CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`) e `GRID402_SOLANA_USDC_MINT` em `public_html/config.php` e adicionar no `.env.example`.
- [x] 1.2 Criar `public_html/services/multichain.php` com registro canônico das 6 redes (`base`, `arbitrum`, `polygon`, `optimism`, `ethereum`, `solana`), RPCs com failover e block explorers.
- [x] 1.3 Implementar `grid402_parse_network_proof()` (suporte a `chain:tx_hash`, cabeçalho `X-Payment-Network`, detecção Base58 Solana e fallback retrocompatível para `base`) e chave anti-replay canônica.
- [x] 1.4 Implementar verificação EVM multi-chain (`grid402_rpc_get_evm_receipt` + `grid402_verify_evm_receipt_usdc`) com allowlist de contratos Circle USDC/USDC.e.
- [x] 1.5 Implementar verificação nativa Solana SPL USDC (`grid402_rpc_get_solana_transaction` + `grid402_verify_solana_tx_usdc`) validando `meta.err === null` e delta líquido de `postTokenBalances - preTokenBalances`.

## 2. Integração no Gateway x402 e Emissão de Session Pass
- [x] 2.1 Integrar `multichain.php` em `public_html/x402.php` (`grid402_validate_payment` aceitando qualquer das 6 redes com trava anti-replay por chave canônica, `supported_networks` em `grid402_respond_402`, e link do block explorer correto no Telegram).
- [x] 2.2 Atualizar `public_html/services/pass_checkout.php` (`grid402_emitir_passe_por_tx(string $txHash, ?string $network = null)`) para validar e emitir Session Passes em qualquer uma das 6 redes e retornar HTTP 400 `UnsupportedNetwork` para redes inválidas.
- [x] 2.3 Atualizar `public_html/index.php` para encaminhar `X-Payment-Network` e o parâmetro `chain`/`network` no `case 'pass-emitir':`.

## 3. Interface Web3 Multi-Chain (`/pass` e `#topup`) e Documentação
- [x] 3.1 Adicionar seletor visual das 6 redes (Base, Arbitrum, Polygon, Optimism, Ethereum e Solana) no bloco de recarga Web3 em `public_html/index.php` e em `public_html/pass.php` com troca automática EVM (`wallet_switchEthereumChain` / `wallet_addEthereumChain`) e fluxo Solana (endereço copiável + campo de assinatura + botão de validação).
- [x] 3.2 Atualizar `public_html/llms.txt` e `README.md` documentando o suporte multi-chain EVM + Solana.

## 4. Testes e Verificação
- [x] 4.1 Criar `tests/test_multichain_payments.php` cobrindo todos os cenários da spec (registro 6 redes, recibos EVM válidos e fraudulentos, transações Solana SPL USDC válidas e falhas, parsing/anti-replay, HTTP 402 `supported_networks`, `/api/v1/pass-emitir` com rede inválida e UI `/pass` + `/#topup`).
- [x] 4.2 Executar `tests/test_multichain_payments.php` e a bateria de regressão (`test_x402_gateway.php`, `test_telegram_notification.php`, `test_dossier.php`, `test_agent_discovery.php`, `test_agentkit_integration.php`, `test_session_pass_statement.php`).
