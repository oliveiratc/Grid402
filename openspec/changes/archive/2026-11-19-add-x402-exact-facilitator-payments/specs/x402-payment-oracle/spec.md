## ADDED Requirements

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

