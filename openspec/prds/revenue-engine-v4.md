# PRD: Grid402 Revenue Engine v4 (Multi-Chain x402 Gasless, Endpoints Globais, Dossiê KYB Web/PDF e Aquisição B2B)

**Status:** Aprovado
**Data:** 2026-11-20
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema
O Grid402 (`https://grid402.online`) já possui infraestrutura estável (`23/23` serviços `UP`, `p95 ~12 ms`, Pix Mercado Pago operacional e `grid402-mcp@1.9.0` publicado), mas apresenta receita orgânica praticamente zerada por 4 gargalos identificados em auditoria de produção:

1. **Perda de demanda de agentes Web3 fora da Base:** o cabeçalho/payload `HTTP 402` (`accepts[]`) anuncia apenas `eip155:8453` (Base). Agentes autônomos rodando em **Solana** (rede principal do PayAI Facilitator), **Polygon** ou **Arbitrum** via `x402-fetch` ignoram o Grid402. Além disso, o endpoint estrela `/api/v1/dossier` ainda não foi indexado no PayAI Bazaar.
2. **Baixa frequência global de chamadas M2M:** o catálogo atual é muito focado em dados brasileiros (CNPJ/CEP/ANBIMA). No `x402scan` e `x402-list.com`, os maiores volumes pagos em USDC vêm de utilitários globais chamados em loop por agentes — em especial extração de página web para Markdown limpo (`/extract`) e validação de e-mails/domínios B2B (`/email-verify`).
3. **Fricção e ausência de SEO para compradores humanos no Brasil:** a página inicial abre em inglês com terminologia Web3 e sem botão direto de compra via Pix no Hero; o site não possui landing pages SSR em português para buscas de alta intenção comercial no Google Brasil; e usuários não-técnicos (factorings, BPO financeiro, vendedores B2B) não têm uma ferramenta web para digitar um CNPJ, pagar R$ 12 no Pix e gerar um **Dossiê 360º KYB visual exportável em PDF**.
4. **Cota gratuita permissiva e baixa presença em marketplaces com cartão:** o modo Sandbox (`X-Payment: demo`) libera `5 req/hora` por IP (suficiente para uso esporádico sem nunca precisar pagar), e o produto ainda não tem pacote pronto para **RapidAPI**, **n8n Official Templates** e **prospecção direta B2B**.

---

## 2. Objetivos
- Destravar **faturamento orgânico em USDC** habilitando liquidação `x402 v2 exact` gasless via PayAI em **4 redes** (`Base`, `Polygon`, `Arbitrum` e `Solana`), indexando `100%` dos endpoints pagos no PayAI Bazaar e lançando 2 endpoints globais de alta frequência (`/api/v1/extract` e `/api/v1/email-verify`).
- Destravar **faturamento orgânico em Reais (Pix/Cartão — mínimo R$ 12,00)** criando o gerador web self-service `/dossie-kyb` com exportação em PDF, 3 páginas SEO SSR em PT-BR e CTA direto de compra no Hero da home.
- Aumentar a taxa de conversão de testes gratuitos para pagos reduzindo o Sandbox de `5 req/hora` para **`3 req/dia` por IP**.
- Entregar na Onda 2 os ativos comerciais prontos para aquisição imediata (pacote RapidAPI, submissão n8n Official Templates, diretórios x402/MCP restantes e Playbook Comercial B2B com scripts de outbound).

---

## 3. Não objetivos (fora do escopo)
- **Não** alterar o valor mínimo de entrada do Mercado Pago (mantido o Starter Pass em **R$ 12,00 = 400 créditos**; não criar plano de R$ 2,90 ou R$ 5,00).
- **Não** instalar bibliotecas server-side de geração de PDF (Dompdf/TCPDF) nem banco de dados SQL (mantida arquitetura Zero-DB na Hostinger).
- **Não** traduzir o HTML SSR base da home `/` para PT-BR (mantido SSR em inglês para SEO global e paridade com testes existentes, usando auto-detecção PT-BR no navegador).
- **Não** executar headless browser/JavaScript rendering no `/api/v1/extract` (extração HTML → Markdown pura via cURL + DOMDocument com limite de 1 MB).
- **Não** criar sistema de login/contas tradicionais para usuários finais (mantido o modelo sem cadastro baseado em Session Pass `pass_g402_...`).

---

