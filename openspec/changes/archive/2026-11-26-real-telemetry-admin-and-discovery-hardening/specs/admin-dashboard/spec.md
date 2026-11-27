# Spec Delta: admin-dashboard

## ADDED Requirements

### Requirement: Separação de Crawlers de Diretórios x402 e Endurecimento do Filtro de Page-Views
O sistema MUST classificar chamadas automatizadas de crawlers/scanners de diretórios x402 separadamente do tráfego real de API, ampliar a lista de User-Agents automatizados em page-views e impedir que `Accept: text/markdown` em `/` ou acessos com sessão administrativa autenticada contem como page-views humanos.

#### Scenario: Classificação de chamadas de crawlers de diretórios separada do tráfego real de API
- **GIVEN** um ambiente com chamadas reais de API e chamadas automatizadas de indexadores/scanners sem pagamento (`challenge_402` ou `/status`)
- **WHEN** `grid402_record_telemetry()` registra requisições com User-Agent de crawler/bot/scanner ou padrão de varredura de diretório
- **THEN** o sistema SHALL incrementar `total_crawler_requests` e `daily[$today]['crawler_requests']` mantendo `total_requests` retrocompatível
- **AND** `GET /admin?api=stats` e o cockpit `/admin` SHALL exibir `real_api_requests` (`total_requests - total_crawler_requests`) separado de `total_crawler_requests`.

#### Scenario: Filtro ampliado de User-Agents automatizados em page-views
- **GIVEN** requisições de páginas públicas usando clientes automatizados (`Go-http-client`, `undici`, `node-fetch`, `axios`, `httpx`, `aiohttp`, `Scrapy`, `Java/`, `okhttp`, `Guzzle`, `ChatGPT-User`, `Claude-Web`, `Meta-ExternalAgent`, `Playwright` ou `Puppeteer`)
- **WHEN** `grid402_record_page_visit()` processa a visita
- **THEN** `grid402_is_bot_user_agent()` SHALL identificar todos esses padrões como automatizados
- **AND** o sistema SHALL incrementar `total_bot_views` mantendo `total_page_views` inalterado.

#### Scenario: Exclusão de Accept text/markdown e sessão administrativa ativa de page-views humanos
- **GIVEN** uma requisição em `/` com cabeçalho `Accept: text/markdown` ou uma visita executada com cookie/sessão administrativa autenticada de `/admin`
- **WHEN** o gateway avalia o registro de visita de página
- **THEN** `total_page_views` SHALL NOT ser incrementado por negociações de markdown nem pela navegação do próprio operador autenticado.

### Requirement: Distinção entre Wallets Conectadas Reais e Perfis M2M Identificados
O sistema MUST contabilizar `wallets_connected` exclusivamente a partir de conexões reais de carteira Web3 confirmadas via `POST /api/v1/growth/wallet-connect` ou liquidação Web3 real com endereço válido, expondo separadamente `identified_profiles` para agentes/scanners M2M no `/admin`.

#### Scenario: Scanners 402, sandbox e falhas sem carteira não inflam Wallets Conectadas
- **GIVEN** eventos registrados em `growth.json` provenientes de desafios `402`, chamadas `demo` ou probes sem carteira EVM/SVM
- **WHEN** `grid402_growth_metrics()` e `/admin?api=stats` calculam o funil
- **THEN** os perfis correspondentes SHALL ser contabilizados em `identified_profiles`
- **AND** `wallets_connected` SHALL permanecer `0` até existir conexão real de carteira verificada.

#### Scenario: Registro autenticado de conexão de wallet com deduplicação e rate-limit
- **GIVEN** um visitante na Home ou em `/pass` após aprovação de `eth_requestAccounts` enviando `POST /api/v1/growth/wallet-connect` com `{"address": "0x..."}` válido
- **WHEN** o backend valida o formato `^0x[a-fA-F0-9]{40}$` e a cota por IP
- **THEN** o sistema SHALL marcar o perfil HMAC correspondente com `wallet_verified: true`, deduplicar chamadas repetidas da mesma carteira e responder HTTP `200`
- **AND** excessos acima da cota por janela SHALL retornar HTTP `429` sem inflar `wallets_connected`.

### Requirement: Ring Buffer Dedicado de Falhas Reais de Pagamento e Alertas do Mercado Pago
O sistema MUST persistir falhas reais de pagamento (x402, Session Pass e erros `502`/`503` em `/api/v1/pass/mp-*`) em `private_data/payment_failures.json` sob `flock(LOCK_EX)` com retenção de até 500 eventos Zero-PII, alimentando `/admin` de forma imune a rajadas `402` e disparando alertas automáticos do Mercado Pago.

