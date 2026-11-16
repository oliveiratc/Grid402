## ADDED Requirements

### Requirement: Rastreabilidade via X-Request-Id e Log Estruturado JSONL Zero-PII
O gateway HTTP do Grid402 SHALL emitir o cabeçalho de correlação `X-Request-Id` em todas as respostas da API e gravar eventos estruturados em formato JSON Lines (`private_data/requests.jsonl`) com proteção de concorrência (`flock`) e rotação automática ao atingir `10 MB`, sem registrar dados pessoais identificáveis (Zero-PII) nem segredos ou tokens completos de autenticação.

#### Scenario: Emissao de X-Request-Id e registro JSONL sanitizado
- **GIVEN** uma requisição recebida em `/api/v1/forex` ou `/status` contendo cabeçalho opcional `X-Payment: pass_g402_secret_token_example`
- **WHEN** o gateway conclui o processamento HTTP
- **THEN** a resposta HTTP SHALL incluir o cabeçalho `X-Request-Id`
- **AND** uma nova linha JSONL válida SHALL ser anexada ao arquivo de logs contendo `ts`, `request_id`, `service`, `status`, `latency_ms`, `payment_source` e `ip_hmac`
- **AND** a linha gravada SHALL NOT conter o token `pass_g402_...` completo nem o endereço IP em texto claro.

#### Scenario: Rotacao automatica do arquivo de log ao atingir limite de tamanho
- **GIVEN** um arquivo de log JSONL ativo com tamanho igual ou superior ao limite configurado (padrão `10 MB`)
- **WHEN** um novo evento de requisição é registrado pelo logger
- **THEN** o sistema SHALL rotacionar atomicamente o arquivo atual para o sufixo `.1` e iniciar um novo `requests.jsonl` limpo sem perda do evento corrente.

### Requirement: Comprovacao Automatizada de Concorrencia Paralela e Carga
O repositório SHALL incluir uma suíte automatizada de testes de concorrência real multi-processo e carga (`tests/test_concurrency_and_load.php`) validando atomicidade contra double-spend no último crédito do Session Pass, replay concorrente de `tx_hash`/`payer:nonce` e latências `p50`/`p95`/`p99`.

#### Scenario: Disputa paralela pelo ultimo credito de um Session Pass
- **GIVEN** um Session Pass válido com exatamente 1 crédito restante
- **WHEN** múltiplos processos concorrentes tentam consumir 1 crédito simultaneamente sobre o mesmo arquivo de passes
- **THEN** estritamente 1 processo SHALL obter autorização (`valid: true`) e todos os demais SHALL ser rejeitados (`PassExhausted`), mantendo `credits_remaining === 0`.

#### Scenario: Replay concorrente de tx_hash e payer:nonce
- **GIVEN** um `tx_hash` inédito e um par `payer:nonce` inédito
- **WHEN** múltiplos processos concorrentes tentam adquirir a trava simultaneamente para cada identificador
- **THEN** estritamente 1 processo SHALL obter o lock de cada identificador e todos os demais SHALL falhar imediatamente.
