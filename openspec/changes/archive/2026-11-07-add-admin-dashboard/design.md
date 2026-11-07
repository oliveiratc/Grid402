# Design: Arquitetura do Painel de Monitoramento e Faturamento

## 1. Visão Geral
O painel administrativo do Grid402 provê métricas financeiras e operacionais protegidas por autenticação forte, mantendo estritamente a filosofia **Zero-DB** (sem banco de dados relacional ou dependências externas pesadas) e alta performance (< 2ms de sobrecarga na API).

## 2. Modelagem de Dados em Arquivos JSON (`private_data/`)

### 2.1. Credenciais Administrativas (`private_data/admin_auth.json`)
Armazenado no diretório seguro protegido por `.htaccess` (`Require all denied`):
```json
{
  "username": "admin",
  "password_hash": "$2y$12$...",
  "created_at": "2026-11-07T20:00:00Z",
  "last_login": "2026-11-07T20:15:00Z",
  "login_count": 5
}
```

### 2.2. Telemetria Consolidada (`private_data/metrics.json`)
Atualizado atomicamente a cada consulta recebida nas APIs públicas:
```json
{
  "version": "1.0.0",
  "updated_at": "2026-11-07T20:25:00Z",
  "total_requests": 1520,
  "total_paid_requests": 42,
  "total_revenue_usdc": 0.0210,
  "status_counts": {
    "200": 1450,
    "402": 65,
    "403": 5
  },
  "service_counts": {
    "forex": 480,
    "crypto": 410,
    "dns": 230,
    "ssl": 160,
    "asn": 110,
    "cep": 90,
    "summary": 40
  },
  "daily": {
    "2026-11-07": {
      "requests": 520,
      "paid": 15,
      "usdc": 0.0075
    }
  },
  "recent_events": [
    {
      "timestamp": "2026-11-07T20:25:00Z",
      "service": "dns",
      "status": 200,
      "is_paid": true,
      "price_usdc": "0.0005",
      "tx_hash": "0xabc...def",
      "ip_masked": "189.40.xxx.xxx"
    }
  ]
}
```
*Buffer circular:* O array `recent_events` retém no máximo as últimas 50 entradas (FIFO), impedindo crescimento contínuo do arquivo.

## 3. Segurança e Sessão
- **Sessão PHP:**
  - `session_name('GRID402_ADMIN_SESS')`
  - Cookie flags: `HttpOnly`, `SameSite=Lax`, `Secure` (quando HTTPS ativo).
  - Regeneração do ID de sessão (`session_regenerate_id(true)`) no login.
  - Proteção contra força bruta: delay proposital de 500ms em tentativas com senha incorreta.
- **Anonimização de IP (LGPD):**
  - Função `grid402_mask_ip($ip)` que mascara o último octeto de endereços IPv4 (`192.168.1.xxx`) e o final de IPv6 (`2001:db8:xxxx`).
  - Nenhum dado pessoal identificável (PII) é associado aos registros.

## 4. Atualização em Tempo Real (Polling Leve)
- O front-end do painel realiza polling a cada 5 segundos na rota interna `/admin?api=stats`.
- Essa rota verifica se a sessão é válida e responde em JSON o snapshot de métricas consolidado em menos de 5ms.
- O DOM é atualizado cirurgicamente (sem reflow de página).

