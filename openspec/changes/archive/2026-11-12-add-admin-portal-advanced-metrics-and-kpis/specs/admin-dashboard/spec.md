# admin-dashboard Delta Specification

## ADDED Requirements

### Requirement: Exibição de KPIs Executivos Avançados
O sistema MUST calcular e renderizar no painel administrativo métricas executivas de desempenho incluindo Ticket Médio (ARPU em USDC), Taxa de Sucesso Técnico (SLA HTTP 200 %) e Taxa de Adoção de Sandbox (%).

#### Scenario: Cálculo de Ticket Médio (ARPU) e SLA no Dashboard
- **GIVEN** que o sistema possui consultas registradas e faturamento acumulado em `metrics.json`
- **WHEN** o operador visualizar o dashboard administrativo ou requisitar `/admin?api=stats`
- **THEN** o sistema deve retornar o Ticket Médio por consulta paga em USDC calculado pela divisão entre a receita total e o número de consultas pagas
- **AND** deve retornar o SLA técnico calculado pela porcentagem de requisições com código HTTP 200 sobre o total de requisições
- **AND** deve retornar a porcentagem de chamadas sandbox sobre o total de consultas

---

### Requirement: Gráfico Temporal Diário SVG Nativo dos Últimos 14 Dias
O sistema MUST renderizar um gráfico temporal SVG nativo interativo exibindo a evolução diária de requisições e faturamento em USDC dos últimos 14 dias sem carregar dependências externas de CDN.

#### Scenario: Renderização e rotação da janela móvel de 14 dias
- **GIVEN** que o histórico diário em `metrics.json` contém registros distribuídos ao longo do tempo
- **WHEN** o operador carregar o painel administrativo
- **THEN** o sistema deve exibir um gráfico vetorial SVG com as barras dos últimos 14 dias
- **AND** deve expurgar automaticamente dias anteriores ao período móvel de 14 dias para evitar sobrecarga no arquivo JSON
- **AND** cada coluna do gráfico deve permitir interação com tooltip exibindo a data, total de requisições, consultas pagas e faturamento em USDC

---

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

## MODIFIED Requirements

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
