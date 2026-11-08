## ADDED Requirements

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

