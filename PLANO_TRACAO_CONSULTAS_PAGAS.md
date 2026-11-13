# Grid402 // DiagnÃ³stico e Plano de TraÃ§Ã£o para Consultas Pagas

Este documento consolida a auditoria tÃ©cnica feita no cÃ³digo do **Grid402** e nos dados do painel `/admin` (`https://grid402.online/admin`), junto com o plano prÃ¡tico para destravar consultas pagas reais.

---

## 1. DiagnÃ³stico do Estado Atual (Painel `/admin` + CÃ³digo)

### O que os nÃºmeros do painel mostram
- **497 consultas totais**, sendo **15 pagas** (`7,3%` de conversÃ£o sobre desafios 402 emitidos) e **0% de adoÃ§Ã£o Sandbox**.
- **SLA / Sucesso TÃ©cnico: 22,7%** â€” a maioria das chamadas estÃ¡ batendo no `402 Payment Required` e parando por ali.
- **Top Agentes / IPs:**
  - `#1` (`2804:7f0:...`, IP residencial/mÃ³vel brasileiro): `24 (5p)` â€” perfil `Agente Pagador (x402)`, foco em `forex`.
  - `#2`, `#3`, `#4` (`104.37.xxx.xxx`, `2a04:4e41:...`, `140.248.xxx.xxx`): `Agente sem Wallet` batendo em `ssl`, `indicators`, `crypto` (`0p`). SÃ£o crawlers/agentes/bots de nuvem sem carteira Base configurada.
- **Faturamento Total: US$ 0.0000** e **Saldo On-Chain (Base): US$ 0.0000** (`0 passes ativos â€¢ 4 crÃ©ditos`):
  - **Motivo tÃ©cnico identificado em `public_html/admin.php` (linha 2186):** o JS do painel exibe `Number(data.arpu_usdc || 0.0005).toFixed(4)`, entÃ£o o Ticket MÃ©dio cai no fallback `0.0005` mesmo quando `total_revenue_usdc` estÃ¡ `0.0000`.
  - **Motivo operacional:** as `15 pagas` nÃ£o vieram de liquidaÃ§Ã£o real em USDC na carteira orÃ¡culo atual (`Saldo On-Chain: US$ 0.0000`), ou foram registradas antes da separaÃ§Ã£o entre `facilitator_verify_only` e pagamentos liquidados em `metrics.php`.

### Gargalos de conversÃ£o encontrados no cÃ³digo (e corrigidos)
1. **Resposta HTTP 402 nÃ£o divulgava o Sandbox (`public_html/x402.php`):**
   - Agentes e desenvolvedores que batiam no `402` recebiam instruÃ§Ãµes de EIP-3009, `tx_hash` e `Session Pass`, mas **nÃ£o eram informados sobre o modo gratuito `X-Payment: demo`**. Isso explica a **AdoÃ§Ã£o Sandbox de 0,0%**.
   - **CorreÃ§Ã£o aplicada:** incluÃ­do o bloco `sandbox_test` e a instruÃ§Ã£o de teste imediato com `X-Payment: demo` diretamente no JSON do `402`.
2. **Servidor MCP nÃ£o aceitava `Session Pass` (`mcp/tools.mjs`):**
   - O MCP exigia `GRID402_PRIVATE_KEY` para pagar automaticamente. A maioria dos usuÃ¡rios de Claude Desktop / Cursor nÃ£o vai colocar a chave privada de uma carteira Ethereum em um arquivo JSON local.
   - **CorreÃ§Ã£o aplicada:** adicionado suporte a `GRID402_SESSION_PASS` (`pass_g402_...`) no `mcp/tools.mjs`, alÃ©m de instruÃ§Ã£o clara no retorno `402` listando Sandbox (`GRID402_SANDBOX=true`), Session Pass (`GRID402_SESSION_PASS`) e auto-liquidaÃ§Ã£o (`GRID402_PRIVATE_KEY`).

