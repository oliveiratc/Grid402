# PRD: Fase 1 — Liquidez Global (Dual-Facilitator, CDP Bazaar, Solana) e Blindagem Jurídica (Sanções Oficiais e Extract)

**Status:** Aprovado
**Data:** 2026-11-26
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema

A auditoria de mercado da economia agêntica x402 (2025–2026) combinada com a inspeção técnica do código de produção do Grid402 (`https://grid402.online`) identificou quatro gargalos estruturais que travam a captura de receita global de alto volume e expõem a plataforma a riscos jurídicos e reputacionais:

1. **Facilitador único bloqueando a indexação no Coinbase CDP Bazaar (`public_html/facilitator.php` e `public_html/.well-known/x402.json`):**
   - Embora `public_html/facilitator.php` já implemente geração de JWT ES256/Ed25519 para a Coinbase Developer Platform (CDP), o roteamento atual suporta apenas um facilitador global por vez (`https://facilitator.payai.network`).
   - O **Coinbase CDP Bazaar** não possui cadastro manual: ele indexa automaticamente apenas recursos que liquidam pagamentos reais por meio do **CDP Facilitator** (e remove listagens que ficam 30 dias sem liquidação via CDP). Sem roteamento Dual-Facilitator (CDP + PayAI), o Grid402 fica invisível na principal vitrine semântica de agentes da Base/Coinbase.
2. **Solana x402 inativa e manifesto estático dessincronizado (`public_html/.well-known/x402.json`):**
   - A rede Solana já possui suporte interno em `public_html/facilitator.php` atrás da flag `GRID402_SOLANA_X402_ENABLED=false`, porém está desligada por padrão, ausente do arquivo estático `public_html/.well-known/x402.json` e sem um script CLI dedicado de pré-checagem on-chain (saldo SOL + existência da ATA SPL USDC na carteira `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`).
   - No ecossistema x402 em 2026, a Solana chegou a concentrar cerca de 70% do volume diário transacional do protocolo; permanecer fora ou com manifestos estáticos dessincronizados bloqueia a principal fonte de liquidez M2M.
3. **Risco jurídico e reputacional em `/api/v1/ofac` (`public_html/services/ofac.php`):**
   - O endpoint atualmente consulta uma lista estática *hardcoded* de apenas **6 endereços EVM e 7 nomes de entidades**, mas retorna no payload `'lists_checked'` declarando varredura sobre *US Treasury OFAC SDN* e *UN Security Council Consolidated Sanctions List*.
   - Emitir `CLEAR` para uma entidade ou carteira realmente sancionada configura falso negativo grave com potencial de alegação de informação enganosa (*misrepresentation*) e dano reputacional irreversível.
   - Adicionalmente, a inspeção do ambiente PHP CLI local confirmou ausência das extensões `sqlite3` e `pdo_sqlite` (estando disponíveis `XMLReader`, `SimpleXML`, `zlib`, `curl` e `openssl`), exigindo uma arquitetura de índice particionado 100% compatível com o padrão **Zero-DB** do Grid402.
4. **Exposição jurídica e de copyright em `/api/v1/extract` (`public_html/services/extract.php`):**
   - O extrator Web-to-Markdown possui proteção sólida contra SSRF, mas não consulta nem respeita `robots.txt` (RFC 9309), cabeçalhos `X-Robots-Tag` (`noai`, `noimageai`, `noindex`), `Content-Signal` (`ai-input=no`), meta tags anti-crawl/anti-AI nem sinais básicos de paywall/HTTP 401/403.
   - Além disso, grava o conteúdo textual de terceiros em cache em disco por 600s, criando risco desnecessário de armazenamento persistente de obras protegidas por direitos autorais.

Resolver esses quatro pontos antes de lançar novos endpoints (Fase 2) garante base técnica, liquidez multi-chain e blindagem jurídica sólida para escalar o Grid402 globalmente.

---

