# admin-dashboard Specification

## Purpose
Prover painel web administrativo seguro para monitoramento em tempo real de telemetria, faturamento de micropagamentos x402 e controle de primeiro acesso sem exposicao de dados pessoais.

## Requirements

### Requirement: Setup de Primeiro Acesso
O sistema MUST permitir que o operador defina o nome de usuário e a senha administrativa no primeiro acesso quando não houver credenciais configuradas.

#### Scenario: Primeiro acesso sem credenciais prévias
- GIVEN que o arquivo `private_data/admin_auth.json` não existe no servidor
- WHEN o operador requisitar a rota `/admin`
- THEN o sistema deve exibir a tela de configuração inicial de credenciais
- AND não deve exigir senha prévia para acessar esse formulário de setup inicial

#### Scenario: Cadastro das credenciais iniciais com sucesso
- GIVEN que a tela de configuração inicial está ativa
- WHEN o operador submeter um nome de usuário e uma senha com no mínimo 8 caracteres
- THEN o sistema deve salvar o hash criptográfico com `PASSWORD_BCRYPT` em `private_data/admin_auth.json`
- AND deve iniciar a sessão administrativa autenticada imediatamente
- AND deve redirecionar o operador para o painel principal

---

### Requirement: Autenticação Administrativa e Sessão Segura
O sistema MUST proteger todas as rotas e dados administrativos com autenticação de usuário e senha, mantendo sessões protegidas contra sequestro e fixação.

#### Scenario: Acesso a rota administrativa sem estar logado
- GIVEN que o operador já cadastrou suas credenciais administrativas
- AND o operador não possui sessão ativa no navegador
- WHEN o operador requisitar a rota `/admin` ou `/admin?api=stats`
- THEN o sistema deve recusar o acesso às métricas
- AND deve exibir a tela de login (ou retornar HTTP 401 para requisições de API JSON)

#### Scenario: Login bem-sucedido com credenciais válidas
- GIVEN que o operador está na tela de login
- WHEN o operador informar o usuário e a senha corretos
- THEN o sistema deve validar a senha via `password_verify`
- AND deve regenerar o ID da sessão (`session_regenerate_id(true)`)
- AND deve registrar o timestamp do último login em `admin_auth.json`
- AND deve carregar o painel administrativo com sucesso

#### Scenario: Tentativa de login com senha incorreta
- GIVEN que o operador está na tela de login
- WHEN o operador submeter uma senha inválida
- THEN o sistema deve recusar a autenticação com mensagem de erro
- AND deve aplicar delay defensivo anti-bruteforce de no mínimo 300ms
- AND não deve conceder acesso a nenhum dado administrativo

#### Scenario: Encerramento de sessão (Logout)
- GIVEN que o operador está autenticado no painel
- WHEN o operador acionar a opção de Logout (`/admin?action=logout`)
- THEN a sessão PHP deve ser completamente destruída
- AND os cookies de sessão devem ser invalidados
- AND o operador deve ser redirecionado para a tela de login

---

### Requirement: Gravação Atômica de Telemetria e Buffer Circular
O sistema MUST computar metricamente cada requisição recebida nas APIs públicas sem corromper arquivos sob concorrência, identificando explicitamente a tipagem de tráfego e mantendo um buffer circular de 100 eventos recentes.

#### Scenario: Registro atômico de consulta na API pública
- **GIVEN** que uma requisição é processada nas APIs (`/api/v1/*` ou `/status`)
- **WHEN** o gateway processar o resultado da chamada (HTTP 200, 402 ou 403)
- **THEN** o sistema deve atualizar os contadores em `metrics.json` usando trava de arquivo exclusiva (`flock(LOCK_EX)`)
- **AND** deve adicionar a consulta ao feed de eventos recentes classificando o atributo `type` como `paid`, `sandbox`, `challenge_402`, `blocked_403` ou `free`
- **AND** o limite do feed não deve ultrapassar 100 eventos recentes (descartando o mais antigo quando atingir o teto)

