# Design Técnico: Pagamentos Multi-Chain EVM + Solana SPL USDC

## 1. Registro Canônico de Redes (`public_html/services/multichain.php`)

| Slug | Tipo | Chain ID / CAIP-2 | Contratos USDC Oficiais (6 decimais) | Carteira Destinatária |
|---|---|---|---|---|
| `base` | `evm` | `8453` (`eip155:8453`) | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` | `GRID402_WALLET_ADDRESS` |
| `arbitrum` | `evm` | `42161` (`eip155:42161`) | `0xaf88d065e77c8cC2239327C5EDb3A432268e5831` (Native) e `0xFF970A61A04b1cA14834A43f5dE4533eBDDB5CC8` (USDC.e) | `GRID402_WALLET_ADDRESS` |
| `polygon` | `evm` | `137` (`eip155:137`) | `0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359` (Native) e `0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174` (USDC.e) | `GRID402_WALLET_ADDRESS` |
| `optimism` | `evm` | `10` (`eip155:10`) | `0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85` (Native) e `0x7F5c764cBc14f9669B88837ca1490cCa17c31607` (USDC.e) | `GRID402_WALLET_ADDRESS` |
| `ethereum` | `evm` | `1` (`eip155:1`) | `0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48` | `GRID402_WALLET_ADDRESS` |
| `solana` | `solana` | `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` | `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v` (SPL Mint) | `GRID402_SOLANA_WALLET_ADDRESS` |

## 2. Resolução de Rede e Chave Anti-Replay
A função `grid402_parse_network_proof(string $rawProof, ?string $headerNetwork = null)` resolve o comprovante nesta ordem:
1. Prefixo explícito no comprovante: `arbitrum:0x...`, `polygon:0x...`, `optimism:0x...`, `ethereum:0x...`, `base:0x...`, `solana:<base58>`.
2. Cabeçalho HTTP `X-Payment-Network` (ou parâmetro `chain` / `network` no body/query de `/api/v1/pass-emitir`).
3. Fallback retrocompatível: se o hash começa com `0x` sem indicação de rede, assume `base`. Se for uma assinatura Base58 (64–90 chars alfanuméricos sem `0x` e sem prefixo `pass_g402_`), assume `solana` quando a rede não for explicitada.
4. Chave na trava atômica `used_txs.json` (`flock`):
   - Para `base`: `strtolower($txHash)` (100% compatível com registros existentes).
   - Para demais redes: `"{$network}:{$normalizedTx}"` (evitando colisões e garantindo unicidade por rede).

## 3. Verificação EVM Multi-Chain (`grid402_verify_evm_receipt_usdc`)
1. Consulta `eth_getTransactionReceipt` nos RPCs configurados da rede (com sobrescrita via `.env`, ex: `GRID402_RPC_ARBITRUM`, timeouts 3s/5s e `CURLOPT_SSL_VERIFYPEER => true`).
2. Exige `status === '0x1'`.
3. Varre `receipt['logs']` procurando:
   - `strtolower($log['address'])` presente na allowlist de contratos USDC da rede;
   - `topics[0] === '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef'` (ERC-20 `Transfer`);
   - `topics[2]` (destinatário) igual ao endereço oficial `GRID402_WALLET_ADDRESS` (últimos 40 hex chars);
   - Soma de `hexdec($log['data']) >= $requiredRawUnits`.

## 4. Verificação Nativa Solana SPL USDC (`grid402_verify_solana_tx_usdc`)
1. Valida formato Base58 da assinatura (`preg_match('/^[1-9A-HJ-NP-Za-km-z]{64,90}$/', $sig)`).
2. Consulta JSON-RPC `getTransaction` com `encoding: "jsonParsed"`, `commitment: "confirmed"` e `maxSupportedTransactionVersion: 0` nos RPCs públicos da Solana com failover.
3. Exige `meta.err === null`.
4. Calcula o saldo líquido creditado em SPL USDC para o owner `GRID402_SOLANA_WALLET_ADDRESS` comparando `meta.postTokenBalances` e `meta.preTokenBalances` filtrados por `mint === 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'` e `owner === GRID402_SOLANA_WALLET_ADDRESS`.
5. Exige `netCreditedRaw >= $requiredRawUnits`.

## 5. Interface Web3 (`/pass` e `#topup` em `index.php`)
- Barra de seleção com 6 botões de rede: **Base (Padrão)**, **Arbitrum**, **Polygon**, **Optimism**, **Ethereum** e **Solana**.
- Ao selecionar uma rede EVM e clicar em pagar: o JavaScript aciona `wallet_switchEthereumChain` (e `wallet_addEthereumChain` se necessário), envia `transfer(recipient, amountRaw)` no contrato USDC nativo da rede escolhida e chama `POST /api/v1/pass-emitir` com `{ txHash, chain }`.
- Ao selecionar **Solana**: exibe o endereço Solana copiável (`CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`), o valor exato em SPL USDC do pacote escolhido e um campo para colar a assinatura da transação (ou conectar Phantom `window.solana` quando disponível) e clicar em **"Validar Assinatura Solana e Emitir Pass"** chamando `POST /api/v1/pass-emitir` com `{ txHash: signature, chain: 'solana' }`.
