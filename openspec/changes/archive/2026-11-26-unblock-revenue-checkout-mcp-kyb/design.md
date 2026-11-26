# Design

## Context

Esta change implementa o **Bloco 1 (RF01 a RF11)** do PRD aprovado (`openspec/prds/revenue-unblock-and-real-telemetry.md`). Veja `proposal.md` para a motivação completa.

Restrições técnicas do ambiente Grid402:
- Todo o código de produção reside em `public_html/` (LiteSpeed PHP 8.1+, Zero-DB com arquivos JSON protegidos por `flock(LOCK_EX)` e escrita atômica `grid402_atomic_write`).
- A carteira EVM canônica é `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` e a escala canônica do Session Pass é `v2_5000` (`1 crédito = 0.0050 USDC = 5.000 raw units`).
- Os pacotes externos (`mcp/`, `sdk/`, `python/`) não são editados nesta change; a paridade de 27 ferramentas MCP (`v2.0.0`) e a proibição de `&` nos 4 arquivos de documentação precisam continuar passando 100% na suíte de homologação.

## Goals / Non-Goals

**Goals:**
- Garantir coerência total entre o que é anunciado na UI/manifestos e o que liquida com sucesso em produção (Solana oculta atrás da flag `GRID402_SOLANA_X402_ENABLED=false`).
- Eliminar vazamentos de protocolo (`x402 v1 resource`, `x402 v2 accepted deepEqual`, CORS `/api/v1/*` e `/mcp`) e riscos de perda de transação no Top-Up Web3 da Home.
- Reduzir drasticamente a fricção de compra e ativação em 3 trilhas: **Pix BRL** (`/dossie-kyb` com amostra real + tier `micro` R$ 4,90 + fim do auto-disparo 250ms), **Cartão USD** (CTA RapidAPI), e **Agentes/Devs EN** (`MCPTRY50` em `429`/`402`, auto-trial 1/dia no `/mcp` e persistência unificada do Session Pass).

**Non-Goals:**
- Refatoração da telemetria de bots, `wallets_connected`, `payment_failures.json`, `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` e tradução completa de `llms.txt` (escopo exclusivo da Change 2: `real-telemetry-admin-and-discovery-hardening`).
- Edição/publicação de `grid402-mcp` no npm ou SDKs Node/Python.
- Ativação on-chain de Solana ou integração de novo gateway Stripe.

## Decisions

### 1. Ocultação de Solana orientada pela flag `GRID402_SOLANA_X402_ENABLED`
- **Decisão:** Centralizar o controle via `grid402_solana_x402_enabled()` (já existente em `facilitator.php`, padrão `false`) e adicionar um helper leve `grid402_public_networks()` (ou parâmetro `$includeDisabled = false` em `grid402_supported_networks()`) para que `pass.php`, `catalog.php`, `index.php`, `x402.php` e `mcp.php` renderizem apenas as redes EVM ativas (5 redes on-chain / 3 gasless PayAI: Base, Polygon, Arbitrum) quando Solana estiver desligada. Os manifestos estáticos em `public_html/` (`pricing.json`, `openapi.json`, `llms.txt`, `llms-full.txt`, `.well-known/ai-agent.json`, `.well-known/ai-plugin.json`, `.well-known/mcp.json`) e chaves de `assets/js/i18n.js` são atualizados para não anunciar Solana ativa.
- **Alternativa descartada:** Remover todo o código Solana de `multichain.php` e `facilitator.php`. Rejeitada porque o código de verificação SPL já existe e foi testado — mantê-lo protegido pela flag permite reativação limpa assim que o operador financiar `~0,005 SOL` e criar a ATA USDC.

### 2. Conformidade x402 v1 (`resource`) e v2 (`paymentPayload.accepted`) antes do PayAI
- **Decisão:**
  - Em `public_html/x402.php` (`grid402_validate_payment`), sempre prefixar `$resourceUrl` com `GRID402_CANONICAL_DOMAIN` quando `$resourceUrl` começar com `/` (caminho relativo), garantindo URI absoluta válida para x402 v1 (`https://grid402.online/api/v1/<service>`).
  - Em `public_html/facilitator.php` (`grid402_build_payment_requirements` e `grid402_verify_exact_payment`), normalizar o próprio `$paymentPayload['accepted']` antes de serializar o POST para PayAI: (a) converter `accepted.amount` inteiro/numérico para string via `grid402_normalize_uint_string`, (b) substituir `accepted.network` pelo CAIP-2 canônico `$netSpec['v2']` (`eip155:8453`, `eip155:137`, `eip155:42161`), e (c) espelhar chaves whitelisted presentes em `accepted.extra` (`assetTransferMethod` quando `'eip3009'` ou `'permit2'`, e `spenderAddress` válido em `permit2`) para dentro de `paymentRequirements.extra` mantendo ambos os objetos idênticos para `deepEqual`.
