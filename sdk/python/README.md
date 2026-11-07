# Grid402 Python SDK ⚡

[![PyPI version](https://img.shields.io/badge/pypi-v1.0.0-blue.svg)](https://pypi.org/project/grid402/)
[![Base Mainnet](https://img.shields.io/badge/Network-Base%20(8453)-0052FF.svg)](https://base.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Grid402 Status](https://www.grid402.com.br/badge/system.svg)](https://www.grid402.com.br)

Official zero-dependency Python client for the **Grid402 Telemetry Oracle** — inspecting real-time health, latency, and availability of critical Brazilian digital infrastructure (SEFAZ autorizadores fiscais, PIX/BACEN, payment gateways, and cloud backbones) with built-in circuit-breaker patterns and x402 on-chain micro-settlement.

---

## 🚀 Key Features

* **Zero External Dependencies:** Built entirely with Python's standard library (`urllib`).
* **Circuit Breaker Pattern (`guard`):** Automatically halts bulk transaction/NF-e execution when upstream infrastructure drops or degrades.
* **x402 Micropayment Protocol:** Seamlessly handles HTTP 402 handshakes on the **Base Mainnet** ($0.0005 USDC per detailed telemetry query).
* **AI Agent Ready:** Native integration with LangChain, CrewAI, AutoGen, and LLM tool-calling.

---

## 📦 Installation

```bash
pip install grid402
```

---

## ⚡ Quick Start

### 1. Free Public Health Check
```python
from grid402 import Grid402Client

client = Grid402Client()

# Check overall Brazilian infrastructure status
summary = client.get_summary()
print("Sistema:", summary["system_status"])  # UP / DEGRADED / DOWN

# Fast boolean checks before batch jobs
if client.is_healthy("SEFAZ"):
    print("SEFAZ autorizadores operando normalmente!")
```

### 2. Circuit Breaker (`guard`)
Prevent cascading failures when services (e.g., SEFAZ SP or PIX) are unstable:
```python
def emitir_lote_notas():
    # Código que emite 500 notas fiscais eletrônicas
    return "Lote processado com sucesso!"

def salvar_em_contingencia():
    # Fallback automático se a SEFAZ estiver instável
    return "Notas salvas para emissão em contingência."

# Só executa emitir_lote_notas se a SEFAZ estiver saudável
resultado = client.guard("SEFAZ", emitir_lote_notas, fallback=salvar_em_contingencia)
print(resultado)
```

### 3. Detailed Telemetry with x402 Micropayment
```python
from grid402 import Grid402Client, PaymentRequiredError

client = Grid402Client()

try:
    # Query millisecond-level telemetry item by item
    data = client.get_detailed_telemetry("0x<your_base_tx_hash>")
    print("Métricas detalhadas:", data["targets"])
except PaymentRequiredError as err:
    # Handles HTTP 402 challenge
    print(f"Envie {err.price_usdc} USDC para {err.recipient_wallet} na Base (Chain ID 8453).")
```

---

## 🤖 AI Agent Tool Integration (LangChain / CrewAI)

```python
from grid402 import Grid402Client

client = Grid402Client()

def check_brazil_infra_status(service_name: str) -> str:
    """Useful tool for AI agents to check if SEFAZ, PIX, or Gateways are UP before executing financial actions."""
    is_up = client.is_healthy(service_name)
    return f"O serviço {service_name} está {'OPERANTE (UP)' if is_up else 'INSTÁVEL / FORA DO AR (DOWN)'}."
```

---

## 📄 License
MIT License. Developed for autonomous agents and resilient Brazilian financial engineering.

