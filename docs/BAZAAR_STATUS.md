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

## O que falta para o Bazaar (ações suas) — atualizado 2026-11-13 23:05

**Situação atual:** produção roda `GRID402_FACILITATOR_MODE=settle` via PayAI (default do código); preços 10x; x402 linter OK (7/7). Bazaar (payTo=0xa31c...) segue com `items: total 0` e stats do recurso em `0` — Sepolia não catalogou nada; Mainnet ainda não teve nenhum settle.

**Passo 1 — Criar a conta merchant (grátis, único passo que depende de você):**
1. Abra https://merchant.payai.network/onboarding (portal merchant; cadastro/login com seu e-mail).
2. Vincule a carteira `0xa31c31069c43ac1c0075a93c325b0dcd7744356c` como payTo; siga o fluxo até ver os créditos livres (~1.000).
3. Reporte aqui quando ativa — o portal mostra o estado (incluindo se há revisão pendente).

**Passo 2 — Se o Bazaar não catalogar automaticamente com a conta ativa, enviar o e-mail reforçado (seção "Rascunho" abaixo) com as evidências de hoje:**
- 7/7 PASS no linter x402,
- dois settles Sepolia via facilitador,
- payTo com free-credits disponíveis,
- `/discovery/resources?payTo=0xa31c...` → `total: 0`.

**Passo 3 — Se necessário, comprar US$ 0,05 de USDC na Base e fazer 1 settle real:**
1. CEX BR (Mercado Bitcoin/Binance/Coinbase) → compre US$ 0,05 de USDC → saque **direto na rede Base** (não Ethereum; economia de gás). Total < US$ 0,10.
2. Rode o buyer que já existe no repo:
   ```powershell
   $env:GRID402_TEST_PRIVATE_KEY='0xSUA_CHAVE'
   $env:GRID402_TARGET_URL='https://grid402.online/api/v1/crypto'
   node examples/sepolia_x402_buyer.mjs
   ```
3. Confira o catálogo de novo: `Invoke-RestMethod "https://facilitator.payai.network/discovery/resources?payTo=0xa31c31069c43ac1c0075a93c325b0dcd7744356c"`.

**Nota:** o portal merchant é client-side (sem conteúdo estático útil) — não há como inspeção remota revelar o fluxo; só seu e-mail e clique funcionam nele.

## Rascunho de email para info@payai.network

> Subject: Bazaar listing request — Grid402 (grid402.online) — facilitator OK, resource not indexed
>
> Hi PayAI team,
>
> I operate Grid402 (https://grid402.online), an x402 pay-per-call hub of Brazilian public/economic data for autonomous agents — 22 endpoints across CNPJ, Simples Nacional, CEIS/CNEP sanctions, CEP/IBGE, ANBIMA banking calendars, forex, DNS/SSL/ASN. Base Mainnet, `payTo`: `0xa31c31069c43ac1c0075a93c325b0dcd7744356c`, `GRID402_FACILITATOR_MODE=settle` via your facilitator since 2026-11-13.
>
> Independent verification evidence (public):
> - Third-party x402 linter (https://x402lint.sebastiaan-ba3.workers.dev): **PASS on 7/7 sampled endpoints** (/api/v1/crypto, /api/v1/cnpj, /api/v1/compliance, /api/v1/sanctions, /api/v1/forex, /api/v1/pass, /api/v1/workdays) — 0 errors, HTTP 402 with correct `accepts` (eip155:8453, asset 0x833589..., amount 5000, payTo a31c...).
> - Two successful Sepolia `exact` settles via your facilitator (2026-11-13): tx 0xe5785e3c...eb2e478a, tx 0xb2e3c7e2...c90abc3e, HTTP 200 responses, on-chain receipts status 0x1, payer = 0xa592625ADbceB8E025Ac383F1a3Fd8EBB6a63F90.
> - Our 402 advertises the bazaar extension in spec format (`info.input.type=http/method=GET`, `discoverable: true`, `output.type=json`), `serviceName`/`tags`/`iconUrl` valid, absolute HTTPS `resource.url`.
> - `/discovery/resources?payTo=0xa31c...` returns `{"items":[],"total":0}` and the vendor resource stats endpoint shows `settlements: 0`.
>
> Free credits for our payTo are available per `https://facilitator.payai.network/free-credits?payTo=0xa31c...`, but nothing in your public docs explains what is required for cataloging. Questions:
>
> 1. Is a merchant account at https://merchant.payai.network required for cataloging? If so, is registration free and instant?
> 2. Do only Mainnet `exact` settles count toward your Bazaar? Sepolia settles filed under the same facilitator show zero in stats — is that expected?
> 3. What is needed so that https://grid402.online/api/v1/crypto (and the rest) appear in your Bazaar?
> 4. If there is a manual review step, can you start it now?
>
> Thanks,
> Thiago — grid402.online

## Decisões mantidas

- Rewards: aprovação manual (decisão do mantenedor).
- Produção segue Mainnet (`eip155:8453`), com facilitator `settle` habilitado via PayAI desde 2026-11-13 (default do código).
- Nenhuma emissão automática de créditos; nenhuma chave de produção nos testes (wallet de teste descartável).
- Antes deste commit, verifiquei de novo: `/discovery/resources?payTo=...` -> `total: 0`; stats do recurso -> `settlements: 0`.
