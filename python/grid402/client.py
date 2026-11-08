"""
Cliente HTTP oficial para a API de Micropagamentos Grid402.
"""

from typing import Any, Dict, Optional
import json
import ssl
import urllib.error
import urllib.parse
import urllib.request

from grid402.exceptions import Grid402Error, Grid402PaymentRequiredError


DEFAULT_ENDPOINT = "https://grid402.com.br"


class Grid402Client:
    """
    Cliente Python para consumo das ferramentas públicas do Grid402.
    
    Suporta modo de producao na Base Mainnet, modo Sandbox gratuito para testes
    e tratamento estruturado de desafios HTTP 402.
    """

    def __init__(
        self,
        base_url: str = DEFAULT_ENDPOINT,
        sandbox: bool = False,
        payment_tx_hash: Optional[str] = None,
        timeout: int = 10,
        raise_for_payment: bool = False,
    ):
        """
        Inicializa uma nova instancia do Grid402Client.
        
        Args:
            base_url: Endereco base da API do Grid402.
            sandbox: Se True, ativa o modo Sandbox gratuito para homologacao sem custo.
            payment_tx_hash: Hash de transacao USDC opcional padrao para as requisicoes.
            timeout: Tempo limite em segundos para cada requisicao HTTP.
            raise_for_payment: Se True, lanca Grid402PaymentRequiredError ao receber HTTP 402.
                              Se False, retorna o dicionario com o desafio e dados parciais.
        """
        self.base_url = base_url.rstrip("/")
        self.sandbox = sandbox
        self.payment_tx_hash = payment_tx_hash
        self.timeout = timeout
        self.raise_for_payment = raise_for_payment

    def _get_ssl_context(self) -> ssl.SSLContext:
        ctx = ssl.create_default_context()
        if "hostingersite.com" in self.base_url:
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        return ctx

    def _request(
        self,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Executa a requisicao HTTP para a API Grid402."""
        filtered_params = {k: v for k, v in (params or {}).items() if v is not None}
        query_string = urllib.parse.urlencode(filtered_params)

        url = f"{self.base_url}{path}"
        if query_string:
            url += f"?{query_string}"

        headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-Python-SDK/2.2",
        }

        active_tx_hash = payment_tx_hash or self.payment_tx_hash
        if active_tx_hash:
            headers["X-Payment"] = active_tx_hash
        elif self.sandbox:
            headers["X-Payment"] = "demo"

        req = urllib.request.Request(url, headers=headers)
        ctx = self._get_ssl_context()

        try:
            with urllib.request.urlopen(req, context=ctx, timeout=self.timeout) as response:
                return json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            raw_body = exc.read().decode("utf-8")
            try:
                error_data = json.loads(raw_body)
            except Exception:
                error_data = {"error": raw_body, "status_code": exc.code}

            if exc.code == 402:
                if self.raise_for_payment:
                    raise Grid402PaymentRequiredError(error_data) from exc
                error_data["payment_required"] = True
                return error_data

            raise Grid402Error(
                f"Erro na API Grid402 (HTTP {exc.code}): {error_data.get('error') or raw_body}"
            ) from exc
        except urllib.error.URLError as exc:
            raise Grid402Error(f"Falha de conexao com {self.base_url}: {exc.reason}") from exc

    def get_summary(self) -> Dict[str, Any]:
        """Consulta o resumo e status operacional consolidado do Grid402 (gratuito)."""
        return self._request("/status")

    def get_forex(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta cotacoes fiduciarias oficiais em tempo real de USD, EUR e GBP para BRL."""
        return self._request("/api/v1/forex", payment_tx_hash=payment_tx_hash)

    def get_crypto(
        self, symbol: Optional[str] = None, payment_tx_hash: Optional[str] = None
    ) -> Dict[str, Any]:
        """Consulta precos de mercado spot de criptoativos em tempo real."""
        params = {"symbol": symbol} if symbol else {}
        return self._request("/api/v1/crypto", params=params, payment_tx_hash=payment_tx_hash)

    def get_dns(self, domain: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Executa resolucao DNS autoritativa de registros A, AAAA, MX, TXT, NS e CNAME."""
        return self._request("/api/v1/dns", params={"domain": domain}, payment_tx_hash=payment_tx_hash)

    def get_ssl(self, host: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Inspeciona e valida o certificado TLS e SSL na porta 443 de um host remoto."""
        return self._request("/api/v1/ssl", params={"host": host}, payment_tx_hash=payment_tx_hash)

    def get_asn(
        self, ip: Optional[str] = None, payment_tx_hash: Optional[str] = None
    ) -> Dict[str, Any]:
        """Consulta inteligencia de rede BGP, identificando numero de ASN e organizacao."""
        params = {"ip": ip} if ip else {}
        return self._request("/api/v1/asn", params=params, payment_tx_hash=payment_tx_hash)

    def get_cep(self, code: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta a base publica de enderecamento postal brasileiro e codigo IBGE por CEP."""
        return self._request("/api/v1/cep", params={"code": code}, payment_tx_hash=payment_tx_hash)

    def get_cnpj(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta dados cadastrais publicos de Pessoa Juridica na Receita Federal."""
        return self._request("/api/v1/cnpj", params={"cnpj": cnpj}, payment_tx_hash=payment_tx_hash)

    def get_indicators(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta indicadores economicos oficiais (Selic, CDI e IPCA) do Banco Central e IBGE."""
        return self._request("/api/v1/indicators", payment_tx_hash=payment_tx_hash)

    def get_holidays(
        self, year: Optional[int] = None, payment_tx_hash: Optional[str] = None
    ) -> Dict[str, Any]:
        """Consulta calendario oficial de feriados nacionais e bancarios (ANBIMA e Febraban)."""
        params = {"year": year} if year is not None else {}
        return self._request("/api/v1/holidays", params=params, payment_tx_hash=payment_tx_hash)

