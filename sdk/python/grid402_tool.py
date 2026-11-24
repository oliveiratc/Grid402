"""
Grid402 AI Agent Tool Connector
Provides pre-built tool functions and schemas for LangChain, CrewAI, AutoGen, and LLM Function Calling.
Covers all 25 public open data and network utility services via Session Pass, RapidAPI Hub or x402 USDC.
"""

from typing import Any, Callable, Dict, List, Optional
import json
try:
    from grid402 import Grid402Client, PaymentRequiredError
except ImportError:
    from grid402 import Grid402Client, Grid402PaymentRequiredError as PaymentRequiredError


def _wrap_call(fn: Callable[[], Any]) -> str:
    try:
        return json.dumps(fn(), ensure_ascii=False)
    except PaymentRequiredError as p:
        return json.dumps({"payment_required": True, "challenge": p.challenge}, ensure_ascii=False)
    except Exception as e:
        return json.dumps({"error": str(e)}, ensure_ascii=False)


def create_grid402_tools(
    base_url: Optional[str] = None,
    verify_ssl: Optional[bool] = None,
    session_pass: Optional[str] = None,
    rapidapi_key: Optional[str] = None,
    rapidapi_host: Optional[str] = None,
    sandbox: bool = False,
) -> Dict[str, Any]:
    """
    Cria o conjunto completo de funcoes invocaveis por agentes de IA para todos os 25 servicos do Grid402.
    Compativel com CrewAI, LangChain e Function Calling do Gemini/OpenAI/Claude.
    """
    kwargs: Dict[str, Any] = {
        "verify_ssl": verify_ssl,
        "session_pass": session_pass,
        "rapidapi_key": rapidapi_key,
        "rapidapi_host": rapidapi_host,
        "sandbox": sandbox,
    }
    if base_url:
        kwargs["base_url"] = base_url
    client = Grid402Client(**kwargs)

    return {
        "get_summary": lambda: _wrap_call(client.get_summary),
        "get_forex": lambda payment_tx_hash=None: _wrap_call(lambda: client.get_forex(payment_tx_hash=payment_tx_hash)),
        "get_crypto": lambda payment_tx_hash=None: _wrap_call(lambda: client.get_crypto(payment_tx_hash=payment_tx_hash)),
        "get_dns": lambda domain, payment_tx_hash=None: _wrap_call(lambda: client.get_dns(domain, payment_tx_hash=payment_tx_hash)),
        "get_ssl": lambda host, payment_tx_hash=None: _wrap_call(lambda: client.get_ssl(host, payment_tx_hash=payment_tx_hash)),
        "get_asn": lambda ip=None, payment_tx_hash=None: _wrap_call(lambda: client.get_asn(ip=ip, payment_tx_hash=payment_tx_hash)),
        "get_cep": lambda code, payment_tx_hash=None: _wrap_call(lambda: client.get_cep(code, payment_tx_hash=payment_tx_hash)),
        "get_cnpj": lambda cnpj, payment_tx_hash=None: _wrap_call(lambda: client.get_cnpj(cnpj, payment_tx_hash=payment_tx_hash)),
        "get_indicators": lambda payment_tx_hash=None: _wrap_call(lambda: client.get_indicators(payment_tx_hash=payment_tx_hash)),
        "get_holidays": lambda year=None, payment_tx_hash=None: _wrap_call(lambda: client.get_holidays(year=year, payment_tx_hash=payment_tx_hash)),
        "get_workdays": lambda start_date=None, end_date=None, days=None, date=None, payment_tx_hash=None: _wrap_call(
            lambda: client.get_workdays(start_date=start_date, end_date=end_date, days=days, date=date, payment_tx_hash=payment_tx_hash)
        ),
        "get_sanctions": lambda cnpj, payment_tx_hash=None: _wrap_call(lambda: client.get_sanctions(cnpj, payment_tx_hash=payment_tx_hash)),
        "get_simples": lambda cnpj, payment_tx_hash=None: _wrap_call(lambda: client.get_simples(cnpj, payment_tx_hash=payment_tx_hash)),
        "get_compliance": lambda cnpj, payment_tx_hash=None: _wrap_call(lambda: client.get_compliance(cnpj, payment_tx_hash=payment_tx_hash)),
        "get_dossier_360": lambda cnpj, payment_tx_hash=None: _wrap_call(lambda: client.get_dossier_360(cnpj, payment_tx_hash=payment_tx_hash)),
        "get_domain_audit": lambda domain, payment_tx_hash=None: _wrap_call(lambda: client.get_domain_audit(domain, payment_tx_hash=payment_tx_hash)),
        "get_kyb_audit": lambda cnpj, payment_tx_hash=None: _wrap_call(lambda: client.get_kyb_audit(cnpj, payment_tx_hash=payment_tx_hash)),
        "get_market_snapshot": lambda payment_tx_hash=None: _wrap_call(lambda: client.get_market_snapshot(payment_tx_hash=payment_tx_hash)),
        "get_lei": lambda lei, payment_tx_hash=None: _wrap_call(lambda: client.get_lei(lei, payment_tx_hash=payment_tx_hash)),
        "get_ofac": lambda target, payment_tx_hash=None: _wrap_call(lambda: client.get_ofac(target, payment_tx_hash=payment_tx_hash)),
        "notarize": lambda content, payment_tx_hash=None: _wrap_call(lambda: client.notarize(content, payment_tx_hash=payment_tx_hash)),
        "get_global_markets": lambda market=None, base=None, date=None, payment_tx_hash=None: _wrap_call(
            lambda: client.get_global_markets(market=market, base=base, date=date, payment_tx_hash=payment_tx_hash)
        ),
        "extract": lambda url, max_chars=None, payment_tx_hash=None: _wrap_call(lambda: client.extract(url, max_chars=max_chars, payment_tx_hash=payment_tx_hash)),
        "email_verify": lambda email, payment_tx_hash=None: _wrap_call(lambda: client.email_verify(email, payment_tx_hash=payment_tx_hash)),
        "get_pass_balance": lambda token=None: _wrap_call(lambda: client.get_pass_balance(token=token)),
        "verify_proof": lambda payload: _wrap_call(lambda: client.verify_proof_remote(payload)),
    }


