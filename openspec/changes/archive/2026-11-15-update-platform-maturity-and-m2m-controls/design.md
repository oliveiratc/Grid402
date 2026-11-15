# Design Técnico: Maturidade Enterprise, Consistência Multi-Canal e Controles M2M

## 1. Visão Arquitetural
O objetivo deste design é eliminar qualquer divergência entre código, interface web, especificações para LLMs e pacotes distribuídos (Zero-Drift), ao mesmo tempo em que adiciona camadas de governança, observabilidade histórica e segurança financeira M2M mantendo a filosofia **Zero-DB** (`grid402_atomic_write` + `flock`).

```mermaid
flowchart TD
    Agent["Agente de IA / Crawler LLM"] -->|GET /robots.txt, /llms.txt, /openapi.json, /trust| Edge["Porteiro HTTP (index.php)"]
    Agent -->|GET /api/v1/* + X-Payment + Idempotency-Key| Edge
    Edge -->|1. Verifica Idempotency-Key (300s TTL)| Idemp["idempotency_cache.json"]
    Edge -->|2. Valida Session Pass + Spend Controls| PassEngine["x402.php / passes.json"]
    Edge -->|3. Executa Servico + Anexa _meta e _oracle_proof| Upstream["22 Servicos + Cache Local"]
    Collector["collector.php / Status Engine"] -->|Consolida 22/22 UP + Serie 30d| Uptime["summary_free.json + uptime_history.json"]
```

## 2. Decisões Técnicas por Fase

### 2.1. Fonte Única da Verdade para Carteira e Matemática de Créditos (Fase 1)
- **Carteira Recebedora:** Todas as exibições HTML e JSON em `public_html/index.php` usarão `GRID402_WALLET_ADDRESS` (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`) e uma função auxiliar/formatação truncada dinâmica (`0xa592...3f90`), eliminando strings literais divergentes como `0xa31c...356c`.
- **Matemática de Créditos (`1 crédito = 0.0050 USDC`):**
  - **Starter (2 USDC):** `400 créditos` (validade 30 dias).
  - **Pro (5 USDC):** `1.000 créditos` (validade 30 dias).
  - **Fleet (20 USDC):** `5.000 + 1.000 bônus = 6.000 créditos` (validade 60 dias).
  - **Custo em Créditos por Endpoint:**
    - Padrão (`0.0050 USDC`): `1 crédito`
    - Simples Nacional/MEI (`0.0100 USDC`): `2 créditos`
    - Corporativo (`0.0200 USDC` — CNPJ, LEI, OFAC): `4 créditos`
    - Compliance Score 360 (`0.0300 USDC`): `6 créditos`
  - **Retrocompatibilidade de Passes Existentes:** Caso um passe legado no arquivo `passes.json` tenha sido emitido na escala antiga (`>= 4000` créditos para `tier === 'starter'` sem flag `credit_scale_v2`), o motor reconhece ou preserva a proporção para nunca prejudicar saldos ativos. Novos passes gravam `"credit_scale": "v2_0005"` com `400`, `1000` ou `6000` créditos.

### 2.2. Cobertura Completa `22/22 UP` e Sincronização MCP (Fase 1)
- **Status Operacional (`22/22`):** O catálogo de serviços em `config.php` / `index.php` terá verificação ativa e fallback de saúde para todos os 22 serviços (`status`, `verify`, `pass`, `forex`, `crypto`, `global-markets`, `market-snapshot`, `indicators`, `holidays`, `workdays`, `dns`, `ssl`, `asn`, `domain-audit`, `cep`, `cnpj`, `simples`, `sanctions`, `compliance`, `kyb`, `lei`, `ofac`, `notarize`), garantindo que `/status` e `/catalog` reflitam o estado operacional real de cada rota.
- **Servidor MCP (`mcp/tools.mjs`):** Expandiremos `MCP_TOOLS_DEFINITIONS` para cobrir todas as ferramentas do catálogo (incluindo `simples`, `sanctions`, `compliance`, `kyb`, `lei`, `ofac`, `domain_audit`, `global_markets`, `market_snapshot`, `notarize`, `pass_balance`), corrigindo as descrições e mantendo `test_mcp.mjs` validando cada ferramenta.

### 2.3. Histórico de Uptime (30 dias) e Metadados `_meta` (Fase 2)
- **`uptime_history.json`:** A cada execução do coletor ou verificação diária, o sistema registra um bucket diário (`YYYY-MM-DD`) com `checks_total`, `checks_ok`, `uptime_pct` e `avg_latency_ms`, mantendo janela móvel de 90 dias (exibindo os últimos 30 dias em `/status` e `/trust`).
- **Bloco `_meta` de Proveniência:** Todas as respostas de dados de endpoints pagos incluirão:
  ```json
  "_meta": {
    "upstream_source": "Receita Federal / BrasilAPI / Cache Oficial",
    "fetched_at": "2026-11-15T06:50:00Z",
    "cache_ttl_sec": 86400,
    "operator": "Grid402 Network Intelligence (grid402.online)"
  }
  ```
  O bloco `_meta` é inserido **antes** da assinatura `_oracle_proof`, garantindo que a origem e o timestamp de frescor também sejam criptograficamente assinados pelo oráculo ECDSA secp256k1.

### 2.4. Idempotência (`Idempotency-Key`) e Agent Spend Controls (Fase 3)
- **Idempotência (TTL 300s):**
  - Se o cliente enviar o cabeçalho `Idempotency-Key: <chave>` (de 8 a 128 caracteres alfanuméricos/hífens), o servidor calcula `sha256($key . '|' . $requestUri)`.
  - Antes de cobrar o Session Pass ou consumir um pagamento, verifica em `idempotency_cache.json` se existe entrada válida com `expires_at > time()`.
  - Se existir, retorna imediatamente o JSON salvo com status HTTP 200 e cabeçalho `X-Idempotent-Replay: true`, **sem debitar créditos novamente**.
  - Após uma resposta HTTP 200 paga bem-sucedida, grava o resultado no `idempotency_cache.json` (com expurgo automático de chaves expiradas).
- **Endpoint Gratuito `GET /api/v1/pass/balance` e Spend Controls:**
  - Recebe o token no cabeçalho `X-Payment: pass_g402_...` (ou `?token=pass_g402_...`).
  - Retorna gratuitamente: `status`, `tier`, `credits_total`, `credits_remaining`, `credits_used_today`, `daily_credit_limit`, `allowed_endpoints`, `expires_at`.
  - Na emissão ou configuração do passe (ou via endpoint `POST /api/v1/pass/limits` autenticado pelo próprio token), o agente/operador pode definir `daily_credit_limit` (ex.: máximo de 100 créditos/dia) e `allowed_endpoints` (ex.: `["cnpj", "compliance"]`). Se o consumo diário atingir o teto, o servidor responde com `HTTP 429 Too Many Requests` (`error: "PassDailyLimitExceeded"`), protegendo o orçamento do agente.
