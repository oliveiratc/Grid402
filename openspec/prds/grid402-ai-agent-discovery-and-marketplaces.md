# PRD: Descoberta Autonoma para Agentes de IA e Indexacao em Marketplaces

**Status:** Em revisao  
**Data:** 2026-11-08  
**Autor:** Usuario + Agente Antigravity  

---

## 1. Contexto e problema
O Grid402 oferece microservicos de dados publicos brasileiros (CNPJ, CEP, Selic, CDI, PTAX, Feriados e Dias Uteis) e oraculo criptografico com cobranca pay-per-call via HTTP 402 (protocolo x402 em USDC na rede Base).

Atualmente, embora as APIs estejam operacionais, faltam manifestos padronizados de descoberta automatica (como `llms.txt`, `llms-full.txt`, `.well-known/mcp.json` e `ai-plugin.json`) e uma interface publica focada em desenvolvedores de agentes de IA (`/agents`). Como resultado:
1. Agentes autonomos (Coinbase AgentKit, AutoGen, CrewAI, LangChain) que leem o dominio nao possuem um arquivo `llms.txt` estruturado para sintetizar os endpoints e instrucoes de pagamento sem consumir tokens excessivos de raspagem HTML.
2. Clientes Model Context Protocol (MCP) como Claude Desktop e Cursor precisam de um manifesto legivel por maquina para mapeamento direto de ferramentas.
3. Marketplaces globais de agentes (Agentic Market, PayAPI Market, Smithery e Glama) exigem metadados precisos e pre-formatados para indexacao oficial.

Resolver essa lacuna estabelece o Grid402 como a fonte referencial de dados brasileiros para a economia dos agentes de inteligencia artificial.

---

## 2. Objetivos
- Fornecer arquivos de contexto legiveis por LLMs (`llms.txt` e `llms-full.txt`) na raiz publica do dominio, com instrucoes claras de x402 e esquemas dos endpoints.
- Disponibilizar manifestos padronizados em `/.well-known/mcp.json` e `/.well-known/ai-plugin.json` com definicao completa das ferramentas e schemas JSON.
- Criar uma pagina publica `/agents` no visual terminal do Grid402 com guias faceis de copiar para Claude Desktop, scripts Python e comandos cURL.
- Elaborar um guia de submissao (`docs/AGENT_MARKETPLACE_SUBMISSIONS.md`) com todos os textos, descricoes, tags e metadados prontos para cadastro imediato no Agentic Market, PayAPI, Smithery e Glama.
- Garantir que todos os arquivos de descoberta tenham CORS aberto (`Access-Control-Allow-Origin: *`) e tempos de resposta inferiores a 20 ms.

---

## 3. Nao objetivos (fora do escopo)
- Alterar a logica interna dos endpoints de dados ou do motor de oraculo criptografico existente.
- Desenvolver bots proprietarios para realizar cadastros automatizados em sites de terceiros que exigem login social ou aprovacao humana manual.
- Modificar tabelas ou introduzir qualquer banco de dados relacional (manter estritamente a arquitetura Zero-DB).

---

## 4. Publico-alvo e personas
1. **Engenheiro de Agentes de IA:** Desenvolve fluxos com LangChain, CrewAI ou Coinbase AgentKit e busca APIs prontas para x402 que possam ser contratadas e pagas por carteiras Web3 sem contas ou chaves de API estaticas.
2. **Usuario de Claude Desktop e Cursor:** Busca adicionar ferramentas de dados do Brasil ao seu assistente via protocolo MCP com uma configuracao simples de 1 clique.
3. **Curador de Marketplace de IA:** Avalia APIs nos diretorios Agentic Market e PayAPI Market para listar servicos confiaveis com oraculos assinados.

---

