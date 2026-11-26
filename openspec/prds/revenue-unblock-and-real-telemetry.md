# PRD: Destravamento de Faturamento, Conversão Multicanal e Telemetria Real (Grid402)

**Status:** Aprovado
**Data:** 2026-11-26
**Autor:** Thiago Campos + Agente

---

## 1. Contexto e problema

A auditoria técnica, de funil e de telemetria executada em 26/11/2026 sobre a operação em produção do Grid402 (`https://grid402.online`, commit `579206a`) revelou por que o faturamento acumulado permaneceu em **US$ 4,4005** apesar de o painel `/admin` exibir 26.559 chamadas de API, 3.463 page-views humanos e 21 wallets conectadas:

1. **Audiência real mascarada por distorções do `/admin`:**
   - Entre 80% e 92% das chamadas diárias de API vêm de apenas dois crawlers de diretórios x402 (`65.203.xxx.xxx` e `2a02:3102:xxxx:xxxx`), sem separação no painel.
   - A métrica `Wallets Conectadas (21)` não mede conexões reais de carteira no front-end — contabiliza todo perfil não-RapidAPI em `growth.json`, incluindo scanners em `/mcp` e tentativas de prova inválida.
   - Os 903 bloqueios HTTP `429` do modo Sandbox (`X-Payment: demo`) são somados dentro de `total_sandbox_requests` e `payment_sources['sandbox']`, inflando ~181 chamadas bem-sucedidas para 1.084.
   - Os 2.993 eventos classificados como `"Outros"` são page-views históricos pré-24/11 sem status HTTP, enquanto o painel *"Diagnóstico Real de Falhas"* lê apenas os últimos `256 KB` de `requests.jsonl` — janela soterrada pelos bots `402` a cada ~9–10 horas.
   - Erros `502`/`503` do Mercado Pago nas rotas `/api/v1/pass/mp-*` não alimentam a telemetria do `/admin`, criando cegueira operacional total sobre falhas do Pix.
2. **Travas técnicas e vazamentos de conversão sobre quem chega de verdade:**
   - **Solana anunciada, mas inoperante:** carteira Solana está com `0 SOL`, ATA SPL USDC ausente, `GRID402_SOLANA_X402_ENABLED=false` e RPC primário (`solana-rpc.publicnode.com`) retornando HTTP `403`, mas UI, `/catalog`, `/pass`, `llms.txt` e manifestos seguem anunciando Solana ativa.
   - **Top-Up Web3 da Home com risco de perda de pagamento:** `copyOracleAddress()` está duplicada (`public_html/index.php`) e sempre copia o endereço EVM mesmo com Solana selecionada; `startWeb3Topup()` faz apenas uma chamada a `/api/v1/pass-emitir` após 3,5s sem retry e, em caso de erro, apaga o link do `txHash` da tela.
   - **Vazamentos residuais no protocolo x402 e CORS:** no x402 v1, `resource` é enviado como caminho relativo (`/api/v1/...`) em vez de URL absoluta; no x402 v2, mismatches de tipo/normalização em `paymentPayload.accepted` (`amount` inteiro, `network` alias, `extra`) quebram `deepEqual` estrito do PayAI; CORS em `public_html/index.php` e `public_html/mcp.php` omite headers x402/MCP (`X-Payment-Signature`, `Payment`, `X-Grid402-Channel`, `Mcp-Session-Id`, `MCP-Protocol-Version`), e `mcp.php` não lê os aliases `X-Payment-Signature`/`Payment`.
   - **Fricção no checkout e pós-compra do Session Pass:** visitantes globais no `/pass` não têm saída fiat em USD; clicar em Pix na Home dispara `startMpPix()` automaticamente em 250ms antes de o visitante ler os planos; `mercadopago.php` envia `payer.email = 'pagador@grid402.online'` (mesmo domínio do vendedor) quando o e-mail é omitido; pós-compra salva o token em 3 chaves de armazenamento incompatíveis entre Home, `/pass`, `/dossie-kyb` e `/payment-dashboard`; e o Playground da Home não aceita uso com Session Pass.
   - **Desperdício de demanda em `429`, `/mcp` e `/dossie-kyb`:** o cupom `MCPTRY50` tem `0/250` resgates porque não é promovido nos erros `429` nem no `/mcp` hospedado; o Playground trata `429` igual a `402` em loop; `/mcp` trava `protocolVersion: 2024-11-05` sem auto-trial; `/dossie-kyb` está ausente da navbar global, exibe texto placeholder na *"Prévia Grátis"* e exige R$ 12 sem degrau intermediário; `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP=0` deixa margem exposta a arbitragem no `/dossier`; e `llms.txt` está em português.

