## ADDED Requirements

### Requirement: Auditoria de Segurança de Tokens Multi-Chain (token-safety)
O sistema SHALL disponibilizar o endpoint `GET /api/v1/token-safety` (`0.0150 USDC` / 3 créditos) para análise determinística de risco de contratos e tokens nas redes Base (`base`), Ethereum (`ethereum`), Polygon (`polygon`), Arbitrum (`arbitrum`) e Solana (`solana`), utilizando exclusivamente fontes públicas (GoPlus Security API e DexScreener API pública) com cache Zero-DB de 300 segundos, teaser gratuito no desafio HTTP 402 e prova criptográfica `_meta` + `_oracle_proof`.

#### Scenario: Consulta paga válida para token EVM na Base retorna score, flags e prova de oráculo
- **GIVEN** um endereço EVM válido em `address` (`0x...`) na rede `base` e um comprovante de pagamento válido (`X-Payment` Session Pass ou x402)
- **WHEN** um agente envia `GET /api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`
- **THEN** o sistema SHALL retornar HTTP `200` com `verdict` (`SAFE`, `CAUTION`, `HIGH_RISK` ou `CRITICAL`), `safety_score` (`0..100`), flags de risco (`is_honeypot`, `is_mintable`, `is_proxy`), taxas (`buy_tax`, `sell_tax`), liquidez DEX, `legal_disclaimer`, `_meta` e assinatura ECDSA `_oracle_proof`.

#### Scenario: Auto-detecção de token Solana Base58
- **GIVEN** um endereço Solana Base58 válido sem parâmetro `chain` explícito (ou com `chain=solana`)
- **WHEN** um agente envia `GET /api/v1/token-safety?address=EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`
- **THEN** o sistema SHALL resolver automaticamente `chain = "solana"`, consultar os endpoints públicos Solana da GoPlus/DexScreener e retornar HTTP `200` com o relatório normalizado.

#### Scenario: Validação estrita pré-cobrança rejeita endereço ou rede inválidos
- **GIVEN** um endereço malformado ou incompatível com a rede informada
- **WHEN** o cliente requisita `/api/v1/token-safety?chain=base&address=invalido`
- **THEN** o sistema SHALL retornar HTTP `400 Bad Request` imediatamente antes de emitir desafio `402` ou debitar créditos.

#### Scenario: Desafio HTTP 402 com teaser gratuito e preço exato de 0.0150 USDC
- **GIVEN** uma requisição válida para `/api/v1/token-safety` sem cabeçalho de pagamento
- **WHEN** o porteiro x402 processa a chamada
- **THEN** o sistema SHALL retornar HTTP `402 Payment Required` anunciando `15000` raw units (`0.0150 USDC`) em `accepts` e um bloco `preview` com rede detectada, endereço normalizado e `locked_fields`.

#### Scenario: Indisponibilidade total de fontes públicas retorna HTTP 503 pré-cobrança
- **GIVEN** falha simultânea de GoPlus Security API e DexScreener API sem entrada válida em cache
- **WHEN** um agente consulta `/api/v1/token-safety` com Session Pass válido
- **THEN** o sistema SHALL retornar HTTP `503 Service Unavailable` e SHALL estornar integralmente os 3 créditos ao Session Pass (ou abortar `/settle` x402).

### Requirement: Mercados de Previsão Normalizados (prediction-markets)
O sistema SHALL disponibilizar o endpoint `GET /api/v1/prediction-markets` (`0.0100 USDC` / 2 créditos) agregando Polymarket Gamma API pública e Kalshi Public Trade API v2 em schema normalizado de probabilidades (`0–100%`), volume 24h e liquidez com cache Zero-DB de 120 segundos, degradação graciosa quando uma das duas fontes falhar, teaser HTTP 402 e assinatura `_meta` + `_oracle_proof`.

#### Scenario: Consulta paga com filtro q agrega Polymarket e Kalshi
- **GIVEN** os feeds públicos de Polymarket e Kalshi disponíveis e pagamento válido
- **WHEN** um agente envia `GET /api/v1/prediction-markets?q=bitcoin&limit=5`
- **THEN** o sistema SHALL retornar HTTP `200` com até 5 mercados normalizados contendo `source`, `title`, `outcomes` com `probability_pct`, `volume_24h_usd`, `liquidity_usd`, `sources_status`, `_meta` e `_oracle_proof`.

#### Scenario: Degradação graciosa quando uma fonte falha e falha total retorna HTTP 503
- **GIVEN** Polymarket respondendo normalmente e Kalshi retornando erro de rede/HTTP 5xx
- **WHEN** um agente envia `GET /api/v1/prediction-markets?source=all`
- **THEN** o sistema SHALL retornar HTTP `200` com os mercados da Polymarket e indicar `sources_status.kalshi = "unavailable"`, enquanto a falha simultânea de ambas SHALL retornar HTTP `503` sem cobrança.

