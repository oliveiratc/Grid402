# human-revenue-funnel Specification

## Purpose

Maximizar a aquisição orgânica e a conversão de compradores humanos e empresas no Brasil (Pix R$ 12+) por meio do gerador web self-service de Dossiê 360º KYB em PDF, páginas SEO SSR em PT-BR, melhorias de conversão no Hero da home e pacotes operacionais de distribuição paga e prospecção B2B.

## Requirements

### Requirement: Gerador Web Self-Service de Dossiê 360º KYB com PDF (`/dossie-kyb`)
O sistema MUST disponibilizar a rota pública `GET /dossie-kyb` renderizada 100% em PT-BR (SSR), permitindo que qualquer pessoa ou empresa informe um CNPJ, visualize uma prévia gratuita real, pague o Starter Pass (R$ 12,00 = 400 créditos / 40 dossiês) via Pix inline na própria página (ou utilize um token `pass_g402_...` existente) e obtenha o Dossiê 360º visual completo com exportação para PDF via `@media print` estilizado.

#### Scenario: Renderização SSR da página /dossie-kyb em português
- **GIVEN** um visitante acessando `GET /dossie-kyb`
- **WHEN** o servidor responde
- **THEN** retorna HTTP 200 com `<html lang="pt-BR">`, um único `<h1>`, formulário acessível de consulta de CNPJ, fluxo inline de pagamento Pix (R$ 12) e folha `@media print` dedicada para exportação em PDF

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
