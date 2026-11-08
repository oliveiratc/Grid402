"""
Grid402 AI Agent Tool Connector
Provides pre-built tool functions and schemas for LangChain, CrewAI, AutoGen, and LLM Function Calling.
Access public open data and network utilities via x402 micropayments on Base.
"""

from typing import Any, Dict, List, Optional
import json
from grid402 import Grid402Client, PaymentRequiredError


def create_grid402_tools(base_url: Optional[str] = None, verify_ssl: Optional[bool] = None) -> Dict[str, Any]:
    """
    Cria um conjunto de funções invocáveis por agentes de IA para utilitários de rede e dados públicos.
    Compatível com CrewAI, LangChain e Function Calling do Gemini/OpenAI/Claude.
    """
    client = Grid402Client(base_url=base_url, verify_ssl=verify_ssl) if base_url else Grid402Client(verify_ssl=verify_ssl)

    def get_summary() -> str:
        """Obtém o resumo gratuito de saúde dos micro-serviços do Grid402."""
        try:
            return json.dumps(client.get_summary(), ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    def get_forex(payment_tx_hash: Optional[str] = None) -> str:
        """Consulta cotações fiduciárias em tempo real (USD, EUR, GBP para BRL)."""
        try:
            return json.dumps(client.get_forex(payment_tx_hash=payment_tx_hash), ensure_ascii=False)
        except PaymentRequiredError as p:
            return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    def get_crypto(payment_tx_hash: Optional[str] = None) -> str:
        """Consulta cotações spot de criptoativos (BTC, ETH, SOL, USDC, BASE)."""
        try:
            return json.dumps(client.get_crypto(payment_tx_hash=payment_tx_hash), ensure_ascii=False)
        except PaymentRequiredError as p:
            return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    def get_dns(domain: str, payment_tx_hash: Optional[str] = None) -> str:
        """Resolve registros DNS (A, AAAA, MX, TXT, NS, CNAME) para um domínio."""
        try:
            return json.dumps(client.get_dns(domain, payment_tx_hash=payment_tx_hash), ensure_ascii=False)
        except PaymentRequiredError as p:
            return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    def get_ssl(host: str, payment_tx_hash: Optional[str] = None) -> str:
        """Inspeciona a validade e emissor de certificado TLS/SSL na porta 443 de um host."""
        try:
            return json.dumps(client.get_ssl(host, payment_tx_hash=payment_tx_hash), ensure_ascii=False)
        except PaymentRequiredError as p:
            return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    def get_asn(ip: Optional[str] = None, payment_tx_hash: Optional[str] = None) -> str:
        """Resolve dados de BGP, ASN e organização responsável para um IP."""
        try:
            return json.dumps(client.get_asn(ip=ip, payment_tx_hash=payment_tx_hash), ensure_ascii=False)
        except PaymentRequiredError as p:
            return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    def get_cep(code: str, payment_tx_hash: Optional[str] = None) -> str:
        """Resolve endereço público e código IBGE a partir de um CEP brasileiro."""
        try:
            return json.dumps(client.get_cep(code, payment_tx_hash=payment_tx_hash), ensure_ascii=False)
        except PaymentRequiredError as p:
            return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
        except Exception as e:
            return json.dumps({"error": str(e)}, ensure_ascii=False)

    return {
        "get_summary": get_summary,
        "get_forex": get_forex,
        "get_crypto": get_crypto,
        "get_dns": get_dns,
        "get_ssl": get_ssl,
        "get_asn": get_asn,
        "get_cep": get_cep,
    }


def create_grid402_tool(base_url: Optional[str] = None, verify_ssl: Optional[bool] = None):
    """
    Função utilitária unificada (dispatcher) para orquestradores como CrewAI e LangChain.
    """
    tools = create_grid402_tools(base_url=base_url, verify_ssl=verify_ssl)

    def grid402_query_hub(service: str, target: Optional[str] = None, payment_tx_hash: Optional[str] = None) -> str:
        """
        Consulta utilitários de rede ou dados públicos no Grid402.

        Args:
            service: Nome do serviço ('summary', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'cep').
            target: Parâmetro alvo (ex: domínio para DNS/SSL, IP para ASN, CEP para CEP).
            payment_tx_hash: Hash da transação on-chain (Base Mainnet) com US$ 0.0005 USDC caso requerido.
        """
        srv = (service or "").lower().strip()
        if srv == "summary":
            return tools["get_summary"]()
        elif srv == "forex":
            return tools["get_forex"](payment_tx_hash)
        elif srv == "crypto":
            return tools["get_crypto"](payment_tx_hash)
        elif srv == "dns":
            if not target:
                return json.dumps({"error": "Parâmetro 'target' (domínio) é obrigatório para DNS"})
            return tools["get_dns"](target, payment_tx_hash)
        elif srv == "ssl":
            if not target:
                return json.dumps({"error": "Parâmetro 'target' (host) é obrigatório para SSL"})
            return tools["get_ssl"](target, payment_tx_hash)
        elif srv == "asn":
            return tools["get_asn"](target, payment_tx_hash)
        elif srv == "cep":
            if not target:
                return json.dumps({"error": "Parâmetro 'target' (CEP) é obrigatório para CEP"})
            return tools["get_cep"](target, payment_tx_hash)
        else:
            return json.dumps({"error": f"Serviço desconhecido '{service}'. Opções: summary, forex, crypto, dns, ssl, asn, cep"})

    return grid402_query_hub


# Schemas JSON padrão para Gemini / OpenAI / Claude Function Calling
GRID402_FUNCTION_SCHEMAS: List[Dict[str, Any]] = [
    {
        "type": "function",
        "function": {
            "name": "grid402_query_hub",
            "description": "Consulta utilitários de rede de alta performance e dados públicos do Brasil e globais (Câmbio USD/EUR/BRL, Criptoativos BTC/ETH/SOL, Resolução DNS, Inspeção TLS/SSL, ASN/IP BGP e Consulta CEP/IBGE) via protocolo x402.",
            "parameters": {
                "type": "object",
                "properties": {
                    "service": {
                        "type": "string",
                        "description": "Nome do serviço a ser consultado.",
                        "enum": ["summary", "forex", "crypto", "dns", "ssl", "asn", "cep"]
                    },
                    "target": {
                        "type": "string",
                        "description": "Alvo da consulta técnica (domínio para DNS/SSL, IP para ASN, código de 8 dígitos para CEP). Opcional para summary, forex e crypto."
                    },
                    "payment_tx_hash": {
                        "type": "string",
                        "description": "Hash de transação hexadecimal de 32 bytes (66 caracteres) no Base Mainnet com 0.0005 USDC comprovando pagamento x402."
                    }
                },
                "required": ["service"]
            }
        }
    }
]
