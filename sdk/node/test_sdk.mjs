/**
 * Testes unitarios do SDK Grid402 em ESM (Node.js test runner)
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';

import {
  Grid402Client,
  Grid402ActionProvider,
  Grid402Error,
  PaymentRequiredError,
  DEFAULT_GRID402_URL,
  GRID402_ORACLE_WALLET,
  computeDataHash,
  verifyOracleProof,
} from './grid402.mjs';

test('Grid402Client - Inicializacao e opcoes padrao', () => {
  const client = new Grid402Client();
  assert.equal(client.baseUrl, DEFAULT_GRID402_URL);
  assert.equal(client.sandbox, false);
  assert.equal(client.timeout, 10000);
  assert.equal(client.raiseForPayment, true);
});

test('Grid402Client - Inicializacao em modo Sandbox', () => {
  const client = new Grid402Client({ sandbox: true, timeout: 5000 });
  assert.equal(client.sandbox, true);
  assert.equal(client.timeout, 5000);
});

test('Grid402Client - Injeção de cabecalho X-Payment em modo sandbox', async () => {
  const client = new Grid402Client({ sandbox: true });

  const originalFetch = globalThis.fetch;
  let capturedHeaders = null;

  globalThis.fetch = async (url, options) => {
    capturedHeaders = options.headers;
    return {
      status: 200,
      ok: true,
      text: async () => JSON.stringify({ status: 'sandbox_ok' }),
    };
  };

  try {
    const res = await client.getCnpj('00000000000191');
    assert.deepEqual(res, { status: 'sandbox_ok' });
    assert.equal(capturedHeaders['X-Payment'], 'demo');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('Grid402Client - Lancamento de PaymentRequiredError com Teaser Data', async () => {
  const client = new Grid402Client({ sandbox: false, raiseForPayment: true });

  const originalFetch = globalThis.fetch;
  const challengeBody = {
    error: 'PaymentRequired',
    message: 'Pagamento obrigatorio de 0.0005 USDC na Base',
    price_usdc: '0.0005',
    recipient_wallet: '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
    network: 'Base',
    token: 'USDC',
    preview_data: { amostra: true, empresa: 'BANCO DO BRASIL' },
  };

  globalThis.fetch = async () => ({
    status: 402,
    ok: false,
    text: async () => JSON.stringify(challengeBody),
  });

  try {
    await assert.rejects(
      async () => {
        await client.getForex();
      },
      (err) => {
        assert(err instanceof PaymentRequiredError);
        assert.equal(err.priceUsdc, '0.0005');
        assert.equal(err.recipientWallet, '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90');
        assert.deepEqual(err.previewData, { amostra: true, empresa: 'BANCO DO BRASIL' });
        return true;
      }
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('Grid402ActionProvider - Auto-liquidacao com Coinbase AgentKit wallet_provider', async () => {
  const provider = new Grid402ActionProvider({ sandbox: false });

  let transferCalledWith = null;
  const mockWalletProvider = {
    async transfer(params) {
      transferCalledWith = params;
      return { transactionHash: '0xmocked_node_tx_hash_67890' };
    },
  };

  let callCount = 0;
  const originalFetch = globalThis.fetch;

  globalThis.fetch = async (url, options) => {
    callCount++;
    if (callCount === 1) {
      return {
        status: 402,
        ok: false,
        json: async () => ({
          error: 'PaymentRequired',
          price_usdc: '0.0020',
          recipient_wallet: '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90',
        }),
      };
    }
    // Segunda chamada com o comprovante
    assert.equal(options.headers['X-Payment'], '0xmocked_node_tx_hash_67890');
    return {
      status: 200,
      ok: true,
      json: async () => ({
        razao_social: 'BANCO DO BRASIL SA',
        cnpj: '00000000000191',
      }),
    };
  };

  try {
    const res = await provider.queryCnpj('00000000000191', mockWalletProvider);
    assert.equal(res.razao_social, 'BANCO DO BRASIL SA');
    assert.equal(transferCalledWith.to, '0xa592625adbceb8e025ac383f1a3fd8ebb6a63f90');
    assert.equal(transferCalledWith.amount, '0.0020');
    assert.equal(transferCalledWith.assetId, 'usdc');
    assert.equal(res._x402_settlement.tx_hash, '0xmocked_node_tx_hash_67890');
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('Grid402Client - Metodos getIndicators e getHolidays', async () => {
  const client = new Grid402Client({ sandbox: true });

  const originalFetch = globalThis.fetch;
  const capturedUrls = [];

  globalThis.fetch = async (url) => {
    capturedUrls.push(url);
    if (url.includes('/api/v1/indicators')) {
      return {
        status: 200,
        ok: true,
        text: async () => JSON.stringify({
          status: 'success',
          service: 'indicators',
          indicators: { selic_target: 13.75, cdi_annual: 13.65 }
        })
      };
    }
    return {
      status: 200,
      ok: true,
      text: async () => JSON.stringify({
        status: 'success',
        service: 'holidays',
        year: 2026,
        total_holidays: 15,
        holidays: [{ date: '2026-01-01', name: 'Confraternizacao Universal' }]
      })
    };
  };

  try {
    const ind = await client.getIndicators();
    assert.equal(ind.status, 'success');
    assert.equal(ind.indicators.selic_target, 13.75);
    assert(capturedUrls[0].includes('/api/v1/indicators'));

    const hol = await client.getHolidays(2026);
    assert.equal(hol.status, 'success');
    assert.equal(hol.year, 2026);
    assert.equal(hol.total_holidays, 15);
    assert(capturedUrls[1].includes('/api/v1/holidays?year=2026'));
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test('Grid402Client - Metodo getWorkdays', async () => {
  const client = new Grid402Client({ sandbox: true });

  const originalFetch = globalThis.fetch;
  let capturedUrl = null;

  globalThis.fetch = async (url) => {
    capturedUrl = url;
    return {
      status: 200,
      ok: true,
      text: async () => JSON.stringify({
        status: 'success',
        service: 'workdays',
        mode: 'range',
        workdays: 5,
        calendar_days: 7
      })
    };
  };

  try {
    const res = await client.getWorkdays({ startDate: '2026-11-06', endDate: '2026-11-13' });
    assert.equal(res.status, 'success');
    assert.equal(res.workdays, 5);
    assert(capturedUrl.includes('/api/v1/workdays'));
    assert(capturedUrl.includes('start_date=2026-11-06'));
  } finally {
    globalThis.fetch = originalFetch;
  }
});

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

test('Grid402 - Determinismo do hash canonico', () => {
  const objA = { status: 'success', service: 'workdays', workdays: 5 };
  const objB = { workdays: 5, service: 'workdays', status: 'success' };
  assert.equal(computeDataHash(objA), computeDataHash(objB));
  assert(computeDataHash(objA).startsWith('0x'));
});

test('Grid402Client - Validacao positiva de prova criptografica do oraculo', () => {
  const client = new Grid402Client();
  const sample = { status: 'success', service: 'workdays', workdays: 5 };
  const signed = generateMockSignedResponse(sample, false);

  const result = client.verifyProof(signed);
  assert.equal(result.valid, true);
  assert.equal(result.oracleAddress, GRID402_ORACLE_WALLET.toLowerCase());
  assert.equal(result.sandbox, false);
});

test('Grid402Client - Rejeicao de payload adulterado (DataHashMismatch)', () => {
  const client = new Grid402Client();
  const sample = { status: 'success', service: 'workdays', workdays: 5 };
  const signed = generateMockSignedResponse(sample, false);

  signed.workdays = 6;

  const result = client.verifyProof(signed);
  assert.equal(result.valid, false);
  assert.equal(result.error, 'DataHashMismatch');
});

test('Grid402Client - Rejeicao de carteira de oraculo divergente', () => {
  const client = new Grid402Client();
  const sample = { status: 'success', service: 'workdays', workdays: 5 };
  const signed = generateMockSignedResponse(sample, false);

  const result = client.verifyProof(signed, '0x000000000000000000000000000000000000dead');
  assert.equal(result.valid, false);
  assert.equal(result.error, 'OracleAddressMismatch');
});

test('Grid402Client - Rejeicao de assinatura forjada ou corrompida', () => {
  const client = new Grid402Client();
  const sample = { status: 'success', service: 'workdays', workdays: 5 };
  const signed = generateMockSignedResponse(sample, false);

  signed._oracle_proof.signature = '0x1234567890abcdef';

  const result = client.verifyProof(signed);
  assert.equal(result.valid, false);
});

test('Grid402Client - Validacao de prova em modo Sandbox', () => {
  const client = new Grid402Client({ sandbox: true });
  const sample = { status: 'success', service: 'indicators', selic: '10.75' };
  const signed = generateMockSignedResponse(sample, true);

  const result = client.verifyProof(signed);
  assert.equal(result.valid, true);
  assert.equal(result.sandbox, true);
});

test('Grid402Client - Metodos de Compliance, Sancoes e Simples Nacional', async () => {
  const client = new Grid402Client({ sandbox: true });

  const originalFetch = globalThis.fetch;
  const recordedUrls = [];

  globalThis.fetch = async (url) => {
    recordedUrls.push(url);
    return {
      status: 200,
      ok: true,
      text: async () => JSON.stringify({ status: 'success', cnpj: '00000000000191' }),
    };
  };

  try {
    await client.getSanctions('00.000.000/0001-91');
    await client.getSimples('00.000.000/0001-91');
    await client.getCompliance('00.000.000/0001-91');

    assert.equal(recordedUrls.length, 3);
    assert(recordedUrls[0].includes('/api/v1/sanctions?cnpj=00000000000191'));
    assert(recordedUrls[1].includes('/api/v1/simples?cnpj=00000000000191'));
    assert(recordedUrls[2].includes('/api/v1/compliance?cnpj=00000000000191'));
  } finally {
    globalThis.fetch = originalFetch;
  }
});
