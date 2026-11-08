## 1. Configuracao e Seguranca
- [x] 1.1 Atualizar `public_html/config.php` para definir constantes `GRID402_TELEGRAM_BOT_TOKEN` e `GRID402_TELEGRAM_CHAT_ID` a partir das variaveis de ambiente
- [x] 1.2 Atualizar `.env` local e preparar instrucoes para insercao no `.env` de producao na Hostinger

## 2. Implementacao da Funcao de Notificacao
- [x] 2.1 Criar a funcao `grid402_notify_telegram_purchase(...)` em `public_html/x402.php` com chamada cURL nao-bloqueante (timeout 1s) e tratamento silencioso de erros
- [x] 2.2 Integrar o gatilho de envio imediatamente apos a confirmacao on-chain em `grid402_validate_payment` para transacoes validas da Base Mainnet (excluindo sandbox)
- [x] 2.3 Formatar a mensagem do alerta com emojis, nome amigavel do servico, valor em USDC, data UTC e link do BaseScan

## 3. Testes e Verificacao
- [x] 3.1 Criar suite de testes em `tests/test_telegram_notification.php` cobrindo cenarios: disparo com sucesso, omissao graciosa com credenciais ausentes, isolamento de timeout e exclusao de sandbox
- [x] 3.2 Executar a suite de testes e validar 100% de sucesso
- [x] 3.3 Enviar notificacao real de teste via script PHP para o Telegram do Thiago para atestar a formatacao final
- [x] 3.4 Validar a change no OpenSpec com `openspec validate add-telegram-purchase-notifications --strict`

