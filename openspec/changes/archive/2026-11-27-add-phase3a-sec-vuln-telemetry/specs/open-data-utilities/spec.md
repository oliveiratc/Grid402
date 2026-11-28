## ADDED Requirements

### Requirement: Consulta Corporativa SEC EDGAR (`sec-company`)
O sistema SHALL disponibilizar o endpoint `GET /api/v1/sec-company` (`0.0100 USDC` / 2 créditos / `10000` raw) para consultar perfil corporativo e filings recentes da SEC EDGAR por `ticker` ou `cik`, enviando cabeçalho `User-Agent` compatível com a SEC, cache Zero-DB de `3600s` (e mapa ticker-CIK de `24h`), parâmetro opcional `include_facts=true` protegido por teto rígido de tamanho de resposta (`8 MB`) com degradação graciosa, validação pré-cobrança e assinatura `_meta` + `_oracle_proof`.

#### Scenario: Consulta paga por ticker retorna perfil corporativo, filings filtrados e prova de oráculo
- **GIVEN** um parâmetro válido `ticker=AAPL`, `forms=10-K,10-Q`, `limit=5` e um pagamento válido (`X-Payment` Session Pass ou x402)
- **WHEN** um agente envia `GET /api/v1/sec-company?ticker=AAPL&forms=10-K,10-Q&limit=5`
- **THEN** o sistema SHALL resolver o CIK de 10 dígitos (`0000320193`), retornar HTTP `200` com `cik`, `company_name`, `sic`, `filings` filtrados (máximo 5 com links diretos do EDGAR), `legal_disclaimer`, `_meta` e assinatura ECDSA `_oracle_proof`.

#### Scenario: include_facts=true protegido contra payloads XBRL acima de 8 MB
- **GIVEN** `include_facts=true` em uma empresa cujo arquivo `companyfacts` ultrapassa `8 MB` (ou excede o limite de download configurado)
- **WHEN** o serviço processa `GET /api/v1/sec-company?cik=0000320193&include_facts=true` com pagamento válido
- **THEN** o sistema SHALL abortar o download excedente sem erro fatal, retornar HTTP `200` com o perfil e os filings disponíveis e sinalizar `facts_status: "skipped_size_limit"`.

#### Scenario: Entrada inválida ou ticker inexistente não gera cobrança líquida
- **GIVEN** uma requisição sem `ticker` nem `cik`, ou um `ticker` inexistente (`ZZZZZZ`) com Session Pass válido
- **WHEN** um cliente consulta `/api/v1/sec-company`
- **THEN** parâmetro inválido ou ausente SHALL retornar HTTP `400 Bad Request` antes do desafio `402`, e ticker/CIK inexistente SHALL retornar HTTP `404` com estorno integral dos 2 créditos no Session Pass (ou sem liquidar `/settle` x402).

#### Scenario: Desafio HTTP 402 com tarifa exata de 0.0100 USDC
- **GIVEN** uma requisição válida para `/api/v1/sec-company?ticker=AAPL` sem cabeçalho de pagamento
- **WHEN** o porteiro x402 processa a chamada
- **THEN** o sistema SHALL retornar HTTP `402 Payment Required` anunciando `10000` raw units (`0.0100 USDC`) em `accepts` e bloco `preview` + `locked_fields`.

### Requirement: Auditoria de Vulnerabilidades de Pacotes e CVEs (`vuln-audit`)
O sistema SHALL prover `GET /api/v1/vuln-audit` (`0.0050 USDC` / 1 crédito / `5000` raw) consultando OSV.dev e CISA KEV (cache `6h` com fallback para última cópia válida), suportando consulta por pacote ou ID (`CVE`/`GHSA`), cache de `900s`, veredito determinístico (`CLEAN` a `CRITICAL_KEV`) e `_meta` + `_oracle_proof`.

#### Scenario: Auditoria de pacote detecta CVE com exploração ativa na CISA KEV
- **GIVEN** um pacote vulnerável consultado em modo pacote (`ecosystem=npm&package=lodash&version=4.17.20` ou equivalente) cujo CVE consta no catálogo CISA KEV
- **WHEN** um agente envia `GET /api/v1/vuln-audit` com pagamento válido
- **THEN** o sistema SHALL retornar HTTP `200` com `verdict: "CRITICAL_KEV"`, `kev_match_count >= 1`, `actively_exploited_kev: true`, `fixed_in`, `severity_summary`, `_meta` e `_oracle_proof`.

#### Scenario: Auditoria por ID direto CVE ou pacote limpo
- **GIVEN** uma requisição paga válida com `id=CVE-2021-44228` ou um pacote sem vulnerabilidades registradas
- **WHEN** `/api/v1/vuln-audit` processa a requisição
- **THEN** no caso de ID direto retorna `query_mode: "id"` com detalhes normalizados da vulnerabilidade, e no caso sem vulnerabilidades retorna `verdict: "CLEAN"` e `vulnerability_count: 0`.

