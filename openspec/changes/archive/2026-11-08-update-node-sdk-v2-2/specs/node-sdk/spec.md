# Delta Spec: Node.js e TypeScript SDK Grid402 v2.2.0

## ADDED Requirements

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
