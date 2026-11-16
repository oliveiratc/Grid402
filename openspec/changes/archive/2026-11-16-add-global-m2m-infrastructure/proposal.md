# Proposal: Infraestrutura Global M2M e Passes de Sessão x402

## Por quê
Motivado pelo PRD `openspec/prds/global-m2m-infrastructure-and-session-pass.md`.
Para posicionar o Grid402 como infraestrutura básica e obrigatória para agentes autônomos e sistemas M2M em escala mundial, precisamos expandir o catálogo além das fontes brasileiras com 4 novos super-endpoints globais de dados abertos/CC0 (GLEIF LEI, OFAC/Web3 AML, Notarização Criptográfica e Mercados/Calendários Globais) e introduzir o **x402 Prepaid Session Pass** (`/api/v1/pass`), permitindo que agentes de alta frequência liquidem 1 única transação USDC na Base e executem centenas de chamadas subsequentes em milissegundos sem aguardar tempo de bloco a cada requisição.

## O que muda
- **Novo Serviço `/api/v1/lei` (`public_html/services/lei.php`):** Identidade corporativa mundial via base oficial CC0 da GLEIF (ISO 17442), permitindo busca por código LEI de 20 caracteres ou razão social/nome (`q`). Tarifa: `0.0020 USDC` (4 créditos).
- **Novo Serviço `/api/v1/ofac` (`public_html/services/ofac.php`):** Verificação internacional de sanções (OFAC SDN / ONU - Domínio Público) combinada com inspeção on-chain de carteiras EVM na Base Mainnet (`eth_getBalance`, `eth_getTransactionCount`, `eth_getCode` e saldo USDC). Tarifa: `0.0020 USDC` (4 créditos).
- **Novo Serviço `/api/v1/notarize` (`public_html/services/notarize.php`):** Carimbo de tempo criptográfico e notarização matemática M2M de qualquer hash SHA-256 ou payload, retornando certificado assinado via ECDSA `secp256k1`. Tarifa: `0.0005 USDC` (1 crédito).
- **Novo Serviço `/api/v1/global-markets` (`public_html/services/global_markets.php`):** Calendário financeiro determinístico de dias úteis e feriados globais (`US` NYSE/Fed, `EU` TARGET2/BCE, `UK` LSE, `BR` ANBIMA/B3) + taxas de câmbio globais (30+ moedas via BCE/Frankfurter Open Data). Tarifa: `0.0005 USDC` (1 crédito).
- **Novo Serviço e Motor de Passes `/api/v1/pass` (`public_html/services/pass.php` + `public_html/x402.php`):**
  - Emissão de passes pré-pagos (`pass_g402_...`) assinados criptograficamente quando o cliente envia uma transação USDC na Base (ex: `0.05 USDC` = 100 créditos, `0.50 USDC` = 1.000 créditos, proporcional a `0.0005 USDC` por crédito).
  - Suporte nativo no validador `grid402_validate_payment()` para aceitar `X-Payment: pass_g402_...`, debitando o custo em créditos do endpoint requisitado (1 crédito para endpoints de `0.0005`, 2 para `0.0010`, 4 para `0.0020`, 6 para `0.0030`) em < 15ms com lock exclusivo (`flock`).
  - Consulta gratuita de saldo e validade via `GET /api/v1/pass?status=<token>`.
- **Atualização da Interface, Playground, Console, i18n (`PT | EN`) e Manifestos:**
  - Catálogo expandido para **19/19 Serviços Operacionais**.
  - Dicionário `public_html/assets/js/i18n.js` atualizado em Português e Inglês.
  - Atualização de `llms.txt`, `openapi.json` e `.well-known/x402.json`.

## Impacto
- **Specs afetadas:** `open-data-utilities`, `x402-payment-oracle`, `i18n`.
- **Código afetado:**
  - `public_html/config.php`
  - `public_html/x402.php`
  - `public_html/index.php`
  - `public_html/assets/js/i18n.js`
  - `public_html/services/lei.php` (novo)
  - `public_html/services/ofac.php` (novo)
  - `public_html/services/notarize.php` (novo)
  - `public_html/services/global_markets.php` (novo)
  - `public_html/services/pass.php` (novo)
  - `tests/test_global_services_and_pass.php` (novo)

