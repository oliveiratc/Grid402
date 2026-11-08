## ADDED Requirements

### Requirement: Python client methods for workdays calculator
O SDK Python (`grid402`) SHALL expor o metodo `get_workdays(...)` retornando o modelo Pydantic v2 `WorkdaysResponse` com suporte a auto-liquidacao x402 ou Sandbox.

#### Scenario: Calling get_workdays on Grid402Client
- **GIVEN** uma instancia de `Grid402Client` em Python
- **WHEN** o metodo `client.get_workdays(start_date="2026-11-06", days=5)` e executado
- **THEN** ele retorna uma instancia validada de `WorkdaysResponse` contendo o resultado do calculo temporal

