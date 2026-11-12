# Grid402 // Diagnóstico e Plano de Tração para Consultas Pagas

Este documento consolida a auditoria técnica feita no código do **Grid402** e nos dados do painel `/admin` (`https://grid402.online/admin`), junto com o plano prático para destravar consultas pagas reais.

---

## 1. Diagnóstico do Estado Atual (Painel `/admin` + Código)

### O que os números do painel mostram
- **497 consultas totais**, sendo **15 pagas** (`7,3%` de conversão sobre desafios 402 emitidos) e **0% de adoção Sandbox**.
- **SLA / Sucesso Técnico: 22,7%** — a maioria das chamadas está batendo no `402 Payment Required` e parando por ali.
- **Top Agentes / IPs:**
  - `#1` (`2804:7f0:...`, IP residencial/móvel brasileiro): `24 (5p)` — perfil `Agente Pagador (x402)`, foco em `forex`.
  - `#2`, `#3`, `#4` (`104.37.xxx.xxx`, `2a04:4e41:...`, `140.248.xxx.xxx`): `Agente sem Wallet` batendo em `ssl`, `indicators`, `crypto` (`0p`). São crawlers/agentes/bots de nuvem sem carteira Base configurada.
- **Faturamento Total: US$ 0.0000** e **Saldo On-Chain (Base): US$ 0.0000** (`0 passes ativos • 4 créditos`):
  - **Motivo técnico identificado em `public_html/admin.php` (linha 2186):** o JS do painel exibe `Number(data.arpu_usdc || 0.0005).toFixed(4)`, então o Ticket Médio cai no fallback `0.0005` mesmo quando `total_revenue_usdc` está `0.0000`.
  - **Motivo operacional:** as `15 pagas` não vieram de liquidação real em USDC na carteira oráculo atual (`Saldo On-Chain: US$ 0.0000`), ou foram registradas antes da separação entre `facilitator_verify_only` e pagamentos liquidados em `metrics.php`.

### Gargalos de conversão encontrados no código (e corrigidos)
1. **Resposta HTTP 402 não divulgava o Sandbox (`public_html/x402.php`):**
   - Agentes e desenvolvedores que batiam no `402` recebiam instruções de EIP-3009, `tx_hash` e `Session Pass`, mas **não eram informados sobre o modo gratuito `X-Payment: demo`**. Isso explica a **Adoção Sandbox de 0,0%**.
   - **Correção aplicada:** incluído o bloco `sandbox_test` e a instrução de teste imediato com `X-Payment: demo` diretamente no JSON do `402`.
2. **Servidor MCP não aceitava `Session Pass` (`mcp/tools.mjs`):**
   - O MCP exigia `GRID402_PRIVATE_KEY` para pagar automaticamente. A maioria dos usuários de Claude Desktop / Cursor não vai colocar a chave privada de uma carteira Ethereum em um arquivo JSON local.
   - **Correção aplicada:** adicionado suporte a `GRID402_SESSION_PASS` (`pass_g402_...`) no `mcp/tools.mjs`, além de instrução clara no retorno `402` listando Sandbox (`GRID402_SANDBOX=true`), Session Pass (`GRID402_SESSION_PASS`) e auto-liquidação (`GRID402_PRIVATE_KEY`).

---

## 2. Por que "Agentes sem Wallet" não convertem (e como resolver)

Hoje há dois públicos distintos que podem gerar volume pago no Grid402:

1. **Agentes autônomos nativos Web3 (x402 / AgentKit / Base):** já possuem USDC na Base e pagam por chamada via `PAYMENT-SIGNATURE` ou `tx_hash`. Eles precisam **descobrir** que o Grid402 existe.
2. **Devs, agências de automação e empresas no Brasil (n8n, Make, WhatsApp, ERPs):** precisam consultar **CNPJ, CEP, Simples Nacional, Sanções (CEIS/CNEP), Dias Úteis ANBIMA e Câmbio BRL** todos os dias, mas **não têm USDC na Base**. Para esse público, o caminho de menor atrito é o **Session Pass** (`pass_g402_...`).

---

## 3. Plano de Tração em 3 Frentes (Ordem de Maior Impacto)

