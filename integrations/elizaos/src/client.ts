/**
 * Cliente HTTP para a API Grid402 com suporte a Session Pass e x402
 */

import { Grid402PluginConfig, Grid402Response } from './types.js';

export const DEFAULT_GRID402_BASE_URL = 'https://grid402.online';
export const GRID402_ORACLE_WALLET = '0xa31c31069c43ac1c0075a93c325b0dcd7744356c';
export const BASE_USDC_CONTRACT = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

export class Grid402Client {
  private baseUrl: string;
  private passToken?: string;
  private sandbox: boolean;

  constructor(config?: Grid402PluginConfig) {
    this.baseUrl = (config?.baseUrl || process.env.GRID402_BASE_URL || DEFAULT_GRID402_BASE_URL).replace(/\/$/, '');
    this.passToken = config?.passToken || process.env.GRID402_PASS_TOKEN;
    this.sandbox = config?.sandbox ?? (process.env.GRID402_SANDBOX === 'true');
  }

  /**
   * Executa consulta a um endpoint do Grid402
   */
  public async request<T = any>(endpoint: string, params: Record<string, string | number | undefined> = {}): Promise<Grid402Response<T>> {
    const url = new URL(endpoint.startsWith('/') ? endpoint : `/${endpoint}`, this.baseUrl);
    
    for (const [key, val] of Object.entries(params)) {
      if (val !== undefined && val !== null && val !== '') {
        url.searchParams.set(key, String(val));
      }
    }

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'User-Agent': 'Grid402-ElizaOS-Plugin/1.0',
    };

    if (this.passToken) {
      headers['X-Payment'] = this.passToken.trim();
    } else if (this.sandbox) {
      headers['X-Payment'] = 'demo';
    }

    const resp = await fetch(url.toString(), {
      method: 'GET',
      headers,
    });

    const data = await resp.json() as Grid402Response<T>;

    if (resp.status === 402) {
      throw new Error(`[Grid402 402 Payment Required] Saldo insuficiente ou transação necessária na Base Mainnet. Recarregue seu Session Pass em https://grid402.online/#topup ou configure GRID402_PASS_TOKEN.`);
    }

    if (!resp.ok && resp.status !== 200) {
      throw new Error(`[Grid402 Error ${resp.status}] ${data.message || data.error || 'Falha na consulta'}`);
    }

    return data;
  }
}

