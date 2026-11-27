# PRD: Fase 2 — Token Safety, Prediction Markets e Batch M2M (`token-safety`, `prediction-markets`, `POST /api/v1/batch`)

**Status:** Aprovado
**Data:** 2026-11-27
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema

Com a conclusão da Fase 1 em produção (`https://grid402.online` operando Dual-Facilitator Coinbase CDP + PayAI, Solana x402 ativa, 24/24 endpoints pagos validados no CDP Bazaar v2 e Índice Oficial de Sanções em 4 blocos), o Grid402 possui liquidez multi-chain e blindagem jurídica sólidas.

Para capturar a próxima onda de demanda de alto volume de agentes autônomos de IA (DeFi agents, trading bots, research agents e frotas MCP), três lacunas críticas foram identificadas:

1. **Ausência de triagem determinística de segurança de tokens (`GET /api/v1/token-safety`):** agentes que operam em Base, Ethereum, Polygon, Arbitrum e Solana precisam verificar em milissegundos se um contrato ou token é honeypot, mintable, proxy, possui taxas abusivas de compra/venda ou baixa liquidez DEX antes de executar swaps ou recomendar ativos — tudo sem precisar gerenciar chaves de API de múltiplos provedores.
2. **Ausência de dados estruturados de mercados de previsão (`GET /api/v1/prediction-markets`):** mercados de previsão (Polymarket e Kalshi) tornaram-se o principal oráculo em tempo real de probabilidades para agentes de pesquisa e decisão, mas possuem APIs heterogêneas e sem camada unificada de normalização, cache ou prova criptográfica `_oracle_proof`.
3. **Atrito e latência em fluxos multi-consulta (`POST /api/v1/batch`):** agentes que precisam consultar múltiplos endpoints em uma mesma decisão (ex.: `dns` + `ssl` + `ofac` + `token-safety`) hoje precisam pagar e assinar N requisições separadas. Um endpoint de lote que aceita até 5 sub-chamadas em uma única liquidação x402 (ou 1 chamada de Session Pass com estorno automático por item falho) reduz drasticamente latência, custo operacional e fricção de integração.

---

## 2. Objetivos

1. **Lançar `GET /api/v1/token-safety`** (`0.0150 USDC` / 3 créditos) cobrindo Base (`8453`), Ethereum (`1`), Polygon (`137`), Arbitrum (`42161`) e Solana (`solana`) usando exclusivamente fontes 100% públicas (GoPlus Security API e DexScreener API pública), com veredito determinístico (`SAFE`, `CAUTION`, `HIGH_RISK`, `CRITICAL`), score `0–100`, cache Zero-DB de `300s` e disclaimer técnico.
2. **Lançar `GET /api/v1/prediction-markets`** (`0.0100 USDC` / 2 créditos) agregando Polymarket Gamma API pública e Kalshi Public Trade API v2 (com fallback elegante se uma das duas falhar), retornando probabilidades normalizadas (`0–100%`), volume 24h, liquidez e data de resolução com cache Zero-DB de `120s`.
3. **Lançar `POST /api/v1/batch`** (preço = soma exata dos itens do lote, de 1 a 5 itens) permitindo execução multi-serviço em uma única cobrança x402 v2 ou Session Pass (com estorno automático de itens que falharem no Session Pass, bloqueio de recursão/rotas administrativas e respeito integral a `allowed_endpoints`, `daily_credit_limit` e `Idempotency-Key`).
4. **Garantir paridade total em todo o ecossistema no mesmo ciclo:** expandir o catálogo de **25 para 28 serviços** (`28/28 UP` em `collector.php`, `/status`, `/catalog`, `/trust`, `/admin`), expandir o servidor MCP de **27 para 30 tools** (`v2.1.0`), atualizar `/.well-known/x402.json`, `openapi.json`, `pricing.json`, `llms.txt`, `llms-full.txt`, `i18n.js` (PT-BR e EN) e aprovar 100% no `scripts/validate_cdp_bazaar.php`.

---

## 3. Não objetivos (fora do escopo)

Explicitamente **NÃO** fazem parte desta Fase 2:
- Endpoints reservados para ciclos futuros: `sec-company`, `sec-filing`, `vuln-audit`, `chain-telemetry` e `/api/v1/global-kyb` (UK Companies House / SAM.gov).
- Recursos planejados para a Fase 3: suporte a Stripe MPP (`WWW-Authenticate: Payment`), assinatura EIP-712 verificável por smart contract on-chain e registro no ERC-8004.
- Contratação de APIs pagas de terceiros ou introdução de bancos de dados SQL (`sqlite3`, `pdo_sqlite`, MySQL, PostgreSQL).
- Alteração na escala oficial do Session Pass (`v2_5000`: `1 crédito = 0.0050 USDC`) ou nos preços vigentes dos 25 endpoints existentes.

