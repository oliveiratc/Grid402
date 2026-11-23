# x402-payment-oracle Specification

## Purpose
Disponibilizar endpoint de telemetria pública e paga via protocolo HTTP x402 liquidado em USDC na rede Base, com interface adaptativa para navegadores e agentes M2M.

## Requirements

### Requirement: Adaptive content negotiation
O servidor SHALL inspecionar o cabeçalho HTTP `Accept` para alternar entre uma interface visual para humanos e respostas estruturadas para agentes e máquinas.

#### Scenario: Browser visit
- **WHEN** uma requisição é recebida com cabeçalho `Accept` contendo `text/html`
- **THEN** o servidor renderiza a Landing Page dark-mode em HTML/CSS puro com o status atual dos serviços e documentação da API

#### Scenario: Machine API request
- **WHEN** uma requisição é recebida com cabeçalho `Accept: application/json` ou sem especificação de HTML
- **THEN** o servidor responde no formato JSON com dados de telemetria ou desafio de pagamento x402

### Requirement: Free tier data delivery
O servidor SHALL disponibilizar o resumo gratuito de telemetria sem exigir autenticação ou pagamento.

#### Scenario: Requesting free summary
- **WHEN** um cliente consulta o endpoint raiz sem solicitar dados detalhados
- **THEN** o servidor retorna HTTP 200 contendo o conteúdo de `summary_free.json` com status geral e latência agregada

### Requirement: Payment required challenge
O servidor SHALL emitir um desafio HTTP 402 Payment Required quando dados detalhados ou endpoints técnicos de utilitários forem solicitados sem comprovante de liquidação on-chain, incluindo opcionalmente amostra parcial (preview) e lista de campos bloqueados (locked_fields) para enriquecimento da conversão.

#### Scenario: Requesting detailed telemetry without payment
- **WHEN** um cliente solicita telemetria detalhada (`?detail=true` ou rota de telemetria) sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalho `X-Authenticate: x402` e corpo JSON com os parâmetros da rede Base, contrato USDC, valor de US$ 0.0005 e endereço da carteira

#### Scenario: Requesting utility data without payment
- **WHEN** um cliente solicita qualquer endpoint técnico aprofundado (`/api/v1/forex`, `/api/v1/crypto`, `/api/v1/dns`, `/api/v1/ssl`, `/api/v1/asn`) sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalho `X-Authenticate: x402`, cabeçalho `X-Accept-Payment` e corpo JSON com os parâmetros da rede Base, contrato USDC, valor de US$ 0.0005 e carteira destinatária

#### Scenario: Requesting cadastral data with teaser preview without payment
- **WHEN** um cliente solicita dados cadastrais (`/api/v1/cnpj` ou `/api/v1/cep`) informando identificador válido porém sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalhos `X-Authenticate` e `X-Accept-Payment`, incluindo no JSON as chaves `preview` com os dados abertos e `locked_fields` com a relação de atributos que serão liberados no HTTP 200

### Requirement: On-chain payment verification
O servidor SHALL validar o comprovante de pagamento recebido no cabeçalho `X-Payment` diretamente contra o RPC da rede Base antes de liberar a resposta do utilitário ou telemetria requisitada.

#### Scenario: Valid payment proof
- **WHEN** uma requisição inclui `X-Payment: <tx_hash>` com transação confirmada de valor >= 0.0005 USDC direcionada à carteira do oráculo
- **THEN** o servidor valida o recibo on-chain, registra o hash como utilizado e entrega o payload de dados técnicos com HTTP 200

#### Scenario: Invalid or insufficient payment
- **WHEN** uma requisição inclui `X-Payment: <tx_hash>` com transação inexistente, falha, valor menor que o exigido ou destinatário incorreto
- **THEN** o servidor recusa a entrega e responde com HTTP 402 Payment Required

#### Scenario: Valid payment for utility
- **WHEN** uma requisição técnica inclui `X-Payment: <tx_hash>` com transação confirmada de valor >= 0.0005 USDC direcionada à carteira do oráculo na Base
- **THEN** o servidor valida o recibo on-chain, grava o hash e entrega o payload técnico do utilitário com HTTP 200 OK

