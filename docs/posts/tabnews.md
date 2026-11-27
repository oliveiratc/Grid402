# Grid402: 25 APIs de dados públicos, Sanções 4 Blocos e Dossiê KYB para agentes de IA — via x402 Dual-Facilitator (Base, Polygon, Arbitrum, Solana) ou Pix

Se você automatiza KYB, triagem de compliance, extração web para RAG ou enriquecimento de leads no Brasil e no exterior, conhece o problema: CNPJ, Simples Nacional, sanções internacionais, feriados ANBIMA e dados públicos estão espalhados em portais instáveis — ou presos atrás de contratos anuais caros e burocracia de API key.

O **Grid402** (https://grid402.online) resolve isso para agentes de IA e pipelines M2M: um hub com **25 micro-serviços UP** (e **27 ferramentas MCP `v2.0.0`**, Nota A no mcprush) que agentes contratam na hora, sem cadastro, sem chave de API e sem mensalidade — só um `curl`.

## Novidades recentes em produção

- **Liquidez Global x402 v2 (Dual-Facilitator Coinbase CDP + PayAI + Solana):** roteamento Dual-Facilitator na Base (**Coinbase CDP** como primário com conformidade CDP Bazaar v2 + fallback automático pré-liquidação `/verify` para **PayAI**, com trava anti-dupla-cobrança no `/settle`) e liquidação gasless em **Base, Polygon, Arbitrum e Solana (SPL USDC)** via manifesto dinâmico `/.well-known/x402.json` — além de transferência direta em 6 redes, **Pix/Cartão** (`/pass`) e **RapidAPI (Cartão USD)**.
- **Sanções Globais em 4 Blocos Oficiais + AML Web3 Multi-Ativo (`/api/v1/ofac` e `/api/v1/kyb-audit`):** índice Zero-DB JSON particionado (`<= 30 ms`) sincronizado diariamente com **OFAC SDN (EUA)**, **ONU**, **União Europeia (EU FSFi)** e **Reino Unido (UK OFSI)**, cobrindo carteiras **EVM, Solana, Bitcoin, Tron** e nomes/aliases. Opera em modo *fail-closed* (`HTTP 503` pré-cobrança se o índice estiver indisponível — zero falso `CLEAR`) e expõe `dataset_sha256`, `last_synced_at` e `freshness_status` assinados no `_oracle_proof`.
- **Extrator Web-to-Markdown 100% Transiente (`/api/v1/extract`):** **0 bytes** de conteúdo de terceiros gravados em disco (`transient_only: true`), respeito nativo a `robots.txt` (RFC 9309), `X-Robots-Tag` (`noai`, `noimageai`, `noindex`), `Content-Signal` (`ai-input=no`), meta tags anti-AI e bloqueio automático de paywalls / HTTP `401/403` antes da cobrança.
- **Preços menores, Pro Pass turbinado e garantias pró-cliente:** `/api/v1/cnpj` e `/api/v1/lei` caíram **50%** para **`0,0100 USDC` (2 créditos)**; `/api/v1/kyb-audit` foi para **`0,0250 USDC` (5 créditos)**; **Pro Pass** subiu para **1.200 créditos** (5 USDC / R$ 29) e a validade foi estendida para **180 dias** (Starter/Pro) e **365 dias** (Fleet — 6.000 créditos). Inclui estorno automático em qualquer falha (`HTTP != 200`) e retentativa grátis por 300s com `Idempotency-Key`.
- **Dossiê 360º KYB + Veredito IA (`/api/v1/dossier`):** cruza Receita Federal (CNPJ) + Simples/MEI + Sanções CGU (CEIS/CNEP) com score 0–100 e veredito determinístico (`APPROVED` / `FLAGGED` / `REJECTED`), Zero-PII e assinatura ECDSA secp256k1.

## O que dá para consultar (25 serviços)

| Endpoint | Custo (USDC) | Créditos | Uso |
|---|---:|---:|---|
| `/api/v1/dossier` | 0,0500 | 10 | Dossiê 360º KYB + Veredito IA (`APPROVED`/`FLAGGED`/`REJECTED`): RFB + Simples/MEI + CGU CEIS/CNEP |
| `/api/v1/compliance` | 0,0300 | 6 | Compliance Score 360 (RF + CGU) |
| `/api/v1/kyb-audit` | 0,0250 | 5 | Auditoria global KYB: Receita Federal CNPJ + Sanções 4 Blocos (OFAC/ONU/UE/UK) + GLEIF LEI |
| `/api/v1/ofac` | 0,0200 | 4 | Sanções oficiais em 4 blocos (OFAC SDN, ONU, UE FSFi, UK OFSI) para EVM/Solana/BTC/Tron e entidades + AML Base |
| `/api/v1/domain-audit` | 0,0150 | 3 | Auditoria única: DNS + TLS/SSL + BGP ASN |
| `/api/v1/market-snapshot` | 0,0150 | 3 | Snapshot único: PTAX + Cripto + Selic/CDI/IPCA + ANBIMA |
| `/api/v1/cnpj` | 0,0100 | 2 | Situação cadastral, CNAE, capital social e endereço da PJ |
| `/api/v1/lei` | 0,0100 | 2 | GLEIF LEI — identidade corporativa global ISO 17442 (CC0) |
| `/api/v1/sanctions` | 0,0100 | 2 | Sanções CEIS/CNEP (CGU) |
| `/api/v1/simples` | 0,0100 | 2 | Enquadramento Simples/MEI na Receita |
| `/api/v1/extract` | 0,0050 | 1 | Extrator Web-to-Markdown 100% transiente (`robots.txt`, `noai`, anti-SSRF e bloqueio de paywall) |
| `/email-verify`, `/cep`, `/indicators`, `/holidays`, `/workdays`, `/forex`, `/crypto`, `/notarize`, `/global-markets`, `/dns`, `/ssl`, `/asn` | 0,0050 | 1 | Email Verify Zero-PII, CEP/IBGE, Selic/CDI/IPCA, ANBIMA, câmbio, cripto, notarização SHA-256, BCE, DNS/SSL/ASN |

`/status`, `/api/v1/verify`, `/api/v1/pass/balance` e `/api/v1/pass/statement` são gratuitos.

## Teste grátis em 30 segundos (ou n8n com Pix)

```bash
curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"
```

(Cota de sandbox: **3 chamadas/dia por IP**, sem cadastro e sem carteira — ou resgate **50 créditos grátis** com o cupom `MCPTRY50` em https://grid402.online/pass.)

Quem usa **n8n** no Brasil pode importar `examples/n8n_cnpj_compliance_pix.json` e trocar `'demo'` por um **Session Pass comprado via Pix ou Web3** em https://grid402.online/pass (`1 crédito = 0,0050 USDC`):
- **Starter:** 2 USDC / R$ 12 = **400 créditos** (180 dias)
- **Pro:** 5 USDC / R$ 29 = **1.200 créditos** (`1.000 base + 200 bônus (+20%)`, 180 dias)
- **Fleet:** 20 USDC / R$ 115 = **6.000 créditos** (`4.000 base + 2.000 bônus (+50%)`, 365 dias)

Toda resposta paga ou de sandbox vem com `_oracle_proof` — assinatura ECDSA (`secp256k1`) do oráculo Grid402 cobrindo os dados e metadados `_meta` (incluindo `dataset_sha256` nas sanções), verificável offline ou em `< 1 ms` via `POST /api/v1/verify`.

## Como funciona o pagamento e as garantias

1. **HTTP x402 v2 Dual-Facilitator (M2M gasless):** o servidor responde HTTP 402 com `Payment-Required`, e liquida via **Coinbase CDP** (primário na Base) ou **PayAI** (fallback seguro pré-`/settle` na Base, e primário em Polygon, Arbitrum e Solana SPL USDC).
2. **Transferência direta Multi-Chain:** envie USDC na rede desejada e informe `X-Payment: arbitrum:0x...`, `X-Payment: polygon:0x...` ou `X-Payment: solana:<assinatura>` (6 redes suportadas: Base, Arbitrum, Polygon, Optimism, Ethereum e Solana).
3. **Session Pass pré-pago (`<15 ms`) ou RapidAPI:** compre via USDC ou Pix/Cartão e envie `X-Payment: pass_g402_...`, com `daily_credit_limit`, `allowed_endpoints`, extrato Zero-PII JSON/CSV, **estorno automático em qualquer falha (`HTTP != 200`)** e **retentativa grátis por 300s com `Idempotency-Key`**.

Zero PII (LGPD/GDPR), sem retenção de dados pessoais (QSA/CPF filtrados na origem) e `0 bytes` em disco no `/extract`.

**Links:** https://grid402.online · Catálogo: https://grid402.online/catalog · Trust Center: https://grid402.online/trust · MCP: `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp` · PyPI: `pip install grid402` · npm: `npm install grid402` · GitHub: https://github.com/oliveiratc/Grid402
