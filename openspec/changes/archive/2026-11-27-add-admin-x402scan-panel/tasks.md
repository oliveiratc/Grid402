## 1. Serviço Zero-DB x402scan
- [x] 1.1 Criar `public_html/services/x402scan.php` com consulta tRPC pública (`public.origins.get` e `public.stats.overall` em `1d`, `7d`, `30d` para carteiras EVM + Solana), cache Zero-DB de `300s` em `x402scan_cache.json` via `grid402_atomic_write()`, fallback `stale_cache` / `unavailable`, timeouts `<= 4s` e `$GLOBALS['grid402_x402scan_http_override']`.

## 2. Backend `/admin` e Cockpit View
- [x] 2.1 Integrar `public_html/services/x402scan.php` em `public_html/admin.php`, expondo `x402scan` em `GET /admin?api=stats` (suportando `&refresh=1`) e na renderização SSR da view administrativa.
- [x] 2.2 Atualizar `public_html/includes/admin_cockpit_view.php` com o painel dedicado **"x402scan — Índice Público On-Chain"** (`24h / 7d / 30d`, carteiras EVM + Solana, status `x402 v2`, badge de freshness e link direto) na aba **Receita e Passes**, o card resumido na aba **Resumo Executivo** e atualização dinâmica via JS (`applyStatsPayload` + botão **Atualizar** com `refresh=1`).

## 3. Testes e Validação
- [x] 3.1 Criar `tests/test_admin_x402scan.php` cobrindo cache hit/miss `300s`, conversão raw→USDC, dual-wallet payload, fallback `stale_cache`, estado `unavailable`, API `/admin?api=stats` (`401` sem sessão, `200` com sessão, `&refresh=1`) e SSR HTML das abas.
- [x] 3.2 Executar `php tests/test_admin_x402scan.php`, `php tests/test_admin_dashboard.php`, `php tests/test_billing_country_origin.php` e `npx -y @fission-ai/openspec@latest validate --all --strict`.