### Requirement: Replay attack prevention
O servidor SHALL impedir atomicamente a reutilização de um mesmo hash de transação para múltiplas requisições de dados pagos mediante bloqueio de arquivo com trava exclusiva (`flock`).

#### Scenario: Reused transaction hash
- **WHEN** uma requisição envia um `X-Payment: <tx_hash>` que já consta no arquivo `used_txs.json`
- **THEN** o servidor recusa a requisição retornando HTTP 403 Forbidden com indicação de replay attack detectado

#### Scenario: Concurrent payment race condition
- **WHEN** múltiplas requisições paralelas enviam simultaneamente o mesmo `tx_hash` no mesmo instante
- **THEN** a trava atômica `flock` garante que estritamente uma única requisição seja aprovada e todas as demais sejam rejeitadas com HTTP 403

### Requirement: Private storage isolation
O sistema SHALL bloquear completamente qualquer tentativa de acesso HTTP direto aos arquivos dentro do diretório de dados privados.

#### Scenario: Direct HTTP access attempt
- **WHEN** um cliente tenta acessar diretamente pela URL qualquer arquivo em `/private_data/`
- **THEN** o servidor web LiteSpeed/Apache responde imediatamente com HTTP 403 Forbidden via `.htaccess`

### Requirement: Sandbox and testnet simulation mode
O servidor SHALL permitir a validação gratuita de requisições em Modo Sandbox mediante o cabeçalho `X-Payment: demo` ou transações confirmadas na testnet Base Sepolia (84532), impondo limite defensivo padrão de **3 requisições por dia (janela de 24 horas / 86400s) por endereço IP** (sobrescrevível por variáveis de ambiente `GRID402_SANDBOX_RATE_LIMIT` e `GRID402_SANDBOX_WINDOW_SECONDS`), com orientações de upgrade para Session Pass (Pix R$ 12) ou Multi-Chain USDC.

#### Scenario: Header based simulation mode
- **WHEN** uma requisição técnica ou de telemetria envia o cabeçalho `X-Payment: demo` ou `X-Payment: sandbox` dentro da cota diária do IP
- **THEN** o servidor valida a cota diária do IP (`3 req/dia`), entrega a resposta completa dos dados com status HTTP 200, marca o payload com `"sandbox": true`, inclui `sandbox_info` com `quota_remaining_today` / `quota_limit_per_day` e CTA de upgrade, e envia o cabeçalho `X-Payment-Mode: sandbox`

#### Scenario: Base Sepolia testnet transaction verification
- **WHEN** uma requisição técnica inclui `X-Payment: <tx_hash>` correspondente a uma transação confirmada na testnet Base Sepolia (Chain ID 84532)
- **THEN** o servidor valida o recibo contra o RPC da Sepolia, aplica a trava anti-replay e entrega o payload com HTTP 200 e `"sandbox": true`

#### Scenario: Sandbox rate limit enforcement
- **GIVEN** um endereço IP de cliente que já realizou 3 requisições de sandbox nas últimas 24 horas
- **WHEN** uma 4ª requisição com `X-Payment: demo` ou `sandbox` é recebida a partir do mesmo IP
- **THEN** o servidor recusa a entrega e responde com código HTTP 429 Too Many Requests, cabeçalho `Retry-After` e payload JSON contendo `upgrade_to_production` apontando para `https://grid402.online/pass`

### Requirement: Cryptographic oracle signature on paid responses
O servidor SHALL assinar digitalmente o payload de dados tecnicos entregues em chamadas pagas (HTTP 200) e em modo Sandbox utilizando a chave privada da carteira oraculo via ECDSA secp256k1, anexando o bloco estruturado `_oracle_proof` ao resultado.

