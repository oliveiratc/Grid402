# Catálogo Oficial dos 22 Micro-Serviços, Preços, Fontes Upstream e Ferramentas MCP

Unidade de Crédito Session Pass: **`1 crédito = 0.0050 USDC` (`5.000 raw units`)**

| # | ID do Serviço | Rota HTTP | Ferramenta MCP | Preço USDC | Créditos | Fonte Oficial Upstream (`upstream_source`) | Cache TTL |
|---|---|---|---|---|---|---|---|
| - | `summary` | `GET /status` (`/api/v1/summary`) | `grid402_summary` | Gratuito | 0 | Grid402 Internal Collector Telemetry | 60s |
| 1 | `forex` | `GET /api/v1/forex` | `grid402_forex` | `0.0050` | 1 | Banco Central do Brasil (PTAX) / AwesomeAPI FX Feed | 60s |
| 2 | `crypto` | `GET /api/v1/crypto` | `grid402_crypto` | `0.0050` | 1 | CoinGecko / Binance Spot Price Aggregator | 60s |
| 3 | `dns` | `GET /api/v1/dns?domain=...` | `grid402_dns` | `0.0050` | 1 | Authoritative DNS Root/TLD Resolvers (RFC 1035) | 300s |
| 4 | `ssl` | `GET /api/v1/ssl?host=...` | `grid402_ssl` | `0.0050` | 1 | Direct Port 443 TLS/X.509 Handshake Inspector (RFC 5280) | 300s |
| 5 | `asn` | `GET /api/v1/asn?ip=...` | `grid402_asn` | `0.0050` | 1 | RIPE Stat / BGP Routing Registry (RDAP / RFC 4271) | 3600s |
| 6 | `domain-audit` | `GET /api/v1/domain-audit?domain=...` | `grid402_domain_audit` | `0.0150` | 3 | Grid402 Composite Probe (DNS + TLS/X.509 + BGP/ASN) | 300s |
| 7 | `kyb-audit` | `GET /api/v1/kyb-audit?cnpj=...` | `grid402_kyb_audit` | `0.0200` | 4 | Receita Federal do Brasil + Simples Nacional + CGU (CEIS/CNEP) | 21600s (6h) |
| 8 | `market-snapshot` | `GET /api/v1/market-snapshot` | `grid402_market_snapshot` | `0.0150` | 3 | Banco Central do Brasil (SGS/PTAX) + IBGE + ANBIMA + Crypto Spot | 60s |
| 9 | `cep` | `GET /api/v1/cep?code=...` | `grid402_cep` | `0.0050` | 1 | BrasilAPI / ViaCEP / Diretório Nacional de Endereços + IBGE | 86400s (24h) |
| 10 | `cnpj` | `GET /api/v1/cnpj?cnpj=...` | `grid402_cnpj` | `0.0200` | 4 | Receita Federal do Brasil (Base Pública CNPJ via BrasilAPI/ReceitaWS) | 21600s (6h) |
| 11 | `indicators` | `GET /api/v1/indicators` | `grid402_indicators` | `0.0050` | 1 | Banco Central do Brasil (SGS Séries 432, 11, 12) + IBGE SIDRA (IPCA) | 3600s (1h) |
| 12 | `holidays` | `GET /api/v1/holidays?year=...` | `grid402_holidays` | `0.0050` | 1 | Calendário Oficial ANBIMA / Febraban + Algoritmo Astronômico de Meeus | 86400s (24h) |
| 13 | `workdays` | `GET /api/v1/workdays` | `grid402_workdays` | `0.0050` | 1 | Motor Determinístico ANBIMA / Febraban (Convenção D+0 Bancária) | 3600s (1h) |
| 14 | `sanctions` | `GET /api/v1/sanctions?cnpj=...` | `grid402_sanctions` | `0.0100` | 2 | Controladoria-Geral da União (CGU - Portal da Transparência CEIS/CNEP) | 21600s (6h) |
| 15 | `simples` | `GET /api/v1/simples?cnpj=...` | `grid402_simples` | `0.0100` | 2 | Receita Federal do Brasil ( Portal do Simples Nacional / SIMEI) | 21600s (6h) |
| 16 | `compliance` | `GET /api/v1/compliance?cnpj=...` | `grid402_compliance` | `0.0300` | 6 | Motor Público Grid402 (Receita Federal CNPJ + Simples + CGU CEIS/CNEP) | 21600s (6h) |
| 17 | `lei` | `GET /api/v1/lei?lei=...` | `grid402_lei` | `0.0200` | 4 | Global Legal Entity Identifier Foundation (GLEIF API v1 - CC0 1.0) | 86400s (24h) |
| 18 | `ofac` | `GET /api/v1/ofac?target=...` | `grid402_ofac` | `0.0200` | 4 | US Treasury OFAC SDN + UN Consolidated Sanctions + Base Mainnet RPC | 21600s (6h) |
| 19 | `notarize` | `GET /api/v1/notarize?content=...` | `grid402_notarize` | `0.0050` | 1 | Grid402 Cryptographic Time-Stamp Oracle (ECDSA secp256k1 + SHA-256) | 0s (Real-time) |
| 20 | `global-markets` | `GET /api/v1/global-markets` | `grid402_global_markets` | `0.0050` | 1 | European Central Bank (ECB EuroFXRef) + NYSE/TARGET2/LSE/B3 Calendars | 3600s (1h) |
| 21 | `pass` | `GET /api/v1/pass` | `grid402_pass` | `2 / 5 / 20` | 400 / 1k / 6k | Grid402 Prepaid Session Engine (Base Mainnet USDC / Pix) | 0s (Real-time) |
| 22 | `verify` | `POST /api/v1/verify` | `grid402_verify` | Gratuito | 0 | Grid402 Deterministic ECDSA secp256k1 Verifier | 0s (Stateless) |
| - | `pass-balance` | `GET /api/v1/pass/balance` | `grid402_pass_balance` | Gratuito | 0 | Grid402 Prepaid Session Engine (Spend Controls & Balance Telemetry) | 0s (Real-time) |
