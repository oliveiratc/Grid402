# telemetry-collector Specification

## Purpose
Prover coleta concorrente de telemetria e disponibilidade de serviços críticos brasileiros com tempo de execução estritamente controlado (< 4s) e persistência em cache JSON seguro.

## Requirements

### Requirement: Parallel telemetry collection
O coletor de telemetria SHALL sondar múltiplos endpoints externos em paralelo através de cURL Multi com timeout individual de no máximo 3.0 segundos por alvo.

#### Scenario: Successful batch probe execution
- **WHEN** o coletor é acionado com um lote de 15 a 20 alvos configurados
- **THEN** todas as requisições disparam em paralelo e o tempo de execução total não ultrapassa 4.0 segundos

#### Scenario: Unresponsive target handling
- **WHEN** um dos alvos externos não responde dentro do tempo limite
- **THEN** o coletor captura o erro de timeout individualmente, marca o alvo como DOWN e conclui a coleta dos demais alvos sem travar o lote

### Requirement: Dual-tier JSON persistence
O coletor de telemetria SHALL consolidar e gravar atomicamente os dados coletados em dois arquivos JSON separados dentro do diretório de armazenamento privado.

#### Scenario: Free summary generation
- **WHEN** a rodada de coleta é concluída com sucesso
- **THEN** o arquivo `summary_free.json` é gravado atomicamente contendo o status geral por serviço, latência média por categoria e timestamp da medição

#### Scenario: Paid telemetry generation
- **WHEN** a rodada de coleta é concluída com sucesso
- **THEN** o arquivo `telemetry_paid.json` é gravado atomicamente contendo a telemetria detalhada de cada alvo com latência exata em milissegundos, código HTTP retornado e score de estabilidade

### Requirement: Execution authorization
O coletor de telemetria SHALL restringir sua execução para permitir apenas chamadas via CLI local ou requisições HTTP autenticadas por chave secreta.

#### Scenario: CLI execution
- **WHEN** o coletor é executado na linha de comando local via `php collector.php`
- **THEN** a execução é autorizada e a coleta é iniciada

#### Scenario: HTTP execution with valid token
- **WHEN** uma requisição HTTP GET ou POST é recebida contendo o parâmetro `?token=CRON_SECRET` ou cabeçalho `Authorization: Bearer CRON_SECRET`
- **THEN** a execução é autorizada e a resposta HTTP 200 é retornada com o resumo da execução

#### Scenario: HTTP execution without valid token
- **WHEN** uma requisição HTTP é recebida sem o token secreto ou com token incorreto
- **THEN** o coletor recusa a execução retornando HTTP 403 Forbidden

### Requirement: Persistência de Série Histórica de Uptime (30 Dias) e Verificação de Integridade Zero-DB
O coletor de telemetria e o motor de status SHALL registrar diariamente as métricas de disponibilidade e latência média no arquivo atômico `uptime_history.json` (mantendo janela de até 90 dias) e validar a codificação UTF-8 e estrutura JSON de todos os arquivos de estado no diretório privado.

#### Scenario: Atualização do histórico de 30 dias após coleta
- **GIVEN** a execução do coletor ou do endpoint de status operacional
- **WHEN** os 22 serviços são verificados
- **THEN** o sistema SHALL atualizar atomicamente `uptime_history.json` com o registro do dia corrente (`date`, `uptime_pct`, `avg_latency_ms`, `services_up: 22`) mantendo codificação UTF-8 íntegra.

### Requirement: Avaliação Proativa de Alertas Críticos (Severidade Alta) após Coleta Agendada
Ao concluir a gravação atômica de `summary_free.json` e `uptime_history.json`, o coletor `public_html/collector.php` SHALL avaliar alertas de severidade `high` via `admin_insights.php` e notificar o Telegram respeitando o cooldown Zero-DB de 60 minutos.

#### Scenario: Coletor dispara alerta crítico no Telegram quando há severidade Alta fora do cooldown
- **GIVEN** que existem condições de severidade `high` (erros `5xx`, coletor atrasado ou rajada `429` recente) e o cooldown em `admin_alerts_state.json` não está ativo
- **WHEN** o coletor `collector.php` conclui uma rodada de coleta
- **THEN** o sistema SHALL avaliar os alertas `high` e despachar a notificação Telegram atualizando `admin_alerts_state.json` com trava exclusiva (`flock`)
- **AND** caso o Telegram esteja desativado ou indisponível, a coleta SHALL finalizar com sucesso sem exceder o tempo limite.
