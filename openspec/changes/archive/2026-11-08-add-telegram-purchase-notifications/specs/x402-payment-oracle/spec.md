## ADDED Requirements

### Requirement: Telegram real-time purchase notification
O servidor SHALL emitir uma notificacao push instantanea para o Telegram do mantenedor sempre que um micropagamento on-chain em USDC na rede Base Mainnet for confirmado com sucesso e retido contra replay attack.

#### Scenario: Real payment notification on Base Mainnet
- GIVEN o token do bot `GRID402_TELEGRAM_BOT_TOKEN` e o chat ID `GRID402_TELEGRAM_CHAT_ID` configurados no ambiente
- WHEN um agente de IA envia um `X-Payment` valido com valor >= 0.0005 USDC liquidado na Base Mainnet
- THEN o servidor valida o recibo on-chain, registra o hash como utilizado e dispara uma mensagem ao Telegram contendo o nome do servico, o valor em USDC, o horario UTC e o link de auditoria no BaseScan

#### Scenario: Silent skip on missing credentials
- GIVEN as variaveis `GRID402_TELEGRAM_BOT_TOKEN` ou `GRID402_TELEGRAM_CHAT_ID` ausentes ou vazias no ambiente
- WHEN um pagamento valido e liquidado na Base Mainnet
- THEN o servidor nao tenta contato externo com a Telegram Bot API e entrega a resposta do servico com HTTP 200 normalmente

#### Scenario: Non-blocking resilience on Telegram API failure
- GIVEN a API do Telegram indisponivel, retornando erro HTTP 5xx ou sofrendo timeout de rede
- WHEN uma transacao valida e confirmada na Base Mainnet
- THEN a chamada de notificacao atinge o timeout maximo de 1 segundo, o erro e capturado silenciosamente e o agente de IA recebe seus dados com HTTP 200 sem qualquer interrupcao

#### Scenario: Exclusion of sandbox and free requests
- GIVEN uma requisicao autenticada como sandbox ou consulta ao resumo gratuito
- WHEN a resposta e processada com HTTP 200
- THEN nenhuma notificacao e enviada para o canal de compras do Telegram

