# Tasks: Infraestrutura Global M2M e Passes de Sessão x402

## 1. Configuração Central e Motor de Session Pass (`x402`)
- [x] 1.1 Atualizar `public_html/config.php` com as constantes de arquivos e tarifas dos 5 novos serviços (`lei`, `ofac`, `notarize`, `global-markets`, `pass`) e expandir o catálogo oficial para 19 serviços
- [x] 1.2 Criar `public_html/services/pass.php` com geração criptográfica de tokens `pass_g402_<id>_<hmac>`, persistência atômica via `flock(LOCK_EX)`, débito proporcional de créditos e consulta gratuita de saldo (`?status=pass_...`)
- [x] 1.3 Integrar validação de `pass_g402_...` dentro de `grid402_validate_payment()` em `public_html/x402.php` e adicionar suporte a `grid402_verify_usdc_transfer_amount()` para calcular créditos proporcionais ao valor transferido na Base

## 2. Implementação dos 4 Super-Endpoints Globais (Domínio Público / CC0)
- [x] 2.1 Criar `public_html/services/lei.php` consultando a base mundial CC0 da GLEIF (`api.gleif.org/api/v1/lei-records`) por código LEI ou nome corporativo (`q`), com cache e fallback determinístico
- [x] 2.2 Criar `public_html/services/ofac.php` cruzando endereços EVM e nomes contra listas públicas OFAC SDN / ONU + inspeção on-chain em tempo real na Base Mainnet (`eth_getBalance`, `eth_getTransactionCount`, `eth_getCode`, saldo USDC)
- [x] 2.3 Criar `public_html/services/notarize.php` para carimbo de tempo criptográfico e notarização matemática M2M (`certificate_id`, `input_sha256`, `notarized_at_utc`, `unix_epoch`)
- [x] 2.4 Criar `public_html/services/global_markets.php` com calendário financeiro determinístico (`US` NYSE/Fed, `EU` TARGET2/BCE, `UK` LSE, `BR` B3/ANBIMA) e taxas de câmbio globais (30+ moedas via BCE/Frankfurter Open Data)

## 3. Roteamento HTTP, Prévia Teaser 402 e Interface Bilíngue (`PT | EN`)
- [x] 3.1 Conectar as rotas `/api/v1/lei`, `/api/v1/ofac`, `/api/v1/notarize`, `/api/v1/global-markets` e `/api/v1/pass` em `public_html/index.php` com amostras gratuitas (`preview` e `locked_fields`) no HTTP 402
- [x] 3.2 Adicionar os 5 novos serviços nos cards da Home, nos presets do Playground Interativo, nos botões do Console e na documentação `llms.txt` / `/agents`
- [x] 3.3 Atualizar `public_html/assets/js/i18n.js` com todas as traduções em Português (`pt`) e Inglês (`en`) para os 19 serviços

## 4. Testes Automatizados e Deploy em Produção
- [x] 4.1 Criar e executar `tests/test_global_services_and_pass.php` cobrindo os cenários das specs (LEI, OFAC, Notarize, Global Markets e ciclo completo do Session Pass)
- [x] 4.2 Empacotar e publicar em `https://grid402.online` via Hostinger MCP e validar os 19 endpoints ao vivo
