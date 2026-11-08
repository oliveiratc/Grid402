"""
Suite de testes automatizados para o SDK Grid402.
"""

from typing import Any, Dict
import unittest
from unittest.mock import MagicMock, patch

from grid402 import (
    AsnInput,
    CepInput,
    CnpjInput,
    DnsInput,
    Grid402ActionProvider,
    Grid402Client,
    Grid402Error,
    Grid402PaymentRequiredError,
    HolidayItem,
    HolidaysInput,
    HolidaysResponse,
    IndicatorsResponse,
    SslInput,
    __version__,
)


class TestGrid402SDK(unittest.TestCase):
    """Testes de integridade de modelos, cliente e provedor de acoes do Grid402."""

    def test_version_and_exports(self) -> None:
        """Verifica versao semantica e exportacoes principais."""
        self.assertEqual(__version__, "2.2.0")
        self.assertTrue(issubclass(Grid402PaymentRequiredError, Grid402Error))

    def test_pydantic_models(self) -> None:
        """Valida schemas Pydantic de entrada."""
        cnpj = CnpjInput(cnpj="00000000000191")
        self.assertEqual(cnpj.cnpj, "00000000000191")

        cep = CepInput(code="01310100")
        self.assertEqual(cep.code, "01310100")

        dns = DnsInput(domain="base.org")
        self.assertEqual(dns.domain, "base.org")

        ssl_model = SslInput(host="google.com")
        self.assertEqual(ssl_model.host, "google.com")

        asn_model = AsnInput(ip="1.1.1.1")
        self.assertEqual(asn_model.ip, "1.1.1.1")

        holidays_input = HolidaysInput(year=2026)
        self.assertEqual(holidays_input.year, 2026)

        ind_resp = IndicatorsResponse(
            status="success",
            service="indicators",
            timestamp="2026-11-08T11:50:40Z",
            indicators={
                "selic_target": 13.75,
                "selic_daily": 13.65,
                "cdi_annual": 13.65,
                "cdi_daily": 0.0508,
                "ipca_12m": 4.58,
                "ipca_monthly": 0.44,
                "reference_date": "2026-11-08",
                "ipca_reference_month": "2026-10",
            },
            source="Banco Central do Brasil e IBGE",
        )
        self.assertEqual(ind_resp.indicators.selic_target, 13.75)

        hol_resp = HolidaysResponse(
            status="success",
            service="holidays",
            year=2026,
            total_holidays=1,
            holidays=[
                HolidayItem(
                    date="2026-01-01",
                    name="Confraternizacao Universal",
                    type="national",
                    day_of_week="Quinta-feira",
                    is_banking_holiday=True,
                )
            ],
            source="Calendario Nacional e ANBIMA / Febraban",
        )
        self.assertEqual(len(hol_resp.holidays), 1)
        self.assertTrue(hol_resp.holidays[0].is_banking_holiday)

    def test_client_sandbox_headers(self) -> None:
        """Verifica se o modo Sandbox envia o cabecalho de demonstracao."""
        client = Grid402Client(sandbox=True)
        self.assertTrue(client.sandbox)

        # Mock de urllib.request.urlopen para inspecionar cabecalhos sem bater na rede
        mock_response = MagicMock()
        mock_response.read.return_value = b'{"status": "sandbox_ok"}'
        mock_response.__enter__.return_value = mock_response

        with patch("urllib.request.urlopen", return_value=mock_response) as mock_urlopen:
            res = client.get_cnpj("00000000000191")
            self.assertEqual(res, {"status": "sandbox_ok"})

            req_arg = mock_urlopen.call_args[0][0]
            self.assertEqual(req_arg.headers.get("X-payment"), "demo")

    def test_client_raise_for_payment(self) -> None:
        """Verifica lancamento de Grid402PaymentRequiredError quando raise_for_payment=True."""
        client = Grid402Client(sandbox=False, raise_for_payment=True)

        challenge_body = (
            b'{"status": 402, "message": "Payment Required", "price_usdc": "0.0005", '
            b'"recipient_wallet": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c", '
            b'"preview_data": {"sample": true}}'
        )

        import urllib.error
        http_error = urllib.error.HTTPError(
            url="https://grid402.com.br/api/v1/forex",
            code=402,
            msg="Payment Required",
            hdrs={},  # type: ignore
            fp=MagicMock(read=MagicMock(return_value=challenge_body)),
        )

        with patch("urllib.request.urlopen", side_effect=http_error):
            with self.assertRaises(Grid402PaymentRequiredError) as ctx:
                client.get_forex()

            err = ctx.exception
            self.assertEqual(err.price_usdc, "0.0005")
            self.assertEqual(err.recipient, "0xa31c31069c43ac1c0075a93c325b0dcd7744356c")
            self.assertIsNotNone(err.preview_data)
            self.assertTrue(err.preview_data.get("sample"))

    def test_agentkit_auto_settlement(self) -> None:
        """Simula auto-liquidacao on-chain com carteira CDP e wallet_provider."""
        provider = Grid402ActionProvider(sandbox=False)

        mock_wallet = MagicMock()
        mock_transfer = MagicMock()
        mock_transfer.transaction_hash = "0xmocked_tx_hash_12345"
        mock_wallet.transfer.return_value = mock_transfer

        challenge_bytes = (
            b'{"price_usdc": "0.0020", '
            b'"recipient_wallet": "0xa31c31069c43ac1c0075a93c325b0dcd7744356c"}'
        )
        success_bytes = b'{"razao_social": "BANCO DO BRASIL SA", "cnpj": "00000000000191"}'

        import urllib.error
        challenge_error = urllib.error.HTTPError(
            url="https://grid402.com.br/api/v1/cnpj",
            code=402,
            msg="Payment Required",
            hdrs={},  # type: ignore
            fp=MagicMock(read=MagicMock(return_value=challenge_bytes)),
        )

        success_response = MagicMock()
        success_response.read.return_value = success_bytes
        success_response.__enter__.return_value = success_response

        # Primeira chamada lanca 402, segunda chamada (apos pagamento) retorna sucesso
        with patch("urllib.request.urlopen", side_effect=[challenge_error, success_response]):
            res = provider.query_cnpj("00000000000191", wallet_provider=mock_wallet)

            mock_wallet.transfer.assert_called_once_with(
                to="0xa31c31069c43ac1c0075a93c325b0dcd7744356c",
                amount="0.0020",
                asset_id="usdc",
            )
            self.assertEqual(res.get("razao_social"), "BANCO DO BRASIL SA")
            self.assertTrue(res.get("_x402_settlement", {}).get("settled"))
            self.assertEqual(res.get("_x402_settlement", {}).get("tx_hash"), "0xmocked_tx_hash_12345")

    def test_client_indicators_and_holidays(self) -> None:
        """Verifica chamadas a get_indicators e get_holidays no Grid402Client."""
        client = Grid402Client(sandbox=True)

        mock_resp_indicators = MagicMock()
        mock_resp_indicators.read.return_value = b'{"status": "success", "service": "indicators"}'
        mock_resp_indicators.__enter__.return_value = mock_resp_indicators

        mock_resp_holidays = MagicMock()
        mock_resp_holidays.read.return_value = b'{"status": "success", "service": "holidays", "year": 2026}'
        mock_resp_holidays.__enter__.return_value = mock_resp_holidays

        with patch("urllib.request.urlopen", side_effect=[mock_resp_indicators, mock_resp_holidays]) as mock_urlopen:
            res_ind = client.get_indicators()
            self.assertEqual(res_ind.get("service"), "indicators")

            res_hol = client.get_holidays(year=2026)
            self.assertEqual(res_hol.get("service"), "holidays")

            calls = mock_urlopen.call_args_list
            self.assertIn("/api/v1/indicators", calls[0][0][0].full_url)
            self.assertIn("/api/v1/holidays?year=2026", calls[1][0][0].full_url)


if __name__ == "__main__":
    unittest.main()

