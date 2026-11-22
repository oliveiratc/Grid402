# Design Técnico: Cockpit Executivo e Redesign UX do `/admin`

## 1. Arquitetura Modular (Zero-DB)
Para evitar aumentar a complexidade monolítica de `public_html/admin.php` (~4.060 linhas) e permitir testes unitários isolados, toda a lógica de agregação executiva, avaliação de alertas e controle de cooldown Telegram será encapsulada no novo módulo `public_html/services/admin_insights.php`.

```text
public_html/
├── config.php                        # Define GRID402_FILE_ADMIN_ALERTS_STATE
├── collector.php                     # Ao concluir coleta -> avalia alertas High (Telegram com cooldown)
├── admin.php                         # Carrega services/admin_insights.php + UI 5 abas + Chart.js v4
├── growth_admin.php                  # Mantém config, reconciliação on-chain e rewards + link p/ /admin#tab-audiencia
└── services/
    └── admin_insights.php            # [NOVO] Comparativo Hoje vs Ontem, Saúde 25/25 + Uptime 30d,
                                      #        Motor de 7 Alertas e Cooldown Zero-DB no Telegram
```

## 2. Contrato de Dados (`admin_insights.php` e `/admin?api=stats`)

Todos os campos existentes de `GET /admin?api=stats` são preservados sem alteração de nome ou tipo. São adicionados 3 blocos estruturados:

### 2.1 `today_vs_yesterday`
```json
{
  "today_date": "2026-11-22",
  "today_label": "22/11",
  "yesterday_date": "2026-11-21",
  "yesterday_label": "21/11",
  "metrics": {
    "requests": { "today": 1059, "yesterday": 2729, "total": 13879, "delta": -1670, "delta_pct": -61.2 },
    "page_views": { "today": 52, "yesterday": 412, "total": 2422, "delta": -360, "delta_pct": -87.4 },
    "paid": { "today": 0, "yesterday": 29, "total": 81, "delta": -29, "delta_pct": -100.0 },
    "revenue_usdc": { "today": 0.0, "yesterday": 0.01, "total": 4.2905, "delta": -0.01, "delta_pct": -100.0 },
    "revenue_brl": { "today": 0.0, "yesterday": 0.06, "total": 25.48, "delta": -0.06, "delta_pct": -100.0 },
    "new_wallets": { "today": 0, "yesterday": 2, "total": 12, "delta": -2, "delta_pct": -100.0 }
  }
}
```
- Quando `yesterday == 0`, `delta_pct` é `null` (renderizado como `—` no front-end, evitando `NaN%` ou `Infinity%`).

### 2.2 `operational_health`
Consolidado a partir de `summary_free.json`, `uptime_history.json`, `metrics.json`, `grid402_admin_runtime_status()` e `wallet_balances`:
```json
{
  "services_up": 25,
  "services_total": 25,
  "uptime_30d_pct": 100.0,
  "sla_target_pct": 99.5,
  "error_5xx_count": 0,
  "technical_success_rate": 100.0,
  "latency_p50_ms": 94.84,
  "latency_p95_ms": 549.31,
  "burst_429_recent": 0,
  "collector_age_sec": 68,
  "collector_stale": false,
  "rpc_operational": true,
  "facilitator_mode": "settle",
  "facilitator_host": "facilitator.payai.network",
  "multichain_accepts": 4,
  "rapidapi_configured": true,
  "rapidapi_count": 3,
  "telegram_configured": true,
  "telegram_last_ok": true
}
```

### 2.3 `alerts` e `alerts_summary`
Avaliadas 7 regras determinísticas na ordem de severidade (`high` → `medium` → `low`):
1. `error_5xx` (`high`): `error_5xx_count > 0`
2. `collector_stale` (`high`): `collector_status.summary_stale === true` (`summary_age_sec === null || summary_age_sec > 600`)
3. `burst_429_sandbox` (`high`): quantidade de eventos com `status === 429` em `recent_events` >= `20`
4. `high_latency_p95` (`medium`): `latency_p95_ms > 1000.0`
5. `idle_session_passes` (`medium`): `active_passes > 0` e (`active_remaining_credits >= total_credits` ou `payment_sources.pass === 0`)
6. `wallet_zero_eth` (`low`): `wallet_balances.rpc_operational === true` e `eth_balance <= 0`
7. `low_traction_services` (`low`): micro-serviços pagos do catálogo (`grid402_get_utility_services()`, excluindo `summary` e `verify`) com `< 30` requisições acumuladas em `service_counts`.

Cada item segue o formato:
```json
{
  "id": "burst_429_sandbox",
  "severity": "high",
  "title": "Rajada de bloqueios HTTP 429 detectada",
  "detail": "68 eventos HTTP 429 nos últimos 100 registros recentes.",
  "action": "Verifique o IP de origem em Eventos ao Vivo e avalie cooldown adicional se persistir."
}
```
E `alerts_summary`: `{ "high": 0, "medium": 1, "low": 2, "total": 3 }`.

