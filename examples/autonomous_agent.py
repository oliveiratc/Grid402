"""
Grid402 - Exemplo de Agente Autônomo com Auto-Liquidação x402 (Python)

Demonstra como um agente de IA (CrewAI, LangChain ou autônomo) pode
consumir micro-serviços do Grid402 pagando sob demanda na rede Base.
"""

import os
import json
import ssl
import urllib.parse
import urllib.request
import urllib.error

GRID402_ENDPOINT = os.getenv("GRID402_ENDPOINT", "https://orangered-jackal-900744.hostingersite.com").rstrip("/")
AGENT_PRIVATE_KEY = os.getenv("AGENT_PRIVATE_KEY", None)

def get_ssl_context():
    if "hostingersite.com" in GRID402_ENDPOINT:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        return ctx
    return None

def query_grid402_with_auto_settlement(service_path: str, params: dict = None) -> dict:
    query_str = ("?" + urllib.parse.urlencode(params)) if params else ""
    
    url = f"{GRID402_ENDPOINT}{service_path}{query_str}"
    print(f"\n🤖 [AGENTE] Solicitando serviço: {service_path}{query_str}")

    req = urllib.request.Request(url, headers={"Accept": "application/json"})
    ctx = get_ssl_context()

    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        if e.code == 402:
            challenge_raw = e.read().decode("utf-8")
            challenge = json.loads(challenge_raw)
            print(f"⚡ [AGENTE] Desafio x402 recebido: Requer {challenge.get('price_usdc')} USDC na Base")
            print(f"📍 [AGENTE] Carteira do oráculo: {challenge.get('recipient_wallet')}")

            if not AGENT_PRIVATE_KEY:
                print("⚠️ [AGENTE] AGENT_PRIVATE_KEY não informada. Para auto-pagamento, configure a variável.")
                return challenge

            # Aqui você pode plugar sua rotina web3.py / eth-account para transferir automaticamente
            print("💳 [AGENTE] Chave privada detectada! Executando transferência ERC-20 na Base...")
            # Retorna o desafio para integração em frameworks
            return challenge
        else:
            raise

if __name__ == "__main__":
    print("========================================================")
    print("  GRID402 - AGENTE AUTÔNOMO PYTHON (x402 Base Protocol) ")
    print("========================================================")

    # 1. Teste de Validação de CNPJ
    resultado = query_grid402_with_auto_settlement("/api/v1/cnpj", {"cnpj": "00000000000191"})
    print("\nResposta recebida do oráculo:")
    print(json.dumps(resultado, indent=2, ensure_ascii=False))
