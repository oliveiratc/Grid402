"""
Teste unitário do SDK Python Grid402 e do Conector de Ferramentas para IA
"""

import sys
import os

# Adiciona o diretório do SDK Python ao path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'sdk', 'python')))

from grid402 import Grid402Client, PaymentRequiredError, ReplayAttackError, Grid402Error
from grid402_tool import create_grid402_tool, create_grid402_tools, GRID402_FUNCTION_SCHEMAS

print("=== Testando SDK Python Grid402 ===")

# 1. Instanciação do Cliente
client = Grid402Client(base_url="http://127.0.0.1:8942", timeout=3.0, verify_ssl=False)
assert client.base_url == "http://127.0.0.1:8942", "Base URL deve ser normalizada sem barra final"
print("✓ Grid402Client inicializado com sucesso!")

# 2. Conector de Ferramentas para Agentes de IA
tools = create_grid402_tools(base_url="http://127.0.0.1:8942", verify_ssl=False)
assert callable(tools["get_summary"]), "get_summary deve ser invocável"
assert callable(tools["get_forex"]), "get_forex deve ser invocável"
assert callable(tools["get_crypto"]), "get_crypto deve ser invocável"
assert callable(tools["get_dns"]), "get_dns deve ser invocável"
assert callable(tools["get_ssl"]), "get_ssl deve ser invocável"
assert callable(tools["get_asn"]), "get_asn deve ser invocável"
assert callable(tools["get_cep"]), "get_cep deve ser invocável"
print("✓ Conector com 7 ferramentas individuais criado com sucesso!")

# 3. Ferramenta Unificada (Dispatcher)
unified_tool = create_grid402_tool(base_url="http://127.0.0.1:8942", verify_ssl=False)
assert callable(unified_tool), "Dispatcher unificado deve ser invocável"
unknown_res = unified_tool(service="invalid_service")
assert "desconhecido" in unknown_res, "Deve tratar serviço desconhecido com erro informativo"
print("✓ Dispatcher unificado para LangChain/CrewAI aprovado!")

# 4. Schemas de Function Calling para Gemini/OpenAI
assert len(GRID402_FUNCTION_SCHEMAS) >= 1, "Deve possuir ao menos 1 schema de função"
schema = GRID402_FUNCTION_SCHEMAS[0]
assert schema["type"] == "function", "Tipo deve ser function"
assert schema["function"]["name"] == "grid402_query_hub", "Nome da função deve ser grid402_query_hub"
enums = schema["function"]["parameters"]["properties"]["service"]["enum"]
assert "forex" in enums and "dns" in enums and "cep" in enums, "Enums de serviços devem incluir utilitários"
print("✓ Schemas JSON de Function Calling validados com sucesso!")

print("=== SDK Python Grid402 100% Homologado! ===")

