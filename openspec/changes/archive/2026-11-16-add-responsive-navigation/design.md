# Design: Navegação Responsiva Unificada

## 1. Isolamento de CSS e JS (`.g402-nav-*`)
Como cada página pública possui seu próprio `<style>`, o partial `public_html/includes/nav.php` usa exclusivamente classes prefixadas `.g402-nav-*` e variáveis com fallback (`var(--surface, #0B132B)`, `var(--border, #1E2C4F)`, `var(--accent, #00F0FF)`) para não colidir com estilos existentes (`.btn`, `.nav-links`, `.topbar`).

## 2. Contrato de `grid402_render_public_nav(array $opts = [])`
Opções suportadas:
- `active` (`string`): rota ativa (`/` | `/catalog` | `/pass` | `/agents` | `/trust`), aplicando `aria-current="page"` e classe `.is-active`.
- `show_lang` (`bool`): exibe o seletor `PT | EN` (`setLanguage('pt'|'en')`) quando `true` (ex.: `index.php`).
- `domain` (`string`): URL base canônica opcional (default `/`).

Links renderizados no menu público:
1. Home (`/`)
2. Catálogo (`/catalog`)
3. Session Pass (`/pass`)
4. Agentes (`/agents`)
5. Trust (`/trust`)

## 3. Navegação do Portal Admin (`admin.php` e `growth_admin.php`)
- Partial ou função dedicada `public_html/includes/admin_nav.php` (`grid402_render_admin_nav(array $opts = [])`) para manter o admin desacoplado do site público e sem duplicação entre `admin.php` e `growth_admin.php`.
- Links:
  - **Visão Geral** (`/admin#overview`)
  - **Telemetria** (`/admin#telemetry`)
  - **Passes / MP** (`/admin#passes`)
  - **Growth** (`/growth_admin`)
  - **Voltar ao Site** (`/`)
  - **Sair** (`/admin?logout=1` ou botão de logout existente no admin)
- Breakpoint mobile admin: `@media (max-width: 860px)` com botão hambúrguer `44x44px`, `aria-expanded`, `aria-controls` e fechamento por `Escape` / clique fora / clique em âncora.
