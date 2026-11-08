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


class HolidaysInput(BaseModel):
    """Parametros opcionais para consulta de feriados nacionais e bancarios."""
    year: Optional[int] = Field(
        None,
        description="Ano calendario de 4 digitos entre 1900 e 2100. Se omitido, utiliza o ano corrente.",
        ge=1900,
        le=2100,
        examples=[2026],
    )


class EconomicIndicators(BaseModel):
    selic_target: float = Field(..., description="Taxa Selic meta anual em percentual.")
    selic_daily: float = Field(..., description="Taxa Selic acumulada diaria anualizada.")
    cdi_annual: float = Field(..., description="Taxa CDI anualizada em percentual.")
    cdi_daily: float = Field(..., description="Taxa CDI diaria efetiva em percentual.")
    ipca_12m: float = Field(..., description="IPCA acumulado nos ultimos 12 meses.")
    ipca_monthly: float = Field(..., description="IPCA do ultimo mes fechado.")
    reference_date: str = Field(..., description="Data de referencia dos indicadores diarios.")
    ipca_reference_month: str = Field(..., description="Mes de referencia da divulgacao do IPCA.")


class IndicatorsResponse(BaseModel):
    status: str = Field(..., description="Status da consulta.")
    service: str = Field("indicators", description="Nome do micro-servico.")
    timestamp: str = Field(..., description="Data e hora ISO 8601 da resposta.")
    indicators: EconomicIndicators = Field(..., description="Conjunto de indicadores economicos.")
    source: str = Field(..., description="Fontes oficiais primarias.")
    cached: Optional[bool] = Field(None, description="Indica se os dados foram servidos do cache.")


class HolidayItem(BaseModel):
    date: str = Field(..., description="Data do feriado no formato AAAA-MM-DD.")
    name: str = Field(..., description="Nome oficial do feriado ou celebracao.")
    type: str = Field(..., description="Tipo do feriado: national, banking ou religious.")
    day_of_week: str = Field(..., description="Dia da semana correspondente em portugues.")
    is_banking_holiday: bool = Field(..., description="Indica se nao ha expediente bancario ao publico.")


class HolidaysResponse(BaseModel):
    status: str = Field(..., description="Status da consulta.")
    service: str = Field("holidays", description="Nome do micro-servico.")
    year: int = Field(..., description="Ano calendario consultado.")
    total_holidays: int = Field(..., description="Contagem total de feriados cadastrados no ano.")
    holidays: list[HolidayItem] = Field(..., description="Lista de feriados ordenada cronologicamente.")
    source: str = Field(..., description="Fontes oficiais primarias.")
    cached: Optional[bool] = Field(None, description="Indica se os dados foram servidos do cache.")


class WorkdaysInput(BaseModel):
    """Parametros para calculo de dias uteis financeiros e projecao ANBIMA."""
    start_date: Optional[str] = Field(
        None,
        description="Data de inicio no formato AAAA-MM-DD para contagem ou projecao.",
        examples=["2026-11-06"],
    )
    end_date: Optional[str] = Field(
        None,
        description="Data de termino no formato AAAA-MM-DD para contagem de intervalo.",
        examples=["2026-11-20"],
    )
    days: Optional[int] = Field(
        None,
        description="Quantidade inteira de dias uteis para projecao de prazo (positivo ou negativo).",
        examples=[5],
    )
    date: Optional[str] = Field(
        None,
        description="Data pontual no formato AAAA-MM-DD para checagem se e dia util.",
        examples=["2026-12-25"],
    )


class WorkdaysHolidayItem(BaseModel):
    date: str = Field(..., description="Data do feriado no formato AAAA-MM-DD.")
    name: str = Field(..., description="Nome oficial do feriado bancario.")


class WorkdaysResponse(BaseModel):
    status: str = Field(..., description="Status da consulta.")
    service: str = Field("workdays", description="Nome do micro-servico.")
    mode: str = Field(..., description="Modo executado: range, projection ou check.")
    convention: str = Field(..., description="Convencao financeira adotada.")
    start_date: Optional[str] = Field(None, description="Data de inicio considerada.")
    end_date: Optional[str] = Field(None, description="Data de termino considerada.")
    target_date: Optional[str] = Field(None, description="Data final calculada na projecao de dias uteis.")
    workdays: Optional[int] = Field(None, description="Total de dias uteis calculados.")
    calendar_days: Optional[int] = Field(None, description="Total de dias corridos no intervalo.")
    start_is_workday: Optional[bool] = Field(None, description="Indica se a data inicial e dia util.")
    end_is_workday: Optional[bool] = Field(None, description="Indica se a data final e dia util.")
    target_is_workday: Optional[bool] = Field(None, description="Indica se a data de destino projetada e dia util.")
    is_workday: Optional[bool] = Field(None, description="Indica se a data pontual e dia util.")
    day_type: Optional[str] = Field(None, description="Tipo de dia: workday, weekend ou holiday.")
    description: Optional[str] = Field(None, description="Descricao do status da data consultada.")
    holiday_name: Optional[str] = Field(None, description="Nome do feriado bancario, se aplicavel.")
    next_workday: Optional[str] = Field(None, description="Proximo dia util bancario seguinte.")
    holidays_in_range: Optional[list[WorkdaysHolidayItem]] = Field(None, description="Feriados bancarios ocorridos no intervalo.")
    holidays_in_projection: Optional[list[WorkdaysHolidayItem]] = Field(None, description="Feriados bancarios ocorridos durante a projecao.")
    tariff_usdc: Optional[str] = Field(None, description="Tarifa cobrada em USDC.")
    cached: Optional[bool] = Field(None, description="Indica se os dados foram servidos do cache.")
    sandbox: Optional[bool] = Field(None, description="Indica se a requisicao foi executada em modo sandbox.")
    error: Optional[str] = Field(None, description="Mensagem de erro caso a operacao falhe.")

    def __getitem__(self, item: str) -> Any:
        return getattr(self, item)

    def get(self, item: str, default: Any = None) -> Any:
        return getattr(self, item, default)


class SettlementInfo(BaseModel):
    """Informacoes de liquidacao on-chain de micropagamento x402."""
    settled: bool = Field(True, description="Indica se o pagamento foi liquidado com sucesso.")
    tx_hash: str = Field(..., description="Hash da transacao confirmada na rede Base.")
    price_usdc: str = Field(..., description="Valor pago em USDC.")
    network: str = Field("Base", description="Rede blockchain da liquidacao.")
    token: str = Field("USDC", description="Token utilizado no pagamento.")

