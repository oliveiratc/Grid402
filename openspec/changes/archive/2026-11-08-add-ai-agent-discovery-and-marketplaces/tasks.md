# Tarefas de Implementacao: Descoberta de Agentes e Marketplaces

## 1. Manifestos de Descoberta para LLMs e Agentes
- [x] 1.1 Criar `public_html/llms.txt` com resumo executivo de servicos, precos em USDC na Base e fluxo HTTP 402
- [x] 1.2 Criar `public_html/llms-full.txt` com documentacao tecnica exaustiva, schemas e verificacao de oraculo
- [x] 1.3 Criar diretorio `public_html/.well-known/` se nao existir e gerar `public_html/.well-known/mcp.json` com definicao formal de ferramentas
- [x] 1.4 Criar `public_html/.well-known/ai-plugin.json` no formato padrao de plugins de IA

## 2. Pagina Publica e Roteamento para Agentes
- [x] 2.1 Criar `public_html/agents.php` com interface dark terminal contendo snippets para Claude Desktop, Python e cURL
- [x] 2.2 Configurar `.htaccess` com regra de reescrita para `/agents` e cabecalhos CORS para manifestos
- [x] 2.3 Adicionar links para `/agents` e `/llms.txt` na barra de navegacao e rodape em `public_html/index.php`

## 3. Guias de Submissao em Marketplaces
- [x] 3.1 Criar `docs/AGENT_MARKETPLACE_SUBMISSIONS.md` com fichas preenchidas para Agentic Market, PayAPI, Smithery e Glama

## 4. Testes e Verificacao
- [x] 4.1 Criar script de teste `tests/test_agent_discovery.php` validando HTTP 200, CORS e sintaxe dos manifestos
- [x] 4.2 Executar a suite de testes e validar todos os cenarios das specs

