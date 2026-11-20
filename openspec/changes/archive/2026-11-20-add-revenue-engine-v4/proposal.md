# Adicionar Grid402 Revenue Engine v4 (Multi-Chain x402 Gasless, Endpoints Globais, Dossiê KYB Web/PDF e Aquisição B2B)

## Por quê
Referência: `openspec/prds/revenue-engine-v4.md`.

Apesar de a infraestrutura do Grid402 estar 100% operacional (`23/23 UP`, Pix real ativo e `grid402-mcp@1.9.0`), a receita orgânica permanece travada por quatro gargalos claros:
1. O desafio `HTTP 402` (`accepts[]`) anuncia apenas `eip155:8453` (Base), excluindo agentes autônomos em **Solana**, **Polygon** e **Arbitrum** suportados pelo PayAI Facilitator gasless, e `/api/v1/dossier` ainda não consta no `bazaar_seed.mjs`.
2. O catálogo carece de endpoints globais chamados em loop por agentes de IA (`URL -> Markdown` e validação de e-mails B2B).
3. O site não oferece um produto web self-service para compradores não-técnicos no Brasil gerarem o **Dossiê 360º KYB em PDF via Pix**, nem landing pages SEO SSR em português, enquanto o Sandbox (`5 req/h`) é permissivo demais para estimular upgrade.
4. Ainda faltam os kits operacionais de submissão em marketplaces com cartão (**RapidAPI**, **n8n Official Templates**, **mcp.so / x402-list.com**) e prospecção ativa B2B.

## O que muda
- **x402 v2 Multi-Chain Gasless (PayAI):** expandir `grid402_build_accepts()` e `facilitator.php` para aceitar `exact` em **Base (`eip155:8453`)**, **Polygon (`eip155:137`)**, **Arbitrum (`eip155:42161`)** e **Solana (`solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`)**, mantendo retrocompatibilidade estrita no modo `base-sepolia`.
- **Novos micro-serviços globais (`0.0050 USDC` / 1 crédito):**
  - `GET /api/v1/extract?url=...` (extração HTML → Markdown limpo + metadados com proteção estrita contra SSRF);
  - `GET /api/v1/email-verify?email=...` (validação sintática + MX + SPF/DMARC + descartáveis + score 0–100 + veredito, Zero-PII).
- **Paridade completa (`25` serviços UP / `27` tools MCP):** atualizar `config.php`, `collector.php`, `index.php`, `catalog.php`, `trust.php`, `mcp.php`, `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs`, `openapi.json`, `llms*.txt`, `.well-known/*` e `scripts/bazaar_seed.mjs`.
- **Funil de conversão e Sandbox:** reduzir cota gratuita do Sandbox (`X-Payment: demo`) de `5 req/hora` para **`3 req/dia` por IP** (`86400s`).
- **Produto Web + SEO PT-BR (mínimo mantido em R$ 12,00):**
  - Criar gerador web `/dossie-kyb` (PT-BR SSR) com preview grátis, checkout Pix inline (R$ 12 = 400 créditos / 40 dossiês), renderização visual do Dossiê 360º e exportação PDF via `@media print`.
  - Criar 3 páginas SEO 100% SSR em PT-BR (`/api-cnpj`, `/api-sancoes-cgu`, `/api-dias-uteis-anbima`), atualizar `.htaccess` e `sitemap.xml` (incluindo `/pass`), e adicionar botão direto de Pix R$ 12 no Hero + auto-detecção PT-BR em `i18n.js`.
- **Onda 2 (Distribuição e Outbound B2B):** criar pacotes prontos em `docs/revenue/` para RapidAPI, n8n Official Templates, diretórios x402/MCP e Playbook Comercial B2B.

## Impacto
- **Specs afetadas:** `x402-exact-settlement`, `open-data-utilities`, `x402-payment-oracle`, `mcp-server`, `human-revenue-funnel` (nova).
- **Código afetado:** `public_html/config.php`, `public_html/x402.php`, `public_html/facilitator.php`, `public_html/index.php`, `public_html/collector.php`, `public_html/.htaccess`, `public_html/sitemap.xml`, `public_html/services/extract.php`, `public_html/services/email_verify.php`, `public_html/dossie_kyb.php`, `public_html/seo_landing.php`, `public_html/assets/js/i18n.js`, `public_html/mcp.php`, `public_html/catalog.php`, `public_html/trust.php`, `public_html/openapi.json`, `public_html/llms*.txt`, `public_html/.well-known/*`, `mcp/*`, `scripts/bazaar_seed.mjs`, `docs/revenue/*` e suítes em `tests/`.
- **Fora de escopo:** novos tiers abaixo de R$ 12 no Mercado Pago, renderização JavaScript headless no `/extract`, geração server-side de PDF com Dompdf/TCPDF e sistema de login de usuários.
