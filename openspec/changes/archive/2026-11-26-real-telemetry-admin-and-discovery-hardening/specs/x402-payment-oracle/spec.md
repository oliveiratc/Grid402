# Spec Delta: x402-payment-oracle

## ADDED Requirements

### Requirement: Teto Diário Padrão para Dossiê KYB via RapidAPI
A configuração padrão `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` em `public_html/config.php` MUST ser `15` consultas diárias por assinante RapidAPI no endpoint `/api/v1/dossier` (sobrescrevível via variável de ambiente), protegendo a margem unitária com HTTP `429` a partir do excesso.

#### Scenario: Limite padrão de 15 dossiês diários por assinante RapidAPI bloqueia a 16ª chamada com HTTP 429
- **GIVEN** um ambiente sem override explícito de `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` (assumindo padrão `15`) e um assinante autenticado via `X-RapidAPI-Proxy-Secret`
- **WHEN** o mesmo assinante realiza `15` consultas bem-sucedidas ao `/api/v1/dossier` no dia UTC e envia a `16ª` chamada
- **THEN** as `15` primeiras SHALL retornar HTTP `200` e a `16ª` SHALL ser bloqueada com HTTP `429` (`RapidApiEndpointCapExceeded`) e orientações de upgrade para `/pass` e plano MEGA.

### Requirement: Remoção de Duplicidade Legada teaser_data nos Desafios HTTP 402 Padrão
Em `public_html/x402.php`, desafios HTTP `402` com amostra parcial MUST expor `preview` e `locked_fields` sem duplicar a chave legada `teaser_data` nos micro-serviços padrão, preservando `teaser_data` exclusivamente no endpoint `/api/v1/dossier` para compatibilidade contratual.

#### Scenario: Desafio HTTP 402 em micro-serviços padrão expõe preview sem duplicar teaser_data
- **GIVEN** uma requisição sem pagamento enviada para um serviço com prévia padrão (ex.: `/api/v1/cep?code=01310100`, `/api/v1/cnpj?cnpj=00000000000191` ou `/api/v1/workdays?date=2026-12-25`)
- **WHEN** o gateway x402 emite o desafio HTTP `402`
- **THEN** o payload JSON SHALL conter `preview` e `locked_fields`
- **AND** a chave duplicada `teaser_data` SHALL NOT estar presente no corpo JSON desses endpoints.

#### Scenario: Desafio HTTP 402 em /api/v1/dossier preserva teaser_data retrocompatível
- **GIVEN** uma requisição sem pagamento enviada para `GET /api/v1/dossier?cnpj=00000000000191`
- **WHEN** o gateway x402 emite o desafio HTTP `402`
- **THEN** o payload JSON SHALL manter tanto `preview` quanto `teaser_data` com os metadados públicos resumidos da empresa.
