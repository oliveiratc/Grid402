# responsive-navigation Specification

## Purpose
Definir a arquitetura compartilhada de navegação responsiva global (HTML semântico, acessibilidade, menu móvel em breakpoint de viewport estreito e seletor bilíngue PT/EN) para as páginas públicas e o portal administrativo do Grid402.

## Requirements

### Requirement: Partial Compartilhado de Navegação Pública
O sistema MUST fornecer `public_html/includes/nav.php` com a função `grid402_render_public_nav(array $opts = [])` renderizando `<header>` e `<nav>` semânticos, marca `Grid402`, os 5 links principais (`/` Home, `/catalog` Catálogo, `/pass` Session Pass, `/agents` Agentes, `/trust` Trust), destaque `aria-current="page"` no item ativo e seletor `PT | EN` quando `show_lang` for `true`.

#### Scenario: Renderização com rota ativa e seletor de idioma
- GIVEN `grid402_render_public_nav(['active' => '/pass', 'show_lang' => false])`
- WHEN o HTML é gerado
- THEN contém `<header` e `<nav`, todos os 5 links principais, `aria-current="page"` no link `/pass`, e não exibe o seletor `PT | EN`.

#### Scenario: Renderização na Home com seletor de idioma
- GIVEN `grid402_render_public_nav(['active' => '/', 'show_lang' => true])`
- WHEN o HTML é gerado
- THEN contém os botões `PT` e `EN` com `setLanguage('pt')` e `setLanguage('en')`.

---

### Requirement: Menu Hambúrguer Acessível no Mobile
Tanto a navegação pública (`<= 768px`) quanto a navegação do portal admin (`<= 860px`) MUST exibir um botão `<button>` hambúrguer com área de toque mínima de `44x44px`, atributos `aria-expanded="false"`, `aria-controls` e `aria-label`, além de script para alternar a abertura, fechar com tecla `Escape`, clique fora ou clique em link.

#### Scenario: Atributos de acessibilidade presentes no botão mobile
- GIVEN o HTML renderizado de `grid402_render_public_nav()` ou `grid402_render_admin_nav()`
- WHEN o botão de menu mobile é inspecionado
- THEN possui `type="button"`, `aria-expanded="false"`, `aria-controls`, `aria-label` e regras CSS para `min-width: 44px` e `min-height: 44px` em telas móveis.

---

### Requirement: Integração em Todas as Páginas Públicas
Todas as páginas públicas (`index.php` incluindo rotas institucionais, `pass.php`, `catalog.php`, `agents.php`, `trust.php`, `growth_program.php`, `builder_reputation.php`, `agenthub.php`, `payment_dashboard.php`) MUST incluir `includes/nav.php` e invocar `grid402_render_public_nav()`.

#### Scenario: Todas as páginas públicas utilizam o partial compartilhado
- GIVEN a lista de arquivos das 9 páginas públicas em `public_html/`
- WHEN o código-fonte é verificado
- THEN cada arquivo referencia `includes/nav.php` e chama `grid402_render_public_nav`.

---

### Requirement: Navegação Responsiva no Portal Administrativo
As páginas `public_html/admin.php` e `public_html/growth_admin.php` MUST utilizar uma navegação responsiva dedicada (`public_html/includes/admin_nav.php` com `grid402_render_admin_nav()`) contendo links para Visão Geral (`/admin#overview`), Telemetria (`/admin#telemetry`), Passes / MP (`/admin#passes`), Growth (`/growth_admin`), Voltar ao Site (`/`) e Sair, com menu hambúrguer em `<= 860px`.

#### Scenario: Renderização do menu administrativo em admin.php e growth_admin.php
- GIVEN `grid402_render_admin_nav(['active' => 'overview'])`
- WHEN o HTML é gerado
- THEN contém os links para `/admin#overview`, `/admin#telemetry`, `/admin#passes`, `/growth_admin`, `/` e logout, além do botão hambúrguer acessível.
