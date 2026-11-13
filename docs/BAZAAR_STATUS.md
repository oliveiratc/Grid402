# Grid402 — Bazaar x402: status real (2026-11-13)

## O que foi verificado

### ✅ Homologação Sepolia — APROVADA
- Wallet de teste financiada pelo faucet Circle: `0xa592625ADbceB8E025Ac383F1a3Fd8EBB6a63F90` (20 USDC Sepolia, saldo confirmado on-chain).
- Buyer EIP-3009 (`examples/sepolia_x402_buyer.mjs`) executou: 402 → assinatura → settle via **PayAI** → **HTTP 200**.
- Recibos on-chain (status `0x1`):
  - `0xe5785e3c2e76c6a0547e948a98c8ee549ea82da963fcf5a897ea21ae7dcd24ea`
  - `0xb2e3c7e2346d80efb1ecc863eed82fb25dd96d42d3691d8022f395b5d90abc3e` (advertise `resource.url = https://grid402.online/api/v1/crypto`)
- Bug de produção encontrado e corrigido no caminho: falha de pagamento produzia fatal error (`grid402_build_accepts` inexistente) — agora retorna JSON de erro estruturado (`accepts` extraído de helper único).

### ❌ Listing no Bazaar (PayAI) — NÃO CONFIRMADO
Duas sessões de settle com a extensão `extensions.bazaar` corretamente formada (e, na segunda, `resource.url` absoluto https + `discoverable: true`) **não** geraram entrada em:
- `GET https://facilitator.payai.network/discovery/resources?payTo=0xa31c31069c43ac1c0075a93c325b0dcd7744356c` → `{"items":[],"total":0}`
- Stats do recurso: `GET /discovery/resources/https%3A%2F%2Fgrid402.online%2Fapi%2Fv1%2Fcrypto/stats` → settlements `0` (o endpoint reconhece o recurso, mas não registra settle Sepolia).

Conclusão razoável: o catálogo PayAI não indexa settles de teste (Sepolia) e/ou exige indexação assíncrona/Mainnet; a documentação pública do Bazaar diz explicitamente que catalogação é **detalhe de implementação do facilitador** e não é garantida por verify/settle bem-sucedido.

## O que foi corrigido/melhorado no código

- `resource` do 402: `serviceName`, `tags` (5), `iconUrl` https absoluto — já anunciados.
- `extensions.bazaar`: `input.type=http`, `method`, `discoverable: true`, `output.type=json` — no formato esperado pelos validadores.
- `GRID402_X402_RESOURCE_URL` (opt-in env): sobrescreve apenas o `resource.url` do 402 (útil atrás de proxy reverso ou em homologação).
- Buyer ecoa a extensão no payload e imprime `EXTENSION-RESPONSES` quando presente.
- `extra.name/version` do USDC agora dependem da rede (`USD Coin` mainnet / `USDC` Sepolia — o Sepolia "USD Coin" estava errado e rejeitava as assinaturas).

## O que falta para o Bazaar (ações suas) — atualizado 2026-11-13 22:30

**Situação atual:** produção agora roda `GRID402_FACILITATOR_MODE=settle` via PayAI (padrão do código) e os preços são 10x maiores. Ainda assim, o Bazaar (payTo=0xa31c...) segue com `items: total 0` e `stats` do recurso em 0, porque **nenhum settle real em Mainnet aconteceu ainda**.

**Passo 1 — Obter USDC na Base Mainnet (mínimo necessário):**
1. Coinbase (ou qualquer CEX BR: Mercado Bitcoin, Binance, Coinbase) → comprar US$ 0,05 de USDC.
2. Saque direto **na rede Base** (não no Ethereum, para economizar gás), caindo na sua carteira de teste ou em carteira sua principal. Custo total < US$ 0,10.
3. Alternativa: trocar ETH por USDC diretamente na Base (gas ~zero).

**Passo 2 — UMA compra de teste real no seu endpoint (0.0050 USDC):**
```powershell
$env:GRID402_TEST_PRIVATE_KEY='0xSUA_CHAVE'           # qualquer carteira sua com USDC na Base
$env:GRID402_TARGET_URL='https://grid402.online/api/v1/crypto'
node examples/sepolia_x402_buyer.mjs
```
*Importante:* o script verifica o network do accept — funciona em Mainnet também; só precisa de USDC real.

**Passo 3 — Confirmar catalogação:**
```powershell
Invoke-RestMethod "https://facilitator.payai.network/discovery/resources?payTo=0xa31c31069c43ac1c0075a93c325b0dcd7744356c"
```
Esperado: `items` com seu recurso. Se seguir vazio, mande o e-mail abaixo (com a tx do Passo 2 como evidência).

**Passo 4 — Portal merchant (pode ser etapa obrigatória):**
https://merchant.payai.network — criar conta de comerciante é **grátis**; o campo `payTo` já mostra créditos disponíveis hoje, mas a catalogação pode depender de cadastro + revisão manual. Crie a conta e vincule a mesma carteira (0xa31c...).

**Nota:** isto não move posição econômica relevante — 0.0050 USDC é ~R$ 0,03. Trata-se apenas de provar a esteira completa do Bazaar em produção.

## Rascunho de email para info@payai.network

> Subject: Bazaar listing — Grid402 (grid402.online) — Mainnet facilitator, resource not indexed
>
> Hi PayAI team,
>
> I'm operating an x402 pay-per-call service (grid402.online — Brazilian public/economic data for autonomous agents, 22 endpoints) on the **Base Mainnet** with `payTo`: `0xa31c31069c43ac1c0075a93c325b0dcd7744356c`, settling via your facilitator (`https://facilitator.payai.network`, `/verify` + `/settle`, `exact` scheme).
>
> Production runs `GRID402_FACILITATOR_MODE=settle` through your facilitator since 2026-11-13. Production x402 prices are 0.0050–0.0300 USDC depending on the endpoint. The 402 advertises the bazaar extension in spec format (`info.input.type=http/method=GET`, `discoverable: true`, `output.type=json`), `serviceName`/`tags`/`iconUrl` valid, absolute HTTPS `resource.url`.
>
> Your discovery endpoint `/discovery/resources?payTo=0xa31c31069c43ac1c0075a93c325b0dcd7744356c` returns `{"items":[],"total":0}`, and `/discovery/resources/https%3A%2F%2Fgrid402.online%2Fapi%2Fv1%2Fcrypto/stats` shows `settlements: 0`. I have not yet executed a Mainnet settle (no USDC on Base in my wallet yet).
>
> Questions:
> 1. Is a merchant account on https://merchant.payai.network required for cataloging?
> 2. Are Mainnet `exact` settles the only settlements counted toward your catalog (Sepolia settles apparently are not — I have two Sepolia receipts with zero stats)?
> 3. What is needed so that https://grid402.online/api/v1/crypto (and the other 21 endpoints) appear in your Bazaar?
> 4. If a manual review is required, can you start it now?
>
> Thanks,
> Thiago — grid402.online

## Decisões mantidas

- Rewards: aprovação manual (decisão do mantenedor).
- Produção segue Mainnet (`eip155:8453`), com facilitator `settle` habilitado via PayAI desde 2026-11-13 (default do código).
- Nenhuma emissão automática de créditos; nenhuma chave de produção nos testes (wallet de teste descartável).
- Antes deste commit, verifiquei de novo: `/discovery/resources?payTo=...` -> `total: 0`; stats do recurso -> `settlements: 0`.
