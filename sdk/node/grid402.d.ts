export declare const DEFAULT_GRID402_URL: string;
export declare const GRID402_ORACLE_WALLET: string;
export declare const BASE_USDC_CONTRACT: string;

export interface ServiceSummary {
    name: string;
    description: string;
    status: 'UP' | 'DEGRADED' | 'DOWN';
    latency_ms?: number;
    price?: string;
    endpoint: string;
    category?: string;
}

export interface SummaryResponse {
    protocol: 'x402';
    network: 'Base';
    chain_id: number;
    status: 'OPERATIONAL' | 'DEGRADED' | 'DOWN';
    timestamp: string;
    services: Record<string, ServiceSummary>;
    rates_sample?: Record<string, any>;
    crypto_sample?: Record<string, any>;
    notice?: string;
}

export interface X402Challenge {
    error?: string;
    message: string;
    protocol: 'x402';
    network: 'Base';
    chain_id: number;
    token: string;
    token_contract: string;
    price_usdc: string;
    price_raw_units?: number;
    recipient_wallet: string;
    preview_data?: Record<string, any>;
    payment_details?: Record<string, any>;
    action_required?: string;
    docs?: string;
    payment_required?: boolean;
}

export interface ForexResponse {
    service: string;
    status: string;
    base: string;
    timestamp: string;
    rates: Record<string, {
        code: string;
        name: string;
        bid: number;
        ask: number;
        high?: number;
        low?: number;
        pctChange: number;
        timestamp?: string;
    }>;
    sandbox?: boolean;
}

export interface CryptoResponse {
    service: string;
    status: string;
    network: string;
    chain_id: number;
    timestamp: string;
    tickers: Record<string, {
        symbol: string;
        name: string;
        price_usd: number;
        timestamp?: string;
    }>;
    sandbox?: boolean;
}

export interface DnsResponse {
    success: boolean;
    domain: string;
    timestamp: string;
    records: {
        A?: string[];
        AAAA?: string[];
        MX?: Array<{ host: string; pri: number }>;
        TXT?: string[];
        NS?: string[];
        CNAME?: string[];
    };
    sandbox?: boolean;
    error?: string;
}

export interface SslResponse {
    success: boolean;
    host: string;
    port: number;
    timestamp: string;
    certificate?: {
        common_name: string;
        issuer: string;
        valid_from: string;
        valid_to: string;
        days_remaining: number;
        is_valid: boolean;
        subject_alt_names: string[];
    };
    sandbox?: boolean;
    error?: string;
}

export interface AsnResponse {
    success: boolean;
    ip: string;
    timestamp: string;
    asn?: string;
    org?: string;
    country?: string;
    cidr?: string;
    sandbox?: boolean;
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
    sandbox?: boolean;
    error?: string;
}

export interface CnpjResponse {
    success: boolean;
    cnpj: string;
    razao_social: string;
    nome_fantasia?: string;
    situacao_cadastral?: string;
    data_inicio_atividade?: string;
    cnae_fiscal?: string;
    cnae_fiscal_descricao?: string;
    logradouro?: string;
    numero?: string;
    municipio?: string;
    uf?: string;
    capital_social?: number;
    sandbox?: boolean;
    error?: string;
}

export interface IndicatorsResponse {
    status: string;
    service: string;
    timestamp: string;
    indicators: {
        selic_target: number;
        selic_daily: number;
        cdi_annual: number;
        cdi_daily: number;
        ipca_12m: number;
        ipca_monthly: number;
        reference_date: string;
        ipca_reference_month: string;
    };
    source: string;
    cached?: boolean;
    sandbox?: boolean;
    error?: string;
}

export interface HolidayItem {
    date: string;
    name: string;
    type: 'national' | 'banking' | 'religious' | string;
    day_of_week: string;
    is_banking_holiday: boolean;
}

export interface HolidaysResponse {
    status: string;
    service: string;
    year: number;
    total_holidays: number;
    holidays: HolidayItem[];
    source: string;
    cached?: boolean;
    sandbox?: boolean;
    error?: string;
}

export interface SettlementReceipt {
    settled: boolean;
    tx_hash: string;
    price_usdc: string;
    network: string;
    token: string;
}

