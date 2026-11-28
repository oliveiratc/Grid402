# Proposal: Painel x402scan no Cockpit `/admin`

## Why

Atualmente o operador do Grid402 acompanha o faturamento interno e os saldos das carteiras no `/admin`, mas precisa abrir externamente o site do **x402scan** (`https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50`) para conferir transações públicas indexadas, volume on-chain, compradores únicos e status de indexação `x402 v2` do servidor. Consolidar essas métricas no cockpit administrativo autenticado elimina essa alternância mantendo a arquitetura 100% Zero-API-Key e Zero-DB.

## What Changes

- Criar `public_html/services/x402scan.php` para consultar os endpoints públicos tRPC do x402scan (`public.origins.get` e `public.stats.overall` nas janelas `1d`, `7d` e `30d` para as carteiras EVM + Solana), com cache Zero-DB de `300s` em `private_data/x402scan_cache.json`, fallback `stale_cache` / `unavailable` e override HTTP testável.
- Integrar o bloco `x402scan` no payload autenticado de `GET /admin?api=stats` (com suporte a `&refresh=1` para atualização manual) em `public_html/admin.php`.
- Renderizar o painel multijanela (`24h / 7d / 30d`) **"x402scan — Índice Público On-Chain"** na aba **Receita e Passes** e o indicador resumido (`30d` transações/volume + link direto) na aba **Resumo Executivo** em `public_html/includes/admin_cockpit_view.php`, atualizados dinamicamente no polling do cockpit.
- Criar suíte de testes determinística e E2E HTTP em `tests/test_admin_x402scan.php` e validar regressão em `tests/test_admin_dashboard.php`.
