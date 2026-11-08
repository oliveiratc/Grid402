## ADDED Requirements

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

