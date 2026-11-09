# PRD: Migracao para Dominio Proprio grid402.online

**Status:** Em revisao  
**Data:** 2026-11-09  
**Autor:** Thiago e Agente  

---

## 1. Contexto e problema
O Grid402 vinha operando em producao sob o subdominio gratuito e temporario `orangered-jackal-900744.hostingersite.com`. Embora funcional para a fase inicial de testes, subdominios temporarios enfrentam restricoes severas de confianca, reputacao em auditorias automatizadas de agentes de IA (como PayAPI e Glama) e limites de durabilidade.

Com o registro oficial do dominio `grid402.online` na Hostinger, torna-se necessario migrar integralmente o oraculo para este dominio proprio, configurando raiz canonica com redirecionamento de `www`, ativando certificado SSL Let's Encrypt dedicado, atualizando todos os artefatos de descoberta (OpenAPI, llms.txt, MCP e AI Plugin) e publicando novas versoes dos pacotes no npm, preservando retrocompatibilidade via redirecionamento HTTP 301 no subdominio legado.

---

## 2. Objetivos
- Criar e provisionar o website dedicado `grid402.online` na conta Cloud Hosting da Hostinger com SSL ativo e CDN.
- Configurar `https://grid402.online` como URL canonica oficial do oraculo, redirecionando `www.grid402.online` e HTTP para HTTPS.
- Manter o subdominio legado `orangered-jackal-900744.hostingersite.com` respondendo com redirecionamento 301 para preservar chamadas ativas e testes em andamento do PayAPI.
- Atualizar 100% das referencias hardcoded de dominio no oraculo, manifests, schemas OpenAPI, llms.txt, scripts de exemplo e documentacao.
- Atualizar e publicar versoes atualizadas dos pacotes no npm (`grid402-mcp` v1.7.0 e SDK `grid402` v2.6.0).

---

## 3. Nao objetivos (fora do escopo)
- Alteracao da logica interna de oraculo ou orquestracao de precos x402.
- Adicao de novos micro-servicos (o escopo restringe-se a infraestrutura, dominio e distribuicao).
- Exclusao do subdominio legado antes de 30 dias de transicao.

---

## 4. Publico-alvo e personas
- **Agentes de IA e Crawlers:** Consomem o catalogo de ferramentas via `/.well-known/mcp.json`, `/llms.txt` e `https://grid402.online/api/v1/*`.
- **Desenvolvedores:** Instalam `grid402-mcp` e o SDK Node/Python com endpoints oficiais de producao.
- **Mantenedor (Thiago):** Acompanha o dashboard administrativo em `https://grid402.online/admin`.

---

## 5. Historias de usuario
- Como agente de IA autonomo, quero consultar a API em `https://grid402.online/api/v1/forex` para receber dados assinados sob um dominio confiavel e duravel.
- Como desenvolvedor que utiliza Claude Desktop ou Cursor, quero configurar `grid402-mcp` sem precisar sobrescrever URLs manuais em variaveis de ambiente.
- Como comprador que tinha a URL antiga salva, quero ser redirecionado via HTTP 301 para a rota equivalente em `grid402.online`.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|---|---|---|
| RF01 | Provisionar website dedicado `grid402.online` na conta Cloud Hosting da Hostinger (`order_id: 1008982036`). | Must |
| RF02 | Configurar regras de DNS na Hostinger apontando `grid402.online` e `www.grid402.online` para o IP do servidor de hospedagem. | Must |
| RF03 | Emitir e ativar certificado SSL vitalicio (HTTPS) para `grid402.online` e `www.grid402.online` com redirecionamento automatico para HTTPS. | Must |
| RF04 | Configurar canonical redirect no `.htaccess` de `www.grid402.online` para `https://grid402.online`. | Must |
| RF05 | Configurar redirecionamento HTTP 301 no subdominio legado `orangered-jackal-900744.hostingersite.com` para `https://grid402.online/$1`. | Must |
| RF06 | Atualizar constante `GRID402_CANONICAL_DOMAIN` em `public_html/config.php` e URLs em `openapi.json`, `llms.txt`, `llms-full.txt`, `.well-known/mcp.json`, `.well-known/ai-plugin.json` e `sitemap.xml`. | Must |
| RF07 | Fazer deploy do oraculo e arquivos `public_html` para o novo website `grid402.online` preservando `private_data/.env` com as credenciais do bot do Telegram. | Must |
| RF08 | Atualizar default URL no servidor MCP (`mcp/package.json` e `mcp/src/index.ts`) para v1.7.0 e publicar no npm. | Must |
| RF09 | Atualizar default URL no SDK Node (`sdk/node/package.json` e `sdk/node/src/index.ts`) para v2.6.0 e publicar no npm. | Must |
| RF10 | Atualizar `smithery.yaml` e documentacoes README com o novo dominio oficial. | Must |

---

## 7. Requisitos nao funcionais
- **Performance:** Manter tempo de resposta de API abaixo de 20 ms no novo dominio.
- **Disponibilidade:** Zero downtime durante a transicao entre subdominio legado e dominio proprio.
- **Seguranca:** Forcar HTTPS (HSTS) em todas as rotas e manter segredos isolados em `private_data/.env`.
- **Integridade de Caracteres:** Ausencia absoluta do caractere ampersand em documentacao, codigo, metadados e commits.

---

## 8. Fluxos e UX
- **Fluxo 1 (Acesso Direto):** Cliente acessa `https://grid402.online/api/v1/summary` -> Resposta imediata 200 OK com catalogo completo.
- **Fluxo 2 (Acesso com WWW):** Cliente acessa `http://www.grid402.online/algo` -> Redirecionamento 301 para `https://grid402.online/algo`.
- **Fluxo 3 (Acesso Legado):** Cliente acessa `https://orangered-jackal-900744.hostingersite.com/api/v1/cnpj?cnpj=...` -> Redirecionamento 301 mantendo path e query para `https://grid402.online/api/v1/cnpj?cnpj=...`.

---

## 9. Metricas de sucesso
- 100% dos testes E2E executados com sucesso apontando para `https://grid402.online`.
- Status HTTP 200 e certificado SSL valido em `https://grid402.online`.
- Pacotes `grid402-mcp@1.7.0` e `grid402@2.6.0` disponiveis publicamente no npm registry.

---

## 10. Riscos, dependencias e premissas
- **Propagacao de DNS:** Registros A e CNAME na Hostinger podem levar alguns minutos para propagar globalmente.
- **Canary do PayAPI:** Como o PayAPI testou a rota do subdominio temporario, o redirecionamento 301 garante que qualquer chamada residual seja entregue com sucesso.

