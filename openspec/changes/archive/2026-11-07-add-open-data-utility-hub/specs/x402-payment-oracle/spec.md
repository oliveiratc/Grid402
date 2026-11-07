# x402-payment-oracle Delta Specification

## Purpose
Atualizar o oráculo de pagamento x402 para cobrir os novos endpoints de utilitários públicos com trava atômica anti-replay contra condições de corrida (*TOCTOU*).

## MODIFIED Requirements

### Requirement: Payment required challenge
O servidor SHALL emitir um desafio HTTP 402 Payment Required quando dados detalhados ou endpoints técnicos de utilitários forem solicitados sem comprovante de liquidação on-chain.

#### Scenario: Requesting detailed telemetry without payment
- **WHEN** um cliente solicita telemetria detalhada (`?detail=true` ou rota de telemetria) sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalho `X-Authenticate: x402` e corpo JSON com os parâmetros da rede Base, contrato USDC, valor de US$ 0.0005 e endereço da carteira

#### Scenario: Requesting utility data without payment
- **WHEN** um cliente solicita qualquer endpoint técnico aprofundado (`/api/v1/forex`, `/api/v1/crypto`, `/api/v1/dns`, `/api/v1/ssl`, `/api/v1/asn`, `/api/v1/cep`) sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalho `X-Authenticate: x402`, cabeçalho `X-Accept-Payment` e corpo JSON com os parâmetros da rede Base, contrato USDC, valor de US$ 0.0005 e carteira destinatária

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
