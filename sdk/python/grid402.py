"""
Grid402 Client SDK (Python)
Zero-dependency SDK for autonomous AI agents and M2M applications.
Access public open data and network utilities with x402 micropayments on Base.
"""

from typing import Any, Callable, Dict, Optional, Union
import urllib.request
import urllib.parse
import urllib.error
import json
import ssl

DEFAULT_GRID402_URL = "https://grid402.online"


class Grid402Error(Exception):
    """Exceção base para erros do cliente Grid402."""
    pass


class PaymentRequiredError(Grid402Error):
    """Exceção lançada quando uma consulta técnica exige micropagamento via x402."""
    def __init__(self, challenge: Dict[str, Any]):
        super().__init__(challenge.get("message", "Pagamento x402 requerido"))
        self.challenge = challenge
        self.recipient_wallet = challenge.get("recipient_wallet")
        self.price_usdc = challenge.get("price_usdc")
        self.price_raw_units = challenge.get("price_raw_units")
        self.network = challenge.get("network")
        self.chain_id = challenge.get("chain_id")
        self.token_contract = challenge.get("token_contract")


class ReplayAttackError(Grid402Error):
    """Exceção lançada quando um hash de transação já foi liquidado anteriormente."""
    pass


class Grid402Client:
    """
    Cliente oficial para integração com o Hub de Utilitários e Dados Públicos Grid402.
    
    AVISO LEGAL (AS-IS):
    Este software e os dados fornecidos são disponibilizados 'NO ESTADO EM QUE SE ENCONTRAM' (AS-IS),
    sem qualquer garantia de adequação a um propósito comercial específico.
    """

    def __init__(self, base_url: str = DEFAULT_GRID402_URL, timeout: float = 5.0, verify_ssl: Optional[bool] = None, sandbox: bool = False):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.sandbox = sandbox
        if verify_ssl is None:
            # Auto-fallback para preview domains em ambientes sem certificados raiz intermediários
            self.verify_ssl = False if "hostingersite.com" in self.base_url else True
        else:
            self.verify_ssl = verify_ssl
        self._ssl_context = ssl.create_default_context() if self.verify_ssl else ssl._create_unverified_context()

    def _request(self, endpoint: str, headers: Optional[Dict[str, str]] = None) -> Dict[str, Any]:
        url = f"{self.base_url}{endpoint}"
        req_headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-Python-SDK/2.2",
        }
        if headers:
            req_headers.update(headers)
        if self.sandbox:
            if "X-Payment" not in req_headers:
                req_headers["X-Payment"] = "demo"

        req = urllib.request.Request(url, headers=req_headers, method="GET")

        try:
            with urllib.request.urlopen(req, timeout=self.timeout, context=self._ssl_context) as response:
                body = response.read().decode("utf-8")
                return json.loads(body)
        except urllib.error.HTTPError as err:
            err_body = err.read().decode("utf-8")
            try:
                err_data = json.loads(err_body)
            except Exception:
                err_data = {"error": "HTTPError", "message": err_body}

            if err.code == 402:
                raise PaymentRequiredError(err_data)
            elif err.code == 403 and err_data.get("error") == "ReplayAttackDetected":
                raise ReplayAttackError(err_data.get("message", "Replay attack detectado"))
            else:
                raise Grid402Error(f"HTTP {err.code}: {err_data.get('message', err.reason)}")
        except Exception as ex:
            raise Grid402Error(f"Erro de conexão com o Grid402: {str(ex)}")

    def get_summary(self) -> Dict[str, Any]:
        """Obtém o resumo gratuito de status do hub e cotações agregadas."""
        return self._request("/status?format=json")

    def get_challenge(self, endpoint: str = "/api/v1/forex") -> Dict[str, Any]:
        """Dispara uma consulta técnica sem pagamento para obter o desafio x402 e parâmetros da Base."""
        try:
            self._request(endpoint)
            raise Grid402Error("Esperado desafio HTTP 402, mas a rota respondeu sem restrição.")
        except PaymentRequiredError as p_err:
            return p_err.challenge

    def get_forex(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta cotações fiduciárias em tempo real (USD, EUR, GBP para BRL)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/forex", headers=headers)

    def get_crypto(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta preços de mercado de criptoativos (BTC, ETH, SOL, USDC)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/crypto", headers=headers)

    def get_dns(self, domain: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Resolve registros DNS (A, AAAA, MX, TXT, NS, CNAME) de um domínio."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"domain": domain})
        return self._request(f"/api/v1/dns?{q}", headers=headers)

    def get_ssl(self, host: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Inspeciona o certificado TLS/SSL de um host (emissor, validade e dias restantes)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"host": host})
        return self._request(f"/api/v1/ssl?{q}", headers=headers)

    def get_asn(self, ip: Optional[str] = None, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Resolve metadados BGP, ASN e país de um endereço IP."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = f"?ip={urllib.parse.quote(ip)}" if ip else ""
        return self._request(f"/api/v1/asn{q}", headers=headers)

    def get_cep(self, code: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Resolve endereço público e código IBGE do município a partir de um CEP de 8 dígitos."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"code": code})
        return self._request(f"/api/v1/cep?{q}", headers=headers)

    def get_cnpj(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta dados cadastrais públicos de Pessoa Jurídica da Receita Federal (razão social, CNAE, situação, endereço)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"cnpj": cnpj})
        return self._request(f"/api/v1/cnpj?{q}", headers=headers)

    def get_indicators(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta indicadores macroeconomicos oficiais (Selic meta e diaria, CDI e IPCA acumulado)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/indicators", headers=headers)

    def get_holidays(self, year: Optional[int] = None, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta calendario oficial de feriados nacionais e bancarios ANBIMA."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = f"?year={year}" if year else ""
        return self._request(f"/api/v1/holidays{q}", headers=headers)

    def get_workdays(
        self,
        start_date: Optional[str] = None,
        end_date: Optional[str] = None,
        days: Optional[int] = None,
        date: Optional[str] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Calcula dias uteis financeiros, projecao de liquidacao ou checagem de dia util ANBIMA."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        params = {}
        if start_date:
            params["start_date"] = start_date
        if end_date:
            params["end_date"] = end_date
        if days is not None:
            params["days"] = str(days)
        if date:
            params["date"] = date
        qs = urllib.parse.urlencode(params)
        q = f"?{qs}" if qs else ""
        return self._request(f"/api/v1/workdays{q}", headers=headers)

    def get_sanctions(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta sancoes administrativas e inidoneidade cadastral nos sistemas CEIS e CNEP (CGU)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/sanctions?{q}", headers=headers)

    def get_simples(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta enquadramento tributario no Simples Nacional e MEI (SIMEI) na Receita Federal."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/simples?{q}", headers=headers)

    def get_compliance(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Dossiê consolidado de Compliance 360 com score de risco cadastral e integridade (0 a 100)."""
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/compliance?{q}", headers=headers)

    def is_healthy(self, service_name: str = "forex") -> bool:
        """Verifica se um serviço específico ou o hub está operacional."""
        summary = self.get_summary()
        services = summary.get("services", {})
        if service_name in services:
            return services[service_name].get("status") == "UP"
        return summary.get("status") == "OPERATIONAL"

    def guard(self, service_name: str, action: Callable[..., Any], fallback: Optional[Callable[..., Any]] = None) -> Any:
        """
        Circuit Breaker defensivo: Executa a ação se o micro-serviço estiver UP.
        """
        if self.is_healthy(service_name):
            return action()
        if fallback:
            return fallback()
        raise Grid402Error(f"Circuito aberto: O serviço '{service_name}' está temporariamente indisponível.")
