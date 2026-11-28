# open-data-utilities Specification

## Purpose
Prover um conjunto de 6 micro-serviços de utilidade pública e rede (Câmbio, Cripto, DNS, SSL, ASN, CEP/IBGE) com alta velocidade de entrega e zero tratamento de dados pessoais (PII).

## Requirements

### Requirement: Foreign exchange rates API
O sistema SHALL disponibilizar cotações em tempo real de moedas fiduciárias (USD, EUR, GBP para BRL) a partir de fontes de dados abertos.

#### Scenario: Successful forex rate query
- **WHEN** um agente requisita o endpoint de câmbio `/api/v1/forex`
- **THEN** o sistema retorna as cotações atuais de USD, EUR e GBP em relação ao BRL com timestamp e variação percentual diária

### Requirement: Cryptocurrency tickers API
O sistema SHALL fornecer preços de mercado atualizados de criptoativos essenciais (BTC, ETH, SOL, USDC e BASE).

#### Scenario: Successful crypto ticker query
- **WHEN** um agente requisita o endpoint de cripto `/api/v1/crypto`
- **THEN** o sistema retorna as cotações em USD e BRL de BTC, ETH, SOL e USDC com timestamp da captura

### Requirement: DNS records lookup API
O sistema SHALL resolver registros DNS essenciais (A, AAAA, MX, TXT, NS, CNAME) para domínios válidos fornecidos via parâmetro.

#### Scenario: Valid domain DNS resolution
- **GIVEN** um nome de domínio válido informado no parâmetro `domain`
- **WHEN** uma requisição é enviada para `/api/v1/dns`
- **THEN** o sistema retorna uma coleção estruturada contendo os registros DNS encontrados

#### Scenario: Invalid domain DNS resolution
- **GIVEN** uma string vazia ou domínio sintaticamente inválido
- **WHEN** uma requisição é enviada para `/api/v1/dns`
- **THEN** o sistema retorna HTTP 400 Bad Request com mensagem explicativa

### Requirement: SSL certificate inspector API
O sistema SHALL inspecionar o certificado TLS/SSL de um host remoto informando emissor, período de validade, Subject Alternative Names (SANs) e dias até expiração.

#### Scenario: Valid host SSL inspection
- **GIVEN** um host com certificado TLS válido na porta 443
- **WHEN** uma requisição é enviada para `/api/v1/ssl?host=<host>`
- **THEN** o sistema retorna o nome comum (CN), emissor, data de início e fim da validade e contagem regressiva de dias restantes

#### Scenario: Unreachable or non-SSL host inspection
- **GIVEN** um host inacessível ou sem suporte a TLS
- **WHEN** uma requisição é enviada para `/api/v1/ssl?host=<host>`
- **THEN** o sistema retorna HTTP 502 com detalhes do erro de handshake

### Requirement: ASN and IP intelligence API
O sistema SHALL resolver metadados de roteamento BGP de um endereço IPv4/IPv6, incluindo número de ASN, organização titular e país.

#### Scenario: Valid IP lookup
- **GIVEN** um endereço IP público válido
- **WHEN** uma requisição é enviada para `/api/v1/asn?ip=<ip>`
- **THEN** o sistema retorna o ASN correspondente, nome da organização e país de origem

### Requirement: Public address and IBGE resolution API
O sistema SHALL resolver endereços postais públicos brasileiros e código IBGE do município a partir de um código CEP de 8 dígitos, emitindo amostra gratuita da localidade e UF em caso de requisição sem pagamento.

#### Scenario: Valid CEP lookup
- **GIVEN** um código postal brasileiro de 8 dígitos válido e um cabeçalho `X-Payment` válido
- **WHEN** uma requisição é enviada para `/api/v1/cep?code=<cep>`
- **THEN** o sistema retorna logradouro, bairro, cidade, UF e código oficial IBGE do município com HTTP 200

