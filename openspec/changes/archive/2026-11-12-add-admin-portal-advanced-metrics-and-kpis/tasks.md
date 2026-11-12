# Tarefas: Indicadores Avançados e KPIs de Faturamento no Portal Admin

## 1. Módulo de Telemetria e Retenção Diária (`public_html/metrics.php`)
- [x] 1.1 Implementar rotação determinística da janela móvel de 14 dias diários na gravação de telemetria (`metrics.json`).
- [x] 1.2 Implementar funções helper de cálculo analítico: `grid402_compute_admin_analytics()` (ARPU, SLA técnico, percentual sandbox, top agentes consumidores e receita estimada por micro-serviço).

## 2. Interface e Visualização do Portal Admin (`public_html/admin.php`)
- [x] 2.1 Adicionar os novos cards de KPIs na grade executiva do topo (Ticket Médio ARPU, SLA Técnico HTTP 200 %, Proporção Sandbox % e Monitor de Passes Web3).
- [x] 2.2 Desenvolver e integrar o componente de gráfico temporal SVG nativo interativo para os últimos 14 dias com tooltip flutuante e responsividade fluida.
- [x] 2.3 Implementar o painel analítico "Top Agentes/IPs Consumidores (LGPD)" exibindo volume e perfil de uso.
- [x] 2.4 Implementar o painel "Rentabilidade por Micro-Serviço" ordenado por valor monetário em USDC.
- [x] 2.5 Atualizar o endpoint `/admin?api=stats` e a função JavaScript `fetchLiveStats()` para atualizar em tempo real (5s) todos os novos indicadores e gráficos sem recarregar a página.

## 3. Testes, Verificação e Deploy
- [x] 3.1 Atualizar suíte de testes em `tests/test_admin_dashboard.php` cobrindo o cálculo dos novos KPIs, rotação de 14 dias e contrato do endpoint JSON.
- [x] 3.2 Executar testes automatizados com PHP CLI e validar a especificação OpenSpec.
- [x] 3.3 Empacotar e implantar em produção no Hostinger (`grid402.online`) e validar o painel ao vivo.
