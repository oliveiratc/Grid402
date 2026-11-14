# PRD: Checkout Mercado Pago (Pix + Cartão) para Session Pass

**Status:** Em revisão
**Data:** 2026-11-14
**Autor:** Thiago Campos de Oliveira + Agente

## 1. Contexto e problema
Hoje, a aquisição de Session Passes (`pass_g402_...`) no Grid402 exige que o usuário possua USDC na rede Base Mainnet e uma carteira Web3 conectada. Para desenvolvedores, agências de automação (n8n, Make, Zapier) e empresas no Brasil que querem consultar CNPJ, CEP, Sanções CGU, Compliance 360 e Dias Úteis ANBIMA, essa barreira cripto impede a conversão (0 Session Passes ativos no painel `/admin`).

Integrar o Mercado Pago com **Pix transparente na própria tela** e **Cartão/Saldo via Checkout Pro** elimina a necessidade de carteira cripto e permite emitir tokens `pass_g402_...` em segundos após pagamento em BRL.

## 2. Objetivos
- Permitir compra de Session Passes (Starter, Pro e Fleet) em BRL via Mercado Pago sem exigir carteira Web3 ou USDC.
- Entregar o token `pass_g402_...` automaticamente na interface em até 10 segundos após a confirmação do pagamento (Pix ou Checkout Pro).
- Suportar modo sandbox/mock local e homologação quando `MP_ACCESS_TOKEN` ainda não estiver configurado no `.env`.
- Registrar todas as ordens e emissões com idempotência e proteção contra dupla emissão de token.

## 3. Não objetivos (fora do escopo)
- Envio automático de e-mail transacional com o token (o token é exibido na tela e pode ser copiado; e-mail fica para entrega futura).
- Assinatura recorrente/cobrança automática mensal no cartão.
- Conversão dinâmica USD/BRL por API externa na hora da compra (preços serão fixos em BRL).
- Uso de SDK oficial pesado do Mercado Pago via Composer (a integração será em PHP puro com cURL contra a API REST oficial do Mercado Pago).

## 4. Público-alvo e personas
- **Desenvolvedor / Integrador brasileiro (n8n, Python, Node.js):** quer testar e colocar consultas de CNPJ/CEP/Compliance em produção pagando via Pix em R$ 12–R$ 29, sem abrir conta em exchange.
- **Empresa / Agência de automação:** precisa de maior volume de créditos (Fleet) com opção de pagamento no cartão corporativo ou Pix.

## 5. Histórias de usuário
- Como desenvolvedor brasileiro, quero escolher o pacote Starter (R$ 12), Pro (R$ 29) ou Fleet (R$ 115) e gerar um QR Code Pix direto na página `/pass` para receber meu `pass_g402_...` assim que pagar.
- Como comprador com cartão de crédito ou saldo Mercado Pago, quero clicar em "Pagar com Cartão (Mercado Pago)" para concluir no Checkout Pro seguro do Mercado Pago e voltar para `/pass` com meu `pass_g402_...` emitido.
- Como operador do Grid402, quero que pagamentos aprovados via webhook ou consulta de status não emitam dois passes para o mesmo `payment_id` (idempotência estrita).

## 6. Requisitos funcionais
| ID | Requisito | Prioridade (Must/Should/Could) |
|----|-----------|--------------------------------|
| RF01 | Exibir os 3 pacotes com preços fixos em BRL na página `/pass` (e no bloco `#topup` da Home): **Starter** (R$ 12,00 · 4.000 req · 30d), **Pro** (R$ 29,00 · 10.000 req · 30d) e **Fleet** (R$ 115,00 · 60.000 req · 60d). | Must |
| RF02 | Endpoint `POST /api/v1/pass/mp-pix` cria pagamento Pix via API REST do Mercado Pago (`POST /v1/payments`), grava ordem pendente em `private_data/mp_orders.json` com `flock(LOCK_EX)` e retorna `order_id`, `qr_code`, `qr_code_base64`, `ticket_url` e `expires_at`. | Must |
| RF03 | Endpoint `POST /api/v1/pass/mp-checkout` cria preferência do Checkout Pro (`POST /checkout/preferences`) com `external_reference = order_id` e `back_urls` apontando para `/pass?mp_order=<order_id>`, retornando `init_point` / `sandbox_init_point`. | Must |
| RF04 | Endpoint `POST /api/v1/pass/mp-webhook` recebe notificações do Mercado Pago, valida assinatura HMAC (`x-signature` + `MP_WEBHOOK_SECRET` quando configurada), consulta `GET /v1/payments/{id}` no Mercado Pago e, se `status === 'approved'`, emite o `pass_g402_...` de forma idempotente vinculando `mp_payment_id` à ordem. | Must |
| RF05 | Endpoint `GET /api/v1/pass/mp-status?order_id=...` permite polling seguro do frontend: verifica a ordem local e, se ainda pendente com `mp_payment_id` (ou `payment_id` recebido via query no retorno do Checkout Pro), consulta o Mercado Pago em tempo real para emitir/retornar o token `pass_g402_...` assim que aprovado. | Must |
| RF06 | Modo Sandbox/Simulação (`GRID402_MP_SANDBOX=true` ou quando `MP_ACCESS_TOKEN` estiver vazio em ambiente local/dev): permite gerar QR Code Pix simulado e aprovar uma ordem de teste via endpoint/botão específico apenas em sandbox, sem nunca liberar em produção com token real ausente se sandbox estiver desligado. | Must |
| RF07 | Idempotência estrita: um `mp_payment_id` ou `order_id` aprovado nunca gera um segundo `pass_g402_...`; chamadas repetidas retornam o mesmo token já emitido. | Must |
| RF08 | Contabilizar vendas aprovadas em BRL/equivalente USDC nas métricas e painel `/admin` mantendo separação clara entre receita on-chain (Base USDC) e receita fiat (Mercado Pago BRL). | Should |

