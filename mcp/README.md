# grid402-mcp ⚡

Servidor oficial do **Model Context Protocol (MCP)** para o **Grid402** — Hub de Utilitários de Rede e Dados Públicos Abertos com liquidação de micropagamentos x402 em USDC na rede Base.

Compatível nativamente com **Claude Desktop**, **Cursor**, **Smithery.ai**, **Coinbase AgentKit** e qualquer orquestrador compatível com a especificação MCP da Anthropic.

---

## 🛠️ Ferramentas Disponíveis

| Ferramenta | Descrição | Modalidade | Custo |
|---|---|---|---|
| `grid402_summary` | Resumo consolidado de saúde dos serviços | Direto | Gratuito |
| `grid402_forex` | Cotações em tempo real de USD, EUR e GBP para BRL | On-Demand | 0.0005 USDC |
| `grid402_crypto` | Preços spot de BTC, ETH, SOL, USDC e BASE | On-Demand | 0.0005 USDC |
| `grid402_dns` | Resolução autoritativa de DNS (A, AAAA, MX, TXT, NS, CNAME) | On-Demand | 0.0005 USDC |
| `grid402_ssl` | Inspeção de validade, emissor e dias restantes de certificados TLS/SSL | On-Demand | 0.0005 USDC |
| `grid402_asn` | Inteligência BGP, número ASN, organização e país de bloco IP | On-Demand | 0.0005 USDC |
| `grid402_cep` | Consulta de endereço postal brasileiro e código IBGE oficial | On-Demand | 0.0005 USDC |
| `grid402_cnpj` | Consulta cadastral de CNPJ da Receita Federal com status e CNAE | On-Demand | 0.0020 USDC |
| `grid402_indicators` | Indicadores macroeconômicos oficiais (Selic meta e diária, CDI anual e IPCA) | On-Demand | 0.0005 USDC |
| `grid402_holidays` | Calendário oficial de feriados nacionais e bancários ANBIMA por ano | On-Demand | 0.0005 USDC |
| `grid402_workdays` | Calculadora de dias úteis financeiros e projeção de vencimentos ANBIMA | On-Demand | 0.0005 USDC |
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
        "GRID402_PRIVATE_KEY": "0xsua_chave_privada_base_aqui_opcional"
      }
    }
  }
}
```

> 💡 **Modo Sandbox Gratuito:** Você pode definir `"GRID402_SANDBOX": "true"` para testar todas as ferramentas sem gastar USDC nem configurar chave privada (limite de 3 consultas por hora por IP).
>
> 💡 **Session Pass (Sem Chave Privada):** Se preferir não informar `GRID402_PRIVATE_KEY`, adquira um passe pré-pago em `https://grid402.online/#topup` e defina `"GRID402_SESSION_PASS": "pass_g402_..."` para execução em `<15ms`.
>
> 💡 **Nota de Segurança:** A variável `GRID402_PRIVATE_KEY` é opcional. Se informada, o servidor MCP realiza a transferência automática de USDC on-chain na Base a cada consulta paga. Se não informada ou sem saldo, o servidor retorna o desafio x402 com amostra de dados. A chave é processada estritamente na sua máquina local e nunca é enviada ao servidor do Grid402.

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
