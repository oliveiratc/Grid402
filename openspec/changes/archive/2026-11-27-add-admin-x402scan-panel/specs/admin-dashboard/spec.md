## ADDED Requirements

### Requirement: Telemetria Pública x402scan no Painel Administrativo
O sistema MUST consultar e cachear localmente (`300s` em storage Zero-DB) os dados públicos do **x402scan** (`public.origins.get` e `public.stats.overall` nas janelas `1d`, `7d` e `30d` para as carteiras EVM e Solana do Grid402), expondo-os exclusivamente no cockpit autenticado `/admin` (API JSON `/admin?api=stats`, aba **Receita e Passes** e indicador resumido no **Resumo Executivo**).

#### Scenario: Consulta ao vivo e cache Zero-DB de 300 segundos no serviço x402scan
- **GIVEN** que não existe cache válido em `private_data/x402scan_cache.json`
- **WHEN** `grid402_get_x402scan_stats(false)` for invocado
- **THEN** o sistema deve consultar `public.origins.get` e `public.stats.overall` para `timeframe` `1`, `7` e `30` somando as carteiras EVM e Solana oficiais
- **AND** deve converter `total_amount` em unidades raw USDC (6 casas decimais) para `volume_usdc`
- **AND** deve persistir o resultado em `private_data/x402scan_cache.json` via `grid402_atomic_write()` retornando `status = "live"`
- **AND** chamadas subsequentes dentro de `300s` com `$forceRefresh = false` devem retornar `status = "cached"` sem executar novas requisições HTTP externas

#### Scenario: Fallback resiliente em falha upstream (stale_cache e unavailable)
- **GIVEN** que existe um `x402scan_cache.json` expirado em disco e a API do x402scan falha ou excede timeout
- **WHEN** `grid402_get_x402scan_stats(false)` for invocado
- **THEN** o sistema deve preservar e retornar os dados anteriores com `status = "stale_cache"` e `stale = true`
- **AND** caso não exista nenhum cache prévio em disco durante a falha, deve retornar `status = "unavailable"` com valores vazios seguros sem interromper o carregamento do `/admin`

#### Scenario: Exposição autenticada em `/admin?api=stats` e refresh manual
- **GIVEN** uma sessão administrativa autenticada no `/admin`
- **WHEN** o cliente requisitar `GET /admin?api=stats` (ou `GET /admin?api=stats&refresh=1`)
- **THEN** a resposta HTTP `200` deve conter o objeto `x402scan` com `origin`, `wallets`, `windows.d1`, `windows.d7`, `windows.d30` e `server_url`
- **AND** com `&refresh=1`, o backend deve ignorar o TTL de `300s` e buscar dados frescos no upstream
- **AND** requisições sem sessão ativa devem continuar recebendo HTTP `401`

#### Scenario: Renderização do painel multijanela na aba Receita e Passes e indicador no Resumo Executivo
- **GIVEN** que o operador autenticado acessa `GET /admin`
- **WHEN** o HTML do cockpit for renderizado
- **THEN** a aba **Receita e Passes** deve exibir o painel dedicado **x402scan — Índice Público On-Chain** com as janelas `24h`, `7d` e `30d`, carteiras monitoradas EVM + Solana, badge de protocolo `x402 v2` e link para `https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50`
- **AND** a aba **Resumo Executivo** deve exibir o indicador compacto com transações e volume `30d` do x402scan
- **AND** páginas públicas (`/`, `/trust`, `/status`) não devem exibir dados administrativos do x402scan
