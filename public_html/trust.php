<?php
/**
 * Grid402 - Trust Center
 * SLA, disponibilidade histórica, metodologia e política de incidentes.
 */

declare(strict_types=1);

define('GRID402_EXEC', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/metrics.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');

$catalogServices = grid402_get_utility_services();
$summaryRaw = @file_get_contents(GRID402_FILE_FREE);
$summary = $summaryRaw ? (@json_decode($summaryRaw, true) ?: []) : [];
$services = is_array($summary['services'] ?? null) ? $summary['services'] : [];
$servicesTotal = count($catalogServices);
$servicesUp = 0;
foreach ($catalogServices as $svcId => $def) {
    $st = is_array($services[$svcId] ?? null) ? ($services[$svcId]['status'] ?? 'UP') : 'UP';
    if ($st === 'UP') {
        $servicesUp++;
    }
}

$uptime30d = grid402_get_uptime_history_30d();
$uptimePct30d = (float)($uptime30d['overall_uptime_pct'] ?? 100.0);
$uptimeDays = is_array($uptime30d['days'] ?? null) ? $uptime30d['days'] : [];

$metrics = grid402_get_metrics();
$statusCounts = is_array($metrics['status_counts'] ?? null) ? $metrics['status_counts'] : [];
$total = (int)($metrics['total_requests'] ?? 0);
$ok = (int)($statusCounts['200'] ?? 0);
$error5xx = (int)($statusCounts['500'] ?? 0) + (int)($statusCounts['502'] ?? 0) + (int)($statusCounts['503'] ?? 0);
$availabilityHttp = $total > 0 ? round((($total - $error5xx) / $total) * 100, 2) : 100.0;
$error4xx = (int)($statusCounts['402'] ?? 0) + (int)($statusCounts['403'] ?? 0);
$checkedAt = $summary['timestamp'] ?? gmdate('Y-m-d\TH:i:s\Z');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Trust Center Grid402: Identidade institucional, SLA técnico, série histórica de disponibilidade de 30 dias, proveniência de fontes públicas e governança M2M.">
    <title>Grid402 // Trust Center, Governança &amp; Proveniência</title>
    <style>
        :root { --bg:#060B19; --surface:#0B132B; --border:#1E2C4F; --text:#F8FAFC; --muted:#94A3B8; --ok:#10B981; --primary:#00F0FF; --warn:#F59E0B; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; line-height:1.6; }
        .container { max-width:1060px; margin:0 auto; padding:2rem 1.25rem 4rem; }
        h1 { font-size:2rem; letter-spacing:-.04em; margin-bottom:.25rem; }
        h2 { color:var(--primary); font-size:1.25rem; margin-top:2.2rem; }
        p { color:var(--muted); }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; margin:1.5rem 0; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:1rem; }
        .value { font-size:1.6rem; font-weight:700; color:var(--text); }
        .ok { color:var(--ok); }
        code { color:var(--primary); font-family:'JetBrains Mono',monospace; font-size:.88rem; }
        a { color:var(--primary); }
        li { color:#CBD5E1; margin:.25rem 0; }
        .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:10px; margin:1rem 0; }
        table { width:100%; border-collapse:collapse; min-width:720px; }
        th, td { text-align:left; padding:.65rem .75rem; border-bottom:1px solid var(--border); vertical-align:top; font-size:.9rem; }
        th { color:#CBD5E1; background:#111C38; font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; }
        tr:last-child td { border-bottom:0; }
        .uptime-strip { display:grid; grid-template-columns:repeat(30,1fr); gap:4px; margin:.75rem 0; }
        .uptime-bar { height:28px; border-radius:4px; background:var(--ok); opacity:.9; }
        .uptime-bar.degraded { background:var(--warn); }
        .notice-box { background:rgba(0,240,255,.06); border:1px solid rgba(0,240,255,.28); border-radius:10px; padding:1rem 1.2rem; margin:1.2rem 0; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/nav.php'; grid402_render_public_nav(['active' => '/trust']); ?>
<div class="container">
    <h1>Trust Center, Governança &amp; Proveniência</h1>
    <p>Transparência institucional, disponibilidade histórica verificável (30 dias), matriz de fontes públicas oficiais, limites de escopo e política de segurança do oráculo M2M <strong>Grid402</strong> (<code>grid402.online</code>).</p>

    <div class="cards">
        <div class="card"><div class="value ok"><?php echo number_format($uptimePct30d, 2); ?>%</div><div>Uptime Histórico (30 dias) · Meta Best-Effort: 99,5%</div></div>
        <div class="card"><div class="value ok"><?php echo $servicesUp; ?>/<?php echo $servicesTotal; ?></div><div>Serviços Operacionais (UP) no Snapshot Atual</div></div>
        <div class="card"><div class="value ok"><?php echo number_format($availabilityHttp, 2); ?>%</div><div>Taxa de Sucesso Sem Erros 5xx (<?php echo $ok; ?> respostas 200)</div></div>
        <div class="card"><div class="value"><?php echo $error4xx; ?></div><div>Desafios HTTP 402 Emitidos + Replay Bloqueados</div></div>
    </div>

    <div class="notice-box">
        <strong>Nota de Desambiguação de Marca (<code>grid402.online</code>):</strong><br>
        O <strong>Grid402</strong> (operado exclusivamente no domínio canônico <code>https://grid402.online</code>) é uma infraestrutura independente de <strong>Oráculo de Dados Públicos e Utilitários de Rede M2M liquidados via HTTP 402 na blockchain Base (Chain ID 8453)</strong>. Não possui qualquer vínculo societário, técnico ou operacional com projetos homônimos de distribuição de energia elétrica (<em>smart-grid / physical power grid</em>) ou iniciativas acadêmicas de terceiros.
    </div>

    <h2>1. Identificação Institucional e Governança do Operador</h2>
    <div class="card">
        <ul>
            <li><strong>Operador e Mantenedor:</strong> Grid402 Network Intelligence (Responsável Técnico: Thiago Campos — São Paulo, SP, Brasil).</li>
            <li><strong>Carteira Oficial do Oráculo e Recebedora (Base Mainnet - Chain ID 8453):</strong> <code><?php echo htmlspecialchars(GRID402_WALLET_ADDRESS); ?></code> (<a href="https://basescan.org/address/<?php echo htmlspecialchars(GRID402_WALLET_ADDRESS); ?>" target="_blank" rel="noopener noreferrer">Auditar no BaseScan</a>).</li>
            <li><strong>Política de Disponibilidade (SLA):</strong> Operação sob regime <em>Best-Effort</em> com meta técnica de disponibilidade de <strong>99,5%</strong> nas rotas de oráculo e cache local resiliente para proteção contra oscilações das fontes governamentais upstream.</li>
            <li><strong>Repositório Oficial e Código Aberto dos SDKs/MCP:</strong> <a href="https://github.com/oliveiratc/Grid402" target="_blank" rel="noopener noreferrer">github.com/oliveiratc/Grid402</a> · Pacotes oficiais: <a href="https://www.npmjs.com/package/grid402" target="_blank" rel="noopener noreferrer">npm: grid402</a>, <a href="https://www.npmjs.com/package/grid402-mcp" target="_blank" rel="noopener noreferrer">npm: grid402-mcp</a>, <a href="https://pypi.org/project/grid402/" target="_blank" rel="noopener noreferrer">PyPI: grid402</a>.</li>
            <li><strong>Contato de Suporte, DPO (LGPD/GDPR) e Disclosure de Vulnerabilidades:</strong> <a href="mailto:contato@grid402.online">contato@grid402.online</a> · Manifesto de segurança: <a href="/.well-known/security.txt">/.well-known/security.txt</a>.</li>
        </ul>
    </div>

    <h2>2. Série Histórica de Disponibilidade (Últimos 30 Dias)</h2>
    <p>Monitoramento contínuo dos <?php echo $servicesTotal; ?> micro-serviços. Última sincronização: <code><?php echo htmlspecialchars((string)$checkedAt); ?></code> (<a href="/status">ver JSON em /status</a>).</p>
    <div class="uptime-strip" role="img" aria-label="Histórico de disponibilidade dos últimos 30 dias">
        <?php foreach ($uptimeDays as $day):
            $dayStatus = (string)($day['status'] ?? 'UP');
            $dayDate = (string)($day['date'] ?? '');
            $dayPct = number_format((float)($day['uptime_pct'] ?? 100.0), 1);
            $dayUp = (int)($day['services_up'] ?? $servicesTotal);
            $dayTot = (int)($day['services_total'] ?? $servicesTotal);
        ?>
            <div class="uptime-bar <?php echo $dayStatus !== 'UP' ? 'degraded' : ''; ?>" title="<?php echo htmlspecialchars("{$dayDate}: {$dayPct}% ({$dayUp}/{$dayTot} UP)"); ?>"></div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.82rem;color:var(--muted);">
        <span>30 dias atrás (<?php echo htmlspecialchars((string)($uptimeDays[0]['date'] ?? '')); ?>)</span>
        <span>Média 30d: <strong class="ok"><?php echo number_format($uptimePct30d, 2); ?>%</strong></span>
        <span>Hoje (<?php echo htmlspecialchars((string)($uptimeDays[count($uptimeDays)-1]['date'] ?? gmdate('Y-m-d'))); ?>)</span>
    </div>

    <h2>3. Escopo Transparente: Dados Públicos Oficiais vs. Bureaus Privados de Crédito</h2>
    <p>O Grid402 posiciona-se com transparência absoluta sobre o que entrega e o que não faz parte do seu escopo:</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Dimensão</th>
                    <th>O que o Grid402 Entrega (Oráculo Público M2M)</th>
                    <th>O que Exige Bureau de Crédito Privado (Fora do Escopo)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Fontes de Dados</strong></td>
                    <td>Registros governamentais abertos (Receita Federal CNPJ/Simples, CGU CEIS/CNEP, BCB PTAX/SGS, IBGE, GLEIF LEI CC0, US Treasury OFAC SDN, BCE, DNS/TLS/BGP).</td>
                    <td>Bancos de dados privados de inadimplência comercial, protestos cartorários privados, cheques sem fundo e histórico bancário fechado (ex.: Serasa, SPC, Boa Vista, Quod).</td>
                </tr>
                <tr>
                    <td><strong>Garantia e Prova</strong></td>
                    <td>Normalização determinística em JSON + bloco de proveniência <code>_meta</code> + assinatura criptográfica <strong>ECDSA secp256k1</strong> (<code>_oracle_proof</code>) auditável offline.</td>
                    <td>Score de crédito proprietário fechado sem assinatura criptográfica pública de oráculo.</td>
                </tr>
                <tr>
                    <td><strong>Privacidade (LGPD/GDPR)</strong></td>
                    <td>Arquitetura estritamente <strong>Zero-PII</strong>: processa exclusivamente Pessoas Jurídicas e fatos macroeconômicos/rede, filtrando CPFs e sócios na origem.</td>
                    <td>Consultas a CPF de pessoas naturais, renda presumida de indivíduos e histórico de consumo pessoal.</td>
                </tr>
                <tr>
                    <td><strong>Modelo Comercial</strong></td>
                    <td>Zero cadastro, zero mensalidade. Liquidação por requisição via HTTP 402 (<code>0.0050 a 0.0300 USDC</code>) ou Session Pass pré-pago (<code>1 crédito = 0.0050 USDC</code>).</td>
                    <td>Contratos anuais com franquia mínima mensal, homologação cadastral manual e faturamento pós-pago.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>4. Matriz de Proveniência e Freshness (TTL) dos 22 Serviços</h2>
    <p>Cada resposta HTTP 200 paga ou de sandbox inclui o objeto <code>_meta</code> assinado junto ao <code>_oracle_proof</code>, atestando a fonte upstream exata e a política de cache:</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Serviço</th>
                    <th>Fonte Oficial Upstream</th>
                    <th>Cache TTL</th>
                    <th>Tarifa (USDC / Pass)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catalogServices as $svcId => $svc):
                $price = (string)($svc['price_usdc'] ?? GRID402_PRICE_DISPLAY);
                $credits = ($price === 'Gratuito') ? '0 créditos' : (($svcId === 'pass') ? '400 a 6.000 créditos' : ((int)ceil(((float)$price * 1000000) / 5000) . ' crédito(s)'));
            ?>
                <tr>
                    <td><code><?php echo htmlspecialchars((string)$svc['endpoint']); ?></code></td>
                    <td><strong><?php echo htmlspecialchars((string)$svc['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)($svc['upstream_source'] ?? 'Fonte Pública Oficial')); ?></td>
                    <td><code><?php echo (int)($svc['cache_ttl_sec'] ?? 0); ?>s</code></td>
                    <td><code><?php echo htmlspecialchars($price); ?></code> <span style="color:var(--muted);font-size:.82rem;">(<?php echo htmlspecialchars($credits); ?>)</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>5. Histórico de Versões e Preços (Changelog Público)</h2>
    <div class="card">
        <ul>
            <li><strong>v3.1.0 (2026-11):</strong> Unificação da matemática oficial do Session Pass (<code>1 crédito = 0.0050 USDC</code>: Starter 2 USDC = 400 créditos, Pro 5 USDC = 1.000 créditos, Fleet 20 USDC = 6.000 créditos), suporte a <code>Idempotency-Key</code> (TTL 300s), Spend Controls por passe (<code>daily_credit_limit</code>, <code>allowed_endpoints</code> e <code>GET /api/v1/pass/balance</code>), injeção do bloco <code>_meta</code> assinado, série histórica de 30 dias e cobertura de 22 ferramentas no servidor MCP.</li>
            <li><strong>v3.0.0 (2026-11):</strong> Expansão para 22 serviços com endpoints compostos (<code>/api/v1/domain-audit</code>, <code>/api/v1/kyb-audit</code>, <code>/api/v1/market-snapshot</code>), integração nativa com facilitador x402 v2 (EIP-3009) e checkout híbrido Pix/Cartão + USDC.</li>
            <li><strong>v2.4.0 (2026-10):</strong> Lançamento dos endpoints globais GLEIF LEI (CC0), OFAC SDN + AML Web3 na Base, Notarização ECDSA secp256k1 e Calendário Mundial BCE.</li>
        </ul>
    </div>

    <h2>6. Política de Incidentes e Responsabilidade</h2>
    <ul>
        <li><strong>S0 (Indisponibilidade Crítica):</strong> acionamento imediato e fallback automático para snapshot assinado em cache com indicação explícita de timestamp em <code>_meta.fetched_at</code>.</li>
        <li><strong>S1 (Degradação de Fonte Governamental Externa):</strong> comportamento <em>fail-closed</em> com erro estruturado claro, sem geração de dados sintéticos e sem consumo indevido de créditos em falhas 5xx.</li>
        <li><strong>Isenção de Decisão de Crédito:</strong> O Grid402 fornece dados técnicos públicos verificáveis e não realiza concessão de crédito, aconselhamento jurídico ou aprovação cadastral.</li>
    </ul>
</div>
</body>
</html>