## 7. Requisitos não funcionais
- **Segurança:** `MP_ACCESS_TOKEN` e `MP_WEBHOOK_SECRET` lidos exclusivamente de `.env` (nunca expostos no frontend, logs ou respostas JSON). Validação de HMAC `x-signature` no webhook e verificação ativa do pagamento via `GET /v1/payments/{id}` antes de emitir qualquer passe.
- **Concorrência:** Persistência atômica em `private_data/mp_orders.json` usando `flock(LOCK_EX)` para evitar race condition entre webhook e polling simultâneo.
- **Compatibilidade:** PHP 8.x puro com cURL (sem novas dependências Composer), mantendo o fluxo Web3 USDC existente intacto.
- **Acessibilidade e UX:** Botão de copiar código Pix copia-e-cola com feedback visual, QR Code com `alt` descritivo, estados claros de "Aguardando pagamento", "Aprovado" e "Expirado/Erro", e botão de copiar token `pass_g402_...`.

## 8. Fluxos e UX
1. **Fluxo Pix Transparente (na tela `/pass`):**
   - Usuário escolhe o pacote (Starter/Pro/Fleet), opcionalmente informa e-mail para recibo no MP (ou usa padrão configurável), e clica em **Pagar com Pix**.
   - Frontend chama `POST /api/v1/pass/mp-pix` → exibe modal/painel com QR Code + código copia-e-cola + contagem regressiva.
   - Frontend faz polling em `GET /api/v1/pass/mp-status?order_id=...` a cada 3 segundos.
   - Assim que webhook ou polling confirma `approved`, a UI exibe o token `pass_g402_...`, créditos, validade e exemplo pronto de uso (`curl` / MCP).
2. **Fluxo Cartão / Saldo MP (Checkout Pro):**
   - Usuário clica em **Pagar com Cartão (Mercado Pago)**.
   - Frontend chama `POST /api/v1/pass/mp-checkout` e redireciona para `init_point`.
   - Ao concluir no Mercado Pago, usuário retorna para `/pass?mp_order=<order_id>&payment_id=<id>`.
   - `/pass` consulta `GET /api/v1/pass/mp-status` automaticamente e exibe o token `pass_g402_...` na tela.
3. **Fluxo de Erro / Indisponibilidade:**
   - Se `MP_ACCESS_TOKEN` não estiver configurado e `GRID402_MP_SANDBOX` for `false`, retorna HTTP 503 com mensagem clara orientando usar Web3 ou aguardar ativação.
   - Se o Pix expirar ou o pagamento for rejeitado, exibe botão para gerar nova cobrança.

## 9. Métricas de sucesso
- Tempo entre confirmação do Pix no Mercado Pago e exibição do `pass_g402_...` na tela ≤ 10 segundos.
- 0 emissões duplicadas de `pass_g402_...` para um mesmo `mp_payment_id` sob chamadas concorrentes de webhook + polling.
- 100% dos cenários da spec cobertos por testes automatizados PHP com transporte HTTP simulado.

## 10. Riscos, dependências e premissas
- **Dependência:** Usuário precisa gerar `MP_ACCESS_TOKEN` e `MP_WEBHOOK_SECRET` no Mercado Pago Developers e configurar a URL de webhook `https://grid402.online/api/v1/pass/mp-webhook` para ativar cobranças reais em produção.
- **Risco:** Usuário fechar a aba antes do polling concluir; mitigado exibindo aviso para não fechar a página durante o Pix e mantendo `mp_orders.json` consultável por `order_id`.

## 11. Questões em aberto
- Nenhuma pendência bloqueante; aguardando aprovação do PRD para gerar a change OpenSpec (`proposal`, `specs`, `design`, `tasks`).
