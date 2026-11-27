#!/usr/bin/env node
/**
 * Gera especificacoes OpenAPI 3.0.3 limpas e prontas para importacao no RapidAPI Provider Studio
 * a partir de public_html/openapi.json.
 *
 * Saidas em docs/revenue/openapi/:
 *   - rapidapi-global.json      (16 rotas globais + /api/v1/summary + /api/v1/verify)
 *   - rapidapi-brazil-kyb.json  (10 rotas Brasil KYB & Dados Publicos)
 *   - rapidapi-all.json         (todas as 26 rotas pagas + /api/v1/summary + /api/v1/verify)
 *
 * Uso:
 *   node scripts/generate_rapidapi_openapi.mjs
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
export const REPO_ROOT = path.dirname(__dirname);

const SOURCE_OPENAPI = path.join(REPO_ROOT, "public_html", "openapi.json");
export const OUTPUT_DIR = path.join(REPO_ROOT, "docs", "revenue", "openapi");

export const EXCLUDED_PATHS = new Set([
  "/api/v1/pass",
  "/api/v1/pass/balance",
  "/api/v1/pass/statement",
  "/terms",
  "/privacy",
]);

export const PAYMENT_HEADER_NAMES = new Set([
  "x-payment",
  "payment-signature",
  "x-payment-network",
  "x-session-pass",
]);

export const GLOBAL_PATHS = [
  "/api/v1/token-safety",
  "/api/v1/prediction-markets",
  "/api/v1/batch",
  "/api/v1/extract",
  "/api/v1/email-verify",
  "/api/v1/domain-audit",
  "/api/v1/dns",
  "/api/v1/ssl",
  "/api/v1/asn",
  "/api/v1/notarize",
  "/api/v1/global-markets",
  "/api/v1/ofac",
  "/api/v1/lei",
  "/api/v1/market-snapshot",
  "/api/v1/forex",
  "/api/v1/crypto",
  "/api/v1/summary",
  "/api/v1/verify",
];

export const BRAZIL_KYB_PATHS = [
  "/api/v1/dossier",
  "/api/v1/kyb-audit",
  "/api/v1/cnpj",
  "/api/v1/sanctions",
  "/api/v1/simples",
  "/api/v1/compliance",
  "/api/v1/workdays",
  "/api/v1/holidays",
  "/api/v1/indicators",
  "/api/v1/cep",
];

export const ALL_PATHS = [
  ...GLOBAL_PATHS.filter((p) => p !== "/api/v1/summary" && p !== "/api/v1/verify"),
  ...BRAZIL_KYB_PATHS,
  "/api/v1/summary",
  "/api/v1/verify",
];

const DEFAULT_PARAM_EXAMPLES = {
  payload: JSON.stringify({ _oracle_proof: { data_hash: "00" } }),
};

/**
 * Converte recursivamente construcoes OpenAPI 3.1 para OpenAPI 3.0.3 compativel com RapidAPI.
 */
export function convertSchemaNodeTo303(node) {
  if (Array.isArray(node)) {
    return node.map((item) => convertSchemaNodeTo303(item));
  }
  if (!node || typeof node !== "object") {
    return node;
  }

  const out = {};
  for (const [k, v] of Object.entries(node)) {
    if (k === "$schema") {
      continue;
    }
    if (k === "examples" && Array.isArray(v)) {
      if (node.example === undefined && v.length > 0) {
        out.example = convertSchemaNodeTo303(v[0]);
      }
      continue;
    }
    if (k === "type" && Array.isArray(v)) {
      const nonNull = v.filter((t) => t !== "null");
      if (v.includes("null")) {
        out.nullable = true;
      }
      out.type = nonNull[0] || "string";
      continue;
    }
    out[k] = convertSchemaNodeTo303(v);
  }
  return out;
}

