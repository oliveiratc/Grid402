## ADDED Requirements

### Requirement: Python client oracle proof verification
O SDK Python (`grid402`) SHALL expor o metodo `verify_proof(payload, expected_oracle_address=None)` e o modelo Pydantic `OracleProof` para validacao criptografica local da integridade dos dados e autoria da carteira do oraculo.

#### Scenario: Verifying untampered response in Python
- **GIVEN** uma resposta da API Grid402 contendo o bloco `_oracle_proof` integro
- **WHEN** o metodo `client.verify_proof(response)` e executado
- **THEN** ele retorna um dicionario `{ "valid": True, "oracle_address": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c" }` confirmando a autenticidade

#### Scenario: Rejecting tampered response in Python
- **GIVEN** uma resposta cujo conteudo de dados foi adulterado apos a assinatura
- **WHEN** o metodo `client.verify_proof(response)` e executado
- **THEN** ele detecta a divergencia e retorna `{ "valid": False, "error": "DataHashMismatch" }`

