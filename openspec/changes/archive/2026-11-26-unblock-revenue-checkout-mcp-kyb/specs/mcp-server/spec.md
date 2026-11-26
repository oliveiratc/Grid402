# Spec Delta: mcp-server

## ADDED Requirements

### Requirement: Negociação Dinâmica de `protocolVersion` no `/mcp` Hospedado
O servidor MCP hospedado (`public_html/mcp.php`) MUST negociar `protocolVersion` no método JSON-RPC `initialize`, retornando a versão solicitada quando suportada (`2024-11-05`, `2025-03-26`, `2025-06-18`) e fazendo fallback seguro caso contrário.

#### Scenario: Cliente MCP moderno negocia protocolVersion 2025-03-26 no /mcp
- **GIVEN** um cliente enviando `POST /mcp` com método `initialize` e `params.protocolVersion = "2025-03-26"`
- **WHEN** o servidor processa o handshake
- **THEN** retorna HTTP 200 com `result.protocolVersion === "2025-03-26"` e `serverInfo.version === "2.0.0"`

### Requirement: CORS Completo e Aliases de Credenciais no `/mcp` Hospedado
O servidor `public_html/mcp.php` MUST incluir `X-Payment-Signature`, `Payment`, `Mcp-Session-Id` e `MCP-Protocol-Version` em `Access-Control-Allow-Headers` e aceitar `X-Payment-Signature` e `Payment` como cabeçalhos válidos de pagamento em `tools/call`.

#### Scenario: Preflight OPTIONS e aliases de header x402 aceitos no /mcp
- **GIVEN** um cliente enviando `OPTIONS /mcp` ou `POST /mcp` (`tools/call`) com header `X-Payment-Signature` ou `Payment`
- **WHEN** o servidor processa a requisição
- **THEN** `OPTIONS /mcp` retorna HTTP 204 expondo `X-Payment-Signature`, `Payment`, `Mcp-Session-Id` e `MCP-Protocol-Version`, e `tools/call` reconhece a credencial enviada nesses aliases

### Requirement: Auto-Trial Diário (`mcp_sandbox`) e Conversão `MCPTRY50` em Inglês no `/mcp`
No servidor hospedado `public_html/mcp.php`, a primeira chamada diária por IP a uma ferramenta paga de dados (exceto `grid402_pass`) sem pagamento MUST receber 1 auto-trial gratuito (`payment_source = 'mcp_sandbox'`) sob `flock(LOCK_EX)`; chamadas subsequentes sem prova MUST retornar erro em inglês promovendo `MCPTRY50` (`/pass?promo=MCPTRY50`), `/pass` e RapidAPI USD.

#### Scenario: Primeira chamada paga sem credencial no /mcp recebe 1 auto-trial gratuito
- **GIVEN** um IP novo que ainda não usou o auto-trial diário do `/mcp`
- **WHEN** envia `POST /mcp` (`tools/call`) para `grid402_forex` sem cabeçalho de pagamento
- **THEN** o servidor retorna HTTP 200 com os dados assinados de `forex` marcados com `sandbox: true`, aviso de auto-trial apontando para `MCPTRY50` e registro de telemetria `mcp_sandbox`

#### Scenario: Segunda chamada sem credencial no mesmo dia promove MCPTRY50 em inglês
- **GIVEN** um IP que já consumiu sua 1 chamada diária de auto-trial no `/mcp`
- **WHEN** envia nova chamada `POST /mcp` (`tools/call`) para `grid402_forex` sem pagamento
- **THEN** o servidor retorna `isError: true` em inglês contendo `MCPTRY50`, `https://grid402.online/pass?promo=MCPTRY50`, link RapidAPI USD e `_meta["x402/error"]` sem `solana` quando a flag estiver desligada

#### Scenario: Auto-trial nunca libera emissão de Session Pass (`grid402_pass`)
- **GIVEN** um IP novo sem auto-trial consumido
- **WHEN** invoca `grid402_pass` sem pagamento real
- **THEN** o servidor SHALL NOT conceder auto-trial e retorna erro de pagamento obrigatório
