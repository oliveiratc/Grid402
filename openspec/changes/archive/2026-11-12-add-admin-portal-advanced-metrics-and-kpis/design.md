# Design Técnico: Indicadores Avançados e Visualizações Analíticas no Portal Admin

## Arquitetura Zero-DB e Performance
O portal administrativo continua utilizando a filosofia **Zero-DB**:
1. Todas as agregações derivam de `metrics.json` e dos eventos recentes protegidos por `flock`.
2. Para evitar overhead de leitura e escrita, o histórico diário em `metrics.json` armazena no máximo 14 chaves no formato `YYYY-MM-DD`. Chaves anteriores são removidas de forma determinística na gravação ou consolidação.
3. As métricas adicionais (Ticket Médio, SLA, % Sandbox, Top IPs e Rentabilidade por Serviço) são calculadas in-memory em PHP durante a requisição de `/admin` ou `/admin?api=stats`, consumindo menos de 2ms de tempo de CPU.

## Gráfico Temporal SVG Nativo
- **Zero bibliotecas JS externas:** O SVG é gerado diretamente pelo navegador via JavaScript no front-end (ou pré-renderizado no HTML inicial), criando elementos `<rect>` ou caminhos `<path>` para o volume de requisições e para o faturamento em USDC.
- **Interatividade:** Cada barra do gráfico possui atributos `data-date`, `data-requests`, `data-paid` e `data-usdc`. Eventos `mouseover` / `touchstart` exibem um balão flutuante estilizado (tooltip) com os detalhes do dia.

## Agregações e Fórmulas Analíticas
1. **Ticket Médio (ARPU USDC):**
   $$\text{ARPU} = \frac{\text{total\_revenue\_usdc}}{\max(1, \text{total\_paid\_requests})}$$
2. **Taxa de Sucesso Técnico (SLA HTTP 200 %):**
   $$\text{SLA}_{200} = \frac{\text{status\_counts}['200']}{\max(1, \text{total\_requests})} \times 100$$
3. **Taxa de Adoção Sandbox (%):**
   $$\text{Sandbox}\% = \frac{\text{total\_sandbox\_requests}}{\max(1, \text{total\_requests})} \times 100$$
4. **Top Agentes/IPs Consumidores:**
   - Itera sobre os eventos do buffer circular (`recent_events`, até 100 eventos).
   - Agrupa por `ip_masked`.
   - Computa: total de consultas, quantidade paga, quantidade sandbox, quantidade de desafios 402 e serviços mais acessados.
   - Retorna os Top 5 a 10 ordenados por total de requisições.
5. **Rentabilidade Estimada por Micro-Serviço:**
   - Para cada um dos 19 nós, multiplica a contagem de requisições pagas (ou total proporcional tarifado) pelo preço do nó:
     - `notarize`: $0.0050 USDC
     - `compliance`: $0.0030 USDC
     - `ofac`: $0.0020 USDC
     - `forex`, `crypto`, `indicators`, etc.: $0.0005 USDC
     - `pass`: $2.00, $5.00, $20.00 USDC

## Contrato do Endpoint `/admin?api=stats`
O JSON retornado inclui:
```json
{
  "status": "success",
  "server_time": "2026-11-12T12:00:00Z",
  "total_requests": 140,
  "total_paid_requests": 2,
  "total_sandbox_requests": 8,
  "total_revenue_usdc": 0.001,
  "total_revenue_brl": 0.01,
  "today_revenue_usdc": 0.001,
  "today_revenue_brl": 0.01,
  "arpu_usdc": 0.0005,
  "technical_success_rate": 89.9,
  "sandbox_rate": 5.7,
  "wallet_balances": { ... },
  "daily_history_14d": [
    { "date": "2026-11-12", "requests": 140, "paid": 2, "usdc": 0.001 }
  ],
  "top_consumers": [
    { "ip_masked": "189.40.xxx.xxx", "total": 12, "paid": 2, "sandbox": 4, "challenges": 6 }
  ],
  "service_revenue": {
    "notarize": 0.005,
    "forex": 0.001
  }
}
```