### Frente 1: Onde já existem agentes pagadores (Curto Prazo — M2M)
- [ ] **Submeter ao x402 Ecosystem / x402 Bazaar:** listar os endpoints do Grid402 nos diretórios oficiais do protocolo x402 para aparecer nas buscas automáticas de agentes que usam Coinbase CDP / AgentKit.
- [ ] **Submeter ao Base Ecosystem Directory (`base.org/ecosystem`):** usar o texto pronto já preparado em `DIVULGACAO.md`.
- [ ] **Publicar/Atualizar o MCP nos diretórios:**
  - Confirmar publicação de `grid402-mcp` no **npm**, **Smithery.ai** (`smithery.yaml`) e **Glama.ai** (`glama.json`).
  - Publicar os SDKs `grid402` no **PyPI** e **npm** (comandos em `DIVULGACAO.md`).
  - *Nota:* depois da edição em `mcp/tools.mjs`, publicar uma nova versão patch do `grid402-mcp` no npm para que quem instalar já tenha suporte a `GRID402_SESSION_PASS`.

### Frente 2: Capturar demanda real no Brasil (CNPJ + CEP + Compliance + Forex)
APIs de `crypto` e `dns` têm dezenas de alternativas gratuitas lá fora. O **diferencial único** do Grid402 com demanda comercial imediata no Brasil é:
- `/api/v1/cnpj` + `/api/v1/simples` + `/api/v1/sanctions` + `/api/v1/compliance` (onboarding B2B, antifraude, análise de crédito, KYC/KYB).
- `/api/v1/cep` + `/api/v1/workdays` + `/api/v1/indicators` (logística, cobrança bancária, cálculo de juros/boletos).
- `/api/v1/forex` (precificação em BRL).

**Ação prática:**
1. Criar um **guia/template pronto para n8n e Make** mostrando como enriquecer um lead B2B (recebe CNPJ no formulário/WhatsApp → chama `/api/v1/compliance?cnpj=...` com header `X-Payment: pass_g402_...` → retorna score de risco, situação na Receita, Simples Nacional e sanções CEIS/CNEP em 1 chamada).
2. Divulgar esse caso de uso em comunidades brasileiras de **n8n, automação, SaaS e IA aplicada** (LinkedIn, Discord/Telegram de n8n Brasil, TabNews).

### Frente 3: Eliminar a barreira de entrada do Session Pass
Hoje o painel mostra **0 passes ativos**. Se o único jeito de comprar um Session Pass (`0.05` a `20.00 USDC`) for enviando USDC manualmente na Base Mainnet, quase nenhum dev tradicional brasileiro vai concluir a compra.

**Próxima evolução recomendada no produto:**
- **Checkout simples de Session Pass em BRL/Cartão (Pix / Stripe / Mercado Pago ou Coinbase Onramp):** o usuário paga R$ 5 ou R$ 15 via Pix e recebe automaticamente na tela um token `pass_g402_...` com 1.000 a 5.000 créditos prontos para colar no n8n, Python, Node ou MCP.
- **Link direto de compra na resposta `402`:** apontar `https://grid402.online/#passes` (ou uma página dedicada `/pass`) como URL clara para humanos que inspecionam o `402`.

---

## 4. Exemplo Pronto de Chamada (n8n / HTTP Request / cURL)

Para testar o fluxo completo agora em qualquer automação (n8n `HTTP Request` node ou terminal):

```bash
# 1. Teste imediato no Sandbox (sem custo, até 3 req/hora por IP)
curl -s -H "X-Payment: demo" \
  "https://grid402.online/api/v1/compliance?cnpj=00000000000191"

# 2. Uso em produção com Session Pass (<15ms, sem latência on-chain)
curl -s -H "X-Payment: pass_g402_SEU_TOKEN_AQUI" \
  "https://grid402.online/api/v1/cnpj?cnpj=00000000000191"
```

---

## 5. Checklist de Deploy Imediato
1. Subir as alterações de `public_html/x402.php` para a Hostinger (`https://grid402.online`).
2. Testar `curl -i https://grid402.online/api/v1/forex` e confirmar que o JSON do `402` já exibe `sandbox_test` e a instrução do `X-Payment: demo`.
3. Publicar atualização do pacote `mcp/` (`grid402-mcp`) no npm com suporte a `GRID402_SESSION_PASS`.
4. Submeter nos diretórios (`base.org/ecosystem`, Smithery, Glama, PyPI, npm).