function sanitizeOperation(method, pathStr, rawOp) {
  const op = convertSchemaNodeTo303(structuredClone(rawOp));
  delete op.security;
  delete op["x-payment-info"];

  if (op.responses && typeof op.responses === "object") {
    delete op.responses["402"];
  }

  if (Array.isArray(op.parameters)) {
    op.parameters = op.parameters
      .filter((param) => {
        if (!param || typeof param !== "object") return false;
        const nameLower = String(param.name || "").trim().toLowerCase();
        if (String(param.in || "").toLowerCase() === "header" && PAYMENT_HEADER_NAMES.has(nameLower)) {
          return false;
        }
        return true;
      })
      .map((param) => {
        const cloned = structuredClone(param);
        const existingExample =
          cloned.example ??
          cloned.schema?.example ??
          cloned.schema?.default ??
          DEFAULT_PARAM_EXAMPLES[String(cloned.name || "")] ??
          "sample";
        if (String(cloned.in || "").toLowerCase() === "query") {
          cloned.example = existingExample;
          cloned.schema = cloned.schema && typeof cloned.schema === "object" ? cloned.schema : { type: "string" };
          if (cloned.schema.example === undefined) {
            cloned.schema.example = existingExample;
          }
        }
        return cloned;
      });
  }

  if (
    pathStr === "/api/v1/verify" &&
    method.toLowerCase() === "post" &&
    op.requestBody?.content?.["application/json"]
  ) {
    const appJson = op.requestBody.content["application/json"];
    if (appJson.example === undefined) {
      appJson.example = { _oracle_proof: { data_hash: "00" } };
    }
  }

  return op;
}

function collectReferencedSchemas(pathsObj, allSchemas) {
  const referenced = new Set();
  const queue = [];

  function scanRefs(obj) {
    if (!obj || typeof obj !== "object") return;
    if (Array.isArray(obj)) {
      obj.forEach(scanRefs);
      return;
    }
    if (typeof obj.$ref === "string") {
      const prefix = "#/components/schemas/";
      if (obj.$ref.startsWith(prefix)) {
        const schemaName = obj.$ref.slice(prefix.length);
        if (!referenced.has(schemaName)) {
          referenced.add(schemaName);
          queue.push(schemaName);
        }
      }
    }
    for (const val of Object.values(obj)) {
      scanRefs(val);
    }
  }

  scanRefs(pathsObj);
  while (queue.length > 0) {
    const nextName = queue.shift();
    if (allSchemas[nextName]) {
      scanRefs(allSchemas[nextName]);
    }
  }

  const outSchemas = {};
  for (const name of Object.keys(allSchemas)) {
    if (referenced.has(name)) {
      outSchemas[name] = convertSchemaNodeTo303(allSchemas[name]);
    }
  }
  return outSchemas;
}

function buildRapidApiSpec(sourceSpec, { title, description, selectedPaths }) {
  const newPaths = {};
  const usedTags = new Set();

  for (const routePath of selectedPaths) {
    if (EXCLUDED_PATHS.has(routePath)) {
      continue;
    }
    const pathItem = sourceSpec.paths?.[routePath];
    if (!pathItem) {
      throw new Error(`Rota nao encontrada em openapi.json: ${routePath}`);
    }
    const cleanPathItem = {};
    for (const [method, rawOp] of Object.entries(pathItem)) {
      if (!rawOp || typeof rawOp !== "object") continue;
      const cleanOp = sanitizeOperation(method, routePath, rawOp);
      if (Array.isArray(cleanOp.tags)) {
        cleanOp.tags.forEach((t) => usedTags.add(t));
      }
      cleanPathItem[method] = cleanOp;
    }
    newPaths[routePath] = cleanPathItem;
  }

  const filteredTags = Array.isArray(sourceSpec.tags)
    ? sourceSpec.tags.filter((t) => usedTags.has(t.name))
    : [];

  const schemas = collectReferencedSchemas(newPaths, sourceSpec.components?.schemas || {});

  const spec = {
    openapi: "3.0.3",
    info: {
      title,
      version: String(sourceSpec.info?.version || "3.4.0"),
      description,
      contact: sourceSpec.info?.contact || {
        name: "Grid402 Network Intelligence",
        url: "https://grid402.online",
      },
      license: sourceSpec.info?.license || {
        name: "Grid402 Commercial & Open Data Terms",
        url: "https://grid402.online/terms",
      },
    },
    servers: [
      {
        url: "https://grid402.online",
        description: "Grid402 Production",
      },
    ],
  };

  if (filteredTags.length > 0) {
    spec.tags = filteredTags;
  }

  spec.paths = newPaths;

  if (Object.keys(schemas).length > 0) {
    spec.components = { schemas };
  }

  return spec;
}

