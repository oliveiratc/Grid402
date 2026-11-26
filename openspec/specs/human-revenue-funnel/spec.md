# human-revenue-funnel Specification

## Purpose

Maximizar a aquisição orgânica e a conversão de compradores humanos e empresas no Brasil (Pix R$ 12+) por meio do gerador web self-service de Dossiê 360º KYB em PDF, páginas SEO SSR em PT-BR, melhorias de conversão no Hero da home e pacotes operacionais de distribuição paga e prospecção B2B.

## Requirements

### Requirement: Gerador Web Self-Service de Dossiê 360º KYB com PDF (`/dossie-kyb`)
O sistema MUST disponibilizar `GET /dossie-kyb` em PT-BR (SSR) acessível pela navbar global, oferecendo amostra real completa e estática (dados e PDF via `@media print`), checkout Pix inline com escolha entre **Micro (R$ 4,90 / 5 dossiês)** e **Starter (R$ 12,00 / 40 dossiês)** respeitando `data.qr_mime`, e uso de token `pass_g402_...` sincronizado pela persistência unificada.

#### Scenario: Renderização SSR da página /dossie-kyb em português
- **GIVEN** um visitante acessando `GET /dossie-kyb`
- **WHEN** o servidor responde
- **THEN** retorna HTTP 200 com `<html lang="pt-BR">`, um único `<h1>`, formulário acessível de consulta de CNPJ, acesso à amostra real completa, fluxo inline de pagamento Pix (Micro R$ 4,90 e Starter R$ 12) e folha `@media print` dedicada para exportação em PDF

#### Scenario: Abertura da amostra real não exibe texto placeholder
- **GIVEN** um visitante sem Session Pass na página `/dossie-kyb`
- **WHEN** aciona a visualização da amostra real de Dossiê 360º KYB
- **THEN** a interface renderiza todos os blocos do relatório completo para o CNPJ exemplo (veredito, score, dados cadastrais, Simples/MEI, Sanções CGU e prova de exemplo rotulada como amostra) e habilita a impressão/PDF de demonstração

#### Scenario: Renderização do QR Code Pix respeita data.qr_mime
- **GIVEN** o endpoint `POST /api/v1/pass/mp-pix` retornando `qr_mime: "image/svg+xml"` e `qr_code_base64`
- **WHEN** `/dossie-kyb` exibe o QR Code gerado
- **THEN** o atributo `src` da imagem SHALL usar `data:image/svg+xml;base64,...` (e `data:image/png;base64,...` quando `qr_mime` for `image/png` ou ausente)

#### Scenario: Consulta direta usando Session Pass existente
- **GIVEN** um usuário na página `/dossie-kyb` com um token `pass_g402_...` válido
- **WHEN** envia o CNPJ com seu token
- **THEN** a interface consulta `GET /api/v1/dossier?cnpj=...`, exibe o veredito (`APPROVED`/`FLAGGED`/`REJECTED`), o score 0–100, os blocos cadastrais e o botão de impressão/PDF

### Requirement: Páginas SEO de Alta Intenção em PT-BR e Sitemap Atualizado
O sistema MUST disponibilizar 3 landing pages dedicadas 100% SSR em PT-BR (`/api-cnpj`, `/api-sancoes-cgu` e `/api-dias-uteis-anbima`) roteadas via `.htaccess`, contendo JSON-LD estruturado, exemplos práticos em `curl -sL`, Python e n8n, e CTA 1-clique para `/pass?tier=starter&method=pix` e `/dossie-kyb`, além de listar todas elas e `/pass` em `public_html/sitemap.xml`.

#### Scenario: Acesso às 3 landing pages SEO PT-BR
- **GIVEN** um crawler do Google ou visitante acessando `/api-cnpj`, `/api-sancoes-cgu` ou `/api-dias-uteis-anbima`
- **WHEN** a requisição HTTP GET é processada
- **THEN** cada rota responde HTTP 200 com `<html lang="pt-BR">`, `<title>` e `<meta name="description">` únicos, único `<h1>`, bloco `application/ld+json` válido e link direto para `/pass?tier=starter&method=pix`

#### Scenario: Cobertura completa no sitemap.xml
- **GIVEN** o arquivo `public_html/sitemap.xml`
- **WHEN** inspecionado
- **THEN** contém entradas válidas para `/pass`, `/dossie-kyb`, `/api-cnpj`, `/api-sancoes-cgu` e `/api-dias-uteis-anbima`

### Requirement: CTA de Pix no Hero da Home e Auto-Detecção de Idioma PT-BR
A página inicial (`/`) SHALL manter seu HTML SSR em inglês (preservando SEO global e compatibilidade de testes), adicionar botão direto de compra via Pix (`R$ 12 = 400 créditos`) e atalho para `/dossie-kyb` no Hero, e auto-detectar visitantes de idioma `pt*` no navegador via `assets/js/i18n.js` quando nenhuma preferência prévia estiver salva.