#### Scenario: Paid response with valid oracle proof
- **GIVEN** uma requisicao tecnica contendo comprovante valido de pagamento `X-Payment` na rede Base
- **WHEN** o servidor processa a resposta com status HTTP 200
- **THEN** o payload de resposta inclui o objeto `_oracle_proof` contendo `oracle_address`, `timestamp`, `data_hash`, `signature`, `algorithm` e `"sandbox": false`

#### Scenario: Sandbox response with oracle proof
- **GIVEN** uma requisicao tecnica contendo cabecalho `X-Payment: demo`
- **WHEN** o servidor processa a resposta em modo de simulacao com status HTTP 200
- **THEN** o payload inclui o objeto `_oracle_proof` assinado com `"sandbox": true` para possibilitar auditoria em testes sem custos

#### Scenario: Oracle proof omitted on HTTP 402 challenge
- **GIVEN** uma requisicao tecnica recebida sem comprovante de pagamento
- **WHEN** o servidor emite o desafio HTTP 402 Payment Required
- **THEN** o objeto `_oracle_proof` nao e emitido, entregando apenas o desafio de pagamento estruturado e a amostra gratuita

### Requirement: Public oracle proof verification endpoint
O servidor SHALL disponibilizar o endpoint `/api/v1/verify` para validar a autenticidade e a integridade de qualquer resposta do Grid402 que contenha o bloco `_oracle_proof`.

#### Scenario: Verificacao bem-sucedida de prova valida
- **WHEN** uma requisicao POST contendo um payload legitimo com `_oracle_proof` e enviada para `/api/v1/verify`
- **THEN** o servidor retorna HTTP 200 com JSON indicando `"valid": true`, endereco da carteira oficial e tempo de verificacao

#### Scenario: Deteccao de divergencia no hash do payload
- **WHEN** uma requisicao POST com payload cujos dados foram alterados e enviada para `/api/v1/verify`
- **THEN** o servidor retorna HTTP 200 com JSON indicando `"valid": false`, `"error": "DataHashMismatch"` e os hashes esperado e recebido

#### Scenario: Deteccao de assinatura forjada ou invalida
- **WHEN** uma requisicao POST com bloco `_oracle_proof` contendo assinatura corrompida e enviada para `/api/v1/verify`
- **THEN** o servidor retorna HTTP 200 com JSON indicando `"valid": false` e `"error": "InvalidSignature"`

### Requirement: Telegram real-time purchase notification
O servidor SHALL emitir uma notificacao push instantanea para o Telegram do mantenedor sempre que um micropagamento on-chain em USDC na rede Base Mainnet for confirmado com sucesso e retido contra replay attack.

#### Scenario: Real payment notification on Base Mainnet
- GIVEN o token do bot `GRID402_TELEGRAM_BOT_TOKEN` e o chat ID `GRID402_TELEGRAM_CHAT_ID` configurados no ambiente
- WHEN um agente de IA envia um `X-Payment` valido com valor >= 0.0005 USDC liquidado na Base Mainnet
- THEN o servidor valida o recibo on-chain, registra o hash como utilizado e dispara uma mensagem ao Telegram contendo o nome do servico, o valor em USDC, o horario UTC e o link de auditoria no BaseScan

#### Scenario: Silent skip on missing credentials
- GIVEN as variaveis `GRID402_TELEGRAM_BOT_TOKEN` ou `GRID402_TELEGRAM_CHAT_ID` ausentes ou vazias no ambiente
- WHEN um pagamento valido e liquidado na Base Mainnet
- THEN o servidor nao tenta contato externo com a Telegram Bot API e entrega a resposta do servico com HTTP 200 normalmente

#### Scenario: Non-blocking resilience on Telegram API failure
- GIVEN a API do Telegram indisponivel, retornando erro HTTP 5xx ou sofrendo timeout de rede
- WHEN uma transacao valida e confirmada na Base Mainnet
- THEN a chamada de notificacao atinge o timeout maximo de 1 segundo, o erro e capturado silenciosamente e o agente de IA recebe seus dados com HTTP 200 sem qualquer interrupcao