## 4. Público-alvo e personas
1. **Agente Autônomo de IA (Solana / Base / Polygon / Arbitrum):** consome `x402 v2` via `x402-fetch`, PayAI Bazaar ou servidor MCP (`grid402-mcp`) para ler páginas web em Markdown, validar e-mails B2B ou auditar empresas.
2. **Analista / Gestor B2B no Brasil (Factoring, FIDC, BPO Financeiro, Comercial):** não escreve `curl`; quer digitar um CNPJ na web, pagar R$ 12 no Pix, ver o Dossiê 360º KYB visual na hora e salvar em PDF para anexar ao cadastro/comitê de crédito.
3. **Desenvolvedor / Builder de Automação BR (n8n, Make, Python, Node):** pesquisa no Google por *"API CNPJ sem mensalidade"*, *"API sanções CGU"* ou *"API dias úteis ANBIMA"* e quer testar em 30 segundos e recarregar via Pix sem contrato anual.

---

## 5. Histórias de usuário
- **Como agente autônomo em Solana, Polygon ou Arbitrum**, quero encontrar minha rede no bloco `accepts[]` do `HTTP 402` do Grid402 para liquidar consultas gasless via PayAI sem precisar fazer bridge para a Base.
- **Como agente pesquisador ou SDR de IA**, quero consultar `/api/v1/extract?url=...` e `/api/v1/email-verify?email=...` por `0.0050 USDC` (1 crédito) para obter Markdown limpo ou validação MX/SPF/DMARC com prova criptográfica `_oracle_proof`.
- **Como analista de crédito ou dono de agência B2B**, quero acessar `/dossie-kyb`, digitar um CNPJ, conferir a prévia gratuita, pagar R$ 12 no Pix na mesma página e imediatamente visualizar e imprimir em PDF o Dossiê 360º KYB completo (ficando ainda com 390 créditos para mais 39 dossiês).
- **Como desenvolvedor brasileiro pesquisando no Google**, quero encontrar páginas dedicadas em português (`/api-cnpj`, `/api-sancoes-cgu`, `/api-dias-uteis-anbima`) com exemplos práticos em cURL/n8n e botão de compra via Pix.
- **Como mantenedor do Grid402**, quero apertar a cota gratuita do Sandbox para `3 req/dia` por IP e dispor de um pacote comercial pronto (RapidAPI + n8n Creators + scripts B2B) para acelerar a geração de receita.

---

