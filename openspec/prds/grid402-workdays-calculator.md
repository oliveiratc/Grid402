# PRD: Calculadora de Dias Uteis Financeiros ANBIMA (Workdays)

**Status:** Em revisao
**Data:** 2026-11-08
**Autor:** Thiago e Antigravity

---

## 1. Contexto e problema

No ecossistema corporativo e financeiro brasileiro, a contagem de dias uteis bancarios e o calculo de vencimentos operacionais (como prazos de liquidacao D+1 e D+2, liquidacao de duplicatas, debentures e titulos publicos) seguem rigorosamente o calendario da ANBIMA e da Febraban.

Atualmente, agentes autonomos de inteligencia artificial, robos de cobranca e desenvolvedores de aplicacoes descentralizadas enfrentam barreiras tecnicas ao lidar com dias uteis bancarios:
1. **Falta de padronizacao leve:** Bibliotecas locais sao frequentemente pesadas, exigem dependencias volumosas ou ficam desatualizadas quando regras de feriados municipais ou nacionais mudam.
2. **Dependencia de banco de dados:** A maioria das solucoes exige tabelas relacionais persistentes para indexar dias uteis de varios anos.
3. **Friccao de acesso:** Servicos proprietarios de mercado exigem contratos corporativos, cadastros burocraticos e mensalidades em reais, inviabilizando micro-consultas autonomas M2M (Machine-to-Machine).

Com a disponibilizacao previa do motor de feriados nacionais e bancarios no Grid402 (`/api/v1/holidays`), o ecossistema dispoe da base algoritmica exata para fornecer uma calculadora stateless de altissima precisao temporal, cobrada a apenas 0.0005 USDC via protocolo HTTP 402 na rede Base.

---

## 2. Objetivos

- **Objetivo 1:** Disponibilizar o endpoint `/api/v1/workdays` capaz de executar 3 modalidades de calculo temporal: contagem entre datas, projecao de prazos e checagem pontual de dia util.
- **Objetivo 2:** Adotar estritamente a convencao financeira brasileira da ANBIMA (inicio D+0 exclusivo e data final inclusiva), fornecendo tambem a contagem de dias corridos e a relacao de feriados que incidiram no intervalo.
- **Objetivo 3:** Oferecer amostra gratuita no desafio HTTP 402 (teaser data), permitindo que agentes e clientes avaliem a resposta antes de efetuar o micropagamento.
- **Objetivo 4:** Integrar nativamente a calculadora nos SDKs oficiais (Node.js e Python) e no Servidor MCP (`grid402-mcp`), expandindo o catalogo para 11 servicos operacionais.

---

## 3. Nao objetivos (fora do escopo)

- Nao contempla feriados de pracas internacionais (NYSE, CME, LSE, Nasdaq) nem feriados municipais especificos de cidades do interior (escopo estrito aos feriados bancarios nacionais da ANBIMA e Febraban).
- Nao calcula juros compostos, CDI acumulado ou correcao monetaria de titulos (a calculadora foca estritamente na dimensao cronologica e contagem de prazos).
- Nao retem logs identificaveis de consultas ou dados de clientes (arquitetura 100% Zero-DB e em memoria).

---

## 4. Publico-alvo e personas

- **Agentes Autonomos e FinOps de IA:** Agentes integrados com Coinbase AgentKit ou frameworks similares que necessitam agendar pagamentos ou liquidacoes de contratos na Base para o proximo dia util valido.
- **Desenvolvedores de ERPs e Fintechs:** Engenheiros de software que processam concilicao contabil e cobranca bancaria e desejam consultar prazos uteis via chamadas HTTP rapidas e sem mensalidades.
- **Usuarios de IDEs e Model Context Protocol:** Desenvolvedores no Cursor ou Claude Desktop que utilizam a ferramenta MCP do Grid402 para checar prazos de desenvolvimento e marcos de projetos em dias uteis.

---

## 5. Historias de usuario

