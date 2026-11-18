<?php
/**
 * Testes da navegacao responsiva global (site publico + portal admin).
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers_x402_exact.php';
require_once __DIR__ . '/../public_html/includes/nav.php';
require_once __DIR__ . '/../public_html/includes/admin_nav.php';

echo "=== Testando Navegacao Responsiva Global (Site Publico + Portal Admin) ===\n";

// Cenario 1: Partial publico com show_lang=true e active='/catalog'
ob_start();
grid402_render_public_nav(['active' => '/catalog', 'show_lang' => true]);
$publicWithLang = (string) ob_get_clean();

t_assert('Public nav contem marca Grid402', str_contains($publicWithLang, 'Grid<span') && str_contains($publicWithLang, '402</span>'));
t_assert('Public nav contem links principais',
    str_contains($publicWithLang, 'href="/"') &&
    str_contains($publicWithLang, 'href="/catalog"') &&
    str_contains($publicWithLang, 'href="/pass"') &&
    str_contains($publicWithLang, 'href="/agents"') &&
    str_contains($publicWithLang, 'href="/trust"')
);
t_assert('Public nav marca link ativo com aria-current="page" e is-active',
    preg_match('/class="[^"]*\bis-active\b[^"]*"[^>]*href="\/catalog"[^>]*aria-current="page"/s', $publicWithLang) === 1
);
t_assert('Public nav com show_lang=true renderiza botoes PT | EN',
    str_contains($publicWithLang, 'id="lang-btn-pt"') &&
    str_contains($publicWithLang, 'id="lang-btn-en"')
);

// Cenario 1b: Partial publico sem seletor de idioma
ob_start();
grid402_render_public_nav(['active' => '/trust', 'show_lang' => false]);
$publicNoLang = (string) ob_get_clean();
t_assert('Public nav com show_lang=false omite botoes PT | EN',
    !str_contains($publicNoLang, 'id="lang-btn-pt"') &&
    !str_contains($publicNoLang, 'id="lang-btn-en"')
);

// Cenario 2: Acessibilidade e mobile (<= 768px)
t_assert('Public nav possui breakpoint <= 768px', str_contains($publicWithLang, 'max-width:768px'));
t_assert('Public nav possui botao hamburguer >= 44x44px e atributos ARIA',
    str_contains($publicWithLang, 'min-width:44px;min-height:44px') &&
    str_contains($publicWithLang, 'type="button"') &&
    str_contains($publicWithLang, 'aria-expanded="false"') &&
    str_contains($publicWithLang, 'aria-controls="g402-nav-menu"') &&
    str_contains($publicWithLang, 'aria-label="')
);
t_assert('Public nav fecha menu em Escape, clique fora e clique em link',
    str_contains($publicWithLang, "'Escape'") &&
    str_contains($publicWithLang, 'menu.contains(e.target)') &&
    str_contains($publicWithLang, '.g402-nav-link')
);

// Cenario 3: Integracao em todas as 9 paginas publicas
$publicPages = [
    'public_html/index.php',
    'public_html/pass.php',
    'public_html/catalog.php',
    'public_html/agents.php',
    'public_html/trust.php',
    'public_html/growth_program.php',
    'public_html/builder_reputation.php',
    'public_html/agenthub.php',
    'public_html/payment_dashboard.php',
];

$rootDir = dirname(__DIR__);
foreach ($publicPages as $relPath) {
    $content = (string) file_get_contents($rootDir . '/' . $relPath);
    t_assert("Pagina publica {$relPath} integra includes/nav.php",
        str_contains($content, "includes/nav.php") &&
        str_contains($content, "grid402_render_public_nav(")
    );
}

$indexContent = (string) file_get_contents($rootDir . '/public_html/index.php');
t_assert('index.php chama grid402_render_public_nav() na Home e no template institucional (2x)',
    substr_count($indexContent, 'grid402_render_public_nav(') >= 2
);

// Cenario 4: Partial administrativo e integracao em admin.php e growth_admin.php
ob_start();
grid402_render_admin_nav(['active' => 'overview', 'logout_url' => '/admin?action=logout']);
$adminNavHtml = (string) ob_get_clean();

t_assert('Admin nav possui todos os links administrativos esperados',
    str_contains($adminNavHtml, 'href="/admin#overview"') &&
    str_contains($adminNavHtml, 'href="/admin#telemetry"') &&
    str_contains($adminNavHtml, 'href="/admin#passes"') &&
    str_contains($adminNavHtml, 'href="/growth_admin"') &&
    str_contains($adminNavHtml, 'href="/"') &&
    str_contains($adminNavHtml, 'href="/admin?action=logout"')
);
t_assert('Admin nav marca item ativo com aria-current="page"',
    preg_match('/class="[^"]*\bis-active\b[^"]*"[^>]*href="\/admin#overview"[^>]*aria-current="page"/s', $adminNavHtml) === 1
);
t_assert('Admin nav possui breakpoint <= 860px e acessibilidade >= 44px',
    str_contains($adminNavHtml, 'max-width:860px') &&
    str_contains($adminNavHtml, 'min-width:44px;min-height:44px') &&
    str_contains($adminNavHtml, 'aria-expanded="false"') &&
    str_contains($adminNavHtml, 'aria-controls="g402-anav-menu"') &&
    str_contains($adminNavHtml, "'Escape'")
);

$adminContent = (string) file_get_contents($rootDir . '/public_html/admin.php');
t_assert('admin.php integra includes/admin_nav.php e ancoras #overview, #telemetry, #passes',
    str_contains($adminContent, 'includes/admin_nav.php') &&
    str_contains($adminContent, 'grid402_render_admin_nav(') &&
    str_contains($adminContent, 'id="overview"') &&
    str_contains($adminContent, 'id="telemetry"') &&
    str_contains($adminContent, 'id="passes"')
);
t_assert('admin.php aponta para rota valida /reputation (sem /builder_reputation 404)',
    str_contains($adminContent, 'href="/reputation"') &&
    !str_contains($adminContent, 'href="/builder_reputation"')
);

$growthAdminContent = (string) file_get_contents($rootDir . '/public_html/growth_admin.php');
t_assert('growth_admin.php integra includes/admin_nav.php',
    str_contains($growthAdminContent, 'includes/admin_nav.php') &&
    str_contains($growthAdminContent, 'grid402_render_admin_nav(')
);

t_finish('Navegacao Responsiva Global');
