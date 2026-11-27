# Spec Delta: agent-discovery

## MODIFIED Requirements

### Requirement: Arquivos de Descoberta para LLMs
O servidor SHALL disponibilizar `public_html/llms.txt` integralmente em inglês e `public_html/llms-full.txt` na raiz pública, formatados segundo llmstxt.org, documentando os 25 serviços, preços em USDC (`0.0050` a `0.0500`), Session Pass (`micro`, `starter`, `pro`, `fleet`), cupom `MCPTRY50`, `/mcp` com auto-trial, RapidAPI USD e `/dossie-kyb` sem Solana ativa e sem `&`.

#### Scenario: Requisicao com sucesso de llms.txt
- **GIVEN** o servidor web em produção com `GRID402_SOLANA_X402_ENABLED=false`
- **WHEN** um agente de IA efetua `GET /llms.txt`
- **THEN** o servidor SHALL retornar HTTP `200`, `Content-Type: text/plain` e cabeçalho `Access-Control-Allow-Origin: *`
- **AND** o documento SHALL estar redigido em inglês, conter `MCPTRY50`, `/mcp` (27 tools + auto-trial diário), `Micro` (R$ 4.90 / 50 credits) e tabelas `Starter/Pro/Fleet`, RapidAPI USD, `/dossie-kyb` e URLs sob `https://grid402.online`
- **AND** o conteúdo SHALL NOT conter o caractere `&` nem anunciar Solana como rede ativa.

#### Scenario: Requisicao com sucesso de llms-full.txt
- **GIVEN** o servidor web em produção
- **WHEN** um agente de IA efetua `GET /llms-full.txt`
- **THEN** o servidor SHALL retornar status HTTP `200`, `Content-Type: text/plain` com documentação técnica e endpoints sob `https://grid402.online` sem o caractere `&`.