Resolver essas falhas agora é pré-requisito para que qualquer esforço de distribuição (outbound B2B, n8n Creator Hub, diretórios MCP/x402) converta em receita real mensurável.

---

## 2. Objetivos

1. **Eliminar 100% das travas técnicas P0 de pagamento e protocolo** em `public_html/` (Solana oculta de forma consistente até ativação futura, Top-Up Web3 com retry e preservação de `txHash`, conformidade x402 v1/v2 com PayAI, CORS completo e Pix sem e-mail de domínio próprio).
2. **Aumentar a conversão do público híbrido (Devs/Agentes EN e B2B Brasil PT-BR)** integrando saída Cartão USD via RapidAPI, removendo o auto-disparo Pix em 250ms, unificando a persistência e o uso do Session Pass pós-compra, expondo `MCPTRY50` nos fluxos `402`/`429` e `/mcp` hospedado, e lançando o fluxo completo do `/dossie-kyb` (navbar + amostra real completa + tier Pix de entrada R$ 4,90 com 50 créditos).
3. **Tornar o cockpit `/admin` uma fonte fiel de decisão comercial e operacional**, separando crawlers de diretórios, retirando erros `429` do contador de Sandbox, corrigindo `Wallets Conectadas`, mantendo histórico dedicado de falhas reais de pagamento imune ao volume de `402`, e alertando falhas ou ausência de configuração do Mercado Pago.
4. **Proteger margem e descoberta global M2M** ativando teto diário de 15 dossiês/dia no RapidAPI, traduzindo `llms.txt` para inglês e removendo duplicidade de payload no `402`.
5. **Viabilizar a meta comercial de 30 dias pós-deploy + ações de distribuição:** atingir **>= 10 resgates de `MCPTRY50`**, **>= 5 vendas externas pagas** (Pix ou USDC/Pass com taxa de consumo > 0%) e **>= US$ 100 em receita orgânica**.

---

## 3. Não objetivos (fora do escopo)

Explicitamente **NÃO** fazem parte desta entrega:
- Publicação ou edição dos pacotes externos no npm/PyPI (`mcp/tools.mjs`, `sdk/node/grid402.mjs`, `sdk/python/grid402.py`, `python/grid402/client.py`), incluindo auto-payer gasless EIP-3009 nos SDKs (planejado para Fase 2).
- Integração de novos gateways de cartão em USD (Stripe, LemonSqueezy ou Paddle) — a saída fiat USD desta fase será o direcionamento claro para as listagens já ativas no RapidAPI.
- Ativação em produção da rede Solana (financiamento de SOL, criação de ATA SPL USDC e troca para RPC pago) — Solana será ocultada dos anúncios/UI/manifestos até decisão futura do operador.
- Alteração dos preços canônicos Web3 USDC (`Starter $2 / 400 cr`, `Pro $5 / 1.200 cr`, `Fleet $20 / 6.000 cr`) na escala `v2_5000`, nem alteração da carteira canônica `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`.
- Introdução de banco de dados SQL (mantém arquitetura Zero-DB em `private_data/*.json` com `flock(LOCK_EX)`).
- Execução manual de ações em contas externas do operador (submissão no n8n Creator Hub, envio de mensagens de outbound B2B, conferência do painel RapidAPI ou teste bancário real de Pix).

---

## 4. Público-alvo e personas

