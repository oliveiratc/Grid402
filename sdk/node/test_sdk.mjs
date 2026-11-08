/**
 * Testes unitarios do SDK Grid402 em ESM (Node.js test runner)
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
  Grid402Client,
  Grid402ActionProvider,
  Grid402Error,
  PaymentRequiredError,
  DEFAULT_GRID402_URL,
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
    recipient_wallet: '0xa31c31069c43ac1c0075a93c325b0dcd7744356c',
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
        assert.equal(err.recipientWallet, '0xa31c31069c43ac1c0075a93c325b0dcd7744356c');
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
          recipient_wallet: '0xa31c31069c43ac1c0075a93c325b0dcd7744356c',
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
    assert.equal(transferCalledWith.to, '0xa31c31069c43ac1c0075a93c325b0dcd7744356c');
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
