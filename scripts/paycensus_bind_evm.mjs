#!/usr/bin/env node
/**
 * Vincula um endereco EVM (ex.: 0xa592...f90) ao perfil verificado PayCensus do grid402.online.
 *
 * Spec oficial (https://trust.paycensus.com/verify.html):
 *   msg     = "paycensus-addr-proof=" + "paycensus-verify=<nonce>" (UTF-8 exato)
 *   sign    = EIP-191 personal_sign (ethers Wallet.signMessage / window.ethereum personal_sign)
 *   POST    https://trust.paycensus.com/api/verify/addr-proof
 *           {"vid": 12, "signature": "0x..."}
 *
 * Prioridade para obtencao da assinatura:
 *   1. --signature 0x...   (assinatura ja gerada em carteira externa)
 *   2. --browser           (abre UI local em 127.0.0.1 para MetaMask/Rabby/Ledger)
 *   3. --key-file CAMINHO  (arquivo contendo chave privada hex de 32 bytes)
 *   4. env GRID402_EVM_SIGNING_KEY (carregada do shell ou .env / private_data/.env)
 *
 * Uso:
 *   node scripts/paycensus_bind_evm.mjs --print-message
 *   node scripts/paycensus_bind_evm.mjs --browser
 *   node scripts/paycensus_bind_evm.mjs --browser --probe-alt
 *   node scripts/paycensus_bind_evm.mjs --signature 0x... --dry-run
 */
import crypto from "node:crypto";
import dns from "node:dns/promises";
import fs from "node:fs";
import http from "node:http";
import path from "node:path";
import { execFileSync, spawn } from "node:child_process";
import { fileURLToPath, pathToFileURL } from "node:url";
import { Signature, Wallet, getAddress, verifyMessage } from "ethers";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
export const REPO_ROOT = path.dirname(__dirname);

export const DEFAULT_VID = 12;
export const DEFAULT_NONCE = "paycensus-verify=c05e1ee27fe0c75e2b907b9d842ee45f";
export const DEFAULT_DOMAIN = "grid402.online";
export const DEFAULT_EVM_ADDR = "0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90";
export const BASE_URL = "https://trust.paycensus.com";
export const ENDPOINT = `${BASE_URL}/api/verify/addr-proof`;
export const MESSAGE_ENDPOINT = `${BASE_URL}/api/verify/addr-proof-message`;
export const NONCE_PREFIX = "paycensus-verify=";
export const UA =
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36";

/**
 * Carrega pares KEY=VALUE de arquivos .env sem sobrescrever variaveis ja definidas em targetEnv.
 */
export function loadDotenv(paths, targetEnv = process.env) {
  for (const filePath of paths) {
    if (!filePath || !fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
      continue;
    }
    const content = fs.readFileSync(filePath, "utf8").replace(/^\uFEFF/, "");
    for (const rawLine of content.split(/\r?\n/)) {
      let line = rawLine.trim();
      if (!line || line.startsWith("#")) {
        continue;
      }
      if (line.startsWith("export ")) {
        line = line.slice("export ".length).trimStart();
      }
      const eqIdx = line.indexOf("=");
      if (eqIdx === -1) {
        continue;
      }
      const key = line.slice(0, eqIdx).trim();
      let value = line.slice(eqIdx + 1).trim();
      if (
        value.length >= 2 &&
        ((value.startsWith('"') && value.endsWith('"')) ||
          (value.startsWith("'") && value.endsWith("'")))
      ) {
        value = value.slice(1, -1);
      }
      if (key && (targetEnv[key] === undefined || targetEnv[key] === "")) {
        targetEnv[key] = value;
      }
    }
  }
}

/**
 * Garante que o nonce esteja no formato canonical `paycensus-verify=<hex>`.
 */
export function normalizeNonce(value) {
  const trimmed = String(value ?? "").trim();
  if (!trimmed) {
    throw new Error("Nonce vazio.");
  }
  return trimmed.startsWith(NONCE_PREFIX) ? trimmed : `${NONCE_PREFIX}${trimmed}`;
}

/**
 * Monta a mensagem UTF-8 exata esperada pelo PayCensus (`paycensus-addr-proof=<nonce>`).
 */
export function buildMessage(nonce) {
  return `paycensus-addr-proof=${normalizeNonce(nonce)}`;
}

/**
 * Variante alternativa sem repetir `paycensus-verify=` (apenas para diagnostico caso o endpoint antigo espere so o hex).
 */
export function buildAltMessageWithoutVerifyPrefix(nonce) {
  const normalized = normalizeNonce(nonce);
  return `paycensus-addr-proof=${normalized.slice(NONCE_PREFIX.length)}`;
}

/**
 * Normaliza uma assinatura EIP-191 e gera variantes de `v` (`1b`/`1c` canonico e `00`/`01` yParity).
 */
export function normalizeSignatureVariants(signature) {
  const original = String(signature ?? "").trim();
  const parsed = Signature.from(original);
  const canonical = parsed.serialized;
  const yParity = `${parsed.r}${parsed.s.slice(2)}${parsed.yParity === 0 ? "00" : "01"}`;
  const variants = canonical === yParity ? [canonical] : [canonical, yParity];
  return {
    original,
    originalVHex: original.slice(-2).toLowerCase(),
    canonical,
    yParity,
    variants,
  };
}