1. **Desenvolvedor de Agentes IA / Integrador Global (EN):** descobre o Grid402 via diretórios x402, MCP hospedado (`/mcp`), `llms.txt` ou Playground; quer testar rapidamente sem wallet, resgatar créditos de teste (`MCPTRY50`), pagar via x402 gasless (Base/Polygon/Arbitrum) ou cartão USD (RapidAPI) e operar sem erros silenciosos de protocolo/CORS.
2. **Comprador B2B Brasil (PT-BR — Contabilidades, Fintechs, Compliance, Compras):** acessa `/dossie-kyb` ou Home buscando consultar CNPJ, Quadro Societário, Simples, Sanções/OFAC e veredito determinístico; quer ver antes exatamente como é um Dossiê real (inclusive em PDF), testar pagando pouco via Pix (R$ 4,90 por 5 dossiês) ou ir direto ao Starter (R$ 12 por 40 dossiês) e usar os créditos imediatamente sem entender Web3.
3. **Operador do Grid402 (Admin):** usa `/admin` diariamente para acompanhar audiência humana e de agentes reais (separada de bots de diretórios), conversão real, falhas reais de pagamento (x402 e Mercado Pago) e saúde financeira sem números fantasma.

---

## 5. Histórias de usuário

- **HU01:** Como desenvolvedor integrando via x402 v1/v2 ou browser/MCP, quero que os desafios `402`, a liquidação PayAI e o CORS em `/api/v1/*` e `/mcp` aceitem todos os headers e formatos válidos sem rejeições falsas por `resource` relativo, normalização de `accepted` ou header bloqueado.
- **HU02:** Como comprador Web3 na Home, quero copiar o endereço correto da rede selecionada e ter garantia de retry e preservação do `txHash` após pagar USDC para nunca perder meu Session Pass por latência de RPC.
- **HU03:** Como visitante internacional no `/pass` ou na Home, quero ver claramente as redes realmente ativas (sem Solana quebrada) e ter uma opção de pagamento em cartão USD (via RapidAPI) ao lado de USDC Web3.
- **HU04:** Como comprador brasileiro no `/dossie-kyb` ou `/pass`, quero acessar o Dossiê KYB direto pela navbar, inspecionar uma amostra real completa (dados e PDF) antes de pagar, escolher livremente entre o pacote de entrada **R$ 4,90 (50 créditos = 5 dossiês)** e o **Starter R$ 12 (400 créditos = 40 dossiês)** sem QR Pix disparando sozinho, e usar meu Pass automaticamente nas telas do site.
- **HU05:** Como usuário que atingiu o limite diário do Sandbox (`429`) na API, no Playground ou no `/mcp` hospedado, quero receber instruções claras em inglês oferecendo o cupom gratuito `MCPTRY50` (50 créditos) e links diretos de upgrade (USD/Pass/Pix) sem cair em loop de `402`.
- **HU06:** Como cliente MCP remoto conectado a `https://grid402.online/mcp`, quero negociar a versão do protocolo MCP suportada pelo meu cliente, poder fazer 1 chamada gratuita de descoberta (auto-trial diário) e receber instruções claras de ativação via `MCPTRY50` ou Session Pass.
- **HU07:** Como operador do Grid402, quero abrir `/admin` e enxergar separadamente tráfego real vs. crawlers de diretórios, usos reais de Sandbox vs. bloqueios `429`, conexões reais de wallet, todas as falhas reais de pagamento (incluindo Mercado Pago 502/503) em log persistente e alerta imediato se o Mercado Pago não estiver configurado.
- **HU08:** Como operador do Grid402, quero limitar a 15 consultas diárias o acesso ao `/dossier` via RapidAPI para impedir arbitragem de margem contra o preço unitário de US$ 0,0500.

---

## 6. Requisitos funcionais

Os requisitos abaixo estão estruturados para serem implementados em **2 changes OpenSpec sequenciais**, ambas restritas a `public_html/` e respectivos testes automatizados.

