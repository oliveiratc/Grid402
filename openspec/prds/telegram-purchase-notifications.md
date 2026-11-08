# PRD: Notificacoes de Compra em Tempo Real no Telegram

**Status:** Aprovado  
**Data:** 2026-11-08  
**Autor:** Thiago e Agente  

---

## 1. Contexto e problema
Atualmente o Grid402 processa micropagamentos x402 on-chain em USDC na rede Base de forma 100% autonoma. Os eventos sao registrados silenciosamente na telemetria local (`metrics.json`). No entanto, o mantenedor do projeto nao recebe alertas imediatos em seu dispositivo movel quando uma nova venda ou consumo pago acontece, dificultando o acompanhamento de tracao, faturamento e eventuais picos de uso por agentes de IA em tempo real.

## 2. Objetivos
- Notificar o mantenedor via Telegram em menos de 3 segundos apos a confirmacao on-chain de um pagamento x402 na Base Mainnet.
- Manter o tempo de resposta do oraculo estritamente abaixo de 20 ms, isolando a chamada da API do Telegram de forma nao-bloqueante (timeout de 1s e supressao de falhas).
- Garantir 100% de sigilo das credenciais de autenticacao do bot atraves de variaveis de ambiente no arquivo `.env`.

## 3. Nao objetivos (fora do escopo)
- Envio de alertas para chamadas gratuitas de resumo (`/api/v1/summary`) ou chamadas do oraculo de verificacao (`/api/v1/verify`).
- Envio de alertas para chamadas simuladas de Sandbox ou testnet Base Sepolia.
- Interacao bidirecional com o bot (o bot atuara estritamente como emissor unidirecional de notificacoes, sem receber comandos de administracao remota nesta versao).
- Notificacoes via WhatsApp, Discord ou e-mail.

## 4. Publico-alvo e personas
- **Mantenedor do Grid402 (Thiago):** Deseja ser notificado instantaneamente em seu smartphone sobre receitas geradas por chamadas de agentes de IA, incluindo o servico requisitado, o montante pago e o link da transacao na blockchain.

## 5. Historias de usuario
- Como mantenedor do Grid402, quero receber uma mensagem no meu chat privado do Telegram sempre que uma transacao x402 for validada com sucesso na Base Mainnet, para acompanhar as vendas sem precisar abrir o painel de metricas ou o servidor.
- Como desenvolvedor, quero que qualquer instabilidade na API do Telegram nao prejudique nem atrase a resposta JSON entregue ao agente de IA pagador.

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| RF01 | Integrar funcao de notificacao Telegram no modulo `public_html/x402.php` ou `public_html/metrics.php`. | Must |
| RF02 | Carregar `GRID402_TELEGRAM_BOT_TOKEN` e `GRID402_TELEGRAM_CHAT_ID` via `grid402_load_env()` a partir do `.env`. | Must |
| RF03 | Disparar notificacao exclusivamente para pagamentos com status `valid == true` e `sandbox == false` na Base Mainnet. | Must |
| RF04 | Formatar mensagem com emojis, nome do servico consultado, valor em USDC, data/hora UTC e link para o BaseScan (`https://basescan.org/tx/{txHash}`). | Must |
| RF05 | Desativar silenciosamente o envio caso as variaveis do Telegram nao estejam configuradas, sem lancar erros nem interromper a API. | Must |

## 7. Requisitos nao funcionais
- **Performance:** A chamada cURL para `https://api.telegram.org` deve possuir `CURLOPT_TIMEOUT` maximo de 1 segundo e `CURLOPT_CONNECTTIMEOUT` de 1 segundo para nao degradar a latencia do oraculo.
- **Segurança:** O token do bot e o chat ID nunca devem ser registrados em arquivos de log, respostas HTTP ou no versionamento Git.
- **Confiabilidade:** Falhas de conexao ou respostas com erro da API do Telegram (HTTP != 200) devem ser capturadas e silenciadas (graceful degradation), garantindo a entrega dos dados ao cliente pagador.
- **Formato:** Texto da mensagem enviado em formato HTML ou Markdown compativel com a Telegram Bot API.

## 8. Fluxos e UX
- **Fluxo Principal (Pagamento Confirmado):**
  1. Agente de IA envia header `X-Payment: 0x...` para endpoint protegido.
  2. Grid402 valida o recibo on-chain na Base Mainnet e retem a trava atomica contra replay attack.
  3. `grid402_validate_payment` confirma pagamento valido (`valid = true`, `sandbox = false`).
  4. O sistema dispara a requisicao cURL POST assincrona para `api.telegram.org/bot{TOKEN}/sendMessage`.
  5. Mantenedor recebe notificacao no Telegram:
     ```text
     Nova Compra no Grid402!
     
     Servico: Receita Federal CNPJ
     Valor: 0.0020 USDC
     Rede: Base Mainnet
     Data: 2026-11-08 19:35:00 UTC
     Transacao: https://basescan.org/tx/0x...
     ```
  6. Oraculo assina criptograficamente e entrega os dados com HTTP 200 ao agente.

- **Fluxos Alternativos e Excecoes:**
  - Token nao configurado no `.env`: Funcao retorna imediatamente sem fazer nenhuma requisicao externa.
  - Requisicao Sandbox / Sepolia: Nao envia mensagem.
  - Falha de conexao com o Telegram: Ignora erro e conclui a resposta HTTP 200 do servico normalmente.

## 9. Metricas de sucesso
- 100% dos pagamentos reais na Base Mainnet geram notificacao quando o bot estiver configurado.
- 0 ms de atraso perceptivel para o cliente (mantendo a resposta total da API dentro da meta).
- Zero quebras ou interrupcoes na API em cenarios de timeout ou indisponibilidade do Telegram.

## 10. Riscos, dependencias e premissas
- **Dependencia externa:** API do Telegram (`api.telegram.org`). Mitigado com timeout rigido de 1 segundo e captura silenciosa de erros.
- **Rate limiting do Telegram:** O Telegram limita bots a 30 mensagens por segundo em chats individuais, margem amplamente segura para o volume atual.

## 11. Questoes em aberto
- [x] Eventos: Apenas pagamentos reais na Base Mainnet.
- [x] Destino: Chat privado pessoal com o bot.
- [x] Validacao do Chat ID do Thiago (Chat ID 9266445588 obtido e testado com sucesso).

