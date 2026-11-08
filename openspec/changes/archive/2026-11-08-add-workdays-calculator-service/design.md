# Design: Calculadora de Dias Uteis Financeiros ANBIMA

## Context

O Grid402 opera como um hub de utilitarios de rede e dados publicos em PHP 8.x sob arquitetura Zero-DB e Stateless hospedado na Hostinger. A plataforma ja dispoe do modulo `public_html/services/holidays.php` capaz de calcular feriados moveis (algoritmo Meeus e Gauss) e feriados fixos nacionais e bancarios para qualquer ano entre 1900 e 2100.
Veja `proposal.md` para a motivacao do negocio e `specs/` para os requisitos de comportamento.

## Goals / Non-Goals

**Goals:**
- Implementar algoritmo stateless de computacao de dias uteis em `public_html/services/workdays.php` reutilizando os feriados bancarios de `services/holidays.php`.
- Suportar 3 modos unificados: contagem de intervalo (padrao ANBIMA D+0 exclusivo no inicio e inclusivo no fim), projecao de vencimento (deslocamento de N dias uteis) e checagem pontual de dia util.
- Implementar emissao de teaser parcial no desafio HTTP 402 com status da data base e proximo feriado bancario.
- Assegurar tempo de resposta em servidor inferior a 10ms por chamada sem I/O de disco.
- Integrar os metodos correspondentes nos SDKs Node.js, Python e no servidor MCP com suporte a Sandbox e x402 on-chain na Base.

**Non-Goals:**
- Nao armazenar historico de calculos em banco de dados ou arquivos em disco.
- Nao incluir feriados internacionais ou tabelas de juros e correcao monetaria.

## Decisions

### Decisao 1: Reutilizacao direta do motor de feriados em memoria
- **Escolha:** Importar e invocar as funcoes `grid402_get_holidays($year)` de `services/holidays.php` mantendo cache estatico em memoria durante a requisicao para os anos percorridos.
- **Alternativas consideradas:**
  - Manter uma tabela fixa de datas uteis em arquivo JSON: Descartada pois aumentaria o tamanho do pacote e exigiria manutencao periodica de arquivos de dados.
- **Justificativa:** O calculo Meeus e Gauss e puramente matematico e executa em micro-segundos na CPU, preservando a filosofia Zero-DB.

### Decisao 2: Convencao financeira ANBIMA para contagem de intervalo
- **Escolha:** Adotar a convencao de mercado brasileiro: data de inicio e D+0 (nao conta como dia util decorrido) e data final e inclusiva (se for dia util, soma 1).
- **Alternativas consideradas:**
  - Contagem inclusiva em ambos os extremos: Descartada como padrao pois distorceria calculos de taxas de juros e prazos de liquidacao D+1 e D+2.
- **Justificativa:** Alinhamento 100% compativel com os manuais de operacoes da ANBIMA e B3.

### Decisao 3: Estrutura unificada do payload de resposta
- **Escolha:** Retornar payload normalizado com as chaves:
  - `mode`: "range", "projection" ou "check"
  - `start_date`: Data base inicial
  - `end_date`: Data final (no modo range)
  - `target_date`: Data calculada (no modo projection)
  - `workdays`: Quantidade de dias uteis
  - `calendar_days`: Dias corridos no intervalo
  - `is_workday`: Booleano no modo check
  - `day_type`: "workday", "weekend" ou "holiday"
  - `holidays`: Colecao de feriados bancarios que incidiram no intervalo
  - `tariff_usdc`: "0.0005"
- **Justificativa:** Previsibilidade e facilidade de consumo por agentes e LLMs.

### Decisao 4: Teaser no Desafio HTTP 402
- **Escolha:** Quando requisitado sem pagamento ou sandbox, emitir HTTP 402 com `preview` contendo:
  - `base_date`: Data de inicio informada
  - `is_workday`: Se a data base e dia util
  - `day_type`: Tipo do dia base
  - `next_holiday`: Proximo feriado bancario a partir da data base
  - `locked_fields`: Lista dos atributos bloqueados (workdays, target_date, calendar_days)

## Risks / Trade-offs

- **[Risco: Loop excessivo em projecoes gigantescas]** → Mitigado limitando projecoes a no maximo 5.000 dias uteis ou 20 anos, retornando HTTP 400 caso excedido.
- **[Risco: Inconsistencia entre anos bissextos]** → Mitigado utilizando `DateTimeImmutable` do PHP para operacoes cronologicas nativas com tratamento exato de bissextos.
- **[Risco: Conflito de versoes nos SDKs]** → Mitigado com bump semantico: Node v2.4.0, Python v2.3.0 e MCP v1.4.0.

## Migration Plan

1. Criar `public_html/services/workdays.php` com logica de calculo e teaser.
2. Atualizar despachante `public_html/index.php` adicionando a rota `/api/v1/workdays` e `/workdays`.
3. Atualizar catalogos de documentacao (`openapi.json`, `llms.txt`, `sitemap.xml`, console web e WebMCP).
4. Implementar metodos correspondentes em `sdk/node/`, `python/` e `mcp/`.
5. Executar suíte de testes em todas as frentes.
6. Gerar pacote de deploy e sincronizar na Hostinger.