## 3. Cooldown Zero-DB de Alertas no Telegram (`admin_alerts_state.json`)
- Arquivo: `GRID402_FILE_ADMIN_ALERTS_STATE` (`GRID402_PRIVATE_DIR . '/admin_alerts_state.json'`).
- Função: `grid402_dispatch_high_severity_alerts(array $alerts, int $cooldownSec = 3600, ?callable $sender = null): array`.
  - Filtra apenas alertas com `severity === 'high'`.
  - Se vazio, retorna imediatamente sem abrir lock nem chamar rede.
  - Adquire `flock($fp, LOCK_EX)` em `admin_alerts_state.json`, lê estado com `grid402_read_locked_json($fp, [])`, identifica os `alert_id` cujo `(time() - last_sent_epoch) >= $cooldownSec`, atualiza `last_sent_epoch` / `last_sent_at` / `send_count` e **libera o lock antes de executar qualquer chamada HTTP externa ao Telegram** (evitando travar I/O de disco durante rede).
  - Usa o canal já existente de envio Telegram (`grid402_send_telegram_alert` ou injeção `$sender` para testes unitários sem rede real).
  - Falha de rede no Telegram é capturada sem interromper `/admin?api=stats` nem `collector.php`.
  - Em caso de falha de envio, reverte opcionalmente o timestamp ou registra `last_error` sem estourar exceção.

## 4. Arquitetura UX e Acessibilidade do `/admin`
- **Cabeçalho Executivo Persistente:** acima das abas, exibindo em uma barra densa e legível:
  - Status global (`25/25 UP · 100% 30d`), resumo `Hoje vs. Ontem`, contadores clicáveis de alertas (`Alta`, `Média`, `Baixa` que levam à seção de alertas) e botões compactos (`Exportar CSV`, `Configurações` e status da última atualização).
- **Sistema de 5 Abas (`role="tablist"`):**
  - `#tab-resumo` — **Resumo Executivo** (padrão)
  - `#tab-audiencia` — **Audiência e Funil** (KPIs Growth consolidados + Serviços Pagos Reais vs. Volume Total + Top Consumidores)
  - `#tab-receita` — **Receita e Passes** (Faturamento, ARPU, Origem dos Pagamentos, Carteira Oráculo, Session Passes)
  - `#tab-saude` — **Saúde e Alertas** (Matriz de Saúde Operacional, Distribuição HTTP, Falhas por Categoria, Falhas Recentes de Pagamento)
  - `#tab-eventos` — **Eventos ao Vivo** (Feed 100 eventos com filtros reativos, busca e horário BRT/UTC)
  - **Mapeamento retrocompatível de hashes:** `#overview` → `tab-resumo`, `#passes` → `tab-receita`, `#telemetry` → `tab-eventos`, garantindo que links existentes em `includes/admin_nav.php` funcionem perfeitamente sem quebrar a spec `responsive-navigation`.
- **Drawer / Painel de Configurações:** acionado pelo botão `Configurações` no cabeçalho executivo, organizando os 4 cartões de gestão (`Alertas Telegram`, `RapidAPI Bridge`, `2FA Telegram`, `Alterar Senha`) que abrem os mesmos modais já homologados (preservando IDs, inputs, CSRF e fluxos HTTP testados em `tests/test_admin_dashboard.php`).
- **Chart.js v4 com Fallback Gracioso:**
  - Script: `https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js`.
  - Controles de série: `Requisições`, `Page Views` e `Consultas Pagas`.
  - Se `typeof window.Chart === 'undefined'`, exibe aviso amigável no lugar do `<canvas>` e mantém a tabela diária de 14 dias 100% visível e atualizada em tempo real.
- **Semântica HTML:** exatamente um `<h1>` na visão autenticada (título do cockpit) e hierarquia `<h2>`/`<h3>` consistente.

## 5. Alternativas Consideradas
- **Manter tudo dentro de `admin.php`:** rejeitado porque o arquivo já passa de 4.000 linhas e dificulta testar o motor de alertas/cooldown isoladamente e reutilizá-lo em `collector.php`.
- **Disparar alertas Telegram apenas no `/admin?api=stats`:** rejeitado porque dependeria do navegador do operador estar aberto. Acionar também no `collector.php` garante cobertura 24/7.
- **Alterar links obrigatórios de `includes/admin_nav.php`:** mantidos compatíveis com a spec `responsive-navigation` (`/admin#overview`, `/admin#telemetry`, `/admin#passes`, `/growth_admin`, `/`, logout), mapeando cada hash para sua respectiva aba do novo `/admin`.
