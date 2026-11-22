# PRD: Redesign UX e Cockpit Executivo do Portal Administrativo (`/admin`)

**Status:** Aprovado  
**Data:** 2026-11-22  
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema
O portal administrativo do Grid402 (`/admin`) cresceu organicamente ao longo das entregas da plataforma e hoje concentra cerca de 18 blocos empilhados verticalmente em uma única página longa (~4.060 linhas), misturando métricas financeiras, tráfego, Growth duplicado com `/growth_admin`, gráficos técnicos, logs de falha e feed de eventos sem hierarquia por tarefa.

Como consequência prática na operação diária:
- O mantenedor não consegue responder em segundos às perguntas mais frequentes: *"como foi a audiência ontem vs. hoje?"* e *"há algum ponto de atenção agora?"*.
- Indicadores críticos que já existem nos arquivos Zero-DB (como `page_views` de ontem vs. hoje, novas carteiras por dia, `paid_requests_per_service` real do Growth, saúde consolidada dos 25 serviços e uptime de 30 dias) ficam espalhados, ocultos em JSON ou ausentes da visualização principal.
- Incidentes operacionais graves (erros `5xx`, coletor `summary_free.json` atrasado ou rajadas de `HTTP 429` em sandbox) dependem de inspeção manual no navegador, sem motor unificado de alertas visuais nem disparo controlado para o Telegram.

Resolver isso agora é prioritário porque o tráfego de produção estabilizou em um novo patamar (> 2.400 reqs/dia e > 400 page views/dia), tornando essencial um monitoramento ágil, limpo e acionável.

---

## 2. Objetivos
- **Diagnóstico diário em < 10 segundos:** exibir imediatamente no topo e na aba inicial o comparativo **Hoje vs. Ontem** (requisições, page views, consultas pagas, receita USDC/BRL, novas carteiras e variação percentual) e os **Pontos de Atenção** ordenados por severidade.
- **Reduzir sobrecarga cognitiva no `/admin`:** reorganizar todos os painéis em **5 abas temáticas** sem recarregamento de página, eliminando duplicações visuais de Growth e mantendo ações operacionais organizadas em barra compacta + drawer/aba de Configurações.
- **Detecção proativa de incidentes críticos:** implementar motor determinístico de 7 regras de alerta (severidades Alta, Média e Baixa), com envio automático ao Telegram exclusivo para severidade **Alta** e cooldown anti-spam persistido em Zero-DB (60 min por tipo).
- **Elevar a leitura temporal com Chart.js v4:** substituir o gráfico SVG simples de 14 dias por gráfico interativo via Chart.js v4 (CDN jsDelivr com fallback gracioso) permitindo alternar entre `Requisições`, `Page Views` e `Pagas`, acompanhado de tabela diária dos últimos 14 dias.
- **Zero regressão de segurança e contratos:** manter 100% da autenticação (Bcrypt, 2FA Telegram, CSRF, fingerprint de sessão), compatibilidade retroativa dos payloads `/admin?api=stats` e `/admin?api=growth` e aprovação integral da suíte `tests/test_admin_dashboard.php`.

---

## 3. Não objetivos (fora do escopo)
- Migração de armazenamento Zero-DB para banco de dados SQL.
- Alteração de preços, tarifas x402, regras de cobrança, Session Pass ou endpoints públicos (`/`, `/catalog`, `/trust`, `/pass`, `/api/v1/*`).
- Criação de sistema multiusuário, perfis/permissões (RBAC) ou novos provedores de autenticação no `/admin`.
- Envio de alertas no Telegram para severidades Média ou Baixa (ex.: `0 ETH`, passes ociosos, serviços novos com baixa tração).
- Remoção da página `/growth_admin` — ela permanece dedicada à configuração do Growth Engine, reconciliação on-chain (`eth_getLogs`) e aprovação de rewards/builders.

---

