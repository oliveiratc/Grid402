"""
Excecoes oficiais do SDK Grid402.
"""

from typing import Any, Dict, Optional


class Grid402Error(Exception):
    """Excecao base para erros do SDK Grid402."""
    pass


class Grid402PaymentRequiredError(Grid402Error):
    """
    Excecao levantada quando um endpoint do Grid402 retorna HTTP 402 Payment Required.
    
    Contem todos os metadados do desafio de pagamento x402 e pre-visualizacao parcial.
    """

    def __init__(self, challenge: Dict[str, Any], message: Optional[str] = None):
        self.challenge = challenge
        self.price_usdc: str = challenge.get("price_usdc", "0.0005")
        self.recipient: str = challenge.get("recipient_wallet", "")
        self.network: str = challenge.get("network", "Base")
        self.token: str = challenge.get("token", "USDC")
        self.preview_data: Optional[Dict[str, Any]] = challenge.get("preview_data")
        self.payment_details: Optional[Dict[str, Any]] = challenge.get("payment_details")

        default_msg = (
            f"Pagamento obrigatorio de {self.price_usdc} {self.token} na rede {self.network} "
            f"para a carteira {self.recipient}."
        )
        super().__init__(message or challenge.get("message") or default_msg)

