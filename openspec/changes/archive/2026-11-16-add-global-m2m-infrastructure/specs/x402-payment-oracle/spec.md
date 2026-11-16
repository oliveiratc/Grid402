## ADDED Requirements

### Requirement: Emissão e Consumo de Passe de Sessão Pré-pago x402 (REQ-PASS-001)
O sistema SHALL permitir a emissão de passes de sessão pré-pagos (`pass_g402_<id>_<hmac>`) através do endpoint `/api/v1/pass` mediante liquidação em USDC na Base (escala oficial `v2_5000`: `0.0050 USDC` = 1 crédito universal; Starter `2 USDC` = 400 créditos, Pro `5 USDC` = 1.000 créditos, Fleet `20 USDC` = 6.000 créditos) e aceitar esse token no cabeçalho `X-Payment` em todos os micro-serviços do Grid402.

#### Scenario: Emissão de Session Pass e débito atômico em chamadas subsequentes
- GIVEN que um cliente ou agente obtém um token `pass_g402_...` com créditos ativos
- WHEN realiza uma chamada para qualquer endpoint (ex: `/api/v1/forex` ou `/api/v1/lei`) enviando `X-Payment: pass_g402_...`
- THEN o sistema valida o HMAC e saldo em disco com lock exclusivo, debita os créditos correspondentes ao preço do serviço e retorna HTTP 200 imediatamente sem consultar o RPC da blockchain

#### Scenario: Consulta gratuita de saldo de um Session Pass
- GIVEN um token `pass_g402_...` emitido anteriormente
- WHEN o agente consulta `GET /api/v1/pass?status=pass_g402_...` sem precisar de novo pagamento
- THEN o sistema retorna HTTP 200 com `credits_total`, `credits_remaining`, `expires_at` e `active`

#### Scenario: Rejeição de Session Pass com saldo insuficiente ou assinatura inválida
- GIVEN um token com créditos esgotados ou HMAC adulterado
- WHEN enviado no cabeçalho `X-Payment`
- THEN o sistema rejeita a requisição com HTTP 402/403 informando o motivo exato

