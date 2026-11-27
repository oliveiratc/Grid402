# Proposta: Fase 2 — Token Safety, Prediction Markets e Batch M2M (`add-phase2-token-safety-predictions-batch`)

## Por quê
Conforme especificado em [openspec/prds/fase2-token-safety-prediction-markets-e-batch.md](../../../prds/fase2-token-safety-prediction-markets-e-batch.md), com a Fase 1 concluída em produção (Dual-Facilitator Coinbase CDP + PayAI, Solana x402 ativa, 24 endpoints pagos validados no CDP Bazaar v2 e índice oficial de sanções em 4 blocos), o Grid402 precisa adicionar três capacidades M2M de alta tração usando apenas fontes 100% públicas (zero API key paga) e armazenamento Zero-DB:
1. **`GET /api/v1/token-safety`** (`0.0150 USDC` / 3 créditos): auditoria determinística de segurança de tokens e liquidez DEX em EVM (Base, Ethereum, Polygon, Arbitrum) e Solana.
2. **`GET /api/v1/prediction-markets`** (`0.0100 USDC` / 2 créditos): oráculo normalizado de probabilidades e liquidez em mercados de previsão (Polymarket + Kalshi).
3. **`POST /api/v1/batch`** (soma exata dos itens, 1 a 5 itens): execução multi-serviço em 1 única cobrança x402 ou Session Pass com estorno automático para sub-itens com falha.

## O que muda
- Novos micro-serviços Zero-DB em `public_html/services/token_safety.php`, `public_html/services/prediction_markets.php` e `public_html/services/batch.php`.
- Roteamento, cálculo dinâmico de preço para `POST /api/v1/batch` (incluindo desafio HTTP 402 com soma exata, estorno parcial em Session Pass, Spend Controls e `Idempotency-Key`) em `public_html/index.php` e `public_html/config.php`.
- Paridade total de catálogo e UI: **25 → 28 serviços** (`28/28 UP` em `collector.php`, `/status`, `/catalog`, `/trust`, `/admin`, `i18n.js` PT-BR/EN e Playground).
- Paridade total do servidor MCP (`v2.1.0`): **27 → 30 tools** (`grid402_token_safety`, `grid402_prediction_markets` e `grid402_batch`) em `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs`, `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json`.
- Atualização de descoberta e validação CDP Bazaar: `public_html/well_known_x402.php`, `public_html/.well-known/x402.json`, `public_html/pricing.json`, `public_html/openapi.json`, `public_html/.well-known/ai-agent.json`, `public_html/llms.txt`, `public_html/llms-full.txt`, `README.md` e `scripts/validate_cdp_bazaar.php`.
- Nova suíte automatizada `tests/test_phase2_token_safety_predictions_batch.php` + atualização das contagens em testes existentes.

## Fora de escopo
- Endpoints de ciclos futuros: `sec-company`, `sec-filing`, `vuln-audit`, `chain-telemetry`, `/api/v1/global-kyb`.
- Stripe MPP, assinatura EIP-712 verificável on-chain e registro ERC-8004.
- APIs pagas de terceiros, SQLite, MySQL, PostgreSQL ou alteração de preços dos 25 serviços existentes.

## Impacto
- **Specs afetadas:** `open-data-utilities`, `mcp-server`, `agent-discovery`.
- **Código afetado:** `public_html/config.php`, `public_html/index.php`, `public_html/collector.php`, `public_html/catalog.php`, `public_html/trust.php`, `public_html/admin.php`, `public_html/includes/admin_cockpit_view.php`, `public_html/well_known_x402.php`, `public_html/mcp.php`, `public_html/assets/js/i18n.js`, `public_html/services/{token_safety,prediction_markets,batch}.php`, `public_html/{openapi,pricing}.json`, `public_html/.well-known/{x402,mcp,ai-agent}.json`, `public_html/.well-known/mcp/server-card.json`, `public_html/llms*.txt`, `README.md`, `mcp/{tools,index,test_mcp}.mjs`, `scripts/validate_cdp_bazaar.php` e `tests/`.