## 4. Público-alvo e personas
- **Mantenedor / Operador do Grid402 (Thiago Campos):** acessa o `/admin` diariamente via desktop ou celular (especialmente no início do dia e após ações de divulgação/deploy) para conferir audiência (ontem vs. hoje), faturamento, conversão de wallets/passes, saúde dos 25 micro-serviços e eventuais anomalias.

---

## 5. Histórias de usuário
- Como operador, quero ver no topo e na primeira aba um comparativo **Hoje vs. Ontem** (requisições, page views, consultas pagas, receita e novas wallets) com variação percentual, para avaliar a evolução da audiência sem precisar somar dados manualmente.
- Como operador, quero visualizar uma caixa de **Pontos de Atenção** priorizada por severidade (Alta, Média, Baixa) com diagnóstico claro e ação recomendada, para agir imediatamente quando houver anomalia.
- Como operador, quero receber notificação automática no **Telegram** apenas para alertas de severidade **Alta** (erros `5xx`, coletor atrasado ou rajada `429`), com cooldown anti-spam de 60 minutos, mesmo quando o painel `/admin` estiver fechado.
- Como operador, quero alternar entre **5 abas temáticas** sem perder o contexto crítico do cabeçalho, para investigar audiência, receita, saúde técnica ou eventos ao vivo sem rolar uma página interminável.
- Como operador, quero comparar **serviços pagos reais (`paid_requests_per_service`)** lado a lado com o volume total dos 25 serviços e identificar serviços recém-lançados com baixa tração (`< 30` reqs), para direcionar esforços de divulgação e SEO.
- Como operador, quero ver no gráfico interativo e na tabela dos últimos **14 dias** tanto requisições quanto `page_views`, consultas pagas e receita USDC, para correlacionar visitas HTML com tráfego de APIs.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Faixa superior persistente (Header Executivo):** exibir em todas as abas o status global dos serviços (`X/25 UP` + uptime 30 dias), minirresumo Hoje vs. Ontem (requests, page views, pagas, receita), contagem de alertas ativos por severidade (`Alta`, `Média`, `Baixa`) e barra compacta de ações operacionais (`Exportar CSV`, `Configurações` e `Sair`). | Must |
| RF02 | **Navegação por 5 Abas sem recarregar:** estruturar o conteúdo do `/admin` em 5 abas (`Resumo Executivo`, `Audiência e Funil`, `Receita e Passes`, `Saúde e Alertas`, `Eventos ao Vivo`), com controle via clique e teclado (`ArrowLeft`/`ArrowRight`, atributos ARIA `role="tablist"`, `role="tab"`, `role="tabpanel"`), sincronizadas via hash de URL (`#tab-resumo`, `#tab-audiencia`, `#tab-receita`, `#tab-saude`, `#tab-eventos`) e preservação da aba ativa entre polls de 5s. | Must |
| RF03 | **Quadro comparativo Hoje vs. Ontem (Aba Resumo Executivo):** exibir tabela/cards lado a lado comparando **Hoje (parcial, fuso BRT)** e **Ontem (BRT)** para: Requisições totais, Page Views (HTML), Consultas Pagas, Receita (USDC e BRL) e Novas Wallets conectadas, exibindo delta absoluto e percentual (`+X%` / `-X%` / `—` quando base for 0) e o total acumulado. | Must |
| RF04 | **Motor de Pontos de Atenção Automáticos (7 regras):** calcular no backend (exposto em `/admin?api=stats` no campo `alerts`) e renderizar no painel alertas classificados por severidade (`high`, `medium`, `low`), cada um contendo `id`, `severity`, `title`, `detail` e `action`: <br>• **Alta (`high`):** (1) `error_5xx_count > 0`; (2) `collector_status.summary_stale === true` (`summary_age_sec > 600` ou inexistente); (3) rajada `429` (`>= 20` eventos `status === 429` no buffer `recent_events`). <br>• **Média (`medium`):** (4) `latency_p95_ms > 1000`; (5) Session Passes ociosos (`active_passes > 0` e `remaining_credits >= total_credits` ou `payment_sources.pass === 0` com créditos ativos intactos). <br>• **Baixa (`low`):** (6) carteira oráculo com `eth_balance <= 0` e `rpc_operational === true`; (7) serviços pagos do catálogo com baixa tração (`< 30` requisições acumuladas). | Must |
| RF05 | **Notificação Telegram para severidade Alta com cooldown Zero-DB:** sempre que houver alerta `severity === 'high'`, despachar alerta estruturado no Telegram via função dedicada protegida por `flock` em `GRID402_PRIVATE_DIR . '/admin_alerts_state.json'`, respeitando cooldown configurável (padrão **3.600s / 60 min** por `alert_id`). O avaliador deve ser acionado tanto no cron `collector.php` (para funcionar sem o `/admin` aberto) quanto em `/admin?api=stats` (sem bloquear ou falhar a resposta JSON em caso de erro do Telegram). | Must |
| RF06 | **Gráfico interativo 14 dias (Chart.js v4) + Tabela Diária 14d (Aba Resumo Executivo):** carregar Chart.js v4 via CDN jsDelivr com botões para alternar a série principal entre `Requisições`, `Page Views` e `Consultas Pagas` (com tooltip completo mostrando data, requests, page views, pagas e USDC), acompanhado de uma tabela compacta dos últimos 14 dias (mais recente primeiro). Se `window.Chart` não estiver disponível, degradar graciosamente exibindo aviso discreto e mantendo a tabela 14d intacta. | Must |
| RF07 | **Consolidação de Audiência e Funil (Aba Audiência e Funil):** consolidar sem duplicação os KPIs do Growth Engine (Wallets conectadas, Pagantes, Conversão 1ª compra, Conversão 2ª compra, Retenção, Receita/Wallet, Builders, Referrals, Créditos promo), exibir tabela comparativa **Serviços Pagos Reais (`paid_requests_per_service`) vs. Volume Total** para os 25 micro-serviços com selo de destaque para serviços com `< 30` reqs, e exibir **Top Consumidores (LGPD)**. | Must |
| RF08 | **Consolidação Financeira e Passes (Aba Receita e Passes):** agrupar Faturamento Total/Hoje (USDC + BRL), ARPU, Taxa de Conversão 402→Pago, Origem dos Pagamentos (`facilitator`, `rapidapi`, `pass`, `tx_hash`, `mercadopago`, `sandbox`), saldos on-chain da Carteira Oráculo (`USDC`, `ETH`, status RPC Base Mainnet) e métricas de Session Passes (emitidos, ativos, créditos totais vs. restantes, taxa de utilização %). | Must |
| RF09 | **Painel de Saúde Operacional Consolidado (Aba Saúde e Alertas):** exibir em formato de matriz/cards o estado em tempo real de: Serviços UP (`X/25` a partir de `summary_free.json`), Uptime 30d (`uptime_history.json` vs. meta 99,5%), Taxa Técnica sem 5xx, Latência `p50` e `p95`, Coletor (idade em segundos), Facilitador PayAI (`mode`, `host`, redes multichain), RapidAPI Bridge (status e quantidade de segredos ativos), Telegram (`last_status` HTTP e timestamp BRT/UTC) e RPC Base Mainnet; além de Distribuição de Status HTTP, Falhas por Categoria (incluindo `sandbox_rate_limited`) e tabela limpa de Falhas Recentes de Pagamento. | Must |
| RF10 | **Eventos ao Vivo e Configurações Organizadas (Aba Eventos ao Vivo + Drawer/Aba de Configurações):** manter o feed em tempo real dos últimos 100 eventos com filtros por tipo/serviço, busca textual e horários duplos (BRT + UTC). Reorganizar os acionadores de configuração (`Alertas Telegram`, `RapidAPI Bridge`, `2FA Telegram`, `Alterar Senha`) dentro de um painel/drawer de Configurações acessível pela barra superior, preservando integralmente os modais, tokens CSRF e endpoints POST já existentes. | Must |
| RF11 | **Atualização da navegação `/includes/admin_nav.php` e `/growth_admin.php`:** alinhar os links do menu superior do admin para apontar corretamente para as novas abas do `/admin` e destacar que `/growth_admin` é a central de configuração/reconciliação/aprovação de rewards (com link de volta para a aba `Audiência e Funil` do `/admin`). | Should |
| RF12 | **Retrocompatibilidade do contrato JSON e IDs DOM críticos:** manter todos os campos já existentes em `/admin?api=stats` e `/admin?api=growth` (adicionando novos campos como `alerts`, `today_vs_yesterday`, `operational_health` sem remover nem renomear os atuais) e manter os seletores/tokens CSRF validados pela suíte `tests/test_admin_dashboard.php`. | Must |

