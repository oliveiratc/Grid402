"""
Cliente HTTP oficial para a API de Micropagamentos Grid402.
"""

from typing import Any, Dict, Optional
import hashlib
import json
import ssl
import urllib.error
import urllib.parse
import urllib.request

from grid402.exceptions import Grid402Error, Grid402PaymentRequiredError
from grid402.models import OracleVerificationResult, WorkdaysResponse


DEFAULT_ENDPOINT = "https://grid402.online"
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
    clean: Dict[str, Any] = {}
    for k in sorted(data.keys()):
        if k not in exclude:
            clean[k] = canonicalize_payload(data[k])
    return clean


def compute_data_hash(data: Any) -> str:
    """Calcula o hash SHA-256 em hexadecimal prefixado com 0x do payload normalizado."""
    clean = canonicalize_payload(data)
    serialized = json.dumps(clean, separators=(",", ":"), sort_keys=True, ensure_ascii=False)
    digest = hashlib.sha256(serialized.encode("utf-8")).hexdigest()
    return f"0x{digest}"


def verify_oracle_proof(
    response: Any,
    expected_oracle_address: Optional[str] = None,
    oracle_public_key: Optional[str] = None,
) -> OracleVerificationResult:
    """Valida a prova criptografica de oraculo emitida pelo Grid402."""
    if hasattr(response, "_raw_data") and isinstance(response._raw_data, dict):
        data_dict = response._raw_data
    elif hasattr(response, "model_dump"):
        data_dict = response.model_dump(exclude_none=True, by_alias=True)
    elif isinstance(response, dict):
        data_dict = dict(response)
    else:
        return OracleVerificationResult(valid=False, error="InvalidPayloadType")

    proof_raw = data_dict.get("_oracle_proof")
    if not proof_raw or not isinstance(proof_raw, dict):
        return OracleVerificationResult(valid=False, error="MissingProofBlock")

    computed_hash = compute_data_hash(data_dict)
    received_hash = proof_raw.get("data_hash")

    if computed_hash != received_hash:
        return OracleVerificationResult(
            valid=False,
            error="DataHashMismatch",
            expected_hash=computed_hash,
            received_hash=received_hash,
        )

    expected_addr = (expected_oracle_address or GRID402_ORACLE_WALLET).lower()
    proof_addr = str(proof_raw.get("oracle_address") or "").lower()
    if expected_addr and proof_addr != expected_addr:
        return OracleVerificationResult(
            valid=False,
            error="OracleAddressMismatch",
            oracle_address=proof_addr,
        )

    timestamp = int(proof_raw.get("timestamp") or 0)
    is_sandbox = bool(proof_raw.get("sandbox"))
    mode_str = "sandbox" if is_sandbox else "mainnet"
    message_to_sign = f"{computed_hash}:{timestamp}:{mode_str}".encode("utf-8")

    sig_hex = str(proof_raw.get("signature") or "").replace("0x", "")
    if not sig_hex:
        return OracleVerificationResult(valid=False, error="MissingSignature")

    try:
        sig_bytes = bytes.fromhex(sig_hex)
    except ValueError:
        return OracleVerificationResult(valid=False, error="MalformedSignatureHex")

    pub_pem = (oracle_public_key or DEFAULT_ORACLE_PUBKEY).encode("utf-8")

    try:
        from cryptography.hazmat.primitives.serialization import load_pem_public_key
        from cryptography.hazmat.primitives.asymmetric import ec
        from cryptography.hazmat.primitives import hashes

        pubkey = load_pem_public_key(pub_pem)
        pubkey.verify(sig_bytes, message_to_sign, ec.ECDSA(hashes.SHA256()))

        return OracleVerificationResult(
            valid=True,
            oracle_address=proof_addr,
            timestamp=timestamp,
            sandbox=is_sandbox,
        )
    except Exception as exc:
        return OracleVerificationResult(
            valid=False,
            error=f"InvalidSignature: {exc}",
            oracle_address=proof_addr,
            timestamp=timestamp,
            sandbox=is_sandbox,
        )


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
        session_pass: Optional[str] = None,
        rapidapi_key: Optional[str] = None,
        timeout: int = 10,
        raise_for_payment: bool = False,
    ):
        """
        Inicializa uma nova instancia do Grid402Client.
        
        Args:
            base_url: Endereco base da API do Grid402.
            sandbox: Se True, ativa o modo Sandbox gratuito para homologacao sem custo.
            payment_tx_hash: Hash de transacao USDC opcional padrao para as requisicoes.
            session_pass: Token pass_g402_... opcional para chamadas pre-pagas em <15ms.
            rapidapi_key: Chave X-RapidAPI-Key opcional para assinaturas RapidAPI Hub.
            timeout: Tempo limite em segundos para cada requisicao HTTP.
            raise_for_payment: Se True, lanca Grid402PaymentRequiredError ao receber HTTP 402.
                              Se False, retorna o dicionario com o desafio e dados parciais.
        """
        import os
        self.base_url = base_url.rstrip("/")
        self.sandbox = sandbox
        self.session_pass = session_pass or os.environ.get("GRID402_SESSION_PASS") or None
        self.rapidapi_key = rapidapi_key or os.environ.get("GRID402_RAPIDAPI_KEY") or None
        self.payment_tx_hash = payment_tx_hash or self.session_pass
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
            "User-Agent": "Grid402-Python-SDK/2.6.0",
        }

        if self.rapidapi_key:
            headers["X-RapidAPI-Key"] = self.rapidapi_key

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

    def get_workdays(
        self,
        start_date: Optional[str] = None,
        end_date: Optional[str] = None,
        days: Optional[int] = None,
        date: Optional[str] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Any:
        """Calcula dias uteis financeiros e projecoes de vencimento na convencao ANBIMA."""
        params: Dict[str, Any] = {}
        if start_date is not None:
            params["start_date"] = start_date
        if end_date is not None:
            params["end_date"] = end_date
        if days is not None:
            params["days"] = days
        if date is not None:
            params["date"] = date
        raw = self._request("/api/v1/workdays", params=params, payment_tx_hash=payment_tx_hash)
        if isinstance(raw, dict) and raw.get("status") == "success":
            inst = WorkdaysResponse(**raw)
            inst._raw_data = raw
            return inst
        return raw

    def get_sanctions(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta sancoes administrativas nos cadastros CEIS e CNEP da CGU."""
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._request("/api/v1/sanctions", params={"cnpj": clean}, payment_tx_hash=payment_tx_hash)

    def get_simples(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta enquadramento no Simples Nacional e MEI (SIMEI)."""
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._request("/api/v1/simples", params={"cnpj": clean}, payment_tx_hash=payment_tx_hash)

    def get_compliance(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Calcula o Compliance Score 360 (0 a 100) cruzando Receita Federal e CGU."""
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._request("/api/v1/compliance", params={"cnpj": clean}, payment_tx_hash=payment_tx_hash)

    def get_dossier_360(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Dossie 360 KYB Premium com Veredito Sintetizado para IA (APPROVED, FLAGGED, REJECTED)."""
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._request("/api/v1/dossier", params={"cnpj": clean}, payment_tx_hash=payment_tx_hash)

    def get_dossier(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Alias para get_dossier_360."""
        return self.get_dossier_360(cnpj, payment_tx_hash=payment_tx_hash)

    def get_domain_audit(self, domain: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Auditoria completa de infraestrutura de dominio (DNS + TLS/SSL + BGP/ASN)."""
        return self._request("/api/v1/domain-audit", params={"domain": domain}, payment_tx_hash=payment_tx_hash)

    def get_kyb_audit(self, cnpj: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Auditoria corporativa global KYB em chamada unica (CNPJ + OFAC/ONU + GLEIF LEI)."""
        clean = "".join(ch for ch in str(cnpj or "") if ch.isdigit())
        return self._request("/api/v1/kyb-audit", params={"cnpj": clean}, payment_tx_hash=payment_tx_hash)

    def get_market_snapshot(self, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Panorama financeiro e macroeconomico consolidado em chamada unica."""
        return self._request("/api/v1/market-snapshot", payment_tx_hash=payment_tx_hash)

    def get_lei(self, lei: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Consulta identidade corporativa internacional oficial (ISO 17442) via GLEIF."""
        return self._request("/api/v1/lei", params={"lei": lei.strip()}, payment_tx_hash=payment_tx_hash)

    def get_ofac(self, target: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Triagem de sancoes globais (OFAC SDN / ONU) e risco AML Web3."""
        return self._request("/api/v1/ofac", params={"target": target.strip()}, payment_tx_hash=payment_tx_hash)

    def notarize(self, content: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Carimbo do tempo criptografico M2M e prova de existencia SHA-256 assinada com ECDSA."""
        return self._request("/api/v1/notarize", params={"content": content}, payment_tx_hash=payment_tx_hash)

    def get_global_markets(
        self,
        market: Optional[str] = None,
        base: Optional[str] = None,
        date: Optional[str] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Calendario de liquidacao de bolsas globais (US, EU, UK, BR) e taxas de cambio BCE."""
        params = {"market": market, "base": base, "date": date}
        return self._request("/api/v1/global-markets", params=params, payment_tx_hash=payment_tx_hash)

    def extract(
        self,
        url: str,
        max_chars: Optional[int] = None,
        payment_tx_hash: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Extrai paginas web publicas HTTP/HTTPS para Markdown limpo pronto para LLMs e RAG."""
        params: Dict[str, Any] = {"url": url}
        if max_chars is not None:
            params["max_chars"] = max_chars
        return self._request("/api/v1/extract", params=params, payment_tx_hash=payment_tx_hash)

    def email_verify(self, email: str, payment_tx_hash: Optional[str] = None) -> Dict[str, Any]:
        """Audita entregabilidade de e-mail B2B estritamente Zero-PII (RFC, MX, SPF, DMARC, disposable)."""
        return self._request("/api/v1/email-verify", params={"email": email.strip()}, payment_tx_hash=payment_tx_hash)

    def get_pass_balance(self, token: Optional[str] = None) -> Dict[str, Any]:
        """Consulta gratuita em tempo real do saldo de creditos e Spend Controls de um Session Pass."""
        t = token or self.session_pass or self.payment_tx_hash or ""
        return self._request("/api/v1/pass/balance", params={"token": t})

    def get_pass_statement(
        self,
        token: Optional[str] = None,
        endpoint: Optional[str] = None,
        outcome: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Consulta gratuita do extrato Zero-PII (ate 200 lancamentos recentes) de um Session Pass."""
        t = token or self.session_pass or self.payment_tx_hash or ""
        return self._request("/api/v1/pass/statement", params={"token": t, "endpoint": endpoint, "outcome": outcome})

    def verify_proof(
        self,
        response: Any,
        expected_oracle_address: Optional[str] = None,
        oracle_public_key: Optional[str] = None,
    ) -> OracleVerificationResult:
        """Valida a prova matematica de autenticidade emitida pelo oraculo Grid402."""
        return verify_oracle_proof(response, expected_oracle_address=expected_oracle_address, oracle_public_key=oracle_public_key)

