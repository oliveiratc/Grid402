# agent-discovery Delta Specification

## MODIFIED Requirements

### Requirement: Transparência Institucional, Governança e Security.txt
O sistema SHALL expor em `/trust` e `/status` a identificação do operador, matriz de fontes públicas e TTL, SLA (`99.5% target`), status público de frescor/integridade do índice de sanções e a política transiente com respeito a `robots.txt`/diretivas anti-AI do `/api/v1/extract`, mantendo `CHANGELOG.md` e `/.well-known/security.txt`.

#### Scenario: Consulta à página /trust para verificação de governança
- **GIVEN** um avaliador técnico acessando `https://grid402.online/trust`
- **WHEN** a página for renderizada
- **THEN** a página SHALL exibir a seção de Identificação do Operador e Governança, a matriz de proveniência e TTL por fonte (Receita Federal, BCB, IBGE, CGU, GLEIF, OFAC), o histórico de uptime de 30 dias, o resumo de Changelog de preços/versões e a nota de desambiguação de marca.

#### Scenario: Consulta à página /trust e /status exibe sanções e política transiente
- **GIVEN** um avaliador técnico acessando `https://grid402.online/trust` ou `https://grid402.online/status`
- **WHEN** a resposta for gerada
- **THEN** `/trust` SHALL exibir a política transiente sem retenção em disco e respeito a `robots.txt`/`X-Robots-Tag`/`Content-Signal` do `/api/v1/extract` e a matriz dos 4 blocos de sanções
- **AND** `/status` SHALL incluir o bloco público `sanctions_index` indicando disponibilidade e status de frescor sem expor caminhos internos do servidor

## ADDED Requirements

### Requirement: Manifesto Dinâmico /.well-known/x402.json e /.well-known/x402
O servidor SHALL servir `/.well-known/x402.json` e `/.well-known/x402` dinamicamente via PHP (`public_html/well_known_x402.php` roteado por `public_html/.htaccess`) com `Access-Control-Allow-Origin: *`, refletindo em tempo real `GRID402_SOLANA_X402_ENABLED` e os facilitadores ativos por rede.

#### Scenario: Manifesto /.well-known/x402.json oculta Solana quando flag está desligada
- **GIVEN** `GRID402_SOLANA_X402_ENABLED=false`
- **WHEN** um agente consulta `GET /.well-known/x402.json` ou `GET /.well-known/x402`
- **THEN** o JSON retornado SHALL conter HTTP `200`, `Access-Control-Allow-Origin: *`, redes EVM ativas (`eip155:8453`, `eip155:137`, `eip155:42161`) e SHALL NOT anunciar `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`

#### Scenario: Manifesto /.well-known/x402.json anuncia Solana automaticamente quando flag está ligada
- **GIVEN** `GRID402_SOLANA_X402_ENABLED=true`
- **WHEN** um agente consulta `GET /.well-known/x402.json`
- **THEN** o JSON retornado SHALL incluir `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` em `networks` e nas ofertas `accepts[]` dos recursos pagos com a carteira Solana oficial, mint SPL USDC e `feePayer`

### Requirement: Pré-Checagem CLI On-Chain de Prontidão Solana
O repositório MUST disponibilizar o script CLI `scripts/check_solana_readiness.php` para validar, antes de habilitar `GRID402_SOLANA_X402_ENABLED=true`, se a carteira Solana configurada possui saldo nativo SOL (`> 0` lamports) e conta de token associada (ATA) para o mint SPL USDC oficial usando RPCs saudáveis da Solana Mainnet.

#### Scenario: Carteira pronta com SOL e ATA USDC retorna código 0
- **GIVEN** uma resposta JSON-RPC (real ou simulada) retornando saldo SOL maior que zero e ao menos uma conta SPL Token do mint `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`
- **WHEN** o operador executa `php scripts/check_solana_readiness.php --json`
- **THEN** o script SHALL sair com código `0` e JSON contendo `"ready": true`, `"has_sol": true` e `"has_usdc_ata": true`

#### Scenario: Carteira sem SOL ou sem ATA USDC retorna código 1 com diagnóstico
- **GIVEN** uma carteira Solana com `0` lamports de SOL ou sem ATA SPL USDC criada
- **WHEN** o operador executa `php scripts/check_solana_readiness.php --json`
- **THEN** o script SHALL sair com código `1` e JSON contendo `"ready": false` detalhando exatamente o pré-requisito pendente
