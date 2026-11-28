# Aceleração de Conversão Multicanal, Recuperação x402 e Dossiê KYB em PDF

## Por quê
Apesar de o hub operar com `100% UP` e ~230–260 page views humanos/dia, a taxa de conversão está em `0,4%` devido a três vazamentos concretos observados no `/admin`: erros x402 recuperáveis misturados a rajadas sintéticas de conformidade sem metadados mínimos de autocorreção explícitos; abandono de humanos no card `HTTP 429` da Home por ausência de checkout Pix inline; e ausência de download `.pdf` binário real em 1 clique no `/dossie-kyb`. Conforme especificado em [`openspec/prds/conversion-boost-x402-pix-pdf.md`](../../prds/conversion-boost-x402-pix-pdf.md), esta change destrava a conversão desses pontos mantendo proteção rígida contra abuso de bots no canal M2M.

## O que muda
- Enriquecimento de respostas de erro recuperáveis x402 (`402`, `400` de prova e `429`) com `required_raw_units`, `required_usdc`, `accepts[]` e `PAYMENT-REQUIRED`, além de auditoria de consistência EIP-712/v1-v2 nas redes EVM ativas.
- Política anti-abuso M2M: remoção de promoções/instruções de cupom gratuito nas respostas JSON `402`/`429` e ausência de tool gratuita no `/mcp`, concentrando o canal M2M em pagamento `x402`, Session Pass e RapidAPI.
- Classificação de falhas de pagamento no `/admin` (`compliance_probe` vs. `payer_failure`) em `payment_failures.json`, `/admin?api=stats` e aba *Saúde e Alertas* com filtro visual.
- Checkout Pix inline completo (Micro R$ 4,90 e Starter R$ 12,00) + reexecução automática da consulta no card `HTTP 429` do Playground da Home, com botão condicional de resgate `MCPTRY50` exibido apenas enquanto a campanha estiver ativa.
- Gerador PDF 1.4 nativo em PHP puro (`services/dossier_pdf.php`) com QR Code vetorial de verificação e endpoint `POST /api/v1/dossier/pdf` autenticado por `_oracle_proof`, integrado como download 1-clique em `/dossie-kyb` com fallback `window.print()`.

## Impacto
- **Specs afetadas:** `x402-exact-settlement`, `admin-dashboard`, `human-revenue-funnel`
- **Código afetado:** `public_html/x402.php`, `public_html/facilitator.php`, `public_html/index.php`, `public_html/mcp.php`, `public_html/logger.php`, `public_html/admin.php`, `public_html/includes/admin_cockpit_view.php`, `public_html/services/dossier_pdf.php` (novo), `public_html/dossie_kyb.php`, `tests/`
- **Fora de escopo:** novo gateway de cartão internacional USD (Stripe/Lemon Squeezy), tool MCP de trial grátis, novos micro-serviços de dados ou uso de Composer/SQL.
