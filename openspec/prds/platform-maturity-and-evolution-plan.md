# PRD: Maturidade Enterprise, Consistência Multi-Canal e Evolução da Infraestrutura M2M (Grid402 v3.0)

**Status:** Em revisão
**Data:** 2026-11-15
**Autor:** Thiago Campos + Agente Antigravity

## 1. Contexto e problema
Auditorias independentes conduzidas por modelos de linguagem e plataformas de avaliação técnica (Claude, ChatGPT e Dola) avaliaram o Grid402 como tecnicamente promissor (nota 8.5/10 em integração com IA e 9.0/10 em potencial de mercado), mas atribuíram uma nota de maturidade global de **6.5/10 ("MVP avançado")**, apontando bloqueadores objetivos para adoção corporativa e crítica:

1. **Inconsistência Crítica de Endereço de Carteira:** O rodapé de `public_html/index.php` exibe o texto truncado `0xa31c...356c` apontando para o endereço real `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` no BaseScan, gerando desconfiança imediata de segurança/phishing antes do envio de USDC.
2. **Divergência de 10x a 100x na Matemática de Créditos do Session Pass:** Com o ajuste dos preços base para `0.0050 USDC` (1 crédito), um pacote Starter de `2 USDC` equivale a **400 créditos** (400 chamadas padrão). Porém, o `README.md` anuncia `40.000 créditos`, enquanto `index.php` e `llms.txt` anunciam `4.000 créditos`.
3. **Defasagem de Catálogo entre Site, README, MCP e Glama.ai:** O portal opera 22 serviços, mas o `README.md` lista apenas 8, o servidor MCP (`mcp/tools.mjs`) menciona "6 micro-serviços" e expõe 11 ferramentas, e diretórios externos (Glama.ai) indexaram descrições desatualizadas.
4. **Telemetria Parcial no Catálogo (`/catalog` e `/status`):** Apenas 11 dos 22 endpoints aparecem com status `"UP"` verificado no catálogo público; endpoints corporativos (`compliance`, `lei`, `ofac`, `simples`, `sanctions`, `domain-audit`, `kyb`, `global-markets`, `notarize`, `pass`) não apresentam status operacional confirmado.
5. **Bloqueio de Crawlers de IA em Rotas de Auditoria:** Agentes de auditoria (Claude Web / ChatGPT) sofreram bloqueio ou falha ao tentar ler diretamente `/trust` e `/openapi.json`, dependendo de metadados antigos no GitHub.
6. **Lacunas de Governança, Proveniência e Controles Financeiros M2M:** Ausência de identificação institucional clara do operador, histórico público de uptime (30 dias), `CHANGELOG.md` de preços/versões, atestação de proveniência nos pacotes PyPI/npm, idempotência contra cobrança duplicada (`Idempotency-Key`) e limites de gasto por agente (`Spend Controls`).

## 2. Objetivos
- **OB01 (Fase 1 - Consistência Zero-Drift):** Eliminar 100% das divergências de carteira (`0xa592...3f90`), matemática de créditos (`400 / 1.000 / 6.000 créditos`), catálogo (`22/22 serviços`) e status (`22/22 UP`) entre `index.php`, `config.php`, `openapi.json`, `llms.txt`, `README.md` e `mcp/tools.mjs`.
- **OB02 (Fase 1 - Desbloqueio de Agentes Auditores):** Garantir que `/trust`, `/catalog`, `/status`, `/openapi.json`, `/llms.txt` e `/robots.txt` respondam com HTTP 200 e cabeçalhos abertos para crawlers de LLMs (`ClaudeBot`, `GPTBot`, `ChatGPT-User`, `PerplexityBot`).
- **OB03 (Fase 2 - Governança e Confiabilidade Operacional):** Publicar transparência institucional em `/trust`, `CHANGELOG.md`, `/.well-known/security.txt`, série histórica real de uptime (30 dias), metadados `_meta` de proveniência por resposta e publicação com proveniência verificável (PyPI/npm).
- **OB04 (Fase 3 - Infraestrutura Financeira M2M):** Implementar proteção contra pagamento duplicado via `Idempotency-Key`, endpoint gratuito de auditoria/saldo de Session Pass (`GET /api/v1/pass/balance`) e controles de gasto por agente (`Spend Controls`).

## 3. Não objetivos (fora do escopo)
- Migrar a arquitetura Zero-DB para banco de dados relacional externo (MySQL/PostgreSQL) nesta versão.
- Emitir garantias jurídicas de birô de crédito privado (Serasa/SPC) ou substituir parecer jurídico regulatório humano.
- Alterar o preço base atual das chamadas (`0.0050 a 0.0300 USDC`).

