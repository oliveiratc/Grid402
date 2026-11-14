<?php
/**
 * Grid402 - Navegacao Responsiva Dedicada do Portal Administrativo (/admin e /growth_admin)
 */

declare(strict_types=1);

if (!function_exists('grid402_render_admin_nav')) {
    function grid402_render_admin_nav(array $opts = []): void {
        $active = (string) ($opts['active'] ?? 'overview');
        $logoutUrl = (string) ($opts['logout_url'] ?? '/admin?action=logout');

        $links = [
            ['href' => '/admin#overview', 'key' => 'overview', 'label' => 'Visão Geral'],
            ['href' => '/admin#telemetry', 'key' => 'telemetry', 'label' => 'Telemetria'],
            ['href' => '/admin#passes', 'key' => 'passes', 'label' => 'Passes / MP'],
            ['href' => '/growth_admin', 'key' => 'growth', 'label' => 'Growth'],
            ['href' => '/', 'key' => 'site', 'label' => 'Voltar ao Site'],
        ];
        ?>
<style>
.g402-anav-bar{position:sticky;top:0;z-index:120;background:rgba(6,11,25,.95);backdrop-filter:blur(10px);border-bottom:1px solid #1E2C4F;margin-bottom:1.25rem;}
.g402-anav-inner{max-width:1360px;margin:0 auto;padding:.7rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;position:relative;}
.g402-anav-brand{display:inline-flex;align-items:center;gap:.5rem;color:#F8FAFC;text-decoration:none;font-weight:800;font-size:1.05rem;flex-shrink:0;}
.g402-anav-brand span{color:#00F0FF;}
.g402-anav-badge{font-size:.7rem;font-weight:700;text-transform:uppercase;padding:2px 7px;border-radius:999px;background:rgba(0,82,255,.2);color:#00F0FF;border:1px solid rgba(0,240,255,.35);}
.g402-anav-toggle{display:none;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:0 .5rem;background:rgba(255,255,255,.04);border:1px solid #1E2C4F;border-radius:8px;color:#F8FAFC;cursor:pointer;font-size:1.2rem;}
.g402-anav-menu{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;}
.g402-anav-link{display:inline-flex;align-items:center;padding:.45rem .75rem;border-radius:7px;text-decoration:none;color:#94A3B8;font-size:.86rem;font-weight:600;white-space:nowrap;}
.g402-anav-link:hover{color:#fff;background:rgba(255,255,255,.06);}
.g402-anav-link.is-active{color:#00F0FF;background:rgba(0,240,255,.1);border:1px solid rgba(0,240,255,.25);}
.g402-anav-logout{color:#FCA5A5;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);}
.g402-anav-logout:hover{background:rgba(239,68,68,.2);color:#fff;}
@media (max-width:860px){
  .g402-anav-toggle{display:inline-flex;}
  .g402-anav-menu{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;align-items:stretch;gap:.4rem;padding:.9rem 1.25rem 1.15rem;background:#080F22;border-bottom:1px solid #1E2C4F;box-shadow:0 16px 36px rgba(0,0,0,.6);}
  .g402-anav-menu.is-open{display:flex;}
  .g402-anav-link{min-height:44px;padding:.65rem .9rem;font-size:.94rem;}
}
</style>
<header class="g402-anav-bar">
  <div class="g402-anav-inner">
    <a class="g402-anav-brand" href="/admin">
      <span>Grid402</span>
      <span class="g402-anav-badge">Admin</span>
    </a>
    <button type="button" class="g402-anav-toggle" id="g402-anav-toggle" aria-expanded="false" aria-controls="g402-anav-menu" aria-label="Abrir menu administrativo">☰</button>
    <nav class="g402-anav-menu" id="g402-anav-menu" aria-label="Navegação administrativa">
      <?php foreach ($links as $item):
          $isActive = ($active === $item['key']);
      ?>
      <a class="g402-anav-link<?php echo $isActive ? ' is-active' : ''; ?>"
         href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
         <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <?php endforeach; ?>
      <a class="g402-anav-link g402-anav-logout" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
    </nav>
  </div>
</header>
<script>
(function(){
  var btn=document.getElementById('g402-anav-toggle');
  var menu=document.getElementById('g402-anav-menu');
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
