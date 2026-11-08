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
O servidor SHALL permitir a validação gratuita de requisições em Modo Sandbox mediante o cabeçalho `X-Payment: demo` ou transações confirmadas na testnet Base Sepolia (84532), impondo limite de taxa defensivo por endereço IP.

#### Scenario: Header based simulation mode
- **WHEN** uma requisição técnica ou de telemetria envia o cabeçalho `X-Payment: demo` ou `X-Payment: sandbox`
- **THEN** o servidor valida a cota horária do IP, entrega a resposta completa dos dados com status HTTP 200, marca o payload com `"sandbox": true` e envia o cabeçalho `X-Payment-Mode: sandbox`

#### Scenario: Base Sepolia testnet transaction verification
- **WHEN** uma requisição técnica inclui `X-Payment: <tx_hash>` correspondente a uma transação confirmada na testnet Base Sepolia (Chain ID 84532)
- **THEN** o servidor valida o recibo contra o RPC da Sepolia, aplica a trava anti-replay e entrega o payload com HTTP 200 e `"sandbox": true`

#### Scenario: Sandbox rate limit enforcement
- **GIVEN** um endereço IP de cliente que já realizou 20 requisições de sandbox na última hora
- **WHEN** uma nova requisição com `X-Payment: demo` ou sandbox é recebida a partir do mesmo IP
- **THEN** o servidor recusa a entrega e responde com código HTTP 429 Too Many Requests e mensagem orientando a migração para a Base Mainnet

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
