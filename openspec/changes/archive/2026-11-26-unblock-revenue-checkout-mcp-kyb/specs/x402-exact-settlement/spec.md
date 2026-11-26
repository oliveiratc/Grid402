# Spec Delta: x402-exact-settlement

## MODIFIED Requirements

### Requirement: Oferta Multi-Chain no desafio HTTP 402 (`accepts[]`)
Em modo de produção (`GRID402_X402_NETWORK=base`), `grid402_build_accepts()` MUST emitir no desafio HTTP 402 e no cabeçalho `Payment-Required` as 3 redes EVM ativas (`eip155:8453`, `eip155:137` e `eip155:42161`) por padrão, incluindo a quarta oferta Solana (`solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`) somente quando `GRID402_SOLANA_X402_ENABLED=true`.

#### Scenario: Desafio HTTP 402 em produção expõe 4 redes exact
- **GIVEN** o gateway operando em produção (`GRID402_X402_NETWORK=base`) com `GRID402_SOLANA_X402_ENABLED=true`
- **WHEN** um cliente requisita um endpoint pago sem cabeçalho de pagamento
- **THEN** o array `accepts` do corpo JSON e do cabeçalho `Payment-Required` SHALL conter `eip155:8453`, `eip155:137`, `eip155:42161` e `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` com contratos USDC oficiais e valores sincronizados

#### Scenario: Desafio HTTP 402 em produção com Solana desabilitada expõe 3 redes EVM
- **GIVEN** o gateway operando em produção (`GRID402_X402_NETWORK=base`) com `GRID402_SOLANA_X402_ENABLED=false`
- **WHEN** um cliente requisita um endpoint pago sem cabeçalho de pagamento
- **THEN** o array `accepts` do corpo JSON e do cabeçalho `Payment-Required` SHALL conter exatamente `eip155:8453`, `eip155:137` e `eip155:42161` e SHALL NOT conter `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`

#### Scenario: Modo base-sepolia preserva oferta única de testnet
- **GIVEN** o gateway configurado com `GRID402_X402_NETWORK=base-sepolia`
- **WHEN** um cliente requisita um endpoint pago sem cabeçalho de pagamento
- **THEN** o array `accepts` SHALL conter exclusivamente a oferta `eip155:84532` (Base Sepolia)

## ADDED Requirements

### Requirement: Ocultação Consistente de Solana em UI e Manifestos Quando Desabilitada
Enquanto `GRID402_SOLANA_X402_ENABLED` for `false`, as páginas públicas (`/catalog`, `/pass`, `#topup` da Home), respostas `402`/`429` da API e manifestos públicos em `public_html/` MUST NOT anunciar Solana como rede ativa, mantendo o código interno atrás da flag para reativação futura.

#### Scenario: Páginas públicas e manifestos não promovem Solana com flag desligada
- **GIVEN** `GRID402_SOLANA_X402_ENABLED=false`
- **WHEN** um cliente acessa `/catalog`, `/pass`, `/`, `/api/v1/forex` (402) ou os manifestos (`pricing.json`, `openapi.json`, `llms.txt`, `llms-full.txt`, `.well-known/*.json`)
- **THEN** nenhum seletor ativo de pagamento ou lista de redes habilitadas SHALL promover Solana como opção ativa

### Requirement: Conformidade Estrita x402 v1 (`resource` Absoluto)
Em requisições x402 v1, o gateway MUST sempre enviar `paymentRequirements.resource` como URL absoluta canônica (`https://grid402.online/api/v1/<service>`), mesmo quando `$_SERVER['REQUEST_URI']` for relativo.

#### Scenario: Requisição x402 v1 com REQUEST_URI relativo envia resource absoluto
- **GIVEN** uma chamada em `/api/v1/forex` com `$_SERVER['REQUEST_URI'] = '/api/v1/forex'` e payload x402 v1 válido
- **WHEN** o gateway monta `paymentRequirements` para enviar ao facilitador
- **THEN** `paymentRequirements.resource` SHALL começar com `https://grid402.online/api/v1/forex`

### Requirement: Normalização Estrita de `accepted` no x402 v2 para PayAI
Antes de enviar payloads x402 v2 ao facilitador `/verify` e `/settle`, o gateway MUST normalizar `paymentPayload.accepted` e `paymentRequirements` para coincidir em `deepEqual`: `amount` como string numérica, `network` em CAIP-2 canônico e chaves whitelisted de `extra` espelhadas.

#### Scenario: Payload x402 v2 com amount inteiro, network alias e extra eip3009 é normalizado antes do PayAI
- **GIVEN** um payload x402 v2 válido com `accepted.amount: 5000` (inteiro), `accepted.network: "base"` e `accepted.extra.assetTransferMethod: "eip3009"`
- **WHEN** o gateway prepara a requisição ao facilitador
- **THEN** `paymentPayload.accepted.amount` e `paymentRequirements.amount` SHALL ser `"5000"`, ambos `network` SHALL ser `"eip155:8453"`, e `accepted.extra` SHALL coincidir com `paymentRequirements.extra`

### Requirement: Cabeçalhos CORS Completos nas Rotas `/api/v1/*`
A função `grid402_emit_api_cors_headers()` em `public_html/index.php` MUST incluir `X-Payment-Signature`, `Payment` e `X-Grid402-Channel` em `Access-Control-Allow-Headers` para suportar preflight `OPTIONS` de clientes browser e SDKs x402 v2.

#### Scenario: Preflight OPTIONS em `/api/v1/forex` aceita aliases x402 e canal
- **GIVEN** um cliente browser enviando `OPTIONS /api/v1/forex` com `Access-Control-Request-Headers: X-Payment-Signature, Payment, X-Grid402-Channel`
- **WHEN** o gateway processa o preflight
- **THEN** responde HTTP 204 contendo `X-Payment-Signature`, `Payment` e `X-Grid402-Channel` em `Access-Control-Allow-Headers`
