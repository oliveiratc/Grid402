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

    class IndicatorsInput(BaseModel):
        pass

    class HolidaysInput(BaseModel):
        year: Optional[int] = Field(default=None, description="Ano de referencia para feriados (ex: 2026)")

    class WorkdaysInput(BaseModel):
        start_date: str = Field(description="Data inicial no formato AAAA-MM-DD")
        end_date: Optional[str] = Field(default=None, description="Data final no formato AAAA-MM-DD")
        days: Optional[int] = Field(default=None, description="Quantidade de dias uteis para adicao")

    class VerifyInput(BaseModel):
        payload: Any = Field(description="Payload JSON contendo a chave _oracle_proof a ser verificada")

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

    class IndicatorsInput(BaseModel):  # type: ignore
        pass

    class HolidaysInput(BaseModel):  # type: ignore
        pass

    class WorkdaysInput(BaseModel):  # type: ignore
        pass

    class VerifyInput(BaseModel):  # type: ignore
        pass


GRID402_ORACLE_WALLET = "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90"
BASE_USDC_CONTRACT = "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"


class Grid402ActionProvider:
    """Provedor de acoes do Grid402 compativel com Coinbase AgentKit e LangChain."""

    def __init__(self, base_url: str = "https://grid402.online", sandbox: bool = False):
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

    def query_indicators(self, wallet_provider: Any = None) -> Dict[str, Any]:
        """Indicadores macroeconomicos oficiais (Selic, CDI e IPCA)."""
        return self._execute_with_settlement("/api/v1/indicators", {}, wallet_provider)

    def query_holidays(self, year: Optional[int] = None, wallet_provider: Any = None) -> Dict[str, Any]:
        """Calendario oficial de feriados nacionais e bancarios ANBIMA."""
        params: Dict[str, Any] = {}
        if year is not None:
            params["year"] = str(year)
        return self._execute_with_settlement("/api/v1/holidays", params, wallet_provider)

    def query_workdays(
        self,
        start_date: str,
        end_date: Optional[str] = None,
        days: Optional[int] = None,
        wallet_provider: Any = None
    ) -> Dict[str, Any]:
        """Calculadora de dias uteis bancarios ANBIMA entre datas."""
        params: Dict[str, Any] = {"start_date": start_date}
        if end_date:
            params["end_date"] = end_date
        if days is not None:
            params["days"] = str(days)
        return self._execute_with_settlement("/api/v1/workdays", params, wallet_provider)

    def query_sanctions(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta de sancoes administrativas CEIS e CNEP (CGU)."""
        return self._execute_with_settlement("/api/v1/sanctions", {"cnpj": cnpj}, wallet_provider)

    def query_simples(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta de opcao pelo Simples Nacional e enquadramento MEI."""
        return self._execute_with_settlement("/api/v1/simples", {"cnpj": cnpj}, wallet_provider)

    def query_compliance(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Compliance Score 360 consolidado (Receita Federal, CGU e historico)."""
        return self._execute_with_settlement("/api/v1/compliance", {"cnpj": cnpj}, wallet_provider)

    def verify_proof(self, payload: Any) -> Dict[str, Any]:
        """Validacao sem estado de prova criptografica de oraculo (_oracle_proof)."""
        url = f"{self.base_url}/api/v1/verify"
        post_data = payload if isinstance(payload, str) else json.dumps(payload)
        req = urllib.request.Request(
            url,
            data=post_data.encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json",
                "User-Agent": "Grid402-CoinbaseAgentKit-Python/1.6",
            },
            method="POST",
        )
        ctx = self._get_ssl_context()
        with urllib.request.urlopen(req, context=ctx, timeout=10) as resp:
            return json.loads(resp.read().decode("utf-8"))

    # Aliases no padrao snake_case das ferramentas MCP
    get_cnpj = query_cnpj
    get_cep = query_cep
    get_forex = query_forex
    get_crypto = query_crypto
    get_dns = query_dns
    get_ssl = query_ssl
    get_asn = query_asn
    get_indicators = query_indicators
    get_holidays = query_holidays
    get_workdays = query_workdays
    get_sanctions = query_sanctions
    get_simples = query_simples
    get_compliance = query_compliance
    verify_proof_alias = verify_proof