#### Scenario: Desafio HTTP 402 para prediction-markets
- **GIVEN** uma requisição sem cabeçalho de pagamento para `/api/v1/prediction-markets`
- **WHEN** o porteiro x402 avalia a chamada
- **THEN** o sistema SHALL retornar HTTP `402` com `10000` raw units (`0.0100 USDC`) em `accepts` e bloco `preview`.

### Requirement: Execução em Lote Multi-Serviço (POST /api/v1/batch)
O sistema SHALL disponibilizar o endpoint `POST /api/v1/batch` executando de 1 a 5 sub-requisições de serviços de dados pagos em uma única chamada HTTP, com preço calculado dinamicamente pela soma exata das tarifas oficiais dos itens do lote, bloqueio de serviços recursivos/administrativos, respeito a Spend Controls e `Idempotency-Key`, estorno automático por sub-item falho no Session Pass e assinatura consolidada `_meta` + `_oracle_proof`.

#### Scenario: Desafio HTTP 402 calcula dinamicamente a soma exata dos itens do lote
- **GIVEN** um corpo JSON válido em `POST /api/v1/batch` contendo um item `dns` (`5000` raw) e um item `token-safety` (`15000` raw) sem cabeçalho de pagamento
- **WHEN** o porteiro x402 calcula a cotação do lote
- **THEN** o sistema SHALL retornar HTTP `402` anunciando exatamente `20000` raw units (`0.0200 USDC` / 4 créditos) em `accepts` e detalhando o plano do lote em `batch_plan`.

#### Scenario: Execução com Session Pass estorna automaticamente créditos de sub-itens com falha
- **GIVEN** um Session Pass ativo com saldo suficiente e um lote `POST /api/v1/batch` contendo `dns` (`5000` raw, sucesso) e `token-safety` (`15000` raw, erro 503 de upstream)
- **WHEN** o gateway executa o lote com `X-Payment: pass_g402_...`
- **THEN** o sistema SHALL retornar HTTP `200` com `batch_summary.succeeded = 1`, `batch_summary.failed = 1`, `batch_summary.credits_charged = 1` e `batch_summary.credits_refunded = 3`, debitando do saldo líquido apenas 1 crédito correspondente ao item bem-sucedido.

#### Scenario: Bloqueio de lote inválido, recursivo ou com mais de 5 itens
- **GIVEN** um payload `POST /api/v1/batch` vazio, contendo `> 5` itens, serviço inexistente ou serviço bloqueado (`pass`, `pass-emitir`, `batch`, `verify`, `summary`)
- **WHEN** a requisição alcança o gateway
- **THEN** o sistema SHALL rejeitar com HTTP `400 Bad Request` antes de cobrar ou executar qualquer sub-item.

#### Scenario: Respeito a allowed_endpoints antes de debitar Session Pass
- **GIVEN** um Session Pass configurado com `allowed_endpoints: ["dns"]` e um lote contendo `dns` e `forex`
- **WHEN** o cliente envia `POST /api/v1/batch` com esse Session Pass
- **THEN** o sistema SHALL bloquear a execução antes de qualquer débito retornando HTTP `403 PassEndpointRestricted`.

#### Scenario: Limite de 2 itens em Sandbox e replay seguro com Idempotency-Key
- **GIVEN** uma chamada `POST /api/v1/batch` em modo `X-Payment: demo` com 3 itens, e outra chamada paga repetida com o mesmo `Idempotency-Key`
- **WHEN** ambas são processadas pelo gateway
- **THEN** a chamada `demo` com 3 itens SHALL retornar HTTP `400` sem executar, e a segunda chamada paga com o mesmo `Idempotency-Key` SHALL retornar `X-Idempotent-Replay: true` com zero débito adicional.

## MODIFIED Requirements

### Requirement: Free tier summary aggregation
O sistema SHALL disponibilizar um resumo público consolidado de status operacional cobrindo todos os **28 serviços** do catálogo em `/status` e `/catalog` sem exigir autenticação ou pagamento, incluindo o histórico de disponibilidade dos últimos 30 dias (`uptime_30d`) e metadados públicos de integridade do índice de sanções.

#### Scenario: Querying free utilities summary
- **WHEN** um cliente consulta `/api/v1/summary`, `/status` ou `/catalog` com `Accept: application/json` ou via navegador
- **THEN** o sistema retorna HTTP 200 exibindo o status operacional verificado (`"UP"`) para todos os 28 serviços do catálogo (incluindo `dossier`, `extract`, `email-verify`, `token-safety`, `prediction-markets` e `batch`), dados agregados de cotação e o resumo de disponibilidade histórica de 30 dias.
