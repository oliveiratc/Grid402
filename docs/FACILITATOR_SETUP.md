# Grid402 — Facilitador x402: Homologação (Base Sepolia) e Produção (Base Mainnet)

**Decisões registradas**
- (c) Rewards: **mantida aprovação manual** em `/growth_admin`. Nenhuma emissão automática de créditos.
- (a) Facilitador público: homologação com `x402.org/facilitator` (testnet, grátis, v2), produção **mantida com pagamento direto on-chain (tx_hash) e `GRID402_FACILITATOR_MODE=disabled`** — decisão do mantenedor em 2026-11-13 (sem fee ao serviço; agentes pagam com carteira própria). PayAI/CDP não foram habilitados.
- (b) Bazaar: listing só pode ser afirmado após settlement com facilitador que suporte a extensão `bazaar`; até lá, apenas submissão manual a diretórios (já feita pelo mantenedor) + e-mail enviado à PayAI (aguardando resposta).
  - Nota: mesmo com facilitator desabilitado, o catálogo do Bazaar (via PayAI) exige **settles via PayAI em Mainnet** para catalogar. Um caminho real de homologação Mainnet sem custo para o Grid402: o oráculo tem ~1.000 créditos grátis PayAI; um único settle de 0.0005 USDC por um agente/comprador real pode ser suficiente para indexação — decidir quando isso fizer sentido (o comprador paga; o Grid402 gasta 1 crédito grátis).

---

## 1. O que já está implementado (código, 2026-11-13)

- `GRID402_X402_NETWORK` — `base` (padrão, Mainnet, comportamento inalterado) | `base-sepolia` (opt-in de homologação).
- `GRID402_FACILITATOR_VERIFY_PATH` / `GRID402_FACILITATOR_SETTLE_PATH` — caminhos configuráveis (default `/v2/x402/verify` e `/v2/x402/settle` do CDP).
- `grid402_facilitator_auth_mode()` — `override` (testes) | `cdp` (credenciais presentes) | `public` (facilitador público sem credenciais).
- Sem credenciais CDP, **nenhuma chamada envia cabeçalho Authorization** — segredos nunca são exigidos nem vazam em telemetria.
- `examples/sepolia_x402_buyer.mjs` — buyer de homologação (EIP-3009), chave só via `GRID402_TEST_PRIVATE_KEY`.
- Testes: `php tests/test_facilitator_public.php` (17/17) e `php tests/test_x402_exact_settlement.php` (31/31).

---

## 2. Homologação em Base Sepolia (faça antes de Mainnet)

### 2.1 Carteira de TESTE

Crie uma carteira nova/descartável só para homologação (ex.: MetaMask → adicionar rede Base Sepolia). **Nunca** a carteira oráculo, **nunca** chaves de produção.

### 2.2 Faucets (gratuitos)

1. **USDC Sepolia** em `0x036CbD53842c5426634e7929541eC2318f3dCF7e`: Circle Faucet → https://faucet.circle.com (selecionar rede **Base Sepolia**).
2. **Sepolia ETH** (se precisar para interagir com a chain): https://www.coinbase.com/faucets/base-ethereum-sepolia-faucet

### 2.3 Instância de homologação local (não toca a produção)

Crie `private_data/.env` (fora do deploy) ou exporte as variáveis antes de subir o servidor:

```bash
GRID402_X402_NETWORK=base-sepolia
GRID402_FACILITATOR_MODE=settle          # ou verify_only para teste sem liquidação
GRID402_FACILITATOR_URL=https://x402.org/facilitator
GRID402_FACILITATOR_VERIFY_PATH=/verify
GRID402_FACILITATOR_SETTLE_PATH=/settle
GRID402_CDP_API_KEY_ID=
GRID402_CDP_API_KEY_SECRET=
```

Suba o servidor embutido com o roteador de testes:

```powershell
php -S 127.0.0.1:8090 -t public_html tests/router.php
```

### 2.4 Execute o buyer

```powershell
$env:GRID402_TEST_PRIVATE_KEY = '0xSUA_CHAVE_DE_TESTE'
$env:GRID402_TARGET_URL = 'http://127.0.0.1:8090/api/v1/crypto'
node examples/sepolia_x402_buyer.mjs
```

Resultado esperado: HTTP 402 → assinatura → reenvio → **HTTP 200** com `PAYMENT-RESPONSE` (recibo do facilitador: `success`, `transaction`, `network=eip155:84532`).

Se `verify_only`: HTTP 200 sem `transaction` (autorização validada, **não** liquidada — comportamento esperado; use `settle` para liquidação).

---

## 3. Produção em Base Mainnet — matriz de decisão

