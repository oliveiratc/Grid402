/**
 * Plugin Oficial Grid402 para ElizaOS
 */

import { getForexRateAction } from './actions/forex.js';
import { getCryptoPriceAction } from './actions/crypto.js';
import { getGlobalMarketsAction } from './actions/globalMarkets.js';
import { checkSanctionsAction } from './actions/sanctions.js';
import { lookupLeiAction } from './actions/lei.js';
import { lookupCnpjAction } from './actions/cnpj.js';
import { grid402Provider } from './providers/grid402Provider.js';

export * from './types.js';
export * from './client.js';
export {
  getForexRateAction,
  getCryptoPriceAction,
  getGlobalMarketsAction,
  checkSanctionsAction,
  lookupLeiAction,
  lookupCnpjAction,
  grid402Provider,
};

export const grid402Plugin = {
  name: 'grid402',
  description: 'Grid402 Open Data, Market Intelligence and Compliance Screening Hub for ElizaOS agents with Base x402 settlement',
  actions: [
    getForexRateAction,
    getCryptoPriceAction,
    getGlobalMarketsAction,
    checkSanctionsAction,
    lookupLeiAction,
    lookupCnpjAction,
  ],
  providers: [grid402Provider],
  evaluators: [],
};

export default grid402Plugin;