#### Scenario: Invalid CEP lookup
- **GIVEN** um formato de CEP inválido
- **WHEN** uma requisição é enviada para `/api/v1/cep?code=invalido`
- **THEN** o sistema retorna HTTP 400 Bad Request sem emitir cobrança

#### Scenario: Cadastral CEP preview without payment
- **GIVEN** um código postal de 8 dígitos válido sem o cabeçalho `X-Payment`
- **WHEN** uma requisição é enviada para `/api/v1/cep?code=01310100`
- **THEN** o sistema retorna HTTP 402 com chave `preview` contendo `cep`, `cidade` e `uf`, e chave `locked_fields` listando `bairro`, `logradouro`, `complemento`, `ddd` e `ibge`

### Requirement: Free tier summary aggregation
O sistema SHALL disponibilizar um resumo público consolidado de status operacional cobrindo todos os **28 serviços** do catálogo em `/status` e `/catalog` sem exigir autenticação ou pagamento, incluindo o histórico de disponibilidade dos últimos 30 dias (`uptime_30d`) e metadados públicos de integridade do índice de sanções.

#### Scenario: Querying free utilities summary
- **WHEN** um cliente consulta `/api/v1/summary`, `/status` ou `/catalog` com `Accept: application/json` ou via navegador
- **THEN** o sistema retorna HTTP 200 exibindo o status operacional verificado (`"UP"`) para todos os 28 serviços do catálogo (incluindo `dossier`, `extract`, `email-verify`, `token-safety`, `prediction-markets` e `batch`), dados agregados de cotação e o resumo de disponibilidade histórica de 30 dias.

### Requirement: Consulta Cadastral de CNPJ da Pessoa Jurídica
O sistema SHALL disponibilizar a consulta de situação cadastral e atributos públicos da Pessoa Jurídica a partir de um número de CNPJ de 14 dígitos, fornecendo amostra parcial no desafio 402 para estímulo de conversão.

#### Scenario: Validação matemática prévia rejeita CNPJ inválido sem emitir cobrança
- **GIVEN** uma requisição enviada para `/api/v1/cnpj?cnpj=11111111111111` ou CNPJ com dígitos verificadores matematicamente incorretos
- **WHEN** o servidor processa a validação antes da cobrança
- **THEN** o sistema SHALL retornar HTTP 400 Bad Request imediatamente sem emitir desafio HTTP 402 ou debitar o pagador.

#### Scenario: Consulta sem pagamento retorna desafio x402 de 0.0020 USDC
- **GIVEN** um número de CNPJ matematicamente válido informado sem o cabeçalho `X-Payment`
- **WHEN** a requisição é enviada para `/api/v1/cnpj?cnpj=00000000000191`
- **THEN** o sistema SHALL retornar status HTTP 402 Payment Required com `price_usdc` fixado em 0.0020, incluindo a chave `preview` com Razão Social, Nome Fantasia, UF e Situação Cadastral, e a chave `locked_fields` com os atributos bloqueados.

#### Scenario: Consulta com pagamento x402 válido retorna dados normalizados de PJ
- **GIVEN** uma transação válida de 0.0020 USDC ou mais liquidada na Base para a carteira do oráculo informada no cabeçalho `X-Payment`
- **WHEN** a requisição é processada com sucesso
- **THEN** o sistema SHALL retornar HTTP 200 com os dados cadastrais da PJ (Razão Social, Situação, CNAE, Endereço e Capital Social) descartando intencionalmente quaisquer dados de pessoas físicas (QSA/Sócios).

#### Scenario: Resposta rápida a partir do cache local atômico
- **GIVEN** um CNPJ que já foi consultado e armazenado há menos de 7 dias
- **WHEN** uma nova consulta paga é realizada
- **THEN** o sistema SHALL responder em menos de 100ms a partir do arquivo JSON persistente local sem acionar o upstream externo.

#### Scenario: CNPJ não localizado na base pública da Receita
- **GIVEN** um CNPJ matematicamente válido que não existe no cadastro nacional
- **WHEN** o upstream público confirma a inexistência da inscrição
- **THEN** o sistema SHALL retornar HTTP 404 Not Found com mensagem estruturada antes de emitir cobrança.

