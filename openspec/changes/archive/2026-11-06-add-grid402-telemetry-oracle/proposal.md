# Proposal: Grid402 Telemetry Oracle

## Why
Agentes autônomos de IA e sistemas M2M que operam no mercado brasileiro precisam verificar em tempo real a integridade e latência de serviços críticos (SEFAZ estaduais, PIX/Bancos, Gateways de pagamento e Telecom) antes de disparar transações em lote. Atualmente não existe um oráculo leve, padronizado para IA e monetizado sob demanda com micropagamentos por requisição via protocolo HTTP x402.

A referência completa dos requisitos está em [openspec/prds/grid402-oraculo-telemetria.md](../../prds/grid402-oraculo-telemetria.md).

## What Changes
- Adiciona o motor de coleta assíncrona paralela (`collector.php`) monitorando 15 a 20 alvos críticos em menos de 4 segundos via `curl_multi_init`.
- Adiciona a persistência atômica em arquivos JSON isolados (`summary_free.json` e `telemetry_paid.json`) dentro do diretório protegido `private_data/` com `.htaccess` bloqueando acesso HTTP direto.
- Adiciona o porteiro da API e oráculo de pagamento (`index.php`) com suporte nativo ao protocolo HTTP x402 (`402 Payment Required`), validando micropagamentos de 0.0005 USDC na rede Base via chamadas JSON-RPC.
- Adiciona proteção contra replay attacks registrando transações já liquidadas em `private_data/used_txs.json` com expiração por TTL.
- Adiciona detecção de cabeçalho `Accept` servindo Landing Page dark-mode ultraleve em HTML/CSS para humanos (`text/html`) e dados/desafios 402 para clientes automatizados e agentes M2M.
- Adiciona o manifesto `llms.txt` na raiz para descoberta autônoma por LLMs e bots.
- Adiciona suporte a segredos e variáveis sensíveis via `.env` protegido fora da raiz pública.

## Capabilities

### New Capabilities
- `telemetry-collector`: Coleta de telemetria paralela de serviços críticos brasileiros via cURL Multi em tempo < 4s, persistência em JSON e controle de execução via CLI/Token seguro.
- `x402-payment-oracle`: Porteiro HTTP x402 com validação on-chain de transações USDC na Base, prevenção a replay attacks, resposta adaptativa (HTML/JSON) e entrega de telemetria detalhada.

### Modified Capabilities
<!-- Nenhuma capacidade existente está sendo modificada nesta criação inicial. -->

## Impact
- **Arquivos Criados:** `public_html/index.php`, `public_html/collector.php`, `public_html/config.php`, `public_html/.htaccess`, `public_html/llms.txt`, `public_html/private_data/.htaccess`, `.env.example`, `.gitignore`.
- **Dependências de Infraestrutura:** PHP 8.1+ com extensões `curl` e `json` em servidor web LiteSpeed (Hostinger Shared Hosting). Sem dependência de MySQL ou SQLite.
- **Redes e APIs Externas:** Rede Base Mainnet (JSON-RPC `https://mainnet.base.org`), Contrato USDC Base (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`).

