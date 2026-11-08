# Tarefas de Implementação: Servidor MCP e Distribuição M2M

## 1. Pacote e Servidor MCP Oficial (Node.js)
- [x] 1.1 Criar estrutura `mcp/package.json` com `@modelcontextprotocol/sdk` e `ethers`
- [x] 1.2 Implementar `mcp/x402_payer.mjs` para transferência automática de 0.0005 USDC na Base
- [x] 1.3 Implementar `mcp/tools.mjs` com as 7 ferramentas e seus respectivos schemas JSON
- [x] 1.4 Implementar `mcp/index.mjs` como executável com transporte `StdioServerTransport`
- [x] 1.5 Criar manifesto `mcp/smithery.yaml` para o marketplace Smithery.ai
- [x] 1.6 Criar teste automatizado do MCP Server `mcp/test_mcp.mjs`

## 2. Atualização e Empacotamento dos SDKs (Python e Node.js)
- [x] 2.1 Atualizar `sdk/node/package.json` com metadados do hub, novos utilitários e build
- [x] 2.2 Testar e validar `sdk/node/test_sdk.mjs`
- [x] 2.3 Atualizar `sdk/python/pyproject.toml` e `sdk/python/grid402.py` para suporte a `GRID402_PRIVATE_KEY`
- [x] 2.4 Testar e validar `sdk/python/test_sdk.py`

## 3. Materiais de Lançamento e Divulgação no Ecossistema Base / Farcaster
- [x] 3.1 Criar `DIVULGACAO_BASE_FARCASTER.md` com templates em inglês e português para Warpcast (`/base`, `/ai-agents`), GitHub e comunidades
- [x] 3.2 Incluir instruções passo a passo para publicação no PyPI (`twine upload`) e npm (`npm publish`)

## 4. Homologação e Verificação
- [x] 4.1 Executar toda a suíte de testes locais e verificar conformidade com a spec
