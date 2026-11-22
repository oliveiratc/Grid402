## ADDED Requirements

### Requirement: Cabeçalho Executivo Persistente e Navegação por 5 Abas no Portal Administrativo
O portal `/admin` autenticado MUST exibir uma faixa executiva fixa no topo (status global, resumo Hoje vs. Ontem, contadores de alertas e ações rápidas) seguida por 5 abas temáticas acessíveis sem recarregar a página, sincronizadas com o hash da URL.

#### Scenario: Alternância entre as 5 abas sem recarregar e compatibilidade de hashes
- **GIVEN** que o operador autenticado acessa `/admin`
- **WHEN** o operador alterna entre as abas `Resumo Executivo`, `Audiência e Funil`, `Receita e Passes`, `Saúde e Alertas` e `Eventos ao Vivo` (ou navega para `#overview`, `#passes`, `#telemetry` ou `#tab-*`)
- **THEN** o sistema SHALL exibir apenas o painel da aba ativa com `role="tablist"`, `role="tab"`, `role="tabpanel"` e `aria-selected="true"` sem recarregar a página
- **AND** a faixa executiva superior SHALL permanecer visível com status `X/25 UP`, uptime 30d, alertas ativos e barra compacta (`Exportar CSV`, `Configurações` e `Sair`)
- **AND** o polling periódico de `/admin?api=stats` SHALL atualizar os dados preservando a aba ativa.

#### Scenario: Centralização de configurações operacionais preservando modais e CSRF
- **GIVEN** que o operador está autenticado no `/admin`
- **WHEN** o operador abre o painel/drawer de `Configurações` pela barra superior
- **THEN** o sistema SHALL disponibilizar os acionadores dos modais de `Alertas Telegram`, `RapidAPI Bridge`, `2FA Telegram` e `Alterar Senha`
- **AND** todas as submissões POST SHALL manter proteção por sessão ativa e validação de token CSRF.

---

### Requirement: Quadro Comparativo Hoje vs. Ontem com Variação Percentual
O backend administrativo (`public_html/services/admin_insights.php` e `/admin?api=stats`) MUST expor `today_vs_yesterday` (fuso `America/Sao_Paulo`) comparando Hoje, Ontem, total acumulado, delta absoluto e delta percentual para Requisições, Page Views, Consultas Pagas, Receita e Novas Wallets.

#### Scenario: Cálculo de Hoje vs. Ontem com base não nula e base zero segura
- **GIVEN** que `metrics.json` registra tráfego para hoje e ontem (incluindo `page_views`) e `growth.json` registra `new_wallets_by_day`
- **WHEN** o operador autenticado consulta `GET /admin?api=stats` ou visualiza a aba `Resumo Executivo`
- **THEN** o payload JSON SHALL conter `today_vs_yesterday` com os 6 indicadores (`requests`, `page_views`, `paid`, `revenue_usdc`, `revenue_brl`, `new_wallets`) contendo `today`, `yesterday`, `total`, `delta` e `delta_pct`
- **AND** quando o valor de ontem for `0`, `delta_pct` SHALL ser `null` (renderizado como `—` na interface, sem `NaN` ou divisão por zero).

---

### Requirement: Motor Automático de Pontos de Atenção (7 Regras) e Alerta Telegram com Cooldown Zero-DB
O sistema MUST avaliar 7 regras determinísticas de Pontos de Atenção (`high`, `medium`, `low`), expor `alerts` e `alerts_summary` em `/admin?api=stats` e disparar notificação no Telegram exclusivamente para severidade `high` com cooldown Zero-DB de 60 minutos em `admin_alerts_state.json`.

#### Scenario: Classificação correta das 7 regras de Pontos de Atenção
- **GIVEN** um snapshot administrativo com `error_5xx_count > 0`, `summary_stale = true`, `>= 20` eventos `429` em `recent_events`, `latency_p95_ms > 1000`, passes ativos com `0%` consumido, `eth_balance = 0` (RPC operacional) e serviços pagos com `< 30` requisições
- **WHEN** o motor de insights avalia o snapshot
- **THEN** o sistema SHALL gerar alertas `high` para erros 5xx, coletor atrasado e rajada 429
- **AND** SHALL gerar alertas `medium` para latência p95 alta e passes ociosos
- **AND** SHALL gerar alertas `low` para carteira com 0 ETH e serviços de baixa tração
- **AND** cada alerta SHALL conter `id`, `severity`, `title`, `detail` e `action`.

