## ADDED Requirements

### Requirement: Web3 One-Click Top-Up de Passes no Portal
O portal web do Grid402 SHALL disponibilizar um componente visual interativo para aquisição em lote de Session Passes pré-pagos via carteiras Web3 (MetaMask, Coinbase Smart Wallet, Rainbow) na rede Base (Chain ID 8453), transferindo USDC diretamente para a carteira do oráculo.

#### Scenario: Aquisição bem-sucedida de Session Pass via carteira Web3
- GIVEN um usuário navegando no portal `grid402.online` com uma carteira conectada na rede Base
- WHEN selecionar um plano (ex: Pro 5 USDC) e aprovar a transferência de 5 USDC para a carteira do oráculo
- THEN o frontend SHALL enviar o hash da transação confirmada para a API `/api/v1/pass`, receber o token `pass_g402_...` com 10.000 requisições e exibi-lo com botão de cópia rápida.

#### Scenario: Tentativa em rede incorreta
- GIVEN um usuário com carteira conectada em rede diferente de Base Mainnet (8453)
- WHEN clicar em comprar créditos
- THEN o sistema SHALL solicitar a alternância de rede para Base Mainnet via `wallet_switchEthereumChain`.

### Requirement: Emissão de Passe On-Demand por Transação USDC
O endpoint `/api/v1/pass` SHALL aceitar parâmetro `tx` (ou `txHash`), verificar o comprovante de transferência ERC-20 de USDC na Base Mainnet, creditar a cota correspondente ao valor transferido e emitir um token criptograficamente assinado com HMAC.

#### Scenario: Emissão de passe para transação de 2 USDC
- GIVEN uma transação na Base confirmada transferindo 2.000000 USDC para a carteira do oráculo
- WHEN a API `/api/v1/pass?tx=0x...` for chamada
- THEN o servidor SHALL responder com HTTP 200, status `active`, total de 4.000 créditos, validade de 30 dias e token estruturado `pass_g402_{id}_{hmac}`.

#### Scenario: Transação duplicada ou já utilizada
- GIVEN uma transação que já teve seu passe emitido anteriormente
- WHEN a API for consultada novamente com a mesma transação
- THEN o servidor SHALL retornar o registro existente do passe sem gerar duplicidade de saldo.

