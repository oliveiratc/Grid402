# Grid402 // Guia de Divulgação Orgânica e Distribuição M2M

Este guia reúne todos os materiais, comandos e templates prontos para você cadastrar e distribuir o **Grid402** de forma 100% orgânica e passiva.

---

## 1. Publicação nos Gerenciadores de Pacotes Oficiais

### 1.1. Publicar no PyPI (`pip install grid402`)
Os arquivos `pyproject.toml`, `README.md` e `LICENSE` já estão configurados em `sdk/python/`.

1. Instale as ferramentas de build:
   ```bash
   pip install --upgrade build twine
   ```
2. Gere os pacotes (.whl e .tar.gz):
   ```bash
   cd sdk/python
   python -m build
   ```
3. Envie para o PyPI oficial:
   ```bash
   python -m twine upload dist/*
   ```
   *(Insira seu token de API do pypi.org quando solicitado).*

---

### 1.2. Publicar no NPM (`npm install grid402`)
O `package.json`, tipos TypeScript (`grid402.d.ts`) e módulo ESM (`grid402.mjs`) já estão prontos em `sdk/node/`.

1. Acesse o diretório do Node:
   ```bash
   cd sdk/node
   ```
2. Faça login na sua conta npm:
   ```bash
   npm login
   ```
3. Publique o pacote publicamente:
   ```bash
   npm publish --access public
   ```

---

## 2. Cadastro no Base Ecosystem Directory (`base.org/ecosystem`)

A rede Base (Coinbase) possui um diretório oficial onde projetos pioneiros no uso de USDC e agentes autônomos recebem destaque mundial.

* **Link de submissão:** [https://base.org/ecosystem](https://base.org/ecosystem) ou via formulário do Discord oficial da Base (`#builders`).
* **Campos preenchidos para copiar e colar:**
  - **Project Name:** Grid402
  - **Website:** https://grid402.online
  - **Category:** Infrastructure / AI / DePIN / Oracles
  - **Short Tagline:** High-performance public data & network utilities hub for autonomous AI agents powered by x402 micropayments on Base.
  - **Network:** Base Mainnet (Chain ID 8453)
  - **Token Contract:** `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` (USDC)
  - **Oracle Wallet:** `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`
  - **Description:**
    > Grid402 is an autonomous open data and network utility hub tailored for AI agents, crawlers, and M2M automation pipelines. It provides high-speed, zero-PII queries across Foreign Exchange (USD/EUR/BRL), Spot Crypto Tickers, RFC 1035 DNS records, Port 443 TLS/SSL certificates, BGP/ASN routing, and Brazilian CEP/IBGE postal codes. Queries are settled via the HTTP 402 protocol at $0.0050 USDC per query, verified on-chain against Base Mainnet RPC with atomic anti-replay concurrency locks.

---

## 3. Post de Divulgação Técnica (Warpcast / Farcaster e X)

Canais prioritários no Farcaster: `/base`, `/dev`, `/ai-agents`.

### Modelo em Inglês (Farcaster `/base` & `/ai-agents`):
```text
Built an autonomous open data & network utility hub on @base using the HTTP 402 protocol: Grid402 ⚡

AI agents and scraping pipelines can now resolve network utilities with zero signup, zero subscriptions, and instant on-chain micropayments:
- Real-time Forex (USD, EUR, GBP to BRL)
- Spot Crypto Tickers (BTC, ETH, SOL, USDC, BASE)
- DNS Lookup (A, AAAA, MX, TXT, NS, CNAME)
- TLS/SSL Inspector (Cert validity, issuer, days left)
- ASN & BGP routing lookup
- CEP & IBGE postal code resolution

⚡ $0.0050 USDC per technical query on Base Mainnet (Chain ID 8453)
🔒 100% Zero-PII, open datasets, atomic anti-replay locking

Live: https://grid402.online
Crawler docs: https://grid402.online/llms.txt
Free summary: https://grid402.online/api/v1/summary
SDKs: Python & TypeScript
```

### Modelo em Português (LinkedIn / X / Comunidades Dev Brasil):
```text
Lançamos o Grid402 (https://grid402.online): o primeiro hub de dados públicos e utilitários de rede M2M com micropagamentos HTTP 402 na rede Base.

Agentes autônomos, robôs e esteiras de desenvolvimento agora contam com APIs ultrarrápidas sem burocracia de cadastro, cartão de crédito ou mensalidades:
- Câmbio fiduciário (Dólar, Euro, Libra para Real)
- Cotação spot de criptoativos (BTC, ETH, SOL)
- Resolução completa de DNS
- Inspeção de certificados TLS/SSL
- Consulta de ASN/BGP de IPs
- Busca de CEP e código IBGE do município

Custo: apenas US$ 0.0050 USDC por consulta na Base Mainnet, validado on-chain.

Badges dinâmicos SVG para repositórios:
[![Câmbio USD/BRL](https://grid402.online/badge/forex.svg)](https://grid402.online)
[![Cripto BTC/USD](https://grid402.online/badge/crypto.svg)](https://grid402.online)
[![Grid402 Hub](https://grid402.online/badge/system.svg)](https://grid402.online)

SDKs oficiais disponíveis em Python (`pip install grid402`) e Node.js (`npm install grid402`).
```

---

## 4. Submissão em Listas Curadas do GitHub (*Awesome Lists*)

Crie um Pull Request simples adicionando o Grid402 nas listas mais populares:

### 4.1. Repositório: `awesome-base`
Adicionar sob a seção **AI / Infrastructure / Micropayments**:
```markdown
- [Grid402](https://grid402.online) - High-speed open data & network utility hub with HTTP 402 USDC micro-settlement on Base.
```

### 4.2. Repositório: `awesome-ai-agents` ou `awesome-ai-tools`
Adicionar sob **Agent Infrastructure e Utility Hubs**:
```markdown
- [Grid402](https://grid402.online) - Public data & network utilities hub (forex, crypto, DNS, SSL, ASN, CEP) for autonomous AI agents with HTTP 402 micropayments.
```

### 4.3. Repositório: `awesome-brasil`
Adicionar sob **APIs Públicas e Utilitários**:
```markdown
- [Grid402](https://grid402.online) - APIs públicas de alta performance (Câmbio, Cripto, DNS, SSL, ASN, CEP/IBGE) com micropagamentos x402 e badges dinâmicos em SVG.
```
