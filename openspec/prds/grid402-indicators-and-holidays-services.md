# PRD: Expansao do Catalogo Grid402: Indicadores Economicos e Feriados Nacionais e Bancarios

**Status:** Em revisao  
**Data:** 2026-11-08  
**Autor:** thiago + Antigravity  

---

## 1. Contexto e problema

Agentes autonomos de IA, oraculos Web3 e sistemas Machine-to-Machine (M2M) frequentemente precisam de dados macroeconomicos e do calendario financeiro brasileiro para tomar decisoes automaticas:
1. **Agentes Financeiros e de Tesouraria:** Precisam calcular rendimentos atrelados ao CDI, correcao monetaria pelo IPCA ou calibrar taxas de juros com base na Taxa Selic oficial divulgada pelo Banco Central do Brasil (BCB).
2. **Agentes de Agendamento e Pagamentos:** Precisam verificar se uma determinada data e dia util, feriado nacional ou dia sem expediente bancario (Febraban / ANBIMA) antes de agendar liquidacoes ou contratos.

Hoje, obter esses dados exige:
- Scrapers frageis sujeitos a quebra de layout;
- Contratacao de planos corporativos caros com chaves estaticas de API e burocracia de cartoes internacionais;
- Integracoes diretas complexas com APIs legadas do governo que sofrem instabilidades constantes.

O Grid402 resolve esse gargalo disponibilizando endpoints de alta disponibilidade, formato padronizado JSON, arquitetura Zero-DB com cache resiliente e liquidacao instantanea sob demanda com micropagamentos x402 em USDC na rede Base.

---

## 2. Objetivos

- **Objetivo 1:** Disponibilizar o endpoint `/api/v1/indicators` fornecendo Taxa Selic meta, Selic efetiva diária, taxa CDI anual/diaria e IPCA acumulado (12 meses e mensal) com latencia de resposta menor que 80ms para dados em cache.
- **Objetivo 2:** Disponibilizar o endpoint `/api/v1/holidays` fornecendo calendario oficial de feriados nacionais e bancarios com suporte a parametro de ano (`?year=AAAA`) e identificacao de dias uteis.
- **Objetivo 3:** Integrar ambos os servicos ao protocolo x402 com amostra gratuita (teaser data no HTTP 402) revelando dados parciais e liberando dados completos mediante comprovante de 0.0005 USDC na Base ou token `demo` no Modo Sandbox.
- **Objetivo 4:** Atualizar todo o ecossistema consumidor: OpenAPI 3.1, SDK Node.js (v2.3.0), SDK Python (v2.2.0) e Servidor MCP (v1.3.0).

---

## 3. Nao objetivos (fora do escopo)

- Nao contempla projecoes futuras de mercado (como boletim Focus completo) ou historicos retroativos superiores a 12 meses nesta versao.
- Nao contempla feriados municipais ou estaduais especificos (apenas feriados nacionais oficiais e do sistema bancario nacional).
- Nao contempla gravacao de dados em bancos de dados relacionais (persistencia estritamente Zero-DB com cache em memoria e arquivos transientes).

---

## 4. Publico-alvo e personas

1. **Agente Autonomo de IA (LangChain / CrewAI / AgentKit):** Consome a API programaticamente utilizando chaves transientes ou pagamentos on-chain para alimentar raciocinios financeiros e fluxos de trabalho M2M.
2. **Desenvolvedores Web3 / DeFi:** Precisam de um oraculo veloz de taxas brasileiras sem depender de oraculos centralizados de alto custo.
3. **Usuarios do Claude Desktop e Cursor (via MCP):** Fazem perguntas como "Qual e a taxa Selic hoje?" ou "Segunda-feira de Carnaval e feriado bancario?" e o agente responde consultando as ferramentas oficiais do Grid402.

---

## 5. Historias de usuario

- **US01:** Como agente autonomo de tesouraria, quero consultar os indicadores economicos oficiais do Brasil para atualizar a taxa de desconto de titulos em tempo real.
- **US02:** Como agente de agendamento de liquidacoes, quero consultar se uma data futura possui expediente bancario para programar transferencias PIX e liquidacoes de contratos apenas em dias uteis.
- **US03:** Como desenvolvedor testando o sistema, quero inspecionar a amostra gratuita no status HTTP 402 ou usar o Modo Sandbox (`demo`) sem gastar USDC para validar o schema da resposta.
- **US04:** Como usuario de IA no Claude Desktop ou Cursor, quero acionar as ferramentas `grid402_indicators` e `grid402_holidays` via MCP para obter dados oficiais instantaneamente.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|:---:|
| **RF01** | Criar servico `public_html/services/indicators.php` que consulta a API do Banco Central (Olinda / SGS) retornando Selic meta (% a.a.), Selic efetiva diária, taxa CDI (% a.a.), IPCA acumulado 12 meses e mensal, com data de vigencia e referencia. | **Must** |
| **RF02** | Implementar cache transiente de 1 hora para os indicadores economicos com mecanismo de fallback resiliente caso o upstream governamental oscile. | **Must** |
| **RF03** | Criar servico `public_html/services/holidays.php` com suporte a parametro opcional `?year=AAAA` (padrao ano corrente), calculando feriados nacionais fixos, feriados bancarios ANBIMA e feriados moveis (Carnaval, Sexta-feira Santa, Pascoa e Corpus Christi). | **Must** |
| **RF04** | Implementar cache transiente de 24 horas para o servico de feriados. | **Must** |
| **RF05** | Integrar ambos os servicos ao middleware de pagamento x402 (`public_html/x402.php` e `config.php`) cobrando a tarifa padrao de 0.0005 USDC na Base Mainnet. | **Must** |
| **RF06** | Implementar geracao de Amostra Gratuita (Teaser) no HTTP 402: para `indicators`, revelar Selic meta e data de referencia protegendo desdobramentos de CDI e IPCA; para `holidays`, revelar o proximo feriado a partir da data atual protegendo a lista completa do ano. | **Must** |
| **RF07** | Garantir suporte ao Modo Sandbox (`X-Payment: demo`) com cota de 20 chamadas gratuitas por hora por IP. | **Must** |
| **RF08** | Configurar rotas amigaveis no `.htaccess` (`/api/v1/indicators` e `/api/v1/holidays`). | **Must** |
| **RF09** | Atualizar a especificacao OpenAPI 3.1 (`public_html/openapi.json`), adicionando os dois endpoints com schemas de entrada e saida. | **Must** |
| **RF10** | Atualizar `llms.txt`, `sitemap.xml`, SEP-1649 Server Card e console interativo na homepage. | **Must** |
| **RF11** | Atualizar o SDK Node.js e TypeScript para a versao 2.3.0 adicionando `getIndicators()` e `getHolidays(year?)`. | **Must** |
| **RF12** | Atualizar o SDK Python para a versao 2.2.0 adicionando `get_indicators()` e `get_holidays(year=None)`. | **Must** |
| **RF13** | Atualizar o servidor MCP para a versao 1.3.0 registrando as ferramentas `grid402_indicators` e `grid402_holidays`. | **Must** |