#### Scenario: Exclusion of sandbox and free requests
- GIVEN uma requisicao autenticada como sandbox ou consulta ao resumo gratuito
- WHEN a resposta e processada com HTTP 200
- THEN nenhuma notificacao e enviada para o canal de compras do Telegram

### Requirement: Web3 One-Click Top-Up de Passes no Portal
O portal web do Grid402 SHALL disponibilizar um componente visual interativo para aquisição em lote de Session Passes pré-pagos via carteiras Web3 (MetaMask, Coinbase Smart Wallet, Rainbow) na rede Base (Chain ID 8453) ou Pix/Cartão, transferindo USDC diretamente para a carteira do oráculo e exibindo a matemática unificada (`1 crédito = 0.0050 USDC`: Starter = 400 créditos / 180 dias, Pro = 1.200 créditos com +20% bônus / 180 dias, Fleet = 6.000 créditos com +50% bônus / 365 dias).

#### Scenario: Aquisição bem-sucedida de Session Pass via carteira Web3
- **GIVEN** um usuário navegando no portal `grid402.online` com uma carteira conectada na rede Base
- **WHEN** selecionar um plano (ex: Pro 5 USDC) e aprovar a transferência de 5 USDC para a carteira do oráculo
- **THEN** o frontend SHALL enviar o hash da transação confirmada para a API `/api/v1/pass`, receber o token `pass_g402_...` com 1.200 créditos (validade de 180 dias) e exibi-lo com botão de cópia rápida.

#### Scenario: Tentativa em rede incorreta
- **GIVEN** um usuário com carteira conectada em rede diferente de Base Mainnet (8453)
- **WHEN** clicar em comprar créditos
- **THEN** o sistema SHALL solicitar a alternância de rede para Base Mainnet via `wallet_switchEthereumChain`.

### Requirement: Emissão de Passe On-Demand por Transação USDC
O endpoint `/api/v1/pass` SHALL aceitar parâmetro `tx` (ou `txHash`), verificar o comprovante de transferência ERC-20 de USDC na Base Mainnet, creditar a cota correspondente ao valor transferido na razão `1 crédito = 0.0050 USDC` (2 USDC = 400 créditos / 180 dias; 5 USDC = 1.200 créditos incluindo +20% bônus / 180 dias; 20 USDC = 6.000 créditos incluindo +50% bônus / 365 dias) e emitir um token criptograficamente assinado com HMAC.

#### Scenario: Emissão de passe para transação de 2 USDC
- **GIVEN** uma transação na Base confirmada transferindo 2.000000 USDC para a carteira do oráculo
- **WHEN** a API `/api/v1/pass?tx=0x...` for chamada
- **THEN** o servidor SHALL responder com HTTP 200, status `active`, total de 400 créditos (`1 crédito = 0.0050 USDC`), validade de 180 dias e token estruturado `pass_g402_{id}_{hmac}`.

#### Scenario: Transação duplicada ou já utilizada
- **GIVEN** uma transação que já teve seu passe emitido anteriormente
- **WHEN** a API for consultada novamente com a mesma transação
- **THEN** o servidor SHALL retornar o registro existente do passe sem gerar duplicidade de saldo.

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

### Requirement: Rastreabilidade via X-Request-Id e Log Estruturado JSONL Zero-PII
O gateway HTTP do Grid402 SHALL emitir o cabeçalho de correlação `X-Request-Id` em todas as respostas da API e gravar eventos estruturados em formato JSON Lines (`private_data/requests.jsonl`) com proteção de concorrência (`flock`) e rotação automática ao atingir `10 MB`, sem registrar dados pessoais identificáveis (Zero-PII) nem segredos ou tokens completos de autenticação.

#### Scenario: Emissao de X-Request-Id e registro JSONL sanitizado
- **GIVEN** uma requisição recebida em `/api/v1/forex` ou `/status` contendo cabeçalho opcional `X-Payment: pass_g402_secret_token_example`
- **WHEN** o gateway conclui o processamento HTTP
- **THEN** a resposta HTTP SHALL incluir o cabeçalho `X-Request-Id`
- **AND** uma nova linha JSONL válida SHALL ser anexada ao arquivo de logs contendo `ts`, `request_id`, `service`, `status`, `latency_ms`, `payment_source` e `ip_hmac`
- **AND** a linha gravada SHALL NOT conter o token `pass_g402_...` completo nem o endereço IP em texto claro.

