# Tarefas de Implementacao: Sincronizacao de SDKs e AgentKit (v2.5.0)

## 1. Atualizacao do Servidor MCP Oficial (v1.6.0)
- [x] 1.1 Adicionar definicao e handler da ferramenta `grid402_verify` em `mcp/tools.mjs`
- [x] 1.2 Atualizar versao para `1.6.0` em `mcp/package.json` e documentacao em `mcp/README.md`
- [x] 1.3 Atualizar suíte `mcp/test_mcp.mjs` para validar a execucao da ferramenta `grid402_verify`

## 2. Atualizacao dos Action Providers do Coinbase AgentKit
- [x] 2.1 Atualizar `integrations/agentkit/grid402_action_provider.ts` com indicadores, feriados, dias uteis e verificacao
- [x] 2.2 Atualizar `integrations/agentkit/grid402_action_provider.py` com indicadores, feriados, dias uteis e verificacao
- [x] 2.3 Atualizar URL padrao de producao e instrucoes em `integrations/agentkit/README.md`

## 3. Sincronizacao do Python SDK (v2.5.0)
- [x] 3.1 Atualizar versao para `2.5.0` em `python/pyproject.toml` e `python/grid402/__init__.py`
- [x] 3.2 Compilar novos pacotes locais `.whl` e `.tar.gz` em `python/dist/`

## 4. Testes e Verificacao
- [x] 4.1 Executar a suíte de testes do servidor MCP (`node mcp/test_mcp.mjs`)
- [x] 4.2 Executar a suíte de testes unitarios do Python SDK (`python/tests/test_sdk.py`)
- [x] 4.3 Executar a suíte de testes unitarios do Node SDK (`sdk/node/test_sdk.mjs`)
- [x] 4.4 Verificar a conformidade estrita da regra de ausencia de ampersand

