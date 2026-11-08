# Delta Spec: mcp-server

## ADDED Requirements

### Requirement: Publicação e Resolução Global do Pacote npm v1.2.0
O pacote `grid402-mcp` SHALL ser distribuído no registro oficial do npm na versão `1.2.0`, empacotando os recursos de CNPJ e Modo Sandbox para download público.

#### Scenario: Consulta de metadados da versão no registro npm
- **GIVEN** o pacote `grid402-mcp` publicado no npm
- **WHEN** um cliente consulta o registro via `npm view grid402-mcp version`
- **THEN** o registro SHALL retornar exatamente a versão `1.2.0`.

#### Scenario: Inicialização remota com suporte a Modo Sandbox
- **GIVEN** o pacote instalado via `npx -y grid402-mcp@latest` com a variável `GRID402_SANDBOX=true`
- **WHEN** o cliente invoca a ferramenta `grid402_cnpj` com parâmetro `cnpj: "00000000000191"`
- **THEN** o servidor SHALL executar a chamada utilizando o token de simulação e retornar os dados completos sem exigir saldo em carteira.