---

## 7. Requisitos não funcionais
- **Performance:** o endpoint `/admin?api=stats` deve continuar respondendo em `< 150 ms` em condição normal (lendo arquivos locais em `GRID402_PRIVATE_DIR` e cache de 60s da carteira). O despacho de alerta Telegram deve respeitar timeout curto (`<= 2s`) e nunca lançar exceção não tratada.
- **Segurança:** nenhuma credencial, token de bot, segredo RapidAPI ou token `pass_g402_...` pode ser exposto em respostas JSON, alertas Telegram ou HTML. Toda mutação (Telegram, RapidAPI, 2FA, Senha) permanece protegida por sessão autenticada + fingerprint + token CSRF (`hash_equals`).
- **Acessibilidade e Semântica:** um único `<h1>` na página, contraste mínimo WCAG AA sobre o tema dark (`#060B19` / `#0B132B`), abas navegáveis por teclado (`tablist`/`tab`/`tabpanel`), rótulos explícitos em todos os controles e filtros, e layout responsivo funcional em larguras `>= 360px` (mobile) e desktop (`>= 1280px`).
- **Zero-DB e Concorrência:** o arquivo de estado de cooldown de alertas (`admin_alerts_state.json`) deve usar `fopen(..., 'c+')` + `flock(LOCK_EX)` + `grid402_read_locked_json()` + `ftruncate`/`rewind`/`fwrite`/`fflush`, nunca segurando dois locks de arquivo simultaneamente.
- **Resiliência externa:** Chart.js carregado de CDN pública (`https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js`) com fallback seguro no JS (`if (typeof Chart === 'undefined')`) para garantir operação plena mesmo offline ou com CDN bloqueada.