### Bloco 1 — Change OpenSpec 1: Destravamento de Pagamentos, Conversão, MCP Hospedado e `/dossie-kyb`

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF01 | **Ocultação consistente de Solana até ativação futura:** remover ou ocultar referências de aceite ativo de Solana na UI (`/catalog`, `/pass`, Top-Up da Home e respostas de descoberta `llms.txt`, `llms-full.txt`, `pricing.json`, `ai-agent.json`, `openapi.json`, metadata x402) enquanto `GRID402_SOLANA_X402_ENABLED` for `false`, mantendo o código interno de suporte preservado atrás da flag para reativação futura. | Must |
| RF02 | **Correção do Top-Up Web3 na Home:** eliminar a duplicação de `copyOracleAddress()` e do ID `btn-copy-oracle` em `public_html/index.php`, garantindo cópia fiel do endereço da rede selecionada; implementar retry com backoff (mínimo 5 tentativas) em `startWeb3Topup()` ao chamar `/api/v1/pass-emitir`, preservando o link do `txHash` na tela e preenchendo automaticamente `#manual-pass-tx-input` em caso de falha ou timeout. | Must |
| RF03 | **Conformidade estrita x402 v1 e v2 com PayAI:** em `public_html/x402.php` e `public_html/facilitator.php`, garantir que x402 v1 sempre envie `resource` como URL absoluta (`https://grid402.online/api/v1/<service>`), e que x402 v2 normalize `paymentPayload.accepted` antes de chamar PayAI `/verify` e `/settle` (`amount` como string numérica, `network` em formato CAIP-2 canônico e espelhamento coerente de chaves em `extra`, como `assetTransferMethod`). | Must |
| RF04 | **CORS completo e aliases de header em `/api/v1/*` e `/mcp`:** atualizar `Access-Control-Allow-Headers` em `public_html/index.php` para incluir `X-Payment-Signature`, `Payment` e `X-Grid402-Channel`; atualizar `public_html/mcp.php` para incluir `X-Payment-Signature`, `Payment`, `Mcp-Session-Id` e `MCP-Protocol-Version` no CORS e aceitar `X-Payment-Signature` e `Payment` na leitura de credenciais de pagamento. | Must |
| RF05 | **Correção do checkout Mercado Pago / Pix e fim do auto-disparo:** remover o disparo automático de `startMpPix()` após 250ms ao abrir `/pass?method=pix` (apenas destacar/selecionar o método Pix e o plano escolhido aguardando clique explícito); validar presença de `GRID402_MP_ACCESS_TOKEN` (ou modo sandbox) na UI de `/pass` e `/dossie-kyb` exibindo estado claro caso indisponível; remover o fallback `pagador@grid402.online` em `public_html/services/mercadopago.php` exigindo e-mail válido do pagador (ou gerando endereço neutro fora do domínio `grid402.online` em chamadas legadas sem e-mail); usar `data.qr_mime` dinamicamente em `public_html/dossie_kyb.php` em vez de `image/png` hardcoded. | Must |
| RF06 | **Saída fiat em USD via RapidAPI no `/pass` e na Home:** adicionar bloco/CTA claro de pagamento com **Cartão de Crédito em USD via RapidAPI** em `public_html/pass.php` e na seção de compra/top-up de `public_html/index.php`, apontando para a listagem oficial do Grid402 no RapidAPI Hub, visível especialmente para visitantes em idioma inglês. | Must |
| RF07 | **Persistência unificada e uso imediato do Session Pass:** unificar leitura e gravação do token ativo (`pass_g402_...`) em um helper/chave padrão compartilhada (localStorage + cookie `SameSite=Lax`) entre Home (`index.php`), `/pass` (`pass.php`), `/dossie-kyb` (`dossie_kyb.php`) e `/payment-dashboard` (`payment_dashboard.php`, aceitando token salvo quando `$_GET['token']` não for informado), mantendo retrocompatibilidade de leitura com as chaves legadas; adicionar opção de executar consultas usando Session Pass diretamente no Playground da Home. | Must |
| RF08 | **Conversão de erros `429` e `402` com `MCPTRY50` em inglês:** enriquecer o JSON de resposta HTTP `429` (cota sandbox atingida) e orientações de upgrade no `402` em `public_html/index.php` com instruções claras em inglês promovendo o cupom gratuito `MCPTRY50` (50 créditos sem wallet), link direto para `/pass` (resgate/compra) e opção RapidAPI USD; corrigir o front-end do Playground (`public_html/index.php`) para tratar HTTP `429` com estado dedicado de upgrade/resgate de `MCPTRY50` em vez de reabrir o modal de `402` em loop. | Must |
| RF09 | **Melhorias no `/mcp` hospedado (`public_html/mcp.php`):** negociar dinamicamente `protocolVersion` no método `initialize` aceitando versões suportadas pelos clientes MCP modernos (ex.: `2024-11-05`, `2025-03-26`, `2025-06-18`) com fallback seguro; oferecer **1 chamada gratuita de auto-trial por dia por IP** (sem precisar de header manual) nas ferramentas pagas com contador isolado (`mcp_sandbox`), e ao atingir o limite ou exigir pagamento retornar mensagem clara em inglês com o cupom `MCPTRY50`, URL de resgate/compra `/pass` e instrução de header `X-Payment`. | Must |
| RF10 | **Destravamento comercial do `/dossie-kyb` (Navbar + Amostra Real + Tier Pix R$ 4,90):** adicionar link direto para `/dossie-kyb` na navbar global (`public_html/includes/nav.php`); substituir a *"Prévia Grátis"* baseada em placeholder por uma **amostra real completa e estática** de um CNPJ público exemplo (mostrando exatamente os blocos retornados pelo Dossiê 360°, score, veredito determinístico e botão de download/impressão de PDF de amostra); criar o tier Pix BRL **`micro`** em `grid402_mp_packages()` (**R$ 4,90 → 50 créditos = 5 Dossiês KYB**, validade 90 dias, `raw_usdc_units = 250000`, `credit_scale = 'v2_5000'`), ancorando visualmente o **Starter R$ 12,00 (400 créditos = 40 Dossiês, R$ 0,30/dossiê)** como melhor custo-benefício em `/dossie-kyb` e `/pass`. | Must |
| RF11 | **Visibilidade de erros no Playground Web3:** atualizar `payPlaygroundWithWeb3()` em `public_html/index.php` para distinguir cancelamento explícito do usuário (código EIP-1193 `4001`) de erros reais de RPC/carteira/saldo, exibindo mensagem diagnóstica segura sem mascarar falhas como *"Pagamento Cancelado"*. | Should |

