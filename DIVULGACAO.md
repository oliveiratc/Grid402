# Grid402 // Guia de Divulgação Orgânica & Distribuição M2M

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
  - **Website:** https://www.grid402.com.br
  - **Category:** Infrastructure / AI / DePIN
  - **Short Tagline:** Real-time M2M telemetry oracle for Brazilian infrastructure powered by x402 micro-settlements on Base.
  - **Network:** Base Mainnet (Chain ID 8453)
  - **Token Contract:** `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` (USDC)
  - **Oracle Wallet:** `0xa31c31069c43ac1c0075a93c325b0dcd7744356c`
  - **Description:**
    > Grid402 is an autonomous telemetry oracle designed for AI agents and enterprise backends operating in Brazil. It provides millisecond-precise health and latency metrics across government tax authorities (SEFAZ), central bank payments (BACEN / PIX), and financial gateways. Queries are monetized autonomously via the HTTP 402 protocol at $0.0005 USDC per query, verified on-chain against Base Mainnet RPC.

---

## 3. Post de Divulgação Técnica (Warpcast / Farcaster & X)

Canais prioritários no Farcaster: `/base`, `/dev`, `/ai-agents`.

### Modelo em Inglês (Farcaster `/base` & `/ai-agents`):
```text
Built an autonomous M2M telemetry oracle on @base using the HTTP 402 protocol: Grid402 ⚡

AI agents can now inspect the real-time availability of Brazilian digital infrastructure (SEFAZ tax authorizers, PIX/Central Bank, payment gateways) before firing transaction batches.

- Free tier: aggregated health summary
- Paid tier: $0.0005 USDC micro-payment per query on Base Mainnet
- Zero-DB, pure L1/L2 on-chain receipt verification

Live now: https://www.grid402.com.br
Agent doc: https://www.grid402.com.br/llms.txt
SDKs: Python & TypeScript
```

### Modelo em Português (LinkedIn / X / Comunidades Dev Brasil):
```text
Lançamos o Grid402 (https://www.grid402.com.br): o primeiro oráculo de telemetria M2M para a infraestrutura digital brasileira usando micropagamentos HTTP 402 na rede Base.

Sistemas de ERP, bots e agentes de IA agora podem checar a saúde e latência da SEFAZ (SP, RS, MG, PR), BACEN (PIX) e gateways antes de disparar lotes de transações.

Além da API, disponibilizamos badges SVG dinâmicos para você colocar no README do seu repositório open-source de NF-e ou PIX:

[![SEFAZ SP Status](https://www.grid402.com.br/badge/sefaz_sp.svg)](https://www.grid402.com.br)

SDKs oficiais em Python e Node.js disponíveis!
```

---

## 4. Submissão em Listas Curadas do GitHub (*Awesome Lists*)

Crie um Pull Request simples adicionando o Grid402 nas listas mais populares:

### 4.1. Repositório: `awesome-base`
Adicionar sob a seção **AI / Infrastructure**:
```markdown
- [Grid402](https://www.grid402.com.br) - Real-time Brazilian digital infrastructure telemetry oracle with HTTP 402 USDC micro-settlement on Base.
```

### 4.2. Repositório: `awesome-ai-agents` ou `awesome-ai-tools`
Adicionar sob **Agent Infrastructure & Oracles**:
```markdown
- [Grid402](https://www.grid402.com.br) - Infrastructure telemetry oracle and circuit-breaker for autonomous AI agents with HTTP 402 micropayments.
```

### 4.3. Repositório: `awesome-brasil`
Adicionar sob **APIs Públicas e Infraestrutura**:
```markdown
- [Grid402](https://www.grid402.com.br) - Monitoramento em tempo real de autorizadores fiscais (SEFAZ), PIX/BACEN e gateways de pagamento com badges dinâmicos para README.
```

---

## 5. Viralização Passiva via Badges nos Repositórios de NF-e

Muitos desenvolvedores mantêm repositórios de SPED / NF-e / PIX no GitHub. Você pode abrir uma issue ou PR sugerindo a inclusão do status da SEFAZ no topo do README deles:

**Exemplo de sugestão gentil em issue:**
> "Olá pessoal! Parabéns pelo projeto. Como muitos usuários costumam abrir issues achando que a biblioteca está com bug quando na verdade é a SEFAZ que caiu, sugiro adicionar o badge de status em tempo real no topo do README:
> 
> `[![SEFAZ SP Status](https://www.grid402.com.br/badge/sefaz_sp.svg)](https://www.grid402.com.br)`
> 
> Ele atualiza a cada 2 minutos automaticamente."

