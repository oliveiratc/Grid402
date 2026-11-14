"""
Grid402 Action Provider para Coinbase AgentKit, LangChain e CrewAI.

Permite que agentes autonomos com carteiras CDP (Coinbase Developer Platform)
na rede Base consultem dados cadastrais e utilitarios de rede com auto-liquidacao x402.
"""

from typing import Any, Dict, Optional
import json
import ssl
import urllib.error
import urllib.parse
import urllib.request

from grid402.client import DEFAULT_ENDPOINT
from grid402.models import (
    AsnInput,
    CepInput,
    CnpjInput,
    CryptoInput,
    DnsInput,
    HolidaysInput,
    SslInput,
    WorkdaysInput,
)


GRID402_ORACLE_WALLET = "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90"
BASE_USDC_CONTRACT = "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"


class Grid402ActionProvider:
    """
    Provedor de acoes do Grid402 compativel com Coinbase AgentKit, LangChain e CrewAI.
    
    Gerencia o ciclo de vida completo de micropagamentos x402 de forma autonoma,
    detectando desafios HTTP 402, efetuando a transferencia de USDC on-chain
    na Base e reenviando a requisicao com o comprovante criptografico.
    """

    def __init__(self, base_url: str = DEFAULT_ENDPOINT, sandbox: bool = False):
        """
        Inicializa o provedor de acoes Grid402.
        
        Args:
            base_url: URL base da API do Grid402.
            sandbox: Se True, opera em modo Sandbox gratuito sem consumir USDC real.
        """
        self.base_url = base_url.rstrip("/")
        self.sandbox = sandbox

    def _get_ssl_context(self) -> ssl.SSLContext:
        ctx = ssl.create_default_context()
        if "hostingersite.com" in self.base_url:
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        return ctx

    def _execute_with_settlement(
        self,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        """
        Executa a requisicao HTTP com auto-liquidacao de desafio x402.
        
        Se o endpoint retornar HTTP 402 e um wallet_provider estiver disponivel,
        executa a transferencia de micropagamento em USDC na Base e repete a chamada.
        """
        filtered_params = {k: v for k, v in (params or {}).items() if v is not None}
        query_string = urllib.parse.urlencode(filtered_params)

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

        # 1. Primeira tentativa de requisicao
        try:
            with urllib.request.urlopen(req, context=ctx, timeout=10) as response:
                return json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            raw_body = exc.read().decode("utf-8")
            try:
                challenge = json.loads(raw_body)
            except Exception:
                challenge = {"error": raw_body, "status_code": exc.code}

            # 2. Se retornou HTTP 402 Payment Required
            if exc.code == 402:
                # Se temos wallet_provider e nao estamos no sandbox, auto-liquida on-chain
                if wallet_provider is not None and not self.sandbox:
                    price_usdc = challenge.get("price_usdc", "0.0005")
                    recipient = challenge.get("recipient_wallet", GRID402_ORACLE_WALLET)

                    try:
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
                        with urllib.request.urlopen(retry_req, context=ctx, timeout=10) as retry_res:
                            data = json.loads(retry_res.read().decode("utf-8"))
                            data["_x402_settlement"] = {
                                "settled": True,
                                "tx_hash": tx_hash,
                                "price_usdc": price_usdc,
                                "network": "Base",
                                "token": "USDC",
                            }
                            return data
                    except Exception as pay_err:
                        challenge["payment_error"] = str(pay_err)
                        challenge["payment_required"] = True
                        return challenge

                challenge["payment_required"] = True
                return challenge
            raise

    def get_summary(self) -> Dict[str, Any]:
        """Resumo operacional consolidado e disponibilidade do hub Grid402 (gratuito)."""
        return self._execute_with_settlement("/status")

    def query_forex(self, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta cotacoes fiduciarias de USD, EUR e GBP para BRL em tempo real."""
        return self._execute_with_settlement("/api/v1/forex", {}, wallet_provider)

    def query_crypto(
        self, symbol: Optional[str] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
        """Consulta precos de mercado spot de criptoativos em USD e BRL."""
        params = {"symbol": symbol} if symbol else {}
        return self._execute_with_settlement("/api/v1/crypto", params, wallet_provider)

    def query_dns(self, domain: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Executa resolucao autoritativa de registros DNS para qualquer dominio."""
        return self._execute_with_settlement("/api/v1/dns", {"domain": domain}, wallet_provider)

    def query_ssl(self, host: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Inspeciona e audita certificado TLS e SSL na porta 443 de um host."""
        return self._execute_with_settlement("/api/v1/ssl", {"host": host}, wallet_provider)

    def query_asn(self, ip: Optional[str] = None, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta inteligencia de rede BGP, numero de ASN e organizacao titular."""
        params = {"ip": ip} if ip else {}
        return self._execute_with_settlement("/api/v1/asn", params, wallet_provider)

    def query_cep(self, code: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta endereco postal brasileiro e codigo oficial IBGE por CEP."""
        return self._execute_with_settlement("/api/v1/cep", {"code": code}, wallet_provider)

    def query_cnpj(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta dados cadastrais de Pessoa Juridica na Receita Federal."""
        return self._execute_with_settlement("/api/v1/cnpj", {"cnpj": cnpj}, wallet_provider)

    def query_indicators(self, wallet_provider: Any = None) -> Dict[str, Any]:
        """Consulta taxas oficiais Selic meta e diaria, CDI anual e diario, IPCA 12m e mensal."""
        return self._execute_with_settlement("/api/v1/indicators", {}, wallet_provider)

    def query_holidays(
        self, year: Optional[int] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
        """Consulta o calendario oficial de feriados nacionais e dias sem expediente bancario."""
        params = {"year": year} if year is not None else {}
        return self._execute_with_settlement("/api/v1/holidays", params, wallet_provider)

    def query_workdays(
        self,
        start_date: Optional[str] = None,
        end_date: Optional[str] = None,
        days: Optional[int] = None,
        date: Optional[str] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        """Calcula dias uteis financeiros e projecao de vencimentos ANBIMA."""
        params: Dict[str, Any] = {}
        if start_date is not None:
            params["start_date"] = start_date
        if end_date is not None:
            params["end_date"] = end_date
        if days is not None:
            params["days"] = days
        if date is not None:
            params["date"] = date
        return self._execute_with_settlement("/api/v1/workdays", params, wallet_provider)

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