## 2. Objetivos

1. **Desbloquear descoberta no Coinbase CDP Bazaar e resiliência de liquidação na Base:** operar roteamento Dual-Facilitator (CDP primário na Base quando configurado + fallback automático seguro pré-settle para PayAI; PayAI para Polygon, Arbitrum e Solana) e garantir 100% de conformidade de `extensions.bazaar` v2 validada contra o validador oficial CDP.
2. **Viabilizar ativação segura e dinâmica da rede Solana x402:** tornar `/.well-known/x402.json` dinâmico em função de `GRID402_SOLANA_X402_ENABLED` e entregar script CLI de pré-checagem on-chain (SOL + ATA SPL USDC).
3. **Eliminar falsos negativos e risco reputacional em `/api/v1/ofac` (e `/api/v1/kyb-audit`):** sincronizar diariamente os 4 blocos oficiais públicos completos (**OFAC SDN**, **ONU**, **União Europeia FSFi** e **UK OFSI**) em índice Zero-DB JSON particionado, com triagem multi-ativo (EVM, Solana, BTC e demais formatos presentes no `sdn_advanced.xml`), comportamento *fail-closed* pré-cobrança (`503`) e metadados de integridade (`dataset_sha256`, `freshness_status`).
4. **Blindar juridicamente o `/api/v1/extract`:** operar em modo 100% transiente (0 bytes de texto/Markdown de terceiros persistidos em disco), respeitar estritamente `robots.txt`, `X-Robots-Tag`, `Content-Signal` e meta tags anti-AI, e bloquear URLs protegidas por autenticação ou paywall.
5. **Preservar 100% das invariantes do Grid402:** manter `25/25 UP`, paridade exata de 27 ferramentas MCP (`v2.0.0`), Zero-DB (sem SQLite obrigatório), Zero-PII, carteira canônica `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` e preços atuais sem regressões.

---

## 3. Não objetivos (fora do escopo)

Explicitamente **NÃO** fazem parte desta Fase 1:
- Criação de novos endpoints da Fase 2 (`token-safety`, `sec-company`, `sec-filing`, `vuln-audit`, `prediction-markets`, `chain-telemetry`, `POST /api/v1/batch/*`).
- Recursos planejados para a Fase 3: suporte a Stripe MPP (`WWW-Authenticate: Payment`), assinatura EIP-712 verificável por smart contract on-chain, registro no ERC-8004 e expansão para `/api/v1/global-kyb` (UK Companies House / SAM.gov).
- Alterações na tabela de preços vigente (mantém `0.0200 USDC` / 4 créditos no `/api/v1/ofac`) ou adição/remoção nas 27 ferramentas MCP atuais (`v2.0.0`).
- Introdução obrigatória de `sqlite3`, `pdo_sqlite`, MySQL ou migração de infraestrutura para Cloudflare Workers.
- Execução manual em contas/carteiras externas pelo agente (geração de chaves CDP no portal Coinbase ou envio real de SOL / criação de ATA na carteira Solana pelo operador).

---

## 4. Público-alvo e personas

1. **Agente Autônomo x402 Multi-Chain (Base, Solana, Polygon, Arbitrum):** descobre endpoints via Coinbase CDP Bazaar, PayAI Bazaar, `/.well-known/x402.json` ou MCP; exige schemas exatos sem `$ref` externo, baixa latência e liquidação confiável sem falhas silenciosas.
2. **Fintech / Integrador de Compliance & KYB:** consome `/api/v1/ofac` e `/api/v1/kyb-audit` via x402, Session Pass ou RapidAPI; precisa de cobertura real das listas oficiais (OFAC, ONU, UE, UK OFSI), triagem multi-ativo e trilha auditável (`dataset_sha256`, `last_synced_at`, `freshness_status`) sem risco de receber `CLEAR` falso quando o índice estiver ausente.
3. **Desenvolvedor de Pipeline RAG / Agente Web:** consome `/api/v1/extract` pagando por chamada e espera respostas limpas de páginas realmente públicas, sem riscos legais de copyright ou burla de restrições de crawl.
4. **Operador do Grid402 (Thiago Campos):** administra `.env`, cron jobs na Hostinger e scripts CLI de prontidão Solana, sync de sanções e validação CDP Bazaar.

