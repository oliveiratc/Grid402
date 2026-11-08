# node-sdk Specification

## Purpose
Prover o SDK oficial Node.js e TypeScript (`grid402`) para consumo de dados publicos e auto-liquidacao de tarifas x402 via Coinbase AgentKit e modo Sandbox.

## Requirements

### Requirement: Suporte ao Modo Sandbox Gratuito no Cliente Node.js
O `Grid402Client` MUST permitir ativacao do modo sandbox via opcao `sandbox: true`, injetando o cabecalho `X-Payment: demo` automaticamente em todas as requisicoes.

#### Scenario: Requisicao com modo sandbox ativado
- GIVEN uma instancia de `Grid402Client` com `{ sandbox: true }`
- WHEN qualquer metodo de consulta for executado sem hash explicito
- THEN a requisicao HTTP DEVE conter o cabecalho `X-Payment` com o valor `demo`

### Requirement: Consulta Cadastral de CNPJ no Cliente Node.js
O `Grid402Client` MUST fornecer o metodo `getCnpj(cnpj, paymentTxHash?)` para consulta de dados cadastrais publicos de PJ na Receita Federal.

#### Scenario: Consulta de CNPJ em modo sandbox
- GIVEN uma instancia de `Grid402Client` com `{ sandbox: true }`
- WHEN o metodo `getCnpj("00000000000191")` for chamado
- THEN o cliente DEVE retornar os dados cadastrais da empresa com a razao social

### Requirement: Provedor de Acoes Coinbase AgentKit Unificado
O pacote `grid402` MUST exportar `Grid402ActionProvider` com capacidade de auto-liquidacao de micropagamentos x402 on-chain na Base utilizando o `wallet_provider` de um agente.

#### Scenario: Auto-liquidacao de desafio x402
- GIVEN uma instancia de `Grid402ActionProvider` e um `wallet_provider` valido
- WHEN uma consulta retornar HTTP 402 Payment Required
- THEN o provedor DEVE transferir o montante em USDC na rede Base e reenviar a chamada com o comprovante no cabecalho `X-Payment`

### Requirement: Suporte Hibrido ESM e CommonJS
O pacote `grid402` MUST ser consumivel tanto via `import` em modulos ESM quanto via `require` em scripts CommonJS, sem gerar excecoes de resolucao de modulo.

#### Scenario: Importacao via require CommonJS
- GIVEN um ambiente Node.js executando em modo CommonJS
- WHEN o modulo for carregado com `const { Grid402Client } = require('grid402')`
- THEN a classe `Grid402Client` DEVE ser instanciada e operavel normalmente

### Requirement: Node.js client methods for indicators and holidays
O SDK Node.js e TypeScript (`grid402`) SHALL expor os metodos `getIndicators()` e `getHolidays(year?)` retornando promessas com tipos TypeScript estritos e suporte a auto-liquidacao x402 ou Sandbox.

#### Scenario: Calling getIndicators on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` configurada com Sandbox ou chave EVM
- **WHEN** o metodo `client.getIndicators()` e executado
- **THEN** ele retorna um objeto do tipo `IndicatorsResponse` contendo os indicadores economicos consolidados

#### Scenario: Calling getHolidays on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` configurada
- **WHEN** o metodo `client.getHolidays(2026)` e executado
- **THEN** ele retorna um objeto do tipo `HolidaysResponse` contendo a lista de feriados nacionais e bancarios para o ano solicitado

### Requirement: Node.js client methods for workdays calculator
O SDK Node.js e TypeScript (`grid402`) SHALL expor o metodo `getWorkdays(params, paymentTxHash?)` retornando promessa com tipos TypeScript estritos `WorkdaysResponse` e suporte a auto-liquidacao x402 ou Sandbox.

#### Scenario: Calling getWorkdays on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` configurada com Sandbox ou chave EVM
- **WHEN** o metodo `client.getWorkdays({ startDate: "2026-11-06", days: 5 })` e executado
- **THEN** ele retorna um objeto do tipo `WorkdaysResponse` contendo o resultado do calculo de dias uteis com status HTTP 200
