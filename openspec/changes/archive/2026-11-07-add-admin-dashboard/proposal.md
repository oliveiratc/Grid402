# Proposal: Painel de Monitoramento em Tempo Real e Faturamento das APIs (Admin Dashboard)

## Why
O Grid402 opera como um hub autônomo M2M processando consultas públicas e micropagamentos via protocolo x402 na Base Mainnet. Atualmente, o operador do sistema não possui uma interface visual e privativa para acompanhar o volume de consultas, a taxa de conversão dos desafios HTTP 402, os serviços mais demandados e o faturamento acumulado em USDC e Reais.
Esta entrega resolve essa lacuna adicionando um painel administrativo seguro em `/admin`, protegido por credenciais criptografadas e com atualização em tempo real, sem violar as regras de Zero-DB e LGPD (conforme PRD em `openspec/prds/grid402-admin-dashboard-monitoramento.md`).

## What Changes
- **Rota `/admin` e `/dashboard`:** Interface web dark-mode responsiva com dashboard analítico.
- **Autenticação Segura e Assistente de Primeiro Acesso:**
  - Se for o primeiro acesso, exibe assistente para criação de usuário e senha de administrador com hash seguro (`PASSWORD_BCRYPT`).
  - Sessão PHP protegida (`session_start` com `HttpOnly`, `SameSite=Lax`, regeneração de ID contra session fixation).
  - Rota de logout seguro.
- **Gravação Atômica de Telemetria Interna (`metrics.json`):**
  - Registro de volume por endpoint (`forex`, `crypto`, `dns`, `ssl`, `asn`, `cep`, `summary`).
  - Contagem de códigos HTTP (200, 402, 403).
  - Buffer circular das últimas 50 consultas com IP anonimizado (`189.40.xxx.xxx`) e link de transação BaseScan.
- **Cálculo de Faturamento:**
  - Total de USDC recebido e conversão em BRL com base na cotação USD/BRL em tempo real.
  - Faturamento do dia atual e dos últimos 7 dias.
- **Atualização em Tempo Real (Polling Leve):**
  - Endpoint `/admin?api=stats` consumido pelo front-end a cada 5 segundos para atualização dos cards e feed sem recarregar a página.

## Impact
- Specs afetadas: `admin-dashboard` (nova spec de domínio)
- Código afetado:
  - `public_html/admin.php` (novo controlador e view do painel administrativo)
  - `public_html/index.php` (interceptação de telemetria e gravação em `metrics.json`)
  - `public_html/.htaccess` (rewrite amigável para `/admin` e `/dashboard`)
  - `public_html/router.php` (roteamento local)
  - `tests/test_admin_dashboard.php` (novo teste de autenticação, métricas e segurança)

