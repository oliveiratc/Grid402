# Design: Painel x402scan no Cockpit `/admin`

## 1. Visão Geral da Arquitetura

A integração consulta a API pública tRPC GET do x402scan sem credenciais externas:
- **Origem do servidor:** `GET https://www.x402scan.com/api/trpc/public.origins.get?input={"json":"00294038-51ff-4073-ab29-c59fca221a50"}`
- **Estatísticas agregadas por janela (`timeframe` = `1`, `7`, `30`):**
  `GET https://www.x402scan.com/api/trpc/public.stats.overall?input={"json":{"recipients":{"include":["0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90","CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C"]},"timeframe":30}}`

> **Nota técnica:** O servidor do x402scan possui views materializadas para `1d` (`timeframe: 1`), `7d` (`timeframe: 7`) e `30d` (`timeframe: 30`), enquanto `90d` e `365d` retornam erro interno (`relation "recipient_stats_aggregated_90d" does not exist`) e `timeframe: 0` tem lag atrás do `30d`. Por isso, o módulo usa exclusivamente `1`, `7` e `30`.

## 2. Novo Módulo `public_html/services/x402scan.php`

### Constantes e Configuração
- `GRID402_X402SCAN_ORIGIN_ID`: `'00294038-51ff-4073-ab29-c59fca221a50'` (override via env opcional)
- `GRID402_X402SCAN_SERVER_URL`: `'https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50'`
- `GRID402_X402SCAN_CACHE_TTL_SEC`: `300` (5 minutos)
- Carteiras monitoradas: `[strtolower(GRID402_WALLET_ADDRESS), GRID402_SOLANA_WALLET_ADDRESS]`

### Funções Principais
- `grid402_x402scan_http_get(string $url, int $timeoutSec = 4): array`
  - Verifica `$GLOBALS['grid402_x402scan_http_override']` para injeção determinística em testes sem rede.
  - Em produção usa `curl` com `CURLOPT_CONNECTTIMEOUT = 2`, `CURLOPT_TIMEOUT = 4`, `Accept: application/json`, `User-Agent: Grid402-Admin/1.0`.
- `grid402_get_x402scan_stats(bool $forceRefresh = false): array`
  - Lê `GRID402_PRIVATE_DIR . '/x402scan_cache.json'`.
  - Se não for `$forceRefresh` e existir cache íntegro com `time() - cached_at < 300`, retorna imediatamente com `'status' => 'cached'`, `'stale' => false`.
  - Caso contrário, busca `origins.get` + `stats.overall` (`1`, `7`, `30`).
  - Converte `total_amount` (unidades raw de USDC, 6 casas decimais) via `round(((float) $raw) / 1000000, 4)` para `volume_usdc`.
  - Se ao menos os dados de estatísticas ou origem forem obtidos com sucesso, grava atomicamente com `grid402_atomic_write()` e retorna `'status' => 'live'`, `'stale' => false`.
  - Se todas as chamadas falharem e houver cache prévio válido em disco, retorna o cache preservado marcando `'status' => 'stale_cache'`, `'stale' => true`.
  - Se todas falharem sem cache prévio, retorna estrutura vazia segura com `'status' => 'unavailable'`, `'stale' => false` (nunca lança exceção).

### Contrato de Dados (`x402scan`)
```json
{
  "status": "live | cached | stale_cache | unavailable",
  "stale": false,
  "cached_at": 1795830500,
  "cache_ttl_sec": 300,
  "server_url": "https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50",
  "wallets": {
    "evm": "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90",
    "solana": "CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C"
  },
  "origin": {
    "id": "00294038-51ff-4073-ab29-c59fca221a50",
    "title": "Grid402 - ...",
    "has_x402_v2": true,
    "updated_at": "2026-11-28T01:33:02.628Z"
  },
  "windows": {
    "d1":  { "transactions": 31, "amount_raw": 355000,  "volume_usdc": 0.3550, "unique_buyers": 1, "latest_tx_at": "2026-11-28T01:36:42.000Z" },
    "d7":  { "transactions": 41, "amount_raw": 480000,  "volume_usdc": 0.4800, "unique_buyers": 2, "latest_tx_at": "2026-11-28T01:36:42.000Z" },
    "d30": { "transactions": 68, "amount_raw": 4730000, "volume_usdc": 4.7300, "unique_buyers": 4, "latest_tx_at": "2026-11-27T13:43:53.000Z" }
  },
  "error": null
}
```

## 3. Integração em `admin.php` e `admin_cockpit_view.php`

1. **`public_html/admin.php`:**
   - Requer `services/x402scan.php`.
   - No endpoint `GET /admin?api=stats`: lê `$forceRefresh = (($_GET['refresh'] ?? '') === '1');`, inclui `'x402scan' => grid402_get_x402scan_stats($forceRefresh)` no JSON retornado.
   - Na renderização SSR da view autenticada (`require __DIR__ . '/includes/admin_cockpit_view.php'`): passa `$x402scanStats = grid402_get_x402scan_stats(false)`.
2. **`public_html/includes/admin_cockpit_view.php`:**
   - **Aba `Resumo Executivo`:** card compacto "x402scan (30d)" exibindo `68 txs · US$ 4,7300 · 4 buyers`, status `x402 v2` e link externo.
   - **Aba `Receita e Passes`:** painel completo "x402scan — Índice Público On-Chain" com tabela/cards comparativos `24h`, `7d` e `30d`, últimas liquidações indexadas, carteiras monitoradas EVM + Solana, badge de status do cache (`live`/`cached`/`stale_cache`/`unavailable`) e botão/link para abrir no x402scan.
   - **Polling JS (`fetchLiveStats` / `applyStatsPayload`):** `manualRefresh()` passa `&refresh=1` uma única vez ao clicar em **Atualizar**, enquanto o polling automático de `5s` chama apenas `?api=stats` (aproveitando o cache de `300s`).
