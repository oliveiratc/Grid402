## MODIFIED Requirements

### Requirement: Sandbox and testnet simulation mode
O servidor SHALL permitir a validação gratuita de requisições em Modo Sandbox mediante o cabeçalho `X-Payment: demo` ou transações confirmadas na testnet Base Sepolia (84532), impondo limite defensivo padrão de **3 requisições por dia (janela de 24 horas / 86400s) por endereço IP** (sobrescrevível por variáveis de ambiente `GRID402_SANDBOX_RATE_LIMIT` e `GRID402_SANDBOX_WINDOW_SECONDS`), com orientações de upgrade para Session Pass (Pix R$ 12) ou Multi-Chain USDC.

#### Scenario: Header based simulation mode
- **WHEN** uma requisição técnica ou de telemetria envia o cabeçalho `X-Payment: demo` ou `X-Payment: sandbox` dentro da cota diária do IP
- **THEN** o servidor valida a cota diária do IP (`3 req/dia`), entrega a resposta completa dos dados com status HTTP 200, marca o payload com `"sandbox": true`, inclui `sandbox_info` com `quota_remaining_today` / `quota_limit_per_day` e CTA de upgrade, e envia o cabeçalho `X-Payment-Mode: sandbox`

#### Scenario: Base Sepolia testnet transaction verification
- **WHEN** uma requisição técnica inclui `X-Payment: <tx_hash>` correspondente a uma transação confirmada na testnet Base Sepolia (Chain ID 84532)
- **THEN** o servidor valida o recibo contra o RPC da Sepolia, aplica a trava anti-replay e entrega o payload com HTTP 200 e `"sandbox": true`

#### Scenario: Sandbox rate limit enforcement
- **GIVEN** um endereço IP de cliente que já realizou 3 requisições de sandbox nas últimas 24 horas
- **WHEN** uma 4ª requisição com `X-Payment: demo` ou `sandbox` é recebida a partir do mesmo IP
- **THEN** o servidor recusa a entrega e responde com código HTTP 429 Too Many Requests, cabeçalho `Retry-After` e payload JSON contendo `upgrade_to_production` apontando para `https://grid402.online/pass`
