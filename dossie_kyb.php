<?php
/**
 * Grid402 // Gerador Web Self-Service de Dossiê 360º KYB com PDF (/dossie-kyb)
 *
 * Interface 100% SSR em PT-BR para consulta corporativa de CNPJ:
 * - Prévia grátis real via HTTP 402 (/api/v1/dossier)
 * - Checkout Pix inline R$ 12 (Starter Pass = 400 créditos = 40 dossiês) via Mercado Pago
 * - Suporte a token pass_g402_... existente com persistência em localStorage
 * - Relatório visual completo (Veredito IA, Receita Federal, Simples/MEI, CGU CEIS/CNEP, _oracle_proof)
 * - Exportação para PDF via folha @media print A4 de alto contraste
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    define('GRID402_EXEC', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/mercadopago.php';
require_once __DIR__ . '/metrics.php';

if (function_exists('grid402_record_page_visit')) {
    grid402_record_page_visit('/dossie-kyb', grid402_get_trusted_client_ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$domain = defined('GRID402_CANONICAL_DOMAIN') ? rtrim(GRID402_CANONICAL_DOMAIN, '/') : 'https://grid402.online';
$isSandboxMp = grid402_mp_is_sandbox();
$mpConfigured = $isSandboxMp || (grid402_mp_access_token() !== '');
$rapidApiKybUrl = 'https://rapidapi.com/oliveiratc/api/brazil-kyb-cnpj-cgu-sanctions-anbima-workdays-api';
$initialCnpj = preg_replace('/\D/', '', (string) ($_GET['cnpj'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Dossiê 360º KYB de CNPJ com PDF e Pix (a partir de R$ 4,90) | Grid402</title>
    <meta name="description" content="Consulte qualquer CNPJ na Receita Federal, Simples Nacional/MEI e Sanções CGU (CEIS/CNEP) com Veredito de Risco e assinatura criptográfica. Veja um exemplo real em PDF e pague via Pix (R$ 4,90 = 5 dossiês ou R$ 12 = 40 dossiês).">
    <link rel="canonical" href="<?php echo htmlspecialchars($domain . '/dossie-kyb', ENT_QUOTES, 'UTF-8'); ?>">
<?php require_once __DIR__ . '/includes/seo_head.php'; grid402_render_seo_head([
    'title' => 'Gerar Dossiê 360º KYB de CNPJ com PDF e Pix (a partir de R$ 4,90) | Grid402',
    'description' => 'Consulte qualquer CNPJ na Receita Federal, Simples Nacional/MEI e Sanções CGU (CEIS/CNEP) com Veredito de Risco e assinatura criptográfica. Veja um exemplo real em PDF e pague via Pix (R$ 4,90 = 5 dossiês ou R$ 12 = 40 dossiês).',
    'path' => '/dossie-kyb',
    'card' => 'dossie-kyb',
    'locale' => 'pt_BR',
]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "Grid402 Gerador de Dossiê 360º KYB de CNPJ",
      "url": "https://grid402.online/dossie-kyb",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "inLanguage": "pt-BR",
      "description": "Gerador self-service de Dossiê 360º KYB de empresas brasileiras (Receita Federal, Simples Nacional/MEI, Sanções CGU CEIS/CNEP e Veredito IA) com exportação PDF e pagamento via Pix sem mensalidade.",
      "offers": {
        "@type": "Offer",
        "name": "Starter Pass (400 créditos = 40 Dossiês KYB)",
        "price": "12.00",
        "priceCurrency": "BRL",
        "availability": "https://schema.org/InStock",
        "url": "https://grid402.online/pass?tier=starter&method=pix"
      }
    }
    </script>
    <style>
        :root {
            --bg: #060B19;
            --surface: #0B132B;
            --surface-2: #111C38;
            --border: #1E2C4F;
            --text: #F8FAFC;
            --muted: #94A3B8;
            --cyan: #00F0FF;
            --blue: #0052FF;
            --green: #10B981;
            --amber: #F59E0B;
            --red: #EF4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
        }
        .wrap { max-width: 1020px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        .badge-top {
            display: inline-flex; align-items: center; gap: .45rem;
            background: rgba(16, 185, 129, .12); color: #34D399;
            border: 1px solid rgba(16, 185, 129, .35);
            border-radius: 999px; padding: .28rem .8rem; font-size: .8rem; font-weight: 700;
            margin-bottom: .9rem;
        }
        h1 {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            font-size: clamp(1.75rem, 4.2vw, 2.45rem);
            line-height: 1.15; letter-spacing: -.03em; margin: 0 0 .75rem;
        }
        .lead { color: var(--muted); font-size: 1.03rem; max-width: 780px; margin: 0 0 1.5rem; }
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 1.5rem; margin-bottom: 1.25rem;
        }
        .card-highlight { border-color: rgba(0, 240, 255, .45); box-shadow: 0 10px 30px rgba(0, 82, 255, .15); }
        .form-grid { display: grid; grid-template-columns: 1fr auto; gap: .75rem; align-items: end; }
        @media (max-width: 680px) { .form-grid { grid-template-columns: 1fr; } }
        label { display: block; font-size: .85rem; font-weight: 600; color: #CBD5E1; margin-bottom: .35rem; }
        .input {
            width: 100%; padding: .82rem 1rem; border-radius: 9px;
            border: 1px solid var(--border); background: #050914; color: #fff;
            font-size: 1rem; font-family: 'JetBrains Mono', monospace;
        }
        .input:focus { outline: 2px solid var(--cyan); border-color: var(--cyan); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            padding: .82rem 1.25rem; border-radius: 9px; font-weight: 700; font-size: .95rem;
            border: none; cursor: pointer; text-decoration: none; transition: opacity .15s, transform .15s;
        }
        .btn:hover:not(:disabled) { opacity: .94; transform: translateY(-1px); }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .btn-pix { background: linear-gradient(135deg, #059669, #10B981); color: #04120D; }
        .btn-primary { background: linear-gradient(135deg, var(--blue), var(--cyan)); color: #041024; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: var(--cyan); color: var(--cyan); }
        .quick-samples { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; align-items: center; font-size: .82rem; color: var(--muted); }
        .chip {
            background: rgba(255,255,255,.04); border: 1px solid var(--border);
            color: #CBD5E1; border-radius: 999px; padding: .24rem .65rem;
            font-size: .78rem; font-family: 'JetBrains Mono', monospace; cursor: pointer;
        }
        .chip:hover { border-color: var(--cyan); color: var(--cyan); }
        .alert { border-radius: 10px; padding: .9rem 1.1rem; margin: 1rem 0; font-size: .92rem; display: none; }
        .alert.show { display: block; }
        .alert-err { background: rgba(239, 68, 68, .12); border: 1px solid rgba(239, 68, 68, .4); color: #FCA5A5; }
        .alert-ok { background: rgba(16, 185, 129, .12); border: 1px solid rgba(16, 185, 129, .4); color: #6EE7B7; }
        .two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-top: 1rem; }
        .preview-box { display: none; }
        .preview-box.show { display: block; }
        .kv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .85rem; margin-top: .85rem; }
        .kv { background: #070D1F; border: 1px solid var(--border); border-radius: 9px; padding: .75rem .9rem; }
        .kv-label { font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        .kv-val { font-size: .98rem; font-weight: 700; color: #fff; margin-top: .2rem; word-break: break-word; }
        .locked-pill {
            display: inline-block; font-size: .78rem; font-family: 'JetBrains Mono', monospace;
            background: rgba(245, 158, 11, .1); border: 1px solid rgba(245, 158, 11, .35);
            color: #FCD34D; border-radius: 6px; padding: .2rem .55rem; margin: .2rem .3rem .2rem 0;
        }
        .pix-panel { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .pix-panel.show { display: block; }
        .pix-flex { display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: center; }
        .qr-frame { background: #fff; padding: .55rem; border-radius: 10px; width: 205px; height: 205px; display: flex; align-items: center; justify-content: center; }
        .qr-frame img { max-width: 100%; max-height: 100%; display: block; }
        .report-wrap { display: none; }
        .report-wrap.show { display: block; }
        .verdict-banner {
            border-radius: 12px; padding: 1.25rem 1.4rem; margin-bottom: 1.25rem;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
        }
        .verdict-APPROVED { background: rgba(16, 185, 129, .14); border: 1px solid rgba(16, 185, 129, .5); }
        .verdict-FLAGGED { background: rgba(245, 158, 11, .14); border: 1px solid rgba(245, 158, 11, .5); }
        .verdict-REJECTED { background: rgba(239, 68, 68, .14); border: 1px solid rgba(239, 68, 68, .5); }
        .score-circle {
            font-family: 'Space Grotesk', sans-serif; font-size: 2rem; font-weight: 800;
            padding: .45rem 1rem; border-radius: 12px; background: rgba(0, 0, 0, .28);
        }
        .section-h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.15rem; color: var(--cyan); margin: 1.5rem 0 .65rem; border-bottom: 1px solid var(--border); padding-bottom: .35rem; }
        .proof-code { font-family: 'JetBrains Mono', monospace; font-size: .78rem; color: #CBD5E1; word-break: break-all; background: #050914; border: 1px solid var(--border); border-radius: 8px; padding: .85rem; }
        .trust-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .85rem; margin-top: 2rem; }
        .trust-item { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; font-size: .88rem; color: var(--muted); }
        .trust-item strong { color: #fff; display: block; margin-bottom: .25rem; }
        .print-only-header { display: none; }
        .seo-crosslinks { margin-top: 2.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border); font-size: .88rem; color: var(--muted); }
        .seo-crosslinks a { color: var(--cyan); margin-right: 1rem; text-decoration: none; }
        .seo-crosslinks a:hover { text-decoration: underline; }

        /* Folha de estilo dedicada para exportação PDF via navegador (@media print) */
        @media print {
            @page { size: A4 portrait; margin: 14mm; }
            body { background: #FFFFFF !important; color: #0F172A !important; font-size: 11pt; }
            .g402-nav-header, .no-print, #lookup-card, #preview-card, #unlock-grid, .trust-strip, .seo-crosslinks, .alert {
                display: none !important;
            }
            .wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .print-only-header {
                display: flex !important; justify-content: space-between; align-items: center;
                border-bottom: 2px solid #0F172A; padding-bottom: 10px; margin-bottom: 16px;
            }
            .card {
                background: #FFFFFF !important; border: 1px solid #CBD5E1 !important;
                box-shadow: none !important; color: #0F172A !important; padding: 14px !important;
                page-break-inside: avoid;
            }
            .verdict-banner {
                border: 2px solid #0F172A !important; background: #F8FAFC !important; color: #0F172A !important;
            }
            .score-circle { background: #E2E8F0 !important; color: #0F172A !important; }
            .section-h2 { color: #0F172A !important; border-bottom-color: #94A3B8 !important; }
            .kv { background: #F8FAFC !important; border: 1px solid #CBD5E1 !important; }
            .kv-label { color: #475569 !important; }
            .kv-val { color: #0F172A !important; }
            .proof-code { background: #F1F5F9 !important; color: #0F172A !important; border-color: #94A3B8 !important; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/dossie-kyb', 'domain' => $domain, 'show_lang' => false]); ?>

<main class="wrap">
    <div class="no-print">
        <span class="badge-top">🇧🇷 Consulta Oficial em Tempo Real · Zero Cadastro · Zero-PII (LGPD Art. 1º)</span>
        <h1>Gerador de Dossiê 360º KYB de CNPJ com PDF</h1>
        <p class="lead">
            Audite qualquer empresa brasileira em segundos: <strong>Receita Federal (CNPJ)</strong> + <strong>Simples Nacional / MEI</strong> + <strong>Sanções Federais CGU (CEIS/CNEP)</strong> + <strong>Veredito de Risco (APPROVED / FLAGGED / REJECTED)</strong> e assinatura criptográfica ECDSA.
            Confira o <strong>exemplo real completo (com PDF)</strong> abaixo e comece no Pix por <strong>R$ 4,90 (5 dossiês)</strong> ou <strong>R$ 12 (40 dossiês — R$ 0,30 cada)</strong>.
        </p>
    </div>

    <!-- Passo 1: Formulário de CNPJ + Amostra Real Completa -->
    <section id="lookup-card" class="card card-highlight no-print" aria-labelledby="titulo-busca">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.65rem;margin-bottom:.85rem;">
            <h2 id="titulo-busca" style="margin:0;font-size:1.15rem;">1. Informe o CNPJ ou abra o exemplo real completo</h2>
            <button type="button" id="btn-open-sample-dossier" class="btn btn-outline" style="padding:.52rem .95rem;font-size:.86rem;border-color:rgba(0,240,255,.5);color:var(--cyan);">👁️ Ver Exemplo Real de Dossiê (com PDF)</button>
        </div>
        <form id="cnpj-form" novalidate>
            <div class="form-grid">
                <div>
                    <label for="cnpj-input">CNPJ da empresa (14 dígitos)</label>
                    <input
                        id="cnpj-input"
                        name="cnpj"
                        type="text"
                        inputmode="numeric"
                        maxlength="18"
                        class="input"
                        placeholder="00.000.000/0001-91"
                        value="<?php echo htmlspecialchars($initialCnpj, ENT_QUOTES, 'UTF-8'); ?>"
                        autocomplete="off"
                        required
                    />
                </div>
                <div style="display:flex;gap:.55rem;flex-wrap:wrap;">
                    <button type="submit" id="btn-preview" class="btn btn-primary">📄 Ver Exemplo / Estrutura</button>
                    <button type="button" id="btn-direct-full" class="btn btn-pix" style="display:none;">⚡ Emitir Dossiê Completo</button>
                </div>
            </div>
        </form>
        <div class="quick-samples">
            <span>Exemplos rápidos:</span>
            <button type="button" class="chip" data-sample="00000000000191">Banco do Brasil (00.000.000/0001-91)</button>
            <button type="button" class="chip" data-sample="33000167000101">Petrobras (33.000.167/0001-01)</button>
            <button type="button" class="chip" data-sample="60701190000104">Itaú Unibanco (60.701.190/0001-04)</button>
        </div>
    </section>

    <div id="alert-box" class="alert" role="alert"></div>

    <!-- Prévia Grátis HTTP 402 -->
    <section id="preview-card" class="card preview-box no-print" aria-live="polite">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
            <strong style="font-size:1.08rem;color:var(--cyan);">Prévia Gratuita Confirmada (HTTP 402 Teaser)</strong>
            <span style="font-size:.82rem;color:var(--muted);">Custo do relatório completo: <strong>10 créditos</strong> (R$ 0,30 no Starter Pass)</span>
        </div>
        <div id="preview-kv" class="kv-grid"></div>
        <div style="margin-top:1rem;">
            <div style="font-size:.82rem;color:var(--muted);margin-bottom:.35rem;">Campos bloqueados liberados no Dossiê 360º Completo:</div>
            <div id="preview-locked"></div>
        </div>
    </section>

    <!-- Passo 2: Desbloquear via Pix (Micro R$ 4,90 ou Starter R$ 12) ou Session Pass existente -->
    <section id="unlock-grid" class="two-col no-print" aria-label="Opções de liberação do dossiê">
        <div class="card">
            <div style="font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#34D399;font-weight:700;">Sem cadastro · Liberação imediata via Pix</div>
            <h2 style="margin:.25rem 0 .45rem;font-size:1.2rem;">Escolha seu Pacote Pix</h2>
            <p style="color:var(--muted);font-size:.88rem;margin:0 0 .85rem;">
                Teste com <strong>R$ 4,90 (5 dossiês)</strong> ou leve o <strong>Starter por R$ 12,00 (40 dossiês — R$ 0,30 cada, 8x mais créditos)</strong>.
            </p>
            <div id="kyb-tier-selector" role="radiogroup" aria-label="Escolher pacote Pix" style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:.9rem;">
                <button type="button" id="btn-tier-micro" class="btn btn-outline kyb-tier-btn" data-tier="micro" style="flex-direction:column;align-items:flex-start;padding:.75rem;text-align:left;border-color:var(--border);">
                    <span style="font-size:.72rem;text-transform:uppercase;color:#94A3B8;">Entrada · Teste Rápido</span>
                    <strong style="font-size:1.08rem;color:#fff;">R$ 4,90</strong>
                    <span style="font-size:.78rem;color:#CBD5E1;">50 créditos · 5 dossiês (90d)</span>
                </button>
                <button type="button" id="btn-tier-starter" class="btn btn-outline kyb-tier-btn" data-tier="starter" style="flex-direction:column;align-items:flex-start;padding:.75rem;text-align:left;border-color:#10B981;background:rgba(16,185,129,.12);">
                    <span style="font-size:.72rem;text-transform:uppercase;color:#34D399;">Melhor Custo-Benefício</span>
                    <strong style="font-size:1.08rem;color:#fff;">R$ 12,00</strong>
                    <span style="font-size:.78rem;color:#E2E8F0;">400 créditos · 40 dossiês (R$ 0,30/un)</span>
                </button>
            </div>
            <?php if ($isSandboxMp): ?>
            <div style="font-size:.82rem;color:#FCD34D;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.35);border-radius:8px;padding:.55rem .75rem;margin-bottom:.85rem;">
                Modo Sandbox Mercado Pago ativo neste ambiente de homologação.
            </div>
            <?php elseif (!$mpConfigured): ?>
            <div id="kyb-mp-unavailable" style="font-size:.83rem;color:#FCA5A5;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.45);border-radius:8px;padding:.65rem .8rem;margin-bottom:.85rem;">
                Checkout BRL via Mercado Pago está temporariamente indisponível neste ambiente. Utilize <a href="/pass#multichain-web3" style="color:#00F0FF;">USDC Web3</a> ou <a href="<?php echo htmlspecialchars($rapidApiKybUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" style="color:#00F0FF;">Cartão USD no RapidAPI</a>.
            </div>
            <?php endif; ?>
            <label for="pix-email">E-mail para recibo Pix (obrigatório)</label>
            <input type="email" id="pix-email" class="input" style="margin-bottom:.75rem;font-family:'Inter',sans-serif;" placeholder="financeiro@suaempresa.com.br" autocomplete="email" required />
            <button type="button" id="btn-gen-pix" class="btn btn-pix" style="width:100%;" <?php echo !$mpConfigured ? 'disabled' : ''; ?>>⚡ Gerar QR Code Pix (Starter — R$ 12,00 = 40 Dossiês)</button>

            <div id="pix-panel" class="pix-panel" aria-live="polite">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.75rem;">
                    <strong id="pix-status-title" style="color:var(--cyan);">Escaneie o QR Code no app do seu banco</strong>
                    <span id="pix-status-badge" style="font-size:.78rem;padding:.2rem .6rem;border-radius:999px;background:rgba(245,158,11,.15);color:#FCD34D;border:1px solid rgba(245,158,11,.4);">Aguardando Pix...</span>
                </div>
                <div id="pix-qr-content" class="pix-flex">
                    <div class="qr-frame"><img id="pix-qr-img" src="" alt="QR Code Pix para Starter Pass Grid402" /></div>
                    <div style="flex:1;min-width:220px;">
                        <label for="pix-code">Pix Copia e Cola</label>
                        <input type="text" id="pix-code" class="input" readonly style="font-size:.8rem;margin-bottom:.5rem;" />
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                            <button type="button" id="btn-copy-pix" class="btn btn-outline" style="padding:.55rem .9rem;font-size:.85rem;">📋 Copiar Código Pix</button>
                            <button type="button" id="btn-sandbox-approve" class="btn btn-primary" style="display:none;padding:.55rem .9rem;font-size:.85rem;">Aprovar Sandbox</button>
                        </div>
                        <p style="font-size:.8rem;color:var(--muted);margin:.65rem 0 0;">
                            Assim que o Pix for confirmado, seu token <code>pass_g402_...</code> é salvo automaticamente e o Dossiê completo aparece abaixo.
                        </p>
                    </div>
                </div>
                <div id="pix-approved-box" style="display:none;margin-top:.5rem;padding:.75rem .9rem;border-radius:10px;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.4);color:#A7F3D0;font-size:.88rem;"></div>
            </div>
        </div>

        <div class="card">
            <div style="font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--cyan);font-weight:700;">Já possui créditos?</div>
            <h2 style="margin:.25rem 0 .4rem;font-size:1.2rem;">Usar Session Pass Existente</h2>
            <p style="color:var(--muted);font-size:.9rem;margin:0 0 .9rem;">
                Cole seu token <code>pass_g402_...</code> abaixo para emitir o Dossiê 360º instantaneamente (10 créditos por dossiê) e acompanhar seu saldo restante.
            </p>
            <label for="pass-token-input">Token Session Pass</label>
            <input type="text" id="pass-token-input" class="input" placeholder="pass_g402_..." autocomplete="off" style="margin-bottom:.75rem;font-size:.88rem;" />
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
                <button type="button" id="btn-run-with-pass" class="btn btn-primary" style="flex:1;">📄 Emitir Dossiê Completo (10 cr)</button>
                <button type="button" id="btn-check-balance" class="btn btn-outline">Saldo</button>
            </div>
            <div id="pass-balance-info" style="margin-top:.75rem;font-size:.85rem;color:var(--muted);"></div>
            <p style="margin-top:1rem;font-size:.82rem;color:var(--muted);">
                Precisa de mais volume (1.200 ou 6.000 créditos) ou pagar com cartão/USDC? <a href="/pass" style="color:var(--cyan);">Ver planos no /pass →</a>
            </p>
        </div>
    </section>

    <section class="card no-print" aria-label="Garantias Transparentes de Cobrança" style="border-color:rgba(16,185,129,.35);background:rgba(16,185,129,.06);">
        <div style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:#34D399;font-weight:700;margin-bottom:.65rem;">Garantias Transparentes de Cobrança</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:.85rem;font-size:.86rem;color:var(--muted);">
            <div><strong style="color:#F8FAFC;display:block;margin-bottom:.2rem;">Erro não cobra (estorno automático)</strong>Se a Receita ou CGU falhar (HTTP ≠ 200), seus 10 créditos são estornados na hora automaticamente.</div>
            <div><strong style="color:#F8FAFC;display:block;margin-bottom:.2rem;">Retentativa segura (300s)</strong>Integrações via API podem enviar <code>Idempotency-Key</code> para repetir chamadas por 5 minutos sem cobrança duplicada.</div>
            <div><strong style="color:#F8FAFC;display:block;margin-bottom:.2rem;">Leitura clara de créditos</strong><code>1 crédito = R$ 0,03</code> no Starter Pass; cada Dossiê 360º consome <code>10 créditos</code> (R$ 0,30) e 400 créditos rendem 40 dossiês ou 400 consultas padrão.</div>
        </div>
    </section>

    <!-- Passo 3: Relatório Visual Completo + Impressão PDF -->
    <article id="report-section" class="report-wrap" aria-live="polite">
        <div class="print-only-header">
            <div>
                <strong style="font-size:15pt;">Grid402 — Dossiê 360º KYB Corporativo</strong><br>
                <span style="font-size:9pt;color:#475569;">Auditoria Verificável de Dados Públicos (Receita Federal · Simples/MEI · CGU CEIS/CNEP)</span>
            </div>
            <div style="text-align:right;font-size:9pt;color:#475569;">
                <div id="print-generated-at"></div>
                <div>https://grid402.online/dossie-kyb</div>
            </div>
        </div>

        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem;">
            <div id="credits-after-badge" style="font-size:.88rem;color:#6EE7B7;font-weight:600;"></div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
                <button type="button" id="btn-download-pdf" class="btn btn-pix">⬇️ Baixar PDF Oficial (A4)</button>
                <button type="button" id="btn-print-pdf" class="btn btn-outline">🖨️ Imprimir (Navegador)</button>
                <button type="button" id="btn-download-json" class="btn btn-outline">⬇️ Baixar JSON Assinado</button>
            </div>
        </div>

        <div id="verdict-banner" class="verdict-banner verdict-APPROVED">
            <div>
                <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;opacity:.85;font-weight:700;">Veredito Determinístico KYB 360º</div>
                <div id="verdict-label" style="font-family:'Space Grotesk',sans-serif;font-size:1.75rem;font-weight:800;line-height:1.2;">APPROVED</div>
                <div id="verdict-summary" style="font-size:.93rem;margin-top:.25rem;"></div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;opacity:.85;">Score de Conformidade</div>
                <div id="verdict-score" class="score-circle">100/100</div>
            </div>
        </div>

        <div class="card">
            <h2 class="section-h2" style="margin-top:0;">1. Identidade Cadastral (Receita Federal — Zero-PII)</h2>
            <div id="report-cnpj-kv" class="kv-grid"></div>

            <h2 class="section-h2">2. Regime Tributário (Simples Nacional e MEI)</h2>
            <div id="report-simples-kv" class="kv-grid"></div>

            <h2 class="section-h2">3. Sanções Administrativas e Idoneidade (CGU CEIS / CNEP)</h2>
            <div id="report-sanctions-kv" class="kv-grid"></div>
            <div id="report-sanctions-list" style="margin-top:.75rem;"></div>

            <h2 class="section-h2">4. Verificação Criptográfica de Oráculo (_oracle_proof)</h2>
            <div id="report-proof-box" class="proof-code"></div>
            <p style="font-size:.8rem;color:var(--muted);margin:.6rem 0 0;">
                Este relatório processa exclusivamente registros públicos empresariais (LGPD Art. 1º / Lei 12.527/2011 — Zero-PII) e pode ser auditado offline via assinatura ECDSA secp256k1 em <code>POST https://grid402.online/api/v1/verify</code>.
            </p>
        </div>
    </article>

    <section class="trust-strip no-print" aria-label="Garantias do Dossiê Grid402">
        <div class="trust-item">
            <strong>Fontes Oficiais Públicas</strong>
            Consolida Receita Federal, Simples/MEI e CGU (CEIS/CNEP) em chamada única de baixa latência.
        </div>
        <div class="trust-item">
            <strong>Assinatura ECDSA secp256k1</strong>
            Cada dossiê sai com digest SHA-256 e prova matemática auditável contra adulterações.
        </div>
        <div class="trust-item">
            <strong>Zero Mensalidade e Zero-PII</strong>
            Pague apenas pelos créditos usados via Pix. Não expõe CPF de sócios nem exige fidelidade.
        </div>
        <div class="trust-item">
            <strong>Pronto para API, n8n e IA</strong>
            O mesmo token <code>pass_g402_...</code> funciona na web, no n8n, em Python e no servidor MCP.
        </div>
    </section>

    <nav class="seo-crosslinks no-print" aria-label="Outras soluções em português">
        <strong>Guias Técnicos em PT-BR:</strong>
        <a href="/api-cnpj">API de Consulta CNPJ</a>
        <a href="/api-sancoes-cgu">API de Sanções CGU (CEIS/CNEP)</a>
        <a href="/api-dias-uteis-anbima">API de Dias Úteis ANBIMA</a>
        <a href="/pass">Planos Session Pass (Pix / USDC)</a>
    </nav>
</main>

<script src="/assets/js/pass_storage.js?v=3.5.0"></script>
<script>
(function () {
    const STORAGE_KEY = 'grid402_active_pass_token';
    let selectedPixTier = 'starter';
    let lastDossierPayload = null;
    let currentOrderId = null;
    let pollInterval = null;

    const SAMPLE_REAL_DOSSIER = {
        is_sample_demo: true,
        cnpj: '00000000000191',
        verdict: {
            decision: 'APPROVED',
            score: 100,
            summary: 'AMOSTRA ILUSTRATIVA — Empresa com cadastro ATIVO na Receita Federal e Nada Consta nos cadastros federais CGU CEIS/CNEP.'
        },
        cnpj_data: {
            cnpj: '00000000000191',
            razao_social: 'BANCO DO BRASIL SA',
            nome_fantasia: 'DIRECAO GERAL',
            situacao_cadastral: 'ATIVA',
            data_abertura: '1966-08-01',
            capital_social: 120000000000.00,
            cnae_fiscal: '6422100',
            cnae_fiscal_descricao: 'Bancos múltiplos, com carteira comercial',
            logradouro: 'SAUN QUADRA 5 LOTE B TORRE I, II E III',
            numero: 'S/N',
            bairro: 'ASA NORTE',
            municipio: 'BRASILIA',
            uf: 'DF'
        },
        simples: {
            optante_simples: false,
            optante_mei: false,
            data_opcao_simples: '—',
            data_exclusao_simples: '—'
        },
        sanctions: {
            has_sanctions: false,
            total_sanctions: 0,
            cadastros_consultados: ['CEIS', 'CNEP (CGU)'],
            sanctions: []
        },
        _meta: {
            source_authority: 'Receita Federal + CGU CEIS/CNEP (Amostra Ilustrativa Estática)',
            fetched_at: '2026-11-26T18:00:00Z'
        },
        _oracle_proof: {
            oracle_address: '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
            algorithm: 'secp256k1-sha256',
            data_hash: '0xSAMPLE_DEMONSTRATION_HASH_EMITIDO_COM_ASSINATURA_REAL_NO_DOSSIE_PAGO',
            signature: '0xSAMPLE_DEMONSTRATION_ECDSA_SIGNATURE_VALIDAVEL_EM_POST_API_V1_VERIFY'
        }
    };

    const $ = (id) => document.getElementById(id);

    function onlyDigits(v) { return String(v || '').replace(/\D/g, '').slice(0, 14); }
    function formatCnpj(v) {
        const d = onlyDigits(v);
        if (d.length <= 2) return d;
        if (d.length <= 5) return d.slice(0, 2) + '.' + d.slice(2);
        if (d.length <= 8) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
        if (d.length <= 12) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8);
        return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12, 14);
    }

    function showAlert(msg, type = 'err') {
        const el = $('alert-box');
        el.className = 'alert show ' + (type === 'ok' ? 'alert-ok' : 'alert-err');
        el.textContent = msg;
    }
    function hideAlert() { $('alert-box').className = 'alert'; }

    function esc(s) {
        return String(s ?? '—')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderKv(containerId, items) {
        const el = $(containerId);
        el.innerHTML = items.map(([k, v]) =>
            '<div class="kv"><div class="kv-label">' + esc(k) + '</div><div class="kv-val">' + esc(v) + '</div></div>'
        ).join('');
    }

    function getActiveToken() {
        const typed = ($('pass-token-input').value || '').trim();
        if (typed) return typed;
        if (window.Grid402PassStorage) return window.Grid402PassStorage.load();
        try { return (localStorage.getItem(STORAGE_KEY) || localStorage.getItem('grid402_session_pass') || '').trim(); } catch (_) { return ''; }
    }

    function saveToken(token) {
        const clean = (token || '').trim();
        $('pass-token-input').value = clean;
        if (window.Grid402PassStorage && clean) {
            window.Grid402PassStorage.save(clean);
        } else {
            try { if (clean) localStorage.setItem(STORAGE_KEY, clean); } catch (_) {}
        }
        $('btn-direct-full').style.display = clean.startsWith('pass_g402_') ? 'inline-flex' : 'none';
    }

    function selectPixTier(tier) {
        selectedPixTier = (tier === 'micro') ? 'micro' : 'starter';
        document.querySelectorAll('.kyb-tier-btn').forEach((b) => {
            const active = b.getAttribute('data-tier') === selectedPixTier;
            b.style.borderColor = active ? '#10B981' : 'var(--border)';
            b.style.background = active ? 'rgba(16,185,129,.12)' : 'transparent';
        });
        const btn = $('btn-gen-pix');
        if (btn) {
            btn.textContent = selectedPixTier === 'micro'
                ? '⚡ Gerar QR Code Pix (Micro — R$ 4,90 = 5 Dossiês)'
                : '⚡ Gerar QR Code Pix (Starter — R$ 12,00 = 40 Dossiês)';
        }
    }

    function openRealSampleDossier() {
        hideAlert();
        lastDossierPayload = SAMPLE_REAL_DOSSIER;
        renderFullReport(SAMPLE_REAL_DOSSIER, null);
        $('credits-after-badge').textContent = '👁️ AMOSTRA REAL ILUSTRATIVA (Banco do Brasil) — Teste o botão "Baixar PDF Oficial (A4)" ao lado ou desbloqueie seu CNPJ acima por R$ 4,90 ou R$ 12.';
    }

    async function loadPreview(cnpjDigits) {
        if (window.umami) {
            window.umami.track('dossie_search_cnpj', { cnpj: cnpjDigits });
        }
        hideAlert();
        openRealSampleDossier();
        const activeTok = getActiveToken();
        if (activeTok && activeTok.startsWith('pass_g402_')) {
            await fetchFullDossier(cnpjDigits, activeTok);
            return;
        }
        showAlert('Exibindo exemplo real completo do formato do Dossiê 360º KYB abaixo (inclusive pronto para PDF). Para consultar o CNPJ ' + formatCnpj(cnpjDigits) + ' ao vivo, escolha Micro (R$ 4,90 = 5 dossiês) ou Starter (R$ 12 = 40 dossiês) via Pix.', 'ok');
    }

    async function fetchFullDossier(cnpjDigits, token) {
        hideAlert();
        if (!token || !token.startsWith('pass_g402_')) {
            showAlert('Informe um token Session Pass válido (pass_g402_...) ou gere um Pix R$ 12 ao lado.');
            return;
        }
        const btn = $('btn-run-with-pass');
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Gerando Dossiê 360º...';
        try {
            const res = await fetch('/api/v1/dossier?cnpj=' + encodeURIComponent(cnpjDigits), {
                headers: {
                    'Accept': 'application/json',
                    'X-Payment': token
                }
            });
            const data = await res.json();
            if (!res.ok) {
                showAlert('Não foi possível emitir o dossiê: ' + (data.message || data.error || ('HTTP ' + res.status)));
                return;
            }
            saveToken(token);
            lastDossierPayload = data;
            renderFullReport(data, res.headers.get('X-Pass-Credits-Remaining'));
        } catch (_) {
            showAlert('Erro de conexão ao consultar /api/v1/dossier.');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    }

    function renderFullReport(data, headerCreditsRemaining) {
        const verdictObj = data.verdict || {};
        const verdictStatus = String(verdictObj.decision || verdictObj.status || data.ai_verdict || 'APPROVED').toUpperCase();
        const score = verdictObj.score ?? data.compliance_score ?? data.score ?? '—';
        const summaryText = verdictObj.summary || verdictObj.reason || (Array.isArray(verdictObj.reasons) ? verdictObj.reasons.join(' · ') : 'Auditoria corporativa consolidada a partir de fontes públicas oficiais.');

        const banner = $('verdict-banner');
        banner.className = 'verdict-banner verdict-' + (['APPROVED', 'FLAGGED', 'REJECTED'].includes(verdictStatus) ? verdictStatus : 'FLAGGED');
        $('verdict-label').textContent = verdictStatus;
        $('verdict-summary').textContent = summaryText;
        $('verdict-score').textContent = score + '/100';

        const cnpjData = data.cnpj_data || data.company || data.receita_federal || data.registration || {};
        const addressParts = [
            cnpjData.logradouro || cnpjData.endereco?.logradouro,
            cnpjData.numero || cnpjData.endereco?.numero,
            cnpjData.bairro || cnpjData.endereco?.bairro,
            cnpjData.municipio || cnpjData.endereco?.municipio,
            cnpjData.uf || cnpjData.endereco?.uf
        ].filter(Boolean);

        renderKv('report-cnpj-kv', [
            ['CNPJ', formatCnpj(data.cnpj || cnpjData.cnpj || '')],
            ['Razão Social', cnpjData.razao_social || cnpjData.legal_name || '—'],
            ['Nome Fantasia', cnpjData.nome_fantasia || cnpjData.trade_name || '—'],
            ['Situação Cadastral', cnpjData.situacao_cadastral || cnpjData.status || '—'],
            ['Data de Abertura', cnpjData.data_abertura || cnpjData.opening_date || '—'],
            ['Capital Social', cnpjData.capital_social !== undefined ? ('R$ ' + Number(cnpjData.capital_social).toLocaleString('pt-BR', { minimumFractionDigits: 2 })) : '—'],
            ['CNAE Principal', [cnpjData.cnae_fiscal, cnpjData.cnae_fiscal_descricao].filter(Boolean).join(' - ') || '—'],
            ['Endereço Corporativo', addressParts.join(', ') || '—']
        ]);

        const simplesData = data.simples || data.tax_regime || {};
        renderKv('report-simples-kv', [
            ['Optante pelo Simples Nacional', simplesData.optante_simples === true ? 'Sim' : (simplesData.optante_simples === false ? 'Não' : 'Não informado')],
            ['Enquadramento MEI (SIMEI)', simplesData.optante_mei === true ? 'Sim (MEI)' : (simplesData.optante_mei === false ? 'Não' : 'Não informado')],
            ['Data de Opção Simples', simplesData.data_opcao_simples || '—'],
            ['Data de Exclusão Simples', simplesData.data_exclusao_simples || '—']
        ]);

        const sanctionsData = data.sanctions || data.cgu_sanctions || {};
        const totalSanctions = Number(sanctionsData.total_sanctions ?? (Array.isArray(sanctionsData.sanctions) ? sanctionsData.sanctions.length : 0));
        renderKv('report-sanctions-kv', [
            ['Possui Sanções Ativas (CEIS/CNEP)', sanctionsData.has_sanctions ? 'SIM — Atenção' : 'NÃO (Nada Consta)'],
            ['Total de Ocorrências', String(totalSanctions)],
            ['Cadastros Auditados', Array.isArray(sanctionsData.cadastros_consultados) ? sanctionsData.cadastros_consultados.join(', ') : 'CEIS, CNEP (CGU)'],
            ['Política de Privacidade', 'Zero-PII (LGPD Art. 1º)']
        ]);

        const sList = Array.isArray(sanctionsData.sanctions) ? sanctionsData.sanctions : [];
        $('report-sanctions-list').innerHTML = sList.length > 0
            ? '<ul style="margin:0;padding-left:1.25rem;color:#FCA5A5;">' + sList.map((item) => '<li>' + esc(JSON.stringify(item)) + '</li>').join('') + '</ul>'
            : '<div style="font-size:.86rem;color:#6EE7B7;">Nenhuma sanção administrativa ou impedimento de licitar encontrado nos cadastros federais CEIS/CNEP.</div>';

        const proof = data._oracle_proof || {};
        const meta = data._meta || {};
        $('report-proof-box').textContent = [
            'oracle_address : ' + (proof.oracle_address || '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90'),
            'algorithm      : ' + (proof.algorithm || 'secp256k1-sha256'),
            'data_hash      : ' + (proof.data_hash || proof.digest_sha256 || '—'),
            'signature      : ' + (proof.signature || proof.signature_hex || '—'),
            'fetched_at_utc : ' + (meta.fetched_at || data.timestamp || new Date().toISOString()),
            'upstream_source: ' + (meta.source_authority || 'Receita Federal + CGU CEIS/CNEP')
        ].join('\n');

        $('print-generated-at').textContent = 'Emitido em: ' + new Date().toLocaleString('pt-BR');

        const rem = headerCreditsRemaining ?? data._billing?.credits_remaining;
        if (rem !== null && rem !== undefined && rem !== '') {
            const remNum = Number(rem);
            $('credits-after-badge').textContent = '✓ Dossiê emitido · Saldo restante no seu Pass: ' + remNum + ' créditos (~' + Math.floor(remNum / 10) + ' dossiês restantes)';
        } else {
            $('credits-after-badge').textContent = '✓ Dossiê 360º KYB emitido e assinado pelo Oráculo Grid402';
        }

        $('report-section').classList.add('show');
        $('report-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function generatePixSelected() {
        if (window.umami) {
            window.umami.track('dossie_pix_start', { tier: activePixTier });
        }
        hideAlert();
        const emailInput = $('pix-email');
        const email = (emailInput ? emailInput.value : '').trim();
        const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        if (!isValidEmail || email.toLowerCase().endsWith('@grid402.online')) {
            showAlert('Informe um e-mail válido no campo acima para emitir o QR Code Pix.');
            if (emailInput) emailInput.focus();
            return;
        }
        const btn = $('btn-gen-pix');
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Gerando QR Code Pix...';
        try {
            const res = await fetch('/api/v1/pass/mp-pix', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tier: selectedPixTier, email })
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                showAlert(data.message || data.error || 'Falha ao gerar cobrança Pix.');
                return;
            }
            currentOrderId = data.order_id;
            $('pix-panel').classList.add('show');
            if ($('pix-qr-content')) $('pix-qr-content').style.display = '';
            if ($('pix-approved-box')) {
                $('pix-approved-box').style.display = 'none';
                $('pix-approved-box').textContent = '';
            }
            if ($('pix-status-title')) $('pix-status-title').textContent = 'Escaneie o QR Code no app do seu banco';
            if ($('pix-status-badge')) {
                $('pix-status-badge').textContent = 'Aguardando Pix...';
                $('pix-status-badge').style.color = '#FCD34D';
                $('pix-status-badge').style.borderColor = 'rgba(245,158,11,.4)';
            }
            if (data.qr_code_base64) {
                const mime = data.qr_mime || 'image/png';
                $('pix-qr-img').src = 'data:' + mime + ';base64,' + data.qr_code_base64;
            }
            $('pix-code').value = data.qr_code || '';
            if (data.sandbox) {
                $('btn-sandbox-approve').style.display = 'inline-flex';
            }
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(checkPixStatus, 2000);
        } catch (_) {
            showAlert('Erro de rede ao criar cobrança Pix. Tente novamente.');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    }

    async function checkPixStatus() {
        if (!currentOrderId) return;
        try {
            const res = await fetch('/api/v1/pass/mp-status?order_id=' + encodeURIComponent(currentOrderId));
            const data = await res.json();
            if (data.status === 'approved' && data.pass_token) {
                clearInterval(pollInterval);
                pollInterval = null;
                if ($('pix-qr-content')) $('pix-qr-content').style.display = 'none';
                if ($('btn-sandbox-approve')) $('btn-sandbox-approve').style.display = 'none';
                if ($('pix-status-title')) $('pix-status-title').textContent = 'Pagamento confirmado! Créditos liberados';
                $('pix-status-badge').textContent = 'Pix Aprovado!';
                $('pix-status-badge').style.color = '#6EE7B7';
                $('pix-status-badge').style.borderColor = 'rgba(16,185,129,.5)';
                const tierLabels = {
                    micro: 'Micro Pass',
                    starter: 'Starter Pass',
                    pro: 'Pro Pass',
                    fleet: 'Fleet Pass'
                };
                const tierKey = String(data.tier || selectedPixTier || 'starter').toLowerCase();
                const tierLabel = tierLabels[tierKey] || 'Starter Pass';
                const creditsNum = Number(data.credits ?? 0);
                saveToken(data.pass_token);
                if ($('pix-approved-box')) {
                    $('pix-approved-box').textContent = tierLabel + ' ativo com ' + creditsNum + ' créditos liberados (' + data.pass_token + '). Clique em "Emitir Dossiê Completo (10 cr)" ao lado para gerar o relatório.';
                    $('pix-approved-box').style.display = 'block';
                }
                showAlert('Pix confirmado! Seu ' + tierLabel + ' (' + creditsNum + ' créditos) foi salvo: ' + data.pass_token, 'ok');
                const cnpjDigits = onlyDigits($('cnpj-input').value);
                if (cnpjDigits.length === 14) {
                    await fetchFullDossier(cnpjDigits, data.pass_token);
                } else if ($('btn-run-with-pass')) {
                    $('btn-run-with-pass').focus();
                }
            }
        } catch (_) {}
    }

    $('cnpj-input').addEventListener('input', (e) => {
        e.target.value = formatCnpj(e.target.value);
    });

    document.querySelectorAll('.chip[data-sample]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const val = chip.getAttribute('data-sample');
            $('cnpj-input').value = formatCnpj(val);
            loadPreview(val);
        });
    });

    $('cnpj-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const digits = onlyDigits($('cnpj-input').value);
        if (digits.length !== 14) {
            showAlert('Digite um CNPJ completo com 14 dígitos.');
            return;
        }
        loadPreview(digits);
    });

    $('btn-direct-full').addEventListener('click', () => {
        const digits = onlyDigits($('cnpj-input').value);
        if (digits.length !== 14) {
            showAlert('Digite um CNPJ completo com 14 dígitos.');
            return;
        }
        fetchFullDossier(digits, getActiveToken());
    });

    $('btn-run-with-pass').addEventListener('click', () => {
        const digits = onlyDigits($('cnpj-input').value);
        if (digits.length !== 14) {
            showAlert('Digite um CNPJ completo com 14 dígitos no campo acima.');
            return;
        }
        fetchFullDossier(digits, getActiveToken());
    });

    $('btn-check-balance').addEventListener('click', async () => {
        const token = getActiveToken();
        if (!token) {
            $('pass-balance-info').textContent = 'Cole um token pass_g402_... primeiro.';
            return;
        }
        try {
            const res = await fetch('/api/v1/pass/balance?token=' + encodeURIComponent(token));
            const data = await res.json();
            if (data.ok) {
                saveToken(token);
                const cr = Number(data.credits_remaining ?? 0);
                $('pass-balance-info').textContent = '✓ Saldo ativo: ' + cr + ' créditos (~' + Math.floor(cr / 10) + ' dossiês KYB) · Validade: ' + (data.expires_at || 'ativa');
            } else {
                $('pass-balance-info').textContent = 'Token não encontrado ou expirado.';
            }
        } catch (_) {
            $('pass-balance-info').textContent = 'Erro ao consultar saldo.';
        }
    });

    $('btn-gen-pix').addEventListener('click', generatePixSelected);
    if ($('btn-open-sample-dossier')) {
        $('btn-open-sample-dossier').addEventListener('click', openRealSampleDossier);
    }
    document.querySelectorAll('.kyb-tier-btn').forEach((btn) => {
        btn.addEventListener('click', () => selectPixTier(btn.getAttribute('data-tier')));
    });

    $('btn-copy-pix').addEventListener('click', async () => {
        const code = $('pix-code').value;
        if (!code) return;
        try {
            await navigator.clipboard.writeText(code);
            $('btn-copy-pix').textContent = '✓ Copiado!';
            setTimeout(() => { $('btn-copy-pix').textContent = '📋 Copiar Código Pix'; }, 2000);
        } catch (_) {}
    });

    $('btn-sandbox-approve').addEventListener('click', async () => {
        if (!currentOrderId) return;
        await fetch('/api/v1/pass/mp-sandbox-approve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentOrderId })
        });
        await checkPixStatus();
    });

    if ($('btn-download-pdf')) {
        $('btn-download-pdf').addEventListener('click', async () => {
            if (!lastDossierPayload) return;
            const btn = $('btn-download-pdf');
            const orig = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Gerando PDF (A4)...';
            try {
                const res = await fetch('/api/v1/dossier/pdf', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/pdf' },
                    body: JSON.stringify({ dossier: lastDossierPayload })
                });
                if (!res.ok) {
                    let errMsg = 'Falha ao gerar PDF (' + res.status + '). Utilize o botão "Imprimir (Navegador)" ao lado.';
                    try {
                        const errData = await res.json();
                        if (errData && errData.message) errMsg = errData.message + ' Use "Imprimir (Navegador)" como alternativa.';
                    } catch (_) {}
                    showAlert(errMsg);
                    return;
                }
                const blob = await res.blob();
                const cnpjSlug = onlyDigits(lastDossierPayload.cnpj_raw || lastDossierPayload.cnpj || $('cnpj-input').value || 'kyb') || 'kyb';
                const fileName = 'dossie-kyb-' + cnpjSlug + (lastDossierPayload.is_sample_demo ? '-amostra' : '') + '.pdf';
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(blobUrl), 1500);
            } catch (_) {
                showAlert('Erro de conexão ao gerar PDF. Utilize o botão "Imprimir (Navegador)" ao lado.');
            } finally {
                btn.disabled = false;
                btn.textContent = orig;
            }
        });
    }

    $('btn-print-pdf').addEventListener('click', () => window.print());

    $('btn-download-json').addEventListener('click', () => {
        if (!lastDossierPayload) return;
        const blob = new Blob([JSON.stringify(lastDossierPayload, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'grid402_dossie_' + onlyDigits($('cnpj-input').value || 'kyb') + '.json';
        a.click();
        URL.revokeObjectURL(url);
    });

    // Inicialização: restaura Session Pass salvo e formata CNPJ da querystring
    const saved = getActiveToken();
    if (saved) saveToken(saved);
    if ($('cnpj-input').value) {
        const d = onlyDigits($('cnpj-input').value);
        $('cnpj-input').value = formatCnpj(d);
        if (d.length === 14) loadPreview(d);
    }
})();
</script>
</body>
</html>
