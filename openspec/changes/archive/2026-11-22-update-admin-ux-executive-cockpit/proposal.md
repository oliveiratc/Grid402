# Redesign UX e Cockpit Executivo do Portal Administrativo (`/admin`)

## Por quê
O portal `/admin` acumula cerca de 18 blocos empilhados em uma única página vertical (~4.060 linhas), duplicando métricas de Growth e dificultando a resposta imediata às duas perguntas centrais da operação diária: *"como foi a audiência ontem vs. hoje?"* e *"há algum ponto de atenção agora?"*. Além disso, incidentes críticos (erros `5xx`, coletor atrasado ou rajadas `429`) não possuem motor unificado de alertas visuais nem disparo controlado com cooldown para o Telegram.

Esta change implementa o PRD aprovado em [`openspec/prds/admin-ux-executive-cockpit.md`](../../prds/admin-ux-executive-cockpit.md).

## O que muda
- **Módulo dedicado de inteligência administrativa (`public_html/services/admin_insights.php`):** centraliza o comparativo **Hoje vs. Ontem** (requests, page views, consultas pagas, receita USDC/BRL, novas wallets e deltas %), a agregação de **Saúde Operacional** (`X/25 UP`, uptime 30d, p50/p95, Coletor, PayAI, RapidAPI, Telegram e RPC Base), o **Motor de Pontos de Atenção** (7 regras em severidades Alta, Média e Baixa) e o **despacho de alertas de severidade Alta no Telegram com cooldown Zero-DB de 60 minutos** (`admin_alerts_state.json` sob `flock`).
- **Acionamento proativo no `collector.php`:** avalia alertas de severidade Alta ao final de cada coleta agendada, garantindo notificação no Telegram mesmo quando ninguém está com o `/admin` aberto.
- **Reorganização UX completa do `/admin`:**
  - **Cabeçalho executivo persistente** com status global (`X/25 UP` · uptime 30d), resumo `Hoje vs. Ontem`, contadores de alertas por severidade e barra compacta de ações (`Exportar CSV`, `Configurações` e `Sair`).
  - **5 abas temáticas acessíveis** sem recarregar a página (`Resumo Executivo`, `Audiência e Funil`, `Receita e Passes`, `Saúde e Alertas`, `Eventos ao Vivo`), com suporte a teclado (`tablist`/`tab`/`tabpanel`) e sincronização por hash (incluindo mapeamento retrocompatível de `#overview`, `#telemetry` e `#passes`).
  - **Gráfico interativo 14 dias via Chart.js v4** (CDN jsDelivr + fallback gracioso offline) com alternância entre `Requisições`, `Page Views` e `Consultas Pagas`, acompanhado de tabela diária dos últimos 14 dias.
  - **Consolidação sem duplicação** dos KPIs de Growth, ranking de **Serviços Pagos Reais (`paid_requests_per_service`) vs. Volume Total** (com selo de baixa tração `< 30` reqs), saldos on-chain, Session Passes, saúde técnica, falhas e eventos ao vivo.
  - **Drawer/Painel de Configurações** centralizando Telegram, RapidAPI, 2FA e Alteração de Senha, preservando integralmente os modais, tokens CSRF e contratos da API `/admin?api=stats` e `/admin?api=growth`.
- **Alinhamento de `/growth_admin.php`:** destaque explícito como central de configuração, reconciliação on-chain e aprovação de rewards/builders, com atalho direto para a aba `Audiência e Funil` do `/admin`.

## Impacto
- **Specs afetadas:** `admin-dashboard`, `telemetry-collector`
- **Código afetado:**
  - `public_html/services/admin_insights.php` *(novo)*
  - `public_html/admin.php`
  - `public_html/collector.php`
  - `public_html/growth_admin.php`
  - `public_html/config.php` *(constante opcional `GRID402_FILE_ADMIN_ALERTS_STATE`)*
  - `tests/test_admin_dashboard.php` *(expansão de testes automatizados)*
- **Fora de escopo:** banco de dados SQL, alteração de tarifas x402, páginas públicas (`/`, `/catalog`, `/trust`, `/pass`) e alertas Telegram para severidades Média/Baixa.
