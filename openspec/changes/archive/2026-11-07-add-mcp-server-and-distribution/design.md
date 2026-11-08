# Design Técnico: Servidor MCP e Ecossistema de Distribuição M2M

## 1. Arquitetura do MCP Server (`mcp/`)

O servidor MCP implementa o protocolo oficial da Anthropic via transporte `stdio` (entrada/saída padrão), permitindo execução em Claude Desktop, Cursor e plataformas de agentes.

```
[ Claude Desktop / Cursor / Agente ]
               │ (JSON-RPC via stdio)
               ▼
   [ mcp/index.mjs (Grid402 MCP) ]
               │
               ├─► Executa rota pública / gratuita (ex: grid402_summary)
               │     └── Retorna 200 OK imediatamente
               │
               └─► Executa rota protegida (ex: grid402_dns, grid402_ssl)
                     │
                     ▼
             [ Requisição HTTP ao Grid402 ]
                     │
             Retorna 402 Payment Required?
              ┌──────┴──────┐
              ▼             ▼
       [ Sem Chave ]    [ Com GRID402_PRIVATE_KEY ]
              │             │
              │             ├─► Conecta ao RPC da Base Mainnet (8453)
              │             ├─► Transfere 0.0005 USDC (500 units) via ERC-20
              │             ├─► Obtém tx_hash on-chain
              │             └─► Refaz requisição com header 'X-Payment: tx_hash'
              ▼                     │
    Retorna Desafio 402             ▼
    com dados de pagamento   Retorna Dados Completos (200 OK)
```

## 2. Contratos e Schemas das Ferramentas MCP

1. `grid402_summary`:
   - Descrição: "Consulta o resumo consolidado de saúde dos 6 micro-serviços do Grid402 (gratuito)."
   - Input: nenhum parâmetro obrigatório.
2. `grid402_forex`:
   - Descrição: "Cotações fiduciárias em tempo real de USD, EUR e GBP para BRL com variação diária."
   - Input: `{ payment_tx_hash?: string }`
3. `grid402_crypto`:
   - Descrição: "Preços de mercado spot em USD e BRL de BTC, ETH, SOL, USDC e BASE."
   - Input: `{ payment_tx_hash?: string }`
4. `grid402_dns`:
   - Descrição: "Resolução autoritativa de registros DNS (A, AAAA, MX, TXT, NS, CNAME) para qualquer domínio."
   - Input: `{ domain: string, payment_tx_hash?: string }`
5. `grid402_ssl`:
   - Descrição: "Inspeção de certificados TLS/SSL na porta 443 (validade, emissor, SANs e dias até expirar)."
   - Input: `{ host: string, payment_tx_hash?: string }`
6. `grid402_asn`:
   - Descrição: "Inteligência de rede BGP, número ASN, organização e geolocalização de bloco IP."
   - Input: `{ ip?: string, payment_tx_hash?: string }`
7. `grid402_cep`:
   - Descrição: "Consulta de endereçamento postal nacional brasileiro (logradouro, bairro, UF e código IBGE)."
   - Input: `{ code: string, payment_tx_hash?: string }`

## 3. Módulo de Pagamento On-Chain (`x402_payer.mjs`)
- **Rede:** Base Mainnet (Chain ID 8453)
- **Token:** USDC (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`)
- **Valor:** 500 unidades atômicas (0.000500 USDC)
- **Destino:** Carteira oficial do Grid402 (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`)
- **RPC:** `https://mainnet.base.org` com failover para `https://1rpc.io/base` e `https://base.llamarpc.com`.

## 4. Integração com Claude Desktop (`claude_desktop_config.json`)
```json
{
  "mcpServers": {
    "grid402": {
      "command": "node",
      "args": ["/caminho/para/Grid402/mcp/index.mjs"],
      "env": {
        "GRID402_ENDPOINT": "https://orangered-jackal-900744.hostingersite.com",
        "GRID402_PRIVATE_KEY": "0xsua_chave_privada_aqui"
      }
    }
  }
}
```