#### Scenario: Preservação das falhas reais mesmo após rajada superior a 256 KB de desafios 402
- **GIVEN** uma falha real de pagamento gravada em `payment_failures.json` seguida por mais de 300 KB de eventos `402 PaymentRequired` gravados em `requests.jsonl`
- **WHEN** o operador consulta `GET /admin?api=stats` ou a tabela `recent_payment_failures`
- **THEN** a falha real SHALL continuar visível em `recent_payment_failures` com tokens/segredos redigidos e retenção de até 500 entradas
- **AND** a categoria original da falha SHALL permanecer contabilizada em `failure_categories`.

#### Scenario: Falha 502 ou 503 no checkout Mercado Pago registrada na telemetria e nas falhas reais
- **GIVEN** uma chamada a `POST /api/v1/pass/mp-pix` ou `POST /api/v1/pass/mp-checkout` que falha com HTTP `502` ou `503`
- **WHEN** o gateway finaliza a resposta de erro
- **THEN** o sistema SHALL incrementar `failure_categories['mercadopago_gateway_error']` em `metrics.json`
- **AND** SHALL gravar entrada sanitizada em `private_data/payment_failures.json` sem expor access tokens ou e-mails do pagador.

#### Scenario: Alertas automáticos de token Mercado Pago ausente em produção ou falhas recentes
- **GIVEN** um ambiente com `GRID402_MP_SANDBOX=false` e `GRID402_MP_ACCESS_TOKEN` vazio, ou com falhas recentes de `mercadopago_gateway_error`
- **WHEN** `grid402_admin_evaluate_alerts()` avalia os Pontos de Atenção
- **THEN** o motor SHALL emitir alertas automáticos claros no `/admin` identificando configuração ausente do Mercado Pago ou falhas recentes do gateway Pix/Checkout.

### Requirement: Clareza de Status HTTP 400, Resíduo Histórico e Receita Externa RapidAPI no Admin
O painel `/admin` SHALL distinguir HTTP `400` de prova inválida (`invalid_proof`) de `400` por parâmetro inválido (`bad_request`), rotular explicitamente resíduos históricos pré-24/11 sem código HTTP e informar quando chamadas `rapidapi` têm faturamento liquidado externamente no RapidAPI Hub.

#### Scenario: Separação visual entre HTTP 400 de prova inválida e HTTP 400 de parâmetro inválido
- **GIVEN** chamadas HTTP `400` registradas com categoria `invalid_proof` e chamadas HTTP `400` de validação prévia de parâmetros
- **WHEN** o cockpit `/admin` renderiza a distribuição de status HTTP
- **THEN** o painel SHALL exibir `400 Prova inválida` e `400 Parâmetro inválido` separadamente em vez de agrupar todo `400` sob o mesmo rótulo.

#### Scenario: Rotulagem explícita de resíduo histórico pré-24/11 e aviso de faturamento externo RapidAPI
- **GIVEN** métricas legadas onde `total_requests` supera a soma de `status_counts` e há chamadas pagas com origem `rapidapi`
- **WHEN** o painel `/admin` exibe a distribuição de status e receita por serviço
- **THEN** a diferença histórica SHALL ser rotulada explicitamente como resíduo histórico pré-24/11 (sem misturar com API recente)
- **AND** o painel SHALL indicar que a receita de chamadas `rapidapi` é apurada externamente no RapidAPI Hub (`US$ 0,00` local).

## MODIFIED Requirements

### Requirement: Origem do pagamento no faturamento e na telemetria
O sistema MUST registrar a origem de cada pagamento (`tx_hash`, `facilitator`, `session_pass`, `sandbox` ou `mcp_sandbox`), somando ao faturamento apenas pagamentos liquidados e mantendo bloqueios HTTP `429` fora de `total_sandbox_requests` e `payment_sources['sandbox']`.

#### Scenario: Pagamento facilitado contabilizado
- **WHEN** um pagamento é liquidado via facilitador
- **THEN** o evento é gravado com origem `facilitator`, valor real e hash de liquidação, e o faturamento total e do dia aumentam pelo valor pago.

#### Scenario: Falha de liquidação não gera faturamento
- **WHEN** o facilitador rejeita a autorização ou fica indisponível
- **THEN** o evento é gravado como falha com o motivo, sem alterar o faturamento.

#### Scenario: Bloqueios HTTP 429 não inflam Sandbox e auto-trial MCP usa origem mcp_sandbox
- **GIVEN** um cliente que consome o auto-trial diário do `/mcp` (HTTP `200`) e depois envia requisições `X-Payment: demo` além da cota recebendo HTTP `429`
- **WHEN** `grid402_record_telemetry()` registra esses eventos
- **THEN** o auto-trial HTTP `200` do `/mcp` SHALL incrementar `payment_sources['mcp_sandbox']` separado de `payment_sources['sandbox']`
- **AND** o bloqueio HTTP `429` (`SandboxRateLimitExceeded`) SHALL incrementar apenas `status_counts['429']` e `failure_categories['sandbox_rate_limited']`, sem somar em `total_sandbox_requests` nem em `payment_sources['sandbox']`.
