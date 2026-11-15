## ADDED Requirements

### Requirement: Consistência Absoluta do Endereço da Carteira Oráculo
O sistema SHALL exibir exclusivamente o endereço oficial configurado em `GRID402_WALLET_ADDRESS` (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` e sua forma truncada `0xa592...3f90`) em todas as páginas HTML, desafios HTTP 402, rodapés e manifestos públicos, sem qualquer divergência entre o texto ancorado e o atributo `href` do explorador de blocos.

#### Scenario: Verificação do endereço da carteira no rodapé e na página
- **GIVEN** um usuário ou agente inspecionando o código HTML de `https://grid402.online`
- **WHEN** localizar qualquer link para `basescan.org/address/` ou menção à carteira recebedora
- **THEN** tanto a URL quanto o texto visível truncado SHALL corresponder a `0xa592...3f90` (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`), não existindo nenhuma ocorrência de `0xa31c...356c`.

### Requirement: Proteção contra Pagamento Duplicado via Idempotency-Key
O servidor SHALL suportar o cabeçalho opcional `Idempotency-Key` em todas as rotas pagas `/api/v1/*`, retendo em cache atômico a resposta assinada por 300 segundos para evitar débito duplicado de Session Pass ou falha de replay em retentativas de rede.

#### Scenario: Retentativa com mesma Idempotency-Key não debita crédito adicional
- **GIVEN** um agente autenticado com um Session Pass válido contendo 400 créditos
- **WHEN** o agente envia duas requisições idênticas para `/api/v1/forex` com o mesmo cabeçalho `Idempotency-Key: retry-test-001` dentro de 300 segundos
- **THEN** a primeira requisição debita 1 crédito (restando 399) e a segunda requisição retorna HTTP 200 com cabeçalho `X-Idempotent-Replay: true`, o mesmo payload assinado `_oracle_proof` e saldo inalterado em 399 créditos.

### Requirement: Consulta Gratuita de Saldo e Controles de Gasto do Session Pass (Spend Controls)
O servidor SHALL disponibilizar o endpoint gratuito `GET /api/v1/pass/balance` para consulta de créditos restantes, validade e consumo diário do Session Pass, além de permitir a configuração e imposição de limites de gasto (`daily_credit_limit` e `allowed_endpoints`) por passe.

#### Scenario: Consulta gratuita de saldo do Session Pass
- **GIVEN** um Session Pass ativo enviado no cabeçalho `X-Payment: pass_g402_...`
- **WHEN** o agente requisita `GET /api/v1/pass/balance`
- **THEN** o servidor SHALL responder com HTTP 200 gratuitamente (sem debitar créditos), informando `credits_total`, `credits_remaining`, `credits_used_today`, `daily_credit_limit`, `allowed_endpoints` e `expires_at`.

#### Scenario: Bloqueio automático ao atingir limite diário de créditos (Spend Control)
- **GIVEN** um Session Pass configurado com `daily_credit_limit: 5` que já consumiu 5 créditos na data UTC corrente
- **WHEN** o agente tenta executar uma nova chamada paga utilizando esse passe na mesma data
- **THEN** o servidor SHALL recusar a execução sem debitar créditos e responder com HTTP 429 (`error: "PassDailyLimitExceeded"`).

## MODIFIED Requirements

### Requirement: Web3 One-Click Top-Up de Passes no Portal
O portal web do Grid402 SHALL disponibilizar um componente visual interativo para aquisição em lote de Session Passes pré-pagos via carteiras Web3 (MetaMask, Coinbase Smart Wallet, Rainbow) na rede Base (Chain ID 8453) ou Pix/Cartão, transferindo USDC diretamente para a carteira do oráculo e exibindo a matemática unificada (`1 crédito = 0.0050 USDC`: Starter = 400 créditos, Pro = 1.000 créditos, Fleet = 6.000 créditos com bônus).

#### Scenario: Aquisição bem-sucedida de Session Pass via carteira Web3
- **GIVEN** um usuário navegando no portal `grid402.online` com uma carteira conectada na rede Base
- **WHEN** selecionar um plano (ex: Pro 5 USDC) e aprovar a transferência de 5 USDC para a carteira do oráculo
- **THEN** o frontend SHALL enviar o hash da transação confirmada para a API `/api/v1/pass`, receber o token `pass_g402_...` com 1.000 créditos e exibi-lo com botão de cópia rápida.

#### Scenario: Tentativa em rede incorreta
- **GIVEN** um usuário com carteira conectada em rede diferente de Base Mainnet (8453)
- **WHEN** clicar em comprar créditos
- **THEN** o sistema SHALL solicitar a alternância de rede para Base Mainnet via `wallet_switchEthereumChain`.

### Requirement: Emissão de Passe On-Demand por Transação USDC
O endpoint `/api/v1/pass` SHALL aceitar parâmetro `tx` (ou `txHash`), verificar o comprovante de transferência ERC-20 de USDC na Base Mainnet, creditar a cota correspondente ao valor transferido na razão `1 crédito = 0.0050 USDC` (2 USDC = 400 créditos; 5 USDC = 1.000 créditos; 20 USDC = 6.000 créditos incluindo bônus) e emitir um token criptograficamente assinado com HMAC.

#### Scenario: Emissão de passe para transação de 2 USDC
- **GIVEN** uma transação na Base confirmada transferindo 2.000000 USDC para a carteira do oráculo
- **WHEN** a API `/api/v1/pass?tx=0x...` for chamada
- **THEN** o servidor SHALL responder com HTTP 200, status `active`, total de 400 créditos (`1 crédito = 0.0050 USDC`), validade de 30 dias e token estruturado `pass_g402_{id}_{hmac}`.

#### Scenario: Transação duplicada ou já utilizada
- **GIVEN** uma transação que já teve seu passe emitido anteriormente
- **WHEN** a API for consultada novamente com a mesma transação
- **THEN** o servidor SHALL retornar o registro existente do passe sem gerar duplicidade de saldo.
