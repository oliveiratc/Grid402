# x402-exact-settlement Specification

## Purpose
Permitir que clientes x402 padrão paguem pelos micro-serviços do Grid402 com autorização assinada (EIP-3009, esquema `exact`), com verificação e liquidação delegadas a um facilitador externo, sem exigir ETH de gás na carteira do oráculo.

## Requirements

### Requirement: Extração do payload x402 padrão
O sistema MUST aceitar o payload de pagamento x402 padrão enviado em base64 nos cabeçalhos `PAYMENT-SIGNATURE` (v2) ou `X-PAYMENT` (v1) e decodificá-lo antes da validação, suportando tanto autorizações EVM (EIP-3009 com `payload.signature` e `payload.authorization`) quanto transações Solana SVM (`payload.transaction` em base64 para a rede `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`).

#### Scenario: Payload v2 válido no cabeçalho PAYMENT-SIGNATURE
- **WHEN** um cliente envia `PAYMENT-SIGNATURE` com JSON base64 contendo `x402Version`, `accepted` em `eip155:8453`, `eip155:137` ou `eip155:42161` e `payload` de autorização EIP-3009
- **THEN** o sistema decodifica o payload e inicia a verificação contra os requisitos de pagamento da rede correspondente

#### Scenario: Payload v2 Solana SVM válido no cabeçalho PAYMENT-SIGNATURE
- **WHEN** um cliente envia `PAYMENT-SIGNATURE` com JSON base64 contendo `x402Version: 2`, `accepted.network` igual a `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` e `payload.transaction` em base64 válida
- **THEN** o sistema decodifica o payload Solana e inicia a verificação contra os requisitos de SPL USDC e carteira Solana oficial do Grid402

#### Scenario: Payload malformado
- **WHEN** o cabeçalho contém base64 inválido ou JSON sem os campos obrigatórios para o tipo de rede
- **THEN** o sistema responde HTTP 400 com mensagem clara indicando o campo ausente, sem acionar o facilitador e sem registrar faturamento

### Requirement: Validação local dos requisitos de pagamento
O sistema MUST validar localmente, antes de acionar o facilitador, que a autorização corresponde ao serviço e a uma das redes `exact` suportadas em produção (`eip155:8453` Base, `eip155:137` Polygon, `eip155:42161` Arbitrum ou `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` Solana, ou `eip155:84532` quando em modo `base-sepolia`), conferindo ativo USDC oficial da rede, destinatário (`payTo`) da rede, valor maior ou igual ao preço do serviço e janela de validade vigente (para EIP-3009).

#### Scenario: Valor menor que o preço do serviço
- **WHEN** a autorização assinada informa valor inferior ao preço do serviço
- **THEN** o sistema responde HTTP 402 com o desafio do serviço, sem acionar o facilitador

#### Scenario: Destinatário ou rede incorretos
- **WHEN** a autorização aponta para outra carteira, outro ativo ou uma rede não suportada pelo facilitador
- **THEN** o sistema responde HTTP 402 indicando o requisito não atendido, sem acionar o facilitador

#### Scenario: Autorização expirada
- **WHEN** o momento atual está fora do intervalo `validAfter` e `validBefore` da autorização EVM
- **THEN** o sistema responde HTTP 402 informando que a autorização expirou

### Requirement: Verificação e liquidação por facilitador externo
O sistema MUST verificar e liquidar a autorização via roteamento Dual-Facilitator (`Coinbase CDP` e `PayAI`) e só liberar o dado após liquidação confirmada. Na Base (`eip155:8453`) em modo `auto`, SHALL priorizar o Coinbase CDP quando configurado, com fallback para PayAI apenas na etapa pré-`/settle` (`/verify`) em falha de infraestrutura/quota; Polygon, Arbitrum e Solana SHALL rotear diretamente via PayAI.

#### Scenario: Pagamento liquidado com sucesso
- **WHEN** o facilitador confirma a verificação e a liquidação da autorização
- **THEN** o sistema entrega o payload do serviço com HTTP 200 e inclui o cabeçalho `PAYMENT-RESPONSE` com o recibo de liquidação, incluindo o hash da transação on-chain

#### Scenario: Facilitador rejeita a autorização
- **WHEN** o facilitador responde que a assinatura é inválida ou a liquidação falhou
- **THEN** o sistema responde HTTP 402 com o motivo informado, não entrega o dado e não registra faturamento

#### Scenario: Facilitador indisponível
- **WHEN** o facilitador não responde dentro do tempo limite ou retorna erro de servidor
- **THEN** o sistema responde HTTP 503 com `Retry-After`, não entrega o dado, não registra faturamento e mantém a autorização reutilizável

#### Scenario: Pagamento na Base liquidado via Coinbase CDP primário
- **GIVEN** o gateway em produção com `GRID402_CDP_API_KEY_ID` e `GRID402_CDP_API_KEY_SECRET` configurados e `GRID402_FACILITATOR_ROUTING=auto`
- **WHEN** um cliente envia payload x402 v2 válido na rede `eip155:8453` e o CDP confirma `/verify` e `/settle`
- **THEN** o sistema SHALL verificar e liquidar no Coinbase CDP Facilitator, entregar HTTP 200 com `PAYMENT-RESPONSE` e registrar `cdp` como facilitador utilizado

