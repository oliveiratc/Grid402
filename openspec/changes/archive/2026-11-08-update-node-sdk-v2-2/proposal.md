# Proposta de Modernizacao do SDK Node.js e TypeScript Grid402 v2.2.0

## Por que

Conforme estabelecido no PRD ([`openspec/prds/grid402-node-sdk-v2-2-release.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-node-sdk-v2-2-release.md)), o pacote `grid402` publicado no npm na versao `2.1.0` necessita de uma atualizacao para paridade total com os recursos recentes do hub:
1. Suporte nativo ao Modo Sandbox gratuito (`sandbox: true`);
2. Consulta de CNPJ da Receita Federal com pre-visualizacao cadastral;
3. Integracao com Coinbase AgentKit (`Grid402ActionProvider`) para auto-liquidacao de micropagamentos na Base;
4. Enriquecimento de `PaymentRequiredError` com dados de teaser e parametros x402;
5. Suporte hibrido ESM e CommonJS com tipagem TypeScript (`.d.ts`).

## O que muda

- Atualizacao de `sdk/node/grid402.mjs` adicionando `sandbox`, `preview_data` e `Grid402ActionProvider`.
- Criacao de `sdk/node/grid402.cjs` para suporte nativo a projetos CommonJS (`require('grid402')`).
- Atualizacao das tipagens em `sdk/node/grid402.d.ts`.
- Bump de versao para `2.2.0` no `package.json` com export maps duais (ESM e CJS).
- Criacao de suites de testes automatizados (`test_sdk.mjs` e `test_live_sandbox.mjs`).
- Scripts seguros para publicacao no npm.

## Impacto

- Specs afetadas: `node-sdk`
- Codigo afetado:
  - `sdk/node/grid402.mjs`
  - `sdk/node/grid402.cjs`
  - `sdk/node/grid402.d.ts`
  - `sdk/node/package.json`
  - `sdk/node/README.md`
  - `sdk/node/test_sdk.mjs`

