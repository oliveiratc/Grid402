"""
Grid402 Action Provider para Coinbase AgentKit (Python)

Permite que agentes autonomos com carteiras CDP (Coinbase Developer Platform)
na rede Base consultem dados cadastrais e utilitarios de rede com auto-liquidacao x402.
"""

from typing import Any, Dict, Optional
import json
import urllib.parse
import urllib.request
import ssl

try:
    from pydantic import BaseModel, Field

    class CnpjInput(BaseModel):
        cnpj: str = Field(description="Numero do CNPJ (14 digitos com ou sem pontuacao)")

    class CepInput(BaseModel):
        code: str = Field(description="Numero do CEP brasileiro (8 digitos)")

    class DnsInput(BaseModel):
        domain: str = Field(description="Nome de dominio para resolucao DNS")

    class SslInput(BaseModel):
        host: str = Field(description="Nome de host para inspecao de certificado TLS/SSL")

    class AsnInput(BaseModel):
        ip: str = Field(description="Endereco IP para consulta de BGP e numero ASN")

except ImportError:
    class BaseModel:  # type: ignore
        pass

    class CnpjInput(BaseModel):  # type: ignore
        pass

    class CepInput(BaseModel):  # type: ignore
        pass

    class DnsInput(BaseModel):  # type: ignore
        pass

    class SslInput(BaseModel):  # type: ignore
        pass

    class AsnInput(BaseModel):  # type: ignore
        pass


GRID402_ORACLE_WALLET = "0xa31c31069c43ac1c0075a93c325b0dcd7744356c"
BASE_USDC_CONTRACT = "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"


class Grid402ActionProvider:
    """Provedor de acoes do Grid402 compativel com Coinbase AgentKit e LangChain."""

    def __init__(self, base_url: str = "https://grid402.com.br", sandbox: bool = False):
        self.base_url = base_url.rstrip("/")
        self.sandbox = sandbox

    def _get_ssl_context(self) -> ssl.SSLContext:
        ctx = ssl.create_default_context()
        if "hostingersite.com" in self.base_url:
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        return ctx

    def _execute_with_settlement(
        self, path: str, params: Optional[Dict[str, str]] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
        params = params or {}
        query_string = urllib.parse.urlencode({k: v for k, v in params.items() if v})
        url = f"{self.base_url}{path}"
        if query_string:
            url += f"?{query_string}"

        headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-CoinbaseAgentKit-Python/1.0",
        }

        if self.sandbox:
            headers["X-Payment"] = "demo"

        req = urllib.request.Request(url, headers=headers)
        ctx = self._get_ssl_context()

        # 1. Primeira requisicao
        try:
            with urllib.request.urlopen(req, context=ctx, timeout=8) as response:
                return json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            # 2. Se retornou HTTP 402 Payment Required
            if e.code == 402:
                challenge = json.loads(e.read().decode("utf-8"))

                # Se temos wallet_provider e nao estamos no sandbox, auto-liquida
                if wallet_provider is not None and not self.sandbox:
                    price_usdc = challenge.get("price_usdc", "0.0005")
                    recipient = challenge.get("recipient_wallet", GRID402_ORACLE_WALLET)

                    # Executa transferencia de USDC na Base usando a carteira CDP
                    transfer_result = wallet_provider.transfer(
                        to=recipient,
                        amount=price_usdc,
                        asset_id="usdc",
                    )
                    tx_hash = getattr(transfer_result, "transaction_hash", str(transfer_result))

                    # Reenvia com cabecalho X-Payment
                    headers["X-Payment"] = tx_hash
                    retry_req = urllib.request.Request(url, headers=headers)
                    with urllib.request.urlopen(retry_req, context=ctx, timeout=8) as retry_res:
                        data = json.loads(retry_res.read().decode("utf-8"))
                        data["_x402_settlement"] = {
                            "settled": True,
                            "tx_hash": tx_hash,
                            "price_usdc": price_usdc,
                            "network": "Base",
                        }
                        return data

                return challenge
            raise

    def get_summary(self) -> Dict[str, Any]:
        """Resumo operacional consolidado do hub (gratuito)."""
        return self._execute_with_settlement("/status")

    def query_cnpj(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta cadastral oficial de Pessoa Juridica na Receita Federal."""
        return self._execute_with_settlement("/api/v1/cnpj", {"cnpj": cnpj}, wallet_provider)

    def query_cep(self, code: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Resolucao de endereco postal e codigo IBGE a partir do CEP."""
        return self._execute_with_settlement("/api/v1/cep", {"code": code}, wallet_provider)

    def query_forex(self, wallet_provider: Any = None) -> Dict[str, Any]:
        """Cotacoes cambiais oficiais de USD, EUR e GBP para BRL."""
        return self._execute_with_settlement("/api/v1/forex", {}, wallet_provider)

    def query_crypto(self, wallet_provider: Any = None) -> Dict[str, Any]:
        """Precos spot de mercado de BTC, ETH, SOL e USDC."""
        return self._execute_with_settlement("/api/v1/crypto", {}, wallet_provider)

    def query_dns(self, domain: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Resolucao autoritativa de registros DNS."""
        return self._execute_with_settlement("/api/v1/dns", {"domain": domain}, wallet_provider)

    def query_ssl(self, host: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Inspecao e auditoria de certificados TLS/SSL de hosts remotos."""
        return self._execute_with_settlement("/api/v1/ssl", {"host": host}, wallet_provider)

    def query_asn(self, ip: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Identificacao de ASN e organizacao titular de roteamento BGP."""
        return self._execute_with_settlement("/api/v1/asn", {"ip": ip}, wallet_provider)

    # Aliases no padrao snake_case das ferramentas MCP
    get_cnpj = query_cnpj
    get_cep = query_cep
    get_forex = query_forex
    get_crypto = query_crypto
    get_dns = query_dns
    get_ssl = query_ssl
    get_asn = query_asn
