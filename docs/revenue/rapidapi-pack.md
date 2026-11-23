# Pacote de Publicação — RapidAPI Hub (Grid402)

> **Objetivo:** Capturar compradores recorrentes em dólar (cartão de crédito internacional via RapidAPI) sem alterar o gateway Zero-DB do Grid402, expondo nossos endpoints de maior demanda global e B2B Brasil com faturamento mensal previsível.

## 1. Estratégia de Autenticação (Ponte Nativa `X-RapidAPI-Proxy-Secret` — Zero Manutenção)
1. No RapidAPI Provider Studio (`Hub Listing → Gateway → Firewall Settings`), copie o valor gerado automaticamente do **`X-RapidAPI-Proxy-Secret`**.
2. Na Hostinger, adicione essa variável no arquivo `.env` seguro (fora de `public_html` ou em `private_data/.env` — **nunca** no Git):
   ```env
   GRID402_RAPIDAPI_PROXY_SECRET=<cole_aqui_o_proxy_secret_da_rapidapi>
   ```
3. O gateway Grid402 (`public_html/config.php` + `public_html/index.php`) valida o header em tempo constante (`hash_equals`):
   - Assinantes dos planos pagos da RapidAPI recebem respostas `HTTP 200` com latência `<15ms`, **sem nunca esgotar créditos** e com rastreio separado (`payment_source: "rapidapi"`).
   - **Proteção de segurança:** o `X-RapidAPI-Proxy-Secret` **jamais** libera emissão de Session Passes (`/api/v1/pass` e `/api/v1/pass-emitir` continuam exigindo pagamento real).
   - *(Fallback opcional)* Caso prefira limitar volume via créditos, também é possível configurar `X-Payment: pass_g402_...` em `Gateway → Secret Headers & Parameters`.

## 2. Catálogo de 2 APIs Recomendadas para Listar Separadamente (Maior Descoberta por Busca)

### Listagem A: `Grid402 Web-to-Markdown, Email Verify & Network Utilities` (Público Global)
- **Categoria:** Data / Tools
- **Subtítulo:** SSRF-hardened URL-to-Markdown for LLMs, Zero-PII B2B Email Verifier (MX/SPF/DMARC), DNS/SSL/ASN Audits & ECDSA Notarization.
- **Endpoints incluídos:**
  - `GET /api/v1/extract?url=https://example.com&max_chars=12000` *(custo interno Grid402: 0.0050 USDC / 1 cr)*
  - `GET /api/v1/email-verify?email=contato@grid402.online` *(custo interno: 0.0050 USDC / 1 cr)*
  - `GET /api/v1/domain-audit?domain=base.org` *(0.0150 USDC / 3 cr)*
  - `GET /api/v1/dns?domain=base.org` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/ssl?host=base.org` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/asn?ip=1.1.1.1` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/notarize?content=hello` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/global-markets?market=US&base=USD` *(0.0050 USDC / 1 cr)*

### Listagem B: `Brazil KYB, CNPJ, CGU Sanctions & ANBIMA Workdays API` (Público Brasil & LatAm Fintech)
- **Categoria:** Business / Finance
- **Subtítulo:** Verified Receita Federal CNPJ, Simples/MEI, CGU CEIS/CNEP Sanctions, KYB 360 AI Dossier & ANBIMA Workdays — Zero-PII (LGPD) with ECDSA Oracle Proof.
- **Endpoints incluídos:**
  - `GET /api/v1/dossier?cnpj=00000000000191` *(0.0500 USDC / 10 cr)*
  - `GET /api/v1/cnpj?cnpj=00000000000191` *(0.0200 USDC / 4 cr)*
  - `GET /api/v1/sanctions?cnpj=00000000000191` *(0.0100 USDC / 2 cr)*
  - `GET /api/v1/simples?cnpj=00000000000191` *(0.0100 USDC / 2 cr)*
  - `GET /api/v1/compliance?cnpj=00000000000191` *(0.0300 USDC / 6 cr)*
  - `GET /api/v1/workdays?start_date=2026-12-23&days=5` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/holidays?year=2026` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/indicators` *(0.0050 USDC / 1 cr)*
  - `GET /api/v1/cep?code=01310100` *(0.0050 USDC / 1 cr)*

