"""
Modelos tipados Pydantic para entradas e saidas do Grid402.
"""

from typing import Any, Dict, Optional
from pydantic import BaseModel, Field


class CnpjInput(BaseModel):
    """Parametros para consulta cadastral de Pessoa Juridica na Receita Federal."""
    cnpj: str = Field(
        ...,
        description="Numero do CNPJ com 14 digitos numericos, com ou sem mascara de pontuacao.",
        examples=["00.000.000/0001-91", "00000000000191"],
    )


class CepInput(BaseModel):
    """Parametros para consulta de endereco postal e codigo IBGE por CEP."""
    code: str = Field(
        ...,
        description="Codigo de CEP brasileiro com 8 digitos numericos, com ou sem hifen.",
        examples=["01310-100", "01310100"],
    )


class DnsInput(BaseModel):
    """Parametros para resolucao DNS autoritativa."""
    domain: str = Field(
        ...,
        description="Nome de dominio da internet para resolucao de registros A, AAAA, MX, TXT, NS e CNAME.",
        examples=["base.org", "ethereum.org"],
    )


class SslInput(BaseModel):
    """Parametros para inspecao e auditoria de certificado TLS e SSL."""
    host: str = Field(
        ...,
        description="Hostname ou dominio para inspecao de certificado TLS na porta 443.",
        examples=["base.org", "google.com"],
    )


class AsnInput(BaseModel):
    """Parametros para consulta de BGP e numero ASN."""
    ip: Optional[str] = Field(
        None,
        description="Endereco IPv4 ou IPv6 a consultar. Se omitido, inspeciona o IP publico requisitante.",
        examples=["1.1.1.1", "8.8.8.8"],
    )


class CryptoInput(BaseModel):
    """Parametros opcionais para cotacao spot de criptoativos."""
    symbol: Optional[str] = Field(
        None,
        description="Simbolo opcional do criptoativo (ex: BTC, ETH, SOL, USDC, BASE).",
        examples=["BTC", "ETH"],
    )


class SettlementInfo(BaseModel):
    """Informacoes de liquidacao on-chain de micropagamento x402."""
    settled: bool = Field(True, description="Indica se o pagamento foi liquidado com sucesso.")
    tx_hash: str = Field(..., description="Hash da transacao confirmada na rede Base.")
    price_usdc: str = Field(..., description="Valor pago em USDC.")
    network: str = Field("Base", description="Rede blockchain da liquidacao.")
    token: str = Field("USDC", description="Token utilizado no pagamento.")