## 6. Requisitos funcionais

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Multi-Chain `accepts[]` no HTTP 402 (`exact` PayAI):** `grid402_build_accepts()` DEVE anunciar até 4 opções em mainnet (`eip155:8453` Base, `eip155:137` Polygon, `eip155:42161` Arbitrum e `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` Solana com `feePayer` do PayAI e `payTo` Solana oficial). `facilitator.php` DEVE validar e liquidar payloads x402 v2 tanto EVM (EIP-3009) quanto Solana (`payload.transaction` base64 com anti-replay via hash SHA-256). | Must |
| RF02 | **Novo micro-serviço `GET /api/v1/extract?url=...` (`0.0050 USDC` / 1 crédito):** faz download seguro de página web pública (HTTP/HTTPS) e retorna título, meta description, autor, canonical URL, texto limpo em Markdown, contagem de palavras e principais links, assinado com `_meta` e `_oracle_proof`. | Must |
| RF03 | **Novo micro-serviço `GET /api/v1/email-verify?email=...` (`0.0050 USDC` / 1 crédito):** valida sintaxe RFC, domínio, registros `MX`, políticas `SPF`/`DMARC` via TXT, detecção de provedor gratuito (`gmail`, `outlook`, etc.), lista de domínios descartáveis (*disposable*) e `deliverability_score` (0–100) + `verdict` (`VALID`, `RISKY`, `INVALID`), estritamente Zero-PII (não loga o e-mail completo). | Must |
| RF04 | **Paridade de 25 serviços e 27 ferramentas MCP:** registrar `extract` e `email-verify` em `config.php`, `collector.php` (`25/25 UP`), `index.php`, `catalog.php`, `trust.php`, `openapi.json`, `llms.txt`, `llms-full.txt`, `.well-known/x402.json`, `.well-known/mcp.json`, `.well-known/mcp/server-card.json`, `mcp.php`, `mcp/tools.mjs`, `mcp/index.mjs`, `mcp/test_mcp.mjs` e `scripts/bazaar_seed.mjs` (incluindo `dossier`, `extract` e `email-verify`). | Must |
| RF05 | **Tightening do Sandbox (`3 req/dia` por IP):** alterar `GRID402_SANDBOX_RATE_LIMIT` padrão para `3` e janela de rate-limit em `x402.php` para `86400` segundos (24 horas), atualizando mensagens de erro HTTP 429, `sandbox_info`, UI e testes. | Must |
| RF06 | **Gerador Web Self-Service `/dossie-kyb` (PT-BR SSR + Pix inline + PDF):** página dedicada onde o usuário informa um CNPJ, visualiza um preview gratuito real (via desafio HTTP 402 sem consumir cota paga), pode inserir um `pass_g402_...` existente OU gerar o QR Code Pix do Starter Pass (`R$ 12 = 400 créditos`) inline sem sair da página; ao confirmar o Pix, consulta `/api/v1/dossier` automaticamente, renderiza o Dossiê 360º visual (veredito colorido, score, RFB, Simples/MEI, CGU, `_oracle_proof`), exibe os créditos restantes (`390`) e oferece botão **"Salvar / Imprimir PDF Oficial"** (`@media print` formatado). | Must |
| RF07 | **Páginas SEO de alta intenção em PT-BR + Sitemap:** criar 3 landing pages 100% SSR em PT-BR (`/api-cnpj`, `/api-sancoes-cgu`, `/api-dias-uteis-anbima`) com exemplos práticos (`curl -sL`, Python, n8n), tabela comparativa sem mensalidade, Schema.org JSON-LD e CTA direto para `/pass?tier=starter&method=pix` e `/dossie-kyb`; atualizar `public_html/.htaccess` e `public_html/sitemap.xml` (incluindo `/pass`, `/dossie-kyb` e as 3 novas rotas). | Must |
| RF08 | **Conversão no Hero da Home (`/`):** manter HTML SSR em inglês, adicionar botão primário/secundário de compra imediata **"Pix R$ 12 (400 créditos)"** no Hero e garantir auto-detecção de idioma PT-BR no `i18n.js` para visitantes cujo `navigator.language` inicia com `pt` (quando ainda não escolheram manualmente outro idioma). | Should |
| RF09 | **Onda 2 — Kit de Distribuição Paga e Outbound B2B (`docs/revenue/`):** gerar especificação OpenAPI pronta para importação no **RapidAPI** (`docs/revenue/rapidapi-pack.md`), pacote de submissão **n8n Official Templates** (`docs/revenue/n8n-creator-submission.md`), checklist de submissão **mcp.so / x402-list.com** e **Playbook Comercial B2B** (`docs/revenue/b2b-outbound-playbook.md`) com oferta embalada (setup R$ 500–R$ 2.500 + créditos) e scripts de LinkedIn/WhatsApp. | Must |

---

