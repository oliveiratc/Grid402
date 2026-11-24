"""
Grid402 Client SDK (Python)
Zero-dependency SDK for autonomous AI agents and M2M applications.
Access all 25 public open data and network utilities with Session Pass, RapidAPI Hub or x402 USDC.
"""

from typing import Any, Callable, Dict, Optional
import hashlib
import json
import os
import ssl
import urllib.error
import urllib.parse
import urllib.request

DEFAULT_GRID402_URL = "https://grid402.online"
DEFAULT_RAPIDAPI_HOST = "grid402-open-data-kyb-ai-utilities.p.rapidapi.com"
GRID402_ORACLE_WALLET = "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90"
DEFAULT_ORACLE_PUBKEY = """-----BEGIN PUBLIC KEY-----
MFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEq8MF77hj4TAO3ktuRu5GPRdXQP9m9JhO
c89Y7XGoNGECnG/UiuoqDB/Y7/2s9ww3J7zJ8mC83Zc/Kd0FeDutaQ==
-----END PUBLIC KEY-----"""


def canonicalize_payload(data: Any) -> Any:
    """Normaliza recursivamente o objeto ordenando chaves e excluindo metadados transientes."""
    if data is None or not isinstance(data, (dict, list)):
        return data
    exclude = {"_oracle_proof", "sandbox_info", "_x402_settlement"}
    if isinstance(data, list):
        return [canonicalize_payload(item) for item in data]
    return {k: canonicalize_payload(data[k]) for k in sorted(data.keys()) if k not in exclude}


def compute_data_hash(data: Any) -> str:
    """Calcula o hash SHA-256 em hexadecimal prefixado com 0x do payload normalizado."""
    clean = canonicalize_payload(data)
    serialized = json.dumps(clean, separators=(",", ":"), sort_keys=True, ensure_ascii=False)
    digest = hashlib.sha256(serialized.encode("utf-8")).hexdigest()
    return f"0x{digest}"