## 4. Público-alvo e personas
- **Engenheiro de Agentes de IA / Desenvolvedor M2M:** Integra o Grid402 via MCP (Claude/Cursor), Coinbase AgentKit, ElizaOS ou SDKs Python/Node e exige documentação exata, idempotência e controle de gastos da carteira do agente.
- **Avaliador Técnico / Auditor de Segurança (Humano ou LLM):** Inspeciona `/trust`, `/openapi.json`, `/catalog`, GitHub e PyPI/npm para validar a maturidade, integridade criptográfica e identidade do operador antes de recomendar a plataforma.
- **Gestor de Produto / Compliance Tech:** Avalia fontes de dados públicos (Receita Federal, BCB, CGU, GLEIF, OFAC), frescor de cache (TTL) e histórico de disponibilidade antes de aprovar uso em fluxos de negócio.

## 5. Histórias de usuário
- **HU01:** Como desenvolvedor Web3, quero ver exatamente o mesmo endereço de carteira (`0xa592...3f90`) e a mesma matemática de créditos (`1 crédito = 0.0050 USDC`) no site, na API, no `llms.txt` e no `README.md`, para confiar que não há erro de cobrança ou risco de phishing.
- **HU02:** Como usuário de Claude Desktop / Cursor via MCP, quero ter acesso às ferramentas completas do catálogo do Grid402 com descrições fiéis e preços atualizados em `mcp/tools.mjs`.
- **HU03:** Como agente de IA ou avaliador técnico, quero acessar `/trust`, `/catalog`, `/status` e `/openapi.json` sem bloqueios e visualizar status `"UP"` verificado para todos os 22 serviços, além do histórico de uptime de 30 dias.
- **HU04:** Como arquiteto de software, quero consultar a identidade do operador, o SLA de Best-Effort, o `CHANGELOG.md` de preços e os metadados `_meta` de origem/TTL em cada resposta JSON para auditar a proveniência do dado.
- **HU05:** Como operador de agentes autônomos em produção, quero enviar `Idempotency-Key` para evitar cobrança duplicada em retentativas de rede e consultar `GET /api/v1/pass/balance` (com limites diários opcionais) para controlar o orçamento do meu agente.

## 6. Requisitos funcionais

| ID | Fase | Requisito | Prioridade |
|---|---|---|---|
| **RF01** | Fase 1 | Unificar a exibição do endereço da carteira recebedora (`0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90` / `0xa592...3f90`) em todo o `public_html/index.php` e documentos, removendo qualquer referência residual a `0xa31c...356c`. | Must |
| **RF02** | Fase 1 | Padronizar a matemática oficial do Session Pass (`1 crédito = 0.0050 USDC`): Starter (`2 USDC` = `400 créditos`), Pro (`5 USDC` = `1.000 créditos`), Fleet (`20 USDC` = `5.000 + 1.000 bônus = 6.000 créditos`) no motor de passes (`x402.php`/`index.php`), UI, `llms.txt`, `openapi.json` e `README.md`. | Must |
| **RF03** | Fase 1 | Monitorar e exibir status operacional verificado (`"UP"`, latência e fonte) para todos os **22 serviços** do catálogo em `/status` e `/catalog`. | Must |
| **RF04** | Fase 1 | Sincronizar `mcp/tools.mjs`, `README.md` e schemas para refletir o catálogo completo de serviços, corrigindo descrições defasadas ("6 micro-serviços") e garantindo paridade de nomes e parâmetros. | Must |
| **RF05** | Fase 1 | Disponibilizar `public_html/robots.txt` e cabeçalhos HTTP amigáveis a crawlers de IA (`ClaudeBot`, `GPTBot`, `ChatGPT-User`, `PerplexityBot`, `OAI-SearchBot`) nas rotas `/trust`, `/catalog`, `/status`, `/openapi.json` e `/llms.txt`. | Must |
| **RF06** | Fase 2 | Atualizar `/trust` com identificação institucional transparente do operador, matriz de fontes públicas oficiais + TTL de cache, posicionamento claro (Dados Públicos Verificados com ECDSA vs Bureaus Privados), nota de desambiguação de marca (`grid402.online`) e criar `CHANGELOG.md` + `/.well-known/security.txt`. | Must |
| **RF07** | Fase 2 | Registrar e exibir histórico de disponibilidade (Uptime 30 dias) em `/status` e `/trust` via armazenamento atômico (`uptime_history.json`) e injetar bloco `_meta` (`upstream_source`, `fetched_at`, `cache_ttl_sec`) nas respostas pagas da API. | Must |
| **RF08** | Fase 2 | Configurar workflow de publicação CI/CD com atestação de proveniência (PyPI Trusted Publishing / PEP 740 e `npm publish --provenance`) e script de backup/verificação de integridade UTF-8/JSON do diretório `private_data`. | Should |
| **RF09** | Fase 3 | Implementar suporte ao cabeçalho `Idempotency-Key` nas rotas `/api/v1/*`: chamadas repetidas com a mesma chave dentro de 300 segundos retornam o payload assinado original sem debitar novamente o Session Pass ou rejeitar a transação. | Must |
| **RF10** | Fase 3 | Criar o endpoint gratuito `GET /api/v1/pass/balance` (retornando créditos restantes, validade, histórico resumido e limites) e suportar parâmetros de Spend Control por Session Pass (`daily_credit_limit`, `allowed_endpoints`). | Must |

