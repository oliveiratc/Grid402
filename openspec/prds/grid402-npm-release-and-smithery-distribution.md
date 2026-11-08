# PRD: Publicação do Pacote npm v1.2.0 e Distribuição no Smithery.ai (Grid402)

**Status:** Em revisão  
**Data:** 2026-11-08  
**Autor:** oliveiratco1 + Antigravity  

---

## 1. Contexto e Problema

O servidor MCP oficial do Grid402 foi inicialmente publicado no registro público do npm na versão `1.1.0`. Desde então, o ecossistema evoluiu com a entrega de duas frentes críticas:
1. **Consulta Cadastral de CNPJ da Receita Federal:** Ferramenta `grid402_cnpj` com tarifa de 0.0020 USDC e validação de dígito verificador módulo 11.
2. **Modo Sandbox Gratuito de Testes:** Suporte à variável `GRID402_SANDBOX=true` (ou cabeçalho `X-Payment: demo`), permitindo que desenvolvedores e agentes testem as 8 ferramentas sem gastar USDC e sem necessidade de chave privada EVM.

Atualmente, qualquer desenvolvedor que instale o servidor via comando Smithery (`npx -y @smithery/cli install grid402-mcp --client claude`) ou Cursor baixa a versão `1.1.0`, ficando sem acesso à ferramenta de CNPJ e ao Modo Sandbox.

Resolver esse problema agora garante paridade absoluta entre a infraestrutura em produção na Hostinger, o pacote global no npm e os catálogos de IA.

---

## 2. Objetivos

- **O1:** Atualizar e publicar a versão `1.2.0` do pacote `grid402-mcp` no registro público do npm.
- **O2:** Expor integralmente as **8 ferramentas oficiais** no servidor MCP: `grid402_summary`, `grid402_forex`, `grid402_crypto`, `grid402_dns`, `grid402_ssl`, `grid402_asn`, `grid402_cep` e `grid402_cnpj`.
- **O3:** Habilitar suporte declarativo ao Modo Sandbox gratuito via variável `GRID402_SANDBOX=true`, eliminando a obrigatoriedade de saldo em carteira para testes locais e pipelines CI/CD.
- **O4:** Garantir compatibilidade total do binário com o manifesto `smithery.yaml` e clientes MCP (Claude Desktop e Cursor).

---

## 3. Não Objetivos (Fora do Escopo)

- **Distribuição no PyPI (Python):** O empacotamento para o registro Python permanece reservado para um ciclo posterior.
- **Alterações no Backend PHP:** A API na Hostinger já suporta 100% dos fluxos e endpoints; nenhum ajuste de servidor backend é necessário.
- **Custódia ou Armazenamento de Chaves:** O pacote MCP nunca armazena nem envia chaves privadas para terceiros (todo processamento criptográfico ocorre na máquina local do usuário).

---

## 4. Público-Alvo e Personas

1. **Desenvolvedores Claude Desktop e Cursor:** Usuários que desejam adicionar ferramentas de rede e dados públicos aos seus assistentes em 1 clique via CLI do Smithery.
2. **Engenheiros de IA e Automação:** Desenvolvedores criando fluxos em CrewAI ou LangChain que precisam testar o MCP localmente em Modo Sandbox antes de provisionar saldo de produção.
3. **Agentes de IA em Ambientes de CI/CD:** Ambientes automatizados que consomem dados do Grid402 sem intervenção humana.

---

## 5. Histórias de Usuário

- **US01:** Como desenvolvedor utilizando Claude Desktop, quero instalar o pacote via Smithery e ter acesso imediato às 8 ferramentas (incluindo CNPJ) sem erros de versão desatualizada.
- **US02:** Como engenheiro sem carteira Web3 configurada, quero definir `GRID402_SANDBOX: "true"` na configuração do MCP e realizar consultas simuladas com sucesso.
- **US03:** Como integrador de sistemas, quero que o pacote npm seja leve, seguro e contenha apenas os arquivos necessários para execução rápida do binário.

---

## 6. Requisitos Funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | **Atualização de Versão e Metadados do npm** | Atualizar `package.json` para a versão `1.2.0`, incluindo keywords de CNPJ, Sandbox e descrição atualizada. | Must |
| **RF02** | **Otimização de Empacotamento (`files`)** | Definir o campo `files` em `package.json` limitando a distribuição estritamente a `index.mjs`, `tools.mjs`, `README.md` e `package.json`. | Must |
| **RF03** | **Validação de Ferramentas e Schemas** | Garantir que o comando de teste do MCP valide todas as 8 ferramentas e responda aos schemas esperados pelo protocolo. | Must |
| **RF04** | **Publicação Oficial no Registro npm** | Executar a publicação da versão `1.2.0` no npm com acesso público utilizando a conta autenticada `oliveiratco1`. | Must |
| **RF05** | **Verificação Pós-Publicação** | Verificar que `npm view grid402-mcp version` reporta `1.2.0` e que a instalação via `npx` resolve o binário corretamente. | Must |

---

## 7. Requisitos Não Funcionais

- **RNF01 (Tamanho e Eficiência):** O pacote compactado (`.tgz`) não deve exceder 30 KB no npm.
- **RNF02 (Tempo de Inicialização):** O processo `grid402-mcp` deve inicializar e declarar seus recursos via `stdio` em menos de 800 ms.
- **RNF03 (Segurança Criptográfica):** Nenhuma chave privada ou segredo pode ser empacotado no tarball do npm.
- **RNF04 (Linguagem e Redação):** Ausência total do caractere 'e comercial' em toda documentação, textos de package e saídas de console.

---

## 8. Fluxos e UX

### 8.1. Fluxo de Instalação e Execução via Smithery
```text
1. Desenvolvedor executa:
   npx -y @smithery/cli install grid402-mcp --client claude

2. Smithery consulta registro npm, baixa grid402-mcp@latest (1.2.0).
3. Injeta configuração no claude_desktop_config.json:
   "grid402": {
     "command": "npx",
     "args": ["-y", "grid402-mcp"],
     "env": { "GRID402_SANDBOX": "true" }
   }
4. Claude Desktop inicia o servidor e expõe as 8 ferramentas.
```

### 8.2. Fluxo de Publicação pelo Mantenedor
```text
1. Validação local dos testes de conformidade (node mcp/test_mcp.mjs).
2. Empacotamento e inspeção de integridade (npm pack --dry-run).
3. Publicação pública no registro (npm publish --access public).
4. Confirmação da versão ativa via npm registry API.
```

---

## 9. Métricas de Sucesso

- **M01:** Comando `npm view grid402-mcp version` retorna `1.2.0`.
- **M02:** 100% dos testes do servidor MCP aprovados (`test_mcp.mjs`).
- **M03:** Instalação remota simulada via `npx grid402-mcp` carrega com sucesso as 8 ferramentas.

---

## 10. Riscos, Dependências e Premissas

- **P01:** A conta npm `oliveiratco1` está ativa e com credenciais válidas no terminal.
- **R01 (Conflito de Versão):** O npm rejeita publicação se a versão `1.2.0` já existir.  
  *Mitigação:* Verificado previamente que a versão mais recente publicada é a `1.1.0`.
- **R02 (Propagação de CDN npm):** Pode haver latência de até 2 minutos para que novas tags sejam resolvidas globalmente por espelhos de CDN.

---

## 11. Questões em Aberto

*Nenhuma no momento. O escopo e método de publicação foram alinhados e aprovados na etapa de Grill-me.*
