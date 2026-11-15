---
name: grid402
description: >-
  Conhecimento arquitetural completo, regras invariantes, protocolo HTTP x402 v2
  (Base Mainnet USDC), Session Pass, Spend Controls, Idempotency-Key, assinatura
  de oráculo ECDSA secp256k1 (_oracle_proof + _meta), armazenamento Zero-DB,
  servidor MCP (24 tools), SDKs e fluxo de testes/deploy da plataforma Grid402
  (https://grid402.online). Use sempre que criar, manter, auditar, testar ou
  integrar endpoints, micro-serviços, MCP, SDKs ou páginas do projeto Grid402.
---

# Skill Oficial: Arquitetura e Engenharia Grid402

Esta skill consolida todo o conhecimento técnico, regras de negócio, invariantes financeiras/criptográficas e armadilhas de manutenção da plataforma **Grid402 // Global M2M Open Data & Network Utilities Hub** (`https://grid402.online`).

---

## 1. Identidade, Rede e Invariantes Globais

| Parâmetro | Valor Oficial / Canônico (NUNCA divergir) |
|---|---|
| **Domínio Canônico** | `https://grid402.online` |
| **Operador Institucional** | `Grid402 Network Intelligence` (Responsável Técnico: `Thiago Campos`) |
| **Contato / Security** | `contato@grid402.online` (`/.well-known/security.txt` RFC 9116) |
| **Rede Blockchain** | Base Mainnet (`eip155:8453`, Chain ID `8453`) |
| **Contrato Oficial USDC (Base)** | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` (6 casas decimais: `1 USDC = 1.000.000 raw units`) |
| **Carteira Recebedora / Oráculo** | `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` (`GRID402_WALLET_ADDRESS` / `GRID402_ORACLE_WALLET`) |
| **Facilitador x402 v2 Gasless** | `https://facilitator.payai.network` (esquema `exact` EIP-3009 `TransferWithAuthorization`) |
| **SLA Alvo** | `99.5%` (*best-effort* sobre fontes públicas oficiais, monitorado em janela móvel de 30 dias `uptime_30d`) |

> **REGRA DE OURO #1 (Carteira Única):** NUNCA utilize o endereço legado `0xa31ca8fbabbdac7ab34fb045ae2fc2019ba9356c`. Toda referência em código, UI, JSON, `llms.txt`, `openapi.json` e testes deve apontar exclusivamente para `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`.

---

## 2. Matemática Oficial do Session Pass e Tarifação M2M

### Escala Oficial (`v2_5000`)
- **Unidade Base:** `1 crédito = 0.0050 USDC = 5.000 raw units` (= 1 chamada em endpoint padrão).
- **Pacotes de Emissão (`/api/v1/pass` via Web3 USDC ou Mercado Pago Pix):**
  - **Starter:** `2.0000 USDC` (`2.000.000 raw units` / `R$ 12,00`) $\rightarrow$ **400 créditos** (validade 30 dias)
  - **Pro:** `5.0000 USDC` (`5.000.000 raw units` / `R$ 29,00`) $\rightarrow$ **1.000 créditos** (validade 30 dias)
  - **Fleet:** `20.0000 USDC` (`20.000.000 raw units` / `R$ 115,00`) $\rightarrow$ **6.000 créditos** (`5.000 + 1.000 de bônus`, validade 60 dias)
- **Retrocompatibilidade (`grid402_normalize_pass_entry`):** Passes antigos gravados na escala legada `100x` (sem `'credit_scale' => 'v2_5000'`) são normalizados automaticamente em tempo de leitura dividindo `credits_total` e `credits_remaining` por `100`.

### Tabela de Custo por Endpoint (22 Micro-Serviços no Catálogo)
Consulte a tabela completa com fontes *upstream* e TTL de cache em [`references/catalog-and-pricing.md`](./references/catalog-and-pricing.md):
- **1 crédito (`0.0050 USDC` / `5000 raw`):** `forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `indicators`, `holidays`, `workdays`, `notarize`, `global-markets`
- **2 créditos (`0.0100 USDC` / `10000 raw`):** `sanctions`, `simples`
- **3 créditos (`0.0150 USDC` / `15000 raw`):** `domain-audit`, `market-snapshot`
- **4 créditos (`0.0200 USDC` / `20000 raw`):** `cnpj`, `lei`, `ofac`, `kyb-audit`
- **6 créditos (`0.0300 USDC` / `30000 raw`):** `compliance` (Compliance Score 360)
- **Gratuitos (`0 USDC`):** `/status` (`/api/v1/summary`), `/api/v1/verify`, `/api/v1/pass/balance` (ou `/api/v1/pass?status=...`), `/catalog`, `/trust`

> **REGRA DE OURO #2 (`22/22 UP`):** Sempre que adicionar ou alterar um serviço em `grid402_get_utility_services()` (`public_html/config.php`), atualize também:
> 1. `public_html/collector.php` (para popular `summary_free.json` com `status: "UP"`, `upstream_source`, `cache_ttl_sec`, `latency_ms`)
> 2. `public_html/index.php` (roteamento `$apiParam`, preços `$servicePrices`, teasers HTTP 402 e enriquecimento de `/status`)
> 3. `public_html/catalog.php` e `public_html/trust.php`
> 4. `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs` e `public_html/mcp.php`
> 5. `public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt` e `README.md`

---

## 3. Fluxo Financeiro x402, Idempotência e Spend Controls

### Ordem de Execução no Porteiro (`public_html/index.php`)
Para qualquer chamada a um endpoint pago (`/api/v1/<service>`):
1. **Rotas Gratuitas:** `/api/v1/verify`, `/api/v1/pass?status=...`, `GET /api/v1/pass/balance` e `POST /api/v1/pass/limits` executam sem cobrança.
2. **Desafio HTTP 402 + Teaser Gratuito:** Se não houver cabeçalho `X-Payment` nem `PAYMENT-SIGNATURE`, retorna HTTP 402 com `accepts` (x402 v2), instruções de pagamento e bloco `preview` (amostra real truncada) + `locked_fields`.
3. **Verificação de Idempotência (`Idempotency-Key`):**
   - **ANTES** de debitar o Session Pass ou liquidar transação on-chain, `grid402_idempotency_check($idempotencyKey, $apiParam, $_GET)` verifica se há resposta assinada em cache (TTL de 300s em `private_data/idempotency_cache.json`).
   - Em caso de *cache hit*, retorna imediatamente o payload original com cabeçalho `X-Idempotent-Replay: true` e **zero débito adicional**.
4. **Validação de Pagamento (`grid402_validate_payment` / `grid402_validate_exact_payment`):**
   - **Modo Sandbox (`X-Payment: demo`):** Limitado por IP (`HMAC-SHA256` em `sandbox_rates.json`, `5 req/hora` por IP via `GRID402_SANDBOX_RATE_LIMIT = 5`). Idioma primário da UI pública: Inglês (`en`).
   - **Session Pass (`X-Payment: pass_g402_<id>_<hmac>`):** Valida HMAC-SHA256 stateless e executa `grid402_consume_session_pass($token, $requiredRawUnits, $endpointId)` sob `flock(LOCK_EX)`:
     - Se `$endpointId` não estiver em `allowed_endpoints` (quando configurado): bloqueia com **HTTP 403 `PassEndpointRestricted`**.
     - Se `credits_used_today + $creditsNeeded > daily_credit_limit` (quando configurado): bloqueia com **HTTP 429 `PassDailyLimitExceeded`** e `Retry-After`.
     - Debita créditos atomicamente em `<15ms`.
   - **EIP-3009 Gasless (`PAYMENT-SIGNATURE` base64):** Liquida via facilitador PayAI e previne replay em `used_nonces.json`.
   - **TxHash On-Chain (`0x` + 64 hex):** Verifica receipt JSON-RPC na Base Mainnet (com fallback multi-RPC) e previne replay em `used_txs.json`.
5. **Injeção de Proveniência (`_meta`) e Assinatura de Oráculo (`_oracle_proof`):**
   - Primeiro injeta `_meta` via `$result = grid402_attach_provenance_meta($result, $apiParam)` (`service_id`, `upstream_source`, `source_authority`, `fetched_at`, `cache_status`, `cache_ttl_sec`, `operator`, `oracle_wallet`).
   - Em seguida assina por referência via `grid402_sign_data($result, $isSandbox)` (`public_html/services/oracle_signer.php`), que calcula o `SHA-256` do JSON canônico ordenado recursivamente (`ksort`, incluindo `_meta` e excluindo `_oracle_proof`, `sandbox_info`, `_x402_settlement`) e assina com ECDSA `secp256k1-sha256`.
   - Por fim, se houver `Idempotency-Key`, armazena o payload assinado via `grid402_idempotency_store(...)` e emite `X-Idempotent-Replay: false`.

---

## 4. Arquitetura Zero-DB e Concorrência Segura

O Grid402 opera na Hostinger (LiteSpeed PHP 8.1+) com **Zero Banco de Dados SQL**. Todo o estado reside em `public_html/private_data/*.json` (protegido por `.htaccess` com `Require all denied`).
- **Leitura/Escrita de Cache:** Sempre use `grid402_atomic_write($file, $data)` (escreve em `.tmp.` + `rename()` atômico).
- **Mutação Concorrente (Passes, Nonces, Txs, Idempotência, Rate Limit):** Sempre abra com `fopen($file, 'c+')`, adquira `flock($fp, LOCK_EX)`, faça `ftruncate($fp, 0); rewind($fp); fwrite($fp, ...); fflush($fp); flock($fp, LOCK_UN); fclose($fp);`.
- **Compatibilidade PHP CLI Local vs Produção:** Não dependa da extensão `mbstring` (`mb_check_encoding`) sem fallback; para validar UTF-8 use `preg_match('//u', $raw) === 1`.

---

## 5. Mapa de Arquivos Essenciais

```text
c:\Antigravity\Projetos\Grid402
├── CHANGELOG.md                          # Histórico semântico de versões e tarifas
├── PRD.md                                # Product Requirements Document consolidado
├── README.md                             # Documentação principal (ATENÇÃO: proibido usar '&')
├── smithery.yaml                         # Manifesto Smithery.ai para instalação MCP em 1 clique
├── public_html/                          # DocumentRoot de produção (Hostinger)
│   ├── config.php                        # Constantes, 22 serviços, _meta, uptime_30d, idempotência
│   ├── index.php                         # Porteiro x402, roteador de APIs e Landing Page / Playground
│   ├── x402.php                          # Validador on-chain Base RPC + Replay Protection
│   ├── facilitator.php                   # Liquidação EIP-3009 gasless (x402 v2 exact)
│   ├── collector.php                     # Cron collector (<400ms) -> summary_free.json + uptime_history.json
│   ├── catalog.php                       # Catálogo público HTML/M2M (/catalog) com 22 serviços UP
│   ├── trust.php                         # Trust Center (/trust): governança, SLA 30d, proveniência
│   ├── pass.php                          # Portal Web3/Pix de compra e gestão de Session Pass
│   ├── mcp.php                           # Servidor MCP HTTP Streamable público (/mcp - 24 tools)
│   ├── openapi.json                      # Especificação OpenAPI 3.1.0
│   ├── llms.txt / llms-full.txt          # Manifestos de descoberta para LLMs e agentes
│   ├── robots.txt / robots.php           # Diretivas RFC 9309 permitindo GPTBot, ClaudeBot, OAI-SearchBot
│   ├── .well-known/security.txt          # Política de divulgação de vulnerabilidades RFC 9116
│   └── services/                         # Implementação isolada dos micro-serviços e oracle_signer.php
├── mcp/                                  # Pacote npm oficial grid402-mcp (stdio)
│   ├── index.mjs                         # Servidor MCP stdio (24 ferramentas)
│   ├── tools.mjs                         # Definições MCP_TOOLS_DEFINITIONS (24) e cliente HTTP x402
│   └── test_mcp.mjs                      # Bateria de testes automatizados do MCP
├── scripts/
│   ├── verify_storage_integrity.php      # Verifica UTF-8/JSON e 22/22 UP em private_data/ (+ --backup)
│   └── package_deploy.ps1                # Gera dist/grid402_hostinger_deploy.zip com caminhos POSIX
└── tests/
    ├── test_maturity_and_m2m_controls.php # Suíte de maturidade, passes, Spend Controls, Idempotency e _meta
    ├── test_agentkit_integration.php      # Valida Smithery, AgentKit e ausência de '&' nas docs
    └── test_e2e_full.php                  # Homologação ponta a ponta com servidor HTTP local e Base RPC
```

---

## 6. Regras de Teste e Armadilhas Conhecidas (Checklist Obrigatório)

Antes de concluir qualquer alteração no projeto, verifique estas 5 regras críticas:

1. **Proibição Estrita do Caractere `&` em Documentações:**
   - O teste `tests/test_agentkit_integration.php` falha se encontrar o caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md` ou `smithery.yaml`. Sempre escreva `e` ou `and` (nunca `&`) nesses 4 arquivos!
2. **Assinatura por Referência em `grid402_sign_data`:**
   - `grid402_sign_data(array &$data, bool $isSandbox = false): void` modifica `$data` **por referência** e retorna `void`. Nunca faça `$signed = grid402_sign_data($data)`.
3. **Paridade de 24 Ferramentas MCP:**
   - `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs` e `public_html/mcp.php` devem expor exatamente as mesmas **24 ferramentas** (`grid402_summary` + 22 micro-serviços + `grid402_pass_balance`).
4. **Execução da Bateria de Homologação (PowerShell):**
   ```powershell
   php scripts/verify_storage_integrity.php
   php tests/test_maturity_and_m2m_controls.php
   php tests/test_agentkit_integration.php
   php tests/test_e2e_full.php
   node mcp/test_mcp.mjs
   npx -y @fission-ai/openspec@latest validate --specs --strict
   ```
5. **Empacotamento para Deploy na Hostinger:**
   - Sempre que alterar arquivos em `public_html/`, gere o pacote de deploy atualizado executando:
     ```powershell
     powershell -ExecutionPolicy Bypass -File scripts/package_deploy.ps1
     ```
   - O arquivo final fica em `dist/grid402_hostinger_deploy.zip` (sem incluir `.env` ou dados voláteis de `private_data/*.json`).