### Requirement: Monitoramento de Faturamento e Uso em Tempo Real
O sistema MUST consolidar o faturamento recebido em USDC na Base, abranger todos os 19 micro-serviços operantes do ecossistema e permitir a consulta dinâmica das métricas via polling leve em segundo plano com latência menor que 50 ms.

#### Scenario: Consulta de métricas autenticada via endpoint JSON
- **GIVEN** que o operador possui sessão administrativa ativa
- **WHEN** o front-end requisitar `GET /admin?api=stats`
- **THEN** o sistema deve responder com HTTP 200 e payload JSON estruturado
- **AND** o JSON deve conter o total de requisições, consultas pagas, consultas sandbox, faturamento total e diário, saldos on-chain, lista das 100 consultas recentes, ARPU em USDC, taxa de sucesso técnico, taxa de sandbox, histórico diário dos 14 dias, top consumidores e rentabilidade por serviço

#### Scenario: Cálculo do faturamento em USDC e estimativa em BRL
- **GIVEN** que transações on-chain confirmadas constam no sistema
- **WHEN** o painel calcular o faturamento
- **THEN** cada transação técnica liquidada deve somar o valor tarifado (0.0005 a 0.0050 USDC) ou valor do passe de sessão ao total
- **AND** deve exibir a estimativa em Reais com base na cotação cambial do módulo `forex`

### Requirement: Anonimização de IP e Conformidade LGPD
O sistema MUST mascarar os endereços IP dos requisitantes no painel para assegurar que nenhum dado pessoal identificável seja exposto no histórico.

#### Scenario: Anonimização de endereço IPv4
- GIVEN que um cliente com IP `189.40.122.50` consultou uma API
- WHEN o evento for registrado no painel administrativo
- THEN o campo de IP deve ser mascarado como `189.40.xxx.xxx`

### Requirement: Monitoramento On-Chain da Carteira do Oraculo
O sistema SHALL consultar o saldo de USDC e a reserva de ETH para gas da carteira oficial do oraculo na Base Mainnet via chamadas JSON-RPC diretas, mantendo cache em disco de 60 segundos para assegurar operacao ultrarrapida e resiliente.

#### Scenario: Leitura e exibicao dos saldos on-chain no dashboard
- **GIVEN** que o operador autenticado acessa o painel ou executa o polling de metricas
- **WHEN** o backend compor os dados da resposta administrativa
- **THEN** o sistema deve retornar os saldos vigentes da carteira `0xa31c31069c43ac1c0075a93c325b0dcd7744356c` em USDC e ETH
- **AND** deve reutilizar o cache em disco se a ultima chamada RPC ocorreu ha menos de 60 segundos
- **AND** o dashboard deve exibir um card dedicado de Saldo On-Chain com indicativo de saude do oraculo

### Requirement: Filtros Reativos e Busca no Feed de Eventos
O sistema SHALL disponibilizar na interface do painel controles reativos em Vanilla JS para filtragem imediata do feed por tipo de trafego e busca textual sem realizar novas requisicoes ao servidor.

#### Scenario: Filtragem instantanea por tipo de consulta
- **GIVEN** que o feed de consultas recentes exibe eventos mistos
- **WHEN** o operador clica no botao de filtro "Sandbox" ou "Pagos"
- **THEN** o script no cliente oculta as linhas divergentes e mantem apenas as correspondentes ao tipo selecionado
- **AND** a contagem de linhas visiveis e atualizada imediatamente

#### Scenario: Busca textual por IP ou hash de transacao
- **GIVEN** que o operador deseja localizar uma transacao especifica
- **WHEN** o operador digita os primeiros caracteres de um hash ou IP no campo de busca
- **THEN** as linhas da tabela sao filtradas dinamicamente a cada caractere digitado

### Requirement: Exportacao de Relatorio Financeiro CSV
O sistema SHALL fornecer um endpoint administrativo e botao de interface para download direto de relatorio estruturado em formato CSV com suporte a acentuacao UTF-8 com BOM.