- **Alternativa descartada:** Aceitar apenas clientes que enviam strings canônicas exatas. Rejeitada porque clientes terceiros e SDKs de agentes frequentemente enviam alias `"base"`, `amount` numérico ou `assetTransferMethod: "eip3009"`.

### 3. Top-Up Web3 da Home com função única de cópia e retry resiliente
- **Decisão:** Renomear a função/botão do modal Web3 (`copyWeb3ModalAddress()` / `btn-copy-modal-oracle`) para descolidir de `copyOracleAddress()` / `btn-copy-oracle` do Top-Up manual. Em `startWeb3Topup()`, replicar o padrão comprovado de `pass.php` (até 5 tentativas com backoff progressivo de 2s, 3s, 4s, 5s, 6s contra `/api/v1/pass-emitir`), guardando o `txHash` em escopo superior para que o bloco `catch` preserve o link do explorer na mensagem de status e preencha `#manual-pass-tx-input` automaticamente.
- **Alternativa descartada:** Redirecionar o Top-Up da Home para `/pass`. Rejeitada porque quebraria links profundos `#topup` e testes existentes da Home.

### 4. Tier Pix `micro` (R$ 4,90 / 50 créditos) e política de e-mail no Mercado Pago
- **Decisão:**
  - Adicionar a chave `'micro'` no início de `grid402_mp_packages()` em `public_html/services/mercadopago.php`: `amount_brl = 4.90`, `raw_usdc_units = 250000`, `credits = 50`, `ttl_days = 90`, `label = 'Micro Pass (5 Dossiês)'`. Como `raw_usdc_units / 5000 = 50`, preserva 100% a matemática `v2_5000` sem alterar a emissão/normalização de passes.
  - Em `grid402_mp_create_pix()`, substituir o fallback `'pagador@grid402.online'` por um e-mail neutro fora do domínio do vendedor (ex.: `'checkout+' . substr($orderId, -8) . '@users.noreply.invalid'` ou domínio neutro configurado via env `GRID402_MP_FALLBACK_PAYER_EMAIL` nunca igual ao domínio canônico) quando chamadas API legadas omitirem e-mail, enquanto a UI de `/pass` e `/dossie-kyb` passa a exigir e-mail válido no formulário Pix com validação amigável antes do `POST`.
  - Em `pass.php`, substituir o `setTimeout(() => startMpPix(...), 250)` por destaque visual + scroll suave para o card do pacote solicitado (e foco no campo de e-mail se vazio), eliminando cobranças não solicitadas.
  - Em `pass.php` e `dossie_kyb.php`, adicionar variável server-side `$mpCheckoutEnabled = $isSandbox || (grid402_mp_access_token() !== '')` para renderizar estado claro caso o gateway BRL esteja sem credencial.
- **Alternativa descartada:** Exigir e-mail obrigatório com HTTP 400 na API `/api/v1/pass/mp-pix`. Rejeitada porque quebraria retrocompatibilidade de testes existentes que chamam `mp-pix` apenas com `{"tier": "starter"}` sem `email`.

### 5. Helper Unificado de Persistência de Session Pass (`public_html/assets/js/pass_storage.js` ou partial inline leve)
- **Decisão:** Criar um script compartilhado pequeno (`public_html/assets/js/pass_storage.js`, carregado por `index.php`, `pass.php`, `dossie_kyb.php` e `payment_dashboard.php`) expondo `window.Grid402PassStorage = { save(token), load(), clear() }`:
  - `save(token)`: valida prefixo `pass_g402_`, grava em `localStorage['grid402_active_pass_token']` (e sincroniza as chaves legadas para zero regressão) + grava cookie `g402_pass_token` (`path=/; max-age=2592000; SameSite=Lax`, com `; Secure` em HTTPS).
  - `load()`: lê na ordem `localStorage['grid402_active_pass_token']` -> cookie `g402_pass_token` -> `grid402_session_pass` -> `grid402_last_pass_token`.
  - Em `payment_dashboard.php`, também aceitar `$_COOKIE['g402_pass_token']` no PHP SSR quando `$_GET['token']` estiver vazio, com fallback JS via `Grid402PassStorage.load()`.
  - No Playground da Home (`index.php`), adicionar o 4º botão de modo (`🔑 Session Pass`) com input pré-preenchido via `Grid402PassStorage.load()`, enviando `X-Payment: <token>` e exibindo créditos restantes do header `X-Pass-Credits-Remaining`.

