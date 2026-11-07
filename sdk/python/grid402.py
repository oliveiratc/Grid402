"""
Grid402 Client SDK (Python)
Zero-dependency SDK for autonomous AI agents and M2M applications.
"""

from typing import Any, Callable, Dict, Optional, Union
import urllib.request
import urllib.error
import json
import ssl

DEFAULT_GRID402_URL = "https://orangered-jackal-900744.hostingersite.com"


class Grid402Error(Exception):
    """Exceção base para erros do cliente Grid402."""
    pass


class PaymentRequiredError(Grid402Error):
    """Exceção lançada quando a telemetria detalhada exige pagamento via x402."""
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
    """Exceção lançada quando um hash de transação já foi utilizado."""
    pass


class Grid402Client:
    """Cliente oficial para integração com o Oráculo de Telemetria Grid402."""

    def __init__(self, base_url: str = DEFAULT_GRID402_URL, timeout: float = 5.0, verify_ssl: bool = True):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.verify_ssl = verify_ssl
        self._ssl_context = ssl.create_default_context() if verify_ssl else ssl._create_unverified_context()

    def _request(self, endpoint: str, headers: Optional[Dict[str, str]] = None) -> Dict[str, Any]:
        url = f"{self.base_url}{endpoint}"
        req_headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-Python-SDK/1.0",
        }
        if headers:
            req_headers.update(headers)

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
        """Obtém o resumo executivo gratuito da telemetria (status por categoria e latências médias)."""
        return self._request("/status?format=json")

    def is_healthy(self, target_or_category: str) -> bool:
        """
        Verifica rapidamente se um alvo específico ou categoria está UP.
        Exemplos:
            client.is_healthy('SEFAZ')
            client.is_healthy('sefaz_sp')
            client.is_healthy('mercadopago')
        """
        summary = self.get_summary()

        # Verifica categoria (SEFAZ, Gateways, Bancos & PIX, Telecom & Infra)
        categories = summary.get("categories", {})
        if target_or_category in categories:
            return categories[target_or_category].get("status") == "UP"

        # Se for um ID individual, tenta consultar a telemetria paga se disponível em cache
        # Caso contrário, infere a categoria
        cat_lower = target_or_category.lower()
        if "sefaz" in cat_lower:
            return categories.get("SEFAZ", {}).get("status") == "UP"
        elif any(g in cat_lower for g in ["mercado", "asaas", "stripe", "pagbank", "cora"]):
            return categories.get("Gateways", {}).get("status") == "UP"
        elif any(b in cat_lower for g in ["bacen", "pix", "nubank", "inter", "openfinance"]):
            return categories.get("Bancos & PIX", {}).get("status") == "UP"

        return summary.get("system_status") == "UP"

    def get_challenge(self) -> Dict[str, Any]:
        """Obtém as diretrizes e parâmetros para pagamento x402 na Base Mainnet."""
        try:
            self._request("/telemetry")
            raise Grid402Error("Esperado desafio HTTP 402, mas a rota respondeu sem restrição.")
        except PaymentRequiredError as p_err:
            return p_err.challenge

    def get_detailed_telemetry(self, payment_tx_hash: str) -> Dict[str, Any]:
        """
        Resgata a telemetria detalhada item a item enviando o comprovante de pagamento on-chain.
        
        Args:
            payment_tx_hash: Hash da transação na rede Base (0x...)
        """
        if not payment_tx_hash.startswith("0x") or len(payment_tx_hash) != 66:
            raise ValueError("O payment_tx_hash deve ser um hash hexadecimal válido de 32 bytes (66 caracteres).")

        return self._request("/telemetry", headers={"X-Payment": payment_tx_hash})

    def guard(self, target_or_category: str, action: Callable[..., Any], fallback: Optional[Callable[..., Any]] = None) -> Any:
        """
        Padrão Circuit Breaker: Executa a ação se o serviço monitorado estiver UP.
        Se estiver DOWN ou DEGRADED, dispara o fallback ou bloqueia a execução.
        """
        if self.is_healthy(target_or_category):
            return action()
        
        if fallback:
            return fallback()
        
        raise Grid402Error(f"Circuito aberto: O serviço '{target_or_category}' está instável ou indisponível no momento.")
