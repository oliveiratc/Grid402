# Design Técnico: Aceleração de Conversão, Recuperação x402 e PDF Nativo

## 1. Visão Arquitetural
Esta change segue estritamente a arquitetura **Zero-DB / Zero-PII** do Grid402 em PHP 8.1+ (LiteSpeed Hostinger e PHP CLI local), sem dependências Composer e sem escrita de dados sensíveis de laudos em disco.

---

## 2. Frente A — Recuperação x402, Auditoria EIP-712 e Política Anti-Abuso M2M

### 2.1 Contrato de Erros Recuperáveis (`402` / `400` / `429`)
- **Onde:** `public_html/x402.php` (`grid402_respond_402`, `grid402_emit_payment_required_header`) e `public_html/index.php` (`$emitPaymentFailure`).
- Toda resposta HTTP `402` (incluindo rejeição após verificação), HTTP `400` por prova malformada/rede não suportada e HTTP `429` de cota sandbox MUST incluir no JSON raiz:
  - `'required_raw_units' => (int) $servicePriceRaw`
  - `'required_usdc' => (string) $servicePriceDisplay`
  - `'accepts' => $prBundle['accepts']` (array x402 v2 completo)
  - Cabeçalho HTTP `PAYMENT-REQUIRED` base64 sincronizado.
- Em `public_html/mcp.php`, os erros de pagamento `@x402/mcp` também expõem `required_raw_units`, `required_usdc` e `accepts` dentro de `_meta['x402/error']`.

### 2.2 Política Anti-Abuso M2M (Remoção de Promo-Claim para Bots)
- **Onde:** `public_html/x402.php` (`grid402_validate_payment` linha 492, `grid402_respond_402` bloco `conversion`), `public_html/index.php` (`$emitPaymentFailure`, `sandbox_info`, preview `pass`), `public_html/mcp.php` (trial/erro 402).
- As respostas JSON de APIs `/api/v1/*` (`402` e `429`) e `/mcp` **não** devem conter chaves `free_promo`, `promo_code`, `promo_url` nem URLs `promo=MCPTRY50` ou referências a `/api/v1/pass/promo-claim`.
- Os links e instruções M2M passam a apontar estritamente para canais pagos:
  1. Assinatura x402 v2 (`PAYMENT-SIGNATURE` / `accepts[]` EVM multi-chain) ou pagamento on-chain (`X-Payment: <tx_hash>`)
  2. Session Pass pré-pago (`https://grid402.online/pass` via Pix BRL ou USDC EVM)
  3. Assinatura em USD com cartão via RapidAPI Hub.
- O endpoint `POST /api/v1/pass/promo-claim` continua ativo exclusivamente para o fluxo web humano (em `/pass` e condicional no card `429` da Home enquanto a campanha estiver vigente).

### 2.3 Auditoria EIP-712 Multi-Chain
- Confirmado no código atual que Base (`eip155:8453`), Polygon (`eip155:137`) e Arbitrum (`eip155:42161`) usam domínio `extra: { name: "USD Coin", version: "2" }` (contratos USDC nativos Circle `0x833589...`, `0x3c499c...`, `0xaf88d0...`), e `grid402_normalize_exact_payload()` normaliza `amount` e `network` para o `deepEqual` do PayAI.
- A auditoria verificará e cobrirá com testes automatizados que:
  - Contratos, chain IDs CAIP-2 e metadados EIP-712 `extra.name`/`extra.version` são idênticos entre `x402.php` (`grid402_build_accepts`) e `facilitator.php` (`grid402_exact_supported_networks`);
  - Payloads x402 v1 (top-level `scheme`/`network`) e v2 (`accepted` aninhado) são normalizados com `resource` absoluto sem perder `extra` nem gerar falsos `invalid_proof`.

---

## 3. Frente B — Classificação `compliance_probe` vs. `payer_failure` no `/admin`

