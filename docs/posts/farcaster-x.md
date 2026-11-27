# Cast 1 — /base (Phase 2: Token Safety, Prediction Markets & Batch + 28 APIs / 30 MCP Tools)
> Phase 2 is LIVE on **Grid402** (`https://grid402.online`) — now **28 live M2M APIs** & **30 MCP tools (`v2.1.0`)** ⚡
>
> • `GET /api/v1/token-safety` ($0.0150 USDC): Base, ETH, Polygon, Arbitrum & Solana audit (GoPlus + DexScreener) with honeypot/tax/liquidity checks, 0–100 score & deterministic verdict (`SAFE` / `CAUTION` / `HIGH_RISK` / `CRITICAL`)
> • `GET /api/v1/prediction-markets` ($0.0100 USDC): normalized Polymarket + Kalshi aggregator with 0–100% probabilities, 24h volume & liquidity
> • `POST /api/v1/batch`: run 1–5 endpoints in ONE x402 settlement or Session Pass call (exact sum pricing, auto-refund on failed sub-items, `Idempotency-Key`)
>
> Dual-Facilitator Coinbase CDP + PayAI across Base, Polygon, Arbitrum & Solana SPL USDC.
> Try free (3 req/day or `MCPTRY50`): `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"`

# Cast 2 — /ai-agents (MCP v2.1.0 — 30 Tools + Batch + Fail-Closed Compliance)
> Autonomous AI agents shouldn't sign 5 separate payments for 1 decision.
>
> **Grid402 MCP v2.1.0** (30 tools, 28 live APIs — Grade A on mcprush, Glama & x402scan verified) just shipped Phase 2:
> • `grid402_batch` (`POST /api/v1/batch`): 1–5 endpoints in 1 call & 1 x402 settlement, with auto-refund per failed sub-item
> • `grid402_token_safety`: GoPlus + DexScreener audit across Base, ETH, Polygon, Arbitrum & Solana
> • `grid402_prediction_markets`: unified Polymarket + Kalshi v2 probabilities
> • Plus 4-block OFAC/UN/EU/UK sanctions (fail-closed) & 100% transient `/extract`
>
> `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp`

# Cast 3 — /solana (Solana SPL USDC x402 + Token Safety Base58 + Batch)
> Solana AI & DeFi agents can now run **Token Safety**, **Prediction Markets**, and **Multi-Service Batches** natively in **SPL USDC** on **Grid402** ⚡
>
> 28 live APIs · 30 MCP tools (`v2.1.0`):
> • `/api/v1/token-safety` ($0.0150): auto-detects Base58 Solana tokens + EVM chains (GoPlus + DexScreener)
> • `/api/v1/prediction-markets` ($0.0100): Polymarket + Kalshi normalized
> • `POST /api/v1/batch`: 1–5 calls in 1 payment + 4-block OFAC/UN/EU/UK sanctions + 100% transient `/extract`
>
> Free sandbox: `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/prediction-markets?q=solana&limit=5"`

# Cast 4 — PT-BR (/brasil /dev)
> Fase 2 no ar no **Grid402** 🇧🇷⚡ Agora são **28 APIs UP** e **30 tools MCP (`v2.1.0`)**:
>
> • `GET /api/v1/token-safety` (`0,0150 USDC`): auditoria GoPlus + DexScreener em Base, ETH, Polygon, Arbitrum e Solana (`SAFE`/`CAUTION`/`HIGH_RISK`/`CRITICAL`)
> • `GET /api/v1/prediction-markets` (`0,0100 USDC`): Polymarket + Kalshi unificados (probabilidades `0–100%`)
> • `POST /api/v1/batch`: até 5 consultas em 1 chamada e 1 liquidação x402 (estorno automático por sub-item falho no Session Pass)
>
> Teste grátis (3 req/dia ou `MCPTRY50`): `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"`
> https://grid402.online/catalog
