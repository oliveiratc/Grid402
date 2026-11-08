## 1. Backend e Configuração
- [x] 1.1 Configurar parâmetros da testnet Base Sepolia em `public_html/config.php`.
- [x] 1.2 Implementar função de rate limit de sandbox por IP em `public_html/x402.php` com persistência em `sandbox_rates.json`.
- [x] 1.3 Implementar suporte ao cabeçalho `demo`/`sandbox` e validação fallback da Base Sepolia em `public_html/x402.php`.

## 2. Gateway e Router
- [x] 2.1 Conectar a validação de sandbox no gateway de `public_html/index.php` antes de acionar a auditoria da Mainnet.
- [x] 2.2 Garantir retorno de HTTP 429 Too Many Requests quando a cota de 20 req/hora por IP for superada.
- [x] 2.3 Injetar `"sandbox": true` e cabeçalho `X-Payment-Mode: sandbox` no payload liberado.

## 3. Console Web e Documentação
- [x] 3.1 Adicionar o botão `🧪 Simular Pagamento (Sandbox)` no console interativo de `public_html/index.php`.
- [x] 3.2 Atualizar os snippets das abas de integração (Python, Node.js, cURL) indicando o uso de `X-Payment: demo` para testes.

## 4. Testes e Deploy
- [x] 4.1 Criar script de teste automatizado `tests/test_sandbox_mode.php`.
- [x] 4.2 Executar suite de testes e assegurar 100% de aprovação.
- [x] 4.3 Empacotar e realizar deploy nos domínios da Hostinger via MCP.
- [x] 4.4 Testar e validar o sandbox ao vivo em produção.

