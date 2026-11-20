# Design Técnico: Grid402 Revenue Engine v4

## 1. Arquitetura Multi-Chain `x402 v2 exact` via PayAI Facilitator

### 1.1 Ofertas no cabeçalho/body `HTTP 402` (`grid402_build_accepts`)
Quando `GRID402_X402_NETWORK === 'base'` (produção), `grid402_build_accepts(int $priceRawUnits)` passa a emitir 4 entradas canônicas compatíveis com `https://facilitator.payai.network/supported` (validadas on-chain em 2026-11-20):

| Ordem | Rede | CAIP-2 (`network`) | Asset (`USDC`) | `payTo` | `extra` |
|---|---|---|---|---|---|
| 0 | Base Mainnet | `eip155:8453` | `0x833589fcd6edb6e08f4c7c32d4f71b54bda02913` | `GRID402_WALLET_ADDRESS` | `{"name":"USD Coin","version":"2"}` |
| 1 | Polygon PoS | `eip155:137` | `0x3c499c542cef5e3811e1192ce70d8cc03d5c3359` | `GRID402_WALLET_ADDRESS` | `{"name":"USD Coin","version":"2"}` |
| 2 | Arbitrum One | `eip155:42161` | `0xaf88d065e77c8cc2239327c5edb3a432268e5831` | `GRID402_WALLET_ADDRESS` | `{"name":"USD Coin","version":"2"}` |
| 3 | Solana Mainnet | `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` | `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v` | `GRID402_SOLANA_WALLET_ADDRESS` | `{"feePayer":"<GRID402_PAYAI_SOLANA_FEE_PAYER>"}` |

- Em modo `base-sepolia`, mantém-se **exatamente 1 oferta** (`eip155:84532`) para retrocompatibilidade dos testes de testnet.
- `GRID402_PAYAI_SOLANA_FEE_PAYER` é configurável por env com default `CjNFTjvBhbJJd2B5ePPMHRLx1ELZpa8dwQgGL727eKww` (lido de `/supported` do PayAI).

### 1.2 Decodificação e Validação em `facilitator.php`
- **EVM (`eip155:8453`, `eip155:137`, `eip155:42161` e aliases `base`, `polygon`, `arbitrum`):**
  - Mantém validação estrita EIP-3009 (`payload.signature` hex + `payload.authorization{from,to,value,validAfter,validBefore,nonce}`).
  - Mapa de requisitos por rede resolve o contrato USDC esperado (`asset`) e `extra` (`name`, `version`) corretos para montar `paymentRequirements` enviado ao PayAI `/verify` e `/settle`.
  - Anti-replay atômico por `network:payer:nonce` (ou `payer:nonce` em Base para compatibilidade com nonces já gravados).
- **Solana (`solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` e alias `solana`):**
  - `grid402_decode_x402_payload()` reconhece `payload.transaction` (string base64 não vazia com tamanho mínimo de 64 bytes e máximo de 16 KB) quando `network` for Solana.
  - Validação local confere `scheme === 'exact'`, `network` Solana, `asset === GRID402_SOLANA_USDC_MINT`, `accepted.payTo === GRID402_SOLANA_WALLET_ADDRESS` e `(int) accepted.amount >= $requiredRawUnits`.
  - Anti-replay atômico via `solana:<sha256(payload.transaction)>` em `used_nonces.json` sob `flock(LOCK_EX)`.
  - Monta `paymentRequirements` Solana com `extra.feePayer` e delega verificação criptográfica da instrução SPL Transfer + liquidação ao PayAI `/verify` e `/settle`.

---

## 2. Novos Micro-Serviços Globais (`0.0050 USDC` / 1 crédito)

### 2.1 `public_html/services/extract.php` (`GET /api/v1/extract?url=...`)
- **Entrada:** `url` obrigatório (string até 2048 caracteres, esquema `http` ou `https`).
- **Proteção SSRF (defesa em profundidade):**
  1. `parse_url` exige `scheme` in `['http', 'https']` e `host` não vazio (proíbe credenciais `user:pass@`, portas fora de `80`/`443` ou host `localhost`).
  2. Resolve todos os registros IPv4/IPv6 via `dns_get_record(..., DNS_A | DNS_AAAA)` (fallback `gethostbynamel`). Se algum IP resolvido falhar em `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)`, bloqueia com `HTTP 400` (`BlockedTargetAddress`) **antes** de cobrar ou conectar.
  3. Desabilita `CURLOPT_FOLLOWLOCATION` automático; segue no máximo **3 redirecionamentos manuais**, reexecutando a validação SSRF em cada `Location` resolvido.
  4. `CURLOPT_RESOLVE` fixa o IP validado (mitiga DNS rebinding TOCTOU).
  5. Limite de download em `1.048.576` bytes (1 MB) via `CURLOPT_WRITEFUNCTION`, timeout total `5s`, timeout de conexão `2s`.
