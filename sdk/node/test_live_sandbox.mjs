/**
 * Testes de integracao do SDK Grid402 contra a API publica em modo Sandbox
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { Grid402Client } from './grid402.mjs';

const client = new Grid402Client({
  baseUrl: 'https://grid402.online',
  sandbox: true,
  timeout: 15000,
});

test('Live Sandbox - Consulta de status do ecossistema', async () => {
  const summary = await client.getSummary();
  assert.equal(summary.status, 'OPERATIONAL');
  assert.equal(summary.protocol, 'x402');
  assert.ok(summary.services);
});

test('Live Sandbox - Consulta de cotacoes Forex', async () => {
  const forex = await client.getForex();
  assert.equal(forex.status, 'UP');
  assert.ok(forex.rates);
  assert.ok(forex.rates.USD);
  assert.equal(forex.sandbox, true);
});

test('Live Sandbox - Consulta de precos de Criptoativos', async () => {
  const crypto = await client.getCrypto();
  assert.equal(crypto.status, 'UP');
  assert.ok(crypto.tickers);
  assert.ok(crypto.tickers.BTC);
  assert.equal(crypto.sandbox, true);
});

test('Live Sandbox - Consulta de CEP postal real (Av. Paulista 01310100)', async () => {
  const cep = await client.getCep('01310100');
  assert.equal(cep.success, true);
  assert.equal(cep.uf, 'SP');
  assert.equal(cep.sandbox, true);
});

test('Live Sandbox - Consulta de CNPJ cadastral real (Banco do Brasil 00000000000191)', async () => {
  const cnpj = await client.getCnpj('00000000000191');
  assert.equal(cnpj.success, true);
  assert.ok(cnpj.razao_social);
  assert.equal(cnpj.sandbox, true);
});

test('Live Sandbox - Consulta de Indicadores Economicos oficiais (Selic, CDI e IPCA)', async () => {
  const ind = await client.getIndicators();
  assert.equal(ind.status, 'success');
  assert.ok(ind.indicators);
  assert.ok(ind.indicators.selic_target > 0);
  assert.ok(ind.indicators.cdi_annual > 0);
  assert.equal(ind.sandbox, true);
});

test('Live Sandbox - Consulta de Feriados Nacionais e Calendario Bancario (2026)', async () => {
  const hol = await client.getHolidays(2026);
  assert.equal(hol.status, 'success');
  assert.equal(hol.year, 2026);
  assert.ok(hol.total_holidays >= 14);
  assert.equal(hol.sandbox, true);
});