- **Onde:** `public_html/logger.php`, `public_html/admin.php`, `public_html/includes/admin_cockpit_view.php`.
- Em `grid402_record_payment_failure(array $event)`:
  - Classifica `failure_origin` como `'compliance_probe'` quando ocorrer qualquer uma das condições:
    1. Falha com assinatura sintética óbvia (`0x1111...`, `0xaaaa...`, `0x0000...`, payload mockado de scanner sem nonce/valor válido); OU
    2. O mesmo `ip_hmac` acumular `>= 3` falhas distintas nos últimos `15 segundos` em `payment_failures.json` (rajada automatizada típica de suítes de conformidade x402). Ao atingir o 3º evento na janela, pode atualizar também os eventos recentes dessa mesma rajada dentro dos 15s ou marcar os novos e recalcular na leitura via helper determinístico `grid402_classify_payment_failure_origin()`.
  - Caso contrário, classifica como `'payer_failure'`.
- Em `grid402_read_recent_payment_failures()` e `/admin?api=stats`:
  - Mantém retrocompatibilidade com registros antigos sem campo `failure_origin` (computando a origem em leitura pela janela de 15s por `ip_hmac` quando o campo estiver ausente).
  - Expõe `payment_failures_summary: ['total' => ..., 'payer_failure' => ..., 'compliance_probe' => ...]` em `/admin?api=stats`.
- Na UI (`admin_cockpit_view.php`, aba *Saúde e Alertas*):
  - Botões de filtro rápido no topo da tabela `Diagnóstico Real de Falhas`: **Todos**, **Pagadores Reais (`payer_failure`)** e **Probes de Conformidade (`compliance_probe`)**, com badge visual em cada linha.

---

## 4. Frente C — Pix Inline e Cupom Condicional no Card `429` da Home

- **Onde:** `public_html/index.php` (`#playground-rate-limit-card` + scripts do Playground).
- No SSR PHP da Home, consultar `grid402_promo_get_status('MCPTRY50')` (via `require_once __DIR__ . '/services/promo_pass.php'`) para injetar flag booleana segura `GRID402_HOME_PROMO_ACTIVE` (`true` somente se `ok && is_active`).
- Ao receber HTTP `429` no Playground:
  - Abrir `#playground-rate-limit-card` mostrando:
    1. Seletor inline de plano Pix: **Micro (R$ 4,90 = 50 créditos / 5 dossiês)** pré-selecionado + **Starter (R$ 12,00 = 400 créditos)**;
    2. Campo de e-mail para recibo (obrigatório na criação do Pix) + botão **⚡ Gerar QR Code Pix Agora**;
    3. Container inline do QR Code (`<img src="data:<qr_mime>;base64,...">`), campo Pix Copia e Cola com botão **Copiar** e status de polling em tempo real (`/api/v1/pass/mp-status?order_id=...`);
    4. Botão inline **🔑 Já tenho um Session Pass** (`setPlaygroundMode('pass')`) e link **💳 Cartão USD (RapidAPI)**;
    5. **Condicional (`GRID402_HOME_PROMO_ACTIVE === true`):** botão **🎁 Resgatar 50 créditos grátis agora (`MCPTRY50`)** que faz `POST /api/v1/pass/promo-claim` inline no mesmo card, sem sair da Home. Quando a campanha expirar/esgotar, o elemento não é renderizado.
  - Ao aprovar o Pix (ou resgatar o cupom com sucesso):
    - Persistir o token `pass_g402_...` via helper unificado (`grid402_persist_pass_token` -> `localStorage['grid402_active_pass_token']` + cookie `g402_pass_token`), selecionar o modo Session Pass no Playground, preencher o campo do token e **disparar `runPlayground()` automaticamente** para entregar imediatamente a resposta paga ao usuário.

---

## 5. Frente D — Gerador PDF 1.4 Nativo em PHP Puro e Rota `POST /api/v1/dossier/pdf`