#### Scenario: Download de relatorio CSV de faturamento e telemetria
- **GIVEN** que o operador autenticado requisita `GET /admin?api=export_csv`
- **WHEN** a requisicao e processada
- **THEN** o sistema deve gerar e retornar um arquivo `grid402_telemetria_YYYY-MM-DD.csv` com status HTTP 200
- **AND** os cabecalhos de resposta devem conter `Content-Type: text/csv; charset=utf-8` e `Content-Disposition: attachment`
- **AND** o conteudo deve listar todas as consultas gravadas com colunas de Horario UTC, Servico, Status, Tipo, Valor USDC, Cotacao PTAX BRL, Valor BRL, Hash BaseScan e IP anonimizado

### Requirement: Gerenciamento Seguro de Senha pelo Dashboard
O sistema SHALL disponibilizar no cabecalho do dashboard autenticado um modal para alteracao da senha administrativa do operador, exigindo comprovacao da senha atual e validando requisitos minimos de complexidade.

#### Scenario: Alteracao de senha com credencial atual correta
- **GIVEN** que o operador esta autenticado no painel
- **WHEN** o operador submete o modal informando a senha atual correta e uma nova senha com no minimo 8 caracteres
- **THEN** o sistema deve atualizar o hash `PASSWORD_BCRYPT` em `private_data/admin_auth.json`
- **AND** deve manter a sessao ativa exibindo mensagem de sucesso

#### Scenario: Tentativa de alteracao com senha atual incorreta
- **GIVEN** que o operador tenta alterar a senha
- **WHEN** a senha atual informada for invalida
- **THEN** o sistema deve rejeitar a alteracao com mensagem de erro
- **AND** nao deve modificar as credenciais gravadas em disco

### Requirement: Roteamento Resiliente de Fallback para o Painel
O gateway central `public_html/index.php` SHALL interceptar qualquer solicitacao direcionada a `/admin` ou `/dashboard`, encaminhando a execucao imediatamente ao script `public_html/admin.php`.

#### Scenario: Acesso a rota administrativa atraves do gateway index.php
- **GIVEN** um ambiente de hospedagem onde o servidor web entrega a requisicao `/admin` ao index.php
- **WHEN** o gateway principal receber a chamada
- **THEN** deve carregar diretamente o modulo `admin.php` preservando query strings e headers
- **AND** nao deve renderizar a landing page publica

### Requirement: Exibição de KPIs Executivos Avançados
O sistema MUST calcular e renderizar no painel administrativo métricas executivas de desempenho incluindo Ticket Médio (ARPU em USDC), Taxa de Sucesso Técnico (SLA HTTP 200 %) e Taxa de Adoção de Sandbox (%).

#### Scenario: Cálculo de Ticket Médio (ARPU) e SLA no Dashboard
- **GIVEN** que o sistema possui consultas registradas e faturamento acumulado em `metrics.json`
- **WHEN** o operador visualizar o dashboard administrativo ou requisitar `/admin?api=stats`
- **THEN** o sistema deve retornar o Ticket Médio por consulta paga em USDC calculado pela divisão entre a receita total e o número de consultas pagas
- **AND** deve retornar o SLA técnico calculado pela porcentagem de requisições com código HTTP 200 sobre o total de requisições
- **AND** deve retornar a porcentagem de chamadas sandbox sobre o total de consultas

---

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

### Requirement: Painel de Top Agentes e Consumidores Mais Ativos
O sistema MUST agregar os eventos do buffer circular para identificar os principais requisitantes (endereços IP mascarados em conformidade com a LGPD), exibindo seu volume de consultas e categorização de uso.

