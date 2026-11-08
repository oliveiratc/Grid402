# PRD: Sincronizacao de SDKs e Coinbase AgentKit (v2.5.0)

**Status:** Em revisao  
**Data:** 2026-11-08  
**Autor:** Usuario + Agente Antigravity  

---

## 1. Contexto e problema
O Grid402 evoluiu seu catalogo de microservicos com dados publicos e financeiros do Brasil (Indicadores Selic e CDI, Feriados Nacionais e Bancarios ANBIMA, Calculadora de Dias Uteis) e oraculo criptografico com validacao de assinaturas ECDSA secp256k1 (`_oracle_proof`).

Entretanto, as ferramentas do ecossistema de desenvolvedores estao com niveis de atualizacao divergentes:
1. O servidor local Model Context Protocol (`grid402-mcp`) possui 11 ferramentas, faltando a ferramenta de auditoria `grid402_verify`.
2. As integracoes oficiais com o **Coinbase AgentKit** em `integrations/agentkit/` (TypeScript e Python) possuem apenas os utilitarios iniciais e ainda apontam para o endpoint legado em vez do servidor de producao ativo.
3. O pacote **Python SDK** em `python/` esta na versao `2.4.0` e necessita de sincronizacao de versao com o Node SDK (`2.5.0`) e geracao de novos artefatos compativeis.

Resolver essas divergencias garante uma experiencia consistente e confiavel para desenvolvedores e agentes autonomos que utilizam bibliotecas prontas em qualquer linguagem.

---

## 2. Objetivos
- Alcançar paridade total de recursos em todas as bibliotecas clientes e provedores de agentes (versao 2.5.0 para SDKs e 1.6.0 para MCP).
- Incluir a ferramenta de auditoria de oraculo criptografico (`grid402_verify`) no servidor MCP e no Coinbase AgentKit.
- Atualizar os provedores do Coinbase AgentKit (`grid402_action_provider.ts` e `grid402_action_provider.py`) com os servicos de indicadores, feriados, dias uteis e oraculo.
- Atualizar a URL padrao dos clientes para o endpoint oficial de producao (`https://orangered-jackal-900744.hostingersite.com`).
- Compilar os novos artefatos de distribuicao local do Python (wheel e sdist).
- Garantir 100% de aprovacao em todas as suites de testes automatizados.

---

## 3. Nao objetivos (fora do escopo)
- Publicar credenciais secretas ou realizar upload remoto no PyPI e npm publicos (os scripts de publicacao `publish_*.ps1` ficarao prontos para execucao manual pelo mantenedor com suas chaves).
- Modificar o backend PHP do servidor (que ja esta estavel e auditado em producao).

---

## 4. Publico-alvo e personas
1. **Desenvolvedor de Agentes Autonomos com Coinbase AgentKit:** Utiliza agentes CDP em TypeScript ou Python e deseja contratar dados brasileiros e liquidar micropagamentos na rede Base automaticamente.
2. **Usuario de Claude Desktop e Cursor:** Conecta o servidor local `grid402-mcp` para auditar empresas, indicadores e provas criptograficas diretamente no chat.
3. **Engenheiro de Dados Python:** Utiliza `grid402` com Pydantic para pipelines de enriquecimento de dados e oraculos descentralizados.

---

