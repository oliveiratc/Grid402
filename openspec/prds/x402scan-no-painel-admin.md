# PRD: Painel x402scan no Cockpit `/admin`

**Status:** Aprovado para especificação OpenSpec
**Data:** 2026-11-27
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema

O Grid402 já monitora faturamento interno em `metrics.json` / `growth.json` / `mp_orders.json` e saldo de carteira on-chain no `/admin`, enquanto o diretório público **x402scan** (`https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50`) indexa externamente as liquidações x402 enviadas para as carteiras recebedoras do Grid402 (EVM e Solana), contagem de compradores únicos e status de indexação do servidor (`x402 v2`).

Hoje o operador precisa abrir duas abas separadas (`x402scan.com` e `/admin`) para conferir se os pagamentos registrados internamente e os disparos de seeding on-chain estão sendo corretamente indexados pelo ecossistema público x402.

---

## 2. Objetivos

1. **Integrar telemetria pública do x402scan no `/admin` (`public_html/services/x402scan.php`)** de forma 100% **Zero-API-Key** e **Zero-DB** (`private_data/x402scan_cache.json` com TTL `300s` e gravação atômica via `grid402_atomic_write()`).
2. **Exibir comparativo multijanela fiel ao índice público (`24h`, `7d` e `30d`):** consultar `public.origins.get` e `public.stats.overall` (`timeframe` `1`, `7` e `30`) somando as carteiras recebedoras EVM (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`) e Solana (`CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`), evitando as views `timeframe=0`/`90`/`365` do x402scan que possuem lag de materialização ou erro interno.
3. **Renderizar no cockpit `/admin` autenticado sem impactar latência:** apresentar o bloco dedicado **"x402scan — Índice Público On-Chain"** na aba **Receita e Passes** e um card resumo na aba **Resumo Executivo**, expondo o objeto `x402scan` também em `GET /admin?api=stats` (com suporte a refresh manual via `&refresh=1`).
4. **Garantir resiliência total:** timeouts curtos (`<= 4s`) e fallback automático para `stale_cache` caso o x402scan esteja lento ou indisponível, sem nunca bloquear o carregamento ou polling de `5s` do `/admin`.

---

## 3. Não objetivos (fora do escopo)

Explicitamente **NÃO** entram nesta entrega:
- Exibição de métricas do x402scan em páginas públicas (`/`, `/trust`, `/status` ou endpoints `/api/v1/*`).
- Listagem de transferências individuais linha a linha ou exportação CSV separada do x402scan.
- Disparo remoto de `registerFromOrigin` / re-scan automático via browser a partir do `/admin`.
- Adoção de bancos de dados SQL ou dependência de API keys externas.

---

## 4. Público-alvo e personas

- **Operador do Grid402 (Thiago Campos):** acompanha em um único cockpit autenticado (`/admin`) o cruzamento entre a receita interna registrada pelo gateway/Mercado Pago, os saldos on-chain das carteiras e a prova de indexação pública externa no x402scan.

---

## 5. Histórias de usuário

- **HU01:** Como operador autenticado no `/admin`, quero visualizar na aba **Receita e Passes** um painel do **x402scan** comparando **24h / 7d / 30d** (transações, volume em USDC, compradores únicos e timestamp da última liquidação), para acompanhar a tração pública x402 sem abrir outra aba.
- **HU02:** Como operador, quero ver um indicador compacto na aba **Resumo Executivo** com as transações e o volume **30d** indexados no x402scan e um link direto para a página do servidor no x402scan.
- **HU03:** Como operador, quero que o botão **Atualizar** do cockpit force atualização do cache do x402scan (`?api=stats&refresh=1`), enquanto o polling normal (`5s`) reutiliza o cache em disco de `300s` em `< 5 ms`.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Serviço `public_html/services/x402scan.php`:** implementar `grid402_get_x402scan_stats(bool $forceRefresh = false)` consultando via tRPC HTTP GET público `public.origins.get` (ID `00294038-51ff-4073-ab29-c59fca221a50`) e `public.stats.overall` para `timeframe` `1` (`24h`), `7` (`7d`) e `30` (`30d`) incluindo as carteiras EVM (`GRID402_WALLET_ADDRESS`) e Solana (`GRID402_SOLANA_WALLET_ADDRESS`), com override de transporte testável em `$GLOBALS['grid402_x402scan_http_override']`. | Must |
| RF02 | **Cache Zero-DB (`300s`) e fallback `stale_cache`:** persistir o snapshot normalizado em `GRID402_PRIVATE_DIR . '/x402scan_cache.json'` via `grid402_atomic_write()`. Enquanto a idade for `< 300s` (e `$forceRefresh === false`), responder do cache com `source: "cache"`. Em falha upstream sem dados válidos novos, retornar o último cache válido em disco com `source: "stale_cache"` e `stale: true` (ou estado estruturado `source: "unavailable"` caso não exista cache prévio), sem lançar exceções não tratadas. | Must |
| RF03 | **Integração no `/admin?api=stats`:** expor o objeto `x402scan` no payload autenticado de `public_html/admin.php` (preservando HTTP `401` sem sessão). Aceitar `?api=stats&refresh=1` para ignorar o TTL de `300s` e buscar snapshot atualizado no x402scan. | Must |
| RF04 | **UI na aba `Receita e Passes` (`admin_cockpit_view.php`):** adicionar painel dedicado **"x402scan — Índice Público On-Chain"** exibindo comparativo das 3 janelas (`24h`, `7d`, `30d`: transações, volume USDC com 4 casas decimais, compradores únicos, última liquidação indexada), badge de protocolo `x402 v2` (`has_x402_v2`), última atualização do servidor no x402scan, badge de origem (`Ao vivo` / `Cache 5m` / `Cache antigo` / `Indisponível`), carteiras monitoradas EVM + Solana (truncadas de forma legível) e link externo `https://www.x402scan.com/server/00294038-51ff-4073-ab29-c59fca221a50`. Atualizar dinamicamente via JS durante o polling do cockpit (`applyStatsPayload`) e pelo botão **Atualizar** (`refresh=1`). | Must |
| RF05 | **Indicador na aba `Resumo Executivo`:** exibir card compacto com volume e transações `30d` no x402scan, compradores únicos, status do índice (`x402 v2`) e link direto para o x402scan, também sincronizado via JS no polling. | Must |
| RF06 | **Testes automatizados:** criar `tests/test_admin_x402scan.php` cobrindo hit/miss de cache `300s`, conversão raw→USDC (`total_amount / 1e6`), dual-wallet payload, fallback `stale_cache`, degradação `unavailable`, endpoint HTTP `/admin?api=stats` (incluindo `&refresh=1`) e renderização SSR das abas no HTML do `/admin`, além de validar regressão com `tests/test_admin_dashboard.php`. | Must |

---

## 7. Requisitos não funcionais

- **Performance:** polling automático do `/admin` a cada `5s` não deve disparar requisições HTTP externas dentro da janela de cache de `300s` (`<= 5 ms` de leitura de cache). Em cache miss, usar `CURLOPT_CONNECTTIMEOUT <= 2s` e `CURLOPT_TIMEOUT <= 4s`.
- **Segurança e Privacidade:** acesso estritamente autenticado (sessão administrativa + CSRF quando aplicável em mutações; endpoint JSON protegido por HTTP `401`). Não expor segredos nem dados pessoais. Escapar todos os textos renderizados com `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` no SSR e `esc()` no JS.
- **Compatibilidade PHP:** nenhuma dependência além das extensões padrão (`curl`, `json`). Nunca usar o caractere `&` quebrando XML/HTML sem escape adequado.

---

## 8. Fluxos e UX

1. Operador acessa `/admin` (ou frontend executa polling `GET /admin?api=stats`):
   - Backend chama `grid402_get_x402scan_stats(false)`.
   - Se `x402scan_cache.json` tiver idade `< 300s`, devolve imediatamente `source: "cache"`.
   - Caso contrário, consulta `public.origins.get` + `public.stats.overall` (`1d`, `7d`, `30d`), salva atomicamente e devolve `source: "live"`.
2. Operador clica no botão **Atualizar** no cabeçalho do cockpit:
   - Frontend chama `GET /admin?api=stats&refresh=1` (e remove `&refresh=1` no polling automático).
   - Backend força consulta ao x402scan, atualiza `x402scan_cache.json` e atualiza os cards de **Resumo Executivo** e **Receita e Passes**.
3. Caso o x402scan esteja fora do ar ou dê timeout:
   - Se houver cache anterior em disco, devolve `stale_cache` com aviso discreto no badge ("Cache anterior · upstream indisponível").
   - Se não houver cache prévio, devolve valores `null` / `unavailable` exibindo "Indisponível" nos cards sem interromper nenhuma outra métrica do cockpit.

---

## 9. Métricas de sucesso

- `GET /admin?api=stats` retorna objeto `x402scan` íntegro contendo `windows.d1`, `windows.d7`, `windows.d30`, `origin` e `status`.
- 100% de aprovação em `tests/test_admin_x402scan.php` e `tests/test_admin_dashboard.php` sem regressões nas suítes existentes.
- Zero aumento no tempo de polling quando `x402scan_cache.json` estiver dentro do TTL de `300s`.

---

## 10. Riscos, dependências e premissas

- **Dependência de API pública não documentada (tRPC do x402scan):** a estrutura tRPC (`public.origins.get` / `public.stats.overall`) pode mudar no futuro no servidor do x402scan; o isolamento em `services/x402scan.php` + fallback `stale_cache`/`unavailable` garante que mudanças externas nunca quebrem o `/admin`.
- **Diferença natural entre o `/admin` e o x402scan:** o x402scan contabiliza apenas liquidações x402 indexadas publicamente nas redes suportadas (não contabiliza compras via Pix/Mercado Pago nem créditos pré-pagos gastos via Session Pass além da emissão/liquidação x402 original), motivo pelo qual o rótulo no painel explicita tratar-se do **Índice Público On-Chain**.

---

## 11. Questões em aberto

- Nenhuma pendência em aberto para esta entrega.