#### Scenario: Indisponibilidade da CISA preserva cache anterior e falha do OSV estorna Session Pass
- **GIVEN** um `cisa_kev_cache.json` válido em disco quando o feed da CISA falha, ou falha total do OSV.dev sem entrada em cache
- **WHEN** um agente envia `GET /api/v1/vuln-audit` com Session Pass válido
- **THEN** se apenas a CISA falhar o sistema SHALL usar a última cópia válida de KEV com `kev_source_status: "stale_cache"`, e se o OSV falhar sem cache SHALL retornar HTTP `503` estornando integralmente 1 crédito ao Session Pass.

#### Scenario: Parâmetros inválidos e desafio HTTP 402 para vuln-audit
- **GIVEN** requisição sem `id` nem par `ecosystem`+`package` ou requisição válida sem `X-Payment`
- **WHEN** alcança o gateway em `/api/v1/vuln-audit`
- **THEN** parâmetros ausentes/inválidos SHALL retornar HTTP `400` pré-cobrança, e requisição válida sem pagamento SHALL retornar HTTP `402` anunciando `5000` raw units (`0.0050 USDC`).

### Requirement: Telemetria Multi-Chain em Tempo Real (`chain-telemetry`)
O sistema SHALL disponibilizar o endpoint `GET /api/v1/chain-telemetry` (`0.0050 USDC` / 1 crédito / `5000` raw) para as redes `base`, `ethereum`, `polygon`, `arbitrum` e `solana`, combinando RPCs públicos com fallback e TVL do DeFiLlama público em cache Zero-DB de `60s`, degradação graciosa quando uma das duas fontes oscilar, validação estrita de `chain` e assinatura `_meta` + `_oracle_proof`.

#### Scenario: Consulta paga na Base retorna bloco, gás, latência RPC e TVL DeFiLlama
- **GIVEN** uma requisição válida para `/api/v1/chain-telemetry?chain=base` com pagamento válido
- **WHEN** RPC público da Base e DeFiLlama respondem normalmente
- **THEN** o sistema SHALL retornar HTTP `200` com `chain: "base"`, `block_number`, `gas_gwei`, `tvl_usd`, `partial_data: false`, `sources_status`, `_meta` e `_oracle_proof`.

#### Scenario: Degradação graciosa quando DeFiLlama falha mas RPC responde (e erro 503 em falha total)
- **GIVEN** DeFiLlama indisponível enquanto o RPC público responde (ou falha simultânea de RPC e DeFiLlama sem cache)
- **WHEN** um agente consulta `/api/v1/chain-telemetry?chain=solana` com Session Pass válido
- **THEN** na falha parcial SHALL retornar HTTP `200` com dados de slot/prioridade e `partial_data: true`, e na falha total SHALL retornar HTTP `503` estornando integralmente 1 crédito ao Session Pass.

#### Scenario: Rede não suportada retorna HTTP 400 pré-cobrança e chamada sem pagamento retorna 402
- **GIVEN** `chain=invalidchain` ou uma requisição válida `chain=base` sem pagamento
- **WHEN** processada pelo gateway em `/api/v1/chain-telemetry`
- **THEN** `chain` inválida SHALL retornar HTTP `400` pré-cobrança, e chamada válida sem pagamento SHALL retornar HTTP `402` anunciando `5000` raw units (`0.0050 USDC`).

### Requirement: Suporte aos Serviços da Onda 3A no Lote Multi-Serviço (`POST /api/v1/batch`)
O executor e cotador de `POST /api/v1/batch` (`public_html/services/batch.php`) SHALL aceitar `sec-company` (`10000` raw), `vuln-audit` (`5000` raw) e `chain-telemetry` (`5000` raw) como sub-itens válidos, mantendo soma dinâmica exata, verificação prévia de `allowed_endpoints` e estorno automático por sub-item falho no Session Pass.

#### Scenario: Lote combinando serviços da Onda 3A calcula soma exata e estorna item falho no Session Pass
- **GIVEN** um lote `POST /api/v1/batch` contendo `sec-company` (`10000` raw) e `chain-telemetry` (`5000` raw)
- **WHEN** cotado sem pagamento e posteriormente executado com Session Pass onde `sec-company` falha com `503` e `chain-telemetry` obtém sucesso
- **THEN** o desafio HTTP `402` SHALL anunciar exatamente `15000` raw units (`0.0150 USDC`), e a execução com Session Pass SHALL estornar os 2 créditos de `sec-company`, cobrando líquido apenas 1 crédito de `chain-telemetry`.
