## ADDED Requirements

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