#### Scenario: Presença do CTA Pix no Hero da Home
- **GIVEN** um visitante acessando `GET /`
- **WHEN** o HTML SSR da landing page é renderizado
- **THEN** o Hero contém link direto para `/pass?tier=starter&method=pix` e link para `/dossie-kyb`

### Requirement: Pacotes Operacionais de Distribuição Paga e Prospecção B2B (`docs/revenue/`)
O repositório MUST conter a documentação pronta para execução comercial da Onda 2 em `docs/revenue/`, cobrindo `rapidapi-pack.md`, `n8n-creator-submission.md`, `b2b-outbound-playbook.md` e `directory-checklist.md`.

#### Scenario: Disponibilidade dos pacotes comerciais em docs/revenue/
- **GIVEN** o operador iniciando a Onda 2 de tração comercial
- **WHEN** verifica o diretório `docs/revenue/`
- **THEN** os 4 arquivos (`rapidapi-pack.md`, `n8n-creator-submission.md`, `b2b-outbound-playbook.md` e `directory-checklist.md`) existem e contêm instruções completas, preços coerentes (`R$ 12 / R$ 29 / R$ 115` e `0.0050–0.0500 USDC`) e scripts prontos para uso

### Requirement: Cópia Única de Endereço e Retry Resiliente no Top-Up Web3 da Home
Em `public_html/index.php`, o fluxo de Top-Up Web3 MUST não duplicar `copyOracleAddress()` nem o ID `btn-copy-oracle`, MUST executar ao menos 5 retries com backoff em `startWeb3Topup()` contra `/api/v1/pass-emitir`, e MUST preservar o link do `txHash` visível e preenchido em `#manual-pass-tx-input` em caso de falha ou timeout.

#### Scenario: Ausência de duplicidade de função copyOracleAddress e ID btn-copy-oracle
- **GIVEN** o HTML e script renderizados de `GET /`
- **WHEN** o documento é inspecionado
- **THEN** SHALL existir exatamente uma definição de `copyOracleAddress` e nenhum ID `btn-copy-oracle` duplicado no DOM

#### Scenario: Falha após envio de transação Web3 preserva txHash e executa retries
- **GIVEN** `startWeb3Topup()` em `public_html/index.php`
- **WHEN** a transação retorna um `txHash` e `/api/v1/pass-emitir` responde pendente nas primeiras tentativas
- **THEN** o cliente realiza pelo menos 5 tentativas com espera incremental e, se ainda falhar, mantém o `txHash` exibido com link do explorer e preenchido em `#manual-pass-tx-input`

### Requirement: Persistência Unificada de Session Pass Entre Páginas
As páginas Home, `/pass`, `/dossie-kyb` e `/payment-dashboard` MUST compartilhar a chave canônica `grid402_active_pass_token` em `localStorage` e o cookie `g402_pass_token` (`path=/; SameSite=Lax`), mantendo leitura fallback das chaves legadas e carregamento automático em `/payment-dashboard` quando `?token=` não for informado.

#### Scenario: Token emitido no `/pass` é reconhecido automaticamente nas demais páginas
- **GIVEN** um Session Pass `pass_g402_...` recém-emitido via Pix, Promo ou Web3 em `/pass` ou na Home
- **WHEN** o usuário navega para `/dossie-kyb`, `/` (Playground) ou `/payment-dashboard` sem query string
- **THEN** todas essas páginas recuperam automaticamente o mesmo token `pass_g402_...` da persistência unificada (`grid402_active_pass_token` / cookie `g402_pass_token`)

### Requirement: Conversão de Bloqueios HTTP 429 e Desafios 402 com Cupom `MCPTRY50` em Inglês
Quando uma chamada de API em modo Sandbox atingir a cota diária (HTTP `429`), a resposta JSON em `public_html/index.php` e `public_html/x402.php` MUST promover o cupom `MCPTRY50` (`https://grid402.online/pass?promo=MCPTRY50`) em inglês, link `/pass` sem `autopix=1` forçado e RapidAPI USD, omitindo Solana com flag desligada.

#### Scenario: Resposta HTTP 429 promove MCPTRY50 em inglês e opções de upgrade válidas
- **GIVEN** um IP que excedeu a cota diária de 3 consultas em `X-Payment: demo`
- **WHEN** envia nova requisição `GET /api/v1/forex` com `X-Payment: demo`
- **THEN** o servidor retorna HTTP `429` com JSON contendo `MCPTRY50`, `https://grid402.online/pass?promo=MCPTRY50`, `upgrade_to_production.rapidapi_credit_card_usd` e lista `supported_chains` sem `solana` quando a flag estiver desligada
