# PRD: Confiabilidade M2M, Consistência MCP/SDK e Observabilidade Estruturada (Pós-Auditoria)

**Status:** Em revisão
**Data:** 2026-11-16
**Autor:** Thiago Campos + Agente

## 1. Contexto e problema
Uma auditoria técnica e comercial independente (baseada em documentação pública, repositório GitHub, PyPI e diretório Glama MCP) identificou percepções de risco e inconsistências concretas que reduzem a confiança de desenvolvedores de IA e avaliadores técnicos no Grid402:
1. O diretório **Glama MCP** e os manifests em `/.well-known/` refletem versões desatualizadas (apenas 15 ferramentas listadas em `mcp.json` e `server-card.json` em vez das 24 atuais) e não recebem o campo `instructions` no handshake MCP (`mcp/index.mjs`).
2. Ainda restam referências legadas ao domínio antigo `grid402.com.br` em `examples/` e ao preço antigo `0.0005 USDC` em `sdk/` e `.env.example`, além do pacote público no **PyPI** (`grid402`) constar desatualizado em auditorias externas.
3. Há aparente ambiguidade jurídica entre o `LICENSE` proprietário na raiz e a licença `MIT` declarada no `package.json`, `mcp/package.json` e `python/pyproject.toml`, sem delimitação explícita do modelo **Dual-License**.
4. Apesar de já existir agregação em `metrics.json` e trava atômica `flock`, faltam **logs estruturados JSONL** com correlação por `X-Request-Id` (para auditar falhas, latência p95 e origem de pagamentos sem expor PII) e falta uma **suíte automatizada de testes de concorrência paralela e carga** comprovando ausência de double-spend/race condition em condições reais de disputa.

Resolver esses itens fecha a **Fase 1 (Confiabilidade)** recomendada pela auditoria antes de investir em novos endpoints ou migrações prematuras de banco de dados.

## 2. Objetivos
- Eliminar 100% das inconsistências de metadados, domínios legados, preços antigos (`0.0005`), versões MCP/SDK e licenciamento (Dual-License explícita).
- Publicar `instructions` nativas no servidor MCP (`grid402-mcp`) e sincronizar as 24 ferramentas nos manifests `/.well-known/mcp.json` e `/.well-known/mcp/server-card.json`.
- Implementar observabilidade estruturada Zero-PII (`requests.jsonl` com rotação em 10 MB + header `X-Request-Id`) e expor latência `p95` agregada em `/admin` e `/trust`.
- Comprovar via teste automatizado de estresse e concorrência paralela (`tests/test_concurrency_and_load.php`) que há zero double-spend em Session Pass, tx hashes e nonces EIP-3009, medindo throughput e latências `p50`/`p95`/`p99`.
- Preparar builds locais e scripts validados dos pacotes Python (`wheel`/`sdist`) e MCP (`npm pack`) para republicação imediata no PyPI e npm.

## 3. Não objetivos (fora do escopo)
- Migrar a persistência JSON Zero-DB para SQLite WAL ou PostgreSQL nesta entrega (só será avaliada após medição real do benchmark).
- Criar extrato JSON/CSV detalhado por Session Pass no `/payment-dashboard` (previsto para a Fase 2).
- Construir marketplace para hospedagem de APIs de terceiros ou programa de parceiros estendido (previsto para a Fase 3).

## 4. Público-alvo e personas
- **Engenheiros de Agentes de IA & Clientes MCP (Claude Desktop, Cursor, ElizaOS, AgentKit):** precisam de instruções claras no handshake MCP, catálogo completo das 24 ferramentas e SDKs com licença MIT inequívoca.
- **Avaliadores Técnicos / Due Diligence / Crawlers de Diretórios (Glama, Smithery, Base Grants):** auditam `/.well-known/`, `/trust`, `README.md`, `LICENSE` e evidências de testes de concorrência/observabilidade.
- **Operador da Plataforma Grid402:** precisa de `X-Request-Id`, trilha JSONL auditável sem PII e métricas de latência `p95` no `/admin` e `/trust` mantendo compatibilidade com Hostinger LiteSpeed.