#### Scenario: Agrupamento de agentes consumidores no painel
- **GIVEN** que há eventos registrados no buffer circular recente
- **WHEN** o painel compor os dados analíticos
- **THEN** o sistema deve agrupar as requisições por endereço IP mascarado
- **AND** deve exibir o ranking dos 5 a 10 IPs mais ativos com o total de consultas e a distribuição de tipos (pagas, sandbox ou desafios 402)
- **AND** nenhum endereço IP deve ser exibido em formato desmascarado

---

### Requirement: Painel de Rentabilidade por Micro-Serviço
O sistema MUST apresentar uma estimativa de receita gerada por micro-serviço com base nas requisições tarifadas e seus respectivos preços de tabela.

#### Scenario: Visualização da rentabilidade por endpoint
- **GIVEN** que os 19 micro-serviços possuem tabela de preços definida em USDC
- **WHEN** o operador visualizar a seção analítica de rentabilidade
- **THEN** o sistema deve exibir a receita estimada por serviço ordenando dos nós mais rentáveis para os menos rentáveis

---

### Requirement: Exposicao de Latencia P95 Agregada e Politica de Chaves M2M
O sistema SHALL manter amostra circular de latências (`latency_samples_ms`, até 200 registros) na telemetria atômica e exibir a latência **p95 agregada** em ms nos painéis `/admin` e `/trust`, além de documentar no `/trust` e nos guias técnicos a arquitetura não-custodial de chaves M2M e recomendações de isolamento de carteiras.

#### Scenario: Consulta e exibicao da latencia p95 agregada no /admin e /trust
- **GIVEN** requisições registradas com medição de tempo em `metrics.php`
- **WHEN** o operador consulta `GET /admin?api=stats` (autenticado) ou um avaliador acessa `/trust`
- **THEN** o payload JSON de `/admin?api=stats` SHALL incluir `latency_p95_ms` e `latency_p50_ms` numéricos
- **AND** as páginas `/admin` e `/trust` SHALL exibir o indicador agregado de latência `p95`.

### Requirement: Origem do pagamento no faturamento e na telemetria
O sistema MUST registrar a origem de cada pagamento (`tx_hash`, `facilitator`, `session_pass`, `sandbox` ou `mcp_sandbox`), somando ao faturamento apenas pagamentos liquidados e mantendo bloqueios HTTP `429` fora de `total_sandbox_requests` e `payment_sources['sandbox']`.

#### Scenario: Pagamento facilitado contabilizado
- **WHEN** um pagamento é liquidado via facilitador
- **THEN** o evento é gravado com origem `facilitator`, valor real e hash de liquidação, e o faturamento total e do dia aumentam pelo valor pago.

#### Scenario: Falha de liquidação não gera faturamento
- **WHEN** o facilitador rejeita a autorização ou fica indisponível
- **THEN** o evento é gravado como falha com o motivo, sem alterar o faturamento.

#### Scenario: Bloqueios HTTP 429 não inflam Sandbox e auto-trial MCP usa origem mcp_sandbox
- **GIVEN** um cliente que consome o auto-trial diário do `/mcp` (HTTP `200`) e depois envia requisições `X-Payment: demo` além da cota recebendo HTTP `429`
- **WHEN** `grid402_record_telemetry()` registra esses eventos
- **THEN** o auto-trial HTTP `200` do `/mcp` SHALL incrementar `payment_sources['mcp_sandbox']` separado de `payment_sources['sandbox']`
- **AND** o bloqueio HTTP `429` (`SandboxRateLimitExceeded`) SHALL incrementar apenas `status_counts['429']` e `failure_categories['sandbox_rate_limited']`, sem somar em `total_sandbox_requests` nem em `payment_sources['sandbox']`.

### Requirement: Visibilidade de falhas de pagamento
O sistema MUST exibir no painel a contagem de falhas por categoria (incluindo `sandbox_rate_limited`), mantendo bloqueios `429` de sandbox separados de `payment_rejected` e filtrados da tabela de falhas reais `recent_payment_failures`.

