export declare const DEFAULT_GRID402_URL: string;

export interface ServiceSummary {
    name: string;
    description: string;
    status: 'UP' | 'DEGRADED' | 'DOWN';
    latency_ms: number;
    price: string;
    endpoint: string;
}

export interface SummaryResponse {
    protocol: 'x402';
    network: 'Base';
    chain_id: number;
    status: 'OPERATIONAL' | 'DEGRADED' | 'DOWN';
    timestamp: string;
    updated_at: string;
    services: Record<string, ServiceSummary>;
    rates_sample?: Record<string, any>;
    crypto_sample?: Record<string, any>;
    notice?: string;
}

export interface X402Challenge {
    error: 'PaymentRequired';
    message: string;
    protocol: 'x402';
    network: 'Base';
    chain_id: number;
    token: string;
    token_contract: string;
    price_usdc: string;
    price_raw_units: number;
    recipient_wallet: string;
    action_required: string;
    docs: string;
}

export interface ForexResponse {
    success: boolean;
    timestamp: string;
    cached_seconds_ago: number;
    rates: Record<string, {
        code: string;
        name: string;
        bid: number;
        ask: number;
        high: number;
        low: number;
        pctChange: number;
        create_date: string;
    }>;
}

export interface CryptoResponse {
    success: boolean;
    timestamp: string;
    cached_seconds_ago: number;
    tickers: Record<string, {
        symbol: string;
        name: string;
        price_usd: number;
        change_24h_pct: number;
        market_cap_usd: number;
    }>;
}

export interface DnsResponse {
    success: boolean;
    domain: string;
    timestamp: string;
    resolved_in_ms: number;
    records: {
        A?: string[];
        AAAA?: string[];
        MX?: Array<{ host: string; pri: number }>;
        TXT?: string[];
        NS?: string[];
        CNAME?: string[];
    };
    error?: string;
}

export interface SslResponse {
    success: boolean;
    host: string;
    port: number;
    timestamp: string;
    handshake_ms: number;
    certificate?: {
        common_name: string;
        issuer: string;
        valid_from: string;
        valid_to: string;
        days_remaining: number;
        is_valid: boolean;
        subject_alt_names: string[];
    };
    error?: string;
}

export interface AsnResponse {
    success: boolean;
    ip: string;
    timestamp: string;
    resolved_in_ms: number;
    asn?: string;
    org?: string;
    country?: string;
    cidr?: string;
    error?: string;
}

export interface CepResponse {
    success: boolean;
    cep: string;
    logradouro?: string;
    bairro?: string;
    cidade?: string;
    uf?: string;
    ibge?: string;
    ddd?: string;
    resolved_in_ms: number;
    error?: string;
}

export declare class Grid402Error extends Error {}
export declare class PaymentRequiredError extends Grid402Error {
    challenge: X402Challenge;
    recipientWallet: string;
    priceUsdc: string;
    priceRawUnits: number;
    network: string;
    chainId: number;
    tokenContract: string;
}
export declare class ReplayAttackError extends Grid402Error {}

export interface Grid402Options {
    baseUrl?: string;
    timeout?: number;
}

export declare class Grid402Client {
    constructor(options?: Grid402Options);
    getSummary(): Promise<SummaryResponse>;
    getChallenge(endpoint?: string): Promise<X402Challenge>;
    getForex(paymentTxHash?: string): Promise<ForexResponse>;
    getCrypto(paymentTxHash?: string): Promise<CryptoResponse>;
    getDns(domain: string, paymentTxHash?: string): Promise<DnsResponse>;
    getSsl(host: string, paymentTxHash?: string): Promise<SslResponse>;
    getAsn(ip?: string, paymentTxHash?: string): Promise<AsnResponse>;
    getCep(code: string, paymentTxHash?: string): Promise<CepResponse>;
    isHealthy(serviceName?: string): Promise<boolean>;
    guard<T>(serviceName: string, action: () => Promise<T> | T, fallback?: () => Promise<T> | T): Promise<T>;
}