#### Scenario: Rotacao automatica do arquivo de log ao atingir limite de tamanho
- **GIVEN** um arquivo de log JSONL ativo com tamanho igual ou superior ao limite configurado (padrão `10 MB`)
- **WHEN** um novo evento de requisição é registrado pelo logger
- **THEN** o sistema SHALL rotacionar atomicamente o arquivo atual para o sufixo `.1` e iniciar um novo `requests.jsonl` limpo sem perda do evento corrente.

### Requirement: Comprovacao Automatizada de Concorrencia Paralela e Carga
O repositório SHALL incluir uma suíte automatizada de testes de concorrência real multi-processo e carga (`tests/test_concurrency_and_load.php`) validando atomicidade contra double-spend no último crédito do Session Pass, replay concorrente de `tx_hash`/`payer:nonce` e latências `p50`/`p95`/`p99`.

#### Scenario: Disputa paralela pelo ultimo credito de um Session Pass
- **GIVEN** um Session Pass válido com exatamente 1 crédito restante
- **WHEN** múltiplos processos concorrentes tentam consumir 1 crédito simultaneamente sobre o mesmo arquivo de passes
- **THEN** estritamente 1 processo SHALL obter autorização (`valid: true`) e todos os demais SHALL ser rejeitados (`PassExhausted`), mantendo `credits_remaining === 0`.

#### Scenario: Replay concorrente de tx_hash e payer:nonce
- **GIVEN** um `tx_hash` inédito e um par `payer:nonce` inédito
- **WHEN** múltiplos processos concorrentes tentam adquirir a trava simultaneamente para cada identificador
- **THEN** estritamente 1 processo SHALL obter o lock de cada identificador e todos os demais SHALL falhar imediatamente.

### Requirement: Emissão e Consumo de Passe de Sessão Pré-pago x402 (REQ-PASS-001)
O sistema SHALL permitir a emissão de passes de sessão pré-pagos (`pass_g402_<id>_<hmac>`) através do endpoint `/api/v1/pass` mediante liquidação em USDC na Base (escala oficial `v2_5000`: `0.0050 USDC` = 1 crédito universal; Starter `2 USDC` = 400 créditos / 180 dias, Pro `5 USDC` = 1.200 créditos / 180 dias, Fleet `20 USDC` = 6.000 créditos / 365 dias) e aceitar esse token no cabeçalho `X-Payment` em todos os micro-serviços do Grid402.

#### Scenario: Emissão de Session Pass e débito atômico em chamadas subsequentes
- GIVEN que um cliente ou agente obtém um token `pass_g402_...` com créditos ativos
- WHEN realiza uma chamada para qualquer endpoint (ex: `/api/v1/forex` ou `/api/v1/lei`) enviando `X-Payment: pass_g402_...`
- THEN o sistema valida o HMAC e saldo em disco com lock exclusivo, debita os créditos correspondentes ao preço do serviço e retorna HTTP 200 imediatamente sem consultar o RPC da blockchain

#### Scenario: Consulta gratuita de saldo de um Session Pass
- GIVEN um token `pass_g402_...` emitido anteriormente
- WHEN o agente consulta `GET /api/v1/pass?status=pass_g402_...` sem precisar de novo pagamento
- THEN o sistema retorna HTTP 200 com `credits_total`, `credits_remaining`, `expires_at` e `active`

#### Scenario: Rejeição de Session Pass com saldo insuficiente ou assinatura inválida
- GIVEN um token com créditos esgotados ou HMAC adulterado
- WHEN enviado no cabeçalho `X-Payment`
- THEN o sistema rejeita a requisição com HTTP 402/403 informando o motivo exato

