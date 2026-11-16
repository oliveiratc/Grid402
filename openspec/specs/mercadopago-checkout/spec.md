# mercadopago-checkout Specification

## Purpose
Definir o fluxo oficial de compra e emissão automática de Session Passes pré-pagos x402 em BRL via Mercado Pago (Pix transparente e Checkout Pro com confirmação via webhook e HMAC-SHA256) na plataforma Grid402.

## Requirements

### Requirement: Catálogo de Pacotes em BRL na Interface
O sistema MUST exibir os 3 pacotes de Session Pass com preços fixos em BRL (`Starter` R$ 12,00, `Pro` R$ 29,00 e `Fleet` R$ 115,00) na página `/pass` e na seção `#topup` da Home, mantendo as opções Web3 existentes.

#### Scenario: Renderização dos pacotes BRL em /pass
- GIVEN um usuário acessa `GET /pass`
- WHEN a página é renderizada
- THEN ela exibe os pacotes `Starter` (R$ 12,00 · 4.000 requisições), `Pro` (R$ 29,00 · 10.000 requisições) e `Fleet` (R$ 115,00 · 60.000 requisições) com botões para **Pagar com Pix** e **Pagar com Cartão (Mercado Pago)**.

---

### Requirement: Criação de Cobrança Pix Transparente
O endpoint `POST /api/v1/pass/mp-pix` MUST validar o pacote (`starter`, `pro` ou `fleet`), criar um pagamento Pix na API `/v1/payments` do Mercado Pago (ou no modo sandbox quando ativo), persistir a ordem em `private_data/mp_orders.json` com `flock(LOCK_EX)` e retornar `order_id`, `qr_code`, `qr_code_base64` e `expires_at`.

#### Scenario: Geração de Pix para pacote válido
- GIVEN `MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- WHEN o cliente envia `POST /api/v1/pass/mp-pix` com `{"tier":"pro","email":"dev@empresa.com.br"}`
- THEN o servidor retorna HTTP 200 com `order_id`, `tier: "pro"`, `amount_brl: 29`, `qr_code` e `qr_code_base64`, e grava a ordem com status `pending`.

#### Scenario: Pacote inválido rejeitado
- GIVEN qualquer configuração ativa
- WHEN o cliente envia `POST /api/v1/pass/mp-pix` com `{"tier":"inexistente"}`
- THEN o servidor retorna HTTP 400 sem chamar a API do Mercado Pago.

#### Scenario: Gateway não configurado fora do modo sandbox
- GIVEN `GRID402_MP_ACCESS_TOKEN` vazio e `GRID402_MP_SANDBOX=false`
- WHEN o cliente envia `POST /api/v1/pass/mp-pix`
- THEN o servidor retorna HTTP 503 informando que o checkout em BRL ainda não está configurado.

---

### Requirement: Criação de Preferência Checkout Pro (Cartão / Saldo MP)
O endpoint `POST /api/v1/pass/mp-checkout` MUST criar uma preferência no Mercado Pago (`/checkout/preferences`) com o valor fixo em BRL do pacote escolhido, `external_reference` igual ao `order_id` e `back_urls` apontando para `/pass?mp_order=<order_id>`, retornando `order_id` e `checkout_url`.

#### Scenario: Criação de preferência Checkout Pro válida
- GIVEN `MP_ACCESS_TOKEN` configurado (ou transporte mockado)
- WHEN o cliente envia `POST /api/v1/pass/mp-checkout` com `{"tier":"fleet"}`
- THEN o servidor retorna HTTP 200 com `order_id` e `checkout_url`, e a preferência enviada ao Mercado Pago contém `unit_price: 115`, `currency_id: "BRL"` e `external_reference` igual ao `order_id`.

---

### Requirement: Webhook com Validação HMAC e Verificação Ativa
O endpoint `POST /api/v1/pass/mp-webhook` MUST validar a assinatura `x-signature` quando `GRID402_MP_WEBHOOK_SECRET` estiver definida, consultar ativamente `GET /v1/payments/{id}` no Mercado Pago e, quando `status === 'approved'` e o `external_reference` corresponder a uma ordem válida, emitir o `pass_g402_...` de forma idempotente.

#### Scenario: Webhook aprovado emite Session Pass
- GIVEN uma ordem `pending` registrada em `mp_orders.json` e `GET /v1/payments/998877` retornando `status: "approved"` com `external_reference` igual ao `order_id` e `transaction_amount` compatível
- WHEN `POST /api/v1/pass/mp-webhook` recebe a notificação com assinatura válida
- THEN a ordem é atualizada para `approved` e um token `pass_g402_...` com os créditos do pacote é emitido e vinculado à ordem.

#### Scenario: Webhook com assinatura HMAC inválida é rejeitado
- GIVEN `GRID402_MP_WEBHOOK_SECRET` configurada
- WHEN `POST /api/v1/pass/mp-webhook` recebe header `x-signature` inválido
- THEN o servidor retorna HTTP 401 e não consulta nem aprova a ordem.

---

### Requirement: Consulta de Status (Polling) e Idempotência
O endpoint `GET /api/v1/pass/mp-status?order_id=...` MUST retornar o status atual da ordem e, caso esteja `approved`, retornar o token `pass_g402_...` emitido, os créditos e a validade. Se a ordem estiver `pending` e receber `payment_id` (retorno do Checkout Pro) ou já possuir `mp_payment_id`, MUST consultar o Mercado Pago para atualizar e emitir o token de forma idempotente.

#### Scenario: Polling após aprovação retorna o mesmo token sem duplicar
- GIVEN uma ordem já aprovada via webhook com `pass_token: "pass_g402_abc..."`
- WHEN o frontend chama `GET /api/v1/pass/mp-status?order_id=...` repetidamente (e/ou um segundo webhook chega com o mesmo `payment_id`)
- THEN todas as chamadas retornam o mesmo `pass_token` sem criar um segundo registro em `pass_registry.json`.

---

### Requirement: Modo Sandbox para Testes Sem Credenciais Reais
Quando `GRID402_MP_SANDBOX=true`, o sistema MUST permitir simular a criação de Pix/Checkout Pro e aprovar uma ordem de teste via `POST /api/v1/pass/mp-sandbox-approve`, emitindo um Session Pass marcado como `sandbox: true`. Quando `GRID402_MP_SANDBOX=false`, `mp-sandbox-approve` MUST retornar HTTP 403.

#### Scenario: Aprovação simulada em modo sandbox
- GIVEN `GRID402_MP_SANDBOX=true` e uma ordem criada via `mp-pix`
- WHEN o cliente envia `POST /api/v1/pass/mp-sandbox-approve` com `{"order_id":"..."}`
- THEN a ordem passa para `approved` e retorna um `pass_g402_...` válido com `sandbox: true`.

#### Scenario: Bloqueio do endpoint sandbox em produção
- GIVEN `GRID402_MP_SANDBOX=false`
- WHEN o cliente envia `POST /api/v1/pass/mp-sandbox-approve`
- THEN o servidor retorna HTTP 403.
