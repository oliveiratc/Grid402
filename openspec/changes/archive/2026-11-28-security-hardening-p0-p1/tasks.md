# Tasks

## 1. Saneamento de Segredos e Deploy (P0)

- [x] 1.1 Excluir permanentemente o arquivo `dist/grid402_deploy_wallets.zip` e verificar sua ausência em disco
- [x] 1.2 Atualizar `.gitignore` para bloquear explicitamente `.env.*`, backups de storage e chaves privadas, verificando com `git status` e `git check-ignore`
- [x] 1.3 Refatorar `scripts/package_deploy.ps1` com allowlist estrita para cópia de `private_data/` e portão de bloqueio em caso de arquivos sensíveis no staging

## 2. Blindagem de Faturamento Web3 e x402 (P0)

- [x] 2.1 Modificar `public_html/services/pass_checkout.php` para retornar `already_issued` sem expor `pass_token` em requisições repetidas de `txHash` e adicionar teste de verificação
- [x] 2.2 Ajustar `public_html/facilitator.php` e `public_html/index.php` para calcular créditos estritamente sobre `paymentRequirements.amount` e exigir igualdade em Permit2
- [x] 2.3 Validar mint e ATA em instruções Solana SPL e ignorar campos JSON de valor não assinados em `public_html/facilitator.php`

## 3. Blindagem de Checkout Fiat e Mercado Pago (P0)

- [x] 3.1 Implementar política fail-closed em `public_html/services/mercadopago.php` para rejeitar webhooks sem secret ou com assinatura HMAC inválida em produção
- [x] 3.2 Remover a exposição de `pass_token` no retorno HTTP do webhook do Mercado Pago em `public_html/index.php` e adicionar verificação de frescor temporal do timestamp `ts`

## 4. Proteção de Carteiras em Auto-Payers e MCP (P0 & P1)

- [x] 4.1 Implementar validação de HTTPS, bloqueio de redirects e allowlist canônica de carteiras em `mcp/x402_payer.mjs`, SDKs e AgentKit
- [x] 4.2 Adicionar tetos padrão por transação (`$0.10`) e por sessão (`$1.00`) nos auto-payers do MCP e SDKs
- [x] 4.3 Corrigir precedência e bloquear auto-liquidação em modo Sandbox ou com Session Pass ativo em `mcp/tools.mjs`

## 5. Endurecimento de Oráculo, Telemetria e Admin (P0 & P1)

- [x] 5.1 Atualizar `public_html/collector.php` para operar fail-closed caso `GRID402_CRON_SECRET` seja o default e descontinuar autenticação via `?token=` em GET
- [x] 5.2 Sanitizar `public_html/metrics.php` para nunca gravar tokens `pass_g402_...` no campo `tx_hash` de eventos recentes
- [x] 5.3 Remover fallbacks hardcoded de chaves do oráculo em `public_html/services/oracle_signer.php` e derivar chaves públicas dinamicamente via OpenSSL
- [x] 5.4 Restringir mutação de Spend Controls em `public_html/index.php` exclusivamente a verbos `POST`/`PATCH` com validação JSON

## 6. Prevenção de DoS e Resiliência de Serviços (P1)

- [x] 6.1 Reestruturar `public_html/services/dossier_pdf.php` para operar em tempo linear O(N) e forçar fixture estática no modo amostra
- [x] 6.2 Adicionar validação de limites de calendário e trava de iterações máximas no laço de cálculo em `public_html/services/workdays.php`
- [x] 6.3 Ajustar `public_html/services/compliance.php` para operar fail-closed em erros do Portal da Transparência sem cachear falsos negativos de sanções
- [x] 6.4 Implementar limites de regras e wildcards no parser de `robots.txt` em `public_html/services/extract.php`

## 7. Verificação Integrada e Testes de Regressão

- [x] 7.1 Executar a suíte completa de testes de segurança e pagamentos em `tests/` e verificar 100% de aprovação
- [x] 7.2 Executar `openspec validate --all` e verificar que todas as specs e mudanças estão em total conformidade