---

## 8. Fluxos e UX

### Fluxo principal (check-in diário do operador)
1. Operador acessa `/admin` (já autenticado ou após login + 2FA Telegram).
2. No topo fixo, vê imediatamente: `25/25 UP · 100% 30d`, resumo `Hoje vs. Ontem`, badges de alertas ativos (`0 Alta · 1 Média · 2 Baixa`) e botão `Configurações` / `CSV`.
3. Na aba padrão **Resumo Executivo**:
   - **Bloco 1:** Comparativo **Hoje vs. Ontem** (5 indicadores com delta %).
   - **Bloco 2:** **Pontos de Atenção** (cards ordenados por severidade: vermelho=Alta, âmbar=Média, ciano/azul=Baixa; se não houver nenhum alerta, exibe card verde *"Nenhum ponto de atenção ativo"*).
   - **Bloco 3:** Gráfico **Chart.js 14 dias** com botões `Requisições` | `Page Views` | `Pagas` + tabela diária dos 14 dias.
4. Clicando nas demais abas (`Audiência e Funil`, `Receita e Passes`, `Saúde e Alertas`, `Eventos ao Vivo`), o conteúdo troca instantaneamente sem recarregar a página, atualizando o hash da URL e sendo mantido atualizado pelo polling em tempo real (5s para `/admin?api=stats`, 15s para `/admin?api=growth`).

