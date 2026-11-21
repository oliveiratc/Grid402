'use strict';

const assert = require('node:assert');
const { Grid402, Grid402Api } = require('./index.js');

async function runTests() {
  console.log('=== Testando Pacote Oficial n8n-nodes-grid402 ===');

  const cred = new Grid402Api();
  assert.strictEqual(cred.name, 'grid402Api');
  assert.ok(Array.isArray(cred.properties) && cred.properties.length >= 4);

  const node = new Grid402();
  assert.strictEqual(node.description.name, 'grid402');
  assert.strictEqual(node.description.usableAsTool, true);
  const opProp = node.description.properties.find(p => p.name === 'operation');
  assert.strictEqual(opProp.options.length, 25, 'Deve expor todas as 25 operacoes no seletor do n8n');

  // Mock de execucao no contexto do n8n
  const originalFetch = globalThis.fetch;
  let capturedUrl = '';
  let capturedHeaders = {};
  globalThis.fetch = async (url, opts) => {
    capturedUrl = url;
    capturedHeaders = opts.headers;
    return {
      status: 200,
      json: async () => ({ status: 'success', service: 'dossier', verdict: { decision: 'APPROVED' } }),
    };
  };

  try {
    const fakeContext = {
      getInputData: () => [{ json: {} }],
      getCredentials: async () => ({ authMode: 'sessionPass', sessionPass: 'pass_g402_n8n_test', baseUrl: 'https://grid402.online' }),
      getNodeParameter: (name) => (name === 'operation' ? 'dossier' : '00.000.000/0001-91'),
    };
    const res = await node.execute.call(fakeContext);
    assert.strictEqual(res[0][0].json.verdict.decision, 'APPROVED');
    assert.strictEqual(capturedHeaders['X-Payment'], 'pass_g402_n8n_test');
    assert.ok(capturedUrl.includes('/api/v1/dossier?cnpj=00000000000191'));
    console.log('  ✓ Credenciais, 25 operacoes e execucao do no n8n homologadas com 100% de sucesso!');
  } finally {
    globalThis.fetch = originalFetch;
  }
}

runTests().catch(err => {
  console.error(err);
  process.exit(1);
});
