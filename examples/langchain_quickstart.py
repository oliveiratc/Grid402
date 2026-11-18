"""
Grid402 - LangChain and CrewAI Quickstart Tool (Python)

Como conectar agentes de IA construídos com LangChain ou CrewAI diretamente
aos oráculos e micropagamentos do Grid402 (Base Mainnet / Modo Sandbox).

Zero dependências externas obrigatórias (usa standard library urllib).
"""

import os
import json
import ssl
import urllib.parse
import urllib.request
import urllib.error
from typing import Optional, Dict, Any

class Grid402Client:
    """Cliente leve para consultar serviços do Grid402 com suporte a Sandbox e Session Pass."""

    def __init__(
        self,
        base_url: str = "https://grid402.online",
        session_pass: Optional[str] = None,
        use_sandbox: bool = False,
    ):
        self.base_url = base_url.rstrip("/")
        self.session_pass = session_pass or os.getenv("GRID402_SESSION_PASS")
        self.use_sandbox = use_sandbox or (os.getenv("GRID402_SANDBOX", "").lower() in ("1", "true"))

    def _headers(self) -> Dict[str, str]:
        headers = {
            "Accept": "application/json",
            "User-Agent": "Grid402-LangChain-Agent/1.0",
        }
        if self.session_pass:
            headers["X-Session-Pass"] = self.session_pass
        elif self.use_sandbox:
            headers["X-Payment"] = "demo"
        return headers

    def query(self, endpoint: str, params: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        query_str = ("?" + urllib.parse.urlencode(params)) if params else ""
        url = f"{self.base_url}{endpoint}{query_str}"
        req = urllib.request.Request(url, headers=self._headers())

        try:
            # Tenta com verificação SSL padrão; se a CA local falhar, usa fallback
            try:
                ctx = ssl.create_default_context()
                resp_handle = urllib.request.urlopen(req, context=ctx, timeout=10)
            except urllib.error.URLError as url_err:
                if "CERTIFICATE_VERIFY_FAILED" in str(url_err) or "ssl" in str(url_err).lower():
                    ctx = ssl._create_unverified_context()
                    resp_handle = urllib.request.urlopen(req, context=ctx, timeout=10)
                else:
                    raise

            with resp_handle as resp:
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            if e.code == 402:
                challenge = json.loads(e.read().decode("utf-8"))
                return {
                    "error": "PaymentRequired",
                    "status": 402,
                    "message": challenge.get("message", "Pagamento x402 exigido."),
                    "price_usdc": challenge.get("price_usdc"),
                    "network": challenge.get("network", "base"),
                    "recipient_wallet": challenge.get("recipient_wallet"),
                    "teaser_data": challenge.get("teaser_data", {}),
                }
            else:
                raw = e.read().decode("utf-8", errors="ignore")
                return {"error": f"HTTP {e.code}", "detail": raw}
        except Exception as e:
            return {"error": str(e)}

    # --- Ferramentas Específicas para Agentes de IA ---

    def get_cnpj(self, cnpj: str) -> Dict[str, Any]:
        """Consulta cadastral completa e situação na Receita Federal por CNPJ."""
        return self.query("/api/v1/cnpj", {"cnpj": cnpj})

    def get_forex(self) -> Dict[str, Any]:
        """Cotações em tempo real de USD, EUR e GBP para BRL com spread e máximas/mínimas."""
        return self.query("/api/v1/forex")

    def get_crypto(self) -> Dict[str, Any]:
        """Preços spot de BTC, ETH, SOL, USDC e BASE em USD e BRL."""
        return self.query("/api/v1/crypto")

    def get_market_snapshot(self) -> Dict[str, Any]:
        """Snapshot financeiro unificado: Câmbio + Cripto + Selic + CDI + IPCA."""
        return self.query("/api/v1/market-snapshot")

    def check_compliance(self, cnpj: str) -> Dict[str, Any]:
        """Dossiê de risco corporativo cruzando CEIS, CNEP e Receita Federal."""
        return self.query("/api/v1/compliance", {"cnpj": cnpj})


# --- Exemplo de Wrapper para LangChain Core Tools ---
try:
    from langchain_core.tools import tool

    client = Grid402Client(use_sandbox=True)

    @tool
    def grid402_cnpj_lookup(cnpj: str) -> str:
        """Consulta razão social, CNAE e situação cadastral oficial de uma empresa brasileira pelo CNPJ."""
        data = client.get_cnpj(cnpj)
        return json.dumps(data, ensure_ascii=False)

    @tool
    def grid402_forex_rates() -> str:
        """Retorna taxas de câmbio comercial atualizadas (USD, EUR, GBP para BRL)."""
        data = client.get_forex()
        return json.dumps(data, ensure_ascii=False)

except ImportError:
    pass


if __name__ == "__main__":
    print("==================================================")
    print("  Grid402 // LangChain / CrewAI Quickstart Client ")
    print("==================================================")

    # Inicializa em modo Sandbox gratuito
    agent_client = Grid402Client(use_sandbox=True)

    # 1. Consulta Câmbio
    print("\n[1] Consultando Câmbio Comercial...")
    forex = agent_client.get_forex()
    if forex.get("status") == 402:
        print("⚡ [x402] Pagamento exigido:", forex.get("price_usdc"), "USDC")
    elif "error" in forex:
        print("⚠️ [Aviso]:", forex.get("error"))
        try:
            detail = json.loads(forex.get("detail", "{}"))
            print("   Motivo:", detail.get("message"))
        except Exception:
            print("   Detalhe:", forex.get("detail"))
    else:
        print("USD/BRL:", forex.get("rates", {}).get("USD", {}).get("bid"))
        print("Origem dos dados:", forex.get("_meta", {}).get("source_authority"))

    # 2. Consulta CNPJ (Exemplo: Banco do Brasil)
    print("\n[2] Consultando CNPJ (00.000.000/0001-91)...")
    cnpj_info = agent_client.get_cnpj("00000000000191")
    if cnpj_info.get("status") == 402:
        print("⚡ [x402] Cota gratuita atingida. Desafio de micropagamento recebido com sucesso:")
        print(f"   Preço: {cnpj_info.get('price_usdc')} USDC na Base ({cnpj_info.get('network')})")
        print(f"   Carteira de Destino: {cnpj_info.get('recipient_wallet')}")
        print(f"   Prévia dos Dados (Teaser): {cnpj_info.get('teaser_data')}")
    elif "error" in cnpj_info:
        print("⚠️ [Aviso]:", cnpj_info.get("error"))
        try:
            detail = json.loads(cnpj_info.get("detail", "{}"))
            print("   Motivo:", detail.get("message"))
        except Exception:
            print("   Detalhe:", cnpj_info.get("detail"))
    else:
        print("Razão Social:", cnpj_info.get("razao_social"))
        print("Situação:", cnpj_info.get("situacao_cadastral"))
        print("Prova Criptográfica de Oráculo:", bool(cnpj_info.get("_oracle_proof")))
