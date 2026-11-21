<?php
/**
 * Grid402 — Banner de Reengagement de Session Pass Ocioso
 *
 * Componente visual para lembrar usuários de Session Passes
 * que foram comprados mas ainda não foram utilizados (ou estão
 * prestes a expirar).
 *
 * Uso:
 *   require_once __DIR__ . '/pass-reengagement-banner.php';
 *   echo grid402_pass_reengagement_banner([
 *       'idle_passes' => 3,           // qtd de passes ociosos do usuário
 *       'idle_usdc'   => 7.00,        // USDC parado
 *       'expires_in'  => 12,          // dias até expirar (opcional)
 *       'lang'        => 'pt-BR',     // 'pt-BR' ou 'en'
 *   ]);
 *
 * Se chamado sem argumentos, exibe um banner genérico de conscientização.
 */

declare(strict_types=1);

if (!function_exists('grid402_pass_reengagement_banner')) {
    function grid402_pass_reengagement_banner(array $opts = []): string {
        $lang  = (string) ($opts['lang'] ?? 'pt-BR');
        $isPt  = str_starts_with($lang, 'pt');
        $idle  = (int) ($opts['idle_passes'] ?? 0);
        $usdc  = (float) ($opts['idle_usdc'] ?? 0.0);
        $days  = (int) ($opts['expires_in'] ?? 0);
        $hasExpiry = $days > 0;

        // ----- Textos -----
        $texts = [
            'pt-BR' => [
                'title'   => 'Você tem créditos de Session Pass parados',
                'body'    => 'Seu(s) pass(s) não foi(ram) usado(s) ainda. Os créditos não expiram imediatamente, mas vale a pena começar a usar antes que o saldo fique esquecido.',
                'cta'     => 'Ver meus passes',
                'url'     => '/payment-dashboard',
                'subtitle'=> 'Consulte forex, cnpj, dossier e mais de 25 serviços em <15ms.',
            ],
            'en' => [
                'title'   => 'You have idle Session Pass credits',
                'body'    => 'Your pass(es) have not been used yet. Credits do not expire immediately, but it is worth starting to use them before the balance is forgotten.',
                'cta'     => 'View my passes',
                'url'     => '/payment-dashboard',
                'subtitle'=> 'Query forex, cnpj, dossier and 25+ services in <15ms.',
            ],
        ];
        $t = $texts[$isPt ? 'pt-BR' : 'en'];
        $url = $t['url'];

        // ----- Variação por urgência -----
        if ($idle > 0 && $hasExpiry && $days <= 7) {
            $badgeText    = $isPt ? 'Expira em ' . $days . ' dias' : 'Expires in ' . $days . ' days';
            $variantClass = 'g402-banner-reengagement--urgent';
            $title        = $isPt ? 'Seus créditos expiram em breve' : 'Your credits expire soon';
            $body         = $isPt
                ? "Você tem {$idle} pass(es) com {$usdc} USDC parado(s) e expira(m) em {$days} dia(s). Use agora para não perder o saldo."
                : "You have {$idle} pass(es) with {$usdc} USDC idle expiring in {$days} day(s). Use them now to avoid losing the balance.";
            $cta          = $isPt ? 'Usar meus créditos agora' : 'Use my credits now';
        } elseif ($idle > 0) {
            $badgeText    = $isPt ? $idle . ' pass(es) sem uso' : $idle . ' unused pass(es)';
            $variantClass = 'g402-banner-reengagement--idle';
            $title        = $t['title'];
            $body         = $isPt
                ? "Você tem {$idle} pass(es) com {$usdc} USDC parado(s). Comece a usar em https://grid402.online/payment-dashboard"
                : "You have {$idle} unused pass(es) with {$usdc} USDC idle. Start using at https://grid402.online/payment-dashboard";
            $cta          = $t['cta'];
        } else {
            // Banner genérico de conscientização (quando não há dados do usuário)
            $badgeText    = 'Session Pass';
            $variantClass = 'g402-banner-reengagement--info';
            $title        = $isPt ? 'Compre um Session Pass e pague por chamada' : 'Buy a Session Pass and pay per call';
            $body         = $isPt
                ? 'Adquira créditos pré-pagos (R$ 12 Pix ou 2 / 5 / 20 USDC) e consulte APIs em <15ms sem assinar transação a cada chamada.'
                : 'Purchase prepaid credits (R$ 12 Pix or 2 / 5 / 20 USDC) and query APIs in <15ms without signing a transaction on every call.';
            $cta          = $isPt ? 'Comprar Session Pass' : 'Buy Session Pass';
            $url          = '/pass?tier=starter&autopix=1';
        }
        $url = $opts['url'] ?? $url;

        ob_start();
        ?>
        <style>
        .g402-banner-reengagement {
            margin: 1.25rem 0;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            border: 1px solid;
            box-shadow: 0 6px 20px rgba(0,0,0,.35);
            animation: g402BannerSlide .45s ease-out both;
        }
        @keyframes g402BannerSlide {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .g402-banner-reengagement--info  { background: rgba(0,82,255,.07);  border-color: rgba(0,82,255,.3); }
        .g402-banner-reengagement--idle  { background: rgba(245,158,11,.07); border-color: rgba(245,158,11,.3); }
        .g402-banner-reengagement--urgent{ background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.35); }
        .g402-banner-icon {
            width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center;
            flex-shrink: 0;
        }
        .g402-banner-reengagement--info  .g402-banner-icon { background: rgba(0,82,255,.15); color: #00F0FF; }
        .g402-banner-reengagement--idle  .g402-banner-icon { background: rgba(245,158,11,.15); color: #F59E0B; }
        .g402-banner-reengagement--urgent .g402-banner-icon { background: rgba(239,68,68,.15); color: #EF4444; }
        .g402-banner-content { flex: 1; min-width: 220px; }
        .g402-banner-badge {
            display: inline-block; font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .6px; padding: 2px 8px; border-radius: 999px; margin-bottom: .35rem;
            border: 1px solid; opacity: .9;
        }
        .g402-banner-reengagement--info  .g402-banner-badge { color: #67E8F9; border-color: rgba(0,240,255,.35); background: rgba(0,240,255,.08); }
        .g402-banner-reengagement--idle  .g402-banner-badge { color: #FBBF24; border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.08); }
        .g402-banner-reengagement--urgent .g402-banner-badge { color: #FCA5A5; border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.08); }
        .g402-banner-title { font-size: .98rem; font-weight: 800; color: #F8FAFC; margin: 0 0 .2rem; letter-spacing: -.2px; }
        .g402-banner-body  { font-size: .86rem; color: #CBD5E1; margin: 0 0 .65rem; line-height: 1.5; }
        .g402-banner-body a { color: #00F0FF; text-decoration: underline; text-decoration-thickness: 1px; }
        .g402-banner-actions { display: flex; gap: .55rem; align-items: center; flex-wrap: wrap; }
        .g402-banner-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .58rem 1rem; border-radius: 8px; font-weight: 800; font-size: .88rem;
            text-decoration: none; border: none; cursor: pointer; transition: transform .1s ease, filter .15s ease;
        }
        .g402-banner-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }
        .g402-banner-reengagement--info  .g402-banner-btn { background: #0052FF; color: #FFFFFF; }
        .g402-banner-reengagement--idle  .g402-banner-btn { background: #F59E0B; color: #04120D; }
        .g402-banner-reengagement--urgent .g402-banner-btn { background: #EF4444; color: #fff; }
        .g402-banner-close {
            background: none; border: none; color: #64748B; cursor: pointer; padding: .4rem;
            border-radius: 6px; font-size: 1.2rem; line-height: 1; flex-shrink: 0;
        }
        .g402-banner-close:hover { background: rgba(255,255,255,.08); color: #F8FAFC; }
        @media (max-width: 480px) {
            .g402-banner-reengagement { flex-direction: column; align-items: flex-start; padding: 1rem; }
            .g402-banner-actions { width: 100%; }
            .g402-banner-btn { width: 100%; justify-content: center; }
        }
        </style>

        <section class="g402-banner-reengagement <?php echo $variantClass; ?>" role="banner" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="g402-banner-icon" aria-hidden="true"><?php echo $hasExpiry && $days <= 7 ? '⏰' : ($idle > 0 ? '💳' : '🪪'); ?></div>
            <div class="g402-banner-content">
                <span class="g402-banner-badge"><?php echo htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'); ?></span>
                <h3 class="g402-banner-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="g402-banner-body"><?php echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="g402-banner-actions">
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="g402-banner-btn"><?php echo htmlspecialchars($cta, ENT_QUOTES, 'UTF-8'); ?></a>
                <button type="button" class="g402-banner-close" onclick="this.closest('.g402-banner-reengagement').style.display='none'" aria-label="Fechar aviso">✕</button>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
