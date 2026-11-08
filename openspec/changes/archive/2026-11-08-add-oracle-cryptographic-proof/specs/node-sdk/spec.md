## ADDED Requirements

### Requirement: Node client oracle proof verification
O SDK Node.js e TypeScript (`grid402`) SHALL expor o metodo `verifyProof(payload, expectedOracleAddress?)` e a funcao estatica `verifyOracleProof(...)` para validacao criptografica local instantanea da integridade dos dados e autoria do oraculo.

#### Scenario: Verifying untampered response payload
- **GIVEN** uma resposta da API Grid402 contendo o bloco `_oracle_proof` integro
- **WHEN** o metodo `client.verifyProof(response)` e executado
- **THEN** ele retorna `{ valid: true, oracleAddress: "0xa31c31069c43ac1c0075a93c325b0dcd7744356c" }` confirmando a autenticidade

#### Scenario: Rejecting tampered response payload
- **GIVEN** uma resposta cujo conteudo de dados foi adulterado apos a emissao da assinatura
- **WHEN** o metodo `client.verifyProof(response)` e executado
- **THEN** ele detecta a divergencia do hash e retorna `{ valid: false, error: "DataHashMismatch" }` impedindo o uso de dados forjados

