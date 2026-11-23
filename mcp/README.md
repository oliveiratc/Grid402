# grid402-mcp ⚡

Servidor oficial do **Model Context Protocol (MCP)** para o **Grid402** — Hub de 25 Micro-Serviços de Dados Públicos Abertos e Utilitários de Rede (27 ferramentas MCP) com liquidação de micropagamentos x402 em USDC Multi-Chain (Base, Arbitrum, Polygon, Optimism, Ethereum e Solana SPL).

Compatível nativamente com **Claude Desktop**, **Cursor**, **Smithery.ai**, **Glama**, **Coinbase AgentKit** e qualquer orquestrador compatível com a especificação MCP da Anthropic.

---

## 🛠️ Ferramentas Disponíveis (27 Tools)

| Ferramenta | Descrição | Modalidade | Custo |
|---|---|---|---|
| `grid402_summary` | Resumo consolidado de saúde dos 25 serviços | Direto | Gratuito |
| `grid402_forex` | Cotações em tempo real de USD, EUR e GBP para BRL | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_crypto` | Preços spot de BTC, ETH, SOL, USDC e BASE | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_dns` | Resolução autoritativa de DNS (A, AAAA, MX, TXT, NS, CNAME) | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_ssl` | Inspeção de validade, emissor e dias restantes de certificados TLS/SSL | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_asn` | Inteligência BGP, número ASN, organização e país de bloco IP | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_domain_audit` | Bundle consolidado de domínio (DNS + TLS/SSL + BGP/ASN) | On-Demand | 0.0150 USDC (3 cr) |
| `grid402_kyb_audit` | Auditoria global KYB em 1 chamada (Receita Federal CNPJ + OFAC/ONU + GLEIF LEI) | On-Demand | 0.0250 USDC (5 cr) |
| `grid402_get_dossier_360` | Dossiê 360º KYB Premium com Veredito IA (APPROVED/FLAGGED/REJECTED), RFB, Simples/MEI e CGU CEIS/CNEP | On-Demand | 0.0500 USDC (10 cr) |
| `grid402_market_snapshot` | Panorama macro consolidado (Câmbio + Cripto + Selic/CDI/IPCA + ANBIMA) | On-Demand | 0.0150 USDC (3 cr) |
| `grid402_cep` | Consulta de endereço postal brasileiro e código IBGE oficial | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_cnpj` | Consulta cadastral de CNPJ da Receita Federal com status e CNAE | On-Demand | 0.0100 USDC (2 cr) |
| `grid402_indicators` | Indicadores macroeconômicos oficiais (Selic meta e diária, CDI anual e IPCA) | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_holidays` | Calendário oficial de feriados nacionais e bancários ANBIMA por ano | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_workdays` | Calculadora de dias úteis financeiros e projeção de vencimentos ANBIMA | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_sanctions` | Checagem de sanções administrativas nos cadastros CEIS e CNEP da CGU | On-Demand | 0.0100 USDC (2 cr) |
| `grid402_simples` | Enquadramento tributário oficial no Simples Nacional e MEI (SIMEI) | On-Demand | 0.0100 USDC (2 cr) |
| `grid402_compliance` | Compliance Score 360 (0 a 100) cruzando Receita Federal e CGU | On-Demand | 0.0300 USDC (6 cr) |
| `grid402_lei` | Identidade corporativa global ISO 17442 via GLEIF (200+ jurisdições) | On-Demand | 0.0100 USDC (2 cr) |
| `grid402_ofac` | Triagem OFAC SDN / ONU e risco AML Web3 na Base Mainnet | On-Demand | 0.0200 USDC (4 cr) |
| `grid402_notarize` | Carimbo do tempo criptográfico SHA-256 e prova de existência ECDSA | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_global_markets` | Calendário de bolsas (US/EU/UK/BR) e 30+ taxas de câmbio do BCE | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_extract` | Extrator anti-SSRF de páginas web HTTP/HTTPS para Markdown limpo (LLMs e RAG) | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_email_verify` | Auditoria Zero-PII de entregabilidade de e-mail B2B (RFC, MX, SPF, DMARC, disposable e score 0-100) | On-Demand | 0.0050 USDC (1 cr) |
| `grid402_pass` | Emissão de Session Pass pré-pago (Starter 400 cr / Pro 1.200 cr / Fleet 6.000 cr) | Pré-Pago | 2 / 5 / 20 USDC |
| `grid402_pass_balance` | Consulta de saldo, validade e Spend Controls de um Session Pass | Direto | Gratuito |
| `grid402_verify` | Validação matemática de prova criptográfica de oráculo (_oracle_proof) secp256k1 | Direto | Gratuito |

