# Onda 3A: SEC EDGAR (`sec-company`), Auditoria de Vulnerabilidades (`vuln-audit`) e Telemetria Multi-Chain (`chain-telemetry`)

## Por quê

Com as Fases 1 e 2 consolidadas em produção (`28/28` serviços UP, Dual-Facilitator Coinbase CDP + PayAI em Base, Polygon, Arbitrum e Solana USDC, `POST /api/v1/batch` e `grid402-mcp@2.1.0`), o Grid402 precisa adicionar o próximo bloco de utilitários M2M de altíssima demanda orgânica mantendo a política **100% Zero-API-Key obrigatória** e armazenamento **Zero-DB** conforme definido em [fase3a-sec-company-vuln-audit-e-chain-telemetry.md](../../prds/fase3a-sec-company-vuln-audit-e-chain-telemetry.md).

## O que muda

- **Novo serviço `GET /api/v1/sec-company` (`0.0100 USDC` / 2 créditos):** consulta corporativa na SEC EDGAR oficial por `ticker` ou `cik` com lista de filings recentes filtrável por `forms`/`limit`, `include_facts=true` opcional protegido por limite rígido de `8 MB`, cache Zero-DB de `3600s` (e mapa ticker-CIK de `24h`), `legal_disclaimer`, `_meta` e `_oracle_proof`.
- **Novo serviço `GET /api/v1/vuln-audit` (`0.0050 USDC` / 1 crédito):** auditoria determinística via **OSV.dev** cruzada com o catálogo oficial **CISA KEV** (cache `6h` com fallback para última cópia íntegra em disco), suportando consulta por pacote (`ecosystem` + `package` + `version`) ou por ID (`CVE-*`/`GHSA-*`), com veredito determinístico (`CLEAN` a `CRITICAL_KEV`) e cache de `900s`.
- **Novo serviço `GET /api/v1/chain-telemetry` (`0.0050 USDC` / 1 crédito):** telemetria multi-chain em tempo real para `base`, `ethereum`, `polygon`, `arbitrum` e `solana` combinando RPCs públicos (bloco/slot, gás/taxas, latência) e **DeFiLlama** (TVL/stablecoins) com degradação graciosa e cache de `60s`.
- **Paridade completa no hub (`31/31 UP`):** integração dos 3 serviços no `POST /api/v1/batch`, catálogo, coletor, `/admin`, `/trust`, UI/i18n (`?v=3.8.0`), servidor MCP `v2.2.0` (`33 tools`), `/.well-known/x402.json` (`30` recursos pagos validados pelo CDP Bazaar v2), OpenAPI/Pricing/LLMs, `scripts/generate_rapidapi_openapi.mjs` e `scripts/bazaar_seed.mjs`.
- **Fora de escopo:** `/api/v1/sec-filing`, `/api/v1/global-kyb`, atualização dos SDKs Node/Python/AgentKit, `SKILL.md` público, Stripe MPP, EIP-712 on-chain e ERC-8004 (reservados para ondas futuras).

## Impacto

- **Specs afetadas:** `open-data-utilities`, `mcp-server`, `agent-discovery`
- **Código afetado:**
  - `public_html/services/{sec_company.php,vuln_audit.php,chain_telemetry.php,batch.php}`
  - `public_html/{config.php,collector.php,index.php,catalog.php,trust.php,agents.php,admin.php,mcp.php,well_known_x402.php}`
  - `public_html/includes/admin_cockpit_view.php`, `public_html/assets/js/i18n.js`
  - `public_html/{pricing.json,openapi.json,llms.txt,llms-full.txt}` e `public_html/.well-known/{x402.json,mcp.json,mcp/server-card.json,ai-agent.json}`
  - `mcp/{package.json,index.mjs,tools.mjs,test_mcp.mjs,README.md}` e `server.json`
  - `scripts/{generate_rapidapi_openapi.mjs,bazaar_seed.mjs}` e `docs/revenue/openapi/{rapidapi-global,rapidapi-all}.json`
  - `tests/test_phase3a_sec_vuln_telemetry.php` e atualização de contagens nos testes afetados, `README.md`, `CHANGELOG.md`
