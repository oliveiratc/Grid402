<?php
/**
 * Grid402 // View do Cockpit Executivo Administrativo (5 Abas UX + Chart.js v4)
 *
 * Incluido exclusivamente por public_html/admin.php após validação de sessão e CSRF.
 */

declare(strict_types=1);

if (!defined('GRID402_EXEC')) {
    http_response_code(403);
    exit('Acesso direto proibido.');
}

$tvsy = is_array($todayVsYesterday ?? null) ? $todayVsYesterday : [];
$tvsyMetrics = is_array($tvsy['metrics'] ?? null) ? $tvsy['metrics'] : [];
$opHealth = is_array($operationalHealth ?? null) ? $operationalHealth : [];
$alertsList = is_array($alertsBundle['alerts'] ?? null) ? $alertsBundle['alerts'] : [];
$alertsSummary = is_array($alertsBundle['summary'] ?? null) ? $alertsBundle['summary'] : ['high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
$paidServicesMap = is_array($growthMetrics['paid_requests_per_service'] ?? null) ? $growthMetrics['paid_requests_per_service'] : [];

$rapidCount = (int) ($rapidStatus['count'] ?? 0);
$rapidOk = !empty($rapidStatus['configured']);
$tgOk = !empty($tgStatus['configured']);
$tgLast = is_array($tgStatus['last_status'] ?? null) ? $tgStatus['last_status'] : null;
$tgLabel = $tgOk ? 'configurado' : (!empty($tgStatus['disabled']) ? 'desabilitado' : 'não configurado (.env)');
$tgColor = $tgOk ? (($tgLast && empty($tgLast['ok'])) ? '#F59E0B' : '#10B981') : '#EF4444';
$tgSuffix = '';
if ($tgLast) {
    $tgSuffix = !empty($tgLast['ok'])
        ? ' · último HTTP 200'
        : ' · erro: ' . (string) ($tgLast['error'] ?: ('HTTP ' . ($tgLast['http_code'] ?? 0)));
}

$servicesUp = (int) ($opHealth['services_up'] ?? 0);
$servicesTotal = (int) ($opHealth['services_total'] ?? 25);
$uptime30dPct = (float) ($opHealth['uptime_30d_pct'] ?? 100.0);
$slaTargetPct = (float) ($opHealth['sla_target_pct'] ?? 99.5);
$latencyP50Ms = (float) ($opHealth['latency_p50_ms'] ?? ($analytics['latency_p50_ms'] ?? 0.0));
$error5xxCount = (int) ($opHealth['error_5xx_count'] ?? ($analytics['error_5xx_count'] ?? 0));

$passTotalCredits = (int) ($passesStats['total_credits'] ?? 0);
$passRemCredits = (int) ($passesStats['remaining_credits'] ?? 0);
$passUsedCredits = max(0, $passTotalCredits - $passRemCredits);
$passUtilPct = $passTotalCredits > 0 ? round(($passUsedCredits / $passTotalCredits) * 100, 1) : 0.0;

$formatDeltaBadge = static function (?float $pct): array {
    if ($pct === null) {
        return ['text' => '—', 'class' => 'delta-neutral'];
    }
    if ($pct > 0) {
        return ['text' => '+' . number_format($pct, 1, ',', '.') . '%', 'class' => 'delta-up'];
    }
    if ($pct < 0) {
        return ['text' => number_format($pct, 1, ',', '.') . '%', 'class' => 'delta-down'];
    }
    return ['text' => '0,0%', 'class' => 'delta-neutral'];
};

$servicesCatalogOrder = ['dossier', 'extract', 'email-verify', 'lei', 'ofac', 'notarize', 'global-markets', 'pass', 'compliance', 'sanctions', 'simples', 'workdays', 'indicators', 'holidays', 'forex', 'crypto', 'dns', 'ssl', 'asn', 'cep', 'cnpj', 'kyb-audit', 'domain-audit', 'market-snapshot', 'summary'];
$pricingLookup = [
    'dossier' => 0.0500, 'compliance' => 0.0300, 'kyb-audit' => 0.0200, 'ofac' => 0.0200, 'lei' => 0.0200,
    'cnpj' => 0.0200, 'domain-audit' => 0.0150, 'market-snapshot' => 0.0150, 'sanctions' => 0.0100,
    'simples' => 0.0100, 'pass' => 2.0000, 'extract' => 0.0050, 'email-verify' => 0.0050, 'notarize' => 0.0050,
    'forex' => 0.0050, 'crypto' => 0.0050, 'indicators' => 0.0050, 'holidays' => 0.0050, 'workdays' => 0.0050,
    'global-markets' => 0.0050, 'dns' => 0.0050, 'ssl' => 0.0050, 'asn' => 0.0050, 'cep' => 0.0050, 'summary' => 0.0000,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Grid402 // Painel de Telemetria e Faturamento</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" onerror="window.__chartJsLoadFailed=true"></script>
    <style>
        :root {
            --bg: #060B19;
            --surface: #0B132B;
            --surface-alt: #0E1836;
            --surface-hover: #111C38;
            --border: #1E2C4F;
            --border-glow: rgba(0, 240, 255, 0.28);
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --primary: #0052FF;
            --accent: #00F0FF;
            --ok: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --purple: #A855F7;
            --font-heading: 'Space Grotesk', system-ui, sans-serif;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-sans);
            padding: 0 1.25rem;
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.5;
        }
        h1, h2, h3, h4 { font-family: var(--font-heading); letter-spacing: -0.4px; }
        .container { max-width: 1320px; margin: 0 auto; padding: 1.25rem 0 3.5rem; width: 100%; }

        /* Faixa Executiva Persistente */
        .exec-header {
            background: linear-gradient(180deg, rgba(14, 24, 54, 0.95), rgba(11, 19, 43, 0.95));
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.15rem 1.35rem;
            margin-bottom: 1rem;
        }
        .exec-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.9rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .exec-title-block h1 { font-size: 1.35rem; font-weight: 800; color: #fff; margin: 0; }
        .exec-subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
        .exec-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .live-indicator {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: rgba(0, 240, 255, 0.08); border: 1px solid rgba(0, 240, 255, 0.3);
            color: var(--accent); padding: 0.35rem 0.75rem; border-radius: 999px;
            font-family: var(--font-mono); font-size: 0.74rem; font-weight: 600;
        }
        .pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 8px var(--primary); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.35; } 100% { opacity: 1; } }

        .btn-outline {
            background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border); color: var(--text-main);
            padding: 0.42rem 0.8rem; border-radius: 7px; cursor: pointer; font-size: 0.78rem; font-weight: 600;
            text-decoration: none; transition: all 0.18s ease; display: inline-flex; align-items: center; gap: 0.35rem;
            font-family: var(--font-sans);
        }
        .btn-outline:hover { background: var(--surface-hover); border-color: var(--accent); color: #fff; }
        .btn-outline.is-active { background: rgba(0, 240, 255, 0.12); border-color: var(--accent); color: var(--accent); }
        .btn {
            background: linear-gradient(135deg, var(--primary), #0077FF); border: none; color: #fff;
            padding: 0.5rem 1.15rem; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 0.84rem;
        }
        .btn:hover { filter: brightness(1.1); }

        .exec-strip-row {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
            gap: 0.75rem 1.25rem; padding-top: 0.85rem; font-size: 0.8rem;
        }
        .strip-group { display: flex; align-items: center; flex-wrap: wrap; gap: 0.6rem; }
        .strip-pill {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.3rem 0.68rem; border-radius: 8px; background: rgba(3, 7, 18, 0.55);
            border: 1px solid var(--border); font-family: var(--font-mono); font-size: 0.75rem; color: #E2E8F0;
        }
        .strip-pill.ok { border-color: rgba(16, 185, 129, 0.4); color: #34D399; }
        .strip-pill.warn { border-color: rgba(245, 158, 11, 0.45); color: #FBBF24; }
        .strip-pill.danger { border-color: rgba(239, 68, 68, 0.5); color: #FCA5A5; background: rgba(239, 68, 68, 0.1); }
        .alert-pill-btn {
            cursor: pointer; transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .alert-pill-btn:hover { transform: translateY(-1px); border-color: var(--accent); }

        /* Drawer de Configurações Operacionais */
        .settings-drawer {
            display: none;
            background: var(--surface);
            border: 1px solid var(--border-glow);
            border-radius: 12px;
            padding: 1.1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .settings-drawer.is-open { display: block; }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 0.85rem;
            margin-top: 0.85rem;
        }
        .settings-card {
            background: rgba(3, 7, 18, 0.55);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.9rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            justify-content: space-between;
        }
        .settings-card-title { font-size: 0.82rem; font-weight: 700; color: #fff; }
        .settings-card-status { font-size: 0.75rem; font-family: var(--font-mono); line-height: 1.45; }
        .settings-card-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.2rem; }

        /* Barra de 5 Abas */
        .tabs-bar {
            display: flex;
            gap: 0.45rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0.35rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1.25rem;
        }
        .tab-btn {
            appearance: none;
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-muted);
            padding: 0.62rem 1rem;
            border-radius: 8px;
            font-family: var(--font-heading);
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: all 0.16s ease;
        }
        .tab-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.04); }
        .tab-btn[aria-selected="true"] {
            color: #03111C;
            background: linear-gradient(135deg, #00F0FF, #38BDF8);
            border-color: transparent;
            box-shadow: 0 4px 16px rgba(0, 240, 255, 0.25);
        }
        .tab-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .tab-badge {
            font-family: var(--font-mono); font-size: 0.7rem; padding: 0.08rem 0.42rem;
            border-radius: 999px; background: rgba(255, 255, 255, 0.12); color: inherit;
        }
        .tab-panel { display: none; }
        .tab-panel.is-active { display: block; }

        /* Cards e Painéis */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
        }
        .panel-title {
            font-size: 1.04rem; font-weight: 700; margin-bottom: 1rem;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;
        }
        .panel-subtitle { font-size: 0.76rem; color: var(--text-muted); font-weight: 500; font-family: var(--font-sans); }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.15rem 1.25rem;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        .kpi-title {
            font-size: 0.74rem; color: var(--text-muted); text-transform: uppercase;
            font-weight: 700; letter-spacing: 0.5px; margin-bottom: 0.55rem;
            display: flex; justify-content: space-between; align-items: center; gap: 0.4rem;
        }
        .kpi-value { font-size: 1.65rem; font-weight: 800; font-family: var(--font-mono); letter-spacing: -0.5px; }
        .kpi-sub { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; }

        .analytics-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
        .analytics-row > .panel { margin-bottom: 0; }

        /* Quadro Hoje vs Ontem */
        .delta-badge {
            display: inline-block; padding: 0.16rem 0.48rem; border-radius: 999px;
            font-family: var(--font-mono); font-size: 0.72rem; font-weight: 700;
        }
        .delta-up { background: rgba(16, 185, 129, 0.16); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.35); }
        .delta-down { background: rgba(239, 68, 68, 0.15); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.35); }
        .delta-neutral { background: rgba(148, 163, 184, 0.12); color: #CBD5E1; border: 1px solid rgba(148, 163, 184, 0.25); }

        /* Caixa de Pontos de Atenção */
        .alerts-list { display: flex; flex-direction: column; gap: 0.7rem; }
        .alert-item {
            background: rgba(3, 7, 18, 0.55);
            border: 1px solid var(--border);
            border-left-width: 4px;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            display: flex; flex-direction: column; gap: 0.3rem;
        }
        .alert-item.sev-high { border-left-color: var(--danger); background: rgba(239, 68, 68, 0.07); }
        .alert-item.sev-medium { border-left-color: var(--warning); background: rgba(245, 158, 11, 0.06); }
        .alert-item.sev-low { border-left-color: var(--accent); background: rgba(0, 240, 255, 0.04); }
        .alert-item.sev-ok { border-left-color: var(--ok); background: rgba(16, 185, 129, 0.07); }
        .alert-head { display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap; }
        .sev-tag {
            font-family: var(--font-mono); font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
            padding: 0.14rem 0.48rem; border-radius: 4px;
        }
        .sev-tag.high { background: rgba(239, 68, 68, 0.2); color: #FCA5A5; }
        .sev-tag.medium { background: rgba(245, 158, 11, 0.2); color: #FCD34D; }
        .sev-tag.low { background: rgba(0, 240, 255, 0.16); color: #67E8F9; }
        .alert-title { font-weight: 700; font-size: 0.9rem; color: #fff; }
        .alert-detail { font-size: 0.81rem; color: #CBD5E1; }
        .alert-action { font-size: 0.77rem; color: var(--text-muted); }

        /* Chart.js Container */
        .chart-controls { display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .chart-canvas-wrap { position: relative; width: 100%; height: 270px; margin-top: 0.5rem; }
        .chart-fallback {
            display: none; padding: 1rem; border-radius: 8px;
            background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3);
            color: #FCD34D; font-size: 0.82rem; margin-top: 0.5rem;
        }

        /* Matriz de Saúde Operacional */
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.85rem;
        }
        .health-tile {
            background: rgba(3, 7, 18, 0.55);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.85rem 1rem;
        }
        .health-label { font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.4px; }
        .health-val { font-size: 1.15rem; font-weight: 800; font-family: var(--font-mono); margin-top: 0.3rem; }
        .health-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* Barras horizontais */
        .bar-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.58rem; font-size: 0.8rem; }
        .bar-label { width: 112px; font-family: var(--font-mono); color: var(--text-main); }
        .bar-label.wide { width: 170px; font-size: 0.74rem; line-height: 1.25; }
        .bar-track { flex: 1; height: 8px; background: #030712; border-radius: 4px; margin: 0 0.85rem; overflow: hidden; }
        .bar-fill { height: 100%; background: linear-gradient(90deg, #06B6D4, #10B981); border-radius: 4px; transition: width 0.35s ease; }
        .bar-count { width: 72px; text-align: right; font-family: var(--font-mono); color: var(--text-muted); }

        /* Tabelas e Filtros */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid var(--border); background: #030712; width: 100%; }
        table { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 0.8rem; text-align: left; }
        th, td { white-space: nowrap; }
        th {
            background: rgba(255, 255, 255, 0.025); padding: 0.75rem 0.9rem; color: var(--text-muted);
            font-weight: 700; border-bottom: 1px solid var(--border); text-transform: uppercase;
            font-size: 0.7rem; letter-spacing: 0.45px;
        }
        td { padding: 0.68rem 0.9rem; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-family: var(--font-mono); }
        tr:hover td { background: rgba(255, 255, 255, 0.018); }

        .feed-toolbar {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
            gap: 0.75rem; margin-bottom: 1rem; background: rgba(3, 7, 18, 0.6);
            border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.85rem;
        }
        .filter-group { display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .filter-btn {
            background: var(--surface); border: 1px solid var(--border); color: var(--text-muted);
            padding: 0.35rem 0.65rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer;
        }
        .filter-btn:hover { color: #fff; border-color: var(--accent); }
        .filter-btn.active { background: rgba(56, 189, 248, 0.16); border-color: var(--accent); color: #fff; }
        .search-wrap { display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 220px; justify-content: flex-end; }
        .search-input {
            background: #030712; border: 1px solid var(--border); border-radius: 6px; color: #fff;
            font-size: 0.8rem; padding: 0.42rem 0.65rem; font-family: var(--font-sans); width: 100%; max-width: 280px;
        }
        .search-input:focus { outline: none; border-color: var(--accent); }

        .tag-status { display: inline-block; padding: 0.18rem 0.48rem; border-radius: 4px; font-size: 0.72rem; font-weight: 700; }
        .status-200 { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .status-402 { background: rgba(245, 158, 11, 0.15); color: #FBBF24; }
        .status-403, .status-400 { background: rgba(239, 68, 68, 0.15); color: #F87171; }
        .status-429, .status-sandbox { background: rgba(168, 85, 247, 0.16); color: #C084FC; }
        .tag-paid { background: rgba(56, 189, 248, 0.16); color: var(--accent); padding: 0.18rem 0.48rem; border-radius: 4px; font-weight: 700; font-size: 0.72rem; }
        .tag-source { display: inline-block; padding: 0.18rem 0.48rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(56, 189, 248, 0.12); color: #7DD3FC; }
        .tag-failure { display: inline-block; padding: 0.18rem 0.48rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(239, 68, 68, 0.14); color: #FCA5A5; }
        .tag-low-traction { display: inline-block; padding: 0.14rem 0.45rem; border-radius: 4px; font-size: 0.68rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.35); }
        .tag-profile { display: inline-block; padding: 0.18rem 0.48rem; border-radius: 4px; font-size: 0.68rem; font-weight: 600; }
        .profile-paid { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .profile-sandbox { background: rgba(168, 85, 247, 0.15); color: #C084FC; border: 1px solid rgba(168, 85, 247, 0.3); }
        .profile-agent { background: rgba(56, 189, 248, 0.15); color: #38BDF8; border: 1px solid rgba(56, 189, 248, 0.3); }
        .profile-402 { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: rgba(255, 255, 255, 0.08); font-size: 0.7rem; font-weight: 700; color: var(--text-muted); }
        .rank-badge.top-1 { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .rank-badge.top-2 { background: rgba(203, 213, 225, 0.2); color: #CBD5E1; }
        .rank-badge.top-3 { background: rgba(180, 83, 9, 0.2); color: #D97706; }
        .tx-link { color: var(--accent); text-decoration: none; }
        .tx-link:hover { text-decoration: underline; }

        /* Modais */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(3, 7, 18, 0.85); backdrop-filter: blur(6px);
            z-index: 9999; align-items: center; justify-content: center; padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); overflow: hidden;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1.15rem 1.4rem; border-bottom: 1px solid var(--border);
        }
        .modal-title { font-size: 1.05rem; font-weight: 700; color: #fff; }
        .modal-close { background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 0.2rem; }
        .modal-close:hover { color: #fff; }

        @media (max-width: 960px) {
            .analytics-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            body { padding: 0 0.75rem; }
            .exec-header { padding: 1rem; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .feed-toolbar { flex-direction: column; align-items: stretch; }
            .search-wrap, .search-input { max-width: 100%; width: 100%; }
        }
        @media (max-width: 480px) {
            .kpi-grid { grid-template-columns: 1fr; }
            .bar-label { width: 85px; }
            .bar-label.wide { width: 110px; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/admin_nav.php'; grid402_render_admin_nav(['active' => 'overview', 'logout_url' => '/admin?action=logout']); ?>

    <div class="container">
        <?php if ((string)($_GET['recovery'] ?? '') === 'done'): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #10B981; padding: 0.85rem 1.2rem; border-radius: 10px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem;">
                <span>✅</span> <strong>Senha redefinida com sucesso via Telegram!</strong> Suas novas credenciais já estão ativas.
            </div>
        <?php endif; ?>

        <!-- Faixa Executiva Persistente -->
        <section class="exec-header" aria-label="Resumo executivo do sistema">
            <div class="exec-top-row">
                <div class="exec-title-block">
                    <h1>Monitoramento e Faturamento</h1>
                    <div class="exec-subtitle">Cockpit Executivo Grid402 · Operação, Audiência e Receita M2M em tempo real</div>
                </div>
                <div class="exec-actions">
                    <div class="live-indicator" title="Atualização automática em segundo plano">
                        <span class="pulse-dot"></span>
                        <span id="live-timer-text">Ao Vivo (5s)</span>
                    </div>
                    <a href="/admin?api=export_csv" class="btn-outline" title="Baixar relatório financeiro e telemetria CSV">📥 Exportar CSV</a>
                    <button type="button" id="btn-toggle-settings" class="btn-outline" aria-expanded="false" aria-controls="settings-drawer" onclick="toggleSettingsDrawer()">⚙️ Configurações</button>
                    <button type="button" class="btn-outline" onclick="fetchLiveStats(true); fetchGrowthAdmin(true);">🔄 Atualizar</button>
                </div>
            </div>
            <div class="exec-strip-row">
                <div class="strip-group">
                    <span id="header-services-pill" class="strip-pill <?php echo ($servicesUp >= $servicesTotal && $servicesTotal > 0) ? 'ok' : 'warn'; ?>">
                        🟢 <strong><?php echo $servicesUp . '/' . $servicesTotal; ?> UP</strong> · Uptime 30d: <?php echo number_format($uptime30dPct, 1, ',', '.'); ?>%
                    </span>
                    <span id="header-today-pill" class="strip-pill">
                        Hoje (<?php echo htmlspecialchars((string) ($tvsy['today_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>):
                        <strong><?php echo number_format((int) ($tvsyMetrics['requests']['today'] ?? 0), 0, ',', '.'); ?></strong> reqs ·
                        <strong><?php echo number_format((int) ($tvsyMetrics['page_views']['today'] ?? 0), 0, ',', '.'); ?></strong> views ·
                        <strong><?php echo (int) ($tvsyMetrics['paid']['today'] ?? 0); ?></strong> pagas
                    </span>
                </div>
                <div class="strip-group" id="header-alerts-strip">
                    <button type="button" id="header-alert-high" class="strip-pill alert-pill-btn <?php echo ((int)$alertsSummary['high'] > 0) ? 'danger' : 'ok'; ?>" onclick="activateTab('tab-resumo')">
                        Alta: <strong><?php echo (int) $alertsSummary['high']; ?></strong>
                    </button>
                    <button type="button" id="header-alert-medium" class="strip-pill alert-pill-btn <?php echo ((int)$alertsSummary['medium'] > 0) ? 'warn' : ''; ?>" onclick="activateTab('tab-resumo')">
                        Média: <strong><?php echo (int) $alertsSummary['medium']; ?></strong>
                    </button>
                    <button type="button" id="header-alert-low" class="strip-pill alert-pill-btn" onclick="activateTab('tab-resumo')">
                        Baixa: <strong><?php echo (int) $alertsSummary['low']; ?></strong>
                    </button>
                </div>
            </div>
        </section>

        <!-- Drawer / Painel de Configurações Operacionais (preserva todos os badges e modais) -->
        <section id="settings-drawer" class="settings-drawer" aria-label="Configurações e Integrações">
            <div class="panel-title" style="margin-bottom: 0;">
                <span>⚙️ Configurações Operacionais e Segurança</span>
                <button type="button" class="btn-outline" onclick="toggleSettingsDrawer(false)">Fechar ✕</button>
            </div>
            <div class="settings-grid">
                <div class="settings-card">
                    <div>
                        <div class="settings-card-title">Facilitador x402 &amp; Coletor</div>
                        <div class="settings-card-status" style="margin-top: 0.35rem;">
                            <div id="runtime-facilitator-badge" style="color:<?php echo ($facStatus['mode'] === 'settle' ? '#10B981' : '#F59E0B'); ?>;">
                                Facilitador x402: <strong><?php echo htmlspecialchars((string)$facStatus['mode'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                (<?php echo htmlspecialchars(($facStatus['host'] !== '' ? $facStatus['host'] : 'local') . ' · ' . $facStatus['auth_mode'] . ' · ' . (int)($facStatus['multichain_accepts'] ?? 4) . ' chains', ENT_QUOTES, 'UTF-8'); ?>)
                            </div>
                            <div id="runtime-collector-badge" style="margin-top: 0.25rem; color:<?php echo (!$colStatus['summary_stale'] ? '#10B981' : '#F59E0B'); ?>;">
                                Coletor (/status): <strong><?php
                                    if ($colStatus['summary_age_sec'] === null) {
                                        echo 'ausente';
                                    } elseif ($colStatus['summary_age_sec'] < 120) {
                                        echo $colStatus['summary_age_sec'] . 's atrás';
                                    } else {
                                        echo round($colStatus['summary_age_sec'] / 60) . ' min atrás';
                                    }
                                ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-card">
                    <div>
                        <div class="settings-card-title">RapidAPI Bridge</div>
                        <div class="settings-card-status" style="margin-top: 0.35rem;">
                            <span id="runtime-rapidapi-badge" style="color:<?php echo ($rapidOk ? '#10B981' : '#94A3B8'); ?>;">
                                RapidAPI Bridge: <strong><?php echo $rapidOk ? ($rapidCount > 1 ? 'ativo (' . $rapidCount . ' APIs)' : 'ativo') : 'inativo'; ?></strong>
                            </span>
                        </div>
                    </div>
                    <div class="settings-card-actions">
                        <button type="button" id="btn-config-rapidapi" class="btn-outline" onclick="openRapidApiModal()">⚡ Configurar RapidAPI</button>
                    </div>
                </div>

                <div class="settings-card">
                    <div>
                        <div class="settings-card-title">Alertas Telegram (Severidade Alta + 2FA)</div>
                        <div class="settings-card-status" style="margin-top: 0.35rem;">
                            <span id="runtime-telegram-badge" style="color:<?php echo $tgColor; ?>;">
                                Telegram: <strong><?php echo htmlspecialchars($tgLabel, ENT_QUOTES, 'UTF-8'); ?></strong><?php echo htmlspecialchars($tgSuffix, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="settings-card-actions">
                        <button type="button" id="btn-config-telegram" class="btn-outline" onclick="openTelegramModal()">⚙️ Configurar Telegram</button>
                        <button type="button" id="btn-test-telegram" class="btn-outline" onclick="triggerTelegramTest()">🔔 Testar Telegram</button>
                    </div>
                </div>

                <div class="settings-card">
                    <div>
                        <div class="settings-card-title">Segurança da Conta e Sessão</div>
                        <div class="settings-card-status" style="margin-top: 0.35rem;">
                            <span id="runtime-security-badge" style="color:<?php echo $twoFactorActive ? '#10B981' : '#38BDF8'; ?>;">
                                Sessão: <strong id="runtime-security-text"><?php echo $twoFactorActive ? '2FA Telegram ativo' : ($twoFactorEnabled ? '2FA aguardando bot' : '2FA desativado'); ?> · 30m timeout · IP/UA vinculado</strong>
                            </span>
                        </div>
                    </div>
                    <div class="settings-card-actions">
                        <button type="button" class="btn-outline" onclick="openPasswordModal()">🔑 Alterar Senha / 2FA</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Barra de 5 Abas -->
        <div class="tabs-bar" role="tablist" aria-label="Seções do Cockpit Administrativo">
            <button type="button" role="tab" id="btn-tab-resumo" class="tab-btn" aria-selected="true" aria-controls="tab-resumo" data-tab="tab-resumo">📊 Resumo Executivo</button>
            <button type="button" role="tab" id="btn-tab-audiencia" class="tab-btn" aria-selected="false" aria-controls="tab-audiencia" data-tab="tab-audiencia" tabindex="-1">📈 Audiência e Funil</button>
            <button type="button" role="tab" id="btn-tab-receita" class="tab-btn" aria-selected="false" aria-controls="tab-receita" data-tab="tab-receita" tabindex="-1">💰 Receita e Passes</button>
            <button type="button" role="tab" id="btn-tab-saude" class="tab-btn" aria-selected="false" aria-controls="tab-saude" data-tab="tab-saude" tabindex="-1">🩺 Saúde e Alertas <span id="tab-badge-alerts" class="tab-badge"><?php echo (int) $alertsSummary['total']; ?></span></button>
            <button type="button" role="tab" id="btn-tab-eventos" class="tab-btn" aria-selected="false" aria-controls="tab-eventos" data-tab="tab-eventos" tabindex="-1">⚡ Eventos ao Vivo</button>
        </div>

        <!-- ===================================================================== -->
        <!-- ABA 1: RESUMO EXECUTIVO                                               -->
        <!-- ===================================================================== -->
        <section id="tab-resumo" class="tab-panel is-active" role="tabpanel" aria-labelledby="btn-tab-resumo">
            <div id="overview" class="panel">
                <div class="panel-title">
                    <span>Comparativo Hoje vs. Ontem (Fuso BRT)</span>
                    <span class="panel-subtitle" id="tvsy-dates-label">
                        Hoje: <?php echo htmlspecialchars((string) ($tvsy['today_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (parcial) vs. Ontem: <?php echo htmlspecialchars((string) ($tvsy['yesterday_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="table-wrap">
                    <table style="min-width: 580px;">
                        <thead>
                            <tr>
                                <th>Indicador</th>
                                <th>Hoje (parcial)</th>
                                <th>Ontem</th>
                                <th>Variação</th>
                                <th>Acumulado Total</th>
                            </tr>
                        </thead>
                        <tbody id="tvsy-tbody">
                            <?php
                            $tvsyRows = [
                                ['key' => 'requests', 'label' => 'Requisições Totais', 'fmt' => 'int'],
                                ['key' => 'page_views', 'label' => 'Page Views (HTML)', 'fmt' => 'int'],
                                ['key' => 'paid', 'label' => 'Consultas Pagas', 'fmt' => 'int'],
                                ['key' => 'revenue_usdc', 'label' => 'Receita (USDC)', 'fmt' => 'usdc'],
                                ['key' => 'revenue_brl', 'label' => 'Receita Estimada (BRL)', 'fmt' => 'brl'],
                                ['key' => 'new_wallets', 'label' => 'Novas Wallets Conectadas', 'fmt' => 'int'],
                            ];
                            foreach ($tvsyRows as $rowSpec):
                                $mRow = $tvsyMetrics[$rowSpec['key']] ?? ['today' => 0, 'yesterday' => 0, 'total' => 0, 'delta_pct' => null];
                                $fmtVal = static function ($v, string $fmt): string {
                                    if ($fmt === 'usdc') return 'US$ ' . number_format((float) $v, 4, ',', '.');
                                    if ($fmt === 'brl') return 'R$ ' . number_format((float) $v, 2, ',', '.');
                                    return number_format((int) $v, 0, ',', '.');
                                };
                                $badge = $formatDeltaBadge($mRow['delta_pct'] ?? null);
                            ?>
                            <tr>
                                <td style="font-family: var(--font-sans); font-weight: 600; color: #fff;"><?php echo $rowSpec['label']; ?></td>
                                <td><strong><?php echo $fmtVal($mRow['today'] ?? 0, $rowSpec['fmt']); ?></strong></td>
                                <td style="color: var(--text-muted);"><?php echo $fmtVal($mRow['yesterday'] ?? 0, $rowSpec['fmt']); ?></td>
                                <td><span class="delta-badge <?php echo $badge['class']; ?>"><?php echo $badge['text']; ?></span></td>
                                <td style="color: var(--accent);"><?php echo $fmtVal($mRow['total'] ?? 0, $rowSpec['fmt']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Caixa de Pontos de Atenção -->
            <div id="alerts-panel" class="panel">
                <div class="panel-title">
                    <span>Pontos de Atenção Automáticos</span>
                    <span class="panel-subtitle">Severidade Alta notifica automaticamente no Telegram (cooldown 60 min)</span>
                </div>
                <div id="alerts-container" class="alerts-list">
                    <?php if (empty($alertsList)): ?>
                        <div class="alert-item sev-ok">
                            <div class="alert-head"><span class="alert-title">✅ Nenhum ponto de atenção ativo</span></div>
                            <div class="alert-detail">Todos os indicadores operacionais e de conversão estão dentro dos limiares saudáveis.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alertsList as $al):
                            $sev = (string) ($al['severity'] ?? 'low');
                            $sevLabel = $sev === 'high' ? 'ALTA' : ($sev === 'medium' ? 'MÉDIA' : 'BAIXA');
                        ?>
                        <div class="alert-item sev-<?php echo htmlspecialchars($sev, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="alert-head">
                                <span class="sev-tag <?php echo htmlspecialchars($sev, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $sevLabel; ?></span>
                                <span class="alert-title"><?php echo htmlspecialchars((string) ($al['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="alert-detail"><?php echo htmlspecialchars((string) ($al['detail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="alert-action">→ <?php echo htmlspecialchars((string) ($al['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gráfico Interativo Chart.js v4 + Tabela 14 Dias -->
            <div class="panel">
                <div class="panel-title">
                    <span>Evolução Diária (Últimos 14 Dias)</span>
                    <div class="chart-controls" role="group" aria-label="Alternar série do gráfico 14 dias">
                        <button type="button" class="filter-btn active" data-chart-metric="requests" onclick="setChartMetric('requests')">Requisições</button>
                        <button type="button" class="filter-btn" data-chart-metric="page_views" onclick="setChartMetric('page_views')">Page Views</button>
                        <button type="button" class="filter-btn" data-chart-metric="paid" onclick="setChartMetric('paid')">Consultas Pagas</button>
                    </div>
                </div>
                <div class="chart-canvas-wrap" id="chart-14d-wrap">
                    <canvas id="chart-14d-canvas" aria-label="Gráfico temporal dos últimos 14 dias"></canvas>
                </div>
                <div id="chart-14d-fallback" class="chart-fallback">
                    ⚠️ Gráfico interativo Chart.js indisponível no momento (CDN offline ou bloqueada). Consulte a tabela diária completa abaixo.
                </div>

                <div class="table-wrap" style="margin-top: 1rem; max-height: 310px; overflow-y: auto;">
                    <table style="min-width: 540px;">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Requisições</th>
                                <th>Page Views</th>
                                <th>Pagas</th>
                                <th>Receita (USDC)</th>
                            </tr>
                        </thead>
                        <tbody id="daily-history-14d-tbody">
                            <?php foreach (array_reverse($dailyHistory14d) as $dRow): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) ($dRow['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong> <span style="color: var(--text-muted); font-size: 0.72rem;">(<?php echo htmlspecialchars((string) ($dRow['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</span></td>
                                <td><?php echo number_format((int) ($dRow['requests'] ?? 0), 0, ',', '.'); ?></td>
                                <td><?php echo number_format((int) ($dRow['page_views'] ?? 0), 0, ',', '.'); ?></td>
                                <td style="color: <?php echo ((int) ($dRow['paid'] ?? 0) > 0) ? '#34D399' : 'var(--text-muted)'; ?>;"><?php echo (int) ($dRow['paid'] ?? 0); ?></td>
                                <td>US$ <?php echo number_format((float) ($dRow['usdc'] ?? 0.0), 4, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===================================================================== -->
        <!-- ABA 2: AUDIÊNCIA E FUNIL (GROWTH CONSOLIDADO)                         -->
        <!-- ===================================================================== -->
        <section id="tab-audiencia" class="tab-panel" role="tabpanel" aria-labelledby="btn-tab-audiencia">
            <div class="panel">
                <div class="panel-title">
                    <span>Growth Engine e Funil de Conversão</span>
                    <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                        <a href="/growth_admin" class="btn-outline">⚙️ Configurar / Aprovar Rewards</a>
                        <a href="/growth" class="btn-outline" target="_blank" rel="noopener">Programa Público ↗</a>
                    </div>
                </div>
                <div class="kpi-grid" id="growth-kpi-grid" style="margin-bottom: 0;">
                    <div class="kpi-card">
                        <div class="kpi-title">Visitas HTML (Page Views)</div>
                        <div class="kpi-value" id="kpi-total-visits"><?php echo number_format($totalPageViews, 0, ',', '.'); ?></div>
                        <div class="kpi-sub" id="kpi-today-visits"><?php echo number_format($todayPageViews, 0, ',', '.'); ?> hoje • HTML público</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Consultas Totais</div>
                        <div class="kpi-value" id="kpi-total-reqs"><?php echo number_format($totalReqs, 0, ',', '.'); ?></div>
                        <div class="kpi-sub" id="kpi-paid-reqs"><?php echo number_format($paidReqs, 0, ',', '.'); ?> pagas • <?php echo number_format($sandboxReqs, 0, ',', '.'); ?> sandbox</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Wallets Conectadas</div>
                        <div class="kpi-value" id="growth-wallets"><?php echo number_format((int) ($growthMetrics['wallets_connected'] ?? 0), 0, ',', '.'); ?></div>
                        <div class="kpi-sub" id="growth-paying-sub"><?php echo (int) ($growthMetrics['wallets_paying'] ?? 0); ?> pagantes • retenção <?php echo number_format((float) ($growthMetrics['wallet_retention'] ?? 0.0), 1, ',', '.'); ?>%</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Conversão 1ª Compra</div>
                        <div class="kpi-value" id="growth-conversion"><?php echo number_format((float) ($growthMetrics['conversion_first_payment'] ?? 0.0), 1); ?>%</div>
                        <div class="kpi-sub">Wallets → 1ª consulta paga</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Conversão 2ª Compra</div>
                        <div class="kpi-value" id="growth-conversion-2"><?php echo number_format((float) ($growthMetrics['conversion_second_payment'] ?? 0.0), 1); ?>%</div>
                        <div class="kpi-sub">Pagantes → recorrentes</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Receita / Wallet</div>
                        <div class="kpi-value" id="growth-revenue-wallet">US$ <?php echo number_format((float) ($growthMetrics['revenue_per_wallet_usdc'] ?? 0.0), 4); ?></div>
                        <div class="kpi-sub">Builders: <span id="growth-builders"><?php echo (int) ($growthMetrics['builders_registered'] ?? 0); ?></span> · Referrals: <span id="growth-referrals"><?php echo (int) ($growthMetrics['referrals_registered'] ?? 0); ?></span> · Promo: <span id="growth-promo-credits"><?php echo (int) ($growthMetrics['promo_credits_total'] ?? 0); ?></span></div>
                    </div>
                </div>
            </div>

            <div class="analytics-row">
                <div class="panel">
                    <div class="panel-title">
                        <span>Serviços Pagos Reais vs. Volume Total (25 Serviços)</span>
                        <span class="panel-subtitle">Destaque para baixa tração (&lt; 30 reqs)</span>
                    </div>
                    <div class="table-wrap" style="max-height: 440px; overflow-y: auto;">
                        <table style="min-width: 480px;">
                            <thead>
                                <tr>
                                    <th>Serviço</th>
                                    <th>Pagas (Real)</th>
                                    <th>Volume Total</th>
                                    <th>Preço</th>
                                    <th>Sinal</th>
                                </tr>
                            </thead>
                            <tbody id="paid-vs-total-services-tbody">
                                <?php
                                $serviceCounts = $metrics['service_counts'] ?? [];
                                $serviceRowsCombined = [];
                                foreach ($servicesCatalogOrder as $svcKey) {
                                    $serviceRowsCombined[] = [
                                        'service' => $svcKey,
                                        'paid' => (int) ($paidServicesMap[$svcKey] ?? 0),
                                        'total' => (int) ($serviceCounts[$svcKey] ?? 0),
                                        'price' => (float) ($pricingLookup[$svcKey] ?? 0.0050),
                                    ];
                                }
                                usort($serviceRowsCombined, static fn($a, $b) => ($b['paid'] <=> $a['paid']) ?: ($b['total'] <=> $a['total']));
                                foreach ($serviceRowsCombined as $srRow):
                                    $isLow = ($srRow['service'] !== 'summary' && $srRow['total'] < 30);
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($srRow['service'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td style="color: <?php echo $srRow['paid'] > 0 ? '#34D399' : 'var(--text-muted)'; ?>;"><strong><?php echo $srRow['paid']; ?></strong></td>
                                    <td><?php echo number_format($srRow['total'], 0, ',', '.'); ?></td>
                                    <td style="color: var(--text-muted);">$<?php echo number_format($srRow['price'], 4); ?></td>
                                    <td><?php echo $isLow ? '<span class="tag-low-traction">Baixa tração (&lt;30)</span>' : '<span style="color: var(--ok); font-size: 0.72rem;">Ativo</span>'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-title">
                        <span>Top Agentes / IPs Consumidores (LGPD)</span>
                        <span class="panel-subtitle">Últimas 100 consultas</span>
                    </div>
                    <div class="table-wrap" style="max-height: 440px; overflow-y: auto;">
                        <table style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 32px;">#</th>
                                    <th>IP Mascarado</th>
                                    <th>Total</th>
                                    <th>Perfil</th>
                                    <th>Fav</th>
                                </tr>
                            </thead>
                            <tbody id="top-consumers-tbody">
                                <?php if (empty($topConsumers)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Aguardando eventos no buffer.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($topConsumers as $c):
                                        $pCls = 'profile-agent';
                                        if ($c['paid'] > 0) $pCls = 'profile-paid';
                                        elseif ($c['sandbox'] > 0) $pCls = 'profile-sandbox';
                                        elseif ($c['challenges'] > 5) $pCls = 'profile-402';
                                        $rkCls = ($c['rank'] <= 3) ? 'top-' . $c['rank'] : '';
                                    ?>
                                    <tr>
                                        <td><span class="rank-badge <?php echo $rkCls; ?>"><?php echo (int) $c['rank']; ?></span></td>
                                        <td><code><?php echo htmlspecialchars((string) $c['ip_masked'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td><strong><?php echo (int) $c['total']; ?></strong> <span style="font-size: 0.7rem; color: var(--text-muted);">(<?php echo (int) $c['paid']; ?>p)</span></td>
                                        <td><span class="tag-profile <?php echo $pCls; ?>"><?php echo htmlspecialchars((string) $c['profile'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><code style="color: var(--accent);"><?php echo htmlspecialchars((string) $c['favorite_service'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title">Volume Acumulado por Micro-Serviço (25 Serviços)</div>
                <div id="service-bars-container">
                    <?php
                    $maxSvc = max(array_values($serviceCounts) ?: [1]);
                    if ($maxSvc <= 0) $maxSvc = 1;
                    foreach ($servicesCatalogOrder as $s):
                        $cnt = (int) ($serviceCounts[$s] ?? 0);
                        $pct = round(($cnt / $maxSvc) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                        <span class="bar-count"><?php echo $cnt; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ===================================================================== -->
        <!-- ABA 3: RECEITA E PASSES                                               -->
        <!-- ===================================================================== -->
        <section id="tab-receita" class="tab-panel" role="tabpanel" aria-labelledby="btn-tab-receita">
            <div id="passes" class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Faturamento Total</div>
                    <div class="kpi-value" id="kpi-total-usdc">US$ <?php echo number_format($revenueUsdc, 4); ?></div>
                    <div class="kpi-sub" id="kpi-total-brl">≈ R$ <?php echo number_format($revenueBrl, 2, ',', '.'); ?> (Base Mainnet)</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Faturamento Hoje</div>
                    <div class="kpi-value" id="kpi-today-usdc">US$ <?php echo number_format($todayUsdc, 4); ?></div>
                    <div class="kpi-sub" id="kpi-today-brl">≈ R$ <?php echo number_format($todayBrl, 2, ',', '.'); ?> hoje</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Ticket Médio (ARPU)</div>
                    <div class="kpi-value" id="kpi-arpu-usdc">US$ <?php echo number_format($arpuUsdc, 4); ?></div>
                    <div class="kpi-sub" id="kpi-arpu-brl">≈ R$ <?php echo number_format($arpuBrl, 4, ',', '.'); ?> / consulta paga</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Taxa de Conversão</div>
                    <div class="kpi-value" id="kpi-conversion"><?php echo $conversionRate; ?>%</div>
                    <div class="kpi-sub">Pagos / Desafios 402 · Sandbox: <strong id="kpi-sandbox-rate" style="color: #C084FC;"><?php echo $sandboxRate; ?>%</strong></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">
                        <span>Saldo On-Chain (Base)</span>
                        <span id="kpi-rpc-status" style="font-size: 0.68rem; font-family: var(--font-mono); color: <?php echo !empty($wallet['rpc_operational']) ? '#10B981' : '#F59E0B'; ?>;"><?php echo !empty($wallet['rpc_operational']) ? 'RPC ATIVO' : 'CACHE OFFLINE'; ?></span>
                    </div>
                    <div class="kpi-value" id="kpi-wallet-usdc">US$ <?php echo number_format((float)($wallet['usdc_balance'] ?? 0.0), 4); ?></div>
                    <div class="kpi-sub" id="kpi-wallet-eth">Reserva Gas: <?php echo number_format((float)($wallet['eth_balance'] ?? 0.0), 5); ?> ETH</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Session Passes Ativos</div>
                    <div class="kpi-value" id="kpi-passes-active"><?php echo (int)($passesStats['active_passes'] ?? 0); ?> <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">/ <?php echo (int)($passesStats['total_issued'] ?? 0); ?> emitidos</span></div>
                    <div class="kpi-sub" id="kpi-wallet-passes"><?php echo (int)($passesStats['active_passes'] ?? 0); ?> passes ativos • <?php echo number_format($passRemCredits, 0, ',', '.'); ?> créditos (<?php echo number_format($passUtilPct, 1, ',', '.'); ?>% usados)</div>
                </div>
            </div>

            <div class="analytics-row">
                <div class="panel">
                    <div class="panel-title">
                        <span>Origem dos Pagamentos</span>
                        <a href="/admin?api=export_csv&amp;scope=paid" class="btn-outline" style="font-size: 0.72rem; padding: 0.28rem 0.6rem;">📥 CSV Pagas (Histórico)</a>
                    </div>
                    <div id="payment-source-bars-container">
                        <?php
                        $sourceCounts = $metrics['payment_sources'] ?? [];
                        $maxSource = max(array_values(array_map('intval', $sourceCounts)) ?: [0]);
                        if ($maxSource <= 0) $maxSource = 1;
                        $totalSource = array_sum(array_map('intval', $sourceCounts));
                        foreach ($paymentSourceLabels as $srcKey => $srcLabel):
                            $cnt = (int) ($sourceCounts[$srcKey] ?? 0);
                            if ($cnt === 0 && $srcKey === 'unknown') continue;
                            $widthPct = round(($cnt / $maxSource) * 100);
                            $sharePct = $totalSource > 0 ? round(($cnt / $totalSource) * 100) : 0;
                        ?>
                        <div class="bar-row" data-source-key="<?php echo htmlspecialchars($srcKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="bar-label wide"><?php echo htmlspecialchars($srcLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width: <?php echo $widthPct; ?>%;"></div></div>
                            <span class="bar-count"><?php echo $cnt; ?> (<?php echo $sharePct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-title">
                        <span>Rentabilidade Estimada por Serviço (USDC)</span>
                        <span class="panel-subtitle">Volume × Preço Oficial (Top 10)</span>
                    </div>
                    <div id="service-revenue-bars-container">
                        <?php
                        $maxRev = 0.0001;
                        foreach ($serviceRevenue as $sr) {
                            if ((float) $sr['estimated_usdc'] > $maxRev) $maxRev = (float) $sr['estimated_usdc'];
                        }
                        foreach (array_slice($serviceRevenue, 0, 10) as $sr):
                            $revVal = (float) $sr['estimated_usdc'];
                            $revPct = round(($revVal / $maxRev) * 100);
                        ?>
                        <div class="bar-row">
                            <span class="bar-label wide"><?php echo htmlspecialchars((string) $sr['service'], ENT_QUOTES, 'UTF-8'); ?> <small style="color: var(--text-muted);">($<?php echo $sr['unit_price']; ?>)</small></span>
                            <div class="bar-track"><div class="bar-fill" style="width: <?php echo $revPct; ?>%; background: linear-gradient(90deg, #10B981, #00F0FF);"></div></div>
                            <span class="bar-count">$<?php echo number_format($revVal, 4); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================== -->
        <!-- ABA 4: SAÚDE E ALERTAS                                                -->
        <!-- ===================================================================== -->
        <section id="tab-saude" class="tab-panel" role="tabpanel" aria-labelledby="btn-tab-saude">
            <div class="panel">
                <div class="panel-title">
                    <span>Matriz de Saúde Operacional Consolidada</span>
                    <span class="panel-subtitle">SLA alvo: <?php echo number_format($slaTargetPct, 1, ',', '.'); ?>% (Best-Effort)</span>
                </div>
                <div class="health-grid">
                    <div class="health-tile">
                        <div class="health-label">Serviços Operantes</div>
                        <div class="health-val" id="health-services-up" style="color: <?php echo ($servicesUp >= $servicesTotal && $servicesTotal > 0) ? '#10B981' : '#F59E0B'; ?>;"><?php echo $servicesUp . '/' . $servicesTotal; ?> UP</div>
                        <div class="health-sub" id="health-uptime-30d">Uptime 30d: <?php echo number_format($uptime30dPct, 2, ',', '.'); ?>%</div>
                    </div>
                    <div class="health-tile">
                        <div class="health-label">SLA / Sucesso Técnico</div>
                        <div class="health-val" id="kpi-sla-tech" style="color: <?php echo $error5xxCount === 0 ? '#10B981' : '#EF4444'; ?>;"><?php echo $technicalSuccessRate; ?>%</div>
                        <div class="health-sub" id="kpi-latency-p95">Sem erros 5xx · Latência p95: <?php echo number_format($latencyP95Ms, 1, ',', '.'); ?> ms</div>
                    </div>
                    <div class="health-tile">
                        <div class="health-label">Latência p50 / p95</div>
                        <div class="health-val" id="health-latencies"><?php echo number_format($latencyP50Ms, 1, ',', '.'); ?> / <?php echo number_format($latencyP95Ms, 1, ',', '.'); ?> ms</div>
                        <div class="health-sub">Janela circular (até 200 amostras)</div>
                    </div>
                    <div class="health-tile">
                        <div class="health-label">Coletor (/status)</div>
                        <div class="health-val" id="health-collector-val" style="color: <?php echo !$colStatus['summary_stale'] ? '#10B981' : '#EF4444'; ?>;"><?php echo !$colStatus['summary_stale'] ? 'SAUDÁVEL' : 'ATRASADO'; ?></div>
                        <div class="health-sub" id="health-collector-sub"><?php echo $colStatus['summary_age_sec'] === null ? 'Ausente' : ('Atualizado há ' . (int) $colStatus['summary_age_sec'] . 's'); ?></div>
                    </div>
                    <div class="health-tile">
                        <div class="health-label">Facilitador x402</div>
                        <div class="health-val" id="health-facilitator-val" style="color: <?php echo $facStatus['mode'] === 'settle' ? '#10B981' : '#F59E0B'; ?>;"><?php echo htmlspecialchars(strtoupper((string) $facStatus['mode']), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="health-sub" id="health-facilitator-sub"><?php echo htmlspecialchars(($facStatus['host'] ?: 'local') . ' · ' . (int) ($facStatus['multichain_accepts'] ?? 4) . ' chains', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="health-tile">
                        <div class="health-label">RapidAPI / Telegram / RPC</div>
                        <div class="health-val" id="health-integrations-val" style="font-size: 0.92rem; color: #38BDF8;">
                            RapidAPI: <?php echo $rapidOk ? ('OK (' . max(1, $rapidCount) . ')') : 'OFF'; ?> · TG: <?php echo $tgOk ? 'OK' : 'OFF'; ?>
                        </div>
                        <div class="health-sub" id="health-rpc-sub">Base Mainnet RPC: <?php echo !empty($wallet['rpc_operational']) ? 'Operacional' : 'Indisponível'; ?></div>
                    </div>
                </div>
            </div>

            <div class="analytics-row">
                <div class="panel">
                    <div class="panel-title">Distribuição de Status HTTP</div>
                    <div id="status-bars-container">
                        <?php
                        $statusCounts = $metrics['status_counts'] ?? [];
                        $totalRequests = (int) ($metrics['total_requests'] ?? 0);
                        $statusSum = array_sum(array_map('intval', $statusCounts));
                        $residual = $totalRequests - $statusSum;
                        if ($residual > 0) {
                            $statusCounts['outros'] = (int) (($statusCounts['outros'] ?? 0) + $residual);
                        }
                        $totalStatus = array_sum($statusCounts) ?: 1;
                        $statuses = [
                            '200' => ['label' => '200 OK', 'color' => '#10B981'],
                            '402' => ['label' => '402 Desafio', 'color' => '#F59E0B'],
                            '403' => ['label' => '403 Replay', 'color' => '#EF4444'],
                            '400' => ['label' => '400 Prova inválida', 'color' => '#F97316'],
                            '429' => ['label' => '429 Limite sandbox', 'color' => '#A855F7'],
                        ];
                        foreach (array_keys($statusCounts) as $extraCode) {
                            if (!isset($statuses[(string) $extraCode])) {
                                $statuses[(string) $extraCode] = [
                                    'label' => ($extraCode === 'outros' ? 'Outros' : $extraCode . ' Outros'),
                                    'color' => '#64748B',
                                ];
                            }
                        }
                        foreach ($statuses as $code => $info):
                            if (!isset($statusCounts[$code]) && !in_array((string) $code, ['200', '402', '403'], true)) {
                                continue;
                            }
                            $cnt = (int) ($statusCounts[$code] ?? 0);
                            $pct = round(($cnt / $totalStatus) * 100);
                        ?>
                        <div class="bar-row">
                            <span class="bar-label wide"><?php echo htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $info['color']; ?>;"></div></div>
                            <span class="bar-count"><?php echo $cnt; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-title">Falhas de Pagamento por Categoria</div>
                    <div id="failure-bars-container">
                        <?php
                        $failureCounts = $metrics['failure_categories'] ?? [];
                        $maxFailure = max(array_values(array_map('intval', $failureCounts)) ?: [0]);
                        if ($maxFailure <= 0) $maxFailure = 1;
                        $totalFailure = array_sum(array_map('intval', $failureCounts));
                        foreach ($failureCategoryLabels as $failKey => $failLabel):
                            $cnt = (int) ($failureCounts[$failKey] ?? 0);
                            if ($cnt === 0 && $failKey === 'unknown') continue;
                            $widthPct = round(($cnt / $maxFailure) * 100);
                            $sharePct = $totalFailure > 0 ? round(($cnt / $totalFailure) * 100) : 0;
                        ?>
                        <div class="bar-row" data-failure-key="<?php echo htmlspecialchars($failKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="bar-label wide"><?php echo htmlspecialchars($failLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width: <?php echo $widthPct; ?>%; background: linear-gradient(90deg, #EF4444, #F59E0B);"></div></div>
                            <span class="bar-count"><?php echo $cnt; ?> (<?php echo $sharePct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="payment-failures-panel" class="panel">
                <div class="panel-title">
                    <span>Diagnóstico Real de Falhas de Pagamento (requests.jsonl · Zero-PII)</span>
                    <span class="panel-subtitle">Últimas 25 falhas reais (exclui 402 vazios e 429 sandbox)</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Horário (BRT · UTC)</th>
                                <th>Serviço</th>
                                <th>HTTP</th>
                                <th>Origem</th>
                                <th>Motivo Exato (Sanitizado)</th>
                            </tr>
                        </thead>
                        <tbody id="payment-failures-tbody">
                            <?php if (empty($recentPaymentFailures)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--ok); padding: 1.25rem;">Nenhuma falha real de pagamento registrada recentemente.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentPaymentFailures as $pf):
                                    $pfDualTs = grid402_admin_format_brt_utc((string) ($pf['ts'] ?? ''), false);
                                ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <div><?php echo htmlspecialchars($pfDualTs['brt'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ($pfDualTs['utc'] !== ''): ?>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($pfDualTs['utc'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars((string) ($pf['service'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><span class="tag-status status-<?php echo (int) ($pf['status'] ?? 402); ?>">HTTP <?php echo (int) ($pf['status'] ?? 402); ?></span></td>
                                    <td><?php echo htmlspecialchars((string) ($pf['payment_source'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.78rem; color: #FCA5A5; word-break: break-word;"><?php echo htmlspecialchars((string) ($pf['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===================================================================== -->
        <!-- ABA 5: EVENTOS AO VIVO                                                -->
        <!-- ===================================================================== -->
        <section id="tab-eventos" class="tab-panel" role="tabpanel" aria-labelledby="btn-tab-eventos">
            <div id="recent-pulse-panel" class="panel">
                <div class="panel-title">
                    <span>Pulso Operacional Recente (Janela de <?php echo (int) ($recentPulse['buffer_total'] ?? 0); ?> Eventos)</span>
                    <span id="pulse-window-badge" style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--accent);">
                        1h: <?php echo (int) ($recentPulse['last_1h_count'] ?? 0); ?> reqs • 6h: <?php echo (int) ($recentPulse['last_6h_count'] ?? 0); ?> • 24h: <?php echo (int) ($recentPulse['last_24h_count'] ?? 0); ?>
                    </span>
                </div>
                <div class="kpi-grid" style="margin-bottom: 0.75rem;">
                    <div class="kpi-card">
                        <div class="kpi-title">Descoberta 402 vs. Falhas</div>
                        <div class="kpi-value" id="pulse-402-clean" style="color: #F59E0B;"><?php echo (int) ($recentPulse['challenge_402_clean'] ?? 0); ?> <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">desafios</span></div>
                        <div class="kpi-sub" id="pulse-payment-failures"><?php echo (int) ($recentPulse['payment_failures'] ?? 0); ?> falhas • <?php echo (int) ($recentPulse['paid_count'] ?? 0); ?> pagas recentes</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Sandbox / Gratuitas</div>
                        <div class="kpi-value" id="pulse-sandbox-free" style="color: #A855F7;"><?php echo (int) ($recentPulse['sandbox_count'] ?? 0); ?> <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">sandbox</span></div>
                        <div class="kpi-sub" id="pulse-free-sub"><?php echo (int) ($recentPulse['free_count'] ?? 0); ?> chamadas gratuitas (summary/status)</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-title">Erros Recentes (exceto 402)</div>
                        <div class="kpi-value" id="pulse-errors-recent" style="color: <?php echo ((int) ($recentPulse['error_non_402_count'] ?? 0) === 0) ? '#10B981' : '#EF4444'; ?>;"><?php echo (int) ($recentPulse['error_non_402_count'] ?? 0); ?></div>
                        <div class="kpi-sub" id="pulse-errors-sub"><?php echo ((int) ($recentPulse['error_non_402_count'] ?? 0) === 0) ? 'Zero 400/403/5xx nos últimos eventos' : 'Verificar feed recente abaixo'; ?></div>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Top serviços sondados recentemente:</span>
                    <div id="pulse-top-services" style="display: flex; flex-wrap: wrap; gap: 0.45rem;">
                        <?php if (empty($recentPulse['top_recent_services'])): ?>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">Sem eventos recentes</span>
                        <?php else: ?>
                            <?php foreach ($recentPulse['top_recent_services'] as $item): ?>
                                <span style="font-size: 0.74rem; font-family: var(--font-mono); padding: 0.2rem 0.55rem; border-radius: 999px; background: rgba(0, 240, 255, 0.08); border: 1px solid rgba(0, 240, 255, 0.25); color: #E2E8F0;">
                                    <?php echo htmlspecialchars((string) $item['service'], ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo (int) $item['count']; ?></strong>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="telemetry" class="panel">
                <div class="panel-title">
                    <div>
                        <span>Feed de Consultas Recentes (Buffer de 100 Eventos)</span>
                        <span style="font-size: 0.78rem; font-weight: normal; color: var(--text-muted); margin-left: 0.5rem;" id="last-updated-text">Atualizado há instantes</span>
                    </div>
                    <a href="/admin?api=export_csv" class="btn-outline" style="font-size: 0.74rem; padding: 0.32rem 0.65rem;">📥 Exportar CSV</a>
                </div>

                <div class="feed-toolbar">
                    <div class="filter-group">
                        <button type="button" class="filter-btn active" id="filter-btn-all" onclick="setFeedFilter('all')">Todos (<span id="count-all">0</span>)</button>
                        <button type="button" class="filter-btn" id="filter-btn-paid" onclick="setFeedFilter('paid')">🟢 Pagos (<span id="count-paid">0</span>)</button>
                        <button type="button" class="filter-btn" id="filter-btn-sandbox" onclick="setFeedFilter('sandbox')">🧪 Sandbox (<span id="count-sandbox">0</span>)</button>
                        <button type="button" class="filter-btn" id="filter-btn-challenge" onclick="setFeedFilter('challenge_402')">🟡 Desafios 402 (<span id="count-challenge">0</span>)</button>
                        <button type="button" class="filter-btn" id="filter-btn-blocked" onclick="setFeedFilter('blocked_403')">🔴 Bloqueados 403 (<span id="count-blocked">0</span>)</button>
                    </div>
                    <div class="search-wrap">
                        <input type="text" id="feed-search-input" class="search-input" placeholder="Buscar por serviço, IP ou hash..." aria-label="Buscar no feed de consultas" oninput="applyFeedSearch()">
                        <span id="feed-visible-count" style="font-size: 0.74rem; color: var(--text-muted); white-space: nowrap;">Mostrando 0 de 0</span>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Horário (BRT · UTC)</th>
                                <th>Serviço</th>
                                <th>Status</th>
                                <th>Tipo</th>
                                <th>Origem / Falha</th>
                                <th>Valor (USDC)</th>
                                <th>Transação On-Chain (Base)</th>
                                <th>IP Requisitante (LGPD)</th>
                            </tr>
                        </thead>
                        <tbody id="recent-events-tbody">
                            <?php
                            $events = $metrics['recent_events'] ?? [];
                            if (empty($events)):
                            ?>
                            <tr id="no-events-row">
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma consulta registrada até o momento.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($events as $ev):
                                    $evType = (string) ($ev['type'] ?? (!empty($ev['is_paid']) ? 'paid' : 'free'));
                                    $st = (int) ($ev['status'] ?? 200);
                                    $evDualTs = grid402_admin_format_brt_utc((string) ($ev['timestamp'] ?? ''), true);
                                ?>
                                <tr class="event-row" data-type="<?php echo htmlspecialchars($evType, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td style="white-space: nowrap;">
                                        <div><?php echo htmlspecialchars($evDualTs['brt'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ($evDualTs['utc'] !== ''): ?>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($evDualTs['utc'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars((string) ($ev['service'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><span class="tag-status status-<?php echo $st; ?>">HTTP <?php echo $st; ?></span></td>
                                    <td>
                                        <?php if ($evType === 'paid'): ?>
                                            <span class="tag-paid">PAGO x402</span>
                                        <?php elseif ($evType === 'sandbox'): ?>
                                            <span class="tag-status status-sandbox">SANDBOX (demo)</span>
                                        <?php elseif ($evType === 'challenge_402'): ?>
                                            <span class="tag-status status-402">DESAFIO 402</span>
                                        <?php elseif ($evType === 'blocked_403'): ?>
                                            <span class="tag-status status-403">BLOQUEADO 403</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.74rem;">GRATUITO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $evFailure = (string) ($ev['failure_category'] ?? '');
                                        $evSource = (string) ($ev['payment_source'] ?? '');
                                        if ($evFailure !== '' && $evFailure !== 'unknown') {
                                            $failLbl = $failureCategoryLabels[$evFailure] ?? $evFailure;
                                            echo '<span class="tag-failure">' . htmlspecialchars($failLbl, ENT_QUOTES, 'UTF-8') . '</span>';
                                        } elseif ($evSource !== '' && $evSource !== 'unknown') {
                                            $srcLbl = $paymentSourceLabels[$evSource] ?? $evSource;
                                            echo '<span class="tag-source">' . htmlspecialchars($srcLbl, ENT_QUOTES, 'UTF-8') . '</span>';
                                        } else {
                                            echo '<span style="color: var(--text-muted);">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo !empty($ev['is_paid']) ? '$' . htmlspecialchars((string) ($ev['price_usdc'] ?? '0.0000'), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td>
                                        <?php
                                        $evTx = (string) ($ev['settlement_tx'] ?? '');
                                        if ($evTx === '') $evTx = (string) ($ev['tx_hash'] ?? '');
                                        if ($evTx !== ''):
                                            $isEvmHexTx = (bool) preg_match('/^0x[a-f0-9]{64}$/i', $evTx);
                                            $isSolanaTx = !$isEvmHexTx && (bool) preg_match('/^[1-9A-HJ-NP-Za-km-z]{64,96}$/', $evTx);
                                            $txExplorerUrl = $isSolanaTx ? ('https://solscan.io/tx/' . rawurlencode($evTx)) : ('https://basescan.org/tx/' . rawurlencode($evTx));
                                        ?>
                                            <?php if ($isEvmHexTx || $isSolanaTx): ?>
                                                <a href="<?php echo htmlspecialchars($txExplorerUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="tx-link"><?php echo htmlspecialchars(substr($evTx, 0, 8) . '...' . substr($evTx, -6), ENT_QUOTES, 'UTF-8'); ?> ↗</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);"><?php echo htmlspecialchars(substr($evTx, 0, 14), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-muted);"><?php echo htmlspecialchars((string) ($ev['ip_masked'] ?? 'xxx.xxx.xxx.xxx'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal de Segurança e Alteração de Senha do Operador -->
    <div id="password-modal-overlay" class="modal-overlay" onclick="handlePasswordOverlayClick(event)">
        <div class="modal-card">
            <div class="modal-header">
                <span class="modal-title">🔑 Segurança, Conta e 2FA</span>
                <button type="button" class="modal-close" onclick="closePasswordModal()">✕</button>
            </div>
            <form id="change-password-form" onsubmit="submitPasswordChange(event)" style="padding: 1.4rem;">
                <div id="password-modal-alert" style="display: none; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-bottom: 1rem;"></div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="modal-new-user" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Usuário Administrador</label>
                    <input type="text" id="modal-new-user" minlength="3" class="search-input" style="max-width: 100%; width: 100%;" value="<?php echo htmlspecialchars($currentAdminUser, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
                </div>
                <div class="form-group" style="margin-bottom: 1rem; padding: 0.65rem 0.85rem; background: rgba(0, 240, 255, 0.06); border: 1px solid rgba(0, 240, 255, 0.2); border-radius: 6px;">
                    <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.82rem; color: #fff; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="modal-2fa-telegram" <?php echo $twoFactorEnabled ? 'checked' : ''; ?> style="width: 16px; height: 16px; accent-color: var(--accent);">
                        <span>Exigir código 2FA no Telegram (@GRIG402BOT) a cada login</span>
                    </label>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="modal-curr-pass" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Senha Atual (obrigatória para autorizar)</label>
                    <input type="password" id="modal-curr-pass" required class="search-input" style="max-width: 100%; width: 100%;" autocomplete="current-password">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="modal-new-pass" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Nova Senha (min. 8 caracteres — deixe em branco para manter a atual)</label>
                    <input type="password" id="modal-new-pass" minlength="8" class="search-input" style="max-width: 100%; width: 100%;" autocomplete="new-password">
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="modal-confirm-pass" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Confirmar Nova Senha</label>
                    <input type="password" id="modal-confirm-pass" minlength="8" class="search-input" style="max-width: 100%; width: 100%;" autocomplete="new-password">
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn-outline" onclick="closePasswordModal()">Cancelar</button>
                    <button type="submit" id="btn-submit-password" class="btn">Salvar Segurança</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Configuração de Telegram -->
    <div id="telegram-modal-overlay" class="modal-overlay" onclick="handleTelegramOverlayClick(event)">
        <div class="modal-card">
            <div class="modal-header">
                <h3>⚙️ Configurar Alertas Telegram</h3>
                <button type="button" class="modal-close" onclick="closeTelegramModal()">&times;</button>
            </div>
            <form id="telegram-config-form" onsubmit="submitTelegramConfig(event)" style="padding: 1.4rem;">
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Salva as credenciais em <code>telegram_config.json</code> (fora do DocumentRoot e preservado entre deploys) e dispara um alerta de teste imediatamente.
                </p>
                <div id="telegram-modal-alert" style="display: none; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-bottom: 1rem;"></div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="modal-tg-token" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Bot Token (@BotFather)</label>
                    <input type="password" id="modal-tg-token" class="search-input" style="max-width: 100%; width: 100%; font-family: var(--font-mono);" placeholder="<?php echo $tgOk ? '•••••••• (deixe em branco para manter o atual)' : 'Ex.: 123456789:AAH...'; ?>" autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="modal-tg-chat" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">Chat ID (usuário, grupo ou canal)</label>
                    <input type="text" id="modal-tg-chat" class="search-input" style="max-width: 100%; width: 100%; font-family: var(--font-mono);" placeholder="Ex.: 123456789 ou -100123456789" autocomplete="off">
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn-outline" onclick="closeTelegramModal()">Cancelar</button>
                    <button type="submit" id="btn-submit-telegram" class="btn">Salvar e Testar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Configuração do RapidAPI Bridge -->
    <div id="rapidapi-modal-overlay" class="modal-overlay" onclick="handleRapidApiOverlayClick(event)">
        <div class="modal-card">
            <div class="modal-header">
                <h3>⚡ Configurar RapidAPI Bridge</h3>
                <button type="button" class="modal-close" onclick="closeRapidApiModal()">&times;</button>
            </div>
            <form id="rapidapi-config-form" onsubmit="submitRapidApiConfig(event)" style="padding: 1.4rem;">
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Cole 1 ou mais segredos (separados por vírgula) de <strong>RapidAPI Studio &rarr; Gateway &rarr; Firewall Settings &rarr; <code>X-RapidAPI-Proxy-Secret</code></strong>.
                </p>
                <div id="rapidapi-modal-alert" style="display: none; padding: 0.65rem 0.85rem; border-radius: 6px; font-size: 0.82rem; margin-bottom: 1rem;"></div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="modal-rapidapi-secret" style="display: block; font-size: 0.82rem; margin-bottom: 0.35rem; color: #fff;">X-RapidAPI-Proxy-Secret (1 ou mais, separados por vírgula)</label>
                    <input type="password" id="modal-rapidapi-secret" class="search-input" style="max-width: 100%; width: 100%; font-family: var(--font-mono);" placeholder="<?php echo $rapidOk ? '•••••••• (informe 1 ou mais segredos separados por vírgula)' : 'Cole 1 ou mais segredos (min. 16 chars)'; ?>" autocomplete="off">
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn-outline" onclick="closeRapidApiModal()">Cancelar</button>
                    <button type="button" id="btn-clear-rapidapi" class="btn-outline" style="color: #FCA5A5; border-color: rgba(239, 68, 68, 0.35);" onclick="clearRapidApiConfig()">Desativar</button>
                    <button type="submit" id="btn-submit-rapidapi" class="btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const SERVICES_CATALOG = <?php echo json_encode($servicesCatalogOrder, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        const SERVICES_PRICING = <?php echo json_encode($pricingLookup, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        let currentDailyHistory = <?php echo json_encode($dailyHistory14d, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        let currentChartMetric = 'requests';
        let chart14dInstance = null;
        let currentFeedFilter = 'all';
        let currentFeedSearchText = '';

        function safeHtmlText(str) {
            return String(str ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        }

        /* ====== Navegação por 5 Abas (com aliases retrocompatíveis) ====== */
        const TAB_HASH_ALIASES = {
            'tab-resumo': 'tab-resumo', 'overview': 'tab-resumo',
            'tab-audiencia': 'tab-audiencia', 'growth': 'tab-audiencia',
            'tab-receita': 'tab-receita', 'passes': 'tab-receita',
            'tab-saude': 'tab-saude', 'health': 'tab-saude',
            'tab-eventos': 'tab-eventos', 'telemetry': 'tab-eventos',
        };
        const TAB_ORDER = ['tab-resumo', 'tab-audiencia', 'tab-receita', 'tab-saude', 'tab-eventos'];

        function activateTab(tabId, updateHash = true) {
            const resolved = TAB_HASH_ALIASES[tabId] || 'tab-resumo';
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const active = btn.getAttribute('data-tab') === resolved;
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
                btn.setAttribute('tabindex', active ? '0' : '-1');
            });
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.toggle('is-active', panel.id === resolved);
            });
            if (updateHash && window.location.hash !== '#' + resolved) {
                history.replaceState(null, '', '#' + resolved);
            }
            if (resolved === 'tab-resumo' && chart14dInstance) {
                chart14dInstance.resize();
            }
        }

        function syncTabFromHash() {
            const raw = (window.location.hash || '').replace(/^#/, '');
            if (raw && TAB_HASH_ALIASES[raw]) {
                activateTab(TAB_HASH_ALIASES[raw], false);
            }
        }

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => activateTab(btn.getAttribute('data-tab')));
            btn.addEventListener('keydown', (e) => {
                const idx = TAB_ORDER.indexOf(btn.getAttribute('data-tab'));
                let nextIdx = idx;
                if (e.key === 'ArrowRight') nextIdx = (idx + 1) % TAB_ORDER.length;
                else if (e.key === 'ArrowLeft') nextIdx = (idx - 1 + TAB_ORDER.length) % TAB_ORDER.length;
                else if (e.key === 'Home') nextIdx = 0;
                else if (e.key === 'End') nextIdx = TAB_ORDER.length - 1;
                else return;
                e.preventDefault();
                const nextId = TAB_ORDER[nextIdx];
                activateTab(nextId);
                const targetBtn = document.getElementById('btn-' + nextId);
                if (targetBtn) targetBtn.focus();
            });
        });
        window.addEventListener('hashchange', syncTabFromHash);

        function toggleSettingsDrawer(forceState) {
            const drawer = document.getElementById('settings-drawer');
            const btn = document.getElementById('btn-toggle-settings');
            if (!drawer || !btn) return;
            const next = typeof forceState === 'boolean' ? forceState : !drawer.classList.contains('is-open');
            drawer.classList.toggle('is-open', next);
            btn.classList.toggle('is-active', next);
            btn.setAttribute('aria-expanded', next ? 'true' : 'false');
        }

        /* ====== Gráfico Chart.js v4 com Fallback Gracioso ====== */
        const CHART_SERIES_META = {
            requests: { label: 'Requisições', bg: 'rgba(0, 240, 255, 0.55)', border: '#00F0FF' },
            page_views: { label: 'Page Views (HTML)', bg: 'rgba(56, 189, 248, 0.55)', border: '#38BDF8' },
            paid: { label: 'Consultas Pagas', bg: 'rgba(16, 185, 129, 0.65)', border: '#10B981' },
        };

        function setChartMetric(metricKey) {
            if (!CHART_SERIES_META[metricKey]) return;
            currentChartMetric = metricKey;
            document.querySelectorAll('[data-chart-metric]').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-chart-metric') === metricKey);
            });
            renderDailyChart14d(currentDailyHistory);
        }

        function renderDailyChart14d(history) {
            if (!Array.isArray(history) || history.length === 0) return;
            currentDailyHistory = history;
            renderDailyTable14d(history);

            const wrap = document.getElementById('chart-14d-wrap');
            const fallback = document.getElementById('chart-14d-fallback');
            const canvas = document.getElementById('chart-14d-canvas');

            if (typeof window.Chart === 'undefined' || window.__chartJsLoadFailed || !canvas) {
                if (wrap) wrap.style.display = 'none';
                if (fallback) fallback.style.display = 'block';
                return;
            }
            if (wrap) wrap.style.display = 'block';
            if (fallback) fallback.style.display = 'none';

            const meta = CHART_SERIES_META[currentChartMetric] || CHART_SERIES_META.requests;
            const labels = history.map(d => String(d.label || d.date || ''));
            const values = history.map(d => Number(d[currentChartMetric] || 0));

            if (chart14dInstance) {
                chart14dInstance.data.labels = labels;
                chart14dInstance.data.datasets[0].label = meta.label;
                chart14dInstance.data.datasets[0].data = values;
                chart14dInstance.data.datasets[0].backgroundColor = meta.bg;
                chart14dInstance.data.datasets[0].borderColor = meta.border;
                chart14dInstance.update('none');
                return;
            }

            chart14dInstance = new window.Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: meta.label,
                        data: values,
                        backgroundColor: meta.bg,
                        borderColor: meta.border,
                        borderWidth: 1.5,
                        borderRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0B132B',
                            borderColor: '#00F0FF',
                            borderWidth: 1,
                            titleColor: '#00F0FF',
                            bodyColor: '#F8FAFC',
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0] ? items[0].dataIndex : 0;
                                    const d = currentDailyHistory[idx] || {};
                                    return '📅 ' + String(d.date || d.label || '');
                                },
                                label: (ctx) => {
                                    const d = currentDailyHistory[ctx.dataIndex] || {};
                                    return [
                                        'Requisições: ' + Number(d.requests || 0).toLocaleString('pt-BR'),
                                        'Page Views: ' + Number(d.page_views || 0).toLocaleString('pt-BR'),
                                        'Consultas Pagas: ' + Number(d.paid || 0).toLocaleString('pt-BR'),
                                        'Receita: US$ ' + Number(d.usdc || 0).toFixed(4),
                                    ];
                                },
                            },
                        },
                    },
                    scales: {
                        x: { ticks: { color: '#94A3B8', font: { family: 'JetBrains Mono', size: 11 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
                        y: { beginAtZero: true, ticks: { color: '#94A3B8', font: { family: 'JetBrains Mono', size: 11 } }, grid: { color: 'rgba(255,255,255,0.06)' } },
                    },
                },
            });
        }

        function renderDailyTable14d(history) {
            const tbody = document.getElementById('daily-history-14d-tbody');
            if (!tbody || !Array.isArray(history)) return;
            const rows = [...history].reverse();
            let html = '';
            rows.forEach(d => {
                const paid = Number(d.paid || 0);
                html += `<tr>
                    <td><strong>${safeHtmlText(d.label || '')}</strong> <span style="color: var(--text-muted); font-size: 0.72rem;">(${safeHtmlText(d.date || '')})</span></td>
                    <td>${Number(d.requests || 0).toLocaleString('pt-BR')}</td>
                    <td>${Number(d.page_views || 0).toLocaleString('pt-BR')}</td>
                    <td style="color: ${paid > 0 ? '#34D399' : 'var(--text-muted)'};">${paid}</td>
                    <td>US$ ${Number(d.usdc || 0).toFixed(4).replace('.', ',')}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        /* ====== Renderizadores Executivos ====== */
        function renderTodayVsYesterday(tvsy) {
            if (!tvsy || !tvsy.metrics) return;
            const m = tvsy.metrics;
            const lbl = document.getElementById('tvsy-dates-label');
            if (lbl) lbl.textContent = `Hoje: ${tvsy.today_label || ''} (parcial) vs. Ontem: ${tvsy.yesterday_label || ''}`;

            const hdrToday = document.getElementById('header-today-pill');
            if (hdrToday) {
                hdrToday.innerHTML = `Hoje (${safeHtmlText(tvsy.today_label || '')}): <strong>${Number(m.requests?.today || 0).toLocaleString('pt-BR')}</strong> reqs · <strong>${Number(m.page_views?.today || 0).toLocaleString('pt-BR')}</strong> views · <strong>${Number(m.paid?.today || 0)}</strong> pagas`;
            }

            const tbody = document.getElementById('tvsy-tbody');
            if (!tbody) return;
            const specs = [
                { key: 'requests', label: 'Requisições Totais', fmt: 'int' },
                { key: 'page_views', label: 'Page Views (HTML)', fmt: 'int' },
                { key: 'paid', label: 'Consultas Pagas', fmt: 'int' },
                { key: 'revenue_usdc', label: 'Receita (USDC)', fmt: 'usdc' },
                { key: 'revenue_brl', label: 'Receita Estimada (BRL)', fmt: 'brl' },
                { key: 'new_wallets', label: 'Novas Wallets Conectadas', fmt: 'int' },
            ];
            const fmtVal = (val, fmt) => {
                if (fmt === 'usdc') return 'US$ ' + Number(val || 0).toFixed(4).replace('.', ',');
                if (fmt === 'brl') return 'R$ ' + Number(val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return Number(val || 0).toLocaleString('pt-BR');
            };
            let html = '';
            specs.forEach(s => {
                const row = m[s.key] || { today: 0, yesterday: 0, total: 0, delta_pct: null };
                let cls = 'delta-neutral';
                let dTxt = '—';
                if (row.delta_pct !== null && row.delta_pct !== undefined) {
                    const num = Number(row.delta_pct);
                    if (num > 0) { cls = 'delta-up'; dTxt = '+' + num.toFixed(1).replace('.', ',') + '%'; }
                    else if (num < 0) { cls = 'delta-down'; dTxt = num.toFixed(1).replace('.', ',') + '%'; }
                    else { dTxt = '0,0%'; }
                }
                html += `<tr>
                    <td style="font-family: var(--font-sans); font-weight: 600; color: #fff;">${s.label}</td>
                    <td><strong>${fmtVal(row.today, s.fmt)}</strong></td>
                    <td style="color: var(--text-muted);">${fmtVal(row.yesterday, s.fmt)}</td>
                    <td><span class="delta-badge ${cls}">${dTxt}</span></td>
                    <td style="color: var(--accent);">${fmtVal(row.total, s.fmt)}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function renderAlerts(alerts, summary) {
            const sum = summary || { high: 0, medium: 0, low: 0, total: (alerts || []).length };
            const hEl = document.getElementById('header-alert-high');
            const mEl = document.getElementById('header-alert-medium');
            const lEl = document.getElementById('header-alert-low');
            const tabBadge = document.getElementById('tab-badge-alerts');
            if (hEl) {
                hEl.className = 'strip-pill alert-pill-btn ' + (Number(sum.high || 0) > 0 ? 'danger' : 'ok');
                hEl.innerHTML = 'Alta: <strong>' + Number(sum.high || 0) + '</strong>';
            }
            if (mEl) {
                mEl.className = 'strip-pill alert-pill-btn ' + (Number(sum.medium || 0) > 0 ? 'warn' : '');
                mEl.innerHTML = 'Média: <strong>' + Number(sum.medium || 0) + '</strong>';
            }
            if (lEl) lEl.innerHTML = 'Baixa: <strong>' + Number(sum.low || 0) + '</strong>';
            if (tabBadge) tabBadge.textContent = String(Number(sum.total || 0));

            const box = document.getElementById('alerts-container');
            if (!box) return;
            if (!Array.isArray(alerts) || alerts.length === 0) {
                box.innerHTML = '<div class="alert-item sev-ok"><div class="alert-head"><span class="alert-title">✅ Nenhum ponto de atenção ativo</span></div><div class="alert-detail">Todos os indicadores operacionais e de conversão estão dentro dos limiares saudáveis.</div></div>';
                return;
            }
            let html = '';
            alerts.forEach(al => {
                const sev = String(al.severity || 'low');
                const sevLbl = sev === 'high' ? 'ALTA' : (sev === 'medium' ? 'MÉDIA' : 'BAIXA');
                html += `<div class="alert-item sev-${safeHtmlText(sev)}">
                    <div class="alert-head">
                        <span class="sev-tag ${safeHtmlText(sev)}">${sevLbl}</span>
                        <span class="alert-title">${safeHtmlText(al.title || '')}</span>
                    </div>
                    <div class="alert-detail">${safeHtmlText(al.detail || '')}</div>
                    <div class="alert-action">→ ${safeHtmlText(al.action || '')}</div>
                </div>`;
            });
            box.innerHTML = html;
        }

        function renderOperationalHealth(oh) {
            if (!oh) return;
            const up = Number(oh.services_up || 0);
            const tot = Number(oh.services_total || 25);
            const u30 = Number(oh.uptime_30d_pct || 100);
            const p50 = Number(oh.latency_p50_ms || 0);
            const p95 = Number(oh.latency_p95_ms || 0);

            const hdrPill = document.getElementById('header-services-pill');
            if (hdrPill) {
                hdrPill.className = 'strip-pill ' + (up >= tot && tot > 0 ? 'ok' : 'warn');
                hdrPill.innerHTML = `🟢 <strong>${up}/${tot} UP</strong> · Uptime 30d: ${u30.toFixed(1).replace('.', ',')}%`;
            }
            const svEl = document.getElementById('health-services-up');
            if (svEl) { svEl.textContent = `${up}/${tot} UP`; svEl.style.color = (up >= tot && tot > 0) ? '#10B981' : '#F59E0B'; }
            const uEl = document.getElementById('health-uptime-30d');
            if (uEl) uEl.textContent = 'Uptime 30d: ' + u30.toFixed(2).replace('.', ',') + '%';
            const latEl = document.getElementById('health-latencies');
            if (latEl) latEl.textContent = p50.toFixed(1).replace('.', ',') + ' / ' + p95.toFixed(1).replace('.', ',') + ' ms';
            const colV = document.getElementById('health-collector-val');
            const colS = document.getElementById('health-collector-sub');
            if (colV) { colV.textContent = oh.collector_stale ? 'ATRASADO' : 'SAUDÁVEL'; colV.style.color = oh.collector_stale ? '#EF4444' : '#10B981'; }
            if (colS) colS.textContent = oh.collector_age_sec === null ? 'Ausente' : ('Atualizado há ' + Number(oh.collector_age_sec) + 's');
            const facV = document.getElementById('health-facilitator-val');
            const facS = document.getElementById('health-facilitator-sub');
            if (facV) { facV.textContent = String(oh.facilitator_mode || 'unknown').toUpperCase(); facV.style.color = oh.facilitator_mode === 'settle' ? '#10B981' : '#F59E0B'; }
            if (facS) facS.textContent = `${oh.facilitator_host || 'local'} · ${Number(oh.multichain_accepts || 4)} chains`;
            const intV = document.getElementById('health-integrations-val');
            const rpcS = document.getElementById('health-rpc-sub');
            if (intV) intV.textContent = `RapidAPI: ${oh.rapidapi_configured ? ('OK (' + Math.max(1, Number(oh.rapidapi_count || 1)) + ')') : 'OFF'} · TG: ${oh.telegram_configured ? 'OK' : 'OFF'}`;
            if (rpcS) rpcS.textContent = 'Base Mainnet RPC: ' + (oh.rpc_operational ? 'Operacional' : 'Indisponível');
        }

        function renderPaidVsTotalServices(paidMap, serviceCounts) {
            const tbody = document.getElementById('paid-vs-total-services-tbody');
            if (!tbody || !serviceCounts) return;
            const pMap = paidMap || {};
            const rows = SERVICES_CATALOG.map(svc => ({
                service: svc,
                paid: Number(pMap[svc] || 0),
                total: Number(serviceCounts[svc] || 0),
                price: Number(SERVICES_PRICING[svc] ?? 0.005),
            })).sort((a, b) => (b.paid - a.paid) || (b.total - a.total));

            let html = '';
            rows.forEach(r => {
                const isLow = r.service !== 'summary' && r.total < 30;
                html += `<tr>
                    <td><strong>${safeHtmlText(r.service)}</strong></td>
                    <td style="color: ${r.paid > 0 ? '#34D399' : 'var(--text-muted)'};"><strong>${r.paid}</strong></td>
                    <td>${r.total.toLocaleString('pt-BR')}</td>
                    <td style="color: var(--text-muted);">$${r.price.toFixed(4)}</td>
                    <td>${isLow ? '<span class="tag-low-traction">Baixa tração (&lt;30)</span>' : '<span style="color: var(--ok); font-size: 0.72rem;">Ativo</span>'}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function renderBreakdownBars(containerId, attrName, countsObj, labelsObj, isFailure) {
            const el = document.getElementById(containerId);
            if (!el || !countsObj || !labelsObj) return;
            const vals = Object.values(countsObj).map(v => Number(v || 0));
            const maxVal = Math.max(1, ...vals);
            const total = vals.reduce((a, b) => a + b, 0);
            const grad = isFailure ? 'linear-gradient(90deg, #EF4444, #F59E0B)' : 'linear-gradient(90deg, #06B6D4, #10B981)';
            let html = '';
            Object.keys(labelsObj).forEach(key => {
                const cnt = Number(countsObj[key] || 0);
                if (cnt === 0 && key === 'unknown') return;
                const w = Math.round((cnt / maxVal) * 100);
                const sh = total > 0 ? Math.round((cnt / total) * 100) : 0;
                html += `<div class="bar-row" ${attrName}="${safeHtmlText(key)}">
                    <span class="bar-label wide">${safeHtmlText(labelsObj[key])}</span>
                    <div class="bar-track"><div class="bar-fill" style="width: ${w}%; background: ${grad};"></div></div>
                    <span class="bar-count">${cnt} (${sh}%)</span>
                </div>`;
            });
            el.innerHTML = html;
        }

        function renderTopConsumers(consumers) {
            const tbody = document.getElementById('top-consumers-tbody');
            if (!tbody) return;
            if (!consumers || consumers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Aguardando eventos no buffer.</td></tr>';
                return;
            }
            let html = '';
            consumers.forEach(c => {
                let pCls = 'profile-agent';
                if (c.paid > 0) pCls = 'profile-paid';
                else if (c.sandbox > 0) pCls = 'profile-sandbox';
                else if (c.challenges > 5) pCls = 'profile-402';
                const rkCls = (c.rank <= 3) ? 'top-' + c.rank : '';
                html += `<tr>
                    <td><span class="rank-badge ${rkCls}">${c.rank}</span></td>
                    <td><code>${safeHtmlText(c.ip_masked)}</code></td>
                    <td><strong>${c.total}</strong> <span style="font-size: 0.7rem; color: var(--text-muted);">(${c.paid}p)</span></td>
                    <td><span class="tag-profile ${pCls}">${safeHtmlText(c.profile)}</span></td>
                    <td><code style="color: var(--accent);">${safeHtmlText(c.favorite_service)}</code></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function renderServiceRevenue(services) {
            const el = document.getElementById('service-revenue-bars-container');
            if (!el || !services) return;
            const top10 = services.slice(0, 10);
            const maxVal = Math.max(0.0001, ...top10.map(s => Number(s.estimated_usdc || 0)));
            let html = '';
            top10.forEach(s => {
                const rev = Number(s.estimated_usdc || 0);
                const p = Math.round((rev / maxVal) * 100);
                html += `<div class="bar-row">
                    <span class="bar-label wide">${safeHtmlText(s.service)} <small style="color: var(--text-muted);">($${s.unit_price})</small></span>
                    <div class="bar-track"><div class="bar-fill" style="width: ${p}%; background: linear-gradient(90deg, #10B981, #00F0FF);"></div></div>
                    <span class="bar-count">$${rev.toFixed(4)}</span>
                </div>`;
            });
            el.innerHTML = html;
        }

        function renderRecentPulse(pulse) {
            if (!pulse) return;
            const winBadge = document.getElementById('pulse-window-badge');
            if (winBadge) winBadge.textContent = `1h: ${Number(pulse.last_1h_count || 0)} reqs • 6h: ${Number(pulse.last_6h_count || 0)} • 24h: ${Number(pulse.last_24h_count || 0)}`;
            const c402 = document.getElementById('pulse-402-clean');
            if (c402) c402.innerHTML = `${Number(pulse.challenge_402_clean || 0)} <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">desafios</span>`;
            const pf = document.getElementById('pulse-payment-failures');
            if (pf) pf.textContent = `${Number(pulse.payment_failures || 0)} falhas • ${Number(pulse.paid_count || 0)} pagas recentes`;
            const sbx = document.getElementById('pulse-sandbox-free');
            if (sbx) sbx.innerHTML = `${Number(pulse.sandbox_count || 0)} <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">sandbox</span>`;
            const fr = document.getElementById('pulse-free-sub');
            if (fr) fr.textContent = `${Number(pulse.free_count || 0)} chamadas gratuitas (summary/status)`;
            const errEl = document.getElementById('pulse-errors-recent');
            const errSub = document.getElementById('pulse-errors-sub');
            const errCount = Number(pulse.error_non_402_count || 0);
            if (errEl) { errEl.textContent = String(errCount); errEl.style.color = errCount === 0 ? '#10B981' : '#EF4444'; }
            if (errSub) errSub.textContent = errCount === 0 ? 'Zero 400/403/5xx nos últimos eventos' : 'Verificar feed recente abaixo';
            const topWrap = document.getElementById('pulse-top-services');
            if (topWrap) {
                const items = Array.isArray(pulse.top_recent_services) ? pulse.top_recent_services : [];
                if (items.length === 0) {
                    topWrap.innerHTML = '<span style="font-size: 0.78rem; color: var(--text-muted);">Sem eventos recentes</span>';
                } else {
                    topWrap.innerHTML = items.map(it => `<span style="font-size: 0.74rem; font-family: var(--font-mono); padding: 0.2rem 0.55rem; border-radius: 999px; background: rgba(0, 240, 255, 0.08); border: 1px solid rgba(0, 240, 255, 0.25); color: #E2E8F0;">${safeHtmlText(it.service)}: <strong>${Number(it.count || 0)}</strong></span>`).join('');
                }
            }
        }

        function formatBrtUtcTimestamp(isoStr, timeOnly) {
            const raw = String(isoStr || '').trim();
            if (!raw) return { brt: '-', utc: '' };
            const d = new Date(raw);
            if (Number.isNaN(d.getTime())) return { brt: timeOnly ? raw.substr(11, 8) : raw, utc: '' };
            const brtTime = d.toLocaleTimeString('pt-BR', { timeZone: 'America/Sao_Paulo', hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            if (timeOnly) {
                const utcTime = d.toLocaleTimeString('pt-BR', { timeZone: 'UTC', hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                return { brt: brtTime + ' BRT', utc: utcTime + ' UTC' };
            }
            const brtDate = d.toLocaleDateString('pt-BR', { timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit' });
            return { brt: brtDate + ' ' + brtTime + ' BRT', utc: raw };
        }

        function renderRecentPaymentFailures(failures) {
            const tbody = document.getElementById('payment-failures-tbody');
            if (!tbody || !Array.isArray(failures)) return;
            if (failures.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--ok); padding: 1.25rem;">Nenhuma falha real de pagamento registrada recentemente.</td></tr>';
                return;
            }
            let html = '';
            failures.forEach(pf => {
                const dualTs = formatBrtUtcTimestamp(pf.ts || '', false);
                const st = Number(pf.status || 402);
                html += `<tr>
                    <td style="white-space: nowrap;"><div>${safeHtmlText(dualTs.brt)}</div>${dualTs.utc ? `<div style="font-size: 0.7rem; color: var(--text-muted);">${safeHtmlText(dualTs.utc)}</div>` : ''}</td>
                    <td><strong>${safeHtmlText(pf.service || '')}</strong></td>
                    <td><span class="tag-status status-${st}">HTTP ${st}</span></td>
                    <td>${safeHtmlText(pf.payment_source || 'unknown')}</td>
                    <td style="font-family: var(--font-mono); font-size: 0.78rem; color: #FCA5A5; word-break: break-word;">${safeHtmlText(pf.error || '')}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function setFeedFilter(filter) {
            currentFeedFilter = filter;
            document.querySelectorAll('.feed-toolbar .filter-btn').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById('filter-btn-' + (filter === 'challenge_402' ? 'challenge' : (filter === 'blocked_403' ? 'blocked' : filter)));
            if (activeBtn) activeBtn.classList.add('active');
            applyFeedFilterAndSearch();
        }

        function applyFeedSearch() {
            const input = document.getElementById('feed-search-input');
            currentFeedSearchText = input ? input.value.trim().toLowerCase() : '';
            applyFeedFilterAndSearch();
        }

        function applyFeedFilterAndSearch() {
            const rows = document.querySelectorAll('.event-row');
            let visible = 0, paid = 0, sbx = 0, c402 = 0, b403 = 0;
            rows.forEach(r => {
                const t = r.getAttribute('data-type') || 'free';
                if (t === 'paid') paid++;
                if (t === 'sandbox') sbx++;
                if (t === 'challenge_402') c402++;
                if (t === 'blocked_403') b403++;
                const okF = currentFeedFilter === 'all' || t === currentFeedFilter;
                const okS = !currentFeedSearchText || r.textContent.toLowerCase().includes(currentFeedSearchText);
                r.style.display = (okF && okS) ? '' : 'none';
                if (okF && okS) visible++;
            });
            const setTxt = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
            setTxt('count-all', rows.length); setTxt('count-paid', paid); setTxt('count-sandbox', sbx);
            setTxt('count-challenge', c402); setTxt('count-blocked', b403);
            setTxt('feed-visible-count', `Mostrando ${visible} de ${rows.length}`);
        }

        function renderRapidApiStatusBadge(rs) {
            const el = document.getElementById('runtime-rapidapi-badge');
            if (!el || !rs) return;
            const rOk = Boolean(rs.configured);
            const rCount = Number(rs.count || 0);
            el.style.color = rOk ? '#10B981' : '#94A3B8';
            el.innerHTML = 'RapidAPI Bridge: <strong>' + (rOk ? (rCount > 1 ? `ativo (${rCount} APIs)` : 'ativo') : 'inativo') + '</strong>';
        }

        function renderTelegramStatusBadge(tg) {
            const el = document.getElementById('runtime-telegram-badge');
            if (!el || !tg) return;
            const configured = Boolean(tg.configured);
            const last = tg.last_status || null;
            const label = configured ? 'configurado' : (tg.disabled ? 'desabilitado' : 'não configurado (.env)');
            el.style.color = configured ? ((last && !last.ok) ? '#F59E0B' : '#10B981') : '#EF4444';
            let suffix = '';
            if (last) suffix = last.ok ? ' · último HTTP 200' : (' · erro: ' + String(last.error || ('HTTP ' + (last.http_code || 0))));
            el.innerHTML = 'Telegram: <strong>' + safeHtmlText(label) + '</strong>' + safeHtmlText(suffix);
        }

        function applyGrowthMetricsToDom(gm) {
            if (!gm) return;
            const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            setText('growth-wallets', Number(gm.wallets_connected || 0).toLocaleString('pt-BR'));
            setText('growth-paying-sub', `${Number(gm.wallets_paying || 0)} pagantes • retenção ${Number(gm.wallet_retention || 0).toFixed(1).replace('.', ',')}%`);
            setText('growth-conversion', Number(gm.conversion_first_payment || 0).toFixed(1) + '%');
            setText('growth-conversion-2', Number(gm.conversion_second_payment || 0).toFixed(1) + '%');
            setText('growth-revenue-wallet', 'US$ ' + Number(gm.revenue_per_wallet_usdc || 0).toFixed(4));
            setText('growth-builders', Number(gm.builders_registered || 0).toLocaleString('pt-BR'));
            setText('growth-referrals', Number(gm.referrals_registered || 0).toLocaleString('pt-BR'));
            setText('growth-promo-credits', Number(gm.promo_credits_total || 0).toLocaleString('pt-BR'));
        }

        async function fetchGrowthAdmin() {
            try {
                const res = await fetch('/admin?api=growth', { cache: 'no-store' });
                if (res.status === 401) { window.location.reload(); return; }
                const data = await res.json();
                if (data && data.metrics) applyGrowthMetricsToDom(data.metrics);
            } catch (e) {}
        }

        let userInteractedSinceLastTouch = false;
        let lastSessionTouchAt = Date.now();
        ['mousemove', 'keydown', 'click', 'touchstart'].forEach(ev => {
            window.addEventListener(ev, () => { userInteractedSinceLastTouch = true; }, { passive: true });
        });

        async function fetchLiveStats(isManual = false) {
            try {
                let statsUrl = '/admin?api=stats';
                if (isManual || (userInteractedSinceLastTouch && (Date.now() - lastSessionTouchAt) > 30000)) {
                    statsUrl += '&touch=1';
                    userInteractedSinceLastTouch = false;
                    lastSessionTouchAt = Date.now();
                }
                const res = await fetch(statsUrl, { cache: 'no-store' });
                if (res.status === 401) { window.location.href = '/admin?expired=idle_timeout'; return; }
                const data = await res.json();
                if (data.status !== 'success') return;

                const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
                setTxt('kpi-total-usdc', 'US$ ' + Number(data.total_revenue_usdc || 0).toFixed(4));
                setTxt('kpi-total-brl', '≈ R$ ' + Number(data.total_revenue_brl || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' (Base Mainnet)');
                setTxt('kpi-today-usdc', 'US$ ' + Number(data.today_revenue_usdc || 0).toFixed(4));
                setTxt('kpi-today-brl', '≈ R$ ' + Number(data.today_revenue_brl || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' hoje');
                setTxt('kpi-total-reqs', Number(data.total_requests || 0).toLocaleString('pt-BR'));
                setTxt('kpi-total-visits', Number(data.total_page_views || 0).toLocaleString('pt-BR'));
                setTxt('kpi-today-visits', Number(data.today_page_views || 0).toLocaleString('pt-BR') + ' hoje • HTML público');
                setTxt('kpi-paid-reqs', Number(data.total_paid_requests || 0).toLocaleString('pt-BR') + ' pagas • ' + Number(data.total_sandbox_requests || 0).toLocaleString('pt-BR') + ' sandbox');
                setTxt('kpi-arpu-usdc', 'US$ ' + Number(data.arpu_usdc || 0).toFixed(4));
                setTxt('kpi-arpu-brl', '≈ R$ ' + Number(data.arpu_brl || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 }) + ' / consulta paga');
                setTxt('kpi-sla-tech', Number(data.technical_success_rate ?? 100).toFixed(1) + '%');
                setTxt('kpi-latency-p95', 'Sem erros 5xx · Latência p95: ' + Number(data.latency_p95_ms || 0).toFixed(1).replace('.', ',') + ' ms');
                setTxt('kpi-sandbox-rate', Number(data.sandbox_rate || 0).toFixed(1) + '%');

                const reqs402 = Number(data.status_counts?.['402'] || 0);
                const paid = Number(data.total_paid_requests || 0);
                setTxt('kpi-conversion', ((reqs402 + paid) > 0 ? ((paid / (reqs402 + paid)) * 100).toFixed(1) : '0') + '%');

                if (data.wallet_balances) {
                    const w = data.wallet_balances;
                    setTxt('kpi-wallet-usdc', 'US$ ' + Number(w.usdc_balance || 0).toFixed(4));
                    setTxt('kpi-wallet-eth', 'Reserva Gas: ' + Number(w.eth_balance || 0).toFixed(5) + ' ETH');
                    const rpcEl = document.getElementById('kpi-rpc-status');
                    if (rpcEl) { rpcEl.textContent = w.rpc_operational ? 'RPC ATIVO' : 'CACHE OFFLINE'; rpcEl.style.color = w.rpc_operational ? '#10B981' : '#F59E0B'; }
                }

                if (data.passes_stats) {
                    const ps = data.passes_stats;
                    const totC = Number(ps.total_credits || 0);
                    const remC = Number(ps.remaining_credits || 0);
                    const utilPct = totC > 0 ? (((totC - remC) / totC) * 100).toFixed(1).replace('.', ',') : '0,0';
                    const pAct = document.getElementById('kpi-passes-active');
                    if (pAct) pAct.innerHTML = `${Number(ps.active_passes || 0)} <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">/ ${Number(ps.total_issued || 0)} emitidos</span>`;
                    setTxt('kpi-wallet-passes', `${Number(ps.active_passes || 0)} passes ativos • ${remC.toLocaleString('pt-BR')} créditos (${utilPct}% usados)`);
                }

                if (data.facilitator_status) {
                    const fs = data.facilitator_status;
                    const facBadge = document.getElementById('runtime-facilitator-badge');
                    if (facBadge) {
                        facBadge.style.color = fs.mode === 'settle' ? '#10B981' : '#F59E0B';
                        facBadge.innerHTML = `Facilitador x402: <strong>${safeHtmlText(fs.mode || 'unknown')}</strong> (${safeHtmlText(fs.host || 'local')} · ${safeHtmlText(fs.auth_mode || '')} · ${Number(fs.multichain_accepts || 4)} chains)`;
                    }
                }
                if (data.rapidapi_status) renderRapidApiStatusBadge(data.rapidapi_status);
                if (data.collector_status) {
                    const cs = data.collector_status;
                    const colBadge = document.getElementById('runtime-collector-badge');
                    if (colBadge) {
                        colBadge.style.color = cs.summary_stale ? '#F59E0B' : '#10B981';
                        const ageTxt = cs.summary_age_sec === null ? 'ausente' : (cs.summary_age_sec < 120 ? cs.summary_age_sec + 's atrás' : Math.round(cs.summary_age_sec / 60) + ' min atrás');
                        colBadge.innerHTML = 'Coletor (/status): <strong>' + safeHtmlText(ageTxt) + '</strong>';
                    }
                }
                if (data.telegram_status) renderTelegramStatusBadge(data.telegram_status);

                if (data.today_vs_yesterday) renderTodayVsYesterday(data.today_vs_yesterday);
                if (data.alerts) renderAlerts(data.alerts, data.alerts_summary);
                if (data.operational_health) renderOperationalHealth(data.operational_health);
                if (data.growth_metrics) applyGrowthMetricsToDom(data.growth_metrics);
                if (data.daily_history_14d) renderDailyChart14d(data.daily_history_14d);
                if (data.top_consumers) renderTopConsumers(data.top_consumers);
                if (data.service_revenue) renderServiceRevenue(data.service_revenue);
                if (data.recent_pulse) renderRecentPulse(data.recent_pulse);
                if (data.service_counts) {
                    renderPaidVsTotalServices(data.paid_requests_per_service || {}, data.service_counts);
                    const sBars = document.getElementById('service-bars-container');
                    if (sBars) {
                        const maxS = Math.max(1, ...Object.values(data.service_counts).map(v => Number(v || 0)));
                        sBars.innerHTML = SERVICES_CATALOG.map(s => {
                            const c = Number(data.service_counts[s] || 0);
                            const p = Math.round((c / maxS) * 100);
                            return `<div class="bar-row"><span class="bar-label">${safeHtmlText(s)}</span><div class="bar-track"><div class="bar-fill" style="width: ${p}%;"></div></div><span class="bar-count">${c}</span></div>`;
                        }).join('');
                    }
                }

                renderBreakdownBars('payment-source-bars-container', 'data-source-key', data.payment_sources, data.payment_source_labels, false);
                renderBreakdownBars('failure-bars-container', 'data-failure-key', data.failure_categories, data.failure_category_labels, true);

                if (Array.isArray(data.recent_payment_failures)) renderRecentPaymentFailures(data.recent_payment_failures);

                const tbody = document.getElementById('recent-events-tbody');
                const events = data.recent_events || [];
                const sourceLabels = data.payment_source_labels || {};
                const failureLabels = data.failure_category_labels || {};
                if (tbody) {
                    if (events.length === 0) {
                        tbody.innerHTML = '<tr id="no-events-row"><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhuma consulta registrada até o momento.</td></tr>';
                    } else {
                        tbody.innerHTML = events.map(ev => {
                            const dualTs = formatBrtUtcTimestamp(ev.timestamp || '', true);
                            const st = Number(ev.status || 200);
                            const rawType = ev.type || (ev.is_paid ? 'paid' : 'free');
                            let typeBadge = '<span style="color: var(--text-muted); font-size: 0.74rem;">GRATUITO</span>';
                            if (rawType === 'paid') typeBadge = '<span class="tag-paid">PAGO x402</span>';
                            else if (rawType === 'sandbox') typeBadge = '<span class="tag-status status-sandbox">SANDBOX (demo)</span>';
                            else if (rawType === 'challenge_402') typeBadge = '<span class="tag-status status-402">DESAFIO 402</span>';
                            else if (rawType === 'blocked_403') typeBadge = '<span class="tag-status status-403">BLOQUEADO 403</span>';

                            const fk = String(ev.failure_category || '');
                            const sk = String(ev.payment_source || '');
                            let orig = '<span style="color: var(--text-muted);">-</span>';
                            if (fk && fk !== 'unknown') orig = `<span class="tag-failure">${safeHtmlText(failureLabels[fk] || fk)}</span>`;
                            else if (sk && sk !== 'unknown') orig = `<span class="tag-source">${safeHtmlText(sourceLabels[sk] || sk)}</span>`;

                            const rawTx = String(ev.settlement_tx || ev.tx_hash || '');
                            const isHex = /^0x[a-f0-9]{64}$/i.test(rawTx);
                            const isSol = !isHex && /^[1-9A-HJ-NP-Za-km-z]{64,96}$/.test(rawTx);
                            const txHtml = isHex
                                ? `<a href="https://basescan.org/tx/${encodeURIComponent(rawTx)}" target="_blank" rel="noopener" class="tx-link">${safeHtmlText(rawTx.substr(0, 8))}...${safeHtmlText(rawTx.substr(-6))} ↗</a>`
                                : (isSol ? `<a href="https://solscan.io/tx/${encodeURIComponent(rawTx)}" target="_blank" rel="noopener" class="tx-link">${safeHtmlText(rawTx.substr(0, 8))}...${safeHtmlText(rawTx.substr(-6))} ↗</a>` : '<span style="color: var(--text-muted);">-</span>');

                            return `<tr class="event-row" data-type="${safeHtmlText(rawType)}">
                                <td style="white-space: nowrap;"><div>${safeHtmlText(dualTs.brt)}</div>${dualTs.utc ? `<div style="font-size: 0.7rem; color: var(--text-muted);">${safeHtmlText(dualTs.utc)}</div>` : ''}</td>
                                <td><strong>${safeHtmlText(ev.service || '')}</strong></td>
                                <td><span class="tag-status status-${st}">HTTP ${st}</span></td>
                                <td>${typeBadge}</td>
                                <td>${orig}</td>
                                <td>${ev.is_paid ? '$' + safeHtmlText(ev.price_usdc) : '-'}</td>
                                <td>${txHtml}</td>
                                <td style="color: var(--text-muted);">${safeHtmlText(ev.ip_masked || 'xxx.xxx.xxx.xxx')}</td>
                            </tr>`;
                        }).join('');
                    }
                }

                setTxt('last-updated-text', 'Última atualização: ' + new Date().toLocaleTimeString('pt-BR'));
                applyFeedFilterAndSearch();
            } catch (e) {
                console.error('Erro ao buscar stats em tempo real:', e);
            }
        }

        /* ====== Modais de Segurança, Telegram e RapidAPI (100% preservados) ====== */
        async function triggerTelegramTest() {
            const btn = document.getElementById('btn-test-telegram');
            if (!btn) return;
            const origText = btn.textContent;
            btn.disabled = true; btn.textContent = 'Enviando teste...';
            try {
                const csrfVal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const fd = new FormData(); fd.append('csrf_token', csrfVal);
                const res = await fetch('/admin?action=test_telegram', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfVal }, body: fd });
                const data = await res.json();
                if (data.telegram_status) renderTelegramStatusBadge(data.telegram_status);
                btn.textContent = data.success ? '✓ Alerta enviado!' : '✗ Falhou';
                if (!data.success && data.message) alert(data.message);
                setTimeout(() => { btn.textContent = origText; btn.disabled = false; }, 2500);
            } catch (err) {
                btn.textContent = '✗ Erro de rede';
                setTimeout(() => { btn.textContent = origText; btn.disabled = false; }, 2500);
            }
        }

        function openPasswordModal() {
            const ov = document.getElementById('password-modal-overlay');
            const al = document.getElementById('password-modal-alert');
            if (al) al.style.display = 'none';
            if (ov) ov.classList.add('active');
            document.getElementById('modal-curr-pass')?.focus();
        }
        function closePasswordModal() {
            document.getElementById('password-modal-overlay')?.classList.remove('active');
            document.getElementById('change-password-form')?.reset();
        }
        function handlePasswordOverlayClick(e) { if (e.target.id === 'password-modal-overlay') closePasswordModal(); }

        async function submitPasswordChange(e) {
            e.preventDefault();
            const newUser = document.getElementById('modal-new-user')?.value.trim() || '';
            const twoFactorVal = document.getElementById('modal-2fa-telegram')?.checked ? '1' : '0';
            const currPass = document.getElementById('modal-curr-pass').value;
            const newPass = document.getElementById('modal-new-pass').value;
            const confirmPass = document.getElementById('modal-confirm-pass').value;
            const alertBox = document.getElementById('password-modal-alert');
            const btn = document.getElementById('btn-submit-password');
            if (newPass !== '' && newPass !== confirmPass) {
                alertBox.style.display = 'block'; alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'A nova senha e a confirmação não coincidem.'; return;
            }
            btn.disabled = true; btn.textContent = 'Salvando...';
            try {
                const csrfVal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const fd = new FormData();
                fd.append('current_password', currPass); fd.append('new_password', newPass); fd.append('new_password_confirm', confirmPass);
                fd.append('new_username', newUser); fd.append('two_factor_telegram', twoFactorVal); fd.append('csrf_token', csrfVal);
                const res = await fetch('/admin?action=change_password', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfVal }, body: fd });
                const data = await res.json();
                alertBox.style.display = 'block';
                if (data.success) {
                    alertBox.style.background = 'rgba(16, 185, 129, 0.15)'; alertBox.style.color = '#34D399';
                    alertBox.textContent = data.message || 'Segurança atualizada com sucesso!';
                    const secTextEl = document.getElementById('runtime-security-text');
                    if (secTextEl && typeof data.two_factor_telegram === 'boolean') {
                        secTextEl.textContent = (data.two_factor_telegram ? '2FA Telegram ativo' : '2FA desativado') + ' · 30m timeout · IP/UA vinculado';
                    }
                    setTimeout(closePasswordModal, 1500);
                } else {
                    alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                    alertBox.textContent = data.error || 'Não foi possível atualizar as configurações.';
                }
            } catch (err) {
                alertBox.style.display = 'block'; alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'Erro ao conectar ao servidor: ' + err.message;
            } finally { btn.disabled = false; btn.textContent = 'Salvar Segurança'; }
        }

        function openTelegramModal() {
            const al = document.getElementById('telegram-modal-alert'); if (al) al.style.display = 'none';
            document.getElementById('telegram-modal-overlay')?.classList.add('active');
            document.getElementById('modal-tg-token')?.focus();
        }
        function closeTelegramModal() {
            document.getElementById('telegram-modal-overlay')?.classList.remove('active');
            document.getElementById('telegram-config-form')?.reset();
        }
        function handleTelegramOverlayClick(e) { if (e.target.id === 'telegram-modal-overlay') closeTelegramModal(); }

        async function submitTelegramConfig(e) {
            e.preventDefault();
            const tokenVal = document.getElementById('modal-tg-token').value.trim();
            const chatVal = document.getElementById('modal-tg-chat').value.trim();
            const alertBox = document.getElementById('telegram-modal-alert');
            const btn = document.getElementById('btn-submit-telegram');
            btn.disabled = true; btn.textContent = 'Salvando e testando...';
            try {
                const csrfVal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const fd = new FormData(); fd.append('bot_token', tokenVal); fd.append('chat_id', chatVal); fd.append('csrf_token', csrfVal);
                const res = await fetch('/admin?action=save_telegram_config', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfVal }, body: fd });
                const data = await res.json();
                if (data.telegram_status) renderTelegramStatusBadge(data.telegram_status);
                alertBox.style.display = 'block';
                if (data.success && data.test_sent) {
                    alertBox.style.background = 'rgba(16, 185, 129, 0.15)'; alertBox.style.color = '#34D399';
                    alertBox.textContent = data.message || 'Credenciais salvas e alerta de teste entregue!';
                    setTimeout(closeTelegramModal, 1500);
                } else if (data.success) {
                    alertBox.style.background = 'rgba(245, 158, 11, 0.15)'; alertBox.style.color = '#FBBF24';
                    alertBox.textContent = data.message || 'Credenciais salvas, mas o teste falhou.';
                } else {
                    alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                    alertBox.textContent = data.error || 'Não foi possível salvar as credenciais do Telegram.';
                }
            } catch (err) {
                alertBox.style.display = 'block'; alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'Erro ao conectar ao servidor: ' + err.message;
            } finally { btn.disabled = false; btn.textContent = 'Salvar e Testar'; }
        }

        function openRapidApiModal() {
            const al = document.getElementById('rapidapi-modal-alert'); if (al) al.style.display = 'none';
            document.getElementById('rapidapi-modal-overlay')?.classList.add('active');
            document.getElementById('modal-rapidapi-secret')?.focus();
        }
        function closeRapidApiModal() {
            document.getElementById('rapidapi-modal-overlay')?.classList.remove('active');
            document.getElementById('rapidapi-config-form')?.reset();
        }
        function handleRapidApiOverlayClick(e) { if (e.target.id === 'rapidapi-modal-overlay') closeRapidApiModal(); }

        async function submitRapidApiConfig(e) {
            e.preventDefault();
            const secretVal = document.getElementById('modal-rapidapi-secret').value.trim();
            const alertBox = document.getElementById('rapidapi-modal-alert');
            const btn = document.getElementById('btn-submit-rapidapi');
            btn.disabled = true; btn.textContent = 'Salvando...';
            try {
                const csrfVal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const fd = new FormData(); fd.append('proxy_secret', secretVal); fd.append('csrf_token', csrfVal);
                const res = await fetch('/admin?action=save_rapidapi_config', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfVal }, body: fd });
                const data = await res.json();
                if (data.rapidapi_status) renderRapidApiStatusBadge(data.rapidapi_status);
                alertBox.style.display = 'block';
                if (data.success) {
                    alertBox.style.background = 'rgba(16, 185, 129, 0.15)'; alertBox.style.color = '#34D399';
                    alertBox.textContent = data.message || 'RapidAPI Bridge ativado com sucesso!';
                    setTimeout(closeRapidApiModal, 1500);
                } else {
                    alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                    alertBox.textContent = data.error || 'Não foi possível salvar o segredo RapidAPI.';
                }
            } catch (err) {
                alertBox.style.display = 'block'; alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'Erro ao conectar ao servidor: ' + err.message;
            } finally { btn.disabled = false; btn.textContent = 'Salvar'; }
        }

        async function clearRapidApiConfig() {
            const alertBox = document.getElementById('rapidapi-modal-alert');
            const btn = document.getElementById('btn-clear-rapidapi');
            btn.disabled = true; btn.textContent = 'Removendo...';
            try {
                const csrfVal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const fd = new FormData(); fd.append('clear', '1'); fd.append('csrf_token', csrfVal);
                const res = await fetch('/admin?action=save_rapidapi_config', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfVal }, body: fd });
                const data = await res.json();
                if (data.rapidapi_status) renderRapidApiStatusBadge(data.rapidapi_status);
                alertBox.style.display = 'block';
                if (data.success) {
                    alertBox.style.background = 'rgba(16, 185, 129, 0.15)'; alertBox.style.color = '#34D399';
                    alertBox.textContent = data.message || 'Configuração RapidAPI removida.';
                    const inp = document.getElementById('modal-rapidapi-secret'); if (inp) inp.value = '';
                } else {
                    alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                    alertBox.textContent = data.error || 'Não foi possível remover a configuração.';
                }
            } catch (err) {
                alertBox.style.display = 'block'; alertBox.style.background = 'rgba(239, 68, 68, 0.15)'; alertBox.style.color = '#FCA5A5';
                alertBox.textContent = 'Erro ao conectar ao servidor: ' + err.message;
            } finally { btn.disabled = false; btn.textContent = 'Desativar'; }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePasswordModal();
                closeTelegramModal();
                closeRapidApiModal();
            }
        });

        /* ====== Inicialização ====== */
        syncTabFromHash();
        renderDailyChart14d(currentDailyHistory);
        applyFeedFilterAndSearch();
        fetchLiveStats();
        fetchGrowthAdmin();
        setInterval(fetchLiveStats, 5000);
        setInterval(fetchGrowthAdmin, 15000);
    </script>
    <footer style="max-width: 1320px; margin: 1.5rem auto 1.5rem; padding: 1.1rem 1.25rem 0; border-top: 1px solid rgba(255,255,255,0.08); text-align: center; font-size: 0.76rem; color: #64748B;">
        © <?php echo date('Y'); ?> Grid402 Network Intelligence. Todos os direitos reservados • Software e Arquitetura Protegidos (Lei nº 9.609/1998 e Lei nº 9.610/1998).
    </footer>
</body>
</html>