### Bloco 2 — Change OpenSpec 2: Telemetria Real `/admin`, Proteção RapidAPI e Descoberta EN

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF12 | **Separação de crawlers de diretórios e ampliação do filtro de bots:** implementar classificação de tráfego automatizado de diretórios/scanners na telemetria de API (`public_html/metrics.php`), permitindo que o `/admin` exiba separadamente **Chamadas API Reais (sem bots/diretórios)** vs. **Crawlers/Diretórios x402** (sem quebrar o rastreamento de saúde dos indexadores); ampliar `grid402_is_bot_user_agent()` para filtrar `Go-http-client`, `undici`, `node-fetch`, `axios`, `httpx`, `aiohttp`, `Scrapy`, `Java/`, `okhttp`, `Guzzle`, `ChatGPT-User`, `Claude-Web`, `Meta-ExternalAgent`, `Playwright` e `Puppeteer`; impedir que chamadas `Accept: text/markdown` em `/` somem como page-views humanos e ignorar visitas autenticadas do próprio operador `/admin`. | Must |
| RF13 | **Desacoplamento de HTTP `429` do contador de Sandbox:** corrigir `public_html/index.php` e `public_html/metrics.php` para que bloqueios HTTP `429` (`X-Payment: demo` ou auto-trial MCP) **não** sejam somados em `total_sandbox_requests` nem em `payment_sources['sandbox']`, sendo contabilizados exclusivamente em contadores dedicados de bloqueio de cota (`sandbox_rate_limited` / `status_counts['429']`), além de separar `mcp_sandbox` de `sandbox` genérico. | Must |
| RF14 | **Correção da métrica `Wallets Conectadas`:** parar de contar qualquer perfil não-RapidAPI de `growth.json` como "Wallet Conectada" (`public_html/growth.php`); registrar conexões reais de wallet via endpoint leve/evento controlado quando `eth_requestAccounts` for concluído no front-end (com proteção anti-spam/rate-limit) e exibir no `/admin` separadamente **Wallets Conectadas (Reais)** e **Agentes/Perfis Identificados**. | Must |
| RF15 | **Log dedicado de falhas reais de pagamento + telemetria e alerta do Mercado Pago:** criar arquivo/ring buffer dedicado em `private_data/payment_failures.json` (ou JSONL exclusivo de falhas reais com retenção mínima de 500 eventos, fora do `requests.jsonl` de 256 KB soterrado por `402`), registrando falhas reais de x402, Session Pass e erros `502`/`503` das rotas `/api/v1/pass/mp-*`; fazer `/admin` ler dessa fonte dedicada sem descartar categorias novas; adicionar alerta automático no `/admin` quando `GRID402_MP_ACCESS_TOKEN` estiver ausente em modo produção ou houver falhas recentes do gateway Mercado Pago. | Must |
| RF16 | **Correção da exibição de status HTTP e reconciliação de receitas no `/admin`:** diferenciar no `/admin` HTTP `400` de erro de prova (`invalid_proof`) de `400` de parâmetro inválido (`bad_request`), rotular explicitamente eventuais resíduos históricos pré-24/11 (não chamando page-views históricos de `"Outros"` de API) e indicar claramente quando chamadas RapidAPI têm faturamento apurado externamente no RapidAPI Hub (`US$ 0,00` local). | Should |
| RF17 | **Proteção de margem no RapidAPI:** definir o padrão de `GRID402_RAPIDAPI_DOSSIER_DAILY_CAP` para **`15`** em `public_html/config.php` (continuando sobrescrevível via `.env`), garantindo retorno estruturado HTTP `429` quando o limite diário do `/dossier` via RapidAPI for atingido. | Must |
| RF18 | **Descoberta global em inglês e limpeza de payload `402`:** traduzir `public_html/llms.txt` para inglês alinhado à oferta atual (sem Solana ativa, destacando `MCPTRY50`, `/mcp`, Base/Polygon/Arbitrum, RapidAPI USD e `/dossie-kyb`), e remover o campo legado duplicado `teaser_data` quando idêntico a `preview` na resposta `402` (`public_html/x402.php`), mantendo retrocompatibilidade se algum contrato interno ou teste exigir (verificado antes da remoção). | Should |