- **Conversão HTML → Markdown:**
  - `DOMDocument` com `LIBXML_NOERROR | LIBXML_NOWARNING` remove `<script>`, `<style>`, `<noscript>`, `<iframe>`, `<svg>`, `<nav>`, `<footer>`.
  - Extrai `title`, `meta[name=description]`, `og:description`, `link[rel=canonical]`, idioma (`html[lang]`), converte `h1..h6`, `p`, `li`, `blockquote`, `pre/code`, `a[href]` em Markdown limpo (truncado de forma segura em `max_chars` configurável até `25000`, default `12000`).
  - Extrai até 30 links absolutos normalizados e `word_count`.
- **Cache:** opcional de `600s` (10 min) por `sha256(normalized_url)` em `GRID402_PRIVATE_DIR`.

### 2.2 `public_html/services/email_verify.php` (`GET /api/v1/email-verify?email=...`)
- **Entrada:** `email` obrigatório (até 254 caracteres).
- **Verificações determinísticas (sem porta SMTP 25):**
  1. Validação sintática RFC (`filter_var(..., FILTER_VALIDATE_EMAIL)` + checagem de parte local e domínio).
  2. Normalização do domínio (IDN/lowercase), extração da parte local para detectar e-mails genéricos/role-based (`info`, `contato`, `admin`, `support`, `noreply`, `sales`, `financeiro`, etc.).
  3. Detecção de provedores gratuitos (`gmail.com`, `outlook.com`, `hotmail.com`, `yahoo.com`, `icloud.com`, `uol.com.br`, `bol.com.br`, `terra.com.br`, `proton.me`, `protonmail.com`, etc.).
  4. Base embutida curada (~120+) de domínios descartáveis/temporários (`mailinator.com`, `guerrillamail.com`, `10minutemail.com`, `yopmail.com`, `temp-mail.org`, `trashmail.com`, `sharklasers.com`, etc.).
  5. Consultas DNS reais: `MX` (`dns_get_record(..., DNS_MX)`), fallback `A/AAAA` (RFC 5321), e registros `TXT` no domínio raiz (`v=spf1`) e `_dmarc.<domain>` (`v=DMARC1`).
  6. Cálculo de `deliverability_score` (`0`–`100`) e `verdict` determinístico:
     - `INVALID`: sintaxe inválida ou sem `MX` e sem `A/AAAA`.
     - `RISKY`: domínio descartável (`disposable: true`), sem `MX` (apenas fallback `A`), ou `role_based` sem SPF/DMARC.
     - `VALID`: sintaxe válida + `MX` ativo + não descartável.
- **Zero-PII:** retorna `domain`, `local_part_type` (`personal`/`role_based`), `email_hash_sha256` (hash do e-mail normalizado) e **nunca** grava o e-mail em claro em arquivos de cache (o cache de 1h guarda apenas a postura DNS do `domain`).

---

## 3. Sandbox Rate Limit (`3 req/dia` por IP)
- `config.php`:
  - `GRID402_SANDBOX_RATE_LIMIT` default alterado de `5` para `3`.
  - Nova constante `GRID402_SANDBOX_WINDOW_SECONDS` default `86400` (24 horas), permitindo override em testes via env.
- `x402.php`: `grid402_check_sandbox_rate_limit()` usa `GRID402_SANDBOX_WINDOW_SECONDS` (`86400s`), retorna `window_seconds`, e emite mensagem orientando compra do Session Pass via Pix (`R$ 12 = 400 créditos`) ou Multi-Chain USDC.

---

## 4. Funil Web Self-Service & SEO PT-BR