ALL_HUB_SERVICES = [
    "summary",
    "forex",
    "crypto",
    "dns",
    "ssl",
    "asn",
    "cep",
    "cnpj",
    "indicators",
    "holidays",
    "workdays",
    "sanctions",
    "simples",
    "compliance",
    "dossier",
    "domain-audit",
    "kyb-audit",
    "market-snapshot",
    "lei",
    "ofac",
    "notarize",
    "global-markets",
    "extract",
    "email-verify",
    "pass-balance",
    "verify",
]


def create_grid402_tool(
    base_url: Optional[str] = None,
    verify_ssl: Optional[bool] = None,
    session_pass: Optional[str] = None,
    rapidapi_key: Optional[str] = None,
    sandbox: bool = False,
):
    """Funcao utilitaria unificada (dispatcher) para orquestradores como CrewAI e LangChain."""
    tools = create_grid402_tools(
        base_url=base_url,
        verify_ssl=verify_ssl,
        session_pass=session_pass,
        rapidapi_key=rapidapi_key,
        sandbox=sandbox,
    )

    def grid402_query_hub(service: str, target: Optional[str] = None, payment_tx_hash: Optional[str] = None) -> str:
        srv = (service or "").lower().strip().replace("_", "-")
        if srv == "summary":
            return tools["get_summary"]()
        if srv == "forex":
            return tools["get_forex"](payment_tx_hash)
        if srv == "crypto":
            return tools["get_crypto"](payment_tx_hash)
        if srv == "indicators":
            return tools["get_indicators"](payment_tx_hash)
        if srv == "market-snapshot":
            return tools["get_market_snapshot"](payment_tx_hash)
        if srv == "holidays":
            year = int(target) if (target and str(target).isdigit()) else None
            return tools["get_holidays"](year, payment_tx_hash)
        if srv == "workdays":
            return tools["get_workdays"](start_date=target, payment_tx_hash=payment_tx_hash)
        if srv == "global-markets":
            return tools["get_global_markets"](market=target or "US", payment_tx_hash=payment_tx_hash)
        if srv == "asn":
            return tools["get_asn"](target, payment_tx_hash)
        if srv == "pass-balance":
            return tools["get_pass_balance"](target)
        if srv == "verify":
            if not target:
                return json.dumps({"error": "Parâmetro 'target' (payload JSON) é obrigatório para verify"})
            return tools["verify_proof"](target)

        required_target_map = {
            "dns": ("domínio", tools["get_dns"]),
            "ssl": ("host", tools["get_ssl"]),
            "cep": ("CEP", tools["get_cep"]),
            "cnpj": ("CNPJ", tools["get_cnpj"]),
            "sanctions": ("CNPJ", tools["get_sanctions"]),
            "simples": ("CNPJ", tools["get_simples"]),
            "compliance": ("CNPJ", tools["get_compliance"]),
            "dossier": ("CNPJ", tools["get_dossier_360"]),
            "dossier-360": ("CNPJ", tools["get_dossier_360"]),
            "domain-audit": ("domínio", tools["get_domain_audit"]),
            "kyb-audit": ("CNPJ", tools["get_kyb_audit"]),
            "lei": ("código LEI", tools["get_lei"]),
            "ofac": ("carteira ou entidade", tools["get_ofac"]),
            "notarize": ("conteúdo ou hash SHA-256", tools["notarize"]),
            "extract": ("URL pública HTTP/HTTPS", lambda t, p: tools["extract"](t, None, p)),
            "email-verify": ("e-mail corporativo", tools["email_verify"]),
        }
        if srv in required_target_map:
            label, fn = required_target_map[srv]
            if not target:
                return json.dumps({"error": f"Parâmetro 'target' ({label}) é obrigatório para {srv}"})
            return fn(target, payment_tx_hash)

        return json.dumps({"error": f"Serviço desconhecido '{service}'. Opções: {', '.join(ALL_HUB_SERVICES)}"})

    return grid402_query_hub


