## ADDED Requirements

### Requirement: Node.js client methods for workdays calculator
O SDK Node.js e TypeScript (`grid402`) SHALL expor o metodo `getWorkdays(params, paymentTxHash?)` retornando promessa com tipos TypeScript estritos `WorkdaysResponse` e suporte a auto-liquidacao x402 ou Sandbox.

#### Scenario: Calling getWorkdays on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` configurada com Sandbox ou chave EVM
- **WHEN** o metodo `client.getWorkdays({ startDate: "2026-11-06", days: 5 })` e executado
- **THEN** ele retorna um objeto do tipo `WorkdaysResponse` contendo o resultado do calculo de dias uteis com status HTTP 200

