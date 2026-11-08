## 1. Atualização e Validação do Pacote
- [x] 1.1 Atualizar `mcp/package.json` para versão `1.2.0`, adicionando campo `files` e palavras-chave de CNPJ e Sandbox.
- [x] 1.2 Executar testes automatizados do MCP localmente (`node mcp/test_mcp.mjs`).
- [x] 1.3 Simular o empacotamento com `npm pack --dry-run` e verificar o conteúdo do tarball.

## 2. Publicação no npm
- [x] 2.1 Publicar a versão `1.2.0` no npm com acesso público via `npm publish --access public`.
- [x] 2.2 Validar a propagação da versão `1.2.0` no registro com `npm view grid402-mcp version`.

## 3. Verificação e Integração Smithery
- [x] 3.1 Executar teste de inicialização do binário remoto via `npx -y grid402-mcp@1.2.0`.
- [x] 3.2 Validar a compatibilidade contínua com o manifesto `smithery.yaml`.