/**
 * Retorna a URL publica de consulta de perfil do dominio na PayCensus.
 */
export function profileUrl(domain) {
  return `${BASE_URL}/api/profile?domain=${encodeURIComponent(domain)}`;
}

/**
 * Retorna a URL oficial para buscar a mensagem exata a assinar (`GET /api/verify/addr-proof-message?vid=<vid>`).
 */
export function messageUrl(vid) {
  return `${MESSAGE_ENDPOINT}?vid=${encodeURIComponent(String(vid))}`;
}

/**
 * Busca programaticamente a mensagem UTF-8 exata a assinar no endpoint oficial da PayCensus.
 */
export async function fetchOfficialMessage(vid) {
  try {
    const res = await httpJson(messageUrl(vid));
    if (
      res.status === 200 &&
      typeof res.data?.message === "string" &&
      res.data.message.startsWith("paycensus-addr-proof=")
    ) {
      return res.data.message.trim();
    }
    return null;
  } catch {
    return null;
  }
}

/**
 * Valida e normaliza uma chave privada EVM (32 bytes hex, com ou sem `0x`) sem expor seu valor.
 */
export function normalizePrivateKey(raw) {
  const cleaned = String(raw ?? "").replace(/\s+/g, "");
  if (!cleaned) {
    throw new Error("Chave privada EVM vazia.");
  }
  const prefixed = /^[0-9a-fA-F]{64}$/.test(cleaned) ? `0x${cleaned}` : cleaned;
  if (!/^0x[0-9a-fA-F]{64}$/.test(prefixed)) {
    throw new Error(
      "Chave privada EVM invalida: esperado hex de 32 bytes (64 caracteres hex, com ou sem 0x)."
    );
  }
  return prefixed;
}

/**
 * Assina a mensagem usando EIP-191 `personal_sign` via ethers Wallet.
 */
export async function signEvmProof(message, privateKey) {
  const wallet = new Wallet(normalizePrivateKey(privateKey));
  const signature = await wallet.signMessage(message);
  return { address: wallet.address, signature };
}

/**
 * Verifica localmente assinatura EIP-191 e confirma se o signer bate com expectedAddress.
 */
export function verifyEvmProof(message, signature, expectedAddress) {
  const sig = String(signature ?? "").trim();
  if (!/^0x[0-9a-fA-F]{130}$/.test(sig)) {
    return {
      ok: false,
      error: "Assinatura EVM invalida: esperado hex 0x com 65 bytes (130 caracteres hex).",
    };
  }

  let expectedChecksum;
  try {
    expectedChecksum = getAddress(String(expectedAddress ?? "").trim());
  } catch (err) {
    return {
      ok: false,
      error: `Endereco EVM esperado invalido: ${err instanceof Error ? err.message : String(err)}`,
    };
  }

  let recovered;
  try {
    recovered = verifyMessage(message, sig);
  } catch (err) {
    return {
      ok: false,
      expected: expectedChecksum,
      error: `Falha ao verificar assinatura EIP-191: ${err instanceof Error ? err.message : String(err)}`,
    };
  }

  const ok = recovered.toLowerCase() === expectedChecksum.toLowerCase();
  if (!ok) {
    return {
      ok: false,
      recovered,
      expected: expectedChecksum,
      error: `Signer recuperado (${recovered}) diverge do endereco esperado (${expectedChecksum}).`,
    };
  }

  return {
    ok: true,
    recovered,
    expected: expectedChecksum,
  };
}

/**
 * Constroi o payload JSON exato exigido por `POST /api/verify/addr-proof`.
 */
export function buildPayload(vid, signature) {
  return { vid, signature };
}

/**
 * Verifica se o endereco EVM ja consta com `proven` verdadeiro no perfil PayCensus.
 */
export function isEvmAlreadyProven(profile, expectedAddress) {
  if (!profile || !Array.isArray(profile.addresses)) {
    return false;
  }
  const target = String(expectedAddress ?? "").trim().toLowerCase();
  return profile.addresses.some(
    (entry) =>
      entry &&
      entry.chain === "evm" &&
      String(entry.addr || "").trim().toLowerCase() === target &&
      Boolean(entry.proven)
  );
}

/**
 * Resolve o nonce atual no registro TXT `_paycensus.<domain>`.
 */
export async function resolveDnsNonce(domain) {
  try {
    const records = await dns.resolveTxt(`_paycensus.${domain}`);
    for (const chunks of records) {
      const joined = chunks.join("").trim();
      if (joined.startsWith(NONCE_PREFIX)) {
        return joined;
      }
    }
    return null;
  } catch {
    return null;
  }
}

