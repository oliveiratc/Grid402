# x402-payment-oracle Specification

## Purpose
Disponibilizar endpoint de telemetria pública e paga via protocolo HTTP x402 liquidado em USDC na rede Base, com interface adaptativa para navegadores e agentes M2M.

## Requirements

### Requirement: Adaptive content negotiation
O servidor SHALL inspecionar o cabeçalho HTTP `Accept` para alternar entre uma interface visual para humanos e respostas estruturadas para agentes e máquinas.

#### Scenario: Browser visit
- **WHEN** uma requisição é recebida com cabeçalho `Accept` contendo `text/html`
- **THEN** o servidor renderiza a Landing Page dark-mode em HTML/CSS puro com o status atual dos serviços e documentação da API

#### Scenario: Machine API request
- **WHEN** uma requisição é recebida com cabeçalho `Accept: application/json` ou sem especificação de HTML
- **THEN** o servidor responde no formato JSON com dados de telemetria ou desafio de pagamento x402

### Requirement: Free tier data delivery
O servidor SHALL disponibilizar o resumo gratuito de telemetria sem exigir autenticação ou pagamento.

#### Scenario: Requesting free summary
- **WHEN** um cliente consulta o endpoint raiz sem solicitar dados detalhados
- **THEN** o servidor retorna HTTP 200 contendo o conteúdo de `summary_free.json` com status geral e latência agregada

### Requirement: Payment required challenge
O servidor SHALL emitir um desafio HTTP 402 Payment Required quando dados detalhados ou endpoints técnicos de utilitários forem solicitados sem comprovante de liquidação on-chain, incluindo opcionalmente amostra parcial (preview) e lista de campos bloqueados (locked_fields) para enriquecimento da conversão.

#### Scenario: Requesting detailed telemetry without payment
- **WHEN** um cliente solicita telemetria detalhada (`?detail=true` ou rota de telemetria) sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalho `X-Authenticate: x402` e corpo JSON com os parâmetros da rede Base, contrato USDC, valor de US$ 0.0005 e endereço da carteira

#### Scenario: Requesting utility data without payment
- **WHEN** um cliente solicita qualquer endpoint técnico aprofundado (`/api/v1/forex`, `/api/v1/crypto`, `/api/v1/dns`, `/api/v1/ssl`, `/api/v1/asn`) sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalho `X-Authenticate: x402`, cabeçalho `X-Accept-Payment` e corpo JSON com os parâmetros da rede Base, contrato USDC, valor de US$ 0.0005 e carteira destinatária

#### Scenario: Requesting cadastral data with teaser preview without payment
- **WHEN** um cliente solicita dados cadastrais (`/api/v1/cnpj` ou `/api/v1/cep`) informando identificador válido porém sem o cabeçalho `X-Payment`
- **THEN** o servidor responde com código HTTP 402, cabeçalhos `X-Authenticate` e `X-Accept-Payment`, incluindo no JSON as chaves `preview` com os dados abertos e `locked_fields` com a relação de atributos que serão liberados no HTTP 200

### Requirement: On-chain payment verification
O servidor SHALL validar o comprovante de pagamento recebido no cabeçalho `X-Payment` diretamente contra o RPC da rede Base antes de liberar a resposta do utilitário ou telemetria requisitada.

#### Scenario: Valid payment proof
- **WHEN** uma requisição inclui `X-Payment: <tx_hash>` com transação confirmada de valor >= 0.0005 USDC direcionada à carteira do oráculo
- **THEN** o servidor valida o recibo on-chain, registra o hash como utilizado e entrega o payload de dados técnicos com HTTP 200

#### Scenario: Invalid or insufficient payment
- **WHEN** uma requisição inclui `X-Payment: <tx_hash>` com transação inexistente, falha, valor menor que o exigido ou destinatário incorreto
- **THEN** o servidor recusa a entrega e responde com HTTP 402 Payment Required

#### Scenario: Valid payment for utility
- **WHEN** uma requisição técnica inclui `X-Payment: <tx_hash>` com transação confirmada de valor >= 0.0005 USDC direcionada à carteira do oráculo na Base
- **THEN** o servidor valida o recibo on-chain, grava o hash e entrega o payload técnico do utilitário com HTTP 200 OK

### Requirement: Replay attack prevention
O servidor SHALL impedir atomicamente a reutilização de um mesmo hash de transação para múltiplas requisições de dados pagos mediante bloqueio de arquivo com trava exclusiva (`flock`).

#### Scenario: Reused transaction hash
- **WHEN** uma requisição envia um `X-Payment: <tx_hash>` que já consta no arquivo `used_txs.json`
- **THEN** o servidor recusa a requisição retornando HTTP 403 Forbidden com indicação de replay attack detectado

#### Scenario: Concurrent payment race condition
- **WHEN** múltiplas requisições paralelas enviam simultaneamente o mesmo `tx_hash` no mesmo instante
- **THEN** a trava atômica `flock` garante que estritamente uma única requisição seja aprovada e todas as demais sejam rejeitadas com HTTP 403

### Requirement: Private storage isolation
O sistema SHALL bloquear completamente qualquer tentativa de acesso HTTP direto aos arquivos dentro do diretório de dados privados.

#### Scenario: Direct HTTP access attempt
- **WHEN** um cliente tenta acessar diretamente pela URL qualquer arquivo em `/private_data/`
- **THEN** o servidor web LiteSpeed/Apache responde imediatamente com HTTP 403 Forbidden via `.htaccess`
