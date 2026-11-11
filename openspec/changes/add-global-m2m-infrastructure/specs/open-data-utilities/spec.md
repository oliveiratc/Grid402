## ADDED Requirements

### Requirement: Consulta Global de Identidade Corporativa GLEIF LEI (REQ-GLOBAL-001)
O sistema DEVE prover o endpoint `/api/v1/lei` consultando a base pública CC0 da GLEIF (ISO 17442) por código `lei` de 20 caracteres ou busca textual `q`, retornando razão social, jurisdição, status cadastral, endereço legal e autoridade de registro.

#### Scenario: Consulta de LEI válido com pagamento ou passe
- GIVEN uma requisição GET para `/api/v1/lei?lei=5493006MHB84DD0ZWV18` com header `X-Payment` válido
- WHEN o serviço processa o código LEI
- THEN o sistema retorna HTTP 200 com `legal_name`, `jurisdiction`, `entity_status`, `registration_status` e `_oracle_proof` assinado

#### Scenario: Desafio 402 com amostra prévia no endpoint LEI
- GIVEN uma requisição GET para `/api/v1/lei?lei=5493006MHB84DD0ZWV18` sem header `X-Payment`
- WHEN o oráculo emite o desafio x402
- THEN retorna HTTP 402 com tarifa `0.0020 USDC`, bloco `preview` contendo o código LEI e jurisdição, e `locked_fields`

---

### Requirement: Inspeção Internacional de Sanções OFAC e AML Web3 (REQ-GLOBAL-002)
O sistema DEVE prover o endpoint `/api/v1/ofac` verificando carteiras EVM (`address`) e/ou nomes de entidades (`name`) contra listas públicas de sanções internacionais (OFAC SDN / ONU) e consultando a telemetria on-chain na Base Mainnet.

#### Scenario: Verificação de endereço sancionado conhecido
- GIVEN uma requisição para `/api/v1/ofac?address=0x8589427373D6D84E98730D7795D8f6f8731FDA16` com pagamento válido
- WHEN o serviço analisa o endereço
- THEN retorna HTTP 200 com `sanctioned: true`, `risk_level: "CRITICAL"` e a referência da lista OFAC SDN

#### Scenario: Verificação de carteira limpa na Base Mainnet
- GIVEN uma requisição para `/api/v1/ofac?address=0xa31c31069c43ac1c0075a93c325b0dcd7744356c` com pagamento válido
- WHEN o serviço analisa o endereço e consulta o RPC da Base
- THEN retorna HTTP 200 com `sanctioned: false`, `risk_level: "LOW"` e o objeto `onchain_profile` com tipo de conta e saldos

---

### Requirement: Carimbo de Tempo Criptográfico e Notarização M2M (REQ-GLOBAL-003)
O sistema DEVE prover o endpoint `/api/v1/notarize` que recebe um parâmetro `data` ou `hash` (SHA-256) e emite um certificado de existência no tempo assinado com a chave privada `secp256k1` do Oráculo Grid402.

#### Scenario: Notarização de payload ou hash por um agente
- GIVEN uma requisição para `/api/v1/notarize?data=contract-agreement-v1` com pagamento válido
- WHEN o oráculo processa o pedido
- THEN retorna HTTP 200 contendo `certificate_id`, `input_sha256`, `notarized_at_utc`, `unix_epoch` e `_oracle_proof` matematicamente verificável

---

### Requirement: Calendário Financeiro Mundial e Câmbio G10/BCE (REQ-GLOBAL-004)
O sistema DEVE prover o endpoint `/api/v1/global-markets` retornando o status de dia útil bancário e feriados oficiais dos mercados `US` (NYSE/Fed), `EU` (TARGET2/BCE), `UK` (LSE) e `BR` (B3/ANBIMA), além de taxas de referência cambial para mais de 25 moedas globais via dados abertos do Banco Central Europeu.

#### Scenario: Consulta de dia útil na NYSE/Fed e taxas globais
- GIVEN uma requisição para `/api/v1/global-markets?market=US&date=2026-07-03` com pagamento válido
- WHEN o serviço avalia o calendário oficial dos EUA (Independence Day observado) e cotações BCE
- THEN retorna HTTP 200 com `market: "US"`, `is_business_day: false`, `holiday_name`, `next_business_day` e o mapa `fx_rates`

