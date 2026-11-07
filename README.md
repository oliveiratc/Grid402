# Grid402 // Oráculo M2M de Telemetria de Serviços Críticos

[![CI](https://github.com/oliveiratc/Grid402/actions/workflows/ci.yml/badge.svg)](https://github.com/oliveiratc/Grid402/actions)
![Network](https://img.shields.io/badge/Network-Base_Mainnet_(8453)-0052FF?logo=coinbase)
![Payment](https://img.shields.io/badge/Payment-0.0005_USDC-2775CA?logo=usd-coin)
![Protocol](https://img.shields.io/badge/Protocol-HTTP_x402-10B981)
![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-777BB4?logo=php)
![Server](https://img.shields.io/badge/Server-Hostinger_LiteSpeed-blue)

> Infraestrutura de telemetria em tempo real para agentes autônomos de IA e sistemas M2M auditarem a disponibilidade e latência de APIs críticas brasileiras (SEFAZ, PIX, Gateways de Pagamento e Telecom) antes de executarem lotes de transações.

---

## ⚡ Destaques da Arquitetura

* **Monetização Nativa via HTTP x402:** Cobrança sob demanda de **US$ 0.0005 por consulta detalhada**, liquidada em **USDC na rede Base** (Chain ID: 8453).
* **Zero Banco de Dados (Zero-DB):** Elimina gargalos de conexões concorrentes na hospedagem compartilhada, utilizando flat-files JSON com gravações atômicas (`tempnam()` + `rename()`).
* **Coleta Assíncrona Ultrarrápida:** Coletor concorrente (`collector.php`) via `curl_multi_init` testando 18 alvos em menos de **800 milissegundos** (limite contratual < 4s).
* **Validação On-Chain Direta:** Consulta de recibos (`eth_getTransactionReceipt`) diretamente no JSON-RPC público da Base checando evento `Transfer` do contrato oficial do USDC (`0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`).
* **Proteção Contra Replay Attack:** Histórico local em `used_txs.json` garantindo que cada hash de transação só libere uma única consulta, com rotação automática por TTL de 7 dias.
* **Interface Adaptativa:** Landing page dark-mode em HTML/CSS puro para visitantes humanos (`Accept: text/html`) e respostas JSON ultrarrápidas (< 5ms) para agentes de IA e clientes M2M.
* **Descoberta para IA:** Manifesto [`public_html/llms.txt`](public_html/llms.txt) padronizado para indexação e consumo por LLMs.

---

## 📁 Estrutura de Arquivos

```text
├── .env.example                       # Modelo de variáveis de ambiente
├── .gitignore                         # Bloqueio de arquivos sensíveis e temporários
├── PRD.md                             # Documento de Requisitos do Produto
├── README.md                          # Este guia completo de operação
├── openspec/                          # Especificações OpenSpec
│   ├── prds/grid402-oraculo-telemetria.md
│   ├── specs/telemetry-collector/spec.md
│   └── specs/x402-payment-oracle/spec.md
├── public_html/                       # Pasta pública (upload para a Hostinger)
│   ├── .htaccess                      # Roteamento, CORS, segurança e LiteSpeed
│   ├── index.php                      # Porteiro x402, API e Landing Page
│   ├── collector.php                  # Motor de coleta paralela cURL Multi
│   ├── config.php                     # Catálogo de 18 alvos e carregador .env
│   ├── x402.php                       # Validador on-chain Base e replay protection
│   ├── llms.txt                       # Manifesto para agentes de IA
│   └── private_data/                  # Diretório de persistência seguro
│       ├── .htaccess                  # Bloqueio total de acesso direto
│       ├── summary_free.json          # Resumo executivo público
│       ├── telemetry_paid.json        # Telemetria técnica individual
│       └── used_txs.json              # Registro de transações consumidas
└── tests/                             # Suíte de testes automatizados
    ├── test_config.php                # Validação de alvos e parâmetros
    ├── test_collector.php             # Teste de execução rápida (< 4s)
    ├── test_x402_gateway.php          # Teste de replay attack, expurgo e RPC Base
    ├── test_http_server.php           # Teste de integração HTTP e rotas
    └── test_e2e_full.php              # Bateria de homologação ponta a ponta
```

---

## 🛠️ Guia de Instalação e Deploy (Hostinger Shared Hosting)

### 1. Upload dos Arquivos
Suba todo o conteúdo da pasta `public_html/` para o diretório `public_html/` da sua conta na Hostinger.

### 2. Configuração de Variáveis de Ambiente (`.env`)
Por questões de segurança, crie o arquivo `.env` um nível acima da raiz pública (ex: `/home/u123456789/.env`) ou dentro de `/home/u123456789/public_html/private_data/.env`:

```ini
GRID402_WALLET_ADDRESS=0xa31c31069c43ac1c0075a93c325b0dcd7744356c
GRID402_CRON_SECRET=defina_um_token_secreto_longo_aqui_123456
GRID402_BASE_RPC_URL=https://mainnet.base.org
GRID402_USDC_CONTRACT=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913
GRID402_USDC_REQUIRED_RAW=500
GRID402_PRICE_DISPLAY=0.0005
```

### 3. Configuração do Cron Job no hPanel
No painel da Hostinger (**Avançado ➔ Tarefas Cron**), configure uma rotina periódica:
* **Frequência:** A cada 2 a 5 minutos (`*/2 * * * *` ou `*/5 * * * *`).
* **Comando:**
  ```bash
  php /home/u123456789/public_html/collector.php
  ```
*(Alternativamente, é possível acionar via ping externo seguro: `https://www.grid402.com.br/collector.php?token=SEU_TOKEN_SECRETO`)*.

---

## 🤖 Como Consumir via Agentes Autônomos (Protocolo x402)

### 1. Resumo Gratuito (Sem Pagamento)
```bash
curl -H "Accept: application/json" https://www.grid402.com.br/status?format=json
```

### 2. Telemetria Detalhada (Fluxo x402)

#### Passo A: Solicitação e Desafio (Recebe HTTP 402)
```bash
curl -i -H "Accept: application/json" https://www.grid402.com.br/telemetry
```
*Retorno:*
```http
HTTP/1.1 402 Payment Required
WWW-Authenticate: x402
X-Accept-Payment: base:usdc:0xa31c31069c43ac1c0075a93c325b0dcd7744356c:0.0005
Content-Type: application/json

{
  "error": "PaymentRequired",
  "network": "Base",
  "chain_id": 8453,
  "token": "USDC",
  "price_usdc": "0.0005",
  "recipient_wallet": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c"
}
```

#### Passo B: Envio do Comprovante On-Chain (Recebe HTTP 200)
Após o agente liquidar a transferência de 0.0005 USDC na Base, reenvie com o cabeçalho `X-Payment`:
```bash
curl -H "Accept: application/json" \
     -H "X-Payment: 0x<tx_hash_da_transferencia_na_base>" \
     https://www.grid402.com.br/telemetry
```

---

## 🧪 Execução dos Testes Automatizados Locais

Com o PHP 8.1+ instalado na máquina:

```powershell
# Executar a bateria completa ponta a ponta
php tests/test_e2e_full.php
```

---

## 📄 Licença
Distribuído sob licença comercial / M2M aberta. Consulte [llms.txt](public_html/llms.txt) para detalhes de termos e uso.

