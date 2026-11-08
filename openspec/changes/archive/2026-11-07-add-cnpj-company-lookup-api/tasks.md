# Tarefas: Implementação da API de Consulta Cadastral de CNPJ

## 1. Configuração e Tarifação
- [x] 1.1 Configurar constante `GRID402_PRICE_CNPJ = '0.0020'` e arquivo de cache em `public_html/config.php`.
- [x] 1.2 Atualizar `public_html/x402.php` para aceitar parâmetro de preço dinâmico na emissão do desafio 402 e verificação on-chain.

## 2. Motor do Micro-Serviço de CNPJ
- [x] 2.1 Criar `public_html/services/cnpj.php` com função `grid402_service_cnpj($cnpj, $bypassCache = false)`.
- [x] 2.2 Implementar validação matemática estrita de módulo 11 para os dígitos verificadores e rejeição de sequências idênticas inválidas.
- [x] 2.3 Implementar consulta a provedor público aberto resiliente com fallback e sanitização de dados (descarte estrito de QSA para conformidade LGPD).
- [x] 2.4 Implementar persistência de cache atômico em JSON com TTL de 7 dias utilizando escrita com `LOCK_EX` fora da raiz pública.

## 3. Roteamento, Telemetria e Admin
- [x] 3.1 Integrar rota `/api/v1/cnpj` e `/api/cnpj` em `public_html/index.php` com validação de formato retornando HTTP 400 antes de emitir o desafio 402.
- [x] 3.2 Atualizar o registro de telemetria em `public_html/metrics.php` para computar a tarifa de $0.0020 USDC em consultas de CNPJ pagas.
- [x] 3.3 Atualizar o painel administrativo em `public_html/admin.php` para incluir `cnpj` na lista de micro-serviços e na consolidação de receita.

## 4. Interface Pública e Documentação de Agentes
- [x] 4.1 Adicionar card do serviço de CNPJ no catálogo e aba interativa no console de testes em `public_html/index.php`.
- [x] 4.2 Atualizar `public_html/llms.txt` documentando o novo endpoint, parâmetros e preço de 0.0020 USDC para agentes de IA.

## 5. Servidor MCP e SDKs
- [x] 5.1 Adicionar a ferramenta `grid402_cnpj` em `mcp/tools.mjs` com schema de validação e mapeamento de tarifa.
- [x] 5.2 Atualizar `mcp/x402_payer.mjs` para suportar valor dinâmico de transferência de 0.0020 USDC para CNPJ.
- [x] 5.3 Atualizar o SDK Node.js (`sdk/node/grid402.mjs` e `grid402.d.ts`) com o método `getCnpj(cnpj)`.
- [x] 5.4 Atualizar o SDK Python (`sdk/python/grid402.py`) com o método `get_cnpj(cnpj)`.

## 6. Testes e Verificação
- [x] 6.1 Criar suíte de testes `tests/test_cnpj.php` validando rejeição matemática de CNPJ inválido (400), desafio 402 a $0.0020, payload normalizado e cache de 7 dias.
- [x] 6.2 Atualizar `mcp/test_mcp.mjs` para verificar as 8 ferramentas registradas e o comportamento de desafio da ferramenta `grid402_cnpj`.
- [x] 6.3 Executar bateria completa de testes de regressão (SDKs, MCP e oráculo PHP).

## 7. Deploy e Homologação na Hostinger
- [x] 7.1 Empacotar a aplicação via `package_deploy.ps1`.
- [x] 7.2 Realizar o deploy no ambiente Hostinger via MCP `hostinger-hosting`.
- [x] 7.3 Validar a consulta ao vivo na URL pública da Hostinger.
