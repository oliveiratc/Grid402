## ADDED Requirements

### Requirement: Plugin Oficial para Framework ElizaOS
O repositório SHALL prover uma integração oficial em TypeScript no padrão ElizaOS (`@elizaos/core`), permitindo que agentes autônomos executem ações de consulta aos serviços de Mercados e Compliance do Grid402.

#### Scenario: Agente consulta câmbio via Plugin ElizaOS
- GIVEN um agente executando com o plugin `@grid402/plugin-elizaos` carregado
- WHEN o usuário solicitar uma cotação de câmbio (ex: USD para BRL)
- THEN o plugin SHALL disparar a ação `GET_FOREX_RATE`, injetar o Session Pass configurado ou liquidar via x402 e responder com os dados normalizados de câmbio.

#### Scenario: Agente verifica sanções OFAC via Plugin ElizaOS
- GIVEN um agente executando com o plugin do Grid402
- WHEN o usuário pedir para checar um nome ou entidade em listas de sanções internacionais
- THEN o plugin SHALL executar a ação `CHECK_SANCTIONS_OFAC` e retornar o resultado detalhado com pontuação de risco.

### Requirement: Dossiê Executivo para Base Grants e CDP
O repositório SHALL conter documentação detalhada em `docs/BASE_GRANTS_AND_CDP_DOSSIER.md` formatada para submissão imediata aos programas de incentivo e subsídio de infraestrutura da Base Foundation e Coinbase Developer Platform.

#### Scenario: Consulta ao dossiê de submissão
- GIVEN o operador preparando a submissão de grants
- WHEN acessar `docs/BASE_GRANTS_AND_CDP_DOSSIER.md`
- THEN o documento SHALL conter o resumo executivo, métricas de rede, arquitetura do x402, casos de uso para agentes de IA e as respostas para os formulários oficiais da Base e Coinbase CDP.