## 5. Histórias de usuário
- Como cliente MCP (Claude/Cursor/Glama), quero receber `instructions` no `initialize` e listar as 24 ferramentas nos manifests `/.well-known/` para saber escolher ferramentas, usar Sandbox/Session Pass e respeitar Spend Controls.
- Como desenvolvedor externo auditando o repositório, quero ver claramente em `LICENSE` e `README.md` que os SDKs/MCP/integrações são **MIT** e o gateway (`public_html/`) é **Proprietário**, com o posicionamento *"The Verifiable Data & x402 Payment Layer for Autonomous AI Agents"* e zero URLs `.com.br` ou preços `0.0005`.
- Como operador e auditor de infraestrutura, quero que toda resposta da API retorne `X-Request-Id` e registre uma linha JSONL sanitizada (Zero-PII) em `private_data/requests.jsonl` com rotação automática em 10 MB, refletindo a latência `p95` no `/admin` e `/trust`.
- Como avaliador de confiabilidade, quero executar `php tests/test_concurrency_and_load.php` para verificar com processos concorrentes reais que duas chamadas simultâneas não conseguem consumir o mesmo crédito final nem reutilizar o mesmo `tx_hash`/`payer:nonce`.

## 6. Requisitos funcionais
| ID | Requisito | Prioridade (Must/Should/Could) |
|----|-----------|--------------------------------|
| RF01 | Adicionar campo `instructions` no `new Server(...)` em `mcp/index.mjs` e alinhar versões (`1.8.2` em `mcp/index.mjs`, `mcp/package.json` e `package.json` raiz). | Must |
| RF02 | Atualizar `public_html/.well-known/mcp.json` e `public_html/.well-known/mcp/server-card.json` para listar todas as 24 ferramentas MCP com descrições consistentes em inglês e `https://grid402.online`. | Must |
| RF03 | Remover referências legadas a `grid402.com.br` (`examples/client_agent.mjs`, `examples/client_agent.py`) e a `0.0005` (`sdk/node/grid402.cjs`, `sdk/python/grid402_tool.py`, `.env.example`), atualizando `python/README.md` e `openspec/specs/` inconsistentes se aplicável. | Must |
| RF04 | Formalizar Dual-License explícita no `LICENSE` raiz e `README.md` (Backend/Gateway `public_html/` Proprietário vs. SDKs/MCP/Integrações/Exemplos sob `MIT`) e alinhar o posicionamento principal *"The Verifiable Data & x402 Payment Layer for Autonomous AI Agents"*. | Must |
| RF05 | Gerar/propagar `X-Request-Id` em todas as respostas HTTP das rotas de API/status e gravar evento estruturado Zero-PII em `private_data/requests.jsonl` (com `ts`, `request_id`, `service`, `status`, `latency_ms`, `payment_source`, `ip_hmac_prefix`, sem tokens completos ou IPs em claro). | Must |
| RF06 | Implementar rotação automática de `requests.jsonl` ao atingir `10 MB` (mantendo backup `.1` com `LOCK_EX` e limite seguro de retenção) para proteger disco em shared hosting. | Must |
| RF07 | Calcular e expor latência `p95` agregada (com base nos últimos eventos estruturados ou `metrics.json`) na seção de telemetria de `/admin` e no `/trust`. | Must |
| RF08 | Criar suíte automatizada `tests/test_concurrency_and_load.php` cobrindo race condition paralela no último crédito de Session Pass, replay paralelo de `tx_hash`/`payer:nonce` e benchmark de carga (`p50`/`p95`/`p99`). | Must |
| RF09 | Documentar política de segurança e gestão não-custodial de chaves M2M (carteiras dedicadas com teto + Spend Controls) em `/trust` e `docs/FACILITATOR_SETUP.md`. | Should |
| RF10 | Atualizar `CHANGELOG.md`, gerar/validar builds locais (`python -m build` no `python/` e `npm pack` no `mcp/`), além de empacotar `dist/grid402_hostinger_deploy.zip`. | Should |

