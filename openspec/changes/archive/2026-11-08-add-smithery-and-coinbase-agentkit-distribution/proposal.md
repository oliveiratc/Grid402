# Proposta: Distribuição Global via Smithery.ai e Integração Coinbase AgentKit

## Por quê
Conforme detalhado no PRD [`openspec/prds/grid402-smithery-and-coinbase-agentkit-distribution.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-smithery-and-coinbase-agentkit-distribution.md), para maximizar a conversão de consultas pagas do Grid402, precisamos disponibilizar o protocolo diretamente nos dois maiores ecossistemas de agentes de IA: o diretório global de MCP Servers Smithery.ai e o framework oficial Coinbase Developer Platform (CDP) AgentKit na rede Base.

## O que muda
- É adicionado o manifesto `smithery.yaml` na raiz do repositório para indexação no Smithery.ai e instalação em 1 clique em clientes como Claude Desktop, Cursor, Windsurf e Zed.
- É criada a pasta `integrations/agentkit/` contendo o `Grid402ActionProvider` em TypeScript e Python, compatível com `@coinbase/agentkit` e `cdp-agentkit-core`.
- O Action Provider mapeia as 7 ferramentas do Grid402 (CNPJ, CEP, Câmbio, Cripto, DNS, SSL e ASN) e executa o ciclo de auto-liquidação via carteira CDP do próprio agente.
- A Landing Page do portal web recebe um novo bloco de comandos de instalação em 1 clique do Smithery e guia para o AgentKit.

## Impacto
- **Specs afetadas:** `mcp-server`
- **Código afetado:**
  - `smithery.yaml`
  - `integrations/agentkit/grid402_action_provider.ts`
  - `integrations/agentkit/grid402_action_provider.py`
  - `integrations/agentkit/README.md`
  - `public_html/index.php`

