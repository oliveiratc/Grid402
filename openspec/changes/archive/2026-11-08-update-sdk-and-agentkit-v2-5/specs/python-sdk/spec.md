## ADDED Requirements

### Requirement: Atualizacao do Python SDK para Versao 2.5.0
O pacote `grid402` para Python SHALL ser atualizado para a versao `2.5.0` garantindo paridade total com o Node SDK e suporte completo aos 12 microservicos.

#### Scenario: Consulta de versao do pacote Python
- GIVEN o pacote `grid402` instalado ou importado em Python
- WHEN a constante `grid402.__version__` e inspecionada
- THEN o valor SHALL ser exatamente `"2.5.0"`.

#### Scenario: Compilacao de artefatos de distribuicao local
- GIVEN o codigo do SDK no diretorio `python/`
- WHEN o processo de build for executado
- THEN os arquivos de distribuicao `grid402-2.5.0-py3-none-any.whl` e `grid402-2.5.0.tar.gz` SHALL ser gerados no diretorio `python/dist/`.

### Requirement: Provedores Coinbase AgentKit Atualizados
Os provedores de acao para o Coinbase AgentKit em `integrations/agentkit/` (TypeScript e Python) SHALL incluir suporte a indicadores economicos, feriados, dias uteis e verificacao de oraculo.

#### Scenario: Execucao de acao de dias uteis via Coinbase AgentKit
- GIVEN um agente configurado com o provedor Grid402ActionProvider
- WHEN o agente invoca a acao de dias uteis com parametros `start_date` e `end_date`
- THEN o provedor SHALL consultar o servico do Grid402 e retornar o calculo de dias uteis com prova criptografica.

#### Scenario: Execucao de acao de verificacao de oraculo via Coinbase AgentKit
- GIVEN um agente configurado com o provedor Grid402ActionProvider
- WHEN o agente invoca a acao de verificacao passando um payload assinado
- THEN o provedor SHALL despachar a requisicao ao endpoint de verificacao e retornar o atestado matematico.