function curlFallbackJson(url, method, bodyText) {
  const curlBin = process.platform === "win32" ? "curl.exe" : "curl";
  const args = [
    "-sS",
    "--max-time",
    "30",
    "-w",
    "\n%{http_code}",
    "-X",
    method,
    "-A",
    UA,
    "-H",
    "Content-Type: application/json",
    "-H",
    "Accept: application/json",
  ];
  if (bodyText !== null) {
    args.push("--data-raw", bodyText);
  }
  args.push(url);

  const stdout = execFileSync(curlBin, args, { encoding: "utf8", timeout: 60000 });
  const splitIdx = stdout.lastIndexOf("\n");
  const rawBody = splitIdx === -1 ? stdout : stdout.slice(0, splitIdx);
  const codePart = splitIdx === -1 ? "" : stdout.slice(splitIdx + 1).trim();
  const parsedStatus = Number.parseInt(codePart, 10) || 0;

  try {
    return { status: parsedStatus, data: JSON.parse(rawBody) };
  } catch {
    return { status: parsedStatus, data: { raw: rawBody } };
  }
}

/**
 * Executa requisicao HTTP JSON usando fetch nativo com fallback em curl se Cloudflare bloquear.
 */
export async function httpJson(url, { method = "GET", payload = null } = {}) {
  const bodyText = payload === null ? null : JSON.stringify(payload);
  const res = await fetch(url, {
    method,
    headers: {
      "Content-Type": "application/json",
      "User-Agent": UA,
      Accept: "application/json",
    },
    body: bodyText,
    signal: AbortSignal.timeout(30000),
  });

  const rawText = await res.text();
  try {
    return { status: res.status, data: JSON.parse(rawText) };
  } catch {
    if ((res.status === 403 || res.status === 429) && /error code/i.test(rawText)) {
      try {
        return curlFallbackJson(url, method, bodyText);
      } catch {
        return { status: res.status, data: { raw: rawText } };
      }
    }
    return { status: res.status, data: { raw: rawText } };
  }
}

/**
 * Envia o POST de prova EVM tentando primeiro `v` canonico (`1b`/`1c`) e fallback para `00`/`01`.
 */
export async function submitProofWithVariants(vid, signature, endpoint = ENDPOINT) {
  const { variants } = normalizeSignatureVariants(signature);
  const attempts = [];
  let lastResult = null;

  for (const variantSig of variants) {
    const vHex = variantSig.slice(-2).toLowerCase();
    const payload = buildPayload(vid, variantSig);
    const postRes = await httpJson(endpoint, { method: "POST", payload });
    console.log(`\nPOST ${endpoint} (v=0x${vHex}) -> HTTP ${postRes.status}`);
    console.log(JSON.stringify(postRes.data, null, 2));

    const ok = postRes.status === 200 && Boolean(postRes.data?.addr_proven || postRes.data?.ok);
    attempts.push({
      vHex,
      signature: variantSig,
      status: postRes.status,
      data: postRes.data,
      ok,
    });
    lastResult = {
      ok,
      status: postRes.status,
      data: postRes.data,
      usedSignature: variantSig,
      vHex,
      attempts,
    };

    if (ok) {
      return lastResult;
    }

    const errMsg = String(postRes.data?.error || "");
    if (!(postRes.status === 400 && /signature does not match/i.test(errMsg))) {
      break;
    }
  }

  return lastResult;
}

/**
 * Analisa argumentos de linha de comando.
 */
export function parseArgs(argv) {
  const opts = {
    help: false,
    printMessage: false,
    browser: false,
    port: 0,
    signature: undefined,
    keyFile: undefined,
    nonce: undefined,
    rawMessage: undefined,
    probeAlt: false,
    vid: undefined,
    domain: undefined,
    addr: undefined,
    dryRun: false,
    force: false,
  };

  const valueFlags = new Set([
    "--port",
    "--signature",
    "--key-file",
    "--nonce",
    "--raw-message",
    "--vid",
    "--domain",
    "--addr",
  ]);

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === "--help" || arg === "-h") {
      opts.help = true;
      continue;
    }
    if (arg === "--print-message") {
      opts.printMessage = true;
      continue;
    }
    if (arg === "--browser") {
      opts.browser = true;
      continue;
    }
    if (arg === "--probe-alt") {
      opts.probeAlt = true;
      continue;
    }
    if (arg === "--dry-run") {
      opts.dryRun = true;
      continue;
    }
    if (arg === "--force") {
      opts.force = true;
      continue;
    }

    if (valueFlags.has(arg)) {
      const nextVal = argv[i + 1];
      if (nextVal === undefined) {
        throw new Error(`Opcao ${arg} exige um valor.`);
      }
      i += 1;
      if (arg === "--port") {
        const parsedPort = Number.parseInt(nextVal, 10);
        if (!Number.isInteger(parsedPort) || parsedPort < 0 || parsedPort > 65535) {
          throw new Error(`Porta invalida para --port: ${nextVal}`);
        }
        opts.port = parsedPort;
      } else if (arg === "--signature") {
        opts.signature = nextVal;
      } else if (arg === "--key-file") {
        opts.keyFile = nextVal;
      } else if (arg === "--nonce") {
        opts.nonce = nextVal;
      } else if (arg === "--raw-message") {
        opts.rawMessage = nextVal;
      } else if (arg === "--vid") {
        const parsedVid = Number.parseInt(nextVal, 10);
        if (!Number.isInteger(parsedVid) || parsedVid <= 0) {
          throw new Error(`--vid invalido: ${nextVal}`);
        }
        opts.vid = parsedVid;
      } else if (arg === "--domain") {
        opts.domain = nextVal;
      } else if (arg === "--addr") {
        opts.addr = nextVal;
      }
      continue;
    }

    throw new Error(`Argumento desconhecido: ${arg}`);
  }

  return opts;
}