---

## 7. Requisitos não funcionais

- **Arquitetura Zero-DB e Concorrência:** toda nova escrita persistente (`payment_failures.json`, telemetria de wallet conectada, pedidos do tier `micro` em `mp_orders.json`, contador `mcp_sandbox`) deve seguir estritamente `fopen(..., 'c+')` + `flock(LOCK_EX)` + `grid402_read_locked_json()` ou `grid402_atomic_write()`, sem segurar dois locks simultaneamente.
- **Compatibilidade de Ambiente:** compatível com PHP 8.1+ (LiteSpeed Hostinger e PHP CLI local Windows/Linux), sem dependência obrigatória de `mbstring` (`preg_match('//u', ...)`).
- **Performance:** o novo log dedicado de falhas e a classificação de bots não podem adicionar mais de `2 ms` ao tempo de resposta p95 da API (`<= 15 ms` para consumo de Session Pass).
- **Segurança e Zero-PII:**
  - Nunca expor `GRID402_MP_ACCESS_TOKEN`, `GRID402_MP_WEBHOOK_SECRET`, `GRID402_ORACLE_PRIVATE_KEY` ou chaves privadas em respostas HTTP, HTML, JS ou logs.
  - Redação estrita de tokens/segredos em `payment_failures.json` (reutilizando `grid402_mp_sanitize_error_detail()` e mascaramento de IPs LGPD `/16` e `/32`).
  - Manter integridade da assinatura criptográfica `_oracle_proof` (ECDSA `secp256k1-sha256` por referência em `grid402_sign_data`) e da carteira canônica `0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90`.
- **Acessibilidade, SEO e UI:** semântica HTML válida, contraste adequado, navegação por teclado, links claros na navbar responsiva (desktop e mobile) sem quebrar layout, e `<title>` / meta descriptions coerentes.
- **Paridade e Invariantes de Testes:**
  - Manter exatamente as **27 ferramentas MCP** (`v2.0.0`) entre `public_html/mcp.php`, `public_html/.well-known/mcp.json`, `public_html/.well-known/mcp/server-card.json` e `mcp/`.
  - Proibição estrita do caractere `&` em `README.md`, `mcp/README.md`, `integrations/agentkit/README.md` e `smithery.yaml`.
  - Toda bateria de homologação existente + novos testes cobrindo os cenários das specs OpenSpec devem passar 100%, com empacotamento final via `scripts/package_deploy.ps1`.

