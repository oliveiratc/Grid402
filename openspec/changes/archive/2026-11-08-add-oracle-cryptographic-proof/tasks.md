# Tasks: Assinatura Criptografica de Oraculo e Prova de Autenticidade

## 1. Backend PHP: Assinador de Oraculo

- [x] 1.1 Criar `public_html/services/oracle_signer.php` com canonizacao de JSON, calculo de hash e assinatura ECDSA secp256k1
- [x] 1.2 Integrar injecao automatica do `_oracle_proof` no despachante de respostas em `public_html/index.php`
- [x] 1.3 Adicionar constantes de configuracao do oraculo em `public_html/config.php`
- [x] 1.4 Criar teste automatizado unitario em PHP `tests/test_oracle_signature.php` e validar geracao e integridade da assinatura

## 2. Documentacao de Maquina e Console Web

- [x] 2.1 Atualizar `public_html/openapi.json` com schema `OracleProof` e campo `_oracle_proof` nas respostas de sucesso
- [x] 2.2 Atualizar `public_html/llms.txt` documentando a atestacao criptografica do oraculo
- [x] 2.3 Validar ausencia de ampersands em todas as documentacoes

## 3. SDK Node.js e TypeScript

- [x] 3.1 Atualizar `sdk/node/grid402.d.ts` com interfaces `OracleProof` e `OracleVerificationResult`
- [x] 3.2 Implementar metodo `verifyProof()` e funcao estatica `verifyOracleProof()` em `grid402.mjs` e `grid402.cjs`
- [x] 3.3 Adicionar testes de verificacao positiva e rejeicao de adulteracao em `sdk/node/test_sdk.mjs` e validar execucao
- [x] 3.4 Atualizar versao para 2.5.0 em `sdk/node/package.json` e documentacao em `README.md`

## 4. SDK Python

- [x] 4.1 Adicionar modelo Pydantic `OracleProof` em `python/grid402/models.py`
- [x] 4.2 Implementar metodo `verify_proof()` em `python/grid402/client.py`
- [x] 4.3 Adicionar testes unitarios em `python/tests/test_sdk.py` e validar execucao
- [x] 4.4 Atualizar versao para 2.4.0 em `python/pyproject.toml` e documentacao em `README.md`

## 5. Servidor MCP

- [x] 5.1 Atualizar despachante `mcp/tools.mjs` e `mcp/index.mjs` assegurando repasse transparente de `_oracle_proof`
- [x] 5.2 Adicionar teste de presenca da prova de oraculo em `mcp/test_mcp.mjs` e validar execucao
- [x] 5.3 Atualizar versao para 1.5.0 em `mcp/package.json` e documentacao em `mcp/README.md`

## 6. Verificacao de Integracao E2E

- [x] 6.1 Executar teste E2E ponta a ponta `tests/test_e2e_full.php` cobrindo a emissao do `_oracle_proof`
- [x] 6.2 Validar todas as especificacoes com `openspec validate add-oracle-cryptographic-proof --strict`