def verify_oracle_proof(
    response: Dict[str, Any],
    expected_oracle_address: Optional[str] = None,
    oracle_public_key: Optional[str] = None,
) -> Dict[str, Any]:
    """Valida a prova criptografica de oraculo (_oracle_proof) localmente ou via biblioteca cryptography."""
    if not isinstance(response, dict) or not isinstance(response.get("_oracle_proof"), dict):
        return {"valid": False, "error": "MissingProofBlock"}

    proof = response["_oracle_proof"]
    computed_hash = compute_data_hash(response)
    received_hash = proof.get("data_hash")
    if computed_hash != received_hash:
        return {
            "valid": False,
            "error": "DataHashMismatch",
            "expected_hash": computed_hash,
            "received_hash": received_hash,
        }

    expected_addr = (expected_oracle_address or GRID402_ORACLE_WALLET).lower()
    proof_addr = str(proof.get("oracle_address") or "").lower()
    if expected_addr and proof_addr != expected_addr:
        return {
            "valid": False,
            "error": "OracleAddressMismatch",
            "expected_address": expected_addr,
            "received_address": proof_addr,
        }

    timestamp = int(proof.get("timestamp") or 0)
    is_sandbox = bool(proof.get("sandbox"))
    mode_str = "sandbox" if is_sandbox else "mainnet"
    message_to_sign = f"{computed_hash}:{timestamp}:{mode_str}".encode("utf-8")

    sig_hex = str(proof.get("signature") or "").replace("0x", "")
    if not sig_hex:
        return {"valid": False, "error": "MissingSignature"}

    try:
        sig_bytes = bytes.fromhex(sig_hex)
    except ValueError:
        return {"valid": False, "error": "MalformedSignatureHex"}

    try:
        from cryptography.hazmat.primitives.serialization import load_pem_public_key
        from cryptography.hazmat.primitives.asymmetric import ec
        from cryptography.hazmat.primitives import hashes

        pub_pem = (oracle_public_key or DEFAULT_ORACLE_PUBKEY).encode("utf-8")
        pubkey = load_pem_public_key(pub_pem)
        pubkey.verify(sig_bytes, message_to_sign, ec.ECDSA(hashes.SHA256()))
        return {
            "valid": True,
            "oracle_address": proof_addr,
            "timestamp": timestamp,
            "sandbox": is_sandbox,
        }
    except ImportError:
        return {
            "valid": False,
            "error": "CryptographyNotInstalled",
            "hint": "Instale 'cryptography' para validacao ECDSA offline ou chame client.verify_proof_remote(response).",
        }
    except Exception as exc:
        return {
            "valid": False,
            "error": f"InvalidSignature: {exc}",
            "oracle_address": proof_addr,
            "timestamp": timestamp,
            "sandbox": is_sandbox,
        }


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
    Suporta Session Pass (`GRID402_SESSION_PASS`), RapidAPI Hub (`GRID402_RAPIDAPI_KEY`),
    x402 USDC on-chain/gasless e Sandbox.
    """

    def __init__(
        self,
        base_url: str = DEFAULT_GRID402_URL,
        timeout: float = 10.0,
        verify_ssl: Optional[bool] = None,
        sandbox: bool = False,
        session_pass: Optional[str] = None,
        rapidapi_key: Optional[str] = None,
        rapidapi_host: Optional[str] = None,
        payment_tx_hash: Optional[str] = None,
    ):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.sandbox = sandbox
        self.session_pass = session_pass or os.environ.get("GRID402_SESSION_PASS") or None
        self.rapidapi_key = rapidapi_key or os.environ.get("GRID402_RAPIDAPI_KEY") or None
        raw_rapid_host = rapidapi_host or os.environ.get("GRID402_RAPIDAPI_HOST") or DEFAULT_RAPIDAPI_HOST
        self.rapidapi_host = raw_rapid_host.replace("https://", "").replace("http://", "").split("/")[0].strip()
        self.payment_tx_hash = payment_tx_hash or self.session_pass
        if verify_ssl is None:
            self.verify_ssl = False if "hostingersite.com" in self.base_url else True
        else:
            self.verify_ssl = verify_ssl
        self._ssl_context = ssl.create_default_context() if self.verify_ssl else ssl._create_unverified_context()

    def _request(self, endpoint: str, headers: Optional[Dict[str, str]] = None) -> Dict[str, Any]:
        req_headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-Python-SDK/2.7",
        }
        if headers:
            req_headers.update(headers)

        explicit_payment = req_headers.get("X-Payment") or req_headers.get("PAYMENT-SIGNATURE") or self.payment_tx_hash
        use_rapidapi_proxy = bool(self.rapidapi_key and not explicit_payment)

        effective_base_url = self.base_url
        if use_rapidapi_proxy:
            if ".p.rapidapi.com" in self.base_url:
                parsed = urllib.parse.urlparse(self.base_url)
                rapid_host_header = parsed.netloc or self.rapidapi_host
            else:
                effective_base_url = f"https://{self.rapidapi_host}"
                rapid_host_header = self.rapidapi_host
            req_headers.setdefault("X-RapidAPI-Key", str(self.rapidapi_key).strip())
            req_headers.setdefault("X-RapidAPI-Host", rapid_host_header)
        elif self.payment_tx_hash and "X-Payment" not in req_headers:
            req_headers["X-Payment"] = self.payment_tx_hash
        elif self.sandbox and "X-Payment" not in req_headers:
            req_headers["X-Payment"] = "demo"

        url = f"{effective_base_url}{endpoint}"
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
        return self._request("/status?format=json")

    def get_challenge(self, endpoint: str = "/api/v1/forex") -> Dict[str, Any]:
        try:
            self._request(endpoint)
            raise Grid402Error("Esperado desafio HTTP 402, mas a rota respondeu sem restrição.")
        except PaymentRequiredError as p_err:
            return p_err.challenge

    def get_forex(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/forex", headers=headers)

    def get_crypto(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/crypto", headers=headers)

    def get_dns(self, domain: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"domain": domain})
        return self._request(f"/api/v1/dns?{q}", headers=headers)

    def get_ssl(self, host: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"host": host})
        return self._request(f"/api/v1/ssl?{q}", headers=headers)

    def get_asn(self, ip: Optional[str] = None, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = f"?ip={urllib.parse.quote(ip)}" if ip else ""
        return self._request(f"/api/v1/asn{q}", headers=headers)

    def get_cep(self, code: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"code": code})
        return self._request(f"/api/v1/cep?{q}", headers=headers)

    def get_cnpj(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"cnpj": cnpj})
        return self._request(f"/api/v1/cnpj?{q}", headers=headers)

    def get_indicators(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/indicators", headers=headers)

    def get_holidays(self, year: Optional[int] = None, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
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
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/sanctions?{q}", headers=headers)

    def get_simples(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/simples?{q}", headers=headers)

    def get_compliance(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/compliance?{q}", headers=headers)

    def get_dossier_360(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/dossier?{q}", headers=headers)

    def get_dossier(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        return self.get_dossier_360(cnpj, payment_tx_hash=payment_tx_hash)

    def get_domain_audit(self, domain: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"domain": domain})
        return self._request(f"/api/v1/domain-audit?{q}", headers=headers)

    def get_kyb_audit(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        q = urllib.parse.urlencode({"cnpj": clean})
        return self._request(f"/api/v1/kyb-audit?{q}", headers=headers)

    def get_market_snapshot(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        return self._request("/api/v1/market-snapshot", headers=headers)

    def get_lei(self, lei: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"lei": lei.strip()})
        return self._request(f"/api/v1/lei?{q}", headers=headers)

    def get_ofac(self, target: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"target": target.strip()})
        return self._request(f"/api/v1/ofac?{q}", headers=headers)

    def notarize(self, content: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"content": content})
        return self._request(f"/api/v1/notarize?{q}", headers=headers)

    def get_global_markets(
        self,
        market: Optional[str] = None,
        base: Optional[str] = None,
        date: Optional[str] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        params = {k: v for k, v in {"market": market, "base": base, "date": date}.items() if v is not None}
        qs = urllib.parse.urlencode(params)
        q = f"?{qs}" if qs else ""
        return self._request(f"/api/v1/global-markets{q}", headers=headers)

    def extract(
        self,
        url: str,
        max_chars: Optional[int] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        params: Dict[str, Any] = {"url": url}
        if max_chars is not None:
            params["max_chars"] = str(max_chars)
        q = urllib.parse.urlencode(params)
        return self._request(f"/api/v1/extract?{q}", headers=headers)

    def email_verify(self, email: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        headers = {"X-Payment": payment_tx_hash} if payment_tx_hash else None
        q = urllib.parse.urlencode({"email": email.strip()})
        return self._request(f"/api/v1/email-verify?{q}", headers=headers)

    def get_pass_balance(self, token: Optional[str] = None) -> Dict[str, Any]:
        t = token or self.session_pass or self.payment_tx_hash or ""
        q = urllib.parse.urlencode({"token": t})
        return self._request(f"/api/v1/pass/balance?{q}")

    def get_pass_statement(
        self,
        token: Optional[str] = None,
        endpoint: Optional[str] = None,
        outcome: Optional[str] = None,
    ) -> Dict[str, Any]:
        t = token or self.session_pass or self.payment_tx_hash or ""
        params = {k: v for k, v in {"token": t, "endpoint": endpoint, "outcome": outcome}.items() if v is not None}
        q = urllib.parse.urlencode(params)
        return self._request(f"/api/v1/pass/statement?{q}")

    def verify_proof(
        self,
        response: Dict[str, Any],
        expected_oracle_address: Optional[str] = None,
        oracle_public_key: Optional[str] = None,
    ) -> Dict[str, Any]:
        return verify_oracle_proof(response, expected_oracle_address=expected_oracle_address, oracle_public_key=oracle_public_key)

    def verify_proof_remote(self, payload: Any) -> Dict[str, Any]:
        url = f"{self.base_url}/api/v1/verify"
        body = payload if isinstance(payload, str) else json.dumps(payload)
        req = urllib.request.Request(
            url,
            data=body.encode("utf-8"),
            headers={"Content-Type": "application/json", "Accept": "application/json", "User-Agent": "Grid402-Python-SDK/2.7"},
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=self.timeout, context=self._ssl_context) as resp:
            return json.loads(resp.read().decode("utf-8"))

    def is_healthy(self, service_name: str = "forex") -> bool:
        summary = self.get_summary()
        services = summary.get("services", {})
        if service_name in services:
            return services[service_name].get("status") == "UP"
        return summary.get("status") == "OPERATIONAL"

    def guard(self, service_name: str, action: Callable[..., Any], fallback: Optional[Callable[..., Any]] = None) -> Any:
        if self.is_healthy(service_name):
            return action()
        if fallback:
            return fallback()
        raise Grid402Error(f"Circuito aberto: O serviço '{service_name}' está temporariamente indisponível.")
