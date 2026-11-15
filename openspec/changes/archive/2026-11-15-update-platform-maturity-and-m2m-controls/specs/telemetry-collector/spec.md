## ADDED Requirements

### Requirement: Persistência de Série Histórica de Uptime (30 Dias) e Verificação de Integridade Zero-DB
O coletor de telemetria e o motor de status SHALL registrar diariamente as métricas de disponibilidade e latência média no arquivo atômico `uptime_history.json` (mantendo janela de até 90 dias) e validar a codificação UTF-8 e estrutura JSON de todos os arquivos de estado no diretório privado.

#### Scenario: Atualização do histórico de 30 dias após coleta
- **GIVEN** a execução do coletor ou do endpoint de status operacional
- **WHEN** os 22 serviços são verificados
- **THEN** o sistema SHALL atualizar atomicamente `uptime_history.json` com o registro do dia corrente (`date`, `uptime_pct`, `avg_latency_ms`, `services_up: 22`) mantendo codificação UTF-8 íntegra.
