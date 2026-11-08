# Proposta: Assinatura Criptografica de Oraculo e Prova de Autenticidade (Proof of Data)

## Por que

Conforme detalhado no PRD em `openspec/prds/grid402-oracle-cryptographic-proof.md`, agentes de IA autonomos e desenvolvedores Web3 necessitam de garantia matematica de autenticidade e nao-repudio para dados consumidos sob o protocolo HTTP 402 na rede Base.

A inclusao de uma assinatura criptografica oficial (`_oracle_proof`) transforma o Grid402 de um mero gateway de dados em um **Oraculo Publico Verificavel**, elevando a confianca institucional e impulsionando convesoes pagas mesmo durante a operacao sob o subdominio atual.

## O que muda

- **Backend PHP:** Criacao do modulo `services/oracle_signer.php` que calcula o hash criptografico normalizado e assina a resposta com a chave privada da carteira oraculo via ECDSA secp256k1.
- **Estrutura de Resposta:** Todas as respostas tecnicas pagas (HTTP 200) e em modo Sandbox (`X-Payment: demo`) recebem o bloco `_oracle_proof` com endereco do oraculo, timestamp, data_hash, assinatura e algoritmo.
- **Gestao Segura de Chave:** Configuracao da chave privada via `GRID402_ORACLE_PRIVATE_KEY` no arquivo `.env` fora da raiz publica, com fallback deterministico documentado para desenvolvimento local.
- **SDK Node.js e TypeScript:** Metodo `verifyProof()` em `Grid402Client` e funcao estatica exportada para validacao local instantanea (< 5 ms) sem chamadas de rede.
- **SDK Python:** Metodo `verify_proof()` em `Grid402Client` e funcao utilitaria exportada para validacao da assinatura criptografica.
- **Servidor MCP:** Preservacao e repasse transparente do bloco `_oracle_proof` para agentes conectados no Cursor e Claude Desktop.
- **Documentacao:** Atualizacao de `openapi.json`, `llms.txt` e console interativo.

## Impacto

- **Specs afetadas:**
  - `x402-payment-oracle`
  - `node-sdk`
  - `python-sdk`
  - `mcp-server`
- **Codigo afetado:**
  - `public_html/services/oracle_signer.php` (novo)
  - `public_html/index.php` (injecao da prova no despachante de saida)
  - `public_html/config.php` (constantes de configuracao do oraculo)
  - `public_html/openapi.json` e `public_html/llms.txt`
  - `sdk/node/grid402.mjs`, `grid402.cjs`, `grid402.d.ts`, `package.json` (v2.5.0)
  - `python/grid402/client.py`, `models.py`, `pyproject.toml` (v2.4.0)
  - `mcp/tools.mjs`, `package.json` (v1.5.0)
  - `tests/test_oracle_signature.php` e suites de teste dos SDKs

