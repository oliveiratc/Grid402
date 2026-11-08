# python-sdk Specification

## Purpose
Prover o SDK oficial em Python (`grid402`) com modelos Pydantic v2 para consumo dos utilitarios e auto-liquidacao de micropagamentos x402 via Coinbase AgentKit.

## Requirements

### Requirement: Cliente HTTP Central do Grid402
O SDK SHALL fornecer a classe `Grid402Client` para executar requisições HTTP às 8 ferramentas públicas do Grid402.

#### Scenario: Consulta no Modo Sandbox sem custos
- **GIVEN** uma instância de `Grid402Client(sandbox=True)`
- **WHEN** o método `get_cnpj("00000000000191")` é executado
- **THEN** a requisição SHALL incluir o cabeçalho `X-Payment: demo` e retornar o payload cadastral completo com `sandbox: true`.

#### Scenario: Consulta sem pagamento retorna desafio x402 estruturado
- **GIVEN** uma instância de `Grid402Client(sandbox=False)`
- **WHEN** uma consulta protegida é executada sem chave ou comprovante
- **THEN** o cliente SHALL capturar o HTTP 402 e retornar o dicionário contendo o desafio x402 e a amostra de dados `preview`.

### Requirement: Provedor de Ações para Coinbase AgentKit
O SDK SHALL exportar a classe `Grid402ActionProvider` permitindo que agentes autônomos realizem a auto-liquidação de tarifas x402 na Base.

#### Scenario: Auto-liquidação com carteira CDP na Base
- **GIVEN** o provedor configurado com um `wallet_provider` da Coinbase na rede Base
- **WHEN** o agente invoca qualquer ação de utilitário ou dado público
- **THEN** o provedor SHALL interceptar o desafio 402, transferir os USDC requeridos na Base, reenviar com o hash da transação e retornar os dados completos com HTTP 200.

### Requirement: Modelos de Entrada Pydantic Tipados
O SDK SHALL fornecer modelos Pydantic para validação estática e inferência de schemas por orquestradores de IA.

#### Scenario: Validação e serialização de parâmetros
- **GIVEN** os modelos `CnpjInput`, `CepInput`, `DnsInput`, `SslInput` e `AsnInput`
- **WHEN** uma função ou ferramenta de IA é anotada com um desses modelos
- **THEN** os parâmetros SHALL ser validados em tempo de execução garantindo conformidade de tipo e documentação de campo.

### Requirement: Empacotamento e Distribuição PyPI
O SDK SHALL ser estruturado em conformidade com PEP 517 / PEP 621 em `python/pyproject.toml` para geração de arquivos `.whl` e `.tar.gz`.

#### Scenario: Validação de metadados do pacote
- **GIVEN** o diretório `python/` com a configuração de build
- **WHEN** o comando de checagem do build é executado
- **THEN** os artefatos de distribuição SHALL ser validados sem erros de metadados ou arquivos ausentes.

### Requirement: Python client methods for indicators and holidays
O SDK Python (`grid402`) SHALL expor os metodos `get_indicators()` e `get_holidays(year=None)` retornando modelos Pydantic v2 `IndicatorsResponse` e `HolidaysResponse` com suporte a auto-liquidacao x402 ou Sandbox.

#### Scenario: Calling get_indicators on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` em Python
- **WHEN** o metodo `client.get_indicators()` e executado
- **THEN** ele retorna uma instancia validada de `IndicatorsResponse` contendo os indicadores macroeconomicos

#### Scenario: Calling get_holidays on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` em Python
- **WHEN** o metodo `client.get_holidays(year=2026)` e executado
- **THEN** ele retorna uma instancia validada de `HolidaysResponse` contendo a lista tipada de feriados

### Requirement: Python client methods for workdays calculator
O SDK Python (`grid402`) SHALL expor o metodo `get_workdays(...)` retornando o modelo Pydantic v2 `WorkdaysResponse` com suporte a auto-liquidacao x402 ou Sandbox.

#### Scenario: Calling get_workdays on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` em Python
- **WHEN** o metodo `client.get_workdays(start_date="2026-11-06", days=5)` e executado
- **THEN** ele retorna uma instancia validada de `WorkdaysResponse` contendo o resultado do calculo temporal

### Requirement: Python client oracle proof verification
O SDK Python (`grid402`) SHALL expor o metodo `verify_proof(payload, expected_oracle_address=None)` e o modelo Pydantic `OracleProof` para validacao criptografica local da integridade dos dados e autoria da carteira do oraculo.

#### Scenario: Verifying untampered response in Python
- **GIVEN** uma resposta da API Grid402 contendo o bloco `_oracle_proof` integro
- **WHEN** o metodo `client.verify_proof(response)` e executado
- **THEN** ele retorna um dicionario `{ "valid": True, "oracle_address": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c" }` confirmando a autenticidade

#### Scenario: Rejecting tampered response in Python
- **GIVEN** uma resposta cujo conteudo de dados foi adulterado apos a assinatura
- **WHEN** o metodo `client.verify_proof(response)` e executado
- **THEN** ele detecta a divergencia e retorna `{ "valid": False, "error": "DataHashMismatch" }`

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
