/**
 * Tipos e Interfaces do Plugin Grid402 para ElizaOS
 */

export interface Grid402PluginConfig {
  baseUrl?: string;
  passToken?: string;
  sandbox?: boolean;
}

export interface Grid402Response<T = any> {
  success?: boolean;
  status: string | number;
  timestamp: string;
  data?: T;
  _oracle_proof?: {
    signature: string;
    signer: string;
    hash: string;
  };
  [key: string]: any;
}

export interface ForexResult {
  base: string;
  rates: Record<string, number>;
  source: string;
  timestamp: string;
}

export interface CryptoResult {
  tickers: Record<string, {
    price_usd: number;
    price_brl?: number;
    change_24h_pct?: number;
  }>;
  timestamp: string;
}

export interface SanctionsResult {
  target: string;
  sanctioned: boolean;
  risk_level: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  compliance_decision: 'APPROVE_TRANSACTION' | 'MANUAL_REVIEW' | 'BLOCK_TRANSACTION';
  matched_lists: string[];
}

export interface LeiResult {
  lei: string;
  legal_name: string;
  status: string;
  jurisdiction: string;
}

export interface CnpjResult {
  cnpj: string;
  razao_social: string;
  situacao_cadastral: string;
  cnae_principal: string;
  uf: string;
}