## 5. Historias de usuario
- **US01:** Como um agente Claude Desktop conectado via MCP, quero invocar `grid402_verify` passando uma resposta da API para confirmar matematicamente que o oraculo assinou os dados.
- **US02:** Como um agente Coinbase AgentKit em TypeScript, quero chamar `queryIndicators()` para obter as taxas Selic e CDI oficiais do Banco Central com liquidacao automatica via carteira CDP.
- **US03:** Como um agente Coinbase AgentKit em Python, quero chamar `query_workdays()` para calcular prazos financeiros em dias uteis ANBIMA.
- **US04:** Como um desenvolvedor Python, quero instalar ou importar `grid402` versao 2.5.0 com todos os novos metodos tipados e contratos sincronizados.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
| :--- | :--- | :--- |
| **RF01** | Adicionar a ferramenta `grid402_verify` em `mcp/tools.mjs` e atualizar `mcp/package.json` para v1.6.0 | Must |
| **RF02** | Atualizar `integrations/agentkit/grid402_action_provider.ts` com `queryIndicators`, `queryHolidays`, `queryWorkdays` e `verifyProof` | Must |
| **RF03** | Atualizar `integrations/agentkit/grid402_action_provider.py` com `query_indicators`, `query_holidays`, `query_workdays` e `verify_proof` | Must |
| **RF04** | Atualizar o endpoint padrao dos Action Providers para `https://orangered-jackal-900744.hostingersite.com` | Must |
| **RF05** | Atualizar a versao do Python SDK para `2.5.0` em `python/pyproject.toml` e `python/grid402/__init__.py` | Must |
| **RF06** | Atualizar documentacao em `integrations/agentkit/README.md` e `mcp/README.md` | Must |
| **RF07** | Compilar e gerar novos arquivos de distribuicao em `python/dist/` (wheel e tar.gz) | Should |
| **RF08** | Criar e rodar testes de regressao para MCP e AgentKit validando as novas acoes | Must |

---

## 7. Requisitos nao funcionais
- **Paridade Estrutural:** As chamadas nos SDKs e Action Providers devem manter paridade semantica e tipagem estrita.
- **Compatibilidade Retroativa:** Nenhuma assinatura de metodo preexistente deve ser quebrada.
- **Performance:** As chamadas de oraculo local devem validar assinaturas secp256k1 em menos de 10 ms.
- **Segurança:** Nunca expor chaves privadas de carteira em logs ou excecoes.
- **Padroes de Codigo:** Ausencia estrita do caractere comercial ampersand em todo o codigo, testes e documentacao.

---

## 8. Fluxos e UX
1. **Fluxo do MCP com Ferramenta de Verificacao:**
   * O cliente MCP chama `grid402_cnpj` e recebe os dados com `_oracle_proof`.
   * Em seguida, o agente invoca `grid402_verify` passando o payload recebido.
   * O servidor valida os dados e retorna `valid: true` com a atestacao da carteira oficial.

2. **Fluxo do Coinbase AgentKit com Novos Servicos:**
   * O agente recebe a missao de calcular um prazo financeiro no Brasil.
   * Executa a acao `query_workdays(start_date="2026-01-01", end_date="2026-12-31")`.
   * Se a chamada exigir pagamento, a carteira CDP transfere 0.0005 USDC na Base e retorna os dados com a contagem de dias uteis ANBIMA.

---

## 9. Metricas de sucesso
- 100% de sucesso na suite de testes do servidor MCP (`mcp/test_mcp.mjs`) com 12 ferramentas operantes.
- 100% de sucesso na suite de testes unitarios do Python SDK (`python/tests/test_sdk.py`).
- 100% de sucesso na suite de testes do Node SDK (`sdk/node/test_sdk.mjs`).
- Novos pacotes `grid402-2.5.0` gerados em `python/dist/`.

---

## 10. Riscos, dependencias e premissas
- **Risco:** Incompatibilidade com versoes antigas do SDK do Model Context Protocol.
  * *Mitigacao:* O servidor MCP utiliza a versao oficial `@modelcontextprotocol/sdk` v1.6.0 com schemas estritos em Zod e tipos padronizados.
- **Dependencia:** O ambiente de testes requer Node.js >= 18 e Python >= 3.10.
- **Premissa:** As taxas em USDC permanecem alinhadas com as configuracoes do backend (0.0005 USDC padrao e 0.0020 USDC para CNPJ).

---

## 11. Questoes em aberto
- Nenhuma questao impeditiva. Todas as decisoes foram alinhadas na etapa de Grill-me.

