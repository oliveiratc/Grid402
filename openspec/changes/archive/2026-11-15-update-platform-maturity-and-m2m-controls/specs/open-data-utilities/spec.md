## ADDED Requirements

### Requirement: Metadados de Proveniência e Frescor nas Respostas (`_meta`)
O sistema SHALL incluir em todas as respostas HTTP 200 dos serviços de dados um objeto estruturado `_meta` contendo `upstream_source` (fonte oficial de origem), `fetched_at` (timestamp ISO 8601 da coleta), `cache_ttl_sec` (tempo de vida configurado do cache) e `operator`, assinado conjuntamente pelo bloco `_oracle_proof`.

#### Scenario: Resposta paga inclui bloco _meta assinado
- **GIVEN** uma requisição paga ou em modo sandbox para qualquer serviço de dados (ex.: `/api/v1/cnpj` ou `/api/v1/forex`)
- **WHEN** o servidor entrega a resposta com HTTP 200
- **THEN** o JSON retornado SHALL conter a chave `_meta` com `upstream_source`, `fetched_at` e `cache_ttl_sec`, e a verificação em `/api/v1/verify` SHALL validar a integridade do payload completo incluindo `_meta`.

## MODIFIED Requirements

### Requirement: Free tier summary aggregation
O sistema SHALL disponibilizar um resumo público consolidado de status operacional cobrindo todos os **22 serviços** do catálogo em `/status` e `/catalog` sem exigir autenticação ou pagamento, incluindo o histórico de disponibilidade dos últimos 30 dias (`uptime_30d`).

#### Scenario: Querying free utilities summary
- **WHEN** um cliente consulta `/api/v1/summary`, `/status` ou `/catalog` com `Accept: application/json` ou via navegador
- **THEN** o sistema retorna HTTP 200 exibindo o status operacional verificado (`"UP"`) para todos os 22 serviços do catálogo, dados agregados de cotação e o resumo de disponibilidade histórica de 30 dias.
