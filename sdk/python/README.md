# Grid402 Python SDK ⚡

[![PyPI version](https://img.shields.io/badge/pypi-v2.0.0-blue.svg)](https://pypi.org/project/grid402/)
[![Base Mainnet](https://img.shields.io/badge/Network-Base%20(8453)-0052FF.svg)](https://base.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Grid402 Status](https://www.grid402.com.br/badge/system.svg)](https://www.grid402.com.br)

SDK oficial em Python (zero dependências externas) para o **Grid402** — Hub de Alta Performance de Utilitários de Rede e Dados Públicos com micropagamentos via protocolo **x402** na rede **Base Mainnet** (US$ 0.0005 USDC por consulta).

Ideal para robôs autônomos, agentes de IA (LangChain, CrewAI, AutoGen) e esteiras de automação M2M.

---

## 🚀 Funcionalidades

* **Zero Dependências:** Construído unicamente sobre a biblioteca padrão do Python (`urllib`, `ssl`, `json`).
* **6 Micro-serviços Técnicos:**
  1. `forex`: Cotações fiduciárias em tempo real (USD, EUR, GBP para BRL).
  2. `crypto`: Preços spot de criptoativos (BTC, ETH, SOL, USDC, BASE).
  3. `dns`: Resolução de registros RFC 1035 (A, AAAA, MX, TXT, NS, CNAME).
  4. `ssl`: Inspeção TLS na porta 443 (validade, emissor, SANs e dias restantes).
  5. `asn`: Resolução BGP de sistema autônomo, organização e país.
  6. `cep`: Consulta de endereço público e código oficial IBGE do município.
* **Resumo Gratuito:** Rota `/api/v1/summary` aberta para checagem rápida de saúde e discovery.
* **Protocolo x402 Nativo:** Suporte a cabeçalhos `X-Payment` e tratamento automático de desafios HTTP 402.
* **Circuit Breaker (`guard`):** Proteção defensiva de tarefas batch contra serviços indisponíveis.

---

## 📦 Instalação

```bash
pip install grid402
```

---

## ⚡ Guia Rápido

### 1. Resumo Gratuito e Checagem de Saúde
```python
from grid402 import Grid402Client

client = Grid402Client()

# Resumo executivo gratuito
summary = client.get_summary()
print("Status do Hub:", summary["status"])

# Checagem booleana rápida
if client.is_healthy("forex"):
    print("Módulo de câmbio operacional!")
```

### 2. Consultas Técnicas via Micropagamento x402
Cada consulta técnica custa **US$ 0.0005 USDC** liquidado diretamente na Base Mainnet.

```python
from grid402 import Grid402Client, PaymentRequiredError

client = Grid402Client()
tx_hash = "0x<sua_transacao_base_mainnet>"

try:
    # 1. Câmbio fiduciário
    forex = client.get_forex(payment_tx_hash=tx_hash)
    print("USD/BRL:", forex["rates"]["USD"]["bid"])

    # 2. Resolução DNS
    dns = client.get_dns("github.com", payment_tx_hash=tx_hash)
    print("Registros A:", dns["records"]["A"])

    # 3. Inspetor SSL
    ssl_info = client.get_ssl("base.org", payment_tx_hash=tx_hash)
    print("Dias até expirar SSL:", ssl_info["certificate"]["days_remaining"])

    # 4. Consulta CEP e IBGE
    cep = client.get_cep("01310100", payment_tx_hash=tx_hash)
    print("Município IBGE:", cep["ibge"])

except PaymentRequiredError as p:
    # Desafio 402 retornado caso não haja hash ou seja insuficiente
    print(f"Envie {p.price_usdc} USDC para {p.recipient_wallet} na Base (Chain ID 8453).")
```

---

## 🤖 Conector para Agentes de IA (CrewAI / LangChain)

```python
from grid402_tool import create_grid402_tool, GRID402_FUNCTION_SCHEMAS

# Função invocável pelo LLM
grid_tool = create_grid402_tool()

# Exemplo de chamada pelo agente
resultado_dns = grid_tool(service="dns", target="cloudflare.com")
print(resultado_dns)
```

---

## 📄 Termos e Conformidade Legal
- **Pessoa Física / Isenção:** Dados disponibilizados estritamente no estado em que se encontram (AS-IS).
- **Zero PII / LGPD:** Todos os endpoints processam exclusivamente dados públicos de domínio e protocolo de rede.
