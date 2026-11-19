# PRD: Expansão Cross-Chain e Multimoedas (EVM Multi-Chain + Solana SPL USDC)

**Status:** Em revisão  
**Data:** 2026-11-19  
**Autor:** thiag + Antigravity  

---

## 1. Contexto e problema
Atualmente, o Grid402 aceita pagamentos Web3 exclusivamente em USDC na rede **Base Mainnet (Chain ID 8453)**. Embora a Base seja ideal pelo baixo custo e integração com a Coinbase, estima-se que mais de 70% dos agentes autônomos de IA e tesourarias Web3 mantêm reservas em USDC em outras redes líderes — especialmente **Solana**, **Arbitrum One**, **Polygon**, **Optimism** e **Ethereum Mainnet**.

Quando um agente ou desenvolvedor com saldo em Arbitrum, Polygon ou Solana tenta consumir o Grid402, ele enfrenta fricção de bridge manual e abandona a conversão. Permitir verificação e liquidação nativa em **6 blockchains (5 EVM + Solana)** tanto para **Session Passes** quanto para **micropagamentos diretos por chamada** remove a barreira de entrada e multiplica o mercado endereçável da plataforma.

---

## 2. Objetivos
- Suportar verificação nativa on-chain de transferências **USDC (6 decimais)** em **6 redes principais**:
  - **EVM (mesmo endereço `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`):** Base (`8453`), Arbitrum One (`42161`), Polygon PoS (`137`), Optimism (`10`) e Ethereum Mainnet (`1`).
  - **Solana (endereço `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`):** Solana Mainnet (`EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`).
- Permitir emissão de **Session Passes** (Starter 2 USDC / Pro 5 USDC / Fleet 20 USDC) via `/api/v1/pass-emitir` em qualquer uma das 6 redes.
- Permitir **micropagamento direto por chamada** via `tx_hash` em qualquer rede EVM suportada (especificando `X-Payment-Network: arbitrum|polygon|optimism|ethereum|base` ou formato `chain:tx_hash`) e assinatura Solana (`X-Payment-Network: solana`).
- Atualizar a interface Web3 (`/pass` e `#topup` em `index.php`) com seletor visual de rede em 1 clique, troca automática de rede na MetaMask/Coinbase Wallet (EVM) e fluxo de pagamento/verificação para Solana (Phantom/Solflare ou colagem de assinatura).
- Garantir proteção anti-replay atômica isolada por rede (`chain:tx_hash`) no armazenamento Zero-DB.

---

## 3. Não objetivos (fora do escopo)
- Não integrar custódia de chaves privadas de terceiros ou bridges custodiais centralizadas.
- Não aceitar tokens voláteis (ETH, SOL, MATIC, memecoins) sem conversão para USDC — o padrão contábil e de precificação permanece 100% **USDC** (6 decimais) + Pix/BRL.
- Não alterar a compatibilidade retroativa com clientes existentes que enviam `X-Payment: 0x...` sem cabeçalho de rede (o padrão continua sendo **Base Mainnet**).

---

## 4. Público-alvo e personas
1. **Agentes Autônomos em Solana (ElizaOS / Virtuals / Solana Agent Kit):** Agentes com carteira Phantom/Keypair e saldo em SPL USDC que desejam comprar Session Passes ou consultar dados sem precisar fazer bridge para EVM.
2. **Bots DeFi e Tesourarias L2 (Arbitrum, Optimism, Polygon):** Sistemas M2M operando em protocolos L2 que já possuem USDC nativo em suas redes.
3. **Desenvolvedores Web3 na Interface `/pass`:** Humanos comprando créditos em 1 clique com MetaMask, Coinbase Wallet ou Phantom na rede onde já têm saldo.

---

## 5. Histórias de usuário
- **HU01:** Como agente rodando na Arbitrum ou Polygon, quero pagar 2 USDC na minha rede atual e receber imediatamente um token `pass_g402_...` sem fazer bridge para a Base.
- **HU02:** Como agente na Solana, quero transferir SPL USDC para `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`, enviar a assinatura da transação para `/api/v1/pass-emitir?chain=solana` e receber meus créditos na hora.
- **HU03:** Como cliente M2M avulso, quero pagar uma chamada direta (`/api/v1/dossier`) usando um `tx_hash` da Arbitrum ou Optimism passando o cabeçalho `X-Payment-Network: arbitrum` (ou `X-Payment: arbitrum:0x...`).
- **HU04:** Como usuário humano em `grid402.online/pass`, quero clicar na rede desejada (Base, Arbitrum, Polygon, Optimism, Ethereum ou Solana), ver minha carteira trocar automaticamente de rede e concluir o pagamento em 1 clique.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Definir registro canônico multi-chain em `public_html/config.php` com Chain ID, CAIP-2, contrato USDC oficial, endereço destinatário, RPCs com fallback e explorador de blocos para Base, Arbitrum, Polygon, Optimism, Ethereum e Solana. | **Must** |
| **RF02** | Configurar `GRID402_SOLANA_WALLET_ADDRESS` com valor padrão `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C` (sobrescrevível via `.env`). | **Must** |
| **RF03** | Generalizar o verificador EVM (`grid402_verify_evm_usdc_transfer`) para auditar logs ERC-20 `Transfer` (`0xddf252ad...`) do contrato USDC oficial na rede selecionada via RPC com failover. | **Must** |
| **RF04** | Implementar verificador nativo Solana (`grid402_verify_solana_usdc_transfer`) via JSON-RPC `getTransaction` (`jsonParsed`, `maxSupportedTransactionVersion: 0`) auditando `preTokenBalances`/`postTokenBalances` do mint SPL USDC (`EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`) para o owner `GRID402_SOLANA_WALLET_ADDRESS`. | **Must** |
| **RF05** | Suportar especificação de rede via cabeçalho `X-Payment-Network`, prefixo `chain:tx_hash` em `X-Payment` ou parâmetro `chain`/`network` no JSON/query de `/api/v1/pass-emitir`. Sem indicação, manter fallback estrito para `base`. | **Must** |
| **RF06** | Garantir trava anti-replay atômica Zero-DB (`used_txs.json` com `flock`) indexada por chave canônica `chain:tx_hash` (mantendo retrocompatibilidade com hashes legados da Base). | **Must** |
| **RF07** | Incluir lista de redes aceitas (`supported_networks`) no desafio HTTP 402 e no endpoint `/api/v1/pass` para descoberta automática por agentes. | **Must** |
| **RF08** | Atualizar a UI Web3 (`/pass` e `#topup` em `index.php`) com seletor visual das 6 redes, troca automática via `wallet_switchEthereumChain` / `wallet_addEthereumChain` para EVM, e painel de pagamento + colagem de assinatura para Solana. | **Must** |
| **RF09** | Registrar `chain` / rede na telemetria e na notificação do Telegram (com link para o block explorer correto: BaseScan, Arbiscan, PolygonScan, Optimistic Etherscan, Etherscan ou Solscan). | **Should** |
| **RF10** | Atualizar documentação de descoberta (`/llms.txt`, `README.md`) documentando os pagamentos multi-chain EVM + Solana. | **Should** |