function safeJsonForHtml(value) {
  return JSON.stringify(value)
    .replace(/</g, "\\u003c")
    .replace(/>/g, "\\u003e")
    .replace(/&/g, "\\u0026");
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function buildBrowserHtml({ message, expectedAddress, vid, domain, csrfToken }) {
  const clientConfig = safeJsonForHtml({ message, expectedAddress, token: csrfToken });
  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PayCensus EVM addr-proof — ${escapeHtml(domain)}</title>
  <style>
    :root { color-scheme: dark; }
    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background: #0d1117;
      color: #e6edf3;
      max-width: 720px;
      margin: 32px auto;
      padding: 0 20px;
      line-height: 1.55;
    }
    .card {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 18px;
    }
    h1 { font-size: 20px; margin: 0 0 8px; }
    h2 { font-size: 15px; margin: 0 0 10px; color: #9ecbff; }
    .muted { color: #8b949e; font-size: 13px; }
    .mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 13px;
      background: #0d1117;
      border: 1px solid #30363d;
      border-radius: 8px;
      padding: 10px 12px;
      word-break: break-all;
      color: #f0c674;
    }
    button {
      background: #238636;
      color: #ffffff;
      border: 0;
      border-radius: 8px;
      padding: 10px 16px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }
    button.secondary { background: #1f6feb; margin-top: 8px; }
    button:disabled { opacity: 0.6; cursor: not-allowed; }
    input {
      width: 100%;
      box-sizing: border-box;
      background: #0d1117;
      color: #e6edf3;
      border: 1px solid #30363d;
      border-radius: 8px;
      padding: 10px 12px;
      font-family: ui-monospace, Menlo, Consolas, monospace;
      font-size: 13px;
      margin-top: 8px;
    }
    .status { margin-top: 12px; font-size: 14px; }
    .ok { color: #3fb950; font-weight: 600; }
    .err { color: #f85149; font-weight: 600; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Assinatura EIP-191 PayCensus (EVM)</h1>
    <p class="muted">Servidor local temporario em 127.0.0.1. Nenhuma chave privada sai da sua carteira.</p>
    <p><strong>Dominio:</strong> <code>${escapeHtml(domain)}</code> &nbsp;|&nbsp; <strong>vid:</strong> <code>${escapeHtml(String(vid))}</code></p>
    <p><strong>Carteira esperada:</strong></p>
    <div class="mono">${escapeHtml(expectedAddress)}</div>
    <p><strong>Mensagem exata (UTF-8):</strong></p>
    <div id="msg-display" class="mono">${escapeHtml(message)}</div>
  </div>

  <div class="card">
    <h2>Opcao 1 — Conectar MetaMask / Rabby / Ledger no navegador</h2>
    <button id="btn-sign" type="button">Conectar MetaMask / Rabby e assinar (EIP-191)</button>
    <div id="status-wallet" class="status"></div>
  </div>

  <div class="card">
    <h2>Opcao 2 — Colar assinatura manual (0x...)</h2>
    <label for="manual-sig" class="muted">Caso tenha assinado a mensagem acima em outra carteira (ex.: hardware wallet offline / MyCrypto):</label>
    <input id="manual-sig" type="text" placeholder="0x..." autocomplete="off" />
    <button id="btn-manual" class="secondary" type="button">Enviar assinatura manual</button>
    <div id="status-manual" class="status"></div>
  </div>

  <script>
    const CFG = ${clientConfig};
    const btnSign = document.getElementById("btn-sign");
    const btnManual = document.getElementById("btn-manual");

    async function sendCallback(signature, statusEl) {
      btnSign.disabled = true;
      btnManual.disabled = true;
      statusEl.className = "status muted";
      statusEl.textContent = "Validando e enviando assinatura para PayCensus...";
      try {
        const resp = await fetch("/callback", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ token: CFG.token, signature })
        });
        const data = await resp.json();
        if (data.nextStep && data.nextMessage) {
          CFG.message = data.nextMessage;
          document.getElementById("msg-display").textContent = data.nextMessage;
          statusEl.className = "status err";
          statusEl.textContent = data.error || "Tente novamente com a mensagem atualizada acima.";
          btnSign.disabled = false;
          btnManual.disabled = false;
          return;
        }
        if (!resp.ok || (!data.ok && !data.done)) {
          statusEl.className = "status err";
          statusEl.textContent = data.error || "Falha ao validar assinatura.";
          btnSign.disabled = false;
          btnManual.disabled = false;
          return;
        }
        if (data.done && !data.ok) {
          statusEl.className = "status err";
          statusEl.textContent = data.error || "PayCensus rejeitou as variantes. Veja o diagnostico no terminal.";
          btnSign.disabled = true;
          btnManual.disabled = true;
          return;
        }
        statusEl.className = "status ok";
        statusEl.textContent = data.message || ("Assinatura valida para " + data.recovered + "! Retorne ao terminal (esta aba pode ser fechada).");
        btnSign.disabled = true;
        btnManual.disabled = true;
      } catch (err) {
        statusEl.className = "status err";
        statusEl.textContent = (err && err.message) ? err.message : String(err);
        btnSign.disabled = false;
        btnManual.disabled = false;
      }
    }

    btnSign.addEventListener("click", async () => {
      const statusEl = document.getElementById("status-wallet");
      try {
        if (!window.ethereum) {
          statusEl.className = "status err";
          statusEl.textContent = "Nenhuma carteira injetada (window.ethereum) encontrada neste navegador.";
          return;
        }
        const accounts = await window.ethereum.request({ method: "eth_requestAccounts" });
        const account = accounts && accounts[0];
        if (!account) {
          statusEl.className = "status err";
          statusEl.textContent = "Nenhuma conta retornada pela carteira.";
          return;
        }
        if (account.toLowerCase() !== CFG.expectedAddress.toLowerCase()) {
          statusEl.className = "status err";
          statusEl.textContent = "Conta conectada (" + account + ") diverge da carteira esperada (" + CFG.expectedAddress + "). Troque de conta na carteira e tente novamente.";
          return;
        }
        statusEl.className = "status muted";
        statusEl.textContent = "Confirme o personal_sign na sua carteira...";
        const signature = await window.ethereum.request({
          method: "personal_sign",
          params: [CFG.message, account]
        });
        await sendCallback(signature, statusEl);
      } catch (err) {
        statusEl.className = "status err";
        statusEl.textContent = (err && err.message) ? err.message : String(err);
      }
    });

    btnManual.addEventListener("click", async () => {
      const statusEl = document.getElementById("status-manual");
      const signature = document.getElementById("manual-sig").value.trim();
      await sendCallback(signature, statusEl);
    });
  </script>
</body>
</html>`;
}

/**
 * Abre servidor HTTP temporario em 127.0.0.1 para capturar assinatura EIP-191 com seguranca.
 */
export async function captureSignatureViaBrowser({
  message,
  expectedAddress,
  vid,
  domain,
  port = 0,
  onVerifiedSignature = null,
}) {
  const csrfToken = crypto.randomBytes(16).toString("hex");
  const html = buildBrowserHtml({ message, expectedAddress, vid, domain, csrfToken });
  let currentMessage = message;

  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      if (req.method === "GET" && (req.url === "/" || req.url === "/index.html")) {
        res.writeHead(200, {
          "Content-Type": "text/html; charset=utf-8",
          "Cache-Control": "no-store",
        });
        res.end(html);
        return;
      }

      if (req.method === "POST" && req.url === "/callback") {
        let body = "";
        let total = 0;
        req.setEncoding("utf8");
        req.on("data", (chunk) => {
          total += chunk.length;
          if (total <= 65536) {
            body += chunk;
          }
        });
        req.on("end", async () => {
          if (total > 65536) {
            res.writeHead(413, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ ok: false, error: "Payload excedeu o limite permitido." }));
            return;
          }
          let parsed;
          try {
            parsed = JSON.parse(body || "{}");
          } catch {
            res.writeHead(400, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ ok: false, error: "JSON invalido no callback." }));
            return;
          }

          if (parsed.token !== csrfToken) {
            res.writeHead(403, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ ok: false, error: "Token CSRF invalido." }));
            return;
          }

          const sig = String(parsed.signature || "").trim();
          const check = verifyEvmProof(currentMessage, sig, expectedAddress);
          if (!check.ok) {
            res.writeHead(400, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ ok: false, error: check.error }));
            return;
          }

          if (typeof onVerifiedSignature === "function") {
            let cbRes;
            try {
              cbRes = await onVerifiedSignature({
                message: currentMessage,
                signature: sig,
                verification: check,
              });
            } catch (err) {
              res.writeHead(500, { "Content-Type": "application/json" });
              res.end(
                JSON.stringify({
                  ok: false,
                  error: `Erro interno ao submeter prova: ${err instanceof Error ? err.message : String(err)}`,
                })
              );
              return;
            }

            if (cbRes.nextStep && cbRes.nextMessage) {
              currentMessage = cbRes.nextMessage;
              res.writeHead(200, { "Content-Type": "application/json" });
              res.end(
                JSON.stringify({
                  ok: false,
                  nextStep: true,
                  nextMessage: cbRes.nextMessage,
                  error: cbRes.error,
                })
              );
              return;
            }

            res.writeHead(200, { "Content-Type": "application/json" });
            res.end(
              JSON.stringify({
                ok: Boolean(cbRes.ok),
                done: true,
                recovered: check.recovered,
                message: cbRes.message,
                error: cbRes.error,
              })
            );

            server.close(() => {
              resolve({
                submitted: true,
                ok: Boolean(cbRes.ok),
                result: cbRes.result,
                attempts: cbRes.attempts,
                signature: sig,
                origin: "carteira via navegador (personal_sign)",
              });
            });
            if (typeof server.closeAllConnections === "function") {
              setTimeout(() => server.closeAllConnections(), 200);
            }
            return;
          }

          res.writeHead(200, { "Content-Type": "application/json" });
          res.end(JSON.stringify({ ok: true, recovered: check.recovered }));

          server.close(() => {
            resolve({
              submitted: false,
              signature: sig,
              origin: "carteira via navegador (personal_sign)",
            });
          });
          if (typeof server.closeAllConnections === "function") {
            setTimeout(() => server.closeAllConnections(), 200);
          }
        });
        return;
      }

      res.writeHead(404, { "Content-Type": "text/plain; charset=utf-8" });
      res.end("Not found");
    });

    server.on("error", (err) => reject(err));
    server.listen(port, "127.0.0.1", () => {
      const addr = server.address();
      const boundPort = typeof addr === "object" && addr ? addr.port : port;
      const url = `http://127.0.0.1:${boundPort}/`;
      console.log(`Abra no navegador para assinar com MetaMask/Rabby: ${url}`);
      console.log("Aguardando assinatura valida em 127.0.0.1 (Ctrl+C para cancelar)...");
      if (process.platform === "win32") {
        try {
          const child = spawn("cmd", ["/c", "start", "", url], {
            detached: true,
            stdio: "ignore",
          });
          child.unref();
        } catch {
          // URL ja foi impressa no terminal
        }
      }
    });
  });
}