## 3. Tabela de Planos Recomendados na RapidAPI (Margem Saudável após 20% de Taxa RapidAPI)

### 3.1. Endpoints Padrão (Listagem Global — `0.0050` a `0.0150 USDC`)

| Plano RapidAPI | Preço Mensal | Cota Mensal | Overage | Equivalente Direto Grid402 |
|---|---|---|---|---|
| **Basic (Free)** | $0/mês | 25 req/mês (rate limit 1 req/min) | Hard limit | Sandbox direto `3 req/dia` |
| **Pro** | $9,90/mês | 1.500 req/mês | $0.010 / req | Starter Pass R$ 12 (400 cr) / Pro R$ 29 (1.200 cr) |
| **Ultra** | $29,00/mês | 6.000 req/mês | $0.006 / req | Fleet Pass R$ 115 / 20 USDC (6.000 cr) |
| **Mega** | $79,00/mês | 25.000 req/mês | $0.004 / req | Enterprise Custom |

### 3.2. Proteção Anti-Arbitragem para Endpoints Premium (`/dossier` e `/compliance` — Per-Endpoint Quotas)

> **Atenção crítica de margem:** nunca exponha `/api/v1/dossier` (`0.0500 USDC`) ou `/api/v1/compliance` (`0.0300 USDC`) na mesma cota plana de `1.500–25.000 req/mês` dos endpoints padrão, pois um assinante Pro pagaria `$0,0066` por dossiê (quase 8× abaixo do preço direto). No **RapidAPI Provider Studio → Monetize → Objects / Per-Endpoint Quotas**, configure cotas dedicadas para essas rotas:

| Endpoint Premium | Preço Direto Grid402 | Basic (Free) | Pro ($9,90/mês) | Ultra ($29,00/mês) | Mega ($79,00/mês) |
|---|---|---|---|---|---|
| `GET /api/v1/dossier` | `0.0500 USDC` (10 cr) | `3 / mês` (hard) | `100 / mês` (overage `$0.08`) | `400 / mês` (overage `$0.06`) | `1.500 / mês` (overage `$0.05`) |
| `GET /api/v1/compliance` | `0.0300 USDC` (6 cr) | `5 / mês` (hard) | `200 / mês` (overage `$0.05`) | `800 / mês` (overage `$0.035`) | `3.000 / mês` (overage `$0.03`) |

### 3.3. Trava Suave de Segurança no Backend PHP (`.env` — Defesa em Profundidade)

Além das cotas no RapidAPI Studio, o gateway Grid402 (`public_html/config.php` + `public_html/index.php`) suporta duas salvaguardas opt-in via `.env` (Zero-DB com `flock(LOCK_EX)` e Zero-PII usando HMAC de `X-RapidAPI-User`):
- `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP=5` *(padrão `0` = desativado)*: limita chamadas diárias em `/api/v1/dossier` por assinante RapidAPI (retorna `HTTP 429 RapidApiEndpointCapExceeded` com `Retry-After` e link para `/pass`; falhas `HTTP != 200` estornam o contador automaticamente).
- `GRID402_RAPIDAPI_BLOCKED_ENDPOINTS=` *(padrão vazio)*: lista separada por vírgula (ex.: `dossier`) para restringir rotas específicas exclusivamente a Session Pass (Pix/USDC) ou x402 direto (`HTTP 403 RapidApiEndpointRestricted`).

> **Dica de conversão cruzada:** Na descrição da API na RapidAPI, inclua o aviso: *"Need pay-per-call crypto settlement (Base, Polygon, Arbitrum, Solana) or Pix (R$ 12 = 400 credits) with zero monthly retainer? Visit https://grid402.online/pass"*.

## 4. Importação Rápida via OpenAPI 3.1
- Importe diretamente `https://grid402.online/openapi.json` no RapidAPI Studio para gerar todos os schemas, descrições e exemplos automaticamente.
- Antes de publicar, rode pelo menos 1 teste de **200 OK** em cada rota no painel RapidAPI para ganhar o selo de **100% Test Coverage / Popularity Score inicial**.
