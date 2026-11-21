import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { execFileSync } from "node:child_process";
import { Wallet } from "ethers";
import {
  DEFAULT_EVM_ADDR,
  DEFAULT_NONCE,
  REPO_ROOT,
  buildAltMessageWithoutVerifyPrefix,
  buildMessage,
  buildPayload,
  isEvmAlreadyProven,
  loadDotenv,
  messageUrl,
  normalizeNonce,
  normalizePrivateKey,
  normalizeSignatureVariants,
  parseArgs,
  signEvmProof,
  verifyEvmProof,
} from "../scripts/paycensus_bind_evm.mjs";

const SCRIPT_PATH = path.join(REPO_ROOT, "scripts", "paycensus_bind_evm.mjs");

async function runTests() {
  // 1. normalizeNonce
  assert.equal(
    normalizeNonce("c05e1ee27fe0c75e2b907b9d842ee45f"),
    DEFAULT_NONCE
  );
  assert.equal(
    normalizeNonce("  paycensus-verify=c05e1ee27fe0c75e2b907b9d842ee45f  "),
    DEFAULT_NONCE
  );
  assert.throws(() => normalizeNonce("   "), /Nonce vazio/);

  // 2. buildMessage
  assert.equal(
    buildMessage("c05e1ee27fe0c75e2b907b9d842ee45f"),
    "paycensus-addr-proof=paycensus-verify=c05e1ee27fe0c75e2b907b9d842ee45f"
  );
  assert.equal(
    buildMessage(DEFAULT_NONCE),
    "paycensus-addr-proof=paycensus-verify=c05e1ee27fe0c75e2b907b9d842ee45f"
  );
  assert.equal(
    messageUrl(12),
    "https://trust.paycensus.com/api/verify/addr-proof-message?vid=12"
  );

  // 3. normalizePrivateKey (aceita com/sem 0x, nao vaza chave no erro)
  const rawHex64 = "11".repeat(32);
  assert.equal(normalizePrivateKey(rawHex64), `0x${rawHex64}`);
  assert.equal(normalizePrivateKey(`  0x${rawHex64}\n`), `0x${rawHex64}`);
  const secretInvalidSample = "deadbeef_nao_deve_vazar_no_log";
  assert.throws(
    () => normalizePrivateKey(secretInvalidSample),
    (err) => err instanceof Error && !err.message.includes(secretInvalidSample)
  );
  assert.throws(() => normalizePrivateKey(""), /vazia/i);

  // 4. signEvmProof + verifyEvmProof com wallet efemera em memoria
  const ephemeral = Wallet.createRandom();
  const msg = buildMessage("c05e1ee27fe0c75e2b907b9d842ee45f");
  const { address, signature } = await signEvmProof(msg, ephemeral.privateKey);
  assert.equal(address.toLowerCase(), ephemeral.address.toLowerCase());
  assert.match(signature, /^0x[0-9a-fA-F]{130}$/);
  assert.equal(signature.length, 132);

  const okLower = verifyEvmProof(msg, signature, ephemeral.address.toLowerCase());
  assert.equal(okLower.ok, true);
  assert.equal(okLower.recovered.toLowerCase(), ephemeral.address.toLowerCase());

  const okChecksum = verifyEvmProof(msg, signature, ephemeral.address);
  assert.equal(okChecksum.ok, true);

  const wrongAddr = verifyEvmProof(msg, signature, DEFAULT_EVM_ADDR);
  assert.equal(wrongAddr.ok, false);
  assert.match(wrongAddr.error, /diverge/);

  const badSig = verifyEvmProof(msg, "0x1234", ephemeral.address);
  assert.equal(badSig.ok, false);
  assert.match(badSig.error, /130 caracteres hex/);

  // 5. buildPayload estrito { vid, signature }
  const payload = buildPayload(12, signature);
  assert.deepEqual(Object.keys(payload), ["vid", "signature"]);
  assert.equal(payload.vid, 12);
  assert.equal(payload.signature, signature);

  // 6. isEvmAlreadyProven
  assert.equal(
    isEvmAlreadyProven(
      { addresses: [{ chain: "evm", addr: DEFAULT_EVM_ADDR, proven: 0 }] },
      DEFAULT_EVM_ADDR
    ),
    false
  );
  assert.equal(
    isEvmAlreadyProven(
      { addresses: [{ chain: "evm", addr: DEFAULT_EVM_ADDR.toUpperCase().replace("0X", "0x"), proven: 1 }] },
      DEFAULT_EVM_ADDR
    ),
    true
  );
  assert.equal(
    isEvmAlreadyProven(
      { addresses: [{ chain: "solana", addr: DEFAULT_EVM_ADDR, proven: 1 }] },
      DEFAULT_EVM_ADDR
    ),
    false
  );

  // 7. loadDotenv (BOM, export, aspas, comentarios, preserva existente)
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), "grid402-evm-env-"));
  const envFile = path.join(tmpDir, ".env.test");
  try {
    fs.writeFileSync(
      envFile,
      "\uFEFF# comentario\n" +
        'export GRID402_DOMAIN="custom.example"\n' +
        "GRID402_PAYCENSUS_NONCE='abc123'\n" +
        "GRID402_EXISTING=should_not_override\n",
      "utf8"
    );
    const fakeEnv = { GRID402_EXISTING: "kept" };
    loadDotenv([envFile], fakeEnv);
    assert.equal(fakeEnv.GRID402_DOMAIN, "custom.example");
    assert.equal(fakeEnv.GRID402_PAYCENSUS_NONCE, "abc123");
    assert.equal(fakeEnv.GRID402_EXISTING, "kept");
  } finally {
    fs.rmSync(tmpDir, { recursive: true, force: true });
  }

  // 8. parseArgs + buildAltMessageWithoutVerifyPrefix + normalizeSignatureVariants
  assert.equal(
    buildAltMessageWithoutVerifyPrefix("c05e1ee27fe0c75e2b907b9d842ee45f"),
    "paycensus-addr-proof=c05e1ee27fe0c75e2b907b9d842ee45f"
  );

  const variantsA = normalizeSignatureVariants(signature);
  assert.match(variantsA.canonical, /(1b|1c)$/);
  assert.match(variantsA.yParity, /(00|01)$/);
  assert.equal(variantsA.variants.length, 2);
  assert.notEqual(variantsA.variants[0], variantsA.variants[1]);

  // Passar v=00/01 deve normalizar canonical para 1b/1c mantendo o mesmo signer
  const variantsFromYParity = normalizeSignatureVariants(variantsA.yParity);
  assert.equal(variantsFromYParity.originalVHex, variantsA.yParity.slice(-2));
  assert.equal(variantsFromYParity.canonical, variantsA.canonical);
  assert.equal(
    verifyEvmProof(msg, variantsFromYParity.canonical, ephemeral.address).ok,
    true
  );

  const parsed = parseArgs([
    "--browser",
    "--port",
    "4321",
    "--vid",
    "12",
    "--dry-run",
    "--force",
    "--raw-message",
    "custom-msg",
    "--probe-alt",
  ]);
  assert.equal(parsed.browser, true);
  assert.equal(parsed.port, 4321);
  assert.equal(parsed.vid, 12);
  assert.equal(parsed.dryRun, true);
  assert.equal(parsed.force, true);
  assert.equal(parsed.rawMessage, "custom-msg");
  assert.equal(parsed.probeAlt, true);
  assert.throws(() => parseArgs(["--unknown"]), /desconhecido/);
  assert.throws(() => parseArgs(["--port", "abc"]), /Porta invalida/);

  // 9. CLI subprocesso --print-message (sem rede)
  const printOut = execFileSync(
    process.execPath,
    [
      SCRIPT_PATH,
      "--print-message",
      "--nonce",
      "c05e1ee27fe0c75e2b907b9d842ee45f",
    ],
    { encoding: "utf8" }
  );
  assert.ok(
    printOut.includes(
      "paycensus-addr-proof=paycensus-verify=c05e1ee27fe0c75e2b907b9d842ee45f"
    )
  );
  assert.ok(
    printOut.toLowerCase().includes(DEFAULT_EVM_ADDR.toLowerCase())
  );

  // 10. CLI subprocesso assinatura invalida rejeitada localmente (sem POST)
  let failedAsExpected = false;
  try {
    execFileSync(
      process.execPath,
      [
        SCRIPT_PATH,
        "--signature",
        "0x1234",
        "--nonce",
        "c05e1ee27fe0c75e2b907b9d842ee45f",
        "--dry-run",
      ],
      { encoding: "utf8", stdio: "pipe" }
    );
  } catch (err) {
    failedAsExpected = err.status !== 0;
    assert.match(String(err.stderr), /130 caracteres hex/);
  }
  assert.equal(failedAsExpected, true);

  console.log("PASS: 10/10 blocos de teste paycensus_bind_evm.mjs");
}

await runTests();
