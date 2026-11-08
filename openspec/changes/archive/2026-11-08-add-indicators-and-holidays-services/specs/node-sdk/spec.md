## ADDED Requirements

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