### Requirement: Economic indicators API
O sistema SHALL fornecer taxas macroeconomicas oficiais do Brasil (Selic meta, Selic efetiva diaria, taxa CDI e IPCA acumulado) no endpoint `/api/v1/indicators`, emitindo amostra gratuita com Selic meta em caso de requisicao sem pagamento.

#### Scenario: Successful indicators query with payment proof
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo`
- **WHEN** uma requisicao e enviada para `/api/v1/indicators`
- **THEN** o sistema retorna status HTTP 200 OK com os valores de Selic meta, Selic diaria, CDI anual e IPCA acompanhados da data de referencia

#### Scenario: Indicators query without payment proof returns teaser
- **GIVEN** uma requisicao para `/api/v1/indicators` sem cabecalho `X-Payment`
- **WHEN** a requisicao e processada
- **THEN** o sistema retorna status HTTP 402 Payment Required com teaser contendo a Taxa Selic meta e indicacao de campos bloqueados

### Requirement: National and banking holidays API
O sistema SHALL fornecer o calendario oficial de feriados nacionais e bancarios no endpoint `/api/v1/holidays`, com suporte a parametro opcional `year`, emitindo amostra gratuita do proximo feriado em caso de requisicao sem pagamento.

#### Scenario: Successful holidays query for specific year
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo` e parametro `year=2026`
- **WHEN** uma requisicao e enviada para `/api/v1/holidays?year=2026`
- **THEN** o sistema retorna status HTTP 200 OK com a lista completa de feriados nacionais, bancarios e moveis daquele ano

#### Scenario: Holidays query without payment proof returns teaser
- **GIVEN** uma requisicao para `/api/v1/holidays` sem cabecalho `X-Payment`
- **WHEN** a requisicao e processada
- **THEN** o sistema retorna status HTTP 402 Payment Required com teaser contendo o proximo feriado a partir da data corrente

#### Scenario: Invalid year parameter
- **GIVEN** um parametro `year` contendo texto nao numerico ou ano fora do intervalo 1900-2100
- **WHEN** uma requisicao e enviada para `/api/v1/holidays`
- **THEN** o sistema retorna status HTTP 400 Bad Request com codigo `InvalidYearParameter`

### Requirement: ANBIMA financial workdays calculator API
O sistema SHALL fornecer calculo de dias uteis bancarios conforme o calendario ANBIMA no endpoint `/api/v1/workdays`, suportando contagem de intervalo entre duas datas, projecao de vencimento por adicao ou subtracao de dias e checagem pontual de dia util, emitindo amostra gratuita no desafio 402.

