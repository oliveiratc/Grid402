# PRD: Onda 3A — SEC EDGAR, Auditoria de Vulnerabilidades e Telemetria Multi-Chain (`sec-company`, `vuln-audit`, `chain-telemetry`)

**Status:** Em revisão
**Data:** 2026-11-27
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema

Com a conclusão das Fases 1 e 2 em produção (`https://grid402.online` operando com **28/28 serviços UP**, Dual-Facilitator Coinbase CDP + PayAI em Base, Polygon, Arbitrum e Solana USDC, `30` tools MCP na `v2.1.0` e `POST /api/v1/batch` validado on-chain), o Grid402 já cobre triagem Web3 de tokens e mercados de previsão.

Para capturar a próxima onda de tráfego orgânico M2M de altíssima recorrência mantendo a política **100% Zero-API-Key obrigatória** e armazenamento **Zero-DB**, foram priorizadas 3 lacunas globais na **Onda 3A**:

1. **Dados corporativos e regulatórios dos EUA sem fricção (`GET /api/v1/sec-company`):** agentes financeiros, analistas e robôs de research precisam resolver ticker para CIK, consultar perfil regulatório (SIC, exchanges, ano fiscal) e listar filings recentes (`10-K`, `10-Q`, `8-K`) direto da SEC EDGAR — respeitando a política de `User-Agent` da SEC e evitando sobrecarga de memória com JSONs XBRL gigantes (`15–30 MB`) no PHP compartilhado da Hostinger.
2. **Auditoria determinística de vulnerabilidades em tempo real (`GET /api/v1/vuln-audit`):** agentes de programação e pipelines DevSecOps (Claude Code, Cursor, CI/CD) precisam checar rapidamente se um pacote (`npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) ou identificador (`CVE-*`, `GHSA-*`) possui vulnerabilidades conhecidas no **OSV.dev** e se está sob exploração ativa no catálogo oficial **CISA KEV**, recebendo um veredito determinístico e versão corrigida sem gerenciar múltiplas APIs.
3. **Telemetria multi-chain consolidada (`GET /api/v1/chain-telemetry`):** agentes DeFi e de execução on-chain precisam consultar bloco/slot atual, gás/taxas prioritárias, latência RPC e TVL/stablecoins de **Base, Ethereum, Polygon, Arbitrum e Solana** em um único endpoint normalizado com prova assinada `_oracle_proof` e fallback entre fontes públicas.

---

## 2. Objetivos

1. **Lançar `GET /api/v1/sec-company`** (`0.0100 USDC` / 2 créditos / `10000` raw) integrando a SEC EDGAR oficial (submissions + mapa ticker-CIK em cache de 24h) com `include_facts=true` opcional protegido por limite rígido de bytes/memória (`8 MB`) e cache Zero-DB de `3600s`.
2. **Lançar `GET /api/v1/vuln-audit`** (`0.0050 USDC` / 1 crédito / `5000` raw) suportando consulta por **pacote** (`ecosystem` + `package` + `version` opcional) ou **ID direto** (`id=CVE-...` / `GHSA-...`), cruzando **OSV.dev** com **CISA KEV** (cache `6h` com fallback para última cópia válida), veredito determinístico (`CLEAN` a `CRITICAL_KEV`) e cache Zero-DB de `900s`.
3. **Lançar `GET /api/v1/chain-telemetry`** (`0.0050 USDC` / 1 crédito / `5000` raw) cobrindo `base`, `ethereum`, `polygon`, `arbitrum` e `solana` via RPCs públicos multi-fallback + DeFiLlama público, com degradação graciosa (`partial_data`) e cache Zero-DB de `60s`.
4. **Garantir paridade completa do ecossistema no mesmo ciclo:** expandir o catálogo de **28 para 31 serviços UP** (`30` rotas pagas em `/.well-known/x402.json`), habilitar os 3 novos endpoints dentro de **`POST /api/v1/batch`**, atualizar o servidor MCP para **`v2.2.0` (`33 tools`)**, regenerar OpenAPI/Bazaar/RapidAPI e manter 100% dos testes passando sem regressões.

---

## 3. Não objetivos (fora do escopo)

Explicitamente **NÃO** entram nesta Onda 3A:
- Endpoints reservados para futuras ondas: `GET /api/v1/sec-filing` (leitura integral/seções de filings EDGAR) e `GET /api/v1/global-kyb` (UK Companies House / SAM.gov).
- Atualização dos SDKs Node/Python, `integrations/agentkit` e publicação de `SKILL.md` público (previstos para Onda 3B).
- Evoluções de protocolo da Onda 3B: Stripe Machine Payments Protocol (MPP), assinatura EIP-712 verificável por smart contract on-chain e registro no ERC-8004.
- Contratação de APIs pagas, exigência de API keys externas obrigatórias para o servidor operar ou adoção de bancos de dados SQL.

---

## 4. Público-alvo e personas

1. **Agente Financeiro / Equity Research:** consulta `/api/v1/sec-company` por `ticker` ou `cik` para obter perfil corporativo, filings recentes (`10-K`, `10-Q`, `8-K`) e métricas XBRL essenciais sob assinatura `_oracle_proof`.
2. **Agente DevSecOps / Assistente de Código (Claude Code, Cursor):** invoca `/api/v1/vuln-audit` (via HTTP x402, Session Pass ou MCP `grid402_vuln_audit`) antes de instalar dependências ou ao investigar uma CVE, verificando exploração ativa na CISA KEV.
3. **Agente DeFi / Trading / Orquestrador Multi-Chain:** consulta `/api/v1/chain-telemetry` (isoladamente ou via `POST /api/v1/batch` junto com `/token-safety`) para verificar congestionamento/gás, altura de bloco e TVL antes de rotear liquidez.
4. **Operador do Grid402 (Thiago Campos):** acompanha saúde `31/31 UP`, telemetria de uso e faturamento no `/admin` com custo zero de provedores externos.

---

## 5. Histórias de usuário

- **HU01:** Como agente financeiro, quero consultar `GET /api/v1/sec-company?ticker=AAPL&forms=10-K,10-Q&limit=5` e receber CIK formatado, código SIC, setor, lista filtrada de filings com links do EDGAR, `legal_disclaimer`, `_meta` e `_oracle_proof` pagando `0.0100 USDC` (2 créditos).
- **HU02:** Como agente financeiro que precisa de dados contábeis, quero passar `include_facts=true` em `/api/v1/sec-company` e receber resumo financeiro XBRL quando dentro do limite seguro de tamanho, ou um aviso gracioso (`facts_status: "skipped_size_limit"`) sem erro HTTP 500 caso o JSON da SEC exceda `8 MB`.
- **HU03:** Como agente DevSecOps, quero consultar `GET /api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20` (ou `?id=CVE-2021-44228`) pagando `0.0050 USDC` (1 crédito) e receber lista normalizada de vulnerabilidades, flags `actively_exploited_kev`, versão corrigida (`fixed_in`) e veredito determinístico (`CLEAN` a `CRITICAL_KEV`).
- **HU04:** Como agente DeFi, quero consultar `GET /api/v1/chain-telemetry?chain=base` pagando `0.0050 USDC` (1 crédito) e receber bloco/slot atual, taxas de gás/prioridade, latência RPC e TVL DeFiLlama com degradação graciosa se um dos dois provedores oscilar.
- **HU05:** Como orquestrador M2M ou usuário MCP (`v2.2.0`), quero chamar `grid402_sec_company`, `grid402_vuln_audit` e `grid402_chain_telemetry` entre as **33 tools** sincronizadas e combinar os novos serviços dentro de `POST /api/v1/batch`.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Serviço `GET /api/v1/sec-company` (`0.0100 USDC` / 2 créditos / `10000` raw):** criar `public_html/services/sec_company.php` aceitando `ticker` (1–10 chars alfanuméricos/ponto/hífen) ou `cik` (1–10 dígitos, normalizado com zero-padding para 10 dígitos), `limit` (`1..25`, padrão `10`), `forms` opcional (lista CSV de formulários SEC, ex.: `10-K,10-Q,8-K`) e `include_facts` booleano (`false` padrão). Consultar `https://www.sec.gov/files/company_tickers.json` (cache Zero-DB `86400s` em `private_data/`) e `https://data.sec.gov/submissions/CIK{cik10}.json` (cache `3600s`) enviando header `User-Agent` institucional compatível com a política SEC (`Grid402/3.8 (contato@grid402.online)`). Quando `include_facts=true`, buscar `https://data.sec.gov/api/xbrl/companyfacts/CIK{cik10}.json` protegido por teto rígido de tamanho (`8 MB`), extraindo métricas US-GAAP recentes (receita, lucro líquido, ativos, passivos, caixa, EPS) e sinalizando `facts_status` (`ok`, `skipped_size_limit`, `unavailable` ou `not_requested`). Rejeitar entrada inválida com HTTP `400` pré-cobrança, ticker/CIK inexistente com HTTP `404` sem cobrar (estornando Session Pass / abortando settle), indisponibilidade SEC sem cache com HTTP `503` pré-cobrança, e incluir `legal_disclaimer`, `_meta`, `_oracle_proof` e teaser `402`. | Must |
| RF02 | **Serviço `GET /api/v1/vuln-audit` (`0.0050 USDC` / 1 crédito / `5000` raw):** criar `public_html/services/vuln_audit.php` aceitando **Modo Pacote** (`ecosystem` + `package` + `version` opcional, normalizando ecossistemas suportados como `npm`, `PyPI`, `Go`, `Maven`, `Packagist`, `Cargo`, `NuGet`, `RubyGems`) **OU Modo ID Direto** (`id` no padrão `CVE-*`, `GHSA-*`, `OSV-*` etc.). Consultar `https://api.osv.dev/v1/query` ou `/v1/vulns/{id}` e cruzar IDs e aliases `CVE-*` com o catálogo oficial **CISA KEV** (`known_exploited_vulnerabilities.json`, cache Zero-DB `21600s` em `private_data/cisa_kev_cache.json`, preservando a última cópia íntegra em caso de falha da CISA). Normalizar vulnerabilidades (`id`, `aliases`, `summary`, `severity`, `cvss_score`, `actively_exploited_kev`, `kev_due_date`, `fixed_in`, `references`), resumir contagens por severidade e calcular veredito determinístico (`CLEAN`, `LOW`, `MODERATE`, `HIGH`, `CRITICAL`, `CRITICAL_KEV`). Cache Zero-DB de `900s`, HTTP `400` pré-cobrança para parâmetros inválidos, HTTP `404` pré-cobrança/estornável para ID inexistente, HTTP `503` se OSV falhar sem cache e teaser `402`. | Must |
| RF03 | **Serviço `GET /api/v1/chain-telemetry` (`0.0050 USDC` / 1 crédito / `5000` raw):** criar `public_html/services/chain_telemetry.php` aceitando `chain` (`base` padrão, `ethereum`, `polygon`, `arbitrum`, `solana`). Consultar RPC público oficial com fallback multi-endpoint (`eth_blockNumber`, `eth_gasPrice`, `eth_maxPriorityFeePerGas` em EVM; `getSlot` e `getRecentPrioritizationFees` em Solana) e DeFiLlama público (`https://api.llama.fi/v2/chains` com cache compartilhado de `300s`). Consolidar altura de bloco/slot, taxas em gwei ou lamports, latência RPC medida e TVL da cadeia. Degradar graciosamente (`partial_data: true` e `sources_status`) se apenas RPC ou DeFiLlama responder; retornar HTTP `503` sem cobrar apenas se ambas as fontes falharem sem cache válido. Cache Zero-DB de `60s`, HTTP `400` pré-cobrança para rede inválida e teaser `402`. | Must |
| RF04 | **Suporte em `POST /api/v1/batch`:** integrar `sec-company` (`10000` raw), `vuln-audit` (`5000` raw) e `chain-telemetry` (`5000` raw) como sub-serviços válidos no executor e cotador de `public_html/services/batch.php`, preservando soma exata, verificação prévia de `allowed_endpoints`, `daily_credit_limit`, limite sandbox (2 itens) e estorno automático por falha de sub-item no Session Pass. | Must |
| RF05 | **Catálogo, Coletor, UI e Admin (`31/31 UP`):** registrar os 3 serviços em `grid402_get_utility_services()` (`public_html/config.php`), integrar em `collector.php` (`31/31 UP` em `summary_free.json`), `index.php` (roteamento, preços, teasers `402`, playgrounds/landing), `catalog.php`, `trust.php`, `agents.php`, `assets/js/i18n.js` (`?v=3.8.0`), `admin.php` e `includes/admin_cockpit_view.php`. | Must |
| RF06 | **Paridade MCP (`v2.2.0`, 33 tools):** adicionar `grid402_sec_company`, `grid402_vuln_audit` e `grid402_chain_telemetry` sincronizados entre `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/package.json`, `mcp/test_mcp.mjs`, `mcp/README.md`, `server.json`, `public_html/mcp.php`, `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` (total = `33` tools na versão `2.2.0`). | Must |
| RF07 | **Descoberta, OpenAPI, Bazaar v2 e RapidAPI:** atualizar `public_html/well_known_x402.php` (e fallback estático `.well-known/x402.json` com `30` rotas pagas sem `$ref`), `pricing.json`, `openapi.json`, `.well-known/ai-agent.json`, `llms.txt`, `llms-full.txt`, `README.md`, `CHANGELOG.md`, `scripts/generate_rapidapi_openapi.mjs` (+ JSONs em `docs/revenue/openapi/`) e `scripts/bazaar_seed.mjs`, aprovando `30/30` no `scripts/validate_cdp_bazaar.php` e mantendo zero `&` e zero literal `solana` nos 6 manifestos estáticos protegidos. | Must |
| RF08 | **Testes Automatizados e Regressão:** criar `tests/test_phase3a_sec_vuln_telemetry.php` cobrindo todos os fluxos offline via injeção/fixtures + servidor HTTP local, e atualizar os testes de contagem existentes (`28` → `31` serviços, `27` → `30` pagos, `30` → `33` tools MCP) garantindo 100% de aprovação. | Must |

---

## 7. Requisitos não funcionais

- **Performance e Memória (Hostinger LiteSpeed PHP 8.1+):**
  - Timeouts rígidos em requisições externas (`CURLOPT_CONNECTTIMEOUT <= 2s`, `CURLOPT_TIMEOUT <= 5s`, respeitando teto de `8 MB` no download opcional de `companyfacts` XBRL).
  - Respostas servidas a partir do cache Zero-DB devem responder em `<= 50 ms`.
- **Armazenamento Zero-DB e Concorrência:**
  - Todos os caches e índices (`sec_tickers`, `sec_company`, `cisa_kev_cache.json`, `vuln_audit`, `defillama_chains`, `chain_telemetry`) gravados em `GRID402_PRIVATE_DIR` com `grid402_atomic_write()` (sem bancos SQL).
  - Nenhuma extensão PHP fora do padrão (usar `preg_match('//u', ...)` em vez de exigir `mbstring`).
- **Segurança, Zero-PII e Higiene:**
  - Sanitização estrita de `ticker`, `cik`, `ecosystem`, `package`, `version`, `id` e `chain` antes de montar URLs externas (sem SSRF nem path traversal).
  - Assinatura por referência `grid402_sign_data($result, $isSandbox)` com `_meta` + `_oracle_proof` em todas as respostas pagas/sandbox HTTP `200`.
  - Nenhum `&` nas documentações/manifestos monitorados e nenhuma menção literal à palavra proibida nos 6 arquivos estáticos verificados por `tests/test_revenue_unblock_change1.php`.

---

## 8. Fluxos e UX

### Fluxo 1 — `GET /api/v1/sec-company`
1. Agente chama `GET /api/v1/sec-company?ticker=AAPL&forms=10-K,10-Q&limit=5`.
2. Parâmetro inválido (`ticker` ou `cik` ausente/inválido) → HTTP `400` pré-cobrança.
3. Sem header de pagamento → HTTP `402` anunciando `10000` raw (`0.0100 USDC` / 2 créditos) e `preview` gratuito.
4. Com pagamento válido → verifica cache `3600s`; em miss resolve ticker→CIK (cache 24h), consulta SEC `submissions` (+ `companyfacts` apenas se `include_facts=true` e dentro de `8 MB`), anexa `_meta` e `_oracle_proof`, retorna HTTP `200` (ou `404`/`503` com estorno automático no Session Pass se não existir/SEC falhar).

### Fluxo 2 — `GET /api/v1/vuln-audit`
1. Agente chama `GET /api/v1/vuln-audit?ecosystem=npm&package=lodash&version=4.17.20` ou `?id=CVE-2021-44228`.
2. Sem header de pagamento → HTTP `402` anunciando `5000` raw (`0.0050 USDC` / 1 crédito) e `preview` gratuito.
3. Com pagamento válido → verifica cache `900s`; consulta OSV.dev e cruza IDs/aliases com `cisa_kev_cache.json`, calcula severidade e veredito (`CLEAN` a `CRITICAL_KEV`), grava cache, assina com `_meta` + `_oracle_proof`, retorna HTTP `200`.

### Fluxo 3 — `GET /api/v1/chain-telemetry`
1. Agente chama `GET /api/v1/chain-telemetry?chain=base`.
2. `chain` inválida → HTTP `400` pré-cobrança.
3. Sem pagamento → HTTP `402` anunciando `5000` raw (`0.0050 USDC` / 1 crédito).
4. Com pagamento válido → verifica cache `60s`; consulta RPC público (com fallback) e DeFiLlama TVL; entrega HTTP `200` mesmo se apenas uma das fontes responder (`partial_data: true`), ou HTTP `503` com estorno se ambas falharem.

---

## 9. Métricas de sucesso

- **Cobertura operacional:** `31/31 UP` em `/status` e `summary_free.json`.
- **Paridade MCP e Bazaar:** `33/33` tools sincronizadas (`v2.2.0`) e `30/30` endpoints pagos aprovados em `scripts/validate_cdp_bazaar.php` com `0` falhas.
- **Qualidade e Regressão:** 100% de aprovação em `tests/test_phase3a_sec_vuln_telemetry.php`, todas as suítes PHP existentes, `node mcp/test_mcp.mjs` e `npx -y @fission-ai/openspec@latest validate --all --strict`.
- **Performance:** respostas em cache `<= 50 ms` em ambiente local.

---

## 10. Riscos, dependências e premissas

- **Política de User-Agent e rate limit da SEC (`data.sec.gov` / `sec.gov`):** bloqueia requisições sem `User-Agent` identificável e limita rajadas (>10 req/s); mitigado com header oficial fixo, caches de `24h` (tickers) e `3600s` (empresa) e cap rígido de `8 MB` no `companyfacts`.
- **Oscilação de CISA KEV e RPCs públicos gratuitos:** mitigado mantendo a última cópia válida de `cisa_kev_cache.json` em disco e múltiplos RPCs de fallback + degradação graciosa entre RPC e DeFiLlama.
- **Premissa de compatibilidade:** todos os 3 provedores (SEC EDGAR, OSV.dev + CISA KEV, RPCs públicos + DeFiLlama) são abertos e não exigem cadastro ou chave paga.

---

## 11. Questões em aberto

- Nenhuma questão em aberto; todas as decisões foram alinhadas na etapa de Grill-me. Aguardando aprovação deste PRD e da change OpenSpec antes da implementação.