### 6. Amostra Real Completa em `/dossie-kyb`
- **Decisão:** Incluir em `public_html/dossie_kyb.php` um payload JSON estático fidedigno de demonstração (CNPJ público de exemplo institucional — Banco do Brasil `00.000.000/0001-91` com campos estruturais idênticos aos retornados por `/api/v1/dossier`: `verdict`, `cnpj_data`, `simples`, `sanctions`, `_meta`, `_oracle_proof` claramente marcado como amostra ilustrativa), renderizado pelo mesmo `renderFullReport()` com selo visual **"AMOSTRA ILUSTRATIVA — Consulte seu CNPJ abaixo"** e botão funcional de **Imprimir / Salvar PDF de Amostra**.
- **Alternativa descartada:** Liberar consulta real ao vivo gratuita sem pagamento. Rejeitada pelo usuário no Grill-me para evitar abuso de cota/scraping e manter o `/api/v1/dossier` 100% protegido.

### 7. Auto-Trial Diário (`mcp_sandbox`) e Negociação de `protocolVersion` no `/mcp` Hospedado
- **Decisão:**
  - Em `public_html/mcp.php`, suportar array ordenado `MCP_SUPPORTED_PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05']`; no `initialize`, ler `$params['protocolVersion']` e retornar a mesma versão se suportada (ou `'2024-11-05'` se omitida / `'2025-03-26'` se futura desconhecida).
  - Adicionar arquivo dedicado `private_data/mcp_trial_rates.json` sob `flock(LOCK_EX)` controlando **1 chamada gratuita a cada 24h por IP (HMAC-SHA256)** exclusivamente para ferramentas de dados (nunca `grid402_pass`). Quando `$effectiveProof === ''` e o IP ainda tiver auto-trial disponível, o servidor executa o micro-serviço como sandbox, registra telemetria com `payment_source: 'mcp_sandbox'`, anexa `_trial_notice` em inglês destacando `MCPTRY50` (`/pass?promo=MCPTRY50`) e retorna HTTP 200 sem erro. A partir da 2ª chamada sem prova no mesmo dia, retorna `mcp_payment_required_result` em inglês colocando `MCPTRY50` como passo #1.
- **Alternativa descartada:** Reutilizar `sandbox_rates.json` (3 req/dia) no auto-trial do `/mcp`. Rejeitada para manter isolamento entre tráfego sandbox explícito (`X-Payment: demo`) e auto-trial anônimo do `/mcp` (`mcp_sandbox`), além de limitar exposição de chamadas não autenticadas no `/mcp` a 1/dia por IP.

## Risks / Trade-offs

- **[Testes existentes que verificam 4 redes no `accepts[]` ou 5 links na navbar]** -> Auditar e atualizar asserções afetadas em `tests/` (ex.: `tests/test_maturity_and_m2m_controls.php`, `tests/test_e2e_full.php` e testes de navegação/Mercado Pago) mantendo cobertura explícita para ambos os estados da flag `GRID402_SOLANA_X402_ENABLED`.
- **[Chaves de tradução em `assets/js/i18n.js` ainda citando Solana ou 3 pacotes BRL]** -> Atualizar todas as entradas relevantes de `i18n.js` em EN e PT-BR no mesmo pacote para evitar texto divergente pós-hidratação JS.
- **[Abuso do auto-trial 1/dia no `/mcp` por scanners de diretórios]** -> Os 2 crawlers de diretórios (`65.203.xxx.xxx` e `2a02:3102:...`) só consomem no máximo 1 chamada gratuita a cada 24h cada (2 chamadas/dia no total, servidas em grande parte via cache), um impacto praticamente zero frente ao ganho de conversão em clientes MCP reais (Cursor/Claude/Glama).
- **[Fallback de e-mail Pix fora do domínio `grid402.online` em produção]** -> A UI exige e-mail válido real do comprador, reduzindo ao mínimo chamadas sem e-mail; ainda assim, o operador deve validar 1 Pix real de R$ 4,90 ou R$ 12 após o deploy.

## Migration Plan

1. Implementar todas as tarefas da Change 1 em `public_html/` + testes em `tests/`.
2. Executar a bateria completa de homologação (PHP + Node MCP + `openspec validate --all --strict`).
3. Gerar `dist/grid402_hostinger_deploy.zip` via `scripts/package_deploy.ps1` para deploy na Hostinger (nenhuma migração de banco ou de arquivos em `private_data/` é necessária — `mcp_trial_rates.json` é criado automaticamente sob demanda).
4. **Rollback:** restaurar a versão anterior de `public_html/` (nenhum esquema de arquivo existente em `private_data/` sofre quebra retroincompatível).