#### Scenario: Categorias de falha visíveis
- **WHEN** o administrador abre o painel após falhas de pagamento
- **THEN** cada categoria de falha aparece com sua contagem, e a soma das barras de status corresponde ao total de consultas.

#### Scenario: Filtragem de bloqueios 429 sandbox em falhas recentes
- **WHEN** ocorrem bloqueios `HTTP 429 SandboxRateLimitExceeded` em modo sandbox junto a falhas reais de pagamento
- **THEN** a categoria `sandbox_rate_limited` aparece contabilizada separadamente de `payment_rejected`
- **AND** a tabela `recent_payment_failures` lista apenas falhas reais de pagamento/passe, ignorando `429 SandboxRateLimitExceeded` de sandbox.

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

### Requirement: Separação de Crawlers de Diretórios x402 e Endurecimento do Filtro de Page-Views
O sistema MUST classificar chamadas automatizadas de crawlers/scanners de diretórios x402 separadamente do tráfego real de API, ampliar a lista de User-Agents automatizados em page-views e impedir que `Accept: text/markdown` em `/` ou acessos com sessão administrativa autenticada contem como page-views humanos.

#### Scenario: Classificação de chamadas de crawlers de diretórios separada do tráfego real de API
- **GIVEN** um ambiente com chamadas reais de API e chamadas automatizadas de indexadores/scanners sem pagamento (`challenge_402` ou `/status`)
- **WHEN** `grid402_record_telemetry()` registra requisições com User-Agent de crawler/bot/scanner ou padrão de varredura de diretório
- **THEN** o sistema SHALL incrementar `total_crawler_requests` e `daily[$today]['crawler_requests']` mantendo `total_requests` retrocompatível
- **AND** `GET /admin?api=stats` e o cockpit `/admin` SHALL exibir `real_api_requests` (`total_requests - total_crawler_requests`) separado de `total_crawler_requests`.

#### Scenario: Filtro ampliado de User-Agents automatizados em page-views
- **GIVEN** requisições de páginas públicas usando clientes automatizados (`Go-http-client`, `undici`, `node-fetch`, `axios`, `httpx`, `aiohttp`, `Scrapy`, `Java/`, `okhttp`, `Guzzle`, `ChatGPT-User`, `Claude-Web`, `Meta-ExternalAgent`, `Playwright` ou `Puppeteer`)
- **WHEN** `grid402_record_page_visit()` processa a visita
- **THEN** `grid402_is_bot_user_agent()` SHALL identificar todos esses padrões como automatizados
- **AND** o sistema SHALL incrementar `total_bot_views` mantendo `total_page_views` inalterado.

#### Scenario: Exclusão de Accept text/markdown e sessão administrativa ativa de page-views humanos
- **GIVEN** uma requisição em `/` com cabeçalho `Accept: text/markdown` ou uma visita executada com cookie/sessão administrativa autenticada de `/admin`
- **WHEN** o gateway avalia o registro de visita de página
- **THEN** `total_page_views` SHALL NOT ser incrementado por negociações de markdown nem pela navegação do próprio operador autenticado.

### Requirement: Distinção entre Wallets Conectadas Reais e Perfis M2M Identificados
O sistema MUST contabilizar `wallets_connected` exclusivamente a partir de conexões reais de carteira Web3 confirmadas via `POST /api/v1/growth/wallet-connect` ou liquidação Web3 real com endereço válido, expondo separadamente `identified_profiles` para agentes/scanners M2M no `/admin`.

#### Scenario: Scanners 402, sandbox e falhas sem carteira não inflam Wallets Conectadas
- **GIVEN** eventos registrados em `growth.json` provenientes de desafios `402`, chamadas `demo` ou probes sem carteira EVM/SVM
- **WHEN** `grid402_growth_metrics()` e `/admin?api=stats` calculam o funil
- **THEN** os perfis correspondentes SHALL ser contabilizados em `identified_profiles`
- **AND** `wallets_connected` SHALL permanecer `0` até existir conexão real de carteira verificada.

