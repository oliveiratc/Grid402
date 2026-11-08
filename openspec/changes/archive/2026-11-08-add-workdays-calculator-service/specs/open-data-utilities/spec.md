## ADDED Requirements

### Requirement: ANBIMA financial workdays calculator API
O sistema SHALL fornecer calculo de dias uteis bancarios conforme o calendario ANBIMA no endpoint `/api/v1/workdays`, suportando contagem de intervalo entre duas datas, projecao de vencimento por adicao ou subtracao de dias e checagem pontual de dia util, emitindo amostra gratuita no desafio 402.

#### Scenario: Contagem de dias uteis entre duas datas com pagamento
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo` e parametros `start_date=2026-12-23` e `end_date=2026-12-28`
- **WHEN** uma requisicao e enviada para `/api/v1/workdays`
- **THEN** o sistema retorna status HTTP 200 OK com `workdays` calculado pelo padrao ANBIMA (inicio exclusivo e fim inclusivo), `calendar_days` corridos e a relacao de feriados bancarios no intervalo

#### Scenario: Projecao de vencimento adicionando dias uteis
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo` e parametros `start_date=2026-11-06` e `days=5`
- **WHEN** uma requisicao e enviada para `/api/v1/workdays`
- **THEN** o sistema retorna status HTTP 200 OK com `target_date` exata deslocada sobre fins de semana e feriados bancarios

#### Scenario: Checagem pontual de dia util
- **GIVEN** um parametro `date=2026-12-25`
- **WHEN** uma requisicao e enviada para `/api/v1/workdays` com comprovante valido
- **THEN** o sistema retorna status HTTP 200 OK informando `is_workday: false`, `day_type: "holiday"` e `holiday_name: "Natal"` acompanhado da data do proximo dia util subsequente

#### Scenario: Requisicao sem comprovante de pagamento retorna teaser
- **GIVEN** uma requisicao para `/api/v1/workdays` com parametro `date` ou `start_date` sem cabecalho `X-Payment`
- **WHEN** a requisicao e processada
- **THEN** o sistema retorna status HTTP 402 Payment Required com teaser informando se a data base e dia util, o proximo feriado bancario e os campos de contagem bloqueados

#### Scenario: Parametros de data invalidos ou fora do limite de seguranca
- **GIVEN** uma data malformatada fora do padrao ISO 8601 ou projecao superior a 5.000 dias uteis
- **WHEN** uma requisicao e enviada para `/api/v1/workdays`
- **THEN** o sistema retorna status HTTP 400 Bad Request com codigo de erro estruturado

