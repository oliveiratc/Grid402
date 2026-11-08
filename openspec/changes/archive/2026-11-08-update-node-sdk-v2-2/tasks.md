## 1. Atualizacao dos Modulos do SDK
- [x] 1.1 Atualizar `sdk/node/grid402.mjs` com suporte a `sandbox: true`, `getCnpj`, `preview_data` e exportacao do `Grid402ActionProvider`.
- [x] 1.2 Criar wrapper CommonJS `sdk/node/grid402.cjs` para suporte nativo a `require()`.
- [x] 1.3 Atualizar definicoes TypeScript em `sdk/node/grid402.d.ts` cobrindo todas as classes, interfaces e metodos.
- [x] 1.4 Atualizar `sdk/node/package.json` para versao `2.2.0`, export maps duais (ESM e CJS) e arquivos inclusos no tarball.
- [x] 1.5 Atualizar documentacao em `sdk/node/README.md` com exemplos de Sandbox, AgentKit e TypeScript.

## 2. Testes e Validacao
- [x] 2.1 Criar suite de testes unitarios em `sdk/node/test_sdk.mjs` cobrindo cliente, erros, sandbox e mock do AgentKit.
- [x] 2.2 Criar teste de compatibilidade CommonJS em `sdk/node/test_cjs.cjs`.
- [x] 2.3 Criar teste de integracao ao vivo em `sdk/node/test_live_sandbox.mjs` contra a API de producao.
- [x] 2.4 Executar todos os testes e garantir 100% de sucesso.

## 3. Empacotamento e Publicacao
- [x] 3.1 Executar teste de empacotamento local com `npm pack` em `sdk/node/` e inspecionar o tarball gerado.
- [x] 3.2 Fornecer instrucoes e script para publicacao segura da versao `2.2.0` no npm.