#### Scenario: Registro autenticado de conexão de wallet com deduplicação e rate-limit
- **GIVEN** um visitante na Home ou em `/pass` após aprovação de `eth_requestAccounts` enviando `POST /api/v1/growth/wallet-connect` com `{"address": "0x..."}` válido
- **WHEN** o backend valida o formato `^0x[a-fA-F0-9]{40}$` e a cota por IP
- **THEN** o sistema SHALL marcar o perfil HMAC correspondente com `wallet_verified: true`, deduplicar chamadas repetidas da mesma carteira e responder HTTP `200`
- **AND** excessos acima da cota por janela SHALL retornar HTTP `429` sem inflar `wallets_connected`.

### Requirement: Ring Buffer Dedicado de Falhas Reais de Pagamento e Alertas do Mercado Pago
O sistema MUST persistir falhas reais de pagamento (x402, Session Pass e erros `502`/`503` em `/api/v1/pass/mp-*`) em `private_data/payment_failures.json` sob `flock(LOCK_EX)` com retenção de até 500 eventos Zero-PII, alimentando `/admin` de forma imune a rajadas `402` e disparando alertas automáticos do Mercado Pago.

#### Scenario: Preservação das falhas reais mesmo após rajada superior a 256 KB de desafios 402
- **GIVEN** uma falha real de pagamento gravada em `payment_failures.json` seguida por mais de 300 KB de eventos `402 PaymentRequired` gravados em `requests.jsonl`
- **WHEN** o operador consulta `GET /admin?api=stats` ou a tabela `recent_payment_failures`
- **THEN** a falha real SHALL continuar visível em `recent_payment_failures` com tokens/segredos redigidos e retenção de até 500 entradas
- **AND** a categoria original da falha SHALL permanecer contabilizada em `failure_categories`.

#### Scenario: Falha 502 ou 503 no checkout Mercado Pago registrada na telemetria e nas falhas reais
- **GIVEN** uma chamada a `POST /api/v1/pass/mp-pix` ou `POST /api/v1/pass/mp-checkout` que falha com HTTP `502` ou `503`
- **WHEN** o gateway finaliza a resposta de erro
- **THEN** o sistema SHALL incrementar `failure_categories['mercadopago_gateway_error']` em `metrics.json`
- **AND** SHALL gravar entrada sanitizada em `private_data/payment_failures.json` sem expor access tokens ou e-mails do pagador.

#### Scenario: Alertas automáticos de token Mercado Pago ausente em produção ou falhas recentes
- **GIVEN** um ambiente com `GRID402_MP_SANDBOX=false` e `GRID402_MP_ACCESS_TOKEN` vazio, ou com falhas recentes de `mercadopago_gateway_error`
- **WHEN** `grid402_admin_evaluate_alerts()` avalia os Pontos de Atenção
- **THEN** o motor SHALL emitir alertas automáticos claros no `/admin` identificando configuração ausente do Mercado Pago ou falhas recentes do gateway Pix/Checkout.

### Requirement: Clareza de Status HTTP 400, Resíduo Histórico e Receita Externa RapidAPI no Admin
O painel `/admin` SHALL distinguir HTTP `400` de prova inválida (`invalid_proof`) de `400` por parâmetro inválido (`bad_request`), rotular explicitamente resíduos históricos pré-24/11 sem código HTTP e informar quando chamadas `rapidapi` têm faturamento liquidado externamente no RapidAPI Hub.

#### Scenario: Separação visual entre HTTP 400 de prova inválida e HTTP 400 de parâmetro inválido
- **GIVEN** chamadas HTTP `400` registradas com categoria `invalid_proof` e chamadas HTTP `400` de validação prévia de parâmetros
- **WHEN** o cockpit `/admin` renderiza a distribuição de status HTTP
- **THEN** o painel SHALL exibir `400 Prova inválida` e `400 Parâmetro inválido` separadamente em vez de agrupar todo `400` sob o mesmo rótulo.

