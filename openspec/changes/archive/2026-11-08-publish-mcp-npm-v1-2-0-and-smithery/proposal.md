# Publicação do Pacote npm v1.2.0 e Distribuição no Smithery.ai

## Why
O pacote `grid402-mcp` atualmente registrado no npm está na versão `1.1.0`. Desde essa publicação, adicionamos suporte à ferramenta `grid402_cnpj` (consulta cadastral de CNPJ na Receita Federal) e ao Modo Sandbox gratuito (`GRID402_SANDBOX=true`). Desenvolvedores e agentes que executam `npx -y grid402-mcp` ou instalam o servidor pelo Smithery precisam ter acesso imediato às 8 ferramentas completas e ao modo de testes sem custos.
Referência: `openspec/prds/grid402-npm-release-and-smithery-distribution.md`.

## What Changes
- Atualização da versão do pacote em `mcp/package.json` de `1.1.0` para `1.2.0`.
- Otimização do empacotamento com o campo `files` contendo apenas os artefatos de execução essenciais.
- Atualização das palavras-chave e metadados no `package.json` para refletir CNPJ e Sandbox.
- Execução de testes de empacotamento e conformidade técnica (`test_mcp.mjs`).
- Publicação oficial no registro público do npm sob a conta autenticada `oliveiratco1`.
- Validação pós-publicação da resolução remota via `npx -y grid402-mcp@latest` e `smithery.yaml`.

## Capabilities
### New Capabilities
- `npm-distribution-v1-2-0`: Disponibilidade global do pacote `grid402-mcp@1.2.0` no npm contendo as 8 ferramentas e suporte a Modo Sandbox.

### Modified Capabilities
- `mcp-server`: Atualização da versão distribuída publicamente de `1.1.0` para `1.2.0`.

## Impact
- **Specs afetadas:** `mcp-server`
- **Código afetado:** `mcp/package.json`

