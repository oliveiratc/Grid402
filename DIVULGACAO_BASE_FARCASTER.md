# Grid402 // Guia Oficial de Lançamento e Distribuição M2M (Base e Farcaster)

Este guia prático contém todas as instruções e templates prontos para você publicar os pacotes nos gerenciadores oficiais (**PyPI** e **npm**) e divulgar o **Grid402** nos canais mais ativos de agentes de IA na rede **Base** e no **Farcaster (Warpcast)**.

---

## 📦 1. Publicação nos Gerenciadores de Pacotes Oficiais

### 1.1. Publicar no PyPI (`pip install grid402`)
Certifique-se de ter uma conta em [pypi.org](https://pypi.org) e um token de API configurado.

```bash
# 1. Acesse o diretório do SDK Python
cd sdk/python

# 2. Instale as ferramentas de empacotamento
pip install --upgrade build twine

# 3. Compile os arquivos de distribuição (wheel e tar.gz)
python -m build

# 4. Publique no PyPI oficial
python -m twine upload dist/*
```
*Após o upload, qualquer desenvolvedor no mundo poderá instalar com `pip install grid402`.*

---

### 1.2. Publicar no NPM (`npm install grid402` e `npx grid402-mcp`)
Certifique-se de estar logado no npm via terminal (`npm login`).

```bash
# 1. Publicar o SDK Node.js principal
cd sdk/node
npm publish --access public

# 2. Publicar o Servidor MCP Oficial
cd ../../mcp
npm publish --access public
```
*Após o upload, o servidor MCP funcionará instantaneamente via `npx grid402-mcp` em qualquer Claude Desktop ou Cursor.*

---

## 🟣 2. Templates de Divulgação para o Farcaster (Warpcast)

### Template 1: Canal `/base` (Foco em Protocolo x402 e Micropagamentos)
> **Cast no Warpcast `/base`:**
> 
> Launched Grid402 ⚡ A high-performance open data and network utilities hub powered by the HTTP 402 protocol on @base!
> 
> Autonomous AI agents can now query real-time data with $0.0005 USDC micro-settlement per call:
> 💱 Forex (USD, EUR, GBP to BRL)  
> 🪙 Crypto Tickers (BTC, ETH, SOL, USDC)  
> 🌐 DNS Lookup (A, AAAA, MX, TXT, NS)  
> 🔒 TLS/SSL Certificate Inspector  
> 🛰️ BGP / ASN Network Intelligence  
> 📍 Brazilian Postal CEP e IBGE  
> 
> Zero API keys. Zero subscriptions. Native x402 Base settlement.
> 
> 🔗 Portal e Live Console: https://grid402.online  
> 📖 Agent Spec: https://grid402.online/llms.txt  
> 💻 MCP Server: `npx -y grid402-mcp`

---

### Template 2: Canal `/ai-agents` (Foco em Claude, Cursor e MCP)
> **Cast no Warpcast `/ai-agents`:**
> 
> Autonomous agents need real-world utilities without API keys or credit card forms.
> 
> Today we're releasing the official Model Context Protocol (MCP) Server for Grid402!
> 
> Add it to your Claude Desktop or Cursor in 10 seconds:
> ```json
> {
>   "mcpServers": {
>     "grid402": {
>       "command": "npx",
>       "args": ["-y", "grid402-mcp"]
>     }
>   }
> }
> ```
> 
> Comes with automated 0.0005 USDC settlement on @base if you supply `GRID402_PRIVATE_KEY`, or free instant health discovery.
> 
> SDKs available for Python (`grid402`) and Node.js. Built for agents by builders. ⚡

---

### Template 3: Canal `/build` (Showcase Técnico de Arquitetura Zero-DB)
> **Cast no Warpcast `/build`:**
> 
> What if utilities like DNS inspection, SSL audits, and Forex rates were accessible to agents for 0.0005 USDC on Base, with < 50ms latency and zero DB overhead?
> 
> That's Grid402.
> - Zero-DB flat-file atomic architecture
> - flock anti-replay protection against double-spend
> - OpenAPI 3.1 e llms.txt discovery
> - Official MCP Server live via npx
> 
> Try the interactive tester: https://grid402.online

---

## 🌟 3. Submissão nos Diretórios Globais de MCP e Agentes

### 3.1. Smithery.ai (Diretório Oficial de Servidores MCP)
O arquivo `mcp/smithery.yaml` já está configurado. Para submeter:
1. Acesse: [https://smithery.ai/submit](https://smithery.ai/submit)
2. Informe a URL do repositório GitHub (`https://github.com/oliveiratc/Grid402`).
3. O Smithery detectará o `mcp/smithery.yaml` e listará o Grid402 no diretório do Claude Desktop automaticamente.

### 3.2. Repositório `awesome-mcp-servers` no GitHub
Abra uma Pull Request em [punkpeye/awesome-mcp-servers](https://github.com/punkpeye/awesome-mcp-servers) adicionando sob a categoria **Network e Web Services**:
```markdown
- [Grid402 MCP Server](https://github.com/oliveiratc/Grid402/tree/main/mcp) - Real-time open data (Forex, Crypto, CEP) and network utility tools (DNS, TLS/SSL, ASN/BGP) with Base x402 micro-settlements.
```

---

## 💻 4. Snippets Rápidos de Exemplo para Desenvolvedores

### Python (CrewAI / LangChain)
```python
from grid402_tool import create_grid402_tool

# Instancia a ferramenta pronta para agentes
tool = create_grid402_tool()

# Executa consulta
dns_result = tool(service="dns", target="github.com")
print(dns_result)
```

### Node.js / TypeScript
```typescript
import { Grid402Client } from 'grid402';

const client = new Grid402Client();
const summary = await client.getSummary();
console.log('Status do Grid402:', summary.status);
```