## 5. Historias de usuario
- **US01:** Como um agente autonomo de IA, quero acessar `https://orangered-jackal-900744.hostingersite.com/llms.txt` para entender rapidamente quais dados o Grid402 disponibiliza, quanto custam e como pagar via HTTP 402.
- **US02:** Como um desenvolvedor de IA, quero acessar `/.well-known/mcp.json` para importar as definicoes de ferramentas (tools) no meu servidor MCP sem precisar escrever schemas na mao.
- **US03:** Como um desenvolvedor humano, quero visitar `/agents` no meu navegador para copiar a configuracao JSON pronta do Claude Desktop e testar uma chamada x402 em Python.
- **US04:** Como mantenedor do Grid402, quero consultar o guia de submissao para registrar o servico no Agentic Market e PayAPI com dados tecnicos validados.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
| :--- | :--- | :--- |
| **RF01** | Disponibilizar `public_html/llms.txt` com resumo executivo dos endpoints, precos em USDC na Base, cabecalho HTTP 402 e links de aprofundamento | Must |
| **RF02** | Disponibilizar `public_html/llms-full.txt` com documentacao tecnica completa, especificacao do cabecalho `_oracle_proof` e schemas de entrada e saida | Must |
| **RF03** | Disponibilizar `public_html/.well-known/mcp.json` contendo a lista estruturada de ferramentas MCP do Grid402, parametros e endpoints de execucao | Must |
| **RF04** | Disponibilizar `public_html/.well-known/ai-plugin.json` no padrao de manifesto de plugins de IA | Must |
| **RF05** | Criar a rota e pagina `/agents` (`agents.php`) responsiva, no estilo terminal do Grid402, contendo exemplos copiados para Claude Desktop, Python e cURL | Must |
| **RF06** | Adicionar atalho visivel para `/agents` e `llms.txt` no menu ou rodape do site (`index.php`) | Must |
| **RF07** | Configurar o roteamento no `.htaccess` para servir `/agents` de forma amigavel e aplicar cabecalhos de CORS e MIME type adequados para arquivos `.txt` e `.json` | Must |
| **RF08** | Criar o documento `docs/AGENT_MARKETPLACE_SUBMISSIONS.md` com fichas de submissao preenchidas para Agentic Market, PayAPI, Smithery e Glama | Should |

---

## 7. Requisitos nao funcionais
- **Performance:** Todas as rotas de manifestos e a pagina `/agents` devem responder em menos de 20 ms sob carga normal.
- **Zero-DB:** Todos os manifestos e dados exibidos devem ser arquivos estaticos ou gerados via PHP nativo sem consultas a banco de dados.
- **Segurança e CORS:** Arquivos `.txt` e `.json` devem expor `Access-Control-Allow-Origin: *` para permitir leitura por scrapers e agentes de qualquer origem web.
- **Compatibilidade e Acessibilidade:** A pagina `/agents` deve seguir HTML5 semantico, contraste visual adequado e layout responsivo para desktop e dispositivos moveis.
- **Padroes de Codigo:** Ausencia estrita do caractere comercial ampersand em todo o codigo, textos e documentacao.

---

## 8. Fluxos e UX
1. **Fluxo do Agente Autonomo (Machine-to-Machine):**
   * Agente faz requisicao `GET /llms.txt`.
   * Servidor responde `HTTP 200 text/plain; charset=utf-8` com lista de servicos e precos.
   * Agente requisita o endpoint desejado (ex: `/api/v1/cnpj?cnpj=00000000000191`).
   * Servidor responde `HTTP 402 Payment Required` com instrucoes x402.
   * Agente liquida o micropagamento e recebe os dados assinados com `_oracle_proof`.

2. **Fluxo do Desenvolvedor Humano (Browser):**
   * Usuario entra em `https://orangered-jackal-900744.hostingersite.com/agents`.
   * Visualiza a introducao do Grid402 para Agentes de IA e os 3 blocos interativos:
     * *Bloco 1:* Claude Desktop MCP Config (botao copiar com 1 clique).
     * *Bloco 2:* Python Agent Snippet (exemplo funcional com carteira Base).
     * *Bloco 3:* Catalogo de Endpoints e Precos com comando cURL.
   * Acessa os links diretos para `llms.txt`, `OpenAPI JSON` e `Verificador de Oraculo`.

---

## 9. Metricas de sucesso
- 100% de sucesso (HTTP 200) nas requisicoes automatizadas para:
  * `/llms.txt`
  * `/llms-full.txt`
  * `/.well-known/mcp.json`
  * `/.well-known/ai-plugin.json`
  * `/agents`
- Cabecalho `Access-Control-Allow-Origin: *` presente nas respostas de arquivos de descoberta.
- Guia de submissao cobrindo no minimo 4 plataformas de agentes e diretorios de IA.
- Aprovacao sem erros na suite de testes automatizados PHP.

---

## 10. Riscos, dependencias e premissas
- **Risco:** Rejeicao ou demora de aprovacao manual nos marketplaces de terceiros (Agentic Market, Smithery).
  * *Mitigacao:* Os manifestos seguem estritamente as especificacoes abertas oficiais (`llmstxt.org` e padrao Model Context Protocol), permitindo adocao direta mesmo antes de listagens formais.
- **Dependencia:** O servidor Apache/LiteSpeed deve manter as diretivas de reescrita e liberacao de arquivos estaticos preservadas.
- **Premissa:** As taxas de cobranca em USDC e enderecos da carteira oraculo permanecem sincronizados com o `config.php`.

---

## 11. Questoes em aberto
- Nenhuma questao impeditiva no momento. Todas as decisoes de escopo foram alinhadas na etapa de Grill-me.

