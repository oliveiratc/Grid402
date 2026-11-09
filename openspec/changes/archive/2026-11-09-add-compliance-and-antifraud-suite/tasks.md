# Tarefas: Suite de Compliance e Antifraude

## 1. Backend e Camada de Serviços PHP
- [x] 1.1 Criar a classe `src/Grid402/Services/ComplianceService.php` com suporte a consultas aos cadastros CEIS/CNEP da CGU e Simples Nacional com cache indexado em SQLite.
- [x] 1.2 Implementar o algoritmo de cálculo de Compliance Score (0 a 100) com classificação em status (`APROVADO`, `ALERTA`, `REJEITADO`).
- [x] 1.3 Adicionar as rotas `/api/v1/sanctions`, `/api/v1/simples` e `/api/v1/compliance` no controlador principal de API (`public_html/index.php`).
- [x] 1.4 Configurar as regras de liquidação e desafios x402 com precificação escalonada (0.001 USDC para sanções e simples, 0.003 USDC para compliance score) e bypass com `X-Payment: demo`.

## 2. Testes Automatizados de Backend
- [x] 2.1 Criar suíte de testes unitários em `tests/ComplianceServiceTest.php` cobrindo cenários sem sanções, com sanções, optante do simples e cálculo de score.
- [x] 2.2 Executar os testes via PHPUnit ou script de asserções em terminal para validar 100% de sucesso.

## 3. Adaptadores de Agentes, SDKs e MCP
- [x] 3.1 Adicionar os métodos `querySanctions`, `querySimples` e `queryCompliance` em `integrations/agentkit/grid402_action_provider.ts` e `.py`.
- [x] 3.2 Adicionar as ferramentas correspondentes no servidor MCP oficial em `mcp/index.mjs`.

## 4. Frontend, Catálogo e Painel de Telemetria
- [x] 4.1 Adicionar os 3 novos serviços na tabela e cards do portal público `public_html/index.php`.
- [x] 4.2 Atualizar o painel administrativo `public_html/admin.php` para contabilizar requisições das novas ferramentas de compliance.

## 5. Verificação e Validação Final
- [x] 5.1 Testar chamadas pontuais em modo sandbox via curl nos endpoints `/api/v1/sanctions`, `/api/v1/simples` e `/api/v1/compliance`.
- [x] 5.2 Validar carimbo criptográfico `_oracle_proof` assinado em secp256k1 nas novas respostas.
