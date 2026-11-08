# mcp-server Specification

## Purpose
TBD - created by archiving change add-mcp-server-and-distribution. Update Purpose after archive.

## Requirements

### Requirement: Inicialização do Protocolo MCP via Stdio
O servidor MCP SHALL responder a requisições JSON-RPC de inicialização e listar as ferramentas disponíveis via transporte `stdio`.

#### Scenario: Listagem de ferramentas disponíveis
- **GIVEN** o servidor MCP em execução via `node index.mjs`
- **WHEN** o cliente MCP envia uma requisição `tools/list`
- **THEN** o servidor SHALL retornar 8 ferramentas registradas (`grid402_summary`, `grid402_forex`, `grid402_crypto`, `grid402_dns`, `grid402_ssl`, `grid402_asn`, `grid402_cep`, `grid402_cnpj`) com seus respectivos schemas.

### Requirement: Execução de Consulta Gratuita
O servidor MCP SHALL executar consultas gratuitas sem exigir chave de carteira.

#### Scenario: Execução da ferramenta summary
- GIVEN o servidor MCP conectado ao endpoint do Grid402
- WHEN o cliente invoca `grid402_summary`
- THEN o servidor SHALL retornar status 200 OK com o resumo consolidado de saúde dos 6 micro-serviços.

### Requirement: Liquidação Automática com Chave Privada
Quando uma chave privada for informada na variável de ambiente `GRID402_PRIVATE_KEY` e a rota exigir pagamento x402, o servidor SHALL transferir 0.0005 USDC na Base Mainnet, obter o hash da transação e refazer a consulta com o cabeçalho `X-Payment`.

#### Scenario: Consulta detalhada com chave configurada
- GIVEN a variável `GRID402_PRIVATE_KEY` configurada com uma carteira contendo saldo de USDC e ETH na Base
- WHEN o cliente invoca `grid402_dns` com `domain: "github.com"`
- THEN o servidor SHALL transferir 0.0005 USDC para a carteira recebedora do Grid402, aguardar a confirmação e retornar o payload completo de registros DNS com HTTP 200.

### Requirement: Fallback Estruturado sem Chave Privada
Quando nenhuma chave privada for informada e a consulta exigir pagamento, o servidor SHALL retornar um payload amigável informando o desafio `PaymentRequired`.

#### Scenario: Consulta detalhada sem chave configurada
- GIVEN a variável `GRID402_PRIVATE_KEY` não definida
- WHEN o cliente invoca `grid402_ssl` com `host: "base.org"` sem fornecer `payment_tx_hash`
- THEN o servidor SHALL retornar o desafio 402 com as instruções de liquidação e o endereço da carteira destino.

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

### Requirement: Registro e Configuração Smithery.ai
O repositório SHALL incluir o arquivo de manifesto `smithery.yaml` permitindo a descoberta e instalação do servidor MCP com suporte a configuração de chave privada e modo sandbox.

#### Scenario: Instalação automatizada via CLI Smithery
- **WHEN** um usuário executa `npx -y @smithery/cli install grid402-mcp`
- **THEN** o Smithery inicializa o servidor MCP via `npx -y grid402-mcp` passando os parâmetros de configuração especificados no schema

### Requirement: Provedor de Ações para Coinbase AgentKit
O repositório SHALL disponibilizar provedor de ações (`Grid402ActionProvider`) compatível com o ecossistema Coinbase AgentKit nas linguagens TypeScript e Python, permitindo auto-liquidação de consultas na Base.

#### Scenario: Execução de ação com carteira CDP na Base
- **GIVEN** um agente autônomo configurado com o CDP Wallet Provider e saldo em USDC na Base
- **WHEN** o agente invoca uma ação do Grid402 (CNPJ, CEP, Forex, Crypto, DNS, SSL ou ASN)
- **THEN** o Action Provider liquida a tarifa de 0.0005 ou 0.0020 USDC via CDP Wallet Provider na Base e entrega os dados enriquecidos com HTTP 200