#### Scenario: Rotulagem explícita de resíduo histórico pré-24/11 e aviso de faturamento externo RapidAPI
- **GIVEN** métricas legadas onde `total_requests` supera a soma de `status_counts` e há chamadas pagas com origem `rapidapi`
- **WHEN** o painel `/admin` exibe a distribuição de status e receita por serviço
- **THEN** a diferença histórica SHALL ser rotulada explicitamente como resíduo histórico pré-24/11 (sem misturar com API recente)
- **AND** o painel SHALL indicar que a receita de chamadas `rapidapi` é apurada externamente no RapidAPI Hub (`US$ 0,00` local).

### Requirement: Telemetria Pública x402scan no Painel Administrativo
O sistema MUST consultar e cachear localmente (`300s` em storage Zero-DB) os dados públicos do **x402scan** (`public.origins.get` e `public.stats.overall` nas janelas `1d`, `7d` e `30d` para as carteiras EVM e Solana do Grid402), expondo-os exclusivamente no cockpit autenticado `/admin` (API JSON `/admin?api=stats`, aba **Receita e Passes** e indicador resumido no **Resumo Executivo**).

#### Scenario: Consulta ao vivo e cache Zero-DB de 300 segundos no serviço x402scan
- **GIVEN** que não existe cache válido em `private_data/x402scan_cache.json`
- **WHEN** `grid402_get_x402scan_stats(false)` for invocado
- **THEN** o sistema deve consultar `public.origins.get` e `public.stats.overall` para `timeframe` `1`, `7` e `30` somando as carteiras EVM e Solana oficiais
- **AND** deve converter `total_amount` em unidades raw USDC (6 casas decimais) para `volume_usdc`
- **AND** deve persistir o resultado em `private_data/x402scan_cache.json` via `grid402_atomic_write()` retornando `status = "live"`
- **AND** chamadas subsequentes dentro de `300s` com `$forceRefresh = false` devem retornar `status = "cached"` sem executar novas requisições HTTP externas

#### Scenario: Fallback resiliente em falha upstream (stale_cache e unavailable)
- **GIVEN** que existe um `x402scan_cache.json` expirado em disco e a API do x402scan falha ou excede timeout
- **WHEN** `grid402_get_x402scan_stats(false)` for invocado
- **THEN** o sistema deve preservar e retornar os dados anteriores com `status = "stale_cache"` e `stale = true`
- **AND** caso não exista nenhum cache prévio em disco durante a falha, deve retornar `status = "unavailable"` com valores vazios seguros sem interromper o carregamento do `/admin`

#### Scenario: Exposição autenticada em `/admin?api=stats` e refresh manual
- **GIVEN** uma sessão administrativa autenticada no `/admin`
- **WHEN** o cliente requisitar `GET /admin?api=stats` (ou `GET /admin?api=stats&refresh=1`)
- **THEN** a resposta HTTP `200` deve conter o objeto `x402scan` com `origin`, `wallets`, `windows.d1`, `windows.d7`, `windows.d30` e `server_url`
- **AND** com `&refresh=1`, o backend deve ignorar o TTL de `300s` e buscar dados frescos no upstream
- **AND** requisições sem sessão ativa devem continuar recebendo HTTP `401`

#### Scenario: Renderização do painel multijanela na aba Receita e Passes e indicador no Resumo Executivo
- **GIVEN** que o operador autenticado acessa `GET /admin`
- **WHEN** o HTML do cockpit for renderizado
- **THEN** a aba **Receita e Passes** deve exibir o painel dedicado **x402scan — Índice Público On-Chain** com as janelas `24h`, `7d` e `30d`, carteiras monitoradas EVM + Solana, badge de protocolo `x402 v2` e link para `https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50`
- **AND** a aba **Resumo Executivo** deve exibir o indicador compacto com transações e volume `30d` do x402scan
- **AND** páginas públicas (`/`, `/trust`, `/status`) não devem exibir dados administrativos do x402scan
