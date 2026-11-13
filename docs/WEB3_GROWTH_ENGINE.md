# Grid402 Web3 Growth Engine — Fase 1 (P2P Activation)

**Escopo implementado:** infraestrutura de conversão de agentes autônomos, pagamentos por requisição (Pay-per-Request) e pré-pago (Agent/Session Pass), aquisição de builders, programa de afiliação, reputação pseudônima e configurações administrativas.

## O que já está ativo

### 1. Web3 Conversion Engine
- Métrica de carteira única, conversão 1ª compra e 2ª compra
- Retenção / recorrência (>1 transação)
- Conversão desde aquisição
- Integração entre `grid402_metrics` e `growth.json`

### 2. Web3 Payment Experience
- Dois produtos distintos:
  - **Pay-per-Request:** x402 exato na Base (EIP-3009) ou tx_hash direto
  - **Agent Pass:** créditos pré-pagos com sessão HMAC
- Dashboard de pagamentos: `/payment-dashboard`
- 1 CRUD de balanceamento usando `X-Pass-Credits-*`

### 3. Agent Hub
- Central de integração: `/agenthub`
- Exemplos cURL, MCP, budget-aware, regras de produção

### 4. Agent Growth Program
- `/growth` — registro de builders, referências por carteira Base
- Pending validation e audit trail
- 3 tiers: Starter (10 créditos), Builders (100 créditos), Referrals (25 créditos)

### 5. On-chain Reputation
- `/reputation` — tabela pseudônima de builders ativos
- Posições públicas opt-in (`builders` listadas no growth.json)
- Assinatura HMAC de participação, sem PII

### 6. Integration & configuration
- `/admin?action=...` (ações Growth) via `admin_stats` payload
- `/growth_admin` — painel de administradores
- `growth.json` — única fonte de verdade
- `grid402_growth_*` — funções do módulo

## Como ativar em produção

1. Consiga um pass exato com facilitador Coinbase/CDP:
   - Defina `GRID402_FACILITATOR_MODE=settle` + credenciais
   - Valide testnet (Base Sepolia) antes de mainnet
2. Rode `python tests/test_e2e_full.php` (atenção: pode emitir chamadas)
3. Configure administradores: recompensa, refluxo máximo/dia, auto-issue
4. Crie uma carteira de referência e teste o fluxo em `/growth`
5. Monitore via `/admin?api=growth` (polling)

## O que falta para 1.0

- [ ] Facilitador `settle` habilitado em produção e monitorado
- [ ] Rewards reais (txhash + emissão de pass) sem intervenção manual
- [ ] Infra de 6 índices: `charge`, `settle`, `refund`, `dispute`, `complaint`, `withdraw`
- [ ] Métrica de carteiras únicas vs. checkout completion rate
- [ ] Testes de carga: 1000 req/min com wallets distintas
- [ ] Hex por binding de contrato (`builders[0]['reputation_score']` = 0 agora)

## Nunca fazemos

- Nunca emitimos créditos sem validação administrativa
- Nunca publicamos informações pessoais de builders sem consentimento
- Nunca autorizamos movimentação de fundos sem confirmação explícita do dono
