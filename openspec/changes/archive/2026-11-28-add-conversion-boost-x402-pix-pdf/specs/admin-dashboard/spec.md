# Delta Spec: admin-dashboard

## ADDED Requirements

### Requirement: Classificação e Filtro de Probes de Conformidade vs. Falhas Reais de Pagadores
O módulo de observabilidade e o painel `/admin` MUST classificar cada entrada em `private_data/payment_failures.json` com o atributo `failure_origin` assumindo `'compliance_probe'` (rajadas automatizadas de testes sintéticos ou assinaturas/provas mockadas em janela `<= 15 segundos` para um mesmo `ip_hmac`) ou `'payer_failure'` (falhas isoladas atribuíveis a pagadores reais), expondo contadores separados em `/admin?api=stats` e filtro visual dedicado na aba *Saúde e Alertas*.

#### Scenario: Rajada automatizada no mesmo segundo é classificada como compliance_probe
- **GIVEN** um mesmo cliente (`ip_hmac`) gerando 3 ou mais falhas de pagamento distintas em intervalo menor ou igual a 15 segundos em `payment_failures.json`
- **WHEN** o sistema persiste ou lê as falhas em `grid402_read_recent_payment_failures()`
- **THEN** os eventos dessa rajada SHALL receber `failure_origin: "compliance_probe"`
- **AND** uma falha isolada fora de rajada e sem padrão sintético SHALL receber `failure_origin: "payer_failure"`

#### Scenario: Endpoint /admin?api=stats e UI exibem contadores e filtro por origem da falha
- **GIVEN** o operador autenticado acessando `GET /admin?api=stats` e `GET /admin` com registros mistos de `payer_failure` e `compliance_probe`
- **WHEN** a resposta JSON e o HTML da aba *Saúde e Alertas* são renderizados
- **THEN** `/admin?api=stats` SHALL incluir `payment_failures_summary` com os totais `total`, `payer_failure` e `compliance_probe`
- **AND** a tabela de diagnóstico no `/admin` SHALL exibir badge diferenciando `Pagador Real` de `Probe Conformidade` com botões funcionais de filtragem por origem
