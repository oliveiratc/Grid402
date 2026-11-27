# Cast 1 — /base (Dual-Facilitator Coinbase CDP + PayAI + 4-Block Sanctions + Transient Extract)
> Major upgrade live on **Grid402** ⚡ Verifiable public-data & network utilities oracle for AI agents, powered by HTTP x402 v2.
>
> • **Dual-Facilitator on Base:** **Coinbase CDP** primary (CDP Bazaar v2 compliant) + safe pre-settle **PayAI** fallback (anti-double-charge state machine on `/settle`)
> • **Gasless Multi-Chain:** Base, Polygon, Arbitrum & **Solana (SPL USDC)** via dynamic `/.well-known/x402.json` + direct 6-chain transfers
> • **4-Block Official Sanctions (`/api/v1/ofac`):** US OFAC SDN, UN, EU FSFi & UK OFSI across EVM/Solana/BTC/Tron — fail-closed (`503` pre-charge) + `dataset_sha256` + ECDSA `_oracle_proof`
> • **100% Transient `/api/v1/extract`:** 0 bytes on disk, honors `robots.txt`, `noai`, `Content-Signal` & paywall blocks
> • **Lower KYB prices & boosted Passes:** `/cnpj` & `/lei` cut 50% (`0.0100 USDC`), Pro Pass **1,200 cr** (5 USDC · 180d), Fleet **6,000 cr** (20 USDC · 365d), auto-refund on errors
>
> 25 live APIs · 27 MCP tools (`v2.0.0`, mcprush Grade A). Try free (3 req/day or `MCPTRY50`):
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"`
> https://grid402.online

# Cast 2 — /ai-agents (MCP + Fail-Closed Compliance + Transient RAG)
> Production AI agents need three guarantees: reliable x402 settlement, real sanctions coverage, and zero-disk RAG extraction.
>
> **Grid402 v2.0.0** (25 APIs, 27 MCP tools — Grade A on mcprush, verified on Glama & x402scan) just shipped:
> • Dual-Facilitator Coinbase CDP + PayAI (Base, Polygon, Arbitrum, Solana SPL)
> • 4-block official sanctions (OFAC SDN, UN, EU FSFi, UK OFSI) for EVM/Solana/BTC/Tron — fail-closed + ECDSA `_oracle_proof`
> • 100% transient `/extract` honoring `robots.txt`, `X-Robots-Tag: noai`, `Content-Signal` & paywalls
> • Auto-refund on any `HTTP != 200` + free 300s `Idempotency-Key` retries
>
> `npx -y grid402-mcp` · Remote MCP: `https://grid402.online/mcp`
> `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/dossier?cnpj=00000000000191"`

# Cast 3 — /solana (Native SPL USDC Gasless x402 + Multi-Asset AML)
> Solana AI agents can now pay gaslessly in **SPL USDC** on **Grid402** ⚡
>
> 25 verifiable data & network APIs: 4-block OFAC/UN/EU/UK sanctions (screens Solana, EVM, BTC & Tron wallets), KYB Dossier 360 + AI Verdict, GLEIF LEI, 100% transient `/extract`, ECB FX, DNS/SSL/ASN & ECDSA notarization.
>
> Dynamic `/.well-known/x402.json` discovery OR `<15ms` Session Passes (Pro: 1,200 credits / 5 USDC / 180d) at https://grid402.online/pass.
>
> Free sandbox (3 req/day): `curl -sL -H "X-Payment: demo" "https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16"`

# Cast 4 — PT-BR (/brasil /dev)
> Atualização grande no **Grid402** 🇧🇷⚡
>
> • **Liquidez x402 v2:** Dual-Facilitator **Coinbase CDP + PayAI** na Base + gasless em Base, Polygon, Arbitrum e **Solana (SPL USDC)**, além de Pix/Cartão
> • **Sanções em 4 Blocos (`/api/v1/ofac`):** OFAC SDN, ONU, UE FSFi e UK OFSI para EVM, Solana, BTC, Tron e empresas — modo *fail-closed* + ECDSA `_oracle_proof`
> • **`/extract` 100% transiente:** 0 bytes gravados em disco, respeita `robots.txt`, `noai` e bloqueia paywalls pré-cobrança
> • **Preços e Passes:** `/cnpj` e `/lei` caíram 50% (`0,0100 USDC` / 2 cr); Pro Pass subiu para **1.200 créditos** (R$ 29 / 180d); Fleet **6.000 cr** (R$ 115 / 365d) + estorno automático em falhas
>
> Teste grátis (3 req/dia ou cupom `MCPTRY50`): `curl -sL -H "X-Payment: demo" https://grid402.online/api/v1/ofac?target=0x8589427373d6d84e98730d7795d8f6f8731fda16`
> https://grid402.online