---

## 7. Requisitos não funcionais
- **Segurança:** Validação estrita de formato (`0x[a-f0-9]{64}` para EVM; Base58 de 64–90 chars para assinaturas Solana), verificação obrigatória de `status === 0x1` (EVM) e `meta.err === null` (Solana), SSL verificado (`CURLOPT_SSL_VERIFYPEER => true`), proteção contra SSRF (apenas RPCs em allowlist).
- **Performance:** Timeouts curtos por RPC (3s connect / 5s total) com failover automático entre 2+ endpoints RPC por rede.
- **Armazenamento Zero-DB:** Nenhuma dependência externa ou banco SQL; toda trava anti-replay e emissão de Session Pass permanece em JSON atômico com `flock`.
- **Retrocompatibilidade:** 100% dos testes existentes da Base Mainnet e x402 v2 continuam passando sem qualquer quebra.

---

## 8. Fluxos e UX

### 8.1. Fluxo Multi-Chain EVM (Session Pass ou Chamada Direta)
1. Agente/usuário seleciona Arbitrum, Polygon, Optimism, Ethereum ou Base.
2. Envia USDC oficial da respectiva rede para `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`.
3. Envia `tx_hash` + `chain` para `/api/v1/pass-emitir` (ou `X-Payment: arbitrum:0x...` em qualquer endpoint).
4. Grid402 consulta o RPC da rede selecionada, valida contrato USDC, destinatário e valor raw ($\ge$ mínimo exigido), grava a trava anti-replay e libera o Session Pass ou a resposta JSON assinada.

### 8.2. Fluxo Solana (SPL USDC)
1. Agente/usuário seleciona **Solana** na UI ou passa `chain: "solana"` na API.
2. Transfere SPL USDC (`EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`) para `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`.
3. Informa a assinatura Base58 da transação em `/api/v1/pass-emitir` ou via `X-Payment: solana:<signature>`.
4. Grid402 consulta `getTransaction` no RPC da Solana, calcula o delta líquido creditado (`postTokenBalances - preTokenBalances`) na conta de token USDC pertencente ao endereço oficial, trava contra replay e emite o Session Pass / resposta.

---

## 9. Métricas de sucesso
- Suporte verificado e testado para as **6 redes** (Base, Arbitrum, Polygon, Optimism, Ethereum, Solana).
- 100% dos cenários de validação EVM multi-chain, validação Solana SPL USDC, anti-replay cross-chain e fallback retrocompatível para Base cobertos em `tests/test_multichain_payments.php`.
- Zero regressões na suíte existente (`test_x402_gateway.php`, `test_session_pass_statement.php`, `test_dossier.php`, `test_agent_discovery.php`).

---

## 10. Riscos, dependências e premissas
- **Latência/Rate-limit de RPCs públicos:** Mitigado com lista de 2 a 3 RPCs públicos por rede com failover automático e suporte a sobrescrita via `.env` (`GRID402_RPC_<CHAIN>`).
- **USDC Bridged vs Native em L2s (ex: USDC.e vs USDC nativo em Arbitrum/Polygon):** Podemos aceitar o contrato oficial Circle Native USDC como padrão (e opcionalmente aceitar USDC.e oficial da Circle em Arbitrum/Polygon/Optimism para evitar perda de pagamento de usuários com saldo legado!).
- **Confirmação Solana (`finalized` vs `confirmed`):** Usar `commitment: "confirmed"` (finalidade prática sub-segundo na Solana) garantindo `meta.err === null`.

---

## 11. Questões em aberto
- [x] Redes definidas: Base, Arbitrum, Polygon, Optimism, Ethereum e Solana.
- [x] Endereço Solana confirmado: `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`.
- [x] Escopo confirmado: Session Pass + Micropagamento direto por chamada + Seletor Web3 em 1 clique na UI.
