# open-data-utilities Delta Specification

## MODIFIED Requirements

### Requirement: Inspeção Internacional de Sanções OFAC e AML Web3 (REQ-GLOBAL-002)
O sistema SHALL prover `/api/v1/ofac` (`0.0200 USDC` / 4 créditos) verificando endereços cripto multi-ativo (EVM, Solana, BTC, Tron) e entidades/aliases contra o índice Zero-DB JSON particionado sincronizado dos 4 blocos oficiais (**OFAC SDN**, **ONU**, **UE FSFi**, **UK OFSI**), mantendo Base RPC para EVM. Sem índice em chamada paga, MUST retornar HTTP `503` pré-cobrança; índice `> 72h` SHALL sinalizar `STALE`; modo `demo` sem índice SHALL usar `demo_subset` rotulado.

#### Scenario: Verificação de endereço sancionado conhecido
- GIVEN uma requisição para `/api/v1/ofac?address=0x8589427373D6D84E98730D7795D8f6f8731FDA16` com pagamento válido
- WHEN o serviço analisa o endereço
- THEN retorna HTTP 200 com `sanctioned: true`, `risk_level: "CRITICAL"` e a referência da lista OFAC SDN

#### Scenario: Verificação de carteira limpa na Base Mainnet
- GIVEN uma requisição para `/api/v1/ofac?address=0xa31c31069c43ac1c0075a93c325b0dcd7744356c` com pagamento válido
- WHEN o serviço analisa o endereço e consulta o RPC da Base
- THEN retorna HTTP 200 com `sanctioned: false`, `risk_level: "LOW"` e o objeto `onchain_profile` com tipo de conta e saldos

#### Scenario: Verificação de endereço multi-ativo (EVM, Solana ou BTC) presente no índice oficial
- **GIVEN** o índice oficial particionado sincronizado contendo endereços EVM, Solana e Bitcoin sancionados pelo OFAC SDN
- **WHEN** um cliente pago consulta `/api/v1/ofac?target=<endereco_solana_ou_btc_ou_evm>`
- **THEN** o sistema SHALL retornar HTTP 200 com `sanctioned: true`, `risk_level: "CRITICAL"`, `compliance_decision: "BLOCK_TRANSACTION"`, `matched_lists` apontando a fonte oficial, `dataset_metadata.freshness_status: "FRESH"`, `dataset_sha256` e `legal_disclaimer`

#### Scenario: Fail-closed pré-cobrança quando índice oficial não existe em chamada paga
- **GIVEN** o diretório `sanctions_index/` ausente ou sem `manifest.json` válido
- **WHEN** um cliente envia requisição paga (x402 `PAYMENT-SIGNATURE` ou `Session Pass`) para `/api/v1/ofac`
- **THEN** o sistema SHALL responder HTTP 503 (`SanctionsIndexUnavailable`) e SHALL NOT liquidar `/settle` x402 nem debitar créditos líquidos do Session Pass

#### Scenario: Aviso STALE quando índice ultrapassa 72 horas sem sincronização
- **GIVEN** um índice oficial válido cujo `synced_at` tem idade superior a 72 horas
- **WHEN** um cliente consulta `/api/v1/ofac` com pagamento válido
- **THEN** o sistema SHALL retornar HTTP 200 com `dataset_metadata.freshness_status: "STALE"` e `dataset_metadata.stale_dataset_warning: true`

#### Scenario: Modo sandbox demo sem índice local usa subset rotulado
- **GIVEN** o índice oficial ausente localmente e requisição com cabeçalho `X-Payment: demo`
- **WHEN** o cliente consulta `/api/v1/ofac`
- **THEN** o sistema SHALL responder HTTP 200 utilizando o subset de demonstração rotulado explicitamente com `dataset_metadata.dataset_mode: "demo_subset"` e `dataset_metadata.freshness_status: "DEMO_SUBSET"`

### Requirement: Web Page to Clean Markdown Extractor API (`/api/v1/extract`)
O sistema SHALL prover `GET /api/v1/extract?url=<url>` (`0.0050 USDC` / 1 crédito) em modo **100% transiente** (sem gravar texto/Markdown de terceiros em disco), com proteção SSRF (página e `robots.txt`), respeito a `robots.txt` (RFC 9309), bloqueio de diretivas anti-AI (`X-Robots-Tag`, `Content-Signal`, `<meta>`), bloqueio de HTTP `401`/`402`/`403`/`407` e sinais de paywall, além de `_oracle_proof`.

#### Scenario: Extração bem-sucedida de página pública com pagamento ou sandbox
- **GIVEN** uma URL pública HTTP/HTTPS válida e cabeçalho `X-Payment` válido ou `demo`
- **WHEN** um agente envia `GET /api/v1/extract?url=https://example.com`
- **THEN** o sistema SHALL responder HTTP 200 com `title`, `markdown`, `word_count`, `links`, `_meta` e `_oracle_proof`

#### Scenario: Extração permitida opera 100% em modo transiente sem persistir conteúdo
- **GIVEN** uma URL pública HTTP/HTTPS permitida pelo `robots.txt`, sem cabeçalhos/meta tags restritivas e sem paywall, e cabeçalho `X-Payment` válido ou `demo`
- **WHEN** um agente envia `GET /api/v1/extract?url=https://example.com`
- **THEN** o sistema SHALL responder HTTP 200 com `transient_only: true`, `cached: false` e `legal_disclaimer`
- **AND** nenhum arquivo contendo o texto ou Markdown extraído SHALL ser gravado em `GRID402_PRIVATE_DIR`

