# Spec Delta: mercadopago-checkout

## MODIFIED Requirements

### Requirement: Catálogo de Pacotes em BRL na Interface
O sistema MUST disponibilizar os 4 pacotes oficiais em BRL na escala `v2_5000` (`micro` R$ 4,90 / 50 cr / 90d, `starter` R$ 12,00 / 400 cr / 180d, `pro` R$ 29,00 / 1.200 cr / 180d e `fleet` R$ 115,00 / 6.000 cr / 365d) em `/pass`, mantendo os 3 pacotes Web3 USDC (`2 / 5 / 20 USDC`) e sem disparar `startMpPix()` automaticamente ao abrir `/pass?method=pix` ou `autopix=1`.

#### Scenario: Renderização dos pacotes BRL em /pass
- **GIVEN** um usuário acessa `GET /pass`
- **WHEN** a página é renderizada
- **THEN** ela exibe os pacotes `Micro` (R$ 4,90 · 50 créditos / 5 dossiês), `Starter` (R$ 12,00 · 400 créditos / 40 dossiês), `Pro` (R$ 29,00 · 1.200 créditos) e `Fleet` (R$ 115,00 · 6.000 créditos) com botões para **Pagar com Pix** e **Pagar com Cartão (Mercado Pago)**

#### Scenario: Abertura de /pass com method=pix não dispara cobrança automática
- **GIVEN** um visitante acessa `GET /pass?tier=starter&method=pix` ou `GET /pass?tier=starter&autopix=1`
- **WHEN** a página termina de carregar no navegador
- **THEN** a interface destaca visualmente o pacote solicitado sem executar `POST /api/v1/pass/mp-pix` antes do clique explícito do usuário

### Requirement: Criação de Cobrança Pix Transparente
O endpoint `POST /api/v1/pass/mp-pix` MUST validar o pacote (`micro`, `starter`, `pro` ou `fleet`), nunca utilizar endereço do domínio `grid402.online` como fallback de `payer.email`, criar o pagamento Pix na API `/v1/payments` (ou sandbox), persistir a ordem em `private_data/mp_orders.json` sob `flock(LOCK_EX)` e retornar `order_id`, `qr_code`, `qr_code_base64`, `qr_mime` e `expires_at`.

#### Scenario: Geração de Pix para pacote válido
- **GIVEN** `GRID402_MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- **WHEN** o cliente envia `POST /api/v1/pass/mp-pix` com `{"tier":"pro","email":"dev@empresa.com.br"}`
- **THEN** o servidor retorna HTTP 200 com `order_id`, `tier: "pro"`, `amount_brl: 29`, `credits: 1200`, `qr_code`, `qr_code_base64` e `qr_mime`, gravando a ordem com status `pending`

#### Scenario: Geração de Pix para o novo pacote micro (R$ 4,90)
- **GIVEN** `GRID402_MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- **WHEN** o cliente envia `POST /api/v1/pass/mp-pix` com `{"tier":"micro","email":"compras@empresa.com.br"}`
- **THEN** o servidor retorna HTTP 200 com `order_id`, `tier: "micro"`, `amount_brl: 4.9`, `credits: 50`, `qr_code`, `qr_code_base64` e `qr_mime`, gravando a ordem com status `pending`

#### Scenario: Cobrança Pix sem e-mail nunca envia endereço do domínio grid402.online
- **GIVEN** `GRID402_MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- **WHEN** uma chamada envia `POST /api/v1/pass/mp-pix` com `{"tier":"starter","email":""}`
- **THEN** o campo `payer.email` enviado à API `/v1/payments` SHALL NOT conter `@grid402.online`

#### Scenario: Pacote inválido rejeitado
- **GIVEN** qualquer configuração ativa
- **WHEN** o cliente envia `POST /api/v1/pass/mp-pix` com `{"tier":"inexistente"}`
- **THEN** o servidor retorna HTTP 400 sem chamar a API do Mercado Pago

#### Scenario: Gateway não configurado fora do modo sandbox
- **GIVEN** `GRID402_MP_ACCESS_TOKEN` vazio e `GRID402_MP_SANDBOX=false`
- **WHEN** o cliente envia `POST /api/v1/pass/mp-pix`
- **THEN** o servidor retorna HTTP 503 informando que o checkout em BRL ainda não está configurado

### Requirement: Criação de Preferência Checkout Pro (Cartão / Saldo MP)
O endpoint `POST /api/v1/pass/mp-checkout` MUST aceitar os pacotes `micro`, `starter`, `pro` e `fleet` e criar uma preferência no Mercado Pago (`/checkout/preferences`) com o valor fixo em BRL, `external_reference` igual ao `order_id` e `back_urls` apontando para `/pass?mp_order=<order_id>`, retornando `order_id` e `checkout_url`.

#### Scenario: Criação de preferência Checkout Pro válida
- **GIVEN** `GRID402_MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- **WHEN** o cliente envia `POST /api/v1/pass/mp-checkout` com `{"tier":"fleet"}`
- **THEN** o servidor retorna HTTP 200 com `order_id` e `checkout_url`, e a preferência enviada ao Mercado Pago contém `unit_price: 115`, `currency_id: "BRL"` e `external_reference` igual ao `order_id`

#### Scenario: Criação de preferência Checkout Pro válida para pacote micro
- **GIVEN** `GRID402_MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- **WHEN** o cliente envia `POST /api/v1/pass/mp-checkout` com `{"tier":"micro"}`
- **THEN** o servidor retorna HTTP 200 com `order_id` e `checkout_url`, e a preferência enviada ao Mercado Pago contém `unit_price: 4.9`, `currency_id: "BRL"` e `external_reference` igual ao `order_id`

## ADDED Requirements

### Requirement: Validação de Disponibilidade do Gateway Mercado Pago na Interface
As páginas `/pass` e `/dossie-kyb` MUST verificar no servidor se `GRID402_MP_ACCESS_TOKEN` está configurado ou `GRID402_MP_SANDBOX=true` antes de habilitar ações de checkout BRL, exibindo aviso claro e redirecionando para Web3 USDC / RapidAPI USD quando o gateway estiver indisponível.

#### Scenario: Interface indica indisponibilidade quando token MP está ausente em produção
- **GIVEN** `GRID402_MP_SANDBOX=false` e `GRID402_MP_ACCESS_TOKEN` vazio
- **WHEN** um visitante acessa `GET /pass` ou `GET /dossie-kyb`
- **THEN** a página informa que o checkout BRL via Mercado Pago está indisponível e desabilita os botões de disparo Pix/Cartão BRL preservando as alternativas ativas

### Requirement: Saída Fiat em USD via RapidAPI no `/pass` e na Home
As páginas `/pass` (`public_html/pass.php`) e a seção de compra da Home (`public_html/index.php`) MUST exibir um CTA claro de pagamento com **Cartão de Crédito em USD via RapidAPI** apontando para `https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities`.

#### Scenario: Visitante internacional encontra opção Cartão USD no `/pass` e na Home
- **GIVEN** um visitante acessando `GET /pass` ou `GET /`
- **WHEN** o HTML SSR é renderizado
- **THEN** ambas as páginas SHALL conter link externo (`rel="noopener noreferrer"`) com texto explícito de Cartão USD apontando para `https://rapidapi.com/oliveiratc/api/grid402-open-data-kyb-ai-utilities`
