# Pivot para Hub de Utilitários de Rede & Dados Públicos Abertos (Grid402)

## Por quê
Conforme especificado no PRD ([`openspec/prds/grid402-open-data-utility-hub.md`](file:///c:/Antigravity/Projetos/Grid402/openspec/prds/grid402-open-data-utility-hub.md)), o foco do projeto é pivotado para prover um portal e oráculo de micro-serviços técnicos de utilidade pública e rede voltado para agentes de IA, robôs e pipelines de automação/scraping. 

Esta mudança elimina integralmente os riscos jurídicos de LGPD (zero dados pessoais/PII tratados), riscos de marcas corporativas e responsabilidade civil por indisponibilidade de terceiros, viabilizando uma operação 100% segura e compatível para **Pessoa Física**, mantendo micropagamentos x402 na Base Mainnet por US$ 0.0005 USDC.

## O que muda
- **Novo Catálogo Core (6 APIs de Utilitários):**
  1. `Câmbio & Moedas`: Cotações de USD, EUR, GBP para BRL com variação e timestamp.
  2. `Cripto Tickers`: Preços de BTC, ETH, SOL, USDC e BASE.
  3. `DNS Records Lookup`: Resolução de registros A, AAAA, MX, TXT, NS e CNAME de qualquer host.
  4. `Inspetor SSL`: Validade, dias até expirar, emissor e SANs de certificados TLS/SSL.
  5. `ASN & IP Info`: Metadados de roteamento BGP, provedor e geolocalização pública de IP.
  6. `CEP & IBGE`: Logradouro, bairro, município, UF e código IBGE via base de dados públicos.
- **Porteiro x402 Reforçado:**
  - Implementação de trava atômica (`flock(LOCK_EX)`) para eliminar vulnerabilidade de condição de corrida (*TOCTOU*) na checagem anti-replay.
  - Manutenção do preço de US$ 0.0005 USDC (500 unidades atômicas) na Base Mainnet.
- **Camada Gratuita:** Resumo agregado e status de saúde do hub via `/status` e `/api/v1/summary`.
- **Interface e Documentação:**
  - Atualização da Landing Page dark-mode e do console interativo com abas para cada uma das 6 ferramentas.
  - Atualização do manifesto `llms.txt` para descoberta nativa por agentes de IA.
  - Atualização dos Badges SVG e SDKs (Python e Node.js).

## Impacto
- **Specs afetadas:**
  - `open-data-utilities` (novo domínio)
  - `x402-payment-oracle` (modificado para novas rotas e trava atômica)
  - `telemetry-collector` (modificado para dados de mercado e utilitários)
- **Código afetado:**
  - `public_html/config.php`: Novos alvos, endpoints e constantes de dados públicos.
  - `public_html/collector.php`: Motor de atualização periódica de Câmbio e Cripto.
  - `public_html/x402.php`: Validação on-chain com trava atômica anti-replay `flock`.
  - `public_html/index.php`: Roteamento amigável das 6 APIs, console interativo renovado.
  - `public_html/badge.php`: Suporte a badges dos utilitários públicos.
  - `public_html/llms.txt`: Documentação técnica das novas rotas.
  - `sdk/python/` e `sdk/node/`: Novos métodos de conveniência para os utilitários.
  - `tests/`: Bateria completa de testes automatizados ponta a ponta.