function printHelp() {
  console.log(`Uso: node scripts/paycensus_bind_evm.mjs [opcoes]

Vincula o endereco EVM (default: ${DEFAULT_EVM_ADDR}) ao perfil PayCensus Trust (vid=${DEFAULT_VID}).

Opcoes:
  --print-message     Exibe dominio, vid, endereco, nonce e mensagem exata para assinar e sai
  --browser           Abre UI local em 127.0.0.1 para assinar via MetaMask/Rabby (personal_sign)
  --port <N>          Porta local para --browser (default: 0 = automatica)
  --probe-alt         Com --browser, tenta tambem mensagem sem prefixo paycensus-verify= se der 400
  --raw-message <txt> Usa exatamente <txt> como mensagem UTF-8 em vez de paycensus-addr-proof=<nonce>
  --signature <0x..>  Usa assinatura EIP-191 hex de 65 bytes ja gerada externamente
  --key-file <path>   Le chave privada EVM hex (32 bytes) de um arquivo local
  --nonce <valor>     Nonce do PayCensus ("paycensus-verify=<hex>" ou apenas "<hex>")
  --vid <N>           Verification ID (default: env GRID402_PAYCENSUS_VID ou ${DEFAULT_VID})
  --domain <host>     Dominio do perfil (default: env GRID402_DOMAIN ou ${DEFAULT_DOMAIN})
  --addr <0x..>       Endereco EVM esperado (default: env GRID402_WALLET_ADDRESS ou ${DEFAULT_EVM_ADDR})
  --dry-run           Valida assinatura localmente e imprime o payload sem POSTar
  --force             Executa mesmo se o perfil ja constar como proven=1
  -h, --help          Exibe esta ajuda
`);
}