---

## 2. Por que "Agentes sem Wallet" nÃ£o convertem (e como resolver)

Hoje hÃ¡ dois pÃºblicos distintos que podem gerar volume pago no Grid402:

1. **Agentes autÃ´nomos nativos Web3 (x402 / AgentKit / Base):** jÃ¡ possuem USDC na Base e pagam por chamada via `PAYMENT-SIGNATURE` ou `tx_hash`. Eles precisam **descobrir** que o Grid402 existe.
2. **Devs, agÃªncias de automaÃ§Ã£o e empresas no Brasil (n8n, Make, WhatsApp, ERPs):** precisam consultar **CNPJ, CEP, Simples Nacional, SanÃ§Ãµes (CEIS/CNEP), Dias Ãšteis ANBIMA e CÃ¢mbio BRL** todos os dias, mas **nÃ£o tÃªm USDC na Base**. Para esse pÃºblico, o caminho de menor atrito Ã© o **Session Pass** (`pass_g402_...`).

---

## 3. Plano de TraÃ§Ã£o em 3 Frentes (Ordem de Maior Impacto)

### Frente 1: Onde jÃ¡ existem agentes pagadores (Curto Prazo â€” M2M)
- [ ] **Submeter ao x402 Ecosystem / x402 Bazaar:** listar os endpoints do Grid402 nos diretÃ³rios oficiais do protocolo x402 para aparecer nas buscas automÃ¡ticas de agentes que usam Coinbase CDP / AgentKit.
- [ ] **Submeter ao Base Ecosystem Directory (`base.org/ecosystem`):** usar o texto pronto jÃ¡ preparado em `DIVULGACAO.md`.
- [ ] **Publicar/Atualizar o MCP nos diretÃ³rios:**
  - Confirmar publicaÃ§Ã£o de `grid402-mcp` no **npm**, **Smithery.ai** (`smithery.yaml`) e **Glama.ai** (`glama.json`).
  - Publicar os SDKs `grid402` no **PyPI** e **npm** (comandos em `DIVULGACAO.md`).
  - *Nota:* depois da ediÃ§Ã£o em `mcp/tools.mjs`, publicar uma nova versÃ£o patch do `grid402-mcp` no npm para que quem instalar jÃ¡ tenha suporte a `GRID402_SESSION_PASS`.

### Frente 2: Capturar demanda real no Brasil (CNPJ + CEP + Compliance + Forex)
APIs de `crypto` e `dns` tÃªm dezenas de alternativas gratuitas lÃ¡ fora. O **diferencial Ãºnico** do Grid402 com demanda comercial imediata no Brasil Ã©:
- `/api/v1/cnpj` + `/api/v1/simples` + `/api/v1/sanctions` + `/api/v1/compliance` (onboarding B2B, antifraude, anÃ¡lise de crÃ©dito, KYC/KYB).
- `/api/v1/cep` + `/api/v1/workdays` + `/api/v1/indicators` (logÃ­stica, cobranÃ§a bancÃ¡ria, cÃ¡lculo de juros/boletos).
- `/api/v1/forex` (precificaÃ§Ã£o em BRL).

**AÃ§Ã£o prÃ¡tica:**
1. Template pronto criado em [`examples/n8n/grid402_cnpj_compliance_workflow.json`](examples/n8n/grid402_cnpj_compliance_workflow.json) e guia em [`examples/n8n/README.md`](examples/n8n/README.md) (consulta CNPJ Compliance 360 + CEP/IBGE em paralelo, jÃ¡ prÃ©-configurado com `X-Payment: demo` para teste em 30s e troca fÃ¡cil para `pass_g402_...`).
2. Divulgar esse caso de uso em comunidades brasileiras de **n8n, automaÃ§Ã£o, SaaS e IA aplicada** (LinkedIn, Discord/Telegram de n8n Brasil, TabNews).

