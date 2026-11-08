# Proposta: Notificacoes de Compra em Tempo Real no Telegram

## Por que
O Grid402 processa micropagamentos x402 em USDC na rede Base de forma autonoma. Para que o mantenedor possa monitorar a receita e a adocao em tempo real sem depender de consultas manuais ao servidor ou painel de metricas, precisamos de um canal de notificacao push instantaneo no smartphone via Telegram Bot API. Referencia detalhada no PRD: `openspec/prds/telegram-purchase-notifications.md`.

## O que muda
- Adicao de funcao `grid402_notify_telegram_purchase(...)` em `public_html/x402.php` (ou modulo dedicado integrado).
- Leitura segura das configuracoes `GRID402_TELEGRAM_BOT_TOKEN` e `GRID402_TELEGRAM_CHAT_ID` a partir do `.env`.
- Disparo assincrono nao-bloqueante (timeout estrito de 1 segundo) imediatamente apos a confirmacao on-chain de transacoes validas na Base Mainnet.
- Envio de mensagem formatada contendo servico, valor em USDC, data/hora e link do BaseScan.
- Tratamento resiliente: se o Telegram falhar ou se as credenciais nao existirem, a API segue seu fluxo normal sem nenhum atraso ou erro para o agente de IA pagador.

## Impacto
- Specs afetadas: `x402-payment-oracle`
- Codigo afetado:
  - `public_html/config.php` (declaracao e fallback das variaveis do Telegram)
  - `public_html/x402.php` (disparo do alerta apos confirmacao do pagamento)
  - `.env` (armazenamento seguro do token e chat_id)
  - `tests/test_telegram_notification.php` (suite de testes de integracao e isolamento de falhas)

