## ADDED Requirements

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