### Frente 3: Eliminar a barreira de entrada do Session Pass
Hoje o painel mostra **0 passes ativos**. Se o Ãºnico jeito de comprar um Session Pass (`0.05` a `20.00 USDC`) for enviando USDC manualmente na Base Mainnet, quase nenhum dev tradicional brasileiro vai concluir a compra.

**PrÃ³xima evoluÃ§Ã£o recomendada no produto:**
- **Checkout simples de Session Pass em BRL/CartÃ£o (Pix / Stripe / Mercado Pago ou Coinbase Onramp):** o usuÃ¡rio paga R$ 5 ou R$ 15 via Pix e recebe automaticamente na tela um token `pass_g402_...` com 1.000 a 5.000 crÃ©ditos prontos para colar no n8n, Python, Node ou MCP.
- **Link direto de compra na resposta `402`:** apontar `https://grid402.online/#passes` (ou uma pÃ¡gina dedicada `/pass`) como URL clara para humanos que inspecionam o `402`.

---

## 4. Exemplo Pronto de Chamada (n8n / HTTP Request / cURL)

Para testar o fluxo completo agora em qualquer automaÃ§Ã£o (n8n `HTTP Request` node ou terminal):

```bash
# 1. Teste imediato no Sandbox (sem custo, atÃ© 3 req/hora por IP)
curl -s -H "X-Payment: demo" \
  "https://grid402.online/api/v1/compliance?cnpj=00000000000191"

# 2. Uso em produÃ§Ã£o com Session Pass (<15ms, sem latÃªncia on-chain)
curl -s -H "X-Payment: pass_g402_SEU_TOKEN_AQUI" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"
```

---

## 5. Checklist de Deploy Imediato e SubmissÃ£o nos DiretÃ³rios

### Deploy TÃ©cnico
1. Subir as alteraÃ§Ãµes de `public_html/x402.php` para a Hostinger (`https://grid402.online`).
2. Testar `curl -i https://grid402.online/api/v1/forex` e confirmar que o JSON do `402` jÃ¡ exibe `sandbox_test` e o link `https://grid402.online/#topup`.
3. Publicar atualizaÃ§Ã£o do pacote `mcp/` (`grid402-mcp@1.8.1`) no npm com suporte a `GRID402_SESSION_PASS`:
   ```bash
   cd mcp
   npm publish --access public
   ```

### SubmissÃ£o nos DiretÃ³rios (Copy & Paste)
1. **x402 Ecosystem / x402 Bazaar (`https://www.x402.org/ecosystem`):**
   - **Name:** `Grid402`
   - **Category:** `Data & Network Utilities / Oracles`
   - **Endpoint / Discovery:** `https://grid402.online/llms.txt` e `https://grid402.online/openapi.json`
   - **Description:** `Autonomous open data & network utility hub (Forex, Crypto, DNS, SSL, ASN, CEP, CNPJ, Compliance, LEI, OFAC) settled via HTTP x402 v2 in USDC on Base Mainnet ($0.0050-$0.0300) with ECDSA secp256k1 oracle proofs and <15ms Session Passes.`
2. **Base Ecosystem Directory (`https://www.base.org/ecosystem`):**
   - FormulÃ¡rio pronto em [`DIVULGACAO.md`](DIVULGACAO.md) (seÃ§Ã£o 2).
3. **DiretÃ³rios MCP (Smithery.ai, Glama.ai, PulseMCP, mcp.so):**
   - **RepositÃ³rio:** `https://github.com/oliveiratc/Grid402`
   - **Package npm:** `grid402-mcp` (`1.8.1`)
   - **Manifestos jÃ¡ no repo:** `smithery.yaml`, `glama.json`, `public_html/.well-known/mcp/server-card.json`
4. **PyPI e npm (SDKs):**
   - Comandos prontos em [`DIVULGACAO.md`](DIVULGACAO.md) (seÃ§Ã£o 1).
