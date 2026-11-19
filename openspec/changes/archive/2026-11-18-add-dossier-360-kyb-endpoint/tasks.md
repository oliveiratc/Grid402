# Tasks: Dossiê 360º KYB (`/api/v1/dossier`) e Ferramenta MCP

## 1. Serviço Core e Motor de Veredito (`dossier.php`)
- [x] 1.1 Criar `public_html/services/dossier.php` com validação matemática de CNPJ, cache atômico Zero-DB (`dossier_<cnpj>.json`, TTL 24h) e projeção estrita Zero PII (sem QSA/CPF).
- [x] 1.2 Implementar `grid402_dossier_compute_verdict()` com regras determinísticas para `APPROVED`, `FLAGGED` e `REJECTED`, alertas críticos e resumo executivo em português.
- [x] 1.3 Implementar `grid402_service_dossier()` consolidando `grid402_service_cnpj`, `grid402_service_simples`, `grid402_service_sanctions` e `grid402_service_compliance`.
- [x] 1.4 Implementar helper de prévia segura `grid402_teaser_dossier()` para o desafio HTTP 402.

## 2. Gateway x402, Precificação e Roteamento
- [x] 2.1 Registrar o serviço `'dossier'` em `public_html/config.php` com preço `0.0500` USDC (`amount_raw: 50000`, `credits: 10`), categoria `Compliance Premium` e rota `/api/v1/dossier`.
- [x] 2.2 Adicionar `'dossier'` em `public_html/index.php` (`$validApiEndpoints`, precificação 0.0500 USDC / 10 créditos no Session Pass, teaser 402, `case 'dossier':` com `_oracle_proof` e `_meta`, e entrada no catálogo/playground web).
- [x] 2.3 Atualizar `public_html/x402.php` com parâmetros padrão, `credits_cost`, alias `teaser_data` e descrição do serviço `'dossier'`.
- [x] 2.4 Verificar regras de rewrite em `public_html/.htaccess` e `tests/router.php` para garantir suporte a `/api/v1/dossier`.

## 3. Integração MCP (`grid402-mcp`) e Descoberta
- [x] 3.1 Adicionar a ferramenta `grid402_get_dossier_360` em `mcp/tools.mjs` e `mcp/index.mjs` (schema `cnpj`, descrição com custo `0.0500 USDC / 10 créditos` e handler chamando `/api/v1/dossier`).
- [x] 3.2 Atualizar o endpoint MCP remoto em PHP (`public_html/mcp.php`) para espelhar `grid402_get_dossier_360`.
- [x] 3.3 Atualizar documentação e descoberta de agentes (`README.md`, `public_html/llms.txt` e `public_html/.well-known/x402.json`).

## 4. Testes e Verificação
- [x] 4.1 Criar suíte `tests/test_dossier.php` cobrindo todos os cenários da spec: CNPJ inválido (400), HTTP 402 com preço `0.0500` USDC e Teaser, vereditos `APPROVED` / `FLAGGED` / `REJECTED`, débito de 10 créditos no Session Pass, Zero PII e assinatura `_oracle_proof`.
- [x] 4.2 Atualizar `mcp/test_mcp.mjs` para validar o schema e registro das 25 ferramentas incluindo `grid402_get_dossier_360`.
- [x] 4.3 Executar bateria completa de testes (PHP + MCP + Descoberta) e validar ausência de regressões.