### 5.1 Autenticação Anti-Forjamento (`_oracle_proof`)
- **Onde:** `public_html/services/oracle_signer.php`, `public_html/index.php` (`POST /api/v1/dossier/pdf`).
- Para impedir uso da rota como "fábrica de PDFs falsos" com timbre da Grid402:
  - Requisições para `POST /api/v1/dossier/pdf` enviam JSON contendo o dossiê completo (`dossier`) ou `{ "dossier": {...} }`.
  - O servidor verifica se o payload corresponde à assinatura ECDSA `secp256k1` válida em `_oracle_proof` usando `grid402_verify_oracle_proof($dossierPayload)` (já existente no verificador `/api/v1/verify` — reutilizar o mesmo algoritmo com a chave pública oficial `GRID402_ORACLE_PUBKEY` / `GRID402_DEFAULT_ORACLE_PUBKEY`).
  - Exceção explícita controlada: payloads marcados com `'sample_demo' => true` **somente** são aceitos se corresponderem ao CNPJ exemplo oficial estático (`00000000000191`) e imprimem tarja/cabeçalho evidente **`AMOSTRA ILUSTRATIVA — SEM VALOR LEGAL`** em todas as páginas.
  - Qualquer payload sem `_oracle_proof` válido ou adulterado recebe HTTP `403` (`InvalidOracleProof`), sem gerar PDF.

### 5.2 Arquitetura do Gerador PDF 1.4 Puro (`public_html/services/dossier_pdf.php`)
- **Zero dependências externas e zero escrita em disco:** monta a stream PDF em memória (`%PDF-1.4`, objetos de catálogo, árvore de páginas, fontes padrão Type1 `Helvetica`, `Helvetica-Bold` e `Courier` com `/Encoding /WinAnsiEncoding`, tabela `xref` calculada por offset exato em bytes e `trailer`).
- **Codificação WinAnsi pura (`grid402_pdf_encode_winansi`):** tabela determinística convertendo caracteres UTF-8 PT-BR comuns (`áàâãäéèêëíìîïóòôõöúùûüçñºª§–—`) para bytes `Windows-1252` + escape de parênteses/barras PDF (`\(`, `\)`, `\\`), sem exigir extensão `mbstring` ou `iconv`.
- **QR Code vetorial em PHP puro:**
  - Implementar gerador minimalista de matriz QR Code em modo Byte (ECC nível `L`, versões 1–6 automáticas conforme tamanho da URL, ex.: `https://grid402.online/trust?hash=...`, incluindo polinômios GF(256) Reed-Solomon, máscaras e padrões de alinhamento/tempo/formato) em PHP puro, renderizado diretamente no PDF com blocos vetoriais pretos (`x y w h re f`), dispensando biblioteca GD ou imagens PNG em disco!
- **Layout Executivo A4 (`595.28 x 841.89 pt`):**
  - Cabeçalho institucional azul-escuro `#0B132B` com marca **Grid402 // Dossiê 360° KYB** e data/hora BRT+UTC (ou banner `AMOSTRA ILUSTRATIVA` quando amostra);
  - Card de Veredito (`APPROVED` verde, `FLAGGED` âmbar, `REJECTED` vermelho) com Score 0–100 e CNPJ formatado;
  - Seções tabuladas com quebra automática para página 2+ quando necessário: Dados Cadastrais (Receita Federal), Simples Nacional / MEI, Sanções CGU (CEIS/CNEP) e Quadro Societário (QSA);
  - Rodapé criptográfico com `data_hash`, assinatura truncada, endereço do oráculo (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`) e o QR Code vetorial de auditoria.

### 5.3 Integração no `/dossie-kyb` (`public_html/dossie_kyb.php`)
- Guardar em memória (`currentDossierPayload`) o JSON completo retornado por `/api/v1/dossier` ou os dados da amostra oficial.
- Botão principal **"⬇️ Baixar PDF Oficial (A4)"** executa `fetch('/api/v1/dossier/pdf', { method: 'POST', body: JSON.stringify(...) })`, converte a resposta em `Blob` (`application/pdf`) e dispara download de `dossie-kyb-<cnpj>.pdf` (com fallback amigável em caso de erro).
- Botão secundário **"🖨️ Imprimir (Navegador)"** aciona `window.print()`.
