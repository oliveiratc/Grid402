# Proposta: Adicionar Indicadores Avançados e KPIs de Faturamento ao Portal Admin

## Por quê
O painel administrativo atual da Grid402 monitora faturamento consolidado e tráfego básico, mas carece de indicadores analíticos para guiar decisões de monetização e acompanhar o comportamento de agentes autônomos. Conforme definido no PRD (`openspec/prds/admin-portal-advanced-metrics-and-kpis.md`), é fundamental visualizar o Ticket Médio (ARPU), o SLA técnico (HTTP 200 %), a proporção de testes em Sandbox vs Produção, Passes de Sessão ativos, uma curva temporal diária em SVG nativo dos últimos 14 dias, um ranking dos maiores agentes/IPs consumidores e a rentabilidade por micro-serviço.

## O que muda
- **Novos KPIs Executivos nos Cards de Topo:**
  - Ticket Médio por Consulta Paga (ARPU em USDC).
  - Taxa de Sucesso Técnico (SLA HTTP 200 %).
  - Taxa de Adoção Sandbox (% Sandbox / Total).
  - Monitoramento de Passes Web3 emitidos/ativos.
- **Gráfico Temporal Diário SVG Nativo (14 dias):**
  - Renderização direta em SVG responsivo e interativo (sem scripts externos).
  - Tooltip informativo dinâmico (volume diário, consultas pagas e faturamento USDC).
  - Janela móvel de 14 dias com expurgo automático de datas mais antigas em `metrics.json`.
- **Painel Analítico "Top Agentes/IPs Consumidores":**
  - Agrupamento dos IPs mascarados mais ativos no buffer circular de eventos.
  - Exibição de total de requisições, status prevalente e tag de tipo de tráfego.
- **Painel "Rentabilidade Estimada por Micro-Serviço":**
  - Tabela/gráfico ordenado pelo valor em USDC estimado de cada um dos 19 nós operantes.
- **Atualização Dinâmica e Exportação:**
  - Endpoint `/admin?api=stats` estendido com os novos indicadores calculados para polling a cada 5s.
  - Exportação CSV enriquecida com as novas agregações.

## Impacto
- **Specs afetadas:** `admin-dashboard`
- **Código afetado:**
  - `public_html/metrics.php`: manutenção e rotação da janela móvel de 14 dias diários.
  - `public_html/admin.php`: novos KPIs, SVG dinâmico, painéis analíticos, payload `/admin?api=stats` e polling front-end.
  - `tests/test_admin_dashboard.php`: novos testes automatizados para os KPIs e payload estendido.
