# Tasks: Redesign UX e Cockpit Executivo do Portal `/admin`

## 1. Fundação e Motor de Insights (`admin_insights.php` + `config.php`)
- [x] 1.1 Definir `GRID402_FILE_ADMIN_ALERTS_STATE` em `public_html/config.php` apontando para `GRID402_PRIVATE_DIR . '/admin_alerts_state.json'`.
- [x] 1.2 Criar `public_html/services/admin_insights.php` implementando:
  - `grid402_admin_today_vs_yesterday(array $metrics, array $growthMetrics, float $usdRate): array` (com `requests`, `page_views`, `paid`, `revenue_usdc`, `revenue_brl`, `new_wallets`, deltas e proteção para divisão por zero).
  - `grid402_admin_operational_health(array $metrics, array $analytics, array $runtimeStatus, array $walletBalances): array` (lendo `summary_free.json` para `services_up`/`services_total` e `uptime_history.json` para `uptime_30d_pct`, além de `burst_429_recent` e status das integrações).
  - `grid402_admin_evaluate_alerts(array $metrics, array $analytics, array $operationalHealth, array $walletBalances): array` (as 7 regras em severidades `high`, `medium` e `low` + `alerts_summary`).
  - `grid402_dispatch_high_severity_alerts(array $alerts, int $cooldownSec = 3600, ?callable $sender = null): array` (Zero-DB com `flock(LOCK_EX)` em `admin_alerts_state.json`, enviando apenas `high` e liberando lock antes de I/O HTTP).

## 2. Integração Backend (`/admin?api=stats` e `collector.php`)
- [x] 2.1 Integrar `public_html/services/admin_insights.php` em `public_html/admin.php`, expondo `today_vs_yesterday`, `operational_health`, `alerts` e `alerts_summary` tanto no SSR inicial quanto em `GET /admin?api=stats` (sem remover nem alterar nenhum campo legado).
- [x] 2.2 Acionar `grid402_dispatch_high_severity_alerts()` de forma não bloqueante em `/admin?api=stats` e ao final da coleta em `public_html/collector.php`.

## 3. Redesign UX do Portal `/admin` e Alinhamento do `/growth_admin`
- [x] 3.1 Reestruturar o layout autenticado de `public_html/admin.php` com:
  - Faixa Executiva Persistente no topo (status `X/25 UP` + uptime 30d, resumo Hoje vs. Ontem, contadores clicáveis de alertas por severidade e barra compacta `Exportar CSV` / `Configurações`).
  - Barra de 5 abas acessíveis (`Resumo Executivo`, `Audiência e Funil`, `Receita e Passes`, `Saúde e Alertas`, `Eventos ao Vivo`) com `role="tablist"`, navegação por teclado, sincronização por hash (`#tab-*` e aliases retrocompatíveis `#overview`, `#passes`, `#telemetry`) e preservação de aba ativa durante polling.
  - Drawer/Painel de Configurações agrupando os acionadores dos modais existentes (`Telegram`, `RapidAPI`, `2FA`, `Alterar Senha`) sem alterar tokens CSRF nem IDs críticos.
- [x] 3.2 Implementar os conteúdos das 5 abas e o front-end reativo:
  - **Aba Resumo Executivo:** Quadro Hoje vs. Ontem com badges de delta %, Caixa de Pontos de Atenção ordenada por severidade, Gráfico Chart.js v4 (jsDelivr + fallback gracioso) com botões `Requisições` / `Page Views` / `Pagas` e Tabela Diária dos últimos 14 dias.
  - **Aba Audiência e Funil:** KPIs consolidados do Growth Engine (sem duplicação), tabela **Serviços Pagos Reais (`paid_requests_per_service`) vs. Volume Total** (25 serviços, destacando `< 30` reqs) e Top Consumidores (LGPD).
  - **Aba Receita e Passes:** Faturamento Total/Hoje, ARPU, Conversão 402→Pago, Origem dos Pagamentos, Saldos On-Chain da Carteira Oráculo e Session Passes (com % de utilização).
  - **Aba Saúde e Alertas:** Matriz de Saúde Operacional consolidada, Distribuição HTTP, Falhas por Categoria (incluindo `sandbox_rate_limited`) e Falhas Recentes de Pagamento.
  - **Aba Eventos ao Vivo:** Feed de 100 eventos com filtros reativos, busca e horário BRT/UTC.
- [x] 3.3 Atualizar `public_html/growth_admin.php` destacando o foco em configuração, reconciliação on-chain e aprovação de rewards/builders, com link direto para `/admin#tab-audiencia`.

## 4. Testes, Validação OpenSpec e Empacotamento
- [x] 4.1 Expandir `tests/test_admin_dashboard.php` com cenários automatizados cobrindo:
  - `today_vs_yesterday` (com base > 0 e base 0 retornando `delta_pct = null`).
  - Todas as 7 regras do motor de Pontos de Atenção (`high`, `medium`, `low`).
  - Cooldown Zero-DB de 60 minutos no Telegram (1 envio na janela, supressão de duplicados, reenvio após expiração e não envio de `medium`/`low`).
  - Presença de `today_vs_yesterday`, `operational_health`, `alerts` e `alerts_summary` em `GET /admin?api=stats` e marcação das 5 abas + Chart.js + fallback no HTML de `/admin`.
- [x] 4.2 Executar sintaxe PHP (`php -l`), toda a suíte de homologação (`test_admin_dashboard.php`, `test_maturity_and_m2m_controls.php`, `test_concurrency_and_load.php`, `test_session_pass_statement.php`, `test_agentkit_integration.php`, `verify_storage_integrity.php`) e `npx -y @fission-ai/openspec@latest validate --all --strict`.
- [x] 4.3 Regerar pacote de deploy `dist/grid402_hostinger_deploy.zip` via `scripts/package_deploy.ps1` (excluindo `admin_alerts_state.json` e demais arquivos voláteis).