## 7. Requisitos não funcionais
- **Segurança SSRF estrita no `/api/v1/extract`:** aceitar apenas esquemas `http` e `https`; resolver DNS antes da conexão e rejeitar qualquer IP fora do range público (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`), incluindo IPv4 loopback/privado/metadata (`169.254.169.254`) e IPv6 privado/loopback; validar cada salto de redirect manualmente (máx. 3 redirects); limitar download a **1 MB** e timeout total de **5 segundos**.
- **Zero-PII (LGPD/GDPR):** `/api/v1/email-verify` não deve gravar o endereço de e-mail consultado em logs JSONL, métricas ou cache em texto claro (cache apenas por hash HMAC-SHA256 ou sem cache de endereço completo).
- **Retrocompatibilidade x402:** modo `base-sepolia` (testes) continua emitindo apenas `eip155:84532` em `accepts[]` para não quebrar suítes existentes; em produção (`base`), `eip155:8453` continua sendo o primeiro elemento de `accepts[0]`.
- **Performance e Infraestrutura:** Zero banco de dados SQL, PHP 8.1+ sem extensões extras (`mbstring` opcional com fallback UTF-8 `preg_match('//u')`), zero dependências npm novas.
- **SEO & Acessibilidade:** um único `<h1>` por página, `meta description` exclusiva, contraste WCAG AA, labels em todos os inputs e navegação por teclado.
- **Regra do repositório:** proibição estrita do caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md` e `smithery.yaml`.

---

## 8. Fluxos e UX

### Fluxo A — Agente Autônomo Multi-Chain (Solana / Polygon / Arbitrum / Base)
1. Agente chama `GET /api/v1/extract?url=https://example.com` sem pagamento.
2. Grid402 responde `HTTP 402` com `Payment-Required` contendo 4 ofertas `exact` (Base, Polygon, Arbitrum, Solana) + extensão `bazaar`.
3. Agente escolhe a rede onde tem saldo USDC (ex.: Solana ou Polygon), assina e envia no cabeçalho `PAYMENT-SIGNATURE`.
4. `facilitator.php` valida localmente rede/asset/payTo/valor, reserva anti-replay atômico (`flock`), chama PayAI `/verify` e `/settle`, e retorna `HTTP 200` com `PAYMENT-RESPONSE`, `_meta` e `_oracle_proof`.

### Fluxo B — Humano BR no `/dossie-kyb` (Pix -> Relatório PDF)
1. Visitante acessa `https://grid402.online/dossie-kyb`, digita um CNPJ e clica em **"Gerar Prévia Grátis"**.
2. A página consulta `GET /api/v1/dossier?cnpj=...` sem pagamento (recebe `HTTP 402` com `preview` real + `locked_fields`) e exibe a prévia com os campos críticos desfocados (*blur*).
3. Usuário clica em **"Desbloquear Dossiê Completo via Pix (R$ 12 = 40 Dossiês)"** → o QR Code Pix abre inline via `POST /api/v1/pass/mp-pix`.
4. Após pagamento (polling `/api/v1/pass/mp-status`), o token `pass_g402_...` é salvo no `localStorage`, a página chama automaticamente `GET /api/v1/dossier?cnpj=...` com `X-Payment: pass_g402_...`, exibe o Dossiê 360º completo, o botão **"Baixar / Imprimir PDF Oficial"** e o saldo restante (`390 créditos`).
5. **Fluxo alternativo:** se o usuário já possui um `pass_g402_...`, pode colar o token diretamente na mesma página e consultar em `< 15 ms`.
6. **Estados de erro:** CNPJ inválido (validação client + HTTP 422), falha upstream da Receita/CGU (HTTP 503 sem débito de créditos), Pix expirado (botão de regenerar QR).

---

## 9. Métricas de sucesso (primeiros 14 dias pós-deploy)
- **Indexação x402:** `24/24` endpoints pagos (incluindo `dossier`, `extract` e `email-verify`) indexados no PayAI Bazaar e suportando `exact` em 4 redes.
- **Conversão humana (Pix):** pelo menos **10 compras reais** de Session Pass via Mercado Pago (`>= R$ 120,00` inicial) + primeiras reuniões/propostas B2B via playbook outbound.
- **Conversão M2M (USDC):** pelo menos **50 liquidações orgânicas externas** (vindas de wallets diferentes da carteira do mantenedor) via PayAI / x402.
- **Qualidade técnica:** `100%` das suítes de testes PHP/Node aprovadas, `0` erros 5xx e `openspec validate --all --strict` com 0 falhas.

---

## 10. Riscos, dependências e premissas
- **Risco 1 (SSRF em `/api/v1/extract`):** mitigado com validação estrita de IP público pós-DNS, bloqueio de redirects privados e teto de 1 MB.
- **Risco 2 (Formato Solana `exact` no PayAI):** o `feePayer` do PayAI pode rotacionar periodicamente; o servidor deve permitir override via env (`GRID402_PAYAI_SOLANA_FEE_PAYER`) com default no valor oficial vigente consultado em `/supported` (`CjNFTjvBhbJJd2B5ePPMHRLx1ELZpa8dwQgGL727eKww`) e cache opcional.
- **Risco 3 (Limites DNS/SMTP em hospedagem compartilhada Hostinger no `/email-verify`):** faremos verificação via DNS (`MX`, `TXT` SPF/DMARC) + heurística determinística de descartáveis/role-based sem abrir sockets SMTP porta 25 (frequentemente bloqueada em hospedagens compartilhadas), garantindo latência baixa e zero bloqueio.
- **Premissa:** o mantenedor executará a ação final de seed do Bazaar (`bazaar_seed.mjs --execute` para as 3 novas rotas: `dossier`, `extract`, `email-verify` = `$0.06 USDC` total que volta 100% para sua própria carteira) e publicará as contas/formulários externos da Onda 2 (RapidAPI, n8n Creators e DMs B2B).

---

## 11. Questões em aberto
- [ ] Aprovação do PRD para avançar para a criação da change OpenSpec (`add-revenue-engine-v4`).
