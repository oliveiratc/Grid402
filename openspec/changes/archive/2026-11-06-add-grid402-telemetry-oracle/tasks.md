# Tasks: Grid402 Telemetry Oracle

## 1. Estrutura de Arquivos e Configuração

- [x] 1.1 Criar a estrutura de diretórios `public_html/` e `public_html/private_data/` com seus respectivos arquivos `.htaccess` e verificar que o acesso direto a `private_data/` é bloqueado
- [x] 1.2 Criar `.env.example` e `public_html/config.php` com carregamento de variáveis de ambiente, catálogo inicial de 15 a 20 alvos (SEFAZ, Gateways, Bancos, Telecom) e parâmetros da rede Base e verificar o carregamento de configurações em teste unitário

## 2. Motor de Telemetria e Coleta Concorrente

- [x] 2.1 Implementar em `public_html/collector.php` a sondagem paralela via `curl_multi_init` com timeout de 2.8s por alvo e captura resiliente de erros individuais
- [x] 2.2 Implementar autenticação de execução (CLI ou HTTP com `?token=CRON_SECRET`) e persistência atômica dos dados em `private_data/summary_free.json` e `private_data/telemetry_paid.json`
- [x] 2.3 Criar script de teste do coletor e verificar execução completa em tempo estritamente inferior a 4.0 segundos com geração dos arquivos JSON

## 3. Porteiro x402 e Validação On-Chain

- [x] 3.1 Implementar módulo de validação JSON-RPC da Base Mainnet em `public_html/index.php` verificando status, contrato USDC (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`), evento Transfer, carteira recebedora e valor mínimo de 0.0005 USDC
- [x] 3.2 Implementar mecanismo de prevenção a replay attacks gravando hashes consumidos em `private_data/used_txs.json` com rotina de limpeza de registros com mais de 7 dias
- [x] 3.3 Implementar o ciclo de respostas do `index.php`: entrega de `summary_free.json` (HTTP 200), emissão de desafio de pagamento (HTTP 402 com headers `WWW-Authenticate: x402`) e entrega do payload pago via `readfile()`
- [x] 3.4 Criar suíte de testes automatizados cobrindo os cenários das specs (consulta gratuita, desafio 402, recusa de replay attack e liquidação)

## 4. Interface Web e Manifesto para IA

- [x] 4.1 Implementar Landing Page dark-mode ultraleve (HTML/CSS puro responsivo) embutida em `public_html/index.php` para requisições com cabeçalho `Accept: text/html` e verificar visualmente
- [x] 4.2 Criar `public_html/llms.txt` documentando a proposta, endpoints, especificações x402 e exemplos de consumo em Python e cURL para crawlers e LLMs
- [x] 4.3 Configurar `public_html/.htaccess` na raiz com roteamento limpo, cabeçalhos de segurança (CORS, X-Frame-Options) e compressão LiteSpeed

## 5. Validação e Homologação Final

- [x] 5.1 Executar teste de ponta a ponta simulando ciclo completo de sondagem do cron, visualização web e consumo por agente autônomo M2M

## Workflow follow-up

- Validar todos os cenários das specs após implementação.
- Arquivar a change via OpenSpec CLI após aprovação do usuário.

