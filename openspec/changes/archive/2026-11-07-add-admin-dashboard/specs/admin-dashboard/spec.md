# admin-dashboard Specification

## ADDED Requirements

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
O sistema MUST computar metricamente cada requisição recebida nas APIs públicas sem corromper arquivos sob concorrência e com buffer circular de eventos recentes.

#### Scenario: Registro atômico de consulta na API pública
- GIVEN que uma requisição é processada nas APIs (`/api/v1/*` ou `/status`)
- WHEN o gateway processar o resultado da chamada (HTTP 200, 402 ou 403)
- THEN o sistema deve atualizar os contadores em `metrics.json` usando trava de arquivo exclusiva (`flock(LOCK_EX)`)
- AND deve adicionar a consulta ao feed de eventos recentes
- AND o limite do feed não deve ultrapassar 50 eventos recentes (descartando o mais antigo)

---

### Requirement: Monitoramento de Faturamento e Uso em Tempo Real
O sistema MUST consolidar o faturamento recebido em USDC na Base e permitir a consulta dinâmica das métricas via polling leve em segundo plano.

#### Scenario: Consulta de métricas autenticada via endpoint JSON
- GIVEN que o operador possui sessão administrativa ativa
- WHEN o front-end requisitar `GET /admin?api=stats`
- THEN o sistema deve responder com HTTP 200 e payload JSON estruturado
- AND o JSON deve conter o total de requisições, faturamento acumulado em USDC, contadores por serviço e a lista das consultas recentes

#### Scenario: Cálculo do faturamento em USDC e estimativa em BRL
- GIVEN que transações on-chain confirmadas constam no sistema
- WHEN o painel calcular o faturamento
- THEN cada transação técnica liquidada deve somar 0.0005 USDC ao total
- AND deve exibir a estimativa em Reais com base na cotação cambial do módulo `forex`

---

### Requirement: Anonimização de IP e Conformidade LGPD
O sistema MUST mascarar os endereços IP dos requisitantes no painel para assegurar que nenhum dado pessoal identificável seja exposto no histórico.

#### Scenario: Anonimização de endereço IPv4
- GIVEN que um cliente com IP `189.40.122.50` consultou uma API
- WHEN o evento for registrado no painel administrativo
- THEN o campo de IP deve ser mascarado como `189.40.xxx.xxx`
