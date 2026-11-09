# Tasks: Migracao para Dominio Proprio grid402.online

## 1. Infraestrutura na Hostinger
- [x] 1.1 Criar website dedicado `grid402.online` na conta Cloud Hosting (`order_id: 1008982036`) via MCP
- [x] 1.2 Verificar e configurar registros DNS A e CNAME para `grid402.online` e `www.grid402.online`
- [x] 1.3 Instalar e ativar certificado SSL vitalicio (Let's Encrypt) para `grid402.online`
- [x] 1.4 Habilitar forcar HTTPS e verificar configuracoes de cache e CDN

## 2. Roteamento e Codigo Web (Zero-DB)
- [x] 2.1 Atualizar `GRID402_CANONICAL_DOMAIN` e constantes de URL em `public_html/config.php`
- [x] 2.2 Configurar regras de redirecionamento 301 (force HTTPS e strip www) no `.htaccess` de `grid402.online`
- [x] 2.3 Atualizar URLs em `public_html/openapi.json`, `llms.txt`, `llms-full.txt`, `sitemap.xml`, `.well-known/mcp.json` e `ai-plugin.json`
- [x] 2.4 Atualizar links e snippets de copia em `public_html/agents.php` e landing page `public_html/index.php`
- [x] 2.5 Configurar `.htaccess` do subdominio legado para redirecionar 301 para `https://grid402.online/$1`

## 3. Deploy e Migracao em Producao
- [x] 3.1 Empacotar `grid402_full_deploy.zip` com o novo codigo e o arquivo `private_data/.env` (bot do Telegram)
- [x] 3.2 Executar deploy para `grid402.online` via MCP `hosting_deploy`
- [x] 3.3 Testar HTTP 200, SSL e emissao do desafio x402 v2 em `https://grid402.online/api/v1/forex`
- [x] 3.4 Deploy do redirecionador no subdominio legado `orangered-jackal-900744.hostingersite.com`

## 4. Pacotes Globais e Documentacao
- [x] 4.1 Atualizar default API URL no servidor MCP (`mcp/package.json` e `mcp/src/index.ts`) para v1.7.0
- [x] 4.2 Compilar e publicar `grid402-mcp@1.7.0` no npm registry
- [x] 4.3 Atualizar default API URL no SDK Node (`sdk/node/package.json` e `sdk/node/src/index.ts`) para v2.6.0
- [x] 4.4 Compilar e publicar `grid402@2.6.0` no npm registry
- [x] 4.5 Atualizar `smithery.yaml`, `examples/` e `README.md` com o novo dominio
- [x] 4.6 Atualizar homepageUrl no repositorio GitHub via `gh repo edit`

## 5. Testes e Verificacao
- [x] 5.1 Atualizar suite de testes para cobrir o novo dominio (`tests/test_agent_discovery.php`, `tests/test_config.php`)
- [x] 5.2 Executar bateria de testes completa e homologar funcionamento
- [x] 5.3 Commit das alteracoes no Git seguindo Conventional Commits e push para `origin/main`

