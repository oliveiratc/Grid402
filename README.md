<p align="center">
  <img src="public_html/assets/logo-horizontal-transparent.png" alt="Grid402 Logo" width="380">
</p>

# Grid402 // Hub de Utilitários de Rede e Dados Públicos Abertos

[![CI](https://github.com/oliveiratc/Grid402/actions/workflows/ci.yml/badge.svg)](https://github.com/oliveiratc/Grid402/actions)
![Network](https://img.shields.io/badge/Network-Base_Mainnet_(8453)-0052FF?logo=coinbase)
![Payment](https://img.shields.io/badge/Payment-0.0005_a_0.0020_USDC-2775CA?logo=usd-coin)
![Protocol](https://img.shields.io/badge/Protocol-HTTP_x402-00F0FF)
![Brand](https://img.shields.io/badge/Manual_da_Marca-Visual_Identity-0052FF)](MANUAL_DE_MARCA.md)
![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-777BB4?logo=php)
![Server](https://img.shields.io/badge/Server-Hostinger_LiteSpeed-blue)

> Infraestrutura de dados abertos e utilitários de rede de alta velocidade para agentes autônomos de IA e sistemas M2M. Consultas de câmbio, cripto, DNS, certificados SSL, BGP/ASN, CEP e CNPJ da Receita Federal com liquidação de micropagamentos sob demanda via protocolo HTTP x402 em USDC na rede Base.

---

## ⚡ Destaques da Arquitetura

* **Monetização Nativa via HTTP x402:** Cobrança por chamada (0.0005 a 0.0020 USDC) liquidada diretamente em USDC na rede Base (Chain ID 8453), sem necessidade de chaves de API ou cadastro prévio.
* **Amostras Gratuitas no HTTP 402 (Teaser Data):** O desafio 402 inclui uma prévia segura dos dados públicos encontrados (ex: razão social, município e UF no CNPJ; logradouro e bairro no CEP; status TLS no SSL) antes da liquidação financeira.
* **Modo Sandbox Gratuito para Testes:** Permite simulação instantânea via cabeçalho `X-Payment: demo` (com cota de 20 requisições por hora por IP), facilitando desenvolvimento local e pipelines CI/CD.
* **Zero Banco de Dados (Zero-DB):** Arquitetura ultraleve baseada em arquivos JSON planos com travamento concorrente (`flock`) e escrita atômica (`grid402_atomic_write`), sem sobrecarga de conexões SQL.
* **Distribuição Smithery.ai:** Manifesto `smithery.yaml` na raiz para instalação e configuração em 1 clique em clientes MCP como Claude Desktop e Cursor.
* **Coinbase Developer Platform (CDP) AgentKit:** Provedor de ações pronto em TypeScript e Python (`integrations/agentkit/`) para auto-liquidação M2M por agentes autônomos baseados em LangChain e CrewAI.
* **Interface Adaptativa:** Respostas JSON limpas para agentes de IA e clientes M2M, e portal web responsivo em modo escuro com console interativo para desenvolvedores.

---

## 🛠️ Catálogo de Serviços Disponíveis

| Serviço | Rota da API | Custo M2M | Descrição Técnica |
|---|---|---|---|
| **Resumo Operacional** | `GET /status` | Gratuito | Saúde geral, latências médias e serviços operantes |
| **Câmbio Comercial** | `GET /api/v1/forex` | 0.0005 USDC | Cotações em tempo real de USD, EUR e GBP para BRL |
| **Preços Cripto** | `GET /api/v1/crypto` | 0.0005 USDC | Cotações spot e variação 24h de BTC, ETH, SOL, USDC e BASE |
| **Resolução DNS** | `GET /api/v1/dns?domain=...` | 0.0005 USDC | Registros A, AAAA, MX, TXT, NS e CNAME com latência |
| **Inspetor SSL/TLS** | `GET /api/v1/ssl?domain=...` | 0.0005 USDC | Emissor, validade, dias restantes e cipher suite |
| **Inteligência ASN** | `GET /api/v1/asn?ip=...` | 0.0005 USDC | BGP, número ASN, organização detentora e país |
| **Consulta Postal CEP** | `GET /api/v1/cep?code=...` | 0.0005 USDC | Logradouro, bairro, cidade, UF e código IBGE oficial |
| **Consulta CNPJ** | `GET /api/v1/cnpj?cnpj=...` | 0.0020 USDC | Razão social, status cadastral, CNAE e dados da Receita Federal |

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

### 2. Provedor de Ações Coinbase AgentKit (M2M Auto-Settlement)

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

### 3. Plugin Oficial para ElizaOS (`grid402-plugin-elizaos`)

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

### 4. Recarga Web3 em 1 Clique (Session Passes na Base)

Para agentes e desenvolvedores que buscam alta frequência (abaixo de 15ms) sem o custo e atrito de assinar 1 transação a cada `GET`:
- Acesse a interface web em [https://grid402.online/#topup](https://grid402.online/#topup)
- Conecte sua carteira (MetaMask, Coinbase Smart Wallet com Passkey, Rainbow) na rede Base
- Selecione seu pacote:
  - **Starter:** 2 USDC $\rightarrow$ 4.000 requisições (validade 30 dias)
  - **Pro:** 5 USDC $\rightarrow$ 10.000 requisições (validade 30 dias)
  - **Fleet:** 20 USDC $\rightarrow$ 50.000 requisições (+10k bônus, validade 60 dias)
- Receba instantaneamente seu token `pass_g402_live...` para usar no cabeçalho `X-Payment`.

### 5. Dossiê Institucional Base Grants e Coinbase CDP

O plano de crescimento, métricas de rede e candidatura formal a subsídios da Base Foundation e Coinbase Developer Platform estão detalhados em [`docs/BASE_GRANTS_AND_CDP_DOSSIER.md`](docs/BASE_GRANTS_AND_CDP_DOSSIER.md).

### 6. Chamadas Diretas via cURL

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
│   ├── tools.mjs                      # Implementação das 7 ferramentas e cliente HTTP x402
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

## 📄 Direitos Autorais e Propriedade Intelectual
© 2026 **Grid402 Network Intelligence**. **Todos os direitos reservados / All rights reserved.**
O software da plataforma Grid402, motor de liquidação HTTP 402, arquitetura de Session Pass, design visual e marca são protegidos pela Lei de Software (Lei nº 9.609/1998), Lei de Direitos Autorais (Lei nº 9.610/1998) e Convenção de Berna. Os dados consultados nos endpoints provêm de fontes públicas abertas e registros em domínio público (CC0, LAI, 17 U.S.C. § 105). Consulte [LICENSE](LICENSE) e [llms.txt](public_html/llms.txt) para os termos completos.