---

## 🚀 Como Configurar e Instalar

### 1. Instalação em 1 Clique via Smithery.ai

Se você utiliza a CLI do Smithery, instale diretamente no cliente desejado:

```bash
# Para Claude Desktop
npx -y @smithery/cli install grid402-mcp --client claude

# Para Cursor
npx -y @smithery/cli install grid402-mcp --client cursor
```

O Smithery criará e injetará as configurações automaticamente no seu ambiente.

---

### 2. Configuração Manual no Claude Desktop

Adicione ao seu arquivo de configuração (`claude_desktop_config.json`):
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "grid402": {
      "command": "npx",
      "args": ["-y", "grid402-mcp"],
      "env": {
        "GRID402_ENDPOINT": "https://grid402.online",
        "GRID402_SANDBOX": "false",
        "GRID402_SESSION_PASS": "pass_g402_seu_passe_aqui_opcional",
        "GRID402_PAYMENT_NETWORK": "base",
        "GRID402_PRIVATE_KEY": "0xsua_chave_privada_evm_opcional",
        "GRID402_SOLANA_PRIVATE_KEY": "sua_chave_privada_solana_base58_opcional"
      }
    }
  }
}
```

> 💡 **Modo Sandbox Gratuito:** Você pode definir `"GRID402_SANDBOX": "true"` para testar todas as ferramentas sem gastar USDC nem configurar chave privada (limite de 3 consultas por dia por IP).
>
> 💡 **Session Pass (Sem Chave Privada):** Se preferir não informar `GRID402_PRIVATE_KEY`, adquira um passe pré-pago em `https://grid402.online/pass` e defina `"GRID402_SESSION_PASS": "pass_g402_..."` para execução em `<15ms`.
>
> 💡 **Auto-Payer Multi-Chain (Base, Polygon, Arbitrum e Solana):** O módulo `x402_payer.mjs` suporta liquidação automática Multi-Chain tanto para carteiras EVM (`GRID402_PRIVATE_KEY` em `base`, `polygon`, `arbitrum`, `optimism`, `ethereum`, com suporte a modo gasless EIP-3009 `GRID402_X402_GASLESS=true`) quanto para carteiras **Solana SPL USDC** (`GRID402_SOLANA_PRIVATE_KEY` em Base58 ou JSON array com `GRID402_PAYMENT_NETWORK=solana`, on-chain ou gasless PayAI SVM). As chaves são processadas estritamente na sua máquina local e nunca são enviadas ao servidor do Grid402.

---

### 3. Configuração Manual no Cursor IDE

No seu projeto ou globalmente em `~/.cursor/mcp.json`:
```json
{
  "mcpServers": {
    "grid402": {
      "command": "npx",
      "args": ["-y", "grid402-mcp"],
      "env": {
        "GRID402_SANDBOX": "true"
      }
    }
  }
}
```

---

### 4. Agentes Autônomos com Coinbase AgentKit (CDP)

Para robôs que operam nativamente na Base utilizando o Coinbase AgentKit, utilize o provedor de ações pronto em TypeScript ou Python:

- **TypeScript:** `integrations/agentkit/grid402_action_provider.ts`
- **Python:** `integrations/agentkit/grid402_action_provider.py`
- **Guia Completo:** Veja `integrations/agentkit/README.md`

O provedor gerencia automaticamente o ciclo de vida HTTP 402, transferindo os micropagamentos em USDC pela carteira CDP do agente e reenviando a requisição de forma transparente.

---

## 🛡️ Prova Criptografica de Oraculo (Proof of Data)

Todas as ferramentas pagas e em sandbox retornam o bloco `_oracle_proof` com garantia matematica de integridade e assinatura ECDSA secp256k1 emitida pela carteira oficial do Grid402 (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`). Isso permite que agentes validem que os dados consumidos sao legitimos e nao sofreram alteracao durante o trânsito.

---

## 🧪 Testando Localmente

```bash
cd mcp
node test_mcp.mjs
```

Licença MIT • Grid402 Team
