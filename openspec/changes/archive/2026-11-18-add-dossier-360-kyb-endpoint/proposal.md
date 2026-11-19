# Adicionar Endpoint Premium de Dossiê 360º KYB (`/api/v1/dossier`) e Ferramenta MCP

## Por quê
Agentes autônomos de IA e integrações M2M atualmente precisam realizar de 3 a 5 chamadas separadas (`/cnpj`, `/simples`, `/sanctions`, `/compliance`) para avaliar o risco de uma empresa brasileira, gerando latência extra e exigindo que o próprio LLM interprete dados brutos. Além disso, consultas atômicas possuem ticket médio baixo ($0.005 a $0.02 USDC).

Conforme aprovado no PRD [`openspec/prds/dossie-360-kyb-agentico.md`](../../prds/dossie-360-kyb-agentico.md), a criação de um endpoint composto de alto valor com veredito estruturado (`APPROVED`, `FLAGGED`, `REJECTED`) aumenta o ticket médio para **0.0500 USDC** (10 créditos de Session Pass) e entrega uma resposta pronta para tomada de decisão autônoma.

## O que muda
- Novo micro-serviço `public_html/services/dossier.php` consolidando Receita Federal (CNPJ), Simples Nacional/MEI, Sanções CGU (CEIS/CNEP) e Score 360 com cache Zero-DB atômico.
- Novo motor de veredito determinístico para IA (`verdict`: `status`, `score`, `alerts`, `summary`).
- Nova rota `GET /api/v1/dossier?cnpj={cnpj}` com cobrança HTTP x402 de **0.0500 USDC** (10 créditos no Session Pass), prévia gratuita (Teaser) no desafio 402, suporte a Modo Sandbox e assinatura criptográfica ECDSA secp256k1 (`_oracle_proof`).
- Nova ferramenta `grid402_get_dossier_360` no servidor MCP oficial (`mcp/tools.mjs` e `mcp/index.mjs`).
- Atualização de descoberta e documentação (`/status`, `llms.txt`, `index.php` e `README.md`).

## Fora do escopo
- Não inclui dados pessoais sensíveis (PII), CPFs ou quadro societário (QSA).
- Não realiza consultas a birôs privados fechados (Serasa/Boa Vista) nem certidões estaduais/municipais fragmentadas.

## Impacto
- **Specs afetadas:** `x402-payment-oracle`, `mcp-server`
- **Código afetado:** `public_html/services/dossier.php`, `public_html/config.php`, `public_html/index.php`, `public_html/x402.php`, `public_html/.htaccess`, `mcp/tools.mjs`, `mcp/index.mjs`, `tests/test_dossier.php`, `mcp/test_mcp.mjs`, `README.md`.
