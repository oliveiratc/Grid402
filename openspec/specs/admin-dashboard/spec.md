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
