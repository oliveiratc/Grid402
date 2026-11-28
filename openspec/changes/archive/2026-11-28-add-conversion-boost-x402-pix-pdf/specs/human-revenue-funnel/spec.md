# Delta Spec: human-revenue-funnel

## MODIFIED Requirements

### Requirement: Gerador Web Self-Service de Dossiê 360º KYB com PDF (`/dossie-kyb`)
O sistema MUST disponibilizar `GET /dossie-kyb` em PT-BR (SSR) na navbar global, oferecendo amostra real completa, checkout Pix inline (**Micro R$ 4,90** e **Starter R$ 12,00**), persistência unificada de Session Pass, botão principal para download binário `.pdf` via `POST /api/v1/dossier/pdf` e fallback `window.print()`.

#### Scenario: Renderização SSR da página /dossie-kyb em português
- **GIVEN** um visitante acessando `GET /dossie-kyb`
- **WHEN** o servidor responde
- **THEN** retorna HTTP 200 com `<html lang="pt-BR">`, um único `<h1>`, formulário acessível de CNPJ, acesso à amostra real, checkout Pix inline (Micro R$ 4,90 e Starter R$ 12), botão principal de download `.pdf` via `/api/v1/dossier/pdf` e botão fallback `@media print`

#### Scenario: Abertura da amostra real não exibe texto placeholder
- **GIVEN** um visitante sem Session Pass na página `/dossie-kyb`
- **WHEN** aciona a visualização da amostra real de Dossiê 360º KYB
- **THEN** a interface renderiza todos os blocos do relatório completo para o CNPJ exemplo e habilita o download `.pdf` demonstrativo (além da impressão fallback)

#### Scenario: Renderização do QR Code Pix respeita data.qr_mime
- **GIVEN** o endpoint `POST /api/v1/pass/mp-pix` retornando `qr_mime: "image/svg+xml"` e `qr_code_base64`
- **WHEN** `/dossie-kyb` exibe o QR Code gerado
- **THEN** o atributo `src` da imagem SHALL usar `data:image/svg+xml;base64,...` (e `data:image/png;base64,...` quando `qr_mime` for `image/png` ou ausente)

#### Scenario: Consulta direta usando Session Pass existente
- **GIVEN** um usuário na página `/dossie-kyb` com um token `pass_g402_...` válido
- **WHEN** envia o CNPJ com seu token
- **THEN** a interface consulta `GET /api/v1/dossier?cnpj=...`, exibe o veredito (`APPROVED`/`FLAGGED`/`REJECTED`), o score 0–100, os blocos cadastrais e habilita o download `.pdf` oficial autenticado por `_oracle_proof`

### Requirement: Conversão de Bloqueios HTTP 429 e Desafios 402 com Cupom `MCPTRY50` em Inglês
Ao atingir o limite Sandbox (HTTP `429`), as respostas JSON M2M (`index.php`, `x402.php`) MUST orientar canais pagos (`x402`, `/pass`, RapidAPI USD) omitindo Solana com flag desligada e **MUST NOT** anunciar `promo-claim` ou créditos grátis; na UI humana da Home, o botão inline `MCPTRY50` SHALL aparecer apenas enquanto a campanha estiver ativa.

#### Scenario: Resposta HTTP 429 promove MCPTRY50 em inglês e opções de upgrade válidas
- **GIVEN** um IP que excedeu a cota diária de 3 consultas em `X-Payment: demo`
- **WHEN** envia nova requisição `GET /api/v1/forex` com `X-Payment: demo`
- **THEN** o servidor retorna HTTP `429` com JSON em inglês contendo `required_raw_units`, `required_usdc`, `accepts[]`, `upgrade_to_production.rapidapi_credit_card_usd` e `supported_chains` sem `solana` quando a flag estiver desligada
- **AND** a resposta JSON M2M SHALL NOT anunciar `MCPTRY50` nem `/api/v1/pass/promo-claim` para evitar abuso programático por bots

