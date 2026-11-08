# Proposta: API de Consulta Cadastral de CNPJ (Pessoa Jurídica)

## Por quê
Conforme especificado no PRD (`openspec/prds/grid402-api-cnpj-receita-federal.md`), agentes de IA autônomos em fluxos de compras, faturamento e compliance precisam validar a existência e situação legal de empresas no Brasil sem depender de assinaturas burocráticas ou cartões de crédito. O Grid402 introduz o micro-serviço de **Consulta Cadastral de CNPJ** com dados de domínio público da Pessoa Jurídica a **$0.002 USDC** por consulta liquidada na rede Base Mainnet via protocolo HTTP 402, com entrega sub-100ms em cache local de 7 dias e conformidade estrita de Risco Zero sob LGPD.

## O que muda
- Novo micro-serviço em `public_html/services/cnpj.php` com validação matemática de dígitos verificadores (Módulo 11), upstream resiliente e cache local atômico em JSON (TTL de 7 dias).
- Roteamento em `public_html/index.php` para o endpoint `GET /api/v1/cnpj` e `/api/cnpj` com validação defensiva gratuita (rejeição 400 antes de cobrar) e emissão de desafio x402 de 0.002 USDC.
- Suporte a preço parametrizado em `public_html/x402.php` e `public_html/config.php` (`GRID402_PRICE_CNPJ = '0.0020'`).
- Inclusão do serviço `cnpj` no catálogo público, `llms.txt`, OpenAPI 3.1 e console interativo no portal `index.php`.
- Inclusão do serviço `cnpj` e métricas ponderadas a $0.002 no painel administrativo `public_html/admin.php`.
- Adição da ferramenta `grid402_cnpj` no servidor MCP oficial (`mcp/tools.mjs`, `mcp/x402_payer.mjs` e `mcp/index.mjs`) com suporte a liquidação automática de 0.002 USDC.
- Atualização do SDK Node.js (`sdk/node/grid402.mjs`) e Python (`sdk/python/grid402.py`) com os métodos `getCnpj()` e `get_cnpj()`.

## Impacto
- Specs afetadas:
  - `open-data-utilities` (inclusão dos cenários da API de CNPJ, validação módulo 11 e cache de 7 dias).
  - `mcp-server` (inclusão da ferramenta `grid402_cnpj` e liquidação x402 a $0.002).
- Código afetado:
  - `public_html/config.php`
  - `public_html/x402.php`
  - `public_html/services/cnpj.php` (novo)
  - `public_html/index.php`
  - `public_html/admin.php`
  - `public_html/llms.txt`
  - `mcp/tools.mjs`
  - `mcp/x402_payer.mjs`
  - `sdk/node/grid402.mjs` e `sdk/node/grid402.d.ts`
  - `sdk/python/grid402.py`
  - `tests/test_cnpj.php` (novo)

