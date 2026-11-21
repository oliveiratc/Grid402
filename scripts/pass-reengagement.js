/**
 * Grid402 — Session Pass Reengagement Script
 *
 * Lê public_html/private_data/session_passes.json, identifica passes
 * inativos (usage_count = 0 ou sem uso há > 7 dias) e gera uma fila
 * de notificações em public_html/private_data/pass_reengagement_queue.json.
 *
 * Uso:
 *   node scripts/pass-reengagement.js [--dry-run]
 *
 * Pode ser executado via cron (ex: diário às 09:00 UTC):
 *   0 9 * * * /usr/bin/node /caminho/para/Grid402/scripts/pass-reengagement.js
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const SESSION_PASSES_PATH = path.join(ROOT, 'public_html', 'private_data', 'session_passes.json');
const QUEUE_PATH = path.join(ROOT, 'public_html', 'private_data', 'pass_reengagement_queue.json');

// Limiares (dias)
const INACTIVE_DAYS = 7;      // sem uso há mais de N dias = inativo
const EXPIRE_SOON_DAYS = 7;   // expira em menos de N dias = urgente
const EXPIRE_WARN_DAYS = 14;  // expira em menos de N dias = aviso

const now = new Date();
const today = now.toISOString().slice(0, 10);

function parseDate(str) {
  if (!str) return null;
  return new Date(str);
}

function daysBetween(d1, d2) {
  const ms = d2 - d1;
  return ms / (1000 * 60 * 60 * 24);
}

function tierLabel(tier) {
  const labels = {
    starter: 'Starter',
    pro: 'Pro',
    fleet: 'Fleet',
    standard: 'Standard',
  };
  return labels[tier] || tier;
}

function buildMessage(pass, reason, daysLeft) {
  const tier = tierLabel(pass.tier);
  const credits = pass.credits_remaining ?? pass.credits_total ?? 0;
  const usdc = parseFloat(pass.usdc_deposited ?? 0);

  switch (reason) {
    case 'never_used':
      return `Seu ${tier} Pass (${usdc} USDC / ${credits} créditos) foi emitido em ${pass.issued_at.slice(0,10)} e ainda não foi usado. Comece agora: acesse https://grid402.online/pass e consulte serviços como forex, cnpj, dossier.`;
    case 'expire_soon':
      return `Atenção: seu ${tier} Pass expira em ${daysLeft} dias (${pass.expires_at.slice(0,10)}). Ainda restam ${credits} créditos. Use agora antes que expirem.`;
    case 'expire_warn':
      return `Seu ${tier} Pass expira em ${daysLeft} dias (${pass.expires_at.slice(0,10)}). Não deixe seus ${credits} créditos parados.`;
    case 'inactive':
      return `Seu ${tier} Pass não é usado há mais de ${INACTIVE_DAYS} dias. Ainda restam ${credits} créditos. Retome suas consultas em https://grid402.online/pass.`;
    default:
      return `Seu ${tier} Pass tem ${credits} créditos disponíveis.`;
  }
}

function main() {
  const dryRun = process.argv.includes('--dry-run');

  let raw;
  try {
    raw = fs.readFileSync(SESSION_PASSES_PATH, 'utf8');
  } catch (e) {
    console.error(`[ERRO] Não foi possível ler ${SESSION_PASSES_PATH}`);
    process.exit(1);
  }

  const passes = JSON.parse(raw);
  const queue = [];
  let totalUsdc = 0;
  let totalCredits = 0;

  for (const [passId, p] of Object.entries(passes)) {
    const usageCount = p.usage_count ?? 0;
    const creditsRemaining = p.credits_remaining ?? p.credits_total ?? 0;
    const creditsTotal = p.credits_total ?? 0;
    const usdc = parseFloat(p.usdc_deposited ?? 0);
    const issuedAt = p.issued_at ?? null;
    const expiresAt = p.expires_at ?? null;
    const lastUsedAt = p.last_used_at ?? null;

    // Pula passes que já foram usados e ainda têm créditos (ativos)
    if (usageCount > 0 && lastUsedAt) {
      const lastUsed = parseDate(lastUsedAt);
      const daysIdle = daysBetween(lastUsed, now);
      if (daysIdle <= INACTIVE_DAYS) continue; // ativo recente
    }

    // Determina razão da notificação
    let reason = null;
    let daysLeft = null;
    let priority = 'baixa';

    const expires = parseDate(expiresAt);
    if (expires) {
      daysLeft = Math.round(daysBetween(now, expires));
    }

    // 1. Nunca usado
    if (usageCount === 0) {
      reason = 'never_used';
      priority = 'alta';
    }
    // 2. Expira em breve
    else if (daysLeft !== null && daysLeft <= EXPIRE_SOON_DAYS && daysLeft > 0) {
      reason = 'expire_soon';
      priority = 'alta';
    }
    // 3. Expira em até 14 dias
    else if (daysLeft !== null && daysLeft <= EXPIRE_WARN_DAYS && daysLeft > EXPIRE_SOON_DAYS) {
      reason = 'expire_warn';
      priority = 'media';
    }
    // 4. Inativo há mais de 7 dias
    else if (lastUsedAt) {
      const lastUsed = parseDate(lastUsedAt);
      const daysIdle = Math.round(daysBetween(lastUsed, now));
      if (daysIdle > INACTIVE_DAYS) {
        reason = 'inactive';
        priority = 'media';
      }
    }

    if (!reason) continue;

    totalUsdc += usdc;
    totalCredits += creditsRemaining;

    queue.push({
      pass_id: passId,
      pass_token: p.pass_token ?? null,
      tier: p.tier ?? 'standard',
      usdc_deposited: usdc,
      credits_total: creditsTotal,
      credits_remaining: creditsRemaining,
      usage_count: usageCount,
      issued_at: issuedAt,
      expires_at: expiresAt,
      last_used_at: lastUsedAt,
      days_until_expiry: daysLeft,
      reason,
      priority,
      message: buildMessage(p, reason, daysLeft),
      suggested_action: reason === 'never_used'
        ? 'primeiro_uso'
        : reason.startsWith('expire')
        ? 'usar_antes_expirar'
        : 'reativar',
      created_at: new Date().toISOString(),
    });
  }

  // Ordena por prioridade e proximidade de expiração
  const prioOrder = { alta: 0, media: 1, baixa: 2 };
  queue.sort((a, b) => {
    if (prioOrder[a.priority] !== prioOrder[b.priority]) {
      return prioOrder[a.priority] - prioOrder[b.priority];
    }
    return (a.days_until_expiry ?? 999) - (b.days_until_expiry ?? 999);
  });

  const output = {
    generated_at: new Date().toISOString(),
    summary: {
      total_pass_ativos_ociosos: queue.length,
      usdc_ocioso: parseFloat(totalUsdc.toFixed(4)),
      creditos_ociosos: totalCredits,
      por_prioridade: {
        alta: queue.filter(q => q.priority === 'alta').length,
        media: queue.filter(q => q.priority === 'media').length,
        baixa: queue.filter(q => q.priority === 'baixa').length,
      },
      por_motivo: {
        never_used: queue.filter(q => q.reason === 'never_used').length,
        expire_soon: queue.filter(q => q.reason === 'expire_soon').length,
        expire_warn: queue.filter(q => q.reason === 'expire_warn').length,
        inactive: queue.filter(q => q.reason === 'inactive').length,
      },
    },
    queue,
  };

  if (dryRun) {
    console.log(JSON.stringify(output.summary, null, 2));
    console.log('\n--- PRIMEIROS 10 ITENS DA FILA ---');
    console.log(JSON.stringify(output.queue.slice(0, 10), null, 2));
    return;
  }

  // Garante diretório
  const dir = path.dirname(QUEUE_PATH);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }

  fs.writeFileSync(QUEUE_PATH, JSON.stringify(output, null, 2) + '\n', 'utf8');

  console.log(`[OK] Fila gerada: ${QUEUE_PATH}`);
  console.log(`[OK] Total de passes ociosos: ${output.summary.total_pass_ativos_ociosos}`);
  console.log(`[OK] USDC ocioso: ${output.summary.usdc_ocioso} USDC`);
  console.log(`[OK] Créditos ociosos: ${output.summary.creditos_ociosos}`);
  console.log(`[OK] Alta prioridade: ${output.summary.por_prioridade.alta}`);
  console.log(`[OK] Média prioridade: ${output.summary.por_prioridade.media}`);
  console.log(`[OK] Baixa prioridade: ${output.summary.por_prioridade.baixa}`);
}

main();