| Facilitador | v2 Base Mainnet | Bazaar | Credenciais | Custo |
|---|---|---|---|---|
| Coinbase CDP (`https://api.cdp.coinbase.com/platform`) | ✅ | não confirmado | obrigatórias (JWT) | ver docs CDP |
| PayAI (`https://facilitator.payai.network`) | ✅ (`eip155:8453`, exact) | ✅ extensão `bazaar` | nenhuma | **~0.00166 USDC por settle** (pricing 2026-11-13) |
| Mogami (`https://facilitator.mogami.tech`) | só v1 | não | nenhuma | grátis (só v1) |
| x402.org facilitator | ❌ (testnet) | não | nenhuma | grátis, só Sepolia |
| Dexter (`https://dexter.cash/facilitator`) | inconclusivo (404 no probe) | ? | nenhuma | — |

**Atenção ao custo da PayAI:** com preço de endpoint de US$ 0.0005, uma taxa de settle de ~US$ 0.00166 torna cada chamada x402 exata deficitária (~3,3× a receita). Se ativar PayAI, revise preços ou concentre em Session Pass / volumes. A alternativa v2 sem conta e sem taxa confirmada hoje seria a CDP com cadastro (perfil C no `.env.example`) ou renegociação com um facilitador.

**Recomendação:** teste em Sepolia primeiro (perfil Sepolia). Só depois escolha entre:
- **PayAI** + revisão de preços (única opção v2 pública com `bazaar`);
- **CDP** com credenciais da Coinbase Developer Platform (KYT/OFAC incluídos; nunca no repo);
- manter `disabled` e Tx-hash direto (atual).

---

## 4. Bazaar — como confirmar de verdade

O listing no x402 Bazaar é mediado pelo **facilitador**, não por deploy de código. Para afirmar presença:

1. Execute um settle real (perfil Sepolia ou Mainnet) com um facilitador que suporte a extensão `bazaar` (hoje: PayAI).
2. Inspecione os headers de resposta do facilitador: procure `EXTENSION-RESPONSES` (ou equivalente) com confirmação de inclusão no Bazaar.
3. Verifique se o recurso aparece na busca do Bazaar para `grid402` / `oracle`.
4. Só então atualize a comunicação pública (README, `/llms.txt`, docs). Enquanto não houver essa confirmação, o Grid402 está **não listado** — o manifesto `/.well-known/x402.json` e a extensão `bazaar` no 402 são apenas metadados de preparação.

### Submissões manuais (não dependem de facilitador)

Veja `docs/AGENT_MARKETPLACE_SUBMISSIONS.md` (textos prontos): Agentic Market, PayAPI Market, Smithery, Glama, mcp.so, PulseMCP. Essas submissões melhoram descoberta, mas não substituem a confirmação do Bazaar via facilitador.

---

## 5. Rewards (item c) — decisão

Aprovado manter **100% manual**: rewards ficam `pending` até aprovação em `/growth_admin`. Não há emissão automática e nenhuma será adicionada sem decisão explícita e validação anti-fraude.

---

## 5.1 Cron de reconciliação on-chain

Cron criado no Hostinger (conta `u637629962`, uid `2URlitBU3F`):

- Agenda: `30 4 * * 0` (domingo, 04:30) — weekly snapshot de 10.000 blocos (~5,5h) via `services/onchain_stats.php`
- Saída: descartada (`>/dev/null`); resultado fica em `private_data/onchain_stats_cache.json` (não vai para o deploy)
- Verificar: `/growth_admin` → seção "Reconciliação On-Chain" mostra o snapshot atualizado após a primeira execução (domingo 2026-11-15)

---

## 6. Checklist de produção (em ordem)

- [x] Homologação Sepolia concluída com HTTP 200 + recibo `PAYMENT-RESPONSE` (2 settles via PayAI, 2026-11-13)
- [x] Facilitador de Mainnet escolhido: **Tx-hash direto (mantido desabilitado)** — decisão do mantenedor 2026-11-13
- [ ] Resposta da PayAI ao e-mail de catalogação (aguardando)
- [ ] Settle real em Mainnet com comprador habilitado (se/quando a PayAI confirmar indexação ou se feito para contagem de catálogo)
- [ ] Cache do Hostinger purgado após mudança de env/build
- [ ] Settle real em Mainnet com valor mínimo (0.0005 USDC) verificado via `PAYMENT-RESPONSE`
- [ ] Listing Bazaar confirmado (ou não) via `EXTENSION-RESPONSES`
- [ ] Documentação atualizada com o resultado real

**Itens que nunca faremos sem autorização escrita:** mover fundos, emitir rewards automaticamente, usar chaves de produção em scripts de teste, afirmar listing no Bazaar sem evidência do facilitador.
