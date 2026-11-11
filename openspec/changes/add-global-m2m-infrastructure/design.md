# Design Técnico: Infraestrutura Global M2M e Passes de Sessão x402

## 1. Arquitetura do Passe de Sessão Pré-pago (`x402 Session Pass`)
Para manter a filosofia **Zero-DB** do Grid402 (sem MySQL/PostgreSQL ou Redis), o controle de passes utiliza um arquivo JSON protegido em `private_data/session_passes.json` operado com `flock(LOCK_EX)` para atomicidade estrita em concorrência, combinado com assinatura HMAC-SHA256 derivada de `GRID402_ORACLE_PRIVATE_KEY` (ou `GRID402_CRON_SECRET` como fallback).

### Formato do Token:
`pass_g402_<16_hex_id>_<16_hex_hmac>`

- `<16_hex_id>`: Identificador único gerado com `bin2hex(random_bytes(8))`.
- `<16_hex_hmac>`: Primeiros 16 caracteres hexadecimais de `hash_hmac('sha256', $passId, $secret)`. Isso permite rejeitar tokens inválidos ou forjados em **0.05ms** na memória antes mesmo de abrir o arquivo em disco.

### Conversão de USDC para Créditos:
- Unidade base: `500` unidades raw de USDC (`0.0005 USDC`) = **1 Crédito Universal**.
- Depósito mínimo para emissão de passe em `/api/v1/pass`: `0.0050 USDC` (`5000` raw = 10 créditos).
- Pacote recomendado padrão: `0.0500 USDC` (`50000` raw = **100 créditos**).
- Pacote enterprise: `0.5000 USDC` (`500000` raw = **1.000 créditos**).
- No modo Sandbox (`X-Payment: demo` em `/api/v1/pass`), o sistema emite um token de demonstração `pass_g402_demo_...` com **5 créditos** gratuitos para testes de integração.

### Consumo de Créditos por Endpoint:
Custo em créditos = `max(1, intdiv($requiredRawUnits, 500))`.
- Endpoints de `0.0005 USDC` (`forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `indicators`, `holidays`, `workdays`, `notarize`, `global-markets`): **1 crédito**.
- Endpoints de `0.0010 USDC` (`sanctions`, `simples`): **2 créditos**.
- Endpoints de `0.0020 USDC` (`cnpj`, `lei`, `ofac`): **4 créditos**.
- Endpoint de `0.0030 USDC` (`compliance`): **6 créditos**.

## 2. Especificação dos 4 Novos Endpoints Globais (Domínio Público / CC0)

### 2.1 `/api/v1/lei` — Global Corporate Identity (GLEIF LEI)
- **Fonte Primária:** `https://api.gleif.org/api/v1/lei-records` (Licença CC0 1.0 Universal — Domínio Público Mundial).
- **Parâmetros:** `lei` (código ISO 17442 de 20 caracteres, ex: `5493006MHB84DD0ZWV18` para Apple Inc., `5493001KJTIIGC8Y1R12` para Microsoft, `INR2EJN1ERAN0W5ZP974` para Alphabet/Google) ou `q` (busca por nome corporativo, ex: `q=Coinbase`).
- **Retorno:** Razão social (`legal_name`), jurisdição (`jurisdiction`), categoria, status (`entity_status`), status do registro LEI (`registration_status`), endereço legal completo, autoridade de registro (`registration_authority`) e data da próxima renovação.

### 2.2 `/api/v1/ofac` — Global Sanctions & Web3 AML Inspector
- **Fontes Primárias:**
  1. Lista pública de endereços cripto e entidades sancionadas do Tesouro dos EUA (OFAC SDN — Domínio Público 17 U.S.C. § 105) + cache local atualizável de endereços e nomes sancionados conhecidos (Tornado Cash, Lazarus Group, Blender.io, Garantex, Sinbad etc.).
  2. RPC Público da Base Mainnet (`https://mainnet.base.org`) para inspeção em tempo real de qualquer carteira EVM (`0x...`): saldo nativo ETH, saldo USDC (`balanceOf`), número de transações enviadas (`eth_getTransactionCount` / nonce) e verificação se é EOA ou Smart Contract (`eth_getCode`).
- **Parâmetros:** `address` (carteira `0x...`) e/ou `name` (nome da entidade/empresa).
- **Retorno:** `sanctioned` (bool), `risk_level` (`LOW` | `CRITICAL`), `matched_lists` (array), `onchain_profile` (saldos, nonce, tipo de conta `EOA` ou `SMART_CONTRACT`).

### 2.3 `/api/v1/notarize` — M2M Cryptographic Timestamp & Attestation
- **Funcionamento:** Primitiva pura de cartório digital M2M. O agente envia `data` (qualquer string/JSON ou decisão de IA) ou `hash` (SHA-256 hex).
- **Retorno:** Certificado determinístico contendo `certificate_id`, `input_sha256`, `notarized_at_utc` (ISO-8601), `unix_epoch`, `oracle_address`, `network: "Base Mainnet (8453)"` e a assinatura matemática `_oracle_proof` (`secp256k1-sha256`) verificável perpetuamente em `/api/v1/verify`.

### 2.4 `/api/v1/global-markets` — World Financial Calendar & G10 FX
- **Fontes Primárias:**
  1. Cotações oficiais de referência do Banco Central Europeu (ECB Open Data via `api.frankfurter.app/latest?from=USD`) cobrindo USD, EUR, GBP, JPY, CHF, CAD, AUD, CNY, HKD, SGD, MXN, BRL, INR, KRW, SEK, NOK, NZD.
  2. Motor determinístico de calendário financeiro para os 4 maiores hubs globais: `US` (NYSE / Federal Reserve), `EU` (TARGET2 / ECB), `UK` (London Stock Exchange / Bank Holidays) e `BR` (B3 / ANBIMA).
- **Parâmetros:** `market` (`US`, `EU`, `UK`, `BR`, default `US`), `date` (`YYYY-MM-DD`, default hoje UTC), `base` (moeda base, default `USD`).
- **Retorno:** Status de dia útil (`is_business_day`), próximo dia útil (`next_business_day`), feriados oficiais do mercado selecionado no ano e cotações G10/globais atualizadas com `quote_date`.