---

## 8. Fluxos e UX

### 8.1 Fluxo B2B Brasil (`/dossie-kyb` via Pix)
1. Usuário encontra **"Dossiê KYB"** diretamente na navbar global em qualquer página.
2. Em `/dossie-kyb`, vê o botão **"Ver Exemplo Real de Dossiê (PDF/Dados)"** que abre uma amostra real completa (CNPJ público exemplo com Razão Social, QSA, Simples, Sanções/OFAC, Score e veredito `APPROVED`/`FLAGGED` + opção de visualizar/baixar PDF de amostra).
3. Ao decidir consultar seu próprio CNPJ, escolhe entre:
   - **Pacote Teste / Micro:** **R$ 4,90** → **50 créditos (5 Dossiês KYB)** — entrada sem fricção;
   - **Starter (Mais vantajoso):** **R$ 12,00** → **400 créditos (40 Dossiês KYB — R$ 0,30/dossiê)**;
   - **Pro / Fleet** para volume maior.
4. Informa um e-mail válido para recibo/antifraude e clica explicitamente em **"Gerar QR Code Pix"**.
5. **Estado de erro:** se Mercado Pago estiver sem configuração ou retornar `502`/`503`, a UI exibe mensagem clara com fallback para Web3/RapidAPI e o erro é gravado em `payment_failures.json` + alerta no `/admin`.
6. Após confirmação do Pix, o Session Pass é salvo automaticamente na chave unificada (localStorage + cookie), liberando o formulário do `/dossie-kyb`, o Playground da Home e o `/payment-dashboard` sem o usuário precisar copiar/colar manualmente o token.

### 8.2 Fluxo Desenvolvedor / Agente IA (EN — Sandbox `429`, `/mcp`, `/pass`)
1. Visitante/agente chama um endpoint em modo sandbox (`X-Payment: demo`) ou via `/mcp` (que concede 1 chamada auto-trial/dia por IP).
2. Ao atingir a cota (`429`), recebe JSON estruturado em inglês contendo:
   - Cupom **`MCPTRY50`** (50 créditos grátis sem wallet) + endpoint/URL de resgate direto;
   - Link para `/pass` com 3 caminhos explícitos: **Web3 USDC (Base / Polygon / Arbitrum)**, **Cartão USD (RapidAPI)** e **Pix/Cartão BRL (Mercado Pago)** — sem exibir Solana;
   - No Playground da Home, o erro `429` abre card dedicado de resgate de `MCPTRY50` ou inserção de Session Pass (sem loop de `402`).
3. Se o usuário escolher Web3 Top-Up na Home, copia o endereço correto da rede EVM escolhida, envia USDC e o front faz até 5 retries com backoff em `/api/v1/pass-emitir`, mantendo o link do `txHash` visível e preenchido no campo manual caso ocorra qualquer timeout.

### 8.3 Fluxo Operador (`/admin`)
1. Ao abrir `/admin`, vê imediatamente:
   - Audiência de API **Real** separada de **Crawlers de Diretórios x402**;
   - **Sandbox (200 OK)** separado de **Bloqueios 429** e **MCP Auto-Trial**;
   - **Wallets Conectadas Reais** separadas de **Perfis/Agentes Identificados**;
   - Alerta no topo se o Mercado Pago estiver sem token em produção ou com falhas recentes;
   - Tabela *"Diagnóstico Real de Falhas de Pagamento"* alimentada por `payment_failures.json`, preservando as últimas falhas reais mesmo após milhares de desafios `402` dos bots.

### 8.4 Sequenciamento de Entrega (OpenSpec)
- **Change 1 (`unblock-revenue-checkout-mcp-kyb`):** RF01 a RF11 — foco direto em eliminar bugs de cobrança e destravar conversão (deploy rápido em produção).
- **Change 2 (`real-telemetry-admin-and-discovery-hardening`):** RF12 a RF18 — foco em visibilidade fiel no `/admin`, proteção de margem RapidAPI e descoberta global em inglês.

