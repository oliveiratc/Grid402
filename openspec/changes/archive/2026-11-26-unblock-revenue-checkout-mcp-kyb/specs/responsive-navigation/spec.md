# Spec Delta: responsive-navigation

## MODIFIED Requirements

### Requirement: Partial Compartilhado de Navegação Pública
O sistema MUST fornecer `public_html/includes/nav.php` com `grid402_render_public_nav(array $opts = [])` renderizando `<header>` e `<nav>` semânticos, marca `Grid402`, os **6 links principais** (`/` Home, `/catalog` Catalog, `/dossie-kyb` Dossiê KYB, `/pass` Session Pass, `/agents` AI Agents, `/trust` Trust), `aria-current="page"` no item ativo e seletor `PT | EN` quando `show_lang` for `true`.

#### Scenario: Renderização com rota ativa e seletor de idioma
- **GIVEN** `grid402_render_public_nav(['active' => '/pass', 'show_lang' => false])`
- **WHEN** o HTML é gerado
- **THEN** contém `<header` e `<nav`, todos os 6 links principais (incluindo `/dossie-kyb`), `aria-current="page"` no link `/pass`, e não exibe o seletor `PT | EN`

#### Scenario: Renderização com rota ativa `/dossie-kyb` e 6 links principais
- **GIVEN** `grid402_render_public_nav(['active' => '/dossie-kyb', 'show_lang' => false])`
- **WHEN** o HTML é gerado
- **THEN** contém `<header` e `<nav`, todos os 6 links principais, `aria-current="page"` no link `/dossie-kyb`, e não exibe o seletor `PT | EN`

#### Scenario: Renderização na Home com seletor de idioma
- **GIVEN** `grid402_render_public_nav(['active' => '/', 'show_lang' => true])`
- **WHEN** o HTML é gerado
- **THEN** contém os 6 links principais e os botões `PT` e `EN` com `setLanguage('pt')` e `setLanguage('en')`
