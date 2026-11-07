"""
Grid402 AI Agent Tool Connector
Provides pre-built tool functions and schemas for LangChain, CrewAI, AutoGen, and LLM Function Calling.
"""

from typing import Any, Dict, Optional
import json
from grid402 import Grid402Client


def create_grid402_tool(base_url: Optional[str] = None, verify_ssl: bool = True):
    """
    Cria uma função invocável por agentes de IA para verificar a saúde da infraestrutura brasileira.
    Compatível com CrewAI, LangChain e Function Calling do Gemini/OpenAI/Claude.
    """
    client = Grid402Client(base_url=base_url, verify_ssl=verify_ssl) if base_url else Grid402Client(verify_ssl=verify_ssl)

    def grid402_check_infrastructure(service_or_category: str) -> str:
        """
        Consulta a disponibilidade e latência de serviços críticos brasileiros (SEFAZ autorizadores, PIX, BACEN, Gateways).
        
        Args:
            service_or_category: Nome do serviço ou categoria (ex: 'SEFAZ', 'sefaz_sp', 'Gateways', 'mercadopago', 'pix', 'system').
        """
        try:
            summary = client.get_summary()
            is_up = client.is_healthy(service_or_category)
            
            categories = summary.get("categories", {})
            cat_info = categories.get(service_or_category) or {}
            avg_lat = cat_info.get("avg_latency_ms", "N/A")

            status_str = "OPERANTE (UP)" if is_up else "INSTÁVEL OU FORA DO AR (DOWN)"
            return (
                f"Status de '{service_or_category}': {status_str}. "
                f"Latência média da categoria: {avg_lat} ms. "
                f"Status geral da infraestrutura brasileira: {summary.get('system_status')}."
            )
        except Exception as e:
            return f"Erro ao consultar o oráculo Grid402: {str(e)}"

    return grid402_check_infrastructure


# Schema JSON padrão para OpenAI / Gemini Function Calling
GRID402_FUNCTION_SCHEMA = {
    "type": "function",
    "function": {
        "name": "check_brazilian_infrastructure_status",
        "description": "Verifica em tempo real se serviços fiscais (SEFAZ SP/RS/MG), bancários (PIX/BACEN) ou gateways de pagamento estão online antes de submeter operações financeiras ou emissões fiscais.",
        "parameters": {
            "type": "object",
            "properties": {
                "service_or_category": {
                    "type": "string",
                    "description": "Nome da categoria ('SEFAZ', 'Gateways', 'Bancos & PIX', 'Telecom & Infra') ou alvo específico ('sefaz_sp', 'mercadopago', 'bacen_dados').",
                    "enum": [
                        "SEFAZ", "Gateways", "Bancos & PIX", "Telecom & Infra",
                        "sefaz_sp", "sefaz_rs", "sefaz_mg", "sefaz_pr", "sefaz_svan",
                        "mercadopago", "asaas", "cielo", "stone", "pagseguro",
                        "bacen_dados", "banco_brasil", "itau_pix", "bradesco_pix",
                        "system"
                    ]
                }
            },
            "required": ["service_or_category"]
        }
    }
}
