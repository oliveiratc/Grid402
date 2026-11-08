"""
Testes de integracao com a API publica do Grid402 em modo Sandbox gratuito.
"""

import unittest

from grid402 import Grid402Client


class TestLiveSandboxGrid402(unittest.TestCase):
    """Executa chamadas reais no endpoint de producao utilizando modo Sandbox gratuito."""

    @classmethod
    def setUpClass(cls) -> None:
        # Usa o endpoint oficial da Hostinger para testes ponta a ponta
        cls.client = Grid402Client(
            base_url="https://orangered-jackal-900744.hostingersite.com",
            sandbox=True,
            timeout=15,
        )

    def test_live_summary(self) -> None:
        """Verifica se a rota /status responde 200 com status operacional."""
        res = self.client.get_summary()
        self.assertEqual(res.get("status"), "OPERATIONAL")
        self.assertIn("services", res)
        self.assertEqual(res.get("protocol"), "x402")

    def test_live_forex(self) -> None:
        """Verifica cotacoes cambiais reais em modo sandbox."""
        res = self.client.get_forex()
        self.assertEqual(res.get("status"), "UP")
        self.assertIn("rates", res)
        self.assertIn("USD", res["rates"])
        self.assertTrue(res.get("sandbox", False))

    def test_live_crypto(self) -> None:
        """Verifica precos de criptoativos em tempo real."""
        res = self.client.get_crypto()
        self.assertEqual(res.get("status"), "UP")
        self.assertIn("tickers", res)
        self.assertIn("BTC", res["tickers"])
        self.assertTrue(res.get("sandbox", False))

    def test_live_cep(self) -> None:
        """Verifica resolucao de CEP de logradouro real (Av. Paulista 01310-100)."""
        res = self.client.get_cep("01310100")
        self.assertTrue(res.get("success", False))
        self.assertEqual(res.get("uf"), "SP")
        self.assertTrue(res.get("sandbox", False))

    def test_live_cnpj(self) -> None:
        """Verifica consulta de CNPJ real (Banco do Brasil 00.000.000/0001-91)."""
        res = self.client.get_cnpj("00000000000191")
        self.assertTrue(res.get("success", False))
        self.assertIn("razao_social", res)
        self.assertTrue(res.get("sandbox", False))

    def test_live_indicators(self) -> None:
        """Verifica consulta de Indicadores Economicos oficiais (Selic, CDI e IPCA)."""
        res = self.client.get_indicators()
        self.assertEqual(res.get("status"), "success")
        self.assertIn("indicators", res)
        self.assertGreater(res["indicators"].get("selic_target", 0), 0)
        self.assertGreater(res["indicators"].get("cdi_annual", 0), 0)
        self.assertTrue(res.get("sandbox", False))

    def test_live_holidays(self) -> None:
        """Verifica consulta de Feriados Nacionais e Calendario Bancario de 2026."""
        res = self.client.get_holidays(year=2026)
        self.assertEqual(res.get("status"), "success")
        self.assertEqual(res.get("year"), 2026)
        self.assertGreaterEqual(res.get("total_holidays", 0), 14)
        self.assertTrue(res.get("sandbox", False))


if __name__ == "__main__":
    unittest.main()