---

## 7. Requisitos nao funcionais

- **Desempenho:** Latencia menor que 80ms para dados servidos a partir do cache transiente.
- **Disponibilidade e Resiliencia:** Se o upstream oficial do BCB ou BrasilAPI falhar, o servico deve servir o ultimo valor persistido em cache com flag `'stale': true` em vez de retornar erro 500 para o agente.
- **Zero-DB:** Nenhuma dependencia de banco de dados MySQL, PostgreSQL ou SQLite. Armazenamento puramente stateless e local em arquivos JSON isolados em `private_data/`.
- **Segurança:** Validacao estrita de tipos e sanitizacao de parametros (`year` como inteiro entre 1900 e 2100).
- **Compatibilidade:** Suporte hibrido ESM e CommonJS no SDK Node, tipagem Pydantic v2 no Python, e conformidade rigorosa com a especificacao MCP Model Context Protocol.
- **Semantica de Caracteres:** Zero ocorrencias do caractere e comercial (`&`) em textos, documentacoes, schemas e comentarios (adotando sempre `e` ou `and`).

---

## 8. Fluxos e UX

### Fluxo Principal 1: Consulta aos Indicadores Economicos
1. Agente envia `GET /api/v1/indicators` sem cabecalho de pagamento.
2. Servidor retorna `HTTP 402 Payment Required` com cabecalho `WWW-Authenticate: x402 ...` e payload teaser contendo a Taxa Selic meta e aviso dos campos protegidos (CDI e IPCA).
3. Agente inspeciona o teaser e envia transacao de 0.0005 USDC na Base para o endereco do oraculo (`0xa31c31069c43ac1c0075a93c325b0dcd7744356c`).
4. Agente repete a requisicao com `X-Payment: <hash_ou_demo>`.
5. Servidor valida o comprovante e responde com `HTTP 200 OK` contendo todos os indicadores calculados e consolidados.

### Fluxo Principal 2: Consulta aos Feriados Nacionais e Bancarios
1. Agente envia `GET /api/v1/holidays?year=2026`.
2. Sem pagamento, recebe `HTTP 402` com o proximo feriado mais proximo e instrucoes de desbloqueio.
3. Com `X-Payment: <hash_ou_demo>`, recebe a lista integral de todos os feriados nacionais, dias bancarios inoperantes e dias da semana com `is_banking_holiday: true/false`.

### Fluxo de Erro e Fallback
- Ano invalido (ex: `year=abc` ou `year=3500`): Retorna `HTTP 400 Bad Request` com codigo `InvalidYearParameter`.
- Falha no upstream do Banco Central: Recupera do cache transiente anterior com `"cached": true` e `"upstream_status": "degraded"`.

---

## 9. Metricas de sucesso

- **Taxa de Sucesso dos Endpoints:** > 99.5% de requisicoes resolvidas com sucesso (200 ou 402 com schema valido).
- **Tempo de Resposta Médio (p95):** < 100ms no LiteSpeed Hostinger.
- **Cobertura de Testes:** 100% dos novos metodos cobertos por testes unitarios no Node.js e Python.
- **Compatibilidade com Ferramentas de IA:** Validacao bem-sucedida das ferramentas no Claude Desktop e Cursor via `grid402-mcp`.

---

## 10. Riscos, dependencias e premissas

- **Risco:** Instabilidade temporaria na API Olinda / SGS do Banco Central.
  - *Mitigacao:* Cache transiente com retencao de 24 horas como fallback e valores de contingencia caso a API fique fora do ar no primeiro boot.
- **Risco:** Feriados moveis variam anualmente com base na data da Pascoa.
  - *Mitigacao:* Algoritmo matematico deterministico computado localmente (calculo de Meeus) para Pascoa, Carnaval, Sexta-feira Santa e Corpus Christi, sem dependencia exclusiva de servicos externos.
- **Premissa:** Micropagamentos permanecem liquidados em USDC na Base Mainnet (contrato `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`).

---

## 11. Questoes em aberto

- [x] Fontes de dados alinhadas: BCB Olinda / SGS e BrasilAPI com algoritmo local.
- [x] Tarifacao alinhada: 0.0005 USDC por consulta com amostra gratuita no 402.
- [x] Escopo alinhado: Hub PHP, OpenAPI, SDK Node.js, SDK Python e Servidor MCP.

