<?php
/**
 * Grid402 - Partial Compartilhado de Navegacao Publica Responsiva
 */

declare(strict_types=1);

if (!function_exists('grid402_render_public_nav')) {
    function grid402_render_public_nav(array $opts = []): void {
        $active = (string) ($opts['active'] ?? '');
        $showLang = !empty($opts['show_lang']);
        $base = rtrim((string) ($opts['domain'] ?? ''), '/');

        $links = [
            ['href' => ($base !== '' ? $base : '') . '/', 'key' => '/', 'label' => 'Home', 'i18n' => 'nav.home'],
            ['href' => $base . '/catalog', 'key' => '/catalog', 'label' => 'Catalog', 'i18n' => 'nav.catalog'],
            ['href' => $base . '/dossie-kyb', 'key' => '/dossie-kyb', 'label' => 'Dossiê KYB', 'i18n' => 'nav.dossier'],
            ['href' => $base . '/pass', 'key' => '/pass', 'label' => 'Session Pass', 'i18n' => 'nav.pass'],
            ['href' => $base . '/agents', 'key' => '/agents', 'label' => 'AI Agents', 'i18n' => 'nav.agents'],
            ['href' => $base . '/trust', 'key' => '/trust', 'label' => 'Trust', 'i18n' => 'nav.trust'],
        ];
        ?>
<style>
.g402-nav-header{position:sticky;top:0;z-index:120;background:rgba(6,11,25,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border,#1E2C4F);width:100%;}
.g402-nav-inner{max-width:1200px;margin:0 auto;padding:.75rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;position:relative;}
.g402-nav-brand{display:inline-flex;align-items:center;gap:.55rem;text-decoration:none;color:#F8FAFC;font-weight:800;font-size:1.15rem;letter-spacing:-.4px;flex-shrink:0;}
.g402-nav-brand span{color:#00F0FF;}
.g402-nav-brand img{width:28px;height:28px;border-radius:6px;display:block;}
.g402-nav-toggle{display:none;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:0 .5rem;background:rgba(255,255,255,.04);border:1px solid var(--border,#1E2C4F);border-radius:8px;color:#F8FAFC;cursor:pointer;font-size:1.25rem;line-height:1;}
.g402-nav-toggle:focus-visible,.g402-nav-link:focus-visible{outline:2px solid #00F0FF;outline-offset:2px;}
.g402-nav-menu{display:flex;align-items:center;gap:1.15rem;}
.g402-nav-links{display:flex;align-items:center;gap:.35rem;list-style:none;margin:0;padding:0;}
.g402-nav-link{display:inline-flex;align-items:center;padding:.45rem .72rem;border-radius:7px;text-decoration:none;color:#94A3B8;font-size:.9rem;font-weight:600;transition:color .15s ease,background .15s ease;white-space:nowrap;}
.g402-nav-link:hover{color:#F8FAFC;background:rgba(255,255,255,.05);}
.g402-nav-link.is-active{color:#00F0FF;background:rgba(0,240,255,.1);border:1px solid rgba(0,240,255,.25);}
.g402-nav-lang{display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.04);padding:3px 6px;border-radius:7px;border:1px solid var(--border,#1E2C4F);}
.g402-nav-lang button{background:none;border:none;color:#94A3B8;cursor:pointer;font-size:.78rem;font-weight:700;padding:4px 7px;border-radius:5px;}
.g402-nav-lang button.active-lang,.g402-nav-lang button.active{background:#0052FF;color:#fff;}
@media (max-width:768px){
  .g402-nav-toggle{display:inline-flex;}
  .g402-nav-menu{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;align-items:stretch;gap:.75rem;padding:1rem 1.25rem 1.25rem;background:#080F22;border-bottom:1px solid var(--border,#1E2C4F);box-shadow:0 16px 36px rgba(0,0,0,.55);}
  .g402-nav-menu.is-open{display:flex;}
  .g402-nav-links{flex-direction:column;align-items:stretch;gap:.35rem;}
  .g402-nav-link{min-height:44px;padding:.65rem .9rem;font-size:.96rem;}
  .g402-nav-lang{align-self:flex-start;}
}
</style>
<header class="g402-nav-header">
  <div class="g402-nav-inner">
    <a class="g402-nav-brand" href="<?php echo htmlspecialchars(($base !== '' ? $base : '') . '/', ENT_QUOTES, 'UTF-8'); ?>">
      <img src="<?php echo htmlspecialchars($base . '/assets/logo-icon-transparent.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Grid402" width="28" height="28" onerror="this.style.display='none'">
      <span>Grid<span style="color:#fff">402</span></span>
    </a>
    <button type="button" class="g402-nav-toggle" id="g402-nav-toggle" aria-expanded="false" aria-controls="g402-nav-menu" aria-label="Abrir menu de navegação">☰</button>
    <nav class="g402-nav-menu" id="g402-nav-menu" aria-label="Navegação principal">
      <ul class="g402-nav-links">
        <?php foreach ($links as $item):
            $isActive = ($active === $item['key']);
        ?>
        <li>
          <a class="g402-nav-link<?php echo $isActive ? ' is-active' : ''; ?>"
             href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
             <?php echo $isActive ? 'aria-current="page"' : ''; ?>
             data-i18n="<?php echo htmlspecialchars($item['i18n'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($showLang): ?>
      <div class="g402-nav-lang" role="group" aria-label="Language">
        <button type="button" onclick="if(window.setLanguage)setLanguage('en')" id="lang-btn-en" class="active">EN</button>
        <button type="button" onclick="if(window.setLanguage)setLanguage('pt')" id="lang-btn-pt">PT</button>
      </div>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script>
(function(){
  var btn=document.getElementById('g402-nav-toggle');
  var menu=document.getElementById('g402-nav-menu');
  if(!btn||!menu)return;
  function setOpen(open){
    menu.classList.toggle('is-open',open);
    btn.setAttribute('aria-expanded',open?'true':'false');
    btn.textContent=open?'✕':'☰';
  }
  btn.addEventListener('click',function(e){e.stopPropagation();setOpen(!menu.classList.contains('is-open'));});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&menu.classList.contains('is-open')){setOpen(false);btn.focus();}});
  document.addEventListener('click',function(e){if(menu.classList.contains('is-open')&&!menu.contains(e.target)&&e.target!==btn){setOpen(false);}});
  menu.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){setOpen(false);});});
})();
</script>
        <?php
    }
}
