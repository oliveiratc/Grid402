## ADDED Requirements

### Requirement: Economic indicators API
O sistema SHALL fornecer taxas macroeconomicas oficiais do Brasil (Selic meta, Selic efetiva diaria, taxa CDI e IPCA acumulado) no endpoint `/api/v1/indicators`, emitindo amostra gratuita com Selic meta em caso de requisicao sem pagamento.

#### Scenario: Successful indicators query with payment proof
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo`
- **WHEN** uma requisicao e enviada para `/api/v1/indicators`
- **THEN** o sistema retorna status HTTP 200 OK com os valores de Selic meta, Selic diaria, CDI anual e IPCA acompanhados da data de referencia

#### Scenario: Indicators query without payment proof returns teaser
- **GIVEN** uma requisicao para `/api/v1/indicators` sem cabecalho `X-Payment`
- **WHEN** a requisicao e processada
- **THEN** o sistema retorna status HTTP 402 Payment Required com teaser contendo a Taxa Selic meta e indicacao de campos bloqueados

### Requirement: National and banking holidays API
O sistema SHALL fornecer o calendario oficial de feriados nacionais e bancarios no endpoint `/api/v1/holidays`, com suporte a parametro opcional `year`, emitindo amostra gratuita do proximo feriado em caso de requisicao sem pagamento.

#### Scenario: Successful holidays query for specific year
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo` e parametro `year=2026`
- **WHEN** uma requisicao e enviada para `/api/v1/holidays?year=2026`
- **THEN** o sistema retorna status HTTP 200 OK com a lista completa de feriados nacionais, bancarios e moveis daquele ano

#### Scenario: Holidays query without payment proof returns teaser
- **GIVEN** uma requisicao para `/api/v1/holidays` sem cabecalho `X-Payment`
- **WHEN** a requisicao e processada
- **THEN** o sistema retorna status HTTP 402 Payment Required com teaser contendo o proximo feriado a partir da data corrente

#### Scenario: Invalid year parameter
- **GIVEN** um parametro `year` contendo texto nao numerico ou ano fora do intervalo 1900-2100
- **WHEN** uma requisicao e enviada para `/api/v1/holidays`
- **THEN** o sistema retorna status HTTP 400 Bad Request com codigo `InvalidYearParameter`

