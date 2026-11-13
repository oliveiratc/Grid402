/**
 * Teste unitário do SDK Node.js Grid402
 */

import { Grid402Client, Grid402Error, PaymentRequiredError, ReplayAttackError } from '../sdk/node/grid402.mjs';
import assert from 'node:assert';

console.log('=== Testando SDK Node.js Grid402 ===');

// 1. Instanciação do Cliente
const client = new Grid402Client({ baseUrl: 'http://127.0.0.1:8942', timeout: 3000 });
assert.strictEqual(client.baseUrl, 'http://127.0.0.1:8942', 'Base URL deve ser normalizada sem barra final');
assert.strictEqual(client.timeout, 3000, 'Timeout deve ser configurado');
console.log('✓ Grid402Client instanciado com sucesso!');

// 2. Verificação de métodos
assert.strictEqual(typeof client.getSummary, 'function', 'getSummary deve existir');
assert.strictEqual(typeof client.getChallenge, 'function', 'getChallenge deve existir');
assert.strictEqual(typeof client.getForex, 'function', 'getForex deve existir');
assert.strictEqual(typeof client.getCrypto, 'function', 'getCrypto deve existir');
assert.strictEqual(typeof client.getDns, 'function', 'getDns deve existir');
assert.strictEqual(typeof client.getSsl, 'function', 'getSsl deve existir');
assert.strictEqual(typeof client.getAsn, 'function', 'getAsn deve existir');
assert.strictEqual(typeof client.getCep, 'function', 'getCep deve existir');
assert.strictEqual(typeof client.isHealthy, 'function', 'isHealthy deve existir');
assert.strictEqual(typeof client.guard, 'function', 'guard deve existir');
console.log('✓ Todos os métodos dos 6 micro-serviços estão disponíveis!');

// 3. Teste de classes de erro
const challengeMock = {
    error: 'PaymentRequired',
    message: 'Pagamento requerido',
    recipient_wallet: '0xa31c31069c43ac1c0075a93c325b0dcd7744356c',
    price_usdc: '0.0050',
    price_raw_units: 5000,
    network: 'Base',
    chain_id: 8453,
    token_contract: '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913',
};
const err = new PaymentRequiredError(challengeMock);
assert.strictEqual(err.recipientWallet, challengeMock.recipient_wallet);
assert.strictEqual(err.priceUsdc, '0.0050');
assert.strictEqual(err.priceRawUnits, 5000);
assert(err instanceof Grid402Error);
console.log('✓ Tratamento de PaymentRequiredError e desafio x402 validado!');

console.log('=== SDK Node.js Grid402 100% Homologado! ===');

