## 1. Backend Foundation
- [x] 1.1 Atualizar a função `grid402_respond_402` em `public_html/x402.php` para suportar parâmetros opcionais `$preview` e `$lockedFields`.
- [x] 1.2 Implementar a função `grid402_get_cnpj_preview(string $cnpj): ?array` em `public_html/services/cnpj.php`.
- [x] 1.3 Implementar a função `grid402_get_cep_preview(string $code): ?array` em `public_html/services/cep.php`.

## 2. Gateway e Router
- [x] 2.1 Integrar no fluxo de `/api/v1/cnpj` em `public_html/index.php` a geração de preview no 402 quando a requisição não tiver `X-Payment`.
- [x] 2.2 Integrar no fluxo de `/api/v1/cep` em `public_html/index.php` a geração de preview no 402 quando a requisição não tiver `X-Payment`.
- [x] 2.3 Garantir que erros 400 (sintaxe inválida) e 404 (inexistente) sejam emitidos antes de qualquer 402.

## 3. Experiência Visual no Console Web
- [x] 3.1 Atualizar `runConsole` e criar componente visual de card de destaque de amostra em `public_html/index.php`.
- [x] 3.2 Conectar os botões do card de amostra diretamente à abertura do modal de pagamento Web3 e QR Code com o valor correto preenchido.

## 4. Testes e Deploy
- [x] 4.1 Criar script de teste `tests/test_teaser_preview.php` cobrindo cenários com e sem preview, validações 400 e 404.
- [x] 4.2 Executar suíte de testes locais e confirmar 100% de sucesso.
- [x] 4.3 Empacotar e realizar deploy nos domínios da Hostinger via MCP.
- [x] 4.4 Verificar em produção a entrega de preview no 402.

