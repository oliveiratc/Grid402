## MODIFIED Requirements

### Requirement: Free tier summary aggregation
O sistema SHALL disponibilizar um resumo público consolidado de status operacional cobrindo todos os **25 serviços** do catálogo em `/status` e `/catalog` sem exigir autenticação ou pagamento, incluindo o histórico de disponibilidade dos últimos 30 dias (`uptime_30d`).

#### Scenario: Querying free utilities summary
- **WHEN** um cliente consulta `/api/v1/summary`, `/status` ou `/catalog` com `Accept: application/json` ou via navegador
- **THEN** o sistema retorna HTTP 200 exibindo o status operacional verificado (`"UP"`) para todos os 25 serviços do catálogo (incluindo `dossier`, `extract` e `email-verify`), dados agregados de cotação e o resumo de disponibilidade histórica de 30 dias.

## ADDED Requirements

### Requirement: Web Page to Clean Markdown Extractor API (`/api/v1/extract`)
O sistema SHALL prover o endpoint `GET /api/v1/extract?url=<url>` à tarifa de `0.0050 USDC` (1 crédito) para baixar páginas web públicas HTTP/HTTPS e extrair conteúdo limpo em Markdown, título, descrição, URL canônica, contagem de palavras e principais links absolutos, com proteção rigorosa contra SSRF e bloco `_oracle_proof` assinado.

#### Scenario: Extração bem-sucedida de página pública com pagamento ou sandbox
- **GIVEN** uma URL pública HTTP/HTTPS válida e cabeçalho `X-Payment` válido ou `demo`
- **WHEN** um agente envia `GET /api/v1/extract?url=https://example.com`
- **THEN** o sistema SHALL responder HTTP 200 com `title`, `markdown`, `word_count`, `links`, `_meta` e `_oracle_proof`

#### Scenario: Bloqueio SSRF de endereço interno ou metadata cloud sem cobrar
- **GIVEN** uma URL apontando para `http://127.0.0.1/`, `http://localhost/`, `http://169.254.169.254/latest/meta-data/` ou esquema `file://`
- **WHEN** a requisição chega em `/api/v1/extract`
- **THEN** o sistema SHALL rejeitar imediatamente com HTTP 400 (`BlockedTargetAddress` ou `InvalidUrlParameter`) antes de emitir cobrança ou realizar conexão de rede

#### Scenario: Desafio HTTP 402 sem pagamento
- **GIVEN** uma URL pública válida sem cabeçalho `X-Payment`
- **WHEN** o cliente requisita `/api/v1/extract?url=https://example.com`
- **THEN** o sistema SHALL responder HTTP 402 com `price_usdc: "0.0050"`, `accepts` multi-chain e bloco `preview` + `locked_fields`

### Requirement: B2B Email and Domain Deliverability Verification API (`/api/v1/email-verify`)
O sistema SHALL prover o endpoint `GET /api/v1/email-verify?email=<email>` à tarifa de `0.0050 USDC` (1 crédito) para auditar sintaxe RFC, registros DNS `MX`, políticas `SPF` e `DMARC`, classificação de provedor gratuito ou descartável (*disposable*) e endereço *role-based*, retornando `deliverability_score` (0–100) e `verdict` (`VALID`, `RISKY` ou `INVALID`), em estrita conformidade Zero-PII.

#### Scenario: Validação de e-mail corporativo com MX e SPF válidos
- **GIVEN** um e-mail sintaticamente válido cujo domínio possui registros `MX` ativos e não pertence à lista de domínios descartáveis
- **WHEN** um agente envia `GET /api/v1/email-verify?email=contato@grid402.online` com `X-Payment` válido
- **THEN** o sistema SHALL responder HTTP 200 com `verdict: "VALID"`, `has_mx: true`, `disposable: false`, `deliverability_score`, `email_hash_sha256`, `_meta` e `_oracle_proof`, sem persistir o endereço de e-mail completo em disco

#### Scenario: Detecção de domínio descartável (disposable)
- **GIVEN** um endereço pertencente a provedor temporário conhecido (ex.: `teste@mailinator.com`)
- **WHEN** o cliente consulta `/api/v1/email-verify` com pagamento válido
- **THEN** o sistema SHALL responder HTTP 200 com `disposable: true` e `verdict: "RISKY"` (ou `"INVALID"` se sem MX)

#### Scenario: E-mail malformado rejeitado antes de cobrar
- **GIVEN** um parâmetro `email` ausente ou sem formato válido (ex.: `nao-e-email`)
- **WHEN** o cliente consulta `/api/v1/email-verify`
- **THEN** o sistema SHALL retornar HTTP 400 Bad Request sem emitir desafio HTTP 402
