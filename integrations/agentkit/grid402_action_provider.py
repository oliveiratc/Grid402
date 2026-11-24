"""
Grid402 Action Provider para Coinbase AgentKit (Python)

Permite que agentes autonomos com carteiras CDP (Coinbase Developer Platform)
na rede Base ou Session Pass pre-pago consultem todos os 25 micro-servicos
do Grid402 com auto-liquidacao x402 e confirmacao on-chain.
"""

from typing import Any, Dict, Optional
import json
import os
import ssl
import time
import urllib.error
import urllib.parse
import urllib.request

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

    class ExtractInput(BaseModel):
        url: str = Field(description="URL publica HTTP/HTTPS para extracao em Markdown")
        max_chars: Optional[int] = Field(default=None, description="Limite maximo de caracteres (500-25000)")

    class EmailVerifyInput(BaseModel):
        email: str = Field(description="Endereco de e-mail corporativo para auditoria Zero-PII")

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

    class ExtractInput(BaseModel):  # type: ignore
        pass

    class EmailVerifyInput(BaseModel):  # type: ignore
        pass

    class VerifyInput(BaseModel):  # type: ignore
        pass


GRID402_ORACLE_WALLET = "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90"
BASE_USDC_CONTRACT = "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"


def _encode_erc20_transfer_data(recipient: str, raw_units: int) -> str:
    clean_recipient = str(recipient or "").lower().replace("0x", "").rjust(64, "0")
    amount_hex = hex(int(raw_units))[2:].rjust(64, "0")
    return f"0xa9059cbb{clean_recipient}{amount_hex}"


class Grid402ActionProvider:
    """Provedor de acoes do Grid402 compativel com Coinbase AgentKit e LangChain."""

    def __init__(
        self,
        base_url: str = "https://grid402.online",
        sandbox: bool = False,
        session_pass: Optional[str] = None,
    ):
        self.base_url = base_url.rstrip("/")
        self.sandbox = sandbox
        self.session_pass = session_pass or os.environ.get("GRID402_SESSION_PASS") or None

    def _get_ssl_context(self) -> ssl.SSLContext:
        ctx = ssl.create_default_context()
        if "hostingersite.com" in self.base_url:
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
        return ctx

    def _send_and_confirm_usdc(
        self,
        wallet_provider: Any,
        recipient: str,
        price_usdc: str,
        raw_units: int,
        token_contract: str,
    ) -> str:
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
            raise RuntimeError("wallet_provider deve implementar send_transaction() ou transfer().")

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
        self, path: str, params: Optional[Dict[str, Any]] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
        params = params or {}
        query_string = urllib.parse.urlencode({k: v for k, v in params.items() if v is not None and v != ""})
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

        # 1. Primeira requisicao
        try:
            with urllib.request.urlopen(req, context=ctx, timeout=12) as response:
                return json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            # 2. Se retornou HTTP 402 Payment Required
            if e.code == 402:
                challenge = json.loads(e.read().decode("utf-8"))

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
                                data = json.loads(retry_res.read().decode("utf-8"))
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
                        except urllib.error.HTTPError as retry_err:
                            retry_raw = retry_err.read().decode("utf-8")
                            try:
                                retry_data = json.loads(retry_raw)
                            except Exception:
                                retry_data = {"raw_body": retry_raw}
                            return {
                                "payment_required": retry_err.code == 402,
                                "error": f"Post-payment request failed with HTTP {retry_err.code}",
                                "http_status": retry_err.code,
                                "server_response": retry_data,
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

                return challenge
            raise

    def get_summary(self) -> Dict[str, Any]:
        return self._execute_with_settlement("/status")

    def query_cnpj(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/cnpj", {"cnpj": cnpj}, wallet_provider)

    def query_cep(self, code: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/cep", {"code": code}, wallet_provider)

    def query_forex(self, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/forex", {}, wallet_provider)

    def query_crypto(self, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/crypto", {}, wallet_provider)

    def query_dns(self, domain: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/dns", {"domain": domain}, wallet_provider)

    def query_ssl(self, host: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/ssl", {"host": host}, wallet_provider)

    def query_asn(self, ip: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/asn", {"ip": ip}, wallet_provider)

    def query_indicators(self, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/indicators", {}, wallet_provider)

    def query_holidays(self, year: Optional[int] = None, wallet_provider: Any = None) -> Dict[str, Any]:
        params: Dict[str, Any] = {}
        if year is not None:
            params["year"] = str(year)
        return self._execute_with_settlement("/api/v1/holidays", params, wallet_provider)

    def query_workdays(
        self,
        start_date: str,
        end_date: Optional[str] = None,
        days: Optional[int] = None,
        wallet_provider: Any = None,
    ) -> Dict[str, Any]:
        params: Dict[str, Any] = {"start_date": start_date}
        if end_date:
            params["end_date"] = end_date
        if days is not None:
            params["days"] = str(days)
        return self._execute_with_settlement("/api/v1/workdays", params, wallet_provider)

    def query_sanctions(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/sanctions", {"cnpj": cnpj}, wallet_provider)

    def query_simples(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/simples", {"cnpj": cnpj}, wallet_provider)

    def query_compliance(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/compliance", {"cnpj": cnpj}, wallet_provider)

    def query_dossier_360(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/dossier", {"cnpj": cnpj}, wallet_provider)

    def query_domain_audit(self, domain: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/domain-audit", {"domain": domain}, wallet_provider)

    def query_kyb_audit(self, cnpj: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/kyb-audit", {"cnpj": cnpj}, wallet_provider)

    def query_market_snapshot(self, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/market-snapshot", {}, wallet_provider)

    def query_lei(self, lei: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/lei", {"lei": lei}, wallet_provider)

    def query_ofac(self, target: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/ofac", {"target": target}, wallet_provider)

    def query_notarize(self, content: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/notarize", {"content": content}, wallet_provider)

    def query_global_markets(
        self, market: Optional[str] = None, base: Optional[str] = None, date: Optional[str] = None, wallet_provider: Any = None
    ) -> Dict[str, Any]:
        return self._execute_with_settlement(
            "/api/v1/global-markets", {"market": market, "base": base, "date": date}, wallet_provider
        )

    def query_extract(self, url: str, max_chars: Optional[int] = None, wallet_provider: Any = None) -> Dict[str, Any]:
        params: Dict[str, Any] = {"url": url}
        if max_chars is not None:
            params["max_chars"] = str(max_chars)
        return self._execute_with_settlement("/api/v1/extract", params, wallet_provider)

    def query_email_verify(self, email: str, wallet_provider: Any = None) -> Dict[str, Any]:
        return self._execute_with_settlement("/api/v1/email-verify", {"email": email}, wallet_provider)

    def verify_proof(self, payload: Any) -> Dict[str, Any]:
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
    get_dossier_360 = query_dossier_360
    get_domain_audit = query_domain_audit
    get_kyb_audit = query_kyb_audit
    get_market_snapshot = query_market_snapshot
    get_lei = query_lei
    get_ofac = query_ofac
    notarize = query_notarize
    get_global_markets = query_global_markets
    extract = query_extract
    email_verify = query_email_verify
    verify_proof_alias = verify_proof
