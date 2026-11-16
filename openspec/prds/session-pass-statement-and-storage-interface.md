# PRD: Extrato Detalhado por Session Pass e Abstração de Persistência Zero-DB (Fase 2)

**Status:** Em revisão
**Data:** 2026-11-16
**Autor:** Thiago Campos + Agente

## 1. Contexto e problema
Após a conclusão da Fase 1 (observabilidade JSONL global com `X-Request-Id`, percentis `p50`/`p95` no `/admin` e `/trust`, travas multi-processo e sincronização MCP/SDK), os operadores administrativos possuem telemetria global da plataforma, porém os clientes finais e agentes M2M que utilizam Session Passes pré-pagos (`pass_g402_...`) ainda possuem apenas uma visão agregada de saldo em `/payment-dashboard` e `/api/v1/pass/balance`.

Hoje faltam:
1. Um extrato individual por token (`pass_g402_...`) discriminando cada débito bem-sucedido (`200`), bloqueio por Spend Control (`403 PassEndpointRestricted`, `429 PassDailyLimitExceeded`) e replay idempotente (`idempotent_replay`), com equivalente em USDC e correlação por `request_id`, além de exportação CSV/JSON para conciliação financeira.
2. Uma fronteira formal de persistência (`PassStorageInterface`) desacoplando a lógica de negócio dos arquivos JSON com `flock()`, mantendo 100% de compatibilidade com Hostinger LiteSpeed PHP 8.1+ (onde extensões `pdo_sqlite` e `pdo_mysql` não são assumidas no runtime CLI/shared), mas deixando o código preparado para uma eventual troca de engine futura sem reescrever regras de cobrança.

## 2. Objetivos
- Entregar extrato estruturado Zero-PII por Session Pass em **JSON e CSV** via API gratuita (`GET /api/v1/pass/statement`) e interface visual em `/payment-dashboard` (EN/PT), cobrindo eventos `200`, `403` (`PassEndpointRestricted`), `429` (`PassDailyLimitExceeded`) e replays idempotentes.
- Limitar a retenção por passe a um **ring buffer de até `200` lançamentos recentes** por `pass_id` (evitando crescimento ilimitado em disco e preservando latência `< 15 ms` por débito).
- Introduzir a interface `PassStorageInterface` e sua implementação oficial `JsonFlockPassRepository` por trás de `public_html/services/pass.php`, garantindo isolamento de leitura/escrita atômica sob `flock()` e `grid402_read_locked_json()`.
- Manter compatibilidade estrita com o CI atual (PHP `8.1`, `8.2`, `8.3`) com nova bateria de testes automatizados cobrindo 100% dos requisitos Must.

## 3. Não objetivos (fora do escopo)
- Provisionar ou exigir bancos SQLite WAL, MySQL ou PostgreSQL em produção ou no CI nesta Fase 2.
- Alterar o catálogo de `24` ferramentas do servidor MCP `grid402-mcp` ou adicionar novos métodos nos SDKs `npm` / `PyPI`.
- Exigir assinatura EIP-191 de carteira adicional além do token HMAC `pass_g402_...` para consultar o extrato do passe.
- Construir marketplace de hospedagem ou faturamento para APIs x402 de terceiros (Fase 3).

## 4. Público-alvo e personas
- **Engenheiros de Agentes Autônomos e Desenvolvedores M2M:** precisam depurar quais endpoints consumiram os créditos de um agente, verificar o motivo exato de bloqueios `403`/`429` do Spend Control e correlacionar chamadas com `X-Request-Id`.
- **Times Financeiros e Operadores de Frota (Fleet/Pro Pass):** precisam baixar demonstrativos CSV/JSON com equivalente em USDC (`0.0050 USDC` por crédito) para fechamento contábil e prestação de contas interna.
- **Mantenedores da Plataforma Grid402:** precisam de uma arquitetura de repositório desacoplada (`PassStorageInterface`) e segura contra crescimento de arquivo ou regressão de concorrência.

## 5. Histórias de usuário
- Como dono de um Session Pass (`pass_g402_...`), quero acessar `/payment-dashboard` com meu token e visualizar os últimos lançamentos filtrados por endpoint ou status para auditar onde meus créditos foram gastos ou bloqueados.
- Como responsável financeiro de uma equipe de agentes, quero clicar em "Exportar CSV" ou consultar `GET /api/v1/pass/statement?token=...&format=csv` para baixar um extrato compatível com planilhas (UTF-8 com BOM e protegido contra CSV injection).
- Como agente ou script de monitoramento, quero consultar `GET /api/v1/pass/statement` enviando o token no header `X-Payment` ou query `token` e receber JSON estruturado contendo o resumo do passe, lista paginada/filtrada (máximo `200` entradas recentes) e metadados da janela de retenção.
- Como arquiteto do Grid402, quero que as operações de emissão, leitura de saldo, atualização de Spend Controls, consumo atômico e extrato passem por `PassStorageInterface` implementado por `JsonFlockPassRepository`.