---

## 9. Métricas de sucesso

### Critérios Técnicos (na entrega das 2 changes)
- **100%** dos testes de homologação existentes + novos testes automatizados OpenSpec passando sem regressões.
- **0** referências ativas a Solana em produção enquanto `GRID402_SOLANA_X402_ENABLED=false`.
- **100%** das falhas simuladas de Mercado Pago (`502`/`503`) e x402 registradas em `payment_failures.json` e visíveis no `/admin` mesmo após rajada simulada > `256 KB` de eventos `402`.
- **0** bloqueios `429` somados em `total_sandbox_requests` ou `payment_sources['sandbox']`.
- **100%** de interoperabilidade do token de Session Pass salvo após compra entre `/pass`, `/dossie-kyb`, Home Playground e `/payment-dashboard`.

### Critérios de Negócio (30 dias pós-deploy + ações de distribuição)
- **>= 10 resgates reais** do cupom `MCPTRY50` (hoje `0/250`).
- **>= 5 vendas externas pagas** (Pix Micro/Starter ou USDC Web3/Pass) com **taxa de consumo de créditos > 0%** (eliminando passes ociosos).
- **>= US$ 100 em receita orgânica** combinada (USDC + Pix BRL + RapidAPI).
- **Conversão pós-429 mensurável** (acompanhada via resgates `MCPTRY50` + compras após bloqueio de cota).

---

## 10. Riscos, dependências e premissas

| Tipo | Descrição | Mitigação |
|------|-----------|-----------|
| **Risco Operacional (Alto)** | `GRID402_MP_ACCESS_TOKEN` ou `GRID402_MP_WEBHOOK_SECRET` ausentes/inválidos no `.env` de produção bloqueando Pix mesmo após deploy. | Operador testa 1 Pix real de R$ 4,90 ou R$ 12 após deploy; RF15 cria alerta visível no `/admin` e RF05 evita botão quebrado silencioso. |
| **Risco de Receita (Alto)** | Sem execução das ações externas de aquisição (n8n Creator Hub, outbound B2B, divulgação MCP/Cursor), a melhora de conversão sozinha renderá receita modesta (~US$ 20–60/mês). | Tratar deploy da Change 1 como gatilho imediato para o operador executar o checklist de distribuição externa. |
| **Risco de Retrocompatibilidade (Médio)** | Alterar chaves de storage do Session Pass ou remover `teaser_data` pode quebrar consumidores ou testes legados. | Manter leitura fallback das chaves antigas no browser e verificar todas as asserções da suíte de testes antes de remover `teaser_data`. |
| **Risco de Segurança (Médio)** | Novo endpoint de evento `wallet_connected` (RF14) sofrer spam por bots inflando novamente a métrica. | Proteger com rate-limit por IP/janela, validação de formato de endereço EVM (`0x[a-f0-9]{40}`) e deduplicação por hash de carteira. |
| **Dependência Externa** | Pacote npm `grid402-mcp` já instalado nos clientes continua com a string local em PT-BR no `402` até a Fase 2. | Enriquecer mensagens server-side no `402`/`429` e no `/mcp` hospedado em inglês para capturar o máximo possível sem novo `npm publish`. |

---

## 11. Questões em aberto (checagens operacionais do operador)

- [ ] Testar 1 Pix real em produção com conta bancária de terceiro e confirmar `GRID402_MP_ACCESS_TOKEN` (prod) e `GRID402_MP_WEBHOOK_SECRET` no `.env` da Hostinger.
- [ ] Confirmar se houve rotação de `GRID402_ORACLE_PRIVATE_KEY` ou `GRID402_CRON_SECRET` após a emissão dos 2 Session Passes atuais (800 créditos ociosos) e se foram compras próprias ou de terceiros.
- [ ] Conferir no painel do RapidAPI Hub se as 31 chamadas registradas geraram assinantes/receita externa antes de ativar o teto de 15 dossiês/dia em produção.
- [ ] Confirmar a URL exata da listagem principal do Grid402 no RapidAPI Hub a ser vinculada no CTA de Cartão USD do `/pass` e da Home (caso diferente das listagens já mapeadas em `docs/revenue/`).
