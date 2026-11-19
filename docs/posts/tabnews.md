# Grid402: 23 APIs de dados públicos e Dossiê 360º KYB para agentes de IA — via x402 Multi-Chain (5 EVM + Solana) ou Pix

Se você automatiza KYB, triagem B2B ou enriquecimento de leads no Brasil, conhece o problema: CEP, CNPJ, situação cadastral, Simples Nacional, sanções CEIS/CNEP, feriados e dias úteis ANBIMA estão espalhados em portais públicos sem API unificada — ou atrás de licenças caras de bureau de crédito.

O **Grid402** (https://grid402.online) resolve isso para agentes de IA e pipelines M2M: um hub com **23 micro-serviços** (e **25 ferramentas MCP**, nota A no mcprush) que agentes **contratam na hora**, sem cadastro, sem chave de API e sem contrato — só um `curl`.

## Novidades recentes em produção

- **Novo `/api/v1/dossier` (`0,0500 USDC` / 10 créditos):** Dossiê 360º KYB premium em 1 chamada cruzando Receita Federal (CNPJ) + Simples/MEI + Sanções CGU (CEIS/CNEP) com score 0–100 e **veredito determinístico pronto para agentes de IA** (`APPROVED` / `FLAGGED` / `REJECTED`), estritamente Zero-PII e assinado com ECDSA secp256k1.
- **Pagamentos Multi-Chain em 6 redes (zero taxa de bridge):** além da Base Mainnet e Pix/Cartão BRL (Mercado Pago), agora aceita USDC nativo em **Arbitrum One, Polygon PoS, Optimism, Ethereum Mainnet e Solana (SPL USDC)**.
- **Verificações oficiais:** mcprush **Grade A (25 tools)**, Glama Verified MCP, x402scan Verified Server e PayAPI Market Verified, com quickstart pronto para **LangChain e CrewAI** (`examples/langchain_quickstart.py`).

## O que dá para consultar (23 serviços)

| Endpoint | Custo (USDC) | Créditos | Uso |
|---|---:|---:|---|
| `/api/v1/dossier` | 0,0500 | 10 | Dossiê 360º KYB + Veredito IA (`APPROVED`/`FLAGGED`/`REJECTED`): RFB + Simples/MEI + CGU CEIS/CNEP |
| `/api/v1/compliance` | 0,0300 | 6 | Compliance Score 360 (RF + CGU) |
| `/api/v1/kyb-audit` | 0,0200 | 4 | Auditoria global KYB: Receita Federal CNPJ + Sanções OFAC/ONU + GLEIF LEI |
| `/api/v1/cnpj` | 0,0200 | 4 | Situação cadastral, CNAE, capital social e endereço da PJ |
| `/api/v1/lei` | 0,0200 | 4 | GLEIF LEI — identidade corporativa global ISO 17442 (CC0) |
| `/api/v1/ofac` | 0,0200 | 4 | OFAC SDN + ONU + AML on-chain na Base |
| `/api/v1/domain-audit` | 0,0150 | 3 | Auditoria única: DNS + TLS/SSL + BGP ASN |
| `/api/v1/market-snapshot` | 0,0150 | 3 | Snapshot único: PTAX + Cripto + Selic/CDI/IPCA + ANBIMA |
| `/api/v1/sanctions` | 0,0100 | 2 | Sanções CEIS/CNEP (CGU) |
| `/api/v1/simples` | 0,0100 | 2 | Enquadramento Simples/MEI na Receita |
| `/api/v1/cep`, `/indicators`, `/holidays`, `/workdays`, `/forex`, `/crypto`, `/notarize`, `/global-markets`, `/dns`, `/ssl`, `/asn` | 0,0050 | 1 | CEP/IBGE, Selic/CDI/IPCA, ANBIMA, câmbio, cripto, notarização SHA-256, BCE, DNS/SSL/ASN |

`/status`, `/api/v1/verify`, `/api/v1/pass/balance` e `/api/v1/pass/statement` são gratuitos.

## Teste grátis em 30 segundos (ou n8n com Pix)

```bash
curl -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"
```

(Cota de sandbox: **5 chamadas/hora por IP**, sem cadastro e sem carteira.)

Quem usa **n8n** no Brasil pode importar `examples/n8n_cnpj_compliance_pix.json` e trocar `'demo'` por um **Session Pass comprado direto via Pix** em https://grid402.online/pass:
- **Starter:** 2 USDC / R$ 12 = **400 créditos** (30 dias)
- **Pro:** 5 USDC / R$ 29 = **1.000 créditos** (30 dias)
- **Fleet:** 20 USDC / R$ 115 = **6.000 créditos** (`4.000 base + 2.000 bônus (+50%)`, 60 dias)

Toda resposta paga ou de sandbox vem com `_oracle_proof` — assinatura ECDSA (`secp256k1`) do oráculo Grid402, verificável offline ou em `< 1 ms` via `POST /api/v1/verify`.

## Como funciona o pagamento

1. **HTTP x402 v2 (M2M gasless):** o servidor responde HTTP 402 com o desafio `Payment-Required`, o agente assina EIP-3009 USDC na Base e consome.
2. **Transferência direta Multi-Chain:** envie USDC na rede desejada e informe `X-Payment: arbitrum:0x...`, `X-Payment: polygon:0x...` ou `X-Payment: solana:<assinatura>`.
3. **Session Pass pré-pago (`<15 ms`):** compre via 6 blockchains (5 EVM + Solana) ou Pix/Cartão e use `X-Payment: pass_g402_...`, com `daily_credit_limit`, `allowed_endpoints`, extrato Zero-PII e `Idempotency-Key`.

Zero PII (LGPD/GDPR), sem retenção de dados pessoais (QSA/CPF filtrados na origem).

**Links:** https://grid402.online · Playground: https://grid402.online/#playground · MCP: `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp` · PyPI: `pip install grid402` · npm: `npm install grid402` · GitHub: https://github.com/oliveiratc/Grid402
