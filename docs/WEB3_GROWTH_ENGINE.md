# Grid402 Web3 Growth Engine — Fase 1 + Fase 2

**Escopo implementado:**
- **Fase 1:** infraestrutura de conversão de agentes autônomos, pagamentos por requisição (Pay-per-Request) e pré-pago (Agent/Session Pass), aquisição de builders, programa de afiliação, reputação pseudônima e configurações administrativas.
- **Fase 2:** reconciliação on-chain read-only, digest de faturamento por chamada, aviso de saldo baixo no Session Pass, gatilhos de conversão no payload 402, janela real de 24h no funil e hardening CSRF do painel Growth.

## O que já está ativo

### 1. Web3 Conversion Engine
- Métrica de carteira única, conversão 1ª compra e 2ª compra
- Retenção / recorrência (>1 transação)
- Conversão desde aquisição
- Integração entre `grid402_metrics` e `growth.json`
- Gatilhos de conversão no payload **402** (`conversion.starter_promo`, `conversion.session_pass`, `conversion.catalog`) — apenas informativos; não alteram requisitos de pagamento
- Janela deslizante de ~24h para `requests_24h` (antes era acumulador vitalício)

### 2. Web3 Payment Experience
- Dois produtos distintos:
  - **Pay-per-Request:** x402 exato na Base (EIP-3009) ou tx_hash direto
  - **Agent Pass:** créditos pré-pagos com sessão HMAC
- Dashboard de pagamentos: `/payment-dashboard`
- Saldo baixo: aviso visual no `/payment-dashboard` e headers `X-Pass-Low-Balance`, `X-Pass-Low-Balance-Threshold`, `X-Pass-Recharge` nas respostas 200 com créditos
- Digest por chamada (`_billing`): `service`, `price_usdc`, `payment_source`, `credits_debited/remaining`, `payer_last4`, `digest_id` (HMAC) — incluído no payload antes da assinatura `_oracle_proof`, mais header `X-Billing-Digest`

### 3. Agent Hub
- Central de integração: `/agent-hub` (301 a partir de `/agenthub`)
- Exemplos cURL, MCP, budget-aware, regras de produção

### 4. Agent Growth Program
- `/growth` — registro de builders, referências por carteira Base
- Pending validation e audit trail
- 3 tiers: Starter (10 créditos), Builders (100 créditos), Referrals (25 créditos)

### 5. On-chain Reputation
- `/reputation` — tabela pseudônima de builders ativos
- Posições públicas opt-in (`builders` listadas no growth.json)
- Assinatura HMAC de participação, sem PII

### 6. Reconciliação On-Chain (Fase 2, read-only)
- Serviço: `public_html/services/onchain_stats.php`
- Leitura pública via `eth_getLogs` de eventos `Transfer(USDC)` para a carteira oráculo
- Janela ajustável (até 60.000 blocos ≈ 33h), fatiada em chunks de 5.000 blocos
- Nós públicos com fallback: `GRID402_BASE_RPC_URL` → `base.llamarpc.com` → `1rpc.io/base`
- Nenhum endereço bruto é persistido: apenas contagens e últimos 4 hex de até 25 carteiras únicas
- Resultado em `private_data/onchain_stats_cache.json` (nunca vai para o deploy)
- Painel: `/growth_admin` → seção "Reconciliação On-Chain" (botão "Reconciliar agora", protegido por sessão admin + CSRF)
- CLI/cron: `php services/onchain_stats.php [window_blocks]` (ex.: `php services/onchain_stats.php 10000`)
- HTTP: `/services/onchain_stats.php?token=CRON_SECRET&blocks=10000`
- **Honesto sobre limites:** nós públicos rejeitam parte dos chunks (timeout/rate-limit). O snapshot marca `partial:true` com `chunks_failed/chunks_total` quando isso acontece; valores ficam subnotificados e o painel exibe o aviso. `rpc_ok:false` mantém o snapshot anterior.

### 7. Integration & configuration
- `/admin?api=growth` — payload Growth para o dashboard admin (sessão admin obrigatória)
- `/growth_admin` — painel de administradores (agora com CSRF próprio)
- `growth.json` — única fonte de verdade
- `grid402_growth_*` — funções do módulo

## Como ativar em produção

1. Consiga um pass exato com facilitador Coinbase/CDP:
   - Defina `GRID402_FACILITATOR_MODE=settle` + credenciais
   - Valide testnet (Base Sepolia) antes de mainnet
2. Agende o recon: `php services/onchain_stats.php 10000` (cron diário sugerido)
3. Configure administradores: recompensa, refluxo máximo/dia, auto-issue
4. Crie uma carteira de referência e teste o fluxo em `/growth`
5. Monitore via `/admin?api=growth` (polling) e `/growth_admin`

## O que falta para 1.0

- [ ] Facilitador `settle` habilitado em produção e monitorado
- [ ] Listing no x402 Bazaar **confirmado** (hoje só documentado/manifesto; sem `EXTENSION-RESPONSES` de facilitador não se pode afirmar presença)
- [ ] Rewards reais (txhash + emissão de pass) sem intervenção manual
- [ ] Teste em Base Sepolia autorizado pelo mantenedor
- [ ] Métrica de carteiras únicas vs. checkout completion rate
- [ ] Testes de carga: 1000 req/min com wallets distintas
- [ ] Spend caps em SDK/MCP (hoje existe apenas documentação/budget-aware no hub)

## Nunca fazemos

- Nunca emitimos créditos sem validação administrativa
- Nunca publicamos informações pessoais de builders sem consentimento
- Nunca autorizamos movimentação de fundos sem confirmação explícita do dono
- Nunca persistimos endereços brutos de pagadores na reconciliação on-chain (apenas últimos 4 hex)
