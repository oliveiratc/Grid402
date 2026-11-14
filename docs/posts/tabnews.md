# Grid402: dados públicos do Brasil para agentes de IA — sem cadastro, via HTTP 402 e USDC na Base

Se você automatiza KYB, triagem B2B ou enriquecimento de leads no Brasil, conhece o problema: CEP, CNPJ, situação cadastral, Simples Nacional, sanções CEIS/CNEP, feriados e dias úteis ANBIMA, tudo isso está espalhado em portais públicos sem API — ou atrás de licenças caras de bureau de crédito.

**Grid402** (https://grid402.online) resolve isso para agentes de IA: um hub de utilitários de dados públicos e de rede que agentes **contratam na hora**, com pagamento por chamada em USDC na Base, sem cadastro, sem chave de API, sem contrato — só um `curl`.

## O que dá para consultar

| Endpoint | Custo (USDC) | Uso |
|---|---:|---|
| `/api/v1/cnpj` | 0,0200 | Situação cadastral, CNAE, Simples, endereço |
| `/api/v1/compliance` | 0,0300 | Score 360 (RF + CGU + societário) |
| `/api/v1/sanctions` | 0,0100 | Sanções CEIS/CNEP (CGU) |
| `/api/v1/simples` | 0,0100 | Enquadramento Simples/MEI na Receita |
| `/api/v1/lei` | 0,0200 | GLEIF LEI — identidade corporativa global (CC0) |
| `/api/v1/ofac` | 0,0200 | OFAC SDN + ONU + AML on-chain na Base |
| `/api/v1/cep` | 0,0050 | Logradouro, bairro, IBGE |
| `/api/v1/indicators` | 0,0050 | Selic, CDI, IPCA |
| `/api/v1/holidays` | 0,0050 | Feriados bancários ANBIMA |
| `/api/v1/workdays` | 0,0050 | Dias úteis (contagem/projeção) |
| `/api/v1/forex`, `/api/v1/crypto`, `/api/v1/notarize`, `/api/v1/global-markets`, `/api/v1/dns`, `/api/v1/ssl`, `/api/v1/asn` | 0,0050 | Câmbio, cripto, cartimbo de tempo, mercados BCE, DNS/SSL/ASN |

`/api/v1/summary` e `/api/v1/verify` são gratuitos.

## Teste grátis em 30 segundos

```bash
curl -H "X-Payment: demo" "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"
```

(Amostra grátis + desafio 402. Cota de sandbox: 30 chamadas/hora por IP.)

Toda resposta paga vem com `_oracle_proof` — assinatura ECDSA (secp256k1) do oráculo Grid402, verificável offline em `< 1 ms` via `/api/v1/verify`.

## Como funciona o pagamento

HTTP 402 responde com o preço, você paga em USDC na Base e passa a consumir; ou compra um **Session Pass** (Starter: 2 USDC = 40.000 créditos → 400 chamadas padrão) e as chamadas ficam instantâneas (<15 ms).

Zero PII, nada fica retido além do estritamente necessário; o verify-endpoint é gratuito e público.

**Links:** https://grid402.online · MCP: `npx grid402-mcp` · PyPI: `pip install grid402` · npm: `npm install grid402` · código: https://github.com/oliveiratc/Grid402
