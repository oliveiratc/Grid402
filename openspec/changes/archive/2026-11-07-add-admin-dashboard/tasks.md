# Tarefas de Implementação: Painel de Monitoramento & Faturamento

## 1. Módulo de Telemetria e Métricas Zero-DB
- [x] 1.1 Criar `public_html/metrics.php` com `grid402_record_telemetry(...)` usando trava exclusiva `flock(LOCK_EX)` e buffer circular de 50 eventos
- [x] 1.2 Implementar em `public_html/metrics.php` a função `grid402_mask_ip(...)` para anonimização de endereços IPv4/IPv6
- [x] 1.3 Integrar o registro de telemetria no gateway central `public_html/index.php` para todas as rotas da API (gratuitas, pagas, desafios 402 e erros 403)

## 2. Autenticação Administrativa e Setup de Primeiro Acesso
- [x] 2.1 Implementar em `public_html/admin.php` a verificação de credenciais com `password_hash`/`password_verify` (Bcrypt)
- [x] 2.2 Criar fluxo assistido de primeiro acesso para cadastro inicial de usuário e senha quando `admin_auth.json` não existir
- [x] 2.3 Implementar gerenciamento de sessão segura com cookies `HttpOnly`, regeneração de ID no login e rota de logout

## 3. Interface do Dashboard Dark-Mode e Endpoint de Polling
- [x] 3.1 Construir a interface visual de login e de primeiro acesso com design system do Grid402
- [x] 3.2 Construir a interface do Dashboard com cards de Faturamento (USDC/BRL), volume por serviço e tabela de eventos recentes
- [x] 3.3 Implementar endpoint JSON autenticado `/admin?api=stats` e polling front-end a cada 5 segundos

## 4. Roteamento e Regras de Servidor
- [x] 4.1 Adicionar regras de rewrite amigável em `public_html/.htaccess` para rotas `/admin` e `/dashboard`
- [x] 4.2 Atualizar o roteador local `public_html/router.php` para rotear `/admin` perfeitamente em desenvolvimento

## 5. Bateria de Testes e Homologação
- [x] 5.1 Criar bateria de testes em `tests/test_admin_dashboard.php` cobrindo todos os cenários da especificação
- [x] 5.2 Executar suíte completa de testes locais e homologar 100% de aprovação
