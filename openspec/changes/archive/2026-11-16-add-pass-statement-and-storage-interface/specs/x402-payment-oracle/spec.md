## ADDED Requirements

### Requirement: Abstração de Repositório Zero-DB para Session Passes (PassStorageInterface)
O sistema SHALL definir o contrato `PassStorageInterface` e a implementação oficial `JsonFlockPassRepository` baseada em JSON + `flock()`, concentrando emissão, consulta de saldo, atualização de Spend Controls, consumo atômico e extrato sem exigir extensões SQL externas (`pdo_sqlite` ou `pdo_mysql`).

#### Scenario: Operação completa via PassStorageInterface sem dependências SQL
- **GIVEN** um ambiente PHP 8.1+ sem `pdo_sqlite` nem `pdo_mysql` habilitados
- **WHEN** o gateway emite um Session Pass, configura Spend Controls e debita créditos através das funções de `services/pass.php`
- **THEN** todas as operações SHALL executar por meio de `JsonFlockPassRepository` (implementando `PassStorageInterface`) usando `flock()` e `grid402_read_locked_json()`, mantendo atomicidade estrita contra double-spend.

---

### Requirement: Registro Zero-PII em Ring Buffer de 200 Lançamentos no Extrato do Session Pass
O sistema SHALL registrar atomicamente em arquivo dedicado (`pass_statements.json`), limitado a um ring buffer de no máximo `200` eventos recentes por `pass_id`, os desfechos `consumed` (`200`), `endpoint_restricted` (`403`), `daily_limit_exceeded` (`429`) e `idempotent_replay` (`200`), armazenando apenas metadados operacionais Zero-PII.

#### Scenario: Registro de débito aprovado, bloqueios 403/429 e replay idempotente sem PII
- **GIVEN** um Session Pass ativo configurado com `allowed_endpoints: ["forex"]` e `daily_credit_limit: 1`
- **WHEN** o cliente realiza (a) consulta válida em `/api/v1/forex?base=BRL` com `Idempotency-Key`, (b) retentativa com mesma `Idempotency-Key`, (c) chamada proibida em `/api/v1/cnpj?cnpj=00000000000191` (`403`) e (d) nova chamada em `/api/v1/forex` excedendo o limite diário (`429`)
- **THEN** o extrato do passe SHALL conter respectivamente os 4 eventos com `outcome` igual a `consumed` (`credits_debited: 1`, `http_status: 200`), `idempotent_replay` (`credits_debited: 0`, `http_status: 200`), `endpoint_restricted` (`credits_debited: 0`, `http_status: 403`) e `daily_limit_exceeded` (`credits_debited: 0`, `http_status: 429`)
- **AND** nenhum lançamento SHALL armazenar parâmetros de query (`00000000000191`), segredos ou endereço IP.

#### Scenario: Ring buffer mantém estritamente no máximo 200 lançamentos recentes por passe
- **GIVEN** um Session Pass com mais de `200` chamadas registradas sequencialmente
- **WHEN** o histórico do passe é persistido pelo repositório
- **THEN** o armazenamento SHALL reter exatamente os `200` eventos mais recentes (`stored_entries === 200`), descartando os mais antigos sem degradar `session_passes.json`.

---

### Requirement: Endpoint Gratuito de Extrato por Session Pass (JSON e CSV Seguro)
O gateway SHALL expor gratuitamente a rota `GET /api/v1/pass/statement` autenticada pelo token `pass_g402_...` (via query `?token=`, cabeçalho `X-Payment` ou `Authorization: Bearer`), com filtros opcionais `?endpoint=` e `?outcome=` e exportação `?format=json` (padrão) ou `?format=csv` (UTF-8 com BOM e proteção anti CSV injection).

#### Scenario: Exportação JSON com filtros e rejeição segura de token inválido
- **GIVEN** uma requisição `GET /api/v1/pass/statement` com token válido e filtro `?endpoint=forex&outcome=consumed`
- **WHEN** o servidor processa a requisição
- **THEN** responde com HTTP `200`, zero débito de créditos, cabeçalho `X-Request-Id`, bloco `retention` (`max_entries_per_pass: 200`, `support_contact: "contato@grid402.online"`), `summary` consolidado e `entries` filtradas em ordem decrescente de timestamp
- **AND** caso o token seja inválido no formato HMAC ou inexistente, responde respectivamente com HTTP `400` (`InvalidPassTokenFormat`) ou HTTP `404` (`PassNotFound`).

#### Scenario: Exportação CSV segura com BOM UTF-8 e neutralização contra CSV Injection
- **GIVEN** uma requisição `GET /api/v1/pass/statement?token=pass_g402_...&format=csv` onde existe célula de valor iniciado por `=`, `+`, `-`, `@`, `\t` ou `\r` (ex.: em `request_id` personalizado)
- **WHEN** o servidor gera o CSV de download
- **THEN** responde com HTTP `200`, `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment`, prefixo BOM UTF-8 e qualquer célula iniciada por caractere de fórmula SHALL ser prefixada com apóstrofo simples (`'`).
