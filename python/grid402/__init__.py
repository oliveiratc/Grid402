"""
Grid402 Python SDK - Biblioteca oficial para a Internet Agente e Micropagamentos HTTP 402.

Suporte para Python 3.9+, Coinbase Developer Platform (CDP) AgentKit,
LangChain, CrewAI e modo Sandbox gratuito.
"""

from grid402.agentkit import Grid402ActionProvider
from grid402.client import (
    DEFAULT_ENDPOINT,
    DEFAULT_ORACLE_PUBKEY,
    GRID402_ORACLE_WALLET,
    Grid402Client,
    canonicalize_payload,
    compute_data_hash,
    verify_oracle_proof,
)
from grid402.exceptions import Grid402Error, Grid402PaymentRequiredError
from grid402.models import (
    AsnInput,
    CepInput,
    CnpjInput,
    CryptoInput,
    DnsInput,
    EconomicIndicators,
    HolidayItem,
    HolidaysInput,
    HolidaysResponse,
    IndicatorsResponse,
    OracleProof,
    OracleVerificationResult,
    SettlementInfo,
    SslInput,
    WorkdaysHolidayItem,
    WorkdaysInput,
    WorkdaysResponse,
)

__version__ = "2.4.0"
__author__ = "Grid402 Team"
__license__ = "MIT"

__all__ = [
    "Grid402Client",
    "Grid402ActionProvider",
    "Grid402Error",
    "Grid402PaymentRequiredError",
    "CnpjInput",
    "CepInput",
    "DnsInput",
    "SslInput",
    "AsnInput",
    "CryptoInput",
    "HolidaysInput",
    "WorkdaysInput",
    "EconomicIndicators",
    "IndicatorsResponse",
    "HolidayItem",
    "HolidaysResponse",
    "WorkdaysHolidayItem",
    "WorkdaysResponse",
    "SettlementInfo",
    "OracleProof",
    "OracleVerificationResult",
    "verify_oracle_proof",
    "canonicalize_payload",
    "compute_data_hash",
    "DEFAULT_ORACLE_PUBKEY",
    "GRID402_ORACLE_WALLET",
    "DEFAULT_ENDPOINT",
    "__version__",
]