#### Scenario: Botão de cupom MCPTRY50 no card 429 da Home respeita vigência da campanha
- **GIVEN** o HTML SSR da Home (`/`) renderizado com a campanha `MCPTRY50` ativa vs. expirada ou esgotada
- **WHEN** o card `#playground-rate-limit-card` é inspecionado
- **THEN** o botão de resgate inline de `MCPTRY50` SHALL estar presente somente quando `grid402_promo_get_status('MCPTRY50')['is_active'] === true`
- **AND** quando expirada ou esgotada, o card SHALL ocultar o botão de cupom mantendo Pix inline, Session Pass e Cartão USD

## ADDED Requirements

### Requirement: Checkout Pix Inline e Reexecução Automática no Card HTTP 429 do Playground
Ao atingir HTTP `429` no Playground da Home (`/`), o card `#playground-rate-limit-card` MUST oferecer checkout **Pix inline** (`Micro R$ 4,90` e `Starter R$ 12,00`), gerar QR Code + Copia e Cola com polling automático, salvar o token aprovado na persistência unificada e reexecutar a consulta automaticamente.

#### Scenario: Card 429 da Home renderiza controles Pix inline sem sair da página
- **GIVEN** um visitante acessando `GET /`
- **WHEN** a página inicial é renderizada
- **THEN** `#playground-rate-limit-card` SHALL conter controles inline para escolher pacote `micro` (`R$ 4,90`) ou `starter` (`R$ 12,00`), e-mail para recibo, geração de QR Code via `POST /api/v1/pass/mp-pix` (`qr_mime`), polling em `/api/v1/pass/mp-status` e chamada automática a `runPlayground()` após aprovação

### Requirement: Geração Binária de PDF 1.4 do Dossiê KYB Autenticada por `_oracle_proof` (`POST /api/v1/dossier/pdf`)
O gateway MUST expor `POST /api/v1/dossier/pdf` sobre gerador **PDF 1.4 nativo em PHP puro** (`public_html/services/dossier_pdf.php`, sem dependências externas e Zero-PII), validando `_oracle_proof` com `grid402_verify_oracle_proof()` antes de devolver download `%PDF-1.4` com QR Code vetorial de verificação.

#### Scenario: Download de PDF válido para Dossiê autenticado por _oracle_proof sem gravar em disco
- **GIVEN** um payload de Dossiê 360º KYB assinado por `grid402_sign_data()` com `_oracle_proof` íntegro
- **WHEN** é enviado via `POST /api/v1/dossier/pdf`
- **THEN** o servidor retorna HTTP `200` com `Content-Type: application/pdf`, `Content-Disposition: attachment` e corpo iniciando com `%PDF-1.4` e terminando com `%%EOF`
- **AND** o PDF inclui cabeçalho Grid402, veredito, score, blocos completos, QR Code vetorial e nenhum arquivo temporário de dossiê é gravado em `private_data/`

#### Scenario: Rejeição de payload adulterado ou sem assinatura válida do oráculo
- **GIVEN** um payload enviado para `POST /api/v1/dossier/pdf` sem `_oracle_proof` ou com `verdict.score` ou `cnpj` adulterados
- **WHEN** o servidor valida a prova criptográfica
- **THEN** SHALL recusar com HTTP `403` (`InvalidOracleProof`) e SHALL NOT retornar arquivo `application/pdf`

#### Scenario: Geração de PDF para amostra ilustrativa recebe marcação obrigatória de amostra
- **GIVEN** a requisição `POST /api/v1/dossier/pdf` com o CNPJ de demonstração oficial e indicador de amostra ilustrativa
- **WHEN** o servidor gera o PDF
- **THEN** retorna HTTP `200` com `%PDF-1.4` válido exibindo a marcação `AMOSTRA ILUSTRATIVA` no documento
