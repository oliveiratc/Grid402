# mcp-server Delta Specification

## MODIFIED Requirements

### Requirement: Inicialização do Protocolo MCP via Stdio
O servidor MCP SHALL responder a requisições JSON-RPC de inicialização e listar as ferramentas disponíveis via transporte `stdio`.

#### Scenario: Listagem de ferramentas disponíveis
- **GIVEN** o servidor MCP em execução via `node index.mjs`
- **WHEN** o cliente MCP envia uma requisição `tools/list`
- **THEN** o servidor SHALL retornar 8 ferramentas registradas (`grid402_summary`, `grid402_forex`, `grid402_crypto`, `grid402_dns`, `grid402_ssl`, `grid402_asn`, `grid402_cep`, `grid402_cnpj`) com seus respectivos schemas.

## ADDED Requirements

### Requirement: Execução da Ferramenta de CNPJ no MCP
O servidor MCP SHALL permitir a consulta de dados cadastrais de Pessoa Jurídica via ferramenta `grid402_cnpj`.

#### Scenario: Consulta de CNPJ com liquidação automática de 0.0020 USDC
- **GIVEN** a variável `GRID402_PRIVATE_KEY` configurada com saldo suficiente de USDC na Base
- **WHEN** o cliente invoca `grid402_cnpj` com parâmetro `cnpj: "00000000000191"`
- **THEN** o servidor SHALL transferir 0.0020 USDC on-chain na Base para a carteira do oráculo e retornar os dados cadastrais da empresa com status HTTP 200.

#### Scenario: Consulta de CNPJ sem chave privada retorna desafio x402 de 0.0020 USDC
- **GIVEN** nenhuma chave privada configurada
- **WHEN** o cliente invoca `grid402_cnpj` com parâmetro `cnpj: "00000000000191"`
- **THEN** o servidor SHALL retornar o desafio 402 com instrução de pagamento de 0.0020 USDC e carteira de destino.