export function generateSpecs(outputDir = OUTPUT_DIR) {
  const sourceSpec = JSON.parse(fs.readFileSync(SOURCE_OPENAPI, "utf8"));
  fs.mkdirSync(outputDir, { recursive: true });

  const targets = [
    {
      filename: "rapidapi-global.json",
      title: "Grid402 Web-to-Markdown, Email Verify & Network Utilities",
      description:
        "SSRF-hardened URL-to-Markdown extraction for LLMs, Zero-PII B2B Email Verifier (MX/SPF/DMARC), DNS/SSL/ASN & Domain Audits, GLEIF LEI, OFAC SDN screening, Market Snapshots, Forex/Crypto feeds and ECDSA secp256k1 Oracle Proof verification.\n\nNeed pay-per-call crypto settlement (Base, Polygon, Arbitrum, Solana USDC) or Pix (R$ 12 = 400 credits) with zero monthly retainer? Visit https://grid402.online/pass",
      selectedPaths: GLOBAL_PATHS,
    },
    {
      filename: "rapidapi-brazil-kyb.json",
      title: "Grid402 Brazil KYB, CNPJ, CGU Sanctions & ANBIMA Workdays",
      description:
        "Verified Receita Federal CNPJ, Simples Nacional / MEI, CGU CEIS/CNEP Sanctions, KYB 360 AI Dossier, Compliance score, ANBIMA bank workdays/holidays, BCB indicators and IBGE/CEP lookups — Zero-PII (LGPD) with signed ECDSA Oracle Proof.\n\nNeed pay-per-call crypto settlement (Base, Polygon, Arbitrum, Solana USDC) or Pix (R$ 12 = 400 credits) with zero monthly retainer? Visit https://grid402.online/pass",
      selectedPaths: BRAZIL_KYB_PATHS,
    },
    {
      filename: "rapidapi-all.json",
      title: "Grid402 Complete M2M Data & Utilities Hub",
      description:
        "Complete Grid402 catalog combining 26 paid M2M open data, Multi-Chain Token Safety, Prediction Markets, Batch Orchestration, LLM Web-to-Markdown extraction, B2B Email Verification, Network Utility audits and Brazilian KYB/compliance endpoints plus free hub summary and ECDSA secp256k1 signature verification.\n\nNeed pay-per-call crypto settlement (Base, Polygon, Arbitrum, Solana USDC) or Pix (R$ 12 = 400 credits) with zero monthly retainer? Visit https://grid402.online/pass",
      selectedPaths: ALL_PATHS,
    },
  ];

  const written = [];
  for (const item of targets) {
    const spec = buildRapidApiSpec(sourceSpec, item);
    const outPath = path.join(outputDir, item.filename);
    fs.writeFileSync(outPath, JSON.stringify(spec, null, 2) + "\n", "utf8");
    written.push({
      file: outPath,
      filename: item.filename,
      pathsCount: Object.keys(spec.paths).length,
      title: spec.info.title,
    });
  }
  return written;
}

if (
  process.argv[1] &&
  pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url
) {
  const written = generateSpecs();
  console.log(JSON.stringify({ ok: true, generated: written }, null, 2));
}
