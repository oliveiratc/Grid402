# Design: Checkout Mercado Pago (Pix + Cartão) para Session Pass

## 1. Arquitetura e Fluxo de Dados
A integração usa PHP 8.x puro com cURL contra a API REST v1 do Mercado Pago, sem dependências Composer.

### Pacotes Fixos em BRL
| Tier | Preço BRL | Equivalente Pass (`raw_usdc_units`) | Créditos | Validade |
|---|---|---|---|---|
| `starter` | `12.00` | `2000000` (2 USDC) | 4.000 req | 30 dias |
| `pro` | `29.00` | `5000000` (5 USDC) | 10.000 req | 30 dias |
| `fleet` | `115.00` | `20000000` (20 USDC) | 60.000 req (50k + 10k bônus) | 60 dias |

### Persistência Atômica (`private_data/mp_orders.json`)
Cada ordem possui estrutura:
```json
{
  "order_id": "mp_ord_<hex>",
  "tier": "pro",
  "amount_brl": 29.00,
  "raw_usdc_units": 5000000,
  "method": "pix|checkout_pro",
  "status": "pending|approved|rejected|expired",
  "sandbox": false,
  "mp_payment_id": "123456789",
  "mp_preference_id": "pref_...",
  "pass_token": "pass_g402_...",
  "created_at": 1794670000,
  "updated_at": 1794670010
}
```
Todas as leituras/escritas com transição de estado usam `fopen(..., 'c+')` + `flock(LOCK_EX)` para impedir emissão dupla entre webhook e polling simultâneo.

### Idempotência na Emissão do Session Pass
Ao confirmar `status === 'approved'` no Mercado Pago (via webhook ou polling):
1. Adquire `flock(LOCK_EX)` em `mp_orders.json`.
2. Se `order['pass_token']` já existir, retorna o token existente sem reemitir.
3. Também verifica mapa reverso `mp_payment_id -> order_id` para impedir que o mesmo pagamento MP emita passes em duas ordens diferentes.
4. Chama `grid402_issue_session_pass('mp_pay_' . $mpPaymentId, $rawUsdcUnits, $isSandbox)` de `public_html/services/pass.php`.

### Segurança do Webhook e Transporte Testável
- Validação de `x-signature` (formato `ts=...,v1=...`) usando `hash_hmac('sha256', "id:{$dataId};request-id:{$xRequestId};ts:{$ts};", $secret)` quando `GRID402_MP_WEBHOOK_SECRET` estiver configurada.
- Mesmo após validar assinatura, o backend **sempre** consulta `GET /v1/payments/{id}` na API do Mercado Pago antes de aprovar a ordem.
- Transporte HTTP injetável via `$GLOBALS['grid402_mp_http_transport']` (mesmo padrão de `facilitator.php`) para testes unitários/E2E determinísticos sem rede externa.

### Modo Sandbox (`GRID402_MP_SANDBOX`)
- Se `GRID402_MP_SANDBOX=true` (ou `1`), `mp-pix` gera um QR Code SVG/data-URI de simulação local e `mp-checkout` gera URL de simulação local para `/pass?mp_order=...&sandbox_approve=1`.
- `POST /api/v1/pass/mp-sandbox-approve` só funciona quando `GRID402_MP_SANDBOX` está ativo; caso contrário retorna HTTP 403.
- Em produção sem `MP_ACCESS_TOKEN` e com `GRID402_MP_SANDBOX=false`, os endpoints retornam HTTP 503.
