# Design

## Context

Hoje `public_html/index.php` lê a prova de pagamento de `X-Payment` (ou `Authorization: x402`, query e corpo POST) e `grid402_validate_payment()` em `public_html/x402.php` trata tudo como `tx_hash`, `pass_g402_...` ou `demo`. O desafio 402 já anuncia `scheme: exact` e `x402Version: 2` (`grid402_respond_402()`), mas nenhum caminho aceita a assinatura EIP-3009 que clientes padrão enviam. A carteira do oráculo não tem ETH, então o servidor não pode liquidar por conta própria. O motivo da mudança está em `proposal.md` e o comportamento exigido, nos specs.

Restrições: PHP puro sem Composer (hospedagem Hostinger), armazenamento em arquivos JSON com `flock`, nenhuma dependência nova, segredos apenas em `.env`.

## Goals / Non-Goals

**Goals:**
- Um cliente x402 padrão consegue pagar e receber HTTP 200 sem intervenção manual.
- Os fluxos `tx_hash`, `pass_g402_...` e `demo` seguem inalterados.
- Falha do facilitador nunca libera dado nem gera faturamento.

**Non-Goals:**
- Relayer próprio (chave privada, gás e `transferWithAuthorization` pelo servidor).
- Liquidação em lote, outras redes, outros ativos.
- Implementar um segundo facilitador além da interface configurável.

## Decisions

1. **Facilitador externo, padrão Coinbase CDP.** Chamadas `POST {base}/v2/x402/verify` e `POST {base}/v2/x402/settle`, com `Authorization: Bearer <JWT>` assinado com a API key do CDP. Alternativas: relayer próprio (exige ETH, chave quente no servidor e operação de gás) e verificar agora e liquidar depois (risco de inadimplência e de autorização expirada). A URL base é configurável para trocar de facilitador sem mudar código.
2. **Liquidar antes de entregar.** Fluxo: validar localmente, reservar o `nonce`, chamar `verify`, chamar `settle` e só então responder 200. Alternativa: entregar após `verify` e liquidar depois, mais rápido porém com risco de entrega sem pagamento. Como cada consulta custa frações de centavo, a segurança vale mais que a latência. O modo `verify_only` existe só para testes.
3. **Distinção do formato da prova por inspeção.** Ordem: `pass_g402_` → `demo` → `0x`+64 hex → base64 JSON válido → desconhecido (400). Evita ambiguidade com `X-PAYMENT` v1, que usa o mesmo nome de cabeçalho do fluxo antigo.
4. **Anti-replay por `payer:nonce`.** Novo arquivo `private_data/used_nonces.json` com `flock`, no mesmo padrão de `used_txs.json`, reservado antes do `verify` e liberado se o facilitador falhar sem ter liquidado. Alternativa: confiar só no nonce on-chain do USDC, que protege os fundos mas não evita chamadas duplicadas ao facilitador.
5. **JWT do CDP em PHP puro.** Assinado com `openssl_sign` (ES256, convertendo a assinatura DER para o formato R||S) ou com `sodium_crypto_sign_detached` (Ed25519), conforme o tipo da chave. O token é gerado por requisição, com expiração curta. Alternativa: biblioteca JWT via Composer, descartada para não adicionar dependência. Os dois tipos de chave devem ser validados na hospedagem antes do deploy.
6. **Falha segura.** Mapeamento: assinatura ou valor inválido → 402; replay → 403; formato → 400; facilitador fora do ar ou timeout → 503 com `Retry-After`. Timeout de 5 s em `verify` e 10 s em `settle`, sem retentar `settle` automaticamente para não liquidar duas vezes.
7. **Telemetria com origem do pagamento.** `grid402_record_telemetry()` ganha o campo `payment_source` e o `tx_hash` de liquidação. O painel exibe falhas por categoria e inclui qualquer status gravado nas barras (já feito em `61eb920`).
8. **Compatibilidade do desafio.** O 402 mantém `accepts` no formato v2 e acrescenta, no corpo, o fluxo alternativo por `tx_hash` e `demo`. O cabeçalho `Payment-Required` já existente continua.

## Risks / Trade-offs

- [Dependência de terceiro: indisponibilidade ou mudança de API do CDP] → modo `disabled` com degradação para o fluxo por `tx_hash`, URL configurável e testes com facilitador simulado.
- [Custo ou limites do CDP em volume de micropagamentos de US$ 0,0005] → confirmar a política de preço e as cotas antes do deploy; se o custo por transação superar a receita por consulta, priorizar Session Pass (pagamento único para várias consultas).
- [Settle sem confirmação de resposta (timeout após liquidar)] → registrar o `nonce` como "pendente", não retentar `settle` e conferir o estado on-chain antes de liberar de novo.
- [Latência maior que a do `tx_hash` direto] → aceitável para M2M; o Session Pass continua sendo o caminho de baixa latência.
- [JWT incorreto em PHP puro] → testes unitários com chaves de teste e verificação contra a documentação oficial do CDP.
- [Segredos vazando em log ou resposta] → mascarar valores em qualquer mensagem de erro e cobrir com teste dedicado.

## Migration Plan

1. Implementar atrás da configuração `GRID402_FACILITATOR_MODE=disabled` (padrão), sem alterar o comportamento atual.
2. Validar localmente com facilitador simulado e, depois, em Base Sepolia com credenciais de teste.
3. Em produção, configurar as credenciais no `.env`, ativar `verify_only` e depois `settle`.
4. Rollback: voltar `GRID402_FACILITATOR_MODE=disabled`; os fluxos antigos permanecem intactos.