### Requirement: Abstração de Repositório Zero-DB para Session Passes (PassStorageInterface)
O sistema SHALL definir o contrato `PassStorageInterface` e a implementação oficial `JsonFlockPassRepository` baseada em JSON + `flock()`, concentrando emissão, consulta de saldo, atualização de Spend Controls, consumo atômico e extrato sem exigir extensões SQL externas (`pdo_sqlite` ou `pdo_mysql`).

#### Scenario: Operação completa via PassStorageInterface sem dependências SQL
- **GIVEN** um ambiente PHP 8.1+ sem `pdo_sqlite` nem `pdo_mysql` habilitados
- **WHEN** o gateway emite um Session Pass, configura Spend Controls e debita créditos através das funções de `services/pass.php`
- **THEN** todas as operações SHALL executar por meio de `JsonFlockPassRepository` (implementando `PassStorageInterface`) usando `flock()` e `grid402_read_locked_json()`, mantendo atomicidade estrita contra double-spend.

---

### Requirement: Registro Zero-PII em Ring Buffer de 200 Lançamentos no Extrato do Session Pass
O sistema SHALL registrar atomicamente em arquivo dedicado (`pass_statements.json`), limitado a um ring buffer de no máximo `200` eventos recentes por `pass_id`, os desfechos `consumed` (`200`), `endpoint_restricted` (`403`), `daily_limit_exceeded` (`429`) e `idempotent_replay` (`200`), armazenando apenas metadados operacionais Zero-PII.

#### Scenario: Registro de débito aprovado, bloqueios 403/429 e replay idempotente sem PII
- **GIVEN** um Session Pass ativo configurado com `allowed_endpoints: ["forex"]` e `daily_credit_limit: 1`
- **WHEN** o cliente realiza (a) consulta válida em `/api/v1/forex?base=BRL` com `Idempotency-Key`, (b) retentativa com mesma `Idempotency-Key`, (c) chamada proibida em `/api/v1/cnpj?cnpj=00000000000191` (`403`) e (d) nova chamada em `/api/v1/forex` excedendo o limite diário (`429`)
- **THEN** o extrato do passe SHALL conter respectivamente os 4 eventos com `outcome` igual a `consumed` (`credits_debited: 1`, `http_status: 200`), `idempotent_replay` (`credits_debited: 0`, `http_status: 200`), `endpoint_restricted` (`credits_debited: 0`, `http_status: 403`) e `daily_limit_exceeded` (`credits_debited: 0`, `http_status: 429`)
- **AND** nenhum lançamento SHALL armazenar parâmetros de query (`00000000000191`), segredos ou endereço IP.

#### Scenario: Ring buffer mantém estritamente no máximo 200 lançamentos recentes por passe
- **GIVEN** um Session Pass com mais de `200` chamadas registradas sequencialmente
- **WHEN** o histórico do passe é persistido pelo repositório
- **THEN** o armazenamento SHALL reter exatamente os `200` eventos mais recentes (`stored_entries === 200`), descartando os mais antigos sem degradar `session_passes.json`.

---

### Requirement: Endpoint Gratuito de Extrato por Session Pass (JSON e CSV Seguro)
O gateway SHALL expor gratuitamente a rota `GET /api/v1/pass/statement` autenticada pelo token `pass_g402_...` (via query `?token=`, cabeçalho `X-Payment` ou `Authorization: Bearer`), com filtros opcionais `?endpoint=` e `?outcome=` e exportação `?format=json` (padrão) ou `?format=csv` (UTF-8 com BOM e proteção anti CSV injection).

#### Scenario: Exportação JSON com filtros e rejeição segura de token inválido
- **GIVEN** uma requisição `GET /api/v1/pass/statement` com token válido e filtro `?endpoint=forex&outcome=consumed`
- **WHEN** o servidor processa a requisição
- **THEN** responde com HTTP `200`, zero débito de créditos, cabeçalho `X-Request-Id`, bloco `retention` (`max_entries_per_pass: 200`, `support_contact: "contato@grid402.online"`), `summary` consolidado e `entries` filtradas em ordem decrescente de timestamp
- **AND** caso o token seja inválido no formato HMAC ou inexistente, responde respectivamente com HTTP `400` (`InvalidPassTokenFormat`) ou HTTP `404` (`PassNotFound`).

