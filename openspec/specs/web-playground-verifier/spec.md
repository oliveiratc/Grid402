# web-playground-verifier Specification

## Purpose
Permite que desenvolvedores e operadores de agentes de IA experimentem os micro-servicos do Grid402 diretamente pelo navegador, simulem respostas HTTP 402 e auditem a prova matematica de oraculo em tres etapas visuais.

## Requirements

### Requirement: Secao de Playground e Verificador na Landing Page
A interface web do Grid402 SHALL exibir uma secao interativa de Playground e Verificador de Oraculo integrada na pagina principal com estilizacao dark-mode e link de navegacao no cabecalho.

#### Scenario: Navegacao rapida para o Playground
- **WHEN** o usuario clica no link "Playground" presente no cabecalho da landing page
- **THEN** a pagina executa uma rolagem suave posicionando a secao interativa no centro da tela

### Requirement: Barra de selecao de presets de micro-servicos
O Playground SHALL disponibilizar botoes de acao rapida para os 11 micro-servicos do ecossistema, preenchendo automaticamente os parametros de demonstracao.

#### Scenario: Selecao de preset de servico
- **WHEN** o usuario clica no botao de preset "Dias Uteis ANBIMA"
- **THEN** o Playground carrega os parametros padrao do micro-servico e prepara a chamada

### Requirement: Alternancia entre Modo Sandbox e Simulador x402
O Playground SHALL fornecer um alternador visual permitindo escolher entre **Simulador x402 (HTTP 402)**, **Modo Sandbox gratuito**, **Modo Session Pass (`pass_g402_...`)** e **Validar JSON Externo**, pré-preenchendo automaticamente o token Session Pass quando houver um token salvo na persistência unificada do navegador.

#### Scenario: Execucao em Modo Sandbox gratuito
- **WHEN** o usuario seleciona o modo Sandbox e clica em "Executar Consulta"
- **THEN** a requisicao e enviada com o cabecalho de teste gratuito e o painel exibe o JSON com a prova de oraculo

#### Scenario: Execucao no Simulador de Desafio x402
- **WHEN** o usuario seleciona o modo Simulador x402 e clica em "Executar Consulta"
- **THEN** a requisicao e enviada sem cabecalho de pagamento e o painel exibe o retorno HTTP 402 com amostra gratuita e preco em USDC

#### Scenario: Execucao em Modo Session Pass
- **GIVEN** um usuario com um token `pass_g402_...` salvo na persistência unificada ou informado no campo do Playground
- **WHEN** seleciona o modo Session Pass e clica em "Executar Consulta"
- **THEN** a requisicao e enviada com o cabecalho `X-Payment: pass_g402_...` e o painel exibe a resposta HTTP 200 com a prova de oraculo e os creditos restantes quando retornados no header

### Requirement: Card de Auditoria Tecnica em 3 Etapas
O Verificador SHALL auditar o bloco de prova criptografica e exibir um card de auditoria tecnica avaliando integridade, identidade e validade da assinatura.

#### Scenario: Prova de oraculo integra e legitima
- **WHEN** uma resposta valida emitida pelo Grid402 e analisada
- **THEN** o card apresenta 3 etapas verdes com selo de oraculo verificado, link para o BaseScan e tempo de checagem em milissegundos

#### Scenario: Deteccao de payload adulterado
- **WHEN** uma resposta cujo conteudo de dados foi modificado apos a assinatura e analisada
- **THEN** o card sinaliza falha na etapa de integridade de dados apontando divergencia de hash

### Requirement: Area de Verificacao Manual de JSON Arbitrario
O Verificador SHALL disponibilizar um campo de texto onde o usuario pode colar qualquer JSON de resposta anterior para validar sua autenticidade.

#### Scenario: Verificacao de JSON colado manualmente
- **WHEN** o usuario cola um JSON contendo o bloco da prova e aciona a verificacao
- **THEN** o sistema processa a analise e atualiza o card de auditoria com o resultado

### Requirement: Tratamento Dedicado de HTTP 429 no Playground Sem Loop de 402
Quando o Playground receber HTTP `429` (cota diária do Sandbox excedida), a interface MUST renderizar um estado dedicado de limite atingido oferecendo resgate em 1 clique do cupom gratuito **`MCPTRY50`** (`/pass?promo=MCPTRY50`), alternância direta para o **Modo Session Pass** e link para compra via Pix/Cartão USD/USDC, em vez de reutilizar o card genérico de desafio HTTP `402`.

#### Scenario: Limite Sandbox (429) exibe card dedicado com MCPTRY50 e atalho para Session Pass
- **GIVEN** um usuario executando consulta em modo Sandbox no Playground cuja resposta retorna HTTP `429`
- **WHEN** o front-end processa a resposta
- **THEN** o badge indica cota Sandbox excedida e o painel exibe explicitamente o CTA para resgatar `MCPTRY50` (`/pass?promo=MCPTRY50`) e o atalho para inserir/usar um Session Pass, sem tratar o erro como um desafio 402 comum

### Requirement: Visibilidade Clara de Erros no Pagamento Web3 do Playground
A função `payPlaygroundWithWeb3()` em `public_html/index.php` MUST distinguir cancelamento explícito do usuário (código EIP-1193 `4001` ou `ACTION_REJECTED`) de falhas técnicas de rede, troca de chain ou saldo, exibindo mensagem diagnóstica segura no status/viewer em caso de falha técnica em vez de rotular toda exceção como `"Pagamento Cancelado"`.

#### Scenario: Erro técnico de RPC ou carteira exibe motivo em vez de "Pagamento Cancelado"
- **GIVEN** um usuário acionando pagamento Web3 no Playground
- **WHEN** ocorre uma exceção cujo código não é `4001` (ex.: erro de rede ou saldo insuficiente)
- **THEN** o status do Playground indica falha de pagamento e exibe a mensagem sanitizada do erro em vez de `"Pagamento Cancelado"`
