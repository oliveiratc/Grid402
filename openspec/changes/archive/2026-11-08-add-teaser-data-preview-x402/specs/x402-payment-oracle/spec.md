## MODIFIED Requirements

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

