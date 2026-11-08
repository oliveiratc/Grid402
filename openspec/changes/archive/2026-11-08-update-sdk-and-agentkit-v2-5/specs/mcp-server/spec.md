## ADDED Requirements

### Requirement: Ferramenta MCP de Verificacao de Oraculo
O servidor MCP (`grid402-mcp`) SHALL registrar e expor a ferramenta `grid402_verify` para validacao sem estado de assinaturas criptograficas secp256k1 geradas pelo oraculo do Grid402.

#### Scenario: Execucao da ferramenta grid402_verify com sucesso
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_verify` passando um payload JSON contendo a chave `_oracle_proof`
- THEN o servidor SHALL executar a verificacao e retornar `valid: true` acompanhado do hash dos dados e do endereco do oraculo.

#### Scenario: Execucao da ferramenta grid402_verify com payload invalido
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_verify` passando um payload sem a chave `_oracle_proof` ou com dados adulterados
- THEN o servidor SHALL retornar o resultado da verificacao com `valid: false` e detalhes da rejeicao.

### Requirement: Atualizacao do Pacote MCP para Versao 1.6.0
O pacote `grid402-mcp` SHALL ser atualizado para a versao `1.6.0` empacotando as 12 ferramentas do hub de dados e utilitarios de rede.

#### Scenario: Verificacao da lista de ferramentas na versao 1.6.0
- GIVEN o servidor MCP em execucao via `node index.mjs`
- WHEN o cliente envia uma requisicao `tools/list`
- THEN o servidor SHALL retornar 12 ferramentas registradas, incluindo `grid402_indicators`, `grid402_holidays`, `grid402_workdays` e `grid402_verify`.