#### Scenario: Fallback automático na Base para PayAI quando CDP falha no /verify
- **GIVEN** o gateway na Base (`eip155:8453`) com credenciais CDP configuradas e o endpoint `/v2/x402/verify` do CDP retornando indisponibilidade (`503`, `429` ou timeout)
- **WHEN** um cliente envia payload x402 v2 válido para a Base
- **THEN** o sistema SHALL executar fallback automático para o facilitador PayAI na etapa `/verify`, concluir `/settle` via PayAI e entregar HTTP 200 sem perda da requisição

#### Scenario: Rejeição por mérito no /verify não aciona fallback
- **GIVEN** o facilitador primário respondendo HTTP 200 com `isValid: false` (assinatura ou autorização inválida do cliente)
- **WHEN** o gateway processa a verificação
- **THEN** o sistema SHALL liberar o nonce, responder HTTP 402 com o motivo da rejeição e SHALL NOT tentar o facilitador de fallback

#### Scenario: Roteamento de Polygon, Arbitrum e Solana diretamente para PayAI
- **GIVEN** credenciais CDP configuradas e `GRID402_SOLANA_X402_ENABLED=true`
- **WHEN** um cliente envia payload x402 v2 válido em `eip155:137`, `eip155:42161` ou `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`
- **THEN** o sistema SHALL rotear `/verify` e `/settle` diretamente para o facilitador PayAI sem acionar o CDP

### Requirement: Anti-replay por nonce de autorização
O sistema MUST impedir a reutilização de uma mesma autorização, identificada por pagador e `nonce`, de forma atômica sob concorrência.

#### Scenario: Reuso do mesmo nonce
- **WHEN** uma autorização com pagador e `nonce` já liquidados é enviada novamente
- **THEN** o sistema responde HTTP 403 indicando replay e não aciona o facilitador

#### Scenario: Requisições simultâneas com o mesmo nonce
- **WHEN** várias requisições paralelas enviam a mesma autorização
- **THEN** exatamente uma é processada e as demais recebem HTTP 403

### Requirement: Configuração e degradação segura
O sistema MUST permitir configurar os facilitadores por variáveis de ambiente (`GRID402_FACILITATOR_ROUTING`, URLs/paths de CDP e PayAI, credenciais CDP e modo `disabled`, `verify_only` ou `settle`), mantendo segredos fora de respostas e logs. Em erro ambíguo ou timeout durante `/settle`, SHALL manter o nonce em estado `pending`, retornar HTTP 503 com `Retry-After` e **MUST NOT** reenviar ao facilitador secundário.

#### Scenario: Facilitador desabilitado
- **WHEN** o modo é `disabled` e um cliente envia payload x402 padrão
- **THEN** o sistema responde HTTP 402 com instruções do fluxo por `tx_hash`, sem erro interno

#### Scenario: Segredos nunca expostos
- **WHEN** ocorre qualquer erro na comunicação com o facilitador
- **THEN** nenhuma credencial aparece na resposta HTTP, nos logs nem na telemetria

#### Scenario: Erro ambíguo ou timeout durante /settle bloqueia fallback para evitar dupla cobrança
- **GIVEN** uma autorização na Base aprovada no `/verify` pelo facilitador primário (`cdp`) cujo passo `/settle` sofre timeout ou retorna HTTP 503 sem confirmação
- **WHEN** o gateway trata a resposta do `/settle`
- **THEN** o sistema SHALL manter o nonce marcado como `pending` em `used_nonces.json`, responder HTTP 503 com `Retry-After` e SHALL NOT acionar o facilitador fallback (`payai`) no `/settle`

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

### Requirement: Conformidade Estrita da Extensão extensions.bazaar v2 para Coinbase CDP Bazaar
O sistema MUST emitir em todos os desafios HTTP 402 (`PAYMENT-REQUIRED`) metadados `resource` e `extensions.bazaar` compatíveis com o Coinbase CDP Bazaar: `resource.url` em HTTPS absoluto canônico, `description` técnica com no máximo `500` caracteres sem padrões de prompt-injection, e `extensions.bazaar.schema` totalmente *inlined* sem `$ref` ou `$id` externos.

#### Scenario: Desafio 402 atende todos os critérios estruturais do CDP Bazaar
- **GIVEN** qualquer dos 24 endpoints pagos do catálogo do Grid402
- **WHEN** um cliente solicita o endpoint sem cabeçalho de pagamento e recebe HTTP 402 com cabeçalho `PAYMENT-REQUIRED`
- **THEN** o payload decodificado SHALL conter `resource.url` começando com `https://grid402.online/`, `resource.description` com até 500 caracteres sem texto de prompt-injection, e `extensions.bazaar.schema` sem chaves `$ref` ou `$id` externas

### Requirement: Validação Automatizada CLI de Conformidade CDP Bazaar
O repositório MUST prover o script CLI `scripts/validate_cdp_bazaar.php` para auditar offline todos os endpoints pagos contra as restrições estruturais do CDP Bazaar v2 e, com `--live`, validar cada recurso contra `https://api.cdp.coinbase.com/platform/v2/x402/validate`.

#### Scenario: Validação offline CLI aprova todos os endpoints pagos do catálogo
- **GIVEN** o catálogo oficial em `public_html/config.php` e os geradores em `public_html/x402.php`
- **WHEN** um operador executa `php scripts/validate_cdp_bazaar.php --json`
- **THEN** o script SHALL sair com código `0` reportando aprovação estrutural de todos os endpoints pagos

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
