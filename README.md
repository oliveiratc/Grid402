<p align="center">
  <img src="public_html/assets/logo-horizontal-transparent.png" alt="Grid402 Logo" width="380">
</p>

# Grid402 // The Verifiable Data and x402 Payment Layer for Autonomous AI Agents

[![CI](https://github.com/oliveiratc/Grid402/actions/workflows/ci.yml/badge.svg)](https://github.com/oliveiratc/Grid402/actions)
[![PayAPI Market](https://img.shields.io/badge/PayAPI_Market-Verified-10B981?logo=checkmarx)](https://payapi.market/api/grid402-brazil-public-data-network-oracle)
[![x402scan](https://img.shields.io/badge/x402scan-Verified_Server-00F0FF)](https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50)
[![Glama](https://img.shields.io/badge/Glama-Verified_MCP-0052FF)](https://glama.ai/mcp/servers/oliveiratc/Grid402)
![Network](https://img.shields.io/badge/Network-Base_Mainnet_(8453)-0052FF?logo=coinbase)
![Payment](https://img.shields.io/badge/Payment-0.0050_a_0.0300_USDC-2775CA?logo=usd-coin)
![Protocol](https://img.shields.io/badge/Protocol-HTTP_x402-00F0FF)
![SDK License](https://img.shields.io/badge/SDKs_%26_MCP-MIT_License-10B981)
![Core License](https://img.shields.io/badge/Gateway_Core-Proprietary-64748B)
[![Brand](https://img.shields.io/badge/Manual_da_Marca-Visual_Identity-0052FF)](MANUAL_DE_MARCA.md)
![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-777BB4?logo=php)
![Server](https://img.shields.io/badge/Server-Hostinger_LiteSpeed-blue)
[![mcptry](https://mcptry.com/badge/io.github.oliveiratc/grid402)](https://mcptry.com/servers/io.github.oliveiratc/grid402)

> **The Verifiable Data and x402 Payment Layer for Autonomous AI Agents.** Infraestrutura M2M de dados públicos verificáveis com assinatura ECDSA secp256k1 (`_oracle_proof`) e liquidação instantânea via HTTP x402 (USDC na rede Base) ou Session Passes pré-pagos sub-15ms.

---

## ⚡ Destaques da Arquitetura

* **Monetização Nativa via HTTP x402 (v2 Exact):** Cobrança por chamada (0.0050 a 0.0300 USDC) liquidada diretamente em USDC na rede Base (Chain ID 8453) via autorizações gasless EIP-3009 (`PAYMENT-SIGNATURE`), Session Passes pré-pagos ou transferências on-chain, sem necessidade de chaves de API estáticas.
* **Amostras Gratuitas no HTTP 402 (Teaser Data):** O desafio 402 inclui uma prévia segura dos dados públicos encontrados (ex: razão social, município e UF no CNPJ; logradouro e bairro no CEP; status TLS no SSL) antes da liquidação financeira.
* **Modo Sandbox para Testes e CI/CD:** Permite simulação técnica via cabeçalho `X-Payment: demo` (cota defensiva por IP), facilitando desenvolvimento local e pipelines de homologação antes de operar em produção.
* **Zero Banco de Dados (Zero-DB):** Arquitetura ultraleve baseada em arquivos JSON planos com travamento concorrente (`flock`) e escrita atômica (`grid402_atomic_write`), sem sobrecarga de conexões SQL.
* **Distribuição Smithery.ai:** Manifesto `smithery.yaml` na raiz para instalação e configuração em 1 clique em clientes MCP como Claude Desktop e Cursor.
* **Coinbase Developer Platform (CDP) AgentKit:** Provedor de ações pronto em TypeScript e Python (`integrations/agentkit/`) para auto-liquidação M2M por agentes autônomos baseados em LangChain e CrewAI.
* **Interface Adaptativa:** Respostas JSON limpas para agentes de IA e clientes M2M, e portal web responsivo em modo escuro com console interativo para desenvolvedores.

---

## 🛠️ Catálogo de Serviços Disponíveis

| Serviço | Rota da API | Custo M2M | Descrição Técnica |
|---|---|---|---|
| **Resumo Operacional** | `GET /status` | Gratuito | Saúde geral, latências médias, uptime 30d e 22 serviços operantes |
| **Câmbio Comercial** | `GET /api/v1/forex` | 0.0050 USDC (1 crédito) | Cotações em tempo real de USD, EUR e GBP para BRL |
| **Preços Cripto** | `GET /api/v1/crypto` | 0.0050 USDC (1 crédito) | Cotações spot e variação 24h de BTC, ETH, SOL, USDC e BASE |
| **Resolução DNS** | `GET /api/v1/dns?domain=...` | 0.0050 USDC (1 crédito) | Registros A, AAAA, MX, TXT, NS e CNAME com latência |
| **Inspetor SSL/TLS** | `GET /api/v1/ssl?host=...` | 0.0050 USDC (1 crédito) | Emissor, validade, dias restantes e cipher suite |
| **Inteligência ASN** | `GET /api/v1/asn?ip=...` | 0.0050 USDC (1 crédito) | BGP, número ASN, organização detentora e país |
| **Auditoria de Domínio** | `GET /api/v1/domain-audit?domain=...` | 0.0150 USDC (3 créditos) | Bundle consolidado DNS + TLS/SSL + BGP/ASN em chamada única |
| **Dossiê KYB Audit** | `GET /api/v1/kyb-audit?cnpj=...` | 0.0200 USDC (4 créditos) | Dossiê corporativo unificado: CNPJ + Simples + Sanções + Score 360 |
| **Dossiê 360º KYB + Veredito IA** | `GET /api/v1/dossier?cnpj=...` | 0.0500 USDC (10 créditos) | Dossiê corporativo premium: RFB + Simples/MEI + CGU CEIS/CNEP + Veredito IA (APPROVED/FLAGGED/REJECTED) |
| **Market Snapshot** | `GET /api/v1/market-snapshot` | 0.0150 USDC (3 créditos) | Panorama macro e financeiro: Câmbio + Cripto + Selic/CDI/IPCA + ANBIMA |
| **Consulta Postal CEP** | `GET /api/v1/cep?code=...` | 0.0050 USDC (1 crédito) | Logradouro, bairro, cidade, UF e código IBGE oficial |
| **Consulta CNPJ** | `GET /api/v1/cnpj?cnpj=...` | 0.0200 USDC (4 créditos) | Razão social, status cadastral, CNAE e dados da Receita Federal |
| **Indicadores BCB/IBGE** | `GET /api/v1/indicators` | 0.0050 USDC (1 crédito) | Taxas oficiais Selic, CDI e IPCA acumulado 12m |
| **Feriados Nacionais** | `GET /api/v1/holidays?year=...` | 0.0050 USDC (1 crédito) | Calendário nacional e bancário ANBIMA/Febraban |
| **Dias Úteis ANBIMA** | `GET /api/v1/workdays` | 0.0050 USDC (1 crédito) | Contagem e projeção de vencimentos D+N bancários |
| **Sanções CEIS/CNEP** | `GET /api/v1/sanctions?cnpj=...` | 0.0100 USDC (2 créditos) | Checagem de inidoneidade e punições administrativas na CGU |
| **Simples Nacional e MEI** | `GET /api/v1/simples?cnpj=...` | 0.0100 USDC (2 créditos) | Enquadramento tributário Simples Nacional e SIMEI |
| **Compliance Score 360** | `GET /api/v1/compliance?cnpj=...` | 0.0300 USDC (6 créditos) | Score de risco corporativo de 0 a 100 cruzando RF e CGU |
| **Identidade Global LEI** | `GET /api/v1/lei?lei=...` | 0.0200 USDC (4 créditos) | Registro corporativo global ISO 17442 via GLEIF (200+ jurisdições) |
| **Sanções OFAC e AML** | `GET /api/v1/ofac?target=...` | 0.0200 USDC (4 créditos) | Triagem OFAC SDN, ONU e risco AML Web3 na Base Mainnet |
| **Notarização SHA-256** | `GET /api/v1/notarize?content=...` | 0.0050 USDC (1 crédito) | Carimbo do tempo criptográfico e prova de existência ECDSA |
| **Mercados Globais e BCE** | `GET /api/v1/global-markets` | 0.0050 USDC (1 crédito) | Calendário US/EU/UK/BR e 30+ taxas de câmbio do BCE |
| **Session Pass x402** | `GET /api/v1/pass` | 2 / 5 / 20 USDC | Emissão de passe pré-pago de sub-15ms (400 / 1.000 / 6.000 créditos) |
| **Saldo do Session Pass** | `GET /api/v1/pass/balance` | Gratuito | Consulta gratuita de créditos restantes, validade e Spend Controls |
| **Verificador de Prova** | `POST /api/v1/verify` | Gratuito | Verificação matemática stateless da assinatura `_oracle_proof` |

---

## 🤖 Formas de Integração para Agentes de IA

### 1. Servidor MCP via Smithery.ai (Claude Desktop e Cursor)

Instalação automática em 1 clique via CLI do Smithery:

```bash
# Para Claude Desktop
npx -y @smithery/cli install grid402-mcp --client claude

# Para Cursor
npx -y @smithery/cli install grid402-mcp --client cursor
```

### 2. PayAPI Market e Claude Desktop / Cursor MCP

O Grid402 é verificado e listado oficialmente no [PayAPI Market](https://payapi.market/api/grid402-brazil-public-data-network-oracle):

```json
{
  "mcpServers": {
    "payapi": {
      "url": "https://payapi.market/mcp"
    }
  }
}
```

### 3. Provedor de Ações Coinbase AgentKit (M2M Auto-Settlement)

Para agentes autônomos que operam na Base com carteiras CDP:

```typescript
import { AgentKit, CdpWalletProvider } from "@coinbase/agentkit";
import { Grid402ActionProvider } from "./integrations/agentkit/grid402_action_provider";

const walletProvider = await CdpWalletProvider.configureWithWallet();
const agentKit = await AgentKit.from({
  walletProvider,
  actionProviders: [new Grid402ActionProvider()]
});
```

Exemplos detalhados em TypeScript e Python disponíveis em [`integrations/agentkit/README.md`](integrations/agentkit/README.md).

### 4. Plugin Oficial para ElizaOS (`grid402-plugin-elizaos`)

Para agentes autônomos no framework [ElizaOS](https://github.com/elizaos/eliza):

```typescript
import { grid402Plugin } from "grid402-plugin-elizaos";

export const character = {
  name: "CryptoComplianceAgent",
  plugins: [grid402Plugin],
  settings: {
    secrets: {
      GRID402_PASS_TOKEN: "pass_g402_live..._..." // Adquira em https://grid402.online/#topup
    }
  }
};
```

Exemplos detalhados e guia de instalação disponíveis em [`integrations/elizaos/README.md`](integrations/elizaos/README.md).

### 5. Integração com LangChain e CrewAI (Python Quickstart)

Ferramenta leve e sem dependências externas pesadas para agentes Python:

```python
from examples.langchain_quickstart import Grid402Client

client = Grid402Client(use_sandbox=True)
forex = client.get_forex()
print("USD/BRL:", forex["rates"]["USD"]["bid"])
```

Exemplo completo com wrappers prontos para `@tool` em [`examples/langchain_quickstart.py`](examples/langchain_quickstart.py).

### 6. Recarga Web3 Multi-Chain em 1 Clique (EVM + Solana SPL USDC)

Para agentes e desenvolvedores que buscam alta frequência (abaixo de 15ms) sem o custo e atrito de assinar 1 transação a cada `GET`, o Grid402 aceita USDC nativamente em **6 redes** (sem taxa de bridge):
- **5 Redes EVM** (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`): **Base**, **Arbitrum One**, **Polygon PoS**, **Optimism** e **Ethereum Mainnet**
- **Solana Mainnet** (`CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`): **SPL USDC** (`EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`)
- Acesse [https://grid402.online/pass](https://grid402.online/pass) ou [https://grid402.online/#topup](https://grid402.online/#topup), escolha a rede e selecione seu pacote (`1 crédito = 0.0050 USDC`):
  - **Starter:** 2 USDC / R$ 12 $\rightarrow$ 400 créditos (= 400 chamadas padrão, validade 30 dias)
  - **Pro:** 5 USDC / R$ 29 $\rightarrow$ 1.000 créditos (= 1.000 chamadas padrão, validade 30 dias)
  - **Fleet:** 20 USDC / R$ 115 $\rightarrow$ 6.000 créditos (5.000 + 1.000 bônus = 6.000 chamadas padrão, validade 60 dias)
- Para pagamentos avulsos multi-chain por chamada, envie `X-Payment: arbitrum:0x...`, `X-Payment: polygon:0x...`, `X-Payment: solana:<assinatura>` ou o cabeçalho `X-Payment-Network`. Na Base, `X-Payment: 0x...` permanece como padrão retrocompatível.

### 7. Dossiê Institucional Base Grants e Coinbase CDP

O plano de crescimento, métricas de rede e candidatura formal a subsídios da Base Foundation e Coinbase Developer Platform estão detalhados em [`docs/BASE_GRANTS_AND_CDP_DOSSIER.md`](docs/BASE_GRANTS_AND_CDP_DOSSIER.md).

### 8. Chamadas Diretas via cURL

#### Consulta com Desafio HTTP 402 e Amostra Gratuita:
```bash
curl -i "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"
```

#### Liquidação On-Chain na Base:
```bash
curl "https://grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: 0x<tx_hash_da_transferencia_na_base>"
```

#### Modo Sandbox Gratuito para Testes:
```bash
curl "https://grid402.online/api/v1/cnpj?cnpj=00000000000191" \
  -H "X-Payment: demo"
```

---

## 📁 Estrutura de Arquivos

```text
├── .env.example                       # Modelo de variáveis de ambiente
├── smithery.yaml                      # Manifesto oficial para o registro Smithery.ai
├── PRD.md                             # Documento de Requisitos do Produto
├── README.md                          # Este guia completo do projeto
├── integrations/                      # Integrações para frameworks de IA
│   └── agentkit/                      # Provedor de ações Coinbase Developer Platform (CDP)
│       ├── grid402_action_provider.ts # Implementação TypeScript para @coinbase/agentkit
│       ├── grid402_action_provider.py # Implementação Python para cdp-agentkit-core
│       └── README.md                  # Guia de uso para LangChain e CrewAI
├── mcp/                               # Pacote oficial Model Context Protocol (grid402-mcp)
│   ├── package.json                   # Configuração e dependências do servidor MCP
│   ├── index.mjs                      # Ponto de entrada do protocolo MCP
│   ├── tools.mjs                      # Implementação das 24 ferramentas MCP e cliente HTTP x402
│   ├── test_mcp.mjs                   # Suíte de testes automatizados do MCP
│   └── README.md                      # Documentação de configuração do servidor MCP
├── public_html/                       # Raiz pública servida na Hostinger
│   ├── index.php                      # Porteiro x402, roteador da API e portal interativo
│   ├── config.php                     # Definições, alvos, provedores e carregador .env
│   ├── x402.php                       # Validador on-chain Base e verificação de recibos
│   ├── openapi.json                   # Especificação OpenAPI 3.1
│   ├── llms.txt                       # Manifesto de descoberta para modelos de linguagem
│   └── private_data/                  # Armazenamento protegido (Zero-DB)
│       ├── summary_free.json          # Cache público do resumo operacional
│       ├── telemetry_paid.json        # Dados consolidados dos serviços
│       ├── sandbox_usage.json         # Controle de limites por IP no Sandbox
│       └── used_txs.json              # Registro de transações já liquidadas
└── tests/                             # Suíte de testes automatizados locais
    ├── test_teaser_preview.php        # Teste de validação de amostras no HTTP 402
    ├── test_sandbox_mode.php          # Teste de validação do Modo Sandbox
    └── test_agentkit_integration.php  # Teste de conformidade Smithery e AgentKit
```

---

## 🧪 Testes Automatizados Locais

Para executar os testes com PHP 8.1+ e Node.js:

```powershell
# 1. Testes de Amostra de Dados (Teaser)
php tests/test_teaser_preview.php

# 2. Testes de Modo Sandbox
php tests/test_sandbox_mode.php

# 3. Testes do Servidor MCP
node mcp/test_mcp.mjs
```

---

## 🔐 Segurança Não-Custodial e Gestão de Chaves M2M

- **Zero Custódia de Chaves de Agentes:** O gateway servidor nunca solicita nem armazena chaves privadas ou seed phrases de clientes.
- **Carteiras Isoladas de Orçamento (Agentes Autônomos):** Ao utilizar `GRID402_PRIVATE_KEY` no `grid402-mcp` ou Coinbase AgentKit, utilize sempre uma carteira operacional dedicada com saldo estritamente limitado ao orçamento de automação (injetada via `.env` ou cofre de segredos, jamais versionada no Git).
- **Session Pass com Spend Controls:** Para eliminar qualquer exposição de chave privada no runtime do agente, emita um `pass_g402_...` e configure `daily_credit_limit` e `allowed_endpoints` com acompanhamento via `GET /api/v1/pass/balance`.

---

## 📄 Licenciamento (Dual-License) e Propriedade Intelectual

O ecossistema Grid402 adota separação explícita de licenças para garantir liberdade total de adoção nos clientes de IA e proteção da infraestrutura proprietária:

| Camada / Diretório | Licença Aplicável | Escopo |
|---|---|---|
| **SDKs, MCP Server e Integrações** (`python/`, `mcp/`, `sdk/`, `integrations/`, `examples/`) | **MIT License** | Uso livre, comercial ou aberto, em agentes autônomos, forks clientes e produtos SaaS (PyPI `grid402`, npm `grid402-mcp`) |
| **Gateway e Plataforma Servidora** (`public_html/`) | **Proprietária (All Rights Reserved)** | Motor HTTP x402, assinador ECDSA do Oráculo, Session Pass, interface web e painel administrativo (`Lei nº 9.609/1998` e `Lei nº 9.610/1998`) |
| **Dados Consultados nas APIs** | **Domínio Público / CC0 / LAI** | Registros governamentais abertos e protocolos IETF sem reserva proprietária |

© 2026 **Grid402 Network Intelligence**. Consulte [LICENSE](LICENSE) e [llms.txt](public_html/llms.txt) para os termos completos.