function printDiagnosticSummary(attempts) {
  console.log("\n=== RESUMO DIAGNOSTICO PAYCENSUS EVM ===");
  for (let i = 0; i < attempts.length; i += 1) {
    const item = attempts[i];
    console.log(`[${i + 1}] mensagem: ${item.message}`);
    console.log(`    v=0x${item.vHex} | HTTP ${item.status} | ok=${item.ok}`);
    console.log(`    signature: ${item.signature}`);
    console.log(`    resposta: ${JSON.stringify(item.data)}`);
  }
}

export async function main(argv = process.argv.slice(2)) {
  let args;
  try {
    args = parseArgs(argv);
  } catch (err) {
    console.error(`erro: ${err instanceof Error ? err.message : String(err)}`);
    return 2;
  }

  if (args.help) {
    printHelp();
    return 0;
  }

  loadDotenv([
    path.join(REPO_ROOT, ".env"),
    path.join(REPO_ROOT, "private_data", ".env"),
  ]);

  const domain = args.domain || process.env.GRID402_DOMAIN || DEFAULT_DOMAIN;

  let vid;
  if (args.vid !== undefined) {
    vid = args.vid;
  } else if ((process.env.GRID402_PAYCENSUS_VID || "").trim()) {
    const parsedEnvVid = Number.parseInt(process.env.GRID402_PAYCENSUS_VID.trim(), 10);
    if (!Number.isInteger(parsedEnvVid) || parsedEnvVid <= 0) {
      console.error(
        `erro: GRID402_PAYCENSUS_VID=${JSON.stringify(process.env.GRID402_PAYCENSUS_VID)} nao e um inteiro valido.`
      );
      return 2;
    }
    vid = parsedEnvVid;
  } else {
    vid = DEFAULT_VID;
  }

  let expectedAddress;
  try {
    expectedAddress = getAddress(
      (args.addr || process.env.GRID402_WALLET_ADDRESS || DEFAULT_EVM_ADDR).trim()
    );
  } catch (err) {
    console.error(
      `erro: endereco EVM invalido (${err instanceof Error ? err.message : String(err)})`
    );
    return 2;
  }

  let nonceRaw = args.nonce || (process.env.GRID402_PAYCENSUS_NONCE || "").trim();
  let nonceOrigin = args.nonce ? "flag --nonce" : "env GRID402_PAYCENSUS_NONCE";
  let fetchedMessage = null;
  if (!nonceRaw && !args.rawMessage) {
    fetchedMessage = await fetchOfficialMessage(vid);
    if (fetchedMessage) {
      nonceRaw = fetchedMessage.slice("paycensus-addr-proof=".length);
      nonceOrigin = `API GET /api/verify/addr-proof-message?vid=${vid}`;
    } else {
      const dnsNonce = await resolveDnsNonce(domain);
      if (dnsNonce) {
        nonceRaw = dnsNonce;
        nonceOrigin = `DNS TXT _paycensus.${domain}`;
      } else {
        nonceRaw = DEFAULT_NONCE;
        nonceOrigin = "default estatico";
        console.error(
          `aviso: nao foi possivel resolver _paycensus.${domain}; usando nonce default estatico.`
        );
      }
    }
  } else if (!nonceRaw) {
    nonceRaw = DEFAULT_NONCE;
    nonceOrigin = "default estatico";
  }

  let nonce;
  try {
    nonce = normalizeNonce(nonceRaw);
  } catch (err) {
    console.error(`erro: nonce invalido (${err instanceof Error ? err.message : String(err)})`);
    return 2;
  }

  const message = args.rawMessage ? args.rawMessage : (fetchedMessage || buildMessage(nonce));
  const urlProfile = profileUrl(domain);

  if (args.printMessage) {
    console.log(`dominio: ${domain}`);
    console.log(`vid: ${vid}`);
    console.log(`endereco EVM esperado: ${expectedAddress}`);
    console.log(`nonce (${nonceOrigin}): ${nonce}`);
    console.log(`mensagem UTF-8 exata:\n${message}`);
    console.log("\nComo vincular:");
    console.log("  1) Recomendado (MetaMask/Rabby/Ledger): node scripts/paycensus_bind_evm.mjs --browser");
    console.log('  2) Com assinatura pronta: node scripts/paycensus_bind_evm.mjs --signature "0x..."');
    return 0;
  }

  if (args.browser && args.probeAlt && !args.dryRun && !args.rawMessage) {
    if (!args.force) {
      const profileBefore = await httpJson(urlProfile);
      if (profileBefore.status === 200 && isEvmAlreadyProven(profileBefore.data, expectedAddress)) {
        console.log("esse endereco EVM ja consta como proven=1 no perfil; nada a fazer.");
        return 0;
      }
    }

    const altMessage = buildAltMessageWithoutVerifyPrefix(nonce);
    const allAttempts = [];
    let stepIndex = 0;

    const captured = await captureSignatureViaBrowser({
      message,
      expectedAddress,
      vid,
      domain,
      port: args.port,
      onVerifiedSignature: async ({ message: stepMessage, signature: stepSig, verification }) => {
        const sigInfo = normalizeSignatureVariants(stepSig);
        console.log(`\n[passo ${stepIndex + 1}] assinatura origem: carteira via navegador (personal_sign)`);
        console.log(`signer verificado localmente: ${verification.recovered}`);
        console.log(`vid: ${vid} | nonce (${nonceOrigin}): ${nonce}`);
        console.log(`mensagem assinada (UTF-8 exato): ${stepMessage}`);
        console.log(`assinatura EIP-191 (65 bytes, v=0x${sigInfo.originalVHex}): ${sigInfo.canonical}`);

        const postRes = await submitProofWithVariants(vid, stepSig);
        for (const attempt of postRes.attempts) {
          allAttempts.push({
            message: stepMessage,
            vHex: attempt.vHex,
            signature: attempt.signature,
            status: attempt.status,
            data: attempt.data,
            ok: attempt.ok,
          });
        }

        if (postRes.ok) {
          return {
            ok: true,
            done: true,
            message: "PayCensus confirmou addr_proven!",
            result: postRes,
            attempts: allAttempts,
          };
        }

        if (stepIndex === 0 && altMessage !== stepMessage) {
          stepIndex = 1;
          return {
            ok: false,
            nextStep: true,
            nextMessage: altMessage,
            error:
              "PayCensus retornou 400 para a mensagem padrao. A mensagem acima foi atualizada para testar sem prefixo paycensus-verify= — clique no botao novamente.",
          };
        }

        return {
          ok: false,
          done: true,
          error:
            "PayCensus retornou 400 tambem na variante alternativa. Veja o diagnostico completo no terminal.",
          result: postRes,
          attempts: allAttempts,
        };
      },
    });

    if (!captured.ok) {
      printDiagnosticSummary(captured.attempts || allAttempts);
      console.error(
        "\nfalha no bind EVM em todas as variantes testadas; diagnostico salvo acima."
      );
      return 1;
    }

    const profileAfter = await httpJson(urlProfile);
    console.log(`\nGET ${urlProfile} -> HTTP ${profileAfter.status}`);
    const addresses = Array.isArray(profileAfter.data?.addresses) ? profileAfter.data.addresses : [];
    for (const entry of addresses) {
      console.log(`  ${String(entry.chain).padStart(6)} ${entry.addr} proven=${entry.proven}`);
    }
    const bound = isEvmAlreadyProven(profileAfter.data, expectedAddress);
    console.log(
      "\nRESULTADO:",
      bound
        ? "VINCULADO COM PROVA CRIPTOGRAFICA (proven=1)"
        : "NAO CONSTANTE COMO proven - investigar"
    );
    return bound ? 0 : 1;
  }

  let signature;
  let sigOrigin;
  const envSigningKey = (process.env.GRID402_EVM_SIGNING_KEY || "").trim();

  if (args.signature) {
    signature = args.signature.trim();
    sigOrigin = "flag --signature";
  } else if (args.browser) {
    const captured = await captureSignatureViaBrowser({
      message,
      expectedAddress,
      vid,
      domain,
      port: args.port,
    });
    signature = captured.signature;
    sigOrigin = captured.origin;
  } else if (args.keyFile) {
    let rawFileKey;
    try {
      rawFileKey = fs.readFileSync(args.keyFile, "utf8").trim();
    } catch (err) {
      console.error(
        `erro ao ler --key-file (${args.keyFile}): ${err instanceof Error ? err.message : String(err)}`
      );
      return 2;
    }
    try {
      const signed = await signEvmProof(message, rawFileKey);
      signature = signed.signature;
      sigOrigin = `arquivo ${args.keyFile}`;
    } catch (err) {
      console.error(`erro ao assinar com --key-file: ${err instanceof Error ? err.message : String(err)}`);
      return 2;
    }
  } else if (envSigningKey) {
    try {
      const signed = await signEvmProof(message, envSigningKey);
      signature = signed.signature;
      sigOrigin = "env GRID402_EVM_SIGNING_KEY";
    } catch (err) {
      console.error(
        `erro ao assinar com GRID402_EVM_SIGNING_KEY: ${err instanceof Error ? err.message : String(err)}`
      );
      return 2;
    }
  } else {
    console.error(
      "erro: nenhuma assinatura ou chave fornecida. Escolha um dos caminhos:\n" +
        "  - Recomendado (sem chave em disco): node scripts/paycensus_bind_evm.mjs --browser\n" +
        '  - Assinatura externa pronto uso:    node scripts/paycensus_bind_evm.mjs --signature "0x..."\n' +
        "  - Ver mensagem para assinar fora:   node scripts/paycensus_bind_evm.mjs --print-message\n" +
        "  - Uso headless: --key-file <caminho> ou env GRID402_EVM_SIGNING_KEY"
    );
    return 2;
  }

  const verification = verifyEvmProof(message, signature, expectedAddress);
  if (!verification.ok) {
    console.error(`erro na verificacao local da assinatura: ${verification.error}`);
    if (verification.recovered && verification.expected) {
      console.error(`  recuperado: ${verification.recovered}`);
      console.error(`  esperado:   ${verification.expected}`);
    }
    return 1;
  }

  const sigInfo = normalizeSignatureVariants(signature);
  console.log(`assinatura origem: ${sigOrigin}`);
  console.log(`signer verificado localmente: ${verification.recovered}`);
  console.log(`vid: ${vid} | nonce (${nonceOrigin}): ${nonce}`);
  console.log(`mensagem assinada (UTF-8 exato): ${message}`);
  console.log(`assinatura EIP-191 (65 bytes, v=0x${sigInfo.originalVHex}): ${sigInfo.canonical}`);

  const profileBefore = await httpJson(urlProfile);
  if (
    profileBefore.status === 200 &&
    isEvmAlreadyProven(profileBefore.data, expectedAddress) &&
    !args.force
  ) {
    console.log("esse endereco EVM ja consta como proven=1 no perfil; nada a fazer.");
    return 0;
  }

  const payload = buildPayload(vid, sigInfo.canonical);
  if (args.dryRun) {
    console.log("\n[dry-run] payload que seria POSTado:");
    console.log(JSON.stringify(payload, null, 2));
    return 0;
  }

  const postRes = await submitProofWithVariants(vid, signature);
  if (!postRes.ok) {
    console.error(
      "falha no bind EVM; confira se o vid, o nonce (_paycensus TXT) e a conta assinante estao corretos."
    );
    return 1;
  }

  const profileAfter = await httpJson(urlProfile);
  console.log(`\nGET ${urlProfile} -> HTTP ${profileAfter.status}`);
  const addresses = Array.isArray(profileAfter.data?.addresses) ? profileAfter.data.addresses : [];
  for (const entry of addresses) {
    console.log(`  ${String(entry.chain).padStart(6)} ${entry.addr} proven=${entry.proven}`);
  }
  const bound = isEvmAlreadyProven(profileAfter.data, expectedAddress);
  console.log(
    "\nRESULTADO:",
    bound
      ? "VINCULADO COM PROVA CRIPTOGRAFICA (proven=1)"
      : "NAO CONSTANTE COMO proven - investigar"
  );
  return bound ? 0 : 1;
}

if (
  process.argv[1] &&
  pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url
) {
  const exitCode = await main();
  process.exitCode = exitCode;
}