#### Scenario: Contagem de dias uteis entre duas datas com pagamento
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo` e parametros `start_date=2026-12-23` e `end_date=2026-12-28`
- **WHEN** uma requisicao e enviada para `/api/v1/workdays`
- **THEN** o sistema retorna status HTTP 200 OK com `workdays` calculado pelo padrao ANBIMA (inicio exclusivo e fim inclusivo), `calendar_days` corridos e a relacao de feriados bancarios no intervalo

#### Scenario: Projecao de vencimento adicionando dias uteis
- **GIVEN** um agente com comprovante `X-Payment` valido ou token `demo` e parametros `start_date=2026-11-06` e `days=5`
- **WHEN** uma requisicao e enviada para `/api/v1/workdays`
- **THEN** o sistema retorna status HTTP 200 OK com `target_date` exata deslocada sobre fins de semana e feriados bancarios

#### Scenario: Checagem pontual de dia util
- **GIVEN** um parametro `date=2026-12-25`
- **WHEN** uma requisicao e enviada para `/api/v1/workdays` com comprovante valido
- **THEN** o sistema retorna status HTTP 200 OK informando `is_workday: false`, `day_type: "holiday"` e `holiday_name: "Natal"` acompanhado da data do proximo dia util subsequente

#### Scenario: Requisicao sem comprovante de pagamento retorna teaser
- **GIVEN** uma requisicao para `/api/v1/workdays` com parametro `date` ou `start_date` sem cabecalho `X-Payment`
- **WHEN** a requisicao e processada
- **THEN** o sistema retorna status HTTP 402 Payment Required com teaser informando se a data base e dia util, o proximo feriado bancario e os campos de contagem bloqueados

#### Scenario: Parametros de data invalidos ou fora do limite de seguranca
- **GIVEN** uma data malformatada fora do padrao ISO 8601 ou projecao superior a 5.000 dias uteis
- **WHEN** uma requisicao e enviada para `/api/v1/workdays`
- **THEN** o sistema retorna status HTTP 400 Bad Request com codigo de erro estruturado

### Requirement: CGU Sanctions Check API (CEIS and CNEP)
O sistema SHALL disponibilizar consulta unificada de sanções administrativas no Cadastro de Empresas Inidôneas e Suspensas (CEIS) e Cadastro Nacional de Empresas Punidas (CNEP) via endpoint `/api/v1/sanctions`.

#### Scenario: Clean corporate entity without sanctions
- **GIVEN** um CNPJ válido sem registros no CEIS e CNEP
- **WHEN** uma requisição é enviada para `/api/v1/sanctions?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 200 contendo `has_sanctions: false`, lista vazia de sanções e objeto `_oracle_proof` assinado em secp256k1

#### Scenario: Sanctioned corporate entity with active penalties
- **GIVEN** um CNPJ com penalidade ativa cadastrada pela CGU
- **WHEN** uma requisição é enviada para `/api/v1/sanctions?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 200 contendo `has_sanctions: true`, órgão sancionador, motivo legal da penalidade e período de vigência

#### Scenario: Missing or malformed CNPJ for sanctions
- **GIVEN** um CNPJ ausente ou com quantidade incorreta de dígitos
- **WHEN** uma requisição é enviada para `/api/v1/sanctions`
- **THEN** o sistema retorna HTTP 400 Bad Request com descrição de erro

---

### Requirement: Simples Nacional and MEI Verification API
O sistema SHALL disponibilizar verificação oficial de opção pelo regime tributário do Simples Nacional e enquadramento como Microempreendedor Individual (MEI) via endpoint `/api/v1/simples`.

#### Scenario: Query Simples Nacional and MEI status
- **GIVEN** um CNPJ válido informado no parâmetro `cnpj`
- **WHEN** uma requisição é enviada para `/api/v1/simples?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 200 informando booleanos de opção pelo Simples e SIMEI, situação cadastral e assinatura `_oracle_proof`

#### Scenario: Unsettled x402 payment for Simples query
- **GIVEN** uma requisição sem comprovante de pagamento ou cabeçalho sandbox
- **WHEN** uma requisição é enviada para `/api/v1/simples?cnpj=<cnpj>`
- **THEN** o sistema retorna HTTP 402 com o desafio de pagamento de 0.001 USDC na rede Base

---

### Requirement: Consolidated Compliance and Risk Score 360 API
O sistema SHALL calcular um score consolidado de conformidade e integridade empresarial (0 a 100) via endpoint `/api/v1/compliance`, combinando dados cadastrais da Receita Federal, sanções administrativas da CGU e histórico societário.

#### Scenario: High integrity approved company
- **GIVEN** um CNPJ ativo, sem sanções e com mais de 2 anos de atividade regular
- **WHEN** uma requisição é enviada para `/api/v1/compliance?cnpj=<cnpj>` com autorização válida
- **THEN** o sistema retorna HTTP 200 com `score` entre 85 e 100, `status: "APROVADO"` e array de flags vazio

#### Scenario: High risk rejected company due to active sanctions
- **GIVEN** um CNPJ com sanção punitiva ou situação inativa
- **WHEN** uma requisição é enviada para `/api/v1/compliance?cnpj=<cnpj>` com autorização válida
- **THEN** o sistema retorna HTTP 200 com `score` inferior a 50, `status: "REJEITADO"` e apontamentos específicos no array de flags