### Fluxos alternativos e estados
- **Alerta crítico detectado (`severity: high`):** aparece no topo da caixa de Pontos de Atenção, destaca o badge vermelho no cabeçalho fixo e, se o cooldown de 60 min para aquele `alert_id` estiver vencido e o Telegram estiver configurado, dispara mensagem formatada no Telegram e registra `last_sent_at` em `admin_alerts_state.json`.
- **Estado vazio (ex.: dia recém-iniciado às 00:05 BRT ou instalação nova):** deltas contra base zero exibem `—` (nunca `NaN%` ou `Infinity%`); listas vazias (ex.: `recent_payment_failures` vazio) exibem estado positivo claro (*"Nenhuma falha real de pagamento registrada"*).
- **CDN Chart.js indisponível:** o container do gráfico exibe aviso discreto (*"Gráfico interativo indisponível offline — consulte a tabela de 14 dias abaixo"*) sem interromper o polling nem o restante do painel.
- **Sessão expirada durante polling:** comportamento existente preservado (redirecionamento para `/admin?expired=...` em HTTP 401).

---

## 9. Métricas de sucesso
- **Cobertura de indicadores:** 100% dos indicadores da análise operacional (Hoje vs. Ontem com `page_views` e novas wallets, 7 regras de Pontos de Atenção, Saúde Operacional 25/25 + uptime 30d + integrações, `paid_requests_per_service` real vs. volume total, tabela 14 dias e gráfico Chart.js) visíveis e alimentados por dados reais no `/admin`.
- **Zero duplicação de KPIs:** cada KPI de Growth e Receita exibido em sua aba principal sem repetição redundante na mesma tela.
- **Alertas Telegram sem spam:** 100% de aderência ao cooldown de 60 minutos verificado por teste automatizado (2 avaliações seguidas geram exatamente 1 disparo; após expiração do cooldown, dispara novamente).
- **Qualidade e estabilidade:** 0 erros no console JS em desktop e mobile, 100% de aprovação na suíte PHP (`test_admin_dashboard.php`, `test_maturity_and_m2m_controls.php`, `test_concurrency_and_load.php`, `test_session_pass_statement.php`, `verify_storage_integrity.php`) e validação estrita do OpenSpec (`openspec validate --all --strict`).

---

## 10. Riscos, dependências e premissas
- **Risco — Spam no Telegram durante rajadas contínuas:** mitigado pelo arquivo `admin_alerts_state.json` com `flock` e cooldown de 60 minutos por `alert_id` (compartilhado entre `collector.php` e `/admin?api=stats`).
- **Risco — Quebra de seletores/contratos em testes existentes:** `tests/test_admin_dashboard.php` valida estrutura HTML, token CSRF, `/admin?api=stats` e modais; mitigado preservando todos os campos existentes da API e IDs/formulários dos modais de segurança/configuração.
- **Risco — Tamanho e manutenibilidade de `public_html/admin.php`:** o arquivo já possui ~4.060 linhas; recomenda-se extrair a lógica pura do motor de alertas, comparativo Hoje vs. Ontem e agregação de saúde operacional para um módulo dedicado (ex.: `public_html/services/admin_insights.php`), facilitando testes unitários isolados e mantendo `admin.php` enxuto.
- **Dependência:** carregamento externo do Chart.js v4 via jsDelivr (mitigado por fallback gracioso + tabela 14d nativa).
- **Premissa:** os arquivos `summary_free.json`, `uptime_history.json`, `metrics.json`, `growth.json`, `requests.jsonl` e `session_passes.json` continuam em `GRID402_PRIVATE_DIR`.

---

## 11. Questões em aberto
- Nenhuma pendência bloqueante — todas as decisões de arquitetura UX, escopo dos 5 blocos de indicadores, limiares de alertas, envio Telegram (apenas severidade Alta) e uso de Chart.js v4 foram alinhadas na etapa de Grill-me.
