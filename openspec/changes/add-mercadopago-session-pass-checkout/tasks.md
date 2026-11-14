# Tasks: Checkout Mercado Pago (Pix + Cartão) para Session Pass

## 1. Configuração e Módulo Backend Mercado Pago
- [x] 1.1 Adicionar constantes `GRID402_MP_ACCESS_TOKEN`, `GRID402_MP_WEBHOOK_SECRET`, `GRID402_MP_SANDBOX` e `GRID402_FILE_MP_ORDERS` em `public_html/config.php` e documentar as variáveis em `.env.example`
- [x] 1.2 Criar `public_html/services/mercadopago.php` com tabela fixa de pacotes BRL (`starter` R$ 12, `pro` R$ 29, `fleet` R$ 115), cliente cURL para `/v1/payments`, `/checkout/preferences` e `GET /v1/payments/{id}`, transporte injetável para testes e modo sandbox
- [x] 1.3 Implementar persistência atômica em `private_data/mp_orders.json` com `flock(LOCK_EX)`, verificação HMAC de webhook (`x-signature`) e emissão idempotente via `grid402_issue_session_pass()`

## 2. Roteamento HTTP e Frontend (`/pass` e Home `#topup`)
- [x] 2.1 Conectar as rotas `POST /api/v1/pass/mp-pix`, `POST /api/v1/pass/mp-checkout`, `POST /api/v1/pass/mp-webhook`, `GET /api/v1/pass/mp-status` e `POST /api/v1/pass/mp-sandbox-approve` em `public_html/index.php`
- [x] 2.2 Atualizar `public_html/pass.php` e a seção `#topup` de `public_html/index.php` com preços fixos em BRL, botões de **Pagar com Pix** e **Pagar com Cartão (Mercado Pago)**, modal/painel de QR Code Pix + copia-e-cola com polling a cada 3s, tratamento de retorno `?mp_order=...` do Checkout Pro e exibição imediata do token `pass_g402_...`

## 3. Testes e Verificação
- [x] 3.1 Criar e executar `tests/test_mercadopago_checkout.php` cobrindo todos os cenários da spec (Pix, Checkout Pro, webhook com HMAC válido/inválido, polling idempotente, modo sandbox e bloqueio 403/503)
- [x] 3.2 Validar lint PHP e suíte completa de testes existentes sem regressões
