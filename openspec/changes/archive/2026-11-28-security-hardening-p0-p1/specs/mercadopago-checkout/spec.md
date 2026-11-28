# Spec Delta

## ADDED Requirements

### Requirement: Mercado Pago webhook shall verify signature fail-closed
O endpoint do webhook do Mercado Pago SHALL validar a assinatura HMAC de forma estrita em ambiente de produção, rejeitando notificações sem chave configurada ou com assinatura inválida.

#### Scenario: Webhook recebido sem webhook secret configurado em producao
- **WHEN** uma requisição POST é enviada a `/api/v1/pass/mp-webhook` em produção e `GRID402_MP_WEBHOOK_SECRET` não está definido
- **THEN** o sistema retorna HTTP 503 e aborta o processamento sem aprovar pedidos

#### Scenario: Assinatura de webhook expirada ou fraudulenta
- **WHEN** uma requisição POST contém timestamp `ts` com desvio superior a 300 segundos ou HMAC divergente
- **THEN** o sistema responde com HTTP 401 Unauthorized e não altera o estado da ordem

### Requirement: Webhook response shall not leak session pass tokens
O manipulador do webhook SHALL retornar exclusivamente confirmação genérica de recebimento sem incluir dados sensíveis ou tokens de clientes pagantes no corpo da resposta HTTP.

#### Scenario: Confirmacao bem sucedida de webhook
- **WHEN** um webhook de pagamento aprovado é processado com sucesso
- **THEN** o endpoint responde com corpo `{"ok": true}` e não inclui a chave `pass_token`
