## ADDED Requirements

### Requirement: Avaliação Proativa de Alertas Críticos (Severidade Alta) após Coleta Agendada
Ao concluir a gravação atômica de `summary_free.json` e `uptime_history.json`, o coletor `public_html/collector.php` SHALL avaliar alertas de severidade `high` via `admin_insights.php` e notificar o Telegram respeitando o cooldown Zero-DB de 60 minutos.

#### Scenario: Coletor dispara alerta crítico no Telegram quando há severidade Alta fora do cooldown
- **GIVEN** que existem condições de severidade `high` (erros `5xx`, coletor atrasado ou rajada `429` recente) e o cooldown em `admin_alerts_state.json` não está ativo
- **WHEN** o coletor `collector.php` conclui uma rodada de coleta
- **THEN** o sistema SHALL avaliar os alertas `high` e despachar a notificação Telegram atualizando `admin_alerts_state.json` com trava exclusiva (`flock`)
- **AND** caso o Telegram esteja desativado ou indisponível, a coleta SHALL finalizar com sucesso sem exceder o tempo limite.