## 7. Requisitos não funcionais
- **Performance:** A validação de Session Pass, `Idempotency-Key` e consulta a `/api/v1/pass/balance` deve manter latência interna de processamento abaixo de `15ms`.
- **Concorrência e Integridade Zero-DB:** Todas as leituras/escritas em arquivos JSON (`passes.json`, `idempotency_cache.json`, `uptime_history.json`) devem usar `grid402_atomic_write` com `LOCK_EX` e validação estrita de UTF-8 (`JSON_UNESCAPED_UNICODE`).
- **Segurança:** Nenhum token de Session Pass completo deve vazar em logs públicos; `/api/v1/pass/balance` exige envio do próprio token via cabeçalho `X-Payment` ou parâmetro autenticado.
- **Compatibilidade:** Manter retrocompatibilidade total com PHP 8.1+, Node.js 18+ (MCP/SDK) e Python 3.10+ (SDK).

## 8. Fluxos e UX
1. **Fluxo de Auditoria por LLM / Desenvolvedor (Fase 1 & 2):**
   - O avaliador (ou bot de IA) acessa `https://grid402.online/llms.txt`, `/openapi.json`, `/catalog`, `/status` e `/trust`.
   - Encontra exatamente 22 serviços documentados, todos com status `"UP"` verificado, carteira única `0xa592...3f90`, tabela de créditos consistente (`400 / 1.000 / 6.000`), histórico de uptime de 30 dias, `CHANGELOG.md` e operador identificado.
2. **Fluxo de Consumo Resiliente com Spend Control (Fase 3):**
   - O agente envia `GET /api/v1/cnpj?cnpj=...` com `X-Payment: pass_g402_...` e `Idempotency-Key: req_123`.
   - Se a rede oscilar e o agente repetir a requisição com `Idempotency-Key: req_123`, o Grid402 devolve HTTP 200 com `X-Idempotent-Replay: true` e o mesmo `_oracle_proof`, debitando apenas 1 vez (4 créditos).
   - O agente consulta `GET /api/v1/pass/balance` gratuitamente para verificar seus créditos restantes e teto diário antes de iniciar um lote de consultas.

## 9. Métricas de sucesso
- **Zero inconsistências** detectadas em nova rodada de auditoria no Claude, ChatGPT e Dola (meta: elevar nota de maturidade percebida de `6.5/10` para `>= 8.5/10`).
- **22/22 endpoints** reportando status `"UP"` verificado em `/status` e `/catalog`.
- **100% de paridade** matemática entre `config.php`, `index.php`, `x402.php`, `openapi.json`, `llms.txt`, `README.md` e `mcp/tools.mjs`.
- **100% dos testes automatizados** (`tests/` e `mcp/test_mcp.mjs`) passando nas 3 fases.

## 10. Riscos, dependências e premissas
- **Passes emitidos anteriormente:** No motor de passes (`x402.php`/`index.php`), passes existentes no armazenamento continuarão funcionando normalmente; a nova emissão ajustará os créditos dos tiers (`400`, `1.000`, `6.000`) e o custo unitário em créditos (`1 crédito = 0.0050 USDC`) de forma sincronizada para que o poder de compra real em chamadas permaneça idêntico (Starter = 400 chamadas padrão).
- **Disponibilidade de fontes externas:** Caso uma API pública upstream (ex.: ReceitaWS/BrasilAPI/BCB) sofra lentidão momentânea, o status e o metadado `_meta` informarão se o dado veio de cache resiliente (`stale-if-error`), preservando a transparência operacional.

## 11. Questões em aberto
- Nenhuma questão bloqueante em aberto (decisões de matemática de créditos, transparência institucional e execução em 3 fases já confirmadas na etapa de Grill-me).