export declare class Grid402Error extends Error {}

export declare class PaymentRequiredError extends Grid402Error {
    challenge: X402Challenge;
    recipientWallet: string;
    priceUsdc: string;
    network: string;
    chainId: number;
    token: string;
    tokenContract: string;
    previewData: Record<string, any> | null;
    paymentDetails: Record<string, any> | null;
}

export declare class ReplayAttackError extends Grid402Error {}

export interface Grid402Options {
    baseUrl?: string;
    sandbox?: boolean;
    paymentTxHash?: string;
    timeout?: number;
    raiseForPayment?: boolean;
}

export declare class Grid402Client {
    baseUrl: string;
    sandbox: boolean;
    paymentTxHash: string | null;
    timeout: number;
    raiseForPayment: boolean;

    constructor(options?: Grid402Options);

    getSummary(): Promise<SummaryResponse>;
    getChallenge(endpoint?: string): Promise<X402Challenge>;
    getForex(paymentTxHash?: string): Promise<ForexResponse>;
    getCrypto(paymentTxHash?: string, symbol?: string): Promise<CryptoResponse>;
    getDns(domain: string, paymentTxHash?: string): Promise<DnsResponse>;
    getSsl(host: string, paymentTxHash?: string): Promise<SslResponse>;
    getAsn(ip?: string, paymentTxHash?: string): Promise<AsnResponse>;
    getCep(code: string, paymentTxHash?: string): Promise<CepResponse>;
    getCnpj(cnpj: string, paymentTxHash?: string): Promise<CnpjResponse>;
    getIndicators(paymentTxHash?: string): Promise<IndicatorsResponse>;
    getHolidays(year?: number, paymentTxHash?: string): Promise<HolidaysResponse>;
    isHealthy(serviceName?: string): Promise<boolean>;
    guard<T>(serviceName: string, action: () => Promise<T> | T, fallback?: () => Promise<T> | T): Promise<T>;
}

export interface ActionProviderOptions {
    baseUrl?: string;
    sandbox?: boolean;
}

export interface WalletProvider {
    transfer(params: { to: string; amount: string; assetId: string }): Promise<any>;
}

export declare class Grid402ActionProvider {
    baseUrl: string;
    sandbox: boolean;

    constructor(options?: ActionProviderOptions);

    getSummary(): Promise<SummaryResponse>;
    queryForex(walletProvider?: WalletProvider): Promise<ForexResponse>;
    queryCrypto(walletProvider?: WalletProvider, symbol?: string): Promise<CryptoResponse>;
    queryDns(domain: string, walletProvider?: WalletProvider): Promise<DnsResponse>;
    querySsl(host: string, walletProvider?: WalletProvider): Promise<SslResponse>;
    queryAsn(ip?: string, walletProvider?: WalletProvider): Promise<AsnResponse>;
    queryCep(code: string, walletProvider?: WalletProvider): Promise<CepResponse>;
    queryCnpj(cnpj: string, walletProvider?: WalletProvider): Promise<CnpjResponse>;
    queryIndicators(walletProvider?: WalletProvider): Promise<IndicatorsResponse>;
    queryHolidays(year?: number, walletProvider?: WalletProvider): Promise<HolidaysResponse>;

    getCnpj(cnpj: string, walletProvider?: WalletProvider): Promise<CnpjResponse>;
    getCep(code: string, walletProvider?: WalletProvider): Promise<CepResponse>;
    getForex(walletProvider?: WalletProvider): Promise<ForexResponse>;
    getCrypto(walletProvider?: WalletProvider, symbol?: string): Promise<CryptoResponse>;
    getDns(domain: string, walletProvider?: WalletProvider): Promise<DnsResponse>;
    getSsl(host: string, walletProvider?: WalletProvider): Promise<SslResponse>;
    getAsn(ip?: string, walletProvider?: WalletProvider): Promise<AsnResponse>;
    getIndicators(walletProvider?: WalletProvider): Promise<IndicatorsResponse>;
    getHolidays(year?: number, walletProvider?: WalletProvider): Promise<HolidaysResponse>;
}
