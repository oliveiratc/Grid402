# Proposal

## Why

A auditoria de mercado da economia agêntica x402 (2025–2026) combinada com a inspeção técnica do código de produção do Grid402 (`openspec/prds/fase1-liquidez-global-e-blindagem-juridica.md`) revelou quatro travas prioritárias que bloqueiam liquidez global e geram exposição jurídica e reputacional:

1. **Facilitador único impedindo descoberta no Coinbase CDP Bazaar:** `public_html/facilitator.php` já gera JWT ES256/Ed25519 para CDP, mas o roteamento atual suporta apenas um facilitador global (`PayAI`), impossibilitando liquidar via Coinbase CDP na Base para indexação automática no CDP Bazaar.
2. **Solana x402 desligada e manifesto estático dessincronizado:** `public_html/.well-known/x402.json` é estático (sem refletir `GRID402_SOLANA_X402_ENABLED`) e falta ferramenta CLI de pré-checagem on-chain (SOL + ATA SPL USDC) antes de ativar a rede responsável por ~70% do volume recente do protocolo x402.
3. **Lista hardcoded de 6 endereços/7 entidades no `/api/v1/ofac`:** `public_html/services/ofac.php` anuncia varredura de OFAC SDN e ONU, mas consulta apenas um array estático mínimo, criando risco grave de falso negativo (`CLEAR` indevido) e informação enganosa.
4. **Risco de copyright e falta de diretivas em `/api/v1/extract`:** `public_html/services/extract.php` persiste conteúdo textual de terceiros em disco por 600s e não respeita `robots.txt` (RFC 9309), `X-Robots-Tag`, `Content-Signal`, meta tags anti-AI ou bloqueios de paywall/autenticação.

Resolver essas quatro frentes na Fase 1 desbloqueia descoberta global e liquidez multi-chain com blindagem jurídica completa, mantendo 100% da arquitetura Zero-DB (sem exigir SQLite).

## What Changes

- **Roteamento Dual-Facilitator e conformidade Coinbase CDP Bazaar (RF01, RF02, RF10):** implementar roteamento por rede em `public_html/facilitator.php` (Coinbase CDP primário na Base Mainnet quando configurado com fallback automático para PayAI apenas na etapa pré-`/settle`; PayAI para Polygon, Arbitrum e Solana; bloqueio estrito de fallback em erro ambíguo de `/settle`), garantir conformidade estrita de `extensions.bazaar` v2 em `public_html/x402.php` e criar o validador CLI `scripts/validate_cdp_bazaar.php` (offline + `--live`).
- **Manifesto dinâmico `/.well-known/x402.json` e pré-checagem Solana (RF03, RF04):** rotear `/.well-known/x402.json` e `/.well-known/x402` via `public_html/well_known_x402.php` refletindo `GRID402_SOLANA_X402_ENABLED` e facilitadores ativos, e criar `scripts/check_solana_readiness.php` para validar saldo SOL e ATA SPL USDC on-chain.
- **Sincronização streaming dos 4 blocos oficiais de sanções e triagem multi-ativo (RF05, RF06, RF07):** criar `scripts/sync_sanctions_index.php` e `public_html/services/sanctions_index.php` usando `XMLReader` em streaming e índice Zero-DB JSON particionado em `GRID402_PRIVATE_DIR/sanctions_index/` (OFAC SDN `sdn_advanced.xml`, ONU Consolidated, EU FSFi e UK OFSI); evoluir `public_html/services/ofac.php` para triagem multi-ativo (EVM, Solana, BTC e outros + nomes/aliases) com comportamento *fail-closed* HTTP `503` pré-cobrança quando sem índice, aviso `STALE` acima de 72h, `dataset_metadata` (`dataset_sha256`) + `legal_disclaimer`, e `demo_subset` explícito no sandbox; atualizar `public_html/services/kyb_audit.php` para aceitar multi-ativo e propagar HTTP `503` sem falso `CLEAR`.
- **Blindagem jurídica e operação 100% transiente no `/api/v1/extract` (RF08, RF09):** checar `robots.txt` (RFC 9309 sob o mesmo guarda anti-SSRF, cache leve só das diretivas por host), bloquear `X-Robots-Tag` (`noai`, `noimageai`, `noindex`, `none`), `Content-Signal` (`ai-input=no`, `ai-train=no`), meta tags anti-AI e HTTP `401`/`402`/`403`/`407` ou sinais estruturais de paywall, e remover integralmente a gravação em disco de conteúdo extraído de terceiros.
- **Telemetria, configuração e testes (RF11, RF12):** atualizar `.env.example`, `/status` (`collector.php`) e `/trust` com status do índice de sanções e política transiente do `/extract`, preservando `25/25 UP`, 27 ferramentas MCP `v2.0.0`, carteira canônica e cobertura integral em `tests/test_phase1_liquidity_and_legal_hardening.php`.

## Capabilities

### New Capabilities
<!-- Nenhuma capability totalmente nova; todas as evoluções refinam e expandem capabilities existentes no repositório. -->

### Modified Capabilities
- `x402-exact-settlement`: adicionar roteamento Dual-Facilitator (Coinbase CDP + PayAI com fallback seguro pré-`/settle` e regra anti-dupla-cobrança em `/settle`), conformidade estrita de `extensions.bazaar` v2 e script CLI `validate_cdp_bazaar.php`.
- `agent-discovery`: tornar `/.well-known/x402.json` e `/.well-known/x402` dinâmicos por flag e facilitadores ativos, adicionar `scripts/check_solana_readiness.php` e expor frescor de sanções e política transiente no `/trust` e `/status`.
- `open-data-utilities`: substituir a lista estática do `/api/v1/ofac` pelo índice particionado Zero-DB dos 4 blocos oficiais com triagem multi-ativo, *fail-closed* pré-cobrança e propagação segura em `/api/v1/kyb-audit`; blindar `/api/v1/extract` com `robots.txt`, diretivas anti-AI, bloqueio de paywall e operação 100% transiente sem cache de conteúdo em disco.

## Impact

- **PRD de origem:** `openspec/prds/fase1-liquidez-global-e-blindagem-juridica.md` (`RF01` a `RF12`).
- **Escopo de código afetado:** `public_html/config.php`, `public_html/facilitator.php`, `public_html/x402.php`, `public_html/index.php`, `public_html/.htaccess`, `public_html/well_known_x402.php`, `public_html/services/sanctions_index.php`, `public_html/services/ofac.php`, `public_html/services/kyb_audit.php`, `public_html/services/extract.php`, `public_html/collector.php`, `public_html/trust.php`, `.env.example`, `scripts/sync_sanctions_index.php`, `scripts/check_solana_readiness.php`, `scripts/validate_cdp_bazaar.php` e `tests/test_phase1_liquidity_and_legal_hardening.php`.
- **Fora desta change:** novos endpoints da Fase 2 (`token-safety`, `sec-company`, `vuln-audit`, `prediction-markets`, `chain-telemetry`, `batch`), recursos da Fase 3 (`Stripe MPP`, `EIP-712`, `ERC-8004`), alterações de preços ou das 27 ferramentas MCP, ou dependência de SQLite/MySQL.
- **Invariantes preservadas:** `25/25 UP`, paridade das 27 ferramentas MCP (`v2.0.0`), Zero-DB com `flock(LOCK_EX)` e troca atômica, compatibilidade PHP 8.1+ sem `sqlite3` ou `mbstring`, Zero-PII, carteira canônica `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` e proibição do caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md` e `smithery.yaml`.
