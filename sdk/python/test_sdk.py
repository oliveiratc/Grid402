"""
Teste de validação do SDK Python do Grid402 contra o endpoint em produção na Hostinger.
"""
from grid402 import Grid402Client, PaymentRequiredError

client = Grid402Client(verify_ssl=False)

print("[TEST SDK PYTHON] 1. Testando get_summary()...")
summary = client.get_summary()
assert summary["protocol"] == "x402", "Protocolo deve ser x402"
assert "categories" in summary, "Categorias devem estar presentes"
print(f"  ✓ Resumo obtido com sucesso! Status do sistema: {summary['system_status']}")

print("[TEST SDK PYTHON] 2. Testando is_healthy('SEFAZ')...")
is_sefaz_ok = client.is_healthy("SEFAZ")
print(f"  ✓ Saúde da SEFAZ: {'UP' if is_sefaz_ok else 'DOWN'}")

print("[TEST SDK PYTHON] 3. Testando get_challenge()...")
challenge = client.get_challenge()
assert challenge["error"] == "PaymentRequired", "Deve retornar desafio de pagamento"
assert challenge["price_usdc"] == "0.0005", "Preço deve ser 0.0005 USDC"
assert challenge["recipient_wallet"] == "0xa31c31069c43ac1c0075a93c325b0dcd7744356c", "Carteira deve ser a do usuário"
print(f"  ✓ Desafio x402 validado! Carteira: {challenge['recipient_wallet']}")

print("[TEST SDK PYTHON] 4. Testando guard() Circuit Breaker...")
executed = False
def batch_work():
    global executed
    executed = True
    return "Lote processado com sucesso!"

result = client.guard("SEFAZ", batch_work)
assert executed is True, "batch_work deve ter sido executado"
print(f"  ✓ Circuit Breaker guard() executou a rotina protegida: '{result}'")

print("\n✓ Todos os testes do SDK Python passaram com 100% de sucesso!")
