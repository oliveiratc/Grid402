# Delta Spec: admin-dashboard

## MODIFIED Requirements

### Requirement: Gravação Atômica de Telemetria e Buffer Circular
O sistema MUST computar metricamente cada requisição recebida nas APIs públicas sem corromper arquivos sob concorrência, identificando explicitamente a tipagem de tráfego e mantendo um buffer circular de 100 eventos recentes.

#### Scenario: Registro atômico de consulta na API pública
- **GIVEN** que uma requisição é processada nas APIs (`/api/v1/*` ou `/status`)
- **WHEN** o gateway processar o resultado da chamada (HTTP 200, 402 ou 403)
- **THEN** o sistema deve atualizar os contadores em `metrics.json` usando trava de arquivo exclusiva (`flock(LOCK_EX)`)
- **AND** deve adicionar a consulta ao feed de eventos recentes classificando o atributo `type` como `paid`, `sandbox`, `challenge_402`, `blocked_403` ou `free`
- **AND** o limite do feed não deve ultrapassar 100 eventos recentes (descartando o mais antigo quando atingir o teto)

### Requirement: Monitoramento de Faturamento e Uso em Tempo Real
O sistema MUST consolidar o faturamento recebido em USDC na Base, abranger todos os 11 micro-serviços operantes do ecossistema e permitir a consulta dinâmica das métricas via polling leve em segundo plano com latência menor que 50 ms.

#### Scenario: Consulta de métricas autenticada via endpoint JSON
- **GIVEN** que o operador possui sessão administrativa ativa
- **WHEN** o front-end requisitar `GET /admin?api=stats`
- **THEN** o sistema deve responder com HTTP 200 e payload JSON estruturado
- **AND** o JSON deve conter o total de requisições, faturamento acumulado em USDC, contadores de volume para todos os 11 micro-serviços (`workdays`, `indicators`, `holidays`, `forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `cnpj`, `summary`), saldos on-chain e a lista das 100 consultas recentes

#### Scenario: Cálculo do faturamento em USDC e estimativa em BRL
- **GIVEN** que transações on-chain confirmadas constam no sistema
- **WHEN** o painel calcular o faturamento
- **THEN** cada transação técnica liquidada deve somar o valor tarifado (0.0005 ou 0.0020 USDC) ao total
- **AND** deve exibir a estimativa em Reais com base na cotação cambial do módulo `forex`

## ADDED Requirements

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
