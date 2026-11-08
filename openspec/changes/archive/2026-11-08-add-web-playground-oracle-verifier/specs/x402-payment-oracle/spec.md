# Spec Delta: x402-payment-oracle

## ADDED Requirements

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

