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

## O que falta para o Bazaar (ações suas)

1. **Enviar email para a PayAI** pedindo catalogação/verificação do recurso (rascunho abaixo). É o único canal confiável de resposta sobre catalogação.
2. **Ou** fazer um settle em Mainnet (precisa de um comprador com USDC na Base Mainnet apontando para `https://grid402.online/api/v1/crypto` — custo do comprador: 0.0005 USDC; custo do oráculo: créditos PayAI, hoje `available`/1.000 grátis).

## Rascunho de email para info@payai.network

> Subject: Bazaar listing — Grid402 (grid402.online) — settlement feito, não catalogou
>
> Hi PayAI team,
>
> I'm operating an x402 pay-per-call service (grid402.online — Brazilian public/economic data for autonomous agents) on Base (Mainnet payTo: 0xa31c31069c43ac1c0075a93c325b0dcd7744356c) and I'd like to list it in your Bazaar catalog.
>
> On Base Sepolia I successfully completed two x402 `exact` settles via your facilitator, each with a fully-formed `bazaar` extension echoed in the payload and an absolute HTTPS `resource.url`:
> - tx 0xe5785e3c2e76c6a0547e948a98c8ee549ea82da963fcf5a897ea21ae7dcd24ea (resource http://127.0.0.1:8090/api/v1/crypto — local staging)
> - tx 0xb2e3c7e2346d80efb1ecc863eed82fb25dd96d42d3691d8022f395b5d90abc3e (resource https://grid402.online/api/v1/crypto)
>
> Your discovery endpoint `/discovery/resources?payTo=0xa31c31069c43ac1c0075a93c325b0dcd7744356c` returns `{"items":[],"total":0}` after these settles, and `/discovery/resources/https%3A%2F%2Fgrid402.online%2Fapi%2Fv1%2Fcrypto/stats` shows zero settlements.
>
> Could you please confirm (1) whether Sepolia settles feed your catalog at all, (2) what is required for our resource to be indexed (metadata, volume, manual review), and (3) whether you can index `https://grid402.online/api/v1/crypto` (22 x402 endpoints, tags: public-data, utility, x402, base, agents)?
>
> Our 402 advertises the bazaar extension in spec format (`info.input.type=http/method=GET`, `discoverable: true`, `output.type=json`).
>
> Thanks,
> Thiago — grid402.online

## Decisões mantidas

- Rewards: aprovação manual (decisão do mantenedor).
- Produção segue Mainnet (`eip155:8453`), sem `settle` habilitado no pior caso — nenhum funde real foi movido.
- Nenhuma emissão automática de créditos; nenhuma chave de produção nos testes (wallet de teste descartável).
