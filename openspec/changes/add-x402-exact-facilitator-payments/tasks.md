# Tasks

## 1. Configuração e extração da prova de pagamento

- [x] 1.1 Adicionar a `config.php` e `.env.example` as variáveis `GRID402_FACILITATOR_MODE` (`disabled` por padrão), `GRID402_FACILITATOR_URL`, `GRID402_CDP_API_KEY_ID` e `GRID402_CDP_API_KEY_SECRET`; verificar que `php -l` passa e que o modo padrão não altera nenhuma resposta atual
- [x] 1.2 Criar em `x402.php` a função de classificação do formato da prova (`pass`, `demo`, `tx_hash`, `x402_payload`, `unknown`) e a de decodificação do payload base64; cobrir com `tests/test_x402_exact_parsing.php` (payload v2 válido, base64 inválido, JSON sem campos obrigatórios, formatos legados)
- [x] 1.3 Ajustar `index.php` para encaminhar `PAYMENT-SIGNATURE` e `X-PAYMENT` em base64 ao novo fluxo sem quebrar `X-Payment: 0x...`, `pass_g402_...` e `demo`; verificar com `tests/test_x402_gateway.php` e `tests/test_sandbox_mode.php` passando

## 2. Validação local e anti-replay

- [x] 2.1 Implementar a validação local dos requisitos (rede `eip155:8453`, USDC, destinatário, valor mínimo e janela `validAfter`/`validBefore`) e verificar com testes para valor menor, destinatário errado, rede errada e autorização expirada, todos retornando 402 sem acionar o facilitador
- [x] 2.2 Implementar `used_nonces.json` com `flock` (reserva, liberação e estado pendente) e verificar com teste de reuso (403) e de concorrência com várias requisições simultâneas do mesmo `payer:nonce` aprovando exatamente uma

## 3. Cliente do facilitador

- [x] 3.1 Implementar a geração do JWT do CDP (ES256 com `openssl_sign` e conversão DER para R||S; Ed25519 com `sodium`) e verificar com teste unitário que valida a assinatura e a expiração do token com chaves de teste
- [x] 3.2 Implementar `verify` e `settle` por cURL com timeouts (5 s e 10 s), sem retentar `settle`, e o mapeamento de erros (402, 403, 503 com `Retry-After`); verificar com facilitador simulado local cobrindo sucesso, rejeição, timeout e erro 5xx
- [x] 3.3 Garantir que segredos nunca apareçam em respostas, logs ou telemetria e verificar com teste que força erro do facilitador e inspeciona a saída

## 4. Integração no fluxo de pagamento

- [x] 4.1 Integrar o novo caminho em `grid402_validate_payment()`: validar, reservar nonce, `verify`, `settle` e só então liberar o 200, incluindo o cabeçalho `PAYMENT-RESPONSE` (e `X-PAYMENT-RESPONSE`) com o recibo; verificar com teste de ponta a ponta usando o facilitador simulado
- [x] 4.2 Garantir que, com `GRID402_FACILITATOR_MODE=disabled`, um payload padrão retorne 402 com instruções do fluxo por `tx_hash`; verificar com teste dedicado
- [x] 4.3 Atualizar `grid402_respond_402()` para manter `accepts` v2 e descrever, em `action_required`, o fluxo alternativo por `tx_hash` e `demo`; verificar que o JSON do desafio mantém `price_usdc`, `recipient_wallet` e `accepts` e que `tests/test_x402_gateway.php` segue passando

## 5. Telemetria e painel

- [x] 5.1 Estender `metrics.php` com `payment_source` e o hash de liquidação, somando ao faturamento apenas pagamentos liquidados; verificar com teste que simula pagamento facilitado, falha do facilitador e sandbox
- [x] 5.2 Atualizar `admin.php` para mostrar a origem do pagamento e as falhas por categoria (prova inválida, expirada, replay, facilitador indisponível), de modo que a soma das barras de status seja igual ao total de consultas; verificar com `tests/test_admin_dashboard.php` ajustado e inspeção visual do painel

## 6. Documentação e clientes

- [x] 6.1 Atualizar `llms.txt`, `llms-full.txt`, `openapi.json`, `.well-known/mcp.json` e `README.md` descrevendo os dois fluxos de pagamento; verificar que os exemplos citados correspondem ao comportamento testado
- [x] 6.2 Revisar `sdk/node`, `sdk/python` e `mcp/x402_payer.mjs` para suportar o pagamento padrão ou documentar claramente o fluxo por `tx_hash`; verificar com `tests/test_sdk_node.mjs` e `tests/test_sdk_python.py`

## 7. Integração e homologação

- [x] 7.1 Executar a suíte `tests/*.php` completa e `openspec validate add-x402-exact-facilitator-payments --strict`; verificar que tudo passa
- [ ] 7.2 Homologar em Base Sepolia com credenciais de teste do facilitador e um cliente x402 padrão, verificando HTTP 200, `PAYMENT-RESPONSE` e a contabilização no painel

## Workflow follow-up

- Em produção: configurar as credenciais no `.env`, ativar `verify_only` e depois `settle`, acompanhando o painel.
- Confirmar preço e cotas do facilitador antes de ativar `settle`.
- Arquivar a change depois da revisão e da homologação.

