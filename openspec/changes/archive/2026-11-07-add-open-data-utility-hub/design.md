# Design Técnico: Grid402 // Hub de Utilitários de Rede & Dados Públicos

## 1. Arquitetura do Sistema

O sistema preserva a arquitetura **Zero-DB** (sem MySQL, sem SQLite) hospedada no LiteSpeed com PHP 8.3 na Hostinger, dividindo as 6 ferramentas em duas modalidades de resolução:

```
[ Agentes de IA / Robôs ] ── HTTP GET ──> [ LiteSpeed / index.php ]
                                                   │
                   ┌───────────────────────────────┴───────────────────────────────┐
                   ▼                                                               ▼
        [ Utilitários sob Demanda ]                                    [ Dados Periódicos de Mercado ]
          - DNS Lookup (dns_get_record)                                  - Câmbio (USD/EUR/BRL/GBP)
          - SSL Inspector (stream_socket_client)                         - Cripto (BTC/ETH/SOL/USDC)
          - ASN & IP Info (API pública de roteamento)                            │
          - CEP & IBGE (Base pública aberta)                                     ▼
                   │                                                   [ collector.php (Cron) ]
                   │                                                             │
                   ▼                                                             ▼
            [ Cache TTL Local ] <────────────────────────────────────── [ private_data/*.json ]
```

### 1.1. Utilitários Periódicos (Background Cron via `collector.php`):
- **Câmbio & Moedas:** Atualizado a cada 2 minutos consultando a API pública aberta do Banco Central / AwesomeAPI e persistido em `private_data/forex_cache.json`.
- **Cripto Tickers:** Atualizado a cada 2 minutos consultando tickers públicos de mercado spot e persistido em `private_data/crypto_cache.json`.

### 1.2. Utilitários Resolvidos On-Demand (Tempo Real com Cache de Curto Prazo):
- **DNS Records:** Executado via função nativa `dns_get_record()` do PHP com fallback para `1.1.1.1` e cache de 60s em disco/memória.
- **SSL Inspector:** Abre conexão TLS pontual na porta 443 via `stream_socket_client('ssl://host:443', ...)` capturando o contexto do certificado X.509 (`stream_context_get_params`), calculando data de validade, emissor e dias restantes.
- **ASN & IP Info:** Identifica dados do bloco IP e ASN BGP através de serviços abertos de roteamento.
- **CEP & IBGE:** Consulta base pública nacional aberta com sanitização de entrada e normalização.

---

## 2. Reforço de Segurança: Trava Atômica Anti-Replay (`flock`)

Para mitigar a vulnerabilidade de condição de corrida (*TOCTOU - Time-of-Check to Time-of-Use*), a verificação e reserva do `tx_hash` no `x402.php` passa a adotar trava exclusiva de escrita no descritor de arquivo:

```php
$fp = fopen(GRID402_FILE_USED_TXS, 'c+');
if (flock($fp, LOCK_EX)) {
    // Leitura atômica
    $data = stream_get_contents($fp);
    $txs = $data ? json_decode($data, true) : [];
    
    if (isset($txs[$txHash])) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return ['valid' => false, 'code' => 403, 'reason' => 'ReplayAttackDetected'];
    }
    
    // Reserva temporária antes da validação RPC
    $txs[$txHash] = time();
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($txs));
    flock($fp, LOCK_UN);
}
fclose($fp);
```

---

## 3. Contratos de API (Endpoints & Schemas)

### 3.1. Rota Gratuita: Resumo Geral
- **URL:** `GET /status` ou `GET /api/v1/summary`
- **Cabeçalho:** `Accept: application/json`
- **Retorno (200 OK):**
```json
{
  "hub": "Grid402",
  "version": "2.0.0",
  "status": "OPERATIONAL",
  "timestamp": "2026-11-07T18:30:00Z",
  "services": {
    "forex": { "status": "UP", "usd_brl": 5.421, "eur_brl": 5.892 },
    "crypto": { "status": "UP", "btc_usd": 68450.0, "eth_usd": 3210.5 },
    "dns": { "status": "UP" },
    "ssl": { "status": "UP" },
    "asn": { "status": "UP" },
    "cep": { "status": "UP" }
  }
}
```

### 3.2. Rotas Técnicas Profundas (Protegidas por x402)
- **Câmbio Detalhado:** `GET /api/v1/forex`
- **Cripto Detalhado:** `GET /api/v1/crypto`
- **DNS Lookup:** `GET /api/v1/dns?domain=github.com`
- **SSL Inspector:** `GET /api/v1/ssl?host=base.org`
- **ASN / IP Info:** `GET /api/v1/asn?ip=8.8.8.8`
- **CEP & IBGE:** `GET /api/v1/cep?code=01310100`

Se requisitadas sem `X-Payment`, o servidor retorna `HTTP 402` informando valor de `0.0005 USDC` e a carteira `0xa31c31069c43ac1c0075a93c325b0dcd7744356c` na Base. Se requisitadas com `X-Payment: <tx_hash>`, valida on-chain e entrega o JSON completo.