### 4.1 Gerador Web `/dossie-kyb` (`public_html/dossie_kyb.php`)
- HTML5 semântico 100% SSR em **PT-BR** com título/meta description otimizados para `"Dossiê 360º KYB de CNPJ | Consulta Receita Federal, Simples e CGU em PDF"`.
- **Fluxo UI sem cadastro:**
  1. Campo de CNPJ com máscara automática + botão **"Gerar Prévia Grátis"** e opção colapsável **"Já tenho um Session Pass (`pass_g402_...`)"** (carregado automaticamente do `localStorage` se existente).
  2. Prévia grátis aciona `GET /api/v1/dossier?cnpj=<clean>` sem `X-Payment` (retorna HTTP 402 com `preview` real) → exibe card com Razão Social / UF / Situação e preview desfocado (*blur*) dos itens premium (Veredito IA, Score 0–100, Simples/MEI, Sanções CEIS/CNEP e Selo ECDSA).
  3. CTA principal **"Desbloquear Dossiê Completo + 39 consultas extras (Pix R$ 12)"** aciona `POST /api/v1/pass/mp-pix` (`tier: "starter"`) inline na mesma tela, mostra QR Code + Copia e Cola + polling automático em `/api/v1/pass/mp-status`.
  4. Assim que o pagamento aprova (ou quando o usuário fornece um passe existente com >= 10 créditos), chama `GET /api/v1/dossier?cnpj=<clean>` com `X-Payment: pass_g402_...`, renderiza o relatório executivo completo e libera botão **"Baixar / Imprimir PDF Oficial"** (`window.print()`).
  5. Folha de estilos `@media print` dedicada oculta navegação/botões/formulários e formata o relatório em fundo branco A4 limpo com cabeçalho Grid402, veredito, score, blocos RFB/Simples/CGU, hash SHA-256 + assinatura `_oracle_proof` e URL de auditoria `https://grid402.online/api/v1/verify`.

### 4.2 Páginas SEO SSR em PT-BR (`public_html/seo_landing.php`)
- Um template limpo, leve e parametrizado roteado via `.htaccess` para 3 slugs estratégicos:
  - `/api-cnpj` → *"API de Consulta CNPJ Receita Federal sem Mensalidade (Pix ou x402)"*
  - `/api-sancoes-cgu` → *"API de Consulta Sanções CGU (CEIS e CNEP) para Compliance e KYB"*
  - `/api-dias-uteis-anbima` → *"API de Dias Úteis e Feriados Bancários ANBIMA para Fintechs e Contabilidade"*
- Cada página inclui: único `<h1>`, `meta description`, `canonical`, Schema.org `SoftwareApplication` + `FAQPage` em JSON-LD, comparação de preço vs. birôs, snippets prontos (`curl -sL`, Python, n8n), link interno para `/dossie-kyb` e CTA 1-clique para `/pass?tier=starter&method=pix`.

### 4.3 Home `/` (`index.php` + `assets/js/i18n.js`)
- Mantém SSR em inglês (zero impacto em descoberta global ou testes).
- Adiciona botão de destaque **"🇧🇷 Pix R$ 12 (400 créditos)"** (`href="/pass?tier=starter&method=pix"`) no Hero CTA group e link direto para `/dossie-kyb`.
- Em `i18n.js`, quando não houver idioma salvo no `localStorage`, detecta `navigator.language` iniciando com `pt` e aplica `pt-BR` automaticamente (com fallback seguro para `en`).

---

## 5. Pacotes Comerciais e Distribuição (`docs/revenue/`)
- `docs/revenue/rapidapi-pack.md`: passo a passo completo + especificação dos endpoints de maior margem e tabela de planos para publicar no RapidAPI.
- `docs/revenue/n8n-creator-submission.md`: metadados, descrição EN/PT e checklist para submeter o workflow `examples/n8n_cnpj_compliance_pix.json` no catálogo oficial `n8n.io/creators`.
- `docs/revenue/b2b-outbound-playbook.md`: oferta comercial embalada (setup R$ 500–R$ 2.500 + recarga Grid402), matriz de ICPs (Agências n8n, Factorings/FIDCs, BPO Financeiro) e scripts prontos de LinkedIn DM, WhatsApp e follow-up.
- `docs/revenue/directory-checklist.md`: checklist de atualização/submissão para `x402-list.com`, `x402bazaar.org`, `x402.new`, `mcp.so`, `Glama`, `Smithery` e `PayAI Bazaar`.
