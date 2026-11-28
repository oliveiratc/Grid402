"""
Grid402 Action Provider para Coinbase AgentKit, LangChain e CrewAI.

Permite que agentes autonomos com carteiras CDP (Coinbase Developer Platform)
na rede Base ou Session Pass pre-pago consultem todos os 25 servicos publicos
e utilitarios de rede do Grid402 com auto-liquidacao x402.
"""

from typing import Any, Dict, Optional
import json
import os
import ssl
import time
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


def _encode_erc20_transfer_data(recipient: str, raw_units: int) -> str:
    """Codifica calldata ABI para ERC-20 transfer(address,uint256)."""
    clean_recipient = str(recipient or "").lower().replace("0x", "").rjust(64, "0")
    amount_hex = hex(int(raw_units))[2:].rjust(64, "0")
    return f"0xa9059cbb{clean_recipient}{amount_hex}"


class Grid402ActionProvider:
    """
    Provedor de acoes do Grid402 compativel com Coinbase AgentKit, LangChain e CrewAI.

    Suporta:
    - Session Pass pre-pago (`session_pass` ou env `GRID402_SESSION_PASS`) para latencia <15ms;
    - Auto-liquidacao on-chain via CDP AgentKit moderno (`send_transaction` ERC-20) ou `transfer` legado,
      aguardando confirmacao do bloco antes do retry e validando HTTP 200;
    - Todos os 25 micro-servicos do catalogo Grid402.
    """

    def __init__(
        self,
        base_url: str = DEFAULT_ENDPOINT,
        sandbox: bool = False,
        session_pass: Optional[str] = None,
    ):
        self.base_url = base_url.rstrip("/")
        self.sandbox = sandbox
        self.session_pass = session_pass or os.environ.get("GRID402_SESSION_PASS") or None

    def _get_ssl_context(self) -> ssl.SSLContext:
        return ssl.create_default_context()

    def _send_and_confirm_usdc(
        self,
        wallet_provider: Any,
        recipient: str,
        price_usdc: str,
        raw_units: int,
        token_contract: str,
    ) -> str:
        """Executa transferencia ERC-20 USDC e aguarda confirmacao antes do retry."""
        if callable(getattr(wallet_provider, "send_transaction", None)):
            calldata = _encode_erc20_transfer_data(recipient, raw_units)
            transfer_result = wallet_provider.send_transaction(
                {"to": token_contract, "data": calldata, "value": 0}
            )
        elif callable(getattr(wallet_provider, "transfer", None)):
            transfer_result = wallet_provider.transfer(
                to=recipient,
                amount=price_usdc,
                asset_id="usdc",
            )
        else:
            raise RuntimeError(
                "wallet_provider deve implementar send_transaction() (CDP AgentKit v0.1+) ou transfer()."
            )

        if isinstance(transfer_result, str):
            tx_hash = transfer_result
        elif isinstance(transfer_result, dict):
            tx_hash = str(
                transfer_result.get("transaction_hash")
                or transfer_result.get("transactionHash")
                or transfer_result.get("hash")
                or ""
            )
        else:
            tx_hash = str(
                getattr(transfer_result, "transaction_hash", None)
                or getattr(transfer_result, "hash", None)
                or transfer_result
            )

        wait_fn = getattr(transfer_result, "wait", None)
        wait_receipt_fn = getattr(wallet_provider, "wait_for_transaction_receipt", None)
        if callable(wait_fn):
            wait_fn()
        elif callable(wait_receipt_fn):
            wait_receipt_fn(tx_hash)
        else:
            time.sleep(2.5)

        return tx_hash

    def _execute_with_settlement(
        self,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        """Executa requisicao HTTP com Session Pass ou auto-liquidacao x402."""
        filtered_params = {k: v for k, v in (params or {}).items() if v is not None and v != ""}
        query_string = urllib.parse.urlencode(filtered_params)

        url = f"{self.base_url}{path}"
        if query_string:
            url += f"?{query_string}"

        headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-CoinbaseAgentKit-Python/2.0",
        }

        if self.session_pass:
            headers["X-Payment"] = str(self.session_pass).strip()
        elif self.sandbox:
            headers["X-Payment"] = "demo"

        req = urllib.request.Request(url, headers=headers)
        ctx = self._get_ssl_context()

        # 1. Primeira tentativa de requisicao
        try:
            with urllib.request.urlopen(req, context=ctx, timeout=12) as response:
                return json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            raw_body = exc.read().decode("utf-8")
            try:
                challenge = json.loads(raw_body)
            except Exception:
                challenge = {"error": raw_body, "status_code": exc.code}

            # 2. Se retornou HTTP 402 Payment Required
            if exc.code == 402:
                if wallet_provider is not None and not self.sandbox:
                    price_usdc = str(challenge.get("price_usdc", "0.0050"))
                    raw_units = int(challenge.get("price_raw_units") or round(float(price_usdc) * 1_000_000) or 5000)
                    recipient = str(challenge.get("recipient_wallet") or GRID402_ORACLE_WALLET)
                    token_contract = str(challenge.get("token_contract") or BASE_USDC_CONTRACT)

                    try:
                        tx_hash = self._send_and_confirm_usdc(
                            wallet_provider, recipient, price_usdc, raw_units, token_contract
                        )

                        headers["X-Payment"] = tx_hash
                        retry_req = urllib.request.Request(url, headers=headers)
                        try:
                            with urllib.request.urlopen(retry_req, context=ctx, timeout=12) as retry_res:
                                status_code = getattr(retry_res, "status", 200)
                                retry_body = retry_res.read().decode("utf-8")
                                data = json.loads(retry_body)
                                if status_code == 200:
                                    data["_x402_settlement"] = {
                                        "settled": True,
                                        "tx_hash": tx_hash,
                                        "price_usdc": price_usdc,
                                        "network": "Base",
                                        "token": "USDC",
                                    }
                                    return data
                                return {
                                    "payment_required": status_code == 402,
                                    "error": f"Post-payment request failed with HTTP {status_code}",
                                    "http_status": status_code,
                                    "server_response": data,
                                    "_x402_settlement": {
                                        "settled": False,
                                        "tx_hash": tx_hash,
                                        "price_usdc": price_usdc,
                                        "network": "Base",
                                        "token": "USDC",
                                    },
                                }
                        except urllib.error.HTTPError as retry_exc:
                            retry_err_raw = retry_exc.read().decode("utf-8")
                            try:
                                retry_err_data = json.loads(retry_err_raw)
                            except Exception:
                                retry_err_data = {"raw_body": retry_err_raw}
                            return {
                                "payment_required": retry_exc.code == 402,
                                "error": f"Post-payment request failed with HTTP {retry_exc.code}",
                                "http_status": retry_exc.code,
                                "server_response": retry_err_data,
                                "_x402_settlement": {
                                    "settled": False,
                                    "tx_hash": tx_hash,
                                    "price_usdc": price_usdc,
                                    "network": "Base",
                                    "token": "USDC",
                                },
                            }
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
        return self._execute_with_settlement("/api/v1/forex", {}, wallet_provider)

    def query_crypto(
        self, symbol: Optional[str] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
        params = {"symbol": symbol} if symbol else {}
        return self._execute_with_settlement("/api/v1/crypto", params, wallet_provider)

    def query_dns(self, domain: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/dns", {"domain": domain}, wallet_provider)

    def query_ssl(self, host: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/ssl", {"host": host}, wallet_provider)

    def query_asn(self, ip: Optional[str] = None, wallet_provider: Any = None) -> Dict[str, Any]:
        params = {"ip": ip} if ip else {}
        return self._execute_with_settlement("/api/v1/asn", params, wallet_provider)

    def query_cep(self, code: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/cep", {"code": code}, wallet_provider)

    def query_cnpj(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._execute_with_settlement("/api/v1/cnpj", {"cnpj": clean}, wallet_provider)

    def query_indicators(self, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/indicators", {}, wallet_provider)

    def query_holidays(
        self, year: Optional[int] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
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

    def query_sanctions(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._execute_with_settlement("/api/v1/sanctions", {"cnpj": clean}, wallet_provider)

    def query_simples(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._execute_with_settlement("/api/v1/simples", {"cnpj": clean}, wallet_provider)

    def query_compliance(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._execute_with_settlement("/api/v1/compliance", {"cnpj": clean}, wallet_provider)

    def query_dossier_360(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._execute_with_settlement("/api/v1/dossier", {"cnpj": clean}, wallet_provider)

    def query_domain_audit(self, domain: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/domain-audit", {"domain": domain}, wallet_provider)

    def query_kyb_audit(
        self,
        cnpj: str,
        name: Optional[str] = None,
        lei: Optional[str] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        params: Dict[str, Any] = {"cnpj": clean}
        if name:
            params["name"] = name
        if lei:
            params["lei"] = lei
        return self._execute_with_settlement("/api/v1/kyb-audit", params, wallet_provider)

    def query_market_snapshot(self, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/market-snapshot", {}, wallet_provider)

    def query_lei(self, lei: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/lei", {"lei": lei.strip()}, wallet_provider)

    def query_ofac(self, target: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/ofac", {"target": target.strip()}, wallet_provider)

    def query_notarize(self, content: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/notarize", {"content": content}, wallet_provider)

    def query_global_markets(
        self,
        market: Optional[str] = None,
        base: Optional[str] = None,
        date: Optional[str] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        params = {"market": market, "base": base, "date": date}
        return self._execute_with_settlement("/api/v1/global-markets", params, wallet_provider)

    def query_extract(
        self,
        url: str,
        max_chars: Optional[int] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        params: Dict[str, Any] = {"url": url}
        if max_chars is not None:
            params["max_chars"] = max_chars
        return self._execute_with_settlement("/api/v1/extract", params, wallet_provider)

    def query_email_verify(self, email: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/email-verify", {"email": email.strip()}, wallet_provider)

    def query_pass(
        self,
        status: Optional[str] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        params = {"status": status} if status else {}
        return self._execute_with_settlement("/api/v1/pass", params, wallet_provider)

    def get_pass_balance(self, token: Optional[str] = None) -> Dict[str, Any]:
        t = token or self.session_pass or ""
        return self._execute_with_settlement("/api/v1/pass/balance", {"token": t})

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
                "User-Agent": "Grid402-CoinbaseAgentKit-Python/2.0",
            },
            method="POST",
        )
        ctx = self._get_ssl_context()
        with urllib.request.urlopen(req, context=ctx, timeout=10) as resp:
            return json.loads(resp.read().decode("utf-8"))

    # Aliases no padrao snake_case das ferramentas MCP (todos os 25 servicos)
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
    get_dossier_360 = query_dossier_360
    get_dossier = query_dossier_360
    get_domain_audit = query_domain_audit
    get_kyb_audit = query_kyb_audit
    get_market_snapshot = query_market_snapshot
    get_lei = query_lei
    get_ofac = query_ofac
    notarize = query_notarize
    get_global_markets = query_global_markets
    extract = query_extract
    email_verify = query_email_verify
    get_pass = query_pass
