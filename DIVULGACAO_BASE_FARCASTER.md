# Grid402 // Guia Oficial de LanÃ§amento e DistribuiÃ§Ã£o M2M (Base e Farcaster)

Este guia prÃ¡tico contÃ©m todas as instruÃ§Ãµes e templates prontos para vocÃª publicar os pacotes nos gerenciadores oficiais (**PyPI** e **npm**) e divulgar o **Grid402** nos canais mais ativos de agentes de IA na rede **Base** e no **Farcaster (Warpcast)**.

---

## ðŸ“¦ 1. PublicaÃ§Ã£o nos Gerenciadores de Pacotes Oficiais

### 1.1. Publicar no PyPI (`pip install grid402`)
Certifique-se de ter uma conta em [pypi.org](https://pypi.org) e um token de API configurado.

```bash
# 1. Acesse o diretÃ³rio do SDK Python
cd sdk/python

# 2. Instale as ferramentas de empacotamento
pip install --upgrade build twine

# 3. Compile os arquivos de distribuiÃ§Ã£o (wheel e tar.gz)
python -m build

# 4. Publique no PyPI oficial
python -m twine upload dist/*
```
*ApÃ³s o upload, qualquer desenvolvedor no mundo poderÃ¡ instalar com `pip install grid402`.*

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
*ApÃ³s o upload, o servidor MCP funcionarÃ¡ instantaneamente via `npx grid402-mcp` em qualquer Claude Desktop ou Cursor.*

---

## ðŸŸ£ 2. Templates de DivulgaÃ§Ã£o para o Farcaster (Warpcast)

### Template 1: Canal `/base` (Foco em Protocolo x402 e Micropagamentos)
> **Cast no Warpcast `/base`:**
> 
> Launched Grid402 âš¡ A high-performance open data and network utilities hub powered by the HTTP 402 protocol on @base!
> 
> Autonomous AI agents can now query real-time data with $0.0050 USDC micro-settlement per call:
> ðŸ’± Forex (USD, EUR, GBP to BRL)  
> ðŸª™ Crypto Tickers (BTC, ETH, SOL, USDC)  
> ðŸŒ DNS Lookup (A, AAAA, MX, TXT, NS)  
> ðŸ”’ TLS/SSL Certificate Inspector  
> ðŸ›°ï¸ BGP / ASN Network Intelligence  
> ðŸ“ Brazilian Postal CEP e IBGE  
> 
> Zero API keys. Zero subscriptions. Native x402 Base settlement.
> 
> ðŸ”— Portal e Live Console: https://grid402.online  
> ðŸ“– Agent Spec: https://grid402.online/llms.txt  
> ðŸ’» MCP Server: `npx -y grid402-mcp`

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
> Comes with automated 0.0050 USDC settlement on @base if you supply `GRID402_PRIVATE_KEY`, or free instant health discovery.
> 
> SDKs available for Python (`grid402`) and Node.js. Built for agents by builders. âš¡

---

### Template 3: Canal `/build` (Showcase TÃ©cnico de Arquitetura Zero-DB)
> **Cast no Warpcast `/build`:**
> 
> What if utilities like DNS inspection, SSL audits, and Forex rates were accessible to agents for 0.0050 USDC on Base, with < 50ms latency and zero DB overhead?
> 
> That's Grid402.
> - Zero-DB flat-file atomic architecture
> - flock anti-replay protection against double-spend
> - OpenAPI 3.1 e llms.txt discovery
> - Official MCP Server live via npx
> 
> Try the interactive tester: https://grid402.online

---

## ðŸŒŸ 3. SubmissÃ£o nos DiretÃ³rios Globais de MCP e Agentes

### 3.1. Smithery.ai (DiretÃ³rio Oficial de Servidores MCP)
O arquivo `mcp/smithery.yaml` jÃ¡ estÃ¡ configurado. Para submeter:
1. Acesse: [https://smithery.ai/submit](https://smithery.ai/submit)
2. Informe a URL do repositÃ³rio GitHub (`https://github.com/oliveiratc/Grid402`).
3. O Smithery detectarÃ¡ o `mcp/smithery.yaml` e listarÃ¡ o Grid402 no diretÃ³rio do Claude Desktop automaticamente.

### 3.2. RepositÃ³rio `awesome-mcp-servers` no GitHub
Abra uma Pull Request em [punkpeye/awesome-mcp-servers](https://github.com/punkpeye/awesome-mcp-servers) adicionando sob a categoria **Network e Web Services**:
```markdown
- [Grid402 MCP Server](https://github.com/oliveiratc/Grid402/tree/main/mcp) - Real-time open data (Forex, Crypto, CEP) and network utility tools (DNS, TLS/SSL, ASN/BGP) with Base x402 micro-settlements.
```

---

## ðŸ’» 4. Snippets RÃ¡pidos de Exemplo para Desenvolvedores

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