#### Scenario: Bloqueio por robots.txt impede extração e cobrança
- **GIVEN** uma URL cujo host publica `robots.txt` com `Disallow` aplicável a `Grid402-Extract-Bot` ou `*`
- **WHEN** um agente requisita `/api/v1/extract?url=<url>`
- **THEN** o sistema SHALL retornar HTTP 403 (`RobotsDisallowed`) antes de baixar o conteúdo da página e SHALL abortar `/settle` x402 ou estornar Session Pass

#### Scenario: Bloqueio por cabeçalhos X-Robots-Tag, Content-Signal ou meta tags anti-AI
- **GIVEN** uma página pública retornando `X-Robots-Tag: noai` (ou `noindex`/`none`), `Content-Signal: ai-input=no` (ou `ai-train=no`) ou `<meta name="robots" content="noai">`
- **WHEN** um agente requisita `/api/v1/extract`
- **THEN** o sistema SHALL retornar HTTP 403 (`DirectiveDisallowed`), não entregar conteúdo e não liquidar pagamento

#### Scenario: Bloqueio por HTTP 401/403 ou paywall estrutural
- **GIVEN** uma origem respondendo HTTP `401`/`402`/`403`/`407` ou HTML contendo JSON-LD `"isAccessibleForFree": false` ou `<meta property="article:content_tier" content="locked">`
- **WHEN** um agente requisita `/api/v1/extract`
- **THEN** o sistema SHALL retornar HTTP 403 (`OriginAccessDenied` ou `PaywallDetected`), não entregar conteúdo e não liquidar pagamento

#### Scenario: Bloqueio SSRF de endereço interno ou metadata cloud sem cobrar
- **GIVEN** uma URL apontando para `http://127.0.0.1/`, `http://localhost/`, `http://169.254.169.254/latest/meta-data/` ou esquema `file://`
- **WHEN** a requisição chega em `/api/v1/extract`
- **THEN** o sistema SHALL rejeitar imediatamente com HTTP 400 (`BlockedTargetAddress` ou `InvalidUrlParameter`) antes de emitir cobrança ou realizar conexão de rede

#### Scenario: Desafio HTTP 402 sem pagamento
- **GIVEN** uma URL pública válida sem cabeçalho `X-Payment`
- **WHEN** o cliente requisita `/api/v1/extract?url=https://example.com`
- **THEN** o sistema SHALL responder HTTP 402 com `price_usdc: "0.0050"`, `accepts` multi-chain e bloco `preview` + `locked_fields`

## ADDED Requirements

### Requirement: Sincronizador Streaming Zero-DB dos 4 Blocos Oficiais de Sanções
O sistema MUST prover `scripts/sync_sanctions_index.php` e `public_html/services/sanctions_index.php` para processar em streaming (`XMLReader`/leitura incremental, sem `sqlite3`) os feeds de **OFAC SDN**, **ONU**, **EU FSFi** e **UK OFSI**, gravando índice JSON particionado em `GRID402_PRIVATE_DIR/sanctions_index/` por troca atômica via *staging*.

#### Scenario: Sincronização completa offline via --from-dir cria índice particionado com dataset_sha256
- **GIVEN** um diretório de fixtures contendo os 4 feeds oficiais (OFAC, ONU, UE e UK OFSI)
- **WHEN** o operador executa `php scripts/sync_sanctions_index.php --from-dir=<fixtures>`
- **THEN** o script SHALL sair com código `0` e gravar `manifest.json` e os shards `addr_*.json` e `ent_*.json` em `GRID402_PRIVATE_DIR/sanctions_index/` contendo contagens válidas dos 4 blocos e `dataset_sha256`

#### Scenario: Falha de fonte oficial preserva índice anterior íntegro
- **GIVEN** um índice oficial válido já gravado e uma nova execução do sincronizador onde um dos feeds retorna erro ou XML malformado
- **WHEN** `scripts/sync_sanctions_index.php` falha durante download ou parse
- **THEN** o script SHALL sair com código diferente de `0`, remover o diretório staging e manter o índice anterior intacto sem corrupção

### Requirement: Propagação Fail-Closed e Triagem Multi-Ativo em /api/v1/kyb-audit
O endpoint `/api/v1/kyb-audit` (`public_html/services/kyb_audit.php`) MUST suportar endereços cripto multi-ativo (além de `0x...` e razão social/CNPJ/LEI) na checagem de sanções e MUST propagar indisponibilidade do índice oficial (`503 SanctionsIndexUnavailable`) pré-cobrança em produção, nunca mascarando falha de índice como resultado limpo (`CLEAR`).

#### Scenario: /api/v1/kyb-audit sem índice oficial em chamada paga falha fechado sem cobrar
- **GIVEN** o índice oficial de sanções ausente e uma requisição paga para `/api/v1/kyb-audit?cnpj=00000000000191`
- **WHEN** o gateway processa a requisição
- **THEN** o sistema SHALL retornar HTTP 503 (`SanctionsIndexUnavailable`) antes de liquidar pagamento ou consumir créditos líquidos do Session Pass
