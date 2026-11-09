# Design Tecnico: Migracao de Dominio grid402.online

## 1. Arquitetura de Redirecionamento e Roteamento

```
[ Cliente HTTP / Agente IA ]
           |
           +---> http://grid402.online ------------+
           |                                       |
           +---> http://www.grid402.online --------+--> [ 301 Permanent Redirect ]
           |                                       |               |
           +---> https://www.grid402.online -------+               v
           |                                             https://grid402.online/api/v1/*
           |
           +---> https://orangered-jackal-900744... -> [ 301 Redirect para grid402.online/$1 ]
```

### Regras no .htaccess da Raiz Oficial (`grid402.online`):
1. **Forcar HTTPS:**
   Se `%[HTTPS] != on`, redirecionar para `https://grid402.online%{REQUEST_URI}` com status 301.
2. **Eliminar WWW (Canonical Root):**
   Se `%[HTTP_HOST] == www.grid402.online`, redirecionar para `https://grid402.online%{REQUEST_URI}` com status 301.
3. **CORS e Headers de Seguranca:**
   Manter `Access-Control-Allow-Origin: *` e exposicao dos headers x402 (`Payment-Required`, etc.).

### Regras no .htaccess do Subdominio Legado:
Manter redirecionamento 301 de qualquer requisicao recebida no subdominio `hostingersite.com` preservando path e query parameters:
```apache
RewriteEngine On
RewriteCond %{HTTP_HOST} ^orangered-jackal-900744\.hostingersite\.com$ [NC]
RewriteRule ^(.*)$ https://grid402.online/$1 [R=301,L]
```

## 2. Estrutura de Hospedagem na Hostinger
- **Conta Cloud:** `order_id: 1008982036` (usuário `u637629962`).
- **Novo Addon Domain:** `grid402.online`.
- **Root Directory:** `/home/u637629962/domains/grid402.online/public_html`.
- **SSL:** Let's Encrypt gratuito e vitalicio com auto-renovacao.
- **DNS:** Zona vinculada aos Name Servers da Hostinger, com apontamento A para o IP do CloudLinux e CNAME `www` -> `grid402.online.`.

## 3. Integridade de Descoberta M2M
Todos os manifests publicados servidos em `/.well-known/` e na raiz terao suas URLs absolutas alinhadas:
- `servers[0].url`: `https://grid402.online` no OpenAPI 3.1.
- `url`: `https://grid402.online` no `mcp.json`.
- `api.url`: `https://grid402.online/openapi.json` no `ai-plugin.json`.
- `loc`: `https://grid402.online/...` no `sitemap.xml`.
- Links de referencia em `llms.txt` e `llms-full.txt`.

