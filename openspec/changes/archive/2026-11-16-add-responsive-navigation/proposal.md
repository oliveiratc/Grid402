# Adicionar Navegação Responsiva Unificada (Site Público + Portal Admin)

## Por quê
As páginas públicas e administrativas do Grid402 hoje utilizam cabeçalhos distintos, sem menu hambúrguer padronizado no mobile, causando quebra visual e navegação inconsistente entre `/`, `/pass`, `/catalog`, `/agents`, `/trust`, `/growth`, `/builder`, `/agent-hub`, `/payment-dashboard`, `/admin` e `/growth_admin`. Conforme definido no PRD (`openspec/prds/navegacao-responsiva-global-e-admin.md`), unificar a navegação pública em um partial reutilizável e dotar o portal admin de navegação responsiva dedicada resolve o problema sem dependências externas.

## O que muda
- Novo partial compartilhado `public_html/includes/nav.php` (função `grid402_render_public_nav(array $opts = [])`) com HTML semântico, CSS isolado (`.g402-nav-*`) e JS acessível (menu hambúrguer `<= 768px`, `aria-expanded`, `aria-controls`, `Escape`, clique fora).
- Substituição/integração do cabeçalho em todas as páginas públicas: `index.php` (Home + rotas institucionais `/about`, `/contact`, `/terms`, `/privacy`), `pass.php`, `catalog.php`, `agents.php`, `trust.php`, `growth_program.php`, `builder_reputation.php`, `agenthub.php` e `payment_dashboard.php`.
- Nova barra de navegação responsiva no portal administrativo (`public_html/admin.php` e `public_html/growth_admin.php`) com menu hambúrguer em `<= 860px` e links para Visão Geral, Telemetria, Passes / MP, Growth, Voltar ao Site e Sair.
- Testes automatizados em `tests/test_responsive_navigation.php` validando a renderização do menu público, do menu admin e a ausência de erros de sintaxe em todas as páginas.

## Impacto
- Specs afetadas: `responsive-navigation` (nova spec)
- Código afetado: `public_html/includes/nav.php`, `public_html/index.php`, `public_html/pass.php`, `public_html/catalog.php`, `public_html/agents.php`, `public_html/trust.php`, `public_html/growth_program.php`, `public_html/builder_reputation.php`, `public_html/agenthub.php`, `public_html/payment_dashboard.php`, `public_html/admin.php`, `public_html/growth_admin.php`, `tests/test_responsive_navigation.php`.