# Schemas JSON padrão para Gemini / OpenAI / Claude Function Calling (todos os 25 serviços)
GRID402_FUNCTION_SCHEMAS: List[Dict[str, Any]] = [
    {
        "type": "function",
        "function": {
            "name": "grid402_query_hub",
            "description": "Consulta os 25 micro-serviços públicos verificáveis do Grid402 (KYB 360 Dossier, CNPJ, Sanções CGU, Simples/MEI, Compliance, GLEIF LEI, OFAC, Web-to-Markdown extract, Email Verify, Forex, Crypto, ANBIMA Workdays/Holidays, Selic/CDI/IPCA, DNS, SSL, ASN, CEP, Notarize, Global Markets) via Session Pass, RapidAPI ou x402 USDC.",
            "parameters": {
                "type": "object",
                "properties": {
                    "service": {
                        "type": "string",
                        "description": "Nome do serviço a ser consultado.",
                        "enum": ALL_HUB_SERVICES,
                    },
                    "target": {
                        "type": "string",
                        "description": "Alvo da consulta (CNPJ para dossier/cnpj/sanctions/simples/compliance/kyb-audit, URL para extract, e-mail para email-verify, domínio para dns/ssl/domain-audit, IP para asn, CEP para cep, LEI para lei, carteira/nome para ofac, texto para notarize, ano para holidays, mercado US/EU/UK/BR para global-markets).",
                    },
                    "payment_tx_hash": {
                        "type": "string",
                        "description": "Opcional: token Session Pass (pass_g402_...), payload x402 v2 base64 ou txHash USDC Multi-Chain (Base/Polygon/Arbitrum/Solana).",
                    },
                },
                "required": ["service"],
            },
        },
    }
]
