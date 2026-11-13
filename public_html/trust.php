<?php
/**
 * Grid402 - Trust Center
 * SLA, disponibilidade histórica, metodologia e política de incidentes.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';

$summaryRaw = @file_get_contents(GRID402_FILE_FREE);
$summary = $summaryRaw ? (@json_decode($summaryRaw, true) ?: []) : [];
$services = is_array($summary['services'] ?? null) ? $summary['services'] : [];
$servicesUp = 0;
foreach ($services as $s) {
    if (($s['status'] ?? '') === 'UP') $servicesUp++;
}

$metrics = grid402_get_metrics();
$meta = $metrics;
$statusCounts = is_array($metrics['status_counts'] ?? null) ? $metrics['status_counts'] : [];
$total = (int)($metrics['total_requests'] ?? 0);
$ok = (int)($statusCounts['200'] ?? 0);
$sla = $total > 0 ? round(($ok / $total) * 100, 2) : 100.0;
$error4xx = (int)($statusCounts['402'] ?? 0) + (int)($statusCounts['403'] ?? 0);
$checkedAt = $summary['timestamp'] ?? null;
$servicesTotal = count(grid402_get_utility_services());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SLA, disponibilidade histórica e metodologia pública do Grid402.">
    <title>Grid402 // Trust Center</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --ok:#10B981; --primary:#00F0FF; --warn:#F59E0B; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.6; }
        .container { max-width:980px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        h1 { font-size:2rem; letter-spacing:-.04em; margin-bottom:.25rem; }
        h2 { color:var(--primary); font-size:1.2rem; margin-top:2.2rem; }
        p { color:var(--muted); }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin:1.5rem 0; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:1rem; }
        .value { font-size:1.6rem; font-weight:700; color:var(--text); }
        .ok { color:var(--ok); }
        code { color:var(--primary); }
        a { color:var(--primary); }
        li { color:#CBD5E1; margin:.25rem 0; }
    </style>
</head>
<body>
<div class="container">
    <p><a href="/">← Grid402</a></p>
    <h1>Trust Center</h1>
    <p>Informação pública e verificável sobre disponibilidade, metodologia e política de incidentes da plataforma.</p>

    <div class="cards">
        <div class="card"><div class="value ok"><?php echo $sla; ?>%</div><div>SLA técnico — HTTP 200 sobre o total de chamadas registradas</div></div>
        <div class="card"><div class="value"><?php echo $servicesUp; ?>/<?php echo $servicesTotal; ?></div><div>Serviços UP no snapshot operacional</div></div>
        <div class="card"><div class="value"><?php echo $total; ?></div><div>Requisições registradas (telemetria interna anonimizada)</div></div>
        <div class="card"><div class="value"><?php echo $error4xx; ?></div><div>Desafios x402 + bloqueios registrados</div></div>
    </div>

    <h2>Status ao vivo</h2>
    <p>Última atualização do snapshot: <code><?php echo htmlspecialchars((string)($checkedAt ?: 'n/d')); ?></code>. Para checar agora, veja <a href="/status">/status</a> (grátis, sem autenticação).</p>

    <h2>Metodologia</h2>
    <ul>
        <li><strong>Fonte pública:</strong> chamadas HTTP são roteadas para microserviços; cada detalhe é pago e acompanhado por telemetria anônima.</li>
        <li><strong>Origem dos dados:</strong> Receita Federal, IBGE, BACEN, ANBIMA/Febraban, CGU, CEIS/CNEP, GLEIF/LEI, OFAC/ONU, ECB, registros IETF (TLS/DNS/BGP).</li>
        <li><strong>LGPD/GDPR:</strong> somente CNPJ/entidades e fatos públicos são processados; dados pessoais de sócios/titulares não são armazenados ou retornados.</li>
        <li><strong>Freshness:</strong> feeds de mercado são atualizados por um cron dedicado; valores on-chain e dados de registro são consultados em tempo real.</li>
    </ul>

    <h2>Política de incidentes e histórico</h2>
    <ul>
        <li><strong>S0</strong> — indisponibilidade total: acionamento imediato e substituição por snapshot público.</li>
        <li><strong>S1</strong> — degradação de serviço específico: fail-closed com erro claro (402/5xx); sem dados inventados.</li>
        <li><strong>Postmortem:</strong> causa raiz, impacto e ação corretiva são publicados em até 5 dias úteis.</li>
        <li><strong>Histórico:</strong> problemas conhecidos são divulgados em <a href="/status">/status</a> e nesta página após resolução.</li>
    </ul>

    <h2>Decisões judiciais, crédito e conformidade</h2>
    <p><strong>Grid402 não toma decisões de crédito, cadastro, aprovação ou negação.</strong> É infraestrutura de dados públicos e sinais de conformidade. A empresa contratante deve validar sua própria política de risco, CVM, Bacen, LGPD e critérios internos antes de usar qualquer resultado como única base de decisão.</p>
    <p>Para registro e rastreabilidade de dados, use <code>_oracle_proof</code> e <code>/api/v1/verify</code>.</p>

    <p><strong>Contato de segurança e DPO:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a></p>
</div>
</body>
</html>