---

## 5. Histórias de usuário

- **HU01:** Como agente autônomo na rede Base, quero que meus pagamentos EIP-3009/Permit2 sejam liquidados primariamente pelo Coinbase CDP Facilitator (com fallback automático para PayAI se o CDP estiver indisponível antes da liquidação), garantindo que o Grid402 apareça no CDP Bazaar sem risco de dupla cobrança.
- **HU02:** Como operador do Grid402, quero validar via CLI se minha carteira Solana possui saldo SOL e ATA SPL USDC antes de ligar `GRID402_SOLANA_X402_ENABLED=true`, e quero que `/.well-known/x402.json` passe a anunciar Solana automaticamente assim que a flag for ativada.
- **HU03:** Como integrador de compliance, quero que `/api/v1/ofac` consulte os cadastros oficiais completos de OFAC SDN, ONU, União Europeia e UK OFSI para endereços EVM, Solana, BTC e nomes de entidades, informando o hash SHA-256 e a data da última sincronização no `_meta`.
- **HU04:** Como integrador pagando por chamada em `/api/v1/ofac` ou `/api/v1/kyb-audit`, quero receber HTTP `503` **antes** de qualquer débito de Session Pass ou `/settle` x402 caso o índice oficial nunca tenha sido sincronizado, e um aviso claro (`freshness_status: "STALE"`) se o índice tiver mais de 72 horas.
- **HU05:** Como desenvolvedor avaliando o Grid402 em modo `X-Payment: demo` sem índice local sincronizado, quero testar o endpoint com um subset de demonstração claramente rotulado como `demo_subset` para não confundir ambiente de teste com base oficial completa.
- **HU06:** Como operador responsável pela conformidade jurídica, quero que `/api/v1/extract` respeite `robots.txt`, `X-Robots-Tag` (`noai`, `noimageai`, `noindex`, `none`), `Content-Signal`, meta tags anti-AI e sinais de paywall/401/403, e jamais grave texto de páginas de terceiros em disco.
- **HU07:** Como operador preparando a listagem no Coinbase CDP Bazaar, quero rodar um script CLI único que valide offline e contra o endpoint público `/v2/x402/validate` da Coinbase todos os endpoints pagos do Grid402.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Roteamento Dual-Facilitator (`Coinbase CDP` + `PayAI`) com fallback seguro pré-settle:** configurar roteamento por rede em `public_html/facilitator.php`. Na Base Mainnet (`eip155:8453`), usar Coinbase CDP (`/v2/x402/verify` e `/v2/x402/settle` com JWT ES256/Ed25519) como facilitador primário sempre que `GRID402_CDP_API_KEY_ID` e `GRID402_CDP_API_KEY_SECRET` estiverem configurados; caso ocorra falha de infraestrutura, quota (`401`/`403`/`408`/`429`/`5xx`) ou timeout na seleção ou durante `/verify` (pré-`/settle`), executar fallback automático para PayAI (`https://facilitator.payai.network`). Para Polygon (`eip155:137`), Arbitrum (`eip155:42161`) e Solana (`solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`), rotear diretamente para PayAI (respeitando overrides explícitos de homologação/testnet como Base Sepolia). **Invariante anti-dupla-cobrança:** se ocorrer erro ambíguo ou timeout durante `/settle`, o nonce permanece em estado `pending` e **nunca** é reenviado ao segundo facilitador. | Must |
| RF02 | **Conformidade estrita de `extensions.bazaar` v2 (Coinbase CDP Bazaar):** assegurar que todos os desafios `402` e payloads normalizados para o facilitador emitam `resource.url` HTTPS absoluto, descrição estritamente técnica com `<= 500` caracteres (sem texto imperativo ou padrões suspeitos de prompt-injection) e `inputSchema` / `outputSchema` completamente *inlined*, sem qualquer referência externa `$ref` ou `$id`. | Must |
| RF03 | **Manifesto dinâmico `/.well-known/x402.json` e `/.well-known/x402`:** servir `/.well-known/x402.json` e `/.well-known/x402` via handler PHP roteado pelo `public_html/.htaccess` (mantendo headers CORS e acesso público equivalentes ao manifesto estático), refletindo dinamicamente o estado de `GRID402_SOLANA_X402_ENABLED` (incluindo ou omitindo `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`, carteira `GRID402_SOLANA_WALLET_ADDRESS`, mint USDC e `feePayer`) e os facilitadores ativos por rede. | Must |
| RF04 | **Script CLI de pré-checagem on-chain Solana (`scripts/check_solana_readiness.php`):** implementar script executável em CLI que consulta RPCs públicos saudáveis da Solana Mainnet (priorizando `GRID402_SOLANA_RPC_URL` com lista de fallback sem nós 403), verifica saldo nativo SOL (`> 0` lamports) e existência da ATA SPL USDC (`GRID402_SOLANA_USDC_MINT`) para `GRID402_SOLANA_WALLET_ADDRESS`, exibindo relatório humano e modo `--json` opcional com exit code `0` quando pronto para produção e `1` caso contrário. | Must |
| RF05 | **Sincronizador diário dos 4 blocos oficiais de sanções (`scripts/sync_sanctions_index.php`):** baixar e processar via streaming (`XMLReader` / leitura incremental sem carregar árvores DOM gigantes em RAM e sem exigir SQLite) as 4 fontes públicas oficiais: (1) US Treasury OFAC SDN (`sdn_advanced.xml`, extraindo todos os endereços cripto multi-ativo e nomes/aliases), (2) UN Security Council Consolidated List (`consolidated.xml`), (3) EU Financial Sanctions (`FSFi`), (4) UK OFSI Consolidated List. Gravar índice Zero-DB JSON particionado em `GRID402_PRIVATE_DIR/sanctions_index/` (mapa de endereços cripto normalizados, shards de entidades por prefixo/token e `manifest.json` com `dataset_sha256`, `synced_at`, contagens por lista e licenças). A atualização deve ocorrer em diretório *staging* com validação de integridade e troca atômica preservando o índice anterior em caso de falha de download ou parsing. Suportar execução via CLI/cron e modo `--from-dir` para testes/fixtures offline. | Must |
| RF06 | **Triagem completa multi-ativo em `/api/v1/ofac` (`public_html/services/ofac.php`):** substituir a base estática hardcoded pelo índice particionado de RF05. Aceitar no parâmetro `target` (e aliases `address`/`entity`) endereços EVM (`0x...`), Solana (Base58), Bitcoin (`1...`, `3...`, `bc1...`) e demais ativos cripto presentes no `sdn_advanced.xml`, além de nomes de entidades (com normalização case-insensitive, remoção de acentos/pontuação e busca em aliases). Manter telemetria on-chain Base RPC quando o alvo for endereço EVM, manter preço atual (`0.0200 USDC` / 4 créditos) e preservar compatibilidade de campos já consumidos por clientes e MCP. | Must |
| RF07 | **Governança Fail-Closed, Freshness, Modo Demo e Herança em `/api/v1/kyb-audit`:** (a) em chamadas pagas (x402, Session Pass, RapidAPI), se o índice oficial nunca foi sincronizado, retornar HTTP `503 Service Unavailable` **antes** de debitar Session Pass ou executar `/settle` x402 (zero cobrança ao cliente); (b) quando `synced_at` ultrapassar 72 horas, manter atendimento retornando `freshness_status: "STALE"` e `stale_dataset_warning: true` (`"FRESH"` quando `<= 72h`); (c) incluir `dataset_metadata` (`dataset_sha256`, `last_synced_at`, `lists_counts`, `freshness_status`, `dataset_mode`) e `legal_disclaimer` explícito de triagem técnica *best-effort*; (d) no modo `X-Payment: demo`, caso o índice oficial não exista localmente, responder com subset de demonstração claramente rotulado com `dataset_mode: "demo_subset"`; (e) atualizar `public_html/services/kyb_audit.php` para propagar o erro/indisponibilidade de sanções (nunca mascarar indisponibilidade como `CLEAR`) e aceitar alvos cripto multi-ativo. | Must |
| RF08 | **Respeito a `robots.txt` e diretivas anti-AI em `/api/v1/extract`:** antes de buscar o recurso de destino, buscar e avaliar `/robots.txt` da origem sob o mesmo guarda anti-SSRF (timeout `<= 2s`, cache exclusivo da regra `robots.txt` por host por até 3600s, avaliando os user-agents `Grid402-Extract-Bot` e `*`), bloqueando com HTTP `403` (`RobotsDisallowed`) se o path estiver proibido. Após receber cabeçalhos e corpo, inspecionar `X-Robots-Tag` (`noai`, `noimageai`, `noindex`, `none`), `Content-Signal` (`ai-input=no` ou `ai-train=no`) e tags HTML `<meta name="robots|googlebot|..." content="...">` contendo `noai`/`noimageai`/`noindex`/`none`, bloqueando com HTTP `403` (`DirectiveDisallowed`) antes de retornar conteúdo. | Must |
| RF09 | **Bloqueio de paywalls/autenticação e operação 100% transiente em `/api/v1/extract`:** rejeitar URLs cujo servidor de origem retorne HTTP `401`, `402`, `403` ou `407`, ou sinais estruturais claros de paywall (ex.: JSON-LD `"isAccessibleForFree": false` ou redirecionamento para login/assinatura), retornando erro estruturado sem liquidar cobrança x402 quando bloqueado pré-`/settle`. **Remover integralmente** o cache em disco do texto/Markdown extraído em `public_html/services/extract.php` (zero persistência em `private_data/` de conteúdo de terceiros), adicionando `transient_only: true` e `legal_disclaimer` na resposta. | Must |
| RF10 | **Script CLI de validação Coinbase CDP Bazaar (`scripts/validate_cdp_bazaar.php`):** inspecionar todos os endpoints pagos do catálogo quanto às regras do CDP Bazaar v2 (URL absoluta, descrição `<= 500` chars, ausência de `$ref`/`$id` externos, schemas de entrada/saída válidos, ausência de linguagem de prompt-injection) e suportar flag `--live` para validar cada endpoint contra `POST https://api.cdp.coinbase.com/platform/v2/x402/validate` (sem necessidade de API key). | Must |
| RF11 | **Atualização de `.env.example`, `/status` e `/trust`:** documentar as novas variáveis de ambiente em `.env.example` (roteamento Dual-Facilitator, `GRID402_SOLANA_RPC_URL`, limiar de freshness de sanções) e expor em `/status` e `/trust` o resumo público de integridade do índice de sanções (`last_synced_at`, `freshness_status`, contagem de registros, licenças) e a política de operação transiente + `robots.txt` do `/extract`. | Should |
| RF12 | **Preservação de invariantes e cobertura integral de testes:** manter `25/25 UP`, paridade exata de 27 ferramentas MCP (`v2.0.0`), ausência do caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md` e `smithery.yaml`, carteira canônica `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`, e implementar bateria de testes automatizados determinísticos (offline com mocks/fixtures) cobrindo todos os requisitos `Must` (`RF01` a `RF10`). | Must |

---

## 7. Requisitos não funcionais

- **Performance:**
  - Lookup de sanções em `/api/v1/ofac` usando índice JSON particionado em shards deve responder em `<= 30 ms` (excluindo latência de rede Base RPC quando aplicável).
  - Consumo de Session Pass permanece `<= 15 ms`.
  - Consulta de `robots.txt` em `/api/v1/extract` deve ter timeout de conexão/leitura `<= 2 s` e usar cache exclusivo de diretivas por host para não penalizar chamadas repetidas ao mesmo domínio.
  - Parsing dos arquivos oficiais em `scripts/sync_sanctions_index.php` deve usar `XMLReader` em streaming mantendo pico de memória `< 64 MB` mesmo em hospedagem compartilhada (Hostinger).
- **Compatibilidade:**
  - PHP 8.1+ (LiteSpeed Hostinger e PHP CLI Windows/Linux) sem exigir `sqlite3`, `pdo_sqlite` ou `mbstring` (usando `preg_match('//u', ...)` e transliteração/normalização compatível).
- **Concorrência e Armazenamento Zero-DB:**
  - Toda mutação concorrente (nonces, passes, métricas) deve seguir estritamente `fopen(..., 'c+')` + `flock(LOCK_EX)` + `grid402_read_locked_json()`, sem segurar dois locks simultaneamente.
  - Escrita do índice de sanções via staging + troca atômica para evitar leitura parcial durante execução do cron.
- **Segurança e Zero-PII:**
  - Nunca logar ou expor `GRID402_CDP_API_KEY_SECRET`, JWTs de facilitador ou dados pessoais.
  - Aplicar estritamente a mesma validação anti-SSRF (bloqueio de RFC 1918/4193, loopback, cloud metadata e DNS rebinding via `CURLOPT_RESOLVE`) tanto no fetch de `robots.txt` quanto no fetch da página em `/api/v1/extract`.
  - Preservar assinatura criptográfica por referência `grid402_sign_data(array &$data, ...)` (`_oracle_proof` + `_meta`).
- **Blindagem jurídica:**
  - Utilizar exclusivamente fontes públicas oficiais com licenças compatíveis com uso comercial (US Public Domain `17 U.S.C. § 105`, UN Open Data, EU Open Data, UK OGL v3.0), declarando licença, autoridade fonte e disclaimer técnico em `_meta` / `legal_disclaimer`.
  - Garantir **zero armazenamento em disco** de texto extraído de terceiros pelo `/api/v1/extract`.

---

## 8. Fluxos e UX

### Fluxo 1 — Liquidação Dual-Facilitator (`Coinbase CDP` + `PayAI`)
1. Cliente envia requisição com cabeçalho `PAYMENT-SIGNATURE` / `X-Payment` (x402 v1 ou v2).
2. `public_html/facilitator.php` valida localmente formato, rede, destinatário (`payTo`), valor mínimo (`requiredRawUnits`), janela temporal e reserva atomicamente o nonce (`reserved`).
3. **Seleção do facilitador:**
   - Se a rede for Base (`eip155:8453`) **e** credenciais CDP estiverem configuradas → seleciona **Coinbase CDP** como primário (PayAI como fallback habilitado).
   - Se a rede for Polygon, Arbitrum ou Solana (ou CDP não configurado) → seleciona **PayAI**.
4. **Etapa `/verify` (pré-serviço):**
   - Chama `/verify` no facilitador primário.
   - Se falhar por erro de infraestrutura/quota/auth (`401`/`403`/`408`/`429`/`5xx`/timeout) **e** houver fallback elegível (PayAI na Base), tenta `/verify` no fallback.
   - Se a assinatura for rejeitada por mérito do cliente (`isValid: false`), libera nonce e retorna `402`.
5. **Execução do serviço antes do `/settle` (Fail-Closed):**
   - O endpoint executa a lógica do serviço (incluindo checagem de disponibilidade do índice OFAC ou bloqueio `robots.txt`/paywall no `/extract`).
   - Se o serviço retornar erro pré-liquidação (`503` ou `403`/`422`), `grid402_abort_verified_exact_payment()` libera o nonce e **não** executa `/settle`.
6. **Etapa `/settle`:**
   - Executa `/settle` no mesmo facilitador que aprovou o `/verify`.
   - Em sucesso, marca nonce como `settled`, grava `txLockKey` em `used_txs.json` e devolve HTTP `200` com `PAYMENT-RESPONSE` e `_oracle_proof`.
   - **Em timeout ou erro HTTP 5xx/ambíguo no `/settle`:** mantém nonce como `pending`, retorna `503` orientando não reenviar e **nunca** faz fallback para o outro facilitador.

### Fluxo 2 — Sincronização e Consulta `/api/v1/ofac` e `/api/v1/kyb-audit`
1. **Sync diário (cron/CLI):** `scripts/sync_sanctions_index.php` baixa os 4 feeds oficiais, processa em streaming, calcula `dataset_sha256`, grava em diretório staging e promove atomicamente para `GRID402_PRIVATE_DIR/sanctions_index/`. Se algum feed falhar, aborta a promoção mantendo o índice anterior intacto e registra erro estruturado.
2. **Consulta em produção (x402 / Session Pass / RapidAPI):**
   - Se `manifest.json` ou os shards estiverem ausentes/corrompidos → retorna `503` antes de debitar Session Pass ou liquidar `/settle`.
   - Se `synced_at` existir e `<= 72h` → consulta multi-ativo (EVM/Solana/BTC/outros ou nome/aliases) + telemetria Base RPC (se EVM) → retorna `200` com `freshness_status: "FRESH"`, `dataset_sha256`, contagens e `legal_disclaimer`.
   - Se `synced_at > 72h` → retorna `200` com `freshness_status: "STALE"` e `stale_dataset_warning: true`.
3. **Consulta Sandbox (`X-Payment: demo`):** se não houver índice sincronizado localmente, executa contra o subset de demonstração claramente identificado por `dataset_mode: "demo_subset"`.
4. **Herança `/api/v1/kyb-audit`:** repassa alvo multi-ativo para `/api/v1/ofac`; se sanções retornarem erro `503`, propaga erro `503` pré-cobrança sem jamais retornar `CLEAR`.

### Fluxo 3 — Extração Segura e Transiente `/api/v1/extract`
1. Valida URL de entrada e resolve DNS contra SSRF (bloqueio de IPs privados/reservados/loopback/metadata e portas fora de 80/443).
2. Busca regras `robots.txt` do host (usando cache leve exclusivo de regras `robots.txt` em disco por até 3600s, sem armazenar páginas; se bloqueado por `Disallow`, retorna `403 RobotsDisallowed` antes de cobrar).
3. Executa fetch HTTP da URL (até 3 redirects revalidados contra SSRF, limite 1 MB).
4. Se origem retornar `401`/`402`/`403`/`407`, cabeçalho `X-Robots-Tag` restritivo (`noai`/`noimageai`/`noindex`/`none`), `Content-Signal` (`ai-input=no`/`ai-train=no`), meta tag restritiva ou sinal claro de paywall → bloqueia sem gravar texto e sem liquidar `/settle`.
5. Converte HTML em Markdown em memória, **sem gravar nada em disco**, anexa `_meta`, `transient_only: true`, `legal_disclaimer` e assina `_oracle_proof`.

### Fluxo 4 — Prontidão e Ativação Solana
1. Operador executa `php scripts/check_solana_readiness.php` para confirmar saldo SOL e ATA SPL USDC na carteira `CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`.
2. Quando `exit code = 0`, operador altera `GRID402_SOLANA_X402_ENABLED=true` no `.env`.
3. Automaticamente, tanto os desafios `402` quanto `/.well-known/x402.json` passam a incluir a rede Solana e o roteamento PayAI gasless passa a aceitar pagamentos SPL USDC.

---

## 9. Métricas de sucesso

| KPI / Critério | Meta na Entrega da Fase 1 |
|---|---|
| Aprovação de schemas CDP Bazaar (`scripts/validate_cdp_bazaar.php`) | **100%** dos endpoints pagos aprovados offline e compatíveis com `/v2/x402/validate` |
| Cobertura de listas oficiais em `/api/v1/ofac` | **4/4 blocos oficiais** (OFAC SDN, ONU, UE FSFi, UK OFSI) indexados com `dataset_sha256` verificável |
| Segurança anti-dupla-cobrança no Dual-Facilitator | **0** casos de reenvio para segundo facilitador após erro ambíguo de `/settle` em testes |
| Persistência de conteúdo de terceiros em `/api/v1/extract` | **0 bytes** de texto/Markdown de URLs externas salvos em `private_data/` |
| Respeito a diretivas no `/api/v1/extract` | **100%** de bloqueio nos cenários de `robots.txt`, `X-Robots-Tag: noai`, `Content-Signal`, `<meta noai>` e HTTP `401`/`403`/paywall |
| Integridade da suíte e invariantes | **100%** dos testes existentes + novos testes Fase 1 + `openspec validate --all --strict` passando; **27/27** tools MCP e **25/25** serviços UP preservados |

---

## 10. Riscos, dependências e premissas

| Risco / Dependência | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Erro ambíguo no `/settle` causar dupla cobrança ao tentar fallback | Baixa | Crítico | Regra estrita em `RF01`: fallback permitido **somente** até `/verify`; qualquer timeout/5xx no `/settle` mantém nonce `pending` e aborta retry automático. |
| Mudança de layout ou indisponibilidade temporária nos XMLs/CSVs oficiais (OFAC, ONU, UE, UK) | Média | Alto | Parse defensivo em `XMLReader` streaming + staging atômico: se 1 bloco falhar ou vier vazio/inválido, o índice anterior íntegro é preservado e o cron alerta sem corromper produção. |
| Consumo excessivo de memória/tempo ao indexar dezenas de milhares de entidades em JSON | Média | Alto | Particionamento em shards leves por prefixo/hash de endereço e tokens de nome, mantendo apenas o shard necessário carregado por requisição (`<= 30 ms`). |
| Latência adicional ao consultar `robots.txt` no `/api/v1/extract` | Média | Médio | Cache local leve **somente** das diretivas `robots.txt` por host (TTL 3600s) e timeout curto (`<= 2 s`); falha de rede no `robots.txt` tratada de forma previsível (5xx/404 tratado conforme RFC 9309). |
| Dependência externa de credenciais CDP e funding da carteira Solana pelo operador | Alta | Médio | Todo o código funciona em modo seguro mesmo antes de o operador inserir `GRID402_CDP_API_KEY_*` ou ligar `GRID402_SOLANA_X402_ENABLED=true`, com scripts CLI de verificação pronta (`check_solana_readiness.php` e `validate_cdp_bazaar.php`). |

---

## 11. Questões em aberto (ações operacionais pós-implementação)

- [ ] Inserir `GRID402_CDP_API_KEY_ID` e `GRID402_CDP_API_KEY_SECRET` no `.env` de produção na Hostinger após a entrega do código e rodar `php scripts/validate_cdp_bazaar.php --live` + 1 liquidação real na Base para confirmar indexação no Coinbase CDP Bazaar.
- [ ] Enviar pequena fração de SOL e criar a ATA SPL USDC na carteira Solana (`CSgJyQTwehb3vF2vbsDMdUuBPdcuvx1vdEEhGvVCCF8C`), validar com `php scripts/check_solana_readiness.php` e então ativar `GRID402_SOLANA_X402_ENABLED=true`.
- [ ] Configurar cron job diário no hPanel da Hostinger executando `php scripts/sync_sanctions_index.php` (após a implementação e primeiro sync manual no deploy).