#### Scenario: Exportação CSV segura com BOM UTF-8 e neutralização contra CSV Injection
- **GIVEN** uma requisição `GET /api/v1/pass/statement?token=pass_g402_...&format=csv` onde existe célula de valor iniciado por `=`, `+`, `-`, `@`, `\t` ou `\r` (ex.: em `request_id` personalizado)
- **WHEN** o servidor gera o CSV de download
- **THEN** responde com HTTP `200`, `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment`, prefixo BOM UTF-8 e qualquer célula iniciada por caractere de fórmula SHALL ser prefixada com apóstrofo simples (`'`).

### Requirement: Rota Canônica de Dossiê 360º KYB (`/api/v1/dossier`)
O sistema MUST disponibilizar a rota `GET /api/v1/dossier?cnpj={cnpj}` aceitando CNPJ com ou sem pontuação e retornando HTTP 400 (`InvalidCNPJ`) quando o CNPJ for matematicamente inválido.

#### Scenario: Consulta de CNPJ inválido retorna HTTP 400
- GIVEN um cliente autenticado em Modo Sandbox (`X-Payment: demo`)
- WHEN envia `GET /api/v1/dossier?cnpj=12345678000100`
- THEN o sistema responde HTTP 400 com `error: "InvalidCNPJ"` sem debitar créditos.

#### Scenario: Consulta de CNPJ formatado ou sem máscara é aceita
- GIVEN um CNPJ válido (`00.000.000/0001-91` ou `00000000000191`)
- WHEN o cliente envia `GET /api/v1/dossier?cnpj=00.000.000/0001-91` com pagamento válido ou sandbox
- THEN o sistema normaliza o CNPJ para 14 dígitos e retorna HTTP 200 com `service: "dossier"`.

### Requirement: Precificação Premium x402 de 0.0500 USDC e Teaser Gratuito no 402
O sistema MUST exigir pagamento de `0.0500` USDC (50000 unidades raw na Base Mainnet ou 10 créditos de Session Pass) para o endpoint `/api/v1/dossier`, e devolver uma prévia segura (Teaser) no desafio HTTP 402.

#### Scenario: Chamada sem pagamento retorna HTTP 402 com preço 0.0500 USDC e Teaser
- GIVEN uma requisição `GET /api/v1/dossier?cnpj=00000000000191` sem cabeçalho de pagamento
- WHEN o gateway x402 processa a chamada
- THEN retorna HTTP 402 com `price_usdc: "0.0500"`, `credits_cost: 10`, carteira oficial do oráculo e bloco `teaser_data` contendo razão social, UF e indicação de existência de sanções.

#### Scenario: Liquidação via Session Pass debita exatamente 10 créditos
- GIVEN um Session Pass válido com saldo de 20 créditos
- WHEN o agente envia `GET /api/v1/dossier?cnpj=00000000000191` com cabeçalho `X-Session-Pass`
- THEN o sistema retorna HTTP 200, debita exatamente 10 créditos (`_billing.credits_debited: 10`) e reporta saldo restante de 10 créditos.

### Requirement: Consolidação de Fontes Oficiais e Motor de Veredito para IA (`verdict`)
O sistema MUST consolidar dados da Receita Federal (CNPJ), Simples Nacional/MEI, Sanções CGU (CEIS/CNEP) e Compliance Score 360, produzindo o bloco `verdict` com `status` (`APPROVED`, `FLAGGED`, `REJECTED`), `score` (0 a 100), `alerts` e `summary` executivo.

