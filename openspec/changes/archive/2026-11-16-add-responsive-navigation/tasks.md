# Tasks: Navegação Responsiva Unificada (Site Público + Portal Admin)

## 1. Partials Compartilhados (Público e Admin)
- [x] 1.1 Criar `public_html/includes/nav.php` com `grid402_render_public_nav()`, CSS isolado (`.g402-nav-*`), breakpoint `<= 768px`, botão hambúrguer acessível (`44x44px`, `aria-expanded`, `aria-controls`, `Escape`, clique fora) e suporte opcional a `PT | EN`
- [x] 1.2 Criar `public_html/includes/admin_nav.php` com `grid402_render_admin_nav()`, CSS isolado (`.g402-anav-*`), breakpoint `<= 860px`, links de Visão Geral, Telemetria, Passes / MP, Growth (`/growth_admin`), Voltar ao Site (`/`) e Sair

## 2. Integração nas Páginas Públicas
- [x] 2.1 Integrar `grid402_render_public_nav()` em `public_html/index.php` (Home + `grid402_render_page_html()` das páginas institucionais `/about`, `/contact`, `/terms`, `/privacy`)
- [x] 2.2 Integrar `grid402_render_public_nav()` em `public_html/pass.php`, `public_html/catalog.php`, `public_html/agents.php` e `public_html/trust.php`
- [x] 2.3 Integrar `grid402_render_public_nav()` em `public_html/growth_program.php`, `public_html/builder_reputation.php`, `public_html/agenthub.php` e `public_html/payment_dashboard.php`

## 3. Integração no Portal Administrativo
- [x] 3.1 Integrar `grid402_render_admin_nav()` no dashboard autenticado de `public_html/admin.php` (com âncoras `#overview`, `#telemetry`, `#passes`) e em `public_html/growth_admin.php`

## 4. Testes e Verificação
- [x] 4.1 Criar e executar `tests/test_responsive_navigation.php` validando todos os cenários da spec e lint PHP em todos os arquivos modificados
- [x] 4.2 Validar que `tests/test_admin_dashboard.php` e `tests/test_mercadopago_checkout.php` continuam 100% aprovados
