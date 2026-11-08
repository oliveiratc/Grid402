# PRD: Distribuição Global via Smithery.ai e Integração Coinbase AgentKit

**Status:** Em revisão
**Data:** 2026-11-08
**Autor:** Thiago Campos de Oliveira + Antigravity

---

## 1. Contexto e Problema
O Grid402 possui agora uma infraestrutura completa de monetização e testes: micro-serviços estáveis, auto-liquidação via x402 na Base, amostra cadastral no desafio 402 e modo sandbox gratuito para homologação.

Contudo, para maximizar o volume de consultas pagas e adoção, é necessário disponibilizar o protocolo diretamente nos dois canais de maior concentração de desenvolvedores de agentes autônomos:
1. **Smithery.ai (Registro MCP Global):** O maior diretório público de MCP Servers, utilizado por desenvolvedores do Claude Desktop, Cursor, Windsurf e Zed para instalação em 1 clique.
2. **Coinbase Developer Platform (CDP) AgentKit:** O ecossistema oficial da Base e da Coinbase para agentes autônomos dotados de carteiras on-chain com saldo em USDC.

Sem essa distribuição padronizada, desenvolvedores precisam configurar manualmente cabeçalhos e scripts, elevando o tempo de onboarding de 30 segundos para mais de 15 minutos.

---

## 2. Objetivos
1. Disponibilizar a instalação automatizada do servidor MCP do Grid402 em 1 clique via CLI do Smithery (`npx -y @smithery/cli install grid402-mcp`).
2. Criar o **Grid402 Action Provider** para o Coinbase AgentKit em TypeScript e Python, permitindo que agentes da Coinbase realizem consultas com auto-liquidação na Base em menos de 5 linhas de código.
3. Expor as 7 ferramentas do hub (CNPJ, CEP, Câmbio, Cripto, DNS, SSL e ASN) diretamente para agentes construídos em LangChain, CrewAI e ElizaOS.

---

## 3. Não Objetivos (Fora do Escopo)
- Não recriar a lógica do AgentKit da Coinbase (utilizamos as interfaces oficiais de Action Provider da Coinbase SDK).
- Não alterar as regras de tarifação do Grid402 (0.0005 USDC para rede/mercado e 0.0020 USDC para CNPJ).
- Não criar custódia centralizada de chaves privadas (a assinatura continua delegada à carteira do próprio agente).

---

## 4. Público-alvo e Personas
1. **Criadores de Agentes de IA na Base (Coinbase AgentKit):** Desenvolvedores que usam o CDP AgentKit para criar robôs autônomos de enriquecimento cadastral, monitoramento de infraestrutura e trading.
2. **Usuários de Claude Desktop e Cursor:** Engenheiros que utilizam o Smithery para plugar ferramentas externas aos seus ambientes de desenvolvimento assistido por IA.

---

## 5. Histórias de Usuário
- **Como usuário do Claude Desktop**, quero rodar `npx -y @smithery/cli install grid402-mcp --client claude` para ter todas as ferramentas do Grid402 disponíveis instantaneamente no meu assistente.
- **Como desenvolvedor do Coinbase AgentKit**, quero importar `grid402ActionProvider()` no meu agente para que ele pague tarifas de 0.0005 USDC com a carteira da Coinbase automaticamente ao consultar dados.

---

## 6. Requisitos Funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| **RF01** | Criar o manifesto `smithery.yaml` na raiz do projeto com especificação do comando de inicialização `npx -y grid402-mcp` e esquema de configuração (`privateKey`, `sandbox` e `apiUrl`). | Must |
| **RF02** | Implementar o `Grid402ActionProvider` em TypeScript (`integrations/agentkit/grid402_action_provider.ts`) compatível com `@coinbase/agentkit` expondo as 7 ações com auto-liquidação via CDP Wallet Provider. | Must |
| **RF03** | Implementar o `Grid402ActionProvider` em Python (`integrations/agentkit/grid402_action_provider.py`) compatível com `cdp-agentkit-core` para ecossistemas Python (LangChain e CrewAI). | Must |
| **RF04** | Fornecer documentação executável e exemplos prontos em `integrations/agentkit/README.md` demonstrando a inicialização de um agente autônomo com carteira CDP. | Must |
| **RF05** | Atualizar a Landing Page do portal com o comando de 1 clique do Smithery e instruções de integração para o AgentKit da Coinbase. | Should |

---

## 7. Requisitos Não Funcionais
1. **Segurança:** Chaves privadas nunca são trafegadas ou armazenadas pelo Grid402. O Action Provider utiliza os métodos de assinatura segura do próprio SDK da Coinbase.
2. **Compatibilidade Multi-Linguagem:** O Action Provider deve funcionar nativamente em Node.js (>= 18) e Python (>= 3.10).
3. **Resiliência:** Em caso de falha de conexão na testnet ou mainnet, o provedor deve reportar mensagens amigáveis de depuração para o agente de IA.

---

## 8. Arquitetura do Provedor Coinbase AgentKit

```
[Agente Autônomo (LangChain / CrewAI)]
                 │
                 ▼
     [Grid402 Action Provider]
                 │
  ┌──────────────┴──────────────┐
  │ 1. Consulta Inicial (HTTP)  │
  └──────────────┬──────────────┘
                 ▼
         [Recebe HTTP 402]
                 │
  ┌──────────────┴──────────────┐
  │ 2. CDP Wallet Provider      │
  │    Assina Transferência     │
  │    de USDC na Base          │
  └──────────────┬──────────────┘
                 ▼
  ┌──────────────┴──────────────┐
  │ 3. Reenvio com X-Payment: tx│
  └──────────────┬──────────────┘
                 ▼
     [HTTP 200: Dados Entregues]
```

---

## 9. Métricas de Sucesso
- Presença e verificação no diretório oficial do Smithery.ai.
- Tempo de integração de um agente autônomo reduzido para menos de 2 minutos.
- Zero vazamentos de chaves ou incidentes de segurança.

---

## 10. Riscos, Dependências e Premissas
- **Dependência:** Disponibilidade dos SDKs oficiais da Coinbase (`@coinbase/agentkit` e `cdp-agentkit-core`).
- **Premissa:** Agentes executando na Base Mainnet possuem saldo mínimo em USDC e frações de ETH para gas da transação.