## 6. Requisitos funcionais
| ID | Requisito | Prioridade (Must/Should/Could) |
|----|-----------|--------------------------------|
| RF01 | Definir o contrato `PassStorageInterface` (em `public_html/services/pass_repository.php` ou equivalente carregado por `pass.php`) e a implementação `JsonFlockPassRepository`, migrando emissão, leitura, Spend Controls, consumo e extrato para esse repositório. | Must |
| RF02 | Registrar atomicamente eventos Zero-PII no extrato do passe (ring buffer com limite estrito de `200` eventos por `pass_id` em armazenamento separado do índice quente ou isolado sob `flock`) contendo `ts` (ISO-8601 UTC), `request_id`, `endpoint`, `credits_debited`, `credits_after`, `usdc_equivalent`, `http_status` e `outcome`. | Must |
| RF03 | Capturar no extrato todos os desfechos relevantes do Session Pass: débito aprovado (`200`, `outcome: consumed`), bloqueio de endpoint (`403`, `outcome: endpoint_restricted`), bloqueio de limite diário (`429`, `outcome: daily_limit_exceeded`) e replay idempotente com pass (`200`, `credits_debited: 0`, `outcome: idempotent_replay`). | Must |
| RF04 | Implementar o endpoint gratuito `GET /api/v1/pass/statement` aceitando o token `pass_g402_...` via query `?token=`, header `X-Payment` ou `Authorization: Bearer ...`, validando assinatura HMAC antes de tocar no disco (400 em formato inválido, 404 em passe inexistente, sem cobrar créditos). | Must |
| RF05 | Suportar filtros opcionais `?endpoint=<id>` e `?outcome=<consumed|endpoint_restricted|daily_limit_exceeded|idempotent_replay>` e saída em `?format=json` (padrão) ou `?format=csv` (com `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment`, BOM UTF-8 e sanitização de células iniciadas por `=`, `+`, `-`, `@`, `\t` ou `\r`). | Must |
| RF06 | Garantir rigorosamente Zero-PII no extrato e no endpoint: nunca armazenar nem retornar parâmetros de consulta (ex.: CNPJ, CEP, domínio, endereço consultado), segredos ou endereço IP do cliente. | Must |
| RF07 | Atualizar `/payment-dashboard` ([payment_dashboard.php](file:///c:/Antigravity/Projetos/Grid402/public_html/payment_dashboard.php)) com tabela responsiva do extrato recente, filtros por endpoint/desfecho, totais do extrato exibido (chamadas, créditos debitados, bloqueios, USDC equivalente), alerta explicativo da janela de `200` lançamentos com contato de suporte para períodos maiores, estados vazios claros e botões de download CSV e JSON. | Must |
| RF08 | Adicionar traduções completas em inglês e português no [i18n.js](file:///c:/Antigravity/Projetos/Grid402/public_html/assets/js/i18n.js) para todos os textos de UI do `/payment-dashboard` (mantendo inglês SSR padrão sem literal `&`). | Must |
| RF09 | Documentar a rota `GET /api/v1/pass/statement` em `public_html/openapi.json`, `public_html/llms.txt`, `public_html/llms-full.txt` e link de consulta em `/pass`, mantendo zero literal `&` nos arquivos de descoberta validados por `test_agent_discovery.php`. | Should |
| RF10 | Criar suíte automatizada dedicada `tests/test_session_pass_statement.php` cobrindo `PassStorageInterface`, registro de `200`/`403`/`429`/`idempotent_replay`, cap estrito em `200` eventos, Zero-PII, proteção contra CSV injection, filtros HTTP, erros `400`/`404` e disputa concorrente sem degradação ou double-spend. | Must |

## 7. Requisitos não funcionais
- **Performance e Baixa Contenção:** O consumo de Session Pass em `index.php` deve permanecer abaixo de `15 ms` em execução local sob carga normal, e armazenar o extrato em arquivo dedicado de histórico (ou estrutura segmentada por `pass_id` limpa ao expurgarmos passes expirados) para evitar inflar o JSON quente de saldo.
- **Segurança e Privacidade (Zero-PII e CSV Injection):** Nenhum parâmetro de entrada de API, token completo além do próprio identificador fornecido na consulta autorizada, nem IP deve constar nos lançamentos de extrato. Toda célula CSV potencialmente interpretável como fórmula (`=`, `+`, `-`, `@`, `\t`, `\r`) deve ser prefixada com apóstrofo simples (`'`). Os arquivos em `private_data/` continuam bloqueados via HTTP (`403`).
- **Acessibilidade e Semântica HTML:** Tabela responsiva com `<thead>`/`<th scope="col">`, labels explícitos para filtros (`<label for="...">`), navegação por teclado e contraste conforme o tema escuro do site.
- **Compatibilidade Cross-OS e PHP:** 100% compatível com PHP 8.1, 8.2 e 8.3 em Linux e Windows sem exigir extensões externas (`sqlite3`, `pdo_sqlite` ou `pdo_mysql`).

## 8. Fluxos e UX
1. **Fluxo de Registro Automático:** Cliente consome `/api/v1/cnpj` com `X-Payment: pass_g402_...` e `X-Request-Id`.
   - Caso aprovado (`200`): créditos debitados atomicamente e evento `outcome=consumed` gravado no ring buffer (descartando eventos além dos `200` mais recentes).
   - Caso bloqueado por whitelist (`403 PassEndpointRestricted`) ou teto diário (`429 PassDailyLimitExceeded`): resposta de erro retornada imediatamente e tentativa registrada no extrato com `credits_debited=0` e `outcome` correspondente.
   - Caso replay via `Idempotency-Key` (`200`): `credits_debited=0` e `outcome=idempotent_replay`.
2. **Fluxo API `GET /api/v1/pass/statement`:**
   - Token ausente ou HMAC inválido → HTTP `400` (`InvalidPassTokenFormat`).
   - Passe não encontrado → HTTP `404` (`PassNotFound`).
   - `?format=json` (ou omitido) → HTTP `200` com `pass`, `retention` (`max_entries: 200`, `stored_entries`, `support_contact: "contato@grid402.online"`), `filters`, `summary` e `entries`.
   - `?format=csv` → HTTP `200` com arquivo `grid402_statement_<pass_id>.csv` para download.
3. **Fluxo Web (`/payment-dashboard`):**
   - Usuário informa `pass_g402_...` → vê status do passe + cards de resumo + filtros (`Endpoint`, `Outcome`) + botões **Export CSV** e **Export JSON** + tabela dos eventos em ordem decrescente de data.
   - **Estado vazio (token válido sem chamadas ainda ou filtro sem matches):** mensagem clara em inglês/português explicando que ainda não há consumo registrado ou sugerindo limpar o filtro.

## 9. Métricas de sucesso
- `100%` dos cenários da suíte `tests/test_session_pass_statement.php` aprovados (incluindo cap exato em `200`, CSV injection, Zero-PII e concorrência).
- Regressão zero em todas as suítes existentes (`test_concurrency_and_load.php`, `test_maturity_and_m2m_controls.php`, `test_agent_discovery.php`, `test_e2e_full.php`, `verify_storage_integrity.php`).
- Todas as chaves `data-i18n*` de `payment_dashboard.php` cobertas em `PT` e `EN` no `i18n.js`.
- Workflow do GitHub Actions verde em PHP `8.1`, `8.2` e `8.3` após conclusão.

## 10. Riscos, dependências e premissas
- **Risco:** Contenção extra caso o registro de extrato abra múltiplos arquivos sob lock simultaneamente na mesma chamada — **Mitigação:** usar travamento curto e ordem consistente de lock (ou gravação atômica isolada por arquivo de extratos / método único no `JsonFlockPassRepository` com `grid402_read_locked_json()`).
- **Risco:** Crescimento gradual do arquivo de extratos quando muitos passes de sandbox são emitidos — **Mitigação:** expurgar entradas de extratos cujos passes tenham expirado há mais de 7 dias durante a manutenção de emissão/limpeza (espelhando a política já aplicada em `session_passes.json`).
- **Premissa:** O domínio canônico permanece `https://grid402.online`, 1 crédito equivale a `0.0050 USDC`, e o armazenamento continua em `GRID402_PRIVATE_DIR`.

## 11. Questões em aberto
- [x] Todos os pontos de escopo, arquitetura (`PassStorageInterface` + Zero-DB), retenção (`200` lançamentos/pass), autenticação (token HMAC) e eventos registrados (`200`, `403`, `429`, `idempotent_replay`) foram confirmados com o usuário no Grill-me.
