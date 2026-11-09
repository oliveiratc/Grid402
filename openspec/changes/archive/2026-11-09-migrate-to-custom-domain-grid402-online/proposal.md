# Proposta: Migracao para Dominio Proprio grid402.online

## Por que
O Grid402 operava sob o subdominio gratuito `orangered-jackal-900744.hostingersite.com`. Com o registro oficial do dominio `grid402.online` na Hostinger, precisamos unificar toda a infraestrutura, certificados SSL, documentacao, oraculo e pacotes no novo dominio oficial, elevando a durabilidade e reputacao do servico perante crawlers e agentes de IA, alem de manter retrocompatibilidade via redirecionamento HTTP 301 para chamadas legadas.
Referencia: PRD em [`openspec/prds/custom-domain-migration-grid402-online.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/custom-domain-migration-grid402-online.md).

## O que muda
- Provisionamento do website `grid402.online` na Hostinger com SSL Let's Encrypt dedicado.
- `https://grid402.online` torna-se a raiz canonica oficial do oraculo, com `www` redirecionando para a raiz.
- O subdominio anterior (`orangered-jackal-900744.hostingersite.com`) passa a responder com redirecionamento HTTP 301.
- Atualizacao da constante `GRID402_CANONICAL_DOMAIN` e URLs em todos os metadados de descoberta (`openapi.json`, `llms.txt`, `llms-full.txt`, `mcp.json`, `ai-plugin.json` e `sitemap.xml`).
- Deploy sincronizado em producao com as credenciais do bot do Telegram preservadas.
- Atualizacao e publicacao do servidor MCP (`grid402-mcp@1.7.0`) e do SDK Node (`grid402@2.6.0`) no npm registry.
- Atualizacao do `smithery.yaml` e documentacao tecnica.

## Impacto
- Specs afetadas: `agent-discovery`, `mcp-server`, `node-sdk`
- Codigo afetado:
  - `public_html/config.php`
  - `public_html/.htaccess`
  - `public_html/openapi.json`
  - `public_html/llms.txt`
  - `public_html/llms-full.txt`
  - `public_html/.well-known/mcp.json`
  - `public_html/.well-known/ai-plugin.json`
  - `public_html/sitemap.xml`
  - `public_html/agents.php`
  - `mcp/package.json` e `mcp/src/index.ts`
  - `sdk/node/package.json` e `sdk/node/src/index.ts`
  - `smithery.yaml`

