# Tasks: Aceleração de Conversão, Recuperação x402 e PDF Nativo

## 1. Erros x402 Estruturados, Auditoria EIP-712 e Política Anti-Abuso M2M
- [x] 1.1 Auditar e validar consistência entre `grid402_build_accepts()` (`public_html/x402.php`) e `grid402_exact_supported_networks()` (`public_html/facilitator.php`) para Base, Polygon e Arbitrum e payloads v1/v2.
- [x] 1.2 Garantir que toda resposta recuperável (`402`, `400` por prova/rede e `429`) em `public_html/x402.php`, `public_html/index.php` e `public_html/mcp.php` exponha `required_raw_units`, `required_usdc`, `accepts[]` e `PAYMENT-REQUIRED`.
- [x] 1.3 Remover anúncios/instruções de `MCPTRY50` e `/api/v1/pass/promo-claim` das respostas JSON M2M (`402`/`429` e `/mcp`), mantendo redirecionamento exclusivo para `x402`, `/pass` e RapidAPI USD e garantindo nenhuma tool gratuita adicionada ao `/mcp`.

## 2. Separação de Probes Sintéticos vs. Falhas Reais de Pagadores no `/admin`
- [x] 2.1 Implementar classificação determinística `failure_origin` (`compliance_probe` vs. `payer_failure`) em `public_html/logger.php` (`payment_failures.json` e `grid402_read_recent_payment_failures`), com fallback retrocompatível para eventos antigos.
- [x] 2.2 Expor `payment_failures_summary` em `public_html/admin.php` (`/admin?api=stats`) e adicionar badges + botões de filtro por origem na tabela de falhas em `public_html/includes/admin_cockpit_view.php`.

## 3. Checkout Pix Inline e Cupom Condicional no Card `429` do Playground
- [x] 3.1 Injetar no SSR de `public_html/index.php` o status real da campanha via `grid402_promo_get_status('MCPTRY50')` e tornar o botão inline de cupom `MCPTRY50` estritamente condicional a `is_active === true`.
- [x] 3.2 Implementar checkout Pix inline (`micro` R$ 4,90 e `starter` R$ 12,00), QR Code (`qr_mime` dinâmico), polling `/api/v1/pass/mp-status`, salvamento na persistência unificada e reexecução automática da consulta dentro de `#playground-rate-limit-card` em `public_html/index.php`.

## 4. Gerador PDF 1.4 Nativo em PHP Puro e Endpoint `POST /api/v1/dossier/pdf`
- [x] 4.1 Criar `public_html/services/dossier_pdf.php` com escritor PDF 1.4 nativo (zero dependências externas, WinAnsi PT-BR sem `mbstring`, multipágina A4, zero gravação em disco) e codificador QR Code vetorial puro (`re f`).
- [x] 4.2 Implementar validação criptográfica `grid402_verify_oracle_proof()` para payloads de dossiê e rota `POST /api/v1/dossier/pdf` em `public_html/index.php` (incluindo proteção estrita da amostra ilustrativa oficial).
- [x] 4.3 Atualizar `public_html/dossie_kyb.php` para acionar `POST /api/v1/dossier/pdf` em 1 clique no botão principal **"⬇️ Baixar PDF Oficial (A4)"** e manter **"🖨️ Imprimir (Navegador)"** (`window.print()`) como fallback.

## 5. Testes, Verificação e Empacotamento
- [x] 5.1 Criar suíte de testes cobrindo erros recuperáveis x402, ausência de `promo-claim` no M2M, classificação `compliance_probe` vs. `payer_failure`, card `429` da Home e geração/autenticidade `POST /api/v1/dossier/pdf`.
- [x] 5.2 Executar bateria de homologação existente + novos testes, validar `openspec validate --strict` e gerar `dist/grid402_hostinger_deploy.zip`.