---

## 4. Público-alvo e personas

1. **Agente DeFi / Trading Autônomo (Base, Solana, EVM):** consulta `/api/v1/token-safety` antes de aprovar swaps ou interações com contratos recém-lançados; exige resposta estruturada com veredito determinístico e prova assinada `_oracle_proof`.
2. **Agente de Pesquisa / Inteligência de Mercado:** consulta `/api/v1/prediction-markets` para incorporar probabilidades reais de eventos macro, cripto e geopolíticos em análises e relatórios.
3. **Orquestrador de Frota M2M / Servidor MCP:** usa `POST /api/v1/batch` para agrupar até 5 consultas relacionadas em uma única chamada HTTP/MCP e 1 única assinatura x402, minimizando latência e overhead de rede.
4. **Operador do Grid402 (Thiago Campos):** monitora saúde `28/28 UP`, faturamento por serviço e alertas no `/admin`, mantendo custo zero com provedores externos e infraestrutura Zero-DB na Hostinger.

---

## 5. Histórias de usuário

- **HU01:** Como agente DeFi na Base ou Solana, quero consultar `GET /api/v1/token-safety?chain=base&address=0x...` (ou endereço Base58 Solana) e receber um veredito determinístico (`SAFE`/`CAUTION`/`HIGH_RISK`/`CRITICAL`), flags de honeypot/mintable/impostos e liquidez DEX com `_meta` e `_oracle_proof`, pagando `0.0150 USDC` (3 créditos).
- **HU02:** Como agente de pesquisa, quero consultar `GET /api/v1/prediction-markets?q=bitcoin&limit=5` e receber mercados ativos normalizados do Polymarket e Kalshi com probabilidades percentuais, volume 24h e liquidez, pagando `0.0100 USDC` (2 créditos).
- **HU03:** Como orquestrador M2M, quero enviar `POST /api/v1/batch` com até 5 sub-requisições (ex.: `dns`, `ssl`, `token-safety`) pagando exatamente a soma dos preços das sub-requisições em 1 único header `PAYMENT-SIGNATURE` ou Session Pass, recebendo resultados individuais e estorno automático de créditos no Session Pass para qualquer sub-item que falhar.
- **HU04:** Como usuário de MCP (Claude, Cursor, Smithery, Glama), quero ter as 3 novas ferramentas (`grid402_token_safety`, `grid402_prediction_markets`, `grid402_batch`) disponíveis imediatamente nos servidores stdio e HTTP (`/mcp`), totalizando exatamente 30 tools sincronizadas na versão `2.1.0`.
- **HU05:** Como operador do Grid402, quero que os 3 novos endpoints apareçam automaticamente no `/status` (`28/28 UP`), `/catalog`, `/admin`, `/.well-known/x402.json` e passem na validação CDP Bazaar v2 (`scripts/validate_cdp_bazaar.php`).

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Serviço `GET /api/v1/token-safety` (`0.0150 USDC` / 3 créditos):** criar `public_html/services/token_safety.php` aceitando `address` (ou `token`) e `chain` (`base`, `ethereum`, `polygon`, `arbitrum`, `solana`, com auto-detecção de Solana para endereços Base58). Consultar GoPlus Security API pública (`api.gopluslabs.io/api/v1/token_security/{chainId}` e `/solana/token_security`) e DexScreener API pública (`api.dexscreener.com/latest/dex/tokens/{address}`). Calcular score determinístico (`0–100`) e veredito (`SAFE`, `CAUTION`, `HIGH_RISK`, `CRITICAL`) a partir de flags críticas (`is_honeypot`, `is_mintable`, `is_proxy`, `cannot_sell_all`, `buy_tax`, `sell_tax`, `top10_holders_pct`, `liquidity_usd`). Incluir `legal_disclaimer` (*análise técnica automatizada, não constitui recomendação financeira*), cache Zero-DB atômico por `300s`, retorno HTTP `503` pré-cobrança se ambas as fontes falharem, HTTP `400` pré-cobrança se endereço/rede for inválido e teaser gratuito no HTTP `402`. | Must |
| RF02 | **Serviço `GET /api/v1/prediction-markets` (`0.0100 USDC` / 2 créditos):** criar `public_html/services/prediction_markets.php` aceitando parâmetros opcionais `q` (termo de busca), `source` (`all`, `polymarket`, `kalshi`, padrão `all`), `status` (`active`, `closed`, padrão `active`) e `limit` (`1..20`, padrão `10`). Consultar Polymarket Gamma API pública (`gamma-api.polymarket.com/markets`) e Kalshi Public API v2 (`api.elections.kalshi.com/trade-api/v2/markets`), normalizando cada mercado para schema unificado (`id`, `source`, `title`, `slug`, `url`, `outcomes` com probabilidades `0–100%`, `volume_24h_usd`, `liquidity_usd`, `end_date`, `active`). Operar com degradação graciosa se uma das duas fontes falhar (retornar a fonte saudável indicando `sources_status`), retornar HTTP `503` pré-cobrança apenas se ambas falharem, cache Zero-DB por `120s` e teaser gratuito no HTTP `402`. | Must |
| RF03 | **Endpoint `POST /api/v1/batch` (preço dinâmico = soma exata dos itens):** implementar `public_html/services/batch.php` e roteamento dedicado em `public_html/index.php` aceitando payload JSON `{"items": [{"service": "<slug>", "params": {...}}, ...]}` com **1 a 5 itens**. Rejeitar com HTTP `400` lotes vazios, `> 5` itens, JSON malformado, payload `> 16 KB`, serviços inexistentes ou serviços bloqueados (`pass`, `pass-emitir`, `batch`, `verify`, `summary`). Calcular deterministicamente o custo total em `raw_units` como a soma exata dos preços oficiais dos itens do lote. No desafio HTTP `402`, anunciar esse valor total dinâmico em `accepts` (x402 v2 Coinbase CDP + PayAI) junto com o resumo de itens. | Must |
| RF04 | **Cobrança justa, Spend Controls e Idempotência no `POST /api/v1/batch`:** (a) em **Session Pass**, validar `allowed_endpoints` para *cada* sub-serviço do lote (bloqueando com `403 PassEndpointRestricted` antes de debitar se qualquer item estiver fora da whitelist), debitar o custo total sob `daily_credit_limit` e **estornar automaticamente** os créditos de qualquer sub-item que retorne erro ou falha (`status !== 200`); (b) em **x402 gasless / on-chain**, só liquidar `/settle` se pelo menos 1 item do lote retornar sucesso (se 100% falharem, abortar `/settle` e retornar HTTP `503`); (c) em **Sandbox (`X-Payment: demo`)**, limitar o lote a no máximo **2 itens** (HTTP `400` se `> 2`); (d) suportar `Idempotency-Key` (TTL `300s`) com hash canônico do corpo JSON do lote evitando débito duplicado; (e) assinar a resposta consolidada com `_meta` + `_oracle_proof` ECDSA secp256k1. | Must |
| RF05 | **Registro no Catálogo, Coletor e Páginas Públicas (`28/28 UP`):** registrar `token-safety`, `prediction-markets` e `batch` em `grid402_get_utility_services()` (`public_html/config.php`), atualizar `public_html/collector.php` (garantindo `28/28 UP` em `summary_free.json` com `upstream_source`, `cache_ttl_sec` e `latency_ms`), atualizar `public_html/index.php`, `public_html/catalog.php`, `public_html/trust.php`, `public_html/assets/js/i18n.js` (PT-BR e EN) e `public_html/includes/admin_cockpit_view.php` + `public_html/admin.php` (tabela de preços, exportação CSV e barras de volume). | Must |
| RF06 | **Paridade MCP (`v2.1.0`, 30 tools):** adicionar `grid402_token_safety`, `grid402_prediction_markets` e `grid402_batch` com paridade estrita em `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs`, `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` (totalizando exatamente **30 tools** na versão `2.1.0`). | Must |
| RF07 | **Paridade de Descoberta, OpenAPI, Pricing e CDP Bazaar v2:** atualizar `public_html/well_known_x402.php` (e fallback estático `x402.json` com metadados Bazaar v2 sem `$ref` e descrição `<= 500` chars), `public_html/pricing.json`, `public_html/openapi.json`, `public_html/.well-known/ai-agent.json`, `public_html/llms.txt`, `public_html/llms-full.txt`, `README.md` (sem o caractere `&`) e aprovar 100% dos endpoints pagos em `scripts/validate_cdp_bazaar.php`. | Must |
| RF08 | **Cobertura de Testes Automatizados e Invariantes:** criar `tests/test_phase2_token_safety_predictions_batch.php` (determinístico offline com mocks/fixtures + servidor HTTP local) cobrindo todos os cenários de `RF01` a `RF07`, e atualizar os testes existentes que verificam contagens exatas (`25` → `28` serviços e `27` → `30` tools MCP) sem introduzir regressões. | Must |