## 7. Requisitos não funcionais
- **Zero-PII / LGPD & GDPR:** `requests.jsonl` jamais deve armazenar chaves privadas, headers `X-Payment` completos (`pass_g402_...` ou payloads base64) nem IPs completos (somente prefixo HMAC-SHA256).
- **Performance em Shared Hosting:** O acréscimo de escrita em `requests.jsonl` com `FILE_APPEND | LOCK_EX` não deve adicionar mais que `5 ms` no tempo médio de resposta e deve rotacionar em `10 MB` mantendo no máximo `1` arquivo rotacionado anterior (`requests.jsonl.1`), limitando consumo máximo a ~`20 MB`.
- **Acesso Privado Bloqueado:** `private_data/requests.jsonl*` deve permanecer inacessível via HTTP (protegido pelo `.htaccess` existente de `private_data/` -> HTTP 403).
- **Retrocompatibilidade:** Todas as suítes existentes (`test_e2e_full.php`, `test_agent_discovery.php`, `test_maturity_and_m2m_controls.php`, `test_responsive_navigation.php`, `test_x402_exact_*`, `mcp/test_mcp.mjs`) devem continuar passando sem relaxamento de asserções.

## 8. Fluxos e UX
1. **Fluxo MCP `initialize`:** Cliente MCP conecta via stdio -> `mcp/index.mjs` responde `serverInfo` (`grid402-mcp-server`, `1.8.2`), `capabilities.tools` e `instructions` estruturadas explicando ferramentas gratuitas, Sandbox (`demo`), Session Pass e x402 -> `tools/list` retorna as 24 tools.
2. **Fluxo de Observabilidade HTTP:** Requisição chega em `/api/v1/*` ou `/status` -> se cliente enviou `X-Request-Id` válido (`[a-zA-Z0-9._-]{1,64}`), propaga; caso contrário gera UUID/hex único -> envia `X-Request-Id` no header da resposta -> ao concluir resposta, registra linha JSONL em `private_data/requests.jsonl` (rotacionando se tamanho `>= 10 MB`).
3. **Fluxo de Visibilidade (`/trust` e `/admin`):** Exibe latência `p95` em ms sem expor dados sensíveis ou IPs.

## 9. Métricas de sucesso
- 100% dos arquivos ativos no repositório livres de `grid402.com.br` e `0.0005` (exceto archives históricos de specs antigas em `openspec/changes/archive/`).
- `/.well-known/mcp.json` e `/.well-known/mcp/server-card.json` com exatas **24 ferramentas** sincronizadas com `mcp/tools.mjs`.
- `mcp/index.mjs` emitindo campo `instructions` não vazio no `initialize`.
- Teste paralelo `test_concurrency_and_load.php` aprovando **0** double-spends em disputa concorrente real e imprimindo métricas `p50`/`p95`/`p99`.
- Builds de `python` e `grid402-mcp` gerados localmente sem erros de validação.

## 10. Riscos, dependências e premissas
- **Risco:** Publicação efetiva no PyPI e npm depende de tokens de autenticação do usuário (`TWINE` / `NPM_TOKEN`), caso não estejam ativos na sessão — mitigação: deixar builds e pacotes tar.gz/whl validados localmente e prontos para publicação em 1 comando.
- **Risco:** Contenção de arquivo sob carga extrema em Windows vs Linux com `flock` — mitigação: manter writes curtos em JSONL + buffer circular de p95 em `metrics.json` ou leitura limitada das últimas N linhas (tail).
- **Premissa:** Ambiente alvo de produção continua sendo Hostinger LiteSpeed PHP 8.1+ Zero-DB.

## 11. Questões em aberto
- [x] Todas as decisões de escopo, observabilidade, licenciamento e empacotamento foram alinhadas e confirmadas com o usuário.
