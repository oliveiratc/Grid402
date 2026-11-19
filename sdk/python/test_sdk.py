"""
Teste de validação do SDK Python do Grid402 contra o endpoint em produção na Hostinger.
"""
import time
from grid402 import Grid402Client, Grid402Error, PaymentRequiredError


def call_with_retry(fn, attempts: int = 3, delay: float = 2.0):
    """Repete chamadas de leitura ao vivo em caso de timeout transitorio durante deploy."""
    for attempt in range(1, attempts + 1):
        try:
            return fn()
        except PaymentRequiredError:
            raise
        except Grid402Error:
            if attempt >= attempts:
                raise
            time.sleep(delay)


client = Grid402Client(timeout=15.0, verify_ssl=False)

print("[TEST SDK PYTHON] 1. Testando get_summary()...")
summary = call_with_retry(client.get_summary)
assert summary["protocol"] == "x402", "Protocolo deve ser x402"
assert "services" in summary, "Catálogo de serviços deve estar presente"
print(f"  ✓ Resumo obtido com sucesso! Status do hub: {summary.get('status')}")

print("[TEST SDK PYTHON] 2. Testando is_healthy('forex')...")
is_forex_ok = call_with_retry(lambda: client.is_healthy("forex"))
print(f"  ✓ Saúde do Câmbio (forex): {'UP' if is_forex_ok else 'DOWN'}")

print("[TEST SDK PYTHON] 3. Testando get_challenge()...")
challenge = call_with_retry(client.get_challenge)
assert challenge["error"] == "PaymentRequired", "Deve retornar desafio de pagamento"
assert challenge["price_usdc"] == "0.0050", "Preço deve ser 0.0050 USDC"
assert challenge["recipient_wallet"] == "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90", "Carteira deve ser a oficial do Grid402"
print(f"  ✓ Desafio x402 validado! Carteira destino: {challenge['recipient_wallet']}")

print("[TEST SDK PYTHON] 4. Testando guard() Circuit Breaker...")
executed = False
def batch_work():
    global executed
    executed = True
    return "Consulta protegida processada com sucesso!"

result = call_with_retry(lambda: client.guard("forex", batch_work))
assert executed is True, "batch_work deve ter sido executado"
print(f"  ✓ Circuit Breaker guard() executou a rotina protegida: '{result}'")

print("[TEST SDK PYTHON] 5. Testando metodos de Compliance, Sancoes e Simples Nacional...")
from unittest.mock import MagicMock, patch
import io
import json

mock_response = io.BytesIO(json.dumps({"status": "success", "cnpj": "00000000000191", "sandbox": True}).encode("utf-8"))
mock_cm = MagicMock()
mock_cm.__enter__.return_value = mock_response
mock_cm.__exit__.return_value = None

with patch("urllib.request.urlopen", return_value=mock_cm) as mock_open:
    sandbox_client = Grid402Client(sandbox=True, verify_ssl=False)

    sanctions = sandbox_client.get_sanctions("00.000.000/0001-91")
    assert sanctions["status"] == "success"
    req = mock_open.call_args[0][0]
    assert "/api/v1/sanctions?cnpj=00000000000191" in req.full_url
    assert req.headers.get("X-payment") == "demo" or req.headers.get("X-Payment") == "demo"

    mock_response.seek(0)
    simples = sandbox_client.get_simples("00.000.000/0001-91")
    assert simples["status"] == "success"
    req = mock_open.call_args[0][0]
    assert "/api/v1/simples?cnpj=00000000000191" in req.full_url

    mock_response.seek(0)
    compliance = sandbox_client.get_compliance("00.000.000/0001-91")
    assert compliance["status"] == "success"
    req = mock_open.call_args[0][0]
    assert "/api/v1/compliance?cnpj=00000000000191" in req.full_url

    mock_response.seek(0)
    dossier = sandbox_client.get_dossier_360("00.000.000/0001-91")
    assert dossier["status"] == "success"
    req = mock_open.call_args[0][0]
    assert "/api/v1/dossier?cnpj=00000000000191" in req.full_url

print("  ✓ Metodos get_sanctions, get_simples, get_compliance e get_dossier_360 validados com sucesso!")

print("\n✓ Todos os testes do SDK Python passaram com 100% de sucesso!")
