# Proposal

## Why

A auditoria completa de segurança do Grid402 confirmou 4 vulnerabilidades críticas e 15 de alta severidade que expõem o faturamento a roubo de Session Passes Web3 (`PAY-01`), inflação de créditos desvinculada de liquidação real em Permit2 e Solana (`PAY-02`), aprovação indevida e vazamento de tokens em webhook Mercado Pago (*fail-open*) (`PAY-03`), drenagem de carteiras clientes em auto-payers x402 sem teto/allowlist (`ECO-01`), além de exposição de segredos em disco (`ECO-04`), fallbacks hardcoded de chaves do oráculo (`PAY-04`), DoS sem autenticação em geração de PDF (`SVC-01`), loop infinito em projeção de dias úteis (`SVC-02`) e amplificação descontrolada de lote via RapidAPI (`PAY-05`). Esta mudança implementa o endurecimento imediato dos pacotes P0 e P1, estancando vazamentos de receita e blindando os fluxos de faturamento sem interromper o serviço legítimo.

## What Changes

- **Proteção contra IDOR em emissão Web3 (`PAY-01`):** `/api/v1/pass-emitir` mantém a emissão imediata e entrega do `pass_token` no primeiro pagamento, mas retorna exclusivamente `status: already_issued` (sem expor o token) para requisições subsequentes com o mesmo `txHash`. Adiciona fluxo de recuperação autenticado via assinatura de carteira (EIP-191 / Ed25519).
- **Paridade estrita de liquidação e créditos (`PAY-02`):** Vincula a concessão de créditos em `/api/v1/pass` e `/mcp` (`grid402_pass`) estritamente ao valor liquidado (`paymentRequirements.amount`), exigindo igualdade exata entre `permitted.amount` e o montante requerido em Permit2 e validando ATA/mint em Solana SVM.
- **Webhook Mercado Pago seguro e fail-closed (`PAY-03`):** Rejeita requisições com HTTP 401/503 quando `GRID402_MP_WEBHOOK_SECRET` estiver ausente ou inválido em produção; valida janela temporal do timestamp (`ts <= 300s`); responde apenas `{"ok":true}` sem ecoar `pass_token`; valida `currency_id === 'BRL'` e associação estrita de `external_reference`.
- **Defesa financeira em Auto-Payers (`ECO-01`):** Adiciona validação obrigatória de protocolo `https://`, desativação de redirects automáticos (`redirect: 'error'`), allowlist canônica de carteiras/tokens de destino por rede e tetos padrão por transação (`GRID402_MAX_TX_USDC = 0.10`) e por sessão (`GRID402_MAX_SESSION_USDC = 1.00`).
- **Remoção de segredos e saneamento de deploy (`ECO-04`):** Exclusão permanente do artefato local `dist/grid402_deploy_wallets.zip`; reestruturação de `scripts/package_deploy.ps1` com allowlist estrita de staging e endurecimento de `.gitignore` para bloquear `.env.*`, arquivos de backup e chaves.
- **Proteção de `collector.php` e redação de telemetria (`WEB-01`, `WEB-02`):** Bloqueio fail-closed de `collector.php` se `GRID402_CRON_SECRET` for o padrão; descontinuação de `?token=` em GET (adotando header `Authorization: Bearer` ou execução CLI); sanitização de `metrics.php:546` para nunca salvar `pass_g402_...` no campo `tx_hash`.
- **Eliminação de fallbacks hardcoded de chaves do Oráculo (`PAY-04`):** Falha fechada (fail-closed) sem chaves em produção; derivação dinâmica de chave pública via OpenSSL; segredo dedicado `GRID402_PASS_HMAC_SECRET` isolado de chaves cron/oráculo.
- **Salvaguardas RapidAPI expandidas para lotes (`PAY-05`):** Validação e débito atômico individual para cada sub-serviço em `POST /api/v1/batch` contra `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` e `GRID402_RAPIDAPI_BLOCKED_ENDPOINTS`.
- **Spend Controls isolados e sem mutação via GET (`PAY-06`, `WEB-04`, `ECO-05`):** Restrição de `pass-limits` a `POST`/`PATCH` com validação de payload JSON; aceitação de `pass_token` apenas em headers HTTP; validação atômica de `allowed_endpoints` dentro do `flock` de `consumePass()`.
- **Preservação de Sandbox e integridade MCP (`ECO-02`):** Filtragem de argumentos no servidor MCP contra o schema; bloqueio de auto-liquidação em `mcp/tools.mjs` sob modo Sandbox explícito ou Session Pass ativo; remoção da sobreposição de rede arbitrária.
- **Verificação TLS mandatória em Python (`ECO-03`):** Remoção de exceções para `hostingersite.com` e eliminação de fallbacks inseguros para `ssl._create_unverified_context()`.
- **Mitigação de DoS e ReDoS em serviços (`SVC-01`, `SVC-02`, `SVC-03`, `SVC-04`):** Fixture estática server-side para modo amostra em `/dossier/pdf` + reescrita linear $O(N)$ do layout; validação de limites de data (1900–2100) e teto de 10.000 iterações em `workdays.php`; fail-closed em consultas CGU CEIS/CNEP; parser seguro de `robots.txt` em `extract.php`.

## Capabilities

### New Capabilities
<!-- Nenhuma capacidade inteiramente nova; o foco é endurecimento e segurança das capacidades existentes. -->

### Modified Capabilities
- `x402-payment-oracle`: Endurecimento criptográfico, validação de chaves sem fallback, proteção contra IDOR em `/pass-emitir`, spend controls isolados e redação de passes.
- `x402-exact-settlement`: Validação estrita de valores em Permit2 e Solana SVM; tetos de segurança e bloqueio de redirects em auto-payers.
- `mercadopago-checkout`: Validação fail-closed de webhook HMAC, validação temporal de `ts`, não-exposição de `pass_token` e verificação de moeda BRL.
- `mcp-server`: Filtragem estrita de argumentos, bloqueio de auto-liquidação em modo sandbox e respeito a limites de sessão.
- `python-sdk`: Verificação TLS obrigatória sem exceções de domínio ou fallback desabilitado.
- `open-data-utilities`: Proteção contra DoS linear em PDF, prevenção de loop infinito em `workdays.php`, fail-closed em triagem de sanções CGU e parser linear de `robots.txt`.

## Impact

- **APIs e Endpoints Afetados:** `/api/v1/pass-emitir`, `/api/v1/pass`, `/api/v1/pass/limits`, `/api/v1/pass/mp-webhook`, `/api/v1/dossier/pdf`, `/api/v1/workdays`, `/api/v1/extract`, `/collector.php`.
- **Arquivos Core:** `public_html/services/pass_checkout.php`, `public_html/services/pass_repository.php`, `public_html/services/mercadopago.php`, `public_html/facilitator.php`, `public_html/services/oracle_signer.php`, `public_html/services/dossier_pdf.php`, `public_html/services/workdays.php`, `public_html/services/compliance.php`, `public_html/collector.php`, `public_html/metrics.php`.
- **Ecossistema:** `mcp/tools.mjs`, `mcp/x402_payer.mjs`, `sdk/python/grid402.py`, `python/grid402/client.py`, `scripts/package_deploy.ps1`, `.gitignore`.
- **Compatibilidade e Quebra:** Não quebra o faturamento legítimo. Compradores continuam recebendo tokens no primeiro checkout; chamadas de automação mantêm o comportamento esperado.
