# Delta Spec: x402-exact-settlement

## ADDED Requirements

### Requirement: Respostas de Erro Recuperáveis x402 Estruturadas com Metadados de Pagamento
Toda resposta HTTP recuperável de desafio ou rejeição de pagamento (`HTTP 402`, `HTTP 400` por prova malformada/rede não suportada e `HTTP 429` de cota sandbox) em `/api/v1/*` MUST incluir no JSON os campos explícitos `required_raw_units` (inteiro em unidades mínimas USDC), `required_usdc` (string decimal com 4 casas) e o array `accepts` (x402 v2), acompanhados do cabeçalho HTTP `PAYMENT-REQUIRED` atualizado.

#### Scenario: Falha por valor insuficiente expõe required_raw_units, required_usdc e accepts completos
- **GIVEN** um cliente enviando autorização x402 com valor menor que o preço exigido pelo endpoint `/api/v1/ofac` (`20000` raw units / `0.0200` USDC)
- **WHEN** o gateway rejeita o pagamento
- **THEN** a resposta HTTP `402` SHALL conter no corpo JSON `required_raw_units: 20000`, `required_usdc: "0.0200"` e o array `accepts` completo
- **AND** SHALL emitir o cabeçalho `PAYMENT-REQUIRED` em base64 sincronizado com o valor exigido pela rota

#### Scenario: Erro 400 por rede não suportada mantém metadados de recuperação
- **GIVEN** um cliente enviando `X-Payment-Network` inválido em um endpoint pago
- **WHEN** o gateway retorna HTTP `400` (`UnsupportedNetwork`)
- **THEN** o corpo JSON SHALL incluir `required_raw_units`, `required_usdc` e `accepts[]` para que o agente corrija a rede sem nova chamada prévia

#### Scenario: Consistência EIP-712 entre accepts e requisitos de liquidação Base, Polygon e Arbitrum
- **GIVEN** o gateway configurado em produção (`GRID402_X402_NETWORK=base`)
- **WHEN** são comparados os requisitos gerados por `grid402_build_accepts()` e `grid402_exact_supported_networks()`
- **THEN** Base (`eip155:8453`), Polygon (`eip155:137`) e Arbitrum (`eip155:42161`) SHALL apresentar contratos USDC, `payTo` e metadados EIP-712 (`extra.name: "USD Coin"`, `extra.version: "2"`) idênticos

### Requirement: Política Anti-Abuso M2M Sem Divulgação de Cupom Gratuito em JSON e MCP
As respostas JSON M2M (`402`, `400`, `429`) das rotas `/api/v1/*` e `/mcp` MUST NOT anunciar, promover ou instruir resgate gratuito via `promo-claim` ou `MCPTRY50`, e o servidor `/mcp` MUST NOT expor ferramenta de criação/resgate de passe gratuito, restringindo a recomendação M2M a canais pagos (`x402` v2/on-chain, Session Pass Pix/USDC em `/pass` e RapidAPI Cartão USD).

#### Scenario: Resposta 402 e 429 da API não promove promo-claim nem cupom gratuito para bots
- **GIVEN** uma requisição M2M a `/api/v1/forex` sem pagamento (HTTP `402`) ou após esgotar a cota sandbox `X-Payment: demo` (HTTP `429`)
- **WHEN** o cliente recebe a resposta JSON
- **THEN** o JSON SHALL apresentar opções de pagamento via `x402` (`PAYMENT-SIGNATURE` / `tx_hash`), compra de Session Pass (`/pass`) e RapidAPI USD
- **AND** SHALL NOT conter referências a `/api/v1/pass/promo-claim`, `MCPTRY50` ou instruções de resgate gratuito programático

#### Scenario: Servidor MCP mantém catálogo fechado de ferramentas sem tool de trial grátis
- **GIVEN** uma chamada `tools/list` para `POST /mcp`
- **WHEN** a lista de ferramentas é retornada
- **THEN** nenhuma ferramenta SHALL oferecer emissão gratuita de Session Pass ou resgate promocional
- **AND** o total oficial de ferramentas do servidor MCP SHALL permanecer íntegro sem regressões
