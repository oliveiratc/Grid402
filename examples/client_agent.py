"""
Grid402 - Exemplo de Integração de Agente Autônomo de IA (Python)

Este script demonstra como um agente autônomo M2M consulta o Grid402:
1. Faz uma checagem preliminar gratuita (Circuit Breaker).
2. Se necessário, solicita a telemetria detalhada via protocolo HTTP x402.
3. Liquida os 0.0005 USDC na rede Base e consome o payload técnico completo.
"""

import os
import json
import requests

GRID402_BASE_URL = os.getenv("GRID402_URL", "https://www.grid402.com.br")

def check_system_health():
    """Consulta o resumo gratuito da telemetria."""
    print("[AGENTE] 1. Consultando disponibilidade preliminar (Camada Gratuita)...")
    url = f"{GRID402_BASE_URL}/status?format=json"
    response = requests.get(url, headers={"Accept": "application/json"}, timeout=3)
    
    if response.status_code == 200:
        data = response.json()
        print(f"  -> Status Geral: {data.get('system_status')}")
        print(f"  -> Alvos Saudáveis: {data.get('healthy_targets')}/{data.get('total_targets')}")
        for cat, stats in data.get("categories", {}).items():
            print(f"     • {cat}: {stats['status']} (Latência Média: {stats['avg_latency_ms']} ms)")
        return data
    else:
        print(f"  -> Falha na consulta preliminar: HTTP {response.status_code}")
        return None

def fetch_detailed_telemetry(wallet_tx_hash=None):
    """Solicita telemetria detalhada via fluxo x402."""
    print("\n[AGENTE] 2. Solicitando telemetria detalhada de alta precisão...")
    url = f"{GRID402_BASE_URL}/telemetry"
    headers = {"Accept": "application/json"}
    
    if wallet_tx_hash:
        headers["X-Payment"] = wallet_tx_hash

    response = requests.get(url, headers=headers, timeout=5)

    # Caso 1: Desafio HTTP 402 Payment Required
    if response.status_code == 402:
        challenge = response.json()
        print("  -> Recebido Desafio HTTP 402 Payment Required!")
        print(f"     • Rede: {challenge.get('network')} (Chain ID: {challenge.get('chain_id')})")
        print(f"     • Token: {challenge.get('token')} ({challenge.get('token_contract')})")
        print(f"     • Preço: US$ {challenge.get('price_usdc')} USDC")
        print(f"     • Carteira Oráculo: {challenge.get('recipient_wallet')}")
        print(f"     • Ação do Agente: Executar transfer(recipient_wallet, 500) e reenviar tx_hash.")
        return challenge

    # Caso 2: Pagamento Aceito (HTTP 200)
    elif response.status_code == 200:
        payload = response.json()
        print("  ✓ Pagamento validado com sucesso! Recebendo telemetria técnica:")
        for target in payload.get("targets", [])[:5]:
            print(f"     • [{target['category']}] {target['name']}: {target['status']} | Latência: {target['latency_ms']} ms | HTTP: {target['http_code']}")
        return payload

    # Caso 3: Erro ou Replay Attack
    else:
        print(f"  -> Erro HTTP {response.status_code}: {response.text}")
        return None

if __name__ == "__main__":
    # Passo 1: Checagem rápida
    summary = check_system_health()
    
    # Passo 2: Demonstração do desafio x402
    challenge = fetch_detailed_telemetry()