### Requirement: Metadados de Proveniência e Frescor nas Respostas (`_meta`)
O sistema SHALL incluir em todas as respostas HTTP 200 dos serviços de dados um objeto estruturado `_meta` contendo `upstream_source` (fonte oficial de origem), `fetched_at` (timestamp ISO 8601 da coleta), `cache_ttl_sec` (tempo de vida configurado do cache) e `operator`, assinado conjuntamente pelo bloco `_oracle_proof`.

#### Scenario: Resposta paga inclui bloco _meta assinado
- **GIVEN** uma requisição paga ou em modo sandbox para qualquer serviço de dados (ex.: `/api/v1/cnpj` ou `/api/v1/forex`)
- **WHEN** o servidor entrega a resposta com HTTP 200
- **THEN** o JSON retornado SHALL conter a chave `_meta` com `upstream_source`, `fetched_at` e `cache_ttl_sec`, e a verificação em `/api/v1/verify` SHALL validar a integridade do payload completo incluindo `_meta`.

### Requirement: Consulta Global de Identidade Corporativa GLEIF LEI (REQ-GLOBAL-001)
O sistema SHALL prover o endpoint `/api/v1/lei` consultando a base pública CC0 da GLEIF (ISO 17442) por código `lei` de 20 caracteres ou busca textual `q`, retornando razão social, jurisdição, status cadastral, endereço legal e autoridade de registro.

#### Scenario: Consulta de LEI válido com pagamento ou passe
- GIVEN uma requisição GET para `/api/v1/lei?lei=5493006MHB84DD0ZWV18` com header `X-Payment` válido
- WHEN o serviço processa o código LEI
- THEN o sistema retorna HTTP 200 com `legal_name`, `jurisdiction`, `entity_status`, `registration_status` e `_oracle_proof` assinado

#### Scenario: Desafio 402 com amostra prévia no endpoint LEI
- GIVEN uma requisição GET para `/api/v1/lei?lei=5493006MHB84DD0ZWV18` sem header `X-Payment`
- WHEN o oráculo emite o desafio x402
- THEN retorna HTTP 402 com tarifa `0.0100 USDC`, bloco `preview` contendo o código LEI e jurisdição, e `locked_fields`

---

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

### Requirement: Carimbo de Tempo Criptográfico e Notarização M2M (REQ-GLOBAL-003)
O sistema SHALL prover o endpoint `/api/v1/notarize` que recebe um parâmetro `data` ou `hash` (SHA-256) e emite um certificado de existência no tempo assinado com a chave privada `secp256k1` do Oráculo Grid402.

#### Scenario: Notarização de payload ou hash por um agente
- GIVEN uma requisição para `/api/v1/notarize?data=contract-agreement-v1` com pagamento válido
- WHEN o oráculo processa o pedido
- THEN retorna HTTP 200 contendo `certificate_id`, `input_sha256`, `notarized_at_utc`, `unix_epoch` e `_oracle_proof` matematicamente verificável

---

### Requirement: Calendário Financeiro Mundial e Câmbio G10/BCE (REQ-GLOBAL-004)
O sistema SHALL prover o endpoint `/api/v1/global-markets` retornando o status de dia útil bancário e feriados oficiais dos mercados `US` (NYSE/Fed), `EU` (TARGET2/BCE), `UK` (LSE) e `BR` (B3/ANBIMA), além de taxas de referência cambial para mais de 25 moedas globais via dados abertos do Banco Central Europeu.

#### Scenario: Consulta de dia útil na NYSE/Fed e taxas globais
- GIVEN uma requisição para `/api/v1/global-markets?market=US&date=2026-07-03` com pagamento válido
- WHEN o serviço avalia o calendário oficial dos EUA (Independence Day observado) e cotações BCE
- THEN retorna HTTP 200 com `market: "US"`, `is_business_day: false`, `holiday_name`, `next_business_day` e o mapa `fx_rates`

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