#### Scenario: Disparo de Telegram apenas para severidade Alta respeitando cooldown de 60 minutos
- **GIVEN** que o Telegram está configurado e o motor detecta alertas de severidade `high`, `medium` e `low`
- **WHEN** o despachante de alertas é executado duas vezes consecutivas dentro da janela de cooldown (60 minutos)
- **THEN** apenas os alertas de severidade `high` SHALL ser enviados na primeira execução com trava `flock` em `admin_alerts_state.json`
- **AND** na segunda execução dentro do cooldown nenhum alerta duplicado SHALL ser reenviado
- **AND** nenhum alerta de severidade `medium` ou `low` SHALL ser enviado ao Telegram
- **AND** após expiração do cooldown, um alerta `high` ainda ativo SHALL poder ser reenviado.

---

### Requirement: Consolidação de Audiência, Serviços Pagos Reais, Receita e Saúde Operacional
O portal `/admin` MUST consolidar sem duplicação visual os indicadores de Audiência/Growth (incluindo `paid_requests_per_service` real vs. volume total dos 25 serviços), Receita/Passes e o painel de Saúde Operacional (`operational_health`).

#### Scenario: Exposição de saúde operacional consolidada e serviços pagos reais em `/admin?api=stats`
- **GIVEN** que `summary_free.json` possui 25 serviços UP e `uptime_history.json` possui histórico de 30 dias
- **WHEN** o operador autenticado requisita `GET /admin?api=stats` e navega pelas abas `Audiência e Funil`, `Receita e Passes` e `Saúde e Alertas`
- **THEN** o JSON SHALL incluir `operational_health` com `services_up`, `services_total`, `uptime_30d_pct`, `sla_target_pct`, `latency_p50_ms`, `latency_p95_ms`, `error_5xx_count` e estado das integrações (`collector`, `facilitator`, `rapidapi`, `telegram`, `rpc_operational`)
- **AND** a aba `Audiência e Funil` SHALL comparar `paid_requests_per_service` real com o volume total dos 25 serviços destacando serviços com `< 30` requisições
- **AND** todos os campos legados de `/admin?api=stats` SHALL permanecer presentes sem quebra de compatibilidade.

## RENAMED Requirements
- FROM: `### Requirement: Gráfico Temporal Diário SVG Nativo dos Últimos 14 Dias`
- TO: `### Requirement: Gráfico Temporal Interativo dos Últimos 14 Dias (Chart.js v4 com Fallback) e Tabela Diária`

## MODIFIED Requirements

### Requirement: Gráfico Temporal Interativo dos Últimos 14 Dias (Chart.js v4 com Fallback) e Tabela Diária
O sistema MUST renderizar na aba `Resumo Executivo` um gráfico interativo dos últimos 14 dias via Chart.js v4 (CDN jsDelivr com fallback gracioso) com alternância entre `Requisições`, `Page Views` e `Consultas Pagas`, acompanhado de tabela diária dos últimos 14 dias.

#### Scenario: Renderização e rotação da janela móvel de 14 dias
- **GIVEN** que o histórico diário em `metrics.json` contém registros distribuídos ao longo do tempo
- **WHEN** o operador carregar o painel administrativo
- **THEN** o sistema SHALL exibir o gráfico temporal dos últimos 14 dias e a tabela diária correspondente
- **AND** deve expurgar automaticamente dias anteriores ao período móvel de 14 dias para evitar sobrecarga no arquivo JSON
- **AND** cada dia no gráfico e na tabela SHALL exibir data, total de requisições, page views, consultas pagas e faturamento em USDC.

#### Scenario: Alternância de série no gráfico 14 dias e fallback offline
- **GIVEN** que `daily_history_14d` contém 14 dias contínuos com `requests`, `page_views`, `paid` e `usdc`
- **WHEN** o operador alterna os seletores entre `Requisições`, `Page Views` e `Consultas Pagas` (ou quando `window.Chart` não carrega)
- **THEN** o gráfico Chart.js SHALL atualizar a série exibida mantendo tooltip completo
- **AND** caso `window.Chart` não esteja disponível, o painel SHALL exibir aviso discreto mantendo a tabela diária e o polling totalmente operacionais.

### Requirement: Visibilidade de falhas de pagamento
O sistema MUST exibir no painel a contagem de falhas por categoria (incluindo `sandbox_rate_limited`), mantendo bloqueios `429` de sandbox separados de `payment_rejected` e filtrados da tabela de falhas reais `recent_payment_failures`.

#### Scenario: Categorias de falha visíveis
- **WHEN** o administrador abre o painel após falhas de pagamento
- **THEN** cada categoria de falha aparece com sua contagem, e a soma das barras de status corresponde ao total de consultas.

#### Scenario: Filtragem de bloqueios 429 sandbox em falhas recentes
- **WHEN** ocorrem bloqueios `HTTP 429 SandboxRateLimitExceeded` em modo sandbox junto a falhas reais de pagamento
- **THEN** a categoria `sandbox_rate_limited` aparece contabilizada separadamente de `payment_rejected`
- **AND** a tabela `recent_payment_failures` lista apenas falhas reais de pagamento/passe, ignorando `429 SandboxRateLimitExceeded` de sandbox.
