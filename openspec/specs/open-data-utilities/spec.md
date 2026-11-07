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
O sistema SHALL resolver endereços postais públicos brasileiros e código IBGE do município a partir de um código CEP de 8 dígitos.

#### Scenario: Valid CEP lookup
- **GIVEN** um código postal brasileiro de 8 dígitos válido
- **WHEN** uma requisição é enviada para `/api/v1/cep?code=<cep>`
- **THEN** o sistema retorna logradouro, bairro, cidade, UF e código oficial IBGE do município

#### Scenario: Invalid CEP lookup
- **GIVEN** um formato de CEP inválido
- **WHEN** uma requisição é enviada para `/api/v1/cep?code=invalido`
- **THEN** o sistema retorna HTTP 400 Bad Request

### Requirement: Free tier summary aggregation
O sistema SHALL disponibilizar um resumo público consolidado de status dos utilitários sem exigir autenticação ou pagamento.

#### Scenario: Querying free utilities summary
- **WHEN** um cliente consulta `/api/v1/summary` ou `/status` com `Accept: application/json`
- **THEN** o sistema retorna HTTP 200 com o status operacional dos 6 utilitários e dados agregados de cotação
