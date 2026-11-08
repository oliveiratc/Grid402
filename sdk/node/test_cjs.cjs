/**
 * Teste de compatibilidade CommonJS para Grid402 SDK (require)
 */

const test = require('node:test');
const assert = require('node:assert/strict');

const {
  Grid402Client,
  Grid402ActionProvider,
  Grid402Error,
  PaymentRequiredError,
  DEFAULT_GRID402_URL,
} = require('./grid402.cjs');

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
});

test('CJS - Classes de Erro disponiveis via require()', () => {
  assert(new PaymentRequiredError({}) instanceof Grid402Error);
});