- **Como agente de liquidacao financeira,** quero projetar a data util correspondente a D+2 a partir de hoje, para disparar a transferencia de USDC no momento exato de liquidacao.
- **Como sistema de concilicao contabil,** quero calcular quantos dias uteis ocorreram entre a data de faturamento e a data de pagamento, para checar a cobranca de encargos por dia de atraso.
- **Como desenvolvedor em Claude Desktop via MCP,** quero saber se uma data especifica e dia util ou feriado bancario, para estruturar cronogramas de entrega sem ambiguidades.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | **Roteamento e Modos de Operacao:** O endpoint `/api/v1/workdays` deve aceitar requisicoes GET e POST (JSON), identificando o modo de calculo automaticamente a partir dos parametros fornecidos (`start_date`, `end_date`, `days`, `date`). | Must |
| **RF02** | **Contagem entre Datas (Modo Intervalo):** Quando `start_date` e `end_date` forem informados, o sistema deve calcular a quantidade de dias uteis conforme a convencao ANBIMA (inicio exclusivo e fim inclusivo), os dias corridos (`calendar_days`), a lista de feriados no periodo e o status de dia util de cada extremo. | Must |
| **RF03** | **Projecao de Prazo (Modo Deslocamento):** Quando `start_date` (ou `date`) e `days` (inteiro positivo ou negativo) forem informados, o sistema deve deslocar a data adicionando ou subtraindo dias uteis ate alcancar a `target_date`, informando os dias corridos e feriados percorridos. | Must |
| **RF04** | **Checagem Pontual (Modo Data Simples):** Quando apenas `date` for informado, o sistema deve indicar se a data e dia util (`is_workday`: true/false), o tipo de dia (`workday`, `weekend` ou `holiday`), o nome do feriado (se houver) e o proximo dia util subsequente. | Must |
| **RF05** | **Desafio HTTP 402 com Amostra Teaser:** Quando a rota for requisitada sem comprovante de pagamento e fora do sandbox, o servidor deve retornar HTTP 402 contendo os parametros de cobranca (0.0005 USDC) e a chave `preview` informando se a data base e dia util e o proximo feriado bancario. | Must |
| **RF06** | **Modo Sandbox Gratuito:** O sistema deve conceder acesso 100% funcional e gratuito a todos os calculos quando o cabecalho `X-Payment: demo` estiver presente. | Must |
| **RF07** | **Limites Defensivos e Validacao Temporal:** O sistema deve aceitar apenas datas validas no formato ISO 8601 (`YYYY-MM-DD`) entre os anos 1900 e 2100, rejeitando intervalos superiores a 20 anos ou projecoes acima de 5.000 dias uteis com HTTP 400. | Must |
| **RF08** | **SDK Node.js e TypeScript:** O pacote `grid402` deve incluir o metodo `client.getWorkdays(params, paymentTxHash?)` com tipagem estrita `WorkdaysParams` e `WorkdaysResponse`. | Must |
| **RF09** | **SDK Python Oficial:** O pacote `grid402` em Python deve incluir o metodo `client.get_workdays(...)` com modelos Pydantic v2 `WorkdaysInput` e `WorkdaysResponse`. | Must |
| **RF10** | **Servidor MCP:** O servidor `grid402-mcp` deve registrar a ferramenta `grid402_workdays` compativel com Claude Desktop, Cursor e orquestradores de IA. | Must |
| **RF11** | **Documentacao e Catalogo OpenAPI:** Atualizacao de `openapi.json`, `llms.txt`, `sitemap.xml`, console web interativo e WebMCP para refletir o novo servico. | Must |

---

## 7. Requisitos nao funcionais

- **Performance:** Tempo de processamento em servidor inferior a 10ms por calculo, operando estritamente em memoria RAM sem consultas a disco ou rede externa.
- **Arquitetura Stateless e Zero-DB:** Nenhum banco de dados ou estado persistente deve ser necessario para computar os dias uteis e feriados.
- **Segurança e Anti-DoS:** Rejeicao defensiva de projecoes que excedam 5.000 dias ou 20 anos para impedir esgotamento de ciclos de CPU em ambientes compartilhados.
- **Conformidade de Linguagem:** Zero caracteres de e comercial ampersand em todas as documentacoes, schemas, comentarios e respostas do sistema.

---

## 8. Fluxos e UX

### 8.1. Fluxo Principal: Contagem entre Datas
1. O requisitante envia `GET /api/v1/workdays?start_date=2026-12-23` com parametro `end_date=2026-12-28`.
2. O servidor valida a prova de pagamento x402 ou token `demo`.
3. O servidor computa o intervalo: dia 23 e D+0; dias 24 e 25 sao feriados bancarios; dias 26 e 27 sao final de semana; dia 28 e dia util (D+1).
4. O servidor retorna HTTP 200 com `workdays: 1`, `calendar_days: 5`, e os feriados identificados.

### 8.2. Fluxo Principal: Projecao de Vencimento (Adicao de Dias Uteis)
1. O requisitante envia `GET /api/v1/workdays?start_date=2026-11-06` com parametro `days=5`.
2. O servidor avanca dia a dia pulando finais de semana e feriados bancarios ate totalizar 5 dias uteis.
3. O servidor retorna HTTP 200 com a `target_date` exata e o detalhamento do percurso.

### 8.3. Fluxo Alternativo: Chamada sem Pagamento (Teaser 402)
1. O requisitante chama `GET /api/v1/workdays?start_date=2026-12-25` com parametro `days=10` sem cabecalho `X-Payment`.
2. O servidor detecta a ausencia do comprovante e formata o desafio HTTP 402 Payment Required.
3. No payload de resposta, entrega `price_usdc: 0.0005`, carteira de destino, rede Base e a chave `preview` contendo:
   - `base_date`: "2026-12-25"
   - `is_workday`: false
   - `day_type`: "holiday"
   - `holiday_name`: "Natal"
   - `next_workday`: "2026-12-28"
4. Os campos de calculo completo sao listados na chave `locked_fields`.

---

## 9. Metricas de sucesso

- **Cobertura de Testes:** 100% dos testes unitarios e de integracao aprovados em PHP, Node.js, Python e MCP.
- **Precisao de Calendario:** Concordancia matematica de 100% com o calendario de dias uteis publicado pela ANBIMA para os anos vigentes.
- **Integracao de Ecossistema:** 3 novos SDKs/ferramentas distribuidos com versionamento semantico incremental.

---

## 10. Riscos, dependencias e premissas

- **Dependencia:** O servico depende das definicoes de feriados bancarios existentes em `public_html/services/holidays.php`.
- **Premissa:** As datas fornecidas sao baseadas no horario oficial de Brasilia (UTC-3).
- **Risco:** Requisicoes com intervalos de centenas de anos poderiam degradar a CPU; o risco e mitigado pela trava de 20 anos / 5.000 dias uteis.

---

## 11. Questoes em aberto

- [x] Convencao de contagem definida: Padrão ANBIMA (D+0 exclusivo no inicio e inclusivo no final).
- [x] Limite maximo de projecao definido: 20 anos ou 5.000 dias uteis.
- [x] Politica de amostragem gratuita (teaser) aprovada para o desafio HTTP 402.

