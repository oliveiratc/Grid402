# Adicionar Checkout Mercado Pago (Pix + Cartão) para Session Pass

## Por quê
Atualmente, a compra de Session Passes (`pass_g402_...`) no Grid402 exige USDC na rede Base Mainnet e carteira Web3 conectada, o que bloqueia desenvolvedores e empresas brasileiras sem carteira cripto. Conforme alinhado no PRD (`openspec/prds/checkout-mercadopago-session-pass.md`), adicionar pagamento em BRL via API REST do Mercado Pago (Pix transparente na tela + Checkout Pro para cartão/saldo) destrava a conversão imediata mantendo a emissão atômica e idempotente de tokens `pass_g402_...`.

## O que muda
- Novo módulo `public_html/services/mercadopago.php` para comunicação cURL com a API REST do Mercado Pago (`/v1/payments` para Pix, `/checkout/preferences` para Checkout Pro, `GET /v1/payments/{id}` para verificação ativa), persistência atômica de ordens em `private_data/mp_orders.json` via `flock(LOCK_EX)`, validação HMAC de webhook e modo sandbox/simulação.
- Novas constantes de configuração em `public_html/config.php` (`GRID402_MP_ACCESS_TOKEN`, `GRID402_MP_WEBHOOK_SECRET`, `GRID402_MP_SANDBOX`, `GRID402_FILE_MP_ORDERS`, tabela de pacotes BRL) e atualização de `.env.example`.
- Novas rotas em `public_html/index.php`:
  - `POST /api/v1/pass/mp-pix`
  - `POST /api/v1/pass/mp-checkout`
  - `POST /api/v1/pass/mp-webhook`
  - `GET /api/v1/pass/mp-status`
  - `POST /api/v1/pass/mp-sandbox-approve` (ativo apenas em modo sandbox)
- Atualização da UI de `/pass` (`public_html/pass.php`) e da seção `#topup` em `public_html/index.php` com preços fixos em BRL (`Starter R$ 12`, `Pro R$ 29`, `Fleet R$ 115`), modal/painel de QR Code Pix + copia-e-cola com polling a cada 3s, botão de Checkout Pro e exibição imediata do token `pass_g402_...`.

## Impacto
- Specs afetadas: `mercadopago-checkout` (nova spec)
- Código afetado: `public_html/config.php`, `public_html/services/mercadopago.php`, `public_html/services/pass.php` (reuso de `grid402_issue_session_pass`), `public_html/index.php`, `public_html/pass.php`, `public_html/metrics.php` (opcional/compatível), `.env.example`, `tests/test_mercadopago_checkout.php`.
