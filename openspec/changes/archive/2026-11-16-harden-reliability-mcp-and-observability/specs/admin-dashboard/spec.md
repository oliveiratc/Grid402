## ADDED Requirements

### Requirement: Exposicao de Latencia P95 Agregada e Politica de Chaves M2M
O sistema SHALL manter amostra circular de latências (`latency_samples_ms`, até 200 registros) na telemetria atômica e exibir a latência **p95 agregada** em ms nos painéis `/admin` e `/trust`, além de documentar no `/trust` e nos guias técnicos a arquitetura não-custodial de chaves M2M e recomendações de isolamento de carteiras.

#### Scenario: Consulta e exibicao da latencia p95 agregada no /admin e /trust
- **GIVEN** requisições registradas com medição de tempo em `metrics.php`
- **WHEN** o operador consulta `GET /admin?api=stats` (autenticado) ou um avaliador acessa `/trust`
- **THEN** o payload JSON de `/admin?api=stats` SHALL incluir `latency_p95_ms` e `latency_p50_ms` numéricos
- **AND** as páginas `/admin` e `/trust` SHALL exibir o indicador agregado de latência `p95`.