---

## 7. Requisitos não funcionais

- **Performance e Timeouts (Hostinger PHP 8.3):**
  - Chamadas upstream em `token-safety` e `prediction-markets` com `CURLOPT_CONNECTTIMEOUT <= 2s` e `CURLOPT_TIMEOUT <= 4s`.
  - Respostas cacheadas em Zero-DB devem responder em `<= 50 ms`.
  - `POST /api/v1/batch` limitado a 5 itens garantindo execução total dentro do limite de timeout do PHP/LiteSpeed.
- **Armazenamento Zero-DB e Concorrência:**
  - Caches em `GRID402_PRIVATE_DIR` usando exclusivamente `grid402_atomic_write()` (sem SQLite/MySQL).
  - Estornos e débitos de Session Pass em `batch` via `PassStorageInterface` com `flock(LOCK_EX)` seguro (nunca dois locks simultâneos).
- **Segurança, Zero-PII e Blindagem Jurídica:**
  - Validação estrita de entrada (`address` regex EVM `0x[a-fA-F0-9]{40}` ou Solana Base58 `32..44` chars; `q` sanitizado `<= 120` chars; `batch` body `<= 16 KB`).
  - Assinatura criptográfica por referência `grid402_sign_data($result, $isSandbox)` com `_meta` + `_oracle_proof`.
  - Fontes 100% públicas sem API keys pagas; disclaimer técnico explícito em `token-safety` e `prediction-markets`.
  - Zero caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md`, `smithery.yaml`, `llms.txt` e descrições JSON do Bazaar.

---

## 8. Fluxos e UX

### Fluxo 1 — `GET /api/v1/token-safety`
1. Agente chama `GET /api/v1/token-safety?chain=base&address=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`.
2. Sem pagamento → HTTP `402` com `accepts` (`15000 raw` = `0.0150 USDC`) e `preview` gratuito (rede detectada + endereço normalizado).
3. Com pagamento válido → verifica cache `300s`; em cache miss consulta GoPlus + DexScreener, calcula score/veredito determinístico, grava cache atômico, anexa `_meta` e `_oracle_proof`, retorna HTTP `200`.
4. Se formato de endereço for inválido → HTTP `400` pré-cobrança. Se ambos os upstreams falharem → HTTP `503` pré-cobrança (com estorno se Session Pass).

### Fluxo 2 — `GET /api/v1/prediction-markets`
1. Agente chama `GET /api/v1/prediction-markets?q=fed&limit=5`.
2. Sem pagamento → HTTP `402` com `accepts` (`10000 raw` = `0.0100 USDC`) e `preview` truncado.
3. Com pagamento válido → verifica cache `120s`; consulta Polymarket Gamma e Kalshi Public API, normaliza probabilidades `0–100%`, ordena por volume 24h, anexa `_meta` + `_oracle_proof`, retorna HTTP `200`.

### Fluxo 3 — `POST /api/v1/batch`
1. Agente envia `POST /api/v1/batch` com `{"items":[{"service":"dns","params":{"domain":"base.org"}},{"service":"token-safety","params":{"chain":"base","address":"0x..."}}]}`.
2. Roteador valida estrutura (1..5 itens, sem serviços proibidos) e calcula soma exata (`5000 + 15000 = 20000 raw` = `0.0200 USDC` / 4 créditos).
3. Sem pagamento → HTTP `402` anunciando exatamente `20000 raw` em `accepts` + resumo do plano do lote.
4. Com `Idempotency-Key` repetido em `300s` → retorna resposta original em cache (`X-Idempotent-Replay: true`, zero débito).
5. Com pagamento válido → executa cada sub-item internamente, coleta resultados individuais (`ok`, `status`, `data`/`error`), estorna automaticamente créditos no Session Pass para sub-itens que falharem, anexa `_meta` + `_oracle_proof` e retorna HTTP `200` (ou `503` sem cobrança se 100% dos itens falharem).

---

## 9. Métricas de sucesso

- **Cobertura operacional:** `28/28 UP` em `/status` e `summary_free.json`.
- **Paridade MCP e Bazaar:** `30/30` tools MCP sincronizadas (`v2.1.0`) e `27/27` endpoints pagos aprovados em `scripts/validate_cdp_bazaar.php` (0 falhas).
- **Qualidade:** 100% de aprovação em toda a bateria de testes (nova suíte Fase 2 + todas as suítes existentes + `openspec validate --all --strict`).
- **Performance:** respostas em cache `<= 50 ms` e `POST /api/v1/batch` (5 itens em cache) `<= 150 ms`.

---

## 10. Riscos, dependências e premissas

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Rate limit ou instabilidade em GoPlus / DexScreener / Polymarket / Kalshi | Média | Cache Zero-DB (`300s` / `120s`), timeouts curtos (`<= 4s`), degradação graciosa (1 fonte ok já atende) e `503` pré-cobrança se todas falharem |
| Timeout em lote com 5 itens pesados não cacheados | Média | Cap estrito de 5 itens por lote + recomendação de `Idempotency-Key` |
| Quebra de testes legados que fazem assert fixo em `25` serviços ou `27` tools | Alta | Mapear e atualizar todos os testes que fixam `25`/`27` na tarefa dedicada de testes da Fase 2 |

---

## 11. Questões em aberto

Nenhuma questão em aberto — todas as decisões de escopo, preços, fontes e tratamento de falha parcial foram alinhadas com o operador na etapa de Grill-me.
