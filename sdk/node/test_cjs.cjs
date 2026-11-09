/**
 * Teste de compatibilidade CommonJS para Grid402 SDK (require)
 */

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

const {
  Grid402Client,
  Grid402ActionProvider,
  Grid402Error,
  PaymentRequiredError,
  DEFAULT_GRID402_URL,
  GRID402_ORACLE_WALLET,
  computeDataHash,
  verifyOracleProof,
} = require('./grid402.cjs');

const TEST_ORACLE_PRIVKEY = `-----BEGIN PRIVATE KEY-----
MIGEAgEAMBAGByqGSM49AgEGBSuBBAAKBG0wawIBAQQgbx7yBcDIEAJOTpIxrSji
FuMG+zevhna+MX1NMJ1+yZ+hRANCAARmHsUjjU6W0zb8/uaJbQv8PQ4ToXMRBGTZ
gmgw8Rc7+YqJnqhKLmX0wIi9L9bsigil7fPURX2thUSwAM6AceQn
-----END PRIVATE KEY-----`;

function generateMockSignedResponse(data, isSandbox = false) {
  const hash = computeDataHash(data);
  const timestamp = Math.floor(Date.now() / 1000);
  const msg = `${hash}:${timestamp}:${isSandbox ? 'sandbox' : 'mainnet'}`;
  const signer = crypto.createSign('sha256');
  signer.update(msg);
  const signature = '0x' + signer.sign(TEST_ORACLE_PRIVKEY, 'hex');

  return {
    ...data,
    _oracle_proof: {
      oracle_address: GRID402_ORACLE_WALLET,
      timestamp,
      data_hash: hash,
      signature,
      algorithm: 'secp256k1-sha256',
      sandbox: isSandbox,
    },
  };
}

test('CJS - Grid402Client carregado via require()', () => {
  const client = new Grid402Client({ sandbox: true });
  assert.equal(client.baseUrl, DEFAULT_GRID402_URL);
  assert.equal(client.sandbox, true);
  assert.equal(typeof client.getCnpj, 'function');
  assert.equal(typeof client.getCep, 'function');
  assert.equal(typeof client.getForex, 'function');
  assert.equal(typeof client.getCrypto, 'function');
  assert.equal(typeof client.getIndicators, 'function');
  assert.equal(typeof client.getHolidays, 'function');
  assert.equal(typeof client.getWorkdays, 'function');
  assert.equal(typeof client.getSanctions, 'function');
  assert.equal(typeof client.getSimples, 'function');
  assert.equal(typeof client.getCompliance, 'function');
  assert.equal(typeof client.verifyProof, 'function');
});

test('CJS - Grid402ActionProvider carregado via require()', () => {
  const provider = new Grid402ActionProvider({ sandbox: true });
  assert.equal(provider.sandbox, true);
  assert.equal(typeof provider.queryCnpj, 'function');
  assert.equal(typeof provider.queryCep, 'function');
  assert.equal(typeof provider.queryForex, 'function');
  assert.equal(typeof provider.queryIndicators, 'function');
  assert.equal(typeof provider.queryHolidays, 'function');
  assert.equal(typeof provider.queryWorkdays, 'function');
  assert.equal(typeof provider.querySanctions, 'function');
  assert.equal(typeof provider.querySimples, 'function');
  assert.equal(typeof provider.queryCompliance, 'function');
});

test('CJS - Classes de Erro disponiveis via require()', () => {
  assert(new PaymentRequiredError({}) instanceof Grid402Error);
});

test('CJS - Validacao de prova criptografica e deteccao de adulteracao', () => {
  const client = new Grid402Client();
  const sample = { status: 'success', service: 'workdays', workdays: 5 };
  const signed = generateMockSignedResponse(sample, false);

  const validRes = client.verifyProof(signed);
  assert.equal(validRes.valid, true);

  signed.workdays = 99;
  const tamperedRes = client.verifyProof(signed);
  assert.equal(tamperedRes.valid, false);
  assert.equal(tamperedRes.error, 'DataHashMismatch');
});