#### Scenario: Empresa ativa, sem sanções e score >= 70 recebe veredito APPROVED
- GIVEN uma empresa com situação cadastral `ATIVA`, sem registros no CEIS/CNEP e score >= 70
- WHEN o serviço `grid402_service_dossier` consolida o dossiê
- THEN `verdict.status` é `"APPROVED"`, `verdict.risk_level` é `"LOW"` e `verdict.summary` confirma a aptidão cadastral.

#### Scenario: Empresa com sanção ativa no CEIS/CNEP ou situação inapta recebe veredito REJECTED
- GIVEN um CNPJ sancionado no CEIS/CNEP (ex: `11111111000191`) ou com situação diferente de `ATIVA`
- WHEN o serviço `grid402_service_dossier` consolida o dossiê
- THEN `verdict.status` é `"REJECTED"`, `verdict.risk_level` é `"HIGH"` e `verdict.alerts` lista explicitamente a restrição encontrada.

#### Scenario: Empresa ativa sem sanções mas com score < 70 ou alerta de atenção recebe veredito FLAGGED
- GIVEN uma empresa `ATIVA` sem sanções na CGU, porém com score inferior a 70 ou alerta cadastral de atenção
- WHEN o serviço `grid402_service_dossier` consolida o dossiê
- THEN `verdict.status` é `"FLAGGED"`, `verdict.risk_level` é `"MEDIUM"` e `verdict.alerts` detalha os pontos para revisão.

### Requirement: Garantia de Zero PII e Assinatura Criptográfica ECDSA (`_oracle_proof`)
O sistema MUST excluir qualquer dado pessoal de sócios (Zero PII / sem QSA ou CPF) e assinar toda resposta HTTP 200 do Dossiê 360º com `_oracle_proof` (ECDSA secp256k1) e `_meta`.

#### Scenario: Resposta do Dossiê 360º não contém PII e inclui prova criptográfica
- GIVEN uma chamada bem-sucedida a `GET /api/v1/dossier?cnpj=00000000000191`
- WHEN a resposta JSON é inspecionada
- THEN não existem chaves `qsa`, `socios` ou `cpf` na resposta, e os blocos `_oracle_proof` (com `signature`, `data_hash` e `algorithm`) e `_meta` estão presentes.

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

### Requirement: Coexistência de formatos de prova de pagamento
O sistema MUST aceitar simultaneamente prova por `tx_hash` (`0x` + 64 hex), Session Pass (`pass_g402_...`), modo sandbox (`demo`) e payload x402 padrão em base64, identificando o formato antes de validar.

#### Scenario: Fluxo por tx_hash preservado
- **WHEN** uma requisição envia `X-Payment: 0x<64 hex>` de transação válida na Base
- **THEN** o sistema valida on-chain como antes e retorna HTTP 200

#### Scenario: Payload x402 padrão encaminhado ao novo fluxo
- **WHEN** uma requisição envia `PAYMENT-SIGNATURE` ou um `X-PAYMENT` em base64 JSON
- **THEN** o sistema encaminha a prova ao fluxo de liquidação por facilitador, sem tratá-la como `tx_hash`

#### Scenario: Prova em formato desconhecido
- **WHEN** a prova não corresponde a nenhum formato suportado
- **THEN** o sistema responde HTTP 400 listando os formatos aceitos e o passo a passo de pagamento

### Requirement: Desafio 402 compatível com clientes padrão
O sistema MUST emitir um desafio 402 cujo `accepts` e cabeçalho `Payment-Required` possam ser atendidos por clientes x402 padrão, informando também a alternativa por `tx_hash` e o modo sandbox.

#### Scenario: Cliente padrão conclui o pagamento a partir do desafio
- **WHEN** um cliente x402 padrão recebe o desafio 402 de um serviço pago
- **THEN** ele consegue montar a autorização a partir de `accepts` e repetir a requisição com `PAYMENT-SIGNATURE` sem intervenção manual

#### Scenario: Instruções alternativas no corpo do desafio
- **WHEN** o desafio 402 é emitido
- **THEN** o corpo JSON mantém `price_usdc`, `recipient_wallet` e `action_required` descrevendo o fluxo por `tx_hash` e o uso de `X-Payment: demo`
