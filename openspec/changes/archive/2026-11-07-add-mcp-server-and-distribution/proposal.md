# Proposta: Servidor MCP Oficial e Distribuição M2M para Agentes de IA

## Por quê
O Grid402 está operacional e testado na Hostinger com 6 micro-serviços de alta demanda e monetização x402 em USDC na Base. Para viabilizar adoção em massa e faturamento recorrente sem depender do apontamento imediato de domínio customizado, é necessário disponibilizar os conectores nativos onde os agentes operam:
1. **Model Context Protocol (MCP):** Padrão adotado por Claude Desktop, Cursor e plataformas como Smithery.ai.
2. **Pagamento Automático x402:** Capacidade de o MCP Server e SDKs liquidarem consultas pagas de US$ 0.0005 USDC automaticamente caso o operador configure uma chave privada no `.env`.
3. **Distribuição nos Registros Oficiais:** Atualização dos pacotes Python (PyPI) e Node.js (NPM) e templates para Farcaster e comunidades de IA na Base.
Referência completa: [`openspec/prds/grid402-mcp-server-and-distribution.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-mcp-server-and-distribution.md).

## O que muda
- Criação de pacote Node.js dedicado `mcp/` implementando o servidor MCP com `@modelcontextprotocol/sdk` (transporte `stdio`).
- Exportação de 7 ferramentas MCP (`forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `summary`).
- Adição de módulo de pagamento assistido em ethers/viem para transferência automática de 0.0005 USDC na Base quando `GRID402_PRIVATE_KEY` for informada.
- Adição de `smithery.yaml` e documentação de configuração para Claude Desktop e Cursor.
- Atualização e homologação dos SDKs `sdk/python` e `sdk/node`.
- Publicação de guia prático de divulgação para o ecossistema Base e Farcaster.

## Impacto
- **Specs afetadas:** `mcp-server` (nova spec)
- **Código afetado:** Nova pasta `mcp/`, atualizações em `sdk/python/` e `sdk/node/`, novo guia `DIVULGACAO_BASE_FARCASTER.md`.

